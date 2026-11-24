{{--
    جسم جدول المستودعات — منفصلٌ عن index.blade.php كي يُستبدَل بطلب Ajax
    عند البحث أو تغيير الصفحة أو عدد السجلات، دون إعادة تحميل الصفحة كلّها.
--}}

@php
    $states = [
        'active' => ['نشط', 'on'],
        'inactive' => ['غير نشط', 'off'],
        'stopped' => ['متوقف', 'off'],
    ];
@endphp

@if ($storehouses->count() > 0)
    <div class="table-responsive stk-scroll">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>المستودع</th>
                    <th>الحساب المحاسبي</th>
                    <th class="text-center">عدد الأصناف</th>
                    <th class="text-center">الكمية الإجمالية</th>
                    <th class="text-center">قيمة المخزون</th>
                    <th>الموظفون (مستودعهم الافتراضي)</th>
                    <th class="text-center" style="width:12%">الحالة</th>
                    <th class="text-center" style="width:10%">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($storehouses as $index => $storehouse)
                    @php
                        [$stateLabel, $stateTone] = $states[$storehouse->status] ?? ['غير محدد', 'off'];
                        $isMain = $storehouse->major == 1;
                        $meta = $storehouseMeta[$storehouse->id] ?? null;
                    @endphp

                    <tr>
                        <td class="stk-num text-muted">{{ $storehouses->firstItem() + $index }}</td>

                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb">
                                    <i class="feather icon-{{ $isMain ? 'star' : 'home' }}"></i>
                                </span>
                                <div class="stk-media__body">
                                    <span class="stk-media__title">
                                        @if (hasPermission('stock.warehouses.view'))
                                            <a href="{{ route('storehouse.show', $storehouse->id) }}">{{ $storehouse->name }}</a>
                                        @else
                                            {{ $storehouse->name }}
                                        @endif
                                        @if ($isMain)
                                            <span class="badge badge-light-success">رئيسي</span>
                                        @endif
                                    </span>
                                    <span class="stk-media__sub d-block">
                                        <i class="feather icon-git-branch"></i>
                                        {{ optional($storehouse->branch)->name ?? 'بلا فرع' }}
                                    </span>
                                    <span class="stk-media__sub d-block">
                                        <i class="feather icon-map-pin"></i>
                                        {{ $storehouse->shipping_address ?: 'بلا عنوان' }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <td>
                            @if ($meta && $meta['account'])
                                <span class="stk-media__title">{{ $meta['account']->name }}</span>
                                <div class="stk-help stk-num">{{ $meta['account']->code ?: '—' }} · @moneyPlain($meta['account']->balance)</div>
                            @else
                                <span class="text-muted">لا يوجد حساب مرتبط</span>
                            @endif
                        </td>

                        <td class="text-center stk-num">{{ number_format($meta['products_count'] ?? 0) }}</td>
                        <td class="text-center stk-num">{{ number_format($meta['total_quantity'] ?? 0, 2) }}</td>
                        <td class="text-center stk-num">@moneyPlain($meta['stock_value'] ?? 0)</td>

                        <td>
                            @if ($meta && $meta['responsible_employees']->isNotEmpty())
                                @foreach ($meta['responsible_employees'] as $employee)
                                    <a href="{{ route('employee.show', $employee->id) }}" class="d-block">
                                        {{ $employee->full_name }}
                                    </a>
                                @endforeach
                            @else
                                <span class="text-muted">لا يوجد</span>
                            @endif
                        </td>

                        <td class="text-center">
                            <div class="dropdown d-inline-block">
                                {{-- الشارة نفسها هي زرّ التغيير: كانت شارةً وزراً منفصلين --}}
                                <button type="button"
                                        class="btn btn-sm btn-link p-0 dropdown-toggle stk-state stk-state--{{ $stateTone }}"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ $stateLabel }}
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @foreach ($states as $value => $state)
                                        <a class="dropdown-item status-change" href="#"
                                           data-id="{{ $storehouse->id }}" data-status="{{ $value }}">
                                            {{ $state[0] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false" aria-label="خيارات {{ $storehouse->name }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if (hasPermission('stock.warehouses.view'))
                                        <a class="dropdown-item" href="{{ route('storehouse.show', $storehouse->id) }}">
                                            <i class="feather icon-eye"></i> عرض
                                        </a>
                                    @endif

                                    @if (hasPermission('stock.warehouses.edit'))
                                        <a class="dropdown-item" href="{{ route('storehouse.edit', $storehouse->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endif

                                    @if ($storehouse->status === 'active')
                                        <div class="dropdown-divider"></div>
                                        @if (hasPermission('stock.reports.summary_movement'))
                                            <a class="dropdown-item" href="{{ route('storehouse.summary_inventory_operations', $storehouse->id) }}">
                                                <i class="feather icon-activity"></i> ملخّص العمليات
                                            </a>
                                        @endif
                                        @if (hasPermission('stock.reports.value'))
                                            <a class="dropdown-item" href="{{ route('storehouse.inventory_value', $storehouse->id) }}">
                                                <i class="feather icon-dollar-sign"></i> قيمة المخزون
                                            </a>
                                        @endif
                                        @if (hasPermission('stock.reports.inventory_sheet'))
                                            <a class="dropdown-item" href="{{ route('storehouse.inventory_sheet', $storehouse->id) }}">
                                                <i class="feather icon-clipboard"></i> ورقة الجرد
                                            </a>
                                        @endif
                                    @endif

                                    @if (! $isMain && hasPermission('stock.warehouses.delete'))
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-storehouse" href="#"
                                           data-id="{{ $storehouse->id }}" data-name="{{ $storehouse->name }}">
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

    @include('stock::storehouse.partials.pagination', ['storehouses' => $storehouses])
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-home"></i></div>
            <h5 class="stk-empty__title">لا توجد مستودعات</h5>
            <p class="stk-empty__hint">لا شيء يطابق معايير البحث، أو لم يُنشأ أي مستودع بعد</p>
            @if (hasPermission('stock.warehouses.create'))
                <a href="{{ route('storehouse.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة مستودع
                </a>
            @endif
        </div>
    </div>
@endif
