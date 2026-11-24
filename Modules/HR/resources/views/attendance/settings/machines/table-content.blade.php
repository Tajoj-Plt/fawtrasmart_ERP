{{--
    محتوى بطاقة جدول الماكينات — يُعرض داخل #tableContainer في الصفحة،
    ويُعاد نفسه من machines.search عند البحث بـ AJAX.
--}}
@php
    // روابط الصفحات تشير إلى شاشة القائمة لا إلى نقطة البحث (التي تُعيد JSON)
    $machines->withPath(route('machines.index'))
        ->appends(collect(request()->only(['name', 'status', 'machine_type']))->filter(fn ($v) => $v !== null && $v !== '')->all());
    $machineFilters = collect(request()->only(['name', 'status', 'machine_type']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $canShowMachine = hasPermission('attendance_devices.show');
    $canEditMachine = hasPermission('attendance_devices.edit');
    $canDestroyMachine = hasPermission('attendance_devices.destroy');
@endphp

@if ($machines->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> قائمة الماكينات
            <span class="stk-count">{{ $machines->total() }}</span>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="machines-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>الماكينة</th>
                    <th>النوع</th>
                    <th>المضيف</th>
                    <th class="text-center">المنفذ</th>
                    <th class="text-center">الحالة</th>
                    <th>تاريخ الإنشاء</th>
                    <th class="text-center" style="width:70px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($machines as $index => $machine)
                    <tr id="machine-{{ $machine->id }}">
                        <td class="stk-num text-muted">{{ $machines->firstItem() + $index }}</td>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-monitor"></i></span>
                                <span class="stk-media__body">
                                    @if ($canShowMachine)
                                        <a href="{{ route('machines.show', $machine->id) }}" class="stk-media__title">{{ $machine->name }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $machine->name }}</span>
                                    @endif
                                    <span class="stk-media__sub">{{ $machine->serial_number ?: '#'.$machine->id }}</span>
                                </span>
                            </div>
                        </td>
                        <td>{{ ucfirst($machine->machine_type) }}</td>
                        <td><code class="stk-num">{{ $machine->host_name }}</code></td>
                        <td class="text-center stk-num">{{ $machine->port_number }}</td>
                        <td class="text-center">
                            <div class="d-inline-flex align-items-center gap-2">
                                <div class="form-check form-switch mb-0" title="تفعيل / إيقاف">
                                    <input class="form-check-input status-toggle"
                                           type="checkbox"
                                           data-machine-id="{{ $machine->id }}"
                                           {{ $machine->status ? 'checked' : '' }}
                                           onchange="toggleStatus({{ $machine->id }})"
                                           aria-label="تفعيل الماكينة">
                                </div>
                                <span class="stk-state stk-state--{{ $machine->status ? 'on' : 'off' }}">{{ $machine->status ? 'نشط' : 'غير نشط' }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="stk-num" title="{{ $machine->created_at?->diffForHumans() }}">{{ $machine->created_at?->format('Y-m-d') ?? '—' }}</span>
                        </td>
                        <td class="text-center">
                            @if ($canShowMachine || $canEditMachine || $canDestroyMachine)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="dropdown" data-bs-display="static"
                                        aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if ($canShowMachine)
                                            <a class="dropdown-item" href="{{ route('machines.show', $machine->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                        @endif
                                        @if ($canEditMachine)
                                            <a class="dropdown-item" href="{{ route('machines.edit', $machine->id) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                        @endif
                                        @if ($canDestroyMachine)
                                            @if ($canShowMachine || $canEditMachine)
                                                <div class="dropdown-divider"></div>
                                            @endif
                                            {{-- الحذف يُعيد JSON، فيجري عبر deleteMachine() في الصفحة لا عبر نموذجٍ عادي --}}
                                            <a class="dropdown-item text-danger" href="#"
                                               data-name="{{ $machine->name }}"
                                               onclick="event.preventDefault(); deleteMachine({{ $machine->id }}, this.dataset.name);">
                                                <i class="fa fa-trash me-2"></i>حذف
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($machines->hasPages())
        <div class="stk-table-foot">
            <p class="stk-table-foot__count">عرض {{ $machines->firstItem() }}–{{ $machines->lastItem() }} من {{ $machines->total() }}</p>
            {{ $machines->links() }}
        </div>
    @endif
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-monitor"></i></div>
            <h5 class="stk-empty__title">لا توجد ماكينات</h5>
            <p class="stk-empty__hint">
                @if ($machineFilters)
                    لم يطابق البحث أي ماكينة — جرّب معايير أوسع
                @else
                    أضف ماكينة البصمة ليُعرف مصدر كل بصمة ترد منها
                @endif
            </p>
            @if ($machineFilters)
                <a href="{{ route('machines.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
            @elseif (hasPermission('attendance_devices.create'))
                <a href="{{ route('machines.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة ماكينة</a>
            @endif
        </div>
    </div>
@endif
