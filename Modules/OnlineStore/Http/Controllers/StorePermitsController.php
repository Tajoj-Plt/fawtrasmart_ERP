<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Constants\SystemAccountIds;
use App\Enums\LinkingType;
use App\Enums\ProductType;
use App\Enums\ReferenceType;
use App\Exports\StorePermitsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\WarehousePermitsRequest;
use App\Models\Account;
use App\Models\DocumentTemplate;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\PermissionSource;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Services\OnlineStoreSettingsService;
use App\Support\AccountingPeriodGuard;
use App\Support\CurrencySettings;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\OnlineStore\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\OnlineStore\Http\Controllers\Concerns\ScopesStoreWarehouses;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use TCPDF;

class StorePermitsController extends Controller
{
    use GeneratesDocumentNumbers;

    // حسابات القيود تُقرأ من «الحسابات المحاسبية» لا من ثوابت مكتوبة —
    // نفس السمة التي توحّد الحلّ في Stock\StorePermitsManagementController
    use ResolvesAccountingAccounts;

    // أيُّ مخازن المتجر وأذونها يراها المستخدم: نطاقُه ∩ فرعُ الشريط —
    // مصدرٌ واحد للقائمة والفلاتر والمنسدلات وحراسة الروابط المباشرة
    use ScopesStoreWarehouses;

    // نطاق الفرع/التسلسل الإداري نفسه المستخدم في Stock\StorePermitsManagementController
    // — الإذن المخزني هو نفس الميزة، فيجب أن يخضع لنفس ضابط الرؤية
    use ScopesToEmployeeHierarchy;

    /** صلاحيةُ إنشاء كل نوع — المسارُ المشترك `store` يقبل الأربعة، فيُحسم النوعُ هنا */
    private const CREATE_PERMISSIONS = [
        1 => 'stock.permits.create_addition',
        2 => 'stock.permits.create_disbursement',
        3 => 'stock.permits.create_transfer',
        4 => 'stock.permits.create_opening',
    ];

    /**
     * صلاحيات الأذونات المخزنية — كتالوج `stock.permits.*` نفسه الذي
     * تستعمله `Stock\StorePermitsManagementController`، لا الاسم القديم
     * `inv_manage_inventory_permission_*` الذي لم يبق له استعمالٌ مستقل.
     * كل الأدوار المالكة للاسم القديم تملك مقابله الجديد بالفعل.
     *
     * الاعتماد والرفض وإلغاء الاعتماد لكلٍّ صلاحيتُه: الإذن لا يمسّ المخزون
     * ولا الدفاتر حتى يُعتمد، فمن يكتبه ليس بالضرورة من يقرّه.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.permits.view', ['only' => ['index', 'show', 'productsByStore', 'getProductStock']]);
        $this->middleware('permission.role:stock.permits.create_addition|stock.permits.create_disbursement|stock.permits.create_transfer|stock.permits.create_opening', ['only' => ['store']]);
        $this->middleware('permission.role:stock.permits.create_addition', ['only' => ['create']]);
        $this->middleware('permission.role:stock.permits.create_disbursement', ['only' => ['manual_disbursement']]);
        $this->middleware('permission.role:stock.permits.create_transfer', ['only' => ['manual_conversion']]);
        $this->middleware('permission.role:stock.permits.create_opening', ['only' => ['opening_balance']]);
        $this->middleware('permission.role:stock.permits.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.permits.delete', ['only' => ['delete']]);
        $this->middleware('permission.role:stock.permits.export', ['only' => ['export']]);
        $this->middleware('permission.role:stock.permits.print', ['only' => ['printView', 'pdf']]);
        $this->middleware('permission.role:stock.permits.approve', ['only' => ['approve']]);
        $this->middleware('permission.role:stock.permits.reject', ['only' => ['reject']]);
        $this->middleware('permission.role:stock.permits.cancel_approval', ['only' => ['cancelApproval']]);
    }

    /**
     * فحص ما إذا كانت جميع المنتجات نشطة
     */
    private function checkProductsStatus($productIds)
    {
        $inactiveProducts = Product::whereIn('id', $productIds)
            ->where('status', '!=', 'active')
            ->select('id', 'name', 'status')
            ->get();

        if ($inactiveProducts->isNotEmpty()) {
            $productNames = $inactiveProducts->pluck('name')->implode(', ');

            return [
                'valid' => false,
                'message' => "لا يمكن إجراء العملية على المنتجات التالية لأنها غير نشطة: {$productNames}",
            ];
        }

        return ['valid' => true];
    }

