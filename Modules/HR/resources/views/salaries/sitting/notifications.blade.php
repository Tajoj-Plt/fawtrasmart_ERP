@extends('hr::master')

@section('title', 'إشعارات العقود والوثائق')

@section('content')
    @php
        /** أيقونةُ شرحٍ تُقرأ عند التمرير (`stk-tip`) — ما يفعله الحقل وأين يسري. */
        $tip = fn (string $text) => '<i class="feather icon-help-circle stk-tip" title="'.e($text).'"></i>';
        $s = $payrollSettings;
    @endphp

    @section('page-actions')
        <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> الإعدادات
        </a>
        <button type="submit" form="payrollNoticesForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الإعدادات
        </button>
    @endsection

    @include('stock::components.page-head', [
        'title' => 'إشعارات العقود والوثائق',
        'icon' => 'bell',
        'crumbs' => ['الإعدادات' => route('SalarySittings.index'), 'إشعارات العقود' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        @include('hr::components.guide', [
            'key' => 'hr-payroll-settings.notifications',
            'title' => 'متى يُنبَّه على عقدٍ أو وثيقةٍ توشك على الانتهاء، ومن يصله التنبيه؟',
            'lead' => 'مهمّتان يوميّتان تفحصان العقودَ ووثائقَ الموظفين، وتُنبّهان حين يبقى على الانتهاء عددُ الأيام المضبوط هنا أو أقلّ.',
            'blocks' => [
                'من يصله التنبيه' => [
                    'الموظفُ نفسُه إن كان له مستخدمٌ في النظام، وإشعارٌ عامّ لمسؤولي النظام.',
                ],
                'وأين يظهر أيضاً' => [
                    'تقريرا «العقود المنتهية قريباً» و«الوثائق المنتهية قريباً» في تقارير الموظفين يقرآن المدّتين نفسيهما.',
                ],
            ],
            'facts' => [
                'الفارغ = 30 يوماً',
                'يسري من التشغيل التالي للمهمّة',
            ],
        ])

        <form id="payrollNoticesForm" action="{{ route('SalarySittings.notifications.update') }}" method="POST">
            @csrf

            <div class="card">
                <div class="card-body">
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-bell"></i>
                            <h6 class="stk-section__title">مدد الإنذار</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contract_end_notice_period">
                                        إنذار انتهاء العقد (بالأيام)
                                        {!! $tip('تقرؤه مهمّةُ «فحص العقود المنتهية قريباً» اليومية، وتقريرُ العقود المنتهية قريباً: يُنبَّه على كلّ عقدٍ نشطٍ بقي على نهايته هذا العددُ من الأيام أو أقلّ. فارغاً = 30 يوماً.') !!}
                                    </label>
                                    <input type="number" min="1" max="365" step="1"
                                           id="contract_end_notice_period" name="contract_end_notice_period"
                                           class="form-control @error('contract_end_notice_period') is-invalid @enderror"
                                           placeholder="30"
                                           value="{{ old('contract_end_notice_period', $s['contract_end_notice_period'] ?? '') }}">
                                    @error('contract_end_notice_period')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="document_expiry_notice_period">
                                        إنذار انتهاء وثائق الموظفين (بالأيام)
                                        {!! $tip('تقرؤه مهمّةُ «تنبيه انتهاء وثائق الموظفين» اليومية، وتقريرُ الوثائق المنتهية قريباً: الإقامات والرخص والوثائق الصحّية. والتذكيرُ يتكرّر أسبوعياً حتى تُجدَّد الوثيقة. فارغاً = 30 يوماً.') !!}
                                    </label>
                                    <input type="number" min="1" max="365" step="1"
                                           id="document_expiry_notice_period" name="document_expiry_notice_period"
                                           class="form-control @error('document_expiry_notice_period') is-invalid @enderror"
                                           placeholder="30"
                                           value="{{ old('document_expiry_notice_period', $s['document_expiry_notice_period'] ?? '') }}">
                                    @error('document_expiry_notice_period')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('hr::salaries.sitting.partials.cron-state', [
                        'cronKeys' => [
                            'contract_expirations' => 'مدّة إنذار انتهاء العقد',
                            'hr_document_expiry' => 'مدّة إنذار انتهاء الوثائق',
                        ],
                    ])
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
