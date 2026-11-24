{{--
    عرض فاتورة مشتريات المتجر (أو مرتجع شراء).

    الشاشة مبنيّة على نظام تصميم وحدة المشتريات نفسه (`po-*`): الأقسام نفسها
    بالترتيب نفسه الذي تعرض به `purchases::purchases.invoices_purchase.show`
    فاتورة الشراء — شارات الحالة ← الإجراءات ← ألسنة (التفاصيل / البنود /
    المدفوعات / القيود المحاسبية / سجل النشاطات) — فلا يرى المستخدم الفاتورة
    الواحدة بشكلين بين القسمين.

    ما يختلف عن شاشة المشتريات، ولماذا:
      • بطاقة المورد الموحّدة أعلى الصفحة — الحقول نفسها في النموذج والطباعة.
      • الدفع من نافذة داخل الصفحة (`store_purchase_invoices.pay`) لا من شاشة
        «سندات دفع الموردين»: المتجر لا يملك تلك الشاشة، والرابط القديم كان
        يشير إلى مسارٍ غير معرَّف.
      • لا «نسخ» ولا «مركز تكلفة» ولا «ملاحظات» ولا تعديل/حذف دفعة: لا مسار
        لها في المتجر، وزرٌّ بلا مسار يُسقط الصفحة أو يفشل بصمت.
      • المبالغ بعملة الفاتورة المحفوظة معها (`$currencyCode`) لا بالعملة
        الافتراضية الحالية — فتغيير عملة النظام لا يغيّر مستنداً قديماً.
--}}
@extends('onlinestore::master')

@section('title', ($purchaseInvoice->isPurchaseReturn() ? 'مرتجع شراء ' : 'فاتورة مشتريات ').$purchaseInvoice->code)

@push('page-css')
    {{-- ورقة الخطّ الزمني التي تُصمِّم .timeline و.timeline-item في سجل النشاطات --}}
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
@endpush

