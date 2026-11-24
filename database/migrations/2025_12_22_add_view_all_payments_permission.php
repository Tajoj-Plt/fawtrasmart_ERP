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
            if (! Schema::hasColumn('job_roles', 'sales_view_all_payments')) {
                $table->boolean('sales_view_all_payments')->default(false)->after('sales_edit_payment_options');
            }
            if (! Schema::hasColumn('job_roles', 'sales_create_invoice_from_any_location')) {
                $table->boolean('sales_create_invoice_from_any_location')->default(false)->after('sales_view_all_payments');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            $table->dropColumn('sales_view_all_payments');
            $table->dropColumn('sales_create_invoice_from_any_location');
        });
    }
};
