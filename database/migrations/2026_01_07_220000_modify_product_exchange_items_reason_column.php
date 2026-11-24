<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * تحويل عمود reason من enum إلى foreign key
     */
    public function up(): void
    {
        // MySQL يُسقط فهرسَ العمود ضمناً مع العمود، وSQLite لا يفعل: يعيد بناء
        // الجدول فيجد الفهرس يشير إلى عمودٍ لم يعد موجوداً ويتوقّف. نُسقط
        // الفهرس أولاً صراحةً — سلوكٌ صحيحٌ على المحرّكين معاً.
        $indexes = collect(Schema::getIndexes('product_exchange_items'))->pluck('name')->all();

        Schema::table('product_exchange_items', function (Blueprint $table) use ($indexes) {
            if (in_array('product_exchange_items_reason_index', $indexes, true)) {
                $table->dropIndex('product_exchange_items_reason_index');
            }

            $table->dropColumn('reason');
        });

        Schema::table('product_exchange_items', function (Blueprint $table) {
            // إضافة العمود الجديد كـ foreign key
            if (! Schema::hasColumn('product_exchange_items', 'reason_id')) {
                $table->unsignedBigInteger('reason_id')->nullable()->after('total');
            }
            if (! SchemaGuard::hasForeignKey('product_exchange_items', 'product_exchange_items_reason_id_foreign')) {
                $table->foreign('reason_id')->references('id')->on('exchange_reasons')->onDelete('set null');
            }
            
            // إضافة index
            if (! SchemaGuard::hasIndex('product_exchange_items', 'product_exchange_items_reason_id_index')) {
                $table->index('reason_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_exchange_items', function (Blueprint $table) {
            // حذف العلاقة والعمود الجديد
            $table->dropForeign(['reason_id']);
            $table->dropIndex(['reason_id']);
            $table->dropColumn('reason_id');
        });

        Schema::table('product_exchange_items', function (Blueprint $table) {
            // إرجاع العمود القديم
            $table->enum('reason', ['damaged', 'missing_perfume', 'defective', 'other'])->default('other')->comment('سبب التبديل')->after('total');
        });
    }
};
