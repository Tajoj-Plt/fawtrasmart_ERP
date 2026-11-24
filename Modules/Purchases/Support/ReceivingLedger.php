<?php

namespace Modules\Purchases\Support;

use App\Enums\PurchaseDocumentType;
use App\Support\ClosedPeriodRanges;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * دفتر استلام المشتريات — ما اشتُري مقابل ما دخل المستودع فعلاً.
 *
 * **العلّة التي وُجد لها:**
 *
 * تقرير «المشتريات حسب الصنف» يجمع كمّيات بنود الفواتير، وهي جواب سؤال
 * «كم اشترينا؟» لا «كم دخل المخزن؟». والفرق ليس نظرياً: في قاعدة هذا
 * النظام صنفٌ رصيده في المستودع مئة وحدة، ومجموع بنود فواتيره ألفٌ
 * وخمسمئة. فمن قرأ رقم التقرير على أنه مخزون ظنّ عنده ما ليس عنده.
 *
 * سببا الفارق:
 *
 *   ١) فاتورةٌ غير مستلمة تُحتسب كاملةً وهي لم تصل بعد.
 *   ٢) فاتورةٌ مستلمة جزئياً تُحتسب كاملةً كذلك.
 *
 * **من أين تُعرف الكمية المستلمة؟**
 *
 * لا من `purchase_invoices.receiving_status` — فهو علمٌ على الفاتورة كلها
 * لا على بنودها، ولا عمود «كمية مستلمة» في `invoice_items` أصلاً. بل من
 * **الإذن المخزني**: هو مستند الاستلام الفعلي، يُنشأ للفاتورة
 * (`reference_type = purchase_invoice`) ولا يُدخل بضاعةً حتى يُعتمد
 * (`status = apporod`).
 *
 * فالاستلام الجزئي هنا **محسوبٌ من واقع البيانات** لا مقروءاً من علمٍ لا
 * يضبطه أحد: إذنٌ بكمّيةٍ أقلّ من بند الفاتورة يعني استلاماً جزئياً،
 * ومقداره الفارق بينهما.
 *
 * **حبيبة التقرير** (grain) هي (فاتورة، صنف) لا سطر الفاتورة الخام: الإذن
 * يسجّل الكمية للصنف لا للسطر، فلو تكرّر الصنف في سطرين من فاتورة واحدة
 * تعذّر نسبةُ المستلم إلى أحدهما — فيُجمعان.
 */
class ReceivingLedger
{
    /** حالة الإذن المعتمد — الإملاء الخاطئ مقصود، هو المحفوظ في الجدول */
    public const PERMIT_APPROVED = 'apporod';

