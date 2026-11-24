@extends('hr::master')

@section('title', 'عرض قائمة العطلات')

@section('page-actions')
    @permission('holidays.edit')
        <a href="{{ route('holiday_lists.edit', $holiday_list->id) }}" class="btn btn-primary">
            <i class="fa fa-edit"></i> <span>تعديل</span>
        </a>
    @endpermission
    @if (canAny(['holidays.edit', 'holidays.index', 'holidays.destroy']))
        @permission('holidays.edit')
            <a href="{{ route('holiday_lists.holyday_employees', $holiday_list->id) }}" class="btn btn-primary">
                <i class="feather icon-link"></i> <span class="d-none d-md-inline">على من تسري (قسم / مسمى / موظف)</span>
            </a>
        @endpermission
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa fa-ellipsis-h"></i>
                <span>المزيد</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @permission('holidays.edit')
                <li>
                    <a class="dropdown-item" href="{{ route('holiday_lists.holyday_employees', $holiday_list->id) }}">
                        <i class="fa fa-users me-2 text-success"></i>ربط بالموظفين
                    </a>
                </li>
                @endpermission
                @permission('holidays.index')
                <li>
                    <a class="dropdown-item" href="{{ route('holiday_lists.index') }}">
                        <i class="fa fa-list me-2 text-primary"></i>العودة للقائمة
                    </a>
                </li>
                @endpermission
                @permission('holidays.destroy')
                @if (canAny(['holidays.edit', 'holidays.index']))
                <li><hr class="dropdown-divider"></li>
                @endif
                <li>
                    <form method="POST" action="{{ route('holiday_lists.delete', $holiday_list->id) }}"
                          onsubmit="return confirm('حذف القائمة كاملة؟');">
                        @csrf @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fa fa-trash me-2"></i>حذف
                        </button>
                    </form>
                </li>
                @endpermission
            </ul>
        </div>
    @endif
@endsection

@section('content')
    @include('hr::components.table-menu')
