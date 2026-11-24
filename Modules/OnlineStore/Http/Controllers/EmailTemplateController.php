<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\EmailCategory;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

class EmailTemplateController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.email_templates.view', ['only' => ['index', 'show', 'preview']]);
        $this->middleware('permission.role:online_store.email_templates.create', ['only' => ['create', 'store', 'duplicate']]);
        $this->middleware('permission.role:online_store.email_templates.edit', ['only' => ['edit', 'update', 'toggleStatus']]);
        $this->middleware('permission.role:online_store.email_templates.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:online_store.email_templates.send_test', ['only' => ['sendTest']]);
        $this->middleware('permission.role:online_store.email_templates.export', ['only' => ['export']]);
    }

    /**
     * عرض قائمة قوالب البريد الإلكتروني
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = EmailTemplate::query();

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('template_name', 'like', "%{$search}%")
                    ->orWhere('template_code', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // التصفية حسب التصنيف
        if (is_string($request->category) && EmailCategory::tryFrom($request->category)) {
            $query->where('template_category', $request->category);
        }

        // التصفية حسب الحالة
        if (in_array($request->status, ['active', 'inactive'], true)) {
            $query->where('is_active', $request->status === 'active');
        }

        // التصفية حسب اللغة
        if (in_array($request->language, ['ar', 'en'], true)) {
            $query->where('language', $request->language);
        }

        $templates = $query->orderBy('template_category')
            ->orderBy('template_name')
            ->paginate(20)
            ->withQueryString();

        $categories = EmailCategory::cases();

        return view('onlinestore::email-templates.index', compact('templates', 'categories'));
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'template_name' => 'اسم القالب',
        'template_code' => 'رمز القالب',
        'template_category' => 'التصنيف',
        'subject' => 'عنوان الرسالة',
        'html_content' => 'محتوى HTML',
        'text_content' => 'المحتوى النصي',
        'variables' => 'المتغيرات',
        'language' => 'اللغة',
        'is_active' => 'حالة التفعيل',
        'test_email' => 'بريد الاختبار',
    ];

    /**
     * قواعد الحفظ — بأطوال أعمدة الجدول نفسها (الرمز 50، الاسم 100، العنوان 200):
     * كانت القواعد أوسع من الأعمدة فيُبتر النص أو يسقط الحفظ.
     */
    private function rules(?EmailTemplate $template = null): array
    {
        return [
            'template_name' => 'required|string|max:100',
            'template_code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.\-]+$/',
                Rule::unique('email_templates', 'template_code')->ignore($template?->template_id, 'template_id'),
            ],
            // تصنيفٌ خارج القائمة كان يُسقط الحفظ بخطأ 500
            'template_category' => ['required', Rule::in(array_column(EmailCategory::cases(), 'value'))],
            'subject' => 'required|string|max:200',
            'html_content' => 'required_without:text_content|nullable|string',
            'text_content' => 'nullable|string',
            'variables' => 'nullable|string|max:2000',
            'language' => 'required|in:ar,en',
            'is_active' => 'nullable|boolean',
        ];
    }

    /** «a, b, c» ← مصفوفة نظيفة بلا فراغات ولا تكرار. */
    private function parseVariables($raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        // يقبل الصيغة القديمة المحفوظة JSON كما يقبل القائمة المفصولة بفواصل
        $decoded = json_decode($raw, true);
        $items = is_array($decoded) ? $decoded : explode(',', $raw);

        return array_values(array_unique(array_filter(array_map(
            fn ($v) => trim((string) $v, " \t\n\r\0\x0B\"'[]{}"),
            $items
        ), fn ($v) => $v !== '')));
    }

    /**
     * عرض صفحة إنشاء قالب جديد
     */
    public function create()
    {
        $categories = EmailCategory::cases();

        return view('onlinestore::email-templates.create', compact('categories'));
    }

    /**
     * حفظ قالب جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), [], self::ATTRIBUTES);

        // تحويل المتغيرات من نص إلى JSON
        $validated['variables'] = $this->parseVariables($request->variables);
        $validated['html_content'] = (string) ($validated['html_content'] ?? '');

        $validated['is_active'] = $request->boolean('is_active');

        try {
            EmailTemplate::create($validated);

            return redirect()
                ->route('email_templates.index')
                ->with('success', 'تم إنشاء قالب البريد الإلكتروني بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.EmailTemplate', 'store');
            Log::error('خطأ في إنشاء قالب البريد: '.$e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء إنشاء القالب');
        }
    }

    /**
     * عرض تفاصيل قالب معين
     */
    public function show(EmailTemplate $emailTemplate)
    {
        // إحصائيات الاستخدام
        $stats = [
            'total_sent' => $emailTemplate->emailLogs()->sent()->count(),
            'total_failed' => $emailTemplate->emailLogs()->failed()->count(),
            'total_pending' => $emailTemplate->emailLogs()->pending()->count(),
            'last_sent' => $emailTemplate->emailLogs()->sent()->latest('sent_at')->first()?->sent_at,
        ];

        // آخر السجلات
        $recentLogs = $emailTemplate->emailLogs()
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('onlinestore::email-templates.show', compact('emailTemplate', 'stats', 'recentLogs'));
    }

    /**
     * عرض صفحة تعديل قالب
     */
    public function edit(EmailTemplate $emailTemplate)
    {
        $categories = EmailCategory::cases();

        // تحويل المتغيرات من JSON إلى نص
        $variables = '';
        if ($emailTemplate->variables) {
            $vars = is_array($emailTemplate->variables)
                ? $emailTemplate->variables
                : json_decode($emailTemplate->variables, true);
            $variables = implode(', ', $vars ?? []);
        }

        return view('onlinestore::email-templates.edit', compact('emailTemplate', 'categories', 'variables'));
    }

    /**
     * تحديث قالب موجود
     */
    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $validated = $request->validate($this->rules($emailTemplate), [], self::ATTRIBUTES);

        // تحويل المتغيرات من نص إلى JSON
        $validated['variables'] = $this->parseVariables($request->variables);
        $validated['html_content'] = (string) ($validated['html_content'] ?? '');

        $validated['is_active'] = $request->boolean('is_active');

        try {
            $emailTemplate->update($validated);

            return redirect()
                ->route('email_templates.show', $emailTemplate)
                ->with('success', 'تم تحديث القالب بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.EmailTemplate', 'update', $emailTemplate);
            Log::error('خطأ في تحديث القالب: '.$e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء تحديث القالب');
        }
    }

    /**
     * حذف قالب
     */
    public function destroy(EmailTemplate $emailTemplate)
    {
        try {
            // التحقق من وجود سجلات مرتبطة
            if ($emailTemplate->emailLogs()->exists()) {
                return back()->with('error', 'لا يمكن حذف هذا القالب لوجود سجلات بريد مرتبطة به. عطّله بدل حذفه.');
            }

            $emailTemplate->delete();

            return redirect()
                ->route('email_templates.index')
                ->with('success', 'تم حذف القالب بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.EmailTemplate', 'destroy', $emailTemplate);
            Log::error('خطأ في حذف القالب: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء حذف القالب');
        }
    }

    /**
     * تبديل حالة القالب (مفعل/غير مفعل)
     */
    public function toggleStatus(EmailTemplate $emailTemplate)
    {
        try {
            $emailTemplate->update([
                'is_active' => ! $emailTemplate->is_active,
            ]);

            $status = $emailTemplate->is_active ? 'مفعل' : 'معطل';

            return response()->json([
                'success' => true,
                'message' => "تم {$status} القالب بنجاح",
                'is_active' => $emailTemplate->is_active,
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.EmailTemplate', 'toggleStatus', $emailTemplate);
            Log::error('خطأ في تبديل حالة القالب: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الحالة',
            ], 500);
        }
    }

    /**
     * معاينة القالب
     */
    public function preview(EmailTemplate $emailTemplate, Request $request)
    {
        // بيانات تجريبية للمعاينة
        $sampleData = [
            'store_name' => 'متجر فوترة',
            'customer_name' => 'أحمد محمد',
            'customer_email' => 'customer@example.com',
            'order_number' => '12345',
            'order_date' => now()->format('Y-m-d'),
            'total' => CurrencySettings::format(500),
            'store_url' => url('/'),
            'support_email' => 'support@example.com',
            'store_phone' => '+966 50 123 4567',
            'unsubscribe_url' => url('/unsubscribe'),
        ];

        // دمج البيانات المخصصة من الطلب
        if ($request->filled('preview_data')) {
            $customData = json_decode($request->preview_data, true) ?? [];
            $sampleData = array_merge($sampleData, $customData);
        }

        $content = $emailTemplate->replaceVariables($sampleData);

        return view('onlinestore::email-templates.preview', compact('emailTemplate', 'content', 'sampleData'));
    }

    /**
     * نسخ قالب موجود
     */
    public function duplicate(EmailTemplate $emailTemplate)
    {
        try {
            $newTemplate = $emailTemplate->replicate();
            $newTemplate->template_name = $emailTemplate->template_name.' (نسخة)';
            // العمود 50 حرفاً — الرمز الأصلي يُقصّ كي لا يتجاوزها مع اللاحقة
            $newTemplate->template_code = Str::limit($emailTemplate->template_code, 34, '').'_c'.time();
            $newTemplate->is_active = false;
            $newTemplate->save();

            return redirect()
                ->route('email_templates.edit', $newTemplate)
                ->with('success', 'تم نسخ القالب بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.EmailTemplate', 'duplicate', $emailTemplate);
            Log::error('خطأ في نسخ القالب: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء نسخ القالب');
        }
    }

    /**
     * اختبار إرسال القالب
     */
    public function sendTest(Request $request, EmailTemplate $emailTemplate)
    {
        $request->validate([
            'test_email' => 'required|email|max:255',
        ], [], self::ATTRIBUTES);

        // القالب المعطّل لا يُرسَل (sendTemplatedEmail يتجاهله) — كانت الرسالة «تم الإرسال» مع ذلك
        if (! $emailTemplate->is_active) {
            return back()->with('error', 'القالب معطّل — فعّله أولاً ثم أرسل بريد الاختبار.');
        }

        $lastLogId = (int) EmailLog::max('log_id');

        try {
            // بيانات تجريبية
            $testData = [
                'store_name' => 'متجر فوترة',
                'customer_name' => 'مستخدم تجريبي',
                'customer_email' => $request->test_email,
                'order_number' => 'TEST-'.rand(1000, 9999),
                'total' => CurrencySettings::format(100),
                'store_url' => url('/'),
                'support_email' => config('mail.from.address'),
                'store_phone' => '+966 50 123 4567',
                'unsubscribe_url' => url('/unsubscribe'),
            ];

            EmailTemplate::sendTemplatedEmail(
                $request->test_email,
                $emailTemplate->template_code,
                $testData
            );

            // النتيجة الفعلية من سجل البريد — فشلُ خادم البريد لا يرمي استثناءً هنا
            $log = EmailLog::where('log_id', '>', $lastLogId)
                ->where('recipient_email', $request->test_email)
                ->latest('log_id')->first();

            if ($log && ($log->status->value ?? $log->status) === 'failed') {
                return back()->with('error', 'تعذّر إرسال البريد التجريبي: '.Str::limit((string) $log->error_message, 180).' — راجع إعدادات خادم البريد (SMTP).');
            }

            return back()->with('success', 'تم إرسال البريد التجريبي إلى '.$request->test_email);
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.EmailTemplate', 'sendTest', $emailTemplate);
            Log::error('خطأ في إرسال البريد التجريبي: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء إرسال البريد التجريبي');
        }
    }

    /**
     * تصدير القوالب
     */
    public function export()
    {
        $templates = EmailTemplate::all();
        $filename = 'email_templates_'.now()->format('Y-m-d_His').'.json';

        return response()->json($templates)
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
