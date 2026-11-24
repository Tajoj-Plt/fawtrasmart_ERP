{{--
    تعديل فاتورة مشتريات المتجر — على نموذج دورة المشتريات المشترك نفسه
    (`purchases::partials.form`) كما في شاشة الإنشاء، فلا يختلف تصميم
    التعديل عن الإنشاء ولا عن شاشة وحدة المشتريات.

    ما لا يُعدَّل هنا: رقم الفاتورة (صادرٌ من التسلسل)، وعملتها وفرعها
    (مختومة عند الإنشاء)، والمدفوع عليها (سندات الدفع تُدار من صفحة الفاتورة).
--}}
@extends('onlinestore::master')

@section('title', 'تعديل فاتورة مشتريات')

@section('content')
    @include('purchases::partials.style')
    @include('purchases::partials.doc-theme-sl')

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
                    'unit_name' => $item->unit_name,
                    'conversion_factor' => $item->conversion_factor ?: 1,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    ...\Modules\Purchases\Support\OrderLine::discountFields($item),
                    'tax_1' => (float) $item->tax_1,
                    'tax_2' => (float) $item->tax_2,
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
            // الخصم العام محفوظٌ مبلغاً محسوباً — فيُعرض مبلغاً
            'discount_amount' => $invoice->discount_amount,
            'discount_type' => 'amount',
            'shipping_cost' => $invoice->shipping_cost,
            'store_house_id' => $invoice->warehousePermit->store_houses_id ?? null,
            'is_received' => $invoice->receiving_status === 'received',
            'received_date' => $invoice->received_date ? \Illuminate\Support\Carbon::parse($invoice->received_date)->format('Y-m-d') : null,
        ];
    @endphp

    <div class="content-body">
        @include('onlinestore::components.page-head', [
            'title' => 'تعديل فاتورة مشتريات',
            'icon' => 'edit',
            'sub' => 'فاتورة رقم '.$invoice->code.' — '.($invoice->supplier->name ?? 'غير محدد'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'فواتير الشراء' => route('store_purchase_invoices.index'),
                $invoice->code => route('store_purchase_invoices.show', $invoice->id),
                'تعديل' => null,
            ],
        ])

        <div class="po-page po-doc--sl">
            @include('purchases::partials.doc-context', ['code' => $invoice->code])

            @include('purchases::partials.period-block')

            @include('onlinestore::suppliers.partials.supplier-card', [
                'supplier' => $suppliers->firstWhere('id', old('supplier_id', $invoice->supplier_id)),
                'live' => true,
                'suppliers' => $suppliers,
            ])

            @if ((float) $invoice->advance_payment > 0)
                <div class="alert alert-info d-flex align-items-start gap-2">
                    <i class="feather icon-info mt-1"></i>
                    <div>
                        دُفع على هذه الفاتورة <strong>{{ \App\Support\CurrencySettings::number($invoice->advance_payment) }}</strong>
                        — تعديل البنود لا يغيّر المدفوع، ولا يُقبل إجماليٌّ أقلّ منه. الدفعات تُدار من صفحة الفاتورة.
                    </div>
                </div>
            @endif

            @include('purchases::partials.form', [
                'action' => route('store_purchase_invoices.update', $invoice->id),
                'isEdit' => true,
                'backUrl' => route('store_purchase_invoices.show', $invoice->id),
                'code' => $invoice->code,
                'documentLabel' => 'فاتورة مشتريات',
                'products' => $products,
                'suppliers' => $suppliers ?? collect(),
                'taxes' => $taxes,
                'treasuries' => collect(),
                'warehouses' => $storeHouses ?? collect(),
                'rows' => $rows,
                'defaults' => $defaults,
                'config' => [
                    'title' => false,
                    'supplier_mode' => 'single',
                    'due_date' => false,
                    'prices' => true,
                    'tax_field' => 'tax_1',
                    'attachments' => false,
                    'settlement' => true,
                    'date_field' => 'date',
                    'terms' => true,
                    'second_tax' => false,
                    // الدفع والاستلام لا يُعدَّلان من هنا: الدفعات من صفحة
                    // الفاتورة، والاستلام باعتماد الإذن المخزني
                    'payment' => false,
                    'receiving' => true,
                    'warehouse' => true,
                    'refund' => false,
                    'branch' => false,
                ],
            ])
        </div>
    </div>
@endsection
