<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط بنود طلبات المتجر بإعداد الضريبة الحقيقي — نفس نمط `InvoiceItem`
 * (2026_08_19_190000_add_tax_ids_to_invoice_items_table): عمود `tax` يبقى
 * النسبة المجمَّدة وقت البيع، و`tax_1_id`/`tax_2_id` يحملان الـFK الحقيقي
 * إلى `tax_sittings`. كان `online_order_items` بلا أي FK ضريبي إطلاقاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('online_order_items', 'tax_1_id')) {
                $table->foreignId('tax_1_id')->nullable()->after('tax')
                    ->constrained('tax_sittings')->nullOnDelete();
            }

            if (! Schema::hasColumn('online_order_items', 'tax_2_id')) {
                $table->foreignId('tax_2_id')->nullable()->after('tax_1_id')
                    ->constrained('tax_sittings')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('online_order_items', 'tax_2_id')) {
                $table->dropConstrainedForeignId('tax_2_id');
            }

            if (Schema::hasColumn('online_order_items', 'tax_1_id')) {
                $table->dropConstrainedForeignId('tax_1_id');
            }
        });
    }
};
