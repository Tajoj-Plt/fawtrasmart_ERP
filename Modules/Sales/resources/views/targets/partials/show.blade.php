{{--
    تفاصيل الهدف.

    **هذه الشاشة لم تكن موجودة.** المسار `sales-targets.show` مسجَّل،
    و`SalesTargetController::show()` يجمع بياناته ويستدعي
    `view('sales::targets.show')` — وذلك القالب لا وجود له في المشروع، ولا
    نظيره في التحصيلات. فكلّ ضغطةٍ على اسم هدفٍ كانت ترتدّ
    `InvalidArgumentException: View [sales::targets.show] not found`، أي
    صفحة ٥٠٠. وهي ثغرةٌ صامتة: القائمة تعمل، والإنشاء يعمل، والتعديل
    يعمل — ويسقط «العرض» وحده.

    ما تعرضه: ضبطُ الهدف، ومستوياتُ عمولته، ومن أُسند إليهم وأين بلغ كلٌّ
    منهم، ثمّ سجلُّ التسويات المنفَّذة عليه.

    المتغيّرات:
      $screen, $target, $totalAchieved, $totalTarget, $overallPercentage
--}}
@php
    $r = $screen['routes'];
    $canManage = hasPermission('sales.targets.manage');
    $canSettle = hasPermission('sales.targets.settle');

    $tiers = $target->commissionTiers->sortBy('achievement_percentage');
    $settlements = $target->settlements;
    $isActive = $target->status === 'active';

    $money = fn ($v) => \App\Support\CurrencySettings::format((float) $v);
    // التاريخ بتنسيق اللغة النشطة — `LanguageSettings::formatDate` تقرأ
    // `date_format` من `system_languages`، فيتبع العرضُ ما ضُبط في «اللغات»
    $date = fn ($v) => $v ? \App\Support\LanguageSettings::formatDate($v) : '—';
@endphp

