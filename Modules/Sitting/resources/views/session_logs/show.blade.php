@extends('sitting::master')

@section('title', __('settings.session_logs.detail_title'))

@section('css')
    <style>
        .sd { --b:#e3e7ef; --muted:#79809a; --soft:#f7f9fc; --accent: var(--app-primary,#7367F0); --ink:#2f3349; }

        .sd-grid { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(0,1fr); gap:.85rem; align-items:start; }
        @media (max-width:992px) { .sd-grid { grid-template-columns:minmax(0,1fr); } }

        .sd-card { background:#fff; border:1px solid var(--b); border-radius:11px;
            box-shadow:0 1px 3px rgba(20,25,45,.05); margin-bottom:.85rem; overflow:hidden; }
        .sd-card-head { padding:.7rem 1rem; border-bottom:1px solid var(--b); background:var(--soft); }
        .sd-card-head h3 { font-size:.9rem; font-weight:700; margin:0; color:var(--ink);
            display:flex; align-items:center; gap:.45rem; }
        .sd-card-body { padding:1rem; }

        .sd-row { display:flex; justify-content:space-between; gap:1rem; padding:.5rem 0;
            border-bottom:1px dashed var(--b); font-size:.82rem; }
        .sd-row:last-child { border-bottom:0; }
        .sd-row dt { color:var(--muted); font-weight:600; margin:0; flex:0 0 auto; }
        .sd-row dd { margin:0; color:var(--ink); text-align:end; word-break:break-word; }
        .sd-row dd.mono { font-family:ui-monospace,monospace; font-size:.76rem; direction:ltr; }

        .sd-pill { font-size:.72rem; border-radius:20px; padding:.12rem .6rem; border:1px solid var(--b); }
        .sd-pill.ok { background:#eaf8f0; color:#1c6b40; border-color:#c7e9d5; }
        .sd-pill.no { background:#fdeeee; color:#a13333; border-color:#f5cccc; }
        .sd-pill.wait { background:#fff8e6; color:#8a5b00; border-color:#ffe0a3; }
        .sd-pill.neutral { background:#f2f4f8; color:#4a5069; border-color:#e0e4ec; }

        /* الخطّ الزمني للجلسة */
        .sd-time { position:relative; padding-inline-start:1.2rem; }
        .sd-time::before { content:''; position:absolute; inset-inline-start:4px; top:.4rem; bottom:.4rem;
            width:2px; background:var(--b); }
        .sd-step { position:relative; padding:.4rem 0; font-size:.82rem; }
        .sd-step::before { content:''; position:absolute; inset-inline-start:-1.2rem; top:.7rem;
            width:10px; height:10px; border-radius:50%; background:var(--accent); border:2px solid #fff;
            box-shadow:0 0 0 2px var(--b); }
        .sd-step b { display:block; color:var(--ink); }
        .sd-step span { font-size:.74rem; color:var(--muted); }

        .sd-agent { font-family:ui-monospace,monospace; font-size:.71rem; color:#5a6076;
            background:var(--soft); border:1px solid var(--b); border-radius:8px; padding:.5rem;
            direction:ltr; text-align:left; word-break:break-all; line-height:1.6; }

        .sd-related { font-size:.78rem; }
        .sd-related a { display:flex; justify-content:space-between; gap:.5rem; padding:.45rem .2rem;
            border-bottom:1px dashed var(--b); color:#4a5069; text-decoration:none; }
        .sd-related a:last-child { border-bottom:0; }
        .sd-related a:hover { background:var(--soft); }
    </style>
@endsection

@section('content')
<div class="sd" dir="rtl">

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-log-in"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.session_logs.detail_title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SessionLogs.index') }}">{{ __('settings.session_logs.crumb') }}</a></li>
                        <li class="breadcrumb-item active">#{{ $log->id }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('SessionLogs.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather icon-arrow-right"></i> {{ __('settings.session_logs.back') }}
            </a>
            @if ($log->status === 'active')
                <form method="POST" action="{{ route('SessionLogs.terminate', $log->id) }}" class="d-inline"
                    onsubmit="return confirm('{{ __('settings.session_logs.confirm_terminate') }}')">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="feather icon-power"></i> {{ __('settings.session_logs.terminate') }}
                    </button>
                </form>
            @endif

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="sd-grid">
            <div>
                {{-- ==================== الجلسة ==================== --}}
                <div class="sd-card">
                    <div class="sd-card-head">
                        <h3><i class="feather icon-user" style="color:var(--accent)"></i> {{ __('settings.session_logs.session_info') }}</h3>
                    </div>
                    <div class="sd-card-body">
                        <dl class="mb-0">
                            <div class="sd-row">
                                <dt>{{ __('settings.session_logs.user') }}</dt>
                                <dd>{{ $log->actorName() }}</dd>
                            </div>
                            @if ($log->user?->email)
                                <div class="sd-row">
                                    <dt>{{ __('settings.session_logs.email') }}</dt>
                                    <dd class="mono">{{ $log->user->email }}</dd>
                                </div>
                            @elseif ($log->attempted_email)
                                <div class="sd-row">
                                    <dt>{{ __('settings.session_logs.attempted_email') }}</dt>
                                    <dd class="mono">{{ $log->attempted_email }}</dd>
                                </div>
                            @endif
                            <div class="sd-row">
                                <dt>{{ __('settings.common.status') }}</dt>
                                <dd><span class="sd-pill {{ $log->statusTone() }}">{{ $log->statusLabel() }}</span></dd>
                            </div>
                            @if ($log->reasonLabel())
                                <div class="sd-row">
                                    <dt>{{ __('settings.session_logs.reason') }}</dt>
                                    <dd>{{ $log->reasonLabel() }}</dd>
                                </div>
                            @endif
                            <div class="sd-row">
                                <dt>{{ __('settings.session_logs.duration') }}</dt>
                                <dd>{{ $log->durationForHumans() }}</dd>
                            </div>
                            @if ($log->remembered)
                                <div class="sd-row">
                                    <dt>{{ __('settings.session_logs.remembered') }}</dt>
                                    <dd>{{ __('settings.session_logs.remembered_yes') }}</dd>
                                </div>
                            @endif
                            @if ($log->guard)
                                <div class="sd-row">
                                    <dt>{{ __('settings.session_logs.guard') }}</dt>
                                    <dd class="mono">{{ $log->guard }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- ==================== الخطّ الزمني ==================== --}}
                <div class="sd-card">
                    <div class="sd-card-head">
                        <h3><i class="feather icon-clock" style="color:var(--accent)"></i> {{ __('settings.session_logs.timeline') }}</h3>
                    </div>
                    <div class="sd-card-body">
                        <div class="sd-time">
                            <div class="sd-step">
                                <b>{{ $log->status === 'failed' || $log->status === 'locked'
                                    ? __('settings.session_logs.attempt_at')
                                    : __('settings.session_logs.login_at') }}</b>
                                <span>{{ $log->login_at?->format('Y-m-d H:i:s') }} — {{ $log->login_at?->diffForHumans() }}</span>
                            </div>

                            @if ($log->last_seen_at && $log->status === 'active')
                                <div class="sd-step">
                                    <b>{{ __('settings.session_logs.last_activity') }}</b>
                                    <span>{{ $log->last_seen_at->format('Y-m-d H:i:s') }} — {{ $log->last_seen_at->diffForHumans() }}</span>
                                </div>
                            @endif

                            @if ($log->logout_at)
                                <div class="sd-step">
                                    <b>{{ __('settings.session_logs.logout_at') }}</b>
                                    <span>{{ $log->logout_at->format('Y-m-d H:i:s') }} — {{ $log->reasonLabel() }}</span>
                                </div>
                            @elseif ($log->status === 'active')
                                <div class="sd-step">
                                    <b>{{ __('settings.session_logs.still_open') }}</b>
                                    <span>{{ __('settings.session_logs.still_open_hint') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div>
                {{-- ==================== الجهاز ==================== --}}
                <div class="sd-card">
                    <div class="sd-card-head">
                        <h3><i class="feather icon-monitor" style="color:var(--accent)"></i> {{ __('settings.session_logs.device_info') }}</h3>
                    </div>
                    <div class="sd-card-body">
                        <dl class="mb-2">
                            <div class="sd-row">
                                <dt>{{ __('settings.session_logs.browser') }}</dt>
                                <dd>{{ $log->browser ?: '—' }}</dd>
                            </div>
                            <div class="sd-row">
                                <dt>{{ __('settings.session_logs.os') }}</dt>
                                <dd>{{ $log->os ?: '—' }}</dd>
                            </div>
                            <div class="sd-row">
                                <dt>{{ __('settings.session_logs.device') }}</dt>
                                <dd>{{ $log->device_type ? __('settings.session_logs.devices.'.$log->device_type) : '—' }}</dd>
                            </div>
                            <div class="sd-row">
                                <dt>{{ __('settings.session_logs.ip') }}</dt>
                                <dd class="mono">{{ $log->ip_address ?: '—' }}</dd>
                            </div>
                            @if ($log->session_id)
                                <div class="sd-row">
                                    <dt>{{ __('settings.session_logs.session_id') }}</dt>
                                    <dd class="mono">{{ \Illuminate\Support\Str::limit($log->session_id, 16) }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($log->user_agent)
                            <div class="sd-agent">{{ $log->user_agent }}</div>
                        @endif
                    </div>
                </div>

                {{-- ==================== جلسات أخرى ==================== --}}
                <div class="sd-card">
                    <div class="sd-card-head">
                        <h3><i class="feather icon-repeat" style="color:var(--accent)"></i> {{ __('settings.session_logs.related') }}</h3>
                    </div>
                    <div class="sd-card-body">
                        <div class="sd-related">
                            @forelse ($related as $row)
                                <a href="{{ route('SessionLogs.show', $row->id) }}">
                                    <span>{{ $row->login_at?->format('Y-m-d H:i') }}</span>
                                    <span class="sd-pill {{ $row->statusTone() }}">{{ $row->statusLabel() }}</span>
                                </a>
                            @empty
                                <p class="mb-0 text-muted" style="font-size:.8rem">{{ __('settings.session_logs.no_related') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
