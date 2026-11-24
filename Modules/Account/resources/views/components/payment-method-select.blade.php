{{--
    طريقة الدفع — من شاشة «طرق الدفع» في الإعدادات.

    تُعرض الطرق النقدية (`type = normal`) المفعَّلة وحدها: بوّابات الدفع
    الإلكتروني تخصّ المتجر ونقطة البيع، ولا معنى لاختيارها في سند صرفٍ يدوي.

    الاستعمال:

        @include('account::components.payment-method-select', [
            'name'     => 'payment_method_id',
            'label'    => 'طريقة الدفع',
            'selected' => old('payment_method_id', $expense->payment_method_id ?? null),
        ])
--}}

@php
    $methods = \App\Models\PaymentMethod::query()
        ->where('type', 'normal')
        ->where('status', 'active')
        ->orderByDesc('is_default')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();

    $methodId = $selected ?? optional($methods->firstWhere('is_default', true))->id;
    $selectId = $id ?? $name;
@endphp

@isset($label)
    <label for="{{ $selectId }}">
        {{ $label }}@if ($required ?? false)<span class="text-danger">*</span>@endif
    </label>
@endisset

<select name="{{ $name }}" id="{{ $selectId }}"
    class="{{ $class ?? 'form-control' }}"
    @if ($required ?? false) required @endif>

    <option value="">{{ $placeholder ?? 'غير محدّدة' }}</option>

    @foreach ($methods as $method)
        <option value="{{ $method->id }}" @selected((string) $methodId === (string) $method->id)>
            {{ $method->name }}
        </option>
    @endforeach
</select>
