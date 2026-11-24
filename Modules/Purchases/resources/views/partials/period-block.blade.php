{{--
    تنبيه الفترة المحاسبية المقفلة.

    يُقال قبل ملء المستند كلّه لا بعد الضغط على حفظ: الحارس في المتحكّم
    يردّ الطلب على أي حال، لكنّ ردَّه بعد نصف ساعةٍ من الإدخال ليس جواباً.

    نصُّ السبب من خدمة الفترات نفسِها — يسمّي الفترة وتاريخ إقفالها.
--}}
@if (! empty($periodBlock))
    <div class="alert alert-warning d-flex align-items-start" role="alert">
        <i class="feather icon-lock mt-1 mx-1"></i>
        <div>
            <strong class="d-block">{{ __('purchase_cycle.period_blocked') }}</strong>
            <span>{{ $periodBlock }}</span>
        </div>
    </div>
@endif
