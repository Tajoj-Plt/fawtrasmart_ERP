@extends('installer::layout')

@section('title', 'تعثّر التنفيذ')

@section('content')
    <h2>تعثّر التثبيت</h2>
    <p class="lead">توقّف التنفيذ قبل إتمامه. لم تُسجَّل علامة التثبيت، فيمكنك إصلاح السبب وإعادة المحاولة.</p>

    @include('installer::partials.log', ['log' => $log])

    @if (! empty($output))
        <h3>مخرجات الأمر</h3>
        <pre>{{ trim($output) }}</pre>
    @endif

    <div class="alert warn" style="margin-top: 20px;">
        الأسباب الشائعة: مستخدم قاعدة البيانات بلا صلاحية <code>ALTER</code> أو <code>CREATE</code>،
        أو قاعدة تحتوي جداول قديمة تتعارض مع الهجرات (جرّب خيار إعادة البناء)،
        أو نفاد مهلة PHP على استضافة مشتركة (نفّذ من الطرفية: <code>php artisan installer:install</code>).
    </div>

    <div class="actions">
        <a class="btn ghost" href="{{ route('installer.review') }}">→ رجوع للمراجعة</a>
        <a class="btn" href="{{ route('installer.database') }}">راجع بيانات الاتصال</a>
    </div>
@endsection
