@extends('onlinestore::master')

@section('title', 'تقييمات المنتجات')

@section('content')
    @php
        // اسم العميل: لا عمود `name` في `clients` — كان يظهر «عميل محذوف» لكل العملاء
        $reviewClientName = fn ($r) => $r->client ? $r->client->display_name : 'عميل محذوف';
        $productThumb = function ($product) {
            $images = $product ? json_decode((string) $product->images, true) : null;

            return is_array($images) && ! empty($images[0]) ? $images[0] : null;
        };
    @endphp
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'تقييمات المنتجات',
            'icon' => 'message-square',
            'sub' => 'تقييمات العملاء على منتجات المتجر واعتمادها والرد عليها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'تقييمات المنتجات' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-message-square"></i></span>
                <span class="stk-metric__label">إجمالي التقييمات</span>
                <span class="stk-metric__value">{{ $stats['total'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">قيد الانتظار</span>
                <span class="stk-metric__value">{{ $stats['pending'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">معتمد</span>
                <span class="stk-metric__value">{{ $stats['approved'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">مرفوض</span>
                <span class="stk-metric__value">{{ $stats['rejected'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-star"></i></span>
                <span class="stk-metric__label">متوسط التقييم</span>
                <span class="stk-metric__value">{{ $stats['average_rating'] ?? 0 }}</span>
            </div>
        </div>

        <div class="card mb-2">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-bar-chart-2"></i> توزيع التقييمات</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    @for ($i = 5; $i >= 1; $i--)
                        @php
                            $count = $ratingDistribution[$i] ?? 0;
                            $total = array_sum($ratingDistribution ?? []);
                            $percentage = $total > 0 ? round(($count / $total) * 100) : 0;
                        @endphp
                        <div class="col-md-2 mb-1">
                            <div class="d-flex align-items-center">
                                <span class="mr-1">{{ $i }}</span>
                                <i class="feather icon-star text-warning mr-1"></i>
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: {{ $percentage }}%"></div>
                                </div>
                                <span class="ml-1 text-muted">({{ $count }})</span>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('product_reviews.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="بحث بالعنوان أو التعليق…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="pending" @selected(request('status') == 'pending')>قيد الانتظار</option>
                                    <option value="approved" @selected(request('status') == 'approved')>معتمد</option>
                                    <option value="rejected" @selected(request('status') == 'rejected')>مرفوض</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="rating">التقييم</label>
                                <select name="rating" id="rating" class="form-control select2">
                                    <option value="">الكل</option>
                                    @for ($i = 5; $i >= 1; $i--)
                                        <option value="{{ $i }}" @selected(request('rating') == $i)>{{ $i }} نجوم</option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="product_id">المنتج</label>
                                <select name="product_id" id="product_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="verified">مشتري مؤكد</label>
                                <select name="verified" id="verified" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="yes" @selected(request('verified') == 'yes')>نعم</option>
                                    <option value="no" @selected(request('verified') == 'no')>لا</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('product_reviews.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $canBulkReviews = hasPermission(['online_store.reviews.moderate', 'online_store.reviews.delete']);
            $canReviewActions = hasPermission([
                'online_store.reviews.view',
                'online_store.reviews.moderate',
                'online_store.reviews.reply',
                'online_store.reviews.delete',
            ]);
            $reviewColspan = 9 + ($canBulkReviews ? 1 : 0) + ($canReviewActions ? 1 : 0);
        @endphp

        @if ($canBulkReviews)
            <div class="card mb-2 d-none" id="bulkActionsBar">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-1">
                    <div>
                        <span class="selected-count">0</span> تقييم محدد
                    </div>
                    <div>
                        @if (hasPermission('online_store.reviews.moderate'))
                            <button type="button" class="btn btn-success btn-sm" id="bulkApprove">
                                <i class="feather icon-check"></i> اعتماد المحدد
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" id="bulkReject">
                                <i class="feather icon-x"></i> رفض المحدد
                            </button>
                        @endif
                        @if (hasPermission('online_store.reviews.delete'))
                            <button type="button" class="btn btn-danger btn-sm" id="bulkDelete">
                                <i class="feather icon-trash-2"></i> حذف المحدد
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة التقييمات</h6>
                <div>
                    <button type="button" id="printReviewsBtn" class="btn btn-sm btn-success">
                        <i class="fa fa-print"></i> طباعة
                    </button>
                    <button type="button" id="exportReviewsExcel" class="btn btn-sm btn-info">
                        <i class="fa fa-file-excel-o"></i> Excel
                    </button>
                    <span class="badge badge-light-primary">{{ $reviews->total() }} تقييم</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="reviewsTable">
                    <thead>
                        <tr>
                            @if ($canBulkReviews)
                                <th class="text-center" style="width: 44px;">
                                    <input type="checkbox" id="selectAll" class="stk-check" title="تحديد الكل">
                                </th>
                            @endif
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>المنتج</th>
                            <th>العميل</th>
                            <th>التقييم</th>
                            <th>التعليق</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center">مؤكد</th>
                            <th class="text-center">مميز</th>
                            <th class="text-center">التاريخ</th>
                            @if ($canReviewActions)
                                <th class="text-center" style="width: 100px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reviews as $review)
                            @php
                                $reviewStatus = $review->status->value ?? $review->status;
                                $statusClass = match ($reviewStatus) {
                                    'pending' => 'stk-state--warn',
                                    'approved' => 'stk-state--on',
                                    'rejected' => 'stk-state--off',
                                    default => 'stk-state--neutral',
                                };
                                $statusText = match ($reviewStatus) {
                                    'pending' => 'قيد الانتظار',
                                    'approved' => 'معتمد',
                                    'rejected' => 'مرفوض',
                                    default => 'غير معروف',
                                };
                            @endphp
                            <tr data-id="{{ $review->id }}">
                                @if ($canBulkReviews)
                                    <td class="text-center">
                                        <input type="checkbox" class="stk-check review-checkbox" value="{{ $review->id }}">
                                    </td>
                                @endif
                                <td class="text-center stk-num">{{ $review->id }}</td>
                                <td>
                                    <div class="stk-media">
                                        @if ($thumb = $productThumb($review->product))
                                            <span class="stk-media__thumb"><img src="{{ asset($thumb) }}"
                                                    alt="{{ $review->product->name ?? '' }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                        @else
                                            <span class="stk-media__thumb"><i class="feather icon-box"></i></span>
                                        @endif
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">{{ $review->product->name ?? 'منتج محذوف' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="stk-media__title">{{ $reviewClientName($review) }}</span>
                                    <span class="stk-media__sub d-block">{{ $review->client->email ?? '' }}</span>
                                </td>
                                <td>
                                    <div>
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i class="feather icon-star {{ $i <= $review->rating ? 'text-warning' : 'text-muted' }}"></i>
                                        @endfor
                                        <span class="text-muted">({{ $review->rating }})</span>
                                    </div>
                                </td>
                                <td>
                                    @if ($review->title)
                                        <strong>{{ Str::limit($review->title, 30) }}</strong><br>
                                    @endif
                                    <span class="text-muted">{{ Str::limit($review->comment, 50) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="stk-state {{ $statusClass }}">{{ $statusText }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($review->is_verified_purchase)
                                        <span class="badge badge-light-success"><i class="feather icon-check"></i> مؤكد</span>
                                    @else
                                        <span class="badge badge-light-secondary">غير مؤكد</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.reviews.toggle_featured'))
                                        <button type="button" class="btn btn-sm btn-link toggle-featured p-0"
                                            data-id="{{ $review->id }}" data-url="{{ route('product_reviews.toggle-featured', $review) }}">
                                            <i class="feather icon-star {{ $review->is_featured ? 'text-warning' : 'text-muted' }}"></i>
                                        </button>
                                    @else
                                        <i class="feather icon-star {{ $review->is_featured ? 'text-warning' : 'text-muted' }}"></i>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <small class="text-muted">{{ $review->created_at->format('Y-m-d') }}</small>
                                </td>
                                @if ($canReviewActions)
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" data-toggle="dropdown" aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-right">
                                                @if (hasPermission('online_store.reviews.view'))
                                                    <a class="dropdown-item" href="{{ route('product_reviews.show', $review) }}">
                                                        <i class="feather icon-eye"></i> عرض التفاصيل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.reviews.moderate'))
                                                    @if ($reviewStatus !== 'approved')
                                                        <button class="dropdown-item approve-review" data-id="{{ $review->id }}" data-url="{{ route('product_reviews.approve', $review) }}">
                                                            <i class="feather icon-check-circle"></i> اعتماد
                                                        </button>
                                                    @endif
                                                    @if ($reviewStatus !== 'rejected')
                                                        <button class="dropdown-item reject-review" data-id="{{ $review->id }}" data-url="{{ route('product_reviews.reject', $review) }}">
                                                            <i class="feather icon-x-circle"></i> رفض
                                                        </button>
                                                    @endif
                                                @endif
                                                @if (hasPermission('online_store.reviews.reply'))
                                                    <button type="button" class="dropdown-item reply-review" data-id="{{ $review->id }}"
                                                        data-current-reply="{{ $review->admin_reply }}">
                                                        <i class="feather icon-message-circle"></i> رد
                                                    </button>
                                                @endif
                                                @if (hasPermission('online_store.reviews.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('product_reviews.destroy', $review) }}" method="POST" class="delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item delete-item text-danger">
                                                            <i class="feather icon-trash-2"></i> حذف
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $reviewColspan }}">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                        <h6 class="stk-empty__title">لا توجد تقييمات</h6>
                                        <p class="stk-empty__hint">لم يتم إضافة أي تقييمات بعد.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- جدول مخفي بنصوص صافية، لأغراض الطباعة والتصدير فقط (بلا أيقونات/إجراءات) --}}
            <table id="reviewsExportTable" class="d-none">
                <tbody>
                    @forelse ($reviews as $review)
                        @php
                            $exportReviewStatus = $review->status->value ?? $review->status;
                            $exportStatusText = match ($exportReviewStatus) {
                                'pending' => 'قيد الانتظار',
                                'approved' => 'معتمد',
                                'rejected' => 'مرفوض',
                                default => 'غير معروف',
                            };
                        @endphp
                        <tr>
                            <td>{{ $review->id }}</td>
                            <td>{{ $review->product->name ?? 'منتج محذوف' }}</td>
                            <td>{{ $reviewClientName($review) }}</td>
                            <td>{{ $review->rating }}/5</td>
                            <td>{{ trim(($review->title ? $review->title.' - ' : '').$review->comment) }}</td>
                            <td>{{ $exportStatusText }}</td>
                            <td>{{ $review->is_verified_purchase ? 'نعم' : 'لا' }}</td>
                            <td>{{ $review->is_featured ? 'نعم' : 'لا' }}</td>
                            <td>{{ $review->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $reviews, 'label' => 'نتيجة'])
        </div>
    </div>

    @if (hasPermission('online_store.reviews.reply'))
        <!-- Reply Modal -->
        <div class="modal fade" id="reviewReplyModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">الرد على التقييم</h5>
                        <button type="button" class="btn-close close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form id="reviewReplyForm">
                        <div class="modal-body">
                            <input type="hidden" id="reviewReplyId" name="review_id">
                            <div class="form-group">
                                <label for="reviewAdminReply">الرد</label>
                                <textarea class="form-control" id="reviewAdminReply" name="admin_reply" rows="4" required placeholder="اكتب ردك هنا..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">إرسال الرد</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
$(document).ready(function() {
    // طباعة تقييمات المنتجات
    $('#printReviewsBtn').click(function() {
        printReviewsReport();
    });

    // تصدير تقييمات المنتجات إلى Excel
    $('#exportReviewsExcel').click(function() {
        exportReviewsReport();
    });

    if ($.fn.select2) {
        $('.select2').select2({
            dir: 'rtl',
            language: {
                noResults: function() {
                    return "لا توجد نتائج";
                }
            },
            allowClear: true,
            width: '100%'
        });
    }

    // تحديد الكل
    $('#selectAll').change(function() {
        $('.review-checkbox').prop('checked', $(this).is(':checked'));
        toggleBulkActions();
    });

    $(document).on('change', '.review-checkbox', function() {
        toggleBulkActions();
    });

    function toggleBulkActions() {
        var $boxes = $('.review-checkbox');
        var checkedCount = $boxes.filter(':checked').length;

        // مزامنة مربّع «تحديد الكل» — نصف محدَّد عند اختيارٍ جزئي
        $('#selectAll')
            .prop('checked', $boxes.length > 0 && checkedCount === $boxes.length)
            .prop('indeterminate', checkedCount > 0 && checkedCount < $boxes.length);

        $('#bulkActionsBar').toggleClass('d-none', checkedCount === 0);
        $('.selected-count').text(checkedCount);
    }

    function getSelectedIds() {
        return $('.review-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }

    // الموافقة على المحدد
    $('#bulkApprove').click(function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;

        $.ajax({
            url: '{{ route("product_reviews.bulk-approve") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                ids: ids
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء العملية');
            }
        });
    });

    // رفض المحدد
    $('#bulkReject').click(function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;

        $.ajax({
            url: '{{ route("product_reviews.bulk-reject") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                ids: ids
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء العملية');
            }
        });
    });

    // حذف المحدد
    $('#bulkDelete').click(function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف التقييمات المحددة نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("product_reviews.bulk-delete") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            location.reload();
                        }
                    },
                    error: function() {
                        toastr.error('حدث خطأ أثناء الحذف');
                    }
                });
            }
        });
    });

    // اعتماد/رفض تقييم — مع مؤشّر تحميل يبقى حتى إعادة تحميل الصفحة
    function runReviewAction(url, workingText) {
        if (!url) return;
        Swal.fire({
            title: workingText,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            url: url,
            type: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { _token: '{{ csrf_token() }}' },
            success: function (response) {
                if (response && response.success) {
                    toastr.success(response.message || 'تمت العملية');
                    location.reload();               // المؤشّر يبقى ظاهراً حتى تُعاد الصفحة
                } else {
                    Swal.close();
                    toastr.error((response && response.message) || 'تعذّرت العملية');
                }
            },
            error: function (xhr) {
                Swal.close();
                var msg = xhr.responseJSON && xhr.responseJSON.message;
                toastr.error(msg || 'حدث خطأ أثناء العملية');
            }
        });
    }

    $(document).on('click', '.approve-review', function () {
        runReviewAction($(this).data('url'), 'جارٍ اعتماد التقييم…');
    });

    $(document).on('click', '.reject-review', function () {
        runReviewAction($(this).data('url'), 'جارٍ رفض التقييم…');
    });

    // تبديل المميز
    $('.toggle-featured').click(function() {
        var btn = $(this);
        var url = btn.data('url');
        var icon = btn.find('i');

        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    icon.toggleClass('text-warning text-muted');
                    toastr.success(response.message);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء العملية');
            }
        });
    });

    // فتح/إغلاق المودال بأي واجهة متاحة: هذا القالب يحمّل Bootstrap 5 (CDN)
    // و Bootstrap 4 معاً (داخل Vuexy vendors.min.js) — نجرّب الاثنين ثم عرضاً
    // يدوياً كملاذ أخير، فلا يتعطّل الفتح مهما كانت المكتبة الفعلية المحمّلة.
    // jQuery أولاً: إضافة مودال Bootstrap 4 (من Vuexy vendors.min.js) هي
    // المحمّلة فعلياً على هذه الصفحة. BS5 احتياطٌ إن غابت.
    function openModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        var jq = window.jQuery;
        if (jq && jq.fn && jq.fn.modal) { jq(el).modal('show'); return; }
        var B = window.bootstrap;
        if (B && B.Modal) {
            (B.Modal.getOrCreateInstance ? B.Modal.getOrCreateInstance(el) : new B.Modal(el)).show();
            return;
        }
        el.classList.add('show');
        el.style.display = 'block';
        document.body.classList.add('modal-open');
        if (!document.querySelector('.modal-backdrop')) {
            var bd = document.createElement('div');
            bd.className = 'modal-backdrop fade show';
            document.body.appendChild(bd);
        }
    }
    function closeModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        var jq = window.jQuery;
        if (jq && jq.fn && jq.fn.modal) { jq(el).modal('hide'); return; }
        var B = window.bootstrap;
        if (B && B.Modal) {
            var inst = B.Modal.getInstance ? B.Modal.getInstance(el)
                : (B.Modal.getOrCreateInstance ? B.Modal.getOrCreateInstance(el) : null);
            if (inst) { inst.hide(); return; }
        }
        el.classList.remove('show');
        el.style.display = 'none';
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
    }

    // إغلاق يدوي لأزرار «إلغاء / ✕» (سمات data-dismiss قد لا تعمل)
    $(document).on('click', '#reviewReplyModal [data-bs-dismiss="modal"], #reviewReplyModal [data-dismiss="modal"]', function () {
        closeModal('reviewReplyModal');
    });

    // فتح مودال الرد وتعبئته
    $(document).on('click', '.reply-review', function () {
        var $btn = $(this);
        $('#reviewReplyId').val($btn.data('id'));
        $('#reviewAdminReply').val($btn.data('current-reply') || '');
        openModal('reviewReplyModal');
    });

    // إرسال الرد
    $('#reviewReplyForm').submit(function(e) {
        e.preventDefault();
        var id = $('#reviewReplyId').val();
        var reply = $.trim($('#reviewAdminReply').val());
        if (!id || !reply) {
            toastr.warning('اكتب نص الرد أولاً');
            return;
        }

        var $submitBtn = $(this).find('button[type="submit"]').prop('disabled', true);

        $.ajax({
            url: '/{{ LaravelLocalization::getCurrentLocale() }}/online_store/reviews/' + id + '/reply',
            type: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                _token: '{{ csrf_token() }}',
                admin_reply: reply
            },
            success: function(response) {
                if (response && response.success) {
                    toastr.success(response.message || 'تم إضافة الرد بنجاح');
                    closeModal('reviewReplyModal');
                    location.reload();
                } else {
                    toastr.error((response && response.message) || 'تعذّر حفظ الرد');
                    $submitBtn.prop('disabled', false);
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && (xhr.responseJSON.message
                    || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]));
                toastr.error(msg || 'حدث خطأ أثناء إرسال الرد');
                $submitBtn.prop('disabled', false);
            }
        });
    });

    // تأكيد الحذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا التقييم نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});

