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
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_invoices', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id');

                // إذا عندك جدول للفروع، ممكن تضيف علاقة خارجية
                // $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_invoices', 'branch_id')) {
                $table->dropColumn('branch_id');
            }
        });
    }
};
