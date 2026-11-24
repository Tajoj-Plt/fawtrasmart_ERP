{{--
    قائمة إجراءات الصفّ المنسدلة تُقصّ عند حافّة الجدول.

    كل جداول القسم ملفوفة فى `.table-responsive` (overflow-x: auto)،
    وبحسب مواصفة CSS فإن ضبط محورٍ واحد بغير `visible` يفرض على المحور
    الآخر — إن كان `visible` — أن يُحسَب `auto` أيضاً. فحين تُفتح القائمة
    المنسدلة (`dropdown-menu`) قرب أسفل الجدول أو حافته، تُقصّ بصندوق
    `overflow` الخاص بالجدول نفسه — لا علاقة للأمر بخوارزميّة تصادم
    Popper، فتوسيع `boundary` فى إعداداتها لا يُصلح شيئاً.

    الحلّ: عند `show.bs.dropdown` تُنتزَع القائمة من مكانها وتُلحَق بـ
    `document.body` مباشرة، وتُموضَع بـ`position: fixed` بحساب
    `getBoundingClientRect` لزرّ الفتح — فتخرج من كل صندوق قصّ مهما كان.
    وعند `hidden.bs.dropdown` تعود إلى مكانها الأصليّ (المحفوظ فى
    WeakMap) كي لا يبقى فى `<body>` عقدة تائهة تُفسد أيّ تحديث Ajax لاحق
    يستبدل محتوى الجدول.

    الاستماع بالتفويض على `document` — لا ربط لكل قائمة على حدة، فيسري
    تلقائياً على كل جدول فى كل شاشة من غير سطر توصيل واحد، ويستمرّ يعمل
    بعد أي استبدال Ajax للجدول دون إعادة ربط.

    التفعيل مقصورٌ على القوائم التي زرّها داخل `.table-responsive` وحدها؛
    قوائم أخرى فى الصفحة (قائمة المطبوعات، شريط التصفّح) لا تُمسّ إطلاقاً.

    تعطيل Popper: `data-bs-display="static"` يُضاف على الزرّ فى مرحلة
    الالتقاط (capture) — أي قبل أن ينشئ Bootstrap نسخته من القائمة عند
    أول نقرة — فيتجنّب أيّ تصادم بين حساب Popper التلقائي والموضع اليدويّ
    بعد نقل العقدة. الالتقاط يسبق مستمع Bootstrap نفسه (وهو مسجَّل على
    `document` بمرحلة الانتشار العادية) بصرف النظر عن ترتيب تحميل السكربتات.

    يُضمَّن مرّة واحدة من `purchases::master` فيسري على كل شاشات القسم.
--}}
@once
    <script>
        (function () {
            if (window.__poDropdownFixInstalled) {
                return;
            }
            window.__poDropdownFixInstalled = true;

            var originalSlots = new WeakMap(); // menu  -> {parent, next}
            var detachedMenus = new WeakMap(); // toggle -> menu
            var openToggle = null;

            function isTableToggle(el) {
                return !!(
                    el &&
                    el.getAttribute &&
                    el.getAttribute('data-bs-toggle') === 'dropdown' &&
                    el.closest &&
                    el.closest('.table-responsive')
                );
            }

            document.addEventListener('click', function (e) {
                var toggle = e.target && e.target.closest
                    ? e.target.closest('[data-bs-toggle="dropdown"]')
                    : null;

                if (isTableToggle(toggle) && !toggle.hasAttribute('data-bs-display')) {
                    toggle.setAttribute('data-bs-display', 'static');
                }
            }, true);

            function findMenu(toggle) {
                var parent = toggle.parentElement;
                if (!parent) return null;

                for (var i = 0; i < parent.children.length; i++) {
                    if (parent.children[i].classList.contains('dropdown-menu')) {
                        return parent.children[i];
                    }
                }

                return null;
            }

            function positionMenu(toggle, menu) {
                menu.style.visibility = 'hidden';
                menu.style.display = 'block';
                menu.style.position = 'fixed';
                menu.style.margin = '0';

                var toggleRect = toggle.getBoundingClientRect();
                var menuRect = menu.getBoundingClientRect();
                var viewportW = document.documentElement.clientWidth;
                var viewportH = document.documentElement.clientHeight;
                var gap = 4;

                var dir = document.documentElement.getAttribute('dir')
                    || getComputedStyle(document.documentElement).direction;
                var isRtl = dir === 'rtl';
                var alignToRight = menu.classList.contains('dropdown-menu-end') ? !isRtl : isRtl;

                var left = alignToRight ? (toggleRect.right - menuRect.width) : toggleRect.left;
                if (left < gap) left = gap;
                if (left + menuRect.width > viewportW - gap) {
                    left = Math.max(gap, viewportW - menuRect.width - gap);
                }

                var top = toggleRect.bottom;
                var flips = (top + menuRect.height > viewportH - gap)
                    && (toggleRect.top - menuRect.height > gap);
                if (flips) {
                    top = toggleRect.top - menuRect.height;
                }

                menu.style.top = Math.round(top) + 'px';
                menu.style.left = Math.round(left) + 'px';
                menu.style.right = 'auto';
                menu.style.zIndex = '1071';
                menu.style.visibility = 'visible';
            }

            document.addEventListener('show.bs.dropdown', function (e) {
                var toggle = e.target;
                if (!isTableToggle(toggle)) return;

                var menu = findMenu(toggle);
                if (!menu) return;

                if (!originalSlots.has(menu)) {
                    originalSlots.set(menu, {
                        parent: menu.parentElement,
                        next: menu.nextElementSibling,
                    });
                }

                document.body.appendChild(menu);
                menu.classList.add('po-dropdown-detached');
                detachedMenus.set(toggle, menu);
                openToggle = toggle;

                positionMenu(toggle, menu);
            });

            document.addEventListener('hidden.bs.dropdown', function (e) {
                var toggle = e.target;
                var menu = detachedMenus.get(toggle);
                if (!menu) return;

                var slot = originalSlots.get(menu);
                if (slot && slot.parent) {
                    if (slot.next && slot.next.parentElement === slot.parent) {
                        slot.parent.insertBefore(menu, slot.next);
                    } else {
                        slot.parent.appendChild(menu);
                    }
                }

                menu.classList.remove('po-dropdown-detached');
                menu.style.position = '';
                menu.style.top = '';
                menu.style.left = '';
                menu.style.right = '';
                menu.style.margin = '';
                menu.style.zIndex = '';
                menu.style.visibility = '';
                menu.style.display = '';

                detachedMenus.delete(toggle);
                if (openToggle === toggle) openToggle = null;
            });

            // القائمة صارت ثابتة الموضع (`fixed`) بعد النقل فلا تتبع الزرّ
            // أثناء التمرير — تُغلَق بدل أن تبقى معلَّقة بمكانها القديم.
            window.addEventListener('scroll', function () {
                if (!openToggle) return;

                var instance = window.bootstrap
                    && window.bootstrap.Dropdown
                    && window.bootstrap.Dropdown.getInstance(openToggle);

                if (instance) instance.hide();
            }, true);
        })();
    </script>
@endonce
