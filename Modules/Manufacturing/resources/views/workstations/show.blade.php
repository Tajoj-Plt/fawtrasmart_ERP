@extends('manufacturing::master')

@section('title', $workstation->name)

@section('content')
    @php
        $expenses = $workstation->stationsCosts ?? collect();
        $expensesTotal = $expenses->sum('cost_expenses');

        /*
            أعمدة الحسابات في هذا الجدول اسمها `account_wages` و`origin`.
            كان الشرط يفحص `$workstation->account_origin` — عموداً لا وجود له —
            فلا يظهر بند «الأصل» أبداً مهما كانت تكلفته.
        */
        $hasWages = $workstation->cost_wages > 0;
        $hasOrigin = $workstation->cost_origin > 0;

        // إهلاك الساعة = قيمة الأصل ÷ عمره بالساعات — هو ما يدخل تكلفةَ الساعة لا قيمةُ الأصل
        $hourlyDepreciation = $workstation->hourlyDepreciation();
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('manufacturing.workstations.edit', $workstation->id) }}" class="btn btn-primary">
                <i class="feather icon-edit-2"></i> تعديل
            </a>
            <button type="button" class="btn btn-outline-danger" id="deleteBtn"
                    data-id="{{ $workstation->id }}" data-name="{{ $workstation->name }}">
                <i class="feather icon-trash-2"></i> حذف
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => $workstation->name,
            'icon' => 'cpu',
            'sub' => '#' . $workstation->code . ' — التكلفة لكل ' . \App\Models\WorkStations::UNIT,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'محطات العمل' => route('manufacturing.workstations.index'),
                $workstation->name => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num">@money($workstation->total_cost)</span>
                <span class="stk-metric__label">تكلفة الساعة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-list"></i></span>
                <span class="stk-metric__value stk-num">@money($expensesTotal)</span>
                <span class="stk-metric__label">المصروفات</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__value stk-num">@money($workstation->cost_wages)</span>
                <span class="stk-metric__label">الأجور</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-box"></i></span>
                <span class="stk-metric__value stk-num">@money($hourlyDepreciation)</span>
                <span class="stk-metric__label">إهلاك الأصل في الساعة</span>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#home" role="tab">
                            <i class="feather icon-info"></i> المعلومات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#costs" role="tab">
                            <i class="feather icon-dollar-sign"></i> التكاليف
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#activate" role="tab">
                            <i class="feather icon-activity"></i> سجلّ النشاط
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content p-2">

                <div class="tab-pane active" id="home" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-file-text"></i> بيانات المحطة</h4>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>الاسم</dt>
                                    <dd>{{ $workstation->name }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الكود</dt>
                                    <dd class="stk-num">{{ $workstation->code }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الوحدة</dt>
                                    <dd>{{ \App\Models\WorkStations::UNIT }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الوصف</dt>
                                    <dd>{{ $workstation->description ?: '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="tab-pane" id="costs" role="tabpanel">
                    @if ($expenses->count() > 0)
                        <div class="card stk-table-card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-dollar-sign"></i> المصروفات
                                    <span class="stk-count">{{ $expenses->count() }}</span>
                                </h6>
                                <span class="stk-balance stk-num">@money($expensesTotal)</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:30%">التكلفة</th>
                                            <th>الحساب</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($expenses as $cost)
                                            <tr>
                                                <td class="text-center stk-num">
                                                    @money($cost->cost_expenses)
                                                    <span class="stk-media__sub d-block">لكل {{ \App\Models\WorkStations::UNIT }}</span>
                                                </td>
                                                <td>{{ optional($cost->accountExpenses)->name ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="stk-total-row">
                                            <td class="text-center stk-num">@money($expensesTotal)</td>
                                            <td>إجمالي المصروفات</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if ($hasWages || $hasOrigin)
                        <div class="card stk-table-card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-layers"></i> بنود أخرى
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:22%">البند</th>
                                            <th class="text-center" style="width:28%">التكلفة</th>
                                            <th>الحساب</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if ($hasWages)
                                            <tr>
                                                <td><strong>الأجور</strong></td>
                                                <td class="text-center stk-num">@money($workstation->cost_wages)</td>
                                                <td>{{ optional($workstation->accountWages)->name ?? '—' }}</td>
                                            </tr>
                                        @endif

                                        @if ($hasOrigin)
                                            {{-- قيمةُ الأصل تُعرض للعلم؛ والداخلُ في تكلفة الساعة إهلاكُها --}}
                                            <tr>
                                                <td><strong>إهلاك الأصل</strong></td>
                                                <td class="text-center stk-num">
                                                    @money($hourlyDepreciation)
                                                    <span class="stk-media__sub d-block">
                                                        @if (! $workstation->automatic_depreciation)
                                                            قيمة الأصل @money($workstation->cost_origin) — الإهلاك التلقائي معطَّل فلا يدخل التكلفة
                                                        @elseif ($workstation->asset_life_hours > 0)
                                                            قيمة الأصل @money($workstation->cost_origin)
                                                            ÷ {{ rtrim(rtrim(number_format($workstation->asset_life_hours, 2), '0'), '.') }} ساعة
                                                        @else
                                                            قيمة الأصل @money($workstation->cost_origin) — لم يُحدَّد عمره بالساعات فلا إهلاك
                                                        @endif
                                                    </span>
                                                </td>
                                                <td>{{ optional($workstation->accountOrigin)->name ?? '—' }}</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if ($expenses->isEmpty() && ! $hasWages && ! $hasOrigin)
                        <div class="card">
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-dollar-sign"></i></div>
                                    <h5 class="stk-empty__title">لا تكاليف مسجَّلة</h5>
                                    <p class="stk-empty__hint">أضف مصروفات أو أجوراً أو أصلاً من شاشة التعديل</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="tab-pane" id="activate" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            @if (isset($logs) && count($logs) > 0)
                                <ul class="stk-timeline">
                                    @foreach ($logs as $date => $dayLogs)
                                        @php $day = \Illuminate\Support\Carbon::parse($date); @endphp

                                        <li class="stk-timeline__day">
                                            <span>{{ $day->translatedFormat('l، d F Y') }}</span>
                                        </li>

                                        @foreach ($dayLogs as $log)
                                            @continue (! $log)

                                            <li class="stk-timeline__item">
                                                <span class="stk-timeline__dot stk-timeline__dot--log">
                                                    <i class="feather icon-edit-3"></i>
                                                </span>

                                                <div class="stk-timeline__body">
                                                    <p class="stk-timeline__head">
                                                        <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                        <span class="stk-timeline__time stk-num">
                                                            <i class="feather icon-clock"></i>
                                                            {{ $log->created_at->format('H:i:s') }}
                                                        </span>
                                                    </p>

                                                    <div class="stk-timeline__text">
                                                        @include('manufacturing::partials.log-description', ['log' => $log])
                                                    </div>

                                                    <p class="stk-timeline__meta">
                                                        <span>
                                                            <i class="feather icon-map-pin"></i>
                                                            {{ $log->user->branch->name ?? 'فرع غير معروف' }}
                                                        </span>
                                                    </p>
                                                </div>
                                            </li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            @else
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                                    <h5 class="stk-empty__title">لا نشاط مسجَّل</h5>
                                    <p class="stk-empty__hint">تظهر هنا كل عملية على هذه المحطة</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            $('#deleteBtn').on('click', function () {
                const id = $(this).data('id');

                Swal.fire({
                    title: 'حذف محطة العمل؟',
                    text: $(this).data('name'),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (! result.isConfirmed) return;

                    $.ajax({
                        url: '{{ route('manufacturing.workstations.delete', ':id') }}'.replace(':id', id),
                        method: 'POST',
                        data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') },
                    })
                        .done(function (response) {
                            if (response && response.success === false) {
                                Swal.fire({ icon: 'error', title: 'تعذّر الحذف', text: response.message });
                                return;
                            }
                            window.location.href = '{{ route('manufacturing.workstations.index') }}';
                        })
                        .fail(xhr => Swal.fire({
                            icon: 'error',
                            title: 'تعذّر الحذف',
                            text: (xhr.responseJSON && xhr.responseJSON.message) || '',
                        }));
                });
            });
        });
    </script>
@endsection
