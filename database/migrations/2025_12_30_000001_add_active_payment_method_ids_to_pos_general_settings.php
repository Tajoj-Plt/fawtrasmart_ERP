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
        Schema::table('pos_general_settings', function (Blueprint $table) {
            // إضافة حقل JSON لتخزين طرق الدفع المفعلة (مصفوفة من IDs)
            if (!Schema::hasColumn('pos_general_settings', 'active_payment_method_ids')) {
                $table->json('active_payment_method_ids')->nullable()->after('active_payment_method_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_general_settings', 'active_payment_method_ids')) {
                $table->dropColumn('active_payment_method_ids');
            }
        });
    }
};
