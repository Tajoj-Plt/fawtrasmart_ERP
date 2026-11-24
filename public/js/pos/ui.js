/*
    ═══════════ الوقتُ والتنبيهاتُ والاختصارات ═══════════

    ما لا يخصّ بيعاً بعينه: الساعة، والتنبيه، ومفاتيحُ الاختصار.
*/

// ==========================================

// تحديث التاريخ والوقت
function updateDateTime() {
    const now = new Date();
    const options = { 
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    };
    const dateTimeElement = document.getElementById('currentDateTime');
    if (dateTimeElement) {
        dateTimeElement.textContent = now.toLocaleDateString('ar-SA', options);
    }
}

// عرض الإشعارات
function showNotification(message, type = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    const icons = {
        success: 'success',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };

    Toast.fire({
        icon: icons[type] || 'info',
        title: message
    });
}

// عرض الأخطاء
function showError(message) {
    showNotification(message, 'error');
}

// إعداد اختصارات لوحة المفاتيح
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // تجاهل الاختصارات عند الكتابة في حقول الإدخال
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }

        switch(e.key) {
            case 'F1':
                e.preventDefault();
                switchView('categories');
                break;
            case 'F2':
                e.preventDefault();
                switchView('products');
                break;
            case 'F3':
                e.preventDefault();
                switchView('clients');
                break;
            case 'F5':
                e.preventDefault();
                holdOrder();
                break;
            case 'F6':
                e.preventDefault();
                initiatePayment();
                break;
                case 'F7':  // أضف هذا
        e.preventDefault();
        initiateReturn();
        break;
            case 'F11':
                e.preventDefault();
                toggleFullscreen();
                break;
            case 'Escape':
                if (searchInput.value) {
                    searchInput.value = '';
                    performSearch('');
                }
                break;
        }

        // اختصارات بـ Ctrl
        if (e.ctrlKey) {
            switch(e.key) {
                case 'f':
                case 'F':
                    e.preventDefault();
                    searchInput.focus();
                    break;
                case 'n':
                case 'N':
                    e.preventDefault();
                    addNewOrder();
                    break;
            }
        }
    });
}

// تبديل الشاشة الكاملة
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            posLog('خطأ في الشاشة الكاملة:', err);
        });
    } else {
        document.exitFullscreen();
    }
}

// تحسين تجربة اللمس
if ('ontouchstart' in window) {
    let isDown = false;
    let startX;
    let scrollLeft;

    const orderTabs = document.getElementById('orderTabs');

    orderTabs.addEventListener('touchstart', (e) => {
        isDown = true;
        startX = e.touches[0].pageX - orderTabs.offsetLeft;
        scrollLeft = orderTabs.scrollLeft;
    }, { passive: true });

    orderTabs.addEventListener('touchend', () => {
        isDown = false;
    }, { passive: true });

    orderTabs.addEventListener('touchmove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.touches[0].pageX - orderTabs.offsetLeft;
        const walk = (x - startX) * 2;
        orderTabs.scrollLeft = scrollLeft - walk;
    });
}

// إجعل الدوال متاحة عالمياً
window.toggleFullscreen = toggleFullscreen;
