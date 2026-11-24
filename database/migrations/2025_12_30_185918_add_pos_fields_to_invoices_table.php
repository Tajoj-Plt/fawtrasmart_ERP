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
        Schema::table('invoices', function (Blueprint $table) {
            // إضافة حقل session_id لربط الفاتورة بجلسة نقطة البيع
            if (!Schema::hasColumn('invoices', 'session_id')) {
                $table->unsignedBigInteger('session_id')->nullable()->after('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'session_id')) {
                $table->dropColumn('session_id');
            }
        });
    }
};
