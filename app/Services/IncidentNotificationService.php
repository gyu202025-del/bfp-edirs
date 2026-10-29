<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\Notification;
use App\Models\User;
use App\Support\DepartmentRouting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class IncidentNotificationService
{
    public function assignDepartments(Incident $incident): void
    {
        $departmentIds = DepartmentRouting::departmentIdsForCategory($incident->category ?? $incident->incident_type);
        if ($departmentIds === []) {
            throw new \LogicException('The incident category has no configured department assignments.');
        }

        foreach ($departmentIds as $departmentId) {
            IncidentAssignment::firstOrCreate(
                ['incident_id' => $incident->id, 'department_id' => $departmentId],
                ['status' => 'Pending', 'assigned_at' => now()]
            );
        }
    }

    public function notifyAssignedDepartments(Incident $incident): void
    {
        try {
            $assignments = $incident->assignments()->with('department')->get();
            $recipients = User::whereHas('roleRecord', fn ($query) => $query->where('scope', 'department'))
                ->where('status', 'Active')
                ->whereIn('department_id', $assignments->pluck('department_id'))
                ->get();
        } catch (QueryException $exception) {
            Log::error('Could not find recipients for department incident notifications.', [
                'incident_id' => $incident->id,
                'exception' => $exception,
            ]);
            return;
        }

        foreach ($recipients as $recipient) {
            $title = 'New ' . ($incident->category ?? $incident->incident_type) . ' Report';
            try {
                if (!Notification::where('user_id', $recipient->id)->where('incident_id', $incident->id)->where('title', $title)->exists()) {
                    Notification::create([
                        'user_id' => $recipient->id,
                        'incident_id' => $incident->id,
                        'title' => $title,
                        'message' => 'Incident #' . $incident->display_number . ' — ' . ($incident->landmark ?: $incident->location_text),
                        'status' => 'Unread',
                    ]);
                }
            } catch (QueryException $exception) {
                Log::error('Could not create an incident department notification.', [
                    'incident_id' => $incident->id,
                    'user_id' => $recipient->id,
                    'exception' => $exception,
                ]);
            }
        }
    }
}
