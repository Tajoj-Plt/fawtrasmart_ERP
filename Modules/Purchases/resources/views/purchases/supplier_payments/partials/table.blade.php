{{--
    جدول مدفوعات الموردين — يُعاد تصييره وحده في طلبات AJAX.

    التصميم منقول عن جدول «فواتير الشراء» بنيةً وأصنافاً: `table modern-table`،
    عمود `#`، عمود رئيسي بسطرين، شارة حالة، عمود تاريخ فوق وقت، ثم زرّ
    `bg-gradient-info` بثلاث نقاط يفتح قائمة الإجراءات.

    **كان الجدول يحمل مُنتقي «عرض» داخله** ومعه غلاف DataTables كامل —
    فيتكرّر المُنتقي مع كل تحديث AJAX. صار المُنتقي في ترويسة البطاقة مرّةً
    واحدة كبقيّة شاشات القسم.

    ما يأتي من الإعدادات:
      • العملة والكسور والفواصل ← «العملات»
      • صيغة التاريخ والوقت      ← إعدادات اللغة النشطة
      • الألوان والخط            ← «مظهر النظام» عبر متغيّرات CSS

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $payments
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    $unknown = __('purchase_cycle.empty.unknown');
@endphp

<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th class="text-center" style="width: 60px;">#</th>
                <th>{{ __('purchase_cycle.payment.number') }}</th>
                <th>{{ __('purchase_cycle.fields.supplier') }}</th>
                <th>{{ __('purchase_cycle.payment.invoice') }}</th>
                <th class="text-center">{{ __('purchase_cycle.payment.amount') }}</th>
                <th>{{ __('purchase_cycle.payment.method') }}</th>
                <th class="text-center">{{ __('purchase_cycle.payment.status') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.datetime') }}</th>
                <th>{{ __('branches.node_filter.column') }}</th>
                <th class="text-center" style="width: 80px;">{{ __('purchase_cycle.actions.options') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                @php
                    $isCancelled = (int) $payment->payment_status === 5;
                    $supplier = $payment->supplier ?? $payment->purchase_invoice?->supplier;

                    /*
                     * الحالة تُشتقّ ولا تُخزَّن.
                     *
                     * كانت تُقرأ من عمود `payment_status` الذي نكتبه نحن عند
                     * الحفظ، فيبقى على قيمته الأولى مهما جرى على الفاتورة
                     * بعدها — تُسدَّد بالكامل بدفعةٍ لاحقة والسند ما زال يقول
                     * غير ذلك. الحقيقةُ في الفاتورة: متبقّيها صفرٌ فهي مكتملة.
                     */
                    $invoice = $payment->purchase_invoice;
                    $remaining = (float) ($invoice->due_value ?? 0);

                    if ($isCancelled) {
                        $state = ['class' => 'po-status--rejected', 'icon' => 'fa-times-circle',
                                  'label' => __('purchase_cycle.payment.cancelled'), 'hint' => null];
                    } elseif (! $invoice) {
                        $state = ['class' => 'po-status--quotation', 'icon' => 'fa-unlink',
                                  'label' => __('purchase_cycle.payment.confirmed'),
                                  'hint' => __('purchase_cycle.payment.no_invoice')];
                    } elseif ($remaining <= 0.009) {
                        $state = ['class' => 'po-status--approved', 'icon' => 'fa-check-circle',
                                  'label' => __('purchase_cycle.payment.completed'),
                                  'hint' => __('purchase_cycle.payment.completed_hint')];
                    } else {
                        $state = ['class' => 'po-status--review', 'icon' => 'fa-adjust',
                                  'label' => __('purchase_cycle.payment.incomplete'),
                                  'hint' => __('purchase_cycle.payment.incomplete_hint', [
                                      'amount' => CurrencySettings::format($remaining),
                                  ])];
                    }
                @endphp

                <tr class="payment-row" data-payment-id="{{ $payment->id }}">
                    <td class="text-center">{{ $payment->id }}</td>

                    <td>
                        <a href="{{ route('PaymentSupplier.showSupplierPayment', $payment->id) }}">
                            <strong>{{ $payment->reference_number ?: '#'.$payment->id }}</strong>
                        </a>
                        @if ($payment->employee)
                            <br><small class="text-muted">
                                <i class="fas fa-user"></i> {{ $payment->employee->name }}
                            </small>
                        @endif
                    </td>

                    <td>{{ $supplier->trade_name ?? $unknown }}</td>

                    <td>
                        @if ($payment->purchase_invoice)
                            <a href="{{ route('invoicePurchases.show', $payment->purchase_invoice->id) }}">
                                {{ $payment->purchase_invoice->code ?: '—' }}
                            </a>
                        @else
                            {{ $unknown }}
                        @endif
                    </td>

                    {{-- المبلغ بالعملة الافتراضية وكسورها كما تضبطها «العملات» --}}
                    <td class="text-center num">{{ CurrencySettings::format($payment->amount ?? 0) }}</td>

                    {{-- اسم العمود بحرف كبير في الجدول؛ قراءته بحرف صغير
                         تعيد null فتخرج الخانة فارغة --}}
                    <td>{{ $payment->Payment_method ?: $unknown }}</td>

                    <td class="text-center">
                        <span class="po-status {{ $state['class'] }}">
                            <i class="fas {{ $state['icon'] }}"></i>{{ $state['label'] }}
                        </span>
                        @if ($state['hint'])
                            <div class="mt-1"><small class="text-muted">{{ $state['hint'] }}</small></div>
                        @endif
                    </td>

                    <td class="text-center num">
                        <div>{{ LanguageSettings::formatDate($payment->payment_date ?? $payment->created_at) ?: '—' }}</div>
                        <small class="text-muted">
                            {{ $payment->created_at?->timezone(LanguageSettings::timezone())?->format(LanguageSettings::timeFormat()) }}
                        </small>
                    </td>

                    {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                    <td>@include('partials.branch-node-path', ['branchId' => $payment->branch_id])</td>

                    <td class="text-center">
                        <div class="btn-group">
                            <div class="dropdown">
                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    aria-label="{{ __('purchase_cycle.actions.options') }}"></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('PaymentSupplier.showSupplierPayment', $payment->id) }}">
                                        <i class="fas fa-eye me-2 text-primary"></i>{{ __('purchase_cycle.actions.show') }}
                                    </a>
                                    @permission('purchases.payments.export')
                                        <a class="dropdown-item" href="{{ route('PaymentSupplier.print', ['id' => $payment->id]) }}"
                                            target="_blank" rel="noopener">
                                            <i class="fas fa-print me-2 text-info"></i>{{ __('purchase_cycle.actions.print') }}
                                        </a>
                                        <a class="dropdown-item" href="{{ route('PaymentSupplier.pdf', ['id' => $payment->id]) }}">
                                            <i class="fas fa-file-pdf me-2 text-danger"></i>{{ __('purchase_cycle.actions.download_pdf') }}
                                        </a>
                                    @endpermission

                                    @unless ($isCancelled)
                                        @permission('purchases.payments.edit')
                                            <a class="dropdown-item" href="{{ route('PaymentSupplier.editSupplierPayment', $payment->id) }}">
                                                <i class="fas fa-edit me-2 text-success"></i>{{ __('purchase_cycle.actions.edit') }}
                                            </a>
                                        @endpermission
                                        @permission('purchases.payments.cancel')
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-warning cancel-payment" href="#" data-id="{{ $payment->id }}">
                                                <i class="fas fa-ban me-2"></i>{{ __('purchase_cycle.payment.cancelled') }}
                                            </a>
                                        @endpermission
                                    @endunless

                                    @permission('purchases.payments.delete')
                                        <a class="dropdown-item text-danger delete-payment" href="#" data-id="{{ $payment->id }}">
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
                            <span class="sl-empty__icon"><i class="fas fa-credit-card"></i></span>
                            <p class="sl-empty__title">
                                {{ request()->hasAny(['supplier_id', 'invoice_number', 'payment_method', 'date_from', 'date_to']) ? __('purchase_cycle.empty.filtered') : __('purchase_cycle.payment.empty_title') }}
                            </p>
                            <p class="sl-empty__hint">{{ __('purchase_cycle.payment.empty_hint') }}</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
