{{--
    تسليم واستلام فواتير العميل من مساحة الزيارة.

    يعرض فواتير هذا العميل التي عليها مستحقّ فقط — ما يُسلَّم في الزيارة
    عهدةُ تحصيل، والمسدَّدة لا تحصيل عليها — ولكلٍّ منها إجراؤه:

      في عهدتي          ← «تسليم» إلى موظفٍ آخر  (invoice-transfers.store)
      في عهدة غيري      ← يُعرض صاحبُها فقط — لا طلبَ لها (بطلب المستخدم 2026-09-26)
      عليها طلبٌ معلّق  ← قبول/رفض لمن ينتظر ردَّه، وإلغاءٌ لمن بدأه

    القوائم من {@see \App\Services\Clients\InvoiceTransferRules}، وهي نفسُ
    ما يتحقّق منه `InvoiceTransferController` — القائمةُ هنا عرضٌ لا قاعدة.
    كلُّ زرٍّ بصلاحية مساره، وكلُّ طلبٍ يحمل `visit_id` فيُعرف أنّه جرى في
    زيارة. وقبل «بدء الزيارة» يعطّل `wsApplyLock()` أزرارَ التبويب كغيره.

    التبويبُ والنوافذ خارج `.ws-tab-pane` الأخرى: النافذةُ داخل عنصرٍ مخفيّ
    (`d-none`) لا تُعرض.
--}}
@php
    $wtUser = auth()->user();
    $wtCanSend = hasPermission('clients_invoice_transfer_send');
    $wtCanRespond = hasPermission('clients_invoice_transfer_respond');
    $wtCanCancel = hasPermission('clients_invoice_transfer_cancel');

    $wtInvoices = \App\Services\Clients\InvoiceTransferRules::outstandingForClient($wtUser, (int) $client->id);
    // موظفو فرع العميل، ومن في خطّ سيره هذا العميلُ أوّلاً
    $wtAllRecipients = $wtCanSend
        ? \App\Services\Clients\InvoiceTransferRules::recipientsForClients($wtUser, [(int) $client->id])->where('in_branch', true)
        : collect();
    $wtRecipients = $wtAllRecipients->where('can_receive', true)->values();
    $wtNoPermission = $wtAllRecipients->where('can_receive', false)->pluck('name');
    $wtBranchId = \App\Services\Clients\InvoiceTransferRules::clientBranches([(int) $client->id])[$client->id] ?? null;
    $wtBranchName = $wtBranchId ? \App\Models\Branch::findCached($wtBranchId)?->name : null;

    // ① بانتظار استلامي لهذا العميل — صفٌّ لكلّ رصيدٍ أو فاتورة
    $wtAwaitingRows = \App\Models\InvoiceTransfer::with(['invoice:id,code,due_value', 'fromEmployee:id,name', 'toEmployee:id,name'])
        ->where('client_id', $client->id)
        ->where('status', 'pending')
        ->respondableBy($wtUser->id)
        ->latest('id')
        ->get();

    $wtMine = $wtInvoices->where('is_mine', true);
    $wtSendable = $wtMine->filter(fn ($invoice) => ! $invoice->pending)->values();

    // رصيدُ حساب العميل (بعد الإشعارات الدائنة) — ويُسلَّم إن كان رصيدُه في عهدتي
    $wtLedger = \App\Services\Clients\InvoiceTransferRules::ledgerBalances([(int) $client->id])[$client->id] ?? 0;
    $wtMyBalance = $wtCanSend ? \App\Services\Clients\InvoiceTransferRules::balanceClientsOf($wtUser, [(int) $client->id])->first() : null;
    $wtInvoiceDue = (float) $wtInvoices->sum('due_value');

    // ② لمن الرصيد الآن: تسليمٌ معلّق، أو آخرُ من استلمه، أو مسؤولُ العميل
    $wtBalancePending = \App\Models\InvoiceTransfer::with('toEmployee:id,name')
        ->where('client_id', $client->id)->where('kind', 'balance')->where('status', 'pending')->latest('id')->first();
    $wtBalanceHolder = \App\Models\InvoiceTransfer::with('toEmployee:id,name')
        ->where('client_id', $client->id)->where('kind', 'balance')->where('status', 'accepted')
        ->latest('responded_at')->latest('id')->first()?->toEmployee?->name
        ?? \App\Models\User::whereIn('employee_id', \Illuminate\Support\Facades\DB::table('client_employee')->where('client_id', $client->id)->pluck('employee_id'))
            ->orderBy('id')->value('name');

    $wtReasonRequired = \App\Helpers\ClientSettingsHelper::invoiceTransferReasonRequired();
    $wtRequiresApproval = \App\Helpers\ClientSettingsHelper::invoiceTransferRequiresApproval();
