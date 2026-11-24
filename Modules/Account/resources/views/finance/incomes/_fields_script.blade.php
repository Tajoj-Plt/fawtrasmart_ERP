{{--
    سلوك حقول سند القبض (_fields.blade.php) — منتزَعٌ من create.blade.php
    ليُستدعى من هناك ومن مودال مساحة عمل الزيارة معاً. لا يلمس تسليم
    النموذج نفسه (submit) — كل صفحةٍ تتولّاه بطريقتها.
--}}
@include('account::finance.partials.tax-math')
<script>
    (function () {
        'use strict';

        var amountInput = document.getElementById('amount_input');
        var tax1 = document.getElementById('tax1');
        var tax2 = document.getElementById('tax2');
        var taxFields = document.getElementById('tax-fields');
        var taxHint = document.getElementById('taxEmptyHint');

        if (!amountInput || !tax1 || !tax2 || !taxFields || !taxHint) { return; }

        function calculateTax() {
            window.VoucherTax.apply(amountInput.value, tax1, tax2, 'taxMethodHint');
        }

        document.getElementById('toggleTaxBtn').addEventListener('click', function () {
            taxFields.style.display = 'block';
            taxHint.style.display = 'none';
        });

        document.getElementById('removeTaxBtn').addEventListener('click', function () {
            taxFields.style.display = 'none';
            taxHint.style.display = '';
            tax1.value = '';
            tax2.value = '';
            document.getElementById('tax1_account_id').value = '';
            document.getElementById('tax2_account_id').value = '';
            calculateTax();
        });

        amountInput.addEventListener('input', calculateTax);
        tax1.addEventListener('change', calculateTax);
        tax2.addEventListener('change', calculateTax);

        amountInput.addEventListener('blur', function () {
            this.value = (parseFloat(this.value) || 0).toFixed(2);
            calculateTax();
        });

        document.getElementById('checkbox').addEventListener('change', function () {
            var display = this.checked ? 'block' : 'none';
            document.getElementById('duplicate-options-container').style.display = display;
            document.getElementById('end-date-container').style.display = display;
        });

        // معاينة المرفق المختار — يقابله عمودٌ خاصٌّ به الآن بدل حقلٍ يتيم
        // في صفّ إعدادات التكرار (راجع _fields.blade.php)
        var attachmentInput = document.getElementById('attachments');
        var attachmentPreview = document.getElementById('attachment-preview');

        if (attachmentInput && attachmentPreview) {
            attachmentInput.addEventListener('change', function () {
                if (!this.files || !this.files.length) {
                    attachmentPreview.innerHTML = '<small class="stk-help">لم يُختَر مرفق بعد.</small>';
                    return;
                }

                var file = this.files[0];
                var icon = 'file';
                if (file.type.indexOf('image') > -1) {
                    icon = 'image';
                } else if (file.type.indexOf('pdf') > -1) {
                    icon = 'file-text';
                }

                attachmentPreview.innerHTML =
                    '<div class="stk-media mb-1">' +
                    '<span class="stk-media__thumb"><i class="feather icon-' + icon + '"></i></span>' +
                    '<span class="stk-media__body">' +
                    '<span class="stk-media__title">' + file.name + '</span>' +
                    '<span class="stk-media__sub">' + (file.size / 1024 / 1024).toFixed(2) + ' م.ب</span>' +
                    '</span>' +
                    '</div>';
            });
        }

        if (window.jQuery && jQuery.fn.select2) {
            var accountSelect = jQuery('#account_id');

            if (accountSelect.hasClass('select2-hidden-accessible')) {
                accountSelect.select2('destroy');
            }

            accountSelect.select2({
                dir: 'rtl',
                width: '100%',
                placeholder: 'ابحث بالكود أو باسم الحساب أو باسم العميل',
                allowClear: true,
                matcher: function (params, data) {
                    if (jQuery.trim(params.term) === '') {
                        return data;
                    }

                    if (data.children && data.children.length) {
                        var matched = [];

                        for (var i = 0; i < data.children.length; i++) {
                            if (matchesTerm(params.term, data.children[i])) {
                                matched.push(data.children[i]);
                            }
                        }

                        if (! matched.length) {
                            return null;
                        }

                        var group = jQuery.extend({}, data);
                        group.children = matched;

                        return group;
                    }

                    return matchesTerm(params.term, data) ? data : null;
                }
            });
        }

        function matchesTerm(term, option) {
            var needle = jQuery.trim(term).toLowerCase();
            var element = option.element;
            var haystack = element ? (element.getAttribute('data-search') || '') : '';

            haystack = (haystack + ' ' + (option.text || '')).toLowerCase();

            return haystack.indexOf(needle) > -1;
        }
    })();
</script>
