{{--
    قالب بوابة العميل.

    ══════════════════ ما استُبدل ══════════════════

    كانت شاشات البوابة تمتدّ من `resources/views/client.blade.php`: قالب
    النظام الإداري كاملاً — `sidebar_client` و`header_client`، وjQuery
    مُحمَّلاً **ستَّ مرّات** وDataTables **ثلاثاً**، وTinyMCE وخرائط Google
    وApexCharts وSelectize وSpectrum. كلُّ ذلك ليرى زبونٌ أربع فواتير.

    ══════════════════ التنقّل ══════════════════

    شريطٌ جانبيّ كحليّ (طلب المستخدم 2026-09-19 بتصميمٍ مرفق)، بنودُه من
    `ClientPortalPermissions::navItems()` — أي من المفاتيح المفعَّلة في
    «صلاحيات بوابة العميل» وحدها. فلا يظهر بندٌ لصفحةٍ سيُردّ عنها صاحبُه،
    ولا تُكتب هنا قائمةٌ ثانية تفترق عن الإعدادات.

    والأيقونةُ تُترجَم من اسمها في السجلّ إلى اسمٍ في Font Awesome عبر
    خريطةٍ واحدة أدناه: السجلُّ يسمّي المعنى (`file-text`)، والقالبُ يعرف
    مكتبتَه. وكانت الترجمةُ ثلاثيّاتٍ متداخلةً في سطرٍ واحد، فأيُّ مفتاحٍ
    جديدٍ يظهر بأيقونة تقويم مهما كان معناه.

    ══════════════════ الأقسام ══════════════════

      title    عنوان الصفحة (إلزامي)
      subtitle سطرٌ تحت العنوان (اختياري)
      actions  أزرارٌ في جهة العنوان (اختياري)
      content  جسم الصفحة
      styles   تنسيقاتٌ خاصّة بالشاشة (اختياري)
      scripts  سكربتات الصفحة (اختياري)
      hero     بطاقةُ ترحيبٍ تسبق العنوان (اختياري — الرئيسية وحدها)
--}}
@php
    use App\Support\ClientPortalPermissions;

    $navItems = ClientPortalPermissions::navItems();
    $currentRoute = \Illuminate\Support\Facades\Route::currentRouteName();

    $portalClient = $portalClient ?? null;

    $clientName = $portalClient->trade_name
        ?? trim((string) (auth()->user()->name ?? ''))
        ?: 'حسابي';

    // الحرف الأول للأفاتار — `mb_substr` لا `[0]`: الحرف العربي بايتان،
    // فالقصّ بالبايت يطبع نصف حرفٍ مشوَّهاً
    $initial = mb_substr(trim($clientName), 0, 1, 'UTF-8') ?: '؟';

    $isRtl = $languageIsRtl ?? true;

    // خطُّ «مظهر النظام» إن اختاره المسؤول — وإلّا يبقى الخطّ الافتراضي
    $themeFont = \App\Support\ThemeSettings::fontUrl();

    /** اسمُ المعنى في السجلّ ← اسمُ الأيقونة في Font Awesome */
    $faIcons = [
        'home' => 'home',
        'user' => 'user',
        'file-text' => 'file-invoice',
        'book-open' => 'book-open',
        'tag' => 'tags',
        'calendar' => 'calendar-alt',
        'bookmark' => 'calendar-check',
        'wallet' => 'wallet',
        'undo' => 'undo',
        'map-marker-alt' => 'map-marker-alt',
        'award' => 'award',
    ];

    /*
     * كحليُّ البوابة — من «مظهر النظام»، ويُغمَّق إن كان فاتحاً.
     *
     * التصميمُ المطلوب (2026-09-19) شريطٌ جانبيٌّ داكنٌ وبطاقةُ ترحيبٍ داكنة.
     * ولونُ شريط النظام في كثيرٍ من المنشآت **أبيض**، فلو أخذناه كما هو خرجت
     * البوابةُ بيضاءَ على بيضاء ولا شيءَ من التصميم. فالقاعدة: إن كان لونُ
     * الشريط داكناً فهو لونُنا (تتبعُ البوابةُ المظهرَ حرفياً)، وإلّا اشتققنا
     * داكناً من **اللون الأساسي للمنشأة** — فيبقى الشكلُ كالتصميم واللونُ
     * لونَ المنشأة لا لوناً مكتوباً في القالب.
     */
    $themePalette = \App\Support\ThemeSettings::palette();
    $themeSidebar = $themePalette['--app-sidebar-bg'] ?? null;

    $portalNavBg = ($themeSidebar && \App\Support\ColorTools::isDark($themeSidebar))
        ? $themeSidebar
        : \App\Support\ColorTools::darken($themePalette['--app-primary'] ?? '#12315b', 0.62);

    $portalNavText = \App\Support\ColorTools::readableOn($portalNavBg);

    /** اللغاتُ المفعَّلة — مصدرُها إعداداتُ اللغات لا قائمةٌ في القالب */
    $portalLocales = collect(\Mcamara\LaravelLocalization\Facades\LaravelLocalization::getSupportedLocales())
        ->map(fn ($locale) => $locale['native'] ?? $locale['name'] ?? '')
        ->filter()
        ->all();

    // إشعاراتُ العميل غيرُ المقروءة — رقمٌ في الجرس، وهي نفسُها التي
    // تُعرَض تنبيهاتٍ داخل الصفحة
    $unreadCount = auth()->check()
        ? \App\Models\notifications::where('receiver_id', auth()->id())->where('read', false)->count()
        : 0;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة العميل')</title>

    {{-- خطٌّ واحد وأيقونات واحدة. لا jQuery ولا DataTables ولا محرّر نصوص:
         البوابة صفحاتُ عرضٍ ونماذجُ قليلة، وكلُّ مكتبةٍ تُحمَّل هنا يدفع
         ثمنَها زبونٌ على شبكة هاتف --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    @if ($themeFont)
        <link href="{{ $themeFont }}" rel="stylesheet">
    @endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    {{-- ══════════ «مظهر النظام» يصل البوابة ══════════

         متغيّرات `--app-*` من `ThemeSettings` (اللون الأساسي ولون الشريط
         الجانبي والسطوح والخطّ والحواف). نظامُ البوابة `cp-*` مبنيٌّ عليها
         أصلاً، فتغييرُ لون المنشأة من الإعدادات يظهر هنا كما يظهر في شاشات
         الموظفين — ولا تبقى البوابةُ جزيرةً بلونٍ آخر.

         المتغيّراتُ وحدَها لا `ThemeSettings::css()`: تلك قواعدُ شاشات
         الإدارة بـ`!important` (شريطٌ جانبيّ بعرضٍ ثابت، وجداولُ القالب)
         وتُخرّب تخطيطَ البوابة. --}}
    <style>
        {!! \App\Support\ThemeSettings::cssVariables() !!}

        /* كحليُّ الشريط وبطاقةِ الترحيب — انظر التعليق في رأس القالب */
        :root {
            --cp-nav-bg: {{ $portalNavBg }};
            --cp-nav-text: {{ $portalNavText }};
        }
    </style>

    <style>
        * { margin: 0; padding: 0; }
        body {
            font-family: var(--app-font-family, 'Cairo', system-ui, sans-serif);
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }
    </style>

    @include('client::portal._theme')

    @yield('styles')
</head>

<body class="cp" id="cpBody">

<div class="cp__wrap">

    {{-- ══════════ الشريط الجانبي ══════════ --}}
    <aside class="cp-side cp-noprint" id="cpSide">
        <div class="cp-side__brand">
            <span class="cp-side__logo"><i class="fa fa-file-invoice-dollar"></i></span>
            <div style="min-width:0">
                <p class="cp-side__name">فوترة</p>
                <p class="cp-side__tag">بوابة العميل</p>
            </div>
        </div>

        <nav class="cp-side__nav" aria-label="أقسام البوابة">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="cp-side__item {{ $currentRoute === $item['route'] ? 'is-active' : '' }}"
                   @if ($currentRoute === $item['route']) aria-current="page" @endif>
                    <i class="fa fa-{{ $faIcons[$item['icon']] ?? 'circle' }}"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="cp-side__foot">
            <form action="{{ route('logout') }}" method="POST" style="margin:0">
                @csrf
                <button type="submit" class="cp-side__out">
                    <i class="fa fa-sign-out-alt"></i> تسجيل الخروج
                </button>
            </form>
        </div>
    </aside>

    <div class="cp__main">

        {{-- ══════════ الشريط العلوي ══════════ --}}
        <header class="cp-top cp-noprint">
            <div class="cp-top__inner">
                <button type="button" class="cp-burger" id="cpBurger" aria-label="القائمة">
                    <i class="fa fa-bars"></i>
                </button>

                {{-- بحثٌ داخل الصفحة لا في الخادم: البوابةُ صفحاتُ جداولَ
                     قصيرة، وطلبُ بحثٍ للخادم على أربع فواتير انتظارٌ بلا مقابل --}}
                <div class="cp-search">
                    <i class="fa fa-search"></i>
                    <input type="search" id="cpFind" placeholder="ابحث في هذه الصفحة…" autocomplete="off">
                </div>

                <div class="cp-top__tools">
                    @if (\Illuminate\Support\Facades\Route::has('clients.personal'))
                        <a href="{{ route('clients.personal') }}" class="cp-iconbtn" title="التنبيهات">
                            <i class="fa fa-bell"></i>
                            @if ($unreadCount > 0)
                                <span class="cp-iconbtn__dot">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                            @endif
                        </a>
                    @endif

                    {{-- مبدّلُ اللغة من اللغات المفعَّلة في «اللغات» — لا قائمةٌ
                         مكتوبةٌ هنا: تفعيلُ لغةٍ في الإعدادات يُظهرها في البوابة.
                         ولا يظهر أصلاً إن كانت لغةً واحدة. --}}
                    @if (count($portalLocales) > 1)
                        <div class="cp-lang">
                            <button type="button" class="cp-iconbtn" id="cpLangBtn" title="اللغة">
                                <i class="fa fa-globe"></i>
                            </button>
                            <div class="cp-lang__menu" id="cpLangMenu" hidden>
                                @foreach ($portalLocales as $code => $label)
                                    <a href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL($code) }}"
                                       class="cp-lang__item {{ app()->getLocale() === $code ? 'is-active' : '' }}">
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="cp-who">
                        <span class="cp-who__avatar" aria-hidden="true">{{ $initial }}</span>
                        <div style="min-width:0">
                            <p class="cp-who__hi">مرحباً بك</p>
                            <p class="cp-who__name">{{ auth()->user()->name ?: $clientName }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="cp__shell">

            {{-- شريط «أنت تعاين بوابة عميل» — في القالب لا في كل شاشة --}}
            @include('partials.portal-preview-banner')

            @yield('hero')

            @if (session('success'))
                <div class="cp-alert cp-alert--success cp-noprint">
                    <i class="fa fa-check-circle"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if (session('error'))
                <div class="cp-alert cp-alert--danger cp-noprint">
                    <i class="fa fa-exclamation-circle"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            {{-- مواعيدُ شاركها الموظفون مع العميل ولم يطّلع عليها — تُعلَّم مقروءةً
                 حين يفتح «مواعيدي» (`ClientSettingController::appointments_client`).
                 بلا تبويب المواعيد لا شريط: لا رابطَ يؤدّي إلى ما يُخفيه. --}}
            @php
                $sharedAppointmentNotices = auth()->check()
                    && \Illuminate\Support\Facades\Route::has('clients.appointments_client')
                    && collect($navItems)->contains('route', 'clients.appointments_client')
                    ? \App\Models\notifications::where('receiver_id', auth()->id())
                        ->where('type', 'client_appointment_shared')
                        ->where('read', false)
                        ->latest()
                        ->limit(3)
                        ->get(['id', 'title', 'description'])
                    : collect();
            @endphp
            @if ($sharedAppointmentNotices->isNotEmpty() && $currentRoute !== 'clients.appointments_client')
                <div class="cp-alert cp-alert--info cp-noprint">
                    <i class="fa fa-calendar-check"></i>
                    <div>
                        @foreach ($sharedAppointmentNotices as $notice)
                            <div>{{ $notice->description ?: $notice->title }}</div>
                        @endforeach
                        <a href="{{ route('clients.appointments_client') }}">عرض مواعيدي</a>
                    </div>
                </div>
            @endif

            {{-- `isset` قبل `any()`: `$errors` يشاركه `ShareErrorsFromSession`
                 في مجموعة `web` وحدها، فأيُّ رندرٍ خارج طلبٍ كامل (تصدير،
                 معاينة، طابور) يسقط على `any()` على `null` --}}
            @if (isset($errors) && $errors->any())
                <div class="cp-alert cp-alert--danger cp-noprint">
                    <i class="fa fa-exclamation-triangle"></i>
                    <div>
                        <strong>تعذّر الحفظ:</strong>
                        <ul style="margin:.3rem 0 0;padding-inline-start:1.1rem">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="cp-head">
                <div style="display:flex;align-items:flex-end;gap:1rem;flex-wrap:wrap">
                    <div style="flex:1 1 auto;min-width:0">
                        <h1 class="cp-head__title">@yield('title', 'بوابة العميل')</h1>
                        @hasSection('subtitle')
                            <p class="cp-head__sub">@yield('subtitle')</p>
                        @endif
                    </div>

                    @hasSection('actions')
                        <div class="cp-noprint" style="display:flex;gap:.5rem;flex-wrap:wrap">
                            @yield('actions')
                        </div>
                    @endif
                </div>
            </div>

            @yield('content')
        </div>
    </div>
</div>

<script>
    /* فتحُ الشريط الجانبي على الهاتف، وبحثٌ يُخفي صفوفَ الجداول التي لا
       تطابق. كلاهما بلا مكتبة: البوابة بلا jQuery عمداً. */
    (function () {
        var body = document.getElementById('cpBody');
        var burger = document.getElementById('cpBurger');

        if (burger) {
            burger.addEventListener('click', function () {
                body.classList.toggle('is-nav-open');
            });

            document.addEventListener('click', function (event) {
                if (!body.classList.contains('is-nav-open')) { return; }
                if (event.target.closest('#cpSide') || event.target.closest('#cpBurger')) { return; }
                body.classList.remove('is-nav-open');
            });
        }

        var langBtn = document.getElementById('cpLangBtn');
        var langMenu = document.getElementById('cpLangMenu');

        if (langBtn && langMenu) {
            langBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                langMenu.hidden = !langMenu.hidden;
            });

            document.addEventListener('click', function (event) {
                if (!langMenu.hidden && !event.target.closest('.cp-lang')) {
                    langMenu.hidden = true;
                }
            });
        }

        var find = document.getElementById('cpFind');

        if (find) {
            find.addEventListener('input', function () {
                var needle = find.value.trim().toLowerCase();

                document.querySelectorAll('.cp-table tbody, .cp-list').forEach(function (holder) {
                    var rows = holder.querySelectorAll(':scope > tr, :scope > .cp-list__item');

                    rows.forEach(function (row) {
                        row.hidden = needle !== '' && !row.textContent.toLowerCase().includes(needle);
                    });
                });
            });
        }
    })();
</script>

@yield('scripts')
</body>

</html>
