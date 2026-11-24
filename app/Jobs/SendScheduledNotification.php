<?php

namespace App\Jobs;

use App\Models\notifications;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $notificationData;
    protected $employeeIds;

    /**
     * Create a new job instance.
     */
    public function __construct($notificationData, $employeeIds = [])
    {
        $this->notificationData = $notificationData;
        $this->employeeIds = $employeeIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting scheduled notification job', [
                'title' => $this->notificationData['title'],
                'employee_count' => count($this->employeeIds)
            ]);

            // إذا كان الإرسال لجميع الموظفين
            if (empty($this->employeeIds)) {
                $employees = User::where('role', 'employee')->get();
                foreach ($employees as $employee) {
                    $this->sendNotificationToEmployee($employee->id);
                }
            } else {
                // إرسال للموظفين المحددين
                foreach ($this->employeeIds as $employeeId) {
                    $this->sendNotificationToEmployee($employeeId);
                }
            }

            Log::info('Scheduled notification sent successfully');

        } catch (\Exception $e) {
            Log::error('Error sending scheduled notification: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * إرسال إشعار لموظف محدد
     */
    protected function sendNotificationToEmployee($employeeId)
    {
        notifications::create([
            'user_id' => $this->notificationData['user_id'],
            'receiver_id' => $employeeId,
            'title' => $this->notificationData['title'],
            'description' => $this->notificationData['description'],
            'type' => $this->notificationData['type'] ?? 'general',
            'read' => 0,
            'is_scheduled' => true,
            'is_sent' => true,
            'scheduled_at' => $this->notificationData['scheduled_at'] ?? now()
        ]);
    }
}
