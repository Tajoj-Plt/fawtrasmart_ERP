{{--
    كانت تُمدَّد `master` للموظف و`main-master` لغيره — فرقٌ كان له معنى
    حين كان للموظف محتوى مختلف كلياً (بطاقة ترحيب وشبكة أدوات خاصة به).
    ذلك المحتوى لم يعد موجوداً: كل الأقسام أدناه (المبيعات، المخزون...)
    مشتركة الآن ومحكومة بصلاحيات كل بطاقة على حدة، فلا داعي لتخطيطين.
    والأهم: `master.blade.php` يُضمِّن شريطاً جانبياً حقيقياً بجافاسكربت
    يحجز له مساحةً ديناميكياً (`app-menu.js`) — وهو ما كان يخترق كل تجاوزات
    CSS في هذا الملف لإخفائه، فتظهر مساحة فارغة للموظف حصراً. `main-master`
    لا شريط جانبي فيه أصلاً، فلا حاجة لمحاربته.
--}}
@extends('main-master')

@section('content')
<style>
{{--
    كانت هذه الأنماط تُخفي الشريط الجانبي عن غير الموظف فقط
    (`! isEmployee()`) — و`master.blade.php` (تخطيط الموظف) يضمّن
    `layouts.sidebar` بلا شرط إطلاقاً، فكان الموظف وحده يرى الشريط في هذه
    الصفحة رغم أنها لوحةٌ منفردة لا قائمة تنقّل لها. صار الإخفاء عاماً.
--}}
/* إخفاء السايدبار */
.main-menu,
.vertical-layout .main-menu {
    display: none !important;
}

/*
    قالب الثيم (vertical-menu.css/-rtl) يحجز مساحة السايدبار على `.content`
    نفسها بقواعد `margin-right`/`margin-left` مبنية على حالة القائمة
    (menu-expanded: 260px، menu-collapsed: 80px) بمحدِّدٍ أخصّ من
    `.app-content` وحدها (`body.vertical-layout.vertical-menu-modern...`)
    — فتخفيةُ `.main-menu` وحدها لا تُسقط الهامش المحجوز أصلاً. التجاوز هنا
    يطابق نفس بادئة المحدِّد ليضمن الغلبة بصرف النظر عن حالة القائمة.

    **وما يُسقط هو حجزُ السايدبار وحده لا تباعدُ الصفحة.**

    كان التجاوز يمحو `padding` وكلّ الهوامش، ويمدّ الشريط العلوي من
    حافةٍ إلى حافة بـ`width: 100%` ويلغي زواياه — فتخرج الصفحة الرئيسية
    بشريطٍ ملتصقٍ بالحواف ومحتوىً ملتصقٍ بأعلى الشاشة، غريبةً عن بقية
    النظام.

    وشاشاتُ المالية (`account::master`) تستعمل هندسةَ الثيم كما هي: شريطٌ
    عائم بـ`margin: 1.3rem 2.2rem 0` وزوايا `0.5rem`، ومحتوىً تحته بـ
    `padding: calc(2.2rem - 0.4rem) 2.2rem 0` و`margin-top: 6rem`.

    فالمقاديرُ أدناه مقاديرُ الثيم نفسُها حرفاً، ناقصةً حجزَ الـ 80px
    لا غير — لأنّ هذه الصفحة وحدها بلا سايدبار.
*/
body.vertical-layout.vertical-menu-modern .app-content,
body.vertical-layout.vertical-menu-modern .content,
body.vertical-layout.vertical-menu-modern .content-area,
body.vertical-layout.vertical-menu-modern .main-content,
body.vertical-layout.vertical-menu-modern .content-body,
body.vertical-layout.vertical-menu-modern .footer,
.app-content,
.content-area,
.main-content,
.content-body {
    margin-left: 0 !important;
    margin-right: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
}

