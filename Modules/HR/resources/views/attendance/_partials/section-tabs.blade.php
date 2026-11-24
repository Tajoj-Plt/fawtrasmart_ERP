{{--
    تبويباتُ التنقّل بين شاشات المجموعة الواحدة.

    القائمةُ الجانبية تحمل رابطاً واحداً لكلّ مجموعة («الإجازات»،
    «الورديات») بدل بنودها كلّها، والتنقّلُ بين شاشاتها من هنا.

    الاستعمال: @include('hr::attendance._partials.section-tabs', ['group' => 'leaves'])

    كلُّ تبويبٍ يُعرض لمن يملك صلاحيةَ قائمته — الحارسُ نفسُه في متحكّمها —
    ولا يُعرض الشريط إن بقي تبويبٌ واحد.
--}}
@php
    $tabGroups = [
        'daily' => [
            ['perm' => 'attendance_days.index', 'route' => 'hr.attendance-log.index', 'label' => 'الحضور اليومي', 'icon' => 'feather icon-list', 'match' => 'hr/attendance-log*'],
            ['perm' => 'attendance_team.index', 'route' => 'hr.team-board.index', 'label' => 'متابعة الفريق', 'icon' => 'feather icon-users', 'match' => 'hr/team-board*'],
            ['perm' => 'attendance_records.index', 'route' => 'hr.attendance-punches.index', 'label' => 'سجلات الحضور', 'icon' => 'feather icon-shield', 'match' => 'hr/attendance-punches*'],
            ['perm' => 'attendance_corrections.index', 'route' => 'hr.attendance-corrections.index', 'label' => 'طلبات التصحيح', 'icon' => 'feather icon-edit-3', 'match' => 'hr/attendance-corrections*'],
            ['perm' => 'attendance_corrections.approve', 'route' => 'hr.attendance-correction-approvals.index', 'label' => 'اعتماد التصحيح', 'icon' => 'feather icon-check-square', 'match' => 'hr/attendance-correction-approvals*'],
            ['perm' => 'attendance_books.index', 'route' => 'attendance_sheets.index', 'label' => 'دفاتر الحضور', 'icon' => 'feather icon-book', 'match' => 'hr/attendance-sheets*'],
        ],
        'devices' => [
            ['perm' => 'attendance_places.index', 'route' => 'attendance.settings.geofences.index', 'label' => 'أماكن التسجيل', 'icon' => 'feather icon-map-pin', 'match' => 'hr/settings/geofences*'],
            ['perm' => 'attendance_places.index', 'route' => 'attendance.settings.kiosks.index', 'label' => 'أجهزة التسجيل', 'icon' => 'feather icon-tablet', 'match' => 'hr/settings/kiosks*'],
            ['perm' => 'attendance_devices.index', 'route' => 'hr.device-monitor.index', 'label' => 'حالة الأجهزة', 'icon' => 'feather icon-activity', 'match' => 'hr/device-monitor*'],
            ['perm' => 'attendance_cards.index', 'route' => 'barcode.cards', 'label' => 'بطاقات الحضور', 'icon' => 'feather icon-credit-card', 'match' => 'hr/barcode/cards*'],
            ['perm' => 'attendance_devices.index', 'route' => 'machines.index', 'label' => 'آلات البصمة', 'icon' => 'feather icon-monitor', 'match' => 'hr/machines*'],
        ],
        'leaves' => [
            ['perm' => 'leave_requests.index', 'route' => 'attendance.leave_requests.index', 'label' => 'طلبات الإجازة', 'icon' => 'feather icon-send', 'match' => 'hr/leave_requests*'],
            ['perm' => 'leave_permissions.index', 'route' => 'leave_permissions.index', 'label' => 'أذونات الإجازة', 'icon' => 'feather icon-clock', 'match' => 'hr/leave-permissions*'],
            ['perm' => 'leave_balances.index', 'route' => 'employee_leave_balances.index', 'label' => 'أرصدة الإجازات', 'icon' => 'feather icon-pie-chart', 'match' => 'hr/employee-leave-balances*'],
        ],
        'shifts' => [
            ['perm' => 'shifts.index', 'route' => 'shift_management.index', 'label' => 'الورديات', 'icon' => 'feather icon-clock', 'match' => 'hr/shift_management*'],
            ['perm' => 'shifts.index', 'route' => 'custom_shifts.index', 'label' => 'الورديات المخصصة', 'icon' => 'feather icon-calendar', 'match' => 'hr/custom-shifts*'],
        ],
    ];

    $sectionTabs = array_values(array_filter(
        $tabGroups[$group] ?? [],
        fn ($tab) => hasPermission($tab['perm'])
    ));
@endphp

@if (count($sectionTabs) > 1)
    <ul class="nav stk-tabs mb-3">
        @foreach ($sectionTabs as $tab)
            <li class="nav-item">
                <a href="{{ route($tab['route']) }}"
                    class="nav-link {{ request()->is('*' . $tab['match']) ? 'active' : '' }}">
                    <i class="{{ $tab['icon'] }}"></i> {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
@endif
