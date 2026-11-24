{{--
    جدول «وسم ← قيمة» لشاشة العرض.

    كانت الشاشة تبني كل حقلٍ بأربعة عناصر `div` متداخلة (`col-6 mb-3` ثم
    `info-label` ثم `info-value`) بأصناف لا تعريف لها في أي ورقة أنماط
    محمَّلة — فتخرج القيمة بحجم النصّ العادي بلا تمييزٍ عن وسمها.

    الصفوف الفارغة تُسقَط: «رقم التتبّع: —» في أمرٍ لم يُشحن بعد سطرٌ لا
    يقول شيئاً، وحذفُه يترك الشاشة على ما هو معروفٌ فعلاً.

    الوسائط:
      rows  مصفوفة ['الوسم' => 'القيمة'] — القيم الفارغة تُحذف
--}}

@php
    $rows = collect($rows ?? [])
        ->map(fn ($value) => is_string($value) ? trim($value) : $value)
        ->reject(fn ($value) => $value === null || $value === '' || $value === []);
@endphp

@if ($rows->isNotEmpty())
    <dl style="display:grid;grid-template-columns:minmax(120px,auto) 1fr;gap:.55rem 1rem;margin:0">
        @foreach ($rows as $label => $value)
            <dt style="font-size:.78rem;font-weight:600;color:var(--app-muted, #6e6b7b);margin:0">
                {{ $label }}
            </dt>
            <dd style="font-size:.88rem;font-weight:600;color:var(--app-text, #4b4b4b);margin:0;min-width:0;
                       overflow-wrap:anywhere">
                {{ $value }}
            </dd>
        @endforeach
    </dl>
@else
    <p class="sl-media__sub" style="margin:0">{{ __('sales_settings.ui.none') }}</p>
@endif
