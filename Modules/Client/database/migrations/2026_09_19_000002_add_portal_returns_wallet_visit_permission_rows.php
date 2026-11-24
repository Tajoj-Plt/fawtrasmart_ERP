<?php

use App\Models\ClientPermission;
use App\Support\ClientPortalPermissions;
use Illuminate\Database\Migrations\Migration;

/**
 * صفوفُ مفاتيح «المرتجعات» و«محفظتي» و«طلب زيارة ميدانية».
 *
 * شاشةُ الحفظ تُحدّث الصفوفَ الموجودة وحدَها (`ClientPermission::all()`)،
 * فمفتاحٌ في الكتالوج بلا صفٍّ **لا يمكن تفعيله** — تعرضه الشاشةُ موسوماً
 * ولا يُحفَظ. فالصفوفُ تُنشأ هنا كما فُعل بمفتاحَي الحجز وإشعار السداد.
 *
 * **والثلاثةُ تُفعَّل** خلافاً لسابقتها (طلب المستخدم 2026-09-19: «أريدها
 * ظاهرةً الآن»): ثلاثتُها قراءةٌ أو طلبٌ يراجعه موظّف — لا مالَ يدخل ولا
 * وقتَ مندوبٍ يُحجَز بها — فتفعيلُها لا يفتح باباً يُندَم عليه. ومن أرادها
 * مطفأةً أطفأها من «صلاحيات بوابة العميل».
 *
 * وكلُّ المفاتيح تُمشَّط لا الثلاثةُ وحدَها (بلا لمسِ `is_active` لما وُجد
 * صفُّه أصلاً)، فلو غاب صفُّ مفتاحٍ قديمٍ على قاعدةٍ ما استوى الجدولُ مع
 * الكتالوج هنا.
 */
return new class extends Migration
{
    /** ما يُفعَّل فورَ إنشائه — انظر ترويسة الملف */
    private const ACTIVATED = ['view_returns', 'view_wallet', 'request_field_visit'];

    public function up(): void
    {
        foreach (ClientPortalPermissions::all() as $key => $definition) {
            ClientPermission::firstOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['label'],
                    'is_active' => in_array($key, self::ACTIVATED, true),
                ],
            );
        }

        /* صفٌّ موجودٌ سلفاً (أُنشئ مطفأً بترحيلٍ سابق مشَّط الكتالوج كلَّه)
           لا تلمسه `firstOrCreate` — والمطلوب أن تُرى الشاشاتُ الثلاث الآن،
           فيُرفع `is_active` صراحةً عليها وحدَها. */
        ClientPermission::whereIn('key', self::ACTIVATED)->update(['is_active' => true]);

        ClientPortalPermissions::forget();
    }

    public function down(): void
    {
        ClientPermission::whereIn('key', self::ACTIVATED)->delete();

        ClientPortalPermissions::forget();
    }
};
