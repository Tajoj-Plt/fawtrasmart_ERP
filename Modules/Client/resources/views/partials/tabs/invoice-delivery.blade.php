{{-- تبويب «تسليم الفواتير» — بطاقاتٌ بتصميم «ملاحظات الزيارات» و«التبديلات»
     (visit_note_cards.css، ثلاثٌ في الصف)، لا جدولٌ بتنسيقٍ خاصٍّ به.

     كان التبويب جدولين فارغين يملؤهما `public/js/invoice-transfer-manager.js`
     بطلباتٍ بعد التحميل، والسكربت يكتب في عناصر إحصاءٍ لا وجود لها
     (`sent-pending-count`…) فينكسر، وقائمةُ مستلِميه لا تعرف المندوبين. صار
     كلُّه يُصيَّر هنا من الخادم، وتعيد العمليات تحميلَ الصفحة.

     قائمتا النافذة من {@see \App\Services\Clients\InvoiceTransferRules}:
     فواتيرُ هذا العميل التي في عهدة المستخدم، والموظفون على رأس العمل —
     وهما نفسُ ما يتحقّق منه `InvoiceTransferController::store()`. --}}
@permission('clients_invoice_transfer_view')
@php
    $itUser = auth()->user();
    $itRows = \App\Models\InvoiceTransfer::with(['invoice:id,code,due_value', 'fromEmployee:id,name', 'toEmployee:id,name'])
        ->where('client_id', $client->id)
        ->where(fn ($q) => $q->where('from_employee_id', $itUser->id)->orWhere('to_employee_id', $itUser->id))
        ->latest('id')
        ->get();

    // كلُّ زرٍّ بنفس صلاحية مساره في `Modules/Client/routes/web.php`
    $itCanSend = hasPermission('clients_invoice_transfer_send');
    $itCanRespond = hasPermission('clients_invoice_transfer_respond');
    $itCanCancel = hasPermission('clients_invoice_transfer_cancel');

    $itInvoices = $itCanSend
        ? \App\Services\Clients\InvoiceTransferRules::transferableInvoices($itUser)
            ->where('client_id', $client->id)
            ->latest('id')
            ->limit(100)
            ->get(['id', 'code', 'grand_total', 'due_value'])
        : collect();
    // موظفو فرع العميل فقط، ومن في خطّ سيره هذا العميلُ أوّلاً
    $itRecipients = $itCanSend
        ? \App\Services\Clients\InvoiceTransferRules::recipientsForClients($itUser, [(int) $client->id])->where('in_branch', true)->values()
        : collect();

    $itReasonRequired = \App\Helpers\ClientSettingsHelper::invoiceTransferReasonRequired();
    $itRequiresApproval = \App\Helpers\ClientSettingsHelper::invoiceTransferRequiresApproval();
    $itWaiting = $itRows->filter(fn ($t) => $t->isPending() && $t->responderId() === (int) $itUser->id)->count();

    $itCurrency = ($account_setting ?? null)?->currency;
    $itSymbol = empty($itCurrency) || $itCurrency === 'SAR'
        ? '<img src="' . asset('assets/images/Saudi_Riyal.svg') . '" alt="ريال سعودي" width="14">'
        : e($itCurrency);
    $itStatuses = [
        'pending' => ['label' => 'بانتظار الردّ', 'tone' => 'live', 'icon' => 'fa-clock'],
        'accepted' => ['label' => 'مقبول', 'tone' => 'done', 'icon' => 'fa-check-circle'],
        'rejected' => ['label' => 'مرفوض', 'tone' => 'cancel', 'icon' => 'fa-times-circle'],
    ];
