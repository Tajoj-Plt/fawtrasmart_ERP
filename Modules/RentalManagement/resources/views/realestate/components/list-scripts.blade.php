{{--
    سلوك القوائم والإجراءات في شاشات العقارات — مرّةً واحدة لكلّ الشاشات.

      reList({ url, extra })   بحثٌ وترقيمٌ بـAjax على `#searchForm` ← `#results-container`.

      .re-action               زرّ إجراءٍ تصريحي:
          data-url      العنوان            data-method   POST|PUT|DELETE
          data-confirm  نصّ التأكيد         data-prompt   يطلب سبباً (نصّ التسمية) ويرسله `reason`
          data-payload  JSON يُرسَل مع الطلب  data-tone     danger|warning|primary
        استجابة 409 مع `confirm:true` تُعيد السؤال وترسل `force=1` إن وافق المستخدم.

    الأحداث مفوَّضةٌ إلى `document`: الحاوية تُستبدل كاملةً مع كل بحث، وقوائم
    BS5 المنسدلة تُنقل إلى `<body>`.
--}}
<script>
    (function ($) {
        const token = @json(csrf_token());

        window.reList = function (options) {
            const $form = $('#searchForm');
            const $box = $('#results-container');

            window.loadData = function (page, push) {
                $('#loading-indicator').removeClass('d-none');

                const perPage = $('#perPage').length ? $('#perPage').val() : 10;
                const extra = typeof options.extra === 'function' ? options.extra() : {};
                const query = $form.serialize() + '&page=' + (page || 1) + '&per_page=' + perPage
                    + Object.keys(extra).map(k => '&' + encodeURIComponent(k) + '=' + encodeURIComponent(extra[k])).join('');

                if (push !== false) {
                    const url = new URL(window.location.href);
                    url.search = '';
                    new URLSearchParams(query).forEach((value, key) => { if (value && key !== '_token') url.searchParams.set(key, value); });
                    window.history.replaceState({}, '', url);
                }

                $.ajax({ url: options.url, data: query, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .done(html => $box.html(html))
                    .fail(() => $box.html('<div class="alert alert-danger m-2">تعذّر تحميل البيانات</div>'))
                    .always(() => $('#loading-indicator').addClass('d-none'));
            };

            if ($.fn.select2) {
                $form.find('.select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            $box.on('click', '.pagination-link', function (e) {
                e.preventDefault();
                if (!$(this).closest('.page-item').is('.disabled, .active')) loadData(parseInt($(this).data('page'), 10));
            });

            $form.on('submit', e => { e.preventDefault(); loadData(1); });
            $form.on('change input', 'input, select', function () {
                clearTimeout(window.reSearchTimer);
                window.reSearchTimer = setTimeout(() => loadData(1), 450);
            });

            $('#resetSearch').on('click', function () {
                $form[0].reset();
                $form.find('input[type=text], input[type=date], input[type=number]').val('');
                $form.find('select').val('').trigger('change.select2');
                loadData(1);
            });
        };

        function send($btn, extra) {
            const payload = Object.assign({ _token: token }, $btn.data('payload') || {}, extra || {});
            const method = ($btn.data('method') || 'POST').toUpperCase();

            if (method !== 'POST') payload._method = method;

            $.ajax({ url: $btn.data('url'), method: 'POST', data: payload, headers: { Accept: 'application/json' } })
                .done(function (response) {
                    Swal.fire({ icon: 'success', title: response.message || 'تم', timer: 1300, showConfirmButton: false })
                        .then(() => response.redirect ? window.location.assign(response.redirect)
                            : (typeof window.loadData === 'function' && $('#results-container').length && !$btn.data('reload') ? loadData(1, false) : window.location.reload()));
                })
                .fail(function (xhr) {
                    const body = xhr.responseJSON || {};

                    if (xhr.status === 409 && body.confirm) {
                        return Swal.fire({ icon: 'warning', title: 'تنبيه', text: body.message, showCancelButton: true,
                            confirmButtonText: 'متابعة رغم ذلك', cancelButtonText: 'تراجع' })
                            .then(r => r.isConfirmed && send($btn, Object.assign({}, extra, { force: 1 })));
                    }

                    const errors = body.errors ? Object.values(body.errors).flat().join('\n') : null;
                    Swal.fire({ icon: 'error', title: 'تعذّر التنفيذ', text: errors || body.message || 'حدث خطأ غير متوقّع' });
                });
        }

        $(document).on('click', '.re-action', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const tone = $btn.data('tone') || 'primary';
            const colors = { danger: '#ea5455', warning: '#ff9f43', primary: '#7367f0' };

            if ($btn.data('prompt')) {
                return Swal.fire({ title: $btn.data('confirm') || 'تأكيد', input: 'textarea', inputLabel: $btn.data('prompt'),
                    inputValidator: v => (!v || !v.trim()) ? 'هذا الحقل مطلوب' : undefined, showCancelButton: true,
                    confirmButtonText: 'تأكيد', cancelButtonText: 'تراجع', confirmButtonColor: colors[tone] })
                    .then(r => r.isConfirmed && send($btn, { reason: r.value }));
            }

            if ($btn.data('confirm')) {
                return Swal.fire({ icon: tone === 'danger' ? 'warning' : 'question', title: $btn.data('confirm'), showCancelButton: true,
                    confirmButtonText: 'نعم', cancelButtonText: 'تراجع', confirmButtonColor: colors[tone] })
                    .then(r => r.isConfirmed && send($btn));
            }

            send($btn);
        });

        // التحقّق قبل الإرسال: select2 يُخفي الـ<select> الأصلي، و`required` على حقلٍ مخفي
        // يجعل المتصفّح يُلغي الإرسال بصمت — فالنماذج `novalidate` والفحص هنا
        $(document).on('submit', 'form[data-re-validate]', function (e) {
            const missing = [];

            $(this).find('[data-required="1"]').each(function () {
                const empty = !$(this).val() || ($.isArray($(this).val()) && !$(this).val().length);
                $(this).toggleClass('is-invalid', empty);
                if (empty) missing.push($('label[for="' + this.id + '"]').first().text().trim() || this.name);
            });

            if (missing.length) {
                e.preventDefault();
                e.stopImmediatePropagation();
                Swal.fire({ icon: 'warning', title: 'حقول مطلوبة', text: missing.join('، ') });
                $(document).trigger('re:invalid', [this]);
            }
        });

        $(function () {
            if ($.fn.select2) {
                $('form[data-re-validate] .select2, .modal .select2').each(function () {
                    $(this).select2({ dir: 'rtl', width: '100%', placeholder: $(this).find('option[value=""]').text() || 'اختر…', allowClear: true,
                        dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $(document.body) });
                });
            }
        });
    })(jQuery);
</script>
