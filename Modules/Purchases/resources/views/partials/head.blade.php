{{--
    ترويسة صفحة — أيقونة، العنوان وفتات الخبز، ثمّ أزرار الإجراء.

    مشتركة بين أقسام دورة المشتريات ليخرج رأس كل شاشة على قياس واحد.
    على قياس ترويسة فواتير المبيعات (`sales::components.page-head`):
    أيقونة فى مربّع بلون النظام الأساسي إلى جانب العنوان، اختياريّة كي لا
    تنكسر الشاشات التي تستدعي هذا القالب من غير أن تُمرِّرها.

    @param string $title
    @param array<int, array{label: string, url?: string}> $crumbs
    @param string|null $icon     أيقونة: `fa-truck` تُقرأ FontAwesome، و`truck`
                                 أو `icon-truck` تُقرأ feather — انظر أدناه
    @param string|null $sub      سطرٌ شارح تحت العنوان — نظير `sub` في ترويسة المبيعات
    @param string|null $action   HTML جاهز يُوضع في جهة الإجراءات
    @param string|null $crumbStyle  'breadcrumb' يُخرج الفتات بوسم بوتستراب
                                 نفسِه الذي تستعمله ترويسة المبيعات. وما عداه
                                 يبقى على `po-head__crumbs` — فالشاشاتُ التي
                                 لا تُمرِّره لا يتغيّر رأسُها

    **الأيقونة بأيّ خطّ؟** شاشاتُ القسم كلُّها على FontAwesome، وشاشاتُ
    مستندات المبيعات على feather — وقد طُلب أن تتطابق شاشاتُ إنشاء مستندات
    المشتريات معها. فبدل تفريق القالب إلى نسختين يُقرأ الاسمُ نفسُه:
    ما بدأ بـ`fa-` فهو FontAwesome كما كان، وما عداه feather. والخطّان
    محمَّلان معاً في كل صفحة (`layouts.head_rtl` يجلب feather من القالب،
    و`purchases::master` يجلب FontAwesome).
--}}
@php
    // `@include` يمرّر متغيّرات القالب الأب كلَّها لا ما مُرِّر في المصفوفة
    // وحده — ففحصُ النوع يمنع متغيّراً محليّاً في الأب من أن يصير وسيطاً هنا
    $headSub = (isset($sub) && is_string($sub) && $sub !== '') ? $sub : null;
    $headIcon = (isset($icon) && is_string($icon) && $icon !== '') ? $icon : null;

    $headIconClass = $headIcon === null
        ? null
        : (str_starts_with($headIcon, 'fa-')
            ? 'fas '.$headIcon
            : 'feather '.(str_starts_with($headIcon, 'icon-') ? $headIcon : 'icon-'.$headIcon));

    $headCrumbs = (isset($crumbs) && is_array($crumbs)) ? $crumbs : [];
    $headCrumbStyle = (isset($crumbStyle) && is_string($crumbStyle)) ? $crumbStyle : null;
@endphp

<div class="po-head">
    <div class="po-head__titles">
        @if ($headIconClass)
            <span class="po-head__icon" aria-hidden="true"><i class="{{ $headIconClass }}"></i></span>
        @endif

        <div class="po-head__titles-text">
            <h2>{{ $title }}</h2>

            @if ($headSub)
                <p class="po-head__sub">{{ $headSub }}</p>
            @endif

            @if ($headCrumbStyle === 'breadcrumb')
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('purchases.dashboard') }}">{{ __('purchase_orders.breadcrumb.home') }}</a>
                        </li>

                        @foreach ($headCrumbs as $crumb)
                            @if (! empty($crumb['url']))
                                <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
                            @else
                                <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @else
                <nav class="po-head__crumbs" aria-label="breadcrumb">
                    <a href="{{ route('purchases.dashboard') }}">{{ __('purchase_orders.breadcrumb.home') }}</a>

                    @foreach ($headCrumbs as $crumb)
                        <span class="sep">/</span>
                        @if (! empty($crumb['url']))
                            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                        @else
                            <span>{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    @if (! empty($action))
        <div class="po-head__actions">{!! $action !!}</div>
    @endif
</div>
