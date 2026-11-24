<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('leave_permissions')) {
            Schema::create('leave_permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('employee_id')->index('leave_permissions_employee_id_foreign');
                // التعليقان كانا متبادلَين — والشاشةُ والموديل وحسابُ اليوم
                // كلُّها تقرأ `leave_type` نوعاً للإذن و`type` نوعاً للإجازة.
                $table->tinyInteger('type')->default(1)->comment('1-اجازة اعتيادية 2-اجازة عرضية');
                $table->tinyInteger('leave_type')->default(1)->comment('1-الوصول المتأخر 2-الانصراف المبكر');
                $table->date('start_date');
                $table->date('end_date');
                $table->date('submission_date')->nullable();
                $table->text('notes')->nullable();
                $table->string('attachments')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_permissions');
    }
};
