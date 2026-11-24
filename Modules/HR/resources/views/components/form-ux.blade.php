{{--
    تجربةُ التحقّق الموحّدة لكل شاشات الموارد البشرية والرواتب — مُضمَّنةٌ مرّةً
    واحدة في `hr::master`، فلا تحتاج الشاشةُ أن تفعل شيئاً لتنالها.

    ما تفعله:
      ١) حقلٌ إجباريّ فارغ عند الضغط على «حفظ»: المتصفّح يرفض الإرسال — والبيانات
         المُدخلة باقيةٌ لأنّ الصفحة لم تُغادَر — فنُمرّر إلى الحقل ونضع عليه
         المؤشّر ونكتب تحته «هذا الحقل إجباري». ويشمل ذلك قوائم Select2 التي
         يُخفي عنصرُها الأصليّ فلا يستطيع المتصفّح إظهار فقاعته عليه أصلاً.
      ٢) أخطاءُ الخادم (Validation): تُعلَّم الحقولُ بأسمائها، وتُكتب الرسالةُ تحت
         كلٍّ منها، ويُمرَّر إلى أوّلها — والقيمُ تعود من `old()` في الشاشة.
      ٣) نجمةٌ حمراء بجانب عنوان كل حقلٍ `required` لا يحملها.

    تستثني النموذجَ الذي يحمل `data-no-ux`.
--}}
@php
    $hrUxErrors = (isset($errors) && $errors->any()) ? $errors->getMessages() : [];
