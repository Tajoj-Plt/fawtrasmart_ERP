{{--
    نموذج مودال يُرسَل بـAjax: `<form class="re-ajax-form" data-url="…" data-method="POST|PUT">` وفيه
    `<div class="re-ajax-errors d-none">`. أخطاء 422 تُعرض داخل المودال فلا يضيع ما كتبه المستخدم.
    عند النجاح: `redirect` من الاستجابة، وإلا `loadData` إن وُجدت قائمة، وإلا إعادة تحميل.
--}}
<script>
    (function ($) {
        $(document).on('submit', '.re-ajax-form', function (e) {
            e.preventDefault();

            const $form = $(this);
            const $errors = $form.find('.re-ajax-errors').addClass('d-none').empty();
            const $submit = $form.find('[type=submit]').prop('disabled', true);
            const method = ($form.data('method') || 'POST').toUpperCase();
            let data = $form.serialize() + '&_token=' + encodeURIComponent(@json(csrf_token()));

            if (method !== 'POST') data += '&_method=' + method;

            $.ajax({ url: $form.data('url'), method: 'POST', data: data, headers: { Accept: 'application/json' } })
                .done(function (response) {
                    const $modal = $form.closest('.modal');
                    if ($modal.length) reModal($modal, false);

                    Swal.fire({ icon: 'success', title: response.message || 'تم', timer: 1300, showConfirmButton: false }).then(function () {
                        if (response.redirect) return window.location.assign(response.redirect);
                        if ($form.data('list') && typeof window.loadData === 'function') return loadData(1, false);
                        window.location.reload();
                    });
                })
                .fail(function (xhr) {
                    const body = xhr.responseJSON || {};
                    const list = body.errors ? Object.values(body.errors).flat() : [body.message || 'حدث خطأ غير متوقّع'];
                    $errors.removeClass('d-none').html('<ul class="mb-0 ps-3 pe-3">' + list.map(m => '<li>' + $('<i>').text(m).html() + '</li>').join('') + '</ul>');
                })
                .always(() => $submit.prop('disabled', false));
        });
    })(jQuery);
</script>
