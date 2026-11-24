{{--
    الحالة الفارغة — نظير `stk-empty`.

    جدولٌ بلا صفوف كان يُعرض ترويسةً معلّقة فوق فراغ، فلا يُعرف: أهي شاشة
    لم يُضَف إليها شيء بعد، أم فلترٌ لم يُطابق شيئاً؟ هنا يُقال أيّهما،
    ويُعرَض الفعل التالي.

    الوسائط:
      icon    أيقونة feather بلا البادئة (افتراضي: inbox)
      title   العنوان
      hint    سطر شارح (اختياري)
      action  HTML لزرّ الإجراء (اختياري)
--}}
@php
    $icon = (isset($icon) && is_string($icon) && $icon !== '') ? $icon : 'inbox';
    $hint = (isset($hint) && is_string($hint) && $hint !== '') ? $hint : null;
    $action = (isset($action) && is_string($action)) ? $action : null;
@endphp

<div class="rsv-empty">
    <div class="rsv-empty__icon" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></div>
    <p class="rsv-empty__title">{{ $title }}</p>

    @if ($hint)
        <p class="rsv-empty__hint">{{ $hint }}</p>
    @endif

    @if ($action !== null && trim($action) !== '')
        {!! $action !!}
    @endif
</div>
