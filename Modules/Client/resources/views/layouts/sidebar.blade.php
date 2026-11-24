{{--
    القائمة الجانبية لوحدة العملاء.

    **مجموعاتٌ قابلةٌ للطيّ** (2026-09-16، طلب المستخدم: «خط السير لوحده،
    والعملاء، وغيرها»، ثمّ اختار الطيَّ على العناوين الثابتة): العملاء ←
    خط السير والزيارات ← الحجوزات والمواعيد ← التقارير والإعدادات. بنيةُ
    القالب نفسُها (`nav-item has-sub` + `ul.menu-content`) فيطويها `app-menu.js`
    بلا سكربتٍ خاصّ، و`menu-accordion` على الحاوية يُغلق المفتوحةَ عند فتح أخرى.
    المجموعةُ التي فيها الشاشةُ الحالية تُفتح من الخادم (`open`) — فلا يُدفن
    البندُ الذي يقف عليه المستخدم خلف نقرة.

    **الحجوزات والمواعيد صارت هنا** بعد نقل وحدة الحجوزات إلى العملاء؛
    شاشاتُها (`client::reservations.master`) تعرض هذا الشريط نفسه.

    ══════════ كيف تُبنى ══════════

    كلُّ مجموعةٍ مصفوفةُ بنود، ولكلّ بندٍ `show` محسوبٌ من حارسه الذي كان
    عليه حرفاً (`@can` ← `can()`، و`@permission` ← `hasPermission()`) —
    فلا يظهر لأحدٍ رابطٌ لم يكن يراه ولا يختفي رابطٌ كان يراه. والعنوانُ
    لا يُطبع إلّا إن ظهر تحته بندٌ واحد على الأقل، فلا عناوين يتيمة.

    وبوّابةُ كلّ مجموعة مفتاحُ «شاشة التطبيقات» الخاصُّ بها: `customers`
    للعملاء وخط السير والمواعيد، و`booking_management` للحجوزات — نفسُ
    المفتاح الذي يقرؤه وسيط `EnsureModuleEnabled` على بادئة `Reservations/`.
--}}

