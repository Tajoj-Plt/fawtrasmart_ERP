{{--
    ترويسة المطبوعات — بيانات المنشأة من شاشة «معلومات الحساب».

    كانت هذه البيانات مكتوبةً نصّاً ثابتاً في كل قالب طباعة، فلا يتغيّر
    المطبوع بتغيير الإعدادات. صارت من مصدر واحد: `App\Support\BusinessInfo`.

    @param string|null $documentTitle   عنوان المستند فوق اسم المنشأة
    @param bool $showLogo               إخفاؤه في القوالب الحرارية الضيّقة
--}}
@php
    use App\Support\BusinessInfo;

    $business = BusinessInfo::all();
    $showLogo = $showLogo ?? true;
@endphp

<div class="print-header">
    @if ($showLogo && BusinessInfo::logo())
        <img src="{{ BusinessInfo::logo() }}" alt="{{ BusinessInfo::name() }}" class="print-header__logo"
            style="max-height: 70px; max-width: 180px;">
    @endif

    @if (! empty($documentTitle))
        <h3 class="print-header__title">{{ $documentTitle }}</h3>
    @endif

    <p class="print-header__name"><strong>{{ BusinessInfo::name() }}</strong></p>

    @if (BusinessInfo::address())
        <p class="print-header__line">{{ BusinessInfo::address() }}</p>
    @endif

    @if (BusinessInfo::phone())
        <p class="print-header__line">{{ __('purchase_cycle.print.phone') }}: {{ BusinessInfo::phone() }}</p>
    @endif

    @if (BusinessInfo::email())
        <p class="print-header__line">{{ BusinessInfo::email() }}</p>
    @endif

    @if (BusinessInfo::taxNumber())
        <p class="print-header__line">{{ __('purchase_cycle.print.tax_number') }}: {{ BusinessInfo::taxNumber() }}</p>
    @endif

    @if (BusinessInfo::commercialRegistration())
        <p class="print-header__line">
            {{ __('purchase_cycle.print.commercial_registration') }}: {{ BusinessInfo::commercialRegistration() }}
        </p>
    @endif
</div>
