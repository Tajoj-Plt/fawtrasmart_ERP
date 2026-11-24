@extends('stock::master')

@section('title', $price_list->name)

@section('content')
    @php
        $rows = $list_products ?? collect();
        $isActive = $price_list->status == 0;
    @endphp

    <div class="content-body">

        @section('page-actions')
            @can('stock.price_lists.manage_products')
                <button type="button" class="btn btn-primary"
                        data-toggle="modal" data-bs-toggle="modal"
                        data-target="#addProductModal" data-bs-target="#addProductModal">
                    <i class="feather icon-plus"></i> إضافة منتج
                </button>
            @endcan

            @can('stock.price_lists.edit')
                <a href="{{ route('price_list.edit', $price_list->id) }}" class="btn btn-outline-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endcan

            @can('products_delete_price_groups')
                <button type="button" class="btn btn-outline-danger"
                        data-toggle="modal" data-bs-toggle="modal"
                        data-target="#deleteModal" data-bs-target="#deleteModal">
                    <i class="feather icon-trash-2"></i> حذف
                </button>
            @endcan
        @endsection

        @include('stock::components.page-head', [
            'title' => $price_list->name,
            'icon' => 'tag',
            'sub' => 'سلع هذه القائمة وأسعارها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قوائم الأسعار' => route('price_list.index'),
                $price_list->name => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num">{{ $rows->count() }}</span>
                <span class="stk-metric__label">عدد السلع</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-toggle-right"></i></span>
                <span class="stk-metric__value">
                    <span class="stk-state stk-state--{{ $isActive ? 'on' : 'off' }}">
                        {{ $isActive ? 'نشطة' : 'موقوفة' }}
                    </span>
                </span>
                <span class="stk-metric__label">الحالة</span>
            </div>
        </div>

        {{-- ══════════ تصفية السلع ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> تصفية السلع</h6>
                </div>

                <div class="row">
                    <div class="col-md-6 col-sm-12">
                        <div class="form-group mb-0">
                            <label for="product-filter">المنتج</label>
                            {{-- كان الحقلان يحملان الاسم `category` كلاهما، ولم يكونا موصولين
                                 بأي تصفية. صارا تصفيةً في المتصفّح على الجدول المعروض. --}}
                            <select id="product-filter" class="form-control select2">
                                <option value="">الكل</option>
                                @foreach ($rows as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6 col-sm-12">
                        <div class="form-group mb-0">
                            <label for="brand-filter">الماركة</label>
                            <select id="brand-filter" class="form-control select2">
                                <option value="">الكل</option>
                                {{-- `brand` علاقةٌ لا نصّ: طباعتها مباشرةً تُخرج JSON كاملاً للماركة --}}
                                @foreach ($rows->map(fn ($row) => optional($row->brand)->name)->filter()->unique() as $brandName)
                                    <option value="{{ $brandName }}">{{ $brandName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ السلع ══════════ --}}
        <div class="card stk-table-card">
            @if ($rows->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> السلع
                        <span class="stk-count">{{ $rows->count() }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="products-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:14%">الكود</th>
                                <th>البند</th>
                                <th style="width:18%">الماركة</th>
                                <th class="text-center" style="width:16%">سعر البيع</th>
                                <th class="text-center" style="width:10%">حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $product)
                                <tr class="product-row"
                                    data-id="{{ $product->id }}"
                                    data-brand="{{ optional($product->brand)->name }}">

                                    <td class="text-center stk-num">{{ $product->barcode ?: '—' }}</td>
                                    <td><span class="stk-media__title">{{ $product->name }}</span></td>
                                    <td>{{ optional($product->brand)->name ?: '—' }}</td>
                                    <td class="text-center stk-num">@money($product->sale_price)</td>
                                    <td class="text-center">
                                        {{-- سطر الربط لا المنتج: الحذف من القائمة لا من النظام --}}
                                        @can('products_delete_price_groups')
                                        <a href="{{ route('price_list.delete_product', $product->pivot->id) }}"
                                           class="btn btn-sm btn-outline-danger" aria-label="حذف السلعة"
                                           onclick="return confirm('حذف «{{ addslashes($product->name) }}» من القائمة؟')">
                                            <i class="feather icon-trash-2"></i>
                                        </a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="stk-table-foot" id="filter-empty" hidden>
                    <p class="stk-table-foot__count">لا سلعة تطابق التصفية الحالية</p>
                </div>
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                        <h5 class="stk-empty__title">لا سلع في هذه القائمة</h5>
                        <p class="stk-empty__hint">أضف منتجاً وحدّد سعره الخاص بهذه القائمة</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════ إضافة منتج ══════════ --}}
    <div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form action="{{ route('price_list.add_product', $price_list->id) }}" method="POST">
                @csrf
                <div class="modal-content text-right">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة منتج للقائمة</h5>
                        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <label for="product-select" class="stk-req">المنتج</label>
                            <select id="product-select" name="product_id" required class="form-control select2">
                                <option value="">اختر المنتج…</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" data-price="{{ $product->sale_price }}">
                                        {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('product_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <label for="sale-price-input">سعر البيع في هذه القائمة</label>
                            <input type="number" step="0.01" min="0" id="sale-price-input" name="sale_price"
                                   class="form-control stk-num" value="{{ old('sale_price') }}">
                            <span class="stk-help">يُملأ بسعر المنتج الافتراضي عند اختياره</span>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                            إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-plus"></i> إضافة
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════ حذف القائمة ══════════ --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف قائمة الأسعار</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">ستُحذف <strong>{{ $price_list->name }}</strong> وكل أسعارها نهائياً.</p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <a href="{{ route('price_list.delete', $price_list->id) }}" class="btn btn-danger">
                        <i class="feather icon-trash-2"></i> حذف نهائي
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
                $('#product-select').select2({
                    placeholder: 'اختر المنتج…', dir: 'rtl', width: '100%',
                    dropdownParent: $('#addProductModal'),
                });
            }

            /* اختيار المنتج يملأ سعره الافتراضي */
            $('#product-select').on('change', function () {
                const option = this.options[this.selectedIndex];
                $('#sale-price-input').val(option ? (option.dataset.price || '') : '');
            });

            function applyFilters() {
                const product = $('#product-filter').val();
                const brand = $('#brand-filter').val();
                let visible = 0;

                $('.product-row').each(function () {
                    const row = $(this);
                    const matches =
                        (! product || String(row.data('id')) === String(product))
                        && (! brand || String(row.data('brand')) === String(brand));

                    row.toggle(matches);
                    if (matches) visible++;
                });

                const empty = document.getElementById('filter-empty');
                if (empty) empty.hidden = visible > 0;
            }

            $('#product-filter, #brand-filter').on('change', applyFilters);
        });
    </script>
@endsection
