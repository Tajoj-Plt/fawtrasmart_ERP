<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('target_settings')) {
            Schema::create('target_settings', function (Blueprint $table) {
                $table->id();

                // نوع الهدف: sales أو collections (مرن للتوسع المستقبلي)
                $table->string('target_type', 50)->unique()->index();

                // التفعيل العام
                $table->boolean('enabled')->default(true);

                // طريقة الاحتساب
                // sales: net_sales | gross_sales | paid_only
                // collections: total_collected | bank_only | etc.
                $table->string('calculation_method', 50)->default('net_sales')->nullable();

                // ============= إعدادات العمولة =============
                // حساب العمولة من شجرة الحسابات (Chart of Accounts)
                $table->unsignedBigInteger('commission_account_id')->nullable();

                // صرف العمولة من خزينة الموظف الخاصة (1) أو من خزينة افتراضية (0)
                $table->boolean('pay_from_employee_treasury')->default(false);

                // الخزينة الافتراضية للصرف عند عدم توفر خزينة للموظف
                $table->unsignedBigInteger('default_commission_treasury_id')->nullable();

                // ============= إعدادات التسوية المجدولة =============
                $table->boolean('auto_settlement_enabled')->default(false);

                // التكرار: weekly | monthly | quarterly | yearly
                $table->string('settlement_frequency', 30)->nullable();

                // يوم التنفيذ (1-7 للأسبوع، 1-31 للشهر)
                $table->integer('settlement_day')->nullable();

                // وقت التنفيذ
                $table->time('settlement_time')->default('00:01:00');

                // ============= إعدادات الإشعار والاعتماد =============
                $table->boolean('notify_on_settlement')->default(true);
                $table->boolean('require_approval')->default(false);  // اعتماد إجباري قبل الصرف

                // ============= الحقول المرنة للتوسع المستقبلي =============
                $table->json('extra_settings')->nullable();

                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();

                // FK constraints (soft - بدون cascade)
                $table->foreign('commission_account_id')->references('id')->on('accounts')->nullOnDelete();
                $table->foreign('default_commission_treasury_id')->references('id')->on('accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('target_settings');
    }
};
