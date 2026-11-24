/**
 * JavaScript العام لصفحة عرض العميل
 * يشمل: dropdowns، tooltips، fancybox، appointments filtering
 */

document.addEventListener('DOMContentLoaded', function() {
    // تفعيل Tooltips
    initializeTooltips();

    // إصلاح مشكلة Dropdown Menu
    fixDropdownMenus();

    // تفعيل جميع Dropdowns في الصفحة
    initializeDropdowns();

    // إخفاء الـ dropdown عند النقر خارجه
    setupDropdownClickListener();

    // تفعيل Fancybox للصور
    initializeFancybox();

    // فلترة المواعيد
    setupAppointmentsFilter();

    // تفعيل Select2 إذا كانت موجودة
    initializeSelect2();

    // معالجة نموذج الرصيد الافتتاحي
    handleOpeningBalanceForm();
});

/**
 * تفعيل Tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * إصلاح مشكلة Dropdown Menu
 */
function fixDropdownMenus() {
    document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
        menu.style.position = 'absolute';
        menu.style.zIndex = '10000';
    });
}

/**
 * تفعيل جميع Dropdowns في الصفحة
 */
function initializeDropdowns() {
    const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    dropdownElementList.map(function (dropdownToggleEl) {
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
}

/**
 * إخفاء الـ dropdown عند النقر خارجه
 */
function setupDropdownClickListener() {
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
            });
        }
    });
}

/**
 * تفعيل Fancybox للصور
 */
function initializeFancybox() {
    if (typeof Fancybox !== 'undefined') {
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
    }
}

/**
 * فلترة المواعيد
 */
function setupAppointmentsFilter() {
    const filterButtons = document.querySelectorAll('.filter-appointments');
    filterButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            filterButtons.forEach(function(btn) {
                btn.classList.remove('active');
            });
            this.classList.add('active');

            const rows = document.querySelectorAll('#appointments tbody tr');
            rows.forEach(function(row) {
                const rowStatus = row.getAttribute('data-status');
                if (filter === 'all' || rowStatus == filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
}

/**
 * تفعيل Select2
 */
function initializeSelect2() {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.select2').select2({
            placeholder: 'اختر الموظفين',
            allowClear: true,
            dropdownParent: document.getElementById('assignEmployeeModal') || undefined
        });
    }
}

/**
 * معالجة نموذج الرصيد الافتتاحي
 */
function handleOpeningBalanceForm() {
    const form = document.getElementById('openingBalanceForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // إظهار رسالة الحفظ
        if (typeof Swal !== 'undefined') {
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
        }

        // إرسال الفورم مباشرة (redirect)
        this.submit();
    });
}

/**
 * طباعة الملاحظة
 */
function printNote(noteElement) {
    const printContent = noteElement.cloneNode(true);

    // إزالة الأزرار
    const actions = printContent.querySelector('.note-actions');
    if (actions) actions.remove();

    const overlays = printContent.querySelectorAll('.attachment-overlay');
    overlays.forEach(function(overlay) {
        overlay.remove();
    });

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
                ${printContent.innerHTML}
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

/**
 * معالج طباعة الملاحظات
 */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.print-note').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const noteCard = this.closest('.note-card-item');
            if (noteCard) {
                printNote(noteCard);
            }
        });
    });
});
