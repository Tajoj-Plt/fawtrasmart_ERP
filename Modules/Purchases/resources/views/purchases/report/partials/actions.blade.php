{{--
    صفّ أزرار الإجراءات — بنفس ترتيب شاشات المبيعات ومقاساتها:
    بحث / إلغاء البحث / طباعة / Excel، ثم مجموعة نوع العرض إلى الطرف.

    زرّ الطباعة يطبع الصفحة نفسها بقواعد `@media print` المشتركة، فيخرج
    المطبوع بترويسة المنشأة وشعارها وعملتها. سُنِد إليه `rptPrintAction`
    ويُربط في `partials/scripts`.

    @param string      $route        اسم مسار التقرير (لزرّ الإلغاء وروابط العرض)
    @param array       $query        معاملات الرابط الحالية
    @param string|null $exportRoute  اسم مسار التصدير — يُخفى الزرّ إن لم يُمرَّر
    @param string|null $viewType     نوع العرض الحالي
    @param array       $views        ['key' => ['label' => …, 'icon' => …]] — فارغة تعني بلا مجموعة
--}}
@php
    $views = $views ?? [];
    $exportRoute = $exportRoute ?? null;
    $viewType = $viewType ?? null;
@endphp

<div class="rpt-actions mt-2">
    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>

    <a href="{{ route($route) }}" class="btn btn-outline-secondary">
        <i class="fa fa-refresh"></i> إلغاء البحث
    </a>

    <button type="button" id="rptPrintAction" class="btn btn-success">
        <i class="fa fa-print"></i> طباعة
    </button>

    {{--
        Excel يُبنى في المتصفّح بـ`excel-export-helper.js` من بيانات مرسَلة
        من الخادم (جزئية `excel`)، فيخرج ملفاً منسَّقاً بترويسة وإحصاءات لا
        نصّاً خاماً. و CSV يبقى إلى جانبه لمن يريد ملفاً خفيفاً أو يصدّر
        فترةً كبيرة، فالتصدير عندها يتدفّق من الخادم.
    --}}
    <button type="button" id="rptExcelAction" class="btn btn-info">
        <i class="fa fa-file-excel-o"></i> Excel
    </button>

    @if ($exportRoute)
        <a href="{{ route($exportRoute, $query) }}" class="btn btn-outline-info" title="تصدير CSV خام">
            <i class="fa fa-file-text-o"></i> CSV
        </a>
    @endif

    @if ($views !== [])
        <div class="btn-group ml-auto" role="group">
            @foreach ($views as $key => $view)
                <a href="{{ route($route, array_merge($query, ['view_type' => $key])) }}"
                   class="btn {{ $viewType === $key ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle">
                    <i class="fa fa-{{ $view['icon'] }}"></i> {{ $view['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</div>
