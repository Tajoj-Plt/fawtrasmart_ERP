@extends('sitting::master')

@section('title', __('settings.session_logs.title'))

@section('css')
    <style>
        .sl { --b:#e3e7ef; --muted:#79809a; --soft:#f7f9fc; --accent: var(--app-primary,#7367F0); --ink:#2f3349; }

        .sl-pill { font-size:.71rem; border-radius:20px; padding:.12rem .6rem; border:1px solid var(--b);
            white-space:nowrap; display:inline-block; }
        .sl-pill.ok { background:#eaf8f0; color:#1c6b40; border-color:#c7e9d5; }
        .sl-pill.no { background:#fdeeee; color:#a13333; border-color:#f5cccc; }
        .sl-pill.wait { background:#fff8e6; color:#8a5b00; border-color:#ffe0a3; }
        .sl-pill.neutral { background:#f2f4f8; color:#4a5069; border-color:#e0e4ec; }

        .sl-actor { font-weight:700; color:var(--ink); font-size:.85rem; }
        .sl-sub { display:block; font-size:.72rem; color:var(--muted); margin-top:.1rem; }
        .sl-ip { font-family:ui-monospace,monospace; font-size:.75rem; color:#4a4f61; direction:ltr; display:inline-block; }
        .sl-device { font-size:.75rem; color:#5a6076; }
        .sl-device i { color:var(--muted); width:14px; text-align:center; }

        /* نقطة نابضة للجلسة القائمة الآن */
        .sl-live { display:inline-block; width:7px; height:7px; border-radius:50%; background:#28a745;
            margin-inline-end:.35rem; animation:slPulse 1.8s ease-in-out infinite; }
        @keyframes slPulse { 0%,100% { opacity:1; } 50% { opacity:.35; } }
        @media (prefers-reduced-motion: reduce) { .sl-live { animation:none; } }

        .sl-empty { text-align:center; padding:2.5rem 1rem; color:var(--muted); }
        .sl-empty i { font-size:2rem; color:#c9cfdd; display:block; margin-bottom:.6rem; }
    </style>
@endsection

@section('content')
    <link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">

    <div class="sl">
    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-log-in"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.session_logs.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.session_logs.crumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('UserSessions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather icon-monitor"></i> {{ __('settings.session_logs.live_sessions') }}
            </a>
            <button type="button" class="btn btn-outline-danger btn-sm" data-toggle="modal" data-target="#cleanupModal">
                <i class="feather icon-trash-2"></i> {{ __('settings.session_logs.cleanup') }}
            </button>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- الفرق بين هذه الشاشة وشاشة الجلسات القائمة يُقال صراحةً:
             وجود شاشتين متشابهتي الاسم بلا بيان يربك أكثر مما يفيد --}}
        <div class="alert d-flex align-items-start"
            style="background:#f4f6fb;border:1px solid #dde3ee;color:#4a5069;border-radius:10px">
            <i class="feather icon-info mt-1 ml-2"></i>
            <div style="font-size:.85rem">
                {!! __('settings.session_logs.intro', [
                    'link' => '<a href="'.route('UserSessions.index').'">'.__('settings.session_logs.live_sessions').'</a>',
                ]) !!}
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row mb-2">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-log-in"></i></div>
                <div class="stat-label">{{ __('settings.session_logs.stats.total') }}</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up"><i class="feather icon-calendar"></i>
                    <span>{{ __('settings.session_logs.stats.today', ['count' => number_format($stats['today'])]) }}</span></div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-activity"></i></div>
                <div class="stat-label">{{ __('settings.session_logs.stats.active') }}</div>
                <div class="stat-value">{{ number_format($stats['active']) }}</div>
                <div class="stat-trend up"><i class="feather icon-users"></i>
                    <span>{{ __('settings.session_logs.stats.users_today', ['count' => number_format($stats['users_today'])]) }}</span></div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-clock"></i></div>
                <div class="stat-label">{{ __('settings.session_logs.stats.avg_duration') }}</div>
                <div class="stat-value" style="font-size:1.15rem">
                    @php $avg = new \App\Models\SessionLog(['duration_seconds' => $stats['avg_duration']]); @endphp
                    {{ $stats['avg_duration'] > 0 ? $avg->durationForHumans() : '—' }}
                </div>
                <div class="stat-trend up"><i class="feather icon-log-out"></i>
                    <span>{{ __('settings.session_logs.stats.ended', ['count' => number_format($stats['ended'])]) }}</span></div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-shield-off"></i></div>
                <div class="stat-label">{{ __('settings.session_logs.stats.failed') }}</div>
                <div class="stat-value">{{ number_format($stats['failed']) }}</div>
                <div class="stat-trend up"><i class="feather icon-alert-triangle"></i>
                    <span>{{ __('settings.session_logs.stats.failed_hint') }}</span></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card filter-card-modern mb-2">
            <div class="card-body">
                <form method="GET" action="{{ route('SessionLogs.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-3 col-12 mb-1">
                            <label>{{ __('settings.common.search') }}</label>
                            <input type="text" name="search" class="form-control"
                                value="{{ request('search') }}" placeholder="{{ __('settings.session_logs.search_placeholder') }}">
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.session_logs.user') }}</label>
                            <select name="user_id" class="form-control">
                                <option value="">{{ __('settings.session_logs.all_users') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" {{ (string) request('user_id') === (string) $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.common.status') }}</label>
                            <select name="status" class="form-control">
                                <option value="">{{ __('settings.session_logs.all_statuses') }}</option>
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.session_logs.device') }}</label>
                            <select name="device_type" class="form-control">
                                <option value="">{{ __('settings.session_logs.all_devices') }}</option>
                                @foreach ($devices as $key => $label)
                                    <option value="{{ $key }}" {{ request('device_type') === $key ? 'selected' : '' }}>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-12 mb-1">
                            <div class="row">
                                <div class="col-6 pr-0">
                                    <label>{{ __('settings.session_logs.from') }}</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                </div>
                                <div class="col-6">
                                    <label>{{ __('settings.session_logs.to') }}</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> {{ __('settings.common.search') }}
                            </button>
                            <a href="{{ route('SessionLogs.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fa fa-refresh"></i> {{ __('settings.session_logs.reset') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <form method="POST" action="{{ route('SessionLogs.bulk-delete') }}" id="bulkForm"
            onsubmit="return confirm('{{ __('settings.session_logs.confirm_bulk') }}')">
            @csrf

            <div class="card table-card-modern">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="feather icon-list"></i> {{ __('settings.session_logs.list') }}
                        <small class="text-muted">({{ number_format($logs->total()) }})</small>
                    </h4>
                    <button type="submit" class="btn btn-outline-danger btn-sm" id="bulkBtn" disabled>
                        <i class="feather icon-trash-2"></i> {{ __('settings.session_logs.delete_selected') }}
                    </button>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:38px"><input type="checkbox" id="checkAll"></th>
                                    <th>{{ __('settings.session_logs.user') }}</th>
                                    <th>{{ __('settings.session_logs.login_at') }}</th>
                                    <th>{{ __('settings.session_logs.logout_at') }}</th>
                                    <th class="text-center">{{ __('settings.session_logs.duration') }}</th>
                                    <th>{{ __('settings.session_logs.device') }}</th>
                                    <th>{{ __('settings.session_logs.ip') }}</th>
                                    <th class="text-center">{{ __('settings.common.status') }}</th>
                                    <th class="text-center" style="width:70px">{{ __('settings.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($logs as $log)
                                    <tr>
                                        <td><input type="checkbox" name="ids[]" value="{{ $log->id }}" class="row-check"></td>

                                        <td style="min-width:180px">
                                            <span class="sl-actor">
                                                @if ($log->status === 'active')<span class="sl-live"
                                                    title="{{ __('settings.session_logs.statuses.active') }}"></span>@endif
                                                {{ $log->actorName() }}
                                            </span>
                                            @if ($log->user && $log->user->email)
                                                <span class="sl-sub">{{ $log->user->email }}</span>
                                            @endif
                                        </td>

                                        <td style="min-width:150px">
                                            {{ $log->login_at?->format('Y-m-d H:i') ?? '—' }}
                                            <span class="sl-sub">{{ $log->login_at?->diffForHumans() }}</span>
                                        </td>

                                        <td style="min-width:150px">
                                            @if ($log->logout_at)
                                                {{ $log->logout_at->format('Y-m-d H:i') }}
                                                <span class="sl-sub">{{ $log->reasonLabel() }}</span>
                                            @elseif ($log->status === 'active')
                                                <span class="text-muted">{{ __('settings.session_logs.still_open') }}</span>
                                                <span class="sl-sub">{{ __('settings.session_logs.last_seen', [
                                                    'time' => $log->last_seen_at?->diffForHumans() ?? '—']) }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        <td class="text-center">{{ $log->durationForHumans() }}</td>

                                        <td class="sl-device" style="min-width:150px">
                                            <i class="feather icon-{{ match ($log->device_type) {
                                                'mobile' => 'smartphone',
                                                'tablet' => 'tablet',
                                                'bot' => 'terminal',
                                                default => 'monitor',
                                            } }}"></i>
                                            {{ $log->browser ?: '—' }}
                                            <span class="sl-sub">{{ $log->os }}</span>
                                        </td>

                                        <td><span class="sl-ip">{{ $log->ip_address ?: '—' }}</span></td>

                                        <td class="text-center">
                                            <span class="sl-pill {{ $log->statusTone() }}">{{ $log->statusLabel() }}</span>
                                        </td>

                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn bg-gradient-info fa fa-ellipsis-v btn-sm"
                                                    type="button" data-toggle="dropdown" aria-expanded="false"></button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="{{ route('SessionLogs.show', $log->id) }}">
                                                        <i class="feather icon-eye"></i> {{ __('settings.session_logs.details') }}
                                                    </a>

                                                    @if ($log->status === 'active')
                                                        <button type="submit" class="dropdown-item text-warning"
                                                            form="terminate-{{ $log->id }}">
                                                            <i class="feather icon-power"></i> {{ __('settings.session_logs.terminate') }}
                                                        </button>
                                                    @endif

                                                    <div class="dropdown-divider"></div>

                                                    <button type="submit" class="dropdown-item text-danger"
                                                        form="delete-{{ $log->id }}"
                                                        onclick="return confirm('{{ __('settings.session_logs.confirm_delete') }}')">
                                                        <i class="feather icon-trash-2"></i> {{ __('settings.common.delete') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9">
                                            <div class="sl-empty">
                                                <i class="feather icon-inbox"></i>
                                                {{ __('settings.session_logs.empty') }}
                                                <div style="font-size:.8rem;margin-top:.3rem">{{ __('settings.session_logs.empty_hint') }}</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                    @include('sitting::components.table-foot', ['paginator' => $logs])
            </div>
        </form>

        {{-- نماذج الإجراءات خارج نموذج التحديد الجماعي: النماذج المتداخلة
             غير صالحة في HTML، والمتصفّح يتجاهل الداخلي منها بصمت --}}
        @foreach ($logs as $log)
            @if ($log->status === 'active')
                <form id="terminate-{{ $log->id }}" method="POST"
                    action="{{ route('SessionLogs.terminate', $log->id) }}" class="d-none">@csrf</form>
            @endif
            <form id="delete-{{ $log->id }}" method="POST"
                action="{{ route('SessionLogs.destroy', $log->id) }}" class="d-none">@csrf @method('DELETE')</form>
        @endforeach
    </div>
    </div>

    <!-- Cleanup modal -->
    <div class="modal fade" id="cleanupModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form method="POST" action="{{ route('SessionLogs.cleanup') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('settings.session_logs.cleanup') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <label for="days">{{ __('settings.session_logs.cleanup_label') }}</label>
                    <input type="number" name="days" id="days" class="form-control" min="1" max="3650" value="90">
                    <small class="text-muted d-block mt-2" style="font-size:.75rem;line-height:1.7">
                        {{ __('settings.session_logs.cleanup_hint') }}
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('settings.common.cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('settings.session_logs.cleanup_confirm') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var checkAll = document.getElementById('checkAll');
    var boxes = Array.prototype.slice.call(document.querySelectorAll('.row-check'));
    var bulkBtn = document.getElementById('bulkBtn');

    function sync() {
        var checked = boxes.filter(function (b) { return b.checked; }).length;
        if (bulkBtn) bulkBtn.disabled = checked === 0;
        if (checkAll) {
            checkAll.checked = checked > 0 && checked === boxes.length;
            checkAll.indeterminate = checked > 0 && checked < boxes.length;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            boxes.forEach(function (b) { b.checked = checkAll.checked; });
            sync();
        });
    }

    boxes.forEach(function (b) { b.addEventListener('change', sync); });
    sync();
})();
</script>
@endsection
