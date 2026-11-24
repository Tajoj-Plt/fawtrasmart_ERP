{{--
    أخطاء التحقّق.

    `layouts.alerts.error` لا يعرض إلّا رسالة الجلسة، فأخطاء التحقّق كانت
    تعود من الخادم ولا يظهر منها شيء — يبدو للمستخدم أنّ الزرّ لا يعمل.
--}}

{{--
    `$errors` تُشاركها وسيطة `ShareErrorsFromSession` في طلبات المتصفّح،
    فتكون موجودةً دائماً هناك. لكنّ القالب يُصيَّر أيضاً خارج تلك السلسلة —
    من مهمّةٍ تولّد PDF، أو من فحصٍ يستدعي الفعل مباشرةً — فتكون `null`
    ويسقط النداء عليها. وهذا المكوّن مُضمَّنٌ في القالب الرئيسي، فسقوطه
    يُسقط الصفحة كلّها لا سطراً فيها.
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
