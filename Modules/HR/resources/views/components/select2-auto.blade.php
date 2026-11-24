{{--
    Select2 موحّد لقسم الرواتب — مُضمَّنٌ مرّةً في `hr::master`.

    الاستعمال: أضِف الصنف `hr-s2` إلى أيّ `<select>`. لا حاجةَ لسطر JavaScript في الشاشة:
      • يُهيَّأ عند التحميل، ويُهيَّأ تلقائياً كلُّ `select.hr-s2` يُضاف لاحقاً
        (صفوف البنود المُستنسخة، محتوى نافذةٍ يُحمَّل بـ AJAX) عبر MutationObserver.
      • داخل نافذةٍ منبثقة (`.modal`) تُفتح القائمة داخل النافذة (`dropdownParent`)،
        وإلا اختفت خلفها ولم يُكتب فيها البحث.
      • العنصر الأوّل الفارغ (`value=""`) يصير نصَّ الإرشاد (placeholder)، ويُتاح زرُّ
        المسح «×» إن لم يكن الحقل إجبارياً.
      • البحثُ يظهر حين تزيد الخيارات على ٧ — القائمة القصيرة لا تحتاجه.

    خيارات لكل حقل:
      data-placeholder="…"   نصُّ الإرشاد
      data-s2-search="always|never"
      data-s2-native         يُطلق حدثَ `change` أصلياً أيضاً (لمستمعي addEventListener):
                             حدثُ Select2 لا يصل إليهم، وجعلُه للكلّ يُكرّر مستمعي jQuery.
      data-s2-tags           يسمح بكتابة قيمةٍ جديدة

    لا يُوضع على select داخل `input-group` (يكسر ارتفاعَ المجموعة) ولا على
    `stk-perpage__select`.

    الحاوية المولَّدة تُنزَع عنها الكلمةُ المجرّدة `select2` حتى لا يلتقطها
    مسحُ `form-select2.js` العامّ فيُهيّئ Select2 على الـ span نفسه (قائمةٌ فارغة).
--}}
<style id="hr-s2-style">
    /* مقصورٌ على ما يليه select.hr-s2 — فلا يمسّ حقول Select2 القائمة في شاشات أخرى */
    select.hr-s2 + .select2-container { width: 100% !important; }
    select.hr-s2 + .select2-container .select2-selection--single,
    select.hr-s2 + .select2-container .select2-selection--multiple {
        min-height: calc(1.5em + .75rem + 2px);
        border: 1px solid var(--app-border, #d8d6de);
        border-radius: var(--app-radius, .357rem);
        background: var(--app-surface, #fff);
    }
    select.hr-s2 + .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: calc(1.5em + .75rem);
        color: var(--app-text, #6e6b7b);
        padding-inline: .75rem 2rem;
    }
    select.hr-s2 + .select2-container .select2-selection--single .select2-selection__arrow { height: calc(1.5em + .75rem); }
    select.hr-s2 + .select2-container--focus .select2-selection,
    select.hr-s2 + .select2-container--open .select2-selection {
        border-color: var(--app-primary, #7367f0);
        box-shadow: 0 0 0 .15rem color-mix(in srgb, var(--app-primary, #7367f0) 18%, transparent);
    }
    select.hr-s2.form-control-sm + .select2-container .select2-selection--single { min-height: calc(1.5em + .5rem + 2px); }
    select.hr-s2.form-control-sm + .select2-container .select2-selection__rendered { line-height: calc(1.5em + .5rem) !important; font-size: .875rem; }
    select.hr-s2.form-control-sm + .select2-container .select2-selection__arrow { height: calc(1.5em + .5rem) !important; }
    table select.hr-s2 + .select2-container { min-width: 160px; }
    .modal .select2-dropdown { z-index: 1090; }
</style>
<script>
(function () {
    'use strict';

    var SELECTOR = 'select.hr-s2';

    /*
        صفٌّ مُستنسَخٌ بـ cloneNode/‎.clone() من صفٍّ مهيّأ يحمل علاماتِ Select2 وحاويةً
        ميتةً لا تعمل. نُنظّفه ثم نُهيّئه من جديد بدل أن يبقى الحقل معطوباً.
    */
    function isStaleClone(el) {
        if (!el.classList.contains('select2-hidden-accessible') && !el.dataset.hrS2Ready) return false;
        var $ = window.jQuery;
        return !$(el).data('select2');
    }

    function cleanStale(el) {
        var next = el.nextElementSibling;
        if (next && next.classList.contains('select2-container')) next.remove();
        el.classList.remove('select2-hidden-accessible');
        el.removeAttribute('data-select2-id');
        el.removeAttribute('aria-hidden');
        el.removeAttribute('tabindex');
        Array.prototype.forEach.call(el.options, function (o) { o.removeAttribute('data-select2-id'); });
        delete el.dataset.hrS2Ready;
    }

    function initOne(el) {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2) return;
        if (isStaleClone(el)) cleanStale(el);
        if (el.classList.contains('select2-hidden-accessible') || el.dataset.hrS2Ready) return;
        if (el.closest('.input-group') || el.classList.contains('stk-perpage__select')) return;

        var $el = $(el);
        var first = el.options.length ? el.options[0] : null;
        var placeholder = el.getAttribute('data-placeholder')
            || (first && first.value === '' ? (first.textContent || '').trim() : '')
            || '— اختر —';
        var search = el.getAttribute('data-s2-search');
        var countable = Array.prototype.filter.call(el.options, function (o) { return o.value !== ''; }).length;
        var modal = el.closest('.modal');

        var opts = {
            dir: document.documentElement.getAttribute('dir') || 'rtl',
            width: '100%',
            placeholder: placeholder,
            allowClear: !el.required && !el.multiple && !!(first && first.value === ''),
            minimumResultsForSearch: search === 'always' ? 0 : (search === 'never' ? Infinity : (countable > 7 ? 0 : Infinity)),
            tags: el.hasAttribute('data-s2-tags'),
            language: {
                noResults: function () { return 'لا نتائج مطابقة'; },
                searching: function () { return 'جارٍ البحث…'; },
                removeAllItems: function () { return 'مسح الكل'; }
            }
        };
        if (modal) opts.dropdownParent = $(modal);

        el.dataset.hrS2Ready = '1';
        $el.select2(opts);
        $el.next('.select2-container').removeClass('select2');

        if (el.hasAttribute('data-s2-native')) {
            $el.on('select2:select select2:unselect select2:clear', function () {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
        // حقلٌ معطَّل يبقى معطَّلاً بصرياً — وتفعيله لاحقاً يُعكس على الحاوية
        if (el.disabled) $el.prop('disabled', true);
    }

    function initWithin(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches(SELECTOR)) initOne(root);
        root.querySelectorAll(SELECTOR).forEach(initOne);
    }

    function start() {
        initWithin(document);
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (n) { if (n.nodeType === 1) initWithin(n); });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    // بعد سكربتات الشاشة (التي قد تملأ الخيارات أو تبني الصفوف في DOMContentLoaded)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(start, 0); });
    } else {
        setTimeout(start, 0);
    }

    // عند فتح نافذةٍ منبثقة: ما بُني فيها قبل ظهورها يُعاد قياسُ عرضه
    document.addEventListener('shown.bs.modal', function (e) { initWithin(e.target); });

    window.HrSelect2 = { init: initWithin };
})();
</script>
