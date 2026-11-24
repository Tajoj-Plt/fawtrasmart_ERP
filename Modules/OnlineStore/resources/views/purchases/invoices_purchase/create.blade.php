{{--
    إنشاء فاتورة مشتريات للمتجر (أو مرتجع شراء من فاتورة قائمة).

    الشاشة مبنيّة على نموذج دورة المشتريات المشترك نفسه
    (`purchases::partials.form`) الذي تبني به وحدة المشتريات شاشة «فاتورة
    الشراء» — فالتصميم والترتيب (بيانات المستند ← البنود ← التفاصيل الإضافية
    ← الملاحظات والحفظ) وجدول البنود وحساب الإجماليات واحدة في الوحدتين، ولا
    نسخة ثانية تُصان هنا.

    ما يخصّ المتجر يأتي من المتحكّم:
      • الموردون        ← الفرع المختار في الشريط ونطاق المستخدم
      • المستودعات      ← مستودعات المتجر النشطة داخل النطاق
      • الخزائن         ← خزائن وبنوك المتجر المسموح السحب منها — وللجزء
                          المدفوع فقط: الفاتورة الآجلة لا تحتاج خزينة
      • الرقم           ← «تسلسل أرقام المستندات» (يصدره الخادم عند الحفظ)
--}}
@extends('onlinestore::master')

@section('title', $originalInvoice ? 'إنشاء مرتجع شراء' : 'إضافة فاتورة مشتريات')

@section('content')
    @include('purchases::partials.style')
    @include('purchases::partials.doc-theme-sl')

    @php
        $isReturn = (bool) $originalInvoice;
        $products = $items ?? collect();
        $taxes = $taxs ?? collect();

        // بنود مُعادة بعد فشل تحقّق تتقدّم، ثم بنود الفاتورة الأصلية (مرتجع)
        $rows = old('items');

        if ($rows === null) {
            $rows = $isReturn
                ? collect($originalInvoice->items)->map(fn ($item) => [
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
                    'tax_1_id' => $item->tax_1_id ?: \App\Services\Purchases\PurchaseTaxCalculator::matchByRate((float) $item->tax_1),
                    'tax_2_id' => $item->tax_2_id ?: \App\Services\Purchases\PurchaseTaxCalculator::matchByRate((float) $item->tax_2),
                ])->values()->all()
                : [];
        }

        $defaults = [
            'date' => old('date', date('Y-m-d')),
            'supplier_id' => $isReturn ? $originalInvoice->supplier_id : ($selectedSupplierId ?? null),
            'treasury_id' => $treasury_id ?? null,
            'store_house_id' => $defaultStoreHouseId ?? null,
        ];

        $documentLabel = $isReturn ? 'مرتجع شراء' : 'فاتورة مشتريات';
    @endphp

    <div class="content-body">
        @include('onlinestore::components.page-head', [
            'title' => $isReturn ? 'إنشاء مرتجع شراء' : 'إضافة فاتورة مشتريات',
            'icon' => $isReturn ? 'rotate-ccw' : 'file-plus',
            'sub' => $isReturn
                ? 'مرتجع لفاتورة الشراء رقم '.$originalInvoice->code.' — '.($originalInvoice->supplier->name ?? 'غير محدد')
                : 'تُرحَّل إلى ح/ المشتريات وذمّة المورد عند الحفظ — والخزينة للجزء المدفوع فقط',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'فواتير الشراء' => route('store_purchase_invoices.index'),
                ($isReturn ? 'إنشاء مرتجع' : 'إضافة') => null,
            ],
        ])

        <div class="po-page po-doc--sl">
            @include('purchases::partials.doc-context', ['code' => $code])

            @include('purchases::partials.period-block')

            @if ($isReturn)
                <div class="alert alert-warning d-flex align-items-center gap-2">
                    <i class="feather icon-alert-triangle"></i>
                    <div>
                        هذا مرتجع لفاتورة الشراء رقم <strong>{{ $originalInvoice->code }}</strong> للمورد
                        <strong>{{ $originalInvoice->supplier->name ?? '—' }}</strong> — البنود معبَّأة منها تلقائياً.
                        عدِّل الكميات المرتجَعة فعلياً قبل الحفظ؛ المورد والمستودع يتبعان الفاتورة الأصلية.
                    </div>
                </div>
            @else
                {{-- بطاقة بيانات المورد — نفسها في نموذج الفاتورة وصفحتها وطباعتها --}}
                @include('onlinestore::suppliers.partials.supplier-card', [
                    'supplier' => $suppliers->firstWhere('id', old('supplier_id', $defaults['supplier_id'])),
                    'live' => true,
                    'suppliers' => $suppliers,
                ])

                @if ($treasuries->isEmpty())
                    <div class="alert alert-info d-flex align-items-start gap-2">
                        <i class="feather icon-info mt-1"></i>
                        <div>
                            لا خزينة ولا حساب بنكي للمتجر متاح لك في الفرع الحالي — يمكنك حفظ الفاتورة <strong>آجلةً</strong>
                            (بلا دفعة) وتُقيَّد كاملةً على حساب المورد. للدفع عند الإنشاء أضف خزينة من
                            @if (hasPermission('account.treasury.view'))
                                <a href="{{ route('store_treasury.index') }}">الخزائن والحسابات البنكية</a>.
                            @else
                                «الخزائن والحسابات البنكية».
                            @endif
                        </div>
                    </div>
                @endif
            @endif

            @if ($storeHouses->isEmpty())
                <div class="alert alert-danger d-flex align-items-start gap-2">
                    <i class="feather icon-alert-octagon mt-1"></i>
                    <div>لا مستودع نشط للمتجر في الفرع الحالي — فاتورة الشراء تحتاج مستودعاً تُستلم فيه البضاعة. أضف مستودعاً للمتجر أو بدّل الفرع من الشريط العلوي.</div>
                </div>
            @endif

            @include('purchases::partials.form', [
                'action' => route('store_purchase_invoices.store'),
                'isEdit' => false,
                'backUrl' => route('store_purchase_invoices.index'),
                'code' => $code,
                'documentLabel' => $documentLabel,
                'products' => $products,
                'suppliers' => $suppliers ?? collect(),
                'taxes' => $taxes,
                'treasuries' => $treasuries ?? collect(),
                'warehouses' => $storeHouses ?? collect(),
                'rows' => $rows,
                'defaults' => $defaults,
                'hidden_fields' => $isReturn
                    ? ['type' => 'return', 'return_from' => $originalInvoice->id]
                    : [],
                'config' => [
                    'title' => false,
                    'supplier_mode' => 'single',
                    'due_date' => false,
                    'prices' => true,
                    'tax_field' => 'tax_1',
                    'attachments' => ! $isReturn,
                    'settlement' => ! $isReturn,
                    'date_field' => 'date',
                    'terms' => ! $isReturn,
                    'second_tax' => false,
                    'payment' => ! $isReturn,
                    'payment_default_setting' => 'default_paid_invoices',
                    'receiving' => ! $isReturn,
                    'receiving_default_setting' => 'default_received_invoices',
                    'warehouse' => ! $isReturn,
                    'refund' => false,
                    // فرع الفاتورة يتبع فرع مستودع المتجر الذي تُستلم فيه
                    'branch' => false,
                ],
            ])
        </div>
    </div>
@endsection
