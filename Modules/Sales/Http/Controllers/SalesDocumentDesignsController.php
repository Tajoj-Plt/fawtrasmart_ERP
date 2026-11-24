<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Auth;

/**
 * تصاميم مستندات المبيعات.
 *
 * كانت شاشة «تصاميم الفواتير» تعرض نظام `Template` القديم: جدول بحقلَي
 * اسم ومحتوى، وقالبه في الوحدة ملفٌّ من ثمانية أسطر بقسم محتوى فارغ،
 * والمتحكّم يعرض قالب الجذر بدلاً منه. وفي المشروع نظامٌ ثانٍ مكتمل —
 * `DocumentTemplate` بمحرّر مرئي ومعاينة ومقاسات ورق وقالب افتراضي لكل
 * نوع — لكنّه يعيش في وحدة الإعدادات ويعرض أنواع المستندات الثمانية عشر
 * جميعاً، فالباحث عن قالب فاتورة مبيعات يمرّ على أذون المخازن ومسيرات
 * الرواتب.
 *
 * هذه الشاشة نافذة مبيعاتية على ذلك النظام: الأنواع الثمانية التي تخصّ
 * المبيعات وحدها، مجموعةً بنوعها، والتحرير يذهب إلى المحرّر نفسه — فلا
 * نظام ثالث ولا نسخة ثانية من المحرّر.
 */
class SalesDocumentDesignsController extends Controller
{
    /**
     * أنواع المستندات التي تخصّ دورة المبيعات.
     *
     * الترتيب مقصود: مسار المستند من عرض السعر إلى الفاتورة فما بعدها.
     * التسميات تُقرأ من `settings.doc_templates.types` — نفس مصدر شاشة
     * الإعدادات، فلا يتباعد الاسمان.
     *
     * @var list<string>
     */
    public const TYPES = [
        'quote',
        // أمر البيع: خطوةٌ بين العرض والفاتورة، ويُطبع بقالبٍ من هذه الشاشة
        'sales_order',
        'sales_invoice',
        'tax_invoice',
        'simplified_invoice',
        'sales_return',
        'credit_note',
        'debit_note',
        'receipt_voucher',
        // اتفاقية التقسيط: عقدٌ يُوقَّع ويُسلَّم — ولم يكن للقسم مطبوعةٌ قطّ
        'installment_agreement',
    ];

    /** أيقونة لكل نوع — تمييز بصري يغني عن قراءة العنوان في كل مرة. */
    private const ICONS = [
        'quote' => 'file-text',
        'sales_order' => 'clipboard',
        'sales_invoice' => 'file',
        'tax_invoice' => 'file-plus',
        'simplified_invoice' => 'file-minus',
        'sales_return' => 'corner-up-left',
        'credit_note' => 'plus-circle',
        'debit_note' => 'minus-circle',
        'receipt_voucher' => 'credit-card',
        'supply_order' => 'clipboard',
    ];

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.settings.invoice_designs — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.settings.invoice_designs');
    }

    public function index()
    {
        $labels = (array) __('settings.doc_templates.types');
        $papers = (array) __('settings.doc_templates.paper_sizes');

        // نفس عزل الفروع الذي تطبّقه شاشة الإعدادات: قوالب الفرع الحالي
        // والقوالب العامّة (بلا فرع) التي يشترك فيها الجميع
        $templates = DocumentTemplate::query()
            ->whereIn('document_type', self::TYPES)
            ->when(Auth::check(), function ($query) {
                $query->where(function ($scope) {
                    $scope->where('branch_id', Auth::user()->branch_id)
                        ->orWhereNull('branch_id');
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->groupBy('document_type');

        // كل نوع يظهر ولو خلا من قالب: الفراغ نفسه معلومة — يعني أن طباعة
        // هذا المستند تقع على قالب النظام
        $groups = [];

        // أسماءٌ لأنواعٍ لا مفتاحَ لها في `settings.doc_templates.types`
        $fallbackLabels = [];

        foreach (self::TYPES as $type) {
            $groups[$type] = [
                'label' => $labels[$type] ?? $fallbackLabels[$type] ?? $type,
                'icon' => self::ICONS[$type] ?? 'file',
                'templates' => $templates->get($type, collect()),
                'hasDefault' => $templates->get($type, collect())->contains('is_default', true),
            ];
        }

        $all = $templates->flatten();

        return view('sales::sitting.designs.index', [
            'groups' => $groups,
            'paperSizes' => $papers,
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('is_active', true)->count(),
                'covered' => count(array_filter($groups, fn (array $g) => $g['hasDefault'])),
                'types' => count(self::TYPES),
            ],
            // أزرار التحرير تذهب إلى متحكّم وحدة الإعدادات وهو محروس بهذه
            // الصلاحية؛ إظهارها لمن لا يملكها يقوده إلى 403 لا إلى المحرّر
            'canEdit' => hasPermission('settings_edit_general_settings'),
        ]);
    }
}
