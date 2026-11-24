{{--
    اسمُ العميل في التقارير رابطاً إلى صفحته — مصدرٌ واحدٌ لتقارير المبيعات والعملاء.

    @param int|null $id        معرّف العميل (clients.id). بلا معرّف يُطبع الاسمُ نصّاً.
    @param string   $name      الاسم المعروض.
    @param string   $fallback  ما يُطبع حين يكون الاسم فارغاً (الافتراض «-»).

    يُفتح في تبويبٍ جديد كي لا يضيع التقريرُ بفلاتره. وإيقافُ انتشار النقرة
    مقصود: صفوفٌ كثيرة في هذه التقارير تفتح تفاصيلَها بالنقر عليها، فبدونه
    يطوي النقرُ على الاسم الصفَّ أو يفتحه مع فتح الرابط.
--}}
@php
    $clId = $id ?? null;
    $clName = trim((string) ($name ?? '')) !== '' ? $name : ($fallback ?? '-');
@endphp
@once
    <style>
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
        @media print { .rpt-client-link { border-bottom: 0; } }
    </style>
@endonce
@if ($clId)<a href="{{ route('clients.show', $clId) }}" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة العميل" onclick="event.stopPropagation()">{{ $clName }}</a>@else{{ $clName }}@endif
