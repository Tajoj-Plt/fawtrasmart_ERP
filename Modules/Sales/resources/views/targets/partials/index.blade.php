{{--
    قائمة الأهداف — قالبٌ واحد يخدم «أهداف المبيعات» و«أهداف التحصيلات».

    كانت الشاشتان ملفّين متطابقين إلّا في أسماء المسارات (١٧٧٣٣ بايت
    و١٧٨٣٧)، على تخطيطٍ لا يمتّ إلى بقيّة وحدة المبيعات بصلة: بطاقاتٌ
    بأنماطٍ محقونة في الوسم، وعناوينُ عربية مكتوبة في القالب لا تُترجَم،
    وأرقامٌ عارية بلا رمز عملة. صارت قالباً واحداً على لغة `sl-` نفسها
    التي تعمل بها شاشات الإعدادات والفواتير، ونصوصُه من `sales_targets`،
    ومبالغُه من `CurrencySettings` — فيتبع رمزَ العملة وفواصلَها كما ضُبطت
    في «إعدادات النظام ← العملات».

    ما يفترق فيه النوعان يصل في `$screen` من `Modules\Sales\Support\TargetScreen`.

    المتغيّرات المتوقَّعة:
      $screen     وصفُ النوع — انظر TargetScreen::config()
      $targets    LengthAwarePaginator
      $stats      ['total','active','inactive','employees','target_total']
      $pageSizes  خيارات عدد السجلّات
--}}
@php
    $r = $screen['routes'];
    $hasFilters = request()->hasAny(['search', 'status']);
    $canManage = hasPermission('sales.targets.manage');
    $canSettle = hasPermission('sales.targets.settle');

    // «تفعيل :type» في إعدادات الأهداف — الإيقاف يمنع الإنشاء والتعديل
    // والتسوية. من غير هذا السطر لا يعرف المستخدم لماذا رُدَّ حفظُه
    $typeDisabled = ! \App\Models\TargetSetting::for($screen['settings_type'])->enabled;
@endphp

