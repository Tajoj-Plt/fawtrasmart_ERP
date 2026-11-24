@extends('stock::master')

@section('title', 'إذن مخزني ' . $permit->number)

@section('content')
    @php
        $items = $permit->items ?? collect();

        $states = [
            'pending' => ['قيد الانتظار', 'warning'],
            'approved' => ['موافق عليه', 'success'],
            'apporod' => ['موافق عليه', 'success'],
            'rejected' => ['مرفوض', 'danger'],
            'rejec' => ['مرفوض', 'danger'],
            'processing' => ['قيد المعالجة', 'info'],
        ];
        [$stateLabel, $stateTone] = $states[$permit->status] ?? [$permit->status, 'secondary'];

        $types = [1 => 'إذن إضافة', 2 => 'إذن صرف', 3 => 'تحويل مخزني', 4 => 'رصيد افتتاحي'];
        /* أذونات الفواتير والمرتجعات لا تحمل `permission_type` — نوعُها في
           مصدر الإذن (فاتورة مشتريات، مرتجع…) فيُقرأ منه بدل «غير محدد». */
        $typeLabel = $types[$permit->permission_type]
            ?? (\App\Constants\permissions\PermissionSources::getAll()[$permit->permission_source_id] ?? 'غير محدد');

        /* إجمالي السطر المخزَّن لا `كمية × سعر`: سعر الوحدة الأساسية عمودٌ
           بخانتين عشريتين، فسعرُ ٠٫١٩١٣ يُخزَّن ٠٫١٩ وحاصل ضربه في ٣٠٬٠٠٠
           حبّة ٥٬٧٠٠ بدل ٥٬٧٣٩ — والمخزَّن هو ما دخل القيد. */
        $lineTotal = fn ($item) => (float) ($item->total ?? 0) > 0
            ? (float) $item->total
            : (float) ($item->quantity ?? 0) * (float) ($item->unit_price ?? 0);

        $isPending = $permit->status === 'pending';
        $itemsTotal = $items->sum($lineTotal);
    @endphp

    <div class="content-body">

        @section('page-actions')
            {{--
                كانت الأزرار هنا بلا حراسة صلاحيات: يراها من لا يملكها ثم
                يُردّ بـ403 عند النقر. وكان «الرفض» غائباً عن هذه الشاشة
                رأساً — يُرفض الإذن من الفهرس وحده — و«إلغاء الموافقة» كذلك،
                فإذنٌ معتمَد لا يعرض إجراءً واحداً.
            --}}
            @if ($isPending)
                @can('stock.permits.approve')
                    <button type="button" class="btn btn-primary" onclick="approvePermit()">
                        <i class="feather icon-check-circle"></i> اعتماد
                    </button>
                @endcan

                @can('stock.permits.reject')
                    <button type="button" class="btn btn-outline-warning" onclick="openRejectModal()">
                        <i class="feather icon-x-circle"></i> رفض
                    </button>
                @endcan

                @can('stock.permits.edit')
                    <a href="{{ route('store_permits_management.edit', $permit->id) }}" class="btn btn-outline-primary">
                        <i class="feather icon-edit-2"></i> تعديل
                    </a>
                @endcan
            @endif

            @if (in_array($permit->status, ['approved', 'apporod'], true))
                @can('stock.permits.cancel_approval')
                    <button type="button" class="btn btn-outline-warning" onclick="cancelApproval()">
                        <i class="feather icon-rotate-ccw"></i> إلغاء الاعتماد
                    </button>
                @endcan
            @endif

            @if (canAny(['stock.permits.print', 'stock.permits.export']))
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="feather icon-printer"></i> طباعة
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    @can('stock.permits.print')
                    <a class="dropdown-item" target="_blank"
                       href="{{ route('store_permits_management.print', ['id' => $permit->id, 'type' => 'with-prices']) }}">
                        <i class="feather icon-file-text"></i> إذن مخزني بالأسعار
                    </a>
                    <a class="dropdown-item" target="_blank"
                       href="{{ route('store_permits_management.print', ['id' => $permit->id, 'type' => 'without-prices']) }}">
                        <i class="feather icon-file"></i> إذن مخزني بلا أسعار
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="javascript:void(0)" id="printReport">
                        <i class="feather icon-printer"></i> طباعة البنود
                    </a>
                    @endcan
                    @can('stock.permits.export')
                    <a class="dropdown-item" href="javascript:void(0)" id="exportExcel">
                        <i class="feather icon-grid"></i> تصدير Excel
                    </a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="exportPermit()">
                        <i class="feather icon-server"></i> تصدير من الخادم
                    </a>
                    @endcan
                </div>
            </div>
            @endif

            @can('stock.permits.delete')
            <button type="button" class="btn btn-outline-danger" onclick="deletePermit()">
                <i class="feather icon-trash-2"></i> حذف
            </button>
            @endcan
        @endsection

        @include('stock::components.page-head', [
            'title' => 'إذن مخزني ' . $permit->number,
            'icon' => 'file-text',
            'sub' => $typeLabel . ' — ' . (optional($permit->storeHouse)->name ?? 'مستودع غير محدد'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => route('store_permits_management.index'),
                $permit->number => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ الخلاصة ══════════ --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value">
                    <span class="badge badge-light-{{ $stateTone }}">{{ $stateLabel }}</span>
                </span>
                <span class="stk-metric__label">الحالة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__value">{{ $typeLabel }}</span>
                <span class="stk-metric__label">نوع الإذن</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-home"></i></span>
                <span class="stk-metric__value">{{ optional($permit->storeHouse)->name ?? '—' }}</span>
                <span class="stk-metric__label">المستودع</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__value stk-num">
                    {{ $permit->permission_date
                        ? \Illuminate\Support\Carbon::parse($permit->permission_date)->format('Y-m-d')
                        : optional($permit->created_at)->format('Y-m-d') }}
                </span>
                <span class="stk-metric__label">تاريخ الإذن</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num">{{ $items->count() }}</span>
                <span class="stk-metric__label">عدد الأصناف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num">@money($permit->grand_total)</span>
                <span class="stk-metric__label">القيمة الإجمالية</span>
            </div>
        </div>

        {{-- ══════════ التبويبات ══════════ --}}
        <div class="card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#items" role="tab">
                            <i class="feather icon-list"></i> الأصناف
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#preview" role="tab">
                            <i class="feather icon-eye"></i> معاينة الطباعة
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#activity" role="tab">
                            <i class="feather icon-activity"></i> سجلّ النشاط
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content p-2">

                {{-- ═════ الأصناف ═════ --}}
                <div class="tab-pane active" id="items" role="tabpanel">
                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-package"></i> أصناف الإذن
                                <span class="stk-count">{{ $items->count() }}</span>
                            </h6>
                            <span class="stk-balance stk-num">@money($itemsTotal)</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="reportTable">
                                <thead>
                                    <tr>
                                        <th style="width:44px">#</th>
                                        <th class="text-center" style="width:12%">كود الصنف</th>
                                        <th>الصنف</th>
                                        <th class="text-center" style="width:10%">الكمية</th>
                                        <th class="text-center" style="width:10%">الوحدة</th>
                                        <th class="text-center" style="width:13%">سعر الوحدة</th>
                                        <th class="text-center" style="width:13%">الإجمالي</th>
                                        <th class="text-center" style="width:12%">تاريخ الانتهاء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $item)
                                        <tr>
                                            <td class="stk-num text-muted">{{ $loop->iteration }}</td>
                                            <td class="text-center stk-num">
                                                {{ optional($item->product)->serial_number ?? optional($item->product)->code ?? '—' }}
                                            </td>
                                            <td>
                                                <span class="stk-media__title">
                                                    {{ optional($item->product)->name ?? $item->item ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="text-center stk-num">{{ number_format($item->quantity ?? 0, 2) }}</td>
                                            {{-- الكمية بالوحدة الأساسية دائماً — فوحدتها هي الافتراضية للصنف --}}
                                            <td class="text-center">{{ $item->product?->defaultUnit?->categoryUnit?->name ?: '—' }}</td>
                                            <td class="text-center stk-num">
                                                {{-- سعر الوحدة من الإجمالي ÷ الكمية بأربع خانات — العمود المخزَّن بخانتين يُظهر ٠٫١٩ لسعر ٠٫١٩١٣ --}}
                                                @if ((float) $item->quantity > 0 && (float) $item->total > 0)
                                                    {{ number_format((float) $item->total / (float) $item->quantity, 4) }}
                                                @else
                                                    @money($item->unit_price)
                                                @endif
                                            </td>
                                            <td class="text-center stk-num">@money($lineTotal($item))</td>
                                            <td class="text-center stk-num">{{ $item->expiry_date ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8">
                                                <div class="stk-empty">
                                                    <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                                                    <h5 class="stk-empty__title">لا أصناف</h5>
                                                    <p class="stk-empty__hint">لا بنود في هذا الإذن</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                @if ($items->count())
                                    <tfoot>
                                        <tr class="stk-total-row">
                                            <td colspan="6">الإجمالي</td>
                                            <td class="text-center stk-num">@money($itemsTotal)</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ═════ معاينة الطباعة ═════
                     كانت المعاينة داخل صندوق بخلفية `lightslategray` وارتفاع
                     شاشةٍ كاملة وتصغيرٍ بـ`transform: scale(.8)` — تُقرأ رمادية
                     مائلة. صارت ورقةً بيضاء على خلفية الثيم بمقاسها الطبيعي. --}}
                <div class="tab-pane" id="preview" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-eye"></i> معاينة قالب الطباعة</h4>
                            @can('stock.permits.print')
                            <a class="btn btn-sm btn-outline-primary" target="_blank"
                               href="{{ route('store_permits_management.print', ['id' => $permit->id, 'type' => 'with-prices']) }}">
                                <i class="feather icon-printer"></i> فتح للطباعة
                            </a>
                            @endcan
                        </div>
                        <div class="card-body">
                            <div class="stk-paper" id="print-section">
                                @include('stock::store_permits_management.partials.pdf', ['permit' => $permit])
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═════ سجلّ النشاط ═════ --}}
                <div class="tab-pane" id="activity" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            @if (isset($logs) && count($logs) > 0)
                                <ul class="stk-timeline">
                                    @foreach ($logs as $date => $dayLogs)
                                        @php $day = \Illuminate\Support\Carbon::parse($date); @endphp

                                        <li class="stk-timeline__day">
                                            <span>{{ $day->translatedFormat('l، d F Y') }}</span>
                                        </li>

                                        @foreach ($dayLogs as $log)
                                            @continue (! $log)

                                            <li class="stk-timeline__item">
                                                <span class="stk-timeline__dot stk-timeline__dot--log">
                                                    <i class="feather icon-edit-3"></i>
                                                </span>

                                                <div class="stk-timeline__body">
                                                    <p class="stk-timeline__head">
                                                        <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                        <span class="stk-timeline__time stk-num">
                                                            <i class="feather icon-clock"></i>
                                                            {{ $log->created_at->format('H:i:s') }}
                                                        </span>
                                                    </p>

                                                    <div class="stk-timeline__text">
                                                        {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                                                    </div>

                                                    <p class="stk-timeline__meta">
                                                        <span>
                                                            <i class="feather icon-map-pin"></i>
                                                            {{ $log->user->branch->name ?? 'فرع غير معروف' }}
                                                        </span>
                                                    </p>
                                                </div>
                                            </li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            @else
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                                    <h5 class="stk-empty__title">لا نشاط مسجَّل</h5>
                                    <p class="stk-empty__hint">تظهر هنا كل عملية على هذا الإذن</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="deleteForm" method="POST" class="d-none"
          action="{{ route('store_permits_management.delete', $permit->id) }}">
        @csrf
        @method('DELETE')
    </form>

    {{--
        **سبب الـ405.**

        كان هذا النموذج يحمل `@method('PATCH')` — أي يُزوّر الطريقة إلى
        PATCH — والمسار مسجَّل `Route::post('/{id}/approve', …)`. فيبحث
        الموجِّه عن مسارٍ يقبل PATCH على هذا العنوان ولا يجد، فيردّ
        **405 Method Not Allowed**. لا خطأ في الشيفرة ولا في الصلاحية: فعلٌ
        واحد لا يوافق فعلاً.

        (نموذج الحذف أسفله يحمل `@method('DELETE')` وهو صحيح، لأن مساره
        مسجَّل `Route::delete` — ولعلّ ذلك ما أوحى بتزوير الفعل هنا أيضاً.)
    --}}
    <form id="approveForm" method="POST" class="d-none"
          action="{{ route('store_permits_management.approve', $permit->id) }}">
        @csrf
    </form>

    <form id="cancelApprovalForm" method="POST" class="d-none"
          action="{{ route('store_permits_management.cancel_approval', $permit->id) }}">
        @csrf
    </form>

    {{-- نافذة الرفض: السبب إلزامي، كما في شاشة الفهرس --}}
    @can('stock.permits.reject')
        <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('store_permits_management.reject', $permit->id) }}">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header">
                            <h5 class="modal-title">رفض الإذن <span class="stk-num">#{{ $permit->number }}</span></h5>
                            <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <div class="form-group">
                                <label for="rejection_reason" class="stk-req">سبب الرفض</label>
                                <textarea id="rejection_reason" name="rejection_reason" class="form-control"
                                          rows="4" required placeholder="اكتب سبب رفض الإذن…"></textarea>
                            </div>

                            <div class="alert alert-warning mb-0">
                                <i class="feather icon-alert-triangle"></i>
                                سيُرفض الإذن نهائياً ولا يمكن التراجع عن هذا الإجراء.
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                                إلغاء
                            </button>
                            <button type="submit" class="btn btn-warning">
                                <i class="feather icon-x-circle"></i> تأكيد الرفض
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            stkReportTools({
                table: '#reportTable',
                fileName: 'إذن_مخزني_' + @json($permit->number),
                title: 'إذن مخزني رقم ' + @json($permit->number),
                subTitle: @json(optional($permit->storeHouse)->name ?? ''),
                numericColumns: [0, 3, 5, 6],
                info: {
                    'رقم الإذن': @json($permit->number),
                    'المستودع': @json(optional($permit->storeHouse)->name ?? '—'),
                    'الحالة': @json($permit->status),
                },
                cards: [
                    { title: 'عدد البنود', value: @json((string) count($items)), unit: ' بند' },
                    { title: 'الإجمالي', value: @json(number_format($itemsTotal, 2)), color: 'success', borderColor: 'success' },
                ],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 2,
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function approvePermit() {
            Swal.fire({
                title: 'اعتماد الإذن؟',
                text: 'ستُرحَّل الكميات إلى المخزون وتُسجَّل القيود المحاسبية.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، اعتمد',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) document.getElementById('approveForm').submit();
            });
        }

        function openRejectModal() {
            var modal = document.getElementById('rejectModal');

            if (! modal) {
                return;
            }

            // بوتستراب 5 هو المحمَّل؛ ويبقى نداء jQuery احتياطاً للصفحات القديمة
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            } else if (window.jQuery && jQuery.fn.modal) {
                jQuery(modal).modal('show');
            }
        }

        function cancelApproval() {
            Swal.fire({
                title: 'إلغاء اعتماد الإذن؟',
                text: 'ستُرجَع الكميات وتُعكَس القيود المحاسبية.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، ألغِ الاعتماد',
                cancelButtonText: 'تراجع',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) document.getElementById('cancelApprovalForm').submit();
            });
        }

        function deletePermit() {
            Swal.fire({
                title: 'حذف الإذن؟',
                text: 'لا يمكن التراجع عن هذا الإجراء.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) document.getElementById('deleteForm').submit();
            });
        }

        function exportPermit() {
            window.location.href = '{{ route('store_permits_management.export') }}?id={{ $permit->id }}';
        }
    </script>
@endsection
