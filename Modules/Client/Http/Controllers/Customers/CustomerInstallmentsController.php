<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Installment;
use App\Models\PaymentsProcess;
use App\Models\Statuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerInstallmentsController extends Controller
{
    use \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports;
    use \Modules\Client\Http\Controllers\Concerns\RespectsAccountingPeriods;

    public function __construct()
    {
        /* صلاحيةُ هذا التقرير وحدَه.

           كانت التركيبةُ الثلاثية `clients_view_all_reports|clients_view_own_reports|client_reports_view`
           مكرَّرةً حرفياً في التسعة، فمن فُتح له تقريرٌ انفتحت له
           التسعةُ جميعاً. واثنتان منها صلاحيتا **نطاق** لا عملية،
           وقد صار النطاقُ محوراً مستقلاً يقرؤه `App\Support\DataScope`. */
        $this->middleware('permission:clients.reports.installments');
    }

    /**
     * عرض صفحة الأقساط
     */
    public function customerInstallments(Request $request)
    {
        /* نفسُ فلاتر بقيّة تقارير العملاء ومن مصادرها نفسِها: «تابع لـ»
           شجرةً واحدة، والعميلُ بالبحث لا بقائمةٍ من ٣٨٥٢ عنصراً، ومسؤولُ
           المبيعات من أقسام إعدادات وحدات العملاء. */
        $categories = CategoriesClient::all();
        $statuses = Statuses::assignable()->get(['id', 'name', 'color']);

        // الفترة المحاسبية — تملأ حقلَي التاريخ القائمَين ولا تزيد فلتراً
        $periodContext = $this->reportPeriodContext($request, 'date_from', 'date_to');

        /* النتيجةُ تُبنى هنا لا بطلبٍ ثانٍ بعد التحميل — كما في تقريرَي
           أعمار الديون: رابطُ الصفحة يحمل ما يُعرض فيه. */
        $report = $this->getReportDataCustomerInstallments($request)->getData(true);

        return view('client::customers.CustomerReport.customer_installment', [
            'reportRows' => $report['installments'] ?? [],
            'totals' => $report['totals'] ?? [],
            'chartData' => $report['chart_data'] ?? [],
            'treeNodes' => $this->affiliationTreeNodes(),
            'salesEmployees' => $this->salesEmployeeOptions(),
            'statuses' => $statuses,
            'registrationStages' => $this->registrationStageOptions(),
            'customerTargets' => $this->customerTargetOptions(),
            'categories' => $categories,
            'selectedClient' => $request->filled('customer')
                ? Client::find($request->input('customer'))
                : null,
            'periodContext' => $periodContext,
        ]);
    }

    /**
     * إرجاع بيانات الأقساط عبر AJAX
     */
    public function customerInstallmentsAjax(Request $request)
    {
        return $this->getReportDataCustomerInstallments($request);
    }

    /**
     * دالة جلب بيانات تقرير أقساط العملاء
     */
    private function getReportDataCustomerInstallments(Request $request)
    {
        try {
            // تحديد نطاق التاريخ
            $dateRange = $this->getDateRange($request);

            // تطبيق استعلام الأقساط مع العلاقات اللازمة
            $query = Installment::with([
                'invoice',
                'invoice.client',
                'invoice.client.status_client:id,name,color',
                'invoice.client.employee',
                'invoice.client.employee.branch',
                'invoice.client.branch',
                'invoice.client.neighborhood.Region',
                'invoice.client.categoriesClient',
                /* اسمُ العلاقة `details` لا `installmentDetails` — الأخيرةُ
                   غيرُ معرَّفةٍ على النموذج إطلاقاً. ولم يظهر الخطأ لأنّ
                   الجدولَ فارغ: Eloquent لا يُشغّل تحميلَ العلاقة حين لا
                   صفَّ أباً لها. فأوّلُ قسطٍ يُنشَأ كان سيُسقط الشاشةَ
                   بـ«Call to undefined relationship». */
                'details',
            ]);

            /* المدى على **أقساط** الاتفاقية لا على `installments.due_date` —
               ذاك أوّلُ استحقاقٍ فيها وحدَه، فكانت اتفاقيةٌ قسطُها في سبتمبر
               تغيب عن بحث سبتمبر لأنّ أوّلَ أقساطها في أغسطس. والملغاةُ لا
               دينَ عليها. */
            $query->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelled'));

            if ($dateRange['from'] || $dateRange['to']) {
                $query->whereHas('details', function ($q) use ($dateRange) {
                    if ($dateRange['from']) {
                        $q->where('due_date', '>=', $dateRange['from']);
                    }
                    if ($dateRange['to']) {
                        $q->where('due_date', '<=', $dateRange['to']);
                    }
                });
            }

            /* ══════════ فلاتر العميل ══════════

               تعريفٌ واحدٌ مع بقيّة تقارير العملاء (`applyClientFilters`)
               على قاعدة العميل نفسِها — وهنا يُبلَغ إليها عبر فاتورة القسط.

               كانت هنا نسخةٌ ثالثةٌ من الفلاتر تختلف عن أختيها: «الفرع»
               يطابق `clients.branch_id` (الفرعَ المحاسبيَّ المشتقّ لا
               تبعيّةَ العميل)، و«المجموعة» تمرّ عبر حيّ العميل وحدَه فيسقط
               من لا حيَّ له، و«مسؤول المبيعات» يترجم مستخدماً إلى موظّف
               فيُسقط كلَّ مندوبٍ بلا حساب دخول. */
            $query->whereHas('invoice.client', fn ($q) => $this->applyClientFilters($q, $request));

            // الحصول على الأقساط المفلترة
            $installments = $query->get();

            // معالجة البيانات وحساب التفاصيل
            $installmentData = [];
            $totals = [
                'total_installments' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'pending_amount' => 0,
                'overdue_amount' => 0,
                // غراماتُ التأخير المحمَّلة غيرُ المحصَّلة — ذمّةٌ على العميل خارج مبلغ الأقساط
                'late_fees_outstanding' => 0,
                // الدفعةُ المقدَّمة — تُقبض عند الاتفاقية وليست من الأقساط
                'down_payment' => 0,
                'down_payment_paid' => 0,
                // المتبقي من أقساطٍ يقع استحقاقُها داخل المدى المختار
                'period_due' => 0,
            ];

            $statusCounts = [
                'paid' => 0,
                'pending' => 0,
                'overdue' => 0,
                'partial' => 0,
            ];

            foreach ($installments as $installment) {
                // تحقق من وجود الفاتورة والعميل
                if (! $installment->invoice || ! $installment->invoice->client) {
                    continue;
                }

                /* ══════════ الصفُّ = الاتفاقية، وأرقامُه من أقساطها ══════════

                   كان «المدفوع» يُقرأ من `payments_process.installment_id` —
                   عمودٌ لا وجود له، والخطأ يُبتلع فيعود صفراً: لا قسطَ «مدفوع»
                   ولا «جزئي» أبداً. والمبلغُ قسطٌ واحد (`installments.amount`)
                   لا ما على الاتفاقية، والاستحقاقُ أوّلُ أقساطها ولو سُدِّد.

                   المدفوعُ الآن `installments_details.paid_amount` — نفسُ ما
                   يقرؤه `Installment::paid_amount` — والمبلغُ مجموعُ الأقساط،
                   والاستحقاقُ أقربُ قسطٍ لم يُسدَّد، والتأخيرُ من أقدم متأخّر. */
                $details = $installment->details->sortBy('due_date')->values();
                $today = now()->startOfDay();

                $totalAmount = (float) $details->sum('amount');
                $paidAmount = (float) $details->sum(fn ($d) => min((float) $d->paid_amount, (float) $d->amount));
                $remainingAmount = round($totalAmount - $paidAmount, 2);

                $openDetails = $details->filter(fn ($d) => (float) $d->paid_amount < (float) $d->amount - 0.005);
                $nextDue = $openDetails->first();
                $oldestOverdue = $openDetails->first(fn ($d) => $d->due_date && $d->due_date->lt($today));

                $status = match (true) {
                    $remainingAmount <= 0.005 => 'paid',
                    $oldestOverdue !== null => 'overdue',
                    $paidAmount > 0.005 => 'partial',
                    default => 'pending',
                };

                // تطبيق فلتر حالة القسط
                if ($request->filled('installment_status') && $status !== $request->installment_status) {
                    continue;
                }

                // فلتر الأقساط المتأخرة فقط
                if ($request->boolean('show_overdue_only') && $status !== 'overdue') {
                    continue;
                }

                $daysOverdue = $oldestOverdue ? (int) $oldestOverdue->due_date->copy()->startOfDay()->diffInDays($today) : 0;
                $overdueAmount = (float) $openDetails
                    ->filter(fn ($d) => $d->due_date && $d->due_date->lt($today))
                    ->sum(fn ($d) => (float) $d->amount - (float) $d->paid_amount);

                /* الغراماتُ والمقدَّمةُ والمستحقُّ في المدى — كانت غائبةً عن التقرير:
                   اتفاقيةٌ عليها 500 غرامةً محمَّلةً تظهر كأنّ ما عليها الأقساطُ وحدَها. */
                $lateFeesOutstanding = round((float) $details->sum(fn ($d) => max(0, (float) $d->late_fee_amount - (float) $d->late_fee_paid)), 2);
                $downPayment = round((float) $installment->down_payment, 2);
                $downPaymentPaid = $downPayment > 0 ? (float) $installment->down_payment_paid : 0.0;
                $periodDue = round((float) $openDetails
                    ->filter(fn ($d) => $d->due_date
                        && (! $dateRange['from'] || $d->due_date->toDateString() >= $dateRange['from'])
                        && (! $dateRange['to'] || $d->due_date->toDateString() <= $dateRange['to']))
                    ->sum(fn ($d) => (float) $d->amount - (float) $d->paid_amount), 2);

                // جلب تفاصيل الأقساط الفرعية
                $installmentDetails = [];
                foreach ($details as $detail) {
                    $detailPaidAmount = min((float) $detail->paid_amount, (float) $detail->amount);
                    $detailRemainingAmount = round((float) $detail->amount - $detailPaidAmount, 2);
                    $detailStatus = $this->determineInstallmentDetailStatus($detail, $detailPaidAmount);
                    $detailDaysOverdue = $this->calculateDaysOverdue($detail->due_date, $detailStatus);

                    $installmentDetails[] = [
                        'id' => $detail->id,
                        'amount' => (float) $detail->amount,
                        // تاريخٌ مجرّد — التسلسلُ الافتراضيّ UTC كان يُظهره قبل يومه بيوم
                        'due_date' => optional($detail->due_date)->toDateString(),
                        'status' => $detailStatus,
                        'paid_amount' => $detailPaidAmount,
                        'remaining_amount' => $detailRemainingAmount,
                        'days_overdue' => $detailDaysOverdue,
                        'description' => 'القسط '.($detail->installment_number ?? ''),
                        'notes' => $detail->note ?? '',
                        'late_fee_amount' => (float) $detail->late_fee_amount,
                        'payment_method' => $detail->payment_method ?? 'غير محدد',
                        'reference_number' => $detail->reference_number ?? '',
                        'installments_id' => $detail->installments_id,
                        'created_at' => $detail->created_at,
                        'updated_at' => $detail->updated_at,
                    ];
                }

                $installmentRecord = [
                    'id' => $installment->id,
                    'installment_id' => $installment->id,
                    'invoice_id' => $installment->invoice->id,
                    'invoice_number' => $installment->invoice->code ?? $installment->invoice->id,
                    'agreement_code' => $installment->code,
                    'agreement_status' => $installment->status,
                    'client_id' => $installment->invoice->client->id ?? null,
                    // `display_name`: الاسمُ التجاريّ الفارغ ("") كان يُعرض اسماً فارغاً
                    'client_name' => $installment->invoice->client->display_name ?? 'غير محدد',
                    'client_code' => $installment->invoice->client->code ?? 'غير محدد',
                    'client_status_name' => optional($installment->invoice->client->status_client)->name ?? 'غير محدد',
                    'client_status_color' => optional($installment->invoice->client->status_client)->color ?? 'secondary',
                    // «تابع لـ» عقدةُ العميل الفعلية، و`Employee` بلا عمود `name` — الاسمُ `full_name`
                    'branch' => $installment->invoice->client->resolveTreeNode()?->name
                        ?? optional($installment->invoice->client->branch)->name
                        ?? 'غير محدد',
                    'employee' => trim((string) optional($installment->invoice->client->employee)->full_name) ?: 'غير محدد',
                    'category' => optional($installment->invoice->client->categoriesClient ?? null)->name ?? 'غير محدد',
                    'installment_number' => $details->count() ?: ($installment->installment_number ?? 1),
                    'amount' => $totalAmount,
                    'due_date' => optional($nextDue?->due_date ?? $details->last()?->due_date)->toDateString(),
                    'status' => $status,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'days_overdue' => $daysOverdue,
                    'overdue_amount' => round($overdueAmount, 2),
                    'late_fees_outstanding' => $lateFeesOutstanding,
                    'down_payment' => $downPayment,
                    'down_payment_paid' => round($downPaymentPaid, 2),
                    'period_due' => $periodDue,
                    'details' => $installmentDetails,
                    'details_count' => count($installmentDetails),
                ];

                $installmentData[] = $installmentRecord;

                // تحديث الإجماليات
                $totals['total_amount'] += $totalAmount;
                $totals['paid_amount'] += $paidAmount;
                $totals['pending_amount'] += $remainingAmount;
                // المتأخّرُ ما فات استحقاقُه فعلاً، لا المتبقّي كلُّه
                $totals['overdue_amount'] += $overdueAmount;
                $totals['late_fees_outstanding'] += $lateFeesOutstanding;
                $totals['down_payment'] += $downPayment;
                $totals['down_payment_paid'] += $downPaymentPaid;
                $totals['period_due'] += $periodDue;

                // تحديث عدادات الحالة
                $statusCounts[$status]++;
            }

            $totals['total_installments'] = count($installmentData);
            foreach (['total_amount', 'paid_amount', 'pending_amount', 'overdue_amount', 'late_fees_outstanding', 'down_payment', 'down_payment_paid', 'period_due'] as $key) {
                $totals[$key] = round((float) $totals[$key], 2);
            }

            // ترتيب النتائج حسب تاريخ الاستحقاق
            usort($installmentData, function ($a, $b) {
                return strtotime($a['due_date']) - strtotime($b['due_date']);
            });

            // تجميع البيانات حسب العميل
            $groupedData = [];
            if ($request->boolean('group_by_client')) {
                $groupedData = $this->groupInstallmentsByClient($installmentData);
            }

            /* الرسم: مدفوع / متبقٍّ لم يحلّ بعد / متأخّر — مجموعُها مبلغُ الأقساط.
               شريحةُ «جزئي» كانت صفراً دائماً (لا تُحسب) فأُزيلت. */
            $chartData = [
                'paid' => $totals['paid_amount'],
                'pending' => round($totals['pending_amount'] - $totals['overdue_amount'], 2),
                'overdue' => $totals['overdue_amount'],
            ];

            // إعداد الاستجابة
            $response = [
                'success' => true,
                'installments' => $installmentData,
                'grouped_data' => $groupedData,
                'totals' => $totals,
                'status_counts' => $statusCounts,
                'chart_data' => $chartData,
                'records_count' => count($installmentData),
                'filters_applied' => [
                    'date_from' => $dateRange['from'],
                    'date_to' => $dateRange['to'],
                    'client' => $request->filled('customer'),
                    'employee' => $request->filled('sales_manager'),
                    'branch' => $request->filled('branch'),
                    'client_category' => $request->filled('client_category'),
                    'installment_status' => $request->filled('installment_status'),
                    'show_overdue_only' => $request->boolean('show_overdue_only'),
                    'group_by_client' => $request->boolean('group_by_client'),
                ],
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('خطأ في تقرير أقساط العملاء: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ في تحميل البيانات',
                    'error' => config('app.debug') ? $e->getMessage() : 'خطأ داخلي في الخادم',
                ],
                500,
            );
        }
    }

    /**
     * الحصول على اسم العميل
     */
    private function getClientName($client)
    {
        if (! $client) {
            return 'غير محدد';
        }

        return $client->trade_name ?? $client->first_name.' '.$client->last_name;
    }

    /**
     * حساب المبلغ المدفوع للقسط
     */
    private function getInstallmentPaidAmount($installmentId)
    {
        try {
            $paidAmount = PaymentsProcess::where('installment_id', $installmentId)->sum('amount');

            return $paidAmount ?? 0;
        } catch (\Exception $e) {
            Log::error('خطأ في حساب المبلغ المدفوع للقسط: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * حساب المبلغ المدفوع لتفصيل القسط
     */
    private function getInstallmentDetailPaidAmount($installmentDetailId)
    {
        try {
            $paidAmount = PaymentsProcess::where('installments_detail_id', $installmentDetailId)->sum('amount');

            return $paidAmount ?? 0;
        } catch (\Exception $e) {
            Log::error('خطأ في حساب المبلغ المدفوع لتفصيل القسط: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * تحديد حالة تفصيل القسط
     */
    private function determineInstallmentDetailStatus($installmentDetail, $paidAmount)
    {
        $totalAmount = $installmentDetail->amount;
        $dueDate = $installmentDetail->due_date;
        $today = now()->startOfDay();

        // إذا كان مدفوع بالكامل
        if ($paidAmount >= $totalAmount) {
            return 'paid';
        }

        // إذا كان مدفوع جزئياً
        if ($paidAmount > 0 && $paidAmount < $totalAmount) {
            return 'partial';
        }

        // إذا كان متأخر
        if ($dueDate < $today) {
            return 'overdue';
        }

        // إذا كان قيد الانتظار
        return 'pending';
    }

    /**
     * تحديد حالة القسط
     */
    private function determineInstallmentStatus($installmentDetail, $paidAmount)
    {
        $totalAmount = $installmentDetail->amount;
        $dueDate = $installmentDetail->due_date;
        $today = now()->startOfDay();

        // إذا كان مدفوع بالكامل
        if ($paidAmount >= $totalAmount) {
            return 'paid';
        }

        // إذا كان مدفوع جزئياً
        if ($paidAmount > 0 && $paidAmount < $totalAmount) {
            return 'partial';
        }

        // إذا كان متأخر
        if ($dueDate < $today) {
            return 'overdue';
        }

        // إذا كان قيد الانتظار
        return 'pending';
    }

    /**
     * حساب أيام التأخير
     */
    private function calculateDaysOverdue($dueDate, $status)
    {
        if ($status !== 'overdue') {
            return 0;
        }

        $today = now()->startOfDay();
        $due = \Carbon\Carbon::parse($dueDate)->startOfDay();

        // من الاستحقاق إلى اليوم — بالعكس يعطي Carbon 3 عدداً سالباً فيُعرض «-»
        return (int) $due->diffInDays($today);
    }

    /**
     * تجميع الأقساط حسب العميل
     */
    private function groupInstallmentsByClient($installmentData)
    {
        $grouped = [];

        foreach ($installmentData as $installment) {
            $clientId = $installment['client_id'];
            $clientName = $installment['client_name'];

            if (! isset($grouped[$clientId])) {
                $grouped[$clientId] = [
                    'client_name' => $clientName,
                    'client_code' => $installment['client_code'],
                    'installments' => [],
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'remaining_amount' => 0,
                    'count' => 0,
                ];
            }

            $grouped[$clientId]['installments'][] = $installment;
            $grouped[$clientId]['total_amount'] += $installment['amount'];
            $grouped[$clientId]['paid_amount'] += $installment['paid_amount'];
            $grouped[$clientId]['remaining_amount'] += $installment['remaining_amount'];
            $grouped[$clientId]['count']++;
        }

        // ترتيب المجموعات حسب المبلغ المتبقي تنازلياً
        uasort($grouped, function ($a, $b) {
            return $b['remaining_amount'] <=> $a['remaining_amount'];
        });

        return $grouped;
    }

    /**
     * تحديد نطاق التاريخ
     */
    private function getDateRange(Request $request)
    {
        // `input()` لا `get()` — الأخيرةُ مُهمَلةٌ في symfony/http-foundation 7.4 وتطبع تحذيراً في كلّ طلب
        $dateType = $request->input('date_type', 'custom');

        switch ($dateType) {
            case 'today':
                $from = \Carbon\Carbon::today()->format('Y-m-d');
                $to = \Carbon\Carbon::today()->format('Y-m-d');
                break;
            case 'yesterday':
                $from = \Carbon\Carbon::yesterday()->format('Y-m-d');
                $to = \Carbon\Carbon::yesterday()->format('Y-m-d');
                break;
            case 'this_week':
                $from = \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d');
                $to = \Carbon\Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'last_week':
                $from = \Carbon\Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d');
                $to = \Carbon\Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $from = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');
                $to = \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $from = \Carbon\Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
                $to = \Carbon\Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_year':
                $from = \Carbon\Carbon::now()->startOfYear()->format('Y-m-d');
                $to = \Carbon\Carbon::now()->endOfYear()->format('Y-m-d');
                break;
            case 'last_year':
                $from = \Carbon\Carbon::now()->subYear()->startOfYear()->format('Y-m-d');
                $to = \Carbon\Carbon::now()->subYear()->endOfYear()->format('Y-m-d');
                break;
            case 'custom':
            default:
                /* الافتراضُ كان «من أوّل الشهر إلى اليوم» — مدىً تقويميّ لا
                   صلةَ له بالفترة المحاسبية. صار الفترةَ المختارة، وإلّا
                   السنةَ المالية الجارية.

                   والمُسبقاتُ أعلاه (اليوم/الأسبوع/الشهر/السنة) تبقى كما هي:
                   هي اختيارُ المستخدم الصريح، ولا يُقصّ اختيارٌ صريح. */
                $period = $this->reportPeriodContext($request, 'date_from', 'date_to');
                $from = $period['start'];
                $to = $period['end'];
                break;
        }

        return [
            'from' => $from,
            'to' => $to,
        ];
    }
}
