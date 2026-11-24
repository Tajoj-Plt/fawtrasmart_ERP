@extends('onlinestore::master')

@section('title', 'الأذون المخزنية')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission(['stock.permits.create_addition', 'stock.permits.create_disbursement', 'stock.permits.create_transfer', 'stock.permits.create_opening']))
                <div class="btn-group dropdown">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="feather icon-plus"></i> إضافة إذن مخزني
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        @if (hasPermission('stock.permits.create_addition'))
                            <a class="dropdown-item" href="{{ route('online_store.permits.create') }}">
                                <i class="feather icon-plus text-success"></i> إضافة يدوي
                            </a>
                        @endif
                        @if (hasPermission('stock.permits.create_disbursement'))
                            <a class="dropdown-item" href="{{ route('online_store.permits.manual_disbursement') }}">
                                <i class="feather icon-minus text-danger"></i> صرف يدوي
                            </a>
                        @endif
                        @if (hasPermission('stock.permits.create_transfer'))
                            <a class="dropdown-item" href="{{ route('online_store.permits.manual_conversion') }}">
                                <i class="feather icon-repeat text-info"></i> تحويل يدوي
                            </a>
                        @endif
                        @if (hasPermission('stock.permits.create_opening'))
                            <a class="dropdown-item" href="{{ route('online_store.permits.opening_balance') }}">
                                <i class="feather icon-star text-warning"></i> رصيد افتتاحي
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'الأذون المخزنية',
            'icon' => 'file-text',
            'sub' => 'أذون إضافة وصرف وتحويل ورصيد افتتاحي المخزون',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الأذون المخزنية' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">إجمالي الأذون</span>
                <span class="stk-metric__value">{{ $wareHousePermits->total() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">أذون موافق عليها</span>
                <span class="stk-metric__value">{{ $approvedCount ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">أذون معلقة</span>
                <span class="stk-metric__value">{{ $pendingCount ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">أذون مرفوضة</span>
                <span class="stk-metric__value">{{ $rejectedCount ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('online_store.permits.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>البحث</label>
                                <input type="text" name="keywords" class="form-control"
                                       placeholder="ابحث برقم الإذن أو التفاصيل"
                                       value="{{ is_string(request('keywords')) ? request('keywords') : '' }}">
                            </div>
                        </div>
                        {{--
                            فلتر الفرع على فرع **المخزن** وما تحته (لا فرع منشئ الإذن)،
                            وخياراتُه فروعُ المستخدم وحدَها.
                        --}}
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>الفرع</label>
                                <select name="branch_id" class="form-control select2">
                                    <option value="">كل الفروع المتاحة</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ (is_scalar(request('branch_id')) ? (string) request('branch_id') : '') === (string) $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>نوع الإذن</label>
                                <select name="permission_type" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="1" {{ request('permission_type') == '1' ? 'selected' : '' }}>إضافة</option>
                                    <option value="2" {{ request('permission_type') == '2' ? 'selected' : '' }}>صرف</option>
                                    <option value="3" {{ request('permission_type') == '3' ? 'selected' : '' }}>تحويل</option>
                                    <option value="4" {{ request('permission_type') == '4' ? 'selected' : '' }}>رصيد افتتاحي</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>الحالة</label>
                                <select name="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>قيد الانتظار</option>
                                    <option value="apporod" {{ request('status') == 'apporod' ? 'selected' : '' }}>موافق عليه</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>مرفوض</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>المستودع</label>
                                <select name="store_house" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($storeHouses as $storeHouse)
                                        <option value="{{ $storeHouse->id }}" {{ request('store_house') == $storeHouse->id ? 'selected' : '' }}>
                                            {{ $storeHouse->name }}{{ $storeHouse->branch ? ' — '.$storeHouse->branch->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('online_store.permits.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        @if (hasPermission('stock.permits.export'))
                            <button type="button" id="printPermitsBtn" class="btn btn-outline-secondary">
                                <i class="feather icon-printer"></i> طباعة
                            </button>
                            <button type="button" id="exportPermitsBtn" class="btn btn-outline-success">
                                <i class="feather icon-download"></i> تصدير Excel
                            </button>
                        @endif
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card" id="reportCard">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة الأذون المخزنية</h6>
                <span class="badge badge-light-primary">{{ $wareHousePermits->total() }} إذن</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="permitsTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th>رقم الإذن</th>
                            <th>التاريخ</th>
                            <th class="text-center">نوع الإذن</th>
                            <th class="text-center">مصدر الإذن</th>
                            <th>المستودع</th>
                            <th class="text-center">الإجمالي ({{ \App\Support\CurrencySettings::code() }})</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width: 100px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($wareHousePermits as $item)
                            <tr>
                                <td class="text-center stk-num">{{ $loop->iteration + ($wareHousePermits->currentPage() - 1) * $wareHousePermits->perPage() }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb">
                                            @if ($item->permission_type == 1)
                                                <i class="fa fa-plus"></i>
                                            @elseif($item->permission_type == 2)
                                                <i class="fa fa-minus"></i>
                                            @elseif($item->permission_type == 3)
                                                <i class="fa fa-exchange"></i>
                                            @else
                                                <i class="fa fa-star"></i>
                                            @endif
                                        </span>
                                        <span class="stk-media__body">
                                            <span class="stk-media__title stk-num">{{ $item->number }}</span>
                                            <span class="stk-media__sub">#{{ $item->id }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted stk-num">
                                        <i class="feather icon-calendar mr-1"></i>
                                        {{ \Carbon\Carbon::parse($item->permission_date)->format('Y-m-d') }}
                                    </span>
                                    <br>
                                    <small class="text-muted">{{ $item->user->name ?? '---' }}</small>
                                </td>
                                <td class="text-center">
                                    @if ($item->permission_type == 1)
                                        <span class="badge badge-light-success"><i class="fa fa-plus"></i> إضافة</span>
                                    @elseif($item->permission_type == 2)
                                        <span class="badge badge-light-danger"><i class="fa fa-minus"></i> صرف</span>
                                    @elseif($item->permission_type == 3)
                                        <span class="badge badge-light-info"><i class="fa fa-exchange"></i> تحويل</span>
                                    @else
                                        <span class="badge badge-light-warning"><i class="fa fa-star"></i> رصيد افتتاحي</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php
                                        $sourceName = \App\Constants\permissions\PermissionSources::getName($item->permission_source_id ?? 0);
                                    @endphp
                                    <span class="badge badge-light-primary">{{ $sourceName }}</span>
                                </td>
                                <td>
                                    @if ($item->fromStoreHouse && $item->toStoreHouse)
                                        <span class="text-primary">{{ $item->fromStoreHouse->name }}</span>
                                        <i class="fa fa-arrow-left mx-1 text-muted"></i>
                                        <span class="text-success">{{ $item->toStoreHouse->name }}</span>
                                        <br>
                                        <small class="text-muted">
                                            {{ $item->fromStoreHouse->branch->name ?? '—' }}
                                            ←
                                            {{ $item->toStoreHouse->branch->name ?? '—' }}
                                        </small>
                                    @elseif ($item->fromStoreHouse)
                                        {{ $item->fromStoreHouse->name }}
                                    @elseif ($item->toStoreHouse)
                                        {{ $item->toStoreHouse->name }}
                                    @elseif ($item->storeHouse)
                                        {{ $item->storeHouse->name }}
                                        @if ($item->storeHouse->branch)
                                            <br><small class="text-muted">{{ $item->storeHouse->branch->name }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </td>
                                <td class="text-center stk-num">{{ number_format((float) $item->grand_total, 2) }}</td>
                                <td class="text-center">
                                    @if ($item->status == 'pending')
                                        <span class="stk-state stk-state--warn">
                                            <i class="feather icon-clock"></i> قيد الانتظار
                                        </span>
                                    @elseif ($item->status == 'apporod')
                                        <span class="stk-state stk-state--on">
                                            <i class="feather icon-check-circle"></i> موافق عليه
                                        </span>
                                    @elseif (in_array($item->status, ['rejec', 'rejected'], true))
                                        {{-- «مرفوض» تُخزَّن `rejec` — كانت الشاشة تقارن بـ`rejected` فتبقى الخانة فارغة --}}
                                        <span class="stk-state stk-state--off" @if ($item->rejection_reason) title="{{ $item->rejection_reason }}" @endif>
                                            <i class="feather icon-x-circle"></i> مرفوض
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-icon dropdown-toggle dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown"
                                            aria-expanded="false">
                                            <i class="feather icon-more-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            @if (hasPermission('stock.permits.view'))
                                                <a class="dropdown-item" href="{{ route('online_store.permits.show', $item->id) }}">
                                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                                </a>
                                            @endif

                                            @if ($item->status == 'pending' && hasPermission('stock.permits.edit') && ! in_array($item->reference_type, ['purchase_invoice', 'purchase_return'], true))
                                                <a class="dropdown-item" href="{{ route('online_store.permits.edit', $item->id) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                            @endif

                                            @if (hasPermission('stock.permits.print'))
                                                <a class="dropdown-item" href="{{ route('online_store.permits.printView', $item->id) }}" target="_blank">
                                                    <i class="feather icon-printer"></i> طباعة
                                                </a>
                                            @endif

                                            @if ($item->permission_type == 1 && $item->status == 'apporod')
                                                <a class="dropdown-item" href="{{ route('store_purchase_invoices.create') }}?from_permit={{ $item->id }}">
                                                    <i class="feather icon-file-text"></i> إنشاء فاتورة شراء
                                                </a>
                                            @endif

                                            <div class="dropdown-divider"></div>

                                            {{-- الاعتماد والرفض وإلغاء الاعتماد: لكلٍّ صلاحيتُه، كحارس مساره تماماً --}}
                                            @if ($item->status == 'pending')
                                                @if (hasPermission('stock.permits.approve'))
                                                    <a class="dropdown-item text-success approve-btn" href="#"
                                                        data-id="{{ $item->id }}"
                                                        data-number="{{ $item->number }}">
                                                        <i class="feather icon-check"></i> موافقة
                                                    </a>
                                                @endif
                                                @if (hasPermission('stock.permits.reject'))
                                                    <a class="dropdown-item text-warning reject-btn" href="#"
                                                        data-url="{{ route('online_store.permits.reject', $item->id) }}"
                                                        data-number="{{ $item->number }}">
                                                        <i class="feather icon-x"></i> رفض
                                                    </a>
                                                @endif
                                            @elseif ($item->status == 'apporod' && hasPermission('stock.permits.cancel_approval') && ! in_array($item->reference_type, ['purchase_invoice', 'purchase_return'], true))
                                                <a class="dropdown-item text-warning cancel-approval-btn" href="#"
                                                    data-id="{{ $item->id }}"
                                                    data-number="{{ $item->number }}">
                                                    <i class="feather icon-rotate-ccw"></i> إلغاء الاعتماد
                                                </a>
                                            @endif

                                            @if (hasPermission('stock.permits.delete') && $item->status !== 'apporod' && ! in_array($item->reference_type, ['purchase_invoice', 'purchase_return'], true))
                                                <a class="dropdown-item text-danger delete-btn" href="#"
                                                    data-id="{{ $item->id }}"
                                                    data-number="{{ $item->number }}">
                                                    <i class="feather icon-trash-2"></i> حذف
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                        <h6 class="stk-empty__title">لا توجد أذون مخزنية</h6>
                                        <p class="stk-empty__hint">لا توجد أذون مخزنية لعرضها.</p>
                                        @if (hasPermission('stock.permits.create_addition'))
                                            <a href="{{ route('online_store.permits.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة إذن جديد
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $wareHousePermits, 'label' => 'إذن'])
        </div>

        {{--
            نافذة رفضٍ واحدة لكل الصفوف. كانت نافذةٌ لكل صفّ بصيغة Bootstrap 4
            (`data-toggle`/`data-dismiss`) لا تُفتح حين يكون Bootstrap 5 هو
            العامل، ونموذجُها يُرسَل POST إلى مسارٍ PATCH فينتهي بـ405.
        --}}
        @if (hasPermission('stock.permits.reject'))
            <div class="modal fade" id="permitRejectModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title text-white">رفض الإذن المخزني رقم <span id="permitRejectNumber"></span></h5>
                            <button type="button" class="btn-close js-reject-close" aria-label="إغلاق"></button>
                        </div>
                        <form method="POST" id="permitRejectForm" action="#">
                            @csrf
                            @method('PATCH')
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label" for="permitRejectReason">سبب الرفض <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="permitRejectReason" name="rejection_reason" rows="4" maxlength="1000"
                                        required placeholder="يرجى كتابة سبب رفض الإذن المخزني..."></textarea>
                                </div>
                                <div class="alert alert-warning mb-0">
                                    <i class="fa fa-exclamation-triangle me-2"></i>
                                    <strong>تنبيه:</strong> سيتم رفض الإذن المخزني نهائياً.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary js-reject-close">
                                    <i class="fa fa-times me-1"></i> إلغاء
                                </button>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fa fa-times me-1"></i> تأكيد الرفض
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
    }

    // تأكيد الموافقة على الإذن
    $('.approve-btn').on('click', function(e) {
        e.preventDefault();
        const permitId = $(this).data('id');
        const permitNumber = $(this).data('number');

        Swal.fire({
            title: 'تأكيد الموافقة',
            text: `هل أنت متأكد من الموافقة على الإذن المخزني رقم #${permitNumber}؟`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، موافق',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                approvePermit(permitId);
            }
        });
    });

    // ── نافذة الرفض: تعمل مع Bootstrap 4 (jQuery) أو 5 أيّهما كان العامل ──
    function toggleRejectModal(show) {
        var el = document.getElementById('permitRejectModal');
        if (! el) return;

        if (window.jQuery && jQuery.fn && jQuery.fn.modal) {
            jQuery(el).modal(show ? 'show' : 'hide');
            return;
        }

        var B = window.bootstrap;
        if (B && B.Modal) {
            var instance = B.Modal.getOrCreateInstance ? B.Modal.getOrCreateInstance(el) : new B.Modal(el);
            show ? instance.show() : instance.hide();
            return;
        }

        el.style.display = show ? 'block' : 'none';
        el.classList.toggle('show', show);
    }

    $('.reject-btn').on('click', function(e) {
        e.preventDefault();
        $('#permitRejectForm').attr('action', $(this).data('url'));
        $('#permitRejectNumber').text('#' + $(this).data('number'));
        $('#permitRejectReason').val('');
        toggleRejectModal(true);
    });

    $(document).on('click', '.js-reject-close', function() {
        toggleRejectModal(false);
    });

    // إلغاء اعتماد إذنٍ معتمَد: يعيد الكميات ويعكس القيد
    $('.cancel-approval-btn').on('click', function(e) {
        e.preventDefault();
        const permitId = $(this).data('id');
        const permitNumber = $(this).data('number');

        Swal.fire({
            title: 'إلغاء الاعتماد',
            text: `سيُعاد المخزون ويُعكس القيد المحاسبي للإذن رقم #${permitNumber} ويعود الإذن معلَّقاً. هل تريد المتابعة؟`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f0ad4e',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، ألغِ الاعتماد',
            cancelButtonText: 'تراجع'
        }).then((result) => {
            if (! result.isConfirmed) return;

            $.ajax({
                url: `{{ url('online_store/permits') }}/${permitId}/cancel-approval`,
                method: 'POST',
                data: { '_token': '{{ csrf_token() }}' },
                success: function(response) {
                    Swal.fire({ title: response.success ? 'تم' : 'تعذّر', text: response.message, icon: response.success ? 'success' : 'error' })
                        .then(() => { if (response.success) location.reload(); });
                },
                error: function(xhr) {
                    Swal.fire({ title: 'تعذّر إلغاء الاعتماد', text: xhr.responseJSON?.message || 'حدث خطأ أثناء معالجة الطلب', icon: 'error' });
                }
            });
        });
    });

    // تأكيد حذف الإذن
    $('.delete-btn').on('click', function(e) {
        e.preventDefault();
        const permitId = $(this).data('id');
        const permitNumber = $(this).data('number');

        Swal.fire({
            title: 'تأكيد الحذف',
            text: `هل أنت متأكد من حذف الإذن المخزني رقم #${permitNumber}؟`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                deletePermit(permitId);
            }
        });
    });

    function approvePermit(permitId) {
        $.ajax({
            url: `{{ url('online_store/permits') }}/${permitId}/approve`,
            method: 'PATCH',
            data: { '_token': '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'تم بنجاح!',
                        text: response.message,
                        icon: 'success',
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({ title: 'تعذّرت الموافقة', text: response.message || 'حدث خطأ أثناء معالجة الطلب', icon: 'error' });
                }
            },
            error: function(xhr) {
                let errorMessage = xhr.responseJSON?.message || 'حدث خطأ أثناء معالجة الطلب';
                Swal.fire({ title: 'خطأ!', text: errorMessage, icon: 'error' });
            }
        });
    }

    function deletePermit(permitId) {
        $.ajax({
            url: `{{ url('online_store/permits') }}/${permitId}`,
            method: 'DELETE',
            data: { '_token': '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحذف',
                        text: response.message || 'تم حذف الإذن المخزني بنجاح',
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'لا يمكن الحذف', text: response.message || 'حدث خطأ أثناء حذف الإذن المخزني' });
                }
            },
            error: function(xhr) {
                let errorMessage = xhr.responseJSON?.message || 'حدث خطأ أثناء حذف الإذن المخزني';
                Swal.fire({ icon: 'error', title: 'لا يمكن الحذف', text: errorMessage });
            }
        });
    }

    @if (session('success'))
        Swal.fire({ title: 'تم بنجاح!', text: '{{ session("success") }}', icon: 'success', timer: 3000 });
    @endif

    @if (session('error'))
        Swal.fire({ title: 'خطأ!', text: '{{ session("error") }}', icon: 'error' });
    @endif
});

// =====================================
// طباعة وتصدير مخصّصان — نفس نمط Account\GeneralAccountsController
// (public/js/print-table.js + public/js/excel-export-helper.js)، بدل
// التنزيل الخادمي المباشر السابق. مقصوران على صفحة القائمة الحالية
// (نفس صفحات الترقيم الأخرى في النظام) لا كل الأذون.
// =====================================
function permitTypeLabel(type) {
    switch (parseInt(type, 10)) {
        case 1: return 'إضافة';
        case 2: return 'صرف';
        case 3: return 'تحويل';
        case 4: return 'رصيد افتتاحي';
        default: return 'غير محدد';
    }
}

function permitStatusLabel(status) {
    switch (status) {
        case 'pending': return 'قيد الانتظار';
        case 'apporod': return 'موافق عليه';
        case 'rejec':
        case 'rejected': return 'مرفوض';
        default: return status;
    }
}

function buildPermitsExportRows() {
    return [
        @foreach ($wareHousePermits as $item)
        @php
            // تسميات النوع/الحالة تُحسب في PHP — كانت تستدعي دالة JS من داخل
            // @json فتُصرَّف إلى نداء PHP لدالة غير موجودة (Call to undefined function)
            $permitTypeText = match ((int) $item->permission_type) {
                1 => 'إضافة',
                2 => 'صرف',
                3 => 'تحويل',
                4 => 'رصيد افتتاحي',
                default => 'غير محدد',
            };
            $permitStatusText = match ((string) $item->status) {
                'pending' => 'قيد الانتظار',
                'apporod' => 'موافق عليه',
                'rejec', 'rejected' => 'مرفوض',
                default => (string) $item->status,
            };
        @endphp
        {
            id: {{ $item->id }},
            number: @json($item->number),
            user: @json($item->user->name ?? '---'),
            date: @json(\Carbon\Carbon::parse($item->permission_date)->format('Y-m-d')),
            type: @json($permitTypeText),
            source: @json(\App\Constants\permissions\PermissionSources::getName($item->permission_source_id ?? 0)),
            warehouse: @json(
                $item->fromStoreHouse && $item->toStoreHouse
                    ? $item->fromStoreHouse->name.' ← '.$item->toStoreHouse->name
                    : ($item->fromStoreHouse->name ?? $item->toStoreHouse->name ?? $item->storeHouse->name ?? 'غير محدد')
            ),
            total: @json(number_format((float) $item->grand_total, 2).' '.\App\Support\CurrencySettings::code()),
            status: @json($permitStatusText),
        },
        @endforeach
    ];
}

function printPermitsList() {
    const rows = buildPermitsExportRows();

    printTable({
        tableSelector: '#permitsTable tbody tr',
        title: 'تقرير الأذون المخزنية',
        infoCards: [
            { label: 'إجمالي الأذون', value: '{{ $wareHousePermits->total() ?? 0 }}' },
            { label: 'موافق عليها', value: '{{ $approvedCount ?? 0 }}', class: 'text-success' },
            { label: 'معلّقة', value: '{{ $pendingCount ?? 0 }}', class: 'text-warning' },
            { label: 'مرفوضة', value: '{{ $rejectedCount ?? 0 }}', class: 'text-danger' }
        ],
        filterInfo: {
            @if(request('keywords')) 'بحث': @json(request('keywords')), @endif
            @if(request('permission_type')) 'نوع الإذن': permitTypeLabel({{ (int) request('permission_type') }}), @endif
            @if(request('status')) 'الحالة': permitStatusLabel(@json(request('status'))), @endif
            @if(is_numeric(request('branch_id')) && $branches->firstWhere('id', (int) request('branch_id'))) 'الفرع': @json($branches->firstWhere('id', (int) request('branch_id'))->name), @endif
            @if(is_numeric(request('store_house')) && $storeHouses->firstWhere('id', (int) request('store_house'))) 'المستودع': @json($storeHouses->firstWhere('id', (int) request('store_house'))->name), @endif
        },
        tableHeaders: ['#', 'رقم الإذن', 'التاريخ', 'نوع الإذن', 'مصدر الإذن', 'المستودع', 'الإجمالي ({{ \App\Support\CurrencySettings::code() }})', 'الحالة'],
        columnWidths: ['5%', '14%', '13%', '11%', '15%', '20%', '11%', '11%'],
        skipColumns: [8]
    });
}

function exportPermitsListExcel() {
    const rows = buildPermitsExportRows();

    const config = {
        fileName: 'تقرير_الأذون_المخزنية',
        mainTitle: 'تقرير الأذون المخزنية',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
        },

        statisticsCards: [
            { title: 'إجمالي الأذون', value: '{{ $wareHousePermits->total() ?? 0 }}', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'موافق عليها', value: '{{ $approvedCount ?? 0 }}', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'معلّقة', value: '{{ $pendingCount ?? 0 }}', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' },
            { title: 'مرفوضة', value: '{{ $rejectedCount ?? 0 }}', titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' }
        ],

        tableTitle: 'قائمة الأذون المخزنية',
        tableHeaders: ['#', 'رقم الإذن', 'المستخدم', 'التاريخ', 'نوع الإذن', 'مصدر الإذن', 'المستودع', 'الإجمالي', 'الحالة'],
        columnWidths: [5, 16, 16, 12, 12, 20, 26, 16, 14],

        tableData: rows.map((row, index) => ({
            values: [index + 1, row.number, row.user, row.date, row.type, row.source, row.warehouse, row.total, row.status],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } }
            ]
        })),

        buttonSelector: '#exportPermitsBtn',
        successMessage: 'تم تصدير تقرير الأذون المخزنية بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}

$('#printPermitsBtn').on('click', printPermitsList);
$('#exportPermitsBtn').on('click', exportPermitsListExcel);
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
@endsection