@section('content')
    @include('purchases::partials.style')
    @include('purchases::partials.doc-theme-sl')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;
        use Modules\Purchases\Services\PurchaseSettings;
        use Modules\Purchases\Support\OrderLine;

        $items = $purchaseInvoice->items;
        $payments = $purchaseInvoice->payments;
        $isReturn = $purchaseInvoice->isPurchaseReturn();
        $journalEntries = $journalEntries ?? collect();
        $treasuries = $treasuries ?? collect();

        // كل مبلغ في الشاشة يمرّ من هنا: فواصل وكسور «العملات»، ورمز عملة الفاتورة نفسها
        $money = static fn ($amount) => CurrencySettings::number($amount).' '.$currencyCode;

        // المدفوع مجموع سندات الدفع المرتبطة — لا عمود `paid_amount` في الجدول
        $paidAmount = (float) $payments->sum('amount');
        $remaining = $isReturn ? 0.0 : (float) ($remainingAmount ?? 0);

        // الإجماليات محفوظة مع الفاتورة فتُقرأ منها ولا يُعاد حسابها، حتى لا
        // يختلف المعروض عمّا قُيِّد محاسبياً
        $totals = [
            'subtotal' => (float) ($purchaseInvoice->subtotal ?? 0),
            'discount' => (float) ($purchaseInvoice->total_discount ?? 0),
            'shipping' => (float) ($purchaseInvoice->shipping_cost ?? 0),
            'tax' => (float) ($purchaseInvoice->total_tax ?? 0),
            'grand_total' => (float) ($purchaseInvoice->grand_total ?? 0),
            'paid' => $paidAmount,
            'remaining' => $remaining,
        ];

        // السطر المشروط لا يُرسَم إلا إذا كان لقيمته وجود — نفس قاعدة
        // `purchases::partials.totals`، و«إجمالي الخصومات» يتبع إعداده هناك
        $shown = static fn (string $key) => abs($totals[$key] ?? 0) >= 0.005;
        $showDiscount = PurchaseSettings::enabled('total_discounts') && $shown('discount');

        $paymentVariants = [
            'paid' => ['po-status--approved', 'fa-check-circle', __('purchase_cycle.invoice.paid')],
            'partial' => ['po-status--review', 'fa-adjust', __('purchase_cycle.invoice.partial')],
            'partially_paid' => ['po-status--review', 'fa-adjust', __('purchase_cycle.invoice.partial')],
            'unpaid' => ['po-status--rejected', 'fa-times-circle', __('purchase_cycle.invoice.unpaid')],
        ];

        $receivingVariants = [
            'received' => ['po-status--approved', 'fa-box-open', __('purchase_cycle.invoice.received')],
            'partial_received' => ['po-status--review', 'fa-dolly', __('purchase_cycle.invoice.partially_received')],
            'partially_received' => ['po-status--review', 'fa-dolly', __('purchase_cycle.invoice.partially_received')],
            'partially_receiv' => ['po-status--review', 'fa-dolly', __('purchase_cycle.invoice.partially_received')],
            'not_received' => ['po-status--quotation', 'fa-clock', __('purchase_cycle.invoice.not_received')],
        ];

        // حالة الإذن المخزني — `apporod` هي القيمة المخزَّنة فعلاً للمعتمَد
        $permitVariants = [
            'apporod' => ['po-status--approved', 'fa-check-circle', 'معتمَد'],
            'pending' => ['po-status--review', 'fa-clock', 'بانتظار الاعتماد'],
            'rejected' => ['po-status--rejected', 'fa-times-circle', 'مرفوض'],
        ];

        $badge = static fn (?array $v) => $v
            ? '<span class="po-status '.$v[0].'"><i class="fas '.$v[1].'"></i>'.e($v[2]).'</span>'
            : '';

        // المرتجع لا يُسدَّد ولا يُستلم: شارته نوعُه، لا حالة دفعٍ لا معنى لها
        $receiving = $receivingVariants[$purchaseInvoice->receiving_status] ?? null;
        $statusBadges = $isReturn
            ? $badge(['po-status--quotation', 'fa-undo', 'مرتجع شراء'])
            : $badge($paymentVariants[$purchaseInvoice->payment_status] ?? $paymentVariants['unpaid']).' '.$badge($receiving);

        // إذن المستند المخزني: إذن الإضافة للفاتورة، وإذن الإخراج للمرتجع
        $permit = $isReturn ? ($returnPermit ?? null) : ($invoicePermit ?? null);
        $permitVariant = $permit ? ($permitVariants[$permit->status] ?? ['po-status--quotation', 'fa-circle', (string) $permit->status]) : null;
        $permitUrl = $permit && \Illuminate\Support\Facades\Route::has('online_store.permits.show') && hasPermission('stock.permits.view')
            ? route('online_store.permits.show', $permit->id) : null;

        // الأزرار تتبع صلاحيات المسارات نفسها — زرٌّ ظاهر يُرفض بعد الضغط أسوأ من زرٍّ مخفيّ
        $mayView = hasPermission('purchases.invoices.view');
        $mayEdit = ($canEdit ?? false) && hasPermission('purchases.invoices.edit');
        $mayDelete = ($canDelete ?? false) && hasPermission('purchases.invoices.delete');
        $mayPay = ($canPay ?? false) && hasPermission('purchases.payments.create');
        $mayReturn = ! $isReturn && empty($existingReturn) && $purchaseInvoice->type === 'invoice' && hasPermission('purchases.invoices.create');

        // النافذة تُعاد مفتوحةً إذا رُفضت الدفعة — وإلا ضاع الخطأ خلف صفحةٍ تبدو سليمة
        $payFields = ['amount', 'payment_date', 'treasury_id', 'payment_method', 'notes'];
        $payHasErrors = $mayPay && $errors->hasAny($payFields);

        $paymentMethods = ['1' => 'نقداً', '2' => 'تحويل بنكي', '3' => 'شيك'];
        // سندات أقدم حفظت الطريقة نصّاً (`cash`/`bank`/`check`) — تُقرأ كلتاهما
        $methodLabel = static function ($value) use ($paymentMethods) {
            $legacy = ['cash' => 'نقداً', 'bank' => 'تحويل بنكي', 'check' => 'شيك'];

            return $paymentMethods[(string) $value] ?? $legacy[(string) $value] ?? (filled($value) ? (string) $value : '—');
        };

        // أسماء الخزائن دفعةً واحدة — لا استعلام لكل سند
        $treasuryNames = $payments->pluck('treasury_id')->filter()->isNotEmpty()
            ? \App\Models\Account::whereIn('id', $payments->pluck('treasury_id')->filter()->unique())->pluck('name', 'id')
            : collect();

        // اسم الوحدة: العمود المخزَّن أوّلاً (المُشتقّ يعود فارغاً لوحدات الصنف)
        $unitOf = static fn ($item) => filled($item->getAttributes()['unit_name'] ?? null)
            ? $item->getAttributes()['unit_name'] : ($item->unit_name ?: null);
        $hasUnits = $items->contains(fn ($item) => filled($unitOf($item)));
    @endphp

    <div class="content-body">
        @include('onlinestore::components.page-head', [
            'title' => ($isReturn ? 'مرتجع شراء' : __('purchase_cycle.documents.purchase_invoice')).' — '.$purchaseInvoice->code,
            'icon' => $isReturn ? 'rotate-ccw' : 'file-text',
            'sub' => ($purchaseInvoice->supplier->name ?? 'مورد غير محدد').' — '.LanguageSettings::formatDate($purchaseInvoice->date ?? $purchaseInvoice->created_at),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'فواتير الشراء' => route('store_purchase_invoices.index'),
                $purchaseInvoice->code => null,
            ],
            'actions' => $statusBadges,
        ])

        <div class="po-page po-doc--sl">
            @include('layouts.alerts.error')
            @include('layouts.alerts.success')

            @if ($isReturn)
                <div class="alert alert-info d-flex align-items-center gap-2">
                    <i class="fas fa-undo"></i>
                    <div>
                        هذا مرتجع لفاتورة الشراء
                        @if ($originalInvoice)
                            <a href="{{ route('store_purchase_invoices.show', $originalInvoice->id) }}"><strong>{{ $originalInvoice->code }}</strong></a>
                        @else
                            <strong>#{{ $purchaseInvoice->reference_id }}</strong> (محذوفة)
                        @endif
                        @unless ($permit)
                            — <span class="text-danger">لا إذن مخزني مرتبط به، فلن تُخصَم الكمية من المخزون تلقائياً.</span>
                        @endunless
                    </div>
                </div>
            @elseif (! empty($existingReturn))
                <div class="alert alert-warning d-flex align-items-center gap-2">
                    <i class="fas fa-undo"></i>
                    <div>
                        {{ __('purchase_cycle.return.has_return') }}:
                        <a href="{{ route('store_purchase_invoices.show', $existingReturn->id) }}"><strong>{{ $existingReturn->code }}</strong></a>
                        — لذلك لا تُعدَّل ولا تُسدَّد.
                    </div>
                </div>
            @endif

            {{-- بطاقة بيانات المورد — نفسها في نموذج الفاتورة وصفحتها وطباعتها --}}
            @include('onlinestore::suppliers.partials.supplier-card', ['supplier' => $purchaseInvoice->supplier])

            {{-- الإجراءات --}}
            <div class="po-card">
                <div class="po-card__body">
                    <div class="po-actions-grid">
                        @if ($mayEdit)
                            <a href="{{ route('store_purchase_invoices.edit', $purchaseInvoice->id) }}" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i><span>{{ __('purchase_cycle.actions.edit') }}</span>
                            </a>
                        @endif

                        {{-- زرٌّ عاديّ بلا data-toggle: محرّكا Bootstrap 4 و5 محمَّلان معاً
                             في هذه اللوحة، فتُفتح النافذة بدالّةٍ واحدة أدناه لا بسمةٍ
                             يتنازعانها --}}
                        @if ($mayPay)
                            <button type="button" class="btn btn-outline-success" data-store-pay-open>
                                <i class="fas fa-credit-card"></i><span>تسجيل دفعة</span>
                            </button>
                        @endif

                        @if ($mayView)
                            @include('purchases::partials.print-menu', [
                                'templates' => $documentTemplates ?? collect(),
                                'default' => $defaultTemplate ?? null,
                                'prefersPdf' => $prefersPdf ?? false,
                                'printRoute' => 'store_purchase_invoices.print',
                                'pdfRoute' => 'store_purchase_invoices.pdf',
                                'documentId' => $purchaseInvoice->id,
                            ])

                            {{-- مخرجات إضافية لا تمرّ بقوالب المستندات --}}
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-file-export"></i><span>{{ __('purchase_cycle.actions.options') }}</span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('store_purchase_invoices.thermal', $purchaseInvoice->id) }}" target="_blank" rel="noopener">
                                            <i class="fas fa-receipt me-2 text-secondary"></i>طباعة حرارية (80 مم)
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('store_purchase_invoices.compact', $purchaseInvoice->id) }}" target="_blank" rel="noopener">
                                            <i class="fas fa-file-alt me-2 text-secondary"></i>نسخة مختصرة (A4)
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('store_purchase_invoices.notes_pdf', $purchaseInvoice->id) }}" target="_blank" rel="noopener">
                                            <i class="fas fa-sticky-note me-2 text-secondary"></i>{{ __('purchase_cycle.invoice.notes_pdf') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        @endif

                        {{-- الفاتورة الواحدة لا تُردّ مرتين: متى وُجد لها مرتجع حلّ
                             محلَّ الزرّ رابطٌ إليه، فلا يقود إلى شاشةٍ سترفض الطلب --}}
                        @if (! $isReturn && ! empty($existingReturn))
                            <a href="{{ route('store_purchase_invoices.show', $existingReturn->id) }}" class="btn btn-outline-warning">
                                <i class="fas fa-undo"></i><span>{{ __('purchase_cycle.return.has_return') }} — {{ $existingReturn->code }}</span>
                            </a>
                        @elseif ($mayReturn)
                            <a href="{{ route('store_purchase_invoices.create') }}?return_from={{ $purchaseInvoice->id }}" class="btn btn-outline-warning">
                                <i class="fas fa-undo"></i><span>{{ __('purchase_cycle.invoice.create_return') }}</span>
                            </a>
                        @endif

                        @if ($mayDelete)
                            <form action="{{ route('store_purchase_invoices.destroy', $purchaseInvoice->id) }}" method="POST" id="storeInvoiceDeleteForm">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-trash-alt"></i><span>{{ __('purchase_cycle.actions.delete') }}</span>
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('store_purchase_invoices.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-right"></i><span>العودة للقائمة</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="po-card">
                <ul class="nav po-tabs" role="tablist" id="storeInvoiceTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#details" role="tab">
                            <i class="fas fa-info-circle me-1"></i>{{ __('purchase_orders.tabs.details') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#items" role="tab">
                            <i class="fas fa-boxes me-1"></i>{{ __('purchase_orders.tabs.items') }}
                            <span class="po-status po-status--quotation ms-1">{{ LanguageSettings::formatNumber($items->count(), 0) }}</span>
                        </a>
                    </li>
                    @unless ($isReturn)
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#payments" role="tab">
                                <i class="fas fa-money-bill-wave me-1"></i>{{ __('purchase_cycle.invoice.payments') }}
                                <span class="po-status po-status--quotation ms-1">{{ LanguageSettings::formatNumber($payments->count(), 0) }}</span>
                            </a>
                        </li>
                    @endunless
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#journal" role="tab">
                            <i class="fas fa-book me-1"></i>القيود المحاسبية
                            <span class="po-status po-status--quotation ms-1">{{ LanguageSettings::formatNumber($journalEntries->count(), 0) }}</span>
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
                    <div class="tab-pane active show" id="details" role="tabpanel">
                        <div class="po-card__body">
                            @include('purchases::partials.details', [
                                'details' => array_values(array_filter([
                                    ['icon' => 'fa-hashtag', 'label' => __('purchase_cycle.fields.code'), 'value' => $purchaseInvoice->code],
                                    ['icon' => 'fa-truck', 'label' => __('purchase_cycle.fields.supplier'), 'value' => $purchaseInvoice->supplier->name ?? null],
                                    ['icon' => 'fa-calendar', 'label' => __('purchase_cycle.fields.date'), 'value' => LanguageSettings::formatDate($purchaseInvoice->date ?? $purchaseInvoice->created_at)],
                                    ['icon' => 'fa-code-branch', 'label' => 'الفرع', 'value' => $purchaseInvoice->branch->name ?? null],
                                    $isReturn ? null : ['icon' => 'fa-box-open', 'label' => __('purchase_cycle.invoice.receiving_status'), 'value' => $receiving[2] ?? null],
                                    ['icon' => 'fa-user', 'label' => __('purchase_cycle.fields.creator'), 'value' => $purchaseInvoice->creator->name ?? ($purchaseInvoice->user->name ?? null)],
                                    ['icon' => 'fa-box', 'label' => __('purchase_cycle.fields.items_count'), 'value' => LanguageSettings::formatNumber($items->count(), 0)],
                                    ['icon' => 'fa-file-invoice-dollar', 'label' => __('purchase_orders.totals.grand_total'), 'value' => $money($totals['grand_total'])],
                                    $isReturn ? null : ['icon' => 'fa-money-bill-wave', 'label' => __('purchase_cycle.invoice.paid_amount'), 'value' => $money($paidAmount)],
                                    $isReturn ? null : ['icon' => 'fa-hourglass-half', 'label' => __('purchase_cycle.invoice.due_value'), 'value' => $money($remaining)],
                                    ['icon' => 'fa-coins', 'label' => __('purchase_cycle.fields.currency'), 'value' => $currencyCode],
                                    ['icon' => 'fa-clock', 'label' => __('purchase_orders.fields.updated_at'), 'value' => LanguageSettings::formatDate($purchaseInvoice->updated_at, true)],
                                ])),
                            ])

                            {{-- الإذن المخزني خارج الشبكة: قيمته شارةٌ ورابط، والشبكة تعرض نصّاً فقط --}}
                            <hr>
                            <div class="po-detail__label">
                                <i class="fas fa-warehouse"></i>{{ $isReturn ? 'إذن إخراج البضاعة' : 'الإذن المخزني' }}
                            </div>
                            <div class="po-detail__value d-flex align-items-center flex-wrap gap-2">
                                @if ($permit)
                                    <strong class="num">{{ $permit->number }}</strong>
                                    {!! $badge($permitVariant) !!}
                                    @if ($permit->status === 'pending')
                                        <span class="po-hint">
                                            {{ $isReturn ? 'تُخصَم الكمية من المخزون عند اعتماده' : 'تدخل الكمية المخزون عند اعتماده' }}
                                            من شاشة «الأذون المخزنية».
                                        </span>
                                    @endif
                                    @if ($permitUrl)
                                        <a href="{{ $permitUrl }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-external-link-alt"></i> عرض الإذن
                                        </a>
                                    @endif
                                @else
                                    <span class="text-muted">لا إذن مخزني مرتبط بهذا المستند.</span>
                                @endif
                            </div>

                            @if ($TaxsInvoice->isNotEmpty())
                                <hr>
                                <div class="po-detail__label">
                                    <i class="fas fa-percentage"></i>{{ __('purchase_orders.items.tax') }}
                                </div>
                                <div class="table-responsive">
                                    <table class="table modern-table po-table mb-0">
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
                                                    <td class="text-center num">{{ $money($tax->value) }}</td>
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

                    {{-- البنود — أعمدة `purchases::partials.items-table` نفسها، بعملة الفاتورة --}}
                    <div class="tab-pane" id="items" role="tabpanel">
                        @if ($items->isEmpty())
                            <div class="po-empty">
                                <i class="fas fa-box-open"></i>
                                <h5>{{ __('purchase_orders.items.empty') }}</h5>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table modern-table po-table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 46px;">{{ __('purchase_orders.items.row') }}</th>
                                            <th>{{ __('purchase_orders.items.item') }}</th>
                                            <th>{{ __('purchase_orders.items.description') }}</th>
                                            <th class="text-center">{{ __('purchase_orders.items.quantity') }}</th>
                                            @if ($hasUnits)
                                                <th class="text-center">{{ __('purchase_orders.items.unit') }}</th>
                                            @endif
                                            <th class="text-center">{{ __('purchase_orders.items.unit_price') }}</th>
                                            <th class="text-center">{{ __('purchase_orders.items.discount') }}</th>
                                            <th class="text-center">{{ __('purchase_orders.items.tax') }}</th>
                                            <th class="text-center">{{ __('purchase_orders.items.total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($items as $item)
                                            <tr>
                                                <td class="text-center num" data-label="{{ __('purchase_orders.items.row') }}">{{ LanguageSettings::formatNumber($loop->iteration, 0) }}</td>
                                                <td data-label="{{ __('purchase_orders.items.item') }}">
                                                    {{ $item->product->name ?? ($item->item ?: '—') }}
                                                    @if (filled($item->product->sku ?? null))
                                                        <small class="po-hint d-block">SKU: {{ $item->product->sku }}</small>
                                                    @endif
                                                </td>
                                                <td data-label="{{ __('purchase_orders.items.description') }}">{{ $item->description ?: '—' }}</td>
                                                <td class="text-center num" data-label="{{ __('purchase_orders.items.quantity') }}">{{ LanguageSettings::formatNumber($item->quantity ?? 0, 0) }}</td>
                                                @if ($hasUnits)
                                                    <td class="text-center" data-label="{{ __('purchase_orders.items.unit') }}">
                                                        @if (filled($unitOf($item)))
                                                            {{ $unitOf($item) }}
                                                            @if ((float) ($item->conversion_factor ?? 1) != 1)
                                                                <small class="po-hint">(× {{ LanguageSettings::formatNumber($item->conversion_factor, 2) }})</small>
                                                            @endif
                                                        @else
                                                            <span class="po-unit-none">&mdash;</span>
                                                        @endif
                                                    </td>
                                                @endif
                                                <td class="text-center num" data-label="{{ __('purchase_orders.items.unit_price') }}">{{ CurrencySettings::number($item->unit_price ?? 0) }}</td>
                                                <td class="text-center num" data-label="{{ __('purchase_orders.items.discount') }}">{{ CurrencySettings::number($item->discount ?? 0) }}</td>
                                                {{-- مبلغ الضريبة من «إعدادات الضريبة» عبر OrderLine — لا عمود
                                                     `tax` في البند، فكانت الخانة صفراً دائماً --}}
                                                <td class="text-center num" data-label="{{ __('purchase_orders.items.tax') }}">
                                                    {{ CurrencySettings::number(OrderLine::tax($item)) }}
                                                    <small class="po-hint">
                                                        ({{ OrderLine::taxRate($item) + 0 }}%@if (OrderLine::isTaxIncluded($item))
                                                            — {{ __('purchase_orders.items.tax_included') }}
                                                        @endif)
                                                    </small>
                                                </td>
                                                <td class="text-center num" data-label="{{ __('purchase_orders.items.total') }}">{{ $money($item->total ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- صندوق الإجماليات بهيئة `purchases::partials.totals` — مرسومٌ هنا
                                 لأن تلك الجزئية تطبع رمز العملة الافتراضية لا عملة الفاتورة --}}
                            <div class="po-card__foot">
                                <span class="po-hint"><i class="fas fa-coins me-1"></i>{{ $currencyCode }}</span>

                                <div class="po-totals">
                                    <div class="po-totals__row">
                                        <span>{{ __('purchase_orders.totals.subtotal') }}</span>
                                        <span>{{ $money($totals['subtotal']) }}</span>
                                    </div>
                                    @if ($showDiscount)
                                        <div class="po-totals__row">
                                            <span>{{ __('purchase_orders.totals.discount') }}</span>
                                            <span>{{ $money($totals['discount']) }}</span>
                                        </div>
                                    @endif
                                    @if ($shown('shipping'))
                                        <div class="po-totals__row">
                                            <span>{{ __('purchase_orders.totals.shipping') }}</span>
                                            <span>{{ $money($totals['shipping']) }}</span>
                                        </div>
                                    @endif
                                    @if ($shown('tax'))
                                        <div class="po-totals__row">
                                            <span>{{ __('purchase_orders.totals.tax') }}</span>
                                            <span>{{ $money($totals['tax']) }}</span>
                                        </div>
                                    @endif
                                    <div class="po-totals__row po-totals__row--grand">
                                        <span>{{ __('purchase_orders.totals.grand_total') }}</span>
                                        <span>{{ $money($totals['grand_total']) }}</span>
                                    </div>
                                    @unless ($isReturn)
                                        @if ($shown('paid'))
                                            <div class="po-totals__row">
                                                <span>{{ __('purchase_orders.totals.paid') }}</span>
                                                <span>{{ $money($totals['paid']) }}</span>
                                            </div>
                                        @endif
                                        @if ($shown('remaining'))
                                            <div class="po-totals__row">
                                                <span>{{ __('purchase_orders.totals.remaining') }}</span>
                                                <span>{{ $money($totals['remaining']) }}</span>
                                            </div>
                                        @endif
                                    @endunless
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- المدفوعات --}}
                    @unless ($isReturn)
                        <div class="tab-pane" id="payments" role="tabpanel">
                            @if ($payments->isEmpty())
                                <div class="po-empty">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <h5>{{ __('purchase_cycle.invoice.no_payments') }}</h5>
                                    @if ($mayPay)
                                        <button type="button" class="btn btn-primary" data-store-pay-open>
                                            <i class="fas fa-plus-circle me-1"></i>تسجيل دفعة
                                        </button>
                                    @endif
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table modern-table po-table mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width: 46px;">{{ __('purchase_orders.items.row') }}</th>
                                                <th class="text-center">{{ __('purchase_cycle.invoice.payment_date') }}</th>
                                                <th class="text-center">{{ __('purchase_cycle.invoice.amount') }}</th>
                                                <th>{{ __('purchase_cycle.invoice.payment_method') }}</th>
                                                <th>الخزينة</th>
                                                <th>{{ __('purchase_cycle.fields.notes') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($payments as $payment)
                                                <tr>
                                                    <td class="text-center num">{{ LanguageSettings::formatNumber($loop->iteration, 0) }}</td>
                                                    <td class="text-center num">{{ LanguageSettings::formatDate($payment->payment_date ?? $payment->created_at) }}</td>
                                                    <td class="text-center num">{{ $money($payment->amount ?? 0) }}</td>
                                                    <td>{{ $methodLabel($payment->payment_method ?? $payment->Payment_method) }}</td>
                                                    <td>{{ $treasuryNames[$payment->treasury_id] ?? '—' }}</td>
                                                    <td>{{ $payment->notes ?: '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr style="border-top: 2px solid var(--po-border);">
                                                <td colspan="2" style="font-weight: 600;">{{ __('purchase_cycle.invoice.paid_amount') }}</td>
                                                <td class="text-center num" style="font-weight: 600;">{{ $money($paidAmount) }}</td>
                                                <td colspan="2" style="font-weight: 600;">{{ __('purchase_cycle.invoice.due_value') }}</td>
                                                <td class="num" style="font-weight: 600;">{{ $money($remaining) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                @if ($mayPay)
                                    <div class="po-card__foot">
                                        <span class="po-hint">المتبقّي على الفاتورة: {{ $money($remaining) }}</span>
                                        <button type="button" class="btn btn-primary" data-store-pay-open>
                                            <i class="fas fa-plus-circle me-1"></i>تسجيل دفعة
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endunless

                    {{-- القيود المحاسبية — قيد الشراء ثم قيود السداد/المرتجع المرتبطة بالمستند --}}
                    <div class="tab-pane" id="journal" role="tabpanel">
                        @if ($journalEntries->isEmpty())
                            <div class="po-empty">
                                <i class="fas fa-book"></i>
                                <h5>لا قيد محاسبي مرتبط بهذا المستند</h5>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table modern-table po-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>رقم القيد</th>
                                            <th class="text-center">التاريخ</th>
                                            <th>البيان</th>
                                            <th>الحساب</th>
                                            <th class="text-center">مدين</th>
                                            <th class="text-center">دائن</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($journalEntries as $entry)
                                            @php $lines = $entry->details; @endphp
                                            @forelse ($lines as $line)
                                                <tr>
                                                    @if ($loop->first)
                                                        <td rowspan="{{ $lines->count() }}" class="num"><strong>{{ $entry->reference_number ?: '#'.$entry->id }}</strong></td>
                                                        <td rowspan="{{ $lines->count() }}" class="text-center num">{{ LanguageSettings::formatDate($entry->date) }}</td>
                                                        <td rowspan="{{ $lines->count() }}">{{ $entry->description ?: '—' }}</td>
                                                    @endif
                                                    <td>
                                                        {{ $line->account->name ?? 'حساب محذوف' }}
                                                        @if (filled($line->account->code ?? null))
                                                            <small class="po-hint num">({{ $line->account->code }})</small>
                                                        @endif
                                                    </td>
                                                    <td class="text-center num">{{ (float) $line->debit > 0 ? CurrencySettings::number($line->debit) : '—' }}</td>
                                                    <td class="text-center num">{{ (float) $line->credit > 0 ? CurrencySettings::number($line->credit) : '—' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="num"><strong>{{ $entry->reference_number ?: '#'.$entry->id }}</strong></td>
                                                    <td class="text-center num">{{ LanguageSettings::formatDate($entry->date) }}</td>
                                                    <td>{{ $entry->description ?: '—' }}</td>
                                                    <td colspan="3" class="text-muted">قيد بلا أطراف</td>
                                                </tr>
                                            @endforelse
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="po-card__foot">
                                <span class="po-hint"><i class="fas fa-coins me-1"></i>المبالغ بعملة المستند: {{ $currencyCode }}</span>
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
    </div>

    {{-- نافذة تسجيل دفعة — المعرّف مسبوقٌ باسم الشاشة: `#replyModal` وأمثاله
         محجوزة في الترويسة العامة فتتصادم معها --}}
    @if ($mayPay)
        <div class="modal fade" id="storeInvoicePayModal" tabindex="-1" role="dialog" aria-labelledby="storeInvoicePayModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="storeInvoicePayModalTitle">
                            <i class="fas fa-credit-card me-1"></i>تسجيل دفعة — {{ $purchaseInvoice->code }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>

                    @if ($treasuries->isEmpty())
                        {{-- بلا خزينة لا نموذج: نموذجٌ قائمته فارغة يُرفض حتماً عند الحفظ --}}
                        <div class="modal-body">
                            <div class="alert alert-info mb-0">
                                لا خزينة ولا حساب بنكي للمتجر متاح لك في الفرع الحالي للسحب منه، فلا يمكن تسجيل الدفعة الآن.
                                أضف خزينةً للمتجر أو بدّل الفرع من الشريط العلوي
                                @if (hasPermission('account.treasury.view'))
                                    — <a href="{{ route('store_treasury.index') }}">الخزائن والحسابات البنكية</a>.
                                @else
                                    — من شاشة «الخزائن والحسابات البنكية».
                                @endif
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إغلاق</button>
                        </div>
                    @else
                        <form method="POST" action="{{ route('store_purchase_invoices.pay', $purchaseInvoice->id) }}" id="storeInvoicePayForm">
                            @csrf
                            <div class="modal-body">
                                @if ($payHasErrors)
                                    <div class="alert alert-danger">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($payFields as $field)
                                                @foreach ($errors->get($field) as $message)
                                                    <li>{{ $message }}</li>
                                                @endforeach
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="alert alert-light border d-flex justify-content-between align-items-center py-2">
                                    <span>المتبقّي على الفاتورة</span>
                                    <strong class="num">{{ $money($remaining) }}</strong>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label" for="storePayAmount">المبلغ <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" name="amount" id="storePayAmount" dir="ltr"
                                                class="form-control @error('amount') is-invalid @enderror"
                                                step="0.01" min="0.01" max="{{ number_format($remaining, 2, '.', '') }}"
                                                value="{{ old('amount', number_format($remaining, 2, '.', '')) }}" required>
                                            <span class="input-group-text">{{ $currencyCode }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="storePayDate">تاريخ الدفع <span class="text-danger">*</span></label>
                                        <input type="date" name="payment_date" id="storePayDate"
                                            class="form-control @error('payment_date') is-invalid @enderror"
                                            value="{{ old('payment_date', date('Y-m-d')) }}" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="storePayTreasury">الخزينة / الحساب البنكي <span class="text-danger">*</span></label>
                                        <select name="treasury_id" id="storePayTreasury" class="form-control @error('treasury_id') is-invalid @enderror" required>
                                            <option value="">اختر الخزينة</option>
                                            @foreach ($treasuries as $treasury)
                                                <option value="{{ $treasury->id }}" @selected((string) old('treasury_id', $defaultTreasuryId) === (string) $treasury->id)>
                                                    {{ $treasury->name }} — الرصيد: {{ CurrencySettings::number($treasury->balance) }} {{ $treasury->currency_code ?: $currencyCode }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">تُقيَّد الدفعة: من ح/ المورد إلى ح/ الخزينة المختارة.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="storePayMethod">طريقة الدفع</label>
                                        <select name="payment_method" id="storePayMethod" class="form-control @error('payment_method') is-invalid @enderror">
                                            @foreach ($paymentMethods as $value => $label)
                                                <option value="{{ $value }}" @selected((string) old('payment_method', '1') === (string) $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="storePayNotes">ملاحظات</label>
                                        <textarea name="notes" id="storePayNotes" rows="2" maxlength="1000"
                                            class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-success" id="storePaySubmit">
                                    <i class="fas fa-check me-1"></i>حفظ الدفعة
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script>
        (function () {
            /* ── الألسنة ──────────────────────────────────────────────────────
               محرّكا Bootstrap 4 (من vendors.min.js) و5 محمَّلان معاً في لوحة
               المتجر، وكلٌّ ينتظر سمته (`data-toggle` / `data-bs-toggle`). فتبديل
               اللسان هنا يدويّ بتبديل الأصناف — يعمل أيّاً كان المحرّك الحيّ. */
            var tabs = document.getElementById('storeInvoiceTabs');

            function showTab(link) {
                var pane = document.querySelector(link.getAttribute('href'));
                if (!pane) { return; }

                tabs.querySelectorAll('.nav-link').forEach(function (el) { el.classList.remove('active'); });
                pane.parentNode.querySelectorAll(':scope > .tab-pane').forEach(function (el) { el.classList.remove('active', 'show'); });

                link.classList.add('active');
                pane.classList.add('active', 'show');
            }

            if (tabs) {
                tabs.addEventListener('click', function (e) {
                    var link = e.target.closest('a.nav-link[href^="#"]');
                    if (!link) { return; }
                    e.preventDefault();
                    showTab(link);
                });
            }

            /* ── تأكيد الحذف ── */
            var deleteForm = document.getElementById('storeInvoiceDeleteForm');

            if (deleteForm) {
                deleteForm.addEventListener('submit', function (e) {
                    if (deleteForm.dataset.confirmed === '1') { return; }
                    e.preventDefault();

                    var text = 'سيُحذف المستند رقم "{{ $purchaseInvoice->code }}" مع قيوده المحاسبية وإذنه المخزني. لا يمكن التراجع عن هذا الإجراء.';
                    var go = function () { deleteForm.dataset.confirmed = '1'; deleteForm.submit(); };

                    if (window.Swal) {
                        Swal.fire({
                            title: 'حذف المستند',
                            text: text,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'نعم، احذف',
                            cancelButtonText: 'إلغاء'
                        }).then(function (result) { if (result.isConfirmed) { go(); } });
                    } else if (window.confirm(text)) {
                        go();
                    }
                });
            }

            /* ── نافذة الدفع ──────────────────────────────────────────────────
               تُجرَّب إضافة jQuery (Bootstrap 4 — المحرّك الحيّ هنا) أوّلاً، ثم
               `bootstrap.Modal` (5)، ثم فتحٌ يدويّ — فلا تبقى النافذة مغلقةً
               لأن أحد المحرّكين غاب أو سبق الآخر. */
            var modal = document.getElementById('storeInvoicePayModal');
            if (!modal) { return; }

            var backdrop = null;

            function manual(open) {
                modal.style.display = open ? 'block' : 'none';
                modal.classList.toggle('show', open);
                modal.setAttribute('aria-hidden', open ? 'false' : 'true');
                document.body.classList.toggle('modal-open', open);

                if (open && !backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                } else if (!open && backdrop) {
                    backdrop.remove();
                    backdrop = null;
                }
            }

            function toggleModal(open) {
                try {
                    if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                        window.jQuery(modal).modal(open ? 'show' : 'hide');
                        return;
                    }
                    if (window.bootstrap && window.bootstrap.Modal) {
                        var instance = window.bootstrap.Modal.getOrCreateInstance(modal);
                        open ? instance.show() : instance.hide();
                        return;
                    }
                } catch (err) { /* يسقط إلى الفتح اليدوي */ }

                manual(open);
            }

            document.addEventListener('click', function (e) {
                if (e.target.closest('[data-store-pay-open]')) {
                    e.preventDefault();
                    toggleModal(true);
                }
            });

            // الإغلاق بالتفويض: `data-bs-dismiss` لا يلتقطه محرّك Bootstrap 4
            modal.addEventListener('click', function (e) {
                if (e.target.closest('[data-bs-dismiss="modal"]')) {
                    e.preventDefault();
                    toggleModal(false);
                    manual(false);
                }
            });

            // منع الإرسال المزدوج: ضغطتان متتاليتان لا تُسجَّلان دفعتين
            var payForm = document.getElementById('storeInvoicePayForm');
            if (payForm) {
                payForm.addEventListener('submit', function () {
                    var submit = document.getElementById('storePaySubmit');
                    if (submit) { submit.disabled = true; }
                });
            }

            // تُفتح تلقائياً بعد رفض دفعة (ليُرى الخطأ)، أو عند القدوم من
            // «تسجيل دفعة» في قائمة الفواتير (`#pay`)
            if (@json($payHasErrors) || window.location.hash === '#pay') {
                var openNow = function () { toggleModal(true); };
                document.readyState === 'complete' ? openNow() : window.addEventListener('load', openNow);
            }
        })();
    </script>
@endsection