    /** نصٌّ من الطلب — أو فارغ إن وصل مصفوفةً أو ما لا يُقرأ نصّاً */
    private function textInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * استعلامُ قائمة الأذون بفلاتر الشاشة — للقائمة وللتصدير معاً.
     *
     * النطاق: مخزنُ الإذن (أو أحدُ طرفَي التحويل) مخزنُ متجرٍ يراه المستخدم
     * الآن — مُسنَداً إليه أو إلى من يتبعه، في فرع الشريط، ومن يرى الكل يرى
     * الجميع ({@see ScopesStoreWarehouses::storePermitsQuery()}). الإذنُ يتبع
     * مخزنَه لا منشئَه: من عُهد إليه مخزنٌ يرى ما كُتب عليه ولو كتبه غيرُه.
     */
    private function filteredPermitsQuery(Request $request)
    {
        $query = $this->storePermitsQuery();

        /*
            فلتر الفرع على **فرع المخزن** وما تحته. كان يُقرأ من
            `users.branch_id` لمنشئ الإذن — وهو فرعُ الشريط الذي يقف عليه
            المنشئُ *الآن* لا فرعُ الإذن، فيتبدّل ناتجُ الفلتر كلّما بدّل
            أحدُهم فرعَه.
        */
        $branchIds = $this->requestedStoreBranchIds($request->input('branch_id') ?? $request->input('branch'));
        if ($branchIds !== null) {
            $this->scopePermitsToStoreWarehouses($query, $this->storeWarehouseIdsInBranches($branchIds));
        }

        $keywords = $this->textInput($request, 'keywords');
        if ($keywords !== '') {
            $query->where(function ($q) use ($keywords) {
                $q->where('number', 'like', '%'.$keywords.'%')
                    ->orWhere('details', 'like', '%'.$keywords.'%');
            });
        }

        $type = $this->textInput($request, 'permission_type');
        if (in_array($type, ['1', '2', '3', '4'], true)) {
            $query->where('permission_type', (int) $type);
        }

        if (is_numeric($request->input('permission_source'))) {
            $query->where('permission_source_id', (int) $request->input('permission_source'));
        }

        if (is_numeric($request->input('id'))) {
            $query->where('id', (int) $request->input('id'));
        }

        // المخزن: أيُّ أعمدته الثلاثة — إذنُ التحويل يظهر عند طرفَيه
        if (is_numeric($request->input('store_house'))) {
            $storeId = (int) $request->input('store_house');
            $query->where(function ($q) use ($storeId) {
                $q->where('store_houses_id', $storeId)
                    ->orWhere('from_store_houses_id', $storeId)
                    ->orWhere('to_store_houses_id', $storeId);
            });
        }

        // الحالة — «مرفوض» تُخزَّن `rejec` (والقيمةُ القديمة `rejected` تبقى مقروءة)
        $status = $this->textInput($request, 'status');
        if (in_array($status, ['rejected', 'rejec'], true)) {
            $query->whereIn('status', ['rejec', 'rejected']);
        } elseif (in_array($status, ['pending', 'apporod'], true)) {
            $query->where('status', $status);
        }

        if (is_numeric($request->input('created_by'))) {
            $query->where('created_by', (int) $request->input('created_by'));
        }

        if (is_numeric($request->input('product'))) {
            $productId = (int) $request->input('product');
            $query->whereHas('products', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $key => $operator) {
            $date = $this->textInput($request, $key);
            if ($date !== '' && strtotime($date) !== false) {
                $query->whereDate('permission_date', $operator, date('Y-m-d', strtotime($date)));
            }
        }

        return $query;
    }

    public function index(Request $request)
    {
        $query = $this->filteredPermitsQuery($request)
            ->with(['user', 'storeHouse.branch', 'fromStoreHouse.branch', 'toStoreHouse.branch'])
            ->orderBy('id', 'DESC');

        $perPage = min(100, max(5, (int) $request->input('per_page', 10)));
        $wareHousePermits = $query->paginate($perPage)->withQueryString();

        // الإحصاءات على ما يراه المستخدم — كانت تعدّ أذون النظام كلِّه بلا نطاق
        $scoped = $this->storePermitsQuery();
        $approvedCount = (clone $scoped)->where('status', 'apporod')->count();
        $pendingCount = (clone $scoped)->where('status', 'pending')->count();
        $rejectedCount = (clone $scoped)->whereIn('status', ['rejec', 'rejected'])->count();

        // منسدلتا الفلتر: مخازنُ المتجر المرئيّة وفروعُ المستخدم وحدَها
        $storeHouses = $this->storeWarehousesQuery()->with('branch')->orderBy('name')->get();
        $branches = $this->storeBranchOptions();
        $permissionSources = PermissionSource::all();

        return view('onlinestore::store_permits.index', compact(
            'wareHousePermits',
            'permissionSources',
            'storeHouses',
            'branches',
            'approvedCount',
            'pendingCount',
            'rejectedCount'
        ));
    }

    /**
     * تصدير أذون المتجر — إذنٌ واحد (`?id=`) أو حسب فلاتر الشاشة نفسها،
     * مقصوراً بالضابط نفسه الذي يحصر القائمة — لا تصدير أذونٍ لا صلة لها
     * بالمتجر ولا بنطاق المستخدم.
     */
    public function export(Request $request)
    {
        $permits = $this->filteredPermitsQuery($request)->orderBy('id', 'DESC')->get();

        if ($request->filled('id') && $permits->isEmpty()) {
            abort(404);
        }

        return Excel::download(
            new StorePermitsExport($permits),
            'store_permits_export_'.now()->format('Y-m-d_H-i-s').'.xlsx'
        );
    }

    /**
     * بياناتُ نموذج الإذن المشتركة بين شاشات الإنشاء والتعديل.
     *
     * المخازن: مخازنُ المتجر **النشطة** داخل النطاق. الإذنُ ذو المخزن الواحد
     * يتبع فرعَ الشريط كالقائمة؛ والتحويلُ يعرض كلَّ ما يشمله نطاقُ المستخدم
     * في الطرفين — من يشمل نطاقُه فرعين يحوّل بينهما من أيّهما وقف.
     * ومخازنُ الإذن المحفوظ تبقى في القائمة عند التعديل ولو أُوقفت.
     */
    private function permitFormData(int $type, ?WarehousePermits $permit = null): array
    {
        $storeHouses = $this->storeWarehousesQuery($type !== 3)
            ->where('status', 'active')
            ->with('branch')
            ->orderBy('name')
            ->get();

        if ($permit) {
            $keep = array_filter([$permit->store_houses_id, $permit->from_store_houses_id, $permit->to_store_houses_id]);
            $missing = array_diff($keep, $storeHouses->pluck('id')->all());

            if ($missing) {
                $storeHouses = $storeHouses->concat(
                    $this->storeWarehousesQuery(false)->whereIn('id', $missing)->with('branch')->get()
                );
            }
        }

        // الأصناف المخزنية وحدها — الخدمات والرقميّات لا رصيد لها في مستودع
        $products = Product::whereIn('type', ProductType::stockedValues())->orderBy('name')->get();

        return compact('storeHouses', 'products');
    }

    public function create()
    {
        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber('warehouse-add');

        return view('onlinestore::store_permits.create', $this->permitFormData(1) + compact('serial_number'));
    }

    public function manual_disbursement()
    {
        $serial_number = $this->previewDocumentNumber('warehouse-dispose');

        return view('onlinestore::store_permits.manual_disbursement', $this->permitFormData(2) + compact('serial_number'));
    }

    public function manual_conversion()
    {
        $serial_number = $this->previewDocumentNumber('transfer-request');

        return view('onlinestore::store_permits.manual_conversion', $this->permitFormData(3) + compact('serial_number'));
    }

    public function opening_balance()
    {
        // الرصيد الافتتاحي إذنٌ وارد، فيشترك مع «إذن إضافة مخزون» في تسلسله
        $serial_number = $this->previewDocumentNumber('warehouse-add');

        return view('onlinestore::store_permits.opening_balance', $this->permitFormData(4) + compact('serial_number'));
    }

    public function show($id)
    {
        // خارج النطاق أو ليس إذنَ مخزنِ متجر ⇒ 404
        $permit = $this->findStorePermitOrFail($id, [
            'items.product', 'storeHouse.branch', 'fromStoreHouse.branch', 'toStoreHouse.branch', 'user',
        ]);

        $logs = ModelsLog::where('type', 'warehouse_permit_log')
            ->where('type_id', $permit->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($log) {
                return $log->created_at->format('Y-m-d');
            });

        $documentType = $this->permitDocumentType($permit);

        return view('onlinestore::store_permits.show', array_merge(compact('permit', 'logs'), [
            'documentTemplates' => PrintSettings::templatesFor($documentType),
            'defaultTemplate' => PrintSettings::defaultTemplateFor($documentType),
            'prefersPdf' => PrintSettings::prefersPdf(),
            'journalEntries' => $this->permitJournalEntries($permit, false)->load('details'),
        ]));
    }

    /**
     * نوع مستند الإذن في «قوالب المستندات» — إضافة/رصيد افتتاحي/تحويل
     * استلامٌ للمستودع الهدف، والصرف وحده مستندٌ مختلف.
     */
    private function permitDocumentType(WarehousePermits $permit): string
    {
        return (int) $permit->permission_type === 2 ? 'warehouse_permit_issue' : 'warehouse_permit_receipt';
    }

    /**
     * معاينة طباعة الإذن المخزني بقالبٍ مضبوط في «قوالب المستندات» —
     * وإلا القالب المدمج. نفس نمط InvoicesPurchaseController::printView().
     */
    public function printView($id, $template = 'default')
    {
        // خارج النطاق ⇒ 404
        $permit = $this->findStorePermitOrFail($id, ['items.product', 'storeHouse', 'fromStoreHouse', 'toStoreHouse']);

        OperationLog::record(
            'printed',
            'warehouse_permit_log',
            $permit,
            'تمت طباعة إذن المخزن رقم **'.$permit->number.'**'
        );

        $html = $this->renderConfiguredPermitTemplate($permit, $template);

        if ($html !== null) {
            return response(DocumentPrintPage::wrap($html));
        }

        return view('onlinestore::store_permits.print', compact('permit'));
    }

    /**
     * تنزيل الإذن المخزني PDF — نفس نمط InvoicesPurchaseController::generatePdf().
     */
    public function pdf($id, $template = null)
    {
        // خارج النطاق ⇒ 404 — قبل try كي لا يُبتلع 404 في رسالة خطأ
        $permit = $this->findStorePermitOrFail($id, ['items.product', 'storeHouse', 'fromStoreHouse', 'toStoreHouse']);

        try {
            $configuredHtml = $this->renderConfiguredPermitTemplate($permit, $template);

            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle('إذن مخزني - '.$permit->number);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(0);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->setRTL(true);
            $pdf->AddPage();
            $pdf->SetFont('aealarabiya', '', 12);

            $html = $configuredHtml ?? view('onlinestore::store_permits.print', compact('permit'))->render();
            $pdf->writeHTML($html, true, false, true, false, '');

            OperationLog::record(
                'exported',
                'warehouse_permit_log',
                $permit,
                'تم تصدير إذن المخزن رقم **'.$permit->number.'** PDF'
            );

            return $pdf->Output('إذن مخزني - '.$permit->number.'.pdf', 'D');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StorePermits', 'pdf', $id);

            return back()->with('error', 'حدث خطأ أثناء تصدير PDF: '.$e->getMessage());
        }
    }

    /** يصيّر قالباً مصمَّماً في الإعدادات — أو null فيتولّى القالب المدمج */
    private function renderConfiguredPermitTemplate(WarehousePermits $permit, $template): ?string
    {
        if (! is_numeric($template)) {
            return null;
        }

        $documentType = $this->permitDocumentType($permit);

        $model = DocumentTemplate::where('id', (int) $template)
            ->where('document_type', $documentType)
            ->first();

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)->getDocumentData($documentType, $permit->id);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'OnlineStore.StorePermits', 'render_template', $permit->id);