@php
    /*
     * إعدادات التطبيقات — المفتاح ← الحالة.
     *
     * الفحص بالنوع لا بـ`isset()` وحدها: `@extends` يمرّر متغيّرات قالب
     * الشاشة كلَّها إلى القالب الأمّ ومنه إلى هنا، فأيّ شاشة تُعرّف
     * `$settings` لنفسها — وشاشةُ إعدادات الحجوزات تفعل ذلك حرفياً —
     * تطمس هذه المصفوفة وتختفي القائمة بلا خطأ يُرى.
     */
    $settings = (isset($settings) && is_array($settings))
        ? $settings
        : \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();

    /** أمفعَّلٌ هذا التطبيق في «شاشة التطبيقات»؟ */
    $appOn = fn (string $key) => ($settings[$key] ?? null) === 'active';

    $user = auth()->user();
    $can = fn (string $permission) => $user && $user->can($permission);

    /*
     * مفاتيح «إعدادات وحدة العملاء» — الديونُ المشكوك فيها والمواعيد تُطفآن
     * منها. القراءة مرّةً واحدة: الشريط يُعرَض في كل شاشة.
     */
    $clientModule = \App\Models\ClientModuleSetting::current();

    $customersOn = $appOn('customers');
    $bookingOn = $appOn('booking_management');

    $groups = [
        [
            'title' => 'العملاء', 'icon' => 'users',
            'items' => [
                ['show' => $customersOn && hasPermission('clients_dashboard_view|clients_view_all_clients'),
                    'route' => 'clients.dashboard', 'icon' => 'pie-chart', 'active' => 'clients.dashboard*', 'label' => 'لوحة العملاء'],
                ['show' => $customersOn && hasPermission('clients_view_all_clients|clients_view_own_clients'),
                    'route' => 'clients.index', 'icon' => 'users', 'active' => 'clients.index', 'label' => trans('main_trans.Customer_management')],
                ['show' => $customersOn && $can('clients_add_client'),
                    'route' => 'clients.create', 'icon' => 'user-plus', 'active' => 'clients.create', 'label' => trans('main_trans.Add_a_new_customer')],
                // نفسُ صلاحية المسار و`CRMController`
                ['show' => $customersOn && hasPermission('clients_crm_view'),
                    'route' => 'clients.mang_client', 'icon' => 'heart', 'active' => 'clients.mang_client', 'label' => trans('main_trans.Customer_relationship_management')],
                ['show' => $customersOn && hasPermission('clients.wallet.view'),
                    'route' => 'clients.wallet.index', 'icon' => 'credit-card', 'active' => 'clients.wallet.*', 'label' => 'محافظ العملاء'],
                /* إشعاراتُ سداد بوابة العميل — بصلاحية سند القبض نفسِها التي
                   يحرس بها المسار، فلا يظهر رابطٌ يردّ ضاغطَه. */
                ['show' => $customersOn && hasPermission('sales.payments.view'),
                    'route' => 'clients.payment_notices.index', 'icon' => 'inbox', 'active' => 'clients.payment_notices.*', 'label' => 'إشعارات السداد'],
                // شاشةُ تسليم الفواتير — بلا رابطٍ هنا تبقى «واردة بانتظار ردّك» بلا مدخل
                ['show' => $customersOn && hasPermission('clients_invoice_transfer_view'),
                    'route' => 'invoice-transfers.index', 'icon' => 'repeat', 'active' => 'invoice-transfers.*', 'label' => 'تحويلات الفواتير'],
                ['show' => $customersOn && $can('doubtful_debts_view') && $clientModule->doubtful_debts_enabled,
                    'route' => 'doubtful-debts.index', 'icon' => 'alert-circle', 'active' => 'doubtful-debts.*', 'label' => 'الديون المشكوك فيها'],
                // كتالوج المنافسين — يملأ «رصد المنافسين» في ملاحظة الزيارة
                ['show' => $customersOn && hasPermission('clients_competitors_view'),
                    'route' => 'clients.competitors.index', 'icon' => 'target', 'active' => 'clients.competitors.*', 'label' => 'المنافسون'],
                ['show' => $customersOn && hasPermission('clients_visit_justifications_view_all'),
                    'route' => 'clients.employees_directory', 'icon' => 'book', 'active' => 'clients.employees_directory', 'label' => trans('main_trans.Contact_list')],
            ],
        ],
        [
            'title' => 'خط السير والزيارات', 'icon' => 'navigation',
            'items' => [
                // «خط سيري اليومي» — المندوب ليومه، ومن يضيف لغيره يفتح منه خطَّ أيّ مندوبٍ في نطاقه
                ['show' => $customersOn && hasPermission('clients_daily_itinerary_view|clients_daily_itinerary_add_for_others'),
                    'route' => 'itinerary.daily', 'icon' => 'navigation', 'active' => 'itinerary.daily*', 'label' => 'خط سيري اليومي'],
                /* طلباتُ الزيارة من بوابة العميل — بصلاحيات المسار نفسِها
                   (زوجُ العرض)، فلا يظهر رابطٌ يردّ ضاغطَه. */
                ['show' => $customersOn && hasPermission('clients_itinerary_view|clients_daily_itinerary_add_for_others'),
                    'route' => 'clients.visit_requests.index', 'icon' => 'map-pin', 'active' => 'clients.visit_requests.*', 'label' => 'طلبات الزيارة'],
                ['show' => $customersOn && $can('clients_itinerary_view'),
                    'route' => 'itinerary.list', 'icon' => 'map', 'active' => 'itinerary.list', 'label' => 'خط السير'],
                ['show' => $customersOn && $can('clients_itinerary_view'),
                    'route' => 'itinerary.dashboard', 'icon' => 'bar-chart-2', 'active' => 'itinerary.dashboard', 'label' => 'لوحة تحكّم خط السير'],
                ['show' => $customersOn && hasPermission('clients_view_forgotten_visits_own|clients_view_forgotten_visits_all|clients_view_itinerary_dashboard'),
                    'route' => 'visits.itinerary-dashboard', 'icon' => 'alert-circle', 'active' => 'visits.itinerary-dashboard', 'label' => 'الزيارات الجارية'],
                ['show' => $customersOn && $user?->isEmployee() && hasPermission('clients_visit_justifications_view_own|clients_visit_justifications_view_all'),
                    'route' => 'employee.visit-justifications.index', 'icon' => 'message-square', 'active' => 'employee.visit-justifications.*', 'label' => 'تبريراتي'],
                ['show' => $customersOn && hasPermission('clients_visit_justifications_view_all'),
                    'route' => 'admin.visit-justifications.index', 'icon' => 'check-square', 'active' => 'admin.visit-justifications.*', 'label' => 'تبريرات الموظفين'],
            ],
        ],
        [
            'title' => 'الحجوزات والمواعيد', 'icon' => 'calendar',
            'items' => [
                // شاشة «ابدأ من هنا» حُذفت (2026-09-20) — الجاهزية بقيت شريطاً فوق الإنشاء واللوحة
                ['show' => $bookingOn && hasPermission('reservations.dashboard'),
                    'route' => 'Reservations.dashboard', 'icon' => 'activity', 'active' => 'Reservations.dashboard', 'label' => 'لوحة الحجوزات'],
                ['show' => $bookingOn && hasPermission('reservations.view'),
                    'route' => 'Reservations.index', 'icon' => 'bookmark', 'active' => 'Reservations.index*|Reservations.show|Reservations.edit|Reservations.client', 'label' => 'إدارة الحجوزات'],
                ['show' => $bookingOn && hasPermission('reservations.create'),
                    'route' => 'Reservations.create', 'icon' => 'plus-circle', 'active' => 'Reservations.create', 'label' => 'حجز جديد'],
                ['show' => $bookingOn && hasPermission('reservations.calendar'),
                    'route' => 'Reservations.calendar', 'icon' => 'calendar', 'active' => 'Reservations.calendar', 'label' => 'تقويم الحجوزات'],
                // المواعيد ميزةُ عملاء (بادئة `clients/`): يحكمها قاطعُ العملاء لا الحجوزات،
                // وصلاحيةُ العرض نفسُها التي يقبلها المسار (كانت «الإضافة» وحدها)
                ['show' => $customersOn && $clientModule->appointments_enabled && hasPermission('client_follow_up_view_notes_attachments_appointments_all|client_follow_up_view_notes_attachments_appointments_assigned|client_follow_up_view_notes_attachments_appointments_own'),
                    'route' => 'appointments.index', 'icon' => 'clock', 'active' => 'appointments.*|appointment.notes.*', 'label' => trans('main_trans.Appointments')],
                ['show' => $bookingOn && hasPermission('reservation_services.view'),
                    'route' => 'Reservations.services.index', 'icon' => 'briefcase', 'active' => 'Reservations.services.*', 'label' => 'خدمات الحجز'],
            ],
        ],
        [
            'title' => 'التقارير والإعدادات', 'icon' => 'bar-chart-2',
            'items' => [
                ['show' => $customersOn && $can('client_reports_view'),
                    'route' => 'ClientReport.index', 'icon' => 'file-text', 'active' => 'ClientReport.*', 'label' => 'تقارير العملاء'],
                ['show' => $bookingOn && hasPermission('reservation_reports.view'),
                    'route' => 'Reservations.reports.index', 'icon' => 'bar-chart', 'active' => 'Reservations.reports.*', 'label' => 'تقارير الحجوزات'],
                ['show' => $customersOn && $can('clients_edit_client_settings'),
                    'route' => 'clients.setting', 'icon' => 'settings', 'active' => 'clients.setting', 'label' => trans('main_trans.Client_settings')],
                ['show' => $bookingOn && hasPermission('reservation_settings.view'),
                    'route' => 'Reservations.settings', 'icon' => 'sliders', 'active' => 'Reservations.settings*', 'label' => 'إعدادات الحجوزات'],
            ],
        ],
    ];
