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
            if (! Schema::hasColumn('job_roles', 'sales_view_assigned_client_invoices')) {
                $table->tinyInteger('sales_view_assigned_client_invoices')->default(0)->after('sales_edit_delete_own_return_invoices');
            }
            if (! Schema::hasColumn('job_roles', 'sales_edit_delete_assigned_client_invoices')) {
                $table->tinyInteger('sales_edit_delete_assigned_client_invoices')->default(0)->after('sales_view_assigned_client_invoices');
            }
            if (! Schema::hasColumn('job_roles', 'sales_add_payments_assigned_client')) {
                $table->tinyInteger('sales_add_payments_assigned_client')->default(0)->after('sales_edit_delete_assigned_client_invoices');
            }
            if (! Schema::hasColumn('job_roles', 'client_follow_up_add_notes_assigned_client')) {
                $table->tinyInteger('client_follow_up_add_notes_assigned_client')->default(0)->after('sales_add_payments_assigned_client');
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
                'sales_view_assigned_client_invoices',
                'sales_edit_delete_assigned_client_invoices',
                'sales_add_payments_assigned_client',
                'client_follow_up_add_notes_assigned_client',
            ]);
        });
    }
};
