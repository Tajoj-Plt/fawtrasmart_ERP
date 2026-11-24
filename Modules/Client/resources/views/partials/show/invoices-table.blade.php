@if(isset($invoices) && $invoices->count() > 0)
    <div class="table">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>التاريخ</th>
                    <th>المبلغ</th>
                    <th>الحالة</th>
                    <th>المبلغ المستحق</th>
                    <th>خيارات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $invoice)
                    <tr>
                        <td><strong>#{{ $invoice->code ?? $invoice->id }}</strong></td>
                        <td>{{ $invoice->created_at->format('Y-m-d') }}</td>
                        <td>{{ number_format($invoice->grand_total ?? $invoice->total, 2) }}
                            {!! $currencySymbol !!}</td>
                        <td>
                            @php
                                $statusClass = match ($invoice->payment_status) {
                                    1 => 'bg-success',
                                    2 => 'bg-warning',
                                    3 => 'bg-danger',
                                    default => 'bg-secondary',
                                };
                                $statusText = match ($invoice->payment_status) {
                                    1 => 'مدفوعة',
                                    2 => 'جزئية',
                                    3 => 'غير مدفوعة',
                                    default => 'غير معروف',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                        </td>
                        <td>
                            @if (isset($invoice->due_value) && $invoice->due_value > 0)
                                <span class="text-danger fw-bold">{{ number_format($invoice->due_value, 2) }}
                                {!! $currencySymbol !!}</span>
                            @else
                                <span class="text-success">--</span>
                            @endif
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="dropdown">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="{{ route('invoices.show', $invoice->id) }}">
                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                    </a>
                                    <a class="dropdown-item" href="{{ route('invoices.edit', $invoice->id) }}">
                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                    </a>
                                    <a class="dropdown-item" href="{{ route('invoices.generatePdf', $invoice->id) }}">
                                        <i class="fa fa-file-pdf me-2 text-danger"></i>PDF
                                    </a>
                                    <hr class="dropdown-divider">
                                    <a class="dropdown-item" href="{{ route('paymentsClient.create',  $invoice->id) }}">
                                        <i class="fa fa-money-bill me-2 text-info"></i>إضافة دفعة
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($invoices->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="pagination-info text-muted">
                عرض {{ $invoices->firstItem() ?? 0 }} إلى {{ $invoices->lastItem() ?? 0 }} من {{ $invoices->total() }} فاتورة
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    {{-- Previous Button --}}
                    <li class="page-item {{ $invoices->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $invoices->currentPage() - 1 }}"
                           data-tab="invoices"
                           {{ $invoices->onFirstPage() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-right"></i>
                        </a>
                    </li>

                    {{-- Numbered Pages --}}
                    @php
                        $start = max($invoices->currentPage() - 2, 1);
                        $end = min($start + 4, $invoices->lastPage());
                        if ($end - $start < 4) {
                            $start = max($end - 4, 1);
                        }
                    @endphp

                    @for ($i = $start; $i <= $end; $i++)
                        <li class="page-item {{ $invoices->currentPage() == $i ? 'active' : '' }}">
                            <a class="page-link pagination-link"
                               href="#"
                               data-page="{{ $i }}"
                               data-tab="invoices">
                                {{ $i }}
                            </a>
                        </li>
                    @endfor

                    {{-- Next Button --}}
                    <li class="page-item {{ !$invoices->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $invoices->currentPage() + 1 }}"
                           data-tab="invoices"
                           {{ !$invoices->hasMorePages() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-left"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    @endif
@else
    <div class="empty-state">
        <i class="fas fa-file-invoice"></i>
        <h5>لا توجد فواتير</h5>
        <p>لم يتم إنشاء أي فواتير لهذا العميل بعد</p>
        <a href="{{ route('invoices.create', ['client_id' => $client->id]) }}" class="btn btn-unified">
            <i class="fas fa-plus me-1"></i>إنشاء فاتورة جديدة
        </a>
    </div>
@endif