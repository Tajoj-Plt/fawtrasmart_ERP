{{--
    رسالةُ النجاح.

    كانت `alert-success text-xl-center` بلا أيقونة ولا زرِّ إغلاق: تبقى في
    الشاشة حتى إعادة التحميل، وتُزيح المحتوى تحتها كلَّما ظهرت. صارت على
    نمط بقيّة الوحدات — أيقونةٌ وزرُّ إغلاق.
--}}
@if (Session::has('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="feather icon-check-circle"></i>
        {{ Session::get('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
    </div>
@endif
