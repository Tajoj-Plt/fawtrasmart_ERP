<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * زر «تعيين مركز التكلفة» في شاشة عرض فاتورة الشراء كان يوجّه لصفحة قيد
 * التطوير بلا أثر — لا عمود يحمل الاختيار. العمود مشترك بين أنواع
 * `purchase_invoices` الأربعة (nullable)، ويُفعَّل الاستخدام لفواتير
 * الشراء وحدها من المتحكّم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_invoices', 'cost_center_id')) {
                $table->unsignedBigInteger('cost_center_id')->nullable()->after('account_id');
            }
            if (! SchemaGuard::hasForeignKey('purchase_invoices', 'purchase_invoices_cost_center_id_foreign')) {
                $table->foreign('cost_center_id')->references('id')->on('cost_centers')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });
    }
};
