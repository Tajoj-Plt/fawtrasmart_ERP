{{--
    إرشادات الحقول في علامة «!» بجوار اسم الحقل — تظهر بمرور المؤشّر عليها.

    ══════════════════ لماذا؟ ══════════════════

    الشرح التفصيلي تحت كل مفتاحٍ وكل حقل (`.sl-help`، `.sl-hint`، `.po-help`،
    `.po-hint`، `.cl-help`، `.rb-help`، وسطر شرح المفتاح، وأسطر الشرح في لوحات
    التحكّم) يُطيل الصفحة ويزاحم الحقول نفسها. هنا يبقى النصّ كما هو في القوالب
    وملفات اللغة، ويُنقَل عند التحميل إلى علامة «!» بجوار عنوان ما يشرحه — فلا
    يتغيّر مصدر أيّ إرشاد.

    ══════════════════ السلوك ══════════════════

      • مرور مؤشّر الفأرة على «!» يُظهر الشرح، ومغادرتُه تُخفيه بعد لحظة —
        تكفي لنقل المؤشّر إلى النافذة نفسها (فيها روابط أحياناً).
      • النقر يُثبّت النافذة مفتوحةً حتى نقرٍ ثانٍ عليها أو خارجها أو Esc.
      • اللمس (جوّال/لوحي) لا مرور فيه: اللمسة تفتح وتُثبّت.

    ══════════════════ الجذور ══════════════════

      الموارد البشرية `.hr-hints` (إعدادات الحضور: `.hint` و`.stk-help`) ·
      المبيعات `.sl-scope` · المشتريات `.sl-scope` و`.po-page` · العملاء
      `.cl-scope` و`.cl-page` · خطوط السير `#route-builder` · النوافذ `.modal`
      (نوافذ Bootstrap تُرسَم خارج نطاق الشاشة غالباً).

    ══════════════════ إلى أين يذهب الشرح ══════════════════

      • شرح المفتاح (`.sl-switch__hint` / `.cl-switch__hint`) ← عنوان المفتاح.
      • سطرٌ نصّيّ في رأس بطاقة المستند (`.sl-doc-card__head` / `.po-card__head`)
        ← عنوان البطاقة. و`.cl-card-hint` في `.card-header` ← `.card-title`.
      • خطوط السير: `.rb-help` في `.rb-section-head` ← عنوانه، وداخل
        `label.rb-field` ← `.rb-field__label`، وفي نافذة `.rb-modal__dialog` ← `h3`.
      • سطر شرحٍ تحت حقل ← أقرب `label` يسبقه في الحقل.
      • فقرة شرحٍ (`p`/`div`) في بطاقة مستندٍ بلا حقل ← عنوان البطاقة.
      • `.cl-help` بلا حقل ← أقرب عنوان: محرّر الشرائح (`h6`)، عنوان النافذة،
        `summary` في `<details>`، عنوان القسم، عنوان البطاقة.
      • لوحات التحكّم: `.sd-card-head small` / `.pd-card-head small` ← عنوان البطاقة؛
        `#client-dashboard`: `.cd-seg-label > small` ← اسم الشريحة، `.cd-card-tip` ← عنوان البطاقة.

    ══════════════════ ما يبقى نصّاً ══════════════════

      • ما له `id` أو فيه عنصرٌ له `id` — سكربت الشاشة يكتب فيه.
      • `[data-hint-keep]` — استثناءٌ صريح من القالب (حالةٌ أو رقمٌ أو قائمةٌ
        فارغة يجب أن تُقرأ دون مرور).
      • المخفيّ (`[hidden]`، `.d-none`، `display:none` عليه أو على أبٍ له).
      • التحذيرات والأخطاء (`.text-warning`، `.text-danger`).
      • الروابط، والعناوين نفسها (`label`)، و«(اختياري)» داخل العنوان.
      • قيمٌ معروضة: خلايا الجداول، `.form-control-plaintext`، بطاقات الإحصاء،
        صفوف الإجماليات، ذيل البطاقة والنموذج، شريط الأدوات، عدّاد النتائج،
        السجلّ الزمني، عدّاد الملاحظات في خطوط السير.
      • منطقة رفع الملفات، و`.sl-hint-line`/`.po-hint-line`.
      • شارةٌ فيها أيقونة في رأس البطاقة (العملة) — قيمةٌ تُقرأ لا شرح.
      • ما لا عنوان يُنسَب إليه.

    بلا جافاسكربت يبقى الشرح ظاهراً تحت الحقل كما كان.

    الاستعمال: `@include('components.hint-tips')` مرّة في الشاشة.
--}}

