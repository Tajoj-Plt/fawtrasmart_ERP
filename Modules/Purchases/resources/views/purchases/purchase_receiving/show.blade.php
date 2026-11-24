{{--
    عرض استلام مشتريات.

    أُعيدت الشاشة إلى نظام تصميم الوحدة نفسه الذي تقوم عليه «عرض فاتورة
    الشراء» و«عرض أمر الشراء»: ترويسة ← لوحة إرشاد ← شريط إجراءات ←
    بطاقةٌ بتبويبات (التفاصيل / البنود / الفواتير / سجلّ النشاطات).

    كانت قبلها بطاقاتٍ مسطَّحةً متتابعة: بيانات ثمّ جدول بنودٍ خام
    (`modern-table` بلا تنسيق) ثمّ قائمة فواتير — بلا تبويبات، وبلا
    إجماليات، وبلا سجلّ نشاطات، وبلا عمود وحدة. فكانت الشاشة الوحيدة في
    الدورة التي تخرج عن شكل أخواتها.

    ما يأتي من الإعدادات:
      • العملة والكسور والرمز ← «العملات»
      • صيغة التاريخ واتجاه الصفحة ← إعدادات اللغة النشطة
      • الألوان والخطّ ← «مظهر النظام»

    قيمة الاستلام في صندوق الإجماليات هي نفسها المقيَّدة في الدفتر عند
    الاعتماد (`PurchaseReceivingAccounting::receiptValue()`) والمحمولة في
    الإذن المخزني — فلا يقرأ المستخدم رقماً في الشاشة وآخر في القيد.
--}}
@extends('purchases::master')

