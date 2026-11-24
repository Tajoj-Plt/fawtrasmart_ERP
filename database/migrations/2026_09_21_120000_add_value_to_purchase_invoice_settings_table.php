<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عمودُ قيمةٍ لإعدادات المشتريات.
 *
 * الجدولُ كان مفاتيحَ تشغيل/إطفاء فقط (`is_active`). وإعدادُ «أقسام
 * موظّفي المشتريات» يحمل قائمةَ معرّفات لا حالةً — فيُحفَظ في هذا العمود
 * نصّاً JSON على صفّ مفتاحه نفسِه، لا في جدولٍ جديد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_invoice_settings', 'value')) {
            Schema::table('purchase_invoice_settings', function (Blueprint $table) {
                $table->text('value')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_invoice_settings', 'value')) {
            Schema::table('purchase_invoice_settings', function (Blueprint $table) {
                $table->dropColumn('value');
            });
        }
    }
};
