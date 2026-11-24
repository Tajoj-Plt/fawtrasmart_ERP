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
        if (! Schema::hasTable('doubtful_debts')) {
            Schema::create('doubtful_debts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
                $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
                $table->decimal('original_balance', 15, 2)->comment('المبلغ الأصلي المديون');
                $table->decimal('provision_amount', 15, 2)->comment('مبلغ المخصص');
                $table->decimal('provision_percentage', 5, 2)->default(50)->comment('نسبة المخصص');
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('set null');
                $table->string('status')->default('pending')->comment('الحالة: pending, collected, written_off');
                $table->decimal('collected_amount', 15, 2)->default(0)->comment('المبلغ المحصل');
                $table->date('collected_date')->nullable()->comment('تاريخ التحصيل');
                $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null')->comment('من قام بالتحصيل');
                $table->text('notes')->nullable()->comment('ملاحظات');
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->index('client_id');
                $table->index('status');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doubtful_debts');
    }
};
