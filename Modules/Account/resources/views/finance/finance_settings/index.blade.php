@extends('account::master')

@section('title', 'الإعدادات المالية')

@section('content')
    @php
        /*
            كانت الشاشة ثلاث بطاقاتٍ متطابقة تحمل صوراً PNG من مجلّد القالب
            (`safe.png`, `expenses.png`) — صورٌ ثابتة لا تتبع «مظهر النظام»
            ولا تتغيّر بتغييره، وبلا سطرٍ يقول ماذا يفعل كل قسم.
        */
        /*
            `perm` يطابق ما يفرضه مسار كل بلاطة فعلياً — كانت البلاطات
            الثلاث تُفلتَر بوجود المسار وحده (`Route::has`) بلا أي فحص
            صلاحية.
        */
        $tiles = [
            [
                'route' => 'finance_settings.treasury_employee',
                'perm' => 'account.treasury_employees.view',
                'icon' => 'briefcase',
                'title' => 'خزائن الموظفين',
                'hint' => 'الخزينة الافتراضية لكل موظف — منها يُصرَف وإليها يُقبَض حين لا يختار غيرها',
            ],
            [
                'route' => 'finance_settings.expenses_category',
                'perm' => 'account.settings.expense_categories',
                'icon' => 'file-minus',
                'title' => 'تصنيفات المصروفات',
                'hint' => 'تُبنى عليها تقارير المصروفات ويُوجَّه بها حساب كل سند صرف',
            ],
            [
                'route' => 'finance_settings.receipt_category',
                'perm' => 'account.settings.receipt_categories',
                'icon' => 'file-plus',
                'title' => 'تصنيفات سندات القبض',
                'hint' => 'تصنيف المقبوضات لتقاريرها ولتوجيه حساباتها',
            ],
        ];
    @endphp

    <div class="content-body">

        @include('account::components.page-head', [
            'title' => 'الإعدادات المالية',
            'icon' => 'settings',
            'sub' => 'خزائن الموظفين وتصنيفات المصروفات والمقبوضات',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المالية' => null,
                'الإعدادات' => null,
            ],
        ])

        <div class="card">
            <div class="card-body">
                <div class="stk-tiles">
                    @php
                        /*
                            هذه الشاشة كلّها تحت ميزة «الخزينة والصناديق» — وهي ما يحرس بادئة
                `finance_settings` في Modules/Account/routes/web.php.

                            والبلاطة خارج الباقة **تُقفَل ولا تُخفى**: إخفاؤها يجعل
                            المنشأة لا تعرف أنها موجودة فلا تسأل عنها ولا تشتريها.
                            أمّا صلاحية النظام (`perm`) فتُخفي كما كانت.
                        */
                        $planLocked = \App\Support\PlanLock::locked(
                            \App\Constants\planFeatures\AccountPlanFeatures::TREASURY
                        );
                    @endphp

                    @foreach ($tiles as $tile)
                        @continue (! Route::has($tile['route']) || ! hasPermission($tile['perm']))
                        <a href="{{ $planLocked ? \App\Support\PlanLock::url() : route($tile['route']) }}"
                           class="stk-tile {{ $planLocked ? 'stk-tile--locked' : '' }}"
                           @if ($planLocked) title="{{ \App\Support\PlanLock::reason(\App\Constants\planFeatures\AccountPlanFeatures::TREASURY) }}" @endif>
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
