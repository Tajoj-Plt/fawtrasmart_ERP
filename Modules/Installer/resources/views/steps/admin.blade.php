@extends('installer::layout')

@section('title', 'حساب المدير')

@section('content')
    <h2>حساب المدير وخيارات التنفيذ</h2>
    <p class="lead">أول حساب دخول للنظام. يُمنح دور <code>manager</code> وكل الصلاحيات المزروعة.</p>

    @include('installer::partials.guide', [
        'title' => 'إرشادات',
        'items' => [
            'هذا الحساب يُمنح دور <code>manager</code>، وتُسنَد إليه فوقه صلاحيات المالك المحجوزة — النسخ الاحتياطي والاشتراك ومفاتيح API وإدارة المستخدمين.',
            'كلمة المرور <b>ثمانية أحرف على الأقل</b>. لن تُعرض لك مرة أخرى بعد التثبيت.',
            'يمكنك تخطّي قسم <b>البريد الصادر</b> وضبطه لاحقاً من «الإعدادات ← إعدادات البريد» حيث يوجد زرّ إرسال رسالة اختبار.',
            'خيارا <b>البيانات التجريبية</b> و<b>إعادة البناء</b> في آخر الصفحة — اقرأ وصفهما قبل تفعيل أيٍّ منهما.',
        ],
    ])

    <form method="POST" action="{{ route('installer.admin.store') }}">
        @csrf

        <h3>بيانات الدخول</h3>
        <div class="grid">
            <div class="field">
                <label for="name">الاسم</label>
                <input type="text" id="name" name="name" value="{{ old('name', $values['name'] ?? '') }}" required>
            </div>

            <div class="field">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email', $values['email'] ?? '') }}" required>
            </div>

            <div class="field">
                <label for="phone">الجوال <span class="opt">(اختياري)</span></label>
                <input type="text" id="phone" name="phone" value="{{ old('phone', $values['phone'] ?? '') }}">
            </div>

            <div class="field">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
                <span class="hint">ثمانية أحرف على الأقل</span>
            </div>

            <div class="field">
                <label for="password_confirmation">تأكيد كلمة المرور</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>

        <h3>البريد الصادر <span class="opt" style="font-weight: 400;">— اختياري، يمكن ضبطه لاحقاً من الإعدادات</span></h3>
        <div class="grid-3">
            <div class="field">
                <label for="mail_host">خادم SMTP</label>
                <input type="text" id="mail_host" name="mail_host" value="{{ old('mail_host', $mail['host'] ?? '') }}"
                       placeholder="smtp.gmail.com">
            </div>

            <div class="field">
                <label for="mail_port">المنفذ</label>
                <input type="number" id="mail_port" name="mail_port" value="{{ old('mail_port', $mail['port'] ?? 587) }}">
            </div>

            <div class="field">
                <label for="mail_encryption">التشفير</label>
                <select id="mail_encryption" name="mail_encryption">
                    @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'بلا تشفير'] as $enc => $label)
                        <option value="{{ $enc }}" @selected(old('mail_encryption', $mail['encryption'] ?? 'tls') === $enc)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="mail_username">اسم المستخدم</label>
                <input type="text" id="mail_username" name="mail_username" value="{{ old('mail_username', $mail['username'] ?? '') }}">
            </div>

            <div class="field">
                <label for="mail_password">كلمة المرور</label>
                <input type="password" id="mail_password" name="mail_password" autocomplete="new-password">
            </div>

            <div class="field">
                <label for="mail_from">البريد المرسِل</label>
                <input type="email" id="mail_from" name="mail_from" value="{{ old('mail_from', $mail['from'] ?? '') }}">
            </div>

            <div class="field full">
                <label for="mail_sender_name">اسم المرسِل الظاهر</label>
                <input type="text" id="mail_sender_name" name="mail_sender_name"
                       value="{{ old('mail_sender_name', $mail['sender_name'] ?? '') }}">
            </div>
        </div>

        <h3>خيارات التنفيذ</h3>
        <div class="grid">
            <div class="field full">
                <label class="check">
                    <input type="checkbox" name="demo_data" value="1" {{ old('demo_data') ? 'checked' : '' }}>
                    <div>
                        <strong>زرع بيانات تجريبية</strong>
                        عملاء وموظفون وعلامات تجارية وكوبونات للتجربة. <b>لا تفعّله على تركيبٍ إنتاجي</b> —
                        البيانات تحمل أسماء افتراضية وستختلط ببياناتك الحقيقية.
                    </div>
                </label>
            </div>

            <div class="field full">
                <label class="check">
                    <input type="checkbox" name="fresh" value="1" {{ old('fresh') ? 'checked' : '' }}
                        {{ $hasData ? '' : '' }}>
                    <div>
                        <strong>إعادة بناء الجداول من الصفر (<code>migrate:fresh</code>)</strong>
                        @if ($hasData)
                            <span style="color: var(--bad); font-weight: 600;">
                                تحذير: القاعدة الحالية تحتوي بيانات فعلاً — تفعيل هذا الخيار يحذفها كلها نهائياً.
                            </span>
                        @else
                            يُسقط كل الجداول ثم يعيد إنشاءها. اتركه فارغاً على قاعدة فارغة.
                        @endif
                    </div>
                </label>
            </div>
        </div>

        <div class="actions">
            <a class="btn ghost" href="{{ route('installer.settings') }}">→ رجوع</a>
            <button type="submit" class="btn">التالي: المراجعة ←</button>
        </div>
    </form>
@endsection
