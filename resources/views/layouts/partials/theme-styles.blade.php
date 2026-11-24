{{--
    مظهر النظام — الألوان والخط والشكل كما ضُبطت في شاشة «مظهر النظام».

    لا يُطبع شيء إن لم يُضبط أي إعداد، فيبقى مظهر القالب الأصلي كما هو.
    تُعرَّف متغيّرات CSS ثم تُطبَّق بقواعد تشير إليها فقط — لا تُعدَّل أي
    ورقة أنماط أصلية، وأي نمط أخصّ في الصفحة يظل قادراً على تجاوزها.
--}}
@php
    $themeCss = \App\Support\ThemeSettings::css();
    $themeFontUrl = \App\Support\ThemeSettings::fontUrl();
@endphp

@if ($themeFontUrl)
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $themeFontUrl }}" rel="stylesheet">
@endif

@if (filled($themeCss))
    <style id="app-theme">{!! $themeCss !!}</style>
@endif
