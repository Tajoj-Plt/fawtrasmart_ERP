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
        if (! Schema::hasTable('journal_entry_invoices')) {
            Schema::create('journal_entry_invoices', function (Blueprint $table) {
                $table->id('link_id');
                $table->unsignedBigInteger('entry_id')->default(0);
                $table->unsignedBigInteger('invoice_id')->default(0);
                $table->enum('invoice_type', ['sale', 'purchase'])->default('sale'); // نوع الفاتورة
                $table->double('allocation_amount')->default(0);
                $table->double('allocation_percentage')->default(0);
                $table->unsignedBigInteger('created_at')->default(0);
            
                // Foreign key للقيد اليومي
                $table->foreign('entry_id')
                      ->references('id')
                      ->on('journal_entries')
                      ->onDelete('cascade');
                  
                // Foreign key للفاتورة حسب النوع
                // Note: We can't create direct foreign keys here because invoice_id can reference
                // either invoices.id or purchase_invoices.id based on invoice_type
                // This constraint is handled at the application level
            
                // Index مركب للأداء
                $table->index(['invoice_type', 'invoice_id']);
                $table->index('entry_id');
                $table->index('invoice_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_invoices');
    }
};