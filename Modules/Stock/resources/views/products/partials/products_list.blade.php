{{--
    جدول المنتجات — يُعرض داخل الصفحة ويُعاد تركيبه وحده عند الفلترة بـAjax،
    فلا يعتمد على شيء من القالب سوى أنماط المخزون المحمَّلة أصلاً في الصفحة.

    المتغيّرات: $products (كل عنصرٍ فيه يحمل can_edit من الكنترولر — نطاق
    ScopesToEmployeeHierarchy لا صلاحيات النظام القديم)، و(اختياريّاً) $user_warehouse_ids.
--}}

@php
    $warehouseIds = $user_warehouse_ids ?? [];

    // إعداد «إظهار الكمية في المخزن» — إيقافه يُخفي عمود الكمية بقيمه
    $quantityVisible = $showWarehouseQuantity ?? true;

    $materialTypes = [
        'raw' => ['خام', 'warning'],
        'semi_finished' => ['نصف مصنَّع', 'info'],
        'finished' => ['منتج نهائي', 'success'],
    ];

    $trackingLabels = [
        0 => 'الرقم التسلسلي',
        1 => 'رقم الشحنة',
        2 => 'تاريخ الانتهاء',
        3 => 'رقم الشحنة وتاريخ الانتهاء',
        4 => 'الكمية فقط',
    ];
@endphp

@if (isset($products) && count($products) > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="productsTable">
            <thead>
                <tr>
                    <th style="width:26%">المنتج</th>
                    <th style="width:20%">التصنيف والنوع</th>
                    <th class="text-center" style="width:14%">المخزون</th>
                    <th class="text-center" style="width:16%">الأسعار</th>
                    <th style="width:16%">أُضيف</th>
                    <th class="text-center" style="width:8%">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $product)
                    @php
                        $isStocked = in_array($product->type, ['products', 'compiled'], true);

                        /* الكمية المعروضة تخصّ مخازن الموظف وحدها إن كان مقيَّداً بمخازن،
                           وإلا فهي مجموع كل المخازن (المدير مثلاً). */
                        $details = $product->productDetails ?? collect();
                        $quantity = (! $isStocked || ! $quantityVisible) ? null : $details
                            ->when(! empty($warehouseIds), fn ($rows) => $rows->whereIn('store_house_id', $warehouseIds))
                            ->sum('quantity');

                        $canEdit = $product->can_edit ?? false;

                        [$materialLabel, $materialTone] = $materialTypes[$product->material_type] ?? [null, null];
                    @endphp

                    {{-- قيمٌ نظيفة للتصدير والطباعة: الخلايا نفسها مركَّبة من
                         شارات وصور، فقراءتها نصّاً تُخرج سطوراً متلاصقة --}}
                    <tr data-name="{{ $product->name }}"
                        data-serial="{{ $product->serial_number }}"
                        data-barcode="{{ $product->barcode }}"
                        data-category="{{ optional($product->category)->name }}"
                        data-brand="{{ optional($product->brand)->name }}"
                        data-type="{{ $isStocked ? 'منتج' : 'خدمة' }}"
                        data-status="{{ $product->status->label() }}"
                        data-quantity="{{ $isStocked && $quantityVisible ? (float) $quantity : '' }}"
                        data-purchase="{{ (float) $product->purchase_price }}"
                        data-sale="{{ (float) $product->sale_price }}"
                        data-created="{{ optional($product->created_at)->format('Y-m-d H:i') }}">
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb">{{ Str::upper(Str::substr($product->name, 0, 1)) }}</span>
                                <div class="stk-media__body">
                                    <a href="{{ route('products.show', $product->id) }}" class="stk-media__title">
                                        {{ $product->name }}
                                    </a>
                                    <span class="stk-media__sub stk-num">#{{ $product->serial_number }}</span>
                                    @if ($product->barcode)
                                        <span class="stk-media__sub">
                                            <i class="feather icon-hash"></i>
                                            <span class="stk-num">{{ $product->barcode }}</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="stk-tags">
                                @if ($product->category)
                                    <span class="badge badge-light-primary">{{ $product->category->name }}</span>
                                @endif
                                @if ($product->brand)
                                    <span class="badge badge-light-secondary">{{ $product->brand->name }}</span>
                                @endif
                                @if ($materialLabel)
                                    <span class="badge badge-light-{{ $materialTone }}">{{ $materialLabel }}</span>
                                @endif
                                <span class="stk-state stk-state--{{ $product->status->value === 'active' ? 'on' : 'off' }}">
                                    {{ $product->status->label() }}
                                </span>
                            </div>
                            @isset($trackingLabels[$product->track_inventory])
                                <span class="stk-media__sub d-block mt-1">
                                    <i class="feather icon-crosshair"></i> {{ $trackingLabels[$product->track_inventory] }}
                                </span>
                            @endisset
                        </td>

                        <td class="text-center">
                            @if (! $isStocked)
                                <span class="stk-state stk-state--neutral">خدمة</span>
                            @elseif (! $quantityVisible)
                                {{-- الإظهار موقوف من إعدادات المنتجات --}}
                                <span class="stk-media__sub">—</span>
                            @elseif ($quantity > 0)
                                <span class="stk-qty stk-num">{{ number_format($quantity) }}</span>
                                <span class="stk-media__sub d-block">متاح</span>
                            @else
                                <span class="stk-state stk-state--off">نفدت الكمية</span>
                            @endif
                        </td>

                        <td class="text-center">
                            @if ($product->purchase_price || $product->sale_price)
                                <div class="stk-price">
                                    @if ($product->purchase_price)
                                        <span class="stk-price__row">
                                            <span class="stk-price__tag">شراء</span>
                                            <span class="stk-num">@money($product->purchase_price)</span>
                                        </span>
                                    @endif
                                    @if ($product->sale_price)
                                        <span class="stk-price__row stk-price__row--sale">
                                            <span class="stk-price__tag">بيع</span>
                                            <span class="stk-num">@money($product->sale_price)</span>
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        <td>
                            <span class="stk-media__sub d-block stk-num">
                                {{ optional($product->created_at)->format('Y-m-d H:i') }}
                            </span>
                            <span class="stk-media__sub d-block">
                                <i class="feather icon-user"></i> {{ $product->user->name ?? 'غير محدد' }}
                            </span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false" aria-label="خيارات {{ $product->name }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('products.show', $product->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>

                                    @if ($canEdit)
                                        <a class="dropdown-item" href="{{ route('products.edit', $product->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endif

                                    <a class="dropdown-item" href="{{ route('products.print_barcode', $product->id) }}">
                                        <i class="feather icon-printer"></i> طباعة الباركود
                                    </a>

                                    @if ($canEdit)
                                        <div class="dropdown-divider"></div>
                                        {{-- addslashes قبل الهروب: الاسم قد يحمل علامة اقتباس تكسر النداء --}}
                                        <a class="dropdown-item text-danger" href="javascript:void(0)"
                                           onclick="confirmDelete({{ $product->id }}, '{{ addslashes($product->name) }}')">
                                            <i class="feather icon-trash-2"></i> حذف
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('stock::products.partials.pagination', ['products' => $products])
@else
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
        <h5 class="stk-empty__title">لا توجد منتجات</h5>
        <p class="stk-empty__hint">لا شيء يطابق معايير البحث الحالية — جرّب توسيع الفلاتر</p>
    </div>
@endif
