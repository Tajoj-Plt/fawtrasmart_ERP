<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\notifications;
use App\Models\NotificationSetting;
use App\Models\SmsTemplate;
use App\Services\MailerConfigurator;
use App\Services\NotificationDispatcher;
use App\Services\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * إعدادات الإشعارات.
 *
 * نفس تقسيم قوالب المستندات وقوالب الرسائل: قائمة بإحصاء وتصفية، وصفحة
 * تحرير مستقلة لكل نوع. المعروض هنا يتحكّم فعلياً بما يُكتب في جدول
 * `notifications` — البوابة مربوطة بأحداث النموذج نفسه.
 */
class NotificationSettingController extends Controller
{
    public function __construct(
        protected SmsGateway $gateway,
        protected MailerConfigurator $mailer,
    ) {
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index(Request $request)
    {
        $query = NotificationSetting::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        // `filled` يسقط «0»، وتصفية المعطَّل مقصودة
        if ($request->input('is_enabled') !== null && $request->input('is_enabled') !== '') {
            $query->where('is_enabled', (bool) $request->input('is_enabled'));
        }

        if ($request->filled('channel')) {
            $query->where('channels', 'like', '%"'.$request->string('channel')->toString().'"%');
        }

        $settings = $query->orderBy('sort_order')->orderBy('label')->get();

        return view('sitting::notification_settings.index', [
            'settings' => $settings->groupBy('category'),
            'categories' => NotificationSetting::categories(),
            'channels' => NotificationSetting::channels(),
            'total' => NotificationSetting::count(),
            'enabledCount' => NotificationSetting::where('is_enabled', true)->count(),
            'discoveredCount' => NotificationSetting::where('is_discovered', true)->count(),
            'counts' => $this->countsByType(),
            'mailReady' => $this->mailer->apply(),
            'smsReady' => $this->gateway->isConfigured(),
        ]);
    }

    public function edit(NotificationSetting $notificationSetting)
    {
        return view('sitting::notification_settings.form', [
            'setting' => $notificationSetting,
            'categories' => NotificationSetting::categories(),
            'channels' => NotificationSetting::channels(),
            'recipients' => NotificationSetting::recipients(),
            'smsTemplates' => SmsTemplate::where('is_active', true)->orderBy('name')->get(),
            'mailReady' => $this->mailer->apply(),
            'smsReady' => $this->gateway->isConfigured(),
            'stats' => $this->statsFor($notificationSetting->key),
        ]);
    }

    public function update(Request $request, NotificationSetting $notificationSetting)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['required', Rule::in(array_keys(NotificationSetting::CATEGORIES))],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(array_keys(NotificationSetting::CHANNELS))],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => [Rule::in(array_keys(NotificationSetting::RECIPIENTS))],
            'sms_template_code' => ['nullable', 'string', 'max:60', Rule::exists('sms_templates', 'code')],
        ], [
            'label.required' => __('settings.notify.errors.label_required'),
            'category.in' => __('settings.notify.errors.category_in'),
        ]);

        $channels = $data['channels'] ?? [];
        // القناة الداخلية هي جوهر الإشعار: إيقافها يساوي تعطيل النوع،
        // وذلك له مفتاحه المستقلّ — فتبقى مضمّنة دائماً
        if (! in_array('database', $channels, true)) {
            array_unshift($channels, 'database');
        }

        $notificationSetting->update([
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'channels' => array_values($channels),
            'recipients' => array_values($data['recipients'] ?? ['receiver']),
            'sms_template_code' => $data['sms_template_code'] ?? null,
            'is_enabled' => $request->boolean('is_enabled'),
            // بمجرّد مراجعته صار مصنَّفاً
            'is_discovered' => false,
        ]);

        return redirect()->route('NotificationSettings.index')->with('success', __('settings.notify.saved'));
    }

    public function toggle(NotificationSetting $notificationSetting)
    {
        $notificationSetting->update(['is_enabled' => ! $notificationSetting->is_enabled]);

        return back()->with(
            'success',
            $notificationSetting->is_enabled
                ? __('settings.notify.enabled_msg', ['name' => $notificationSetting->label])
                : __('settings.notify.disabled_msg', ['name' => $notificationSetting->label])
        );
    }

    /** تفعيل أو إيقاف فئة كاملة بنقرة */
    public function toggleCategory(Request $request, string $category)
    {
        abort_unless(array_key_exists($category, NotificationSetting::CATEGORIES), 404);

        $enable = $request->boolean('enable');

        $affected = NotificationSetting::where('category', $category)->update(['is_enabled' => $enable]);

        // `update` الجماعي لا يطلق أحداث النموذج، فتُسقَط الذاكرة يدوياً
        NotificationSetting::flush();

        return back()->with('success', __(
            $enable ? 'settings.notify.bulk_enabled' : 'settings.notify.bulk_disabled',
            ['count' => $affected]
        ));
    }

    /**
     * إرسال إشعار تجريبي فعلي — الفحص الوحيد الذي يُعتدّ به.
     */
    public function test(Request $request, NotificationSetting $notificationSetting)
    {
        if (! $notificationSetting->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => __('settings.notify.type_off'),
            ]);
        }

        $notification = notifications::create([
            'user_id' => auth()->id(),
            'receiver_id' => auth()->id(),
            'title' => __('settings.notify.test_title', ['name' => $notificationSetting->label]),
            'description' => __('settings.notify.test_body'),
            'type' => $notificationSetting->key,
            'read' => 0,
        ]);

        if (! $notification->exists) {
            return response()->json([
                'success' => false,
                'message' => __('settings.notify.test_rejected'),
            ]);
        }

        $lines = [__('settings.notify.test_created', ['id' => $notification->id])];

        $channels = $notificationSetting->channels ?: ['database'];

        if (in_array('mail', $channels, true)) {
            $emails = NotificationDispatcher::mailRecipients($notification);
            $lines[] = $emails === []
                ? __('settings.notify.test_no_email')
                : __('settings.notify.test_emailed', ['list' => implode(__('settings.common.list_separator'), $emails)]);
        }

        if (in_array('sms', $channels, true)) {
            if (! $this->gateway->isConfigured()) {
                $lines[] = __('settings.notify.test_sms_unready');
            } else {
                $phones = NotificationDispatcher::smsRecipients($notification);
                $lines[] = $phones === []
                    ? __('settings.notify.test_no_phone')
                    : __('settings.notify.test_smsed', ['list' => implode(__('settings.common.list_separator'), $phones)]);
            }
        }

        return response()->json(['success' => true, 'message' => implode("\n", $lines)]);
    }

    // ==================== الداخل ====================

    /**
     * عدد الإشعارات القائمة لكل نوع — يوضّح أثر إيقاف أيٍّ منها.
     *
     * @return array<string, int>
     */
    private function countsByType(): array
    {
        return DB::table('notifications')
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function statsFor(string $key): array
    {
        $base = DB::table('notifications')->where('type', $key);

        return [
            'total' => (clone $base)->count(),
            'unread' => (clone $base)->where('read', 0)->count(),
            'last30' => (clone $base)->where('created_at', '>=', now()->subDays(30))->count(),
            'latest' => (clone $base)->orderByDesc('id')->first(),
        ];
    }
}
