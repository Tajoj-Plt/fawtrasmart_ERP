@extends('installer::layout')

@section('title', 'التنفيذ')

@section('content')
    <h2>جارٍ تثبيت نظام فوترة</h2>
    <p class="lead">يُنفَّذ التثبيت مهمةً مهمة، وتظهر كل خطوة هنا لحظة وقوعها.</p>

    @include('installer::partials.guide', [
        'title' => 'ماذا يحدث الآن',
        'items' => [
            'كل هجرة وكل بذرة <b>نداءٌ مستقل</b> — فلا يصطدم التثبيت بمهلة الخادم مهما طال.',
            '<b>لا تغلق هذه الصفحة</b> ولا تُحدّثها. إغلاقها يوقف التنفيذ عند آخر مهمة تمّت.',
            'عند أي خطأ يتوقّف التنفيذ فوراً ويُعرض نصّه كاملاً — ولا تُسجَّل علامة التثبيت.',
            'إن توقّف التنفيذ، أصلح السبب ثم اضغط <b>«استئناف»</b> — يكمل من المهمة المتعثّرة لا من البداية.',
        ],
    ])

    @if ($resumable)
        <div class="alert warn">
            <strong>توجد عملية تثبيت متوقّفة.</strong> سيُستأنف التنفيذ من حيث توقّف.
        </div>
    @endif

    {{-- شريط التقدّم --}}
    <div class="run-head">
        <div class="run-bar"><div class="run-bar-fill" id="bar"></div></div>
        <div class="run-meta">
            <span id="counter">في انتظار البدء…</span>
            <span id="elapsed">00:00</span>
        </div>
    </div>

    <div class="run-stats" id="stats" hidden>
        <div><b id="stat-migrations">0</b><span>هجرة</span></div>
        <div><b id="stat-seeders">0</b><span>بذرة</span></div>
        <div><b id="stat-done">0</b><span>تمّت</span></div>
        <div><b id="stat-failed">0</b><span>متعثّرة</span></div>
    </div>

    {{-- سجلّ التنفيذ الحيّ --}}
    <h3>سجلّ التنفيذ</h3>
    <div class="run-log" id="log">
        <div class="run-line muted">اضغط «ابدأ التنفيذ» للبدء.</div>
    </div>

    {{-- تفصيل الخطأ عند التوقّف --}}
    <div id="error-box" hidden>
        <h3 style="color: var(--app-danger, #EA5455);">تفصيل الخطأ</h3>
        <pre id="error-detail"></pre>
        <div class="alert warn">
            أصلح السبب أعلاه ثم اضغط «استئناف» — يكمل التنفيذ من المهمة المتعثّرة نفسها.
            الأسباب الشائعة: صلاحيات ناقصة لمستخدم قاعدة البيانات، أو جدول قائم يتعارض مع هجرة،
            أو نفاد ذاكرة PHP.
        </div>
    </div>

    <div class="actions">
        <a class="btn ghost" href="{{ route('installer.review') }}" id="back-btn">→ رجوع</a>
        <span style="display: flex; gap: 10px;">
            <button type="button" class="btn" id="start-btn">
                {{ $resumable ? 'استئناف التنفيذ' : 'ابدأ التنفيذ' }}
            </button>
            <a class="btn" href="{{ route('installer.finished') }}" id="finish-btn" hidden>إلى شاشة الإتمام ←</a>
        </span>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const token = document.querySelector('meta[name=csrf-token]').content;
    const log = document.getElementById('log');
    const bar = document.getElementById('bar');
    const counter = document.getElementById('counter');
    const elapsed = document.getElementById('elapsed');
    const startBtn = document.getElementById('start-btn');
    const finishBtn = document.getElementById('finish-btn');
    const backBtn = document.getElementById('back-btn');
    const stats = document.getElementById('stats');
    const errorBox = document.getElementById('error-box');
    const errorDetail = document.getElementById('error-detail');

    let done = 0, failed = 0, startedAt = 0, timer = null;

    const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    /**
     * نبضة مع إعادة محاولة.
     *
     * الخادم قد يختفي لحظةً في منتصف التنفيذ — خادم التطوير يُعيد تشغيل نفسه
     * حين تُكتب `.env`، وخادم الإنتاج قد يُعاد تحميله. الاستجابة تضيع فتبدو
     * العملية «معلّقة» وهي سليمة: المؤشّر عند الخادم لا يتقدّم إلا بنجاح
     * فعليّ، فإعادة الطلب تُكمل ولا تُكرّر.
     */
    async function post(url, attempts = 6) {
        for (let attempt = 1; ; attempt++) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'},
                });

                const body = await response.json();

                return {status: response.status, body};
            } catch (error) {
                if (attempt >= attempts) throw error;

                if (attempt === 1) line('', 'انتظار', 'الخادم لا يستجيب — إعادة المحاولة…');

                await sleep(attempt * 700);
            }
        }
    }

    function line(cls, title, message, meta) {
        // أول سطر يمحو رسالة «اضغط ابدأ»
        if (log.querySelector('.muted')) log.innerHTML = '';

        const el = document.createElement('div');
        el.className = 'run-line ' + cls;
        el.innerHTML =
            '<span class="run-icon">' + (cls === 'ok' ? '✔' : cls === 'bad' ? '✘' : '•') + '</span>' +
            '<span class="run-title"></span>' +
            '<span class="run-msg"></span>' +
            '<span class="run-time">' + (meta || '') + '</span>';
        el.querySelector('.run-title').textContent = title;
        el.querySelector('.run-msg').textContent = message || '';
        log.appendChild(el);

        // المتابعة التلقائية للأسفل — إلا إذا مرّر المستخدم لأعلى ليقرأ
        const atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
        if (atBottom) log.scrollTop = log.scrollHeight;
    }

    function tick() {
        const s = Math.floor((Date.now() - startedAt) / 1000);
        elapsed.textContent =
            String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    }

    function stop(message) {
        clearInterval(timer);
        startBtn.disabled = false;
        startBtn.textContent = 'استئناف التنفيذ';
        backBtn.hidden = false;
        if (message) line('bad', 'توقّف', message);
    }

    async function loop() {
        let guard = 0;

        // حدٌّ أعلى يمنع دوراناً لا ينتهي لو أعاد الخادم حالةً غير متقدّمة
        while (guard++ < 5000) {
            let res;

            try {
                res = await post('{{ route('installer.run.step') }}');
            } catch (e) {
                stop('انقطع الاتصال بالخادم: ' + e.message);
                return;
            }

            const r = res.body;

            if (r.ok === false) {
                failed++;
                document.getElementById('stat-failed').textContent = failed;
                line('bad', r.title || 'خطأ', r.message);
                errorDetail.textContent = r.detail || r.message || '';
                errorBox.hidden = false;
                errorBox.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                stop();
                return;
            }

            // نبضة اصطدمت بقفل: نبضةٌ أخرى تعمل — يُنتظر بلا تسجيل ولا عدّ
            if (r.type === 'busy') {
                await new Promise(resolve => setTimeout(resolve, 400));
                continue;
            }

            counter.textContent = r.index + ' من ' + r.total;
            bar.style.width = (r.total ? (r.index / r.total) * 100 : 0) + '%';

            // الهجرات تصل زُمَراً في نداءٍ واحد، وتُعرض سطراً سطراً كما كانت
            if (Array.isArray(r.titles) && r.titles.length > 1) {
                r.titles.forEach(t => line('ok', t, ''));
                done += r.titles.length;
                line('', 'زمرة', r.titles.length + ' هجرة في نداء واحد', r.duration + 'ث');
            } else {
                done++;
                line('ok', r.title, r.message, r.duration ? r.duration + 'ث' : '');
            }

            document.getElementById('stat-done').textContent = done;

            if (r.done) {
                clearInterval(timer);
                bar.style.width = '100%';
                counter.textContent = 'اكتمل — ' + r.total + ' مهمة';
                line('ok', 'انتهى التثبيت', 'كل المهام نُفِّذت بنجاح.');
                startBtn.hidden = true;
                finishBtn.hidden = false;
                finishBtn.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                return;
            }
        }

        stop('تجاوز التنفيذ الحدّ المسموح من المهام.');
    }

    startBtn.addEventListener('click', async function () {
        startBtn.disabled = true;
        startBtn.textContent = 'جارٍ التنفيذ…';
        backBtn.hidden = true;
        errorBox.hidden = true;

        startedAt = Date.now();
        clearInterval(timer);
        timer = setInterval(tick, 1000);

        const res = await post('{{ route('installer.run.start') }}');

        if (!res.body.ok) {
            stop(res.body.message || 'تعذّر بناء خطة التنفيذ.');
            return;
        }

        stats.hidden = false;
        document.getElementById('stat-migrations').textContent = res.body.migrations;
        document.getElementById('stat-seeders').textContent = res.body.seeders;
        done = res.body.index || 0; failed = 0;
        counter.textContent = done + ' من ' + res.body.total;
        bar.style.width = (res.body.total ? (done / res.body.total) * 100 : 0) + '%';
        document.getElementById('stat-done').textContent = done;

        line('', res.body.resumed ? 'استئناف' : 'الخطة',
            res.body.resumed
                ? 'يكمل من المهمة ' + (done + 1) + ' من ' + res.body.total + ' — ما تمّ لا يُعاد.'
                : res.body.total + ' مهمة: ' + res.body.migrations + ' هجرة و' + res.body.seeders + ' بذرة.');

        loop();
    });

    // تحذير المتصفّح قبل إغلاق الصفحة أثناء التنفيذ
    window.addEventListener('beforeunload', function (e) {
        if (startBtn.disabled && finishBtn.hidden) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
</script>
@endpush
