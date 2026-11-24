<?php

namespace Modules\Integration\Http\Controllers\Api;

use App\Constants\permissions\PermissionSources;
use App\Constants\planFeatures\SalesPlanFeatures;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Client;
use App\Models\DefaultWarehouses;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\ProductExchange;
use App\Models\ProductExchangeItem;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Settings\ApplicationRegistry;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Controllers\Concerns\ChecksSubscriptionLimits;
use Modules\Sales\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Sales\Http\Controllers\Concerns\ResolvesSaleCost;
use Modules\Sales\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Sales\Http\Controllers\Concerns\ScopesSalesVisibility;
use Modules\Sales\Http\Controllers\Concerns\UsesDocumentCurrency;

/**
 * تبديل بضاعة (تالفة بسليمة) في تطبيق الجوال — إضافةٌ وعرضٌ وطباعة، نفس
 * قواعد شاشة الموقع (`Modules\Sales\Http\Controllers\ProductExchangeController`):
 * حركةُ مخزونٍ باتّجاهين (صادرٌ من مستودع، واردٌ إلى آخر مختلف) بإذنَي
 * صرفٍ وقيدَين محاسبيَّين، بلا أثرٍ على ذمّة العميل أو فاتورةٍ مرجعية —
 * ليست مرتبطةً بفاتورةٍ أصلاً.
 *
 * **إضافةٌ عن الأصل**: `show()` هنا يفحص نطاق المستخدم
 * (`canAccessSalesDocument`) كما يفعل الفاتورة والمرتجع والإشعار —
 * الشاشةُ الأصلية لا تفحصه (ثغرةٌ مذكورةٌ في تقرير الاستكشاف)، وأراه
 * أَولى بالتشديد في واجهة API مفتوحة برقم مستندٍ صريح.
 */
class ProductExchangeController extends Controller
{
    use ChecksSubscriptionLimits;
    use ResolvesAccountingAccounts;
    use ResolvesSaleCost;
    use RespectsAccountingPeriods;
    use ScopesSalesVisibility;
    use UsesDocumentCurrency;

    public function __construct()
    {
        $this->middleware(function (Request $request, \Closure $next) {
            if (! ApplicationRegistry::isAvailable('sales')) {
                return response()->json(['success' => false, 'message' => __('settings.applications.blocked', ['module' => ApplicationRegistry::labelFor('sales')])], 403);
            }

            return $next($request);
        });
    }

