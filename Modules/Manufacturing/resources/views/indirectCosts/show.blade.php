@extends('manufacturing::master')

@section('title', 'تكلفة غير مباشرة #' . $indirectCost->id)

@section('content')
    @php
        $items = $indirectCost->indirectCostItems ?? collect();
        $basedOnQuantity = $indirectCost->based_on == 1;
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('manufacturing.indirectcosts.edit', $indirectCost->id) }}" class="btn btn-primary">
                <i class="feather icon-edit-2"></i> تعديل
            </a>
            <button type="button" class="btn btn-outline-danger"
                    data-toggle="modal" data-bs-toggle="modal"
                    data-target="#deleteModal" data-bs-target="#deleteModal">
                <i class="feather icon-trash-2"></i> حذف
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => 'تكلفة غير مباشرة',
            'icon' => 'pie-chart',
            'sub' => (optional($indirectCost->account)->name ?? 'حساب غير محدد')
                . ' — من ' . $indirectCost->from_date . ' إلى ' . $indirectCost->to_date,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التكاليف غير المباشرة' => route('manufacturing.indirectcosts.index'),
                '#' . $indirectCost->id => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        {{-- ══════════ الخلاصة ══════════ --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-book"></i></span>
                <span class="stk-metric__value">{{ optional($indirectCost->account)->name ?? '—' }}</span>
                <span class="stk-metric__label">الحساب</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num">@money($indirectCost->total)</span>
                <span class="stk-metric__label">إجمالي التكاليف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__value stk-num">{{ $indirectCost->from_date }}</span>
                <span class="stk-metric__label">من تاريخ</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__value stk-num">{{ $indirectCost->to_date }}</span>
                <span class="stk-metric__label">إلى تاريخ</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-share-2"></i></span>
                <span class="stk-metric__value">{{ $basedOnQuantity ? 'حسب الكمية' : 'حسب التكلفة' }}</span>
                <span class="stk-metric__label">نوع التوزيع</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__value stk-num">{{ rtrim(rtrim(number_format((float) $indirectCost->unit_rate, 6, '.', ''), '0'), '.') ?: '0' }}</span>
                <span class="stk-metric__label">{{ $basedOnQuantity ? 'نصيب الوحدة المنتَجة' : 'نصيب كل 1 من تكلفة الأمر' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__value stk-num">@money($appliedTotal ?? 0)</span>
                <span class="stk-metric__label">المحمَّل فعلاً على الأوامر</span>
            </div>
        </div>

        {{-- ══════════ التبويبات ══════════ --}}
        <div class="card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#home" role="tab">
                            <i class="feather icon-info"></i> التفاصيل
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
                    @php
                        $entryItems = $items->whereNotNull('restriction_id')->values();
                        $orderItems = $items->whereNotNull('manufacturing_order_id')->values();
                    @endphp

                    {{-- القيدُ والأمرُ بندان مستقلّان: قيدٌ واحد يُوزَّع على أوامرَ عدّة،
                         فلا يُقرآن في سطرٍ واحد. جدولٌ للقيود وجدولٌ للأوامر. --}}
                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-book"></i> قيود الحساب
                                <span class="stk-count">{{ $entryItems->count() }}</span>
                            </h6>
                        </div>

                        @if ($entryItems->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:44px">#</th>
                                            <th style="width:20%">رقم القيد</th>
                                            <th style="width:16%">التاريخ</th>
                                            <th>البيان</th>
                                            <th class="text-center" style="width:18%">مبلغ القيد</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($entryItems as $item)
                                            <tr>
                                                <td class="stk-num text-muted">{{ $loop->iteration }}</td>
                                                <td class="stk-num">{{ optional($item->journalEntry)->reference_number ?: ('#' . $item->restriction_id) }}</td>
                                                <td class="stk-num">{{ optional(optional($item->journalEntry)->date)->format('Y-m-d') ?: '—' }}</td>
                                                <td>{{ \Illuminate\Support\Str::limit(optional($item->journalEntry)->description, 90) ?: '—' }}</td>
                                                <td class="text-center stk-num">@money($item->restriction_total)</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="stk-total-row">
                                            <td colspan="4">إجمالي القيود</td>
                                            <td class="text-center stk-num">@money($entryItems->sum('restriction_total'))</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-book"></i></div>
                                    <h5 class="stk-empty__title">لا قيود</h5>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-package"></i> أوامر التصنيع في الفترة
                                <span class="stk-count">{{ $orderItems->count() }}</span>
                            </h6>
                        </div>

                        @if ($orderItems->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:44px">#</th>
                                            <th>أمر التصنيع</th>
                                            <th class="text-center" style="width:16%">الكمية</th>
                                            <th class="text-center" style="width:20%">نصيب الأمر</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($orderItems as $item)
                                            <tr>
                                                <td class="stk-num text-muted">{{ $loop->iteration }}</td>
                                                <td>
                                                    @if ($item->manufacturingOrder)
                                                        <a href="{{ route('manufacturing.orders.show', $item->manufacturing_order_id) }}">
                                                            {{ $item->manufacturingOrder->name }}
                                                        </a>
                                                        <span class="stk-media__sub stk-num">#{{ $item->manufacturingOrder->code }}</span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="text-center stk-num">{{ optional($item->manufacturingOrder)->quantity + 0 }}</td>
                                                <td class="text-center stk-num">@money($item->manufacturing_price)</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="stk-total-row">
                                            <td colspan="3">إجمالي التوزيع</td>
                                            <td class="text-center stk-num">@money($orderItems->sum('manufacturing_price'))</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                                    <h5 class="stk-empty__title">لا أوامر</h5>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="feather icon-info"></i>
                        هذه الشاشة للإدخال: النظام يحسب <strong>نصيب الوحدة</strong> من المصروف.
                        ليدخل المصروف تكلفةَ أمرٍ، اختره في قسم «المصروفات غير المباشرة» داخل أمر التصنيع —
                        يُحسب مبلغُه تلقائياً بحسب {{ $basedOnQuantity ? 'كمية الأمر' : 'تكلفة الأمر' }} ويُرحَّل مع قيد إتمام الأمر.
                    </div>
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
                                    <p class="stk-empty__hint">تظهر هنا كل عملية على هذه التكلفة</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- الحذف بنموذج DELETE: كان رابط GET على مسار DELETE فيردّ 405 --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف التكلفة غير المباشرة</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">ستُحذف التكلفة رقم <strong class="stk-num">#{{ $indirectCost->id }}</strong> نهائياً.</p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <form method="POST" action="{{ route('manufacturing.indirectcosts.destroy', $indirectCost->id) }}"
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="feather icon-trash-2"></i> حذف نهائي
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
