@extends('hr::master')

@section('title', 'سجل جلسات الحضور')

@push('styles')
<style>
    .dm-section { margin-bottom:24px; }
    .dm-section h6 { font-weight:700; font-size:.95rem; margin-bottom:10px; }
    .dm-help { font-size:.78rem; color: var(--app-muted,#6b7280); }

    .dm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(270px,1fr)); gap:12px; }
    .dm-card { background: var(--app-surface,#fff); border:1px solid var(--app-border,#e5e7eb);
        border-right:4px solid var(--app-muted,#9ca3af); border-radius:10px; padding:14px 16px; }
    .dm-card--live     { border-right-color: var(--app-success,#16a34a); }
    .dm-card--silent   { border-right-color: var(--app-danger,#dc2626); }
    .dm-card--idle     { border-right-color: var(--app-warning,#d97706); }
    .dm-card--disabled { border-right-color: var(--app-muted,#9ca3af); opacity:.7; }
    .dm-card--worked   { border-right-color: var(--app-info,#0ea5e9); }

    .dm-card__head { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; }
    .dm-card__name { font-weight:700; font-size:.95rem; }
    .dm-card__sub { font-size:.72rem; color: var(--app-muted,#6b7280); }
    .dm-card__row { display:flex; justify-content:space-between; font-size:.79rem; padding:2px 0; }
    .dm-card__row span { color: var(--app-muted,#6b7280); }
    .dm-card__row strong { font-variant-numeric: tabular-nums; }

    .dm-led { width:9px; height:9px; border-radius:50%; display:inline-block; margin-left:5px; }
    .dm-led--live { background: var(--app-success,#16a34a); animation: dm-pulse 2s ease-in-out infinite; }
    .dm-led--silent { background: var(--app-danger,#dc2626); }
    .dm-led--idle { background: var(--app-warning,#d97706); }
    .dm-led--disabled { background: var(--app-muted,#9ca3af); }
    .dm-led--worked { background: var(--app-info,#0ea5e9); }
    @keyframes dm-pulse { 0%,100% { opacity:1; } 50% { opacity:.25; } }

    .dm-state { font-size:.68rem; padding:2px 9px; border-radius:999px; background: var(--app-body-bg,#f3f4f6); white-space:nowrap; }
    .dm-table { font-size:.81rem; }
    .dm-table td, .dm-table th { vertical-align:middle; }
    .dm-warn { background: color-mix(in srgb, var(--app-danger,#dc2626) 6%, transparent); }
</style>
@endpush

@section('content')
@include('stock::components.page-head', [
    'title' => 'سجل جلسات الحضور',
    'crumbs' => ['الحضور' => null, 'جلسات الأجهزة' => null],
])

@include('hr::attendance._partials.section-tabs', ['group' => 'devices'])

@php
    $stateLabels = [
        'live' => 'يعمل الآن', 'silent' => 'صامت', 'idle' => 'لم يُستخدم',
        'disabled' => 'موقوف', 'worked' => 'عمل في هذا اليوم',
    ];
@endphp

<div class="content-body">

    <div class="alert alert-secondary dm-help">
        سؤالُ هذه الشاشة ليس «من حضر؟» بل <strong>«هل النظام يعمل؟»</strong>.
        جهازُ فرعٍ ينطفئ الساعة العاشرة يُنتج يوماً يبدو فيه نصفُ الفرع غائباً،
        ولا يُكتشف ذلك إلا عند صرف الرواتب.
    </div>

    <form method="GET" class="d-flex align-items-center mb-3" style="gap:10px;">
        <label class="dm-help m-0">التاريخ</label>
        <input type="date" name="date" class="form-control form-control-sm" style="max-width:180px;"
               value="{{ $date->toDateString() }}" onchange="this.form.submit()">
        <span class="dm-help">
            أختام اليوم: <strong>{{ $totals['accepted'] }}</strong> مقبول ·
            <strong>{{ $totals['rejected'] }}</strong> مردود
        </span>
    </form>

    {{-- ═══ أجهزة الفروع ═══ --}}
    <div class="dm-section">
        <h6><i class="feather icon-monitor"></i> أجهزة الفروع ({{ $kiosks->count() }})</h6>

        @if($kiosks->isEmpty())
            <div class="dm-card">
                <p class="m-0 dm-help">
                    لا توجد أجهزة مُسجَّلة. تُنشأ من
                    @if(hasPermission('attendance_places.index'))<a href="{{ route('attendance.settings.kiosks.index') }}">أجهزة التسجيل</a>@else «أجهزة التسجيل» @endif،
                    ثم يُربط الجهاز برمز الربط مرة واحدة.
                </p>
            </div>
        @else
            <div class="dm-grid">
                @foreach($kiosks as $row)
                    <div class="dm-card dm-card--{{ $row['state'] }}">
                        <div class="dm-card__head">
                            <div>
                                <div class="dm-card__name">
                                    <span class="dm-led dm-led--{{ $row['state'] }}"></span>
                                    {{ $row['kiosk']->name }}
                                </div>
                                <div class="dm-card__sub">
                                    {{ $row['kiosk']->branch->name ?? 'بلا فرع' }}
                                    @if($row['kiosk']->geofence) · {{ $row['kiosk']->geofence->name }} @endif
                                </div>
                            </div>
                            <span class="dm-state">{{ $stateLabels[$row['state']] ?? $row['state'] }}</span>
                        </div>

                        <div class="mt-2">
                            <div class="dm-card__row">
                                <span>آخر إشارة</span>
                                <strong>
                                    @if($row['last_signal'])
                                        {{ \Carbon\Carbon::parse($row['last_signal'])->format('H:i') }}
                                        @if($row['silent_minutes'] !== null && $row['state'] === 'silent')
                                            <span class="text-danger">(منذ {{ $row['silent_minutes'] }} د)</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </strong>
                            </div>
                            <div class="dm-card__row"><span>أختام مقبولة</span><strong>{{ $row['accepted'] }}</strong></div>
                            <div class="dm-card__row"><span>أختام مردودة</span><strong>{{ $row['rejected'] }}</strong></div>
                        </div>

                        @if($row['state'] === 'silent')
                            <div class="dm-help mt-2 text-danger">
                                لم تصل إشارة منذ أكثر من ٤٥ دقيقة — تحقّق من الجهاز أو الشبكة.
                            </div>
                        @elseif($row['state'] === 'idle' && $date->isToday())
                            <div class="dm-help mt-2">لم يُفتح اليوم بعد.</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══ جلسات الجوال الميداني ═══ --}}
    <div class="dm-section">
        <h6><i class="feather icon-smartphone"></i> جلسات الجوال الميداني ({{ $fieldSessions->count() }})</h6>

        @if($fieldSessions->isEmpty())
            <p class="dm-help">لا تسجيلات ميدانية في هذا اليوم.</p>
        @else
            <div class="table-responsive">
                <table class="table dm-table">
                    <thead>
                        <tr><th>الموظف</th><th>آخر نشاط</th><th>الموقع</th><th>مقبول</th><th>مردود</th></tr>
                    </thead>
                    <tbody>
                        @foreach($fieldSessions as $session)
                            <tr>
                                <td>{{ $session['employee']?->full_name ?? '—' }}</td>
                                <td>{{ $session['last']->punched_at->format('H:i:s') }}</td>
                                <td>
                                    @if($session['last']->latitude !== null)
                                        {{ $session['last']->latitude }}, {{ $session['last']->longitude }}
                                        @if($session['last']->accuracy_meters) (±{{ $session['last']->accuracy_meters }} م) @endif
                                    @else
                                        <span class="dm-help">بلا إحداثيات</span>
                                    @endif
                                </td>
                                <td>{{ $session['accepted'] }}</td>
                                <td class="{{ $session['rejected'] ? 'text-danger' : '' }}">{{ $session['rejected'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ═══ محاولات من مصادر غير معرّفة ═══ --}}
    <div class="dm-section">
        <h6><i class="feather icon-alert-triangle"></i> محاولات غير معرّفة ({{ $unidentified->count() }})</h6>
        <p class="dm-help">
            بطاقات مُسحت على جهاز الفرع وليست لأي موظف. بطاقة تتكرر قد تكون بطاقة لم تُفعَّل — أو محاولة تلاعب.
            (المسح من جهاز غير مربوط بالفرع يُرفض فوراً ولا يُسجَّل هنا، كي لا يُملأ السجل من أي متصفح.)
        </p>

        @if($unidentified->isEmpty())
            <p class="dm-help">لا شيء — كل الأختام منسوبة إلى مصدر معروف.</p>
        @else
            <div class="table-responsive">
                <table class="table dm-table">
                    <thead>
                        <tr><th>الوقت</th><th>المدخل</th><th>الباركود المقروء</th><th>عنوان الشبكة</th><th>النتيجة</th></tr>
                    </thead>
                    <tbody>
                        @foreach($unidentified as $punch)
                            <tr class="dm-warn">
                                <td>{{ $punch->punched_at->format('H:i:s') }}</td>
                                <td>{{ $punch->channelLabel() }}{{ $punch->kiosk_id ? '' : ' — بلا جهاز مقترن' }}</td>
                                <td>{{ $punch->raw_barcode ?? '—' }}</td>
                                <td>{{ $punch->ip_address ?? '—' }}</td>
                                <td>{{ $punch->rejection_reason ?? 'مقبول' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- حُذفت شاشةُ «جلسات الموظفين»: طبقةٌ وسطى لم تعد تُكتب، والتفصيلُ في سجل الأختام --}}
    <p class="dm-help">
        التفاصيل الكاملة لكل ختم — المقبول والمردود — في
        @if(hasPermission('attendance_records.index'))<a href="{{ route('hr.attendance-punches.index') }}">سجلات الحضور</a>@else سجلات الحضور @endif،
        وأثرُ اليوم المحسوب في
        @if(hasPermission('attendance_days.index'))<a href="{{ route('hr.attendance-log.index') }}">أيام الحضور</a>@else أيام الحضور @endif.
    </p>
</div>
@endsection
