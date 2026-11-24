<meta name="csrf-token" content="{{ csrf_token() }}">
@auth
    <meta name="hr-advance-approve-url" content="{{ route('hr.advance-approvals.approve', ['id' => '__ID__']) }}">
    <meta name="hr-advance-reject-url" content="{{ route('hr.advance-approvals.reject', ['id' => '__ID__']) }}">
    {{-- خياراتُ الصرف: يسألها بوبُ الإشعار قبل عرض «اعتماد وصرف» — والخادمُ
         يردّ `can_disburse: false` لمن لا يملك الصلاحية، فلا يُخبر الرابطُ
         وحدَه عن شيء. --}}
    <meta name="hr-advance-disbursement-options-url" content="{{ route('hr.advance-approvals.disbursement_options', ['id' => '__ID__']) }}">
@endauth
<nav class="header-navbar navbar-expand-lg navbar navbar-with-menu floating-nav navbar-light navbar-shadow">
    <div class="navbar-wrapper">
        {{--
            كان اللون مكتوباً في السطر مباشرةً من `$backgroundColorr` — وهو
            «لون الخلفية» لا لون الشريط العلوي، فيخالف ما يُضبط في «مظهر
            النظام». ثم صار `var(--app-topbar-bg)` في السطر نفسه.

            وهذا أيضاً خطأ: `.navbar-container` حاويةٌ داخلية مربّعة داخل
            `.header-navbar` المستديرة الأركان وذات الحدّ. فصبغُ الاثنتين
            يُظهر الشريط طبقةً فوق طبقة.

            صار السطحُ على `.header-navbar` وحدها من ورقة الثيم، وهذه
            الحاوية شفّافة تحمل الحشوة لا اللون.
        --}}
        <div class="navbar-container content">
            <div class="navbar-collapse app-topbar" id="navbar-mobile">
                {{--
                    ══════════ ترتيب الشريط العلوي ══════════

                    كان الشريط كتلتين على أسلوب Bootstrap 4: `div.mr-auto.float-left`
                    تحمل زرَّ القائمة وأيقونةَ المهامّ، و`ul.float-right` تحمل كلَّ
                    ما بقي. و`float` داخل حاويةٍ مرِنة لا يفعل ما يُظنّ به: فتتكدّس
                    الأيقونات كلّها في طرفٍ واحد ملتصقةً ببطاقة المستخدم، وتبقى
                    أيقونةٌ وحيدة في الطرف المقابل، والفراغ كلّه في الوسط.

                    صار الشريط ثلاثَ مناطق صريحة:

                      • البداية (يمين في RTL): زرّ القائمة، ثم مجموعةُ الإجراءات
                        — المهامّ والرئيسية والأقسام وملء الشاشة والزيارات
                        والإشعارات.
                      • الوسط: فراغٌ مرِن يفصل بين المجموعتين.
                      • النهاية (يسار في RTL): اللغة، ثم فاصل، ثم بطاقةُ المستخدم
                        — وهو موضعها المتعارف عليه.

                    ولا `float` ولا `mr-auto`: المسافات من `gap` في CSS.
                --}}
                <ul class="nav navbar-nav app-topbar__start">
                    <li class="nav-item mobile-menu d-xl-none">
                        <a class="nav-link nav-menu-main menu-toggle hidden-xs" href="#" title="القائمة">
                            <i class="ficon feather icon-menu"></i>
                        </a>
                    </li>

                    <li class="nav-item d-none d-lg-block">
                        <a class="nav-link" href="{{ route('task.index') }}" data-toggle="tooltip"
                            data-placement="bottom" title="المهامّ">
                            <i class="ficon feather icon-check-square"></i>
                        </a>
                    </li>

                    <!-- Search -->
                    <li class="nav-item">
                    <a class="nav-link" href="{{ url('/') }}" data-toggle="tooltip" data-placement="top"
                    title="القائمة الرئيسية">
                    <i class="ficon feather icon-home"></i>
                    </a>
                    </li>

                    <!-- الزر الثاني: دخول -->
                    <li class="nav-item">
                    <a class="nav-link" href="#" id="showDashboardCardsBtn"
                    data-toggle="modal" data-target="#dashboardCardsModal"
                    data-bs-toggle="modal" data-bs-target="#dashboardCardsModal"
                    title="عرض جميع الخدمات">
                    <i class="ficon feather icon-grid"></i>
                    </a>
                    </li>

                    <!-- Fullscreen -->
                    <li class="nav-item d-none d-lg-block">
                    <a class="nav-link nav-link-expand">
                    <i class="ficon feather icon-maximize"></i>
                    </a>
                    </li>

                    {{-- زياراتُ اليوم: كانت خلف صلاحية `branches` بمعنى «مديرٌ ما»،
                         وهي عن الزيارات لا الفروع — وللزيارات صلاحيتُها. --}}
                    @if (hasPermission('clients_visits_track'))
                    <li class="dropdown dropdown-notification nav-item">
                    <a class="nav-link nav-link-label" href="#" data-toggle="dropdown">
                    <i class="ficon feather icon-calendar"></i>
                    <span
                    class="badge badge-pill badge-primary badge-up">{{ $todayVisits->count() }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-media dropdown-menu-right">
                    <li class="dropdown-menu-header">
                    <div class="dropdown-header m-0 p-2">
                    <h3 class="white">{{ $todayVisits->count() }} زيارة</h3>
                    <span class="notification-title">زيارات اليوم</span>
                    </div>
                    </li>
                    <li class="scrollable-container media-list">
                    @forelse($todayVisits as $visit)
                    <div class="visit-item media p-1">
                    <div class="media-left">
                    <div class="avatar bg-primary bg-lighten-4 rounded-circle">
                    <span
                    class="avatar-content">{{ substr($visit->client->trade_name, 0, 1) }}</span>
                    </div>
                    </div>
                    <div class="media-body">
                    <h6 class="media-heading text-bold-500">
                    {{ $visit->client->trade_name }}</h6>
                    <p class="mb-1">
                    <i class="feather icon-user"></i>
                    <small class="text-muted">الموظف:
                    {{ $visit->employee->name ?? 'غير معروف' }}</small>
                    </p>
                    <div class="visit-details">
                    @if ($visit->arrival_time)
                    <p class="mb-0">
                    <i class="feather icon-clock text-success"></i>
                    <span class="text-success">الوصول: </span>
                    {{ \Carbon\Carbon::parse($visit->arrival_time)->format('h:i A') }}
                    </p>
                    @endif
                    @if ($visit->departure_time)
                    <p class="mb-0">
                    <i class="feather icon-clock text-danger"></i>
                    <span class="text-danger">المغادرة: </span>
                    {{ \Carbon\Carbon::parse($visit->departure_time)->format('h:i A') }}
                    </p>
                    @else
                    <p class="mb-0 text-warning">
                    <i class="feather icon-clock"></i>
                    <span>ما زال عند العميل</span>
                    </p>
                    @endif
                    @if ($visit->notes)
                    <p class="mb-0 text-muted small">
                    <i class="feather icon-message-square"></i>
                    {{ Str::limit($visit->notes, 50) }}
                    </p>
                    @endif
                    </div>
                    </div>
                    </div>
                    @empty
                    <li class="empty-visits p-2 text-center">لا توجد زيارات اليوم</li>
                    @endforelse
                    </li>
                    <li class="dropdown-menu-footer">
                    <a class="dropdown-item p-1 text-center text-primary" href="">
                    <i class="feather icon-list align-middle"></i>
                    <span class="align-middle text-bold-600">عرض كل الزيارات</span>
                    </a>
                    </li>
                    </ul>
                    </li>
                    @endif

                    <!-- الإشعارات -->
                    @auth
                    <li class="dropdown dropdown-notification nav-item">
                    <a class="nav-link nav-link-label" href="#" data-toggle="dropdown">
                    <i class="ficon feather icon-bell"></i>
                    <span class="badge badge-pill badge-primary badge-up" id="notification-count">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-media dropdown-menu-right">
                    <li class="dropdown-menu-header">
                    <div class="dropdown-header m-0 p-2">
                    <h3 class="white" id="notification-title">إشعارات جديدة</h3>
                    <span class="notification-title">التنبيهات (آخر 24 ساعة)</span>
                    </div>
                    </li>
                    <li class="scrollable-container media-list" id="notification-list">
                    <p class="text-center p-2">لا يوجد إشعارات جديدة</p>
                    </li>
                    <li class="dropdown-menu-footer">
                    <a class="dropdown-item p-1 text-center" href="{{ route('notifications.index') }}">
                    عرض كل الإشعارات
                    </a>
                    </li>
                    </ul>
                    </li>
                    @endauth

                </ul>

                <span class="app-topbar__spacer"></span>

                <ul class="nav navbar-nav app-topbar__end">
                    @include('layouts.partials.language-switcher')

                    <li class="app-topbar__divider" aria-hidden="true"></li>

                    <!-- User Profile -->
                    <li class="dropdown dropdown-user nav-item">
                    <a class="dropdown-toggle nav-link dropdown-user-link" href="#" data-toggle="dropdown"
                    aria-expanded="false">
                    <div class="user-nav d-sm-flex d-none">
                    <span class="user-name text-bold-600">{{ auth()->user()->name ?? '' }}</span>
                    <span class="user-status">
                    متصل
                    @if (auth()->user()->branch_id)
                    - {{ auth()->user()->currentBranch->name ?? 'بدون فرع' }}
                    @elseif (\App\Support\BranchScope::canSwitchToAll())
                    - جميع الفروع
                    @else
                    {{-- بلا فرعٍ وبلا نطاقٍ شامل: يُقال صراحةً بدل «متصل» عارياً --}}
                    - بدون فرع
                    @endif
                    </span>
                    </div>
                    <span>
                    @php
                    $firstLetter = mb_substr(auth()->user()->name, 0, 1, 'UTF-8');
                    @endphp
                    <div class="profile-picture-header">{{ $firstLetter }}</div>
                    </span>
                    <i class="feather icon-chevron-down"></i>
                    </a>

                    <div class="dropdown-menu dropdown-menu-right">
                    <!-- Employee Profile Link -->
                    @if (auth()->user()->employee)
                    <a class="dropdown-item" href="{{ route('employee.profile') }}">
                    <i class="feather icon-user"></i> البروفايل الشخصي
                    </a>
                    @endif

                    <div class="dropdown-divider"></div>

                    {{--
                        ═══════════ مبدّلُ الفروع ═══════════

                        **ما كان** عطبان مستقلّان:

                          • القائمةُ كلُّها خلف `@if (! auth()->user()->isEmployee())`
                            — فمديرُ الفرع الذي يدير موظفين **لا يراها إطلاقاً**،
                            وهو أوّلُ من يحتاجها. والشرطُ يخلط «أهو موظّف؟»
                            بـ«ما نطاقُه؟» وهما سؤالان مختلفان.

                          • ومن يمرّ الشرطَ يُعرض له
                            `Branch::whereNull('parent_id')->get()` — كلُّ فروع
                            الجذر بلا أيّ نطاق. فحسابُ نظامٍ بلا صلاحيةِ نطاقٍ
                            واحدة يرى الفروعَ جميعاً ويبدّل إليها.

                        **وما صار**: [App\Support\BranchScope] وحده يقرّر —
                        وهو نفسُه الذي يحرس `switchBranch()`، فلا يُعرض فرعٌ
                        يُردّ عنه ولا يُخفى فرعٌ يُقبل الوصولُ إليه بالعنوان.

                        ولا تُعرض القائمةُ لمن له فرعٌ واحد: خيارٌ وحيدٌ يوحي
                        بخيارٍ ليس له.
                    --}}
                    @php
                        // القائمةُ تعرض الفروعَ الجذرية (parent_id فارغ) فقط — لا فروعها
                        // الفرعية داخل الشجرة — فيبقى المبدّل مختصراً بمستوى «الفروع
                        // الرئيسية» الذي تُفلتر عليه الصفحات، وحارسُ التبديل
                        // (BranchScope::canSwitchTo) لا يزال يستند إلى النطاق الكامل.
                        $switchableBranches = \App\Support\BranchScope::switchable()->whereNull('parent_id')->values();
                    @endphp

                    {{-- أُزيل خيارُ «جميع الفروع» من المبدّل، فلم يعد `shouldShowSwitcher()`
                         هو الشرطَ الصحيح: كان يفتح القائمةَ لمن يملك النطاقَ الشامل
                         ولو لم يكن أمامه إلا فرعٌ واحد، فتُعرض ترويسةٌ بلا خيارات.
                         الشرطُ الآن: أكثرُ من فرعٍ واحدٍ قابلٍ للتبديل. --}}
                    @if ($switchableBranches->count() > 1)
                    <span class="dropdown-item font-weight-bold">🔹 الفروع:</span>

                    @foreach ($switchableBranches as $branch)
                    <a class="dropdown-item branch-item {{ auth()->user()->branch_id == $branch->id ? 'active bg-light' : '' }}"
                    href="{{ route('branch.switch', $branch->id) }}"
                    data-branch-id="{{ $branch->id }}"
                    onclick="handleBranchSwitch(event, {{ $branch->id }})">
                    <i class="feather icon-map-pin"></i> {{ $branch->name }}
                    @if (auth()->user()->branch_id == $branch->id)
                    <i class="feather icon-check text-success float-left"></i>
                    @endif
                    </a>
                    @endforeach
                    @endif
                    <div class="dropdown-divider"></div>

                    <!-- تسجيل الخروج -->
                    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="dropdown-item">
                    <i class="feather icon-power"></i> تسجيل خروج
                    </button>
                    </form>
                    </div>
                    </li>

                </ul>
            </div>
        </div>
    </div>
</nav>

{{-- شريط التحديث المعلَّق — تفصيله في القالب نفسه --}}
@include('layouts.partials.system-update-banner')

<!-- نافذة الرد المنبثقة -->
@auth
    <div class="modal fade" id="replyModal" tabindex="-1" role="dialog" aria-labelledby="replyModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white" id="replyModalLabel">
                        <i class="feather icon-corner-up-right"></i> الرد على الإشعار
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- الإشعار الأصلي -->
                    <div class="alert alert-light border mb-3">
                        <h6 class="alert-heading">
                            <i class="feather icon-mail"></i> الإشعار الأصلي
                        </h6>
                        <p class="mb-1"><strong>العنوان:</strong> <span id="originalNotificationTitle"></span></p>
                        <p class="mb-0"><strong>من:</strong> <span id="originalSender"></span></p>
                    </div>

                    <!-- نموذج الرد -->
                    <form id="replyNotificationForm">
                        @csrf
                        <input type="hidden" id="reply_notification_id" name="notification_id">

                        <div class="form-group">
                            <label for="reply_message">
                                <i class="feather icon-message-square"></i> نص الرد
                            </label>
                            <textarea class="form-control" id="reply_message" name="reply_message" rows="4" placeholder="اكتب ردك هنا..."
                                required maxlength="500"></textarea>
                            <small class="form-text text-muted">
                                <span id="charCount">0</span>/500 حرف
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x"></i> إلغاء
                    </button>
                    <button type="button" class="btn btn-primary" id="sendReplyBtn">
                        <i class="feather icon-send"></i> إرسال الرد
                    </button>
                </div>
            </div>
        </div>
    </div>
@endauth

<!-- Dashboard Cards Modal -->
<div class="modal fade" id="dashboardCardsModal" tabindex="-1" role="dialog"
    aria-labelledby="dashboardCardsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered mod-dash-dialog" role="document">
        <div class="modal-content mod-dash-modal">
            <div class="mod-dash-header">
                <div class="mod-dash-header__content">
                    <div class="mod-dash-header__icon">
                        <i class="feather icon-grid"></i>
                    </div>
                    <div class="mod-dash-header__text">
                        <h5 id="dashboardCardsModalLabel">جميع الأقسام والخدمات</h5>
                        <p>اختر القسم الذي تريد الانتقال إليه</p>
                    </div>
                </div>
                <button type="button" class="mod-dash-header__close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                    <i class="feather icon-x"></i>
                </button>
            </div>
            <div class="modal-body mod-dash-body">
                <div id="dashboardCardsContainer">
                    <div class="svc-tiles-grid">
        <!-- إدارة المبيعات -->
        @canany([
    'sales.invoices.view', 'sales.invoices.create', 'sales.invoices.edit', 'sales.invoices.delete',
    'sales.payments.view', 'sales.payments.create', 'sales.payments.edit', 'sales.payments.delete',
    'sales.credit_notes.view', 'sales.credit_notes.create', 'sales.credit_notes.edit', 'sales.credit_notes.delete',
    'sales.quotes.view', 'sales.quotes.create', 'sales.quotes.edit', 'sales.quotes.delete',
    'sales.dashboard.view'
])
            <a href="{{ route('sales.department.dashboard') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--primary">
                    <div class="svc-tile__icon"><i class="feather icon-shopping-cart"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">إدارة المبيعات</h4>
                        <p class="svc-tile__desc">مبيعات - فواتير</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endcanany

        <!-- إدارة العملاء -->
        @canany(['clients_view_all_clients', 'clients_add_client', 'client_follow_up_add_notes_attachments_appointments_all', 'clients_edit_client_settings', 'customer_attendance_display'])
            <a href="{{ route('client.department.dashboard') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--info">
                    <div class="svc-tile__icon"><i class="feather icon-users"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">إدارة العملاء</h4>
                        <p class="svc-tile__desc">عملاء - ولاء</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endcanany

        <!-- المتجر الإلكتروني -->
        @can('online_store_content_management')
            <a href="{{ route('onlinestore.department.dashboard') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--success">
                    <div class="svc-tile__icon"><i class="feather icon-globe"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">المتجر الإلكتروني</h4>
                        <p class="svc-tile__desc">طلبات أونلاين</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endcan

        <!-- إدارة العقارات -->
        @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\RealEstatePermissions::PREFIX) || canAny(['rental_unit_view_booking_orders', 'rental_unit_manage_booking_orders', 'rental_unit_manage_rental_settings']))
            <a href="{{ route('rentalmanagement.department.dashboard') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--primary">
                    <div class="svc-tile__icon"><i class="feather icon-home"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">إدارة العقارات</h4>
                        <p class="svc-tile__desc">مبانٍ - وحدات - عقود - دفعات - صيانة</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endif

        <!-- إدارة المخزون -->
        @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\StockPermissions::PREFIX) || auth()->user()->hasRolePermission('online_store_content_management'))
            <a href="{{ route('dashboard.stock') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--danger">
                    <div class="svc-tile__icon"><i class="feather icon-package"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">إدارة المخزون</h4>
                        <p class="svc-tile__desc">مخزون - حركة</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endif

        {{--
            الأقسام التي كانت غائبة عن هذه البطاقة رغم وجود شاشاتها.

            البطاقة تَعِد بأنها «جميع الخدمات» — والزرّ الذي يفتحها عنوانه
            كذلك — بينما كانت تعرض إحدى عشرة بطاقةً من ثمانيةٍ وعشرين تطبيقاً
            مسجَّلاً. فمن يبحث عن نقاط البيع أو الحجوزات أو الفروع لا يجدها
            هنا ولا يعرف أنها موجودة أصلاً.

            كلّ بطاقةٍ تُعرَض بشرطين: أن يكون مسارها معرَّفاً فعلاً، وأن
            يملك المستخدم صلاحيةً تخصّها — فلا يُعرض ما لا يُفتح.
        --}}

        {{--
            التصنيع — وشرطٌ ثالثٌ مع الشرطين أعلاه: مفتاحُ شاشة «التطبيقات».

            المسارُ معرَّفٌ والصلاحيةُ مملوكة، ومع ذلك تردّ الوحدةُ المقفلةُ
            زائرَها إلى الرئيسية. فبطاقةٌ محروسةٌ بالشرطين وحدهما تُخلف وعدَ
            التعليق أعلاه: تَعرِض ما لا يُفتح.
        --}}
        <!-- التصنيع -->
        @canany(['stock.manufacturing_orders.view', 'stock.bom.view', 'stock.workstations.view'])
            @if (\Illuminate\Support\Facades\Route::has('manufacturing.dashboard')
                && \App\Services\Settings\ApplicationRegistry::isAvailable('manufacturing'))
                <a href="{{ route('manufacturing.dashboard') }}" class="svc-tile-link">
                    <article class="svc-tile svc-tile--warning">
                        <div class="svc-tile__icon"><i class="feather icon-layers"></i></div>
                        <div class="svc-tile__content">
                            <h4 class="svc-tile__title">التصنيع</h4>
                            <p class="svc-tile__desc">أوامر إنتاج - قوائم مواد</p>
                        </div>
                        <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                    </article>
                </a>
            @endif
        @endcanany

        <!-- نقاط البيع -->
        @canany(['pos_manage_settings', 'pos_view_reports'])
            @if (\Illuminate\Support\Facades\Route::has('dashboard.pos'))
                <a href="{{ route('dashboard.pos') }}" class="svc-tile-link">
                    <article class="svc-tile svc-tile--success">
                        <div class="svc-tile__icon"><i class="feather icon-monitor"></i></div>
                        <div class="svc-tile__content">
                            <h4 class="svc-tile__title">نقاط البيع</h4>
                            <p class="svc-tile__desc">جلسات - أجهزة - ورديات</p>
                        </div>
                        <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                    </article>
                </a>
            @endif
        @endcanany

        <!-- الفروع -->
        {{-- `branches` صلاحيةٌ قديمة، وكتالوجُ الفروع يعرّف `branches.view`.
             وقبولُ الاثنتين يُبقي وصولَ من يملك القديمة ويفتحه لمن مُنح الجديدة. --}}
        @canany(['branches.view', 'branches'])
            @if (\Illuminate\Support\Facades\Route::has('branches.index'))
                @php
                    $branchesTileHome = hasPermission('branches.dashboard') && \Illuminate\Support\Facades\Route::has('branches.dashboard')
                        ? route('branches.dashboard')
                        : route('branches.index');
                @endphp
                <a href="{{ $branchesTileHome }}" class="svc-tile-link">
                    <article class="svc-tile svc-tile--primary">
                        <div class="svc-tile__icon"><i class="feather icon-git-branch"></i></div>
                        <div class="svc-tile__content">
                            <h4 class="svc-tile__title">الهيكل التنظيمي</h4>
                            <p class="svc-tile__desc">المناطق والفروع والمجموعات والمنافذ</p>
                        </div>
                        <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                    </article>
                </a>
            @endif
        @endcan

        {{--
            بطاقةُ «التقارير» أُسقطت.

            كانت تسمّي نفسها «التقارير» وتفتح تقاريرَ المخزون وحدها
            (`StorHouseReport.index`) — فاسمُها يَعِد بما لا تعطي. ولكلّ
            قسمٍ اليومَ تقاريرُه داخل بطاقته: المبيعات والمشتريات والعملاء
            والمخزون والحجوزات.

            وكانت بلا حارسِ صلاحيةٍ أصلاً: من لا يملك تقارير المخزون يراها
            ويضغطها فيُردّ.
        --}}

        <!-- إدارة المشتريات — أيُّ صلاحيةِ مشترياتٍ واحدة تكفي، كبقيّة
             البنود. كان تعداداً لخمس صلاحيات «عرض» فقط، فمن مُنح الإضافةَ
             أو التعديلَ دون العرض لم يرَ للمشتريات أثراً في الهيدر. -->
        @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\PurchasesPermissions::PREFIX))
            <a href="{{ route('dashboard.purchases') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--info">
                    <div class="svc-tile__icon"><i class="feather icon-shopping-bag"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">إدارة المشتريات</h4>
                        <p class="svc-tile__desc">فواتير شراء</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endif

        <!-- المالية والحسابات العامة -->
        @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\AccountPermissions::PREFIX))
            <a href="{{ route('account.department.dashboard') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--info">
                    <div class="svc-tile__icon"><i class="feather icon-dollar-sign"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">المالية والحسابات</h4>
                        <p class="svc-tile__desc">قيود - حسابات</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endif

        <!-- إدارة المهام -->
        @canany(['tasks_view_tasks'])
            <a href="{{ route('tasks.dashboard') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--secondary">
                    <div class="svc-tile__icon"><i class="feather icon-check-square"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">إدارة المهام</h4>
                        <p class="svc-tile__desc">المتابعة - الإنجاز</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endcanany

        <!-- إدارة الموارد البشرية -->
        {{--
            بطاقتان تفتحان `hr.dashboard.index`، والمسار محروسٌ بـ
            `permission.role:hr_dashboard.index`. وكان حارسُ الثانية
            `@if(employee_id)` وحدَه — بلا صلاحية — فيراها كلُّ موظفٍ أيّاً
            كان دورُه ثمّ يُردّ. اللفُّ بحارس المسار نفسِه؛ والقائمةُ الداخلية
            تختار العنوانَ وحده (إدارةٌ أم لوحةُ أداء).
        --}}
        @if (hasPermission('hr_dashboard.index'))
        @if (canAny([
    'employees.index',
    'employees.create',
    'departments.index',
    'attendance_books.index',
    'leave_requests.approve',
    'attendance_settings.manage',
    'contracts.create',
    'payroll.index',
    'payroll.create',
    'loans.index',
    'payroll_settings.manage',
]))
            <a href="{{ route('hr.dashboard.index') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--success">
                    <div class="svc-tile__icon"><i class="feather icon-users"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">إدارة الموارد البشرية</h4>
                        <p class="svc-tile__desc">موظفين - رواتب</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @else
            <a href="{{ route('hr.dashboard.index') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--success">
                    <div class="svc-tile__icon"><i class="feather icon-trending-up"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">لوحة أداء الموظفين</h4>
                        <p class="svc-tile__desc">مبيعات وتحصيلات الفريق</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endif
        @endif

        <!-- الإعدادات العامة -->
        @canany(['settings_edit_general_settings', 'settings_edit_tax_settings'])
            <a href="{{ route('settings.department.dashboard') }}" class="svc-tile-link">
                <article class="svc-tile svc-tile--dark">
                    <div class="svc-tile__icon"><i class="feather icon-settings"></i></div>
                    <div class="svc-tile__content">
                        <h4 class="svc-tile__title">الإعدادات العامة</h4>
                        <p class="svc-tile__desc">المستخدمين - النظام</p>
                    </div>
                    <span class="svc-tile__arrow"><i class="feather icon-chevron-left"></i></span>
                </article>
            </a>
        @endcanany
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .notification-item {
        transition: all 0.3s ease;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
    }

    .notification-sender,
    .notification-receiver {
        padding: 2px 0;
    }

    .notification-sender i,
    .notification-receiver i {
        font-size: 12px;
        margin-left: 3px;
    }

    .notification-item .media-body {
        padding-left: 10px;
    }

    .reply-notification-btn {
        padding: 4px 12px;
        font-size: 12px;
        transition: all 0.2s ease;
    }

    .reply-notification-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .reply-notification-btn i {
        font-size: 14px;
        margin-left: 3px;
    }

    /* منطقة الرد داخل الـ Dropdown */
    .reply-section {
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        margin-top: 10px;
        border: 1px solid #e3e6f0;
    }

    .reply-textarea {
        resize: vertical;
        min-height: 60px;
        font-size: 13px;
        border: 1px solid #d1d3e2;
    }

    .reply-textarea:focus {
        border-color: #7367f0;
        box-shadow: 0 0 0 0.2rem rgba(115, 103, 240, 0.25);
    }

    .char-counter {
        font-size: 11px;
    }

    .char-counter.text-danger {
        font-weight: bold;
    }

    .send-reply-btn,
    .cancel-reply-btn {
        font-size: 12px;
        padding: 4px 12px;
    }

    .send-reply-btn i,
    .cancel-reply-btn i {
        font-size: 13px;
        margin-left: 3px;
    }

    .spinner {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    #replyModal .modal-header {
        border-bottom: 0;
    }

    #replyModal .modal-body {
        padding: 1.5rem;
    }

    #replyModal textarea {
        resize: vertical;
        min-height: 100px;
    }

    #charCount.text-danger {
        font-weight: bold;
    }

    /* نافذة الأقسام — نفس هيكل dashboard.blade.php */
    #dashboardCardsModal .modal-backdrop.show {
        backdrop-filter: blur(3px);
    }

    #dashboardCardsModal .mod-dash-dialog {
        max-width: 1100px !important;
        width: 92% !important;
        margin: 1.75rem auto !important;
    }

    #dashboardCardsModal .mod-dash-modal {
        border: none !important;
        border-radius: 18px !important;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(115, 103, 240, 0.15) !important;
    }

    #dashboardCardsModal .mod-dash-header {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.35rem 1.75rem;
        background: linear-gradient(135deg, var(--app-primary, #7367F0) 0%, color-mix(in srgb, var(--app-primary, #7367F0) 78%, #fff) 100%);
        border-bottom: none;
    }

    #dashboardCardsModal .mod-dash-header__content {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    #dashboardCardsModal .mod-dash-header__icon {
        flex-shrink: 0;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        color: #fff;
    }

    #dashboardCardsModal .mod-dash-header__icon i {
        font-size: 1.35rem;
    }

    #dashboardCardsModal .mod-dash-header__text h5 {
        margin: 0 0 0.25rem;
        font-size: 1.2rem;
        font-weight: 800;
        color: #fff;
    }

    #dashboardCardsModal .mod-dash-header__text p {
        margin: 0;
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.9);
    }

    #dashboardCardsModal .mod-dash-header__close {
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, 0.35);
        background: rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        color: #fff;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    #dashboardCardsModal .mod-dash-header__close:hover {
        background: rgba(255, 255, 255, 0.3);
        color: #fff;
    }

    #dashboardCardsModal .mod-dash-body {
        max-height: 70vh;
        overflow-y: auto;
        padding: 1.5rem !important;
        background: #f4f5fa !important;
    }

    #dashboardCardsModal .mod-dash-body::-webkit-scrollbar {
        width: 6px;
    }

    #dashboardCardsModal .mod-dash-body::-webkit-scrollbar-thumb {
        background: var(--app-primary, #7367F0);
        border-radius: 6px;
    }

    /* شبكة كروت الأقسام */
    #dashboardCardsModal .svc-tiles-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.25rem;
        width: 100%;
    }

    #dashboardCardsModal .svc-tile-link {
        display: block;
        text-decoration: none;
        color: inherit;
        height: 100%;
    }

    #dashboardCardsModal .svc-tile {
        --svc-color: #7367F0;
        --svc-bg: rgba(115, 103, 240, 0.12);
        display: flex;
        align-items: center;
        gap: 1rem;
        width: 100%;
        min-height: 108px;
        padding: 1.15rem 1.25rem;
        margin: 0;
        box-sizing: border-box;
        background: #fff;
        border: 1px solid #e8e6f0;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(30, 30, 60, 0.04);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    #dashboardCardsModal .svc-tile::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 4px;
        height: 100%;
        background: var(--svc-color);
        opacity: 0;
        transition: opacity 0.25s ease;
    }

    #dashboardCardsModal .svc-tile-link:hover .svc-tile {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(115, 103, 240, 0.12);
        border-color: #d5d0f7;
    }

    #dashboardCardsModal .svc-tile-link:hover .svc-tile::before {
        opacity: 1;
    }

    #dashboardCardsModal .svc-tile__icon {
        flex-shrink: 0;
        width: 52px;
        height: 52px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 13px;
        background: var(--svc-bg);
        color: var(--svc-color);
        font-size: 1.4rem;
        transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease;
    }

    #dashboardCardsModal .svc-tile-link:hover .svc-tile__icon {
        background: var(--svc-color);
        color: #fff;
        transform: scale(1.05);
    }

    #dashboardCardsModal .svc-tile__content {
        flex: 1;
        min-width: 0;
    }

    #dashboardCardsModal .svc-tile__title {
        margin: 0 0 0.35rem;
        font-size: 1rem;
        font-weight: 700;
        color: #2a2a2e;
        line-height: 1.35;
    }

    #dashboardCardsModal .svc-tile__desc {
        margin: 0;
        font-size: 0.8125rem;
        color: #8b8b9a;
        line-height: 1.4;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #dashboardCardsModal .svc-tile__arrow {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: #f4f3fb;
        color: #b0adc4;
        font-size: 1rem;
        opacity: 0;
        transform: translateX(6px);
        transition: opacity 0.25s ease, transform 0.25s ease, background 0.25s ease, color 0.25s ease;
    }

    #dashboardCardsModal .svc-tile-link:hover .svc-tile__arrow {
        opacity: 1;
        transform: translateX(0);
        background: var(--svc-bg);
        color: var(--svc-color);
    }

    #dashboardCardsModal .svc-tile--primary { --svc-color: #7367F0; --svc-bg: rgba(115, 103, 240, 0.12); }
    #dashboardCardsModal .svc-tile--info { --svc-color: #00CFE8; --svc-bg: rgba(0, 207, 232, 0.12); }
    #dashboardCardsModal .svc-tile--success { --svc-color: #28C76F; --svc-bg: rgba(40, 199, 111, 0.12); }
    #dashboardCardsModal .svc-tile--warning { --svc-color: #FF9F43; --svc-bg: rgba(255, 159, 67, 0.14); }
    #dashboardCardsModal .svc-tile--danger { --svc-color: #EA5455; --svc-bg: rgba(234, 84, 85, 0.12); }
    #dashboardCardsModal .svc-tile--secondary { --svc-color: #82868B; --svc-bg: rgba(130, 134, 139, 0.12); }
    #dashboardCardsModal .svc-tile--dark { --svc-color: #4B4B4B; --svc-bg: rgba(75, 75, 75, 0.1); }

    @media (max-width: 991px) {
        #dashboardCardsModal .svc-tiles-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
    }

    @media (max-width: 575px) {
        #dashboardCardsModal .mod-dash-dialog {
            width: calc(100% - 1rem) !important;
            margin: 0.5rem auto !important;
        }

        #dashboardCardsModal .mod-dash-modal {
            border-radius: 14px !important;
        }

        #dashboardCardsModal .mod-dash-header {
            padding: 1rem 1.25rem;
        }

        #dashboardCardsModal .mod-dash-body {
            padding: 1rem !important;
        }

        #dashboardCardsModal .svc-tiles-grid {
            grid-template-columns: 1fr;
            gap: 0.85rem;
        }

        #dashboardCardsModal .svc-tile {
            min-height: 96px;
            padding: 1rem;
        }

        #dashboardCardsModal .svc-tile__icon {
            width: 46px;
            height: 46px;
            font-size: 1.2rem;
        }

        #dashboardCardsModal .svc-tile__arrow {
            opacity: 1;
            transform: none;
        }
    }

    [dir="rtl"] #dashboardCardsModal .svc-tiles-grid {
        direction: rtl;
    }

    [dir="rtl"] #dashboardCardsModal .svc-tile::before {
        right: auto;
        left: 0;
    }

    [dir="rtl"] #dashboardCardsModal .svc-tile__content {
        text-align: right;
    }

    [dir="ltr"] #dashboardCardsModal .svc-tile__arrow i {
        transform: rotate(180deg);
    }
