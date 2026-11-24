<?php

use App\Models\ClientPermission;
use App\Support\ClientPortalPermissions;
use Illuminate\Database\Migrations\Migration;

/**
 * صفوفُ المفاتيح الجديدة في «صلاحيات بوابة العميل».
 *
 * شاشةُ الحفظ تُحدّث الصفوفَ الموجودة وحدَها (`ClientPermission::all()`)،
 * فمفتاحٌ في الكتالوج بلا صفٍّ **لا يمكن تفعيله** — تعرضه الشاشةُ موسوماً
 * ولا يُحفَظ. فالصفّان يُنشآن هنا **مطفأين**: البوابةُ تُفتح بقرارٍ لا بترحيل.
 *
 * وكلُّ المفاتيح تُمشَّط لا الجديدان وحدهما، فلو غاب صفُّ مفتاحٍ قديمٍ على
 * قاعدةٍ ما استوى الجدولُ مع الكتالوج هنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ClientPortalPermissions::all() as $key => $definition) {
            ClientPermission::firstOrCreate(
                ['key' => $key],
                ['name' => $definition['label'], 'is_active' => false],
            );
        }

        ClientPortalPermissions::forget();
    }

    public function down(): void
    {
        ClientPermission::whereIn('key', ['submit_payment_notice', 'book_reservations'])->delete();

        ClientPortalPermissions::forget();
    }
};