<div class="content-body sl-scope">

    @section('page-actions')
        @if ($canManage || $canSettle)
            <a href="{{ route($r['report'], $target->id) }}" class="btn btn-primary">
                <i class="feather icon-bar-chart-2"></i> {{ __('sales_targets.actions.report') }}
            </a>
        @endif

        @if ($canManage)
            <a href="{{ route($r['edit'], $target->id) }}" class="btn btn-outline-primary">
                <i class="feather icon-edit-2"></i> {{ __('sales_settings.ui.edit') }}
            </a>
        @endif

        <a href="{{ route($r['index']) }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
            {{ __('sales_targets.actions.back_to_list') }}
        </a>
    @endsection

    @include('sales::components.page-head', [
        'title' => $target->name,
        'icon' => $screen['icon'],
        'sub' => $screen['achievement_source'],
        'crumbs' => [
            __('sales_settings.ui.home') => route('dashboard'),
            $screen['title'] => route($r['index']),
            $target->name => null,
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ══════════ إرشادُ الشاشة ══════════
         كانت في `targets/show` وحدها، فتحرمها شاشةُ التحصيلات. موضعُها
         القالبُ المشترك ليصل النوعين معاً --}}
    @include('sales::components.guide', [
        'key' => $screen['guide'].'.show',
        'title' => __('sales_targets.guide.show.title'),
        'lead' => __('sales_targets.guide.show.lead'),
        'open' => false,
        'blocks' => [
            [
                __('sales_targets.guide.show.a'),
                __('sales_targets.guide.show.b'),
            ],
        ],
    ])

    {{-- ══════════ الإحصاء ══════════ --}}
    <div class="sl-stats">
        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-users"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($target->achievements->count(), 0) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.stats.employees') }}</p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon"><i class="feather icon-{{ $screen['icon'] }}"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.25rem">{{ $money($totalTarget) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.stats.target_total') }}</p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-check-circle"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.25rem">{{ $money($totalAchieved) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.stats.achieved_total') }}</p>
                @isset($cycle)
                    <small class="sl-help">{{ __('sales_targets.stats.current_cycle', ['from' => $date($cycle[0]), 'to' => $date($cycle[1])]) }}</small>
                @endisset
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--warning"><i class="feather icon-percent"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($overallPercentage, 1) }}%</p>
                <p class="sl-stat__label">{{ __('sales_targets.stats.percentage') }}</p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-file-text"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($settlements->count(), 0) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.stats.settlements') }}</p>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- ══════════ ضبط الهدف ══════════ --}}
        <div class="col-lg-4 col-md-12">
            <div class="card sl-section">
                <div class="sl-section__head">
                    <h6 class="sl-section__title">
                        <i class="feather icon-file-text"></i> {{ __('sales_settings.targets.general') }}
                    </h6>
                    <span class="sl-state {{ $isActive ? 'sl-state--on' : 'sl-state--off' }}">
                        {{ $isActive ? __('sales_settings.ui.active') : __('sales_settings.ui.inactive') }}
                    </span>
                </div>

                <div class="card-body">
                    <dl class="sl-dl">
                        <dt>{{ __('sales_targets.fields.target_value') }}</dt>
                        <dd class="sl-num">{{ $money($target->target_value) }}</dd>

                        {{-- مدّةُ الهدف وطريقةُ صرف عمولته — وهدفٌ بلا مدّة يُقال عنه ذلك صراحةً --}}
                        <dt>{{ __('sales_targets.duration.title') }}</dt>
                        <dd>
                            @if ($target->hasPeriod())
                                <span class="sl-num">{{ $date($target->start_date) }} ← {{ $date($target->end_date) }}</span>
                                <small class="sl-help d-block">
                                    {{ __('sales_targets.duration.modes.'.($target->settlesAtPeriodEnd() ? 'period_end' : 'cycle')) }}
                                    @if ($target->period_settled_at)
                                        · {{ __('sales_targets.duration.settled_at', ['date' => $date($target->period_settled_at)]) }}
                                    @endif
                                </small>
                            @else
                                {{ __('sales_targets.duration.none') }}
                            @endif
                        </dd>

                        <dt>{{ __('sales_targets.fields.period') }}</dt>
                        <dd>{{ __('sales_targets.fields.periods.'.($target->commission_period ?: 'monthly')) }}</dd>

                        <dt>{{ __('sales_targets.fields.auto_weekly') }}</dt>
                        <dd>
                            <span class="sl-state {{ $target->auto_calculate_weekly ? 'sl-state--on' : 'sl-state--off' }}">
                                {{ $target->auto_calculate_weekly ? __('sales_settings.ui.yes') : __('sales_settings.ui.no') }}
                            </span>
                        </dd>

                        <dt>{{ __('sales_targets.fields.created_by') }}</dt>
                        <dd>{{ $target->creator->name ?? '—' }}</dd>

                        <dt>{{ __('sales_targets.fields.created_at') }}</dt>
                        <dd>{{ $date($target->created_at) }}</dd>

                        @if ($target->description)
                            <dt>{{ __('sales_targets.fields.description') }}</dt>
                            <dd>{{ $target->description }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- ══════════ مستويات العمولة ══════════ --}}
            <div class="card sl-section">
                <div class="sl-section__head">
                    <h6 class="sl-section__title">
                        <i class="feather icon-layers"></i> {{ __('sales_targets.tiers.title') }}
                    </h6>
                    <span class="sl-count">{{ $tiers->count() }}</span>
                </div>

                <div class="card-body">
                    @forelse ($tiers as $tier)
                        <p class="sl-tier-rule">
                            <i class="feather icon-corner-down-{{ ($languageIsRtl ?? true) ? 'left' : 'right' }}"></i>
                            {{ __('sales_targets.tiers.rule', [
                                'achievement' => \App\Support\CurrencySettings::number($tier->achievement_percentage, 2),
                                'commission' => \App\Support\CurrencySettings::number($tier->commission_percentage, 2),
                            ]) }}
                        </p>
                    @empty
                        @include('sales::components.empty-state', [
                            'icon' => 'layers',
                            'title' => __('sales_targets.tiers.empty'),
                            'hint' => __('sales_targets.tiers.empty_hint'),
                        ])
                    @endforelse

                    @if ($tiers->count() > 1)
                        <small class="sl-help">{{ __('sales_targets.tiers.sorted_note') }}</small>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════ الموظفون وإنجازهم ══════════ --}}
        <div class="col-lg-8 col-md-12">
            <div class="card sl-table-card">
                <div class="sl-table-head">
                    <h6 class="sl-table-head__title">
                        <i class="feather icon-users"></i> {{ __('sales_targets.fields.employees') }}
                    </h6>
                    <span class="sl-count">{{ $target->achievements->count() }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('sales_targets.settlements.employee') }}</th>
                                <th>{{ __('sales_targets.fields.treasury') }}</th>
                                <th class="text-center">{{ __('sales_targets.stats.target_total') }}</th>
                                <th class="text-center">{{ __('sales_targets.stats.achieved_total') }}</th>
                                <th class="text-center" style="width:150px">{{ __('sales_targets.stats.percentage') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($target->achievements as $achievement)
                                @php
                                    $aTarget = (float) $achievement->target_amount;
                                    $aDone = (float) $achievement->achieved_amount;
                                    $aPct = $aTarget > 0 ? ($aDone / $aTarget) * 100 : 0;
                                @endphp

                                <tr>
                                    <td>
                                        <div class="sl-media">
                                            <span class="sl-media__thumb" aria-hidden="true"><i class="feather icon-user"></i></span>
                                            <div class="sl-media__body">
                                                <span class="sl-media__title">{{ $achievement->employee->name ?? '—' }}</span>
                                                <span class="sl-media__sub">{{ $achievement->employee->email ?? '' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        @if ($achievement->treasury)
                                            <span class="sl-media__title">{{ $achievement->treasury->name }}</span>
                                        @else
                                            <span class="sl-state sl-state--neutral">{{ __('sales_targets.fields.no_treasury') }}</span>
                                        @endif
                                    </td>

                                    <td class="text-center sl-num">{{ $money($aTarget) }}</td>
                                    <td class="text-center sl-num">{{ $money($aDone) }}</td>

                                    <td class="text-center">
                                        @include('sales::targets.partials.progress', ['percentage' => $aPct])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        @include('sales::components.empty-state', [
                                            'icon' => 'users',
                                            'title' => __('sales_targets.messages.no_employees'),
                                            'hint' => __('sales_targets.fields.employees_hint'),
                                            'action' => $canManage
                                                ? '<a href="'.route($r['edit'], $target->id).'" class="btn btn-primary">'
                                                    .'<i class="feather icon-edit-2"></i> '.e(__('sales_settings.ui.edit')).'</a>'
                                                : '',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ══════════ سجلّ التسويات ══════════ --}}
            <div class="card sl-table-card">
                <div class="sl-table-head">
                    <h6 class="sl-table-head__title">
                        <i class="feather icon-check-square"></i> {{ __('sales_targets.settlements.title') }}
                    </h6>
                    <span class="sl-count">{{ $settlements->count() }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('sales_targets.settlements.period') }}</th>
                                <th>{{ __('sales_targets.settlements.employee') }}</th>
                                <th class="text-center">{{ __('sales_targets.settlements.commission') }}</th>
                                <th>{{ __('sales_targets.settlements.settled_by') }}</th>
                                <th class="text-center">{{ __('sales_targets.settlements.settled_at') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($settlements->sortByDesc('from_date') as $settlement)
                                <tr>
                                    <td class="sl-num">
                                        {{ $date($settlement->from_date) }} — {{ $date($settlement->to_date) }}
                                    </td>
                                    {{-- موظفٌ لا مستخدم: `employees` بلا عمود `name` --}}
                                    <td>{{ $settlement->employee?->full_name ?: '—' }}</td>
                                    <td class="text-center sl-num">{{ $money($settlement->commission_amount) }}</td>
                                    <td>{{ $settlement->settler->name ?? '—' }}</td>
                                    <td class="text-center">{{ $date($settlement->settled_date ?: $settlement->created_at) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        @include('sales::components.empty-state', [
                                            'icon' => 'check-square',
                                            'title' => __('sales_targets.settlements.empty'),
                                            'hint' => __('sales_targets.settlements.empty_hint'),
                                            'action' => ($canManage || $canSettle)
                                                ? '<a href="'.route($r['report'], $target->id).'" class="btn btn-primary">'
                                                    .'<i class="feather icon-bar-chart-2"></i> '.e(__('sales_targets.actions.report')).'</a>'
                                                : '',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($settlements->count())
                    <div class="sl-table-foot">
                        <span class="sl-table-foot__count">{{ __('sales_targets.settlements.hint') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@once
    <style>
        /* قائمة تعريف: مصطلحٌ وقيمة في صفّين متقابلين، لا جدولٌ بعمودين */
        .sl-dl { margin: 0; }
        .sl-dl dt {
            font-size: .75rem;
            font-weight: 600;
            color: var(--app-muted, #6b7280);
            margin-block-start: .75rem;
        }
        .sl-dl dt:first-child { margin-block-start: 0; }
        .sl-dl dd {
            margin: .125rem 0 0;
            color: var(--app-text, #1f2937);
        }

        .sl-tier-rule {
            display: flex;
            align-items: baseline;
            gap: .375rem;
            margin: 0 0 .5rem;
            padding: .5rem .625rem;
            font-size: .8125rem;
            border-radius: var(--sl-r, 8px);
            background: var(--app-primary-soft, rgba(75, 123, 236, .07));
            color: var(--app-text, #1f2937);
        }
        .sl-tier-rule:last-of-type { margin-block-end: .75rem; }
    </style>
@endonce
