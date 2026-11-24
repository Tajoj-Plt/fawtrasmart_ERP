@extends('stock::master')

@section('title', 'إعدادات الجرد')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إعدادات الجرد',
            'icon' => 'settings',
            'sub' => 'الإعدادات العامة للجرد والمستودعات الافتراضية وقوالب الطباعة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات الجرد' => null,
            ],
        ])

        {{--
            كانت البلاطات صوراً من `app-assets` بلا شرح، ولون تمريرها مكتوباً
            في الصفحة (`#cdd2d8`) فلا يتبع الثيم. صارت أيقونات وعناوين وأسطراً
            تشرح وجهة كل بلاطة، بألوانٍ كلها من متغيّرات الثيم.
        --}}
        @php
            use App\Constants\planFeatures\StockPlanFeatures;
            use App\Support\PlanLock;

            /*
                هذه الشاشة كلّها تحت ميزة «إدارة المستودعات» — وهي ما يحرس
                بادئة `inventory_settings` في Modules/Stock/routes/web.php.
                فبلاطاتها تُقفَل ولا تُخفى حين تكون خارج الباقة.

                والقفل يفترق عن «قريباً»: ذاك وعدٌ بشيء لم يُبنَ، وهذا شيءٌ
                مبنيٌّ لم يُشترَ — ولهذا يقود إلى شاشة الاشتراك لا إلى لا شيء.
            */
            $planFeature = StockPlanFeatures::STOREHOUSES;
            $planLocked = PlanLock::locked($planFeature);

            $tiles = [
                [
                    'route' => 'inventory_settings.general',
                    'icon' => 'sliders',
                    'title' => 'عام',
                    'hint' => 'الإعدادات العامة لعمليات الجرد',
                ],
                [
                    'route' => 'inventory_settings.employee_default_warehouse',
                    'icon' => 'user-check',
                    'title' => 'المستودع الافتراضي للموظف',
                    'hint' => 'المستودع الذي يُفتح تلقائياً لكل موظف',
                ],
                /*
                    كانت `route => null` فتُعرض «قريباً» — بينما شاشة «قوالب
                    المستندات» في الإعدادات جاهزة وتعرّف نوعَي إذن الصرف
                    والاستلام، وطباعةُ الإذن تقرأ قالبها المعتمد منها فعلاً.
                */
                [
                    'route' => 'document-templates.index',
                    'icon' => 'printer',
                    'title' => 'قوالب الطباعة',
                    'hint' => 'قوالب مستندات المخزون (أذون الصرف والاستلام) — تُصمَّم وتُعتمد من شاشة قوالب المستندات',
                ],
            ];
        @endphp

        <div class="stk-tiles">
            @foreach ($tiles as $tile)
                @php
                    // البلاطة بلا وجهة تُعرض معطَّلةً لا كرابطٍ يوهم بأنه يعمل
                    $available = $tile['route'] && Route::has($tile['route']);

                    // القفل لا يقع على ما ليس له وجهة أصلاً: «قريباً» أصدق
                    $locked = $available && $planLocked;
                @endphp

                @if ($available)
                    <a href="{{ $locked ? PlanLock::url() : route($tile['route']) }}"
                       class="stk-tile {{ $locked ? 'stk-tile--locked' : '' }}"
                       @if ($locked) title="{{ PlanLock::reason($planFeature) }}" @endif>
                @else
                    <span class="stk-tile stk-tile--soon" aria-disabled="true">
                @endif

                    <span class="stk-tile__icon">
                        <i class="feather icon-{{ $locked ? 'lock' : $tile['icon'] }}"></i>
                    </span>
                    <span class="stk-tile__body">
                        <span class="stk-tile__title d-block">
                            {{ $tile['title'] }}
                            @unless ($available)
                                <span class="badge badge-light">قريباً</span>
                            @endunless
                            @if ($locked)
                                <span class="stk-plan-badge">
                                    <i class="feather icon-lock"></i> خارج الباقة
                                </span>
                            @endif
                        </span>
                        <span class="stk-tile__hint d-block">
                            {{ $locked ? 'غير مشمولة بباقتك الحالية — اضغط لعرض الاشتراك والترقية.' : $tile['hint'] }}
                        </span>
                    </span>

                @if ($available)
                    </a>
                @else
                    </span>
                @endif
            @endforeach
        </div>
    </div>
@endsection
