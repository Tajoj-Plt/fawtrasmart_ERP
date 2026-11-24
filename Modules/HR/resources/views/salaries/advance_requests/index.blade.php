@extends('hr::master')

@section('title')
    طلبات السلف
@stop

@section('content')
    @section('page-actions')
        @permission('loans.create')
            <a href="{{ route('hr.advance-requests.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> طلب سلفة جديدة
            </a>
        @endpermission
    @endsection

    @include('stock::components.page-head', [
        'title' => 'طلبات السلف',
        'icon' => 'arrow-up-circle',
        'crumbs' => ['الرئيسية' => route('hr.department.dashboard'), 'طلبات السلف' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-advance-requests.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه طلباتُ سلفك. والطلبُ التماسٌ لا صرف: لا مالَ يخرج ولا قيدَ يُكتب حتى يُعتمَد ثمّ يُصرف.',
            'blocks' => [
                'مراحلُ السلفة' => [
                    'بانتظار الاعتماد ← معتمدة بانتظار الصرف ← مصروفة في دورة السداد ← قيد السداد ← مسدَّدة بالكامل.',
                    'الاعتمادُ قرارُ المعتمِد الذي اخترته؛ والصرفُ يُخرج المالَ من الخزينة ويُقيِّده على حساب سلفك.',
                ],
                'كيف تُسدَّد' => [
                    'تُقتطع السلفةُ المصروفة من راتبك (أقساطاً للمجدولة)، أو تُسدَّد نقداً لدى الإدارة. والتسويةُ لا تُلغي السلفة: تنقلها في دورة السداد حتى تُقفل.',
                ],
                'السحبُ والإلغاء' => [
                    'تستطيع سحبَ طلبك ما دام بانتظار الاعتماد فيُغلق «ملغى». وبعد الاعتماد يكون الإلغاءُ من الإدارة وحدها.',
                ],
            ],
            'facts' => [
                'الطلبُ لا يصرف مالاً',
                'التسويةُ سدادٌ لا إلغاء',
            ],
        ])

        @isset($advanceSummary)
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-arrow-up-circle"></i></span>
                    <span class="stk-metric__label">إجمالي السلف المصروفة لك
                        <i class="feather icon-help-circle stk-tip" title="من القيود المحاسبية على حساب سلفك مباشرة"></i></span>
                    <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($advanceSummary['issued']) }}</span>
                </div>
                <div class="stk-metric stk-metric--success">
                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                    <span class="stk-metric__label">المسدَّد</span>
                    <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($advanceSummary['settled']) }}</span>
                </div>
                <div class="stk-metric {{ $advanceSummary['remaining'] > 0 ? 'stk-metric--danger' : '' }}">
                    <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                    <span class="stk-metric__label">المتبقّي عليك</span>
                    <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($advanceSummary['remaining']) }}</span>
                    @if (! $advanceSummary['has_account'])
                        <span class="stk-metric__note text-warning"><i class="feather icon-alert-triangle"></i> لا يوجد حساب سلف</span>
                    @endif
                </div>
                <div class="stk-metric stk-metric--warning">
                    <span class="stk-metric__icon"><i class="feather icon-inbox"></i></span>
                    <span class="stk-metric__label">طلبات بانتظار الاعتماد</span>
                    <span class="stk-metric__value stk-num">{{ $statistics['pending'] }}</span>
                </div>
            </div>
        @else
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                    <span class="stk-metric__label">إجمالي الطلبات</span>
                    <span class="stk-metric__value stk-num">{{ $statistics['total'] }}</span>
                </div>
                <div class="stk-metric stk-metric--warning">
                    <span class="stk-metric__icon"><i class="feather icon-inbox"></i></span>
                    <span class="stk-metric__label">بانتظار الاعتماد</span>
                    <span class="stk-metric__value stk-num">{{ $statistics['pending'] }}</span>
                </div>
                <div class="stk-metric stk-metric--success">
                    <span class="stk-metric__icon"><i class="feather icon-refresh-cw"></i></span>
                    <span class="stk-metric__label">قائمة (لم تُسدَّد)</span>
                    <span class="stk-metric__value stk-num">{{ $statistics['active'] }}</span>
                </div>
                <div class="stk-metric stk-metric--danger">
                    <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                    <span class="stk-metric__label">مرفوضة أو ملغاة</span>
                    <span class="stk-metric__value stk-num">{{ $statistics['closed'] }}</span>
                </div>
            </div>
        @endisset

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['stage', 'date_from', 'date_to']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('hr.advance-requests.index') }}">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="stage">المرحلة</label>
                                <select name="stage" id="stage" class="form-control hr-s2">
                                    <option value="">كل المراحل</option>
                                    @foreach (\App\Models\SalaryAdvanceRequest::STAGES as $key => $meta)
                                        <option value="{{ $key }}" @selected(request('stage') === $key)>{{ $meta[0] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="date_from">من تاريخ</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="date_to">إلى تاريخ</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('hr.advance-requests.index') }}" class="btn btn-outline-secondary">
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
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> طلبات السلف
                    <span class="stk-count">{{ $requests->total() }}</span>
                </h6>

                @include('stock::components.per-page', [
                    'sizes' => [15, 25, 50, 100],
                    'current' => request('per_page', 15),
                    'onchange' => "window.location.href='" . route('hr.advance-requests.index') . '?' . http_build_query(request()->except('per_page', 'page')) . "&per_page=' + this.value",
                ])
            </div>

            @if ($requests->count())
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>تاريخ الطلب</th>
                                <th class="text-center">المبلغ</th>
                                <th class="text-center">المتبقّي</th>
                                <th>السبب</th>
                                <th>المعتمِد</th>
                                <th class="text-center">المرحلة</th>
                                <th class="text-center" style="width:90px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $row)
                                <tr>
                                    <td class="stk-num">#{{ $row->id }}</td>
                                    <td>{{ optional($row->requested_date)->format('Y-m-d') }}</td>
                                    <td class="text-center"><strong>{{ \App\Support\CurrencySettings::format($row->amount) }}</strong></td>
                                    <td class="text-center">
                                        @if ($row->isDisbursed())
                                            {{ \App\Support\CurrencySettings::format($row->remainingAmount()) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($row->reason, 40) }}</td>
                                    <td>{{ $row->approver->name ?? ($row->approvedBy->name ?? '—') }}</td>
                                    <td class="text-center"><span class="stk-state {{ $row->stageClass() }}">{{ $row->stageLabel() }}</span></td>
                                    <td class="text-center">
                                        @php
                                            // كلُّ بندٍ بصلاحيته — والسجلُّ داخل النطاق أصلاً (المتحكّمُ قصر القائمة)
                                            $arCanShow = hasPermission('loans.show');
                                            $arCanWithdraw = $row->status === 'pending' && hasPermission('loans.create');
                                        @endphp
                                        @if ($arCanShow || $arCanWithdraw)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($arCanShow)
                                                <a class="dropdown-item" href="{{ route('hr.advance-requests.show', $row->id) }}"><i class="fa fa-eye me-2 text-primary"></i>عرض</a>
                                                @endif
                                                @if ($arCanWithdraw)
                                                    @if ($arCanShow)<div class="dropdown-divider"></div>@endif
                                                    <form action="{{ route('hr.advance-requests.cancel', $row->id) }}" method="POST"
                                                        onsubmit="return confirm('سحب الطلب؟ سيُغلق «ملغى».')">
                                                        @csrf
                                                        @method('PUT')
                                                        <button type="submit" class="dropdown-item text-danger"><i class="fa fa-times me-2"></i>سحب الطلب</button>
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

                @include('stock::components.table-foot', ['paginator' => $requests, 'unit' => 'طلباً'])
            @else
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-inbox"></i></div>
                    <h6 class="stk-empty__title">لا توجد طلبات سلف</h6>
                    <p class="stk-empty__hint">
                        @if (request()->hasAny(['stage', 'date_from', 'date_to']))
                            لا نتائج بهذه الفلاتر — جرّب إعادة التعيين.
                        @else
                            ما تقدّمه من طلبات يظهر هنا مع مرحلته.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>

    @include('hr::components.table-menu')
@endsection
