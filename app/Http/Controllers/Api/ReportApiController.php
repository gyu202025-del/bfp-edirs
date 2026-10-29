<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Services\IncidentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportApiController extends Controller
{
    public function categories()
    {
        return response()->json([
            ['slug' => 'fire', 'label' => 'Fire', 'agency' => 'BFP'],
            ['slug' => 'medical', 'label' => 'Medical', 'agency' => 'Ambulance'],
            ['slug' => 'crime', 'label' => 'Crime/Security', 'agency' => 'PNP'],
            ['slug' => 'accident', 'label' => 'Accident', 'agency' => 'Roadside/Vehicular'],
        ]);
    }

    public function municipalities()
    {
        return response()->json(config('leyte.municipalities'));
    }

    public function store(Request $request, IncidentNotificationService $notifications)
    {
        $data = $request->validate([
            'category' => 'required|in:Fire,Medical,Crime/Security,Accident',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'landmark' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'image' => 'nullable|image|max:5120',
        ]);

        $data['incident_type'] = $data['category'];
        $data['status'] = 'Pending';
        $data['reported_at'] = now();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('incidents', 'public');
        }

        $incident = DB::transaction(function () use ($data, $notifications) {
            $incident = Incident::create($data);
            $notifications->assignDepartments($incident);

            return $incident;
        });
        $notifications->notifyAssignedDepartments($incident);

        return response()->json([
            'success' => true,
            // String on purpose: the app's confirmation screen expects text, not a number
            'incident_number' => (string) $incident->display_number,
            'message' => 'Report submitted successfully.',
        ], 201);
    }
}