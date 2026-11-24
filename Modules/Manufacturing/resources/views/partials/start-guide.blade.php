{{--
    «ابدأ من هنا» — خطواتُ تشغيل قسم التصنيع من نظامٍ فارغ، أعلى لوحة
    التصنيع. نظيرُ دليل لوحات العملاء والمبيعات والمشتريات؛ الرسمُ في
    `components.start-guide`.

    ما يُقرأ من النظام لا يُكتب ثابتاً: حالةُ كل خطوة عدٌّ على مستوى النظام
    كلِّه، وكلُّ عدٍّ داخل `try` — جدولٌ ناقص لا يُسقط اللوحة، تختفي حالتُه فقط.
    والزرُّ بصلاحية مساره نفسِها.
--}}
@php
    $mgSafe = function (callable $fn) {
        try { return $fn(); } catch (\Throwable $e) { return null; }
    };
    $mgStatus = fn (?int $n, string $unit) => $n === null ? null
        : ($n > 0 ? ['ok', 'موجود عندك: '.number_format($n).' '.$unit] : ['no', 'لم تُضف بعد — ابدأ بها']);

    // حسابات التصنيع وحدها — لا كلُّ ما في «الحسابات المحاسبية». والمفاتيح من
    // `ManufacturingSources` نفسِها التي يقرؤها الترحيل؛ ومفتاحٌ بلا صفٍّ أصلاً
    // يُعدّ غير مربوط كذلك
    $mgAccountKeys = [
        \Modules\Manufacturing\Constants\ManufacturingSources::ACCOUNT_RAW_MATERIALS,
        \Modules\Manufacturing\Constants\ManufacturingSources::ACCOUNT_WIP,
        \Modules\Manufacturing\Constants\ManufacturingSources::ACCOUNT_FINISHED_GOODS,
        \Modules\Manufacturing\Constants\ManufacturingSources::ACCOUNT_WASTE,
        \Modules\Manufacturing\Constants\ManufacturingSources::ACCOUNT_OVERHEAD,
        \Modules\Manufacturing\Constants\ManufacturingSources::ACCOUNT_PRODUCTION_VARIANCE,
    ];
    $mgUnmapped = $mgSafe(fn () => count($mgAccountKeys) - \App\Models\AccountingSetting::active()
        ->whereIn('key', $mgAccountKeys)->whereNotNull('account_id')->count());

    $mgOnce = [
        [
            'أضف مستودعاً',
            'المواد الخام تُصرف من مستودع، والمنتج النهائي يدخل إلى مستودع. يكفي مستودع واحد للبداية.',
            $mgStatus($mgSafe(fn () => \App\Models\StoreHouse::count()), 'مستودع'),
            ['storehouse.create', hasPermission('stock.warehouses.create'), 'اذهب إلى المستودعات'],
        ],
        [
            'أضف المواد الخام والمنتج النهائي',
            'كلاهما صنف في المنتجات. في بطاقة الصنف اختر نوع المادة: «خام» لما تستهلكه، و«نهائي» لما تنتجه.',
            $mgStatus($mgSafe(fn () => \App\Models\Product::count()), 'صنف'),
            ['products.create', hasPermission('stock.products.edit'), 'اذهب إلى المنتجات'],
        ],
        [
            'أدخل رصيد المواد الخام',
            'لا يُصرف لأمر التصنيع إلا ما هو موجود في المستودع. أدخل الكميات بفاتورة شراء.',
            null,
            ['invoicePurchases.create', hasPermission('purchases.invoices.create'), 'أنشئ فاتورة شراء'],
        ],
        [
            'أضف محطات العمل',
            'المكان أو الآلة التي يتم عليها التصنيع، مع تكلفة ساعتها (أجور ومصروفات) لتدخل في تكلفة المنتج.',
            $mgStatus($mgSafe(fn () => \App\Models\WorkStations::withoutGlobalScopes()->count()), 'محطة'),
            ['manufacturing.workstations.create', hasPermission('stock.workstations.create'), 'أضف محطة عمل'],
        ],
        [
            'أضف مسارات الإنتاج',
            'المراحل التي يمرّ بها المنتج بالترتيب، مثل: خلط ثم تعبئة ثم تغليف.',
            $mgStatus($mgSafe(fn () => \App\Models\ProductionPath::withoutGlobalScopes()->count()), 'مسار'),
            ['manufacturing.paths.create', hasPermission('stock.production_paths.create'), 'أضف مسار إنتاج'],
        ],
        [
            'أنشئ قائمة مواد الإنتاج',
            'هي وصفة المنتج: ما المواد الخام وكم كميتها لإنتاج كمية محددة. تختارها في أمر التصنيع فتُنسخ بنودها تلقائياً.',
            $mgStatus($mgSafe(fn () => \App\Models\ProductionMaterials::withoutGlobalScopes()->count()), 'قائمة'),
            ['BOM.create', hasPermission('stock.bom.create'), 'أنشئ قائمة مواد'],
        ],
        [
            'اربط حسابات التصنيع',
            'صرف المواد وإنهاء الأمر يُسجَّلان قيداً محاسبياً. اختر حسابات المواد الخام، وتحت التشغيل، والبضاعة الجاهزة، والهالك، وتكاليف التشغيل، وانحراف الإنتاج — وبدونها تتوقف العملية برسالة خطأ.',
            $mgUnmapped === null ? null
                : ($mgUnmapped > 0 ? ['no', number_format($mgUnmapped).' حساب تصنيع لم يُختر بعد'] : ['ok', 'كل حسابات التصنيع مربوطة']),
            ['AccountingSettings.index', hasPermission('settings.accounting_settings.view'), 'اذهب إلى الحسابات المحاسبية'],
        ],
        [
            'اضبط إعدادات التصنيع',
            'تحدّد فيها حقول أمر التصنيع المطلوبة والمخفية، وهل يُسمح بإنتاج كمية أكبر من المخططة، وحجز المواد.',
            null,
            ['Manufacturing.settings.index', hasPermission('stock.settings.manufacturing'), 'اذهب إلى إعدادات التصنيع'],
        ],
    ];

    $mgDaily = [
        [
            'أنشئ أمر التصنيع',
            'اختر المنتج والكمية وقائمة المواد، ثم احفظ.',
            $mgStatus($mgSafe(fn () => \App\Models\ManufacturOrders::withoutGlobalScopes()->count()), 'أمر تصنيع'),
            ['manufacturing.orders.create', hasPermission('stock.manufacturing_orders.create'), 'أنشئ أمر تصنيع'],
        ],
        [
            'اصرف المواد ثم أنهِ الأمر',
            'افتح الأمر: استلم المواد من المستودع، وبعد انتهاء الإنتاج أنهِ الأمر بالكمية الفعلية، فيدخل المنتج المستودع بتكلفته. وما زاد من المواد أرجعه من الشاشة نفسها.',
            null,
            ['manufacturing.orders.index', hasPermission('stock.manufacturing_orders.view'), 'اذهب إلى أوامر التصنيع'],
        ],
        [
            'وزّع التكاليف غير المباشرة — اختياري',
            'مصروفات لا تخصّ أمراً بعينه مثل الكهرباء والإيجار: سجّلها لفترة، ثم اخترها في أمر التصنيع لتدخل في تكلفته.',
            null,
            ['manufacturing.indirectcosts.index', hasPermission('stock.indirect_costs.view'), 'اذهب إلى التكاليف غير المباشرة'],
        ],
        [
            'المدير: راجع هذه اللوحة والتقارير',
            'اللوحة تعرض الأوامر الجارية والمتأخرة، والتقارير تعرض تكلفة كل أمر والهالك وأداء المحطات.',
            null,
            ['manufacturing.reports.index', hasPermission('stock.reports.manufacturing'), 'اذهب إلى تقارير التصنيع'],
        ],
    ];
@endphp

@include('components.start-guide', [
    'startGuideTitle' => 'ابدأ من هنا — كيف تشغّل قسم التصنيع؟',
    'startGuideHint' => 'خطوات مرقّمة بالترتيب، وأمام كل خطوة زرّ يأخذك إلى مكانها.',
    'startGuideGroups' => [
        ['أولاً: جهّز النظام — مرة واحدة فقط', 'settings', $mgOnce],
        ['ثانياً: كل يوم', 'sun', $mgDaily],
    ],
])
