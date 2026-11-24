{{--
    ══════════════════════════════════════════════════════════════════════
    قائمة إجراءات الصفّ — تخرج من حدود الجدول وتلتصق بزرّها
    ══════════════════════════════════════════════════════════════════════

    **العطل.** بطاقة الجدول تُقصّ ما يخرج عنها مرّتين:

        .sl-scope .card    { overflow: hidden; }   ← لتبقى الزوايا مستديرة
        .table-responsive  { overflow-x: auto; }   ← من بوتستراب، للتمرير الأفقي

    وبوتستراب يضع `dropdown-menu` **داخل** البطاقة بـ`position: absolute`،
    والعنصر المطلق يقصّه أيّ سلفٍ عليه `overflow` غير `visible`. فقائمة
    إجراءات الصفّ تظهر مقصوصةً عند حافّة الجدول — وكلّما طالت القائمة
    اختفى أكثرُها.

    ولا يصحّ علاجُها بـ`overflow: visible`: يذهب معه التمرير الأفقيّ الذي
    يُبقي الجدول العريض مقروءاً على الشاشات الضيّقة.

    **العلاج** منقولٌ عن `stock::layouts.partials.stock-theme` — نفس العطل
    عولج هناك في «الأذون المخزنية»، وشرحُه المفصَّل هناك يذكر محاولتين
    فاشلتين قبله (`data-bs-strategy="fixed"` سمةٌ لا يقرؤها بوتستراب،
    و`popperConfig.strategy` يومض في زاوية الشاشة لأنّ أوّل تحديثٍ من
    Popper غير متزامن). الخلاصة:

      • `display: 'static'` يجعل بوتستراب يتخطّى Popper لهذه القوائم،
      • ثمّ تُنقل القائمة إلى `<body>` ويُحسَب موضعُها **تزامنياً** من
        مستطيل الزرّ — فلا وميض ولا اعتماد على تفاصيل Popper.

    نسخةٌ لا مشاركة: قالبٌ في المبيعات يشير إلى `stock::` ينكسر بمجرّد
    إطفاء وحدة المخزون في `modules_statuses.json` — وهي القاعدة نفسها
    التي بُني عليها `sales::partials.document-theme`.

    الاستعمال — مرّةً في الشاشة التي فيها جدولٌ بقوائم صفوف:

        @include('sales::partials.table-menu')
--}}