/* تباعدُ المحتوى كما في `html body .content .content-wrapper` بالثيم */
body.vertical-layout.vertical-menu-modern .content-wrapper,
.content-wrapper {
    margin-left: 0 !important;
    margin-right: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    padding: calc(2.2rem - 0.4rem) 2.2rem 0 !important;
    margin-top: 6rem !important;
}

/*
    الشريط العلوي: عائمٌ كما في المالية.

    عرضُ الثيم هناك `100vw - شريط التمرير - 4.4rem - 80px`؛ وحدّ
    الـ 80px حجزُ السايدبار المطوي، ولا سايدبار هنا — فسقط. وبقيت
    الـ 4.4rem (هامشان بـ 2.2rem) كما هي، فيتوسّط الشريطُ بالتباعد
    نفسِه في الاتجاهين.
*/
body.vertical-layout.vertical-menu-modern .header-navbar.floating-nav,
.header-navbar.floating-nav {
    margin: 1.3rem 2.2rem 0 !important;
    border-radius: 0.5rem !important;
    width: calc(100vw - (100vw - 100%) - 4.4rem) !important;
    right: 0 !important;
    left: 0 !important;
}

/* والنمطُ الثابت — حين لا يكون الشريط عائماً في إعدادات القالب */
body.vertical-layout.vertical-menu-modern .header-navbar.navbar-static-top,
.header-navbar.navbar-static-top {
    width: calc(100vw - (100vw - 100%)) !important;
    right: 0 !important;
    left: 0 !important;
}

@media (max-width: 991.98px) {
    body.vertical-layout.vertical-menu-modern .header-navbar.floating-nav,
    .header-navbar.floating-nav {
        margin: 1.3rem 1rem 0 !important;
        width: calc(100vw - (100vw - 100%) - 2rem) !important;
    }

    body.vertical-layout.vertical-menu-modern .content-wrapper,
    .content-wrapper {
        padding: calc(2.2rem - 0.4rem) 1rem 0 !important;
    }
}

