@extends('sitting::master')

@section('title', __('settings.accounting_periods.title'))

@section('content')
    @include('sitting::components._settings_styles')

    @php
        $openPeriods = $years->flatMap->periods->where('status', 'open')->count();
        $closedPeriods = $years->flatMap->periods->whereIn('status', ['closed', 'locked'])->count();
        $canManage = hasPermission('settings.accounting_periods.manage');
        $canClose = hasPermission('settings.accounting_periods.close');
        $canReopen = hasPermission('settings.accounting_periods.reopen');
        // ما لم يُنسَب إلى فترةٍ بعد — يُعرَض ربطُه لا يُفرَض
        $orphans = \App\Support\OrphanedPeriodDocuments::summary();
        $justCreated = session('orphaned_documents');
    @endphp

    {{-- أسبابُ رفض إقفال السنة — من محاولة الإقفال الأخيرة --}}
    @if ($closingBlockers = session('closing_blockers'))
        <div class="alert alert-danger d-block" role="alert">
            <strong class="d-block"><i class="feather icon-slash"></i> لم تُقفل السنة:</strong>
            <ul class="mb-0 mt-1">
                @foreach ((array) $closingBlockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{--
        ══════════ عملياتٌ سابقة بلا سنة مالية ══════════

        المنشأةُ تُدخل بياناتِها قبل أن ترسم تقويمَها، فتبقى بلا نسبة: لا تظهر
        في قائمةٍ ولا تقرير، وهي قائمةٌ في الدفتر ورصيدُ الحساب يحملها. ورسمُ
        السنة هو اللحظةُ التي يصير فيها الربطُ ممكناً، فيُسأل عنه هنا.

        ولا يُربط تلقائياً: نسبةُ مستندٍ إلى فترةٍ قرارٌ محاسبيّ، وقد يكون
        التاريخُ نفسُه خطأً يُصحَّح قبل النسبة.
    --}}
    @if ($orphans['total'] > 0)
        <div class="alert alert-warning" role="alert">
            <h6 class="mb-2">
                <i class="feather icon-link-2"></i>
                عُثر على عمليات سابقة غير مرتبطة بسنة مالية
            </h6>

            <p class="mb-2">
                <strong>{{ number_format($orphans['total']) }}</strong> عملية غير مصنَّفة —
                @foreach ($orphans['tables'] as $orphan)
                    {{ $orphan['label'] }} ({{ number_format($orphan['count']) }}){{ ! $loop->last ? '، ' : '' }}
                @endforeach
                @if ($justCreated)
                    <br>هل تريد ربطها بالسنة المالية «{{ $justCreated['year'] }}»؟
                @else
                    <br>لا تظهر في القوائم ولا التقارير حتى تُربط بفتراتها.
                @endif
            </p>

            <div class="d-flex flex-wrap align-items-center" style="gap:.5rem">
                @if ($canManage)
                    <form method="POST" action="{{ route('AccountingPeriods.attach_orphans') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning">
                            <i class="feather icon-link"></i> نعم، اربطها بفتراتها
                        </button>
                    </form>
                @endif

                <a href="{{ route('journal.index') }}?period_id=none" class="btn btn-sm btn-outline-secondary">
                    <i class="feather icon-eye"></i> استعراضها أولاً
                </a>

                <small class="text-muted">
                    الربط يشمل ما يقع تاريخه في فترة معرَّفة فقط، ولا يمسّ ما صُنِّف من قبل.
                </small>
            </div>
        </div>
    @endif

    <style>
        /* ── ترويسة السنة ── */
        .ap-year > .head { align-items: flex-start; }
        .ap-year .yname { display: flex; flex-direction: column; gap: .2rem; }
        .ap-year .yname h4 { display: flex; align-items: center; gap: .5rem; }
        .ap-year .yrange { font-size: .78rem; color: #6b7280; }
        .ap-year .yrange .st-mono { color: #4b5563; }
        .ap-year .st-actions { margin-inline-start: auto; }

        /* الفترة الجارية تُميَّز بخلفية لا بحدّ: الحدّ يزاحم حدود الجدول */
        .ap-now td { background: #f3f8ff; }
        .ap-now .pname::before {
            content: ''; display: inline-block; width: 7px; height: 7px;
            border-radius: 50%; background: #2b6cb0; margin-inline-end: .45rem;
        }

        .ap-locked td { background: #fdf6f6; }

        /* شريط تقدّم صغير يُظهر موقع الفترة من السنة بلا قراءة تواريخ */
        .ap-bar { height: 4px; background: #eef1f5; border-radius: 99px; overflow: hidden; min-width: 90px; }
        .ap-bar > span { display: block; height: 100%; background: #2b6cb0; border-radius: 99px; }

        .ap-form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .9rem; }
        .ap-help { font-size: .76rem; color: #9aa2ae; margin-top: .2rem; display: block; }
        .ap-toggle-list > .st-toggle-row:first-child { padding-top: 0; }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-calendar"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.accounting_periods.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.accounting_periods.breadcrumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            @if ($canManage)
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#apYearModal"
                    id="apNewYear">
                    <i class="feather icon-plus"></i> {{ __('settings.accounting_periods.new_year') }}
                </button>
            @endif

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="st-stats">
            <div class="st-stat">
                <div class="ic primary"><i class="feather icon-calendar"></i></div>
                <div>
                    <div class="val">{{ $years->count() }}</div>
                    <div class="lbl">{{ __('settings.accounting_periods.stats.years') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic success"><i class="feather icon-unlock"></i></div>
                <div>
                    <div class="val">{{ $openPeriods }}</div>
                    <div class="lbl">{{ __('settings.accounting_periods.stats.open_periods') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic warning"><i class="feather icon-lock"></i></div>
                <div>
                    <div class="val">{{ $closedPeriods }}</div>
                    <div class="lbl">{{ __('settings.accounting_periods.stats.closed_periods') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic info"><i class="feather icon-clock"></i></div>
                <div>
                    <div class="val" style="font-size:1rem">
                        {{ $currentPeriod?->name ?? __('settings.accounting_periods.no_current_period') }}
                    </div>
                    <div class="lbl">{{ __('settings.accounting_periods.stats.current_period') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic {{ $settings->enforce_lock ? 'danger' : 'info' }}">
                    <i class="feather icon-shield"></i>
                </div>
                <div>
                    <div class="val" style="font-size:1rem">
                        {{ $settings->enforce_lock ? __('settings.accounting_periods.enforced') : __('settings.accounting_periods.not_enforced') }}
                    </div>
                    <div class="lbl">{{ __('settings.accounting_periods.stats.enforcement') }}</div>
                </div>
            </div>
        </div>

        <div class="st-note {{ $settings->enforce_lock ? 'warn' : 'info' }}">
            <i class="feather icon-info mt-1"></i>
            <div>
                <strong>{{ __('settings.accounting_periods.intro') }}</strong>
                <p>{{ __('settings.accounting_periods.settings_hint') }}</p>
            </div>
        </div>

        {{-- ══════════ ضوابط القفل ══════════ --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-shield" style="color:#2b6cb0"></i>
                <h4>{{ __('settings.accounting_periods.settings_title') }}</h4>
            </div>
            <form method="POST" action="{{ route('AccountingPeriods.settings') }}">
                @csrf
                <div class="body">
                    <div class="ap-toggle-list">
                        <div class="st-toggle-row">
                            <div class="txt">
                                <b>{{ __('settings.accounting_periods.enforce_lock') }}</b>
                                <small>{{ __('settings.accounting_periods.enforce_lock_hint') }}</small>
                            </div>
                            <label class="st-switch">
                                <input type="checkbox" name="enforce_lock" value="1"
                                    {{ $settings->enforce_lock ? 'checked' : '' }} {{ $canManage ? '' : 'disabled' }}>
                                <span class="track"></span>
                            </label>
                        </div>
                        <div class="st-toggle-row">
                            <div class="txt">
                                <b>{{ __('settings.accounting_periods.block_undefined_dates') }}</b>
                                <small>{{ __('settings.accounting_periods.block_undefined_dates_hint') }}</small>
                            </div>
                            <label class="st-switch">
                                <input type="checkbox" name="block_undefined_dates" value="1"
                                    {{ $settings->block_undefined_dates ? 'checked' : '' }} {{ $canManage ? '' : 'disabled' }}>
                                <span class="track"></span>
                            </label>
                        </div>
                        <div class="st-toggle-row">
                            <div class="txt">
                                <b>{{ __('settings.accounting_periods.block_future_dates') }}</b>
                                <small>{{ __('settings.accounting_periods.block_future_dates_hint') }}</small>
                            </div>
                            <label class="st-switch">
                                <input type="checkbox" name="block_future_dates" value="1"
                                    {{ $settings->block_future_dates ? 'checked' : '' }} {{ $canManage ? '' : 'disabled' }}>
                                <span class="track"></span>
                            </label>
                        </div>
                    </div>

                    <div class="ap-form-row mt-2">
                        <div class="form-group mb-0">
                            <label>{{ __('settings.accounting_periods.lock_before_date') }}</label>
                            <input type="date" name="lock_before_date" class="form-control"
                                value="{{ old('lock_before_date', $settings->lock_before_date?->format('Y-m-d')) }}"
                                {{ $canManage ? '' : 'disabled' }}>
                            <span class="ap-help">{{ __('settings.accounting_periods.lock_before_date_hint') }}</span>
                        </div>
                        <div class="form-group mb-0">
                            <label>{{ __('settings.accounting_periods.default_period_type') }}</label>
                            <select name="default_period_type" class="form-control" {{ $canManage ? '' : 'disabled' }}>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" {{ $settings->default_period_type === $type ? 'selected' : '' }}>
                                        {{ __('settings.accounting_periods.types.'.$type) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                @if ($canManage)
                    <div class="foot text-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> {{ __('settings.common.save_changes') }}
                        </button>
                    </div>
                @endif
            </form>
        </div>

        {{-- ══════════ السنوات المالية ══════════ --}}
        @forelse ($years as $year)
            @php
                $yearEntries = $year->periods->sum(fn ($period) => $entryCounts[$period->id] ?? 0);
                $yearDays = max($year->lengthInDays(), 1);
            @endphp

            <div class="st-card ap-year">
                <div class="head">
                    <div class="yname">
                        <h4>
                            <i class="feather icon-calendar" style="color:#2b6cb0"></i>
                            {{ $year->name }}
                            @if ($year->is_current)
                                <span class="st-badge info">{{ __('settings.accounting_periods.is_current') }}</span>
                            @endif
                            <span class="st-badge {{ $year->isOpen() ? 'on' : 'warn' }}">
                                {{ __('settings.accounting_periods.year_status.'.$year->status) }}
                            </span>
                        </h4>
                        <span class="yrange">
                            <span class="st-mono">{{ $year->start_date->format('Y-m-d') }}</span>
                            &larr;
                            <span class="st-mono">{{ $year->end_date->format('Y-m-d') }}</span>
                            · {{ __('settings.accounting_periods.days', ['count' => $yearDays]) }}
                            · {{ __('settings.accounting_periods.entries_count', ['count' => number_format($yearEntries)]) }}
                            @if ($year->closed_at)
                                · {{ __('settings.accounting_periods.closed_at') }}
                                <span class="st-mono">{{ $year->closed_at->format('Y-m-d') }}</span>
                            @endif
                        </span>
                    </div>

                    <div class="st-actions">
                        {{-- تقرير الإقفال: للمقفلة ما رُحِّل، وللمفتوحة معاينةُ ما سيُرحَّل --}}
                        <a href="{{ route('AccountingPeriods.years.closing_report', $year->id) }}" class="btn btn-sm btn-outline-dark"
                            title="تقرير الإقفال السنوي">
                            <i class="feather icon-file-text"></i> تقرير الإقفال
                        </a>

                        @if ($canManage)
                            @unless ($year->is_current)
                                <form method="POST" action="{{ route('AccountingPeriods.years.current', $year->id) }}"
                                    style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-info"
                                        title="{{ __('settings.accounting_periods.set_current') }}">
                                        <i class="feather icon-star"></i>
                                    </button>
                                </form>
                            @endunless

                            <button type="button" class="btn btn-sm btn-outline-primary ap-edit-year"
                                data-id="{{ $year->id }}" data-name="{{ $year->name }}"
                                data-start="{{ $year->start_date->format('Y-m-d') }}"
                                data-end="{{ $year->end_date->format('Y-m-d') }}"
                                data-notes="{{ $year->notes }}" data-current="{{ $year->is_current ? 1 : 0 }}"
                                title="{{ __('settings.common.edit') }}">
                                <i class="feather icon-edit-2"></i>
                            </button>

                            <button type="button" class="btn btn-sm btn-outline-secondary ap-generate"
                                data-id="{{ $year->id }}" data-name="{{ $year->name }}"
                                title="{{ __('settings.accounting_periods.generate') }}">
                                <i class="feather icon-repeat"></i>
                            </button>

                            <button type="button" class="btn btn-sm btn-outline-success ap-new-period"
                                data-year="{{ $year->id }}" data-name="{{ $year->name }}"
                                data-min="{{ $year->start_date->format('Y-m-d') }}"
                                data-max="{{ $year->end_date->format('Y-m-d') }}"
                                title="{{ __('settings.accounting_periods.new_period') }}">
                                <i class="feather icon-plus"></i>
                            </button>
                        @endif

                        @if ($year->isOpen() && $canClose)
                            @php $readiness = $closingReadiness[$year->id] ?? null; @endphp
                            <form method="POST" action="{{ route('AccountingPeriods.years.close', $year->id) }}"
                                style="display:inline" class="ap-confirm"
                                data-confirm="{{ __('settings.accounting_periods.confirm_close_year', ['name' => $year->name]) }}">
                                @csrf
                                {{-- أسبابُ المنع معروضةٌ تحت رأس السنة؛ الزرّ معطَّلٌ ما دامت قائمة --}}
                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                    @disabled($readiness && ! $readiness['can_close'])
                                    title="{{ $readiness && ! $readiness['can_close'] ? 'لا يمكن الإقفال — راجع الأسباب أدناه' : '' }}">
                                    <i class="feather icon-lock"></i> {{ __('settings.accounting_periods.close_year') }}
                                </button>
                            </form>
                        @elseif (! $year->isOpen() && $canReopen)
                            <form method="POST" action="{{ route('AccountingPeriods.years.reopen', $year->id) }}"
                                style="display:inline" class="ap-confirm"
                                data-confirm="{{ __('settings.accounting_periods.confirm_reopen', ['name' => $year->name]) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="feather icon-unlock"></i> {{ __('settings.accounting_periods.reopen_year') }}
                                </button>
                            </form>
                        @endif

                        @if ($canManage && $year->isOpen())
                            <form method="POST" action="{{ route('AccountingPeriods.years.destroy', $year->id) }}"
                                style="display:inline" class="ap-confirm"
                                data-confirm="{{ __('settings.accounting_periods.confirm_delete_year', ['name' => $year->name]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    title="{{ __('settings.common.delete') }}">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{--
                    ══════════ الإقفال السنوي ══════════
                    السنةُ المفتوحة: ما يمنع إقفالها وما يُنبَّه إليه، ومعاينةُ قيد الإقفال.
                    والمقفلة: قيودُ إقفالها — تُحذف عند إعادة الفتح.
                --}}
                @if ($year->isOpen() && ($readiness = $closingReadiness[$year->id] ?? null))
                    <div class="px-3 pt-3">
                        @if ($readiness['blockers'])
                            <div class="alert alert-danger mb-2 d-block" role="alert">
                                <strong class="d-block"><i class="feather icon-slash"></i> لا يمكن إقفال هذه السنة بعد:</strong>
                                <ul class="mb-0 mt-1">
                                    @foreach ($readiness['blockers'] as $blocker)
                                        <li>{{ $blocker['message'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($readiness['warnings'])
                            <div class="alert alert-warning mb-2 d-block" role="alert">
                                <strong class="d-block"><i class="feather icon-alert-triangle"></i> قبل الإقفال:</strong>
                                <ul class="mb-0 mt-1">
                                    @foreach ($readiness['warnings'] as $warning)
                                        <li>{{ $warning['message'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @php $preview = $readiness['preview']; @endphp
                        <div class="alert alert-light border mb-2 d-block" role="note">
                            <strong class="d-block mb-1"><i class="feather icon-file-text"></i> قيد الإقفال عند إقفال السنة:</strong>
                            إيرادات <strong>@money($preview['revenue'])</strong>
                            — مصروفات <strong>@money($preview['expenses'])</strong>
                            = صافي {{ $preview['net'] >= 0 ? 'ربح' : 'خسارة' }} <strong>@money(abs($preview['net']))</strong>
                            يُرحَّل إلى <strong>{{ $preview['retained_account'] ?? '— (غير محدد)' }}</strong>
                            في {{ $preview['entries'] }} قيد ({{ $preview['lines'] }} سطر)، بتاريخ {{ $year->end_date->format('Y-m-d') }}.
                        </div>
                    </div>
                @elseif (! $year->isOpen() && ($closing = $closingEntries[$year->id] ?? collect())->isNotEmpty())
                    <div class="px-3 pt-3">
                        <div class="alert alert-light border mb-2 d-block" role="note">
                            <strong class="d-block mb-1"><i class="feather icon-file-text"></i> قيود الإقفال:</strong>
                            @foreach ($closing as $closingEntry)
                                @if (Route::has('journal.show'))
                                    <a href="{{ route('journal.show', $closingEntry->id) }}">{{ $closingEntry->reference_number }}</a>{{ ! $loop->last ? '، ' : '' }}
                                @else
                                    <span>{{ $closingEntry->reference_number }}</span>{{ ! $loop->last ? '، ' : '' }}
                                @endif
                            @endforeach
                            — تُعكَس بقيودٍ عكسية عند إعادة فتح السنة (لا تُحذف).
                            <a href="{{ route('AccountingPeriods.years.closing_report', $year->id) }}">تقرير الإقفال</a>
                        </div>
                    </div>
                @endif

                <div class="body flush">
                    @if ($year->periods->isEmpty())
                        <div class="st-empty">
                            <i class="feather icon-calendar"></i>
                            {{ __('settings.accounting_periods.no_periods') }}
                            <div style="font-size:.82rem">{{ __('settings.accounting_periods.no_periods_hint') }}</div>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="st-table">
                                <thead>
                                    <tr>
                                        <th style="width:26%">{{ __('settings.accounting_periods.period_name') }}</th>
                                        <th style="width:16%">{{ __('settings.accounting_periods.start_date') }}</th>
                                        <th style="width:16%">{{ __('settings.accounting_periods.end_date') }}</th>
                                        <th style="width:12%">{{ __('settings.accounting_periods.entries') }}</th>
                                        <th style="width:14%">{{ __('settings.common.status') }}</th>
                                        <th style="width:16%" class="text-center">{{ __('settings.common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($year->periods as $period)
                                        @php
                                            $entries = $entryCounts[$period->id] ?? 0;
                                            $isNow = $currentPeriod && $currentPeriod->id === $period->id;
                                            // موقع الفترة من السنة — للشريط الصغير وحده
                                            $offset = max(0, $year->start_date->diffInDays($period->start_date));
                                            $span = max(1, $period->start_date->diffInDays($period->end_date) + 1);
                                        @endphp
                                        <tr class="{{ $isNow ? 'ap-now' : '' }} {{ $period->isLocked() ? 'ap-locked' : '' }}">
                                            <td>
                                                <span class="pname">{{ $period->name }}</span>
                                                <div class="ap-bar mt-1">
                                                    <span style="margin-inline-start:{{ round($offset / $yearDays * 100, 2) }}%;
                                                                 width:{{ round($span / $yearDays * 100, 2) }}%"></span>
                                                </div>
                                            </td>
                                            <td class="st-mono">{{ $period->start_date->format('Y-m-d') }}</td>
                                            <td class="st-mono">{{ $period->end_date->format('Y-m-d') }}</td>
                                            <td class="num">
                                                @if ($entries > 0)
                                                    <span class="st-badge info">{{ number_format($entries) }}</span>
                                                @else
                                                    <span class="st-badge off">{{ __('settings.accounting_periods.no_entries') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="st-badge {{ $period->badgeTone() }}">
                                                    {{ __('settings.accounting_periods.status.'.$period->status) }}
                                                </span>
                                                @if ($period->closed_at)
                                                    <div style="font-size:.7rem;color:#9aa2ae">
                                                        {{ $period->closed_at->format('Y-m-d') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="st-actions" style="justify-content:center">
                                                    @if ($canManage && ! $period->isLocked())
                                                        <button type="button" class="btn btn-sm btn-outline-primary ap-edit-period"
                                                            data-id="{{ $period->id }}" data-name="{{ $period->name }}"
                                                            data-start="{{ $period->start_date->format('Y-m-d') }}"
                                                            data-end="{{ $period->end_date->format('Y-m-d') }}"
                                                            data-notes="{{ $period->notes }}"
                                                            data-min="{{ $year->start_date->format('Y-m-d') }}"
                                                            data-max="{{ $year->end_date->format('Y-m-d') }}"
                                                            title="{{ __('settings.common.edit') }}">
                                                            <i class="feather icon-edit-2"></i>
                                                        </button>
                                                    @endif

                                                    @if ($period->isOpen() && $canClose)
                                                        <form method="POST"
                                                            action="{{ route('AccountingPeriods.periods.close', $period->id) }}"
                                                            style="display:inline" class="ap-confirm"
                                                            data-confirm="{{ __('settings.accounting_periods.confirm_close', ['name' => $period->name]) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-warning"
                                                                title="{{ __('settings.accounting_periods.close') }}">
                                                                <i class="feather icon-lock"></i>
                                                            </button>
                                                        </form>

                                                        @if ($canReopen)
                                                            <form method="POST"
                                                                action="{{ route('AccountingPeriods.periods.close', $period->id) }}"
                                                                style="display:inline" class="ap-confirm"
                                                                data-confirm="{{ __('settings.accounting_periods.confirm_close_permanent', ['name' => $period->name]) }}">
                                                                @csrf
                                                                <input type="hidden" name="permanent" value="1">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                    title="{{ __('settings.accounting_periods.close_permanent') }}">
                                                                    <i class="feather icon-shield-off"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endif

                                                    @if ($period->canReopen() && $canReopen)
                                                        <form method="POST"
                                                            action="{{ route('AccountingPeriods.periods.reopen', $period->id) }}"
                                                            style="display:inline" class="ap-confirm"
                                                            data-confirm="{{ __('settings.accounting_periods.confirm_reopen', ['name' => $period->name]) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-success"
                                                                title="{{ __('settings.accounting_periods.reopen') }}">
                                                                <i class="feather icon-unlock"></i>
                                                            </button>
                                                        </form>
                                                    @endif

                                                    @if ($canManage && $period->isOpen())
                                                        <form method="POST"
                                                            action="{{ route('AccountingPeriods.periods.destroy', $period->id) }}"
                                                            style="display:inline" class="ap-confirm"
                                                            data-confirm="{{ __('settings.accounting_periods.confirm_delete_period', ['name' => $period->name]) }}">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                title="{{ __('settings.common.delete') }}">
                                                                <i class="feather icon-trash-2"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="st-card">
                <div class="body">
                    <div class="st-empty">
                        <i class="feather icon-calendar"></i>
                        {{ __('settings.accounting_periods.empty') }}
                        <div style="font-size:.82rem">{{ __('settings.accounting_periods.empty_hint') }}</div>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if ($canManage)
        {{-- ══════════ نافذة السنة: إنشاء وتعديل بنموذج واحد ══════════ --}}
        <div class="modal fade" id="apYearModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form method="POST" id="apYearForm" action="{{ route('AccountingPeriods.years.store') }}">
                        @csrf
                        {{-- يُملأ بـPUT عند التعديل ويُفرَّغ عند الإنشاء --}}
                        <input type="hidden" name="_method" id="apYearMethod" value="POST">

                        <div class="modal-header">
                            <h5 class="modal-title" id="apYearTitle">{{ __('settings.accounting_periods.new_year') }}</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <div class="modal-body">
                            <div class="form-group">
                                <label>{{ __('settings.accounting_periods.year_name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="apYearName" class="form-control" required
                                    placeholder="{{ __('settings.accounting_periods.year_name_placeholder') }}">
                            </div>

                            <div class="ap-form-row">
                                <div class="form-group mb-0">
                                    <label>{{ __('settings.accounting_periods.start_date') }} <span class="text-danger">*</span></label>
                                    <input type="date" name="start_date" id="apYearStart" class="form-control" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label>{{ __('settings.accounting_periods.end_date') }} <span class="text-danger">*</span></label>
                                    <input type="date" name="end_date" id="apYearEnd" class="form-control" required>
                                </div>
                            </div>

                            <div class="form-group mt-1" id="apYearTypeWrap">
                                <label>{{ __('settings.accounting_periods.period_type') }}</label>
                                <select name="period_type" class="form-control">
                                    @foreach ($types as $type)
                                        <option value="{{ $type }}" {{ $settings->default_period_type === $type ? 'selected' : '' }}>
                                            {{ __('settings.accounting_periods.types.'.$type) }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="ap-help">{{ __('settings.accounting_periods.generate_hint') }}</span>
                            </div>

                            <div class="form-group">
                                <label>{{ __('settings.common.notes') }}</label>
                                <textarea name="notes" id="apYearNotes" class="form-control" rows="2"></textarea>
                            </div>

                            <label class="st-switch">
                                <input type="checkbox" name="is_current" id="apYearCurrent" value="1">
                                <span class="track"></span>
                                <span>{{ __('settings.accounting_periods.set_current') }}</span>
                            </label>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                {{ __('settings.common.cancel') }}
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> {{ __('settings.common.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ══════════ نافذة الفترة ══════════ --}}
        <div class="modal fade" id="apPeriodModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form method="POST" id="apPeriodForm">
                        @csrf
                        <input type="hidden" name="_method" id="apPeriodMethod" value="POST">

                        <div class="modal-header">
                            <h5 class="modal-title" id="apPeriodTitle">{{ __('settings.accounting_periods.new_period') }}</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <div class="modal-body">
                            <div class="form-group">
                                <label>{{ __('settings.accounting_periods.period_name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="apPeriodName" class="form-control" required
                                    placeholder="{{ __('settings.accounting_periods.period_name_placeholder') }}">
                            </div>

                            <div class="ap-form-row">
                                <div class="form-group mb-0">
                                    <label>{{ __('settings.accounting_periods.start_date') }} <span class="text-danger">*</span></label>
                                    <input type="date" name="start_date" id="apPeriodStart" class="form-control" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label>{{ __('settings.accounting_periods.end_date') }} <span class="text-danger">*</span></label>
                                    <input type="date" name="end_date" id="apPeriodEnd" class="form-control" required>
                                </div>
                            </div>

                            <div class="form-group mt-1">
                                <label>{{ __('settings.common.notes') }}</label>
                                <textarea name="notes" id="apPeriodNotes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                {{ __('settings.common.cancel') }}
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> {{ __('settings.common.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ══════════ نافذة التوليد ══════════ --}}
        <div class="modal fade" id="apGenerateModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form method="POST" id="apGenerateForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('settings.accounting_periods.regenerate') }}</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p style="font-size:.88rem;color:#6b7280" id="apGenerateHint"></p>
                            <div class="form-group mb-0">
                                <label>{{ __('settings.accounting_periods.period_type') }}</label>
                                <select name="period_type" class="form-control">
                                    @foreach ($types as $type)
                                        <option value="{{ $type }}" {{ $settings->default_period_type === $type ? 'selected' : '' }}>
                                            {{ __('settings.accounting_periods.types.'.$type) }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="ap-help">{{ __('settings.accounting_periods.generate_hint') }}</span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                {{ __('settings.common.cancel') }}
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-repeat"></i> {{ __('settings.accounting_periods.generate') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script>
        (function () {
            // كل النماذج الخطرة تمرّ بتأكيد واحد — لا نسخة من `confirm` في كل زرّ
            document.addEventListener('submit', function (event) {
                var form = event.target.closest('.ap-confirm');
                if (!form) return;

                if (!confirm(form.dataset.confirm)) event.preventDefault();
            });

            @if ($canManage)
                var yearForm = document.getElementById('apYearForm');
                var periodForm = document.getElementById('apPeriodForm');
                var generateForm = document.getElementById('apGenerateForm');

                var storeYearUrl = @json(route('AccountingPeriods.years.store'));
                var updateYearUrl = @json(route('AccountingPeriods.years.update', ['fiscalYear' => '__ID__']));
                var storePeriodUrl = @json(route('AccountingPeriods.periods.store', ['fiscalYear' => '__ID__']));
                var updatePeriodUrl = @json(route('AccountingPeriods.periods.update', ['accountingPeriod' => '__ID__']));
                var generateUrl = @json(route('AccountingPeriods.years.generate', ['fiscalYear' => '__ID__']));

                var generateHintText = @json(__('settings.accounting_periods.regenerate'));

                // إنشاء سنة: النموذج يُفرَّغ صراحةً لأن النافذة نفسها تُستعمل
                // للتعديل، وبقايا التعديل السابق تظهر في الإنشاء التالي
                document.getElementById('apNewYear').addEventListener('click', function () {
                    yearForm.action = storeYearUrl;
                    document.getElementById('apYearMethod').value = 'POST';
                    document.getElementById('apYearTitle').textContent = @json(__('settings.accounting_periods.new_year'));
                    document.getElementById('apYearTypeWrap').style.display = '';
                    yearForm.reset();
                });

                document.querySelectorAll('.ap-edit-year').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var data = button.dataset;

                        yearForm.action = updateYearUrl.replace('__ID__', data.id);
                        document.getElementById('apYearMethod').value = 'PUT';
                        document.getElementById('apYearTitle').textContent = @json(__('settings.accounting_periods.edit_year'));
                        document.getElementById('apYearName').value = data.name;
                        document.getElementById('apYearStart').value = data.start;
                        document.getElementById('apYearEnd').value = data.end;
                        document.getElementById('apYearNotes').value = data.notes || '';
                        document.getElementById('apYearCurrent').checked = data.current === '1';

                        // التوليد لا يُعرَض في التعديل: له زرّه ونافذته، وخلطه
                        // بالتعديل يجعل تصحيح اسم سنة يمحو فتراتها
                        document.getElementById('apYearTypeWrap').style.display = 'none';

                        $('#apYearModal').modal('show');
                    });
                });

                document.querySelectorAll('.ap-new-period').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var data = button.dataset;

                        periodForm.action = storePeriodUrl.replace('__ID__', data.year);
                        document.getElementById('apPeriodMethod').value = 'POST';
                        document.getElementById('apPeriodTitle').textContent =
                            @json(__('settings.accounting_periods.new_period')) + ' — ' + data.name;
                        periodForm.reset();

                        // حدود السنة تُفرَض في المتصفح أيضاً — الخادم يفحصها
                        // على أي حال، لكن منع الخطأ خير من ردّه
                        setPeriodBounds(data.min, data.max);

                        $('#apPeriodModal').modal('show');
                    });
                });

                document.querySelectorAll('.ap-edit-period').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var data = button.dataset;

                        periodForm.action = updatePeriodUrl.replace('__ID__', data.id);
                        document.getElementById('apPeriodMethod').value = 'PUT';
                        document.getElementById('apPeriodTitle').textContent = @json(__('settings.accounting_periods.edit_period'));
                        document.getElementById('apPeriodName').value = data.name;
                        document.getElementById('apPeriodStart').value = data.start;
                        document.getElementById('apPeriodEnd').value = data.end;
                        document.getElementById('apPeriodNotes').value = data.notes || '';

                        setPeriodBounds(data.min, data.max);

                        $('#apPeriodModal').modal('show');
                    });
                });

                document.querySelectorAll('.ap-generate').forEach(function (button) {
                    button.addEventListener('click', function () {
                        generateForm.action = generateUrl.replace('__ID__', button.dataset.id);
                        document.getElementById('apGenerateHint').textContent =
                            generateHintText + ' — ' + button.dataset.name;

                        $('#apGenerateModal').modal('show');
                    });
                });

                function setPeriodBounds(min, max) {
                    document.getElementById('apPeriodStart').min = min;
                    document.getElementById('apPeriodStart').max = max;
                    document.getElementById('apPeriodEnd').min = min;
                    document.getElementById('apPeriodEnd').max = max;
                }
            @endif
        })();
    </script>
@endsection
