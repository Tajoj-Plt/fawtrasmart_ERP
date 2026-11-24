<?php

namespace Database\Seeders;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Models\Store\StoreBanner;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StoreBannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banners = [
            // بانرات السلايدر الرئيسي
            [
                'title' => 'عرض خاص - خصم 50%',
                'description' => 'احصل على خصم يصل إلى 50% على جميع المنتجات المختارة',
                'image' => 'assets/uploads/banners/banner_sale_50.jpg',
                'mobile_image' => 'assets/uploads/banners/banner_sale_50_mobile.jpg',
                'link' => null,
                'link_type' => BannerLinkType::URL,
                'link_id' => null,
                'position' => BannerPosition::HOME_SLIDER,
                'sort_order' => 1,
                'start_date' => Carbon::now()->subDays(5),
                'end_date' => Carbon::now()->addDays(30),
                'is_active' => true,
            ],
            [
                'title' => 'وصول حديث - منتجات جديدة',
                'description' => 'اكتشف أحدث منتجاتنا لهذا الموسم',
                'image' => 'assets/uploads/banners/banner_new_arrivals.jpg',
                'mobile_image' => 'assets/uploads/banners/banner_new_arrivals_mobile.jpg',
                'link' => null,
                'link_type' => BannerLinkType::CATEGORY,
                'link_id' => 1, // معرف تصنيف الوصول الحديث
                'position' => BannerPosition::HOME_SLIDER,
                'sort_order' => 2,
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->addDays(60),
                'is_active' => true,
            ],
            [
                'title' => 'شحن مجاني',
                'description' => 'شحن مجاني على جميع الطلبات فوق 200 ريال',
                'image' => 'assets/uploads/banners/banner_free_shipping.jpg',
                'mobile_image' => 'assets/uploads/banners/banner_free_shipping_mobile.jpg',
                'link' => null,
                'link_type' => BannerLinkType::URL,
                'link_id' => null,
                'position' => BannerPosition::HOME_SLIDER,
                'sort_order' => 3,
                'start_date' => Carbon::now()->subDays(3),
                'end_date' => null, // مستمر بدون تاريخ انتهاء
                'is_active' => true,
            ],
            [
                'title' => 'عروض نهاية الموسم',
                'description' => 'تخفيضات تصل إلى 70% على منتجات الموسم الماضي',
                'image' => 'assets/uploads/banners/banner_season_end.jpg',
                'mobile_image' => 'assets/uploads/banners/banner_season_end_mobile.jpg',
                'link' => null,
                'link_type' => BannerLinkType::URL,
                'link_id' => null,
                'position' => BannerPosition::HOME_SLIDER,
                'sort_order' => 4,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addDays(15),
                'is_active' => true,
            ],

            // بانرات الشريط الجانبي
            [
                'title' => 'أفضل العروض',
                'description' => 'لا تفوت عروضنا الحصرية',
                'image' => 'assets/uploads/banners/sidebar_best_deals.jpg',
                'mobile_image' => null,
                'link' => null,
                'link_type' => BannerLinkType::URL,
                'link_id' => null,
                'position' => BannerPosition::SIDEBAR,
                'sort_order' => 1,
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ],
            [
                'title' => 'منتجات مميزة',
                'description' => 'اكتشف منتجاتنا الأكثر مبيعاً',
                'image' => 'assets/uploads/banners/sidebar_featured.jpg',
                'mobile_image' => null,
                'link' => null,
                'link_type' => BannerLinkType::CATEGORY,
                'link_id' => 2,
                'position' => BannerPosition::SIDEBAR,
                'sort_order' => 2,
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ],

            // بانر الفوتر
            [
                'title' => 'اشترك في النشرة البريدية',
                'description' => 'احصل على خصم 10% على أول طلب',
                'image' => 'assets/uploads/banners/footer_newsletter.jpg',
                'mobile_image' => null,
                'link' => null,
                'link_type' => BannerLinkType::URL,
                'link_id' => null,
                'position' => BannerPosition::FOOTER,
                'sort_order' => 1,
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ],

            // Popup بانر
            [
                'title' => 'خصم 20% على أول طلب',
                'description' => 'استخدم كود FIRST20 عند الدفع',
                'image' => 'assets/uploads/banners/popup_first_order.jpg',
                'mobile_image' => 'assets/uploads/banners/popup_first_order_mobile.jpg',
                'link' => null,
                'link_type' => BannerLinkType::URL,
                'link_id' => null,
                'position' => BannerPosition::POPUP,
                'sort_order' => 1,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addDays(45),
                'is_active' => true,
            ],

            // بانر صفحة التصنيفات
            [
                'title' => 'تصفح حسب الفئة',
                'description' => 'اكتشف منتجاتنا المتنوعة',
                'image' => 'assets/uploads/banners/category_browse.jpg',
                'mobile_image' => null,
                'link' => null,
                'link_type' => BannerLinkType::CATEGORY,
                'link_id' => 3,
                'position' => BannerPosition::CATEGORY,
                'sort_order' => 1,
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ],

            // بانرات غير نشطة (للاختبار)
            [
                'title' => 'عرض منتهي - العيد الوطني',
                'description' => 'خصومات خاصة بمناسبة العيد الوطني',
                'image' => 'assets/uploads/banners/national_day_expired.jpg',
                'mobile_image' => null,
                'link' => null,
                'link_type' => BannerLinkType::URL,
                'link_id' => null,
                'position' => BannerPosition::HOME_SLIDER,
                'sort_order' => 10,
                'start_date' => Carbon::now()->subDays(60),
                'end_date' => Carbon::now()->subDays(30),
                'is_active' => false,
            ],
            [
                'title' => 'عرض قادم - رمضان',
                'description' => 'عروض رمضان الحصرية قريباً',
                'image' => 'assets/uploads/banners/ramadan_upcoming.jpg',
                'mobile_image' => null,
                'link' => null,
                'link_type' => BannerLinkType::URL,
                'link_id' => null,
                'position' => BannerPosition::HOME_SLIDER,
                'sort_order' => 11,
                'start_date' => Carbon::now()->addDays(30),
                'end_date' => Carbon::now()->addDays(60),
                'is_active' => false,
            ],
        ];

        $existing = StoreBanner::pluck('title')->all();
        $seeded = 0;

        foreach ($banners as $banner) {
            if (in_array($banner['title'], $existing, true)) {
                continue;
            }

            StoreBanner::create($banner);
            $seeded++;
        }

        $this->command?->info($seeded > 0
            ? "تمّ إنشاء {$seeded} بانراً."
            : 'البانرات مزروعةٌ مسبقاً — لا جديد.');
    }
}
