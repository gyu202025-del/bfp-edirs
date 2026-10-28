<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function select()
    {
        return view('report.select');
    }

    public function create(string $category)
    {
        $map = ['fire' => 'Fire', 'medical' => 'Medical', 'crime' => 'Crime/Security', 'accident' => 'Accident'];
        abort_unless(isset($map[$category]), 404);

        return view('report.create', [
            'category' => $map[$category],
            'slug' => $category,
            'municipalities' => config('leyte.municipalities'),
        ]);
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
        $data['incident_type'] = $data['category']; // keep in sync with Step 1's incident_type column
        $data['report_source'] = 'Mobile App';
        $data['status'] = 'Pending';
        $data['reported_at'] = now();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('incidents', 'public');
        }

        $incident = Incident::create($data);

        // Alert the Head and Staff of the municipality where the incident happened
        $recipients = \App\Models\User::whereIn('role', ['Head', 'Staff'])
            ->where('municipality', $data['municipality'])
            ->get();

        foreach ($recipients as $recipient) {
            \App\Models\Notification::create([
                'user_id' => $recipient->id,
                'incident_id' => $incident->id,
                'title' => 'New ' . $incident->category . ' Report',
                'message' => $incident->incident_number . ' — ' . ($incident->landmark ?: $incident->barangay) . ', ' . $incident->municipality,
                'status' => 'Unread',
            ]);
        }

        return view('report.confirmation', ['incident' => $incident]);
    }
}