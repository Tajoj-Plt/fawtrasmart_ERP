{{-- resources/views/suppliers/partials/suppliers_table.blade.php --}}

@if ($suppliers->count() > 0)
    <table class="table table-hover mb-0" id="suppliersTable">
        <thead>
            <tr>
                <th class="text-center" style="width: 60px;">#</th>
                <th>اسم المورد</th>
                <th>رقم المورد</th>
                <th class="text-center">رقم الجوال</th>
                <th>البريد الإلكتروني</th>
                <th class="text-center">الرصيد</th>
                <th class="text-center">الحالة</th>
                <th>المسؤول</th>
                @if (hasPermission(['purchases.suppliers.view', 'purchases.suppliers.edit', 'purchases.suppliers.delete']))
                    <th class="text-center" style="width: 80px;">الإجراءات</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($suppliers as $supplier)
                <tr>
                    <td class="text-center stk-num">{{ $loop->iteration + ($suppliers->currentPage() - 1) * $suppliers->perPage() }}</td>
                    <td>
                        <div class="stk-media">
                            <span class="stk-media__thumb">{{ mb_substr($supplier->trade_name, 0, 1) }}</span>
                            <span class="stk-media__body">
                                <span class="stk-media__title">{{ $supplier->trade_name }}</span>
                                <span class="stk-media__sub">#{{ $supplier->id }}</span>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-light-info">{{ $supplier->number_suply ?: '-' }}</span>
                    </td>
                    <td class="text-center">
                        <span class="text-muted">{{ $supplier->mobile ?: 'غير محدد' }}</span>
                    </td>
                    <td>
                        @if ($supplier->email)
                            <a href="mailto:{{ $supplier->email }}">{{ Str::limit($supplier->email, 20) }}</a>
                        @else
                            <span class="text-muted">غير محدد</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge badge-light-{{ ($supplier->account->balance ?? 0) >= 0 ? 'success' : 'danger' }}">
                            {{ number_format($supplier->account->balance ?? 0, 2) }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if (hasPermission('purchases.suppliers.edit'))
                            <span class="stk-state {{ $supplier->status == 1 ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                data-id="{{ $supplier->id }}" data-name="{{ $supplier->trade_name }}" data-status="{{ $supplier->status }}">
                                <i class="feather icon-{{ $supplier->status == 1 ? 'check-circle' : 'x-circle' }}"></i>
                                {{ $supplier->status == 1 ? 'نشط' : 'موقوف' }}
                            </span>
                        @else
                            <span class="stk-state {{ $supplier->status == 1 ? 'stk-state--on' : 'stk-state--off' }}">
                                {{ $supplier->status == 1 ? 'نشط' : 'موقوف' }}
                            </span>
                        @endif
                    </td>
                    <td>
                        @if ($supplier->employee)
                            <span class="badge badge-light-primary">{{ $supplier->employee->full_name }}</span>
                        @else
                            <span class="text-muted">غير مسند</span>
                        @endif
                    </td>
                    @if (hasPermission(['purchases.suppliers.view', 'purchases.suppliers.edit', 'purchases.suppliers.delete']))
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if (hasPermission('purchases.suppliers.view'))
                                        <a class="dropdown-item" href="{{ route('store_suppliers.show', $supplier->id) }}">
                                            <i class="feather icon-eye"></i> عرض التفاصيل
                                        </a>
                                    @endif
                                    @if (hasPermission('purchases.suppliers.edit'))
                                        <a class="dropdown-item" href="{{ route('store_suppliers.edit', $supplier->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endif
                                    @if (hasPermission('purchases.suppliers.delete'))
                                        <div class="dropdown-divider"></div>
                                        <form action="{{ route('store_suppliers.destroy', $supplier->id) }}" method="POST" class="delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="feather icon-trash-2"></i> حذف
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    @php
        $hasSearchParams = request()->hasAny(['search', 'email', 'mobile', 'status']);
    @endphp

    <div class="stk-empty">
        @if ($hasSearchParams)
            <span class="stk-empty__icon"><i class="feather icon-search"></i></span>
            <h6 class="stk-empty__title">لا توجد نتائج</h6>
            <p class="stk-empty__hint">لا توجد نتائج تطابق معايير البحث المحددة.</p>
            <a href="{{ route('store_suppliers.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="feather icon-refresh-cw"></i> إعادة تعيين البحث
            </a>
        @else
            <span class="stk-empty__icon"><i class="feather icon-truck"></i></span>
            <h6 class="stk-empty__title">لا يوجد موردين</h6>
            <p class="stk-empty__hint">لم تتم إضافة أي موردين بعد.</p>
            @if (hasPermission('purchases.suppliers.create'))
                <a href="{{ route('store_suppliers.create') }}" class="btn btn-primary btn-sm">
                    <i class="feather icon-plus"></i> أضف مورد جديد
                </a>
            @endif
        @endif
    </div>
@endif
