{{--
    شاشة «لا اشتراك» — الجدار الذي يقف عنده كل مسار.

    لا تمتدّ إلى قالب النظام: القالب نفسه يقرأ إعداداتٍ وقوائم وصلاحيات،
    وعرضُ لوحةٍ كاملة حول رسالةٍ تقول «لا تستطيع العمل» تناقضٌ ظاهر. صفحةٌ
    مستقلّة أوضح، وتعمل ولو كانت بقية الشاشات محجوبة.

    والمخارج الثلاثة وحدها ظاهرة: مزامنة (إن كان الاشتراك مربوطاً وتأخّرت
    لقطته)، وفتح لوحة فوترة للتجديد أو الربط، والخروج.
--}}
@php
    use App\Support\ColorTools;
    use App\Support\ThemeSettings;

    $palette = ThemeSettings::palette();
    $primary = $palette['--app-primary'] ?? '#7367F0';
    $primaryHover = $palette['--app-primary-hover'] ?? '#5B4FB8';
    $danger = $palette['--app-danger'] ?? '#EA5455';
@endphp
    <!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>الاشتراك مطلوب — {{ config('app.name', 'Fawtra') }}</title>
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
            background: #fff; border-radius: 14px; max-width: 620px; width: 100%;
            padding: 34px; box-shadow: 0 20px 60px rgba(0, 0, 0, .3); text-align: center;
        }

        .mark {
            width: 68px; height: 68px; border-radius: 18px; margin: 0 auto 18px;
            display: flex; align-items: center; justify-content: center;
            background: {{ ColorTools::rgba($danger, 0.12) }};
        }
        .mark svg { width: 32px; height: 32px; stroke: {{ $danger }}; }

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
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
    </span>

    <h1>النظام مقفل — الاشتراك مطلوب</h1>
    <p class="reason">{{ $reason }}</p>

    @if ($record)
        <div class="facts">
            <div><span>الباقة</span> <b>{{ $record->plan_name ?: '—' }}</b></div>
            <div><span>الحالة</span> <b>{{ $record->statusLabel() }}</b></div>
            <div><span>تاريخ الانتهاء</span> <b>{{ $record->end_date?->format('Y-m-d') ?: '—' }}</b></div>
            <div><span>آخر مزامنة</span> <b>{{ $record->last_synced_at?->diffForHumans() ?? 'لم تتم' }}</b></div>
        </div>
    @endif

    <div class="actions">
        @auth
            <form method="POST" action="{{ route('Fawtra.sync') }}">
                @csrf
                <button class="btn" type="submit">تحقّق من الاشتراك الآن</button>
            </form>
        @endauth

        <a class="btn" href="{{ $dashboardUrl }}" target="_blank" rel="noopener">افتح لوحة فوترة</a>

        <a class="btn ghost" href="{{ route('Fawtra.index') }}">شاشة الاشتراك</a>

        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn ghost" type="submit">تسجيل الخروج</button>
            </form>
        @endauth
    </div>

    <p class="code">{{ $code }}</p>
</div>
</body>
</html>
