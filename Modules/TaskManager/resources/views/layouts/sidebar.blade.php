@php
    /*
        `$settings` كان يُبنى هنا باستعلامٍ على `application_settings` في كل
        رسمٍ للقائمة، ولم يكن يقرؤه إلا شرطٌ واحدٌ في كتلة مبيعاتٍ جسمُها
        فارغ — تُصدِر لا شيء مهما كانت قيمته. أُسقطت الكتلة ومعها الاستعلام.
        وحالةُ تفعيل الوحدة تُقرأ الآن من
        [\App\Services\Settings\ApplicationRegistry::isActive()] حيث تُقرأ
        بالافتراض الصحيح للصفّ الغائب.
    */
    $getLocal = App::getLocale();
@endphp


{{--
    القائمة الجانبية لوحدة إدارة المهام — لكل شاشات الوحدة، بلا استثناء.

    كان في أوّل هذا الملف تفويضٌ يُسقطه كلَّه:

        @if (auth()->user()->isEmployee())
            @include('layouts.sidebar')
        @else
            … قائمة الوحدة …
        @endif

    فالموظّف — وهو أكثر من يعمل في المهام — لا يرى قائمة الوحدة أبداً، بل
    القائمةَ المختصرة العامّة التي فيها بندٌ واحد للمهام. أي أن للوحدة
    قائمةً لا تُعرض لمن يستعملها.

    وقد أُسقط التفويض: القائمة الآن واحدةٌ للجميع داخل شاشات الوحدة، وهو
    أصل النظام كما يصفه [\App\Support\UnifiedSidebar]: «لكل وحدةٍ قائمتُها
    الجانبية». والانتقال إلى وحدةٍ أخرى من الترويسة، أو بتفعيل «القائمة
    الجانبية الموحّدة» لدور المستخدم من شاشة الأدوار.
--}}
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
            {{-- الرئيسيه --}}


{{--
    قائمة الوحدة لغير الموظفين — شاشات إدارة المهام كلّها.

    كانت محكومةً بثلاثة شروطٍ لا يصحّ منها واحد:

    ١. `@canany(['tasks_view_tasks'])` — اسمٌ من النظام القديم لا تطلبه
       مسارات الوحدة في شيء؛ هي تطلب `tasks.*` من
       [\App\Constants\permissions\TaskManagerPermissions]. فالقائمة
       تظهر لمن يملك القديم وتردّه المسارات بـ403.

    ٢. `isset($settings['tasks']) && $settings['tasks'] === 'active'` —
       يقرأ `application_settings` مباشرةً، وغيابُ الصفّ عنده يعني
       «معطَّل» فتختفي القائمة كلّها. و[\App\Services\Settings\ApplicationRegistry::isActive()]
       يقرأ نفس الجدول بالافتراض الصحيح: الغياب = مفعَّل (وهو حال أي
       تركيبٍ لم يُفتح فيه «شاشة التطبيقات» قطّ). فصار الاستدعاء منه.

    ٣. `@can('settings_edit_general_settings')` — صلاحيةُ **الإعدادات
       العامّة** تحرس قائمة المهام: من يعمل في المهام ولا يملك إعدادات
       النظام لا يرى الوحدة إطلاقاً. ولا وجه لاشتراطها هنا.

    وكانت تنقصها السبرنتات والإعدادات العامّة — وهما شاشتان قائمتان.
--}}
@if (\App\Services\Settings\ApplicationRegistry::isActive('tasks') && canAny([
    'tasks.board.view', 'tasks.board.dashboard', 'tasks.board.backlog',
    'tasks.items.view', 'tasks.projects.view', 'tasks.workspaces.view',
    'tasks.sprints.view', 'tasks.reports.view', 'tasks.settings.global.view',
]))
    <li class="nav-item {{ request()->is("$getLocal/task*") || request()->is("$getLocal/projects*") || request()->is("$getLocal/workspaces*") || request()->is("$getLocal/sprints*") ? 'active open' : '' }}">
        <a href="#">
            <i class="feather icon-check-square"></i>
            <span class="menu-title">{{ trans('main_trans.tasks') }}</span>
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

            {{-- إعدادات المشروع الواحد — لا تظهر إلا داخل مشروع، فمسارها
                 يلزمه `projectId`. والإعدادات العامّة تحتها لكل المشاريع. --}}
            @if (isset($project) && $project && hasPermission('tasks.settings.view'))
            <li>
                <a href="{{ route('taskmanager.settings.index', ['projectId' => $project->id]) }}">
                    <i class="feather icon-sliders"></i>
                    <span class="menu-item">{{ trans('main_trans.Task_Settings') }}</span>
                </a>
            </li>
            @endif
            @if (hasPermission('tasks.settings.global.view'))
            <li>
                <a href="{{ route('taskmanager.global-settings.index') }}">
                    <i class="feather icon-settings {{ request()->is("$getLocal/task-settings*") ? 'active' : '' }}"></i>
                    <span class="menu-item">الإعدادات العامّة للمهام</span>
                </a>
            </li>
            @endif
        </ul>
    </li>
@endif

            {{-- `</ul>` كان ناقصاً: `<ul class="navigation navigation-main">`
                 يُفتح ولا يُغلق، فيُغلقه المتصفّح باجتهاده عند `</div>`.
                 يمرّ في الغالب ويُنتج فرقاً في الهوامش أحياناً. --}}
        </ul>
    </div>
</div>
