{{--
    شاشة الدخول — الصفحة الوحيدة التي تُرى قبل تسجيل الدخول.

    كل ما فيها من ألوان وخطوط وحوافّ وشعار واسمٍ وأيقونة متصفّح يأتي من
    «إعدادات النظام ← مظهر النظام»، لا من قيمٍ مكتوبة هنا:

      • `layouts.partials.theme-styles` يحقن متغيّرات المظهر (‎--app-*‎)
        وخطّ Google المختار وCSS المسؤول الإضافي — وهو نفسه ما تحقنه بقيّة
        شاشات النظام، فالدخول يواكب الهوية دون نسخةٍ ثانية من الإعدادات.
      • أنماط هذه الصفحة تشير إلى تلك المتغيّرات فقط، فتغيير اللون الأساسي
        أو نصف قطر الحواف أو الوضع الليلي ينعكس هنا فوراً.
      • الخلفية من `ThemeSettings::loginBackground()` — لون صفحة الدخول إن
        ضُبط، وإلا اللون الأساسي، وفي الحالتين تدرّجٌ إلى درجة أغمق منه.

    الاتجاه واللغة من `LanguageSettings`، وكل النصوص من `auth.login` — فلا
    اسم نظامٍ ولا نصَّ عربيٍّ مكتوبٌ داخل القالب.
--}}
@php
    use App\Support\ColorTools;
    use App\Support\LanguageSettings;
    use App\Support\ThemeSettings;
    use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

    $theme = ThemeSettings::current();

    $appTitle = ThemeSettings::appTitle('Fawtra');
    $loginLogo = ThemeSettings::loginLogoUrl();
    $favicon = ThemeSettings::faviconUrl() ?: asset('app-assets/images/ico/favicon.ico');

    // اللوحة الجانبية: نفس أساس التدرّج الذي تبنيه ThemeSettings، ليُشتقّ منه
    // لون نصٍّ مقروء فوقه بدل أبيضَ مفروضٍ قد يختفي على خلفية فاتحة.
    $loginBase = filled($theme->login_bg_color) ? $theme->login_bg_color : $theme->valueOr('primary_color');
    $onLogin = ColorTools::readableOn($loginBase);

    $direction = LanguageSettings::direction();
    $locale = app()->getLocale();

    // مبدّل اللغة: القائمة مقيّدة أصلاً باللغات المفعّلة في boot()، فما يظهر
    // هنا هو ما فعّله المسؤول في «إعدادات اللغات» لا ما في ملف الإعداد.
    try {
        $locales = LaravelLocalization::getSupportedLocales();
    } catch (\Throwable $e) {
        $locales = [];
    }

    $features = (array) __('auth.login.features');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $appTitle }} — {{ __('auth.login.page_title') }}</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ $favicon }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/Cairo/stylesheet.css') }}">

    {{-- متغيّرات المظهر والخط المختار وCSS المسؤول — المصدر الوحيد للهوية --}}
    @include('layouts.partials.theme-styles')

    <style>
        /* ============================================================
           صفحة الدخول — كل قيمة لونٍ أو شكلٍ هنا مشتقّة من «مظهر النظام».
           البدائل بعد الفاصلة داخل var() تعمل فقط لو لم تُحقن المتغيّرات.
           ============================================================ */
        :root {
            --lg-login-bg: {!! ThemeSettings::loginBackground() !!};
            --lg-on-brand: {{ $onLogin }};
            --lg-radius: clamp(10px, calc(var(--app-radius, 6px) * 2.4), 26px);
            --lg-field-radius: clamp(8px, calc(var(--app-radius, 6px) * 1.4), 14px);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            font-family: var(--app-font-family, 'Cairo', sans-serif);
            font-size: var(--app-font-size, 14px);
            color: var(--app-text, #4b4b4b);
            background: var(--lg-login-bg);
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(16px, 4vw, 48px);
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- الإطار ---------- */
        .lg-shell {
            width: 100%;
            max-width: 1060px;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            background: var(--app-surface, #fff);
            border-radius: var(--lg-radius);
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(0, 0, 0, .28);
            animation: lg-rise .5s ease-out both;
        }

        /* ---------- اللوحة التعريفية ---------- */
        .lg-brand {
            position: relative;
            isolation: isolate;
            background: var(--lg-login-bg);
            color: var(--lg-on-brand);
            padding: clamp(36px, 5vw, 56px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 28px;
            overflow: hidden;
        }

        /* زخرفة خفيفة تتبع لون النص نفسه — لا لون ثابت مكتوب */
        .lg-brand::before,
        .lg-brand::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: currentColor;
            opacity: .07;
            z-index: -1;
            pointer-events: none;
        }

        .lg-brand::before { width: 320px; height: 320px; inset-block-start: -120px; inset-inline-end: -110px; }
        .lg-brand::after  { width: 220px; height: 220px; inset-block-end: -90px; inset-inline-start: -70px; }

        .lg-brand__head { display: flex; align-items: center; gap: 14px; }

        .lg-brand__mark {
            width: 54px;
            height: 54px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: var(--lg-field-radius);
            background: color-mix(in srgb, currentColor 16%, transparent);
            border: 1px solid color-mix(in srgb, currentColor 26%, transparent);
        }

        .lg-brand__mark svg { width: 28px; height: 28px; }

        .lg-brand__logo { max-height: 64px; max-width: 230px; object-fit: contain; }

        .lg-brand__name {
            font-size: clamp(24px, 3vw, 32px);
            font-weight: var(--app-heading-weight, 700);
            line-height: 1.2;
            margin: 0;
            letter-spacing: -.4px;
        }

        .lg-brand__tagline {
            margin: 4px 0 0;
            font-size: 14px;
            opacity: .82;
        }

        .lg-features { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; }

        .lg-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            opacity: .95;
            animation: lg-slide-in .5s ease-out both;
        }

        .lg-features li:nth-child(1) { animation-delay: .10s; }
        .lg-features li:nth-child(2) { animation-delay: .16s; }
        .lg-features li:nth-child(3) { animation-delay: .22s; }
        .lg-features li:nth-child(4) { animation-delay: .28s; }
        .lg-features li:nth-child(5) { animation-delay: .34s; }

        .lg-features svg {
            width: 26px;
            height: 26px;
            flex: 0 0 auto;
            padding: 5px;
            border-radius: 50%;
            background: color-mix(in srgb, currentColor 18%, transparent);
        }

        /* ---------- لوحة النموذج ---------- */
        .lg-form {
            padding: clamp(32px, 4.5vw, 56px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--app-surface, #fff);
        }

        .lg-form__head { margin-bottom: 26px; }

        .lg-form__head h1 {
            margin: 0 0 6px;
            font-size: clamp(22px, 2.6vw, 28px);
            font-weight: var(--app-heading-weight, 600);
            color: var(--app-text, #333);
        }

        .lg-form__head p { margin: 0; font-size: 14px; color: var(--app-muted, #7b8194); }

        /* ---------- التنبيهات ---------- */
        .lg-alert {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 12px 14px;
            border-radius: var(--lg-field-radius);
            font-size: 13.5px;
            line-height: 1.7;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .lg-alert[hidden] { display: none; }
        .lg-alert svg { width: 18px; height: 18px; flex: 0 0 auto; margin-block-start: 2px; }
        .lg-alert ul { margin: 0; padding-inline-start: 18px; }
        .lg-alert ul li + li { margin-block-start: 4px; }

        .lg-alert--error {
            background: var(--app-danger-soft, rgba(234, 84, 85, .12));
            border-color: color-mix(in srgb, var(--app-danger, #ea5455) 35%, transparent);
            color: var(--app-danger, #ea5455);
        }

        .lg-alert--ok {
            background: var(--app-success-soft, rgba(40, 199, 111, .12));
            border-color: color-mix(in srgb, var(--app-success, #28c76f) 35%, transparent);
            color: var(--app-success, #28c76f);
        }

        .lg-alert--muted {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 28%, transparent);
            color: var(--app-text, #4b4b4b);
        }

        /* ---------- الحقول ---------- */
        .lg-field { margin-bottom: 18px; }

        .lg-field > label {
            display: block;
            margin-bottom: 7px;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--app-text, #333);
        }

        .lg-input { position: relative; display: block; }

        .lg-input__icon {
            position: absolute;
            inset-inline-start: 14px;
            inset-block-start: 50%;
            transform: translateY(-50%);
            width: 17px;
            height: 17px;
            color: var(--app-muted, #7b8194);
            pointer-events: none;
            transition: color .2s ease;
        }

        .lg-input input {
            width: 100%;
            height: var(--app-input-height, 46px);
            padding-inline: 42px 14px;
            border: 1px solid var(--app-border, #e6e9f0);
            border-radius: var(--lg-field-radius);
            background: var(--app-input-bg, #fff);
            color: var(--app-text, #4b4b4b);
            font-family: inherit;
            font-size: 14px;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .lg-input--with-action input { padding-inline-end: 46px; }

        .lg-input input::placeholder { color: var(--app-muted, #a6acbb); opacity: .75; }

        .lg-input input:hover { border-color: color-mix(in srgb, var(--app-primary, #7367f0) 45%, var(--app-border, #e6e9f0)); }

        .lg-input input:focus {
            outline: none;
            border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-glow, rgba(115, 103, 240, .28));
        }

        .lg-input input:focus ~ .lg-input__icon { color: var(--app-primary, #7367f0); }

        .lg-input input[aria-invalid="true"] {
            border-color: var(--app-danger, #ea5455);
            box-shadow: 0 0 0 3px var(--app-danger-soft, rgba(234, 84, 85, .12));
        }

        /* زر إظهار كلمة المرور */
        .lg-reveal {
            position: absolute;
            inset-inline-end: 8px;
            inset-block-start: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border: 0;
            padding: 0;
            cursor: pointer;
            border-radius: 50%;
            background: transparent;
            color: var(--app-muted, #7b8194);
            transition: color .2s ease, background-color .2s ease;
        }

        .lg-reveal:hover { color: var(--app-primary, #7367f0); background: var(--app-primary-soft, rgba(115, 103, 240, .12)); }
        .lg-reveal:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }
        .lg-reveal svg { width: 17px; height: 17px; }
        .lg-reveal .lg-reveal__off { display: none; }
        .lg-reveal[aria-pressed="true"] .lg-reveal__on { display: none; }
        .lg-reveal[aria-pressed="true"] .lg-reveal__off { display: block; }

        .lg-note {
            display: none;
            margin: 7px 0 0;
            font-size: 12.5px;
            line-height: 1.6;
        }

        .lg-note--error { color: var(--app-danger, #ea5455); }
        .lg-note--warn { color: var(--app-warning, #ff9f43); }
        .lg-note.is-shown { display: block; }

        /* ---------- الخيارات ---------- */
        .lg-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .lg-check { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }

        .lg-check input {
            width: 17px;
            height: 17px;
            margin: 0;
            cursor: pointer;
            accent-color: var(--app-primary, #7367f0);
        }

        .lg-check span { font-size: 13.5px; color: var(--app-muted, #7b8194); }

        .lg-linkish {
            border: 0;
            background: none;
            padding: 0;
            font: inherit;
            font-size: 13.5px;
            cursor: pointer;
            color: var(--app-link, var(--app-primary, #7367f0));
            transition: color .2s ease;
        }

        .lg-linkish:hover { color: var(--app-primary-hover, #5b4fb8); text-decoration: underline; }
        .lg-linkish:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 3px; border-radius: 4px; }

        /* ---------- زر الدخول ---------- */
        .lg-submit {
            position: relative;
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            border: 0;
            border-radius: var(--app-button-radius, var(--lg-field-radius));
            background: var(--app-primary, #7367f0);
            color: var(--app-on-primary, #fff);
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .2s ease, transform .15s ease, box-shadow .2s ease;
        }

        .lg-submit svg { width: 18px; height: 18px; }
        .lg-submit:hover { background: var(--app-primary-hover, #5b4fb8); transform: translateY(-1px); box-shadow: 0 10px 24px var(--app-primary-glow, rgba(115, 103, 240, .28)); }
        .lg-submit:active { transform: translateY(0); }
        .lg-submit:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 3px; }
        .lg-submit[disabled] { opacity: .75; cursor: progress; transform: none; box-shadow: none; }

        .lg-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid color-mix(in srgb, currentColor 35%, transparent);
            border-top-color: currentColor;
            border-radius: 50%;
            animation: lg-spin .7s linear infinite;
        }

        /* ---------- التذييل ---------- */
        .lg-secure {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin: 18px 0 0;
            font-size: 12.5px;
            color: var(--app-muted, #7b8194);
        }

        .lg-secure svg { width: 14px; height: 14px; flex: 0 0 auto; }

        .lg-langs {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
            margin-block-start: 18px;
        }

        .lg-langs a {
            padding: 4px 11px;
            border-radius: 999px;
            border: 1px solid var(--app-border, #e6e9f0);
            font-size: 12.5px;
            text-decoration: none;
            color: var(--app-muted, #7b8194);
            transition: border-color .2s ease, color .2s ease, background-color .2s ease;
        }

        .lg-langs a:hover { border-color: var(--app-primary, #7367f0); color: var(--app-primary, #7367f0); }

        .lg-langs a[aria-current="true"] {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 40%, transparent);
            color: var(--app-primary, #7367f0);
            font-weight: 600;
        }

        .lg-foot {
            margin: 22px 0 0;
            padding-block-start: 16px;
            border-block-start: 1px solid var(--app-border, #e6e9f0);
            text-align: center;
            font-size: 12px;
            line-height: 1.9;
            color: var(--app-muted, #7b8194);
        }

        .lg-foot a { color: var(--app-link, var(--app-primary, #7367f0)); text-decoration: none; }
        .lg-foot a:hover { text-decoration: underline; }

        /* ---------- الحركة ---------- */
        @keyframes lg-rise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        @keyframes lg-slide-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @keyframes lg-spin { to { transform: rotate(360deg); } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }

        /* ---------- الاستجابة ---------- */
        @media (max-width: 900px) {
            .lg-shell { grid-template-columns: 1fr; max-width: 520px; }
            .lg-brand { padding: 30px clamp(24px, 6vw, 40px); gap: 0; }
            .lg-brand::before { width: 200px; height: 200px; inset-block-start: -90px; }
            .lg-brand::after { display: none; }
            .lg-features { display: none; }
        }

        @media (max-width: 480px) {
            body { padding: 0; align-items: stretch; }
            .lg-shell { border-radius: 0; max-width: none; min-height: 100vh; box-shadow: none; align-content: start; }
            .lg-form { padding: 28px 20px 36px; }
            /* 16px يمنع تكبير الصفحة تلقائياً عند التركيز في iOS */
            .lg-input input { font-size: 16px; }
        }
    </style>
</head>
<body>
    <main class="lg-shell">

        {{-- ======== اللوحة التعريفية ======== --}}
        <aside class="lg-brand">
            <div class="lg-brand__head">
                @if ($loginLogo)
                    <img src="{{ $loginLogo }}" alt="{{ $appTitle }}" class="lg-brand__logo">
                @else
                    <span class="lg-brand__mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"></path>
                            <path d="m19 9-5 5-4-4-3 3"></path>
                        </svg>
                    </span>
                    <div>
                        <h2 class="lg-brand__name">{{ $appTitle }}</h2>
                        <p class="lg-brand__tagline">{{ __('auth.login.tagline') }}</p>
                    </div>
                @endif
            </div>

            @if ($features)
                <ul class="lg-features">
                    @foreach ($features as $feature)
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>

        {{-- ======== النموذج ======== --}}
        <section class="lg-form">
            <div class="lg-form__head">
                <h1>{{ __('auth.login.welcome') }}</h1>
                <p>{{ __('auth.login.subtitle', ['app' => $appTitle]) }}</p>
            </div>

            @if (session('success'))
                <div class="lg-alert lg-alert--ok" role="status">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="lg-alert lg-alert--error" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    @if ($errors->count() === 1)
                        <span>{{ $errors->first() }}</span>
                    @else
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- لا مسار استعادة كلمة مرور في النظام، فالإرشادة تحلّ محلّ رابطٍ ميت --}}
            <div class="lg-alert lg-alert--muted" id="forgotHint" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span>{{ __('auth.login.forgot_hint') }}</span>
            </div>

            <form action="{{ route('login.check') }}" method="POST" id="loginForm" novalidate>
                @csrf

                <div class="lg-field">
                    <label for="email">{{ __('auth.login.email') }}</label>
                    <div class="lg-input">
                        {{--
                            بلا `dir="ltr"` عمداً: الحشوة والأيقونة كلتاهما
                            منطقيّتان (‎inline-start‎)، فلو خالف اتجاهُ الحقل
                            اتجاهَ الصفحة حُسبت الحشوة من جهةٍ والأيقونة من
                            الأخرى فيمرّ النصُّ تحتها. وقيمةُ البريد لاتينيةٌ
                            كلُّها فيعرضها المتصفّح يسار-يمين على أي حال.
                        --}}
                        <input type="email" id="email" name="email" inputmode="email" autocomplete="username"
                            required spellcheck="false"
                            placeholder="{{ __('auth.login.email_placeholder') }}"
                            value="{{ old('email') }}"
                            aria-describedby="emailError"
                            @if ($errors->has('email')) aria-invalid="true" @endif>
                        <svg class="lg-input__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            <path d="m2 7 10 6 10-6"></path>
                        </svg>
                    </div>
                    <p class="lg-note lg-note--error" id="emailError"></p>
                </div>

                <div class="lg-field">
                    <label for="password">{{ __('auth.login.password') }}</label>
                    <div class="lg-input lg-input--with-action">
                        <input type="password" id="password" name="password" autocomplete="current-password"
                            required placeholder="{{ __('auth.login.password_placeholder') }}"
                            aria-describedby="passwordError capsHint">
                        <svg class="lg-input__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <button type="button" class="lg-reveal" id="revealPassword" aria-pressed="false"
                            aria-controls="password"
                            title="{{ __('auth.login.show_password') }}"
                            aria-label="{{ __('auth.login.show_password') }}"
                            data-show="{{ __('auth.login.show_password') }}"
                            data-hide="{{ __('auth.login.hide_password') }}">
                            <svg class="lg-reveal__on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="lg-reveal__off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72 1.07A9.12 9.12 0 0 1 12 20c-6.5 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="2" y1="2" x2="22" y2="22"></line>
                            </svg>
                        </button>
                    </div>
                    <p class="lg-note lg-note--error" id="passwordError"></p>
                    <p class="lg-note lg-note--warn" id="capsHint">{{ __('auth.login.caps_lock') }}</p>
                </div>

                <div class="lg-options">
                    <label class="lg-check" for="remember">
                        <input type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <span>{{ __('auth.login.remember') }}</span>
                    </label>

                    <button type="button" class="lg-linkish" id="forgotToggle"
                        aria-expanded="false" aria-controls="forgotHint">{{ __('auth.login.forgot') }}</button>
                </div>

                <button type="submit" class="lg-submit" id="submitBtn"
                    data-submitting="{{ __('auth.login.submitting') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <span>{{ __('auth.login.submit') }}</span>
                </button>
            </form>

            <p class="lg-secure">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                </svg>
                <span>{{ __('auth.login.secure') }}</span>
            </p>

            @if (count($locales) > 1)
                <nav class="lg-langs" aria-label="{{ __('auth.login.language') }}">
                    @foreach ($locales as $code => $properties)
                        <a href="{{ LaravelLocalization::getLocalizedURL($code, null, [], true) }}"
                            hreflang="{{ $code }}"
                            @if ($code === $locale) aria-current="true" @endif>{{ $properties['native'] ?? strtoupper($code) }}</a>
                    @endforeach
                </nav>
            @endif

            <p class="lg-foot">
                &copy; {{ now()->year }} {{ $appTitle }} — {{ __('auth.login.rights') }}
                &middot; <a href="https://fawtrasmart.com/" target="_blank" rel="noopener noreferrer">fawtrasmart.com</a>
            </p>
        </section>
    </main>

    <script>
        (function () {
            'use strict';

            var form = document.getElementById('loginForm');
            var email = document.getElementById('email');
            var password = document.getElementById('password');
            var emailError = document.getElementById('emailError');
            var passwordError = document.getElementById('passwordError');
            var capsHint = document.getElementById('capsHint');
            var reveal = document.getElementById('revealPassword');
            var forgotToggle = document.getElementById('forgotToggle');
            var forgotHint = document.getElementById('forgotHint');
            var submitBtn = document.getElementById('submitBtn');

            var messages = {
                email: @json(__('auth.login.email_invalid')),
                password: @json(__('auth.login.password_required'))
            };

            function setNote(input, note, message) {
                if (message) {
                    note.textContent = message;
                    note.classList.add('is-shown');
                    input.setAttribute('aria-invalid', 'true');

                    return false;
                }

                note.textContent = '';
                note.classList.remove('is-shown');
                input.removeAttribute('aria-invalid');

                return true;
            }

            // `force` تعني لحظة الإرسال: الحقل الفارغ خطأ حينها، ولا شيء قبلها
            function validateEmail(force) {
                if (!force && !email.value) {
                    return setNote(email, emailError, '');
                }

                return setNote(email, emailError, email.checkValidity() ? '' : messages.email);
            }

            function validatePassword(force) {
                if (!force && !password.value) {
                    return setNote(password, passwordError, '');
                }

                return setNote(password, passwordError, password.value ? '' : messages.password);
            }

            email.addEventListener('blur', function () { validateEmail(false); });
            email.addEventListener('input', function () {
                if (emailError.classList.contains('is-shown')) validateEmail(false);
            });

            password.addEventListener('blur', function () { validatePassword(false); });
            password.addEventListener('input', function () {
                if (passwordError.classList.contains('is-shown')) validatePassword(false);
            });

            // تنبيه Caps Lock — أشيع سببٍ لرفض كلمة مرور صحيحة
            ['keydown', 'keyup'].forEach(function (type) {
                password.addEventListener(type, function (event) {
                    var on = typeof event.getModifierState === 'function' && event.getModifierState('CapsLock');
                    capsHint.classList.toggle('is-shown', !!on);
                });
            });

            password.addEventListener('blur', function () { capsHint.classList.remove('is-shown'); });

            reveal.addEventListener('click', function () {
                var shown = password.type === 'text';
                password.type = shown ? 'password' : 'text';
                reveal.setAttribute('aria-pressed', shown ? 'false' : 'true');

                var label = shown ? reveal.dataset.show : reveal.dataset.hide;
                reveal.setAttribute('aria-label', label);
                reveal.setAttribute('title', label);
                password.focus();
            });

            forgotToggle.addEventListener('click', function () {
                var open = forgotToggle.getAttribute('aria-expanded') === 'true';
                forgotToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
                forgotHint.hidden = open;
            });

            form.addEventListener('submit', function (event) {
                var validEmail = validateEmail(true);
                var validPassword = validatePassword(true);

                if (!validEmail || !validPassword) {
                    event.preventDefault();
                    (validEmail ? password : email).focus();

                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="lg-spinner" aria-hidden="true"></span><span>'
                    + submitBtn.dataset.submitting + '</span>';
            });

            // المؤشّر يبدأ حيث ينقص الإدخال
            (email.value ? password : email).focus({ preventScroll: true });
        })();
    </script>
</body>
</html>
