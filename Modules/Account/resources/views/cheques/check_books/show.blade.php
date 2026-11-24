@extends('account::master')

@section('title', 'تفاصيل دفتر الشيكات')

@section('content')
    @php
        $total = max(0, ($checkbook->end_serial_number - $checkbook->start_serial_number) + 1);
        $used = $checkbook->cheques?->count() ?? 0;
        $remaining = max(0, $total - $used);
        $usedPercent = $total > 0 ? round(($used / $total) * 100) : 0;
    @endphp

    <div class="content-body">

        {{--
            كانت الشاشة تستعمل أصناف `badge-*` (بوتستراب 4) والصفحة تُحمّل
            بوتستراب 5 الذي يعرفها بصيغة `bg-*` — فالشارات تظهر بلا لون ولا
            خلفية: نصٌّ عارٍ في موضع شارة.
        --}}
        @section('page-actions')
            @can('account.cheque_books.edit')
                <a href="{{ route('check_books.edit', $checkbook->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endcan
        @endsection

        @include('account::components.page-head', [
            'title' => 'دفتر الشيكات ' . $checkbook->cheque_book_number,
            'icon' => 'book',
            'sub' => ($checkbook->bank->name ?? 'بنك غير محدد') . ' — ' . $checkbook->currency,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'دفاتر الشيكات' => route('check_books.index'),
                $checkbook->cheque_book_number => null,
            ],
        ])


        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__label">إجمالي الأوراق</span>
                <span class="stk-metric__value stk-num">{{ number_format($total) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-square"></i></span>
                <span class="stk-metric__label">مستخدمة</span>
                <span class="stk-metric__value stk-num">{{ number_format($used) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-square"></i></span>
                <span class="stk-metric__label">متبقّية</span>
                <span class="stk-metric__value stk-num">{{ number_format($remaining) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">نسبة الاستهلاك</span>
                <span class="stk-metric__value stk-num">{{ $usedPercent }}%</span>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-info"></i> معلومات الدفتر
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>رقم الدفتر</span>
                                <strong class="stk-num">{{ $checkbook->cheque_book_number }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>البنك</span>
                                <strong>{{ $checkbook->bank->name ?? 'غير محدد' }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>العملة</span>
                                <strong class="stk-num">{{ $checkbook->currency }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>الحالة</span>
                                @if ($checkbook->status == 1)
                                    <span class="stk-state stk-state--on">نشط</span>
                                @else
                                    <span class="stk-state stk-state--off">غير نشط</span>
                                @endif
                            </div>
                            <div class="stk-def__row">
                                <span>أُنشئ في</span>
                                <strong class="stk-num">{{ $checkbook->created_at?->format('Y-m-d') ?? '—' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-hash"></i> المدى المصرفي
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-equation stk-equation--lg">
                            <span class="stk-equation__side">
                                <small>من</small>
                                <strong class="stk-num">{{ $checkbook->start_serial_number }}</strong>
                            </span>
                            <span class="stk-equation__eq">←</span>
                            <span class="stk-equation__side stk-equation__side--to">
                                <small>إلى</small>
                                <strong class="stk-num">{{ $checkbook->end_serial_number }}</strong>
                            </span>
                        </div>

                        <div class="stk-def stk-def--inline mt-1">
                            <div class="stk-def__row">
                                <span>مستخدمة</span><strong class="stk-num">{{ $used }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>متبقّية</span><strong class="stk-num">{{ $remaining }}</strong>
                            </div>
                        </div>

                        @if ($remaining === 0 && $total > 0)
                            <div class="alert alert-warning mt-1 mb-0" role="alert">
                                <i class="feather icon-alert-triangle"></i>
                                استُهلك الدفتر بالكامل — سجّل دفتراً جديداً قبل إصدار شيكٍ آخر.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($checkbook->notes)
            <div class="card">
                <div class="card-body">
                    <div class="stk-writein">
                        <strong>ملاحظات:</strong> {{ $checkbook->notes }}
                    </div>
                </div>
            </div>
        @endif

        <div class="card stk-table-card">
            @if ($checkbook->cheques && $checkbook->cheques->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-file-text"></i> الشيكات الصادرة من هذا الدفتر
                        <span class="stk-count">{{ $checkbook->cheques->count() }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:60px">#</th>
                                <th>رقم الشيك</th>
                                <th>المستفيد</th>
                                <th class="text-center">المبلغ</th>
                                <th class="text-center">الاستحقاق</th>
                                <th class="text-center">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($checkbook->cheques as $index => $cheque)
                                <tr>
                                    <td class="text-center stk-num">{{ $index + 1 }}</td>
                                    <td class="stk-num">{{ $cheque->cheque_number }}</td>
                                    <td>{{ $cheque->payee_name ?? '—' }}</td>
                                    <td class="text-center">@money($cheque->amount, $cheque)</td>
                                    <td class="text-center stk-num">
                                        {{ $cheque->due_date?->format('Y-m-d') ?? '—' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="stk-state stk-state--neutral">
                                            {{ $cheque->status?->getName() ?? $cheque->status ?? '—' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-file-text"></i></span>
                    <h6 class="stk-empty__title">لم يُصدَر شيكٌ من هذا الدفتر بعد</h6>
                    <p class="stk-empty__hint">كل ورقة تُحرَّر تظهر هنا برقمها المصرفي وحالتها.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
