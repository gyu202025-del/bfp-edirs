<?php
 
namespace App\Http\Controllers;
 
use App\Models\Incident;
use App\Services\IncidentNotificationService;
use Illuminate\Http\Request;
 
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
 
    public function store(Request $request, IncidentNotificationService $notifications)
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
 
        $data['incident_type'] = $data['category']; // keep in sync with Step 1's incident_type column
        $data['status'] = 'Pending';
        $data['reported_at'] = now();
 
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('incidents', 'public');
        }
 
        $incident = Incident::create($data);
 
        $notifications->notifyDepartments($incident);
 
        return view('report.confirmation', ['incident' => $incident]);
    }
}