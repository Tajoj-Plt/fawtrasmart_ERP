{{--
    لوحة تقارير الموارد البشرية — ثمانية تقارير، لكلٍّ بلاطةٌ واحدة.

    كانت ثلاثين بلاطة: كلُّ عرضٍ صفحةٌ وبلاطة. صارت عروضُ التقرير الواحد
    تبويباتٍ أعلى صفحته، وتظهر هنا روابطَ صغيرةً تحت بلاطته — المصدرُ واحد
    ({@see \Modules\HR\Support\HrReportCatalog}) للوحة وللتبويبات.
    التصميمُ لوحةُ تقارير العملاء نفسُها (`sl-tiles`)، وما خرج عن الباقة يبقى
    ظاهراً مقفولاً يقود إلى شاشة الاشتراك.
--}}
@extends('hr::master')

@section('title')
    تقارير الموارد البشرية
@stop

@section('content')
    @php
        use App\Constants\planFeatures\HrPlanFeatures;
        use App\Support\PlanLock;
        use Modules\HR\Support\HrReportCatalog;

        $locked = PlanLock::locked(HrPlanFeatures::REPORTS);
        $lockReason = $locked ? PlanLock::reason(HrPlanFeatures::REPORTS) : null;

        $cards = collect(HrReportCatalog::groups())
            ->map(fn ($g) => $g + ['visible' => HrReportCatalog::visibleTabs($g)])
            ->filter(fn ($g) => $g['visible'] !== [])
            ->values();
    @endphp

    @include('sales::partials.settings-theme')

    <div class="content-body sl-scope">
        @include('sales::components.page-head', [
            'title' => 'تقارير الموارد البشرية',
            'icon' => 'chart-bar',
            'sub' => 'الحضور، والرواتب، والسلف والخصميات، والموظفون',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="fas fa-chart-bar"></i> التقارير</h4>
                <span class="sl-count">{{ $cards->count() }}</span>
            </div>
            <div class="card-body">
                <div class="sl-tiles">
                    @foreach ($cards as $card)
                        @php $first = $card['visible'][0]; @endphp
                        <div class="sl-tile" @if ($locked) title="{{ $lockReason }}" @endif>
                            <span class="sl-tile__icon" aria-hidden="true"><i class="fas fa-{{ $locked ? 'lock' : $card['icon'] }}"></i></span>
                            <div class="sl-tile__body">
                                <p class="sl-tile__title">
                                    <a href="{{ $locked ? PlanLock::url() : route($first['route']) }}" style="color:inherit;text-decoration:none">{{ $card['title'] }}</a>
                                    @if ($locked) {!! PlanLock::menuBadge() !!} @endif
                                </p>
                                <p class="sl-tile__hint">{{ $card['hint'] }}</p>
                                @if (! $locked && count($card['visible']) > 1)
                                    <div class="sl-tile__kids">
                                        @foreach ($card['visible'] as $tab)
                                            <a class="sl-tile__kid" href="{{ route($tab['route']) }}">{{ $tab['title'] }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
