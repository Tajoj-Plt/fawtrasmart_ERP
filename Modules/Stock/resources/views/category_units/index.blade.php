@extends('stock::master')

@section('title', 'وحدات الصنف: ' . $category->name)

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'وحدات القياس',
            'icon' => 'sliders',
            'sub' => 'الصنف: ' . $category->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأصناف' => route('category.index'),
                'الوحدات' => null,
            ],
            'actions' => '<a href="' . route('category.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-arrow-right"></i> رجوع</a>'
                . (hasPermission('stock.categories.create')
                    ? '<a href="' . route('category_units.create', $category->id) . '" class="btn btn-primary">'
                        . '<i class="feather icon-plus"></i> إضافة وحدة</a>'
                    : ''),
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @php
            $base = $units->firstWhere('is_base_unit', true);
            $others = $units->where('is_base_unit', false);
        @endphp

        @if ($units->count() && ! $base)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                <span>لا توجد وحدة أساسية — حدّد واحدة لتُنسب إليها بقية الوحدات وتُخزَّن بها الكميات.</span>
            </div>
        @endif

        @if ($units->count())
            {{-- ══════════ الوحدة الأساسية: المرجع ══════════ --}}
            @if ($base)
                <div class="stk-base-card">
                    <div class="stk-base-card__mark">
                        <i class="feather icon-anchor"></i>
                    </div>
                    <div class="stk-base-card__body">
                        <span class="stk-base-card__eyebrow">الوحدة الأساسية</span>
                        <h3 class="stk-base-card__name">{{ $base->unit->name ?? '—' }}</h3>
                        <p class="stk-base-card__hint">تُخزَّن بها كميات هذا الصنف، وتُنسب إليها كل وحدة أخرى</p>
                    </div>
                    @permission('stock.categories.edit')
                    <div class="stk-base-card__actions">
                        <a href="{{ route('category_units.edit', [$category->id, $base->id]) }}"
                           class="btn btn-outline-primary btn-sm">
                            <i class="feather icon-edit-2"></i> تعديل
                        </a>
                    </div>
                    @endpermission
                </div>
            @endif

            {{-- ══════════ الوحدات الفرعية: شبكة ══════════ --}}
            <div class="stk-page-head" style="margin-bottom:1rem">
                <div class="stk-page-head__titles">
                    <div>
                        <h2 class="stk-page-head__title" style="font-size:1.05rem">
                            الوحدات الفرعية
                            <span class="stk-count">{{ $others->count() }}</span>
                        </h2>
                    </div>
                </div>
            </div>

            @if ($others->count())
                <div class="stk-unit-grid">
                    @foreach ($others as $cu)
                        <div class="stk-unit-card">
                            <div class="stk-unit-card__head">
                                <span class="stk-unit-card__icon"><i class="feather icon-box"></i></span>
                                <span class="stk-unit-card__name">{{ $cu->unit->name ?? '—' }}</span>
                                <div class="stk-actions">
                                    @permission('stock.categories.edit')
                                    <a href="{{ route('category_units.edit', [$category->id, $cu->id]) }}"
                                       class="btn btn-outline-primary" title="تعديل" aria-label="تعديل">
                                        <i class="feather icon-edit-2"></i>
                                    </a>
                                    @endpermission
                                    @permission('stock.categories.delete')
                                    <button type="button" class="btn btn-outline-danger"
                                            data-toggle="modal" data-target="#del_{{ $cu->id }}"
                                            title="حذف" aria-label="حذف">
                                        <i class="feather icon-trash-2"></i>
                                    </button>
                                    @endpermission
                                </div>
                            </div>

                            {{-- معادلة التحويل مقروءة بدل رقمٍ مجرَّد في عمود --}}
                            <div class="stk-equation">
                                <span class="stk-equation__side">
                                    <b class="stk-num">1</b>
                                    <small>{{ $cu->unit->name ?? '' }}</small>
                                </span>
                                <span class="stk-equation__eq">=</span>
                                <span class="stk-equation__side stk-equation__side--to">
                                    <b class="stk-num">{{ rtrim(rtrim(number_format($cu->conversion_factor, 4, '.', ''), '0'), '.') }}</b>
                                    <small>{{ $base->unit->name ?? 'أساسية' }}</small>
                                </span>
                            </div>
                        </div>

                        @permission('stock.categories.delete')
                        <div class="modal fade" id="del_{{ $cu->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content text-right">
                                    <div class="modal-header">
                                        <h5 class="modal-title">إزالة الوحدة</h5>
                                        <button type="button" class="close" data-dismiss="modal"
                                                aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-0">
                                            ستُزال <strong>{{ $cu->unit->name ?? 'الوحدة' }}</strong> من هذا الصنف.
                                        </p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                                        <a href="{{ route('category_units.delete', [$category->id, $cu->id]) }}"
                                           class="btn btn-danger">
                                            <i class="feather icon-trash-2"></i> إزالة
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endpermission
                    @endforeach

                    {{-- بلاطة إضافة في نهاية الشبكة --}}
                    @permission('stock.categories.create')
                    <a href="{{ route('category_units.create', $category->id) }}" class="stk-unit-add">
                        <i class="feather icon-plus"></i>
                        <span>إضافة وحدة</span>
                    </a>
                    @endpermission
                </div>
            @else
                <div class="card">
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-box"></i></div>
                            <h5 class="stk-empty__title">لا وحدات فرعية</h5>
                            <p class="stk-empty__hint">أضف وحدةً فرعية لتحويل الكميات إليها ومنها</p>
                            @permission('stock.categories.create')
                            <a href="{{ route('category_units.create', $category->id) }}" class="btn btn-primary">
                                <i class="feather icon-plus"></i> إضافة وحدة
                            </a>
                            @endpermission
                        </div>
                    </div>
                </div>
            @endif

            {{-- الأساسية بلا مرجع: تُعرض منفردة بنفس البطاقة --}}
            @if (! $base && $others->count())
                <div class="alert alert-warning mt-3">
                    <i class="feather icon-info"></i>
                    <span>معاملات التحويل أعلاه بلا مرجع حتى تُحدَّد وحدة أساسية.</span>
                </div>
            @endif
        @else
            <div class="card">
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-sliders"></i></div>
                        <h5 class="stk-empty__title">لا وحدات لهذا الصنف</h5>
                        <p class="stk-empty__hint">
                            ابدأ بوحدةٍ أساسية — تُخزَّن بها الكميات وتُنسب إليها بقية الوحدات
                        </p>
                        @permission('stock.categories.create')
                        <a href="{{ route('category_units.create', $category->id) }}" class="btn btn-primary">
                            <i class="feather icon-plus"></i> إضافة الوحدة الأساسية
                        </a>
                        @endpermission
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
