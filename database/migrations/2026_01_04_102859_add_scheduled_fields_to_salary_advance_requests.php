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
        Schema::table('salary_advance_requests', function (Blueprint $table) {
            // نوع السلفة (عادية أو مجدولة)
            if (! Schema::hasColumn('salary_advance_requests', 'advance_type')) {
                $table->enum('advance_type', ['normal', 'scheduled'])->default('normal')->after('amount');
            }
            
            // عدد الأقساط
            if (! Schema::hasColumn('salary_advance_requests', 'installments_count')) {
                $table->integer('installments_count')->nullable()->after('advance_type');
            }
            
            // قيمة القسط الشهري
            if (! Schema::hasColumn('salary_advance_requests', 'installment_amount')) {
                $table->decimal('installment_amount', 10, 2)->nullable()->after('installments_count');
            }
            
            // شهر بداية الخصم
            if (! Schema::hasColumn('salary_advance_requests', 'start_month')) {
                $table->string('start_month', 7)->nullable()->after('installment_amount'); // Format: YYYY-MM
            
                // عدد الأقساط المسددة
                if (! Schema::hasColumn('salary_advance_requests', 'paid_installments')) {
                    $table->integer('paid_installments')->default(0)->after('start_month');
                }
            }
            
            // عدد الأقساط المتبقية
            if (! Schema::hasColumn('salary_advance_requests', 'remaining_installments')) {
                $table->integer('remaining_installments')->nullable()->after('paid_installments');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_advance_requests', function (Blueprint $table) {
            $table->dropColumn([
                'advance_type',
                'installments_count',
                'installment_amount',
                'start_month',
                'paid_installments',
                'remaining_installments'
            ]);
        });
    }
};
