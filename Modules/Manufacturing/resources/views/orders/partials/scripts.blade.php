{{--
    سكربتات صفحة عرض أمر التصنيع.

    ما أُصلح هنا:
    • `assets/js/notes-functions.js` غير موجود في المشروع — كان يردّ 404؛ حُذف
      (دوال الملاحظات صارت داخل `notes-modal`).
    • SweetAlert كان يُحمَّل من CDN رغم وجود نسخة محليّة في `app-assets`.
    • أزرار التأكيد صارت تستعمل `form="…"`، فـ`closest('form')` لم يعد يجدها.
    • التحقّق من نموذج الإنهاء كان يسأل عن `#main_warehouse` و`#waste_warehouse`
      و`#delivery_date` — لا وجود لأيٍّ منها في النافذة، فكان النموذج يفشل دائماً.
    • كان يحوّل كل `.alert-success`/`.alert-danger` في الصفحة إلى نافذة Swal
      ويُخفي الأصل، فتضيع رسائل التحقّق المفصَّلة.
--}}

<script src="{{ asset('app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>

<script>
(function () {
    window.manufacturingOrderId = @json($order->id ?? null);

    /* الأزرار خارج النماذج وتشير إليها بـ form="…" */
    function formOf(button) {
        const id = button.getAttribute('form');
        return id ? document.getElementById(id) : button.closest('form');
    }

    function confirmThen(button, options) {
        const form = formOf(button);
        if (! form) return;

        Swal.fire(Object.assign({
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText: 'تأكيد',
            cancelButtonText: 'إلغاء',
        }, options)).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    }

    $(function () {
        $('.confirm-undo').on('click', function (e) {
            e.preventDefault();

            confirmThen(this, {
                icon: 'warning',
                title: 'التراجع عن الإنهاء',
                text: 'سيعود الأمر إلى حالة «قيد التنفيذ».',
                confirmButtonText: 'نعم، تراجع',
            });
        });

        $('.confirm-close').on('click', function (e) {
            e.preventDefault();

            confirmThen(this, {
                icon: 'warning',
                title: 'إغلاق الأمر نهائياً',
                text: 'بعد الإغلاق لا يمكن تعديل الأمر ولا تغيير حالته.',
                confirmButtonText: 'نعم، أغلق',
            });
        });

        $('.confirm-reopen').on('click', function (e) {
            e.preventDefault();

            confirmThen(this, {
                icon: 'question',
                title: 'إعادة فتح الأمر',
                text: 'سيعود الأمر إلى حالة «مكتمل» ويمكن التعامل معه.',
                confirmButtonText: 'نعم، أعد الفتح',
            });
        });

        /* تأكيد الإنهاء — الحقول الفعليّة: المستودع والكمية */
        $('#finishOrderModal form').on('submit', function (e) {
            if ($(this).data('confirmed')) return;

            e.preventDefault();

            const form = this;
            const quantity = parseFloat($('#actual_quantity_finish').val());

            if (! $('#storehouse_finish').val() || ! (quantity > 0)) {
                Swal.fire({
                    icon: 'error',
                    title: 'بيانات ناقصة',
                    text: 'اختر مستودع الاستلام وأدخل كميّة فعليّة أكبر من صفر.',
                });
                return;
            }

            /*
                لا نافذةَ تأكيدٍ ثانية فوق النافذة المفتوحة.

                كان الإرسالُ يفتح Swal فوق نافذة Bootstrap المفتوحة، ونافذةُ
                Bootstrap تحبس التركيزَ داخلها — فلا يُضغط زرُّ التأكيد في
                Swal ولا يُرسَل شيء: «زرُّ الإنهاء لا يعمل». والنافذةُ نفسُها
                هي التأكيد: فيها المستودعُ والكميةُ وزرٌّ صريح.
            */
            $(form).data('confirmed', true);
            $(form).find('button[type="submit"]').prop('disabled', true)
                .html('<i class="feather icon-loader"></i> جارٍ الإنهاء…');
            form.submit();
        });

        if ($.fn.tooltip) $('[data-toggle="tooltip"]').tooltip();
    });
})();
</script>
