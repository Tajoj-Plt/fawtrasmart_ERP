/**
 * شاشات عرض المستند في دورة المشتريات — حوارات التأكيد لأزرار الحالة والحذف.
 *
 * الأزرار والنماذج تُعرَف بمعرّفاتها، ومن لا يوجد منها في الصفحة يُتجاوَز
 * بلا خطأ — فالشاشة الواحدة تعرض ما يناسب حالة مستندها فقط.
 *
 * كل النصوص تُحقن من الخادم في `window.purchaseShowConfig`، فتتبع الحوارات
 * لغةَ الواجهة بلا نصّ مكتوب هنا.
 */
(function () {
    'use strict';

    const i18n = window.purchaseShowConfig;

    if (!i18n) {
        return;
    }

    const hasSwal = typeof window.Swal !== 'undefined';

    function wire(buttonId, formId, title, text, icon, confirmText, withNote) {
        const trigger = document.getElementById(buttonId);
        const form = document.getElementById(formId);

        if (!trigger || !form) {
            return;
        }

        trigger.addEventListener('click', function () {
            const submit = function (note) {
                if (withNote) {
                    const field = form.querySelector('input[name="note"]');
                    if (field) {
                        field.value = note || '';
                    }
                }
                form.submit();
            };

            if (!hasSwal) {
                if (window.confirm(text)) {
                    submit('');
                }
                return;
            }

            window.Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: i18n.no,
                input: withNote ? 'textarea' : undefined,
                inputPlaceholder: withNote ? i18n.approveNote : undefined,
            }).then(function (result) {
                if (result.isConfirmed) {
                    submit(result.value);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        wire('approveBtn', 'approveForm', i18n.approveTitle, i18n.approveText, 'question', i18n.yes, true);
        wire('rejectBtn', 'rejectForm', i18n.rejectTitle, i18n.rejectText, 'warning', i18n.yes, true);
        wire('cancelApprovalBtn', 'cancelApprovalForm', i18n.cancelApprovalTitle, i18n.cancelApprovalText, 'warning', i18n.yes, false);
        wire('undoRejectionBtn', 'undoRejectionForm', i18n.undoRejectionTitle, i18n.undoRejectionText, 'question', i18n.yes, false);
        wire('convertBtn', 'convertForm', i18n.convertTitle, i18n.convertText, 'question', i18n.yes, false);
        wire('deleteBtn', 'deleteForm', i18n.deleteTitle, i18n.deleteText, 'error', i18n.yesDelete, false);
    });
})();
