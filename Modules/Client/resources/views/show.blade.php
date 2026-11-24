@extends('client::master')

@section('title')
    عرض العميل
@stop

@section('head')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
@endsection

@push('styles')
    {{-- `client::master` لا يملك `@yield('css')` — كان هذا القسم يُصاغ ولا
         يُطبع قطّ، فتبقى `.actions-card`/`.actions-grid`/تبويبات الشاشة
         بلا أيّ تنسيق من هذا الملف. `@stack('styles')` هو ما يقرؤه التخطيط
         فعلاً (`master.blade.php:12`). --}}
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/show_client.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/show_client_redesign.css') }}?v=8">
    <link rel="stylesheet" href="{{ asset('assets/css/visit_note_cards.css') }}?v=7">
@endpush

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if (session('toast_message'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                toastr.{{ session('toast_type', 'success') }}('{{ session('toast_message') }}', '', {
                    positionClass: 'toast-bottom-left',
                    closeButton: true,
                    progressBar: true,
                    timeOut: 5000
                });
            });
        </script>
    @endif

    @include('client::partials.settings-theme')
    @include('components.hint-tips')
    {{-- قوائم «خيارات» في جداول الفواتير والمدفوعات والمواعيد تخرج من حدود الجدول --}}
    @include('client::partials.table-menu')

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => $client->trade_name,
            'icon' => 'user',
            'sub' => 'كود العميل: ' . $client->code,
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                $client->trade_name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً، كما في «عرض المورد» --}}
        @include('client::components.guide', [
            'key' => 'clients.show',
            'open' => false,
            'title' => __('client_settings.guide.show.title'),
            'lead' => __('client_settings.guide.show.lead'),
            'blocks' => [
                __('client_settings.guide.show.balance.title') => [
                    __('client_settings.guide.show.balance.a'),
                    __('client_settings.guide.show.balance.b'),
                ],
                __('client_settings.guide.show.followup.title') => [
                    __('client_settings.guide.show.followup.a'),
                    __('client_settings.guide.show.followup.b'),
                ],
            ],
            'facts' => [
                __('client_settings.guide.show.facts.log'),
                __('client_settings.guide.show.facts.period'),
            ],
        ])

    @php
        $balance = $due ?? 0;

        /* رمزُ العملة من «العملات» لا من `$account_setting->currency` —
           ذاك إعدادُ حسابٍ شخصيّ لا إعدادُ منشأة، واحتياطُه `'SAR'` يجعل
           من ضبط عملةَ نظامه على غيرها يرى الريال في ملفّ عميله.
           وصورةُ الريال تبقى لمن عملتُه الريال فعلاً، لا لكلّ من غابت
           عنه القيمة. */
        $currencyCode = \App\Support\CurrencySettings::code();
        $currencySymbol =
            $currencyCode === 'SAR'
                ? '<img src="' .
                    asset('assets/images/Saudi_Riyal.svg') .
                    '" alt="ريال سعودي" width="15" style="vertical-align: middle;">'
                : \App\Support\CurrencySettings::symbol();

        if ($balance > 50000) {
            $badgeClass = 'bg-danger';
            $badgeText = 'عاجل';
        } elseif ($balance > 0) {
            $badgeClass = 'bg-warning text-dark';
            $badgeText = 'متأخر';
        } else {
            $badgeClass = 'bg-success';
            $badgeText = 'مُسوّى';
        }
    @endphp

        <div class="cx-page">
            <!-- بطاقة رأس العميل: الاسم + الحالة + الإجراءات + المعلومات السريعة -->
            @include('client::partials.client-info')

            <!-- التبويبات -->
            @include('client::partials.tabs-container')
        </div>

        <!-- Modals -->
        @include('client::partials.modals')

    </div>
@endsection

