{{--
    جدول نتائج فواتير الشراء — يُعاد تصييره وحده في طلبات AJAX.

    التصميم منقول عن جدول شاشة «سجلّ النشاطات» (`sitting::ActivityLogs.index`)
    بنيةً وأصنافاً كما في بقيّة جداول القسم: `table modern-table`، عمود تحديد
    في الأول، عمود `#`، عمود رئيسي بسطرين (نصّ عريض فوق نصّ رمادي صغير)،
    عمود تاريخ فوق وقت، ثم زرّ `bg-gradient-info` بثلاث نقاط يفتح قائمة
    الإجراءات — والحالة الفارغة صفٌّ بأيقونة كبيرة باهتة لا كتلة مستقلّة.

    الفارق الوحيد عن الشاشة الأصلية أن الأيقونات هنا من Font Awesome لا من
    Feather، لأنها الخط الذي تحمّله واجهة المشتريات.

    ما يأتي من الإعدادات:
      • العملة والكسور والفواصل ← «العملات» (العملة الافتراضية)
      • صيغة التاريخ            ← إعدادات اللغة النشطة
      • الألوان والخط           ← «مظهر النظام» عبر متغيّرات CSS

    الأصناف والمعرّفات التي يعتمدها JavaScript تبقى كما هي:
    `.delete-invoice[data-id]` و`.order-checkbox` و`#selectAll`.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $purchaseData
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    // شارات حالة الدفع والاستلام — الصنف واحد والاختلاف في اللون والأيقونة
    $paymentVariants = [
        'paid' => ['class' => 'po-status--approved', 'icon' => 'fa-check-circle', 'label' => __('purchase_cycle.invoice.paid')],
        'partial' => ['class' => 'po-status--review', 'icon' => 'fa-adjust', 'label' => __('purchase_cycle.invoice.partial')],
        // الشيفرة القديمة تكتبها بصيغتين، فكلتاهما مقبولة هنا
        'partially_paid' => ['class' => 'po-status--review', 'icon' => 'fa-adjust', 'label' => __('purchase_cycle.invoice.partial')],
        'unpaid' => ['class' => 'po-status--rejected', 'icon' => 'fa-times-circle', 'label' => __('purchase_cycle.invoice.unpaid')],
    ];

    $receivingVariants = [
        'received' => ['class' => 'po-status--approved', 'icon' => 'fa-box-open', 'label' => __('purchase_cycle.invoice.received')],
        'partially_receiv' => ['class' => 'po-status--review', 'icon' => 'fa-dolly', 'label' => __('purchase_cycle.invoice.partially_received')],
        'partially_received' => ['class' => 'po-status--review', 'icon' => 'fa-dolly', 'label' => __('purchase_cycle.invoice.partially_received')],
        'not_received' => ['class' => 'po-status--quotation', 'icon' => 'fa-clock', 'label' => __('purchase_cycle.invoice.not_received')],
    ];
@endphp

<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th class="text-center" style="width: 40px;">
                    <input type="checkbox" id="selectAll" class="po-check"
                        aria-label="{{ __('purchase_orders.bulk.select_all') }}">
                </th>
                <th class="text-center" style="width: 60px;">#</th>
                <th>{{ __('purchase_cycle.fields.code') }}</th>
                <th>{{ __('purchase_cycle.fields.supplier') }}</th>
                <th>{{ __('branches.node_filter.column') }}</th>
                <th>{{ __('purchase_cycle.fields.status') }}</th>
                <th class="text-center">{{ __('purchase_cycle.invoice.due_value') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.grand_total') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.datetime') }}</th>
                <th class="text-center" style="width: 80px;">{{ __('purchase_cycle.actions.options') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchaseData as $invoice)
                @php
                    $payment = $paymentVariants[$invoice->payment_status] ?? null;
                    $receiving = $receivingVariants[$invoice->receiving_status] ?? null;

                    // `relatedDocuments` محمَّلةٌ مسبقاً في المتحكّم، فلا استعلام
                    // لكل صفّ. المرتجع يمنع الدفع ويمنع إنشاء مرتجعٍ ثانٍ.
                    $hasReturn = $invoice->relatedDocuments
                        ->where('type', 'Return')
                        ->isNotEmpty();
                @endphp

                <tr class="invoice-row" data-invoice-id="{{ $invoice->id }}">
                    <td class="text-center">
                        <input type="checkbox" class="po-check order-checkbox" name="ids[]"
                            value="{{ $invoice->id }}" aria-label="{{ $invoice->code }}">
                    </td>

                    <td class="text-center">{{ $invoice->id }}</td>

                    <td>
                        {{-- الرقم هو `code` الآتي من «تسلسل أرقام المستندات»،
                             لا `reference_number` الذي يخصّ المستند المرجعي --}}
                        <a href="{{ route('invoicePurchases.show', $invoice->id) }}">
                            <strong>{{ $invoice->code ?: '—' }}</strong>
                        </a>
                        @if ($invoice->reference_number)
                            <br><small class="text-muted">
                                <i class="fas fa-link"></i> {{ $invoice->reference_number }}
                            </small>
                        @endif
                    </td>

                    <td>{{ $invoice->supplier->trade_name ?? __('purchase_cycle.empty.unknown') }}</td>

                    {{-- «تابع لـ» — العقدةُ المختومة على المستند بمسارها من الجذر --}}
                    <td>@include('partials.branch-node-path', ['branchId' => $invoice->branch_id])</td>

                    <td>
                        @if ($invoice->type === 'Return')
                            <span class="po-status po-status--quotation">
                                <i class="fas fa-undo"></i>{{ __('purchase_cycle.invoice.return') }}
                            </span>
                        @elseif ($payment)
                            <span class="po-status {{ $payment['class'] }}">
                                <i class="fas {{ $payment['icon'] }}"></i>{{ $payment['label'] }}
                            </span>
                        @endif

                        @if ($receiving)
                            <div class="mt-1">
                                <span class="po-status {{ $receiving['class'] }}">
                                    <i class="fas {{ $receiving['icon'] }}"></i>{{ $receiving['label'] }}
                                </span>
                            </div>
                        @endif
                    </td>

                    {{-- لا عمود لتاريخ الاستحقاق في الجدول، والمتبقّي أنفع منه --}}
                    <td class="text-center num">{{ CurrencySettings::format($invoice->due_value ?? 0) }}</td>

                    <td class="text-center num">{{ CurrencySettings::format($invoice->grand_total ?? 0) }}</td>

                    <td class="text-center num">
                        <div>{{ LanguageSettings::formatDate($invoice->date ?? $invoice->created_at) ?: '—' }}</div>
                        <small class="text-muted">
                            {{ $invoice->created_at?->timezone(LanguageSettings::timezone())?->format(LanguageSettings::timeFormat()) }}
                        </small>
                    </td>

                    <td class="text-center">
                        <div class="btn-group">
                            <div class="dropdown">
                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    aria-label="{{ __('purchase_cycle.actions.options') }}"></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('invoicePurchases.show', $invoice->id) }}">
                                        <i class="fas fa-eye me-2 text-primary"></i>{{ __('purchase_cycle.actions.show') }}
                                    </a>
                                    @permission('purchases.invoices.edit')
                                        <a class="dropdown-item" href="{{ route('invoicePurchases.edit', $invoice->id) }}">
                                            <i class="fas fa-edit me-2 text-success"></i>{{ __('purchase_cycle.actions.edit') }}
                                        </a>
                                    @endpermission
                                    @if ($invoice->type === 'invoice' && ! $hasReturn)
                                        <a class="dropdown-item" href="{{ route('ReturnsInvoice.createFromInvoice', $invoice->id) }}">
                                            <i class="fas fa-undo me-2 text-warning"></i>{{ __('purchase_cycle.invoice.create_return') }}
                                        </a>
                                    @endif
                                    {{-- الفاتورة المرتجعة لا تُسدَّد: المرتجع عكس
                                         أثرها في حساب المورّد --}}
                                    @unless ($hasReturn || $invoice->is_paid)
                                        <a class="dropdown-item" href="{{ route('PaymentSupplier.createPurchase', ['id' => $invoice->id]) }}">
                                            <i class="fas fa-credit-card me-2 text-info"></i>{{ __('purchase_cycle.invoice.add_payment') }}
                                        </a>
                                    @endunless
                                    @permission('purchases.invoices.export')
                                        <a class="dropdown-item" href="{{ route('invoicePurchases.pdf', ['id' => $invoice->id]) }}"
                                            target="_blank" rel="noopener">
                                            <i class="fas fa-print me-2 text-info"></i>{{ __('purchase_cycle.actions.print') }}
                                        </a>
                                    @endpermission
                                    @permission('purchases.invoices.delete')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-invoice" href="#" data-id="{{ $invoice->id }}">
                                            <i class="fas fa-trash me-2"></i>{{ __('purchase_cycle.actions.delete') }}
                                        </a>
                                    @endpermission
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">
                        <div class="sl-empty">
                            <span class="sl-empty__icon"><i class="fas fa-file-invoice"></i></span>
                            <p class="sl-empty__title">
                                {{ request()->hasAny(['number_invoice', 'payment_status', 'employee_search']) ? __('purchase_cycle.empty.filtered') : __('purchase_cycle.invoice.empty_title') }}
                            </p>
                            @permission('purchases.invoices.create')
                                <a href="{{ route('invoicePurchases.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>{{ __('purchase_cycle.actions.create') }}
                                </a>
                            @endpermission
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
