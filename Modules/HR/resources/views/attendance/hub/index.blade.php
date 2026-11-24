@extends('hr::master')

@section('title', 'لوحة الحضور')

@section('content')
@include('hr::attendance._partials.styles')
<style>
    #att-hub { --hub-primary: var(--app-primary, #6366f1); --hub-success: var(--app-success, #10b981); --hub-danger: var(--app-danger, #ef4444); --hub-warning: var(--app-warning, #f59e0b); --hub-border: var(--app-border, #e5e7eb); --hub-muted: var(--app-muted, #6b7280); --hub-text: var(--app-text, #1f2937); --hub-surface: var(--app-surface, #fff); }

    #att-hub .hub-card { background: var(--hub-surface); border: 1px solid var(--hub-border); border-radius: 10px; margin-bottom: 1.25rem; }
    #att-hub .hub-card__head { padding: .85rem 1.25rem; border-bottom: 1px solid var(--hub-border); display: flex; align-items: center; gap: .6rem; font-weight: 700; color: var(--hub-text); }
    #att-hub .hub-card__head small { font-weight: 500; color: var(--hub-muted); margin-inline-start: auto; font-size: .8rem; }
    #att-hub .hub-card__body { padding: 1rem 1.25rem 1.15rem; }

    /* أرقام اليوم */
    #att-hub .hub-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .75rem; }
    #att-hub .hub-stat { border: 1px solid var(--hub-border); border-radius: 10px; padding: .8rem 1rem; text-decoration: none; color: inherit; display: block; border-inline-start: 4px solid var(--hub-primary); }
    #att-hub .hub-stat:hover { border-color: var(--hub-primary); }
    #att-hub .hub-stat b { display: block; font-size: 1.5rem; line-height: 1.2; color: var(--hub-text); }
    #att-hub .hub-stat span { font-size: .8rem; color: var(--hub-muted); }
    #att-hub .hub-stat.ok { border-inline-start-color: var(--hub-success); }
    #att-hub .hub-stat.warn { border-inline-start-color: var(--hub-warning); }
    #att-hub .hub-stat.bad { border-inline-start-color: var(--hub-danger); }

    /* خطوات التجهيز — مسارٌ رأسيٌّ واحد */
    #att-hub .hub-steps { list-style: none; margin: 0; padding: 0; max-width: 920px; }
    #att-hub .hub-step { position: relative; display: flex; gap: .85rem; padding-bottom: 1.1rem; }
    #att-hub .hub-step:last-child { padding-bottom: 0; }
    #att-hub .hub-step:not(:last-child)::after { content: ''; position: absolute; top: 2rem; bottom: .2rem; inset-inline-start: calc(1rem - 1px); width: 2px; background: var(--hub-border); }
    #att-hub .hub-step__num { width: 2rem; height: 2rem; border-radius: 50%; flex: none; z-index: 1; display: inline-flex; align-items: center; justify-content: center; font-size: .85rem; font-weight: 700; color: #fff; background: var(--hub-danger); }
    #att-hub .hub-step--ok .hub-step__num { background: var(--hub-success); }
    #att-hub .hub-step--warn .hub-step__num { background: var(--hub-warning); }
    #att-hub .hub-step__main { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; align-items: flex-start; gap: .4rem 1rem; }
    #att-hub .hub-step__text { flex: 1 1 320px; min-width: 0; }
    #att-hub .hub-step__title { display: block; font-size: .92rem; font-weight: 700; color: var(--hub-text); margin-top: .25rem; }
    #att-hub .hub-step__why { margin: .15rem 0 0; font-size: .8rem; color: var(--hub-muted); line-height: 1.7; }
    #att-hub .hub-step__state { display: inline-flex; align-items: center; gap: .3rem; margin-top: .3rem; font-size: .76rem; font-weight: 700; color: var(--hub-danger); }
    #att-hub .hub-step--ok .hub-step__state { color: var(--hub-success); }
    #att-hub .hub-step--warn .hub-step__state { color: var(--hub-warning); }
    #att-hub .hub-step__btn { flex: none; align-self: center; display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; font-weight: 600; text-decoration: none; white-space: nowrap; color: var(--hub-primary); border: 1px solid var(--hub-primary); border-radius: var(--app-button-radius, 6px); padding: .35rem .85rem; }
    #att-hub .hub-step__btn:hover { background: var(--hub-primary); color: #fff; }
    #att-hub .hub-step__admin { flex: none; align-self: center; font-size: .75rem; color: var(--hub-muted); white-space: nowrap; }

    #att-hub .hub-progress { height: 8px; background: var(--hub-border); border-radius: 99px; overflow: hidden; margin-top: .4rem; }
    #att-hub .hub-progress > span { display: block; height: 100%; background: var(--hub-success); }

    #att-hub .hub-links { display: flex; flex-wrap: wrap; gap: .5rem; }
    #att-hub .hub-links a { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid var(--hub-border); border-radius: 8px; padding: .45rem .8rem; font-size: .82rem; font-weight: 600; color: var(--hub-text); text-decoration: none; }
    #att-hub .hub-links a:hover { border-color: var(--hub-primary); color: var(--hub-primary); }
    #att-hub .hub-links .count { background: var(--hub-danger); color: #fff; border-radius: 99px; padding: 0 .45rem; font-size: .72rem; }
