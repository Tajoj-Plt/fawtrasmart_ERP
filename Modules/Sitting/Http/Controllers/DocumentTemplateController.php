<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateMarkup;
use App\Services\DocumentTemplateRenderer;
use App\Services\TemplateStyleSheet;
use App\Support\DocumentBlocks;
use App\Support\DocumentFields;
use App\Support\TemplateStyleOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentTemplateController extends Controller
{
    /**
     * مجموعاتُ أنواع المستندات لفلتر `?group=` في القائمة.
     *
     * شاشةُ «قوالب الطباعة» في الموارد البشرية كانت نسخةً ثانيةً من هذه
     * الشاشة مقصورةً على مستندات الرواتب؛ أُحيلت إلى هنا (`?group=hr`)
     * فصار للقوالب شاشةٌ واحدة، وما يُعدَّل فيها يسري على كل طباعةٍ فوراً.
     *
     * @var array<string, array<int, string>>
     */
    public const DOCUMENT_GROUPS = [
        'hr' => ['contract', 'salary_slip', 'payroll_sheet', 'salary_advance', 'commission_payment'],
        // `?group=realestate`: من إعدادات العقارات — عقدُ الإيجار وإيصالُ دفعته
        'realestate' => ['realestate_contract', 'realestate_receipt'],
    ];

    protected $dataService;

    protected $renderer;

    protected $markup;

    protected $styleSheet;

    public function __construct(
        DocumentTemplateDataService $dataService,
        DocumentTemplateRenderer $renderer,
        DocumentTemplateMarkup $markup,
        TemplateStyleSheet $styleSheet
    ) {
        // Safe protection with settings permission
        $this->middleware('permission:settings_edit_general_settings');
        $this->dataService = $dataService;
        $this->renderer = $renderer;
        $this->markup = $markup;
        $this->styleSheet = $styleSheet;
    }

    /**
     * List all templates with filters.
     */
    public function index(Request $request)
    {
        $query = DocumentTemplate::query();

        // Multi-branch / user isolation
        if (Auth::check()) {
            $query->where(function ($q) {
                $q->where('branch_id', Auth::user()->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        // Filters
        $group = (string) $request->query('group', '');
        $groupTypes = self::DOCUMENT_GROUPS[$group] ?? null;

        if ($groupTypes !== null) {
            $query->whereIn('document_type', $groupTypes);
        } else {
            $group = '';
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        if ($request->filled('paper_size')) {
            $query->where('paper_size', $request->paper_size);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $templates = $query->orderBy('document_type')->orderBy('is_default', 'desc')->paginate(\Modules\Sitting\Support\PageSize::get(20))->appends($request->query());

        $documentTypes = $this->getDocumentTypesList();
        $paperSizes = $this->paperSizes();

        // مع `?group=` تقتصر قائمةُ «نوع المستند» على أنواع المجموعة
        $filterTypes = $groupTypes !== null
            ? array_intersect_key($documentTypes, array_flip($groupTypes))
            : $documentTypes;

        return view('sitting::document_templates.index', compact('templates', 'documentTypes', 'paperSizes', 'group', 'filterTypes'));
    }

    /**
     * Show creation form.
     */
    public function create()
    {
        $documentTypes = $this->getDocumentTypesList();
        $paperSizes = $this->paperSizes();

        // Fetch standard default ready-made templates for base selections
        $readyTemplates = DocumentTemplate::whereNull('branch_id')->get();

        return view('sitting::document_templates.create', compact('documentTypes', 'paperSizes', 'readyTemplates'));
    }

    /**
     * Store new template.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'document_type' => 'required|string',
            'paper_size' => 'required|string',
            'base_template_id' => 'nullable|integer',
        ]);

        $content = '';
        $styles = '';
        $settings = [
            'show_logo' => true,
            'show_qr' => true,
            'show_customer' => true,
            'show_tax' => true,
            'show_discount' => true,
            'font_size' => 12,
            'direction' => 'rtl',
        ];

        // If base template is selected, copy its contents
        if ($request->filled('base_template_id')) {
            $base = DocumentTemplate::find($request->base_template_id);
            if ($base) {
                $content = $base->content;
                $styles = $base->styles;
                $settings = $base->settings;
            }
        } else {
            // Default placeholder contents if starting empty
            $content = __('settings.doc_templates.starter_html');
        }

        $template = DocumentTemplate::create([
            'name' => $request->name,
            'document_type' => $request->document_type,
            'template_type' => 'custom',
            'paper_size' => $request->paper_size,
            'width' => $request->paper_size === 'Thermal_80mm' ? 80 : ($request->paper_size === 'Thermal_58mm' ? 58 : 210),
            'height' => $request->input('height'),
            'orientation' => $request->input('orientation', 'portrait'),
            'content' => $content,
            'styles' => $styles,
            'settings' => $settings,
            'is_default' => false,
            'is_active' => true,
            'branch_id' => Auth::user()->branch_id ?? null,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('document-templates.edit', $template->id)
            ->with('success', __('settings.doc_templates.created_msg'));
    }

    /**
     * Edit template.
     */
    public function edit($id)
    {
        $template = DocumentTemplate::findOrFail($id);

        $this->authorizeBranch($template);

        $documentTypes = $this->getDocumentTypesList();
        $paperSizes = $this->paperSizes();

        return view('sitting::document_templates.edit', [
            'template' => $template,
            'documentTypes' => $documentTypes,
            'paperSizes' => $paperSizes,
            // نسخة آمنة للمحرّر المرئي: الرموز النصية تصير سمات على العناصر
            'editorContent' => $this->markup->toEditor($template->content),
            'fieldGroups' => DocumentFields::groups(),
            'itemFields' => DocumentFields::itemFields(),
            'conditions' => DocumentFields::conditions(),
            'blocks' => DocumentBlocks::all(),

            // لوحة التصميم: التعريفات لبناء الواجهة، والقيم المحفوظة لملئها،
            // والتنسيق اليدوي وحده في محرّر CSS دون الكتلة المولَّدة
            'styleGroups' => TemplateStyleOptions::groups(),
            'styleDefaults' => TemplateStyleOptions::defaults(),
            'styleValues' => $this->styleSheet->extract($template->styles),
            'manualStyles' => $this->styleSheet->stripManaged($template->styles),
        ]);
    }

    /**
     * Update template.
     */
    public function update(Request $request, $id)
    {
        $template = DocumentTemplate::findOrFail($id);

        $this->authorizeBranch($template);

        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'styles' => 'nullable|string',
            'editor_mode' => 'nullable|in:visual,source',
        ]);

        // المحرّر المرئي يرسل HTML بسمات — يُعاد إلى صيغة التخزين قبل الحفظ.
        // محرّر المصدر يرسل صيغة التخزين مباشرة فلا يُلمس.
        $content = $request->input('editor_mode', 'visual') === 'visual'
            ? $this->markup->fromEditor($request->content)
            : $request->content;

        // Construct settings array from input checkbox fields
        $settings = [
            'show_logo' => $request->has('show_logo'),
            'show_qr' => $request->has('show_qr'),
            'show_barcode' => $request->has('show_barcode'),
            'show_customer' => $request->has('show_customer'),
            'show_tax' => $request->has('show_tax'),
            'show_discount' => $request->has('show_discount'),
            'font_size' => $request->input('font_size', 12),
            'font_family' => $request->input('font_family', 'Tajawal'),
            'direction' => $request->input('direction', 'rtl'),
            'primary_color' => $request->input('primary_color', '#7367F0'),
            'secondary_color' => $request->input('secondary_color', '#82868b'),
        ];

        $template->update([
            'name' => $request->name,
            'content' => $content,
            // التنسيق اليدوي كما كتبه المستخدم + كتلة مولَّدة من لوحة التصميم
            'styles' => $this->resolveStyles($request, $template),
            'settings' => $settings,
            'width' => $request->input('width', $template->width),
            'height' => $request->input('height', $template->height),
            'orientation' => $request->input('orientation', $template->orientation),
        ]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => __('settings.doc_templates.saved_msg')]);
        }

        return redirect()->route('document-templates.index')
            ->with('success', __('settings.doc_templates.updated_msg'));
    }

    /**
     * التنسيق النهائي: اليدوي + كتلة لوحة التصميم.
     *
     * لوحة التصميم لا ترسل شيئاً حين لا يُلمس أي خيار، فلا يمكن التمييز بين
     * «لا خيارات» و«اللوحة غائبة عن الطلب». العلامة المخفية تحسم الأمر:
     * بغيابها يُحتفظ بالكتلة المحفوظة بدل محوها بصمت.
     */
    private function resolveStyles(Request $request, DocumentTemplate $template): string
    {
        $manual = $request->input('styles', '');

        if (! $request->boolean('style_panel')) {
            return $this->styleSheet->compose(
                $manual,
                $this->styleSheet->extract($template->styles)
            );
        }

        return $this->styleSheet->compose($manual, (array) $request->input('style_options', []));
    }

    /**
     * قالبُ فرعٍ لا يمسّه مستخدمُ فرعٍ آخر — القالبُ العامّ (بلا فرع) للجميع.
     *
     * كان الفحصُ في `edit` و`update` وحدَهما: فمستخدمُ فرعٍ يُمنع من تعديل
     * قالب فرعٍ آخر، لكنه يحذفه ويعطّله ويجعله افتراضياً وينسخه ويعاينه.
     * صار فحصاً واحداً تمرّ عليه الأفعالُ السبعة كلُّها.
     */
    protected function authorizeBranch(DocumentTemplate $template): void
    {
        if (Auth::check() && $template->branch_id && (int) $template->branch_id !== (int) Auth::user()->branch_id) {
            abort(403, __('settings.doc_templates.not_allowed'));
        }
    }

    /**
     * Duplicate template.
     */
    public function duplicate($id)
    {
        $original = DocumentTemplate::findOrFail($id);

        $this->authorizeBranch($original);

        $copy = $original->replicate();
        $copy->name = $original->name.__('settings.doc_templates.copy_suffix');
        $copy->is_default = false;
        $copy->branch_id = Auth::user()->branch_id ?? null;
        $copy->user_id = Auth::id();
        $copy->save();

        return redirect()->route('document-templates.index')
            ->with('success', __('settings.doc_templates.copied_msg'));
    }

    /**
     * Set template as default.
     */
    public function setDefault($id)
    {
        $template = DocumentTemplate::findOrFail($id);

        $this->authorizeBranch($template);

        DB::transaction(function () use ($template) {
            // Remove default status from all templates of the same document_type, paper_size, and scope
            DocumentTemplate::where('document_type', $template->document_type)
                ->where('paper_size', $template->paper_size)
                ->where('branch_id', $template->branch_id)
                ->update(['is_default' => false]);

            $template->is_default = true;
            $template->save();
        });

        return redirect()->back()->with('success', __('settings.doc_templates.default_set'));
    }

    /**
     * Toggle active status.
     */
    public function toggleActive($id)
    {
        $template = DocumentTemplate::findOrFail($id);

        $this->authorizeBranch($template);

        if ($template->is_default) {
            return redirect()->back()->with('error', __('settings.doc_templates.cannot_disable_default'));
        }

        $template->is_active = ! $template->is_active;
        $template->save();

        return redirect()->back()->with('success', __('settings.doc_templates.toggled'));
    }

    /**
     * Delete template.
     */
    public function destroy($id)
    {
        $template = DocumentTemplate::findOrFail($id);

        $this->authorizeBranch($template);

        if ($template->is_default) {
            return redirect()->back()->with('error', __('settings.doc_templates.cannot_delete_default'));
        }

        $template->delete();

        return redirect()->route('document-templates.index')
            ->with('success', __('settings.doc_templates.deleted_msg'));
    }

    /**
     * Realtime preview ajax action for the editor.
     */
    public function previewHtml(Request $request)
    {
        $temp = new DocumentTemplate;

        // المعاينة تمرّ بنفس تحويل الحفظ، فما يُعرض هو ما سيُحفظ فعلاً
        $temp->content = $request->input('editor_mode', 'visual') === 'visual'
            ? $this->markup->fromEditor($request->input('content', ''))
            : $request->input('content', '');

        // المعاينة تمرّ بنفس دمج الحفظ، فما يُعرض هو التنسيق الذي سيُحفظ
        $temp->styles = $this->styleSheet->compose(
            $request->input('styles', ''),
            (array) $request->input('style_options', [])
        );
        $temp->paper_size = $request->input('paper_size', 'A4');

        $settings = [
            'show_logo' => $request->input('show_logo') === 'true',
            'show_qr' => $request->input('show_qr') === 'true',
            'show_barcode' => $request->input('show_barcode') === 'true',
            'show_customer' => $request->input('show_customer') === 'true',
            'show_tax' => $request->input('show_tax') === 'true',
            'show_discount' => $request->input('show_discount') === 'true',
            'font_size' => $request->input('font_size', 12),
            'font_family' => $request->input('font_family', 'Tajawal'),
            'direction' => $request->input('direction', 'rtl'),
            'primary_color' => $request->input('primary_color', '#7367F0'),
            'secondary_color' => $request->input('secondary_color', '#82868b'),
        ];
        $temp->settings = $settings;

        $docType = $request->input('document_type', 'sales_invoice');

        // Generate normalized demo data
        $demoData = $this->dataService->getDemoData($docType);

        // Render HTML
        $html = $this->renderer->render($temp, $demoData);

        $response = ['html' => $html];

        // عند التبديل من محرّر المصدر إلى المرئي: نعيد النسخة الآمنة للمحرّر
        // ليكون التحويل بنفس منطق الخادم لا بنسخة ثانية في JavaScript
        if ($request->boolean('want_editor_html')) {
            $response['editor_html'] = $this->markup->toEditor($temp->content);
        }

        return response()->json($response);
    }

    /**
     * Preview template page.
     */
    public function previewPage($id, Request $request)
    {
        $template = DocumentTemplate::findOrFail($id);

        $this->authorizeBranch($template);

        $docType = $template->document_type;

        // Fetch demo or real document data
        $data = $request->filled('document_id')
            ? $this->dataService->getDocumentData($docType, $request->document_id)
            : $this->dataService->getDemoData($docType);

        $html = $this->renderer->render($template, $data);

        return view('sitting::document_templates.preview', compact('template', 'html'));
    }

    /**
     * أنواع المستندات بأسمائها المعروضة.
     *
     * كانت مكرَّرة نصّاً في ثلاث دوالّ ومكتوبة بالعربية داخل الشفرة —
     * فصارت مصدراً واحداً يقرأ من ملفّ الترجمة.
     *
     * @return array<string, string>
     */
    private function getDocumentTypesList(): array
    {
        return $this->translatedList('types');
    }

    /**
     * مقاسات الورق المدعومة.
     *
     * @return array<string, string>
     */
    private function paperSizes(): array
    {
        return $this->translatedList('paper_sizes');
    }

    /**
     * @return array<string, string>
     */
    private function translatedList(string $key): array
    {
        $list = __('settings.doc_templates.'.$key);

        return is_array($list) ? $list : [];
    }
}
