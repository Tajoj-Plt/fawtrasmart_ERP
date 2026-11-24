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
            if (! Schema::hasColumn('job_roles', 'sales_add_return_invoice')) {
                $table->tinyInteger('sales_add_return_invoice')->default(0)->after('sales_view_own_credit_notices');
            }
            if (! Schema::hasColumn('job_roles', 'sales_view_all_return_invoices')) {
                $table->tinyInteger('sales_view_all_return_invoices')->default(0)->after('sales_add_return_invoice');
            }
            if (! Schema::hasColumn('job_roles', 'sales_view_own_return_invoices')) {
                $table->tinyInteger('sales_view_own_return_invoices')->default(0)->after('sales_view_all_return_invoices');
            }
            if (! Schema::hasColumn('job_roles', 'sales_edit_delete_all_return_invoices')) {
                $table->tinyInteger('sales_edit_delete_all_return_invoices')->default(0)->after('sales_view_own_return_invoices');
            }
            if (! Schema::hasColumn('job_roles', 'sales_edit_delete_own_return_invoices')) {
                $table->tinyInteger('sales_edit_delete_own_return_invoices')->default(0)->after('sales_edit_delete_all_return_invoices');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            $table->dropColumn([
                'sales_add_return_invoice',
                'sales_view_all_return_invoices',
                'sales_view_own_return_invoices',
                'sales_edit_delete_all_return_invoices',
                'sales_edit_delete_own_return_invoices',
            ]);
        });
    }
};
