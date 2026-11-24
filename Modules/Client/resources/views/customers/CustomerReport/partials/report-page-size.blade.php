{{--
    اختيارُ عدد الصفوف في الصفحة — فوق الجدول بجانب أزرار الطباعة والتصدير
    (طلبُ المستخدم: «تكون فوق الجدول بجانب أزرار الطباعة والإكسل»).

    يوضع داخل `ag-tools` في رأس بطاقة الجدول، ويرتبط بشريط الترقيم أسفل
    الجدول بالمعرّف: `{pagerId}PageSize` ↔ `{pagerId}PagerBar`.

    @param string $pagerId  بادئةُ المعرّفات نفسُها الممرَّرة إلى partials.report-pager (الافتراض `ag`)
--}}
@php
    $psId = is_string($pagerId ?? null) ? $pagerId : 'ag';
@endphp

<label class="ag-page-size no-print" for="{{ $psId }}PageSize">
    <span>عدد الصفوف</span>
    <select id="{{ $psId }}PageSize" class="form-control">
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
        <option value="0">الكل</option>
    </select>
</label>
