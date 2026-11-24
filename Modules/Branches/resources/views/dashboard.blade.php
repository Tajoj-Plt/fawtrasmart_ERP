{{--
    لوحة الهيكل التنظيمي — بنمط تصميم لوحة المبيعات
    (`Modules/Sales/resources/views/dashboard/index.blade.php`) بطاقةً ببطاقة:
    لوحةُ إرشادٍ مطويّة، فشريطُ فلترةٍ واحد يحمل عنوانَ الشاشة، فصفّا
    مؤشّرات، فبطاقاتٌ برأسٍ فيه أيقونةٌ وزرُّ «من أين تأتي هذه البيانات؟».
    وكلُّ لونٍ من متغيّرات `--app-*`.

    الأرقامُ نفسُها والمنطقُ نفسُه — ما تغيّر هو الهيئة وحدَها: المتحكّمُ
    لم يُمسّ، وكلُّ معرّفٍ يقرؤه السكربت (`bxd-*`) باقٍ في موضعه.
--}}
@extends('branches::layouts.master')

@section('title', __('branches.dashboard.title'))

{{-- بالمكدّس لا بالقسم: قسمُ الأنماط يطبعه رأسُ النظام ثمّ قالبُ الوحدة،
     فتخرج الورقةُ مرّتين. والمكدّسُ يُطبع مرّةً واحدة. --}}
@push('styles')
    @include('branches::layouts.dashboard-styles')
@endpush

