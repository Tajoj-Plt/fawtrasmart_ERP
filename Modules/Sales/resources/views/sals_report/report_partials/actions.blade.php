{{--
    صفّ أزرار الإجراءات — بنفس ترتيب شاشات المشتريات ومقاساتها:
    بحث / إلغاء البحث / طباعة / Excel، ثم مجموعة نوع العرض إلى الطرف.

    زرّا العرض يبقيان `type="submit" name="view_type"` كما كانا، فلا يتغيّر
    شيء في آلية الفلترة — التغيير في الشكل فقط.

    زرّ الطباعة يطبع الصفحة نفسها بقواعد `@media print` المشتركة، فيخرج
    المطبوع بترويسة المنشأة وشعارها وعملتها.

    @param string      $resetUrl   رابط إلغاء البحث
    @param bool        $isSummary  هل العرض الحالي ملخص؟
    @param bool|null   $showViews  إظهار مجموعة نوع العرض (افتراضاً نعم)
--}}
@php
    $showViews = $showViews ?? true;
@endphp

<div class="rpt-actions mt-2">
    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> {{ __('sales_reports.common.search') }}</button>

    <a href="{{ $resetUrl }}" class="btn btn-outline-secondary">
        <i class="fa fa-refresh"></i> {{ __('sales_reports.common.reset') }}
    </a>

    <button type="button" id="printBtn" class="btn btn-success">
        <i class="fa fa-print"></i> {{ __('sales_reports.common.print') }}
    </button>

    <button type="button" id="exportExcel" class="btn btn-info">
        <i class="fa fa-file-excel-o"></i> {{ __('sales_reports.common.excel') }}
    </button>

    @if ($showViews)
        <div class="btn-group ml-auto" role="group">
            <button type="submit" name="view_type" value="summary"
                    class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle">
                <i class="fa fa-list"></i> {{ __('sales_reports.common.summary') }}
            </button>
            <button type="submit" name="view_type" value="details"
                    class="btn {{ ! $isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle">
                <i class="fa fa-th-list"></i> {{ __('sales_reports.common.details') }}
            </button>
        </div>
    @endif
</div>
