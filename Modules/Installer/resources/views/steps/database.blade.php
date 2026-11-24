@extends('installer::layout')

@section('title', 'قاعدة البيانات')

@section('content')
    <h2>الاتصال بقاعدة البيانات</h2>
    <p class="lead">تُكتب هذه القيم في <code>.env</code>. المفاتيح الأخرى في الملف تبقى كما هي.</p>

    @include('installer::partials.guide', [
        'title' => 'إرشادات',
        'items' => [
            'استعمل <b>قاعدة بيانات فارغة</b>. التثبيت على قاعدة تحوي جداول قد يتعارض معها.',
            'اضغط <b>«اختبار الاتصال»</b> قبل الحفظ — يخبرك فوراً إن كانت البيانات خاطئة أو القاعدة غير موجودة، دون إعادة تعبئة النموذج.',
            'إن لم تكن القاعدة موجودة، فعّل خيار الإنشاء التلقائي — يتطلّب أن يملك المستخدم صلاحية <code>CREATE DATABASE</code>.',
            'الترميز <code>utf8mb4</code> إلزامي لحفظ النصوص العربية سليمة، ويضبطه المعالج تلقائياً عند الإنشاء.',
        ],
    ])

    <form method="POST" action="{{ route('installer.database.store') }}" id="db-form">
        @csrf

        <div class="grid">
            <div class="field">
                <label for="host">الخادم</label>
                <input type="text" id="host" name="host" value="{{ old('host', $config['host']) }}" required>
                <span class="hint">عادةً <code>127.0.0.1</code> أو <code>localhost</code></span>
            </div>

            <div class="field">
                <label for="port">المنفذ</label>
                <input type="text" id="port" name="port" value="{{ old('port', $config['port']) }}" required>
            </div>

            <div class="field">
                <label for="database">اسم قاعدة البيانات</label>
                <input type="text" id="database" name="database" value="{{ old('database', $config['database']) }}" required>
                <span class="hint">حروف وأرقام وشرطة سفلية فقط</span>
            </div>

            <div class="field">
                <label for="username">اسم المستخدم</label>
                <input type="text" id="username" name="username" value="{{ old('username', $config['username']) }}" required>
            </div>

            <div class="field full">
                <label for="password">كلمة المرور <span class="opt">— اتركها فارغة إن لم تكن مضبوطة</span></label>
                <input type="password" id="password" name="password" value="{{ old('password') }}" autocomplete="new-password">
            </div>

            <div class="field full">
                <label class="check">
                    <input type="checkbox" name="create_database" value="1" {{ old('create_database') ? 'checked' : '' }}>
                    <div>
                        <strong>أنشئ القاعدة إن لم تكن موجودة</strong>
                        يتطلّب أن يملك المستخدم صلاحية <code>CREATE DATABASE</code>. تُنشأ بترميز
                        <code>utf8mb4_unicode_ci</code>، وهو إلزامي لحفظ النصوص العربية سليمة.
                    </div>
                </label>
            </div>
        </div>

        <div id="test-result" style="margin-top: 18px;"></div>

        <div class="actions">
            <a class="btn ghost" href="{{ route('installer.activation') }}">→ رجوع</a>
            <span style="display: flex; gap: 10px;">
                <button type="button" class="btn ghost" id="test-btn">اختبار الاتصال</button>
                <button type="submit" class="btn">حفظ ومتابعة ←</button>
            </span>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // اختبار الاتصال دون مغادرة الصفحة: بيانات الاتصال أكثر ما يُخطئ فيه
    // المستخدم، وإعادة تعبئة النموذج بعد كل محاولة فاشلة عقوبة بلا داعٍ
    document.getElementById('test-btn').addEventListener('click', async function () {
        const form = document.getElementById('db-form');
        const box = document.getElementById('test-result');
        const btn = this;

        btn.disabled = true;
        btn.textContent = 'جارٍ الاختبار…';
        box.innerHTML = '';

        try {
            const response = await fetch('{{ route('installer.database.test') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            });

            const data = await response.json();

            if (response.status === 422) {
                const messages = Object.values(data.errors || {}).flat().join('، ');
                box.innerHTML = '<div class="alert bad">' + (messages || 'تحقّق من الحقول.') + '</div>';
            } else {
                box.innerHTML = '<div class="alert ' + (data.ok ? 'ok' : 'bad') + '">' + data.message + '</div>';
            }
        } catch (error) {
            box.innerHTML = '<div class="alert bad">تعذّر تنفيذ الاختبار: ' + error.message + '</div>';
        } finally {
            btn.disabled = false;
            btn.textContent = 'اختبار الاتصال';
        }
    });
</script>
@endpush
