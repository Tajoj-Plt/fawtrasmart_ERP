{{--
    «ابدأ من هنا» — خطواتُ تشغيل قسم المشتريات من نظامٍ فارغ، أعلى لوحة
    المشتريات. نظيرُ دليل لوحتَي العملاء والمبيعات؛ الرسمُ في
    `components.start-guide`.

    ما يُقرأ من النظام لا يُكتب ثابتاً: حالةُ كل خطوة عدٌّ على مستوى النظام
    كلِّه، وكلُّ عدٍّ داخل `try` — جدولٌ ناقص لا يُسقط اللوحة، تختفي حالتُه فقط.
    والزرُّ بصلاحية مساره نفسِها.
--}}
@php
    $pgSafe = function (callable $fn) {
        try { return $fn(); } catch (\Throwable $e) { return null; }
    };
    $pgStatus = fn (?int $n, string $unit) => $n === null ? null
        : ($n > 0 ? ['ok', 'موجود عندك: '.number_format($n).' '.$unit] : ['no', 'لم تُضف بعد — ابدأ بها']);

    $pgUnmapped = $pgSafe(fn () => \App\Models\AccountingSetting::active()->unmapped()->count());

    $pgOnce = [
        [
            'أضف الفروع',
            'كل فاتورة شراء تتبع فرعاً. بدون فرع لا يكتمل أي مستند.',
            $pgStatus($pgSafe(fn () => \App\Models\Branch::withoutGlobalScopes()->count()), 'فرع'),
            ['branches.create', hasPermission('branches.create'), 'اذهب إلى الفروع'],
        ],
        [
            'أضف مستودعاً',
            'البضاعة المشتراة تدخل إلى مستودع. يكفي مستودع واحد للبداية.',
            $pgStatus($pgSafe(fn () => \App\Models\StoreHouse::count()), 'مستودع'),
            ['storehouse.create', hasPermission('stock.warehouses.create'), 'اذهب إلى المستودعات'],
        ],
        [
            'أضف المنتجات',
            'هي الأصناف التي تشتريها وتختارها في فاتورة الشراء.',
            $pgStatus($pgSafe(fn () => \App\Models\Product::count()), 'منتج'),
            ['products.create', hasPermission('stock.products.edit'), 'اذهب إلى المنتجات'],
        ],
        [
            'أضف الموردين',
            'فاتورة الشراء تُسجَّل على مورد، ويُحسب له ما عليك وما سدّدت.',
            $pgStatus($pgSafe(fn () => \App\Models\Supplier::count()), 'مورد'),
            ['SupplierManagement.create', hasPermission('purchases.suppliers.create'), 'أضف مورداً'],
        ],
        [
            'أضف خزينة أو حساباً بنكياً',
            'المبلغ الذي تسدّده للمورد يخرج منها.',
            $pgStatus($pgSafe(fn () => \App\Models\Treasury::count()), 'خزينة وحساب'),
            ['treasury.create', hasPermission('account.treasury.create'), 'اذهب إلى الخزائن'],
        ],
        [
            'اضبط الضرائب',
            'حدّد نسبة الضريبة (مثل القيمة المضافة 15%) لتُحسب في فاتورة الشراء تلقائياً.',
            $pgStatus($pgSafe(fn () => \App\Models\TaxSitting::count()), 'ضريبة'),
            ['TaxSitting.index', hasPermission('settings_edit_tax_settings'), 'اذهب إلى إعدادات الضرائب'],
        ],
        [
            'اربط الحسابات المحاسبية',
            'فاتورة الشراء والاستلام يُسجَّلان قيداً محاسبياً. إن لم يُختر حساب المشتريات والمستودع والموردين توقّف الحفظ برسالة خطأ.',
            $pgUnmapped === null ? null
                : ($pgUnmapped > 0 ? ['no', number_format($pgUnmapped).' عملية لم يُختر لها حساب'] : ['ok', 'كل العمليات مربوطة بحساب']),
            ['AccountingSettings.index', hasPermission('settings.accounting_settings.view'), 'اذهب إلى الحسابات المحاسبية'],
        ],
        [
            'اضبط إعدادات المشتريات',
            'تحدّد فيها ترقيم المستندات، وهل تحتاج الفاتورة اعتماداً، وطريقة الاستلام والسداد.',
            null,
            ['purchases.settings.index', hasPermission('purchases.settings.view'), 'اذهب إلى إعدادات المشتريات'],
        ],
    ];

    $pgDaily = [
        [
            'سجّل فاتورة الشراء',
            'اختر المورد ثم الأصناف والكميات والأسعار، واحفظ. وإن كان الشراء يحتاج موافقة أولاً فابدأ بـ«طلب شراء» ثم «أمر شراء» ثم حوّله إلى فاتورة.',
            $pgStatus($pgSafe(fn () => \App\Models\PurchaseInvoice::where('type', 'invoice')->count()), 'فاتورة'),
            ['invoicePurchases.create', hasPermission('purchases.invoices.create'), 'أنشئ فاتورة شراء'],
        ],
        [
            'استلم البضاعة',
            'عند وصول بضاعة أمر الشراء سجّل استلامها واعتمده، فتدخل الكمية إلى المستودع.',
            null,
            ['PurchaseReceiving.index', hasPermission('purchases.receiving.view'), 'اذهب إلى الاستلام'],
        ],
        [
            'سدّد للمورد',
            'عندما تدفع: افتح فاتورة الشراء وسجّل الدفعة عليها، واختر الخزينة التي خرج منها المبلغ.',
            null,
            ['PaymentSupplier.indexPurchase', hasPermission('purchases.payments.view'), 'اذهب إلى مدفوعات الموردين'],
        ],
        [
            'سجّل المرتجع إن وُجد',
            'إذا أعدت بضاعة للمورد: افتح فاتورة الشراء وأنشئ لها مرتجعاً، فتخرج الكمية من المستودع ويُخصم المبلغ من حساب المورد.',
            null,
            ['ReturnsInvoice.index', hasPermission('purchases.returns.view'), 'اذهب إلى مرتجعات المشتريات'],
        ],
        [
            'المدير: راجع هذه اللوحة',
            'تعرض المشتريات والمدفوع والمستحق للموردين للفترة المختارة. اضغط أي رقم لترى الفواتير أو الموردين خلفه.',
            null,
            null,
        ],
    ];
@endphp

@include('components.start-guide', [
    'startGuideTitle' => 'ابدأ من هنا — كيف تشغّل قسم المشتريات؟',
    'startGuideHint' => 'خطوات مرقّمة بالترتيب، وأمام كل خطوة زرّ يأخذك إلى مكانها.',
    'startGuideGroups' => [
        ['أولاً: جهّز النظام — مرة واحدة فقط', 'settings', $pgOnce],
        ['ثانياً: كل يوم', 'sun', $pgDaily],
    ],
])
