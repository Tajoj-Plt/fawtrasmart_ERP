@extends('hr::master')

@section('title', 'تقارير الموظفين')

@section('content')
    @php
        use App\Constants\planFeatures\HrPlanFeatures;
        use App\Support\PlanLock;

        $locked = PlanLock::locked(HrPlanFeatures::REPORTS);
        $lockReason = $locked ? PlanLock::reason(HrPlanFeatures::REPORTS) : null;

        $visible = collect($tiles)->filter(
            fn ($tile) => Route::has($tile['route']) && hasPermission($tile['permission'])
        );
    @endphp

    @include('stock::components.page-head', [
        'title' => 'تقارير الموظفين',
        'icon' => 'users',
        'sub' => 'القائمة والحالات والتعيينات والعقود والوثائق والهيكل',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الموارد البشرية' => route('hr.dashboard.index'),
            'تقارير الموظفين' => null,
        ],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @if ($visible->isEmpty())
            <div class="card">
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-bar-chart-2"></i></div>
                        <h5 class="stk-empty__title">لا تقارير متاحة لك</h5>
                        <p class="stk-empty__hint">
                            هذه الشاشة تحتاج صلاحية أحد تقارير الموظفين.
                            راجع مدير النظام لإضافتها إلى دورك.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-users"></i> تقارير الموظفين
                    </h6>
                    <span class="stk-count">{{ $visible->count() }}</span>
                </div>

                <div class="p-1">
                    <div class="stk-tiles">
                        @foreach ($visible as $tile)
                            <a href="{{ $locked ? PlanLock::url() : route($tile['route']) }}"
                                class="stk-tile {{ $locked ? 'stk-tile--locked' : '' }}"
                                @if ($locked) title="{{ $lockReason }}" @endif>
                                <span class="stk-tile__icon">
                                    <i class="feather icon-{{ $locked ? 'lock' : $tile['icon'] }}"></i>
                                </span>
                                <span class="stk-tile__body">
                                    <span class="stk-tile__title d-block">
                                        {{ $tile['title'] }}
                                        @if ($locked)
                                            {!! PlanLock::menuBadge() !!}
                                        @endif
                                    </span>
                                    <span class="stk-tile__hint d-block">{{ $tile['hint'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
@stop
