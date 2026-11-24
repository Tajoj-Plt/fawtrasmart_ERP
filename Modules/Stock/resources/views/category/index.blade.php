@extends('stock::master')

@section('title', 'الأصناف')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'الأصناف',
            'icon' => 'tag',
            'sub' => 'تصنيف المنتجات وربط كل صنف بوحدات قياسه',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات المخزون' => route('product_settings.index'),
                'الأصناف' => null,
            ],
            'actions' => hasPermission('stock.categories.create')
                ? '<a href="' . route('category.create') . '" class="btn btn-primary">'
                    . '<i class="feather icon-plus"></i> صنف جديد</a>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['search', 'product_group_id', 'main_category', 'is_active']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('category.index') }}">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="search">الاسم أو الكود</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       value="{{ request('search') }}" placeholder="اكتب للبحث…">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="product_group_id">مجموعة المنتجات</label>
                                <select id="product_group_id" name="product_group_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($productGroups ?? [] as $group)
                                        <option value="{{ $group->id }}"
                                            {{ request('product_group_id') == $group->id ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="main_category">الصنف الرئيسي</label>
                                <select id="main_category" name="main_category" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($parentCategories ?? [] as $parent)
                                        <option value="{{ $parent->id }}"
                                            {{ request('main_category') == $parent->id ? 'selected' : '' }}>
                                            {{ $parent->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="is_active">الحالة</label>
                                <select id="is_active" name="is_active" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>نشط</option>
                                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>غير نشط</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('category.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ الجدول ══════════ --}}
        <div class="card stk-table-card">
            @if (isset($categories) && count($categories) > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> قائمة الأصناف
                        <span class="stk-count">{{ $categories->total() }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th style="width:44px">#</th>
                                <th>الصنف</th>
                                <th>الكود</th>
                                <th>المجموعة</th>
                                <th>الصنف الرئيسي</th>
                                <th class="text-center">الوحدات</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:170px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $index => $category)
                                <tr>
                                    <td class="stk-num text-muted">{{ $categories->firstItem() + $index }}</td>

                                    <td>
                                        <div class="stk-media">
                                            @if ($category->attachments)
                                                <img src="{{ asset($category->attachments) }}"
                                                     alt="{{ $category->name }}" class="stk-media__thumb">
                                            @else
                                                <span class="stk-media__thumb"><i class="feather icon-tag"></i></span>
                                            @endif
                                            <span class="stk-media__body">
                                                <span class="stk-media__title">{{ $category->name }}</span>
                                                @if ($category->discretion)
                                                    <span class="stk-media__sub">{{ Str::limit($category->discretion, 42) }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        @if ($category->code)
                                            <span class="badge badge-secondary stk-num">{{ $category->code }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($category->productGroup)
                                            <span class="badge badge-light-info">{{ $category->productGroup->name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($category->parent)
                                            {{ $category->parent->name }}
                                        @else
                                            <span class="text-muted">صنف رئيسي</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <span class="badge badge-light-primary stk-num">
                                            {{ $category->units_count ?? $category->units->count() }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <span class="stk-state stk-state--{{ $category->is_active ? 'on' : 'off' }}">
                                            {{ $category->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <div class="stk-actions">
                                            <a href="{{ route('category.show', $category->id) }}"
                                               class="btn btn-outline-secondary" title="عرض" aria-label="عرض">
                                                <i class="feather icon-eye"></i>
                                            </a>
                                            @permission('stock.categories.edit')
                                            <a href="{{ route('category.edit', $category->id) }}"
                                               class="btn btn-outline-primary" title="تعديل" aria-label="تعديل">
                                                <i class="feather icon-edit-2"></i>
                                            </a>
                                            @endpermission
                                            <a href="{{ route('category_units.index', $category->id) }}"
                                               class="btn btn-outline-info" title="الوحدات" aria-label="الوحدات">
                                                <i class="feather icon-sliders"></i>
                                            </a>
                                            @permission('stock.categories.toggle_status')
                                            <button type="button" class="btn btn-outline-warning toggle-status"
                                                    data-id="{{ $category->id }}"
                                                    title="{{ $category->is_active ? 'تعطيل' : 'تفعيل' }}"
                                                    aria-label="{{ $category->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                <i class="feather icon-{{ $category->is_active ? 'slash' : 'check' }}"></i>
                                            </button>
                                            @endpermission
                                            @permission('stock.categories.delete')
                                            <button type="button" class="btn btn-outline-danger"
                                                    data-toggle="modal" data-target="#delete_{{ $category->id }}"
                                                    title="حذف" aria-label="حذف">
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                            @endpermission
                                        </div>

                                        @permission('stock.categories.delete')
                                        <div class="modal fade" id="delete_{{ $category->id }}" tabindex="-1"
                                             role="dialog" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                <div class="modal-content text-right">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">حذف الصنف</h5>
                                                        <button type="button" class="close" data-dismiss="modal"
                                                                aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-1">
                                                            سيُحذف الصنف <strong>{{ $category->name }}</strong> نهائياً.
                                                        </p>
                                                        <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>

                                                        @php $linked = $category->products_count ?? $category->products->count(); @endphp
                                                        @if ($linked > 0)
                                                            <div class="alert alert-warning mt-3 mb-0">
                                                                <i class="feather icon-alert-triangle"></i>
                                                                <span>مرتبط به {{ $linked }} منتجاً</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                                data-dismiss="modal">إلغاء</button>
                                                        <a href="{{ route('category.delete', $category->id) }}"
                                                           class="btn btn-danger">
                                                            <i class="feather icon-trash-2"></i> حذف نهائي
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endpermission
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($categories->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">
                            عرض {{ $categories->firstItem() }}–{{ $categories->lastItem() }}
                            من {{ $categories->total() }}
                        </p>
                        {{ $categories->withQueryString()->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-tag"></i></div>
                        <h5 class="stk-empty__title">لا توجد أصناف</h5>
                        <p class="stk-empty__hint">
                            @if (request()->hasAny(['search', 'product_group_id', 'main_category', 'is_active']))
                                لم يطابق البحث أيّ صنف — جرّب معايير أوسع
                            @else
                                ابدأ بإنشاء صنف لتنظيم منتجاتك وربطها بوحداتها
                            @endif
                        </p>
                        @if (request()->hasAny(['search', 'product_group_id', 'main_category', 'is_active']))
                            <a href="{{ route('category.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-rotate-ccw"></i> إزالة الفلاتر
                            </a>
                        @elseif (hasPermission('stock.categories.create'))
                            <a href="{{ route('category.create') }}" class="btn btn-primary">
                                <i class="feather icon-plus"></i> إضافة صنف
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            $(document).on('click', '.toggle-status', function () {
                const $btn = $(this).prop('disabled', true);

                $.ajax({
                    url: '{{ url("/stock/category/toggle-status") }}/' + $btn.data('id'),
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: r => r.success ? location.reload() : $btn.prop('disabled', false),
                    error: () => $btn.prop('disabled', false),
                });
            });
        });
    </script>
@endsection
