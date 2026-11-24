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
        @include('client::reservations.components.switch', ['name' => 'is_active', …])

    يُسرّب مُغلَقَه إلى `$value` هنا، و`$value ?? 1` تُبقيه — ثم
    `value="{{ $value }}"` ترمي «Object of class Closure could not be
    converted to string»، أي صفحة 500 في شاشة كانت تعمل.

    وهذا ما وقع فعلاً في نماذج عروض الحسم والهدايا وأسباب الاستبدال.
    فحصُ النوع يجعل التسرّب غير مؤذٍ بدل أن يُسقط الشاشة.
--}}
@php
    /*
        قيمةُ التشغيل **رقمٌ صحيح** وإلّا 1 — لا «أيُّ قيمةٍ بسيطة».

        فحصُ `is_scalar` وحدَه لم يكفِ: شاشةُ إعدادات الحجوزات فيها حلقةٌ
        `as $value => $label` قبل المفاتيح، فورث كلُّ مفتاحٍ بعدها
        `$value = 'listWeek'` (نصٌّ بسيط يمرّ من الفحص)، فأرسلت الخاناتُ
        المؤشَّرة «listWeek» ورفض الحفظ ثلاثةَ عشرَ حقلاً بـ«يجب أن تكون
        صحيحة أو خاطئة». والمفتاحُ مفتاحٌ منطقي: قيمتُه 1 أو رقمٌ صريح.
    */
    $value = (isset($value) && (is_int($value) || (is_string($value) && ctype_digit($value)))) ? $value : 1;
    $hint = (isset($hint) && is_string($hint) && $hint !== '') ? $hint : null;

    // `(bool)` على مُغلَقٍ مُسرَّب تعطي true فتُعطَّل الشاشةُ بلا سبب:
    // التعطيل لا يقع إلّا بقيمة منطقية صريحة
    $disabled = isset($disabled) && $disabled === true;

    $id = (isset($id) && is_string($id) && $id !== '')
        ? $id
        : 'sw_'.preg_replace('/[^A-Za-z0-9_]/', '_', $name);
@endphp

<label class="rsv-switch" for="{{ $id }}">
    {{-- بلا هذا الحقل لا يُرسَل الاسم أصلاً حين يكون المفتاح مُطفأً --}}
    <input type="hidden" name="{{ $name }}" value="0">

    <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}"
           {{ $checked ? 'checked' : '' }} {{ $disabled ? 'disabled' : '' }}>

    <span class="rsv-switch__track" aria-hidden="true">
        <span class="rsv-switch__thumb"></span>
    </span>

    {{-- الشرحُ صار أيقونةَ تعجّبٍ تُظهر تلميحاً عند المرور — طلب المستخدم
         (2026-09-20): «بدل الإرشادات تحت الحقل علامةُ تعجّب تظهر بالماوس» --}}
    <span class="rsv-switch__label">
        <span class="rsv-switch__title">
            {{ $title }}
            @if ($hint)
                <i class="fas fa-exclamation-circle rsv-tip" tabindex="0" data-tip="{{ $hint }}" role="img" aria-label="{{ $hint }}"></i>
            @endif
        </span>
    </span>
</label>
