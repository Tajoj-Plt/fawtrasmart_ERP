{{--
    ترويسة المنشأة في مستندات الطباعة و PDF.

    كانت أوراق المخزون تخرج بعنوان التقرير وحده: «تقرير المنتجات»،
    «إذن مخزني»، «قيمة المخزون التقديرية» — بلا اسم المنشأة ولا عنوانها
    ولا رقمها الضريبي. ورقةٌ تخرج من النظام ولا يُعرف من أصدرها، ولا
    تصلح مرفقاً في مراسلةٍ ولا في مراجعة.

    البيانات من «إعدادات المنشأة» عبر `OrganizationInfo` — المصدر نفسه
    الذي يقرأ منه مُصيّر قوالب المستندات، فلا تفترق ورقةٌ عن ورقة.

    الاستعمال داخل `<body>` قبل عنوان التقرير:
        @include('stock::components.print-org-head')
--}}

@php
    $printOrgName = \App\Support\OrganizationInfo::name();
    $printOrgAddress = \App\Support\OrganizationInfo::address();
    $printOrgIdentity = \App\Support\OrganizationInfo::identityLine();
@endphp

<div class="print-org" style="text-align:center;margin-bottom:10px">
    <div style="font-size:15pt;font-weight:700">{{ $printOrgName }}</div>

    @if ($printOrgAddress)
        <div style="font-size:9pt;color:#444">{{ $printOrgAddress }}</div>
    @endif

    @if ($printOrgIdentity)
        <div style="font-size:9pt;color:#444">{{ $printOrgIdentity }}</div>
    @endif
</div>
