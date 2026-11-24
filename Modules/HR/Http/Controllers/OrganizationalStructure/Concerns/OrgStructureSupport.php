<?php

namespace Modules\HR\Http\Controllers\OrganizationalStructure\Concerns;

use App\Models\Employee;
use App\Models\Log as ModelsLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * ما تتقاسمه شاشاتُ الهيكل التنظيمي الأربع (الأقسام، المسمّيات، المستويات،
 * الأنواع): عدُّ الموظفين داخل نطاق الرؤية، وحارسُ الحذف، وسجلُّ النشاط.
 *
 * ══════════════════ لماذا حارسُ الحذف؟ ══════════════════
 *
 * مفاتيحُ الربط إلى هذه الجداول في قاعدة البيانات كلُّها `ON DELETE CASCADE`:
 * حذفُ قسمٍ كان **يحذف حذفاً نهائياً** صفوفَ موظفيه في `employees` (متخطّياً
 * الأرشفة `SoftDeletes`) ومسمّياته الوظيفية — وبها عقودَ حامليها — ومسيراتِ
 * رواتبه. وكانت الشاشة تقول «الحذفُ لا يفحص من يتبعه، فتبقى الملفّات تشير
 * إلى مرجعٍ معدوم»، والواقعُ أسوأ: الملفّاتُ نفسها تختفي. فالحذفُ الآن
 * يُرفَض ما دام للسجلّ تابعٌ في أيٍّ من تلك الجداول، والتعطيلُ هو البديل.
 */
trait OrgStructureSupport
{
    use ScopesToAccessibleEmployees;

    /**
     * عددُ ما يشير إلى السجلّ في كلّ جدولٍ تابع — غيرُ الصفريّ وحده.
     *
     * يُعَدّ من الجداول مباشرةً (لا عبر النماذج) ليشمل الموظفين المؤرشفين:
     * `CASCADE` يحذفهم أيضاً.
     *
     * @param  array<string, array{0:string,1:string}>  $refs  ['التسمية' => ['الجدول', 'العمود']]
     * @return array<string, int>
     */
    protected function orgUsage(array $refs, int $id): array
    {
        $usage = [];

        foreach ($refs as $label => [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = DB::table($table)->where($column, $id)->count();

            if ($count > 0) {
                $usage[$label] = $count;
            }
        }

        return $usage;
    }

    /** رسالةُ رفض الحذف من ناتج `orgUsage()`. */
    protected function usageMessage(string $subject, array $usage): string
    {
        $parts = collect($usage)->map(fn ($count, $label) => $label.' ('.$count.')')->implode('، ');

        return 'لا يُحذف '.$subject.' لأنه مستعمَل في: '.$parts.'. عطّله بدلاً من ذلك، أو انقل ما يتبعه أوّلاً.';
    }

    /** استعلامُ موظفين مقيَّدٌ بنطاق رؤية المستخدم وفرع الشريط العلوي. */
    protected function scopedEmployeeQuery(): Builder
    {
        return $this->scopeToEmployees(Employee::query(), 'employees.id');
    }

    /**
     * استعلامٌ فرعيٌّ يعدّ موظفي النطاق المنسوبين إلى صفّ الجدول الأب —
     * يُضاف بـ`addSelect` فيصير عموداً في نتيجة القائمة.
     */
    protected function scopedEmployeeCountSubquery(string $employeeColumn, string $parentKey): Builder
    {
        return $this->scopedEmployeeQuery()
            ->selectRaw('count(*)')
            ->whereColumn('employees.'.$employeeColumn, $parentKey);
    }

    /** موظفو النطاق المنسوبون إلى السجلّ — لجدول شاشة العرض. */
    protected function scopedMembers(string $employeeColumn, int $id, int $perPage = 15)
    {
        return $this->scopedEmployeeQuery()
            ->with(['job_title:id,name', 'department:id,name', 'branch:id,name'])
            ->where('employees.'.$employeeColumn, $id)
            ->orderBy('first_name')
            ->paginate($perPage, ['*'], 'emp_page')
            ->withQueryString();
    }

    /** سجلُّ النشاط مجمَّعاً باليوم. */
    protected function activityLogs(string $type, int $id)
    {
        return ModelsLog::where('type', $type)
            ->where('type_id', $id)
            ->with('user.branch')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));
    }

    protected function logActivity(string $type, int $id, string $description): void
    {
        ModelsLog::create([
            'type' => $type,
            'type_id' => $id,
            'type_log' => 'log',
            'description' => $description,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * إحصاءاتُ رأس القائمة: الإجمالي والنشط وغير النشط (0 = نشط في هذه الجداول
     * الأربعة)، وموظفو النطاق المنسوبون إلى أيّ سجلّ.
     *
     * @return array{total:int, active:int, inactive:int, employees:int}
     */
    protected function listStats(string $modelClass, string $employeeColumn): array
    {
        $byStatus = $modelClass::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[0] ?? 0),
            'inactive' => (int) $byStatus->except([0])->sum(),
            'employees' => $this->scopedEmployeeQuery()->whereNotNull('employees.'.$employeeColumn)->count(),
        ];
    }
}
