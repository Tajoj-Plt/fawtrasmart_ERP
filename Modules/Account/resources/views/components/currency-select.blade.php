{{--
    قائمة العملة — مرجع واحد لكل شاشات وحدة الحسابات.

    **ما كان**

    كل شاشة تكتب قائمتها بيدها بعملتين ثابتتين: «ريال» و«جنية» في الخزائن،
    و«ريال سعودي (SAR)» و«دولار أمريكي (USD)» في الأصول، و«SAR» وحدها في
    التحويلات — بينما جدول العملات في النظام يحمل عشرين عملة. والقيم أرقامٌ
    بلا معنى (`0`/`1`، `1`/`2`) أو نصوصٌ عربية تُقارَن بأعمدةٍ تخزّن أرقاماً،
    فكان فلتر العملة في شاشة الخزائن لا يطابق صفّاً واحداً أبداً.

    **ما صار**

    العملات المفعَّلة من جدول العملات، وقيمتها رمز ISO كما يُخزَّن في
    المستند، والمحدَّد ابتداءً هو العملة الافتراضية من شاشة «العملات».

    الاستعمال:

        @include('account::components.currency-select', [
            'name'     => 'currency',
            'selected' => old('currency', $treasury->currency ?? null),
        ])

    الوسائط: `name` (إلزامي)، `id`، `label`، `selected`، `required`،
    `class`، `placeholder` (خيار «الكل» في الفلاتر)، `disabled`.
--}}

@php
    $currencies = \App\Models\SystemCurrency::query()
        ->where('is_active', true)
        ->orderByDesc('is_default')
        ->orderBy('sort_order')
        ->orderBy('code')
        ->get();

    // قاعدةٌ لم تُزرع فيها العملات: تبقى الافتراضية وحدها خياراً
    $defaultCode = \App\Support\CurrencySettings::code();

    $selectId = $id ?? $name;
    $selectedCode = $selected ?? ($placeholder ?? null ? null : $defaultCode);
    $isRequired = $required ?? false;
@endphp

@isset($label)
    <label for="{{ $selectId }}">
        {{ $label }}@if ($isRequired)<span class="text-danger">*</span>@endif
    </label>
@endisset

<select name="{{ $name }}" id="{{ $selectId }}"
    class="{{ $class ?? 'form-control' }}"
    @if ($isRequired) required @endif
    @if ($disabled ?? false) disabled @endif>

    @isset($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endisset

    @forelse ($currencies as $currency)
        <option value="{{ $currency->code }}" @selected($selectedCode === $currency->code)>
            {{ $currency->arabic_name ?: ($currency->name ?: $currency->code) }} ({{ $currency->code }})
        </option>
    @empty
        <option value="{{ $defaultCode }}" selected>{{ $defaultCode }}</option>
    @endforelse
</select>
