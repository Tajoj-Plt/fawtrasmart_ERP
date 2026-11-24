/**
 * تصفية شاشات مستندات المبيعات — سلوكٌ واحد لخمس شاشات.
 *
 * الفواتير والمرتجع والإشعارات الدائنة وعروض الأسعار (وأوامر البيع)
 * ومدفوعات العملاء كانت تحمل كلٌّ منها نسخةً من `loadData()` — والنسخُ
 * الخمس تشترك في العيوب نفسها:
 *
 *   • «مسح الفلاتر» يستدعي `form.reset()`، فيعيد الحقول إلى قيم **تحميل
 *     الصفحة** لا إلى الفراغ: من فتح الشاشة من رابطٍ فيه فلترٌ لا يمسحه.
 *   • كلُّ ضغطة حرف ومعها `change` تُطلق طلباً، والطلبات لا تُلغى — فردٌّ
 *     قديمٌ بطيء يكتب فوق ردٍّ أحدث منه.
 *   • عدد السجلّات يُقرأ من مُنتقٍ لا يُرسَم إلّا مع النتائج، فبحثٌ فارغ
 *     يُعيده إلى عشرة.
 *   • `pushState` بلا `popstate` — زرُّ الرجوع يغيّر العنوان ولا الجدول.
 *   • `_token` يذهب في عنوان GET.
 *   • زرُّ «بحث متقدّم» يحمل `data-toggle` و`data-bs-toggle` معاً، والقالب
 *     يحمّل Bootstrap 4 و5 — فيفتح أحدُهما اللوحة ويغلقها الآخر.
 *
 * الاستعمال:
 *
 *   SalesIndexFilters.bind({
 *       url: '…/invoices/index',     // مسار الشاشة (والمسح يعود إليه)
 *       onData: function (response) { … تحديث البطاقات … },
 *   });
 *
 * ويبقى `window.loadData(page)` كما هو: قوالبُ الجداول والترقيم تناديه
 * باسمه (`onchange="loadData(1)"`).
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    /* زرُّ الطيّ — مستقلٌّ عن Bootstrap، فلا تتنازع نسختاه على النقرة */
    $(document).on('click', '[data-sl-toggle]', function (event) {
        event.preventDefault();

        var $target = $($(this).attr('data-sl-toggle'));
        var open = !$target.hasClass('show');

        $target.toggleClass('show', open);
        $(this).attr('aria-expanded', open ? 'true' : 'false');
    });

    function bind(options) {
        var $form = $(options.form || '#searchForm');
        var $container = $(options.container || '#results-container');
        var url = options.url || window.location.pathname;
        var failText = options.failText || '';
        var xhr = null;
        var timer = null;

        if (!$form.length || $form.data('slFiltersBound')) {
            return;
        }

        $form.data('slFiltersBound', true);

        /**
         * معاملات النموذج — بلا `_token`، وبالقوائم الفارغة.
         *
         * القائمةُ الفارغة تُرسَل عمداً: «كل العملات» قيمتُها فراغ، وحذفُها من
         * العنوان يُعيد عملةَ النظام عند إعادة التحميل.
         */
        function params() {
            var result = new URLSearchParams();

            $form.find(':input[name]').each(function () {
                var el = this;

                if (el.name === '_token' || el.disabled || el.type === 'button' || el.type === 'submit') {
                    return;
                }

                if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
                    return;
                }

                var value = $(el).val();

                if (Array.isArray(value)) {
                    value.forEach(function (v) { result.append(el.name, v); });
                } else if (value !== null && (value !== '' || el.tagName === 'SELECT')) {
                    result.append(el.name, value);
                }
            });

            return result;
        }

        function perPage() {
            var $select = $('#perPage');

            if ($select.length && $select.val()) {
                return $select.val();
            }

            return new URLSearchParams(window.location.search).get('per_page') || '';
        }

        window.loadData = function (page, pushState) {
            clearTimeout(timer);

            var query = params();
            var size = perPage();

            query.set('page', String(parseInt(page, 10) || 1));

            if (size) {
                query.set('per_page', size);
            }

            if (pushState !== false && window.history && window.history.pushState) {
                var target = new URL(window.location.href);
                target.search = query.toString();
                window.history.pushState({ slIndex: true }, '', target);
            }

            if (xhr && xhr.readyState !== 4) {
                xhr.abort();
            }

            $('#loading-indicator').removeClass('d-none');
            $container.css('opacity', '.55');

            xhr = $.ajax({
                url: url,
                method: 'GET',
                data: query.toString(),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: false,
            })
                .done(function (response) {
                    if (!response || !response.success) {
                        return;
                    }

                    $container.html(response.data);
                    $('.sl-table-head__title .sl-count').text(response.total);

                    if (typeof options.onData === 'function') {
                        options.onData(response);
                    }
                })
                .fail(function (request, status) {
                    if (status === 'abort') {
                        return;
                    }

                    $container.html('<div class="alert alert-danger m-2">' + failText + '</div>');
                })
                .always(function (a, status) {
                    if (status === 'abort') {
                        return;
                    }

                    $('#loading-indicator').addClass('d-none');
                    $container.css('opacity', '1');
                });
        };

        function schedule(delay) {
            clearTimeout(timer);
            timer = setTimeout(function () { window.loadData(1); }, delay);
        }

        $form.on('submit', function (event) {
            event.preventDefault();
            window.loadData(1);
        });

        // الكتابة تنتظر توقّف اليد، والقوائم والتواريخ تبحث عند التغيير
        $form.on('input', 'input[type="text"], input[type="search"], input[type="number"], input:not([type])', function () {
            schedule(500);
        });

        $form.on('change', 'select, input[type="date"]', function () {
            schedule(150);
        });

        // المسح يفتح الشاشة نظيفةً — لا `form.reset()` الذي يُعيد قيم الرابط
        $(options.reset || '#resetSearch').on('click', function (event) {
            event.preventDefault();
            window.location.href = url;
        });

        $container.on('click', '.pagination-link, .sl-page-link', function (event) {
            event.preventDefault();
            window.loadData($(this).data('page'));
        });

        // الرجوع والتقدّم: الصفحةُ تُبنى من عنوانها كما حُفظ
        window.addEventListener('popstate', function (event) {
            if (event.state && event.state.slIndex) {
                window.location.reload();
            }
        });
    }

    window.SalesIndexFilters = { bind: bind };
})(window, window.jQuery);
