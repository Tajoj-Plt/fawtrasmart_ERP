{{-- القالب الرئيسي لموديول الفروع.

     الترويسة والأصول مطابقة حرفياً لقالب شاشات النظام (client::master):
     نفس ملفّات ثيم Vuexy، ونفس Bootstrap 5 فوق BS4، ونفس ترتيب السكربتات،
     ونفس أصناف <body>. الاختلاف الوحيد المقصود هو القائمة الجانبية —
     بنود الفروع بدل بنود العملاء. --}}
<!DOCTYPE html>
@php
    $dir = $languageDirection ?? (in_array(App::getLocale(), ['ar', 'ur']) ? 'rtl' : 'ltr');
@endphp
{{-- `class="loading"` جزء من ثيم Vuexy: `app.js` يزيله بعد اكتمال التحميل،
     وقالب «الإعدادات» يحمله. بدونه تختلف حالة الصفحة الأولى عن بقية الشاشات. --}}
<html class="loading" dir="{{ $dir }}">
<!-- BEGIN: Head-->

@if ($dir === 'rtl')
    @include('layouts.head_rtl')
@else
    @include('layouts.head_ltr')
@endif

<head>
    @stack('styles')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('branches.nav.dashboard')) — {{ config('app.name', 'Fawtra') }}</title>

    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0-alpha1/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.6/css/selectize.bootstrap3.min.css"
          integrity="sha256-+C0A5Ilqmu4QcSPxrlGpaZxJ04VjsRjKu+G82kl5UJk=" crossorigin="anonymous" />

    <style>
        button span { font-family: 'Cairo', sans-serif !important; }
    </style>

    {{--
        لغة التصميم المشتركة (`stk-*`) — نفسها التي تعمل بها شاشات المصروفات
        والمخزون والحسابات.

        **لماذا تُضمّ هنا ولا يُنسخ ما يلزم؟** لأن نسخَ البطاقات والمرشِّحات
        إلى ثيم الفروع يُنتج نسختين تتباعدان مع أوّل تعديل: يُصلَح لونٌ في
        المخزون فيبقى الفرعُ على القديم. والملفّ واحدٌ يقرؤه الجميع.

        **ولا يتصادم مع ثيم الفروع**: هذا يُعرّف عناصر عامّة (`.btn`، `.card`،
        `.badge`) ومكوّنات `stk-*`، وثيمُ الفروع مُسبَّقٌ كلُّه بـ`bx-*` بلا
        تجاوزٍ لعنصرٍ عام واحد. وترتيبُهما مقصود: المشترك أوّلاً، وثيمُ الفروع
        بعده — فما يخصّ الوحدة يبقى له القرار الأخير.
    --}}
    @include('stock::layouts.partials.stock-theme')

    @include('branches::layouts.theme')

    {{-- لغة تصميم المبيعات (`sl-`) — نسخةُ هذه الوحدة منها. تأتي أخيراً:
         هي التي تحسم شكلَ الزرّ والبطاقة والجدول والحقل داخل `.sl-scope`،
         فتخرج شاشاتُ الفروع بمظهر شاشات المبيعات نفسِه. --}}
    @include('branches::layouts.sales-theme')

    @yield('css')
</head>

