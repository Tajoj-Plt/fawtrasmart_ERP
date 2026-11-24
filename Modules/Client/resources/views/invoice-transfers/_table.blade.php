{{--
    جدولُ تحويلاتٍ واحد لقوائم الشاشة الثلاث (بانتظار ردّي / بدأتُها / الكل).

    الأزرار تُحسب لكلّ صفّ من اتجاهه لا من القائمة التي هو فيها: في طلب
    الاستلام يردّ صاحبُ العهدة (`from`) ويُلغي الطالب (`to`) — عكسُ التسليم.
    والمديرُ يردّ نيابةً كما يسمح `InvoiceTransferController::respondableQuery()`.

    @param \Illuminate\Support\Collection $rows
    @param string $emptyIcon
    @param string $emptyTitle
    @param string $emptyHint
--}}
<div class="table-responsive">
    <table class="table table-hover mb-0 cl-it-table">
        <thead>
            <tr>
                <th>الفاتورة</th>
                <th>العميل</th>
                <th>النوع</th>
                <th>من ← إلى</th>
                <th>المبلغ</th>
                <th>التاريخ</th>
                <th>الملاحظات</th>
                <th>الحالة</th>
                <th>العمليات</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $transfer)
                @php
                    $rowCode = $transfer->subject_label;
                    $rowAt = $transfer->sent_at ?? $transfer->created_at;
                    $rowCanRespond = $transfer->isPending() && $itCanRespond
                        && ($transfer->responderId() === (int) $itUser->id || $itActsOnBehalf);
                    $rowCanCancel = $transfer->isPending() && $itCanCancel
                        && $transfer->initiatorId() === (int) $itUser->id;
                @endphp
                <tr>
                    <td data-num>
                        @if ($transfer->invoice)
                            <a href="{{ route('invoices.show', $transfer->invoice_id) }}" target="_blank" class="text-decoration-none"><strong><bdi>{{ $rowCode }}</bdi></strong></a>
                        @else
                            <strong><bdi>{{ $rowCode }}</bdi></strong>
                        @endif
                    </td>
                    <td>
                        @if ($transfer->client)
                            <a href="{{ route('clients.show', $transfer->client->id) }}" class="cl-media__title text-decoration-none">
                                {{ $transfer->client->trade_name }}
                            </a>
                            @if ($transfer->client->code)
                                <p class="cl-media__sub mb-0">رقم العميل <bdi>{{ $transfer->client->code }}</bdi></p>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="cl-state cl-state--neutral text-nowrap">
                            <i class="feather {{ $transfer->isRequest() ? 'icon-download' : 'icon-upload' }}"></i> {{ $transfer->direction_text }}
                        </span>
                    </td>
                    <td>
                        <span class="text-nowrap">{{ $transfer->fromEmployee->name ?? '—' }}</span>
                        <i class="feather icon-arrow-left text-muted"></i>
                        <span class="text-nowrap">{{ $transfer->toEmployee->name ?? '—' }}</span>
                    </td>
                    <td data-num>
                        {{ number_format((float) $transfer->invoice_amount, 2) }}
                        @if ($transfer->isBalance())
                            <p class="cl-media__sub mb-0">رصيد الحساب يوم التسليم</p>
                        @elseif ($transfer->invoice)
                            <p class="cl-media__sub mb-0">المستحق {{ number_format((float) $transfer->invoice->due_value, 2) }}</p>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        {{ $rowAt?->format('Y-m-d H:i') }}
                        @if ($transfer->responded_at)
                            <p class="cl-media__sub mb-0">الردّ {{ $transfer->responded_at->format('Y-m-d') }}</p>
                        @endif
                    </td>
                    <td>
                        {{ $transfer->sender_notes ?: '—' }}
                        @if ($transfer->receiver_notes)
                            <p class="cl-media__sub mb-0">الردّ: {{ $transfer->receiver_notes }}</p>
                        @endif
                    </td>
                    {{-- `cl-state` لا `badge-{{ status_color }}`: في هذا القالب يأخذ نصُّ الـbadge لونَ خلفيّته فتختفي الحالة --}}
                    <td><span class="cl-state cl-state--{{ ['pending' => 'warning', 'accepted' => 'on', 'rejected' => 'danger'][$transfer->status] ?? 'off' }}">{{ $transfer->status_text }}</span></td>
                    <td>
                        <div class="cl-actions">
                            @if ($rowCanRespond)
                                <button type="button" class="btn btn-outline-success cl-it-respond"
                                        data-bs-toggle="modal" data-bs-target="#clItRespondModal"
                                        data-id="{{ $transfer->id }}" data-mode="accept" data-code="{{ $rowCode }}"
                                        data-request="{{ $transfer->isRequest() ? 1 : 0 }}"
                                        data-to="{{ $transfer->toEmployee->name ?? '' }}"
                                        title="قبول"><i class="feather icon-check"></i></button>
                                <button type="button" class="btn btn-outline-danger cl-it-respond"
                                        data-bs-toggle="modal" data-bs-target="#clItRespondModal"
                                        data-id="{{ $transfer->id }}" data-mode="reject" data-code="{{ $rowCode }}"
                                        data-request="{{ $transfer->isRequest() ? 1 : 0 }}"
                                        data-to="{{ $transfer->toEmployee->name ?? '' }}"
                                        title="رفض"><i class="feather icon-x"></i></button>
                            @elseif ($rowCanCancel)
                                <button type="button" class="btn btn-outline-danger cl-it-cancel"
                                        data-id="{{ $transfer->id }}" data-code="{{ $rowCode }}"
                                        title="إلغاء الطلب"><i class="feather icon-trash-2"></i></button>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        <div class="cl-empty">
                            <span class="cl-empty__icon"><i class="feather {{ $emptyIcon }}"></i></span>
                            <p class="cl-empty__title">{{ $emptyTitle }}</p>
                            <p class="cl-empty__hint">{{ $emptyHint }}</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
