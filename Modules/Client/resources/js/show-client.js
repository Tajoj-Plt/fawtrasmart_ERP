// سكريبتات عرض العميل

$(document).ready(function() {
    // تفعيل Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    // إصلاح مشكلة Dropdown Menu
    document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
        menu.style.position = 'absolute';
        menu.style.zIndex = '10000';
    });

    // تفعيل جميع Dropdowns في الصفحة
    const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    const dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });

    // معالج لإظهار الـ dropdown بشكل صحيح
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const menu = this.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                menu.classList.toggle('show');

                // إخفاء الـ dropdowns الأخرى
                document.querySelectorAll('.dropdown-menu.show').forEach(function(otherMenu) {
                    if (otherMenu !== menu) {
                        otherMenu.classList.remove('show');
                    }
                });
            }
        });
    });

    // إخفاء الـ dropdown عند النقر خارجه
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
            });
        }
    });

    // تفعيل Fancybox للصور
    Fancybox.bind("[data-fancybox]", {
        Toolbar: {
            display: {
                left: ["infobar"],
                middle: [],
                right: ["slideshow", "zoom", "fullscreen", "download", "close"],
            },
        },
        Images: {
            zoom: true,
        },
    });

    // فلترة المواعيد
    $('.filter-appointments').click(function() {
        const filter = $(this).data('filter');
        $('.filter-appointments').removeClass('active');
        $(this).addClass('active');

        $('#appointments tbody tr').each(function() {
            const rowStatus = $(this).data('status');
            if (filter === 'all' || rowStatus == filter) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // تفعيل Select2
    if ($.fn.select2) {
        $('.select2').select2({
            placeholder: 'اختر الموظفين',
            allowClear: true,
            dropdownParent: $('#assignEmployeeModal')
        });
    }

    // معالجة نموذج الرصيد الافتتاحي
    $('#openingBalanceForm').on('submit', function(e) {
        e.preventDefault();

        // إظهار رسالة الحفظ
        Swal.fire({
            title: 'جاري الحفظ...',
            text: 'يرجى الانتظار',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // إرسال الفورم مباشرة (redirect)
        this.submit();
    });

    // طباعة الملاحظة
    $('.print-note').on('click', function() {
        const noteCard = $(this).closest('.note-card-item');
        printNote(noteCard);
    });
});

// وظيفة طباعة الملاحظة
function printNote(noteElement) {
    const printContent = noteElement.clone();

    // إزالة الأزرار
    printContent.find('.note-actions').remove();
    printContent.find('.attachment-overlay').remove();

    // إنشاء نافذة طباعة
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>طباعة الملاحظة</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    background: white;
                }
                .print-header {
                    text-align: center;
                    border-bottom: 3px solid #5B51D8;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .print-header h2 {
                    color: #5B51D8;
                    font-weight: bold;
                    margin: 0;
                }
                .print-date {
                    color: #666;
                    font-size: 14px;
                    margin-top: 10px;
                }
                .note-card-item {
                    border: 2px solid #5B51D8;
                    border-radius: 10px;
                    padding: 20px;
                }
                .attachment-img {
                    max-width: 200px;
                    height: auto;
                    margin: 5px;
                }
                @media print {
                    .no-print {
                        display: none !important;
                    }
                    body {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h2><i class="fa fa-sticky-note me-2"></i>ملاحظة العميل</h2>
                <div class="print-date">
                    <i class="fa fa-calendar me-1"></i>
                    تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')}
                    <i class="fa fa-clock me-1 ms-3"></i>
                    ${new Date().toLocaleTimeString('ar-SA')}
                </div>
            </div>
            <div class="container">
                ${printContent.html()}
            </div>
            <div class="text-center mt-4 no-print">
                <button onclick="window.print()" class="btn btn-primary" style="background-color: #5B51D8; border: none;">
                    <i class="fa fa-print me-1"></i>طباعة
                </button>
                <button onclick="window.close()" class="btn btn-secondary ms-2">
                    <i class="fa fa-times me-1"></i>إغلاق
                </button>
            </div>
        </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.focus();

    // طباعة تلقائية بعد تحميل الصفحة
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
        }, 250);
    };
}

// معالجة التوست
if (typeof toastr !== 'undefined') {
    const toastMessage = document.querySelector('meta[name="toast-message"]');
    const toastType = document.querySelector('meta[name="toast-type"]');
    
    if (toastMessage && toastType) {
        toastr[toastType.content](toastMessage.content, '', {
            positionClass: 'toast-bottom-left',
            closeButton: true,
            progressBar: true,
            timeOut: 5000
        });
    }
}
