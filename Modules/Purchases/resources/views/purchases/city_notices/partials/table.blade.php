{{--
    جدول نتائج الإشعارات المدينة — يُعاد تصييره وحده في طلبات AJAX.

    التصميم منقول عن جدول «فواتير الشراء» بنيةً وأصنافاً: `table modern-table`،
    عمود تحديد في الأول، عمود `#`، عمود رئيسي بسطرين، عمود تاريخ فوق وقت، ثم
    زرّ `bg-gradient-info` بثلاث نقاط يفتح قائمة الإجراءات — والحالة الفارغة
    صفٌّ بأيقونة كبيرة باهتة لا كتلة مستقلّة.

    ما يأتي من الإعدادات:
      • العملة والكسور والفواصل ← «العملات» (العملة الافتراضية)
      • صيغة التاريخ والوقت      ← إعدادات اللغة النشطة
      • الألوان والخط            ← «مظهر النظام» عبر متغيّرات CSS

    الرقم المعروض `code` الآتي من «تسلسل أرقام المستندات» — لا
    `invoice_number` الذي لا وجود له في هذا الجدول فكان العمود يخرج فارغاً.

    الأصناف والمعرّفات التي يعتمدها JavaScript:
    `.delete-notice[data-id]` و`.order-checkbox` و`#selectAll`.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $noticesData
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    // شارات حالة السداد والاستلام — الصنف واحد والاختلاف في اللون والأيقونة
    $paymentVariants = [
        'paid' => ['class' => 'po-status--approved', 'icon' => 'fa-check-circle', 'label' => __('purchase_cycle.invoice.paid')],
        'partial' => ['class' => 'po-status--review', 'icon' => 'fa-adjust', 'label' => __('purchase_cycle.invoice.partial')],
        // الشيفرة القديمة تكتبها بصيغتين، فكلتاهما مقبولة هنا
        'partially_paid' => ['class' => 'po-status--review', 'icon' => 'fa-adjust', 'label' => __('purchase_cycle.invoice.partial')],
        'unpaid' => ['class' => 'po-status--rejected', 'icon' => 'fa-times-circle', 'label' => __('purchase_cycle.invoice.unpaid')],
    ];

    // البضاعة تخرج هنا لا تدخل، فالشارة «مُرتجَعة» لا «مستلمة»
    $receivingVariants = [
        'received' => ['class' => 'po-status--approved', 'icon' => 'fa-dolly-flatbed', 'label' => __('purchase_cycle.goods.returned')],
        'partially_receiv' => ['class' => 'po-status--review', 'icon' => 'fa-dolly', 'label' => __('purchase_cycle.goods.partially_returned')],
        'partially_received' => ['class' => 'po-status--review', 'icon' => 'fa-dolly', 'label' => __('purchase_cycle.goods.partially_returned')],
        'not_received' => ['class' => 'po-status--quotation', 'icon' => 'fa-clock', 'label' => __('purchase_cycle.goods.not_returned')],
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
            @forelse ($noticesData as $notice)
                @php
                    $payment = $paymentVariants[$notice->payment_status] ?? null;
                    $receiving = $receivingVariants[$notice->receiving_status] ?? null;
                @endphp

                <tr class="notice-row" data-notice-id="{{ $notice->id }}">
                    <td class="text-center">
                        <input type="checkbox" class="po-check order-checkbox" name="ids[]"
                            value="{{ $notice->id }}" aria-label="{{ $notice->code }}">
                    </td>

                    <td class="text-center">{{ $notice->id }}</td>

                    <td>
                        <a href="{{ route('CityNotices.show', $notice->id) }}">
                            <strong>{{ $notice->code ?: '—' }}</strong>
                        </a>
                        @if ($notice->reference_id)
                            {{-- الفاتورة المرجعية: الإشعار صادرٌ عنها، فرقمها
                                 معلومةٌ في الصفّ لا تفصيلٌ يُبحَث عنه --}}
                            <br><small class="text-muted">
                                <i class="fas fa-link"></i>
                                {{ $notice->originalDocument->code ?? '#'.$notice->reference_id }}
                            </small>
                        @endif
                    </td>

                    <td>{{ $notice->supplier->trade_name ?? __('purchase_cycle.empty.unknown') }}</td>

                    {{-- «تابع لـ» — العقدةُ المختومة على المستند بمسارها من الجذر --}}
                    <td>@include('partials.branch-node-path', ['branchId' => $notice->branch_id])</td>

                    <td>
                        @if ($payment)
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

                    <td class="text-center num">{{ CurrencySettings::format($notice->due_value ?? 0) }}</td>

                    <td class="text-center num">{{ CurrencySettings::format($notice->grand_total ?? 0) }}</td>

                    <td class="text-center num">
                        <div>{{ LanguageSettings::formatDate($notice->date ?? $notice->created_at) ?: '—' }}</div>
                        <small class="text-muted">
                            {{ $notice->created_at?->timezone(LanguageSettings::timezone())?->format(LanguageSettings::timeFormat()) }}
                        </small>
                    </td>

                    <td class="text-center">
                        <div class="btn-group">
                            <div class="dropdown">
                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    aria-label="{{ __('purchase_cycle.actions.options') }}"></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('CityNotices.show', $notice->id) }}">
                                        <i class="fas fa-eye me-2 text-primary"></i>{{ __('purchase_cycle.actions.show') }}
                                    </a>
                                    @permission('purchases.payments.create')
                                        <a class="dropdown-item" href="{{ route('PaymentSupplier.createPurchase', ['id' => $notice->id]) }}">
                                            <i class="fas fa-credit-card me-2 text-info"></i>{{ __('purchase_cycle.invoice.add_payment') }}
                                        </a>
                                    @endpermission
                                    @permission('purchases.debit_notices.export')
                                        <a class="dropdown-item" href="{{ route('CityNotices.print', ['id' => $notice->id]) }}"
                                            target="_blank" rel="noopener">
                                            <i class="fas fa-print me-2 text-info"></i>{{ __('purchase_cycle.actions.print') }}
                                        </a>
                                    @endpermission
                                    @permission('purchases.debit_notices.delete')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-notice" href="#" data-id="{{ $notice->id }}">
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
                            <span class="sl-empty__icon"><i class="fas fa-file-invoice-dollar"></i></span>
                            <p class="sl-empty__title">
                                {{ request()->hasAny(['number_invoice', 'payment_status', 'employee_search']) ? __('purchase_cycle.empty.filtered') : __('purchase_cycle.debit_note.empty_title') }}
                            </p>
                            <p class="sl-empty__hint">{{ __('purchase_cycle.debit_note.empty_hint') }}</p>
                            @permission('purchases.debit_notices.create')
                                <a href="{{ route('CityNotices.create') }}" class="btn btn-primary btn-sm">
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
