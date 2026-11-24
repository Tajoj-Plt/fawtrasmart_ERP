@extends('onlinestore::master')

@section('title', 'تفاصيل المنتج')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_products.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
            @if (hasPermission('online_store.products.show'))
                <a href="{{ route('store_products.print_barcode', $product->id) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-printer"></i> طباعة الباركود
                </a>
            @endif
            @if (hasPermission('online_store.questions.view'))
                <a href="{{ route('product_questions.product', $product) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-help-circle"></i> أسئلة المنتج
                    @php $productQuestionsCount = \App\Models\Store\ProductQuestion::where('product_id', $product->id)->count(); @endphp
                    @if ($productQuestionsCount > 0)
                        <span class="badge badge-light-primary">{{ $productQuestionsCount }}</span>
                    @endif
                </a>
            @endif
            @if (hasPermission('online_store.reviews.view'))
                <a href="{{ route('product_reviews.product', $product) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-star"></i> تقييمات المنتج
                </a>
            @endif
            @if (hasPermission('online_store.products.delete'))
                <button type="button" class="btn btn-outline-danger" data-os-modal="#modal_DELETE{{ $product->id }}">
                    <i class="feather icon-trash-2"></i> حذف
                </button>
            @endif
            @if (hasPermission('online_store.products.edit'))
                <a href="{{ route('store_products.edit', $product) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => $product->name,
            'icon' => 'package',
            'sub' => ($product->category->name ?? 'غير مصنّف') . ($product->barcode ? ' — ' . $product->barcode : ''),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'المنتجات' => route('store_products.index'),
                $product->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="row">
            {{-- القسم الأيسر - معلومات المنتج الرئيسية --}}
            <div class="col-md-8">
                {{-- رأس المنتج --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="mb-1">{{ $product->name }}</h3>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="stk-state {{ $product->status->value == 'active' ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $product->status->value == 'active' ? 'نشط' : 'غير نشط' }}
                                    </span>
                                    @if($product->available_online)
                                        <span class="badge badge-light-info"><i class="feather icon-globe"></i> متاح أونلاين</span>
                                    @endif
                                    @if($product->featured_product)
                                        <span class="badge badge-light-warning"><i class="feather icon-star"></i> مميز</span>
                                    @endif
                                    @if($product->barcode)
                                        <span class="badge badge-light-secondary stk-num">{{ $product->barcode }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-left">
                                <h4 class="text-success mb-0 stk-num">{{ number_format($product->getFinalPrice(), 2) }} {{ \App\Support\CurrencySettings::symbol() }}</h4>
                                @if($product->hasDiscount())
                                    <del class="text-muted stk-num">{{ number_format($product->sale_price, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</del>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- إحصائيات المنتج --}}
                <div class="stk-metrics mb-3">
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                        <span class="stk-metric__label">الكمية الكلية</span>
                        <span class="stk-metric__value">{{ number_format($stats['total_quantity'] ?? 0) }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                        <span class="stk-metric__label">إجمالي المبيعات</span>
                        <span class="stk-metric__value">{{ number_format($stats['total_sold'] ?? 0) }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                        <span class="stk-metric__label">آخر 28 يوم</span>
                        <span class="stk-metric__value">{{ number_format($stats['sold_last_28_days'] ?? 0) }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                        <span class="stk-metric__label">آخر 7 أيام</span>
                        <span class="stk-metric__value">{{ number_format($stats['sold_last_7_days'] ?? 0) }}</span>
                    </div>
                </div>

                {{-- كميات المخازن --}}
                @if(isset($storeQuantities) && $storeQuantities->count() > 0)
                <div class="card mb-3">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-home"></i> الكميات حسب المخازن</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($storeQuantities as $storeQuantity)
                                @if($storeQuantity->storeHouse)
                                <div class="col-md-4 col-6 mb-3">
                                    <div class="store-quantity-card">
                                        <div class="store-icon">
                                            <i class="feather icon-box"></i>
                                        </div>
                                        <div class="store-info">
                                            <span class="store-name">{{ $storeQuantity->storeHouse->name }}</span>
                                            <strong class="store-qty stk-num {{ $storeQuantity->total_quantity > 10 ? 'text-success' : ($storeQuantity->total_quantity > 0 ? 'text-warning' : 'text-danger') }}">
                                                {{ number_format($storeQuantity->total_quantity) }}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- التابات --}}
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs stk-tabs" id="productTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="info-tab" data-bs-toggle="tab" data-toggle="tab" href="#info" role="tab">
                                    <i class="feather icon-info"></i> معلومات
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="stock-movements-tab" data-bs-toggle="tab" data-toggle="tab" href="#stock-movements" role="tab">
                                    <i class="feather icon-activity"></i> حركة المخزون
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="timeline-tab" data-bs-toggle="tab" data-toggle="tab" href="#timeline" role="tab" onclick="loadTimeline(1)">
                                    <i class="feather icon-clock"></i> الجدول الزمني
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="activity-logs-tab" data-bs-toggle="tab" data-toggle="tab" href="#activity-logs" role="tab" onclick="loadActivityLogs(1)">
                                    <i class="feather icon-list"></i> سجل النشاطات
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content pt-3" id="productTabsContent">
                            {{-- تاب المعلومات --}}
                            <div class="tab-pane fade show active" id="info" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <dl class="stk-def">
                                            <div class="stk-def__row">
                                                <dt>اسم المنتج</dt>
                                                <dd>{{ $product->name }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>التصنيف</dt>
                                                <dd>{{ $product->category->name ?? '-' }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>العلامة التجارية</dt>
                                                <dd>{{ $product->brand->name ?? '-' }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>الباركود</dt>
                                                <dd class="stk-num">{{ $product->barcode ?? '-' }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>الوزن</dt>
                                                <dd>{{ $product->weight ? $product->weight . ' كجم' : '-' }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                    <div class="col-md-6">
                                        <dl class="stk-def">
                                            <div class="stk-def__row">
                                                <dt>سعر الشراء</dt>
                                                <dd class="stk-num">{{ number_format($product->purchase_price, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>سعر البيع</dt>
                                                <dd class="stk-num">{{ number_format($product->sale_price, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>متوسط التكلفة</dt>
                                                <dd class="stk-num">{{ number_format($stats['average_cost'] ?? 0, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>أقل سعر بيع</dt>
                                                <dd class="stk-num">{{ $product->min_sale_price ? number_format($product->min_sale_price, 2) . ' ' . \App\Support\CurrencySettings::symbol() : '-' }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>الخصم</dt>
                                                <dd>
                                                    @if($product->hasDiscount())
                                                        {{ $product->discount }}{{ $product->getDiscountTypeText() }}
                                                    @else
                                                        -
                                                    @endif
                                                </dd>
                                            </div>
                                            @php
                                                // `tax1/tax2` معرّفا ضريبةٍ من شاشة «الضرائب» لا نسبتان
                                                $productTaxes = \App\Models\TaxSitting::whereIn('id', array_filter([$product->tax1, $product->tax2]))->get()->keyBy('id');
                                                $taxLabel = fn ($id) => ! $id ? 'بلا ضريبة'
                                                    : (($t = $productTaxes->get((int) $id))
                                                        ? $t->name.' ('.rtrim(rtrim(number_format((float) $t->tax, 2), '0'), '.').'%)'
                                                        : 'غير معرَّفة — اختر الضريبة من تعديل المنتج');
                                            @endphp
                                            <div class="stk-def__row">
                                                <dt>الضريبة الأولى</dt>
                                                <dd>{{ $taxLabel($product->tax1) }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>الضريبة الثانية</dt>
                                                <dd>{{ $taxLabel($product->tax2) }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                                @if($product->description)
                                <div class="mt-3">
                                    <h6>الوصف:</h6>
                                    <p class="text-muted">{{ $product->description }}</p>
                                </div>
                                @endif
                            </div>

                            {{-- تاب حركة المخزون --}}
                            <div class="tab-pane fade" id="stock-movements" role="tabpanel">
                                <div id="stock-movements-content">
                                    @include('onlinestore::products.partials.stock_movements', [
                                        'stock_movements' => $stock_movements,
                                        'product' => $product,
                                        'permission_sources' => $permission_sources,
                                        'source_id' => $source_id ?? null,
                                        'warehouse_id' => $warehouse_id ?? null,
                                        'user_id' => $user_id ?? null
                                    ])
                                </div>
                            </div>

                            {{-- تاب الجدول الزمني --}}
                            <div class="tab-pane fade" id="timeline" role="tabpanel">
                                <div id="timeline-content">
                                    <div class="text-center p-5">
                                        <i class="feather icon-loader spin"></i> جاري التحميل...
                                    </div>
                                </div>
                            </div>

                            {{-- تاب سجل النشاطات --}}
                            <div class="tab-pane fade" id="activity-logs" role="tabpanel">
                                <div id="activity-logs-content">
                                    <div class="text-center p-5">
                                        <i class="feather icon-loader spin"></i> جاري التحميل...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- المواصفات -->
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-box"></i> المواصفات</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>الوزن</dt>
                                        <dd>{{ $product->weight ? $product->weight . ' كجم' : '-' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الأبعاد</dt>
                                        <dd>
                                            @if($product->dimensions && is_array($product->dimensions) && isset($product->dimensions['length'], $product->dimensions['width'], $product->dimensions['height']))
                                                {{ $product->dimensions['length'] }}x{{ $product->dimensions['width'] }}x{{ $product->dimensions['height'] }} سم
                                            @else
                                                -
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>قابل للإرجاع</dt>
                                        <dd>{{ $product->is_returnable ? 'نعم' : 'لا' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>مدة الإرجاع</dt>
                                        <dd>{{ $product->return_days ?? 0 }} يوم</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>تتبع المخزون</dt>
                                        <dd>{{ $product->track_inventory ? 'نعم' : 'لا' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>تنبيه انخفاض المخزون</dt>
                                        <dd>
                                            @if($product->low_stock_alert)
                                                نعم — عند {{ $product->low_stock_thershold ?? 0 }}
                                            @else
                                                لا
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- الخيارات: تعريفات الخيار وقيمه المحتملة، بصرف النظر عن وجود متغيرات مولَّدة منها --}}
                @if($product->relationLoaded('options') && $product->options->isNotEmpty())
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-sliders"></i> الخيارات ({{ $product->options->count() }})</h6>
                        </div>
                        <div class="card-body">
                            @foreach($product->options as $option)
                                <div class="mb-3 {{ !$loop->last ? 'pb-3 border-bottom' : '' }}">
                                    <div class="font-weight-bold mb-2">{{ $option->displayLabel() }}</div>
                                    <div class="stk-tags">
                                        @forelse($option->values as $value)
                                            <span class="stk-tag">
                                                @if($value->color_code)
                                                    <span class="ov-swatch" style="background:{{ $value->color_code }}"></span>
                                                @endif
                                                {{ $value->displayLabel() }}
                                            </span>
                                        @empty
                                            <span class="text-muted">لا قيم مسجَّلة</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($product->relationLoaded('variants') && $product->variants->isNotEmpty())
                    <!-- المتغيرات -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-grid"></i> المتغيرات ({{ $product->variants->count() }})</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table stk-table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th style="width:56px"></th>
                                    <th>الخيارات</th>
                                    <th>SKU</th>
                                    <th>السعر</th>
                                    <th title="حقل وصفي فقط — المتغيّرات لا تحمل مخزوناً: المتاح في المتجر هو رصيد المنتج الأساسي في مستودع المتجر">المخزون <i class="feather icon-info text-muted"></i></th>
                                    <th>الحالة</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($product->variants as $variant)
                                    <tr>
                                        <td>
                                            @if($variant->image_url)
                                                <img src="{{ asset($variant->image_url) }}" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">
                                            @else
                                                <i class="feather icon-image text-muted"></i>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="stk-tags">
                                                @forelse($variant->optionValues as $ov)
                                                    <span class="stk-tag">
                                                        @if($ov->color_code)
                                                            <span class="ov-swatch" style="background:{{ $ov->color_code }}"></span>
                                                        @endif
                                                        {{ $ov->option->displayLabel() ?? '' }}: {{ $ov->displayLabel() }}
                                                    </span>
                                                @empty
                                                    <span class="text-muted">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td dir="ltr">{{ $variant->sku }}</td>
                                        <td>{{ number_format($variant->finalPrice(), 2) }} {{ \App\Support\CurrencySettings::symbol() }}</td>
                                        <td>{{ $variant->stock_quantity }}</td>
                                        <td>
                                            <span class="stk-state {{ $variant->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                                {{ $variant->is_active ? 'مفعّل' : 'معطّل' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- القسم الأيمن - الصور والإجراءات --}}
            <div class="col-md-4">
                {{-- بطاقة الإجراءات --}}
                <div class="card mb-3">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-settings"></i> الإجراءات</h6>
                    </div>
                    <div class="card-body">
                        <div class="actions-card">
                            <div class="actions-grid">
                                {{-- تعديل --}}
                                @if (hasPermission('online_store.products.edit'))
                                    <a href="{{ route('store_products.edit', $product) }}" class="btn btn-outline-primary">
                                        <i class="fa fa-edit"></i>
                                        <span>تعديل البيانات</span>
                                    </a>
                                @endif

                                {{-- حذف --}}
                                @if (hasPermission('online_store.products.delete'))
                                    <button class="btn btn-outline-danger" data-os-modal="#modal_DELETE{{ $product->id }}">
                                        <i class="fa fa-trash"></i>
                                        <span>حذف</span>
                                    </button>
                                @endif

                                @if($product->type == 'products' || $product->type == 'compiled')
                                    {{-- نقل بين المخازن --}}
                                    @if(Route::has('store_permits_management.manual_conversion'))
                                    <a href="{{ route('store_permits_management.manual_conversion') }}" class="btn btn-outline-info">
                                        <i class="fa fa-reply-all"></i>
                                        <span>نقل</span>
                                    </a>
                                    @endif
                                @endif

                                {{-- طباعة الباركود --}}
                                @if (hasPermission('online_store.products.show'))
                                    <a href="{{ route('store_products.print_barcode', $product->id) }}" class="btn btn-outline-success">
                                        <i class="fa fa-barcode"></i>
                                        <span>طباعة الباركود</span>
                                    </a>
                                @endif

                                {{-- طباعة --}}
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-toggle="dropdown">
                                        <i class="fa fa-print"></i>
                                        <span>طباعة</span>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="printProductDetails()">
                                            <i class="fa fa-file-pdf mr-2 text-danger"></i> طباعة PDF
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" onclick="printProductDirect()">
                                            <i class="fa fa-print mr-2 text-primary"></i> طباعة مباشرة
                                        </a></li>
                                    </ul>
                                </div>

                                @if($product->type == 'products' || $product->type == 'compiled')
                                    @if (hasPermission('online_store.products.manual_stock_adjust'))
                                        {{-- إضافة عملية على المخزون --}}
                                        <a href="{{ route('store_products.manual_stock_adjust', $product->id) }}" class="btn btn-outline-warning">
                                            <i class="fa fa-plus-circle"></i>
                                            <span>إضافة عملية</span>
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- إعدادات العرض --}}
                <div class="card mb-3">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-toggle-right"></i> إعدادات العرض</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 text-center mb-3">
                                <i class="feather icon-globe fa-2x {{ $product->available_online ? 'text-success' : 'text-muted' }}"></i>
                                <p class="mb-0 mt-1 small">متاح أونلاين</p>
                                <strong class="{{ $product->available_online ? 'text-success' : 'text-muted' }}">{{ $product->available_online ? 'نعم' : 'لا' }}</strong>
                            </div>
                            <div class="col-6 text-center mb-3">
                                <i class="feather icon-monitor fa-2x {{ $product->available_pos ? 'text-success' : 'text-muted' }}"></i>
                                <p class="mb-0 mt-1 small">متاح POS</p>
                                <strong class="{{ $product->available_pos ? 'text-success' : 'text-muted' }}">{{ $product->available_pos ? 'نعم' : 'لا' }}</strong>
                            </div>
                            <div class="col-6 text-center">
                                <i class="feather icon-star fa-2x {{ $product->featured_product ? 'text-warning' : 'text-muted' }}"></i>
                                <p class="mb-0 mt-1 small">منتج مميز</p>
                                <strong class="{{ $product->featured_product ? 'text-warning' : 'text-muted' }}">{{ $product->featured_product ? 'نعم' : 'لا' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- الصور --}}
                <div class="card mb-3">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-image"></i> صور المنتج</h6>
                    </div>
                    <div class="card-body">
                        @php
                            $images = is_array($product->images) ? $product->images : json_decode((string) $product->images, true);
                        @endphp
                        @if($images && count($images) > 0)
                            <div id="productCarousel" class="carousel slide" data-ride="carousel">
                                <div class="carousel-inner rounded">
                                    @foreach($images as $index => $image)
                                    <div class="carousel-item {{ $index == 0 ? 'active' : '' }}">
                                        <img src="{{ asset($image) }}"
                                             class="d-block w-100"
                                             style="height: 250px; object-fit: contain; background: #f8f9fa;"
                                             onerror="this.onerror=null; this.src='{{ asset('app-assets/images/placeholder.png') }}';">
                                    </div>
                                    @endforeach
                                </div>
                                @if(count($images) > 1)
                                <a class="carousel-control-prev" href="#productCarousel" role="button" data-slide="prev">
                                    <span class="carousel-control-prev-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                                </a>
                                <a class="carousel-control-next" href="#productCarousel" role="button" data-slide="next">
                                    <span class="carousel-control-next-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                                </a>
                                @endif
                            </div>
                            <div class="row mt-2 g-2">
                                @foreach($images as $index => $image)
                                <div class="col-3 mb-2">
                                    <img src="{{ asset($image) }}"
                                         class="img-thumbnail cursor-pointer"
                                         style="height: 50px; width: 100%; object-fit: cover;"
                                         onclick="$('#productCarousel').carousel({{ $index }})"
                                         onerror="this.onerror=null; this.src='{{ asset('app-assets/images/placeholder.png') }}';">
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-image"></i></span>
                                <p class="stk-empty__hint mb-0">لا توجد صور</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- معلومات إضافية --}}
                <div class="card mb-3">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-star"></i> التقييمات</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-around">
                            <div>
                                <h3 class="text-warning mb-0 stk-num">{{ number_format($stats['average_rating'] ?? 0, 1) }}</h3>
                                <small class="text-muted">متوسط التقييم</small>
                            </div>
                            <div>
                                <h3 class="text-info mb-0 stk-num">{{ $stats['reviews_count'] ?? 0 }}</h3>
                                <small class="text-muted">عدد التقييمات</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ملاحظات --}}
                @if($product->Internal_notes)
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> ملاحظات داخلية</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $product->Internal_notes }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

<style>
    .cursor-pointer { cursor: pointer; }

    /* Actions Card */
    .actions-card .actions-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .actions-card .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 14px;
        white-space: nowrap;
    }
    .actions-card .dropdown {
        display: inline-block;
    }
    .actions-card .dropdown-toggle::after {
        display: none;
    }
    .actions-card .btn i {
        font-size: 16px;
    }

    /* Store Quantity Card */
    .store-quantity-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    .store-quantity-card .store-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 18px;
    }
    .store-quantity-card .store-info {
        display: flex;
        flex-direction: column;
    }
    .store-quantity-card .store-name {
        font-size: 13px;
        color: #666;
    }
    .store-quantity-card .store-qty {
        font-size: 18px;
        font-weight: 700;
    }

    /* Carousel */
    .carousel-inner { border-radius: 10px; overflow: hidden; }
    .carousel-control-prev-icon, .carousel-control-next-icon { background-size: 60%; }

    /* Timeline */
    .activity-timeline { padding: 0; }
    .activity-timeline li {
        position: relative;
        padding-left: 2.5rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #eee;
    }
    .timeline-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 12px;
    }
    .timeline-info p { margin-bottom: 0.5rem; }
    .timeline-info span { font-size: 12px; color: #888; }

    /* Spinner */
    .spin { animation: spin 1s linear infinite; }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* Gap utility */
    .gap-2 { gap: 0.5rem; }
</style>

<script>
    // Load Timeline
    function loadTimeline(page) {
        const productId = '{{ $product->id }}';
        const container = document.getElementById('timeline-content');

        container.innerHTML = '<div class="text-center p-5"><i class="feather icon-loader spin"></i> جاري التحميل...</div>';

        fetch(`{{ url('/online_store/products') }}/${productId}/timeline?page=${page}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = data.html;
            } else {
                container.innerHTML = '<div class="alert alert-danger">حدث خطأ في تحميل البيانات</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="alert alert-danger">حدث خطأ في تحميل البيانات</div>';
        });
    }

    // Load Activity Logs
    function loadActivityLogs(page) {
        const productId = '{{ $product->id }}';
        const container = document.getElementById('activity-logs-content');

        container.innerHTML = '<div class="text-center p-5"><i class="feather icon-loader spin"></i> جاري التحميل...</div>';

        fetch(`{{ url('/online_store/products') }}/${productId}/activity-logs?page=${page}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = data.html;
            } else {
                container.innerHTML = '<div class="alert alert-danger">حدث خطأ في تحميل البيانات</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="alert alert-danger">حدث خطأ في تحميل البيانات</div>';
        });
    }

    // Print Product Details
    function printProductDetails() {
        const productId = '{{ $product->id }}';
        const name = '{{ $product->name }}';
        const sku = '{{ $product->serial_number ?? "" }}';
        const barcode = '{{ $product->barcode ?? "" }}';
        const price = '{{ number_format($product->sale_price, 2) }}';
        const cost = '{{ number_format($product->cost_price ?? 0, 2) }}';
        const quantity = '{{ $stats["total_quantity"] ?? 0 }}';
        const category = '{{ $product->category->name ?? "غير محدد" }}';

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>تفاصيل المنتج - ${name}</title>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 30px; direction: rtl; }
                    .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                    .header h1 { margin: 0; font-size: 24px; }
                    .info-row { display: flex; border-bottom: 1px solid #eee; padding: 10px 0; }
                    .info-label { width: 150px; font-weight: bold; color: #666; }
                    .info-value { flex: 1; }
                    .footer { margin-top: 30px; text-align: center; color: #888; font-size: 12px; }
                    @media print { body { -webkit-print-color-adjust: exact; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>تفاصيل المنتج</h1>
                    <p>تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')}</p>
                </div>
                <div class="info-row"><span class="info-label">اسم المنتج:</span><span class="info-value">${name}</span></div>
                <div class="info-row"><span class="info-label">رقم المنتج:</span><span class="info-value">${sku}</span></div>
                <div class="info-row"><span class="info-label">الباركود:</span><span class="info-value">${barcode || 'لا يوجد'}</span></div>
                <div class="info-row"><span class="info-label">التصنيف:</span><span class="info-value">${category}</span></div>
                <div class="info-row"><span class="info-label">سعر البيع:</span><span class="info-value">${price} {{ \App\Support\CurrencySettings::symbol() }}</span></div>
                <div class="info-row"><span class="info-label">سعر التكلفة:</span><span class="info-value">${cost} {{ \App\Support\CurrencySettings::symbol() }}</span></div>
                <div class="info-row"><span class="info-label">الكمية المتاحة:</span><span class="info-value">${quantity}</span></div>
                <div class="footer">تم الطباعة من نظام إدارة المتجر الإلكتروني</div>
                <script>window.print();<\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    // Print Direct (Simple)
    function printProductDirect() {
        window.print();
    }
</script>

@include('onlinestore::layouts._modal-helper')

{{-- Modal الحذف --}}
<div class="modal fade" id="modal_DELETE{{ $product->id }}" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fa fa-exclamation-triangle"></i> تأكيد الحذف
                </h5>
                <button type="button" class="close text-white" data-os-dismiss aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fa fa-trash-alt text-danger" style="font-size: 48px;"></i>
                </div>
                <p class="text-center">هل أنت متأكد من حذف المنتج:</p>
                <h5 class="text-center text-danger">{{ $product->name }}</h5>
                <p class="text-center text-muted">
                    <small>رقم المنتج: {{ $product->serial_number }}</small>
                </p>
                <div class="alert alert-warning">
                    <i class="fa fa-warning"></i>
                    <strong>تحذير:</strong> هذا الإجراء لا يمكن التراجع عنه!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-os-dismiss>
                    <i class="fa fa-times"></i> إلغاء
                </button>
                @if (hasPermission('online_store.products.delete'))
                    <form action="{{ route('store_products.destroy', $product) }}" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="fa fa-trash"></i> تأكيد الحذف
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
