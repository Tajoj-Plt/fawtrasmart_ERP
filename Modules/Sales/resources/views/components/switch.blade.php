{{--
    مفتاح تبديل بعنوان وشرح — نظير `stk-switch`.

    `custom-switch` من القالب: صغير، وبلا شرح لما يفعله، ولا يرسل شيئاً
    حين يُطفأ فيبقى الإعداد على قيمته القديمة. هنا حقل مخفيّ بقيمة `0`
    يسبق المربّع، فالإطفاء يصل إلى الخادم كما يصل التشغيل.

    الوسائط:
      name      اسم الحقل
      checked   الحالة الابتدائية (bool)
      title     العنوان
      hint      سطر شارح (اختياري)
      value     قيمة التشغيل (افتراضي 1)
      disabled  تعطيل الحقل (اختياري)
      id        مُعرّف (افتراضي: مشتقّ من الاسم)

    ── لماذا تُفحَص أنواع الوسائط الاختيارية؟ ──

    `@include` يمرّر متغيّرات القالب الأب كلَّها إلى المُضمَّن، لا ما مُرِّر
    في المصفوفة وحده. فقالبٌ فيه:

        $value = fn ($field) => old($field, …);   // مساعدٌ محليّ
        @include('sales::components.switch', ['name' => 'is_active', …])

    يُسرّب مُغلَقَه إلى `$value` هنا، و`$value ?? 1` تُبقيه — ثم
    `value="{{ $value }}"` ترمي «Object of class Closure could not be
    converted to string»، أي صفحة 500 في شاشة كانت تعمل.

    وهذا ما وقع فعلاً في نماذج عروض الحسم والهدايا وأسباب الاستبدال.
    فحصُ النوع يجعل التسرّب غير مؤذٍ بدل أن يُسقط الشاشة.
--}}
@php
    $value = (isset($value) && is_scalar($value)) ? $value : 1;
    $hint = (isset($hint) && is_string($hint) && $hint !== '') ? $hint : null;

    // `(bool)` على مُغلَقٍ مُسرَّب تعطي true فتُعطَّل الشاشةُ بلا سبب:
    // التعطيل لا يقع إلّا بقيمة منطقية صريحة
    $disabled = isset($disabled) && $disabled === true;

    $id = (isset($id) && is_string($id) && $id !== '')
        ? $id
        : 'sw_'.preg_replace('/[^A-Za-z0-9_]/', '_', $name);
@endphp

<label class="sl-switch" for="{{ $id }}">
    {{-- بلا هذا الحقل لا يُرسَل الاسم أصلاً حين يكون المفتاح مُطفأً --}}
    <input type="hidden" name="{{ $name }}" value="0">

    <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}"
           {{ $checked ? 'checked' : '' }} {{ $disabled ? 'disabled' : '' }}>

    <span class="sl-switch__track" aria-hidden="true">
        <span class="sl-switch__thumb"></span>
    </span>

    <span class="sl-switch__label">
        <span class="sl-switch__title">{{ $title }}</span>
        @if ($hint)
            <span class="sl-switch__hint">{{ $hint }}</span>
        @endif
    </span>
</label>
