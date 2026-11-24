{{--
    لوحةٌ تظهر عند المرور على «!» — لمحتوىً طويل (كمربّع «جرّب بنفسك») لا يُراد
    أن يشغل الصفحة دائماً.

    الاستعمال:
        <span class="hr-hover-panel">
            <button type="button" class="hr-hover-panel__btn" aria-label="…" title="…">!</button>
            <div class="hr-hover-panel__pop"> …المحتوى… </div>
        </span>
        …ومرّةً في الصفحة: include لهذا الملف (hr::components.hover-panel)

    السلوك:
      • الفأرة: تفتح بالمرور، وتبقى مفتوحةً والمؤشّر داخلها (لتكتب في حقولها)،
        وتُغلق بعد لحظةٍ من خروجه منها ومن «!» معاً.
      • الضغط على «!» أو الكتابة داخلها يثبّتها مفتوحة؛ ضغطٌ ثانٍ أو خارجها أو Esc يغلقها.
      • اللمس: الضغط يفتح ويثبّت.
      • تُوضَع بـ position: fixed من مستطيل الزرّ — فلا تقصّها حدود بطاقةٍ عليها overflow.
--}}
@once
    <style id="hr-hover-panel-style">
        .hr-hover-panel { position: relative; display: inline-flex; vertical-align: middle; }
        .hr-hover-panel__btn {
            width: 1.45rem; height: 1.45rem; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: .9rem; line-height: 1; cursor: pointer;
            color: #fff; background: var(--app-primary, #7367f0);
            border: 0; margin-inline-start: .35rem;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--app-primary, #7367f0) 18%, transparent);
            transition: transform .15s ease;
        }
        .hr-hover-panel__btn:hover,
        .hr-hover-panel.is-open .hr-hover-panel__btn { transform: scale(1.12); }
        .hr-hover-panel__pop {
            display: none; position: fixed; z-index: 1085;
            width: min(620px, calc(100vw - 24px)); max-height: calc(100vh - 24px); overflow: auto;
            padding: .9rem 1rem;
            background: var(--app-surface, #fff);
            border: 1px solid var(--app-border, #ebe9f1);
            border-radius: calc(var(--app-radius, .357rem) + 4px);
            box-shadow: 0 12px 32px rgba(34, 41, 47, .18);
            text-align: start; font-weight: normal; white-space: normal;
        }
        .hr-hover-panel.is-open .hr-hover-panel__pop { display: block; }
        /* المحتوى داخل اللوحة لا يحتاج إطاره القديم — اللوحةُ نفسُها إطار */
        .hr-hover-panel__pop > .eos-example,
        .hr-hover-panel__pop > .gosi-example { margin: 0; padding: 0; border: 0; background: transparent; }
        .hr-hover-panel__pop label { font-weight: 600; font-size: .85rem; }
        .modal-open .hr-hover-panel__pop { z-index: 1095; }
    </style>

    <script>
        (function () {
            'use strict';
            var GAP = 8, CLOSE_DELAY = 220;

            function place(wrap) {
                var btn = wrap.querySelector('.hr-hover-panel__btn');
                var pop = wrap.querySelector('.hr-hover-panel__pop');
                if (!btn || !pop) return;
                var r = btn.getBoundingClientRect();
                var w = pop.offsetWidth, h = pop.offsetHeight;
                var rtl = getComputedStyle(document.documentElement).direction === 'rtl';
                // RTL: تحاذي حافّتها اليمنى زرَّها وتمتدّ يساراً
                var left = rtl ? r.right - w : r.left;
                left = Math.max(12, Math.min(left, window.innerWidth - w - 12));
                var top = r.bottom + GAP;
                if (top + h > window.innerHeight - 12 && r.top - h - GAP > 12) { top = r.top - h - GAP; }
                top = Math.max(12, Math.min(top, window.innerHeight - h - 12));
                pop.style.left = Math.round(left) + 'px';
                pop.style.top = Math.round(top) + 'px';
            }

            function open(wrap) {
                document.querySelectorAll('.hr-hover-panel.is-open').forEach(function (o) {
                    if (o !== wrap) { close(o, true); }
                });
                clearTimeout(wrap._hpTimer);
                wrap.classList.add('is-open');
                place(wrap);
            }

            function close(wrap, force) {
                if (!force && wrap.dataset.pinned === '1') return;
                wrap.classList.remove('is-open');
                delete wrap.dataset.pinned;
            }

            function scheduleClose(wrap) {
                clearTimeout(wrap._hpTimer);
                wrap._hpTimer = setTimeout(function () { close(wrap); }, CLOSE_DELAY);
            }

            function init(wrap) {
                if (wrap.dataset.hpReady) return;
                wrap.dataset.hpReady = '1';
                var btn = wrap.querySelector('.hr-hover-panel__btn');
                var pop = wrap.querySelector('.hr-hover-panel__pop');
                if (!btn || !pop) return;

                [btn, pop].forEach(function (el) {
                    el.addEventListener('pointerenter', function (e) {
                        if (e.pointerType === 'mouse') { open(wrap); }
                    });
                    el.addEventListener('pointerleave', function (e) {
                        if (e.pointerType === 'mouse') { scheduleClose(wrap); }
                    });
                });

                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (wrap.classList.contains('is-open') && wrap.dataset.pinned === '1') {
                        close(wrap, true);
                    } else {
                        open(wrap);
                        wrap.dataset.pinned = '1';
                    }
                });

                // من يكتب داخلها لا تُغلق من تحته
                ['focusin', 'input', 'change', 'keydown'].forEach(function (evt) {
                    pop.addEventListener(evt, function () { wrap.dataset.pinned = '1'; });
                });
                pop.addEventListener('click', function (e) { e.stopPropagation(); });
            }

            document.addEventListener('click', function (e) {
                // قائمةُ Select2 تُرسَم خارج اللوحة (في body) — الاختيارُ منها ليس «ضغطاً خارجها»
                if (e.target.closest && e.target.closest('.select2-container, .select2-dropdown')) { return; }
                document.querySelectorAll('.hr-hover-panel.is-open').forEach(function (o) { close(o, true); });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.hr-hover-panel.is-open').forEach(function (o) { close(o, true); });
                }
            }, true);
            window.addEventListener('scroll', function () {
                document.querySelectorAll('.hr-hover-panel.is-open').forEach(place);
            }, true);
            window.addEventListener('resize', function () {
                document.querySelectorAll('.hr-hover-panel.is-open').forEach(place);
            });

            function start() { document.querySelectorAll('.hr-hover-panel').forEach(init); }
            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
        })();
    </script>
@endonce
