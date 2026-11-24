{{--
    لوحةُ متابعة الفريق — جدولٌ حيّ.

    كانت بطاقاتٍ متجاورة: تُقرأ بطاقةً بطاقة، ولا تُقارَن أوقاتُها بالعين،
    ولا تُرتَّب، ولا تتّسع لعمود. وهي تعرض ما يعرضه «الحضور اليومي» نفسُه
    (المصدرُ واحد: `DailyAttendanceBoard`) — فصارت جدولاً بتصميم تلك الشاشة
    كي يُقارَن الصفُّ بالصفّ، وبقي لها ما تنفرد به: الساعةُ الحيّة،
    والتحديثُ الذاتيّ كلَّ دقيقة، وحصرُ المعروضين في فريق المستخدم.

    والفرقُ بين الشاشتين مكتوبٌ في رأسها لا مُضمَر — فالسؤالُ «وما يفرّقها
    عن الحضور اليومي؟» سؤالُ من يقرأ الشاشة، وجوابُه يسكن الشاشة.
--}}
@extends('hr::master')

@section('title', 'متابعة الفريق')

@push('styles')
<style>
    .tb-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:14px; }
    .tb-clock { font-size:1.9rem; font-weight:800; font-variant-numeric: tabular-nums; line-height:1; }
    .tb-clock small { display:block; font-size:.72rem; font-weight:400; color: var(--app-muted,#6b7280); margin-top:4px; }
    .tb-live { display:inline-flex; align-items:center; gap:6px; font-size:.72rem; color: var(--app-muted,#6b7280); }
    .tb-live i { width:8px; height:8px; border-radius:50%; background: var(--app-success,#16a34a); display:inline-block;
        animation: tb-pulse 2s ease-in-out infinite; }
    @keyframes tb-pulse { 0%,100% { opacity:1; } 50% { opacity:.25; } }

    /* شرحُ الفرق: سطران يُغنيان عن السؤال */
    .tb-note { background: var(--app-surface,#fff); border:1px solid var(--app-border,#e5e7eb);
        border-right:4px solid var(--app-primary,#6366f1); border-radius:10px; padding:12px 15px; margin-bottom:16px;
        font-size:.82rem; line-height:1.9; }
    .tb-note b { font-weight:700; }
    .tb-note__compare { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:10px; margin-top:10px; }
    .tb-note__cell { background: var(--app-body-bg,#f9fafb); border-radius:8px; padding:9px 12px; }
    .tb-note__cell h6 { font-size:.78rem; font-weight:700; margin:0 0 4px; }
    .tb-note__cell p { margin:0; font-size:.76rem; color: var(--app-muted,#6b7280); line-height:1.8; }

    .tb-tiles { display:grid; grid-template-columns:repeat(auto-fit, minmax(118px,1fr)); gap:10px; margin-bottom:16px; }
    .tb-tile { background: var(--app-surface,#fff); border:1px solid var(--app-border,#e5e7eb);
        border-top:3px solid var(--app-muted,#9ca3af); border-radius:10px; padding:11px 13px; cursor:pointer; transition:.15s; }
    .tb-tile:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.06); }
    .tb-tile.active { box-shadow:0 0 0 2px var(--app-primary,#6366f1) inset; }
    .tb-tile .v { font-size:1.6rem; font-weight:800; line-height:1; font-variant-numeric: tabular-nums; }
    .tb-tile .l { font-size:.72rem; color: var(--app-muted,#6b7280); margin-top:4px; }
    .tb-tile--present, .tb-tile--on_break { border-top-color: var(--app-info,#0ea5e9); }
    .tb-tile--late      { border-top-color: var(--app-warning,#d97706); }
    .tb-tile--pending   { border-top-color: var(--app-muted,#9ca3af); }
    .tb-tile--absent, .tb-tile--short { border-top-color: var(--app-danger,#dc2626); }
    .tb-tile--leave, .tb-tile--holiday, .tb-tile--weekly_off { border-top-color:#8b5cf6; }
    .tb-tile--completed, .tb-tile--half_day { border-top-color: var(--app-success,#16a34a); }

    .tb-state { font-size:.7rem; padding:3px 10px; border-radius:999px; white-space:nowrap;
        background: var(--app-body-bg,#f3f4f6); border:1px solid var(--app-border,#e5e7eb); }
    .tb-late { color: var(--app-danger,#b91c1c); font-weight:700; }
    .tb-empty { text-align:center; padding:40px 20px; color: var(--app-muted,#6b7280); }
    tr.tb-row--hidden { display:none; }

    /* ── رأسُ المجموعة: فريقُ مديرٍ مباشرٍ واحد ── */
    /* تدرّجٌ خفيفٌ بلون النظام بدل `--app-body-bg` — فهي بيضاءُ في بعض الثيمات فلا يتميّز الرأس */
    tr.tb-group > td { background: color-mix(in srgb, var(--app-primary,#6366f1) 7%, transparent);
        padding:10px 14px; border-top:2px solid var(--app-border,#e5e7eb); }
    .tb-group__bar { display:flex; align-items:center; flex-wrap:wrap; gap:10px; }
    .tb-group__toggle { background:none; border:0; padding:0 2px; color: var(--app-muted,#6b7280); cursor:pointer; line-height:1; }
    .tb-group__toggle i { transition: transform .15s; display:inline-block; }
    .tb-group--collapsed .tb-group__toggle i { transform: rotate(-90deg); }
    .tb-group__name { font-weight:700; font-size:.88rem; }
    .tb-group__mine { font-size:.66rem; font-weight:600; background: var(--app-primary,#6366f1); color:#fff;
        border-radius:999px; padding:2px 8px; margin-right:6px; }
    .tb-group__title { font-size:.74rem; color: var(--app-muted,#6b7280); }
    .tb-group__counts { margin-right:auto; font-size:.76rem; color: var(--app-muted,#6b7280); font-variant-numeric: tabular-nums; }
    .tb-group__counts b { font-size:.86rem; color: var(--app-fg,#111827); }
    .tb-group__counts em { font-style:normal; font-weight:700; color: var(--app-fg,#111827); }
    .tb-group__hint { font-size:.73rem; color: var(--app-warning,#b45309); margin:6px 0 0 0; padding-right:26px; }
    tr.tb-group--orphan > td { box-shadow: inset -3px 0 0 var(--app-warning,#d97706);
        background: color-mix(in srgb, var(--app-warning,#d97706) 8%, transparent); }
    .tb-indent { width:34px; box-shadow: inset -2px 0 0 var(--app-border,#e5e7eb); }
</style>
@endpush

@section('content')
@include('stock::components.page-head', [
    'title' => 'متابعة الفريق',
    'crumbs' => ['الحضور' => null, 'متابعة الفريق' => null],
])

@include('hr::attendance._partials.section-tabs', ['group' => 'daily'])

@php
    $labels = \App\Services\Attendance\DailyAttendanceBoard::stateLabels();
    $dur = fn ($minutes) => \App\Support\DurationLabel::minutes($minutes);

    /* عمودٌ فارغٌ لكل الصفوف لا يُعرض — كما في «الحضور اليومي» */
    $colLocation = $rows->contains(fn ($r) => $r['location']);
    $colLate = $rows->contains(fn ($r) => $r['late_minutes'] > 0);
    $colWork = $rows->contains(fn ($r) => $r['working_minutes'] > 0);
    $colEarly = $rows->contains(fn ($r) => $r['early_leave_minutes'] > 0);
    $colOver = $rows->contains(fn ($r) => $r['overtime_minutes'] > 0);
    $colBreak = $rows->contains(fn ($r) => $r['break_minutes'] > 0 || $r['on_break']);
    $colDeduction = $rows->contains(fn ($r) => $r['deduction']);

    /* بطاقاتُ الحالات تُبنى من الحالات الموجودة فعلاً، فيصحّ جمعُها على الإجمالي.
       و«متأخرون» صفةٌ على حالةٍ لا حالةٌ مستقلّة — الحاضرُ قد يكون متأخراً — فتُفرَد بعلامة. */
    $stateTiles = collect($labels)
        ->map(fn ($label, $state) => ['state' => $state, 'label' => $label, 'count' => $summary[$state] ?? 0])
        ->filter(fn ($tile) => $tile['count'] > 0)
        ->values();
@endphp

<div class="content-body">

    <div class="tb-head">
        <div>
            <div class="tb-clock" id="tb-clock">—:—:—</div>
            <small>{{ $manager ? 'فريق '.$manager->full_name : 'الموظفون ضمن نطاق رؤيتك' }} · {{ $date->format('Y-m-d') }}</small>
        </div>
        <div class="d-flex align-items-center" style="gap:14px;">
            <span class="tb-live"><i></i> يُحدَّث تلقائياً كل دقيقة</span>
            @if($manager)
            <form method="GET" class="m-0 d-flex align-items-center" style="gap:8px;">
                <input type="hidden" name="all_levels" value="0">
                <label class="m-0" style="font-size:.78rem;">
                    <input type="checkbox" name="all_levels" value="1"
                           @checked($includeIndirect) onchange="this.form.submit()">
                    يشمل المستويات الأدنى
                </label>
            </form>
            @endif
        </div>
    </div>

    <div class="tb-note">
        <b>ما هذه الشاشة؟</b>
        حالةُ <b>فريقك اليوم</b> لحظةً بلحظة: صفٌّ لكل موظفٍ يتبعك، يتحدّث من نفسه كل دقيقة بلا إعادة تحميل،
        ومعه ساعةُ الخادم لا ساعةُ جهازك. تُفتح لتتصرّف الآن — من لم يسجّل؟ من تأخّر؟ من ما زال على رأس العمل؟

        <div class="tb-note__compare">
            <div class="tb-note__cell">
                <h6><i class="feather icon-users"></i> متابعة الفريق (هذه الشاشة)</h6>
                <p>
                    <b>مَن:</b> من يتبعونك إدارياً فقط{{ $includeIndirect ? ' (بما فيهم المستويات الأدنى)' : ' (المباشرون وحدهم)' }}.<br>
                    <b>متى:</b> اليوم وحده، ويتجدّد ذاتياً.<br>
                    <b>لماذا:</b> متابعةٌ لحظيّةٌ تُتَّخذ بها خطوةٌ الآن.
                </p>
            </div>
            <div class="tb-note__cell">
                <h6><i class="feather icon-list"></i> الحضور اليومي</h6>
                <p>
                    <b>مَن:</b> كلُّ من يسمح نطاقُ رؤيتك به، بفلاتر القسم والفرع والحالة.<br>
                    <b>متى:</b> أيُّ يومٍ تختاره.<br>
                    <b>لماذا:</b> مراجعةٌ وتدقيق — حركاتُ اليوم مفصّلةً وأثرُها على الراتب.
                </p>
                @if (hasPermission('attendance_days.index'))
                    <a href="{{ route('hr.attendance-log.index', ['date' => $date->toDateString()]) }}" class="small">
                        <i class="feather icon-arrow-left"></i> افتح الحضور اليومي لهذا اليوم
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if($rows->isEmpty())
        <div class="card"><div class="tb-empty">
            <i class="feather icon-users" style="font-size:2rem;"></i>
            <p class="mt-2 mb-0">لا يوجد موظفون ضمن نطاق رؤيتك.</p>
            <small>مَن تعرضهم هذه اللوحة يحدّده نطاقُ الرؤية في دورك (فريقك / قسمك / فرعك / الكل).</small>
        </div></div>
    @else
        {{-- البطاقاتُ مرشِّحات: نقرةٌ على «متأخرون» تُبقيهم وحدهم في الجدول --}}
        <div class="tb-tiles">
            <div class="tb-tile" data-filter="">
                <div class="v">{{ $summary['total'] }}</div><div class="l">إجمالي الفريق</div>
            </div>
            @foreach($stateTiles as $tile)
                <div class="tb-tile tb-tile--{{ $tile['state'] }}" data-filter="{{ $tile['state'] }}">
                    <div class="v" data-count="{{ $tile['state'] }}">{{ $tile['count'] }}</div>
                    <div class="l">{{ $tile['label'] }}</div>
                </div>
            @endforeach
            <div class="tb-tile tb-tile--late" data-filter="late">
                <div class="v" data-count="late">{{ $summary['late'] }}</div>
                <div class="l">متأخرون <small>(صفة لا حالة)</small></div>
            </div>
        </div>

        @php
            /* عددُ الأعمدة المعروضة — صفُّ رأس المجموعة يمتدّ عليها كلِّها */
            $colSpan = 5 + ($colWork ? 1 : 0) + ($colBreak ? 1 : 0) + ($colLate ? 1 : 0)
                + ($colEarly ? 1 : 0) + ($colOver ? 1 : 0) + ($colDeduction ? 1 : 0) + ($colLocation ? 1 : 0);
        @endphp

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-users"></i> فريقك يوم {{ $date->toDateString() }}
                    <span class="stk-count" id="tb-visible">{{ count($rows) }}</span>
                </h6>
                <span class="text-muted" style="font-size:.76rem;">
                    موزَّعون على {{ count($groups) }} مديراً مباشراً — كما حُدِّد في ملفّ كلّ موظف
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:34px;"></th>
                            <th>الموظف</th>
                            <th>الدوام المطلوب</th>
                            <th>الحضور ← الانصراف</th>
                            @if($colWork)<th class="text-center">صافي العمل</th>@endif
                            @if($colBreak)<th class="text-center">استراحة</th>@endif
                            @if($colLate)<th class="text-center">تأخير</th>@endif
                            @if($colEarly)<th class="text-center">انصراف مبكر</th>@endif
                            @if($colOver)<th class="text-center">إضافي</th>@endif
                            @if($colDeduction)<th class="text-center">أثر الراتب</th>@endif
                            @if($colLocation)<th>المكان</th>@endif
                            <th class="text-center">الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="tb-body">
                        @foreach($groups as $group)
                            {{-- رأسُ المجموعة: المديرُ المباشر وفريقُه وملخّصُ يومهم --}}
                            <tr class="tb-group {{ $group['orphan'] ? 'tb-group--orphan' : '' }}"
                                data-group="{{ $group['manager_id'] }}">
                                <td colspan="{{ $colSpan }}">
                                    <div class="tb-group__bar">
                                        <button type="button" class="tb-group__toggle" aria-expanded="true" title="طيّ / فتح">
                                            <i class="feather icon-chevron-down"></i>
                                        </button>
                                        <span class="tb-group__name">
                                            <i class="feather {{ $group['orphan'] ? 'icon-alert-triangle' : 'icon-user-check' }}"></i>
                                            {{ $group['name'] }}
                                            @if($group['is_mine'])<span class="tb-group__mine">فريقي المباشر</span>@endif
                                        </span>
                                        @if($group['title'])<span class="tb-group__title">{{ $group['title'] }}</span>@endif

                                        <span class="tb-group__counts" data-group-counts="{{ $group['manager_id'] }}">
                                            <b data-gc="total">{{ count($group['rows']) }}</b> موظف
                                            <em data-gc="present">{{ $group['summary']['present'] }}</em> حاضر ·
                                            <em data-gc="late">{{ $group['summary']['late'] }}</em> متأخر ·
                                            <em data-gc="absent">{{ $group['summary']['absent'] }}</em> غائب ·
                                            <em data-gc="pending">{{ $group['summary']['pending'] }}</em> لم يسجّل
                                        </span>
                                    </div>
                                    @if($group['orphan'])
                                        <div class="tb-group__hint">
                                            هؤلاء لم يُحدَّد لهم مديرٌ مباشر في ملفّاتهم — حدِّدْه من «تعديل الموظف» ليظهروا تحت مديرهم.
                                        </div>
                                    @endif
                                </td>
                            </tr>

                            @foreach($group['rows'] as $row)
                            @php $minutes = $row['working_minutes']; @endphp
                            <tr data-employee="{{ $row['employee']->id }}"
                                data-state="{{ $row['state'] }}"
                                data-late="{{ $row['is_late'] ? 1 : 0 }}"
                                data-in-group="{{ $group['manager_id'] }}">
                                <td class="tb-indent"></td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                        <span class="stk-media__body">
                                            @if ($row['day'] && hasPermission('attendance_days.show'))
                                                <a class="stk-media__title" href="{{ route('attendanceDays.show', $row['day']->id) }}">{{ $row['employee']->full_name }}</a>
                                            @else
                                                <span class="stk-media__title">{{ $row['employee']->full_name }}</span>
                                            @endif
                                            <span class="stk-media__sub">
                                                {{ $row['employee']->job_title->name ?? '—' }}
                                                @if($row['employee']->department) · {{ $row['employee']->department->name }} @endif
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
                                    <span class="stk-num fw-bold" data-cell="in">{{ $row['check_in'] ? substr($row['check_in'], 0, 5) : '—' }}</span>
                                    ←
                                    <span class="stk-num fw-bold" data-cell="out">{{ $row['check_out'] ? substr($row['check_out'], 0, 5) : ($row['check_in'] ? 'لم ينصرف' : '—') }}</span>
                                    <div class="small text-muted" data-cell="channel">{{ $row['channel'] }}</div>
                                    @if($row['capped'])
                                        {{-- الفارقُ بين ما قرأته الساعة وما اعتُمد يُقال لا يُخفى --}}
                                        <i class="feather icon-scissors text-muted" title="قُصَّ إلى نهاية الوردية"></i>
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
                                @if($colWork)
                                    <td class="text-center stk-num" data-cell="work">
                                        {{ $minutes ? sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60) : '—' }}
                                        @if($row['still_working'] && $minutes)<div class="small text-info">حتى الآن</div>@endif
                                    </td>
                                @endif
                                @if($colBreak)
                                    <td class="text-center stk-num text-nowrap {{ $row['on_break'] ? 'text-warning fw-bold' : '' }}" data-cell="break">
                                        {{ $row['break_minutes'] ? $dur($row['break_minutes']) : '—' }}
                                        @if($row['on_break'])<div class="small text-warning">في استراحة الآن</div>@endif
                                    </td>
                                @endif
                                @if($colLate)
                                    <td class="text-center stk-num text-nowrap {{ $row['is_late'] ? 'text-danger fw-bold' : '' }}" data-cell="late">
                                        {{ $row['late_minutes'] ? $dur($row['late_minutes']) : '—' }}
                                    </td>
                                @endif
                                @if($colEarly)
                                    <td class="text-center stk-num text-nowrap {{ $row['early_leave_minutes'] ? 'text-danger fw-bold' : '' }}" data-cell="early">
                                        {{ $row['early_leave_minutes'] ? $dur($row['early_leave_minutes']) : '—' }}
                                    </td>
                                @endif
                                @if($colOver)
                                    <td class="text-center stk-num text-nowrap {{ $row['overtime_minutes'] ? 'text-success fw-bold' : '' }}" data-cell="over">
                                        {{ $row['overtime_minutes'] ? $dur($row['overtime_minutes']) : '—' }}
                                    </td>
                                @endif
                                @if($colDeduction)
                                    {{--
                                        «رُحِّل للراتب» أو لا: الخصميةُ التي ولّدها هذا اليومُ تُقرأ هنا،
                                        فلا يُغلَق شهرٌ على تأخيرٍ ظنّ المديرُ أنه خُصم وهو لم يُخصم.
                                    --}}
                                    <td class="text-center" data-cell="deduction">
                                        @if($row['deduction'] && hasPermission('deductions.show'))
                                            <a href="{{ route('hr.deductions.show', $row['deduction']->id) }}"
                                               class="badge badge-light-danger"
                                               title="{{ $row['deduction']->deduction_type === 'absence' ? 'خصم غياب' : 'خصم تأخير' }}">
                                                خُصم {{ number_format((float) $row['deduction']->amount, 2) }}
                                            </a>
                                        @elseif($row['deduction'])
                                            <span class="badge badge-light-danger">خُصم {{ number_format((float) $row['deduction']->amount, 2) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                @endif
                                @if($colLocation)
                                    <td>
                                        @if($row['map_url'])
                                            <a href="{{ $row['map_url'] }}" target="_blank" rel="noopener"><i class="feather icon-map-pin"></i> {{ $row['location'] }}</a>
                                        @else
                                            {{ $row['location'] ?? '—' }}
                                        @endif
                                    </td>
                                @endif
                                <td class="text-center">
                                    <span class="tb-state tb-tile--{{ $row['state'] }}" data-cell="state">{{ $labels[$row['state']] ?? $row['state'] }}</span>
                                </td>
                            </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ─── الساعة: من ساعة الخادم لا من ساعة الجهاز ─── */
    let offsetMs = 0;
    const clock = document.getElementById('tb-clock');

    function tick() {
        const now = new Date(Date.now() + offsetMs);
        clock.textContent = now.toLocaleTimeString('ar-EG', {
            hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }
    tick();
    setInterval(tick, 1000);

    const body = document.getElementById('tb-body');
    if (!body) { return; }

    /* ─── البطاقاتُ مرشِّحات على صفوف الجدول ─── */
    let activeFilter = '';
    const counter = document.getElementById('tb-visible');

    document.querySelectorAll('.tb-tile').forEach(tile => {
        tile.addEventListener('click', () => {
            const next = tile.dataset.filter;
            activeFilter = (activeFilter === next) ? '' : next;

            document.querySelectorAll('.tb-tile').forEach(t =>
                t.classList.toggle('active', t.dataset.filter === activeFilter && activeFilter !== ''));

            applyFilter();
        });
    });

    /* ─── طيُّ مجموعةٍ وفتحُها ─── */
    const collapsed = new Set();

    body.querySelectorAll('tr.tb-group').forEach(head => {
        head.querySelector('.tb-group__toggle').addEventListener('click', () => {
            const id = head.dataset.group;
            collapsed.has(id) ? collapsed.delete(id) : collapsed.add(id);
            head.classList.toggle('tb-group--collapsed', collapsed.has(id));
            head.querySelector('.tb-group__toggle').setAttribute('aria-expanded', collapsed.has(id) ? 'false' : 'true');
            applyFilter();
        });
    });

    /*
       الترشيحُ يسري على صفوف الموظفين، ثمّ **يختفي رأسُ المجموعة التي لم
       يبقَ فيها أحد** — وإلّا بقيت رؤوسٌ فارغةٌ تُوهم أنّ تحتها صفوفاً.
    */
    function applyFilter() {
        let shown = 0;
        const perGroup = {};

        body.querySelectorAll('tr[data-employee]').forEach(tr => {
            // «متأخرون» ليست حالةً بل صفةٌ على حالة: الحاضرُ قد يكون متأخراً
            const match = ! activeFilter
                || (activeFilter === 'late' ? tr.dataset.late === '1' : tr.dataset.state === activeFilter);

            const group = tr.dataset.inGroup;
            if (match) { shown++; perGroup[group] = (perGroup[group] || 0) + 1; }

            tr.classList.toggle('tb-row--hidden', ! match || collapsed.has(group));
        });

        body.querySelectorAll('tr.tb-group').forEach(head => {
            head.classList.toggle('tb-row--hidden', (perGroup[head.dataset.group] || 0) === 0);
        });

        if (counter) { counter.textContent = shown; }
    }

    /* ─── تحديثٌ حيّ: اللوحة تُقرأ لتقرَّر بها، فلا تحتمل بياناتٍ بائتة ─── */
    const stateLabels = @json($labels);

    function setCell(tr, name, value) {
        const el = tr.querySelector('[data-cell="' + name + '"]');
        if (el) { el.textContent = value; }
    }

    /* لونُ الخليّة يتبع قيمتها: صفرٌ لا يبقى أحمرَ من قراءةٍ سابقة */
    function tone(tr, name, on, cls) {
        const el = tr.querySelector('[data-cell="' + name + '"]');
        if (! el) { return; }
        el.classList.toggle(cls, !! on);
        el.classList.toggle('fw-bold', !! on);
    }

    async function refresh() {
        try {
            const data = await (await fetch('{{ route("hr.team-board.feed") }}?all_levels={{ $includeIndirect ? 1 : 0 }}',
                { headers: { 'Accept': 'application/json' } })).json();
            if (! data.success) return;

            const serverNow = new Date(`{{ $date->toDateString() }}T${data.server_time}`);
            if (! isNaN(serverNow)) offsetMs = serverNow.getTime() - Date.now();

            document.querySelectorAll('[data-count]').forEach(el => {
                el.textContent = data.summary[el.dataset.count] ?? 0;
            });

            // تحديثُ الصفوف في مكانها — لا إعادةُ بناءٍ تُفقد المرشِّح وموضعَ التمرير
            data.members.forEach(m => {
                // مطابقةٌ بالمعرّف لا بالاسم: أسماءٌ متطابقةٌ واردة
                const tr = body.querySelector(`tr[data-employee="${m.id}"]`);
                if (! tr) return;

                tr.dataset.state = m.state;
                tr.dataset.late = m.late_minutes > 0 ? '1' : '0';

                setCell(tr, 'in', m.check_in || '—');
                setCell(tr, 'out', m.check_out || (m.check_in ? 'لم ينصرف' : '—'));
                setCell(tr, 'channel', m.channel || '');
                setCell(tr, 'work', m.working_label || '—');
                setCell(tr, 'late', m.late_label || '—');
                setCell(tr, 'early', m.early_label || '—');
                setCell(tr, 'over', m.over_label || '—');
                setCell(tr, 'break', m.break_label || '—');
                setCell(tr, 'state', stateLabels[m.state] || m.state);

                // «أثرُ الراتب» لا يُحدَّث هنا: الخصميةُ تُولَّد عند إقفال اليوم لا أثناءه
                tone(tr, 'early', m.early_leave_minutes > 0, 'text-danger');
                tone(tr, 'over', m.overtime_minutes > 0, 'text-success');
                tone(tr, 'break', m.on_break, 'text-warning');

                const badge = tr.querySelector('[data-cell="state"]');
                if (badge) { badge.className = 'tb-state tb-tile--' + m.state; }

                const lateCell = tr.querySelector('[data-cell="late"]');
                if (lateCell) { lateCell.classList.toggle('text-danger', m.late_minutes > 0); lateCell.classList.toggle('fw-bold', m.late_minutes > 0); }
            });

            // عدّادُ كلّ مجموعةٍ يُعاد حسابُه من التغذية نفسِها — وإلّا بقي رقمُ الصباح معلّقاً فوق فريقٍ تغيّر
            const tally = {};
            data.members.forEach(m => {
                const g = tally[m.manager_id] ??= { total: 0, present: 0, late: 0, absent: 0, pending: 0 };
                g.total++;
                if (m.state === 'present' || m.state === 'on_break') { g.present++; }
                if (m.state === 'absent' || m.state === 'short') { g.absent++; }
                if (m.state === 'pending') { g.pending++; }
                if (m.is_late) { g.late++; }
            });

            document.querySelectorAll('[data-group-counts]').forEach(box => {
                const g = tally[box.dataset.groupCounts] || { total: 0, present: 0, late: 0, absent: 0, pending: 0 };
                box.querySelectorAll('[data-gc]').forEach(el => { el.textContent = g[el.dataset.gc] ?? 0; });
            });

            applyFilter();
        } catch (e) { /* شبكةٌ متقطّعة: التحديثُ التالي يلحق */ }
    }

    refresh();
    setInterval(refresh, 60000);
});
</script>
@endsection
