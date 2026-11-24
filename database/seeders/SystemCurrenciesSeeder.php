<?php

namespace Database\Seeders;

use App\Models\SystemCurrency;
use Illuminate\Database\Seeder;

class SystemCurrenciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    /**
     * العملات المزروعة — مصدرٌ واحد يقرأ منه السيدر ومعالج التثبيت معاً.
     *
     * كانت المصفوفة محبوسة داخل `run()`، فاضطُرّ معالج التثبيت إلى قائمةٍ
     * مكتوبة يدوياً ليعرضها قبل الزرع. وافترقت القائمتان: يزرع السيدر عشرين
     * عملة ويعرض المعالج إحدى عشرة — أي أن تسع عملات لا يمكن اختيارها وقت
     * التثبيت رغم وجودها في النظام بعده.
     *
     * @return array<int, array{name:string, code:string, symbol:string, country:string, decimal_places:int}>
     */
    public static function currencies(): array
    {
        return [
            ['name' => 'ريال سعودي', 'code' => 'SAR', 'symbol' => 'ر.س', 'country' => 'المملكة العربية السعودية', 'decimal_places' => 2],
            ['name' => 'دولار أمريكي', 'code' => 'USD', 'symbol' => '$', 'country' => 'الولايات المتحدة الأمريكية', 'decimal_places' => 2],
            ['name' => 'يورو', 'code' => 'EUR', 'symbol' => '€', 'country' => 'الاتحاد الأوروبي', 'decimal_places' => 2],
            ['name' => 'درهم إماراتي', 'code' => 'AED', 'symbol' => 'د.إ', 'country' => 'الإمارات العربية المتحدة', 'decimal_places' => 2],
            ['name' => 'دينار كويتي', 'code' => 'KWD', 'symbol' => 'د.ك', 'country' => 'الكويت', 'decimal_places' => 3],
            ['name' => 'دينار بحريني', 'code' => 'BHD', 'symbol' => 'د.ب', 'country' => 'مملكة البحرين', 'decimal_places' => 3],
            ['name' => 'ريال عماني', 'code' => 'OMR', 'symbol' => 'ر.ع.', 'country' => 'سلطنة عمان', 'decimal_places' => 3],
            ['name' => 'ريال قطري', 'code' => 'QAR', 'symbol' => 'ر.ق', 'country' => 'قطر', 'decimal_places' => 2],
            ['name' => 'جنيه مصري', 'code' => 'EGP', 'symbol' => 'ج.م', 'country' => 'مصر', 'decimal_places' => 2],
            ['name' => 'دينار أردني', 'code' => 'JOD', 'symbol' => 'د.ا', 'country' => 'الأردن', 'decimal_places' => 3],
            ['name' => 'جنيه إسترليني', 'code' => 'GBP', 'symbol' => '£', 'country' => 'المملكة المتحدة', 'decimal_places' => 2],
            ['name' => 'ليرة تركية', 'code' => 'TRY', 'symbol' => '₺', 'country' => 'تركيا', 'decimal_places' => 2],
            ['name' => 'يوان صيني', 'code' => 'CNY', 'symbol' => '¥', 'country' => 'الصين', 'decimal_places' => 2],
            ['name' => 'روبية هندية', 'code' => 'INR', 'symbol' => '₹', 'country' => 'الهند', 'decimal_places' => 2],
            ['name' => 'روبية باكستانية', 'code' => 'PKR', 'symbol' => '₨', 'country' => 'باكستان', 'decimal_places' => 2],
            ['name' => 'تاكا بنجلاديشي', 'code' => 'BDT', 'symbol' => '৳', 'country' => 'بنغلاديش', 'decimal_places' => 2],
            ['name' => 'ين ياباني', 'code' => 'JPY', 'symbol' => '¥', 'country' => 'اليابان', 'decimal_places' => 0],
            ['name' => 'فرنك سويسري', 'code' => 'CHF', 'symbol' => 'CHF', 'country' => 'سويسرا', 'decimal_places' => 2],
            ['name' => 'دولار كندي', 'code' => 'CAD', 'symbol' => 'CA$', 'country' => 'كندا', 'decimal_places' => 2],
            ['name' => 'دولار أسترالي', 'code' => 'AUD', 'symbol' => 'AU$', 'country' => 'أستراليا', 'decimal_places' => 2],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run()
    {
        foreach (self::currencies() as $currency) {
            SystemCurrency::updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'country' => $currency['country'],
                    'decimal_places' => $currency['decimal_places'],
                    'exchange_rate' => 1.0000,
                    'is_active' => true,
                    'is_default' => ($currency['code'] === 'SAR'), // جعل الريال الافتراضي
                    'rate_source' => 'manual',
                ]
            );
        }
    }
}
