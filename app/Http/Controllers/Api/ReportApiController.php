<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|in:Fire,Medical,Crime/Security,Accident',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'reporter_name' => 'nullable|string|max:255',
            'reporter_contact' => 'nullable|string|max:20',
            'landmark' => 'nullable|string|max:255',
            'description' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'image' => 'nullable|image|max:5120',
        ]);

        $data['incident_number'] = 'INC-' . now()->format('Y') . '-' . str_pad((Incident::count() + 1), 4, '0', STR_PAD_LEFT);
        $data['incident_type'] = $data['category'];
        $data['report_source'] = 'Mobile App';
        $data['status'] = 'Pending';
        $data['reported_at'] = now();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('incidents', 'public');
        }

        $incident = Incident::create($data);

        $heads = User::where('role', 'Head')->where('municipality', $data['municipality'])->get();
        foreach ($heads as $head) {
            Notification::create([
                'user_id' => $head->id,
                'incident_id' => $incident->id,
                'title' => 'New ' . $incident->category . ' Report',
                'message' => $incident->incident_number . ' — ' . ($incident->landmark ?: $incident->barangay) . ', ' . $incident->municipality,
                'status' => 'Unread',
            ]);
        }

        return response()->json([
            'success' => true,
            'incident_number' => $incident->incident_number,
            'message' => 'Report submitted successfully.',
        ], 201);
    }
}