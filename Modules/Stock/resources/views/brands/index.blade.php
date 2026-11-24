@extends('stock::master')

@section('title', 'البراندات')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'البراندات',
            'icon' => 'award',
            'sub' => 'العلامات التجارية المرتبطة بالمنتجات',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات المخزون' => route('product_settings.index'),
                'البراندات' => null,
            ],
            'actions' => hasPermission('stock.brands.create')
                ? '<a href="' . route('stock.brands.create') . '" class="btn btn-primary">'
                    . '<i class="feather icon-plus"></i> براند جديد</a>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['search', 'status', 'is_featured']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('stock.brands.index') }}">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="search">اسم البراند</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       value="{{ request('search') }}" placeholder="اكتب للبحث…">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>غير نشط</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="is_featured">مميّز</label>
                                <select id="is_featured" name="is_featured" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="1" {{ request('is_featured') === '1' ? 'selected' : '' }}>مميّز</option>
                                    <option value="0" {{ request('is_featured') === '0' ? 'selected' : '' }}>عادي</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('stock.brands.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            @if (isset($brands) && count($brands) > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> قائمة البراندات
                        <span class="stk-count">{{ method_exists($brands, 'total') ? $brands->total() : count($brands) }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>البراند</th>
                                <th>الرابط</th>
                                <th class="text-center">الترتيب</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:140px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($brands as $brand)
                                <tr>
                                    <td>
                                        <div class="stk-media">
                                            @if ($brand->logo)
                                                <img src="{{ asset('storage/' . $brand->logo) }}"
                                                     alt="{{ $brand->name }}" class="stk-media__thumb">
                                            @else
                                                <span class="stk-media__thumb"><i class="feather icon-award"></i></span>
                                            @endif
                                            <span class="stk-media__body">
                                                <span class="stk-media__title">
                                                    {{ $brand->name }}
                                                    @if ($brand->is_featured)
                                                        <i class="feather icon-star text-warning" title="مميّز"></i>
                                                    @endif
                                                </span>
                                                @if ($brand->slug)
                                                    <span class="stk-media__sub" dir="ltr">{{ $brand->slug }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        @if ($brand->website)
                                            <a href="{{ $brand->website }}" target="_blank" rel="noopener" dir="ltr">
                                                {{ Str::limit($brand->website, 32) }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-center stk-num">{{ $brand->sort_order ?? 0 }}</td>

                                    <td class="text-center">
                                        <span class="stk-state stk-state--{{ $brand->is_active ? 'on' : 'off' }}">
                                            {{ $brand->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <div class="stk-actions">
                                            <a href="{{ route('stock.brands.show', $brand->id) }}"
                                               class="btn btn-outline-secondary" title="عرض" aria-label="عرض">
                                                <i class="feather icon-eye"></i>
                                            </a>
                                            @permission('stock.brands.edit')
                                            <a href="{{ route('stock.brands.edit', $brand->id) }}"
                                               class="btn btn-outline-primary" title="تعديل" aria-label="تعديل">
                                                <i class="feather icon-edit-2"></i>
                                            </a>
                                            @endpermission
                                            @permission('stock.brands.delete')
                                            <button type="button" class="btn btn-outline-danger"
                                                    data-toggle="modal" data-target="#del_{{ $brand->id }}"
                                                    title="حذف" aria-label="حذف">
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                            @endpermission
                                        </div>

                                        @permission('stock.brands.delete')
                                        <div class="modal fade" id="del_{{ $brand->id }}" tabindex="-1"
                                             role="dialog" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                <div class="modal-content text-right">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">حذف البراند</h5>
                                                        <button type="button" class="close" data-dismiss="modal"
                                                                aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-1">
                                                            سيُحذف البراند <strong>{{ $brand->name }}</strong> نهائياً.
                                                        </p>
                                                        <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                                data-dismiss="modal">إلغاء</button>
                                                        <form action="{{ route('stock.brands.destroy', $brand->id) }}"
                                                              method="POST" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="feather icon-trash-2"></i> حذف نهائي
                                                            </button>
                                                        </form>
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

                @if ($brands instanceof \Illuminate\Pagination\LengthAwarePaginator && $brands->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">
                            عرض {{ $brands->firstItem() }}–{{ $brands->lastItem() }} من {{ $brands->total() }}
                        </p>
                        {{ $brands->withQueryString()->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-award"></i></div>
                        <h5 class="stk-empty__title">لا توجد براندات</h5>
                        <p class="stk-empty__hint">
                            @if (request()->hasAny(['search', 'status', 'is_featured']))
                                لم يطابق البحث أيّ براند — جرّب معايير أوسع
                            @else
                                أضف علامةً تجارية لتربط بها منتجاتك
                            @endif
                        </p>
                        @if (request()->hasAny(['search', 'status', 'is_featured']))
                            <a href="{{ route('stock.brands.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-rotate-ccw"></i> إزالة الفلاتر
                            </a>
                        @elseif (hasPermission('stock.brands.create'))
                            <a href="{{ route('stock.brands.create') }}" class="btn btn-primary">
                                <i class="feather icon-plus"></i> إضافة براند
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
