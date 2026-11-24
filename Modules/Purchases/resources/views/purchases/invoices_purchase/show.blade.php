{{--
    عرض فاتورة الشراء.

    أُعيد بناء الشاشة على نظام تصميم وحدة المشتريات (head / details /
    items-table / totals / activity) بدل ورقة أنماط داخل الملف ومعاينة ورقية
    مكتوب فيها اسم المنشأة وعنوانها وهاتفها نصّاً ثابتاً.

    ما يأتي من الإعدادات:
      • العملة والكسور والرمز ← «العملات»
      • صيغة التاريخ واتجاه الصفحة ← إعدادات اللغة النشطة
      • الألوان والخطّ ← «مظهر النظام»
      • مبلغ ضريبة البند ← «إعدادات الضريبة» عبر OrderLine

    المعاينة الورقية حُذفت: القوالب الحقيقية في قائمة «المطبوعات» تؤدّيها
    وتقرأ بيانات المنشأة من إعداداتها لا من نصّ مكتوب في الشاشة.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.purchase_invoice') }} — {{ $purchaseInvoice->code }}
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
        use Modules\Purchases\Support\OrderLine;

        $items = $purchaseInvoice->items;
        $isInvoice = $purchaseInvoice->type === 'invoice';
        $isReceived = $purchaseInvoice->receiving_status === 'received';

        // الإجماليات محفوظة في الجدول نفسه، فتُقرأ منه ولا يُعاد حسابها حتى
        // لا يختلف المعروض عمّا حُفظ وقُيِّد محاسبياً
        $totals = [
            'subtotal' => (float) ($purchaseInvoice->subtotal ?? 0),
            'discount' => (float) ($purchaseInvoice->total_discount ?? 0),
            'shipping' => (float) ($purchaseInvoice->shipping_cost ?? 0),
            'tax' => (float) ($purchaseInvoice->total_tax ?? 0),
            'grand_total' => (float) ($purchaseInvoice->grand_total ?? 0),
            // الإيداع والمسدَّد والمتبقّي تُملأ بعد قراءة السندات أدناه
        ];

        // لا عمود `paid_amount` في الجدول: المدفوع مجموع سندات الدفع المرتبطة
        $paidAmount = (float) $purchaseInvoice->payments->sum('amount');
        $dueValue = (float) ($purchaseInvoice->due_value ?? 0);

        // صندوق الإجماليات يعرض هذه الثلاثة متى كان لها وجود فقط، فتُضاف
        // إليه بعد حسابها لا قبله (انظر `purchases::partials.totals`)
        $totals['deposit'] = (float) ($purchaseInvoice->advance_payment ?? 0);
        $totals['paid'] = $paidAmount;
        $totals['remaining'] = $dueValue;

        // اسم العمود في `payments_process` بحرف كبير — وقراءته بحرف صغير
        // تعيد null، فتخرج الخانة فارغة

        $paymentVariants = [
            'paid' => ['class' => 'po-status--approved', 'icon' => 'fa-check-circle', 'label' => __('purchase_cycle.invoice.paid')],
            'partial' => ['class' => 'po-status--review', 'icon' => 'fa-adjust', 'label' => __('purchase_cycle.invoice.partial')],
            'partially_paid' => ['class' => 'po-status--review', 'icon' => 'fa-adjust', 'label' => __('purchase_cycle.invoice.partial')],
            'unpaid' => ['class' => 'po-status--rejected', 'icon' => 'fa-times-circle', 'label' => __('purchase_cycle.invoice.unpaid')],
        ];

        $receivingVariants = [
            'received' => ['class' => 'po-status--approved', 'icon' => 'fa-box-open', 'label' => __('purchase_cycle.invoice.received')],
            'partial_received' => ['class' => 'po-status--review', 'icon' => 'fa-dolly', 'label' => __('purchase_cycle.invoice.partially_received')],
            'partially_receiv' => ['class' => 'po-status--review', 'icon' => 'fa-dolly', 'label' => __('purchase_cycle.invoice.partially_received')],
            'not_received' => ['class' => 'po-status--quotation', 'icon' => 'fa-clock', 'label' => __('purchase_cycle.invoice.not_received')],
        ];

        $payment = $paymentVariants[$purchaseInvoice->payment_status] ?? null;
        $receiving = $receivingVariants[$purchaseInvoice->receiving_status] ?? null;

        $statusBadges = '';
        foreach ([$payment, $receiving] as $badge) {
            if ($badge) {
                $statusBadges .= '<span class="po-status '.$badge['class'].'">'
                    .'<i class="fas '.$badge['icon'].'"></i>'.e($badge['label']).'</span>';
            }
        }

    @endphp

    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => __('purchase_cycle.documents.purchase_invoice').' — '.$purchaseInvoice->code,
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_invoice'), 'url' => route('invoicePurchases.index')],
                ['label' => $purchaseInvoice->code],
            ],
            'action' => $statusBadges,
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً هنا وحدها: من يفتح فاتورةً
             بعينها جاء لبياناتها لا للشرح، بخلاف شاشتي الفهرسة والإنشاء --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-invoices.show',
            'title' => __('purchase_cycle.guide.show.title'),
            'lead' => __('purchase_cycle.guide.show.lead'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.show.actions.title') => [
                    __('purchase_cycle.guide.show.actions.a'),
                    __('purchase_cycle.guide.show.actions.b'),
                    __('purchase_cycle.guide.show.actions.c'),
                ],
                __('purchase_cycle.guide.money_flow.title') => [
                    __('purchase_cycle.guide.money_flow.a'),
                    __('purchase_cycle.guide.money_flow.b'),
                    __('purchase_cycle.guide.money_flow.c'),
                ],
                __('purchase_cycle.guide.form.noedit.title') => [
                    __('purchase_cycle.guide.form.noedit.a'),
                    __('purchase_cycle.guide.form.noedit.b'),
                ],
            ],
        ])

        {{-- الإجراءات --}}
        <div class="po-card">
            <div class="po-card__body">
                <div class="po-actions-grid">
                    @permission('purchases.invoices.edit')
                        <a href="{{ route('invoicePurchases.edit', $purchaseInvoice->id) }}" class="btn btn-outline-primary">
                            <i class="fas fa-edit"></i><span>{{ __('purchase_cycle.actions.edit') }}</span>
                        </a>
                    @endpermission

                    @if (! $isReceived && hasPermission('purchases.invoices.approve'))
                        <button type="button" class="btn btn-outline-success" onclick="confirmReceive()">
                            <i class="fas fa-check-double"></i><span>{{ __('purchase_cycle.invoice.receive') }}</span>
                        </button>
                    @endif

                    {{-- الفاتورة المرتجعة لا تُسدَّد: المرتجع عكس أثرها في حساب
                         المورّد، فالدفع بعده يدفع ثمن بضاعةٍ رُدَّت --}}
                    @if (empty($existingReturn) && ! $purchaseInvoice->is_paid)
                        <a href="{{ route('PaymentSupplier.createPurchase', $purchaseInvoice->id) }}" class="btn btn-outline-primary">
                            <i class="fas fa-credit-card"></i><span>{{ __('purchase_cycle.invoice.add_payment') }}</span>
                        </a>
                    @endif

                    @permission('purchases.invoices.export')
                        @include('purchases::partials.print-menu', [
                            'templates' => $documentTemplates ?? collect(),
                            'default' => $defaultTemplate ?? null,
                            'prefersPdf' => $prefersPdf ?? false,
                            'printRoute' => 'invoicePurchases.printView',
                            'pdfRoute' => 'invoicePurchases.pdf',
                            'documentId' => $purchaseInvoice->id,
                        ])

                        {{-- مخرجات إضافية لا تمرّ بالقوالب --}}
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-file-export"></i><span>{{ __('purchase_cycle.actions.options') }}</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="{{ route('exportExcel', $purchaseInvoice->id) }}">
                                        <i class="fas fa-file-excel me-2 text-success"></i>Excel
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('invoicePurchases.notesPdf', $purchaseInvoice->id) }}"
                                        target="_blank" rel="noopener">
                                        <i class="fas fa-sticky-note me-2 text-secondary"></i>{{ __('purchase_cycle.invoice.notes_pdf') }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    @endpermission

                    {{-- الملاحظات --}}
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-paperclip"></i><span>{{ __('purchase_cycle.fields.notes') }}</span>
                        </button>
                        <ul class="dropdown-menu">
                            @permission('purchases.invoices.edit')
                                <li>
                                    <a class="dropdown-item" href="#" onclick="addNoteOrAttachment(); return false;">
                                        <i class="fas fa-plus me-2 text-success"></i>{{ __('purchase_cycle.invoice.add_note') }}
                                    </a>
                                </li>
                            @endpermission
                            @permission('purchases.invoices.view')
                                <li>
                                    <a class="dropdown-item" href="#" onclick="viewAllNotes(); return false;">
                                        <i class="fas fa-list me-2 text-info"></i>{{ __('purchase_cycle.invoice.all_notes') }}
                                    </a>
                                </li>
                            @endpermission
                        </ul>
                    </div>

                    @if ($isInvoice)
                        {{-- الفاتورة الواحدة لا تُردّ مرتين: متى وُجد لها مرتجع
                             حلّ محلَّ القائمة رابطٌ إليه، فلا يقود الزرُّ إلى
                             شاشةٍ سترفض الطلب. والإشعار المدين يسقط معه لأن
                             أثر الفاتورة عُكِس بالمرتجع أصلاً. --}}
                        @if (! empty($existingReturn))
                            <a href="{{ route('ReturnsInvoice.show', $existingReturn->id) }}" class="btn btn-outline-warning">
                                <i class="fas fa-undo"></i>
                                <span>{{ __('purchase_cycle.return.has_return') }} — {{ $existingReturn->code }}</span>
                            </a>
                        @else
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-undo"></i><span>{{ __('purchase_cycle.invoice.return') }}</span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('ReturnsInvoice.createFromInvoice', $purchaseInvoice->id) }}">
                                            <i class="fas fa-plus me-2 text-success"></i>{{ __('purchase_cycle.invoice.create_return') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('CreditNotes.createFromInvoice', $purchaseInvoice->id) }}">
                                            <i class="fas fa-file-invoice me-2 text-info"></i>{{ __('purchase_cycle.invoice.create_debit_note') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        @endif
                    @endif

                    {{-- النسخُ إنشاءٌ لا تعديل — بنفس تصنيفه في المتحكّم والمسار --}}
                    @permission('purchases.invoices.duplicate')
                        <button type="button" class="btn btn-outline-secondary" onclick="copyInvoice()">
                            <i class="fas fa-copy"></i><span>{{ __('purchase_cycle.actions.duplicate') }}</span>
                        </button>
                    @endpermission

                    @permission('purchases.invoices.edit')
                        <button type="button" class="btn btn-outline-secondary" onclick="assignCostCenter()">
                            <i class="fas fa-building"></i><span>{{ __('purchase_cycle.invoice.cost_center') }}</span>
                        </button>
                    @endpermission

                    @permission('purchases.invoices.delete')
                        <button type="button" class="btn btn-outline-danger" onclick="deleteInvoice()">
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
                            {{ LanguageSettings::formatNumber($purchaseInvoice->payments->count(), 0) }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#notes" role="tab">
                        <i class="fas fa-sticky-note me-1"></i>{{ __('purchase_orders.tabs.notes') }}
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
                                ['icon' => 'fa-hashtag', 'label' => __('purchase_cycle.fields.code'), 'value' => $purchaseInvoice->code],
                                ['icon' => 'fa-truck', 'label' => __('purchase_cycle.fields.supplier'), 'value' => $purchaseInvoice->supplier->trade_name ?? null],
                                ['icon' => 'fa-calendar', 'label' => __('purchase_cycle.fields.date'), 'value' => LanguageSettings::formatDate($purchaseInvoice->date ?? $purchaseInvoice->created_at)],
                                ['icon' => 'fa-box-open', 'label' => __('purchase_cycle.invoice.receiving_status'), 'value' => $receiving['label'] ?? null],
                                ['icon' => 'fa-user', 'label' => __('purchase_cycle.fields.creator'), 'value' => $purchaseInvoice->creator->name ?? null],
                                ['icon' => 'fa-box', 'label' => __('purchase_cycle.fields.items_count'), 'value' => LanguageSettings::formatNumber($items->count(), 0)],
                                ['icon' => 'fa-money-bill-wave', 'label' => __('purchase_cycle.invoice.paid_amount'), 'value' => CurrencySettings::format($paidAmount)],
                                ['icon' => 'fa-hourglass-half', 'label' => __('purchase_cycle.invoice.due_value'), 'value' => CurrencySettings::format($dueValue)],
                                ['icon' => 'fa-coins', 'label' => __('purchase_cycle.fields.currency'), 'value' => CurrencySettings::name().' ('.CurrencySettings::symbol().')'],
                                ['icon' => 'fa-building', 'label' => __('purchase_cycle.invoice.cost_center_current'), 'value' => $purchaseInvoice->costCenter->name ?? __('purchase_cycle.invoice.cost_center_none')],
                                ['icon' => 'fa-clock', 'label' => __('purchase_orders.fields.updated_at'), 'value' => LanguageSettings::formatDate($purchaseInvoice->updated_at, true)],
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
                            {{ $purchaseInvoice->notes ?: __('purchase_orders.empty.no_notes') }}
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
                                                {{-- المبلغ من «إعدادات الضريبة» لا العمود `tax`
                                                     الذي لا وجود له، فكانت الخانة صفراً دائماً --}}
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
                    @if ($purchaseInvoice->payments->isEmpty())
                        <div class="po-empty">
                            <i class="fas fa-money-bill-wave"></i>
                            <h5>{{ __('purchase_cycle.invoice.no_payments') }}</h5>
                            @if (empty($existingReturn) && ! $purchaseInvoice->is_paid)
                                <a href="{{ route('PaymentSupplier.createPurchase', $purchaseInvoice->id) }}" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i>{{ __('purchase_cycle.invoice.add_payment') }}
                                </a>
                            @endif
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
                                        <th style="width: 96px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseInvoice->payments as $payment)
                                        <tr>
                                            <td class="text-center num">{{ LanguageSettings::formatNumber($loop->iteration, 0) }}</td>
                                            <td class="text-center num">
                                                {{ LanguageSettings::formatDate($payment->payment_date ?? $payment->created_at) }}
                                            </td>
                                            <td class="text-center num">{{ CurrencySettings::format($payment->amount ?? 0) }}</td>
                                            <td>{{ $payment->Payment_method ?: __('purchase_cycle.empty.unknown') }}</td>
                                            <td>{{ $payment->notes ?: '—' }}</td>
                                            <td>{{ $payment->user->name ?? '—' }}</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editPayment({{ $payment->id }})"
                                                    title="{{ __('purchase_cycle.actions.edit') }}">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deletePayment({{ $payment->id }})"
                                                    title="{{ __('purchase_cycle.actions.delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- الملاحظات --}}
                <div class="tab-pane" id="notes" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.notes-invoice', ['documentId' => $purchaseInvoice->id])
                    </div>
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

    <form id="deleteForm" action="{{ route('invoicePurchases.destroy', $purchaseInvoice->id) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <form id="receiveForm" action="{{ route('invoicePurchases.receive', $purchaseInvoice->id) }}" method="POST" class="d-none">
        @csrf
    </form>
@endsection

@section('scripts')
    <script>
        // دالة استلام الفاتورة (تأكيد)
        function confirmReceive() {
            Swal.fire({
                title: 'تأكيد استلام الفاتورة',
                text: 'هل أنت متأكد من استلام الفاتورة؟ سيتم إنشاء القيود المحاسبية فوراً عند التأكيد.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، تم الاستلام',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'جاري المعالجة...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    document.getElementById('receiveForm').submit();
                }
            });
        }

        // دالة حذف الفاتورة
        function deleteInvoice() {
            Swal.fire({
                title: 'حذف فاتورة الشراء',
                text: 'هل أنت متأكد من حذف فاتورة الشراء رقم "{{ $purchaseInvoice->code }}"؟ هذا الإجراء لا يمكن التراجع عنه!',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // دالة نسخ الفاتورة
        function copyInvoice() {
            Swal.fire({
                title: 'نسخ فاتورة الشراء',
                text: 'سيتم إنشاء نسخة جديدة من فاتورة الشراء',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#17a2b8',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'تأكيد النسخ',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    /* كان يوجّه إلى `create?copy_from=…` — ووسيطٌ لا يقرؤه
                       `create()` إطلاقاً (لا وجود له في الوحدة كلّها)، فيصل
                       المستخدمُ إلى نموذجٍ فارغ ويظنّ الزرَّ معطّلاً. ودالّةُ
                       `copy()` التي تنسخ الترويسةَ والبنودَ كانت يتيمةً بلا
                       مُستدعٍ — فرُبط الزرُّ بها. */
                    window.location.href = "{{ route('invoicePurchases.copy', $purchaseInvoice->id) }}";
                }
            });
        }

        // دالة تعيين مركز التكلفة
        function assignCostCenter() {
            Swal.fire({
                title: 'جاري التحميل...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch("{{ route('invoicePurchases.assignCostCenter', $purchaseInvoice->id) }}")
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        Swal.fire('خطأ!', '{{ __('purchase_cycle.invoice.cost_center_save_error') }}', 'error');
                        return;
                    }

                    const options = data.costCenters.map(center => {
                        const selected = center.id === data.current ? 'selected' : '';
                        return `<option value="${center.id}" ${selected}>${center.label}</option>`;
                    }).join('');

                    Swal.fire({
                        title: '{{ __('purchase_cycle.invoice.cost_center') }}',
                        html: `
                            <p class="text-muted">{{ __('purchase_cycle.invoice.cost_center_prompt') }}</p>
                            <select id="cost_center_select" class="form-control">
                                <option value="">{{ __('purchase_cycle.invoice.cost_center_none') }}</option>
                                ${options}
                            </select>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'حفظ',
                        cancelButtonText: 'إلغاء',
                        preConfirm: () => document.getElementById('cost_center_select').value
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            return;
                        }

                        const formData = new FormData();
                        formData.append('cost_center_id', result.value);
                        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                        fetch("{{ route('invoicePurchases.updateCostCenter', $purchaseInvoice->id) }}", {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(saveData => {
                                if (saveData.success) {
                                    Swal.fire('تم بنجاح!', '{{ __('purchase_cycle.invoice.cost_center_saved') }}', 'success')
                                        .then(() => location.reload());
                                } else {
                                    Swal.fire('خطأ!', '{{ __('purchase_cycle.invoice.cost_center_save_error') }}', 'error');
                                }
                            })
                            .catch(() => {
                                Swal.fire('خطأ!', '{{ __('purchase_cycle.invoice.cost_center_save_error') }}', 'error');
                            });
                    });
                })
                .catch(() => {
                    Swal.fire('خطأ!', '{{ __('purchase_cycle.invoice.cost_center_save_error') }}', 'error');
                });
        }

        // دالة تعديل الدفعة
        function editPayment(paymentId) {
            // جلب بيانات الدفعة
            fetch(`/payment-supplier/${paymentId}/edit`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const payment = data.payment;

                        Swal.fire({
                            title: 'تعديل الدفعة',
                            html: `
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_amount" class="form-label text-start d-block">المبلغ:</label>
                                    <input type="number" id="edit_amount" class="form-control"
                                           value="${payment.amount}" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_payment_method" class="form-label text-start d-block">طريقة الدفع:</label>
                                    <select id="edit_payment_method" class="form-control" required>
                                        <option value="cash" ${payment.payment_method === 'cash' ? 'selected' : ''}>نقداً</option>
                                        <option value="bank" ${payment.payment_method === 'bank' ? 'selected' : ''}>تحويل بنكي</option>
                                        <option value="check" ${payment.payment_method === 'check' ? 'selected' : ''}>شيك</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_notes" class="form-label text-start d-block">الملاحظات:</label>
                                <textarea id="edit_notes" class="form-control" rows="3">${payment.notes || ''}</textarea>
                            </div>
                        `,
                            width: '500px',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'حفظ التعديلات',
                            cancelButtonText: 'إلغاء',
                            preConfirm: () => {
                                const amount = document.getElementById('edit_amount').value;
                                const paymentMethod = document.getElementById('edit_payment_method').value;
                                const notes = document.getElementById('edit_notes').value;

                                if (!amount || amount <= 0) {
                                    Swal.showValidationMessage('يرجى إدخال مبلغ صحيح');
                                    return false;
                                }

                                return {
                                    amount,
                                    paymentMethod,
                                    notes
                                };
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                updatePayment(paymentId, result.value);
                            }
                        });
                    }
                })
                .catch(error => {
                    Swal.fire('خطأ!', 'حدث خطأ أثناء جلب بيانات الدفعة', 'error');
                });
        }

        // دالة تحديث الدفعة
        function updatePayment(paymentId, paymentData) {
            const formData = new FormData();
            formData.append('amount', paymentData.amount);
            formData.append('payment_method', paymentData.paymentMethod);
            formData.append('notes', paymentData.notes);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            formData.append('_method', 'PUT');

            fetch(`/payment-supplier/${paymentId}`, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('تم التحديث!', 'تم تحديث الدفعة بنجاح', 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('خطأ!', data.message || 'حدث خطأ أثناء تحديث الدفعة', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('خطأ!', 'حدث خطأ أثناء الاتصال بالخادم', 'error');
                });
        }

        // دالة حذف الدفعة
        function deletePayment(paymentId) {
            Swal.fire({
                title: 'حذف الدفعة',
                text: 'هل أنت متأكد من حذف هذه الدفعة؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/payment-supplier/${paymentId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('تم الحذف!', 'تم حذف الدفعة بنجاح', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('خطأ!', data.message || 'حدث خطأ أثناء حذف الدفعة', 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('خطأ!', 'حدث خطأ أثناء الاتصال بالخادم', 'error');
                        });
                }
            });
        }

        // دالة إضافة ملاحظة أو مرفق (نفس الكود من أمر الشراء مع تعديل المسارات)
        function addNoteOrAttachment() {
            // الحصول على التاريخ والوقت الحالي
            const now = new Date();
            const currentDate = now.toISOString().split('T')[0];
            const currentTime = now.toTimeString().split(' ')[0].substring(0, 5);

            Swal.fire({
                title: 'إضافة ملاحظة أو مرفق',
                html: `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="process" class="form-label text-start d-block">نوع الإجراء:</label>
                            <select id="process" class="form-control">
                                <option value="">اختر نوع الإجراء</option>
                                <option value="مراجعة الفاتورة">مراجعة الفاتورة</option>
                                <option value="تسجيل الدفع">تسجيل الدفع</option>
                                <option value="تأكيد الاستلام">تأكيد الاستلام</option>
                                <option value="مراجعة البضائع">مراجعة البضائع</option>
                                <option value="التواصل مع المورد">التواصل مع المورد</option>
                                <option value="تحديث البيانات">تحديث البيانات</option>
                                <option value="ملاحظة عامة">ملاحظة عامة</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="noteDate" class="form-label text-start d-block">التاريخ:</label>
                            <input type="date" id="noteDate" class="form-control" value="${currentDate}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="noteTime" class="form-label text-start d-block">الوقت:</label>
                            <input type="time" id="noteTime" class="form-control" value="${currentTime}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="attachment" class="form-label text-start d-block">مرفق (اختياري):</label>
                            <input type="file" id="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="note" class="form-label text-start d-block">تفاصيل الملاحظة:</label>
                        <textarea id="note" class="form-control" rows="4" placeholder="اكتب تفاصيل الملاحظة هنا..."></textarea>
                    </div>
                `,
                width: '600px',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-save me-1"></i> حفظ الملاحظة',
                cancelButtonText: '<i class="fas fa-times me-1"></i> إلغاء',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const process = document.getElementById('process').value;
                    const note = document.getElementById('note').value.trim();
                    const noteDate = document.getElementById('noteDate').value;
                    const noteTime = document.getElementById('noteTime').value;
                    const attachment = document.getElementById('attachment').files[0];

                    // التحقق من صحة البيانات
                    if (!process) {
                        Swal.showValidationMessage('يرجى اختيار نوع الإجراء');
                        return false;
                    }

                    if (!note) {
                        Swal.showValidationMessage('يرجى إدخال تفاصيل الملاحظة');
                        return false;
                    }

                    if (!noteDate) {
                        Swal.showValidationMessage('يرجى تحديد التاريخ');
                        return false;
                    }

                    if (!noteTime) {
                        Swal.showValidationMessage('يرجى تحديد الوقت');
                        return false;
                    }

                    return {
                        process,
                        note,
                        noteDate,
                        noteTime,
                        attachment
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    saveNoteToDatabase(result.value);
                }
            });
        }

        // دالة حفظ الملاحظة في قاعدة البيانات
        function saveNoteToDatabase(noteData) {
            const formData = new FormData();
            formData.append('description', noteData.note);
            formData.append('process', noteData.process);
            formData.append('date', noteData.noteDate);
            formData.append('time', noteData.noteTime);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            if (noteData.attachment) {
                formData.append('attachment', noteData.attachment);
            }

            fetch(`{{ route('invoicePurchases.addNote', $purchaseInvoice->id) }}`, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'تم الحفظ بنجاح!',
                            text: 'تم إضافة الملاحظة بنجاح',
                            icon: 'success',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            // إعادة تحميل الصفحة لإظهار الملاحظة الجديدة
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'خطأ!',
                            text: data.message || 'حدث خطأ أثناء حفظ الملاحظة',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'خطأ!',
                        text: 'حدث خطأ أثناء الاتصال بالخادم',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
        }

        // دالة عرض جميع الملاحظات
        function viewAllNotes() {
            // عرض loader أثناء التحميل
            Swal.fire({
                title: 'جاري تحميل الملاحظات...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`{{ route('invoicePurchases.getNotes', $purchaseInvoice->id) }}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('البيانات المُستلمة:', data); // للتشخيص

                    if (data.success) {
                        let notesHtml = '';

                        if (data.notes && data.notes.length > 0) {
                            data.notes.forEach((note, index) => {
                                // التأكد من وجود البيانات المطلوبة
                                const processName = note.process || 'غير محدد';
                                const employeeName = note.employee_name || 'غير محدد';
                                const noteDate = note.date || 'غير محدد';
                                const noteTime = note.time || 'غير محدد';
                                const description = note.description || 'لا يوجد وصف';

                                notesHtml += `
                                <div class="card mb-3 border-start border-primary border-3" id="note-${note.id}">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-1">
                                                    <span class="badge bg-primary me-2">${index + 1}</span>
                                                    <h6 class="mb-0 text-primary fw-bold">${processName}</h6>
                                                </div>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-user me-1 text-info"></i><strong>الموظف:</strong> ${employeeName}
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-calendar me-1 text-success"></i><strong>التاريخ:</strong> ${noteDate}
                                                    <i class="fas fa-clock me-2 ms-3 text-warning"></i><strong>الوقت:</strong> ${noteTime}
                                                </small>
                                            </div>
                                            <button class="btn btn-outline-danger btn-sm" onclick="deleteNote(${note.id})"
                                                    title="حذف الملاحظة">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>

                                        <div class="mt-3 p-3 bg-light rounded">
                                            <p class="mb-0 text-dark">${description}</p>
                                        </div>

                                        ${note.has_attachment ? `
                                            <div class="mt-3 pt-2 border-top">
                                                <a href="${note.attachment_url}" target="_blank"
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-paperclip me-1"></i>عرض المرفق
                                                </a>
                                            </div>
                                        ` : ''}

                                        ${note.created_at ? `
                                            <div class="mt-2 pt-2 border-top">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>تم الإنشاء: ${note.created_at}
                                                </small>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                            });
                        } else {
                            notesHtml = `
                            <div class="alert alert-info text-center py-4">
                                <i class="fas fa-info-circle fs-1 text-info mb-3"></i>
                                <h5 class="mb-2">لا توجد ملاحظات حتى الآن</h5>
                                <p class="mb-0 text-muted">يمكنك إضافة ملاحظة جديدة من خلال زر "إضافة ملاحظة جديدة"</p>
                            </div>
                        `;
                        }

                        Swal.fire({
                            title: `<i class="fas fa-sticky-note me-2 text-primary"></i>جميع الملاحظات (${data.notes ? data.notes.length : 0})`,
                            html: `
                            <div class="text-start" style="max-height: 500px; overflow-y: auto; padding: 10px;">
                                ${notesHtml}
                            </div>
                            <div class="mt-3 pt-3 border-top text-center">
                                <button type="button" class="btn btn-success btn-sm me-2" onclick="Swal.close(); addNoteOrAttachment();">
                                    <i class="fas fa-plus me-1"></i>إضافة ملاحظة جديدة
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="location.reload();">
                                    <i class="fas fa-sync me-1"></i>تحديث الصفحة
                                </button>
                            </div>
                        `,
                            width: '800px',
                            showConfirmButton: false,
                            showCancelButton: true,
                            cancelButtonText: '<i class="fas fa-times me-1"></i>إغلاق',
                            cancelButtonColor: '#6c757d',
                            customClass: {
                                popup: 'swal-rtl'
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'خطأ!',
                            text: data.message || 'لم يتم العثور على ملاحظات',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('خطأ في جلب الملاحظات:', error);
                    Swal.fire({
                        title: 'خطأ في الاتصال!',
                        text: 'حدث خطأ أثناء جلب الملاحظات. يرجى التحقق من الاتصال بالإنترنت والمحاولة مرة أخرى.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545',
                        footer: `<small class="text-muted">تفاصيل الخطأ: ${error.message}</small>`
                    });
                });
        }

        // دالة حذف ملاحظة
        function deleteNote(noteId) {
            Swal.fire({
                title: 'تأكيد الحذف',
                text: 'هل أنت متأكد من حذف هذه الملاحظة؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`{{ route('invoicePurchases.deleteNote', '') }}/${noteId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('تم الحذف!', 'تم حذف الملاحظة بنجاح', 'success');
                                viewAllNotes(); // إعادة تحميل قائمة الملاحظات
                            } else {
                                Swal.fire('خطأ!', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire('خطأ!', 'حدث خطأ أثناء حذف الملاحظة', 'error');
                        });
                }
            });
        }

        // عرض رسائل النجاح والخطأ
        @if (session('success'))
            Swal.fire({
                title: 'تم بنجاح!',
                text: '{{ session('success') }}',
                icon: 'success',
                confirmButtonColor: '#28a745'
            });
        @endif

        @if (session('error'))
            Swal.fire({
                title: 'خطأ!',
                text: '{{ session('error') }}',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        @endif

        // تأثيرات إضافية للتفاعل
        document.addEventListener('DOMContentLoaded', function() {
            // تأثير hover للأزرار
            const buttons = document.querySelectorAll('.status-btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                });

                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // تأثير نبضة للأزرار المهمة
            const pulseButtons = document.querySelectorAll('.pulse-effect');
            pulseButtons.forEach(button => {
                button.classList.add('pulse-effect');
            });
        });
    </script>
@endsection
