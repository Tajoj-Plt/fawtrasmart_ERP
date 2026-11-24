<?php

namespace App\Services;

use App\Models\SmsLog;

class SmsLogService
{
    /**
     * Log an SMS message.
     *
     * @param string $recipient
     * @param string $message
     * @param string $status
     * @param string|null $gatewayResponse
     * @param int|null $userId
     * @return SmsLog
     */
    public function log(string $recipient, string $message, string $status, ?string $gatewayResponse = null, ?int $userId = null)
    {
        return SmsLog::create([
            'user_id' => $userId ?? auth()->id(),
            'recipient' => $recipient,
            'message' => $message,
            'status' => $status,
            'gateway_response' => $gatewayResponse,
        ]);
    }
}
