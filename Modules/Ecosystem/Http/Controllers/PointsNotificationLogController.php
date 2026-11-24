<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\PointsNotificationLog;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PointsNotificationLogController extends Controller
{
    /**
     * عرض سجل الإشعارات
     */
    public function index(Request $request)
    {
        $query = PointsNotificationLog::with(['template', 'employee', 'client']);

        // فلترة حسب نوع المستخدم
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // فلترة حسب القناة
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فلترة حسب نوع الحدث (من خلال القالب)
        if ($request->filled('event_type')) {
            $query->whereHas('template', function ($q) use ($request) {
                $q->where('event_type', $request->event_type);
            });
        }

        // فلترة حسب التاريخ
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('middle_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // إحصائيات
        $stats = [
            'total' => PointsNotificationLog::count(),
            'pending' => PointsNotificationLog::where('status', NotificationStatus::PENDING->value)->count(),
            'sent' => PointsNotificationLog::where('status', NotificationStatus::SENT->value)->count(),
            'delivered' => PointsNotificationLog::where('status', NotificationStatus::DELIVERED->value)->count(),
            'failed' => PointsNotificationLog::where('status', NotificationStatus::FAILED->value)->count(),
            'read' => PointsNotificationLog::where('status', NotificationStatus::READ->value)->count(),
        ];

        $channels = NotificationChannel::cases();
        $statuses = NotificationStatus::cases();
        $userTypes = UserType::cases();
        $eventTypes = \App\Services\PointsNotificationService::getEventTypes();

        return view('ecosystem::points.notification-logs.index', compact(
            'logs', 'stats', 'channels', 'statuses', 'userTypes', 'eventTypes'
        ));
    }

    /**
     * عرض تفاصيل إشعار
     */
    public function show(PointsNotificationLog $notification_log)
    {
        $notification_log->load(['template', 'employee', 'client']);

        return view('ecosystem::points.notification-logs.show', compact('notification_log'));
    }

    /**
     * تعليم الإشعار كمقروء
     */
    public function markAsRead(PointsNotificationLog $notification_log)
    {
        if (!$notification_log->isRead()) {
            $notification_log->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تعليم الإشعار كمقروء',
        ]);
    }

    /**
     * تعليم مجموعة كمقروءة
     */
    public function bulkMarkAsRead(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:points_notification_logs,id',
        ]);

        PointsNotificationLog::whereIn('id', $request->ids)
            ->whereIn('status', [
                NotificationStatus::PENDING->value,
                NotificationStatus::SENT->value,
                NotificationStatus::DELIVERED->value,
            ])
            ->update([
                'status' => NotificationStatus::READ->value,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعليم الإشعارات المحددة كمقروءة',
        ]);
    }

    /**
     * تصدير السجل
     */
    public function export()
    {
        $logs = PointsNotificationLog::with(['template', 'employee', 'client'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $logs->map(function ($log) {
            $userName = '-';
            if ($log->user_type === UserType::EMPLOYEE && $log->employee) {
                $userName = $log->employee->full_name;
            } elseif ($log->client) {
                $userName = $log->client->full_name;
            }

            return [
                'العنوان' => $log->title,
                'المحتوى' => $log->body,
                'المستخدم' => $userName,
                'نوع المستخدم' => $log->user_type?->label() ?? '-',
                'القناة' => $log->channel?->label() ?? '-',
                'الحالة' => $log->status?->label() ?? '-',
                'نوع الحدث' => $log->template?->event_type ?? '-',
                'تاريخ الإرسال' => $log->sent_at?->format('Y-m-d H:i') ?? '-',
                'تاريخ القراءة' => $log->read_at?->format('Y-m-d H:i') ?? '-',
                'تاريخ الإنشاء' => $log->created_at->format('Y-m-d H:i'),
            ];
        });

        $filename = 'notification_logs_' . now()->format('Y-m-d_H-i-s') . '.json';

        return response()->json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }
}
