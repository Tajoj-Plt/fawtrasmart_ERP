@extends('hr::master')

@section('title', 'عرض العقد')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
@endpush

@php
    $employee = $contract->employee;
    $end = $contract->end_date;
    $state = ! $end || $end->gte(now()->startOfDay())
        ? ($end && $end->lte(now()->addDays(30)) ? ['warn', 'ينتهي خلال 30 يوماً'] : ['on', 'ساري'])
        : ['off', 'منتهٍ'];
    $fmt = fn ($v) => \App\Support\CurrencySettings::format($v);
    $date = fn ($v) => $v ? $v->format('Y-m-d') : '—';
@endphp

@section('page-actions')
    {{-- الترتيب الموحّد لشاشات العرض: [اعتماد] [تعديل] [طباعة ▾] [المزيد ▾ … حذف] --}}
    @if ($can['approve'])
        <form action="{{ route('Contracts.approve', $contract->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success"><i class="fa fa-check-circle"></i> اعتماد العقد</button>
        </form>
    @endif
    @if ($can['edit'])
        <a href="{{ route('Contracts.edit', $contract->id) }}" class="btn btn-primary">
            <i class="fa fa-edit"></i> تعديل
        </a>
    @endif
    @if (! $can['print'])
        {{-- لا صلاحية طباعة: لا زرّ --}}
    @elseif ($printTemplates->count() > 1)
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa fa-print"></i> طباعة
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">قوالب «عقد عمل»</h6></li>
                @foreach ($printTemplates as $tpl)
                    <li>
                        <a class="dropdown-item" target="_blank" href="{{ route('Contracts.printTemplate', ['id' => $contract->id, 'template' => $tpl->id]) }}">
                            <i class="fa fa-file-alt me-2 text-info"></i>{{ $tpl->name }} @if ($tpl->is_default)<span class="badge badge-light-primary">افتراضي</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif ($printTemplates->isNotEmpty())
        <a href="{{ route('Contracts.printTemplate', $contract->id) }}" target="_blank" class="btn btn-outline-primary">
            <i class="fa fa-print"></i> طباعة
        </a>
    @endif
    @if ($can['destroy'])
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE">
                        <i class="fa fa-trash me-2"></i>حذف العقد
                    </a>
                </li>
            </ul>
        </div>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'عقد '.($contract->code ?: '#'.$contract->id),
        'icon' => 'file-text',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'العقود' => route('Contracts.index'),
            ($contract->code ?: '#'.$contract->id) => null,
        ],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @if ($can['print'] && $printTemplates->isEmpty())
        <div class="alert alert-warning" role="alert">
            <i class="feather icon-alert-triangle"></i>
            لا يوجد قالب طباعة فعّال لنوع «عقد عمل» — أنشئه أو فعّله من الإعدادات ← قوالب المستندات لتظهر الطباعة.
        </div>
    @endif

    {{-- ══════════ بطاقة الموظف ══════════ --}}
    <div class="card">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap" style="gap: .75rem;">
            <div class="stk-media">
                <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                <span class="stk-media__body">
                    <span class="stk-media__title">{{ $employee->full_name ?? '—' }}</span>
                    <span class="stk-media__sub">
                        {{ $contract->jobTitle->name ?? 'بلا مسمّى' }}
                        @if ($employee?->department) · {{ $employee->department->name }} @endif
                        @if ($employee?->branch) · {{ $employee->branch->name }} @endif
                    </span>
                </span>
            </div>
            <div class="d-flex align-items-center flex-wrap" style="gap: .5rem;">
                @if ($employee)
                    @php $st = $employee->employmentStatus(); @endphp
                    <span class="badge badge-{{ $st->badge() }}">{{ $st->label() }}</span>
                @endif
                <span class="stk-state stk-state--{{ $state[0] }}">{{ $state[1] }}</span>
                @if ($contract->isApproved())
                    <span class="badge badge-light-success" title="{{ $contract->approved_at ? 'اعتُمد '.$contract->approved_at->format('Y-m-d H:i').($contract->approver ? ' — '.$contract->approver->name : '') : '' }}">
                        <i class="feather icon-check"></i> معتمد
                    </span>
                @else
                    <span class="badge badge-light-warning"><i class="feather icon-clock"></i> مسودّة — بانتظار الاعتماد</span>
                @endif
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#details" role="tab"><i class="feather icon-file-text"></i> التفاصيل</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#activity" role="tab"><i class="feather icon-activity"></i> سجل النشاطات</a>
        </li>
    </ul>

    <div class="tab-content pt-1">
        <div class="tab-pane active" id="details" role="tabpanel">
            <div class="row">
                <div class="col-lg-6">
                    <div class="card stk-card--tight">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> معلومات العقد</h4></div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>رقم العقد</dt><dd dir="ltr" class="text-right">{{ $contract->code ?: '#'.$contract->id }}</dd></div>
                                <div class="stk-def__row"><dt>المسمى الوظيفي</dt><dd>{{ $contract->jobTitle->name ?? '—' }}</dd></div>
                                <div class="stk-def__row"><dt>المستوى الوظيفي</dt><dd>{{ $contract->jobLevel->name ?? '—' }}</dd></div>
                                <div class="stk-def__row"><dt>العقد الأساسي</dt>
                                    <dd>
                                        @if ($contract->parentContract)
                                            <a href="{{ route('Contracts.show', $contract->parent_contract_id) }}">{{ $contract->parentContract->code ?: '#'.$contract->parent_contract_id }}</a>
                                        @else — @endif
                                    </dd>
                                </div>
                                <div class="stk-def__row"><dt>الوصف</dt><dd>{{ $contract->description ?: '—' }}</dd></div>
                                <div class="stk-def__row"><dt>أضيف بواسطة</dt><dd>{{ $contract->creator->name ?? '—' }}</dd></div>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card stk-card--tight">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-calendar"></i> المدة والتواريخ</h4></div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>المدة</dt><dd>{{ $contract->getDurationText() }}</dd></div>
                                <div class="stk-def__row"><dt>تاريخ البداية</dt><dd dir="ltr" class="text-right">{{ $date($contract->start_date) }}</dd></div>
                                <div class="stk-def__row"><dt>تاريخ الانتهاء</dt><dd dir="ltr" class="text-right">{{ $end ? $end->format('Y-m-d') : 'مفتوح' }}</dd></div>
                                <div class="stk-def__row"><dt>تاريخ الالتحاق</dt><dd dir="ltr" class="text-right">{{ $date($contract->join_date) }}</dd></div>
                                <div class="stk-def__row"><dt>نهاية فترة التجربة</dt><dd dir="ltr" class="text-right">{{ $date($contract->probation_end_date) }}</dd></div>
                                <div class="stk-def__row"><dt>تاريخ توقيع العقد</dt><dd dir="ltr" class="text-right">{{ $date($contract->contract_date) }}</dd></div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ بيانات الراتب ══════════ --}}
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-dollar-sign"></i> بيانات الراتب</h6>
                    <span class="text-muted small">
                        دورة القبض: {{ $contract->receiving_cycle ? $contract->receiving_cycle_text : '—' }}
                        · العملة: {{ $contract->currency ?: \App\Support\CurrencySettings::code() }}
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>بند الراتب</th>
                                <th>النوع</th>
                                <th>الصيغة الحسابية</th>
                                <th class="text-end">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>الراتب الأساسي</strong> <span class="text-muted small" dir="ltr">basic</span></td>
                                <td><span class="stk-state stk-state--neutral">أساسي</span></td>
                                <td class="text-muted">—</td>
                                <td class="text-end">{{ $contract->amount !== null ? $fmt($contract->amount) : '—' }}</td>
                            </tr>
                            @foreach ($additionItems as $addition)
                                <tr>
                                    <td>{{ $addition->name }}</td>
                                    <td><span class="stk-state stk-state--on">مستحق</span></td>
                                    <td dir="ltr" class="text-right text-muted">{{ $addition->calculation_formula ?: '—' }}</td>
                                    <td class="text-end">{{ $fmt($addition->amount) }}</td>
                                </tr>
                            @endforeach
                            @foreach ($deductionItems as $deductionItem)
                                <tr>
                                    <td>{{ $deductionItem->name }}</td>
                                    <td><span class="stk-state stk-state--warn">مستقطع</span></td>
                                    <td dir="ltr" class="text-right text-muted">{{ $deductionItem->calculation_formula ?: '—' }}</td>
                                    <td class="text-end text-danger">−{{ $fmt($deductionItem->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="stk-total-row"><td colspan="3">إجمالي المستحقات</td><td class="text-end">{{ $fmt($totals['additions']) }}</td></tr>
                            <tr class="stk-total-row"><td colspan="3">مبلغ العقد (الأساسي + المستحقات)</td><td class="text-end">{{ $fmt($totals['gross']) }}</td></tr>
                            <tr class="stk-total-row"><td colspan="3">إجمالي المستقطعات</td><td class="text-end">{{ $fmt($totals['deductions']) }}</td></tr>
                            <tr class="stk-total-row"><td colspan="3">صافي الراتب</td><td class="text-end">{{ $fmt($totals['net']) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- ══════════ المرفقات ══════════ --}}
            <div class="card stk-card--tight">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-paperclip"></i> المرفقات</h4></div>
                <div class="card-body">
                    {{-- العمودُ اسمُ ملفٍ واحد (نصّ) — كانت الشاشة تعامله مجموعةً فترمي عند وجوده --}}
                    @if (is_string($contract->attachments) && $contract->attachments !== '')
                        <a href="{{ asset('assets/uploads/contracts/'.$contract->attachments) }}" target="_blank" rel="noopener">
                            <i class="feather icon-file"></i> {{ $contract->attachments }}
                        </a>
                    @else
                        <div class="stk-empty py-1">
                            <p class="stk-empty__hint mb-0">لا توجد مرفقات</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════ سجل النشاطات ══════════ --}}
        <div class="tab-pane" id="activity" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    @if ($logs && count($logs) > 0)
                        @php $previousDate = null; @endphp
                        @foreach ($logs as $day => $dayLogs)
                            @php
                                $currentDate = \Carbon\Carbon::parse($day);
                                $diffInDays = $previousDate ? $previousDate->diffInDays($currentDate) : 0;
                            @endphp
                            @if ($diffInDays > 7)
                                <div class="timeline-date"><h4>{{ $currentDate->format('Y-m-d') }}</h4></div>
                            @endif
                            <div class="timeline-day">{{ $currentDate->translatedFormat('l') }}</div>
                            <ul class="timeline">
                                @foreach ($dayLogs as $log)
                                    <li class="timeline-item">
                                        <div class="timeline-content">
                                            <div class="time"><i class="feather icon-clock"></i> {{ $log->created_at->format('H:i:s') }}</div>
                                            <div>
                                                <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                {!! Str::markdown(e($log->description ?? 'لا يوجد وصف')) !!}
                                                <div class="text-muted">{{ $log->user->branch->name ?? '' }}</div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            @php $previousDate = $currentDate; @endphp
                        @endforeach
                    @else
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                            <h5 class="stk-empty__title">لا توجد سجلات نشاط</h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($can['destroy'])
        <div class="modal fade" id="modal_DELETE" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content text-right">
                    <div class="modal-header">
                        <h5 class="modal-title">حذف العقد</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">سيُحذف عقد <strong>{{ $employee->full_name ?? '' }}</strong> رقم <strong>{{ $contract->code }}</strong> نهائياً مع بنوده.</p>
                        <p class="stk-empty__hint mb-0">القسائم الصادرة سابقاً لا تتأثر، لكن مسير الرواتب لن يجد عقداً سارياً للموظف بعده.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                        <form action="{{ route('Contracts.destroy', $contract->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> حذف نهائي</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
