{{--
    تخطيط معالج التثبيت — مطابقٌ لهوية النظام البصرية.

    **مصدر الألوان `App\Support\ThemeSettings`** لا قيمٌ مكتوبة هنا. الفائدة
    مزدوجة: على تركيبٍ جديد ترجع الطبقة إلى `ColorSitting::DEFAULTS` (بنفسجي
    Vuexy ‎#7367F0 وخط Cairo) فيطابق المعالجُ شاشةَ الدخول تماماً؛ وعلى إعادة
    تثبيتٍ فوق قاعدة قائمة يلتقط المعالجُ الألوانَ التي ضبطها صاحب النظام
    فعلاً. وكل ذلك بلا استعلامٍ محفوف: `ThemeSettings::palette()` محاطة
    بـ try وترجع إلى الافتراضي عند غياب القاعدة — وهو ما جُرِّب هنا.

    **لا يرث قالب النظام** رغم تطابق المظهر: ذاك يفترض جلسةً ومستخدماً
    وجداولَ إعدادات، وكلها غائبة قبل التثبيت.

    **الخط والأيقونات محليّة**: `Cairo` من `public/assets/fonts` كما تفعل
    شاشة الدخول، والأيقونات SVG مضمَّنة لا Font Awesome من CDN — معالجُ تثبيت
    ينهار مظهره بلا إنترنت ليس معالجاً.
--}}
@php
    use App\Support\ColorTools;
    use App\Support\ThemeSettings;

    $palette = ThemeSettings::palette();

    $primary = $palette['--app-primary'] ?? '#7367F0';
    $primaryHover = $palette['--app-primary-hover'] ?? '#5B4FB8';
    $success = $palette['--app-success'] ?? '#28C76F';
    $danger = $palette['--app-danger'] ?? '#EA5455';
    $warning = '#FF9F43'; // لا يضبطه المستخدم في شاشة المظهر، فهو ثابت Vuexy

    // الدرجات الشفافة تُشتقّ هنا لا في CSS: إلحاق شفافية سداسية بـ var()
    // ‏(‎var(--x)1f‎) ليس CSS صحيحاً — يُتجاهل السطر بأكمله فتخرج الشارات بلا خلفية
    $softSuccess = ColorTools::rgba($success, 0.12);
    $softDanger = ColorTools::rgba($danger, 0.12);
    $softWarning = ColorTools::rgba($warning, 0.14);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تثبيت نظام فوترة — @yield('title', 'المعالج')</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('app-assets/images/ico/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/Cairo/stylesheet.css') }}">
    <style>
        {!! \App\Support\ThemeSettings::cssVariables() !!}

        *, *::before, *::after { box-sizing: border-box; }
        * { margin: 0; padding: 0; }

        html, body { height: 100%; }

        body {
            font-family: var(--app-font-family, 'Cairo', sans-serif);
            font-size: 13px;
            /* التدرّج نفسه المستعمل في شاشة الدخول — مشتقّاً من اللون الأساسي */
            background: linear-gradient(135deg, {{ $primary }} 0%, {{ $primaryHover }} 100%);
            padding: 14px 16px;
            color: var(--app-text, #4b4b4b);
            line-height: 1.55;

            /* الشاشة كاملةٌ في إطار واحد: الترويسة والخطوات والأزرار ثابتة،
               وما يفيض من الحقول وحده يتمرّر داخل البطاقة لا الصفحة كلّها */
            display: flex; flex-direction: column; overflow: hidden;
        }

        .shell {
            max-width: 1080px; width: 100%; margin: 0 auto;
            flex: 1; display: flex; flex-direction: column; min-height: 0;
        }

        /* ===== الترويسة: شريطٌ رفيع لا كتلة مركزية ===== */
        .masthead {
            display: flex; align-items: center; gap: 12px;
            color: #fff; margin-bottom: 12px; flex: none;
        }
        .masthead .mark {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 10px; flex: none;
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .28);
        }
        .masthead .mark svg { width: 20px; height: 20px; }
        .masthead h1 { font-size: 17px; font-weight: 700; letter-spacing: -.3px; }
        .masthead p { font-size: 12px; font-weight: 300; opacity: .85; }

        /* ===== شريط الخطوات: أرقامٌ صغيرة في سطر واحد ===== */
        .steps {
            display: flex; gap: 6px; margin-bottom: 10px; flex: none;
            overflow-x: auto; padding-bottom: 2px; scrollbar-width: none;
        }
        .steps::-webkit-scrollbar { display: none; }
        .steps span {
            font-size: 11.5px; padding: 4px 11px; border-radius: 999px; white-space: nowrap;
            background: rgba(255, 255, 255, .16); color: rgba(255, 255, 255, .82);
            border: 1px solid rgba(255, 255, 255, .22);
        }
        .steps span.done { background: rgba(255, 255, 255, .28); color: #fff; }
        .steps span.active { background: #fff; color: {{ $primary }}; font-weight: 600; border-color: #fff; }

        /* ===== البطاقة: نفس زوايا وظلّ شاشة الدخول ===== */
        .card {
            background: var(--app-surface, #fff);
            border-radius: 12px;
            box-shadow: 0 16px 44px rgba(0, 0, 0, .28);
            padding: 18px 20px;
            flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden;

            /* الأزرار تلتصق بأسفل البطاقة، فلا يبحث المستخدم عن «التالي»
               بالتمرير حين يطول النموذج */
            display: flex; flex-direction: column;
        }
        .card h2 { font-size: 17px; font-weight: var(--app-heading-weight, 600); }
        .card .lead { margin: 2px 0 12px; color: var(--app-muted, #7b8194); font-size: 12.5px; }

        h3 {
            font-size: 13.5px; font-weight: 600; margin: 16px 0 9px;
            padding-bottom: 6px; border-bottom: 1px solid var(--app-border, #e6e9f0);
            display: flex; align-items: center; gap: 8px;
        }
        h3:first-of-type { margin-top: 0; }
        h3::before {
            content: ''; width: 4px; height: 14px; border-radius: 2px;
            background: {{ $primary }};
        }

        /* ===== النماذج: ثلاثة أعمدة على الشاشات العريضة ===== */
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px 14px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px 14px; }
        @media (max-width: 900px) { .grid, .grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 620px) { .grid, .grid-3 { grid-template-columns: 1fr; } }
        .field { display: flex; flex-direction: column; gap: 4px; }
        .field.full { grid-column: 1 / -1; }

        label { color: var(--app-text, #333); font-weight: 500; font-size: 12px; }
        label .opt { font-weight: 400; color: var(--app-muted, #7b8194); }

        input[type=text], input[type=email], input[type=password], input[type=number], select {
            width: 100%; padding: 7px 11px; font-size: 13px; font-family: inherit;
            border: 1.5px solid var(--app-border, #e0e0e0);
            border-radius: 7px;
            background: var(--app-surface, #fff);
            color: var(--app-text, #4b4b4b);
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        input:focus, select:focus {
            outline: none;
            border-color: {{ $primary }};
            box-shadow: 0 0 0 4px var(--app-primary-soft, rgba(115, 103, 240, .1));
        }
        .hint { font-size: 11px; color: var(--app-muted, #7b8194); }

        .check {
            display: flex; gap: 9px; align-items: flex-start; padding: 9px 11px;
            border: 1.5px solid var(--app-border, #e0e0e0); border-radius: 7px;
            transition: border-color .2s ease;
        }
        .check:hover { border-color: {{ $primary }}; }
        .check input { width: 16px; height: 16px; margin-top: 2px; cursor: pointer; accent-color: {{ $primary }}; }
        .check div { font-size: 11.5px; color: var(--app-muted, #7b8194); }
        .check strong { display: block; color: var(--app-text, #333); font-weight: 600; font-size: 12.5px; }

        /* ===== الأزرار: ملتصقة بأسفل البطاقة ===== */
        .actions {
            display: flex; gap: 10px; justify-content: space-between; align-items: center;
            margin-top: auto; padding-top: 12px; border-top: 1px solid var(--app-border, #e6e9f0);
            position: sticky; bottom: -18px;
            background: var(--app-surface, #fff);
            padding-bottom: 2px;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 20px; border-radius: 7px; border: none; font-size: 13px;
            font-family: inherit; font-weight: 600; cursor: pointer; text-decoration: none;
            background: linear-gradient(135deg, {{ $primary }} 0%, {{ $primaryHover }} 100%);
            color: var(--app-on-primary, #fff);
            transition: box-shadow .2s ease, transform .2s ease;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px var(--app-primary-glow, rgba(115, 103, 240, .28)); }
        .btn.ghost {
            background: transparent; color: var(--app-muted, #7b8194);
            border: 1.5px solid var(--app-border, #e0e0e0);
        }
        .btn.ghost:hover { border-color: {{ $primary }}; color: {{ $primary }}; transform: none; box-shadow: none; }
        .btn[disabled] { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ===== الجداول والحالات ===== */
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th, td { text-align: right; padding: 6px 8px; border-bottom: 1px solid var(--app-border, #e6e9f0); }
        th { font-size: 11px; color: var(--app-muted, #7b8194); font-weight: 600; }
        tr:last-child td { border-bottom: none; }

        .pill { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 999px; }
        .pill.ok { background: {{ $softSuccess }}; color: {{ $success }}; }
        .pill.bad { background: {{ $softDanger }}; color: {{ $danger }}; }
        .pill.warn { background: {{ $softWarning }}; color: #b5701a; }

        .alert {
            padding: 8px 13px; border-radius: 7px; margin-bottom: 10px;
            font-size: 12.5px; border-right: 3px solid;
        }
        .alert.bad { background: {{ $softDanger }}; border-color: {{ $danger }}; color: {{ ColorTools::darken($danger, 0.18) }}; }
        .alert.ok { background: {{ $softSuccess }}; border-color: {{ $success }}; color: {{ ColorTools::darken($success, 0.22) }}; }
        .alert.warn { background: {{ $softWarning }}; border-color: {{ $warning }}; color: #b5701a; }
        .alert ul { margin: 8px 0 0; padding-right: 18px; }

        code {
            font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
            font-size: .88em; padding: 2px 7px; border-radius: 4px;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: {{ $primary }};
            direction: ltr; display: inline-block;
        }
        pre {
            background: #283046; color: #d0d2d6; padding: 11px; border-radius: 7px;
            overflow: auto; max-height: 190px; font-size: 11.5px; direction: ltr; text-align: left;
            font-family: ui-monospace, Consolas, monospace;
        }
        pre code { background: none; color: inherit; padding: 0; }

        details { margin-top: 10px; }
        summary { cursor: pointer; font-size: 13px; color: {{ $primary }}; font-weight: 500; }

        dl { display: grid; grid-template-columns: 170px 1fr; gap: 5px 14px; font-size: 12.5px; }
        dt { color: var(--app-muted, #7b8194); }
        dd { font-weight: 600; color: var(--app-text, #4b4b4b); }
        @media (max-width: 680px) { dl { grid-template-columns: 1fr; } dd { margin-bottom: 10px; } }

        /* بطاقة عنوان اللوحة — يقرؤها المستخدم ولا يكتبها */
        .fw-endpoint {
            display: flex; align-items: center; gap: 9px; flex-wrap: wrap;
            background: var(--app-primary-soft, rgba(115, 103, 240, .10));
            border-radius: 7px; padding: 7px 12px; margin-bottom: 10px;
            font-size: 12px;
        }
        .fw-endpoint span { color: var(--app-muted, #7b8194); font-weight: 600; }
        .fw-endpoint code {
            background: none; color: {{ $primary }}; font-weight: 600;
            padding: 0; font-size: 13px;
        }

        /* ===== شبكة المراجعة: أربع بطاقات لا خمس كتل متتالية ===== */
        .review-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        @media (max-width: 760px) { .review-grid { grid-template-columns: 1fr; } }
        .rv { border: 1px solid var(--app-border, #e6e9f0); border-radius: 7px; padding: 8px 11px; }
        .rv h4 {
            font-size: 12px; font-weight: 600; color: {{ $primary }};
            margin-bottom: 5px;
        }
        .rv dl { grid-template-columns: 110px 1fr; gap: 3px 10px; font-size: 12px; }

        /* ===== شبكة فحص المتطلبات ===== */
        .req-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; }
        @media (max-width: 900px) { .req-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 620px) { .req-grid { grid-template-columns: 1fr; } }
        .req {
            display: flex; align-items: center; gap: 7px; min-width: 0;
            padding: 5px 9px; border-radius: 6px; font-size: 12px;
            border: 1px solid var(--app-border, #e6e9f0);
        }
        .req.ok .req-mark { color: {{ $success }}; }
        .req.bad { background: {{ $softDanger }}; border-color: {{ $danger }}; }
        .req.bad .req-mark, .req.bad .req-label { color: {{ $danger }}; font-weight: 600; }
        .req-mark { flex: none; }
        .req-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .req-value {
            margin-inline-start: auto; flex: none; font-size: 11px;
            color: var(--app-muted, #7b8194); direction: ltr;
            max-width: 45%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* ===== شاشة التنفيذ الحيّ ===== */
        .run-head { margin-bottom: 8px; }
        .run-bar {
            height: 7px; border-radius: 999px; overflow: hidden;
            background: var(--app-border, #e6e9f0);
        }
        .run-bar-fill {
            height: 100%; width: 0;
            background: linear-gradient(90deg, {{ $primary }} 0%, {{ $primaryHover }} 100%);
            transition: width .35s ease;
        }
        .run-meta {
            display: flex; justify-content: space-between; margin-top: 8px;
            font-size: 13px; color: var(--app-muted, #7b8194);
        }
        #elapsed { font-family: ui-monospace, Consolas, monospace; direction: ltr; }

        .run-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 6px; }
        .run-stats > div {
            text-align: center; padding: 5px; border-radius: 7px;
            border: 1px solid var(--app-border, #e6e9f0);
        }
        .run-stats b { display: block; font-size: 15px; color: {{ $primary }}; }
        .run-stats span { font-size: 11px; color: var(--app-muted, #7b8194); }

        /* السجلّ يملأ ما تبقّى من ارتفاع البطاقة: قائمةٌ من مئات المهام لا
           تمدّ الصفحة، والأزرار تبقى مرئية أسفلها */
        .run-log {
            flex: 1; min-height: 140px; overflow-y: auto; padding: 7px;
            border: 1px solid var(--app-border, #e6e9f0); border-radius: 7px;
            background: var(--app-body-bg, #f8f8f8);
            font-size: 12px; scroll-behavior: smooth;
        }
        .run-line {
            display: grid; grid-template-columns: 20px minmax(0, 1fr) minmax(0, 1.1fr) 52px;
            gap: 8px; align-items: baseline;
            padding: 5px 8px; border-radius: 5px;
        }
        .run-line + .run-line { margin-top: 2px; }
        .run-line.ok .run-icon { color: {{ $success }}; }
        .run-line.bad { background: {{ $softDanger }}; }
        .run-line.bad .run-icon, .run-line.bad .run-title { color: {{ $danger }}; font-weight: 600; }
        .run-line.muted { color: var(--app-muted, #7b8194); grid-template-columns: 1fr; }
        .run-icon { text-align: center; }
        .run-title {
            font-family: ui-monospace, Consolas, monospace; font-size: 12px;
            direction: ltr; text-align: right;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .run-msg {
            color: var(--app-muted, #7b8194);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .run-time {
            font-size: 11px; color: var(--app-muted, #7b8194);
            font-family: ui-monospace, Consolas, monospace; direction: ltr; text-align: left;
        }
        @media (max-width: 680px) {
            .run-line { grid-template-columns: 20px 1fr; }
            .run-msg, .run-time { display: none; }
            .run-stats { grid-template-columns: repeat(2, 1fr); }
        }

        /* ===== اختيار الثيم ===== */
        .theme-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; }
        @media (max-width: 900px) { .theme-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        @media (max-width: 620px) { .theme-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .theme-option {
            position: relative; display: flex; flex-direction: column; gap: 4px;
            padding: 7px; border: 1.5px solid var(--app-border, #e0e0e0); border-radius: 7px;
            cursor: pointer; transition: border-color .2s ease, box-shadow .2s ease;
        }
        .theme-option:hover { border-color: {{ $primary }}; }
        /* :has يتيح إبراز المحدَّد بلا JS — والصنف is-selected احتياطٌ للمتصفحات القديمة */
        .theme-option:has(input:checked), .theme-option.is-selected {
            border-color: {{ $primary }};
            box-shadow: 0 0 0 4px var(--app-primary-soft, rgba(115, 103, 240, .1));
        }
        .theme-option input { position: absolute; opacity: 0; pointer-events: none; }
        .theme-swatch { display: block; height: 22px; border-radius: 5px; }
        .theme-name { font-size: 11.5px; font-weight: 600; color: var(--app-text, #4b4b4b); }
        .theme-code { display: none; }

        /* صندوق الإرشادات — مطويّ افتراضياً: يشرح لمن يسأل ولا يزاحم الحقول */
        .guide {
            background: var(--app-primary-soft, rgba(115, 103, 240, .10));
            border-right: 3px solid {{ $primary }};
            border-radius: 7px; padding: 7px 12px; margin-bottom: 10px;
        }
        .guide > summary {
            font-size: 12.5px; font-weight: 600; color: {{ $primary }};
            display: flex; align-items: center; gap: 6px; cursor: pointer; list-style: none;
        }
        .guide > summary::-webkit-details-marker { display: none; }
        .guide > summary svg { width: 15px; height: 15px; flex: none; }
        .guide > summary::after { content: '▾'; margin-inline-start: auto; font-size: 11px; }
        .guide[open] > summary::after { content: '▴'; }
        .guide ul { margin: 7px 0 0; padding-right: 18px; font-size: 11.5px; color: var(--app-text, #4b4b4b); }
        .guide li { margin-bottom: 3px; }
        .guide li:last-child { margin-bottom: 0; }
        .guide p { font-size: 11.5px; color: var(--app-text, #4b4b4b); margin-top: 7px; }

        .foot { display: none; }
    </style>
</head>
<body>
<div class="shell">
    <header class="masthead">
        <span class="mark">
            {{-- أيقونة مضمَّنة: لا تعتمد على شبكة ولا على حزمة أيقونات --}}
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/>
            </svg>
        </span>
        <span>
            <h1>تثبيت نظام فوترة</h1>
            <p>معالج التهيئة الأولى — يُقفل تلقائياً بعد إتمامه</p>
        </span>
    </header>

    @php
        $flow = [
            'welcome' => 'الترحيب',
            'requirements' => 'المتطلبات',
            'activation' => 'التفعيل',
            'database' => 'قاعدة البيانات',
            'settings' => 'الإعدادات',
            'admin' => 'المدير',
            'review' => 'المراجعة',
            'finished' => 'الإتمام',
        ];
        $currentIndex = array_search($step ?? 'welcome', array_keys($flow), true);
    @endphp

    <nav class="steps">
        @foreach ($flow as $key => $label)
            @php $i = array_search($key, array_keys($flow), true); @endphp
            <span class="{{ $i === $currentIndex ? 'active' : ($i < $currentIndex ? 'done' : '') }}">
                {{ $loop->iteration }}. {{ $label }}
            </span>
        @endforeach
    </nav>

    <div class="card">
        @if (session('error'))
            <div class="alert bad">{{ session('error') }}</div>
        @endif

        @if (session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert bad">
                تحقّق من الحقول التالية:
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    <p class="foot">فوترة — نظام ERP الذكي</p>
</div>
@stack('scripts')
</body>
</html>
