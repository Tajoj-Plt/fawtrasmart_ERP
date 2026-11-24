@extends('manufacturing::master')

@section('title', 'إعدادات التصنيع العامة')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'الإعدادات العامة',
            'icon' => 'sliders',
            'sub' => 'قواعد أوامر التصنيع',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات التصنيع' => route('Manufacturing.settings.index'),
                'عام' => null,
            ],
            'actions' => '<button type="submit" form="general_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الإعدادات</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        <form id="general_form" action="{{ route('Manufacturing.general_settings.update') }}" method="POST">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-settings"></i> أوامر التصنيع</h4>
                </div>
                <div class="card-body">
                    <label class="stk-switch mb-0">
                        <input type="checkbox" name="quantity_exceeded" value="1"
                               {{ optional($general_settings)->quantity_exceeded == 1 ? 'checked' : '' }}>
                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                        <span class="stk-switch__label">
                            <span class="stk-switch__title">السماح بتجاوز الكمية المطلوبة</span>
                            <span class="stk-switch__hint">
                                مفعَّلاً: يُقبل عند إتمام الأمر إنتاجٌ أكبر من كميته.
                                معطَّلاً: يُرفض إتمامُ الأمر بكميةٍ فعليةٍ تزيد على المطلوبة.
                            </span>
                        </span>
                    </label>

                    <label class="stk-switch mb-0 mt-2">
                        <input type="checkbox" name="reserve_materials_on_order" value="1"
                               {{ optional($general_settings)->reserve_materials_on_order === false ? '' : 'checked' }}>
                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                        <span class="stk-switch__label">
                            <span class="stk-switch__title">حجز المواد عند إنشاء الأمر</span>
                            <span class="stk-switch__hint">
                                يُخصم المحجوز من الكمية المتاحة، فلا يعتمد أمران على المواد نفسها.
                                المتاح = الرصيد الفعلي − المحجوز
                            </span>
                        </span>
                    </label>
                </div>

                {{-- ══════════ نموذج أمر التصنيع ══════════ --}}
                @php
                    $ofs = \Modules\Manufacturing\Support\OrderFormSettings::class;
                @endphp

                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-layout"></i> حقول نموذج أمر التصنيع</h4>
                </div>
                <div class="card-body">
                    <p class="stk-help mb-2">
                        اختر لكل حقلٍ: هل يظهر في شاشة الأمر، وهل هو إلزامي. إخفاءُ ما لا تحتاجه يجعل إدخال الأمر أسرع.
                    </p>

                    <div class="row">
                        @foreach ($ofs::FIELDS as $fieldKey => [$fieldLabel])
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="of_{{ $fieldKey }}">{{ $fieldLabel }}</label>
                                    <select id="of_{{ $fieldKey }}" name="order_fields[{{ $fieldKey }}]" class="form-control">
                                        @foreach ($ofs::MODES as $mode => $modeLabel)
                                            <option value="{{ $mode }}" @selected($ofs::field($fieldKey) === $mode)>{{ $modeLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <label class="d-block mt-1 mb-1"><strong>أقسام النموذج الظاهرة</strong></label>
                    <span class="stk-help d-block mb-1">المواد الخام تظهر دائماً. القسم المخفيّ يظهر مع ذلك في أمرٍ محفوظٍ فيه بنود.</span>

                    <div class="row">
                        @foreach ($ofs::SECTIONS as $sectionKey => $sectionLabel)
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label class="stk-switch mb-1">
                                    <input type="checkbox" name="order_sections[{{ $sectionKey }}]" value="1"
                                           {{ $ofs::section($sectionKey) ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">{{ $sectionLabel }}</span>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="stk-form-foot">
                    {{-- كان زرّ «إلغاء» رابطاً فارغاً (href="") يعيد تحميل الصفحة --}}
                    <a href="{{ route('Manufacturing.settings.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ الإعدادات
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
