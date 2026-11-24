<?php

namespace Modules\HR\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JopTitle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Support\HrDashboardService;

/**
 * لوحةُ تحكّم الموظفين — على تصميم لوحة العملاء ومنهجها.
 *
 * **ما كانت عليه**: أربعةَ عشرَ رقماً تُحسب في المتحكّم مباشرةً، لا يفتح
 * منها رقمٌ قائمةَ موظفيه، ولا يقول رقمٌ من أين قُرئ. و«الحضور اليوم» ثلاثةُ
 * أعدادٍ من `attendance_days` وحدها — فمن لم يُسجَّل له صفٌّ أصلاً لا يظهر
 * في أيّ خانة، فيبدو اليومُ مكتملاً وفيه عشرون موظفاً لم يسجّلوا.
 *
 * **ما صارت**: الحسابُ كلُّه في {@see HrDashboardService} على قاعدة لوحة
 * العملاء — كلُّ مقياسٍ خريطةٌ يُعَدُّ منها، و`segment()` يقرأ الخريطةَ
 * نفسَها فتفتح كلُّ بطاقةٍ موظفيها بالضبط. ولكلّ شريحةٍ سطرُ تعريفٍ يُعرَض
 * مع القائمة.
 *
 * والفترةُ على الحركة وحدها (تعيين، أيام حضور، صرفات، طلبات) لا على قاعدة
 * الموظفين — فتغييرُها لا يُنقص عدّةَ القوى العاملة.
 */
class DashboardController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:hr_dashboard.index', ['only' => ['index', 'segment']]);
    }

    public function index(Request $request)
    {
        [$from, $to, $period] = $this->resolvePeriod($request);

        $service = new HrDashboardService(
            $this->accessibleEmployeeIds(),
            $from,
            $to,
            $this->filters($request)
        );

        return view('hr::dashboard.index', [
            'data' => $service->build(),
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'filters' => $this->filters($request),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'jobTitles' => JopTitle::orderBy('name')->get(['id', 'name']),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'statusOptions' => EmploymentStatus::options(),
        ]);
    }

    /**
     * قائمةُ موظفي شريحةٍ — تُطلَب بـAJAX من بطاقةٍ أو صفٍّ في اللوحة.
     *
     * بفلاتر اللوحة نفسِها: القائمةُ التي تُفتح هي عينُ ما عُدَّ منه الرقم.
     */
    public function segment(Request $request): JsonResponse
    {
        $request->validate(['segment' => 'required|string|max:80']);

        [$from, $to] = $this->resolvePeriod($request);

        $service = new HrDashboardService(
            $this->accessibleEmployeeIds(),
            $from,
            $to,
            $this->filters($request)
        );

        return response()->json($service->drillDown((string) $request->input('segment')));
    }

    /**
     * الفترة — بأزرارٍ سريعةٍ أو مدًى مخصّص.
     *
     * `30` تعني «هذا الشهر» لا ثلاثين يوماً مضت: هي ما يسأله مديرُ الموارد
     * البشرية عن الرواتب والحضور، والشهرُ وحدةُ عملِ هذا القسم.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $period = (string) $request->get('period', '30');

        [$from, $to] = match ($period) {
            '1' => [Carbon::today(), Carbon::today()],
            '7' => [Carbon::today()->subDays(6), Carbon::today()],
            '90' => [Carbon::today()->subDays(89), Carbon::today()],
            '365' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            'prev_month' => [Carbon::now()->subMonthNoOverflow()->startOfMonth(), Carbon::now()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                $request->filled('start_date') ? Carbon::parse($request->get('start_date')) : Carbon::now()->startOfMonth(),
                $request->filled('end_date') ? Carbon::parse($request->get('end_date')) : Carbon::today(),
            ],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };

        // مدًى مقلوبٌ يُقوَّم بدل أن يُعيد صفراً بلا تفسير
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, $period];
    }

    /**
     * فلاترُ الشاشة — معرّفاتٌ صحيحةٌ فحسب.
     *
     * الفارغُ يُسقَط، **وكذلك كلُّ قيمةٍ ليست رقماً موجباً**. فطلبٌ وصل فيه
     * `department_id=null` (نصّاً) كان يُعامَل فلتراً حقيقياً فيُستعلم
     * `where('department_id', 'null')`، فتعود اللوحةُ بلا موظفٍ واحد وتُفتح كلُّ
     * شريحةٍ فارغة. والحارسُ هنا حتّى لو أصلحت الواجهةُ ما تُرسله: العنوانُ
     * يُكتب باليد ويُحفظ في المفضّلات.
     *
     * @return array<string, int>
     */
    private function filters(Request $request): array
    {
        $filters = [];

        foreach (['department_id', 'job_title_id', 'branch_id', 'employee_status'] as $key) {
            $value = $request->get($key);

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '' || ! is_scalar($value) || ! ctype_digit((string) $value) || (int) $value < 1) {
                continue;
            }

            $filters[$key] = (int) $value;
        }

        return $filters;
    }
}
