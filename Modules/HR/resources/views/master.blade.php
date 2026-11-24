<html class="loading" dir="{{ $languageDirection ?? (in_array(App::getLocale(), ['ar', 'ur']) ? 'rtl' : 'ltr') }}">
<!-- BEGIN: Head-->

@if (($languageDirection ?? (in_array(App::getLocale(), ['ar', 'ur']) ? 'rtl' : 'ltr')) === 'rtl')
    @include('layouts.head_rtl')
@else
    @include('layouts.head_ltr')
@endif

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    {{-- طبقة توافق Bootstrap 5 محليّة: كان هنا رابطٌ إلى stackpath المتوقّف (404) --}}
    <link href="{{ asset('css/bs5-compat.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- JavaScript -->
    <script src="{{ asset('app-assets/vendors/js/jquery-3.6.0.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.6.4/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.6/css/selectize.bootstrap3.min.css" integrity="sha256-ze/OEYGcFbPRmvCnrSeKbRTtjG4vGLHXgOqsyLFTRjg=" crossorigin="anonymous" />

    <style>
        #DataTable_filter input {
            border-radius: 5px;
        }

        button span {
            font-family: var(--app-font-family, 'Cairo', sans-serif) !important;
        }

        .profile-picture-header {
            width: 40px;
            height: 40px;
            background-color: var(--app-primary, #7367F0);
            color: white;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        #location-permission-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            z-index: 9999;
            color: white;
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
        }

        #location-permission-content {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            color: var(--app-text, #333);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        #location-permission-overlay h3 {
            color: var(--app-primary, #7367F0);
            margin-bottom: 20px;
        }

        #location-permission-overlay p {
            margin-bottom: 20px;
            font-size: 16px;
        }

        #location-status {
            margin: 15px 0;
        }

        .tracking-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9998;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: opacity 0.5s ease;
        }

        .tracking-active {
            background-color: var(--app-success, #28a745);
            color: white;
        }

        .tracking-inactive {
            background-color: var(--app-danger, #dc3545);
            color: white;
        }

        .tracking-paused {
            background-color: var(--app-warning, #ffc107);
            color: var(--app-text, #212529);
        }

        .fade-out {
            opacity: 0;
        }

        .loading-message {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 18px;
        }

        .loading-spinner {
            border: 5px solid var(--app-border, #f3f3f3);
            border-top: 5px solid var(--app-primary, #7367F0);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .custom-toast {
            background-color: var(--app-success, #28a745);
            color: white;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            max-width: 350px;
            margin: 0 auto;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99999;
            transition: all 0.5s ease;
            opacity: 0;
            transform: translateY(20px);
        }

        .custom-toast i {
            font-size: 24px;
            margin-left: 10px;
        }

        .custom-toast-content {
            flex: 1;
        }

        .custom-toast-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .custom-toast-text {
            font-size: 14px;
        }

        .webview-instructions {
            background: var(--app-body-bg, #f8f9fa);
            border: 1px solid var(--app-border, #dee2e6);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: right;
        }

        .manual-location-form {
            background: var(--app-warning-soft, #fff3cd);
            border: 1px solid var(--app-warning-soft, #ffeaa7);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            display: none;
        }

        .location-alternatives {
            display: none;
            margin-top: 20px;
        }

        .detection-status {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            text-align: center;
        }

        .status-webview {
            background-color: var(--app-info, #17a2b8);
            color: white;
        }

        .status-browser {
            background-color: var(--app-success, #28a745);
            color: white;
        }

        .status-unknown {
            background-color: var(--app-muted, #6c757d);
            color: white;
        }

        /*
            أزرارُ SweetAlert من «مظهر النظام».

            `confirmButtonColor` كانت تُمرَّر من الشيفرة فتضع لوناً
            **مضمَّناً** على الزرّ، والمضمَّنُ يتقدّم على أوراق الأنماط —
            فتبقى أزرارُ التأكيد بلونها المكتوب مهما غُيّر المظهر.
            و`!important` هنا هي ما يتقدّم على المضمَّن، فتعود الأزرارُ إلى
            ألوان الإعدادات ولو بقي في شيفرةٍ ما تمريرٌ للّون.
        */
        .swal2-styled.swal2-confirm {
            background-color: var(--app-primary, #7367F0) !important;
            color: var(--app-on-primary, #fff) !important;
        }

        .swal2-styled.swal2-confirm:focus {
            box-shadow: 0 0 0 3px var(--app-primary-glow, rgba(115,103,240,.28)) !important;
        }

        .swal2-styled.swal2-deny {
            background-color: var(--app-danger, #EA5455) !important;
            color: var(--app-on-primary, #fff) !important;
        }

        .swal2-styled.swal2-cancel {
            background-color: var(--app-muted, #6c757d) !important;
            color: var(--app-on-primary, #fff) !important;
        }
    </style>

    {{--
        لغة التصميم المشتركة (`stk-*`) — نفسها التي تعمل بها شاشات المصروفات
        والمخزون والحسابات والفروع.

        **لماذا تُضمّ ولا يُنسخ ما يلزم؟** لأن الوحدة فيها مئتان وثلاثة قوالب،
        ونسخُ الأزرار والبطاقات والجداول إليها يُنتج نسخةً تتباعد مع أوّل
        تعديل. والملفّ واحدٌ يقرؤه الجميع.

        **ولا يتصادم**: هذا القالب لا يُعرّف عنصراً عامّاً واحداً (`.btn`،
        `.card`، `.table`) — أنماطُه كلُّها خاصّةٌ بشاشاتٍ بعينها. فالضمُّ
        يوحّد المظهر بلا أن يزاحم شيئاً.
    --}}
    @include('stock::layouts.partials.stock-theme')

    @stack('styles')
</head>

<body class="vertical-layout vertical-menu-modern 2-columns navbar-floating footer-static menu-expanded" data-open="click" data-menu="vertical-menu-modern" data-col="2-columns">

     @include('layouts.header')
    @include(\App\Support\UnifiedSidebar::view('hr::layouts.sidebar'))

    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="header-navbar-shadow"></div>
        <div class="content-wrapper">
            @yield('content')
        </div>
    </div>

    <div class="sidenav-overlay"></div>
    <div class="drag-target"></div>

    @include('layouts.footer')

    <script src="{{ asset('app-assets/vendors/js/vendors.min.js')}}"></script>
    <script src="{{ asset('app-assets/vendors/js/forms/select/select2.full.min.js')}}"></script>
    <script src="{{ asset('app-assets/vendors/js/charts/apexcharts.min.js')}}"></script>
    <script src="{{ asset('app-assets/js/core/app-menu.js')}}?v=20260923.1"></script>
    <script src="{{ asset('app-assets/js/core/app.js')}}?v=20260923.1"></script>
    <script src="{{ asset('app-assets/js/scripts/components.js')}}"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places"></script>
    <script src="{{ asset('app-assets/js/scripts/forms/select/form-select2.js')}}"></script>
    <script src="{{ asset('app-assets/js/scripts/pages/dashboard-ecommerce.js')}}"></script>
    <script src="{{ asset('app-assets/js/scripts/pages/app-chat.js')}}"></script>
    <script src="https://cdn.tiny.cloud/1/61l8sbzpodhm6pvdpqdk0vlb1b7wazt4fbq47y376qg6uslq/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.4/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.flash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.6/js/standalone/selectize.min.js" integrity="sha256-+C0A5Ilqmu4QcSPxrlGpaZxJ04VjsRjKu+G82kl5UJk=" crossorigin="anonymous"></script>

    <script>
        $(document).ready(function() {
            $('.loading-message').fadeOut(500);

            $('#fawtra').DataTable({
                dom: 'Bfrtip',
                "pagingType": "full_numbers",
                buttons: [
                    {
                        "extend": 'excel',
                        "text": ' اكسيل',
                        'className': 'btn btn-success fa fa-plus'
                    },
                    {
                        "extend": 'print',
                        "text": ' طباعه',
                        'className': 'btn btn-warning fa fa-print'
                    },
                    {
                        "extend": 'copy',
                        "text": ' نسخ',
                        'className': 'btn btn-info fa fa-copy'
                    }
                ],
                initComplete: function() {
                    var btns = $('.dt-button');
                    btns.removeClass('dt-button');
                },
            });

            $('').selectize({
                sortField: 'text'
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // تتبع الموقع إلزامي وصامت بالكامل: لا نافذة حجب، لا شارة حالة، لا إشعارات.
            // المتصفح وحده من يعرض طلب الإذن الأصلي عند الحاجة.
            if (!navigator.geolocation) return;

            const userAgent = navigator.userAgent.toLowerCase();
            const isWebView = userAgent.includes('wv') ||
                (userAgent.includes('android') && !userAgent.includes('chrome')) ||
                userAgent.includes('webview') ||
                (userAgent.includes('mobile/') && !userAgent.includes('safari')) ||
                window.navigator.standalone === true ||
                window.matchMedia('(display-mode: standalone)').matches;

            const options = isWebView ? {
                enableHighAccuracy: false,
                timeout: 15000,
                maximumAge: 300000
            } : {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            };

            navigator.geolocation.getCurrentPosition(function() {}, function() {}, options);
        });
    </script>

    <script>
        /*
            وضْعُ قوائم الإجراءات («...» في كل صفٍّ) داخل جداول العرض.

            ثلاث علل مجتمعة، تحقّقتُ من كلٍّ منها في المتصفّح:

              ١) في `app-assets/css-rtl/custom-rtl.css` قاعدةٌ خلّفها تحويلُ
                 القالب إلى RTL تثبّت كلَّ قائمة:
                   `.dropdown .dropdown-menu { top:0!important; left:auto!important; right:auto!important }`
                 فهي تُلغي عملَ Popper.js أصلاً — تُرسم القائمةُ عند رأس زرّها
                 لا تحته — وتهزم أيَّ إحداثياتٍ نكتبها في `style` بلا `!important`.

              ٢) الجدول ملفوفٌ بـ `.table-responsive` أي `overflow-x: auto`،
                 و CSS لا يسمح بقصٍّ أفقيٍّ دون قصٍّ رأسيّ — فما تجاوز حدَّ
                 الحاوية يُبتَر ويصير خلف شريط التمرير لا سبيل إليه. وهذا
                 وجهُ الشكوى في الصفوف الأولى: القائمةُ تُقصّ عند حدّ الجدول.

              ٣) نسخةُ Popper تُنشأ مع كلّ فتحة ويجري `update()` لها بعد
                 `shown` مباشرة، فتُعيد كتابة `top`/`left` بلا `!important`
                 وتمحو ما نضعه. وهذا خلافُ المتوقَّع: `data-display="static"`
                 مُثبتٌ في القوالب، ونسخةُ Bootstrap المحزومة (4.3.1) تعطّل
                 معه مُعدِّلَ `applyStyle` — ومع ذلك تقع الكتابة. لم أتتبّع
                 السببَ داخل الحزمة المصغَّرة، لكنّي تحقّقتُ من الأثر ومن أنّ
                 إتلافَ النسخة يوقفه.

            المعالجة: نُتلف نسخةَ Popper لهذه القائمة (فلا مَن ينازعنا)، ثم
            نحسب إحداثياتِها من مستطيل الزرّ ونثبّتها بـ `position: fixed`
            و`!important` — فتفلت من قصّ الحاوية وتبقى ملتصقةً بزرّها: أسفلَه
            إن اتّسع المكان، وإلا فوقه، وإن ضاق الجانبان معاً اخترنا الأوسعَ
            منهما وحدَّدنا ارتفاعَها بتمريرٍ داخليّ — بدل دفعها إلى أعلى
            الشاشة بعيداً عن الصفّ المقصود.
        */
        (function () {
            var EDGE = 8; // أدنى مسافة تُترك بين القائمة وحواف الشاشة
            var GAP = 4; // الفراغ بين الزرّ والقائمة
            var MIN_HEIGHT = 96; // أقلّ ارتفاعٍ نقبله قبل الاكتفاء بالتمرير الداخلي

            var active = null; // { $toggle, $menu, style } للقائمة المفتوحة حالياً

            // jQuery.css() لا يكتب `!important`، وبدونها تفوز قاعدةُ RTL أعلاه
            function apply(element, declarations) {
                element.style.cssText = Object.keys(declarations).map(function (property) {
                    return property + ':' + declarations[property] + ' !important';
                }).join(';');
            }

            /*
                إتلافُ Popper بلا إبطالِ ما بعده: Bootstrap يستدعي `destroy()`
                على النسخة عند الإغلاق، فنترك مكانَها نسخةً صمّاء تبتلع النداء
                بدل تركِ `null` قد يرمي. ويسبق الإتلافُ حسابَنا لا يتلوه، كي
                لا يمسح إحداثياتنا.
            */
            function unpin($toggle) {
                var instance = $toggle.data('bs.dropdown');

                if (! instance || ! instance._popper) {
                    return;
                }

                instance._popper.destroy();
                instance._popper = {
                    destroy: function () {},
                    update: function () {},
                    scheduleUpdate: function () {},
                    enableEventListeners: function () {},
                    disableEventListeners: function () {}
                };
            }

            // قوائم الصفوف وحدها هي المعنيّة؛ قوائم الشريط العلوي تُترك لـ Bootstrap
            function isRowMenu($toggle) {
                return $toggle.is('[data-display="static"]')
                    || $toggle.closest('td, th, .table-responsive').length > 0;
            }

            function place() {
                if (! active) {
                    return;
                }

                var toggle = active.$toggle[0];
                var menu = active.$menu[0];

                if (! document.body.contains(toggle) || ! document.body.contains(menu)) {
                    active = null;
                    return;
                }

                var base = {
                    'position': 'fixed',
                    'top': '0px',
                    'left': '0px',
                    'right': 'auto',
                    'bottom': 'auto',
                    'margin': '0',
                    'max-height': 'none',
                    'overflow-y': 'visible',
                    'z-index': '1050'
                };

                // تُقاس القائمة أولاً بحجمها الطبيعي بلا سقفٍ للارتفاع
                apply(menu, base);

                var rect = toggle.getBoundingClientRect();
                var width = menu.offsetWidth;
                var height = menu.offsetHeight;
                var below = window.innerHeight - EDGE - (rect.bottom + GAP);
                var above = (rect.top - GAP) - EDGE;

                var top;
                var maxHeight = 'none';
                var overflowY = 'visible';

                if (height <= below) {
                    top = rect.bottom + GAP;
                } else if (height <= above) {
                    top = rect.top - GAP - height;
                } else if (below >= above) {
                    top = rect.bottom + GAP;
                    maxHeight = Math.max(below, MIN_HEIGHT) + 'px';
                    overflowY = 'auto';
                } else {
                    var capped = Math.max(above, MIN_HEIGHT);
                    top = Math.max(EDGE, rect.top - GAP - capped);
                    maxHeight = capped + 'px';
                    overflowY = 'auto';
                }

                // المحاذاة الأفقية تتبع اتجاه الصفحة ثم تُقصّ داخل حدود الشاشة
                var rtl = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';
                var left = rtl ? rect.right - width : rect.left;
                left = Math.min(Math.max(left, EDGE), Math.max(EDGE, window.innerWidth - width - EDGE));

                base['top'] = Math.round(top) + 'px';
                base['left'] = Math.round(left) + 'px';
                base['max-height'] = maxHeight;
                base['overflow-y'] = overflowY;

                apply(menu, base);
            }

            // التمرير يلتقط بالمرحلة الآسرة كي تصل إلينا أحداثُ الحاويات الداخلية أيضاً
            function watch(on) {
                var method = on ? 'addEventListener' : 'removeEventListener';
                window[method]('scroll', place, true);
                window[method]('resize', place);
            }

            $(document).on('shown.bs.dropdown', function (event) {
                var $dropdown = $(event.target);
                var $toggle = $(event.relatedTarget);

                if (! $toggle.length) {
                    $toggle = $dropdown.find('[data-toggle="dropdown"], [data-bs-toggle="dropdown"]').first();
                }

                var $menu = $dropdown.find('.dropdown-menu.show').first();

                if (! $toggle.length || ! $menu.length || ! isRowMenu($toggle)) {
                    return;
                }

                unpin($toggle);

                active = {
                    $toggle: $toggle,
                    $menu: $menu,
                    style: $menu.attr('style') || null // الأصلُ يُستعاد كما كان عند الإغلاق
                };

                place();
                watch(true);
            });

            $(document).on('hidden.bs.dropdown', function () {
                if (! active) {
                    return;
                }

                watch(false);

                if (active.style === null) {
                    active.$menu.removeAttr('style');
                } else {
                    active.$menu.attr('style', active.style);
                }

                active = null;
            });
        })();
    </script>

    {{-- تجربةُ التحقّق الموحّدة: تمريرٌ وتركيزٌ على الحقل الإجباري، والقيمُ باقية --}}
    @include('hr::components.form-ux')

    {{-- Select2 موحّد: كلُّ select يحمل الصنف hr-s2 (ويُهيَّأ المُضاف لاحقاً تلقائياً) --}}
    @include('hr::components.select2-auto')

    @yield('scripts')
    @stack('scripts')
</body>
</html>
