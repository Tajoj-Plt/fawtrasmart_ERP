<?php

namespace App\Services;

use App\Helpers\NumberToWords;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\BusinessData;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CreditNotification;
use App\Models\Expense;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PaymentMethod;
use App\Models\PaymentsProcess;
use App\Models\PaymentVoucher;
use App\Models\Payroll;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationView;
use App\Models\Quote;
use App\Models\Receipt;
use App\Models\SalaryAdvanceRequest;
use App\Models\SalaryItem;
use App\Models\SalarySlip;
use App\Models\SalesTargetSettlement;
use App\Models\Store\OnlineOrder;
use App\Models\Supplier;
use App\Models\WarehousePermits;
use App\Services\Tax\DocumentTaxCalculator;
use App\Services\Zatca\ZatcaLabel;
use App\Services\Zatca\ZatcaQr;
use App\Support\CurrencySettings;
use App\Support\LanguageSettings;
use App\Support\PaymentTerms;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Purchases\Support\OrderLine;
use Modules\RentalManagement\Models\Contract as RealEstateContract;
use Modules\RentalManagement\Models\Payment as RealEstatePayment;
use Modules\RentalManagement\Models\TenantProfile;
use Modules\RentalManagement\Support\RealEstateVocabulary;
use Picqer\Barcode\BarcodeGeneratorPNG;

class DocumentTemplateDataService
{
    /**
     * Get real document data normalized for the template.
     */
    /**
     * بيانات المستند للمطبوعة.
     *
     * الباركود ورمز الاستجابة يُملآن هنا لا في كل مُطبِّع: سبعة
     * عشر موضعاً تكتب `'qr_code' => ''` و`'barcode' => ''`، وموضعٌ
     * واحد أحرى أن يُنسى.
     */
    /**
     * @param  int|null  $partyId  جهةٌ بعينها حين يحمل المستندُ عدّةَ جهات —
     *                             «طلب عرض أسعار» يُرسَل إلى مورّدين، ولكلٍّ
     *                             نسختُه المعنونةُ باسمه. تُترك فارغةً فيُسرَد
     *                             المورّدون كلُّهم في نسخةٍ واحدة.
     */
    public function getDocumentData(string $documentType, $documentId, $partyId = null)
    {
        $this->source = null;

        $payload = $this->resolveDocumentData($documentType, $documentId, $partyId);

        return $this->withCodes($this->withContext($payload, $this->source, $documentType));
    }

    /** نموذجُ المستند الذي قرأه `resolveDocumentData()` — لـ`withContext()` */
    private $source = null;

    /**
     * الموظفون الذين يحقّ للطابع رؤيةُ قسائمهم في «كشف مسير رواتب» —
     * `null` بلا قيد. يضبطه المتحكّم بنطاق المستخدم قبل الطلب.
     *
     * @var array<int, int>|null
     */
    private ?array $employeeScope = null;

    /** @param  array<int, int>|null  $employeeIds */
    public function withEmployeeScope(?array $employeeIds): static
    {
        $this->employeeScope = $employeeIds === null ? null : array_map('intval', $employeeIds);

        return $this;
    }

    /**
     * صرفٌ بعينه لقسيمة الراتب (`salary_slip`) بدل آخر صرفٍ لها.
     *
     * طباعةُ «سجل صرف الرواتب» (`payroll-runs.print`) كانت ملفَّ بلايد
     * ثابتاً — مصدرَ قوالب ثانياً لا يراه محرِّرُ «قوالب المستندات». صارت
     * تطبع قالبَ القسيمة نفسَه مثبَّتاً على الصرف المطبوع: فترتُه وسلفُه
     * المستقطعة وتاريخُ صرفه. يُستهلك مرّةً واحدة.
     */
    private ?int $payrollRunId = null;

    public function withPayrollRun(?int $runId): static
    {
        $this->payrollRunId = $runId;

        return $this;
    }

    /** العنوانُ الإنجليزيّ تحت العربيّ في «التصميم الموحّد» — بنوع البيانات */
    private const ENGLISH_TITLES = [
        'sales_invoice' => 'Sales Invoice',
        'tax_invoice' => 'Tax Invoice',
        'simplified_invoice' => 'Simplified Tax Invoice',
        'sales_return' => 'Sales Return',
        'online_order' => 'Online Order',
        'purchase_invoice' => 'Purchase Invoice',
        'purchase_order' => 'Purchase Order',
        'quotation_request' => 'Request for Quotation',
        'purchase_quotation' => 'Supplier Quotation',
        'purchase_order_request' => 'Purchase Request',
        'purchase_return' => 'Purchase Return',
        'debit_note' => 'Debit Note',
        'credit_note' => 'Credit Note',
        'receipt_voucher' => 'Receipt Voucher',
        'client_payment' => 'Receipt Voucher',
        'payment_voucher' => 'Payment Voucher',
        'supplier_payment' => 'Payment Voucher',
        'quote' => 'Price Quotation',
        'sales_order' => 'Sales Order',
        'installment_agreement' => 'Installment Agreement',
        'warehouse_permit_issue' => 'Stock Issue Permit',
        'warehouse_permit_receipt' => 'Stock Receipt Permit',
        'inventory_transfer' => 'Stock Transfer',
        'inventory_adjustment' => 'Stock Adjustment',
        'journal_entry' => 'Journal Entry',
        'contract' => 'Employment Contract',
        'salary_slip' => 'Salary Slip',
        'payroll_sheet' => 'Payroll Sheet',
        'salary_advance' => 'Salary Advance Voucher',
        'commission_payment' => 'Commission Payment Voucher',
        'supplier_statement' => 'Supplier Statement',
        'client_statement' => 'Customer Statement',
        'realestate_contract' => 'Lease Contract',
        'realestate_receipt' => 'Rent Payment Receipt',
    ];

