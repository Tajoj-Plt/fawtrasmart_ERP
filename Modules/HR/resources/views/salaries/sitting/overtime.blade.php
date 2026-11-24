@extends('hr::master')

@section('title', 'إعدادات الأجر الإضافي')

@section('content')
    @php
        /** أيقونةُ شرحٍ تُقرأ عند التمرير (`stk-tip`) — ما يفعله الحقل وأين يسري. */
        $tip = fn (string $text) => '<i class="feather icon-help-circle stk-tip" title="'.e($text).'"></i>';

        // الخانةُ المطفأة لا تُرسَل — فبعد فشل التحقّق يُقرأ ما أُرسل لا المحفوظ
        $submitted = session()->hasOldInput();
        $on = fn (string $name, bool $current) => $submitted ? (bool) old($name) : $current;
        $s = $overtimeSettings;
    @endphp

    @section('page-actions')
        <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> الإعدادات
        </a>
        <button type="submit" form="overtimeSettingsForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الإعدادات
        </button>
    @endsection

    @include('stock::components.page-head', [
        'title' => 'إعدادات الأجر الإضافي',
        'icon' => 'clock',
        'crumbs' => ['الإعدادات' => route('SalarySittings.index'), 'الأجر الإضافي' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        @include('hr::components.guide', [
            'key' => 'hr-payroll-settings.overtime',
            'title' => 'كيف تتحوّل دقائق الحضور الإضافية إلى أجر؟',
            'lead' => 'عند صرف كلّ راتبٍ تُجمع دقائقُ العمل الإضافي من سجلّ الحضور لفترة الصرفة، وتُحوَّل إلى بند استحقاقٍ في القسيمة يُقيَّد مع الراتب على «مصروف الرواتب».',
            'blocks' => [
                'المعادلة' => [
                    'المبلغ = (الدقائق ÷ 60) × سعر الساعة × المضاعف.',
                    'سعرُ الساعة ثابتٌ إن ضُبط، وإلّا = الأساسي ÷ ساعات الشهر.',
                    'اليومُ المعلَّم عطلةً أو راحةً أسبوعية في الحضور يأخذ مضاعفَ العطلة وحدَّها.',
                ],
                'الترتيب' => [
                    'الحدُّ اليومي يُطبَّق على كلّ يوم، ثمّ حدُّ الفترة على مجموعها (تُقصّ الدقائق بالنسبة).',
                ],
            ],
            'facts' => [
                'معطّلٌ افتراضياً',
                'يسري على ما يُصرف بعد الحفظ',
            ],
        ])

        <form id="overtimeSettingsForm" action="{{ route('SalarySittings.overtime.update') }}" method="POST">
            @csrf

            <div class="card">
                <div class="card-body">

                    {{-- ═══════════ التفعيل ═══════════ --}}
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-power"></i>
                            <h6 class="stk-section__title">التفعيل والاعتماد</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="stk-switch" for="overtime_enabled">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">
                                            احتساب الأجر الإضافي عند صرف الراتب
                                            {!! $tip('يُقرأ عند صرف كلّ صرفة: مفعّلاً يُضاف بندُ «أجر إضافي» إلى القسيمة من دقائق الحضور الإضافية. مطفأً: لا يُضاف شيء، ويُحذف البندُ المولَّد من صرفةٍ لم تُصرف بعد.') !!}
                                        </span>
                                        <span class="stk-switch__hint">معطّلٌ افتراضياً كي لا تُضاف مبالغ إلى مسيراتٍ قائمة بلا قرار.</span>
                                    </span>
                                    <input type="checkbox" id="overtime_enabled" name="overtime_enabled" value="1"
                                           @checked($on('overtime_enabled', (bool) ($s['overtime_enabled'] ?? false)))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="stk-switch" for="overtime_requires_approval">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">
                                            لا يُصرف إضافيٌّ إلا بطلبٍ معتمد
                                            {!! $tip('يُقرأ عند الصرف: دقائقُ كلّ يومٍ تُقصّ بما اعتُمد له في «طلبات العمل الإضافي». الاعتمادُ سقفٌ لا منحة — من اعتُمدت له أربعُ ساعاتٍ وعمل ساعتين يُصرف له ساعتان.') !!}
                                        </span>
                                        <span class="stk-switch__hint">
                                            الدقائقُ تُسجَّل على أيّ حال — هذا يشترط قراراً قبل صرفها.
                                            @if (hasPermission('overtime.approve'))
                                                <a href="{{ route('hr.overtime-approvals.index') }}">اعتماد الطلبات</a>
                                            @endif
                                        </span>
                                    </span>
                                    <input type="checkbox" id="overtime_requires_approval" name="overtime_requires_approval" value="1"
                                           @checked($on('overtime_requires_approval', (bool) ($s['overtime_requires_approval'] ?? false)))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ سعر الساعة ═══════════ --}}
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-dollar-sign"></i>
                            <h6 class="stk-section__title">سعر الساعة وحدّ الفترة</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="overtime_monthly_hours" class="stk-req">
                                        ساعات الشهر
                                        {!! $tip('سعرُ الساعة = الراتب الأساسي في القسيمة ÷ هذه الساعات، ما لم يُضبط سعرٌ ثابت. 240 = 30 يوماً × 8 ساعات.') !!}
                                    </label>
                                    <input type="number" step="0.01" min="1" max="744" required
                                           id="overtime_monthly_hours" name="overtime_monthly_hours"
                                           class="form-control @error('overtime_monthly_hours') is-invalid @enderror"
                                           value="{{ old('overtime_monthly_hours', $s['overtime_monthly_hours'] ?? 240) }}">
                                    @error('overtime_monthly_hours')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="overtime_hourly_rate">
                                        سعر الساعة الثابت
                                        {!! $tip('اختياري. إن ضُبط يُستعمل لكلّ الموظفين بدل اشتقاق السعر من الأساسي. فارغاً أو صفراً = يُشتقّ من الأساسي.') !!}
                                    </label>
                                    <input type="number" step="0.01" min="0"
                                           id="overtime_hourly_rate" name="overtime_hourly_rate"
                                           class="form-control @error('overtime_hourly_rate') is-invalid @enderror"
                                           placeholder="من الأساسي"
                                           value="{{ old('overtime_hourly_rate', $s['overtime_hourly_rate'] ?? '') }}">
                                    @error('overtime_hourly_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="overtime_max_minutes_per_period">
                                        حدّ الدقائق في الفترة
                                        {!! $tip('أقصى مجموعٍ من الدقائق يُصرف في صرفةٍ واحدة (فترة الراتب). ما زاد يُقصّ بالنسبة بين أيام العمل والعطل. فارغاً = بلا حدّ.') !!}
                                    </label>
                                    <input type="number" step="1" min="1" max="100000"
                                           id="overtime_max_minutes_per_period" name="overtime_max_minutes_per_period"
                                           class="form-control @error('overtime_max_minutes_per_period') is-invalid @enderror"
                                           placeholder="بلا حدّ"
                                           value="{{ old('overtime_max_minutes_per_period', $s['overtime_max_minutes_per_period'] ?? '') }}">
                                    @error('overtime_max_minutes_per_period')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="overtime_item_name">
                                        يظهر في القسيمة باسم
                                        {!! $tip('بندُ استحقاقٍ يُولَّد عند الصرف — لا يُضاف يدوياً. يُقيَّد مع الراتب على «مصروف رواتب الموظفين» المربوط في إعدادات الحسابات المحاسبية.') !!}
                                    </label>
                                    <input type="text" id="overtime_item_name" class="form-control" value="{{ $overtimeItemName }}" readonly disabled>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ المضاعف والحدّ اليومي ═══════════ --}}
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-sliders"></i>
                            <h6 class="stk-section__title">المضاعف والحدّ اليومي</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="weekday_multiplier" class="stk-req">
                                        مضاعف يوم العمل
                                        {!! $tip('يُضرب في سعر الساعة لدقائق الإضافي في أيام العمل العادية. من 1 إلى 5.') !!}
                                    </label>
                                    <input type="number" step="0.01" min="1" max="5" required
                                           id="weekday_multiplier" name="weekday_multiplier"
                                           class="form-control @error('weekday_multiplier') is-invalid @enderror"
                                           value="{{ old('weekday_multiplier', optional($weekday)->multiplier ?? 1.5) }}">
                                    @error('weekday_multiplier')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="weekday_max_minutes_per_day">
                                        حدّ دقائق يوم العمل
                                        {!! $tip('أقصى ما يُحتسب من يوم عملٍ واحد — ما زاد لا يُصرف. فارغاً = بلا حدّ.') !!}
                                    </label>
                                    <input type="number" step="1" min="1" max="1440"
                                           id="weekday_max_minutes_per_day" name="weekday_max_minutes_per_day"
                                           class="form-control @error('weekday_max_minutes_per_day') is-invalid @enderror"
                                           placeholder="بلا حدّ"
                                           value="{{ old('weekday_max_minutes_per_day', optional($weekday)->max_minutes_per_day) }}">
                                    @error('weekday_max_minutes_per_day')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="holiday_multiplier" class="stk-req">
                                        مضاعف العطلة
                                        {!! $tip('لأيامٍ معلَّمةٍ في الحضور عطلةً رسمية أو راحةً أسبوعية. من 1 إلى 5.') !!}
                                    </label>
                                    <input type="number" step="0.01" min="1" max="5" required
                                           id="holiday_multiplier" name="holiday_multiplier"
                                           class="form-control @error('holiday_multiplier') is-invalid @enderror"
                                           value="{{ old('holiday_multiplier', optional($holiday)->multiplier ?? 2) }}">
                                    @error('holiday_multiplier')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="holiday_max_minutes_per_day">
                                        حدّ دقائق العطلة
                                        {!! $tip('أقصى ما يُحتسب من يوم عطلةٍ واحد. فارغاً = بلا حدّ.') !!}
                                    </label>
                                    <input type="number" step="1" min="1" max="1440"
                                           id="holiday_max_minutes_per_day" name="holiday_max_minutes_per_day"
                                           class="form-control @error('holiday_max_minutes_per_day') is-invalid @enderror"
                                           placeholder="بلا حدّ"
                                           value="{{ old('holiday_max_minutes_per_day', optional($holiday)->max_minutes_per_day) }}">
                                    @error('holiday_max_minutes_per_day')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <p class="stk-help mb-0">المبلغ = (الدقائق ÷ 60) × سعر الساعة × المضاعف.</p>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> حفظ الإعدادات
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
