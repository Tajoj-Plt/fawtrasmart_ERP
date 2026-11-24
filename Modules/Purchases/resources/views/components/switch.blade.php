{{--
    مفتاح تبديل بعنوان وشرح — نسخة المشتريات من `sales::components.switch`،
    بالمظهر نفسه (`sl-switch`) وبوسائطه نفسها، وزيادةٍ واحدة:

      hidden   أيُسبَق المربّع بحقلٍ مخفيّ قيمته `0`؟ (افتراضياً نعم)

    لماذا الزيادة؟ شاشة إعدادات المشتريات ترسل مفاتيحها مصفوفةً واحدة
    `settings[]` قيمةُ كلِّ مربّعٍ فيها اسمُ مفتاحه، والمتحكّم يطفئ الكلّ ثم
    يشعل المرسَل — فالحقل المخفيّ هناك يرسل `settings[] = 0` فتردّه قاعدة
    `in:` ويرتدّ الحفظ كلُّه. بقيّة الشاشات تبقى على الافتراضي.

    الوسائط:
      name      اسم الحقل
      checked   الحالة الابتدائية (bool)
      title     العنوان
      hint      سطر شارح (اختياري)
      meta      سطر ثانٍ تحت الشرح — «يسري على» مثلاً (اختياري)
      badge     شارة صغيرة بجانب العنوان (اختياري)
      value     قيمة التشغيل (افتراضي 1)
      disabled  تعطيل الحقل (اختياري)
      id        مُعرّف (افتراضي: مشتقّ من الاسم)
      hidden    الحقل المخفيّ `0` (افتراضي: نعم)

    فحصُ أنواع الوسائط الاختيارية مقصود: `@include` يمرّر متغيّرات القالب
    الأب كلَّها، فمُغلَقٌ محليٌّ في الأب اسمه `$value` يصل إلى هنا ويُسقط
    الشاشة بـ«Closure could not be converted to string».
--}}
@php
    $value = (isset($value) && is_scalar($value)) ? $value : 1;
    $hint = (isset($hint) && is_string($hint) && $hint !== '') ? $hint : null;
    $meta = (isset($meta) && is_string($meta) && $meta !== '') ? $meta : null;
    $badge = (isset($badge) && is_string($badge) && $badge !== '') ? $badge : null;
    $disabled = isset($disabled) && $disabled === true;
    $hidden = ! isset($hidden) || $hidden !== false;

    $id = (isset($id) && is_string($id) && $id !== '')
        ? $id
        : 'sw_'.preg_replace('/[^A-Za-z0-9_]/', '_', $name);
@endphp

<label class="sl-switch" for="{{ $id }}">
    @if ($hidden)
        {{-- بلا هذا الحقل لا يُرسَل الاسم أصلاً حين يكون المفتاح مُطفأً --}}
        <input type="hidden" name="{{ $name }}" value="0">
    @endif

    <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}"
           {{ $checked ? 'checked' : '' }} {{ $disabled ? 'disabled' : '' }}
           @if ($hint) aria-describedby="{{ $id }}_hint" @endif>

    <span class="sl-switch__track" aria-hidden="true">
        <span class="sl-switch__thumb"></span>
    </span>

    <span class="sl-switch__label">
        <span class="sl-switch__title">
            {{ $title }}
            @if ($badge)
                <span class="badge badge-light-primary sl-switch__badge">{{ $badge }}</span>
            @endif
        </span>
        @if ($hint)
            <span class="sl-switch__hint" id="{{ $id }}_hint">{{ $hint }}</span>
        @endif
        @if ($meta)
            <span class="sl-switch__hint sl-switch__meta">{{ $meta }}</span>
        @endif
    </span>
</label>
