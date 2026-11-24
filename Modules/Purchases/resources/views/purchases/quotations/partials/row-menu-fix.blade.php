{{--
    قائمة إجراءات الصفّ — تخرج من حدود الجدول
--}}

@once
    <script>
        (function () {
            'use strict';

            var GAP = 4;
            var open = null;

            function isClipped(element) {
                return !! element.closest('.card, .table-responsive, .modal-body');
            }

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

                var rtl = getComputedStyle(document.documentElement).direction === 'rtl';
                var alignEnd = menu.classList.contains('dropdown-menu-end');
                var left = (alignEnd !== rtl) ? rect.right - width : rect.left;

                left = Math.max(GAP, Math.min(left, window.innerWidth - width - GAP));

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

            window.addEventListener('scroll', place, true);
            window.addEventListener('resize', place);
        })();
    </script>
@endonce
