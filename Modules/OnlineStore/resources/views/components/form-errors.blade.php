{{--
    أخطاء التحقّق.

    `layouts.alerts.error` لا يعرض إلّا رسالة الجلسة، فأخطاء التحقّق كانت
    تعود من الخادم ولا يظهر منها شيء — يبدو للمستخدم أنّ الزرّ لا يعمل.
--}}
@if (isset($errors) && $errors->any())
    <div class="alert alert-danger" role="alert">
        <p class="mb-1">
            <i class="feather icon-alert-circle"></i>
            <strong>تعذّر الحفظ — راجع الحقول التالية:</strong>
        </p>

        <ul class="mb-0 ps-3 pe-3">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
