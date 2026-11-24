{{--
    ترويسة صفحة على قياس شاشة «سجلّ النشاطات» (`sitting::ActivityLogs.index`).

    نفس البنية والأصناف حرفياً: `content-header row` وعنوان `content-header-title`
    وفتات خبز `ol.breadcrumb` من قالب النظام، والإجراءات في `btn-group` يميناً.
    فما يضبطه «مظهر النظام» من لون وخطّ يسري عليها كما يسري على تلك الشاشة.

    كانت شاشات القوائم تستعملها وحدها، وانتقلت إلى `purchases::partials.head`
    (ترويسة `po-head`) لتطابق شكل ترويسة «فواتير المبيعات» — أيقونة فى مربّع
    بجانب العنوان. هذا القالب باقٍ لشاشتين لا تزالان تستدعيانه مباشرة:
    `supplier_payments/bulk_payment` و`supplier_management/statement`.

    @param string $title
    @param array<int, array{label: string, url?: string}> $crumbs
    @param string|null $action   HTML جاهز يُوضع في جهة الإجراءات
--}}
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">{{ $title }}</h2>

                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('purchases.dashboard') }}">{{ __('purchase_orders.breadcrumb.home') }}</a>
                        </li>

                        @foreach ($crumbs ?? [] as $crumb)
                            @if (! empty($crumb['url']))
                                <li class="breadcrumb-item">
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                                </li>
                            @else
                                <li class="breadcrumb-item active">{{ $crumb['label'] }}</li>
                            @endif
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if (! empty($action))
        <div class="content-header-right text-md-right col-md-3 col-12">
            <div class="btn-group">{!! $action !!}</div>
        </div>
    @endif
</div>
