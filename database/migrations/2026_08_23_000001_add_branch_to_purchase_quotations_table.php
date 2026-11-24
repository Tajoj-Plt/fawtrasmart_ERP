<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط طلبات عروض الأسعار بالفروع — بنفس منطق أوامر الشراء
     * (2026_08_23_000000_add_branch_to_purchase_orders_table).
     *
     * الفرع يُجمَّد وقت الإنشاء بدل الاعتماد على فرع الموظف الحالي، تفادياً
     * لعيب «الفرع الحي» الذي أُصلح سابقاً في مستندات المبيعات
     * (انظر App\Traits\BelongsToBranch).
     */
    public function up(): void
    {
        Schema::table('purchase_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_quotations', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('updated_by');
            }
            if (! SchemaGuard::hasForeignKey('purchase_quotations', 'purchase_quotations_branch_id_foreign')) {
                $table->foreign('branch_id')->references('id')->on('branches')
                    ->nullOnDelete()->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_quotations', function (Blueprint $table) {
            $table->dropForeign('purchase_quotations_branch_id_foreign');
            $table->dropColumn('branch_id');
        });
    }
};
