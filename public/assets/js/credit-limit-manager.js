/**
 * إدارة الحد الائتماني - Credit Limit Manager
 */

class CreditLimitManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
    }

    /**
     * إعداد مستمعي الأحداث
     */
    setupEventListeners() {
        // عند الضغط على زر فتح الـ modal للحد الائتماني
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-credit-limit')) {
                const clientId = e.target.dataset.clientId;
                this.openCreditLimitModal(clientId);
            }
        });
    }

    /**
     * فتح modal الحد الائتماني
     */
    openCreditLimitModal(clientId) {
        // يمكن إضافة منطق لفتح modal هنا
        // سيتم استخدام نفس الـ modal الموجود في show.blade.php
    }

    /**
     * تحديث الحد الائتماني لعميل واحد
     */
    updateSingleClientLimit(clientId, value) {
        return fetch(window.clientRoutes?.update_credit_limit || '/clients/update-credit-limit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
            },
            body: JSON.stringify({
                client_id: clientId,
                value: value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccessMessage(data.message);
                return true;
            } else {
                this.showErrorMessage(data.message);
                return false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showErrorMessage('حدث خطأ في الاتصال');
            return false;
        });
    }

    /**
     * تحديث الحد الائتماني لجميع العملاء
     */
    updateAllClientsLimit(value) {
        if (!confirm('هل أنت متأكد من تحديث الحد الائتماني لجميع العملاء؟')) {
            return false;
        }

        return fetch(window.clientRoutes?.update_all_credit_limits || '/clients/update-all-credit-limits', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
            },
            body: JSON.stringify({
                value: value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccessMessage(data.message);
                return true;
            } else {
                this.showErrorMessage(data.message);
                return false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showErrorMessage('حدث خطأ في الاتصال');
            return false;
        });
    }

    /**
     * عرض رسالة نجاح
     */
    showSuccessMessage(message) {
        if (typeof toastr !== 'undefined') {
            toastr.success(message);
        } else {
            alert(message);
        }
    }

    /**
     * عرض رسالة خطأ
     */
    showErrorMessage(message) {
        if (typeof toastr !== 'undefined') {
            toastr.error(message);
        } else {
            alert('خطأ: ' + message);
        }
    }
}

// تفعيل الـ manager عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    window.creditLimitManager = new CreditLimitManager();
});
