@extends('sitting::master')

@section('title', __('settings.layout.settings_panel'))

@section('content')
    @include('sitting::components._account_ui')

    @php
        /**
         * بطاقات الصحّة تربط بشاشاتها — تشخيص بلا طريق إلى العلاج ناقص.
         * الرابط يُؤخذ من القائمة المفلترة بالصلاحيات: ما لا يراه المستخدم
         * يبقى بطاقةً بلا رابط بدل أن يقوده إلى 403.
         */
        $urlOf = function (string $route) use ($groups) {
            foreach ($groups as $group) {
                foreach ($group['items'] as $item) {
                    if (($item['route'] ?? null) === $route) {
                        return $item['url'];
                    }
                }
            }
            return null;
        };

        $modulesUrl = $urlOf('Application.index');
        $cronUrl = $urlOf('CronJobs.index');
        $backupUrl = $urlOf('Backup.index');

        $schedulerTone = match ($scheduler['status']) {
            'healthy' => 'success',
            'warning' => 'warning',
            'failed' => 'danger',
            default => 'muted',
        };

        $backupTone = $backupAgeHours === null ? 'danger' : ($backupAgeHours > 48 ? 'warning' : 'success');
    @endphp

    <style>
        /* ══ بطاقات الصحّة ══ */
        a.ax-stat { color: inherit; text-decoration: none; }
        .ax-stat { border-radius: 14px; }
        .ax-stat.link { transition: border-color .18s, box-shadow .18s, transform .18s; }
        .ax-stat.link:hover {
            border-color: var(--ax-brand-line); color: inherit; text-decoration: none; transform: translateY(-2px);
            box-shadow: 0 10px 26px -8px rgba(47,44,64,.22), 0 2px 6px rgba(47,44,64,.06);
        }
        .ax-stat.link:focus-visible { outline: 2px solid var(--ax-brand); outline-offset: 3px; }
        .ax-stat .ic.muted { background: #f1f0f6; color: var(--ax-ink-2); }
        .ax-stat .go {
            margin-inline-start: auto; color: var(--ax-ink-3); font-size: .95rem;
            opacity: 0; transform: translateX(-4px); transition: opacity .18s, transform .18s;
        }
        [dir="ltr"] .ax-stat .go { transform: translateX(4px) rotate(180deg); }
        .ax-stat.link:hover .go { opacity: 1; transform: none; }
        [dir="ltr"] .ax-stat.link:hover .go { opacity: 1; transform: rotate(180deg); }
        .ax-stat .val.sm { font-size: 1.05rem; }

        @media (prefers-reduced-motion: reduce) {
            .ax-stat.link { transition: none; }
        }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-settings"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.dashboard.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.common.settings') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- بطاقة الاشتراك أولاً: أول ما يُسأل عنه، وأول ما يُنسى --}}
        @if ($subscription)
            @php $days = $subscription->daysRemaining(); @endphp
            <div class="ax-note {{ $subscription->urgency() === 'ok' ? 'brand' : ($subscription->urgency() === 'warn' ? 'warn' : 'dang') }}">
                <i class="feather icon-award mt-1"></i>
                <div style="flex:1">
                    <strong>
                        {{ $subscription->plan_name }} —
                        @if ($subscription->isPerpetual())
                            {{ __('settings.subscription.perpetual') }}
                        @elseif ($days < 0)
                            {{ __('settings.account.expired_since', ['days' => abs($days)]) }}
                        @else
                            {{ __('settings.account.days_left', ['days' => $days]) }}
                        @endif
                    </strong>
                    <p>
                        {{ __('settings.subscription.from') }} {{ $subscription->started_at?->format('Y-m-d') }}
                        {{ __('settings.subscription.to') }} {{ $subscription->expires_at?->format('Y-m-d') ?? '—' }}
                        · <a href="{{ route('Subscription.index') }}" style="color:inherit;text-decoration:underline">{{ __('settings.subscription.title') }}</a>
                    </p>
                </div>
            </div>
        @endif

        <div class="ax-stats">
            <div class="ax-stat">
                <div class="ic primary"><i class="feather icon-sliders"></i></div>
                <div>
                    <div class="val">{{ $screenCount }}</div>
                    <div class="lbl">{{ __('settings.dashboard.screens_available') }}</div>
                </div>
            </div>

            {{-- وسم <a> بلا href ليس رابطاً ولا يُركَّز عليه: تبقى البطاقة
                 بطاقةً لمن لا يملك صلاحية الشاشة، بلا تكرار للقالب --}}
            <a class="ax-stat {{ $modulesUrl ? 'link' : '' }}" @if ($modulesUrl) href="{{ $modulesUrl }}" @endif>
                <div class="ic success"><i class="feather icon-grid"></i></div>
                <div>
                    <div class="val">{{ $activeModules }}</div>
                    <div class="lbl">{{ __('settings.dashboard.modules_active', ['total' => $totalModules]) }}</div>
                </div>
                @if ($modulesUrl)<i class="feather icon-chevron-left go"></i>@endif
            </a>

            <a class="ax-stat {{ $cronUrl ? 'link' : '' }}" @if ($cronUrl) href="{{ $cronUrl }}" @endif
                title="{{ $scheduler['message'] }}">
                <div class="ic {{ $schedulerTone }}"><i class="feather icon-clock"></i></div>
                <div>
                    <div class="val sm">{{ $scheduler['label'] }}</div>
                    <div class="lbl">{{ __('settings.dashboard.scheduler_status') }}</div>
                </div>
                @if ($cronUrl)<i class="feather icon-chevron-left go"></i>@endif
            </a>

            <a class="ax-stat {{ $backupUrl ? 'link' : '' }}" @if ($backupUrl) href="{{ $backupUrl }}" @endif>
                <div class="ic {{ $backupTone }}"><i class="feather icon-database"></i></div>
                <div>
                    <div class="val sm">
                        @if ($backupAgeHours === null)
                            {{ __('settings.dashboard.backup_none') }}
                        @elseif ($backupAgeHours < 1)
                            {{ __('settings.dashboard.backup_recent') }}
                        @else
                            {{ __('settings.dashboard.backup_hours', ['hours' => $backupAgeHours]) }}
                        @endif
                    </div>
                    <div class="lbl">{{ __('settings.dashboard.last_backup') }}</div>
                </div>
                @if ($backupUrl)<i class="feather icon-chevron-left go"></i>@endif
            </a>
        </div>

        {{-- شريط البحث وبطاقات الشاشات — مشترك مع لوحة إعدادات المبيعات --}}
        @include('sitting::components._settings_hub', ['groups' => $groups])
    </div>
@endsection
