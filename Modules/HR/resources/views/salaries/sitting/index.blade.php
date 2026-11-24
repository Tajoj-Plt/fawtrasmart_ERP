@extends('hr::master')

@section('title', 'إعدادات الرواتب')

@section('content')
@php
    use App\Constants\planFeatures\HrPlanFeatures;
    use App\Support\PlanLock;

    $tile = function (string $label, string $icon, string $routeName, string $hint, string $feature = HrPlanFeatures::SALARY_ITEMS) {
        $locked = PlanLock::locked($feature);

        return [
            'label' => $label,
            'icon' => $icon,
            'hint' => $hint,
            'href' => $locked ? PlanLock::url() : route($routeName),
            'locked' => $locked,
            'reason' => $locked ? PlanLock::reason($feature) : null,
        ];
    };
@endphp

    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => 'إعدادات الرواتب',
            'icon' => 'settings',
            'sub' => 'يوم الصرف والأتمتة، الحسابات، الإشعارات، الأجر الإضافي، التأمينات، نهاية الخدمة، والملف البنكي',
        ])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-payroll-settings.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه إعداداتُ الرواتب: الحساباتُ التي تُقيَّد عليها، وقواعدُ الاحتساب العامّة. وما يُضبَط هنا يظهر أثرُه في كلّ قيدِ راتبٍ يُكتب بعده.',
            'blocks' => [
                'لماذا هي أخطرُ من شاشةٍ عاديّة' => [
                    'حسابٌ خاطئٌ هنا لا يُخطئ قيداً واحداً: يُخطئ كلَّ صرفةٍ تُكتب بعده حتى يُنتبَه إليه.',
                    'والأثرُ على ما يُحتسَب بعد التغيير؛ والقيودُ التي كُتبت تبقى على ما كُتبت به.',
                ],
                'ما تحكمه' => [
                    'يومَ الصرف وأتمتتَه واعتمادَه، وحساباتِ المصروف والذمّة والسلف والخصميات، وقواعدَ الأجر الإضافي والتأمينات ونهاية الخدمة.',
                    'والعملةُ عملةُ النظام من شاشة «العملات» — لا عملةَ مستقلّةً للرواتب.',
                ],
            ],
            'facts' => [
                'خطأٌ هنا يتكرّر في كلّ صرفةٍ بعده',
                'الأثرُ على ما يُحتسَب بعد التغيير',
            ],
        ])


        <div class="stk-tiles">
            @if (hasPermission('payroll_settings.manage'))
                @php $t = $tile('الإعدادات العامة', 'settings', 'SalarySittings.general', 'يوم الصرف، وأتمتة التوليد والصرف، وضوابط الاعتماد'); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            @if (hasPermission('payroll_settings.manage'))
                @php $t = $tile('حسابات الرواتب', 'briefcase', 'SalarySittings.accounts', 'عرضُ كلّ حسابات الرواتب — تُربط من إعدادات الحسابات المحاسبية'); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            @if (canAny(['contracts.notifications', 'payroll_settings.manage']))
                @php $t = $tile('اشعارات العقد', 'bell', 'SalarySittings.notifications', 'تنبيهات قبل انتهاء العقد والوثائق'); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            @if (hasPermission('payroll_settings.manage'))
                @php $t = $tile('اعدادات العمولات', 'percent', 'SalarySittings.commissions', 'مصدرُها أهداف المبيعات: الحساب والخزينة والاعتماد'); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            @if (hasPermission('payroll_settings.manage'))
                @php $t = $tile('إعدادات السلف', 'arrow-up-circle', 'SalarySittings.advances', 'أقسامُ «الموظف المعتمد» في طلب السلفة', HrPlanFeatures::ADVANCES); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            {{--
                بنودُ الرواتب نزلت هنا من قائمة «المرتبات» الجانبية: تُضبط
                مرّةً عند التأسيس ثم تُفتح نادراً، والقائمةُ كانت عشرين بنداً
                يغرق فيها ما يُفتَح كلَّ شهر.

                والحارسُ `salary_items.index` هو ما تفحصه الشاشةُ فعلاً
                (`SalaryItemsController`)؛ وكانت القائمةُ الجانبية تحرسها
                بصلاحيات «العقود» فيُعرض البندُ لمن يُردّ عنه بـ403.

                وبلاطةُ «قوالب الرواتب» أُزيلت (2026-09-24): القوالبُ أُلغيت،
                وبنودُ الراتب تُضاف مباشرةً في العقد.
            --}}
            @if (hasPermission('salary_items.index'))
                @php $t = $tile('بنود الرواتب', 'list', 'SalaryItems.index', 'الاستحقاقات والاستقطاعات وصيغُ احتسابها'); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            {{--
                بلاطةُ «كتالوج البدلات والاستقطاعات» أُزيلت (2026-09-24): الكتالوجُ
                دُمج في «بنود الرواتب» — الخضوعُ للتأمينات وأجرُ نهاية الخدمة
                علَمان على البند نفسِه، ومساراتُ الكتالوج تُحوِّل إلى البنود.
            --}}

            @if (hasPermission('overtime.settings'))
                @php $t = $tile('الأجر الإضافي', 'clock', 'SalarySittings.overtime', 'احتساب الساعات الإضافية ومضاعفاتها'); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            @if (hasPermission('insurance.settings'))
                @php $t = $tile('التأمينات الاجتماعية', 'shield', 'hr.gosi.settings', 'نسب الاستقطاع والسقف، وعرضُ حساباتها', HrPlanFeatures::PAYROLL); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            @if (hasPermission('insurance.index'))
                @php $t = $tile('سداد التأمينات', 'send', 'hr.gosi.remittances', 'المحجوز والمستحق للتأمينات، وكشف الاشتراكات، وتسجيل السداد', HrPlanFeatures::PAYROLL); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            @if (hasPermission('bank_files.index'))
                @php $t = $tile('ملف الرواتب البنكي', 'credit-card', 'hr.bank-files.index', 'توليد ملف تحويل للبنك من الرواتب المصروفة', HrPlanFeatures::PAYROLL); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            {{--
                إعداداتُ الملف البنكي ونهاية الخدمة كانت تُبلغ من شاشتيهما
                وحدهما — فمن يفتح «إعدادات الرواتب» لا يجدهما بين الإعدادات.
            --}}
            @if (hasPermission('payroll_settings.manage'))
                @php $t = $tile('إعدادات الملف البنكي', 'file-text', 'hr.bank-files.settings', 'صيغة الملف وبنك المنشأة', HrPlanFeatures::PAYROLL); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif

            @if (hasPermission('eos.settings'))
                @php $t = $tile('مكافأة نهاية الخدمة', 'award', 'hr.eos.settings', 'شرائح المدّة، والأجر، وعرضُ حسابَي المصروف والمخصص', HrPlanFeatures::PAYROLL); @endphp
                <a href="{{ $t['href'] }}" class="stk-tile {{ $t['locked'] ? 'stk-tile--locked' : '' }}" @if ($t['locked']) title="{{ $t['reason'] }}" @endif>
                    <span class="stk-tile__icon"><i class="feather icon-{{ $t['icon'] }}"></i></span>
                    <span class="stk-tile__body">
                        <h3 class="stk-tile__title">{{ $t['label'] }}</h3>
                        <p class="stk-tile__hint">{{ $t['hint'] }}</p>
                    </span>
                </a>
            @endif
        </div>
    </div>
@endsection
