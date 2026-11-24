{{--
    القائمة الجانبية لموديول الفروع — قائمةٌ مسطّحة بلا أقسامٍ ولا طيّ.

    جُرّبت المجموعاتُ القابلة للطيّ على نمط شريط الحسابات، ولم تكن في
    محلّها هنا: الطيُّ يكسب مساحةً حين تكون البنود كثيرة، وبنودُ هذه
    الوحدة قليلة — فلم يكن يكسب شيئاً، وكان يُخفي نصفَها خلف نقرة.
    والبند المسطّح يحمل أيقونته هو، فيُعرَف بالنظرة لا بالقراءة.

    البنود تُقرأ من BranchesNavigation — مصدرٌ واحد للقائمة وللوحة، وفيه
    تُحسب صلاحيةُ النظام (تُخفي) وقفلُ الباقة (يُقفل ولا يُخفي) وشارةُ
    الامتلاء (تُنذر عند ٨٠٪ من الحدّ ولا تمنع).
--}}
@php
    use App\Support\PlanLock;

    /*
        `visible()` تعيد البنودَ مُجمَّعة، والقائمةُ هنا مسطّحة — فتُدمج
        المجموعاتُ في قائمةٍ واحدة بترتيبها.

        والدمجُ بـ `array_merge` لا بـ `+`: مفاتيحُ البنود عدديّة، و`+`
        تُبقي أوّلَ ما وجدَ عند تكرار المفتاح فتُسقط بنودَ المجموعات
        التالية كلَّها.
    */
    $navItems = [];
    foreach (\Modules\Branches\Services\BranchesNavigation::visible() as $group) {
        $navItems = array_merge($navItems, $group['items']);
    }
@endphp

<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto">
                <a class="navbar-brand" href="{{ route('branches.dashboard') }}">
                    <div class="brand-logo"></div>
                    <h2 class="brand-text mb-0">{{ __('branches.common.branches') }}</h2>
                </a>
            </li>
            <li class="nav-item nav-toggle">
                <a class="nav-link modern-nav-toggle pr-0" data-toggle="collapse">
                    <i class="feather icon-x d-block d-xl-none font-medium-4 primary toggle-icon"></i>
                    <i class="toggle-icon feather icon-disc font-medium-4 d-none d-xl-block collapse-toggle-icon primary"
                       data-ticon="icon-disc"></i>
                </a>
            </li>
        </ul>
    </div>

    <div class="shadow-bottom"></div>

    <div class="main-menu-content">
        <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">

            <li class="nav-item {{ request()->routeIs('branches.dashboard') ? 'active' : '' }}">
                <a href="{{ route('branches.dashboard') }}">
                    <i class="feather icon-grid"></i>
                    <span class="menu-title">{{ __('branches.nav.dashboard') }}</span>
                </a>
            </li>

            @foreach ($navItems as $item)
                {{--
                    البند المقفول يبقى ظاهراً بقفلٍ أحمر ويقود إلى شاشة
                    الاشتراك؛ وشارةُ الامتلاء تظهر عند مقاربة الحدّ ولا
                    تُخفي البند — من امتلأ سقفُه يحتاج شاشته ليحذف أو ليرى
                    ما عنده.
                --}}
                <li class="nav-item {{ request()->routeIs($item['route']) ? 'active' : '' }} {{ ($item['locked'] ?? false) ? 'stk-plan-locked' : '' }}">
                    <a href="{{ $item['url'] }}" title="{{ $item['hint'] }}">
                        <i class="feather icon-{{ ($item['locked'] ?? false) ? 'lock' : $item['icon'] }}"></i>
                        <span class="menu-title">{{ $item['label'] }}</span>

                        @if ($item['locked'] ?? false)
                            {!! PlanLock::menuBadge() !!}
                        @elseif ($item['usage'] ?? null)
                            <span class="stk-plan-badge {{ $item['usage']['exhausted'] ? 'stk-plan-badge--full' : 'stk-plan-badge--warn' }}"
                                  title="{{ $item['usage']['title'] }}">{{ $item['usage']['text'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
