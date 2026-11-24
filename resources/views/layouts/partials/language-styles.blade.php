{{--
    أنماط اللغة الحالية — الخط وحجمه واللون المميّز كما ضُبطت في شاشة إعدادات اللغات.

    لا يُطبع شيء إن لم تُضبط هذه الحقول، فلا يُفرض تغيير على التصميم القائم.
    ولا تُلمس قواعد التصميم الأصلية: تُعرَّف متغيّرات CSS فقط ثم تُطبَّق على
    الجسم بأولوية منخفضة، فيبقى بإمكان أي نمط أخصّ تجاوزها.
--}}
@php
    $langCss = $languageCss ?? \App\Support\LanguageSettings::cssVariables();
@endphp

@if (filled($langCss))
    <style>
        {!! $langCss !!}

        body {
            font-family: var(--app-font-family, inherit);
            font-size: var(--app-font-size, inherit);
        }
    </style>
@endif