/* تصميم لوحة المندوب */
.employee-welcome-card {
    background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
    color: #fff;
    border-radius: 18px;
    padding: 2.5rem;
    margin-bottom: 2.5rem;
    box-shadow: 0 10px 20px rgba(115, 103, 240, 0.15);
    position: relative;
    overflow: hidden;
}
.employee-welcome-card h1 {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 0.5rem;
}
.employee-welcome-card p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}
.employee-welcome-card .decor {
    position: absolute;
    right: 5%;
    bottom: -20px;
    font-size: 8rem;
    opacity: 0.1;
    pointer-events: none;
}
.employee-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.8rem;
}
.employee-widget-card {
    background: #fff;
    border: 1px solid #eef2f5;
    border-radius: 18px;
    padding: 1.8rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.employee-widget-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(115, 103, 240, 0.08);
    border-color: #dcdffd;
}
.widget-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f4f6f8;
}
.widget-title-wrapper {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}
.widget-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
.bg-light-primary { background: rgba(115, 103, 240, 0.1); color: #7367f0; }
.bg-light-success { background: rgba(40, 199, 111, 0.1); color: #28c76f; }
.bg-light-warning { background: rgba(255, 159, 67, 0.1); color: #ff9f43; }
.bg-light-info { background: rgba(0, 207, 221, 0.1); color: #00cfdd; }
.bg-light-danger { background: rgba(234, 84, 85, 0.1); color: #ea5455; }
.bg-light-secondary { background: rgba(130, 134, 139, 0.1); color: #82868b; }

.widget-header h3 {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
    color: #2c3e50;
}
.widget-links {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    flex-grow: 1;
}
.widget-link-item a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.8rem 1rem;
    background: #f8f9fc;
    border-radius: 10px;
    color: #4e5d6c;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}
.widget-link-item a:hover {
    background: #7367f0;
    color: #fff !important;
}
.widget-link-item a i.chevron {
    font-size: 0.8rem;
    transition: transform 0.2s ease;
}
.widget-link-item a:hover i.chevron {
    transform: translateX(-3px);
}

/* الحاوية الرئيسية */
/*
    التباعدُ من `.content-wrapper` لا من هنا.

    كان `margin-top: 90px` و`padding: 2rem 3rem` تعويضاً عن التجاوز
    الذي يمحو تباعد الثيم. وقد عاد التباعدُ إلى موضعه، فصار
    التعويض فراغاً مضاعفاً بين الشريط العلوي وأول كارد.
*/
.dashboard-wrapper {
    width: 100%;
    box-sizing: border-box;
    padding: 0 0 2rem;
    margin-top: 0;
}

/* كارد الهوية */
.system-card {
    border: 2px solid #7367F0;
    border-radius: 18px;
    background: linear-gradient(145deg, #ffffff, #f9f9ff);
    padding: 2rem;
    text-align: center;
    margin-bottom: 2.5rem;
    box-shadow: 0 5px 18px rgba(115, 103, 240, 0.08);
}

.system-card h1 {
    font-size: 1.8rem;
    font-weight: 800;
    color: #4B4B4B;
    margin-bottom: 0.5rem;
    letter-spacing: -0.5px;
}

.system-card .highlight {
    color: #7367F0;
}

.system-card p {
    font-size: 1rem;
    color: #666;
    margin: 0;
}

/* شبكة الكروت */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    align-items: stretch;
}

/* تصميم الكرت */
.card {
    border-radius: 18px !important;
    transition: all 0.3s ease;
    cursor: pointer;
    background: #fff;
    text-align: start;
    height: 160px;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem;
    box-sizing: border-box;
    border: 1px solid #f0f0f0;
}

.card-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.1);
    border-color: #ddd;
}

.card h4 {
    font-weight: 700;
    font-size: 1.05rem;
    margin-bottom: 0.3rem;
    color: #333;
    line-height: 1.3;
}

.card p {
    font-size: 0.9rem;
    color: #777;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-link {
    text-decoration: none !important;
    color: inherit !important;
    display: block;
}

body {
    overflow-x: hidden !important;
}

/* شريط قيد التطوير */
.ribbon-wrapper {
    position: absolute;
    top: -5px;
    left: -5px;
    width: 85px;
    height: 85px;
    overflow: hidden;
    z-index: 10;
}

.ribbon {
    position: absolute;
    top: 18px;
    left: -22px;
    width: 100px;
    padding: 4px 0;
    font-size: 0.7rem;
    font-weight: 700;
    text-align: center;
    color: #fff;
    transform: rotate(-45deg);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
</style>

<div class="dashboard-wrapper">

   
        {{-- كارد الهوية --}}
        <div class="system-card">
            <h1>نظام <span class="highlight">فوتره سمارت</span></h1>
            <p>نظام ERP متكامل لإدارة أعمالك — المبيعات، الموارد، المخزون، المالية والمزيد.</p>
        </div>

        <?php
            $recentTargetUpdates = \App\Models\notifications::where('type', 'target_evaluation')
                ->where('receiver_id', auth()->id())
                ->where('created_at', '>=', now()->subHours(24))
                ->orderBy('created_at', 'desc')
                ->take(20)
                ->get();
        ?>

        <?php if($recentTargetUpdates->count() > 0): ?>
        <div class="system-card" style="text-align: right; padding: 1.5rem; background: #fff; border-color: #eef2f5;">
            <h3 style="font-weight: 700; margin-bottom: 1rem; color: #4B4B4B; font-size: 1.3rem;">
                <i class="feather icon-target text-primary"></i> آخر تحديثات تقييم العملاء (التقييم الآلي)
            </h3>
            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-hover mb-0">
                    <tbody>
                        <?php foreach($recentTargetUpdates as $update): ?>
                            <?php
                                $isDowngrade = (strpos($update->title, 'تراجع') !== false || strpos($update->description, 'تراجع') !== false);
                            ?>
                            <tr>
                                <td style="width: 50px; text-align: center; vertical-align: middle;">
                                    <?php if($isDowngrade): ?>
                                        <div class="avatar bg-rgba-danger p-50 m-0">
                                            <div class="avatar-content"><i class="feather icon-trending-down text-danger font-medium-3"></i></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar bg-rgba-success p-50 m-0">
                                            <div class="avatar-content"><i class="feather icon-check-circle text-success font-medium-3"></i></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="vertical-align: middle;">
                                    <h6 class="mb-0 font-weight-bold" style="color: #2c3e50;"><?php echo htmlspecialchars($update->title); ?></h6>
                                    <span class="text-muted" style="font-size: 0.9rem;"><?php echo htmlspecialchars($update->description); ?></span>
                                </td>
                                <td class="text-left" style="vertical-align: middle;">
                                    <span class="badge badge-light-secondary"><?php echo $update->created_at->diffForHumans(); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="dashboard-grid">

        {{-- إدارة المبيعات --}}
        @canany([
            'sales.invoices.create', 'sales.invoices.view',
            'sales.invoices.edit', 'sales.invoices.delete',
            'sales.payments.create', 'sales.payments.view',
            'sales.payments.edit', 'sales.payments.delete',
            'sales.settings.update',
            'sales.credit_notes.create', 'sales.credit_notes.view',
            'sales.credit_notes.edit', 'sales.credit_notes.delete',
            'sales.quotes.view', 'sales.quotes.create',
            'sales.quotes.edit', 'sales.quotes.delete',
            'sales.returns.view', 'sales.returns.create',
            'sales.returns.edit', 'sales.returns.delete',
            'sales.invoices.edit_date', 'sales.invoices.change_seller',
            'sales.invoices.invoice_all_products', 'sales.invoices.view_profit',
            'sales.invoices.print_tax',
            'sales.collections_targets.view', 'sales.collections_targets.create'
        ])
            <a href="{{ route('sales.department.dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>إدارة المبيعات </h4>
                            <p>مبيعات - فواتير </p>
                        </div>
                        <div class="avatar bg-rgba-primary p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-shopping-cart text-primary font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endcanany

        {{-- نقاط البيع --}}
        {{--
            قسمٌ مستقلّ لا بندٌ تحت المبيعات.

            كانت صلاحيات `points_sale_*` مضمومةً إلى شرط بطاقة «إدارة
            المبيعات»، فمن لا يملك إلّا نقاط البيع تُفتح له بوابة المبيعات
            كلّها، ولا يجد فيها ما يخصّه إلّا بعد أن يتصفّح قائمةً ليست
            له. وصار لنقاط البيع بطاقتها وشريطها الجانبي.
        --}}
        @canany([
            'points_sale_edit_product_prices', 'points_sale_add_discount',
            'points_sale_open_sessions_all', 'points_sale_open_sessions_own',
            'points_sale_close_sessions_all', 'points_sale_close_sessions_own',
            'points_sale_view_own_sessions',
            'points_sale_confirm_close_sessions_all', 'points_sale_confirm_close_sessions_own',
            'pos_manage_settings', 'pos_view_reports', 'pos_reports_view',
        ])
            <a href="{{ route('dashboard.pos') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>نقاط البيع</h4>
                            <p>بدء البيع - الجلسات - التقارير</p>
                        </div>
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-monitor text-success font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endcanany

        {{-- إدارة العملاء --}}
        @canany([
            'clients_view_all_clients', 'clients_add_client',
            'client_follow_up_add_notes_attachments_appointments_all',
            'clients_edit_client_settings',
            'customer_attendance_display'
        ])
            <a href="{{ route('client.department.dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>إدارة  العملاء </h4>
                            <p>  عملاء</p>
                        </div>
                        <div class="avatar bg-rgba-primary p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-shopping-cart text-primary font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endcanany

        {{-- المتجر الإلكتروني --}}
        @can('online_store_content_management')
            <a href="{{ route('onlinestore.department.dashboard') }}" class="card-link">
                <div class="card"> 
                    <div class="card-content">
                        <div>
                            <h4>المتجر الإلكتروني</h4>
                            <p>طلبات أونلاين - الدفع - العملاء</p>
                        </div>
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-globe text-success font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endcan

        {{-- إدارة العقارات — بطاقةٌ فعّالة الآن (كانت «قيد التطوير») تقود إلى
             لوحة القسم. البوّابة: أيّ صلاحية `realestate.*`، أو صلاحيات
             الإيجار القديمة لمن لا يملك غيرها. --}}
        @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\RealEstatePermissions::PREFIX) || canAny(['rental_unit_view_booking_orders', 'rental_unit_manage_booking_orders', 'rental_unit_manage_rental_settings']))
            <a href="{{ route('rentalmanagement.department.dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>إدارة العقارات</h4>
                            <p>مبانٍ - وحدات - عقود - دفعات - صيانة</p>
                        </div>
                        <div class="avatar bg-rgba-primary p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-home text-primary font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endif

        {{-- إدارة المخزون --}}
        @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\StockPermissions::PREFIX) || auth()->user()->hasRolePermission('online_store_content_management'))
            <a href="{{ route('dashboard.stock') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>إدارة المخزون</h4>
                            <p>مخزون - حركة المستودعات - تقارير</p>
                        </div>
                        <div class="avatar bg-rgba-danger p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-package text-danger font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endif

        {{--
            التصنيع.

            كان قسماً داخل المخزون، فلم تكن له بطاقةٌ هنا. وصار وحدةً قائمة
            بلوحتها ومساراتها، فلزمته بطاقةٌ كسائر الوحدات.

            والبطاقة محروسةٌ بمفتاح شاشة «التطبيقات» كما هو الشريط الجانبي.
            كانت محروسةً بالصلاحية ووجودِ المسار وحدهما، فإذا عُطِّلت الوحدة
            من الشاشة اختفى بندُها من الشريط وبقيت البطاقة هنا ظاهرة: ينقرها
            المستخدم فيردّه `EnsureModuleEnabled` إلى هذه الصفحة نفسها. أي
            أن الصفحة كانت تعرض باباً تعيده هي إلى مكانه.
        --}}
        @canany([
            'stock.manufacturing_orders.view',
            'stock.bom.view',
            'stock.workstations.view',
            'stock.indirect_costs.view'
        ])
            {{--
                وحدُّ الباقة إلى جانبهما.

                البطاقة كانت محروسةً بالصلاحية ووجودِ المسار ومفتاحِ شاشة
                «التطبيقات» — ولا شيء يسأل «هل اشترت المنشأة التصنيع؟».
                فمنشأةٌ على باقةٍ لا تشمله ترى بطاقتَه في صفحتها الأولى،
                فتنقرها فتُردّ بصفحة 402 «باقتك لا تشمل…». أي أن الصفحة
                تعرض باباً تعرف أنه مقفل.

                والشرط «واحدةٌ على الأقل» — كما `@canany` فوقه بالضبط:
                البطاقة تقود إلى اللوحة، واللوحة تفتح لمن يملك شيئاً في
                الوحدة لا أوامرَ التصنيع بعينها.
            --}}
            @if (\Illuminate\Support\Facades\Route::has('manufacturing.dashboard')
                && \App\Services\Settings\ApplicationRegistry::isAvailable('manufacturing')
                && \App\Services\Fawtra\SubscriptionGate::permits(\App\Constants\planFeatures\StockPlanFeatures::manufacturingOnly()))
                <a href="{{ route('manufacturing.dashboard') }}" class="card-link">
                    <div class="card">
                        <div class="card-content">
                            <div>
                                <h4>التصنيع</h4>
                                <p>أوامر إنتاج - قوائم مواد - محطّات عمل</p>
                            </div>
                            <div class="avatar bg-rgba-warning p-50 m-0">
                                <div class="avatar-content">
                                    <i class="feather icon-layers text-warning font-medium-5"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            @endif
        @endcanany

        {{--
            بطاقةُ «التقارير» أُسقطت.

            كانت تسمّي نفسها «التقارير» وتفتح تقاريرَ المخزون وحدها
            (`StorHouseReport.index`) — فاسمُها يَعِد بما لا تعطي. ولكلّ
            قسمٍ اليومَ تقاريرُه داخل بطاقته: المبيعات والمشتريات والعملاء
            والمخزون والحجوزات.

            وكانت بلا حارسِ صلاحيةٍ أصلاً: من لا يملك تقارير المخزون يراها
            ويضغطها فيُردّ.
        --}}

        {{-- إدارة المشتريات — أيُّ صلاحيةِ مشترياتٍ واحدة تكفي، كبقيّة
             البطاقات. كان تعداداً يدوياً لثماني صلاحيات من ثمانين، فمن
             مُنح الاستلامَ أو المرتجعاتِ أو المدفوعاتِ أو عروضَ أسعار
             الموردين لم يرَ البطاقة. --}}
        @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\PurchasesPermissions::PREFIX))
            <a href="{{ route('dashboard.purchases') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>إدارة المشتريات</h4>
                            <p>فواتير شراء - مرتجعات - موردين</p>
                        </div>
                        <div class="avatar bg-rgba-info p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-shopping-bag text-info font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endif


        {{-- المالية والحسابات العامة --}}
        @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\AccountPermissions::PREFIX))
            <a href="{{ route('account.department.dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>المالية والحسابات العامة</h4>
                            <p>قيود - حسابات - ميزانية</p>
                        </div>
                        <div class="avatar bg-rgba-info p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-dollar-sign text-info font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endif
 @canany([
            'tasks_view_tasks',
        ])
        {{-- إدارة المهام — محروسةٌ بـ`tasks_view_tasks` في الشرط أعلاه.
             (كان التعليقُ يقول «بدون صلاحيات، متاحة للجميع» وهو يناقض شيفرتَه.) --}}
        <a href="{{ route('tasks.dashboard') }}" class="card-link">
            <div class="card">
                <div class="card-content">
                    <div>
                        <h4>إدارة المهام</h4>
                        <p>المتابعة - الإنجاز - التقارير</p>
                    </div>
                    <div class="avatar bg-rgba-secondary p-50 m-0">
                        <div class="avatar-content">
                            <i class="feather icon-check-square text-secondary font-medium-5"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
