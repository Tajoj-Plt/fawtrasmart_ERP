@extends('stock::master')

@section('title', 'تعديل وحدة الصنف')

@section('content')
    <div class="content-body">

        @php
            $base = $category->categoryUnits->firstWhere('is_base_unit', true);
        @endphp

        @include('stock::components.page-head', [
            'title' => 'تعديل الوحدة',
            'icon' => 'edit-2',
            'sub' => 'الصنف: ' . $category->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأصناف' => route('category.index'),
                'الوحدات' => route('category_units.index', $category->id),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('category_units.index', $category->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="unit_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="unit_form" method="POST"
              action="{{ route('category_units.update', [$category->id, $categoryUnit->id]) }}">
            @csrf
            @method('PUT')

            <div class="row justify-content-center">
                <div class="col-lg-7 col-md-9 col-12">

                    {{-- ══════════ معاينة المعادلة ══════════
                         تتحدّث مع الكتابة، فيرى المستخدم ما يبنيه قبل الحفظ --}}
                    <div class="stk-preview" id="preview_box">
                        <span class="stk-preview__label">المعادلة الناتجة</span>
                        <div class="stk-equation stk-equation--lg">
                            <span class="stk-equation__side">
                                <b class="stk-num">1</b>
                                <small id="pv_from">الوحدة</small>
                            </span>
                            <span class="stk-equation__eq">=</span>
                            <span class="stk-equation__side stk-equation__side--to">
                                <b class="stk-num" id="pv_factor">1</b>
                                <small id="pv_to">{{ $base->unit->name ?? 'أساسية' }}</small>
                            </span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">

                            <div class="form-group">
                                <label>الوحدة</label>
                                {{-- لا تُبدَّل بعد الربط: أرصدة المنتجات محسوبة عليها --}}
                                <div class="stk-unit mb-0">
                                    <span class="stk-unit__icon"><i class="feather icon-box"></i></span>
                                    <span class="stk-unit__body">
                                        <span class="stk-unit__name">{{ $categoryUnit->unit->name ?? '—' }}</span>
                                        <span class="stk-unit__meta">احذف الوحدة وأضف غيرها لتبديلها</span>
                                    </span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="conversion_factor" class="stk-req">معامل التحويل</label>
                                <input type="number" id="conversion_factor" name="conversion_factor" required
                                       class="form-control form-control-lg text-center stk-num @error('conversion_factor') is-invalid @enderror"
                                       step="0.0001" min="0.0001" value="{{ old('conversion_factor', $categoryUnit->conversion_factor) }}">
                                @error('conversion_factor')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <span class="stk-help text-center d-block">
                                    @if ($base)
                                        كم وحدةً من «{{ $base->unit->name ?? 'الأساسية' }}» تساوي وحدةً واحدة من هذه؟
                                    @else
                                        لا وحدة أساسية بعد — حدّد هذه كأساسية ليكون معاملها 1
                                    @endif
                                </span>
                            </div>

                            <div class="stk-choice">
                                <label class="stk-switch mb-0">
                                    <input type="checkbox" id="is_base_unit" name="is_base_unit" value="1"
                                           {{ old('is_base_unit', $categoryUnit->is_base_unit) ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">اجعلها الوحدة الأساسية</span>
                                        <span class="stk-switch__hint">
                                            تُخزَّن بها الكميات ومعاملها 1 دائماً
                                            @if ($base)
                                                — ستحلّ محلّ «{{ $base->unit->name ?? 'الحالية' }}»
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="stk-form-foot">
                            <a href="{{ route('category_units.index', $category->id) }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-check"></i> حفظ التعديلات
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            const $factor = $('#conversion_factor'), $base = $('#is_base_unit');
            const baseName = @json($base->unit->name ?? 'أساسية');

            function paint() {
                const isBase = $base.is(':checked');
                const name = @json($categoryUnit->unit->name ?? 'الوحدة');

                $('#pv_from').text(name);
                $('#pv_factor').text(isBase ? '1' : ($factor.val() || '—'));
                $('#pv_to').text(isBase ? name : baseName);
                $('#preview_box').toggleClass('is-base', isBase);

                $factor.prop('readonly', isBase);
                if (isBase) $factor.val('1');
            }

            $factor.on('input', paint);
            $base.on('change', paint);
            paint();
        });
    </script>
@endsection
