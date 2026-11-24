{{--
    جدول مدفوعات العملاء — على قياس `sales::invoices.partials.table`.

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات» (كان الرمز مكتوباً «ر.س»)
      • صيغة التاريخ    ← اللغة النشطة (كانت `format('Y-m-d')` مثبَّتة)
      • طرق الدفع       ← «طرق الدفع» (الاسم لا الرقم)

    المطبوعات ليست هنا: قوالب سند القبض تُنتقى وتُعايَن في شاشة العرض،
    وعشرة قوالب في قائمة كل صفٍّ تُطيلها حتى تتجاوز الجدول.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $payments
--}}

@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    // [اللون] لكل حالة سداد — بدل سلسلة `elseif` تعيد الشارة خمس مرّات
    $tones = [1 => 'success', 2 => 'warning', 3 => 'secondary', 4 => 'info', 5 => 'danger'];
@endphp

@if ($payments->count())
    {{-- العدّاد ومُنتقي عدد السجلّات داخل الجدول: كلاهما يتغيّر مع
         الصفحة، وكانا فوق الحاوية فيبقيان على أرقام أوّل تحميل. --}}
    <div class="d-flex justify-content-between align-items-center p-2 pb-0">
        <span class="sl-help mb-0">
            {{ __('sales_documents.ui.showing', [
                'from' => $payments->firstItem() ?? 0,
                'to' => $payments->lastItem() ?? 0,
                'total' => $payments->total(),
            ]) }}
        </span>

        @include('sales::components.per-page', ['onchange' => 'loadData(1)'])
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('sales_payment.table.number') }}</th>
                    <th>{{ __('sales_payment.table.date') }}</th>
                    <th>{{ __('sales_payment.table.client') }}</th>
                    <th>{{ __('branches.node_filter.column') }}</th>
                    <th>{{ __('sales_payment.table.invoice') }}</th>
                    <th>{{ __('sales_payment.table.method') }}</th>
                    <th>{{ __('sales_payment.table.treasury') }}</th>
                    <th class="num">{{ __('sales_payment.table.amount') }}</th>
                    <th class="text-center">{{ __('sales_payment.table.status') }}</th>
                    <th class="text-center">{{ __('sales_payment.table.actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($payments as $payment)
                    @php
                        $invoice = $payment->invoice;

                        $clientName = $invoice && $invoice->client
                            ? ($invoice->client->trade_name
                                ?: trim(($invoice->client->first_name ?? '').' '.($invoice->client->last_name ?? '')))
                            : ($payment->client->trade_name ?? __('sales_documents.ui.unknown'));

                        $status = (int) $payment->payment_status;
                        $tone = $tones[$status] ?? 'dark';

                        // مَن سجّل السند — من مستنده الأصل (سند القبض / الفاتورة / الإشعار /
                        // الشيك، انظر `PaymentRecorder`)، وإلّا من عمود السند عبر الموديل
                        $recordedBy = ($recorderNames ?? [])[$payment->id] ?? $payment->recorded_by_name;
                    @endphp

                    <tr>
                        <td>
                            <a href="{{ route('paymentsClient.show', $payment->id) }}" class="sl-code">
                                {{ $payment->reference_number ?: '#'.$payment->id }}
                            </a>
                        </td>

                        <td class="sl-num">
                            {{ LanguageSettings::formatDate($payment->payment_date ?: $payment->created_at) }}

                            {{-- «سجَّلها» تحت التاريخ لا تحت العميل — كنظيرها في
                                 جدول الفواتير. كان الاسم يُطبع سطراً صغيراً تحت
                                 اسم العميل بلا عنوان، فيقرأ القارئ اسماً ثانياً
                                 ويحسبه من بيانات العميل. --}}
                            @if ($recordedBy)
                                <span class="sl-media__sub d-block">
                                    <i class="feather icon-user"></i>
                                    {{ __('sales_payment.table.added_by_line', ['name' => $recordedBy]) }}
                                </span>
                            @endif
                        </td>

                        <td><span class="sl-media__title">{{ $clientName }}</span></td>

                        {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                        <td>@include('partials.branch-node-path', ['branchId' => $payment->branch_id])</td>

                        <td>
                            @if ($invoice)
                                @permission('sales.invoices.view')
                                    <a href="{{ route('invoices.show', $invoice->id) }}" class="sl-code">
                                        {{ $invoice->code }}
                                    </a>
                                @else
                                    <span class="sl-code">{{ $invoice->code }}</span>
                                @endpermission
                            @else
                                <span class="sl-media__sub">{{ __('sales_payment.table.no_invoice') }}</span>
                            @endif
                        </td>

                        <td>
                            {{-- الاسم من «طرق الدفع» لا الرقم الخام --}}
                            <span class="sl-media__sub">
                                {{ $payment->payment_type->name ?? __('sales_documents.ui.dash') }}
                            </span>
                        </td>

                        <td>
                            <span class="sl-media__sub">
                                {{ $payment->treasury_name ?: __('sales_documents.ui.dash') }}
                            </span>
                        </td>

                        <td class="num">
                            <span class="sl-num">{{ CurrencySettings::format($payment->amount ?? 0) }}</span>
                        </td>

                        <td class="text-center">
                            <span class="badge badge-light-{{ $tone }}">
                                {{ __('sales_payment.status.'.$status) }}
                            </span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                {{-- `data-bs-toggle` وحده: الصفحة تحمّل بوتستراب ٥
                                     وبوتستراب ٤ داخل `vendors.min.js`، والوسمان
                                     معاً يجعلان الاثنين يعالجان النقرة نفسها --}}
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="{{ __('sales_payment.table.actions') }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('paymentsClient.show', $payment->id) }}">
                                        <i class="feather icon-eye"></i> {{ __('sales_documents.ui.view') }}
                                    </a>

                                    @if ($status !== 5)
                                        @permission('sales.payments.edit')
                                            <a class="dropdown-item" href="{{ route('paymentsClient.edit', $payment->id) }}">
                                                <i class="feather icon-edit-2"></i> {{ __('sales_documents.ui.edit') }}
                                            </a>
                                        @endpermission

                                        @permission('sales.payments.cancel')
                                            <div class="dropdown-divider"></div>

                                            <a class="dropdown-item text-danger cancel-payment" href="#"
                                               data-id="{{ $payment->id }}"
                                               data-code="{{ $payment->reference_number ?: '#'.$payment->id }}">
                                                <i class="feather icon-x-circle"></i> {{ __('sales_payment.show.cancel') }}
                                            </a>
                                        @endpermission
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('sales::payment.partials.pagination', ['payments' => $payments])
@else
    @include('sales::components.empty-state', [
        'icon' => 'credit-card',
        'title' => __('sales_payment.empty.title'),
        'hint' => __('sales_payment.empty.hint'),
    ])
@endif
