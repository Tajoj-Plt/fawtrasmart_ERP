<?php

namespace Modules\Purchases\Support;

use App\Constants\SystemAccountIds;
use App\Enums\PurchaseDocumentType;
use App\Support\AccountingSettings;
use App\Support\ClosedPeriodRanges;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * دفتر مشتريات الأصناف — كمّيات المنتجات وتكلفتها من مستندات مرحَّلة وحدها.
 *
 * **لماذا هنا لا في المتحكّم؟**
 *
 * كان `ProductReportController` يجلب كل قيود الفترة بـ
 * `with('purchaseInvoice.items.product.category')` ثم يوزّع مبلغ القيد على
 * البنود بالتناسب في PHP. ثلاث علل في ذلك:
 *
 * 1. **الأداء** — فترةٌ بألف فاتورة تعني تحميل عشرات آلاف البنود
 *    ومنتجاتها وأصنافها في الذاكرة لتُجمَع سطراً سطراً. هنا استعلامٌ
 *    واحد مجمَّع في قاعدة البيانات يعيد صفّاً لكل منتج.
 *
 * 2. **الضريبة** — كان المبلغ الموزَّع يشمل «ضريبة المدخلات»، فتظهر تكلفة
 *    الصنف أعلى مما هي. والضريبة مستردّة لا تدخل في تكلفة الشراء، فبنود
 *    التقرير هنا صافيةٌ منها (`invoice_items.total`)، وتُعرض الضريبة
 *    ومجموع القيد منفصلَين في `controlTotals()` ليظهر الربط بالقيود.
 *
 * 3. **الخصم** — `discount` مبلغٌ بالعملة في كل الحالات (المتحكّمات تحفظ
 *    المبلغَ المحسوب و`discount_type` يتذكّر طريقةَ الإدخال فقط)، فيُجمع
 *    كما هو.
 *
 * **ما الذي يدخل التقرير؟** بنود المستندات التي لها قيدٌ مرحَّل
 * (`journal_entries.status = 1`) فقط — فأمر الشراء لا يظهر لأنه ليس
 * مستنداً مالياً، ولا تظهر فاتورةٌ لم تُرحَّل بعد.
 *
 *     فاتورة شراء (invoice)      ← تُزيد الكمية والتكلفة
 *     مرتجع شراء  (Return)       ← تُنقصهما
 *     إشعار مدين  (City Notice)  ← تسويةُ قيمة بلا كمية
 */
class ProductPurchaseLedger
{
    /**
     * الفلاتر المطبَّقة — تُبنى مرّة وتُمرَّر لكل استعلام.
     *
     * @param  array<string, mixed>  $filters
     */
    public function __construct(protected array $filters = []) {}

    /**
     * @param  array<string, mixed>  $filters  المفاتيح المقبولة:
     *                                         product, category, storehouse, supplier, branch_id,
     *                                         employee_id, payment_status, receiving_status, invoice_type
     */
    /**
     * معرّفاتُ المستخدمين الذين يحقّ لقارئ التقرير رؤيةُ ما أنشأوه.
     *
     * `null` تعني «بلا قيد» لا «لا أحد» — والدفترُ يُستعمل من الشاشات ومن
     * الأوامر المجدولة معاً، فالافتراضُ فيه عدمُ التقييد ويأتيه النطاقُ من
     * المتحكّم عبر {@see visibleTo()}. ولولا ذلك لأفرغ أمرُ كرونٍ بلا
     * مستخدمٍ مسجَّلٍ كلَّ تقريرٍ يبنيه.
     *
     * @var array<int, int>|null
     */
    protected ?array $visibleCreatorIds = null;

    /**
     * قصرُ الدفتر على ما أنشأه هؤلاء — نطاقُ الرؤية آتياً من المتحكّم.
     *
     * @param  array<int, int>|null  $userIds  معرّفاتُ `users` لا `employees`،
     *                                         فالعمود `je.created_by_employee`
     *                                         مُسمّى «موظف» وهو مستخدم.
     */
    public function visibleTo(?array $userIds): static
    {
        $this->visibleCreatorIds = $userIds;

        return $this;
    }

