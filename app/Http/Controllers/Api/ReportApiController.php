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
            'landmark' => 'nullable|string|max:255',
            'description' => 'required|string',
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

        $incident = Incident::create($data);

        // Alert the Head and Staff of the municipality where the incident happened
        $recipients = User::whereIn('role', ['Head', 'Staff'])->where('municipality', $data['municipality'])->get();
        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'incident_id' => $incident->id,
                'title' => 'New ' . $incident->category . ' Report',
                'message' => 'Incident #' . $incident->display_number . ' — ' . ($incident->landmark ?: $incident->location_text),
                'status' => 'Unread',
            ]);
        }

        return response()->json([
            'success' => true,
            // String on purpose: the app's confirmation screen expects text, not a number
            'incident_number' => (string) $incident->display_number,
            'message' => 'Report submitted successfully.',
        ], 201);
    }
}