@endcanany
        {{--
            الموارد البشرية — بطاقتان تفتحان مساراً واحداً.

            المسار `hr.dashboard.index` محروسٌ في متحكّمه بـ
            `permission.role:hr_dashboard.index`، وحرّاسُ البطاقتين كانا
            شيئاً آخر: قائمةُ ثمانٍ وثلاثين صلاحيةً لا تضمّها للأولى،
            و`@if(employee_id)` **بلا أيّ صلاحية** للثانية.

            فكان كلُّ مستخدمٍ مرتبطٍ بسجلّ موظف يرى «لوحة أداء الموظفين»
            أيّاً كان دورُه، ثمّ يُردّ عند الضغط.

            اللفُّ بحارس المسار نفسِه: ما يُعرَض يُفتَح، وما يُردّ لا يُعرَض.
            ولمن أراد أن يراها موظفوه: تُمنح `hr_dashboard.index` لدورهم.
        --}}
        @if (hasPermission('hr_dashboard.index'))
        {{-- المسارُ محروسٌ بـ`hr_dashboard.index` — الحارسُ نفسُه هنا؛ والقائمةُ الداخلية تختار العنوانَ وحده --}}
        @if (canAny(['employees.index', 'employees.create', 'departments.index', 'attendance_books.index', 'leave_requests.approve', 'attendance_settings.manage', 'contracts.create', 'payroll.index', 'payroll.create', 'loans.index', 'payroll_settings.manage']))
            <a href="{{ route('hr.dashboard.index') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>إدارة الموارد البشرية</h4>
                            <p>موظفين - رواتب - حضور وانصراف</p>
                        </div>
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-users text-success font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @else
            <a href="{{ route('hr.dashboard.index') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>لوحة أداء الموظفين</h4>
                            <p>مبيعات، تحصيلات، وجداول أداء الفريق للفترة</p>
                        </div>
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-trending-up text-success font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endif
        @endif

        {{--
            النظام البيئي للنقاط.

            كانت البطاقةُ **بلا حارسٍ إطلاقاً** — يراها كلُّ من يسجّل دخوله
            ويفتح منها ضبطَ قواعد النقاط وتعديلَ أرصدة العملاء. وسببُ ذلك
            أن الوحدة لم تكن لها صلاحيةٌ واحدة في القاعدة؛ صار لها كتالوجُها
            (`EcosystemPermissions`) فحلّت الحراسةُ محلَّ الانكشاف.
        --}}
        @canany([
            'ecosystem.view',
            'ecosystem.points.view',
            'ecosystem.tiers.view',
            'ecosystem.rewards.view',
            'ecosystem.engagement.view',
            'ecosystem.settings',
            'ecosystem.reports.view',
        ])
        <a href="{{ route('ecosystem.dashboard') }}" class="card-link">
            <div class="card">
                <div class="card-content">
                    <div>
                        <h4>النظام البيئي للنقاط</h4>
                        <p>نقاط - مكافآت - إنجازات - تحديات</p>
                    </div>
                    <div class="avatar bg-rgba-warning p-50 m-0">
                        <div class="avatar-content">
                            <i class="feather icon-star text-warning font-medium-5"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
        @endcanany

        {{-- إدارة الفروع والمجموعات --}}
        {{-- كانت تسأل عن `branches` وحدها — وهي صلاحيةٌ قديمة لا يملكها
             من مُنح `branches.view` من كتالوج الفروع، فتختفي عنه البطاقة
             وهو يملك فتحَ الشاشة فعلاً. --}}
        @canany([
            'branches.view',
            'branches',
            'settings_edit_general_settings',
            'clients_edit_client_settings'
        ])
            @php
                $branchesHome = hasPermission('branches.dashboard') && \Illuminate\Support\Facades\Route::has('branches.dashboard')
                    ? route('branches.dashboard')
                    : route('branches.index');
            @endphp
            <a href="{{ $branchesHome }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>إدارة الهيكل التنظيمي</h4>
                            <p>لوحة الهيكل: المناطق والفروع والمجموعات والمنافذ</p>
                        </div>
                        <div class="avatar bg-rgba-primary p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-git-branch text-primary font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endcanany

        {{-- الإعدادات العامة --}}
        @canany([
            'settings_edit_general_settings',
            'settings_edit_tax_settings'
        ])
            <a href="{{ route('settings.department.dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-content">
                        <div>
                            <h4>الإعدادات العامة</h4>
                            <p>المستخدمين - الصلاحيات - النظام</p>
                        </div>
                        <div class="avatar bg-rgba-dark p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-settings text-dark font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endcanany

    </div>
  
</div>

<script>
function showComingSoon(moduleName) {
    Swal.fire({
        icon: 'info',
        title: '🚧 قيد التطوير',
        html: `
            <div style="text-align: center;">
                <p style="font-size: 1.1rem; color: #555; margin-bottom: 10px;">
                    <strong>${moduleName}</strong>
                </p>
                <p style="color: #777;">
                    هذه الميزة قيد التطوير حالياً وسيتم إطلاقها قريباً إن شاء الله.
                </p>
                <p style="color: #999; font-size: 0.9rem; margin-top: 15px;">
                    <i class="feather icon-clock"></i> نعمل على إنجازها في أقرب وقت
                </p>
            </div>
        `,
        confirmButtonText: 'حسناً',
        confirmButtonColor: '#7367F0',
        customClass: {
            popup: 'animated fadeInDown'
        }
    });
}
</script>
@endsection
