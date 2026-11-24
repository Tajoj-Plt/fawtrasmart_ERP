{{--
    قالبُ مطبوعات الإقفال اليومي — صفحةٌ مستقلّة تُفتح في تبويبٍ جديد.

    المطبوعاتُ الثلاثة كانت ثلاثَ صفحاتٍ مستقلّة، لكلٍّ منها نحو أربعمئة
    سطرٍ من CSS مكتوبٍ بيده: ألوانٌ ثابتة (`#333`، `#059669`، تدرّجاتٌ
    بنفسجية) لا تتبع مظهر النظام، وخطُّ `Tahoma` مكتوبٌ نصّاً لا خطُّ
    المنشأة المضبوط، وترويسةٌ بلا شعارٍ ولا رقمٍ ضريبي، ورمزُ ريالٍ
    مكتوبٌ في كلّ خلية. وكان أحدُها يضبط هامش الصفحة `12mm` والآخر `10mm`
    والثالث لا يضبط شيئاً.

    صار واحداً. ومصادرُ ما فيه كلُّها شاشاتُ الإعدادات:

        App\Support\ThemeSettings     ← «المظهر»: الألوان والخطّ ونصف القطر
        App\Support\BusinessInfo      ← «معلومات الحساب»: الاسم والشعار والعنوان والرقم الضريبي
        App\Support\CurrencySettings  ← «العملات»: الرمز واسم العملة
        App\Support\LanguageSettings  ← «اللغات»: الاتجاه وتنسيق التاريخ والوقت
        App\Support\PrintSettings     ← الختم والتوقيع

    @param string      $documentTitle  عنوان المطبوع
    @param string|null $periodLabel    وصف الفترة
    @param array       $filters        ['التسمية' => 'القيمة']
--}}
@php
    use App\Support\BusinessInfo;
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;
    use App\Support\PrintSettings;
    use App\Support\ThemeSettings;

    $direction = LanguageSettings::direction();
    $printedAt = now()->format(LanguageSettings::dateFormat().' '.LanguageSettings::timeFormat());
    $appliedFilters = collect($filters ?? [])->filter(fn ($value) => filled($value));
    $fontUrl = ThemeSettings::fontUrl();
