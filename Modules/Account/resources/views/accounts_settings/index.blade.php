@extends('account::master')

@section('title', 'إعدادات الحسابات')

@section('content')
    @php
        /*
            كانت الشاشة أربع بطاقاتٍ متطابقة بأيقوناتٍ بحجم `fa-8x` — نصفُ
            الشاشة أيقونات، ولا سطرَ يقول ماذا تفعل كلٌّ منها. والتمييز بين
            «الفترات المالية» و«فترة مغلقة» متروكٌ للتخمين.

            صارت بلاطاتٍ من نظام التصميم المعتمد: أيقونةٌ بحجمها، وعنوانٌ،
            وسطرٌ يشرح، وترتيبٌ يتبع تسلسل الاستعمال — السنة تُعرَّف أولاً،
            ثم تُقفَل فتراتها، ثم تُوجَّه الحسابات.
        */
        /*
            كل بلاطةٍ الآن بمفتاح `perm` يطابق ما يفرضه مسارها فعلياً
            (`Modules/Account/routes/web.php`) — كانت البلاطات تُفلتَر بوجود
            المسار وحده (`Route::has`)، فتظهر لأيّ مستخدم وصل شاشة الإعدادات
            بصرف النظر عن صلاحيته الفعلية على كل قسم.
        */
        $tiles = [
            [
                'route' => 'accounts_settings.financial_years',
                'perm' => 'account.settings.fiscal_years',
                'icon' => 'calendar',
                'title' => 'السنوات المالية',
                'hint' => 'بداية السنة المالية ونهايتها — يبني عليها كل تقرير مداه الافتراضي',
            ],
            [
                'route' => 'accounts_settings.closed_periods',
                'perm' => 'settings.accounting_periods.view',
                'icon' => 'lock',
                'title' => 'الفترات المقفلة',
                'hint' => 'الفترة المقفلة لا تُقبل فيها حركةٌ محاسبية جديدة ولا تعديل',
            ],
            [
                'route' => 'accounts_settings.accounts_routing',
                'perm' => 'account.settings.routing',
                'icon' => 'git-branch',
                'title' => 'توجيه الحسابات',
                'hint' => 'أي حسابٍ يستقبل قيد كل عملية — المبيعات والمشتريات والضرائب',
            ],
            [
                'route' => 'accounts_settings.accounting_general',
                'perm' => 'account.settings.general',
                'icon' => 'sliders',
                'title' => 'الإعدادات العامة',
                'hint' => 'طريقة التكلفة وسياسة الترحيل وضوابط القيد',
            ],
        ];
    @endphp

    <div class="content-body">

        @include('account::components.page-head', [
            'title' => 'إعدادات الحسابات',
            'icon' => 'settings',
            'sub' => 'ضوابط الدورة المحاسبية: السنوات والفترات وتوجيه الحسابات',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الحسابات' => null,
                'الإعدادات' => null,
            ],
        ])

        <div class="card">
            <div class="card-body">
                <div class="stk-tiles">
                    @php
                        /*
                            هذه الشاشة كلّها تحت ميزة «دليل الحسابات» — وهي ما يحرس بادئة
                `accounts_settings` في Modules/Account/routes/web.php.

                            والبلاطة خارج الباقة **تُقفَل ولا تُخفى**: إخفاؤها يجعل
                            المنشأة لا تعرف أنها موجودة فلا تسأل عنها ولا تشتريها.
                            أمّا صلاحية النظام (`perm`) فتُخفي كما كانت.
                        */
                        $planLocked = \App\Support\PlanLock::locked(
                            \App\Constants\planFeatures\AccountPlanFeatures::CHART_OF_ACCOUNTS
                        );
                    @endphp

                    @foreach ($tiles as $tile)
                        @continue (! Route::has($tile['route']) || ! hasPermission($tile['perm']))
                        <a href="{{ $planLocked ? \App\Support\PlanLock::url() : route($tile['route']) }}"
                           class="stk-tile {{ $planLocked ? 'stk-tile--locked' : '' }}"
                           @if ($planLocked) title="{{ \App\Support\PlanLock::reason(\App\Constants\planFeatures\AccountPlanFeatures::CHART_OF_ACCOUNTS) }}" @endif>
                            <span class="stk-tile__icon">
                                <i class="feather icon-{{ $planLocked ? 'lock' : $tile['icon'] }}"></i>
                            </span>
                            <span class="stk-tile__body">
                                <span class="stk-tile__title d-block">
                                    {{ $tile['title'] }}
                                    @if ($planLocked)
                                        <span class="stk-plan-badge">
                                            <i class="feather icon-lock"></i> خارج الباقة
                                        </span>
                                    @endif
                                </span>
                                <span class="stk-tile__hint d-block">
                                    {{ $planLocked ? 'غير مشمولة بباقتك الحالية — اضغط لعرض الاشتراك والترقية.' : $tile['hint'] }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
