@extends('installer::layout')

@section('title', 'تمّ التثبيت')

@section('content')
    <h2>تمّ تثبيت النظام</h2>
    <p class="lead">
        سُجِّلت علامة التثبيت في <code>storage/app/installed</code>، ومعالج التثبيت مقفل الآن.
    </p>

    @if (! empty($log))
        <h3>سجلّ التنفيذ</h3>
        @include('installer::partials.log', ['log' => $log])
    @endif

    <h3>ما ينبغي فعله الآن</h3>
    <table>
        <tbody>
        <tr>
            <td style="width: 190px;"><strong>سجّل الدخول</strong></td>
            <td>
                بالبريد وكلمة المرور اللذين أدخلتهما
                @if (! empty($meta['admin_email']))
                    (<code>{{ $meta['admin_email'] }}</code>)
                @endif
            </td>
        </tr>
        <tr>
            <td><strong>فعّل المهام المجدولة</strong></td>
            <td>
                أضف هذا السطر إلى cron الخادم — بدونه لا تعمل التقارير الدورية ولا النسخ الاحتياطي:
                <pre style="margin-top: 8px;">* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</pre>
            </td>
        </tr>
        <tr>
            <td><strong>راجع شاشة التطبيقات</strong></td>
            <td>عطّل الوحدات غير المستعملة من الإعدادات ← التطبيقات، فتختفي من القوائم ويُمنع الوصول إلى مساراتها.</td>
        </tr>
        <tr>
            <td><strong>اضبط الترقيم والقوالب</strong></td>
            <td>الإعدادات ← ترقيم المستندات، وقوالب المستندات — لتطابق الفواتير هوية منشأتك.</td>
        </tr>
        <tr>
            <td><strong>جرّب البريد</strong></td>
            <td>الإعدادات ← إعدادات البريد ← إرسال رسالة اختبار، للتأكد من وصول التنبيهات.</td>
        </tr>
        </tbody>
    </table>

    <div class="alert warn" style="margin-top: 24px;">
        <strong>للأمان:</strong> عطّل وحدة التثبيت بعد الاطمئنان — اضبط <code>"Installer": false</code>
        في <code>modules_statuses.json</code> ثم نفّذ <code>composer dump-autoload</code> و<code>php artisan optimize:clear</code>.
        وجودها مفعّلة بعد التثبيت غير ضار (العلامة تقفل مساراتها) لكن إزالتها أضمن.
    </div>

    <div class="actions">
        <span></span>
        <a class="btn" href="{{ url('/login') }}">اذهب إلى تسجيل الدخول ←</a>
    </div>
@endsection
