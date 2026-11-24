@extends('client::reservations.master')

{{--
    شاشةُ الحجز — ما فيها يُفعل بالحجز: الطباعة، والإرسال للعميل، والتعديل،
    وتغيير الحالة، و«تحويل إلى فاتورة».

    **لا مالَ هنا**: لا قبضَ ولا ردّ. «تحويل إلى فاتورة» يفتح شاشة فاتورة
    المبيعات معبّأةً، والتحصيلُ عليها من المبيعات.
--}}

@section('title', __('reservations.ui.reservation_code', ['code' => $reservation->code]))

@section('content')
    @php
        $money = fn ($value) => number_format((float) $value, 2);
        $status = $reservation->status;
        // الفاتورةُ القائمة — لا `invoice_id` وحده، فالفاتورةُ قد تُحذف ويبقى معرّفُها
        $invoice = $reservation->isInvoiced() ? $reservation->invoice : null;
        $invoiceDue = $invoice ? (float) $invoice->due_value : null;
        // القاعدةُ في النموذج (`canBeInvoiced`) — هي نفسُها التي تحرس `toInvoice` وشاشةَ الفاتورة
        $invoiceable = hasPermission('reservations.create_invoice') && $reservation->canBeInvoiced();
    @endphp

    @section('page-actions')
        <a href="{{ route('Reservations.print', $reservation->id) }}" target="_blank" class="btn btn-outline-secondary">
            <i class="feather icon-printer"></i> {{ __('reservations.actions.print') }}
        </a>
        @if (hasPermission('reservations.edit') && ! $status->isFinal() && ! $invoice)
            <a href="{{ route('Reservations.edit', $reservation->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-edit-2"></i> {{ __('reservations.actions.edit') }}
            </a>
        @endif
        @if ($invoiceable)
            <a href="{{ route('Reservations.toInvoice', $reservation->id) }}" class="btn btn-primary">
                <i class="feather icon-file-plus"></i> {{ __('reservations.actions.to_invoice') }}
            </a>
        @endif
        <a href="{{ route('Reservations.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-list"></i> {{ __('reservations.actions.back_to_list') }}
        </a>
    @endsection

    @include('client::reservations.components.page-head', [
        'title' => __('reservations.ui.reservation_code', ['code' => $reservation->code]),
        'icon' => 'bookmark',
        'sub' => $reservation->reservation_date?->format('Y-m-d')
            . ' — ' . substr((string) $reservation->start_time, 0, 5)
            . ' → ' . substr((string) $reservation->end_time, 0, 5)
            . ' (' . __('reservations.ui.minutes', ['n' => $reservation->duration]) . ')',
        'crumbs' => [
            __('reservations.menu.home') => route('dashboard'),
            __('reservations.menu.reservations') => route('Reservations.index'),
            $reservation->code => null,
        ],
    ])

    @include('client::reservations.partials.alerts')

    {{-- حجزٌ صارت له فاتورة — يُقال في أوّل الشاشة لا في بطاقةٍ جانبية وحدها --}}
    @if ($invoice)
        <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2 rsv-no-print">
            <span><i class="feather icon-check-circle"></i> {{ __('reservations.links.converted', ['code' => $invoice->code]) }}</span>
            @if (Route::has('invoices.show'))
                <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-sm btn-success">
                    <i class="feather icon-file-text"></i> {{ __('reservations.links.open_invoice') }}
                </a>
            @endif
        </div>
    @endif

    {{-- ═════════ الحالة: الأزرارُ المسموحة وحدَها ═════════ --}}
    @if (count($transitions))
        <div class="rsv-card rsv-no-print">
            <div class="rsv-card__head">
                <h6 class="rsv-card__title"><i class="feather icon-git-pull-request"></i> {{ __('reservations.ui.next_step') }}</h6>
                <span class="rsv-badge" style="background: {{ $status->color() }}">{{ $status->label() }}</span>
            </div>
            <div class="rsv-card__body d-flex gap-2 flex-wrap">
                @foreach ($transitions as $target)
                    @php
                        $map = [
                            'confirmed' => ['route' => 'Reservations.confirm', 'class' => 'btn-info', 'label' => __('reservations.actions.confirm')],
                            'checked_in' => ['route' => 'Reservations.checkin', 'class' => 'btn-primary', 'label' => __('reservations.actions.check_in')],
                            'in_progress' => ['route' => 'Reservations.start', 'class' => 'btn-primary', 'label' => __('reservations.actions.start')],
                            'completed' => ['route' => 'Reservations.complete', 'class' => 'btn-success', 'label' => __('reservations.actions.complete')],
                            'no_show' => ['route' => 'Reservations.noShow', 'class' => 'btn-dark', 'label' => __('reservations.actions.no_show')],
                            'rejected' => ['route' => 'Reservations.reject', 'class' => 'btn-outline-danger', 'label' => __('reservations.actions.reject')],
                        ];
                        $action = $map[$target->value] ?? null;
                    @endphp

                    @if ($action)
                        <form method="POST" action="{{ route($action['route'], $reservation->id) }}" data-rsv-once
                              data-rsv-confirm="{{ __('reservations.ui.confirm_action', ['action' => $action['label']]) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $action['class'] }}">
                                <i class="feather icon-{{ $target->icon() }}"></i> {{ $action['label'] }}
                            </button>
                        </form>
                    @elseif ($target->value === 'cancelled' && ! $invoice)
                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rsvCancelModal">
                            <i class="feather icon-x-circle"></i> {{ __('reservations.actions.cancel') }}
                        </button>
                    @elseif ($target->value === 'rescheduled')
                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#rsvRescheduleModal">
                            <i class="feather icon-calendar"></i> {{ __('reservations.actions.reschedule') }}
                        </button>
                    @endif
                @endforeach
            </div>
        </div>
    @elseif ($status->isFinal())
        <div class="alert alert-secondary rsv-no-print">
            <i class="feather icon-lock"></i> {{ __('reservations.hints.final_status', ['status' => $status->label()]) }}
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            {{-- ═════════ الحجز ═════════ --}}
            <div class="rsv-card">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-info"></i> {{ __('reservations.ui.details') }}</h6>
                    <span class="d-flex gap-1">
                        <span class="rsv-badge" style="background: {{ $status->color() }}">{{ $status->label() }}</span>
                        @if ($invoice)
                            <span class="rsv-badge" style="background: #27ae60">{{ __('reservations.links.invoiced_badge') }}</span>
                        @endif
                    </span>
                </div>
                <div class="rsv-card__body">
                    <div class="row g-3">
                        @php
                            $rows = [
                                [__('reservations.columns.service'), $reservation->service_name ?: '—', 'briefcase'],
                                [__('reservations.columns.employee'), $reservation->employee?->full_name ?? '—', 'user-check'],
                                [__('reservations.columns.date'), $reservation->reservation_date?->format('Y-m-d') . ' — ' . substr((string) $reservation->start_time, 0, 5) . ' → ' . substr((string) $reservation->end_time, 0, 5), 'clock'],
                                [__('reservations.columns.created_by'), ($reservation->creator?->name ?? '—') . ' — ' . $reservation->created_at?->format('Y-m-d H:i'), 'user-plus'],
                            ];
                        @endphp

                        @foreach ($rows as [$label, $value, $icon])
                            <div class="col-md-6">
                                <span class="rsv-muted d-block"><i class="feather icon-{{ $icon }}"></i> {{ $label }}</span>
                                <strong>{{ $value }}</strong>
                            </div>
                        @endforeach

                        @if ($reservation->reschedule_count)
                            <div class="col-md-6">
                                <span class="rsv-muted d-block"><i class="feather icon-repeat"></i> {{ __('reservations.ui.reschedule_count') }}</span>
                                <strong>{{ $reservation->reschedule_count }}</strong>
                                @if ($reservation->rescheduled_from_date)
                                    <span class="rsv-hint">{{ __('reservations.ui.previous_slot', [
                                        'date' => $reservation->rescheduled_from_date->format('Y-m-d'),
                                        'time' => substr((string) $reservation->rescheduled_from_start, 0, 5),
                                    ]) }}</span>
                                @endif
                            </div>
                        @endif

                        @if ($reservation->cancellation_reason)
                            <div class="col-12">
                                <span class="rsv-muted d-block"><i class="feather icon-x-circle"></i> {{ __('reservations.attributes.cancel_reason') }}</span>
                                <strong>{{ $reservation->cancellation_reason }}</strong>
                            </div>
                        @endif

                        @if ($reservation->notes)
                            <div class="col-12">
                                <span class="rsv-muted d-block"><i class="feather icon-file-text"></i> {{ __('reservations.attributes.notes') }}</span>
                                {{ $reservation->notes }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═════════ العميل ═════════ --}}
            <div class="rsv-card">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-user"></i> {{ __('reservations.ui.client') }}</h6>
                    @if ($reservation->client_id && Route::has('clients.show'))
                        <a href="{{ route('clients.show', $reservation->client_id) }}" class="btn btn-sm btn-outline-secondary rsv-no-print">
                            <i class="feather icon-external-link"></i> {{ __('reservations.actions.open_client') }}
                        </a>
                    @endif
                </div>
                <div class="rsv-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="rsv-muted d-block">{{ __('reservations.columns.client') }}</span>
                            <strong>{{ $reservation->client_name ?: '—' }}</strong>
                            @if ($reservation->client?->code)
                                <span class="rsv-hint">{{ $reservation->client->code }}</span>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <span class="rsv-muted d-block">{{ __('reservations.columns.phone') }}</span>
                            <strong dir="ltr">{{ $reservation->client?->mobile ?: ($reservation->client?->phone ?: '—') }}</strong>
                            @if ($reservation->client?->email)
                                <span class="rsv-hint" dir="ltr">{{ $reservation->client->email }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═════════ خطّ الحالات ═════════ --}}
            <div class="rsv-card">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-activity"></i> {{ __('reservations.ui.timeline') }}</h6>
                </div>
                <div class="rsv-card__body">
                    <ul class="rsv-timeline">
                        @forelse ($reservation->statusHistories as $history)
                            @php $to = $history->toStatus(); @endphp
                            <li>
                                <div>
                                    @if ($history->from_status)
                                        <span class="rsv-muted">{{ $history->fromStatus()?->label() }} ←</span>
                                    @endif
                                    <span class="rsv-badge" style="background: {{ $to->color() }}">{{ $to->label() }}</span>
                                </div>
                                <div class="rsv-muted">
                                    {{ $history->created_at?->format('Y-m-d H:i') }} — {{ $history->user?->name ?? __('reservations.ui.system') }}
                                </div>
                                @if ($history->note)
                                    <div>{{ $history->note }}</div>
                                @endif
                            </li>
                        @empty
                            <li class="rsv-muted">{{ __('reservations.ui.no_history') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- ═════════ سجلّ النشاط ═════════ --}}
            <details class="rsv-card rsv-no-print">
                <summary class="rsv-card__head" style="cursor:pointer">
                    <h6 class="rsv-card__title"><i class="feather icon-list"></i> {{ __('reservations.ui.activity') }}</h6>
                    <span class="rsv-count">{{ $activity->count() }}</span>
                </summary>
                <div class="rsv-card__body">
                    <ul class="rsv-timeline">
                        @forelse ($activity as $log)
                            <li>
                                <div><i class="{{ $log->icon }}"></i> {{ $log->description }}</div>
                                <div class="rsv-muted">
                                    {{ $log->created_at?->format('Y-m-d H:i') }} — {{ $log->user?->name ?? __('reservations.ui.system') }}
                                </div>
                            </li>
                        @empty
                            <li class="rsv-muted">{{ __('reservations.ui.no_activity') }}</li>
                        @endforelse
                    </ul>
                </div>
            </details>
        </div>

        <div class="col-lg-4">
            {{-- ═════════ السعر ═════════ --}}
            <div class="rsv-card">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-tag"></i> {{ __('reservations.ui.amounts') }}</h6>
                    <span class="rsv-muted">{{ $reservation->currency }}</span>
                </div>
                <div class="rsv-card__body">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="rsv-muted">{{ __('reservations.columns.price') }}</span>
                        <span class="rsv-money">{{ $money($reservation->unit_price) }}</span>
                    </div>
                    @if ((float) $reservation->discount_amount > 0)
                        <div class="d-flex justify-content-between mb-1">
                            <span class="rsv-muted">{{ __('reservations.columns.discount') }}</span>
                            <span class="rsv-money text-danger">−{{ $money($reservation->discount_amount) }}</span>
                        </div>
                    @endif
                    @if ((float) $reservation->tax_amount > 0)
                        <div class="d-flex justify-content-between mb-1">
                            <span class="rsv-muted">{{ __('reservations.columns.tax') }} {{ $reservation->tax?->name ? '(' . $reservation->tax->name . ')' : '' }}</span>
                            <span class="rsv-money">{{ $money($reservation->tax_amount) }}</span>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between pt-2" style="border-top:1px solid var(--rsv-line)">
                        <strong>{{ __('reservations.columns.total') }}</strong>
                        <strong class="rsv-money">{{ $money($reservation->total) }}</strong>
                    </div>
                </div>
            </div>

            {{-- ═════════ الفاتورة — في المبيعات ═════════ --}}
            <div class="rsv-card">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-file-text"></i> {{ __('reservations.ui.invoice') }}</h6>
                </div>
                <div class="rsv-card__body">
                    @if ($invoice)
                        <div class="mb-2">
                            <span class="rsv-muted d-block">{{ __('reservations.ui.invoice_number') }}</span>
                            @if (Route::has('invoices.show'))
                                <a href="{{ route('invoices.show', $invoice->id) }}" class="fw-bold">{{ $invoice->code }}</a>
                            @else
                                <strong>{{ $invoice->code }}</strong>
                            @endif
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="rsv-muted">{{ __('reservations.columns.total') }}</span>
                            <span class="rsv-money">{{ $money($invoice->grand_total) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="rsv-muted">{{ __('reservations.ui.invoice_due') }}</span>
                            <span class="rsv-money {{ $invoiceDue > 0.009 ? 'text-danger' : 'text-success' }}">{{ $money($invoiceDue) }}</span>
                        </div>
                        @if ($invoiceDue > 0.009 && Route::has('paymentsClient.create'))
                            <a href="{{ route('paymentsClient.create', [$invoice->id, 'invoice']) }}" class="btn btn-sm btn-success w-100 rsv-no-print">
                                <i class="feather icon-plus-circle"></i> {{ __('reservations.actions.collect_on_invoice') }}
                            </a>
                        @endif
                    @else
                        <p class="rsv-muted mb-2">{{ __('reservations.ui.no_invoice') }}</p>
                        @if ($invoiceable)
                            <a href="{{ route('Reservations.toInvoice', $reservation->id) }}" class="btn btn-primary w-100 rsv-no-print">
                                <i class="feather icon-file-plus"></i> {{ __('reservations.actions.to_invoice') }}
                            </a>
                            <span class="rsv-hint mt-2 d-block">{{ __('reservations.hints.to_invoice') }}</span>
                        @elseif (hasPermission('reservations.create_invoice') && $reservation->invoiceBlockReason())
                            {{-- لماذا لا زرّ: مسودةٌ أو انتظارٌ لم يُؤكَّد، أو حالةٌ نهائية بلا خدمة --}}
                            <span class="rsv-hint d-block">{{ $reservation->invoiceBlockReason() }}</span>
                        @endif
                    @endif
                </div>
            </div>

            {{-- ═════════ الموعد — يُكتب في شاشة المواعيد عند التأكيد ═════════ --}}
            <div class="rsv-card">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-calendar"></i> {{ __('reservations.links.appointment') }}</h6>
                </div>
                <div class="rsv-card__body">
                    @if ($appointment)
                        <div class="d-flex justify-content-between mb-1">
                            <span class="rsv-muted">{{ __('reservations.columns.date') }}</span>
                            <span dir="ltr">{{ $appointment->appointment_date?->format('Y-m-d') }} {{ substr((string) $appointment->time, 0, 5) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="rsv-muted">{{ __('reservations.links.appointment_status') }}</span>
                            <strong>{{ $appointment->status_text }}</strong>
                        </div>
                        @if (Route::has('appointments.show'))
                            <a href="{{ route('appointments.show', $appointment->id) }}" class="btn btn-sm btn-outline-primary w-100 rsv-no-print">
                                <i class="feather icon-external-link"></i> {{ __('reservations.links.open_appointment') }}
                            </a>
                        @endif
                    @else
                        <p class="rsv-muted mb-0">{{ __('reservations.links.no_appointment') }}</p>
                    @endif
                </div>
            </div>

            {{-- ═════════ الإرسال للعميل ═════════ --}}
            <div class="rsv-card rsv-no-print">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-send"></i> {{ __('reservations.actions.send_client') }}</h6>
                </div>
                <div class="rsv-card__body d-grid gap-2">
                    @if ($sendLinks['whatsapp'])
                        <a href="{{ $sendLinks['whatsapp'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-success">
                            <i class="feather icon-message-circle"></i> {{ __('reservations.actions.send_whatsapp') }}
                        </a>
                    @endif
                    {{-- بريدٌ حقيقيٌّ من الخادم مع PDF — لا `mailto:` يفتح برنامجَ المستخدم --}}
                    @if (Route::has('Reservations.sendEmail'))
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rsvMailModal">
                            <i class="feather icon-mail"></i> {{ __('reservations.mail.action') }}
                        </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="rsvCopyText" data-text="{{ $sendLinks['text'] }}"
                            data-done="{{ __('reservations.send.copied') }}">
                        <i class="feather icon-copy"></i> {{ __('reservations.actions.copy_text') }}
                    </button>
                    @unless ($sendLinks['whatsapp'] || $sendLinks['email'])
                        <span class="rsv-hint">{{ __('reservations.send.no_contact') }}</span>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    @include('client::reservations.partials.modals')

    @if (Route::has('Reservations.sendEmail'))
        <div class="modal fade" id="rsvMailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('Reservations.sendEmail', $reservation->id) }}" class="modal-content" data-rsv-once>
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="feather icon-mail"></i> {{ __('reservations.mail.title', ['code' => $reservation->code]) }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('reservations.actions.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="rsvMailEmail">{{ __('reservations.mail.recipient') }} <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="rsvMailEmail" class="form-control" dir="ltr" required
                               value="{{ $reservation->client?->email }}" placeholder="name@example.com">
                        @unless ($reservation->client?->email)
                            <span class="rsv-hint text-warning">{{ __('reservations.mail.no_client_email') }}</span>
                        @endunless

                        <label class="form-label mt-3" for="rsvMailNote">{{ __('reservations.mail.note') }}</label>
                        <textarea name="note" id="rsvMailNote" rows="3" maxlength="1000" class="form-control"></textarea>
                        <span class="rsv-hint">{{ __('reservations.mail.attachment') }}</span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('reservations.mail.cancel') }}</button>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-send"></i> {{ __('reservations.mail.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script>
        (function () {
            const copy = document.getElementById('rsvCopyText');
            if (!copy) return;

            copy.addEventListener('click', () => {
                const done = () => {
                    const label = copy.innerHTML;
                    copy.textContent = copy.dataset.done;
                    setTimeout(() => { copy.innerHTML = label; }, 1500);
                };

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(copy.dataset.text).then(done);
                } else {
                    const area = document.createElement('textarea');
                    area.value = copy.dataset.text;
                    document.body.appendChild(area);
                    area.select();
                    document.execCommand('copy');
                    area.remove();
                    done();
                }
            });
        })();
    </script>
@endsection
