<?php

namespace App\Listeners;

use App\Models\ActivityLog;

class LogActivity
{
    public function handle(object $event): void
    {
        $action = class_basename($event);
        $userId = null;
        $subjectType = null;
        $subjectId = null;

        if (property_exists($event, 'case')) {
            $userId = $event->case->user_id;
            $subjectType = get_class($event->case);
            $subjectId = $event->case->id;
        } elseif (property_exists($event, 'document')) {
            $userId = $event->document->user_id;
            $subjectType = get_class($event->document);
            $subjectId = $event->document->id;
        } elseif (property_exists($event, 'message')) {
            $userId = $event->userId ?? null;
            $subjectType = get_class($event->message);
            $subjectId = $event->message->id;
        }

        ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
    }
}
