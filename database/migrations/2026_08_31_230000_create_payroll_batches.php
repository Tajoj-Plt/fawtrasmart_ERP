<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اعتماد صرفات جملةً (§15 TODO 2.8): مسير صرفات لا قسائم.
 * الصرفة غير المجمّعة تُصرف كما كانت؛ المجمّعة لا تُصرف حتى يُعتمد المسير.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_batches')) {
            Schema::create('payroll_batches', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('payroll_id')->nullable();
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('payroll_runs') && ! Schema::hasColumn('payroll_runs', 'payroll_batch_id')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_runs', 'payroll_batch_id')) {
                    $table->unsignedBigInteger('payroll_batch_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_runs') && Schema::hasColumn('payroll_runs', 'payroll_batch_id')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->dropColumn('payroll_batch_id');
            });
        }

        Schema::dropIfExists('payroll_batches');
    }
};
