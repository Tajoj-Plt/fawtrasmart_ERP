<?php

namespace Modules\HR\Http\Controllers\Attendance\Settings;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * قوالب طباعة الموارد البشرية.
 *
 * @deprecated 2026-09-24 — غيرُ موصولٍ بمسار. مساراتُ
 *   `attendance.settings.printable-templates.*` تُحوِّل إلى
 *   `document-templates.index?group=hr` (الشاشة الموحَّدة في الإعدادات
 *   العامة)، فلا شاشتان للقوالب نفسِها. يبقى الملف مرجعاً فقط.
 *
 * لا محرِّكَ قوالبَ هنا: النظام يملك واحداً كاملاً بالفعل — جدولُ
 * `document_templates` ومحرِّرُه في وحدة الإعدادات ومُصيِّرُه
 * `DocumentTemplateRenderer`. هذه الشاشة تعرض ما يخصّ الموارد البشرية منه
 * (عقد العمل وقسيمة الراتب) وتوصل إلى المحرِّر نفسه، فلا يزدوج محرِّكان.
 *
 * كانت هذه الشاشة قبلاً هيكلاً فارغاً: index() تعيد عرضاً فيه ترويسةٌ فقط،
 * و create() تعيد ملفاً صفرَ الأسطر — أي صفحةً بيضاء.
 */
class PrintableTemplatesController extends Controller
{
    /** أنواعُ المستندات التي تعني الموارد البشرية من كتالوج قوالب النظام. */
    private const HR_DOCUMENT_TYPES = [
        'contract' => 'عقد العمل',
        'salary_slip' => 'قسيمة الراتب',
        'payroll_sheet' => 'كشف مسير الرواتب',
        'salary_advance' => 'سند السلفة',
        'commission_payment' => 'سند صرف العمولة',
    ];

    /** مقاساتُ الورق التي يفهمها مُصيِّر القوالب */
    private const PAPER_SIZES = [
        'A4' => 'A4',
        'A5' => 'A5',
        'Letter' => 'Letter',
        'Thermal_80mm' => 'حراري 80mm',
        'Thermal_58mm' => 'حراري 58mm',
    ];

    public function __construct()
    {
        $this->middleware('permission.role:settings_edit_general_settings', ['only' => ['index', 'create', 'store']]);
    }

    public function index()
    {
        $templates = DocumentTemplate::query()
            ->whereIn('document_type', array_keys(self::HR_DOCUMENT_TYPES))
            ->orderBy('document_type')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $documentTypes = self::HR_DOCUMENT_TYPES;

        /*
            محرِّر القوالب يقع خلف صلاحية إعداداتٍ عامّة، وليست بالضرورة مع كل
            من يدير الموارد البشرية. نمرّرها للعرض ليُظهر أزرار التحرير لمن
            يملكها، ويشرح لغيره أين تُدار بدل أن يصطدم بـ403.
        */
        $canManageTemplates = hasPermission('settings_edit_general_settings');

        return view('hr::attendance.settings.printable-templates.index', compact(
            'templates', 'documentTypes', 'canManageTemplates'
        ));
    }

    /**
     * إنشاءُ القالب يبقى داخل الموارد البشرية.
     *
     * شاشةُ الإنشاء في وحدة الإعدادات تُخرج المستخدمَ من سياق الموارد البشرية
     * إلى قائمةٍ جانبيةٍ أخرى تماماً، وتعرض كلَّ أنواع المستندات في النظام —
     * فواتير ومشتريات وقيود — وهي لا تخصّ من جاء ليطبع عقداً أو قسيمة راتب.
     * فالإنشاء هنا، مقصوراً على نوعَي الموارد البشرية، ثم يعود إلى هذه الشاشة.
     * تصميمُ القالب نفسه يبقى في المحرِّر المشترك، ويُقصد إليه صراحةً.
     */
    public function create()
    {
        return view('hr::attendance.settings.printable-templates.create', [
            'documentTypes' => self::HR_DOCUMENT_TYPES,
            'paperSizes' => self::PAPER_SIZES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_type' => ['required', Rule::in(array_keys(self::HR_DOCUMENT_TYPES))],
            'paper_size' => ['required', Rule::in(array_keys(self::PAPER_SIZES))],
            'orientation' => 'required|in:portrait,landscape',
        ], [], [
            'name' => 'اسم القالب',
            'document_type' => 'نوع المستند',
            'paper_size' => 'مقاس الورق',
        ]);

        $template = DocumentTemplate::create([
            'name' => $validated['name'],
            'document_type' => $validated['document_type'],
            'template_type' => 'custom',
            'paper_size' => $validated['paper_size'],
            'width' => match ($validated['paper_size']) {
                'Thermal_80mm' => 80,
                'Thermal_58mm' => 58,
                default => 210,
            },
            'orientation' => $validated['orientation'],
            'content' => __('settings.doc_templates.starter_html'),
            'styles' => '',
            'settings' => [
                'show_logo' => true,
                'show_qr' => true,
                'show_customer' => true,
                'show_tax' => true,
                'show_discount' => true,
                'font_size' => 12,
                'direction' => 'rtl',
            ],
            'is_active' => true,
            'is_default' => false,
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('attendance.settings.printable-templates.index')
            ->with('success', 'أُنشئ القالب «'.$template->name.'». لتصميمه افتح المحرِّر من زر التعديل.');
    }
}
