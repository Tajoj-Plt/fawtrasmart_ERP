@extends('onlinestore::master')

@section('title', 'تفاصيل الصنف: ' . $category->name)

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.categories.edit'))
                <a href="{{ route('store_categories.edit', $category) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            @if (hasPermission('online_store.categories.delete'))
                <form action="{{ route('store_categories.destroy', $category) }}" method="POST" class="delete-form d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="feather icon-trash-2"></i> حذف
                    </button>
                </form>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل الصنف',
            'icon' => 'folder',
            'sub' => $category->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأصناف' => route('store_categories.index'),
                $category->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__label">أصناف فرعية</span>
                <span class="stk-metric__value">{{ $subCategories ? $subCategories->count() : 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">منتجات</span>
                <span class="stk-metric__value">{{ $products ? $products->total() : 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">منتجات نشطة</span>
                <span class="stk-metric__value">{{ $stats['active_products'] ?? 0 }}</span>
            </div>
        </div>

        <div class="row">
            <!-- القسم الرئيسي -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-folder"></i> معلومات الصنف</h6>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الاسم</dt>
                                <dd>{{ $category->name }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>النوع</dt>
                                <dd>
                                    @if ($category->main_category && $parent)
                                        <span class="badge badge-light-info">
                                            <i class="feather icon-folder"></i> تابع لـ: {{ $parent->name }}
                                        </span>
                                    @else
                                        <span class="badge badge-light-primary">صنف رئيسي</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الوصف</dt>
                                <dd>{{ $category->discretion ?: '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإنشاء</dt>
                                <dd class="stk-num">{{ $category->created_at ? $category->created_at->format('Y-m-d H:i') : '-' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>آخر تحديث</dt>
                                <dd class="stk-num">{{ $category->updated_at ? $category->updated_at->format('Y-m-d H:i') : '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- الأصناف الفرعية -->
                @if ($subCategories && $subCategories->count() > 0)
                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-layers"></i> الأصناف الفرعية</h6>
                            <span class="badge badge-light-primary">{{ $subCategories->count() }} صنف</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الاسم</th>
                                        <th class="text-center">عدد المنتجات</th>
                                        @if (hasPermission(['online_store.categories.view', 'online_store.categories.edit']))
                                            <th class="text-center">الإجراءات</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($subCategories as $sub)
                                        <tr>
                                            <td class="stk-num">{{ $sub->id }}</td>
                                            <td>
                                                @if ($sub->attachments)
                                                    <img src="{{ asset($sub->attachments) }}" alt=""
                                                        class="rounded me-1" style="width: 30px; height: 30px; object-fit: cover;">
                                                @endif
                                                {{ $sub->name }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-light-primary">{{ $sub->products_count ?? 0 }}</span>
                                            </td>
                                            @if (hasPermission(['online_store.categories.view', 'online_store.categories.edit']))
                                                <td class="text-center">
                                                    @if (hasPermission('online_store.categories.view'))
                                                        <a href="{{ route('store_categories.show', $sub) }}" class="btn btn-sm btn-icon btn-light-info">
                                                            <i class="feather icon-eye"></i>
                                                        </a>
                                                    @endif
                                                    @if (hasPermission('online_store.categories.edit'))
                                                        <a href="{{ route('store_categories.edit', $sub) }}" class="btn btn-sm btn-icon btn-light-warning">
                                                            <i class="feather icon-edit-2"></i>
                                                        </a>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- المنتجات -->
                @if ($products && $products->count() > 0)
                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-package"></i> المنتجات</h6>
                            <span class="badge badge-light-primary">{{ $products->total() }} منتج</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>المنتج</th>
                                        <th class="text-center">السعر</th>
                                        <th class="text-center">الحالة</th>
                                        @if (hasPermission(['online_store.products.show', 'online_store.products.edit']))
                                            <th class="text-center">الإجراءات</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($products as $product)
                                        <tr>
                                            <td class="stk-num">{{ $product->id }}</td>
                                            <td>
                                                {{ $product->name }}
                                                @if ($product->barcode)
                                                    <small class="text-muted d-block">{{ $product->barcode }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center stk-num">{{ number_format($product->sale_price, 2) }}</td>
                                            <td class="text-center">
                                                <span class="stk-state {{ $product->status == 'active' ? 'stk-state--on' : 'stk-state--off' }}">
                                                    {{ $product->status == 'active' ? 'نشط' : 'غير نشط' }}
                                                </span>
                                            </td>
                                            @if (hasPermission(['online_store.products.show', 'online_store.products.edit']))
                                                <td class="text-center">
                                                    @if (hasPermission('online_store.products.show'))
                                                        <a href="{{ route('store_products.show', $product) }}" class="btn btn-sm btn-icon btn-light-info">
                                                            <i class="feather icon-eye"></i>
                                                        </a>
                                                    @endif
                                                    @if (hasPermission('online_store.products.edit'))
                                                        <a href="{{ route('store_products.edit', $product) }}" class="btn btn-sm btn-icon btn-light-warning">
                                                            <i class="feather icon-edit-2"></i>
                                                        </a>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($products->hasPages())
                            <div class="p-3">
                                {{ $products->links() }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- القسم الجانبي -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-image"></i> صورة الصنف</h6>
                    </div>
                    <div class="card-body text-center">
                        @if ($category->attachments)
                            <img src="{{ asset($category->attachments) }}" alt="{{ $category->name }}"
                                class="img-fluid rounded" style="max-height: 250px;">
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-image"></i></span>
                                <p class="stk-empty__hint mb-0">لا توجد صورة</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا الصنف نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@endsection
