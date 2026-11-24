{{-- تبويب «الفواتير» — جدولٌ بتصميم «جدول العملاء» (`partials/client_cards`):
     `card cl-table-card` + `cl-table-head` + `table table-hover table-sm`، وزرُّ
     ثلاث نقاطٍ في كل صفّ. القائمة تخرج من حدود الجدول بـ`client::partials.table-menu`
     (مضمَّنةٌ مرّةً في `show.blade.php`) فلا تُقصّ عند حافّة البطاقة.
     `returned_payment` يكفي للمرتجع و`returnSettlementState()` للحالة.
     خمسةَ عشر صفّاً ثم «عرض الكل» — المعالج المشترك `data-vn-more` في `activity-log`. --}}
@php
    $ivRows = $invoices ?? collect();
    $ivCurrency = ($account_setting ?? null)?->currency;
    $ivSymbol = empty($ivCurrency) || $ivCurrency === 'SAR'
        ? '<img src="' . asset('assets/images/Saudi_Riyal.svg') . '" alt="ريال سعودي" width="14">'
        : e($ivCurrency);
    $ivStatuses = [
        1 => ['label' => 'مدفوعة بالكامل', 'tone' => 'on'],
        2 => ['label' => 'مدفوعة جزئياً', 'tone' => 'warning'],
        3 => ['label' => 'غير مدفوعة', 'tone' => 'danger'],
        4 => ['label' => 'مستلمة', 'tone' => 'off'],
    ];
    $ivCanAddPayment = hasPermission('sales.payments.create');
    $ivLimit = 15;
    $ivTotal = 0;
    $ivReturnedTotal = 0;
    $ivDueTotal = 0;
@endphp
<div class="tab-pane" id="invoices" role="tabpanel">
    <div class="card cl-table-card">
        <div class="cl-table-head">
            <h6 class="cl-table-head__title">
                <i class="fas fa-file-invoice"></i>
                الفواتير
                <span class="cl-count">{{ $ivRows->count() }} فاتورة</span>
            </h6>
        </div>

        @if ($ivRows->isNotEmpty())
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>أنشأها</th>
                                <th class="text-center">الإجمالي</th>
                                <th class="text-center">المرتجع</th>
                                <th class="text-center">المستحق</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width: 70px;">خيارات</th>
                            </tr>
                        </thead>
                        <tbody id="cl-invoices-rows">
                            @foreach ($ivRows as $invoice)
                                @php
                                    $ivAmount = (float) ($invoice->grand_total ?? $invoice->total);
                                    $ivReturned = (float) ($invoice->returned_payment ?? 0);
                                    $ivNetDue = (float) $invoice->due_value - $ivReturned;
                                    $ivTotal += $ivAmount;
                                    $ivReturnedTotal += $ivReturned;
                                    $ivDueTotal += max($ivNetDue, 0);
                                    // المرتجعُ (أو الإشعار الدائن) أطفأ ما بقي — لا «غير مدفوعة» بلا مستحق
                                    $ivStatus = match ($invoice->returnSettlementState()) {
                                        'returned' => ['label' => 'مرتجعة', 'tone' => 'off'],
                                        'settled' => ['label' => 'مُسدَّدة', 'tone' => 'on'],
                                        default => $ivStatuses[(int) $invoice->payment_status] ?? ['label' => 'غير معروفة', 'tone' => 'off'],
                                    };
                                @endphp
                                <tr @if ($loop->iteration > $ivLimit) hidden data-vn-extra @endif>
                                    <td class="text-center cl-num">{{ $loop->iteration }}</td>
                                    <td>
                                        <a href="{{ route('invoices.show', $invoice->id) }}"><strong><bdi>{{ $invoice->code }}</bdi></strong></a>
                                        @if ($invoice->notes)
                                            <small class="text-muted d-block text-truncate" style="max-width: 220px;" title="{{ $invoice->notes }}">{{ $invoice->notes }}</small>
                                        @endif
                                    </td>
                                    <td class="cl-num">
                                        @if ($invoice->created_at)
                                            {{ $invoice->created_at->format('Y-m-d') }}
                                            <small class="text-muted d-block">{{ $invoice->created_at->format('h:i') }} {{ $invoice->created_at->format('A') === 'AM' ? 'ص' : 'م' }}</small>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        {{ $invoice->createdByUser->name ?? 'غير محدد' }}
                                        @if ($invoice->employee)
                                            <small class="text-muted d-block">للمندوب: {{ $invoice->employee->first_name ?? 'غير محدد' }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center cl-num"><bdi dir="ltr">{{ number_format($ivAmount, 2) }}</bdi> {!! $ivSymbol !!}</td>
                                    <td class="text-center cl-num">
                                        @if ($ivReturned > 0)
                                            <span class="text-danger"><bdi dir="ltr">{{ number_format($ivReturned, 2) }}</bdi> {!! $ivSymbol !!}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center cl-num">
                                        @if ($ivNetDue >= 0.005)
                                            <span class="text-danger"><bdi dir="ltr">{{ number_format($ivNetDue, 2) }}</bdi> {!! $ivSymbol !!}</span>
                                        @else
                                            <span class="text-muted">لا مستحق</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="cl-state cl-state--{{ $ivStatus['tone'] }}">{{ $ivStatus['label'] }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item" href="{{ route('invoices.show', $invoice->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                </a>
                                                <a class="dropdown-item" href="{{ route('invoices.edit', $invoice->id) }}">
                                                    <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                </a>
                                                <a class="dropdown-item" href="{{ route('invoices.generatePdf', $invoice->id) }}">
                                                    <i class="fa fa-print me-2 text-info"></i>PDF / طباعة
                                                </a>
                                                <a class="dropdown-item" href="{{ route('invoices.send', $invoice->id) }}">
                                                    <i class="fa fa-envelope me-2 text-info"></i>إرسال إلى العميل
                                                </a>
                                                @if ($ivCanAddPayment)
                                                    <a class="dropdown-item" href="{{ route('paymentsClient.create', ['id' => $invoice->id]) }}">
                                                        <i class="fa fa-credit-card me-2 text-warning"></i>إضافة عملية دفع
                                                    </a>
                                                @endif
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('invoices.destroy', $invoice->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger"
                                                        onclick="return confirm('هل أنت متأكد من حذف هذه الفاتورة؟')">
                                                        <i class="fa fa-trash me-2"></i>حذف
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4">الإجمالي</td>
                                <td class="text-center"><bdi dir="ltr">{{ number_format($ivTotal, 2) }}</bdi> {!! $ivSymbol !!}</td>
                                <td class="text-center">
                                    @if ($ivReturnedTotal > 0)
                                        <bdi dir="ltr">{{ number_format($ivReturnedTotal, 2) }}</bdi> {!! $ivSymbol !!}
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($ivDueTotal >= 0.005)
                                        <bdi dir="ltr">{{ number_format($ivDueTotal, 2) }}</bdi> {!! $ivSymbol !!}
                                    @endif
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($ivRows->count() > $ivLimit)
                <div class="cl-table-foot">
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-vn-more="cl-invoices-rows">
                            <i class="fa fa-chevron-down me-1"></i>عرض كل الفواتير ({{ $ivRows->count() }})
                        </button>
                    </div>
                </div>
            @endif
        @else
            <div class="cl-empty">
                <span class="cl-empty__icon"><i class="fas fa-file-invoice"></i></span>
                <p class="cl-empty__title">لا توجد فواتير</p>
                <p class="cl-empty__hint">لم تُصدر فواتير لهذا العميل بعد</p>
            </div>
        @endif
    </div>
</div>
