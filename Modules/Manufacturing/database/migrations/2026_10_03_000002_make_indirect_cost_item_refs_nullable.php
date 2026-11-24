<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * بندُ التوزيع إمّا قيدٌ وإمّا أمر — لا الاثنان معاً.
 *
 * كان الجدولُ يفرض `restriction_id` و`manufacturing_order_id` في كل سطر،
 * فيُحفظ القيدُ والأمرُ متلازمَين بترتيب ظهورهما: قيدٌ واحد يُوزَّع على
 * ثلاثة أوامر لا يجد إلّا سطراً واحداً، فيُحفظ الأمرُ الأوّل ويضيع الباقيان.
 *
 * الآن سطرٌ لكل قيدٍ وسطرٌ لكل أمر، فالعمودان يقبلان الفراغ. تغييرُ قابليّة
 * الفراغ وحدها — لا نوعَ يتبدّل ولا قيدَ مرجعيّاً يُمسّ.
 */
return new class extends Migration
{
    private const TABLE = 'indirect_cost_items';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function ($table) {
            foreach (['restriction_id', 'manufacturing_order_id'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->unsignedBigInteger($column)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        // لا رجوع: إعادةُ فرض العمودين تردّ السطورَ المحفوظة بالصيغة الجديدة
    }
};
