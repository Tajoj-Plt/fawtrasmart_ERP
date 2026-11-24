{{--
    شاشة التفعيل والربط — مشتركة بين «غير مربوط» و«تغيير المفتاح» فلا يفترقان.

    التصميم يخدم عملاً واحداً: أن يعرف المستخدم **الآن** إن كان مفتاحه مقبولاً.
    فالزرّان مفصولان (تحقّق / تحقّق واربط)، والنتيجة تظهر تحت النموذج مباشرةً
    لا بعد إعادة تحميل، وكل محاولة تُكتب في سجلّات النظام كي يُعرف لاحقاً
    لماذا توقّف الربط.
--}}
<style>
    .fw-link { --fw-line: #e6e9f0; }
    .fw-link .steps { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
    .fw-link .steps span {
        font-size: .78rem; color: #6b7280; background: #f4f6fa;
        border-radius: 999px; padding: .3rem .8rem;
    }
    .fw-link .steps b { color: #4b5563; }

    .fw-field { margin-bottom: .9rem; }
    .fw-field label { font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: .3rem; display: block; }
    .fw-field label .hint { font-weight: 400; color: #9aa3af; font-size: .74rem; }
    .fw-field .form-control { border-radius: 9px; border: 1.5px solid var(--fw-line); padding: .55rem .8rem; font-size: .88rem; }
    .fw-field .form-control:focus { border-color: #5e50ee; box-shadow: 0 0 0 4px rgba(94, 80, 238, .1); }
    .fw-field .form-control[disabled] { background: #f8f9fb; color: #7b8194; }
    .fw-field small.note { display: block; color: #9aa3af; font-size: .73rem; margin-top: .25rem; }

    .fw-actions { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-top: 1.1rem; }

    /* شريط النتيجة: لونٌ واحد يقول كل شيء قبل أن يُقرأ النصّ */
    .fw-result {
        display: none; align-items: flex-start; gap: .6rem;
        border-radius: 10px; padding: .8rem 1rem; margin-top: .9rem;
        font-size: .87rem; border-inline-start: 4px solid transparent;
    }
    .fw-result.on { display: flex; }
    .fw-result.info { background: #eef2ff; border-color: #6366f1; color: #3730a3; }
    .fw-result.ok { background: #ecfdf3; border-color: #28c76f; color: #16653a; }
    .fw-result.bad { background: #fef2f2; border-color: #ea5455; color: #93261f; }
    .fw-result .ic { flex: none; font-size: 1.05rem; line-height: 1.4; }
    .fw-result .txt { flex: 1; min-width: 0; }
    .fw-result .txt b { display: block; margin-bottom: .15rem; }
    .fw-result .meta { font-size: .78rem; opacity: .85; margin-top: .2rem; direction: ltr; text-align: start; }

    .fw-spin {
        width: 14px; height: 14px; border-radius: 50%; flex: none;
        border: 2px solid rgba(99, 102, 241, .3); border-top-color: #6366f1;
        animation: fw-rotate .7s linear infinite;
    }
    @keyframes fw-rotate { to { transform: rotate(360deg); } }
</style>

<div class="fw-link">
    <div class="steps">
        <span><b>١)</b> افتح لوحة فوترة ← مفاتيح التكامل ← إصدار مفتاح</span>
        <span><b>٢)</b> انسخ المعرّف والسرّ (يُعرض مرّة واحدة)</span>
        <span><b>٣)</b> الصقهما هنا واضغط «تحقّق»</span>
    </div>

    <form method="POST" action="{{ route('Fawtra.link') }}" id="fw-link-form">
        @csrf

        <div class="row">
            <div class="col-md-6 fw-field">
                <label>عنوان لوحة فوترة</label>
                <input type="text" class="form-control" dir="ltr" value="{{ config('fawtra.base_url') }}" disabled>
                <small class="note">مضبوط في إعداد النظام — لا يُدخل هنا</small>
            </div>

            <div class="col-md-6 fw-field">
                <label>دومين نظامك <span class="hint">domain</span></label>
                <input type="text" name="domain" dir="ltr" required
                       class="form-control @error('domain') is-invalid @enderror"
                       value="{{ old('domain', $credentials['domain'] ?? $suggestedDomain) }}"
                       placeholder="erp.example.com">
                <small class="note">يجب أن يطابق المسجَّل عند فوترة — وهو داخلٌ في التوقيع</small>
                @error('domain') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 fw-field">
                <label>معرّف العميل <span class="hint">client_id</span></label>
                <input type="text" name="client_id" dir="ltr" required
                       class="form-control @error('client_id') is-invalid @enderror"
                       value="{{ old('client_id', $credentials['client_id'] ?? '') }}"
                       placeholder="fwck_…">
                @error('client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 fw-field">
                <label>السرّ <span class="hint">secret</span></label>
                <input type="password" name="secret" dir="ltr" required autocomplete="new-password"
                       class="form-control @error('secret') is-invalid @enderror" placeholder="fwsk_…">
                <small class="note">يُحفظ في ملف البيئة ولا يُعرض بعدها — ولا يُخزَّن في قاعدة البيانات</small>
                @error('secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="fw-actions">
            {{-- زرّان لا زرّ: «تحقّق» يجيب فوراً بلا حفظ، و«اربط» يحفظ ويزامن.
                 الجمعُ بينهما كان يترك من رُفض مفتاحه أمام صفحةٍ لا تقول شيئاً --}}
            <button type="button" class="btn btn-outline-primary" id="fw-verify">
                <i class="feather icon-shield"></i> تحقّق من المفتاح
            </button>
            <button class="btn btn-primary" id="fw-link">
                <i class="feather icon-link-2"></i> تحقّق واربط
            </button>
            <small class="text-muted" style="font-size:.76rem">
                لا يُحفظ مفتاحٌ مرفوض — ويُسجَّل كل تحقّق في سجلّ الأحداث.
            </small>
        </div>

        <div class="fw-result" id="fw-result" role="status" aria-live="polite">
            <span class="ic" id="fw-result-ic"></span>
            <span class="txt">
                <b id="fw-result-title"></b>
                <span id="fw-result-body"></span>
                <span class="meta" id="fw-result-meta"></span>
            </span>
        </div>
    </form>
</div>

<script>
(function () {
    'use strict';

    const button = document.getElementById('fw-verify');

    if (! button) return;

    const form = document.getElementById('fw-link-form');
    const box = document.getElementById('fw-result');
    const icon = document.getElementById('fw-result-ic');
    const title = document.getElementById('fw-result-title');
    const body = document.getElementById('fw-result-body');
    const meta = document.getElementById('fw-result-meta');

    function show(tone, iconText, titleText, bodyText, metaText) {
        box.className = 'fw-result on ' + tone;
        icon.innerHTML = iconText;
        title.textContent = titleText;
        body.textContent = bodyText || '';
        meta.textContent = metaText || '';
    }

    button.addEventListener('click', async function () {
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = 'جارٍ التحقّق…';
        show('info', '<span class="fw-spin"></span>', 'يُفحص المفتاح عند لوحة فوترة…',
            'يُرسَل طلبٌ موقَّع إلى /ping — بلا حفظ ولا تغيير.');

        try {
            const response = await fetch('{{ route('Fawtra.verify') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': form.querySelector('input[name=_token]').value,
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            });

            const data = await response.json();

            // 422 يعني تحقّق حقولٍ فاشلاً: رسائله في `errors` لا في `message`
            const fieldErrors = data.errors ? Object.values(data.errors).flat() : [];

            if (data.ok) {
                const account = (data.data && (data.data.account || data.data.client)) || '';
                show('ok', '✔', 'المفتاح مقبول', data.message || 'تم الاتصال بنجاح.',
                    account ? 'الحساب: ' + account : '');
            } else {
                show('bad', '✘', 'لم يُقبل المفتاح',
                    data.message || fieldErrors.join(' · ') || 'تعذّر قراءة ردّ الخادم.',
                    data.code ? data.code : '');
            }
        } catch (error) {
            show('bad', '✘', 'تعذّر الاتصال بالخادم', error.message,
                'تأكّد أن الخادم يعمل وأن الشبكة متاحة.');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
})();
</script>