</style>

<script>
    $(document).ready(function() {

        // دالة تنسيق وقت الإشعار
        function formatNotificationTime(dateTime) {
            const now = new Date();
            const notificationDate = new Date(dateTime);
            const diffInSeconds = Math.floor((now - notificationDate) / 1000);

            if (diffInSeconds < 60) {
                return 'منذ لحظات';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `منذ ${minutes} ${minutes === 1 ? 'دقيقة' : 'دقائق'}`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `منذ ${hours} ${hours === 1 ? 'ساعة' : 'ساعات'}`;
            } else {
                const days = Math.floor(diffInSeconds / 86400);
                return `منذ ${days} ${days === 1 ? 'يوم' : 'أيام'}`;
            }
        }

        // ========================================
        // جلب الإشعارات - ⭐ آخر 24 ساعة فقط
        // ========================================
        /* كان `/sales/notifications/{id}/reply-form` مكتوباً باليد — ولا مسار
           بهذا الشكل (المسار `notifications/reply-form/{id}` تحت بادئة اللغة)،
           فكان الرد من الجرس ينتهي بـ404. */
        const replyFormUrl = @json(route('notifications.reply.form', ['id' => '__ID__']));
        const replyAjaxUrl = @json(route('notifications.reply', ['id' => '__ID__']));

        function escapeNotificationText(text) {
            return $('<div>').text(text == null ? '' : String(text)).html().replace(/"/g, '&quot;');
        }

        /* زرُّ «إرسال الرد» في نافذة `#replyModal` — كانت النافذةُ بلا مستمعٍ
           لزرّها، فتُفتح من شاشة الإشعار ولا ترسل شيئاً. */
        $(document).on('click', '#sendReplyBtn', function () {
            const $button = $(this);
            const id = $('#reply_notification_id').val();
            const message = $.trim($('#reply_message').val());

            if (!id || !message) {
                $('#reply_message').focus();
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: replyAjaxUrl.replace('__ID__', id),
                method: 'POST',
                data: { reply_message: message, _token: $('meta[name="csrf-token"]').attr('content') },
            })
                .done(function (response) {
                    $('#replyModal').modal('hide');
                    $('#reply_message').val('');
                    $('#charCount').text('0');

                    if (window.Swal) {
                        Swal.fire({ icon: 'success', text: (response && response.message) || 'تم إرسال الرد', toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
                    }

                    fetchNotifications();
                    $(document).trigger('notification:replied');
                })
                .fail(function (xhr) {
                    const text = (xhr.responseJSON && xhr.responseJSON.message) || 'تعذّر إرسال الرد';

                    if (window.Swal) {
                        Swal.fire({ icon: 'error', text: text });
                    } else {
                        alert(text);
                    }
                })
                .always(function () {
                    $button.prop('disabled', false);
                });
        });

        // شاشةٌ غيّرت حالة إشعار (قراءة، حذف) تطلب تحديث الجرس
        $(document).on('notification:changed', function () { fetchNotifications(); });

        function fetchNotifications() {

            $.ajax({
                url: "{{ route('notifications.unread') }}",
                method: "GET",
                beforeSend: function() {
                    console.log('📤 إرسال الطلب...');
                },
                success: function(response) {
                console.log('✅ البيانات المستلمة:', response);

                    if (response.success) {
                        let notifications = response.notifications || [];
                        // العدُّ من الخادم: القائمة تحمل أحدث عشرين فقط
                        let count = (typeof response.count === 'number') ? response.count : notifications.length;

                        console.log('📊 عدد الإشعارات (آخر 24 ساعة):', count);

                        // تحديث العداد
                        $('#notification-count').text(count);

                        // تحديث العنوان
                        $('#notification-title').text(
                            count > 0 ?
                            count + (count === 1 ? " إشعار جديد" : " إشعارات جديدة") :
                            "لا توجد إشعارات جديدة"
                        );

                        let notificationList = $('#notification-list');
                        notificationList.empty();

                        if (notifications.length > 0) {
                            notifications.forEach(raw => {
                                let timeAgo = formatNotificationTime(raw.created_at);

                                /* النصوص تُهرَّب قبل إدراجها: عنوانُ الإشعار ووصفُه
                                   يكتبهما المستخدمون، وكانا يُدرجان HTML كما هما —
                                   وعلامةُ `<` أو `"` في العنوان تكسر القائمة كلَّها.
                                   والوصفُ يُقصَّر: الكاملُ في شاشة الإشعارات. */
                                let description = raw.description || '';
                                let notification = Object.assign({}, raw, {
                                    title: escapeNotificationText(raw.title || 'بدون عنوان'),
                                    sender: raw.sender ? escapeNotificationText(raw.sender) : raw.sender,
                                    receiver: raw.receiver ? escapeNotificationText(raw.receiver) : raw.receiver,
                                    description: escapeNotificationText(
                                        description.length > 140 ? description.substring(0, 140) + '…' : description
                                    ),
                                });

                                // معلومات المُرسل
                                let senderInfo = notification.sender ? `
                                <div class="notification-sender mb-1">
                                    <small class="text-muted">
                                        <i class="feather icon-user"></i>
                                        <strong>من:</strong> ${notification.sender}
                                    </small>
                                </div>
                            ` : '';

                                // معلومات المُستقبِل
                                let receiverInfo = (notification.receiver && notification.receiver !== 'الجميع') ? `
                                <div class="notification-receiver mb-1">
                                    <small class="text-muted">
                                        <i class="feather icon-arrow-left"></i>
                                        <strong>إلى:</strong> ${notification.receiver}
                                    </small>
                                </div>
                            ` : '';

                                // ⭐ زر الرد - يظهر للجميع دائماً
                                let replyButton = `
                                <button class="btn btn-sm btn-outline-primary mt-2 reply-notification-btn"
                                    data-notification-id="${notification.id}"
                                    data-sender-id="${notification.sender_id || ''}"
                                    data-sender-name="${notification.sender || 'غير معروف'}"
                                    data-title="${notification.title}">
                                    <i class="feather icon-corner-up-right"></i> رد
                                </button>
                            `;

                                // ⭐ منطقة الرد - تظهر للجميع (استخدام form عادي بدلاً من AJAX)
                                let replySection = `
                                <div class="reply-section mt-2" id="reply-section-${notification.id}" style="display: none;">
                                    <form action="${replyFormUrl.replace('__ID__', notification.id)}" method="POST" class="reply-form">
                                        <input type="hidden" name="_token" value="${$('meta[name=csrf-token]').attr('content')}">
                                        <input type="hidden" name="redirect_url" value="${window.location.pathname}">
                                        <textarea
                                            class="form-control form-control-sm reply-textarea"
                                            name="reply_message"
                                            id="reply-textarea-${notification.id}"
                                            rows="2"
                                            placeholder="اكتب ردك هنا..."
                                            maxlength="500"
                                            required
                                        ></textarea>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted">
                                                <span class="char-counter" id="char-counter-${notification.id}">0</span>/500
                                            </small>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-secondary cancel-reply-btn" data-notification-id="${notification.id}">
                                                    <i class="feather icon-x"></i> إلغاء
                                                </button>
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="feather icon-send"></i> إرسال
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            `;

                                // تحديد اللون
                                let iconColor = 'primary';
                                if (notification.is_sender) {
                                    iconColor = 'success';
                                } else if (notification.is_receiver) {
                                    iconColor = 'warning';
                                }

                                let listItem = `
                                <div class="notification-item" data-notification-id="${notification.id}">
                                    <div class="media d-flex align-items-start w-100 p-2">
                                        <div class="media-left">
                                            <i class="feather icon-bell font-medium-5 ${iconColor}"></i>
                                        </div>
                                        <div class="media-body">
                                            <h6 class="${iconColor} media-heading mb-1">
                                                ${notification.title}
                                            </h6>
                                            ${senderInfo}
                                            ${receiverInfo}
                                            <p class="notification-text mb-1">${notification.description}</p>
                                            <small class="text-muted">
                                                <i class="feather icon-clock"></i> ${timeAgo}
                                            </small>
                                            ${replyButton}
                                            ${replySection}
                                        </div>
                                    </div>
                                    <hr class="my-1">
                                </div>
                            `;
                                notificationList.append(listItem);
                            });

                            if (count > notifications.length) {
                                notificationList.append(
                                    `<p class="text-center p-1 mb-0 text-muted"><small>و${count - notifications.length} إشعاراً آخر — اضغط «عرض كل الإشعارات»</small></p>`
                                );
                            }
                        } else {
                            notificationList.html(
                                '<p class="text-center p-2 text-muted">لا توجد إشعارات جديدة (آخر 24 ساعة)</p>'
                            );
                        }
                    } else {
                        console.error('❌ خطأ في الاستجابة');
                        $('#notification-list').html(
                            '<p class="text-center p-2 text-danger">حدث خطأ في جلب الإشعارات</p>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ خطأ AJAX:', {
                        status: status,
                        error: error,
                        statusCode: xhr.status,
                        response: xhr.responseText
                    });

                    let errorMsg = 'فشل الاتصال بالخادم';
                    if (xhr.status === 404) {
                        errorMsg = 'الرابط غير موجود (404)';
                    } else if (xhr.status === 500) {
                        errorMsg = 'خطأ في الخادم (500)';
                    } else if (xhr.status === 401) {
                        errorMsg = 'غير مصرح (401)';
                    }

                    $('#notification-list').html(
                        `<p class="text-center p-2 text-danger">${errorMsg}</p>`);
                }
            });
        }

        // ========================================
        // فتح منطقة الرد داخل الـ Dropdown
        // ========================================
        $(document).on('click', '.reply-notification-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            let notificationId = $(this).data('notification-id');

            // إخفاء جميع مناطق الرد الأخرى
            $('.reply-section').slideUp(200);

            // إظهار منطقة الرد لهذا الإشعار
            $(`#reply-section-${notificationId}`).slideDown(200);

            // التركيز على textarea
            setTimeout(function() {
                $(`#reply-textarea-${notificationId}`).focus();
            }, 250);
        });

        // ========================================
        // إلغاء الرد
        // ========================================
        $(document).on('click', '.cancel-reply-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            let notificationId = $(this).data('notification-id');
            $(`#reply-section-${notificationId}`).slideUp(200);
            $(`#reply-textarea-${notificationId}`).val('');
            $(`#char-counter-${notificationId}`).text('0');
        });

        // ========================================
        // عداد الأحرف للرد في الـ Dropdown
        // ========================================
        $(document).on('input', '.reply-textarea', function() {
            let notificationId = $(this).attr('id').split('-')[2];
            let length = $(this).val().length;
            $(`#char-counter-${notificationId}`).text(length);

            if (length > 450) {
                $(`#char-counter-${notificationId}`).addClass('text-danger');
            } else {
                $(`#char-counter-${notificationId}`).removeClass('text-danger');
            }
        });

        // ========================================
        // لا حاجة لكود AJAX - نستخدم form submission مباشر
        // Form يعمل تلقائياً ويرسل البيانات للسيرفر
        // السيرفر يعمل redirect بعد النجاح
        // ========================================

                    // ========================================
                    // عداد الأحرف للنافذة المنبثقة
                    // ========================================
                    $('#reply_message').on('input', function() {
                        let length = $(this).val().length;
                        $('#charCount').text(length);

                        if (length > 450) {
                            $('#charCount').addClass('text-danger');
                        } else {
                            $('#charCount').removeClass('text-danger');
                        }
                    });

                    // ========================================
                    // التحميل الأولي والتحديث التلقائي
                    // ========================================
                    fetchNotifications();
                    setInterval(fetchNotifications, 60000); // كل دقيقة

                    // ========================================
                    // جلب الزيارات اليومية
                    // ========================================
                    function formatVisitTime(dateTime) {
                        try {
                            const now = new Date();
                            const visitDate = new Date(dateTime);
                            const diffInSeconds = Math.floor((now - visitDate) / 1000);

                            if (diffInSeconds < 60) return 'الآن';
                            if (diffInSeconds < 3600) return `منذ ${Math.floor(diffInSeconds / 60)} دقيقة`;
                            if (diffInSeconds < 86400) return `منذ ${Math.floor(diffInSeconds / 3600)} ساعة`;
                            return `منذ ${Math.floor(diffInSeconds / 86400)} يوم`;
                        } catch (e) {
                            console.error('Error formatting time:', e);
                            return '--';
                        }
                    }

                    @if (hasPermission('clients_visits_track'))
                        function fetchTodayVisits() {
                            $.ajax({
                                url: "{{ route('visits.today') }}",
                                method: "GET",
                                success: function(response) {
                                    let visits = response.visits || [];
                                    let count = response.count || 0;

                                    $('#visits-count').text(count);
                                    $('#visits-title').text(count + ' زيارة');

                                    let visitsList = $('#visits-list');
                                    visitsList.empty();

                                    if (count > 0) {
                                        visits.forEach(visit => {
                                            let timeAgo = formatVisitTime(visit.created_at);
                                            visitsList.append(`
                        <div class="media d-flex align-items-start px-2 py-1">
                            <div class="media-left">
                                <i class="feather icon-user font-medium-5 primary"></i>
                            </div>
                            <div class="media-body">
                                <h6 class="primary media-heading mb-0">${visit.client_name}</h6>
                                <small class="text-muted d-block">الموظف: ${visit.employee_name}</small>
                                <small class="text-muted d-block">الوصول: ${visit.arrival_time} | الانصراف: ${visit.departure_time}</small>
                                <small class="text-muted"><i class="far fa-clock"></i> ${timeAgo}</small>
                            </div>
                        </div>
                        <hr class="my-1">
                    `);
                                        });
                                    } else {
                                        visitsList.append(
                                            '<p class="text-center p-2">لا توجد زيارات اليوم</p>');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('Error fetching visits:', error);
                                    $('#visits-list').html(
                                        '<p class="text-center p-2 text-danger">حدث خطأ أثناء جلب البيانات</p>'
                                    );
                                }
                            });
                        }

                        fetchTodayVisits();
                        setInterval(fetchTodayVisits, 60000);
                    @endif

                });

    // ========================================
    // التعامل مع تغيير الفرع وتحديث صفحة خطوط السير
    // ========================================
    function handleBranchSwitch(event, branchId) {
        // Check if we're on the itinerary list page
        const currentPath = window.location.pathname;

        if (currentPath.includes('/itinerary') || currentPath.includes('client/itinerary') || currentPath.includes('customer-balances') || currentPath.includes('CustomerReport')) {
            event.preventDefault();

            // Show loading overlay if available
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.add('active');
            }

            // Make AJAX request to switch branch first
            $.ajax({
                url: event.currentTarget.href,
                method: 'GET',
                success: function(response) {
                    // After switching branch, reload the page with new branch context
                    const currentUrl = new URL(window.location.href);

                    // Always clear specific branch parameters to let the session branch take over
                    currentUrl.searchParams.delete('branch');
                    currentUrl.searchParams.delete('branch_id');

                    // If we want to explicitly set it (optional, session is enough usually)
                    // if (branchId != 0) { currentUrl.searchParams.set('branch', branchId); }

                    // Reload the page
                    window.location.href = currentUrl.toString();
                },
                error: function(xhr, status, error) {
                    console.error('Error switching branch:', error);
                    if (loadingOverlay) { loadingOverlay.classList.remove('active'); }
                    if (typeof toastr !== 'undefined') { toastr.error('حدث خطأ أثناء تغيير الفرع'); }
                    else { alert('حدث خطأ أثناء تغيير الفرع'); }
                }
            });
        }
        // For other pages, let the default link behavior happen
    }
</script>

@php
    $notificationSystemVersion = (@filemtime(public_path('js/notification-system.js')) ?: time()) . '-v5';
@endphp
<!-- تحميل نظام الإشعارات بعد SweetAlert2 (مع version لكسر الكاش على الدومين الرئيسي) -->
<script>
    window.__notificationSystemJsUrl = @json(asset('js/notification-system.js?v=' . $notificationSystemVersion));
    window.__loadNotificationSystem = function (callback) {
        if (window.__notificationSystemLoaded) {
            if (typeof callback === 'function') callback();
            return;
        }
        if (typeof Swal === 'undefined') {
            setTimeout(function () { window.__loadNotificationSystem(callback); }, 50);
            return;
        }
        var script = document.createElement('script');
        script.src = window.__notificationSystemJsUrl;
        script.onload = function () {
            window.__notificationSystemLoaded = true;
            if (typeof callback === 'function') callback();
        };
        script.onerror = function () {
            console.error('❌ فشل تحميل notification-system.js من:', window.__notificationSystemJsUrl);
        };
        document.body.appendChild(script);
    };
</script>

<script>
    // متغير عام للـ CSRF token
    window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // مسارات اعتماد/رفض السلفة من البوب (مع بادئة اللغة الصحيحة)
    @auth
    window.hrAdvanceApprovalUrls = {
        approve: @json(route('hr.advance-approvals.approve', ['id' => '__ID__'])),
        reject: @json(route('hr.advance-approvals.reject', ['id' => '__ID__'])),
        disbursementOptions: @json(route('hr.advance-approvals.disbursement_options', ['id' => '__ID__']))
    };
    @endauth

    // دالة لإظهار حجب الصفحة
    function showNotificationsOverlay() {
        const overlay = document.getElementById('notifications-overlay');
        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.style.display = 'flex';
        }
    }

    // دالة لإخفاء حجب الصفحة
    function hideNotificationsOverlay() {
        const overlay = document.getElementById('notifications-overlay');
        if (overlay) {
            overlay.classList.add('hidden');
            overlay.style.display = 'none';
        }
    }

    function bootEmployeePopupNotifications() {
        @auth
            cleanupOldDeletedNotifications();
            // تمت إزالة showNotificationsOverlay() لمنع الشاشة السوداء عند تحميل الصفحة
            checkEmployeePopupNotifications();
            
            // تحقق تلقائي كل 15 ثانية لجلب البوب اب الجديد في أي صفحة
            if (!window.__employeePopupInterval) {
                window.__employeePopupInterval = setInterval(checkEmployeePopupNotifications, 15000);
            }
        @else
            hideNotificationsOverlay();
        @endauth
    }

    // تحميل الإشعارات بعد جاهزية SweetAlert2 وملف النظام
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.__loadNotificationSystem === 'function') {
            window.__loadNotificationSystem(bootEmployeePopupNotifications);
        } else {
            bootEmployeePopupNotifications();
        }
    });

    // التعامل مع زر الرجوع في المتصفح (bfcache)
    window.addEventListener('pageshow', function(event) {
        @auth
            // إذا تم تحميل الصفحة من الكاش (مثل عند الضغط على زر الرجوع)
            if (event.persisted) {
                console.log('🔄 تم استعادة الصفحة من الكاش - إعادة التحقق من الإشعارات');
                // إخفاء أي تنبيهات SweetAlert قديمة قد تكون بقيت في الكاش
                if (typeof Swal !== 'undefined') {
                    Swal.close();
                }

                // تمت إزالة showNotificationsOverlay() لمنع الشاشة السوداء
                if (typeof window.__loadNotificationSystem === 'function') {
                    window.__loadNotificationSystem(checkEmployeePopupNotifications);
                } else {
                    checkEmployeePopupNotifications();
                }
            }
        @endauth
    });

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

    // دالة للتحقق من الإشعارات المعلقة للموظف
    function checkEmployeePopupNotifications() {
        const url = "{{ route('notifications.employee.popup') }}";
        console.log('🔔 جلب الإشعارات من:', url);

        fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfToken
            }
        })
        .then(response => {
            console.log('🔔 Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('🔔 بيانات الإشعارات:', data);
            if (data.success && data.notifications && data.notifications.length > 0) {
                let deletedNotifications = JSON.parse(localStorage.getItem('deletedNotifications') || '[]').map(String);
                let filteredNotifications = data.notifications.filter(notification =>
                    !deletedNotifications.includes(String(notification.id))
                );

                if (filteredNotifications.length === 0) {
                    hideNotificationsOverlay();
                    return;
                }

                console.log('🔔 عرض', filteredNotifications.length, 'إشعار');
                // عرض الإشعارات بالتتابع
                showClientNotificationsSequentially(filteredNotifications, 0);
            } else {
                console.log('🔔 لا توجد إشعارات معلقة');
                // لا توجد إشعارات - إخفاء الحجب
                hideNotificationsOverlay();
            }
        })
        .catch(error => {
            console.error('❌ خطأ في جلب الإشعارات:', error);
            hideNotificationsOverlay();
        });
    }

    // دالة إرسال الرد على الإشعار
    function sendNotificationReply(notificationId, replyMessage) {
        const baseReplyUrl = "{{ route('notifications.reply', ['id' => '__ID__']) }}";
        const replyUrl = baseReplyUrl.replace('__ID__', notificationId);

        console.log('🔔 إرسال رد للإشعار:', notificationId);

        return fetch(replyUrl, {
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
            console.log('🔔 بيانات الرد:', data);
            if (data.success) {
                console.log('✅ تم إرسال الرد بنجاح');
                return true;
            } else {
                console.error('❌ فشل إرسال الرد:', data.message);
                return false;
            }
        })
        .catch(error => {
            console.error('❌ خطأ في إرسال الرد:', error);
            return false;
        });
    }

    // دالة وضع علامة على الإشعار كمقروء
    function markNotificationAsRead(notificationId) {
        const normalizedId = String(notificationId);
        let deletedNotifications = JSON.parse(localStorage.getItem('deletedNotifications') || '[]').map(String);
        if (!deletedNotifications.includes(normalizedId)) {
            deletedNotifications.push(normalizedId);
            localStorage.setItem('deletedNotifications', JSON.stringify(deletedNotifications));
        }

        console.log('💾 تم حفظ الإشعار في localStorage كـ deleted:', normalizedId);

        return fetch("{{ route('notifications.employee.popup.markShown') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfToken
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ تم تسجيل الإشعار كمقروء في السيرفر:', normalizedId);
            }
            return data;
        })
        .catch(error => {
            console.error('❌ خطأ في تحديث حالة الإشعار بالسيرفر:', error);
            return { success: false };
        });
    }
</script>

{{-- تتبع الموقع المباشر للموظفين - تلقائي وصامت بالكامل --}}
@if(auth()->check() && auth()->user()->isEmployee())
<script>
    (function() {
        if (!navigator.geolocation) return;

        var lastSentTime = 0;
        var sendInterval = 15000; // كل 15 ثانية لتوازن البطارية والتتبع
        var watchId = null;
        var gpsTrackUrl = "{{ route('itinerary.update-location') }}";

        function sendLocationToServer(lat, lng) {
            var now = Date.now();
            if (now - lastSentTime < sendInterval) return;
            lastSentTime = now;

            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.content : '{{ csrf_token() }}';

            fetch(gpsTrackUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ latitude: lat, longitude: lng })
            }).catch(function() {});
        }

        // بدء التتبع التلقائي الصامت
        watchId = navigator.geolocation.watchPosition(
            function(pos) {
                sendLocationToServer(pos.coords.latitude, pos.coords.longitude);
            },
            function(err) {},
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 10000
            }
        );
    })();
</script>
@endif

@include('layouts.partials.menu-toggle-fallback')
