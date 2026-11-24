@extends('branches::layouts.master')

@section('title', $branch->name)

@section('content')
    @php
        use App\Constants\planFeatures\BranchesPlanFeatures;
        use App\Support\PlanLock;

        $lockCreate = PlanLock::locked(BranchesPlanFeatures::BRANCHES);
        $lockSettings = PlanLock::locked(BranchesPlanFeatures::SETTINGS);
        $lockMerge = PlanLock::locked(BranchesPlanFeatures::MERGE);

        // نوعُ العرض لا المخزَّن: منطقةٌ مخزَّنةٌ «مجموعة» تُسمّى هنا منطقة،
        // كما تُسمّى في الشجرة واللوحة — انظر `Branch::displayNodeType()`
        $nodeType = \App\Models\Branch::displayNodeType($branch, $children);
        $typeMeta = [
            'branch' => [__('branches.node_types.branch'), 'icon-home', 'primary', __('branches.show.branch_hint')],
            'region' => [__('branches.node_types.region'), 'icon-map', 'success', __('branches.show.region_hint')],
            'group'  => [__('branches.node_types.group'), 'icon-users', 'info', __('branches.show.group_hint')],
            'outlet' => [__('branches.node_types.outlet'), 'icon-shopping-bag', 'warning', __('branches.show.outlet_hint')],
        ][$nodeType] ?? [__('branches.node_types.branch_short'), 'icon-home', 'primary', ''];
        $isActive = (int) $branch->status !== 1;
    @endphp

    <x-branches::page-head
        :icon="ltrim($typeMeta[1], 'icon-')"
        :title="$branch->name"
        :subtitle="$typeMeta[3]"
        :crumbs="[__('branches.tree.breadcrumb') => route('branches.index'), $branch->name => null]">
        <x-slot:actions>
            @can('branches.edit')
                <a href="{{ route('branches.edit', $branch->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> {{ __('branches.common.edit') }}
                </a>
            @endcan
            @can('branches.create')
                <a href="{{ $lockCreate ? PlanLock::url() : route('branches.create') . '?parent_id=' . $branch->id }}"
                   class="btn btn-outline-primary {{ $lockCreate ? 'stk-plan-locked' : '' }}"
                   @if ($lockCreate) title="{{ PlanLock::reason(BranchesPlanFeatures::BRANCHES) }}" @endif>
                    <i class="feather icon-{{ $lockCreate ? 'lock' : 'plus' }}"></i> {{ __('branches.show.add_child') }}
                </a>
            @endcan
            @can('branches.view')
                <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-git-branch"></i> {{ __('branches.show.the_tree') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-branches::page-head>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══ الهوية والمسار ══ --}}
        <div class="card bx-identity">
            <div class="card-body">
                <div class="d-flex align-items-start flex-wrap" style="gap:1.15rem">
                    <div class="bx-identity-avatar {{ $typeMeta[2] }}">
                        <i class="feather {{ $typeMeta[1] }}"></i>
                    </div>

                    <div style="flex:1;min-width:240px">
                        <h3 class="bx-identity-name">{{ $branch->name }}</h3>

                        <div class="d-flex align-items-center flex-wrap mt-50" style="gap:.4rem">
                            <span class="sl-tag">{{ $typeMeta[0] }}</span>
                            @if ($branch->is_main)
                                <span class="sl-tag">{{ __('branches.show.main_branch_badge') }}</span>
                            @endif
                            <span class="sl-state {{ $isActive ? 'sl-state--on' : 'sl-state--off' }}">
                                {{ $isActive ? __('branches.common.active') : __('branches.common.inactive') }}
                            </span>
                            <span class="stk-num">{{ $branch->code ?: __('branches.show.no_code') }}</span>
                        </div>

                        {{-- المسار كفتات قابلة للنقر: الصعود إلى الأب أكثر ما
                             يُفعل في هذه الشاشة، وكان نصّاً مرصوصاً بلا تمييز. --}}
                        <nav class="bx-path mt-1">
                            @foreach ($ancestors as $a)
                                <a href="{{ route('branches.show', $a->id) }}">{{ $a->name }}</a>
                                <span class="bx-path-sep">←</span>
                            @endforeach
                            <span class="bx-path-current">{{ $branch->name }}</span>
                            @if ($ancestors->isEmpty())
                                <span class="text-muted" style="font-size:.78rem">{{ __('branches.show.root_note') }}</span>
                            @endif
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ الأرقام ══ --}}
        @php
            /* الرقمُ الكبير هو التابع لهذا العنصر مباشرةً، والملاحظةُ تقول كم
               يقع تحته. فمنطقةٌ كلُّ عملائها على منافذها كانت تظهر بصفر بلا
               هذا، ويظنّ المستخدمُ أنها فارغة فيحذفها. */
            $clientsBelow = max(0, ($stats['clients_subtree'] ?? $stats['clients']) - $stats['clients']);
            $hoodsBelow = max(0, ($stats['neighborhoods_subtree'] ?? $stats['neighborhoods']) - $stats['neighborhoods']);
        @endphp
        <div class="sl-stats">
            <x-branches::stat :label="__('branches.show.stat_clients')" :value="$stats['clients']" icon="users" tone="primary"
                              :title="__('branches.show.stat_clients_title')"
                              :note="$clientsBelow
                                  ? __('branches.show.stat_clients_note_below', ['count' => number_format($clientsBelow)])
                                  : __('branches.show.stat_clients_note')" note-icon="file-text" />

            <x-branches::stat :label="__('branches.show.stat_employees')" :value="$stats['employees']" icon="user-check" tone="success"
                              :title="__('branches.show.stat_employees_title')"
                              :note="__('branches.show.stat_employees_note')" note-icon="briefcase" />

            <x-branches::stat :label="__('branches.show.stat_neighborhoods')" :value="$stats['neighborhoods']" icon="map-pin" tone="info"
                              :title="__('branches.show.stat_neighborhoods_title')"
                              :note="$hoodsBelow
                                  ? __('branches.show.stat_neighborhoods_note_below', ['count' => number_format($hoodsBelow)])
                                  : __('branches.show.stat_neighborhoods_note')" note-icon="map" />

            <x-branches::stat :label="__('branches.show.stat_descendants')" :value="$descendantCount" icon="git-branch" tone="warning"
                              :title="__('branches.show.stat_descendants_title')"
                              :note="$descendantCount ? __('branches.show.stat_descendants_note') : __('branches.show.stat_leaf_note')"
                              note-icon="corner-left-down" />
        </div>

        {{--
            تنبيهُ الارتباطات ليس إرشاداً ثابتاً: لا يظهر إلا حين تتعلّق
            بالعقدة سجلّاتٌ فعلية، ويقول ما يقع إن حُذفت. ورابطُ النقل
            يتبع صلاحيةَ الدمج وقفلَ الباقة — فلا يُعرض بابٌ يُردّ عنده.
        --}}
        @if ($stats['clients'] || $clientsBelow || $stats['employees'] || $stats['neighborhoods'] || $hoodsBelow || $descendantCount)
            <x-branches::explain tone="warning" :title="__('branches.show.linked_title')">
                @php
                    $moveLabel = e(__('branches.tree.move_clients'));
                    $moveHref = hasPermission('branches.groups.merge')
                        ? ($lockMerge ? PlanLock::url() : route('groups.merge_select_page'))
                        : null;
                @endphp
                {!! __('branches.show.linked_text', [
                    'link' => $moveHref
                        ? '<a href="' . e($moveHref) . '">' . $moveLabel . '</a>'
                        : $moveLabel,
                ]) !!}
            </x-branches::explain>
        @endif

        <div class="row">
            <div class="col-lg-7 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-file-text"></i> {{ __('branches.show.basic_data') }}</h4>
                    </div>
                    <div class="card-body">
                        {{-- قائمة بيانات (dl) لا جدول: هذه أزواج «تسمية/قيمة»
                             لا صفوفٌ متجانسة، والجدول كان يفرض عليها ترويسةً
                             وحدوداً لا معنى لها هنا. و`sl-def` هي نفسها
                             المستعملة في شاشة عرض سند الصرف. --}}
                        <dl class="sl-def mb-0">
                                <div class="sl-def__row">
                                    <dt>{{ __('branches.show.parent_branch') }}</dt>
                                    <dd>
                                        @if ($branch->parentBranch)
                                            <a href="{{ route('branches.show', $branch->parentBranch->id) }}">{{ $branch->parentBranch->name }}</a>
                                        @else
                                            <span class="text-muted">{{ __('branches.show.root_of_tree') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="sl-def__row"><dt>{{ __('branches.form.phone') }}</dt><dd>{{ $branch->phone ?: '—' }}</dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.form.mobile') }}</dt><dd>{{ $branch->mobile ?: '—' }}</dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.show.country_city') }}</dt><dd>{{ $branch->country ?: '—' }} / {{ $branch->city ?: '—' }}</dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.form.region') }}</dt><dd>{{ $branch->region ?: '—' }}</dd></div>
                                <div class="sl-def__row">
                                    <dt>{{ __('branches.show.address') }}</dt>
                                    <dd>{{ $branch->address1 ?: '—' }}{{ $branch->address2 ? ' — ' . $branch->address2 : '' }}</dd>
                                </div>
                                <div class="sl-def__row"><dt>{{ __('branches.form.credit_limit') }}</dt><dd>{{ number_format((float) $branch->credit_limit, 2) }}</dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.form.work_hours') }}</dt><dd>{{ $branch->work_hours ?: '—' }}</dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.form.description') }}</dt><dd>{{ $branch->description ?: '—' }}</dd></div>
                                <div class="sl-def__row">
                                    <dt>{{ __('branches.show.geo') }}</dt>
                                    <dd>
                                        @if ($branch->geoLocation)
                                            <a href="https://maps.google.com/?q={{ $branch->geoLocation->latitude }},{{ $branch->geoLocation->longitude }}"
                                               target="_blank" rel="noopener noreferrer">
                                                <i class="feather icon-external-link"></i>
                                                <span class="stk-num">{{ $branch->geoLocation->latitude }}, {{ $branch->geoLocation->longitude }}</span>
                                            </a>
                                        @else
                                            <span class="text-muted">{{ __('branches.common.not_set') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="sl-def__row"><dt>{{ __('branches.show.created') }}</dt><dd>{{ optional($branch->created_at)->format('Y-m-d H:i') ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-git-branch"></i> {{ __('branches.show.children_title') }}
                            <span class="sl-count">{{ $children->count() }}</span>
                        </h4>
                    </div>
                    <div class="card-body bx-scroll-sm">
                        <dl class="sl-def mb-0">
                            @forelse ($children as $child)
                                @can('branches.show')
                                    <a href="{{ route('branches.show', $child->id) }}" class="sl-def__row bx-def-link">
                                        <dt>{{ $child->name }}</dt>
                                        <dd class="stk-num">{{ $child->code ?: '—' }}</dd>
                                    </a>
                                @else
                                    <div class="sl-def__row">
                                        <dt>{{ $child->name }}</dt>
                                        <dd class="stk-num">{{ $child->code ?: '—' }}</dd>
                                    </div>
                                @endcan
                            @empty
                                <x-branches::empty icon="git-branch" :title="__('branches.show.children_empty')"
                                                   :text="__('branches.show.children_empty_hint')">
                                    @can('branches.create')
                                        <a href="{{ $lockCreate ? PlanLock::url() : route('branches.create') . '?parent_id=' . $branch->id }}"
                                           class="btn btn-sm btn-outline-primary {{ $lockCreate ? 'stk-plan-locked' : '' }}"
                                           @if ($lockCreate) title="{{ PlanLock::reason(BranchesPlanFeatures::BRANCHES) }}" @endif>
                                            <i class="feather icon-{{ $lockCreate ? 'lock' : 'plus' }}"></i> {{ __('branches.tree.add_child_here') }}
                                        </a>
                                    @endcan
                                </x-branches::empty>
                            @endforelse
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-sliders"></i> {{ __('branches.show.settings_title') }}</h4>
                    </div>
                    <div class="card-body">
                        <p class="sl-help mb-1">{{ __('branches.show.settings_intro') }}</p>

                        <dl class="sl-def">
                            @forelse ($settings as $setting)
                                <div class="sl-def__row">
                                    <dt>
                                        {{ $setting['name'] }}
                                        @if ($setting['source'] === 'inherited')
                                            <small class="sl-help d-block">
                                                {{ __('branches.show.settings_inherited_from', ['name' => $setting['source_branch']]) }}
                                            </small>
                                        @endif
                                    </dt>
                                    <dd>
                                        <span class="sl-tag">{{ $setting['display'] }}</span>
                                    </dd>
                                </div>
                            @empty
                                <x-branches::empty icon="sliders" :title="__('branches.show.settings_empty')"
                                                   :text="__('branches.show.settings_empty_hint')" />
                            @endforelse
                        </dl>

                        @can('branches.settings.view')
                            <a href="{{ $lockSettings ? PlanLock::url() : route('branches.settings', ['branch_id' => $branch->id]) }}"
                               class="btn btn-sm btn-outline-primary {{ $lockSettings ? 'stk-plan-locked' : '' }}"
                               @if ($lockSettings) title="{{ PlanLock::reason(BranchesPlanFeatures::SETTINGS) }}" @endif>
                                <i class="feather icon-{{ $lockSettings ? 'lock' : 'sliders' }}"></i> {{ __('branches.show.manage_settings') }}
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
