<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * إدارة نصوص الرسائل النصية التي يرسلها النظام.
 *
 * التقسيم نفسه المتّبع في قوالب المستندات: قائمة بإحصاء وتصفية، وصفحة
 * تحرير مستقلة لكل قالب — بدل تحرير الجميع في صفحة واحدة.
 */
class SmsTemplateController extends Controller
{
    /**
     * المتغيّرات المتاحة داخل نصّ الرسالة، مجموعةً كما في لوحة حقول
     * قوالب المستندات: المفتاح ← [التسمية، مثال للقيمة].
     */
    /**
     * المتغيّرات المتاحة داخل نصّ الرسالة، مجموعةً كما في لوحة حقول
     * قوالب المستندات: المفتاح ← [التسمية، مثال للقيمة].
     *
     * تغطّي كلّ ما يستعمله كتالوج القوالب. المتغيّر الذي يكتبه المستخدم
     * ولا وجود له هنا يُحذف من الرسالة وقت الإرسال بلا تنبيه — فنقصان
     * هذه اللوحة يعني رسائل تصل ناقصة.
     */
    protected const VARIABLE_GROUPS = [
        'business' => [
            'label' => 'بيانات المنشأة',
            'icon' => 'home',
            'fields' => [
                'business' => ['اسم المنشأة', 'مؤسسة الحلول الذكية'],
                'phone' => ['هاتف المنشأة', '0111234567'],
                'link' => ['رابط', 'https://example.com/x'],
            ],
        ],
        'people' => [
            'label' => 'العميل والموظف',
            'icon' => 'user',
            'fields' => [
                'customer' => ['اسم العميل', 'عميل تجريبي'],
                'employee' => ['اسم الموظف', 'أحمد المحمد'],
            ],
        ],
        'document' => [
            'label' => 'بيانات المستند',
            'icon' => 'file-text',
            'fields' => [
                'number' => ['رقم المستند', 'INV-2026-000042'],
                'date' => ['تاريخ اليوم', '2026-08-17'],
                'due_date' => ['تاريخ الاستحقاق', '2026-09-16'],
                'time' => ['الوقت', '10:30'],
                'location' => ['المكان أو الفرع', 'الفرع الرئيسي'],
                'tracking' => ['رقم التتبّع', 'TRK-8891023'],
            ],
        ],
        'totals' => [
            'label' => 'المبالغ',
            'icon' => 'dollar-sign',
            'fields' => [
                'amount' => ['المبلغ', '1,092.50'],
                'remaining' => ['المتبقي', '92.50'],
                'balance' => ['الرصيد', '450.00'],
                'currency' => ['العملة', 'ر.س'],
                'discount' => ['نسبة الخصم', '15'],
            ],
        ],
        'loyalty' => [
            'label' => 'الولاء والعضوية',
            'icon' => 'award',
            'fields' => [
                'points' => ['عدد النقاط', '250'],
                'tier' => ['الفئة', 'الفئة الذهبية'],
            ],
        ],
        'misc' => [
            'label' => 'متغيّرات عامة',
            'icon' => 'more-horizontal',
            'fields' => [
                'item' => ['الصنف', 'جهاز لوحي'],
                'days' => ['عدد الأيام', '5'],
                'count' => ['العدد', '3'],
                'code' => ['رمز التحقّق', '482913'],
                'minutes' => ['عدد الدقائق', '10'],
                'message' => ['نصّ حرّ', 'تفاصيل إضافية'],
            ],
        ],
    ];

    /**
     * صياغات جاهزة تُدرج بنقرة — مقابل «الكتل الجاهزة» في قوالب المستندات.
     */
    protected const PRESETS = [
        'invoice' => [
            'label' => 'إشعار فاتورة',
            'icon' => 'file-text',
            'hint' => 'إبلاغ العميل بصدور فاتورته',
            'body' => 'عميلنا {customer}، صدرت فاتورتك رقم {number} بمبلغ {amount}. شكراً لتعاملك مع {business}.',
        ],
        'payment' => [
            'label' => 'إشعار استلام دفعة',
            'icon' => 'check-circle',
            'hint' => 'تأكيد استلام مبلغ وبيان المتبقي',
            'body' => 'استلمنا دفعتك بمبلغ {amount} على الفاتورة {number}. المتبقي {remaining}. {business}',
        ],
        'reminder' => [
            'label' => 'تذكير استحقاق',
            'icon' => 'clock',
            'hint' => 'تنبيه قبل موعد السداد',
            'body' => 'تذكير: فاتورتك رقم {number} بمبلغ {amount} تستحق بتاريخ {due_date}. {business}',
        ],
        'overdue' => [
            'label' => 'تنبيه تأخر سداد',
            'icon' => 'alert-triangle',
            'hint' => 'صياغة حازمة ومهذّبة',
            'body' => 'عميلنا {customer}، الفاتورة {number} بمبلغ {amount} تجاوزت موعد استحقاقها. نرجو السداد. {business}',
        ],
        'welcome' => [
            'label' => 'ترحيب بعميل',
            'icon' => 'smile',
            'hint' => 'رسالة أولى بعد التسجيل',
            'body' => 'أهلاً بك {customer} في {business}. سعداء بانضمامك، وفريقنا في خدمتك على {phone}.',
        ],
        'ready' => [
            'label' => 'جاهزية الطلب',
            'icon' => 'package',
            'hint' => 'إبلاغ العميل بجاهزية طلبه',
            'body' => 'عميلنا {customer}، طلبك رقم {number} جاهز للاستلام. {business}',
        ],
        'thanks' => [
            'label' => 'شكر بعد الشراء',
            'icon' => 'heart',
            'hint' => 'رسالة قصيرة تُحتسب رسالة واحدة',
            'body' => 'شكراً {customer} على ثقتك بـ {business}. نسعد بخدمتك دائماً.',
        ],
    ];

