@extends('hr::master')

@section('title', 'مكافأة نهاية الخدمة')

@php
    $money = fn ($v) => \App\Support\CurrencySettings::format($v);
    $filtered = request()->hasAny(['status', 'employee_id']);
    $canShow = hasPermission('eos.show');
    $canRecalc = hasPermission('eos.create');
@endphp

@section('content')
    @include('stock::components.page-head', [
        'title' => 'مكافأة نهاية الخدمة',
        'icon' => 'award',
        'sub' => 'حساب الاستحقاق من مدة الخدمة وسبب الإنهاء، ثم قيد المخصص فالتسوية فالصرف',
        'actions' => hasPermission('eos.settings')
            ? '<a href="' . route('hr.eos.settings') . '" class="btn btn-outline-secondary"><i class="fa fa-cog"></i> الإعدادات</a>'
            : '',
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-eos.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه مكافأةُ نهاية الخدمة. تُحتسَب من مدّة الخدمة وسببِ إنهائها، ثمّ يُكتب لها قيدُ مخصَّصٍ وتسوية — فهي شاشةٌ ماليّةٌ لا تقريريّة.',
            'blocks' => [
                'المراحل' => [
                    '**مسودة** ← **معتمدة** (قيد المخصص: مدين المصروف / دائن المخصص) ← **مستحقة للموظف** (مدين المخصص / دائن ذمّته) ← **مدفوعة** (مدين ذمّته / دائن الخزينة + سند صرف).',
                ],
                'ما يترتّب على الصرف' => [
                    'يُفحص **رصيدُ الخزينة** تحت قفلٍ قبل أيِّ قيد، ويُرفض الصرفُ إن لم يكفِ — فلا تصير الخزينةُ سالبة.',
                    'ويُمنع إن كانت الخزينةُ خارجَ صلاحيتك أو كانت الفترةُ المحاسبية مقفلة.',
                ],
            ],
            'facts' => [
                'المبلغُ يتبع مدّةَ الخدمة وسببَ الإنهاء',
                'الصرفُ يفحص رصيدَ الخزينة أوّلاً',
            ],
        ])

        <div class="stk-metrics mb-2">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-list"></i></span>
                <span class="stk-metric__label">السجلات</span>
                <span class="stk-metric__value stk-num">{{ $totals['count'] }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">لم تُصرف بعد</span>
                <span class="stk-metric__value stk-num">{{ $totals['open'] }}</span>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-alert-circle"></i></span>
                <span class="stk-metric__label">مستحقة غير مدفوعة</span>
                <span class="stk-metric__value stk-num">{{ $money($totals['unpaid_amount']) }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">مدفوعة</span>
                <span class="stk-metric__value stk-num">{{ $money($totals['paid_amount']) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <form method="GET" action="{{ route('hr.eos.index') }}">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
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
                        <div class="col-md-6 col-sm-12">
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
                    </div>
                    <div class="stk-filters__foot">
                        <a href="{{ route('hr.eos.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إعادة تعيين</a>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            @if ($benefits->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-list"></i> السجلات
                        <span class="stk-count">{{ $benefits->total() }}</span></h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>السبب</th>
                                <th class="text-center">سنوات الخدمة</th>
                                <th class="text-end">الأجر الشهري</th>
                                <th class="text-end">المستحق</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:70px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($benefits as $row)
                                <tr>
                                    <td class="stk-media__title">{{ optional($row->employee)->full_name ?? '#' . $row->employee_id }}</td>
                                    <td>{{ $row->reasonLabel() }}</td>
                                    <td class="text-center stk-num">{{ number_format((float) $row->years_of_service, 2) }}</td>
                                    <td class="text-end stk-num">{{ $money($row->monthly_wage) }}</td>
                                    <td class="text-end stk-num"><strong>{{ $money($row->payable_amount) }}</strong></td>
                                    <td class="text-center"><span class="stk-state stk-state--{{ $row->statusTone() }}">{{ $row->statusLabel() }}</span></td>
                                    <td class="text-center">
                                        @if ($canShow || ($row->isDraft() && $canRecalc))
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($canShow)
                                                <a class="dropdown-item" href="{{ route('hr.eos.show', $row->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                </a>
                                                @endif
                                                @if ($row->isDraft() && $canRecalc)
                                                    <form method="POST" action="{{ route('hr.eos.recalculate', $row->id) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item"><i class="fa fa-sync-alt me-2 text-info"></i>إعادة الحساب</button>
                                                    </form>
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
                @if ($benefits->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">عرض {{ $benefits->firstItem() }}–{{ $benefits->lastItem() }} من {{ $benefits->total() }}</p>
                        {{ $benefits->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-award"></i></div>
                        <h5 class="stk-empty__title">لا سجلات</h5>
                        <p class="stk-empty__hint">
                            {{ $filtered ? 'لم يطابق البحث أيّ سجل.' : 'تُنشأ المسودة تلقائياً عند إنهاء خدمة موظف، أو من بطاقة الموظف المنتهية خدمته.' }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @include('hr::components.table-menu')
@endpush
