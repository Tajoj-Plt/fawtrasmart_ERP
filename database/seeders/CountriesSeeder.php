<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Country;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $countries = [
            // دول مجلس التعاون الخليجي
            ['name' => 'المملكة العربية السعودية', 'code' => 'SA', 'phone_code' => '966', 'status' => true, 'is_default' => true],
            ['name' => 'الإمارات العربية المتحدة', 'code' => 'AE', 'phone_code' => '971', 'status' => true, 'is_default' => false],
            ['name' => 'الكويت', 'code' => 'KW', 'phone_code' => '965', 'status' => true, 'is_default' => false],
            ['name' => 'البحرين', 'code' => 'BH', 'phone_code' => '973', 'status' => true, 'is_default' => false],
            ['name' => 'عمان', 'code' => 'OM', 'phone_code' => '968', 'status' => true, 'is_default' => false],
            ['name' => 'قطر', 'code' => 'QA', 'phone_code' => '974', 'status' => true, 'is_default' => false],
            
            // دول عربية أخرى
            ['name' => 'مصر', 'code' => 'EG', 'phone_code' => '20', 'status' => true, 'is_default' => false],
            ['name' => 'الأردن', 'code' => 'JO', 'phone_code' => '962', 'status' => true, 'is_default' => false],
            ['name' => 'العراق', 'code' => 'IQ', 'phone_code' => '964', 'status' => true, 'is_default' => false],
            ['name' => 'لبنان', 'code' => 'LB', 'phone_code' => '961', 'status' => true, 'is_default' => false],
            ['name' => 'فلسطين', 'code' => 'PS', 'phone_code' => '970', 'status' => true, 'is_default' => false],
            ['name' => 'سوريا', 'code' => 'SY', 'phone_code' => '963', 'status' => true, 'is_default' => false],
            ['name' => 'اليمن', 'code' => 'YE', 'phone_code' => '967', 'status' => true, 'is_default' => false],
            ['name' => 'ليبيا', 'code' => 'LY', 'phone_code' => '218', 'status' => true, 'is_default' => false],
            ['name' => 'تونس', 'code' => 'TN', 'phone_code' => '216', 'status' => true, 'is_default' => false],
            ['name' => 'الجزائر', 'code' => 'DZ', 'phone_code' => '213', 'status' => true, 'is_default' => false],
            ['name' => 'المغرب', 'code' => 'MA', 'phone_code' => '212', 'status' => true, 'is_default' => false],
            ['name' => 'موريتانيا', 'code' => 'MR', 'phone_code' => '222', 'status' => true, 'is_default' => false],
            ['name' => 'السودان', 'code' => 'SD', 'phone_code' => '249', 'status' => true, 'is_default' => false],
            ['name' => 'الصومال', 'code' => 'SO', 'phone_code' => '252', 'status' => true, 'is_default' => false],
            ['name' => 'جيبوتي', 'code' => 'DJ', 'phone_code' => '253', 'status' => true, 'is_default' => false],
            ['name' => 'جزر القمر', 'code' => 'KM', 'phone_code' => '269', 'status' => true, 'is_default' => false],
            
            // دول أوروبية رئيسية
            ['name' => 'المملكة المتحدة', 'code' => 'GB', 'phone_code' => '44', 'status' => true, 'is_default' => false],
            ['name' => 'فرنسا', 'code' => 'FR', 'phone_code' => '33', 'status' => true, 'is_default' => false],
            ['name' => 'ألمانيا', 'code' => 'DE', 'phone_code' => '49', 'status' => true, 'is_default' => false],
            ['name' => 'إيطاليا', 'code' => 'IT', 'phone_code' => '39', 'status' => true, 'is_default' => false],
            ['name' => 'إسبانيا', 'code' => 'ES', 'phone_code' => '34', 'status' => true, 'is_default' => false],
            ['name' => 'هولندا', 'code' => 'NL', 'phone_code' => '31', 'status' => true, 'is_default' => false],
            ['name' => 'بلجيكا', 'code' => 'BE', 'phone_code' => '32', 'status' => true, 'is_default' => false],
            ['name' => 'سويسرا', 'code' => 'CH', 'phone_code' => '41', 'status' => true, 'is_default' => false],
            ['name' => 'النمسا', 'code' => 'AT', 'phone_code' => '43', 'status' => true, 'is_default' => false],
            ['name' => 'السويد', 'code' => 'SE', 'phone_code' => '46', 'status' => true, 'is_default' => false],
            ['name' => 'النرويج', 'code' => 'NO', 'phone_code' => '47', 'status' => true, 'is_default' => false],
            ['name' => 'الدنمارك', 'code' => 'DK', 'phone_code' => '45', 'status' => true, 'is_default' => false],
            ['name' => 'فنلندا', 'code' => 'FI', 'phone_code' => '358', 'status' => true, 'is_default' => false],
            ['name' => 'البرتغال', 'code' => 'PT', 'phone_code' => '351', 'status' => true, 'is_default' => false],
            ['name' => 'اليونان', 'code' => 'GR', 'phone_code' => '30', 'status' => true, 'is_default' => false],
            ['name' => 'بولندا', 'code' => 'PL', 'phone_code' => '48', 'status' => true, 'is_default' => false],
            ['name' => 'التشيك', 'code' => 'CZ', 'phone_code' => '420', 'status' => true, 'is_default' => false],
            ['name' => 'روسيا', 'code' => 'RU', 'phone_code' => '7', 'status' => true, 'is_default' => false],
            ['name' => 'تركيا', 'code' => 'TR', 'phone_code' => '90', 'status' => true, 'is_default' => false],
            
            // دول آسيوية
            ['name' => 'الصين', 'code' => 'CN', 'phone_code' => '86', 'status' => true, 'is_default' => false],
            ['name' => 'اليابان', 'code' => 'JP', 'phone_code' => '81', 'status' => true, 'is_default' => false],
            ['name' => 'كوريا الجنوبية', 'code' => 'KR', 'phone_code' => '82', 'status' => true, 'is_default' => false],
            ['name' => 'الهند', 'code' => 'IN', 'phone_code' => '91', 'status' => true, 'is_default' => false],
            ['name' => 'باكستان', 'code' => 'PK', 'phone_code' => '92', 'status' => true, 'is_default' => false],
            ['name' => 'بنغلاديش', 'code' => 'BD', 'phone_code' => '880', 'status' => true, 'is_default' => false],
            ['name' => 'إندونيسيا', 'code' => 'ID', 'phone_code' => '62', 'status' => true, 'is_default' => false],
            ['name' => 'ماليزيا', 'code' => 'MY', 'phone_code' => '60', 'status' => true, 'is_default' => false],
            ['name' => 'سنغافورة', 'code' => 'SG', 'phone_code' => '65', 'status' => true, 'is_default' => false],
            ['name' => 'تايلاند', 'code' => 'TH', 'phone_code' => '66', 'status' => true, 'is_default' => false],
            ['name' => 'فيتنام', 'code' => 'VN', 'phone_code' => '84', 'status' => true, 'is_default' => false],
            ['name' => 'الفلبين', 'code' => 'PH', 'phone_code' => '63', 'status' => true, 'is_default' => false],
            ['name' => 'إيران', 'code' => 'IR', 'phone_code' => '98', 'status' => true, 'is_default' => false],
            ['name' => 'أفغانستان', 'code' => 'AF', 'phone_code' => '93', 'status' => true, 'is_default' => false],
            
            // دول أمريكية
            ['name' => 'الولايات المتحدة', 'code' => 'US', 'phone_code' => '1', 'status' => true, 'is_default' => false],
            ['name' => 'كندا', 'code' => 'CA', 'phone_code' => '1', 'status' => true, 'is_default' => false],
            ['name' => 'المكسيك', 'code' => 'MX', 'phone_code' => '52', 'status' => true, 'is_default' => false],
            ['name' => 'البرازيل', 'code' => 'BR', 'phone_code' => '55', 'status' => true, 'is_default' => false],
            ['name' => 'الأرجنتين', 'code' => 'AR', 'phone_code' => '54', 'status' => true, 'is_default' => false],
            ['name' => 'تشيلي', 'code' => 'CL', 'phone_code' => '56', 'status' => true, 'is_default' => false],
            ['name' => 'كولومبيا', 'code' => 'CO', 'phone_code' => '57', 'status' => true, 'is_default' => false],
            ['name' => 'بيرو', 'code' => 'PE', 'phone_code' => '51', 'status' => true, 'is_default' => false],
            
            // دول أفريقية
            ['name' => 'جنوب أفريقيا', 'code' => 'ZA', 'phone_code' => '27', 'status' => true, 'is_default' => false],
            ['name' => 'نيجيريا', 'code' => 'NG', 'phone_code' => '234', 'status' => true, 'is_default' => false],
            ['name' => 'كينيا', 'code' => 'KE', 'phone_code' => '254', 'status' => true, 'is_default' => false],
            ['name' => 'إثيوبيا', 'code' => 'ET', 'phone_code' => '251', 'status' => true, 'is_default' => false],
            ['name' => 'غانا', 'code' => 'GH', 'phone_code' => '233', 'status' => true, 'is_default' => false],
            ['name' => 'تنزانيا', 'code' => 'TZ', 'phone_code' => '255', 'status' => true, 'is_default' => false],
            
            // دول أخرى
            ['name' => 'أستراليا', 'code' => 'AU', 'phone_code' => '61', 'status' => true, 'is_default' => false],
            ['name' => 'نيوزيلندا', 'code' => 'NZ', 'phone_code' => '64', 'status' => true, 'is_default' => false],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(['code' => $country['code']], $country);
        }
    }
}
