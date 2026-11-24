<?php

namespace App\Services;

use App\Models\ErrorLog;
use Throwable;

class ErrorLogService
{
    /**
     * Log an error or exception.
     *
     * @param string|Throwable $error
     * @param string|null $url
     * @param int|null $userId
     * @return ErrorLog
     */
    public function log($error, ?string $url = null, ?int $userId = null)
    {
        $errorType = null;
        $errorMessage = '';
        $stackTrace = null;

        if ($error instanceof Throwable) {
            $errorType = get_class($error);
            $errorMessage = $error->getMessage();
            $stackTrace = $error->getTraceAsString();
        } else {
            $errorMessage = (string) $error;
        }

        return ErrorLog::create([
            'user_id' => $userId ?? auth()->id(),
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'stack_trace' => $stackTrace,
            'url' => $url ?? request()->fullUrl(),
            'ip_address' => request()->ip(),
        ]);
    }
}