// =====================================
// طباعة تقييمات المنتجات
// =====================================
function printReviewsReport() {
    printTable({
        tableSelector: '#reviewsExportTable tbody tr',
        title: 'تقرير تقييمات المنتجات',
        infoCards: [
            { label: 'إجمالي التقييمات', value: {{ $stats['total'] ?? 0 }} },
            { label: 'قيد الانتظار', value: {{ $stats['pending'] ?? 0 }} },
            { label: 'معتمد', value: {{ $stats['approved'] ?? 0 }}, class: 'text-success' },
            { label: 'مرفوض', value: {{ $stats['rejected'] ?? 0 }}, class: 'text-danger' },
            { label: 'متوسط التقييم', value: {{ $stats['average_rating'] ?? 0 }} }
        ],
        filterInfo: {
            @if (request('search'))
                'بحث': '{{ request('search') }}',
            @endif
            @if (request('status'))
                'الحالة': '{{ request('status') }}',
            @endif
            @if (request('rating'))
                'التقييم': '{{ request('rating') }} نجوم',
            @endif
            @if (request('verified'))
                'مشتري مؤكد': '{{ request('verified') == 'yes' ? 'نعم' : 'لا' }}',
            @endif
        },
        tableHeaders: ['#', 'المنتج', 'العميل', 'التقييم', 'التعليق', 'الحالة', 'مؤكد', 'مميز', 'التاريخ'],
        columnWidths: ['5%', '15%', '13%', '8%', '26%', '10%', '8%', '7%', '8%'],
        descriptionColumn: 5,
        windowWidth: 1400,
        windowHeight: 900
    });
}

