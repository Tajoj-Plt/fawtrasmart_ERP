<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * دورةُ حياة السلفة: الاعتمادُ غيرُ الصرف، والتسويةُ غيرُ الإلغاء.
 *
 * كان الاعتمادُ يصرف المالَ في الخطوة نفسها، فلا حالةَ «معتمدة بانتظار
 * الصرف»؛ وكان إلغاءُ الموظف لطلبه يُكتب «مرفوضاً» فيختلط بقرار المعتمِد؛
 * وكانت «تسوية» السلفة تُغلقها وكأنها أُلغيت. والأعمدةُ هنا تفصل الثلاثة:
 *
 *   • `disbursed_at` و`disbursement_journal_id` — متى خرج المال وبأيّ قيد.
 *     السلفةُ المعتمدةُ بلا صرفٍ لا تُقتطع من الراتب: خطّةُ الرواتب تشترط
 *     قيدَ صرفٍ على حساب سلف الموظف.
 *   • حالةُ `cancelled` وأعمدتُها — الإلغاءُ عمليةٌ مستقلّة لها قيدُها
 *     العكسيّ إن كان المالُ قد خرج.
 *   • `approver_user_id` — المعتمِدُ الذي اختاره الموظف عند الطلب.
 *
 * وفي نظام السلف القديم (`salary_advances`) لم يكن عمودُ حالةٍ أصلاً، مع
 * أن شاشته تُصفّي به — فيُضاف مع أعمدة الإلغاء.
 *
 * و`payroll_settings.advance_approver_department_ids` — الأقسامُ التي
 * يُختار منها «الموظف المعتمد» في طلب السلفة.
 *
 * والسلفُ المعتمدةُ القائمة كلُّها صُرفت يوم اعتُمدت (كان الاعتمادُ صرفاً)،
 * فيُملأ لها `disbursed_at` من قيدها لا من افتراض.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_advance_requests')) {
            Schema::table('salary_advance_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_advance_requests', 'approver_user_id')) {
                    $table->unsignedBigInteger('approver_user_id')->nullable()->after('status');
                }
                if (! Schema::hasColumn('salary_advance_requests', 'disbursed_at')) {
                    $table->dateTime('disbursed_at')->nullable()->after('treasury_id');
                }
                if (! Schema::hasColumn('salary_advance_requests', 'disbursed_by')) {
                    $table->unsignedBigInteger('disbursed_by')->nullable()->after('disbursed_at');
                }
                if (! Schema::hasColumn('salary_advance_requests', 'disbursement_journal_id')) {
                    $table->unsignedBigInteger('disbursement_journal_id')->nullable()->after('disbursed_by');
                }
                if (! Schema::hasColumn('salary_advance_requests', 'cancelled_at')) {
                    $table->dateTime('cancelled_at')->nullable();
                }
                if (! Schema::hasColumn('salary_advance_requests', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable();
                }
                if (! Schema::hasColumn('salary_advance_requests', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable();
                }
                if (! Schema::hasColumn('salary_advance_requests', 'cancellation_journal_id')) {
                    $table->unsignedBigInteger('cancellation_journal_id')->nullable();
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE salary_advance_requests MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");

                // السلفُ المعتمدةُ القائمة صُرفت عند اعتمادها — يُقرأ قيدُها
                DB::statement("
                    UPDATE salary_advance_requests r
                    JOIN journal_entries je
                      ON je.reference_number IN (CONCAT('ADV-REQ-', r.id), CONCAT('ADV-ISSUE-', r.id))
                    SET r.disbursed_at = COALESCE(r.approved_date, je.created_at),
                        r.disbursed_by = r.approved_by,
                        r.disbursement_journal_id = je.id
                    WHERE r.status = 'approved' AND r.disbursed_at IS NULL
                ");
            }
        }

        if (Schema::hasTable('salary_advances')) {
            Schema::table('salary_advances', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_advances', 'status')) {
                    $table->string('status', 20)->default('active')->after('pay_from_salary');
                }
                if (! Schema::hasColumn('salary_advances', 'cancelled_at')) {
                    $table->dateTime('cancelled_at')->nullable();
                }
                if (! Schema::hasColumn('salary_advances', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable();
                }
                if (! Schema::hasColumn('salary_advances', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable();
                }
                if (! Schema::hasColumn('salary_advances', 'cancellation_journal_id')) {
                    $table->unsignedBigInteger('cancellation_journal_id')->nullable();
                }
            });

            // المكتملةُ تُوسَم بما هي عليه — لا تبقى «نشطة» بعد سداد آخر قسط
            DB::table('salary_advances')
                ->whereColumn('paid_installments', '>=', 'total_installments')
                ->where('total_installments', '>', 0)
                ->update(['status' => 'completed']);
        }

        if (Schema::hasTable('payroll_settings') && ! Schema::hasColumn('payroll_settings', 'advance_approver_department_ids')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                $table->text('advance_approver_department_ids')->nullable();
            });
        }
    }

    public function down(): void
    {
        // تُترك الأعمدة: «يُتقاعَد ولا يُحذف» — وحالةُ «ملغاة» قد صارت في بيانات
    }
};
