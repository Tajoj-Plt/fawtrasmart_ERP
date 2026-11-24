<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log as ModelsLog;
use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\SmsTemplate;
use App\Services\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * إعدادات بوابة الرسائل النصية.
 *
 * كانت الشاشة أربعة حقول تُحفظ بـ `DB::table()->update()`، وكل ما عداها
 * مكتوبٌ في الكود. صارت الآن تسعة أقسام، وكلّها تُقرأ في `SmsGateway`
 * قبل كل إرسال — لا حقلَ هنا بلا أثر.
 */
class SmsController extends Controller
{
    /**
     * أقسام الشاشة: الرمز ← الأيقونة.
     *
     * التقسيم في الكود لا في القالب كي تبقى التبويبات والحقول متطابقة،
     * ولئلّا يُنسى قسمٌ عند إضافة إعداد. التسميات في ملف الترجمة تحت
     * `settings.sms.sections` — انظر `sections()`.
     */
    public const SECTIONS = [
        'gateway' => 'server',
        'message' => 'edit-3',
        'timing' => 'clock',
        'limits' => 'sliders',
        'retry' => 'refresh-cw',
        'safety' => 'shield',
        'cost' => 'dollar-sign',
        'logging' => 'database',
        'alerts' => 'bell',
    ];

    public function __construct(protected SmsGateway $gateway)
    {
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index()
    {
        $settings = SmsSetting::current();

        // الشاشة تعرض حالة الاتصال وآخر ما أُرسل، لا حقول الإدخال وحدها:
        // كان المستخدم يحفظ ولا يعرف أوصلت رسالة واحدة أم لا
        return view('sitting::sms.index', [
            'settings' => $settings,
            'smsSettings' => $settings,
            'sections' => $this->sections(),
            'providers' => SmsSetting::PROVIDERS,
            'configured' => $settings->isConfigured(),
            'templateCount' => SmsTemplate::active()->count(),
            'recentLogs' => SmsLog::latest('id')->limit(6)->get(),
            'weekDays' => $this->weekDays(),
            'timezones' => $this->timezones(),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * فحص الاتصال بالبوابة عبر سؤالها عن الرصيد — يثبت صحة البيانات
     * دون استهلاك رسالة.
     */
    public function checkBalance()
    {
        return response()->json($this->gateway->checkBalance());
    }

    public function store(Request $request)
    {
        $existing = SmsSetting::query()->orderBy('id')->first();

        $data = $this->validated($request, hasStoredKey: filled($existing?->api_key));

        /* المفتاحُ لا يُعاد إلى الشاشة بعد حفظه، فحقلُه يصل فارغاً في كل حفظٍ
           لا يُراد به تغييرُه — والفارغُ يعني «أبقِ المحفوظ» لا «امسحه». */
        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        if ($existing) {
            $existing->update($data);
            $message = __('settings.sms.updated');
            $logText = __('settings.sms.log_updated');
            $id = $existing->id;
        } else {
            $created = SmsSetting::create($data);
            $message = __('settings.sms.created');
            $logText = __('settings.sms.log_created');
            $id = $created->id;
        }

        SmsSetting::flush();

        ModelsLog::create([
            'type' => 'setting',
            'type_id' => $id,
            'type_log' => 'log',
            'description' => $logText,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('Sms.index')
            ->with('success', $message)
            ->withFragment('tab-'.$request->input('active_section', 'gateway'));
    }

    public function sendTestSmsAjax(Request $request)
    {
        $phone = (string) $request->input('phone');

        if ($phone === '') {
            return response()->json(['success' => false, 'error' => __('settings.sms.invalid_phone')]);
        }

        if (! $this->gateway->isConfigured()) {
            return response()->json(['success' => false, 'error' => __('settings.sms.save_first')]);
        }

        $template = SmsTemplate::active()->where('code', 'test')->first();

        $body = $template
            ? $template->render(['business' => config('app.name')])
            : __('settings.sms.default_test_body');

        $result = $this->gateway->send($phone, $body, auth()->id(), $template?->code);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'status' => $result['status'] ?? null,
            'error' => $result['success'] ? null : $result['message'],
        ]);
    }

    // ==================== الداخل ====================

    /**
     * أقسام الشاشة: الرمز ← [التسمية، الأيقونة، الشرح].
     *
     * الأيقونة في الثابت والتسمية في ملف الترجمة — نصٌّ عربيّ مكتوب في
     * الكود لا يُترجَم، والشاشة تُعرض بالإنجليزية أيضاً.
     */
    protected function sections(): array
    {
        $out = [];

        foreach (self::SECTIONS as $key => $icon) {
            $out[$key] = [
                __('settings.sms.sections.'.$key),
                $icon,
                __('settings.sms.sections.'.$key.'_hint'),
            ];
        }

        return $out;
    }

    /**
     * إحصاء الإرسال — الحالات الجديدة تُعدّ على حدة.
     *
     * جمع «مؤجَّلة» و«ممنوعة» تحت «فشلت» يجعل الشاشة تتّهم البوابة بخطأ
     * سببه إعدادٌ ضبطه المستخدم بنفسه.
     */
    protected function stats(): array
    {
        $counts = SmsLog::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $of = fn (array $keys) => collect($keys)->sum(fn ($key) => (int) ($counts[$key] ?? 0));

        return [
            'total' => (int) $counts->sum(),
            'sent' => $of(['sent']),
            'failed' => $of(['failed']),
            'pending' => $of(['deferred', 'retry', 'processing']),
            'withheld' => $of(['blocked', 'suppressed', 'capped']),
            'test' => $of(['test']),
            'today' => SmsLog::where('status', 'sent')->where('created_at', '>=', now()->startOfDay())->count(),
            'month_cost' => (float) SmsLog::whereIn('status', ['sent', 'test'])
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('cost'),
        ];
    }

    /** أيام الأسبوع بترقيم Carbon — الأحد صفر */
    protected function weekDays(): array
    {
        return [
            0 => __('settings.sms.days.sunday'),
            1 => __('settings.sms.days.monday'),
            2 => __('settings.sms.days.tuesday'),
            3 => __('settings.sms.days.wednesday'),
            4 => __('settings.sms.days.thursday'),
            5 => __('settings.sms.days.friday'),
            6 => __('settings.sms.days.saturday'),
        ];
    }

    /** المناطق الزمنية الشائعة في المنطقة، ثم منطقة التطبيق إن خرجت عنها */
    protected function timezones(): array
    {
        $zones = [
            'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Bahrain',
            'Asia/Muscat', 'Asia/Baghdad', 'Asia/Amman', 'Asia/Beirut', 'Asia/Damascus',
            'Asia/Aden', 'Africa/Cairo', 'Africa/Khartoum', 'Africa/Tripoli',
            'Africa/Tunis', 'Africa/Algiers', 'Africa/Casablanca', 'UTC',
        ];

        $appZone = config('app.timezone');

        if ($appZone && ! in_array($appZone, $zones, true)) {
            array_unshift($zones, $appZone);
        }

        return $zones;
    }

    /**
     * التحقّق من كل حقول الأقسام التسعة.
     *
     * الحدود العليا ليست اعتباطاً: سقف يومي بمليون رسالة أو مهلة اتصال
     * بساعة كاملة كلاهما خطأ إدخال لا نيّة.
     */
    protected function validated(Request $request, bool $hasStoredKey = false): array
    {
        $rules = [
            // ---------- البوابة ----------
            'provider' => ['required', Rule::in(array_keys(SmsSetting::PROVIDERS))],
            'username' => ['required', 'string', 'max:255'],
            // مطلوبٌ في أوّل ضبطٍ فقط: بعده يبقى المحفوظُ ما لم يُكتب غيرُه
            'api_key' => [$hasStoredKey ? 'nullable' : 'required', 'string', 'max:500'],
            'api_url' => ['nullable', 'url', 'max:300'],
            'sender_name' => ['required', 'string', 'max:255'],
            'fallback_sender_name' => ['nullable', 'string', 'max:255'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:120'],

            // ---------- صياغة الرسالة ----------
            'default_country_code' => ['required', 'string', 'max:6', 'regex:/^\d{1,5}$/'],
            'allowed_country_codes' => ['nullable', 'string', 'max:255'],
            'encoding_mode' => ['required', Rule::in(SmsSetting::ENCODING_MODES)],
            'max_segments' => ['required', 'integer', 'min:1', 'max:10'],
            'signature_text' => ['nullable', 'string', 'max:120'],
            'opt_out_text' => ['nullable', 'string', 'max:120'],

            // ---------- أوقات الإرسال ----------
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'quiet_hours_action' => ['required', Rule::in(SmsSetting::QUIET_ACTIONS)],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['integer', 'between:0,6'],
            'timezone' => ['nullable', 'timezone'],

            // ---------- الحدود ----------
            'rate_limit_per_minute' => ['required', 'integer', 'min:0', 'max:10000'],
            'daily_cap' => ['required', 'integer', 'min:0', 'max:1000000'],
            'monthly_cap' => ['required', 'integer', 'min:0', 'max:10000000'],
            'per_recipient_daily_cap' => ['required', 'integer', 'min:0', 'max:1000'],
            'duplicate_window_minutes' => ['required', 'integer', 'min:0', 'max:1440'],

            // ---------- إعادة المحاولة ----------
            'retry_attempts' => ['required', 'integer', 'min:0', 'max:10'],
            'retry_delay_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'retry_backoff' => ['required', Rule::in(SmsSetting::BACKOFF_MODES)],

            // ---------- الاختبار والحماية ----------
            'test_redirect_number' => ['nullable', 'string', 'max:20'],
            'blocked_numbers' => ['nullable', 'string', 'max:20000'],
            'allowed_numbers' => ['nullable', 'string', 'max:20000'],

            // ---------- الكلفة ----------
            'low_balance_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'cost_per_segment' => ['required', 'numeric', 'min:0', 'max:100'],
            'cost_currency' => ['required', 'string', 'max:10'],

            // ---------- السجل ----------
            'log_retention_days' => ['required', 'integer', 'min:0', 'max:3650'],

            // ---------- التنبيه ----------
            'failure_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'failure_notify_emails' => ['nullable', 'string', 'max:500'],
        ];

        $data = $request->validate($rules, [
            'default_country_code.regex' => __('settings.sms.errors.country_code'),
            'api_url.url' => __('settings.sms.errors.api_url'),
            'quiet_hours_start.date_format' => __('settings.sms.errors.time_format'),
            'quiet_hours_end.date_format' => __('settings.sms.errors.time_format'),
        ]);

        // مفاتيح الدول تُكتب نصّاً مفصولاً بفواصل وتُحفظ مصفوفة
        $data['allowed_country_codes'] = collect(preg_split('/[\s,;]+/', (string) $request->input('allowed_country_codes')))
            ->map(fn ($code) => preg_replace('/\D+/', '', (string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $data['working_days'] = array_values(array_map('intval', (array) $request->input('working_days', [])));

        // المفاتيح المنطقية: الحقل غير المؤشَّر لا يُرسَل أصلاً، فقراءته
        // بـ `boolean` هي الطريقة الوحيدة التي تفرّق «معطّل» عن «غائب»
        foreach ([
            'is_active', 'allow_international', 'strip_emoji', 'collapse_whitespace',
            'append_signature', 'append_opt_out', 'quiet_hours_enabled', 'respect_working_days',
            'retry_enabled', 'test_mode', 'whitelist_enabled', 'low_balance_alert',
            'log_enabled', 'log_body', 'mask_recipient', 'notify_on_failure',
        ] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }
}