// =====================================
// تصدير تقييمات المنتجات إلى Excel
// =====================================
function exportReviewsReport() {
    var tableData = [];

    @foreach ($reviews as $review)
        @php
            $exportReviewStatus2 = $review->status->value ?? $review->status;
            $exportStatusText2 = match ($exportReviewStatus2) {
                'pending' => 'قيد الانتظار',
                'approved' => 'معتمد',
                'rejected' => 'مرفوض',
                default => 'غير معروف',
            };
        @endphp
        tableData.push({
            values: [
                {{ $review->id }},
                @json($review->product->name ?? 'منتج محذوف'),
                @json($reviewClientName($review)),
                '{{ $review->rating }}/5',
                @json(trim(($review->title ? $review->title.' - ' : '').$review->comment)),
                @json($exportStatusText2),
                @json($review->is_verified_purchase ? 'نعم' : 'لا'),
                @json($review->is_featured ? 'نعم' : 'لا'),
                @json($review->created_at->format('Y-m-d'))
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        });
    @endforeach

    var config = {
        fileName: 'تقرير_تقييمات_المنتجات',
        mainTitle: 'تقرير تقييمات المنتجات',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            @if (request('search'))
                'بحث': '{{ request('search') }}',
            @endif
            @if (request('status'))
                'الحالة': '{{ request('status') }}',
            @endif
            @if (request('rating'))
                'التقييم': '{{ request('rating') }} نجوم',
            @endif
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        },

        statisticsCards: [
            {
                title: 'إجمالي التقييمات',
                value: '{{ $stats['total'] ?? 0 }}',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'قيد الانتظار',
                value: '{{ $stats['pending'] ?? 0 }}',
                titleColor: 'warning',
                valueColor: 'warning',
                backgroundColor: 'warningLight',
                borderColor: 'warning'
            },
            {
                title: 'معتمد',
                value: '{{ $stats['approved'] ?? 0 }}',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'مرفوض',
                value: '{{ $stats['rejected'] ?? 0 }}',
                titleColor: 'danger',
                valueColor: 'danger',
                backgroundColor: 'dangerLight',
                borderColor: 'danger'
            },
            {
                title: 'متوسط التقييم',
                value: '{{ $stats['average_rating'] ?? 0 }}',
                unit: ' / 5',
                titleColor: 'purple',
                valueColor: 'purple',
                backgroundColor: 'purpleLight',
                borderColor: 'purple'
            }
        ],

        tableTitle: 'قائمة التقييمات',
        tableHeaders: ['#', 'المنتج', 'العميل', 'التقييم', 'التعليق', 'الحالة', 'مؤكد', 'مميز', 'التاريخ'],
        columnWidths: [6, 22, 20, 10, 35, 14, 10, 10, 14],

        tableData: tableData,

        buttonSelector: '#exportReviewsExcel',
        successMessage: 'تم تصدير تقرير التقييمات بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