    public static function fromFilters(array $filters): self
    {
        return new self(array_filter($filters, fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * صفٌّ لكل منتج بمجاميع الفترة — استعلامٌ واحد مجمَّع.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function productTotals(ReportPeriod $period): Collection
    {
        $rows = $this->baseQuery($period)
            ->groupBy('ii.product_id', 'p.name', 'p.serial_number', 'p.barcode', 'p.id', 'c.name', 'pi.type')
            ->select([
                'ii.product_id',
                'p.name as product_name',
                /*
                 * لا عمود `code` في جدول المنتجات. كان المتحكّم القديم يقرأ
                 * `$product->code` فيعود بـ`null` دائماً، فعمود «الكود» في
                 * التقرير كان فارغاً في كل صفٍّ منذ كُتب. المعرِّف الفعلي هو
                 * الرقم التسلسلي، وإلا الباركود.
                 */
                DB::raw('COALESCE(NULLIF(p.serial_number, \'\'), NULLIF(p.barcode, \'\'), CONCAT(\'#\', p.id)) as product_code'),
                'c.name as category_name',
                'pi.type as document_type',
                DB::raw('SUM(ii.quantity) as quantity'),
                /* الكمية بالوحدة الأساسية — لمتوسط السعر وحده، لا للعرض.
                   `ii.quantity` كما أُدخلت: كرتونٌ يُحسب واحداً لا بعدد
                   حبّاته، فقسمةُ المبلغ عليها تخرج سعر الكرتون (١٩١٫٣٠) لا
                   سعر الحبّة — بينما `Product::averageCost()` في كل مكانٍ
                   آخر بالنظام محسوبٌ بالوحدة الأساسية. `quantity_in_base_unit`
                   فارغٌ في بنودٍ قديمة، فيُشتقّ من معامل التحويل عندها. */
                DB::raw('SUM(COALESCE(NULLIF(ii.quantity_in_base_unit, 0), ii.quantity * COALESCE(NULLIF(ii.conversion_factor, 0), 1))) as base_quantity'),
                DB::raw('SUM(ii.total) as amount'),
                DB::raw('SUM(ii.unit_price * ii.quantity) as gross'),
                DB::raw($this->discountExpression().' as discount'),
                // لا تُسمَّ `lines`: كلمةٌ محجوزة في MariaDB ترمي خطأ نحوٍ عند التحضير
                DB::raw('COUNT(*) as line_count'),
                DB::raw('COUNT(DISTINCT pi.id) as documents'),
                DB::raw('MAX(pi.date) as last_date'),
            ])
            ->get();

        $products = [];

        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            $products[$productId] ??= self::emptyProduct($row);

            $quantity = (float) $row->quantity;
            $amount = (float) $row->amount;
            $bucket = self::bucketFor($row->document_type);

            $products[$productId][$bucket] += $amount;
            $products[$productId][$bucket.'_count'] += (int) $row->documents;

            // الإشعار المدين تسويةُ قيمة على المورّد لا حركةَ صنف — قيمته تُحتسب وكمّيته لا
            if ($bucket !== 'debit_notes') {
                $products[$productId][$bucket.'_qty'] += $quantity;
            }

            // كمّيةُ المشتريات بالوحدة الأساسية — لمتوسّط السعر وحده، انظر finalise()
            if ($bucket === 'purchases') {
                $products[$productId]['purchases_base_qty'] += (float) $row->base_quantity;
            }

            $products[$productId]['gross'] += (float) $row->gross;
            $products[$productId]['discount'] += (float) $row->discount;
            $products[$productId]['lines'] += (int) $row->line_count;
            $products[$productId]['documents'] += (int) $row->documents;

            $last = (string) $row->last_date;
            if ($last > $products[$productId]['last_date']) {
                $products[$productId]['last_date'] = $last;
            }
        }

        foreach ($this->supplierCounts($period) as $productId => $count) {
            if (isset($products[$productId])) {
                $products[$productId]['suppliers_count'] = $count;
            }
        }

        return collect($products)->map(fn ($product) => self::finalise($product))->values();
    }

    /**
     * بنود الفترة مفصَّلةً — سطراً سطراً بمستنده ومورّده، للعرض التفصيلي.
     *
     * تُطلب فقط حين يختار المستخدم «تفاصيل»، فالملخّص لا يحمّل شيئاً منها.
     *
     * @return Collection<int, \stdClass> صفوف عليها product_id
     */
    public function lines(ReportPeriod $period): Collection
    {
        return $this->baseQuery($period)
            ->leftJoin('suppliers as s', 's.id', '=', 'pi.supplier_id')
            ->leftJoin('store_houses as sh', 'sh.id', '=', 'ii.store_house_id')
            ->select([
                'ii.product_id',
                'ii.id as line_id',
                'ii.quantity',
                'ii.unit_price',
                'ii.total',
                'ii.unit_name',
                'ii.discount',
                'ii.discount_type',
                DB::raw('('.$this->lineDiscountExpression().') as discount_value'),
                'pi.id as document_id',
                'pi.code as document_code',
                'pi.date',
                'pi.type as document_type',
                'pi.receiving_status',
                'pi.payment_status',
                's.trade_name as supplier_name',
                'sh.name as storehouse_name',
            ])
            ->orderBy('pi.date')
            ->orderBy('pi.id')
            ->get();
    }

    /**
     * أرقام الربط بالقيود — ما رُحِّل فعلاً إلى حسابات المشتريات والضريبة.
     *
     * يُعرض إلى جانب مجاميع البنود ليرى المحاسب أن التقرير مطابقٌ للدفتر:
     * مجموع البنود = مدين حساب المشتريات، والفارق كلّه ضريبةٌ وشحن.
     *
     * @return array{purchases_account: float, vat: float, entries: int}
     */
    public function controlTotals(ReportPeriod $period): array
    {
        $purchasesAccount = AccountingSettings::id('purchases', SystemAccountIds::PURCHASES);
        $vatAccount = AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID);

        $accounts = array_values(array_filter([$purchasesAccount, $vatAccount]));

        if ($accounts === []) {
            return ['purchases_account' => 0.0, 'vat' => 0.0, 'entries' => 0];
        }

        $rows = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->where('je.status', 1)
            ->whereIn('jed.account_id', $accounts)
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            // ومجاميعُ الضبط بالقاعدة نفسِها، وإلّا خالفت ما تضبطه
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->whereIn('je.reference_type', [
                ...SupplierLedger::PURCHASE_TYPES,
                ...SupplierLedger::RETURN_TYPES,
                ...SupplierLedger::DEBIT_NOTE_TYPES,
            ])
            ->groupBy('jed.account_id')
            ->select([
                'jed.account_id',
                DB::raw('SUM(jed.debit) - SUM(jed.credit) as net'),
                DB::raw('COUNT(DISTINCT je.id) as entries'),
            ])
            ->get();

        $result = ['purchases_account' => 0.0, 'vat' => 0.0, 'entries' => 0];

        foreach ($rows as $row) {
            $key = (int) $row->account_id === (int) $purchasesAccount ? 'purchases_account' : 'vat';
            $result[$key] += (float) $row->net;
            $result['entries'] = max($result['entries'], (int) $row->entries);
        }

        return array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $result);
    }

