@extends('hr::master')

@section('title', 'الخصميات')

@php
    $money = fn ($v) => \App\Support\CurrencySettings::format($v);
    $filtered = request()->hasAny(['search', 'employee_id', 'status', 'type', 'date_from', 'date_to', 'settled_filter']);
    $tone = [
        'pending' => 'warn', 'approved' => 'neutral', 'partially_settled' => 'neutral',
        'settled' => 'on', 'rejected' => 'off', 'cancelled' => 'off',
    ];
@endphp

@section('content')
    @php
        // صلاحياتُ صفوف الخصميات — مرّةً للصفحة
        $dedCan = ['show' => hasPermission('deductions.show'), 'edit' => hasPermission('deductions.edit')];
    @endphp
    @include('stock::components.page-head', [
        'title' => 'خصميات الموظفين',
        'icon' => 'minus-circle',
        'sub' => 'الغياب والتأخير والجزاءات — تُقيَّد عند اعتمادها وتُقتطع من الراتب عند صرفه',
        'actions' => hasPermission('deductions.create')
            ? '<a href="' . route('hr.deductions.create') . '" class="btn btn-primary"><i class="feather icon-plus"></i> خصمية جديدة</a>'
            : '',
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('hr::components.guide', [
            'key' => 'hr-deductions.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه خصمياتُ الموظفين: يدويةٌ من هنا، أو آليةٌ من معالج الحضور (غياب، تأخير، انصراف مبكر).',
            'blocks' => [
                'الدورة' => [
                    '**بانتظار الاعتماد** ← **معتمدة** (يُنشأ قيدُها) ← **مسوّاة** عند اقتطاعها من الراتب أو بتسويةٍ يدوية.',
                    'الإلغاءُ يعكس ما قُيِّد ولم يُقتطع بعد. وما اقتُطع في راتبٍ مصروف يُصحَّح بعكس ذلك الصرف.',
                ],
                'المحاسبة' => [
                    'قيدُ الاعتماد: مدين مستحقات الموظف / دائن حساب نوع الخصمية (من «إعدادات الحسابات المحاسبية»).',
                    'وصرفُ الراتب لا يُقيّد الخصميةَ ثانيةً: يُنقص الصافي بها فقط — فلا يتكرّر مبلغُها في الدفاتر.',
                ],
            ],
            'facts' => [
                'القيدُ عند الاعتماد لا عند الصرف',
                'لكل نوعٍ حسابُه، وإلا الحسابُ العام',
            ],
        ])

        <div class="stk-metrics mb-2">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-list"></i></span>
                <span class="stk-metric__label">الكل</span>
                <span class="stk-metric__value stk-num">{{ $statistics['total'] }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">بانتظار الاعتماد</span>
                <span class="stk-metric__value stk-num">{{ $statistics['pending'] }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-check-square"></i></span>
                <span class="stk-metric__label">معتمدة لم تُسوَّ</span>
                <span class="stk-metric__value stk-num">{{ $statistics['approved'] }}</span>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                <span class="stk-metric__label">متبقٍّ للاقتطاع</span>
                <span class="stk-metric__value stk-num">{{ $money($statistics['unsettled_amount']) }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">مسوّى</span>
                <span class="stk-metric__value stk-num">{{ $money($statistics['settled_amount']) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if ($filtered)<span class="stk-count">فلاتر مفعّلة</span>@endif
                </div>
                <form method="GET" action="{{ route('hr.deductions.index') }}">
                    <div class="row">
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="اسم الموظف، رقم الخصمية، السبب…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="employee_id">الموظف</label>
                                <select name="employee_id" id="employee_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($employees as $emp)
                                        <option value="{{ $emp->id }}" @selected((string) request('employee_id') === (string) $emp->id)>{{ trim($emp->first_name . ' ' . $emp->middle_name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    @foreach ($statuses as $k => $v)
                                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="type">النوع</label>
                                <select name="type" id="type" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    @foreach ($types as $k => $v)
                                        <option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="date_from">من تاريخ</label>
                                <input type="date" id="date_from" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="date_to">إلى تاريخ</label>
                                <input type="date" id="date_to" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="settled_filter">التسوية</label>
                                <select name="settled_filter" id="settled_filter" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    <option value="unsettled" @selected(request('settled_filter') === 'unsettled')>لم تُسوَّ بالكامل</option>
                                    <option value="settled" @selected(request('settled_filter') === 'settled')>مسوّاة</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="stk-filters__foot">
                        <a href="{{ route('hr.deductions.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إعادة تعيين</a>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            @if ($deductions->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-list"></i> الخصميات
                        <span class="stk-count">{{ $deductions->total() }}</span></h6>
                    @include('stock::components.per-page', [
                        'sizes' => [10, 20, 50, 100],
                        'current' => request('per_page', 20),
                        'onchange' => "window.location.href='" . request()->fullUrlWithQuery(['per_page' => '__PP__', 'page' => null]) . "'.replace('__PP__', this.value)",
                    ])
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>الرقم</th>
                                <th>الموظف</th>
                                <th>النوع</th>
                                <th>التاريخ</th>
                                <th class="text-end">المبلغ</th>
                                <th class="text-end">المسوّى</th>
                                <th class="text-end">المتبقي</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:110px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deductions as $d)
                                <tr>
                                    <td class="stk-strong">{{ $d->code ?: '#' . $d->id }}</td>
                                    <td>
                                        <span class="stk-media__title">{{ trim(($d->employee->first_name ?? '') . ' ' . ($d->employee->middle_name ?? '')) ?: '—' }}</span>
                                        <span class="stk-media__sub d-block" title="{{ $d->reason }}">{{ \Illuminate\Support\Str::limit($d->reason, 40) }}</span>
                                    </td>
                                    <td><span class="stk-tag">{{ $d->type_label }}</span></td>
                                    <td class="stk-num">{{ optional($d->date)->format('Y-m-d') }}</td>
                                    <td class="text-end stk-num"><strong>{{ $money($d->amount) }}</strong></td>
                                    <td class="text-end stk-num text-success">{{ $money($d->settled_amount) }}</td>
                                    <td class="text-end stk-num text-danger">{{ $money($d->remaining_amount) }}</td>
                                    <td class="text-center"><span class="stk-state stk-state--{{ $tone[$d->status] ?? 'neutral' }}">{{ $d->status_label }}</span></td>
                                    <td class="text-center">
                                        @php $rowEdit = $dedCan['edit'] && $d->canBeEdited(); @endphp
                                        @if ($dedCan['show'] || $rowEdit)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($dedCan['show'])
                                                    <a class="dropdown-item" href="{{ route('hr.deductions.show', $d->id) }}"><i class="fa fa-eye me-2 text-primary"></i>عرض</a>
                                                @endif
                                                @if ($rowEdit)
                                                    <a class="dropdown-item" href="{{ route('hr.deductions.edit', $d->id) }}"><i class="fa fa-edit me-2 text-success"></i>تعديل</a>
                                                @endif
                                                @if ($dedCan['show'])
                                                    <a class="dropdown-item" href="{{ route('hr.deductions.history', $d->id) }}"><i class="fa fa-history me-2 text-info"></i>سجل العمليات</a>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($deductions->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">عرض {{ $deductions->firstItem() }}–{{ $deductions->lastItem() }} من {{ $deductions->total() }}</p>
                        {{ $deductions->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-minus-circle"></i></div>
                        <h5 class="stk-empty__title">لا توجد خصميات</h5>
                        <p class="stk-empty__hint">{{ $filtered ? 'لم يطابق البحث أيّ خصمية — جرّب معايير أوسع' : 'تظهر هنا الخصمياتُ اليدوية وخصمياتُ الحضور الآلية' }}</p>
                        @if ($filtered)
                            <a href="{{ route('hr.deductions.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                        @elseif (hasPermission('deductions.create'))
                            <a href="{{ route('hr.deductions.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> خصمية جديدة</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('hr::components.table-menu')
@endsection
