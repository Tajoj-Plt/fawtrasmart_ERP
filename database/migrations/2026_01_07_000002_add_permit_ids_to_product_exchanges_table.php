<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * كان اسم هذا الملف `2026_01_06_183218_...` — أي قبل يومٍ من هجرة
     * إنشاء `product_exchanges` نفسه (`2026_01_07_000001`). ولمّا كان
     * Laravel يرتّب الهجرات بالاسم، كان `migrate` على قاعدة جديدة يسقط هنا:
     * «الجدول غير موجود». أُعيدت التسمية لتلي الإنشاء.
     *
     * وبما أن إعادة التسمية تجعل القاعدة القائمة تراها هجرةً جديدة
     * فتُشغِّلها من جديد، صار كل تعديل مشروطاً بغياب ما يضيفه.
     */
    public function up(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            // معرفات الإذون المخزنية
            if (! Schema::hasColumn('product_exchanges', 'outgoing_permit_id')) {
                $table->unsignedBigInteger('outgoing_permit_id')->nullable()->after('status')->comment('معرف إذن الصرف - صادر');
                if (! SchemaGuard::hasForeignKey('product_exchanges', 'product_exchanges_outgoing_permit_id_foreign')) {
                    $table->foreign('outgoing_permit_id')->references('id')->on('warehouse_permits')->onDelete('set null');
                }
            }

            if (! Schema::hasColumn('product_exchanges', 'incoming_permit_id')) {
                $table->unsignedBigInteger('incoming_permit_id')->nullable()->after('outgoing_permit_id')->comment('معرف إذن الإضافة - وارد');
                if (! SchemaGuard::hasForeignKey('product_exchanges', 'product_exchanges_incoming_permit_id_foreign')) {
                    $table->foreign('incoming_permit_id')->references('id')->on('warehouse_permits')->onDelete('set null');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->dropForeign(['outgoing_permit_id']);
            $table->dropForeign(['incoming_permit_id']);
            $table->dropColumn(['outgoing_permit_id', 'incoming_permit_id']);
        });
    }
};
