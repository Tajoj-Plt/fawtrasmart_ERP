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
        Schema::table('job_roles', function (Blueprint $table) {
            // أضفنا الصلاحيات الجديدة للعمل من أي موقع لكل وحدة
            if (!Schema::hasColumn('job_roles', 'sales_return_invoice_from_any_location')) {
                $table->boolean('sales_return_invoice_from_any_location')->default(false);
            }
            if (!Schema::hasColumn('job_roles', 'sales_credit_note_from_any_location')) {
                $table->boolean('sales_credit_note_from_any_location')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            $table->dropColumn(['sales_return_invoice_from_any_location', 'sales_credit_note_from_any_location']);
        });
    }
};
