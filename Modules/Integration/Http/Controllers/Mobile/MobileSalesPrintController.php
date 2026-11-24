<?php

namespace Modules\Integration\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CreditNotification;
use App\Models\Account;
use App\Models\Receipt;
use App\Models\Visit;
use App\Models\TaxInvoice;
use App\Support\BusinessInfo;
use App\Support\PrintSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Http\Controllers\Finance\IncomesController;
use Modules\Sales\Http\Controllers\CreditNotificationController;
use Modules\Sales\Http\Controllers\DailyClosingController;
use Modules\Sales\Http\Controllers\InvoicesController;
use Modules\Sales\Http\Controllers\ReturnInvoiceController;
use Modules\Sales\Http\Controllers\SalesPaymentController;
use Symfony\Component\HttpFoundation\Response;

/**
 * مطبوعات مستندات المبيعات لتطبيق الجوال — **نفس دوالّ طباعة الويب**، ويعود ناتجها HTML داخل JSON.
 *
 * كان التطبيق يبني مطبوعته بتصميمٍ ثابتٍ فيه، فلا أثر لما يُصمَّم في «قوالب المستندات». وتصييرُ
 * القوالب وبياناتُها (`DocumentTemplateRenderer` و`DocumentTemplateDataService`) هنا في الويب، فلا
 * يُنسخ منها شيء: تُستدعى دالّة الطباعة كما يستدعيها رابط الويب، ويؤخذ ناتجها كما هو.
 *
 *   بلا `?builtin`  ← القالب الافتراضيّ لنوعه من الإعدادات، وإلّا قالب النظام (كشاشات المستندات).
 *   `?builtin=1`   ← قالب النظام المدمج دائماً (كمطبوعات مساحة الزيارة)، و`&type=thermal` لسند القبض.
 *
 * الصلاحية وميزة الباقة على المسار نفسه كرابط الويب (`routes/mobile_api.php`).
 */
class MobileSalesPrintController extends Controller
{
    public function invoice(Request $request, int $id): JsonResponse
    {
        return $this->wrap(app(InvoicesController::class)->print($id, $this->template($request)));
    }

    public function salesReturn(Request $request, int $id): JsonResponse
    {
        return $this->wrap(app(ReturnInvoiceController::class)->print($id, $this->template($request)));
    }

    public function creditNote(Request $request, int $id): JsonResponse
    {
        /* قالب النظام للإشعار الدائن في الويب يُحوَّل PDF بـDompdf ويُبَثّ مباشرةً (`stream()` يكتب
           الترويسات ويخرج)، فلا HTML يُؤخذ من ناتجه. يُصيَّر هنا **القالب نفسه بالبيانات نفسها**
           (`sales::creted_note.pdf` — `CreditNotificationController::print()`). */
        if ($request->boolean('builtin')) {
            $credit = CreditNotification::with(['client', 'createdBy'])->findOrFail($id);

            return response()->json(['success' => true, 'data' => ['html' => view('sales::creted_note.pdf', [
                'credit' => $credit,
                'TaxsInvoice' => TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'credit')->get(),
                'company_data' => [
                    'company_name' => BusinessInfo::name(),
                    'company_address' => BusinessInfo::address(),
                    'company_phone' => BusinessInfo::phone(),
                    'company_email' => BusinessInfo::email(),
                    'company_logo' => BusinessInfo::logo(),
                    'tax_number' => BusinessInfo::taxNumber(),
                ],
            ])->render()]]);
        }

