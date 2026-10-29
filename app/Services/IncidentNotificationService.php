<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;
use App\Support\DepartmentRouting;

class IncidentNotificationService
{
    public function notifyDepartments(Incident $incident): void
    {
        $departments = DepartmentRouting::departmentsForCategory($incident->category ?? $incident->incident_type);
        if ($departments === []) {
            return;
        }

        $recipients = User::whereIn('role', ['Head', 'Staff'])
            ->where('municipality', $incident->municipality)
            ->whereIn('department', $departments)
            ->get();

        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'incident_id' => $incident->id,
                'title' => 'New ' . ($incident->category ?? $incident->incident_type) . ' Report',
                'message' => 'Incident #' . $incident->display_number . ' — ' . ($incident->landmark ?: $incident->location_text),
                'status' => 'Unread',
            ]);
        }
    }
}
