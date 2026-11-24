<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أثر بند الفاتورة على سطر الاستلام الذي فوتره — بلا هذا العمود يستحيل
 * معرفة أي جزءٍ من الاستلام فُوتر وأيّه بقي، فتنكسر الفوترة الجزئية
 * (استلام مئة، فاتورة بستين، فاتورة ثانية بالأربعين الباقية) ويصير
 * ازدواج الفوترة على نفس الكمية ممكناً بلا رادّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'purchase_receiving_item_id')) {
                $table->foreignId('purchase_receiving_item_id')->nullable()
                    ->after('purchase_invoice_id')
                    ->constrained('purchase_receiving_items')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_receiving_item_id');
        });
    }
};
