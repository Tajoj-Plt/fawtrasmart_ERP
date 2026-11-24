<?php

namespace Modules\Client\Http\Controllers;

use App\Enums\ReferenceType;
use App\Helpers\ClientSettingsHelper;
use App\Helpers\WeekHelper;
use App\Http\Controllers\Controller;
use App\Imports\ClientsImport;
use App\Mail\TestMail;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeNote;
use App\Models\ClientRelation;
use App\Models\ClientStageRequest;
use App\Models\ClientStatusRequest;
use App\Models\CreditLimit;
use App\Models\CustomerTarget;
use App\Models\DocumentTemplate;
use App\Models\DoubtfulDebt;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\Expense;
use App\Models\GeneralClientSetting;
use App\Models\HiddenClient;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Location;
use App\Models\Log as ModelsLog;
use App\Models\Neighborhood;
use App\Models\notifications;
use App\Models\PaymentsProcess;
use App\Models\ProductExchange;
use App\Models\Receipt;
use App\Models\Revenue;
use App\Models\SerialSetting;
use App\Models\Statuses;
use App\Models\Target;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClientAccessService;
use App\Services\Clients\ClientVisibilityScope;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Services\PointsService;
use App\Services\Wallets\ClientWalletService;
use App\Support\AccountingSettings;
use App\Support\DocumentPrintPage;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use App\Traits\AssignedEmployeeAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Client\Http\Requests\ClientRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;

// PDF Generation

/**
 * كنترول العملاء — سجلّهم وملفّاتهم وعملياتهم.
 *
 * ═══════════════ ما يأتي من الإعدادات ═══════════════
 *
 *   • *الحسابات*   ← «الحسابات المحاسبية» وحدها، بلا احتياطٍ برمجي
 *                    (`settingsAccount()`). لا `SystemAccountIds` هنا.
 *   • *العملة*     ← «العملات»؛ كانت `'SAR'` مكتوبةً نصّاً في سبعة مواضع
 *                    داخل قيود الشطب والديون المشكوك فيها
 *                    (`UsesDocumentCurrency`).
 *   • *الإقفال*    ← «الفترات المحاسبية»: شطبُ دينٍ أو تحويلُه إلى مشكوكٍ
 *                    فيه بتاريخٍ في فترةٍ مقفلة يُردّ (`RespectsAccountingPeriods`).
 *   • *القوالب*    ← «قوالب المستندات»، النوع `client_statement`.
 *   • *حالة العميل*← «إعدادات وحدات العملاء»: الحالةُ الافتراضية للعميل
 *                    الجديد تُقرأ من الإعداد لا من رقمٍ في الشيفرة.
 *   • *قواعد التسجيل* ← الهاتفُ مطلوب؟ فريد؟ الهويةُ مطلوبة؟ البريد؟
 *                    كلُّها إعداداتٌ في تبويب «العملاء».
 *   • *حدّ الشطب*  ← مبلغٌ يتجاوزه يحتاج صلاحية اعتمادٍ ثانية.
 *   • *السجلّات*   ← `OperationLog`: النشاط والأحداث والأخطاء في كل عملية.
 *
 * ═══════════════ الصلاحيات ═══════════════
 *
 * كان على المتحكّم ستّةُ أسطر تحرس نحو خمسٍ وعشرين دالّة من ستٍّ وستّين.
 * والمكشوف منها لم يكن هامشياً: `writeOffBranchDebt` يشطب ذمم فرعٍ كامل،
 * و`toggleExemptLocation` يُعفي عميلاً من التحقّق من موقع الزيارة،
 * و`importExcel` يكتب مئات الصفوف، و`send_email` يُخرج رسالةً من المنشأة —
 * كلُّها كانت مفتوحةً لكل من يفتح الشاشة.
 *
 * @see \App\Constants\permissions\ClientsPermissions كتالوج الصلاحيات
 * @see \App\Support\ClientSettings سجلّ إعدادات الوحدة
 */
class ClientController extends Controller
{
    use AssignedEmployeeAccess;
    use Concerns\EnforcesClientModuleSettings;
    use Concerns\RendersClientDocumentTemplates;
    use Concerns\RespectsAccountingPeriods;
    use Concerns\UsesDocumentCurrency;

    /** نوع المستند في شاشة «قوالب المستندات» — كشف حساب العميل */
    public const DOCUMENT_TYPE = 'client_statement';

    /** قيمة العمود `type` في سجلّ النشاط — بها يربط العميلُ خطَّه الزمني */
    public const LOG_MODULE = 'Clients';

    /**
     * حساب مربوط في شاشة «الحسابات المحاسبية» — بالمفتاح وحده.
     *
     * **الإعدادات هي المصدر الوحيد.** لا ثابت برمجي احتياطياً: المفتاح غير
     * المربوط يُرَدّ برسالة تسمّيه وتدلّ على الشاشة، لا بقيدٍ يذهب صامتاً
     * إلى حسابٍ مكتوبٍ في الشيفرة لا يعرفه المحاسب.
     *
     * @throws \Exception إن لم يكن المفتاح مربوطاً أو كان حسابه محذوفاً
     */
    private function settingsAccount(string $key): Account
    {
        $account = AccountingSettings::account($key);

        if (! $account) {
            throw new \Exception(__('purchase_cycle.messages.account_missing', [
                'account' => __('purchase_cycle.accounts.'.$key),
            ]));
        }

        return $account;
    }

    public function __construct(private readonly ClientAccessService $clientAccess)
    {
        /* ── العرض ──
           أربعُ صلاحيات عرضٍ بديلة: الكلّ، أو عملاؤه، أو مجموعاتُه، أو
           خطُّ سيره اليومي. يكفي واحدة، والنطاقُ نفسه يُطبَّق داخل
           الاستعلام بعدها. */
        $viewAny = 'permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary';

        $this->middleware($viewAny, ['only' => [
            'index', 'getAjaxResponse', 'getClientMap', 'getMapData', 'getClientsForSelect',
            'show', 'getClientDetails', 'getClientInvoices',
            'paginate', 'searchClients', 'getAllClients', 'contacts',
            'show_contant', 'getFirstClient', 'getNextClient', 'getPreviousClient',
            'getFilteredClients', 'needsModification', 'currentRoute', 'search',
        ]]);

        // ── السجلّ: إضافة وتعديل وحذف ──
        $this->middleware('permission:clients_add_client', ['only' => ['create', 'store']]);
        $this->middleware('permission:clients_edit_data', ['only' => [
            'edit', 'edit_question', 'update', 'searchByCode', 'searchClientsForSelect',
        ]]);
        $this->middleware('permission:clients_delete', ['only' => ['destroy']]);

        // ── الحالات: التغيير، ثم الاعتماد ──
        $this->middleware('permission:clients_change_status', ['only' => [
            'updateStatus', 'updateStatusClient', 'bulkUpdateStatus', 'bulkStatusPage',
            'updateStageClient',
        ]]);
        $this->middleware('permission:clients_approve_status', ['only' => [
            'approveStatusRequest', 'rejectStatusRequest',
            'approveStatusRequestByClient', 'rejectStatusRequestByClient',
            'statusRequestsIndex',
        ]]);
        // ── الوضع (مرحلة التسجيل): نفس نمط الحالة أعلاه بصلاحية اعتمادٍ خاصّة بها ──
        $this->middleware('permission:clients_approve_stage', ['only' => [
            'approveStageRequest', 'rejectStageRequest',
            'approveStageRequestByClient', 'rejectStageRequestByClient',
        ]]);

        // ── الحدود والأرصدة ──
        $this->middleware('permission:clients_manage_credit_limit', ['only' => [
            'updateCreditLimit', 'updateAllClientsCreditLimit', 'getClientCreditLimit',
        ]]);
        $this->middleware('permission:clients_manage_opening_balance', ['only' => ['updateOpeningBalance']]);

        // ── كشف الحساب والتصدير والاستيراد ──
        $this->middleware('permission:clients_view_statement', ['only' => [
            'statement', 'statementPrint', 'statementPdf',
        ]]);
        $this->middleware('permission:clients_export', ['only' => [
            'generateClientsPdf', 'exportAllClients', 'generateNotesPdf', 'getImportTemplate',
        ]]);
        // الاستيراد يكتب مئات الصفوف دفعةً واحدة — كان مفتوحاً بلا صلاحية
        $this->middleware('permission:clients_import', ['only' => [
            'import', 'importExcel', 'showImportPage',
        ]]);
        // بريدٌ يخرج من المنشأة إلى العميل — كان مفتوحاً بلا صلاحية
        $this->middleware('permission:clients_send_email', ['only' => ['send_email']]);

        // ── إسناد الموظفين: نُقلت إلى ClientEmployeeController ──

        // ── الزيارات من ملفّ العميل ──
        $this->middleware('permission:clients_visits_track|clients_itinerary_add', ['only' => [
            'registerVisit', 'completeVisit', 'storeVisit',
        ]]);
        $this->middleware('permission:clients_hide_from_map', ['only' => [
            'hideFromMap', 'showInMap', 'forceShow', 'getHiddenClients',
        ]]);

        // ── الملاحظات والمتابعة: نُقلت إلى ClientNoteController ──
        // (getClientNotes() تشير إلى دالّةٍ محذوفة/معلَّقة قبل هذا التعديل
        // بكثير — راجع التعليق أعلى تعريف الـ route المقابل، لم تُمسّ هنا)
        $this->middleware('permission:client_follow_up_view_notes_attachments_appointments_all|client_follow_up_view_notes_attachments_appointments_assigned|client_follow_up_view_notes_attachments_appointments_own', ['only' => [
            'getClientNotes',
        ]]);

        // ── سجلّ النشاط ──
        $this->middleware('permission:clients_view_all_activity_logs|clients_view_own_activity_log', ['only' => ['show_activity_log']]);

        /* ── الديون ──
           لم يكن على هذين الإجرائين أي صلاحية: أي مستخدم يصل شاشة العميل
           كان يقدر يصفّر رصيده أو يحوّله لدين مشكوك فيه بضغطة. */
        $this->middleware('permission:clients_manage_debts', ['only' => ['writeOffBadDebt', 'addDoubtfulDebtQuick']]);

        /* شطبُ ذمم فرعٍ كامل صلاحيةٌ وحدها لا `clients_manage_debts`:
           الأولى تشطب دَين عميل، وهذه تشطب دفتر فرعٍ بأكمله. */
        $this->middleware('permission:clients_writeoff_branch_debt', ['only' => [
            'showWriteOffBranchDebtForm', 'writeOffBranchDebt',
        ]]);

        /* منحُ الإعفاء غير حملِه: `clients_exempt_*` يحملها المُعفى،
           و`clients_manage_exemptions` يملكها من يمنحها. وكان الزرّان
           مفتوحَين لكل من يفتح ملفّ العميل، فيُعفي نفسه بضغطة. */
        $this->middleware('permission:clients_manage_exemptions', ['only' => [
            'toggleExemptLocation', 'toggleExemptExchange',
        ]]);
    }

    // ... existing methods ...

    public function getClientDetails($id)
    {
        $client = Client::with(['status_client', 'neighborhood', 'branch'])->find($id);
        if (! $client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        return response()->json(['client' => $client]);
    }

    // getLastNote(), getClientEmployees(), updateClientEmployee() نُقلت إلى
    // ClientNoteController / ClientEmployeeController — انظر تعليق الترويسة.

    public function generateNotesPdf($id)
    {
        // تحميل العميل مع الملاحظات
        $client = Client::with(['employee'])->findOrFail($id);
        $ClientRelations = ClientRelation::where('client_id', $id)
            ->with(['employee', 'clientEmployee'])
            ->orderBy('created_at', 'desc')
            ->get();

        // إنشاء PDF جديد باستخدام TCPDF
        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // تعيين معلومات الوثيقة
        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('ملاحظات العميل - '.$client->trade_name);

        // تعيين الهوامش
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // تعطيل رأس وتذييل الصفحة
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // تعيين الاتجاه من اليمين إلى اليسار
        $pdf->setRTL(true);

        // إضافة صفحة جديدة
        $pdf->AddPage();

        // تعيين الخط العربي
        $pdf->SetFont('aealarabiya', '', 12);

        // إنشاء محتوى HTML
        $html = view('client::partials.notes_pdf', compact('client', 'ClientRelations'))->render();

        // إضافة المحتوى للـ PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // إخراج الملف
        $filename = 'ملاحظات_العميل_'.$client->code.'.pdf';

        return $pdf->Output($filename, 'I');
    }

    public function getClientInvoices($id)
    {
        $user = auth()->user();

        // التحقق من أن المستخدم يمكنه الوصول للعميل
        if (! $this->canAccessClient($user, $id)) {
            return response()->json([
                'message' => 'ليس لديك صلاحية للوصول لفواتير هذا العميل',
            ], 403);
        }

        // جلب الفواتير مع تطبيق الفلترة حسب الصلاحيات
        $query = Invoice::where('client_id', $id);
        $invoices = $this->filterInvoicesByUserPermissions($query, $user)
            ->with(['payments'])
            ->orderBy('date', 'desc')
            ->paginate(10);

        return response()->json(['invoices' => $invoices]);
    }

    // public function getClientNotes($id)
    // {

    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     return response()->json(['notes' => $notes]);
    // }

    public function searchClients(Request $request)
    {
        $term = $request->term;

        $clients = Client::where(function ($query) use ($term) {
            $query
                ->where('trade_name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('code', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%');
        })
            ->with(['status_client', 'neighborhood'])
            ->limit(10)
            ->get();

        return response()->json(['clients' => $clients]);
    }

    public function needsModification(Request $request)
    {
        $employeeId = $request->employee_id;
        $year = $request->year;
        $week = $request->week;

        $clients = Client::whereHas('employeeClientVisit', function ($query) use ($employeeId, $year, $week) {
            $query->where('employee_id', $employeeId)->where('year', $year)->where('week_number', $week)->where('needs_modification', true);
        })
            ->with(['neighborhood', 'employeeClientVisit'])
            ->get();

        return response()->json(['data' => $clients]);
    }

    /**
     * هل تشمل الفلترةُ ما تحت العقدة؟ نعم ما لم تُرسل الشاشةُ خلافَ ذلك.
     *
     * الشمولُ هو الافتراض لأن الأرقامَ تتجمّع لأعلى: من يفلتر بـ«فرع الرياض»
     * يتوقّع عملاءَ مناطقه ومنافذه معه. و`include_descendants=0` من مفتاح
     * الشاشة يعطي المطابقةَ التامّة لمن أراد عملاءَ العقدة وحدها.
     */
    protected static function wantsDescendants(Request $request): bool
    {
        return ! in_array((string) $request->input('include_descendants', '1'), ['0', 'false', 'off'], true);
    }

    private function applyFilters($baseQuery, $request)
    {
        if ($request->filled('client')) {
            $baseQuery->where('id', $request->client);
        }

        if ($request->filled('name')) {
            $baseQuery->where('trade_name', 'like', '%'.$request->name.'%');
        }

        if ($request->filled('status')) {
            // إذا كانت القيمة 'no_status' فنبحث عن العملاء الذين ليس لهم حالة محددة
            if ($request->status === 'no_status') {
                $baseQuery->whereNull('status_id');
            } else {
                $baseQuery->where('status_id', $request->status);
            }
        }

        // مرحلة التسجيل — لعرض «عملاء تحت المراجعة» وحدهم دون تصفّح
        // شاشةٍ منفصلة. انظر docs/CRM_Customer_Status_Gap_Analysis_Plan.md القسم 3
        if ($request->filled('registration_stage')) {
            $baseQuery->where('registration_stage_id', $request->registration_stage);
        }

        // «شامل التوابع» افتراضاً — العملاءُ موزّعون على مستوياتٍ مختلفة،
        // فالمطابقةُ التامّة تُخفي عميلَ المنفذ عمّن يفلتر بالفرع فوقه.
        // المفتاحُ في الشاشة يرسل `include_descendants=0` لمن أراد التامّة.
        if ($request->filled('region')) {
            $baseQuery->inTreeNode((int) $request->region, static::wantsDescendants($request));
        }

        if ($request->filled('neighborhood')) {
            $baseQuery->whereHas('Neighborhoodname', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->neighborhood.'%')
                    ->orWhere('id', $request->neighborhood);
            });
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $baseQuery->whereBetween('created_at', [
                $request->date_from.' 00:00:00',
                $request->date_to.' 23:59:59',
            ]);
        } elseif ($request->filled('date_from')) {
            $baseQuery->where('created_at', '>=', $request->date_from.' 00:00:00');
        } elseif ($request->filled('date_to')) {
            $baseQuery->where('created_at', '<=', $request->date_to.' 23:59:59');
        }

        if ($request->filled('categories')) {
            $baseQuery->where('category_id', $request->categories);
        }

        if ($request->filled('user')) {
            // لا عمود created_by على clients — نفس علّة الفلاتر المصلَحة
            // سابقاً في تقارير العملاء: العمود الحقيقي هو user_id
            $baseQuery->where('user_id', $request->user);
        }

        if ($request->filled('type')) {
            // لا عمود type على clients — النوع الحقيقي client_type
            // (فردي/تجاري)؛ هذا الفرع كان يُسقط أيّ طلبٍ فيه type=... —
            // بما في ذلك تصدير PDF نفسه، الذي يستعمل type لغرضٍ آخر
            // تماماً (نطاق التصدير) فيصطدم بهذا الفلتر كلّما وُجد.
            $baseQuery->where('client_type', $request->type);
        }

        if ($request->filled('employee')) {
            $baseQuery->where('employee_id', $request->employee);
        }

        // عملاء تجاوزوا حدَّهم الائتماني — نفس منطق بند «مركز التنبيهات»
        // في لوحة العملاء (ClientDashboardService::notificationsCenter)
        // بالحرف، كي يطابق الرابط الرقم الذي جاء منه.
        if ($request->boolean('over_credit_limit')) {
            $baseQuery->whereNotNull('credit_limit')
                ->where('credit_limit', '>', 0)
                ->whereHas('account', function ($q) {
                    $q->ledger()->whereColumn('accounts.balance', '>', 'clients.credit_limit');
                });
        }

        if ($request->filled('customer_target_id')) {
            if ($request->customer_target_id === 'no_target') {
                $baseQuery->whereNull('customer_target_id');
            } else {
                $baseQuery->where('customer_target_id', $request->customer_target_id);
            }
        }

        // فلترة حسب آخر فاتورة
        if ($request->filled('last_invoice_period')) {
            $dates = $this->getPeriodDates($request->last_invoice_period);

            $baseQuery->whereHas('invoices', function ($q) use ($dates) {
                $q->whereBetween('invoice_date', [$dates['start'], $dates['end']]);
            });
        }

        // فلترة حسب آخر دفعة (من خلال الفواتير)
        if ($request->filled('last_payment_period')) {
            $dates = $this->getPeriodDates($request->last_payment_period);

            $baseQuery->whereHas('invoices.payments', function ($q) use ($dates) {
                $q->whereBetween('created_at', [$dates['start'], $dates['end']])
                    ->where('type', 'client payments');
            });
        }

        // فلترة حسب آخر نشاط (دفعة أو سند قبض)
        // فلترة حسب آخر نشاط فعلي (فاتورة أو دفعة أو سند قبض)
        if ($request->filled('last_activity_period')) {
            $dates = $this->getPeriodDates($request->last_activity_period);

            $baseQuery->whereIn('id', function ($subQuery) use ($dates) {
                $subQuery->select('client_id')
                    ->from(function ($inner) {
                        $inner->select('invoices.client_id', 'invoices.invoice_date as date')
                            ->from('invoices')

                            // الدفعات من جدول payments_process
                            ->unionAll(
                                \DB::table('payments_process')
                                    ->join('invoices', 'payments_process.invoice_id', '=', 'invoices.id')
                                    ->select('invoices.client_id', 'payments_process.created_at as date')
                            )

                            // السندات
                            ->unionAll(
                                \DB::table('accounts')
                                    ->join('receipts', 'accounts.id', '=', 'receipts.account_id')
                                    ->select('accounts.client_id', 'receipts.created_at as date')
                            );
                    }, 'activities')
                    ->whereRaw('activities.date = (
                SELECT MAX(a2.date)
                FROM (
                    SELECT invoices.client_id, invoices.invoice_date as date
                    FROM invoices

                    UNION ALL
                    SELECT invoices.client_id, payments_process.created_at as date
                    FROM payments_process
                    JOIN invoices ON payments_process.invoice_id = invoices.id

                    UNION ALL
                    SELECT accounts.client_id, receipts.created_at as date
                    FROM accounts
                    JOIN receipts ON accounts.id = receipts.account_id
                ) as a2
                WHERE a2.client_id = activities.client_id
            )')
                    ->whereBetween('activities.date', [$dates['start'], $dates['end']]);
            });
        }

