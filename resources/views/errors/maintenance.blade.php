{{--
    صفحة «النظام في وضع الصيانة» — ما يراه كل من ليس في قائمة المسموح لهم.

    مستقلّة عن قالب النظام كنظيرتها `subscription-required`: القالب يقرأ
    قوائمَ وصلاحياتٍ وإعداداتِ مظهر، ورسمُ لوحةٍ كاملة حول رسالةٍ تقول «لا
    يمكنك الدخول» تناقضٌ ظاهر — والمستخدم عند هذه الصفحة قد أُخرج من جلسته
    أصلاً، فلا قائمةَ له تُرسم.

    وتُقدَّم بالحالة `503`، لا `403`: الخدمة متوقّفةٌ مؤقتاً ولم يُمنع أحدٌ
    لسوء صلاحيته. (انظر `CheckMaintenanceMode::block()`.)

    وزرُّ «تسجيل الدخول» ظاهرٌ عمداً: من كان في القائمة يمرّ منه إلى نظامه
    كاملاً، ومسارُ `login` مستثنىً في الوسيط لهذا السبب.
--}}
@php
    use App\Support\ThemeSettings;

    $palette = ThemeSettings::palette();
    $primary = $palette['--app-primary'] ?? '#7367F0';
    $primaryHover = $palette['--app-primary-hover'] ?? '#5B4FB8';

    $setting = $setting ?? null;
    $message = $setting?->displayMessage() ?? 'النظام في وضع الصيانة حالياً.';
    $since = $setting?->activated_at;
@endphp
    <!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>النظام في وضع الصيانة — {{ config('app.name', 'Fawtra') }}</title>
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
            padding: 24px; color: #4b4b4b; line-height: 1.8;
        }

        .card {
            background: #fff; border-radius: 18px; max-width: 520px; width: 100%;
            padding: 44px 38px; text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .22);
        }

        .icon {
            width: 82px; height: 82px; margin: 0 auto 22px;
            border-radius: 50%; background: #fdf4e0;
            display: flex; align-items: center; justify-content: center;
            font-size: 38px; line-height: 1;
        }

        h1 { font-size: 1.45rem; color: #1b2430; margin-bottom: 12px; font-weight: 700; }
        .msg { font-size: 1rem; color: #55606e; white-space: pre-line; }

        .since {
            margin-top: 18px; font-size: .82rem; color: #8a93a0;
            border-top: 1px solid #eef0f4; padding-top: 14px;
        }

        .actions { margin-top: 26px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }

        .btn {
            display: inline-block; padding: 11px 26px; border-radius: 9px;
            font-size: .92rem; font-weight: 600; text-decoration: none;
            border: 1px solid transparent; transition: opacity .15s;
        }

        .btn:hover { opacity: .88; }
        .btn-main { background: {{ $primary }}; color: #fff; }
        .btn-ghost { background: #f4f6f8; color: #55606e; border-color: #e3e7ec; }

        .hint { margin-top: 18px; font-size: .8rem; color: #99a1ac; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">🔧</div>

    <h1>النظام في وضع الصيانة</h1>

    <p class="msg">{{ $message }}</p>

    @if ($since)
        <p class="since">بدأت الصيانة {{ $since->diffForHumans() }}.</p>
    @endif

    <div class="actions">
        <a href="{{ route('login') }}" class="btn btn-main">تسجيل الدخول</a>
        <a href="{{ url('/') }}" class="btn btn-ghost">إعادة المحاولة</a>
    </div>

    <p class="hint">
        إن كنت من المسؤولين المصرَّح لهم بالعمل أثناء الصيانة، سجّل الدخول بحسابك للمتابعة.
    </p>
</div>
</body>
</html>
