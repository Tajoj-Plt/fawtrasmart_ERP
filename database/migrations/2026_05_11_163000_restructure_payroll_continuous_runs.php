<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * إعادة هيكلة نظام قسائم الرواتب - النسخة 2 (الدورات المستمرة):
 *
 *   salary_slips       = اتفاق راتب مستمر (slip دائم)
 *   payroll_runs       = كل عملية صرف شهرية (سجل مستقل)
 *   payroll_run_items  = بنود كل عملية صرف (استحقاقات/خصومات/سلف)
 *
 * يتم:
 *   1) نقل حقول الصرف من salary_slips (التي أُضيفت سابقاً) إلى payroll_runs
 *   2) إضافة حقول دورة حياة slip (start_date, end_date, frequency, scheduled_payment_day, approved_*, stopped_*)
 *   3) إنشاء جدولي payroll_runs و payroll_run_items
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============== 1) حذف حقول الصرف من salary_slips (انتقلت إلى payroll_runs) ==============
        Schema::table('salary_slips', function (Blueprint $table) {
            $cols = collect(Schema::getColumnListing('salary_slips'))->toArray();
            foreach ([
                'disbursed', 'disbursed_at', 'disbursed_by',
                'expense_id', 'journal_entry_id',
                'scheduled_payment_date',
                'total_advances_deducted', 'total_deductions_applied',
            ] as $col) {
                if (in_array($col, $cols)) {
                    $table->dropColumn($col);
                }
            }
        });

        // ============== 2) إضافة حقول دورة الحياة لـ salary_slips ==============
        Schema::table('salary_slips', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_slips', 'start_date')) {
                $table->date('start_date')->nullable()->after('basic_amount')
                    ->comment('بداية القسيمة - يستخدم لتوليد الـ payroll_runs');
            }
            if (!Schema::hasColumn('salary_slips', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date')
                    ->comment('نهاية القسيمة - من العقد أو يدوي. NULL = مفتوحة');
            }
            if (!Schema::hasColumn('salary_slips', 'frequency')) {
                $table->string('frequency', 20)->default('monthly')->after('end_date')
                    ->comment('monthly | weekly');
            }
            if (!Schema::hasColumn('salary_slips', 'scheduled_payment_day')) {
                $table->tinyInteger('scheduled_payment_day')->nullable()->after('frequency')
                    ->comment('يوم الصرف في الشهر (1-28). NULL = من الموظف أو الافتراضي');
            }
            if (!Schema::hasColumn('salary_slips', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('salary_slips', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('salary_slips', 'stopped_at')) {
                $table->timestamp('stopped_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('salary_slips', 'stopped_by')) {
                $table->unsignedBigInteger('stopped_by')->nullable()->after('stopped_at');
            }
            if (!Schema::hasColumn('salary_slips', 'stop_reason')) {
                $table->text('stop_reason')->nullable()->after('stopped_by');
            }
        });

        // ============== 3) جدول payroll_runs ==============
        if (!Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('salary_slip_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('contract_id')->nullable();

                // الفترة
                $table->date('period_from');
                $table->date('period_to');
                $table->date('scheduled_date')->comment('متى يجب صرف هذا الـ run');

                // المبالغ
                $table->decimal('basic_amount', 15, 2)->default(0);
                $table->decimal('total_additions', 15, 2)->default(0);
                $table->decimal('total_deductions', 15, 2)->default(0);
                $table->decimal('total_advances_deducted', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);

                // الحالة
                $table->string('status', 20)->default('pending')
                    ->comment('pending | disbursed | cancelled');
                $table->string('trigger_source', 20)->default('cron')
                    ->comment('manual | cron');

                // الصرف
                $table->timestamp('disbursed_at')->nullable();
                $table->unsignedBigInteger('disbursed_by')->nullable();
                $table->unsignedBigInteger('expense_id')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();

                $table->unsignedBigInteger('treasury_id')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['salary_slip_id', 'period_from', 'period_to'], 'uniq_slip_period');
                $table->index(['status', 'scheduled_date'], 'idx_run_status_sched');
                $table->index(['employee_id', 'status'], 'idx_run_emp_status');
                $table->foreign('salary_slip_id', 'fk_run_slip')
                    ->references('id')->on('salary_slips')->cascadeOnDelete();
            });
        }

        // ============== 4) جدول payroll_run_items ==============
        if (!Schema::hasTable('payroll_run_items')) {
            Schema::create('payroll_run_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payroll_run_id');
                $table->string('item_type', 20)->comment('addition | deduction | advance');
                $table->string('source_table', 64)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('name');
                $table->decimal('amount', 15, 2);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['payroll_run_id', 'item_type'], 'idx_run_item_type');
                $table->foreign('payroll_run_id', 'fk_run_item_run')
                    ->references('id')->on('payroll_runs')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_items');
        Schema::dropIfExists('payroll_runs');

        Schema::table('salary_slips', function (Blueprint $table) {
            foreach ([
                'start_date', 'end_date', 'frequency', 'scheduled_payment_day',
                'approved_at', 'approved_by', 'stopped_at', 'stopped_by', 'stop_reason',
            ] as $col) {
                if (Schema::hasColumn('salary_slips', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
