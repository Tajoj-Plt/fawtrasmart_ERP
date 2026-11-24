<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collections_target_settlements')) {
            Schema::create('collections_target_settlements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('collections_target_id');
                $table->unsignedBigInteger('employee_id'); // employees.id (مطابق لـ sales_target_settlements)
                $table->decimal('commission_amount', 15, 2);
                $table->date('from_date');
                $table->date('to_date');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('settled_by');
                $table->boolean('is_settled')->default(true);
                $table->unsignedBigInteger('salary_slip_id')->nullable();
                $table->date('settled_date')->nullable();
                $table->decimal('settled_amount', 15, 2)->nullable();
                $table->boolean('paid_directly')->default(false);
                $table->unsignedBigInteger('direct_journal_entry_id')->nullable();
                $table->timestamp('direct_paid_at')->nullable();
                $table->timestamps();

                $table->unique(['collections_target_id', 'from_date', 'to_date'], 'uniq_coll_target_period');
                $table->foreign('collections_target_id', 'fk_coll_settle_target')
                      ->references('id')->on('collections_targets')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collections_target_settlements');
    }
};
