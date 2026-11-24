{{--
    مُنتقي «هدف العميل».

    الهدفُ حقلٌ حقيقيٌّ في النظام — `clients.customer_target_id` ←
    `customer_targets` — مضبوطٌ على كلّ عملاء هذه المنشأة (٣٨٥٢/٣٨٥٢)، ولم
    يكن يُعرض ولا يُفلتَر به في أيٍّ من تقارير العملاء.

    و«بلا هدف» خيارٌ صريح: خانةٌ فارغةٌ تعني «كلّ الأهداف»، فلا سبيلَ
    للسؤال عن غير المصنَّفين إلّا بقيمةٍ خاصّةٍ لهم.

    والعنوانُ فوقه اختياري — تمرّره الشاشةُ التي تصفُّ حقولَها في شبكةٍ
    واحدة كي تستوي رؤوسُ الحقول، وتتركه الشاشةُ التي تكتفي بالنصّ داخل الحقل.

    @param \Illuminate\Support\Collection $customerTargets
    @param ?string $label  نصُّ العنوان فوق الحقل — بلا قيمةٍ لا يُطبع عنوان
--}}

@php
    $tsTargets = $customerTargets ?? collect();
    $tsCurrent = (string) request('customer_target_id', '');
    $tsLabel = $label ?? null;
@endphp

@if ($tsLabel)
    <label for="customer_target_id">{{ $tsLabel }}</label>
@endif

<select name="customer_target_id" id="customer_target_id" class="form-control rpt-select2"
        data-placeholder="كل الأهداف">
    <option value="">كل الأهداف</option>
    <option value="no_target" {{ $tsCurrent === 'no_target' ? 'selected' : '' }}>— بلا هدف —</option>
    @foreach ($tsTargets as $tsTarget)
        <option value="{{ $tsTarget->id }}" {{ $tsCurrent === (string) $tsTarget->id ? 'selected' : '' }}>
            {{ $tsTarget->name }}{{ $tsTarget->is_active ? '' : ' (موقوف)' }}
        </option>
    @endforeach
</select>