    /**
     * وقتُ الإصدار والموظّفُ والفرعُ والعنوانُ الإنجليزيّ.
     *
     * يطلبها «التصميم الموحّد» في كلّ مستند، ولا يملؤها أيٌّ من المُطبِّعات:
     * فتُقرأ هنا مرّةً من النموذج نفسِه بدل تكرارها في عشرين مُطبِّعاً.
     * وما ملأه المُطبِّع بنفسه لا يُمَسّ. وكلُّ قراءةٍ محروسة — علاقةٌ غائبة
     * في نموذجٍ ما تُفرغ الحقلَ ولا تُسقط المطبوعة.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withContext(array $payload, $doc, string $documentType): array
    {
        $document = $payload['document'] ?? [];

        $document['title_en'] = $document['title_en'] ?? (self::ENGLISH_TITLES[$documentType] ?? '');

        if ($documentType === 'warehouse_permit_issue' || $documentType === 'warehouse_permit_receipt') {
            $document['title_en'] = (int) ($doc->permission_type ?? 0) === 2 ? 'Stock Issue Permit' : 'Stock Receipt Permit';
        }

        $document['time'] = $document['time'] ?? '';
        $document['employee'] = $document['employee'] ?? '';
        $document['branch'] = $document['branch'] ?? '';

        if ($doc instanceof Model) {
            if ($document['time'] === '' && $doc->created_at) {
                $document['time'] = Carbon::parse($doc->created_at)->format('H:i');
            }

            if ($document['employee'] === '') {
                $document['employee'] = $this->relatedName($doc, ['salesPerson', 'employee', 'createdByUser', 'creator', 'createdBy', 'user']);
            }

            if ($document['branch'] === '') {
                $document['branch'] = $this->relatedName($doc, ['branch']);
            }
        }

        $payload['document'] = $document;

        return $payload;
    }

    /** اسمُ أوّل علاقةٍ موجودةٍ ذاتِ قيمة — موظّفٌ (`full_name`) أو مستخدمٌ أو فرع */
    private function relatedName($doc, array $relations): string
    {
        foreach ($relations as $relation) {
            if (! method_exists($doc, $relation)) {
                continue;
            }

            try {
                $related = $doc->{$relation};
            } catch (\Throwable $e) {
                continue;
            }

            if (! $related instanceof Model) {
                continue;
            }

            $name = trim((string) ($related->full_name ?? $related->name ?? ''));

            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    private function resolveDocumentData(string $documentType, $documentId, $partyId = null)
    {
        $userId = Auth::id();
        $business = $this->getBusinessData($userId);

        switch ($documentType) {
            case 'sales_invoice':
            case 'tax_invoice':
            case 'simplified_invoice':
                $doc = Invoice::with(['client', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                /* العنوان بحسب المشتري: «ضريبية» لمنشأةٍ برقم ضريبي و«مبسّطة»
                   لغيرها. كان «فاتورة مبيعات» للأنواع الثلاثة، وهو ليس من
                   عناوين الهيئة. */
                return $this->normalizeInvoice($doc, $business, ZatcaLabel::invoiceTitle($doc->client));

            case 'sales_return':
                // مرتجع البيع نوعه `returned` في جدول `invoices`، لا `return` —
                // انظر `ReturnInvoiceController::create()`. القيد الخاطئ كان
                // يُسقط `findOrFail` دائماً، فتصميم القالب في «قوالب المبيعات»
                // لا يُصيَّر أبداً ويعود الطبعُ إلى القالب المدمج بصمت.
                $doc = Invoice::with(['client', 'items.product'])->where('type', 'returned')->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeInvoice($doc, $business, 'مرتجع مبيعات');

            case 'online_order':
                $doc = OnlineOrder::with(['client', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeOnlineOrder($doc, $business);

            case 'purchase_invoice':
                $doc = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizePurchaseInvoice($doc, $business);

            case 'purchase_order':
                $doc = PurchaseOrder::with(['creator', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizePurchaseOrder($doc, $business);

            case 'quotation_request':
                $doc = PurchaseQuotation::with(['creator', 'items.product', 'suppliers'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeQuotationRequest($doc, $business, $partyId);

            case 'purchase_quotation':
                $doc = PurchaseQuotationView::with(['supplier', 'creator', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizePurchaseQuotation($doc, $business);

            case 'purchase_order_request':
                $doc = PurchaseInvoice::with(['supplier', 'creator', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizePurchaseOrderRequest($doc, $business);

            case 'purchase_return':
                $doc = PurchaseInvoice::with(['supplier', 'creator', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeSharedPurchaseDocument($doc, $business, 'purchase_return');

            case 'debit_note':
                $doc = PurchaseInvoice::with(['supplier', 'creator', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeSharedPurchaseDocument($doc, $business, 'debit_note');

                // «الإشعار الدائن» نوعٌ معروضٌ في شاشة «تصاميم مستندات
                // المبيعات» ويُصمَّم له قالب ويُعيَّن افتراضياً — ولم يكن له
                // مدخلٌ هنا، فطباعتُه بقالبٍ مصمَّم ترمي وتعود إلى المدمج
            case 'credit_note':
                $doc = CreditNotification::with(['client', 'items.product', 'invoice'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeCreditNote($doc, $business);

            case 'receipt_voucher':
                $doc = Receipt::with('client')->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeReceiptVoucher($doc, $business);

                /* سندُ قبضٍ من شاشة «مدفوعات العملاء» — صفٌّ في
                   `payments_process` لا في `receipts`.
                 *
                 * ولمَ نوعٌ مستقلّ؟ لأن `SalesPaymentController` يطبع
                 * بقوالب `receipt_voucher` ويمرّر معرّف `PaymentsProcess`،
                 * فيبحث عنه هذا المُحوِّل في `receipts` — ويجده غالباً:
                 * ٨٢٧٨ معرّفاً مشتركاً بين الجدولين. فإمّا `findOrFail`
                 * يرمي فيعود الطبعُ إلى القالب المدمج (فالقالبُ المصمَّم لا
                 * يُطبع أبداً)، وإمّا **يجد سنداً آخر بالكامل** فيُطبع
                 * مستندُ عميلٍ مكان آخر. ولذلك لا يصحّ التخمين بالمحاولة
                 * على الجدولين: النوعُ يُصرَّح به من النداء.
                 *
                 * والقوالبُ نفسُها: `SalesPaymentController` يطلب قوالب
                 * `receipt_voucher` ويطلب بيانات `client_payment`. */
            case 'client_payment':
                $doc = PaymentsProcess::with(['client', 'invoice.client'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeClientPayment($doc, $business);

            case 'payment_voucher':
                $doc = PaymentVoucher::findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizePaymentVoucher($doc, $business);

                // دفعة المورّد سندُ صرف، لكنها في `payments_process` لا في
                // `payment_vouchers` — فلها مُطبِّعها الخاص
            case 'supplier_payment':
                $doc = PaymentsProcess::with(['supplier', 'purchase_invoice', 'treasury', 'employee'])
                    ->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeSupplierPayment($doc, $business);

            case 'quote':
                $doc = Quote::with(['client', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeQuote($doc, $business);

                /* أمر البيع — صفٌّ في `quotes` بنوعٍ آخر، انظر
                   `App\Models\Quote::TYPE_SALES_ORDER`. بنيتُه بنيةُ عرض
                   السعر، فالمُطبِّع واحد ويفترقان في العنوان المطبوع وحده. */
            case 'sales_order':
                $doc = Quote::with(['client', 'items.product'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeQuote($doc, $business, 'أمر بيع');

                // اتفاقية التقسيط: عقدٌ يُوقَّع ويُسلَّم للعميل، ولم يكن له
                // نوعٌ هنا ولا مطبوعةٌ في القسم أصلاً
            case 'installment_agreement':
                $doc = Installment::with(['client', 'invoice', 'details', 'paymentMethod'])
                    ->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeInstallmentAgreement($doc, $business);

            case 'warehouse_permit_issue':
            case 'warehouse_permit_receipt':
                $doc = WarehousePermits::with(['items.product', 'client'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeWarehousePermit($doc, $business);

            case 'contract':
                $doc = Contract::with(['employee.department', 'employee.branch', 'jobTitle', 'jobLevel', 'salaryItems', 'creator'])
                    ->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeContract($doc, $business);

            case 'salary_slip':
                $doc = SalarySlip::with(['employee.department', 'employee.job_title', 'employee.branch', 'salaryItems', 'payroll', 'contract'])
                    ->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeSalarySlip($doc, $business);

            case 'payroll_sheet':
                $doc = Payroll::with(['department', 'jobTitle', 'branch'])->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizePayrollSheet($doc, $business);

                // سندُ السلفة — صفٌّ في `salary_advance_requests`
            case 'salary_advance':
                $doc = SalaryAdvanceRequest::with(['employee.department', 'employee.job_title', 'treasury', 'approver', 'approvedBy', 'disbursedBy'])
                    ->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeSalaryAdvance($doc, $business);

                // سندُ صرف العمولة — تسويةٌ في `sales_target_settlements`
            case 'commission_payment':
                $doc = SalesTargetSettlement::with(['salesTarget', 'employee.department', 'employee.job_title', 'settler'])
                    ->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeCommissionPayment($doc, $business);

                // كشف الحساب: `$documentId` معرّف الطرف لا معرّف مستند — لا وجود
                // لجدول «كشوف حسابات»، فالكشف يُبنى عند الطلب من حركة حسابه
            case 'supplier_statement':
                return $this->normalizeAccountStatement(
                    Supplier::findOrFail($documentId), 'supplier', $business
                );

            case 'client_statement':
                return $this->normalizeAccountStatement(
                    Client::findOrFail($documentId), 'client', $business
                );

                /* عقدُ الإيجار العقاري وإيصالُ دفعته — `re_contracts` و`re_payments`.
                   غيرُ `contract` (عقد العمل) وغيرُ `receipt_voucher` (سند القبض
                   في `receipts`): جدولان آخران بمعرّفاتٍ تتقاطع، فالنوعُ يُصرَّح به. */
            case 'realestate_contract':
                $doc = RealEstateContract::with(['tenant', 'owner', 'unit.building', 'unit.floor', 'building', 'schedules', 'terms'])
                    ->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeRealEstateContract($doc, $business);

            case 'realestate_receipt':
                $doc = RealEstatePayment::with(['client', 'contract.tenant', 'contract.unit.building', 'contract.building', 'allocations.schedule', 'treasuryAccount'])
                    ->findOrFail($documentId);
                $this->source = $doc;

                return $this->normalizeRealEstatePayment($doc, $business);

                // Add other cases as needed
            default:
                return $this->getDemoData($documentType);
        }
    }

    /**
     * بيانات المنشأة المعروضة في ترويسة كل مستند.
     *
     * **ما تغيّر:** كانت تقرأ `business_data` لصاحب الطلب وحده، وأي حقل ناقص
     * يُستبدَل بنصّ تجريبي مكتوب في الشفرة («الشركة الافتراضية للخدمات
     * والتجارة»، الرقم الضريبي `300000000000003`…). وجدولُ `business_data`
     * فارغ في هذا التركيب، فكانت كل مطبوعة تخرج ببيانات وهمية.
     *
     * صارت تُجمَّع من مصادر الإعدادات الحقيقية بالترتيب:
     *
     *   1. «معلومات المنشأة» → `business_data` للمستخدم الحالي
     *   2. سجلّ المنشأة المرآة → `clients` لصاحب «معلومات الحساب» (تكتبه الشاشة نفسها)
     *   3. أي سجلّ منشأة موجود — التركيب أحادي المنشأة عملياً
     *   4. «معلومات الحساب» → `account_settings` للشعار والعملة والختم
     *
     * ولا يُلجأ إلى نصّ بديل إلا حين يكون الحقل فارغاً في كل ما سبق.
     */
    public function getBusinessData($userId = null)
    {
        $userId = $userId ?? Auth::id();

        $businessData = BusinessData::where('user_id', $userId)->first()
            ?? BusinessData::query()->first();

        /* «معلومات الحساب» يحفظها المسؤول تحت حسابه هو: صفٌّ في
           `account_settings` وصفُّ منشأةٍ مرآةٌ له في `clients`. وكان
           البحث عن الصفّين بمعرّف **من يطبع**، فلا يجدهما إلا المسؤول
           نفسه: يطبع المندوب فاتورته فتخرج باسم النظام بلا عنوانٍ ولا
           رقمٍ ضريبيّ — والبيانات مسجَّلةٌ كما ينبغي في الشاشة.

           فصاحبُ الإعدادات هو المرجع: صفُّ المستخدم الحالي إن وُجد، وإلّا
           أوّلُ صفّ `account_settings` في التركيب — وهو أحاديُّ المنشأة
           عملياً، كما يفترض السطر أعلاه في `business_data` وأسفلُه في
           الشعار والختم.

           والمرآةُ تُقرأ بمعرّف صاحب الإعدادات لا بمعرّف الطابع: صفوف
           `clients` فيها عملاءُ البوابة ولهم `user_id` أيضاً، فالبحث
           بمعرّف عميلٍ يسجّل دخوله يجعل ترويسةَ الفاتورة اسمَ العميل
           نفسِه. */
        $accountSetting = AccountSetting::where('user_id', $userId)->first()
            ?? AccountSetting::query()->orderBy('id')->first();

        $mirror = Client::where('user_id', $accountSetting->user_id ?? $userId)->first();

        /** أول قيمة غير فارغة من المصادر، وإلا السلسلة الفارغة */
        $pick = function (...$values) {
            foreach ($values as $value) {
                if (filled($value)) {
                    return $value;
                }
            }

            return '';
        };

        // الشعار والختم والتوقيع: سجلّ المنشأة أولاً، ثم إعدادات حساب المستخدم،
        // ثم أي حساب رفعها — التركيب أحادي المنشأة فلا تتضارب الهويات
        $anyAssets = AccountSetting::query()
            ->where(function ($q) {
                $q->whereNotNull('attachments')->orWhereNotNull('stamp')->orWhereNotNull('signature');
            })
            ->first();

        $logo = $pick($businessData?->logo, $accountSetting?->attachments, $anyAssets?->attachments);
        $stamp = $pick($businessData?->stamp, $accountSetting?->stamp, $anyAssets?->stamp);
        $signature = $pick($businessData?->signature, $accountSetting?->signature, $anyAssets?->signature);

        $address = implode(', ', array_filter([
            $pick($businessData?->street_address1, $mirror?->street1),
            $pick($businessData?->street_address2, $mirror?->street2),
            $pick($businessData?->city, $mirror?->city),
            $pick($businessData?->region, $mirror?->region),
            $pick($businessData?->country, $mirror?->country),
        ]));

        return [
            'name' => $pick(
                $businessData?->business_name,
                $mirror?->trade_name,
                trim(($mirror?->first_name ?? '').' '.($mirror?->last_name ?? '')),
                config('app.name')
            ),
            'logo' => $logo ? asset('storage/'.$logo) : asset('assets/images/logo/logo.png'),
            'phone' => $pick($businessData?->phone, $businessData?->mobile, $mirror?->phone, $mirror?->mobile),
            'email' => $pick($businessData?->business_email, $mirror?->email),
            'tax_number' => $pick($businessData?->tax_number, $mirror?->tax_number),
            'commercial_registration' => $pick($businessData?->commercial_registration, $mirror?->commercial_registration),
            'address' => $address,
            'website' => $pick($businessData?->website, $mirror?->website),
            'currency' => $pick($accountSetting?->currency, CurrencySettings::code()),
            'stamp' => $stamp ? asset('storage/'.$stamp) : '',
            'signature' => $signature ? asset('storage/'.$signature) : '',
        ];
    }

    /**
     * Normalize Invoice model.
     */
    private function normalizeInvoice($doc, $business, $title = 'فاتورة مبيعات')
    {
        /* رمز الهيئة لا نصٌّ مفصولٌ بـ`|`: كان ما لم يُخزَّن له رمز — كلّ
           مرتجع، وكلّ فاتورةٍ من الواجهة البرمجية — يُطبَع بنصٍّ لا يقرؤه
           تطبيق الهيئة. والمصدر الواحد `ZatcaQr`. */
        $qrCode = $this->generateQrCodeSvg(ZatcaQr::forInvoice($doc));

        $items = [];
        foreach ($doc->items as $item) {
            $line = $this->itemTaxAndTotal($item);

            $items[] = [
                'code' => $item->product->code ?? $item->product_id ?? '',
                'name' => $item->item ?? $item->product->name ?? 'بند مبيعات',
                'quantity' => $this->qty($item->quantity),
                'unit' => $item->unit_name ?? 'حبة',
                'price' => number_format($item->unit_price, 2, '.', ''),
                'discount' => number_format($item->discount ?? 0, 2, '.', ''),
                'tax' => $line['tax'],
                'total' => $line['total'],
            ];
        }

        return [
            'business' => $business,
            'customer' => [
                'name' => $doc->client->trade_name ?? ($doc->client->first_name.' '.$doc->client->last_name) ?? 'عميل افتراضي',
                'tax_number' => $doc->client->tax_number ?? 'غير متوفر',
                'address' => $doc->client->street1 ?? 'غير متوفر',
                'phone' => $doc->client->phone ?? $doc->client->mobile ?? 'غير متوفر',
                'code' => $doc->client->code ?? 'غير متوفر',
            ],
            'document' => [
                'title' => $title,
                'number' => str_pad($doc->code ?? $doc->id, 6, '0', STR_PAD_LEFT),
                'date' => $doc->invoice_date ? $doc->invoice_date->format('Y-m-d') : now()->format('Y-m-d'),
                /* تاريخُ الاستحقاق = تاريخُ الإصدار + مهلةُ السداد.

                   كان السطرُ يطبع `issue_date` نفسَه تحت عنوان «تاريخ
                   الاستحقاق» — أي تاريخَ الإصدار مرّتين — ويخترع
                   «اليوم + ٣٠» متى غاب. فالمطبوعةُ تحمل تاريخاً لا أصلَ
                   له، ويبني عليه العميلُ سدادَه.

                   وفارغاً حين لا مهلة: فاتورةُ نقدٍ لا استحقاقَ مؤجَّلٌ
                   لها، والقالبُ يُخفي السطرَ الفارغ. */
                'due_date' => PaymentTerms::of($doc)?->dueDate->format('Y-m-d') ?? '',
                'payment_method' => $this->paymentMethodName($doc->payment_method, 'نقدي'),
                'notes' => $doc->notes ?? '',
                'qr_code' => $qrCode,
                // يملؤه `withCodes()` محلّياً. كان وسمَ `<img>` نحو barcodeapi.org،
                // فلا يراه `withCodes()` فارغاً ويُطبع مسارُ صورةٍ مكسور
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format($doc->subtotal ?? ($doc->total - $doc->tax_total), 2, '.', ''),
                'discount' => number_format($doc->discount_amount ?? 0, 2, '.', ''),
                'shipping' => number_format($doc->shipping_cost ?? 0, 2, '.', ''),
                'tax' => number_format($doc->tax_total ?? 0, 2, '.', ''),
                'grand_total' => number_format($doc->grand_total, 2, '.', ''),
            ] + $this->invoiceSettlementTotals($doc),
        ];
    }

    /**
     * «المدفوع» و«المرتجع» و«المتبقي» لفاتورة المبيعات — بنفس حساب شاشتها
     * (`invoices/show.blade.php`) فلا تقول المطبوعةُ غيرَ ما تقوله الشاشة.
     *
     * - المدفوع: مجموع تحصيلات الفاتورة النقدية (`client payments` —
     *   `Invoice::payments()`). كان يُقرأ من `advance_payment` وحده، فالتحصيل
     *   اللاحق يُنقص «المستحق» ولا يظهر سطرُه. ثمّ صار يجمع كلَّ صفوف
     *   `payments_process` بلا نوع. بلا تحصيلٍ مسجَّل يبقى `advance_payment`
     *   (بيانات أقدم من جدول التحصيلات).
     * - المرتجع: `returned_payment` — يكتبه مرتجعُ المبيعات **والإشعارُ
     *   الدائن** الذي يسوّي الفاتورة (`ClientDebtSettlement`). كان مجموعَ
     *   فواتير المرتجع وحدها، فالفاتورةُ التي سوّاها إشعارٌ تُطبع بإجماليٍّ
     *   ومستحقٍّ بينهما فرقٌ لا سطرَ له. وبياناتٌ أقدم لم يُكتب فيها العمود
     *   تأخذ مجموعَ فواتير المرتجع.
     * - المتبقي: `due_value - المرتجع` كما في الشاشة.
     *
     * @return array{paid: string, returned: string, remaining: string}
     */
    private function invoiceSettlementTotals($doc): array
    {
        $paid = $doc instanceof Invoice ? (float) $doc->payments()->sum('amount') : 0.0;
        if ($paid <= 0) {
            $paid = (float) ($doc->advance_payment ?? 0);
        }

        $returned = (float) ($doc->returned_payment ?? 0);
        if ($returned <= 0) {
            $returned = (float) Invoice::where('type', 'returned')
                ->where('reference_number', $doc->id)
                ->sum('grand_total');
        }

        $remaining = max(0, (float) ($doc->due_value ?? 0) - $returned);

        return [
            'paid' => number_format($paid, 2, '.', ''),
            'returned' => number_format($returned, 2, '.', ''),
            'remaining' => number_format($remaining, 2, '.', ''),
        ];
    }

    /**
     * تطبيع طلب المتجر الإلكتروني — على قياس فاتورة المبيعات (`normalizeInvoice`)
     * ليعمل به أي قالب صُمِّم لها، لأن `OnlineOrder` ليست فاتورة بالمعنى
     * المحاسبي (تلك `Invoice` المرتبطة عبر `invoice_id`) بل مستند طلبٍ له
     * حالة شحن ومتابعة مستقلة عن الفاتورة.
     */
    private function normalizeOnlineOrder($doc, $business)
    {
        $qrCode = $this->generateQrCodeSvg(ZatcaQr::forOnlineOrder($doc));

        $items = [];
        foreach ($doc->items as $item) {
            $line = $this->itemTaxAndTotal($item);

            $items[] = [
                'code' => $item->product->code ?? $item->product_id ?? '',
                'name' => $item->product_name ?? $item->product->name ?? 'بند طلب',
                'quantity' => $this->qty($item->quantity),
                'unit' => 'حبة',
                'price' => number_format($item->unit_price, 2, '.', ''),
                'discount' => number_format($item->discount ?? 0, 2, '.', ''),
                'tax' => $line['tax'],
                'total' => $line['total'],
            ];
        }

        return [
            'business' => $business,
            'customer' => [
                'name' => $doc->client->trade_name
                    ?? trim(($doc->client->first_name ?? '').' '.($doc->client->last_name ?? ''))
                    ?: ($doc->shipping_name ?? 'عميل المتجر'),
                'tax_number' => $doc->client->tax_number ?? '',
                'address' => $doc->shipping_address ?? ($doc->client->street1 ?? ''),
                'phone' => $doc->shipping_phone ?? ($doc->client->phone ?? $doc->client->mobile ?? ''),
                'code' => $doc->client->code ?? '',
            ],
            'document' => [
                'title' => 'طلب متجر إلكتروني',
                'number' => $doc->order_number ?: str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                'date' => optional($doc->created_at)->format('Y-m-d') ?: now()->format('Y-m-d'),
                'due_date' => optional($doc->estimated_delivery_date)->format('Y-m-d') ?: '',
                'payment_method' => $doc->paymentMethod->name ?? ($doc->payment_method?->label() ?? ''),
                'notes' => $doc->customer_notes ?? '',
                'qr_code' => $qrCode,
                'barcode' => '', // يملؤه `withCodes()` — انظر `normalizeInvoice()`
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format($doc->subtotal ?? 0, 2, '.', ''),
                'discount' => number_format($doc->discount_amount ?? 0, 2, '.', ''),
                'shipping' => number_format($doc->shipping_cost ?? 0, 2, '.', ''),
                'shipping_tax' => number_format($doc->shipping_tax_amount ?? 0, 2, '.', ''),
                // ضريبة المنتجات + ضريبة الشحن، كي تجمع أرقام القالب إلى الإجمالي
                'tax' => number_format((float) ($doc->tax_amount ?? 0) + (float) ($doc->shipping_tax_amount ?? 0), 2, '.', ''),
                'grand_total' => number_format($doc->total_amount ?? 0, 2, '.', ''),
                'paid' => number_format($doc->payment_status?->value === 'paid' ? $doc->total_amount : 0, 2, '.', ''),
                'remaining' => number_format($doc->payment_status?->value === 'paid' ? 0 : $doc->total_amount, 2, '.', ''),
            ],
        ];
    }

    /**
     * الإشعار الدائن — مستندٌ يخفض ذمّة العميل.
     *
     * يُطبَّع على قياس فاتورة المبيعات ليعمل به أيّ قالبٍ صُمِّم لها:
     * نفس المفاتيح ونفس البنية. ما ينفرد به بندان:
     *
     *   • *المرجع*: رقم الفاتورة التي صدر عنها، فالإشعار بلا أصلٍ
     *     مستندٌ معلّق.
     *   • *الإشارة*: مبالغه تُعرَض موجبةً كما تُحفَظ؛ الخفض يفهمه
     *     القارئ من عنوان المستند لا من إشارةٍ سالبة تربكه في الطباعة.
     */
    private function normalizeCreditNote($doc, $business)
    {
        $qrCode = $this->generateQrCodeSvg(ZatcaQr::forCreditNote($doc));

        $items = [];

        foreach ($doc->items as $item) {
            $line = $this->itemTaxAndTotal($item);

            $items[] = [
                'code' => $item->product->code ?? $item->product_id ?? '',
                'name' => $item->item ?? $item->product->name ?? 'بند إشعار دائن',
                'quantity' => $this->qty($item->quantity),
                'unit' => $item->unit_name ?? 'حبة',
                'price' => number_format($item->unit_price, 2, '.', ''),
                'discount' => number_format($item->discount ?? 0, 2, '.', ''),
                'tax' => $line['tax'],
                'total' => $line['total'],
            ];
        }

        return [
            'business' => $business,
            'customer' => [
                'name' => $doc->client->trade_name
                    ?? trim(($doc->client->first_name ?? '').' '.($doc->client->last_name ?? '')),
                'tax_number' => $doc->client->tax_number ?? '',
                'address' => $doc->client->street1 ?? '',
                'phone' => $doc->client->phone ?? $doc->client->mobile ?? '',
                'code' => $doc->client->code ?? '',
            ],
            'document' => [
                'title' => 'إشعار دائن',
                'number' => $doc->credit_number ?: str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                'date' => optional($doc->credit_date)->format('Y-m-d') ?: $doc->created_at->format('Y-m-d'),
                'due_date' => optional($doc->release_date)->format('Y-m-d') ?: '',
                'reference' => $doc->invoice->code ?? '',
                'payment_method' => $doc->paymentMethod->name ?? '',
                'notes' => $doc->notes ?? '',
                'qr_code' => $qrCode,
                'barcode' => '', // يملؤه `withCodes()` — انظر `normalizeInvoice()`
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format($doc->subtotal ?? 0, 2, '.', ''),
                'discount' => number_format($doc->total_discount ?? 0, 2, '.', ''),
                'shipping' => number_format($doc->shipping_cost ?? 0, 2, '.', ''),
                'tax' => number_format($doc->total_tax ?? 0, 2, '.', ''),
                'grand_total' => number_format($doc->grand_total ?? 0, 2, '.', ''),
                'paid' => number_format(0, 2, '.', ''),
                'remaining' => number_format($doc->grand_total ?? 0, 2, '.', ''),
            ],
        ];
    }

    /**
     * جهة المستند في مطبوعات المشتريات: المورّد.
     *
     * **ما تغيّر:** كانت المُطبِّعات تقرأ `vat_number` و`address` و`code` —
     * وثلاثتها لا وجود لها في جدول `suppliers`. فكانت خانات الرقم الضريبي
     * والعنوان والرمز تخرج فارغةً (أو بنصّ «غير متوفر») في كل مطبوعة مهما
     * كانت بيانات المورّد مكتملة.
     *
     * الأعمدة الحقيقية: `tax_number` للرقم الضريبي، و`number_suply` للرمز،
     * والعنوان يُركَّب من الشارع والمدينة والمنطقة والدولة.
     */
    private function supplierParty($supplier): array
    {
        if (! $supplier) {
            return ['name' => '', 'tax_number' => '', 'address' => '', 'phone' => '', 'code' => ''];
        }

        $name = $supplier->trade_name
            ?: trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? ''));

        $address = collect([
            $supplier->street1 ?? null,
            $supplier->street2 ?? null,
            $supplier->city ?? null,
            $supplier->region ?? null,
            $supplier->country ?? null,
        ])->filter(fn ($part) => trim((string) $part) !== '')->implode(' — ');

        return [
            'name' => $name ?: '',
            'tax_number' => $supplier->tax_number ?? '',
            'address' => $address,
            'phone' => $supplier->phone ?: ($supplier->mobile ?? ''),
            'code' => $supplier->number_suply ?? '',
        ];
    }

    /**
     * جهةُ «طلب عرض الأسعار» المعروضة في المطبوعة.
     *
     * الطلبُ يُرسَل إلى عدّة مورّدين، وجهةُ القالب واحدةٌ مسطّحة
     * (`customer.*`) — و`{{#items}}` هو القسمُ المتكرّرُ الوحيد في المحرّك،
     * فلا سبيل إلى تكرار الجهة داخل الصفحة الواحدة. فالمخرجان:
     *
     *   • `$partyId` محدَّداً — نسخةُ ذلك المورّد وحدَه، معنونةً باسمه
     *     وبياناته كاملة. وهذا ما تطبعه الشاشة: نسخةٌ لكلّ مورّد.
     *   • بلا تحديد — المورّدون كلُّهم مسرودين في نسخةٍ واحدة، لمن
     *     يستدعي الخدمةَ بلا حلقة.
     *
     * ومعرّفٌ لا يقابله مورّدٌ في الطلب يردّ جهةً فارغة، فيتكفّل النداء
     * أعلاه بنسبتها إلى منشئ الطلب.
     */
    private function quotationRequestParty($doc, $partyId): array
    {
        if ($partyId !== null) {
            return $this->supplierParty($doc->suppliers->firstWhere('id', (int) $partyId));
        }

        return $doc->suppliers->count() > 1
            ? $this->mergeParties($doc->suppliers->map(fn ($supplier) => $this->supplierParty($supplier)))
            : $this->supplierParty($doc->suppliers->first());
    }

    /**
     * يدمج جهاتٍ متعدّدة في جهةٍ واحدة مسطّحة — لمستندٍ يُرسَل إلى أكثر من
     * مورّد بينما القالب لا يعرف إلّا جهةً واحدة.
     *
     * الترتيبُ محفوظٌ في كل خانة، والناقصُ يُنوَب عنه بشَرطة، فيُقرأ
     * المورّدُ الثالث في «الهاتف» على أنه الثالث في «الاسم»:
     *
     *     الاسم:  مورد أ، مورد ب، مورد ج
     *     الهاتف: 0501،   —،      0503
     *
     * وخانةٌ خاليةٌ عند الجميع تبقى خالية، فلا تُطبَع سلسلةُ شَرَطات.
     *
     * @param  Collection<int, array>  $parties
     */
    private function mergeParties($parties): array
    {
        $join = function (string $key) use ($parties) {
            $values = $parties->map(fn (array $party) => trim((string) ($party[$key] ?? '')));

            if ($values->every(fn (string $value) => $value === '')) {
                return '';
            }

            return $values->map(fn (string $value) => $value !== '' ? $value : '—')->implode('، ');
        };

        return [
            'name' => $join('name'),
            'tax_number' => $join('tax_number'),
            'address' => $join('address'),
            'phone' => $join('phone'),
            'code' => $join('code'),
        ];
    }

    /**
     * تطبيع فاتورة المشتريات لقوالب المستندات.
     *
     * **ما تغيّر:** كانت تقرأ أعمدةً لا وجود لها في الجداول —
     * `product_name` و`product_code` و`unit` في بند الفاتورة، و
     * `invoice_number` و`invoice_date` و`due_date` و`tax` و`discount`
     * و`total` و`paid_amount` و`due_amount` في الفاتورة نفسها. فكان كل
     * بند يخرج باسم ثابت مكتوب في الشفرة («بند مشتريات») ووحدة ثابتة
     * («حبة»)، والإجماليات أصفاراً، والتاريخ تاريخَ اليوم.
     *
     * الأعمدة الحقيقية: `item` (أو اسم المنتج) و`unit_name` في البند،
     * و`code` و`date` و`total_discount` و`total_tax` و`grand_total`
     * و`advance_payment` و`due_value` في الفاتورة. ومبلغُ الضريبة يُحسب
     * بـ`OrderLine` لأن `tax_1` نسبة مئوية لا مبلغاً.
     */
    private function normalizePurchaseInvoice($doc, $business)
    {
        $items = [];

        foreach ($doc->items as $item) {
            $line = $this->itemTaxAndTotal($item);

            $items[] = [
                'code' => $item->product->barcode ?? $item->product_id ?? '',
                'name' => $item->item ?: ($item->product->name ?? ''),
                'quantity' => $this->qty($item->quantity),
                'unit' => $item->unit_name ?? '',
                'price' => number_format((float) ($item->unit_price ?? 0), 2, '.', ''),
                'discount' => number_format((float) ($item->discount ?? 0), 2, '.', ''),
                'tax' => number_format(OrderLine::tax($item), 2, '.', ''),
                'total' => number_format((float) ($item->total ?: OrderLine::total($item)), 2, '.', ''),
            ];
        }

        // لا عمود لتاريخ الاستحقاق: مهلة السداد `terms` بالأيام من تاريخ الفاتورة
        $dueDate = ($doc->date && (int) $doc->terms > 0)
            ? Carbon::parse($doc->date)->addDays((int) $doc->terms)
            : null;

        return [
            'business' => $business,
            'customer' => $this->supplierParty($doc->supplier),
            'document' => [
                'title' => __('settings.doc_templates.types.purchase_invoice'),
                'number' => $doc->code ?: str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                'date' => LanguageSettings::formatDate($doc->date ?: $doc->created_at),
                'due_date' => LanguageSettings::formatDate($dueDate),
                'payment_method' => $this->paymentMethodName($doc->payment_method),
                'notes' => $doc->notes ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format((float) ($doc->subtotal ?? 0), 2, '.', ''),
                'discount' => number_format((float) ($doc->total_discount ?? 0), 2, '.', ''),
                'shipping' => number_format((float) ($doc->shipping_cost ?? 0), 2, '.', ''),
                'tax' => number_format((float) ($doc->total_tax ?? 0), 2, '.', ''),
                'grand_total' => number_format((float) ($doc->grand_total ?? 0), 2, '.', ''),
                'paid' => number_format((float) ($doc->advance_payment ?? 0), 2, '.', ''),
                'remaining' => number_format((float) ($doc->due_value ?? 0), 2, '.', ''),
            ],
        ];
    }

    /**
     * تطبيع طلب الشراء لقوالب المستندات.
     *
     * الطلب لا يحمل مورّداً ولا مبالغ محسوبة في جدوله، فالبنود تُجمَع هنا
     * والجهةُ المعروضة هي مُنشئ الطلب — وإلا خرجت خانة «العميل» فارغة في
     * كل قالب لا يُخفيها إعداده. حساب البند من `OrderLine` وحدها لأن
     * `tax_1` نسبة مئوية لا مبلغاً.
     */
    private function normalizePurchaseOrder($doc, $business)
    {
        $items = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($doc->items as $item) {
            $line = OrderLine::gross($item);
            $discount = (float) ($item->discount ?? 0);
            $tax = OrderLine::tax($item);

            $subtotal += $line;
            $discountTotal += $discount;
            $taxTotal += $tax;

            $items[] = [
                'code' => $item->product->code ?? $item->product_id ?? '',
                'name' => $item->item ?? $item->product->name ?? __('purchase_orders.items.item'),
                'quantity' => $this->qty($item->quantity),
                'unit' => $item->unit_name ?? '',
                'price' => number_format((float) ($item->unit_price ?? 0), 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'tax' => number_format($tax, 2, '.', ''),
                'total' => number_format(OrderLine::total($item), 2, '.', ''),
            ];
        }

        $grandTotal = $subtotal - $discountTotal + $taxTotal;

        return [
            'business' => $business,
            'customer' => [
                'name' => $doc->creator->name ?? __('purchase_orders.empty.unknown'),
                'tax_number' => $business['tax_number'] ?? '',
                'address' => $business['address'] ?? '',
                'phone' => $doc->creator->phone ?? '',
                'code' => $doc->creator->id ?? '',
            ],
            'document' => [
                'title' => __('purchase_orders.pdf.title'),
                'number' => $doc->code ?? str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                'date' => LanguageSettings::formatDate($doc->order_date ?: $doc->created_at),
                'due_date' => LanguageSettings::formatDate($doc->due_date),
                'payment_method' => '',
                'notes' => $doc->notes ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discount' => number_format($discountTotal, 2, '.', ''),
                'tax' => number_format($taxTotal, 2, '.', ''),
                'grand_total' => number_format($grandTotal, 2, '.', ''),
                'paid' => number_format(0, 2, '.', ''),
                'remaining' => number_format($grandTotal, 2, '.', ''),
            ],
        ];
    }

    /**
     * تطبيع «طلب عرض أسعار» — المستند الذي يُرسَل إلى المورّدين.
     *
     * لا يحمل أسعاراً: هو طلب تسعيرٍ لا تسعيرة، فالإجماليات أصفار، والجهة
     * المعروضة هي أول مورّد مرشَّح إن وُجد وإلا منشئ الطلب.
     */
    private function normalizeQuotationRequest($doc, $business, $partyId = null)
    {
        $items = [];

        foreach ($doc->items as $item) {
            $items[] = [
                'code' => $item->product->code ?? $item->product_id ?? '',
                'name' => $item->product->name ?? $item->item ?? '',
                'quantity' => $this->qty($item->quantity ?? 0),
                'unit' => $item->unit_name ?? '',
                'price' => number_format(0, 2, '.', ''),
                'discount' => number_format(0, 2, '.', ''),
                'tax' => number_format(0, 2, '.', ''),
                'total' => number_format(0, 2, '.', ''),
            ];
        }

        $party = $this->quotationRequestParty($doc, $partyId);

        // طلب التسعير قد يُطبَع قبل ترشيح أي مورّد — فيُنسب حينها إلى منشئه
        if ($party['name'] === '') {
            $party['name'] = $doc->creator->name ?? '';
        }

        return [
            'business' => $business,
            'customer' => $party,
            'document' => [
                'title' => __('settings.doc_templates.types.quotation_request'),
                'number' => $doc->code ?? str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                'date' => LanguageSettings::formatDate($doc->order_date ?: $doc->created_at),
                'due_date' => LanguageSettings::formatDate($doc->due_date),
                'payment_method' => '',
                'notes' => $doc->notes ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format(0, 2, '.', ''),
                'discount' => number_format(0, 2, '.', ''),
                'tax' => number_format(0, 2, '.', ''),
                'grand_total' => number_format(0, 2, '.', ''),
                'paid' => number_format(0, 2, '.', ''),
                'remaining' => number_format(0, 2, '.', ''),
            ],
        ];
    }

    /**
     * تطبيع «عرض سعر مشتريات» — التسعيرة الواردة من المورّد.
     *
     * الإجماليات محفوظة في الجدول نفسه (`subtotal`، `total_tax`،
     * `grand_total`) فتُقرأ منه ولا يُعاد حسابها، حتى لا يختلف المطبوع
     * عمّا اعتُمد فعلاً.
     */
    private function normalizePurchaseQuotation($doc, $business)
    {
        $items = [];

        foreach ($doc->items as $item) {
            $line = (float) ($item->unit_price ?? 0) * (float) ($item->quantity ?? 0);
            $discount = (float) ($item->discount ?? 0);

            $items[] = [
                'code' => $item->product->code ?? $item->product_id ?? '',
                'name' => $item->item ?? $item->product->name ?? '',
                'quantity' => $this->qty($item->quantity ?? 0),
                'unit' => $item->unit_name ?? '',
                'price' => number_format((float) ($item->unit_price ?? 0), 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'tax' => number_format((float) ($item->tax_1 ?? 0), 2, '.', ''),
                'total' => number_format((float) ($item->total ?? ($line - $discount)), 2, '.', ''),
            ];
        }

        $validUntil = ($doc->date && $doc->valid_days)
            ? Carbon::parse($doc->date)->addDays((int) $doc->valid_days)
            : null;

        return [
            'business' => $business,
            'customer' => $this->supplierParty($doc->supplier),
            'document' => [
                'title' => __('settings.doc_templates.types.purchase_quotation'),
                'number' => $doc->code ?? $doc->purchase_price_number ?? str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                'date' => LanguageSettings::formatDate($doc->date ?: $doc->created_at),
                'due_date' => LanguageSettings::formatDate($validUntil),
                'payment_method' => '',
                'notes' => $doc->notes ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format((float) ($doc->subtotal ?? 0), 2, '.', ''),
                'discount' => number_format((float) ($doc->discount_amount ?? $doc->total_discount ?? 0), 2, '.', ''),
                'shipping' => number_format((float) ($doc->shipping_cost ?? 0), 2, '.', ''),
                'tax' => number_format((float) ($doc->total_tax ?? 0), 2, '.', ''),
                'grand_total' => number_format((float) ($doc->grand_total ?? 0), 2, '.', ''),
                'paid' => number_format(0, 2, '.', ''),
                'remaining' => number_format((float) ($doc->grand_total ?? 0), 2, '.', ''),
            ],
        ];
    }

    /**
     * تطبيع «أمر شراء» — صفّ في `purchase_invoices` نوعه `Requested`،
     * فيُعاد استعمال مُطبِّع فاتورة المشتريات بعنوان المستند الصحيح.
     */
    private function normalizePurchaseOrderRequest($doc, $business)
    {
        return $this->normalizeSharedPurchaseDocument($doc, $business, 'purchase_order_request');
    }

    /**
     * تطبيع مستندٍ يتشارك جدول `purchase_invoices` مع الفاتورة.
     *
     * أربعة أنواع تُحفظ في ذلك الجدول — الفاتورة والمرتجع وأمر الشراء
     * والإشعار المدين — وبنيتها واحدة: مورّد وبنود وإجماليات. فما يفرّقها
     * في المطبوعة هو عنوان المستند وحده، ويأتي من «قوالب المستندات».
     *
     * بلا هذا كان المرتجع والإشعار المدين يسقطان إلى `getDemoData()`:
     * مطبوعةٌ ببيانات وهمية لا صلة لها بالمستند.
     */
    private function normalizeSharedPurchaseDocument($doc, $business, string $documentType)
    {
        $data = $this->normalizePurchaseInvoice($doc, $business);
        $data['document']['title'] = __('settings.doc_templates.types.'.$documentType);

        return $data;
    }

    /**
     * Normalize Receipt Voucher model.
     */
    /**
     * تطبيع كشف حساب طرف (مورّد أو عميل) لقوالب المستندات.
     *
     * **لماذا هنا؟** لا جدول لكشوف الحسابات: الكشف يُبنى عند الطلب من
     * تفاصيل القيود على حساب الطرف. فالمُطبِّع يستعلم بنفسه بدل أن يستقبل
     * نموذجاً جاهزاً كبقيّة الأنواع.
     *
     * **الفترة** تُقرأ من الطلب (`from_date` / `to_date`) لأن الشاشة تصفّي
     * بها، والمطبوعة يجب أن تطابق ما يراه المستخدم على الشاشة لا أن تخرج
     * بكل تاريخ الحساب.
     *
     * **الرصيد الافتتاحي** هو صافي الحركة قبل `from_date`، لا `balance`
     * المخزَّن في الحساب — ذاك رصيدٌ لحظيّ لا يعرف الفترة.
     *
     * الاتجاه (مدين/دائن) يتبع `balance_type` للحساب: الموردون دائنون
     * والعملاء مدينون، فالإشارة تُقلب تبعاً له وإلا خرج الرصيد بإشارة عكسية.
     */
    /**
     * بيانُ الحركة مختصراً — ما لا يكرّره عمودٌ آخر في الكشف.
     *
     * وصفُ القيد المولَّد يجمع أربعة أجزاء بفاصل `|`، ثلاثةٌ منها مكرَّرة في
     * الجدول نفسه:
     *
     *     زيادة ذمم الموردين - فاتورة شراء رقم (PUR-00002)   ← عمود «المرجع»
     *     | المورد: موسسة الطيب الأفضل للتجارة               ← بطاقة الطرف
     *     | المبلغ: 10,015.00 ر.س (دائن - مستحق للمورد)      ← عمودا مدين/دائن
     *
     * فيخرج البيان في مئةٍ وثلاثين حرفاً، تسعون منها تكرار — وهو ما يخنق
     * العمود ويمطّ الصفّ عشرة أسطر مهما وُسِّع. يبقى هنا الجزء الأول وحده:
     * «زيادة ذمم الموردين»، ويُحذف رقمُ المستند منه متى كان هو المرجعَ
     * المعروض في عموده.
     */
    private function statementNarrative(?string $description, ?string $reference): string
    {
        $text = trim((string) $description);

        if ($text === '') {
            return '';
        }

        // الأجزاء المكرَّرة: الطرف والمبلغ
        $parts = array_filter(
            array_map('trim', explode('|', $text)),
            fn ($part) => $part !== ''
                && ! preg_match('/^(المورد|المورّد|العميل|المبلغ|Supplier|Client|Amount)\s*:/u', $part)
        );

        $text = implode(' — ', $parts);

        // «… رقم (PUR-00002)» متى كان الرقم هو المرجع المعروض في عموده
        if (filled($reference)) {
            $text = preg_replace(
                '/\s*رقم\s*\(?\s*'.preg_quote($reference, '/').'\s*\)?/u',
                '',
                $text
            );
        }

        // فواصل وشرطات معلَّقة بعد الحذف
        $text = preg_replace('/\s*[-–—]\s*$/u', '', trim($text));

        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    }

    private function normalizeAccountStatement($party, string $partyType, $business): array
    {
        $account = Account::where($partyType.'_id', $party->id)->first();

        $from = request('from_date') ?: null;
        $to = request('to_date') ?: null;

        $rows = collect();
        $opening = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $balanceType = $account->balance_type ?? ($partyType === 'supplier' ? 'credit' : 'debit');

        // إشارة الحركة على الحساب: المدين يزيد الحسابَ المدين وينقص الدائن
        $signed = fn (float $debit, float $credit) => $balanceType === 'debit'
            ? $debit - $credit
            : $credit - $debit;

        if ($account) {
            $base = JournalEntryDetail::where('account_id', $account->id)
                ->whereHas('journalEntry');

            // ما قبل الفترة يُجمَع رصيداً افتتاحياً لا يُعرَض سطراً سطراً
            if ($from) {
                $opening = (clone $base)
                    ->whereHas('journalEntry', fn ($q) => $q->whereDate('date', '<', Carbon::parse($from)))
                    ->get()
                    ->reduce(fn ($carry, $d) => $carry + $signed((float) $d->debit, (float) $d->credit), 0.0);
            }

            $query = (clone $base)->with('journalEntry:id,date,description,reference_number');

            if ($from) {
                $query->whereHas('journalEntry', fn ($q) => $q->whereDate('date', '>=', Carbon::parse($from)));
            }

            if ($to) {
                $query->whereHas('journalEntry', fn ($q) => $q->whereDate('date', '<=', Carbon::parse($to)));
            }

            $running = $opening;

            foreach ($query->get()->sortBy(fn ($d) => [$d->journalEntry->date ?? '', $d->id])->values() as $detail) {
                $debit = (float) ($detail->debit ?? 0);
                $credit = (float) ($detail->credit ?? 0);

                $running += $signed($debit, $credit);
                $totalDebit += $debit;
                $totalCredit += $credit;

                $reference = $detail->journalEntry->reference_number ?? '';
                $narrative = $this->statementNarrative(
                    $detail->description ?: ($detail->journalEntry->description ?? ''),
                    $reference
                );

                $rows->push([
                    'date' => LanguageSettings::formatDate($detail->journalEntry->date ?? null),
                    'reference' => $reference,
                    'description' => $narrative,
                    'debit' => number_format($debit, 2, '.', ''),
                    'credit' => number_format($credit, 2, '.', ''),
                    'balance' => number_format($running, 2, '.', ''),
                    // رموز الأصناف تبقى مملوءةً كذلك: قالبٌ حُرِّر يدوياً قد
                    // يستعمل `{{name}}` بدل `{{description}}`
                    'code' => $reference,
                    'name' => $narrative,
                    'quantity' => '',
                    'unit' => LanguageSettings::formatDate($detail->journalEntry->date ?? null),
                    'price' => number_format($debit, 2, '.', ''),
                    'discount' => number_format($credit, 2, '.', ''),
                    'tax' => number_format($credit, 2, '.', ''),
                    'total' => number_format($running, 2, '.', ''),
                ]);
            }
        }

        $closing = $opening + $signed($totalDebit, $totalCredit);

        $partyData = $partyType === 'supplier'
            ? $this->supplierParty($party)
            : [
                'name' => $party->trade_name ?: trim(($party->first_name ?? '').' '.($party->last_name ?? '')),
                'tax_number' => $party->tax_number ?? '',
                'address' => $party->address ?? '',
                'phone' => $party->phone ?? '',
                'code' => $party->code ?? (string) $party->id,
            ];

        return [
            'business' => $business,
            'customer' => $partyData,
            'document' => [
                'title' => $partyType === 'supplier' ? 'كشف حساب مورّد' : 'كشف حساب عميل',
                'number' => $account->code ?? $partyData['code'],
                'date' => LanguageSettings::formatDate(Carbon::now()),
                'due_date' => '',
                'payment_method' => '',
                'notes' => $account->name ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $rows->all(),
            'statement' => [
                'from' => $from ? LanguageSettings::formatDate(Carbon::parse($from)) : '—',
                'to' => $to ? LanguageSettings::formatDate(Carbon::parse($to)) : LanguageSettings::formatDate(Carbon::now()),
                'opening' => number_format($opening, 2, '.', ''),
                'debit' => number_format($totalDebit, 2, '.', ''),
                'credit' => number_format($totalCredit, 2, '.', ''),
                'closing' => number_format(abs($closing), 2, '.', ''),
                'direction' => $closing >= 0
                    ? ($balanceType === 'debit' ? 'مدين' : 'دائن')
                    : ($balanceType === 'debit' ? 'دائن' : 'مدين'),
                'count' => $rows->count(),
            ],
            // القوالب المشتركة تقرأ `totals`، فتُملأ بما يقابلها في الكشف
            'totals' => [
                'subtotal' => number_format($opening, 2, '.', ''),
                'discount' => number_format($totalCredit, 2, '.', ''),
                'tax' => number_format($totalDebit, 2, '.', ''),
                'grand_total' => number_format(abs($closing), 2, '.', ''),
                'paid' => number_format($totalCredit, 2, '.', ''),
                'remaining' => number_format(abs($closing), 2, '.', ''),
            ],
        ];
    }

    private function normalizeReceiptVoucher($doc, $business)
    {
        return [
            'business' => $business,
            'customer' => [
                // سندٌ بلا عميل (قبضٌ عامّ) كان يرمي `first_name on null` فيعود الطبعُ إلى المدمج
                'name' => $doc->client?->trade_name ?: (trim(($doc->client?->first_name ?? '').' '.($doc->client?->last_name ?? '')) ?: 'عميل نقدي'),
                'tax_number' => $doc->client?->tax_number ?? '',
                'phone' => $doc->client?->phone ?? '',
                'address' => $doc->client?->street1 ?? '',
                'code' => $doc->client?->code ?? '',
            ],
            'document' => [
                'title' => 'سند قبض',
                'number' => str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                'date' => $doc->created_at ? $doc->created_at->format('Y-m-d') : now()->format('Y-m-d'),
                'payment_method' => $this->paymentMethodName($doc->payment_method, 'نقدي'),
                'notes' => $doc->notes ?? 'إيصال استلام مبلغ مالي',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => [],
            'totals' => [
                'grand_total' => number_format($doc->amount, 2, '.', ''),
                'paid' => number_format($doc->amount, 2, '.', ''),
                'remaining' => '0.00',
            ],
        ];
    }

    /**
     * Normalize Payment Voucher model.
     */
    /**
     * تطبيع دفعة المورّد (`payments_process`) لقوالب المستندات.
     *
     * كانت الشاشة تطبع الإيصال من قالبٍ مكتوبٍ داخلها (`receipt/pdf_receipt`)
     * لا يعرفه محرّر القوالب ولا تسري عليه ألوان «مظهر النظام» ولا بيانات
     * المنشأة من إعداداتها.
     *
     * السند سطرٌ واحد: مبلغُ الدفعة وما دُفع عنه. فيُملأ `items` بصفٍّ واحد
     * حتى تعمل القوالب التي تعرض جدولاً، و`totals` بالمبلغ نفسه.
     *
     * **الأعمدة الحقيقية:** `Payment_method` بحرف كبير (وقراءتها بحرف صغير
     * تعيد `null` فتخرج الخانة فارغة)، و`payment_date` لا `date`.
     */
    private function normalizeSupplierPayment($doc, $business)
    {
        $invoice = $doc->purchase_invoice;
        $amount = (float) ($doc->amount ?? 0);

        $line = trim(($invoice?->code ? __('purchase_cycle.payment.invoice').' '.$invoice->code : '')
            .' '.($doc->notes ?? ''));

        return [
            'business' => $business,
            'customer' => $this->supplierParty($doc->supplier ?: $invoice?->supplier),
            'document' => [
                'title' => __('purchase_cycle.payment.document'),
                'number' => $doc->reference_number ?: str_pad((string) $doc->id, 6, '0', STR_PAD_LEFT),
                'date' => LanguageSettings::formatDate($doc->payment_date ?: $doc->created_at),
                'due_date' => '',
                // اسم العمود بحرف كبير في الجدول
                'payment_method' => $this->paymentMethodName($doc->Payment_method),
                'notes' => $doc->notes ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => [[
                'code' => $invoice?->code ?? '',
                'name' => $line !== '' ? $line : __('purchase_cycle.payment.document'),
                'quantity' => 1,
                'unit' => '',
                'price' => number_format($amount, 2, '.', ''),
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => number_format($amount, 2, '.', ''),
                // رموز كشف الحساب مملوءةٌ كذلك لمن حرّر قالباً يستعملها
                'date' => LanguageSettings::formatDate($doc->payment_date ?: $doc->created_at),
                'reference' => $doc->reference_number ?: '',
                'description' => $line,
                'debit' => number_format($amount, 2, '.', ''),
                'credit' => '0.00',
                'balance' => number_format((float) ($invoice->due_value ?? 0), 2, '.', ''),
            ]],
            'totals' => [
                'subtotal' => number_format($amount, 2, '.', ''),
                'discount' => '0.00',
                'tax' => '0.00',
                'grand_total' => number_format($amount, 2, '.', ''),
                'paid' => number_format($amount, 2, '.', ''),
                'remaining' => number_format((float) ($invoice->due_value ?? 0), 2, '.', ''),
            ],
        ];
    }

    /**
     * تطبيعُ دفعةِ عميل (`payments_process`) لقوالب سند القبض.
     *
     * نظيرةُ `normalizeSupplierPayment()` في الجهة المقابلة: سطرٌ واحد
     * بمبلغ الدفعة وما دُفع عنه، وإجماليٌّ بالمبلغ نفسه — فتعمل القوالبُ
     * التي تعرض جدولاً والتي تعرض مبلغاً وحده.
     */
    private function normalizeClientPayment($doc, $business)
    {
        $invoice = $doc->invoice;
        $client = $doc->client ?: $invoice?->client;
        $amount = (float) ($doc->amount ?? 0);
        $date = LanguageSettings::formatDate($doc->payment_date ?: $doc->created_at);

        $line = trim(($invoice?->code ? 'فاتورة '.$invoice->code : '').' '.($doc->notes ?? ''));

        return [
            'business' => $business,
            'customer' => [
                'name' => $client->trade_name ?? trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: 'عميل',
                'tax_number' => $client->tax_number ?? 'غير متوفر',
                'phone' => $client->phone ?? 'غير متوفر',
                'address' => $client->street1 ?? 'غير متوفر',
                'code' => $client->code ?? '',
            ],
            'document' => [
                'title' => 'سند قبض',
                'number' => $doc->reference_number ?: str_pad((string) $doc->id, 6, '0', STR_PAD_LEFT),
                'date' => $date,
                'due_date' => '',
                'payment_method' => $this->paymentMethodName($doc->payment_method, 'نقدي'),
                'notes' => $doc->notes ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => [[
                'code' => $invoice?->code ?? '',
                'name' => $line !== '' ? $line : 'سند قبض',
                'quantity' => 1,
                'unit' => '',
                'price' => number_format($amount, 2, '.', ''),
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => number_format($amount, 2, '.', ''),
                'date' => $date,
                'reference' => $doc->reference_number ?: '',
                'description' => $line,
                'debit' => '0.00',
                'credit' => number_format($amount, 2, '.', ''),
                'balance' => number_format((float) ($invoice->due_value ?? 0), 2, '.', ''),
            ]],
            'totals' => [
                'subtotal' => number_format($amount, 2, '.', ''),
                'discount' => '0.00',
                'tax' => '0.00',
                'grand_total' => number_format($amount, 2, '.', ''),
                'paid' => number_format($amount, 2, '.', ''),
                'remaining' => number_format((float) ($invoice->due_value ?? 0), 2, '.', ''),
            ],
        ];
    }

    private function normalizePaymentVoucher($doc, $business)
    {
        return [
            'business' => $business,
            'customer' => [
                'name' => $doc->beneficiary_name ?? 'المستفيد',
                'tax_number' => 'غير متوفر',
                'phone' => 'غير متوفر',
                'address' => 'غير متوفر',
                'code' => '',
            ],
            'document' => [
                'title' => 'سند صرف',
                'number' => str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                'date' => $doc->date ?? now()->format('Y-m-d'),
                'payment_method' => $doc->payment_method ?? 'نقدي',
                'notes' => $doc->notes ?? 'سند صرف مالي',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => [],
            'totals' => [
                'grand_total' => number_format($doc->amount, 2, '.', ''),
                'paid' => number_format($doc->amount, 2, '.', ''),
                'remaining' => '0.00',
            ],
        ];
    }

    /**
     * Normalize Quote model.
     */
    private function normalizeQuote($doc, $business, string $title = 'عرض سعر')
    {
        $items = [];
        foreach ($doc->items as $item) {
            /* كان `$line` يُقرأ أدناه بلا تعريف — فيخرج عمودا الضريبة والإجمالي
               فارغَين في كلّ مطبوعة عرض سعر وأمر بيع. البنودُ صفوفُ
               `invoice_items` كبنود الفاتورة، فتُحسب بالدالّة نفسِها. */
            $line = $this->itemTaxAndTotal($item);

            $items[] = [
                'code' => $item->product->code ?? $item->product_id ?? '',
                'name' => $item->item ?? $item->product->name ?? 'بند عرض سعر',
                'quantity' => $this->qty($item->quantity),
                'unit' => $item->unit_name ?? 'حبة',
                'price' => number_format($item->unit_price, 2, '.', ''),
                'discount' => number_format($item->discount ?? 0, 2, '.', ''),
                'tax' => $line['tax'],
                'total' => $line['total'],
            ];
        }

        return [
            'business' => $business,
            'customer' => [
                // عرضٌ بلا عميلٍ مرتبط كان يرمي `first_name on null` فيعود الطبعُ إلى المدمج
                'name' => $doc->client?->trade_name ?: (trim(($doc->client?->first_name ?? '').' '.($doc->client?->last_name ?? '')) ?: 'عميل نقدي'),
                'tax_number' => $doc->client?->tax_number ?? '',
                'phone' => $doc->client?->phone ?? '',
                'address' => $doc->client?->street1 ?? '',
                'code' => $doc->client?->code ?? '',
            ],
            'document' => [
                'title' => $title,
                /* الرقم المعروض هو `quote_number` — وهو ما يراه العميل على
                   المستند ويُرجَع إليه في المراسلة. `id` مصفوفاً إلى ستّ
                   خانات رقمٌ داخليّ لا يطابق شيئاً في الشاشات. */
                'number' => $doc->quote_number ?: str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                // `quote_date` مصبوبٌ `date` — نصُّه الخامُّ يُطبع بساعةٍ صفرية
                'date' => optional($doc->quote_date)->format('Y-m-d') ?? now()->format('Y-m-d'),
                /* أمر البيع أُزيل منه «تاريخ التسليم» — فلا يُطبع له تاريخٌ
                   مخترَع (اليوم + ١٥) لو صُمِّم له قالبٌ يقرأ هذا الحقل */
                'due_date' => $doc->type === Quote::TYPE_SALES_ORDER
                    ? ''
                    : ($doc->expiry_date ?? now()->addDays(15)->format('Y-m-d')),
                'payment_method' => 'غير محدد',
                'notes' => $doc->notes ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                // الأعمدة الحقيقية `total_discount` و`total_tax` — لا
                // `discount` و`tax` (غير موجودين في `quotes`)، وكانا
                // يخرجان صفراً دائماً فتُخفي القوالبُ سطريهما مهما كان
                // للعرض خصمٌ أو ضريبةٌ فعليّان
                'subtotal' => number_format($doc->subtotal ?? 0, 2, '.', ''),
                'discount' => number_format($doc->total_discount ?? 0, 2, '.', ''),
                'shipping' => number_format($doc->shipping_cost ?? 0, 2, '.', ''),
                'tax' => number_format($doc->total_tax ?? 0, 2, '.', ''),
                'grand_total' => number_format($doc->grand_total ?? 0, 2, '.', ''),
                'paid' => '0.00',
                'remaining' => number_format($doc->grand_total ?? 0, 2, '.', ''),
            ],
        ];
    }

    /**
     * اتفاقية التقسيط في الشكل الذي يتوقّعه `DocumentTemplateRenderer`.
     *
     * «البنود» هنا أقساطُ الجدول: كلُّ قسطٍ سطرٌ برقمه وتاريخ استحقاقه
     * ومبلغه وما سُدِّد منه. وهو ما يوقّع عليه العميل فعلاً.
     */
    private function normalizeInstallmentAgreement($doc, $business)
    {
        $items = [];

        foreach ($doc->details as $row) {
            $items[] = [
                'code' => (string) $row->installment_number,
                'name' => __('sales_installments.fields.due_date').' '.optional($row->due_date)->format('Y-m-d'),
                'quantity' => 1,
                'unit' => '',
                'price' => number_format((float) $row->amount, 2, '.', ''),
                'discount' => number_format((float) $row->paid_amount, 2, '.', ''),
                'tax' => number_format((float) $row->tax_amount, 2, '.', ''),
                /* المتبقّي **وغرامتُه المحمَّلة**: الغرامة مطالبةٌ قائمة
                   على العميل بمقتضى هذا المستند، فطبعُ المتبقّي دونها
                   يعطيه ورقةً تقول أقلّ ممّا عليه. */
                'total' => number_format(
                    (float) $row->remaining_amount + (float) $row->late_fee_remaining,
                    2, '.', ''
                ),
            ];
        }

        /* سياسة الغرامة تُذيَّل بالملاحظات.

           قوالبُ المستندات في هذا النظام تُخزَّن في قاعدة البيانات بشكلٍ
           ثابت (business / customer / document / items / totals)، فإضافةُ
           حقلٍ جديد تعني تعديلَ مئتي قالبٍ وأكثر. والملاحظاتُ حقلٌ حرّ
           موجودٌ في كلّ قالب، وهي موضعُ الشروط المكتوبة أصلاً.

           ولا يُذكَر شيء إن لم تُضبَط سياسةٌ: «بلا غرامة» سطرٌ لا يقول
           شيئاً في عقد. */
        $notes = trim((string) ($doc->note ?? ''));

        if (method_exists($doc, 'hasLateFeePolicy') && $doc->hasLateFeePolicy()) {
            $policy = __('sales_installments.sections.penalty').': '.__('sales_installments.late_fee.policy', [
                'type' => __('sales_installments.late_fee_types.'.$doc->late_fee_type),
                'value' => $doc->late_fee_type === 'percent'
                    ? rtrim(rtrim(number_format((float) $doc->late_fee_value, 2), '0'), '.').'%'
                    : $doc->money($doc->late_fee_value),
                'grace' => (int) $doc->late_fee_grace_days,
                'basis' => __('sales_installments.late_fee_bases.'.($doc->late_fee_basis ?: 'once')),
            ]);

            $notes = $notes === '' ? $policy : $notes.'
'.$policy;
        }

        return [
            'business' => $business,
            'customer' => [
                'name' => $doc->client->trade_name
                    ?? trim(($doc->client->first_name ?? '').' '.($doc->client->last_name ?? ''))
                    ?: __('sales_settings.ui.none'),
                'tax_number' => $doc->client->tax_number ?? '',
                'phone' => $doc->client->phone ?? '',
                'address' => $doc->client->street1 ?? '',
            ],
            'document' => [
                'title' => __('sales_installments.print.title'),
                // الرقم كما وُلِّد من «الترقيم المتسلسل» ببادئته
                'number' => $doc->code ?? (string) $doc->id,
                'date' => optional($doc->created_at)->format('Y-m-d'),
                'due_date' => optional($doc->last_due_date)->format('Y-m-d') ?? '',
                'payment_method' => $doc->paymentMethod->name ?? '',
                'status' => __('sales_installments.status.'.$doc->status),
                'notes' => $notes,
                'currency' => $doc->currency_code ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format((float) $doc->principal_amount, 2, '.', ''),
                'discount' => number_format((float) $doc->down_payment, 2, '.', ''),
                'tax' => number_format((float) $doc->tax_amount, 2, '.', ''),
                'grand_total' => number_format((float) $doc->financed_amount, 2, '.', ''),
                'paid' => number_format((float) $doc->paid_amount + (float) $doc->late_fees_paid, 2, '.', ''),
                // الأقساطُ وغراماتُها — ما على العميل كلُّه من هذه الاتفاقية
                'remaining' => number_format((float) $doc->total_outstanding, 2, '.', ''),
            ],
        ];
    }

    /**
     * Normalize Warehouse Permit model.
     */
    /**
     * تطبيع إذن مخزني — كان مبنياً على أعمدة غير موجودة في هذا التركيب
     * (`type`, `permit_date`, `client_id` وحده) فتخرج كل مطبوعة بعنوان
     * «صرف» ثابت، وتاريخ اليوم بدل تاريخ الإذن الفعلي، وأسعارٍ صفرية —
     * مهما كان محتوى الإذن الحقيقي.
     *
     * الاتجاه (استلام/صرف) من `permission_type` الرقمي لا من عمود نصّي
     * غير موجود: 1 و4 إضافة/رصيد افتتاحي (استلام)، 2 صرف، 3 تحويل يُعرض
     * كاستلام للمستودع الهدف. الطرف الآخر عميل مربوط (`client_id`) وإلا
     * مورّد فاتورة الشراء المصدر (`sub_account` حين `reference_type`
     * = purchase_invoice)، وإلا يبقى فارغاً بدل نصٍّ وهمي.
     */
    private function normalizeWarehousePermit($doc, $business)
    {
        $items = [];
        foreach ($doc->items as $item) {
            $items[] = [
                'code' => $item->product->barcode ?? $item->product_id ?? '',
                'name' => $item->product->name ?? 'منتج مخزني',
                'quantity' => $this->qty($item->quantity),
                'unit' => 'حبة',
                'price' => number_format((float) ($item->unit_price ?? 0), 2, '.', ''),
                'total' => number_format((float) ($item->total ?? ($item->quantity * ($item->unit_price ?? 0))), 2, '.', ''),
            ];
        }

        $party = null;

        if ($doc->client) {
            $party = [
                'name' => $doc->client->trade_name ?: trim(($doc->client->first_name ?? '').' '.($doc->client->last_name ?? '')),
                'tax_number' => $doc->client->tax_number ?? '',
                'phone' => $doc->client->phone ?? '',
                'address' => $doc->client->street1 ?? '',
                'code' => (string) $doc->client->id,
            ];
        } elseif ($doc->reference_type === 'purchase_invoice' && $doc->sub_account) {
            $supplier = Supplier::find($doc->sub_account);
            if ($supplier) {
                $party = $this->supplierParty($supplier);
            }
        }

        $party = $party ?? ['name' => '', 'tax_number' => '', 'address' => '', 'phone' => '', 'code' => ''];

        $title = match ((int) $doc->permission_type) {
            2 => __('settings.doc_templates.types.warehouse_permit_issue'),
            default => __('settings.doc_templates.types.warehouse_permit_receipt'),
        };

        $grandTotal = (float) ($doc->grand_total ?: collect($items)->sum(fn ($item) => (float) $item['total']));

        return [
            'business' => $business,
            'customer' => $party,
            'document' => [
                'title' => $title,
                'number' => $doc->number ?: str_pad($doc->id, 6, '0', STR_PAD_LEFT),
                'date' => LanguageSettings::formatDate($doc->permission_date ?: $doc->created_at),
                'due_date' => '',
                'payment_method' => '',
                'notes' => $doc->details ?? '',
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                'grand_total' => number_format($grandTotal, 2, '.', ''),
            ],
        ];
    }

    /**
     * عقد العمل — من حقوله الحقيقية.
     *
     * كان يقرأ أعمدةً لا وجود لها على العقد ولا على الموظف (`employee->name`
     * و`salary` و`contract_terms` و`payment_method`)، فتخرج كلُّ مطبوعة عقدٍ
     * باسمٍ فارغ وراتبٍ صفر و«شروط العقد وتفاصيله المعتمدة» نصّاً ثابتاً.
     *
     * ما يُملأ الآن:
     *   • `customer`      الموظف: الاسم الكامل والجوال والعنوان ورقم الموظف.
     *   • `document`      رقم العقد من التسلسل، وتاريخا البداية والانتهاء،
     *                     و«طريقة صرف الراتب» = دورة القبض، و`notes` = بنود
     *                     العقد نصّاً (المسمّى والمستوى والقسم والفرع والمدّة
     *                     والتواريخ وتفصيل الراتب) — والقوالب تعرضه بـ`pre-line`.
     *   • `items`         سطرٌ لكل بند: الأساسي ثم المستحقات ثم المستقطعات —
     *                     لمن يصمّم قالباً بجدول بنود (`{{#items}}`).
     *   • `totals`        `grand_total` = إجمالي الراتب (مبلغ العقد: الأساسي +
     *                     المستحقات)، `subtotal` = الأساسي، `discount` =
     *                     المستقطعات، `remaining` = صافي الراتب.
     *   • `contract`      الحقول نفسُها مفاتيحَ مسمّاة لقالبٍ يكتب `$contract[...]`.
     */
    private function normalizeContract($doc, $business)
    {
        $employee = $doc->employee;
        $fmt = fn ($v) => number_format((float) $v, 2, '.', ',');
        $date = fn ($d) => $d ? LanguageSettings::formatDate($d) : '';

        $items = $doc->relationLoaded('salaryItems') ? $doc->salaryItems : $doc->salaryItems()->get();
        $additions = $items->where('type', SalaryItem::TYPE_ADDITION)->values();
        $deductions = $items->where('type', SalaryItem::TYPE_DEDUCTION)->values();
        $totals = $doc->salaryTotals();

        $currency = $doc->currency ?: ($business['currency'] ?? '');

        $lines = [];
        $index = 0;
        if ($totals['basic'] > 0) {
            $lines[] = ['code' => (string) ++$index, 'name' => 'الراتب الأساسي', 'quantity' => '1', 'unit' => 'مستحق',
                'price' => $fmt($totals['basic']), 'discount' => '', 'tax' => '', 'total' => $fmt($totals['basic']), 'description' => ''];
        }
        foreach ($additions as $item) {
            $lines[] = ['code' => (string) ++$index, 'name' => $item->name, 'quantity' => '1', 'unit' => 'مستحق',
                'price' => $fmt($item->amount), 'discount' => '', 'tax' => '', 'total' => $fmt($item->amount), 'description' => (string) $item->calculation_formula];
        }
        foreach ($deductions as $item) {
            $lines[] = ['code' => (string) ++$index, 'name' => $item->name, 'quantity' => '1', 'unit' => 'مستقطع',
                'price' => $fmt($item->amount), 'discount' => '', 'tax' => '', 'total' => '-'.$fmt($item->amount), 'description' => (string) $item->calculation_formula];
        }

        $duration = (int) $doc->type_contract === Contract::TYPE_END_DATE || ! $doc->duration
            ? ($doc->end_date ? 'حتى '.$date($doc->end_date) : 'غير محددة')
            : $doc->duration.' '.$doc->duration_unit_text;

        $contract = [
            'code' => (string) ($doc->code ?: $doc->id),
            'employee_name' => $employee->full_name ?? '',
            'employee_code' => (string) ($employee->employee_code ?? ''),
            'job_title' => $doc->jobTitle->name ?? '',
            'job_level' => $doc->jobLevel->name ?? '',
            'department' => $employee->department->name ?? '',
            'branch' => $employee->branch->name ?? '',
            'start_date' => $date($doc->start_date),
            'end_date' => $date($doc->end_date),
            'duration' => $duration,
            'join_date' => $date($doc->join_date),
            'probation_end_date' => $date($doc->probation_end_date),
            'contract_date' => $date($doc->contract_date),
            'receiving_cycle' => $doc->receiving_cycle ? $doc->receiving_cycle_text : '',
            'currency' => $currency,
            'basic_salary' => $fmt($totals['basic']),
            'total_additions' => $fmt($totals['additions']),
            'total_deductions' => $fmt($totals['deductions']),
            'gross_salary' => $fmt($totals['gross']),
            'net_salary' => $fmt($totals['net']),
            'description' => (string) ($doc->description ?? ''),
        ];

        // نصُّ البنود — سطرٌ لكل معلومة، والفارغُ يُسقَط
        $terms = array_filter([
            $contract['job_title'] ? 'المسمّى الوظيفي: '.$contract['job_title'] : null,
            $contract['job_level'] ? 'المستوى الوظيفي: '.$contract['job_level'] : null,
            $contract['department'] ? 'القسم: '.$contract['department'] : null,
            $contract['branch'] ? 'الفرع: '.$contract['branch'] : null,
            'مدة العقد: '.$duration,
            $contract['join_date'] ? 'تاريخ المباشرة: '.$contract['join_date'] : null,
            $contract['probation_end_date'] ? 'نهاية فترة التجربة: '.$contract['probation_end_date'] : null,
            $contract['contract_date'] ? 'تاريخ توقيع العقد: '.$contract['contract_date'] : null,
            'الراتب الأساسي: '.$contract['basic_salary'].' '.$currency,
            $additions->isNotEmpty()
                ? 'المستحقات: '.$additions->map(fn ($i) => $i->name.' '.$fmt($i->amount))->implode('، ').' (المجموع '.$contract['total_additions'].')'
                : null,
            $deductions->isNotEmpty()
                ? 'المستقطعات: '.$deductions->map(fn ($i) => $i->name.' '.$fmt($i->amount))->implode('، ').' (المجموع '.$contract['total_deductions'].')'
                : null,
            'إجمالي الراتب: '.$contract['gross_salary'].' '.$currency,
            'صافي الراتب: '.$contract['net_salary'].' '.$currency,
            $contract['description'] !== '' ? $contract['description'] : null,
        ]);

        /* `contract.terms`: البنودُ بلا أسطر الراتب — القوالبُ التي تعرض جدولَ
           البنود (`{{#items}}`) وإجماليّاته تقرؤها بدل `document.notes`، فلا
           يتكرّر الراتبُ نصّاً تحت الجدول. `document.notes` باقٍ كاملاً لقوالب
           المستخدمين القائمة. */
        $contract['terms'] = implode("\n", array_filter([
            $contract['job_title'] ? 'المسمّى الوظيفي: '.$contract['job_title'] : null,
            $contract['job_level'] ? 'المستوى الوظيفي: '.$contract['job_level'] : null,
            $contract['department'] ? 'القسم: '.$contract['department'] : null,
            $contract['branch'] ? 'الفرع: '.$contract['branch'] : null,
            'مدة العقد: '.$duration,
            $contract['join_date'] ? 'تاريخ المباشرة: '.$contract['join_date'] : null,
            $contract['probation_end_date'] ? 'نهاية فترة التجربة: '.$contract['probation_end_date'] : null,
            $contract['contract_date'] ? 'تاريخ توقيع العقد: '.$contract['contract_date'] : null,
            $contract['description'] !== '' ? $contract['description'] : null,
        ]));
        $contract['has_deductions'] = $deductions->isNotEmpty() ? '1' : '';

        /* «تصميم الموارد البشرية» — حقولٌ إضافية للطرف الثاني وللتفقيط.
           إضافةٌ خالصة: لا مفتاحَ قائماً يتغيّر. */
        $contract['nationality'] = (string) ($employee->country ?? '');
        $contract['gosi_number'] = (string) ($employee->gosi_number ?? '');
        $contract['phone'] = (string) (($employee->mobile_number ?? '') ?: ($employee->phone_number ?? ''));
        $contract['email'] = (string) ($employee->email ?? '');
        $contract['net_salary_words'] = NumberToWords::arabic($totals['net'], (string) $currency);
        $contract['has_probation'] = $contract['probation_end_date'] !== '' ? '1' : '';
        $contract['has_end_date'] = $contract['end_date'] !== '' ? '1' : '';

        $address = trim(implode('، ', array_filter([
            $employee->current_address_1 ?? null,
            $employee->current_address_2 ?? null,
            $employee->city ?? null,
            $employee->region ?? null,
        ])));

        return [
            'business' => $business,
            'customer' => [
                'name' => $employee->full_name ?? '',
                'tax_number' => '',
                'phone' => (string) ($employee->mobile_number ?: ($employee->phone_number ?? '')),
                'address' => $address,
                'code' => (string) ($employee->employee_code ?? $employee->id ?? ''),
            ],
            'document' => [
                'title' => 'عقد عمل',
                'number' => $contract['code'],
                'date' => $contract['start_date'],
                'due_date' => $contract['end_date'],
                'payment_method' => $contract['receiving_cycle'],
                'notes' => implode("\n", $terms),
                'qr_code' => '',
                'barcode' => '',
                'employee' => $doc->creator->name ?? '',
                'branch' => $contract['branch'],
            ],
            'items' => $lines,
            'contract' => $contract,
            'totals' => [
                'subtotal' => number_format($totals['basic'], 2, '.', ''),
                'discount' => number_format($totals['deductions'], 2, '.', ''),
                'grand_total' => number_format($totals['gross'], 2, '.', ''),
                'remaining' => number_format($totals['net'], 2, '.', ''),
            ],
        ];
    }

    /**
     * قسيمةُ الراتب لقوالب «كشف رواتب».
     *
     * كانت تقرأ أعمدةً لا وجود لها في `salary_slips` (`basic_salary`،
     * `allowances`، `deductions`، `date_issued`) واسمَ موظفٍ من `name` — فتخرج
     * كلُّ مطبوعةٍ بأصفارٍ وتاريخ اليوم و«الموظف المستلم». صارت تقرأ القسيمةَ
     * كما تحفظها شاشتُها: الأساسيُّ ثمّ بنودُ الاستحقاق موجبةً ثمّ بنودُ
     * الخصم سالبة، والإجماليّاتُ من الأعمدة نفسِها التي تعرضها الشاشة.
     *
     * `customer.address` تحمل القسمَ/المسمّى: القوالبُ المزروعة تعنونها
     * «القسم / الإدارة».
     */
    private function normalizeSalarySlip($doc, $business)
    {
        $employee = $doc->employee;

        /*
            **مصدرُ المطبوع: الصرفةُ لا القسيمة** (2026-09-26).

            القسيمةُ اتفاقُ راتبٍ مستمرّ (أساسي + بنودٌ ثابتة). أمّا ما صُرف فعلاً
            في شهرٍ بعينه فيُحسب عند الصرف ويُحفظ في `payroll_run_items`: خصميات
            الحضور والجزاءات من جدول `deductions`، والإضافيّ، والمكافآت، والتسوية
            الرجعية، والتأمينات، والسلف. كان المطبوعُ يُبنى من القسيمة وحدها،
            فتخرج «طباعة صرف القسيمة» بلا خصمِ الغياب ولا الإضافيّ، وبمجاميع
            القسيمة لا مجاميعِ ما صُرف. الآن: إن وُجدت صرفةٌ (المحدّدة، وإلا
            الأحدث) فبنودُها ومجاميعُها هي المطبوع؛ وقسيمةٌ لم تُصرف بعد تُطبع
            من بنودها كما كانت.
        */
        $run = null;
        $pinnedRun = $this->payrollRunId;
        $this->payrollRunId = null;
        try {
            $run = $pinnedRun !== null
                ? $doc->payrollRuns()->whereKey($pinnedRun)->first()
                : $doc->payrollRuns()
                    ->whereNotIn('status', ['cancelled'])
                    ->orderByDesc('period_to')->orderByDesc('id')->first();
        } catch (\Throwable $e) {
            $run = null;
        }

        $runItems = collect();
        if ($run && Schema::hasTable('payroll_run_items')) {
            $runItems = DB::table('payroll_run_items')
                ->where('payroll_run_id', $run->id)
                ->orderBy('id')
                ->get();
        }
        $fromRun = $runItems->isNotEmpty();

        $items = [];
        $money = fn ($v) => number_format((float) $v, 2, '.', ',');

        if ($fromRun) {
            // المستحقات كما صُرفت (الأساسيّ سطرٌ منها)
            foreach ($runItems->where('item_type', 'addition') as $line) {
                $items[] = $this->salaryLine((string) $line->name, (float) $line->amount)
                    + ['kind' => 'earning', 'amount' => $money($line->amount), 'note' => (string) ($line->description ?? '')];
            }
            // الاستقطاعات كما اقتُطعت — التأمينات والغياب والجزاءات وبنود القسيمة
            // (السلفُ خارجها: يطبعها القالبُ سطراً «سلف مستقطعة» من slip.advances)
            foreach ($runItems->where('item_type', 'deduction') as $line) {
                $items[] = $this->salaryLine((string) $line->name, -1 * (float) $line->amount)
                    + ['kind' => 'deduction', 'amount' => $money($line->amount), 'note' => (string) ($line->description ?? '')];
            }

            $basic = (float) ($run->basic_amount ?? 0);
            $gross = (float) $runItems->where('item_type', 'addition')->sum('amount');
            $deductions = (float) $runItems->where('item_type', 'deduction')->sum('amount');
            $advancesTotal = (float) ($run->total_advances_deducted ?? $runItems->where('item_type', 'advance')->sum('amount'));
            // صافي الصرفة محسوبٌ بعد السلف أصلاً
            $net = (float) ($run->net_salary ?? max(0, $gross - $deductions - $advancesTotal)) + $advancesTotal;
        } else {
            $basic = (float) ($doc->basic_amount ?? 0);

            if ($basic > 0) {
                $items[] = $this->salaryLine('الراتب الأساسي', $basic) + ['kind' => 'earning', 'amount' => $money($basic)];
            }

            foreach ($doc->salaryItems->where('type', SalaryItem::TYPE_ADDITION) as $item) {
                $items[] = $this->salaryLine($item->name, (float) $item->amount) + ['kind' => 'earning', 'amount' => $money($item->amount)];
            }

            foreach ($doc->salaryItems->where('type', SalaryItem::TYPE_DEDUCTION) as $item) {
                $items[] = $this->salaryLine($item->name, -1 * (float) $item->amount) + ['kind' => 'deduction', 'amount' => $money($item->amount)];
            }

            $gross = (float) ($doc->total_salary ?? 0);
            $deductions = (float) ($doc->total_deductions ?? 0);
            $net = (float) ($doc->net_salary ?? ($gross - $deductions));
        }

        $period = trim(
            optional($doc->start_date)->format('Y-m-d').' → '.(optional($doc->end_date)->format('Y-m-d') ?: 'مفتوحة'),
            ' →'
        );

        /* `slip.*` — «تصميم الموارد البشرية» لقسيمة الراتب: بيانات الموظف
           والبنك، والتأمينات، والسلف المستقطعة في آخر صرفٍ للقسيمة، والتفقيط.
           والسلفُ لا تدخل `items` ولا `totals` — تلك تقرؤها قوالبُ قائمة
           بمجاميع القسيمة نفسها. */
        $fmt = fn ($v) => number_format((float) $v, 2, '.', ',');
        $insuranceItemIds = $doc->salaryItems
            ->where('type', SalaryItem::TYPE_DEDUCTION)
            ->where('origin', SalaryItem::ORIGIN_SOCIAL_INSURANCE)
            ->pluck('id')->all();
        $insurance = $fromRun
            ? (float) $runItems->where('item_type', 'deduction')
                ->filter(fn ($line) => $line->source_table === 'salary_items' && in_array((int) $line->source_id, $insuranceItemIds, true))
                ->sum('amount')
            : (float) $doc->salaryItems
                ->where('type', SalaryItem::TYPE_DEDUCTION)
                ->where('origin', SalaryItem::ORIGIN_SOCIAL_INSURANCE)
                ->sum('amount');

        $advances = (float) ($run->total_advances_deducted ?? 0);
        $currency = (string) (($doc->currency ?? '') ?: ($business['currency'] ?? ''));
        $payable = max(0, $net - $advances);

        /* السلف أسطراً مفصّلة (نوعُها advance) لكلّ القوالب — كلُّ سلفةٍ باسمها من
           بنود الصرفة، وإلا سطرٌ واحد بمجموعها */
        $advanceLines = $fromRun ? $runItems->where('item_type', 'advance') : collect();
        if ($advanceLines->isNotEmpty()) {
            foreach ($advanceLines as $line) {
                $items[] = $this->salaryLine((string) $line->name, -1 * (float) $line->amount)
                    + ['kind' => 'advance', 'amount' => number_format((float) $line->amount, 2, '.', ','), 'note' => (string) ($line->description ?? '')];
            }
        } elseif ($advances > 0.001) {
            $items[] = $this->salaryLine('سلف مستقطعة', -1 * $advances)
                + ['kind' => 'advance', 'amount' => number_format($advances, 2, '.', ',')];
        }

        $slip = [
            'employee_name' => $employee ? trim(($employee->full_name ?? '') ?: trim($employee->first_name.' '.$employee->middle_name)) : '—',
            'employee_code' => (string) ($employee->employee_code ?? $employee->id ?? ''),
            'job_title' => (string) (optional(optional($employee)->job_title)->name ?? ''),
            'department' => (string) (optional(optional($employee)->department)->name ?? ''),
            'branch' => (string) (optional(optional($employee)->branch)->name ?? ''),
            'bank_name' => (string) ($employee->bank_name ?? ''),
            'iban' => (string) ($employee->iban ?? ''),
            'payroll_name' => (string) (optional($doc->payroll)->name ?? ''),
            'period' => $run && $run->period_from
                ? Carbon::parse($run->period_from)->format('Y-m')
                : (optional($doc->start_date ?? $doc->slip_date)->format('Y-m') ?: now()->format('Y-m')),
            'period_from' => $run && $run->period_from ? Carbon::parse($run->period_from)->format('Y-m-d') : (optional($doc->start_date)->format('Y-m-d') ?? ''),
            'period_to' => $run && $run->period_to ? Carbon::parse($run->period_to)->format('Y-m-d') : (optional($doc->end_date)->format('Y-m-d') ?? ''),
            'payment_date' => $run && $run->disbursed_at ? Carbon::parse($run->disbursed_at)->format('Y-m-d') : ($run && $run->scheduled_date ? Carbon::parse($run->scheduled_date)->format('Y-m-d') : ''),
            'status' => (string) (SalarySlip::statuses()[$doc->status] ?? $doc->status),
            'basic' => $fmt($basic),
            'total_earnings' => $fmt($gross),
            'total_deductions' => $fmt($deductions + $advances),
            'insurance' => $fmt($insurance),
            'has_insurance' => $insurance > 0.001 ? '1' : '',
            'advances' => $fmt($advances),
            'has_advances' => $advances > 0.001 ? '1' : '',
            'net' => $fmt($payable),
            'net_words' => NumberToWords::arabic($payable, $currency),
            'currency' => $currency,
            'notes' => (string) ($doc->notes ?? ''),
            'has_deductions' => ($deductions + $advances) > 0.001 ? '1' : '',
        ];

        return [
            'slip' => $slip,
            'business' => $business,
            'customer' => [
                'name' => $employee ? trim(($employee->full_name ?? '') ?: trim($employee->first_name.' '.$employee->middle_name)) : '—',
                'tax_number' => '',
                'phone' => $employee->mobile_number ?? $employee->phone_number ?? '',
                'address' => $employee ? trim(implode(' / ', array_filter([
                    optional($employee->department)->name,
                    optional($employee->job_title)->name,
                ]))) : '',
                'code' => (string) ($employee->employee_code ?? $employee->id ?? ''),
            ],
            'document' => [
                'title' => 'قسيمة راتب',
                'number' => $doc->code ?: str_pad((string) $doc->id, 6, '0', STR_PAD_LEFT),
                'date' => optional($doc->start_date ?? $doc->slip_date)->format('Y-m') ?: now()->format('Y-m'),
                'due_date' => optional($doc->end_date)->format('Y-m-d') ?? '',
                'payment_method' => '',
                'notes' => trim(implode(' — ', array_filter([
                    $doc->payroll ? 'المسير: '.$doc->payroll->name : null,
                    $period !== '' ? 'الفترة: '.$period : null,
                    $doc->notes,
                ]))),
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format($gross, 2, '.', ''),
                // الخصوماتُ كلُّها (التأمينات والجزاءات والسلف) — والصافي ما يستلمه الموظف فعلاً
                'discount' => number_format($deductions + $advances, 2, '.', ''),
                'grand_total' => number_format($payable, 2, '.', ''),
            ],
        ];
    }

    /**
     * «كشف مسير رواتب»: سطرٌ لكلّ قسيمةٍ في المسير (`payroll_id`) —
     * القسائمُ نفسُها التي تعرضها شاشةُ المسير وملفُّ Excel، مقيَّدةً بنطاق
     * الطابع ({@see withEmployeeScope()}).
     */
    private function normalizePayrollSheet($payroll, $business)
    {
        $query = SalarySlip::with(['employee.department', 'employee.job_title', 'salaryItems'])
            ->where('payroll_id', $payroll->id)
            ->orderBy('employee_id')
            ->orderBy('id');

        if ($this->employeeScope !== null) {
            $query->whereIn('employee_id', $this->employeeScope ?: [0]);
        }

        $slips = $query->get();

        $memberIds = $payroll->memberEmployeeIds();
        $members = $this->employeeScope === null
            ? count($memberIds)
            : count(array_intersect($memberIds, $this->employeeScope));
        $this->employeeScope = null;

        /* السلفُ المستقطعة لكلّ قسيمة: من آخر صرفٍ لها داخل فترة المسير. */
        /*
            **الكشفُ من الصرفات لا من القسائم** (2026-09-26): ما صُرف فعلاً في
            فترة المسير — خصمياتُ الحضور والجزاءات، والإضافيّ، والسلف — محفوظٌ في
            الصرفة وبنودها لا في القسيمة. فلكلّ قسيمةٍ آخرُ صرفةٍ (غيرُ ملغاة)
            داخل الفترة وبنودُها؛ وقسيمةٌ بلا صرفةٍ بعد تبقى على أرقامها.
        */
        $runsBySlip = [];
        $runItemsByRun = [];
        if ($slips->isNotEmpty() && Schema::hasTable('payroll_runs')) {
            $runsBySlip = DB::table('payroll_runs')
                ->whereIn('salary_slip_id', $slips->pluck('id'))
                ->where('status', '!=', 'cancelled')
                ->when($payroll->start_date, fn ($q) => $q->where('period_to', '>=', $payroll->start_date->format('Y-m-d')))
                ->when($payroll->end_date, fn ($q) => $q->where('period_from', '<=', $payroll->end_date->format('Y-m-d')))
                ->orderBy('period_to')
                ->orderBy('id')
                ->get()
                ->keyBy('salary_slip_id')
                ->all();

            if ($runsBySlip && Schema::hasTable('payroll_run_items')) {
                $runItemsByRun = DB::table('payroll_run_items')
                    ->whereIn('payroll_run_id', collect($runsBySlip)->pluck('id'))
                    ->get()
                    ->groupBy('payroll_run_id')
                    ->all();
            }
        }

        $fmt = fn ($v) => number_format((float) $v, 2, '.', ',');
        $sum = ['basic' => 0.0, 'allowances' => 0.0, 'gross' => 0.0, 'insurance' => 0.0, 'advances' => 0.0, 'other' => 0.0, 'net' => 0.0];

        $items = $slips->map(function ($slip) use ($runsBySlip, $runItemsByRun, $fmt, &$sum) {
            $employee = $slip->employee;

            $deductionItems = $slip->salaryItems->where('type', SalaryItem::TYPE_DEDUCTION);
            $insuranceItemIds = $deductionItems->where('origin', SalaryItem::ORIGIN_SOCIAL_INSURANCE)->pluck('id')->all();
            $run = $runsBySlip[$slip->id] ?? null;
            $lines = $run ? collect($runItemsByRun[$run->id] ?? []) : collect();

            if ($run && $lines->isNotEmpty()) {
                $basicAmount = (float) $run->basic_amount;
                $grossAmount = (float) $lines->where('item_type', 'addition')->sum('amount');
                $deductionAmount = (float) $lines->where('item_type', 'deduction')->sum('amount');
                $insurance = (float) $lines->where('item_type', 'deduction')
                    ->filter(fn ($line) => $line->source_table === 'salary_items' && in_array((int) $line->source_id, $insuranceItemIds, true))
                    ->sum('amount');
                $advanceAmount = (float) $run->total_advances_deducted;
                $netAmount = max(0, (float) $run->net_salary);
            } else {
                $insurance = (float) $deductionItems->where('origin', SalaryItem::ORIGIN_SOCIAL_INSURANCE)->sum('amount');
                $basicAmount = (float) $slip->basic_amount;
                $grossAmount = (float) $slip->total_salary;
                $deductionAmount = (float) $slip->total_deductions;
                $advanceAmount = (float) ($run->total_advances_deducted ?? 0);
                $netAmount = max(0, (float) $slip->net_salary - $advanceAmount);
            }
            $otherAmount = max(0, $deductionAmount - $insurance);

            $sum['basic'] += $basicAmount;
            $sum['allowances'] += max(0, $grossAmount - $basicAmount);
            $sum['gross'] += $grossAmount;
            $sum['insurance'] += $insurance;
            $sum['advances'] += $advanceAmount;
            $sum['other'] += $otherAmount;
            $sum['net'] += $netAmount;

            // «تصميم الموارد البشرية» — أعمدة المسير المفصّلة (`{{item.basic}}`…)
            $hr = [
                'emp_name' => $employee ? trim(($employee->full_name ?? '') ?: trim($employee->first_name.' '.$employee->middle_name)) : '—',
                'emp_code' => (string) ($employee->employee_code ?? $employee->id ?? ''),
                'department' => (string) (optional(optional($employee)->department)->name ?? ''),
                'job_title' => (string) (optional(optional($employee)->job_title)->name ?? ''),
                'basic' => $fmt($basicAmount),
                'allowances' => $fmt(max(0, $grossAmount - $basicAmount)),
                'gross' => $fmt($grossAmount),
                'insurance' => $fmt($insurance),
                'advances' => $fmt($advanceAmount),
                'other_deductions' => $fmt($otherAmount),
                'net' => $fmt($netAmount),
            ];
            $name = $employee ? trim(($employee->full_name ?? '') ?: trim($employee->first_name.' '.$employee->middle_name)) : '—';
            $meta = array_filter([
                $slip->code,
                'أساسي '.number_format($basicAmount, 2),
                'إجمالي '.number_format($grossAmount, 2),
                ($deductionAmount + $advanceAmount) > 0 ? 'خصم '.number_format($deductionAmount + $advanceAmount, 2) : null,
            ]);

            return [
                'code' => (string) ($slip->code ?? $slip->id),
                'name' => $name.' ('.implode(' · ', $meta).')',
                'quantity' => 1,
                'unit' => '',
                'price' => number_format($grossAmount, 2, '.', ''),
                'discount' => number_format($deductionAmount + $advanceAmount, 2, '.', ''),
                'tax' => '0.00',
                'total' => number_format($netAmount, 2, '.', ''),
            ] + $hr;
        })->values()->all();

        $statusLabels = Payroll::statuses();
        $totalDeductions = $sum['insurance'] + $sum['advances'] + $sum['other'];

        // `payroll.*` — رأسُ المسير وملخّصه في «تصميم الموارد البشرية»
        $sheet = [
            'name' => (string) $payroll->name,
            'code' => str_pad((string) $payroll->id, 6, '0', STR_PAD_LEFT),
            'period_from' => optional($payroll->start_date)->format('Y-m-d') ?? '',
            'period_to' => optional($payroll->end_date)->format('Y-m-d') ?? '',
            'branch' => (string) (optional($payroll->branch)->name ?? '') ?: 'كل الفروع',
            'department' => (string) (optional($payroll->department)->name ?? '') ?: 'كل الأقسام',
            'job_title' => (string) (optional($payroll->jobTitle)->name ?? ''),
            'status' => (string) ($statusLabels[$payroll->status] ?? $payroll->status),
            'approved_at' => optional($payroll->approved_at)->format('Y-m-d') ?? '',
            'employees_count' => (string) count($items),
            'members_count' => (string) $members,
            'total_basic' => $fmt($sum['basic']),
            'total_allowances' => $fmt($sum['allowances']),
            'total_gross' => $fmt($sum['gross']),
            'total_insurance' => $fmt($sum['insurance']),
            'total_advances' => $fmt($sum['advances']),
            'total_other' => $fmt($sum['other']),
            'total_deductions' => $fmt($totalDeductions),
            'total_net' => $fmt($sum['net']),
            'net_words' => NumberToWords::arabic($sum['net'], (string) ($business['currency'] ?? '')),
        ];

        return [
            'payroll' => $sheet,
            'business' => $business,
            'customer' => [
                'name' => $payroll->name,
                'tax_number' => '',
                'phone' => '',
                'address' => trim(implode(' / ', array_filter([
                    optional($payroll->branch)->name,
                    optional($payroll->department)->name,
                    optional($payroll->jobTitle)->name,
                ]))) ?: 'كل الفروع / كل الأقسام',
                'code' => (string) $payroll->id,
            ],
            'document' => [
                'title' => 'كشف مسير رواتب',
                'number' => str_pad((string) $payroll->id, 6, '0', STR_PAD_LEFT),
                'date' => optional($payroll->start_date)->format('Y-m') ?: optional($payroll->registration_date)->format('Y-m-d') ?: now()->format('Y-m'),
                'due_date' => optional($payroll->end_date)->format('Y-m-d') ?? '',
                'payment_method' => '',
                'notes' => 'عدد الموظفين في المسير: '.$members.' — عدد القسائم: '.count($items),
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format((float) $slips->sum('total_salary'), 2, '.', ''),
                'discount' => number_format((float) $slips->sum('total_deductions'), 2, '.', ''),
                'grand_total' => number_format((float) $slips->sum('net_salary'), 2, '.', ''),
            ],
        ];
    }

    /** بياناتُ الموظف في سندات الموارد البشرية — `address` تحمل القسم/المسمّى */
    private function employeeParty($employee): array
    {
        return [
            'name' => $employee ? trim(($employee->full_name ?? '') ?: trim($employee->first_name.' '.$employee->middle_name)) : '—',
            'tax_number' => '',
            'phone' => (string) ($employee->mobile_number ?? $employee->phone_number ?? ''),
            'address' => $employee ? trim(implode(' / ', array_filter([
                optional($employee->department)->name,
                optional($employee->job_title)->name,
            ]))) : '',
            'code' => (string) ($employee->employee_code ?? $employee->id ?? ''),
        ];
    }

    /**
     * «سند سلفة» — طلبُ سلفةٍ من `salary_advance_requests`.
     *
     * `items` جدولُ السداد: قسطٌ لكلّ شهرٍ من `start_month` في السلفة
     * المجدولة (المسدَّدُ منها بعدد `paid_installments`)، أو سطرٌ واحد في
     * السلفة العادية. و`advance.*` الحقولُ مسمّاةً للقالب — ومنها المرحلةُ
     * بعبارة الشاشة نفسها (`stageLabel()`).
     */
    private function normalizeSalaryAdvance($doc, $business)
    {
        $fmt = fn ($v) => number_format((float) $v, 2, '.', '');
        $date = fn ($d) => $d ? LanguageSettings::formatDate($d) : '';

        $amount = (float) $doc->amount;
        $repaid = $doc->repaidAmount();
        $remaining = $doc->remainingAmount();
        $scheduled = $doc->isScheduled() && (int) $doc->installments_count > 0;
        $paidCount = (int) $doc->paid_installments;

        $items = [];
        if ($scheduled) {
            $count = (int) $doc->installments_count;
            $per = (float) ($doc->installment_amount ?: round($amount / $count, 2));
            $start = null;
            try {
                $month = (string) $doc->start_month;
                $start = $month !== '' ? Carbon::parse(strlen($month) === 7 ? $month.'-01' : $month) : null;
            } catch (\Throwable $e) {
                $start = null;
            }

            for ($i = 0; $i < $count; $i++) {
                $value = $i === $count - 1 ? max(0, round($amount - $per * ($count - 1), 2)) : $per;
                $items[] = [
                    'code' => (string) ($i + 1),
                    'name' => $start ? 'قسط '.$start->copy()->addMonthsNoOverflow($i)->format('Y-m') : 'القسط '.($i + 1),
                    'quantity' => 1,
                    'unit' => ($i < $paidCount || $doc->is_settled) ? 'مسدَّد' : 'مستحق',
                    'price' => $fmt($value),
                    'discount' => '0.00',
                    'tax' => '0.00',
                    'total' => $fmt($value),
                ];
            }
        } else {
            $items[] = [
                'code' => '1',
                'name' => 'سلفة عادية — تُسدَّد دفعةً واحدة'.($doc->start_month ? ' ('.$doc->start_month.')' : ''),
                'quantity' => 1,
                'unit' => $doc->is_settled ? 'مسدَّد' : ($repaid > 0 ? 'مسدَّد جزئياً' : 'مستحق'),
                'price' => $fmt($amount),
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => $fmt($amount),
            ];
        }

        $approver = $doc->approver->name ?? ($doc->approvedBy->name ?? '');

        $advance = [
            'type' => $scheduled ? 'مجدولة على '.(int) $doc->installments_count.' أقساط' : 'عادية',
            'stage' => $doc->stageLabel(),
            'requested_date' => $date($doc->requested_date),
            'approved_date' => $date($doc->approved_date),
            'disbursed_date' => $date($doc->disbursed_at),
            'approver' => (string) $approver,
            'disbursed_by' => (string) ($doc->disbursedBy->name ?? ''),
            'treasury' => (string) ($doc->treasury->name ?? ''),
            'start_month' => (string) ($doc->start_month ?? ''),
            'installments_count' => $scheduled ? (string) (int) $doc->installments_count : '',
            'installment_amount' => $scheduled ? $fmt($doc->installment_amount) : '',
            'paid_installments' => $scheduled ? (string) $paidCount : '',
            'amount' => $fmt($amount),
            'repaid' => $fmt($repaid),
            'remaining' => $fmt($remaining),
            'has_repaid' => $repaid > 0.001 ? '1' : '',
            'reason' => (string) ($doc->reason ?? ''),
            'admin_notes' => (string) ($doc->admin_notes ?? ''),
            'amount_words' => NumberToWords::arabic($amount, (string) ($business['currency'] ?? '')),
        ];

        return [
            'business' => $business,
            'customer' => $this->employeeParty($doc->employee),
            'document' => [
                'title' => 'سند سلفة',
                'number' => 'ADV-'.str_pad((string) $doc->id, 6, '0', STR_PAD_LEFT),
                'date' => $advance['disbursed_date'] ?: $advance['requested_date'],
                'due_date' => '',
                'payment_method' => $advance['treasury'],
                'notes' => trim(implode("\n", array_filter([$advance['reason'], $advance['admin_notes']]))),
                'qr_code' => '',
                'barcode' => '',
                'employee' => $advance['disbursed_by'] ?: $advance['approver'],
            ],
            'items' => $items,
            'advance' => $advance,
            'totals' => [
                'subtotal' => $fmt($amount),
                'discount' => '0.00',
                'paid' => $fmt($repaid),
                'remaining' => $fmt($remaining),
                'grand_total' => $fmt($amount),
            ],
        ];
    }

    /**
     * «سند صرف عمولة» — تسويةُ عمولةٍ من `sales_target_settlements`.
     *
     * رقمُ السند رقمُ سند الصرف (`expenses.code`) الذي كتبته التسوية متى
     * وُجد، وإلّا `COM-{id}`. وحالةُ القيد كما تعرضها شاشةُ «العمولات
     * المصروفة»: مصروفةٌ (`is_settled`/`paid_directly`) أو بانتظار اعتماد القيد.
     */
    private function normalizeCommissionPayment($doc, $business)
    {
        $fmt = fn ($v) => number_format((float) $v, 2, '.', '');
        $date = fn ($d) => $d ? LanguageSettings::formatDate($d) : '';

        $target = $doc->salesTarget;
        $journal = $doc->direct_journal_entry_id ? JournalEntry::find($doc->direct_journal_entry_id) : null;
        $expense = $journal && $journal->reference_id
            ? Expense::where('id', $journal->reference_id)->where('code', $journal->reference_number)->first()
            : null;

        $posted = $doc->is_settled || $doc->paid_directly;
        $journalStatus = $journal
            ? ($posted ? 'معتمد' : 'بانتظار اعتماد القيد')
            : ($posted ? 'مصروفة' : '');

        $periodType = match ($target->commission_period ?? null) {
            'weekly' => 'أسبوعية',
            'monthly' => 'شهرية',
            default => '',
        };
        $period = trim($date($doc->from_date).' — '.$date($doc->to_date), ' —');
        $paidAt = $doc->direct_paid_at ?? $doc->settled_date ?? $doc->created_at;
        $amount = (float) ($doc->commission_amount ?? 0);

        $commission = [
            'target' => (string) ($target->name ?? ''),
            'target_value' => ($target && $target->target_value !== null) ? $fmt($target->target_value) : '',
            'period_type' => $periodType,
            'period' => $period,
            'paid_date' => $date($paidAt),
            'method' => $doc->paid_directly ? 'صرفٌ مباشر من الخزينة' : ($journal ? 'قيد صرف' : 'تسوية على الراتب'),
            'journal_number' => $journal ? (string) ($journal->reference_number ?: $journal->id) : '',
            'journal_status' => $journalStatus,
            'expense_code' => (string) ($expense->code ?? ''),
            'settled_by' => (string) ($doc->settler->name ?? ''),
            'amount' => $fmt($amount),
            'notes' => (string) ($doc->notes ?? ''),
            'amount_words' => NumberToWords::arabic($amount, (string) ($business['currency'] ?? '')),
        ];

        return [
            'business' => $business,
            'customer' => $this->employeeParty($doc->employee),
            'document' => [
                'title' => 'سند صرف عمولة',
                'number' => $commission['expense_code'] ?: 'COM-'.str_pad((string) $doc->id, 6, '0', STR_PAD_LEFT),
                'date' => $commission['paid_date'],
                'due_date' => '',
                'payment_method' => $commission['method'],
                'notes' => $commission['notes'],
                'qr_code' => '',
                'barcode' => '',
                'employee' => $commission['settled_by'],
            ],
            'items' => [[
                'code' => '1',
                'name' => 'عمولة'.($periodType !== '' ? ' '.$periodType : '').($commission['target'] !== '' ? ' — '.$commission['target'] : ''),
                'quantity' => 1,
                'unit' => $period,
                'price' => $fmt($amount),
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => $fmt($amount),
            ]],
            'commission' => $commission,
            'totals' => [
                'subtotal' => $fmt($amount),
                'discount' => '0.00',
                'grand_total' => $fmt($amount),
            ],
        ];
    }

    /** سطرُ بندٍ في مطبوعات الرواتب — المبلغُ السالب خصم. */
    private function salaryLine(string $name, float $amount): array
    {
        $formatted = number_format($amount, 2, '.', '');

        return [
            'code' => '',
            'name' => $name,
            'quantity' => 1,
            'unit' => '',
            'price' => $formatted,
            'discount' => '0.00',
            'tax' => '0.00',
            'total' => $formatted,
        ];
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                        إدارة العقارات                        ║
    // ╚══════════════════════════════════════════════════════════════╝

    /** جهةُ المستند في مطبوعات العقارات: المستأجر (صفٌّ في `clients`). */
    private function realEstateTenantParty($client): array
    {
        if (! $client) {
            return ['name' => '', 'tax_number' => '', 'address' => '', 'phone' => '', 'code' => ''];
        }

        $name = trim((string) ($client->display_name ?? ''))
            ?: trim((string) ($client->trade_name ?? ''))
            ?: trim(($client->first_name ?? '').' '.($client->last_name ?? ''));

        return [
            'name' => $name,
            'tax_number' => (string) ($client->tax_number ?? ''),
            'address' => collect([$client->street1 ?? null, $client->city ?? null, $client->region ?? null])
                ->filter(fn ($part) => trim((string) $part) !== '')->implode('، '),
            'phone' => (string) (($client->mobile ?? '') ?: ($client->phone ?? '')),
            'code' => (string) ($client->code ?? ''),
        ];
    }

    /**
     * ضريبةُ العقد مبلغاً — بقاعدة `ContractService` نفسِها: النسبةُ لقطةٌ على
     * العقد (`tax_rate`)، و`tax_included` يحدّد أهي داخل القيمة أم فوقها.
     *
     * @return array{rate: float, net: float, tax: float, gross: float}
     */
    private function realEstateContractTax($contract): array
    {
        $total = (float) ($contract->total_value ?? 0);
        $rate = (float) ($contract->tax_rate ?? 0);

        if ($rate <= 0) {
            return ['rate' => 0.0, 'net' => $total, 'tax' => 0.0, 'gross' => $total];
        }

        if ($contract->tax_included ?? false) {
            $net = round($total / (1 + $rate / 100), 2);

            return ['rate' => $rate, 'net' => $net, 'tax' => round($total - $net, 2), 'gross' => $total];
        }

        $tax = round($total * $rate / 100, 2);

        return ['rate' => $rate, 'net' => $total, 'tax' => $tax, 'gross' => round($total + $tax, 2)];
    }

    /** مفرداتُ العقارات — محروسةٌ فلا تُسقط المطبوعةَ قيمةٌ خارج القائمة. */
    private function realEstateLabel(string $list, $value): string
    {
        $vocabulary = RealEstateVocabulary::class;

        if ($value === null || $value === '' || ! defined($vocabulary.'::'.$list)) {
            return '';
        }

        return (string) (constant($vocabulary.'::'.$list)[(string) $value]['label'] ?? $value);
    }

    /**
     * «عقد إيجار عقاري» — `re_contracts`.
     *
     * البنودُ جدولُ الدفعات (قسطٌ لكلّ سطر، والملغى مستبعَد)، والمجالُ
     * المسمّى `contract.*` يحمل الطرفين والعين المؤجَّرة والمدّة والقيمة
     * والضريبة. والأعمدةُ تُقرأ بحراسة (`?? ''`): بعضُها يُضاف في مهاجراتٍ
     * لاحقة (`re_owners.tax_number`، `re_units.usage_type`).
     */
    private function normalizeRealEstateContract($doc, $business)
    {
        $fmt = fn ($v) => number_format((float) $v, 2, '.', '');
        $date = fn ($d) => $d ? LanguageSettings::formatDate($d) : '';

        $owner = $doc->effectiveOwner();
        $tenant = $doc->tenant;
        $unit = $doc->unit;
        $building = $unit->building ?? $doc->building;
        $profile = $tenant
            ? TenantProfile::where('client_id', $tenant->id)->first()
            : null;

        $currency = (string) (($doc->currency ?? '') ?: ($business['currency'] ?? ''));
        $tax = $this->realEstateContractTax($doc);

        $schedules = $doc->schedules->where('status', '!=', 'cancelled')->values();
        $rentSchedules = $schedules->where('kind', '!=', 'deposit');

        $items = [];
        foreach ($schedules as $schedule) {
            $kind = $this->realEstateLabel('SCHEDULE_KINDS', $schedule->kind);
            $status = $this->realEstateLabel('SCHEDULE_STATUSES', $schedule->live_status);
            $balance = round((float) $schedule->amount_due - (float) $schedule->amount_paid, 2);

            $items[] = [
                'code' => (string) ($schedule->installment_no ?: ''),
                'name' => trim($kind.($schedule->installment_no ? ' — القسط '.$schedule->installment_no : '')),
                'quantity' => 1,
                'unit' => $status,
                'price' => $fmt($schedule->amount_due),
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => $fmt($schedule->amount_due),
                'date' => $date($schedule->due_date),
                'description' => $kind,
                // حقولٌ مسمّاة للسطر: `{{item.due_date}}`، `{{item.balance}}`…
                'kind' => $kind,
                'due_date' => $date($schedule->due_date),
                'amount' => $fmt($schedule->amount_due),
                'paid' => $fmt($schedule->amount_paid),
                'balance' => $fmt($balance),
                'status' => $status,
            ];
        }

        $paid = (float) $schedules->sum(fn ($s) => (float) $s->amount_paid);
        $due = (float) $schedules->sum(fn ($s) => (float) $s->amount_due);

        $taxName = '';
        if ($tax['rate'] > 0 && ($doc->tax_id ?? null)) {
            try {
                $taxName = (string) (DB::table('tax_sittings')->where('id', $doc->tax_id)->value('name') ?? '');
            } catch (\Throwable $e) {
                $taxName = '';
            }
        }

        $terms = $doc->terms->values()->map(function ($term, $index) {
            $title = trim((string) $term->title);

            return ($index + 1).'. '.($title !== '' ? $title.': ' : '').trim((string) $term->body);
        })->implode("\n");

        $tenantParty = $this->realEstateTenantParty($tenant);

        $contract = [
            'code' => (string) $doc->code,
            'type' => (string) $doc->type_label,
            'status' => (string) ($doc->status_meta['label'] ?? ''),
            'owner_name' => (string) ($owner->display_name ?? $owner->name ?? ($business['name'] ?? '')),
            'owner_mobile' => (string) (($owner->mobile ?? '') ?: ($owner->phone ?? '')),
            'owner_id_number' => (string) ($owner->id_number ?? ''),
            'owner_tax_number' => (string) ($owner->tax_number ?? ''),
            'tenant_name' => $tenantParty['name'],
            'tenant_mobile' => $tenantParty['phone'],
            'tenant_id_number' => (string) ($profile->id_number ?? ''),
            'tenant_id_type' => $this->realEstateLabel('ID_TYPES', $profile->id_type ?? null),
            'tenant_nationality' => (string) ($profile->nationality ?? ''),
            'building' => (string) ($building->name ?? ''),
            'building_address' => collect([$building->city ?? null, $building->district ?? null, $building->address ?? null])
                ->filter(fn ($part) => trim((string) $part) !== '')->implode('، '),
            'unit' => (string) ($unit->full_label ?? ''),
            'unit_no' => (string) ($unit->unit_no ?? ''),
            'unit_type' => (string) ($unit->type_label ?? ''),
            'unit_usage' => $this->realEstateLabel('USAGE_TYPES', $unit->usage_type ?? null),
            'floor' => (string) ($unit->floor->label ?? ''),
            'area' => filled($unit->area_sqm ?? null) ? $this->qty($unit->area_sqm) : '',
            'start_date' => $date($doc->start_date),
            'end_date' => $date($doc->end_date),
            'duration' => $doc->duration_months ? (int) $doc->duration_months.' شهراً' : '',
            'annual_value' => $fmt($doc->annual_value),
            'total_value' => $fmt($tax['net']),
            'total_with_tax' => $fmt($tax['gross']),
            'tax' => $tax['rate'] > 0 ? $this->qty($tax['rate']).'%' : '',
            'tax_name' => $taxName,
            'tax_mode' => $tax['rate'] > 0 ? (($doc->tax_included ?? false) ? 'مشمولة في القيمة' : 'مضافة على القيمة') : '',
            'tax_amount' => $fmt($tax['tax']),
            'has_tax' => $tax['tax'] > 0 ? '1' : '',
            'deposit' => $fmt($doc->security_deposit),
            'has_deposit' => (float) $doc->security_deposit > 0 ? '1' : '',
            'payment_frequency' => $this->realEstateLabel('PAYMENT_FREQUENCIES', $doc->payment_frequency),
            'installments_count' => (string) $rentSchedules->count(),
            'currency' => $currency,
            'signed_at' => $date($doc->signed_at),
            'notes' => (string) ($doc->notes ?? ''),
            'terms' => $terms,
            'has_terms' => $terms !== '' ? '1' : '',
            'total_words' => NumberToWords::arabic($tax['gross'], $currency),
        ];

        return [
            'business' => $business,
            'customer' => $tenantParty,
            'document' => [
                'title' => 'عقد '.($contract['type'] ?: 'إيجار عقاري'),
                'number' => (string) ($doc->code ?: str_pad((string) $doc->id, 6, '0', STR_PAD_LEFT)),
                'date' => $contract['signed_at'] ?: $contract['start_date'],
                'due_date' => $contract['end_date'],
                'payment_method' => $contract['payment_frequency'],
                'notes' => $contract['notes'],
                'qr_code' => '',
                'barcode' => '',
            ],
            'items' => $items,
            'contract' => $contract,
            'totals' => [
                'subtotal' => $fmt($tax['net']),
                'discount' => '0.00',
                'shipping' => '0.00',
                'tax' => $fmt($tax['tax']),
                'grand_total' => $fmt($tax['gross']),
                'paid' => $fmt($paid),
                'remaining' => $fmt(max(0, $due - $paid)),
            ],
        ];
    }

    /**
     * «إيصال دفعة إيجار عقاري» — `re_payments`.
     *
     * البنودُ توزيعُ الدفعة على الأقساط (وسطرٌ للدفعة المقدّمة إن وُجدت)،
     * والمجالُ المسمّى `payment.*` يحمل تفصيلَها: إيجارٌ وضريبتُه وتأمينٌ
     * ومقدَّم. والضريبةُ بقاعدة قيد التحصيل (`PaymentService`): الأقساطُ
     * شاملةٌ ضريبتَها، فتُستخرج منها بنسبة العقد.
     */
    private function normalizeRealEstatePayment($doc, $business)
    {
        $fmt = fn ($v) => number_format((float) $v, 2, '.', '');
        $date = fn ($d) => $d ? LanguageSettings::formatDate($d) : '';

        $contract = $doc->contract;
        $tenant = $doc->client ?? $contract?->tenant;
        $unit = $contract?->unit;
        $building = $unit->building ?? $contract?->building;
        $currency = (string) (($doc->currency ?? '') ?: ($contract->currency ?? '') ?: ($business['currency'] ?? ''));

        $items = [];
        $rent = 0.0;
        $deposit = 0.0;

        foreach ($doc->allocations as $allocation) {
            $schedule = $allocation->schedule;
            $kind = $this->realEstateLabel('SCHEDULE_KINDS', $schedule->kind ?? null);

            if (($schedule->kind ?? null) === 'deposit') {
                $deposit += (float) $allocation->amount;
            } else {
                $rent += (float) $allocation->amount;
            }

            $items[] = [
                'code' => (string) (($schedule->installment_no ?? '') ?: ''),
                'name' => trim($kind.(($schedule->installment_no ?? 0) ? ' — القسط '.$schedule->installment_no : '')),
                'quantity' => 1,
                'unit' => $kind,
                'price' => $fmt($allocation->amount),
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => $fmt($allocation->amount),
                'date' => $date($schedule->due_date ?? null),
                'description' => $kind,
                'kind' => $kind,
                'due_date' => $date($schedule->due_date ?? null),
                'amount' => $fmt($allocation->amount),
            ];
        }

        $advance = (float) ($doc->unallocated_amount ?? 0);

        if ($advance > 0) {
            $items[] = [
                'code' => '', 'name' => 'دفعة مقدّمة', 'quantity' => 1, 'unit' => 'مقدَّم',
                'price' => $fmt($advance), 'discount' => '0.00', 'tax' => '0.00', 'total' => $fmt($advance),
                'date' => '', 'description' => 'دفعة مقدّمة', 'kind' => 'مقدَّم', 'due_date' => '', 'amount' => $fmt($advance),
            ];
        }

        // مبلغُ الضريبة المخزَّن على الدفعة إن وُجد عمودُه، وإلّا المستخرَج بنسبة العقد
        $rate = (float) ($contract->tax_rate ?? 0) / 100;
        $taxAmount = isset($doc->tax_amount)
            ? (float) $doc->tax_amount
            : ($rate > 0 ? round($rent - $rent / (1 + $rate), 2) : 0.0);

        $method = (($doc->payment_method_id ?? null) ? $this->paymentMethodName($doc->payment_method_id) : '')
            ?: (string) $doc->method_label;
        $method = $method === '—' ? '' : $method;
        $tenantParty = $this->realEstateTenantParty($tenant);

        $payment = [
            'code' => (string) $doc->code,
            'date' => $date($doc->payment_date),
            'status' => (string) ($doc->status_meta['label'] ?? ''),
            'is_cancelled' => ($doc->posting_status ?? '') === 'cancelled' ? '1' : '',
            'contract_code' => (string) ($contract->code ?? ''),
            'tenant_name' => $tenantParty['name'],
            'tenant_mobile' => $tenantParty['phone'],
            'building' => (string) ($building->name ?? ''),
            'unit' => (string) ($unit->full_label ?? ''),
            'method' => $method,
            'reference' => (string) ($doc->reference_no ?? ''),
            'treasury' => (string) ($doc->treasuryAccount->name ?? ''),
            'amount' => $fmt($doc->amount),
            'amount_words' => NumberToWords::arabic((float) $doc->amount, $currency),
            'rent' => $fmt($rent - $taxAmount),
            'tax_amount' => $fmt($taxAmount),
            'has_tax' => $taxAmount > 0 ? '1' : '',
            'deposit' => $fmt($deposit),
            'has_deposit' => $deposit > 0 ? '1' : '',
            'advance' => $fmt($advance),
            'has_advance' => $advance > 0 ? '1' : '',
            'commission' => $fmt($doc->commission_amount ?? 0),
            'currency' => $currency,
            'received_by' => (string) ($doc->creator->name ?? ''),
            'notes' => (string) ($doc->notes ?? ''),
            'contract_remaining' => $contract ? $fmt($contract->outstanding()) : '0.00',
        ];

        return [
            'business' => $business,
            'customer' => $tenantParty,
            'document' => [
                'title' => 'إيصال دفعة إيجار',
                'number' => (string) ($doc->code ?: str_pad((string) $doc->id, 6, '0', STR_PAD_LEFT)),
                'date' => $payment['date'],
                'due_date' => '',
                'payment_method' => $method,
                'notes' => $payment['notes'],
                'qr_code' => '',
                'barcode' => '',
                'employee' => $payment['received_by'],
            ],
            'items' => $items,
            'payment' => $payment,
            'totals' => [
                'subtotal' => $fmt((float) $doc->amount - $taxAmount),
                'discount' => '0.00',
                'shipping' => '0.00',
                'tax' => $fmt($taxAmount),
                'grand_total' => $fmt($doc->amount),
                'paid' => $fmt($doc->amount),
                'remaining' => $payment['contract_remaining'],
            ],
        ];
    }

    /**
     * اسمُ طريقة الدفع لا رقمُها.
     *
     * الفاتورةُ وسندُ القبض ودفعةُ المورد يخزّنون معرّفَ صفٍّ من «طرق الدفع»
     * (`payment_methods`)، فكانت المطبوعة تقول «طريقة الدفع: 1». والنصُّ
     * المخزَّن (بياناتٌ أقدم) يمرّ كما هو.
     */
    private function paymentMethodName($value, string $fallback = ''): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if ($value instanceof \BackedEnum) {
            return method_exists($value, 'label') ? (string) $value->label() : (string) $value->value;
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        static $names = null;
        $names ??= PaymentMethod::pluck('name', 'id')->all();

        return (string) ($names[(int) $value] ?? $fallback);
    }

    /**
     * الكمّية كما تُقرأ لا كما تُخزّن.
     *
     * عمود الكمّية عشريّ بأربع منازل، فكان يخرج في المطبوعة
     * «200.0000» — أربعة أصفار لا تعني شيئاً وتوسّع العمود. والكسر
     * الحقيقي يبقى: «1.5» تُطبع «1.5» لا «2».
     */
    private function qty($value): string
    {
        $number = (float) $value;

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * يملأ `document.barcode` من رقم المستند إن كان فارغاً.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withCodes(array $payload): array
    {
        $number = (string) ($payload['document']['number'] ?? '');

        if ($number === '') {
            return $payload;
        }

        if (empty($payload['document']['barcode'])) {
            $payload['document']['barcode'] = $this->generateBarcodeSvg($number);
        }

        /* رمز الاستجابة كان يُملأ في فاتورة المبيعات والإشعار الدائن
           وحدهما؛ ومطبوعات المشتريات والسندات والمخازن تخرج بموضع
           رمزٍ فارغ. وما يملأه المُطبِّع بنفسه يُترك كما هو. */
        if (empty($payload['document']['qr_code'])) {
            $payload['document']['qr_code'] = $this->generateQrCodeSvg(implode('|', [
                (string) ($payload['business']['name'] ?? ''),
                (string) ($payload['business']['tax_number'] ?? ''),
                $number,
                (string) ($payload['document']['date'] ?? ''),
                (string) ($payload['totals']['grand_total'] ?? '0.00'),
                (string) ($payload['totals']['tax'] ?? '0.00'),
            ]));
        }

        return $payload;
    }

    /**
     * باركود Code 128 كمسار بيانات يقرأه `<img src>`.
     *
     * و`Code 128` لا يرمِّز إلا ASCII المطبوع، فأي حرفٍ عربيّ في رقم
     * المستند يُسقط قبل الترميز لا بعده — وإلّا رمى المولِّد وخرجت
     * المطبوعة بلا باركود.
     */
    /**
     * ضريبةُ البند وإجماليُّه — مبلغاً لا نسبةً.
     *
     * **ما كان** — أربعةُ مواضعَ تملأ عمود «الضريبة» في كل مطبوعة، وكلُّها
     * خاطئة بطريقتين:
     *
     *   • `$item->tax` (الفاتورة والمرتجع والطلب): **لا عمودَ بهذا الاسم**
     *     في `invoice_items` — الأعمدة `tax_1` و`tax_2`. فالقيمة `null`
     *     وتُطبع `0.00` في كل بندٍ من كل فاتورة، ولو كانت الضريبة ١٥٪.
     *   • `$item->tax_1` (عرض السعر والإشعار الدائن): العمودُ **نسبةٌ** لا
     *     مبلغ. فبندٌ بـ١٥٪ على مئةٍ يطبع «15.00» في خانة المبلغ بدل
     *     «15.00»… تصادفَ تطابقُهما هنا، لكنّ بندَ الألف يطبع «15.00»
     *     والصحيح مئةٌ وخمسون. رقمٌ معقولُ الشكل وخاطئ.
     *
     * **وما صار** — `DocumentTaxCalculator::line()`، وهي نفسُها التي تحسب
     * بها الشاشاتُ والقيود: تقرأ معرّف الضريبة لتعرف أمتضمَّنةٌ هي أم
     * مضافة، وتعود إلى النسبة المخزَّنة للبنود القديمة بلا معرّف. وإجماليُّ
     * البند يُؤخذ منها كذلك، فلا يختلف المطبوعُ عن المحسوب.
     *
     * @return array{tax: string, total: string}
     */
    private function itemTaxAndTotal($item): array
    {
        $line = DocumentTaxCalculator::line(
            (float) ($item->quantity ?? 0),
            (float) ($item->unit_price ?? 0),
            (float) ($item->discount ?? 0),
            [$item->tax_1_id ?? null, $item->tax_2_id ?? null],
            [(float) ($item->tax_1 ?? 0), (float) ($item->tax_2 ?? 0)]
        );

        /* الإجماليُّ **قبل الضريبة** (`base` لا `total`): كتلةُ الإجماليات
           أسفلَ المطبوعة تضيف الضريبةَ سطراً مستقلّاً، فبندٌ يحمل ضريبتَه
           داخله يجعل الأرقام لا تجتمع في عين القارئ — ٣٢٤ + ٤٨٫٦٠ = ٣٧٢٫٦٠
           بينما يقول سطرُ البند ٣٧٢٫٦٠ أصلاً. وهو كذلك ما يحفظه
           `invoice_items.total`، فالمطبوعُ يطابق المخزَّن. */
        return [
            'tax' => number_format($line['tax'], 2, '.', ''),
            'total' => number_format($line['base'], 2, '.', ''),
        ];
    }

    private function generateBarcodeSvg(string $value): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', '', $value);

        if ($clean === null || trim($clean) === '') {
            return '';
        }

        try {
            /* PNG لا SVG. السببُ أن SVG داخل `<img>` يقرؤه المتصفّح ولا
               يقرؤه TCPDF، وصورةٌ يعجز عن قياسها تُسقط **توليد الملف
               كلَّه** — فيصل البريد بلا مستندٍ مرفق لأجل باركود. انظر
               `App\Services\Sales\SalesDocumentMailer::pdf()`،
               ونظيرَتها في `generateQrCodeSvg()`. */
            $png = new BarcodeGeneratorPNG;

            return 'data:image/png;base64,'.base64_encode(
                $png->getBarcode($clean, $png::TYPE_CODE_128, 2, 42)
            );
        } catch (\Throwable $e) {
            Log::warning('تعذّر توليد الباركود', [
                'value' => $clean,
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Generate Svg QR Code.
     */
    private function generateQrCodeSvg($text)
    {
        if (trim((string) $text, " |\t\n\r\0\x0B") === '') {
            return '';
        }

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(120, 1),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);

            /* المُرجَع markup خام لـSVG، وكل القوالب تضعه في
               `<img src="…">` — فالصورة مكسورة دائماً ولم يظهر
               رمز الاستجابة في مطبوعة قطّ. يُعاد مسار بيانات يقرأه
               `src` كما هو. */
            /* `UTF-8` لازم: المُرمِّز يفترض ISO-8859-1، والنصّ
               يبدأ باسم المنشأة عربيّاً — فيرمي
               `Could not encode content to ISO-8859-1`، والالتقاط أدناه
               يبتلعه فيعود فارغاً. فلم يظهر رمز استجابة في
               مطبوعة قطّ، ولا أثر لذلك في أي سجلّ. */
            $svg = 'data:image/svg+xml;base64,'
                .base64_encode($writer->writeString($text, 'UTF-8'));

            /* و PNG متى أمكن. السببُ أن SVG داخل `<img>` يقرؤه المتصفّح
               ولا يقرؤه TCPDF: `writeHTML` يحوّل مسارَ البيانات إلى صورةٍ
               خام ثم يعجز عن قياسها، ويرمي خطأً يُسقط **توليد الملف كلَّه**
               — فيخرج البريد بلا مستندٍ مرفق. انظر
               `App\Services\Sales\SalesDocumentMailer::pdf()`.

               و PNG يقرؤه الاثنان. والمقاس ٤٠٠ بكسل لمربّعٍ يُطبع في نحو
               ٣٠ مم — أي ما يفوق ٣٠٠ نقطة/بوصة، فلا يفقد المسحُ دقّته. */
            return $this->qrCodePng($text) ?? $svg;
        } catch (\Throwable $e) {
            Log::warning('تعذّر توليد رمز الاستجابة', [
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * رمزُ الاستجابة PNG بمسار بيانات — أو `null` فيُستعمل SVG.
     *
     * `TCPDF2DBarcode` يرسمه بـGD، وهي مثبَّتةٌ هنا بخلاف Imagick التي
     * تطلبها واجهةُ PNG في `BaconQrCode`.
     */
    private function qrCodePng(string $text): ?string
    {
        try {
            /* الوسيطان **مقاسُ الوحدة الواحدة** بالبكسل لا مقاسُ الصورة:
               تمريرُ ٤٠٠ يطلب مربّعاً بأربعمئة بكسل لكل وحدةٍ من نحو
               ثلاثين — أي عشراتِ آلاف البكسلات ضلعاً، فتنفد الذاكرة قبل
               أن يكتمل. وثمانيةٌ تعطي نحو ٢٤٠ بكسلاً ضلعاً، وهي تكفي
               مربّعاً يُطبع في ٣٠ مم. */
            $png = (new \TCPDF2DBarcode($text, 'QRCODE,M'))->getBarcodePngData(8, 8, [0, 0, 0]);

            return $png ? 'data:image/png;base64,'.base64_encode($png) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get realistic Demo Data.
     */
    public function getDemoData(string $documentType)
    {
        $business = [
            'name' => 'مؤسسة الحلول الذكية للتقنية والتجارة',
            'logo' => '',
            'phone' => '+966 509 992 803',
            'email' => 'contact@smartsolution.sa',
            'tax_number' => '300000000000003',
            'commercial_registration' => '1010123456',
            'address' => 'الرياض - طريق الملك فهد - حي العليا - المملكة العربية السعودية',
            'website' => 'www.smartsolution.sa',
            'currency' => 'SAR',
        ];

        $customer = [
            'name' => 'شركة خالد بن عبدالله القحطاني وشركائه',
            'tax_number' => '310928374839281',
            'phone' => '0501234567',
            'address' => 'الدمام - حي الفيصلية - شارع الملك عبدالعزيز',
            'code' => 'CL-0918',
        ];

        $items = [
            [
                'code' => 'P-1002',
                'name' => 'جهاز حاسب آلي مكتبي Dell Optiplex Core i7',
                'quantity' => 2,
                'unit' => 'جهاز',
                'price' => '2500.00',
                'discount' => '100.00',
                'tax' => '720.00',
                'total' => '4900.00',
            ],
            [
                'code' => 'P-1045',
                'name' => 'طابعة ليزر HP LaserJet Enterprise M506',
                'quantity' => 1,
                'unit' => 'طابعة',
                'price' => '1800.00',
                'discount' => '0.00',
                'tax' => '270.00',
                'total' => '1800.00',
            ],
            [
                'code' => 'P-2009',
                'name' => 'فأرة لاسلكية ولوحة مفاتيح Logitech MK270',
                'quantity' => 5,
                'unit' => 'مجموعة',
                'price' => '120.00',
                'discount' => '10.00',
                'tax' => '88.50',
                'total' => '590.00',
            ],
        ];

        // QR Code demo
        $qrCode = $this->generateQrCodeSvg('مؤسسة الحلول الذكية للتقنية والتجارة|300000000000003|'.now()->format('Y-m-d H:i').'|8383.50|1078.50');

        $document = [
            'title' => 'فاتورة مبيعات ضريبية',
            'number' => 'INV-2026-00918',
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(15)->format('Y-m-d'),
            'payment_method' => 'حوالة بنكية / مدى',
            'notes' => 'يرجى سداد القيمة خلال الفترة المحددة لتجنب إيقاف الخدمات. نشكر لكم تعاونكم معنا.',
            'qr_code' => $qrCode,
            'barcode' => '',
        ];

        // الشحن موجودٌ في المعاينة بقيمةٍ حقيقية حتى يرى المصمّم سطره:
        // القوالب تُظهره بشرطٍ، فصفرُه هنا يعني اختفاءه من المعاينة كلّها
        $totals = [
            'subtotal' => '7290.00',
            'discount' => '110.00',
            'shipping' => '110.00',
            'tax' => '1078.50',
            'grand_total' => '8368.50',
            'paid' => '3000.00',
            'remaining' => '5368.50',
        ];

        // Customize per document type if needed
        if ($documentType === 'receipt_voucher') {
            $document['title'] = 'سند قبض مالي';
            $totals['shipping'] = '0.00';
            $totals['grand_total'] = '3500.00';
            $totals['paid'] = '3500.00';
            $totals['remaining'] = '0.00';
            $items = [];
        } elseif ($documentType === 'payment_voucher') {
            $document['title'] = 'سند صرف مالي';
            $totals['shipping'] = '0.00';
            $totals['grand_total'] = '1850.00';
            $totals['paid'] = '1850.00';
            $totals['remaining'] = '0.00';
            $customer['name'] = 'مؤسسة التوريدات السريعة المحدودة';
            $items = [];
        } elseif ($documentType === 'quote') {
            $document['title'] = 'عرض سعر فني ومالي';
            $totals['paid'] = '0.00';
            $totals['remaining'] = '8368.50';
        } elseif ($documentType === 'sales_order') {
            $document['title'] = 'أمر بيع';
            $totals['paid'] = '0.00';
            $totals['remaining'] = '8368.50';
        } elseif ($documentType === 'salary_slip' || $documentType === 'payroll_sheet') {
            // معاينةُ قوالب الرواتب ببنودٍ تشبه ما تُطبعه فعلاً — لا بأصناف فاتورة
            $sheet = $documentType === 'payroll_sheet';
            $document['title'] = $sheet ? 'كشف مسير رواتب' : 'قسيمة راتب';
            $document['number'] = $sheet ? '000012' : 'SLP-00042';
            $document['date'] = now()->format('Y-m');
            $document['notes'] = $sheet ? 'عدد الموظفين في المسير: 3 — عدد القسائم: 3' : 'المسير: مسير رواتب '.now()->format('Y-m');
            $customer = [
                'name' => $sheet ? 'مسير رواتب '.now()->format('Y-m') : 'أحمد محمد العتيبي',
                'tax_number' => '',
                'phone' => $sheet ? '' : '0501234567',
                'address' => $sheet ? 'الفرع الرئيسي / كل الأقسام' : 'قسم المبيعات / مندوب مبيعات',
                'code' => $sheet ? '12' : 'EMP-0007',
            ];
            $line = fn ($name, $amount) => ['code' => '', 'name' => $name, 'quantity' => 1, 'unit' => '', 'price' => $amount, 'discount' => '0.00', 'tax' => '0.00', 'total' => $amount];
            $items = $sheet
                ? [$line('أحمد محمد العتيبي (أساسي 5000.00 · إجمالي 6000.00)', '6000.00'), $line('سارة علي القحطاني (أساسي 4500.00 · إجمالي 5200.00 · خصم 200.00)', '5000.00'), $line('خالد سعد الدوسري (أساسي 3000.00 · إجمالي 3500.00)', '3500.00')]
                : [$line('الراتب الأساسي', '5000.00'), $line('بدل سكن', '750.00'), $line('بدل نقل', '250.00'), $line('خصم تأخير', '-100.00')];
            $totals = $sheet
                ? ['subtotal' => '14700.00', 'discount' => '200.00', 'shipping' => '0.00', 'tax' => '0.00', 'grand_total' => '14500.00', 'paid' => '0.00', 'remaining' => '0.00']
                : ['subtotal' => '6000.00', 'discount' => '100.00', 'shipping' => '0.00', 'tax' => '0.00', 'grand_total' => '5900.00', 'paid' => '0.00', 'remaining' => '0.00'];
            $document['qr_code'] = '';
        }

        /* كشف الحساب سجلّ حركةٍ لا مستندٌ ببنود: أسطره تاريخٌ
           ومرجعٌ وبيانٌ ومدينٌ ودائنٌ ورصيدٌ جارٍ. وبدونها يُعاَين القالب
           بجدولٍ فارغٍ وأرصدةٍ خالية فلا يرى المصمّم ما يصمّمه. */
        $statement = [];

        if (in_array($documentType, ['supplier_statement', 'client_statement'], true)) {
            $supplier = $documentType === 'supplier_statement';

            $document['title'] = $supplier ? 'كشف حساب مورّد' : 'كشف حساب عميل';
            $document['number'] = $customer['code'];
            $document['notes'] = $supplier
                ? 'ذمم المورّدين — الحساب رقم 2101'
                : 'ذمم العملاء — الحساب رقم 1301';

            $items = [
                ['date' => '2026-07-02', 'reference' => 'INV-2026-00841',
                    'description' => 'فاتورة مبيعات — أجهزة حاسب وملحقاتها',
                    'debit' => '12500.00', 'credit' => '0.00', 'balance' => '15700.00'],
                ['date' => '2026-07-14', 'reference' => 'RCV-2026-00219',
                    'description' => 'سند قبض — حوالة بنكية',
                    'debit' => '0.00', 'credit' => '8000.00', 'balance' => '7700.00'],
                ['date' => '2026-08-03', 'reference' => 'INV-2026-00902',
                    'description' => 'فاتورة مبيعات — طابعات وقرطاسية',
                    'debit' => '4300.00', 'credit' => '0.00', 'balance' => '12000.00'],
                ['date' => '2026-08-19', 'reference' => 'CRN-2026-00034',
                    'description' => 'إشعار دائن — مرتجع صنفٍ تالف',
                    'debit' => '0.00', 'credit' => '1250.00', 'balance' => '10750.00'],
            ];

            $statement = [
                'from' => '2026-07-01',
                'to' => '2026-08-31',
                'opening' => '3200.00',
                'debit' => '16800.00',
                'credit' => '9250.00',
                'closing' => '10750.00',
                'direction' => $supplier ? 'دائن' : 'مدين',
                'count' => count($items),
            ];

            $totals = [
                'subtotal' => '0.00', 'discount' => '0.00', 'shipping' => '0.00',
                'tax' => '0.00', 'grand_total' => '10750.00',
                'paid' => '0.00', 'remaining' => '0.00',
            ];
        }

        /* معاينةُ عقد العمل وسندَي السلفة والعمولة بحقولها المسمّاة
           (`contract.*` / `advance.*` / `commission.*`) — وإلّا عوينت
           قوالبُها بجدول أصناف فاتورة وإجمالياتٍ فارغة. */
        $named = [];
        $hrEmployee = [
            'name' => 'أحمد محمد العتيبي', 'tax_number' => '', 'phone' => '0501234567',
            'address' => 'قسم المبيعات / مندوب مبيعات', 'code' => 'EMP-0007',
        ];
        $hrLine = fn ($name, $unit, $amount) => ['code' => '', 'name' => $name, 'quantity' => 1, 'unit' => $unit, 'price' => $amount, 'discount' => '0.00', 'tax' => '0.00', 'total' => $amount];
        $hrTotals = fn ($amount) => ['subtotal' => $amount, 'discount' => '0.00', 'shipping' => '0.00', 'tax' => '0.00', 'grand_total' => $amount, 'paid' => '0.00', 'remaining' => '0.00'];

        if ($documentType === 'contract') {
            $customer = $hrEmployee;
            $document['title'] = 'عقد عمل';
            $document['number'] = 'CTR-00015';
            $document['payment_method'] = 'شهري';
            $document['qr_code'] = '';
            $items = [$hrLine('الراتب الأساسي', 'مستحق', '5000.00'), $hrLine('بدل سكن', 'مستحق', '1250.00'), $hrLine('بدل نقل', 'مستحق', '500.00'), $hrLine('التأمينات الاجتماعية', 'مستقطع', '-562.50')];
            $totals = $hrTotals('6750.00');
            $named['contract'] = [
                'basic_salary' => '5,000.00', 'total_additions' => '1,750.00', 'gross_salary' => '6,750.00',
                'total_deductions' => '562.50', 'net_salary' => '6,187.50', 'has_deductions' => '1',
                'terms' => "المسمّى الوظيفي: مندوب مبيعات\nالقسم: المبيعات\nمدة العقد: 12 شهر\nتاريخ المباشرة: ".now()->format('Y-m-d'),
            ];
        } elseif ($documentType === 'salary_advance') {
            $customer = $hrEmployee;
            $document['title'] = 'سند سلفة';
            $document['number'] = 'ADV-000025';
            $document['payment_method'] = 'الخزينة الرئيسية';
            $document['notes'] = 'سلفة لظرفٍ عائلي';
            $document['qr_code'] = '';
            $start = now()->startOfMonth();
            $items = [
                $hrLine('قسط '.$start->format('Y-m'), 'مسدَّد', '1000.00'),
                $hrLine('قسط '.$start->copy()->addMonth()->format('Y-m'), 'مستحق', '1000.00'),
                $hrLine('قسط '.$start->copy()->addMonths(2)->format('Y-m'), 'مستحق', '1000.00'),
            ];
            $totals = $hrTotals('3000.00');
            $named['advance'] = [
                'type' => 'مجدولة على 3 أقساط', 'stage' => 'قيد السداد', 'requested_date' => now()->format('Y-m-d'),
                'disbursed_date' => now()->format('Y-m-d'), 'approver' => 'مدير الموارد البشرية', 'treasury' => 'الخزينة الرئيسية',
                'installment_amount' => '1000.00', 'amount' => '3000.00', 'repaid' => '1000.00', 'remaining' => '2000.00', 'has_repaid' => '1',
            ];
        } elseif ($documentType === 'commission_payment') {
            $customer = $hrEmployee;
            $document['title'] = 'سند صرف عمولة';
            $document['number'] = 'EXP-00412';
            $document['payment_method'] = 'قيد صرف';
            $document['notes'] = '';
            $document['qr_code'] = '';
            $period = now()->startOfMonth()->format('Y-m-d').' — '.now()->endOfMonth()->format('Y-m-d');
            $items = [$hrLine('عمولة شهرية — هدف مبيعات الربع', $period, '1450.00')];
            $totals = $hrTotals('1450.00');
            $named['commission'] = [
                'target' => 'هدف مبيعات الربع', 'target_value' => '50000.00', 'period_type' => 'شهرية', 'period' => $period,
                'paid_date' => now()->format('Y-m-d'), 'method' => 'قيد صرف', 'journal_number' => 'JE-3480', 'journal_status' => 'معتمد',
                'settled_by' => 'المحاسب', 'amount' => '1450.00',
            ];
        }

        /* «تصميم الموارد البشرية» — حقولُه المسمّاة في المعاينة كذلك
           (`slip.*` / `payroll.*` / `{{item.basic}}`…)، وإلّا عوينت فارغة. */
        if ($documentType === 'salary_slip') {
            $kinds = ['earning', 'earning', 'earning', 'deduction'];
            $items = array_map(function ($line, $kind) {
                return $line + ['kind' => $kind, 'amount' => number_format(abs((float) $line['total']), 2, '.', ',')];
            }, $items, $kinds);
            $items[] = ['code' => '', 'name' => 'تأمينات اجتماعية', 'quantity' => 1, 'unit' => '', 'price' => '-487.50', 'discount' => '0.00', 'tax' => '0.00', 'total' => '-487.50', 'kind' => 'deduction', 'amount' => '487.50'];
            $named['slip'] = [
                'employee_name' => 'أحمد محمد العتيبي', 'employee_code' => 'EMP-0007', 'job_title' => 'مندوب مبيعات',
                'department' => 'المبيعات', 'branch' => 'الفرع الرئيسي', 'bank_name' => 'مصرف الراجحي',
                'iban' => 'SA03 8000 0000 6080 1016 7519', 'payroll_name' => 'مسير رواتب '.now()->format('Y-m'),
                'period' => now()->format('Y-m'), 'period_from' => now()->startOfMonth()->format('Y-m-d'),
                'period_to' => now()->endOfMonth()->format('Y-m-d'), 'payment_date' => now()->endOfMonth()->format('Y-m-d'),
                'status' => 'نشطة', 'basic' => '5,000.00', 'total_earnings' => '6,000.00', 'total_deductions' => '1,087.50',
                'insurance' => '487.50', 'has_insurance' => '1', 'advances' => '500.00', 'has_advances' => '1',
                'net' => '4,912.50', 'net_words' => NumberToWords::arabic(4912.50, 'SAR'),
                'currency' => 'SAR', 'notes' => '', 'has_deductions' => '1',
            ];
        } elseif ($documentType === 'payroll_sheet') {
            $rows = [
                ['أحمد محمد العتيبي', 'EMP-0007', 'المبيعات', '5,000.00', '1,000.00', '6,000.00', '487.50', '500.00', '100.00', '4,912.50'],
                ['سارة علي القحطاني', 'EMP-0012', 'المحاسبة', '4,500.00', '700.00', '5,200.00', '438.75', '0.00', '200.00', '4,561.25'],
                ['خالد سعد الدوسري', 'EMP-0019', 'المستودعات', '3,000.00', '500.00', '3,500.00', '292.50', '0.00', '0.00', '3,207.50'],
            ];
            $items = array_map(fn ($r) => [
                'code' => $r[1], 'name' => $r[0], 'quantity' => 1, 'unit' => '', 'price' => $r[5], 'discount' => '0.00', 'tax' => '0.00', 'total' => $r[9],
                'emp_name' => $r[0], 'emp_code' => $r[1], 'department' => $r[2], 'job_title' => '', 'basic' => $r[3], 'allowances' => $r[4],
                'gross' => $r[5], 'insurance' => $r[6], 'advances' => $r[7], 'other_deductions' => $r[8], 'net' => $r[9],
            ], $rows);
            $named['payroll'] = [
                'name' => 'مسير رواتب '.now()->format('Y-m'), 'code' => '000012',
                'period_from' => now()->startOfMonth()->format('Y-m-d'), 'period_to' => now()->endOfMonth()->format('Y-m-d'),
                'branch' => 'الفرع الرئيسي', 'department' => 'كل الأقسام', 'job_title' => '', 'status' => 'نشط (موافق عليه)',
                'approved_at' => now()->format('Y-m-d'), 'employees_count' => '3', 'members_count' => '3',
                'total_basic' => '12,500.00', 'total_allowances' => '2,200.00', 'total_gross' => '14,700.00',
                'total_insurance' => '1,218.75', 'total_advances' => '500.00', 'total_other' => '300.00',
                'total_deductions' => '2,018.75', 'total_net' => '12,681.25',
                'net_words' => NumberToWords::arabic(12681.25, 'SAR'),
            ];
        } elseif ($documentType === 'contract') {
            $named['contract'] += [
                'code' => 'CTR-00015', 'employee_name' => 'أحمد محمد العتيبي', 'employee_code' => 'EMP-0007',
                'job_title' => 'مندوب مبيعات', 'job_level' => 'المستوى الثاني', 'department' => 'المبيعات', 'branch' => 'الفرع الرئيسي',
                'start_date' => now()->format('Y-m-d'), 'end_date' => now()->addYear()->subDay()->format('Y-m-d'), 'duration' => '12 شهر',
                'join_date' => now()->format('Y-m-d'), 'probation_end_date' => now()->addDays(90)->format('Y-m-d'),
                'contract_date' => now()->format('Y-m-d'), 'receiving_cycle' => 'شهري', 'currency' => 'SAR', 'description' => '',
                'nationality' => 'السعودية', 'gosi_number' => '1234567890', 'phone' => '0501234567', 'email' => 'ahmed@example.com',
                'net_salary_words' => NumberToWords::arabic(6187.50, 'SAR'), 'has_probation' => '1', 'has_end_date' => '1',
            ];
        } elseif ($documentType === 'salary_advance') {
            $named['advance']['amount_words'] = NumberToWords::arabic(3000, 'SAR');
        } elseif ($documentType === 'commission_payment') {
            $named['commission']['amount_words'] = NumberToWords::arabic(1450, 'SAR');
        }

        /* معاينةُ مطبوعتَي العقارات بحقولها المسمّاة (`contract.*` / `payment.*`)
           وبجدول أقساطٍ لا بأصناف فاتورة. */
        if ($documentType === 'realestate_contract' || $documentType === 'realestate_receipt') {
            $customer = [
                'name' => 'عبدالله سعد الحربي', 'tax_number' => '', 'phone' => '0551234567',
                'address' => 'الرياض، حي النرجس', 'code' => 'C-01105',
            ];
            $document['qr_code'] = '';
            $document['notes'] = '';
            $start = now()->startOfMonth();
            $reRow = fn ($no, $kind, $due, $amount, $status) => [
                'code' => (string) $no, 'name' => $kind.($no ? ' — القسط '.$no : ''), 'quantity' => 1, 'unit' => $status,
                'price' => $amount, 'discount' => '0.00', 'tax' => '0.00', 'total' => $amount, 'date' => $due, 'description' => $kind,
                'kind' => $kind, 'due_date' => $due, 'amount' => $amount, 'paid' => $status === 'مدفوعة' ? $amount : '0.00',
                'balance' => $status === 'مدفوعة' ? '0.00' : $amount, 'status' => $status,
            ];

            if ($documentType === 'realestate_contract') {
                $document['title'] = 'عقد إيجار تجاري';
                $document['number'] = 'REC-000017';
                $document['payment_method'] = 'ربع سنوي';
                $document['due_date'] = $start->copy()->addYear()->subDay()->format('Y-m-d');
                $items = [
                    $reRow('', 'تأمين', $start->format('Y-m-d'), '9000.00', 'مدفوعة'),
                    $reRow(1, 'إيجار', $start->format('Y-m-d'), '51750.00', 'مدفوعة'),
                    $reRow(2, 'إيجار', $start->copy()->addMonths(3)->format('Y-m-d'), '51750.00', 'مجدولة'),
                    $reRow(3, 'إيجار', $start->copy()->addMonths(6)->format('Y-m-d'), '51750.00', 'مجدولة'),
                    $reRow(4, 'إيجار', $start->copy()->addMonths(9)->format('Y-m-d'), '51750.00', 'مجدولة'),
                ];
                $totals = ['subtotal' => '180000.00', 'discount' => '0.00', 'shipping' => '0.00', 'tax' => '27000.00',
                    'grand_total' => '207000.00', 'paid' => '60750.00', 'remaining' => '155250.00'];
                $named['contract'] = [
                    'code' => 'REC-000017', 'type' => 'إيجار تجاري', 'status' => 'ساري',
                    'owner_name' => 'محمد بن فهد العتيبي', 'owner_mobile' => '0505550101', 'owner_id_number' => '1012345678',
                    'owner_tax_number' => '310123456700003',
                    'tenant_name' => $customer['name'], 'tenant_mobile' => $customer['phone'], 'tenant_id_number' => '1098765432',
                    'tenant_id_type' => 'هوية وطنية', 'tenant_nationality' => 'سعودي',
                    'building' => 'برج النخيل', 'building_address' => 'الرياض، العليا، طريق الملك فهد',
                    'unit' => 'محل S1 — برج النخيل', 'unit_no' => 'S1', 'unit_type' => 'محل', 'unit_usage' => 'تجاري',
                    'floor' => 'الدور الأرضي', 'area' => '120',
                    'start_date' => $start->format('Y-m-d'), 'end_date' => $start->copy()->addYear()->subDay()->format('Y-m-d'),
                    'duration' => '12 شهراً', 'annual_value' => '180000.00', 'total_value' => '180000.00',
                    'total_with_tax' => '207000.00', 'tax' => '15%', 'tax_name' => 'ضريبة القيمة المضافة',
                    'tax_mode' => 'مضافة على القيمة', 'tax_amount' => '27000.00', 'has_tax' => '1',
                    'deposit' => '9000.00', 'has_deposit' => '1', 'payment_frequency' => 'ربع سنوي', 'installments_count' => '4',
                    'currency' => 'SAR', 'signed_at' => $start->format('Y-m-d'), 'notes' => '',
                    'terms' => "1. الاستخدام: تُستعمل العين للغرض المتّفق عليه ولا يجوز تغييره إلا بموافقة المؤجِّر كتابةً.\n2. السداد: يلتزم المستأجر بسداد الأقساط في مواعيدها المبيّنة في جدول الدفعات.\n3. الصيانة: الصيانة الدورية على المستأجر، والإنشائية على المؤجِّر.",
                    'has_terms' => '1',
                    'total_words' => NumberToWords::arabic(207000, 'SAR'),
                ];
            } else {
                $document['title'] = 'إيصال دفعة إيجار';
                $document['number'] = 'REP-000039';
                $document['payment_method'] = 'تحويل بنكي';
                $document['due_date'] = '';
                $items = [
                    $reRow('', 'تأمين', $start->format('Y-m-d'), '9000.00', 'مدفوعة'),
                    $reRow(1, 'إيجار', $start->format('Y-m-d'), '51750.00', 'مدفوعة'),
                ];
                $totals = ['subtotal' => '54000.00', 'discount' => '0.00', 'shipping' => '0.00', 'tax' => '6750.00',
                    'grand_total' => '60750.00', 'paid' => '60750.00', 'remaining' => '155250.00'];
                $named['payment'] = [
                    'code' => 'REP-000039', 'date' => now()->format('Y-m-d'), 'status' => 'مرحّلة', 'is_cancelled' => '',
                    'contract_code' => 'REC-000017', 'tenant_name' => $customer['name'], 'tenant_mobile' => $customer['phone'],
                    'building' => 'برج النخيل', 'unit' => 'محل S1 — برج النخيل',
                    'method' => 'تحويل بنكي', 'reference' => 'TRX-88213', 'treasury' => 'البنك الأهلي — الحساب الجاري',
                    'amount' => '60750.00', 'amount_words' => NumberToWords::arabic(60750, 'SAR'),
                    'rent' => '45000.00', 'tax_amount' => '6750.00', 'has_tax' => '1', 'deposit' => '9000.00', 'has_deposit' => '1',
                    'advance' => '0.00', 'has_advance' => '', 'commission' => '4500.00', 'currency' => 'SAR',
                    'received_by' => 'أحمد محمد', 'notes' => '', 'contract_remaining' => '155250.00',
                ];
            }
        }

        $document += [
            'title_en' => self::ENGLISH_TITLES[$documentType] ?? 'Document',
            'time' => now()->format('H:i'),
            'employee' => 'أحمد محمد',
            'branch' => 'الفرع الرئيسي',
        ];

        return $this->withCodes(compact('business', 'customer', 'document', 'items', 'totals', 'statement') + $named);
    }
}