@once
    <style>
        .hint-tip {
            display: inline-flex; align-items: center; justify-content: center;
            flex: none;
            inline-size: 1.1rem; block-size: 1.1rem;
            margin-inline-start: .35rem;
            padding: 0;
            border-radius: 50%;
            border: 1.5px solid var(--app-primary, #7367f0);
            background: var(--app-primary-soft, rgba(115, 103, 240, .1));
            color: var(--app-primary, #7367f0);
            font: 800 .68rem/1 system-ui, sans-serif;
            vertical-align: middle;
            position: relative; top: -1px;
            cursor: help;
            transition: background .15s ease, color .15s ease;
        }

        .hint-tip:hover,
        .hint-tip[aria-expanded="true"] {
            background: var(--app-primary, #7367f0);
            color: #fff;
        }

        .hint-tip:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .hint-tip__pop {
            position: fixed; z-index: 1070;
            inline-size: max-content;
            max-inline-size: min(22rem, calc(100vw - 1.5rem));
            overflow: auto;
            padding: .55rem .85rem .6rem;
            padding-inline-end: 2rem;
            background: var(--app-surface, #fff);
            color: var(--app-text, #4b4b4b);
            border: 1px solid var(--app-border, rgba(34, 41, 47, .12));
            border-block-start: 3px solid var(--app-primary, #7367f0);
            border-radius: .5rem;
            box-shadow: 0 .6rem 1.6rem rgba(34, 41, 47, .18);
            font-size: .8rem; font-weight: 400; line-height: 1.8;
            text-align: start;
            letter-spacing: normal;
            text-transform: none;
        }

        /* فوق نوافذ Bootstrap (1050–1060) حين تكون العلامة داخل نافذة */
        .modal-open .hint-tip__pop { z-index: 1090; }

        .hint-tip__pop[hidden] { display: none !important; }

        .hint-tip__body p { margin: 0 0 .35rem; }
        .hint-tip__body p:last-child { margin-bottom: 0; }
        .hint-tip__body a { color: var(--app-primary, #7367f0); }
        .hint-tip__body .feather,
        .hint-tip__body .fas,
        .hint-tip__body .fa,
        .hint-tip__body .far { margin-inline-end: .25rem; }

        .hint-tip__close {
            position: absolute; top: .2rem; inset-inline-end: .35rem;
            border: 0; background: transparent;
            color: var(--app-muted, #6e6b7b);
            font-size: 1.2rem; line-height: 1;
            padding: 0 .2rem;
            cursor: pointer;
        }

        .hint-tip__close:hover { color: var(--app-text, #4b4b4b); }
    </style>

    <script>
        /* نافذةٌ واحدة للصفحة كلّها تُملأ من العلامة التي تحت المؤشّر.

           النقر على العلامة يُوقَف انتشاره: في لوحات التحكّم الصفوف والبطاقات
           `[data-segment]` تفتح درج التفاصيل عند النقر أو Enter، وفي النماذج
           العلامة داخل `label` أو `summary` فالنقر كان سيقلب المفتاح أو يركّز
           الحقل أو يطوي القسم.

           المرور بالفأرة وحدها (`pointerType === 'mouse'`): اللمس يُطلق
           `pointerenter` ثم `click` معاً، فلو فتح المرورُ لأغلقه النقرُ فوراً. */
        (function () {
            var LABEL = {!! json_encode(__('guide.hint'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!};
            var CLOSE = {!! json_encode(__('guide.close'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!};
            var HIDE_DELAY = 180;

            /* جذور الشاشات، وأصناف سطر الشرح، وبطاقات المستند — لكل الوحدات.
               الإضافة هنا تكفي لوحدةٍ أخرى بالنمط نفسه. */
            var ROOTS = '.sl-scope, .po-page, .cl-scope, .cl-page, #route-builder, .modal, .hr-hints';
            var HINTS = '.sl-help, .sl-hint, .po-help, .po-hint, .cl-help, .cl-card-hint, .rb-help, '
                + '.hr-hints .stk-help, .hr-hints .av2-field > .hint';
            var SWITCH = '.sl-switch, .cl-switch';
            var SWITCH_HINT = '.sl-switch__hint, .cl-switch__hint';
            var SWITCH_TITLE = '.sl-switch__title, .cl-switch__title';
            var CARD = '.sl-doc-card, .po-card';
            var CARD_HEAD = '.sl-doc-card__head, .po-card__head';
            var CARD_TITLE = '.sl-doc-card__title, .po-card__title';

            var KEEP_SELF = 'a, label, [hidden], [data-hint-keep], .d-inline, .d-none, .text-warning, .text-danger, '
                + '.results-info, .sl-hint-line, .po-hint-line';
            var KEEP_INSIDE = '[data-hint-keep], [hidden], [style*="display:none"], [style*="display: none"], '
                + '.sl-form-foot, .sl-upload, .po-upload, td, th, '
                + '.sl-stat, .po-stat, .cl-stat, .sl-totals__row, .po-totals, .po-card__foot, .po-toolbar, '
                + '.form-control-plaintext, .po-timeline, .rb-notes-head, .rb-pane__foot, .hint-tip__pop';

            /* حدود الحقل: البحث عن عنوانٍ لا يعبرها */
            var FIELD_BOUNDARY = ROOTS + ', form, .card, .card-body, .card-header, .tab-pane, .row, '
                + '.sl-switch-row, table, .sl-section, .cl-section, .modal-body, details, .rb-card, .cl-seg-editor, '
                + CARD + ', ' + CARD_HEAD + ', .sl-doc-card__body, .po-card__body';

            var DASHBOARD_HEADS = '#sales-dashboard .sd-card-head small, #purchases-dashboard .pd-card-head small';

            var pop = null;
            var current = null;
            var pinned = false;
            var hideTimer = null;
            var sources = new WeakMap();

            function textOf(el) {
                return (el.textContent || '').replace(/\s+/g, ' ').trim();
            }

            function cancelHide() {
                if (hideTimer) {
                    clearTimeout(hideTimer);
                    hideTimer = null;
                }
            }

            /* مهلةٌ قصيرة لا إخفاءٌ فوري: بين العلامة والنافذة فجوةٌ صغيرة،
               ومن ينقل المؤشّر إلى رابطٍ داخل النافذة يعبرها */
            function scheduleHide() {
                cancelHide();
                if (pinned) { return; }
                hideTimer = setTimeout(function () {
                    hideTimer = null;
                    if (!pinned) { close(false); }
                }, HIDE_DELAY);
            }

            function ensurePop() {
                if (pop) { return pop; }
                pop = document.createElement('div');
                pop.className = 'hint-tip__pop';
                pop.id = 'hintTipPop';
                pop.setAttribute('role', 'tooltip');
                pop.hidden = true;

                var closer = document.createElement('button');
                closer.type = 'button';
                closer.className = 'hint-tip__close';
                closer.setAttribute('aria-label', CLOSE);
                closer.innerHTML = '&times;';

                var body = document.createElement('div');
                body.className = 'hint-tip__body';

                pop.appendChild(closer);
                pop.appendChild(body);

                pop.addEventListener('pointerenter', function (e) {
                    if (e.pointerType === 'mouse') { cancelHide(); }
                });
                pop.addEventListener('pointerleave', function (e) {
                    if (e.pointerType === 'mouse') { scheduleHide(); }
                });

                document.body.appendChild(pop);
                return pop;
            }

            function makeButton(title) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'hint-tip';
                btn.textContent = '!';
                btn.setAttribute('aria-expanded', 'false');
                btn.setAttribute('aria-controls', 'hintTipPop');
                btn.setAttribute('aria-label', title ? LABEL + ': ' + title : LABEL);

                btn.addEventListener('pointerenter', function (e) {
                    if (e.pointerType !== 'mouse') { return; }
                    cancelHide();
                    if (current !== btn) { open(btn); }
                });

                btn.addEventListener('pointerleave', function (e) {
                    if (e.pointerType === 'mouse') { scheduleHide(); }
                });

                /* النقر يُثبّت ما فتحه المرور، والنقر على مثبَّتٍ يُغلقه */
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    cancelHide();

                    if (current === btn && pinned) {
                        close(false);
                        return;
                    }

                    if (current !== btn) { open(btn); }
                    pinned = true;
                });

                btn.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.stopPropagation(); }
                });

                sources.set(btn, document.createElement('div'));
                return btn;
            }

            /* ينقل عُقَد الشرح نفسها (بروابطها وأيقوناتها) إلى مخزن العلامة
               ثم يحذف السطر من الصفحة */
            function moveInto(btn, el) {
                var p = document.createElement('p');
                while (el.firstChild) { p.appendChild(el.firstChild); }
                sources.get(btn).appendChild(p);
                el.remove();
            }

            function buttonIn(target, title) {
                var btn = null;
                for (var i = 0; i < target.children.length; i++) {
                    if (target.children[i].classList.contains('hint-tip')) { btn = target.children[i]; }
                }
                if (!btn) {
                    btn = makeButton(title);
                    target.appendChild(btn);
                }
                return btn;
            }

            /* ما يبقى نصّاً في مكانه — انظر رأس القالب */
            function keepInPlace(el) {
                if (el.id || !textOf(el)) { return true; }
                if (el.matches(KEEP_SELF)) { return true; }
                if (el.style.display === 'none') { return true; }
                if (el.closest(KEEP_INSIDE)) { return true; }
                if (el.querySelector('[id]')) { return true; }
                if (el.matches('.sl-hint, .po-hint') && el.closest('label')) { return true; }
                return false;
            }

            /* أقرب `label` يسبق السطر داخل الحقل نفسه — لا يعبر حدود صفٍّ أو
               بطاقة أو قسم كي لا يُنسَب شرحُ حقلٍ إلى عنوان حقلٍ مجاور */
            function labelFor(el) {
                var node = el.parentElement;
                for (var depth = 0; node && depth < 3; depth++, node = node.parentElement) {
                    if (node.matches(FIELD_BOUNDARY)) { return null; }
                    var labels = node.querySelectorAll('label:not(.sl-switch):not(.cl-switch)');
                    for (var i = labels.length - 1; i >= 0; i--) {
                        if (labels[i].compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING) {
                            return labels[i];
                        }
                    }
                }
                return null;
            }

            function first(scope, selector) {
                return scope ? scope.querySelector(selector) : null;
            }

            /* شرح العملاء بلا حقل ← أقرب عنوانٍ يُنسَب إليه */
            function clientTitleFor(el) {
                var editor = el.closest('.cl-seg-editor');
                if (editor) { return first(editor, 'h6'); }

                var modal = el.closest('.modal-content');
                if (modal) { return first(modal, '.modal-title'); }

                var details = el.closest('details');
                if (details) { return first(details, ':scope > summary'); }

                var section = el.closest('.cl-section');
                if (section) { return first(section, '.cl-section__title'); }

                var card = el.closest('.card');
                if (card) {
                    return first(card, '.card-header .card-title')
                        || first(card, '.card-header > span:first-child');
                }

                return null;
            }

            function targetFor(el) {
                var parent = el.parentElement;

                /* سطرٌ في رأس بطاقة المستند بجوار عنوانها («الحقول المعلَّمة
                   مطلوبة»). الشارة ذات الأيقونة هناك قيمةٌ (العملة) فتبقى */
                if (parent && parent.matches(CARD_HEAD)) {
                    if (el.querySelector('i, svg')) { return null; }
                    return parent.querySelector(CARD_TITLE);
                }

                /* إعدادات الحضور: عنوانُ الحقل هو `label` الابن المباشر — لا
                   أقربُ `label` يسبقه، فقد يكون خانةَ اختيارٍ (أيام العطلة) */
                if (el.matches('.av2-field > .hint')) {
                    return el.parentElement.querySelector(':scope > label');
                }

                if (el.matches('.cl-card-hint')) {
                    return first(el.closest('.card-header'), '.card-title');
                }

                /* خطوط السير */
                if (el.matches('.rb-help')) {
                    var sectionHead = el.closest('.rb-section-head');
                    if (sectionHead) { return first(sectionHead, 'h2'); }

                    var field = el.closest('label.rb-field');
                    if (field) { return first(field, '.rb-field__label'); }

                    var dialog = el.closest('.rb-modal__dialog');
                    if (dialog && el.matches('p')) { return first(dialog, 'h3'); }

                    return null;
                }

                var label = labelFor(el);
                if (label) { return label; }

                /* فقرة شرحٍ داخل بطاقة مستندٍ لا حقل لها ← عنوان البطاقة */
                if (el.matches('p, div')) {
                    var card = el.closest(CARD);
                    if (card) { return card.querySelector(CARD_TITLE); }
                }

                if (el.matches('.cl-help')) { return clientTitleFor(el); }

                return null;
            }

            function convertDocuments(root) {
                root.querySelectorAll(SWITCH_HINT).forEach(function (hint) {
                    var sw = hint.closest(SWITCH);
                    var title = sw && sw.querySelector(SWITCH_TITLE);
                    if (!title || !textOf(hint)) { return; }
                    moveInto(buttonIn(title, textOf(title)), hint);
                });

                root.querySelectorAll(HINTS).forEach(function (hint) {
                    if (!hint.isConnected || keepInPlace(hint)) { return; }
                    var target = targetFor(hint);
                    if (!target) { return; }

                    /* الشرح قد يكون داخل العنوان نفسه (خيار راديو) — فلا يدخل
                       نصُّه في اسم الزرّ المقروء */
                    var title = target.contains(hint)
                        ? textOf(target).replace(textOf(hint), '').trim()
                        : textOf(target);

                    moveInto(buttonIn(target, title), hint);
                });
            }

            function convertDashboardHeads() {
                document.querySelectorAll(DASHBOARD_HEADS).forEach(function (small) {
                    if (small.id || !textOf(small) || small.closest('[data-segment]')) { return; }
                    var box = small.parentElement;
                    var title = box && box.querySelector('h4');
                    if (!title) { return; }
                    moveInto(buttonIn(title, textOf(title)), small);
                });
            }

            function convertClientDashboard(root) {
                root.querySelectorAll('.cd-seg-label > small').forEach(function (small) {
                    if (!textOf(small)) { return; }
                    var label = small.parentElement;
                    var name = textOf(label).replace(textOf(small), '').trim();
                    var btn = makeButton(name);
                    label.insertBefore(btn, small);
                    moveInto(btn, small);
                });

                root.querySelectorAll('.cd-card-tip').forEach(function (tip) {
                    var card = tip.closest('.cd-card');
                    var head = card && card.querySelector('.cd-card-head h4');
                    if (!head || !textOf(tip)) { return; }
                    moveInto(buttonIn(head, textOf(head)), tip);
                });
            }

            function place() {
                if (!current) { return; }
                var r = current.getBoundingClientRect();
                var vw = document.documentElement.clientWidth;
                var vh = window.innerHeight;
                var gap = 6, edge = 12;

                pop.style.maxHeight = '';
                var w = pop.offsetWidth;
                var h = pop.offsetHeight;

                var rtl = window.getComputedStyle(current).direction === 'rtl';
                var left = rtl ? r.right - w : r.left;
                left = Math.max(edge, Math.min(left, vw - w - edge));

                var below = vh - r.bottom - gap - edge;
                var above = r.top - gap - edge;
                var top;

                if (h <= below || below >= above) {
                    top = r.bottom + gap;
                    pop.style.maxHeight = Math.max(120, below) + 'px';
                } else {
                    pop.style.maxHeight = Math.max(120, above) + 'px';
                    top = Math.max(edge, r.top - gap - Math.min(h, above));
                }

                pop.style.left = left + 'px';
                pop.style.top = top + 'px';
            }

            function open(btn) {
                ensurePop();
                close(false);

                var body = pop.querySelector('.hint-tip__body');
                body.innerHTML = '';
                sources.get(btn).childNodes.forEach(function (node) {
                    body.appendChild(node.cloneNode(true));
                });

                pop.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                current = btn;
                place();
            }

            function close(returnFocus) {
                cancelHide();
                pinned = false;
                if (!current) { return; }
                var btn = current;
                pop.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
                current = null;
                if (returnFocus) { btn.focus(); }
            }

            document.addEventListener('click', function (e) {
                if (!current) { return; }
                if (e.target.closest('.hint-tip__close')) { close(true); return; }
                if (!pop.contains(e.target)) { close(false); }
            });

            /* Esc يُغلق الشرح وحده: نافذة Bootstrap تحته تُغلق بـEsc أيضاً،
               فيُمنع انتشاره متى كان الشرح مفتوحاً */
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && current) {
                    e.stopPropagation();
                    close(true);
                }
            }, true);

            window.addEventListener('resize', place);
            window.addEventListener('scroll', place, true);

            function init() {
                /* جذرٌ داخل جذر (`po-page` في `sl-scope`، نافذةٌ داخل الشاشة)
                   يُعالَج مع أبيه مرّةً واحدة */
                document.querySelectorAll(ROOTS).forEach(function (root) {
                    if (root.parentElement && root.parentElement.closest(ROOTS)) { return; }
                    convertDocuments(root);
                });

                convertDashboardHeads();

                var clientDashboard = document.getElementById('client-dashboard');
                if (clientDashboard) { convertClientDashboard(clientDashboard); }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endonce
