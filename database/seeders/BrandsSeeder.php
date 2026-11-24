<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brands Seeder
 * سيدر البراندات والعلامات التجارية
 */
class BrandsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * إدراج البراندات الأساسية
     */
    public function run(): void
    {
        $brands = [
            [
                'name' => 'سامسونج',
                'slug' => 'samsung',
                'description' => 'رائدة في صناعة الإلكترونيات والهواتف الذكية',
                'logo' => '/images/brands/samsung.png',
                'website' => 'https://www.samsung.com',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'meta_title' => 'منتجات سامسونج - متجر فوت الرئيسي',
                'meta_description' => 'تسوق أحدث منتجات سامسونج من الهواتف والأجهزة الذكية'
            ],
            [
                'name' => 'آيفون',
                'slug' => 'iphone',
                'description' => 'الهواتف الذكية الأكثر تطوراً في العالم',
                'logo' => '/images/brands/apple.png',
                'website' => 'https://www.apple.com',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'meta_title' => 'هواتف آيفون - متجر فوت الرئيسي',
                'meta_description' => 'اشتري أحدث هواتف آيفون بأفضل الأسعار'
            ],
            [
                'name' => 'هواوي',
                'slug' => 'huawei',
                'description' => 'تقنيات متقدمة في الاتصالات والهواتف الذكية',
                'logo' => '/images/brands/huawei.png',
                'website' => 'https://www.huawei.com',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'meta_title' => 'منتجات هواوي - متجر فوت الرئيسي',
                'meta_description' => 'تسوق هواتف وأجهزة هواوي الذكية'
            ],
            [
                'name' => 'شاومي',
                'slug' => 'xiaomi',
                'description' => 'أجهزة ذكية بجودة عالية وأسعار منافسة',
                'logo' => '/images/brands/xiaomi.png',
                'website' => 'https://www.mi.com',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 4,
                'meta_title' => 'منتجات شاومي - متجر فوت الرئيسي',
                'meta_description' => 'هواتف وأكسسوارات شاومي بأفضل الأسعار'
            ],
            [
                'name' => 'أوبو',
                'slug' => 'oppo',
                'description' => 'هواتف ذكية بتقنيات تصوير متقدمة',
                'logo' => '/images/brands/oppo.png',
                'website' => 'https://www.oppo.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 5,
                'meta_title' => 'هواتف أوبو - متجر فوت الرئيسي',
                'meta_description' => 'تسوق أحدث هواتف أوبو بميزات التصوير المتقدمة'
            ],
            [
                'name' => 'فيفو',
                'slug' => 'vivo',
                'description' => 'هواتف ذكية أنيقة بأداء متميز',
                'logo' => '/images/brands/vivo.png',
                'website' => 'https://www.vivo.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 6,
                'meta_title' => 'هواتف فيفو - متجر فوت الرئيسي',
                'meta_description' => 'اشتري هواتف فيفو الأنيقة والمتطورة'
            ],
            [
                'name' => 'ون بلس',
                'slug' => 'oneplus',
                'description' => 'أداء فائق للمستخدمين المتقدمين',
                'logo' => '/images/brands/oneplus.png',
                'website' => 'https://www.oneplus.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 7,
                'meta_title' => 'هواتف ون بلس - متجر فوت الرئيسي',
                'meta_description' => 'هواتف ون بلس بأداء قوي وسرعة فائقة'
            ],
            [
                'name' => 'نوكيا',
                'slug' => 'nokia',
                'description' => 'الجودة والمتانة التي تثق بها',
                'logo' => '/images/brands/nokia.png',
                'website' => 'https://www.nokia.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 8,
                'meta_title' => 'هواتف نوكيا - متجر فوت الرئيسي',
                'meta_description' => 'هواتف نوكيا المتينة والموثوقة'
            ],
            [
                'name' => 'ريلمي',
                'slug' => 'realme',
                'description' => 'تقنيات حديثة للشباب العصري',
                'logo' => '/images/brands/realme.png',
                'website' => 'https://www.realme.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 9,
                'meta_title' => 'هواتف ريلمي - متجر فوت الرئيسي',
                'meta_description' => 'تسوق هواتف ريلمي العصرية بأحدث التقنيات'
            ],
            [
                'name' => 'انفينكس',
                'slug' => 'infinix',
                'description' => 'أجهزة ذكية بأسعار اقتصادية',
                'logo' => '/images/brands/infinix.png',
                'website' => 'https://www.infinixmobility.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 10,
                'meta_title' => 'هواتف انفينكس - متجر فوت الرئيسي',
                'meta_description' => 'هواتف انفينكس بأسعار اقتصادية وجودة ممتازة'
            ],

            // براندات الإلكترونيات والأجهزة المنزلية
            [
                'name' => 'إل جي',
                'slug' => 'lg',
                'description' => 'أجهزة منزلية وإلكترونيات متطورة',
                'logo' => '/images/brands/lg.png',
                'website' => 'https://www.lg.com',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 11,
                'meta_title' => 'منتجات إل جي - متجر فوت الرئيسي',
                'meta_description' => 'أجهزة إل جي المنزلية والإلكترونية المتطورة'
            ],
            [
                'name' => 'سوني',
                'slug' => 'sony',
                'description' => 'ترفيه وتقنيات صوتية متميزة',
                'logo' => '/images/brands/sony.png',
                'website' => 'https://www.sony.com',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 12,
                'meta_title' => 'منتجات سوني - متجر فوت الرئيسي',
                'meta_description' => 'أجهزة سوني الصوتية وألعاب البلايستيشن'
            ],
            [
                'name' => 'جيه في سي',
                'slug' => 'jvc',
                'description' => 'أنظمة صوتية عالية الجودة',
                'logo' => '/images/brands/jvc.png',
                'website' => 'https://www.jvc.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 13,
                'meta_title' => 'أجهزة جيه في سي الصوتية - متجر فوت الرئيسي',
                'meta_description' => 'أنظمة صوتية جيه في سي عالية الجودة'
            ],

            // براندات الأزياء والإكسسوارات
            [
                'name' => 'أديداس',
                'slug' => 'adidas',
                'description' => 'ملابس وأحذية رياضية عالمية',
                'logo' => '/images/brands/adidas.png',
                'website' => 'https://www.adidas.com',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 14,
                'meta_title' => 'منتجات أديداس - متجر فوت الرئيسي',
                'meta_description' => 'ملابس وأحذية أديداس الرياضية الأصلية'
            ],
            [
                'name' => 'نايكي',
                'slug' => 'nike',
                'description' => 'العلامة التجارية الرياضية الرائدة',
                'logo' => '/images/brands/nike.png',
                'website' => 'https://www.nike.com',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 15,
                'meta_title' => 'منتجات نايكي - متجر فوت الرئيسي',
                'meta_description' => 'ملابس وأحذية نايكي الرياضية الأصلية'
            ],
            [
                'name' => 'بوما',
                'slug' => 'puma',
                'description' => 'رياضة وأناقة في تصميم واحد',
                'logo' => '/images/brands/puma.png',
                'website' => 'https://www.puma.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 16,
                'meta_title' => 'منتجات بوما - متجر فوت الرئيسي',
                'meta_description' => 'ملابس وأحذية بوما الرياضية العصرية'
            ],

            // براندات العناية الشخصية
            [
                'name' => 'لوريال',
                'slug' => 'loreal',
                'description' => 'منتجات عناية وجمال عالمية',
                'logo' => '/images/brands/loreal.png',
                'website' => 'https://www.loreal.com',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 17,
                'meta_title' => 'منتجات لوريال - متجر فوت الرئيسي',
                'meta_description' => 'منتجات العناية والجمال من لوريال'
            ],
            [
                'name' => 'نيفيا',
                'slug' => 'nivea',
                'description' => 'عناية فائقة بالبشرة منذ أكثر من قرن',
                'logo' => '/images/brands/nivea.png',
                'website' => 'https://www.nivea.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 18,
                'meta_title' => 'منتجات نيفيا - متجر فوت الرئيسي',
                'meta_description' => 'منتجات العناية بالبشرة من نيفيا'
            ],

            // براندات محلية
            [
                'name' => 'الراجحي',
                'slug' => 'al-rajhi',
                'description' => 'منتجات وخدمات محلية موثوقة',
                'logo' => '/images/brands/alrajhi.png',
                'website' => 'https://www.alrajhibank.com.sa',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 19,
                'meta_title' => 'منتجات الراجحي - متجر فوت الرئيسي',
                'meta_description' => 'منتجات وخدمات الراجحي المحلية'
            ],
            [
                'name' => 'العثيم',
                'slug' => 'al-othaim',
                'description' => 'تجارة التجزئة والمنتجات الاستهلاكية',
                'logo' => '/images/brands/alothaim.png',
                'website' => 'https://www.alothaimmalls.com',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 20,
                'meta_title' => 'منتجات العثيم - متجر فوت الرئيسي',
                'meta_description' => 'منتجات العثيم المتنوعة والجودة العالية'
            ]
        ];

        foreach ($brands as $brand) {
            DB::table('brands')->updateOrInsert(
                ['slug' => $brand['slug']],
                array_merge($brand, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        $this->command->info('تم إدراج البراندات بنجاح (' . count($brands) . ' براند)');
    }
}