    /** حالات تُقبل كاعتماد — `approved` احتياطاً لو صُحّح الإملاء لاحقاً */
    public const APPROVED_STATUSES = ['apporod', 'approved'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(protected array $filters = []) {}

    /**
     * @param  array<string, mixed>  $filters  product, category, supplier,
     *                                         branch_id, employee_id, storehouse, receipt_state
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
     * بنود الفترة: لكل (فاتورة، صنف) ما اشتُري وما استُلم وما بقي.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(ReportPeriod $period): Collection
    {
        $rows = $this->baseQuery($period)
            ->leftJoin('suppliers as s', 's.id', '=', 'pi.supplier_id')
            ->leftJoin('branches as b', 'b.id', '=', 'pi.branch_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->groupBy(
                'pi.id', 'pi.code', 'pi.date', 'pi.receiving_status', 'pi.payment_status',
                // `p.id` مطلوب في التجميع لأن تعبير الكود يشير إليه،
                // و MySQL هنا يعمل بـ ONLY_FULL_GROUP_BY
                'ii.product_id', 'p.id', 'p.name', 'p.serial_number', 'p.barcode',
                'c.name', 's.trade_name', 'b.name'
            )
            ->select([
                'pi.id as invoice_id',
                'pi.code as invoice_code',
                'pi.date',
                'pi.receiving_status',
                'pi.payment_status',
                'ii.product_id',
                'p.name as product_name',
                DB::raw('COALESCE(NULLIF(p.serial_number, \'\'), NULLIF(p.barcode, \'\'), CONCAT(\'#\', p.id)) as product_code'),
                'c.name as category_name',
                's.trade_name as supplier_name',
                'b.name as branch_name',
                /* بالوحدة الأساسية: إذنُ المخزن يُكتب بها دائماً
                   ({@see \App\Models\WarehousePermitsProducts})، وبندُ الفاتورة
                   بوحدة الشراء — فكرتونٌ بألف حبّةٍ كان يُقرأ «مشترى ١٠،
                   مستلم ١٠٬٠٠٠» وزيادةً وهميّةً في الاستلام. الأسطرُ القديمة
                   بلا `quantity_in_base_unit` تُشتقّ من الكمّية × المعامل. */
                DB::raw('SUM(COALESCE(NULLIF(ii.quantity_in_base_unit, 0), ii.quantity * COALESCE(NULLIF(ii.conversion_factor, 0), 1))) as purchased'),
                DB::raw('SUM(ii.total) as amount'),
                DB::raw('SUM(ii.unit_price * ii.quantity) as gross'),
            ])
            ->get();

        $received = $this->receivedMap($rows->pluck('invoice_id')->unique()->all());

        $lines = $rows->map(function ($row) use ($received) {
            $purchased = (float) $row->purchased;
            $key = $row->invoice_id.':'.$row->product_id;

            // المستلم لا يتجاوز المشترى في التقرير: الزيادة خللٌ في الإذن،
            // وإظهارها «سالب باقٍ» يربك القارئ أكثر مما يفيده
            $rawReceived = (float) ($received[$key] ?? 0);
            $receivedQty = min($rawReceived, $purchased);
            $pending = round($purchased - $receivedQty, 2);

            // سعرُ الوحدة الأساسية = الإجمالي قبل الخصم ÷ الكمّية الأساسية،
            // فيبقى «قيمة ما لم يصل» بالمال نفسِه الذي التزمنا به
            $unit = $purchased > 0 ? (float) $row->gross / $purchased : 0.0;

            return [
                'invoice_id' => (int) $row->invoice_id,
                'invoice_code' => $row->invoice_code ?: '—',
                'date' => \Carbon\Carbon::parse($row->date)->format('d/m/Y'),
                'raw_date' => \Carbon\Carbon::parse($row->date)->format('Y-m-d'),
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name ?: '—',
                'product_code' => $row->product_code,
                'category_name' => $row->category_name ?: '—',
                'supplier_name' => $row->supplier_name ?: '—',
                'branch_name' => $row->branch_name ?: '—',
                'purchased' => round($purchased, 2),
                'received' => round($receivedQty, 2),
                'pending' => $pending,
                'over_received' => round(max($rawReceived - $purchased, 0), 2),
                'unit_price' => round($unit, 2),
                'amount' => round((float) $row->amount, 2),
                // قيمة ما لم يصل — أهمّ رقم للمشتري: مالٌ التزمنا به وبضاعةٌ لم تصل
                'pending_value' => round($pending * $unit, 2),
                'received_value' => round($receivedQty * $unit, 2),
                'state' => self::state($purchased, $receivedQty),
                'declared_status' => $row->receiving_status,
                'payment_status' => $row->payment_status,
            ];
        });

        // الفلترة بحالة الاستلام تجري بعد الحساب لأنها مشتقّة لا مخزَّنة
        if ($state = ($this->filters['receipt_state'] ?? null)) {
            $lines = $lines->filter(fn ($l) => $l['state']['code'] === $state);
        }

        return $lines->sortBy([['raw_date', 'desc'], ['invoice_code', 'desc']])->values();
    }

    /**
     * تجميع البنود على الصنف — نظرة «كم لنا عند الموردين من كل صنف».
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return Collection<int, array<string, mixed>>
     */
    public static function byProduct(Collection $lines): Collection
    {
        return $lines
            ->groupBy('product_id')
            ->map(function (Collection $group) {
                $first = $group->first();
                $purchased = round($group->sum('purchased'), 2);
                $received = round($group->sum('received'), 2);
                $pending = round($purchased - $received, 2);

                return [
                    'product_id' => $first['product_id'],
                    'product_name' => $first['product_name'],
                    'product_code' => $first['product_code'],
                    'category_name' => $first['category_name'],
                    'invoices' => $group->pluck('invoice_id')->unique()->count(),
                    'suppliers' => $group->pluck('supplier_name')->unique()->count(),
                    'purchased' => $purchased,
                    'received' => $received,
                    'pending' => $pending,
                    'amount' => round($group->sum('amount'), 2),
                    'pending_value' => round($group->sum('pending_value'), 2),
                    'received_value' => round($group->sum('received_value'), 2),
                    'rate' => $purchased > 0 ? round($received / $purchased * 100, 1) : 0.0,
                    'state' => self::state($purchased, $received),
                ];
            })
            ->sortByDesc('pending')
            ->values();
    }

    /**
     * رصيد المستودع الفعلي لكل صنف — للمقابلة مع ما يقوله التقرير.
     *
     * المخزون يُقرأ من `product_details`، وهو الجدول الذي تقرأ منه شاشات
     * المخزون رصيدها. عرضُه إلى جانب «المستلم» يكشف أي انحراف بين مستند
     * الاستلام والرصيد المسجَّل بدل أن يبقى مخفياً.
     *
     * @return array<int, float> product_id ← الكمية
     */
    public static function warehouseStock(?int $storeHouseId = null): array
    {
        return DB::table('product_details')
            ->when($storeHouseId, fn ($q) => $q->where('store_house_id', $storeHouseId))
            ->groupBy('product_id')
            ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as qty')
            ->pluck('qty', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * الكمية المستلمة لكل (فاتورة، صنف) من الأذون المعتمدة.
     *
     * @param  array<int, int>  $invoiceIds
     * @return array<string, float> "invoiceId:productId" ← الكمية
     */
    protected function receivedMap(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return DB::table('warehouse_permits as wp')
            ->join('warehouse_permits_products as wpp', 'wpp.warehouse_permits_id', '=', 'wp.id')
            ->where('wp.reference_type', 'purchase_invoice')
            ->whereIn('wp.reference_id', $invoiceIds)
            ->whereIn('wp.status', self::APPROVED_STATUSES)
            ->when(
                $this->filters['storehouse'] ?? null,
                fn ($q, $id) => $q->where('wp.store_houses_id', $id)
            )
            ->groupBy('wp.reference_id', 'wpp.product_id')
            ->selectRaw('wp.reference_id, wpp.product_id, SUM(wpp.quantity) as qty')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->reference_id.':'.$r->product_id => (float) $r->qty])
            ->all();
    }

    /** حالة الاستلام محسوبةً من الكمّيات لا من علمٍ مخزَّن */
    public static function state(float $purchased, float $received): array
    {
        if ($received <= 0.009) {
            return ['text' => 'لم يُستلم', 'class' => 'danger', 'code' => 'pending'];
        }

        if ($received + 0.009 >= $purchased) {
            return ['text' => 'مستلم بالكامل', 'class' => 'success', 'code' => 'received'];
        }

        return ['text' => 'مستلم جزئياً', 'class' => 'warning', 'code' => 'partial'];
    }

    /**
     * الاستعلام الأساس: بنود فواتير الشراء المرحّلة في الفترة.
     *
     * المرتجعات والإشعارات خارج التقرير: سؤاله «ما الذي طلبناه ولم يصل»،
     * والمرتجع بضاعةٌ وصلت ثم رُدّت — إدخالها يخلط سؤالين.
     */
    protected function baseQuery(ReportPeriod $period)
    {
        $query = DB::table('invoice_items as ii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'ii.purchase_invoice_id')
            ->join('products as p', 'p.id', '=', 'ii.product_id')
            ->where('pi.type', PurchaseDocumentType::INVOICE->value)
            ->whereBetween('pi.date', [$period->from->toDateString(), $period->to->toDateString()])
            // ما أُقفل محاسبياً لا يدخل كمّيةً ولا قيمة — القاعدةُ نفسُها التي
            // تُخفيه من شاشات المستندات ومن `ProductPurchaseLedger`
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'pi.date'))
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

        foreach ([
            'product' => 'ii.product_id',
            'category' => 'p.category_id',
            'supplier' => 'pi.supplier_id',
            'branch_id' => 'pi.branch_id',
            'storehouse' => 'ii.store_house_id',
        ] as $key => $column) {
            if (! isset($this->filters[$key])) {
                continue;
            }

            $value = $this->filters[$key];

            // القيمة قد تكون قائمة — عقدةٌ في شجرة «تابع لـ» تُترجم إلى
            // فروعها كلّها، كما في `ProductPurchaseLedger`
            if (is_array($value)) {
                $query->whereIn($column, $value);

                continue;
            }

            $query->where($column, $value);
        }

        return $query;
    }
}
