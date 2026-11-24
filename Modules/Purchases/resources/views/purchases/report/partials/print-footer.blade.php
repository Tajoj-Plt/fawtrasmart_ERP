{{--
    تذييل مطبوعات التقارير — الختم والتوقيع واسم المنشأة.

    الختم والتوقيع مرفوعان في شاشة «معلومات الحساب» ويقرأهما
    `App\Support\PrintSettings`؛ من لم يرفعهما يحصل على سطر توقيعٍ فارغ
    بدل صورةٍ مفقودة.
--}}
@php
    use App\Support\BusinessInfo;
    use App\Support\PrintSettings;

    $stamp = PrintSettings::stampUrl();
    $signature = PrintSettings::signatureUrl();
@endphp

<div class="rpt-print-only rpt-printfoot">
    <div class="rpt-printfoot__marks">
        <div>
            <span>المُعِدّ</span>
            @if ($signature)
                <img src="{{ $signature }}" alt="">
            @else
                <i></i>
            @endif
        </div>
        <div>
            <span>الختم</span>
            @if ($stamp)
                <img src="{{ $stamp }}" alt="">
            @else
                <i></i>
            @endif
        </div>
        <div>
            <span>المعتمِد</span>
            <i></i>
        </div>
    </div>

    <p class="rpt-printfoot__org">{{ BusinessInfo::name() }}{{ BusinessInfo::website() ? ' — '.BusinessInfo::website() : '' }}</p>
</div>

<style>
    @media print {
        .rpt-printfoot { margin-top: 18px; padding-top: 10px; border-top: 1px solid #999; break-inside: avoid; }
        .rpt-printfoot__marks { display: flex; justify-content: space-around; gap: 20px; }
        .rpt-printfoot__marks > div { text-align: center; flex: 1; font-size: 10px; color: #444; }
        .rpt-printfoot__marks span { display: block; margin-bottom: 4px; }
        .rpt-printfoot__marks img { max-height: 56px; max-width: 130px; object-fit: contain; }
        /* سطر توقيعٍ فارغ حين لا صورة — الورق يحتاج مكاناً يُوقَّع فيه */
        .rpt-printfoot__marks i { display: block; height: 40px; border-bottom: 1px solid #666; }
        .rpt-printfoot__org { text-align: center; font-size: 10px; color: #666; margin: 10px 0 0; }
    }
</style>