@endphp
<div class="tab-pane" id="invoice-delivery" role="tabpanel">
    <div class="vn-wrap">
        <div class="vn-section-head d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="vn-section-title">
                <span class="vn-section-icon"><i class="fas fa-exchange-alt"></i></span>
                <div>
                    تسليم الفواتير
                    <span class="vn-section-count">
                        {{ $itRows->count() }} تحويل
                        @if ($itWaiting)
                            · {{ $itWaiting }} بانتظار ردّك
                        @endif
                    </span>
                </div>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                @if ($itRows->isNotEmpty())
                    <div class="btn-group btn-group-sm" role="group" aria-label="تصفية التحويلات">
                        <button type="button" class="btn btn-outline-primary active" data-it-filter="all">الكل</button>
                        <button type="button" class="btn btn-outline-primary" data-it-filter="in">الاستلام</button>
                        <button type="button" class="btn btn-outline-primary" data-it-filter="out">التسليم</button>
                    </div>
                @endif
                @if ($itCanSend)
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#itTransferModal">
                        <i class="fa fa-paper-plane me-1"></i>تسليم فاتورة
                    </button>
                @endif
                <a href="{{ route('invoice-transfers.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-list me-1"></i>كل تحويلاتي
                </a>
            </div>
        </div>

        @if ($itRows->isNotEmpty())
            <div class="vn-grid" id="vn-transfers-grid">
                @foreach ($itRows as $transfer)
                    @php
                        $itIncoming = (int) $transfer->to_employee_id === (int) $itUser->id;
                        $itStatus = $itStatuses[$transfer->status] ?? ['label' => 'غير معروف', 'tone' => 'muted', 'icon' => 'fa-question-circle'];
                        $itCode = $transfer->subject_label;
                        $itAt = $transfer->sent_at ?? $transfer->created_at;
                    @endphp
                    <article class="vn-card" data-it-dir="{{ $itIncoming ? 'in' : 'out' }}">
                        <header class="vn-head">
                            <span class="vn-num vn-num-{{ $itIncoming ? 'warning' : 'primary' }}">{{ $loop->iteration }}</span>
                            <div class="vn-who">
                                <div class="vn-title">
                                    <span class="vn-title-icon"><i class="fas fa-file-invoice"></i></span>
                                    @if ($transfer->invoice && ! $transfer->isBalance())
                                        <a href="{{ route('invoices.show', $transfer->invoice_id) }}" target="_blank" title="عرض الفاتورة"><bdi>{{ $itCode }}</bdi></a>
                                    @else
                                        <bdi>{{ $itCode }}</bdi>
                                    @endif
                                </div>
                                <div class="vn-sub">
                                    @if ($itIncoming && $transfer->isRequest())
                                        <span><i class="fas fa-arrow-down"></i>طلبتُ استلامها من {{ $transfer->fromEmployee->name ?? 'غير معروف' }}</span>
                                    @elseif ($itIncoming)
                                        <span><i class="fas fa-arrow-down"></i>استلام من {{ $transfer->fromEmployee->name ?? 'غير معروف' }}</span>
                                    @elseif ($transfer->isRequest())
                                        <span><i class="fas fa-arrow-up"></i>يطلب استلامها {{ $transfer->toEmployee->name ?? 'غير معروف' }}</span>
                                    @else
                                        <span><i class="fas fa-arrow-up"></i>تسليم إلى {{ $transfer->toEmployee->name ?? 'غير معروف' }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="vn-when">
                                @if ($itAt)
                                    <span><i class="fas fa-calendar-alt"></i>{{ $itAt->format('Y-m-d') }}</span>
                                    <span><i class="fas fa-clock"></i>{{ $itAt->format('h:i') }} {{ $itAt->format('A') === 'AM' ? 'ص' : 'م' }}</span>
                                @endif
                                <span class="vn-status vn-status-{{ $itStatus['tone'] }}">
                                    <i class="fas {{ $itStatus['icon'] }}"></i>{{ $itStatus['label'] }}
                                </span>
                            </div>
                        </header>

                        <div class="vn-note" title="{{ $transfer->sender_notes }}">
                            <p><span class="vn-note-label">سبب التسليم:</span> {{ $transfer->sender_notes ?: 'لا توجد ملاحظات' }}</p>
                            @if ($transfer->receiver_notes)
                                <p><span class="vn-note-label">ردّ المستلِم:</span> {{ $transfer->receiver_notes }}</p>
                            @endif
                            <span class="vn-note-icon"><i class="fas fa-comment-alt"></i></span>
                        </div>

                        <div class="vn-facts">
                            <div class="vn-fact">
                                <div class="vn-fact-label"><i class="fas fa-money-bill vn-ic-comp"></i> مبلغ الفاتورة</div>
                                <div class="vn-amount"><bdi dir="ltr">{{ number_format((float) $transfer->invoice_amount, 2) }}</bdi> {!! $itSymbol !!}</div>
                            </div>
                            <div class="vn-fact">
                                <div class="vn-fact-label"><i class="fas fa-hand-holding-usd vn-ic-doc"></i> المستحق الآن</div>
                                @if ($transfer->isBalance())
                                    <div class="vn-empty">تسليم رصيد — لا فاتورة</div>
                                @elseif ($transfer->invoice)
                                    <div class="vn-amount"><bdi dir="ltr">{{ number_format((float) $transfer->invoice->due_value, 2) }}</bdi> {!! $itSymbol !!}</div>
                                @else
                                    <div class="vn-empty">الفاتورة محذوفة</div>
                                @endif
                            </div>
                            <div class="vn-fact">
                                <div class="vn-fact-label"><i class="fas fa-reply vn-ic-custody"></i> تاريخ الردّ</div>
                                @if ($transfer->responded_at)
                                    <div class="vn-count"><b>{{ $transfer->responded_at->format('Y-m-d') }}</b></div>
                                @else
                                    <div class="vn-empty">لم يُردّ بعد</div>
                                @endif
                            </div>
                        </div>

                        <footer class="vn-foot">
                            <span class="vn-foot-meta">
                                @if ($itAt)
                                    <i class="fas fa-history"></i> {{ $itAt->diffForHumans() }}
                                @endif
                            </span>
                            <div class="vn-actions">
                                @if ($transfer->isPending() && $transfer->responderId() === (int) $itUser->id && $itCanRespond)
                                    <button type="button" class="vn-act it-respond"
                                        data-bs-toggle="modal" data-bs-target="#itRespondModal"
                                        data-id="{{ $transfer->id }}" data-mode="accept" data-code="{{ $itCode }}"
                                        data-request="{{ $transfer->isRequest() ? 1 : 0 }}" data-to="{{ $transfer->toEmployee->name ?? '' }}"
                                        title="قبول الاستلام"><i class="fas fa-check"></i></button>
                                    <button type="button" class="vn-act vn-act-danger it-respond"
                                        data-bs-toggle="modal" data-bs-target="#itRespondModal"
                                        data-id="{{ $transfer->id }}" data-mode="reject" data-code="{{ $itCode }}"
                                        data-request="{{ $transfer->isRequest() ? 1 : 0 }}" data-to="{{ $transfer->toEmployee->name ?? '' }}"
                                        title="رفض الاستلام"><i class="fas fa-times"></i></button>
                                @elseif ($transfer->isPending() && $transfer->initiatorId() === (int) $itUser->id && $itCanCancel)
                                    <button type="button" class="vn-act vn-act-danger it-cancel"
                                        data-id="{{ $transfer->id }}" data-code="{{ $itCode }}"
                                        title="إلغاء طلب التسليم"><i class="fas fa-undo"></i></button>
                                @endif
                            </div>
                        </footer>
                    </article>
                @endforeach
            </div>

            <div class="empty-state" data-it-none hidden>
                <i class="fa fa-filter"></i>
                <h5>لا شيء هنا</h5>
                <p>لا توجد تحويلات في هذا القسم لهذا العميل.</p>
            </div>
        @else
            <div class="empty-state">
                <i class="fa fa-exchange-alt"></i>
                <h5>لا توجد تحويلات</h5>
                <p>لم تُسلِّم فاتورةً لهذا العميل، ولم تصلك منه فاتورة.</p>
            </div>
        @endif
    </div>
</div>

@if ($itCanSend)
    <div class="modal fade" id="itTransferModal" tabindex="-1" aria-labelledby="itTransferModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="itTransferForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="itTransferModalLabel">
                            <i class="fa fa-paper-plane me-2"></i>تسليم فاتورة لموظف
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="itInvoice" class="form-label">الفاتورة <span class="text-danger">*</span></label>
                                <select id="itInvoice" name="invoice_id" class="form-control" required>
                                    @if ($itInvoices->isEmpty())
                                        <option value="">لا توجد فاتورة في عهدتك لهذا العميل</option>
                                    @else
                                        <option value="">— اختر فاتورة —</option>
                                        @foreach ($itInvoices as $invoice)
                                            <option value="{{ $invoice->id }}">
                                                {{ $invoice->code }} — {{ number_format((float) $invoice->grand_total, 2) }} (المستحق {{ number_format((float) $invoice->due_value, 2) }})
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                <small class="text-muted d-block mt-1">تظهر الفواتير التي أنت «مسؤول المبيعات» فيها فقط، وليس عليها طلبُ تسليمٍ معلّق.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="itRecipient" class="form-label">الموظف المستلِم <span class="text-danger">*</span></label>
                                <select id="itRecipient" name="to_employee_id" class="form-control" required>
                                    <option value="">— اختر موظفاً —</option>
                                    @foreach ($itRecipients->where('can_receive', true) as $recipient)
                                    <option value="{{ $recipient->id }}">{{ $recipient->name }}@if ($recipient->itinerary_date) — في خط سيره {{ $recipient->itinerary_date }}@endif</option>
                                @endforeach
                                </select>
                            @php $blockedRecipients = $itRecipients->where('can_receive', false); @endphp
                            @if ($blockedRecipients->isNotEmpty())
                                <details class="small text-muted mt-1">
                                    <summary style="cursor:pointer">{{ $blockedRecipients->count() }} موظفاً لا يظهرون لأن أدوارهم لا تملك صلاحية الاستلام</summary>
                                    {{ $blockedRecipients->pluck('name')->implode('، ') }}
                                </details>
                            @endif
                                <small class="text-muted d-block mt-1">
                                    الموظفون على رأس العمل.
                                    @if ($itRequiresApproval)
                                        المعطَّل منهم لا يملك دورُه صلاحية «قبول أو رفض تحويل وارد» فلن يصله الطلب.
                                    @endif
                                </small>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="itReason" class="form-label">
                                سبب التسليم @if ($itReasonRequired)<span class="text-danger">*</span>@endif
                            </label>
                            <textarea id="itReason" name="sender_notes" rows="3" maxlength="1000" class="form-control"
                                @if ($itReasonRequired) required @endif
                                placeholder="لماذا تُسلَّم هذه الفاتورة؟"></textarea>
                            <small class="text-muted d-block mt-1">
                                @if ($itRequiresApproval)
                                    تبقى الفاتورة باسمك حتى يقبلها المستلِم.
                                @else
                                    الاعتماد غير مطلوب في الإعدادات — تصير الفاتورة باسم المستلِم فور الإرسال.
                                @endif
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" @disabled($itInvoices->isEmpty())>
                            <i class="fa fa-paper-plane me-1"></i>إرسال
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($itCanRespond)
    <div class="modal fade" id="itRespondModal" tabindex="-1" aria-labelledby="itRespondTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="itRespondForm">
                    <input type="hidden" name="transfer_id">
                    <input type="hidden" name="mode">
                    <div class="modal-header">
                        <h5 class="modal-title" id="itRespondTitle">الردّ على طلب التسليم</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert" id="itRespondLead"></div>
                        <label for="itRespondNotes" class="form-label">
                            ملاحظات <span class="text-danger d-none" id="itRespondReq">*</span>
                        </label>
                        <textarea id="itRespondNotes" name="receiver_notes" rows="3" maxlength="1000" class="form-control"></textarea>
                        <small class="text-muted d-block mt-1" id="itRespondHint"></small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="itRespondSubmit">تأكيد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<script>
(function () {
    'use strict';

    var CSRF = @json(csrf_token());
    var REASON_REQUIRED = @json($itReasonRequired);
    var ROUTES = {
        store: @json(route('invoice-transfers.store')),
        accept: @json(route('invoice-transfers.accept', ['id' => '__ID__'])),
        reject: @json(route('invoice-transfers.reject', ['id' => '__ID__'])),
        cancel: @json(route('invoice-transfers.cancel', ['id' => '__ID__']))
    };

    function notify(type, message) {
        if (window.toastr) { toastr[type](message); return; }
        alert(message);
    }

    /* تُعاد الصفحة على هذا التبويب نفسه: الحالاتُ والعدّادات كلُّها من الخادم */
    function reload() {
        location.hash = 'invoice-delivery';
        location.reload();
    }

    function send(url, method, payload) {
        return fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF
            },
            body: payload ? JSON.stringify(payload) : null
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                return { ok: response.ok, body: body };
            });
        });
    }

    /* رسالةُ الخادم أوّلاً — رسائل التحقّق من لارافيل تأتي في `errors` */
    function errorOf(result) {
        if (result.body && result.body.errors) {
            var first = Object.values(result.body.errors)[0];
            if (first && first.length) { return first[0]; }
        }
        return (result.body && result.body.message) || 'تعذّر إتمام العملية';
    }

    function submitWith(button, url, method, payload) {
        if (button) { button.disabled = true; }

        send(url, method, payload).then(function (result) {
            if (button) { button.disabled = false; }

            if (result.ok && result.body.success) {
                notify('success', result.body.message || 'تم الحفظ');
                reload();
                return;
            }

            notify('error', errorOf(result));
        }).catch(function () {
            if (button) { button.disabled = false; }
            notify('error', 'تعذّر الاتصال بالخادم');
        });
    }

    // ═══════════ تصفية: الكل / الاستلام / التسليم ═══════════
    document.querySelectorAll('[data-it-filter]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var dir = chip.dataset.itFilter;
            var shown = 0;

            document.querySelectorAll('[data-it-filter]').forEach(function (other) {
                other.classList.toggle('active', other === chip);
            });

            document.querySelectorAll('#vn-transfers-grid [data-it-dir]').forEach(function (card) {
                var visible = dir === 'all' || card.dataset.itDir === dir;
                card.hidden = !visible;
                if (visible) { shown++; }
            });

            var none = document.querySelector('[data-it-none]');
            if (none) { none.hidden = shown > 0; }
        });
    });

    // ═══════════ تسليم فاتورة ═══════════
    var transferForm = document.getElementById('itTransferForm');

    if (transferForm) {
        transferForm.addEventListener('submit', function (event) {
            event.preventDefault();

            submitWith(transferForm.querySelector('button[type="submit"]'), ROUTES.store, 'POST', {
                invoice_id: transferForm.invoice_id.value,
                to_employee_id: transferForm.to_employee_id.value,
                sender_notes: transferForm.sender_notes.value
            });
        });
    }

    /* ═══════════ القبول والرفض ═══════════
       النافذة تُفتح تصريحياً بـ`data-bs-toggle` — مستمعُ بوتستراب مفوَّضٌ على
       المستند فيصله الحدث بعد مستمع الزرّ، فالحقول مملوءةٌ قبل العرض */
    document.querySelectorAll('.it-respond').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = document.getElementById('itRespondForm');
            if (!form) { return; }

            var reject = button.dataset.mode === 'reject';
            var lead = document.getElementById('itRespondLead');
            var submit = document.getElementById('itRespondSubmit');

            form.transfer_id.value = button.dataset.id;
            form.mode.value = button.dataset.mode;
            form.receiver_notes.value = '';
            form.receiver_notes.required = reject && REASON_REQUIRED;

            document.getElementById('itRespondTitle').textContent = reject ? 'رفض استلام الفاتورة' : 'قبول استلام الفاتورة';
            lead.className = 'alert ' + (reject ? 'alert-danger' : 'alert-success');
            // في طلب الاستلام يردّ صاحبُ العهدة: قبولُه تسليمٌ منه إلى الطالب
            lead.textContent = button.dataset.request === '1'
                ? (reject
                    ? 'سترفض طلب استلام الفاتورة ' + (button.dataset.code || '') + ' وتبقى في عهدتك.'
                    : 'ستنتقل الفاتورة ' + (button.dataset.code || '') + ' من عهدتك إلى ' + (button.dataset.to || 'الطالب') + '.')
                : (reject
                    ? 'سترفض استلام الفاتورة ' + (button.dataset.code || '') + ' وتبقى مع مرسِلها.'
                    : 'ستستلم الفاتورة ' + (button.dataset.code || '') + ' وتصير باسمك.');
            document.getElementById('itRespondReq').classList.toggle('d-none', !(reject && REASON_REQUIRED));
            document.getElementById('itRespondHint').textContent = reject && REASON_REQUIRED
                ? 'سببُ الرفض مطلوبٌ في إعدادات وحدات العملاء.'
                : 'ملاحظةٌ اختيارية تُسجَّل في سجلّ العميل.';

            submit.className = 'btn ' + (reject ? 'btn-danger' : 'btn-success');
            submit.textContent = reject ? 'تأكيد الرفض' : 'تأكيد القبول';
        });
    });

    var respondForm = document.getElementById('itRespondForm');

    if (respondForm) {
        respondForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var url = (respondForm.mode.value === 'reject' ? ROUTES.reject : ROUTES.accept)
                .replace('__ID__', respondForm.transfer_id.value);

            submitWith(document.getElementById('itRespondSubmit'), url, 'POST', {
                receiver_notes: respondForm.receiver_notes.value
            });
        });
    }

    // ═══════════ إلغاء طلبٍ مرسَل ═══════════
    document.querySelectorAll('.it-cancel').forEach(function (button) {
        button.addEventListener('click', function () {
            var run = function () {
                submitWith(button, ROUTES.cancel.replace('__ID__', button.dataset.id), 'DELETE', null);
            };
            var text = 'سيُحذف طلب تسليم الفاتورة ' + (button.dataset.code || '') + ' ويُخطَر المستلِم بالإلغاء.';

            if (window.Swal) {
                Swal.fire({
                    title: 'إلغاء طلب التسليم؟',
                    text: text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، ألغِ الطلب',
                    cancelButtonText: 'تراجع'
                }).then(function (choice) { if (choice.isConfirmed) { run(); } });
                return;
            }

            if (confirm(text)) { run(); }
        });
    });
})();
</script>
@endpermission
