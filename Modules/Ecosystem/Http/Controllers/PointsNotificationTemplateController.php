<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\PointsNotificationTemplate;
use App\Enums\NotificationChannel;
use App\Services\PointsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PointsNotificationTemplateController extends Controller
{
    /**
     * عرض قائمة قوالب الإشعارات
     */
    public function index(Request $request)
    {
        $query = PointsNotificationTemplate::withCount('logs');

        // فلترة حسب نوع الحدث
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // فلترة حسب الحالة
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // فلترة حسب القناة
        if ($request->filled('channel')) {
            $query->whereJsonContains('channels', $request->channel);
        }

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title_ar', 'like', "%{$search}%")
                  ->orWhere('title_en', 'like', "%{$search}%")
                  ->orWhere('body_ar', 'like', "%{$search}%")
                  ->orWhere('event_type', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // إحصائيات
        $stats = [
            'total' => PointsNotificationTemplate::count(),
            'active' => PointsNotificationTemplate::where('is_active', true)->count(),
            'inactive' => PointsNotificationTemplate::where('is_active', false)->count(),
            'total_sent' => \App\Models\Ecosystem\PointsNotificationLog::count(),
        ];

        $eventTypes = PointsNotificationService::getEventTypes();
        $channels = NotificationChannel::cases();

        return view('ecosystem::points.notification-templates.index', compact(
            'templates', 'stats', 'eventTypes', 'channels'
        ));
    }

    /**
     * صفحة إنشاء قالب جديد
     */
    public function create()
    {
        $eventTypes = PointsNotificationService::getEventTypes();
        $channels = NotificationChannel::cases();

        return view('ecosystem::points.notification-templates.create', compact('eventTypes', 'channels'));
    }

    /**
     * حفظ قالب جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_type' => 'required|string|max:100',
            'title_ar' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'body_ar' => 'required|string|max:2000',
            'body_en' => 'nullable|string|max:2000',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:' . implode(',', NotificationChannel::values()),
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // جلب المتغيرات من قائمة الأحداث
        $allEvents = PointsNotificationService::getEventTypes();
        $validated['variables'] = $allEvents[$validated['event_type']]['variables'] ?? [];

        PointsNotificationTemplate::create($validated);

        return redirect()
            ->route('ecosystem.points.notification-templates.index')
            ->with('success', 'تم إنشاء قالب الإشعار بنجاح');
    }

    /**
     * عرض تفاصيل القالب
     */
    public function show(PointsNotificationTemplate $notification_template)
    {
        $notification_template->load(['logs' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(20);
        }]);

        $eventTypes = PointsNotificationService::getEventTypes();
        $eventInfo = $eventTypes[$notification_template->event_type] ?? null;

        return view('ecosystem::points.notification-templates.show', compact('notification_template', 'eventInfo'));
    }

    /**
     * صفحة تعديل القالب
     */
    public function edit(PointsNotificationTemplate $notification_template)
    {
        $eventTypes = PointsNotificationService::getEventTypes();
        $channels = NotificationChannel::cases();

        return view('ecosystem::points.notification-templates.edit', compact('notification_template', 'eventTypes', 'channels'));
    }

    /**
     * تحديث القالب
     */
    public function update(Request $request, PointsNotificationTemplate $notification_template)
    {
        $validated = $request->validate([
            'event_type' => 'required|string|max:100',
            'title_ar' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'body_ar' => 'required|string|max:2000',
            'body_en' => 'nullable|string|max:2000',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:' . implode(',', NotificationChannel::values()),
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // تحديث المتغيرات
        $allEvents = PointsNotificationService::getEventTypes();
        $validated['variables'] = $allEvents[$validated['event_type']]['variables'] ?? [];

        $notification_template->update($validated);

        return redirect()
            ->route('ecosystem.points.notification-templates.index')
            ->with('success', 'تم تحديث قالب الإشعار بنجاح');
    }

    /**
     * حذف القالب
     */
    public function destroy(PointsNotificationTemplate $notification_template)
    {
        $notification_template->delete();

        return redirect()
            ->route('ecosystem.points.notification-templates.index')
            ->with('success', 'تم حذف قالب الإشعار بنجاح');
    }

    /**
     * تبديل حالة القالب
     */
    public function toggleStatus(PointsNotificationTemplate $notification_template)
    {
        $notification_template->update([
            'is_active' => !$notification_template->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة القالب بنجاح',
            'is_active' => $notification_template->is_active,
        ]);
    }

    /**
     * حذف مجمع
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:points_notification_templates,id',
        ]);

        PointsNotificationTemplate::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف القوالب المحددة بنجاح',
        ]);
    }

    /**
     * تصدير القوالب
     */
    public function export()
    {
        $templates = PointsNotificationTemplate::withCount('logs')->get();

        $data = $templates->map(function ($template) {
            return [
                'نوع الحدث' => $template->event_type,
                'العنوان' => $template->title_ar,
                'القنوات' => implode(', ', array_map(fn($c) => NotificationChannel::from($c)->label(), $template->getChannels())),
                'المتغيرات' => implode(', ', $template->getVariables()),
                'الحالة' => $template->is_active ? 'نشط' : 'غير نشط',
                'عدد الإرسال' => $template->logs_count,
                'تاريخ الإنشاء' => $template->created_at->format('Y-m-d H:i'),
            ];
        });

        $filename = 'notification_templates_' . now()->format('Y-m-d_H-i-s') . '.json';

        return response()->json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }
}