    public function __construct(protected SmsGateway $gateway)
    {
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index(Request $request)
    {
        $query = SmsTemplate::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->toString());
        }

        // `filled` يسقط القيمة «0»، والتصفية على المعطّل مقصودة
        if ($request->input('is_active') !== null && $request->input('is_active') !== '') {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        $templates = $query->orderBy('sort_order')->orderBy('name')->get();

        return view('sitting::sms_templates.index', [
            'templates' => $templates,
            // التجميع في المتحكّم لا في القالب: الترتيب داخل كل تصنيف
            // محفوظ في `sort_order`، وإعادة ترتيبه في Blade تُضيّعه
            'grouped' => $templates->groupBy(fn ($template) => $template->category ?: 'general'),
            'categories' => SmsTemplate::categories(),
            'events' => SmsTemplate::events(),
            'eventGroups' => SmsTemplate::eventGroups(),
            'configured' => $this->gateway->isConfigured(),
            'sentCount' => SmsLog::where('status', 'sent')->count(),
        ]);
    }

    public function create()
    {
        return view('sitting::sms_templates.form', $this->formData(new SmsTemplate));
    }

    public function edit(SmsTemplate $smsTemplate)
    {
        return view('sitting::sms_templates.form', $this->formData($smsTemplate));
    }

    public function store(Request $request)
    {
        $template = SmsTemplate::create($this->validated($request));

        return redirect()->route('SmsTemplates.edit', $template->id)
            ->with('success', __('settings.sms_templates.created'));
    }

    public function update(Request $request, SmsTemplate $smsTemplate)
    {
        $data = $this->validated($request, $smsTemplate->id);

        // النصّ يُعدَّل والرمز لا: الكود يستدعي القالب النظامي برمزه
        if ($smsTemplate->is_system) {
            unset($data['code']);
        }

        $smsTemplate->update($data);

        return redirect()->route('SmsTemplates.index')->with('success', __('settings.sms_templates.saved'));
    }

    public function toggle(SmsTemplate $smsTemplate)
    {
        $smsTemplate->update(['is_active' => ! $smsTemplate->is_active]);

        return back()->with('success', $smsTemplate->is_active ? __('settings.sms_templates.activated') : __('settings.sms_templates.deactivated'));
    }

    /**
     * نسخ قالب — الرمز فريد فيُولَّد له رمز جديد بدل أن يفشل الحفظ.
     */
    public function duplicate(SmsTemplate $smsTemplate)
    {
        $copy = $smsTemplate->replicate();
        $copy->name = $smsTemplate->name.__('settings.sms_templates.copy_suffix');
        $copy->code = $this->uniqueCode($smsTemplate->code);
        $copy->is_active = false;
        // النسخة قالب عادي: لا شيء في الكود يستدعيها برمزها الجديد
        $copy->is_system = false;
        $copy->save();

        return redirect()->route('SmsTemplates.edit', $copy->id)
            ->with('success', __('settings.sms_templates.copied'));
    }

    /**
     * الحذف — ممنوع على القوالب النظامية.
     *
     * الرمز `test` مثلاً تستدعيه شاشة الإعدادات، و`otp_code` يستدعيه
     * التحقّق: حذف أيٍّ منهما يُعطّل ميزةً بلا رسالة خطأ تدلّ على السبب.
     */
    public function destroy(SmsTemplate $smsTemplate)
    {
        if ($smsTemplate->is_system) {
            return redirect()->route('SmsTemplates.index')
                ->with('error', __('settings.sms_templates.system_locked'));
        }

        $smsTemplate->delete();

        return redirect()->route('SmsTemplates.index')->with('success', __('settings.sms_templates.deleted'));
    }