        return $this->wrap(app(CreditNotificationController::class)->print($id, $this->template($request)));
    }

    /**
     * الإقفال اليوميّ — `DailyClosingController::print()` كرابط «طباعة» في شاشة الإقفال بالويب، بنطاقه
     * (إقفالي، أو الكلّ لمن يرى الكلّ). لا قوالب له في «قوالب المستندات»: مطبوعةٌ واحدة.
     */
    public function closing(int $id): JsonResponse
    {
        return $this->wrap(app(DailyClosingController::class)->print($id));
    }

    /** يقرأ `?builtin` و`?type` من الطلب نفسه، كرابط الويب */
    public function payment(int $id): JsonResponse
    {
        return $this->wrap(app(SalesPaymentController::class)->rereceipt($id));
    }

    /**
     * سند القبض الماليّ (`receipts`) — `IncomesController::print()` كرابط «طباعة» في شاشة السند بالويب:
     * `?template={id}` قالبٌ من «قوالب المستندات» (النوع `receipt_voucher`)، و`?builtin=1` النسخة المدمجة
     * (`&type=thermal` للحرارية)، وبلا معاملٍ القالبُ الافتراضيّ إن وُجد وإلّا المدمجة.
     * ويُحرس بنطاق العرض نفسه الذي تحرس به شاشة السند (`show` — منشئه، أو عميلٌ مسنَدٌ للمستخدم)
     * — `print()` في الويب لا يفحصه —
     * أو بالزيارة (`?visit=`): مستندات العميل في الزيارة تعرض كلّ سنداته، فسندُ عميل الزيارة يُطبع لصاحبها.
     */
    public function income(Request $request, int $id): JsonResponse
    {
        $income = Receipt::findOrFail($id);
        $controller = app(IncomesController::class);
        $inScope = $controller->canViewReceipt($income);
        if (! $inScope && ! $this->receiptOfVisitClient($income, $request->integer('visit'))) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا السجل.');
        }

        if (! $request->boolean('builtin') && ! is_numeric($request->query('template'))) {
            $default = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPES['income']);
            if ($default) {
                $request->query->set('template', (string) $default->id);
            }
        }
        if ($request->boolean('builtin')) {
            $request->query->remove('template');
        }

        return $this->wrap($controller->print($id, $request->query('type') === 'thermal' ? 'thermal' : 'normal'));
    }

    /**
     * سندٌ لعميل زيارةٍ يملكها المستخدم (أو يتتبّع الزيارات): عميلُ السند هو عميلُ الزيارة، بعميله
     * أو بحسابه — كما تجمع قائمة مستندات الزيارة سنداتِه.
     */
    private function receiptOfVisitClient(Receipt $income, int $visitId): bool
    {
        $user = auth()->user();
        $visit = $visitId > 0 ? Visit::find($visitId) : null;
        if (! $user || ! $visit || ! $visit->client_id) {
            return false;
        }
        $owns = (int) $visit->employee_id === (int) $user->id
            || $user->can('clients_visits_track')
            || $user->hasRole('admin');
        if (! $owns) {
            return false;
        }
        if ((int) $income->client_id === (int) $visit->client_id) {
            return true;
        }

        return $income->account_id
            && Account::ledger()->where('id', $income->account_id)->where('client_id', $visit->client_id)->exists();
    }

    /**
     * قوالب نوع المستند كما تعرضها قائمة «المطبوعات» في شاشة المستند بالويب
     * (`PrintSettings::templatesFor()`: الفعّالة، العامّة وفرعُ المستخدم، الافتراضيّ أولاً).
     */
    public function templates(string $kind): JsonResponse
    {
        $type = self::DOCUMENT_TYPES[$kind] ?? null;
        if (! $type) {
            return response()->json(['success' => false, 'message' => 'نوع مستندٍ غير معروف.'], 404);
        }

        $list = PrintSettings::templatesFor($type)->map(fn ($t) => [
            'id' => (int) $t->id,
            'name' => (string) $t->name,
            'paperSize' => (string) ($t->paper_size ?: 'A4'),
            'isDefault' => (bool) $t->is_default,
        ])->values();

        return response()->json(['success' => true, 'data' => ['templates' => $list]]);
    }

    /** نوع القالب لكلّ مستند — ثوابت `DOCUMENT_TYPE` في متحكّمات الويب */
    private const DOCUMENT_TYPES = [
        'invoice' => InvoicesController::DOCUMENT_TYPE,
        'return' => ReturnInvoiceController::DOCUMENT_TYPE,
        'credit-note' => CreditNotificationController::DOCUMENT_TYPE,
        'payment' => SalesPaymentController::DOCUMENT_TYPE,
        // سند القبض الماليّ: نوع القالب نفسه، ومُطبِّعه في `DocumentTemplateDataService` يقرأ `receipts`
        'income' => 'receipt_voucher',
    ];

    /**
     * القالب المطلوب: `?builtin=1` ← قالب النظام (مقطع `builtin` كروابط مساحة الزيارة)،
     * `?template={id}` ← قالبٌ بعينه من القائمة، وإلّا فالافتراضيّ.
     */
    private function template(Request $request): ?string
    {
        if ($request->boolean('builtin')) {
            return 'builtin';
        }
        $id = $request->query('template');

        return is_numeric($id) ? (string) (int) $id : null;
    }

    private function wrap(mixed $out): JsonResponse
    {
        if ($out instanceof View) {
            return response()->json(['success' => true, 'data' => ['html' => $out->render()]]);
        }

        if ($out instanceof Response) {
            if ($out->isRedirection() || $out->getStatusCode() >= 400) {
                return response()->json(['success' => false, 'message' => 'تعذّر تجهيز المطبوعة.'], $out->getStatusCode() >= 400 ? $out->getStatusCode() : 422);
            }

            return response()->json(['success' => true, 'data' => ['html' => (string) $out->getContent()]]);
        }

        return response()->json(['success' => true, 'data' => ['html' => (string) $out]]);
    }
}
