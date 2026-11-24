<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلبُ عملٍ إضافيّ باعتمادٍ مسبق.
 *
 * الدقائقُ تُحسب وتُسجَّل دائماً في `attendance_days.overtime_minutes` —
 * ذلك واقعةٌ لا تُمحى. لكن تحوُّلَها إلى **بندِ أجرٍ على القسيمة** يصير
 * مشروطاً باعتمادٍ سابق، حين يُفعَّل المفتاح.
 *
 * وهذا يفصل «ما وقع» عن «ما يُصرف» — وهو نفسُ مبدأ دورة تصحيح الحضور:
 * الأثرُ المالي لا يُنشئه ختمٌ آليّ، بل قرارٌ بشريٌّ فوقه.
 *
 * و`approved_minutes` مستقلٌّ عن `requested_minutes` عمداً: المعتمِد قد
 * يُقرّ ساعتين ممّن طلب أربعاً. والاعتمادُ سقفٌ لا مِنحة — إن عمل الموظفُ
 * أقلَّ ممّا اعتُمِد صُرف ما عمله فقط (انظر `OvertimePayService`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('overtime_requests')) {
            Schema::create('overtime_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id')->index();

                /*
                    يومٌ واحدٌ لكل طلب لا مدى: الاعتمادُ يُقابَل بصفّ يومٍ
                    بعينه في `attendance_days`، ومدىً يمتدّ أسبوعاً يجعل
                    توزيعَ السقف على أيامه اجتهاداً لا قاعدة.
                */
                $table->date('overtime_date');

                $table->unsignedSmallInteger('requested_minutes');
                $table->unsignedSmallInteger('approved_minutes')->nullable()
                    ->comment('سقفُ الدقائق المعتمَدة — قد يقلّ عن المطلوب');

                $table->string('status', 16)->default('pending')->index();
                $table->text('reason')->nullable();

                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('rejection_reason', 255)->nullable();
                $table->text('admin_notes')->nullable();

                $table->timestamps();

                // طلبٌ واحدٌ نافذ لكل موظفٍ ويوم — يُحرَس في الخدمة أيضاً
                $table->index(['employee_id', 'overtime_date'], 'idx_ot_req_emp_date');
            });
        }

        Schema::table('payroll_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_settings', 'overtime_requires_approval')) {
                /*
                    معطَّلٌ افتراضياً: تفعيلُه يوقف صرفَ كلِّ إضافيٍّ بلا طلب
                    معتمَد، وذلك قرارُ سياسةٍ لا أثرُ ترحيلِ هجرة.
                */
                $table->boolean('overtime_requires_approval')->default(false)
                    ->after('overtime_enabled')
                    ->comment('لا يُصرف أجرٌ إضافي إلا بطلبٍ معتمَد');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');

        Schema::table('payroll_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_settings', 'overtime_requires_approval')) {
                $table->dropColumn('overtime_requires_approval');
            }
        });
    }
};