    /**
     * عدد الموردين المميَّزين لكل منتج.
     *
     * @return array<int, int>
     */
    protected function supplierCounts(ReportPeriod $period): array
    {
        return $this->baseQuery($period)
            ->groupBy('ii.product_id')
            ->select([
                'ii.product_id',
                DB::raw('COUNT(DISTINCT pi.supplier_id) as suppliers'),
            ])
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => (int) $row->suppliers])
            ->all();
    }

    // ==================== البناء ====================

    /** الصفّ الفارغ لمنتج — يُبنى عليه تجميع أنواع مستنداته */
    protected static function emptyProduct(object $row): array
    {
        return [
            'product_id' => (int) $row->product_id,
            'name' => $row->product_name ?: '—',
            'code' => $row->product_code ?: '—',
            'category' => $row->category_name ?: '—',
            'purchases' => 0.0,
            'purchases_qty' => 0.0,
            'purchases_base_qty' => 0.0,
            'purchases_count' => 0,
            'returns' => 0.0,
            'returns_qty' => 0.0,
            'returns_count' => 0,
            'debit_notes' => 0.0,
            'debit_notes_count' => 0,
            'gross' => 0.0,
            'discount' => 0.0,
            'lines' => 0,
            'documents' => 0,
            'suppliers_count' => 0,
            'last_date' => '',
        ];
    }

    /** المشتقّات: الصافي ومتوسّط السعر — تُحسب مرّة بعد اكتمال التجميع */
    protected static function finalise(array $product): array
    {
        $netQty = $product['purchases_qty'] - $product['returns_qty'];
        $netAmount = $product['purchases'] - $product['returns'] - $product['debit_notes'];

        /* متوسّط تكلفة الوحدة الأساسية من مشتريات الفترة — إقحام المرتجع
           يشوّهه، والقسمة على `purchases_base_qty` لا `purchases_qty`:
           تلك بوحدة الإدخال (كرتون، علبة…)، وهذه بالوحدة الأساسية —
           المقياس نفسُه الذي يحسب به `Product::averageCost()` في بقيّة
           النظام، فلا يقرأ المستخدم «١٩١» هنا و«٠٫١٩» في شاشة المنتج. */
        $product['avg_price'] = $product['purchases_base_qty'] > 0
            ? round($product['purchases'] / $product['purchases_base_qty'], 2)
            : 0.0;

        $product['quantity'] = round($netQty, 2);
        $product['net_amount'] = round($netAmount, 2);
        $product['amount'] = round($netAmount, 2);      // اسمٌ قديم تقرأه الشاشة
        $product['count'] = $product['documents'];      // اسمٌ قديم تقرأه الشاشة

        foreach (['purchases', 'purchases_qty', 'purchases_base_qty', 'returns', 'returns_qty', 'debit_notes', 'gross', 'discount'] as $key) {
            $product[$key] = round($product[$key], 2);
        }

        return $product;
    }

    /** نوع المستند ← بند التجميع */
    protected static function bucketFor(?string $documentType): string
    {
        return match (PurchaseDocumentType::fromValue($documentType)) {
            PurchaseDocumentType::RETURN => 'returns',
            PurchaseDocumentType::DEBIT_NOTE => 'debit_notes',
            default => 'purchases',
        };
    }

    /** الخصم بالعملة — العمود مبلغٌ لا نسبة. */
    protected function lineDiscountExpression(): string
    {
        /* `discount` مبلغٌ **دائماً**: متحكّماتُ المشتريات تحفظ فيه المبلغَ
           المحسوب وتكتب `discount_type = 2` لتتذكّر أنه أُدخل نسبةً
           ({@see OrderLine::discountFields()})، و`1` للمبلغ. كان التعبيرُ
           يقرأ `1` نسبةً فيحسب خصمَ «١٠٠ ريال» على ألفٍ ألفَ ريال — عشرةَ
           أضعافه. */
        return 'COALESCE(ii.discount, 0)';
    }

    protected function discountExpression(): string
    {
        return 'SUM('.$this->lineDiscountExpression().')';
    }

    /**
     * الاستعلام الأساس: بنودُ مستندات مرحَّلة في الفترة.
     *
     * الترحيل يُتحقَّق بـ`EXISTS` لا بـ`JOIN` — فالمستند قد يحمل أكثر من قيد
     * (قيد الفاتورة وقيدُ سدادها)، والانضمام يضاعف صفوف البند بعددها فتتضاعف
     * الكمّيات والمبالغ.
     */
    /**
     * عددُ المستندات المتميّزة في الفترة — للشريط العلوي.
     *
     * جمعُ عمود «المستندات» من صفوف المنتجات يعدّ الفاتورةَ مرّةً لكلّ صنفٍ
     * فيها (فاتورةٌ بصنفين = مستندان)؛ العدُّ الحقيقيّ على الاستعلام الأساس
     * نفسِه بفلاتره ونطاقه.
     */
    public function documentCount(ReportPeriod $period): int
    {
        return (int) $this->baseQuery($period)->distinct()->count('pi.id');
    }

    protected function baseQuery(ReportPeriod $period)
    {
        $query = DB::table('invoice_items as ii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'ii.purchase_invoice_id')
            ->join('products as p', 'p.id', '=', 'ii.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->whereBetween('pi.date', [$period->from->toDateString(), $period->to->toDateString()])
            // ما أُقفل محاسبياً لا يدخل كمّيةً ولا تكلفة — القاعدةُ نفسُها
            // التي تُخفيه من شاشات المستندات ومن لوحة المشتريات
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'pi.date'))
            ->whereIn('pi.type', [
                PurchaseDocumentType::INVOICE->value,
                PurchaseDocumentType::RETURN->value,
                PurchaseDocumentType::DEBIT_NOTE->value,
            ])
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('journal_entries as je')
                    ->whereColumn('je.purchase_invoice_id', 'pi.id')
                    ->where('je.status', 1);

                if ($employeeId = $this->filters['employee_id'] ?? null) {
                    $sub->where('je.created_by_employee', $employeeId);
                }

                if ($this->visibleCreatorIds !== null) {
                    $sub->whereIn('je.created_by_employee', $this->visibleCreatorIds);
                }
            });

        $this->applyFilters($query);

        return $query;
    }

    protected function applyFilters($query): void
    {
        $map = [
            'product' => 'ii.product_id',
            'storehouse' => 'ii.store_house_id',
            'category' => 'p.category_id',
            'supplier' => 'pi.supplier_id',
            'branch_id' => 'pi.branch_id',
            'payment_status' => 'pi.payment_status',
            'receiving_status' => 'pi.receiving_status',
            'invoice_type' => 'pi.type',
        ];

        foreach ($map as $key => $column) {
            if (! isset($this->filters[$key])) {
                continue;
            }

            $value = $this->filters[$key];

            // القيمة قد تكون قائمة — شجرة الفروع تعني أن أباً واحداً
            // قد يُترجم إلى عدّة معرّفات
            if (is_array($value)) {
                $query->whereIn($column, $value);

                continue;
            }

            $query->where($column, $value);
        }
    }
}
