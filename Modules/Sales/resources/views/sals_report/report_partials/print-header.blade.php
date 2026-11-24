{{--
    ترويسة مطبوعات تقارير المبيعات — بيانات المنشأة والفترة والفلاتر.

    الترويسة جزءٌ من الصفحة نفسها، مخفيّةٌ على الشاشة وظاهرةٌ عند الطباعة
    (`.rpt-print-only`)، ومصادرها كلّها من شاشات الإعدادات:

        App\Support\BusinessInfo      ← «معلومات الحساب»: الاسم والشعار والعنوان والرقم الضريبي
        App\Support\CurrencySettings  ← «العملات»: العملة المعتمدة واسمها
        App\Support\LanguageSettings  ← «اللغات»: تنسيق التاريخ والوقت

    @param string      $documentTitle  عنوان التقرير
    @param string|null $periodLabel    وصف الفترة بتاريخيها
    @param array       $filters        ['التسمية' => 'القيمة'] للفلاتر المطبَّقة
--}}
@php
    use App\Support\BusinessInfo;
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    $printedAt = now();
    $stamp = $printedAt->format(LanguageSettings::dateFormat().' '.LanguageSettings::timeFormat());
    $appliedFilters = collect($filters ?? [])->filter(fn ($v) => filled($v));
@endphp

<div class="rpt-print-only rpt-printhead">
    <div class="rpt-printhead__top">
        @if (BusinessInfo::logo())
            <img src="{{ BusinessInfo::logo() }}" alt="{{ BusinessInfo::name() }}" class="rpt-printhead__logo">
        @endif

        <div class="rpt-printhead__org">
            <strong>{{ BusinessInfo::name() }}</strong>
            @if (BusinessInfo::address())
                <span>{{ BusinessInfo::address() }}</span>
            @endif
            <span>
                @if (BusinessInfo::phone()) {{ __('sales_reports.common.phone') }}: {{ BusinessInfo::phone() }} @endif
                @if (BusinessInfo::email()) · {{ BusinessInfo::email() }} @endif
            </span>
            <span>
                @if (BusinessInfo::taxNumber())
                    {{ __('sales_reports.common.tax_number') }}: {{ BusinessInfo::taxNumber() }}
                @endif
                @if (BusinessInfo::commercialRegistration())
                    · {{ __('sales_reports.common.commercial_registration') }}: {{ BusinessInfo::commercialRegistration() }}
                @endif
            </span>
        </div>

        <div class="rpt-printhead__stamp">
            <span>{{ __('sales_reports.common.printed_at') }}: {{ $stamp }}</span>
            <span>{{ __('sales_reports.common.printed_by') }}: {{ auth()->user()->name ?? '—' }}</span>
            <span>{{ __('sales_reports.common.currency') }}: {{ CurrencySettings::name() }} ({{ CurrencySettings::symbol() }})</span>
        </div>
    </div>

    <h2 class="rpt-printhead__title">{{ $documentTitle }}</h2>

    @if (! empty($periodLabel))
        <p class="rpt-printhead__period">{{ $periodLabel }}</p>
    @endif

    @if ($appliedFilters->isNotEmpty())
        <p class="rpt-printhead__filters">
            @foreach ($appliedFilters as $label => $value)
                <span><b>{{ $label }}:</b> {{ $value }}</span>
            @endforeach
        </p>
    @endif
</div>

<style>
    @media print {
        .rpt-printhead {
            border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 14px;
            color: #000; font-size: 11px;
        }
        .rpt-printhead__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
        .rpt-printhead__logo { max-height: 62px; max-width: 160px; object-fit: contain; }
        .rpt-printhead__org { display: flex; flex-direction: column; gap: 2px; flex: 1; }
        .rpt-printhead__org strong { font-size: 15px; }
        .rpt-printhead__stamp { display: flex; flex-direction: column; gap: 2px; text-align: end; font-size: 10px; color: #444; }
        .rpt-printhead__title { text-align: center; font-size: 17px; font-weight: 800; margin: 12px 0 4px; }
        .rpt-printhead__period { text-align: center; font-size: 11px; color: #444; margin: 0; }
        .rpt-printhead__filters {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 4px 14px;
            font-size: 10px; color: #444; margin: 6px 0 0;
        }
    }
</style>