    /**
     * قائمة التبديلات — نفس فلاتر ونطاق شاشة الفهرس في الموقع.
     *
     * الاستعلامات المقبولة: client_id, code, from_date, to_date, branch
     * (فرع التبديل نفسه، مُجمَّدٌ وقت الإنشاء)، per_page (10|25|50|100)، page.
     *
     * ✅ صلاحية العرض محروسة في المسار (permission.role:sales.exchanges.view)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductExchange::with(['client', 'items.product', 'items.exchangeReason', 'employee'])
            ->orderByDesc('exchange_date')->orderByDesc('id');

        $this->applyHeaderBranchScope($query);
        $this->scopeSalesDocuments($query);

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('code')) {
            $query->where('code', 'like', '%'.$request->code.'%');
        }
        if ($request->filled('from_date')) {
            $query->whereDate('exchange_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('exchange_date', '<=', $request->to_date);
        }
        if ($request->filled('branch')) {
            $query->where('branch_id', $request->branch);
        }

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $exchanges = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => $exchanges->getCollection()->map(fn ($e) => $this->exchangePayload($e)),
            'meta' => [
                'current_page' => $exchanges->currentPage(),
                'last_page' => $exchanges->lastPage(),
                'per_page' => $exchanges->perPage(),
                'total' => $exchanges->total(),
            ],
        ]);
    }

    /**
     * الحقول: client_id, storehouse_id (الصادر), return_storehouse_id
     * (الوارد — يجب أن يختلف عن الصادر), exchange_date, image (ملفّ، إلزامي)،
     * notes?, items[] { product_id, quantity, reason (معرّف من قائمة أسباب التبديل) }.
     */
    // ✅ صلاحية الإنشاء محروسة في المسار (permission.role:sales.exchanges.create)
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'client_id' => 'required|integer|exists:clients,id',
                'storehouse_id' => 'required|integer|exists:store_houses,id',
                'return_storehouse_id' => 'required|integer|exists:store_houses,id|different:storehouse_id',
                'exchange_date' => 'required|date',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'notes' => 'nullable|string',
                'employee_id' => 'nullable|integer|exists:employees,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.reason' => 'required|integer|exists:exchange_reasons,id',
                'items.*.reason_notes' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        }

        $user = $request->user();
        if (! Client::whereKey($validated['client_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'العميل غير موجود.'], 404);
        }

        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::PRODUCT_EXCHANGES)) {
            return response()->json(['success' => false, 'message' => $blocked], 402);
        }

        $employeeId = $validated['employee_id'] ?? $user->employee_id;

        // ═══ سقف الموظّف اليومي ═══
        $employee = Employee::find($employeeId);
        if ($employee && $employee->daily_qty_limit) {
            $totalQtyRequested = array_sum(array_column($validated['items'], 'quantity'));
            $todayQty = (int) ProductExchangeItem::whereHas('productExchange', fn ($q) => $q->where('employee_id', $employeeId)->whereDate('exchange_date', $validated['exchange_date']))->sum('quantity');
            if ($todayQty + $totalQtyRequested > $employee->daily_qty_limit) {
                return response()->json(['success' => false, 'message' => "تجاوزتَ السقف اليومي للموظّف ({$employee->daily_qty_limit} قطعة)."], 422);
            }
        }

        // ═══ سقف العميل اليومي ═══
        $client = Client::find($validated['client_id']);
        $clientLimit = $client->daily_exchange_limit ?: (int) (AccountSetting::first()->global_client_exchange_limit ?? 0);
        if ($clientLimit > 0) {
            $totalQtyRequested = array_sum(array_column($validated['items'], 'quantity'));
            $todayQty = (int) ProductExchangeItem::whereHas('productExchange', fn ($q) => $q->where('client_id', $validated['client_id'])->whereDate('exchange_date', $validated['exchange_date']))->sum('quantity');
            if ($todayQty + $totalQtyRequested > $clientLimit) {
                return response()->json(['success' => false, 'message' => "تجاوز العميل سقفه اليومي للتبديل ($clientLimit قطعة)."], 422);
            }
        }

        [$outStoreId, $inStoreId] = $this->resolveStorehouses($validated['storehouse_id'], $validated['return_storehouse_id'], $user);

        if ($blocked = $this->accountingPeriodBlock($validated['exchange_date'])) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        // ═══ كفاية المخزون — لكل الأسطر قبل أي كتابة ═══
        $products = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))->get()->keyBy('id');
        foreach ($validated['items'] as $row) {
            $product = $products->get($row['product_id']);
            if (! $product) {
                return response()->json(['success' => false, 'message' => 'أحد الأصناف غير موجود.'], 422);
            }
            $available = (float) ProductDetails::where('product_id', $product->id)->where('store_house_id', $outStoreId)->value('quantity');
            if ($available < $row['quantity']) {
                return response()->json(['success' => false, 'message' => "الكمية المتاحة من «{$product->name}» غير كافية — المطلوب {$row['quantity']}، المتاح $available."], 422);
            }
        }

        $imagePath = $this->storeImage($request->file('image'));

        DB::beginTransaction();

        try {
            $code = next_document_number('product-exchange');

            $exchange = ProductExchange::create([
                'code' => $code,
                'client_id' => $validated['client_id'],
                'branch_id' => current_branch_id(),
                'employee_id' => $employeeId,
                'storehouse_id' => $outStoreId,
                'return_storehouse_id' => $inStoreId,
                'created_by' => $user->id,
                'exchange_date' => $validated['exchange_date'],
                'notes' => $validated['notes'] ?? null,
                'image' => $imagePath,
                'total_value' => 0,
                'status' => 1,
            ]);

            $this->applyExchangeEffects($exchange, $validated['items'], $products, $outStoreId, $inStoreId, $user);

            OperationLog::record('created', 'ProductExchanges', $exchange, 'إنشاء تبديل بضاعة «'.$exchange->code.'» — عبر تطبيق الجوال');

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'ProductExchanges', 'integration.store');
            @unlink(public_path('uploads/product_exchanges/'.$imagePath));

            return response()->json(['success' => false, 'message' => 'تعذّر حفظ التبديل: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => '✨ تم إنشاء التبديل بنجاح!',
            'data' => $this->exchangePayload($exchange->fresh(['client', 'items.product', 'items.exchangeReason'])),
        ], 201);
    }

    // ✅ صلاحية العرض محروسة في المسار (permission.role:sales.exchanges.view)
    public function show(Request $request, int $id): JsonResponse
    {
        $exchange = ProductExchange::with(['client', 'items.product', 'items.exchangeReason', 'employee'])->find($id);
        if (! $exchange) {
            return response()->json(['success' => false, 'message' => 'التبديل غير موجود.'], 404);
        }

        if (! $this->canAccessSalesDocument($exchange->created_by, $exchange->employee_id, $exchange->branch_id)) {
            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية عرض هذا التبديل.'], 403);
        }

        return response()->json(['success' => true, 'data' => $this->exchangePayload($exchange)]);
    }

    /**
     * لا نظام قوالب لهذا المستند في الموقع — نفس القالب المدمج (HTML، لا PDF).
     *
     * ✅ صلاحية الطباعة محروسة في المسار (permission.role:sales.exchanges.print)
     */
    public function print(Request $request, int $id): \Illuminate\Http\Response
    {
        $exchange = ProductExchange::with(['client', 'items.product', 'items.exchangeReason'])->findOrFail($id);
        abort_unless($this->canAccessSalesDocument($exchange->created_by, $exchange->employee_id, $exchange->branch_id), 403, 'لا تملك صلاحية عرض هذا التبديل.');

        $accountSetting = AccountSetting::first();

        return response()->view('sales::product_exchange.pdf', ['exchange' => $exchange, 'account_setting' => $accountSetting]);
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /** @return array{0: int, 1: int} [مستودع الصادر, مستودع الوارد] بعد تقييدهما بمستودعات الموظّف إن كانت له */
    private function resolveStorehouses(int $requestedOut, int $requestedIn, $user): array
    {
        $employeeId = $user->employee_id;
        $allowed = $employeeId ? DefaultWarehouses::where('employee_id', $employeeId)->pluck('storehouse_id')->all() : [];

        if (! empty($allowed)) {
            $out = in_array($requestedOut, $allowed, true) ? $requestedOut : $allowed[0];
            $in = in_array($requestedIn, $allowed, true) && $requestedIn !== $out
                ? $requestedIn
                : (collect($allowed)->first(fn ($id) => $id !== $out) ?? $requestedIn);

            return [$out, $in];
        }

        return [$requestedOut, $requestedIn];
    }

    private function storeImage($file): string
    {
        $destination = public_path('uploads/product_exchanges');
        if (! file_exists($destination)) {
            @mkdir($destination, 0755, true);
        }

        $filename = time().'_'.uniqid().'.jpg';
        $info = getimagesize($file->getRealPath());
        $image = match ($info['mime'] ?? '') {
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/gif' => imagecreatefromgif($file->getRealPath()),
            default => imagecreatefromjpeg($file->getRealPath()),
        };

        $maxDimension = 800;
        [$w, $h] = [$info[0] ?? imagesx($image), $info[1] ?? imagesy($image)];
        if ($w > $maxDimension || $h > $maxDimension) {
            $ratio = min($maxDimension / $w, $maxDimension / $h);
            $resized = imagecreatetruecolor((int) ($w * $ratio), (int) ($h * $ratio));
            imagecopyresampled($resized, $image, 0, 0, 0, 0, (int) ($w * $ratio), (int) ($h * $ratio), $w, $h);
            imagedestroy($image);
            $image = $resized;
        }

        imagejpeg($image, $destination.'/'.$filename, 70);
        imagedestroy($image);

        return $filename;
    }

    private function applyExchangeEffects(ProductExchange $exchange, array $items, $products, int $outStoreId, int $inStoreId, $user): void
    {
        $outPermit = WarehousePermits::create([
            'permission_type' => 2, 'permission_date' => $exchange->exchange_date,
            'reference_type' => 'ProductExchange', 'reference_id' => $exchange->id,
            'permission_source_id' => PermissionSources::PRODUCT_EXCHANGE,
            'client_id' => $exchange->client_id, 'number' => $exchange->code.'-OUT',
            'store_houses_id' => $outStoreId, 'currency' => $this->defaultCurrency(),
            'created_by' => $user->id, 'status' => 'apporod',
        ]);
        $inPermit = WarehousePermits::create([
            'permission_type' => 1, 'permission_date' => $exchange->exchange_date,
            'reference_type' => 'ProductExchange', 'reference_id' => $exchange->id,
            'permission_source_id' => PermissionSources::PRODUCT_EXCHANGE_RETURN,
            'client_id' => $exchange->client_id, 'number' => $exchange->code.'-IN',
            'store_houses_id' => $inStoreId, 'currency' => $this->defaultCurrency(),
            'created_by' => $user->id, 'status' => 'apporod',
        ]);

        $totalValue = 0.0;
        $outAccount = $this->warehouseAccount($outStoreId);
        $inAccount = $this->warehouseAccount($inStoreId);

        foreach ($items as $row) {
            $product = $products->get($row['product_id']);
            $unitCost = $this->averageUnitCost($product) ?? 0.0;
            $lineTotal = round($unitCost * $row['quantity'], 2);
            $totalValue += $lineTotal;

            // الصادر — ينقص من مستودع الصادر
            $outDetails = ProductDetails::where('product_id', $product->id)->where('store_house_id', $outStoreId)->lockForUpdate()->first();
            $outBefore = $outDetails->quantity;
            $outDetails->decrement('quantity', $row['quantity']);

            WarehousePermitsProducts::create([
                'warehouse_permits_id' => $outPermit->id, 'product_id' => $product->id,
                'quantity' => $row['quantity'], 'unit_price' => $unitCost, 'total' => $lineTotal,
                'stock_before' => $outBefore, 'stock_after' => $outBefore - $row['quantity'], 'store_house_id' => $outStoreId,
            ]);

            // الوارد — يزيد في مستودع الوارد (بضاعةٌ تالفة تدخل مستودع التالف)
            $inDetails = ProductDetails::firstOrCreate(['product_id' => $product->id, 'store_house_id' => $inStoreId], ['quantity' => 0]);
            $inBefore = $inDetails->quantity;
            $inDetails->increment('quantity', $row['quantity']);

            WarehousePermitsProducts::create([
                'warehouse_permits_id' => $inPermit->id, 'product_id' => $product->id,
                'quantity' => $row['quantity'], 'unit_price' => $unitCost, 'total' => $lineTotal,
                'stock_before' => $inBefore, 'stock_after' => $inBefore + $row['quantity'], 'store_house_id' => $inStoreId,
            ]);

            ProductExchangeItem::create([
                'product_exchange_id' => $exchange->id, 'product_id' => $product->id,
                'quantity' => $row['quantity'], 'unit_price' => $unitCost, 'total' => $lineTotal,
                'reason_id' => $row['reason'], 'reason_notes' => $row['reason_notes'] ?? null,
            ]);
        }

        $exchange->update(['total_value' => $totalValue, 'outgoing_permit_id' => $outPermit->id, 'incoming_permit_id' => $inPermit->id]);

        if ($totalValue > 0) {
            $outEntry = \App\Models\JournalEntry::create([
                'date' => $exchange->exchange_date, 'description' => 'تبديل بضاعة (صادر) — '.$exchange->code,
                'status' => true, 'currency' => $this->defaultCurrency(), 'client_id' => $exchange->client_id,
                'employee_id' => $exchange->employee_id,
                'reference_type' => ReferenceType::PRODUCT_EXCHANGE->value, 'reference_number' => $exchange->code.'-OUT',
                'branch_id' => $exchange->branch_id, 'is_posted' => true, 'linking_type' => \App\Enums\LinkingType::SINGLE->value,
            ]);
            \App\Models\JournalEntryDetail::create(['journal_entry_id' => $outEntry->id, 'account_id' => $this->requireAccountingAccount('cost_of_sales')->id, 'debit' => $totalValue, 'credit' => 0, 'description' => 'تكلفة الصنف التالف', 'currency' => $this->defaultCurrency()]);
            \App\Models\JournalEntryDetail::create(['journal_entry_id' => $outEntry->id, 'account_id' => $outAccount->id, 'debit' => 0, 'credit' => $totalValue, 'description' => 'مخزون الصادر', 'currency' => $this->defaultCurrency()]);

            $inEntry = \App\Models\JournalEntry::create([
                'date' => $exchange->exchange_date, 'description' => 'تبديل بضاعة (وارد) — '.$exchange->code,
                'status' => true, 'currency' => $this->defaultCurrency(), 'client_id' => $exchange->client_id,
                'employee_id' => $exchange->employee_id,
                'reference_type' => ReferenceType::PRODUCT_EXCHANGE->value, 'reference_number' => $exchange->code.'-IN',
                'branch_id' => $exchange->branch_id, 'is_posted' => true, 'linking_type' => \App\Enums\LinkingType::SINGLE->value,
            ]);
            \App\Models\JournalEntryDetail::create(['journal_entry_id' => $inEntry->id, 'account_id' => $inAccount->id, 'debit' => $totalValue, 'credit' => 0, 'description' => 'مخزون الوارد (تالف)', 'currency' => $this->defaultCurrency()]);
            \App\Models\JournalEntryDetail::create(['journal_entry_id' => $inEntry->id, 'account_id' => $this->requireAccountingAccount('cost_of_sales')->id, 'debit' => 0, 'credit' => $totalValue, 'description' => 'إلغاء تكلفة الصنف الوارد', 'currency' => $this->defaultCurrency()]);
        }
    }

    private function warehouseAccount(int $storeHouseId): Account
    {
        $storeHouse = StoreHouse::find($storeHouseId);

        return ($storeHouse?->account_id ? Account::find($storeHouse->account_id) : null)
            ?? Account::where('storehouse_id', $storeHouseId)->first()
            ?? $this->requireAccountingAccount('inventory');
    }

    private function exchangePayload(ProductExchange $exchange): array
    {
        return [
            'id' => $exchange->id,
            'code' => $exchange->code,
            'client' => $exchange->client ? ['id' => $exchange->client->id, 'trade_name' => $exchange->client->trade_name] : null,
            'exchange_date' => optional($exchange->exchange_date)->toDateString(),
            'total_value' => (float) $exchange->total_value,
            'image_url' => $exchange->image ? asset('uploads/product_exchanges/'.$exchange->image) : null,
            'notes' => $exchange->notes,
            'items' => $exchange->items->map(fn ($i) => [
                'product_id' => $i->product_id, 'product_name' => $i->product->name ?? null,
                'quantity' => (int) $i->quantity, 'unit_price' => (float) $i->unit_price, 'total' => (float) $i->total,
                'reason' => $i->exchangeReason->name ?? null, 'reason_notes' => $i->reason_notes,
            ]),
            'print_url' => route('api.integration.exchanges.print', $exchange->id),
            'created_at' => $exchange->created_at?->toDateTimeString(),
        ];
    }
}
