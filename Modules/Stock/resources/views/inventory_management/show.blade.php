@extends('stock::master')

@section('title', 'ورقة جرد #' . ($adjustment->reference_number ?: $adjustment->id))

@section('content')
    @php
        $statuses = [
            'draft' => ['مسودة', 'secondary'],
            'in_progress' => ['قيد التنفيذ', 'warning'],
            'completed' => ['مكتمل — في انتظار التسوية', 'info'],
            'adjusted' => ['تمت التسوية', 'success'],
            'cancelled' => ['ملغي', 'danger'],
        ];
        [$statusLabel, $statusTone] = $statuses[$adjustment->status] ?? [$adjustment->status, 'secondary'];

        $types = [
            'full' => 'جرد شامل',
            'partial' => 'جرد دوري',
            'spot' => 'جرد مفاجئ',
            'category' => 'جرد حسب الصنف',
        ];

        $reference = $adjustment->reference_number ?: $adjustment->id;
        $items = $adjustment->items;
        $at = $adjustment->inventory_time ? \Illuminate\Support\Carbon::parse($adjustment->inventory_time) : null;

        // إجماليات الفروقات: هي خلاصة الورقة
        $positive = $items->where('quantity_difference', '>', 0)->sum('quantity_difference');
        $negative = abs($items->where('quantity_difference', '<', 0)->sum('quantity_difference'));

        // الحقول المتاحة للتصدير — قائمة واحدة بدل عشر كتل متطابقة
        $exportFields = [
            'reference_number' => 'رقم المرجع',
            'storehouse' => 'المستودع',
            'date' => 'التاريخ',
            'type' => 'نوع الجرد',
            'status' => 'الحالة',
            'items_count' => 'عدد الأصناف',
            'created_by' => 'أُنشئ بواسطة',
            'note' => 'الملاحظات',
            'calculation_type' => 'نوع الحساب',
        ];
    @endphp

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="content-body">

        @php
            /*
                أزرار دورة التسوية تُشتق من الأذون الفعلية لا من الحالة وحدها:
                الورقة تبقى «مكتملة» بعد إصدار الأذون وحتى اعتمادها كلّها،
                فالحالة وحدها لا تفرّق بين «لم تُسوَّ بعد» و«أذونها بانتظار
                الاعتماد» — وكان زرّ «تسوية الآن» يبقى معروضاً فيُصدر النقرُ
                الثاني أذوناً مكرّرة لنفس الفروق.
            */
            $activeSettlementPermits = $warehousePermits->whereNotIn('status', ['rejected', 'rejec']);
            $approvedSettlementPermits = $activeSettlementPermits->whereIn('status', ['approved', 'apporod']);
            $settlementLocked = $adjustment->status === 'adjusted' || $approvedSettlementPermits->isNotEmpty();
        @endphp

        @section('page-actions')
            @if ($adjustment->status === 'completed' && $activeSettlementPermits->isEmpty() && hasPermission('stock.adjustments.approve'))
                <button type="button" class="btn btn-primary" onclick="adjustInventory()">
                    <i class="feather icon-check-circle"></i> تسوية الآن
                </button>
            @elseif ($adjustment->status === 'completed' && !$settlementLocked && $activeSettlementPermits->isNotEmpty() && hasPermission('stock.adjustments.cancel'))
                {{-- أذون معلَّقة لم يُعتمد منها شيء: يمكن سحبها وإعادة التسوية --}}
                <button type="button" class="btn btn-outline-danger" onclick="cancelAdjustment()">
                    <i class="feather icon-rotate-ccw"></i> سحب أذون التسوية المعلَّقة
                </button>
            @endif
            {{-- بعد اعتماد إذنٍ واحد لا إلغاء ولا تعديل ولا حذف: التسوية
                 ارتبطت بإذنٍ معتمد وقيدٍ مرحَّل. التراجع من شاشة الأذونات
                 («إلغاء الموافقة») وهو ما يعيد الورقة «مكتملة» تلقائياً --}}

            @if (in_array($adjustment->status, ['draft', 'in_progress'], true) && hasPermission('stock.adjustments.edit'))
            <a href="{{ route('inventory.edit', $adjustment->id) }}" class="btn btn-outline-primary">
                <i class="feather icon-edit-2"></i> تعديل
            </a>
            @endif

            <button type="button" class="btn btn-outline-secondary" id="printReport">
                <i class="feather icon-printer"></i> طباعة
            </button>

            @if (hasPermission('stock.adjustments.export'))
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="feather icon-download"></i> تصدير
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="javascript:void(0)" id="exportExcel">
                        <i class="feather icon-grid"></i> Excel (المعروض)
                    </a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="showExportModal()">
                        <i class="feather icon-sliders"></i> تصدير بأعمدة مختارة
                    </a>
                </div>
            </div>
            @endif

            @if ($adjustment->status === 'draft' && hasPermission('stock.adjustments.delete'))
            {{-- الحذف للمسودّة وحدها — كما يفرضه المتحكّم أصلاً --}}
            <button type="button" class="btn btn-outline-danger" onclick="deleteInventory()">
                <i class="feather icon-trash-2"></i> حذف
            </button>
            @endif
        @endsection

        @include('stock::components.page-head', [
            'title' => 'ورقة جرد #' . $reference,
            'icon' => 'clipboard',
            'sub' => (optional($adjustment->storeHouse)->name ?? 'مستودع غير محدد')
                . ' — ' . ($types[$adjustment->type] ?? $adjustment->type),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إدارة الجرد' => route('inventory_management.index'),
                '#' . $reference => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')

        {{--
            لافتة الأذون المعلَّقة.

            التسوية تُصدر أذوناً مخزنية ولا تُحرّك شيئاً بنفسها: الكمّيات
            والقيد موقوفان على اعتمادها. وكان المستخدم يقرأ «تمت التسوية
            بنجاح» ثم يجد المخزون على حاله ولا شيء يخبره لماذا.
        --}}
        @php
            $pendingPermits = collect($warehousePermits ?? [])
                ->reject(fn ($p) => in_array($p->status, ['approved', 'apporod', 'rejected', 'rejec'], true));
        @endphp

        @if ($pendingPermits->isNotEmpty())
            <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <i class="feather icon-clock"></i>
                    <strong>{{ $pendingPermits->count() }} إذناً مخزنياً بانتظار الاعتماد.</strong>
                    لن تتحرّك كمّيات المستودع ولن يُرحَّل قيد الجرد قبل اعتمادها.
                </div>
                @can('stock.permits.approve')
                    <a href="{{ route('store_permits_management.index') }}" class="btn btn-sm btn-warning">
                        <i class="feather icon-check-square"></i> الانتقال إلى الأذون المخزنية
                    </a>
                @endcan
            </div>
        @endif

        {{-- ══════════ خلاصة الورقة ══════════ --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value">
                    <span class="badge badge-light-{{ $statusTone }}">{{ $statusLabel }}</span>
                </span>
                <span class="stk-metric__label">الحالة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__value stk-num">{{ $at ? $at->format('Y-m-d') : '—' }}</span>
                <span class="stk-metric__label">{{ $at ? $at->format('H:i') : 'تاريخ الجرد' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-sliders"></i></span>
                <span class="stk-metric__value">
                    {{ $adjustment->calculation_type === 'dated' ? 'حسب التاريخ' : 'غير مرتبط' }}
                </span>
                <span class="stk-metric__label">نوع الحساب</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num">{{ $items->count() }}</span>
                <span class="stk-metric__label">عدد الأصناف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__value stk-num text-success">{{ number_format($positive, 2) }}</span>
                <span class="stk-metric__label">فروقات موجبة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__value stk-num text-danger">{{ number_format($negative, 2) }}</span>
                <span class="stk-metric__label">فروقات سالبة</span>
            </div>
        </div>

        {{-- ══════════ التبويبات ══════════ --}}
        <div class="card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#details" role="tab">
                            <i class="feather icon-list"></i> الأصناف
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#warehouse-permits" role="tab">
                            <i class="feather icon-file-text"></i> الأذون المخزنية
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#notes" role="tab">
                            <i class="feather icon-edit-3"></i> الملاحظات
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content p-2">

                {{-- ═════ الأصناف ═════ --}}
                <div class="tab-pane active" id="details" role="tabpanel">
                    <div class="card stk-filters">
                        <div class="card-body">
                            <div class="stk-filters__head">
                                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> تصفية الأصناف</h6>
                            </div>

                            <div class="row">
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label for="product-search">البحث</label>
                                        <input type="text" id="product-search" class="form-control" placeholder="اسم الصنف…">
                                    </div>
                                </div>

                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label for="category-filter">التصنيف</label>
                                        <select id="category-filter" class="form-control">
                                            <option value="">جميع التصنيفات</option>
                                            @foreach ($items->pluck('product.category')->unique('id')->filter() as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label for="difference-filter">الفارق</label>
                                        <select id="difference-filter" class="form-control">
                                            <option value="">جميع الفروقات</option>
                                            <option value="positive">زيادة</option>
                                            <option value="negative">نقص</option>
                                            <option value="zero">بلا فارق</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-list"></i> أصناف الجرد
                                <span class="stk-count">{{ $items->count() }}</span>
                            </h6>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="products-table">
                                <thead>
                                    <tr>
                                        <th style="width:44px">#</th>
                                        <th class="text-center" style="width:11%">الكود</th>
                                        <th>الصنف</th>
                                        <th style="width:13%">التصنيف</th>
                                        <th class="text-center" style="width:10%">سعر الوحدة</th>
                                        <th class="text-center" style="width:10%">فعلي</th>
                                        <th class="text-center" style="width:10%">بالنظام</th>
                                        <th class="text-center" style="width:10%">الفارق</th>
                                        <th class="text-center" style="width:8%">صورة</th>
                                        <th style="width:14%">ملاحظة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $item)
                                        @php
                                            $difference = $item->quantity_difference ?? 0;
                                            $tone = $difference > 0 ? 'success' : ($difference < 0 ? 'danger' : 'muted');
                                            $bucket = $difference > 0 ? 'positive' : ($difference < 0 ? 'negative' : 'zero');
                                        @endphp

                                        <tr class="product-row"
                                            data-name="{{ Str::lower(optional($item->product)->name ?? '') }}"
                                            data-category="{{ optional($item->product)->category_id }}"
                                            data-difference="{{ $bucket }}">

                                            <td class="stk-num text-muted">{{ $loop->iteration }}</td>
                                            <td class="text-center stk-num">{{ optional($item->product)->serial_number ?: '—' }}</td>
                                            <td>
                                                <span class="stk-media__title">
                                                    {{ optional($item->product)->name ?? 'منتج محذوف' }}
                                                </span>
                                            </td>
                                            <td>{{ optional(optional($item->product)->category)->name ?? '—' }}</td>
                                            <td class="text-center stk-num">{{ number_format($item->unit_price ?? 0, 2) }}</td>
                                            <td class="text-center stk-num">{{ $item->quantity_in_stock ?? 0 }}</td>
                                            <td class="text-center stk-num">{{ $item->quantity_in_system ?? 0 }}</td>
                                            <td class="text-center">
                                                <span class="stk-move stk-move--{{ $tone }}">
                                                    <span class="stk-num">{{ $difference > 0 ? '+' . $difference : $difference }}</span>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @if ($item->image)
                                                    {{-- الصورة تُفتح في تبويب جديد: نافذة لكل صفّ كانت تحشر
                                                         عشرات النوافذ في الصفحة --}}
                                                    <a href="{{ asset($item->image) }}" target="_blank" rel="noopener">
                                                        <img src="{{ asset($item->image) }}" alt="صورة الصنف" class="stk-thumb">
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="stk-media__sub">{{ $item->note ?: '—' }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10">
                                                <div class="stk-empty">
                                                    <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                                                    <h5 class="stk-empty__title">لا أصناف</h5>
                                                    <p class="stk-empty__hint">لم تُدخَل أصناف في هذه الورقة بعد</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="stk-table-foot" id="filter-empty" hidden>
                            <p class="stk-table-foot__count">لا صنف يطابق التصفية الحالية</p>
                        </div>
                    </div>
                </div>

                {{-- ═════ الأذون المخزنية ═════ --}}
                <div class="tab-pane" id="warehouse-permits" role="tabpanel">
                    <div class="card stk-table-card">
                        @if (isset($warehousePermits) && count($warehousePermits) > 0)
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-file-text"></i> الأذون
                                    <span class="stk-count">{{ count($warehousePermits) }}</span>
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:14%">رقم الإذن</th>
                                            <th style="width:18%">المستودع</th>
                                            <th style="width:14%">التاريخ</th>
                                            <th>الوصف</th>
                                            <th class="text-center" style="width:12%">الحالة</th>
                                            <th class="text-center" style="width:10%">عرض</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($warehousePermits as $permit)
                                            @php
                                                $permitStates = [
                                                    'draft' => ['مسودة', 'off'],
                                                    'confirmed' => ['مؤكد', 'on'],
                                                ];
                                                [$permitLabel, $permitTone] = $permitStates[$permit->status] ?? [$permit->status, 'off'];
                                            @endphp

                                            <tr>
                                                <td class="stk-num">{{ $permit->number }}</td>
                                                <td>{{ optional($permit->storeHouse)->name ?? '—' }}</td>
                                                <td class="stk-num">
                                                    {{ $permit->permission_date ? \Illuminate\Support\Carbon::parse($permit->permission_date)->format('Y-m-d') : '—' }}
                                                </td>
                                                <td><span class="stk-media__sub">{{ $permit->description ?: '—' }}</span></td>
                                                <td class="text-center">
                                                    <span class="stk-state stk-state--{{ $permitTone }}">{{ $permitLabel }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('store_permits_management.show', $permit->id) }}"
                                                       class="btn btn-sm btn-outline-secondary" aria-label="عرض الإذن">
                                                        <i class="feather icon-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-file-text"></i></div>
                                    <h5 class="stk-empty__title">لا أذون مخزنية</h5>
                                    <p class="stk-empty__hint">تُنشأ الأذون عند تسوية الجرد</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ═════ الملاحظات ═════ --}}
                <div class="tab-pane" id="notes" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-edit-3"></i> ملاحظات الجرد</h4>
                        </div>
                        <div class="card-body">
                            @if ($adjustment->note)
                                <p class="mb-0" style="white-space:pre-line">{{ $adjustment->note }}</p>
                            @else
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-edit-3"></i></div>
                                    <h5 class="stk-empty__title">لا ملاحظات</h5>
                                    <p class="stk-empty__hint">لم تُسجَّل ملاحظات على هذه الورقة</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ نافذة التصدير ══════════ --}}
    <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">تصدير ورقة الجرد</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <form id="exportForm">
                        <div class="form-group">
                            <label>الحقول المُصدَّرة</label>
                            <div class="stk-picks">
                                @foreach ($exportFields as $value => $label)
                                    <label class="stk-pick">
                                        <input type="checkbox" name="fields[]" value="{{ $value }}" checked>
                                        <span class="stk-pick__box"><i class="feather icon-check"></i></span>
                                        <span class="stk-pick__label">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label>الصيغة</label>
                            <div class="stk-picks">
                                @foreach (['excel' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'] as $value => $label)
                                    <label class="stk-pick">
                                        <input type="radio" name="format" value="{{ $value }}" {{ $loop->first ? 'checked' : '' }}>
                                        <span class="stk-pick__box"><i class="feather icon-check"></i></span>
                                        <span class="stk-pick__label">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <button type="button" class="btn btn-primary" onclick="exportInventory()">
                        <i class="feather icon-download"></i> تصدير
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            stkReportTools({
                table: '#products-table',
                fileName: 'ورقة_جرد_' + @json($adjustment->id),
                title: 'ورقة الجرد رقم ' + @json((string) $adjustment->id),
                subTitle: @json(optional($adjustment->storeHouse ?? null)->name ?? config('app.name')),
                numericColumns: [0, 4, 5, 6, 7],
                cards: [
                    { title: 'عدد الأصناف', value: @json((string) $items->count()), unit: ' صنف' },
                ],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 2,
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function showExportModal() {
            $('#exportModal').modal('show');
        }

        function deleteInventory() {
            Swal.fire({
                title: 'حذف ورقة الجرد؟',
                text: 'لا يمكن التراجع عن هذا الإجراء.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
            }).then(result => {
                if (! result.isConfirmed) return;

                $.get('{{ route('inventory.delete', $adjustment->id) }}')
                    .done(function (response) {
                        if (response && response.success === false) {
                            Swal.fire({ icon: 'error', title: 'تعذّر الحذف', text: response.message });
                            return;
                        }
                        window.location.href = '{{ route('inventory_management.index') }}';
                    })
                    .fail(() => Swal.fire({ icon: 'error', title: 'تعذّر الحذف' }));
            });
        }

        function adjustInventory() {
            Swal.fire({
                title: 'تسوية الجرد الآن؟',
                text: 'ستُرحَّل الفروقات إلى المخزون وتُنشأ الأذون والقيود. لا يمكن التراجع.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، سوِّ',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) {
                    window.location.href = '{{ route('inventory.adjustment', $adjustment->id) }}';
                }
            });
        }

        function cancelAdjustment() {
            Swal.fire({
                title: 'سحب أذون التسوية المعلَّقة؟',
                text: 'تُحذف الأذون التي لم تُعتمد بعد — لم تتحرّك كمّيات ولم تُرحَّل قيود، ويمكن إعادة التسوية بعدها.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، اسحب',
                cancelButtonText: 'تراجع',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) {
                    window.location.href = '{{ route('inventory.Canceladjustment', $adjustment->id) }}';
                }
            });
        }

        function exportInventory() {
            const format = $('input[name="format"]:checked').val();
            const body = new FormData();

            body.append('format', format);
            $('input[name="fields[]"]:checked').each(function () {
                body.append('fields[]', this.value);
            });

            $('#exportModal').modal('hide');

            $.ajax({
                url: '{{ route('inventory.export', $adjustment->id) }}',
                type: 'POST',
                data: body,
                processData: false,
                contentType: false,
                xhrFields: { responseType: 'blob' },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            })
                .done(function (data, status, xhr) {
                    const disposition = xhr.getResponseHeader('Content-Disposition') || '';
                    const match = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                    const fallback = 'inventory-{{ $reference }}.'
                        + (format === 'csv' ? 'csv' : (format === 'pdf' ? 'pdf' : 'xlsx'));

                    const filename = (match && match[1]) ? match[1].replace(/['"]/g, '') : fallback;
                    const link = document.createElement('a');

                    link.href = window.URL.createObjectURL(
                        new Blob([data], { type: xhr.getResponseHeader('Content-Type') })
                    );
                    link.download = filename;
                    link.click();
                    window.URL.revokeObjectURL(link.href);
                })
                .fail(() => Swal.fire({ icon: 'error', title: 'تعذّر التصدير' }));
        }

        $(function () {
            /* تصفية الأصناف في المتصفّح: الورقة محمَّلة كاملةً أصلاً */
            function applyFilters() {
                const name = ($('#product-search').val() || '').toLowerCase();
                const category = $('#category-filter').val();
                const difference = $('#difference-filter').val();

                let visible = 0;

                $('.product-row').each(function () {
                    const row = $(this);
                    const matches =
                        (! name || String(row.data('name')).indexOf(name) !== -1)
                        && (! category || String(row.data('category')) === String(category))
                        && (! difference || row.data('difference') === difference);

                    row.toggle(matches);
                    if (matches) visible++;
                });

                document.getElementById('filter-empty').hidden = visible > 0 || ! $('.product-row').length;
            }

            $('#product-search').on('input', applyFilters);
            $('#category-filter, #difference-filter').on('change', applyFilters);
        });
    </script>
@endsection