@section('scripts')
    {{-- حقول الهاتف والجوال: رقمٌ سعودي يبدأ بصفر وطولُه 10 — نظيرُ App\Rules\SaudiPhone --}}
    <script src="{{ asset('assets/js/saudi-phone.js') }}?v=1"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>

    <!-- نظام الإشعارات الاحترافي -->
    <script src="{{ asset('js/notification-system.js?v=' . time()) }}"></script>

    <!-- سكريبت تسوية الديون المعدومة -->
    <script src="{{ asset('js/client/write_off_debt.js') }}"></script>

    <!-- سكريبت عرض العميل -->
    <script src="{{ asset('js/show-client.js') }}"></script>

    <!-- سكريبت مدير العملاء المخفيين -->
    <script src="{{ asset('assets/js/hidden-clients-manager.js') }}"></script>

    <!-- سكريبت ملاحظات الموظفين -->
    <script src="{{ asset('js/client/employee-notes.js?v=' . time()) }}"></script>

    <script>
        // تعريف البيانات المطلوبة للـ modals
        window.currentClient = {
            id: {{ $client->id }},
            name: '{{ $client->trade_name }}'
        };

        // تعريف المستخدم الحالي
        window.currentUser = {
            id: {{ auth()->id() }},
            name: '{{ auth()->user()->name }}'
        };

        // تعريف الـ routes
        window.clientRoutes = {
            updateCreditLimit: "{{ route('clients.update_credit_limit') }}",
            updateAllCreditLimits: "{{ route('clients.update_all_credit_limits') }}",
            getCreditLimit: "{{ route('clients.getCreditLimit', ':id') }}",
            hideFromMap: "{{ route('clients.hideFromMap', ':id') }}",
            showInMap: "{{ route('clients.showInMap', ':id') }}",
            index: "{{ route('clients.index') }}"
        };

        // الـ CSRF Token
        window.csrfToken = "{{ csrf_token() }}";

        /**
         * تنظيف قائمة الإشعارات المحذوفة القديمة جداً (أكثر من 24 ساعة)
         */
        function cleanupOldDeletedNotifications() {
            let lastCleanup = localStorage.getItem('lastNotificationCleanup');
            let now = Date.now();
            let twentyFourHours = 24 * 60 * 60 * 1000;

            if (!lastCleanup || (now - parseInt(lastCleanup)) > twentyFourHours) {
                localStorage.removeItem('deletedNotifications');
                localStorage.setItem('lastNotificationCleanup', now.toString());
                console.log('✅ تم تنظيف قائمة الإشعارات المحذوفة القديمة');
            }
        }

        // تنظيف البيانات القديمة عند تحميل الصفحة
        cleanupOldDeletedNotifications();

        document.addEventListener('DOMContentLoaded', function() {
            // تهيئة مدير العملاء المخفيين
            if (typeof HiddenClientsManager !== 'undefined') {
                window.hiddenClientsManager = new HiddenClientsManager();
                window.hiddenClientsManager.init();
            }

            // عودةٌ إلى «تسليم الفواتير» بعد عملياته — تُعيد الصفحة بـ#invoice-delivery
            if (location.hash === '#invoice-delivery') {
                var transferTab = document.getElementById('invoice-delivery-tab');
                if (transferTab && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    bootstrap.Tab.getOrCreateInstance(transferTab).show();
                }
            }

            // ⭐ جلب وعرض إشعارات Popup للعميل
            checkClientPopupNotifications({{ $client->id }});
        });

        /**
         * دالة للتحقق من وجود إشعارات popup للعميل وعرضها
         */
        function checkClientPopupNotifications(clientId) {
            console.log('🔔 Checking popup notifications for client:', clientId);
            console.log('🔔 CSRF Token:', window.csrfToken);

            fetch(`/notifications/client-popup/${clientId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken
                }
            })
            .then(response => {
                console.log('🔔 Response status:', response.status);
                console.log('🔔 Response ok:', response.ok);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('🔔 Error response:', text);
                        throw new Error('Network response was not ok');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('🔔 Popup notifications data:', JSON.stringify(data, null, 2));
                if (data.success && data.count > 0) {
                    // تصفية الإشعارات المحذوفة من localStorage
                    let deletedNotifications = JSON.parse(localStorage.getItem('deletedNotifications') || '[]');
                    let filteredNotifications = data.notifications.filter(notification =>
                        !deletedNotifications.includes(notification.id)
                    );

                    console.log('🔔 Deleted notifications:', deletedNotifications);
                    console.log('🔔 Filtered notifications count:', filteredNotifications.length);

                    if (filteredNotifications.length > 0) {
                        console.log('🔔 Showing', filteredNotifications.length, 'notifications');
                        console.log('🔔 Notifications:', filteredNotifications);
                        // استخدام نظام الإشعارات الاحترافي (نفس الهيدر)
                        // العميل يرى فقط زر "الرد" بدون "تم الاستلام"
                        showClientNotificationsSequentially(filteredNotifications, 0);
                    } else {
                        console.log('🔔 All notifications were already deleted');
                    }
                } else {
                    console.log('🔔 No notifications to show. Success:', data.success, 'Count:', data.count);
                }
            })
            .catch(error => {
                console.error('🔔 Error fetching client popup notifications:', error);
            });
        }

        /**
         * عرض الإشعارات للعميل مع إمكانية الرد
         */
        function showClientNotificationsWithReply(notifications, index = 0) {
            if (!notifications || notifications.length === 0 || index >= notifications.length) return;

            const notification = notifications[index];
            const clientId = {{ $client->id }};

            Swal.fire({
                icon: 'warning',
                title: notification.title || 'إشعار جديد',
                html: `
                    <div style="text-align: right; direction: rtl;">
                        <div style="font-weight: 700; color: #7367F0; margin-bottom: 12px;">
                            <i class="fas fa-user-circle" style="margin-left: 8px;"></i>
                            المرسل: ${notification.sender || 'النظام'}
                        </div>
                        <div style="color: #444; padding: 10px; background: rgba(115, 103, 240, 0.08); border-radius: 10px; border-right: 4px solid #7367F0; margin: 15px 0;">
                            ${notification.description || 'لا يوجد وصف'}
                        </div>
                        <div style="color: #888; font-size: 13px;">
                            <i class="fas fa-clock" style="margin-left: 8px;"></i>
                            ${notification.created_at}
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check" style="margin-left: 8px;"></i>تم الاستلام',
                cancelButtonText: '<i class="fas fa-reply" style="margin-left: 8px;"></i>الرد',
                confirmButtonColor: '#7367F0',
                cancelButtonColor: '#28a745',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed && notification.id) {
                    // وضع علامة مقروء
                    markClientNotificationAsRead(notification.id);
                    // عرض الإشعار التالي
                    setTimeout(() => {
                        showClientNotificationsWithReply(notifications, index + 1);
                    }, 300);
                } else if (result.dismiss === Swal.DismissReason.cancel && notification.id) {
                    // فتح نافذة الرد
                    showClientReplyDialog(notification, notifications, index);
                }
            });
        }

        /**
         * عرض نافذة الرد على الإشعار من صفحة العميل
         */
        function showClientReplyDialog(notification, notifications, index) {
            Swal.fire({
                title: 'الرد على الإشعار',
                html: `
                    <div style="text-align: right; direction: rtl;">
                        <div class="alert alert-info mb-3">
                            <strong>الإشعار الأصلي:</strong> ${notification.title || 'بدون عنوان'}
                            <br><small>من: ${notification.sender || 'النظام'}</small>
                        </div>
                        <textarea id="swal-reply-message" class="swal2-textarea" placeholder="اكتب ردك هنا..." style="text-align: right; direction: rtl;"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-paper-plane" style="margin-left: 8px;"></i>إرسال الرد',
                cancelButtonText: '<i class="fas fa-times" style="margin-left: 8px;"></i>إلغاء',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const replyMessage = document.getElementById('swal-reply-message').value;
                    if (!replyMessage.trim()) {
                        Swal.showValidationMessage('يرجى كتابة رسالة الرد');
                        return false;
                    }
                    return replyMessage;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // إرسال الرد
                    sendClientNotificationReply(notification.id, result.value).then((success) => {
                        if (success) {
                            markClientNotificationAsRead(notification.id);
                            // عرض الإشعار التالي
                            setTimeout(() => {
                                showClientNotificationsWithReply(notifications, index + 1);
                            }, 300);
                        } else {
                            // إذا فشل الرد، نعود للإشعار الأصلي
                            showClientNotificationsWithReply(notifications, index);
                        }
                    });
                } else {
                    // إذا ألغى الرد، نعود للإشعار الأصلي
                    showClientNotificationsWithReply(notifications, index);
                }
            });
        }

        /**
         * إرسال رد على الإشعار من صفحة العميل
         */
        function sendClientNotificationReply(notificationId, replyMessage) {
            return fetch(`{{ route('notifications.client.reply', ['id' => '__ID__']) }}`.replace('__ID__', notificationId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken
                },
                body: JSON.stringify({ reply_message: replyMessage })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('✅ تم إرسال الرد بنجاح من العميل:', data);
                    return true;
                } else {
                    console.error('❌ فشل الرد:', data.message);
                    return false;
                }
            })
            .catch(error => {
                console.error('❌ خطأ في إرسال الرد:', error);
                return false;
            });
        }

        /**
         * وضع علامة مقروء على إشعار العميل
         */
        function markClientNotificationAsRead(notificationId) {
            fetch('/notifications/client-popup/mark-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('✅ تم وضع علامة مقروء على الإشعار:', notificationId);
                }
            })
            .catch(error => console.error('❌ خطأ في وضع علامة مقروء:', error));
        }

    </script>
@endsection

