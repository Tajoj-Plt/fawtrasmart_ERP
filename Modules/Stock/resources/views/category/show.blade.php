@extends('stock::master')

@section('title', 'الصنف: ' . $category->name)

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => $category->name,
            'icon' => 'tag',
            'sub' => $category->code ? 'الكود: ' . $category->code : 'تفاصيل الصنف ووحداته ومنتجاته',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأصناف' => route('category.index'),
                $category->name => null,
            ],
            'actions' => '<a href="' . route('category.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-arrow-right"></i> رجوع</a>'
                . '<a href="' . route('category_units.index', $category->id) . '" class="btn btn-outline-info">'
                . '<i class="feather icon-sliders"></i> الوحدات</a>'
                . (hasPermission('stock.categories.edit')
                    ? '<a href="' . route('category.edit', $category->id) . '" class="btn btn-primary">'
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
                        @if ($category->attachments)
                            <img src="{{ asset($category->attachments) }}" alt="{{ $category->name }}"
                                 class="stk-avatar-lg mb-3">
                        @else
                            <span class="stk-avatar-lg mb-3"><i class="feather icon-tag"></i></span>
                        @endif

                        <h4 class="mb-1">{{ $category->name }}</h4>

                        <div class="d-flex justify-content-center flex-wrap" style="gap:.4rem">
                            @if ($category->code)
                                <span class="badge badge-secondary stk-num">{{ $category->code }}</span>
                            @endif
                            <span class="stk-state stk-state--{{ $category->is_active ? 'on' : 'off' }}">
                                {{ $category->is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </div>

                        @if ($category->discretion)
                            <p class="stk-help mt-3 mb-0">{{ $category->discretion }}</p>
                        @endif
                    </div>

                    <div class="stk-metrics">
                        <div class="stk-metric">
                            <span class="stk-metric__value">{{ $category->products->count() }}</span>
                            <span class="stk-metric__label">منتج</span>
                        </div>
                        <div class="stk-metric">
                            <span class="stk-metric__value">{{ $category->categoryUnits->count() }}</span>
                            <span class="stk-metric__label">وحدة</span>
                        </div>
                        <div class="stk-metric">
                            <span class="stk-metric__value">{{ $category->children->count() ?? 0 }}</span>
                            <span class="stk-metric__label">صنف فرعي</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ التفاصيل ══════════ --}}
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-info"></i> البيانات</h4>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>مجموعة المنتجات</dt>
                                <dd>
                                    @if ($category->productGroup)
                                        <a href="{{ route('product_groups.edit', $category->productGroup->id) }}">
                                            {{ $category->productGroup->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الصنف الرئيسي</dt>
                                <dd>
                                    @if ($category->parent)
                                        <a href="{{ route('category.show', $category->parent->id) }}">
                                            {{ $category->parent->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">صنف رئيسي</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>أُنشئ في</dt>
                                <dd class="stk-num">{{ optional($category->created_at)->format('Y-m-d') ?? '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>آخر تحديث</dt>
                                <dd class="stk-num">{{ optional($category->updated_at)->format('Y-m-d') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- ══════════ الوحدات ══════════ --}}
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-sliders"></i> وحدات القياس
                            <span class="stk-count">{{ $category->categoryUnits->count() }}</span>
                        </h6>
                        <a href="{{ route('category_units.index', $category->id) }}"
                           class="btn btn-sm btn-outline-primary">
                            <i class="feather icon-settings"></i> إدارة
                        </a>
                    </div>

                    @if ($category->categoryUnits->count())
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>الوحدة</th>
                                        <th class="text-center">معامل التحويل</th>
                                        <th class="text-center">النوع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($category->categoryUnits as $cu)
                                        <tr>
                                            <td>
                                                <div class="stk-media">
                                                    <span class="stk-media__thumb"><i class="feather icon-box"></i></span>
                                                    <span class="stk-media__body">
                                                        <span class="stk-media__title">{{ $cu->unit->name ?? '—' }}</span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-center stk-num">{{ $cu->conversion_factor }}</td>
                                            <td class="text-center">
                                                @if ($cu->is_base_unit)
                                                    <span class="stk-state stk-state--on">أساسية</span>
                                                @else
                                                    <span class="text-muted">فرعية</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="card-body">
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-sliders"></i></div>
                                <h5 class="stk-empty__title">لا وحدات مرتبطة</h5>
                                <p class="stk-empty__hint">أضف وحدةً أساسية على الأقل ليمكن تخزين الكميات</p>
                                <a href="{{ route('category_units.index', $category->id) }}" class="btn btn-primary">
                                    <i class="feather icon-plus"></i> إضافة وحدة
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
