{{--
    عرض مرتجع الشراء.

    الشاشة واحدةٌ وشاشةَ «عرض فاتورة الشراء» بنيةً وأصنافاً: ترويسة `head`
    بشارات الحالة، ثم شبكة إجراءات، ثم بطاقة تبويبات (تفاصيل / بنود /
    مدفوعات / سجلّ النشاط) — وكلّها من نظام تصميم وحدة المشتريات نفسه.

    ما يأتي من الإعدادات:
      • العملة والكسور والرمز      ← «العملات»
      • صيغة التاريخ واتجاه الصفحة ← إعدادات اللغة النشطة
      • الألوان والخطّ             ← «مظهر النظام»
      • مبلغ ضريبة البند           ← «إعدادات الضريبة» عبر OrderLine
      • قائمة «المطبوعات»          ← «قوالب المستندات» + «طريقة الطباعة»

    لا زرّ تعديل: المرتجع لا يُعدَّل بعد إنشائه لأن قيده المحاسبي وحركة
    مخزونه كُتِبا — والفرق يُعالَج بإشعار مدين لا بتحرير مستندٍ مُقيَّد.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.purchase_return') }} — {{ $purchaseInvoiceReturn->code }}
@stop

@section('css')
    {{-- ورقة الخطّ الزمني التي تُصمِّم .timeline و.timeline-item --}}
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
    @include('purchases::partials.style')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        use App\Models\WarehousePermits;
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;
        use Modules\Purchases\Support\OrderLine;

        $items = $purchaseInvoiceReturn->items;
        $original = $purchaseInvoiceReturn->originalDocument;
        // مصدر الحقيقة لـ"هل اعتُمِد فعلياً؟" هو حالة الإذن المخزني نفسه لا
        // عمود receiving_status (تدوين معلوماتي فقط منذ فصل الاعتماد عن الحفظ)
        $isReceived = WarehousePermits::where('reference_type', 'purchase_return')
            ->where('reference_id', $purchaseInvoiceReturn->id)
            ->where('status', 'apporod')
            ->exists();

        // الإجماليات محفوظة في الجدول نفسه، فتُقرأ منه ولا يُعاد حسابها حتى
        // لا يختلف المعروض عمّا حُفظ وقُيِّد محاسبياً
        $totals = [
            'subtotal' => (float) ($purchaseInvoiceReturn->subtotal ?? 0),
            'discount' => (float) ($purchaseInvoiceReturn->total_discount ?? 0),
            'shipping' => (float) ($purchaseInvoiceReturn->shipping_cost ?? 0),
            'tax' => (float) ($purchaseInvoiceReturn->total_tax ?? 0),
            'grand_total' => (float) ($purchaseInvoiceReturn->grand_total ?? 0),
            // الإيداع والمسدَّد والمتبقّي تُملأ بعد قراءة السندات أدناه
        ];

        // لا عمود `paid_amount` في الجدول: المسترد مجموع سندات الدفع المرتبطة
        $paidAmount = (float) $purchaseInvoiceReturn->payments->sum('amount');
        $dueValue = (float) ($purchaseInvoiceReturn->due_value ?? 0);

        // صندوق الإجماليات يعرض هذه الثلاثة متى كان لها وجود فقط، فتُضاف
        // إليه بعد حسابها لا قبله (انظر `purchases::partials.totals`)
        $totals['deposit'] = (float) ($purchaseInvoiceReturn->advance_payment ?? 0);
        $totals['refund'] = $paidAmount;
        $totals['remaining'] = $dueValue;

        $paymentVariants = [
            'paid' => ['class' => 'po-status--approved', 'icon' => 'fa-check-circle', 'label' => __('purchase_cycle.invoice.paid')],
            'partial' => ['class' => 'po-status--review', 'icon' => 'fa-adjust', 'label' => __('purchase_cycle.invoice.partial')],
            'partially_paid' => ['class' => 'po-status--review', 'icon' => 'fa-adjust', 'label' => __('purchase_cycle.invoice.partial')],
            'unpaid' => ['class' => 'po-status--rejected', 'icon' => 'fa-times-circle', 'label' => __('purchase_cycle.invoice.unpaid')],
        ];

        $receivingVariants = [
            'received' => ['class' => 'po-status--approved', 'icon' => 'fa-box-open', 'label' => __('purchase_cycle.goods.returned')],
            'partial_received' => ['class' => 'po-status--review', 'icon' => 'fa-dolly', 'label' => __('purchase_cycle.goods.partially_returned')],
            'partially_receiv' => ['class' => 'po-status--review', 'icon' => 'fa-dolly', 'label' => __('purchase_cycle.goods.partially_returned')],
            'partially_received' => ['class' => 'po-status--review', 'icon' => 'fa-dolly', 'label' => __('purchase_cycle.goods.partially_returned')],
            'not_received' => ['class' => 'po-status--quotation', 'icon' => 'fa-clock', 'label' => __('purchase_cycle.goods.not_returned')],
        ];

        $payment = $paymentVariants[$purchaseInvoiceReturn->payment_status] ?? null;
        $receiving = $receivingVariants[$purchaseInvoiceReturn->receiving_status] ?? null;

        $statusBadges = '<span class="po-status po-status--quotation">'
            .'<i class="fas fa-undo"></i>'.e(__('purchase_cycle.invoice.return')).'</span>';

        foreach ([$payment, $receiving] as $badge) {
            if ($badge) {
                $statusBadges .= '<span class="po-status '.$badge['class'].'">'
                    .'<i class="fas '.$badge['icon'].'"></i>'.e($badge['label']).'</span>';
            }
        }
    @endphp

    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => __('purchase_cycle.documents.purchase_return').' — '.$purchaseInvoiceReturn->code,
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_return'), 'url' => route('ReturnsInvoice.index')],
                ['label' => $purchaseInvoiceReturn->code],
            ],
            'action' => $statusBadges,
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-returns.show',
            'title' => __('purchase_cycle.guide.return.show.title'),
            'lead' => __('purchase_cycle.guide.return.show.lead'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.return.source.title') => [
                    __('purchase_cycle.guide.return.source.a'),
                    __('purchase_cycle.guide.return.source.b'),
                ],
                __('purchase_cycle.guide.return.effect.title') => [
                    __('purchase_cycle.guide.return.effect.a'),
                    __('purchase_cycle.guide.return.effect.b'),
                    __('purchase_cycle.guide.return.effect.c'),
                ],
            ],
        ])


        {{-- الإجراءات --}}
        <div class="po-card">
            <div class="po-card__body">
                <div class="po-actions-grid">
                    @if (! $isReceived && hasPermission('purchases.returns.approve'))
                        <button type="button" class="btn btn-outline-success" onclick="confirmReceive()">
                            <i class="fas fa-check-double"></i><span>{{ __('purchase_cycle.goods.confirm') }}</span>
                        </button>
                    @endif

                    @permission('purchases.payments.create')
                        <a href="{{ route('PaymentSupplier.createPurchase', $purchaseInvoiceReturn->id) }}" class="btn btn-outline-primary">
                            <i class="fas fa-credit-card"></i><span>{{ __('purchase_cycle.invoice.add_payment') }}</span>
                        </a>
                    @endpermission

                    @permission('purchases.returns.export')
                        @include('purchases::partials.print-menu', [
                            'templates' => $documentTemplates ?? collect(),
                            'default' => $defaultTemplate ?? null,
                            'prefersPdf' => $prefersPdf ?? false,
                            'printRoute' => 'ReturnsInvoice.print',
                            'pdfRoute' => 'ReturnsInvoice.pdf',
                            'documentId' => $purchaseInvoiceReturn->id,
                        ])
                    @endpermission

                    {{-- الملاحظات --}}
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-paperclip"></i><span>{{ __('purchase_cycle.fields.notes') }}</span>
                        </button>
                        <ul class="dropdown-menu">
                            @permission('purchases.returns.edit')
                                <li>
                                    <a class="dropdown-item" href="#" onclick="addNote(); return false;">
                                        <i class="fas fa-plus me-2 text-success"></i>{{ __('purchase_cycle.invoice.add_note') }}
                                    </a>
                                </li>
                            @endpermission
                            @permission('purchases.returns.view')
                                <li>
                                    <a class="dropdown-item" href="#" onclick="viewAllNotes(); return false;">
                                        <i class="fas fa-list me-2 text-info"></i>{{ __('purchase_cycle.invoice.all_notes') }}
                                    </a>
                                </li>
                            @endpermission
                        </ul>
                    </div>

                    @if ($original)
                        <a href="{{ route('invoicePurchases.show', $original->id) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-file-invoice"></i><span>{{ __('purchase_cycle.return.source_invoice') }}</span>
                        </a>
                    @endif

                    @permission('purchases.returns.duplicate')
                        <a href="{{ route('ReturnsInvoice.copy', $purchaseInvoiceReturn->id) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-copy"></i><span>{{ __('purchase_cycle.actions.duplicate') }}</span>
                        </a>
                    @endpermission

                    @permission('purchases.returns.delete')
                        <button type="button" class="btn btn-outline-danger" onclick="deleteReturn()">
                            <i class="fas fa-trash-alt"></i><span>{{ __('purchase_cycle.actions.delete') }}</span>
                        </button>
                    @endpermission
                </div>
            </div>
        </div>

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
                    <a class="nav-link" data-bs-toggle="tab" href="#payments" role="tab">
                        <i class="fas fa-money-bill-wave me-1"></i>{{ __('purchase_cycle.invoice.payments') }}
                        <span class="po-status po-status--quotation ms-1">
                            {{ LanguageSettings::formatNumber($purchaseInvoiceReturn->payments->count(), 0) }}
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
                {{-- التفاصيل --}}
                <div class="tab-pane active" id="details" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.details', [
                            'details' => [
                                ['icon' => 'fa-hashtag', 'label' => __('purchase_cycle.fields.code'), 'value' => $purchaseInvoiceReturn->code],
                                ['icon' => 'fa-file-invoice', 'label' => __('purchase_cycle.return.source_invoice'), 'value' => $original->code ?? null],
                                ['icon' => 'fa-truck', 'label' => __('purchase_cycle.fields.supplier'), 'value' => $purchaseInvoiceReturn->supplier->trade_name ?? null],
                                ['icon' => 'fa-calendar', 'label' => __('purchase_cycle.fields.date'), 'value' => LanguageSettings::formatDate($purchaseInvoiceReturn->date ?? $purchaseInvoiceReturn->created_at)],
                                ['icon' => 'fa-box-open', 'label' => __('purchase_cycle.goods.status'), 'value' => $receiving['label'] ?? null],
                                ['icon' => 'fa-user', 'label' => __('purchase_cycle.fields.creator'), 'value' => $purchaseInvoiceReturn->creator->name ?? null],
                                ['icon' => 'fa-box', 'label' => __('purchase_cycle.fields.items_count'), 'value' => LanguageSettings::formatNumber($items->count(), 0)],
                                ['icon' => 'fa-undo-alt', 'label' => __('purchase_cycle.return.refund_amount'), 'value' => CurrencySettings::format($paidAmount)],
                                ['icon' => 'fa-hourglass-half', 'label' => __('purchase_cycle.invoice.due_value'), 'value' => CurrencySettings::format($dueValue)],
                                ['icon' => 'fa-coins', 'label' => __('purchase_cycle.fields.currency'), 'value' => CurrencySettings::name().' ('.CurrencySettings::symbol().')'],
                                ['icon' => 'fa-clock', 'label' => __('purchase_orders.fields.updated_at'), 'value' => LanguageSettings::formatDate($purchaseInvoiceReturn->updated_at, true)],
                            ],
                        ])

                        @if ($TaxsInvoice->isNotEmpty())
                            <hr>
                            <div class="po-detail__label">
                                <i class="fas fa-percentage"></i>{{ __('purchase_orders.items.tax') }}
                            </div>
                            <div class="table-responsive">
                                <table class="table modern-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('purchase_cycle.invoice.tax_type') }}</th>
                                            <th class="text-center">{{ __('purchase_orders.items.tax') }}</th>
                                            <th class="text-center">{{ __('purchase_cycle.invoice.amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($TaxsInvoice as $tax)
                                            <tr>
                                                <td>
                                                    {{ $tax->name }}
                                                    @if ($tax->type === 'included')
                                                        <span class="po-hint">— {{ __('purchase_orders.items.tax_included') }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-center num">{{ $tax->rate + 0 }}%</td>
                                                <td class="text-center num">{{ CurrencySettings::format($tax->value) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <hr>

                        <div class="po-detail__label">
                            <i class="fas fa-sticky-note"></i>{{ __('purchase_cycle.fields.notes') }}
                        </div>
                        <div class="po-detail__value">
                            {{ $purchaseInvoiceReturn->notes ?: __('purchase_orders.empty.no_notes') }}
                        </div>
                    </div>
                </div>

                {{-- البنود --}}
                <div class="tab-pane" id="items" role="tabpanel">
                    @if ($items->isEmpty())
                        <div class="po-empty">
                            <i class="fas fa-box-open"></i>
                            <h5>{{ __('purchase_orders.items.empty') }}</h5>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 46px;">{{ __('purchase_orders.items.row') }}</th>
                                        <th>{{ __('purchase_orders.items.item') }}</th>
                                        <th class="text-center">{{ __('purchase_orders.items.quantity') }}</th>
                                        <th class="text-center">{{ __('purchase_orders.items.unit_price') }}</th>
                                        <th class="text-center">{{ __('purchase_orders.items.discount') }}</th>
                                        <th class="text-center">{{ __('purchase_orders.items.tax') }}</th>
                                        <th class="text-center">{{ __('purchase_orders.items.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $item)
                                        <tr>
                                            <td class="text-center num">{{ LanguageSettings::formatNumber($loop->iteration, 0) }}</td>
                                            <td>
                                                {{ $item->product->name ?? $item->item }}
                                                @if ($item->unit_name)
                                                    <span class="po-hint">— {{ $item->unit_name }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center num">{{ LanguageSettings::formatNumber($item->quantity ?? 0, 0) }}</td>
                                            <td class="text-center num">{{ CurrencySettings::number($item->unit_price ?? 0) }}</td>
                                            <td class="text-center num">{{ CurrencySettings::number($item->discount ?? 0) }}</td>
                                            <td class="text-center num">
                                                {{-- المبلغ من «إعدادات الضريبة» لا من عمودٍ لا وجود له --}}
                                                {{ CurrencySettings::number(OrderLine::tax($item)) }}
                                                <span class="po-hint">
                                                    ({{ OrderLine::taxRate($item) + 0 }}%@if (OrderLine::isTaxIncluded($item))
                                                        — {{ __('purchase_orders.items.tax_included') }}
                                                    @endif)
                                                </span>
                                            </td>
                                            <td class="text-center num">{{ CurrencySettings::format($item->total ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @include('purchases::partials.totals', ['totals' => $totals])
                    @endif
                </div>

                {{-- المدفوعات --}}
                <div class="tab-pane" id="payments" role="tabpanel">
                    @if ($purchaseInvoiceReturn->payments->isEmpty())
                        <div class="po-empty">
                            <i class="fas fa-money-bill-wave"></i>
                            <h5>{{ __('purchase_cycle.invoice.no_payments') }}</h5>
                            @permission('purchases.payments.create')
                                <a href="{{ route('PaymentSupplier.createPurchase', $purchaseInvoiceReturn->id) }}" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i>{{ __('purchase_cycle.invoice.add_payment') }}
                                </a>
                            @endpermission
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 46px;">{{ __('purchase_orders.items.row') }}</th>
                                        <th class="text-center">{{ __('purchase_cycle.invoice.payment_date') }}</th>
                                        <th class="text-center">{{ __('purchase_cycle.invoice.amount') }}</th>
                                        <th>{{ __('purchase_cycle.invoice.payment_method') }}</th>
                                        <th>{{ __('purchase_cycle.fields.notes') }}</th>
                                        <th>{{ __('purchase_cycle.fields.creator') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseInvoiceReturn->payments as $payment)
                                        <tr>
                                            <td class="text-center num">{{ LanguageSettings::formatNumber($loop->iteration, 0) }}</td>
                                            <td class="text-center num">
                                                {{ LanguageSettings::formatDate($payment->payment_date ?? $payment->created_at) }}
                                            </td>
                                            <td class="text-center num">{{ CurrencySettings::format($payment->amount ?? 0) }}</td>
                                            <td>{{ $payment->Payment_method ?: __('purchase_cycle.empty.unknown') }}</td>
                                            <td>{{ $payment->notes ?: '—' }}</td>
                                            <td>{{ $payment->user->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- سجلّ النشاط --}}
                <div class="tab-pane" id="activity" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.activity', ['logs' => $logs])
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="deleteForm" action="{{ route('ReturnsInvoice.destroy', $purchaseInvoiceReturn->id) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <form id="receiveForm" action="{{ route('ReturnsInvoice.receive', $purchaseInvoiceReturn->id) }}" method="POST" class="d-none">
        @csrf
    </form>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        // `@json` لا يحتمل نداءً فيه فاصلة، فتُبنى المصفوفة هنا ثم تُمرَّر
        $returnShowConfig = [
            'routes' => [
                'addNote' => route('ReturnsInvoice.addNote', $purchaseInvoiceReturn->id),
                'getNotes' => route('ReturnsInvoice.getNotes', $purchaseInvoiceReturn->id),
                'deleteNote' => route('ReturnsInvoice.deleteNote', ['noteId' => '__ID__']),
            ],
            'i18n' => [
                'deleteTitle' => __('purchase_orders.confirm.delete_title'),
                'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => $purchaseInvoiceReturn->code]),
                'yesDelete' => __('purchase_orders.confirm.yes_delete'),
                'no' => __('purchase_orders.confirm.no'),
                'receiveTitle' => __('purchase_cycle.goods.confirm'),
                'receiveText' => __('purchase_cycle.goods.hint'),
                'yes' => __('purchase_orders.confirm.yes'),
                'addNote' => __('purchase_cycle.invoice.add_note'),
                'allNotes' => __('purchase_cycle.invoice.all_notes'),
                'notes' => __('purchase_cycle.fields.notes'),
                'save' => __('purchase_orders.actions.save'),
                'empty' => __('purchase_orders.empty.no_notes'),
                'failed' => __('purchase_cycle.messages.validation_failed'),
            ],
        ];
    @endphp

    <script>
        window.returnShow = @json($returnShowConfig);

        function deleteReturn() {
            Swal.fire({
                title: window.returnShow.i18n.deleteTitle,
                text: window.returnShow.i18n.deleteText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: window.returnShow.i18n.yesDelete,
                cancelButtonText: window.returnShow.i18n.no,
            }).then(function(result) {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        function confirmReceive() {
            Swal.fire({
                title: window.returnShow.i18n.receiveTitle,
                text: window.returnShow.i18n.receiveText,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: window.returnShow.i18n.yes,
                cancelButtonText: window.returnShow.i18n.no,
            }).then(function(result) {
                if (result.isConfirmed) {
                    document.getElementById('receiveForm').submit();
                }
            });
        }

        function addNote() {
            Swal.fire({
                title: window.returnShow.i18n.addNote,
                input: 'textarea',
                inputLabel: window.returnShow.i18n.notes,
                showCancelButton: true,
                confirmButtonText: window.returnShow.i18n.save,
                cancelButtonText: window.returnShow.i18n.no,
            }).then(function(result) {
                if (!result.isConfirmed || !result.value) {
                    return;
                }

                $.ajax({
                    url: window.returnShow.routes.addNote,
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        note: result.value,
                        content: result.value,
                    },
                    success: function() {
                        window.location.reload();
                    },
                    error: function() {
                        Swal.fire(window.returnShow.i18n.failed, '', 'error');
                    },
                });
            });
        }

        function viewAllNotes() {
            $.get(window.returnShow.routes.getNotes, function(response) {
                const notes = (response && response.notes) || [];

                const body = notes.length ?
                    notes.map(function(note) {
                        return '<div class="po-detail__value text-start mb-2">' +
                            '<strong>' + (note.user_name || note.user || '') + '</strong>' +
                            '<div>' + (note.content || note.note || '') + '</div>' +
                            '<small class="text-muted">' + (note.created_at || '') + '</small></div>';
                    }).join('') :
                    '<p class="text-muted mb-0">' + window.returnShow.i18n.empty + '</p>';

                Swal.fire({
                    title: window.returnShow.i18n.allNotes,
                    html: body,
                    width: 640,
                    confirmButtonText: window.returnShow.i18n.no,
                });
            }).fail(function() {
                Swal.fire(window.returnShow.i18n.failed, '', 'error');
            });
        }
    </script>
@endsection
