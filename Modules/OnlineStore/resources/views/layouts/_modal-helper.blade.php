{{--
    فتح/إغلاق مودالات لوحة المتجر بأيّ واجهةٍ متاحة.

    القالب يحمّل Bootstrap 5 (CDN في <head>) و Bootstrap 4 (داخل Vuexy
    vendors.min.js آخر الصفحة) معاً، فأيُّ صيغةٍ واحدة — `data-toggle`
    وحدها أو `data-bs-toggle` وحدها أو `bootstrap.Modal.getOrCreateInstance`
    وحدها — تعمل في متصفّحٍ وتصمت في آخر. هنا مسارٌ واحد يجرّب بالترتيب:
    إضافة jQuery (Bootstrap 4) ← Bootstrap 5 ← عرضٌ يدوي.

    الاستعمال في القالب:
        <button type="button" data-os-modal="#myModal">فتح</button>
        <button type="button" data-os-dismiss>إلغاء</button>   (داخل المودال)
    وفي الجافاسكربت:
        osModal.show('#myModal');  osModal.hide('#myModal');

    لا تضع مع `data-os-modal` سمات `data-toggle`/`data-bs-toggle` — اجتماعها
    يفتح المودال مرتين (خلفيتان).
--}}
@once
<script>
(function () {
    if (window.osModal) { return; }

    function resolve(target) {
        if (!target) { return null; }
        if (typeof target !== 'string') { return target; }
        return document.getElementById(target.replace(/^#/, ''));
    }

    function manualShow(el) {
        el.classList.add('show');
        el.style.display = 'block';
        el.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        if (!document.querySelector('.modal-backdrop')) {
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    }

    function manualHide(el) {
        el.classList.remove('show');
        el.style.display = 'none';
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
    }

    window.osModal = {
        show: function (target) {
            var el = resolve(target);
            if (!el) { return; }
            var jq = window.jQuery;
            if (jq && jq.fn && jq.fn.modal) { jq(el).modal('show'); return; }
            var B = window.bootstrap;
            if (B && B.Modal) {
                (B.Modal.getOrCreateInstance ? B.Modal.getOrCreateInstance(el) : new B.Modal(el)).show();
                return;
            }
            manualShow(el);
        },
        hide: function (target) {
            var el = resolve(target);
            if (!el) { return; }
            var jq = window.jQuery;
            if (jq && jq.fn && jq.fn.modal) { jq(el).modal('hide'); return; }
            var B = window.bootstrap;
            if (B && B.Modal) {
                var inst = B.Modal.getInstance ? B.Modal.getInstance(el)
                    : (B.Modal.getOrCreateInstance ? B.Modal.getOrCreateInstance(el) : null);
                if (inst) { inst.hide(); return; }
            }
            manualHide(el);
        }
    };

    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) { return; }

        var opener = e.target.closest('[data-os-modal]');
        if (opener) {
            e.preventDefault();
            window.osModal.show(opener.getAttribute('data-os-modal'));
            return;
        }

        var closer = e.target.closest('[data-os-dismiss]');
        if (closer) {
            var modal = closer.closest('.modal');
            if (modal) { window.osModal.hide(modal); }
        }
    });
})();
</script>
@endonce