        /* حُذف فلترُ «دوام المسؤول من / إلى» مع حقلَيه في بطاقة البحث:
           كان يفلتر على دوام أحدث ملاحظةٍ للعميل لا على بيانٍ ثابتٍ فيه. */
    }

    // دالة مساعدة لحساب الفترات
    private function getPeriodDates($period)
    {
        $now = now();

        switch ($period) {
            case 'today':
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];

            case 'week':
                return [
                    'start' => $now->copy()->subDays(7)->startOfDay(),
                    'end' => $now->copy()->subDay()->endOfDay(),
                ];

            case 'two_weeks':
                return [
                    'start' => $now->copy()->subDays(14)->startOfDay(),
                    'end' => $now->copy()->subDays(8)->endOfDay(),
                ];

            case 'month':
                return [
                    'start' => $now->copy()->subDays(30)->startOfDay(),
                    'end' => $now->copy()->subDays(15)->endOfDay(),
                ];

            case 'three_months':
                return [
                    'start' => $now->copy()->subDays(90)->startOfDay(),
                    'end' => $now->copy()->subDays(31)->endOfDay(),
                ];

            case 'six_months':
                return [
                    'start' => $now->copy()->subDays(180)->startOfDay(),
                    'end' => $now->copy()->subDays(91)->endOfDay(),
                ];

            case 'year':
                return [
                    'start' => $now->copy()->subDays(365)->startOfDay(),
                    'end' => $now->copy()->subDays(181)->endOfDay(),
                ];

            case 'more_than_year':
                return [
                    'start' => $now->copy()->subYears(100),
                    'end' => $now->copy()->subDays(365)->endOfDay(),
                ];

            default:
                return [
                    'start' => $now->subYears(100),
                    'end' => $now,
                ];
        }
    }

    public function currentRoute(Request $request)
    {
        $employeeId = $request->employee_id;
        $year = $request->year;
        $week = $request->week;

        $clients = Client::whereHas('employeeClientVisit', function ($query) use ($employeeId, $year, $week) {
            $query->where('employee_id', $employeeId)->where('year', $year)->where('week_number', $week);
        })
            ->with(['neighborhood', 'employeeClientVisit'])
            ->get();

        return response()->json(['data' => $clients]);
    }

    private function calculateClientData($clients, $currentYear)
    {
        if ($clients->isEmpty()) {
            return [];
        }

        $clientIds = $clients->pluck('id');
        $monthlyTarget = 648;
        $classificationThresholds = ['a_plus_plus' => 100, 'a' => 60, 'b' => 30];

        // جلب البيانات المالية
        $returnedInvoiceIds = Invoice::whereNotNull('reference_number')->pluck('reference_number')->toArray();
        $excludedInvoiceIds = array_unique(array_merge($returnedInvoiceIds, Invoice::where('type', 'returned')->pluck('id')->toArray()));

        $invoices = Invoice::whereIn('client_id', $clientIds)->where('type', 'normal')->whereNotIn('id', $excludedInvoiceIds)->get();

        $invoiceIdsByClient = $invoices->groupBy('client_id')->map->pluck('id');

        $payments = PaymentsProcess::whereIn('invoice_id', $invoices->pluck('id'))->whereYear('created_at', $currentYear)->get()->groupBy('invoice_id');

        $receipts = Receipt::with('account')->whereHas('account', fn ($q) => $q->whereIn('client_id', $clientIds))->whereYear('created_at', $currentYear)->get()->groupBy(fn ($receipt) => $receipt->account->client_id);

        $months = [
            'يناير' => 1,
            'فبراير' => 2,
            'مارس' => 3,
            'أبريل' => 4,
            'مايو' => 5,
            'يونيو' => 6,
            'يوليو' => 7,
            'أغسطس' => 8,
            'سبتمبر' => 9,
            'أكتوبر' => 10,
            'نوفمبر' => 11,
            'ديسمبر' => 12,
        ];

        $getClassification = function ($percentage, $collected = 0) use ($classificationThresholds) {
            if ($collected == 0) {
                return ['group' => 'D', 'class' => 'secondary'];
            }
            if ($percentage > $classificationThresholds['a_plus_plus']) {
                return ['group' => 'A++', 'class' => 'primary'];
            }
            if ($percentage >= $classificationThresholds['a']) {
                return ['group' => 'A', 'class' => 'success'];
            }
            if ($percentage >= $classificationThresholds['b']) {
                return ['group' => 'B', 'class' => 'warning'];
            }

            return ['group' => 'C', 'class' => 'danger'];
        };

        return $clients
            ->map(function ($client) use ($invoiceIdsByClient, $payments, $receipts, $months, $monthlyTarget, $getClassification, $currentYear) {
                $invoiceIds = $invoiceIdsByClient[$client->id] ?? collect();

                $clientData = [
                    'id' => $client->id,
                    'monthly' => [],
                    'invoices_count' => $invoiceIds->count(),
                    'payments_count' => $invoiceIds->sum(fn ($id) => isset($payments[$id]) ? $payments[$id]->count() : 0),
                    'receipts_count' => isset($receipts[$client->id]) ? $receipts[$client->id]->count() : 0,
                    'total_collected' => 0,
                ];

                $totalYearlyCollected = 0;

                foreach ($months as $monthName => $monthNumber) {
                    $paymentsTotal = 0;
                    if ($invoiceIds->isNotEmpty()) {
                        foreach ($invoiceIds as $invoiceId) {
                            if (isset($payments[$invoiceId])) {
                                $paymentsTotal += $payments[$invoiceId]->filter(fn ($payment) => Carbon::parse($payment->created_at)->year == $currentYear && Carbon::parse($payment->created_at)->month == $monthNumber)->sum('amount');
                            }
                        }
                    }

                    $receiptsTotal = isset($receipts[$client->id]) ? $receipts[$client->id]->filter(fn ($receipt) => Carbon::parse($receipt->created_at)->year == $currentYear && Carbon::parse($receipt->created_at)->month == $monthNumber)->sum('amount') : 0;

                    $monthlyCollected = $paymentsTotal + $receiptsTotal;
                    $totalYearlyCollected += $monthlyCollected;

                    $percentage = $monthlyTarget > 0 ? round(($monthlyCollected / $monthlyTarget) * 100, 2) : 0;
                    $classification = $getClassification($percentage, $monthlyCollected);

                    $clientData['monthly'][$monthName] = [
                        'collected' => $monthlyCollected,
                        'payments_total' => $paymentsTotal,
                        'receipts_total' => $receiptsTotal,
                        'target' => $monthlyTarget,
                        'percentage' => $percentage,
                        'group' => $classification['group'],
                        'group_class' => $classification['class'],
                        'month_number' => $monthNumber,
                    ];
                }

                $clientData['total_collected'] = $totalYearlyCollected;

                return $clientData;
            })
            ->keyBy('id');
    }

    /**
     * المستحقّ على العملاء لعمود «المستحقّ» في القائمة.
     *
     * `ledger()` تستبعد حسابات المحافظ — المحفظة حسابٌ مستقلٌّ لنا عليه
     * (خصوم) لا يدخل ذمم العميل، وبدونها كان مجموعُ الحسابات يخلط الرصيدين
     * فيختلف رقمُ القائمة عن رقم شاشة العميل التي تستخدم `ledger()` أصلاً.
     */
    private function getClientDueBalances($clients)
    {
        if ($clients->isEmpty()) {
            return [];
        }

        $clientIds = $clients->pluck('id');

        return Account::ledger()->whereIn('client_id', $clientIds)->selectRaw('client_id, SUM(balance) as total_due')->groupBy('client_id')->pluck('total_due', 'client_id');
    }

    private function getClientTotalSales($clients)
    {
        if ($clients->isEmpty()) {
            return [];
        }

        $clientIds = $clients->pluck('id');
        $returnedInvoiceIds = Invoice::whereNotNull('reference_number')->pluck('reference_number')->toArray();
        $excludedInvoiceIds = array_unique(array_merge($returnedInvoiceIds, Invoice::where('type', 'returned')->pluck('id')->toArray()));

        return Invoice::whereIn('client_id', $clientIds)->where('type', 'normal')->whereNotIn('id', $excludedInvoiceIds)->groupBy('client_id')->selectRaw('client_id, SUM(grand_total) as total_sales')->pluck('total_sales', 'client_id');
    }

    private function calculateClientDistances($clients, $user)
    {
        $clientDistances = [];
        $userLocation = Location::where('employee_id', $user->id)->whereNull('client_id')->latest()->first();

        // إذا لم يكن هناك موقع للمستخدم، إرجاع بيانات فارغة
        if (! $userLocation) {
            foreach ($clients as $client) {
                $clientDistances[$client->id] = [
                    'distance' => null,
                    'message' => 'موقعك غير معروف',
                    'within_range' => false,
                ];
            }

            return $clientDistances;
        }

        foreach ($clients as $client) {
            // البحث عن آخر موقع للعميل
            $clientLocation = $client->locations()->latest()->first();

            if ($clientLocation && $clientLocation->latitude && $clientLocation->longitude) {
                $distanceKm = $this->calculateDistance(
                    $userLocation->latitude,
                    $userLocation->longitude,
                    $clientLocation->latitude,
                    $clientLocation->longitude
                );

                $clientDistances[$client->id] = [
                    'distance' => $distanceKm,
                    'message' => $distanceKm !== null ? 'تم الحساب بنجاح' : 'خطأ في حساب المسافة',
                    'within_range' => $distanceKm !== null && $distanceKm <= ClientSettingsHelper::visitProximityRadiusKmFor($user),
                    'distance_text' => $distanceKm !== null ?
                        ($distanceKm < 1 ?
                            round($distanceKm * 1000).' متر' :
                            round($distanceKm, 2).' كم'
                        ) : 'غير معروف',
                ];
            } else {
                $clientDistances[$client->id] = [
                    'distance' => null,
                    'message' => 'موقع العميل غير معروف',
                    'within_range' => false,
                    'distance_text' => 'غير معروف',
                ];
            }
        }

        return $clientDistances;
    }

    public function getMapData(Request $request)
    {
        $user = auth()->user();

        // تأكد من استخدام نفس العلاقة المستخدمة في index
        $baseQuery = Client::with([
            'employee',
            'status_client:id,name,color',
            'locations',
            'neighborhood.region',
            'branch:id,name',
            'latestRelation', // تحميل أحدث ملاحظة لجلب الدوام
        ]);

        $noClients = false;

        $currentDate = now();
        $weekInfo = WeekHelper::getWeekAndYear($currentDate);
        $currentWeek = $weekInfo['week'];
        $currentYear = $weekInfo['year'];

        $englishDays = WeekHelper::getEnglishDays();
        $currentDayOfWeek = $currentDate->dayOfWeek;
        $adjustedDayOfWeek = ($currentDayOfWeek + 1) % 7;
        $currentDayNameEn = $englishDays[$adjustedDayOfWeek];

        /* عميلٌ انتهى دورُه اليوم يختفي من خريطة **مندوبه** — «كأنّه خلّص
           زياراته». ويعود بمجرّد وجود صفّ خطّ سيرٍ غير مكتمل، وهو ما يصنعه
           «سأعود لهذا العميل اليوم» عند إنهاء الزيارة.

           الإخفاء هنا وفي خطّ سير اليوم فقط: العميل يبقى في فهرس العملاء
           والبحث وملفّه، فالمندوب يحتاجه لمراجعة فاتورةٍ أو رصيد. ومن يرى كل
           العملاء (مديرٌ أو مشرف) لا يُخفى عنه شيء — الخريطة عنده أداةُ
           متابعةٍ لا قائمةَ عملٍ. */
        /* من له خطُّ سيرٍ اليوم لا يرى على الخريطة إلا عملاءَه — المخطَّطين،
           ومن أضافهم هو، ومن أضافه له دورٌ آخر من «خط سيري اليومي» — مهما
           اتّسعت صلاحياتُ عرضه («عرض جميع العملاء» أو نطاقُ فرعٍ). الخريطةُ هنا
           قائمةُ عمل اليوم. ومن لا خطَّ له اليوم (مديرٌ أو مشرف) تبقى خريطتُه
           أداةَ متابعةٍ كما كانت. الجدولُ تحتها لا يتغيّر. */
        $itineraryRows = EmployeeClientVisit::where('employee_id', $user->id)
            ->whereDate('visit_date', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('visit_status')->orWhere('visit_status', '!=', 'cancelled'))
            ->orderBy('id')
            ->get(['client_id', 'added_by']);
        $itineraryMode = $itineraryRows->isNotEmpty();

        if ($itineraryMode || ! hasPermission('clients_view_all_clients')) {
            $finishedToday = EmployeeClientVisit::hiddenFinishedClientIdsFor((int) $user->id);

            if ($finishedToday->isNotEmpty()) {
                $baseQuery->whereNotIn('id', $finishedToday);
            }
        }

        // نطاق شجرة الفرع للمستخدم (الفرع + كل أحفاده)
        $userBranchScopeIds = $this->resolveUserBranchTreeScope($user);

        // ⭐ تطبيق نطاق الشجرة للمستخدمين الذين لديهم صلاحية عرض جميع العملاء
        // عند اختيار فرع من الهيدر: يُعرض هذا الفرع + كل المناطق تحته في الشجرة
        if ($itineraryMode) {
            $baseQuery->whereIn('id', $itineraryRows->pluck('client_id')->unique()->values());
        } elseif (hasPermission('clients_view_all_clients') && $userBranchScopeIds !== null) {
            $this->applyClientBranchTreeScope($baseQuery, $userBranchScopeIds);
        }

        // تطبيق منطق الصلاحيات الجديد — النواة المشتركة (مجموعات/خط سير/عملاء مسندون)
        // من ClientAccessService::applyPermissionCascade؛ الاحتياط أدناه خاصٌّ بهذه الشاشة
        if (! $itineraryMode && ! hasPermission('clients_view_all_clients')) {
            $matched = $this->clientAccess->applyPermissionCascade($baseQuery, $user, $currentDayNameEn, $currentYear, $currentWeek);

            if ($matched === false) {
                $noClients = true;
            } elseif ($matched === null) {
                // ✅ للموظف الذي ليس لديه صلاحيات خاصة - استخدام خط السير اليومي (استخدام isEmployee() بدلاً من الدور)
                if ($user->isEmployee()) {
                    $clientVisits = $this->clientAccess->todayItineraryClientIds($user->id, $currentDayNameEn, $currentYear, $currentWeek);

                    if ($clientVisits->isNotEmpty()) {
                        $baseQuery->whereIn('id', $clientVisits);
                    } else {
                        $noClients = true;
                    }
                }
                // للمستخدم المرتبط بفرع معين — نطاق الشجرة (الفرع + كل أحفاده)
                elseif ($user->branch_id) {
                    $this->applyClientBranchTreeScope($baseQuery, $userBranchScopeIds);
                } else {
                    // لا توجد صلاحية للعرض
                    $noClients = true;
                    $baseQuery->whereRaw('1 = 0');
                }
            }
        }

        // **الإضافة المهمة: تطبيق فلتر العملاء المخفيين قبل تطبيق الفلاتر الأخرى**
        $hiddenClientIds = HiddenClient::getHiddenClientsForUser($user->id);
        if (! empty($hiddenClientIds)) {
            $baseQuery->whereNotIn('id', $hiddenClientIds);
        }

        // تطبيق الفلاتر
        $this->applyFilters($baseQuery, $request);

        if ($noClients) {
            return response()->json(['clients' => []]);
        }

        // جلب العملاء مع المواقع فقط
        $clients = $baseQuery
            ->whereHas('locations', function ($query) {
                $query->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('latitude', '!=', '')
                    ->where('longitude', '!=', '')
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0);
            })
            ->get();

        /* «الزيارة قبل العرض» في نافذة الماركر — نفسُ شرط صفّ الجدول في
           `partials/client_cards.blade.php` بالضبط، محسوباً هنا مرّةً واحدة
           لكلّ القائمة بدل استعلامٍ لكلّ نافذة. بدونه كان زرُّ البطاقة يفتح
           `clients.show` دائماً، فيصطدم الموظّفُ المُلزَم بالحاجز بعد النقر
           بينما يعرض له الجدولُ «تسجيل زيارة» للعميل نفسه. */
        $mustVisit = hasPermission('clients_must_visit_before_view')
            && ! hasPermission('clients_exempt_visit_before_view');

        $completedVisitClientIds = [];
        $openVisitByClient = [];

        if ($mustVisit && $clients->isNotEmpty()) {
            $todayVisits = Visit::where('employee_id', $user->id)
                ->whereIn('client_id', $clients->pluck('id'))
                ->whereDate('visit_date', now()->toDateString())
                ->get(['id', 'client_id', 'stage', 'departure_time']);

            foreach ($todayVisits as $todayVisit) {
                if ($todayVisit->stage === Visit::STAGE_COMPLETED) {
                    $completedVisitClientIds[$todayVisit->client_id] = true;
                } elseif ($todayVisit->departure_time === null && ! isset($openVisitByClient[$todayVisit->client_id])) {
                    $openVisitByClient[$todayVisit->client_id] = $todayVisit->id;
                }
            }

            /* عميلٌ له عودةٌ مجدولة اليوم لم تُنجَز بعد يبقى «يحتاج زيارة»
               ولو أُتمّت زيارته الأولى — نفس شرط `show()` حرفاً بحرف، وإلا
               عرضت البطاقةُ «التفاصيل» ثم أعاده المتحكّم إلى الزيارة. */
            foreach (EmployeeClientVisit::pendingItineraryClientIdsFor((int) $user->id) as $pendingClientId) {
                unset($completedVisitClientIds[$pendingClientId]);
            }

            // زيارةٌ أُتمّت اليوم تُلغي «المفتوحة» — كما في الجدول، لا يُحسب
            // `activeVisit` أصلاً حين يكون `hasCompletedVisitToday` صحيحاً
            $openVisitByClient = array_diff_key($openVisitByClient, $completedVisitClientIds);
        }

        // مصدرُ كل عميلٍ في خط سير اليوم — آخرُ صفٍّ له يغلب (عودةٌ أُضيفت بعد الخطّة)
        $itinerarySources = [];

        if ($itineraryMode) {
            $adders = app(\App\Services\Itinerary\DailyItineraryService::class)
                ->describeAdders($itineraryRows->pluck('added_by')->filter()->unique()->all());

            foreach ($itineraryRows as $row) {
                $by = $row->added_by ? ($adders[(int) $row->added_by] ?? null) : null;

                $itinerarySources[(int) $row->client_id] = match (true) {
                    ! $row->added_by => ['tone' => 'plan', 'label' => 'من خط السير المخطَّط'],
                    (int) $row->added_by === (int) $user->id => ['tone' => 'self', 'label' => 'أضفتَه أنت لخط سيرك'],
                    default => ['tone' => 'other', 'label' => 'أضافه: '.($by['name'] ?? '—').(! empty($by['role']) ? ' · '.$by['role'] : '')],
                };
            }
        }

        // رصيدُ العميل في بطاقة الماركر — نفسُ رقم عمود «المستحق» في الجدول
        $clientDueBalances = $this->getClientDueBalances($clients);

        // تنسيق البيانات للخريطة
        $clientsForMap = [];

        foreach ($clients as $client) {
            $location = $client->locations;
            $due = (float) ($clientDueBalances[$client->id] ?? 0);

            if ($location && $location->latitude && $location->longitude && $location->latitude != 0 && $location->longitude != 0) {
                // استخدام نفس المنطق من الصفحة الرئيسية
                $statusColor = '#4361ee'; // اللون الافتراضي
                $statusName = 'غير محدد';

                // التحقق من وجود status_client (نفس ما في الصفحة الرئيسية)
                if ($client->status_client) {
                    $statusColor = $client->status_client->color ?: '#4361ee';
                    $statusName = $client->status_client->name ?: 'غير محدد';
                }

                $needsVisit = $mustVisit && ! isset($completedVisitClientIds[$client->id]);
                $activeVisitId = $openVisitByClient[$client->id] ?? null;

                $clientsForMap[] = [
                    'id' => $client->id,
                    'lat' => (float) $location->latitude,
                    'lng' => (float) $location->longitude,
                    'trade_name' => $client->trade_name ?? 'غير محدد',
                    'code' => $client->code ?? 'غير محدد',
                    'phone' => $client->phone ?? 'غير متوفر',
                    'address' => $location->address ?? 'غير محدد',
                    'status' => $statusName,
                    'statusColor' => $statusColor, // تأكد من إرسال اللون الصحيح
                    'branch' => optional($client->branch)->name ?? 'غير محدد',
                    // مسارُ الفرع كاملاً من الجذر — نفسُ ما تعرضه شاشةُ
                    // العميل تحت «مسار الفرع»، لا اسمُ الورقة وحدَه
                    'branch_path' => $client->branchTreeDisplayPaths()['main'] ?? (optional($client->branch)->name ?? 'غير محدد'),
                    'employee' => optional($client->employee)->name ?? 'غير محدد',
                    'shift_start' => optional($client->latestRelation)->shift_start,
                    'shift_end' => optional($client->latestRelation)->shift_end,
                    'balance' => \App\Support\CurrencySettings::format(abs($due)),
                    'balance_direction' => $due > 0 ? 'مدين' : ($due < 0 ? 'دائن' : ''),
                    // زرُّ البطاقة: «تسجيل الزيارة» لمن يلزمه ذلك، و«التفاصيل»
                    // لغيره — والوجهةُ تختلف حسب وجود زيارةٍ مفتوحة اليوم،
                    // تماماً كرابط الجدول
                    'needs_visit' => $needsVisit,
                    'itinerary_source' => $itinerarySources[$client->id] ?? null,
                    'action_url' => $needsVisit
                        ? ($activeVisitId
                            ? route('clients.visitWorkspace', ['client' => $client->id, 'visit' => $activeVisitId])
                            : route('clients.openVisitWorkspace', $client->id))
                        : route('clients.show', $client->id),
                ];
            }
        }

        return response()->json([
            'clients' => $clientsForMap,
            'total' => count($clientsForMap),
            'itinerary_only' => $itineraryMode,
            'debug' => [
                'user_role' => $user->role,
                'total_clients_found' => $clients->count(),
                'clients_with_location' => count($clientsForMap),
                'hidden_clients_count' => count($hiddenClientIds),
                'hidden_client_ids' => $hiddenClientIds,
            ],
        ]);
    }

    /**
     * نطاق فرع المستخدم في الشجرة: الفرع نفسه + كل أحفاده. null = كل العملاء.
     *
     * @return array<int>|null
     */
    private function resolveUserBranchTreeScope($user): ?array
    {
        return $this->clientAccess->resolveUserBranchTreeScope($user);
    }

    /**
     * تطبيق نطاق شجرة الفرع على استعلام عملاء: فرع المحاسبة أو حي العميل ضمن الشجرة.
     *
     * @param  array<int>|null  $scopeIds
     */
    private function applyClientBranchTreeScope($query, ?array $scopeIds): void
    {
        $this->clientAccess->applyClientBranchTreeScope($query, $scopeIds);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $baseQuery = Client::with(['employee', 'status_client:id,name,color', 'locations', 'neighborhood.region', 'branch:id,name', 'account', 'categoriesClient']);
        $noClients = false;

        /* شجرةُ الفروع كلُّها باستعلامٍ واحد: كلُّ صفٍّ في الجدول يعرض مسارَ
           عقدته من الجذر (`Client::branchTreeDisplayPaths()`)، وبلا هذا يصعد
           كلُّ صفٍّ سلسلةَ أسلافه باستعلامٍ لكل سلف. */
        Branch::preloadCache();

        // نطاق شجرة الفرع للمستخدم: الفرع + كل أحفاده (null = بدون حصر / فرع رئيسي)
        $userBranchScopeIds = $this->resolveUserBranchTreeScope($user);

        $currentDate = now();
        $weekInfo = WeekHelper::getWeekAndYear($currentDate);
        $currentWeek = $weekInfo['week'];
        $currentYear = $weekInfo['year'];

        $arabicDays = WeekHelper::getArabicDays();
        $englishDays = WeekHelper::getEnglishDays();

        $currentDayOfWeek = $currentDate->dayOfWeek;
        $adjustedDayOfWeek = ($currentDayOfWeek + 1) % 7;

        $currentDayName = $arabicDays[$adjustedDayOfWeek];
        $currentDayNameEn = $englishDays[$adjustedDayOfWeek];
        $GeneralClientSettings = GeneralClientSetting::all();

        // إنشاء query منفصل لجلب جميع العملاء للـ select (بدون فلاتر الجدول الزمني)
        $allClientsQuery = Client::select(['id', 'trade_name', 'code']);

        // تطبيق صلاحيات المستخدم الأساسية فقط على allClients (بدون الفلاتر الزمنية)
        if (! hasPermission('clients_view_all_clients')) {
            $matched = $this->clientAccess->applyPermissionCascade($allClientsQuery, $user, $currentDayNameEn, $currentYear, $currentWeek);

            if ($matched === null) {
                // للموظف الذي ليس لديه صلاحيات خاصة
                if ($user->isEmployee()) {
                    $allEmployeeClients = $this->clientAccess->todayItineraryClientIds($user->id, $currentDayNameEn, $currentYear, $currentWeek);

                    if ($allEmployeeClients->isNotEmpty()) {
                        $allClientsQuery->whereIn('id', $allEmployeeClients);
                    } else {
                        $allClientsQuery = $allClientsQuery->whereRaw('1 = 0');
                    }
                }
                // للمستخدم المرتبط بفرع معين — نطبق نطاق الشجرة (الفرع + أحفاده)
                elseif ($user->branch_id) {
                    $this->applyClientBranchTreeScope($allClientsQuery, $userBranchScopeIds);
                }
            }
        }

        // ⭐ إذا اختار المستخدم مجموعة/فرع من فلتر الصفحة، نقصر قائمة العملاء في السيلكت على شجرة هذا الفرع —
        // بنفس مطابقة الجدول في applyFilters() (التبعيّة لا الفرع المحاسبي)، وإلا اختلفت القائمة عن الجدول
        if ($request->filled('region')) {
            $allClientsQuery->inTreeNode((int) $request->region, static::wantsDescendants($request));
        }

        // ⭐ فرع الهيدر على قائمة الاختيار — قبل جلبها لا بعده: كان يُطبَّق أسفل
        // بعد ->get() فتعرض القائمةُ عملاءَ كل الفروع مهما اختير في الهيدر
        if (hasPermission('clients_view_all_clients') && $userBranchScopeIds !== null) {
            $this->applyClientBranchTreeScope($allClientsQuery, $userBranchScopeIds);
        }

        // جلب جميع العملاء للـ select بناءً على صلاحيات المستخدم
        $allClients = $allClientsQuery->orderBy('trade_name')->get();

        /* عميلٌ انتهى دورُه اليوم يختفي من **جدول الفهرس** كما يختفي من
           الخريطة (`getMapData()`): «خلاص يخفي العميل من الخريطة ومن جدول
           الفهرس». يعود بمجرّد صفّ خطّ سيرٍ غير مكتمل — وهو ما يصنعه «سأعود
           لهذا العميل اليوم».

           لمن **لا** يملك `clients_view_all_clients` فقط: المدير يرى كل
           شيء، والفهرس عنده أداةُ متابعةٍ لا قائمةَ عملٍ يومية. */
        if (! hasPermission('clients_view_all_clients')) {
            $finishedTodayIds = EmployeeClientVisit::hiddenFinishedClientIdsFor((int) $user->id);

            if ($finishedTodayIds->isNotEmpty()) {
                $baseQuery->whereNotIn('id', $finishedTodayIds);
            }
        }

        /* شريط «أُنهيت زياراتهم اليوم» فوق الجدول — العملاء المُخفَون أعلاه
           بأسمائهم، وزرُّ «إرجاع لخط السير» لكلٍّ منهم متى سمحت الإعدادات.
           بلا هذا يختفي العميل ولا يعرف المندوب أين ذهب ولا كيف يعيده. */
        $finishedTodayClients = ! hasPermission('clients_view_all_clients') && ! empty($finishedTodayIds) && $finishedTodayIds->isNotEmpty()
            ? Client::whereIn('id', $finishedTodayIds)->orderBy('trade_name')->get(['id', 'trade_name', 'code'])
            : collect();

        // ⭐ تطبيق فلترة الفرع للمستخدمين الذين لديهم صلاحية عرض جميع العملاء
        // إذا اختار فرعاً محدداً من الهيدر، يتم فلترة العملاء حسب هذا الفرع وكل أحفاده
        if (hasPermission('clients_view_all_clients') && $userBranchScopeIds !== null) {
            $this->applyClientBranchTreeScope($baseQuery, $userBranchScopeIds);
        }

        // تطبيق فلترة حسب الصلاحيات — نفس applyPermissionCascade المستعملة أعلاه لقائمة
        // الاختيار (allClientsQuery) وفي شاشة التصدير المجاورة، لا نسخة يدوية منفصلة.
        // كانت النسخة اليدوية القديمة هنا (سلسلة elseif) تُحبَس عند أوّل صلاحيةٍ يملكها
        // المستخدم حتى لو كان نطاقها فارغاً؛ فمن يملك «عرض حسب مجموعات الموظف» مع «عرض
        // حسب خط السير اليومي» معاً (تركيبةٌ شائعة لتجاورهما في شاشة الصلاحيات) لا يصل
        // نطاقه الحقيقي (خط سيره) أبداً — يرى «لا عملاء» رغم امتلاك الصلاحية وامتلاء
        // خط سيره فعلياً.
        if (! hasPermission('clients_view_all_clients')) {
            $matched = $this->clientAccess->applyPermissionCascade($baseQuery, $user, $currentDayNameEn, $currentYear, $currentWeek);

            if ($matched === false) {
                $noClients = true;
            } elseif ($matched === null) {
                // للموظف الذي ليس لديه صلاحيات خاصة - استخدام خط السير اليومي
                if ($user->isEmployee()) {
                    $clientVisits = $this->clientAccess->todayItineraryClientIds($user->id, $currentDayNameEn, $currentYear, $currentWeek);

                    if ($clientVisits->isNotEmpty()) {
                        $baseQuery->whereIn('id', $clientVisits);
                    } else {
                        $noClients = true;
                    }
                }
                // للمستخدم المرتبط بفرع معين — نطاق الشجرة (الفرع + كل أحفاده)
                elseif ($user->branch_id) {
                    $this->applyClientBranchTreeScope($baseQuery, $userBranchScopeIds);
                } else {
                    // لا توجد صلاحية للعرض
                    $noClients = true;
                    $baseQuery->whereRaw('1 = 0');
                }
            }
        }

        // جلب العملاء المخفيين للمستخدم الحالي من قاعدة البيانات
        $hiddenClientIds = HiddenClient::getHiddenClientsForUser($user->id);

        // استبعاد العملاء المخفيين من الـ query
        if (! empty($hiddenClientIds)) {
            $baseQuery->whereNotIn('id', $hiddenClientIds);
        }

        // تطبيق الفلاتر على البيانات المعروضة فقط
        $this->applyFilters($baseQuery, $request);

        // الأحدثُ أولاً — في الفتح الأوّل وفي كل فلترةٍ وتنقّلٍ بين الصفحات
        $baseQuery->orderByDesc('clients.created_at')->orderByDesc('clients.id');

        // إذا كان طلب AJAX، نرجع البيانات فقط
        if ($request->ajax()) {
            return $this->getAjaxResponse($baseQuery, $request, $noClients, $user);
        }

        // للطلب العادي، نرجع البيانات الأساسية للعرض الأولي
        $perPage = $this->resolvePerPage($request);

        // إنشاء paginator فارغ إذا لم يكن هناك عملاء
        if ($noClients) {
            $clients = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        } else {
            $clients = $baseQuery->paginate($perPage)->appends($request->query());
        }

        // جلب بيانات مبسطة للخريطة فقط (مع الفلاتر المطبقة)
        $mapQuery = clone $baseQuery;
        $mapClients = $noClients
            ? collect()
            : $mapQuery
                ->select(['id', 'trade_name', 'code', 'phone', 'status_id', 'branch_id'])
                ->with(['status_client:id,name,color', 'locations:id,client_id,latitude,longitude', 'branch:id,name'])
                ->get();

        // حساب المسافات للعملاء المعروضين فقط
        $clientDistances = $this->calculateClientDistances($clients, $user);

        // حساب البيانات المالية للعملاء المعروضين فقط
        $clientsData = $this->calculateClientData($clients, $currentYear);
        $clientDueBalances = $this->getClientDueBalances($clients);

        $userBranch = $user->branch ?? null;

        // المجموعات الآن هي عقد الشجرة (is_territory=1) تحت branches
        if ($userBranch && $userBranch->is_main) {
            // ✅ فرع رئيسي → كل عقد المناطق
            $regionGroups = Branch::withoutGlobalScopes()
                ->where('is_territory', 1)
                ->orderBy('name')
                ->get();
            // كل الفروع غير الرئيسية + كل المناطق (لبناء شجرة هرمية بدون الجذر)
            $branchTreeNodes = Branch::withoutGlobalScopes()
                ->where(function ($q) {
                    $q->where('is_main', 0)->orWhereNull('is_main');
                })
                ->orderBy('name')
                ->get();
        } else {
            // ✅ غير رئيسي → فقط مناطق ضمن شجرة فرعه
            if ($userBranchScopeIds !== null) {
                $regionGroups = Branch::withoutGlobalScopes()
                    ->where('is_territory', 1)
                    ->whereIn('id', $userBranchScopeIds)
                    ->orderBy('name')
                    ->get();
                $branchTreeNodes = Branch::withoutGlobalScopes()
                    ->whereIn('id', $userBranchScopeIds)
                    ->where(function ($q) {
                        $q->where('is_main', 0)->orWhereNull('is_main');
                    })
                    ->orderBy('name')
                    ->get();
            } else {
                $regionGroups = collect();
                $branchTreeNodes = collect();
            }
        }

        // قائمة «تابع لـ» — المسارُ من الأعلى ونوعُ العقدة؛ نفسُها في شاشة التعيين الجماعي
        $branchTreeOptions = \App\Support\AffiliationTree::pathOptions($branchTreeNodes);

        /* ══════════ إحصاءات رأس الشاشة ══════════

           أربعُ بلاطاتٍ على قياس شاشة الموردين. العدّ من نطاق الاستعلام
           نفسه (`$baseQuery`) لا من الجدول كلّه، فما يراه المستخدم في
           البلاطات هو ما تعدّه صلاحيتُه ونطاقُ فرعه.

           و«النشط»/«الموقوف» من `kpi_role` في شاشة «حالات العملاء» لا من
           `status_id == 1`/`== 2` مثبَّتين — منشأةٌ تسمّي حالاتها كما تشاء
           وتضبط أيَّها نشطة، والعدّ يتبعها. */
        $clientStats = $noClients
            ? ['total' => 0, 'active' => 0, 'stopped' => 0, 'due' => 0]
            : $this->clientIndexStats($baseQuery, $clientDueBalances);

        return view('client::index', [
            'clients' => $clients,
            'clientStats' => $clientStats,
            'allClients' => $allClients,
            'mapClients' => $mapClients,
            'clientsData' => $clientsData,
            'clientDueBalances' => $clientDueBalances,
            'clientDistances' => $clientDistances,
            'Neighborhoods' => Neighborhood::all(),
            'GeneralClientSettings' => $GeneralClientSettings,
            'users' => User::all(),
            'categories' => CategoriesClient::all(),
            'employees' => Employee::all(),
            'creditLimit' => CreditLimit::first(),
            'statuses' => Statuses::assignable()->get()->keyBy('id'),
            'customerTargets' => CustomerTarget::orderBy('priority', 'desc')->get(),
            'Region_groups' => $regionGroups,
            'branchTreeOptions' => $branchTreeOptions,
            /* `Target::find(2)->value` كانت ترمي على معرّفٍ غير موجود —
               `null->value` لا يحميه `??`. و648 رقمٌ مثبَّت بينما
               `monthly_collection_target` عمودٌ في إعدادات العملاء لا يقرؤه
               أحد؛ صار هو المصدر، والهدفُ المخزَّن احتياطُه. */
            'target' => Target::find(2)?->value ?? ClientSettingsHelper::monthlyCollectionTarget(),
            'monthlyTarget' => ClientSettingsHelper::monthlyCollectionTarget(),
            'months' => ['يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'أبريل' => 4, 'مايو' => 5, 'يونيو' => 6, 'يوليو' => 7, 'أغسطس' => 8, 'سبتمبر' => 9, 'أكتوبر' => 10, 'نوفمبر' => 11, 'ديسمبر' => 12],
            'currentYear' => $currentYear,
            'currentDayName' => $currentDayName,
            'hiddenClients' => $hiddenClientIds,
            'finishedTodayClients' => $finishedTodayClients,
            'canReturnFinishedClients' => ClientSettingsHelper::allowReturnFinishedClientSameDay(),
        ]);
    }

    /**
     * حجمُ صفحة قائمة العملاء.
     *
     * قائمةُ «عرض … سجل» في الشاشة ترسل `per_page`، وكان `index()` وشقيقتُها
     * `getAjaxResponse()` تقرآن `perPage` — اسمٌ لا يرسله أحد. فكانت القائمة
     * تُبدَّل ولا يتغيّر شيء، وتبقى الصفحةُ على الافتراضي أبداً. يُقبَل
     * الاسمان معاً كي لا ينكسر رابطٌ قديمٌ محفوظ، والقيمةُ محصورةٌ في خيارات
     * القائمة نفسها فلا يطلب رابطٌ مصنوعٌ بيدٍ عشرةَ آلاف صفٍّ دفعةً واحدة.
     */
    private function resolvePerPage(Request $request): int
    {
        $allowed = [10, 25, 50, 100];
        $requested = (int) $request->get('per_page', $request->get('perPage', 10));

        return in_array($requested, $allowed, true) ? $requested : 10;
    }

    /**
     * بلاطاتُ رأس الفهرس من نطاق الاستعلام نفسه — تُحسب في الفتح الأوّل وتعود
     * مع كل فلترةٍ بـAJAX، فلا تبقى أرقامُ ما قبل الفلترة فوق جدولٍ تغيّر.
     *
     * @return array{total: int, active: int, stopped: int, due: int}
     */
    private function clientIndexStats($baseQuery, iterable $clientDueBalances): array
    {
        // بلا ترتيب — العدُّ لا يحتاجه
        $statsQuery = (clone $baseQuery)->reorder();

        return [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->whereIn('status_id', Statuses::activeClientStatusIds())->count(),
            'stopped' => (clone $statsQuery)->whereIn('status_id', Statuses::stoppedClientStatusIds())->count(),
            // العملاء المستحقّ عليهم في الصفحة المعروضة — الرصيد يُحسب
            // بنداءٍ لكل عميل، فعدُّه على الجدول كلّه استعلامٌ لكل صفّ
            'due' => collect($clientDueBalances)->filter(fn ($v) => (float) $v > 0)->count(),
        ];
    }

    /** البلاطاتُ بتنسيق لغة النظام — كما تكتبها الشاشةُ في الفتح الأوّل */
    private function formatClientIndexStats(array $stats): array
    {
        return array_map(fn ($value) => \App\Support\LanguageSettings::formatNumber($value, 0), $stats);
    }

    private function getAjaxResponse($baseQuery, $request, $noClients, $user)
    {
        $perPage = $this->resolvePerPage($request);
        $page = $request->get('page', 1);

        // إذا لم يكن هناك عملاء، إرجاع paginator فارغ
        if ($noClients) {
            $emptyPaginator = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return response()->json([
                'success' => true,
                'html' => view('client::partials.client_cards', [
                    'clients' => $emptyPaginator,
                    'clientsData' => [],
                    'clientDueBalances' => [],
                    'clientDistances' => [],
                    'statuses' => Statuses::assignable()->get()->keyBy('id'),
                    'months' => [
                        'يناير' => 1,
                        'فبراير' => 2,
                        'مارس' => 3,
                        'أبريل' => 4,
                        'مايو' => 5,
                        'يونيو' => 6,
                        'يوليو' => 7,
                        'أغسطس' => 8,
                        'سبتمبر' => 9,
                        'أكتوبر' => 10,
                        'نوفمبر' => 11,
                        'ديسمبر' => 12,
                    ],
                    'currentYear' => now()->year,
                    'clientTotalSales' => [],
                ])->render(),
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'has_more_pages' => false,
                    'on_first_page' => true,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'stats' => $this->formatClientIndexStats(['total' => 0, 'active' => 0, 'stopped' => 0, 'due' => 0]),
                'results_label' => __('client_settings.index.results_count', ['count' => \App\Support\LanguageSettings::formatNumber(0, 0)]),
            ]);
        }

        // جلب العملاء المخفيين للمستخدم الحالي من قاعدة البيانات
        $hiddenClientIds = HiddenClient::getHiddenClientsForUser($user->id);

        // استبعاد العملاء المخفيين من query قبل جلب البيانات
        if (! empty($hiddenClientIds)) {
            $baseQuery->whereNotIn('id', $hiddenClientIds);
        }

        /* الصفحةُ من قاعدة البيانات بترتيب الفهرس (الأحدث أولاً) — كانت تجلب كلَّ
           عملاء النطاق بعلاقاتهم ثم ترتّبهم بالمسافة في PHP، فتخالف الصفحةُ الثانية
           ترتيبَ الأولى المعروضة عند الفتح. والمسافةُ باقيةٌ عموداً في الجدول. */
        $pagedClients = $baseQuery->paginate($perPage, ['*'], 'page', $page)->appends($request->except('page'));
        $stats = $this->clientIndexStats($baseQuery, []);

        if ($pagedClients->isEmpty()) {
            $emptyPaginator = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return response()->json([
                'success' => true,
                'html' => view('client::partials.client_cards', [
                    'clients' => $emptyPaginator,
                    'clientsData' => [],
                    'clientDueBalances' => [],
                    'clientDistances' => [],
                    'statuses' => Statuses::assignable()->get()->keyBy('id'),
                    'months' => [
                        'يناير' => 1,
                        'فبراير' => 2,
                        'مارس' => 3,
                        'أبريل' => 4,
                        'مايو' => 5,
                        'يونيو' => 6,
                        'يوليو' => 7,
                        'أغسطس' => 8,
                        'سبتمبر' => 9,
                        'أكتوبر' => 10,
                        'نوفمبر' => 11,
                        'ديسمبر' => 12,
                    ],
                    'currentYear' => now()->year,
                    'clientTotalSales' => [],
                ])->render(),
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'has_more_pages' => false,
                    'on_first_page' => true,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'stats' => $this->formatClientIndexStats($stats),
                'results_label' => __('client_settings.index.results_count', ['count' => \App\Support\LanguageSettings::formatNumber(0, 0)]),
            ]);
        }

        // المسافاتُ والبياناتُ المالية للصفحة المعروضة وحدها
        $clientDistances = $this->calculateClientDistances($pagedClients, $user);
        $clientsData = $this->calculateClientData($pagedClients, now()->year);
        $clientDueBalances = $this->getClientDueBalances($pagedClients);
        $stats['due'] = collect($clientDueBalances)->filter(fn ($v) => (float) $v > 0)->count();

        return response()->json([
            'success' => true,
            'html' => view('client::partials.client_cards', [
                'clients' => $pagedClients,
                'clientsData' => $clientsData,
                'clientDueBalances' => $clientDueBalances,
                'clientDistances' => $clientDistances,
                'statuses' => Statuses::assignable()->get()->keyBy('id'),
                'months' => [
                    'يناير' => 1,
                    'فبراير' => 2,
                    'مارس' => 3,
                    'أبريل' => 4,
                    'مايو' => 5,
                    'يونيو' => 6,
                    'يوليو' => 7,
                    'أغسطس' => 8,
                    'سبتمبر' => 9,
                    'أكتوبر' => 10,
                    'نوفمبر' => 11,
                    'ديسمبر' => 12,
                ],
                'currentYear' => now()->year,
                'clientTotalSales' => $this->getClientTotalSales($pagedClients),
            ])->render(),
            'pagination' => [
                'current_page' => $pagedClients->currentPage(),
                'last_page' => $pagedClients->lastPage(),
                'has_more_pages' => $pagedClients->hasMorePages(),
                'on_first_page' => $pagedClients->onFirstPage(),
                'per_page' => $pagedClients->perPage(),
                'total' => $pagedClients->total(),
                'from' => $pagedClients->firstItem(),
                'to' => $pagedClients->lastItem(),
            ],
            'stats' => $this->formatClientIndexStats($stats),
            'results_label' => __('client_settings.index.results_count', ['count' => \App\Support\LanguageSettings::formatNumber($pagedClients->total(), 0)]),
        ]);
    }

    public function hideFromMap(Request $request, $clientId)
    {
        try {
            $client = Client::findOrFail($clientId);
            $userId = auth()->id();

            // ✅ الخطوة 1: جلب آخر زيارة للعميل من نفس الموظف اليوم
            // لم نعد نجعل وجود الزيارة إلزامياً لإخفاء العميل، ولكن إذا وجدت زيارة مفتوحة، نسجل الانصراف.
            $lastVisit = Visit::where('employee_id', $userId)
                ->where('client_id', $clientId)
                ->whereDate('visit_date', now()->toDateString())
                ->whereNull('departure_time') // فقط الزيارات المفتوحة
                // زيارة مساحة العمل لا يُنهيها الإخفاء — تُنهى من «إنهاء الزيارة» وحده
                ->autoDepartable()
                ->latest()
                ->first();

            // ✅ الخطوة 2: تسجيل الانصراف مباشرة (أو تحديثه إذا كان موجود) إذا كانت هناك زيارة مفتوحة
            $departureTime = null;
            $arrivalTime = null;
            $visitDuration = null;
            $visitId = null;

            if ($lastVisit) {
                $lastVisit->update([
                    'departure_time' => now(),
                ]);

                $departureTime = now()->format('H:i');
                $arrivalTime = $lastVisit->arrival_time->format('H:i');
                $visitDuration = $lastVisit->arrival_time->diffInMinutes(now()).' دقيقة';
                $visitId = $lastVisit->id;
            } else {
                // إذا لم تكن هناك زيارة مفتوحة، يمكننا افتراض أن الزيارة "مكتملة" بطريقة أخرى (مثل ملاحظة أو تسجيل خارجي)
                // ولا نحتاج لإرجاع خطأ يمنع الإخفاء.
            }

            // ✅ الخطوة 3: التحقق من عدم وجود إخفاء سابق لا يزال فعال
            $existingHidden = HiddenClient::where('user_id', $userId)
                ->where('client_id', $clientId)
                ->where('expires_at', '>', now())
                ->first();

            if ($existingHidden) {
                return response()->json([
                    'success' => false,
                    'message' => 'العميل مخفي بالفعل.',
                ]);
            }

            // ✅ الخطوة 4: إنشاء سجل جديد للإخفاء
            $hideDurationHours = ClientSettingsHelper::mapHideDurationHours();

            HiddenClient::create([
                'user_id' => $userId,
                'client_id' => $clientId,
                'hidden_at' => now(),
                'expires_at' => now()->addHours($hideDurationHours),
            ]);

            OperationLog::record(
                'hidden_from_map',
                self::LOG_MODULE,
                $client,
                'إخفاء العميل «'.$client->trade_name.'» من الخريطة لمدة '.$hideDurationHours.' ساعة'
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إخفاء العميل بنجاح لمدة '.$hideDurationHours.' ساعة.'.($lastVisit ? ' وتم تسجيل الانصراف.' : ''),
                'client_id' => $clientId,
                'client_name' => $client->trade_name,
                'visit_id' => $visitId,
                'arrival_time' => $arrivalTime,
                'departure_time' => $departureTime,
                'visit_duration' => $visitDuration,
                'expires_at' => now()->addHours($hideDurationHours)->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'hide_from_map');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إخفاء العميل.',
            ], 500);
        }
    }

    public function storeVisit(Request $request, $clientId)
    {
        // التحقق من صحة البيانات المدخلة
        $validated = $request->validate([
            'current_latitude' => 'required|numeric|between:-90,90',
            'current_longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ], [
            'current_latitude.required' => 'لم يتم تحديد موقعك الحالي',
            'current_longitude.required' => 'لم يتم تحديد موقعك الحالي',
            'current_latitude.between' => 'إحداثيات الموقع غير صحيحة',
            'current_longitude.between' => 'إحداثيات الموقع غير صحيحة',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
        ]);

        // جلب بيانات العميل
        $client = Client::findOrFail($clientId);

        // نفس فحص النطاق في registerVisit() أعلاه — بلا هذا التحقّق يستطيع
        // من يملك صلاحية تسجيل الزيارات العامة إنشاء زيارةٍ حقيقية على
        // عميلٍ خارج نطاقه، وتلك الزيارة تُمرَّر لاحقاً بصفتها ملكه في
        // `VisitWorkspaceController::authorizeVisitOwner()`
        abort_unless(
            $this->clientAccess->canAccessClient(auth()->user(), $client->id),
            403,
            'لا يمكنك تسجيل زيارة لعميل غير مسموح لك بالوصول إليه.'
        );

        // جلب موقع العميل من جدول locations
        $clientLocation = Location::where('client_id', $client->id)
            ->clientPoint()
            ->latest()->first();

        // التحقق من وجود موقع للعميل
        if (! $clientLocation || is_null($clientLocation->latitude) || is_null($clientLocation->longitude)) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد موقع مسجل لهذا العميل. يرجى تحديث بيانات العميل أولاً.',
                ], 400);
            }

            return redirect()->back()
                ->with('error', 'لا يوجد موقع مسجل لهذا العميل. يرجى تحديث بيانات العميل أولاً.')
                ->withInput();
        }

        // حساب المسافة بين الموقع الحالي للموظف وموقع العميل
        $distance = $this->calculateDistance(
            $validated['current_latitude'],
            $validated['current_longitude'],
            $clientLocation->latitude,
            $clientLocation->longitude
        );

        // الحد الأقصى المسموح به — قابل للتعديل من إعدادات وحدات العملاء، أو
        // بنطاقٍ مخصَّص لهذا الموظف
        $maxDistance = ClientSettingsHelper::visitProximityRadiusKmFor(auth()->user());

        // التحقق من أن المستخدم ضمن النطاق المسموح
        if ($distance > $maxDistance) {
            $errorMessage = sprintf(
                'أنت خارج نطاق موقع العميل. المسافة الحالية: %.2f كم. يجب أن تكون ضمن نطاق %.1f كم.',
                $distance,
                $maxDistance
            );

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 400);
            }

            return redirect()->back()
                ->with('error', $errorMessage)
                ->with('warning', 'يرجى الاقتراب من موقع العميل وإعادة المحاولة.')
                ->withInput();
        }

        // التحقق من عدم وجود زيارة مفتوحة (بدون انصراف) لنفس العميل في نفس اليوم
        $existingVisit = Visit::where('employee_id', auth()->id())
            ->where('client_id', $client->id)
            ->whereDate('visit_date', now()->toDateString())
            ->whereNull('departure_time')
            ->first();

        if ($existingVisit) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لديك زيارة مفتوحة بالفعل لهذا العميل اليوم. يرجى إنهاء الزيارة الحالية (الانصراف) قبل تسجيل زيارة جديدة.',
                ], 400);
            }

            return redirect()->back()
                ->with('warning', 'لديك زيارة مفتوحة بالفعل لهذا العميل اليوم.')
                ->with('info', 'يرجى إنهاء الزيارة الحالية (الانصراف) قبل تسجيل زيارة جديدة.');
        }

        // الموظفُ الميداني يسجّل الزيارة داخل دوامه فقط
        if ($blocked = app(\App\Services\Attendance\FieldWorkGuard::class)->visitStartBlock((int) auth()->id())) {
            return redirect()->back()->with('error', $blocked);
        }

        // تسجيل الزيارة في قاعدة البيانات
        try {
            $visit = Visit::create([
                'employee_id' => auth()->id(),
                'client_id' => $client->id,
                'visit_date' => now(),
                'arrival_time' => now(),
                'started_at' => now(),
                'stage' => Visit::STAGE_IN_PROGRESS,
                'visit_number' => next_document_number('visit'),
                'employee_latitude' => $validated['current_latitude'],
                'employee_longitude' => $validated['current_longitude'],
                'client_latitude' => $clientLocation->latitude,
                'client_longitude' => $clientLocation->longitude,
                'distance' => $distance,
                'gps_accuracy' => $validated['accuracy'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'recording_method' => 'manual',
                'is_approved' => false,
            ]);

            // تسجيل في الـ Log
            OperationLog::record(
                'visit_registered',
                self::LOG_MODULE,
                $client,
                'تسجيل زيارة للعميل «'.$client->trade_name.'»'
            );

            $successMessage = sprintf(
                'تم تسجيل الزيارة بنجاح! المسافة من موقع العميل: %.2f كم',
                $distance
            );

            $workspaceUrl = route('clients.visitWorkspace', ['client' => $client->id, 'visit' => $visit->id]);

            // إذا كان الطلب AJAX، نرجع JSON
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'visit_id' => $visit->id,
                    'redirect' => $workspaceUrl,
                ], 200);
            }

            // الانتقال إلى مساحة عمل الزيارة لإتمامها
            return redirect($workspaceUrl)->with('success', $successMessage);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store_visit');

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تسجيل الزيارة. يرجى المحاولة مرة أخرى.',
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء تسجيل الزيارة. يرجى المحاولة مرة أخرى.')
                ->withInput();
        }
    }

    public function showInMap(Request $request, $clientId)
    {
        try {
            $client = Client::findOrFail($clientId);
            $userId = auth()->id();

            // حذف السجل من قاعدة البيانات
            HiddenClient::where('user_id', $userId)
                ->where('client_id', $clientId)
                ->delete();

            OperationLog::record(
                'shown_in_map',
                self::LOG_MODULE,
                $client,
                'إظهار العميل «'.$client->trade_name.'» في الخريطة من جديد'
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إظهار العميل بنجاح',
                'client_id' => $clientId,
                'client_name' => $client->trade_name,
            ]);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'show_in_map');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إظهار العميل',
            ], 500);
        }
    }

    public function getHiddenClients(Request $request)
    {
        try {
            $userId = auth()->id();

            // جلب العملاء المخفيين من قاعدة البيانات مع معلومات العميل
            $hiddenClients = HiddenClient::where('user_id', $userId)
                ->where('expires_at', '>', now())
                ->with('client:id,trade_name,code')
                ->get()
                ->map(function ($hidden) {
                    return [
                        'id' => $hidden->client_id,
                        'name' => $hidden->client->trade_name,
                        'code' => $hidden->client->code,
                        'hidden_at' => $hidden->hidden_at->format('Y-m-d H:i:s'),
                        'expires_at' => $hidden->expires_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'success' => true,
                'hidden_clients' => $hiddenClients,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'getHiddenClients');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب العملاء المخفيين',
            ], 500);
        }
    }

    // تعديل دالة getAjaxResponse لاستبعاد العملاء المخفيين من قاعدة البيانات
    public function updateCreditLimit(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'value' => 'required|numeric|min:0',
            // المدّة تصل من نفس النافذة، وغيابُها يعني أنّ الحقل مطفأٌ في
            // الحقول الإضافية — لا أنّ المستخدم أراد محوَها
            'credit_period' => 'nullable|integer|min:0',
        ]);

        try {
            // تحديث أو إنشاء الحد الائتماني للعميل
            CreditLimit::updateOrCreateForClient(
                $request->client_id,
                $request->value
            );

            $logMessage = 'تحديث الحد الائتماني للعميل #'.$request->client_id.' إلى '.$request->value;

            if ($request->has('credit_period')) {
                $creditPeriod = $request->input('credit_period');
                $creditPeriod = ($creditPeriod === null || $creditPeriod === '') ? null : (int) $creditPeriod;

                Client::whereKey($request->client_id)->update(['credit_period' => $creditPeriod]);

                $logMessage .= '، والمدة الائتمانية إلى '.($creditPeriod === null ? 'بلا مدّة' : $creditPeriod.' يوم');
            }

            OperationLog::record(
                'credit_limit_updated',
                self::LOG_MODULE,
                (int) $request->client_id,
                $logMessage
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الحد الائتماني بنجاح!',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'updateCreditLimit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحديث الحد الائتماني لجميع العملاء
     */
    /**
     * جلب قائمة العملاء بصيغة JSON لـ Select2
     */
    /**
     * جلب قائمة العملاء بصيغة JSON لـ Select2 مع Pagination
     */
    public function getClientsForSelect(Request $request)
    {
        try {
            $user = auth()->user();
            $search = $request->input('search', '');
            $page = $request->input('page', 1);
            $perPage = 30;

            // الحصول على معلومات اليوم الحالي لخط السير
            $currentDate = now();
            $weekInfo = WeekHelper::getWeekAndYear($currentDate);
            $currentWeek = $weekInfo['week'];
            $currentYear = $weekInfo['year'];
            $englishDays = WeekHelper::getEnglishDays();
            $currentDayOfWeek = $currentDate->dayOfWeek;
            $adjustedDayOfWeek = ($currentDayOfWeek + 1) % 7;
            $currentDayNameEn = $englishDays[$adjustedDayOfWeek];

            // إنشاء query للعملاء بناءً على صلاحيات المستخدم
            $query = Client::select(['id', 'trade_name', 'code']);

            // ⭐ تطبيق فلترة الفرع للمستخدمين الذين لديهم صلاحية عرض جميع العملاء
            // إذا اختار فرعاً محدداً من الهيدر، يتم فلترة العملاء حسب هذا الفرع
            if (hasPermission('clients_view_all_clients') && $user->branch_id) {
                // تحقق أن الفرع المختار ليس الفرع الرئيسي (جميع الفروع)
                $mainBranch = Branch::where('is_main', true)->first();
                if (! $mainBranch || $user->branch_id != $mainBranch->id) {
                    $query->inTreeNode((int) $user->branch_id);
                }
            }

            // تطبيق الصلاحيات — نفس applyPermissionCascade المستعملة في index()، لا نسخة
            // يدوية منفصلة (كانت سلسلة elseif هنا تُحبَس عند أوّل صلاحيةٍ يملكها المستخدم
            // حتى لو كان نطاقها فارغاً، فمن يملك أكثر من صلاحية تتالٍ معاً لا يصل نطاقه
            // الحقيقي الثاني أبداً — نفس علّة index() المُصلَحة).
            if (! hasPermission('clients_view_all_clients')) {
                $matched = $this->clientAccess->applyPermissionCascade($query, $user, $currentDayNameEn, $currentYear, $currentWeek);

                if ($matched === false) {
                    $query->whereRaw('1 = 0');
                } elseif ($matched === null) {
                    // للموظف الذي ليس لديه صلاحيات خاصة - جلب العملاء المجدولين لليوم الحالي
                    if ($user->isEmployee()) {
                        $allEmployeeClients = $this->clientAccess->todayItineraryClientIds($user->id, $currentDayNameEn, $currentYear, $currentWeek);

                        if ($allEmployeeClients->isNotEmpty()) {
                            $query->whereIn('id', $allEmployeeClients);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    }
                    // للمستخدم المرتبط بفرع معين
                    elseif ($user->branch_id) {
                        $mainBranchName = Branch::where('is_main', true)->value('name');
                        $currentBranchName = Branch::find($user->branch_id)->name;

                        if ($currentBranchName !== $mainBranchName) {
                            $query->inTreeNode((int) $user->branch_id);
                        }
                    }
                }
            }

            // البحث
            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('trade_name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            }

            // جلب العملاء مع Pagination
            $clients = $query->orderBy('trade_name')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $clients->items(),
                'total' => $clients->total(),
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'clients_for_select');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function updateAllClientsCreditLimit(Request $request)
    {
        $request->validate([
            'value' => 'required|numeric|min:0',
            'exclude_clients' => 'nullable|array',
            'exclude_clients.*' => 'numeric|exists:clients,id',
        ]);

        try {
            $user = auth()->user();
            $excludeClientIds = $request->input('exclude_clients', []);

            // الحصول على قائمة العملاء بناءً على صلاحيات المستخدم
            $query = Client::query();

            if (! hasPermission('clients_view_all_clients')) {
                if ($user->isEmployee()) {
                    $query->where('employee_id', $user->id);
                } elseif ($user->branch_id) {
                    $query->inTreeNode((int) $user->branch_id);
                }
            }

            // استثن العملاء المختارين من قبل المستخدم
            if (! empty($excludeClientIds)) {
                $query->whereNotIn('id', $excludeClientIds);
            }

            // تحديث الحد الائتماني لجميع العملاء
            $clients = $query->pluck('id');

            foreach ($clients as $clientId) {
                CreditLimit::updateOrCreateForClient($clientId, $request->value);
            }

            $count = $clients->count();
            $excludedCount = count($excludeClientIds);

            $message = "تم تحديث الحد الائتماني لـ {$count} عميل بنجاح!";
            if ($excludedCount > 0) {
                $message .= "<br><small class='text-muted'>تم استثناء {$excludedCount} عميل من التحديث</small>";
            }

            OperationLog::record(
                'credit_limit_bulk_updated',
                self::LOG_MODULE,
                null,
                'تحديث الحد الائتماني لـ '.$count.' عميل إلى '.$request->value.($excludedCount ? ' (باستثناء '.$excludedCount.')' : '')
            );

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $count,
                'excluded_count' => $excludedCount,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'updateAllClientsCreditLimit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * الحصول على الحد الائتماني لعميل
     */
    public function getClientCreditLimit($clientId)
    {
        try {
            $creditLimit = CreditLimit::getClientCreditLimit($clientId);

            return response()->json([
                'success' => true,
                'creditLimit' => $creditLimit ? $creditLimit->value : 0,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'getClientCreditLimit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    public function create()
    {
        $user = auth()->user(); // المستخدم الحالي

        /* موظّفون من `employees` لا من `users`.

           كانت القائمة `User::where('role','employee')` — فالقيمةُ المرسَلة
           في `employee_client_id[]` معرّفُ **مستخدم**، بينما العمودُ الذي
           تُكتب فيه (`client_employee.employee_id`) معرّفُ **موظف**،
           وعلاقةُ `Client::employees()` تقرؤه من جدول `employees`. والجدولان
           لا تتطابق معرّفاتهما (الموظف ٢ هو المستخدم ٦)، فالموظّف المعيَّن
           عند إضافة عميل إمّا لا يظهر في شاشة العرض أصلاً أو يظهر شخصٌ آخر.
           شاشةُ التعديل كانت تستعمل `Employee::all()` — فاختلفت الشاشتان في
           معنى نفس الحقل. */
        $employees = $this->employeesForClientAssignment();
        $categories = CategoriesClient::all();
        $branches = Branch::all();
        $newCode = peek_document_number('customer');

        $GeneralClientSettings = GeneralClientSetting::all();

        // إذا كان الجدول فارغًا، قم بإنشاء قيم افتراضية (مفعلة بالكامل)
        if ($GeneralClientSettings->isEmpty()) {
            $defaultSettings = [
                ['key' => 'image', 'name' => 'صورة', 'is_active' => true],
                ['key' => 'location', 'name' => 'الموقع على الخريطة', 'is_active' => true],
                ['key' => 'credit_duration', 'name' => 'المدة الائتمانية', 'is_active' => true],
            ];

            $GeneralClientSettings = collect($defaultSettings)->map(fn ($item) => (object) $item);
        }

        $clientBranchTreeOptions = $this->branchesForClientTreeSelect($user);
        $clientBranchTreeNodes = $this->clientBranchTreeNodes($user);

        return view('client::create', compact(
            'employees',
            'newCode',
            'categories',
            'GeneralClientSettings',
            'clientBranchTreeOptions',
            'clientBranchTreeNodes'
        ));
    }

    /**
     * كتابةُ موقع العميل في الشجرة على عمودَيه المختلفَين في المعنى.
     *
     * `tree_node_id` هو ما اختاره المستخدم كما هو — منطقةً أو فرعاً أو منفذاً.
     * و`branch_id` هو الفرعُ المحاسبيّ المشتقّ منه، فالقيودُ والفواتيرُ وتقاريرُ
     * المبيعات حسب الفرع لا تصلح على صفِّ منطقة. وهذه هي السياسةُ المكتوبة في
     * `branches.node_types.explain`.
     *
     * وموضعٌ واحدٌ للاشتقاق كي لا تفترق `store()` عن `update()`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyTreeNodeToClientData(array $data, Branch $node): array
    {
        $data['tree_node_id'] = (int) $node->id;
        $data['branch_id'] = $node->resolveAccountingBranchId() ?? (int) $node->id;

        return $data;
    }

    /**
     * عقد شجرة الفروع اللازمة لمُنتقي «موقع العميل في الشجرة» المتدرّج.
     *
     * كلُّ عقدةٍ داخل نطاق المستخدم صالحةٌ للاختيار — منطقةً كانت أو فرعاً أو
     * مجموعةً أو منفذاً، وسطى كانت أو ورقة. وكان الحقلُ سابقاً يقبل الأوراقَ
     * وحدَها (`is_leaf`) فيُحمَّل المستخدمُ على النزول لآخر الشجرة دائماً.
     *
     * تُرسَل `node_type` و`type_label` مع كل عقدة كي تُميّز الشاشةُ المنطقةَ من
     * الفرع من المنفذ في نفس القائمة، بنفس مفردات شجرة الفروع.
     *
     * @return array<int, array{id:int, name:string, parent_id:?int, selectable:bool, node_type:string, type_label:string}>
     */
    protected function clientBranchTreeNodes(?User $user = null, ?int $keepId = null): array
    {
        $allowed = $this->branchesForClientTreeSelect($user);

        $nodes = [];
        $put = function (Branch $node, bool $selectable) use (&$nodes) {
            $id = (int) $node->id;
            $type = $node->nodeTypeKey();
            $nodes[$id] = [
                'id' => $id,
                'name' => $node->name,
                'parent_id' => $node->parent_id ? (int) $node->parent_id : null,
                'selectable' => $selectable || ($nodes[$id]['selectable'] ?? false),
                'node_type' => $type,
                // نوعُ العرض لا المخزَّن — المناطق والمنافذ القديمة مخزَّنةٌ «مجموعة»
                'type_label' => $node->displayTypeLabel(),
            ];
        };

        foreach ($allowed as $node) {
            $put($node, true);
        }

        /* عقدةُ العميل الحالية قد تقع خارج النطاق — نُقلت في الشجرة، أو تغيّر
           نطاقُ الموظف بعد إنشائه. فتُضمّ هي وسلسلةُ أسلافها كي تظهر الشاشةُ
           بما هو محفوظٌ فعلاً، والأسلافُ للتنقّل لا للاختيار. */
        if ($keepId && ! isset($nodes[(int) $keepId])) {
            $extra = Branch::withoutGlobalScopes()->find($keepId);
            if ($extra) {
                foreach ($extra->lineageFromRoot() as $ancestor) {
                    $put($ancestor, (int) $ancestor->id === (int) $keepId);
                }
            }
        }

        return array_values($nodes);
    }

    /**
     * الموظفون المتاحون لإسنادهم إلى عميل — نقطةٌ واحدة لكل شاشةٍ تُسند.
     *
     * النطاق من «أقسام الموظفين المسموح تعيينهم للعملاء» في إعدادات وحدات
     * العملاء ← تبويب الموظفين. والفراغ يعني كل الأقسام.
     *
     * **المعيَّنون حالياً يبقَون في القائمة** ولو خرج قسمُهم من النطاق: كل
     * شاشات الإسناد تُرسل القائمةَ كاملةً ثم تُزامَن بها
     * (`sync()` في `ClientEmployeeController::assignEmployees`، وحذفٌ ثمّ
     * إعادةُ إنشاء في `update()`) — فموظّفٌ غائبٌ عن القائمة يُفكّ إسنادُه
     * بصمت عند أوّل حفظ، بلا أن يطلب أحدٌ ذلك.
     */
    protected function employeesForClientAssignment(?Client $client = null): Collection
    {
        $employees = ClientSettingsHelper::scopeEmployeesToClientDepartments(
            Employee::query()->with('department')
        )->get();

        if (! $client) {
            return $employees;
        }

        $missing = array_diff(
            $client->employees->pluck('id')->all(),
            $employees->pluck('id')->all()
        );

        return $missing
            ? $employees->merge(Employee::with('department')->whereIn('id', $missing)->get())->unique('id')->values()
            : $employees;
    }

    /**
     * قاعدةُ `employee_client_id.*` في إضافة العميل وتعديله — نطاقُ القائمة
     * (`employeesForClientAssignment()`) مفروضاً على الخادم أيضاً، كما في
     * `ClientEmployeeController::assignEmployees()`. كان `store()`/`update()`
     * يحفظان أيَّ معرّفٍ يصل: القائمةُ تُضيّق النافذة، والطلبُ المصنوع بيدٍ
     * يتجاوزها. الفراغ في الإعداد = كل الأقسام، والمعيَّنون حالياً مستثنَون
     * (خروجُ قسمِهم من النطاق لاحقاً لا يمنع حفظ التعديل).
     */
    protected function employeeAssignmentRules(?Client $client = null): array
    {
        $allowedDepartments = ClientSettingsHelper::clientEmployeeDepartmentIds();
        $currentIds = $client
            ? $client->employees->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];

        return [
            'employee_client_id' => 'nullable|array',
            'employee_client_id.*' => [
                'integer',
                'exists:employees,id',
                function ($attribute, $value, $fail) use ($allowedDepartments, $currentIds) {
                    if (! $allowedDepartments || in_array((int) $value, $currentIds, true)) {
                        return;
                    }

                    if (! Employee::whereKey($value)->whereIn('department_id', $allowedDepartments)->exists()) {
                        $fail('أحد الموظفين المختارين خارج الأقسام المسموح تعيينها للعملاء في إعدادات وحدات العملاء.');
                    }
                },
            ],
        ];
    }

    /**
     * عقدُ الشجرة المتاحة لربط العميل، ضمن نطاق المستخدم.
     *
     * كانت أوراقَ الشجرة وحدَها: أيُّ عقدةٍ لها أبناءٌ مستبعَدة. فالمنشأةُ
     * التي تريد عميلاً على **منطقة** أو على **منفذٍ** فوقه فروع لا تجد ما
     * تختاره، وتُدفع دفعاً إلى أعمق ورقةٍ ولو لم تكن هي موقعَ العميل.
     *
     * سببُ القيد أنّ `clients.branch_id` كان يحمل معنيين معاً — الموقعَ
     * التنظيميّ والفرعَ المحاسبيّ — فعقدةٌ وسطى فيه تعني قيوداً على صفِّ
     * منطقة. وقد فُصل المعنيان (`clients.tree_node_id`)، فسقط سببُ القيد
     * وسقط معه القيد: كلُّ العقد في النطاق صالحةٌ للاختيار.
     */
    protected function branchesForClientTreeSelect(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        /* ── فرعُ الترويسة يحكم القائمة ──

           مبدِّلُ الفروع في الترويسة يكتب `users.branch_id` مباشرةً
           (`BranchesController::switchBranch` — لا جلسة، انظر
           `current_branch_id()`). فالفرعُ المختار هناك هو ما يجب أن تُشتقّ
           منه قائمةُ «الفرع في الشجرة» في إضافة العميل وتعديله: أبناؤه
           وأحفادُه وحدهم.

           كان مسارُ الموظف أعلاه يسبق هذا فيقرأ `regionGroups` ويتجاهل
           اختيارَ الترويسة كلَّه — فيرى الموظفُ نفسَ القائمة مهما بدّل. */
        $headerBranch = $user->branch ?? null;
        $headerScopeIds = ($headerBranch && ! $headerBranch->is_main)
            ? collect([(int) $headerBranch->id])->merge($headerBranch->descendantBranchIds())->unique()->all()
            : null;

        // النطاق الأصليّ للمستخدم — مجموعاتُ المناطق للموظف، وإلّا الشجرة كلّها
        $userScopeIds = null;

        if ($user->isEmployee()) {
            $groups = $user->regionGroups;
            if ($groups->isNotEmpty()) {
                $ids = collect();
                foreach ($groups as $b) {
                    if (! $b) {
                        continue;
                    }
                    $ids->push((int) $b->id);
                    $ids = $ids->merge($b->descendantBranchIds());
                }
                $userScopeIds = $ids->unique()->all();
            }
        }

        /* تقاطعُ النطاقين لا أحدُهما: اختيارُ الترويسة يضيّق، ولا يوسّع
           صلاحيةَ موظفٍ إلى فرعٍ خارج مجموعاته.

           والتقاطعُ الفارغ يعود إلى نطاق المستخدم وحده: فرعُ الموظف المسجَّل
           في `users.branch_id` قد لا يقع أصلاً داخل مجموعات مناطقه (بيانات
           سابقة، أو فرعٌ إداريّ لا تشغيليّ) — فلو تُرك التقاطعُ فارغاً لبقيت
           قائمةُ الفروع خاليةً فلا يستطيع إضافة عميلٍ إطلاقاً. */
        $scopeIds = match (true) {
            $headerScopeIds !== null && $userScopeIds !== null => array_values(array_intersect($headerScopeIds, $userScopeIds)) ?: $userScopeIds,
            $headerScopeIds !== null => $headerScopeIds,
            $userScopeIds !== null => $userScopeIds,
            default => null,
        };

        $query = Branch::withoutGlobalScopes();

        if ($scopeIds !== null) {
            $query->whereIn('id', $scopeIds ?: [0]);
        }

        return $query->orderBy('code')->orderBy('id')->get();
    }

    private function getNeighborhoodFromGoogle($latitude, $longitude)
    {
        /* بلا إحداثيات لا نداء. عميلٌ بلا موقع — الموقع صار غير مطلوب من
           «إعدادات وحدات العملاء» — كان يرسل `latlng=,` فيرفضه Google
           بردّ ٤٠٠، و`file_get_contents` يحوّل ذلك إلى تحذيرٍ يُسقط حفظ
           العميل كلّه. */
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $apiKey = env('GOOGLE_MAPS_API_KEY'); // احصل على API Key من .env
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$latitude,$longitude&key=$apiKey&language=ar";

        // الشبكة حدٌّ خارجي حقيقي: مفتاحٌ منتهٍ، حصّةٌ مستنفدة، أو انقطاعٌ
        // مؤقّت لا ينبغي أن يُسقط حفظ العميل — يبقى بلا اسم حيٍّ فقط
        $response = @file_get_contents($url);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (! empty($data['results'])) {
            foreach ($data['results'][0]['address_components'] as $component) {
                if (in_array('sublocality', $component['types']) || in_array('neighborhood', $component['types'])) {
                    return $component['long_name']; // اسم الحي
                }
            }
        }

        return 'لم يتم العثور على الحي';
    }

    /**
     * Generate PDF of all clients
     */
    public function generateClientsPdf(Request $request)
    {
        $type = $request->get('type', 'all'); // الافتراضي: تصدير الكل
        // 'type' هنا نطاق التصدير (all/filtered/page) لا فلتر «نوع العميل»
        // الذي يقرأه applyFilters() تحت نفس الاسم — يُزال قبل تمرير
        // الطلب إليها كي لا يُفهم نطاق التصدير خطأً بوصفه قيمة تصفية
        $request->query->remove('type');

        // إنشاء الاستعلام الأساسي
        $query = Client::with([
            'status_client',
            'branch',
            'neighborhood.region',
            'categoriesClient',
            'invoices',
        ]);

        // ✅ تطبيق الفلاتر باستخدام الدالة المساعدة
        $this->applyFilters($query, $request);

        // ✅ تحديد نوع التصدير
        if ($type === 'filtered') {
            $clients = $query->orderBy('trade_name')->get();
        } elseif ($type === 'page') {
            $page = $request->get('page', 1);
            $perPage = 100; // نفس عدد العملاء في الصفحة
            // ->items() يُعيد مصفوفة خامّة لا Collection — map() أدناه يحتاج Collection
            $clients = collect($query->paginate($perPage, ['*'], 'page', $page)->items());
        } else {
            $clients = $query->orderBy('trade_name')->get();
        }

        // ترويسة المنشأة، وجدولٌ محدَّد الحدود بصفوفٍ متناوبة التظليل —
        // نفس تصميم بقية مستندات النظام (قوالب المخزون)، بدل جدولٍ عارٍ
        // بلا شعار المنشأة ولا تنسيقها. المُصدِّر مشتركٌ مع تقارير الموارد
        // البشرية عمداً — بناء الترويسة والجدول لا يتكرّر لكل وحدة.
        $headings = ['الكود', 'الاسم التجاري', 'الهاتف', 'الحالة', 'الفرع', 'الفئة', 'الرصيد', 'الحد الائتماني', 'تاريخ التسجيل', 'آخر فاتورة'];

        $rows = $clients->map(function ($client) {
            $lastInvoice = $client->invoices->sortByDesc('invoice_date')->first();
            $due = Account::ledger()->where('client_id', $client->id)->sum('balance');

            return [
                $client->code ?? '-',
                $client->trade_name ?? '-',
                $client->phone ?? '-',
                optional($client->status_client)->name ?? '-',
                optional($client->branch)->name ?? '-',
                optional($client->categoriesClient)->name ?? '-',
                number_format($due, 2),
                $client->credit_limit ?? '-',
                optional($client->created_at)->format('Y-m-d') ?? '-',
                optional($lastInvoice)->invoice_date ?? '-',
            ];
        })->all();

        $typeLabel = $type === 'filtered' ? 'حسب الفلترة' : ($type === 'page' ? 'الصفحة الحالية' : 'الكل');

        return \Modules\HR\Support\HrReportExporter::download(
            'pdf',
            'قائمة العملاء — '.$typeLabel,
            $headings,
            $rows,
            'clients-list'
        );
    }

    public function store(ClientRequest $request)
    {
        $allowedIds = $this->branchesForClientTreeSelect()->pluck('id')->all();
        $request->validate(
            [
                'client_tree_branch_id' => ['required', 'integer', Rule::in($allowedIds)],
            ] + $this->employeeAssignmentRules(),
            [
                'client_tree_branch_id.required' => 'يرجى اختيار الفرع أو المنطقة التي يتبع لها العميل.',
                'client_tree_branch_id.in' => 'الخيار المحدّد خارج صلاحيتك.',
            ],
        );

        $node = Branch::withoutGlobalScopes()->findOrFail((int) $request->client_tree_branch_id);
        $data_request = $request->except('_token', 'client_tree_branch_id', 'region_id');
        $data_request = $this->applyTreeNodeToClientData($data_request, $node);

        if ($request->has('latitude') && $request->has('longitude')) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
        } elseif (\App\Helpers\ClientSettingsHelper::isLocationRequired()) {
            return redirect()->back()->with('error', 'الإحداثيات غير موجودة');
        }

        if (
            Schema::hasColumn('clients', 'group_id')
            && \App\Helpers\ClientSettingsHelper::isGroupRequired()
            && ! $request->filled('group_id')
        ) {
            return redirect()->back()->with('error', 'المجموعة مطلوبة');
        }

        /* المعاملة تحمي نصفَ العميل من البقاء.

           كان `store()` بلا معاملة: يُحفَظ العميل أوّلاً، ثمّ جهاتُ
           الاتصال والموظّفون والموقع والحيّ، ثمّ حسابُ الدخول في
           `users`، وأخيراً — بعد كلّ ذلك — الحسابُ المحاسبيّ تحت
           «حساب العملاء».

           فبريدٌ مستعمَل يُفشل `User::create` في منتصف الطريق، فيخرج
           الطلبُ بخطأ ٥٠٠ ويبقى العميلُ محفوظاً **بلا حسابٍ محاسبيّ**:
           لا تُقيَّد له فاتورة ولا يُعرَف له رصيد. وهو ما رآه المستخدم.

           والقاعدةُ في `ClientRequest` تمنع هذا السبب بعينه، لكنّ
           المعاملةَ تمنع كلَّ سببٍ آخر — فشلُ رفع مرفق، أو حيٌّ لا
           يُحلّ، أو أيُّ استثناءٍ في الطريق. */
        /* يُقرأ قبل المعاملة لا داخلها: قيمةٌ لا تعتمد على شيءٍ يُنشأ فيها،
           ووجودُها هنا يضمن تعريفَها في كل مسارٍ يصل إلى ما بعد
           `DB::commit()` حيث تُقرأ ثانيةً لتقرير وجهة إعادة التوجيه.

           الشرط الثاني ليس تزيّداً: راوت `clients.visitWorkspace` محروسٌ
           بإحدى أربع صلاحيات، فمن مُنح `clients_start_visit_on_create`
           وحدها كان يُنشأ له صفُّ خطّ سيرٍ وزيارةٌ ثم يُرمى في 403 عند
           الوجهة — زيارةٌ يتيمة وشاشةُ منعٍ بدل عميلٍ حُفظ للتوّ. فإن
           تعذّر عليه دخول مساحة العمل لا نبدأ المسار أصلاً، ويذهب إلى
           الفهرس كما لو لم يملك الصلاحية. */
        $startVisitOnCreate = hasPermission('clients_start_visit_on_create')
            && hasPermission([
                'clients_visits_track',
                'clients_itinerary_add',
                'clients_visit_workspace_access',
                'clients_must_visit_before_view',
            ]);

        DB::beginTransaction();

        try {
            $client = new Client;
            // بلا `?? 3`: الثلاثةُ معرّفُ صفٍّ قد يُحذف أو يعني حالةً أخرى في
            // منشأةٍ أخرى. المساعد يردّ المضبوطَ في الإعدادات، وإلّا أوّلَ
            // حالةٍ في ترتيب الشاشة — قيمةٌ من بيانات المنشأة لا ثابتٌ يُخمَّن
            $client->status_id = ClientSettingsHelper::defaultNewClientStatusId();

            $client->fill($data_request);

            /* «وضع العميل» صار حقلاً في نموذج الإضافة يختاره منشئ العميل،
               و`ClientRequest` هو من يتحقّق من انتمائه لقسم
               `registration_stage` ومن إلزاميّته حسب مفتاح «وضع العميل
               مطلوب» في «الحقول الإلزامية».

               تُكتب هنا بعد `fill()` كي لا تصل سلسلةٌ فارغة إلى عمودٍ
               رقميّ: الفارغ يسقط على الوضع الافتراضي المضبوط في «إعدادات
               وحدات العملاء ← العملاء» — وهو ما كانت الشاشة تكتبه دوماً
               قبل وجود هذا الحقل. */
            $client->registration_stage_id = $request->filled('registration_stage_id')
                ? (int) $request->input('registration_stage_id')
                : ClientSettingsHelper::defaultRegistrationStageId();

            // نفس مولّد الأرقام المتسلسل المستعمل في بقية مستندات النظام
            // (كما في next_document_number('visit') أسفل هذا الملف)
            $client->code = next_document_number('customer');

            // معالجة الصورة
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $client->attachments = $filename;
                }
            }

            $client->save();

            // حفظ جهات الاتصال الأساسية
            $mainContact = [
                'first_name' => $client->trade_name,
                'phone' => $client->phone,
                'mobile' => $client->mobile,
                'email' => $client->email,
                'is_primary' => true,
            ];

            $client->contacts()->create($mainContact);

            // حفظ الموظفين المرتبطين وجمع معرفاتهم
            $employeeIds = [];

            if (hasPermission('clients_assign_employees')) {
                if ($request->has('employee_client_id')) {
                    foreach ($request->employee_client_id as $employee_id) {
                        $client_employee = new ClientEmployee;
                        $client_employee->client_id = $client->id;
                        $client_employee->employee_id = $employee_id;
                        $client_employee->save();
                        $employeeIds[] = $employee_id;
                    }
                }
            } elseif (auth()->user()->isEmployee()) {
                $employeeId = auth()->user()->employee_id;
                ClientEmployee::create([
                    'client_id' => $client->id,
                    'employee_id' => $employeeId,
                ]);
                $employeeIds[] = $employeeId;
            }

            // تسجيل الإحداثيات
            $client->locations()->create([
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            $neighborhoodName = $request->neighborhood_name_manual ?: $this->getNeighborhoodFromGoogle($latitude, $longitude);
            $Neighborhood = new Neighborhood;
            $Neighborhood->name = $neighborhoodName ?? 'غير محدد';
            $Neighborhood->region_id = $node->resolveTerritoryBranchForNeighborhood()->id;
            $Neighborhood->client_id = $client->id;
            $Neighborhood->save();

            // إنشاء مستخدم جديد إذا تم توفير البريد الإلكتروني
            $password = Str::random(10);
            $full_name = $client->trade_name.' '.$client->first_name.' '.$client->last_name;
            if ($request->email != null) {
                User::create([
                    'name' => $full_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'role' => 'client',
                    'client_id' => $client->id,
                    'password' => Hash::make($password),
                ]);
            }

            // تسجيل إشعار نظام جديد
            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $client,
                'إضافة عميل «'.$client->trade_name.'»',
                ['new' => $client->only(['code', 'trade_name', 'phone', 'status_id', 'branch_id'])]
            );

            // زيادة الرقم الحالي بمقدار 1

            // إنشاء حساب فرعي باستخدام trade_name
            $this->ensureClientLedgerAccount($client, (float) ($request->opening_balance ?? 0));

            // حفظ جهات الاتصال الإضافية
            if ($request->has('contacts') && is_array($request->contacts)) {
                foreach ($request->contacts as $contact) {
                    $client->contacts()->create($contact);
                }
            }

            // الاسم والجوّال وجهات الاتصال تنزل في «موظفو العميل» فلا تُدخَل مرّتين
            \App\Services\Clients\ClientContactSync::sync($client, (array) $request->input('contacts', []));

            /* خطّ سير **المُنشئ** — المسلكُ الوحيد الذي يضع العميل الجديد في
               خطّ سيرٍ عند الحفظ، وهو فعلٌ صريحٌ يقوم به المستخدم لنفسه:
               من يملك `clients_start_visit_on_create` يضيف عميلاً وهو عنده
               في الميدان، فيُوضَع في خطّ سيره اليوم ثم تُفتح له مساحة
               الزيارة بعد الحفظ (أسفل الدالّة، بعد `DB::commit()`).

               كان هنا قبله إقحامٌ تلقائيّ للعميل في خطّ سير كلّ موظّفٍ مسؤولٍ
               عنه («الزيارات التلقائية») — أُزيل في 2026-09-14. و`updateOrCreate`
               يبقى كي لا يُكتب صفّان لليوم نفسه إن كان للمُنشئ صفٌّ مسبق. */
            if ($startVisitOnCreate) {
                $now = now();
                $weekInfo = WeekHelper::getWeekAndYear($now);

                /* `strtolower` هنا كما في الكتلة أعلاه، وهو بلا أثرٍ فعليّ:
                   `day_of_week` عمود ENUM بأعضاءٍ بحرفٍ كبير
                   (`'Saturday','Sunday',…`)، وMySQL يطابق القيمة على العضو
                   بلا حساسيةٍ لحالة الأحرف ويخزّن صيغته هو. فما يُقرأ من
                   الجدول `Wednesday` مهما كُتب — وهو ما يجعل الصفَّ مطابقاً
                   لبقية صفوف خطوط السير. */
                EmployeeClientVisit::updateOrCreate(
                    [
                        'employee_id' => auth()->id(),
                        'client_id' => $client->id,
                        'day_of_week' => strtolower(WeekHelper::getCurrentDayNameEn($now)),
                        'year' => $weekInfo['year'],
                        'week_number' => $weekInfo['week'],
                    ],
                    [
                        'visit_date' => $now->toDateString(),
                        'status' => 'active',
                    ],
                );
            }

            // تسجيل المحفظة الافتراضية للعميل
            try {
                $employeeForPoints = auth()->user() ? (auth()->user()->employee_id ?? auth()->id()) : null;

                // منح نقاط عن إضافة عميل جديد
                app(PointsService::class)->processAction('new_client_acquired', [
                    'employee_id' => $employeeForPoints,
                    'client_id' => $client->id,
                    'amount' => $request->opening_balance ?? 0,
                    'branch_id' => $client->branch_id ?? null,
                    'reference_type' => get_class($client),
                    'reference_id' => $client->id,
                ]);

                // منح نقاط عن اكمال الملف الشخصي
                app(PointsService::class)->processAction('profile_completed', [
                    'employee_id' => $employeeForPoints,
                    'client_id' => $client->id,
                    'amount' => 0,
                    'branch_id' => $client->branch_id ?? null,
                    'reference_type' => get_class($client),
                    'reference_id' => $client->id,
                ]);
            } catch (\Exception $e) {
                OperationLog::failure($e, self::LOG_MODULE, 'points_on_create');
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            // كان الاستثناء يخرج خاماً إلى الشاشة بلا أثرٍ في السجلّ
            OperationLog::failure($e, 'Client.Client', 'store');

            $message = 'حدث خطأ أثناء إضافة العميل: '.$e->getMessage();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->back()->withInput()->with('error', $message);
        }

        /* «وينقله إلى مساحة الزيارة عشان يبدأ الزيارة» — الوجهة بعد الحفظ
           لحاملي `clients_start_visit_on_create`.

           **بعد `DB::commit()` لا داخله**: الزيارة ليست جزءاً من سجلّ
           العميل، وفشلُ إنشائها (رقمُ مستندٍ متعذّر، عمودٌ ناقص) لا يصحّ أن
           يُسقط العميلَ الذي حُفظ للتوّ. فإن تعثّرت، يبقى العميل ويذهب
           المستخدم إلى ملفّه برسالةٍ تشرح ما جرى.

           الزيارة تُنشأ بمرحلة `STAGE_ARRIVED` بلا `started_at` — نسخةٌ
           مطابقة لما يفعله `registerVisit()`: المؤقّت لا يبدأ إلا بضغط
           «بدء الزيارة» داخل مساحة العمل من موقع العميل، حيث التحقّق
           الحقيقي من GPS (`VisitWorkspaceController::start()`). */
        $visitRedirectUrl = null;

        if ($startVisitOnCreate) {
            try {
                $visit = Visit::create([
                    'employee_id' => auth()->id(),
                    'client_id' => $client->id,
                    'visit_date' => now(),
                    'visit_number' => next_document_number('visit'),
                    'client_latitude' => optional($client->locations)->latitude,
                    'client_longitude' => optional($client->locations)->longitude,
                    'recording_method' => 'manual',
                    'is_approved' => false,
                    'stage' => Visit::STAGE_ARRIVED,
                ]);

                OperationLog::record(
                    'visit_registered',
                    self::LOG_MODULE,
                    $client,
                    'تسجيل زيارة فور إضافة العميل «'.$client->trade_name.'»'
                );

                $visitRedirectUrl = route('clients.visitWorkspace', ['client' => $client->id, 'visit' => $visit->id]);
            } catch (\Throwable $e) {
                OperationLog::failure($e, self::LOG_MODULE, 'start_visit_on_create', $client);
            }
        }

        $message = $visitRedirectUrl
            ? '✨ تم إضافة العميل وفتح زيارته — أضِف مسؤول العميل لتبدأ.'
            : '✨ تم إضافة العميل بنجاح!';

        // العميل حُفظ لكن الزيارة تعثّرت: لا نبتلع الخبر — نقوله ونذهب لملفّه
        if ($startVisitOnCreate && ! $visitRedirectUrl) {
            $message = '✨ تم إضافة العميل، لكن تعذّر فتح زيارته — سجّلها من زرّ «تسجيل زيارة» في ملفّه.';
        }

        // التحقق من كون الطلب AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'client_id' => $client->id,
                'redirect_url' => $visitRedirectUrl ?? route('clients.index'),
            ]);
        }

        if ($visitRedirectUrl) {
            return redirect($visitRedirectUrl)->with('success', $message);
        }

        return redirect()->route('clients.index')->with('success', $message);
    }

    protected function getCorrectWeekNumber(Carbon $date)
    {
        // تاريخ السبت الماضي كبداية للأسبوع
        $weekStart = $date->copy()->startOfWeek(Carbon::SATURDAY);

        // تاريخ 1 يناير من نفس السنة
        $janFirst = Carbon::create($weekStart->year, 1, 1);

        // إذا كان 1 يناير ليس سبتاً، نبدأ العد من السبت التالي
        if ($janFirst->dayOfWeek != Carbon::SATURDAY) {
            $janFirst->next(Carbon::SATURDAY);
        }

        // حساب الفرق بالأسابيع + 1
        $weekNumber = $janFirst->diffInWeeks($weekStart) + 1;

        // التأكد من أن رقم الأسبوع لا يتجاوز 52
        return min($weekNumber, 52);
    }

    /**
     * تسجيل زيارة والانتقال مباشرةً إلى مساحة عملها — بلا صفحة وسيطة تطلب
     * الموقع (كانت `client::register_visit`). الزيارة تُنشأ بمرحلة
     * `Visit::STAGE_ARRIVED` بلا `started_at`: المؤقّت لا يبدأ إلا حين
     * يضغط الموظف «بدء الزيارة» داخل مساحة العمل نفسها من موقع العميل
     * (`VisitWorkspaceController::start()`) — هناك التحقّق الحقيقي من GPS.
     */
    public function registerVisit($id)
    {
        $client = Client::findOrFail($id);

        // كان يقبل أيّ معرّف عميل بلا فحص نطاق: صلاحية `clients_visits_track`/
        // `clients_itinerary_add` صلاحية مسارٍ عامة لا تتحقّق من أنّ هذا
        // العميل تحديداً ضمن نطاق المستخدم — فتح الباب لتسجيل زيارةٍ (ثم
        // مساحة عملها) لعميلٍ لا يملك المستخدم صلاحية الوصول إليه أصلاً.
        abort_unless(
            $this->clientAccess->canAccessClient(auth()->user(), $client->id),
            403,
            'لا يمكنك تسجيل زيارة لعميل غير مسموح لك بالوصول إليه.'
        );

        // زيارة مفتوحة اليوم لهذا العميل بالفعل (بدأت أو لم تبدأ بعد) —
        // تُكمَل مساحة عملها لا تُنشأ زيارة ثانية.
        $existingVisit = Visit::where('employee_id', auth()->id())
            ->where('client_id', $client->id)
            ->whereDate('visit_date', now()->toDateString())
            ->whereNull('departure_time')
            ->first();

        if ($existingVisit) {
            return redirect()->route('clients.visitWorkspace', ['client' => $client->id, 'visit' => $existingVisit->id]);
        }

        /* «تسجيل زيارة» يجب أن يوصل دوماً إلى مساحة عمل الزيارة — لا صفحةً
           أخرى مهما حصل — فلا إعادة توجيهٍ إلى الخلف بعد اليوم إن كان
           العميل بلا موقعٍ مسجَّل. `$client->locations` (لا `Location::query()`
           الخام هنا سابقاً) نفسُ العلاقة المصفّاة التي يعتمدها
           `VisitWorkspaceController::show()`؛ فإن غاب الموقع تُنشأ الزيارة
           بإحداثيَّين فارغين، وتلك الشاشة تعرض بنفسها تنبيهاً واضحاً
           («لا يوجد موقعٌ مسجَّل...» مع رابط إعادة محاولة) بدل أن يُحبَس
           الموظف خارج مسار الزيارة كلياً بلا تفسير. */
        $clientLocation = $client->locations;

        try {
            $visit = Visit::create([
                'employee_id' => auth()->id(),
                'client_id' => $client->id,
                'visit_date' => now(),
                'visit_number' => next_document_number('visit'),
                'client_latitude' => optional($clientLocation)->latitude,
                'client_longitude' => optional($clientLocation)->longitude,
                'recording_method' => 'manual',
                'is_approved' => false,
                'stage' => Visit::STAGE_ARRIVED,
            ]);

            OperationLog::record(
                'visit_registered',
                self::LOG_MODULE,
                $client,
                'تسجيل زيارة للعميل «'.$client->trade_name.'»'
            );

            return redirect()->route('clients.visitWorkspace', ['client' => $client->id, 'visit' => $visit->id]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'register_visit', $client);

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء تسجيل الزيارة. يرجى المحاولة مرة أخرى.');
        }
    }

    /**
     * الحصول على رقم الترتيب التالي للزيارة
     */

    /**
     * الحصول على رقم الترتيب التالي للزيارة
     */

    /**
     * إنهاء الزيارة وتسجيل وقت المغادرة
     */
    public function completeVisit(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);

        // التحقق من أن الزيارة تخص الموظف الحالي
        if ($visit->employee_id !== auth()->id()) {
            return redirect()->back()
                ->with('error', 'لا يمكنك إنهاء زيارة لا تخصك.');
        }

        // التحقق من أن الزيارة لم تنتهِ بالفعل
        if ($visit->status === 'completed') {
            return redirect()->back()
                ->with('warning', 'هذه الزيارة منتهية بالفعل.');
        }

        try {
            // تحديث حالة الزيارة
            $visit->update([
                'departure_time' => now(),
                'status' => 'completed',
                'notes' => $request->input('final_notes', $visit->notes),
            ]);

            OperationLog::record(
                'visit_completed',
                self::LOG_MODULE,
                $visit,
                'إنهاء الزيارة #'.$visit->id.' للعميل «'.optional($visit->client)->trade_name.'»'
            );

            return redirect()->route('visits.index')
                ->with('success', 'تم إنهاء الزيارة بنجاح.');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'complete_visit', $visit);

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء إنهاء الزيارة. يرجى المحاولة مرة أخرى.');
        }
    }

    public function send_email($id)
    {
        $employee = User::where('client_id', $id)->first();

        if (! $employee || empty($employee->email)) {
            return redirect()->back()->with('error', 'العميل لا يمتلك بريدًا إلكترونيًا للدخول.');
        }

        // توليد كلمة مرور جديدة عشوائية
        $newPassword = $this->generateRandomPassword();

        // تحديث كلمة المرور في قاعدة البيانات بعد تشفيرها
        $employee->password = Hash::make($newPassword);
        $employee->save();

        // إعداد بيانات البريد
        $details = [
            'name' => $employee->name,
            'email' => $employee->email,
            'password' => $newPassword, // إرسال كلمة المرور الجديدة مباشرة
        ];

        // إرسال البريد
        Mail::to($employee->email)->send(new TestMail($details));
        OperationLog::record(
            'credentials_sent',
            self::LOG_MODULE,
            $employee->id,
            'إرسال بيانات الدخول إلى «'.$employee->name.'»'
        );

        // return back()->with('message', 'تم إرسال البريد بنجاح!');
        return redirect()
            ->back()
            ->with(['success' => 'تم  ارسال البريد بنجاح .']);
    }

    private function generateRandomPassword($length = 10)
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }

    protected function generateUniqueAccountCode($parentId, $parentCode)
    {
        $lastChild = Account::where('parent_id', $parentId)->orderBy('code', 'desc')->first();

        $baseCode = $lastChild ? (int) $lastChild->code + 1 : $parentCode.'001';

        $counter = 1;
        $newCode = $baseCode;

        while (Account::where('code', $newCode)->exists()) {
            $newCode = $baseCode.'_'.$counter;
            $counter++;

            if ($counter > 100) {
                throw new \RuntimeException('فشل توليد كود فريد');
            }
        }

        return $newCode;
    }

    /* حسابُ العميل في شجرة الحسابات — يُنشأ إن لم يكن له حساب.

       كان الإنشاءُ في `store()` وحدَه، فكلُّ عميلٍ أُضيف قبل ضبط «حساب
       العملاء» في الإعدادات المحاسبية، أو استُورد من خارج هذا المسلك،
       يبقى بلا حسابٍ في الشجرة ولا سبيلَ للمستخدم إلى إنشائه. صار
       التعديلُ يستدركه.

       الموجودُ يُرجَع كما هو بلا مساس: لا بالكود (مولَّدٌ من كود الأب،
       ودوسُه يفجّر `accounts_code_unique` — انظر التعليق في `update()`)
       ولا بالرصيد الافتتاحي (له شاشتُه: `updateOpeningBalance`).

       و`null` إن لم يكن «حساب العملاء» مضبوطاً في الإعدادات المحاسبية —
       بلا سقوطٍ إلى `Account::where('name', 'العملاء')`. */
    private function ensureClientLedgerAccount(Client $client, float $openingBalance = 0): ?Account
    {
        $existing = Account::ledger()->where('client_id', $client->id)->first();
        if ($existing) {
            return $existing;
        }

        $customers = AccountingSettings::account('clients_account');
        if (! $customers) {
            return null;
        }

        $customerAccount = new Account;
        $customerAccount->name = $client->trade_name;
        $customerAccount->client_id = $client->id;

        // نوعُ الحساب المالي مطابقٌ للحساب الأب
        $customerAccount->account_type = $customers->account_type;

        $lastChild = Account::where('parent_id', $customers->id)->orderBy('code', 'desc')->first();
        $newCode = $lastChild ? $this->generateNextCode($lastChild->code) : $customers->code.'1';

        while (Account::where('code', $newCode)->exists()) {
            $newCode = $this->generateNextCode($newCode);
        }

        $customerAccount->code = $newCode;
        $customerAccount->balance_type = 'debit';
        $customerAccount->parent_id = $customers->id;

        $customerAccount->opening_balance = $openingBalance;
        $customerAccount->opening_balance_type = $customers->balance_type; // مطابق لنوع الرصيد الأب
        $customerAccount->is_active = true;

        $customerAccount->save();

        // قيدُ الرصيد الافتتاحي إن كان هناك رصيد
        if ($customerAccount->opening_balance != 0) {
            $customerAccount->createOrUpdateOpeningBalanceJournalEntry();
        }

        return $customerAccount;
    }

    // إضافة هذه الدالة في نفس وحدة التحكم
    private function generateNextCode(string $lastChildCode): string
    {
        // استخراج الرقم الأخير من الكود
        $lastNumber = intval(substr($lastChildCode, -1));
        // زيادة الرقم الأخير بمقدار 1
        $newNumber = $lastNumber + 1;

        // إعادة بناء الكود مع الرقم الجديد
        return substr($lastChildCode, 0, -1).$newNumber;
    }

    public function update(ClientRequest $request, $id)
    {
        $client = Client::with('neighborhood')->findOrFail($id);
        $allowedIds = $this->branchesForClientTreeSelect()->pluck('id')->all();

        // السماح للعميل بالاحتفاظ بفرعه الحالي حتى لو لم يكن ورقة في الشجرة
        // (للتوافق مع البيانات القديمة قبل تطبيق قاعدة الأوراق فقط)
        // العقدةُ المحفوظة فعلاً — لا `branch_id` الذي صار الفرعَ المحاسبيّ المشتقّ
        $currentBranchId = $client->resolveTreeNode()?->id;
        if ($currentBranchId && ! in_array((int) $currentBranchId, array_map('intval', $allowedIds), true)) {
            $allowedIds[] = (int) $currentBranchId;
        }

        $locationRule = \App\Helpers\ClientSettingsHelper::isLocationRequired() ? 'required|numeric' : 'nullable|numeric';

        $rules = [
            'client_tree_branch_id' => ['required', 'integer', Rule::in($allowedIds)],
            'latitude' => $locationRule,
            'longitude' => $locationRule,
        ] + $this->employeeAssignmentRules($client);

        if (Schema::hasColumn('clients', 'group_id') && \App\Helpers\ClientSettingsHelper::isGroupRequired()) {
            $rules['group_id'] = 'required';
        }

        $messages = [
            'client_tree_branch_id.required' => 'يرجى اختيار الفرع أو المنطقة التي يتبع لها العميل.',
            'client_tree_branch_id.in' => 'الخيار المحدّد خارج صلاحيتك.',
            'latitude.required' => 'العميل ليس لديه موقع مسجل الرجاء تحديد الموقع على الخريطة',
            'longitude.required' => 'العميل ليس لديه موقع مسجل الرجاء تحديد الموقع على الخريطة',
            'group_id.required' => 'المجموعة مطلوبة',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(
                [
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'يرجى التحقق من البيانات المدخلة',
                ],
                422,
            );
        }

        // بدء المعاملة لضمان سلامة البيانات
        DB::beginTransaction();

        try {
            $data_request = $request->except('_token', 'contacts');
            $client = Client::findOrFail($id);
            $oldData = $client->getOriginal();

            $latitude = $request->latitude ?? $client->latitude;
            $longitude = $request->longitude ?? $client->longitude;

            $node = Branch::withoutGlobalScopes()->findOrFail((int) $request->client_tree_branch_id);
            // `code` مستثنًى عمداً: يُولَّد تسلسلياً عند الإنشاء وحده
            // (`next_document_number('customer')`) — تعديلُه هنا كان يقبل
            // أيّ نصٍّ يرسله النموذج فيكسر التسلسل، والحقل في الشاشة صار
            // للعرض فقط (بلا `name`) بعد هذا التغيير
            $data_request = $request->except('_token', 'contacts', 'latitude', 'longitude', 'client_tree_branch_id', 'region_id', 'code');
            $data_request = $this->applyTreeNodeToClientData($data_request, $node);

            // حذف الموظفين السابقين وإعادة إسنادهم فقط لمن يملك صلاحية إسناد الموظفين
            if (hasPermission('clients_assign_employees')) {
                ClientEmployee::where('client_id', $client->id)->delete();

                if ($request->has('employee_client_id')) {
                    foreach ($request->employee_client_id as $employee_id) {
                        ClientEmployee::create([
                            'client_id' => $client->id,
                            'employee_id' => $employee_id,
                        ]);
                    }
                }
            } elseif (auth()->user()->isEmployee()) {
                $employee_id = auth()->user()->employee_id;

                // التحقق إذا هو أصلاً مسؤول
                $alreadyExists = ClientEmployee::where('client_id', $client->id)->where('employee_id', $employee_id)->exists();

                if (! $alreadyExists) {
                    ClientEmployee::create([
                        'client_id' => $client->id,
                        'employee_id' => $employee_id,
                    ]);
                }
            }

            // 1. معالجة المرفقات
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    // حذف الملف القديم إن وجد
                    if ($client->attachments) {
                        $oldFilePath = public_path('assets/uploads/').$client->attachments;
                        if (File::exists($oldFilePath)) {
                            File::delete($oldFilePath);
                        }
                    }

                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $data_request['attachments'] = $filename;
                }
            }

            // حفظ المستوى السابق قبل التحديث إذا تغير المستوى الحالي
            if (isset($data_request['customer_target_id']) && $data_request['customer_target_id'] != $client->customer_target_id) {
                $data_request['previous_target_id'] = $client->customer_target_id;
            }

            // «وضع العميل» — سلسلةٌ فارغة من السيلكت تعني «بلا وضع» لا صفراً
            // في عمودٍ رقميّ يشير إلى `statuses`
            if (array_key_exists('registration_stage_id', $data_request) && $data_request['registration_stage_id'] === '') {
                $data_request['registration_stage_id'] = null;
            }

            // تحديث بيانات العميل الأساسية
            $client->update($data_request);

            // التأكد من أن الكود يطابق الـ ID
            // if ($client->code != $client->id) {
            //     $client->update(['code' => $client->id]);
            // }

            /* اسمُ الحساب وحدَه يتبع اسمَ العميل — أمّا `code` فلا.

               كان السطرُ هنا يكتب `'code' => $client->id`: يدوس كودَ
               الحساب في شجرة الحسابات — وهو مولَّدٌ عند الإنشاء من كود
               الأب (`$customers->code.'1'` ثمّ `generateNextCode`) — برقمِ
               صفِّ العميل. والعمود `accounts.code` فريد، فأوّلُ حسابٍ في
               الشجرة كودُه يساوي رقمَ العميل يُفجّر التعديلَ كلَّه:

                 Duplicate entry '2' for key 'accounts_code_unique'

               فيفشل تعديلُ العميل بسببٍ لا صلةَ له ببياناته. وحتى حين
               كان ينجح كان يفسد الشجرة: كودٌ لا ينتمي لأبيه. */
            /* والعميلُ الذي لا حسابَ له في الشجرة — أُضيف قبل ضبط «حساب
               العملاء» في الإعدادات، أو استُورد من خارج `store()` — يُنشأ
               له حسابُه هنا عند أوّل تعديل، برصيدٍ افتتاحيٍّ صفر: الرصيدُ
               له شاشتُه المستقلّة (`updateOpeningBalance`) ولا يمرّ من
               نموذج التعديل. */
            $clientAccount = $this->ensureClientLedgerAccount($client);
            if ($clientAccount) {
                $clientAccount->update([
                    'name' => $client->trade_name,
                ]);
            }

            // 3. معالجة الإحداثيات - الطريقة المؤكدة
            $client->locations()->delete(); // حذف جميع المواقع القديمة

            $client->locations()->create([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'client_id' => $client->id,
            ]);

            $neighborhoodName = $request->neighborhood_name_manual ?: $this->getNeighborhoodFromGoogle($request->latitude, $request->longitude);

            // البحث عن الحي الحالي للعميل
            $Neighborhood = Neighborhood::where('client_id', $client->id)->first();

            if ($Neighborhood) {
                // إذا كان لديه حي، قم بتحديثه
                $Neighborhood->name = $neighborhoodName ?? 'غير محدد';
                $Neighborhood->region_id = $node->resolveTerritoryBranchForNeighborhood()->id;
                $Neighborhood->save();
            } else {
                // إذا لم يكن لديه حي، أضف حيًا جديدًا
                $Neighborhood = new Neighborhood;
                $Neighborhood->name = $neighborhoodName ?? 'غير محدد';
                $Neighborhood->region_id = $node->resolveTerritoryBranchForNeighborhood()->id;
                $Neighborhood->client_id = $client->id;
                $Neighborhood->save();
            }

            // 4. تحديث بيانات المستخدم
            if ($request->email) {
                $full_name = implode(' ', array_filter([$client->trade_name, $client->first_name, $client->last_name]));

                $userData = [
                    'name' => $full_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                ];

                $user = User::where('client_id', $client->id)->first();

                if ($user) {
                    $user->update($userData);
                } else {
                    $userData['password'] = Hash::make(Str::random(10));
                    $userData['role'] = 'client';
                    $userData['client_id'] = $client->id;
                    User::create($userData);
                }
            }

            // 6. معالجة جهات الاتصال
            if ($request->has('contacts')) {
                $existingContacts = $client->contacts->keyBy('id');
                $newContacts = collect($request->contacts);

                // الحذف
                $contactsToDelete = $existingContacts->diffKeys($newContacts->whereNotNull('id')->keyBy('id'));
                $client->contacts()->whereIn('id', $contactsToDelete->keys())->delete();

                // التحديث والإضافة
                foreach ($request->contacts as $contact) {
                    if (isset($contact['id']) && $existingContacts->has($contact['id'])) {
                        $existingContacts[$contact['id']]->update($contact);
                    } else {
                        $client->contacts()->create($contact);
                    }
                }
            }

            // الاسم والجوّال وجهات الاتصال تنزل في «موظفو العميل» فلا تُدخَل مرّتين
            \App\Services\Clients\ClientContactSync::sync($client, (array) $request->input('contacts', []), $oldData);

            // 7. تسجيل العملية في السجل
            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $client,
                'تحديث بيانات العميل «'.$client->trade_name.'»',
                ['old' => $oldData, 'new' => $client->getAttributes()]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '✨ تم تحديث بيانات العميل بنجاح!',
                'redirect_url' => route('clients.index'),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'update');

            DB::rollBack();

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء التحديث: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    public function edit_question($id)
    {
        $client = Client::with('neighborhood')->findOrFail($id);
        $employees = $this->employeesForClientAssignment($client);
        $location = Location::where('client_id', $id)->clientPoint()->latest()->first();

        $user = auth()->user();
        $clientBranchTreeOptions = $this->branchesForClientTreeSelect($user);
        $prefBranchId = $client->resolveTreeNode()?->id;
        if ($prefBranchId && ! $clientBranchTreeOptions->contains(fn ($b) => (int) $b->id === (int) $prefBranchId)) {
            $extra = Branch::withoutGlobalScopes()->find($prefBranchId);
            if ($extra) {
                $clientBranchTreeOptions = $clientBranchTreeOptions->prepend($extra)->unique('id')->values();
            }
        }

        // ✅ الإعدادات العامة
        $GeneralClientSettings = GeneralClientSetting::all();
        if ($GeneralClientSettings->isEmpty()) {
            $defaultSettings = [
                ['key' => 'image', 'name' => 'صورة', 'is_active' => true],
                ['key' => 'location', 'name' => 'الموقع على الخريطة', 'is_active' => true],
                ['key' => 'credit_duration', 'name' => 'المدة الائتمانية', 'is_active' => true],
            ];

            $GeneralClientSettings = collect($defaultSettings)->map(function ($item) {
                return (object) $item;
            });
        }

        $categories = CategoriesClient::all();

        $clientBranchTreeNodes = $this->clientBranchTreeNodes($user, $prefBranchId ? (int) $prefBranchId : null);

        return view('client::edit', compact(
            'client',
            'employees',
            'categories',
            'clientBranchTreeOptions',
            'clientBranchTreeNodes',
            'location',
            'GeneralClientSettings'
        ));
    }

    public function destroy($id)
    {
        $client = Client::findOrFail($id);

        // التحقق من وجود فواتير مرتبطة بالعميل
        if ($client->invoices()->exists()) {
            return redirect()->back()->with('error', 'لا يمكن حذف العميل لأنه يحتوي على فواتير مرتبطة.');
        }

        $clientId = $client->id;
        $clientName = $client->trade_name;

        try {
            // حذف المدفوعات المرتبطة
            if ($client->payments()->exists()) {
                $client->payments()->delete();
            }

            // حذف إشعارات الائتمان المرتبطة
            if ($client->creditNotifications()->exists()) {
                $client->creditNotifications()->delete();
            }

            // حذف مدخلات المجلة المرتبطة
            if ($client->journalEntries()->exists()) {
                $client->journalEntries()->delete();
            }

            // حذف المرفقات إذا وجدت
            if ($client->attachments) {
                $attachments = explode(',', $client->attachments);
                foreach ($attachments as $attachment) {
                    $path = public_path('uploads/clients/'.trim($attachment));
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }

            // حذف العميل
            $client->delete();

            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $clientId,
                'حذف العميل «'.$clientName.'» وجميع بياناته المرتبطة'
            );

            return redirect()->back()->with('success', 'تم حذف العميل وجميع البيانات المرتبطة به بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $clientId);

            return redirect()->back()->with('error', 'حدث خطأ أثناء حذف العميل. يرجى المحاولة مرة أخرى.');
        }
    }

    public function show($id)
    {
        // موظفٌ يُلزَم بتسجيل الزيارة قبل عرض العميل لا يصل هذه الشاشة إلا
        // بعد أن يُتمّ زيارة اليوم لهذا العميل تحديداً — قبلها يُعاد دوماً
        // إلى تسجيل/متابعة الزيارة، لا من رابطٍ مباشر ولا من الفهرس.
        // `registerVisit()` نفسها تتولى فرعَي «زيارةٌ مفتوحة اليوم» و«لا
        // زيارة بعد»، فلا تكرار للفحص هنا. راجع clients_must_visit_before_view
        // ونفس الشرط في client_cards.blade.php (لا يعيدا التوجيه بعد الإتمام،
        // وإلا حُبس مَن أتمّ زيارته في حلقة «سجّل زيارة» كل مرّة يفتح العميل).
        //
        // `clients_exempt_visit_before_view` تفكّ هذا القيد عن فردٍ بعينه دون
        // سحب الإلزام من دوره كلّه — تُفحص هنا وفي client_cards.blade.php معاً.
        //
        // بلا `isEmployee()`: مَن يُلزَم بالزيارة تقرّره الصلاحيتان لا نوعُ
        // الحساب. كان الشرط يسقط عن غير الموظّف ولو مُنح الإلزام صراحةً،
        // فيصير منحُ الصلاحية بلا أثر ولا يفسّر النظامُ لماذا.
        $mustVisitFirst = hasPermission('clients_must_visit_before_view')
            && ! hasPermission('clients_exempt_visit_before_view');

        if ($mustVisitFirst) {
            /* «ما ودي يفتح ملفّ العميل سواء اكتمل أو ما اكتمل»: من يُلزَم
               بالزيارة لا يرى `clients.show` إطلاقاً — كلُّ عمله في صفحة
               الزيارة. الوجهة تختلف بحسب حاله لا أكثر:

                 • انتهى دورُه اليوم (كلُّ صفوفه مكتملة) ⇒ الفهرس، لأنّ
                   تسجيل زيارةٍ ثالثة لعميلٍ فرغ منه ليس ما يريده أحد.
                 • غير ذلك (لم يزره بعد، أو له عودةٌ مجدولة) ⇒ تسجيل الزيارة.

               وكان `$hasCompletedVisitToday` وحده يفتح الملفّ بعد أوّل
               إنهاء. */
            // بالإعداد: إخفاءٌ مطفأ يعني أنّ العميل ظاهرٌ في القائمة، فلا يُردّ
            // من فتحه إلى الفهرس — تبدأ له زيارةٌ جديدة
            $finishedToday = EmployeeClientVisit::hiddenFinishedClientIdsFor((int) auth()->id())
                ->contains((int) $id);

            if ($finishedToday) {
                return redirect()->route('clients.index')
                    ->with('info', 'انتهت زيارة هذا العميل اليوم. لفتحه من جديد سجّل له زيارةً في خطّ سيرٍ آخر.');
            }

            /* الوجهة مساحةُ الزيارة، لا الباب المؤدّي إليها: زيارةٌ مفتوحة
               اليوم تُفتح مساحتُها مباشرةً بلا المرور بـ`/register-visit/{id}`
               إطلاقاً. لا يُستعمل الباب إلا حين لا زيارةَ بعد — فهو وحده من
               يُنشئها. */
            $openVisitId = Visit::where('employee_id', auth()->id())
                ->where('client_id', $id)
                ->whereDate('visit_date', now()->toDateString())
                ->whereNull('departure_time')
                ->value('id');

            if ($openVisitId) {
                return redirect()->route('clients.visitWorkspace', ['client' => $id, 'visit' => $openVisitId]);
            }

            /* ولا نُحوّل أحداً إلى مسارٍ يردّه بـ403: الباب صار بسعة مساحة
               الزيارة نفسها (راجع تعليقه في ملفّ المسارات)، وهذا الفحص يبقى
               حارساً لو ضاق أحدُهما عن الآخر يوماً. حين يتعذّر التحويل تُعرض
               الشاشة بدل حائطٍ بلا مخرج. */
            $canRegisterVisit = hasPermission([
                'clients_visits_track',
                'clients_itinerary_add',
                'clients_visit_workspace_access',
                'clients_must_visit_before_view',
            ]);

            if ($canRegisterVisit) {
                return redirect()->route('clients.openVisitWorkspace', $id);
            }
        }

        // تحميل العميل المحدد مع جميع العلاقات الضرورية (مع استبعاد الفواتير الreturnedة)
        $client = Client::with([
            'invoices' => function ($query) {
                $query->where('type', '!=', 'returned')
                    ->whereNotIn('id', function ($subQuery) {
                        $subQuery->select('reference_number')
                            ->from('invoices')
                            ->whereNotNull('reference_number');
                    })
                    ->orderBy('created_at', 'desc');
            },
            'invoices.payments',
            'appointments' => function ($query) {
                $query->with(['employees', 'employee'])->orderBy('created_at', 'desc');
            },
            'employee',
            'customerTarget',
            'account',
            'payments' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'appointmentNotes' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'visits.employee' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        // تحميل البيانات الإضافية
        // $installment = Installment::with('invoice.client')->get();
        // نافذة «تعيين موظفين للعميل» في هذه الشاشة تقرأ هذه القائمة —
        // فتتبع نفس نطاق الأقسام الذي تتبعه شاشتا الإضافة والتعديل
        $employees = $this->employeesForClientAssignment($client);
        $account = Account::all();
        // Statuses::all() كانت تجلب مراحل التسجيل (section='registration_stage')
        // معها فتظهر ضمن قائمة «حالة العميل» — راجع docs/CRM_Customer_Status_Gap_Analysis_Plan.md
        // ونشط/موقوف فقط الآن؛ الحالات القديمة أثرٌ تاريخي لا خيارٌ حيّ — راجع Statuses::scopeAssignable()
        $statuses = Statuses::assignable()->get();

        /* حجوزاتُ العميل — لتبويب «الحجوزات». كان `$bookings` يُحمَّل من نموذج
           `Booking` القديم ولا يقرؤه قالبٌ واحد؛ الآن مستندُ الحجز الحقيقي مع
           خدمته وموظفه، الأحدثُ أوّلاً. */
        $reservations = \Modules\Client\Reservations\Models\Reservation::query()
            ->where('client_id', $id)
            ->with(['service:id,name', 'employee:id,first_name,middle_name,nickname'])
            ->orderByDesc('reservation_date')
            ->orderByDesc('start_time')
            ->get();
        $productExchanges = ProductExchange::where('client_id', $id)
            ->with(['storehouse', 'employee', 'items.product', 'items.exchangeReason'])
            ->orderBy('created_at', 'desc')
            ->get();

        // شريط «المنافسون عند العميل الآن» في تبويب ملاحظات الزيارات — آخر
        // رصدٍ لكل منافس، الأحدث أولاً. آخر ٢٠٠ رصد تكفي لصورة الوضع.
        $competitorObservations = \App\Models\VisitCompetitorObservation::where('client_id', $id)
            ->with(['competitor:id,name', 'employee:id,name', 'visit:id,visit_number'])
            ->latest('id')
            ->limit(200)
            ->get();

        // تحميل الفواتير (مع التصفية الإضافية للتأكد)
        // تجهيز الفواتير - جلب الكل بدون pagination
        $invoices = $client->invoices()
            ->where('type', '!=', 'returned')
            ->whereNull('reference_number')
            ->with(['createdByUser:id,name', 'employee'])
            ->orderBy('created_at', 'desc')
            ->get();

        // تجهيز المواعيد - جلب الكل بدون pagination
        $appointments = $client->appointments()->with(['employees', 'employee'])
            ->orderBy('created_at', 'desc')
            ->get();

        // تجهيز المدفوعات - جلب الكل بدون pagination
        $payments = PaymentsProcess::whereHas('invoice', function ($query) use ($client) {
            $query->where('client_id', $client->id);
        })
            ->with('invoice:id,code')
            ->orderBy('created_at', 'desc')
            ->get();

        // تجهيز الزيارات - جلب الكل بدون pagination (من جدول EmployeeClientVisit)
        $visits = EmployeeClientVisit::where('client_id', $id)
            ->with('employee')
            ->orderBy('visit_date', 'desc')
            ->get();

        // حالةُ كل زيارة بقاعدة لوحة العملاء: مكتملة / جارية / فائتة / مبرَّرة / لم يُزَر بعد / ملغاة
        $visitStates = app(\App\Services\ClientDashboardService::class)->clientVisitStates((int) $id);

        // تجهيز الملاحظات - مع pagination
        /* `competitorObservations` محمَّلةٌ مسبقاً: بطاقة الملاحظة تعرض
           المنافسين المرصودين معها، وبلا التحميل استعلامٌ لكل بطاقة. */
        $allClientNotes = ClientRelation::where('client_id', $id)
            ->with(['employee', 'clientEmployee', 'competitorObservations.competitor'])
            ->orderBy('created_at', 'desc')
            ->get();

        /* بطاقات «ملاحظات الزيارات»: بطاقةٌ لكل زيارة تضمّ ملاحظتها ومستنداتها
           (فاتورة، سند، مرتجع، إشعار). الترقيم على البطاقات لا على الملاحظات —
           ترقيم الملاحظات كان يقسم الزيارة الواحدة بين صفحتين. */
        $visitNoteCards = \App\Services\Clients\VisitNoteCards::build($allClientNotes, (int) $id);
        $noteLeads = \App\Services\Clients\VisitNoteCards::leadNotes($allClientNotes, $visitNoteCards);
        $notesPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $ClientRelations = new \Illuminate\Pagination\LengthAwarePaginator(
            $noteLeads->forPage($notesPage, 6)->values(),
            $noteLeads->count(),
            6,
            $notesPage,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );

        if (request()->ajax()) {
            return response()->json([
                'html' => view('client::partials.tabs.notes-list', compact('ClientRelations', 'client', 'visitNoteCards'))->render(),
                'pagination' => $ClientRelations->links('client::partials.client-notes-pagination')->toHtml(),
            ]);
        }

        $invoice_due = $invoices->sum('due_value');
        $due = Account::ledger()->where('client_id', $id)->sum('balance');

        // تحميل الملاحظات
        $appointmentNotes = $client->appointmentNotes;

        // تحميل الفئات والعلاقات الأخرى
        $categories = CategoriesClient::all();

        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        $account = Account::ledger()->where('client_id', $id)->first();

        if (! $account) {
            return redirect()->back()->with('error', 'لا يوجد حساب مرتبط بهذا العميل.');
        }

        $accountId = $account->id;
        // جلب بيانات الخزينة
        $treasury = $this->getTreasury($accountId);
        $branches = $this->getBranches();

        // جلب العمليات المالية
        $transactions = $this->getTransactions($accountId);
        $transfers = $this->getTransfers($accountId);
        $expenses = $this->getExpenses($accountId);
        $revenues = $this->getRevenues($accountId);

        // معالجة العمليات وحساب الرصيد
        $allOperations = $this->processOperations($transactions, $transfers, $expenses, $revenues, $treasury);

        // ترتيب العمليات حسب التاريخ
        usort($allOperations, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // تقسيم العمليات إلى صفحات
        $operationsPaginator = $this->paginateOperations($allOperations);

        // جلب الحد الائتماني للعميل
        $creditLimit = CreditLimit::getClientCreditLimit($id);

        // جلب إعدادات العميل العامة
        $GeneralClientSettings = GeneralClientSetting::where('is_active', true)->get();

        // تحقق مما إذا كان الموظف قد أضاف ملاحظة اليوم أو لديه زيارة مكتملة
        // نقسم العلامة إلى نوعين:
        //   $hasVisitNote = ملاحظة الزيارة السريعة (Modal) — تفتح "فاتورة" و "سند قبض" فقط
        //   $hasMainNote  = الملاحظة الرئيسية (إضافة ملاحظة) — تفتح كل الإجراءات
        $hasVisitNote = true;
        $hasMainNote = true;
        $moduleSettings = \App\Models\ClientModuleSetting::current();

        // استبعاد الموقع يعفي من ملاحظة الزيارة كذلك: عميلٌ لا يُشترط عليه
        // تحقّق الموقع لا معنى لإبقائه محجوباً خلف اشتراط الملاحظة وحده.
        // (استبعاد «معاملة اليوم» لتبديل البضاعة أُلغي كلياً — راجع
        // hasTodayTransaction/canExchange أدناه — فلا أثر له هنا بعد اليوم)
        $isExemptFromVisitNote = $moduleSettings->exempt_location_enabled && (bool) $client->exempt_visit_location;

        /* إعفاءُ الشخص (`clients_exempt_action_locks`) فوق إعفاء العميل
           (`exempt_visit_location`): الأوّل يقول «هذا الموظّف لا يُشترط عليه»،
           والثاني «هذا العميل لا يُشترط فيه». وأيُّهما تحقّق فتحَ الأزرار. */
        $exemptActionLocks = hasPermission('clients_exempt_action_locks');

        if (auth()->user()->isEmployee() && ! $exemptActionLocks) {
            if (! ClientSettingsHelper::visitNoteRequired() || $isExemptFromVisitNote) {
                $hasVisitNote = true;
                $hasMainNote = true;
            } else {
                $today = now()->toDateString();
                $userId = auth()->id();

                $hasVisitNote = ClientRelation::where('client_id', $id)
                    ->where('employee_id', $userId)
                    ->whereDate('created_at', $today)
                    ->exists() ||
                    EmployeeClientVisit::where('client_id', $id)
                        ->where('employee_id', $userId)
                        ->whereDate('visit_date', $today)
                        ->where(function ($q) {
                            $q->where('status', 'active')
                                ->orWhere(function ($q2) {
                                    $q2->whereNotNull('justification')
                                        ->where('justification', '!=', '')
                                        ->where('justification_approved', 1);
                                });
                        })
                        ->exists();

                $hasMainNote = AppointmentNote::where('client_id', $id)
                    ->where('user_id', $userId)
                    ->whereDate('created_at', $today)
                    ->exists() ||
                    ClientEmployeeNote::where('client_id', $id)
                        ->where('employee_id', $userId)
                        ->whereDate('created_at', $today)
                        ->exists();
            }
        }
        // للحفاظ على التوافق العكسي مع أي كود قديم
        $hasNoteToday = $hasVisitNote || $hasMainNote;

        // فحوصات إضافية لزر "تبديل البضاعة":
        //   - يُفتح فقط إذا اليوم يوم خط سير الموظف لهذا العميل
        //   - وعنده فاتورة أو سند قبض أو إشعار دائن للعميل اليوم
        $isItineraryDay = true;
        $hasTodayTransaction = true;
        $exchangeRequiresTransaction = ClientSettingsHelper::exchangeRequiresTransaction();
        if (auth()->user()->isEmployee()) {
            $today = now()->toDateString();
            $userId = auth()->id();

            /* «يوم خطّ السير» قيدُ جدولةٍ لا ضابطٌ رقابي — يُعفى منه حاملُ
               `clients_exempt_action_locks`. أمّا `hasTodayTransaction` أدناه
               فيُحسب له كما يُحسب لغيره: هو الضابط الذي أُلغي استثناؤه عمداً. */
            $isItineraryDay = $exemptActionLocks
                || EmployeeClientVisit::isItineraryDayFor($userId, (int) $id);

            $hasInvoiceToday = Invoice::where('client_id', $id)
                ->whereDate('invoice_date', $today)
                ->exists();

            $hasCreditNoteToday = \DB::table('credit_notifications')
                ->where('client_id', $id)
                ->whereDate('credit_date', $today)
                ->exists();

            $hasReceiptToday = Receipt::whereHas('account', function ($q) use ($id) {
                $q->where('client_id', $id);
            })->whereDate('created_at', $today)->exists();

            $hasPaymentToday = PaymentsProcess::whereHas('invoice', function ($q) use ($id) {
                $q->where('client_id', $id);
            })->whereDate('created_at', $today)->exists();

            $hasTodayTransaction = ! $exchangeRequiresTransaction
                || $hasInvoiceToday || $hasCreditNoteToday || $hasReceiptToday || $hasPaymentToday;
        }
        // شرط «فاتورة/سند/إشعار دائن اليوم» غير قابلٍ للاستثناء بعد اليوم —
        // كان استبعاد «exempt_exchange_transaction» يُبيح تجاوزه، وأُزيل عمداً
        $canExchange = $isItineraryDay && $hasTodayTransaction;

        $statusRequests = ClientStatusRequest::where('client_id', $id)
            ->with(['oldStatus', 'newStatus', 'requester'])
            ->orderBy('created_at', 'desc')
            ->get();

        /* ثلاثةُ أنواعٍ في الخطّ الزمني الواحد:
             `client`  — ما كتبه `ModelsLog::create` قبل التوحيد.
             `Clients` — ما يكتبه `OperationLog` الآن، بمعرّفٍ صحيح.
             `clients` — صفوفٌ قديمة بلا `type_id`، تُلتقَط بالاسم وحده.
           والأخيرُ يبقى لأنّ صفوفَه في القاعدة فعلاً؛ ولا يُكتب مثلُها بعد
           اليوم، فالمطابقةُ بالاسم لا تتوسّع. */
        $activityLogs = ModelsLog::where(function ($q) use ($id) {
            $q->whereIn('type', ['client', self::LOG_MODULE])->where('type_id', $id);
        })
            ->orWhere(function ($q) use ($client) {
                $q->where('type', 'clients')->where('description', 'like', '%'.$client->trade_name.'%');
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        /* الوصولُ يُسجَّل هنا لا في أوّل الدالة: كلُّ ما قبله قد يُعيد
           التوجيه (إلزام الزيارة، عميلٌ بلا حساب)، ومن رُدّ لم يصل الملف.
           وطلبُ AJAX لترقيم الملاحظات خرج قبل هذا السطر فلا يُعدّ دخولاً. */
        \App\Models\ClientProfileView::record((int) $id, auth()->id());

        $canViewProfileAccessLog = hasPermission('clients_view_profile_access_log');
        $profileViews = $canViewProfileAccessLog ? \App\Models\ClientProfileView::summaryFor((int) $id) : collect();
        $profileViewEntries = $canViewProfileAccessLog ? \App\Models\ClientProfileView::entriesFor((int) $id) : collect();

        // إرسال البيانات إلى الواجهة
        return view('client::show', compact(
            'client',
            'treasury',
            'account',
            'operationsPaginator',
            'branches',
            'ClientRelations',
            'visits',
            'visitStates',
            'due',
            'invoice_due',
            'statuses',
            'account',
            'employees',
            'reservations',
            'invoices',
            'payments',
            'appointmentNotes',
            'account_setting',
            'creditLimit',
            'GeneralClientSettings',
            'hasNoteToday',
            'hasVisitNote',
            'hasMainNote',
            'isItineraryDay',
            'hasTodayTransaction',
            'canExchange',
            'productExchanges',
            'competitorObservations',
            'visitNoteCards',
            'statusRequests',
            'activityLogs',
            'canViewProfileAccessLog',
            'profileViews',
            'profileViewEntries'
        ));
    }

    public function paginate(Request $request, $id)
    {
        $client = Client::findOrFail($id);
        $type = $request->input('type', 'invoices');
        $perPage = $request->input('per_page', 10);
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        switch ($type) {
            case 'invoices':
                $items = $client->invoices()
                    ->where('type', '!=', 'returned')
                    ->whereNull('reference_number')
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.invoices-table', [
                    'client' => $client,
                    'invoices' => $items,
                    'currencySymbol' => \App\Support\CurrencySettings::symbol(),
                ])->render();

            case 'payments':
                $items = $client->payments()
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.payments-table', [
                    'client' => $client,
                    'payments' => $items,
                    'currencySymbol' => \App\Support\CurrencySettings::symbol(),
                ])->render();

            case 'appointments':
                $items = $client->appointments()->with(['employees', 'employee'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.appointments-table', [
                    'client' => $client,
                    'appointments' => $items,
                ])->render();

            case 'visits':
                $items = EmployeeClientVisit::where('client_id', $id)
                    ->with('employee')
                    ->orderBy('visit_date', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.visits-table', [
                    'client' => $client,
                    'visits' => $items,
                ])->render();

            case 'notes':
                $items = ClientRelation::where('client_id', $id)
                    ->with(['employee', 'clientEmployee', 'competitorObservations.competitor'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.notes-table', [
                    'client' => $client,
                    'ClientRelations' => $items,
                ])->render();

            default:
                return response()->json(['error' => 'نوع غير صالح'], 400);
        }
    }

    /**
     * Generate PDF of client notes
     */
    public function exportPdf(Request $request)
    {
        $type = $request->get('type', 'all'); // الافتراضي تصدير الكل

        // بناء الاستعلام الأساسي
        $query = Client::with(['status_client', 'branch', 'neighborhood.region', 'categoriesClient', 'account', 'balance']);

        if ($type === 'filtered') {
            // تطبيق نفس فلاتر الصفحة (مثلاً حسب الحالة أو الفرع)
            // هنا افترض أن الفلاتر تأتي من الـ session أو الـ request
            if ($request->filled('branch_id')) {
                $query->inTreeNode((int) $request->branch_id, static::wantsDescendants($request));
            }
            if ($request->filled('status_id')) {
                $query->where('status_id', $request->status_id);
            }

            $clients = $query->get();

        } elseif ($type === 'page') {
            // جلب الصفحة الحالية فقط (حسب pagination)
            $page = $request->get('page', 1);
            $perPage = 10; // نفس عدد العناصر في pagination
            $clients = $query->paginate($perPage, ['*'], 'page', $page)->items();

        } else {
            // تصدير الكل
            $clients = $query->orderBy('trade_name')->get();
        }

        // إنشاء PDF
        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('تقرير العملاء');

        $pdf->SetMargins(10, 10, 10);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setRTL(true);
        $pdf->AddPage();
        $pdf->SetFont('aealarabiya', '', 11);

        // إنشاء المحتوى
        $html = view('client::partials.clients_pdf', compact('clients', 'type'))->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('clients_report.pdf', 'I');
    }

    public function exportAllClients()
    {
        // جلب جميع العملاء مع العلاقات الأساسية المهمة للتصدير
        $clients = Client::with([
            'branch',
            'categoriesClient',
            'status_client',
        ])->get();

        // تجهيز البيانات بصيغة منظمة للتصدير
        $data = $clients->map(function ($client) {
            return [
                'id' => $client->id,
                'code' => $client->code ?? '-',
                'trade_name' => $client->trade_name ?? '-',
                'first_name' => trim(($client->first_name ?? '').' '.($client->last_name ?? '')),
                'phone' => $client->phone ?? '-',
                'category' => $client->categoriesClient->name ?? $client->category ?? '-',
                'branch' => $client->branch->name ?? '-',
                'status' => $client->status_client->name ?? '—',
                'distance' => $client->locations->map_url ?? '-',
                'created_at' => optional($client->created_at)->format('Y-m-d'),
                'last_invoice' => optional($client->invoices()->latest('invoice_date')->first())->invoice_date ?? '-',

                'balance' => number_format($client->Balance(), 2),
                'credit_limit' => $client->credit_limit ?? '-',
                'credit_period' => $client->credit_period ?? '-',
                'address' => $client->formatted_address ?? '-',
            ];
        });

        return response()->json($data);
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $client = Client::findOrFail($id);
            $client->notes = $request->notes; // تحديث الملاحظات بالحالة الجديدة
            $changes = OperationLog::diff($client, ['notes']);
            $client->save();

            OperationLog::record(
                'notes_updated',
                self::LOG_MODULE,
                $client,
                'تحديث ملاحظات العميل «'.$client->trade_name.'»',
                $changes
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update_status', (int) $id);

            return response()->json(['success' => false], 500);
        }
    }

    public function contact()
    {
        $clients = Client::all();

        return view('client::contacts.contact_mang', compact('clients'));
    }

    public function contacts(Request $request)
    {
        /* ── النطاق: نفسُ محورَي «إدارة العملاء» ──
           كانت `Client::query()` بلا أيّ نطاق: المندوبُ ذو «عملائي فقط» يرى
           دليلَ هواتف المنشأة كلِّها، وتبديلُ الفرع في الشريط لا يغيّر شيئاً.
           `applyDocumentSelectScope()` تجمع المحورين كما يطبّقهما الفهرس —
           الصلاحية أوّلاً، ثمّ فرعُ الشريط وما تحته لمن يرى كلَّ العملاء
           (والفرعُ الرئيسيّ يعني المنشأة كلَّها، انظر `HeaderBranchScope`). */
        $query = Client::query()
            ->select(['id', 'code', 'trade_name', 'first_name', 'last_name', 'phone', 'mobile', 'email',
                'employee_id', 'status_id', 'tree_node_id', 'branch_id'])
            ->with(['status_client:id,name,color', 'contacts:id,client_id,first_name,last_name,phone,mobile,email']);

        ClientVisibilityScope::applyDocumentSelectScope($query);

        /* البحث الأساسي — الاسم والكود وأرقام العميل، **وجهاتُ اتصاله**.
           كان يبحث أيضاً في `employees.trade_name` — عمودٌ لا وجود له في
           جدول الموظفين، فأيُّ بحثٍ كان يُسقط الشاشة بخطأ SQL. */
        if ($request->filled('search')) {
            $search = '%'.trim($request->input('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('trade_name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('mobile', 'like', $search)
                    ->orWhereHas('contacts', function ($c) use ($search) {
                        $c->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('phone', 'like', $search)
                            ->orWhere('mobile', 'like', $search);
                    });
            });
        }

        /* البحث المتقدم — الحقلُ في النموذج اسمه `advanced_search`، والمتحكّم
           كان يقرأ `phone`/`mobile`/`email` منفصلةً لا يرسلها النموذج، فكان
           البحثُ المتقدّم لا يفعل شيئاً. */
        if ($request->filled('advanced_search')) {
            $term = '%'.trim($request->input('advanced_search')).'%';
            $query->where(function ($q) use ($term) {
                $q->where('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('mobile', 'like', $term)
                    ->orWhereHas('contacts', function ($c) use ($term) {
                        $c->where('email', 'like', $term)
                            ->orWhere('phone', 'like', $term)
                            ->orWhere('mobile', 'like', $term);
                    });
            });
        }

        $clients = $query->orderByRaw('TRIM(trade_name)')->paginate(25)->withQueryString();

        // موقعُ كل صفٍّ في الشجرة يُقرأ من ذاكرة الفروع لا باستعلامٍ لكل سلف
        Branch::preloadCache();

        $headerNodeId = HeaderBranchScope::nodeId();
        $headerBranch = current_branch_id() ? Branch::findCached(current_branch_id()) : null;

        return view('client::contacts.contact_mang', compact('clients', 'headerNodeId', 'headerBranch'));
    }

    public function show_contant($id)
    {
        /* `appointments.notes` كانت تحمّل علاقةً غير موجودة أصلاً على
           Appointment (`notes` عمودٌ نصّي عادي لا علاقة) — الشاشة كانت
           تسقط بـRelationNotFoundException عند أوّل عميلٍ له موعد. */
        $client = Client::with(['appointments.employee', 'appointments.client'])->findOrFail($id);
        $notes = AppointmentNote::with(['appointment', 'user'])
            ->whereHas('appointment', function ($query) use ($id) {
                $query->where('client_id', $id);
            })
            ->latest()
            ->get();

        // كانت `client.contacts.show_contant` (نقطة لا `::`) — لا وجود لهذه
        // الشاشة أصلاً بهذا الاسم في مُحلِّل Blade؛ الشاشة كانت 500 دائماً.
        return view('client::contacts.show_contant', compact('client', 'notes'));
    }

    public function mang_client(Request $request)
    {
        // عقد المناطق الآن في branches (is_territory=1)
        $clientGroups = Branch::withoutGlobalScopes()
            ->where('is_territory', 1)
            ->orderBy('name')
            ->get();
        $invoices = Invoice::all();
        $notes = ClientRelation::all();
        $clients = Client::with([
            'invoices',
            'appointmentNotes.employee',
            'clientRelations' => function ($query) {
                $query->with(['employee', 'location', 'competitorObservations.competitor'])->orderBy('date', 'desc');
            },
        ])
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->full_name,
                    'phone' => $client->phone,
                    'balance' => $client->balance,
                    'invoices' => $client->invoices->map(function ($invoice) {
                        return [
                            'id' => $invoice->id,
                            'number' => $invoice->code,
                            'date' => $invoice->invoice_date->format('Y-m-d'),
                            'amount' => $invoice->grand_total,
                            'status' => $invoice->payment_status,
                            'remaining' => $invoice->remaining_amount,
                            'paymentMethod' => $invoice->payment_method,
                        ];
                    }),
                    'notes' => $client->appointmentNotes->map(function ($note) {
                        return [
                            'id' => $note->id,
                            'date' => $note->date,
                            'employee' => $note->employee->name ?? 'غير محدد',
                            'content' => $note->description,
                            'status' => $note->status,
                        ];
                    }),
                    'relations' => $client->clientRelations->map(function ($relation) {
                        return [
                            'id' => $relation->id,
                            'status' => $relation->status,
                            'process' => $relation->process,
                            'time' => $relation->time,
                            'date' => $relation->date,
                            'employee' => $relation->employee->name ?? 'غير محدد',
                            'description' => $relation->description,
                            'location' => $relation->location
                                ? [
                                    'id' => $relation->location->id,
                                    'address' => $relation->location->address,
                                    'coordinates' => $relation->location->coordinates,
                                ]
                                : null,
                            'site_type' => $relation->site_type,
                            'competitor_documents' => $relation->competitor_documents,
                            // المنافسون المرصودون مع الملاحظة — يعرضهم كرتُها
                            // في هذه الشاشة كما يعرضهم في شاشة العميل
                            'competitors' => $relation->competitorObservations->map(fn ($o) => [
                                'name' => $o->display_name,
                                'asset_count' => $o->asset_count,
                            ])->values(),
                            'additional_data' => $relation->additional_data,
                        ];
                    }),
                ];
            });

        return view('client::relestion_mang_client', [
            'clients' => $clients,
            'invoices' => $invoices,
            'notes' => $notes,
            'clientGroups' => $clientGroups,
        ]);
    }

    public function getClientData($clientId)
    {
        $client = Client::find($clientId);

        if (! $client) {
            return response()->json(['error' => 'العميل غير موجود'], 404);
        }

        // جلب الفواتير الخاصة بهذا العميل فقط
        $invoices = Invoice::where('client_id', $clientId)
            ->with(['client', 'createdByUser', 'employee'])
            ->orderBy('created_at', 'desc')
            ->get();

        // جلب الملاحظات الخاصة بهذا العميل
        $notes = ClientRelation::where('client_id', $clientId)->with('employee')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'client' => $client,
            'invoices' => $invoices,
            'notes' => $notes,
        ]);
    }

    public function getAllClients()
    {
        $clients = Client::with('latestStatus')->orderBy('created_at', 'desc')->get();

        return response()->json($clients);
    }

    /**
     * الرصيد الافتتاحي للعميل — دَينٌ عليه لنا دائماً، بلا اختيار اتجاه.
     *
     * خلاف المورد (رصيده الافتتاحي دائماً **لنا عليه**، مدينٌ نحن)، رصيد
     * العميل الافتتاحي دائماً **له علينا** بمعنى محاسبي معكوس: هو مدينٌ
     * لنا. لا حالة عكسية تحتاج خياراً هنا بقرار صريح من المستخدم — عميلٌ
     * دفع مقدَّماً يُسجَّل عبر محفظته من شاشتها لا من هذا النموذج.
     */
    public function updateOpeningBalance(Request $request, $id)
    {
        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ], [
            'opening_balance.required' => 'أدخل مبلغ الرصيد الافتتاحي.',
            'opening_balance.numeric' => 'مبلغ الرصيد الافتتاحي يجب أن يكون رقماً.',
            'opening_balance.min' => 'المبلغ لا يكون سالباً.',
        ]);

        try {
            DB::beginTransaction();

            $client = Client::findOrFail($id);

            // الحصول على الحساب المرتبط بالعميل
            $Account = Account::ledger()->where('client_id', $id)->first();

            if (! $Account) {
                DB::rollBack();

                return redirect()->back()->with('error', 'لا يوجد حساب مرتبط بهذا العميل');
            }

            $amount = (float) $validated['opening_balance'];
            $previousAmount = (float) ($Account->opening_balance ?? 0);

            $Account->opening_balance = $amount;
            $Account->opening_balance_type = Account::DEBIT;
            $Account->save();

            // أيّ رصيدٍ سابقٍ عبر المحفظة (من قبل إزالة خيار الاتجاه) يُصفَّى:
            // الاتجاه الآن مدينٌ دائماً، فلا يبقى أثرٌ لتسجيلٍ سابقٍ عكسه
            ClientWalletService::clearOpeningBalance($client);

            if ($amount != 0) {
                $Account->createOrUpdateOpeningBalanceJournalEntry();
            } else {
                $Account->deleteExistingOpeningBalanceEntries();
            }

            OperationLog::record(
                'updated',
                'Client.Client',
                $client->id,
                sprintf(
                    'تم تحديث الرصيد الافتتاحي للعميل «%s» من %s إلى %s',
                    $client->trade_name,
                    number_format($previousAmount, 2),
                    number_format($amount, 2),
                ),
            );

            DB::commit();

            return redirect()->route('clients.show', $id)->with('success', 'تم تحديث الرصيد الافتتاحي بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'updateOpeningBalance');

            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getNextClient(Request $request)
    {
        $currentClientId = $request->query('currentClientId');
        $nextClient = Client::where('id', '>', $currentClientId)->orderBy('id', 'asc')->first();

        if ($nextClient) {
            $nextClient->load('notes'); // تحميل الملاحظات المرتبطة

            return response()->json(['client' => $nextClient]);
        }

        return response()->json(['client' => null]);
    }

    public function getPreviousClient(Request $request)
    {
        $currentClientId = $request->query('currentClientId');
        $previousClient = Client::where('id', '<', $currentClientId)->orderBy('id', 'desc')->first();

        if ($previousClient) {
            $previousClient->load('notes'); // تحميل الملاحظات المرتبطة

            return response()->json(['client' => $previousClient]);
        }

        return response()->json(['client' => null]);
    }

    public function getFirstClient()
    {
        $firstClient = Client::orderBy('id', 'asc')->first();
        if ($firstClient) {
            $firstClient->load('notes');

            return response()->json(['client' => $firstClient]);
        }

        return response()->json(['client' => null]);
    }

    public function mang_client_store(ClientRequest $request)
    {
        $data_request = $request->except('_token');

        // إنشاء العميل
        $client = new Client;

        // الحصول على الرقم الحالي لقسم العملاء من جدول serial_settings
        $serialSetting = SerialSetting::where('section', 'customer')->first();

        // إذا لم يتم العثور على إعدادات، نستخدم 1 كقيمة افتراضية
        $currentNumber = $serialSetting ? $serialSetting->current_number : 1;

        // تعيين id للعميل الجديد باستخدام الرقم الحالي
        // $client->id = $currentNumber;

        // تعيين الكود للعميل الجديد (إذا كان الكود مطلوبًا أيضًا)
        $client->code = $currentNumber;

        // تعبئة البيانات الأخرى
        $client->fill($data_request);

        // معالجة الصورة
        if ($request->hasFile('attachments')) {
            $file = $request->file('attachments');
            if ($file->isValid()) {
                $filename = time().'_'.$file->getClientOriginalName();
                $file->move(public_path('assets/uploads/'), $filename);
                $client->attachments = $filename;
            }
        }

        // حفظ العميل
        $client->save();

        // تسجيل اشعار نظام جديد
        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $client,
            'إضافة عميل «'.$client->trade_name.'»',
            ['new' => $client->only(['code', 'trade_name', 'phone', 'status_id', 'branch_id'])]
        );

        // زيادة الرقم الحالي بمقدار 1
        if ($serialSetting) {
            $serialSetting->update(['current_number' => $currentNumber + 1]);
        }

        // إنشاء حساب فرعي باستخدام trade_name
        $customers = Account::where('name', 'العملاء')->first(); // الحصول على حساب العملاء الرئيسي
        if ($customers) {
            $customerAccount = new Account;
            $customerAccount->name = $client->trade_name; // استخدام trade_name كاسم الحساب
            $customerAccount->client_id = $client->id;

            // تعيين نوع الحساب المالي ليكون مطابقًا للحساب الأب
            $customerAccount->account_type = $customers->account_type;

            // تعيين كود الحساب الفرعي بناءً على كود الحسابات
            $lastChild = Account::where('parent_id', $customers->id)->orderBy('code', 'desc')->first();
            $newCode = $lastChild ? $this->generateNextCode($lastChild->code) : $customers->code.'1'; // استخدام نفس منطق توليد الكود
            $customerAccount->code = $newCode; // تعيين الكود الجديد للحساب الفرعي

            $customerAccount->balance_type = 'debit'; // أو 'credit' حسب الحاجة
            $customerAccount->parent_id = $customers->id; // ربط الحساب الفرعي بحساب العملاء
            $customerAccount->is_active = false;

            // تعيين الرصيد الافتتاحي ونوعه
            $customerAccount->opening_balance = 0; // لا يوجد رصيد افتتاحي في هذا السياق
            $customerAccount->opening_balance_type = $customers->balance_type; // مطابق لنوع الرصيد الأب
            $customerAccount->is_active = true;

            $customerAccount->save();

            // سيتم إنشاء قيد الرصيد الافتتاحي تلقائيًا من خلال نموذج الحساب إذا لزم الأمر
        }

        // حفظ جهات الاتصال المرتبطة بالعميل
        if ($request->has('contacts') && is_array($request->contacts)) {
            foreach ($request->contacts as $contact) {
                $client->contacts()->create($contact);
            }
        }

        return redirect()->route('clients.mang_client')->with('success', '✨ تم إضافة العميل بنجاح!');
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (is_null($lat1) || is_null($lon1) || is_null($lat2) || is_null($lon2)) {
            return 0;
        }

        if (abs($lat1) > 90 || abs($lon1) > 180 || abs($lat2) > 90 || abs($lon2) > 180) {
            return 0;
        }

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * asin(sqrt($a));
        $distance = 6371000 * $c; // بالمتر

        return $distance / 1000; // تحويل إلى كيلومتر
    }

    public function forceShow(Client $client)
    {
        // الصلاحية مضبوطة عبر middleware('permission:clients_hide_from_map') في الباني
        try {
            $client->update([
                'force_show' => true,
                'last_note_at' => null,
            ]);

            OperationLog::record(
                'map_force_shown',
                self::LOG_MODULE,
                $client,
                'إظهار العميل «'.$client->trade_name.'» في الخريطة قبل انتهاء مدّة الإخفاء'
            );

            return back()->with('success', 'تم إظهار العميل في الخريطة بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'force_show', $client);

            return back()->with('error', 'حدث خطأ أثناء إظهار العميل في الخريطة.');
        }
    }

    // دالة مساعدة لحساب المسافة

    /**
     * حساب المسافة باستخدام Haversine formula
     */
    public function mang_client_details($id)
    {
        try {
            // Find the client
            $client = Client::with(['employee'])->findOrFail($id);

            // Get all clients for the sidebar
            $clients = Client::orderBy('created_at', 'desc')->get();

            // Get notes and appointments
            $notes = AppointmentNote::with(['user'])
                ->latest()
                ->get();
            $appointments = Appointment::all();
            $employees = Employee::all();

            // Get previous and next client IDs
            $previousClient = Client::where('id', '<', $id)->orderBy('id', 'desc')->first();
            $nextClient = Client::where('id', '>', $id)->orderBy('id', 'asc')->first();

            // If it's an AJAX request, return JSON
            if (request()->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'client' => $client,
                        'trade_name' => $client->trade_name,
                        'phone' => $client->phone,
                        'email' => $client->email,
                        'status' => $client->status,
                        'employee' => $client->employee
                            ? [
                                'name' => $client->employee->name,
                                'department' => $client->employee->department,
                                'role' => $client->employee->role,
                            ]
                            : null,
                    ],
                ]);
            }

            // For regular requests, return the view
            return view('client.relestion_mang_client', compact('clients', 'client', 'employees', 'notes', 'appointments', 'previousClient', 'nextClient'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'mang_client_details');

            // If it's an AJAX request, return error response
            if (request()->ajax()) {
                return response()->json(
                    [
                        'status' => 'error',
                        'message' => 'حدث خطأ أثناء تحميل بيانات العميل',
                    ],
                    422,
                );
            }

            // For regular requests, redirect with error
            return redirect()->route('clients.mang_client')->with('error', 'حدث خطأ أثناء تحميل بيانات العميل');
        }
    }

    public function search(Request $request)
    {
        try {
            $searchTerm = $request->query('query'); // تغيير اسم المتغير هنا

            $clients = Client::with(['latestStatus', 'employee'])
                ->where(function ($queryBuilder) use ($searchTerm) {
                    // استخدام اسم مختلف للـ Query Builder
                    $queryBuilder
                        ->where('trade_name', 'like', '%'.$searchTerm.'%')
                        ->orWhere('code', 'like', '%'.$searchTerm.'%')
                        ->orWhere('phone', 'like', '%'.$searchTerm.'%')
                        ->orWhere('email', 'like', '%'.$searchTerm.'%');
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => $clients->items(),
                'pagination' => [
                    'total' => $clients->total(),
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                    'per_page' => $clients->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'search');

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'حدث خطأ أثناء البحث',
                ],
                500,
            );
        }
    }

    // assignEmployees()، removeEmployee()، getAssignedEmployees() نُقلت إلى
    // ClientEmployeeController — بلا تغيير في المنطق أو أسماء الـ routes.
    // (removeEmployee() كانت تشير إلى متغيّر $employeeId غير معرَّف في
    // رسالة السجلّ — صُحِّحت إلى $request->employee_id عند النقل.)

    public function import(Request $request)
    {
        set_time_limit(500);
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        Excel::import(new ClientsImport, $request->file('file'));

        return redirect()->back()->with('success', 'تم استيراد العملاء بنجاح!');
    }

    /**
     * استيراد العملاء من ملف Excel
     */
    public function importExcel(Request $request)
    {
        try {
            set_time_limit(500);
            $request->validate([
                'file' => 'required|mimes:xlsx,xls,csv',
            ], [
                'file.required' => 'يرجى اختيار ملف للاستيراد',
                'file.mimes' => 'يجب أن يكون الملف بصيغة Excel (xlsx, xls) أو CSV',
            ]);

            $file = $request->file('file');

            try {
                $imported = Excel::import(new ClientsImport, $file);

                OperationLog::record(
                    'imported',
                    self::LOG_MODULE,
                    null,
                    'استيراد عملاء من ملف Excel «'.$file->getClientOriginalName().'»'
                );

                return response()->json([
                    'success' => true,
                    'message' => '✅ تم استيراد العملاء بنجاح! يرجى تحديث الصفحة.',
                ]);
            } catch (\Exception $importError) {
                // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
                OperationLog::failure($importError, self::LOG_MODULE, 'importExcel');

                // معالجة أخطاء قاعدة البيانات بشكل أفضل
                $errorMsg = $importError->getMessage();

                if (strpos($errorMsg, 'trade_name') !== false) {
                    $message = '❌ خطأ: يجب أن يحتوي كل صف على (الاسم التجاري) أو (اسم العميل)';
                } elseif (strpos($errorMsg, 'mobile') !== false) {
                    $message = '❌ خطأ: يجب أن يحتوي كل صف على رقم جوال صحيح';
                } elseif (strpos($errorMsg, 'Integrity constraint') !== false) {
                    $message = '❌ خطأ: بيانات غير صحيحة في الملف. تأكد من ملء الحقول الإجبارية.';
                } else {
                    $message = '❌ خطأ في الاستيراد: '.$errorMsg;
                }

                throw new \Exception($message);
            }
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'importExcel');

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * الحصول على نموذج استيراد Excel
     */
    public function getImportTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // إضافة رؤوس الأعمدة
            $headers = [
                'كود العميل',
                'الاسم التجاري',
                'اسم العميل',
                'اسم العائلة',
                'رقم الهاتف',
                'رقم الجوال',
                'البريد الإلكتروني',
                'التصنيف',
                'الفرع',
                'المنطقة',
                'الحي',
                'العنوان',
                'الرصيد الافتتاحي',
                'حد الائتمان',
                'مدة الائتمان بالأيام',
            ];

            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index).'1', $header);
                // تنسيق رؤوس الأعمدة
                $sheet->getStyle(chr(65 + $index).'1')->getFont()->setBold(true);
                $sheet->getStyle(chr(65 + $index).'1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF2980B9');
                $sheet->getStyle(chr(65 + $index).'1')->getFont()->getColor()->setARGB('FFFFFFFF');
            }

            // تعيين عرض الأعمدة
            for ($i = 0; $i < count($headers); $i++) {
                $sheet->getColumnDimension(chr(65 + $i))->setWidth(18);
            }

            $writer = new Xlsx($spreadsheet);
            $temp_file = tempnam(sys_get_temp_dir(), 'clients_template_');
            $writer->save($temp_file);

            return response()->download($temp_file, 'نموذج_استيراد_العملاء.xlsx');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'getImportTemplate');

            return response()->json([
                'success' => false,
                'message' => '❌ خطأ في إنشاء النموذج: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * عرض صفحة الاستيراد
     */
    public function showImportPage()
    {
        return view('client::import', [
            'branches' => Branch::all(),
            'categories' => CategoriesClient::all(),
        ]);
    }

    public function updateStatusClient(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            // 'statuses' جدولٌ مشترَك مع مراحل التسجيل (section='registration_stage') —
            // exists:statuses,id وحدها كانت تقبل معرّف مرحلة تسجيل كحالة تشغيلية للعميل.
            //
            // و`whereNotNull('kpi_role')` هو `Statuses::scopeAssignable()` نفسه الذي
            // تُبنى منه كلّ منسدلة حالةٍ في النظام (نشط/موقوف): الحالات العامّة
            // القديمة بلا `kpi_role` أثرٌ تاريخيّ لا تعرضه شاشة، فقبولُها هنا
            // قبولُ ما لا طريق إليه إلا بتحرير الطلب يدوياً — ويَخرج بها العميل
            // من عدّادَي «نشط» و«موقوف» كليهما في اللوحة.
            'status_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('statuses', 'id')->whereNull('section')->whereNotNull('kpi_role'),
            ],
            'notes' => 'nullable|string|max:1000',
            // من أين جاء الطلب — مساحة عمل الزيارة ترسله ليعرف المُعتمِد أنّ
            // الطلب ناتجُ معاينةٍ ميدانية، ولتعود الشاشة إلى الزيارة بعد الحفظ.
            'visit_id' => 'nullable|exists:visits,id',
        ]);

        $client = Client::findOrFail($request->client_id);
        $user = auth()->user();

        /* صلاحية المسار (`clients_change_status`) عامّة ولا تعرف أنّ هذا العميل
           بعينه ضمن نطاق المستخدم — نفس حارس `VisitWorkspaceController::open()`.
           بدونه كان أيّ مندوبٍ يحمل الصلاحية يوقِف عميلاً لا يراه أصلاً بتبديل
           `client_id` المخفيّ في النموذج. والفحص قبل `beginTransaction()` عمداً:
           `abort_unless()` داخل `try` أدناه يلتقطه `catch (\Exception)` فيعود
           403 في صورة رسالة «حدث خطأ أثناء تغيير حالة العميل». */
        abort_unless(
            app(ClientAccessService::class)->canAccessClient($user, $client->id),
            403,
            'لا يمكنك تغيير حالة عميل غير مسموح لك بالوصول إليه.'
        );

        DB::beginTransaction();

        try {
            try {
                $hasApprovePermission = hasPermission('clients_approve_status');
            } catch (\Exception $e) {
                $hasApprovePermission = false;
            }

            $oldStatusId = $client->status_id;
            // بيانات قديمة قد تشير إلى صفّ حالةٍ محذوف منذ توحيد حالات
            // العميل — إدراجها كما هي يكسر قيد client_status_requests
            // الأجنبي على old_status_id، فتُطبَّع إلى NULL (العمود يقبله).
            if ($oldStatusId && ! Statuses::whereKey($oldStatusId)->exists()) {
                $oldStatusId = null;
            }

            /* الطلب القادم من مساحة عمل الزيارة يحمل رقم زيارته في نصّ السبب:
               `client_status_requests` لا عمود `visit_id` فيه، وإضافةُ عمودٍ
               لأجل سطرٍ يقرؤه إنسانٌ واحد أثقلُ من فائدته. ما يهمّ المُعتمِد أن
               يعرف أنّ الإيقاف نتيجةُ معاينةٍ ميدانية لا قرارُ مكتب، وأن يجد
               الزيارة نفسها إن أراد تفاصيلها. */
            $visitTag = '';
            if ($request->filled('visit_id')) {
                $requestVisit = Visit::find($request->visit_id);
                // زيارةُ هذا العميل وحده — رقمُ زيارةِ عميلٍ آخر في سبب الإيقاف تضليل
                if ($requestVisit && (int) $requestVisit->client_id === (int) $client->id) {
                    $visitTag = '[من زيارة '.($requestVisit->visit_number ?: '#'.$requestVisit->id)
                        .' بتاريخ '.optional($requestVisit->visit_date)->format('Y-m-d').'] ';
                }
            }
            $statusNotes = trim($visitTag.trim((string) $request->notes)) ?: null;

            /* من يحتاج اعتماداً؟ من لا يملك `clients_approve_status` — وكفى.
               كان الشرط يبدأ بـ`$user->isEmployee()`، وهي حرفياً
               `users.employee_id !== null` لا أكثر: مستخدمٌ بلا ملفّ موظّف
               (حسابٌ أُنشئ يدوياً، أو حساب بوّابة عميل) يملك
               `clients_change_status` ولا يملك الاعتماد كان يمرّ إلى التغيير
               المباشر تحت إعدادٍ اسمُه «يلزم اعتماد المدير» وهو مفعَّل. الصلاحية
               هي السلطة، لا وجود صفّ موظّف. والشرط نفسه الآن في رأس ملفّ العميل
               (`partials/client-info.blade.php`) وفي مساحة عمل الزيارة. */
            if (! $hasApprovePermission && ClientSettingsHelper::clientStatusChangeRequiresApproval()) {

                /* المُعتمِد لم يكن عليه تحقّقٌ أصلاً: أيّ رقمٍ يمرّ في
                   `receiver_id`، فيذهب الإشعار إلى مستخدمٍ لا يملك زرّ الاعتماد
                   (أو إلى معرّفٍ لا وجود له)، ويبقى الطلب `pending` إلى الأبد
                   بلا أن يراه أحد. */
                if (! ClientStatusRequest::isApprover((int) $request->manager_id)) {
                    DB::rollBack();

                    return redirect()->back()->with('error', 'يرجى اختيار مسؤولٍ يملك صلاحية اعتماد حالات العملاء.');
                }

                // Create a request
                $statusReq = ClientStatusRequest::create([
                    'client_id' => $client->id,
                    'requested_by' => $user->id,
                    'old_status_id' => $oldStatusId,
                    'new_status_id' => $request->status_id,
                    'status' => 'pending',
                    'notes' => $statusNotes,
                ]);

                // Log activity
                $oldStatusNameReq = Statuses::find($oldStatusId);
                $newStatusNameReq = Statuses::find($request->status_id);
                OperationLog::record(
                    'status_change_requested',
                    self::LOG_MODULE,
                    $client,
                    'طلب تغيير حالة العميل من «'.($oldStatusNameReq->name ?? 'غير معروف').'» إلى «'.($newStatusNameReq->name ?? 'غير معروف').'» بانتظار الاعتماد'.($statusNotes ? ' — السبب: '.$statusNotes : ''),
                    ['old' => ['status_id' => $oldStatusId], 'new' => ['status_id' => (int) $request->status_id]]
                );

                // Send Notification to Manager
                notifications::create([
                    'user_id' => $user->id,
                    'receiver_id' => $request->manager_id,
                    'client_id' => $client->id,
                    'title' => 'طلب اعتماد حالة عميل',
                    'description' => 'طلب '.($user->name ?? $user->first_name ?? 'موظف').' تغيير حالة العميل '
                        .$client->trade_name.' إلى «'.($newStatusNameReq->name ?? 'حالة جديدة').'»'
                        .($statusNotes ? ' — السبب: '.$statusNotes : ''),
                    'type' => 'client_status_request',
                    'read' => false,
                    'popup_shown' => false,
                    'is_sent' => true,
                ]);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('success', 'تم رفع طلب تغيير الحالة للإدارة بنجاح وتم إشعار المسؤول.');
            }

            // Direct update for admins or authorized personnel
            $client->status_id = $request->status_id;
            $client->save();

            // إنشاء سجل طلب تغيير حالة (معتمد مباشرة) للتتبع في سجل النشاط
            ClientStatusRequest::create([
                'client_id' => $client->id,
                'requested_by' => $user->id,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $request->status_id,
                'status' => 'approved',
                'approved_by' => $user->id,
                'notes' => $statusNotes,
            ]);

            // جلب أسماء الحالات
            $oldStatusName = Statuses::find($oldStatusId);
            $newStatusName = Statuses::find($request->status_id);

            // Log activity with full details
            OperationLog::record(
                'status_changed',
                self::LOG_MODULE,
                $client,
                'تغيير حالة العميل من «'.($oldStatusName->name ?? 'غير معروف').'» إلى «'.($newStatusName->name ?? 'غير معروف').'»'.($statusNotes ? ' — السبب: '.$statusNotes : ''),
                ['old' => ['status_id' => $oldStatusId], 'new' => ['status_id' => (int) $request->status_id]]
            );

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'تم تغيير حالة العميل بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();

            OperationLog::failure($e, self::LOG_MODULE, 'update_status');

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء تغيير حالة العميل: '.$e->getMessage());
        }
    }

    /**
     * اعتماد عميلٍ من مرحلة التسجيل: يفعّله (حالة «نشط» القياسية) ويمسح
     * مرحلة التسجيل بخطوة واحدة. محروسٌ بصلاحية `clients_approve_status`
     * نفسها التي تحرس اعتماد طلبات تغيير الحالة — الفعل نفسه اعتماد.
     *
     * @see \App\Models\Statuses::activeClientStatusIds()
     * @see docs/CRM_Customer_Status_Gap_Analysis_Plan.md القسم 3
     */
    public function approveRegistrationStage(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $client = Client::with('registrationStage')->findOrFail($id);
            $user = auth()->user();

            $oldStatusId = $client->status_id;
            // بيانات قديمة قد تشير إلى صفّ حالةٍ محذوف منذ توحيد حالات
            // العميل — إدراجها كما هي يكسر قيد client_status_requests
            // الأجنبي على old_status_id، فتُطبَّع إلى NULL (العمود يقبله).
            if ($oldStatusId && ! Statuses::whereKey($oldStatusId)->exists()) {
                $oldStatusId = null;
            }
            $newStatusId = Statuses::activeClientStatusIds()[0];
            $oldStageId = $client->registration_stage_id;
            $stageLabel = $client->registrationStage->name ?? 'غير معروفة';

            $client->status_id = $newStatusId;
            $client->registration_stage_id = null;
            $client->save();

            // نفس ما يسجّله كل تغيير حالة مباشر — انظر updateStatusClient() أعلاه
            ClientStatusRequest::create([
                'client_id' => $client->id,
                'requested_by' => $user->id,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
                'status' => 'approved',
                'approved_by' => $user->id,
                'notes' => 'اعتماد من وضع العميل: '.$stageLabel,
            ]);

            OperationLog::record(
                'status_changed',
                self::LOG_MODULE,
                $client,
                'اعتماد '.$client->display_name.' من وضع العميل («'.$stageLabel.'») إلى نشط',
                ['old' => ['status_id' => $oldStatusId, 'registration_stage_id' => $oldStageId], 'new' => ['status_id' => $newStatusId, 'registration_stage_id' => null]]
            );

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'تم اعتماد العميل وتفعيله.');
        } catch (\Exception $e) {
            DB::rollBack();

            OperationLog::failure($e, self::LOG_MODULE, 'approve_registration_stage');

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء اعتماد العميل: '.$e->getMessage());
        }
    }

    public function approveStatusRequest(Request $request, $id)
    {
        $statusRequest = ClientStatusRequest::findOrFail($id);

        DB::beginTransaction();
        try {
            $user = auth()->user();

            // تحديث حالة الطلب
            $statusRequest->status = 'approved';
            $statusRequest->approved_by = $user->id;
            $statusRequest->save();

            // تحديث حالة العميل
            $client = Client::findOrFail($statusRequest->client_id);
            $client->status_id = $statusRequest->new_status_id;
            $client->save();

            // Log activity
            $oldStatusApprove = Statuses::find($statusRequest->old_status_id);
            $newStatusApprove = Statuses::find($statusRequest->new_status_id);
            OperationLog::record(
                'status_change_approved',
                self::LOG_MODULE,
                $client,
                'اعتماد تغيير حالة العميل من «'.($oldStatusApprove->name ?? 'غير معروف').'» إلى «'.($newStatusApprove->name ?? 'غير معروف').'»'.($statusRequest->notes ? ' — السبب: '.$statusRequest->notes : ''),
                [
                    'old' => ['status_id' => $statusRequest->old_status_id],
                    'new' => ['status_id' => $statusRequest->new_status_id],
                ]
            );

            // Send Notification to Employee
            notifications::create([
                'user_id' => $user->id,
                'receiver_id' => $statusRequest->requested_by,
                'client_id' => $client->id,
                'title' => 'تم اعتماد طلب تغيير حالة العميل',
                'description' => 'تمت الموافقة على طلبك لتغيير حالة العميل '.$client->trade_name.' إلى: '.($client->status_client ? $client->status_client->name : 'حالة جديدة'),
                'type' => 'client_status_approved',
                'read' => false,
                'popup_shown' => false,
                'is_sent' => true,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'تم اعتماد طلب تغيير الحالة بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'approve_status_request');

            return redirect()->back()->with('error', 'حدث خطأ أثناء اعتماد الطلب: '.$e->getMessage());
        }
    }

    public function rejectStatusRequest(Request $request, $id)
    {
        $statusRequest = ClientStatusRequest::findOrFail($id);

        if ($statusRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'هذا الطلب تم معالجته مسبقاً.');
        }

        try {
            $user = auth()->user();

            $statusRequest->status = 'rejected';
            $statusRequest->approved_by = $user->id;
            $statusRequest->save();

            // Log activity
            OperationLog::record(
                'status_change_rejected',
                self::LOG_MODULE,
                (int) $statusRequest->client_id,
                'رفض طلب تغيير حالة العميل',
                ['old' => ['requested_status_id' => $statusRequest->new_status_id]]
            );

            // Send Notification to Employee
            notifications::create([
                'user_id' => $user->id,
                'receiver_id' => $statusRequest->requested_by,
                'client_id' => $statusRequest->client_id,
                'title' => 'تم رفض طلب تغيير حالة العميل',
                'description' => 'تم رفض طلبك لتغيير حالة العميل '.($statusRequest->client ? $statusRequest->client->trade_name : ''),
                'type' => 'client_status_rejected',
                'read' => false,
                'popup_shown' => false,
                'is_sent' => true,
            ]);

            return redirect()->back()->with('success', 'تم رفض طلب تغيير الحالة.');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'reject_status_request', (int) $statusRequest->client_id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء رفض طلب تغيير الحالة.');
        }
    }

    public function approveStatusRequestByClient(Request $request, $client_id)
    {
        $statusRequest = ClientStatusRequest::where('client_id', $client_id)
            ->where('status', 'pending')
            ->firstOrFail();

        return $this->approveStatusRequest($request, $statusRequest->id);
    }

    public function rejectStatusRequestByClient(Request $request, $client_id)
    {
        $statusRequest = ClientStatusRequest::where('client_id', $client_id)
            ->where('status', 'pending')
            ->firstOrFail();

        return $this->rejectStatusRequest($request, $statusRequest->id);
    }

    /**
     * قائمة طلبات تغيير حالة العميل عبر كل العملاء — الشاشة التي كان بند
     * «طلبات تغيير حالة معلَّقة» في مركز تنبيهات لوحة العملاء يُشير إليه
     * برقمٍ فقط بلا رابط. الاعتماد والرفض هنا هما نفس رَوطي
     * `clients.status-requests.approve`/`.reject` المستعملين أصلاً في
     * تبويب سجلّ نشاط العميل — لا منطق موازٍ.
     */
    public function statusRequestsIndex(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = ClientStatusRequest::with(['client:id,trade_name,code', 'oldStatus', 'newStatus', 'requester'])
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('client', function ($q) use ($search) {
                $q->where('trade_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $statusRequests = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => ClientStatusRequest::where('status', 'pending')->count(),
            'approved' => ClientStatusRequest::where('status', 'approved')->count(),
            'rejected' => ClientStatusRequest::where('status', 'rejected')->count(),
        ];

        return view('client::status_requests.index', compact('statusRequests', 'stats', 'status'));
    }

    /**
     * طلب تغيير وضع العميل (`registration_stage_id`) — نفس آلية
     * `updateStatusClient()` أعلاه بحرفيّتها، بفارقين فقط: القيمة الهدف
     * حقلٌ مفتوح لا ثنائيّ (`Statuses::section('registration_stage')`)،
     * وتقبل NULL («بلا وضع» هدفٌ صحيح لإخراج عميلٍ من مراحل التسجيل).
     *
     * الاعتماد هنا **دائماً** مطلوبٌ ممن لا يحمل `clients_approve_stage` —
     * بلا مفتاح إعدادٍ يعطّله كما عند الحالة (`clientStatusChangeRequiresApproval`):
     * الطلب صريحٌ «نفس آلية الاعتماد تماماً»، ووضعُ العميل تصنيفٌ تجاريٌّ
     * لا عدّاد لوحةٍ يحتاج منشأةً بلا مُعتمِدين أن تعطّله بالكامل.
     */
    public function updateStageClient(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            // NULL مقصودةٌ («بلا وضع») فالحقل nullable — ما دام مرسَلاً فعليه
            // أن يشير إلى صفّ وضعٍ حقيقي لا إلى حالةٍ تشغيلية من نفس الجدول.
            'stage_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('statuses', 'id')->where(fn ($q) => $q->where('section', 'registration_stage')),
            ],
            'notes' => 'nullable|string|max:1000',
            'visit_id' => 'nullable|exists:visits,id',
        ]);

        $client = Client::findOrFail($request->client_id);
        $user = auth()->user();

        // نفس حارس updateStatusClient() — الصلاحية عامّة ولا تعرف نطاق هذا العميل بعينه
        abort_unless(
            app(ClientAccessService::class)->canAccessClient($user, $client->id),
            403,
            'لا يمكنك تغيير وضع عميل غير مسموح لك بالوصول إليه.'
        );

        DB::beginTransaction();

        try {
            try {
                $hasApprovePermission = hasPermission('clients_approve_stage');
            } catch (\Exception $e) {
                $hasApprovePermission = false;
            }

            $oldStageId = $client->registration_stage_id;
            if ($oldStageId && ! Statuses::whereKey($oldStageId)->exists()) {
                $oldStageId = null;
            }

            $newStageId = $request->filled('stage_id') ? (int) $request->stage_id : null;

            $visitTag = '';
            if ($request->filled('visit_id')) {
                $requestVisit = Visit::find($request->visit_id);
                if ($requestVisit && (int) $requestVisit->client_id === (int) $client->id) {
                    $visitTag = '[من زيارة '.($requestVisit->visit_number ?: '#'.$requestVisit->id)
                        .' بتاريخ '.optional($requestVisit->visit_date)->format('Y-m-d').'] ';
                }
            }
            $stageNotes = trim($visitTag.trim((string) $request->notes)) ?: null;

            if (! $hasApprovePermission) {
                if (! ClientStageRequest::isApprover((int) $request->manager_id)) {
                    DB::rollBack();

                    return redirect()->back()->with('error', 'يرجى اختيار مسؤولٍ يملك صلاحية اعتماد وضع العملاء.');
                }

                $stageReq = ClientStageRequest::create([
                    'client_id' => $client->id,
                    'requested_by' => $user->id,
                    'old_stage_id' => $oldStageId,
                    'new_stage_id' => $newStageId,
                    'status' => 'pending',
                    'notes' => $stageNotes,
                ]);

                $oldStageNameReq = Statuses::find($oldStageId);
                $newStageNameReq = Statuses::find($newStageId);
                OperationLog::record(
                    'stage_change_requested',
                    self::LOG_MODULE,
                    $client,
                    'طلب تغيير وضع العميل من «'.($oldStageNameReq->name ?? 'بلا وضع').'» إلى «'.($newStageNameReq->name ?? 'بلا وضع').'» بانتظار الاعتماد'.($stageNotes ? ' — السبب: '.$stageNotes : ''),
                    ['old' => ['registration_stage_id' => $oldStageId], 'new' => ['registration_stage_id' => $newStageId]]
                );

                notifications::create([
                    'user_id' => $user->id,
                    'receiver_id' => $request->manager_id,
                    'client_id' => $client->id,
                    'title' => 'طلب اعتماد وضع عميل',
                    'description' => 'طلب '.($user->name ?? $user->first_name ?? 'موظف').' تغيير وضع العميل '
                        .$client->trade_name.' إلى «'.($newStageNameReq->name ?? 'بلا وضع').'»'
                        .($stageNotes ? ' — السبب: '.$stageNotes : ''),
                    'type' => 'client_stage_request',
                    'read' => false,
                    'popup_shown' => false,
                    'is_sent' => true,
                ]);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('success', 'تم رفع طلب تغيير الوضع للإدارة بنجاح وتم إشعار المسؤول.');
            }

            $client->registration_stage_id = $newStageId;
            $client->save();

            ClientStageRequest::create([
                'client_id' => $client->id,
                'requested_by' => $user->id,
                'old_stage_id' => $oldStageId,
                'new_stage_id' => $newStageId,
                'status' => 'approved',
                'approved_by' => $user->id,
                'notes' => $stageNotes,
            ]);

            $oldStageName = Statuses::find($oldStageId);
            $newStageName = Statuses::find($newStageId);
            OperationLog::record(
                'stage_changed',
                self::LOG_MODULE,
                $client,
                'تغيير وضع العميل من «'.($oldStageName->name ?? 'بلا وضع').'» إلى «'.($newStageName->name ?? 'بلا وضع').'»'.($stageNotes ? ' — السبب: '.$stageNotes : ''),
                ['old' => ['registration_stage_id' => $oldStageId], 'new' => ['registration_stage_id' => $newStageId]]
            );

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'تم تغيير وضع العميل بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();

            OperationLog::failure($e, self::LOG_MODULE, 'update_stage');

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء تغيير وضع العميل: '.$e->getMessage());
        }
    }

    public function approveStageRequest(Request $request, $id)
    {
        $stageRequest = ClientStageRequest::findOrFail($id);

        DB::beginTransaction();
        try {
            $user = auth()->user();

            $stageRequest->status = 'approved';
            $stageRequest->approved_by = $user->id;
            $stageRequest->save();

            $client = Client::findOrFail($stageRequest->client_id);
            $client->registration_stage_id = $stageRequest->new_stage_id;
            $client->save();

            $oldStageApprove = Statuses::find($stageRequest->old_stage_id);
            $newStageApprove = Statuses::find($stageRequest->new_stage_id);
            OperationLog::record(
                'stage_change_approved',
                self::LOG_MODULE,
                $client,
                'اعتماد تغيير وضع العميل من «'.($oldStageApprove->name ?? 'بلا وضع').'» إلى «'.($newStageApprove->name ?? 'بلا وضع').'»'.($stageRequest->notes ? ' — السبب: '.$stageRequest->notes : ''),
                [
                    'old' => ['registration_stage_id' => $stageRequest->old_stage_id],
                    'new' => ['registration_stage_id' => $stageRequest->new_stage_id],
                ]
            );

            notifications::create([
                'user_id' => $user->id,
                'receiver_id' => $stageRequest->requested_by,
                'client_id' => $client->id,
                'title' => 'تم اعتماد طلب تغيير وضع العميل',
                'description' => 'تمت الموافقة على طلبك لتغيير وضع العميل '.$client->trade_name.' إلى: '.($client->registrationStage ? $client->registrationStage->name : 'بلا وضع'),
                'type' => 'client_stage_approved',
                'read' => false,
                'popup_shown' => false,
                'is_sent' => true,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'تم اعتماد طلب تغيير الوضع بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'approve_stage_request');

            return redirect()->back()->with('error', 'حدث خطأ أثناء اعتماد الطلب: '.$e->getMessage());
        }
    }

    public function rejectStageRequest(Request $request, $id)
    {
        $stageRequest = ClientStageRequest::findOrFail($id);

        if ($stageRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'هذا الطلب تم معالجته مسبقاً.');
        }

        try {
            $user = auth()->user();

            $stageRequest->status = 'rejected';
            $stageRequest->approved_by = $user->id;
            $stageRequest->save();

            OperationLog::record(
                'stage_change_rejected',
                self::LOG_MODULE,
                (int) $stageRequest->client_id,
                'رفض طلب تغيير وضع العميل',
                ['old' => ['requested_stage_id' => $stageRequest->new_stage_id]]
            );

            notifications::create([
                'user_id' => $user->id,
                'receiver_id' => $stageRequest->requested_by,
                'client_id' => $stageRequest->client_id,
                'title' => 'تم رفض طلب تغيير وضع العميل',
                'description' => 'تم رفض طلبك لتغيير وضع العميل '.($stageRequest->client ? $stageRequest->client->trade_name : ''),
                'type' => 'client_stage_rejected',
                'read' => false,
                'popup_shown' => false,
                'is_sent' => true,
            ]);

            return redirect()->back()->with('success', 'تم رفض طلب تغيير الوضع.');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'reject_stage_request', (int) $stageRequest->client_id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء رفض طلب تغيير الوضع.');
        }
    }

    public function approveStageRequestByClient(Request $request, $client_id)
    {
        $stageRequest = ClientStageRequest::where('client_id', $client_id)
            ->where('status', 'pending')
            ->firstOrFail();

        return $this->approveStageRequest($request, $stageRequest->id);
    }

    public function rejectStageRequestByClient(Request $request, $client_id)
    {
        $stageRequest = ClientStageRequest::where('client_id', $client_id)
            ->where('status', 'pending')
            ->firstOrFail();

        return $this->rejectStageRequest($request, $stageRequest->id);
    }

    /**
     * تحديث جماعي — لحالة العميل التشغيلية (status_id) ولوضعه
     * (registration_stage_id)، أحدهما أو كلاهما في نفس الطلب: سيناريو
     * «كل من حالته ضريبي ← وضعه ضريبي وحالته نشط» عمليةٌ واحدة لا اثنتان.
     * كل قيمة تُتحقَّق من القسم (`section`) الذي يخصّها في جدول `statuses`
     * المشترك — كي لا يصير status_id يشير إلى صفّ وضعٍ أو العكس.
     *
     * الشكل القديم (`type=registration_stage` مع `status_id`) ما زال مقبولاً:
     * يُترجَم هنا إلى `registration_stage_id`.
     */
    public function bulkUpdateStatus(Request $request)
    {
        if ($request->input('type') === 'registration_stage' && ! $request->filled('registration_stage_id')) {
            $request->merge([
                'registration_stage_id' => $request->input('status_id'),
                'status_id' => null,
            ]);
        }

        $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*' => 'exists:clients,id',
            'status_id' => [
                'nullable',
                'required_without_all:registration_stage_id,registration_stage_from_status_id',
                \Illuminate\Validation\Rule::exists('statuses', 'id')->whereNull('section'),
            ],
            'registration_stage_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('statuses', 'id')->where(fn ($q) => $q->where('section', 'registration_stage')),
            ],
            // «الوضع الجديد» = اسم حالةٍ قديمة (ضريبي…): يُستعمل الوضع الذي
            // يحمل الاسم نفسه، ويُنشأ إن لم يكن موجوداً — لا خطوة إضافة يدوية.
            'registration_stage_from_status_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('statuses', 'id')->whereNull('section'),
            ],
        ]);

        $changes = array_filter([
            'registration_stage_id' => $request->input('registration_stage_id'),
            'status_id' => $request->input('status_id'),
        ]);

        DB::beginTransaction();

        try {
            $createdStage = null;
            if (! isset($changes['registration_stage_id']) && $request->filled('registration_stage_from_status_id')) {
                $source = Statuses::findOrFail($request->input('registration_stage_from_status_id'));
                $stage = Statuses::section('registration_stage')->where('name', $source->name)->first();

                if (! $stage) {
                    $stage = $createdStage = Statuses::create([
                        'section' => 'registration_stage',
                        'name' => $source->name,
                        'color' => $source->color,
                        'is_deletable' => true,
                    ]);

                    OperationLog::record(
                        'status_created',
                        self::LOG_MODULE,
                        $stage,
                        'إضافة وضع «'.$stage->name.'» تلقائياً من الحالة القديمة بنفس الاسم (تحديث جماعي)',
                        ['new' => $stage->only(['name', 'color'])]
                    );
                }

                $changes = ['registration_stage_id' => $stage->id] + $changes;
            }

            $count = Client::whereIn('id', $request->client_ids)->update($changes);

            DB::commit();

            $names = Statuses::whereIn('id', array_values($changes))->pluck('name', 'id');
            $parts = [];
            if (isset($changes['registration_stage_id'])) {
                $parts[] = 'الوضع ← «'.($names[$changes['registration_stage_id']] ?? '#'.$changes['registration_stage_id']).'»';
            }
            if (isset($changes['status_id'])) {
                $parts[] = 'الحالة ← «'.($names[$changes['status_id']] ?? '#'.$changes['status_id']).'»';
            }

            OperationLog::record(
                isset($changes['status_id']) ? 'bulk_status_updated' : 'bulk_registration_stage_updated',
                self::LOG_MODULE,
                null,
                'تحديث جماعي لـ'.count($request->client_ids).' عميل: '.implode('، ', $parts),
                ['new' => $changes + ['client_ids' => array_map('intval', $request->client_ids)]]
            );

            $message = match (true) {
                count($changes) > 1 => 'تم تحديث '.$count.' عميل: '.implode('، ', $parts).'.',
                isset($changes['registration_stage_id']) => 'تم تغيير وضع العملاء بنجاح.',
                default => 'تم تغيير حالة العملاء بنجاح.',
            };

            if ($createdStage) {
                $message .= ' (أُنشئ الوضع «'.$createdStage->name.'» لأنه لم يكن موجوداً.)';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'created_stage' => $createdStage?->only(['id', 'name']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            OperationLog::failure($e, self::LOG_MODULE, 'bulk_update_status');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحديث الجماعي: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * صفحة التحديث الجماعي — حالة العميل أو مرحلة تسجيله
     */
    /**
     * كلُّ عميلٍ بلا وضع يأخذ وضعاً باسم حالته المعتمدة (نشط ← «نشط»، موقوف ← «موقوف»).
     *
     * الوضعُ يُنشأ بالاسم واللون نفسيهما إن لم يوجد — كما يفعل التحديثُ الجماعي
     * مع الحالات القديمة. ولا يُمسّ من له وضعٌ مضبوط.
     */
    public function fillStageFromStatus()
    {
        $summary = [];

        try {
            DB::transaction(function () use (&$summary) {
                foreach (Statuses::assignable()->ordered()->get() as $status) {
                    $query = Client::whereNull('registration_stage_id')->where('status_id', $status->id);
                    if (! (clone $query)->exists()) {
                        continue;
                    }

                    $stage = Statuses::section('registration_stage')->where('name', $status->name)->first();
                    if (! $stage) {
                        $stage = Statuses::create([
                            'section' => 'registration_stage',
                            'name' => $status->name,
                            'color' => $status->color,
                            'is_deletable' => true,
                        ]);
                        OperationLog::record('status_created', self::LOG_MODULE, $stage,
                            'إضافة وضع «'.$stage->name.'» من الحالة بنفس الاسم (ضبط الوضع حسب الحالة)',
                            ['new' => $stage->only(['name', 'color'])]);
                    }

                    $n = $query->update(['registration_stage_id' => $stage->id]);
                    $summary[] = $n.' «'.$status->name.'»';
                }
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'fillStageFromStatus');

            return back()->with('error', 'تعذّر ضبط الوضع — راجع سجلّ الأخطاء.');
        }

        if ($summary === []) {
            return back()->with('success', 'لا يوجد عميل «نشط» أو «موقوف» بلا وضع.');
        }

        OperationLog::record('bulk_registration_stage_updated', self::LOG_MODULE, null,
            'ضبط وضع العملاء بلا وضع حسب حالتهم: '.implode('، ', $summary));

        return back()->with('success', 'تم ضبط الوضع حسب الحالة: '.implode('، ', $summary).'.');
    }

    public function bulkStatusPage()
    {
        // القيمة الجديدة للحالة: نشط/موقوف وحدهما (القرار في خطة حالات العملاء).
        $statuses = Statuses::assignable()->select('id', 'name', 'color', 'kpi_role')->get();
        // أمّا التصفية فتحتاج كل الحالات — القديمة منها (ضريبي، بلدية، …)
        // هي بالضبط ما يُجمَع هنا لنقله إلى «الوضع» وإرجاع حالته إلى نشط.
        $filterStatuses = Statuses::whereNull('section')->withCount('clients')->ordered()
            ->get(['id', 'name', 'color', 'kpi_role']);
        $registrationStages = Statuses::section('registration_stage')->withCount('registrationStageClients')->ordered()
            ->get(['id', 'name', 'color']);
        $activeStatusId = $statuses->firstWhere('kpi_role', 'active')?->id;
        $employees = Employee::select('id', 'first_name', 'middle_name', 'nickname')->orderBy('first_name')->get();
        // «تابع لـ» بالمُنتقي الموحّد (شجرةٌ مسمّاةٌ بنوع كلّ جهة) — مقصوصاً بنطاق المستخدم
        $treeNodes = \App\Support\DataScope::filterNodes(\App\Support\AffiliationTree::nodes());

        // أرقامُ الترويسة وبطاقة «ضبط الوضع حسب الحالة»
        $noStageByStatus = Client::whereNull('registration_stage_id')
            ->whereIn('status_id', $statuses->pluck('id'))
            ->selectRaw('status_id, COUNT(*) as c')->groupBy('status_id')->pluck('c', 'status_id');
        $counts = [
            'no_stage' => Client::whereNull('registration_stage_id')->count(),
            'no_status' => Client::whereNull('status_id')->count(),
            'legacy' => Client::whereIn('status_id', $filterStatuses->whereNull('kpi_role')->pluck('id'))->count(),
        ];

        return view('client::bulk_status', compact(
            'statuses', 'filterStatuses', 'registrationStages', 'activeStatusId', 'employees', 'treeNodes', 'noStageByStatus', 'counts'
        ));
    }

    /**
     * جلب العملاء المفلترين (AJAX) — فلترا الحالة ومرحلة التسجيل مستقلّان
     * ويمكن الجمع بينهما، والفرع يشمل تلقائياً كل الفروع الفرعية التابعة له.
     */
    public function getFilteredClients(Request $request)
    {
        $query = Client::with(['status_client', 'registrationStage'])
            ->select('id', 'trade_name', 'code', 'status_id', 'registration_stage_id', 'branch_id');

        // `none` = بلا حالة / بلا وضع — لتجميع من لم يُضبط بعد
        if ($request->filled('status_id')) {
            $request->status_id === 'none'
                ? $query->whereNull('status_id')
                : $query->where('status_id', $request->status_id);
        }

        if ($request->filled('registration_stage_id')) {
            $request->registration_stage_id === 'none'
                ? $query->whereNull('registration_stage_id')
                : $query->where('registration_stage_id', $request->registration_stage_id);
        }

        if ($request->filled('branch_id')) {
            $query->inTreeNode((int) $request->branch_id, static::wantsDescendants($request));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $clients = $query->get();

        $results = $clients->map(function ($client) {
            return [
                'id' => $client->id,
                'trade_name' => $client->trade_name,
                'code' => $client->code,
                'status_name' => $client->status_client ? $client->status_client->name : '—',
                'status_color' => $client->status_client ? $client->status_client->color : '#999',
                'stage_name' => $client->registrationStage ? $client->registrationStage->name : '—',
                'stage_color' => $client->registrationStage ? $client->registrationStage->color : '#999',
            ];
        });

        return response()->json(['success' => true, 'clients' => $results]);
    }

    /**
     * البحث عن عميل بالكود (AJAX)
     */
    public function searchByCode(Request $request)
    {
        $code = trim($request->get('code', ''));

        if (empty($code)) {
            return response()->json(['success' => false, 'message' => 'الرجاء إدخال كود العميل.']);
        }

        $client = Client::with('status_client')
            ->where('code', $code)
            ->first(['id', 'trade_name', 'code', 'status_id']);

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'لم يتم العثور على عميل بهذا الكود.']);
        }

        return response()->json([
            'success' => true,
            'client' => [
                'id' => $client->id,
                'trade_name' => $client->trade_name,
                'code' => $client->code,
                'status_name' => $client->status_client ? $client->status_client->name : '—',
                'status_color' => $client->status_client ? $client->status_client->color : '#999',
            ],
        ]);
    }

    /**
     * بحث العملاء لـ Select2 (بالكود أو الاسم)
     */
    public function searchClientsForSelect(Request $request)
    {
        $term = trim($request->get('term', ''));

        $query = Client::with(['status_client', 'registrationStage'])
            ->select('id', 'trade_name', 'code', 'status_id', 'registration_stage_id');

        if (! empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', '%'.$term.'%')
                    ->orWhere('trade_name', 'like', '%'.$term.'%');
            });
        }

        $clients = $query->limit(15)->get();

        $results = $clients->map(function ($client) {
            return [
                'id' => $client->id,
                'text' => $client->trade_name.' ('.$client->code.')',
                'trade_name' => $client->trade_name,
                'code' => $client->code,
                'status_name' => $client->status_client ? $client->status_client->name : '—',
                'status_color' => $client->status_client ? $client->status_client->color : '#999',
                'stage_name' => $client->registrationStage ? $client->registrationStage->name : '—',
                'stage_color' => $client->registrationStage ? $client->registrationStage->color : '#999',
            ];
        });

        return response()->json(['results' => $results]);
    }

    public function statement($id)
    {
        $client = Client::find($id);
        $account = Account::ledger()->where('client_id', $id)->first();

        if (! $account) {
            return redirect()->back()->with('error', 'لا يوجد حساب مرتبط بهذا العميل.');
        }

        $accountId = $account->id;

        // جلب بيانات الخزينة
        $treasury = $this->getTreasury($accountId);
        $branches = $this->getBranches();

        // جلب العمليات المالية
        $transactions = $this->getTransactions($accountId);
        $transfers = $this->getTransfers($accountId);
        $expenses = $this->getExpenses($accountId);
        $revenues = $this->getRevenues($accountId);

        // معالجة العمليات
        $allOperations = [];

        // معالجة المعاملات (Transactions)
        foreach ($transactions as $transaction) {
            $amount = $transaction->debit > 0 ? $transaction->debit : $transaction->credit;
            $isDebit = $transaction->debit > 0;

            // جلب نوع العملية من ReferenceType
            $journalEntry = $transaction->journalEntry;
            $referenceType = $journalEntry->reference_type ?? null;

            // تحويل Enum إلى string value إذا كان ضرورياً
            if ($referenceType && is_object($referenceType)) {
                $referenceType = $referenceType->value ?? (string) $referenceType;
            }
            $referenceType = (string) $referenceType; // تحويل آمن لنص

            // الحصول على تفاصيل العملية (الاسم العربي والأيقونة)
            $operationDetails = $this->getOperationTypeDetails($referenceType);
            $operationType = $operationDetails['name'];

            // بناء الرفرنس (الرقم والنوع)
            $referenceDisplay = '';
            if ($journalEntry->reference_number) {
                $referenceDisplay = $journalEntry->reference_number;
            } elseif ($journalEntry->invoice) {
                $referenceDisplay = $journalEntry->invoice->code ?? $journalEntry->invoice->id;
            }

            // معالجة خاصة لقيود الديون المعدومة
            // إذا كان الوصف يحتوي على "ديون معدومة"، يجب أن تكون عملية تقليل (withdraw)
            $description = $journalEntry->description ?? $transaction->description ?? '';
            $isWriteOffDebt = strpos(strtolower($description), 'ديون معدومة') !== false;

            // للديون المعدومة، نعتبرها كـ withdraw (تقليل) بغض النظر عن debit/credit
            if ($isWriteOffDebt) {
                $isDebit = false; // اجعلها withdraw بدلاً من deposit
            }

            $operation = [
                'date' => $journalEntry->date ?? $transaction->created_at,
                'type' => $operationType,
                'reference_type' => $referenceType,
                'description' => $description,
                'reference' => $referenceDisplay,
                'amount' => $amount,
                'is_deposit' => $isDebit,
                'deposit' => $isDebit ? $amount : 0,
                'withdraw' => ! $isDebit ? $amount : 0,
                'balance' => 0,
            ];

            $allOperations[] = $operation;
        }    // معالجة المصروفات (Expenses)
        foreach ($expenses as $expense) {
            $operation = [
                'date' => $expense->date ?? $expense->created_at,
                'type' => 'مصروفات',
                'reference_type' => 'EXP',
                'description' => $expense->description ?? '',
                'reference' => $expense->voucher_number ? $expense->voucher_number : '',
                'amount' => $expense->amount,
                'is_deposit' => false,
                'deposit' => 0,
                'withdraw' => $expense->amount,
                'balance' => 0,
            ];

            $allOperations[] = $operation;
        }

        // معالجة الإيرادات (Revenues)
        foreach ($revenues as $revenue) {
            $operation = [
                'date' => $revenue->date ?? $revenue->created_at,
                'type' => 'إيرادات',
                'reference_type' => 'REV',
                'description' => $revenue->description ?? '',
                'reference' => $revenue->voucher_number ? $revenue->voucher_number : '',
                'amount' => $revenue->amount,
                'is_deposit' => false,
                'deposit' => 0,
                'withdraw' => $revenue->amount,
                'balance' => 0,
            ];

            $allOperations[] = $operation;
        }

        // تنظيف البيانات والتأكد من وجود التاريخ
        $allOperations = array_filter($allOperations, function ($operation) {
            return isset($operation['date']) && ! empty($operation['date']);
        });

        // ترتيب العمليات حسب التاريخ والوقت (من الأقدم للأحدث)
        usort($allOperations, function ($a, $b) {
            $dateA = is_string($a['date']) ? strtotime($a['date']) : $a['date']->timestamp;
            $dateB = is_string($b['date']) ? strtotime($b['date']) : $b['date']->timestamp;

            return $dateA - $dateB; // ترتيب تصاعدي (من الأقدم للأحدث)
        });

        // حساب الرصيد بعد كل عملية
        // نبدأ من صفر لأن العمليات تشمل القيد الافتتاحي (OPB)
        $runningBalance = 0;

        foreach ($allOperations as $key => &$operation) {
            if ($operation['is_deposit']) {
                // إذا كانت إيداع (فاتورة مبيعات أو رصيد افتتاحي)
                $runningBalance += $operation['amount'];
            } else {
                // إذا كانت سحب (تحصيل)
                $runningBalance -= $operation['amount'];
            }

            // تحديث الرصيد بعد العملية
            $operation['balance'] = $runningBalance;
        }
        unset($operation);

        // تقسيم العمليات إلى صفحات
        $perPage = 50;
        $currentPage = request()->get('page', 1);
        $operationsPaginator = new LengthAwarePaginator(
            array_slice($allOperations, ($currentPage - 1) * $perPage, $perPage),
            count($allOperations),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        // حساب الإجماليات
        $totalDeposits = array_reduce($allOperations, function ($carry, $item) {
            return $carry + $item['deposit'];
        }, 0);

        $totalWithdraws = array_reduce($allOperations, function ($carry, $item) {
            return $carry + $item['withdraw'];
        }, 0);

        // إرسال البيانات إلى الواجهة
        return view('client::statement', [
            'treasury' => $treasury,
            'account' => $account,
            'operationsPaginator' => $operationsPaginator,
            'branches' => $branches,
            'client' => $client,
            'totalDeposits' => $totalDeposits,
            'totalWithdraws' => $totalWithdraws,
            // قوالب كشف الحساب المصمَّمة في «قوالب المستندات» — الطباعة كانت
            // تنسخ محتوى الصفحة إلى نافذةٍ بأنماطٍ مكتوبةٍ في JavaScript، لا
            // يعرفها محرّر القوالب ولا تقرأ بيانات المنشأة من إعداداتها
            'documentTemplates' => PrintSettings::templatesFor(self::DOCUMENT_TYPE),
            'defaultTemplate' => PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE),
            'prefersPdf' => PrintSettings::prefersPdf(),
        ]);
    }

    /**
     * معاينة كشف حساب العميل بقالبٍ من «قوالب المستندات».
     */
    public function statementPrint(Request $request, $id, $template = 'default')
    {
        $client = Client::findOrFail($id);

        $html = $this->renderStatementTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderStatementTemplate($id, $fallback->id) : '';
        }

        return response(DocumentPrintPage::wrap($html));
    }

    /** تنزيل كشف حساب العميل PDF بالقالب المختار، وإلا الافتراضي للنوع */
    public function statementPdf(Request $request, $id, $template = null)
    {
        $client = Client::findOrFail($id);

        $html = $this->renderStatementTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderStatementTemplate($id, $fallback->id) : null;
        }

        if ($html === null) {
            return redirect()->back()->with('error', __('purchase_cycle.messages.no_template'));
        }

        return Pdf::loadHTML($html)
            ->download(($client->code ?: $client->id).'-statement.pdf');
    }

    /**
     * يصيّر قالب كشف حساب من الإعدادات — أو `null` إن لم يكن `$template`
     * معرّفَ قالبٍ صالح لهذا النوع.
     */
    private function renderStatementTemplate($clientId, $template): ?string
    {
        if (! is_numeric($template)) {
            return null;
        }

        $model = DocumentTemplate::where('id', (int) $template)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->first();

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)
                ->getDocumentData(self::DOCUMENT_TYPE, $clientId);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'client', 'render_template', $clientId);

            return null;
        }
    }

    private function getTreasury($id)
    {
        return Account::findOrFail($id);
    }

    private function getBranches()
    {
        return Branch::all();
    }

    /**
     * تحويل رموز ReferenceType إلى نصوص عربية مع تحديد اتجاه التأثير
     */
    private function getOperationTypeDetails($referenceType)
    {
        // التأكد من أن referenceType هي نص وليس null أو Enum
        if (! is_string($referenceType)) {
            // إذا كانت Enum، استخرج القيمة
            if (is_object($referenceType)) {
                if (property_exists($referenceType, 'value')) {
                    $referenceType = $referenceType->value;
                } else {
                    $referenceType = (string) $referenceType;
                }
            } else {
                $referenceType = (string) $referenceType;
            }
        }

        $typeMapping = [
            'OPB' => ['name' => 'رصيد افتتاحي', 'icon' => '📊'],
            'JV' => ['name' => 'قيد يومية عام', 'icon' => '📝'],
            'PV' => ['name' => 'سند صرف', 'icon' => '💸'],
            'RV' => ['name' => 'سند قبض', 'icon' => '💰'],
            'PI' => ['name' => 'فاتورة مشتريات', 'icon' => '📥'],
            'PR' => ['name' => 'مردودات مشتريات', 'icon' => '↩️'],
            'SI' => ['name' => 'فاتورة مبيعات', 'icon' => '📤'],
            'SR' => ['name' => 'مردودات مبيعات', 'icon' => '↪️'],
            'DN' => ['name' => 'إشعار مدين', 'icon' => '📋'],
            'CN' => ['name' => 'إشعار دائن', 'icon' => '📃'],
            'AW' => ['name' => 'مخزن معتمد', 'icon' => '📦'],
            'EXP' => ['name' => 'مصروفات', 'icon' => '💳'],
            'ADV' => ['name' => 'سلفة/عهدة', 'icon' => '🏦'],
            'STK' => ['name' => 'تسوية مخزون', 'icon' => '📊'],
            'TRF' => ['name' => 'تحويل داخلي', 'icon' => '🔄'],
            'PAY' => ['name' => 'مدفوعات موردين', 'icon' => '💳'],
            'REC' => ['name' => 'تحصيلات عملاء', 'icon' => '💵'],
            'SAL' => ['name' => 'مسير رواتب', 'icon' => '💼'],
            'DEP' => ['name' => 'إهلاك أصول', 'icon' => '📉'],
            'ACC' => ['name' => 'تسوية قيود محاسبية', 'icon' => '⚙️'],
            'PCAN' => ['name' => 'إلغاء دفعة', 'icon' => '❌'],
        ];

        return $typeMapping[$referenceType] ?? ['name' => $referenceType ?: 'عملية عامة', 'icon' => '❓'];
    }

    private function getTransactions($id)
    {
        return JournalEntryDetail::where('account_id', $id)
            ->with([
                'journalEntry' => function ($query) {
                    $query->with('invoice', 'client');
                },
            ])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getTransfers($id)
    {
        return JournalEntry::whereHas('details', function ($query) use ($id) {
            $query->where('account_id', $id);
        })
            ->with(['details.account'])
            ->where('description', 'تحويل المالية')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getExpenses($id)
    {
        return Expense::where('treasury_id', $id)
            ->with(['expenses_category', 'vendor', 'employee', 'branch', 'client'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getRevenues($id)
    {
        return Revenue::where('treasury_id', $id)
            ->with(['account', 'paymentVoucher', 'treasury', 'bankAccount', 'journalEntry'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function processOperations($transactions, $transfers, $expenses, $revenues, $treasury)
    {
        $allOperations = [];

        // معالجة المعاملات (Transactions)
        foreach ($transactions as $transaction) {
            $amount = $transaction->debit > 0 ? $transaction->debit : $transaction->credit;
            $type = $transaction->debit > 0 ? 'إيداع' : 'سحب';

            $allOperations[] = [
                'operation' => $transaction->description ?? ($type === 'إيداع' ? 'فاتورة مبيعات' : 'تحصيل'),
                'deposit' => $type === 'إيداع' ? $amount : 0,
                'withdraw' => $type === 'سحب' ? $amount : 0,
                'balance_after' => 0, // سيتم حسابه لاحقاً
                'journalEntry' => $transaction->journalEntry->id ?? null,
                'date' => $transaction->journalEntry->date ?? $transaction->created_at,
                'invoice' => $transaction->journalEntry->invoice ?? null,
                'client' => $transaction->journalEntry->client ?? null,
                'type' => 'transaction',
            ];
        }

        // معالجة المصروفات (Expenses)
        foreach ($expenses as $expense) {
            $allOperations[] = [
                'operation' => 'سند صرف: '.($expense->description ?? ''),
                'deposit' => 0,
                'withdraw' => $expense->amount,
                'balance_after' => 0, // سيتم حسابه لاحقاً
                'date' => $expense->date ?? $expense->created_at,
                'invoice' => null,
                'client' => $expense->client,
                'type' => 'expense',
            ];
        }

        // معالجة الإيرادات (Revenues)
        foreach ($revenues as $revenue) {
            $allOperations[] = [
                'operation' => 'سند قبض: '.($revenue->description ?? ''),
                'deposit' => 0,
                'withdraw' => $revenue->amount, // نعتبر الإيرادات كسحب (مبلغ سالب)
                'balance_after' => 0, // سيتم حسابه لاحقاً
                'date' => $revenue->date ?? $revenue->created_at,
                'invoice' => null,
                'client' => null,
                'type' => 'revenue',
            ];
        }

        return $allOperations;
    }

    private function updateBalance($currentBalance, $amount, $type)
    {
        return $type === 'إيداع' ? $currentBalance + $amount : $currentBalance - $amount;
    }

    private function paginateOperations($allOperations)
    {
        $perPage = 15;
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedOperations = array_slice($allOperations, $offset, $perPage);

        return new LengthAwarePaginator($paginatedOperations, count($allOperations), $perPage, $currentPage, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    // addnotes() و quickVisitNote() نُقلتا إلى ClientNoteController — بلا
    // تغيير في المنطق أو أسماء الـ routes. VisitWorkspaceController::storeNote()
    // يستدعي addnotes() الآن عبر app(ClientNoteController::class)->addnotes(...).

    /**
     * كتابة الديون المعدومة - تسوية الديون بعدم السداد
     * Write off bad debts - Zero out client account with journal entry
     */
    public function writeOffBadDebt(Request $request, $clientId)
    {
        // عملةُ النظام لا `'SAR'` مكتوبةً في كل طرفٍ من أطراف القيد
        $currency = $this->defaultCurrency();

        // الفترة تُفحَص قبل أي كتابة: قيدٌ مرحَّل إلى سنةٍ أُقفلت لا يُسحَب
        if ($block = $this->accountingPeriodBlock(now())) {
            return back()->with('error', $block);
        }

        try {
            DB::beginTransaction();

            // Validate input
            $validated = $request->validate([
                'write_off_type' => 'required|in:full,partial,negative_to_zero',
                'amount' => 'nullable|numeric|min:0.01',
                'reason' => 'required|string|max:500',
                'notes' => 'nullable|string|max:1000',
            ], [
                'write_off_type.required' => 'يجب اختيار نوع التسوية',
                'amount.numeric' => 'المبلغ يجب أن يكون رقماً صحيحاً',
                'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            ]);

            // Fetch client
            $client = Client::findOrFail($clientId);

            // Fetch client account
            $clientAccount = Account::ledger()->where('client_id', $clientId)->first();
            if (! $clientAccount) {
                return back()->with('error', 'لا يوجد حساب لهذا العميل.');
            }

            // Get current balance
            $currentBalance = floatval($clientAccount->balance ?? 0);

            // تحديد المبلغ والوصف بناءً على نوع التسوية
            $writeOffType = $validated['write_off_type'];
            $amountToWriteOff = 0;
            $typeDescription = '';
            $isNegativeBalance = $currentBalance < 0;

            if ($writeOffType === 'full') {
                // تسوية كاملة
                if ($currentBalance == 0) {
                    return back()->with('error', 'رصيد العميل صفر، لا توجد حاجة للتسوية.');
                }
                $amountToWriteOff = abs($currentBalance);
                $typeDescription = 'تسوية كاملة للمديونية';

            } elseif ($writeOffType === 'partial') {
                // تسوية جزئية بمبلغ محدد
                if (! isset($validated['amount'])) {
                    return back()->with('error', 'يجب إدخال المبلغ للتسوية الجزئية.');
                }
                $amount = floatval($validated['amount']);
                if ($amount > abs($currentBalance)) {
                    return back()->with('error', 'المبلغ المدخل أكبر من رصيد العميل.');
                }
                if ($amount <= 0) {
                    return back()->with('error', 'المبلغ يجب أن يكون موجباً.');
                }
                $amountToWriteOff = $amount;
                $typeDescription = 'تسوية جزئية بمبلغ '.number_format($amount, 2);

            } elseif ($writeOffType === 'negative_to_zero') {
                // تحويل الرصيد السالب إلى صفر
                if ($currentBalance >= 0) {
                    return back()->with('error', 'الرصيد الحالي ليس سالباً، لا توجد حاجة لتحويله إلى صفر.');
                }
                $amountToWriteOff = abs($currentBalance);
                $typeDescription = 'تحويل الرصيد السالب إلى صفر';
            }

            // مبالغ تتجاوز الحد المسموح تحتاج صلاحية اعتماد إضافية — لا تُشطب
            // بضغطة واحدة من أي مستخدم يملك clients_manage_debts فقط
            $writeoffThreshold = ClientSettingsHelper::badDebtWriteoffApprovalThreshold();
            if ($writeoffThreshold !== null && $amountToWriteOff > $writeoffThreshold && ! hasPermission('clients_approve_large_debt_writeoff')) {
                DB::rollBack();

                return back()->with('error', 'هذا المبلغ ('.number_format($amountToWriteOff, 2).') يتجاوز الحد المسموح به لشطب الديون ('.number_format($writeoffThreshold, 2).') ويحتاج اعتماد مسؤول.');
            }

            // Get bad debt account
            $badDebtAccount = $this->settingsAccount('bad_debts');

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'reference_number' => 'WO-'.$client->id.'-'.time(),
                'date' => now()->toDateString(),
                'description' => $typeDescription.' - '.$client->trade_name,
                'status' => 1,
                'currency' => $currency,
                'client_id' => $clientId,
                'reference_type' => ReferenceType::JOURNAL_VOUCHER,
                'created_by_employee' => auth()->id(),
                'approved_by_employee' => auth()->id(),
                'is_posted' => 1,
                'branch_id' => auth()->user()->branch_id,
            ]);

            // القيد المحاسبي الصحيح:
            // إذا كان الرصيد موجب (مدين): العميل عليه مبلغ
            // إذا كان الرصيد سالب (دائن): العميل له مبلغ

            if ($isNegativeBalance) {
                // رصيد سالب (دائن) - العميل له رصيد عندنا
                // 1) حساب العميل (مدين) - نقلل رصيده الدائن
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $clientAccount->id,
                    'description' => 'إقفال رصيد دائن - '.$client->trade_name.' ('.$typeDescription.')',
                    'debit' => $amountToWriteOff,
                    'credit' => 0,
                ]);

                // 2) حساب الديون المعدومة (دائن) - نسجل الفرق
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $badDebtAccount->id,
                    'description' => 'رصيد دائن معدوم - '.$client->trade_name,
                    'debit' => 0,
                    'credit' => $amountToWriteOff,
                ]);
            } else {
                // رصيد موجب (مدين) - العميل عليه مبلغ
                // 1) حساب الديون المعدومة (مدين)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $badDebtAccount->id,
                    'description' => 'مصروف ديون معدومة - '.$client->trade_name,
                    'debit' => $amountToWriteOff,
                    'credit' => 0,
                ]);

                // 2) حساب العميل (دائن) - نقلل مديونيته
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $clientAccount->id,
                    'description' => 'إقفال مديونية العميل - '.$client->trade_name.' ('.$typeDescription.')',
                    'debit' => 0,
                    'credit' => $amountToWriteOff,
                ]);
            }

            // تحديث رصيد العميل
            if ($writeOffType === 'full' || $writeOffType === 'negative_to_zero') {
                // تصفير الحساب تماماً
                $clientAccount->balance = 0;
            } else {
                // تقليل الرصيد بالمبلغ المحدد
                $clientAccount->balance = $currentBalance - $amountToWriteOff;
            }
            $clientAccount->save();

            // Log action
            OperationLog::record(
                'debt_written_off',
                self::LOG_MODULE,
                (int) $clientId,
                $typeDescription.' للعميل «'.$client->trade_name.'» بمبلغ '.number_format($amountToWriteOff, 2).' — السبب: '.$validated['reason'],
                ['new' => ['amount' => $amountToWriteOff, 'currency' => $currency, 'reason' => $validated['reason']]]
            );

            DB::commit();

            $message = '';
            if ($writeOffType === 'full') {
                $message = 'تم تسوية المديونية بالكامل بنجاح وتصفير الحساب. رقم القيد: '.$journalEntry->id;
            } elseif ($writeOffType === 'partial') {
                $message = 'تم تسوية مبلغ '.number_format($amountToWriteOff, 2).' من المديونية بنجاح. رقم القيد: '.$journalEntry->id;
            } elseif ($writeOffType === 'negative_to_zero') {
                $message = 'تم تحويل الرصيد السالب إلى صفر بنجاح. رقم القيد: '.$journalEntry->id;
            }

            return redirect()->route('clients.show', $clientId)
                ->with('success', $message);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'writeOffBadDebt');

            DB::rollBack();

            return back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * إضافة مديونية مشكوك فيها بشكل سريع (بدون حقول)
     */
    public function addDoubtfulDebtQuick($clientId)
    {
        $currency = $this->defaultCurrency();

        if ($block = $this->accountingPeriodBlock(now())) {
            return back()->with('error', $block);
        }

        try {
            DB::beginTransaction();

            // جلب العميل
            $client = Client::findOrFail($clientId);

            // جلب حساب العميل
            $clientAccount = Account::ledger()->where('client_id', $clientId)->first();
            if (! $clientAccount) {
                return redirect()->back()->with('error', '❌ لا يوجد حساب للعميل');
            }

            // حساب الرصيد الحالي
            $currentBalance = abs(floatval($clientAccount->balance ?? 0));

            if ($currentBalance == 0) {
                return redirect()->back()->with('error', '❌ الحساب مُسوّى - لا توجد مديونية');
            }

            // الحصول على حساب الديون المشكوك فيها من الثابت
            $doubtfulDebtAccount = $this->settingsAccount('allowance_for_doubtful_debts');

            if (! $doubtfulDebtAccount) {
                return redirect()->back()->with('error', '❌ لا يوجد حساب محدد للديون المشكوك فيها');
            }

            // حساب مبلغ المخصص = الرصيد الكامل للعميل
            $provisionAmount = $currentBalance;

            $writeoffThreshold = ClientSettingsHelper::badDebtWriteoffApprovalThreshold();
            if ($writeoffThreshold !== null && $provisionAmount > $writeoffThreshold && ! hasPermission('clients_approve_large_debt_writeoff')) {
                DB::rollBack();

                return redirect()->back()->with('error', 'هذا المبلغ ('.number_format($provisionAmount, 2).') يتجاوز الحد المسموح به لتسجيل ديون مشكوك فيها ('.number_format($writeoffThreshold, 2).') ويحتاج اعتماد مسؤول.');
            }

            // إنشاء قيد اليومية
            $journalEntry = JournalEntry::create([
                'reference_number' => 'DD-'.$client->id,
                'date' => now()->toDateString(),
                'description' => 'مديونية مشكوك فيها - '.$client->trade_name,
                'status' => 1, // معتمد مباشرة
                'currency' => $currency,
                'client_id' => $clientId,
                'reference_type' => ReferenceType::JOURNAL_VOUCHER,
                'created_by_employee' => auth()->id(),
                'approved_by_employee' => auth()->id(),
                'is_posted' => 1,
                'branch_id' => auth()->user()->branch_id,
            ]);

            // تفاصيل القيد
            // 1. حساب الديون المشكوك فيها (مدين) - المخصص الكامل
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $doubtfulDebtAccount->id,
                'description' => 'مديونية مشكوك فيها - '.$client->trade_name,
                'debit' => $provisionAmount,
                'credit' => 0,
                'reference' => $clientAccount->id,
                'currency' => $currency,
                'is_debit' => true,
            ]);

            // 2. حساب العميل (دائن) - المبلغ الكامل المديون (متساوي مع المدين)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $clientAccount->id,
                'description' => 'تصفية حساب - مديونية مشكوك فيها - '.$client->trade_name,
                'debit' => 0,
                'credit' => $provisionAmount,
                'reference' => $clientAccount->id,
                'currency' => $currency,
                'is_debit' => false,
            ]);

            // تصفير رصيد حساب العميل
            $clientAccount->save();

            // تسجيل الديون المشكوك فيها في قاعدة البيانات
            DoubtfulDebt::create([
                'client_id' => $clientId,
                'account_id' => $clientAccount->id,
                'original_balance' => $currentBalance,
                'provision_amount' => $provisionAmount,
                'provision_percentage' => 100,
                'journal_entry_id' => $journalEntry->id,
                'status' => 'pending',
                'collected_amount' => 0,
                'created_by' => auth()->id(),
            ]);

            // تسجيل سجل العملية
            OperationLog::record(
                'doubtful_debt_added',
                self::LOG_MODULE,
                (int) $clientId,
                'تسجيل سريع لمديونية مشكوك فيها للعميل «'.$client->trade_name.'» — الرصيد المصفَّر '.$currentBalance.
                    ' والمخصّص '.$provisionAmount,
                ['new' => ['original_balance' => $currentBalance, 'provision_amount' => $provisionAmount, 'currency' => $currency]]
            );

            DB::commit();

            return redirect()->route('clients.show', $clientId)
                ->with('toast_type', 'success')
                ->with('toast_message', '✅ تم تسجيل مديونية مشكوك فيها وتصفيه الحساب بنجاح! المبلغ المصفر: '.number_format($currentBalance, 2).
                    ' - المخصص: '.number_format($provisionAmount, 2).' - رقم القيد: '.$journalEntry->id);

        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'add_doubtful_debt_quick');

            return redirect()->back()
                ->with('error', '❌ حدث خطأ: '.$e->getMessage());
        }
    }

    public function showWriteOffBranchDebtForm()
    {
        $branches = Branch::all();

        return view('client::write_off_branch_debt', compact('branches'));
    }

    public function writeOffBranchDebt(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'reason' => 'required|string|max:500',
                'notes' => 'nullable|string|max:1000',
            ]);

            $currency = $this->defaultCurrency();

            if ($block = $this->accountingPeriodBlock(now())) {
                return back()->with('error', $block);
            }

            DB::beginTransaction();

            // Fetch the branch
            $branch = Branch::findOrFail($validated['branch_id']);

            // Get all clients in this branch that have outstanding debt
            $clientsWithDebt = Client::where('branch_id', $validated['branch_id'])
                ->whereHas('branch')
                ->get();

            if ($clientsWithDebt->isEmpty()) {
                return back()->with('warning', 'لا توجد عملاء في هذا الفرع.');
            }

            // Get bad debt account
            $badDebtAccount = $this->settingsAccount('bad_debts');

            $processedCount = 0;
            $totalWrittenOff = 0;
            $skippedCount = 0;

            // Process each client
            foreach ($clientsWithDebt as $client) {
                // Fetch client account
                $clientAccount = Account::ledger()->where('client_id', $client->id)->first();
                if (! $clientAccount) {
                    $skippedCount++;

                    continue;
                }

                // Get current balance
                $currentBalance = floatval($clientAccount->balance ?? 0);

                // Skip if balance is zero or negative (already paid or creditor)
                if ($currentBalance <= 0) {
                    $skippedCount++;

                    continue;
                }

                // Create journal entry for this client
                $journalEntry = JournalEntry::create([
                    'reference_number' => 'WO-BATCH-'.$branch->id.'-'.$client->id.'-'.time(),
                    'date' => now()->toDateString(),
                    'description' => 'تسوية ديون معدومة جماعية - الفرع: '.$branch->name.' - العميل: '.$client->trade_name,
                    'status' => 1,
                    'currency' => $currency,
                    'client_id' => $client->id,
                    'reference_type' => ReferenceType::JOURNAL_VOUCHER,
                    'created_by_employee' => auth()->id(),
                    'approved_by_employee' => auth()->id(),
                    'is_posted' => 1,
                    'branch_id' => $validated['branch_id'],
                ]);

                $amount = $currentBalance;

                // Correct entry:
                // 1) Bad Debt Account (Debit)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $badDebtAccount->id,
                    'description' => 'مصروف ديون معدومة - '.$client->trade_name,
                    'debit' => $amount,
                    'credit' => 0,
                ]);

                // 2) Client Account (Credit)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $clientAccount->id,
                    'description' => 'إقفال مديونية العميل - '.$client->trade_name,
                    'debit' => 0,
                    'credit' => $amount,
                ]);

                // Zero out client account balance
                $clientAccount->balance = 0;
                $clientAccount->save();

                $totalWrittenOff += $amount;
                $processedCount++;
            }

            // Log the batch action
            OperationLog::record(
                'branch_debt_written_off',
                self::LOG_MODULE,
                null,
                'تسوية ديون معدومة جماعية لفرع «'.$branch->name.'» — '.$processedCount.' عميل بإجمالي '
                    .\App\Support\CurrencySettings::format($totalWrittenOff).' — السبب: '.$validated['reason'],
                ['new' => ['branch_id' => $branch->id, 'clients' => $processedCount, 'total' => $totalWrittenOff, 'currency' => $currency]]
            );

            DB::commit();

            $message = 'تم تسوية الديون المعدومة بنجاح للفرع: '.$branch->name.
                ' | عدد العملاء المعالجين: '.$processedCount.
                ' | إجمالي المبلغ المسوى: '.number_format($totalWrittenOff, 2).' ريال';

            if ($skippedCount > 0) {
                $message .= ' | عدد العملاء المتخطاهم (بدون ديون): '.$skippedCount;
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'writeoff_branch_debt');

            return back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    // storeEmployeeNote()، getEmployeeNotes()، compressAndSaveImage()،
    // editNote()، updateNote()، destroyNote()، deleteNoteAttachment() نُقلت
    // إلى ClientNoteController، و clientEmployeesDirectory() إلى
    // ClientEmployeeController — بلا تغيير في المنطق أو أسماء الـ routes.

    /**
     * استبعاد العميل من التحقق بالموقع
     */
    public function toggleExemptLocation(Request $request, $clientId)
    {
        /* `hasPermission()` لا `$user->can()`: صلاحيات هذا النظام على
           الدور (`role_id` ← `role_has_permissions`) لا على المستخدم، وهي
           ما تقرؤه بقيّة الشاشات — انظر `app/Helpers/PermissionHelper.php`.
           والصلاحية مفروضةٌ على المسار كذلك، وهذا فحصٌ ثانٍ للرسالة. */
        if (! hasPermission('clients_exempt_visit_location')) {
            return redirect()->back()
                ->with('toast_message', 'ليس لديك صلاحية استبعاد العميل من التحقق بالموقع')
                ->with('toast_type', 'error');
        }

        if (! \App\Models\ClientModuleSetting::current()->exempt_location_enabled) {
            return redirect()->back()
                ->with('toast_message', 'ميزة استبعاد الموقع معطّلة من إعدادات وحدات العملاء')
                ->with('toast_type', 'error');
        }

        $client = Client::findOrFail($clientId);

        try {
            $client->exempt_visit_location = ! $client->exempt_visit_location;
            $client->save();

            $message = $client->exempt_visit_location
                ? 'تم استبعاد العميل من التحقق بالموقع بنجاح!'
                : 'تم إلغاء استبعاد العميل من التحقق بالموقع بنجاح!';

            OperationLog::record(
                'exempt_location_toggled',
                self::LOG_MODULE,
                $client,
                'تعديل خيار استبعاد الموقع للعميل «'.$client->trade_name.'» إلى: '.($client->exempt_visit_location ? 'مستبعد' : 'غير مستبعد')
            );

            return redirect()->back()
                ->with('toast_message', $message)
                ->with('toast_type', 'success');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'toggle_exempt_location', $client);

            return redirect()->back()
                ->with('toast_message', 'حدث خطأ أثناء تعديل استبعاد الموقع.')
                ->with('toast_type', 'error');
        }
    }

    /**
     * أُلغيت هذه الميزة كلياً: كانت تستثني عميلاً من شرط «فاتورة/سند/إشعار
     * دائن اليوم» قبل السماح بتبديل بضاعته — إعفاءٌ من ضابطٍ رقابي، لا
     * صلاحية عملٍ عادية. زرّها حُذف من `actions-buttons.blade.php`، والشرط
     * نفسه صار غير قابلٍ للاستثناء في حساب `canExchange`/`hasTodayTransaction`
     * أعلاه — فهذا المسار مرفوضٌ دوماً الآن، محفوظاً فقط كي لا ينكسر أيّ
     * رابطٍ قديم بخطإ ٤٠٤ مربِك.
     */
    public function toggleExemptExchange(Request $request, $clientId)
    {
        abort(410, 'ميزة استبعاد الفواتير أُلغيت نهائياً — شرط المعاملة اليومية لتبديل البضاعة لم يعد قابلاً للاستثناء.');
    }
}
