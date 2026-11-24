<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\EmailStatus;
use App\Enums\RecipientType;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

class EmailLogController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.email_logs.view', ['only' => ['index', 'show', 'statistics']]);
        $this->middleware('permission.role:online_store.email_logs.resend', ['only' => ['resend']]);
        $this->middleware('permission.role:online_store.email_logs.delete', ['only' => ['destroy', 'bulkDelete']]);
        $this->middleware('permission.role:online_store.email_logs.cleanup', ['only' => ['cleanup']]);
        $this->middleware('permission.role:online_store.email_logs.export', ['only' => ['export']]);
    }

    /**
     * عرض قائمة سجلات البريد الإلكتروني
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = EmailLog::with('template');

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('recipient_email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('log_id', 'like', "%{$search}%");
            });
        }

        // التصفية حسب الحالة
        if (is_string($request->status) && EmailStatus::tryFrom($request->status)) {
            $query->where('status', $request->status);
        }

        // التصفية حسب نوع المستلم
        if (is_string($request->recipient_type) && RecipientType::tryFrom($request->recipient_type)) {
            $query->where('recipient_type', $request->recipient_type);
        }

        // التصفية حسب القالب
        if ($request->filled('template_id') && is_numeric($request->template_id)) {
            $query->where('template_id', (int) $request->template_id);
        }

        // التصفية حسب التاريخ — تاريخٌ غير صالح يُهمَل
        if ($from = $this->validDate($request->date_from)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $this->validDate($request->date_to)) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->latest('created_at')->latest('log_id')->paginate(50)->withQueryString();

        // البيانات للفلاتر
        $templates = EmailTemplate::select('template_id', 'template_name')
            ->orderBy('template_name')
            ->get();

        $statuses = EmailStatus::cases();
        $recipientTypes = RecipientType::cases();

        // الإحصائيات
        $stats = [
            'total' => EmailLog::count(),
            'sent' => EmailLog::where('status', EmailStatus::Sent)->count(),
            'failed' => EmailLog::where('status', EmailStatus::Failed)->count(),
            'pending' => EmailLog::where('status', EmailStatus::Pending)->count(),
            'today' => EmailLog::whereDate('created_at', today())->count(),
        ];

        return view('onlinestore::email-logs.index', compact(
            'logs',
            'templates',
            'statuses',
            'recipientTypes',
            'stats'
        ));
    }

    /** تاريخٌ بصيغة Y-m-d أو null. */
    private function validDate($value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * عرض تفاصيل سجل معين
     */
    public function show(EmailLog $emailLog)
    {
        // لا نُحمّل 'recipient' مُسبقاً: علاقةٌ شرطية على نوع المستلم، والعرض
        // يكتفي بـ recipient_email و recipient_type — تحميلها كان يُعطب الصفحة
        // حين يكون النوع Guest/Support/External (كانت العلاقة تُعيد null).
        $emailLog->load('template');

        return view('onlinestore::email-logs.show', compact('emailLog'));
    }

    /**
     * إعادة إرسال بريد فاشل
     */
    public function resend(EmailLog $emailLog)
    {
        // صفحة السجلات تعرض تنبيهَي success/error فقط — رسالة `warning` لم تكن تظهر
        if ($emailLog->status === EmailStatus::Sent) {
            return back()->with('error', 'هذا البريد أُرسل بنجاح من قبل — لا حاجة لإعادة إرساله.');
        }

        try {
            // محاولة إعادة الإرسال
            Mail::html(
                $emailLog->email_content,
                function ($message) use ($emailLog) {
                    $message->to($emailLog->recipient_email)
                        ->subject($emailLog->subject);
                }
            );

            // تحديث الحالة
            $emailLog->update([
                'status' => EmailStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            return back()->with('success', 'تم إعادة إرسال البريد بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.EmailLog', 'resend');

            $emailLog->update([
                'error_message' => $e->getMessage(),
            ]);

            return back()->with('error', 'فشل إعادة الإرسال: '.Str::limit($e->getMessage(), 200).' — راجع إعدادات خادم البريد (SMTP).');
        }
    }

    /**
     * حذف سجل
     */
    public function destroy(EmailLog $emailLog)
    {
        try {
            $emailLog->delete();

            return redirect()
                ->route('email_logs.index')
                ->with('success', 'تم حذف السجل بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.EmailLog', 'destroy');

            return back()->with('error', 'حدث خطأ أثناء حذف السجل');
        }
    }

    /**
     * حذف جماعي للسجلات
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'log_ids' => 'required|array',
            'log_ids.*' => 'integer|exists:email_logs,log_id',
        ], [], ['log_ids' => 'السجلات المحددة', 'log_ids.*' => 'السجل المحدد']);

        try {
            EmailLog::whereIn('log_id', $request->log_ids)->delete();

            return back()->with('success', 'تم حذف السجلات المحددة بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.EmailLog', 'bulkDelete');

            return back()->with('error', 'حدث خطأ أثناء حذف السجلات');
        }
    }

    /**
     * تنظيف السجلات القديمة
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ], [], ['days' => 'عدد الأيام']);

        try {
            $date = now()->subDays($request->days);
            $count = EmailLog::where('created_at', '<', $date)->delete();

            return back()->with('success', "تم حذف {$count} سجل أقدم من {$request->days} يوم");
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.EmailLog', 'cleanup');

            return back()->with('error', 'حدث خطأ أثناء تنظيف السجلات');
        }
    }

    /**
     * إحصائيات تفصيلية
     */
    public function statistics(Request $request)
    {
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $period = $request->get('period', 'week'); // week, month, year
        if (! in_array($period, ['week', 'month', 'year'], true)) {
            $period = 'week';
        }

        $startDate = match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subWeek(),
        };

        // إحصائيات حسب الحالة
        $statusStats = EmailLog::select('status', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('status')
            ->get();

        // إحصائيات حسب القالب
        $templateStats = EmailLog::select('template_id', DB::raw('count(*) as count'))
            ->with('template:template_id,template_name')
            ->where('created_at', '>=', $startDate)
            ->groupBy('template_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // إحصائيات يومية
        $dailyStats = EmailLog::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as total'),
            DB::raw('sum(case when status = "sent" then 1 else 0 end) as sent'),
            DB::raw('sum(case when status = "failed" then 1 else 0 end) as failed')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // معدل النجاح
        $totalEmails = EmailLog::where('created_at', '>=', $startDate)->count();
        $successfulEmails = EmailLog::where('created_at', '>=', $startDate)
            ->where('status', EmailStatus::Sent)
            ->count();
        $successRate = $totalEmails > 0 ? ($successfulEmails / $totalEmails) * 100 : 0;

        return view('onlinestore::email-logs.statistics', compact(
            'statusStats',
            'templateStats',
            'dailyStats',
            'successRate',
            'period',
            'startDate'
        ));
    }

    /**
     * تصدير السجلات
     */
    public function export(Request $request)
    {
        $query = EmailLog::with('template');

        // تطبيق الفلاتر
        if (is_string($request->status) && EmailStatus::tryFrom($request->status)) {
            $query->where('status', $request->status);
        }

        if ($from = $this->validDate($request->date_from)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $this->validDate($request->date_to)) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->latest('log_id')->get();

        // تصدير كـ CSV
        $filename = 'email_logs_'.now()->format('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            // BOM للتوافق مع Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // الرأس
            fputcsv($file, [
                'رقم السجل',
                'القالب',
                'المستلم',
                'البريد الإلكتروني',
                'الموضوع',
                'الحالة',
                'تاريخ الإرسال',
                'رسالة الخطأ',
            ]);

            // البيانات
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->log_id,
                    $log->template?->template_name ?? '',
                    $log->recipient_type->label(),
                    $log->recipient_email,
                    $log->subject,
                    $log->status->label(),
                    $log->sent_at?->format('Y-m-d H:i:s') ?? '',
                    $log->error_message ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
