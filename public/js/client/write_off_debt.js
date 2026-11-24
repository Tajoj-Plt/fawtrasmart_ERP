// معالجة نموذج تسوية الديون المعدومة
document.addEventListener('DOMContentLoaded', function() {
    const writeOffForm = document.getElementById('writeOffForm');
    
    if (!writeOffForm) return;
    
    writeOffForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // التحقق من التأكيد
        const confirmCheckbox = document.getElementById('confirmWriteOff');
        if (!confirmCheckbox || !confirmCheckbox.checked) {
            Swal.fire({
                icon: 'warning',
                title: 'تأكيد مطلوب',
                text: 'يجب عليك تأكيد أنك تفهم عواقب هذه العملية'
            });
            return false;
        }

        const reasonSelect = document.getElementById('reason');
        const reason = reasonSelect ? reasonSelect.value : '';
        if (!reason) {
            Swal.fire({
                icon: 'warning',
                title: 'خطأ',
                text: 'يرجى اختيار السبب'
            });
            return false;
        }

        // عرض تأكيد نهائي قبل المتابعة
        Swal.fire({
            icon: 'warning',
            title: 'تحذير نهائي',
            html: '<p style="color: red; font-weight: bold;">هل أنت متأكد تماماً من تسوية الديون المعدومة؟</p>' +
                  '<p>هذه العملية ستقوم بـ:</p>' +
                  '<ul style="text-align: right; display: inline-block;">' +
                  '<li>تصفير رصيد الحساب بالكامل</li>' +
                  '<li>إنشاء قيد يومية</li>' +
                  '<li>هذه العملية لا يمكن التراجع عنها</li>' +
                  '</ul>',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'نعم، أتابع',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                // إظهار رسالة التحميل
                Swal.fire({
                    title: 'جاري المعالجة...',
                    text: 'يرجى الانتظار',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // إرسال الفورم مباشرة (redirect)
                writeOffForm.submit();
            }
        });

        return false;
    });
});
