<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ «فترات التبرير/الإعفاء» — الإصلاح الجذري لمشكلة إعادة طلب تبريرٍ
 * سبق اعتماده. `employee_client_visits.justification` كان تبريراً على
 * صفٍّ واحد (زيارة يوم واحد) لا فترة، ولم يكن هناك أي سجلٍّ يمثّل «هذا
 * الموظف مُعفًى مع هذا العميل من تاريخ كذا إلى تاريخ كذا» بمعزلٍ عن صلاحية
 * المستخدم الحالية — فتغييرُ الصلاحية كان يُعيد فتح الفترة كاملةً.
 *
 * هذا الجدول هو مصدر الحقيقة الوحيد للتغطية الآن: صلاحٌ (`status=approved`)
 * فترةً كاملةً بصرف النظر عمّا يملكه الموظف من صلاحياتٍ حالياً — راجع
 * `App\Models\VisitJustification::coversDate()` و
 * `App\Models\EmployeeClientVisit::needingJustificationQuery()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visit_justifications')) {
            Schema::create('visit_justifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('client_id');
                $table->date('date_from');
                $table->date('date_to');
                $table->string('reason');
                $table->text('notes')->nullable();
                // 0 معلّق، 1 معتمد (يغطّي الزيارات فعلياً)، 2 مرفوض — نفس ترميز
                // employee_client_visits.justification_approved القديم عمداً.
                $table->unsignedTinyInteger('status')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

                $table->index(['employee_id', 'client_id', 'status', 'date_from', 'date_to'], 'visit_justifications_coverage_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_justifications');
    }
};