@endphp
<style id="hr-form-ux">
    .hr-ux-feedback { display:block; width:100%; margin-top:.25rem; font-size:.8rem; color: var(--app-danger, #dc2626); }
    .hr-ux-invalid, .hr-ux-invalid + .select2-container .select2-selection {
        border-color: var(--app-danger, #dc2626) !important;
        box-shadow: 0 0 0 .15rem color-mix(in srgb, var(--app-danger, #dc2626) 18%, transparent) !important;
    }
    .hr-ux-star { color: var(--app-danger, #dc2626); margin-inline-start: .15rem; font-weight: 700; }
    .hr-ux-flash { animation: hrUxFlash 1.2s ease-out 1; }
    @keyframes hrUxFlash { 0% { background: color-mix(in srgb, var(--app-danger, #dc2626) 14%, transparent); } 100% { background: transparent; } }
</style>
<script>
(function () {
    'use strict';
    var SERVER_ERRORS = @json($hrUxErrors);
    var REQUIRED_MSG = 'هذا الحقل إجباري';

    function dotToName(key) {
        var parts = String(key).split('.');
        return parts.length === 1 ? parts[0] : parts[0] + parts.slice(1).map(function (p) { return '[' + p + ']'; }).join('');
    }

    function findField(key) {
        var name = dotToName(key);
        var candidates = [name, name + '[]', key];
        for (var i = 0; i < candidates.length; i++) {
            var el = document.querySelector('[name="' + candidates[i].replace(/"/g, '\\"') + '"]:not([type="hidden"])')
                || document.querySelector('[name="' + candidates[i].replace(/"/g, '\\"') + '"]');
            if (el) return el;
        }
        return document.getElementById(key) || null;
    }

    // العنصر الظاهر الذي يمثّل الحقل: حاوية Select2 إن وُجدت.
    function visualOf(el) {
        var next = el.nextElementSibling;
        if (next && next.classList && next.classList.contains('select2-container')) return next;
        return el;
    }

    function containerOf(el) {
        return el.closest('.form-group, .att-field, .stk-field, .mb-3, .col, [class*="col-"]') || el.parentElement;
    }

    function setMessage(el, message) {
        el.classList.add('hr-ux-invalid', 'is-invalid');
        var anchor = visualOf(el);
        var holder = containerOf(el);
        var existing = holder ? holder.querySelector('.hr-ux-feedback') : null;
        // رسالة الخادم المكتوبة في الشاشة نفسها (توجيه الخطأ في Blade) تكفي — لا نكرّرها.
        var serverFeedback = holder ? holder.querySelector('.invalid-feedback, .text-danger.small, small.text-danger') : null;
        if (existing) { existing.textContent = message; return; }
        if (serverFeedback && serverFeedback.textContent.trim() !== '') { serverFeedback.style.display = 'block'; return; }
        var fb = document.createElement('div');
        fb.className = 'hr-ux-feedback';
        fb.textContent = message;
        anchor.parentNode.insertBefore(fb, anchor.nextSibling);
    }

    function clearMessage(el) {
        el.classList.remove('hr-ux-invalid', 'is-invalid');
        var holder = containerOf(el);
        var fb = holder ? holder.querySelector('.hr-ux-feedback') : null;
        if (fb) fb.remove();
    }

    // يُظهر الحقل إن كان داخل تبويبٍ مخفيّ أو قسمٍ مطويّ.
    function reveal(el) {
        var pane = el.closest('.tab-pane');
        if (pane && !pane.classList.contains('active') && pane.id) {
            var trigger = document.querySelector('[href="#' + pane.id + '"], [data-bs-target="#' + pane.id + '"], [data-target="#' + pane.id + '"]');
            if (trigger) trigger.click();
        }
        var details = el.closest('details');
        if (details && !details.open) details.open = true;
        var collapse = el.closest('.collapse');
        if (collapse && !collapse.classList.contains('show')) collapse.classList.add('show');
    }

    function focusField(el) {
        reveal(el);
        var target = visualOf(el);
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('hr-ux-flash');
        setTimeout(function () {
            target.classList.remove('hr-ux-flash');
            if (window.jQuery && el.classList.contains('select2-hidden-accessible')) {
                try { window.jQuery(el).select2('open'); } catch (e) { /* ignore */ }
            } else if (typeof el.focus === 'function') {
                el.focus({ preventScroll: true });
            }
        }, 350);
    }

    function messageFor(el) {
        if (el.validity && el.validity.valueMissing) return REQUIRED_MSG;
        return el.validationMessage || REQUIRED_MSG;
    }

    // ── ١) تحقّق المتصفّح: نلتقط أوّل حقلٍ غير صالح في كل محاولة إرسال ──
    var firstInvalidThisRound = null;
    document.addEventListener('invalid', function (e) {
        var el = e.target;
        var form = el.form;
        if (form && form.hasAttribute('data-no-ux')) return;
        e.preventDefault(); // نكتب رسالتنا بدل فقاعة المتصفّح (التي لا تظهر أصلاً على Select2)
        setMessage(el, messageFor(el));
        if (!firstInvalidThisRound) {
            firstInvalidThisRound = el;
            setTimeout(function () {
                var f = firstInvalidThisRound;
                firstInvalidThisRound = null;
                if (f) focusField(f);
            }, 0);
        }
    }, true);

    function onEdit(e) {
        var el = e.target;
        if (el && el.classList && el.classList.contains('hr-ux-invalid')) {
            if (!el.checkValidity || el.checkValidity()) clearMessage(el);
        }
    }
    document.addEventListener('input', onEdit, true);
    document.addEventListener('change', onEdit, true);
    if (window.jQuery) {
        window.jQuery(document).on('select2:select select2:unselect', function (e) { onEdit({ target: e.target }); });
    }

    function markRequiredLabels(root) {
        (root || document).querySelectorAll('input[required], select[required], textarea[required]').forEach(function (el) {
            if (el.type === 'hidden' || el.type === 'checkbox' || el.type === 'radio') return;
            var label = (el.id && document.querySelector('label[for="' + el.id + '"]')) || (containerOf(el) ? containerOf(el).querySelector('label') : null);
            if (!label || label.dataset.hrUxStar) return;
            label.dataset.hrUxStar = '1';
            var text = label.textContent || '';
            // `stk-req` على الوسم نفسه يرسم النجمة بـ::after — فلا تُكرَّر
            if (text.indexOf('*') !== -1 || label.classList.contains('stk-req') || label.querySelector('.req, .stk-req, .text-danger, .required, .hr-ux-star')) return;
            var star = document.createElement('span');
            star.className = 'hr-ux-star';
            star.setAttribute('aria-hidden', 'true');
            star.textContent = '*';
            label.appendChild(star);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        markRequiredLabels(document);

        // ── ٢) أخطاء الخادم ──
        var keys = Object.keys(SERVER_ERRORS || {});
        var first = null;
        keys.forEach(function (key) {
            var el = findField(key);
            if (!el) return;
            var msgs = SERVER_ERRORS[key] || [];
            setMessage(el, msgs[0] || REQUIRED_MSG);
            if (!first) first = el;
        });
        if (first) {
            setTimeout(function () { focusField(first); }, 250);
        }
    });

    window.HrFormUx = { mark: setMessage, clear: clearMessage, focus: focusField, markRequiredLabels: markRequiredLabels };
})();
</script>