<div class="content-body sl-scope">

    {{-- يُعرَّف قبل الترويسة: `yieldContent` تقرأ ما سُجّل حتى تلك اللحظة --}}
    @section('page-actions')
        @if ($canManage)
            <a href="{{ route($r['create']) }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> {{ $screen['new'] }}
            </a>
        @endif

        <a href="{{ $screen['settings_url'] }}" class="btn btn-outline-primary">
            <i class="feather icon-sliders"></i> {{ __('sales_targets.actions.settings') }}
        </a>

        <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
            {{ __('sales_settings.ui.back') }}
        </a>
    @endsection

    @include('sales::components.page-head', [
        'title' => $screen['title'],
        'icon' => $screen['icon'],
        'sub' => $screen['subtitle'],
        'crumbs' => [
            __('sales_settings.ui.home') => route('dashboard'),
            __('sales_settings.hub.title') => route('sittingInvoice.index'),
            $screen['title'] => null,
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')
    @include('sales::components.form-errors')

    @if ($typeDisabled)
        <div class="alert alert-warning">
            <i class="feather icon-pause-circle me-1"></i>
            {{ __('sales_targets.messages.type_disabled') }}
            <a href="{{ $screen['settings_url'] }}" class="alert-link">
                {{ __('sales_targets.actions.settings') }}
            </a>
        </div>
    @endif

    {{-- الإعدادات التي تعمل بها الشاشة: العملة تُنسّق كل مبلغ هنا، وحساب
         العمولة يستقبل قيد التسوية، والفترة المحاسبية قد تردّها. كان ذلك
         كلّه في الخلفية بلا ذِكر، فتُرفَض التسوية ولا يعرف المستخدم أين
         يُصلحها --}}
    @include('sales::components.system-context', [
        'only' => ['currency', 'accounts', 'period', 'payments', 'language'],
        'hint' => $screen['achievement_source'],
    ])

    {{-- ══════════ بطاقات الإحصاء ══════════ --}}
    <div class="sl-stats">
        <div class="sl-stat">
            <div class="sl-stat__icon"><i class="feather icon-{{ $screen['icon'] }}"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($stats['total'], 0) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.stats.total') }}</p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-check-circle"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($stats['active'], 0) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.stats.active') }}</p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--warning"><i class="feather icon-pause-circle"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($stats['inactive'], 0) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.stats.inactive') }}</p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-users"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($stats['employees'], 0) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.stats.employees') }}</p>
            </div>
        </div>
    </div>

    {{-- ══════════ البحث والتصفية ══════════ --}}
    <div class="card sl-filters">
        <div class="card-body">
            <div class="sl-filters__head">
                <h6 class="sl-filters__title">
                    <i class="feather icon-filter"></i> {{ __('sales_settings.ui.filters') }}
                </h6>

                @if ($hasFilters)
                    <span class="sl-count">{{ __('sales_settings.ui.filters_active') }}</span>
                @endif
            </div>

            <form method="GET" action="{{ route($r['index']) }}">
                <div class="row">
                    <div class="col-md-8 col-sm-12">
                        <div class="form-group">
                            <label for="search">{{ __('sales_settings.ui.search') }}</label>
                            <input type="text" id="search" name="search" class="form-control"
                                   value="{{ request('search') }}"
                                   placeholder="{{ __('sales_targets.fields.name') }}">
                        </div>
                    </div>

                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="status">{{ __('sales_settings.ui.status') }}</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">{{ __('sales_settings.ui.all_statuses') }}</option>
                                <option value="active" @selected(request('status') === 'active')>
                                    {{ __('sales_settings.ui.active') }}
                                </option>
                                <option value="inactive" @selected(request('status') === 'inactive')>
                                    {{ __('sales_settings.ui.inactive') }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="sl-filters__foot">
                    @if ($hasFilters)
                        <a href="{{ route($r['index']) }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> {{ __('sales_settings.ui.filters_clear') }}
                        </a>
                    @endif

                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-search"></i> {{ __('sales_settings.ui.filters_apply') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════ الجدول ══════════ --}}
    <div class="card sl-table-card">
        <div class="sl-table-head">
            <h6 class="sl-table-head__title">
                <i class="feather icon-{{ $screen['icon'] }}"></i> {{ $screen['title'] }}
            </h6>
            <span class="sl-count">{{ \App\Support\CurrencySettings::number($targets->total(), 0) }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('sales_targets.fields.name') }}</th>
                        <th class="text-center">{{ __('sales_targets.fields.target_value') }}</th>
                        <th class="text-center">{{ __('sales_targets.fields.period') }}</th>
                        <th class="text-center">{{ __('sales_targets.stats.employees') }}</th>
                        <th class="text-center">{{ __('sales_targets.stats.tiers') }}</th>
                        <th class="text-center" style="width:150px">{{ __('sales_targets.stats.percentage') }}</th>
                        <th class="text-center">{{ __('sales_settings.ui.status') }}</th>
                        <th class="text-center" style="width:170px">{{ __('sales_settings.ui.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($targets as $target)
                        @php
                            // الإسنادات محمَّلة مسبقاً في المتحكّم؛ الجمع هنا على
                            // مجموعةٍ في الذاكرة لا استعلامٌ داخل حلقة
                            $tTarget = (float) $target->achievements->sum('target_amount');
                            $tAchieved = (float) $target->achievements->sum('achieved_amount');
                            $pct = $tTarget > 0 ? ($tAchieved / $tTarget) * 100 : 0;
                            $isActive = $target->status === 'active';
                        @endphp

                        <tr>
                            <td>
                                <div class="sl-media">
                                    <span class="sl-media__thumb" aria-hidden="true">
                                        <i class="feather icon-{{ $screen['icon'] }}"></i>
                                    </span>
                                    <div class="sl-media__body">
                                        <a href="{{ route($r['show'], $target->id) }}" class="sl-media__title">
                                            {{ $target->name }}
                                        </a>
                                        @if ($target->description)
                                            <span class="sl-media__sub">{{ Str::limit($target->description, 60) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- بعملة النظام لا برقم عارٍ: كان `number_format(...,2)` --}}
                            <td class="text-center sl-num">
                                {{ \App\Support\CurrencySettings::format((float) $target->target_value) }}
                            </td>

                            <td class="text-center">
                                <span class="badge badge-{{ $target->commission_period === 'weekly' ? 'info' : 'primary' }}">
                                    {{ __('sales_targets.fields.periods.'.($target->commission_period ?: 'monthly')) }}
                                </span>

                                {{-- مدّةُ الهدف تحت الدورية — تظهر حين ضُبطت وحدها --}}
                                @if ($target->hasPeriod())
                                    <small class="d-block sl-help sl-num">
                                        {{ $target->start_date->format('Y-m-d') }} ← {{ $target->end_date->format('Y-m-d') }}
                                    </small>
                                @endif
                            </td>

                            <td class="text-center sl-num">{{ $target->achievements->count() }}</td>

                            <td class="text-center sl-num">{{ $target->commissionTiers->count() }}</td>

                            <td class="text-center">
                                @include('sales::targets.partials.progress', ['percentage' => $pct])
                            </td>

                            <td class="text-center">
                                <span class="sl-state {{ $isActive ? 'sl-state--on' : 'sl-state--off' }}">
                                    {{ $isActive ? __('sales_settings.ui.active') : __('sales_settings.ui.inactive') }}
                                </span>
                            </td>

                            <td class="text-center">
                                <div class="sl-actions">
                                    <a href="{{ route($r['show'], $target->id) }}"
                                       class="btn btn-outline-secondary" title="{{ __('sales_settings.ui.view') }}"
                                       aria-label="{{ __('sales_settings.ui.view') }}">
                                        <i class="feather icon-eye"></i>
                                    </a>

                                    @if ($canManage || $canSettle)
                                        <a href="{{ route($r['report'], $target->id) }}"
                                           class="btn btn-outline-info" title="{{ __('sales_targets.actions.report') }}"
                                           aria-label="{{ __('sales_targets.actions.report') }}">
                                            <i class="feather icon-bar-chart-2"></i>
                                        </a>
                                    @endif

                                    @if ($canManage)
                                        <a href="{{ route($r['edit'], $target->id) }}"
                                           class="btn btn-outline-primary" title="{{ __('sales_settings.ui.edit') }}"
                                           aria-label="{{ __('sales_settings.ui.edit') }}">
                                            <i class="feather icon-edit-2"></i>
                                        </a>

                                        <form action="{{ route($r['destroy'], $target->id) }}"
                                              method="POST" class="sl-delete-form d-inline"
                                              data-name="{{ $target->name }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger"
                                                    title="{{ __('sales_settings.ui.delete') }}"
                                                    aria-label="{{ __('sales_settings.ui.delete') }}">
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                @include('sales::components.empty-state', [
                                    'icon' => $screen['icon'],
                                    'title' => $hasFilters
                                        ? __('sales_settings.ui.no_results')
                                        : $screen['empty'],
                                    'hint' => $hasFilters
                                        ? __('sales_settings.ui.no_results_hint')
                                        : $screen['empty_hint'],
                                    'action' => $hasFilters
                                        ? '<a href="'.route($r['index']).'" class="btn btn-outline-secondary">'
                                            .'<i class="feather icon-x"></i> '.e(__('sales_settings.ui.filters_clear')).'</a>'
                                        : ($canManage
                                            ? '<a href="'.route($r['create']).'" class="btn btn-primary">'
                                                .'<i class="feather icon-plus"></i> '.e($screen['new']).'</a>'
                                            : ''),
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('sales::components.table-foot', [
            'paginator' => $targets,
            'sizes' => $pageSizes,
        ])
    </div>
</div>
