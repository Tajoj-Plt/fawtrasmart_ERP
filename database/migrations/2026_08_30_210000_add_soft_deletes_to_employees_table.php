<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أرشفةُ الموظف بدل حذفه.
 *
 * **ما كان — وهو أخطرُ ممّا يبدو**
 *
 * `EmployeeController::delete()` ينادي `$employee->delete()` حذفاً صلباً
 * على جدولٍ تشير إليه **إحدى وثلاثون علاقة**:
 *
 *   • **خمسٌ وعشرون منها `ON DELETE CASCADE`** — فحذفُ موظفٍ واحد يمحو
 *     معه: عقودَه، وخصمياتِه، وطلباتِ سلفه، وسجلَّ حضوره كاملاً،
 *     وتسوياتِ أهدافه (`sales_target_settlements`)، وحركاتِ نقاطه
 *     ومكافآته وإنجازاته، وربطَه بالعملاء.
 *
 *   • وفيها `employees.direct_manager_id` — **فحذفُ مديرٍ يحذف مرؤوسيه،
 *     ومرؤوسيهم، إلى آخر الشجرة**. نقرةٌ واحدة تمحو قسماً بأكمله.
 *
 *   • **وخمسٌ `SET NULL`** — منها `invoices.employee_id`: تفقد الفواتيرُ
 *     مندوبَها فلا يُعرف من باع.
 *
 *   • و`salary_slips` و`payroll_runs` و`salary_advances` و`users` **بلا
 *     مفتاحٍ أجنبي أصلاً** — فتبقى صفوفُها تشير إلى موظفٍ لا وجود له.
 *
 * ولا شيء من ذلك يظهر للمستخدم: يضغط «حذف» فيُقال له «تم بنجاح».
 *
 * **ما صار**
 *
 * `deleted_at` يجعل `$employee->delete()` تحديثاً لا حذفاً — فلا يُطلق
 * أيّاً من الشلّالات الخمسة والعشرين، ولا يُيتّم صفّاً، ولا يُفرّغ عمودَ
 * مندوبٍ في فاتورة. والموظفُ يختفي من كل استعلامٍ تلقائياً (نطاقُ
 * `SoftDeletes` العام)، ويُستردّ بحاله إن لزم.
 *
 * **ولا يُمَسّ شيءٌ من تلك العلاقات**: تبقى كما هي لمن يحذف من القاعدة
 * مباشرةً — وهو ما لا تفعله الشاشة بعد اليوم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('employees', 'deleted_at')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employees', 'deleted_at')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
