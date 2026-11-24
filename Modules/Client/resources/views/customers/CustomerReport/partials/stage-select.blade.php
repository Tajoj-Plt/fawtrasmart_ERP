{{--
    مُنتقي «وضع العميل» — مرحلةُ تسجيله.

    حقلٌ مستقلٌّ عن «حالة العميل»: الحالةُ نشط/موقوف (`clients.status_id`)،
    والوضعُ مرحلةُ التسجيل (`clients.registration_stage_id`) من نفس جدول
    `statuses` لكن بقسم `registration_stage` — وهو المعروضُ في فهرس العملاء
    وشاشة العميل باسم «الوضع».

    والعنوانُ فوقه اختياري: شاشةٌ تصفُّ حقولَها في شبكةٍ واحدة تمرّر
    `label` فتستوي رؤوسُ الحقول على سطرٍ واحد، وشاشةٌ تكتفي بالنصّ داخل
    الحقل تتركه — فلا يُفرَض عنوانٌ على تخطيطٍ لم يطلبه.

    @param \Illuminate\Support\Collection $registrationStages
    @param ?string $label  نصُّ العنوان فوق الحقل — بلا قيمةٍ لا يُطبع عنوان
--}}

@php
    $ssStages = $registrationStages ?? collect();
    $ssCurrent = (string) request('registration_stage', '');
    $ssLabel = $label ?? null;
@endphp

@if ($ssLabel)
    <label for="registration_stage">{{ $ssLabel }}</label>
@endif

<select name="registration_stage" id="registration_stage" class="form-control rpt-select2"
        data-placeholder="كل الأوضاع">
    <option value="">كل الأوضاع</option>
    <option value="no_stage" {{ $ssCurrent === 'no_stage' ? 'selected' : '' }}>— بلا وضع —</option>
    @foreach ($ssStages as $ssStage)
        <option value="{{ $ssStage->id }}" {{ $ssCurrent === (string) $ssStage->id ? 'selected' : '' }}>
            {{ $ssStage->name }}
        </option>
    @endforeach
</select>
