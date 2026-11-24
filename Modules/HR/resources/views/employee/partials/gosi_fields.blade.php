{{--
    «التأمينات الاجتماعية» في بطاقة الموظف — يقرؤها `SocialInsuranceService::eligibility()`.
    `gosi_subject`: فارغ = حسب حالة المواطنة، 1 = خاضع، 0 = معفى.
--}}
@php
    $gosiSubject = old('gosi_subject', $employee?->gosi_subject);
    $gosiSubject = ($gosiSubject === null || $gosiSubject === '') ? '' : (string) (int) $gosiSubject;
    $gosiStart = old('gosi_start_date', $employee?->gosi_start_date ? \Carbon\Carbon::parse($employee->gosi_start_date)->format('Y-m-d') : '');
@endphp
<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-shield"></i> التأمينات الاجتماعية
            <i class="feather icon-help-circle stk-tip" title="عند صرف الراتب: المواطن يخضع بنسبتي الموظف والمنشأة، والمقيم بنسبتي غير المواطن (افتراضاً 0% على الموظف و2% على المنشأة)، والزائر لا يخضع. النسب من «إعدادات التأمينات الاجتماعية»."></i>
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="form-group col-md-4">
                <label for="gosi_subject">الخضوع للتأمينات
                    <i class="feather icon-help-circle stk-tip" title="«حسب حالة المواطنة» هو الافتراضي. «معفى» يوقف الاحتساب لهذا الموظف أيّاً كانت جنسيته. «خاضع» يُخضع الزائر بنسبتي غير المواطن."></i>
                </label>
                <select class="form-control hr-s2 @error('gosi_subject') is-invalid @enderror" name="gosi_subject" id="gosi_subject">
                    <option value="" {{ $gosiSubject === '' ? 'selected' : '' }}>حسب حالة المواطنة</option>
                    <option value="1" {{ $gosiSubject === '1' ? 'selected' : '' }}>خاضع</option>
                    <option value="0" {{ $gosiSubject === '0' ? 'selected' : '' }}>معفى</option>
                </select>
                @error('gosi_subject')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="form-group col-md-4">
                <label for="gosi_number">رقم الاشتراك</label>
                <input type="text" class="form-control @error('gosi_number') is-invalid @enderror" name="gosi_number" id="gosi_number"
                       maxlength="50" value="{{ old('gosi_number', $employee?->gosi_number) }}">
                @error('gosi_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="form-group col-md-4">
                <label for="gosi_start_date">تاريخ بدء الاشتراك
                    <i class="feather icon-help-circle stk-tip" title="اختياري. صرفاتُ الفترات المنتهية قبل هذا التاريخ لا تُحتسب لها تأمينات."></i>
                </label>
                <input type="date" class="form-control @error('gosi_start_date') is-invalid @enderror" name="gosi_start_date" id="gosi_start_date"
                       value="{{ $gosiStart }}">
                @error('gosi_start_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
