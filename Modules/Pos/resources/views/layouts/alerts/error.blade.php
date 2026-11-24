{{--
    رسالةُ الخطأ — انظر [success.blade.php] لسبب التغيير.

    و`error` وحدها لا تكفي: كثيرٌ من متحكّمات النظام تكتب `errors` جمعاً
    (رسالةً أو مصفوفة)، فكانت تُكتب في الجلسة ولا تُعرض إطلاقاً.
--}}
@if (Session::has('error') || Session::has('errors_message'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="feather icon-alert-circle"></i>
        {{ Session::get('error') ?: Session::get('errors_message') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
    </div>
@endif
