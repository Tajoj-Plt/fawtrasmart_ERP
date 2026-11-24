<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول معاملات التحديات - يسجل كل معاملة نقاط مرتبطة بتحدي معين
     * يوفر سجل تدقيق كامل لمعرفة أي معاملات ساهمت في تقدم كل مشارك
     */
    public function up(): void
    {
        if (! Schema::hasTable('challenge_transactions')) {
            Schema::create('challenge_transactions', function (Blueprint $table) {
                $table->id();
            
                // العلاقات الأساسية
                $table->unsignedBigInteger('challenge_id')->comment('التحدي');
                $table->unsignedBigInteger('challenge_participant_id')->comment('المشارك في التحدي');
                $table->unsignedBigInteger('points_transaction_id')->comment('معاملة النقاط الأصلية');
            
                // بيانات المستخدم
                $table->enum('user_type', ['employee', 'client'])->comment('نوع المستخدم');
                $table->unsignedBigInteger('employee_id')->nullable()->comment('الموظف');
                $table->unsignedBigInteger('client_id')->nullable()->comment('العميل');
            
                // بيانات المعاملة
                $table->string('action_type', 100)->comment('نوع الإجراء');
                $table->integer('progress_value')->default(1)->comment('قيمة التقدم المضافة (1 للعدد، أو المبلغ)');
                $table->integer('progress_before')->default(0)->comment('التقدم قبل هذه المعاملة');
                $table->integer('progress_after')->default(0)->comment('التقدم بعد هذه المعاملة');
                $table->integer('points')->default(0)->comment('نقاط المعاملة الأصلية');
                $table->decimal('amount', 15, 2)->nullable()->comment('المبلغ/السعر إن توفر');
                $table->text('description')->nullable()->comment('وصف/ملاحظة');
                $table->json('metadata')->nullable()->comment('بيانات إضافية (رقم فاتورة، اسم عميل...)');
            
                $table->timestamps();

                // Foreign Keys
                $table->foreign('challenge_id')
                    ->references('id')->on('challenges')
                    ->onDelete('cascade');
            
                $table->foreign('challenge_participant_id')
                    ->references('id')->on('challenge_participants')
                    ->onDelete('cascade');
            
                $table->foreign('points_transaction_id')
                    ->references('id')->on('points_transactions')
                    ->onDelete('cascade');
            
                $table->foreign('employee_id')
                    ->references('id')->on('employees')
                    ->onDelete('cascade');
            
                $table->foreign('client_id')
                    ->references('id')->on('clients')
                    ->onDelete('cascade');

                // Indexes
                $table->index(['challenge_id', 'challenge_participant_id'], 'ct_challenge_participant_idx');
                $table->index(['challenge_id', 'user_type'], 'ct_challenge_user_type_idx');
                $table->index('points_transaction_id', 'ct_points_transaction_idx');
                $table->index('action_type', 'ct_action_type_idx');
                $table->index('created_at', 'ct_created_at_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_transactions');
    }
};
