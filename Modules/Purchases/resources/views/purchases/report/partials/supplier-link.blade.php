{{--
    اسمُ المورّد في التقارير رابطاً إلى صفحته — نظيرُ `reports.partials.client-link`
    في تقارير العملاء، بالصنف نفسِه (`rpt-client-link`) فيأخذ مظهرَه.

    @param int|null $id        معرّف المورّد (suppliers.id). بلا معرّف يُطبع الاسمُ نصّاً.
    @param string   $name      الاسم المعروض.
    @param string   $fallback  ما يُطبع حين يكون الاسم فارغاً (الافتراض «-»).

    يُفتح في تبويبٍ جديد كي لا يضيع التقريرُ بفلاتره، ولا يُعرض الرابطُ لمن
    لا يملك صلاحية عرض المورّد (`purchases.suppliers.view`).
--}}
@php
    $spId = $id ?? null;
    $spName = trim((string) ($name ?? '')) !== '' ? $name : ($fallback ?? '-');
    $spCan = $spId && hasPermission('purchases.suppliers.view') && \Illuminate\Support\Facades\Route::has('SupplierManagement.show');
@endphp
@once
    <style>
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
        @media print { .rpt-client-link { border-bottom: 0; } }
    </style>
@endonce
@if ($spCan)<a href="{{ route('SupplierManagement.show', $spId) }}" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة المورد" onclick="event.stopPropagation()">{{ $spName }}</a>@else{{ $spName }}@endif
