{{--
    شاشة «ميزةٌ خارج الباقة».

    **لا تُخلط بـ`subscription-required`**: تلك تعني أن النظام كلّه مقفل —
    لا اشتراك أو انتهى — فتُعرض بلا قالبٍ ولا قائمة لأن لا شيء يعمل. وهذه
    تعني أن الاشتراك نشط ويعمل، وأن هذه الشاشة وحدها لم تُشترَ. فالمخرج
    منها العودةُ إلى العمل لا الخروجُ من النظام.

    ولهذا: زرّ «رجوع» أولاً، وترقيةُ الباقة ثانياً — ولا زرّ خروج إطلاقاً.
--}}
@php
    use App\Support\ColorTools;
    use App\Support\ThemeSettings;

    $palette = ThemeSettings::palette();
    $primary = $palette['--app-primary'] ?? '#7367F0';
    $primaryHover = $palette['--app-primary-hover'] ?? '#5B4FB8';
    $warning = $palette['--app-warning'] ?? '#FF9F43';

    $record = \App\Models\FawtraSubscription::current();
    $upgradeUrl = $record?->link('upgrade')
        ?: ($record?->link('subscription') ?: rtrim((string) config('fawtra.base_url'), '/'));
@endphp
    <!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ميزة خارج الباقة — {{ config('app.name', 'Fawtra') }}</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('app-assets/images/ico/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/Cairo/stylesheet.css') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        * { margin: 0; padding: 0; }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, {{ $primary }} 0%, {{ $primaryHover }} 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px; color: #4b4b4b; line-height: 1.7;
        }

        .card {
            background: #fff; border-radius: 14px; max-width: 600px; width: 100%;
            padding: 34px; box-shadow: 0 20px 60px rgba(0, 0, 0, .3); text-align: center;
        }

        .mark {
            width: 68px; height: 68px; border-radius: 18px; margin: 0 auto 18px;
            display: flex; align-items: center; justify-content: center;
            background: {{ ColorTools::rgba($warning, 0.14) }};
        }
        .mark svg { width: 32px; height: 32px; stroke: {{ $warning }}; fill: none; }

        h1 { font-size: 21px; font-weight: 700; margin-bottom: 10px; }
        .reason { font-size: 14.5px; color: #5c6470; margin-bottom: 22px; }

        .facts {
            text-align: right; background: #f8f9fb; border-radius: 10px;
            padding: 14px 18px; margin-bottom: 22px; font-size: 13px;
        }
        .facts div { display: flex; justify-content: space-between; padding: 3px 0; }
        .facts b { font-weight: 600; }

        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 7px; text-decoration: none;
            padding: 10px 22px; border-radius: 8px; border: none; cursor: pointer;
            font-family: inherit; font-size: 14px; font-weight: 600;
            background: linear-gradient(135deg, {{ $primary }} 0%, {{ $primaryHover }} 100%);
            color: #fff;
        }
        .btn.ghost { background: transparent; color: #7b8194; border: 2px solid #e0e0e0; }
        .btn:hover { opacity: .92; }

        .code { margin-top: 18px; font-size: 11.5px; color: #a5adb9; direction: ltr; }
    </style>
</head>
<body>
<div class="card">
    <span class="mark">
        <svg viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2 3 7v6c0 5 3.8 8.6 9 9.9 5.2-1.3 9-4.9 9-9.9V7z"/>
            <path d="M12 8v4"/><path d="M12 16h.01"/>
        </svg>
    </span>

    <h1>هذه الميزة خارج باقتك الحالية</h1>
    <p class="reason">{{ $message }}</p>

    <div class="facts">
        <div><span>الباقة الحالية</span> <b>{{ $record?->plan_name ?: '—' }}</b></div>
        <div><span>الميزة المطلوبة</span> <b>{{ implode('، ', (array) $features) }}</b></div>
    </div>

    <div class="actions">
        <a class="btn ghost" href="javascript:history.back()">رجوع</a>

        <a class="btn" href="{{ $upgradeUrl }}" target="_blank" rel="noopener">ترقية الباقة</a>

        <a class="btn ghost" href="{{ route('Fawtra.index') }}">شاشة الاشتراك</a>
    </div>

    <p class="code">PLAN_FEATURE_UNAVAILABLE</p>
</div>
</body>
</html>
