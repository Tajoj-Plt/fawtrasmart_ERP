<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\City;
use App\Models\Country;

class CitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // السعودية
        $sa = Country::where('code', 'SA')->first();
        if ($sa) {
            $cities = [
                ['name' => 'الرياض', 'status' => true],
                ['name' => 'جدة', 'status' => true],
                ['name' => 'مكة المكرمة', 'status' => true],
                ['name' => 'المدينة المنورة', 'status' => true],
                ['name' => 'الدمام', 'status' => true],
                ['name' => 'الخبر', 'status' => true],
                ['name' => 'الظهران', 'status' => true],
                ['name' => 'تبوك', 'status' => true],
                ['name' => 'بريدة', 'status' => true],
                ['name' => 'خميس مشيط', 'status' => true],
                ['name' => 'أبها', 'status' => true],
                ['name' => 'الطائف', 'status' => true],
                ['name' => 'حائل', 'status' => true],
                ['name' => 'نجران', 'status' => true],
                ['name' => 'جيزان', 'status' => true],
                ['name' => 'ينبع', 'status' => true],
                ['name' => 'القطيف', 'status' => true],
                ['name' => 'الجبيل', 'status' => true],
                ['name' => 'الأحساء', 'status' => true],
                ['name' => 'عرعر', 'status' => true],
                ['name' => 'سكاكا', 'status' => true],
                ['name' => 'القريات', 'status' => true],
                ['name' => 'عنيزة', 'status' => true],
                ['name' => 'الرس', 'status' => true],
                ['name' => 'الزلفي', 'status' => true],
                ['name' => 'المجمعة', 'status' => true],
                ['name' => 'الخرج', 'status' => true],
                ['name' => 'الدوادمي', 'status' => true],
                ['name' => 'القنفذة', 'status' => true],
                ['name' => 'رابغ', 'status' => true],
                ['name' => 'صبيا', 'status' => true],
                ['name' => 'أحد رفيدة', 'status' => true],
                ['name' => 'بيشة', 'status' => true],
                ['name' => 'محايل', 'status' => true],
                ['name' => 'النماص', 'status' => true],
                ['name' => 'رجال ألمع', 'status' => true],
                ['name' => 'طريف', 'status' => true],
                ['name' => 'رفحاء', 'status' => true],
                ['name' => 'دومة الجندل', 'status' => true],
                ['name' => 'ضبا', 'status' => true],
                ['name' => 'الوجه', 'status' => true],
                ['name' => 'أملج', 'status' => true],
                ['name' => 'تيماء', 'status' => true],
                ['name' => 'الحوطة', 'status' => true],
                ['name' => 'الأفلاج', 'status' => true],
                ['name' => 'وادي الدواسر', 'status' => true],
                ['name' => 'حفر الباطن', 'status' => true],
                ['name' => 'الجوف', 'status' => true],
                ['name' => 'شقراء', 'status' => true],
                ['name' => 'ضرماء', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $sa->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // الإمارات
        $ae = Country::where('code', 'AE')->first();
        if ($ae) {
            $cities = [
                ['name' => 'دبي', 'status' => true],
                ['name' => 'أبوظبي', 'status' => true],
                ['name' => 'الشارقة', 'status' => true],
                ['name' => 'عجمان', 'status' => true],
                ['name' => 'رأس الخيمة', 'status' => true],
                ['name' => 'الفجيرة', 'status' => true],
                ['name' => 'أم القيوين', 'status' => true],
                ['name' => 'العين', 'status' => true],
                ['name' => 'خورفكان', 'status' => true],
                ['name' => 'كلباء', 'status' => true],
                ['name' => 'دبا الحصن', 'status' => true],
                ['name' => 'الذيد', 'status' => true],
                ['name' => 'جبل علي', 'status' => true],
                ['name' => 'مدينة زايد', 'status' => true],
                ['name' => 'الرويس', 'status' => true],
                ['name' => 'غياثي', 'status' => true],
                ['name' => 'ليوا', 'status' => true],
                ['name' => 'مصفح', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $ae->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // الكويت
        $kw = Country::where('code', 'KW')->first();
        if ($kw) {
            $cities = [
                ['name' => 'مدينة الكويت', 'status' => true],
                ['name' => 'حولي', 'status' => true],
                ['name' => 'الفروانية', 'status' => true],
                ['name' => 'الأحمدي', 'status' => true],
                ['name' => 'الجهراء', 'status' => true],
                ['name' => 'مبارك الكبير', 'status' => true],
                ['name' => 'السالمية', 'status' => true],
                ['name' => 'الفحيحيل', 'status' => true],
                ['name' => 'المنقف', 'status' => true],
                ['name' => 'الجابرية', 'status' => true],
                ['name' => 'الفنطاس', 'status' => true],
                ['name' => 'صباح السالم', 'status' => true],
                ['name' => 'الرقة', 'status' => true],
                ['name' => 'المهبولة', 'status' => true],
                ['name' => 'صبحان', 'status' => true],
                ['name' => 'العبدلي', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $kw->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // البحرين
        $bh = Country::where('code', 'BH')->first();
        if ($bh) {
            $cities = [
                ['name' => 'المنامة', 'status' => true],
                ['name' => 'المحرق', 'status' => true],
                ['name' => 'الرفاع', 'status' => true],
                ['name' => 'مدينة عيسى', 'status' => true],
                ['name' => 'سترة', 'status' => true],
                ['name' => 'مدينة حمد', 'status' => true],
                ['name' => 'الحد', 'status' => true],
                ['name' => 'جدحفص', 'status' => true],
                ['name' => 'عالي', 'status' => true],
                ['name' => 'البديع', 'status' => true],
                ['name' => 'الدراز', 'status' => true],
                ['name' => 'سار', 'status' => true],
                ['name' => 'الجفير', 'status' => true],
                ['name' => 'السيف', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $bh->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // عمان
        $om = Country::where('code', 'OM')->first();
        if ($om) {
            $cities = [
                ['name' => 'مسقط', 'status' => true],
                ['name' => 'صلالة', 'status' => true],
                ['name' => 'صحار', 'status' => true],
                ['name' => 'نزوى', 'status' => true],
                ['name' => 'صور', 'status' => true],
                ['name' => 'البريمي', 'status' => true],
                ['name' => 'السيب', 'status' => true],
                ['name' => 'الرستاق', 'status' => true],
                ['name' => 'بركاء', 'status' => true],
                ['name' => 'السويق', 'status' => true],
                ['name' => 'عبري', 'status' => true],
                ['name' => 'بهلاء', 'status' => true],
                ['name' => 'إبراء', 'status' => true],
                ['name' => 'الخابورة', 'status' => true],
                ['name' => 'الباطنة', 'status' => true],
                ['name' => 'قريات', 'status' => true],
                ['name' => 'خصب', 'status' => true],
                ['name' => 'شناص', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $om->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // قطر
        $qa = Country::where('code', 'QA')->first();
        if ($qa) {
            $cities = [
                ['name' => 'الدوحة', 'status' => true],
                ['name' => 'الريان', 'status' => true],
                ['name' => 'الوكرة', 'status' => true],
                ['name' => 'الخور', 'status' => true],
                ['name' => 'دخان', 'status' => true],
                ['name' => 'مسيعيد', 'status' => true],
                ['name' => 'أم صلال', 'status' => true],
                ['name' => 'الشحانية', 'status' => true],
                ['name' => 'الشمال', 'status' => true],
                ['name' => 'الغويرية', 'status' => true],
                ['name' => 'الوجبة', 'status' => true],
                ['name' => 'الثميد', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $qa->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // مصر
        $eg = Country::where('code', 'EG')->first();
        if ($eg) {
            $cities = [
                ['name' => 'القاهرة', 'status' => true],
                ['name' => 'الإسكندرية', 'status' => true],
                ['name' => 'الجيزة', 'status' => true],
                ['name' => 'شبرا الخيمة', 'status' => true],
                ['name' => 'بورسعيد', 'status' => true],
                ['name' => 'السويس', 'status' => true],
                ['name' => 'الأقصر', 'status' => true],
                ['name' => 'أسوان', 'status' => true],
                ['name' => 'المنصورة', 'status' => true],
                ['name' => 'طنطا', 'status' => true],
                ['name' => 'أسيوط', 'status' => true],
                ['name' => 'الإسماعيلية', 'status' => true],
                ['name' => 'الفيوم', 'status' => true],
                ['name' => 'الزقازيق', 'status' => true],
                ['name' => 'دمياط', 'status' => true],
                ['name' => 'بنها', 'status' => true],
                ['name' => 'المنيا', 'status' => true],
                ['name' => 'سوهاج', 'status' => true],
                ['name' => 'قنا', 'status' => true],
                ['name' => 'بني سويف', 'status' => true],
                ['name' => 'كفر الشيخ', 'status' => true],
                ['name' => 'العريش', 'status' => true],
                ['name' => 'مرسى مطروح', 'status' => true],
                ['name' => 'الغردقة', 'status' => true],
                ['name' => 'شرم الشيخ', 'status' => true],
                ['name' => 'دهب', 'status' => true],
                ['name' => 'نويبع', 'status' => true],
                ['name' => 'طابا', 'status' => true],
                ['name' => 'رشيد', 'status' => true],
                ['name' => 'دمنهور', 'status' => true],
                ['name' => 'شبين الكوم', 'status' => true],
                ['name' => 'المحلة الكبرى', 'status' => true],
                ['name' => 'الطور', 'status' => true],
                ['name' => 'القنطرة', 'status' => true],
                ['name' => 'رفح', 'status' => true],
                ['name' => 'السلوم', 'status' => true],
                ['name' => 'الداخلة', 'status' => true],
                ['name' => 'الخارجة', 'status' => true],
                ['name' => 'سيوة', 'status' => true],
                ['name' => 'أبو سمبل', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $eg->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // الأردن
        $jo = Country::where('code', 'JO')->first();
        if ($jo) {
            $cities = [
                ['name' => 'عمان', 'status' => true],
                ['name' => 'الزرقاء', 'status' => true],
                ['name' => 'إربد', 'status' => true],
                ['name' => 'العقبة', 'status' => true],
                ['name' => 'السلط', 'status' => true],
                ['name' => 'المفرق', 'status' => true],
                ['name' => 'جرش', 'status' => true],
                ['name' => 'الكرك', 'status' => true],
                ['name' => 'مادبا', 'status' => true],
                ['name' => 'الطفيلة', 'status' => true],
                ['name' => 'معان', 'status' => true],
                ['name' => 'عجلون', 'status' => true],
                ['name' => 'الرمثا', 'status' => true],
                ['name' => 'الرصيفة', 'status' => true],
                ['name' => 'سحاب', 'status' => true],
                ['name' => 'الجويدة', 'status' => true],
                ['name' => 'البتراء', 'status' => true],
                ['name' => 'الأزرق', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $jo->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // العراق
        $iq = Country::where('code', 'IQ')->first();
        if ($iq) {
            $cities = [
                ['name' => 'بغداد', 'status' => true],
                ['name' => 'البصرة', 'status' => true],
                ['name' => 'الموصل', 'status' => true],
                ['name' => 'أربيل', 'status' => true],
                ['name' => 'كركوك', 'status' => true],
                ['name' => 'النجف', 'status' => true],
                ['name' => 'كربلاء', 'status' => true],
                ['name' => 'السليمانية', 'status' => true],
                ['name' => 'الناصرية', 'status' => true],
                ['name' => 'العمارة', 'status' => true],
                ['name' => 'الديوانية', 'status' => true],
                ['name' => 'الكوت', 'status' => true],
                ['name' => 'الحلة', 'status' => true],
                ['name' => 'الرمادي', 'status' => true],
                ['name' => 'الفلوجة', 'status' => true],
                ['name' => 'بعقوبة', 'status' => true],
                ['name' => 'تكريت', 'status' => true],
                ['name' => 'سامراء', 'status' => true],
                ['name' => 'دهوك', 'status' => true],
                ['name' => 'زاخو', 'status' => true],
                ['name' => 'بيجي', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $iq->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // لبنان
        $lb = Country::where('code', 'LB')->first();
        if ($lb) {
            $cities = [
                ['name' => 'بيروت', 'status' => true],
                ['name' => 'طرابلس', 'status' => true],
                ['name' => 'صيدا', 'status' => true],
                ['name' => 'صور', 'status' => true],
                ['name' => 'جبيل', 'status' => true],
                ['name' => 'زحلة', 'status' => true],
                ['name' => 'بعلبك', 'status' => true],
                ['name' => 'النبطية', 'status' => true],
                ['name' => 'جونيه', 'status' => true],
                ['name' => 'عاليه', 'status' => true],
                ['name' => 'بشري', 'status' => true],
                ['name' => 'حاصبيا', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $lb->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // فلسطين
        $ps = Country::where('code', 'PS')->first();
        if ($ps) {
            $cities = [
                ['name' => 'القدس', 'status' => true],
                ['name' => 'غزة', 'status' => true],
                ['name' => 'الخليل', 'status' => true],
                ['name' => 'نابلس', 'status' => true],
                ['name' => 'رام الله', 'status' => true],
                ['name' => 'بيت لحم', 'status' => true],
                ['name' => 'جنين', 'status' => true],
                ['name' => 'طولكرم', 'status' => true],
                ['name' => 'قلقيلية', 'status' => true],
                ['name' => 'أريحا', 'status' => true],
                ['name' => 'رفح', 'status' => true],
                ['name' => 'خان يونس', 'status' => true],
                ['name' => 'دير البلح', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $ps->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // سوريا
        $sy = Country::where('code', 'SY')->first();
        if ($sy) {
            $cities = [
                ['name' => 'دمشق', 'status' => true],
                ['name' => 'حلب', 'status' => true],
                ['name' => 'حمص', 'status' => true],
                ['name' => 'حماة', 'status' => true],
                ['name' => 'اللاذقية', 'status' => true],
                ['name' => 'طرطوس', 'status' => true],
                ['name' => 'دير الزور', 'status' => true],
                ['name' => 'الرقة', 'status' => true],
                ['name' => 'إدلب', 'status' => true],
                ['name' => 'السويداء', 'status' => true],
                ['name' => 'درعا', 'status' => true],
                ['name' => 'القامشلي', 'status' => true],
                ['name' => 'الحسكة', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $sy->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // اليمن
        $ye = Country::where('code', 'YE')->first();
        if ($ye) {
            $cities = [
                ['name' => 'صنعاء', 'status' => true],
                ['name' => 'عدن', 'status' => true],
                ['name' => 'تعز', 'status' => true],
                ['name' => 'الحديدة', 'status' => true],
                ['name' => 'إب', 'status' => true],
                ['name' => 'ذمار', 'status' => true],
                ['name' => 'المكلا', 'status' => true],
                ['name' => 'صعدة', 'status' => true],
                ['name' => 'مأرب', 'status' => true],
                ['name' => 'حجة', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $ye->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // ليبيا
        $ly = Country::where('code', 'LY')->first();
        if ($ly) {
            $cities = [
                ['name' => 'طرابلس', 'status' => true],
                ['name' => 'بنغازي', 'status' => true],
                ['name' => 'مصراتة', 'status' => true],
                ['name' => 'الزاوية', 'status' => true],
                ['name' => 'البيضاء', 'status' => true],
                ['name' => 'سبها', 'status' => true],
                ['name' => 'طبرق', 'status' => true],
                ['name' => 'درنة', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $ly->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // المغرب
        $ma = Country::where('code', 'MA')->first();
        if ($ma) {
            $cities = [
                ['name' => 'الرباط', 'status' => true],
                ['name' => 'الدار البيضاء', 'status' => true],
                ['name' => 'فاس', 'status' => true],
                ['name' => 'مراكش', 'status' => true],
                ['name' => 'طنجة', 'status' => true],
                ['name' => 'أغادير', 'status' => true],
                ['name' => 'مكناس', 'status' => true],
                ['name' => 'وجدة', 'status' => true],
                ['name' => 'القنيطرة', 'status' => true],
                ['name' => 'تطوان', 'status' => true],
                ['name' => 'الصويرة', 'status' => true],
                ['name' => 'العيون', 'status' => true],
                ['name' => 'الداخلة', 'status' => true],
                ['name' => 'سطات', 'status' => true],
                ['name' => 'بني ملال', 'status' => true],
                ['name' => 'خريبكة', 'status' => true],
                ['name' => 'الجديدة', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $ma->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // تونس
        $tn = Country::where('code', 'TN')->first();
        if ($tn) {
            $cities = [
                ['name' => 'تونس', 'status' => true],
                ['name' => 'صفاقس', 'status' => true],
                ['name' => 'سوسة', 'status' => true],
                ['name' => 'القيروان', 'status' => true],
                ['name' => 'بنزرت', 'status' => true],
                ['name' => 'قابس', 'status' => true],
                ['name' => 'أريانة', 'status' => true],
                ['name' => 'قفصة', 'status' => true],
                ['name' => 'المنستير', 'status' => true],
                ['name' => 'المهدية', 'status' => true],
                ['name' => 'نابل', 'status' => true],
                ['name' => 'توزر', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $tn->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // الجزائر
        $dz = Country::where('code', 'DZ')->first();
        if ($dz) {
            $cities = [
                ['name' => 'الجزائر', 'status' => true],
                ['name' => 'وهران', 'status' => true],
                ['name' => 'قسنطينة', 'status' => true],
                ['name' => 'عنابة', 'status' => true],
                ['name' => 'تلمسان', 'status' => true],
                ['name' => 'سطيف', 'status' => true],
                ['name' => 'باتنة', 'status' => true],
                ['name' => 'بسكرة', 'status' => true],
                ['name' => 'ورقلة', 'status' => true],
                ['name' => 'بجاية', 'status' => true],
                ['name' => 'تيزي وزو', 'status' => true],
                ['name' => 'مستغانم', 'status' => true],
                ['name' => 'غرداية', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $dz->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // السودان
        $sd = Country::where('code', 'SD')->first();
        if ($sd) {
            $cities = [
                ['name' => 'الخرطوم', 'status' => true],
                ['name' => 'أم درمان', 'status' => true],
                ['name' => 'بورتسودان', 'status' => true],
                ['name' => 'كسلا', 'status' => true],
                ['name' => 'القضارف', 'status' => true],
                ['name' => 'نيالا', 'status' => true],
                ['name' => 'الأبيض', 'status' => true],
                ['name' => 'مدني', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $sd->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // الصومال
        $so = Country::where('code', 'SO')->first();
        if ($so) {
            $cities = [
                ['name' => 'مقديشو', 'status' => true],
                ['name' => 'هرجيسا', 'status' => true],
                ['name' => 'بوصاصو', 'status' => true],
                ['name' => 'كسمايو', 'status' => true],
                ['name' => 'بربرة', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $so->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // موريتانيا
        $mr = Country::where('code', 'MR')->first();
        if ($mr) {
            $cities = [
                ['name' => 'نواكشوط', 'status' => true],
                ['name' => 'نواذيبو', 'status' => true],
                ['name' => 'كيفة', 'status' => true],
                ['name' => 'روصو', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $mr->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // جيبوتي
        $dj = Country::where('code', 'DJ')->first();
        if ($dj) {
            $cities = [
                ['name' => 'جيبوتي', 'status' => true],
                ['name' => 'علي صبيح', 'status' => true],
                ['name' => 'تاجورة', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $dj->id, 'name' => $city['name']],
                    $city
                );
            }
        }

        // جزر القمر
        $km = Country::where('code', 'KM')->first();
        if ($km) {
            $cities = [
                ['name' => 'موروني', 'status' => true],
                ['name' => 'متسامودو', 'status' => true],
                ['name' => 'فومبوني', 'status' => true],
            ];
            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $km->id, 'name' => $city['name']],
                    $city
                );
            }
        }
    }
}
