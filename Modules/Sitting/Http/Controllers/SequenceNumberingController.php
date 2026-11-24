<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log as ModelsLog;
use App\Models\SerialSetting;
use App\Services\DocumentNumberService;
use App\Support\DocumentSections;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SequenceNumberingController extends Controller
{
    public function __construct(protected DocumentNumberService $numbers)
    {
        // حماية بصلاحية تعديل الإعدادات العامة
        $this->middleware('permission:settings_edit_general_settings');
    }

    /**
     * شاشة إعدادات الترقيم لقسم واحد.
     *
     * القسم يُمرَّر عبر الرابط وقد يصل باسم قديم أو خاطئ — `resolve` يحوّله
     * إلى مفتاح صالح بدل أن تعرض الشاشة صفراً لقسم مجهول كما كان يحدث.
     */
    public function index(?string $section = null)
    {
        $section = DocumentSections::resolve($section);
        $setting = $this->numbers->settingsFor($section);

        return view('sitting::sequence_numbering.index', [
            'section' => $section,
            'setting' => $setting,
            'groups' => DocumentSections::grouped(),
            'configured' => SerialSetting::pluck('current_number', 'section')->all(),
            'previewNext' => $this->numbers->peek($section),
            'lastFromTable' => $this->numbers->lastNumberFromTable($section),
        ]);
    }

    /**
     * حفظ إعدادات الترقيم لقسم.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'section' => ['required', 'string', Rule::in(DocumentSections::keys())],
            'current_number' => ['required', 'integer', 'min:0', 'max:99999999'],
            'number_of_digits' => ['required', 'integer', 'min:1', 'max:12'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'separator' => ['nullable', 'string', 'max:5'],
            'mode' => ['required', 'integer', 'between:0,6'],
            'reset_period' => ['required', Rule::in(['never', 'yearly', 'monthly', 'daily'])],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'section.in' => __('settings.messages.section_unknown'),
            'current_number.max' => __('settings.messages.current_number_max'),
            'number_of_digits.max' => __('settings.messages.digits_max'),
        ]);

        // صيغتان متطابقتان في جدول واحد تنتهيان إلى الرمز نفسه، وعمود
        // الرمز فريد على مستوى الجدول — فيُرفض الحفظ قبل أن يقع التصادم
        if ($clash = $this->formatClash($data)) {
            return back()->withInput()->with('error', $clash);
        }

        $existing = SerialSetting::where('section', $data['section'])->first();

        // بدء دورة تصفير جديدة عند تغيير نوع الدورة، وإلا بقيت المقارنة
        // معلّقة على تاريخ دورة قديمة لا تخصّ الإعداد الجديد
        $resetChanged = ! $existing || $existing->effective_reset_period !== $data['reset_period'];

        $setting = SerialSetting::updateOrCreate(
            ['section' => $data['section']],
            [
                'current_number' => $data['current_number'],
                'number_of_digits' => $data['number_of_digits'],
                'prefix' => $data['prefix'] ?? '',
                'suffix' => $data['suffix'] ?? null,
                'separator' => $data['separator'] ?? '',
                'mode' => $data['mode'],
                'reset_period' => $data['reset_period'],
                'reset_yearly' => $data['reset_period'] === 'yearly', // مزامنة الحقل القديم
                'is_active' => $request->boolean('is_active', true),
                'last_reset_at' => $resetChanged ? now() : ($existing->last_reset_at ?? now()),
            ]
        );

        ModelsLog::create([
            'type' => 'setting',
            'type_id' => $setting->id,
            'type_log' => 'log',
            'description' => __('settings.sequence.log_updated', ['section' => DocumentSections::label($setting->section)]),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('SequenceNumbering.index', ['section' => $setting->section])
            ->with('success', __('settings.sequence.saved', ['section' => DocumentSections::label($setting->section)]));
    }

    /**
     * قسمٌ شقيق يتشارك الجدول ويحمل الصيغة نفسها — أو `null` إن لم يوجد.
     *
     * أربعة أنواع مشتريات تُحفظ في `purchase_invoices`: الفاتورة والمرتجع
     * وأمر الشراء والإشعار المدين. تمييزها في الترقيم هو البادئة واللاحقة
     * والفاصل وعدد الخانات والنمط. فإذا تطابقت هذه كلّها بين نوعين، أنتج
     * كلاهما `0001` — ويرفض الفهرس الفريد الثاني، أو يقفز عدّاده رقماً
     * فيبدو للمستخدم أن بين النوعين تسلسلاً مشتركاً.
     *
     * @param  array<string, mixed>  $data
     */
    protected function formatClash(array $data): ?string
    {
        $shape = fn ($prefix, $suffix, $separator, $digits, $mode) => implode('|', [
            trim((string) $prefix), trim((string) $suffix),
            (string) $separator, (int) $digits, (int) $mode,
        ]);

        $mine = $shape(
            $data['prefix'] ?? '', $data['suffix'] ?? '', $data['separator'] ?? '',
            $data['number_of_digits'], $data['mode']
        );

        $siblings = DocumentSections::siblings($data['section']);

        if ($siblings === []) {
            return null;
        }

        foreach (SerialSetting::whereIn('section', $siblings)->get() as $sibling) {
            $theirs = $shape(
                $sibling->prefix, $sibling->suffix, $sibling->separator,
                $sibling->number_of_digits, $sibling->mode
            );

            if ($mine === $theirs) {
                return __('settings.sequence.format_clash', [
                    'section' => DocumentSections::label($sibling->section),
                ]);
            }
        }

        return null;
    }

    /**
     * معاينة حية للرقم التالي — يستدعيها محرّر الشاشة عند كل تغيير.
     *
     * تُحسب على الخادم بنفس منطق التوليد، فلا يوجد تكرار للمنطق في JS
     * يمكن أن ينحرف عن الناتج الحقيقي.
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'current_number' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'number_of_digits' => ['nullable', 'integer', 'min:1', 'max:12'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'separator' => ['nullable', 'string', 'max:5'],
            'mode' => ['nullable', 'integer', 'between:0,6'],
        ]);

        $draft = new SerialSetting([
            'number_of_digits' => $data['number_of_digits'] ?? 6,
            'prefix' => $data['prefix'] ?? '',
            'suffix' => $data['suffix'] ?? null,
            'separator' => $data['separator'] ?? '',
            'mode' => $data['mode'] ?? 0,
        ]);

        $current = (int) ($data['current_number'] ?? 0);

        return response()->json([
            'next' => $this->numbers->format($draft, $current + 1),
            'after' => $this->numbers->format($draft, $current + 2),
        ]);
    }

    /**
     * توليد الرقم التالي واستهلاكه — لاستدعاءات AJAX من شاشات المستندات.
     */
    public function generate(string $section)
    {
        if (! DocumentSections::exists($section)) {
            return response()->json(['success' => false, 'message' => __('settings.messages.section_unknown_short')], 404);
        }

        try {
            return response()->json([
                'success' => true,
                'section' => DocumentSections::resolve($section),
                'serial_number' => $this->numbers->next($section),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.SequenceNumbering', 'generate');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * الرقم التالي بلا استهلاك — لعرضه في نموذج إنشاء مستند.
     */
    public function currentNumber(string $section)
    {
        if (! DocumentSections::exists($section)) {
            return response()->json(['success' => false, 'message' => __('settings.messages.section_unknown_short')], 404);
        }

        return response()->json([
            'success' => true,
            'section' => DocumentSections::resolve($section),
            'next_number' => $this->numbers->peek($section),
            'current_number' => $this->numbers->current($section),
        ]);
    }
}
