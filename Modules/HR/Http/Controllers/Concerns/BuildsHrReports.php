<?php

namespace Modules\HR\Http\Controllers\Concerns;

use App\Models\Department;
use App\Models\Employee;
use App\Models\JopTitle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Client\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\HR\Support\BranchTree;

/**
 * أساسُ تقارير الموارد البشرية بتصميم تقارير العملاء.
 *
 * كلُّ تقريرٍ يمرّ بالخطوات نفسها، فتبقى الفلاتر والمدى والنطاق واحدةً في
 * التقارير كلّها:
 *   ١) المدى: `hrReportPeriod()` — من/إلى، أو فترةٌ محاسبية، أو سنةٌ مالية
 *      (نفسُ مُحدِّد الفترة في تقارير العملاء)، ولا يتجاوز اليوم.
 *   ٢) الموظفون: `hrReportEmployees()` — نطاقُ رؤية المستخدم، ثم القسم والمسمى
 *      و«تابع لـ» (الفرع في الشجرة) والموظف والحالة.
 *   ٣) العرض: صفوفٌ مصفوفات، تُرقَّم صفحاتُها بـ`hrPaginate()`، وتُصدَّر كلُّها
 *      بـ`?export=rows` (JSON) لزرَّي التصدير والطباعة — `reports.partials.fetch-rows-tools`.
 *   ٤) الخيارات: `hrReportOptions()` لقوائم الفلتر.
 */
trait BuildsHrReports
{
    use RespectsAccountingPeriods;
    use ScopesToAccessibleEmployees;

    protected int $hrPerPage = 50;

    /**
     * @return array{from: Carbon, to: Carbon, context: array<string, mixed>, label: string}
     */
    protected function hrReportPeriod(Request $request, bool $defaultThisMonth = false): array
    {
        // تقاريرُ الحضور تفتح على الشهر الجاري؛ الرواتبُ على السنة المالية (افتراضُ تقارير العملاء)
        if ($defaultThisMonth && ! $request->hasAny(['from_date', 'to_date', 'accounting_period_id', 'fiscal_year_id'])) {
            $request->merge(['from_date' => Carbon::today()->startOfMonth()->toDateString(), 'to_date' => Carbon::today()->toDateString()]);
        }

        $context = $this->reportPeriodContext($request, 'from_date', 'to_date');
        $from = Carbon::parse($context['start'])->startOfDay();
        $to = Carbon::parse($context['end'])->endOfDay();
        if ($to->isFuture()) {
            $to = Carbon::today()->endOfDay();
        }

        return [
            'from' => $from,
            'to' => $to,
            'context' => $context,
            'label' => 'من '.$from->format('d/m/Y').' إلى '.$to->format('d/m/Y'),
        ];
    }

    /** موظفو التقرير: نطاقُ المستخدم + فلاتر القسم والمسمى والفرع والموظف والحالة. */
    protected function hrReportEmployees(Request $request, bool $activeOnlyByDefault = true): Builder
    {
        $query = $this->scopeToEmployees(Employee::query(), 'id');

        $status = $request->input('employee_status', $activeOnlyByDefault ? 'active' : '');
        // «على رأس العمل» تعريفُ الموديل نفسه (نشط + تحت التجربة) — `Employee::active()`
        $activeValues = \App\Enums\EmploymentStatus::activeValues();
        if ($status === 'active') {
            $query->whereIn('status', $activeValues);
        } elseif ($status === 'inactive') {
            $query->where(fn ($q) => $q->whereNotIn('status', $activeValues)->orWhereNull('status'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->input('department_id'));
        }
        if ($request->filled('job_title_id')) {
            $query->where('job_title_id', (int) $request->input('job_title_id'));
        }
        if ($request->filled('branch_id')) {
            $query->where(fn ($q) => BranchTree::apply($q, $request->input('branch_id'), 'employees.branch_id'));
        }
        if ($request->filled('employee_id')) {
            $query->where('id', (int) $request->input('employee_id'));
        }

        return $query;
    }

    /** @return array<string, mixed> */
    protected function hrReportOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'jobTitles' => JopTitle::orderBy('name')->get(['id', 'name']),
            'employeeOptions' => $this->scopedEmployeeOptions(),
            'treeNodes' => BranchTree::treeNodes(),
        ];
    }

    protected function hrPaginate(Collection $rows, Request $request, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = $perPage ?: (int) $request->input('per_page', $this->hrPerPage);
        $perPage = in_array($perPage, [25, 50, 100, 200], true) ? $perPage : $this->hrPerPage;
        $page = max(1, (int) $request->input('page', 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );
    }

    /** دقائق ← «س:د» */
    protected function hm(int $minutes): string
    {
        return $minutes > 0 ? sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60) : '—';
    }
}