<body class="vertical-layout vertical-menu-modern 2-columns navbar-floating footer-static menu-expanded"
      data-open="click" data-menu="vertical-menu-modern" data-col="2-columns">

    @include('layouts.header')
    @include(\App\Support\UnifiedSidebar::view('branches::layouts.sidebar'))

    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="header-navbar-shadow"></div>
        {{-- `sl-scope` على الحاوية كلِّها لا على `content-body`: ترويسةُ الصفحة
             والنوافذُ المنبثقة تقع خارجه، وأزرارُها من اللغة نفسها. --}}
        <div class="content-wrapper sl-scope">
            @yield('content')
        </div>
    </div>

    <div class="sidenav-overlay"></div>
    <div class="drag-target"></div>

    @include('layouts.footer')

    <script src="{{ asset('app-assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('app-assets/vendors/js/forms/select/select2.full.min.js') }}"></script>
    <script src="{{ asset('app-assets/js/core/app-menu.js') }}?v=20260923.1"></script>
    <script src="{{ asset('app-assets/js/core/app.js') }}?v=20260923.1"></script>
    <script src="{{ asset('app-assets/js/scripts/components.js') }}"></script>
    <script src="{{ asset('app-assets/js/scripts/forms/select/form-select2.js') }}"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        /*
            نافذةُ التأكيد في شاشات الفروع — بانٍ واحد.

            كانت كلُّ شاشةٍ تبني نصَّ نافذتها بيدها داخل قالبٍ نصّيّ، وتلصق
            الترجمةَ فيه بعلامات جمعٍ وكأنها خارجه — فتظهر العلاماتُ والدوالُّ
            نفسُها للمستخدم («' + "نقل :n عميل…".replace(…) + '») بدل السؤال.

            هنا السؤالُ سطرٌ، ثمّ «من / إلى» صفوفاً بتسمياتها، ثمّ الملاحظة.
            وكلُّ نصٍّ يمرّ على `esc` — اسمُ فرعٍ فيه علامةٌ لا يكسر النافذة —
            ولونُ الزرّ من مظهر النظام لا لونٌ مكتوب.
        */
        window.bxConfirm = (function () {
            function esc(text) {
                return $('<div>').text(text === null || text === undefined ? '' : String(text)).html();
            }

            function color(name, fallback) {
                var value = getComputedStyle(document.documentElement).getPropertyValue(name);

                return (value && value.trim()) || fallback;
            }

            /**
             * @param {string} question  السؤال — نصٌّ خام
             * @param {Array<{label: string, value: string, items?: string[]}>} rows
             * @param {string=} note     ما يترتّب على التنفيذ
             */
            function body(question, rows, note) {
                var html = '<div class="bx-confirm"><p class="bx-confirm__q">' + esc(question) + '</p>';

                if (rows && rows.length) {
                    html += '<dl class="bx-confirm__rows">';
                    rows.forEach(function (row) {
                        var value = row.items
                            ? '<ul>' + row.items.map(function (item) { return '<li>' + esc(item) + '</li>'; }).join('') + '</ul>'
                            : esc(row.value);

                        html += '<div><dt>' + esc(row.label) + '</dt><dd>' + value + '</dd></div>';
                    });
                    html += '</dl>';
                }

                if (note) {
                    html += '<p class="bx-confirm__note"><i class="feather icon-info"></i> ' + esc(note) + '</p>';
                }

                return html + '</div>';
            }

            return {
                esc: esc,
                body: body,
                primary: function () { return color('--app-primary', '#7367f0'); },
                danger: function () { return color('--app-danger', '#ea5455'); },
                muted: '#82868b'
            };
        })();

        $(function () {
            $('.loading-message').fadeOut(300);

            // تنبيهات الجلسة موحّدة على مستوى الموديول: كل شاشة كانت تكرّر كتلة
            // Swal بصياغة مختلفة، وبعضها يطبع الرسالة داخل سلسلة جافاسكربت
            // فتنكسر عند وجود علامة اقتباس في اسم فرع.
            @if (session('success'))
                Swal.fire({ icon: 'success', title: @json(__('branches.common.success_title')), text: @json(session('success')),
                            confirmButtonText: @json(__('branches.common.ok')), confirmButtonColor: '#7367f0' });
            @endif
            @if (session('error'))
                Swal.fire({ icon: 'error', title: @json(__('branches.common.error_title')), text: @json(session('error')),
                            confirmButtonText: @json(__('branches.common.ok')), confirmButtonColor: '#ea5455' });
            @endif
            @if (session('partial_success'))
                Swal.fire({ icon: 'warning', title: @json(__('branches.common.partial_title')), text: @json(session('partial_success')),
                            confirmButtonText: @json(__('branches.common.ok')), confirmButtonColor: '#ff9f43' });
            @endif
        });
    </script>

    @yield('scripts')
</body>
</html>