@endphp

<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow menu-hide" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto"><a class="navbar-brand" href="{{ route('dashboard') }}">
                    <div class="brand-logo"></div>
                    <h2 class="brand-text mb-0">فوترة</h2>
                </a>
            </li>

            <li class="nav-item nav-toggle"><a class="nav-link modern-nav-toggle pr-0" data-toggle="collapse"><i
                        class="feather icon-x d-block d-xl-none font-medium-4 primary toggle-icon"></i><i
                        class="toggle-icon feather icon-disc font-medium-4 d-none d-xl-block collapse-toggle-icon primary"
                        data-ticon="icon-disc"></i></a></li>
        </ul>
    </div>

    <div class="shadow-bottom"></div>

    <div class="main-menu-content">

        <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">

            @foreach ($groups as $group)
                @php
                    $visible = array_filter($group['items'], fn ($item) => $item['show'] && Route::has($item['route']));
                    foreach ($visible as $key => $item) {
                        $visible[$key]['isActive'] = request()->routeIs(...explode('|', $item['active']));
                    }
                    // المجموعةُ التي فيها الشاشةُ الحالية تُفتح عند التحميل
                    $groupActive = collect($visible)->contains('isActive', true);
                @endphp

                @if ($visible)
                    <li class="nav-item has-sub cl-nav-group {{ $groupActive ? 'open sidebar-group-active' : '' }}">
                        <a href="#">
                            <i class="feather icon-{{ $group['icon'] }}"></i>
                            <span class="menu-title">{{ $group['title'] }}</span>
                        </a>
                        <ul class="menu-content">
                            @foreach ($visible as $item)
                                <li class="{{ $item['isActive'] ? 'active' : '' }}">
                                    <a href="{{ route($item['route']) }}">
                                        <i class="feather icon-{{ $item['icon'] }}"></i>
                                        <span class="menu-item">{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            @endforeach

        </ul>
    </div>
</div>

{{-- لمساتُ المجموعات: عنوانٌ أوضح، وخطٌّ جانبيّ يربط البنود بمجموعتها.
     الألوانُ من متغيّرات «مظهر النظام» (`ThemeSettings`) لا مكتوبةً هنا، فتتبع ما يختاره المدير. --}}
<style>
    .main-menu .navigation > li.cl-nav-group > a .menu-title {
        font-weight: 600;
    }
    .main-menu .navigation > li.cl-nav-group.open > a,
    .main-menu .navigation > li.cl-nav-group.sidebar-group-active > a {
        color: var(--app-primary, #7367f0);
    }
    .main-menu .navigation > li.cl-nav-group > ul.menu-content {
        border-inline-start: 2px solid color-mix(in srgb, var(--app-primary, #7367f0) 18%, transparent);
    }
</style>