</style>

<div id="att-hub" class="content-body">

    <div class="stk-page-head">
        <div class="stk-page-head__titles">
            <span class="stk-page-head__icon" aria-hidden="true"><i class="feather icon-user-check"></i></span>
            <div class="min-width-0">
                <h2 class="stk-page-head__title">الحضور والانصراف</h2>
                <div class="stk-page-head__sub">جهّز النظام مرة واحدة بالترتيب أدناه، ثم تابع حضور موظفيك يومياً من هنا.</div>
            </div>
        </div>
    </div>

    {{-- ═══ اليوم ═══ --}}
    @php $today = now()->toDateString(); @endphp
    <div class="hub-card">
        <div class="hub-card__head">
            <i class="feather icon-calendar"></i> اليوم
            <small>{{ now()->translatedFormat('l j F Y') }}</small>
        </div>
        <div class="hub-card__body">
            <div class="hub-stats">
                @php $logUrl = hasPermission('attendance_days.index') ? route('hr.attendance-log.index', ['date' => $today]) : null; @endphp
                <a class="hub-stat ok" @if ($logUrl) href="{{ $logUrl }}" @endif><b>{{ $todayStats['present'] }}</b><span>حاضر</span></a>
                <a class="hub-stat warn" @if ($logUrl) href="{{ $logUrl }}" @endif><b>{{ $todayStats['late'] }}</b><span>متأخر</span></a>
                <a class="hub-stat bad" @if ($logUrl) href="{{ $logUrl }}" @endif><b>{{ $todayStats['absent'] }}</b><span>غائب</span></a>
                <a class="hub-stat" @if ($logUrl) href="{{ $logUrl }}" @endif><b>{{ $todayStats['leave'] }}</b><span>في إجازة</span></a>
            </div>

            <div class="hub-links mt-3">
                @if (hasPermission('attendance_days.index'))
                    <a href="{{ route('hr.attendance-log.index') }}"><i class="feather icon-list"></i> الحضور اليومي</a>
                @endif
                @if (hasPermission('leave_requests.index'))
                    <a href="{{ route('attendance.leave_requests.index') }}"><i class="feather icon-send"></i> طلبات الإجازة
                        @if ($pending['leaves'] > 0) <span class="count">{{ $pending['leaves'] }}</span> @endif</a>
                @endif
                @if (hasPermission('attendance_corrections.approve'))
                    <a href="{{ route('hr.attendance-correction-approvals.index') }}"><i class="feather icon-check-square"></i> طلبات تصحيح الحضور
                        @if ($pending['corrections'] > 0) <span class="count">{{ $pending['corrections'] }}</span> @endif</a>
                @endif
                @if (hasPermission('attendance_registration.mobile'))
                    <a href="{{ route('hr.field-attendance.index') }}"><i class="feather icon-smartphone"></i> سجّل حضوري</a>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══ التجهيز ═══ --}}
    <div class="hub-card">
        <div class="hub-card__head">
            <i class="feather icon-check-circle"></i> جهّز النظام — مرة واحدة فقط
            <small>{{ $readyCount }} من {{ count($steps) }} جاهزة</small>
        </div>
        <div class="hub-card__body">
            <div class="hub-progress mb-3"><span style="width: {{ count($steps) ? round($readyCount / count($steps) * 100) : 0 }}%"></span></div>

            <ol class="hub-steps">
                @foreach ($steps as $i => $step)
                    @php
                        // التنبيهُ يغلب «تمّ»: خطوةٌ منجَزةٌ بثغرة (قائمة عطلٍ لبعض الموظفين) ليست جاهزة
                        $state = ! empty($step['warn']) ? 'warn' : ($step['done'] ? 'ok' : 'no');
                        $allowed = $step['perm'] === null || hasPermission($step['perm']);
                        $url = \Illuminate\Support\Facades\Route::has($step['route'])
                            ? route($step['route'], $step['routeParam'] ?? [])
                            : null;
                    @endphp
                    <li class="hub-step hub-step--{{ $state }}">
                        <span class="hub-step__num">{{ $i + 1 }}</span>
                        <div class="hub-step__main">
                            <div class="hub-step__text">
                                <span class="hub-step__title">{{ $step['title'] }}</span>
                                <p class="hub-step__why">{{ $step['why'] }}</p>
                                <span class="hub-step__state">
                                    <i class="feather {{ $state === 'ok' ? 'icon-check' : ($state === 'warn' ? 'icon-alert-triangle' : 'icon-x') }}"></i>
                                    {{ $step['status'] }}
                                </span>
                            </div>
                            @if ($allowed && $url)
                                <a class="hub-step__btn" href="{{ $url }}">{{ $step['button'] }} <i class="feather icon-arrow-left"></i></a>
                            @else
                                <span class="hub-step__admin">يقوم بها مدير النظام</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</div>
@endsection