@section('content')
    @php
        use App\Constants\planFeatures\BranchesPlanFeatures;
        use App\Support\PlanLock;

        // القفلُ يُحسب مرّةً في رأس القالب لا عند كل زرّ: `locked()` يقرأ لقطةَ
        // الاشتراك، وتكرارُ النداء داخل حلقةٍ يعني قراءةً لكل صفّ.
        $lockCreate = PlanLock::locked(BranchesPlanFeatures::BRANCHES);
        $lockExport = PlanLock::locked(BranchesPlanFeatures::EXPORT);

        /*
         * فحصُ الصلاحية مرّةً واحدةً لا مرّةً لكل صفّ: فحصٌ داخل حلقةٍ يُصدر
         * استعلاماً لكل صفّ. والخريطةُ كذلك: بناؤها في كل دورةٍ يعني استدعاءَ
         * أربعِ ترجماتٍ لكل صفّ.
         */
        $canShowNode = hasPermission('branches.show');
        $canViewTree = hasPermission('branches.view');

        /*
         * رابطُ «عملاء هذه الجهة» يقود إلى شاشة العملاء بنفس معرّف العقدة
         * (`region`) و«شامل التوابع» — وهي تفلتر بنفس تعريف التبعيّة الذي
         * تُحسب به أرقامُ هذه اللوحة، فالعددُ الذي يراه هنا هو العددُ الذي
         * يجده هناك.
         *
         * ووجودُ المسار يُفحص لأن وحدةَ العملاء قد تكون مغلقةً من شاشة
         * التطبيقات، فيختفي المسارُ ويرمي `route()` استثناءً.
         */
        $canListClients = Route::has('clients.index') && hasPermission([
            'clients_view_all_clients',
            'clients_view_own_clients',
            'clients_view_by_employee_groups',
            'clients_view_by_daily_itinerary',
        ]);

        /*
         * كلُّ عنصرٍ يُسمّى **بنوعه هو**: منطقةٌ تُقال منطقة، ومجموعةٌ تُقال
         * مجموعة، ومنفذٌ يُقال منفذ. ولا تُستعمل كلمةُ «فرع» اسماً عامّاً لكل
         * ما في الهيكل — فالفرعُ نوعٌ من أربعة، لا مرادفٌ للعنصر.
         */
        $nodeTypeMeta = [
            'region' => [__('branches.node_types.region'), 'icon-map', 'success'],
            'branch' => [__('branches.node_types.branch_short'), 'icon-home', 'primary'],
            'group' => [__('branches.node_types.group_short'), 'icon-users', 'info'],
            'outlet' => [__('branches.node_types.outlet_short'), 'icon-shopping-bag', 'warning'],
        ];

        // مقامُ نسب الدائرة: عناصرُ المستوى المعروض — وهي مجموعُ الأنواع نفسِها
        $typeTotal = max(1, $stats['branches'] + $stats['regions'] + $stats['groups'] + $stats['outlets']);

        // النِّسبُ تُحسب هنا مرّةً، ولا قسمةَ على صفر
        $clientsScope = max(0, (int) $stats['clients']);
        $pct = fn (int $part) => $clientsScope > 0 ? (int) round($part / $clientsScope * 100) : 0;

        $infoLabel = __('branches.dashboard.info_toggle');

        // رابطُ عملاء النطاق المعروض — يفتحه مؤشّرُ «العملاء» وزرُّ الجهة المختارة
        $scopeClientsUrl = null;
        if ($canListClients) {
            $scopeClientsUrl = $selectedCard
                ? route('clients.index', ['region' => $selectedCard['id'], 'include_descendants' => $selectedCard['exact'] ? 0 : 1])
                : route('clients.index');
        }

        $stoppedLeads = $stats['clients_stopped'] > $stats['clients_active'];

        /*
         * المؤشّرات: صفٌّ لأرقام العملاء وصفٌّ لأرقام الهيكل — كما تفصل لوحةُ
         * المبيعات المبالغَ عن الأعداد. وكلُّ رقمٍ معه ما يُفهم به: نسبتُه في
         * الشارة، أو تفصيلُه في الحاشية. الرقمُ وحدَه لا يقول إن كان كبيراً
         * أم صغيراً.
         */
        $kpiClients = [
            [
                'label' => __('branches.dashboard.stat_clients'),
                'value' => number_format($stats['clients']),
                'icon' => 'users',
                'color' => 'var(--sd-primary)',
                'url' => $scopeClientsUrl,
                'foot' => $selectedCard
                    ? trans_choice('branches.dashboard.stat_clients_direct_note', $selectedCard['clients_direct'], ['count' => number_format($selectedCard['clients_direct'])])
                    : trans_choice('branches.dashboard.stat_clients_spread', $stats['total'], ['count' => number_format($stats['total'])]),
            ],
            [
                'label' => __('branches.dashboard.stat_active_clients'),
                'value' => number_format($stats['clients_active']),
                'icon' => 'user-check',
                'color' => 'var(--sd-success)',
                'delta' => ['class' => 'sd-delta-up', 'text' => $pct($stats['clients_active']).'%'],
                'foot' => __('branches.dashboard.of_scope_clients', ['percent' => $pct($stats['clients_active'])]),
            ],
            [
                'label' => __('branches.dashboard.fact_stopped'),
                'value' => number_format($stats['clients_stopped']),
                'icon' => 'user-x',
                'color' => $stoppedLeads ? 'var(--sd-danger)' : 'var(--sd-warning)',
                'delta' => ['class' => $stoppedLeads ? 'sd-delta-down' : 'sd-delta-flat', 'text' => $pct($stats['clients_stopped']).'%'],
                'foot' => __('branches.dashboard.of_scope_clients', ['percent' => $pct($stats['clients_stopped'])]),
            ],
            [
                'label' => __('branches.dashboard.col_new'),
                'value' => number_format($stats['clients_new']),
                'icon' => 'trending-up',
                'color' => 'var(--sd-info)',
                'delta' => ['class' => 'sd-delta-up', 'text' => $pct($stats['clients_new']).'%'],
                'foot' => __('branches.dashboard.of_scope_clients', ['percent' => $pct($stats['clients_new'])]),
            ],
        ];

        $kpiStructure = [
            [
                // العددان معاً: الشجرةُ تحت الجهة كلُّها، وما تحتها مباشرةً.
                // الرقمُ الأوّل وحدَه كان يخالف الجدولَ والدائرةَ فيبدو خطأً.
                'label' => __('branches.dashboard.stat_nodes_scope'),
                'value' => number_format($stats['total']),
                'icon' => 'git-branch',
                'color' => 'var(--sd-primary)',
                'url' => $canViewTree ? route('branches.index') : null,
                'foot' => trans_choice('branches.dashboard.stat_level_note', $stats['level'], ['count' => number_format($stats['level'])]),
            ],
            [
                'label' => __('branches.dashboard.stat_neighborhoods'),
                'value' => number_format($stats['neighborhoods']),
                'icon' => 'map-pin',
                'color' => 'var(--sd-warning)',
                'foot' => __('branches.dashboard.stat_hoods_note'),
            ],
            [
                'label' => __('branches.dashboard.col_employees'),
                'value' => number_format($stats['employees']),
                'icon' => 'briefcase',
                'color' => 'var(--sd-info)',
                'foot' => __('branches.dashboard.stat_employees_note'),
            ],
        ];

        // لا تُعرض بطاقةُ التنبيه إن لم يكن فيها ما يُقال: تنبيهٌ دائمُ الظهور
        // يُقرأ كزينةٍ فيُتجاهَل حين يصير حقيقياً.
        $hasHealth = $orphans->isNotEmpty() || $noCode->isNotEmpty()
            || $stats['unplaced_clients'] > 0 || $stats['inactive'] > 0;
    @endphp

    <div class="content-body">
        <section id="branches-dashboard">

            {{-- ══════════ إرشادُ اللوحة ══════════
                 سؤالان يُسألان عن كل لوحةٍ في النظام: «من أين جاء الرقم؟» و«لماذا
                 يخالف الشاشةَ الأخرى؟». الجوابُ هنا، لا في ذاكرة من بناها.
                 ومطويّةٌ افتراضياً: من يفتح اللوحة جاء لأرقامها. --}}
            <x-branches::explain tone="primary" :title="__('branches.dashboard.how_title')">
                <ul>
                    <li>{{ __('branches.dashboard.how_affiliation') }}</li>
                    <li>{{ __('branches.dashboard.how_subtree') }}</li>
                    <li>{{ __('branches.dashboard.how_status') }}</li>
                    <li>{{ __('branches.dashboard.how_neighborhoods') }}</li>
                </ul>
            </x-branches::explain>

            {{-- ══════════ ١) الفلترة: عنوانُ الشاشة واختيارُ الجهة ══════════

                 نفسُ مُنتقي تقارير العملاء — القالبُ نفسُه لا نسخةٌ منه، فالقيمةُ
                 `tree_node` تنتقل بين الشاشتين برابطٍ واحد، والعددُ الذي يُقرأ
                 هنا هو الذي يُقرأ هناك.

                 وكلُّ حقلٍ فيه يحمل اسمَ **نوع ما يعرضه**: حقلٌ خياراتُه مناطقُ
                 اسمُه «منطقة»، وحقلٌ خياراتُه مجموعاتٌ اسمُه «مجموعة». --}}
            <div class="sd-toolbar no-print">
                <div class="sd-toolbar-head">
                    <div>
                        <h4 class="sd-title"><i class="feather icon-grid"></i> {{ __('branches.dashboard.title') }}</h4>
                        <p class="sd-subtitle">{{ __('branches.dashboard.subtitle') }}</p>
                    </div>

                    <div class="sd-toolbar-actions">
                        @can('branches.view')
                            <a href="{{ route('branches.index') }}" class="btn btn-outline-primary btn-sm">
                                <i class="feather icon-git-branch"></i> {{ __('branches.dashboard.open_tree') }}
                            </a>
                        @endcan
                        @can('branches.export')
                            <a href="{{ $lockExport ? PlanLock::url() : route('branches.export') }}"
                               class="btn btn-outline-secondary btn-sm {{ $lockExport ? 'stk-plan-locked' : '' }}"
                               title="{{ $lockExport ? PlanLock::reason(BranchesPlanFeatures::EXPORT) : __('branches.tree.export_hint') }}">
                                <i class="feather icon-{{ $lockExport ? 'lock' : 'download' }}"></i> {{ __('branches.common.export') }}
                            </a>
                        @endcan
                        @can('branches.create')
                            <a href="{{ $lockCreate ? PlanLock::url() : route('branches.create') }}"
                               class="btn btn-primary btn-sm {{ $lockCreate ? 'stk-plan-locked' : '' }}"
                               @if ($lockCreate) title="{{ PlanLock::reason(BranchesPlanFeatures::BRANCHES) }}" @endif>
                                <i class="feather icon-{{ $lockCreate ? 'lock' : 'plus' }}"></i> {{ __('branches.dashboard.new_node') }}
                            </a>
                        @endcan
                    </div>
                </div>

                @if ($selectedCard)
                    {{-- المسارُ ليس زينةً: «حائل» وحدَها لا تقول تحت أيّ منطقةٍ هي.
                         وكلُّ جزءٍ منه رابطٌ يصعد إليه، فالخروجُ من العمق بضغطةٍ
                         لا بالرجوع خطوةً خطوة. --}}
                    <nav class="sd-crumbs" aria-label="{{ __('branches.dashboard.path_label') }}">
                        {{-- «كامل الهيكل» لمن لم يحصر الشريطَ على فرع: المحصورُ
                             لا يخرج من شجرة فرعه، فمسارُه يبدأ منها --}}
                        @if (! $headerScope)
                            <a href="{{ route('branches.dashboard') }}">— {{ __('branches.dashboard.path_root') }} —</a>
                        @endif
                        @foreach ($selectedCard['path'] as $step)
                            @if (! $loop->first || ! $headerScope)
                                <span class="sd-crumb-sep">/</span>
                            @endif
                            @if ($loop->last)
                                <strong>{{ $step['name'] }}</strong>
                            @else
                                <a href="{{ route('branches.dashboard', ['tree_node' => $step['id']]) }}">{{ $step['name'] }}</a>
                            @endif
                        @endforeach
                    </nav>
                @endif

                {{-- إعادةُ تحميلٍ بـGET لا نداءُ AJAX: العنوانُ يحمل ما يُعرض،
                     فالرجوعُ والتقدّمُ والحفظُ في المفضّلة تعمل كلُّها — وهي
                     نفسُ القاعدة التي انتقلت إليها تقاريرُ العملاء. --}}
                <form method="GET" action="{{ route('branches.dashboard') }}" id="bxd-scope-form">
                    <div class="row">
                        @include('reports.partials.affiliation-tree', [
                            'treeNodes' => $treeNodes,
                            'currentNode' => $selectedId,
                            'label' => __('branches.dashboard.scope_label'),
                            'typeOnlyLabels' => true,
                        ])
                    </div>

                    <div class="sd-filters">
                        <button type="submit" class="sd-apply">
                            <i class="feather icon-filter"></i> {{ __('branches.dashboard.scope_apply') }}
                        </button>
                        {{-- بلا حصر: رجوعٌ إلى الهيكل كاملاً. ومع حصر الشريط: رجوعٌ
                             إلى فرعه — ولا يُعرض وهو المعروضُ أصلاً --}}
                        @if ($headerScope)
                            @if (! $headerScope['is_current'])
                                <a href="{{ route('branches.dashboard') }}" class="sd-reset">
                                    <i class="feather icon-refresh-cw"></i> {{ __('branches.common.header_scope_back', ['name' => $headerScope['name']]) }}
                                </a>
                            @endif
                        @elseif ($selectedId)
                            <a href="{{ route('branches.dashboard') }}" class="sd-reset">
                                <i class="feather icon-refresh-cw"></i> {{ __('branches.dashboard.scope_clear') }}
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            @include('layouts.alerts.success')
            @include('layouts.alerts.error')

            {{-- الشاشةُ محصورةٌ على فرع الشريط العلويّ — يُقال ذلك صراحةً، وإلّا
                 ظنّ المستخدمُ أن بقيّةَ الهيكل اختفى --}}
            @if ($headerScope)
                <div class="sd-notice bxd-header-scope">
                    <strong><i class="feather icon-map-pin"></i> {{ __('branches.common.header_scope_title', ['name' => $headerScope['name']]) }}</strong>
                    {{ __('branches.common.header_scope_text', ['name' => $headerScope['name']]) }}
                </div>
            @endif

            {{-- ══════════ ٢) الجهة المختارة ══════════
                 نوعُها، وموقعُها في الهيكل، وما تحتها مباشرةً. --}}
            @if ($selectedCard)
                @php
                    $scopeMeta = $nodeTypeMeta[$selectedCard['type']] ?? $nodeTypeMeta['branch'];
                @endphp

                <div class="sd-card sd-card--flat bxd-focus bxd-focus--{{ $scopeMeta[2] }}">
                    <div class="sd-card-body">
                        <div class="bxd-focus__head">
                            <span class="bxd-focus__icon"><i class="feather {{ $scopeMeta[1] }}"></i></span>
                            <span class="bxd-focus__titles">
                                <span class="bxd-focus__name">{{ $selectedCard['name'] }}</span>
                                <span class="bxd-focus__sub">
                                    <span class="bxd-type bxd-type--{{ $scopeMeta[2] }}">{{ $scopeMeta[0] }}</span>
                                    @if ($selectedCard['is_main'])
                                        <span class="bxd-type bxd-type--main">{{ __('branches.common.main') }}</span>
                                    @endif
                                    @if (! $selectedCard['active'])
                                        <span class="bxd-type bxd-type--off">{{ __('branches.common.inactive') }}</span>
                                    @endif
                                    @if ($selectedCard['code'] !== '')
                                        <span class="sd-num">{{ $selectedCard['code'] }}</span>
                                    @endif
                                    <span class="bxd-none">
                                        {{ __('branches.dashboard.level_n', ['level' => $selectedCard['depth'] + 1]) }}
                                    </span>
                                    @if ($selectedCard['exact'])
                                        <span class="bxd-type bxd-type--warning">{{ __('branches.dashboard.exact_badge') }}</span>
                                    @endif
                                </span>
                            </span>

                            <span class="bxd-focus__actions">
                                @can('branches.show')
                                    <a href="{{ route('branches.show', $selectedCard['id']) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="feather icon-eye"></i> {{ __('branches.common.view_details') }}
                                    </a>
                                @endcan
                                @if ($scopeClientsUrl)
                                    <a href="{{ $scopeClientsUrl }}" class="btn btn-sm btn-primary"
                                       title="{{ __('branches.dashboard.open_clients_hint') }}">
                                        <i class="feather icon-users"></i> {{ __('branches.dashboard.open_clients') }}
                                    </a>
                                @endif
                            </span>
                        </div>

                        @if ($selectedCard['children_by_type'])
                            <div class="bxd-focus__kids">
                                <span class="bxd-none">{{ __('branches.dashboard.children_breakdown') }}</span>
                                @foreach ($selectedCard['children_by_type'] as $kidType => $kidCount)
                                    @php
                                        $kidKey = isset($nodeTypeMeta[$kidType]) ? $kidType : 'branch';
                                        $kidMeta = $nodeTypeMeta[$kidKey];
                                    @endphp
                                    <span class="bxd-type bxd-type--{{ $kidMeta[2] }}">
                                        <i class="feather {{ $kidMeta[1] }}"></i>
                                        {{-- الجمعُ يتبع العدد: «مجموعةٌ» و«مجموعتان» و«٥ مجموعات»
                                             و«١١ مجموعةً» أربعُ صيغٍ في العربية. --}}
                                        {{ trans_choice('branches.node_types.'.$kidKey.'_count', $kidCount, ['count' => number_format($kidCount)]) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ══════════ ٣) ما يحتاج انتباهاً ══════════ --}}
            @if ($hasHealth)
                <div class="sd-notice warning">
                    <strong><i class="feather icon-alert-triangle"></i> {{ __('branches.dashboard.health_title') }}</strong>
                    <ul class="bxd-health">
                        @if ($stats['unplaced_clients'] > 0)
                            <li>
                                <strong>{{ __('branches.dashboard.unplaced', ['count' => number_format($stats['unplaced_clients'])]) }}</strong>
                                — {{ __('branches.dashboard.unplaced_hint') }}
                            </li>
                        @endif

                        @if ($orphans->isNotEmpty())
                            <li>
                                <strong>{{ __('branches.dashboard.orphans', ['count' => $orphans->count()]) }}</strong>
                                — {{ __('branches.dashboard.orphans_hint') }}
                                @foreach ($orphans->take(6) as $orphan)
                                    @can('branches.edit')
                                        <a href="{{ route('branches.edit', $orphan->id) }}" class="sl-tag">{{ $orphan->name }}</a>
                                    @else
                                        <span class="sl-tag">{{ $orphan->name }}</span>
                                    @endcan
                                @endforeach
                                @if ($orphans->count() > 6)
                                    <span>{{ __('branches.dashboard.and_more', ['count' => $orphans->count() - 6]) }}</span>
                                @endif
                            </li>
                        @endif

                        @if ($noCode->isNotEmpty())
                            <li>
                                <strong>{{ __('branches.dashboard.no_code', ['count' => $noCode->count()]) }}</strong>
                                — {{ __('branches.dashboard.no_code_hint') }}
                            </li>
                        @endif

                        @if ($stats['inactive'] > 0)
                            <li>
                                <strong>{{ __('branches.dashboard.inactive_nodes') }}: {{ number_format($stats['inactive']) }}</strong>
                                — {{ __('branches.dashboard.inactive_note') }}
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            {{-- ══════════ ٤) المؤشّرات: صفّان ══════════ --}}
            @foreach ([$kpiClients, $kpiStructure] as $kpiRow)
                <div class="row">
                    @foreach ($kpiRow as $kpi)
                        @php
                            $kpiUrl = $kpi['url'] ?? null;
                            $kpiTag = $kpiUrl ? 'a' : 'div';
                        @endphp
                        <div class="col-xl col-lg-4 col-md-6 col-12 mb-1">
                            {{-- المؤشّرُ الذي وراءه قائمةٌ رابطٌ يفتحها؛ وما ليس وراءه شيءٌ يبقى بطاقةً --}}
                            <{{ $kpiTag }} class="sd-kpi" @if ($kpiUrl) href="{{ $kpiUrl }}" data-segment @endif>
                                <div class="sd-kpi-top">
                                    <div class="sd-kpi-icon" style="background: color-mix(in srgb, {{ $kpi['color'] }} 14%, transparent);">
                                        <i class="feather icon-{{ $kpi['icon'] }}" style="color: {{ $kpi['color'] }};"></i>
                                    </div>
                                    @if (! empty($kpi['delta']))
                                        <span class="sd-delta {{ $kpi['delta']['class'] }}">{{ $kpi['delta']['text'] }}</span>
                                    @endif
                                </div>
                                <p class="sd-kpi-label">{{ $kpi['label'] }} @if ($kpiUrl)<i class="feather icon-list sd-kpi-open"></i>@endif</p>
                                <p class="sd-kpi-value">{{ $kpi['value'] }}</p>
                                <div class="sd-kpi-foot">{{ $kpi['foot'] }}</div>
                            </{{ $kpiTag }}>
                        </div>
                    @endforeach
                </div>
            @endforeach

            {{-- ══════════ ٥) توزيعان ══════════ --}}
            <div class="row">
                <div class="col-lg-5 col-12">
                    <div class="sd-card">
                        <div class="sd-card-head">
                            <div>
                                <h4><i class="feather icon-layers"></i> {{ __('branches.dashboard.types_title_level') }}</h4>
                            </div>
                            <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="sd-card-body">
                            <div class="sd-info-panel" hidden>{{ __('branches.dashboard.types_intro_level') }}</div>

                            <div id="bxdTypesChart"></div>

                            <div class="bxd-legend">
                                @foreach ([
                                    ['region', $stats['regions']],
                                    ['branch', $stats['branches']],
                                    ['group', $stats['groups']],
                                    ['outlet', $stats['outlets']],
                                ] as [$typeKey, $typeCount])
                                    @php
                                        $legendMeta = $nodeTypeMeta[$typeKey];
                                        $percent = (int) round($typeCount / $typeTotal * 100);
                                    @endphp
                                    <span class="bxd-legend__row" title="{{ __('branches.node_types.'.$typeKey.'_hint') }}">
                                        <i class="feather {{ $legendMeta[1] }} bxd-tone--{{ $legendMeta[2] }}"></i>
                                        <span class="bxd-legend__label">{{ $legendMeta[0] }}</span>
                                        <span class="sd-num">{{ number_format($typeCount) }}</span>
                                        <span class="bx-bar">
                                            <span class="bx-bar__fill bx-bar__fill--{{ $legendMeta[2] }}" style="width:{{ $percent }}%"></span>
                                        </span>
                                        <span class="bxd-legend__pct">{{ $percent }}%</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 col-12">
                    <div class="sd-card">
                        <div class="sd-card-head">
                            <div>
                                <h4><i class="feather icon-bar-chart-2"></i> {{ __('branches.dashboard.top_title_level') }}</h4>
                            </div>
                            <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="sd-card-body">
                            <div class="sd-info-panel" hidden>{{ __('branches.dashboard.top_intro_level') }}</div>

                            @if ($topNodes->isEmpty())
                                <div class="sd-empty">
                                    <i class="feather icon-users"></i>
                                    {{ __('branches.dashboard.top_empty') }}
                                    <small class="d-block">{{ __('branches.dashboard.top_empty_hint') }}</small>
                                </div>
                            @else
                                <div id="bxdTopChart"></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ ٦) الشجرة ══════════

                 الهيكلُ تحت الجهة **بأعماقه كلِّها** لا مستواه الأوّل وحدَه: كلُّ
                 صفٍّ عنصرٌ بأرقامه، وسهمُه يفتح ما تحته — كشجرة لوحة المبيعات.
                 مطويّةٌ عند الفتح فيظهر أعلى مستوى، والنزولُ بالسهم داخل
                 الصفحة أو بضغطة الاسم فيصير العنصرُ هو الجهة.

                 كانت البطاقةُ تعرض مستوىً واحداً، فمن أراد ما تحت «منطقة
                 الرياض» غادر الصفحة إليها ثمّ عاد. --}}
            <div class="row">
                <div class="col-12">
                    <div class="sd-card" id="bxd-level-card">
                        <div class="sd-card-head">
                            <div>
                                <h4>
                                    <i class="feather icon-git-branch"></i>
                                    {{ $selectedCard
                                        ? __('branches.dashboard.tree_title_under', ['name' => $selectedCard['name']])
                                        : __('branches.dashboard.tree_title') }}
                                    @if ($treeRows->isNotEmpty())
                                        <span class="sd-node-type">{{ trans_choice('branches.dashboard.node_count', $treeRows->count(), ['count' => number_format($treeRows->count())]) }}</span>
                                    @endif
                                </h4>
                                @if ($treeRows->isNotEmpty())
                                    <small>{{ __('branches.dashboard.tree_hint') }}</small>
                                @endif
                            </div>
                            <div class="sd-tree-actions">
                                @if ($treeRows->contains('has_children', true))
                                    <button type="button" class="sd-tree-all" data-bxd-tree-all="1">{{ __('branches.tree.expand_short') }}</button>
                                    <button type="button" class="sd-tree-all" data-bxd-tree-all="0">{{ __('branches.tree.collapse_short') }}</button>
                                @endif
                                <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                            </div>
                        </div>

                        <div class="sd-card-body">
                            <div class="sd-info-panel" hidden>{{ __('branches.dashboard.tree_info') }}</div>

                            @if ($treeRows->isEmpty())
                                <div class="sd-empty">
                                    <i class="feather icon-corner-left-down"></i>
                                    {{ $exactNode ? __('branches.dashboard.level_exact_title') : __('branches.dashboard.level_empty_title') }}
                                    <small class="d-block">{{ $exactNode ? __('branches.dashboard.level_exact_text') : __('branches.dashboard.level_empty_text') }}</small>
                                    @if ($selectedCard && $scopeClientsUrl)
                                        <a href="{{ $scopeClientsUrl }}" class="btn btn-primary btn-sm mt-1">
                                            <i class="feather icon-users"></i> {{ __('branches.dashboard.open_clients') }}
                                        </a>
                                    @endif
                                </div>
                            @else
                                <div class="sd-filters bxd-level-filters no-print">
                                    <div class="sd-field bxd-level-filters__search">
                                        <label for="bxd-search">{{ __('branches.common.search') }}</label>
                                        <input type="text" id="bxd-search" class="form-control"
                                               placeholder="{{ __('branches.tree.filter_search_placeholder') }}" autocomplete="off">
                                    </div>

                                    <div class="sd-field">
                                        <label for="bxd-type">{{ __('branches.dashboard.col_type') }}</label>
                                        <select id="bxd-type" class="custom-select">
                                            <option value="">{{ __('branches.tree.filter_all_types') }}</option>
                                            @foreach ($nodeTypeMeta as $typeKey => $meta)
                                                <option value="{{ $typeKey }}">{{ $meta[0] }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="sd-field">
                                        <label for="bxd-clients">{{ __('branches.dashboard.filter_clients') }}</label>
                                        <select id="bxd-clients" class="custom-select">
                                            <option value="">{{ __('branches.dashboard.filter_clients_all') }}</option>
                                            <option value="has">{{ __('branches.dashboard.filter_clients_has') }}</option>
                                            <option value="none">{{ __('branches.dashboard.filter_clients_only_empty') }}</option>
                                        </select>
                                    </div>

                                    <span class="sd-drawer-count" id="bxd-result"></span>
                                </div>

                                <div class="table-responsive">
                                    <table class="sd-table sd-tree bxd-table" id="bxd-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('branches.dashboard.col_node') }}</th>
                                                <th>{{ __('branches.dashboard.col_type') }}</th>
                                                <th>{{ __('branches.common.code') }}</th>
                                                <th title="{{ __('branches.dashboard.col_clients_hint') }}">{{ __('branches.dashboard.col_clients') }}</th>
                                                <th title="{{ __('branches.dashboard.col_split_hint') }}">{{ __('branches.dashboard.col_split') }}</th>
                                                <th class="sd-num" title="{{ __('branches.dashboard.col_new_hint') }}">{{ __('branches.dashboard.col_new') }}</th>
                                                <th class="sd-num" title="{{ __('branches.dashboard.fact_employees') }}">{{ __('branches.dashboard.col_employees') }}</th>
                                                <th>{{ __('branches.common.status') }}</th>
                                                <th class="text-center">{{ __('branches.common.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($treeRows as $row)
                                                @php
                                                    $meta = $nodeTypeMeta[$row['type']] ?? $nodeTypeMeta['branch'];
                                                    // العمقُ نسبةً إلى أعلى المعروض: تحت جهةٍ مختارةٍ يبدأ أبناؤها من الصفر
                                                    $level = $row['depth'] - $treeBase;
                                                @endphp

                                                {{-- مطويّةٌ عند الفتح: أعلى مستوى وحدَه ظاهر، وما دونه
                                                     يبقى مخفياً حتى يفتحه المستخدم بسهمه --}}
                                                <tr class="bxd-row sd-tree-d{{ min($level, 3) }}"
                                                    data-tree-id="{{ $row['id'] }}"
                                                    data-tree-parent="{{ $level > 0 ? $row['parent_id'] : '' }}"
                                                    data-level="{{ $level }}"
                                                    @if ($level > 0) hidden @endif
                                                    data-type="{{ $row['type'] }}"
                                                    data-empty="{{ $row['clients'] ? '0' : '1' }}"
                                                    data-search="{{ $row['name'] }} {{ $row['code'] }}">

                                                    {{-- ══ العنصر ══ --}}
                                                    <td class="bxd-node-cell" style="padding-inline-start: calc(.55rem + {{ $level }} * 1.4rem);">
                                                        <div class="bx-node">
                                                            @if ($row['has_children'])
                                                                <button type="button" class="sd-tree-caret" data-bxd-tree-toggle aria-expanded="false"
                                                                        title="{{ __('branches.tree.toggle_hint') }}"><i class="feather icon-chevron-left"></i></button>
                                                            @else
                                                                <span class="sd-tree-leaf"></span>
                                                            @endif

                                                            <span class="bx-node-icon {{ $meta[2] }}">
                                                                <i class="feather {{ $meta[1] }}"></i>
                                                            </span>

                                                            <span class="bxd-ident">
                                                                {{-- الاسمُ يقود إلى النزول لا إلى صفحة العنصر: هذه
                                                                     لوحةُ قراءةٍ تُقرأ مستوىً بعد مستوى، وصفحةُ
                                                                     العنصر في عمود الإجراءات. --}}
                                                                <a href="{{ route('branches.dashboard', ['tree_node' => $row['id']]) }}"
                                                                   class="sd-drill"
                                                                   title="{{ __('branches.dashboard.focus_hint', ['name' => $row['name']]) }}">{{ $row['name'] }}</a>

                                                                <span class="bxd-ident__meta">
                                                                    @if ($row['is_main'])
                                                                        <span class="bxd-type bxd-type--main">{{ __('branches.common.main') }}</span>
                                                                    @endif
                                                                    @if ($row['subtree'])
                                                                        <span title="{{ __('branches.dashboard.subtree_hint') }}">
                                                                            <i class="feather icon-git-branch"></i>
                                                                            {{ trans_choice('branches.dashboard.node_count', $row['subtree'], ['count' => number_format($row['subtree'])]) }}
                                                                        </span>
                                                                    @else
                                                                        <span class="bxd-none">{{ __('branches.dashboard.leaf_node') }}</span>
                                                                    @endif
                                                                </span>
                                                            </span>
                                                        </div>
                                                    </td>

                                                    {{-- ══ النوع ══
                                                         عمودٌ مستقلّ لا شارةٌ في زاوية: النوعُ يحدّد ما يفعله
                                                         العنصر، فيُقرأ ويُفلتَر به. --}}
                                                    <td><span class="bxd-type bxd-type--{{ $meta[2] }}">{{ $meta[0] }}</span></td>

                                                    {{-- ══ الكود ══ --}}
                                                    <td class="sd-num">{{ $row['code'] !== '' ? $row['code'] : '—' }}</td>

                                                    {{-- ══ العملاء: الإجماليّ ثمّ تفصيلُه ══ --}}
                                                    <td class="bxd-clients">
                                                        @if ($row['clients'])
                                                            <span class="bxd-big">{{ number_format($row['clients']) }}</span>
                                                            <span class="sd-bar" role="presentation"
                                                                  title="{{ __('branches.dashboard.share_hint', ['percent' => $row['share']]) }}">
                                                                {{-- حدٌّ أدنى للعرض: عنصرٌ بعميلٍ واحدٍ نصيبُه صفرٌ بعد
                                                                     التقريب، فيختفي شريطُه ويبدو الصفُّ فارغاً. --}}
                                                                <span class="bx-bar__fill--{{ $meta[2] }}" style="width:{{ max($row['share'], 2) }}%"></span>
                                                            </span>
                                                            <span class="bxd-split">
                                                                <span title="{{ __('branches.tree.clients_direct') }}">
                                                                    {{ __('branches.tree.lbl_direct') }} <b>{{ number_format($row['clients_direct']) }}</b>
                                                                </span>
                                                                @if ($row['clients_below'])
                                                                    <span title="{{ __('branches.tree.clients_below') }}">
                                                                        {{ __('branches.tree.lbl_below') }} <b>{{ number_format($row['clients_below']) }}</b>
                                                                    </span>
                                                                @endif
                                                                <span class="bxd-share">{{ $row['share'] }}%</span>
                                                            </span>
                                                        @else
                                                            <span class="bxd-none" title="{{ __('branches.dashboard.no_clients_hint') }}">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- ══ نشِط / موقوف ══ --}}
                                                    <td>
                                                        @if ($row['clients_active'] || $row['clients_stopped'])
                                                            <span class="bxd-pair">
                                                                {{-- الكلمةُ بجانب الرقم: نقطةٌ خضراء وأخرى حمراء
                                                                     وحدَهما لا تقولان أيُّهما النشِط لمن لا يميّز اللون --}}
                                                                <span class="bxd-dot bxd-dot--on" title="{{ __('branches.dashboard.col_active_hint') }}">
                                                                    {{ __('branches.tree.lbl_active') }} {{ number_format($row['clients_active']) }}
                                                                </span>
                                                                <span class="bxd-dot bxd-dot--off" title="{{ __('branches.dashboard.col_stopped_hint') }}">
                                                                    {{ __('branches.tree.lbl_stopped') }} {{ number_format($row['clients_stopped']) }}
                                                                </span>
                                                            </span>
                                                        @else
                                                            <span class="bxd-none">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- ══ الجديد خلال ثلاثين يوماً ══ --}}
                                                    <td class="sd-num">
                                                        @if ($row['clients_new'])
                                                            <span class="sd-delta sd-delta-up" title="{{ __('branches.dashboard.col_new_hint') }}">
                                                                <i class="feather icon-trending-up"></i> {{ number_format($row['clients_new']) }}
                                                            </span>
                                                        @else
                                                            <span class="bxd-none">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- ══ الموظفون ══ --}}
                                                    <td class="sd-num">{{ $row['employees'] ? number_format($row['employees']) : '—' }}</td>

                                                    {{-- ══ الحالة ══ --}}
                                                    <td>
                                                        <span class="sl-state {{ $row['active'] ? 'sl-state--on' : 'sl-state--off' }}">
                                                            {{ $row['active'] ? __('branches.common.active') : __('branches.common.inactive') }}
                                                        </span>
                                                    </td>

                                                    {{-- ══ الإجراءات ══ --}}
                                                    <td class="text-center bxd-row-actions">
                                                        <a href="{{ route('branches.dashboard', ['tree_node' => $row['id']]) }}"
                                                           title="{{ __('branches.dashboard.focus_hint', ['name' => $row['name']]) }}">
                                                            <i class="feather icon-crosshair"></i>
                                                        </a>
                                                        @if ($canListClients && $row['clients'])
                                                            <a href="{{ route('clients.index', ['region' => $row['id'], 'include_descendants' => 1]) }}"
                                                               title="{{ __('branches.dashboard.open_clients_hint') }}">
                                                                <i class="feather icon-users"></i>
                                                            </a>
                                                        @endif
                                                        @if ($canShowNode)
                                                            <a href="{{ route('branches.show', $row['id']) }}"
                                                               title="{{ __('branches.show.view_title', ['name' => $row['name']]) }}">
                                                                <i class="feather icon-eye"></i>
                                                            </a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-none" id="bxd-no-results">
                                    <div class="sd-empty">
                                        <i class="feather icon-search"></i>
                                        {{ __('branches.tree.no_filter_results') }}
                                        <small class="d-block">{{ __('branches.tree.no_filter_results_hint') }}</small>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ ٧) آخر ما جرى ══════════ --}}
            <div class="row">
                <div class="col-12">
                    <div class="sd-card">
                        <div class="sd-card-head">
                            <div>
                                <h4><i class="feather icon-activity"></i> {{ __('branches.dashboard.recent_title') }}</h4>
                            </div>
                        </div>
                        <div class="sd-card-body">
                            @if ($recentLogs->isEmpty())
                                <div class="sd-empty">
                                    <i class="feather icon-inbox"></i>
                                    {{ __('branches.dashboard.recent_empty') }}
                                    <small class="d-block">{{ __('branches.dashboard.recent_empty_hint') }}</small>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="sd-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('branches.dashboard.what_happened') }}</th>
                                                <th class="sd-num">{{ __('branches.dashboard.since') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($recentLogs as $log)
                                                <tr>
                                                    <td>{{ str_replace('**', '', (string) $log->description) }}</td>
                                                    <td class="sd-num">{{ optional($log->created_at)->diffForHumans() ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </section>
    </div>
@endsection

@section('scripts')
    @php
        /*
         * بيانات الرسمَين تُحضَّر هنا في متغيّرات، ثمّ تُطبع بالموجّه json
         * ووسيطٍ واحد.
         *
         * ولماذا لا تُكتب داخل النداء مباشرةً؟ لأن ذلك الموجّه يقسم ما بين
         * قوسيه على الفواصل ليأخذ (القيمة، الخيارات، العمق) — فمصفوفةٌ فيها
         * فواصل تُقرأ ثلاثةَ وسائط، فيخرج جافاسكربت مقصوصاً ويسقط الرسمُ
         * صامتاً.
         */
        $chartSeries = [$stats['regions'], $stats['branches'], $stats['groups'], $stats['outlets']];
        $chartLabels = [
            __('branches.node_types.region'),
            __('branches.node_types.branch_short'),
            __('branches.node_types.group_short'),
            __('branches.node_types.outlet_short'),
        ];
        $chartTotalLabel = __('branches.dashboard.chart_level_total');
        $chartTotalValue = (int) $stats['level'];
        $chartClientsLabel = __('branches.dashboard.col_clients');

        // بترتيب المتحكّم كما هو — الأكبرُ أوّلاً: الأعمدةُ الأفقية تُرسم من
        // الأعلى إلى الأسفل، فأوّلُ السلسلة في القمّة. كانت تُقلَب هنا على ظنّ
        // العكس، فيظهر الأكبرُ في القاع تحت عنوانٍ يقول «الأكبر».
        $topNames = $topNodes->pluck('name')->values();
        $topValues = $topNodes->pluck('clients')->values();

        $filterResultTemplate = __('branches.tree.filter_results', ['count' => ':n']);
    @endphp

    {{-- ApexCharts من أصول النظام لا من شبكةٍ خارجية: اللوحة تُفتح في شبكاتٍ
         مغلقة، ونسخةُ الـCDN كانت ستترك مكان الرسم فارغاً بلا سببٍ ظاهر. --}}
    <script src="{{ asset('app-assets/vendors/js/charts/apexcharts.min.js') }}"></script>

    <script>
        (function () {
            'use strict';

            var root = document.getElementById('branches-dashboard');
            if (!root) { return; }

            var isRtl = document.documentElement.getAttribute('dir') === 'rtl';

            // ══════════ ١) «من أين تأتي هذه البيانات؟» ══════════
            // الزرُّ يُظهر لوحةَ الشرح في بطاقته هو ويخفيها — كما في لوحة المبيعات.
            root.addEventListener('click', function (event) {
                var toggle = event.target.closest('[data-sd-info-toggle]');
                if (!toggle) { return; }

                var card = toggle.closest('.sd-card');
                var panel = card ? card.querySelector('.sd-info-panel') : null;
                if (panel) { panel.hidden = !panel.hidden; }
            });

            // ══════════ ٢) الرسمان ══════════
            if (window.ApexCharts) {
                /*
                 * --sd-primary معرَّفٌ بمتغيّرٍ داخل متغيّر، وقراءتُه نصّاً تعيد
                 * السلسلةَ كما كُتبت لا لوناً فيهجر ApexCharts ألوانَ الثيم.
                 * فيُسنَد المتغيّر إلى خاصيّة لونٍ حقيقية على عنصر مسبار ويُقرأ
                 * rgb صريحاً — نفسُ ما تفعله لوحةُ المبيعات.
                 */
                var probe = document.createElement('span');
                probe.style.display = 'none';
                root.appendChild(probe);
                var SENTINEL = 'rgb(1, 2, 3)';
                var color = function (name, fallback) {
                    probe.style.color = SENTINEL;
                    probe.style.color = 'var(' + name + ')';
                    var resolved = getComputedStyle(probe).color;

                    return (!resolved || resolved === SENTINEL) ? fallback : resolved;
                };

                var palette = {
                    primary: color('--sd-primary', '#7367f0'),
                    success: color('--sd-success', '#28c76f'),
                    warning: color('--sd-warning', '#ff9f43'),
                    info: color('--sd-info', '#00cfe8'),
                    muted: color('--sd-muted', '#7b8194'),
                    border: color('--sd-border', '#e6e9f0'),
                    font: getComputedStyle(root).fontFamily || 'Cairo, sans-serif'
                };

                var base = {
                    chart: { fontFamily: palette.font, toolbar: { show: false } },
                    grid: { borderColor: palette.border, strokeDashArray: 4 }
                };

                /* الرسمُ بعد تحميل الخطّ: ApexCharts يقيس عرضَ أسماء المحور مرّةً
                   واحدة لحظةَ الرسم، فإن سبق الخطَّ قاسها بالبديل الأضيق ثم
                   انزلقت الأسماءُ فوق الأعمدة. */
                var charts = [];
                var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
                var render = function (id, options) {
                    var el = document.getElementById(id);
                    if (!el) { return; }
                    fontsReady.then(function () {
                        var chart = new ApexCharts(el, options);
                        chart.render();
                        charts.push(chart);
                    });
                };

                render('bxdTypesChart', Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, { type: 'donut', height: 250 }),
                    series: @json($chartSeries),
                    labels: @json($chartLabels),
                    // الترتيبُ ترتيبُ السلسلة: منطقة، فرع، مجموعة، منفذ
                    colors: [palette.success, palette.primary, palette.info, palette.warning],
                    legend: { show: false },
                    dataLabels: { enabled: true, formatter: function (val) { return Math.round(val) + '%'; }, style: { fontSize: '12px' } },
                    stroke: { width: 2 },
                    plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: {
                        show: true, label: @json($chartTotalLabel), fontSize: '13px', color: palette.muted,
                        formatter: function () { return @json($chartTotalValue); }
                    } } } } }
                }));

                var topNames = @json($topNames);
                var topValues = @json($topValues);

                render('bxdTopChart', Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, { type: 'bar', height: Math.max(260, topNames.length * 36 + 60) }),
                    series: [{ name: @json($chartClientsLabel), data: topValues }],
                    colors: [palette.primary],
                    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
                    xaxis: { categories: topNames, labels: { style: { colors: palette.muted, fontSize: '11px' } } },
                    yaxis: { opposite: isRtl, labels: { maxWidth: 220, style: { colors: palette.muted, fontSize: '12px', fontFamily: palette.font } } },
                    legend: { show: false },
                    dataLabels: { enabled: true, style: { fontSize: '11px' } },
                    grid: { borderColor: palette.border, strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } }
                }));

                var resizeTimer;
                window.addEventListener('resize', function () {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function () { charts.forEach(function (chart) { chart.render(); }); }, 250);
                });
            }

            // ══════════ ٣) الشجرة: طيٌّ وفتح ══════════
            var rows = Array.prototype.slice.call(document.querySelectorAll('.bxd-row'));
            if (!rows.length) { return; }

            var byId = {};
            var kidsOf = {};
            rows.forEach(function (row) {
                byId[row.getAttribute('data-tree-id')] = row;
                var parent = row.getAttribute('data-tree-parent');
                if (parent) { (kidsOf[parent] = kidsOf[parent] || []).push(row); }
            });

            function setOpen(row, open) {
                var caret = row.querySelector('[data-bxd-tree-toggle]');
                if (caret) { caret.setAttribute('aria-expanded', open ? 'true' : 'false'); }
            }

            // الطيُّ ينزل إلى آخر الفرع: إخفاءُ الأبناء وحدَهم يترك الأحفادَ
            // ظاهرين بلا أبٍ فوقهم.
            function collapse(row) {
                setOpen(row, false);
                (kidsOf[row.getAttribute('data-tree-id')] || []).forEach(function (kid) {
                    kid.hidden = true;
                    collapse(kid);
                });
            }

            function expand(row) {
                setOpen(row, true);
                (kidsOf[row.getAttribute('data-tree-id')] || []).forEach(function (kid) { kid.hidden = false; });
            }

            // ══════════ ٤) التصفية ══════════
            // المطابِقُ يظهر مع آبائه حتى يبقى موضعُه في الشجرة مفهوماً. والآباءُ
            // مسارٌ لا نتيجة، فلا يُحسبون في العدد.
            var search = document.getElementById('bxd-search');
            var typeFilter = document.getElementById('bxd-type');
            var clientsFilter = document.getElementById('bxd-clients');
            var resultLabel = document.getElementById('bxd-result');
            var noResults = document.getElementById('bxd-no-results');
            var table = document.getElementById('bxd-table');
            var resultTemplate = @json($filterResultTemplate);

            function resetFilters() {
                [search, typeFilter, clientsFilter].forEach(function (el) { if (el) { el.value = ''; } });
                if (resultLabel) { resultLabel.textContent = ''; }
                if (noResults) { noResults.classList.add('d-none'); }
                if (table) { table.parentElement.classList.remove('d-none'); }
            }

            root.addEventListener('click', function (event) {
                var caret = event.target.closest('[data-bxd-tree-toggle]');
                if (caret) {
                    var row = caret.closest('.bxd-row');
                    if (caret.getAttribute('aria-expanded') === 'true') { collapse(row); } else { expand(row); }

                    return;
                }

                var all = event.target.closest('[data-bxd-tree-all]');
                if (all) {
                    // «توسيع» و«طيّ» عمليّتان على الشجرة كلِّها، فتُلغيان التصفية
                    // القائمة — وإلّا ظهر بعدهما ما استبعدته والحقولُ ما زالت ممتلئة.
                    resetFilters();
                    var open = all.getAttribute('data-bxd-tree-all') === '1';
                    rows.forEach(function (row) {
                        row.hidden = !open && row.getAttribute('data-level') !== '0';
                        setOpen(row, open);
                    });
                }
            });

            function applyFilters() {
                var term = (search ? search.value : '').trim().toLowerCase();
                var type = typeFilter ? typeFilter.value : '';
                var clients = clientsFilter ? clientsFilter.value : '';
                var active = term !== '' || type !== '' || clients !== '';

                // بلا تصفية: تعود الشجرةُ إلى هيئة الفتح — أعلى مستوى وحدَه
                if (!active) {
                    rows.forEach(function (row) {
                        row.hidden = row.getAttribute('data-level') !== '0';
                        setOpen(row, false);
                    });
                    resetFilters();

                    return;
                }

                rows.forEach(function (row) { row.hidden = true; setOpen(row, false); });

                var shown = 0;
                rows.forEach(function (row) {
                    if (term !== '' && (row.getAttribute('data-search') || '').toLowerCase().indexOf(term) === -1) { return; }
                    if (type !== '' && row.getAttribute('data-type') !== type) { return; }
                    if (clients === 'has' && row.getAttribute('data-empty') === '1') { return; }
                    if (clients === 'none' && row.getAttribute('data-empty') === '0') { return; }

                    row.hidden = false;
                    shown++;

                    var parent = byId[row.getAttribute('data-tree-parent')];
                    var guard = 0;
                    while (parent && guard++ < 50) {
                        parent.hidden = false;
                        setOpen(parent, true);
                        parent = byId[parent.getAttribute('data-tree-parent')];
                    }
                });

                if (resultLabel) { resultLabel.textContent = resultTemplate.replace(':n', shown); }
                if (noResults) { noResults.classList.toggle('d-none', shown > 0); }
                if (table) { table.parentElement.classList.toggle('d-none', shown === 0); }
            }

            // البحثُ يُؤجَّل قليلاً: الكتابةُ السريعة كانت تعيد مسحَ كل الصفوف
            // عند كل حرف.
            var debounce = null;
            if (search) {
                search.addEventListener('input', function () {
                    clearTimeout(debounce);
                    debounce = setTimeout(applyFilters, 140);
                });
            }

            [typeFilter, clientsFilter].forEach(function (el) {
                if (el) { el.addEventListener('change', applyFilters); }
            });
        })();
    </script>
@endsection
