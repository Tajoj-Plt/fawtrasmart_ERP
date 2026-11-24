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
        if (! Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('document_type'); // sales_invoice, purchase_invoice, receipt_voucher, payment_voucher, quote, warehouse_permit_issue, warehouse_permit_receipt, inventory_transfer, inventory_adjustment, manufacturing_order, contract, payroll, salary_slip, leave_request, attendance_sheet
                $table->string('template_type')->default('ready'); // ready, custom
                $table->string('paper_size')->default('A4'); // A4, Thermal_80mm, Thermal_58mm, custom
                $table->double('width')->nullable();
                $table->double('height')->nullable();
                $table->string('orientation')->default('portrait'); // portrait, landscape
                $table->longText('content');
                $table->text('styles')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                // Indexes
                $table->index('document_type');
                $table->index('branch_id');
                $table->index('user_id');

                // Foreign Keys
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
