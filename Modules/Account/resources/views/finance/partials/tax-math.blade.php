{{--
    حسابُ ضريبة السند في الشاشة — موضعٌ واحد لسندَي الصرف والقبض.

    كان لكلّ شاشةٍ نسختُها من الحساب، وكلُّها تضرب في النسبة وحدها — أي
    تفترض أنّ كلّ ضريبةٍ «مضافة». فمن ضبط ضريبةً «شاملة» أُضيفت له فوق
    مبلغٍ يحملها أصلاً، فتضخّم الإجماليُّ بمقدارها مرّتين.

    وهذا المِلفُّ يقابل `App\Support\TaxCalculator` على الخادم: الحسابُ
    نفسُه بالطريقتين، فما تراه العينُ في الشاشة هو ما يُحفَظ.
--}}
<script>
    (function () {
        'use strict';

        /** يقبل `0.15` و`15` معاً ويعيد الكسر. */
        function fraction(rate) {
            var value = parseFloat(rate) || 0;

            if (value <= 0) {
                return 0;
            }

            // ما دون الواحد كسرٌ أصلاً؛ وما فوقه نسبةٌ مئوية
            return value < 1 ? value : value / 100;
        }

        /**
         * نسبةُ الخيار المختار وطريقتُه.
         *
         * قيمةُ الخيار **معرّفُ الضريبة** (وهو ما يُخزَّن في `tax1`/`tax2`)،
         * والنسبةُ في `data-rate` والطريقةُ في `data-type`. وقراءةُ القيمة
         * نسبةً تجعل معرّفاً قيمتُه 1 يُحسب نسبةَ 100%.
         */
        function optionOf(selectEl) {
            if (!selectEl) {
                return { rate: 0, inclusive: false };
            }

            var opt = selectEl.options[selectEl.selectedIndex];

            if (!opt) {
                return { rate: 0, inclusive: false };
            }

            return {
                rate: fraction(opt.getAttribute('data-rate')),
                inclusive: opt.getAttribute('data-type') === 'included'
            };
        }

        /**
         * تفصيلُ السند — الطريقة المصرية.
         *
         *   • «مضافة» (`excluded`): الضريبةُ فوق المبلغ، والإجماليُّ أكبر منه.
         *   • «شاملة» (`included`): المبلغُ يحملها، فتُستخرَج منه ويُردّ إلى
         *     أساسه، والإجماليُّ هو المبلغُ نفسُه.
         *
         * فإن اجتمعت الطريقتان قُسِم المبلغُ على (١ + مجموع نسب الشاملة) ثم
         * حُسبت كلُّ ضريبةٍ من الأساس. وحسابُ كلٍّ على حدة يُخطئ حين تكون
         * الضريبتان شاملتَين معاً.
         */
        function breakdown(amount, tax1El, tax2El) {
            amount = parseFloat(amount) || 0;

            var one = optionOf(tax1El);
            var two = optionOf(tax2El);

            var carried = (one.inclusive ? one.rate : 0) + (two.inclusive ? two.rate : 0);
            var base = carried > 0 ? amount / (1 + carried) : amount;

            var tax1 = Math.round(base * one.rate * 100) / 100;
            var tax2 = Math.round(base * two.rate * 100) / 100;

            base = Math.round(base * 100) / 100;

            return {
                base: base,
                tax1: tax1,
                tax2: tax2,
                tax: Math.round((tax1 + tax2) * 100) / 100,
                total: Math.round((base + tax1 + tax2) * 100) / 100,
                inclusive: one.inclusive || two.inclusive
            };
        }

        /**
         * كتابةُ التفصيل في حقول السند.
         *
         * `hintId` سطرٌ يقول الطريقةَ والوعاء — فالفرقُ يمسّ المبلغ، وحقُّه
         * أن يُقرأ لا أن يُستنتَج.
         */
        function apply(amount, tax1El, tax2El, hintId) {
            var lines = breakdown(amount, tax1El, tax2El);

            var t1 = document.getElementById('tax1_amount');
            var t2 = document.getElementById('tax2_amount');
            var total = document.getElementById('total_amount');

            if (t1) t1.value = lines.tax1.toFixed(2);
            if (t2) t2.value = lines.tax2.toFixed(2);
            if (total) total.value = lines.total.toFixed(2);

            var hint = hintId ? document.getElementById(hintId) : null;

            if (hint) {
                if (lines.tax > 0 && lines.inclusive) {
                    hint.textContent = 'ضريبة شاملة: المبلغ المُدخَل يحملها — '
                        + 'الوعاء ' + lines.base.toFixed(2)
                        + ' وضريبة ' + lines.tax.toFixed(2)
                        + '، والإجمالي ' + lines.total.toFixed(2) + '.';
                    hint.style.display = '';
                } else if (lines.tax > 0) {
                    hint.textContent = 'ضريبة مضافة: الوعاء ' + lines.base.toFixed(2)
                        + ' وضريبة ' + lines.tax.toFixed(2)
                        + '، والإجمالي ' + lines.total.toFixed(2) + '.';
                    hint.style.display = '';
                } else {
                    hint.style.display = 'none';
                }
            }

            return lines;
        }

        window.VoucherTax = {
            fraction: fraction,
            optionOf: optionOf,
            breakdown: breakdown,
            apply: apply
        };

        /*
            «إضافة ضرائب» تُلزم باختيار نوعها.

            حقولُ الضرائب ظاهرةٌ ⇒ أُضيفت ضريبة. فإن لم تُختر الأولى ولا
            الثانية مُنع الحفظ هنا، قبل نافذة التأكيد وقبل الإرسال. و`has_tax`
            يحمل الحالةَ نفسها إلى الخادم (`TaxableDocument::assertChosenWhenAdded`)
            فلا يمرّ السند وإن تُجووزت الشاشة.

            يُلتقط الإرسالُ في مرحلة الالتقاط على `document` — قبل مستمع كلّ
            شاشة — فيسري على الشاشات الأربع بلا تعديلٍ في أيٍّ منها.
        */
        function taxAdded(taxFields) {
            return window.getComputedStyle(taxFields).display !== 'none';
        }

        function chosen(selectEl) {
            return !!selectEl && selectEl.value !== '' && selectEl.value !== '0';
        }

        document.addEventListener('submit', function (event) {
            var form = event.target;
            var taxFields = form && form.querySelector ? form.querySelector('#tax-fields') : null;

            if (!taxFields) {
                return;
            }

            var added = taxAdded(taxFields);
            var flag = form.querySelector('input[name="has_tax"]');

            if (!flag) {
                flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = 'has_tax';
                form.appendChild(flag);
            }

            flag.value = added ? '1' : '0';

            var tax1 = form.querySelector('#tax1');

            if (!added || chosen(tax1) || chosen(form.querySelector('#tax2'))) {
                if (tax1) tax1.classList.remove('is-invalid');
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            var message = 'اختر نوع الضريبة — أضفت ضرائب للسند ولم تختر أيّاً منها، أو اضغط «إزالة الضرائب».';

            if (tax1) {
                tax1.classList.add('is-invalid');
                tax1.focus();
            }

            if (window.Swal) {
                window.Swal.fire({ icon: 'warning', title: 'نوع الضريبة مطلوب', text: message, confirmButtonText: 'حسناً' });
            } else {
                window.alert(message);
            }
        }, true);
    })();
</script>
