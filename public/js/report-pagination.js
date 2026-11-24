/**
 * دالة معالجة الـ pagination
 */
function renderPagination(pagination) {
    let paginationHtml = '<nav class="mt-3"><ul class="pagination justify-content-center">';

    // زر الصفحة السابقة
    if (pagination.prev_page_url) {
        paginationHtml += `<li class="page-item">
            <a class="page-link" href="#" onclick="loadReportData(${pagination.current_page - 1}); return false;">
                <i class="fas fa-chevron-right"></i> السابق
            </a>
        </li>`;
    } else {
        paginationHtml += '<li class="page-item disabled"><span class="page-link">السابق</span></li>';
    }

    // رقم الصفحة الحالية والإجمالي
    paginationHtml += `<li class="page-item disabled">
        <span class="page-link">الصفحة ${pagination.current_page} من ${pagination.last_page}</span>
    </li>`;

    // زر الصفحة التالية
    if (pagination.next_page_url) {
        paginationHtml += `<li class="page-item">
            <a class="page-link" href="#" onclick="loadReportData(${pagination.current_page + 1}); return false;">
                التالي <i class="fas fa-chevron-left"></i>
            </a>
        </li>`;
    } else {
        paginationHtml += '<li class="page-item disabled"><span class="page-link">التالي</span></li>';
    }

    paginationHtml += '</ul></nav>';
    return paginationHtml;
}

/**
 * دالة عرض معلومات pagination
 */
function displayPaginationInfo(pagination) {
    let infoHtml = `<div class="alert alert-info mt-3">
        <small>
            عدد السجلات الكلي: <strong>${pagination.total}</strong> |
            السجلات في الصفحة: <strong>${pagination.per_page}</strong> |
            إجمالي الصفحات: <strong>${pagination.last_page}</strong>
        </small>
    </div>`;
    return infoHtml;
}

/**
 * دالة التنسيق الأساسية للأرقام
 */
function formatNumber(num) {
    return parseFloat(num).toLocaleString('ar-SA', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * دالة التحقق من حالة الحمل
 */
function showLoadingSpinner(show = true) {
    if (show) {
        const spinner = `<div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-3">جاري تحميل البيانات...</p>
        </div>`;
        $('#reportTableBody').parent().parent().html(spinner);
    }
}

/**
 * دالة إخفاء رسالة الخطأ
 */
function hideErrorAlert() {
    $('#errorAlert').fadeOut('fast');
}

/**
 * دالة عرض رسالة الخطأ
 */
function showErrorAlert(message) {
    let alertHtml = `<div id="errorAlert" class="alert alert-danger alert-dismissible fade show">
        <strong>خطأ!</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#reportForm').after(alertHtml);
}

/**
 * دالة تحويل التاريخ
 */
function formatDateForInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * دالة عرض رسالة التنبيه
 */
function showAlert(message, type = 'info') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;

    // البحث عن مكان لعرض الرسالة
    if ($('#reportForm').length) {
        $('#reportForm').before(alertHtml);
    } else {
        $('body').prepend(alertHtml);
    }

    // إغلاق الرسالة بعد 5 ثوان
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
}
