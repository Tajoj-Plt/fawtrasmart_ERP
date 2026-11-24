{{--
    «ابدأ من هنا» — خطواتُ تشغيل قسم المبيعات من نظامٍ فارغ، أعلى لوحة
    المبيعات. نظيرُ دليل لوحة العملاء؛ الرسمُ في `components.start-guide`.

    ما يُقرأ من النظام لا يُكتب ثابتاً: حالةُ كل خطوة عدٌّ على مستوى النظام
    كلِّه، وكلُّ عدٍّ داخل `try` — جدولٌ ناقص لا يُسقط اللوحة، تختفي حالتُه فقط.
    والزرُّ بصلاحية مساره نفسِها.
--}}
@php
    $sgSafe = function (callable $fn) {
        try { return $fn(); } catch (\Throwable $e) { return null; }
    };
    $sgStatus = fn (?int $n, string $unit) => $n === null ? null
        : ($n > 0 ? ['ok', 'موجود عندك: '.number_format($n).' '.$unit] : ['no', 'لم تُضف بعد — ابدأ بها']);

    $sgUnmapped = $sgSafe(fn () => \App\Models\AccountingSetting::active()->unmapped()->count());

    $sgOnce = [
        [
            'أضف الفروع',
            'كل فاتورة وكل عميل يتبع فرعاً. بدون فرع لا يكتمل أي مستند.',
            $sgStatus($sgSafe(fn () => \App\Models\Branch::withoutGlobalScopes()->count()), 'فرع'),
            ['branches.create', hasPermission('branches.create'), 'اذهب إلى الفروع'],
        ],
        [
            'أضف مستودعاً',
            'البضاعة المباعة تخرج من مستودع. يكفي مستودع واحد للبداية.',
            $sgStatus($sgSafe(fn () => \App\Models\StoreHouse::count()), 'مستودع'),
            ['storehouse.create', hasPermission('stock.warehouses.create'), 'اذهب إلى المستودعات'],
        ],
        [
            'أضف المنتجات والخدمات',
            'هي البنود التي تختارها في الفاتورة، مع سعر البيع لكل منها.',
            $sgStatus($sgSafe(fn () => \App\Models\Product::count()), 'منتج'),
            ['products.create', hasPermission('stock.products.edit'), 'اذهب إلى المنتجات'],
        ],
        [
            'أضف خزينة أو حساباً بنكياً',
            'المبلغ الذي تحصّله من العميل يدخل فيها.',
            $sgStatus($sgSafe(fn () => \App\Models\Treasury::count()), 'خزينة وحساب'),
            ['treasury.create', hasPermission('account.treasury.create'), 'اذهب إلى الخزائن'],
        ],
        [
            'اضبط الضرائب',
            'حدّد نسبة الضريبة (مثل القيمة المضافة 15%) لتُحسب في الفاتورة تلقائياً.',
            $sgStatus($sgSafe(fn () => \App\Models\TaxSitting::count()), 'ضريبة'),
            ['TaxSitting.index', hasPermission('settings_edit_tax_settings'), 'اذهب إلى إعدادات الضرائب'],
        ],
        [
            'اربط الحسابات المحاسبية',
            'حتى تُسجَّل قيود الفواتير والتحصيل على الحسابات الصحيحة في شجرة الحسابات.',
            $sgUnmapped === null ? null
                : ($sgUnmapped > 0 ? ['no', number_format($sgUnmapped).' عملية لم يُختر لها حساب'] : ['ok', 'كل العمليات مربوطة بحساب']),
            ['AccountingSettings.index', hasPermission('settings.accounting_settings.view'), 'اذهب إلى الحسابات المحاسبية'],
        ],
        [
            'اضبط إعدادات المبيعات',
            'تحدّد فيها شكل الفاتورة وترقيمها، والخصومات، وشروط الدفع، وما يظهر من مستندات (عروض الأسعار، أوامر البيع…).',
            null,
            ['sittingInvoice.index', hasPermission('sales.settings.view'), 'اذهب إلى إعدادات المبيعات'],
        ],
        [
            'أضف العملاء',
            'الفاتورة تصدر باسم عميل. وتجد خطوات تجهيز العملاء كاملةً في لوحة العملاء.',
            $sgStatus($sgSafe(fn () => \App\Models\Client::withoutGlobalScopes()->count()), 'عميل'),
            ['clients.create', hasPermission('clients_add_client'), 'أضف عميلاً'],
        ],
    ];

    $sgDaily = [
        [
            'أصدر فاتورة المبيعات',
            'اختر العميل ثم المنتجات والكميات، واحفظ. وإن أردت عرض سعر أولاً فابدأ من «عروض الأسعار» ثم حوّله إلى فاتورة.',
            $sgStatus($sgSafe(fn () => \App\Models\Invoice::where('type', 'normal')->count()), 'فاتورة'),
            ['invoices.create', hasPermission('sales.invoices.create'), 'أنشئ فاتورة'],
        ],
        [
            'سجّل التحصيل',
            'عندما يدفع العميل: افتح فاتورته وسجّل الدفعة عليها، واختر الخزينة التي استلمت المبلغ.',
            null,
            ['paymentsClient.index', hasPermission('sales.payments.view'), 'اذهب إلى مدفوعات العملاء'],
        ],
        [
            'سجّل المرتجع إن وُجد',
            'إذا أعاد العميل بضاعة: افتح فاتورته وأنشئ لها مرتجعاً، فترجع الكمية إلى المستودع ويُخصم المبلغ من حسابه.',
            null,
            ['ReturnIInvoices.index', hasPermission('sales.returns.view'), 'اذهب إلى الفواتير المرتجعة'],
        ],
        [
            'المدير: راجع هذه اللوحة',
            'تعرض المبيعات والتحصيل والمتأخرات للفترة المختارة. اضغط أي رقم لترى الفواتير أو العملاء خلفه.',
            null,
            null,
        ],
    ];
@endphp

@include('components.start-guide', [
    'startGuideTitle' => 'ابدأ من هنا — كيف تشغّل قسم المبيعات؟',
    'startGuideHint' => 'خطوات مرقّمة بالترتيب، وأمام كل خطوة زرّ يأخذك إلى مكانها.',
    'startGuideGroups' => [
        ['أولاً: جهّز النظام — مرة واحدة فقط', 'settings', $sgOnce],
        ['ثانياً: كل يوم', 'sun', $sgDaily],
    ],
])
