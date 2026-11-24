{{--
    ترويسةُ شاشة تقرير العملاء — العنوانُ وأيقونتُه، ومسارُ الصفحة، وشارتا
    المدى والعملة، ولوحةُ الإرشاد المطويّة تحتها.

    أسماءُ المعاملات مسبوقةٌ بـ`head` كي لا يلتقط الـinclude متغيّراً بالاسم
    نفسِه من الشاشة (`$title`، `$extra`…) — انظر رأس partials.report-pager.

    @param string      $headIcon      اسم أيقونة Font Awesome 5 بلا البادئة (file-invoice-dollar)
    @param string      $headTitle     عنوان التقرير
    @param string|null $headSubtitle  سطرٌ يقول ما تعنيه أرقامُه
    @param string|null $headPeriod    وصف المدى بتاريخيه
    @param string|null $headExtra     شارةٌ إضافيةٌ اختيارية
    @param string|null $headGuideKey  مفتاحُ تذكّر لوحة الإرشاد لهذه الشاشة
    @param array|null  $headGuide     إرشادُ الشاشة نفسِها: title / lead / blocks / facts.
                                      بدونه تُعرض لوحةُ تقارير المبيعات العامة كما كانت.
    @param string|null $headCrumbLabel  أوّلُ المسار (الافتراض «تقارير العملاء») — تقاريرُ المبيعات تمرّر لوحتَها
    @param string|null $headCrumbUrl    رابطُه (الافتراض لوحة تقارير العملاء)
--}}
@php
    $rhCurrency = \App\Support\CurrencySettings::name();
    $rhIcon = is_string($headIcon ?? null) ? $headIcon : 'chart-bar';
    $rhTitle = (string) ($headTitle ?? '');
    $rhSubtitle = is_string($headSubtitle ?? null) ? $headSubtitle : '';
    $rhPeriod = is_string($headPeriod ?? null) ? $headPeriod : '';
    $rhExtra = is_string($headExtra ?? null) ? $headExtra : '';
    $rhGuideKey = is_string($headGuideKey ?? null) ? $headGuideKey : 'client-reports.screen';
    $rhCrumbLabel = is_string($headCrumbLabel ?? null) ? $headCrumbLabel : 'تقارير العملاء';
    $rhCrumbUrl = is_string($headCrumbUrl ?? null) ? $headCrumbUrl : route('ClientReport.index');
    $rhGuide = is_array($headGuide ?? null) ? $headGuide : [
        'title' => __('sales_reports.guide.hub.title'),
        'lead' => __('sales_reports.guide.hub.lead'),
        'blocks' => [[
            __('sales_reports.guide.hub.a'),
            __('sales_reports.guide.hub.b'),
            __('sales_reports.guide.hub.c'),
            __('sales_reports.guide.hub.d'),
        ]],
        'facts' => [],
    ];
@endphp

<div class="ag-head">
    <div class="ag-head__title">
        <span class="ag-head__icon"><i class="fas fa-{{ $rhIcon }}"></i></span>
        <div>
            <h1>{{ $rhTitle }}</h1>
            @if ($rhSubtitle !== '')
                <p>{{ $rhSubtitle }}</p>
            @endif
        </div>
    </div>
    <div class="ag-head__side no-print">
        <nav class="ag-crumbs" aria-label="مسار الصفحة">
            <a href="{{ $rhCrumbUrl }}">{{ $rhCrumbLabel }}</a>
            <i class="fas fa-chevron-left"></i>
            <span>{{ $rhTitle }}</span>
        </nav>
        <div class="ag-chips">
            @if ($rhPeriod !== '')
                <span class="ag-chip"><i class="fas fa-calendar-alt"></i> {{ $rhPeriod }}</span>
            @endif
            @if ($rhExtra !== '')
                <span class="ag-chip"><i class="fas fa-info-circle"></i> {{ $rhExtra }}</span>
            @endif
            <span class="ag-chip"><i class="fas fa-coins"></i> {{ $rhCurrency }}</span>
        </div>
    </div>
</div>

<div class="no-print">
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::components.guide', [
        'key' => $rhGuideKey,
        'title' => $rhGuide['title'] ?? '',
        'lead' => $rhGuide['lead'] ?? null,
        'open' => false,
        'blocks' => $rhGuide['blocks'] ?? [],
        'facts' => $rhGuide['facts'] ?? [],
    ])
</div>
