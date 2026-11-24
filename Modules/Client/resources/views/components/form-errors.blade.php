{{--
    أخطاء التحقّق — نظير `stock::components.form-errors`.

    `layouts.alerts.error` لا يعرض إلّا رسالة الجلسة، فأخطاء التحقّق كانت
    تعود من الخادم ولا يظهر منها شيء: يبدو للمستخدم أنّ الزرّ لا يعمل.
--}}
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0" style="padding-inline-start:1.1rem">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
