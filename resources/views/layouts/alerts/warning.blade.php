{{--
    تنبيهٌ لِما تمّ لكنّه يحتاج خطوةً تالية.

    كان في النظام قالبان فقط: `success` و`error`. فكل عمليةٍ نجحت جزئياً —
    كتسوية جردٍ أصدرت أذوناً تنتظر الاعتماد — لم تجد قناةً تُبلَّغ بها: إن
    استُعملت `success` قُرئت انتهاءً تامّاً، وإن استُعملت `warning` ضاعت
    الرسالة بلا قالبٍ يعرضها.
--}}
@if (Session::has('warning'))
    <div class="alert alert-warning" role="alert">
        <p class="mb-0">
            <i class="feather icon-alert-triangle"></i>
            {{ Session::get('warning') }}
        </p>
    </div>
@endif

@if (Session::has('info'))
    <div class="alert alert-info" role="alert">
        <p class="mb-0">
            <i class="feather icon-info"></i>
            {{ Session::get('info') }}
        </p>
    </div>
@endif
