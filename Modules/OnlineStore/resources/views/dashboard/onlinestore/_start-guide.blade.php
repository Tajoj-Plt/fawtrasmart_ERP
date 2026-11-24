{{--
    «ابدأ من هنا» — دليل تشغيل المتجر الإلكتروني أعلى لوحة المتجر.

    نسخةُ المتجر من دليل لوحة العملاء
    (Modules/Client/resources/views/dashboard/client/_start-guide.blade.php)
    بنفس الشكل والسلوك: خطواتٌ مرقّمة في مسارٍ واحد، لكل خطوة عنوانٌ هو
    فعل، سطرُ «لماذا؟» بلغةٍ بسيطة، حالتُها مقروءةً من النظام، وزرٌّ يسمّي
    الشاشة. مطويٌّ عند فتح الصفحة دائماً (عنصر <details>) — لا تذكّر ولا
    إخفاء دائم، كما في العملاء.

    ما يُقرأ من النظام لا يُكتب ثابتاً — كلّه عدٌّ خفيف يُحسب هنا (المتحكّم
    لا يمرّر شيئاً لهذا الجزء):
    - الإعدادات من `OnlineStoreSettingsService` نفسِها التي يقرؤها المتجر.
    - المستودع/الخزينة: الموسومان «تابع للمتجر» + المختار افتراضياً في الإعدادات.
    - الطلبات والكوبونات وطرق الشحن بنطاق الفرع النشط (`forBranch`) كبقية اللوحة.
    - الزرّ بصلاحية المسار؛ بلا صلاحية يظهر «يقوم بها المدير» ولا تختفي الخطوة.

    أسماء الأصناف `sg-*` محصورةٌ داخل `.os-guide` كي لا تصطدم بشيء في اللوحة.
--}}
@php
    use App\Services\OnlineStoreSettingsService as StoreSettings;

    $safe = function (callable $fn, $fallback = null) {
        try { return $fn(); } catch (\Throwable $e) { return $fallback; }
    };
    /* زرّ = [اسم المسار إن وُجد وللمستخدم صلاحيته وإلا null، نص الزرّ] */
    $btn = fn (string $route, string $permission, string $label) => [
        (hasPermission($permission) && \Illuminate\Support\Facades\Route::has($route)) ? $route : null,
        $label,
    ];
    $n = fn ($v) => number_format((int) $v);
    $branchId = $safe(fn () => current_branch_id());

    // ── 1) الإعدادات الأساسية
    $storeNameRow = $safe(fn () => \App\Models\Store\OnlineStoreSetting::where('key', 'store_name')->value('value'));
    $currencyCode = $safe(fn () => \App\Support\CurrencySettings::code(), '');
    $taxRate = $safe(fn () => StoreSettings::getTaxRate());
    $taxIncluded = $safe(fn () => StoreSettings::isTaxIncluded(), false);

    // ── 2) مستودع المتجر
    $storehouseCount = $safe(fn () => \App\Models\StoreHouse::onlineStore()->count());
    $defaultStorehouse = $safe(function () {
        $id = StoreSettings::get('default_storehouse_id');

        return $id ? \App\Models\StoreHouse::onlineStore()->where('status', 'active')->find($id) : null;
    });

    // ── 3) خزينة المتجر
    $treasuryCount = $safe(fn () => \App\Models\Account::onlineStore()->count());
    $defaultTreasury = $safe(function () {
        $id = StoreSettings::get('default_treasury_id');

        return $id ? \App\Models\Account::onlineStore()->find($id) : null;
    });

    // ── 4) طرق الدفع المفعّلة للمتجر
    $paymentCount = $safe(fn () => count(StoreSettings::getEnabledPaymentMethods()));

    // ── 5) الشحن
    $citiesCount = $safe(fn () => \App\Models\City::active()->count());
    $shippingCount = $safe(fn () => \App\Models\Store\ShippingMethod::forBranch($branchId)->active()->count());

    // ── 6) الكتالوج
    $categoriesCount = $safe(fn () => \App\Models\Category::count());
    $brandsCount = $safe(fn () => \App\Models\Store\Brand::count());

    // ── 7) المنتجات ورصيدها في مستودع المتجر الافتراضي
    $onlineProducts = $safe(fn () => \App\Models\Product::where('available_online', 1)->count());
    $stockedProducts = $safe(function () use ($defaultStorehouse) {
        if (! $defaultStorehouse) {
            return 0;
        }

        return \App\Models\ProductDetails::where('store_house_id', $defaultStorehouse->id)
            ->whereHas('product', fn ($q) => $q->where('available_online', 1))
            ->groupBy('product_id')
            ->havingRaw('SUM(quantity) > 0')
            ->pluck('product_id')
            ->count();
    });

    // ── 8) التسويق والمحتوى
    $couponsCount = $safe(fn () => \App\Models\Store\StoreCoupon::forBranch($branchId)->count());
    $bannersCount = $safe(fn () => \App\Models\Store\StoreBanner::count());
    $pagesCount = $safe(fn () => \App\Models\Store\StorePage::count());

    // ── 9) و10) اليومي
    $pendingOrders = $safe(fn () => \App\Models\Store\OnlineOrder::forBranch($branchId)->pending()->count());
    $pendingQuestions = $safe(fn () => \App\Models\Store\ProductQuestion::pending()->count());
    $pendingReviews = $safe(fn () => \App\Models\Store\ProductReview::pending()->count());

    /* حالة = ['ok'|'no', نص] أو null (خطوة بلا حالة تُقاس) */
    $onceSteps = [
        [
            'اضبط إعدادات المتجر',
            'اسم المتجر وشعاره وبيانات التواصل، ونسبة الضريبة وهل الأسعار شاملةٌ لها. العملة هي عملة النظام نفسها.',
            $storeNameRow === null && $taxRate === null ? null
                : (filled($storeNameRow)
                    ? ['ok', 'المتجر: '.$storeNameRow.' — العملة: '.$currencyCode.' — الضريبة: '.rtrim(rtrim(number_format((float) $taxRate, 2), '0'), '.').'% '.($taxIncluded ? '(الأسعار شاملة)' : '(تُضاف على السعر)')]
                    : ['no', 'لم يُكتب اسم المتجر بعد']),
            [$btn('store_settings.index', 'online_store.settings.view', 'اذهب إلى الإعدادات')],
        ],
        [
            'أنشئ مستودع المتجر واختره',
            'المتجر يبيع من مستودعٍ واحد: أنشئ مستودعاً وعلّم عليه «تابع للمتجر»، ثم اختره «المستودع الافتراضي» في إعدادات المتجر. بدونه يظهر كل منتج «غير متوفر».',
            $storehouseCount === null ? null
                : ($defaultStorehouse
                    ? ['ok', 'المستودع الافتراضي: '.$defaultStorehouse->name]
                    : ($storehouseCount > 0
                        ? ['no', 'عندك '.$n($storehouseCount).' مستودع للمتجر، لكن لم يُختر المستودع الافتراضي في الإعدادات']
                        : ['no', 'لا يوجد مستودع تابع للمتجر بعد'])),
            [
                $btn('store_storehouses.index', 'stock.warehouses.view', 'اذهب إلى المخازن'),
                $btn('store_settings.index', 'online_store.settings.view', 'اختره في الإعدادات'),
            ],
        ],
        [
            'أنشئ خزينة المتجر واخترها',
            'تدخل إليها مبالغ الطلبات المدفوعة. أنشئ خزينة أو حساباً بنكياً «تابعاً للمتجر»، ثم اختره «الخزينة الافتراضية» في إعدادات المتجر.',
            $treasuryCount === null ? null
                : ($defaultTreasury
                    ? ['ok', 'الخزينة الافتراضية: '.$defaultTreasury->name]
                    : ($treasuryCount > 0
                        ? ['no', 'عندك '.$n($treasuryCount).' خزينة للمتجر، لكن لم تُختر الخزينة الافتراضية في الإعدادات']
                        : ['no', 'لا توجد خزينة تابعة للمتجر بعد'])),
            [
                $btn('store_treasury.index', 'account.treasury.view', 'اذهب إلى الخزائن'),
                $btn('store_settings.index', 'online_store.settings.view', 'اخترها في الإعدادات'),
            ],
        ],
        [
            'فعّل طرق الدفع',
            'حدّد كيف يدفع العميل: عند الاستلام، تحويل بنكي، بطاقة… تُفعَّل من إعدادات المتجر (قسم طرق الدفع).',
            $paymentCount === null ? null
                : ($paymentCount > 0 ? ['ok', 'مفعّل: '.$n($paymentCount).' طريقة دفع'] : ['no', 'لا توجد طريقة دفع مفعّلة — لن يستطيع العميل إتمام الطلب']),
            [
                $btn('payment_methods.index', 'online_store.payment_methods.view', 'عرض طرق الدفع'),
                $btn('store_settings.index', 'online_store.settings.manage', 'فعّلها من الإعدادات'),
            ],
        ],
        [
            'أضف المدن وطرق الشحن',
            'المدن التي توصل إليها، وطرق الشحن وتكلفة كلٍّ منها (ثابتة، أو مجانية فوق مبلغٍ معيّن، أو استلام من الفرع).',
            $shippingCount === null ? null
                : ($shippingCount > 0
                    ? ['ok', 'موجود عندك: '.$n($shippingCount).' طريقة شحن مفعّلة و'.$n($citiesCount).' مدينة']
                    : ['no', 'لا توجد طريقة شحن مفعّلة في هذا الفرع']),
            [
                $btn('shipping_methods.index', 'online_store.shipping_methods.view', 'اذهب إلى طرق الشحن'),
                $btn('cities.index', 'online_store.geo.view', 'المدن'),
            ],
        ],
        [
            'أضف التصنيفات والعلامات التجارية',
            'مثل: عطور، إلكترونيات، ملابس… ليتصفّح العميل المتجر بسهولة ويفلتر المنتجات.',
            $categoriesCount === null ? null
                : ($categoriesCount > 0
                    ? ['ok', 'موجود عندك: '.$n($categoriesCount).' تصنيف و'.$n($brandsCount).' علامة تجارية']
                    : ['no', 'لم تُضف أي تصنيف بعد — ابدأ بها']),
            [
                $btn('store_categories.index', 'online_store.categories.view', 'اذهب إلى التصنيفات'),
                $btn('brands.index', 'online_store.brands.view', 'العلامات التجارية'),
            ],
        ],
        [
            'أضف المنتجات وأدخل رصيدها',
            'أضف المنتج بسعره وصوره وفعّل «يُعرض في المتجر». ثم أدخل كميته إلى مستودع المتجر بإذن إضافة مخزني أو بفاتورة مشتريات — المنتج بلا رصيد يظهر «غير متوفر».',
            $onlineProducts === null ? null
                : ($onlineProducts === 0
                    ? ['no', 'لا يوجد منتج معروض في المتجر بعد']
                    : ($stockedProducts > 0
                        ? ['ok', $n($onlineProducts).' منتج معروض، منها '.$n($stockedProducts).' له رصيد في مستودع المتجر']
                        : ['no', $n($onlineProducts).' منتج معروض، لكن لا رصيد لأيٍّ منها في مستودع المتجر'])),
            [
                $btn('store_products.index', 'online_store.products.view', 'اذهب إلى المنتجات'),
                $btn('online_store.permits.index', 'stock.permits.view', 'الأذون المخزنية'),
                $btn('store_purchase_invoices.index', 'purchases.invoices.view', 'فواتير الشراء'),
            ],
        ],
        [
            'جهّز العروض والمحتوى (اختياري)',
            'كوبونات الخصم، وبانرات الصفحة الرئيسية، وصفحات «من نحن» و«سياسة الإرجاع» و«الشروط».',
            $couponsCount === null ? null
                : ['ok', $n($couponsCount).' كوبون، '.$n($bannersCount).' بانر، '.$n($pagesCount).' صفحة'],
            [
                $btn('store_coupons.index', 'online_store.coupons.view', 'الكوبونات'),
                $btn('store_banners.index', 'online_store.banners.view', 'البانرات'),
                $btn('store_pages.index', 'online_store.pages.view', 'الصفحات'),
            ],
        ],
    ];

    $dailySteps = [
        [
            'تابع الطلبات الجديدة',
            'كل طلب يصل «قيد الانتظار»: أكّده، جهّزه، اشحنه، ثم سلّمه. عند التأكيد يُخصم المخزون ويُسجَّل القيد المحاسبي تلقائياً.',
            $pendingOrders === null ? null
                : ($pendingOrders > 0 ? ['no', $n($pendingOrders).' طلب ينتظر التأكيد'] : ['ok', 'لا طلبات تنتظر التأكيد']),
            [$btn('online_orders.index', 'online_store.orders.view', 'اذهب إلى الطلبات')],
        ],
        [
            'أجب على أسئلة العملاء وراجع التقييمات',
            'العميل يسأل عن المنتج من صفحته في المتجر؛ إجابتك تظهر تحت المنتج للجميع. والتقييم لا يظهر إلا بعد اعتماده.',
            $pendingQuestions === null ? null
                : (($pendingQuestions + (int) $pendingReviews) > 0
                    ? ['no', $n($pendingQuestions).' سؤال بلا إجابة، '.$n($pendingReviews).' تقييم ينتظر الاعتماد']
                    : ['ok', 'لا أسئلة ولا تقييمات معلّقة']),
            [
                $btn('product_questions.index', 'online_store.questions.view', 'أسئلة المنتجات'),
                $btn('product_reviews.index', 'online_store.reviews.view', 'التقييمات'),
            ],
        ],
        [
            'راجع التقارير',
            'المبيعات والطلبات والمنتجات الأكثر مبيعاً والعملاء والكوبونات والشحن — لتعرف ما يُباع وما يحتاج تحسيناً.',
            null,
            [$btn('onlinestore.reports.index', 'online_store.reports.view', 'اذهب إلى التقارير')],
        ],
    ];

    $doneCount = collect($onceSteps)->filter(fn ($s) => $s[2] && $s[2][0] === 'ok')->count();
    $measuredCount = collect($onceSteps)->filter(fn ($s) => $s[2] !== null)->count();