@once
    <script>
        (function () {
            'use strict';

            var GAP = 4;
            var open = null;   // { menu, toggle, home, next }

            /* حاوياتُ تقارير المبيعات (`rpt-card` وصندوقا التمرير `rpt-scroll`/`mx-scroll`)
               تقصّ كالبطاقة: `overflow: auto` للتمرير الأفقي والشبكةُ الشهرية بارتفاعٍ
               محدود — فكانت قائمةُ إجراءات الصفّ تُفتح داخل الجدول وتُقصّ. */
            function isClipped(element) {
                return !! element.closest('.card, .table-responsive, .modal-body, .rpt-card, .rpt-scroll, .mx-scroll');
            }

            /* بوتستراب ٤ يعالج هذه القائمة أيضاً؟ إذن لا تُنقَل.
             *
             * `master` يحمّل بوتستراب ٥ (CDN) ثمّ `vendors.min.js` وفيه
             * jQuery وبوتستراب ٤ وPopper ١. فزرٌّ يحمل `data-toggle`
             * و`data-bs-toggle` معاً يعالجه الاثنان في النقرة الواحدة:
             * الخامس يطلق `show.bs.dropdown` فتُنقَل القائمة إلى
             * `<body>`، ثمّ يأتي الرابع فلا يجدها في غلافه
             * (`_getMenuElement()` تعيد `null`) ويمرّرها إلى Popper ١:
             *
             *     Cannot read properties of null (reading 'setAttribute')
             *
             * فلا تُفتح القائمة أصلاً. الأصل ألّا يجتمع الوسمان — وأزرار
             * الصفوف هنا صارت بـ`data-bs-toggle` وحده — وهذا الفحص حارسٌ
             * لئلّا يعود العطل مع أوّل زرٍّ يُنسَخ من شاشةٍ قديمة: قائمةٌ
             * مقصوصة أهون من قائمةٍ لا تُفتح. */
            function ownedByBootstrap4(toggle) {
                return toggle.hasAttribute('data-toggle')
                    && typeof jQuery !== 'undefined'
                    && !! (jQuery.fn && jQuery.fn.dropdown);
            }

            function place() {
                if (! open) {
                    return;
                }

                var menu = open.menu;
                var rect = open.toggle.getBoundingClientRect();
                var width = menu.offsetWidth;
                var height = menu.offsetHeight;

                // المحاذاة الأفقية: `-end` تحاذي الحافّة البعيدة عن اتّجاه القراءة
                var rtl = getComputedStyle(document.documentElement).direction === 'rtl';
                var alignEnd = menu.classList.contains('dropdown-menu-end');
                var left = (alignEnd !== rtl) ? rect.right - width : rect.left;

                if (alignEnd && rtl) {
                    left = rect.right - width;
                } else if (! alignEnd && rtl) {
                    left = rect.left;
                }

                left = Math.max(GAP, Math.min(left, window.innerWidth - width - GAP));

                // رأسياً: تحت الزرّ، وتُقلَب فوقه إن ضاق ما تحته
                var top = rect.bottom + GAP;

                if (top + height > window.innerHeight - GAP && rect.top - height - GAP > GAP) {
                    top = rect.top - height - GAP;
                }

                top = Math.max(GAP, Math.min(top, window.innerHeight - height - GAP));

                menu.style.position = 'fixed';
                menu.style.left = Math.round(left) + 'px';
                menu.style.top = Math.round(top) + 'px';
                menu.style.right = 'auto';
                menu.style.bottom = 'auto';
                menu.style.margin = '0';
                menu.style.transform = 'none';
            }

            /* بوتستراب يتخطّى Popper حين يكون `display` ساكناً */
            document.addEventListener('mousedown', function (event) {
                var toggle = event.target.closest('[data-bs-toggle="dropdown"]');

                if (! toggle || toggle.dataset.slDropdownReady) {
                    return;
                }

                if (typeof bootstrap === 'undefined' || ! bootstrap.Dropdown) {
                    return;
                }

                if (! isClipped(toggle) || ownedByBootstrap4(toggle)) {
                    return;
                }

                toggle.dataset.slDropdownReady = '1';

                // التهيئة قبل بوتستراب، فيتبنّى `getOrCreateInstance` نسختنا
                bootstrap.Dropdown.getOrCreateInstance(toggle, { display: 'static' });
            }, true);

            document.addEventListener('show.bs.dropdown', function (event) {
                var toggle = event.target;
                var container = toggle.closest('.dropdown, .btn-group, .dropup');
                var menu = container ? container.querySelector(':scope > .dropdown-menu') : null;

                if (! menu || ! isClipped(menu) || ownedByBootstrap4(toggle)) {
                    return;
                }

                open = { menu: menu, toggle: toggle, home: menu.parentNode, next: menu.nextSibling };

                document.body.appendChild(menu);
                place();
            });

            // القياس بعد الرسم: العرض والارتفاع لا يُعرفان قبل ظهورها
            document.addEventListener('shown.bs.dropdown', place);

            document.addEventListener('hidden.bs.dropdown', function () {
                if (! open) {
                    return;
                }

                var menu = open.menu;

                menu.style.position = '';
                menu.style.left = '';
                menu.style.top = '';
                menu.style.right = '';
                menu.style.bottom = '';
                menu.style.margin = '';
                menu.style.transform = '';

                open.home.insertBefore(menu, open.next);
                open = null;
            });

            // `fixed` منسوبةٌ إلى النافذة: التمرير يُزيح الزرّ لا القائمة
            window.addEventListener('scroll', place, true);
            window.addEventListener('resize', place);
        })();
    </script>
@endonce