@endphp

<div class="ws-tab-pane d-none" data-pane="invoice-transfers">
    <div class="card ws-docs-card">
        <div class="card-header">
            <span><i class="fas fa-exchange-alt text-primary me-1"></i> تسليم واستلام — {{ $client->trade_name }}</span>
            <a href="{{ route('invoice-transfers.index', ['client_id' => $client->id]) }}" class="btn btn-sm btn-outline-secondary" data-ws-allow>
                <i class="fas fa-list me-1"></i> سجلّ تحويلات العميل
            </a>
        </div>
        <div class="card-body">

            {{-- ══ ① بانتظار استلامك ══
                 صفٌّ لكلّ ما سلّمه زميلٌ لي لهذا العميل: رصيدُ حسابه أو فاتورةٌ
                 بعينها، بمبلغه ومرسِله وسببه، وزرّا استلامٍ ورفضٍ له وحده —
                 و«استلام الكل» لهذا العميل وحده (`receive-all` بـ`client_id`). --}}
            <section class="ws-wt-sec {{ $wtAwaitingRows->isNotEmpty() ? 'is-alert' : '' }}">
                <header class="ws-wt-sec__head">
                    <h6><span class="ws-wt-num">١</span> بانتظار استلامك</h6>
                    @if ($wtCanRespond && $wtAwaitingRows->where('direction', '!=', 'request')->count() > 1)
                        <button type="button" class="btn btn-success btn-sm" id="wsWtReceiveAll">
                            <i class="fas fa-check-double me-1"></i> استلام الكل ({{ $wtAwaitingRows->where('direction', '!=', 'request')->count() }})
                        </button>
                    @endif
                </header>

                @if ($wtAwaitingRows->isEmpty())
                    <p class="ws-wt-none"><i class="fas fa-check-circle"></i> لا شيء سُلِّم لك لهذا العميل.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr><th>ما سُلِّم لك</th><th>من</th><th>المبلغ</th><th>السبب</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($wtAwaitingRows as $row)
                                    @php
                                        $rowSubject = $row->isBalance() ? 'رصيد حساب العميل' : 'الفاتورة '.$row->invoice_code;
                                        $rowAmount = $row->isBalance() ? (float) $row->invoice_amount : (float) ($row->invoice->due_value ?? 0);
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($row->isBalance())
                                                <span class="ws-wt-kind is-balance"><i class="fas fa-wallet"></i> رصيد الحساب</span>
                                            @else
                                                <span class="ws-wt-kind"><i class="fas fa-file-invoice"></i> فاتورة</span>
                                                <a href="{{ route('invoices.show', $row->invoice_id) }}" target="_blank" data-ws-allow><bdi>{{ $row->invoice_code }}</bdi></a>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $row->isRequest() ? ($row->toEmployee->name ?? '—').' (يطلبها منك)' : ($row->fromEmployee->name ?? '—') }}
                                            <small class="d-block text-muted">{{ ($row->sent_at ?? $row->created_at)?->locale('ar')->diffForHumans() }}</small>
                                        </td>
                                        <td>
                                            <strong><bdi dir="ltr">{{ number_format($rowAmount, 2) }}</bdi></strong>
                                            <small class="d-block text-muted">{{ $row->isBalance() ? 'الرصيد يوم التسليم' : 'مستحقّها الآن' }}</small>
                                        </td>
                                        <td class="text-muted">{{ $row->sender_notes ?: '—' }}</td>
                                        <td class="text-nowrap text-end">
                                            @if ($wtCanRespond)
                                                <button type="button" class="btn btn-sm btn-success ws-wt-respond"
                                                    data-bs-toggle="modal" data-bs-target="#wsWtRespondModal"
                                                    data-id="{{ $row->id }}" data-mode="accept" data-subject="{{ $rowSubject }}"
                                                    data-request="{{ $row->isRequest() ? 1 : 0 }}" data-to="{{ $row->toEmployee->name ?? '' }}">
                                                    <i class="fas fa-check"></i> {{ $row->isRequest() ? 'موافقة' : 'استلام' }}
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger ws-wt-respond"
                                                    data-bs-toggle="modal" data-bs-target="#wsWtRespondModal"
                                                    data-id="{{ $row->id }}" data-mode="reject" data-subject="{{ $rowSubject }}"
                                                    data-request="{{ $row->isRequest() ? 1 : 0 }}" data-to="{{ $row->toEmployee->name ?? '' }}">
                                                    <i class="fas fa-times"></i> رفض
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($wtAwaitingRows->contains(fn ($t) => $t->isBalance()))
                        <small class="text-muted d-block mt-1">استلامُ الرصيد يجعل رصيدَ العميل باسمك، وتنتقل معه فواتيرُ المرسِل المستحقّة لهذا العميل.</small>
                    @endif
                @endif
            </section>

            {{-- ══ ② رصيد العميل ══
                 الرقمُ الذي يُحصَّل: رصيدُ الحساب بعد الإشعارات الدائنة والسندات
                 والافتتاحي — ومعه مستحقُّ الفواتير والفرقُ بينهما، ولمن هو الآن. --}}
            <section class="ws-wt-sec">
                <header class="ws-wt-sec__head">
                    <h6><span class="ws-wt-num">٢</span> رصيد العميل</h6>
                    @if ($wtMyBalance)
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#wsWtBalanceModal">
                            <i class="fas fa-wallet me-1"></i> تسليم الرصيد لموظف
                        </button>
                    @endif
                </header>
                <div class="ws-wt-summary">
                    <div class="is-main"><span>رصيد حساب العميل</span><strong><bdi dir="ltr">{{ number_format((float) $wtLedger, 2) }}</bdi></strong></div>
                    <div><span>مستحقّ فواتيره</span><strong><bdi dir="ltr">{{ number_format((float) $wtInvoiceDue, 2) }}</bdi></strong></div>
                    @if (abs($wtInvoiceDue - $wtLedger) > 0.01)
                        <div class="is-warn"><span>الفرق</span><strong><bdi dir="ltr">{{ number_format(abs($wtInvoiceDue - $wtLedger), 2) }}</bdi></strong><small>إشعاراتٌ دائنة أو رصيدٌ افتتاحي</small></div>
                    @endif
                    <div>
                        <span>الرصيد في عهدة</span>
                        <strong>
                            @if ($wtBalancePending && (int) $wtBalancePending->to_employee_id === (int) $wtUser->id)
                                مسلَّمٌ إليك — استلمه من القسم ١
                            @elseif ($wtBalancePending)
                                مسلَّمٌ إلى {{ $wtBalancePending->toEmployee->name ?? '—' }} — بانتظار استلامه
                            @elseif ($wtMyBalance)
                                عهدتك
                            @else
                                {{ $wtBalanceHolder ?? 'غيرك' }}
                            @endif
                        </strong>
                    </div>
                </div>
            </section>

            {{-- ══ ③ فواتير العميل المستحقّة ══ --}}
            <section class="ws-wt-sec mb-0">
                <header class="ws-wt-sec__head">
                    <h6><span class="ws-wt-num">٣</span> فواتير العميل المستحقّة <small class="text-muted">({{ $wtInvoices->count() }})</small></h6>
                    @if ($wtCanSend && $wtSendable->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-outline-primary ws-wt-send"
                            data-bs-toggle="modal" data-bs-target="#wsWtSendModal" data-only="">
                            <i class="fas fa-paper-plane me-1"></i> تسليم فواتيري ({{ $wtSendable->count() }})
                        </button>
                    @endif
                </header>

                @if ($wtInvoices->isEmpty())
                    <p class="ws-wt-none"><i class="fas fa-check-circle"></i> لا فاتورةٌ عليها مستحقّ لهذا العميل.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 ws-wt-table">
                            <thead>
                                <tr><th>الفاتورة</th><th>التاريخ</th><th>الإجمالي</th><th>المستحق</th><th>في عهدة</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($wtInvoices as $invoice)
                                    @php
                                        $wtPending = $invoice->pending;
                                        $wtCancel = $wtPending && $wtCanCancel && $wtPending->initiatorId() === (int) $wtUser->id;
                                    @endphp
                                    <tr class="{{ $invoice->is_mine ? 'is-mine' : '' }}">
                                        <td><a href="{{ route('invoices.show', $invoice->id) }}" target="_blank" data-ws-allow><bdi>{{ $invoice->code }}</bdi></a></td>
                                        <td class="text-nowrap">{{ $invoice->invoice_date ? \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '—' }}</td>
                                        <td><bdi dir="ltr">{{ number_format((float) $invoice->grand_total, 2) }}</bdi></td>
                                        <td><strong><bdi dir="ltr">{{ number_format((float) $invoice->due_value, 2) }}</bdi></strong></td>
                                        <td>
                                            @if ($invoice->is_mine)
                                                <span class="badge bg-primary">عهدتك</span>
                                            @else
                                                {{ $invoice->holder ?? 'بلا مسؤول' }}
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-end">
                                            @if ($wtPending)
                                                {{-- ما ينتظر ردّي في القسم ① — هنا حالتُه فقط --}}
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-clock"></i>
                                                    {{ $wtPending->responderId() === (int) $wtUser->id ? 'بانتظار استلامك ↑' : 'مسلَّمة إلى '.($wtPending->toEmployee->name ?? '—') }}
                                                </span>
                                                @if ($wtCancel)
                                                    <button type="button" class="btn btn-sm btn-outline-danger ws-wt-cancel"
                                                        data-id="{{ $wtPending->id }}" data-code="{{ $invoice->code }}">
                                                        <i class="fas fa-undo"></i> إلغاء
                                                    </button>
                                                @endif
                                            @elseif ($invoice->is_mine && $wtCanSend)
                                                <button type="button" class="btn btn-sm btn-outline-primary ws-wt-send"
                                                    data-bs-toggle="modal" data-bs-target="#wsWtSendModal" data-only="{{ $invoice->id }}">
                                                    <i class="fas fa-paper-plane"></i> تسليم
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>

@if ($wtCanSend)
    <div class="modal fade" id="wsWtSendModal" tabindex="-1" aria-labelledby="wsWtSendTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="wsWtSendForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="wsWtSendTitle"><i class="fas fa-paper-plane me-1"></i> تسليم فواتير {{ $client->trade_name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label fw-bold">١ الفواتير المستحقّة في عهدتك</label>
                        <div class="ws-wt-checks mb-1">
                            @foreach ($wtSendable as $invoice)
                                <label class="ws-wt-check">
                                    <input type="checkbox" value="{{ $invoice->id }}" data-due="{{ (float) $invoice->due_value }}" checked>
                                    <span><bdi>{{ $invoice->code }}</bdi> · {{ $invoice->invoice_date ? \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '' }}</span>
                                    <b><bdi dir="ltr">{{ number_format((float) $invoice->due_value, 2) }}</bdi></b>
                                </label>
                            @endforeach
                        </div>
                        <p class="small text-muted mb-3" data-wt-count></p>

                        <label for="wsWtRecipient" class="form-label fw-bold">٢ الموظف المستلِم</label>
                        <p class="small text-muted mb-1">
                            موظفو {{ $wtBranchName ? 'فرع العميل ('.$wtBranchName.')' : 'المنشأة' }} فقط، ومن في خط سيره هذا العميل أولاً.
                        </p>
                        <select id="wsWtRecipient" name="to_employee_id" class="form-select mb-1" required>
                            @if ($wtRecipients->isEmpty())
                                <option value="">لا يوجد موظف من فرع العميل يملك صلاحية الاستلام</option>
                            @else
                                <option value="">— اختر موظفاً —</option>
                                @foreach ($wtRecipients as $recipient)
                                    <option value="{{ $recipient->id }}" data-name="{{ $recipient->name }}">
                                        {{ $recipient->name }}@if ($recipient->itinerary_date) — في خط سيره {{ $recipient->itinerary_date }}@endif
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        @if ($wtNoPermission->isNotEmpty())
                            <details class="small text-muted mb-2">
                                <summary style="cursor:pointer">{{ $wtNoPermission->count() }} من موظفي الفرع لا يظهرون لأن أدوارهم لا تملك صلاحية الاستلام</summary>
                                {{ $wtNoPermission->implode('، ') }}
                            </details>
                        @endif

                        <label for="wsWtSendReason" class="form-label fw-bold mt-2">٣ سبب التسليم @if ($wtReasonRequired)<span class="text-danger">*</span>@else<small class="text-muted fw-normal">(اختياري)</small>@endif</label>
                        <textarea id="wsWtSendReason" name="sender_notes" rows="2" maxlength="1000" class="form-control"
                            @if ($wtReasonRequired) required @endif placeholder="مثلاً: العميل في خط سير زميلي وسيتابع تحصيلها"></textarea>

                        <div class="alert alert-info mt-3 mb-0" data-wt-summary hidden></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" disabled><i class="fas fa-paper-plane me-1"></i> تسليم</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($wtMyBalance)
    <div class="modal fade" id="wsWtBalanceModal" tabindex="-1" aria-labelledby="wsWtBalanceTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="wsWtBalanceForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="wsWtBalanceTitle"><i class="fas fa-wallet me-1"></i> تسليم رصيد {{ $client->trade_name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            رصيد حساب العميل <b>{{ number_format($wtMyBalance['balance'], 2) }}</b>
                            @if (abs($wtMyBalance['balance'] - $wtMyBalance['invoice_due']) > 0.01)
                                <br><small>مستحق الفواتير {{ number_format($wtMyBalance['invoice_due'], 2) }} — الفرق إشعاراتٌ دائنة أو رصيدٌ افتتاحي، والرصيد هو ما يُحصَّل.</small>
                            @endif
                            <br><small>حين يستلمه الموظف تنتقل إليه معه فواتيرك المستحقّة لهذا العميل.</small>
                        </div>
                        <label for="wsWtBalanceRecipient" class="form-label fw-bold">الموظف المستلِم</label>
                        <p class="small text-muted mb-1">موظفو {{ $wtBranchName ? 'فرع العميل ('.$wtBranchName.')' : 'المنشأة' }} فقط، ومن في خط سيره هذا العميل أولاً.</p>
                        <select id="wsWtBalanceRecipient" name="to_employee_id" class="form-select mb-2" required>
                            <option value="">— اختر موظفاً —</option>
                            @foreach ($wtRecipients as $recipient)
                                <option value="{{ $recipient->id }}">{{ $recipient->name }}@if ($recipient->itinerary_date) — في خط سيره {{ $recipient->itinerary_date }}@endif</option>
                            @endforeach
                        </select>
                        <label for="wsWtBalanceReason" class="form-label fw-bold">سبب التسليم @if ($wtReasonRequired)<span class="text-danger">*</span>@else<small class="text-muted fw-normal">(اختياري)</small>@endif</label>
                        <textarea id="wsWtBalanceReason" name="sender_notes" rows="2" maxlength="1000" class="form-control" @if ($wtReasonRequired) required @endif></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-wallet me-1"></i> تسليم الرصيد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif


@if ($wtCanRespond)
    <div class="modal fade" id="wsWtRespondModal" tabindex="-1" aria-labelledby="wsWtRespondTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="wsWtRespondForm">
                    <input type="hidden" name="transfer_id">
                    <input type="hidden" name="batch">
                    <input type="hidden" name="mode">
                    <div class="modal-header">
                        <h5 class="modal-title" id="wsWtRespondTitle">الردّ على الطلب</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert" id="wsWtRespondLead"></div>
                        <label for="wsWtRespondNotes" class="form-label">ملاحظات <span class="text-danger d-none" id="wsWtRespondReq">*</span></label>
                        <textarea id="wsWtRespondNotes" name="receiver_notes" rows="3" maxlength="1000" class="form-control"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="wsWtRespondSubmit">تأكيد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<style>
    .visit-ws .ws-wt-sec { border: 1px solid var(--app-border, #e2e8f0); border-radius: 10px; padding: .85rem 1rem; margin-bottom: 1rem; }
    .visit-ws .ws-wt-sec.is-alert { border-color: color-mix(in srgb, var(--ws-warning, #d97706) 55%, transparent); border-inline-start: 4px solid var(--ws-warning, #d97706); background: color-mix(in srgb, var(--ws-warning, #d97706) 6%, transparent); }
    .visit-ws .ws-wt-sec__head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; flex-wrap: wrap; margin-bottom: .6rem; }
    .visit-ws .ws-wt-sec__head h6 { margin: 0; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
    .visit-ws .ws-wt-num { width: 24px; height: 24px; border-radius: 50%; display: inline-grid; place-items: center; background: var(--ws-primary, #4f46e5); color: #fff; font-size: .8rem; }
    .visit-ws .ws-wt-none { margin: 0; color: var(--app-muted, #64748b); }
    .visit-ws .ws-wt-none i { color: var(--ws-success, #16a34a); margin-inline-end: .35rem; }
    .visit-ws .ws-wt-kind { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .55rem; border-radius: 999px; font-size: .78rem; font-weight: 600; background: color-mix(in srgb, var(--ws-primary, #4f46e5) 12%, transparent); color: var(--ws-primary, #4f46e5); }
    .visit-ws .ws-wt-kind.is-balance { background: color-mix(in srgb, var(--ws-success, #16a34a) 14%, transparent); color: var(--ws-success, #16a34a); }
    .visit-ws .ws-wt-summary .is-main strong { font-size: 1.15rem; }
    .visit-ws .ws-wt-summary small { font-size: .7rem; color: var(--app-muted, #64748b); }
    #wsWtSendModal .ws-wt-checks { max-height: 240px; overflow-y: auto; border: 1px solid var(--app-border, #e2e8f0); border-radius: 8px; }
    #wsWtSendModal label.ws-wt-check { display: flex; align-items: center; gap: .6rem; padding: .5rem .75rem; margin: 0; border-bottom: 1px solid var(--app-border, #e2e8f0); cursor: pointer; }
    #wsWtSendModal label.ws-wt-check:last-child { border-bottom: 0; }
    #wsWtSendModal label.ws-wt-check span { flex: 1; }
    #wsWtSendModal label.ws-wt-check b { color: var(--app-danger, #dc2626); }
    #wsWtSendModal .form-select { display: block; width: 100%; }
</style>

<script>
(function () {
    'use strict';

    var CSRF = @json(csrf_token());
    var VISIT_ID = @json($visit->id);
    var REASON_REQUIRED = @json($wtReasonRequired);
    var ROUTES = {
        store: @json(route('invoice-transfers.store')),
        accept: @json(route('invoice-transfers.accept', ['id' => '__ID__'])),
        reject: @json(route('invoice-transfers.reject', ['id' => '__ID__'])),
        cancel: @json(route('invoice-transfers.cancel', ['id' => '__ID__'])),
        batch: @json(route('invoice-transfers.batch.respond', ['batch' => '__BATCH__'])),
        receiveAll: @json(route('invoice-transfers.receive-all')),
        balance: @json(route('invoice-transfers.balance'))
    };

    function notify(type, message) {
        if (window.toastr) { toastr[type](message); return; }
        alert(message);
    }

    /* يُعاد تحميل الزيارة على هذا التبويب نفسه — الحالاتُ كلُّها من الخادم */
    function reload() {
        location.hash = 'ws-invoice-transfers';
        location.reload();
    }

    function errorOf(body) {
        if (body && body.errors) {
            var first = Object.values(body.errors)[0];
            if (first && first.length) { return first[0]; }
        }
        return (body && body.message) || 'تعذّر إتمام العملية';
    }

    function submitWith(button, url, method, payload) {
        if (button) { button.disabled = true; }

        fetch(url, {
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
                if (button) { button.disabled = false; }
                if (response.ok && body.success) {
                    notify('success', body.message || 'تم الحفظ');
                    reload();
                    return;
                }
                notify('error', errorOf(body));
            });
        }).catch(function () {
            if (button) { button.disabled = false; }
            notify('error', 'تعذّر الاتصال بالخادم');
        });
    }

    /* تسليم دفعة: «تسليم كل فواتيري» يفتح النافذة والفواتير محدَّدةٌ كلُّها،
       وزرُّ الصفّ يفتحها على فاتورته وحدها — والمستلِم يستلمها معاً */
    var sendForm = document.getElementById('wsWtSendForm');
    if (sendForm) {
        var CLIENT_NAME = @json($client->trade_name);
        var boxes = sendForm.querySelectorAll('.ws-wt-checks input[type=checkbox]');
        var sendButton = sendForm.querySelector('button[type="submit"]');

        var refreshSend = function () {
            var picked = Array.prototype.filter.call(boxes, function (box) { return box.checked; });
            var due = picked.reduce(function (sum, box) { return sum + Number(box.dataset.due); }, 0);
            var dueText = due.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            var select = sendForm.to_employee_id;
            var who = select.value ? select.options[select.selectedIndex].dataset.name : '';

            sendForm.querySelector('[data-wt-count]').textContent = 'المحدَّد: ' + picked.length + ' من ' + boxes.length + ' — إجمالي المستحق ' + dueText;
            sendButton.disabled = !(picked.length && select.value);

            var summary = sendForm.querySelector('[data-wt-summary]');
            summary.hidden = !(picked.length && who);
            summary.textContent = 'ستُسلَّم ' + (picked.length === 1 ? 'فاتورة' : picked.length + ' فواتير') + ' للعميل ' + CLIENT_NAME
                + ' (المستحق ' + dueText + ') إلى ' + who + ' — يستلمها من «تحويلات الفواتير» أو حين يزور العميل.';
        };

        document.querySelectorAll('.ws-wt-send').forEach(function (button) {
            button.addEventListener('click', function () {
                sendForm.reset();
                boxes.forEach(function (box) { box.checked = !button.dataset.only || box.value === button.dataset.only; });
                refreshSend();
            });
        });
        sendForm.addEventListener('change', refreshSend);

        sendForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitWith(sendButton, ROUTES.store, 'POST', {
                invoice_ids: Array.prototype.filter.call(boxes, function (box) { return box.checked; }).map(function (box) { return box.value; }),
                to_employee_id: sendForm.to_employee_id.value,
                sender_notes: sendForm.sender_notes.value,
                visit_id: VISIT_ID
            });
        });
    }

    var balanceForm = document.getElementById('wsWtBalanceForm');
    if (balanceForm) {
        balanceForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitWith(balanceForm.querySelector('button[type="submit"]'), ROUTES.balance, 'POST', {
                client_ids: [@json($client->id)],
                to_employee_id: balanceForm.to_employee_id.value,
                sender_notes: balanceForm.sender_notes.value,
                visit_id: VISIT_ID
            });
        });
    }

    // «استلام الكل» لهذا العميل وحده
    var receiveAll = document.getElementById('wsWtReceiveAll');
    if (receiveAll) {
        receiveAll.addEventListener('click', function () {
            if (receiveAll.getAttribute('aria-disabled') === 'true') { return; }
            var run = function () { submitWith(receiveAll, ROUTES.receiveAll, 'POST', { client_id: @json($client->id) }); };
            var text = 'ستستلم كلَّ ما سُلِّم لك لهذا العميل ويصير باسمك.';
            if (window.Swal) {
                Swal.fire({ title: 'استلام الكل؟', text: text, icon: 'question', showCancelButton: true, confirmButtonText: 'نعم، استلم', cancelButtonText: 'تراجع' })
                    .then(function (choice) { if (choice.isConfirmed) { run(); } });
                return;
            }
            if (confirm(text)) { run(); }
        });
    }

    document.querySelectorAll('.ws-wt-respond').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = document.getElementById('wsWtRespondForm');
            if (!form) { return; }

            var reject = button.dataset.mode === 'reject';
            var isRequest = button.dataset.request === '1';
            var code = button.dataset.code || '';
            var lead = document.getElementById('wsWtRespondLead');
            var submit = document.getElementById('wsWtRespondSubmit');

            form.transfer_id.value = button.dataset.id || '';
            form.batch.value = button.dataset.batch || '';
            form.mode.value = button.dataset.mode;
            form.receiver_notes.value = '';
            form.receiver_notes.required = reject && REASON_REQUIRED;
            document.getElementById('wsWtRespondReq').classList.toggle('d-none', !(reject && REASON_REQUIRED));

            document.getElementById('wsWtRespondTitle').textContent = isRequest
                ? (reject ? 'رفض طلب الاستلام' : 'الموافقة على طلب الاستلام')
                : (reject ? 'رفض استلام الفاتورة' : 'استلام الفاتورة');
            lead.className = 'alert ' + (reject ? 'alert-danger' : 'alert-success');
            var subject = button.dataset.subject || ('الفاتورة ' + code);
            lead.textContent = isRequest
                ? (reject
                    ? 'سترفض طلب استلام ' + subject + ' — تبقى في عهدتك.'
                    : 'ستنتقل عهدة ' + subject + ' منك إلى ' + (button.dataset.to || 'الطالب') + '.')
                : (reject
                    ? 'سترفض استلام ' + subject + ' — تبقى العهدة مع المرسِل، وتُكتب ملاحظةٌ على العميل.'
                    : 'ستستلم ' + subject + ' ويصير باسمك.');

            submit.className = 'btn ' + (reject ? 'btn-danger' : 'btn-success');
            submit.textContent = reject ? 'تأكيد الرفض' : 'تأكيد';
        });
    });

    var respondForm = document.getElementById('wsWtRespondForm');
    if (respondForm) {
        respondForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var url = respondForm.batch.value
                ? ROUTES.batch.replace('__BATCH__', respondForm.batch.value)
                : (respondForm.mode.value === 'reject' ? ROUTES.reject : ROUTES.accept)
                    .replace('__ID__', respondForm.transfer_id.value);
            submitWith(document.getElementById('wsWtRespondSubmit'), url, 'POST', {
                receiver_notes: respondForm.receiver_notes.value,
                mode: respondForm.mode.value,
                // هذا العميلُ وحده من الدفعة — لا يُستلم في زيارته ما يخصّ عملاء آخرين
                client_id: @json($client->id)
            });
        });
    }

    document.querySelectorAll('.ws-wt-cancel').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.getAttribute('aria-disabled') === 'true') { return; }
            var text = 'سيُلغى طلب الفاتورة ' + (button.dataset.code || '') + ' ويُخطَر الطرف الآخر.';
            var run = function () {
                submitWith(button, ROUTES.cancel.replace('__ID__', button.dataset.id), 'DELETE', null);
            };

            if (window.Swal) {
                Swal.fire({
                    title: 'إلغاء الطلب؟', text: text, icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'نعم، ألغِ الطلب', cancelButtonText: 'تراجع'
                }).then(function (choice) { if (choice.isConfirmed) { run(); } });
                return;
            }

            if (confirm(text)) { run(); }
        });
    });
})();
</script>
