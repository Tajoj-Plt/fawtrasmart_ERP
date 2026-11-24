{{--
    معالج الخطوات — كلّ الخطوات في نموذجٍ واحد يُرسَل مرّةً واحدة؛ الخطوات
    عرضٌ لا طلبات. الترميز:

      <div class="re-steps"> <button type="button" class="re-steps__item" data-step="0">…</button> … </div>
      <div class="re-step" data-step="0">…</div> …
      <div class="re-step-nav"> <button data-step-prev> <button data-step-next> </div>

    «التالي» (والقفز إلى خطوةٍ لاحقة من رأسها) يتحقّق من الخطوة الحالية أولاً:
    كلّ حقلٍ ظاهر يحمل `data-required` أو `required` يجب أن يُملأ، وإلا عُلِّم
    الحقل وكُتب تحته السبب ووقف التنقّل عنده. الرجوع إلى الخلف حرّ دائماً.

    للنموذج أن يضيف قواعده: يستمع لـ`re:validate-step` على `document`
    (الوسائط: رقم الخطوة، لوحتها، ودالة `fail(field, message)`) ويستدعي `fail`
    لكلّ حقلٍ لا يصحّ. من يريد تجاوز الفحص كلّياً يضع `data-step-free` على
    حاوية `.re-steps`.

    عند خطأ تحقّقٍ (من الخادم أو من `data-re-validate`) يُفتح أوّل خطوةٍ فيها
    حقلٌ معلَّم ويُلوَّن رأسها — فلا يضيع الخطأ في خطوةٍ مخفيّة.
--}}
<script>
    (function ($) {
        $(function () {
            const $heads = $('.re-steps__item');
            const $panes = $('.re-step');
            const free = $('.re-steps').is('[data-step-free]');
            let current = 0;

            function show(index) {
                current = Math.max(0, Math.min($panes.length - 1, index));
                $heads.removeClass('is-active').filter('[data-step="' + current + '"]').addClass('is-active');
                $panes.removeClass('is-active').filter('[data-step="' + current + '"]').addClass('is-active');
                $('[data-step-prev]').prop('disabled', current === 0);
                $('[data-step-next]').toggleClass('d-none', current === $panes.length - 1);
                $('[data-step-last]').toggleClass('d-none', current !== $panes.length - 1);
                $(document).trigger('re:step', [current]);
            }

            // ما يُلوَّن عند الخطأ: الحقل نفسه، وواجهة select2 إن كان الحقل مخفيّاً خلفها
            function $face($field) {
                const $select2 = $field.next('.select2-container');
                return $select2.length ? $field.add($select2.find('.select2-selection')) : $field;
            }

            function clearMark($field) {
                $face($field).removeClass('is-invalid').css('border-color', '');
                $field.closest('.form-group, [class*="col-"]').find('.re-step-error').remove();
            }

            function mark($field, message) {
                clearMark($field);
                $face($field).addClass('is-invalid');
                $field.next('.select2-container').find('.select2-selection').css('border-color', '#ea5455');

                const $holder = $field.closest('.form-group');
                const $note = $('<small class="re-step-error text-danger d-block"></small>').text(message);
                $holder.length ? $holder.append($note) : ($field.next('.select2-container').length ? $field.next('.select2-container') : $field).after($note);
            }

            function isEmpty($field) {
                if ($field.is(':checkbox, :radio')) {
                    const name = $field.attr('name');
                    return !(name ? $field.closest('form, body').find('[name="' + name + '"]:checked').length : $field.is(':checked'));
                }

                const value = $field.val();
                return value === null || value === undefined || ($.isArray(value) ? !value.length : !String(value).trim());
            }

            // حقلٌ مخفيّ (قسمٌ مطويّ أو حقلٌ لا يخصّ هذا النوع) لا يُطالَب به؛ و<select> خلف select2 يُعدّ ظاهراً بواجهته
            function isShown($field) {
                if ($field.is(':disabled') || $field.is('[type=hidden]')) return false;
                const $select2 = $field.next('.select2-container');
                return $select2.length ? $select2.is(':visible') : $field.is(':visible');
            }

            /** يتحقّق من خطوةٍ ويعيد أوّل حقلٍ ناقص فيها، أو null إن صحّت. */
            function validate(step) {
                const $pane = $panes.filter('[data-step="' + step + '"]');
                let $first = null;

                const fail = function (field, message) {
                    const $field = $(field).first();
                    if (!$field.length) return;
                    mark($field, message || 'هذا الحقل مطلوب');
                    if (!$first) $first = $field;
                };

                $pane.find('.re-step-error').remove();

                $pane.find('[data-required], select[required], input[required], textarea[required]').each(function () {
                    const $field = $(this);
                    if ($field.attr('data-required') === '0' || !isShown($field)) return;
                    isEmpty($field) ? fail($field, 'هذا الحقل مطلوب') : clearMark($field);
                });

                $(document).trigger('re:validate-step', [step, $pane, fail]);

                $heads.filter('[data-step="' + step + '"]').toggleClass('is-invalid', !!$first);

                return $first;
            }

            function focus($field) {
                const $select2 = $field.next('.select2-container');

                if ($select2.length) {
                    $select2[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
                    try { $field.select2('open'); } catch (ignore) {}
                } else {
                    $field[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
                    $field.trigger('focus');
                }
            }

            /** التقدّم إلى `target`: كلّ خطوةٍ بينهما تُفحص، ويقف عند أوّل ناقصة. */
            function advance(target) {
                if (free) return show(target);

                for (let step = current; step < target; step++) {
                    const $bad = validate(step);

                    if ($bad) {
                        if (step !== current) show(step);
                        return focus($bad);
                    }
                }

                show(target);
            }

            function flagInvalid() {
                $heads.removeClass('is-invalid');
                let first = null;

                $panes.each(function () {
                    if ($(this).find('.is-invalid').length) {
                        const step = $(this).data('step');
                        $heads.filter('[data-step="' + step + '"]').addClass('is-invalid');
                        if (first === null) first = step;
                    }
                });

                if (first !== null) show(first);
            }

            $heads.on('click', function () {
                const target = parseInt($(this).data('step'), 10);
                target > current ? advance(target) : show(target);
            });
            $('[data-step-next]').on('click', () => advance(current + 1));
            $('[data-step-prev]').on('click', () => show(current - 1));
            $(document).on('re:invalid', flagInvalid);

            // الخطأ يزول حين يُصحَّح حقله
            $panes.on('input change', 'input, select, textarea', function () {
                if ($(this).hasClass('is-invalid') || $(this).closest('.form-group').find('.re-step-error').length) clearMark($(this));
            });

            show(0);
            flagInvalid();
        });
    })(jQuery);
</script>
