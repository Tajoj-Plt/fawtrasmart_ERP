@extends('onlinestore::master')

@section('title', 'تفاصيل التقييم')

@section('content')
    @php
        $reviewStatus = $productReview->status->value ?? $productReview->status;
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

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('product_reviews.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة للقائمة
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل التقييم #' . $productReview->id,
            'icon' => 'message-square',
            'sub' => $statusText . ($productReview->is_verified_purchase ? ' — مشتري مؤكد' : '') . ($productReview->is_featured ? ' — مميز' : ''),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'تقييمات المنتجات' => route('product_reviews.index'),
                'تفاصيل التقييم #' . $productReview->id => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="row g-3">
            <!-- معلومات التقييم -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="stk-head-plain">
                        <h6 class="stk-table-head__title"><i class="feather icon-message-square"></i> محتوى التقييم</h6>
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <span class="stk-state {{ $statusClass }}">{{ $statusText }}</span>
                            @if ($productReview->is_verified_purchase)
                                <span class="badge badge-light-info"><i class="feather icon-check"></i> مشتري مؤكد</span>
                            @endif
                            @if ($productReview->is_featured)
                                <span class="badge badge-light-warning"><i class="feather icon-star"></i> مميز</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="stk-rating mb-2">
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="feather icon-star {{ $i <= $productReview->rating ? 'text-warning' : 'text-muted' }}"></i>
                            @endfor
                            <span class="text-muted mr-1">({{ $productReview->rating }}/5)</span>
                        </div>

                        <blockquote class="stk-quote">
                            <i class="feather icon-message-square stk-quote__mark"></i>
                            <p class="stk-quote__text">
                                @if ($productReview->title)<strong class="d-block mb-1">{{ $productReview->title }}</strong>@endif
                                {{ $productReview->comment }}
                            </p>
                        </blockquote>

                        <div class="stk-meta-row">
                            <span><i class="feather icon-user"></i> {{ $productReview->client ? $productReview->client->display_name : 'عميل محذوف' }}</span>
                            <span class="stk-num"><i class="feather icon-calendar"></i> {{ $productReview->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                </div>

                <!-- رد الإدارة -->
                <div class="card">
                    <div class="stk-head-plain">
                        <h6 class="stk-table-head__title"><i class="feather icon-message-circle"></i> رد الإدارة</h6>
                    </div>
                    <div class="card-body">
                        @if ($productReview->admin_reply)
                            <div class="stk-reply-panel">
                                <p class="stk-reply-panel__text mb-2">{{ $productReview->admin_reply }}</p>
                                <div class="stk-reply-panel__by">
                                    <i class="feather icon-check-circle"></i>
                                    {{ $productReview->repliedBy->name ?? 'مسؤول النظام' }}
                                    <span class="mx-1">·</span>
                                    <i class="feather icon-clock"></i>
                                    {{ $productReview->replied_at ? $productReview->replied_at->format('Y-m-d H:i') : '' }}
                                </div>
                            </div>
                            @if (hasPermission('online_store.reviews.reply'))
                                <form action="{{ route('product_reviews.remove-reply', $productReview) }}" method="POST" class="mt-3">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="feather icon-trash-2"></i> حذف الرد
                                    </button>
                                </form>
                            @endif
                        @elseif (hasPermission('online_store.reviews.reply'))
                            <form action="{{ route('product_reviews.reply', $productReview) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label for="admin_reply" class="stk-req">اكتب ردك</label>
                                    <textarea class="form-control" id="admin_reply" name="admin_reply" rows="4" required placeholder="اكتب رد الإدارة هنا..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-send"></i> إرسال الرد
                                </button>
                            </form>
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-message-circle"></i></span>
                                <h6 class="stk-empty__title">لا يوجد رد بعد</h6>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <!-- معلومات المنتج -->
                <div class="card">
                    <div class="stk-head-plain">
                        <h6 class="stk-table-head__title"><i class="feather icon-box"></i> المنتج</h6>
                    </div>
                    <div class="card-body text-center">
                        @if ($productReview->product)
                            <div class="stk-detail-img mb-2">
                                @php
                                    $reviewProductImages = json_decode((string) $productReview->product->images, true);
                                    $reviewProductThumb = is_array($reviewProductImages) && ! empty($reviewProductImages[0]) ? $reviewProductImages[0] : null;
                                @endphp
                                @if ($reviewProductThumb)
                                    <img src="{{ asset($reviewProductThumb) }}" alt="{{ $productReview->product->name }}">
                                @else
                                    <i class="feather icon-image"></i>
                                @endif
                            </div>
                            <h6 class="mb-2">{{ $productReview->product->name }}</h6>
                            @if (hasPermission('online_store.reviews.view'))
                                <a href="{{ route('product_reviews.product', $productReview->product) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="feather icon-external-link"></i> عرض تقييمات المنتج
                                </a>
                            @endif
                        @else
                            <p class="text-muted mb-0">المنتج محذوف</p>
                        @endif
                    </div>
                </div>

                <!-- معلومات العميل -->
                <div class="card">
                    <div class="stk-head-plain">
                        <h6 class="stk-table-head__title"><i class="feather icon-user"></i> العميل</h6>
                    </div>
                    <div class="card-body">
                        @if ($productReview->client)
                            <dl class="stk-def mb-0">
                                <div class="stk-def__row">
                                    <dt>الاسم</dt>
                                    <dd>{{ $productReview->client->display_name }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>البريد</dt>
                                    <dd class="text-break">{{ $productReview->client->email ?: '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الهاتف</dt>
                                    <dd class="stk-num">{{ $productReview->client->phone ?? 'غير محدد' }}</dd>
                                </div>
                            </dl>
                        @else
                            <p class="text-muted mb-0">العميل محذوف</p>
                        @endif
                    </div>
                </div>

                <!-- معلومات الفاتورة -->
                @if ($productReview->invoice)
                    <div class="card">
                        <div class="stk-head-plain">
                            <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> الفاتورة</h6>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def mb-0">
                                <div class="stk-def__row">
                                    <dt>رقم الفاتورة</dt>
                                    <dd class="stk-num">{{ $productReview->invoice->invoice_number ?? $productReview->invoice->id }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>التاريخ</dt>
                                    <dd class="stk-num">{{ $productReview->invoice->created_at->format('Y-m-d') }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif

                <!-- الإجراءات -->
                @if (hasPermission(['online_store.reviews.moderate', 'online_store.reviews.toggle_featured', 'online_store.reviews.delete']))
                    <div class="card">
                        <div class="stk-head-plain">
                            <h6 class="stk-table-head__title"><i class="feather icon-settings"></i> الإجراءات</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                @if (hasPermission('online_store.reviews.moderate'))
                                    @if ($reviewStatus !== 'approved')
                                        <button class="btn btn-outline-success w-100 approve-btn" data-url="{{ route('product_reviews.approve', $productReview) }}">
                                            <i class="feather icon-check-circle"></i> اعتماد التقييم
                                        </button>
                                    @endif

                                    @if ($reviewStatus !== 'rejected')
                                        <button class="btn btn-outline-warning w-100 reject-btn" data-url="{{ route('product_reviews.reject', $productReview) }}">
                                            <i class="feather icon-x-circle"></i> رفض التقييم
                                        </button>
                                    @endif
                                @endif

                                @if (hasPermission('online_store.reviews.toggle_featured'))
                                    <button class="btn btn-outline-warning w-100 toggle-featured-btn" data-url="{{ route('product_reviews.toggle-featured', $productReview) }}">
                                        <i class="feather icon-star"></i>
                                        {{ $productReview->is_featured ? 'إلغاء التمييز' : 'تمييز التقييم' }}
                                    </button>
                                @endif

                                @if (hasPermission('online_store.reviews.delete'))
                                    <form action="{{ route('product_reviews.destroy', $productReview) }}" method="POST" class="delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger w-100">
                                            <i class="feather icon-trash-2"></i> حذف التقييم
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // اعتماد التقييم
    $('.approve-btn').click(function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
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

    // رفض التقييم
    $('.reject-btn').click(function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
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

    // تبديل التمييز
    $('.toggle-featured-btn').click(function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
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
</script>
@endsection
