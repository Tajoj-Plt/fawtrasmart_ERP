@if (isset($payments) && $payments->where('type', 'client payments')->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>رقم الدفع</th>
                    <th>الفاتورة</th>
                    <th>المبلغ</th>
                    <th>تاريخ الدفع</th>
                    <th>الحالة</th>
                    <th>خيارات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments->where('type', 'client payments') as $payment)
                    <tr>
                        <td><strong>#{{ $payment->id }}</strong></td>
                        <td>{{ $payment->invoice ? $payment->invoice->code : '--' }}</td>
                        <td class="text-success fw-bold">{{ number_format($payment->amount, 2) }}
                            {!! $currencySymbol !!}</td>
                        <td>{{ $payment->payment_date ?? $payment->created_at->format('Y-m-d') }}</td>
                        <td>
                            @php
                                $statusClass = match ($payment->payment_status) {
                                    1 => 'bg-success',
                                    2 => 'bg-warning',
                                    default => 'bg-secondary',
                                };
                                $statusText = match ($payment->payment_status) {
                                    1 => 'مكتمل',
                                    2 => 'غير مكتمل',
                                    default => 'غير معروف',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm" type="button" data-bs-toggle="dropdown">
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('paymentsClient.show', $payment->id) }}">
                                        <i class="fas fa-eye me-2 text-primary"></i>عرض التفاصيل
                                    </a>
                                    <a class="dropdown-item" href="{{ route('paymentsClient.edit', $payment->id) }}">
                                        <i class="fas fa-edit me-2 text-success"></i>تعديل الدفع
                                    </a>
                                    <hr class="dropdown-divider">
                                    <button type="button" class="dropdown-item text-danger {{ auth()->user()->isEmployee() ? 'disabled-action' : '' }}"
                                        onclick="{{ auth()->user()->isEmployee() ? 'showPermissionError()' : 'confirmCancelPayment(' . $payment->id . ')' }}">
                                        <i class="fa fa-times me-2"></i>إلغاء عملية الدفع
                                    </button>
                                    <a class="dropdown-item" href="{{ route('paymentsClient.rereceipt', ['id' => $payment->id]) }}?type=a4" target="_blank">
                                        <i class="fas fa-file-pdf me-2 text-warning"></i>إيصال (A4)
                                    </a>
                                    <a class="dropdown-item" href="{{ route('paymentsClient.rereceipt', ['id' => $payment->id]) }}?type=thermal" target="_blank">
                                        <i class="fas fa-receipt me-2 text-warning"></i>إيصال (حراري)
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($payments->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="pagination-info text-muted">
                عرض {{ $payments->firstItem() ?? 0 }} إلى {{ $payments->lastItem() ?? 0 }} من {{ $payments->total() }} دفعة
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ $payments->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $payments->currentPage() - 1 }}"
                           data-tab="payments"
                           {{ $payments->onFirstPage() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-right"></i>
                        </a>
                    </li>

                    @php
                        $start = max($payments->currentPage() - 2, 1);
                        $end = min($start + 4, $payments->lastPage());
                        if ($end - $start < 4) {
                            $start = max($end - 4, 1);
                        }
                    @endphp

                    @for ($i = $start; $i <= $end; $i++)
                        <li class="page-item {{ $payments->currentPage() == $i ? 'active' : '' }}">
                            <a class="page-link pagination-link"
                               href="#"
                               data-page="{{ $i }}"
                               data-tab="payments">
                                {{ $i }}
                            </a>
                        </li>
                    @endfor

                    <li class="page-item {{ !$payments->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $payments->currentPage() + 1 }}"
                           data-tab="payments"
                           {{ !$payments->hasMorePages() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-left"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    @endif
@else
    <div class="empty-state">
        <i class="fas fa-credit-card"></i>
        <h5>لا توجد مدفوعات</h5>
        <p>لم يتم إجراء أي مدفوعات لهذا العميل بعد</p>
        <a href="{{ route('incomes.create', isset($client) ? ['client_id' => $client->id] : []) }}" class="btn btn-unified">
            <i class="fas fa-plus me-1"></i>إضافة دفعة جديدة
        </a>
    </div>
@endif