@extends('hr::master')

@section('title', 'أيام الحضور')

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'أيام الحضور',
        'icon' => 'calendar',
        'sub' => 'صفٌّ واحدٌ لكل موظفٍ في اليوم: الوردية والدخول والخروج والتأخير والساعات وأثرُها على الراتب',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'أيام الحضور' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'daily'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @php
        // مصدرٌ واحدٌ للتسميات: نسختان تتباعدان بأول تعديل
        $labels = \App\Services\Attendance\DailyAttendanceBoard::stateLabels();
        $stateBadges = [
            'completed' => 'badge-light-success',
            'half_day' => 'badge-light-warning',
            'short' => 'badge-light-danger',
            'present' => 'badge-light-info',
            'on_break' => 'badge-light-warning',
            'pending' => 'badge-secondary',
            'absent' => 'badge-light-danger',
            'leave' => 'badge-light-warning',
            'holiday' => 'badge-light-primary',
            'weekly_off' => 'badge-light-primary',
        ];
        $hasFilters = collect(\Illuminate\Support\Arr::except($filters ?? [], ['date']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    {{-- ملخّصُ اليوم: الجوابُ الذي تُفتح الشاشةُ لأجله كلَّ صباح --}}
    @include('hr::organizational_structure.partials._stats', ['statItems' => [
        ['label' => 'إجمالي الموظفين', 'value' => $summary['total'], 'icon' => 'users', 'tone' => 'primary'],
        ['label' => 'أكملوا اليوم', 'value' => $summary['completed'], 'icon' => 'check-circle', 'tone' => 'success'],
        ['label' => 'حاضرون الآن', 'value' => $summary['present'], 'icon' => 'user-check', 'tone' => 'info'],
        ['label' => 'متأخرون', 'value' => $summary['late'], 'icon' => 'clock', 'tone' => 'muted'],
        ['label' => 'لم يسجّلوا بعد', 'value' => $summary['pending'], 'icon' => 'loader', 'tone' => 'muted'],
        ['label' => 'غائبون', 'value' => $summary['absent'], 'icon' => 'user-x', 'tone' => 'muted'],
        ['label' => 'في إجازة', 'value' => $summary['leave'], 'icon' => 'sun', 'tone' => 'info'],
    ]])

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form method="GET" action="{{ url()->current() }}">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="ul_date">التاريخ</label>
                            <input type="date" id="ul_date" name="date" class="form-control" value="{{ $date->toDateString() }}"
                                   onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="ul_q">الموظف</label>
                            <input type="text" id="ul_q" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="ابحث بالاسم…">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="ul_department_id">القسم</label>
                            <select id="ul_department_id" name="department_id" class="form-control hr-s2">
                                <option value="">الكل</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? '') == $department->id)>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="ul_state">الحالة</label>
                            <select id="ul_state" name="state" class="form-control">
                                <option value="">الكل</option>
                                @foreach($labels as $key => $label)
                                    <option value="{{ $key }}" @selected(($filters['state'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            @include('reports.partials.affiliation-tree', [
                                'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                                'name' => 'branch_id',
                                'showExact' => false,
                                'label' => 'تابع لـ',
                            ])
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card stk-table-card">
        @if (count($rows) > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> حضور يوم {{ $date->toDateString() }}
                    <span class="stk-count">{{ count($rows) }}</span>
                </h6>
            </div>

            <div class="table-responsive">
                @php
                    /* أعمدةُ المدّة تُقرأ بالساعات والدقائق — «ساعة و10 دقائق»
                       لا «70». الصيغةُ في `App\Support\DurationLabel` فتتّفق
                       الشاشاتُ عليها. و«صافي العمل» يبقى `H:MM` كما كان:
                       عمودٌ تُقارَن فيه الصفوفُ بالعين، والعبارةُ الكاملة
                       تُطيله بلا فائدة. */
                    $dur = fn ($minutes) => \App\Support\DurationLabel::minutes($minutes);

                    /* عمودٌ فارغٌ لكل الصفوف لا يُعرض — كان الجدولُ صفوفاً من «—» في أعمدة التأخير والإضافي وأثر الراتب */
                    $colLocation = $rows->contains(fn ($r) => $r['location']);
                    $colLate = $rows->contains(fn ($r) => $r['late_minutes'] > 0);
                    $colEarly = $rows->contains(fn ($r) => $r['early_leave_minutes'] > 0);
                    $colOver = $rows->contains(fn ($r) => $r['overtime_minutes'] > 0);
                    $colDeduction = $rows->contains(fn ($r) => $r['deduction']);
                @endphp
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th>الموظف</th>
                            <th>الدوام المطلوب</th>
                            <th>الحضور ← الانصراف</th>
                            @if($colLocation)<th>المكان</th>@endif
                            <th class="text-center">صافي العمل</th>
                            @if($colLate)<th class="text-center">تأخير</th>@endif
                            @if($colEarly)<th class="text-center">نقص / مبكر</th>@endif
                            @if($colOver)<th class="text-center">إضافي</th>@endif
                            @if($colDeduction)<th class="text-center">أثر الراتب</th>@endif
                            <th class="text-center">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $minutes = $row['working_minutes'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                        <span class="stk-media__body">
                                            {{-- تفصيلُ اليوم الواحد: الصفُّ هنا ملخّص، وما وراءه سجلُّ الحركات والتدقيق --}}
                                            @if ($row['day'] && hasPermission('attendance_days.show'))
                                                <a class="stk-media__title" href="{{ route('attendanceDays.show', $row['day']->id) }}">{{ $row['employee']->full_name }}</a>
                                            @else
                                                <span class="stk-media__title">{{ $row['employee']->full_name }}</span>
                                            @endif
                                            <span class="stk-media__sub">
                                                {{ $row['employee']->department->name ?? '—' }}
                                                @if($row['employee']->branch) · {{ $row['employee']->branch->name }} @endif
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td class="stk-num text-muted text-nowrap">
                                    @if($row['flexible'])
                                        مرن {{ rtrim(rtrim(number_format($row['flexible_hours'], 2, '.', ''), '0'), '.') }} ساعة
                                    @else
                                        {{ $row['expected_in'] ? substr($row['expected_in'], 0, 5) : '—' }}
                                        –
                                        {{ $row['expected_out'] ? substr($row['expected_out'], 0, 5) : '—' }}
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <span class="stk-num fw-bold">{{ $row['check_in'] ? substr($row['check_in'], 0, 5) : '—' }}</span>
                                    ←
                                    <span class="stk-num fw-bold">{{ $row['check_out'] ? substr($row['check_out'], 0, 5) : ($row['check_in'] ? 'لم ينصرف' : '—') }}</span>
                                    @if($row['capped'])
                                        {{-- الفارقُ بين ما قرأته الساعة وما اعتُمد يُقال هنا لا يُخفى --}}
                                        <i class="feather icon-scissors text-muted" title="قُصَّ إلى نهاية الوردية"></i>
                                    @endif
                                    @if($row['channel'])
                                        <div class="small text-muted">{{ $row['channel'] }}</div>
                                    @endif
                                    {{-- حركاتُ اليوم بترتيبها: حضور، استراحة، عودة، انصراف --}}
                                    @if(count($row['timeline']) > 2 || collect($row['timeline'])->contains(fn ($t) => str_starts_with($t['type'], 'rest')))
                                        @php $tlLabels = ['in' => 'حضور', 'out' => 'انصراف', 'rest_in' => 'استراحة', 'rest_out' => 'عودة']; @endphp
                                        <div class="small mt-25" style="white-space:normal">
                                            @foreach($row['timeline'] as $t)
                                                <span class="badge {{ str_starts_with($t['type'], 'rest') ? 'badge-light-warning' : 'badge-light-secondary' }}">{{ $tlLabels[$t['type']] ?? $t['type'] }} {{ $t['time'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                @if($colLocation)
                                    <td>
                                        @if($row['map_url'])
                                            <a href="{{ $row['map_url'] }}" target="_blank" rel="noopener"><i class="feather icon-map-pin"></i> {{ $row['location'] }}</a>
                                        @else
                                            {{ $row['location'] ?? '—' }}
                                        @endif
                                    </td>
                                @endif
                                <td class="text-center stk-num">
                                    {{ $minutes ? sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60) : '—' }}
                                    @if($row['still_working'] && $minutes)
                                        <div class="small text-info">حتى الآن</div>
                                    @endif
                                    @if($row['on_break'])
                                        <div class="small text-warning">في استراحة الآن</div>
                                    @elseif($row['break_minutes'])
                                        <div class="small text-muted">استراحة {{ $dur($row['break_minutes']) }}</div>
                                    @endif
                                </td>
                                @if($colLate)
                                <td class="text-center stk-num text-nowrap {{ $row['is_late'] ? 'text-danger fw-bold' : '' }}">
                                    {{ $dur($row['late_minutes']) }}
                                </td>
                                @endif
                                @if($colEarly)
                                <td class="text-center stk-num text-nowrap {{ $row['early_leave_minutes'] ? 'text-danger fw-bold' : '' }}">
                                    {{ $dur($row['early_leave_minutes']) }}
                                </td>
                                @endif
                                @if($colOver)
                                <td class="text-center stk-num text-nowrap {{ $row['overtime_minutes'] ? 'text-success fw-bold' : '' }}">
                                    {{ $dur($row['overtime_minutes']) }}
                                </td>
                                @endif
                                {{--
                                    «رُحِّل للراتب» أو لا: الخصميةُ التي ولّدها هذا
                                    اليومُ تُقرأ هنا، فلا يُغلَق شهرٌ على تأخيرٍ
                                    ظنّ المراجعُ أنه خُصم وهو لم يُخصم.
                                --}}
                                @if($colDeduction)
                                <td class="text-center">
                                    @if($row['deduction'] && ! hasPermission('deductions.show'))
                                        <span class="badge badge-light-danger">خُصم {{ number_format((float) $row['deduction']->amount, 2) }}</span>
                                    @elseif($row['deduction'])
                                        <a href="{{ route('hr.deductions.show', $row['deduction']->id) }}"
                                           class="badge badge-light-danger"
                                           title="{{ $row['deduction']->deduction_type === 'absence' ? 'خصم غياب' : 'خصم تأخير' }}">
                                            خُصم {{ number_format((float) $row['deduction']->amount, 2) }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                @endif
                                <td class="text-center">
                                    <span class="badge {{ $stateBadges[$row['state']] ?? 'badge-secondary' }}">{{ $labels[$row['state']] ?? $row['state'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>
                    <h5 class="stk-empty__title">لا يوجد موظفون مطابقون</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أيّ موظف في هذا اليوم — جرّب معايير أوسع
                        @else
                            لا يوجد موظفون في نطاقك لهذا اليوم.
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ url()->current() }}?date={{ $date->toDateString() }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <p class="text-muted small mt-2">
        كل بيانات هذا الجدول آلية — لا حقل يكتبه أحد. تعديل أي وقت يمرّ
        بـ@if(hasPermission('attendance_corrections.index'))<a href="{{ route('hr.attendance-corrections.index') }}">طلب تصحيح حضور</a>@else طلب تصحيح حضور @endif،
        والأختام الخام في @if(hasPermission('attendance_records.index'))<a href="{{ route('hr.attendance-punches.index') }}">سجلات الحضور</a>@else سجلات الحضور @endif.
        @permission('attendance_days.index')
            {{-- القائمةُ الإدارية القديمة تبقى موصولة — لا تُهجَر شاشةٌ تُستعمل --}}
            وإدارةُ صفوف الأيام (إنشاء/تعديل/حذف) في
            <a href="{{ route('attendanceDays.index') }}">قائمة أيام الحضور</a>.
        @endpermission
    </p>
</div>
@endsection
