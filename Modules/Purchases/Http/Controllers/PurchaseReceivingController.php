<?php

namespace Modules\Purchases\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Http\Controllers\Controller;
use App\Models\InvoiceItem;
use App\Models\ProductDetails;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReceiving;
use App\Models\PurchaseReceivingItem;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Purchases\PurchaseTaxCalculator;
use App\Support\OperationLog;
use App\Support\StockEligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Purchases\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Purchases\Support\PurchaseFulfillmentLedger;
use Modules\Purchases\Support\PurchaseInvoiceConversionAccounting;
use Modules\Purchases\Support\PurchaseReceivingAccounting;
use Modules\Purchases\Support\PurchaseReceivingCost;

/**
 * استلام المشتريات — المستند الذي يفصل الآن بين اعتماد أمر الشراء وإصدار
 * فاتورته: لا تُنشأ فاتورة شراء من أمرٍ مباشرةً بعد اليوم (انظر
 * `PurchaseOrdersController::convertToInvoice()`، المُعطَّل)، بل يُستلَم
 * أمرُ الشراء أولاً — كلياً أو جزئياً، وعلى دفعاتٍ متعددة — ثم يُحوَّل كلُّ
 * استلامٍ معتمَد إلى فاتورةٍ أو أكثر بحسب ما فُوتر منه.
 *
 * المخزون يتحرّك هنا وحده: عند اعتماد الاستلام (`approve()`)، عبر نفس آلية
 * الإذن المخزني (`WarehousePermits`) المستعملة في بقية النظام — لا عند
 * إنشاء أمر الشراء ولا عند تحويل الاستلام إلى فاتورة.
 */
class PurchaseReceivingController extends Controller
{
    use RespectsAccountingPeriods;
    use ScopesToEmployeeHierarchy;

    public const NUMBER_SECTION = 'purchase-receiving';

    public const LOG_MODULE = 'purchase_receiving';

    public function __construct()
    {
        $this->middleware('permission:purchases.receiving.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:purchases.receiving.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:purchases.receiving.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:purchases.receiving.delete', ['only' => ['destroy']]);
        $this->middleware('permission:purchases.receiving.approve', ['only' => ['approve']]);
        $this->middleware('permission:purchases.receiving.convert', ['only' => ['convertToInvoice']]);
    }

