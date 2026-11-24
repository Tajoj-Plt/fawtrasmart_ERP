{{--
    احتياطُ زرّ القائمة الجانبية — مضمَّنٌ في الصفحة لا في ملفٍ خارجي.

    القائمة يشغّلها app.js الذي يُحمَّل آخر الصفحة، وقد يتأخّر (شبكة بطيئة،
    سكربتات CDN قبله) أو يكون المتصفّح محتفظاً بنسخة قديمة منه. في الحالتين
    كان الزرّ ميتاً حتى يجهز. هذا السكربت لا يعتمد على شيء: لا jQuery ولا
    app.js، ويعمل من لحظة ظهور الهيدر. يتنحّى فوراً حين تجهز القائمة
    الحقيقية (`jQuery.app.menu` بحالةٍ محسوبة) فلا يزدوج التبديل.

    وهو داخل HTML الصفحة، والصفحات لا تُخزَّن في ذاكرة المتصفّح، فيصل لكل
    جهاز مهما كانت نسخة app.js التي عنده.
--}}
<script>
(function () {
    if (window.__fawtraMenuToggleFallback) { return; }
    window.__fawtraMenuToggleFallback = true;

    function menuReady() {
        // `__fawtraAppMenu` يبقى صحيحاً حتى لو حمّلت الشاشة jQuery ثانية
        var m = window.__fawtraAppMenu ||
            (window.jQuery && window.jQuery.app && window.jQuery.app.menu);
        return !!(m && m.hidden !== null && m.expanded !== null);
    }

    function closest(el, selector) {
        while (el && el.nodeType === 1) {
            if (el.matches(selector)) { return el; }
            el = el.parentNode;
        }
        return null;
    }

    function setOverlay(shown) {
        var overlay = document.querySelector('.sidenav-overlay');
        if (!overlay) { return; }
        overlay.classList.remove(shown ? 'd-none' : 'd-block');
        overlay.classList.add(shown ? 'd-block' : 'd-none');
    }

    function fallbackToggle() {
        var cl = document.body.classList;
        var wide = window.innerWidth >= 1200;

        if (wide && !cl.contains('vertical-overlay-menu')) {
            // شاشة كبيرة: زرّ التثبيت يطوي القائمة ويبسطها
            if (cl.contains('menu-collapsed')) {
                cl.remove('menu-collapsed'); cl.add('menu-expanded');
            } else {
                cl.remove('menu-expanded'); cl.add('menu-collapsed');
            }
            return;
        }

        // شاشة أصغر من 1200: القائمة طبقةٌ فوق المحتوى — هذا ما تفعله
        // toOverlayMenu في app-menu.js عند التهيئة
        cl.remove('vertical-menu-modern'); cl.add('vertical-overlay-menu');
        cl.remove('menu-expanded'); cl.remove('menu-collapsed');

        if (cl.contains('menu-open')) {
            cl.remove('menu-open'); cl.add('menu-hide');
            setOverlay(false);
        } else {
            cl.remove('menu-hide'); cl.add('menu-open');
            setOverlay(true);
        }
    }

    // مرحلة الالتقاط على document: تسبق معالج jQuery المفوَّض في app.js،
    // وإيقاف الانتشار يمنع تشغيله بعدنا فلا تُبدَّل القائمة مرتين
    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.menu-toggle, .modern-nav-toggle');
        if (!btn || menuReady()) { return; }
        if (!document.querySelector('.main-menu-content')) { return; }

        e.preventDefault();
        e.stopPropagation();
        fallbackToggle();
    }, true);

    // النقر على الطبقة المعتمة يغلق القائمة، كما يفعل app.js بعد جهوزه
    document.addEventListener('click', function (e) {
        if (menuReady() || !closest(e.target, '.sidenav-overlay')) { return; }
        var cl = document.body.classList;
        if (!cl.contains('menu-open')) { return; }

        e.preventDefault();
        e.stopPropagation();
        cl.remove('menu-open'); cl.add('menu-hide');
        setOverlay(false);
    }, true);
})();
</script>
