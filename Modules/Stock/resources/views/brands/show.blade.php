@extends('stock::master')

@section('title', 'البراند: ' . $brand->name)

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => $brand->name,
            'icon' => 'award',
            'sub' => 'تفاصيل العلامة التجارية',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'البراندات' => route('stock.brands.index'),
                $brand->name => null,
            ],
            'actions' => '<a href="' . route('stock.brands.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-arrow-right"></i> رجوع</a>'
                . (hasPermission('stock.brands.edit')
                    ? '<a href="' . route('stock.brands.edit', $brand->id) . '" class="btn btn-primary">'
                        . '<i class="feather icon-edit-2"></i> تعديل</a>'
                    : ''),
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="row">
            {{-- ══════════ بطاقة الهوية ══════════ --}}
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-link"></i> الرابط والأرشفة</h4>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>المعرّف اللطيف</dt>
                                <dd dir="ltr">{{ $brand->slug ?: '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الموقع</dt>
                                <dd>
                                    @if ($brand->website)
                                        <a href="{{ $brand->website }}" target="_blank" rel="noopener" dir="ltr">{{ $brand->website }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>ترتيب العرض</dt>
                                <dd class="stk-num">{{ $brand->sort_order ?? 0 }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>عنوان الأرشفة</dt>
                                <dd>{{ $brand->meta_title ?: '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>وصف الأرشفة</dt>
                                <dd>{{ $brand->meta_description ?: '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- ══════════ المنتجات ══════════ --}}
            <div class="col-lg-8 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-package"></i> منتجات البراند
                            <span class="stk-count">{{ $products->total() ?? $products->count() }}</span>
                        </h6>
                    </div>

                    @if ($products->count())
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>الكود</th>
                                        <th class="text-center">الكمية</th>
                                        <th class="text-center">سعر البيع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($products as $product)
                                        <tr>
                                            <td>
                                                <div class="stk-media">
                                                    @if ($product->images)
                                                        <img src="{{ asset('assets/uploads/product/' . $product->images) }}"
                                                             alt="{{ $product->name }}" class="stk-media__thumb">
                                                    @else
                                                        <span class="stk-media__thumb"><i class="feather icon-package"></i></span>
                                                    @endif
                                                    <span class="stk-media__body">
                                                        <span class="stk-media__title">{{ $product->name }}</span>
                                                        @if ($product->category)
                                                            <span class="stk-media__sub">{{ $product->category->name }}</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            </td>

                                            <td>
                                                @if ($product->serial_number)
                                                    <span class="badge badge-secondary stk-num">{{ $product->serial_number }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>

                                            <td class="text-center stk-num">
                                                {{ number_format($product->productDetails->sum('quantity') ?? 0) }}
                                            </td>

                                            <td class="text-center stk-num">@money($product->sale_price ?? 0)</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($products instanceof \Illuminate\Pagination\LengthAwarePaginator && $products->hasPages())
                            <div class="stk-table-foot">
                                <p class="stk-table-foot__count">
                                    عرض {{ $products->firstItem() }}–{{ $products->lastItem() }} من {{ $products->total() }}
                                </p>
                                {{ $products->withQueryString()->links() }}
                            </div>
                        @endif
                    @else
                        <div class="card-body">
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                                <h5 class="stk-empty__title">لا منتجات لهذا البراند</h5>
                                <p class="stk-empty__hint mb-0">اربط منتجاً بهذا البراند ليظهر هنا</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
