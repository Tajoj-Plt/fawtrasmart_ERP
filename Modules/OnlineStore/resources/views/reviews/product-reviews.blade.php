@extends('onlinestore::master')

@section('title', 'تقييمات المنتج: ' . $product->name)

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('product_reviews.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة للقائمة
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تقييمات المنتج',
            'icon' => 'message-square',
            'sub' => $product->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'تقييمات المنتجات' => route('product_reviews.index'),
                $product->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- معلومات المنتج -->
        <div class="card mb-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        @php
                            $reviewProductImages = json_decode((string) $product->images, true);
                            $reviewProductThumb = is_array($reviewProductImages) && ! empty($reviewProductImages[0]) ? $reviewProductImages[0] : null;
                        @endphp
                        @if ($reviewProductThumb)
                            <img src="{{ asset($reviewProductThumb) }}" alt="{{ $product->name }}" class="img-fluid rounded" style="max-height: 120px;">
                        @else
                            <div>
                                <i class="feather icon-image" style="font-size: 48px;"></i>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-7">
                        <h5>{{ $product->name }}</h5>
                        <p class="text-muted mb-0">{{ Str::limit($product->description, 150) }}</p>
                    </div>
                    <div class="col-md-3 text-center">
                        <div>
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="feather icon-star {{ $i <= $stats['average'] ? 'text-warning' : 'text-muted' }}" style="font-size: 20px;"></i>
                            @endfor
                        </div>
                        <h5 class="mb-0 stk-num">{{ $stats['average'] }}/5</h5>
                        <small class="text-muted">{{ $stats['total'] }} تقييم</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-message-square"></i></span>
                <span class="stk-metric__label">إجمالي التقييمات</span>
                <span class="stk-metric__value">{{ $stats['total'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">معتمد</span>
                <span class="stk-metric__value">{{ $stats['approved'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-star"></i></span>
                <span class="stk-metric__label">متوسط التقييم</span>
                <span class="stk-metric__value">{{ $stats['average'] }}</span>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> التقييمات</h6>
                <span class="badge badge-light-primary">{{ $reviews->total() }} تقييم</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>العميل</th>
                            <th>التقييم</th>
                            <th>التعليق</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center">التاريخ</th>
                            @if (hasPermission('online_store.reviews.view'))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
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
                            <tr>
                                <td class="text-center stk-num">{{ $review->id }}</td>
                                <td>
                                    <span class="stk-media__title">{{ $review->client ? $review->client->display_name : 'عميل محذوف' }}</span>
                                    <span class="stk-media__sub d-block">{{ $review->client->email ?? '' }}</span>
                                </td>
                                <td>
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="feather icon-star {{ $i <= $review->rating ? 'text-warning' : 'text-muted' }}"></i>
                                    @endfor
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
                                    <small>{{ $review->created_at->format('Y-m-d') }}</small>
                                </td>
                                @if (hasPermission('online_store.reviews.view'))
                                    <td class="text-center">
                                        <a href="{{ route('product_reviews.show', $review) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="feather icon-eye"></i>
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ hasPermission('online_store.reviews.view') ? 7 : 6 }}">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                        <h6 class="stk-empty__title">لا توجد تقييمات</h6>
                                        <p class="stk-empty__hint">لم يتم تقييم هذا المنتج بعد.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $reviews, 'label' => 'نتيجة'])
        </div>
    </div>
@endsection
