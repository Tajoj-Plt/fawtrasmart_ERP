{{--
    تعديل فاتورة مشتريات — الوجه الآخر لشاشة الإنشاء.

    كلتاهما تبنيان على نموذج دورة المشتريات المشترك (`partials/form`) نفسه
    الذي تبني به شاشة «أمر الشراء»، فالفرق بينهما مصدر البيانات لا الشكل:
    هنا تُملأ الحقول من الفاتورة المحفوظة، وبنودها تُعاد صفوفاً بأسعارها
    وخصومها وضرائبها ووحداتها.

    **ملاحظة:** `InvoicesPurchaseController::edit()` يمنع الوصول إلى هذه
    الشاشة سياسةً بعد اعتماد الفاتورة ويُعيد توجيهاً برسالة. القالب يبقى
    مبنيّاً وصحيحاً ليعمل متى رُفعت تلك السياسة.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.purchase_invoice') }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('purchases::partials.doc-theme-sl')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        use App\Services\Purchases\PurchaseTaxCalculator;

        $products = $items ?? collect();
        $taxes = $taxs ?? collect();

        // البنود المعادة بعد فشل تحقّق تتقدّم على المحفوظة، وإلا ضاع الإدخال
        $rows = old('items');

        if ($rows === null) {
            $rows = collect($invoice->items ?? [])
                ->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_unit_id' => $item->product_unit_id,
                    // الاسم والمعامل مع المعرّف: وحدةُ الصنف تُحفظ بمعرّفٍ فارغ
                    'unit_name' => $item->unit_name,
                    'conversion_factor' => $item->conversion_factor ?: 1,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    // خصمُ البند نسبةً من سطره — العمودُ مخفيّ؛ انظر `OrderLine::discountFields()`
                    ...\Modules\Purchases\Support\OrderLine::discountFields($item),
                    'tax_1' => (float) $item->tax_1,
                    'tax_2' => (float) $item->tax_2,
                    // البنود المحفوظة قبل إضافة عمود المعرّف تُطابَق بالنسبة،
                    // وإلا سقطت ضريبتها عند أول تعديل
                    'tax_1_id' => $item->tax_1_id ?: PurchaseTaxCalculator::matchByRate((float) $item->tax_1),
                    'tax_2_id' => $item->tax_2_id ?: PurchaseTaxCalculator::matchByRate((float) $item->tax_2),
                ])
                ->values()
                ->all();
        }

        $defaults = [
            'date' => old('date', $invoice->date ? \Illuminate\Support\Carbon::parse($invoice->date)->format('Y-m-d') : date('Y-m-d')),
            'supplier_id' => $invoice->supplier_id,
            'terms' => $invoice->terms,
            'notes' => $invoice->notes,
            'attachments' => $invoice->attachments,
            'branch_id' => $invoice->branch_id,
            'discount_amount' => $invoice->discount_amount,
            'discount_type' => $invoice->discount_type == 2 ? 'percentage' : 'amount',
            'shipping_cost' => $invoice->shipping_cost,
            'tax_id' => $invoice->tax_type,
            'advance_payment' => $invoice->advance_payment,
            'advance_payment_type' => $invoice->advance_payment_type == 2 ? 'percentage' : 'amount',
            'treasury_id' => $treasury_id ?? ($MainTreasury->id ?? null),
            'payment_method' => $invoice->payment_method,
            'reference_number' => $invoice->reference_number,
            // حالة السداد المحفوظة — تُقرأ فقط حين يُفعَّل «حالة يدوية لفاتورة
            // الشراء»، فحقلها لا يُعرَض أصلاً بغيره
            'payment_status' => $invoice->payment_status,
            'is_received' => (bool) $invoice->is_received,
            'received_date' => $invoice->received_date ? \Illuminate\Support\Carbon::parse($invoice->received_date)->format('Y-m-d') : null,
        ];
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'file-text',
            'sub' => 'تعديلُ الأرقام يُعيد كتابة قيد الفاتورة كاملاً',
            'title' => __('purchase_cycle.documents.purchase_invoice'),
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_invoice'), 'url' => route('invoicePurchases.index')],
                ['label' => __('purchase_orders.breadcrumb.edit')],
            ],
        ])

        @include('purchases::partials.doc-context', ['code' => $invoice->code])

        {{-- لوحة إرشاد الشاشة — مفتاحٌ يخصّ التعديل: قاعدة «لا تعديل بعد
             الاعتماد» تهمّ من فتح هذه الشاشة لا من فتح شاشة الإنشاء --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-invoices.edit',
            'title' => __('purchase_cycle.guide.form.title'),
            'lead' => __('purchase_cycle.guidance.invoice_edit'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.form.noedit.title') => [
                    __('purchase_cycle.guide.form.noedit.a'),
                    __('purchase_cycle.guide.form.noedit.b'),
                ],
                __('purchase_cycle.guide.form.save.title') => [
                    __('purchase_cycle.guide.form.save.a'),
                    __('purchase_cycle.guide.form.save.b'),
                    __('purchase_cycle.guide.form.save.c'),
                    __('purchase_cycle.guide.form.save.d'),
                ],
                __('purchase_cycle.guide.statuses.title') => [
                    __('purchase_cycle.guide.statuses.a'),
                    __('purchase_cycle.guide.statuses.b'),
                    __('purchase_cycle.guide.statuses.c'),
                ],
                __('purchase_cycle.guide.form.blocked.title') => [
                    __('purchase_cycle.guide.form.blocked.a'),
                    __('purchase_cycle.guide.form.blocked.b'),
                    __('purchase_cycle.guide.form.blocked.c'),
                ],
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('invoicePurchases.update', $invoice->id),
            'isEdit' => true,
            'backUrl' => route('invoicePurchases.show', $invoice->id),
            'code' => $invoice->code,
            'documentLabel' => __('purchase_cycle.documents.purchase_invoice'),
            'products' => $products,
            'suppliers' => $suppliers ?? collect(),
            'taxes' => $taxes,
            'treasuries' => $treasuries ?? collect(),
            'warehouses' => $warehouses ?? collect(),
            'rows' => $rows,
            'defaults' => $defaults,
            'config' => [
                'title' => false,
                'supplier_mode' => 'single',
                'due_date' => false,
                'prices' => true,
                'tax_field' => 'tax_1',
                'attachments' => true,
                'settlement' => true,
                'date_field' => 'date',
                'terms' => true,
                'second_tax' => false,
                'payment' => true,
                'payment_default_setting' => 'default_paid_invoices',
                'invoice_status_setting' => 'manual_invoice_status',
                'receiving' => true,
                'receiving_default_setting' => 'default_received_invoices',
                'warehouse' => true,
                'refund' => false,
                'branch' => true,
            ],
        ])
    </div>
@endsection