    /**
     * إرسال القالب فعلياً — الفحص الوحيد الذي يُعتدّ به.
     */
    public function test(Request $request, SmsTemplate $smsTemplate)
    {
        $request->validate(['phone' => 'required|string|max:20'], [
            'phone.required' => __('settings.sms_templates.errors.phone_required'),
        ]);

        if (! $this->gateway->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => __('settings.sms_templates.gateway_first'),
            ]);
        }

        return response()->json(
            $this->gateway->send($request->phone, $smsTemplate->render($this->samples()))
        );
    }

    // ==================== الداخل ====================

    /**
     * مجموعات المتغيّرات وصياغاتها الجاهزة بتسمياتها المترجَمة.
     *
     * الثابتان أعلاه يبقيان مرجع البنية — المفاتيح والأيقونات — وقيمهما
     * العربية احتياطٌ يظهر إن نقصت الترجمة.
     *
     * @return array<string, mixed>
     */
    private function variableGroups(): array
    {
        $out = [];

        foreach (self::VARIABLE_GROUPS as $key => $group) {
            $fields = [];

            foreach ($group['fields'] as $field => [$label, $sample]) {
                $fields[$field] = [self::tr('vars.'.$field, $label), $sample];
            }

            $out[$key] = [
                'label' => self::tr('var_groups.'.$key, $group['label']),
                'icon' => $group['icon'],
                'fields' => $fields,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function presets(): array
    {
        $out = [];

        foreach (self::PRESETS as $key => $preset) {
            $out[$key] = [
                'label' => self::tr('presets.'.$key, $preset['label']),
                'icon' => $preset['icon'],
                'hint' => self::tr('presets.'.$key.'_hint', $preset['hint']),
                'body' => self::tr('presets.'.$key.'_body', $preset['body']),
            ];
        }

        return $out;
    }

    private static function tr(string $key, string $fallback): string
    {
        $full = 'settings.sms_templates.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(SmsTemplate $template): array
    {
        return [
            'template' => $template,
            'events' => SmsTemplate::events(),
            'eventGroups' => SmsTemplate::eventGroups(),
            'categories' => SmsTemplate::categories(),
            'variableGroups' => $this->variableGroups(),
            'presets' => $this->presets(),
            'samples' => $this->samples(),
            'senderName' => DB::table('sms_settings')->value('sender_name'),
            'configured' => $this->gateway->isConfigured(),
        ];
    }

    /** قيم توضيحية للمعاينة والفحص */
    private function samples(): array
    {
        // اسم المنشأة الحقيقي لا `config('app.name')`: كانت المعاينة تعرض
        // «Laravel» فتبدو الرسالة غريبة ولا تُقاس صياغتها على الواقع
        $business = \App\Models\Client::where('user_id', auth()->id())->value('trade_name')
            ?: (\App\Models\Client::whereNotNull('trade_name')->value('trade_name') ?: config('app.name'));

        return [
            'business' => $business,
            'phone' => '0111234567',
            'link' => 'https://example.com/x',

            'customer' => __('settings.sms_templates.samples.customer'),
            'employee' => __('settings.sms_templates.samples.employee'),

            'number' => 'INV-2026-000042',
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'time' => '10:30',
            'location' => __('settings.sms_templates.samples.location'),
            'tracking' => 'TRK-8891023',

            'amount' => '1,092.50',
            'remaining' => '92.50',
            'balance' => '450.00',
            'currency' => __('settings.sms_templates.samples.currency'),
            'discount' => '15',

            'points' => '250',
            'tier' => __('settings.sms_templates.samples.tier'),

            'item' => __('settings.sms_templates.samples.item'),
            'days' => '5',
            'count' => '3',
            'code' => '482913',
            'minutes' => '10',
            'message' => __('settings.sms_templates.samples.message'),
        ];
    }

    private function uniqueCode(string $base): string
    {
        $code = $base.'_copy';
        $index = 2;

        while (SmsTemplate::where('code', $code)->exists()) {
            $code = $base.'_copy'.$index++;
        }

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignore = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('sms_templates', 'code')->ignore($ignore),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(array_keys(SmsTemplate::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:500'],
            // الكتالوج الموسَّع لا الثابت القديم: كان خمسة أحداث فقط،
            // فيُرفض حفظ قالب مربوط بحدثٍ صار النظام يعرفه
            'event' => ['nullable', Rule::in(SmsTemplate::eventKeys())],
            // 700 حرف ≈ عشر رسائل بالعربية؛ ما فوقها خطأ إدخال غالباً
            'body' => ['required', 'string', 'max:700'],
        ], [
            'code.regex' => __('settings.sms_templates.errors.code_regex'),
            'code.unique' => __('settings.sms_templates.errors.code_unique'),
            'name.required' => __('settings.sms_templates.errors.name_required'),
            'body.required' => __('settings.sms_templates.errors.body_required'),
            'body.max' => __('settings.sms_templates.errors.body_max'),
        ]);

        // تصنيف غير مختار يُشتقّ من الحدث بدل أن يسقط القالب في «عام»
        if (blank($data['category'] ?? null)) {
            $data['category'] = SmsTemplate::categoryOfEvent($data['event'] ?? null);
        }

        return $data + [
            'is_active' => $request->boolean('is_active'),
            'variables' => $this->variablesIn($data['body']),
        ];
    }

    private function variablesIn(string $body): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $body, $matches);

        return array_values(array_unique($matches[1]));
    }
}
