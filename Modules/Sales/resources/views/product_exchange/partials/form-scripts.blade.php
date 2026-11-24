{{--
    سكربتُ نموذج التبديل — للإنشاء والتعديل معاً.

    الوسائط:
      itemTemplate  ترميزُ صفٍّ فارغ، فهرسُه `__I__`
      ajax          `true` في الإنشاء: `store()` يردّ JSON لا تحويلاً
--}}
<script>
    (function () {
        'use strict';

        var form = document.getElementById('exchangeForm');
        var body = document.getElementById('itemsBody');
        var empty = document.getElementById('itemsEmpty');

        // قالبُ الصفّ من الخادم — نفسُ القالب الذي طُبعت به الصفوف المحفوظة،
        // فلا نسختان تتباعدان ولا نصوصٌ خارج ملفّ اللغة
        var tpl = @json($itemTemplate ?? '');
        var useAjax = @json((bool) ($ajax ?? false));

        var text = {
            sameStore: @json(__('sales_exchanges.messages.same_storehouse')),
            oneItem: @json(__('sales_exchanges.items.empty')),
            saving: @json(__('sales_exchanges.form.saving')),
            failed: @json(__('sales_exchanges.messages.save_failed')),
            notice: @json(__('sales_exchanges.form.notice')),
            done: @json(__('sales_exchanges.form.done')),
            imageType: @json(__('sales_exchanges.form.image_type')),
            imageSize: @json(__('sales_exchanges.form.image_size')),
        };

        var hasSelect2 = window.jQuery && jQuery.fn && jQuery.fn.select2;

        /* تهيئةُ select2 على حقلٍ واحد، ثم نزعُ صنف `select2` عن حاويته:
           `form-select2.js` يمسح `.select2` في الصفحة كلِّها، وحاويةُ
           select2 نفسُها تحمل ذلك الصنف — فيُهيَّأ عليها مرّةً ثانية وتخرج
           قائمةٌ فارغة فوق حقلٍ فيه خياراتُه. */
        function enhance(select) {
            if (!hasSelect2 || jQuery(select).data('select2')) return;

            jQuery(select).select2({
                width: '100%',
                dir: document.documentElement.dir || 'rtl',
                placeholder: select.getAttribute('data-placeholder') || '',
                allowClear: false,
            });

            jQuery(select).next('.select2-container').removeClass('select2');
        }

        function enhanceRow(row) {
            row.querySelectorAll('select[name$="[product_id]"]').forEach(enhance);
        }

        function notify(message, icon) {
            if (window.Swal) {
                Swal.fire({ title: text.notice, text: message, icon: icon || 'warning' });
            } else {
                alert(message);
            }
        }

        function refresh() {
            var rows = body.querySelectorAll('[data-row]');
            empty.classList.toggle('d-none', rows.length > 0);

            // إعادةُ ترقيم الأسماء بعد الحذف: ثغرةٌ في الفهارس تجعل لارافيل
            // يقرأ `items` خريطةً لا قائمة
            rows.forEach(function (row, i) {
                row.querySelectorAll('[name^="items"]').forEach(function (input) {
                    input.name = input.name.replace(/items\[[^\]]*\]/, 'items[' + i + ']');
                });
            });
        }

        document.querySelectorAll('.px-select2').forEach(enhance);
        body.querySelectorAll('[data-row]').forEach(enhanceRow);

        document.getElementById('addItem').addEventListener('click', function () {
            var wrap = document.createElement('div');
            wrap.innerHTML = tpl.replace(/__I__/g, body.querySelectorAll('[data-row]').length);

            var row = wrap.firstElementChild;
            body.appendChild(row);
            enhanceRow(row);
            refresh();
        });

        body.addEventListener('click', function (e) {
            var btn = e.target.closest('.sl-item__remove');
            if (!btn) return;

            btn.closest('[data-row]').remove();
            refresh();
        });

        // ── المستودعان لا يصحّ أن يتّحدا — يُقال ذلك عند الاختيار لا بعد
        //    رحلةٍ إلى الخادم
        var out = document.getElementById('storehouse_id');
        var back = document.getElementById('return_storehouse_id');
        var storeError = document.getElementById('storehouseError');

        function storesDiffer() {
            var same = !!out.value && out.value === back.value;
            storeError.classList.toggle('d-none', !same);
            back.setCustomValidity(same ? text.sameStore : '');

            return !same;
        }

        // `change` من select2 حدثُ jQuery لا يبلغ `addEventListener`
        if (window.jQuery) {
            jQuery(out).add(back).on('change', storesDiffer);
        } else {
            out.addEventListener('change', storesDiffer);
            back.addEventListener('change', storesDiffer);
        }
        storesDiffer();

        // ── معاينةُ الصورة قبل الرفع
        var image = document.getElementById('image');
        var preview = document.getElementById('imagePreview');

        image.addEventListener('change', function () {
            var file = image.files && image.files[0];
            if (!file) return;

            if (!/^image\//.test(file.type)) {
                image.value = '';
                return notify(text.imageType);
            }

            // السقفُ ٥ ميجابايت — كما يتحقّق الخادم (`max:5120`)
            if (file.size > 5 * 1024 * 1024) {
                image.value = '';
                return notify(text.imageSize);
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });

        // ── الإرسال
        form.addEventListener('submit', function (e) {
            if (!body.querySelectorAll('[data-row]').length) {
                e.preventDefault();
                return notify(text.oneItem);
            }

            if (!storesDiffer()) {
                e.preventDefault();
                return notify(text.sameStore);
            }

            if (!useAjax) return;

            e.preventDefault();

            var buttons = document.querySelectorAll('button[type="submit"][form="exchangeForm"], #exchangeForm button[type="submit"]');
            var labels = [];
            buttons.forEach(function (b) {
                labels.push(b.innerHTML);
                b.disabled = true;
                b.innerHTML = '<i class="feather icon-loader"></i> ' + text.saving;
            });

            function restore() {
                buttons.forEach(function (b, i) {
                    b.disabled = false;
                    b.innerHTML = labels[i];
                });
            }

            jQuery.ajax({
                url: form.getAttribute('action'),
                type: 'POST',
                data: new FormData(form),
                processData: false,
                contentType: false,
                headers: { 'Accept': 'application/json' },
                success: function (response) {
                    if (response && response.success) {
                        if (window.Swal) {
                            Swal.fire({ title: text.done, text: response.message, icon: 'success', timer: 1800, timerProgressBar: true })
                                .then(function () { window.location.href = response.redirect; });
                        } else {
                            window.location.href = response.redirect;
                        }

                        return;
                    }

                    restore();
                    notify((response && response.message) || text.failed, 'error');
                },
                error: function (xhr) {
                    restore();

                    var payload = xhr.responseJSON || {};
                    var message = payload.message || text.failed;

                    // أخطاءُ التحقّق تُسرَد كلُّها، لا أوّلُها وحده
                    if (payload.errors) {
                        message = Object.keys(payload.errors).map(function (key) {
                            return payload.errors[key][0];
                        }).join('\n');
                    }

                    notify(message, xhr.status === 422 ? 'warning' : 'error');
                },
            });
        });

        refresh();
    })();
</script>
