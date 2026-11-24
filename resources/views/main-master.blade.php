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

    <!-- منع التخزين المؤقت للصفحات الديناميكية -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0-alpha1/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.10.1/css/jquery.fileupload.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.10.1/js/jquery.fileupload.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.6.4/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.6/css/selectize.bootstrap3.min.css" integrity="sha256-+C0A5Ilqmu4QcSPxrlGpaZxJ04VjsRjKu+G82kl5UJk=" crossorigin="anonymous" />

    <style>
        #DataTable_filter input {
            border-radius: 5px;
        }

        button span {
            font-family: 'Cairo', sans-serif !important;
        }

        .profile-picture-header {
            width: 40px;
            height: 40px;
            background-color: #7367F0;
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
            color: #333;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        #location-permission-overlay h3 {
            color: #7367F0;
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
            background-color: #28a745;
            color: white;
        }

        .tracking-inactive {
            background-color: #dc3545;
            color: white;
        }

        .tracking-paused {
            background-color: #ffc107;
            color: #212529;
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
            border: 5px solid #f3f3f3;
            border-top: 5px solid #7367F0;
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
            background-color: #28a745;
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
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: right;
        }

        .manual-location-form {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
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
            background-color: #17a2b8;
            color: white;
        }

        .status-browser {
            background-color: #28a745;
            color: white;
        }

        .status-unknown {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>

<body class="vertical-layout vertical-menu-modern 2-columns navbar-floating footer-static menu-collapsed" data-open="click" data-menu="vertical-menu-modern" data-col="2-columns">


    @include('layouts.header')


    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="header-navbar-shadow"></div>
        <div class="content-wrapper">
            {{--
                رسائل الجلسة.

                لم يكن في هذا التخطيط موضعٌ يعرضها إطلاقاً — وهو تخطيط
                الصفحة الرئيسية، أي **وجهةُ كلّ إعادة توجيه في النظام**:
                `EnsureModuleEnabled` يردّ إليها بـ`with('error', …)` حين
                تكون الوحدة مقفلة، وكذلك `CheckJustificationExemption`.
                فكانت الرسالة تُكتب في الجلسة ثم تُستهلك بلا أن تُطبع، ويرى
                المستخدم ارتداداً صامتاً إلى الرئيسية بلا سببٍ معلن — يقرؤه
                عطلاً في الشاشة التي قصدها، لا قفلاً مقصوداً من مسؤوله.
            --}}
            @include('layouts.alerts.error')
            @include('layouts.alerts.success')
            @include('layouts.alerts.warning')

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

    {{-- نظام الإشعارات يُحمّل من layouts/header بعد SweetAlert2 --}}

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
            // المتصفح وحده من يعرض طلب الإذن الأصلي عند الحاجة. المدراء مستثنون.
            const userRole = "{{ auth()->user()->role ?? '' }}";
            if (userRole === 'manager' || userRole === 'main') {
                return;
            }

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

    @yield('scripts')
    @stack('scripts')
</body>
</html>
