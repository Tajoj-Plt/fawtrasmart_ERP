@extends('hr::master')

@section('title', 'سجلات الحضور')

@push('styles')
<style>
    .pn-reason { white-space: nowrap; }
    .pn-reason__text { display: block; font-size: .78rem; color: var(--app-danger, #b91c1c); margin-top: 2px; cursor: help; }
</style>
@endpush

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'سجلات الحضور',
        'icon' => 'activity',
        'sub' => 'الحركاتُ الخام كما وردت من جهاز الفرع أو جوال الميدان — لحظةً بلحظة',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'سجلات الحضور' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'daily'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{--
        لا رابطَ من هنا إلى «جلسات الحضور» القديمة: `AttendanceStampService`
        تكتب الختمَ وسجلَّ اليوم ولا تكتب جلسةً، فالشاشةُ تلك تُدخل صفوفاً
        لا يقرؤها حسابٌ ولا راتب. والمسارُ الوحيد لتغيير الأثر هو التصحيح.
    --}}
    <div class="alert alert-secondary small">
        سجلٌّ غيرُ قابلٍ للتعديل: كلُّ محاولةِ تسجيلٍ تُكتب هنا مرّةً واحدة، مقبولةً كانت أو مردودة.
        تعديلُ أثرِ الحضور يمرّ بـ@if(hasPermission('attendance_corrections.index'))<a href="{{ route('hr.attendance-corrections.index') }}"><strong>طلب تصحيح</strong></a>@else <strong>طلب تصحيح</strong> @endif
        فقط، ويبقى الختمُ الأصلي كما وقع. والأثرُ المحسوب لليوم يُقرأ في
        @if(hasPermission('attendance_days.index'))<a href="{{ route('hr.attendance-log.index') }}">أيام الحضور</a>@else أيام الحضور @endif.
    </div>

    @php
        $hasFilters = collect($filters ?? [])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

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
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="pn_employee_id">الموظف</label>
                            <select id="pn_employee_id" name="employee_id" class="form-control hr-s2">
                                <option value="">الكل</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="pn_date_from">من تاريخ</label>
                            <input type="date" id="pn_date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="pn_date_to">إلى تاريخ</label>
                            <input type="date" id="pn_date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="pn_result">النتيجة</label>
                            <select id="pn_result" name="result" class="form-control">
                                <option value="">الكل</option>
                                <option value="accepted" @selected(($filters['result'] ?? '') === 'accepted')>مقبول</option>
                                <option value="rejected" @selected(($filters['result'] ?? '') === 'rejected')>مردود</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="pn_channel">المدخل</label>
                            <select id="pn_channel" name="channel" class="form-control">
                                <option value="">الكل</option>
                                <option value="kiosk" @selected(($filters['channel'] ?? '') === 'kiosk')>جهاز الفرع</option>
                                <option value="field" @selected(($filters['channel'] ?? '') === 'field')>الجوال الميداني</option>
                                <option value="manual" @selected(($filters['channel'] ?? '') === 'manual')>إدخال يدوي</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="pn_punch_type">النوع</label>
                            <select id="pn_punch_type" name="punch_type" class="form-control">
                                <option value="">الكل</option>
                                <option value="in" @selected(($filters['punch_type'] ?? '') === 'in')>حضور</option>
                                <option value="out" @selected(($filters['punch_type'] ?? '') === 'out')>انصراف</option>
                                <option value="rest_in" @selected(($filters['punch_type'] ?? '') === 'rest_in')>بدء استراحة</option>
                                <option value="rest_out" @selected(($filters['punch_type'] ?? '') === 'rest_out')>نهاية استراحة</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="pn_kiosk_id">الجهاز</label>
                            <select id="pn_kiosk_id" name="kiosk_id" class="form-control">
                                <option value="">الكل</option>
                                @foreach($kiosks as $kiosk)
                                    <option value="{{ $kiosk->id }}" @selected((string) ($filters['kiosk_id'] ?? '') === (string) $kiosk->id)>
                                        {{ $kiosk->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="pn_stage">سبب الردّ</label>
                            <select id="pn_stage" name="stage" class="form-control">
                                <option value="">الكل</option>
                                <option value="employee" @selected(($filters['stage'] ?? '') === 'employee')>ملف الموظف</option>
                                <option value="location" @selected(($filters['stage'] ?? '') === 'location')>الموقع الجغرافي</option>
                                <option value="day" @selected(($filters['stage'] ?? '') === 'day')>سجل اليوم</option>
                                <option value="photos" @selected(($filters['stage'] ?? '') === 'photos')>صور التسجيل</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="pn_adjustment_code">التعديل</label>
                            <select id="pn_adjustment_code" name="adjustment_code" class="form-control">
                                <option value="">الكل</option>
                                <option value="capped_to_shift_end" @selected(($filters['adjustment_code'] ?? '') === 'capped_to_shift_end')>
                                    قُصَّ إلى نهاية الوردية
                                </option>
                            </select>
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
        @if ($punches->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة الأختام
                    <span class="stk-count">{{ $punches->total() }}</span>
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>الموظف</th>
                            <th>الوقت</th>
                            <th class="text-center">النوع</th>
                            <th>المدخل / الوسيلة</th>
                            <th>الجهاز</th>
                            <th>الموقع</th>
                            <th>الوردية</th>
                            <th>النتيجة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($punches as $index => $punch)
                            <tr>
                                <td class="stk-num text-muted">{{ $punches->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">{{ $punch->employee?->full_name ?? '—' }}</span>
                                            <span class="stk-media__sub">{{ $punch->punched_at->format('Y-m-d') }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="stk-num text-nowrap">
                                    {{ $punch->punched_at->format('H:i:s') }}
                                    @if($punch->adjustment_code === 'capped_to_shift_end')
                                        {{-- الوقتان معاً: ما قرأته الساعة، وما اعتُمد --}}
                                        <div class="small text-muted" title="وقع خارج نافذة الوردية فقُصّ إلى نهايتها">
                                            <i class="feather icon-scissors"></i>
                                            اعتُمد {{ substr($punch->applied_time, 0, 5) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php
                                        $punchTypeLabels = ['in' => 'حضور', 'out' => 'انصراف', 'rest_in' => 'بدء استراحة', 'rest_out' => 'نهاية استراحة'];
                                    @endphp
                                    <span class="stk-state {{ in_array($punch->punch_type, ['rest_in', 'rest_out'], true) ? 'stk-state--warn' : 'stk-state--neutral' }}">{{ $punchTypeLabels[$punch->punch_type] ?? $punch->punch_type }}</span>
                                </td>
                                <td class="text-nowrap">{{ $punch->channelLabel() }} · {{ $punch->methodLabel() }}</td>
                                <td>{{ $punch->kiosk?->name ?? '—' }}</td>
                                <td>
                                    @if($punch->mapsUrl())
                                        <a href="{{ $punch->mapsUrl() }}" target="_blank" rel="noopener" title="فتح على الخريطة">
                                            {{ $punch->locationDisplay() }}
                                        </a>
                                    @else
                                        {{ $punch->locationDisplay() }}
                                    @endif
                                </td>
                                <td class="stk-num text-nowrap">
                                    {{-- الحضورُ يُقاس بموعد الدخول وتأخيره، والانصرافُ بموعد الخروج وتبكيره —
                                         كان الانصرافُ يعرض موعدَ الدخول و«+480 د» من حقلٍ لا معنى له فيه --}}
                                    @if(in_array($punch->punch_type, ['rest_in', 'rest_out'], true))
                                        —
                                    @elseif($punch->punch_type === \App\Models\AttendancePunch::TYPE_OUT)
                                        {{ $punch->expected_check_out ? substr($punch->expected_check_out, 0, 5) : '—' }}
                                        @if($punch->early_leave_minutes)
                                            <span class="stk-state stk-state--warn">قبل {{ $punch->early_leave_minutes }} د</span>
                                        @endif
                                    @else
                                        {{ $punch->expected_check_in ? substr($punch->expected_check_in, 0, 5) : '—' }}
                                        @if($punch->late_minutes)
                                            <span class="stk-state stk-state--warn">+{{ $punch->late_minutes }} د</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="pn-reason">
                                    @if($punch->result === 'accepted')
                                        <span class="stk-state stk-state--on">مقبول</span>
                                    @else
                                        <span class="stk-state stk-state--off">مردود</span>
                                        {{-- سطرٌ قصير؛ النصُّ الكامل تلميحٌ عند المرور — كان يمتدّ أسطراً ويُكبّر الصف --}}
                                        <span class="pn-reason__text" @if($punch->rejection_reason) title="{{ $punch->rejection_reason }}" @endif>
                                            {{ $punch->rejectionTitle() }}
                                            @if($punch->rejection_reason)<i class="feather icon-info"></i>@endif
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($punches->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $punches->firstItem() }}–{{ $punches->lastItem() }} من {{ $punches->total() }}</p>
                    {{ $punches->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                    <h5 class="stk-empty__title">لا توجد أختام</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أيّ ختم — جرّب معايير أوسع
                        @else
                            تظهر هنا كلُّ محاولة تسجيل حضور أو انصراف من جهاز الفرع أو الجوال الميداني.
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ url()->current() }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
