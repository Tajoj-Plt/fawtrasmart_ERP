@extends('stock::master')

@section('title', 'الوحدات')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'وحدات القياس',
            'icon' => 'sliders',
            'sub' => 'الوحدات المتاحة في النظام وترتبط بها الأصناف',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات المخزون' => route('product_settings.index'),
                'الوحدات' => null,
            ],
            'actions' => hasPermission('stock.units.create')
                ? '<a href="' . route('units.create') . '" class="btn btn-primary">'
                    . '<i class="feather icon-plus"></i> وحدة جديدة</a>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['search', 'is_active']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('units.index') }}">
                    <div class="row">
                        <div class="col-md-8 col-sm-12">
                            <div class="form-group">
                                <label for="search">الاسم أو الرمز</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       value="{{ request('search') }}" placeholder="اكتب للبحث…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
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
                        <a href="{{ route('units.index') }}" class="btn btn-outline-secondary">
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
            @if (isset($units) && count($units) > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> قائمة الوحدات
                        <span class="stk-count">{{ method_exists($units, 'total') ? $units->total() : count($units) }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th style="width:44px">#</th>
                                <th>الوحدة</th>
                                <th class="text-center">الرمز</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:110px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($units as $index => $unit)
                                <tr>
                                    <td class="stk-num text-muted">
                                        {{ method_exists($units, 'firstItem') ? $units->firstItem() + $index : $index + 1 }}
                                    </td>

                                    <td>
                                        <div class="stk-media">
                                            <span class="stk-media__thumb"><i class="feather icon-box"></i></span>
                                            <span class="stk-media__body">
                                                <span class="stk-media__title">{{ $unit->name }}</span>
                                                @if ($unit->description)
                                                    <span class="stk-media__sub">{{ Str::limit($unit->description, 48) }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        @if ($unit->symbol)
                                            <span class="badge badge-secondary">{{ $unit->symbol }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <span class="stk-state stk-state--{{ $unit->is_active ? 'on' : 'off' }}">
                                            {{ $unit->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <div class="stk-actions">
                                            @permission('stock.units.edit')
                                            <a href="{{ route('units.edit', $unit->id) }}"
                                               class="btn btn-outline-primary" title="تعديل" aria-label="تعديل">
                                                <i class="feather icon-edit-2"></i>
                                            </a>
                                            @endpermission
                                            @permission('stock.units.delete')
                                            <button type="button" class="btn btn-outline-danger"
                                                    data-toggle="modal" data-target="#del_{{ $unit->id }}"
                                                    title="حذف" aria-label="حذف">
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                            @endpermission
                                        </div>

                                        @permission('stock.units.delete')
                                        <div class="modal fade" id="del_{{ $unit->id }}" tabindex="-1"
                                             role="dialog" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                <div class="modal-content text-right">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">حذف الوحدة</h5>
                                                        <button type="button" class="close" data-dismiss="modal"
                                                                aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-1">
                                                            ستُحذف الوحدة <strong>{{ $unit->name }}</strong> نهائياً.
                                                        </p>
                                                        <p class="stk-empty__hint mb-0">
                                                            الأصناف المرتبطة بها تفقد وحدتها — راجعها قبل الحذف.
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                                data-dismiss="modal">إلغاء</button>
                                                        <form action="{{ route('units.destroy', $unit->id) }}"
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

                @if ($units instanceof \Illuminate\Pagination\LengthAwarePaginator && $units->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">
                            عرض {{ $units->firstItem() }}–{{ $units->lastItem() }} من {{ $units->total() }}
                        </p>
                        {{ $units->withQueryString()->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-sliders"></i></div>
                        <h5 class="stk-empty__title">لا توجد وحدات</h5>
                        <p class="stk-empty__hint">
                            @if (request()->hasAny(['search', 'is_active']))
                                لم يطابق البحث أيّ وحدة — جرّب معايير أوسع
                            @else
                                أضف وحدة قياس لتربطها بأصنافك
                            @endif
                        </p>
                        @if (request()->hasAny(['search', 'is_active']))
                            <a href="{{ route('units.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-rotate-ccw"></i> إزالة الفلاتر
                            </a>
                        @elseif (hasPermission('stock.units.create'))
                            <a href="{{ route('units.create') }}" class="btn btn-primary">
                                <i class="feather icon-plus"></i> إضافة وحدة
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
