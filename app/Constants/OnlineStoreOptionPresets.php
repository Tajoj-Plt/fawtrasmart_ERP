<?php

namespace App\Constants;

/**
 * قائمة خيارات منتج جاهزة (اللون، المقاس، ...) يختار منها المستخدم عند
 * إضافة خيار لمنتج في شاشة المتجر الإلكتروني، بدل كتابة الاسم والقيم يدوياً
 * في كل مرة. لا جدول قاعدة بيانات وراءها عمداً: خيارات المنتج
 * (`product_options`/`product_option_values`) مملوكة مباشرة للمنتج نفسه
 * ولا يوجد كتالوج مشترك بينها في المخطط الأصلي، فهذه القائمة ثابتةٌ في
 * الكود فقط لتغذية شاشة الاختيار — واختيار المستخدم منها ينشئ صفوفاً
 * حقيقية في `product_options`/`product_option_values` خاصة بالمنتج نفسه.
 * يبقى بإمكان المستخدم كذلك كتابة خيارٍ وقيمٍ مخصصة كلياً بدل الاختيار من هنا.
 */
class OnlineStoreOptionPresets
{
    /**
     * @var array<int, array{name: string, label_ar: string, label_en: string, values: array<int, array{value: string, label_ar: string, label_en: string, color_code?: string}>}>
     */
    public const PRESETS = [
        [
            'name' => 'color',
            'label_ar' => 'اللون',
            'label_en' => 'Color',
            'values' => [
                ['value' => 'red', 'label_ar' => 'أحمر', 'label_en' => 'Red', 'color_code' => '#E53935'],
                ['value' => 'blue', 'label_ar' => 'أزرق', 'label_en' => 'Blue', 'color_code' => '#1E88E5'],
                ['value' => 'green', 'label_ar' => 'أخضر', 'label_en' => 'Green', 'color_code' => '#43A047'],
                ['value' => 'black', 'label_ar' => 'أسود', 'label_en' => 'Black', 'color_code' => '#000000'],
                ['value' => 'white', 'label_ar' => 'أبيض', 'label_en' => 'White', 'color_code' => '#FFFFFF'],
                ['value' => 'yellow', 'label_ar' => 'أصفر', 'label_en' => 'Yellow', 'color_code' => '#FDD835'],
                ['value' => 'gray', 'label_ar' => 'رمادي', 'label_en' => 'Gray', 'color_code' => '#757575'],
                ['value' => 'beige', 'label_ar' => 'بيج', 'label_en' => 'Beige', 'color_code' => '#D8C3A5'],
                ['value' => 'orange', 'label_ar' => 'برتقالي', 'label_en' => 'Orange', 'color_code' => '#FB8C00'],
                ['value' => 'purple', 'label_ar' => 'بنفسجي', 'label_en' => 'Purple', 'color_code' => '#8E24AA'],
                ['value' => 'pink', 'label_ar' => 'وردي', 'label_en' => 'Pink', 'color_code' => '#EC407A'],
                ['value' => 'brown', 'label_ar' => 'بني', 'label_en' => 'Brown', 'color_code' => '#6D4C41'],
                ['value' => 'navy', 'label_ar' => 'كحلي', 'label_en' => 'Navy', 'color_code' => '#1A237E'],
                ['value' => 'gold', 'label_ar' => 'ذهبي', 'label_en' => 'Gold', 'color_code' => '#C9A227'],
                ['value' => 'silver', 'label_ar' => 'فضي', 'label_en' => 'Silver', 'color_code' => '#B0B0B0'],
                ['value' => 'turquoise', 'label_ar' => 'فيروزي', 'label_en' => 'Turquoise', 'color_code' => '#00ACC1'],
            ],
        ],
        [
            'name' => 'size',
            'label_ar' => 'المقاس',
            'label_en' => 'Size',
            'values' => [
                ['value' => 'xs', 'label_ar' => 'صغير جداً (XS)', 'label_en' => 'XS'],
                ['value' => 's', 'label_ar' => 'صغير (S)', 'label_en' => 'S'],
                ['value' => 'm', 'label_ar' => 'متوسط (M)', 'label_en' => 'M'],
                ['value' => 'l', 'label_ar' => 'كبير (L)', 'label_en' => 'L'],
                ['value' => 'xl', 'label_ar' => 'كبير جداً (XL)', 'label_en' => 'XL'],
                ['value' => 'xxl', 'label_ar' => 'كبير جداً جداً (XXL)', 'label_en' => 'XXL'],
            ],
        ],
        [
            'name' => 'shoe_size',
            'label_ar' => 'مقاس الحذاء',
            'label_en' => 'Shoe Size',
            'values' => [
                ['value' => '38', 'label_ar' => '38', 'label_en' => '38'],
                ['value' => '39', 'label_ar' => '39', 'label_en' => '39'],
                ['value' => '40', 'label_ar' => '40', 'label_en' => '40'],
                ['value' => '41', 'label_ar' => '41', 'label_en' => '41'],
                ['value' => '42', 'label_ar' => '42', 'label_en' => '42'],
                ['value' => '43', 'label_ar' => '43', 'label_en' => '43'],
                ['value' => '44', 'label_ar' => '44', 'label_en' => '44'],
                ['value' => '45', 'label_ar' => '45', 'label_en' => '45'],
                ['value' => '46', 'label_ar' => '46', 'label_en' => '46'],
            ],
        ],
        [
            'name' => 'material',
            'label_ar' => 'المادة',
            'label_en' => 'Material',
            'values' => [
                ['value' => 'cotton', 'label_ar' => 'قطن', 'label_en' => 'Cotton'],
                ['value' => 'leather', 'label_ar' => 'جلد', 'label_en' => 'Leather'],
                ['value' => 'polyester', 'label_ar' => 'بوليستر', 'label_en' => 'Polyester'],
                ['value' => 'wool', 'label_ar' => 'صوف', 'label_en' => 'Wool'],
                ['value' => 'metal', 'label_ar' => 'معدن', 'label_en' => 'Metal'],
                ['value' => 'plastic', 'label_ar' => 'بلاستيك', 'label_en' => 'Plastic'],
            ],
        ],
        [
            'name' => 'storage',
            'label_ar' => 'سعة التخزين',
            'label_en' => 'Storage',
            'values' => [
                ['value' => '64gb', 'label_ar' => '64 جيجابايت', 'label_en' => '64GB'],
                ['value' => '128gb', 'label_ar' => '128 جيجابايت', 'label_en' => '128GB'],
                ['value' => '256gb', 'label_ar' => '256 جيجابايت', 'label_en' => '256GB'],
                ['value' => '512gb', 'label_ar' => '512 جيجابايت', 'label_en' => '512GB'],
                ['value' => '1tb', 'label_ar' => '1 تيرابايت', 'label_en' => '1TB'],
            ],
        ],
        [
            'name' => 'capacity',
            'label_ar' => 'السعة/الحجم',
            'label_en' => 'Capacity',
            'values' => [
                ['value' => '250ml', 'label_ar' => '250 مل', 'label_en' => '250ml'],
                ['value' => '500ml', 'label_ar' => '500 مل', 'label_en' => '500ml'],
                ['value' => '1l', 'label_ar' => '1 لتر', 'label_en' => '1L'],
                ['value' => '2l', 'label_ar' => '2 لتر', 'label_en' => '2L'],
                ['value' => '5l', 'label_ar' => '5 لتر', 'label_en' => '5L'],
                ['value' => '250g', 'label_ar' => '250 جرام', 'label_en' => '250g'],
                ['value' => '500g', 'label_ar' => '500 جرام', 'label_en' => '500g'],
                ['value' => '1kg', 'label_ar' => '1 كيلوجرام', 'label_en' => '1kg'],
            ],
        ],
        [
            'name' => 'flavor',
            'label_ar' => 'النكهة',
            'label_en' => 'Flavor',
            'values' => [
                ['value' => 'original', 'label_ar' => 'أصلي', 'label_en' => 'Original'],
                ['value' => 'vanilla', 'label_ar' => 'فانيليا', 'label_en' => 'Vanilla'],
                ['value' => 'chocolate', 'label_ar' => 'شوكولاتة', 'label_en' => 'Chocolate'],
                ['value' => 'strawberry', 'label_ar' => 'فراولة', 'label_en' => 'Strawberry'],
                ['value' => 'mint', 'label_ar' => 'نعناع', 'label_en' => 'Mint'],
                ['value' => 'orange', 'label_ar' => 'برتقال', 'label_en' => 'Orange'],
                ['value' => 'unflavored', 'label_ar' => 'بدون نكهة', 'label_en' => 'Unflavored'],
            ],
        ],
        [
            'name' => 'connector_type',
            'label_ar' => 'نوع الموصل',
            'label_en' => 'Connector Type',
            'values' => [
                ['value' => 'usb_c', 'label_ar' => 'USB-C', 'label_en' => 'USB-C'],
                ['value' => 'lightning', 'label_ar' => 'لايتنينج', 'label_en' => 'Lightning'],
                ['value' => 'micro_usb', 'label_ar' => 'مايكرو USB', 'label_en' => 'Micro USB'],
                ['value' => 'usb_a', 'label_ar' => 'USB-A', 'label_en' => 'USB-A'],
                ['value' => 'hdmi', 'label_ar' => 'HDMI', 'label_en' => 'HDMI'],
                ['value' => 'aux', 'label_ar' => '3.5mm (AUX)', 'label_en' => '3.5mm (AUX)'],
            ],
        ],
        [
            'name' => 'power',
            'label_ar' => 'القدرة/الفولطية',
            'label_en' => 'Power',
            'values' => [
                ['value' => '5w', 'label_ar' => '5 واط', 'label_en' => '5W'],
                ['value' => '20w', 'label_ar' => '20 واط', 'label_en' => '20W'],
                ['value' => '65w', 'label_ar' => '65 واط', 'label_en' => '65W'],
                ['value' => '100w', 'label_ar' => '100 واط', 'label_en' => '100W'],
                ['value' => '110v', 'label_ar' => '110 فولت', 'label_en' => '110V'],
                ['value' => '220v', 'label_ar' => '220 فولت', 'label_en' => '220V'],
            ],
        ],
        [
            'name' => 'pattern',
            'label_ar' => 'النقشة/التصميم',
            'label_en' => 'Pattern',
            'values' => [
                ['value' => 'plain', 'label_ar' => 'سادة', 'label_en' => 'Plain'],
                ['value' => 'striped', 'label_ar' => 'مخطط', 'label_en' => 'Striped'],
                ['value' => 'checkered', 'label_ar' => 'مربعات', 'label_en' => 'Checkered'],
                ['value' => 'floral', 'label_ar' => 'ورود', 'label_en' => 'Floral'],
                ['value' => 'polka_dot', 'label_ar' => 'منقّط', 'label_en' => 'Polka Dot'],
                ['value' => 'printed', 'label_ar' => 'مطبوع', 'label_en' => 'Printed'],
            ],
        ],
        [
            'name' => 'warranty_period',
            'label_ar' => 'مدة الضمان',
            'label_en' => 'Warranty Period',
            'values' => [
                ['value' => 'no_warranty', 'label_ar' => 'بدون ضمان', 'label_en' => 'No Warranty'],
                ['value' => '3_months', 'label_ar' => '3 أشهر', 'label_en' => '3 Months'],
                ['value' => '6_months', 'label_ar' => '6 أشهر', 'label_en' => '6 Months'],
                ['value' => '1_year', 'label_ar' => 'سنة واحدة', 'label_en' => '1 Year'],
                ['value' => '2_years', 'label_ar' => 'سنتان', 'label_en' => '2 Years'],
            ],
        ],
    ];
}
