@extends('installer::layout')

@section('title', 'تفعيل الاشتراك')

@section('content')
    <h2>ربط النظام بلوحة تحكّم فوترة</h2>
    <p class="lead">
        يجلب النظام اشتراكك وباقتك وحدود استخدامك تلقائياً، ويُحدّثها كل ربع ساعة.
    </p>

    <div class="fw-endpoint">
        <span>لوحة فوترة</span>
        <code dir="ltr">{{ config('fawtra.base_url') }}</code>
    </div>

    @include('installer::partials.guide', [
        'title' => 'من أين تحصل على هذه البيانات',
        'items' => [
            'من لوحة تحكّم فوترة: <b>مفاتيح التكامل ← إصدار مفتاح</b>. تختار الإدارة اشتراكك وتُسجّل دومين خادمك.',
            '<b>السرّ يُعرض مرّة واحدة</b> في صفحة الإصدار ولا يظهر بعدها. من فقده يطلب «تدوير السرّ».',
            'الدومين هنا يجب أن يطابق المسجَّل عند فوترة تماماً — وهو داخلٌ في التوقيع فلا يُقبل غيره.',
            'اضغط <b>«تحقّق الآن»</b> قبل المتابعة: يختبر المفتاح والتوقيع والدومين فوراً ويعرض اسم حسابك.',
            '<b>التفعيل إلزامي</b> — لا يكتمل التثبيت بلا اشتراك متحقَّق منه. عنوان اللوحة مضبوط مسبقاً في إعداد النظام.',
        ],
    ])

    <form method="POST" action="{{ route('installer.activation.store') }}" id="activation-form">
        @csrf

        <h3>بيانات الربط</h3>
        <div class="grid">
            <div class="field full">
                <label for="client_id">معرّف العميل <span class="opt">client_id</span></label>
                <input type="text" id="client_id" name="client_id" dir="ltr"
                       value="{{ old('client_id', $values['client_id'] ?? '') }}"
                       placeholder="fwck_a1b2c3d4e5f6g7h8i9j0k1l2">
            </div>

            <div class="field full">
                <label for="secret">السرّ <span class="opt">secret — يُعرض مرّة واحدة عند الإصدار</span></label>
                <input type="password" id="secret" name="secret" dir="ltr"
                       autocomplete="new-password" placeholder="fwsk_…">
                <span class="hint">
                    يُحفظ في <code>.env</code> ولا يُخزَّن في قاعدة البيانات ولا يُعاد عرضه — كما تفعل لوحة فوترة نفسها.
                </span>
            </div>

            <div class="field full">
                <label for="domain">دومين نظامك</label>
                <input type="text" id="domain" name="domain" dir="ltr"
                       value="{{ old('domain', $values['domain'] ?? $suggestedDomain) }}"
                       placeholder="erp.example.com">
                <span class="hint">اسم مضيف فقط — بلا <code>https://</code> وبلا مسار</span>
            </div>
        </div>

        <div id="verify-result" style="margin-top: 18px;"></div>

        <div class="actions">
            <a class="btn ghost" href="{{ route('installer.requirements') }}">→ رجوع</a>
            <span style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn ghost" id="verify-btn">تحقّق الآن</button>
                <button type="submit" class="btn">تحقّق وتابع ←</button>
            </span>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // التحقّق دون مغادرة الصفحة: خطأ في المفتاح أو الدومين هو الأشيع هنا،
    // وإعادة تعبئة النموذج بعد كل محاولة عقوبة بلا داعٍ
    document.getElementById('verify-btn').addEventListener('click', async function () {
        const form = document.getElementById('activation-form');
        const box = document.getElementById('verify-result');
        const btn = this;

        btn.disabled = true;
        btn.textContent = 'جارٍ التحقّق…';
        box.innerHTML = '';

        try {
            const response = await fetch('{{ route('installer.activation.verify') }}', {
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
                let html = '<div class="alert ' + (data.ok ? 'ok' : 'bad') + '">' + data.message + '</div>';

                if (data.ok && data.data && data.data.subscription_id) {
                    html += '<div class="hint">رقم الاشتراك المرتبط: ' + data.data.subscription_id + '</div>';
                }

                box.innerHTML = html;
            }
        } catch (error) {
            box.innerHTML = '<div class="alert bad">تعذّر تنفيذ التحقّق: ' + error.message + '</div>';
        } finally {
            btn.disabled = false;
            btn.textContent = 'تحقّق الآن';
        }
    });
})();
</script>
@endpush
