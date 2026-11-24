<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رابط الفاتورة بالاستلام الذي وُلدت منه — راحةٌ على مستوى الرأس، توفّر
 * الرجوع إلى `invoice_items.purchase_receiving_item_id` لمعرفة أصل
 * الفاتورة. لا يمسّ `reference_id`: يبقى يشير إلى أمر الشراء الأصل كما
 * كان، ويحتمل الآن أكثر من فاتورة لكل أمر (استلامات متعددة) بدل واحدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_invoices', 'purchase_receiving_id')) {
                $table->foreignId('purchase_receiving_id')->nullable()
                    ->after('reference_id')
                    ->constrained('purchase_receivings')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_receiving_id');
        });
    }
};
