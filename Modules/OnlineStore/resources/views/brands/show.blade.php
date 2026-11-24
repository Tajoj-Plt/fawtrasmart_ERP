@extends('onlinestore::master')

@section('title', 'تفاصيل العلامة التجارية')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.brands.edit'))
                <a href="{{ route('brands.edit', $brand) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل العلامة التجارية',
            'icon' => 'award',
            'sub' => $brand->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'العلامات التجارية' => route('brands.index'),
                $brand->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-award"></i></span>
                <span class="stk-metric__label">العلامة</span>
                <span class="stk-metric__value">{{ $brand->name }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">عدد المنتجات</span>
                <span class="stk-metric__value stk-num">{{ number_format($brand->products_count ?? 0) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $brand->is_active ? 'check-circle' : 'x-circle' }}"></i></span>
                <span class="stk-metric__label">الحالة</span>
                <span class="stk-metric__value">{{ $brand->is_active ? 'نشطة' : 'غير نشطة' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-star"></i></span>
                <span class="stk-metric__label">مميزة</span>
                <span class="stk-metric__value">{{ $brand->is_featured ? 'نعم' : 'لا' }}</span>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات العلامة</h6>
            </div>

            <div class="card-body">
                <div class="row align-items-start">
                    <div class="col-md-3 text-center mb-2">
                        @if ($brand->logo)
                            <img src="{{ asset($brand->logo) }}" alt="{{ $brand->name }}" class="img-fluid rounded" style="max-height: 140px;">
                        @else
                            <i class="feather icon-image text-muted" style="font-size: 64px;"></i>
                            <p class="text-muted mb-0">بلا شعار</p>
                        @endif
                    </div>
                    <div class="col-md-9">
                        <dl class="stk-def mb-0">
                            <div class="stk-def__row">
                                <dt>الاسم</dt>
                                <dd><strong>{{ $brand->name }}</strong></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الرابط (Slug)</dt>
                                <dd dir="ltr" class="text-start">{{ $brand->slug ?: '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الموقع الإلكتروني</dt>
                                <dd dir="ltr" class="text-start">
                                    @if ($brand->website)
                                        <a href="{{ $brand->website }}" target="_blank" rel="noopener noreferrer">{{ $brand->website }}</a>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الترتيب</dt>
                                <dd class="stk-num">{{ $brand->sort_order ?? 0 }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الوصف</dt>
                                <dd>{{ $brand->description ?: '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>عنوان SEO</dt>
                                <dd>{{ $brand->meta_title ?: '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>وصف SEO</dt>
                                <dd>{{ $brand->meta_description ?: '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإضافة</dt>
                                <dd class="stk-num">{{ $brand->created_at?->format('Y-m-d') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        @if (($brand->products_count ?? 0) > 0 && hasPermission('online_store.products.view'))
            <div class="card">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-1">
                    <span>لهذه العلامة {{ number_format($brand->products_count) }} منتج — لا تُحذف ما دامت المنتجات مرتبطة بها.</span>
                    <a href="{{ route('store_products.index', ['brand_id' => $brand->id]) }}" class="btn btn-outline-primary btn-sm">
                        <i class="feather icon-package"></i> عرض منتجاتها
                    </a>
                </div>
            </div>
        @endif
    </div>
@endsection
