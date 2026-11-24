@if ($payments->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead><tr><th>الدفعة</th><th>التاريخ</th><th>العميل</th><th>العقد / الوحدة</th><th>الطريقة</th><th>الخزينة</th><th class="text-end">المبلغ</th><th class="text-center">الترحيل</th><th></th></tr></thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb text-{{ $payment->status_meta['tone'] }}"><i class="feather icon-credit-card"></i></span>
                                <div class="stk-media__body">
                                    <a href="{{ hasPermission('realestate.payments.show') ? route('realestate.payments.show', $payment->id) : '#' }}" class="stk-media__title">{{ $payment->code }}</a>
                                    <span class="stk-media__sub stk-num">{{ $payment->reference_no ?: '—' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="stk-num">{{ $payment->payment_date->format('Y-m-d') }}</td>
                        <td>{{ $payment->client->display_name ?? '—' }}</td>
                        <td>{{ $payment->contract->code ?? '—' }}<small class="text-muted d-block">{{ $payment->contract->unit->full_label ?? '' }}</small></td>
                        <td>{{ $payment->method_label }}</td>
                        <td>{{ $payment->treasuryAccount->name ?? '—' }}</td>
                        <td class="text-end re-num">{{ \Modules\RentalManagement\Support\RealEstateMoney::format($payment->amount, $payment->currency) }}</td>
                        <td class="text-center"><span class="badge badge-light-{{ $payment->status_meta['tone'] }}">{{ $payment->status_meta['label'] }}</span></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات الدفعة {{ $payment->code }}"><i class="feather icon-more-vertical"></i></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @permission('realestate.payments.show')
                                        <a class="dropdown-item" href="{{ route('realestate.payments.show', $payment->id) }}"><i class="feather icon-eye"></i> عرض</a>
                                        <a class="dropdown-item" href="{{ route('realestate.payments.receipt', $payment->id) }}" target="_blank" rel="noopener"><i class="feather icon-printer"></i> إيصال</a>
                                    @endpermission
                                    @if ($payment->posting_status !== 'cancelled')
                                        @permission('realestate.payments.cancel')
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger re-action" href="#" data-tone="danger" data-url="{{ route('realestate.payments.cancel', $payment->id) }}"
                                               data-confirm="إلغاء الدفعة {{ $payment->code }}{{ $payment->journal_entry_id ? ' وعكس قيدها' : '' }}" data-prompt="سبب الإلغاء"><i class="feather icon-rotate-ccw"></i> إلغاء{{ $payment->journal_entry_id ? ' (قيد عكسي)' : '' }}</a>
                                        @endpermission
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $payments, 'unit' => 'دفعة'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'credit-card', 'title' => 'لا دفعات', 'hint' => 'سجّل أول دفعة أو عدّل معايير البحث'])
@endif