            return null;
        }
    }

    /**
     * يتحقّق من نوع الإذن وصلاحية إنشائه ومخازنه — ويُرجع المخازن محسومةً.
     *
     * المسار المشترك `store` يقبل أيًّا من صلاحيات الإنشاء الأربع، فمن يملك
     * «إضافة» وحدها كان يرسل `permission_type=2` فيُنشئ إذنَ صرف. والمخازنُ
     * كانت تُقبل كما وصلت: أيُّ معرّفٍ في `store_houses` يمرّ ولو كان مخزنَ
     * فرعٍ آخر أو مخزناً ليس للمتجر أصلاً.
     *
     * @return array{type:int, store:int, from:?int, to:?int}
     */
    private function resolvePermitContext(Request $request, bool $creating): array
    {
        $type = (int) $request->input('permission_type');

        if (! isset(self::CREATE_PERMISSIONS[$type])) {
            throw ValidationException::withMessages(['permission_type' => ['نوع الإذن غير صالح.']]);
        }

        if ($creating && ! hasPermission(self::CREATE_PERMISSIONS[$type])) {
            abort(403, 'لا تملك صلاحية إنشاء هذا النوع من الأذون المخزنية.');
        }

        if ($type === 3) {
            $from = (int) $request->input('from_store_houses_id');
            $to = (int) $request->input('to_store_houses_id');

            $this->assertStoreWarehouseInScope($from, 'from_store_houses_id', 'المستودع المصدر');
            $this->assertStoreWarehouseInScope($to, 'to_store_houses_id', 'المستودع الهدف');
            $this->assertWarehousesActive(['from_store_houses_id' => $from, 'to_store_houses_id' => $to]);

            return ['type' => $type, 'store' => $from, 'from' => $from, 'to' => $to];
        }

        $store = (int) ($request->input('store_houses_id') ?: $request->input('from_store_houses_id'));

        $this->assertStoreWarehouseInScope($store, 'store_houses_id');
        $this->assertWarehousesActive(['store_houses_id' => $store]);

        return ['type' => $type, 'store' => $store, 'from' => null, 'to' => null];
    }

    /** مخزنٌ موقوف لا تُكتب عليه أذونٌ جديدة */
    private function assertWarehousesActive(array $fields): void
    {
        $inactive = StoreHouse::whereIn('id', array_values($fields))->where('status', '!=', 'active')->pluck('name', 'id');

        foreach ($fields as $field => $id) {
            if (isset($inactive[$id])) {
                throw ValidationException::withMessages([
                    $field => ['المستودع «'.$inactive[$id].'» غير نشط — لا تُسجَّل عليه أذون.'],
                ]);
            }
        }
    }

    /**
     * بنودُ الإذن من الطلب — محسوبةَ الإجمالي في الخادم.
     *
     * الإجماليُّ كان يُؤخذ من الطلب (`total[]`) ومنه يُبنى مبلغُ القيد، فيكفي
     * تبديلُه في المتصفّح ليُرحَّل قيدٌ لا يوافق كمية × سعر. ومصفوفاتٌ غيرُ
     * متساوية الطول كانت تنتهي بخطأ 500 (فهرسٌ غير معرَّف).
     *
     * @return array<int, array{product_id:int, quantity:float, unit_price:float, total:float}>
     */
    private function permitLines(Request $request): array
    {
        $productIds = array_values((array) $request->input('product_id', []));
        $quantities = array_values((array) $request->input('quantity', []));
        $prices = array_values((array) $request->input('unit_price', []));

        $lines = [];

        foreach ($productIds as $index => $productId) {
            $quantity = $quantities[$index] ?? null;
            $price = $prices[$index] ?? null;

            if (! is_numeric($productId) || ! is_numeric($quantity) || (float) $quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => ['الكمية مطلوبة لكل منتج وأكبر من صفر.']]);
            }

            if (! is_numeric($price) || (float) $price < 0) {
                throw ValidationException::withMessages(['unit_price' => ['سعر الوحدة مطلوب لكل منتج ولا يكون سالباً.']]);
            }

            $quantity = round((float) $quantity, 4);
            $price = round((float) $price, 4);

            $lines[] = [
                'product_id' => (int) $productId,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total' => round($quantity * $price, 2),
            ];
        }

        if (! $lines) {
            throw ValidationException::withMessages(['product_id' => ['أضف صنفاً واحداً على الأقل إلى الإذن.']]);
        }

        if (array_sum(array_column($lines, 'total')) < 0.01) {
            throw ValidationException::withMessages(['grand_total' => ['إجمالي الإذن يجب أن يكون أكبر من صفر.']]);
        }

        // الخدمات والرقميّات لا رصيد لها في مستودع — والمتغيّرات لا تحمل مخزوناً: الإذنُ على المنتج الأساسي
        $notStocked = Product::whereIn('id', array_column($lines, 'product_id'))
            ->whereNotIn('type', ProductType::stockedValues())
            ->pluck('name');

        if ($notStocked->isNotEmpty()) {
            throw ValidationException::withMessages([
                'product_id' => ['أصنافٌ لا تُخزَّن (خدمة أو منتج رقمي): '.$notStocked->implode('، ')],
            ]);
        }

        return $lines;
    }

    /** رصيدُ الصنف في مخزنٍ بعينه — مجموعُ صفوفه (المنتج الأساسي) */
    private function stockIn(int $storeId, int $productId, bool $lock = false): float
    {
        $query = ProductDetails::where('store_house_id', $storeId)->where('product_id', $productId);

        if ($lock) {
            // القفل على الصفوف ثم الجمعُ في PHP: `SUM ... FOR UPDATE` لا يقفل شيئاً يُعتمد عليه
            return (float) $query->lockForUpdate()->get(['id', 'quantity'])->sum('quantity');
        }

        return (float) $query->sum('quantity');
    }

    /**
     * أيكفي رصيدُ المخزن لبنود الصرف/التحويل؟ — مجموعةً لكل صنف.
     *
     * الفحص كان لكل بندٍ على حدة: صنفٌ رصيدُه 10 يمرّ في بندين كلٌّ منهما 8.
     *
     * @return string|null رسالةُ المنع أو `null`
     */
    private function stockShortage(int $storeId, iterable $lines, bool $lock = false): ?string
    {
        $needed = [];

        foreach ($lines as $line) {
            $productId = (int) (is_array($line) ? $line['product_id'] : $line->product_id);
            $needed[$productId] = ($needed[$productId] ?? 0) + (float) (is_array($line) ? $line['quantity'] : $line->quantity);
        }

        $storeName = StoreHouse::whereKey($storeId)->value('name') ?? '';

        foreach ($needed as $productId => $quantity) {
            $available = $this->stockIn($storeId, $productId, $lock);

            if ($available + 0.00001 < $quantity) {
                $name = Product::whereKey($productId)->value('name') ?? ('#'.$productId);

                return 'الكمية غير متوفرة للصنف «'.$name.'» في المستودع «'.$storeName.'»: المتاح '
                    .CurrencySettings::quantity($available).' والمطلوب '.CurrencySettings::quantity($quantity).'.';
            }
        }

        return null;
    }

    /** يكتب رأسَ الإذن وبنودَه — مشتركٌ بين الإنشاء والتعديل، بلا أثرٍ على المخزون */
    private function fillPermit(WarehousePermits $permit, Request $request, array $context, array $lines): void
    {
        if ($request->hasFile('attachments')) {
            $permit->attachments = $this->uploadImage('assetss/uploads/warehouse', $request->attachments);
        }

        $permit->permission_type = $context['type'];
        $permit->store_houses_id = $context['store'];
        $permit->from_store_houses_id = $context['from'];
        $permit->to_store_houses_id = $context['to'];
        $permit->permission_date = $request->permission_date;
        $permit->sub_account = is_scalar($request->sub_account) ? $request->sub_account : null;
        $permit->details = $request->details;
        $permit->grand_total = round(array_sum(array_column($lines, 'total')), 2);
        $permit->permission_source_id = match ($context['type']) {
            2 => PermissionSources::WAREHOUSE_DISBURSEMENT,
            3 => PermissionSources::MANUAL_TRANSFER,
            4 => PermissionSources::WAREHOUSE_OPENING_BALANCE,
            default => PermissionSources::WAREHOUSE_ADD,
        };
        $permit->save();

        WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)->delete();

        // الصرف والتحويل يُنقصان رصيد المخزن (المصدر)، والإضافة والافتتاحي يزيدانه
        $direction = in_array($context['type'], [2, 3], true) ? -1 : 1;
        $running = [];

        foreach ($lines as $line) {
            $before = $running[$line['product_id']] ?? $this->stockIn($context['store'], $line['product_id']);
            $after = $before + ($direction * $line['quantity']);
            $running[$line['product_id']] = $after;

            WarehousePermitsProducts::create($line + [
                'stock_before' => $before,
                'stock_after' => $after,
                'warehouse_permits_id' => $permit->id,
            ]);
        }
    }

    /**
     * إنشاء الإذن — بلا أثرٍ على المخزون ولا قيدٍ محاسبي.
     *
     * الإذن سطرٌ ينتظر الاعتماد، و`approve()` وحده ما يُحرِّك المخزون
     * ويُنشئ القيد — نفس ضابط Stock\StorePermitsManagementController::store().
     * الاستثناء الوحيد الرصيد الافتتاحي (نوع 4): يُعتمَد فور حفظه — هو إثباتٌ
     * لرصيدٍ قائم لا طلب حركةٍ ينتظر موافقة — **وفي المعاملة نفسها**: إن
     * تعذّر اعتمادُه لم يُحفظ منه شيء (كان يُحفظ معلَّقاً ثم يُبلَّغ بالخطأ).
     */
    public function store(WarehousePermitsRequest $request)
    {
        $context = $this->resolvePermitContext($request, true);
        $lines = $this->permitLines($request);

        // فحص حالة المنتجات أولاً
        $statusCheck = $this->checkProductsStatus(array_column($lines, 'product_id'));

        if (! $statusCheck['valid']) {
            return redirect()->back()->withInput()->with('error', $statusCheck['message']);
        }

        // الفترة المحاسبية — الاعتماد هو ما يُرحِّل القيد، فالتاريخ يُفحص هناك أيضاً؛
        // هذا الفحص المبكر يمنع حفظ إذنٍ بتاريخ فترة مقفلة أصلاً
        if ($blocked = AccountingPeriodGuard::blockReason($request->permission_date)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        // إذن صرف/تحويل: تأكّدٌ مبكّر من توفّر الكمية في المخزن المصدر (الخصم الفعلي عند الاعتماد)
        if (in_array($context['type'], [2, 3], true) && ($shortage = $this->stockShortage($context['store'], $lines))) {
            return redirect()->back()->withInput()->with('error', $shortage);
        }

        DB::beginTransaction();

        try {
            $permit = new WarehousePermits;
            $permit->created_by = auth()->id();
            $permit->status = 'pending';

            /*
             * رقم الإذن حقلٌ تلقائي من إعدادات «الترقيم المتسلسل» وحدها،
             * لا مما يرسله النموذج — نفس ضابط Stock\StorePermitsManagementController.
             * قسم التسلسل يتبع نوع الإذن: وارد (إضافة/رصيد افتتاحي) أو منصرف أو تحويل.
             */
            $permit->number = $this->issueDocumentNumber(match ($context['type']) {
                2 => 'warehouse-dispose',
                3 => 'transfer-request',
                default => 'warehouse-add',
            });

            $this->fillPermit($permit, $request, $context, $lines);

            $typeLabel = [1 => 'إضافة', 2 => 'صرف', 3 => 'تحويل', 4 => 'رصيد افتتاحي'][$context['type']];

            OperationLog::record(
                'logged',
                'warehouse_permit_log',
                $permit,
                'أُنشئ إذن '.$typeLabel.' رقم **'.$permit->number.'** ('.count($lines).' بند) — بانتظار الاعتماد'
            );

            // الرصيد الافتتاحي يُعتمد فور حفظه — راجع تعليق الدالة أعلاه
            if ($context['type'] === 4) {
                $this->approvePermit($permit);
            }

            DB::commit();
        } catch (\DomainException $e) {
            // سببٌ عمليٌّ مفهوم (فترة مقفلة، حساب غير مربوط…) — يُعرض كما هو ولا يُحفظ شيء
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'تعذّر حفظ الإذن: '.$e->getMessage());
        } catch (\Exception $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — التسجيل يكتب عبر نفس
            // اتصال قاعدة البيانات، فيُلغى سجلّه هو نفسه مع المعاملة إن كُتب
            // قبل انتهائها (تحقّقتُ من هذا مباشرةً على قاعدة محلية)
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StorePermits', 'store');
            Log::error('Warehouse Permit Error: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'حدث خطأ أثناء حفظ الإذن: '.$e->getMessage());
        }

        return redirect()
            ->route('online_store.permits.index')
            ->with('success', $context['type'] === 4
                ? 'تم إنشاء الرصيد الافتتاحي واعتماده وإضافة الكميات إلى المستودع'
                : 'تم انشاء اذن المخزن بنجاح — بانتظار الاعتماد');
    }

    /** إذنٌ ولّدته فاتورة شراء أو مرتجعها — لا يُعدَّل ولا يُحذف ولا يُلغى اعتمادُه من هنا */
    private function linkedDocumentLabel(WarehousePermits $permit): ?string
    {
        return match ($permit->reference_type) {
            'purchase_invoice' => 'فاتورة شراء',
            'purchase_return' => 'مرتجع شراء',
            default => null,
        };
    }

    /** سببُ منع تعديل الإذن — أو `null` */
    private function editBlockReason(WarehousePermits $permit): ?string
    {
        // إذنٌ ولّدته فاتورة شراء أو مرتجعها تلقائياً — بنوده انعكاسٌ لبنود
        // ذلك المستند، لا مستقلّة عنه. التعديل الصحيح يمرّ عبر المستند نفسه
        if ($label = $this->linkedDocumentLabel($permit)) {
            return 'لا يمكن تعديل إذن مخزني مرتبط بـ'.$label.' رقم '.$permit->reference_id.' من هنا — عدِّل المستند نفسه بدلاً من ذلك.';
        }

        if ($permit->status === 'apporod') {
            return 'لا يمكن تعديل إذن معتمَد رقم '.$permit->number.' — البضاعة تحرّكت فعلاً في المخزون والقيود. ألغِ اعتماده أولاً.';
        }

        if ($permit->status !== 'pending') {
            return 'لا يمكن تعديل إذن مرفوض رقم '.$permit->number.'.';
        }

        return null;
    }

    public function edit($id)
    {
        $permit = $this->findStorePermitOrFail($id);

        // كانت الشاشة تُفتح لإذنٍ معتمَد أو مرفوض ثم يُرفض الحفظ بعد تعبئتها
        if ($reason = $this->editBlockReason($permit)) {
            return redirect()->route('online_store.permits.show', $permit->id)->with('error', $reason);
        }

        $type = (int) $permit->permission_type;

        $view = match ($type) {
            2 => 'onlinestore::store_permits.manual_disbursement_edit',
            3 => 'onlinestore::store_permits.manual_conversion_edit',
            4 => 'onlinestore::store_permits.opening_balance_edit',
            default => 'onlinestore::store_permits.edit',
        };

        return view($view, $this->permitFormData($type, $permit) + compact('permit'));
    }

    /**
     * تعديل الإذن — معلَّقٌ فقط، وبلا أثرٍ على المخزون.
     *
     * الإذن المعتمَد لا يُعدَّل من هنا: كميّاته منقولة فعلاً وقيده مُرحَّل،
     * وتعديله يفكّ الارتباط بين المستند وأثره. ونوعُ الإذن لا يتبدّل
     * بالتعديل: رقمُه صدر من تسلسل نوعه، وتبديلُه يترك رقمَ صرفٍ على إذن إضافة.
     */
    public function update(WarehousePermitsRequest $request, $id)
    {
        $permit = $this->findStorePermitOrFail($id);

        if ($reason = $this->editBlockReason($permit)) {
            return redirect()->route('online_store.permits.show', $permit->id)->with('error', $reason);
        }

        if ((int) $request->input('permission_type') !== (int) $permit->permission_type) {
            return redirect()->back()->withInput()->with('error', 'لا يُغيَّر نوع الإذن بعد إصداره — احذف الإذن وأنشئ النوع المطلوب.');
        }

        $context = $this->resolvePermitContext($request, false);
        $lines = $this->permitLines($request);

        $statusCheck = $this->checkProductsStatus(array_column($lines, 'product_id'));

        if (! $statusCheck['valid']) {
            return redirect()->back()->withInput()->with('error', $statusCheck['message']);
        }

        // الفترة المحاسبية — إذنٌ معلَّق قد يُعتمَد لاحقاً بتاريخه
        if ($blocked = AccountingPeriodGuard::blockReason($request->permission_date)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        // كان التعديل بلا فحص رصيدٍ إطلاقاً: إذنُ صرفٍ يُعدَّل إلى كميةٍ تفوق المتاح
        if (in_array($context['type'], [2, 3], true) && ($shortage = $this->stockShortage($context['store'], $lines))) {
            return redirect()->back()->withInput()->with('error', $shortage);
        }

        DB::beginTransaction();

        try {
            // الرقم كما صدر — لا يُعاد ترقيمه ولا يُقرأ من الطلب
            $permit->number = $this->keepDocumentNumber($permit->number, match ($context['type']) {
                2 => 'warehouse-dispose',
                3 => 'transfer-request',
                default => 'warehouse-add',
            });

            $this->fillPermit($permit, $request, $context, $lines);

            OperationLog::record('updated', 'warehouse_permit_log', $permit, 'تم تعديل إذن المخزن رقم **'.$permit->number.'**');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StorePermits', 'update', $id);
            Log::error('Warehouse Permit Update Error: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء تعديل الإذن: '.$e->getMessage());
        }

        return redirect()
            ->route('online_store.permits.index')
            ->with(['success' => 'تم تحديث إذن المخزن — يبقى بانتظار الاعتماد']);
    }

    public function delete($id)
    {
        $permit = $this->findStorePermitOrFail($id);

        $message = null;

        if ($label = $this->linkedDocumentLabel($permit)) {
            // إذنٌ ولّدته فاتورة شراء أو مرتجعها تلقائياً — حذفه يترك المستند
            // بلا إذنٍ يتبعه، فتُعتمَد فاتورةٌ لن تُستلَم بضاعتها أبداً
            $message = 'لا يمكن حذف إذن مخزني رقم '.$permit->number.' — مرتبط بـ'.$label.' رقم '.$permit->reference_id.'.';
        } elseif ($permit->status === 'apporod') {
            // إذن معتمَد: البضاعة تحرّكت فعلاً في المخزون وقيوده استقرّت —
            // حذفه يُبقي أثر ذلك الحركة/القيد بلا مستند يفسّره
            $message = 'لا يمكن حذف إذن مخزني معتمَد رقم '.$permit->number.' — البضاعة تحرّكت فعلاً في المخزون والقيود. ألغِ اعتماده أولاً.';
        } elseif ($this->permitJournalEntries($permit, false)->isNotEmpty()) {
            // إذنٌ اعتُمد ثم أُلغي اعتمادُه: قيدُه وعكسُه في الدفاتر يحملان رقمَه — يبقى أثراً مقروءاً
            $message = 'لا يمكن حذف إذن مخزني رقم '.$permit->number.' — عليه قيود محاسبية (اعتُمد ثم أُلغي اعتماده). ارفضه بدلاً من حذفه.';
        }

        if ($message) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        DB::transaction(function () use ($permit) {
            WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)->delete();
            $permit->delete();

            OperationLog::record(
                'deleted',
                'warehouse_permit_log',
                $permit->id,
                'تم حذف إذن المخزن رقم **'.($permit->number ?? $permit->id).'**'
            );
        });

        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'تم حذف إذن المخزن بنجاح']);
        }

        return redirect()
            ->route('online_store.permits.index')
            ->with(['success' => 'تم حذف إذن المخزن بنجاح']);
    }

    /**
     * رصيدُ صنفٍ في مخزن متجر — يغذّي «المتاح في المستودع» في نموذج الإذن.
     *
     * النموذج كان يسأل مسارَ المخزون العام (`/get-product-stock`) المحروس
     * بنطاق المخزون (عهدةُ الموظف)، فيُردّ عن مخازن المتجر أو لا يجدها.
     * والرصيدُ مجموعُ صفوف الصنف (المنتج الأساسي — المتغيّرات لا تحمل
     * مخزوناً)، لا أوّلُ صفٍّ منها.
     */
    public function getProductStock($storeId, $productId)
    {
        if (! $this->storeWarehouseInScope($storeId)) {
            return response()->json(['success' => false, 'message' => 'المستودع خارج نطاق صلاحياتك.'], 404);
        }

        if (! is_numeric($productId) || ! Product::whereKey((int) $productId)->exists()) {
            return response()->json(['success' => false, 'message' => 'الصنف غير موجود.'], 404);
        }

        return response()->json(['stock' => $this->stockIn((int) $storeId, (int) $productId)]);
    }

    /**
     * أصناف مستودعٍ بعينه — تغذّي قائمة بنود «إذن التحويل»: بعد اختيار
     * «من مستودع» لا تُعرض إلا الأصناف التي لها رصيدٌ فعليّ في ذلك
     * المستودع (صفوف `product_details` بمجموع كميةٍ موجب)، لا كل أصناف
     * النظام. مخزون المتغيرات خارج الحساب — الإذن يحرّك مخزون المنتج
     * الأساسي عبر المستودعات.
     */
    public function productsByStore($storeId)
    {
        // مخزنٌ خارج النطاق لا تُكشف أصنافُه ولا أرصدتُه
        if (! $this->storeWarehouseInScope($storeId)) {
            return response()->json(['products' => []], 404);
        }

        $productIds = ProductDetails::query()
            ->where('store_house_id', (int) $storeId)
            ->groupBy('product_id')
            ->havingRaw('SUM(quantity) > 0')
            ->pluck('product_id');

        $products = Product::whereIn('id', $productIds)
            ->whereIn('type', ProductType::stockedValues())
            ->orderBy('name')
            ->get();

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) ($p->averageCost() ?? $p->purchase_price ?? 0),
            ])->values(),
        ]);
    }

    public function uploadImage($folder, $image)
    {
        $fileExtension = $image->getClientOriginalExtension();
        $fileName = time().rand(1, 99).'.'.$fileExtension;
        $image->move($folder, $fileName);

        return $fileName;
    }

    /**
     * قيد استلام بضاعة فاتورة شراء عند اعتماد الإذن — مدين حساب المستودع،
     * دائن حساب المشتريات (نفس الحساب الذي دُيِّن عند إنشاء الفاتورة في
     * StorePurchaseInvoiceController::createAccountingEntriesFixed()، فتُقفَل
     * الدورة: مشتريات ← مستودع، لا مشتريات معلَّقة إلى الأبد).
     */
    private function createReceivingJournalEntry(WarehousePermits $wareHousePermit, StoreHouse $targetStoreHouse): void
    {
        $totalAmount = (float) $wareHousePermit->items->sum('total');

        if ($totalAmount <= 0) {
            return;
        }

        $storeAccount = $this->warehouseAccount($targetStoreHouse->id, $targetStoreHouse->name);

        $purchasesAccount = Account::find(OnlineStoreSettingsService::storeAccountId('purchases_account_id'))
            ?? $this->requireAccountingAccount('purchases', SystemAccountIds::PURCHASES);

        $description = 'استلام بضاعة فاتورة شراء — إذن مخزني رقم '.$wareHousePermit->number;

        $journalEntry = JournalEntry::create([
            // رقم القيد من تسلسل «قيد يومية» — راجع تعليق createTransferJournalEntry()
            'reference_number' => next_document_number('entry'),
            'date' => $wareHousePermit->permission_date ?? now(),
            'description' => $description,
            'status' => 1,
            'currency' => CurrencySettings::code(),
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
            'linking_type' => LinkingType::SINGLE,
            'reference_id' => $wareHousePermit->id,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $storeAccount->id,
            'description' => $description,
            'debit' => $totalAmount,
            'credit' => 0,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $purchasesAccount->id,
            'description' => $description,
            'debit' => 0,
            'credit' => $totalAmount,
        ]);

        // لا تحديث يدوي للرصيد — JournalEntryDetail::created() أعلاه يعيد
        // حسابه تلقائياً من القيود الفعلية؛ هذا النداء يكفل امتداد الأثر
        // إلى الآباء في شجرة الحسابات فقط
        Account::updateBalancesHierarchically($storeAccount->id);
        Account::updateBalancesHierarchically($purchasesAccount->id);
    }

    /**
     * عكس createReceivingJournalEntry() تماماً — إخراج بضاعة مرتجع شراء عند
     * اعتماد إذنها: مدين حساب المشتريات، دائن حساب المستودع.
     */
    private function createReturnStockJournalEntry(WarehousePermits $wareHousePermit, StoreHouse $targetStoreHouse): void
    {
        $totalAmount = (float) $wareHousePermit->items->sum('total');

        if ($totalAmount <= 0) {
            return;
        }

        $storeAccount = $this->warehouseAccount($targetStoreHouse->id, $targetStoreHouse->name);

        $purchasesAccount = Account::find(OnlineStoreSettingsService::storeAccountId('purchases_account_id'))
            ?? $this->requireAccountingAccount('purchases', SystemAccountIds::PURCHASES);

        $description = 'إخراج بضاعة مرتجع شراء — إذن مخزني رقم '.$wareHousePermit->number;

        $journalEntry = JournalEntry::create([
            // رقم القيد من تسلسل «قيد يومية» — راجع تعليق createTransferJournalEntry()
            'reference_number' => next_document_number('entry'),
            'date' => $wareHousePermit->permission_date ?? now(),
            'description' => $description,
            'status' => 1,
            'currency' => CurrencySettings::code(),
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
            'linking_type' => LinkingType::SINGLE,
            'reference_id' => $wareHousePermit->id,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $purchasesAccount->id,
            'description' => $description,
            'debit' => $totalAmount,
            'credit' => 0,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $storeAccount->id,
            'description' => $description,
            'debit' => 0,
            'credit' => $totalAmount,
        ]);

        // لا تحديث يدوي للرصيد — نفس ملاحظة createReceivingJournalEntry() أعلاه
        Account::updateBalancesHierarchically($purchasesAccount->id);
        Account::updateBalancesHierarchically($storeAccount->id);
    }

    /**
     * قيدُ الإذن اليدوي — **قيدٌ واحد متوازن لكل إذن**، يُرحَّل عند الاعتماد وحده.
     *
     *   • إضافة (1):        مدين حساب المخزن        / دائن «أطراف دائنة أخرى»
     *   • صرف (2):          مدين «أطراف مدينة أخرى» / دائن حساب المخزن
     *   • تحويل (3):        مدين مخزن الهدف         / دائن مخزن المصدر
     *   • رصيد افتتاحي (4): مدين حساب المخزن        / دائن «الأرصدة الافتتاحية»
     *
     * كان يُنشأ قيدٌ لكل **بند** — إذنٌ بعشرة أصناف يستهلك عشرةَ أرقام قيود
     * ويملأ دفتر اليومية بسطورٍ لمستندٍ واحد. والقيدُ يُختم بفرع المخزن لا
     * بفرع الشريط الذي يقف عليه المعتمِد، وبعلامة `WAREHOUSE_<رقم الإذن>`
     * (`batch_reference`) كي يُعثر عليه عند إلغاء الاعتماد بلا التباس.
     */
    private function postPermitEntry(WarehousePermits $permit, StoreHouse $target, ?StoreHouse $from): void
    {
        $amount = round((float) $permit->items->sum('total'), 2);

        if ($amount <= 0) {
            return;
        }

        $type = (int) $permit->permission_type;
        $targetAccount = $this->warehouseAccount($target->id, $target->name);
        $referenceType = ReferenceType::STOCK_ADJUSTMENT;

        switch ($type) {
            case 2:
                $debitId = $this->requireAccountingAccountId('other_debtor_parties', SystemAccountIds::OTHER_DEBITOR_PARTIES);
                $creditId = $targetAccount->id;
                $description = 'إذن صرف مخزني رقم '.$permit->number.' — من المستودع '.$target->name;
                break;
            case 3:
                $fromAccount = $this->warehouseAccount($from->id, $from->name);
                $debitId = $targetAccount->id;
                $creditId = $fromAccount->id;
                $description = 'إذن تحويل مخزني رقم '.$permit->number.' — من المستودع '.$from->name.' إلى '.$target->name;
                break;
            case 4:
                $debitId = $targetAccount->id;
                $creditId = $this->requireAccountingAccountId('opening_balance', SystemAccountIds::OPENING_BALANCE);
                $description = 'رصيد افتتاحي — إذن مخزني رقم '.$permit->number.' — المستودع '.$target->name;
                $referenceType = ReferenceType::OPENING_BALANCE;
                break;
            default:
                $debitId = $targetAccount->id;
                $creditId = $this->requireAccountingAccountId('other_creditor_parties', SystemAccountIds::OTHER_CREDITOR_PARTIES);
                $description = 'إذن إضافة مخزني رقم '.$permit->number.' — إلى المستودع '.$target->name;
        }

        $entry = JournalEntry::create([
            // رقم القيد يتبع تسلسل «قيد يومية» في إعدادات الترقيم المتسلسل
            'reference_number' => next_document_number('entry'),
            'date' => $permit->permission_date ?? now(),
            'description' => $description,
            'status' => 1,
            'currency' => $permit->currency ?: CurrencySettings::code(),
            'created_by_employee' => Auth::id(),
            'reference_type' => $referenceType,
            'linking_type' => LinkingType::SINGLE,
            'reference_id' => $permit->id,
            'batch_reference' => 'WAREHOUSE_'.$permit->id,
            'branch_id' => ($type === 3 ? $from->branch_id : $target->branch_id) ?: null,
        ]);

        foreach ([[$debitId, $amount, 0], [$creditId, 0, $amount]] as [$accountId, $debit, $credit]) {
            JournalEntryDetail::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountId,
                'description' => $description,
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }

        // لا تحديث يدوي للرصيد: JournalEntryDetail::created() يعيد حسابه من
        // القيود الفعلية — هذا النداء يمدّ الأثر إلى الآباء في الشجرة فقط
        Account::updateBalancesHierarchically($debitId);
        Account::updateBalancesHierarchically($creditId);
    }

    /**
     * قيودُ هذا الإذن في الدفاتر.
     *
     * @param  bool  $onlyOpen  القيودُ الأصلية التي لم تُعكس بعد وحدَها
     */
    private function permitJournalEntries(WarehousePermits $permit, bool $onlyOpen): Collection
    {
        $query = JournalEntry::query()
            ->where(function ($q) use ($permit) {
                $q->whereIn('batch_reference', ['WAREHOUSE_'.$permit->id, 'WAREHOUSE_REVERSAL_'.$permit->id])
                    ->orWhere(function ($legacy) use ($permit) {
                        /*
                            قيودٌ رُحِّلت قبل وسم `batch_reference` — تُعرف بمرجعها
                            ونوعها وتاريخها معاً. `reference_id` وحدَه لا يكفي:
                            رقمُ الإذن قد يوافق معرّفَ مستندٍ آخر يحمل النوعَ نفسه
                            (قيدٌ افتتاحيّ لعميلٍ مثلاً)، فيُعرض هنا — أو يُعكس —
                            قيدٌ لا صلة له بالإذن.
                        */
                        $legacy->where('reference_id', $permit->id)
                            ->whereNull('batch_reference')
                            ->where('reference_type', (int) $permit->permission_type === 4
                                ? ReferenceType::OPENING_BALANCE
                                : ReferenceType::STOCK_ADJUSTMENT)
                            ->where('created_at', '>=', $permit->created_at)
                            ->whereDate('date', date('Y-m-d', strtotime((string) ($permit->permission_date ?? $permit->created_at))));
                    });
            });

        if ($onlyOpen) {
            $query->where(function ($q) use ($permit) {
                $q->whereNull('batch_reference')->orWhere('batch_reference', '!=', 'WAREHOUSE_REVERSAL_'.$permit->id);
            })->where(function ($q) {
                $q->whereNull('is_reversed')->orWhere('is_reversed', false);
            })->where('reference_number', 'not like', 'REVERSE\_%');
        }

        return $query->orderBy('id')->get();
    }

    /** يزيد رصيد الصنف في المخزن (المنتج الأساسي) */
    private function increaseStock(int $storeId, int $productId, float $quantity): void
    {
        $row = ProductDetails::where('store_house_id', $storeId)->where('product_id', $productId)
            ->lockForUpdate()->orderBy('id')->first();

        if (! $row) {
            $row = new ProductDetails;
            $row->store_house_id = $storeId;
            $row->product_id = $productId;
            $row->quantity = 0;
        }

        $row->quantity += $quantity;
        $row->save();
    }

    /**
     * يُنقص رصيد الصنف في المخزن — ولا ينزل به تحت الصفر.
     *
     * @throws \DomainException إن لم يكفِ الرصيد
     */
    private function decreaseStock(StoreHouse $store, int $productId, float $quantity): void
    {
        $rows = ProductDetails::where('store_house_id', $store->id)->where('product_id', $productId)
            ->lockForUpdate()->orderByDesc('quantity')->get();

        $remaining = $quantity;

        foreach ($rows as $row) {
            if ($remaining <= 0.00001) {
                break;
            }

            $take = min((float) $row->quantity, $remaining);

            if ($take <= 0) {
                continue;
            }

            $row->quantity -= $take;
            $row->save();
            $remaining -= $take;
        }

        if ($remaining > 0.00001) {
            $name = Product::whereKey($productId)->value('name') ?? ('#'.$productId);

            throw new \DomainException('الكمية المطلوبة من «'.$name.'» أكبر من المتاح في المستودع «'.$store->name.'».');
        }
    }

    /**
     * اعتمادُ إذنٍ معلَّق: يحرّك المخزون ويرحّل القيد — مرّةً واحدة.
     *
     * يُستدعى داخل معاملةٍ يملكها المستدعي. كلُّ سببِ منعٍ عمليّ
     * `DomainException` برسالةٍ تُعرض كما هي، فيتراجع المستدعي عن المعاملة
     * ولا يبقى أثرٌ جزئيّ (كميةٌ تحرّكت بلا قيد، أو قيدٌ بلا كمية).
     *
     * @throws \DomainException
     */
    private function approvePermit(WarehousePermits $permit): void
    {
        if ($permit->status !== 'pending') {
            throw new \DomainException('هذا الإذن '.($permit->status === 'apporod' ? 'تمت الموافقة عليه مسبقاً' : 'تم رفضه مسبقاً'));
        }

        /*
            الفترة المحاسبية بتاريخ الإذن — القيدُ يُرحَّل به. كان الفحصُ عند
            الإنشاء وحدَه، فإذنٌ معلَّق من فترةٍ أُقفلت بعد إنشائه يُعتمد
            ويكتب قيداً في فترةٍ مقفلة.
        */
        if ($blocked = AccountingPeriodGuard::blockReason($permit->permission_date)) {
            throw new \DomainException($blocked);
        }

        $permit->load('items.product');

        if ($permit->items->isEmpty()) {
            throw new \DomainException('الإذن بلا بنود — لا شيء يُعتمد.');
        }

        $statusCheck = $this->checkProductsStatus($permit->items->pluck('product_id')->all());

        if (! $statusCheck['valid']) {
            throw new \DomainException($statusCheck['message']);
        }

        $type = (int) $permit->permission_type;

        /*
            المخزنُ المسجَّل على الإذن نفسُه — كان غيابُه يُعوَّض بـ«المخزن
            الرئيسي» (`major = 1`) بصمت، فيدخل رصيدُ فرعٍ إلى مخزنٍ آخر.
        */
        $target = StoreHouse::find($type === 3 ? $permit->to_store_houses_id : $permit->store_houses_id);
        $from = $type === 3 ? StoreHouse::find($permit->from_store_houses_id) : null;

        if (! $target || ($type === 3 && ! $from)) {
            throw new \DomainException('لم يتم العثور على المستودع المسجَّل على الإذن.');
        }

        // صرف/تحويل: لا يخرج من المخزن أكثرُ ممّا فيه — مجموعاً لكل صنف وتحت قفل
        if (in_array($type, [2, 3], true)) {
            $source = $type === 3 ? $from : $target;

            if ($shortage = $this->stockShortage($source->id, $permit->items, true)) {
                throw new \DomainException($shortage);
            }
        }

        foreach ($permit->items as $item) {
            if (! $item->product) {
                throw new \DomainException('أحد أصناف الإذن لم يعد موجوداً.');
            }

            $quantity = (float) $item->quantity;

            if ($type === 2) {
                $this->decreaseStock($target, (int) $item->product_id, $quantity);
            } elseif ($type === 3) {
                $this->decreaseStock($from, (int) $item->product_id, $quantity);
                $this->increaseStock($target->id, (int) $item->product_id, $quantity);
            } else {
                // النوع 4 «رصيد افتتاحي» يزيد المخزون مثل الإضافة تماماً
                $this->increaseStock($target->id, (int) $item->product_id, $quantity);
            }
        }

        try {
            /*
                إذن استلام فاتورة الشراء: القيد ينقل قيمة البضاعة من حساب
                المشتريات (طرف معلَّق) إلى حساب المستودع. ومرتجع الشراء عكسُه
                تماماً. وما عداهما إذنٌ يدويّ يُقيَّد بقيدٍ واحد لكل إذن.
            */
            if ($type === 1 && $permit->reference_type === 'purchase_invoice') {
                $this->createReceivingJournalEntry($permit, $target);
            } elseif ($type === 2 && $permit->reference_type === 'purchase_return') {
                $this->createReturnStockJournalEntry($permit, $target);
            } else {
                $this->postPermitEntry($permit, $target, $from);
            }
        } catch (\DomainException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \DomainException('فشل في إنشاء القيد المحاسبي: '.$e->getMessage(), 0, $e);
        }

        $permit->status = 'apporod';
        $permit->save();

        // اعتماد إذن فاتورة المشتريات = استلام بضاعتها: تُعلَّم الفاتورة «مستلمة»
        // (كما في أذون المخزون) — كانت تبقى «لم تُستلم» والبضاعة في المستودع
        if ($permit->reference_type === 'purchase_invoice' && $permit->reference_id) {
            DB::table('purchase_invoices')->where('id', $permit->reference_id)->where('receiving_status', '!=', 'received')
                ->update(['receiving_status' => 'received', 'is_received' => 1, 'received_date' => now()->toDateString(), 'updated_at' => now()]);
        }

        OperationLog::record(
            'approved',
            'warehouse_permit_log',
            $permit,
            'تمت الموافقة على إذن المخزن رقم **'.$permit->number.'**'
        );

        $this->notifyPermitOwner($permit, 'تمت الموافقة على الإذن المخزني رقم '.$permit->number, 'success');
    }

    /** إشعارُ منشئ الإذن — كان يُكتب على `user_id` غير الموجود في الإذن فيصل فارغاً */
    private function notifyPermitOwner(WarehousePermits $permit, string $title, string $type): void
    {
        if (! $permit->created_by || (int) $permit->created_by === (int) Auth::id()) {
            return;
        }

        notifications::create([
            'user_id' => Auth::id(),
            'receiver_id' => $permit->created_by,
            'title' => $title,
            'description' => $title,
            'type' => $type,
        ]);
    }

    /** ردٌّ موحَّد لإجراءات الاعتماد/الرفض/الإلغاء — JSON لطلبات AJAX وإلا رجوعٌ برسالة */
    private function actionResponse(bool $success, string $message, int $status = 200, array $extra = [])
    {
        if (request()->ajax() || request()->expectsJson()) {
            return response()->json(['success' => $success, 'message' => $message] + $extra, $success ? 200 : $status);
        }

        return redirect()->back()->with($success ? 'success' : 'error', $message);
    }

    public function approve($id)
    {
        DB::beginTransaction();

        try {
            // خارج النطاق ⇒ 404، وتحت قفلٍ كي لا يُعتمد الإذن مرّتين بنقرتين متزامنتين
            $permit = $this->findStorePermitOrFail($id, [], true);

            $this->approvePermit($permit);

            DB::commit();

            return $this->actionResponse(true, 'تمت الموافقة على الإذن المخزني رقم '.$permit->number.' بنجاح', 200, ['new_status' => 'apporod']);
        } catch (HttpExceptionInterface $e) {
            // 404 خارج النطاق — يُعاد كما هو بعد التراجع عن المعاملة
            DB::rollBack();

            throw $e;
        } catch (\DomainException $e) {
            DB::rollBack();

            return $this->actionResponse(false, $e->getMessage(), 422);
        } catch (\Exception $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في store() أعلاه
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StorePermits', 'approve', $id);
            Log::error('خطأ في الموافقة على الإذن المخزني: '.$e->getMessage());

            return $this->actionResponse(false, 'عذراً، حدث خطأ أثناء الموافقة على الإذن المخزني: '.$e->getMessage(), 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $permit = $this->findStorePermitOrFail($id);

        if ($permit->status === 'apporod') {
            return $this->actionResponse(false, 'لا يمكن رفض إذن تمت الموافقة عليه بالفعل — ألغِ اعتماده أولاً.', 422);
        }

        if ($permit->status !== 'pending') {
            return $this->actionResponse(false, 'هذا الإذن تم رفضه مسبقاً', 422);
        }

        // سببُ الرفض: النموذج يرسله `rejection_reason`، والمتحكّم كان يقرأ `reason` فيضيع
        $reason = $this->textInput($request, 'rejection_reason') ?: $this->textInput($request, 'reason');

        try {
            DB::transaction(function () use ($permit, $reason) {
                $permit->status = 'rejec';
                $permit->rejection_reason = $reason !== '' ? mb_substr($reason, 0, 1000) : null;
                $permit->rejected_by = Auth::id();
                $permit->rejected_at = now();
                $permit->save();

                OperationLog::record(
                    'rejected',
                    'warehouse_permit_log',
                    $permit,
                    'تم رفض إذن المخزن رقم **'.($permit->number ?? $permit->id).'**'.($reason !== '' ? ' — السبب: '.$reason : '')
                );

                $this->notifyPermitOwner($permit, 'تم رفض الإذن المخزني رقم '.$permit->number, 'error');
            });
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StorePermits', 'reject', $id);
            Log::error('Warehouse Permit Rejection Error: '.$e->getMessage());

            return $this->actionResponse(false, 'حدث خطأ أثناء رفض الإذن: '.$e->getMessage(), 500);
        }

        return $this->actionResponse(true, 'تم رفض الإذن المخزني رقم '.$permit->number.' بنجاح', 200, ['new_status' => 'rejec']);
    }

    /**
     * إلغاء اعتماد إذنٍ يدويّ معتمَد: يعيد الكميات ويعكس القيد، ويعود الإذن معلَّقاً.
     *
     * لم يكن للمتجر طريقٌ للتراجع عن إذنٍ اعتُمد خطأً: لا يُعدَّل ولا يُحذف
     * ولا يُرفض. نفس ضابط Stock\StorePermitsManagementController::cancelApproval():
     *
     *   • القيدُ الأصليّ يبقى ويُعلَّم معكوساً، وقيدٌ عكسيٌّ جديد بتاريخ اليوم
     *     برقمٍ من تسلسل «قيد يومية» — فلا يُمحى أثرٌ من الدفاتر.
     *   • الفترةُ تُفحص مرّتين: فترةُ الإذن (مستندٌ من فترةٍ مقفلة لا يُمسّ)
     *     وفترةُ اليوم (فيها يُكتب القيد العكسي).
     *   • لا يُسحب من المخزن ما صُرف منه بعد الاعتماد: إلغاءُ إضافةٍ بيعت
     *     بضاعتُها يُرفض بدل أن يُنزل الرصيدَ تحت الصفر.
     *   • إعادةُ الاعتماد بعد الإلغاء ترحّل قيداً جديداً — والقديمُ معكوسٌ،
     *     فلا ترحيلَ مزدوج.
     *
     * أذونُ فواتير الشراء ومرتجعاتها لا تُلغى من هنا: دورتُها يملكها المستند.
     */
    public function cancelApproval($id)
    {
        DB::beginTransaction();

        try {
            $permit = $this->findStorePermitOrFail($id, ['items.product'], true);

            if ($permit->status !== 'apporod') {
                throw new \DomainException('هذا الإذن غير معتمَد — لا اعتمادَ يُلغى.');
            }

            if ($label = $this->linkedDocumentLabel($permit)) {
                throw new \DomainException('لا يُلغى اعتماد إذنٍ مرتبط بـ'.$label.' رقم '.$permit->reference_id.' من هنا — عالِج المستند نفسه.');
            }

            if ($blocked = AccountingPeriodGuard::blockReason($permit->permission_date) ?: AccountingPeriodGuard::blockReason(now())) {
                throw new \DomainException($blocked);
            }

            $type = (int) $permit->permission_type;
            $target = StoreHouse::find($type === 3 ? $permit->to_store_houses_id : $permit->store_houses_id);
            $from = $type === 3 ? StoreHouse::find($permit->from_store_houses_id) : null;

            if (! $target || ($type === 3 && ! $from)) {
                throw new \DomainException('لم يتم العثور على المستودع المسجَّل على الإذن.');
            }

            // ما دخل بالاعتماد يجب أن يكون باقياً ليُسحب: إضافة/افتتاحي من مخزنها، وتحويلٌ من مخزن الهدف
            if ($type !== 2 && ($shortage = $this->stockShortage($target->id, $permit->items, true))) {
                throw new \DomainException('لا يمكن إلغاء الاعتماد — جزءٌ من بضاعة الإذن خرج من المخزن بعد اعتماده. '.$shortage);
            }

            foreach ($permit->items as $item) {
                $quantity = (float) $item->quantity;

                if ($type === 2) {
                    $this->increaseStock($target->id, (int) $item->product_id, $quantity);
                } elseif ($type === 3) {
                    $this->decreaseStock($target, (int) $item->product_id, $quantity);
                    $this->increaseStock($from->id, (int) $item->product_id, $quantity);
                } else {
                    $this->decreaseStock($target, (int) $item->product_id, $quantity);
                }
            }

            foreach ($this->permitJournalEntries($permit, true) as $entry) {
                $reverse = JournalEntry::create([
                    'reference_number' => next_document_number('entry'),
                    'date' => now(),
                    'description' => 'قيد عكسي (إلغاء اعتماد) — '.$entry->description,
                    'status' => 1,
                    'currency' => $entry->currency ?: CurrencySettings::code(),
                    'created_by_employee' => Auth::id(),
                    'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
                    'linking_type' => LinkingType::SINGLE,
                    'reference_id' => $permit->id,
                    'batch_reference' => 'WAREHOUSE_REVERSAL_'.$permit->id,
                    'branch_id' => $entry->branch_id,
                ]);

                foreach (JournalEntryDetail::where('journal_entry_id', $entry->id)->get() as $detail) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $reverse->id,
                        'account_id' => $detail->account_id,
                        'description' => 'عكس — '.$detail->description,
                        'debit' => $detail->credit,
                        'credit' => $detail->debit,
                    ]);

                    // الرصيدُ يعيد حسابَه JournalEntryDetail::created() — هنا امتدادُه للآباء فقط
                    Account::updateBalancesHierarchically($detail->account_id);
                }

                $entry->update(['is_reversed' => true]);
            }

            $permit->status = 'pending';
            $permit->save();

            OperationLog::record(
                'approval_cancelled',
                'warehouse_permit_log',
                $permit,
                'أُلغي اعتماد إذن المخزن رقم **'.$permit->number.'** — أُعيدت الكميات وعُكس القيد'
            );

            $this->notifyPermitOwner($permit, 'أُلغي اعتماد الإذن المخزني رقم '.$permit->number, 'warning');

            DB::commit();

            return $this->actionResponse(true, 'تم إلغاء اعتماد الإذن رقم '.$permit->number.' — أُعيدت الكميات وعُكس القيد، والإذن الآن بانتظار الاعتماد.', 200, ['new_status' => 'pending']);
        } catch (HttpExceptionInterface $e) {
            // 404 خارج النطاق — يُعاد كما هو بعد التراجع عن المعاملة
            DB::rollBack();

            throw $e;
        } catch (\DomainException $e) {
            DB::rollBack();

            return $this->actionResponse(false, $e->getMessage(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StorePermits', 'cancelApproval', $id);
            Log::error('Store permit cancel approval error: '.$e->getMessage());

            return $this->actionResponse(false, 'حدث خطأ أثناء إلغاء الاعتماد: '.$e->getMessage(), 500);
        }
    }
}
