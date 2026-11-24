@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.purchase_order_request') }} — {{ $purchaseOrdersRequests->code }}
@stop

@section('css')
    {{-- ورقة الخطّ الزمني التي تُصمِّم .timeline و.timeline-item --}}
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
    @include('purchases::partials.style')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $isReview = $purchaseOrdersRequests->status === 'Under Review';
        $isApproved = $purchaseOrdersRequests->status === 'approval';
        $isRejected = $purchaseOrdersRequests->status === 'disagree';
        $isClosed = $purchaseOrdersRequests->status === 'closed';
        $isCancelledOrder = $purchaseOrdersRequests->status === 'cancelled';

        // قيمة قديمة من نظام التحويل المباشر (قبل دورة الاستلام) — تبقى
        // للأوامر التي حُوِّلت قبل هذا التغيير، ولا يُنشئها كودٌ جديد
        $isConverted = $purchaseOrdersRequests->status === 'convert invoice';

        // دورة الاستلام: $fulfillment/$receivings/$relatedInvoices تصل من
        // PurchaseOrdersController::show() — PurchaseFulfillmentLedger هو
        // مصدرها الوحيد
        $fulfillment = $fulfillment ?? \Modules\Purchases\Support\PurchaseFulfillmentLedger::orderTotals($purchaseOrdersRequests);
        $receivings = $receivings ?? collect();
        $relatedInvoices = $relatedInvoices ?? collect();
        $canReceive = $isApproved && $fulfillment['remaining_to_receive'] > 0;
        $canClose = $isApproved && $purchaseOrdersRequests->receiving_status === 'received';

        $items = $purchaseOrdersRequests->items;

        // الإجماليات محفوظة في الجدول نفسه، فتُقرأ منه ولا يُعاد حسابها
        // حتى لا يختلف المعروض عمّا اعتُمد
        // العمودان الحقيقيان في `purchase_invoices` هما `total_discount` و
        // `total_tax` — لا `discount` ولا `tax` (لا وجود لهما أصلاً في
        // fillable الموديل)، فكان الصندوق يعرض صفراً دائماً مهما كان
        // المخزَّن فعلاً
        $totals = [
            'subtotal' => (float) ($purchaseOrdersRequests->subtotal ?? 0),
            'discount' => (float) ($purchaseOrdersRequests->total_discount ?? 0),
            'tax' => (float) ($purchaseOrdersRequests->total_tax ?? 0),
            'grand_total' => (float) ($purchaseOrdersRequests->grand_total ?? 0),
        ];
    @endphp

    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => __('purchase_cycle.documents.purchase_order_request') . ' — ' . $purchaseOrdersRequests->code,
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_order_request'), 'url' => route('PurchaseOrders.index')],
                ['label' => $purchaseOrdersRequests->code],
            ],
            'action' => view('purchases::partials.status', ['status' => $purchaseOrdersRequests->status])->render(),
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً هنا وحدها --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-order-requests.show',
            'title' => __('purchase_cycle.guide.order_request.show.title'),
            'lead' => __('purchase_cycle.guide.order_request.show.lead'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.order_request.show.actions.title') => [
                    __('purchase_cycle.guide.order_request.show.actions.a'),
                    __('purchase_cycle.guide.order_request.show.actions.b'),
                    __('purchase_cycle.guide.order_request.show.actions.c'),
                ],
                __('purchase_cycle.guide.order_request.statuses.title') => [
                    __('purchase_cycle.guide.order_request.statuses.a'),
                    __('purchase_cycle.guide.order_request.statuses.b'),
                    __('purchase_cycle.guide.order_request.statuses.c'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.order_request.facts.no_ledger_yet'),
                __('purchase_cycle.guide.order_request.facts.to_invoice'),
                __('purchase_cycle.guide.order_request.facts.final'),
            ],
        ])

        <div class="po-card">
            <div class="po-card__body">
                <div class="po-actions-grid">
                    @if ($isReview)
                        <a href="{{ route('PurchaseOrders.edit', $purchaseOrdersRequests->id) }}" class="btn btn-outline-primary">
                            <i class="fas fa-edit"></i><span>{{ __('purchase_cycle.actions.edit') }}</span>
                        </a>
                    @endif

                    @include('purchases::partials.print-menu', [
                        'templates' => $documentTemplates ?? collect(),
                        'default' => $defaultTemplate ?? null,
                        'prefersPdf' => $prefersPdf ?? false,
                        'printRoute' => 'PurchaseOrders.print',
                        'pdfRoute' => 'PurchaseOrders.pdf',
                        'documentId' => $purchaseOrdersRequests->id,
                    ])

                    {{-- الاعتمادُ والرفضُ بحارس صلاحيتهما: كانت الحالةُ
                         (`$isReview`) وحدَها شرطَ الظهور، بينما يشترط
                         `PurchaseOrdersController` صلاحيةَ
                         `purchases.order_requests.approve_reject` — فمن لا
                         يملكها يرى الزرَّين ويُردّ بـ403 عند الضغط. --}}
                    @if ($isReview)
                        @permission('purchases.order_requests.approve_reject')
                            <button type="button" class="btn btn-outline-success" id="approveBtn">
                                <i class="fas fa-check"></i><span>{{ __('purchase_orders.actions.approve') }}</span>
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="rejectBtn">
                                <i class="fas fa-times"></i><span>{{ __('purchase_orders.actions.reject') }}</span>
                            </button>
                        @endpermission
                    @elseif ($isApproved)
                        {{-- لم يعد التحويل إلى فاتورة متاحاً مباشرة من هنا:
                             يمرّ الأمر أولاً على استلام مشتريات، ومنه وحده
                             تُنشأ الفاتورة (PurchaseReceivingController). --}}
                        @if ($canReceive)
                            <a href="{{ route('PurchaseReceiving.create', ['order_id' => $purchaseOrdersRequests->id]) }}" class="btn btn-outline-info">
                                <i class="fas fa-dolly"></i><span>استلام</span>
                            </a>
                        @endif

                        @if ($canClose)
                            <form action="{{ route('PurchaseOrders.close', $purchaseOrdersRequests->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary" onclick="return confirm('إغلاق أمر الشراء بعد اكتمال استلامه؟');">
                                    <i class="fas fa-lock"></i><span>إغلاق الأمر</span>
                                </button>
                            </form>
                        @endif

                        @if ($fulfillment['received'] <= 0)
                            <form action="{{ route('PurchaseOrders.cancelOrder', $purchaseOrdersRequests->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('إلغاء أمر الشراء؟');">
                                    <i class="fas fa-ban"></i><span>إلغاء الأمر</span>
                                </button>
                            </form>
                        @endif
                    @elseif ($isRejected)
                        {{-- `undoRejection` يحرسها المتحكّم بنفس الصلاحية --}}
                        @permission('purchases.order_requests.approve_reject')
                            <button type="button" class="btn btn-outline-secondary" id="undoRejectionBtn">
                                <i class="fas fa-undo-alt"></i><span>{{ __('purchase_orders.actions.undo_rejection') }}</span>
                            </button>
                        @endpermission
                    @endif

                    @if ($receivings->isNotEmpty())
                        <a href="{{ route('PurchaseReceiving.index', ['order_id' => $purchaseOrdersRequests->id]) }}" class="btn btn-outline-primary">
                            <i class="fas fa-dolly-flatbed"></i><span>عرض الاستلامات ({{ $receivings->count() }})</span>
                        </a>
                    @endif

                    <button type="button" class="btn btn-outline-danger" id="deleteBtn">
                        <i class="fas fa-trash-alt"></i><span>{{ __('purchase_cycle.actions.delete') }}</span>
                    </button>
                </div>

                @if ($relatedInvoices->isNotEmpty())
                    <div class="mt-3">
                        <div class="po-detail__label"><i class="fas fa-file-invoice-dollar"></i> الفواتير الصادرة عن هذا الأمر</div>
                        <ul class="list-unstyled mt-2 mb-0">
                            @foreach ($relatedInvoices as $invoice)
                                <li class="mb-1">
                                    <a href="{{ route('invoicePurchases.show', $invoice->id) }}">
                                        فاتورة {{ $invoice->code }} — {{ \App\Support\LanguageSettings::formatNumber($invoice->grand_total) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($isConverted)
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        حُوِّل هذا الأمر مباشرةً إلى فاتورة قبل تفعيل دورة الاستلام — لا يمكن فتح استلامٍ جديد عليه.
                    </div>
                @endif
            </div>
        </div>

        {{-- دورة التنفيذ — كانت $fulfillment تُحسَب ولا تُعرَض: يقرؤها
             السطر أعلاه ليقرّر إتاحة زرّ الاستلام، ثمّ تُهمَل. فكان صاحب
             الأمر لا يرى كم استُلم منه ولا كم بقي إلا بفتح الاستلامات
             واحداً واحداً. --}}
        @if ($isApproved || $fulfillment['received'] > 0)
            <div class="po-card">
                <div class="po-card__head">
                    <h6 class="po-card__title">
                        <i class="fas fa-dolly"></i>دورة التنفيذ
                    </h6>
                    <span class="po-hint">الكمّيات بوحدة الشراء المسجَّلة في بنود الأمر</span>
                </div>
                <div class="po-card__body">
                    @include('purchases::partials.details', [
                        'details' => [
                            ['icon' => 'fa-list-ol', 'label' => 'المطلوب', 'value' => LanguageSettings::formatNumber($fulfillment['ordered'])],
                            ['icon' => 'fa-dolly-flatbed', 'label' => 'المستلم', 'value' => LanguageSettings::formatNumber($fulfillment['received'])],
                            ['icon' => 'fa-hourglass-half', 'label' => 'المتبقي للاستلام', 'value' => LanguageSettings::formatNumber($fulfillment['remaining_to_receive'])],
                            ['icon' => 'fa-file-invoice-dollar', 'label' => 'المفوتر', 'value' => LanguageSettings::formatNumber($fulfillment['invoiced'])],
                            ['icon' => 'fa-hourglass-end', 'label' => 'المتبقي للفوترة', 'value' => LanguageSettings::formatNumber($fulfillment['remaining_to_invoice'])],
                            ['icon' => 'fa-dolly', 'label' => 'عدد الاستلامات', 'value' => LanguageSettings::formatNumber($receivings->count(), 0)],
                        ],
                    ])
                </div>
            </div>
        @endif

        <div class="po-card">
            <ul class="nav po-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#details" role="tab">
                        <i class="fas fa-info-circle me-1"></i>{{ __('purchase_orders.tabs.details') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#items" role="tab">
                        <i class="fas fa-boxes me-1"></i>{{ __('purchase_orders.tabs.items') }}
                        <span class="po-status po-status--quotation ms-1">
                            {{ LanguageSettings::formatNumber($items->count(), 0) }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#activity" role="tab">
                        <i class="fas fa-history me-1"></i>{{ __('purchase_orders.tabs.activity') }}
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane active" id="details" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.details', [
                            'details' => [
                                ['icon' => 'fa-hashtag', 'label' => __('purchase_cycle.fields.code'), 'value' => $purchaseOrdersRequests->code],
                                ['icon' => 'fa-truck', 'label' => __('purchase_cycle.fields.supplier'), 'value' => $purchaseOrdersRequests->supplier->name ?? null],
                                ['icon' => 'fa-calendar', 'label' => __('purchase_cycle.fields.date'), 'value' => LanguageSettings::formatDate($purchaseOrdersRequests->date ?? $purchaseOrdersRequests->created_at)],
                                ['icon' => 'fa-calendar-alt', 'label' => __('purchase_cycle.fields.due_date'), 'value' => LanguageSettings::formatDate($purchaseOrdersRequests->due_date)],
                                ['icon' => 'fa-user', 'label' => __('purchase_cycle.fields.creator'), 'value' => $purchaseOrdersRequests->creator->name ?? null],
                                ['icon' => 'fa-box', 'label' => __('purchase_cycle.fields.items_count'), 'value' => LanguageSettings::formatNumber($items->count(), 0)],
                                ['icon' => 'fa-coins', 'label' => __('purchase_cycle.fields.currency'), 'value' => CurrencySettings::name().' ('.CurrencySettings::symbol().')'],
                                ['icon' => 'fa-clock', 'label' => __('purchase_orders.fields.updated_at'), 'value' => LanguageSettings::formatDate($purchaseOrdersRequests->updated_at, true)],
                            ],
                        ])

                        <hr>

                        <div class="po-detail__label">
                            <i class="fas fa-sticky-note"></i>{{ __('purchase_cycle.fields.notes') }}
                        </div>
                        <div class="po-detail__value">
                            {{ $purchaseOrdersRequests->notes ?: __('purchase_orders.empty.no_notes') }}
                        </div>
                    </div>
                </div>

                <div class="tab-pane" id="items" role="tabpanel">
                    @include('purchases::partials.items-table', ['items' => $items])
                    @if ($items->isNotEmpty())
                        @include('purchases::partials.totals', ['totals' => $totals])
                    @endif
                </div>

                <div class="tab-pane" id="activity" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.activity', ['logs' => $logs])
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="approveForm" action="{{ route('PurchaseOrders.approve', $purchaseOrdersRequests->id) }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="note">
    </form>
    <form id="rejectForm" action="{{ route('PurchaseOrders.reject', $purchaseOrdersRequests->id) }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="note">
    </form>
    <form id="cancelApprovalForm" action="{{ route('PurchaseOrders.cancelApproval', $purchaseOrdersRequests->id) }}" method="POST" class="d-none">
        @csrf
    </form>
    <form id="undoRejectionForm" action="{{ route('PurchaseOrders.undoRejection', $purchaseOrdersRequests->id) }}" method="POST" class="d-none">
        @csrf
    </form>
    <form id="deleteForm" action="{{ route('PurchaseOrders.destroy', $purchaseOrdersRequests->id) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@section('scripts')
    @php
        $poDialogs = [
            'approveTitle' => __('purchase_orders.confirm.approve_title'),
            'approveText' => __('purchase_orders.confirm.approve_text'),
            'approveNote' => __('purchase_orders.fields.approval_note'),
            'rejectTitle' => __('purchase_orders.confirm.reject_title'),
            'rejectText' => __('purchase_orders.confirm.reject_text'),
            'cancelApprovalTitle' => __('purchase_orders.actions.cancel_approval'),
            'cancelApprovalText' => __('purchase_orders.confirm.cancel_approval_text'),
            'undoRejectionTitle' => __('purchase_orders.actions.undo_rejection'),
            'undoRejectionText' => __('purchase_orders.confirm.undo_rejection_text'),
            'convertTitle' => __('purchase_cycle.actions.convert_to_invoice'),
            'convertText' => __('purchase_cycle.confirm.convert_text'),
            'deleteTitle' => __('purchase_orders.confirm.delete_title'),
            'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => $purchaseOrdersRequests->code]),
            'yes' => __('purchase_orders.confirm.yes'),
            'yesDelete' => __('purchase_orders.confirm.yes_delete'),
            'no' => __('purchase_orders.confirm.no'),
        ];
    @endphp

    <script>
        window.purchaseShowConfig = @json($poDialogs);
    </script>
    <script src="{{ asset('assets/js/purchase-cycle-show.js') }}"></script>
@endsection
