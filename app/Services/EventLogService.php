<?php

namespace App\Services;

use App\Models\EventLog;

class EventLogService
{
    /**
     * Log a system event.
     *
     * @param string $action
     * @param string|null $description
     * @param int|null $userId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return EventLog
     */
    public function log(string $action, ?string $description = null, ?int $userId = null, ?string $ipAddress = null, ?string $userAgent = null)
    {
        return EventLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
        ]);
    }
}
