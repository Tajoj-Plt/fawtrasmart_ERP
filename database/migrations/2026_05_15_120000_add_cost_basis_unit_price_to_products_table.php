<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سعر التكلفة المعتمد بعد تسوية يدوية (يُعرض ويُستخدم كأساس قبل إعادة الحساب من الحركات).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'cost_basis_unit_price')) {
                $table->decimal('cost_basis_unit_price', 15, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'cost_basis_unit_price')) {
                $table->dropColumn('cost_basis_unit_price');
            }
        });
    }
};
