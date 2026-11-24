{{-- تبويب «المدفوعات» — جدولٌ بتصميم «جدول العملاء» (`partials/client_cards`)
     وزرُّ ثلاث نقاطٍ في كل صفّ؛ القائمة تخرج من حدود الجدول بـ`client::partials.table-menu`.
     خمسةَ عشر صفّاً ثم «عرض الكل» — المعالج المشترك `data-vn-more` في `activity-log`.
     التبويب نفسه لا يظهر بلا مدفوعات (`tabs-container`). --}}
@php
    $pyRows = isset($payments) ? $payments->where('type', 'client payments')->values() : collect();
    $pyCurrency = ($account_setting ?? null)?->currency;
    $pySymbol = empty($pyCurrency) || $pyCurrency === 'SAR'
        ? '<img src="' . asset('assets/images/Saudi_Riyal.svg') . '" alt="ريال سعودي" width="14">'
        : e($pyCurrency);
    $pyStatuses = [
        1 => ['label' => 'مكتمل', 'tone' => 'on'],
        2 => ['label' => 'غير مكتمل', 'tone' => 'warning'],
    ];
    $pyIsEmployee = auth()->user()->isEmployee();
    $pyLimit = 15;
@endphp
<div class="tab-pane" id="payments" role="tabpanel">
    <div class="card cl-table-card">
        <div class="cl-table-head">
            <h6 class="cl-table-head__title">
                <i class="fas fa-credit-card"></i>
                المدفوعات
                <span class="cl-count">{{ $pyRows->count() }} دفعة</span>
            </h6>
        </div>

        @if ($pyRows->isNotEmpty())
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>رقم الدفعة</th>
                                <th>التاريخ</th>
                                <th>طريقة الدفع</th>
                                <th>الفاتورة</th>
                                <th>ملاحظة</th>
                                <th class="text-center">المبلغ</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width: 70px;">خيارات</th>
                            </tr>
                        </thead>
                        <tbody id="cl-payments-rows">
                            @foreach ($pyRows as $payment)
                                @php
                                    $pyStatus = $pyStatuses[(int) $payment->payment_status] ?? ['label' => 'غير معروف', 'tone' => 'off'];
                                    $pyAt = $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date) : $payment->created_at;
                                @endphp
                                <tr @if ($loop->iteration > $pyLimit) hidden data-vn-extra @endif>
                                    <td class="text-center cl-num">{{ $loop->iteration }}</td>
                                    <td>
                                        <a href="{{ route('paymentsClient.show', $payment->id) }}"><strong><bdi>#{{ $payment->id }}</bdi></strong></a>
                                        @if ($payment->reference_number)
                                            <small class="text-muted d-block">مرجع: <bdi>{{ $payment->reference_number }}</bdi></small>
                                        @endif
                                    </td>
                                    <td class="cl-num">{{ $pyAt ? $pyAt->format('Y-m-d') : '—' }}</td>
                                    <td>{{ $payment->Payment_method ?: '—' }}</td>
                                    <td>
                                        @if ($payment->invoice)
                                            <a href="{{ route('invoices.show', $payment->invoice_id) }}" target="_blank" rel="noopener"><bdi dir="ltr">#{{ $payment->invoice->code }}</bdi></a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($payment->notes)
                                            <span class="d-inline-block text-truncate" style="max-width: 200px;" title="{{ $payment->notes }}">{{ $payment->notes }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center cl-num"><bdi dir="ltr">{{ number_format($payment->amount, 2) }}</bdi> {!! $pySymbol !!}</td>
                                    <td class="text-center">
                                        <span class="cl-state cl-state--{{ $pyStatus['tone'] }}">{{ $pyStatus['label'] }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item" href="{{ route('paymentsClient.show', $payment->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض التفاصيل
                                                </a>
                                                <a class="dropdown-item" href="{{ route('paymentsClient.edit', $payment->id) }}">
                                                    <i class="fa fa-edit me-2 text-success"></i>تعديل الدفع
                                                </a>
                                                <a class="dropdown-item" href="{{ route('paymentsClient.rereceipt', ['id' => $payment->id]) }}?type=a4" target="_blank" rel="noopener">
                                                    <i class="fa fa-file-pdf me-2 text-info"></i>إيصال A4
                                                </a>
                                                <a class="dropdown-item" href="{{ route('paymentsClient.rereceipt', ['id' => $payment->id]) }}?type=thermal" target="_blank" rel="noopener">
                                                    <i class="fa fa-receipt me-2 text-info"></i>إيصال حراري
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger" href="#"
                                                    onclick="event.preventDefault(); {{ $pyIsEmployee ? 'showPermissionError();' : 'confirmCancelPayment(' . $payment->id . ');' }}">
                                                    <i class="fa fa-times me-2"></i>إلغاء الدفع
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6">الإجمالي</td>
                                <td class="text-center"><bdi dir="ltr">{{ number_format($pyRows->sum('amount'), 2) }}</bdi> {!! $pySymbol !!}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($pyRows->count() > $pyLimit)
                <div class="cl-table-foot">
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-vn-more="cl-payments-rows">
                            <i class="fa fa-chevron-down me-1"></i>عرض كل المدفوعات ({{ $pyRows->count() }})
                        </button>
                    </div>
                </div>
            @endif
        @else
            <div class="cl-empty">
                <span class="cl-empty__icon"><i class="fas fa-credit-card"></i></span>
                <p class="cl-empty__title">لا توجد مدفوعات</p>
                <p class="cl-empty__hint">لم يتم إجراء أي مدفوعات لهذا العميل بعد</p>
            </div>
        @endif
    </div>
</div>