@endphp

<style>
    .os-guide {
        --sg-primary: var(--stk-primary, var(--app-primary, #7367f0));
        --sg-surface: var(--stk-surface, #fff);
        --sg-border: var(--stk-border, #ebe9f1);
        --sg-text: var(--stk-heading, #5e5873);
        --sg-muted: var(--stk-muted, #8a8795);
        --sg-success: var(--stk-success, #28c76f);
        --sg-danger: var(--stk-danger, #ea5455);
        --sg-radius: 10px;
    }
    .os-guide .sg {
        background: var(--sg-surface); border: 1px solid var(--sg-border);
        border-inline-start: 4px solid var(--sg-primary);
        border-radius: var(--sg-radius); margin-bottom: 1.25rem;
    }
    .os-guide .sg > summary {
        list-style: none; cursor: pointer; padding: .8rem 1.25rem;
        display: flex; align-items: center; gap: .6rem; color: var(--sg-text);
        background: color-mix(in srgb, var(--sg-primary) 7%, transparent);
        border-radius: var(--sg-radius); outline: none;
    }
    .os-guide .sg > summary:focus-visible { box-shadow: 0 0 0 2px color-mix(in srgb, var(--sg-primary) 45%, transparent); }
    .os-guide .sg[open] > summary { border-radius: var(--sg-radius) var(--sg-radius) 0 0; }
    .os-guide .sg > summary::-webkit-details-marker { display: none; }
    .os-guide .sg > summary::marker { content: ''; }
    .os-guide .sg__icon {
        width: 32px; height: 32px; border-radius: 50%; flex: none;
        display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--sg-primary) 12%, transparent); color: var(--sg-primary);
    }
    .os-guide .sg__head { flex: 1; min-width: 0; font-weight: 700; font-size: .95rem; }
    .os-guide .sg__head small { display: block; font-weight: 500; font-size: .78rem; color: var(--sg-muted); }
    .os-guide .sg__progress {
        font-size: .75rem; font-weight: 700; white-space: nowrap; color: var(--sg-primary);
        border: 1px solid color-mix(in srgb, var(--sg-primary) 35%, transparent);
        border-radius: 999px; padding: .2rem .65rem;
    }
    .os-guide .sg__progress--done { color: var(--sg-success); border-color: color-mix(in srgb, var(--sg-success) 45%, transparent); }
    .os-guide .sg__toggle {
        font-size: .8rem; font-weight: 700; white-space: nowrap; color: #fff; background: var(--sg-primary);
        border-radius: var(--app-button-radius, 6px); padding: .4rem .9rem;
    }
    .os-guide .sg[open] .sg__toggle .when-closed,
    .os-guide .sg:not([open]) .sg__toggle .when-open { display: none; }

    .os-guide .sg__body { padding: 1rem 1.25rem 1.1rem; max-width: 980px; }
    .os-guide .sg__group {
        font-size: .8rem; font-weight: 700; color: var(--sg-primary);
        margin: 0 0 .7rem; display: flex; align-items: center; gap: .4rem;
    }
    .os-guide .sg__group:not(:first-child) { margin-top: 1.3rem; }

    /* الخطّ الرأسي يصل الأرقام — يُقرأ مساراً واحداً لا قائمةً مبعثرة */
    .os-guide .sg-steps { list-style: none; margin: 0; padding: 0; }
    .os-guide .sg-step { position: relative; display: flex; gap: .85rem; padding-bottom: 1rem; }
    .os-guide .sg-step:last-child { padding-bottom: 0; }
    .os-guide .sg-step:not(:last-child)::after {
        content: ''; position: absolute; top: 2rem; bottom: .2rem; inset-inline-start: calc(1rem - 1px);
        width: 2px; background: var(--sg-border);
    }
    .os-guide .sg-step__num {
        width: 2rem; height: 2rem; border-radius: 50%; flex: none; z-index: 1;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .85rem; font-weight: 700; color: #fff; background: var(--sg-primary);
    }
    .os-guide .sg-step--ok .sg-step__num { background: var(--sg-success); }
    .os-guide .sg-step--no .sg-step__num { background: var(--sg-danger); }
    .os-guide .sg-step__main { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; align-items: flex-start; gap: .4rem 1rem; }
    .os-guide .sg-step__text { flex: 1 1 320px; min-width: 0; }
    .os-guide .sg-step__title { display: block; font-size: .9rem; font-weight: 700; color: var(--sg-text); margin-top: .25rem; }
    .os-guide .sg-step__why { margin: .15rem 0 0; font-size: .8rem; color: var(--sg-muted); line-height: 1.7; }
    .os-guide .sg-step__state { display: inline-flex; align-items: center; gap: .25rem; margin-top: .3rem; font-size: .75rem; font-weight: 700; }
    .os-guide .sg-step--ok .sg-step__state { color: var(--sg-success); }
    .os-guide .sg-step--no .sg-step__state { color: var(--sg-danger); }
    .os-guide .sg-step__btns { flex: none; align-self: center; display: flex; flex-wrap: wrap; gap: .35rem; }
    .os-guide .sg-step__btn {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .78rem; font-weight: 600; text-decoration: none; white-space: nowrap;
        color: var(--sg-primary); border: 1px solid var(--sg-primary);
        border-radius: var(--app-button-radius, 6px); padding: .35rem .8rem;
    }
    .os-guide .sg-step__btn:hover { background: var(--sg-primary); color: #fff; }
    .os-guide .sg-step__admin { flex: none; align-self: center; font-size: .75rem; color: var(--sg-muted); white-space: nowrap; }
</style>

<div class="os-guide">
    <details class="sg">
        <summary>
            <span class="sg__icon"><i class="feather icon-compass"></i></span>
            <span class="sg__head">
                ابدأ من هنا — كيف تشغّل المتجر الإلكتروني؟
                <small>خطوات مرقّمة بالترتيب، وأمام كل خطوة زرّ يأخذك إلى مكانها.</small>
            </span>
            @if ($measuredCount > 0)
                <span class="sg__progress {{ $doneCount === $measuredCount ? 'sg__progress--done' : '' }}">
                    اكتمل {{ $doneCount }} من {{ $measuredCount }}
                </span>
            @endif
            <span class="sg__toggle">
                <span class="when-closed">اعرض الخطوات <i class="feather icon-chevron-down"></i></span>
                <span class="when-open">إخفاء <i class="feather icon-chevron-up"></i></span>
            </span>
        </summary>

        <div class="sg__body">
            @php $stepNo = 0; @endphp
            @foreach ([['أولاً: جهّز المتجر — مرة واحدة فقط', 'settings', $onceSteps], ['ثانياً: كل يوم', 'sun', $dailySteps]] as [$groupTitle, $groupIcon, $groupSteps])
                <p class="sg__group"><i class="feather icon-{{ $groupIcon }}"></i> {{ $groupTitle }}</p>
                <ol class="sg-steps">
                    @foreach ($groupSteps as [$title, $why, $state, $buttons])
                        @php
                            $stepNo++;
                            $allowed = array_values(array_filter($buttons, fn ($b) => $b[0]));
                        @endphp
                        <li class="sg-step {{ $state ? 'sg-step--'.$state[0] : '' }}">
                            <span class="sg-step__num">
                                @if ($state && $state[0] === 'ok')<i class="feather icon-check"></i>@else{{ $stepNo }}@endif
                            </span>
                            <div class="sg-step__main">
                                <div class="sg-step__text">
                                    <span class="sg-step__title">{{ $stepNo }}. {{ $title }}</span>
                                    <p class="sg-step__why">{{ $why }}</p>
                                    @if ($state)
                                        <span class="sg-step__state">
                                            <i class="feather icon-{{ $state[0] === 'ok' ? 'check-circle' : 'alert-circle' }}"></i> {{ $state[1] }}
                                        </span>
                                    @endif
                                </div>
                                @if ($allowed)
                                    <div class="sg-step__btns">
                                        @foreach ($allowed as [$routeName, $label])
                                            <a href="{{ route($routeName) }}" class="sg-step__btn">{{ $label }} <i class="feather icon-arrow-left"></i></a>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="sg-step__admin"><i class="feather icon-lock"></i> يقوم بها المدير</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endforeach
        </div>
    </details>
</div>
