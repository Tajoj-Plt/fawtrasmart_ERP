<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('payroll_settings')) {
            Schema::create('payroll_settings', function (Blueprint $table) {
                $table->id();
                // Toggle to determine whether approved salary slips should be emailed to employees
                $table->boolean('send_salary_slips')->default(false);
                // Default template for printing or emailing salary slips (e.g. 'ar' for Arabic, 'en' for English)
                $table->string('default_template')->default('ar');
                // Number of days before contract end to send expiration notifications
                $table->integer('contract_end_notice_period')->nullable();
                // Whether to automatically pay approved sales periods commissions
                $table->boolean('auto_pay_approved_sales_periods')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_settings');
    }
};