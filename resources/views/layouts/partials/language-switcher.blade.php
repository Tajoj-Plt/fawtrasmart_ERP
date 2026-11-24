{{--
    مبدّل لغة الواجهة — مصدره جدول system_languages.

    أي لغة تُضاف من شاشة «إعدادات اللغات» تظهر هنا فوراً، والمعطّلة تختفي
    دون أن تُمسّ بياناتها. العلم يأتي من عمود flag ويقبل شكلين:
      - اسم ملف مثل «sa.svg» → أيقونة flag-icon-sa
      - رمز Emoji → يُعرض كما هو
    وإن غاب الاثنان يُعرض رمز اللغة نصاً.

    الاستعمال: @include('layouts.partials.language-switcher')
--}}
@php
    $currentLocale = app()->getLocale();

    // لغات هذا الطلب فقط: قائمة اللغات المدعومة تُبنى في boot()، فلغة أُضيفت
    // بعد ذلك لا يستطيع laravel-localization توليد رابط لها ويرمي استثناء.
    // تظهر في الطلب التالي مباشرة (بعد إعادة التوجيه) وهو المسار الطبيعي.
    $routableLocales = array_keys(LaravelLocalization::getSupportedLocales());

    $switcherLanguages = app(\Modules\Sitting\Repositories\LanguageRepository::class)
        ->getActiveLanguages()
        ->filter(fn ($language) => in_array($language->code, $routableLocales, true))
        ->values();
@endphp

@if ($switcherLanguages->count() > 1)
    <li class="dropdown dropdown-language nav-item">
        <a class="dropdown-toggle nav-link" id="dropdown-flag" href="#" data-toggle="dropdown"
            aria-haspopup="true" aria-expanded="false">
            <i class="ficon feather icon-globe"></i>
            <span class="selected-language">
                @php $active = $switcherLanguages->firstWhere('code', $currentLocale); @endphp
                {{ $active?->native_name ?: ($active?->name ?? strtoupper($currentLocale)) }}
            </span>
        </a>
        <div class="dropdown-menu" aria-labelledby="dropdown-flag">
            @foreach ($switcherLanguages as $switcherLanguage)
                @php $iconCode = $switcherLanguage->flagIconCode(); @endphp
                <a class="dropdown-item {{ $switcherLanguage->code === $currentLocale ? 'active' : '' }}"
                    hreflang="{{ $switcherLanguage->code }}"
                    data-language="{{ $switcherLanguage->code }}"
                    href="{{ LaravelLocalization::getLocalizedURL($switcherLanguage->code, null, [], true) }}">
                    @if ($iconCode)
                        <i class="flag-icon flag-icon-{{ $iconCode }}"></i>
                    @elseif ($switcherLanguage->flagIsText())
                        <span style="font-size: 1.1rem;">{{ $switcherLanguage->flag }}</span>
                    @else
                        <span class="badge badge-light-primary">{{ strtoupper($switcherLanguage->code) }}</span>
                    @endif
                    {{ $switcherLanguage->native_name ?: $switcherLanguage->name }}
                </a>
            @endforeach
        </div>
    </li>
@endif
