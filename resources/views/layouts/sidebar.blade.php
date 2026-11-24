@php
    $getLocal = App::getLocale();
@endphp

<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto"><a class="navbar-brand" href="#">
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
            {{--
                القائمة المختصرة للمندوب/الموظف — إلّا إذا كان دوره مفعَّلاً
                عليه «القائمة الموحّدة» في إعدادات الأدوار. التوحيد وعدٌ بقائمةٍ
                واحدة تُشتقّ من الصلاحيات وحدها، فلو بقي التفرّع هنا لرأى صاحبُ
                الدور المختصرةَ في كل شاشة — وهي أضيقُ مما يملك.
            --}}
            @if (auth()->user()->isEmployee() && ! \App\Support\UnifiedSidebar::enabled())
                <!-- Simplified Sidebar for Representative / Employee -->
                <li class="nav-item {{ request()->is("$getLocal/dashboard") || request()->is("dashboard") ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}">
                        <i class="feather icon-home"></i>
                        <span class="menu-title">الرئيسية</span>
                    </a>
                </li>

                @if (canAny(['clients_view_all_clients', 'clients_view_own_clients', 'clients_add_client', 'clients_itinerary_view', 'clients_daily_itinerary_view', 'clients_daily_itinerary_add_for_others', 'clients.wallet.view']))
                <li class="nav-item {{ request()->is("$getLocal/clients*") || request()->is("$getLocal/itinerary*") || request()->is("$getLocal/visits*") || request()->is("$getLocal/employee/visit-justifications*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-user"></i>
                        <span class="menu-title">{{ trans('main_trans.Customers') }}</span>
                    </a>
                    <ul class="menu-content">
                        @if (canAny(['clients_view_all_clients', 'clients_view_own_clients']))
                            <li>
                                <a href="{{ route('clients.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/clients") ? 'active' : '' }}"></i>
                                    <span class="menu-item">إدارة العملاء</span>
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('clients_add_client'))
                            <li>
                                <a href="{{ route('clients.create') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/clients/create") ? 'active' : '' }}"></i>
                                    <span class="menu-item">{{ trans('main_trans.Add_a_new_customer') }}</span>
                                </a>
                            </li>
                        @endif

                        {{-- محافظ العملاء — **في الموضعين**: هنا بين إخوتها
                             في مجموعة العملاء، وبنداً رئيسياً بعد المجموعة.

                             المجموعة أكورديون مطويّ افتراضياً، فوضعُه هنا
                             وحدَه يُخفيه عمّن لا يفتحها — وهو ما بلّغ عنه
                             المستخدم. ورفعُه وحدَه أخرجه من الموضع الذي
                             يبحث فيه. فالتكرار مقصود: ظهورٌ بلا نقرة، وبقاءٌ
                             حيث تنتظره العين.

                             والحارس `@can` نفسُه الذي يحرس «محافظ الموردين»
                             في شريط المشتريات — بطلب المستخدم صراحةً. --}}
                        @permission('clients.wallet.view')
                            <li>
                                <a href="{{ route('clients.wallet.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/clients/clients_management/client-wallets*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">محافظ العملاء</span>
                                </a>
                            </li>
                        @endpermission

                        @if ((hasPermission('clients_daily_itinerary_view') || hasPermission('clients_daily_itinerary_add_for_others')))
                        <li>
                            <a href="{{ route('itinerary.daily') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/itinerary/my-day*") ? 'active' : '' }}"></i>
                                <span class="menu-item">خط سيري اليومي</span>
                            </a>
                        </li>
                        @endif

                        @if (hasPermission('clients_itinerary_view'))
                        <li>
                            <a href="{{ route('itinerary.list') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/itinerary*") && ! request()->is("$getLocal/itinerary/dashboard*") ? 'active' : '' }}"></i>
                                <span class="menu-item">خط السير</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('itinerary.dashboard') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/itinerary/dashboard*") ? 'active' : '' }}"></i>
                                <span class="menu-item">لوحة تحكّم خط السير</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('clients_visit_justifications_view_own|clients_visit_justifications_view_all'))
                        <li>
                            <a href="{{ route('employee.visit-justifications.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/employee/visit-justifications*") ? 'active' : '' }}"></i>
                                <span class="menu-item">تبريراتي</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                {{-- محافظ العملاء — بندٌ رئيسيّ لا فرعيّ.

                     كان داخل مجموعة «العملاء» أعلاه، والمجموعة أكورديون
                     مطويّ افتراضياً: فمن يفتح الصفحة الرئيسية لا يرى الرابط
                     إطلاقاً حتى ينقر «العملاء» أوّلاً — وهو ما بلّغ عنه
                     المستخدم مرّتين بأنّه «لا يظهر». ورفعُه إلى المستوى
                     الأوّل يجعله مرئياً بلا نقرة، كنظيره «تقرير العملاء». --}}
                @permission('clients.wallet.view')
                    <li class="nav-item {{ request()->is("$getLocal/clients/clients_management/client-wallets*") ? 'active' : '' }}">
                        <a href="{{ route('clients.wallet.index') }}">
                            <i class="feather icon-credit-card"></i>
                            <span class="menu-title">محافظ العملاء</span>
                        </a>
                    </li>
                @endpermission

                {{-- مجموعة «المبيعات» — الجزء المشترك نفسه الذي يعرضه شريط وحدة
                     المبيعات. كانت هنا نسخةٌ ثانية لا تعرف حدود الاشتراك ولا
                     مفاتيح «إعدادات الفواتير»، فبندٌ يُضاف هناك يغيب عن كل شاشةٍ
                     خارج المبيعات. --}}
                @include('sales::layouts.partials.sales-menu')

                {{--
                    المخزون.

                    كان الأب `href="#"` — فالنقر على الأيقونة يفتح القائمة
                    الفرعية ولا يذهب إلى شيء، ولوحة المخزون لا رابط إليها من
                    الشريط الجانبي إطلاقاً (كانت تُبلَغ من بطاقة «إدارة
                    المخزون» في الصفحة الرئيسية وحدها).

                    صار الأب يقود إلى اللوحة، ولها بندٌ صريح في القائمة —
                    لأن الشريط حين يُطوى إلى أيقونات يمنع القالبُ الأبَ من
                    التنقّل ليُظهر القائمة المنبثقة، فيبقى البند طريقاً
                    ثانياً في تلك الحال.
                --}}
                {{-- كان الشرط `|| auth()->user()->employee_id`: أي موظّفٍ تُفتح له
                     المجموعة مهما كانت صلاحيّاته، وبنودُها الثلاثة محروسةٌ
                     بصلاحياتها — فتُفتح على عنوانٍ بلا بنود. وهذا يقع لـ268
                     صلاحيةً من صلاحيات النظام. الشرط الآن حرّاسُ بنودها. --}}
                @if (canAny(['stock.products.view', 'stock.reports.view']))
                <li class="nav-item {{ request()->is("$getLocal/products*") || request()->is("$getLocal/stock/dashboard*") || request()->is("$getLocal/stock/employee-statement*") ? 'active open' : '' }}">
                    <a href="{{ route('stock.dashboard') }}">
                        <i class="feather icon-package"></i>
                        <span class="menu-title">{{ trans('main_trans.Stock') }}</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('stock.reports.view'))
                            <li>
                                <a href="{{ route('stock.dashboard') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/stock/dashboard*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">لوحة المخزون</span>
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('stock.products.view'))
                            <li>
                                <a href="{{ route('products.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/products*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">{{ trans('main_trans.products_management') }}</span>
                                </a>
                            </li>
                        @endif
                        @if (Auth::user()->employee_id && hasPermission('stock.reports.view') && hasPermission('stock.reports.employee_statement'))
                            <li>
                                <a href="{{ route('storehouse.employee_statement') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/stock/employee-statement*") ? 'active' : '' }}"></i>
                                    <span class="menu-item text-primary fw-bold">التقرير المخزني الخاص بالموظف</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                @endif

                @if (canAny(['account.expenses.view', 'account.incomes.view', 'account.treasury.view', 'account.settings.view']))
                <li class="nav-item {{ request()->is("$getLocal/finance/*") || request()->is("$getLocal/expenses*") || request()->is("$getLocal/incomes*") || request()->is("$getLocal/treasury*") || request()->is("$getLocal/finance_settings*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-dollar-sign"></i>
                        <span class="menu-title">{{ trans('main_trans.Financial') }}</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('account.expenses.view'))
                            <li>
                                <a href="{{ route('expenses.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/finance/expenses/*") || request()->is("$getLocal/expenses*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">سندات الصرف (المصروفات)</span>
                                </a>
                            </li>
                        @endif

                        @if (hasPermission('account.incomes.view'))
                            <li>
                                <a href="{{ route('incomes.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/finance/incomes/*") || request()->is("$getLocal/incomes*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">سندات القبض (الإيرادات)</span>
                                </a>
                            </li>
                        @endif

                        @if (hasPermission('account.treasury.view'))
                            <li>
                                <a href="{{ route('treasury.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/finance/treasury/*") || request()->is("$getLocal/treasury*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">{{ trans('main_trans.Cash_and_Bank_Accounts') }}</span>
                                </a>
                            </li>
                        @endif

                        @if (hasPermission('account.settings.view'))
                            <li>
                                <a href="{{ route('finance_settings.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/finance/finance_settings/*") || request()->is("$getLocal/finance_settings*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">{{ trans('main_trans.Financial_Settings') }}</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                @endif

                @if (canAny(['sales.collections_targets.view', 'sales.collections_targets.report']))
                <li class="nav-item {{ request()->is("$getLocal/employee_targets*") || request()->is("$getLocal/target*") || request()->is("$getLocal/daily_closing_entry*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-trending-up"></i>
                        <span class="menu-title">أهدافي والتحصيل</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('sales.collections_targets.view'))
                        <li>
                            <a href="{{ route('employee_targets.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/employee_targets*") ? 'active' : '' }}"></i>
                                <span class="menu-item">أهدافي الحالية</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('sales.collections_targets.report'))
                        <li>
                            <a href="{{ route('daily_closing_entry') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/daily_closing_entry*") ? 'active' : '' }}"></i>
                                <span class="menu-item">تقرير التحصيل اليومي</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('sales.daily_closing.view'))
                        <li>
                            <a href="{{ route('daily-closing.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/hr/daily-closing*") ? 'active' : '' }}"></i>
                                <span class="menu-item">الإقفال اليومي</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                {{--
                    إدارة المهام — قائمةٌ فرعية لا رابطاً واحداً.

                    كان البند رابطاً مفرداً إلى لوحة المهام وحدها، فمن كان
                    دورُه على «القائمة الجانبية الموحّدة» لا يصل من الشريط
                    إلى مساحات العمل ولا المشاريع ولا السبرنتات ولا التقارير
                    ولا الإعدادات — وهي شاشات الوحدة كلّها. القائمة الموحّدة
                    تُغني عن قائمة الوحدة، فبندٌ مفردٌ فيها يقطع الطريق إلى
                    الوحدة بدل أن يفتحها.

                    **والشرط كان `tasks_view_tasks`** — اسمٌ من النظام القديم
                    لا تطلبه مسارات الوحدة في شيء: هي تطلب `tasks.*` من
                    [\App\Constants\permissions\TaskManagerPermissions].
                    فيظهر البند لمن يملك الاسم القديم ويردّه المسار خلفه
                    بـ403 — بطاقةٌ تدعو إلى بابٍ مغلق. والشرط الآن من
                    الأسماء التي تحرس المسارات نفسها، وكلُّ بندٍ فرعيٍّ
                    بصلاحية مساره هو.

                    وحالة `active` تشمل بادئات الوحدة كلّها لا `task*` وحدها:
                    المشاريع ومساحات العمل والسبرنتات مساراتُها لا تبدأ
                    بـ`task`، فكان القسم ينطوي كلّما فُتحت إحداها.
                --}}
                @if (canAny([
                    /* `tasks.board.backlog` حُذفت: لا بندَ في المجموعة يفتحه صاحبُها */
                'tasks.board.view', 'tasks.board.dashboard',
                    'tasks.items.view', 'tasks.projects.view', 'tasks.workspaces.view',
                    'tasks.sprints.view', 'tasks.reports.view', 'tasks.settings.global.view',
                ]))
                <li class="nav-item {{ request()->is("$getLocal/task*") || request()->is("$getLocal/projects*") || request()->is("$getLocal/workspaces*") || request()->is("$getLocal/sprints*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-check-square"></i>
                        <span class="menu-title">إدارة المهام والأنشطة</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('tasks.board.dashboard'))
                        <li>
                            <a href="{{ route('tasks.dashboard') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/task-dashboard*") ? 'active' : '' }}"></i>
                                <span class="menu-item">لوحة معلومات المهام</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.board.view'))
                        <li>
                            <a href="{{ route('tasks.manager') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/task-manager*") ? 'active' : '' }}"></i>
                                <span class="menu-item">لوحة المهام</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.items.view'))
                        <li>
                            <a href="{{ route('tasks.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/tasks") ? 'active' : '' }}"></i>
                                <span class="menu-item">قائمة المهام</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.projects.view'))
                        <li>
                            <a href="{{ route('projects.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/projects*") ? 'active' : '' }}"></i>
                                <span class="menu-item">المشاريع</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.workspaces.view'))
                        <li>
                            <a href="{{ route('workspaces.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/workspaces*") ? 'active' : '' }}"></i>
                                <span class="menu-item">مساحات العمل</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.sprints.view'))
                        <li>
                            <a href="{{ route('sprints.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/sprints*") ? 'active' : '' }}"></i>
                                <span class="menu-item">السبرنتات</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.reports.view'))
                        <li>
                            <a href="{{ route('tasks.reports') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/task-reports*") ? 'active' : '' }}"></i>
                                <span class="menu-item">تقارير المهام</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.settings.global.view'))
                        <li>
                            <a href="{{ route('taskmanager.global-settings.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/task-settings*") ? 'active' : '' }}"></i>
                                <span class="menu-item">إعدادات المهام</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                {{-- بوّابةُ «الموارد البشرية» = اتحادُ حرّاس بنودها بعد تصحيحها.

                     كانت تسرد خمسين صلاحية، أكثرُها لا بندَ له داخل المجموعة
                     (إنشاءُ الأدوار، وإضافةُ العقود، وحذفُ المسيّرات، وصلاحياتُ
                     الخدمة الذاتية…). فمندوبُ المبيعات — وله `employee_staff*` —
                     كانت تُفتح له المجموعةُ **فارغة**: عنوانٌ يُنقر فلا شيء تحته.

                     الأسماءُ أدناه هي ما يظهر بندٌ واحدٌ على الأقل بفضله. --}}
                @if (canAny([
                    'hr_dashboard.index', 'performance.index',
                    'employees.index',
                    'attendance_records.index',
                    'contracts.index',
                    'payroll.index',
                    'loans.index', 'loans.create',
                    'deductions.index',
                    'leave_requests.index',
                    'payslips.index',
                ]))
                <li class="nav-item {{ request()->is("$getLocal/hr*") || request()->is("$getLocal/employee*") || request()->is("$getLocal/attendance*") || request()->is("$getLocal/Contracts*") || request()->is("$getLocal/Payroll*") || request()->is("$getLocal/salarySlip*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-users"></i>
                        <span class="menu-title">الموارد البشرية</span>
                    </a>
                    <ul class="menu-content">
                        {{-- كلُّ بندٍ بصلاحية شاشته (`<الشاشة>.index`) — ولا صلاحياتِ «هو»:
                             ما يراه الموظفُ داخل الشاشة يحدّده نطاقُه (`data_scope_own`). --}}
                        @if (hasPermission('hr_dashboard.index'))
                            <li>
                                <a href="{{ route('hr.dashboard.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/hr/dashboard*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">لوحة الموارد البشرية</span>
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('employees.index'))
                            <li>
                                <a href="{{ route('employee.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/employee*") && !request()->is("$getLocal/employee/visit-justifications*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">إدارة الموظفين</span>
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('attendance_records.index'))
                            {{-- `attendance_records.index` مسارٌ غير معرَّف، و`route()` عليه
                                 يرمي استثناءً يُسقط بقيّةَ القائمة. البديلُ هو شاشةُ
                                 البصمات في وحدة الموارد البشرية. --}}
                            <li>
                                <a href="{{ route('hr.attendance-punches.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/hr/attendance-punches*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">الحضور والانصراف</span>
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('contracts.index'))
                            <li>
                                <a href="{{ route('Contracts.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/Contracts*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">عقود الموظفين</span>
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('payroll.index'))
                            <li>
                                <a href="{{ route('PayrollProcess.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/Payroll*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">مسيرات الرواتب</span>
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('loans.create'))
                            <li>
                                <a href="{{ route('hr.advance-requests.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/hr/advance-requests*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">طلبات السلف</span>
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('loans.index'))
                            <li>
                                <a href="{{ route('hr.advance-approvals.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/hr/advance-approvals*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">إدارة واعتماد السلف</span>
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('deductions.index'))
                            <li>
                                <a href="{{ route('hr.deductions.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/hr/deductions*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">إدارة الخصميات</span>
                                </a>
                            </li>
                        @endif
                        {{-- حارسُ الشاشة `leave_requests.index` — ونطاقُ الموظف يقصره على طلباته --}}
                        @if (hasPermission('leave_requests.index'))
                            <li>
                                <a href="{{ route('attendance.leave_requests.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/attendance/leave-requests*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">طلبات الإجازة</span>
                                </a>
                            </li>
                        @endif

                        {{-- القسائم: حارسُ الشاشة `payslips.index` — والنطاقُ يحدّد قسائمَ من --}}
                        @if (hasPermission('payslips.index'))
                            <li>
                                <a href="{{ route('salarySlip.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/salarySlip*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">قسائم الرواتب</span>
                                </a>
                            </li>
                        @endif

                        @if (hasPermission('performance.index'))
                            <li>
                                <a href="{{ route('hr.employee_performance.index') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/hr/employee-performance*") ? 'active' : '' }}"></i>
                                    <span class="menu-item">أداء الموظفين</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                @else
                    @if (hasPermission('hr_dashboard.index'))
                        <li class="nav-item {{ request()->is("$getLocal/hr/dashboard*") ? 'active' : '' }}">
                            <a href="{{ route('hr.dashboard.index') }}">
                                <i class="feather icon-user-check"></i>
                                <span class="menu-title">لوحة أداء الموظفين</span>
                            </a>
                        </li>
                    @endif
                @endif
            @else
                {{-- الرئيسيه --}}

                {{-- «الرئيسية» — كانت أوّلَ بندٍ في فرع الموظّف ولا نظير لها هنا:
                     الفرعُ الكامل كان يبدأ بالمبيعات مباشرةً، فلا مخرجَ من القائمة
                     إلى لوحة النظام إلّا من الشريط العلوي. وكلُّ شريطٍ في الموديولات
                     يحمل هذا الرابط على شعاره. --}}
                <li class="nav-item {{ request()->is("$getLocal/dashboard") || request()->is('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}">
                        <i class="feather icon-home"></i>
                        <span class="menu-title">الرئيسية</span>
                    </a>
                </li>

              @if (canAny([
                        'sales.invoices.view', 'sales.invoices.create', 'sales.invoices.edit', 'sales.invoices.delete',
                        'sales.quotes.view', 'sales.quotes.create', 'sales.quotes.edit', 'sales.quotes.delete',
                        'sales.payments.view', 'sales.payments.create', 'sales.payments.edit', 'sales.payments.delete',
                        'sales.credit_notes.view', 'sales.credit_notes.create', 'sales.credit_notes.edit', 'sales.credit_notes.delete',
                        'sales.returns.view', 'sales.returns.create',
                    ]))
                @php
                    // جلب جميع الإعدادات من جدول application_settings
                    $settings = \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();
                @endphp


            @endif


            {{-- مجموعة «المبيعات» — الجزء المشترك `sales::layouts.partials.sales-menu`.
                 كانت هنا نسخةٌ ثالثة تفحص بـ`@can('sales.quotes.create')`: أسماءُ
                 صلاحياتٍ من النظام القديم، و`@can` تسأل Spatie مباشرةً بينما
                 صلاحيات هذا النظام على الدور — انظر `hasPermission()`. --}}
            @php
                /* إعدادات التطبيقات: كانت تُقرأ داخل كتلة المبيعات، وتقرؤها
                   مجموعاتٌ بعدها (العملاء ونقاط البيع والمتجر والتصنيع). فبقيت
                   هنا كي لا تختفي بذهاب الكتلة. */
                $settings = $settings ?? \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();
            @endphp

            @include('sales::layouts.partials.sales-menu')

            {{-- الإقفال اليومي — بندٌ في `sales::layouts.sidebar` وفي فرع الموظّف
                 من هذا الملف، وغائبٌ عن الفرع الكامل. ولا يصلح وضعُه في مجموعة
                 «إعدادات الإحصائيات» أدناه: تلك محروسةٌ بـ`! isEmployee()` فلا
                 تُعرض لصاحب `employee_id` أصلاً — وهو الغالب هنا. فبندٌ مستقلٌّ
                 إلى جوار قائمة المبيعات، كما هو في شريط الوحدة. --}}
            @if (hasPermission('sales.daily_closing.view'))
                <li class="nav-item {{ request()->is("$getLocal/sales/daily-closing*") ? 'active' : '' }}">
                    <a href="{{ route('daily-closing.index') }}">
                        <i class="feather icon-lock"></i>
                        <span class="menu-title">الإقفال اليومي</span>
                    </a>
                </li>
            @endif

            {{-- المشتريات.

                 دورة المشتريات كانت غائبة عن هذه القائمة كلّياً — لا رابط
                 واحد لأيّ شاشةٍ فيها. الوحدة هنا `purchase_cycle` (لا
                 `purchases`)، وفحص تفعيلها عبر `ApplicationRegistry::isActive()`
                 لا عبر `$settings['purchases']` كما في المبيعات: هذا هو
                 المصدر الحديث المُوحَّد لحالة الوحدات.

                 أوامر الشراء وطلبات عروض الأسعار تحديداً مُقيَّدتان أيضاً
                 بحدود اشتراك فوترة (`SubscriptionGate::allows()`) — إن كانت
                 باقة العميل لا تشمل الميزة يختفي رابطها، فلا يُغرى المستخدم
                 بفتح شاشةٍ سترفض الحفظ. بقيّة شاشات القسم لا صلة لها بمفتاح
                 اشتراكٍ بعد، فبلا هذا الشرط.

                 صلاحيات الشاشات المُرحَّلة إلى كتالوج `PurchasesPermissions`
                 (Orders/Quotations/ViewPurchasePrice/ReturnsInvoice/CityNotices/
                 PurchasePayment/Settings/Dashboard/Reports) تُفحص بأسمائها
                 الجديدة بنقطة (`purchases.orders.view`...). وما بقي على
                 النظام القديم (فواتير الشراء، الموردون، طلبات الشراء) يُفحص
                 بأسمائه القديمة نفسها التي يحرسها الكنترولر فعلاً؛ فحصه
                 بالاسم الجديد كان سيُخفي الرابط عن كل من يملك الصلاحية
                 القديمة اليوم بلا داعٍ. --}}
            @if (\App\Services\Settings\ApplicationRegistry::isAvailable('purchase_cycle'))
                {{-- بوّابةُ القسم بالبادئة لا بتعدادٍ يدوي: كان التعدادُ
                     يُسقط `receiving` و`supplier_wallet` رغم أن القائمة
                     تعرض بنديهما، ويقتصر على صلاحيات «عرض» فمن مُنح
                     الإضافةَ وحدَها لم يرَ القسم. --}}
                @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\PurchasesPermissions::PREFIX))
                    @php
                        $purchasesGate = app(\App\Services\Fawtra\SubscriptionGate::class);
                        $purchasesPrefixes = ['Orders', 'Quotations', 'ViewPurchasePrice', 'OrdersRequest', 'invoicePurchases', 'ReturnsInvoice', 'CityNotices', 'SupplierManagement', 'PaymentSupplier', 'reports/employees', 'reports/suppliers', 'reports/products', 'reports/payments', 'reports/additional', 'purchase-settings', 'purchases-dashboard'];
                        $purchasesActive = false;
                        foreach ($purchasesPrefixes as $purchasesPrefix) {
                            if (request()->is("$getLocal/$purchasesPrefix*")) {
                                $purchasesActive = true;
                                break;
                            }
                        }
                    @endphp
                    <li class="nav-item {{ $purchasesActive ? 'active open' : '' }}">
                        <a href="#"><i class="feather icon-shopping-bag"></i><span class="menu-title" data-i18n="Dashboard">المشتريات</span></a>
                        <ul class="menu-content">

                            {{-- بندُ اللوحة بالبادئة كمتحكّمه: `PurchasesDashboardController`
                                 صار يقبل أيَّ صلاحيةِ مشتريات، فحصرُه هنا على
                                 `dashboard.view` — وهي نادراً ما تُمنح مع صلاحيات
                                 العمل — كان يُخفي المقصدَ الوحيد المضمونَ بلوغُه. --}}
                            @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\PurchasesPermissions::PREFIX))
                                <li><a href="{{ route('purchases.dashboard') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/purchases-dashboard") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="Analytics">لوحة تحكّم المشتريات</span></a>
                                </li>
                            @endif

                            @if ($purchasesGate->allows('purchase_orders'))
                                @can('purchases.orders.view')
                                    <li><a href="{{ route('PurchaseRequests.index') }}"><i
                                                class="feather icon-circle {{ request()->is("$getLocal/PurchaseRequests*") ? 'active' : '' }}"></i><span
                                                class="menu-item" data-i18n="Analytics">{{ trans('main_trans.Purchase_Orders') }}</span></a>
                                    </li>
                                @endcan
                            @endif

                            @can('purchases.order_requests.view')
                                <li><a href="{{ route('PurchaseOrders.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/PurchaseOrders*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="eCommerce">أوامر الشراء</span></a>
                                </li>
                            @endcan

                            @can('purchases.receiving.view')
                                <li><a href="{{ route('PurchaseReceiving.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/PurchaseReceiving*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="eCommerce">استلام المشتريات</span></a>
                                </li>
                            @endcan

                            @if ($purchasesGate->allows('purchase_quotations'))
                                @can('purchases.quotations.view')
                                    <li><a href="{{ route('Quotations.index') }}"><i
                                                class="feather icon-circle {{ request()->is("$getLocal/Quotations*") ? 'active' : '' }}"></i><span
                                                class="menu-item" data-i18n="Analytics">طلبات عروض الأسعار</span></a>
                                    </li>
                                @endcan
                            @endif

                            @can('purchases.price_quotations.view')
                                <li><a href="{{ route('ViewPurchasePrice.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/ViewPurchasePrice*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="eCommerce">عروض أسعار الموردين</span></a>
                                </li>
                            @endcan

                            @can('purchases.invoices.view')
                                <li><a href="{{ route('invoicePurchases.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/invoicePurchases*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="Analytics">فواتير المشتريات</span></a>
                                </li>
                            @endcan

                            @can('purchases.returns.view')
                                <li><a href="{{ route('ReturnsInvoice.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/ReturnsInvoice*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="eCommerce">مرتجعات المشتريات</span></a>
                                </li>
                            @endcan

                            @can('purchases.debit_notices.view')
                                <li><a href="{{ route('CityNotices.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/CityNotices*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="eCommerce">إشعارات مدينة (مشتريات)</span></a>
                                </li>
                            @endcan

                            @can('purchases.payments.view')
                                <li><a href="{{ route('PaymentSupplier.indexPurchase') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/PaymentSupplier*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="eCommerce">مدفوعات الموردين</span></a>
                                </li>
                            @endcan

                            @can('purchases.suppliers.view')
                                <li><a href="{{ route('SupplierManagement.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/SupplierManagement*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="Analytics">الموردون</span></a>
                                </li>
                            @endcan

                            {{-- محافظ الموردين — رصيدنا لدى المورد (دفعات مقدَّمة) --}}
                            @can('purchases.supplier_wallet.view')
                                <li><a href="{{ route('purchases.supplier-wallet.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/supplier-wallets*") ? 'active' : '' }}"></i><span
                                            class="menu-item">محافظ الموردين</span></a>
                                </li>
                            @endcan

                            @can('purchases.reports.view')
                                <li><a href="{{ route('ReportsPurchases.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/reports/employees*") || request()->is("$getLocal/reports/suppliers*") || request()->is("$getLocal/reports/products*") || request()->is("$getLocal/reports/payments*") || request()->is("$getLocal/reports/additional*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="Analytics">تقارير المشتريات</span></a>
                                </li>
                            @endcan

                            @can('purchases.settings.view')
                                <li><a href="{{ route('purchases.settings.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/purchase-settings*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="eCommerce">إعدادات المشتريات</span></a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
            @endif

            @if (canAny([
                        'clients_view_all_clients',
                        'clients_view_own_clients',
                        /* «إضافة الملاحظات والمواعيد» أُسقطت: المواعيد في مجموعة
                           «الحجوزات»، فما كانت تفتح هنا شيئاً. */
                        'clients_dashboard_view',
                        'clients_edit_client_settings',
                        'clients_crm_view',
                        'clients_daily_itinerary_view',
                        'clients_daily_itinerary_add_for_others',
                        // من يملك المحفظة وحدها تظهر له المجموعة لأجلها
                        'clients.wallet.view',
                        /* حرّاسُ الشاشات المنقولة من `client::layouts.sidebar`.
                           بوّابةُ المجموعة اتحادُ حرّاس بنودها — وإلّا حجبت
                           بوّابةٌ ضيّقة رابطاً يملكه صاحبُه. */
                        'sales.payments.view',
                        'clients_invoice_transfer_view',
                        'clients_competitors_view',
                        'clients_visit_justifications_view_all',
                        'clients_view_forgotten_visits_own',
                        'clients_view_forgotten_visits_all',
                    ])
                    /* بندان محروسان بحارسين معاً، فيُذكران بالشرط نفسِه
                       لئلّا تُفتح المجموعةُ على عنوانٍ بلا بنود: دليلُ التواصل،
                       والديونُ المشكوك فيها (ومعها مفتاحُها في إعدادات الوحدة). */
                    || (hasPermission('clients_visit_justifications_view_all') && hasPermission('clients_assign_employees'))
                    || (hasPermission('clients_manage_debts') && \App\Models\ClientModuleSetting::current()->doubtful_debts_enabled))
                @if (isset($settings['customers']) && $settings['customers'] === 'active')
                    <li class="nav-item">
                        <a href="#">
                            <i class="feather icon-user"></i> <!-- أيقونة العملاء -->
                            <span class="menu-title" data-i18n="Dashboard">{{ trans('main_trans.Customers') }}</span>
                        </a>
                        <ul class="menu-content">
                            @canany(['clients_view_all_clients', 'clients_view_own_clients'])
                                <li><a href="{{ route('clients.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Customer_management') }}</span>
                                    </a>
                                </li>
                            @endcanany

                            {{-- محافظ العملاء — في الموضعين كذلك، وبحارس
                                 `@can` نفسِه. انظر التعليق في فرع الموظّف. --}}
                            @permission('clients.wallet.view')
                                <li>
                                    <a href="{{ route('clients.wallet.index') }}">
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item">محافظ العملاء</span>
                                    </a>
                                </li>
                            @endpermission

                            {{-- لوحةُ العملاء.

                                 كانت تحمل `main_trans.Customer_management` — أي
                                 «ادارة العملاء» نفسَها — فظهر في المجموعة بندان
                                 بالاسم ذاته: أحدهما قائمةُ العملاء والآخر لوحتُهم،
                                 ولا يُعرف أيُّهما أيّ.

                                 وكانت داخل `@canany(view_all|view_own)` وحارسُها هي
                                 `clients_dashboard_view|clients_view_all_clients`،
                                 فمن يملك اللوحةَ وحدها لم يرها. الحارسُ الخارجي أُسقط. --}}
                            @if (canAny(['clients_dashboard_view', 'clients_view_all_clients']))
                                <li><a href="{{ route('clients.dashboard') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">لوحة العملاء</span>
                                    </a>
                                </li>
                            @endif

                            @can('clients_add_client')
                                <li><a href="{{ route('clients.create') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Add_a_new_customer') }}</span>
                                    </a>
                                </li>
                            @endcan



                            @permission('clients_crm_view')
                                    <li><a href="{{ route('clients.mang_client') }}"><i class="feather icon-circle"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.Customer_relationship_management') }}</span>
                                        </a>
                                    </li>
                            @endpermission
                            @if ((hasPermission('clients_daily_itinerary_view') || hasPermission('clients_daily_itinerary_add_for_others')))
                                <li><a href="{{ route('itinerary.daily') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">خط سيري اليومي</span>
                                    </a>
                                </li>
                            @endif
                            @if (hasPermission('clients_itinerary_view'))
                                <li><a href="{{ route('itinerary.list') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item" data-i18n="eCommerce"> خط السير</span>
                                    </a>
                                </li>
                                {{-- لوحةُ خط السير — كانت في فرع الموظّف وفي
                                     `client::layouts.sidebar`، وغائبةً عن هذا الفرع. --}}
                                <li><a href="{{ route('itinerary.dashboard') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/itinerary/dashboard*") ? 'active' : '' }}"></i><span
                                            class="menu-item">لوحة تحكّم خط السير</span>
                                    </a>
                                </li>
                            @endif

                            <!-- رابط تبريرات الزيارات للموظفين -->
                            @if (auth()->user()->isEmployee() && hasPermission('clients_visit_justifications_view_own|clients_visit_justifications_view_all'))
                                <li><a href="{{ route('employee.visit-justifications.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">تبريراتي</span>
                                    </a>
                                </li>
                            @endif

                            {{-- رابط إدارة تبريرات الزيارات للمديرين — كان دوراً خاماً
                                 (`hasAnyRole`) يتجاوز نظام الصلاحيات كلياً، صار صلاحيةً حقيقية. --}}
                            @if(hasPermission('clients_visit_justifications_view_all'))
                                <li><a href="{{ route('admin.visit-justifications.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">إدارة تبريرات الزيارات</span>
                                    </a>
                                </li>
                            @endif

                            {{-- «اعدادات المجموعات» تفتح شجرةَ الفروع، وحارسُها كان
                                 `clients_edit_client_settings` — صلاحيةَ إعدادات
                                 العملاء، لا صلةَ لها بالفروع. فمندوبٌ يملكها كان يرى
                                 البندَ ويُردّ بـ403 من `BranchesController`. الحارسُ
                                 الآن هو ما يفحصه ذاك المتحكّم فعلاً. --}}
                            @if (hasPermission('branches.view'))
                                <li><a href="{{ route('branches.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item" data-i18n="eCommerce">اعدادات المجموعات</span>
                                    </a>
                                </li>
                            @endif


                    @can('clients_edit_client_settings')
                        <li><a href="{{ route('clients.setting') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Client_settings') }}</span>
                            </a>
                        </li>
                    @endcan


                            {{-- ══════ شاشاتٌ كانت في `client::layouts.sidebar` وحدها ══════

                                 ستُّ شاشاتٍ بحرّاسها هناك حرفاً بحرف: إشعاراتُ
                                 سداد بوابة العميل، وتحويلاتُ الفواتير، والديونُ
                                 المشكوك فيها، وكتالوجُ المنافسين، وقائمةُ
                                 التواصل، ولوحةُ الزيارات الجارية والمنسية. --}}

                            {{-- إشعاراتُ السداد — بصلاحية سند القبض نفسِها التي يحرس
                                 بها المسارُ، فلا يظهر رابطٌ يردّ ضاغطَه --}}
                            @if (hasPermission('sales.payments.view'))
                                <li><a href="{{ route('clients.payment_notices.index') }}"><i
                                            class="feather icon-circle"></i><span
                                            class="menu-item">إشعارات السداد</span></a>
                                </li>
                            @endif

                            {{-- تسليمُ الفواتير — بلا رابطٍ هنا تبقى «واردة بانتظار ردّك» بلا مدخل --}}
                            @if (hasPermission('clients_invoice_transfer_view'))
                                <li><a href="{{ route('invoice-transfers.index') }}"><i
                                            class="feather icon-circle"></i><span
                                            class="menu-item">تحويلات الفواتير</span></a>
                                </li>
                            @endif

                            {{-- المتحكّم يفحص `clients_manage_debts` وحدَها؛ و`doubtful_debts_view`
                                 كانت فوقها فتحجب البندَ عمّن يملك الأولى. --}}
                            @if (\App\Models\ClientModuleSetting::current()->doubtful_debts_enabled)
                                @if (hasPermission('clients_manage_debts'))
                                <li><a href="{{ route('doubtful-debts.index') }}"><i
                                            class="feather icon-circle"></i><span
                                            class="menu-item">الديون المشكوك فيها</span></a>
                                </li>
                                @endif
                            @endif

                            {{-- كتالوجُ المنافسين — يملأ «رصد المنافسين» في ملاحظة الزيارة --}}
                            @if (hasPermission('clients_competitors_view'))
                                <li><a href="{{ route('clients.competitors.index') }}"><i
                                            class="feather icon-circle"></i><span
                                            class="menu-item">المنافسون</span></a>
                                </li>
                            @endif

                            @if (hasPermission('clients_visit_justifications_view_all'))
                                @if (hasPermission('clients_assign_employees'))
                                <li><a href="{{ route('clients.employees_directory') }}"><i
                                            class="feather icon-circle"></i><span
                                            class="menu-item">{{ trans('main_trans.Contact_list') }}</span></a>
                                </li>
                                @endif
                            @endif

                            @if (hasPermission('clients_view_forgotten_visits_own|clients_view_forgotten_visits_all|clients_view_itinerary_dashboard'))
                                <li><a href="{{ route('visits.itinerary-dashboard') }}"><i
                                            class="feather icon-circle"></i><span
                                            class="menu-item">الزيارات الجارية والمنسية</span></a>
                                </li>
                            @endif

                            {{-- طلباتُ الزيارة من بوابة العميل — بصلاحيات المسار
                                 نفسِها (زوجُ العرض)، فلا يظهر رابطٌ يردّ ضاغطَه. --}}
                            @if (hasPermission('clients_itinerary_view|clients_daily_itinerary_add_for_others'))
                                <li><a href="{{ route('clients.visit_requests.index') }}"><i
                                            class="feather icon-circle"></i><span
                                            class="menu-item">طلبات الزيارة</span></a>
                                </li>
                            @endif

            </ul>
            </li>
              @endif
            @endif

            {{-- محافظ العملاء — بندٌ رئيسيّ في فرع المدير كذلك، لنفس العلّة:
                 مجموعة «العملاء» مطويّة، فالرابط داخلها لا يُرى. --}}
            @permission('clients.wallet.view')
                <li class="nav-item {{ request()->is("$getLocal/clients/clients_management/client-wallets*") ? 'active' : '' }}">
                    <a href="{{ route('clients.wallet.index') }}">
                        <i class="feather icon-credit-card"></i>
                        <span class="menu-title">محافظ العملاء</span>
                    </a>
                </li>
            @endpermission

            @if (! auth()->user()->isEmployee())
                @if (canAny(['sales.collections_targets.create', 'clients.reports.targets', 'sales.collections_targets.report', 'clients_visit_justifications_view_all']))
                <li class="nav-item">
                    <a href="#">
                        <i class="feather icon-monitor"></i>
                        <span class="menu-title">اعدادات الاحصائيات</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('sales.collections_targets.create'))
                        <li>
                            <a href="{{ route('employee_targets.index') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item" data-i18n="Start Sale">اضافة الاهداف للمناديب</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('target.show') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item" data-i18n="Sessions">اضافة الهدف الشهري للمناديب</span>
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('visitTarget') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item" data-i18n="Sessions">اضافة الهدف السنوي للزيارات</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('clients.reports.targets'))
                        <li>
                            <a href="{{ route('target.client') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item" data-i18n="POS Reports">احصائيات وتصنيف العملاء</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('sales.collections_targets.report'))
                        <li>
                            <a href="{{ route('daily_closing_entry') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item" data-i18n="POS Reports">تقرير التحصيل اليومي</span>
                            </a>
                        </li>
                        @endif

                        {{-- الإقفالُ اليومي — كان في **فرع الموظّف وحدَه** وفي
                             `sales::layouts.sidebar`، وغائباً عن هذا الفرع. --}}
                        @if (hasPermission('sales.daily_closing.view'))
                        <li>
                            <a href="{{ route('daily-closing.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/sales/daily-closing*") ? 'active' : '' }}"></i>
                                <span class="menu-item">الإقفال اليومي</span>
                            </a>
                        </li>
                        @endif

                        <!-- رابط إدارة تبريرات الزيارات للمديرين -->

                        @if (hasPermission('clients_visit_justifications_view_all'))
                        <li>
                            <a href="{{ route('admin.visit-justifications.index') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item">إدارة تبريرات الزيارات</span>
                            </a>
                        </li>
                        @endif

                    </ul>
                </li>
                @endif
            @endif



            {{-- نقاط البيع — بنودُه وحرّاسه من `pos::layouts.sidebar`.

                 **كانت بنودُه الأربعة `href=""`** — روابطَ فارغة تُعيد تحميل
                 الصفحة نفسها، بلا أيّ حارس صلاحية على واحدٍ منها. وشاشاتُ
                 الموديول الحقيقية (الدرج النقدي، أجهزة الكاشير، الطابعات،
                 الورديات وتصنيفاتها، بانتظار التأكيد، الرئيسية) لم يكن لها
                 ذكرٌ هنا أصلاً.

                 والحارسُ الخارجي كان قائمةً من صلاحيات البيع وحدها، فمن يملك
                 «تقارير نقاط البيع» أو «إدارة الأجهزة» ولا يبيع لا يرى
                 المجموعة. صار اتحادَ حرّاس بنودها كما في شريط الموديول.

                 @see \Modules\Pos\resources\views\layouts\sidebar --}}
            @php
                $posOn = ($settings['pos'] ?? null) === 'active';

                $posCanSell = canAny([
                    'points_sale_add_discount',
                    'points_sale_edit_product_prices',
                    'points_sale_open_sessions_all',
                    'points_sale_open_sessions_own',
                ]);
                $posCanSessions = canAny([
                    'points_sale_view_own_sessions',
                    'points_sale_close_sessions_all',
                    'points_sale_close_sessions_own',
                    'points_sale_confirm_close_sessions_all',
                    'points_sale_confirm_close_sessions_own',
                ]);
                $posCanConfirm = canAny(['points_sale_confirm_close_sessions_all', 'points_sale_confirm_close_sessions_own']);
                $posCanReports = canAny(['pos_view_reports', 'pos.reports.profit', 'pos.reports.variance']);
                $posCanSettings = hasPermission('pos_manage_settings');
                $posCanCashDrawer = canAny([
                    'pos.cash_drawer.view',
                    'pos.cash_drawer.pay_in',
                    'pos.cash_drawer.pay_out',
                    'pos.cash_drawer.drop',
                    'pos.cash_drawer.approve',
                ]);
                $posCanDevices = canAny(['pos.devices.view', 'pos.devices.manage']);
                $posCanShifts = canAny(['pos.shifts.view', 'pos.shifts.manage']);

                $posAny = $posCanSell || $posCanSessions || $posCanReports || $posCanSettings
                    || $posCanDevices || $posCanShifts || $posCanCashDrawer;
            @endphp

            @if ($posOn && $posAny)
                <li class="nav-item {{ request()->is("$getLocal/pos*") || request()->is("$getLocal/sales_start*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-monitor"></i>
                        <span class="menu-title" data-i18n="POS">{{ trans('main_trans.Point_of_Sale') }}</span>
                    </a>
                    <ul class="menu-content">

                        <li class="{{ request()->routeIs('dashboard.pos') ? 'active' : '' }}">
                            <a href="{{ route('dashboard.pos') }}">
                                <i class="feather icon-home"></i>
                                <span class="menu-item">رئيسية نقاط البيع</span>
                            </a>
                        </li>

                        @if ($posCanSell && hasPermission('pos.sell'))
                            <li class="{{ request()->routeIs('sales_start.index') ? 'active' : '' }}">
                                <a href="{{ route('sales_start.index') }}">
                                    <i class="feather icon-shopping-cart"></i>
                                    <span class="menu-item">{{ trans('main_trans.Start_Sale') }}</span>
                                </a>
                            </li>
                        @endif

                        @if ($posCanSessions && hasPermission('points_sale_view_own_sessions'))
                            <li class="{{ request()->routeIs('pos.sessions.index') ? 'active' : '' }}">
                                <a href="{{ route('pos.sessions.index') }}">
                                    <i class="feather icon-clock"></i>
                                    <span class="menu-item">{{ trans('main_trans.Sessions') }}</span>
                                </a>
                            </li>
                        @endif

                        {{-- بانتظار التأكيد — العهدةُ لا تُعتمد حتى يؤكّدها مشرف --}}
                        @if ($posCanConfirm && hasPermission('points_sale_view_own_sessions'))
                            <li>
                                <a href="{{ route('pos.sessions.index', ['status' => 'pending_review']) }}">
                                    <i class="feather icon-check-square"></i>
                                    <span class="menu-item">بانتظار التأكيد</span>
                                </a>
                            </li>
                        @endif

                        @if ($posCanCashDrawer && hasPermission('pos.cash_drawer.view'))
                            <li class="{{ request()->routeIs('pos.cash_drawer.*') ? 'active' : '' }}">
                                <a href="{{ route('pos.cash_drawer.index') }}">
                                    <i class="feather icon-dollar-sign"></i>
                                    <span class="menu-item">الدرج النقدي</span>
                                </a>
                            </li>
                        @endif

                        @if ($posCanDevices && canAny(['pos.devices.view', 'pos.devices.manage']))
                            <li class="{{ request()->routeIs('pos.settings.devices.*') ? 'active' : '' }}">
                                <a href="{{ route('pos.settings.devices.index') }}">
                                    <i class="feather icon-hard-drive"></i>
                                    <span class="menu-item">أجهزة الكاشير</span>
                                </a>
                            </li>
                            @if (canAny(['pos.printers.manage', 'pos.devices.view']))
                            <li class="{{ request()->routeIs('pos.settings.printers.*') ? 'active' : '' }}">
                                <a href="{{ route('pos.settings.printers.index') }}">
                                    <i class="feather icon-printer"></i>
                                    <span class="menu-item">الطابعات</span>
                                </a>
                            </li>
                            @endif
                        @endif

                        @if ($posCanShifts && canAny(['pos.shifts.view', 'pos.shifts.manage']))
                            <li class="{{ request()->routeIs('pos.settings.shift.*') ? 'active' : '' }}">
                                <a href="{{ route('pos.settings.shift.index') }}">
                                    <i class="feather icon-calendar"></i>
                                    <span class="menu-item">الورديات</span>
                                </a>
                            </li>
                            @if (canAny(['pos.shift_categories.view', 'pos.shift_categories.manage']))
                            <li class="{{ request()->routeIs('pos.settings.shift_categories.*') ? 'active' : '' }}">
                                <a href="{{ route('pos.settings.shift_categories.index') }}">
                                    <i class="feather icon-tag"></i>
                                    <span class="menu-item">تصنيفات الورديات</span>
                                </a>
                            </li>
                            @endif
                        @endif

                        @if ($posCanReports && hasPermission('pos_view_reports'))
                            <li class="{{ request()->routeIs('pos_reports.*') ? 'active' : '' }}">
                                <a href="{{ route('pos_reports.index') }}">
                                    <i class="feather icon-bar-chart-2"></i>
                                    <span class="menu-item">{{ trans('main_trans.POS_Reports') }}</span>
                                </a>
                            </li>
                        @endif

                        @if ($posCanSettings)
                            <li class="{{ request()->routeIs('pos.settings.index') ? 'active' : '' }}">
                                <a href="{{ route('pos.settings.index') }}">
                                    <i class="feather icon-settings"></i>
                                    <span class="menu-item">{{ trans('main_trans.POS_Settings') }}</span>
                                </a>
                            </li>
                        @endif

                    </ul>
                </li>
            @endif

            {{-- المتجر الإلكتروني — بنودُه وحرّاسه من `onlinestore::layouts.sidebar`.

                 كان هنا بندان فقط (إدارة المحتوى والإعدادات) من ثلاثين شاشةً
                 يملكها الموديول: الطلبات وعملاء المتجر ومورّدوه، والمنتجات
                 وأصنافها وعلاماتها وتقييماتها وأسئلتها، والمخازن وفواتير
                 الشراء والأذون، والخزائن والتحويل بينها وطرق الدفع، والكوبونات
                 والبانرات، والدول والمدن وطرق الشحن، وصفحات المتجر، وقوالب
                 البريد وسجلّاته، وتقاريره الثمانية. فمن يدير المتجر من القائمة
                 الموحّدة لم يكن يبلغ شيئاً منها إلّا بكتابة الرابط.

                 **والحارسُ الخارجي كان `@can('online_store_content_management')`**
                 وحدَه — صلاحيةَ إدارة المحتوى: فمن يملك «عرض الطلبات» أو
                 «تقارير المتجر» ولا يملكها لا يرى المجموعة أصلاً. صار اتحادَ
                 صلاحيات بنودها، وكلُّ بندٍ محروسٌ بصلاحيته كما في شريط الموديول.

                 وقفلُ الباقة لم يُنقَل: هذه القائمة لا تحمل أقفالاً خارج
                 المبيعات والمشتريات، ومسارُ كلِّ شاشةٍ محروسٌ بوسيط `plan:`
                 فيقود إلى شاشة الاشتراك عند النقر.

                 @see \Modules\OnlineStore\resources\views\layouts\sidebar --}}
            @php
                $storeItems = [
                    // إدارة الطلبات
                    ['route' => 'online_orders.index', 'icon' => 'shopping-bag', 'permission' => 'online_store.orders.view', 'label' => 'طلبات المتجر'],
                    ['route' => 'store_clients.index', 'icon' => 'users', 'permission' => 'online_store.clients.view', 'label' => 'عملاء المتجر'],
                    ['route' => 'store_suppliers.index', 'icon' => 'truck', 'permission' => 'purchases.suppliers.view', 'label' => 'موردو المتجر'],
                    // إدارة المنتجات
                    ['route' => 'store_products.index', 'icon' => 'package', 'permission' => 'online_store.products.view', 'label' => 'منتجات المتجر'],
                    ['route' => 'store_categories.index', 'icon' => 'grid', 'permission' => 'online_store.categories.view', 'label' => 'أصناف المنتجات'],
                    ['route' => 'brands.index', 'icon' => 'award', 'permission' => 'online_store.brands.view', 'label' => 'العلامات التجارية'],
                    ['route' => 'product_reviews.index', 'icon' => 'star', 'permission' => 'online_store.reviews.view', 'label' => 'تقييمات المنتجات'],
                    ['route' => 'product_questions.index', 'icon' => 'help-circle', 'permission' => 'online_store.questions.view', 'label' => 'أسئلة المنتجات'],
                    // إدارة المخزون
                    ['route' => 'store_storehouses.index', 'icon' => 'home', 'permission' => 'stock.warehouses.view', 'label' => 'مخازن المتجر'],
                    ['route' => 'store_purchase_invoices.index', 'icon' => 'file-text', 'permission' => 'purchases.invoices.view', 'label' => 'فواتير شراء المتجر'],
                    ['route' => 'online_store.permits.index', 'icon' => 'clipboard', 'permission' => 'stock.permits.view', 'label' => 'الأذون المخزنية للمتجر'],
                    // المالية
                    ['route' => 'store_treasury.index', 'icon' => 'dollar-sign', 'permission' => 'account.treasury.view', 'label' => 'خزائن المتجر وحساباته'],
                    ['route' => 'store_treasury.transfer_create', 'icon' => 'repeat', 'permission' => 'account.treasury_transfers.view', 'label' => 'تحويل بين خزائن المتجر'],
                    ['route' => 'payment_methods.index', 'icon' => 'credit-card', 'permission' => 'online_store.payment_methods.view', 'label' => 'طرق الدفع في المتجر'],
                    // التسويق والعروض
                    ['route' => 'store_coupons.index', 'icon' => 'tag', 'permission' => 'online_store.coupons.view', 'label' => 'كوبونات الخصم'],
                    ['route' => 'store_banners.index', 'icon' => 'image', 'permission' => 'online_store.banners.view', 'label' => 'البانرات والإعلانات'],
                    // الشحن والتوصيل
                    ['route' => 'countries.index', 'icon' => 'globe', 'permission' => 'online_store.geo.view', 'label' => 'الدول'],
                    ['route' => 'cities.index', 'icon' => 'map-pin', 'permission' => 'online_store.geo.view', 'label' => 'المدن'],
                    ['route' => 'shipping_methods.index', 'icon' => 'send', 'permission' => 'online_store.shipping_methods.view', 'label' => 'طرق الشحن'],
                    // المحتوى والصفحات
                    ['route' => 'content_management.index', 'icon' => 'layout', 'permission' => 'online_store_content_management', 'label' => trans('main_trans.Content_management')],
                    ['route' => 'store_pages.index', 'icon' => 'file', 'permission' => 'online_store.pages.view', 'label' => 'صفحات المتجر'],
                    // الاتصالات
                    ['route' => 'email_templates.index', 'icon' => 'mail', 'permission' => 'online_store.email_templates.view', 'label' => 'قوالب البريد'],
                    ['route' => 'email_logs.index', 'icon' => 'inbox', 'permission' => 'online_store.email_logs.view', 'label' => 'سجلات البريد'],
                    // التقارير
                    ['route' => 'onlinestore.reports.index', 'icon' => 'bar-chart-2', 'permission' => 'online_store.reports.view', 'label' => 'تقارير المتجر — نظرة عامة'],
                    ['route' => 'onlinestore.reports.sales', 'icon' => 'trending-up', 'permission' => 'online_store.reports.sales', 'label' => 'تقرير مبيعات المتجر'],
                    ['route' => 'onlinestore.reports.orders', 'icon' => 'shopping-cart', 'permission' => 'online_store.reports.orders', 'label' => 'تقرير طلبات المتجر'],
                    ['route' => 'onlinestore.reports.products', 'icon' => 'box', 'permission' => 'online_store.reports.products', 'label' => 'تقرير منتجات المتجر'],
                    ['route' => 'onlinestore.reports.clients', 'icon' => 'user', 'permission' => 'online_store.reports.clients', 'label' => 'تقرير عملاء المتجر'],
                    ['route' => 'onlinestore.reports.coupons', 'icon' => 'tag', 'permission' => 'online_store.reports.coupons', 'label' => 'تقرير الكوبونات'],
                    ['route' => 'onlinestore.reports.shipping', 'icon' => 'truck', 'permission' => 'online_store.reports.shipping', 'label' => 'تقرير الشحن'],
                    ['route' => 'onlinestore.reports.reviews', 'icon' => 'star', 'permission' => 'online_store.reports.reviews', 'label' => 'تقرير التقييمات'],
                    // الإعدادات
                    ['route' => 'store_settings.index', 'icon' => 'settings', 'permission' => 'online_store.settings.view', 'label' => trans('main_trans.Sittings')],
                ];

                $storeVisible = array_values(array_filter($storeItems, fn (array $item) => \Illuminate\Support\Facades\Route::has($item['route']) && hasPermission($item['permission'])));

                /*
                    بابُ المجموعة صلاحيةٌ من صلاحيات المتجر نفسِه.

                    ستٌّ من شاشات المتجر تستعير صلاحياتِ وحداتٍ أخرى — خزائنُه
                    `account.treasury.view`، وفواتيرُ شرائه `purchases.invoices.view`،
                    ومخازنُه `stock.warehouses.view`، وأذونُه `stock.permits.view`،
                    ومورّدوه `purchases.suppliers.view`. فمن يملك الخزائن وحدها — وهو
                    حالُ مندوب المبيعات — كانت تُفتح له مجموعةُ «المتجر الإلكتروني»
                    ببندٍ واحدٍ لا شأن له بالمتجر، وهو لا يملك في المتجر شيئاً.

                    فالشرطُ الآن: صلاحيةٌ واحدة على الأقل ببادئة `online_store.`
                    (أو صلاحيةُ إدارة المحتوى القديمة). والبنودُ المستعارة تبقى
                    بحرّاسها — فهي تُعرض لمن دخل المتجر أصلاً.
                */
                $storeOwnAccess = auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\OnlineStorePermissions::PREFIX)
                    || hasPermission('online_store_content_management');
            @endphp

            @if ($storeVisible && $storeOwnAccess && isset($settings['ecommerce']) && $settings['ecommerce'] === 'active')
                <li class="nav-item {{ request()->is("$getLocal/online_store/*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-shopping-cart"></i>
                        <span class="menu-title" data-i18n="Online store">{{ trans('main_trans.Online_store') }}</span>
                    </a>
                    <ul class="menu-content">
                        @foreach ($storeVisible as $storeItem)
                            <li class="{{ request()->routeIs($storeItem['route']) ? 'active' : '' }}">
                                <a href="{{ route($storeItem['route']) }}">
                                    <i class="feather icon-{{ $storeItem['icon'] }}"></i>
                                    <span class="menu-item">{{ $storeItem['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endif

            {{-- النظام البيئي للنقاط والولاء — وحدةٌ كاملة لم يكن لها في هذه
                 القائمة بندٌ واحد.

                 تسعٌ وثلاثون شاشةً في `ecosystem::layouts.sidebar`: إعداداتُ
                 النقاط ومعاملاتُها ودفعاتُها وانتهاؤها، ونقاطُ الموظفين
                 والعملاء، والمستوياتُ وسجلُّ الترقيات، والمكافآتُ وطلباتُ
                 الاستبدال، والإنجازاتُ وإنجازاتُ المستخدمين، والتحدّياتُ
                 وفرقُها ولوحةُ المتصدّرين، والإحالاتُ وإعداداتُها، وقوالبُ
                 الإشعارات وسجلُّها، وتقاريرُها الثمانية.

                 ولم يكن يبلغها أحدٌ من هنا إلّا بكتابة الرابط، أو بفتح شاشةٍ
                 من الوحدة فيُبدَّل الشريطُ كلُّه من تحته.

                 ══════ الحراسة ══════

                 **شريطُ الوحدة بلا حارس صلاحيةٍ واحد** — ومسارات الوحدة كذلك
                 (`auth` و`points.system` وحدهما). ولا يصحّ نقلُ واحدٍ وثلاثين
                 بنداً إلى قائمةٍ تُشتقّ من الصلاحيات بلا حارس: القائمةُ هنا
                 تقول «هذا ما تملكه»، فبندٌ بلا حارسٍ يكذب عليها.

                 فالحرّاسُ من `App\Constants\permissions\EcosystemPermissions` —
                 الكتالوجُ القائم فعلاً في النظام والمربوطُ بالأدوار: العرضُ
                 `ecosystem.points.view`، والمستوياتُ `ecosystem.tiers.view`،
                 والمكافآتُ `ecosystem.rewards.view`، والإنجازاتُ والتحدّياتُ
                 والإحالاتُ `ecosystem.engagement.view`، والإعداداتُ
                 `ecosystem.settings`، والتقاريرُ `ecosystem.reports.view`.
                 وهذا فارقٌ مقصودٌ عن شريط الوحدة، لا سهوٌ — ومَن أراد عكسَه
                 فليحرس المساراتِ أوّلاً.

                 ومفاتيحُ الأنظمة الفرعية (`$pointsSettings->enable_*`) منقولةٌ
                 كما هي: نظامٌ مُطفأٌ من إعداداته لا تُعرض شاشاتُه.

                 @see \Modules\Ecosystem\resources\views\layouts\sidebar
                 @see \App\Constants\permissions\EcosystemPermissions --}}
            @php
                try {
                    $pointsSettings = \App\Models\Ecosystem\PointsGeneralSetting::instance();
                    $pointsSystemOn = (bool) $pointsSettings->system_enabled;
                } catch (\Throwable $e) {
                    // الجدولُ يصل بترحيلٍ لاحق — والافتراضُ «مفعَّل» كما في شريط الوحدة
                    $pointsSettings = null;
                    $pointsSystemOn = true;
                }

                /** أمفعَّلٌ هذا النظام الفرعي؟ غيابُ الإعدادات = مفعَّل. */
                $pointsSub = fn (string $flag) => ! $pointsSettings || $pointsSettings->{$flag};

                $ecosystemItems = [
                    ['route' => 'ecosystem.dashboard', 'icon' => 'home', 'label' => 'لوحة النظام البيئي',
                        'show' => hasPermission('ecosystem.view')],

                    // ── النقاط والأرصدة ──
                    ['route' => 'ecosystem.points.settings.index', 'icon' => 'sliders', 'label' => 'إعدادات النقاط',
                        'show' => $pointsSystemOn && hasPermission('ecosystem.settings')],
                    ['route' => 'ecosystem.points.transactions.index', 'icon' => 'repeat', 'label' => 'معاملات النقاط',
                        'show' => $pointsSystemOn && hasPermission('ecosystem.points.view')],
                    ['route' => 'ecosystem.points.batches.index', 'icon' => 'layers', 'label' => 'دفعات النقاط',
                        'show' => $pointsSystemOn && $pointsSub('enable_expiry_system') && hasPermission('ecosystem.points.batch')],
                    ['route' => 'ecosystem.points.expiry-settings.index', 'icon' => 'clock', 'label' => 'إعدادات انتهاء النقاط',
                        'show' => $pointsSystemOn && $pointsSub('enable_expiry_system') && hasPermission('ecosystem.settings')],
                    ['route' => 'ecosystem.points.users.employees', 'icon' => 'user-check', 'label' => 'نقاط الموظفين',
                        'show' => $pointsSystemOn && hasPermission('ecosystem.points.view')],
                    ['route' => 'ecosystem.points.users.clients', 'icon' => 'users', 'label' => 'نقاط العملاء',
                        'show' => $pointsSystemOn && hasPermission('ecosystem.points.view')],

                    // ── المستويات ──
                    ['route' => 'ecosystem.points.tiers.index', 'icon' => 'trending-up', 'label' => 'إدارة المستويات',
                        'show' => $pointsSystemOn && $pointsSub('enable_tiers_system') && hasPermission('ecosystem.tiers.view')],
                    ['route' => 'ecosystem.points.upgrade-logs.index', 'icon' => 'list', 'label' => 'سجل الترقيات',
                        'show' => $pointsSystemOn && $pointsSub('enable_tiers_system') && hasPermission('ecosystem.tiers.view')],

                    // ── المكافآت والاستبدال ──
                    ['route' => 'ecosystem.points.rewards.index', 'icon' => 'gift', 'label' => 'إدارة المكافآت',
                        'show' => $pointsSystemOn && $pointsSub('enable_rewards_system') && hasPermission('ecosystem.rewards.view')],
                    ['route' => 'ecosystem.points.rewards.create', 'icon' => 'plus-circle', 'label' => 'إضافة مكافأة',
                        'show' => $pointsSystemOn && $pointsSub('enable_rewards_system') && hasPermission('ecosystem.rewards.manage')],
                    ['route' => 'ecosystem.points.redemptions.index', 'icon' => 'shopping-bag', 'label' => 'طلبات الاستبدال',
                        'show' => $pointsSystemOn && $pointsSub('enable_rewards_system') && hasPermission('ecosystem.rewards.view')],
                    ['route' => 'ecosystem.points.redemptions.create', 'icon' => 'plus', 'label' => 'طلب استبدال جديد',
                        'show' => $pointsSystemOn && $pointsSub('enable_rewards_system') && hasPermission('ecosystem.redemptions.approve')],

                    // ── الإنجازات ──
                    ['route' => 'ecosystem.points.achievements.index', 'icon' => 'award', 'label' => 'إدارة الإنجازات',
                        'show' => $pointsSystemOn && $pointsSub('enable_achievements_system') && hasPermission('ecosystem.engagement.view')],
                    ['route' => 'ecosystem.points.user-achievements.index', 'icon' => 'check-circle', 'label' => 'إنجازات المستخدمين',
                        'show' => $pointsSystemOn && $pointsSub('enable_achievements_system') && hasPermission('ecosystem.engagement.view')],

                    // ── التحديات ──
                    ['route' => 'ecosystem.points.challenges.index', 'icon' => 'target', 'label' => 'إدارة التحديات',
                        'show' => $pointsSystemOn && $pointsSub('enable_challenges_system') && hasPermission('ecosystem.engagement.view')],
                    ['route' => 'ecosystem.points.challenges.teams.index', 'icon' => 'users', 'label' => 'فرق التحديات',
                        'show' => $pointsSystemOn && $pointsSub('enable_challenges_system') && hasPermission('ecosystem.engagement.view')],
                    ['route' => 'ecosystem.points.challenges.leaderboard.index', 'icon' => 'bar-chart', 'label' => 'لوحة المتصدرين',
                        'show' => $pointsSystemOn && $pointsSub('enable_challenges_system') && hasPermission('ecosystem.engagement.view')],

                    // ── الإحالات ──
                    ['route' => 'ecosystem.points.referrals.index', 'icon' => 'share-2', 'label' => 'إدارة الإحالات',
                        'show' => $pointsSystemOn && $pointsSub('enable_referrals_system') && hasPermission('ecosystem.engagement.view')],
                    ['route' => 'ecosystem.points.referral-settings.index', 'icon' => 'settings', 'label' => 'إعدادات الإحالة',
                        'show' => $pointsSystemOn && $pointsSub('enable_referrals_system') && hasPermission('ecosystem.settings')],

                    // ── الإشعارات ──
                    ['route' => 'ecosystem.points.notification-templates.index', 'icon' => 'mail', 'label' => 'قوالب إشعارات النقاط',
                        'show' => $pointsSystemOn && $pointsSub('enable_notifications_system') && hasPermission('ecosystem.settings')],
                    ['route' => 'ecosystem.points.notification-logs.index', 'icon' => 'inbox', 'label' => 'سجل إشعارات النقاط',
                        'show' => $pointsSystemOn && $pointsSub('enable_notifications_system') && hasPermission('ecosystem.reports.view')],

                    // ── التقارير — متاحةٌ للمراجعة حتى لو أُطفئ النظام (كما في مساراتها) ──
                    ['route' => 'ecosystem.points.reports.points', 'icon' => 'file-text', 'label' => 'تقرير النقاط',
                        'show' => hasPermission('ecosystem.reports.view')],
                    ['route' => 'ecosystem.points.reports.tiers', 'icon' => 'file-text', 'label' => 'تقرير المستويات',
                        'show' => hasPermission('ecosystem.reports.view')],
                    ['route' => 'ecosystem.points.reports.rewards', 'icon' => 'file-text', 'label' => 'تقرير المكافآت',
                        'show' => hasPermission('ecosystem.reports.view')],
                    ['route' => 'ecosystem.points.reports.achievements', 'icon' => 'file-text', 'label' => 'تقرير الإنجازات',
                        'show' => hasPermission('ecosystem.reports.view')],
                    ['route' => 'ecosystem.points.reports.challenges', 'icon' => 'file-text', 'label' => 'تقرير التحديات',
                        'show' => hasPermission('ecosystem.reports.view')],
                    ['route' => 'ecosystem.points.reports.referrals', 'icon' => 'file-text', 'label' => 'تقرير الإحالات',
                        'show' => hasPermission('ecosystem.reports.view')],
                    ['route' => 'ecosystem.points.reports.expiry', 'icon' => 'file-text', 'label' => 'تقرير انتهاء الصلاحية',
                        'show' => hasPermission('ecosystem.reports.view')],
                    ['route' => 'ecosystem.points.reports.daily-summary', 'icon' => 'calendar', 'label' => 'الملخص اليومي للنقاط',
                        'show' => hasPermission('ecosystem.reports.view')],

                    // ── الإعدادات العامة — متاحةٌ دائماً حتى لو أُطفئ النظام، فمنها يُشغَّل ──
                    ['route' => 'ecosystem.points.general-settings.index', 'icon' => 'settings', 'label' => 'الإعدادات العامة للنقاط',
                        'show' => hasPermission('ecosystem.settings')],
                ];

                $ecosystemVisible = array_values(array_filter(
                    $ecosystemItems,
                    fn (array $item) => $item['show'] && \Illuminate\Support\Facades\Route::has($item['route'])
                ));
            @endphp

            @if ($ecosystemVisible)
                <li class="nav-item {{ request()->is("$getLocal/ecosystem*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-star"></i>
                        <span class="menu-title" data-i18n="Ecosystem">نظام النقاط والولاء</span>
                    </a>
                    <ul class="menu-content">
                        @foreach ($ecosystemVisible as $ecosystemItem)
                            <li class="{{ request()->routeIs($ecosystemItem['route']) ? 'active' : '' }}">
                                <a href="{{ route($ecosystemItem['route']) }}">
                                    <i class="feather icon-{{ $ecosystemItem['icon'] }}"></i>
                                    <span class="menu-item">{{ $ecosystemItem['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endif
            {{-- التصنيع — بنودُه وحرّاسه من `manufacturing::layouts.sidebar` نفسِه.

                 **كان الحارسُ `@can('online_store_content_management')`** — صلاحيةَ
                 «إدارة محتوى المتجر الإلكتروني»، لا صلةَ لها بالتصنيع: فمن يملك
                 صلاحيات التصنيع كلَّها ولا يملكها لا يرى المجموعة، ومن يملكها
                 ولا يملك شيئاً من التصنيع يراها كاملةً. صارت البوّابة اتحادَ
                 صلاحيات بنودها، وكلُّ بندٍ محروسٌ بصلاحيته.

                 **وبندان كانا غائبين**: «لوحة التصنيع» و«تقارير التصنيع».

                 وحدُّ الباقة باقٍ: البند خارج الباقة لا يُعرض هنا كما لا يُعرض
                 في شريط الوحدة (`@continue` على `SubscriptionGate::permits`).

                 @see \Modules\Manufacturing\resources\views\layouts\sidebar --}}
            @php
                $manufacturingSections = [
                    ['label' => 'لوحة التصنيع', 'icon' => 'home', 'route' => 'manufacturing.dashboard',
                        'permission' => 'stock.manufacturing_orders.view',
                        'plan' => \App\Constants\planFeatures\StockPlanFeatures::manufacturingOnly()],
                    ['label' => trans('main_trans.Production_Plans'), 'icon' => 'calendar', 'route' => 'manufacturing.production-plans.index',
                        'permission' => 'stock.production_plans.view',
                        'plan' => \App\Constants\planFeatures\StockPlanFeatures::MANUFACTURING],
                    ['label' => trans('main_trans.Manufacturing_Orders'), 'icon' => 'clipboard', 'route' => 'manufacturing.orders.index',
                        'permission' => 'stock.manufacturing_orders.view',
                        'plan' => \App\Constants\planFeatures\StockPlanFeatures::MANUFACTURING],
                    ['label' => trans('main_trans.Bill_of_Materials'), 'icon' => 'layers', 'route' => 'BOM.index',
                        'permission' => 'stock.bom.view',
                        'plan' => \App\Constants\planFeatures\StockPlanFeatures::BOM],
                    ['label' => trans('main_trans.Workstations'), 'icon' => 'cpu', 'route' => 'manufacturing.workstations.index',
                        'permission' => 'stock.workstations.view',
                        'plan' => \App\Constants\planFeatures\StockPlanFeatures::WORKSTATIONS],
                    ['label' => trans('main_trans.Indirect_Costs'), 'icon' => 'percent', 'route' => 'manufacturing.indirectcosts.index',
                        'permission' => 'stock.indirect_costs.view',
                        'plan' => \App\Constants\planFeatures\StockPlanFeatures::MANUFACTURING],
                    ['label' => 'تقارير التصنيع', 'icon' => 'bar-chart-2', 'route' => 'manufacturing.reports.index',
                        'permission' => 'stock.reports.manufacturing',
                        'plan' => \App\Constants\planFeatures\StockPlanFeatures::REPORTS],
                    ['label' => trans('main_trans.Sittings'), 'icon' => 'settings', 'route' => 'Manufacturing.settings.index',
                        'permission' => 'stock.settings.manufacturing',
                        'plan' => \App\Constants\planFeatures\StockPlanFeatures::MANUFACTURING],
                ];

                $manufacturingVisible = array_values(array_filter($manufacturingSections, function (array $section) {
                    return \Illuminate\Support\Facades\Route::has($section['route'])
                        && hasPermission($section['permission'])
                        && \App\Services\Fawtra\SubscriptionGate::permits($section['plan']);
                }));
            @endphp

            @if ($manufacturingVisible && isset($settings['manufacturing']) && $settings['manufacturing'] === 'active')
                <li class="nav-item {{ request()->is("$getLocal/Manufacturing/*") || request()->is("$getLocal/manufacturing/*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-layers"></i>
                        <span class="menu-title" data-i18n="Manufacturing">{{ trans('main_trans.Manufacturing') }}</span>
                    </a>
                    <ul class="menu-content">
                        @foreach ($manufacturingVisible as $manufacturingSection)
                            <li class="{{ request()->routeIs($manufacturingSection['route']) ? 'active' : '' }}">
                                <a href="{{ route($manufacturingSection['route']) }}">
                                    <i class="feather icon-{{ $manufacturingSection['icon'] }}"></i>
                                    <span class="menu-item">{{ $manufacturingSection['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endif

            {{--
                إدارة الحجوزات.

                كانت هذه الكتلة محروسةً بصلاحيات **وحدة التأجير**
                (`rental_unit_*`) — وحدةٍ أخرى لا صلة لها بالحجوزات. فمن
                مُنح التأجير رأى الحجوزات، ومن مُنح الحجوزات لم يرَها.
                وسببُ ذلك أنّ الوحدة لم تكن لها صلاحياتٌ أصلاً؛ صارت لها
                الآن في `ReservationsPermissions`.

                والحارسُ الخارجي `$settings['booking_management']` هو مفتاحُ
                «شاشة التطبيقات»، وهو نفسُه الذي يقرؤه وسيط
                `EnsureModuleEnabled` — فالإخفاءُ من القائمة والمنعُ من
                الرابط يتّفقان الآن على مصدرٍ واحد.
            --}}
            @if (canAny([
                    'reservations.view',
                    'reservations.create',
                    'reservations.calendar',
                    'reservations.dashboard',
                    'reservation_services.view',
                    'reservation_settings.view',
                    'reservation_reports.view',
                    /* `reservation_schedule.view` أُسقطت: لا بندَ لها في المجموعة. */
                ])
                /* المواعيد: صلاحيةُ عرضٍ **مع** تفعيلها في إعدادات وحدة العملاء —
                   وإلّا فُتحت المجموعةُ على عنوانٍ بلا بنود. */
                || (hasPermission('client_follow_up_view_notes_attachments_appointments_all|client_follow_up_view_notes_attachments_appointments_assigned|client_follow_up_view_notes_attachments_appointments_own')
                    && \App\Models\ClientModuleSetting::current()->appointments_enabled))
                @if (isset($settings['booking_management']) && $settings['booking_management'] === 'active')
                    <li class="nav-item">
                        <a href="#">
                            <i class="feather icon-bookmark"></i>
                            <span class="menu-title" data-i18n="Reservations">{{ trans('main_trans.Reservations') }}</span>
                        </a>
                        <ul class="menu-content">
                            {{-- شاشة «ابدأ من هنا» حُذفت (2026-09-20) بطلب المستخدم --}}
                            @if (hasPermission('reservations.dashboard'))
                                <li><a href="{{ route('Reservations.dashboard') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">لوحة الحجوزات</span></a></li>
                            @endif

                            @if (hasPermission('reservations.create'))
                                <li><a href="{{ route('Reservations.create') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">{{ trans('main_trans.Add_reservation') }}</span></a></li>
                            @endif

                            @if (hasPermission('reservations.view'))
                                <li><a href="{{ route('Reservations.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">{{ trans('main_trans.Manage_reservations') }}</span></a></li>
                            @endif

                            @if (hasPermission('reservations.calendar'))
                                <li><a href="{{ route('Reservations.calendar') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">تقويم الحجوزات</span></a></li>
                            @endif

                            @if (hasPermission('reservation_services.view'))
                                <li><a href="{{ route('Reservations.services.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">خدمات الحجز</span></a></li>
                            @endif

                            @if (hasPermission('reservation_reports.view'))
                                <li><a href="{{ route('Reservations.reports.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">تقارير الحجوزات</span></a></li>
                            @endif

                            {{-- حارسُ المسار صلاحياتُ **العرض** الثلاث؛ وكان هنا حارسُ «الإضافة»
                                 فوقها، فمن يملك العرضَ وحده لم يرَ البند. --}}
                            @if (\App\Models\ClientModuleSetting::current()->appointments_enabled)
                                @if (canAny(['client_follow_up_view_notes_attachments_appointments_all', 'client_follow_up_view_notes_attachments_appointments_assigned', 'client_follow_up_view_notes_attachments_appointments_own']))
                                <li><a href="{{ route('appointments.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">{{ trans('main_trans.Appointments') }}</span></a></li>
                                @endif
                            @endif

                            @if (hasPermission('reservation_settings.view'))
                                <li><a href="{{ route('Reservations.settings') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item">إعدادات الحجوزات</span></a></li>
                            @endif
                        </ul>
                    </li>
                @endif
            @endif

            {{-- إدارة الأقساط.

                 عطبان كانا هنا:

                 ١. البند الأب كان يشير إلى ملفّ `index.html` الثابت — لا
                    وجود له، فالضغط على «إدارة الأقساط» يذهب إلى
                    `/ar/index.html` ويعطي Not Found. وبقيّة الأقسام تضع
                    مسار شاشتها الأولى في هذا الموضع (انظر «أوامر التوريد»).

                 ٢. حارسٌ من صلاحيات سُلف الرواتب (اليوم `loans.*`) —
                    في الموارد البشرية، لا علاقة لها بتقسيط فواتير العملاء.
                    فمَن يملك صلاحيات الأقساط لا يرى البند، ومسؤولُ رواتبَ
                    لا شأن له بها يراه. صارت صلاحيات `installments_*` مع
                    إبقاء دور `manager` — نفس حراسة
                    `Modules/Sales/routes/web.php`. --}}
            @if (auth()->user()?->hasRole('manager') || hasPermission('sales.installments.view'))
                @if (isset($settings['installments_management']) && $settings['installments_management'] === 'active')
                    <li class="nav-item"><a href="{{ route('installments.index') }}">
                            <i class="feather icon-credit-card"></i> <!-- أيقونة الأقساط -->
                            <span class="menu-title"
                                data-i18n="Dashboard">{{ trans('main_trans.Installment_Management') }}</span>
                        </a>
                        <ul class="menu-content">

                            <li><a href="{{ route('installments.index') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Installment_agreements') }}</span></a>
                            </li>

                            <li><a href="{{ route('installments.agreement_installments') }}"><i
                                        class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Installments') }}</span></a>
                            </li>
                        </ul>
                    </li>
                @endif
            @endif

            <!-- إدارة الوحدات والإيجارات -->
            @if (canAny([
                    'rental_unit_view_booking_orders',
                    'rental_unit_manage_booking_orders',
                    'rental_unit_manage_rental_settings',
                ]))
                @if (isset($settings['rental_management']) && $settings['rental_management'] === 'active')
                    <li class="nav-item"><a href="#">
                            <i class="feather icon-home"></i> <!-- أيقونة الوحدات والإيجارات -->
                            <span class="menu-title"
                                data-i18n="Dashboard">{{ trans('main_trans.Units_and_Rentals_Management') }}</span>
                        </a>
                        <ul class="menu-content">
                            @can('rental_unit_view_booking_orders')
                                <li><a href="{{ route('rental_management.units.index') }}"><i
                                            class="feather icon-circle"></i><span class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Units') }}</span></a>
                                </li>
                            @endcan

                            @can('rental_unit_manage_booking_orders')
                                <li><a href="{{ route('rental_management.orders.index') }}"><i
                                            class="feather icon-circle"></i><span class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Seizure_orders') }}</span></a>
                                </li>
                            @endcan

                            <li><a href="{{ route('rental_management.rental_price_rule.index') }}"><i
                                        class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Pricing_rules') }}</span></a>
                            </li>
                            <li><a href="{{ route('rental_management.seasonal-prices.index') }}"><i
                                        class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Seasonal_Prices') }}</span></a>
                            </li>

                            @can('rental_unit_manage_rental_settings')
                                <li><a href="{{ route('rental_management.settings.index') }}"><i
                                            class="feather icon-circle"></i><span class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Sittings') }}</span></a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
            @endif

            {{--
                إدارة العقارات — قسمٌ مستقلّ عن «الوحدات والإيجارات» أعلاه
                (الشقق الفندقية/الحجوزات). بنوده وحرّاسه في `realestate.layouts.menu`
                المشترك، فلا يختلف بندٌ بينه وبين قائمة القسم. البوّابة: أيّ
                صلاحية `realestate.*` والوحدة مفعَّلة.
            --}}
            @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\RealEstatePermissions::PREFIX))
                @if (isset($settings['rental_management']) && $settings['rental_management'] === 'active')
                    <li class="nav-item {{ request()->is("$getLocal/realestate*") ? 'active open' : '' }}">
                        <a href="#">
                            <i class="feather icon-home"></i>
                            <span class="menu-title">إدارة العقارات</span>
                        </a>
                        <ul class="menu-content">
                            @include('rentalmanagement::realestate.layouts.menu', ['nested' => true])
                        </ul>
                    </li>
                @endif
            @endif

            <!-- أوامر التوريد -->

            @if (canAny(['work_cycle']))
                <!-- دورات العمل -->
                @if (isset($settings['workflow']) && $settings['workflow'] === 'active')
                    <li class="nav-item"><a href="#">
                            <i class="feather icon-refresh-ccw"></i> <!-- أيقونة دورات العمل -->
                            <span class="menu-title"
                                data-i18n="Dashboard">{{ trans('main_trans.Business_cycles') }}</span>
                        </a>
                        <ul class="menu-content">
                            <li><a href="#"><i class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Sittings') }}</span></a></li>
                        </ul>
                    </li>
                @endif
            @endif
            <!-- العملاء -->

            {{-- حضور العملاء --}}
            @can('customer_attendance_display')
                @if (isset($settings['customer_attendance']) && $settings['customer_attendance'] === 'active')
                    <li class=" nav-item"><a href="#">
                            <i class="feather icon-user-check">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Customer_attendance') }}</span>

                        </a>
                        <ul class="menu-content">
                            @can('customer_attendance_display')
                                <li><a href="{{ route('customer_attendance.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Customer_attendance_records') }}</span></a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
            @endcan

            {{-- ادارة المخازن — البوّابة اتحادُ حرّاس بنودها.

                 كانت `hasAnyPermissionWithPrefix('stock.')`، وبادئةُ المخزون تشمل
                 صلاحيات التصنيع (`stock.bom.view`، `stock.production_plans.view`،
                 `stock.manufacturing_orders.view`…) وشاشاتُها في مجموعة «التصنيع»
                 لا هنا — فمن يملك واحدةً منها كانت تُفتح له مجموعةُ المخزون
                 **فارغة**. سبعُ صلاحياتٍ كانت تفعل ذلك. --}}
            @if (canAny([
                'stock.products.view', 'stock.products.movements', 'stock.units.view',
                'stock.permits.view', 'stock.price_lists.view', 'stock.warehouses.view',
                'stock.adjustments.view', 'stock.settings.view', 'stock.settings.products',
                'stock.reports.view',
            ]))
                @if (isset($settings['inventory_management']) && $settings['inventory_management'] === 'active')
                    <li class=" nav-item {{ request()->is("$getLocal/stock/*") ? 'active open' : '' }}"><a
                            href="#">
                            <i class="feather icon-box">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Stock') }}</span>

                        </a>
                        <ul class="menu-content">
                            {{-- لوحةُ المخزون — كانت في فرع الموظّف وحدَه، وهي شعارُ
                                 `stock::layouts.sidebar` ورأسُه. --}}
                            @if (hasPermission('stock.reports.view'))
                                <li><a href="{{ route('stock.dashboard') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/dashboard*") ? 'active' : '' }}"></i><span
                                            class="menu-item">لوحة المخزون</span></a>
                                </li>
                            @endif

                            @can('stock.products.view')
                                <li><a href="{{ route('products.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/products/*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="Analytics">
                                            {{ trans('main_trans.products_management') }}</span></a>
                                </li>
                            @endcan

                            @can('stock.permits.view')
                                <li><a href="{{ route('store_permits_management.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/store_permits_management/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Store_permissions_management') }}</span></a>
                                </li>
                            @endcan


                            {{-- خمسةُ بنودٍ كانت بلا أيّ فحص صلاحية، فيراها كلُّ من فُتحت
                                 له مجموعةُ المخزون — ومنهم مندوبُ المبيعات — ثم يُردّ
                                 بـ403 عند النقر. الأسماءُ أدناه هي التي يفحصها كلُّ
                                 متحكّمٍ في بانيه (`permission.role:`) حرفاً بحرف. --}}
                            @if (hasPermission('stock.products.movements'))
                                <li><a href="{{ route('products.traking') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.product_tracking') }}</span></a>
                                </li>
                            @endif

                            @can('stock.price_lists.view')
                                <li><a href="{{ route('price_list.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/price_list/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.price_lists') }}</span></a>
                                </li>
                            @endcan

                            @if (hasPermission('stock.warehouses.view'))
                                <li><a href="{{ route('storehouse.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/storehouse/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.warehouses') }}</span></a>
                                </li>
                            @endif

                            @if (hasPermission('stock.adjustments.view'))
                                <li><a href="{{ route('inventory_management.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/inventory_management/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.inventory_Management') }}</span></a>
                                </li>
                            @endif

                            @if (hasPermission('stock.settings.view'))
                                <li><a href="{{ route('inventory_settings.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/inventory_settings/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.inventory_settings') }}</span></a>
                                </li>
                            @endif

                            @if (hasPermission('stock.settings.products'))
                                <li><a href="{{ route('product_settings.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/product_settings/*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="eCommerce">
                                            {{ trans('main_trans.products_Settings') }}</span></a>
                                </li>
                            @endif


                            {{-- شاشتان كانتا في `stock::layouts.sidebar` وحدهما، بحارسَيهما
                                 هناك نفسِهما: «إدارة الوحدات» (وحدات القياس التي تُبنى
                                 عليها أعمدة الوحدة في المستندات) و«الكشف المخزني»
                                 (كشفُ عهدة الموظف من المخزن). --}}
                            @if (hasPermission('stock.units.view'))
                                <li><a href="{{ route('units.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/units/*") ? 'active' : '' }}"></i><span
                                            class="menu-item">إدارة الوحدات</span></a>
                                </li>
                            @endif

                            @if (hasPermission('stock.reports.view') && hasPermission('stock.reports.employee_statement'))
                                <li><a href="{{ route('storehouse.employee_statement') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/employee-statement*") ? 'active' : '' }}"></i><span
                                            class="menu-item">الكشف المخزني</span></a>
                                </li>
                            @endif

                        </ul>

                    </li>
                @endif
            @endif


            {{-- تتبع الوقت --}}
            @if (canAny(['time_entries.index', 'time_entries.create', 'time_entries.edit', 'time_projects.create', 'time_projects.edit', 'time_projects.destroy', 'time_activities.create', 'time_activities.edit', 'time_activities.destroy']))
                @if (isset($settings['time_tracking']) && $settings['time_tracking'] === 'active')
                    <li class=" nav-item"><a href="#">
                            <i class="feather icon-watch">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Time_Tracking') }}</span>
                        </a>
                        <ul class="menu-content">
                            @if (hasPermission('time_entries.index'))
                            <li><a href="{{ route('TrackTime.index') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Time_Tracking') }}</span></a>
                            </li>
                            @endif
                            @if (hasPermission('time_entries.create'))
                            <li><a href="{{ route('TrackTime.create_invoice_time') }}"><i
                                        class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Create_Invoice') }}</span></a>
                            </li>
                            @endif

                            @if (canAny(['time_projects.create', 'time_projects.edit', 'time_projects.destroy', 'time_activities.create', 'time_activities.edit', 'time_activities.destroy', 'time_entries.edit']))
                            <li><a href="{{ route('SittingTrackTime.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Time_Tracking_Settings') }}</span></a>
                            </li>
                            @endif
                        </ul>

                    </li>
                @endif
            @endif

            {{-- المالية --}}
            @if (canAny(['account.expenses.view', 'account.incomes.view', 'account.treasury.view', 'account.settings.view']))
                @if (isset($settings['finance']) && $settings['finance'] === 'active')
                    <li class=" nav-item {{ request()->is("$getLocal/finance/*") ? 'active open' : '' }}">
                        <a href="#">
                            <i class="feather icon-dollar-sign">
                            </i><span class="menu-title"
                                data-i18n="Dashboard">{{ trans('main_trans.Financial') }}</span>
                        </a>

                        <ul class="menu-content">
                            @can('account.expenses.view')
                                <li><a href="{{ route('expenses.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/finance/expenses/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Expenses') }}</span></a>
                                </li>
                            @endcan

                            @can('account.incomes.view')
                                <li><a href="{{ route('incomes.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/finance/incomes/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Receipts') }}</span></a>
                                </li>
                            @endcan

                            @can('account.treasury.view')
                                <li><a href="{{ route('treasury.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item" data-i18n="eCommerce">
                                            {{ trans('main_trans.Cash_and_Bank_Accounts') }}</span></a>
                                </li>
                            @endcan

                            @can('account.settings.view')
                                <li><a href="{{ route('finance_settings.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/finance/finance_settings/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Financial_Settings') }}</span></a>
                                </li>
                            @endcan
                        </ul>

                    </li>
                @endif
            @endif
            {{-- الحسابات العامة --}}
            @if (canAny([
                        'account.journal.view',
                        'account.journal.create',
                        'account.chart.view',
                        'account.cost_centers.view',
                        'account.chart.general_ledger',
                        'account.assets.view',
                    ]))
                @if (isset($settings['general_accounts_journal_entries']) && $settings['general_accounts_journal_entries'] === 'active')
                    <li class=" nav-item {{ request()->is("$getLocal/Accounts/*") ? 'active open' : '' }}"><a
                            href="#">
                            <i class="feather icon-pie-chart">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.General_Accounts') }}</span>
                        </a>

                        <ul class="menu-content">
                            @can('account.journal.view')
                                <li><a href="{{ route('journal.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Journal_Entries') }}</span></a>
                                </li>
                            @endcan

                            @can('account.journal.create')
                                <li><a href="{{ route('journal.create') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Add_Entry') }}</span></a>
                                </li>
                            @endcan

                            @can('account.chart.view')
                                <li><a href="{{ route('accounts_chart.view_root') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/Accounts/accounts_chart/*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="eCommerce">
                                            {{ trans('main_trans.Chart_of_Accounts') }}</span></a>
                                </li>
                            @endcan

                            @can('account.cost_centers.view')
                                <li><a href="{{ route('cost_centers.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/Accounts/cost_centers/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Cost_Centers') }}</span></a>
                                </li>
                            @endcan

                            @can('account.chart.general_ledger')
                                <li><a href="{{ route('journal.generalLedger') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/finance/expenses/*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="Analytics">حساب الاستاذ</span></a>
                                </li>
                            @endcan

                            @can('account.assets.view')
                                <li><a href="{{ route('Assets.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item" data-i18n="eCommerce">
                                            {{ trans('main_trans.Assets') }}</span></a>
                                </li>
                            @endcan

                            @if (hasPermission('account.settings.view'))
                            <li><a href="{{ route('accounts_settings.index') }}"><i
                                        class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.General_Accounts_Settings') }}</span></a>
                            </li>
                            @endif
                        </ul>

                    </li>
                @endif
            @endif
            {{-- الشيكات --}}
            @if (canAny(['account.cheque_books.view', 'account.payable_cheques.view', 'account.received_cheques.view']))
                @if (isset($settings['cheque_cycle']) && $settings['cheque_cycle'] === 'active')
                    <li class=" nav-item {{ request()->is("$getLocal/cheques*") ? 'active open' : '' }}"><a
                            href="#">
                            <i class="feather icon-dollar-sign">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Cheques_Cycle') }}</span>
                        </a>
                        <ul class="menu-content">
                            @can('account.cheque_books.view')
                                <li><a href="{{ route('check_books.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/cheques/check_books*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Check_Books') }}</span></a>
                                </li>
                            @endcan

                            @can('account.payable_cheques.view')
                                <li><a href="{{ route('payable_cheques.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/cheques/payable_cheques*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Paid_Cheques') }}</span></a>
                                </li>
                            @endcan

                            @can('account.received_cheques.view')
                                <li><a href="{{ route('received_cheques.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/cheques/received_cheques*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Received_Cheques') }}</span></a>
                                </li>
                            @endcan
                        </ul>

                    </li>
                @endif
            @endif
            {{-- الطلبات --}}
            @if (canAny(['orders_management', 'orders_setting_management']))
                @if (isset($settings['orders']) && $settings['orders'] === 'active')
                    <li class=" nav-item"><a href="#">
                            <i class="feather icon-briefcase">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Orders') }}</span>
                        </a>
                        <ul class="menu-content">
                            @can('orders_management')
                                <li><a href="{{ route('orders.management.mangame') }}"><i
                                            class="feather icon-circle"></i><span class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Order_Management') }}</span></a>
                                </li>
                            @endcan

                            @can('orders_setting_management')
                                <li><a href="{{ route('orders.Settings.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Sittings') }}</span></a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
            @endif
            {{-- الموظفين --}}
            @if (canAny(['employees.index', 'hr_dashboard.index', 'performance.index', 'employee_settings.manage']))
                @if (isset($settings['employees']) && $settings['employees'] === 'active')
                    <li class=" nav-item {{ request()->is("$getLocal/hr/*") ? 'active open' : '' }}">
                        <a href="#">
                            <i class="fa fa-users"></i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Employees') }}</span>
                        </a>
                        <ul class="menu-content">
                            @if (hasPermission('employees.index'))
                                <li><a href="{{ route('employee.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/employee/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Employee_Management') }}</span></a>
                                </li>
                            @endif

                            {{-- إعداداتُ ملفّ الموظف وقواعدُ الهيكل التنظيمي — شاشةٌ واحدة
                                 بحارسها `employee_settings.manage`: من يضبط القواعدَ ليس
                                 بالضرورة من يسجّل الموظفين. --}}
                            @if (hasPermission('employee_settings.manage'))
                                <li><a href="{{ route('hr.employee_settings.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/employee/settings") ? 'active' : '' }}"></i><span
                                            class="menu-item">إعدادات الموظفين والهيكل</span></a>
                                </li>
                            @endif

                            {{-- الورديات في مجموعة «الحضور» أدناه، بحارسها `shifts.index` --}}

                            {{-- أداءُ الفريق — لوحتان كانتا في **فرع الموظّف وحدَه**
                                 وفي `hr::layouts.sidebar`، ولا أثر لهما في هذا الفرع. --}}
                            @if (hasPermission('hr_dashboard.index'))
                                <li><a href="{{ route('hr.dashboard.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/dashboard*") ? 'active' : '' }}"></i><span
                                            class="menu-item">لوحة أداء الموظفين</span></a>
                                </li>
                            @endif
                            @if (hasPermission('performance.index'))
                                <li><a href="{{ route('hr.employee_performance.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/employee-performance*") ? 'active' : '' }}"></i><span
                                            class="menu-item">أداء الموظفين</span></a>
                                </li>
                            @endif

                        </ul>

                    </li>
                @endif
            @endif
            {{-- الهيكل التنظيمي — البوّابة اتحادُ حرّاس بنودها الأربعة، وكلُّ
                 بندٍ بصلاحية شاشته وحدها: الأقسام، المسمّيات، المستويات، الأنواع. --}}
            @if (canAny(['departments.index', 'job_titles.index', 'job_levels.index', 'job_types.index', 'employee_settings.manage']))
                @if (isset($settings['organizational_structure']) && $settings['organizational_structure'] === 'active')
                    <li class=" nav-item {{ request()->is("$getLocal/OrganizationalStructure/*") ? 'active open' : '' }}">
                        <a href="#">
                            <i class="feather icon-layers">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Organizational_Structure') }}</span>
                        </a>
                        <ul class="menu-content">
                            @if (hasPermission('job_titles.index'))
                            <li><a href="{{ route('JobTitles.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/JobTitles/*") ? 'active open' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Job_Titles_Management') }}</span></a>
                            </li>
                            @endif

                            @if (hasPermission('departments.index'))
                            <li><a href="{{ route('department.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/department/*") ? 'active open' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Departments_Management') }}</span></a>
                            </li>
                            @endif

                            @if (hasPermission('job_levels.index'))
                            <li><a href="{{ route('ManagingFunctionalLevels.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/ManagingFunctionalLevels/*") ? 'active open' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Job_Levels_Management') }}</span></a>
                            </li>
                            @endif
                            @if (hasPermission('job_types.index'))
                            <li><a href="{{ route('ManagingJobTypes.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/ManagingJobTypes/*") ? 'active open' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Job_Types_Management') }}</span></a>
                            </li>
                            @endif
                            {{-- القواعدُ (إلزاميّةُ الحقول وفرادةُ الأسماء) في شاشة إعدادات
                                 الموظفين — وهذا البندُ يفتحها على تبويب «الهيكل التنظيمي». --}}
                            @if (hasPermission('employee_settings.manage'))
                            <li><a href="{{ route('hr.employee_settings.index', ['tab' => 'org']) }}"><i
                                        class="feather icon-circle"></i><span
                                        class="menu-item">إعدادات الهيكل</span></a>
                            </li>
                            @endif
                        </ul>
                    </li>
                @endif
            @endif
            {{--
                الحضور — سبعةُ بنودٍ بدل خمسة عشر، كما في `hr::layouts.sidebar`.
                كلُّ بندٍ يفتح أوّلَ شاشةٍ من مجموعته يملك المستخدمُ صلاحيتَها،
                والتنقّلُ بين شاشاتها من تبويباتٍ أعلاها.
            --}}
            @php
                $attGroups = [
                    'daily' => [
                        ['attendance_days.index', 'hr.attendance-log.index'],
                        ['attendance_team.index', 'hr.team-board.index'],
                        ['attendance_records.index', 'hr.attendance-punches.index'],
                        ['attendance_corrections.index', 'hr.attendance-corrections.index'],
                        ['attendance_corrections.approve', 'hr.attendance-correction-approvals.index'],
                        ['attendance_books.index', 'attendance_sheets.index'],
                    ],
                    'leaves' => [
                        ['leave_requests.index', 'attendance.leave_requests.index'],
                        ['leave_permissions.index', 'leave_permissions.index'],
                        ['leave_balances.index', 'employee_leave_balances.index'],
                    ],
                    'shifts' => [
                        ['shifts.index', 'shift_management.index'],
                    ],
                    'devices' => [
                        ['attendance_places.index', 'attendance.settings.geofences.index'],
                        ['attendance_devices.index', 'hr.device-monitor.index'],
                        ['attendance_cards.index', 'barcode.cards'],
                        ['attendance_devices.index', 'machines.index'],
                    ],
                ];
                $attFirst = function (string $group) use ($attGroups) {
                    foreach ($attGroups[$group] as [$perm, $route]) {
                        if (hasPermission($perm)) {
                            return $route;
                        }
                    }

                    return null;
                };
                $attDaily = $attFirst('daily');
                $attLeaves = $attFirst('leaves');
                $attShifts = $attFirst('shifts');
                $attDevices = $attFirst('devices');
                $attSelf = hasPermission('attendance_registration.mobile');
                $attSettings = canAny(['attendance_settings.manage', 'holidays.index', 'attendance_rules.index', 'leave_types.index', 'leave_policies.index']);
                // شاشةُ جهاز الفرع لحساب تابلت الاستقبال — لم يكن لها بندٌ فلا تُفتح إلا بكتابة عنوانها
                $attKiosk = hasPermission('attendance_registration.kiosk');
                $attAny = $attDaily || $attLeaves || $attShifts || $attDevices || $attSelf || $attKiosk || $attSettings;

                $attIs = fn (array $paths) => request()->is(array_merge(
                    array_map(fn ($p) => "$getLocal/hr/$p", $paths),
                    array_map(fn ($p) => "$getLocal/hr/$p/*", $paths),
                ));
                $attDailyPaths = ['attendance-log', 'team-board', 'attendance-punches', 'attendance-corrections', 'attendance-correction-approvals', 'attendance-sheets', 'attendanceDays'];
                $attLeavePaths = ['leave_requests', 'leave-permissions', 'employee-leave-balances'];
                $attShiftPaths = ['shift_management', 'custom-shifts'];
                $attDevicePaths = ['settings/geofences','settings/kiosks', 'device-monitor', 'barcode', 'machines'];
            @endphp
            @if ($attAny && isset($settings['employee_attendance']) && $settings['employee_attendance'] === 'active')
                <li class="nav-item {{ $attIs(array_merge(['attendance', 'field-attendance', 'settings'], $attDailyPaths, $attLeavePaths, $attShiftPaths, $attDevicePaths)) ? 'active' : '' }}"><a href="#">
                        <i class="feather icon-user-check"></i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Attendance') }}</span>
                    </a>
                    <ul class="menu-content">
                        <li><a href="{{ route('hr.attendance.hub') }}"><i
                                    class="feather icon-circle {{ $attIs(['attendance']) ? 'active' : '' }}"></i><span
                                    class="menu-item">لوحة الحضور</span></a>
                        </li>

                        @if ($attDaily)
                            <li><a href="{{ route($attDaily) }}"><i
                                        class="feather icon-circle {{ $attIs($attDailyPaths) ? 'active' : '' }}"></i><span
                                        class="menu-item">الحضور اليومي</span></a>
                            </li>
                        @endif

                        @if ($attLeaves)
                            <li><a href="{{ route($attLeaves) }}"><i
                                        class="feather icon-circle {{ $attIs($attLeavePaths) ? 'active' : '' }}"></i><span
                                        class="menu-item">الإجازات</span></a>
                            </li>
                        @endif

                        @if ($attShifts)
                            <li><a href="{{ route($attShifts) }}"><i
                                        class="feather icon-circle {{ $attIs($attShiftPaths) ? 'active' : '' }}"></i><span
                                        class="menu-item">الورديات</span></a>
                            </li>
                        @endif

                        @if ($attDevices)
                            <li><a href="{{ route($attDevices) }}"><i
                                        class="feather icon-circle {{ $attIs($attDevicePaths) ? 'active' : '' }}"></i><span
                                        class="menu-item">الأجهزة وأماكن التسجيل</span></a>
                            </li>
                        @endif

                        @if ($attSelf)
                            <li><a href="{{ route('hr.field-attendance.index') }}"><i
                                        class="feather icon-circle {{ $attIs(['field-attendance']) ? 'active' : '' }}"></i><span
                                        class="menu-item">سجّل حضوري</span></a>
                            </li>
                        @endif

                        @if ($attKiosk)
                            <li><a href="{{ route('barcode.scan') }}"><i
                                        class="feather icon-circle {{ $attIs(['barcode/scan']) ? 'active' : '' }}"></i><span
                                        class="menu-item">شاشة جهاز الفرع</span></a>
                            </li>
                        @endif

                        @if ($attSettings)
                            <li><a href="{{ route('attendance.settings.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/hr/settings/*") && ! $attIs(['settings/geofences', 'settings/kiosks']) ? 'active' : '' }}"></i><span
                                        class="menu-item">الإعدادات</span></a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif
            {{-- المرتبات — البوّابة اتحادُ حرّاس بنودها.
                 صلاحياتُ الإنشاء والتعديل والحذف والاعتماد والصرف حُذفت منها:
                 لا بندَ في المجموعة يفتحه صاحبُها وحدَها، فكانت تُفتح فارغة. --}}
            @if (canAny([
                        'contracts.index',
                        'payroll.index',
                        'payslips.index',
                        'disbursements.index',
                        'payroll_settings.manage',
                        'contracts.notifications', 'salary_items.index',
                        'overtime.settings', 'insurance.settings', 'bank_files.index',
                        'loans.index',
                        'loans.create',
                        'eos.index',
                        'insurance.index', 'insurance.show',
                        'deductions.index',
                        'commissions.index',
                    ]))
                @if (isset($settings['salaries']) && $settings['salaries'] === 'active')
                    <li class=" nav-item"><a href="#">
                            <i class="feather icon-dollar-sign">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Salaries') }}</span>
                        </a>
                        <ul class="menu-content">
                            @if (hasPermission('contracts.index'))
                                <li><a href="{{ route('Contracts.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Contracts') }}</span></a>
                                </li>
                            @endif

                            @if (hasPermission('payroll.index'))
                                <li><a href="{{ route('PayrollProcess.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Payroll') }}</span></a>
                                </li>
                            @endif

                            @if (hasPermission('payslips.index'))
                                <li><a href="{{ route('salarySlip.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item" data-i18n="eCommerce">
                                            {{ trans('main_trans.Salary_Slips') }}</span></a>
                                </li>
                            @endif

                            {{-- بندُ «السلف» القديم (ancestor.index) أُزيل: مسارُه تحويلٌ إلى
                                 «إدارة واعتماد السلف» أدناه، فكان بنداً مكرَّراً. --}}

                            {{-- السلفُ والخصميات — كانت في **فرع الموظّف وحدَه** من هذا
                                 الملف، فمن عليه القائمةُ الموحّدة (أو من لا `employee_id`
                                 له) لا يجد طلبَ سلفةٍ ولا اعتمادَها ولا إدارةَ الخصميات.
                                 الحرّاسُ هي حرّاسُ فرع الموظّف نفسُها. --}}
                            @if (hasPermission('loans.create'))
                                <li><a href="{{ route('hr.advance-requests.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/advance-requests*") ? 'active' : '' }}"></i><span
                                            class="menu-item">طلب سلفة (السلف الخاصة بي)</span></a>
                                </li>
                            @endif

                            @if (hasPermission('loans.index'))
                                <li><a href="{{ route('hr.advance-approvals.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/advance-approvals*") ? 'active' : '' }}"></i><span
                                            class="menu-item">إدارة واعتماد السلف</span></a>
                                </li>
                            @endif

                            @if (hasPermission('deductions.index'))
                                <li><a href="{{ route('hr.deductions.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/deductions*") ? 'active' : '' }}"></i><span
                                            class="menu-item">إدارة الخصميات</span></a>
                                </li>
                            @endif

                            {{--
                                بنودُ الرواتب صارت بلاطةً في شاشة
                                «الإعدادات» أدناه — كما في قائمة وحدة الموارد
                                البشرية. وكانتا هنا بلا فحص صلاحيةٍ إطلاقاً
                                بينما تفحص الشاشتان `salary_items.index`.
                            --}}

                            @if (canAny(['payroll_settings.manage', 'contracts.notifications', 'salary_items.index', 'overtime.settings', 'insurance.settings', 'insurance.index', 'bank_files.index']))
                                <li><a href="{{ route('SalarySittings.index') }}"><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Sittings') }}</span></a>
                                </li>
                            @endif

                            {{-- ══════ شاشاتُ الرواتب المنقولة من شريط الموارد البشرية ══════

                                 خمسُ شاشاتٍ كانت في `hr::layouts.sidebar` وحدها:
                                 سجلُّ الصرف الشهري، ومكافأةُ نهاية الخدمة، وسدادُ
                                 التأمينات، ومسيرُ الصرفات، والعمولاتُ المصروفة.
                                 وحارسُ كلٍّ منها هو حارسُها هناك نفسُه. --}}

                            {{-- سجلّ صرف الرواتب الشهري — غير «مسيرات الرواتب» أعلاه --}}
                            @if (hasPermission('disbursements.index'))
                                <li><a href="{{ route('payroll-runs.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/payroll-runs*") ? 'active' : '' }}"></i><span
                                            class="menu-item">سجل صرف الرواتب</span></a>
                                </li>
                            @endif

                            @if (hasPermission('eos.index'))
                                <li><a href="{{ route('hr.eos.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/eos*") ? 'active' : '' }}"></i><span
                                            class="menu-item">مكافأة نهاية الخدمة</span></a>
                                </li>
                            @endif

                            {{-- سدادُ ما حُجز: ذمّتا التأمينات تُقفلان نقداً --}}
                            @if (canAny(['insurance.index', 'insurance.show']))
                                <li><a href="{{ route('hr.gosi.remittances') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/social-insurance/remittances*") ? 'active' : '' }}"></i><span
                                            class="menu-item">سداد التأمينات</span></a>
                                </li>
                            @endif

                            {{-- «مسير الصرفات» أُلغي: اعتمادُ مسير الرواتب هو الاعتمادُ الوحيد للصرف،
                                 وروابطُه القديمة تُحوَّل إلى سجلات الصرف. --}}

                            @if (hasPermission('commissions.index'))
                                <li><a href="{{ route('hr.commissions-report.index') }}"><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/commissions-report*") ? 'active' : '' }}"></i><span
                                            class="menu-item">سجل العمولات</span></a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif
            @endif

            {{--
                التقارير.

                كان القسم كاملاً — أربعة عشر رابطاً — بلا أي فحص صلاحية
                إطلاقاً، لا على الأب ولا على أيّ بندٍ فيه: يظهر لكل من وصل
                إلى هذا الفرع من الشريط بصرف النظر عمّا يملك. الأسماء أدناه
                مطابقةٌ لِما يفحصه كل مسارٍ فعلياً حيث وُجدت حراسة
                (`permission.role:...` على GeneralAccountReports/checksReports/
                StorHouseReport)، وحيث لا حراسة على المسار (أغلب الروابط)
                اخترتُ أقرب صلاحيةٍ من الكتالوج بمعناها لنفس المتحكّم —
                هذا لا يحمي المسار نفسه، بل يوقف تسريب رابطٍ لا صلاحية له
                إطلاقاً.
            --}}
            @if (canAny([
                    'sales.reports.view', 'purchases.reports.view', 'account.reports.view',
                    'account.reports.cheques', 'sms.view', 'stock.reports.manufacturing',
                    /* تقاريرُ الموارد البشرية بمجموعة `hr_reports.*` — كلُّ بندٍ بحارس
                       مساره. و`client_reports_view`
                       صارت `clients.reports.view` تبعاً لحارس المتحكّم. */
                    'hr_reports.employees', 'hr_reports.attendance', 'hr_reports.payroll', 'stock.reports.view',
                    'clients.reports.view', 'settings_edit_general_settings',
                ]))
            <li class=" nav-item"><a href="#">
                    <i class="feather icon-file-text">
                    </i><span class="menu-title" data-i18n="Dashboard">
                        {{ trans('main_trans.Reports') }}</span>

                </a>
                <ul class="menu-content">
                    @if (hasPermission('sales.reports.view'))
                    <li><a href="{{ route('salesReports.index') }}"><i class="feather icon-circle"></i><span
                                class="menu-item" data-i18n="Analytics">
                                {{ trans('main_trans.Sales_Report') }}</span></a>
                    </li>
                    @endif

                    @if (hasPermission('purchases.reports.view'))
                    <li><a href="{{ route('ReportsPurchases.index') }}"><i class="feather icon-circle"></i><span
                                class="menu-item"
                                data-i18n="eCommerce">{{ trans('main_trans.Purchases_Report') }}</span></a>
                    </li>
                    @endif
                    @if (hasPermission('account.reports.view'))
                    <li><a href="{{ route('GeneralAccountReports.index') }}"><i class="feather icon-circle"></i><span
                                class="menu-item"
                                data-i18n="eCommerce">{{ trans('main_trans.General_Accounts_Report') }}</span></a>
                    </li>
                    @endif

                    @if (hasPermission('account.reports.cheques'))
                    <li><a href="{{ route('checksReports.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="eCommerce">{{ trans('main_trans.Cheques_Report') }}</span></a></li>
                    @endif

                    @if (hasPermission('sms.view'))
                    <li><a href="#"><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="eCommerce">{{ trans('main_trans.SMS_Report') }}</span></a>
                    </li>
                    @endif
                    {{-- تقرير التصنيع — كان يشير إلى `ReportsProduction.index`، وهو
                         مسارٌ في وحدة التقارير يستدعي `RentalsController@production`،
                         دالّةً لا وجود لها؛ فالرابط كان يُنتج 500 لا تقريراً. صار
                         يقود إلى تقارير وحدة التصنيع نفسِها، كما فعل شريطُ الوحدة. --}}
                    @if (hasPermission('stock.reports.manufacturing'))
                    <li><a href="{{ route('manufacturing.reports.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="eCommerce">تقرير التصنيع </span></a>
                    </li>
                    @endif

                    {{--
                        الحارسُ `hr_reports.employees` — حارسُ المسار نفسُه:
                        المسارُ صار يبلغ `EmployeeReportController` وحارسُه
                        ذاك. وبالقديم كان من يملك عرضَ ملفّات الموظفين يرى
                        البندَ ويُردّ بـ403 عند النقر.
                    --}}
                    {{-- «تقرير الموظفين» صار مجموعةً داخل لوحة تقارير الموارد البشرية أدناه — بندٌ واحد لا اثنان --}}

                    {{-- لوحةُ تقارير الموارد البشرية — كانت في `hr::layouts.sidebar`
                         وحدها، بحارسيها المزدوجين هناك نفسِهما. --}}
                    @if (canAny(['hr_reports.employees', 'hr_reports.attendance', 'hr_reports.payroll']))
                    <li><a href="{{ route('hr.reports.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="eCommerce">تقارير الموارد البشرية</span></a></li>
                    @endif



                    @if (hasPermission('stock.reports.view'))
                    <li><a href="{{(route('unit-track.index'))}}"><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="eCommerce">تتبع الوحدات </span></a></li>
                    @endif

                    {{-- مركز تقارير العقارات — بحارس مساره نفسه --}}
                    @if (Route::has('realestate.reports.index') && canAny(['realestate.reports.view', 'realestate.reports.financial']))
                    <li><a href="{{ route('realestate.reports.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="eCommerce">تقارير العقارات</span></a></li>
                    @endif

                    @if (hasPermission('clients.reports.view'))
                    <li><a href="{{ route('ClientReport.index') }}"><i class="feather icon-circle"></i><span
                                class="menu-item"
                                data-i18n="eCommerce">{{ trans('main_trans.Customers_Report') }}</span></a></li>
                    @endif

                    @if (hasPermission('stock.reports.view') && hasPermission('stock.reports.inventory_sheet'))
                    <li><a href="{{ route('StorHouseReport.index') }}"><i class="feather icon-circle"></i><span
                                class="menu-item"
                                data-i18n="eCommerce">{{ trans('main_trans.Stock_Report') }}</span></a></li>

                    <li><a href="#"><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="eCommerce">{{ trans('main_trans.Units_Tracking_Report') }}</span></a></li>
                    @endif

                    @if (hasPermission('settings_edit_general_settings'))
                    <li><a href="{{ route('ActivityLog.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="eCommerce">{{ trans('main_trans.Account_Activity_Log') }}</span></a></li>

                    {{-- سجل جلسات الدخول: كان متاحاً من سايدبار قسم الإعدادات وحده --}}
                    <li><a href="{{ route('UserSessions.index') }}"><i class="feather icon-circle"></i><span
                                class="menu-item" data-i18n="eCommerce">جلسات الدخول</span></a></li>
                    @endif

                </ul>

            </li>
            @endif


            {{-- الفروع — تُقرأ من `BranchesNavigation`، المصدر نفسه الذي يقرأ منه
                 شريطُ الوحدة ولوحتُها.

                 كانت ثلاثةَ بنود مكتوبةً يدوياً (الشجرة، الإضافة، الإعدادات)
                 وخمسةٌ غائبة: لوحةُ الفروع، ودمجُ المجموعات بالجملة، ونقلُ
                 العملاء بين المجموعات، وسجلُّ عمليات الدمج، وأداةُ ترحيل
                 المجموعات القديمة.

                 **وحارسُها كان `canAny(['branches'])`** — و«branches» ليس اسمَ
                 صلاحيةٍ في الكتالوج (الأسماء `branches.view` و`branches.create`
                 و`branches.settings.view`…)، و`canAny` على اسمٍ لا وجود له تُرجع
                 false دائماً، فالمجموعة كانت محجوبةً عن الجميع بلا استثناء.
                 الآن `visible()` تُرجع ما يملكه المستخدم فعلاً، وإن لم يملك شيئاً
                 لم تُطبع المجموعة.

                 وقفلُ الباقة محفوظٌ كما في شريط الوحدة: البند خارج الباقة يبقى
                 ظاهراً بقفلٍ ويقود إلى شاشة الاشتراك، ولا يُخفى.

                 @see \Modules\Branches\Services\BranchesNavigation --}}
            @if (isset($settings['branches']) && $settings['branches'] === 'active')
                @php
                    $branchesNav = [];
                    foreach (\Modules\Branches\Services\BranchesNavigation::visible() as $branchesGroup) {
                        $branchesNav = array_merge($branchesNav, $branchesGroup['items']);
                    }
                    $branchesDashboard = \Illuminate\Support\Facades\Route::has('branches.dashboard');
                @endphp

                @if ($branchesNav)
                    <li class="nav-item {{ request()->is("$getLocal/branches*") || request()->is("$getLocal/groups*") ? 'active open' : '' }}">
                        <a href="#">
                            <i class="feather icon-briefcase"></i>
                            <span class="menu-title" data-i18n="Branches">{{ trans('main_trans.Branches') }}</span>
                        </a>
                        <ul class="menu-content">

                            @if ($branchesDashboard && hasPermission('branches.dashboard'))
                                <li class="{{ request()->routeIs('branches.dashboard') ? 'active' : '' }}">
                                    <a href="{{ route('branches.dashboard') }}">
                                        <i class="feather icon-grid"></i>
                                        <span class="menu-item">{{ __('branches.nav.dashboard') }}</span>
                                    </a>
                                </li>
                            @endif

                            @foreach ($branchesNav as $branchesItem)
                                <li class="{{ request()->routeIs($branchesItem['route']) ? 'active' : '' }} {{ ($branchesItem['locked'] ?? false) ? 'stk-plan-locked' : '' }}">
                                    <a href="{{ $branchesItem['url'] }}" title="{{ $branchesItem['hint'] ?? '' }}">
                                        <i class="feather icon-{{ ($branchesItem['locked'] ?? false) ? 'lock' : 'circle' }}"></i>
                                        <span class="menu-item">{{ $branchesItem['label'] }}</span>
                                        @if ($branchesItem['locked'] ?? false)
                                            {!! \App\Support\PlanLock::menuBadge() !!}
                                        @endif
                                    </a>
                                </li>
                            @endforeach

                        </ul>
                    </li>
                @endif
            @endif


            @if (canAny(['templates']))
                {{-- القوالب --}}
                <li class=" nav-item"><a href="#">
                        <i class="feather icon-dollar-sign">
                        </i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Templates') }}</span>

                    </a>
                    <ul class="menu-content">
                        <li><a href="dashboard-analytics.html"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="Analytics">{{ trans('main_trans.Print_Templates') }}</span></a>
                        </li>

                        <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Ready_Invoice_Templates') }}</span></a>
                        </li>
                        <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Emails') }}</span></a>
                        </li>
                        @if (isset($settings['sms']) && $settings['sms'] === 'active')
                            <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.SMS_Models') }}</span></a>
                            </li>
                        @endif
                        <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Terms_and_Conditions') }}</span></a>
                        </li>
                        <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.File_Management_and_Documents') }}</span></a>
                        </li>
                        <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Auto_Send_Rules') }}</span></a>
                        </li>


                    </ul>

                </li>
            @endif
            {{--
                إدارة المهام في القائمة الكاملة — قائمةٌ فرعية حقيقية.

                كان البند محكوماً بـ`@can('settings_edit_general_settings')`:
                صلاحيةُ **الإعدادات العامّة** تحرس قائمة المهام، فمن يعمل في
                المهام ولا يملك إعدادات النظام لا يرى الوحدة، ومن يملك
                الإعدادات ولا يعمل في المهام يراها كاملةً ثم تردّه مساراتُها.

                وكان فيه رابطان ميّتان — «التقويم» و«الإعدادات» بـ`href=""`
                يعيدان الصفحة إلى نفسها — ولا لوحةَ معلوماتٍ ولا لوحةَ مهامّ
                ولا سبرنتاتٍ ولا تقارير، وهي شاشاتٌ قائمة.

                والشرط الآن من الأسماء التي تحرس المسارات نفسها
                ([\App\Constants\permissions\TaskManagerPermissions])، وكلُّ
                بندٍ بصلاحية مساره هو.
            --}}
            @if (canAny([
                /* `tasks.board.backlog` حُذفت: لا بندَ في المجموعة يفتحه صاحبُها */
                'tasks.board.view', 'tasks.board.dashboard',
                'tasks.items.view', 'tasks.projects.view', 'tasks.workspaces.view',
                'tasks.sprints.view', 'tasks.reports.view', 'tasks.settings.global.view',
            ]))
                <li class="nav-item {{ request()->is("$getLocal/task*") || request()->is("$getLocal/projects*") || request()->is("$getLocal/workspaces*") || request()->is("$getLocal/sprints*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-check-circle"></i>
                        <span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.tasks') }}
                        </span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('tasks.board.dashboard'))
                        <li>
                            <a href="{{ route('tasks.dashboard') }}">
                                <i class="feather icon-pie-chart {{ request()->is("$getLocal/task-dashboard*") ? 'active' : '' }}"></i>
                                <span class="menu-item">لوحة معلومات المهام</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.board.view'))
                        <li>
                            <a href="{{ route('tasks.manager') }}">
                                <i class="feather icon-list {{ request()->is("$getLocal/task-manager*") ? 'active' : '' }}"></i>
                                <span class="menu-item">{{ trans('main_trans.management_Tasks') }}</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.items.view'))
                        <li>
                            <a href="{{ route('tasks.index') }}">
                                <i class="feather icon-file-text {{ request()->is("$getLocal/tasks") ? 'active' : '' }}"></i>
                                <span class="menu-item">قائمة المهام</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.projects.view'))
                        <li>
                            <a href="{{ route('projects.index') }}">
                                <i class="feather icon-folder {{ request()->is("$getLocal/projects*") ? 'active' : '' }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Management_Projects') }}</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.workspaces.view'))
                        <li>
                            <a href="{{ route('workspaces.index') }}">
                                <i class="feather icon-layers {{ request()->is("$getLocal/workspaces*") ? 'active' : '' }}"></i>
                                <span class="menu-item">ادارة مساحات العمل</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.sprints.view'))
                        <li>
                            <a href="{{ route('sprints.index') }}">
                                <i class="feather icon-repeat {{ request()->is("$getLocal/sprints*") ? 'active' : '' }}"></i>
                                <span class="menu-item">السبرنتات</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.reports.view'))
                        <li>
                            <a href="{{ route('tasks.reports') }}">
                                <i class="feather icon-bar-chart-2 {{ request()->is("$getLocal/task-reports*") ? 'active' : '' }}"></i>
                                <span class="menu-item">تقرير المهام</span>
                            </a>
                        </li>
                        @endif
                        @if (hasPermission('tasks.settings.global.view'))
                        <li>
                            <a href="{{ route('taskmanager.global-settings.index') }}">
                                <i class="feather icon-settings {{ request()->is("$getLocal/task-settings*") ? 'active' : '' }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Task_Settings') }}</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
            @endif

            {{-- الإعدادات — تُقرأ الآن من `SettingsNavigation` لا مكتوبةً يدوياً.

                 كان هنا نسخةٌ يدويّة من شريط وحدة الإعدادات، وقد تخلّفت عنه:
                 ثلاثٌ وعشرون شاشة موجودةً هناك وغائبةً هنا — الاشتراك والمظهر
                 والعملات والترقيم التسلسلي واللغات وتيليغرام والمستخدمون
                 والأدوار والصلاحيات وأحجام القاعدة وسجلّات الأحداث والجلسات
                 والأخطاء والرسائل ووضع الصيانة والمهام المجدولة وسجلّها
                 والنسخ الاحتياطي واستعادته وإعداداته وتصدير البيانات. والتعليق
                 الذي كان في موضع هذا الشرح يعترف بذلك ويطلب التوحيد.

                 فصارت القائمة تقرأ من المصدر نفسه الذي يقرأ منه شريطُ الوحدة
                 ولوحةُ الإعدادات — من يضيف شاشةً هناك تظهر هنا بلا تعديل، ولا
                 تعود إحدى القائمتين تسبق الأخرى.

                 **والبوّابة `canAny` لا `@can('settings_edit_general_settings')`**:
                 تلك صلاحيةٌ واحدة قديمة كانت تحجب المجموعة كلَّها عمّن يملك
                 «عرض المستخدمين» أو «سجلّ الأخطاء» ولا يملكها — والبنود نفسُها
                 محروسةٌ كلٌّ بصلاحيته داخل `SettingsNavigation::allows()`، فبقاء
                 الحارس الخارجي حجبٌ بلا فائدة. ومن لم يبقَ له بندٌ واحد لا تُطبع
                 له المجموعة أصلاً، فلا عناوين يتيمة.

                 @see \App\Services\Settings\SettingsNavigation
                 @see \Modules\Sitting\resources\views\layouts\sidebar الشريط الذي يقرأ المصدر نفسه --}}
            @php
                $settingsNav = \App\Services\Settings\SettingsNavigation::visible();
                $settingsOwnScreens = hasPermission('settings_edit_general_settings');
            @endphp

            @if ($settingsNav || $settingsOwnScreens)
                <li class="nav-item {{ request()->is("$getLocal/Sitting*") || request()->is("$getLocal/settings*") ? 'active open' : '' }}">
                    <a href="#">
                        <i class="feather icon-settings"></i>
                        <span class="menu-title" data-i18n="Settings">{{ trans('main_trans.Sittings') }}</span>
                    </a>
                    <ul class="menu-content">

                        {{-- لوحةُ الإعدادات وشاشتان لا يعرفهنّ `SettingsNavigation` —
                             بحارسهنّ الذي يفحصه المتحكّم نفسُه
                             (`permission:settings_edit_general_settings`). --}}
                        @if ($settingsOwnScreens)
                            <li class="{{ request()->routeIs('SittingAccount.dashboard') ? 'active' : '' }}">
                                <a href="{{ route('SittingAccount.dashboard') }}">
                                    <i class="feather icon-grid"></i>
                                    <span class="menu-item">{{ __('settings.layout.settings_panel') }}</span>
                                </a>
                            </li>
                        @endif

                        @if ($settingsOwnScreens)
                            <li><a href="{{ route('AccountInfo.index') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item">{{ trans('main_trans.Account_Information') }}</span></a>
                            </li>
                            <li><a href="{{ route('AccountInfo.backgroundColor') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item">{{ trans('main_trans.System_Logo_and_Colors') }}</span></a>
                            </li>
                        @endif

                        @foreach ($settingsNav as $settingsGroup)
                            @foreach ($settingsGroup['items'] as $settingsItem)
                                <li class="{{ request()->url() === $settingsItem['url'] ? 'active' : '' }}">
                                    <a href="{{ $settingsItem['url'] }}" title="{{ $settingsItem['hint'] ?? '' }}">
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item">{{ $settingsItem['label'] }}</span>
                                    </a>
                                </li>

                                {{-- شاشاتٌ تابعة (سجلّ المهام المجدولة، تاريخ النسخ…) —
                                     تُسرد إلى جانب أصلها لأن هذه القائمة بمستوى تداخلٍ واحد. --}}
                                @foreach ($settingsItem['children'] ?? [] as $settingsChild)
                                    <li class="{{ request()->url() === $settingsChild['url'] ? 'active' : '' }}">
                                        <a href="{{ $settingsChild['url'] }}" title="{{ $settingsChild['hint'] ?? '' }}">
                                            <i class="feather icon-corner-down-left"></i>
                                            <span class="menu-item">{{ $settingsChild['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            @endforeach
                        @endforeach

                    </ul>
                </li>
            @endif

            @endif
            </ul>
        </div>
    </div>
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // عناصر واجهة المستخدم
                const statusElement = document.getElementById('location-status');
                const lastUpdateElement = document.getElementById('last-update');
                const nearbyClientsElement = document.getElementById('nearby-clients');
                const startTrackingBtn = document.getElementById('start-tracking');
                const stopTrackingBtn = document.getElementById('stop-tracking');

                // متغيرات التتبع
                let watchId = null;
                let lastLocation = null;
                let isTracking = false;
                let trackingInterval = null;

                // ========== دوال الواجهة ========== //

                // تحديث حالة الواجهة
                function updateUI(status, message) {
                    statusElement.textContent = message;
                    statusElement.className = `alert alert-${status}`;
                    lastUpdateElement.textContent = new Date().toLocaleTimeString();
                }

                // عرض العملاء القريبين
                function displayNearbyClients(count) {
                    if (count > 0) {
                        nearbyClientsElement.innerHTML = `
                <div class="alert alert-info mt-3">
                    <i class="feather icon-users mr-2"></i>
                    يوجد ${count} عميل قريب من موقعك الحالي
                </div>
            `;
                    } else {
                        nearbyClientsElement.innerHTML = '';
                    }
                }

                // ========== دوال التتبع ========== //

                // إرسال بيانات الموقع إلى الخادم
                async function sendLocationToServer(position) {
                    const {
                        latitude,
                        longitude,
                        accuracy
                    } = position.coords;

                    try {
                        const response = await fetch("", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": "{{ csrf_token() }}"
                            },
                            body: JSON.stringify({
                                latitude,
                                longitude,
                                accuracy: accuracy || null
                            })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            updateUI('success', 'تم تحديث موقعك بنجاح');
                            displayNearbyClients(data.nearby_clients || 0);
                            return true;
                        } else {
                            throw new Error(data.message || 'خطأ في الخادم');
                        }
                    } catch (error) {
                        console.error('❌ خطأ في إرسال الموقع:', error);
                        updateUI('danger', `خطأ في تحديث الموقع: ${error.message}`);
                        return false;
                    }
                }

                // معالجة أخطاء الموقع
                function handleGeolocationError(error) {
                    let errorMessage;
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = "تم رفض إذن الوصول إلى الموقع. يرجى تفعيله في إعدادات المتصفح.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = "معلومات الموقع غير متوفرة حالياً.";
                            break;
                        case error.TIMEOUT:
                            errorMessage = "انتهت مهلة طلب الموقع. يرجى المحاولة مرة أخرى.";
                            break;
                        case error.UNKNOWN_ERROR:
                            errorMessage = "حدث خطأ غير معروف أثناء محاولة الحصول على الموقع.";
                            break;
                    }

                    updateUI('danger', errorMessage);
                    if (isTracking) stopTracking();
                }

                // بدء تتبع الموقع
                function startTracking() {
                    if (!navigator.geolocation) {
                        updateUI('danger', 'المتصفح لا يدعم ميزة تحديد الموقع');
                        return;
                    }

                    updateUI('info', 'جاري طلب إذن الموقع...');

                    // طلب الموقع الحالي أولاً
                    navigator.geolocation.getCurrentPosition(
                        async (position) => {
                                const {
                                    latitude,
                                    longitude
                                } = position.coords;
                                lastLocation = {
                                    latitude,
                                    longitude
                                };

                                // إرسال الموقع الأولي
                                await sendLocationToServer(position);

                                // بدء التتبع المستمر
                                watchId = navigator.geolocation.watchPosition(
                                    async (position) => {
                                            const {
                                                latitude,
                                                longitude
                                            } = position.coords;

                                            // التحقق من تغير الموقع بشكل كافي (أكثر من 10 أمتار)
                                            if (!lastLocation ||
                                                getDistance(latitude, longitude, lastLocation.latitude,
                                                    lastLocation.longitude) > 10) {

                                                lastLocation = {
                                                    latitude,
                                                    longitude
                                                };
                                                await sendLocationToServer(position);
                                            }
                                        },
                                        (error) => {
                                            console.error('❌ خطأ في تتبع الموقع:', error);
                                            handleGeolocationError(error);
                                        }, {
                                            enableHighAccuracy: true,
                                            timeout: 10000,
                                            maximumAge: 0,
                                            distanceFilter: 10 // تحديث عند التحرك أكثر من 10 أمتار
                                        }
                                );

                                // بدء التتبع الدوري (كل دقيقة)
                                trackingInterval = setInterval(async () => {
                                    if (lastLocation) {
                                        const fakePosition = {
                                            coords: {
                                                latitude: lastLocation.latitude,
                                                longitude: lastLocation.longitude,
                                                accuracy: 20
                                            }
                                        };
                                        await sendLocationToServer(fakePosition);
                                    }
                                }, 60000);

                                isTracking = true;
                                updateUI('success', 'جاري تتبع موقعك...');
                                if (startTrackingBtn) startTrackingBtn.disabled = true;
                                if (stopTrackingBtn) stopTrackingBtn.disabled = false;
                            },
                            (error) => {
                                console.error('❌ خطأ في الحصول على الموقع:', error);
                                handleGeolocationError(error);
                            }, {
                                enableHighAccuracy: true,
                                timeout: 15000,
                                maximumAge: 0
                            }
                    );
                }

                // إيقاف تتبع الموقع
                function stopTracking() {
                    if (watchId) {
                        navigator.geolocation.clearWatch(watchId);
                        watchId = null;
                    }

                    if (trackingInterval) {
                        clearInterval(trackingInterval);
                        trackingInterval = null;
                    }

                    isTracking = false;
                    updateUI('warning', 'تم إيقاف تتبع الموقع');
                    if (startTrackingBtn) startTrackingBtn.disabled = false;
                    if (stopTrackingBtn) stopTrackingBtn.disabled = true;
                    nearbyClientsElement.innerHTML = '';
                }

                // حساب المسافة بين موقعين (بالمتر)
                function getDistance(lat1, lon1, lat2, lon2) {
                    const R = 6371000; // نصف قطر الأرض بالمتر
                    const φ1 = lat1 * Math.PI / 180;
                    const φ2 = lat2 * Math.PI / 180;
                    const Δφ = (lat2 - lat1) * Math.PI / 180;
                    const Δλ = (lon2 - lon1) * Math.PI / 180;

                    const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                        Math.cos(φ1) * Math.cos(φ2) *
                        Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

                    return R * c;
                }

                // ========== تهيئة الأحداث ========== //

                // أحداث الأزرار
                if (startTrackingBtn) {
                    startTrackingBtn.addEventListener('click', startTracking);
                }

                if (stopTrackingBtn) {
                    stopTrackingBtn.addEventListener('click', stopTracking);
                }

                // بدء التتبع تلقائياً عند تحميل الصفحة
                startTracking();

                // إيقاف التتبع عند إغلاق الصفحة
                window.addEventListener('beforeunload', function() {
                    if (isTracking) {
                        // إرسال بيانات الإغلاق إلى الخادم إذا لزم الأمر
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                const fakePosition = {
                                    coords: {
                                        latitude: position.coords.latitude,
                                        longitude: position.coords.longitude,
                                        accuracy: position.coords.accuracy,
                                        isExit: true
                                    }
                                };
                                sendLocationToServer(fakePosition);
                            },
                            () => {}, {
                                enableHighAccuracy: true
                            }
                        );
                        stopTracking();
                    }
                });
            });
        </script>
    @endsection
