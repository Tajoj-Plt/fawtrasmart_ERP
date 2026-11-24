@extends('hr::master')

@section('title', 'إعدادات الملف البنكي')

@section('content')
    @php
        /** أيقونةُ شرحٍ تُقرأ عند التمرير (`stk-tip`) — ما يفعله الحقل وأين يسري. */
        $tip = fn (string $text) => '<i class="feather icon-help-circle stk-tip" title="'.e($text).'"></i>';
        $format = (string) old('bank_export_format', $bankFileSettings['format'] ?? 'csv');
        $bankCode = (string) old('bank_export_bank_code', $bankFileSettings['bank_code'] ?? '');
    @endphp

    @section('page-actions')
        @permission('bank_files.index')
            <a href="{{ route('hr.bank-files.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> الملف البنكي
            </a>
        @endpermission
        <button type="submit" form="bankFileSettingsForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الإعدادات
        </button>
    @endsection

    @include('stock::components.page-head', [
        'title' => 'إعدادات الملف البنكي',
        'icon' => 'credit-card',
        'sub' => 'بأي صيغة يطلع ملف الرواتب، ومن أي بنك تحوّل الشركة',
        'crumbs' => ['إعدادات الرواتب' => route('SalarySittings.index'), 'إعدادات الملف البنكي' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        @include('hr::components.guide', [
            'key' => 'hr-bank-files.settings',
            'title' => 'وش تحدد هنا؟',
            'lead' => 'ملف الرواتب البنكي فيه سطر لكل موظف: اسمه وآيبانه وصافي راتبه. هنا تختار صيغة الملف (Excel أو CSV) والبنك اللي تحوّل منه الشركة.',
            'blocks' => [
                'أي صيغة أختار؟' => [
                    'Excel: تقدر تفتحه وتراجعه بسهولة.',
                    'CSV: ملف نصي بسيط — أغلب مواقع البنوك تقبله للرفع. اسأل بنكك وش يقبل.',
                ],
                'انتبه' => [
                    'هذا ملف تحويل عام، مو ملف «حماية الأجور» الرسمي ولا صيغة خاصة ببنك معيّن.',
                ],
            ],
            'facts' => [
                'التغيير يمشي على الملفات الجاية بس',
            ],
        ])

        <form id="bankFileSettingsForm" action="{{ route('hr.bank-files.settings.update') }}" method="POST">
            @csrf

            <div class="card">
                <div class="card-body">
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-file-text"></i>
                            <h6 class="stk-section__title">صيغة الملف والبنك</h6>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="bank_export_format" class="stk-req">
                                    بأي صيغة يطلع الملف؟
                                    {!! $tip('لما تضغط «توليد الملف» بيطلع بهذي الصيغة. الملفات اللي طلعت قبل تبقى زي ما هي.') !!}
                                </label>
                                <select id="bank_export_format" name="bank_export_format" required
                                        class="form-control hr-s2 @error('bank_export_format') is-invalid @enderror">
                                    <option value="csv" @selected($format === 'csv')>CSV — ملف نصي (أغلب البنوك تقبله)</option>
                                    <option value="xlsx" @selected($format === 'xlsx')>Excel — سهل تفتحه وتراجعه</option>
                                </select>
                                @error('bank_export_format')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="bank_export_bank_code">
                                    من أي بنك تحوّل الشركة؟
                                    {!! $tip('البنك اللي فيه حساب الشركة وتحوّل منه الرواتب. ينحفظ مع كل ملف كمرجع، وما يغيّر الأسماء ولا المبالغ اللي في الملف.') !!}
                                </label>
                                <select id="bank_export_bank_code" name="bank_export_bank_code"
                                        class="form-control hr-s2 @error('bank_export_bank_code') is-invalid @enderror">
                                    <option value="">— ما حددت —</option>
                                    @foreach ($banks as $code => $name)
                                        <option value="{{ $code }}" @selected($bankCode === (string) $code)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('bank_export_bank_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <a href="{{ hasPermission('bank_files.index') ? route('hr.bank-files.index') : route('SalarySittings.index') }}" class="btn btn-outline-secondary">
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