@section('title')
    استلام مشتريات — {{ $receiving->code }}
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

        $isPending = $receiving->status === 'pending';
        $isApproved = $receiving->status === 'approved';
        $totalRemainingToInvoice = collect($lines)->sum('remaining_to_invoice');

        $logs = $logs ?? collect();
        $totals = $totals ?? ['subtotal' => 0, 'discount' => 0, 'tax' => 0, 'grand_total' => 0];

        /* عمود الوحدة لا يظهر إلا حين يحمل بندٌ واحد على الأقل وحدةً مسمّاة.

           و`unit_name` على `InvoiceItem` مُشتقٌّ من `product_unit_id` ويُهمل
           العمود المخزَّن، فيعود فارغاً لبندٍ وحدتُه من وحدات الصنف — وتلك
           تُحفَظ بالاسم والمعامل وحدهما بلا صفٍّ في `product_units`. فيُقرأ
           العمود أوّلاً ويبقى المشتقُّ بديلاً. */
        $unitOf = static function ($orderItem): ?string {
            if (! $orderItem) {
                return null;
            }

            $stored = $orderItem->getAttributes()['unit_name'] ?? null;

            return filled($stored) ? $stored : ($orderItem->unit_name ?: null);
        };

        $hasUnits = collect($lines)->contains(fn ($line) => filled($unitOf($line['order_item'])));
    @endphp

    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => 'استلام مشتريات — '.$receiving->code,
            'icon' => 'fa-dolly',
            'crumbs' => [
                ['label' => 'استلام المشتريات', 'url' => route('PurchaseReceiving.index')],
                ['label' => $receiving->code],
            ],
            'action' => view('purchases::partials.status', ['status' => $receiving->status])->render(),
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد — مفتاحها يخصّ شاشة العرض وحدها --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-receiving.show',
            'title' => 'استلام مشتريات',
            'lead' => 'هذا المستند يسجّل ما وصل فعلياً من أمر الشراء. المخزون والدفتر لا يتحرّكان إلا عند الاعتماد.',
            'open' => false,
            'blocks' => [
                'ماذا يفعل الاعتماد؟' => [
                    'يُدخل الكمّيات إلى المستودع المحدَّد بالوحدة الأساسية.',
                    'يعتمد الإذن المخزني المرافق للاستلام.',
                    'يُرحِّل قيداً محاسبياً: من ح/ المستودع إلى ح/ المشتريات بقيمة البضاعة.',
                    'لا يمكن التراجع عنه — ولا يُحذف استلامٌ معتمَد.',
                ],
                'ثمّ الفوترة' => [
                    'بعد الاعتماد يُحوَّل الاستلام إلى فاتورة شراء — كلياً أو جزئياً.',
                    'الفاتورة تُثبت الالتزام تجاه المورد والضريبة، ولا تُعيد إدخال البضاعة.',
                    'المتبقي للفوترة يظهر في تبويب البنود سطراً بسطر.',
                ],
            ],
            'facts' => [
                'الكمّية محدودة بالمتبقي من كل صنف في أمر الشراء.',
                'الأصناف الخدمية والرقمية لا تدخل المستودع ولا تُقيَّد في حسابه.',
            ],
        ])

        {{-- ══════════ شريط الإجراءات ══════════ --}}
        <div class="po-card">
            <div class="po-card__body">
                <div class="po-actions-grid">
                    @if ($receiving->purchaseOrder)
                        <a href="{{ route('PurchaseOrders.show', $receiving->purchaseOrder->id) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-file-alt"></i><span>عرض أمر الشراء {{ $receiving->purchaseOrder->code }}</span>
                        </a>
                    @endif

                    {{-- كلُّ زرٍّ بحارس صلاحية فعله كما يشترطها المتحكّم
                         (`PurchaseReceivingController::__construct`): كانت
                         الحالةُ وحدَها شرطَ الظهور، فيرى المستخدمُ أزراراً
                         تردّه بـ403 — والاعتمادُ منها يُدخل المخزونَ
                         ويُرحّل القيد، فلا يصحّ عرضُه لمن لا يملكه. --}}
                    @if ($isPending)
                        @permission('purchases.receiving.edit')
                            <a href="{{ route('PurchaseReceiving.edit', $receiving->id) }}" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i><span>تعديل</span>
                            </a>
                        @endpermission

                        @permission('purchases.receiving.approve')
                            <form action="{{ route('PurchaseReceiving.approve', $receiving->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-success"
                                    onclick="return confirm('اعتماد الاستلام يُدخل الكمّيات إلى مخزون المستودع ويُرحِّل قيده المحاسبي فوراً، ولا يمكن التراجع عنه. متابعة؟');">
                                    <i class="fas fa-check"></i><span>اعتماد الاستلام</span>
                                </button>
                            </form>
                        @endpermission

                        @permission('purchases.receiving.delete')
                            <form action="{{ route('PurchaseReceiving.destroy', $receiving->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('حذف هذا الاستلام؟');">
                                    <i class="fas fa-trash-alt"></i><span>حذف</span>
                                </button>
                            </form>
                        @endpermission
                    @endif

                    @if ($isApproved && $totalRemainingToInvoice > 0)
                        @permission('purchases.receiving.convert')
                            <button type="button" class="btn btn-outline-info" data-bs-toggle="collapse" data-bs-target="#convertPanel">
                                <i class="fas fa-file-invoice-dollar"></i><span>تحويل إلى فاتورة شراء</span>
                            </button>
                        @endpermission
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════ لوحة التحويل إلى فاتورة ══════════ --}}
        @if ($isApproved && $totalRemainingToInvoice > 0)
            <div class="collapse" id="convertPanel">
                <div class="po-card">
                    <div class="po-card__head">
                        <h6 class="po-card__title">
                            <i class="fas fa-file-invoice-dollar"></i>تحويل إلى فاتورة شراء
                        </h6>
                        <span class="po-hint">الكمّية الافتراضية أدناه هي كامل المتبقي للفوترة من كل سطر — يمكن تقليلها لفوترة جزء فقط والاحتفاظ بالباقي لفاتورة لاحقة.</span>
                    </div>

                    <form action="{{ route('PurchaseReceiving.convertToInvoice', $receiving->id) }}" method="POST">
                        @csrf
                        <div class="table-responsive">
                            <table class="table modern-table po-table po-doc-table mb-0">
                                <thead>
                                    <tr>
                                        <th>الصنف</th>
                                        @if ($hasUnits)
                                            <th class="text-center" style="width:110px">{{ __('purchase_orders.items.unit') }}</th>
                                        @endif
                                        <th class="text-center">المستلم في هذا الاستلام</th>
                                        <th class="text-center">المفوتر سابقاً</th>
                                        <th class="text-center">المتبقي للفوترة</th>
                                        <th style="width:160px">كمّية هذه الفاتورة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lines as $i => $line)
                                        @if ($line['remaining_to_invoice'] > 0)
                                            <tr>
                                                <td data-label="الصنف">
                                                    {{ $line['order_item']->item ?? '—' }}
                                                    <input type="hidden" name="items[{{ $i }}][purchase_receiving_item_id]" value="{{ $line['item']->id }}">
                                                </td>
                                                @if ($hasUnits)
                                                    <td class="text-center" data-label="{{ __('purchase_orders.items.unit') }}">
                                                        @if (filled($unitOf($line['order_item'])))
                                                            {{ $unitOf($line['order_item']) }}
                                                        @else
                                                            <span class="po-unit-none">&mdash;</span>
                                                        @endif
                                                    </td>
                                                @endif
                                                <td class="text-center num" data-label="المستلم">{{ LanguageSettings::formatNumber($line['item']->received_quantity) }}</td>
                                                <td class="text-center num" data-label="المفوتر سابقاً">{{ LanguageSettings::formatNumber($line['item']->invoiced_quantity) }}</td>
                                                <td class="text-center num" data-label="المتبقي للفوترة">{{ LanguageSettings::formatNumber($line['remaining_to_invoice']) }}</td>
                                                <td data-label="كمّية هذه الفاتورة">
                                                    <input type="number" step="0.0001" min="0"
                                                        max="{{ $line['remaining_to_invoice'] }}"
                                                        name="items[{{ $i }}][invoice_quantity]"
                                                        class="form-control po-input"
                                                        value="{{ $line['remaining_to_invoice'] }}">
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="po-card__body">
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6">
                                    <div class="po-field">
                                        <label for="payment_status">حالة الدفع</label>
                                        <select name="payment_status" id="payment_status" class="form-control">
                                            <option value="deferred">آجل — بلا دفع الآن</option>
                                            <option value="paid">دفع كامل</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="po-field">
                                        <label for="advance_amount">دفعة مقدَّمة (اختياري)</label>
                                        <input type="number" step="0.01" min="0" name="advance_amount" id="advance_amount" class="form-control" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="po-card__foot">
                            <div></div>
                            <div class="po-toolbar">
                                <button type="submit" class="btn btn-info" onclick="return confirm('سيتم إنشاء فاتورة شراء بالكمّيات المحدَّدة. متابعة؟');">
                                    <i class="fas fa-file-invoice-dollar me-1"></i> تحويل إلى فاتورة
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- ══════════ التبويبات ══════════ --}}
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
                            {{ LanguageSettings::formatNumber(count($lines), 0) }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#invoices" role="tab">
                        <i class="fas fa-file-invoice-dollar me-1"></i>الفواتير
                        <span class="po-status po-status--quotation ms-1">
                            {{ LanguageSettings::formatNumber($receiving->invoices->count(), 0) }}
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
                {{-- ── التفاصيل ── --}}
                <div class="tab-pane active" id="details" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.details', [
                            'details' => [
                                ['icon' => 'fa-hashtag', 'label' => 'الرقم', 'value' => $receiving->code],
                                ['icon' => 'fa-file-alt', 'label' => 'أمر الشراء', 'value' => $receiving->purchaseOrder->code ?? null],
                                ['icon' => 'fa-truck', 'label' => 'المورد', 'value' => $receiving->supplier->trade_name ?? $receiving->supplier->name ?? null],
                                ['icon' => 'fa-warehouse', 'label' => 'المستودع', 'value' => $receiving->storeHouse->name ?? null],
                                ['icon' => 'fa-calendar', 'label' => 'تاريخ الاستلام', 'value' => LanguageSettings::formatDate($receiving->receiving_date)],
                                ['icon' => 'fa-coins', 'label' => __('purchase_cycle.fields.currency'), 'value' => CurrencySettings::name().' ('.CurrencySettings::symbol().')'],
                                ['icon' => 'fa-user', 'label' => 'أنشأه', 'value' => $receiving->creator->name ?? null],
                                ['icon' => 'fa-user-check', 'label' => 'اعتمده', 'value' => $receiving->approver->name ?? null],
                                ['icon' => 'fa-clipboard-check', 'label' => 'تاريخ الاعتماد', 'value' => $receiving->approved_at ? LanguageSettings::formatDate($receiving->approved_at, true) : null],
                                ['icon' => 'fa-file-signature', 'label' => 'الإذن المخزني', 'value' => $receiving->warehousePermit->number ?? null],
                            ],
                        ])

                        <hr>

                        <div class="po-detail__label">
                            <i class="fas fa-sticky-note"></i> ملاحظات
                        </div>
                        <div class="po-detail__value">
                            {{ $receiving->notes ?: __('purchase_orders.empty.no_notes') }}
                        </div>
                    </div>
                </div>

                {{-- ── البنود ── --}}
                <div class="tab-pane" id="items" role="tabpanel">
                    @if (count($lines) === 0)
                        <div class="po-empty">
                            <i class="fas fa-box-open"></i>
                            <h5>{{ __('purchase_orders.items.empty') }}</h5>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table modern-table po-table po-doc-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width:46px">{{ __('purchase_orders.items.row') }}</th>
                                        <th>{{ __('purchase_orders.items.item') }}</th>
                                        <th class="text-center">الكمّية المستلمة</th>
                                        {{-- الوحدة عمودٌ قائمٌ بذاته كما في جدول بنود
                                             الدورة: كمّيةٌ تُقرأ بلا وحدتها تُستلَم
                                             بالحبّة وقد طُلبت بالكرتون --}}
                                        @if ($hasUnits)
                                            <th class="text-center" style="width:120px">{{ __('purchase_orders.items.unit') }}</th>
                                        @endif
                                        <th class="text-center">{{ __('purchase_orders.items.unit_price') }}</th>
                                        <th class="text-center">{{ __('purchase_orders.items.total') }}</th>
                                        <th class="text-center">المفوتر</th>
                                        <th class="text-center">المتبقي للفوترة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lines as $index => $line)
                                        @php
                                            $unitPrice = (float) ($line['order_item']->unit_price ?? 0);
                                            $lineTotal = (float) $line['item']->received_quantity * $unitPrice;
                                        @endphp
                                        <tr>
                                            <td class="text-center num" data-label="{{ __('purchase_orders.items.row') }}">{{ LanguageSettings::formatNumber($index + 1, 0) }}</td>
                                            <td data-label="{{ __('purchase_orders.items.item') }}">{{ $line['order_item']->item ?? '—' }}</td>
                                            <td class="text-center num" data-label="الكمّية المستلمة">{{ LanguageSettings::formatNumber($line['item']->received_quantity) }}</td>
                                            @if ($hasUnits)
                                                <td class="text-center" data-label="{{ __('purchase_orders.items.unit') }}">
                                                    @if (filled($unitOf($line['order_item'])))
                                                        {{ $unitOf($line['order_item']) }}
                                                        @if ((float) ($line['order_item']->conversion_factor ?? 1) != 1)
                                                            <small class="po-hint">
                                                                (× {{ LanguageSettings::formatNumber($line['order_item']->conversion_factor, 2) }})
                                                            </small>
                                                        @endif
                                                    @else
                                                        <span class="po-unit-none">&mdash;</span>
                                                    @endif
                                                </td>
                                            @endif
                                            <td class="text-center num" data-label="{{ __('purchase_orders.items.unit_price') }}">{{ CurrencySettings::number($unitPrice) }}</td>
                                            <td class="text-center num" data-label="{{ __('purchase_orders.items.total') }}">{{ CurrencySettings::format($lineTotal) }}</td>
                                            <td class="text-center num" data-label="المفوتر">{{ LanguageSettings::formatNumber($line['item']->invoiced_quantity) }}</td>
                                            <td class="text-center num" data-label="المتبقي للفوترة">{{ LanguageSettings::formatNumber($line['remaining_to_invoice']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @include('purchases::partials.totals', ['totals' => $totals])
                    @endif
                </div>

                {{-- ── الفواتير ── --}}
                <div class="tab-pane" id="invoices" role="tabpanel">
                    <div class="po-card__body">
                        @if ($receiving->invoices->isEmpty())
                            <div class="po-empty">
                                <i class="fas fa-file-invoice"></i>
                                <h5>لم تُنشأ فواتير من هذا الاستلام بعد</h5>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table modern-table po-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>الرقم</th>
                                            <th class="text-center">التاريخ</th>
                                            <th class="text-center">الإجمالي</th>
                                            <th class="text-center">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($receiving->invoices as $invoice)
                                            <tr>
                                                <td data-label="الرقم">
                                                    <a href="{{ route('invoicePurchases.show', $invoice->id) }}">{{ $invoice->code }}</a>
                                                </td>
                                                <td class="text-center" data-label="التاريخ">{{ LanguageSettings::formatDate($invoice->date ?? $invoice->created_at) }}</td>
                                                <td class="text-center num" data-label="الإجمالي">{{ CurrencySettings::format($invoice->grand_total ?? 0) }}</td>
                                                <td class="text-center" data-label="الحالة">
                                                    @include('purchases::partials.status', ['status' => $invoice->status])
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ── سجلّ النشاطات ── --}}
                <div class="tab-pane" id="activity" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.activity', ['logs' => $logs])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
