<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل تاريخ العمليات على الخصميات.
 *
 * يُسجّل كل عملية: إنشاء، تعديل، اعتماد، رفض، تسوية، إلغاء، تسوية جزئية.
 * يحتفظ بالـ snapshot للتغييرات لمتابعتها.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deduction_histories')) {
            Schema::create('deduction_histories', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('deduction_id');

                // created | updated | approved | rejected | cancelled | settled | partially_settled
                $table->string('action', 40)->index();

                $table->unsignedBigInteger('user_id')->nullable();

                $table->text('description')->nullable();
                $table->json('changes')->nullable();   // snapshot قبل/بعد
                $table->text('notes')->nullable();
                $table->decimal('amount', 14, 2)->nullable(); // مبلغ مرتبط (للتسوية مثلاً)

                $table->timestamps();

                $table->index('deduction_id');

                $table->foreign('deduction_id')->references('id')->on('deductions')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_histories');
    }
};