@endphp
<!DOCTYPE html>
<html dir="{{ $direction }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }}</title>

    @if ($fontUrl)
        <link rel="stylesheet" href="{{ $fontUrl }}">
    @endif

    <style>
        /* متغيّرات المظهر — بها تتبع ألوانُ المطبوع مظهرَ النظام.

           `cssVariables()` تُرجع `:root{…}` نصّاً بلا وسم `<style>` حوله
           (وهكذا يستعملها المثبِّت وأنماطُ اللغة: يضعانها داخل وسمٍ عندهما).
           وكانت هنا تُطبع في `<head>` عاريةً، فيخرجها المحلّل إلى الجسم نصّاً
           ظاهراً — سطرُ `:root{--app-primary:#7367F0;…}` الذي كان يتصدّر
           كلَّ مطبوعات الإقفال قبل ترويستها. */
        {!! ThemeSettings::cssVariables() !!}

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--app-font-family, 'Cairo', 'Tajawal', 'Segoe UI', Tahoma, sans-serif);
            background: #fff;
            color: #1a1a1a;
            line-height: 1.55;
            padding: 16px;
            font-size: 12px;
        }

        /* ══════════ الترويسة ══════════ */
        .pr-head { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 16px; }
        .pr-head__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
        .pr-head__logo { max-height: 64px; max-width: 165px; object-fit: contain; }
        .pr-head__org { display: flex; flex-direction: column; gap: 2px; flex: 1; }
        .pr-head__org strong { font-size: 15px; }
        .pr-head__org span { font-size: 11px; color: #444; }
        .pr-head__stamp { display: flex; flex-direction: column; gap: 2px; text-align: end; font-size: 10px; color: #444; }
        .pr-head__title { text-align: center; font-size: 17px; font-weight: 800; margin: 12px 0 4px; }
        .pr-head__period { text-align: center; font-size: 11px; color: #444; }
        .pr-head__filters {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 4px 14px;
            font-size: 10px; color: #444; margin-top: 6px;
        }

        /* ══════════ التذييل ══════════ */
        .pr-foot { margin-top: 20px; padding-top: 10px; border-top: 1px solid #999; break-inside: avoid; }
        .pr-foot__marks { display: flex; justify-content: space-around; gap: 20px; }
        .pr-foot__marks > div { text-align: center; flex: 1; font-size: 10px; color: #444; }
        .pr-foot__marks span { display: block; margin-bottom: 4px; }
        .pr-foot__marks img { max-height: 56px; max-width: 130px; object-fit: contain; }
        .pr-foot__marks i { display: block; height: 40px; border-bottom: 1px solid #666; }
        .pr-foot__org { text-align: center; font-size: 10px; color: #666; margin-top: 10px; }

        /* ══════════ كتل التفاصيل ══════════ */
        .rpt-block { border: 1px solid #ccc; border-radius: 6px; margin-bottom: 12px; overflow: hidden; break-inside: avoid; }
        .rpt-block > header {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 8px; padding: 8px 12px; background: #f4f4f4; border-bottom: 1px solid #ccc;
        }
        .rpt-block > header h5 { font-size: 13px; font-weight: 800; }

        .rpt-mini { display: flex; flex-wrap: wrap; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 12px; break-inside: avoid; }
        .rpt-mini > div { flex: 1 1 110px; padding: 8px 10px; text-align: center; border-inline-end: 1px solid #ddd; }
        .rpt-mini > div:last-child { border-inline-end: none; }
        .rpt-mini small { display: block; font-size: 10px; color: #666; }
        .rpt-mini strong { font-size: 13px; direction: ltr; display: block; margin-top: 2px; font-variant-numeric: tabular-nums; }

        /* ══════════ الجداول ══════════ */
        .rpt-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .rpt-table thead th {
            background: #eee; font-weight: 700; padding: 7px 6px; text-align: center;
            border: 1px solid #bbb; font-size: 10px; white-space: nowrap;
        }
        .rpt-table tbody td { padding: 6px; text-align: center; border: 1px solid #ddd; vertical-align: middle; }
        .rpt-table tfoot td { padding: 7px 6px; text-align: center; background: #f4f4f4; font-weight: 800; border: 1px solid #bbb; }
        .rpt-num { direction: ltr; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .rpt-name { text-align: start !important; }
        .rpt-scroll { overflow-x: auto; }
        .rpt-empty { text-align: center; padding: 16px; color: #888; font-size: 11px; }

        /* ══════════ الشارات والألوان ══════════ */
        .rpt-pill { display: inline-block; padding: 2px 8px; border: 1px solid #bbb; border-radius: 999px; font-size: 10px; font-weight: 700; }
        .rpt-pos   { color: #0a7c46; }
        .rpt-neg   { color: #b02a2b; }
        .rpt-warnc { color: #a35b00; }
        .rpt-infoc { color: #0b6c7d; }
        .rpt-zero  { color: #999; }
        .rpt-pill-secondary { background: #f4f4f4; }
        .rpt-pill-warning { background: #fff4e5; color: #a35b00; }
        .rpt-pill-success { background: #e8f7ef; color: #0a7c46; border-color: #9fd7b9; }
        .rpt-pill-danger  { background: #fdecec; color: #b02a2b; border-color: #efb3b3; }

        /* ══════════ صفحة الموظف ══════════ */
        .pr-employee { break-inside: auto; }
        .pr-employee + .pr-employee { break-before: page; page-break-before: always; padding-top: 12px; }
        .pr-employee__head {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            background: #f4f4f4; border: 1px solid #ccc; border-radius: 6px;
            padding: 9px 14px; margin-bottom: 10px;
        }
        .pr-employee__head h3 { font-size: 14px; font-weight: 800; }
        .pr-employee__head span { font-size: 11px; color: #555; }

        /* ══════════ أزرار الشاشة ══════════ */
        .pr-actions {
            position: sticky; top: 0; z-index: 10;
            display: flex; gap: 8px; justify-content: center;
            padding: 10px; margin-bottom: 14px; background: #fff; border-bottom: 1px solid #ddd;
        }
        .pr-actions button {
            border: 1px solid var(--app-primary, #7367f0);
            background: var(--app-primary, #7367f0); color: #fff;
            padding: 7px 20px; border-radius: 6px; cursor: pointer;
            font-family: inherit; font-size: 12px; font-weight: 700;
        }
        .pr-actions button.is-ghost { background: transparent; color: var(--app-primary, #7367f0); }

        @media print {
            body { padding: 0; }
            .pr-actions { display: none !important; }
            .rpt-table thead { display: table-header-group; }
            @page { size: A4; margin: 12mm 9mm; }
        }
    </style>
</head>
<body>

    <div class="pr-actions">
        <button type="button" onclick="window.print()">{{ __('sales_settings.ui.print') }}</button>
        <button type="button" class="is-ghost" onclick="window.close()">{{ __('sales_settings.ui.back') }}</button>
    </div>

    <div class="pr-head">
        <div class="pr-head__top">
            @if (BusinessInfo::logo())
                <img src="{{ BusinessInfo::logo() }}" alt="{{ BusinessInfo::name() }}" class="pr-head__logo">
            @endif

            <div class="pr-head__org">
                <strong>{{ BusinessInfo::name() }}</strong>
                @if (BusinessInfo::address())
                    <span>{{ BusinessInfo::address() }}</span>
                @endif
                <span>
                    @if (BusinessInfo::phone()) {{ BusinessInfo::phone() }} @endif
                    @if (BusinessInfo::email()) · {{ BusinessInfo::email() }} @endif
                </span>
                <span>
                    @if (BusinessInfo::taxNumber())
                        {{ __('sales_daily_closing.print.tax_number') }}: {{ BusinessInfo::taxNumber() }}
                    @endif
                    @if (BusinessInfo::commercialRegistration())
                        · {{ BusinessInfo::commercialRegistration() }}
                    @endif
                </span>
            </div>

            <div class="pr-head__stamp">
                <span>{{ $printedAt }}</span>
                <span>{{ auth()->user()->name ?? '—' }}</span>
                <span>{{ CurrencySettings::name() }} ({{ CurrencySettings::symbol() }})</span>
            </div>
        </div>

        <h1 class="pr-head__title">{{ $documentTitle }}</h1>

        @if (! empty($periodLabel))
            <p class="pr-head__period">{{ $periodLabel }}</p>
        @endif

        @if ($appliedFilters->isNotEmpty())
            <p class="pr-head__filters">
                @foreach ($appliedFilters as $label => $value)
                    <span><b>{{ $label }}:</b> {{ $value }}</span>
                @endforeach
            </p>
        @endif
    </div>

    @yield('report')

    <div class="pr-foot">
        <div class="pr-foot__marks">
            <div>
                <span>{{ __('sales_daily_closing.fields.employee') }}</span>
                @if (PrintSettings::signatureUrl())
                    <img src="{{ PrintSettings::signatureUrl() }}" alt="">
                @else
                    <i></i>
                @endif
            </div>
            <div>
                <span>{{ BusinessInfo::name() }}</span>
                @if (PrintSettings::stampUrl())
                    <img src="{{ PrintSettings::stampUrl() }}" alt="">
                @else
                    <i></i>
                @endif
            </div>
            <div>
                <span>{{ __('sales_settings.ui.status') }}</span>
                <i></i>
            </div>
        </div>

        <p class="pr-foot__org">{{ BusinessInfo::name() }}{{ BusinessInfo::website() ? ' — '.BusinessInfo::website() : '' }}</p>
    </div>

</body>
</html>
