{{--
    صفّ لون واحد: منتقٍ ومربّع كود.

    الكلاسان `js-picker` و`js-hex` هما ما يربطه سكربت الشاشة، فالصفّ يعمل في
    أي تبويب أُدرج فيه بلا ربط إضافي.

    حقل الكود وحده يحمل `name`، فالمنتقي معينٌ بصريّ لا أكثر. وعليه يعني تفريغ
    الكود «اترك لون القالب الأصلي» — لذلك تُعرض للاختياري زرّ مسح، ويبقى
    المنتقي مُظهراً اللون الفعلي المشتق ($preview) ليرى المستخدم ما سيحدث.

    المتغيّرات: $name، $label، $hint، $value، و(اختياري) $preview و$optional.
--}}
@php
    $preview = $preview ?? ($value ?: '#000000');
    $optional = $optional ?? false;
@endphp

<div class="ap-color">
    <input type="color" class="ap-swatch js-picker" data-target="{{ $name }}"
        value="{{ $preview }}" aria-label="{{ $label }}">

    <div class="ap-color-info">
        <strong>{{ $label }}</strong>
        <small>{{ $hint }}</small>
    </div>

    <input type="text" class="ap-hex js-hex" name="{{ $name }}" id="{{ $name }}"
        value="{{ $value }}" maxlength="7" pattern="^#[0-9a-fA-F]{6}$"
        placeholder="{{ $optional ? __('settings.appearance.inherit') : '' }}"
        aria-label="{{ __('settings.appearance.color_code', ['label' => $label]) }}">

    @if ($optional)
        <button type="button" class="ap-clear js-clear" data-target="{{ $name }}"
            title="{{ __('settings.appearance.clear_color') }}"
            aria-label="{{ __('settings.appearance.clear_color') }}">
            <i class="feather icon-x"></i>
        </button>
    @endif
</div>
