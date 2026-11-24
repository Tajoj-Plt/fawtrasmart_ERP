<?php

namespace Database\Seeders;

use App\Models\SystemLanguage;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * لغات النظام الافتراضية.
     *
     * الـ Seeder آمن للتشغيل المتكرر: يطابق على الرمز فلا يكرر السجلات،
     * ولا يمسّ اللغة الافتراضية القائمة ولا حالة التفعيل التي عدّلها المستخدم.
     *
     * @return void
     */
    /**
     * اللغات المزروعة — مصدرٌ واحد للسيدر ولمعالج التثبيت.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function languages(): array
    {
        return [
            [
                'code' => 'ar',
                'name' => 'العربية',
                'native_name' => 'العربية',
                'locale' => 'ar',
                'direction' => 'rtl',
                'flag' => 'sa.svg',
                'sort_order' => 1,
                'calendar_type' => 'gregorian',
                'timezone' => 'Asia/Riyadh',
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'locale' => 'en',
                'direction' => 'ltr',
                'flag' => 'us.svg',
                'sort_order' => 2,
                'calendar_type' => 'gregorian',
                'timezone' => 'UTC',
            ],
            [
                'code' => 'ur',
                'name' => 'Urdu',
                'native_name' => 'اردو',
                'locale' => 'ur',
                'direction' => 'rtl',
                'flag' => 'pk.svg',
                'sort_order' => 3,
                'calendar_type' => 'gregorian',
                'timezone' => 'Asia/Karachi',
            ],
            // hi و bn مفعّلتان في config/laravellocalization.php: تُزرعان مفعّلتين
            // حتى يبقى مبدّل اللغة كما هو بعد ربطه بجدول اللغات
            [
                'code' => 'hi',
                'name' => 'Hindi',
                'native_name' => 'हिन्दी',
                'locale' => 'hi',
                'direction' => 'ltr',
                'flag' => 'in.svg',
                'sort_order' => 4,
                'calendar_type' => 'gregorian',
                'timezone' => 'Asia/Kolkata',
            ],
            [
                'code' => 'bn',
                'name' => 'Bengali',
                'native_name' => 'বাংলা',
                'locale' => 'bn',
                'direction' => 'ltr',
                'flag' => 'bd.svg',
                'sort_order' => 5,
                'calendar_type' => 'gregorian',
                'timezone' => 'Asia/Dhaka',
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run()
    {
        $languages = self::languages();

        // اللغة الافتراضية الحالية إن وُجدت — تُحترم بدل فرض العربية من جديد
        $existingDefault = SystemLanguage::where('is_default', true)->value('code');

        foreach ($languages as $lang) {
            $existing = SystemLanguage::where('code', $lang['code'])->first();

            if ($existing) {
                // تحديث الوصف فقط دون المساس بما ضبطه المستخدم من حالة وافتراضية وترتيب
                $existing->fill([
                    'name' => $existing->name ?: $lang['name'],
                    'native_name' => $existing->native_name ?: $lang['native_name'],
                    'locale' => $existing->locale ?: $lang['locale'],
                    'direction' => $existing->direction ?: $lang['direction'],
                    'flag' => $existing->flag ?: $lang['flag'],
                ]);
                $existing->save();

                continue;
            }

            SystemLanguage::create(array_merge($lang, [
                'is_active' => true,
                // العربية افتراضية فقط عند عدم وجود لغة افتراضية مسبقاً
                'is_default' => $existingDefault === null && $lang['code'] === 'ar',
            ]));
        }

        // ضمان وجود لغة افتراضية واحدة على الأقل بعد التشغيل
        if (! SystemLanguage::where('is_default', true)->exists()) {
            SystemLanguage::where('is_active', true)
                ->orderBy('sort_order')
                ->first()
                ?->update(['is_default' => true]);
        }

        // لغة افتراضية واحدة فقط: يُبقى على الأقدم وتُلغى الصفة عن الباقي
        $keep = SystemLanguage::where('is_default', true)->orderBy('id')->value('id');
        if ($keep) {
            SystemLanguage::where('is_default', true)->where('id', '!=', $keep)->update(['is_default' => false]);
        }
    }
}
