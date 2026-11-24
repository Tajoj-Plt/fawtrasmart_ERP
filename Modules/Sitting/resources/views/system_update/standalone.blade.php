{{--
    قالب شاشة التحديث حين تُفتَح **بلا تسجيل دخول**.

    لماذا قالبٌ منفصل؟ لأن `sitting::master` يستدعي `layouts.header`، والهيدر
    يقرأ `auth()->user()->branch_id` بلا حارس — فالشاشة التي فُتحت للزائر عمداً
    كانت تسقط بخطأ «قراءة خاصية على null» قبل أن يرى منها سطراً. وإصلاحُ
    الهيدر كلِّه لأجل هذه الحالة تعديلٌ في قالبٍ تستعمله عشرون وحدة.

    وهو مقصورٌ على رأس الصفحة وذيلها: لا قائمة جانبية ولا شريط علوي — لا شيء
    منهما يعمل بلا مستخدم، ولا حاجة إليهما في شاشةٍ غرضُها زرٌّ واحد.
--}}
<!DOCTYPE html>
<html class="loading" dir="{{ $languageDirection ?? (in_array(App::getLocale(), ['ar', 'ur']) ? 'rtl' : 'ltr') }}">

@if (($languageDirection ?? (in_array(App::getLocale(), ['ar', 'ur']) ? 'rtl' : 'ltr')) === 'rtl')
    @include('layouts.head_rtl')
@else
    @include('layouts.head_ltr')
@endif

<body class="vertical-layout vertical-menu-modern 2-columns navbar-floating footer-static"
      style="background:#f4f6f9">

    <div class="app-content content" style="margin:0;padding:1.5rem">
        <div class="content-wrapper" style="max-width:1100px;margin:0 auto">

            <div style="display:flex;align-items:center;gap:.7rem;margin-bottom:1.2rem">
                <i class="feather icon-download-cloud" style="font-size:1.6rem;color:#2b6cb0"></i>
                <div>
                    <h3 style="margin:0;font-weight:700">تحديث النظام</h3>
                    <p style="margin:.15rem 0 0;font-size:.85rem;color:#6b7280">
                        النظام مرفوعٌ عليه تحديثٌ لم يُنفَّذ بعد، ولا تُفتَح شاشاته حتى يُنفَّذ.
                    </p>
                </div>
                <span style="flex:1"></span>
                <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="feather icon-log-in"></i> تسجيل الدخول
                </a>
            </div>

            @yield('content')
        </div>
    </div>

    <script src="{{ asset('app-assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('app-assets/js/core/app-menu.js') }}?v=20260923.1"></script>
    <script src="{{ asset('app-assets/js/core/app.js') }}?v=20260923.1"></script>
    @yield('scripts')
</body>

</html>
