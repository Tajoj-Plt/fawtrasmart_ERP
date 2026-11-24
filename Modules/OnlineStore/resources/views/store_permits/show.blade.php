@extends('onlinestore::master')

@section('title', 'عرض إذن المخزن')

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/accept.css') }}">
    <style>
        .custom-btn {
            min-width: 120px;
            margin: 5px;
            justify-content: center;
        }

        .custom-dropdown {
            min-width: 200px;
        }

        .tab-content {
            position: relative;
            z-index: 1;
        }

        .tab-content>.active {
            overflow: visible !important;
        }

        .custom-dropdown .dropdown-item {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .custom-btn i {
            margin-right: 5px;
        }

        .toast-container {
            z-index: 9999;
        }

        .actions-card .actions-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .actions-card .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
        }

        .actions-card .dropdown {
            display: inline-block;
        }

        .actions-card .dropdown-toggle::after {
            display: none;
        }

        .actions-card .btn i {
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .btn {
                width: 100%;
                margin-bottom: 10px;
                justify-content: center;
            }
        }
    </style>
@endsection

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('stock.permits.print'))
                @include('onlinestore::partials.print-menu', [
                    'templates' => $documentTemplates ?? collect(),
                    'default' => $defaultTemplate ?? null,
                    'prefersPdf' => $prefersPdf ?? false,
                    'printRoute' => 'online_store.permits.printView',
                    'pdfRoute' => 'online_store.permits.pdf',
                    'documentId' => $permit->id,
                ])
            @endif

            @if ($permit->permission_type == 1 && $permit->status == 'apporod')
                <a href="{{ route('store_purchase_invoices.create') }}?from_permit={{ $permit->id }}" class="btn btn-outline-success">
                    <i class="feather icon-file-text"></i> إنشاء فاتورة شراء
                </a>
            @endif

            @php $isLinkedPermit = in_array($permit->reference_type, ['purchase_invoice', 'purchase_return'], true); @endphp

            {{-- الاعتماد والرفض وإلغاء الاعتماد: لكلٍّ صلاحيتُه، كحارس مساره تماماً --}}
            @if ($permit->status == 'pending')
                @if (hasPermission('stock.permits.approve'))
                    <button type="button" class="btn btn-outline-success" onclick="approvePermit()">
                        <i class="feather icon-check-circle"></i> موافقة
                    </button>
                @endif
                @if (hasPermission('stock.permits.reject'))
                    <button type="button" class="btn btn-outline-danger" onclick="rejectPermit()">
                        <i class="feather icon-x-circle"></i> رفض
                    </button>
                @endif
            @elseif ($permit->status == 'apporod' && ! $isLinkedPermit && hasPermission('stock.permits.cancel_approval'))
                <button type="button" class="btn btn-outline-warning" onclick="cancelPermitApproval()">
                    <i class="feather icon-rotate-ccw"></i> إلغاء الاعتماد
                </button>
            @endif

            {{-- التعديل للإذن المعلَّق وحده — المعتمَد والمرفوض لا يُعدَّلان --}}
            @if ($permit->status == 'pending' && hasPermission('stock.permits.edit') && ! $isLinkedPermit)
                <a href="{{ route('online_store.permits.edit', $permit->id) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif

            @if (hasPermission('stock.permits.delete') && $permit->status !== 'apporod' && ! in_array($permit->reference_type, ['purchase_invoice', 'purchase_return'], true))
                <button type="button" class="btn btn-outline-danger" onclick="deletePermit()">
                    <i class="feather icon-trash-2"></i> حذف
                </button>
            @endif

            @if (hasPermission('stock.permits.export'))
                <button type="button" class="btn btn-outline-secondary" onclick="exportPermitExcel()">
                    <i class="feather icon-download"></i> تصدير
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إذن مخزن #'.$permit->number,
            'icon' => 'file-text',
            'sub' => (int) $permit->permission_type === 3
                ? (($permit->fromStoreHouse->name ?? '—').' ← '.($permit->toStoreHouse->name ?? '—'))
                : ($permit->storeHouse->name ?? 'المستودع غير محدد'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => route('online_store.permits.index'),
                $permit->number => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>رقم الإذن</dt>
                                <dd class="stk-num">{{ $permit->number }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الحالة</dt>
                                <dd>
                                    @switch($permit->status)
                                        @case('pending')
                                            <span class="stk-state stk-state--warn"><i class="feather icon-clock"></i> قيد الانتظار</span>
                                            @break
                                        @case('apporod')
                                            <span class="stk-state stk-state--on"><i class="feather icon-check-circle"></i> مُعتمد</span>
                                            @break
                                        @case('rejec')
                                        @case('rejected')
                                            <span class="stk-state stk-state--off"><i class="feather icon-x-circle"></i> مرفوض</span>
                                            @break
                                        @default
                                            <span class="stk-state stk-state--neutral"><i class="feather icon-help-circle"></i> غير محدد</span>
                                    @endswitch
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>نوع الإذن</dt>
                                <dd>{{ [1 => 'إضافة', 2 => 'صرف', 3 => 'تحويل', 4 => 'رصيد افتتاحي'][(int) $permit->permission_type] ?? 'غير محدد' }}</dd>
                            </div>
                            @if ((int) $permit->permission_type === 3)
                                <div class="stk-def__row">
                                    <dt>من مستودع</dt>
                                    <dd>{{ $permit->fromStoreHouse->name ?? 'غير محدد' }}
                                        @if ($permit->fromStoreHouse?->branch) <small class="text-muted">— {{ $permit->fromStoreHouse->branch->name }}</small> @endif
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>إلى مستودع</dt>
                                    <dd>{{ $permit->toStoreHouse->name ?? 'غير محدد' }}
                                        @if ($permit->toStoreHouse?->branch) <small class="text-muted">— {{ $permit->toStoreHouse->branch->name }}</small> @endif
                                    </dd>
                                </div>
                            @else
                                <div class="stk-def__row">
                                    <dt>المستودع</dt>
                                    <dd>{{ $permit->storeHouse->name ?? 'غير محدد' }}
                                        @if ($permit->storeHouse?->branch) <small class="text-muted">— {{ $permit->storeHouse->branch->name }}</small> @endif
                                    </dd>
                                </div>
                            @endif
                            @if (in_array($permit->status, ['rejec', 'rejected'], true) && $permit->rejection_reason)
                                <div class="stk-def__row">
                                    <dt>سبب الرفض</dt>
                                    <dd>{{ $permit->rejection_reason }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>تاريخ الإذن</dt>
                                <dd class="stk-num">{{ $permit->permission_date ? \Illuminate\Support\Carbon::parse($permit->permission_date)->format('Y-m-d H:i') : $permit->created_at?->format('Y-m-d') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الإجمالي</dt>
                                <dd class="stk-num"><strong>{{ number_format((float) $permit->grand_total, 2) }} {{ $permit->currency ?: \App\Support\CurrencySettings::code() }}</strong></dd>
                            </div>
                            @if ($permit->user)
                                <div class="stk-def__row">
                                    <dt>أنشئ بواسطة</dt>
                                    <dd>{{ $permit->user->name }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#preview" role="tab">
                            <i class="feather icon-eye"></i> معاينة الإذن
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#items" role="tab">
                            <i class="feather icon-box"></i> الأصناف
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#entries" role="tab">
                            <i class="feather icon-book-open"></i> القيود المحاسبية
                            <span class="stk-count">{{ $journalEntries->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#activity" role="tab">
                            <i class="feather icon-activity"></i> سجل النشاطات
                        </a>
                    </li>
                </ul>

                <div class="tab-content pt-3">
                    <!-- تبويب معاينة الإذن -->
                    <div class="tab-pane active" id="preview" role="tabpanel">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="tab-pane fade show active"
                                    style="background: lightslategray; min-height: 100vh; padding: 20px;">
                                    <div class="card shadow" style="max-width: 600px; margin: 20px auto;">
                                        <div class="card-body bg-white p-4" style="min-height: 400px; overflow: auto;">
                                            <div id="print-section"
                                                style="transform: scale(0.8); transform-origin: top center;">
                                                @include('onlinestore::store_permits.partials.pdf', ['permit' => $permit])
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- تبويب الأصناف -->
                    <div class="tab-pane" id="items" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>كود الصنف</th>
                                        <th>اسم الصنف</th>
                                        @php $permitCurrency = $permit->currency ?: \App\Support\CurrencySettings::code(); @endphp
                                        <th>الكمية</th>
                                        <th>سعر الوحدة ({{ $permitCurrency }})</th>
                                        <th>الإجمالي ({{ $permitCurrency }})</th>
                                        <th>الوحدة</th>
                                        <th>تاريخ الانتهاء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($permit->items as $item)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $item->product->code ?? 'غير محدد' }}</td>
                                            <td>{{ $item->product->name ?? $item->item }}</td>
                                            <td class="stk-num">{{ $item->quantity }}</td>
                                            <td class="stk-num">{{ number_format($item->unit_price, 2) }}</td>
                                            <td class="stk-num">{{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                                            <td>{{ $item->product->unit ?? 'قطعة' }}</td>
                                            <td>{{ $item->expiry_date ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8">
                                                <div class="stk-empty">
                                                    <span class="stk-empty__icon"><i class="feather icon-box"></i></span>
                                                    <h6 class="stk-empty__title">لا توجد أصناف</h6>
                                                    <p class="stk-empty__hint">لا توجد أصناف في هذا الإذن.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if($permit->items->count() > 0)
                                    <tfoot>
                                        <tr class="stk-total-row">
                                            <td colspan="5" class="text-start fw-bold">الإجمالي</td>
                                            <td class="fw-bold stk-num">{{ number_format($permit->items->sum(fn($i) => $i->quantity * $i->unit_price), 2) }} {{ $permitCurrency }}</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                    <!-- تبويب القيود المحاسبية: قيدُ الاعتماد وعكسُه إن أُلغي -->
                    <div class="tab-pane" id="entries" role="tabpanel">
                        @if ($journalEntries->isEmpty())
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-book-open"></i></span>
                                <h6 class="stk-empty__title">لا قيود محاسبية</h6>
                                <p class="stk-empty__hint">القيد يُرحَّل عند اعتماد الإذن — الإذن المعلَّق لا يمسّ المخزون ولا الدفاتر.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>رقم القيد</th>
                                            <th>التاريخ</th>
                                            <th>البيان</th>
                                            <th class="text-center">المبلغ</th>
                                            <th class="text-center">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($journalEntries as $entry)
                                            <tr>
                                                <td class="stk-num">{{ $entry->reference_number }}</td>
                                                <td class="stk-num">{{ \Illuminate\Support\Carbon::parse($entry->date)->format('Y-m-d') }}</td>
                                                <td>{{ $entry->description }}</td>
                                                <td class="text-center stk-num">{{ number_format((float) $entry->details->sum('debit'), 2) }} {{ $entry->currency ?: \App\Support\CurrencySettings::code() }}</td>
                                                <td class="text-center">
                                                    @if (str_starts_with((string) $entry->batch_reference, 'WAREHOUSE_REVERSAL_'))
                                                        <span class="badge badge-light-warning">قيد عكسي</span>
                                                    @elseif ($entry->is_reversed)
                                                        <span class="badge badge-light-secondary">معكوس</span>
                                                    @else
                                                        <span class="badge badge-light-success">سارٍ</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <!-- تبويب سجل النشاطات -->
                    <div class="tab-pane" id="activity" role="tabpanel">
                        @if ($logs && count($logs) > 0)
                            @foreach ($logs as $date => $dayLogs)
                                @php
                                    $currentDate = \Carbon\Carbon::parse($date);
                                @endphp

                                <div class="stk-section__head">
                                    <i class="feather icon-calendar"></i>
                                    <h6 class="stk-section__title">
                                        {{ $currentDate->translatedFormat('l') }} — {{ $currentDate->format('Y-m-d') }}
                                    </h6>
                                </div>

                                <div class="stk-timeline mb-4">
                                    @foreach ($dayLogs as $log)
                                        @if ($log)
                                            <div class="stk-timeline__item">
                                                <span class="stk-timeline__dot stk-timeline__dot--log">
                                                    <i class="feather icon-activity"></i>
                                                </span>

                                                <div class="stk-timeline__body">
                                                    <div class="stk-timeline__text">
                                                        <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                        {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                                                    </div>

                                                    <div class="stk-timeline__meta">
                                                        <span class="stk-timeline__time">
                                                            {{ $log->created_at->format('H:i') }}
                                                        </span>
                                                        <span>{{ $log->user->branch->name ?? 'فرع غير معروف' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-activity"></i></span>
                                <h6 class="stk-empty__title">لا سجلّات نشاط</h6>
                                <p class="stk-empty__hint">لم تُسجَّل عمليات على هذا الإذن بعد.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden Forms للعمليات -->
        <form id="deleteForm" action="{{ route('online_store.permits.delete', $permit->id) }}" method="POST"
            style="display: none;">
            @csrf
            @method('DELETE')
        </form>

        <form id="approveForm" action="{{ route('online_store.permits.approve', $permit->id) }}" method="POST"
            style="display: none;">
            @csrf
            @method('PATCH')
        </form>

        <form id="rejectForm" action="{{ route('online_store.permits.reject', $permit->id) }}" method="POST"
            style="display: none;">
            @csrf
            @method('PATCH')
            <input type="hidden" name="rejection_reason" id="rejectReasonInput">
        </form>

        <form id="cancelApprovalForm" action="{{ route('online_store.permits.cancel_approval', $permit->id) }}" method="POST"
            style="display: none;">
            @csrf
        </form>
    </div>
@endsection

@section('scripts')
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // دالة اعتماد الإذن
        function approvePermit() {
            Swal.fire({
                title: 'اعتماد إذن المخزن',
                text: 'هل أنت متأكد من اعتماد إذن المخزن رقم "{{ $permit->number }}"؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، اعتمد الإذن',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('approveForm').submit();
                }
            });
        }

        // دالة رفض الإذن — بسببٍ يُحفظ مع الإذن
        function rejectPermit() {
            Swal.fire({
                title: 'رفض إذن المخزن',
                text: 'اكتب سبب رفض إذن المخزن رقم "{{ $permit->number }}"',
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'سبب الرفض…',
                inputAttributes: { maxlength: 1000 },
                inputValidator: (value) => (! value || ! value.trim()) ? 'سبب الرفض مطلوب' : undefined,
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، ارفض الإذن',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('rejectReasonInput').value = result.value;
                    document.getElementById('rejectForm').submit();
                }
            });
        }

        // إلغاء اعتماد إذنٍ معتمَد: يعيد الكميات ويعكس القيد
        function cancelPermitApproval() {
            Swal.fire({
                title: 'إلغاء الاعتماد',
                text: 'سيُعاد المخزون ويُعكس القيد المحاسبي للإذن رقم "{{ $permit->number }}" ويعود الإذن معلَّقاً. هل تريد المتابعة؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f0ad4e',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، ألغِ الاعتماد',
                cancelButtonText: 'تراجع'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('cancelApprovalForm').submit();
                }
            });
        }

        // دالة حذف الإذن
        function deletePermit() {
            Swal.fire({
                title: 'حذف إذن المخزن',
                text: 'هل أنت متأكد من حذف إذن المخزن رقم "{{ $permit->number }}"؟ هذا الإجراء لا يمكن التراجع عنه!',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // دالة تصدير الإذن Excel
        function exportPermitExcel() {
            window.location.href = "{{ route('online_store.permits.export') }}?id={{ $permit->id }}";
        }

        // عرض رسائل النجاح والخطأ
        @if (session('success'))
            Swal.fire({
                title: 'تم بنجاح!',
                text: '{{ session('success') }}',
                icon: 'success',
                confirmButtonColor: '#28a745'
            });
        @endif

        @if (session('error'))
            Swal.fire({
                title: 'خطأ!',
                text: '{{ session('error') }}',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        @endif
    </script>
@endsection
