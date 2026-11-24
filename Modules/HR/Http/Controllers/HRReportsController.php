<?php

namespace Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Controllers\Concerns\ExportsHrReports;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Controllers\Reports\PayrollDocumentsReportController;
use Modules\HR\Support\BranchTree;

class HRReportsController extends Controller
{
    use ExportsHrReports;
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        // فهرسُ التقارير لمن يملك أيَّ قسمٍ منها — وبلاطاتُه تُصفّى بصلاحية كلٍّ
        $this->middleware('permission.role:hr_reports.employees|hr_reports.attendance|hr_reports.payroll', ['only' => ['index']]);
        $this->middleware('permission.role:hr_reports.attendance', ['only' => ['attendanceByDepartment']]);
        $this->middleware('permission.role:hr_reports.payroll', ['only' => ['paymentsReport', 'salariesByDepartment']]);

        /*
            التصديرُ صلاحيةٌ فوق صلاحية التقرير (`hr_reports.export`): من
            يطّلع على التقرير في الشاشة ليس بالضرورة من يُخرجه ملفّاً.
            والتصديرُ يقع بـ`export=xlsx|pdf` على مسار التقرير نفسِه.
        */
        $this->middleware(function ($request, $next) {
            // تصديرُ ملف (xlsx/pdf) يحتاج صلاحيةَ التصدير؛ و`export=rows` هو ما تقرؤه الطباعةُ أيضاً
            if ($request->filled('export') && $request->input('export') !== 'rows' && ! hasPermission('hr_reports.export')) {
                abort(403, 'ليس لديك صلاحية تصدير تقارير الموارد البشرية.');
            }

            return $next($request);
        });
    }

    /**
     * عرض صفحة التقارير الرئيسية
     */
    public function index()
    {
        return view('hr::reports.index');
    }

    /**
     * تقرير الحضور التفصيلي حسب القسم.
     *
     * كان يقرأ من `attendance_records` — جدول الجلسات (زوج دخول/خروج لكل
     * مسحة باركود)، لا دفتر اليوم — فيغيب عنه أيُّ موظفٍ سُجِّل حضورُه
     * يدوياً (`AttendanceDaysController`) أو عبر طلب تصحيح معتمَد
     * (TODO 10)، ويخلط `status` الجلسة (0 = لم يُغلق بعد، لا "غائب") مع
     * حالة الحضور الفعلية. والتأخير/الانصراف المبكر كانا يُحسبان بعتبتين
     * ثابتتين (09:00/17:00) بصرف النظر عن وردية الموظف الفعلية، متجاهلَين
     * `late_minutes`/`early_leave_minutes` التي يحسبها المعالج فعلياً
     * (TODO 5-8) ويطبّقها فور الانصراف (TODO 9).
     *
     * صار الآن يقرأ من `attendance_days` مباشرة، ويعرض ما حسبه المعالج
     * بدل إعادة اشتقاقه بعتباتٍ خيالية — الحالة الحقيقية، ودقائق التأخير/
     * الانصراف المبكر/الإضافي المخزَّنة، وأيام الحضور بلا انصراف مسجَّل.
     */
    public function attendanceByDepartment(Request $request)
    {
        /*
            صار هذا التقرير جزءاً من «ملخص الحضور الشامل» (عرض «لكل يوم»):
            نفسُ صفوف `attendance_days` وأكثر (الاستراحة وصافي العمل وخصم
            الحضور)، بتصميم تقارير العملاء. يُحوَّل إليه بالفلاتر نفسِها؛
            والتصديرُ القديم (`?export=xlsx|pdf`) يبقى يعمل من هنا.
        */
        if (! $request->filled('export')) {
            return redirect()->route('hr.reports.attendance.summary', array_filter([
                'view' => 'details',
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'department_id' => $request->input('department_id'),
                'employee_id' => $request->input('employee_id'),
                'branch_id' => $request->input('branch_id'),
            ], fn ($v) => $v !== null && $v !== ''));
        }

        $departments = Department::all();
        // قائمةُ الفلتر من نطاق المستخدم — لا كلُّ موظفي المنشأة
        $employees = $this->scopedEmployeeOptions();
        $branches = Branch::all();

        // إعداد البيانات الافتراضية
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : now()->startOfMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : now();

        $attendanceData = collect();
        $totals = [
            'total_employees' => 0,
            'total_present' => 0,
            'total_absent' => 0,
            'total_late' => 0,
            'total_early_leave' => 0,
            'total_overtime' => 0,
            'total_missing_checkout' => 0,
        ];

        // إذا تم إرسال الفلاتر
        if ($request->has('fetch_data') || $request->filled('export')) {
            $query = DB::table('attendance_days')
                ->join('employees', 'attendance_days.employee_id', '=', 'employees.id')
                ->tap(fn ($q) => $this->scopeAnyQueryToEmployees($q, 'attendance_days.employee_id'))
                ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                ->whereBetween('attendance_days.attendance_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

            // تطبيق الفلاتر
            if ($request->filled('department_id')) {
                $query->where('employees.department_id', $request->department_id);
            }

            if ($request->filled('employee_id')) {
                $query->where('attendance_days.employee_id', $request->employee_id);
            }

            if ($request->filled('branch_id')) {
                $query->where(function ($branchFilter) use ($request) {
                    BranchTree::apply($branchFilter, $request->branch_id, 'employees.branch_id');
                });
            }

            // جلب البيانات
            $attendanceData = $query->select(
                'attendance_days.*',
                'employees.first_name as employee_name',
                'employees.id as employee_code',
                'departments.name as department_name'
            )
                ->orderBy('attendance_days.attendance_date', 'desc')
                ->orderBy('employees.first_name', 'asc')
                ->get();

            // حساب الإجماليات من الحالة والدقائق المحسوبة فعلياً — لا عتباتٍ ثابتة
            $totals['total_employees'] = $attendanceData->unique('employee_id')->count();
            $totals['total_present'] = $attendanceData->where('status', 'present')->count();
            $totals['total_absent'] = $attendanceData->where('status', 'absent')->count();
            $totals['total_late'] = $attendanceData->where('status', 'late')->count();
            $totals['total_early_leave'] = $attendanceData->filter(fn ($r) => (int) ($r->early_leave_minutes ?? 0) > 0)->count();
            $totals['total_overtime'] = $attendanceData->filter(fn ($r) => (int) ($r->overtime_minutes ?? 0) > 0)->count();
            $totals['total_missing_checkout'] = $attendanceData->filter(
                fn ($r) => $r->check_in_time && ! $r->check_out_time
            )->count();
        }

        if ($file = $this->exportIfRequested(
            $request,
            'الحضور التفصيلي',
            ['التاريخ', 'كود الموظف', 'اسم الموظف', 'القسم', 'الحالة', 'وقت الحضور', 'وقت الانصراف', 'دقائق التأخير', 'دقائق الانصراف المبكر', 'دقائق الإضافي'],
            $attendanceData->map(fn ($row) => [
                $row->attendance_date,
                $row->employee_code,
                $row->employee_name,
                $row->department_name,
                $row->status,
                $row->check_in_time,
                $row->check_out_time,
                $row->late_minutes,
                $row->early_leave_minutes,
                $row->overtime_minutes,
            ])->all(),
            'attendance'
        )) {
            return $file;
        }

        return view('hr::reports.attendance_by_department', compact(
            'departments',
            'employees',
            'branches',
            'attendanceData',
            'totals',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * «قسائم الراتب حسب القسم» — صار عرضاً في تقرير «قسائم الرواتب».
     *
     * كان يسرد القسائمَ نفسَها التي يسردها السجلّ (`salary_slips`) بأعمدةٍ أقلّ،
     * ويفلترها بتاريخ بدايتها في الشهر — والقسيمةُ اتفاقٌ مستمرّ لا حدثٌ في
     * شهر، فتختفي القسيمةُ السارية لأنها بدأت قبل المدى. فالمسارُ باقٍ (روابطُ
     * الفهرس والقائمة الجانبية) ويحوِّل إلى عرض «لكل قسم» بفلاتره نفسِها،
     * ومعه `export=xlsx|pdf` إن طُلب.
     */
    public function salariesByDepartment(Request $request)
    {
        $query = array_filter([
            'view' => 'by_department',
            'department_id' => $request->input('department_id'),
            'employee_id' => $request->input('employee_id'),
            'branch_id' => $request->input('branch_id'),
            'slip_status' => array_key_exists((string) $request->input('status'), SalarySlip::statuses()) ? $request->input('status') : null,
            'export' => in_array($request->input('export'), ['xlsx', 'pdf'], true) ? $request->input('export') : null,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->route('hr.reports.payroll.slips_register', $query);
    }

    /**
     * «صرف الرواتب» — ما صُرف فعلاً من `payroll_runs` المصروفة، بتصميم تقارير
     * العملاء. المنطقُ في `Reports\PayrollDocumentsReportController::payments`
     * (لكل دفعة / لكل موظف / لكل خزينة)، والمسارُ واسمُه وحارسُ التصدير هنا.
     */
    public function paymentsReport(Request $request)
    {
        return app(PayrollDocumentsReportController::class)->payments($request);
    }
}