    private function rollBackIfOpen(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    public function index(Request $request)
    {
        $query = PurchaseReceiving::with(['purchaseOrder', 'supplier', 'storeHouse'])
            ->latest('id');

        $this->scopeToCreator($query);

        /* فرعُ الشريط العلويّ وما يتبعه — كانت هذه الشاشة وحدَها في القسم لا تقرأ
           الفرعَ أصلاً. ويصمت على محور الأشخاص كبقيّة الشاشات
           ({@see ScopesToEmployeeHierarchy::applyHeaderBranchScope()}). */
        if (! $this->scopeIsPeopleAxis()) {
            \Modules\Purchases\Support\HeaderPickers::constrain($query);
        }

        if ($request->filled('order_id')) {
            $query->where('purchase_order_id', $request->order_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $receivings = $query->paginate(20)->withQueryString();
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers(Supplier::select('id', 'trade_name'))->get();

        return view('purchases::purchases.purchase_receiving.index', compact('receivings', 'suppliers'));
    }

    public function create(Request $request)
    {
        $orderId = $request->query('order_id');
        $order = PurchaseInvoice::with(['items.product', 'supplier'])->findOrFail($orderId);
        $this->authorizeCreatorScope($order->created_by);

        if (! $order->isPurchaseOrder() || $order->status !== 'approval') {
            return redirect()->route('PurchaseOrders.show', $order->id)
                ->with('error', 'لا يمكن فتح استلام إلا لأمر شراء معتمَد.');
        }

        $lines = PurchaseFulfillmentLedger::forOrder($order)->filter(fn ($l) => $l['remaining_to_receive'] > 0);

        if ($lines->isEmpty()) {
            return redirect()->route('PurchaseOrders.show', $order->id)
                ->with('error', 'لا توجد كمّية متبقّية للاستلام في هذا الأمر.');
        }

        // مستودعاتُ فرع الشريط العلويّ — {@see \Modules\Purchases\Support\HeaderPickers}
        $storeHouses = \Modules\Purchases\Support\HeaderPickers::storeHouses(StoreHouse::where('status', 'active'))->get();

        /* المستودعُ المختار ابتداءً: مستودعُ الأمر إن كان له واحد، وإلّا مصدرُ
           المستودع من إعدادات المشتريات (الرئيسيّ أو مستودع الموظّف). كان الحقلُ
           يُفتح بلا اختيار. */
        $defaultStoreHouseId = $order->store_house_id ?? null;

        if (! $defaultStoreHouseId || ! $storeHouses->contains('id', $defaultStoreHouseId)) {
            $candidate = \Modules\Purchases\Support\PurchaseWarehouse::defaultId();
            $defaultStoreHouseId = $storeHouses->contains('id', $candidate) ? $candidate : null;
        }

        return view('purchases::purchases.purchase_receiving.create', compact('order', 'lines', 'storeHouses', 'defaultStoreHouseId'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required|integer|exists:purchase_invoices,id',
            'store_house_id' => 'required|integer|exists:store_houses,id',
            'receiving_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|integer|exists:invoice_items,id',
            'items.*.received_quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            DB::beginTransaction();

            $order = PurchaseInvoice::with('items')->lockForUpdate()->findOrFail($request->purchase_order_id);
            $this->authorizeCreatorScope($order->created_by);

            if (! $order->isPurchaseOrder() || $order->status !== 'approval') {
                throw new \Exception('لا يمكن فتح استلام إلا لأمر شراء معتمَد.');
            }

            if ($blocked = $this->accountingPeriodBlock($request->receiving_date)) {
                throw new \Exception($blocked);
            }

            $orderItemIds = $order->items->pluck('id')->all();

            $lines = [];
            foreach ($request->items as $submitted) {
                $orderItem = $order->items->firstWhere('id', (int) $submitted['purchase_order_item_id']);
                $qty = round((float) $submitted['received_quantity'], 4);

                if (! $orderItem || ! in_array($orderItem->id, $orderItemIds, true)) {
                    throw new \Exception('بند غير تابع لهذا الأمر.');
                }

                if ($qty <= 0) {
                    continue;
                }

                $remaining = PurchaseFulfillmentLedger::remainingToReceive($orderItem);
                if ($qty > $remaining + 0.0009) {
                    throw new \Exception('لا يمكن استلام كمّية أكبر من المتبقي للصنف «'.$orderItem->item.'» — المتبقي: '.$remaining);
                }

                $lines[] = ['orderItem' => $orderItem, 'quantity' => $qty];
            }

            if ($lines === []) {
                throw new \Exception('لا توجد كمّيات صالحة للاستلام.');
            }

            $branchId = \App\Support\Scopes::resolveBranchId(auth()->user());

            $receiving = PurchaseReceiving::create([
                'code' => next_document_number(self::NUMBER_SECTION),
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'store_house_id' => $request->store_house_id,
                'receiving_date' => $request->receiving_date,
                'status' => 'pending',
                'branch_id' => $branchId,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            /* تكلفةُ ما دخل المستودع — صافيةً بعد خصم البند والخصم العامّ
               وبلا الضريبة المتضمَّنة. انظر `PurchaseReceivingCost`. */
            $lineCosts = PurchaseReceivingCost::netLineCosts($order, $lines);

            $grandTotal = 0;
            foreach ($lines as $key => $line) {
                PurchaseReceivingItem::create([
                    'purchase_receiving_id' => $receiving->id,
                    'purchase_order_item_id' => $line['orderItem']->id,
                    'product_id' => $line['orderItem']->product_id,
                    'received_quantity' => $line['quantity'],
                ]);

                $grandTotal += $lineCosts[$key] ?? 0.0;
            }

            $permit = $this->createPendingPermit($receiving, $grandTotal);
            $receiving->update(['warehouse_permit_id' => $permit->id]);

            foreach ($lines as $key => $line) {
                WarehousePermitsProducts::create(array_merge(
                    ['warehouse_permits_id' => $permit->id],
                    $this->warehousePermitProductFields(
                        $line['orderItem'],
                        $line['quantity'],
                        $request->store_house_id,
                        $lineCosts[$key] ?? null
                    )
                ));
            }

            OperationLog::record('created', self::LOG_MODULE, $receiving,
                'تم إنشاء استلام مشتريات رقم '.$receiving->code.' لأمر الشراء رقم '.$order->code);

            DB::commit();

            return redirect()->route('PurchaseReceiving.show', $receiving->id)->with('success', 'تم إنشاء استلام المشتريات بنجاح — بانتظار الاعتماد.');
        } catch (\Throwable $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء حفظ الاستلام: '.$e->getMessage());
        }
    }

    /**
     * حقول بند الإذن المخزني — بالوحدة الأساسية دائماً، لا وحدة الفوترة.
     *
     * بند أمر الشراء يحمل الكمّية والسعر بوحدة الشراء (كرتون مثلاً)، بينما
     * `product_details.quantity` والإذن المخزني كلاهما بالوحدة الأساسية
     * (حبّة). خلطُهما يُدخل ١٩ حبةً بدل ١٩ كرتون (٢٢٨٠ حبة)، ويُخزِّن سعر
     * الكرتون كاملاً كسعر تكلفة الحبة الواحدة — فتتضخّم قيمة المخزون
     * بمقدار معامل التحويل. نفس التحويل المستعمَل في
     * `InvoicesPurchaseController::store()` عبر
     * `WarehousePermitsProducts::inventoryLineFromInvoiceItem()`.
     */
    private function warehousePermitProductFields(
        InvoiceItem $orderItem,
        float $enteredQty,
        int $storeHouseId,
        ?float $netCost = null
    ): array {
        $factor = (float) ($orderItem->conversion_factor ?: 1);

        /* `total` هو وعاءُ التكلفة الذي يقرؤه `Product::averageCost()` ومنه
           تُقيَّد تكلفةُ المبيعات — فيأخذ **صافي** ما دُفع في البضاعة، لا
           `كمية × سعر` الذي يتجاهل خصمَ البند والخصمَ العامّ معاً. نفس
           القاعدة التي تتبعها فاتورةُ الشراء عبر `line_costs`. */
        $total = $netCost !== null
            ? round($netCost, 2)
            : round($enteredQty * (float) $orderItem->unit_price, 2);

        return array_merge([
            'product_id' => $orderItem->product_id,
            'store_house_id' => $storeHouseId,
        ], WarehousePermitsProducts::inventoryLineFromInvoiceItem([
            'quantity_in_base_unit' => round($enteredQty * $factor, 4),
            'quantity' => $enteredQty,
            'total' => $total,
            'unit_price' => (float) $orderItem->unit_price,
            'conversion_factor' => $factor,
        ]));
    }

    private function createPendingPermit(PurchaseReceiving $receiving, float $grandTotal): WarehousePermits
    {
        $lastPermit = WarehousePermits::orderBy('id', 'desc')->first();
        $permitNumber = str_pad($lastPermit ? intval($lastPermit->number) + 1 : 1, 6, '0', STR_PAD_LEFT);

        return WarehousePermits::create([
            'permission_source_id' => PermissionSources::PURCHASE_RECEIVING,
            'permission_type' => 1,
            'permission_date' => $receiving->receiving_date,
            'sub_account' => $receiving->supplier_id,
            'number' => $permitNumber,
            'store_houses_id' => $receiving->store_house_id,
            'from_store_houses_id' => null,
            'to_store_houses_id' => $receiving->store_house_id,
            'grand_total' => round($grandTotal, 2),
            'details' => 'إذن إدخال بضاعة لاستلام مشتريات رقم: '.$receiving->code,
            'created_by' => Auth::id(),
            'status' => 'pending',
            'reference_type' => 'purchase_receiving',
            'reference_id' => $receiving->id,
        ]);
    }

    public function show($id)
    {
        $receiving = PurchaseReceiving::with([
            'purchaseOrder', 'supplier', 'storeHouse', 'creator', 'approver',
            'items.orderItem.product', 'invoices',
        ])->findOrFail($id);
        $this->authorizeCreatorScope($receiving->created_by);

        $lines = $receiving->items->map(fn (PurchaseReceivingItem $item) => [
            'item' => $item,
            'order_item' => $item->orderItem,
            'remaining_to_invoice' => $item->remainingToInvoice(),
        ]);

        // الخطّ الزمني للمستند — نفس تجميع شاشة أمر الشراء
        $logs = \App\Models\Log::where('type', self::LOG_MODULE)
            ->where('type_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        /* قيمة الاستلام — وهي نفسها التي قُيِّدت محاسبياً عند الاعتماد
           (`PurchaseReceivingAccounting::receiptValue()`) والتي يحملها الإذن
           المخزني. فما يُقرأ في الشاشة هو ما في الدفتر.

           و«الخصم» كان مكتوباً صفراً في القالب: الشاشة تعرض الإجماليَّ الخام
           وتقول إنّ خصمه صفر، ولو كان لأمرها خصمٌ عامّ أو خصومُ بنود. فصار
           يُحسب من الفرق بين الخام وصافي التكلفة — وهو ما نزل به الدفتر. */
        $gross = round($lines->sum(
            fn ($line) => (float) $line['item']->received_quantity * (float) ($line['order_item']->unit_price ?? 0)
        ), 2);

        $net = round(array_sum(PurchaseReceivingCost::netLineCosts(
            $receiving->purchaseOrder,
            $lines->map(fn ($line) => [
                'orderItem' => $line['order_item'],
                'quantity' => (float) $line['item']->received_quantity,
            ])->filter(fn ($line) => $line['orderItem'])->values()->all()
        )), 2);

        $totals = [
            'subtotal' => $gross,
            'discount' => max(0.0, round($gross - $net, 2)),
            'tax' => 0.0,
        ];
        $totals['grand_total'] = $net;

        return view('purchases::purchases.purchase_receiving.show', compact('receiving', 'lines', 'logs', 'totals'));
    }

    public function edit($id)
    {
        $receiving = PurchaseReceiving::with(['items.orderItem', 'purchaseOrder.items'])->findOrFail($id);
        $this->authorizeCreatorScope($receiving->created_by);

        if (! $receiving->isEditable()) {
            return redirect()->route('PurchaseReceiving.show', $id)->with('error', 'لا يمكن تعديل استلامٍ معتمَد.');
        }

        $order = $receiving->purchaseOrder;
        $currentQty = $receiving->items->keyBy('purchase_order_item_id');

        // السقف المعروض يستثني التزام هذا الاستلام نفسه بإعادته إلى
        // «المتبقي» — وإلا بدا للمستخدم أنه لا يملك إلا ما لم يُعده هذا
        // الاستلام أصلاً، رغم أن update() يحذف بنوده القديمة قبل التحقق
        $lines = PurchaseFulfillmentLedger::forOrder($order)->map(function ($line) use ($currentQty) {
            $already = (float) ($currentQty->get($line['item']->id)->received_quantity ?? 0);
            $line['remaining_to_receive'] = round($line['remaining_to_receive'] + $already, 4);
            $line['current_quantity'] = $already;

            return $line;
        });

        // مستودعاتُ فرع الشريط العلويّ، ومستودعُ هذا الاستلام يبقى
        $storeHouses = \Modules\Purchases\Support\HeaderPickers::storeHouses(
            StoreHouse::where('status', 'active'),
            [$receiving->store_house_id]
        )->get();

        return view('purchases::purchases.purchase_receiving.edit', compact('receiving', 'order', 'lines', 'storeHouses'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'store_house_id' => 'required|integer|exists:store_houses,id',
            'receiving_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|integer|exists:invoice_items,id',
            'items.*.received_quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            DB::beginTransaction();

            $receiving = PurchaseReceiving::with(['items', 'purchaseOrder.items', 'warehousePermit'])
                ->lockForUpdate()->findOrFail($id);
            $this->authorizeCreatorScope($receiving->created_by);

            if (! $receiving->isEditable()) {
                throw new \Exception('لا يمكن تعديل استلامٍ معتمَد.');
            }

            if ($blocked = $this->accountingPeriodBlock($request->receiving_date)) {
                throw new \Exception($blocked);
            }

            $order = $receiving->purchaseOrder;
            $orderItems = $order->items->keyBy('id');

            // إزالة البنود القديمة وبنود الإذن المخزني لإعادة بنائها من الصفر
            WarehousePermitsProducts::where('warehouse_permits_id', $receiving->warehouse_permit_id)->delete();
            $receiving->items()->delete();

            $lines = [];
            foreach ($request->items as $submitted) {
                $orderItem = $orderItems->get((int) $submitted['purchase_order_item_id']);
                $qty = round((float) $submitted['received_quantity'], 4);

                if (! $orderItem || $qty <= 0) {
                    continue;
                }

                // المتبقي هنا يستثني هذا الاستلام نفسه بحذفه أعلاه قبل الحساب
                $remaining = PurchaseFulfillmentLedger::remainingToReceive($orderItem);
                if ($qty > $remaining + 0.0009) {
                    throw new \Exception('لا يمكن استلام كمّية أكبر من المتبقي للصنف «'.$orderItem->item.'» — المتبقي: '.$remaining);
                }

                $lines[] = ['orderItem' => $orderItem, 'quantity' => $qty];
            }

            if ($lines === []) {
                throw new \Exception('لا توجد كمّيات صالحة للاستلام.');
            }

            // نفس وعاء التكلفة الذي يبنيه `store()` — صافياً بعد الخصمين
            $lineCosts = PurchaseReceivingCost::netLineCosts($order, $lines);

            $grandTotal = 0;
            foreach ($lines as $key => $line) {
                PurchaseReceivingItem::create([
                    'purchase_receiving_id' => $receiving->id,
                    'purchase_order_item_id' => $line['orderItem']->id,
                    'product_id' => $line['orderItem']->product_id,
                    'received_quantity' => $line['quantity'],
                ]);

                WarehousePermitsProducts::create(array_merge(
                    ['warehouse_permits_id' => $receiving->warehouse_permit_id],
                    $this->warehousePermitProductFields(
                        $line['orderItem'],
                        $line['quantity'],
                        $request->store_house_id,
                        $lineCosts[$key] ?? null
                    )
                ));

                $grandTotal += $lineCosts[$key] ?? 0.0;
            }

            $receiving->update([
                'store_house_id' => $request->store_house_id,
                'receiving_date' => $request->receiving_date,
                'notes' => $request->notes,
                'updated_by' => Auth::id(),
            ]);

            if ($receiving->warehousePermit) {
                $receiving->warehousePermit->update([
                    'permission_date' => $request->receiving_date,
                    'store_houses_id' => $request->store_house_id,
                    'to_store_houses_id' => $request->store_house_id,
                    'grand_total' => round($grandTotal, 2),
                ]);
            }

            OperationLog::record('updated', self::LOG_MODULE, $receiving, 'تم تعديل استلام المشتريات رقم '.$receiving->code);

            DB::commit();

            return redirect()->route('PurchaseReceiving.show', $receiving->id)->with('success', 'تم تحديث استلام المشتريات بنجاح.');
        } catch (\Throwable $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'update', $id);

            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء تحديث الاستلام: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $receiving = PurchaseReceiving::lockForUpdate()->findOrFail($id);
            $this->authorizeCreatorScope($receiving->created_by);

            if (! $receiving->isEditable()) {
                throw new \Exception('لا يمكن حذف استلامٍ معتمَد.');
            }

            $code = $receiving->code;

            if ($receiving->warehouse_permit_id) {
                WarehousePermitsProducts::where('warehouse_permits_id', $receiving->warehouse_permit_id)->delete();
                WarehousePermits::where('id', $receiving->warehouse_permit_id)->delete();
            }

            $receiving->items()->delete();
            $receiving->delete();

            OperationLog::record('deleted', self::LOG_MODULE, $id, 'تم حذف استلام المشتريات رقم '.$code);

            DB::commit();

            return redirect()->route('PurchaseReceiving.index')->with('success', 'تم حذف استلام المشتريات بنجاح.');
        } catch (\Throwable $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء حذف الاستلام: '.$e->getMessage());
        }
    }

    public function approve($id)
    {
        try {
            DB::beginTransaction();

            $receiving = PurchaseReceiving::with(['items.orderItem', 'warehousePermit'])
                ->lockForUpdate()->findOrFail($id);
            $this->authorizeCreatorScope($receiving->created_by);

            if ($receiving->status !== 'pending') {
                throw new \Exception('هذا الاستلام معتمَد أو ملغى بالفعل.');
            }

            if ($blocked = $this->accountingPeriodBlock($receiving->receiving_date)) {
                throw new \Exception($blocked);
            }

            // إعادة التحقق لحظة الاعتماد — يقفل سباق استلامين متزامنين
            foreach ($receiving->items as $item) {
                $orderItem = $item->orderItem;
                $othersCommitted = PurchaseReceivingItem::where('purchase_order_item_id', $orderItem->id)
                    ->where('id', '!=', $item->id)
                    ->whereHas('receiving', fn ($q) => $q->where('status', '!=', 'cancelled'))
                    ->sum('received_quantity');

                if ((float) $item->received_quantity + (float) $othersCommitted > (float) $orderItem->quantity + 0.0009) {
                    throw new \Exception('تعارض في الكمّيات المستلمة للصنف «'.$orderItem->item.'» — راجع الاستلامات الأخرى لهذا الأمر.');
                }
            }

            $storeHouseId = $receiving->store_house_id;

            foreach ($receiving->items as $item) {
                $orderItem = $item->orderItem;

                if (! $orderItem->product_id || StockEligibility::isStockless($orderItem->product_id)) {
                    continue;
                }

                $factor = (float) ($orderItem->conversion_factor ?: 1);
                $baseQty = round((float) $item->received_quantity * $factor, 4);

                $productDetail = ProductDetails::firstOrCreate(
                    ['store_house_id' => $storeHouseId, 'product_id' => $orderItem->product_id],
                    ['quantity' => 0]
                );

                $productDetail->quantity += $baseQty;

                // سعر التكلفة يُخزَّن للوحدة الأساسية دائماً — سعر بند
                // الأمر بوحدة الشراء (كرتون) لا يُنسَخ خاماً، وإلا صار سعر
                // الكرتون سعرَ الحبّة الواحدة وتضخّمت قيمة المخزون بمقدار
                // معامل التحويل. نفس دالة `WarehousePermitsProducts` التي
                // تبني بنود الإذن أعلاه.
                if ($orderItem->unit_price > 0) {
                    $productDetail->unit_price = WarehousePermitsProducts::unitPricePerInventoryQuantity(
                        $baseQty, 0, (float) $orderItem->unit_price, $factor
                    );
                }

                $productDetail->save();
            }

            /* قيد الاستلام — قبل اعتماد الإذن لا بعده.
               مُراقِب `JournalEntry` يعتمد الإذن متى رُحِّل قيدُه، فترحيل
               القيد أوّلاً يجعل الاعتماد أثراً للقيد لا خطوةً مستقلّة عنه.
               ويبقى السطر أدناه للإذن الذي لم يبلغه المُراقِب — استلامٌ
               كلُّه أصنافٌ بلا رصيد لا قيد له، وإذنُه يُعتمد مع ذلك. */
            if ($receiving->warehousePermit) {
                PurchaseReceivingAccounting::postReceiptEntry($receiving, $receiving->warehousePermit);

                $receiving->warehousePermit->refresh();

                if ($receiving->warehousePermit->status === 'pending') {
                    $receiving->warehousePermit->update(['status' => 'apporod']);
                }
            }

            $receiving->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            PurchaseFulfillmentLedger::syncOrderReceivingStatus($receiving->purchaseOrder);

            OperationLog::record('approved', self::LOG_MODULE, $receiving, 'تم اعتماد استلام المشتريات رقم '.$receiving->code.' وتحديث المخزون');

            DB::commit();

            return redirect()->route('PurchaseReceiving.show', $receiving->id)->with('success', 'تم اعتماد الاستلام وتحديث المخزون بنجاح.');
        } catch (\Throwable $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'approve', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء اعتماد الاستلام: '.$e->getMessage());
        }
    }

    public function convertToInvoice(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $receiving = PurchaseReceiving::with(['purchaseOrder', 'supplier'])
                ->lockForUpdate()->findOrFail($id);
            $this->authorizeCreatorScope($receiving->created_by);

            if ($receiving->status !== 'approved') {
                throw new \Exception('لا يمكن تحويل استلامٍ لم يُعتمد بعد.');
            }

            $order = $receiving->purchaseOrder;

            if ($blocked = $this->accountingPeriodBlock(now())) {
                throw new \Exception($blocked);
            }

            $submitted = collect($request->input('items', []))->keyBy('purchase_receiving_item_id');

            $lines = [];
            $leftoverQty = 0;
            $rawSubtotal = 0;
            $includedItemTax = 0;
            $totalTax = 0;
            $taxDetails = [];

            $receivingItems = PurchaseReceivingItem::with('orderItem.product')
                ->where('purchase_receiving_id', $receiving->id)
                ->lockForUpdate()
                ->get();

            foreach ($receivingItems as $receivingItem) {
                $orderItem = $receivingItem->orderItem;
                $remaining = $receivingItem->remainingToInvoice();

                $requestedQty = $submitted->has($receivingItem->id)
                    ? (float) $submitted->get($receivingItem->id)['invoice_quantity']
                    : $remaining;

                $qty = round($requestedQty, 4);

                if ($qty <= 0) {
                    continue;
                }

                if ($qty > $remaining + 0.0009) {
                    throw new \Exception('لا يمكن فوترة كمّية أكبر من المتبقي للصنف «'.$orderItem->item.'» — المتبقي للفوترة: '.$remaining);
                }

                $tax1Rate = $orderItem->tax_1_id ? PurchaseTaxCalculator::rate($orderItem->tax_1_id) : (float) $orderItem->tax_1;
                $tax2Rate = $orderItem->tax_2_id ? PurchaseTaxCalculator::rate($orderItem->tax_2_id) : (float) $orderItem->tax_2;

                $ratio = ((float) $orderItem->quantity) > 0 ? $qty / (float) $orderItem->quantity : 0;
                $lineDiscount = round((float) ($orderItem->discount ?? 0) * $ratio, 2);
                $lineGross = round($qty * (float) $orderItem->unit_price, 2);

                $factor = (float) ($orderItem->conversion_factor ?: 1);

                // ما يبقى غيرَ مفوتَرٍ على هذا الاستلام بعد هذه الدفعة
                $leftoverQty += max(0, $remaining - $qty);

                $rawSubtotal += $lineGross;

                // الضريبة تُحسب بعد الحلقة: وعاؤها صافي البند ناقصاً حصّته من
                // الخصم العامّ، والحصّة لا تُعرف قبل جمع البنود كلّها
                $lines[] = [
                    'receivingItem' => $receivingItem,
                    'orderItem' => $orderItem,
                    'quantity' => $qty,
                    'discount' => $lineDiscount,
                    'net' => max(0.0, round($lineGross - $lineDiscount, 2)),
                    'tax_1' => $tax1Rate,
                    'tax_2' => $tax2Rate,
                    'quantity_in_base_unit' => round($qty * $factor, 4),
                ];
            }

            if ($lines === []) {
                throw new \Exception('لا توجد كمّيات صالحة للفوترة من هذا الاستلام.');
            }

            /* ══════════ الخصمُ العامّ يُنقص وعاءَ الضريبة ══════════

               كانت ضريبةُ كلّ بندٍ تُحسب على صافيه بعد خصمه الخاصّ وحده،
               ثمّ يُطرح الخصمُ العامّ من الإجمالي بعد ذلك — فتخرج الفاتورةُ
               بضريبةٍ أعلى ممّا على المرتجع والإشعار وفاتورة الشراء
               المباشرة، وكلُّها توزّع الخصمَ العامّ على البنود قبل الضريبة
               (`PurchaseDocumentTotals::compute()`). أمرٌ بندُه ٢٥٠٠ وخصمُه
               العامّ ١٠٠: الفاتورة ٣٦٧٫٥٠ والمرتجعُ عنها ٣٥٢٫٥٠ — فلا يعكسه.

               حصّةُ هذه الفاتورة من خصم الأمر بنسبة ما فُوتِر من صافي بنوده،
               وما حملته الفواتيرُ السابقة يُطرح — كما يُوزَّع الشحن أدناه. */
            $lineNets = collect($lines)->pluck('net')->all();
            $orderNetItems = round($order->items->sum(
                fn ($item) => max(0.0, (float) $item->quantity * (float) $item->unit_price - (float) ($item->discount ?? 0))
            ), 2);
            $invoicedNet = round(array_sum($lineNets), 2);

            $carriedDiscount = (float) PurchaseInvoice::where('reference_id', $order->id)
                ->where('type', 'invoice')
                ->sum('discount_amount');
            $remainingDiscount = max(0.0, round((float) ($order->discount_amount ?? 0) - $carriedDiscount, 2));
            $discountShareForTax = $orderNetItems > 0
                ? min($remainingDiscount, round((float) ($order->discount_amount ?? 0) * ($invoicedNet / $orderNetItems), 2))
                : 0.0;

            $shares = PurchaseTaxCalculator::allocateDocumentDiscount($lineNets, $discountShareForTax);

            foreach ($lines as $key => $entry) {
                $orderItem = $entry['orderItem'];
                $taxableBase = max(0.0, round($entry['net'] - ($shares[$key] ?? 0.0), 2));

                $line = PurchaseTaxCalculator::line(
                    1.0,
                    $taxableBase,
                    0.0,
                    [$orderItem->tax_1_id, $orderItem->tax_2_id],
                    [$entry['tax_1'], $entry['tax_2']],
                );

                foreach ($line['taxes'] as $detail) {
                    $setting = PurchaseTaxCalculator::find($detail['id']);
                    if (! $setting) {
                        continue;
                    }

                    $taxDetails[] = [
                        'name' => $setting->name,
                        'type' => $setting->type,
                        'rate' => $detail['rate'],
                        'value' => $detail['amount'],
                        'product_id' => $orderItem->product_id,
                    ];
                }

                $includedItemTax += $line['base'] - $line['net'];
                $totalTax += $line['tax'];
                $lines[$key]['total'] = $line['total'];
            }

            $subtotal = round($rawSubtotal - $includedItemTax, 2);
            $lineDiscount = round(collect($lines)->sum('discount'), 2);

            /* ══════════ شحنُ الأمر وضريبتُه وخصمُ مستنده ══════════

               كانت الفاتورة المحوَّلة تُكتب بـ`'shipping_cost' => 0` وبخصمِ
               السطور وحدها، فيضيع من الأمر شحنُه وضريبةُ شحنه وخصمُه
               العامّ: أمرٌ بإجمالي 4104.25 يخرج فاتورةً بـ4019.25.

               وهذه الثلاثة تخصّ **المستند كلَّه** لا سطراً بعينه، فتُوزَّع
               على الفواتير المولودة منه بنسبة ما فُوتِر، ويُطرح ما حملته
               الفواتير السابقة — فلا يُحمَّل الشحنُ مرّتين على أمرٍ يُفوتَر
               على دفعات. ومتى أنهت هذه الدفعةُ الاستلامَ أخذت الباقي
               كاملاً، فلا يضيع كسرُ التقريب. */
            $orderSubtotal = round((float) ($order->subtotal ?? 0), 2);
            $ratio = $orderSubtotal > 0 ? min(1.0, $subtotal / $orderSubtotal) : 1.0;
            $finishesReceiving = $leftoverQty <= 0.0009;

            $carried = PurchaseInvoice::where('reference_id', $order->id)
                ->where('type', 'invoice')
                ->selectRaw('COALESCE(SUM(shipping_cost), 0) AS s, COALESCE(SUM(shipping_tax), 0) AS st, COALESCE(SUM(discount_amount), 0) AS d')
                ->first();

            $allocate = function (float $orderTotal, float $already) use ($ratio, $finishesReceiving): float {
                $remaining = round($orderTotal - $already, 2);

                if ($remaining <= 0) {
                    return 0.0;
                }

                return $finishesReceiving ? $remaining : min($remaining, round($orderTotal * $ratio, 2));
            };

            $shippingCost = $allocate((float) ($order->shipping_cost ?? 0), (float) ($carried->s ?? 0));
            $shippingTax = $allocate((float) ($order->shipping_tax ?? 0), (float) ($carried->st ?? 0));
            $documentDiscount = $allocate((float) ($order->discount_amount ?? 0), (float) ($carried->d ?? 0));

            $totalDiscount = round($lineDiscount + $documentDiscount, 2);
            $totalTaxWithShipping = round($totalTax + $shippingTax, 2);

            /* نفسُ معادلة `writeInvoiceEntryDetails()` مقلوبةً، فيخرج سطرُ
               المشتريات في القيد مساوياً `$subtotal` بالضبط. */
            $grandTotal = round($subtotal - $totalDiscount + $totalTaxWithShipping + $shippingCost, 2);

            $paymentStatus = $request->input('payment_status', 'deferred');
            $advanceAmount = (float) $request->input('advance_amount', 0);

            $payment_status = 'unpaid';
            $finalAdvanceAmount = 0;

            if ($paymentStatus === 'paid') {
                $payment_status = 'paid';
                $finalAdvanceAmount = $grandTotal;
            } elseif ($advanceAmount > 0) {
                $finalAdvanceAmount = min($advanceAmount, $grandTotal);
                $payment_status = $finalAdvanceAmount >= $grandTotal ? 'paid' : 'partially_paid';
            }

            $dueValue = $grandTotal - $finalAdvanceAmount;

            $mainTreasuryAccount = PurchaseInvoiceConversionAccounting::resolveTreasury();
            PurchaseInvoiceConversionAccounting::assertSufficientBalance($mainTreasuryAccount, $finalAdvanceAmount);

            $code = next_document_number(InvoicesPurchaseController::NUMBER_SECTION);

            $invoice = PurchaseInvoice::create([
                'supplier_id' => $order->supplier_id,
                'code' => $code,
                'type' => 'invoice',
                'date' => $receiving->receiving_date,
                'notes' => "محوَّلة من استلام مشتريات رقم: {$receiving->code} (أمر شراء رقم: {$order->code})",
                'payment_status' => $payment_status,
                'receiving_status' => 'received',
                'is_received' => true,
                'received_date' => $receiving->approved_at ?? now(),
                'created_by' => Auth::id(),
                'account_id' => $order->account_id,
                'discount_amount' => $documentDiscount,
                'discount_type' => 1,
                'payment_type' => $order->payment_type ?? 1,
                'shipping_cost' => $shippingCost,
                'shipping_tax' => $shippingTax,
                'tax_type' => $order->tax_type ?? 1,
                'reference_number' => $order->code,
                'reference_id' => $order->id,
                'purchase_receiving_id' => $receiving->id,
                'is_paid' => $payment_status === 'paid',
                'subtotal' => round($subtotal, 2),
                'total_discount' => $totalDiscount,
                'total_tax' => $totalTaxWithShipping,
                'grand_total' => $grandTotal,
                'due_value' => $dueValue,
                'advance_payment' => $finalAdvanceAmount,
                'branch_id' => $order->branch_id,
            ]);

            foreach ($lines as $line) {
                $orderItem = $line['orderItem'];

                InvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'purchase_receiving_item_id' => $line['receivingItem']->id,
                    'product_id' => $orderItem->product_id,
                    'item' => $orderItem->item,
                    'description' => $orderItem->description,
                    'quantity' => $line['quantity'],
                    'unit_price' => $orderItem->unit_price,
                    'discount' => $line['discount'],
                    'discount_type' => $orderItem->discount_type,
                    'tax_1' => $line['tax_1'],
                    'tax_1_id' => $orderItem->tax_1_id,
                    'tax_2' => $line['tax_2'],
                    'tax_2_id' => $orderItem->tax_2_id,
                    'total' => $line['total'],
                    'store_house_id' => $receiving->store_house_id,
                    'product_unit_id' => $orderItem->product_unit_id,
                    'unit_name' => $orderItem->unit_name,
                    'conversion_factor' => $orderItem->conversion_factor,
                    'quantity_in_base_unit' => $line['quantity_in_base_unit'],
                ]);

                $line['receivingItem']->increment('invoiced_quantity', $line['quantity']);
            }

            foreach ($taxDetails as $detail) {
                \App\Models\TaxInvoice::create([
                    'name' => $detail['name'],
                    'invoice_id' => $invoice->id,
                    'type' => $detail['type'],
                    'rate' => $detail['rate'],
                    'value' => $detail['value'],
                    'type_invoice' => 'purchase',
                    'product_id' => $detail['product_id'],
                ]);
            }

            if ($finalAdvanceAmount > 0) {
                \App\Models\PaymentsProcess::create([
                    'purchases_id' => $invoice->id,
                    'supplier_id' => $order->supplier_id,
                    'amount' => $finalAdvanceAmount,
                    'payment_date' => now(),
                    'payment_method' => $order->payment_method ?? 1,
                    'type' => 'supplier payments',
                    'payment_status' => 1,
                    'employee_id' => Auth::id(),
                    'treasury_id' => $mainTreasuryAccount->id,
                    'notes' => 'دفعة لفاتورة المشتريات رقم '.$invoice->code.' (محوَّلة من استلام '.$receiving->code.') من خزينة '.$mainTreasuryAccount->name,
                ]);
            }

            PurchaseInvoiceConversionAccounting::postSupplierLiabilityEntry($invoice, $finalAdvanceAmount, $mainTreasuryAccount);

            /* إعادة التصنيف إلى المستودع تقع مرّةً واحدة.
               صار اعتمادُ الاستلام يكتبها بنفسه، فالفاتورة المولودة منه
               لا تعيدها — وإلّا دخلت قيمةُ البضاعة المستودعَ مرّتين.
               وتبقى للاستلامات التي اعتُمدت قبل هذا التغيير: لا قيد لها،
               فلو تخطّتها الفاتورة أيضاً لم تدخل قيمتُها المستودع أبداً. */
            if (! PurchaseReceivingAccounting::hasReceiptEntry($receiving)) {
                PurchaseInvoiceConversionAccounting::postReceiptReclassEntry($invoice->fresh('items'), $receiving->store_house_id);
            }

            OperationLog::record('converted', self::LOG_MODULE, $receiving,
                'تم تحويل استلام المشتريات رقم '.$receiving->code.' إلى فاتورة رقم '.$invoice->code);

            OperationLog::record('created', InvoicesPurchaseController::LOG_MODULE, $invoice,
                __('purchase_cycle.log.created', [
                    'document' => __('purchase_cycle.documents.purchase_invoice'),
                    'code' => $invoice->code,
                ]));

            DB::commit();

            return redirect()->route('invoicePurchases.show', $invoice->id)
                ->with('success', 'تم تحويل الاستلام إلى فاتورة شراء رقم '.$invoice->code.' بنجاح.');
        } catch (\Throwable $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'convert', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء تحويل الاستلام إلى فاتورة: '.$e->getMessage());
        }
    }
}
