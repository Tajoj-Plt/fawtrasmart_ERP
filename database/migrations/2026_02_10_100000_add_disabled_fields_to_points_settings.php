<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقول سبب التعطيل وتاريخه لجدول إعدادات النقاط
     */
    public function up(): void
    {
        Schema::table('points_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('points_settings', 'disabled_reason')) {
                $table->text('disabled_reason')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('points_settings', 'disabled_at')) {
                $table->timestamp('disabled_at')->nullable()->after('disabled_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_settings', function (Blueprint $table) {
            $table->dropColumn(['disabled_reason', 'disabled_at']);
        });
    }
};
