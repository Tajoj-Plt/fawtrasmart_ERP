{{--
    اشتقاق أسعار الوحدات المرتبطة من الوحدة الأساسية.

    **ما كان**

    تُعرَّف للصنف وحداتٌ بمعاملات تحويل — قطعة، وعلبة بمعامل ١٢، وكرتون
    بمعامل ١٤٤ — ثم تُطلب أسعارُ كلٍّ منها في المنتج على حدة. فمن يسعّر
    القطعة بريالين يكتب بيده ٢٤ للعلبة و٢٨٨ للكرتون، في أربعة حقولٍ لكل
    وحدة (شراء، بيع، أدنى بيع، جملة). صنفٌ بثلاث وحدات يعني اثني عشر
    حقلاً تُملأ بحسابٍ ذهني، وخطأٌ في واحدٍ منها لا يُكتشف إلّا في فاتورة.

    وكان في الشاشة اشتقاقٌ واحد فقط: حقول الوحدة الأساسية تنسخ قيمها إلى
    حقول التسعير العامّة أعلى الصفحة. أمّا بقيّة الوحدات فلا شيء.

    **ما صار**

    كتابة سعرٍ في الوحدة الأساسية تملأ نظيره في كل وحدةٍ مرتبطة:

        سعر الوحدة = سعر الأساسية × (معامل الوحدة ÷ معامل الأساسية)

    والقيمة تبقى **قابلة للتعديل**: أوّل تحريرٍ يدوي لحقلٍ مشتقّ يُثبّته،
    فلا تمسحه كتابةٌ لاحقة في الأساسية. وزرٌّ يُعيد اشتقاق ما ثُبِّت لمن
    أراد الرجوع.

    يُستدعى من `products/create` و`products/edit` معاً — المنطق واحدٌ
    والبطاقات بالترميز نفسه.
--}}

<script>
    (function () {
        'use strict';

        // الحقول التي تُشتقّ — الباركود ليس منها، فهو معرّفٌ لا قيمة
        var PRICE_FIELDS = [
            '.unit-purchase-price',
            '.unit-sale-price',
            '.unit-min-sale-price',
            '.unit-wholesale-price',
        ];

        var CONTAINER = '#product-units-container';

        /** معامل تحويل البطاقة — يُقرأ من `data-factor` ويُفترض ١ حين يغيب */
        function factorOf(card) {
            var value = parseFloat($(card).data('factor'));

            return (isNaN(value) || value <= 0) ? 1 : value;
        }

        function baseCard() {
            return $(CONTAINER).find('.stk-unit-card[data-is-base="1"]').first();
        }

        /**
         * اشتقاق حقلٍ واحد في كل الوحدات غير الأساسية.
         *
         * @param {string} selector صنف الحقل
         * @param {boolean} force   إعادة الاشتقاق ولو حُرِّر يدوياً
         */
        function derive(selector, force) {
            var base = baseCard();

            if (! base.length) {
                return;
            }

            var baseValue = parseFloat(base.find(selector).val());
            var baseFactor = factorOf(base);

            if (isNaN(baseValue)) {
                return;
            }

            $(CONTAINER).find('.stk-unit-card').each(function () {
                if ($(this).data('is-base') === 1 || $(this).attr('data-is-base') === '1') {
                    return;
                }

                var field = $(this).find(selector);

                if (! field.length) {
                    return;
                }

                // حقلٌ حرّره المستخدم لا يُكتب فوقه إلّا بطلبٍ صريح
                if (! force && field.attr('data-manual') === '1') {
                    return;
                }

                var derived = baseValue * (factorOf(this) / baseFactor);

                field.val(derived.toFixed(2));
                field.attr('data-derived', '1');

                if (force) {
                    field.removeAttr('data-manual');
                }
            });
        }

        function deriveAll(force) {
            PRICE_FIELDS.forEach(function (selector) {
                derive(selector, force);
            });
        }

        // كتابةٌ في الأساسية تُحدِّث المرتبطة
        PRICE_FIELDS.forEach(function (selector) {
            $(document).on('input', CONTAINER + ' ' + selector + '[data-is-base="1"]', function () {
                derive(selector, false);
            });
        });

        // تحريرُ حقلٍ مشتقّ يُثبّته
        PRICE_FIELDS.forEach(function (selector) {
            $(document).on('input', CONTAINER + ' ' + selector + ':not([data-is-base="1"])', function () {
                $(this).attr('data-manual', '1').removeAttr('data-derived');
            });
        });

        // إعادة الاشتقاق: تمسح التثبيت وتعيد الحساب من الأساسية
        $(document).on('click', '#rederive-unit-prices', function (event) {
            event.preventDefault();

            if (! baseCard().length) {
                Swal.fire('لا وحدة أساسية', 'عرّف وحدةً أساسية للصنف أولاً.', 'info');
                return;
            }

            deriveAll(true);
        });

        // بطاقاتٌ تُبنى بـAjax بعد اختيار الصنف: تُشتقّ فور بنائها
        window.deriveUnitPrices = deriveAll;
    })();
</script>
