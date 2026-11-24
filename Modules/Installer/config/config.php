<?php

return [
    'name' => 'Installer',

    /** يُكتب في ملف علامة التثبيت — مرجعٌ عند الترقية. */
    'version' => '1.0.0',

    /**
     * القيم المعروضة مبدئياً في خطوة الإعدادات. تُغيَّر من هنا حين يُوزَّع
     * النظام على سوقٍ له افتراضات أخرى، فلا تُلاحَق داخل القوالب.
     */
    'defaults' => [
        'language' => 'ar',
        'currency' => 'SAR',
        'timezone' => 'Asia/Riyadh',
        'business_type' => 'products',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
        'tax_percent' => 15,
        'tax_type' => 'excluded',
        'country' => 'المملكة العربية السعودية',
    ],
];
