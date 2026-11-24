@extends('installer::layout')

@section('title', 'المراجعة')

@section('content')
    <h2>مراجعة قبل التنفيذ</h2>
    <p class="lead">لم يُكتب شيء بعد. كل ما سبق محفوظ في الجلسة فقط — الكتابة تبدأ بضغط زرّ التنفيذ.</p>

    @include('installer::partials.guide', [
        'title' => 'قبل الضغط على «نفّذ التثبيت»',
        'items' => [
            'راجع <b>اسم قاعدة البيانات</b> أدناه — التنفيذ يكتب فيها ولا تراجع بعده.',
            'العملية تستغرق <b>عدّة دقائق</b>. لا تغلق الصفحة ولا تضغط الزرّ مرتين.',
            'بعد الانتهاء يتحقّق المعالج من أن كل إعداد أساسي استقرّ فعلاً في قاعدة البيانات، ولا يُنهي التثبيت إن سقط فحصٌ حرج.',
            'للرجوع وتعديل أي قيمة استعمل زرّ «رجوع» — لن تفقد ما أدخلته.',
        ],
    ])

    @php
        $company = $payload['company'] ?? [];
        $settings = $payload['settings'] ?? [];
        $admin = $payload['admin'] ?? [];
        $db = $payload['database'] ?? [];
        $options = $payload['options'] ?? [];
        $mail = $payload['mail'] ?? [];
    @endphp

    <div class="review-grid">
        <section class="rv">
            <h4>قاعدة البيانات</h4>
    <dl>
        <dt>الخادم</dt><dd>{{ $db['host'] ?? '—' }}:{{ $db['port'] ?? '—' }}</dd>
        <dt>القاعدة</dt><dd>{{ $db['database'] ?? '—' }}</dd>
        <dt>المستخدم</dt><dd>{{ $db['username'] ?? '—' }}</dd>
            </dl>
        </section>

        <section class="rv">
            <h4>المنشأة</h4>
    <dl>
        <dt>الاسم</dt><dd>{{ $company['name'] ?? '—' }}</dd>
        <dt>البريد</dt><dd>{{ $company['email'] ?: '—' }}</dd>
        <dt>الهاتف</dt><dd>{{ $company['phone'] ?: '—' }}</dd>
        <dt>الرقم الضريبي</dt><dd>{{ $company['tax_number'] ?: '—' }}</dd>
            </dl>
        </section>

        <section class="rv">
            <h4>الإعدادات</h4>
    <dl>
        <dt>اللغة</dt><dd>{{ $settings['language'] ?? '—' }}</dd>
        <dt>العملة</dt><dd>{{ $settings['currency'] ?? '—' }}</dd>
        <dt>المنطقة الزمنية</dt><dd>{{ $settings['timezone'] ?? '—' }}</dd>
        <dt>طبيعة النشاط</dt><dd>{{ ['products' => 'منتجات', 'services' => 'خدمات', 'both' => 'منتجات وخدمات'][$settings['business_type'] ?? ''] ?? '—' }}</dd>
        <dt>الضريبة</dt><dd>{{ $settings['tax_percent'] ?? '—' }}% ({{ ($settings['tax_type'] ?? '') === 'included' ? 'شاملة' : 'غير شاملة' }})</dd>
        <dt>الثيم</dt><dd>{{ \App\Models\ColorSitting::PRESETS[$settings['theme'] ?? 'default']['label'] ?? '—' }}</dd>
            </dl>
        </section>

        <section class="rv">
            <h4>حساب المدير</h4>
    <dl>
        <dt>الاسم</dt><dd>{{ $admin['name'] ?? '—' }}</dd>
        <dt>البريد</dt><dd>{{ $admin['email'] ?? '—' }}</dd>
        <dt>كلمة المرور</dt><dd>••••••••</dd>
        <dt>البريد الصادر</dt><dd>{{ $mail['host'] ?? 'لم يُضبط' }}</dd>
            </dl>
        </section>

    </div>

    <h3>ما سيُنفَّذ</h3>
    <table>
        <tbody>
        <tr>
            <td style="width: 190px;"><strong>الهجرات</strong></td>
            <td>
                @if ($options['fresh'] ?? false)
                    <span class="pill bad">migrate:fresh</span> إسقاط كل الجداول ثم إعادة بنائها
                @else
                    <span class="pill ok">migrate</span> تشغيل الهجرات غير المنفَّذة
                @endif
            </td>
        </tr>
        <tr>
            <td><strong>البذور الأساسية</strong></td>
            <td>{{ $coreCount }} بذرة — الصلاحيات، اللغات، العملات، الدول، شجرة الحسابات، القوالب، المهام المجدولة</td>
        </tr>
        <tr>
            <td><strong>البيانات التجريبية</strong></td>
            <td>
                @if ($options['demo_data'] ?? false)
                    <span class="pill warn">مُفعَّل</span> عملاء وموظفون وبيانات متجر افتراضية
                @else
                    <span class="pill ok">غير مُفعَّل</span>
                @endif
            </td>
        </tr>
        <tr>
            <td><strong>الإعدادات</strong></td>
            <td>حساب المدير، اللغة والعملة الافتراضية، بيانات المنشأة، الضريبة، المظهر، البريد</td>
        </tr>
        </tbody>
    </table>

    @if (($options['fresh'] ?? false) && $hasData)
        <div class="alert bad">
            <strong>انتبه:</strong> القاعدة المستهدفة تحتوي بيانات، وخيار <code>migrate:fresh</code> مُفعَّل.
            التنفيذ سيحذف كل ما فيها نهائياً ولا يمكن التراجع. ارجع وألغِ الخيار إن لم يكن هذا مقصوداً.
        </div>
    @endif

    <div class="actions">
        <a class="btn ghost" href="{{ route('installer.admin') }}">→ رجوع</a>
        <a class="btn" href="{{ route('installer.run') }}">انتقل إلى التنفيذ ←</a>
    </div>
@endsection