<style>
    .hs-type-pill { padding:3px 10px; border-radius:10px; font-size:.72rem; font-weight:600; display:inline-flex; align-items:center; gap:4px; }
    .hs-type-pill.once    { background: var(--app-info-soft, #dbeafe); color: var(--app-info, #1e3a8a); }
    .hs-type-pill.weekly  { background: var(--app-success-soft, #d1fae5); color: var(--app-success, #065f46); }
    .hs-type-pill.monthly { background: var(--app-warning-soft, #fef3c7); color: var(--app-warning, #92400e); }
    .hs-type-pill.yearly  { background: var(--app-danger-soft, #fecaca); color: var(--app-danger, #991b1b); }

    .hs-emp-chip { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; background: var(--app-primary-soft, #eef2ff); color: var(--app-primary, #4338ca); border-radius:14px; font-size:.78rem; font-weight:600; margin:3px; }
    .hs-emp-chip .avatar { width:22px; height:22px; background:var(--app-primary, #6366f1); color:#fff; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; }

    .hs-upcoming-row { display:flex; align-items:center; gap:12px; padding:10px 18px; border-bottom: 1px solid var(--app-border, #f3f4f6); }
    .hs-upcoming-row:last-child { border-bottom:none; }
    .hs-date-box { width:56px; text-align:center; padding:6px; background: var(--app-primary-soft, #eef2ff); border-radius:6px; flex-shrink:0; }
    .hs-date-box .day { font-size:1.2rem; font-weight:700; color: var(--app-primary, #4338ca); line-height:1; }
    .hs-date-box .month { font-size:.7rem; color: var(--app-muted, #6b7280); text-transform:uppercase; margin-top:2px; }
    .hs-upcoming-info { flex:1; }
    .hs-upcoming-info .name { font-weight:600; font-size:.9rem; }
    .hs-upcoming-info .meta, .hs-days-from { font-size:.78rem; color: var(--app-muted, #6b7280); }

    .hs-log-item { padding:8px 0; border-bottom: 1px dashed var(--app-border, #e5e7eb); font-size:.875rem; }
    .hs-log-item:last-child { border-bottom:none; }
    .hs-log-item .when { color: var(--app-muted, #9ca3af); font-size:.75rem; }
</style>

<div class="content-body">
    @php
        $holidays = $holiday_list->holidays;
        $counts = [
            'once'    => $holidays->where('recurrence_type', 'once')->count(),
            'weekly'  => $holidays->where('recurrence_type', 'weekly')->count(),
            'monthly' => $holidays->where('recurrence_type', 'monthly')->count(),
            'yearly'  => $holidays->where('recurrence_type', 'yearly')->count(),
        ];
        $typeLabels = [
            'once'    => ['ثابتة',   'once',    'fa-flag'],
            'weekly'  => ['أسبوعية', 'weekly',  'fa-redo'],
            'monthly' => ['شهرية',   'monthly', 'fa-calendar-alt'],
            'yearly'  => ['سنوية',   'yearly',  'fa-star'],
        ];
        $affectedCount = ($affectedEmployees ?? collect())->count();
        $hasLogs = isset($logs) && count($logs) > 0;
    @endphp

    @include('stock::components.page-head', [
        'title' => $holiday_list->name,
        'icon' => 'calendar',
        'crumbs' => ['الرئيسية' => url('/'), 'قوائم العطلات' => route('holiday_lists.index'), $holiday_list->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-calendar"></i></span>
                    <h4 class="mb-25">{{ $holiday_list->name }}</h4>
                    <p class="stk-help mt-0 mb-0">قائمة عطلات رقم #{{ $holiday_list->id }} · أُنشئت {{ optional($holiday_list->created_at)->format('Y-m-d') }}</p>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">إجمالي العطلات</span>
                        <span class="stk-metric__value">{{ $holidays->count() }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">الموظفون المتأثرون</span>
                        <span class="stk-metric__value">{{ $affectedCount }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt><i class="feather icon-flag"></i> ثابتة</dt>
                            <dd class="stk-num">{{ $counts['once'] }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt><i class="feather icon-refresh-cw"></i> أسبوعية</dt>
                            <dd class="stk-num">{{ $counts['weekly'] }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt><i class="feather icon-calendar"></i> شهرية</dt>
                            <dd class="stk-num">{{ $counts['monthly'] }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt><i class="feather icon-star"></i> سنوية</dt>
                            <dd class="stk-num">{{ $counts['yearly'] }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>أُنشئت / آخر تعديل</dt>
                            <dd class="stk-num">{{ optional($holiday_list->created_at)->format('Y-m-d H:i') ?? '—' }} / {{ optional($holiday_list->updated_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- ============ التواريخ القادمة المتأثرة ============ --}}
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-calendar"></i> العطل القادمة (60 يوم)</h4></div>
                @if(!empty($upcomingDates))
                    <div class="pb-50">
                        @foreach($upcomingDates as $item)
                            @php
                                $dt = $item['date'];
                                $diff = (int) now()->startOfDay()->diffInDays($dt->startOfDay(), false);
                                $diffText = $diff === 0 ? 'اليوم' : ($diff === 1 ? 'غداً' : 'بعد ' . $diff . ' يوم');
                            @endphp
                            <div class="hs-upcoming-row">
                                <div class="hs-date-box">
                                    <div class="day">{{ $dt->day }}</div>
                                    <div class="month">{{ $dt->format('M') }}</div>
                                </div>
                                <div class="hs-upcoming-info">
                                    <div class="name">{{ $item['holiday']->named }}</div>
                                    <div class="meta">
                                        <i class="feather icon-calendar"></i> {{ $dt->translatedFormat('l، Y') }}
                                    </div>
                                </div>
                                <div class="hs-days-from">{{ $diffText }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="card-body">
                        <p class="stk-help mb-0">لا توجد عطلات في الـ 60 يوماً القادمة.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ══════════ العطلات والموظفون والسجل ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tab-holidays" role="tab" aria-controls="tab-holidays" aria-selected="true">
                                <i class="feather icon-list"></i> العطلات المُدرَجة
                                <span class="stk-count">{{ $holidays->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-employees" role="tab" aria-controls="tab-employees" aria-selected="false">
                                <i class="feather icon-users"></i> الموظفون المتأثرون
                                <span class="stk-count">{{ $affectedCount }}</span>
                            </a>
                        </li>
                        @if ($hasLogs)
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-activity" role="tab" aria-controls="tab-activity" aria-selected="false">
                                    <i class="feather icon-rotate-ccw"></i> سجل العمليات
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane active" id="tab-holidays" role="tabpanel">
                        @if($holidays->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>اسم العطلة</th>
                                            <th>النوع</th>
                                            <th>التفاصيل</th>
                                            <th>مدفوعة؟</th>
                                            <th class="text-center">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($holidays as $h)
                                            @php $t = $typeLabels[$h->recurrence_type] ?? ['?', 'once', 'fa-question']; @endphp
                                            <tr>
                                                <td class="stk-num text-muted">#{{ $h->id }}</td>
                                                <td><strong>{{ $h->named }}</strong></td>
                                                <td><span class="hs-type-pill {{ $t[1] }}"><i class="fa {{ $t[2] }}"></i> {{ $t[0] }}</span></td>
                                                <td>{{ $h->recurrence_label ?? '—' }}</td>
                                                <td>
                                                    @if($h->is_paid)
                                                        <span class="text-success"><i class="feather icon-check-circle"></i> نعم</span>
                                                    @else
                                                        <span class="text-muted"><i class="feather icon-x-circle"></i> لا</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span class="stk-state stk-state--{{ $h->is_active ? 'on' : 'off' }}">{{ $h->is_active ? 'نشطة' : 'متوقفة' }}</span>
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
                                    <h5 class="stk-empty__title">لا توجد عطلات في هذه القائمة</h5>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- ============ الموظفون المربوطون ============ --}}
                    <div class="tab-pane" id="tab-employees" role="tabpanel">
                        <div class="card-body">
                            @if($affectedCount > 0)
                                @permission('holidays.edit')
                                    <div class="text-end mb-1">
                                        <a href="{{ route('holiday_lists.holyday_employees', $holiday_list->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="feather icon-plus-circle"></i> ربط/تعديل
                                        </a>
                                    </div>
                                @endpermission
                                <div>
                                    @foreach($affectedEmployees as $emp)
                                        @php $name = trim(($emp->first_name ?? '') . ' ' . ($emp->middle_name ?? '')); @endphp
                                        <span class="hs-emp-chip">
                                            <span class="avatar">{{ mb_substr($name, 0, 1) ?: '?' }}</span>
                                            {{ $name }}
                                            <small style="opacity:.7;">#{{ $emp->id }}</small>
                                        </span>
                                    @endforeach
                                </div>

                                @if(!empty($customizations) && $customizations->where('use_rules', 1)->count() > 0)
                                    <p class="stk-help mt-2 mb-0">
                                        <i class="feather icon-info"></i>
                                        هذه القائمة مربوطة بقواعد (فرع/قسم/مسمى). أي موظف جديد يطابق القواعد سيتأثر تلقائياً.
                                    </p>
                                @endif
                            @else
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-users"></i></div>
                                    <h5 class="stk-empty__title">لا يوجد موظفون مربوطون بهذه القائمة</h5>
                                    @permission('holidays.edit')
                                        <a href="{{ route('holiday_lists.holyday_employees', $holiday_list->id) }}" class="btn btn-primary btn-sm mt-1">
                                            <i class="feather icon-plus"></i> ربط موظفين الآن
                                        </a>
                                    @endpermission
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($hasLogs)
                        <div class="tab-pane" id="tab-activity" role="tabpanel">
                            <div class="card-body">
                                @foreach($logs as $date => $dayLogs)
                                    <div class="mb-2">
                                        <h6 class="text-muted small mt-1"><i class="feather icon-calendar"></i> {{ $date }}</h6>
                                        @foreach($dayLogs as $log)
                                            <div class="hs-log-item">
                                                <div>{{ $log->description }}</div>
                                                <div class="when">{{ optional($log->user)->name ?? '—' }} • {{ $log->created_at->format('H:i') }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
