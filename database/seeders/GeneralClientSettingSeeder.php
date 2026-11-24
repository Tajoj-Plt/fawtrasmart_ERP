<?php

namespace Database\Seeders;

use App\Models\GeneralClientSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * زرعُ حقول ملفّ العميل المعروضة في `general_client_settings`.
 *
 * **يُعاد تشغيله** من شاشة «تحديث النظام» على قاعدةٍ فيها الصفوف أصلاً،
 * فالزرعُ **بالمفتاح الناقص وحده**: الصيغة القديمة (`create()` لكل صفّ)
 * كانت تنفجر عند أوّل مفتاحٍ موجود بـ«Duplicate entry 'image' for key
 * `general_client_settings_key_unique`» فتسقط الخطوة كلُّها.
 *
 * **`is_active` لا تُلمَس لصفٍّ موجود**: إخفاءُ حقلٍ من نموذج العميل قرارُ
 * صاحب النظام من «إعدادات وحدات العملاء»؛ زرعٌ يعيد كتابتها يُعيد إظهار ما
 * أُخفي عمداً. وبقيّةُ الأعمدة (الحدود والنِّسب وحقيبة `options`) لا تُمسّ
 * إطلاقاً — الصفّ يحمل إعداداتٍ أخرى غير مفتاحه.
 */
class GeneralClientSettingSeeder extends Seeder
{
    /**
     * الحقول المزروعة. حُذفت 'type' و'birth_date' و'national_id'
     * و'addresses' و'link' بطلب المستخدم: مفاتيحُ لا يقرؤها
     * create.blade.php/edit.blade.php إطلاقاً — تفعيلها لا يُظهر حقلاً ولا
     * يُخفيه. انظر migration drop_unwired_client_field_settings.
     *
     * @return array<string, string> المفتاح ⇦ اسمُه المعروض
     */
    private function fields(): array
    {
        return [
            'image' => 'صورة',
            'location' => 'الموقع على الخريطة',
            'credit_duration' => 'المدة الائتمانية',
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('general_client_settings')) {
            return;
        }

        $existing = DB::table('general_client_settings')->pluck('key')->all();
        $seeded = 0;

        foreach ($this->fields() as $key => $name) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            GeneralClientSetting::query()->create([
                'key' => $key,
                'name' => $name,
                'is_active' => true,
            ]);

            $seeded++;
        }

        $this->command?->info($seeded > 0
            ? "تمّ زرع {$seeded} حقلاً من حقول ملفّ العميل."
            : 'حقول ملفّ العميل مزروعةٌ مسبقاً — لا جديد.');
    }
}
