/**
 * نظام الإشعارات المنبثقة للموظفين
 * يعرض الإشعارات الجديدة والمجدولة كـ Popup للموظف
 */

(function() {
    'use strict';

    // الحصول على الـ locale من الـ URL
    function getLocalePrefix() {
        const path = window.location.pathname;
        const match = path.match(/^\/(ar|en|ur)\//);
        return match ? '/' + match[1] : '';
    }

    // إعدادات النظام - تستخدم الـ routes المعرفة من Laravel أو تحسب من الـ URL
    const CONFIG = {
        checkInterval: 30000,        // التحقق كل 30 ثانية
        get apiUrl() {
            return window.employeeNotificationRoutes?.apiUrl || getLocalePrefix() + '/sales/notifications/employee-popup';
        },
        get markShownUrl() {
            return window.employeeNotificationRoutes?.markShownUrl || getLocalePrefix() + '/sales/notifications/employee-popup/mark-shown';
        },
        enableSound: true,
        soundUrl: '/sounds/notification.mp3'
    };

    // حالة النظام
    let isShowingNotification = false;
    let notificationQueue = [];
    let checkIntervalId = null;

    /**
     * حقن الأنماط CSS
     */
    function injectStyles() {
        if (document.getElementById('employee-popup-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'employee-popup-styles';
        styles.textContent = `
            @keyframes employeeDropDown {
                0% { transform: translateY(-100vh) scale(0.5) rotateX(90deg); opacity: 0; }
                60% { transform: translateY(20px) scale(1.05); opacity: 1; }
                100% { transform: translateY(0) scale(1); opacity: 1; }
            }

            @keyframes employeeShake {
                0%, 100% { transform: translateX(0); }
                10%, 30% { transform: translateX(-8px); }
                20%, 40% { transform: translateX(8px); }
                50% { transform: translateX(0); }
            }

            @keyframes employeePulse {
                0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(115, 103, 240, 0.5); }
                50% { transform: scale(1.05); box-shadow: 0 0 50px rgba(115, 103, 240, 0.9); }
            }

            @keyframes employeeBounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-15px); }
            }

            @keyframes employeeFloatUp {
                0% { transform: translateY(0) scale(1); opacity: 1; }
                100% { transform: translateY(-200px) scale(0); opacity: 0; }
            }

            @keyframes employeeFall {
                0% { transform: translateY(-20vh) translateX(-20px) rotateZ(0deg); opacity: 0; }
                10% { opacity: 1; }
                25% { transform: translateY(20vh) translateX(25px) rotateZ(45deg); }
                50% { transform: translateY(50vh) translateX(-25px) rotateZ(180deg); }
                75% { transform: translateY(80vh) translateX(25px) rotateZ(315deg); }
                100% { transform: translateY(110vh) translateX(0) rotateZ(720deg); opacity: 0; }
            }

            .employee-popup .swal2-popup {
                border-radius: 20px !important;
                border: 3px solid #7367F0 !important;
                background: linear-gradient(145deg, #f5f4ff 0%, #e8e6ff 100%) !important;
                box-shadow: 0 20px 60px rgba(115, 103, 240, 0.3) !important;
                animation: employeeDropDown 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
                padding: 30px !important;
                z-index: 100001 !important;
            }

            .employee-popup .swal2-icon.swal2-info {
                font-size: 50px !important;
                width: 60px !important;
                height: 60px !important;
                margin: 10px auto !important;
                border-width: 3px !important;
                border-color: #7367F0 !important;
                color: #7367F0 !important;
                animation: employeeBounce 0.6s ease-in-out !important;
            }

            .employee-popup .swal2-icon.swal2-info .swal2-icon-content {
                color: #7367F0 !important;
            }

            .employee-popup .swal2-title {
                color: #7367F0 !important;
                font-weight: 800 !important;
                font-size: 24px !important;
                margin: 20px 0 15px !important;
                animation: employeeShake 0.5s ease-out !important;
            }

            .employee-popup .swal2-html-container {
                color: #333 !important;
                font-size: 15px !important;
                line-height: 1.8 !important;
                direction: rtl !important;
                text-align: right !important;
                padding: 20px !important;
                background: rgba(255, 255, 255, 0.7) !important;
                border-radius: 15px !important;
            }

            .employee-popup .notification-sender {
                font-weight: 700 !important;
                color: #7367F0 !important;
                margin-bottom: 12px !important;
                font-size: 16px !important;
            }

            .employee-popup .notification-description {
                color: #444 !important;
                margin: 15px 0 !important;
                padding: 10px;
                background: rgba(115, 103, 240, 0.08);
                border-radius: 10px;
                border-right: 4px solid #7367F0;
            }

            .employee-popup .notification-time {
                color: #888 !important;
                font-size: 13px !important;
                margin-top: 15px;
            }

            .employee-popup .notification-scheduled-badge {
                display: inline-block;
                background: linear-gradient(135deg, #ff9f43, #ff6b6b);
                color: white;
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 12px;
                margin-top: 10px;
            }

            .employee-popup .swal2-confirm {
                background: linear-gradient(135deg, #7367F0, #5a50d9) !important;
                border-radius: 20px !important;
                padding: 12px 50px !important;
                font-weight: 700 !important;
                color: white !important;
                box-shadow: 0 8px 25px rgba(115, 103, 240, 0.4) !important;
                animation: employeePulse 1.5s infinite !important;
            }

            .employee-popup .swal2-confirm:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 12px 35px rgba(115, 103, 240, 0.6) !important;
            }

            .employee-popup .swal2-backdrop {
                background: rgba(0, 0, 0, 0.2) !important;
                z-index: 100000 !important;
            }

            .employee-notification-piece {
                position: fixed;
                pointer-events: none;
                z-index: 99999;
                font-size: 25px;
            }

            .employee-notification-sparkle {
                position: absolute;
                pointer-events: none;
                z-index: 100000;
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * إنشاء تأثير المطر المتساقط
     */
    function createNotificationRain() {
        const icons = [
            '<i class="fas fa-bell"></i>',
            '<i class="fas fa-envelope"></i>',
            '<i class="fas fa-comment"></i>',
            '<i class="fas fa-exclamation-circle"></i>',
            '<i class="fas fa-clipboard-list"></i>',
            '<i class="fas fa-tasks"></i>',
            '<i class="fas fa-star"></i>'
        ];
        const colors = ['#7367F0', '#28c76f', '#ff9f43', '#00cfe8', '#ea5455'];

        for (let i = 0; i < 50; i++) {
            const piece = document.createElement('div');
            piece.className = 'employee-notification-piece';
            piece.innerHTML = icons[Math.floor(Math.random() * icons.length)];
            piece.style.left = Math.random() * 100 + 'vw';
            piece.style.top = '-50px';
            piece.style.color = colors[Math.floor(Math.random() * colors.length)];
            piece.style.fontSize = (Math.random() * 25 + 15) + 'px';
            piece.style.textShadow = '0 0 5px rgba(0,0,0,0.1)';

            const duration = Math.random() * 3 + 3;
            const delay = Math.random() * 2;
            piece.style.animation = `employeeFall ${duration}s ease-in-out forwards`;
            piece.style.animationDelay = delay + 's';

            document.body.appendChild(piece);
            setTimeout(() => piece.remove(), (duration + delay + 1) * 1000);
        }
    }

    /**
     * تشغيل صوت الإشعار
     */
    function playNotificationSound() {
        if (!CONFIG.enableSound) return;

        try {
            // محاولة تشغيل صوت إشعار بسيط
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.3;

            oscillator.start();
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            oscillator.stop(audioContext.currentTime + 0.5);
        } catch (e) {
            console.log('تعذر تشغيل صوت الإشعار');
        }
    }

    /**
     * الحصول على CSRF token
     */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * وضع علامة "تم العرض" على الإشعار
     */
    function markNotificationAsShown(notificationId) {
        fetch(CONFIG.markShownUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ تم وضع علامة على الإشعار:', notificationId);
            }
        })
        .catch(error => console.error('❌ خطأ:', error));
    }

    /**
     * عرض الإشعارات بالتتابع
     */
    function showNotificationsSequentially(notifications, index = 0) {
        if (!notifications || notifications.length === 0) return;
        if (index >= notifications.length) {
            isShowingNotification = false;
            return;
        }

        isShowingNotification = true;
        const notification = notifications[index];

        injectStyles();
        createNotificationRain();
        playNotificationSound();

        const isScheduled = notification.is_scheduled;
        const scheduledBadge = isScheduled
            ? `<div class="notification-scheduled-badge"><i class="fas fa-clock" style="margin-left: 5px;"></i>إشعار مجدول</div>`
            : '';

        const htmlContent = `
            <div class="notification-sender">
                <i class="fas fa-user-circle" style="margin-left: 8px;"></i>
                المرسل: ${notification.sender}
            </div>
            <div class="notification-description">
                ${notification.description || 'لا يوجد وصف'}
            </div>
            <div class="notification-time">
                <i class="fas fa-clock" style="margin-left: 8px;"></i>
                ${notification.created_at}
            </div>
            ${scheduledBadge}
        `;

        Swal.fire({
            icon: 'info',
            title: notification.title || 'إشعار جديد',
            html: htmlContent,
            confirmButtonText: '<i class="fas fa-check" style="margin-left: 8px;"></i>تم',
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: {
                container: 'employee-popup'
            }
        }).then((result) => {
            if (result.isConfirmed && notification.id) {
                markNotificationAsShown(notification.id);
            }

            // عرض الإشعار التالي بعد تأخير قصير
            setTimeout(() => {
                showNotificationsSequentially(notifications, index + 1);
            }, 500);
        });
    }

    /**
     * التحقق من وجود إشعارات جديدة
     */
    function checkForNewNotifications() {
        // تجنب التحقق إذا كان هناك إشعار معروض حالياً
        if (isShowingNotification) return;

        fetch(CONFIG.apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications && data.notifications.length > 0) {
                console.log('📬 تم العثور على ' + data.notifications.length + ' إشعار جديد');
                showNotificationsSequentially(data.notifications);
            }
        })
        .catch(error => {
            console.error('❌ خطأ في جلب الإشعارات:', error);
        });
    }

    /**
     * بدء النظام
     */
    function initEmployeeNotifications() {
        console.log('🔔 بدء نظام الإشعارات المنبثقة للموظفين...');

        // التحقق الأولي بعد تحميل الصفحة
        setTimeout(() => {
            checkForNewNotifications();
        }, 2000);

        // التحقق الدوري
        checkIntervalId = setInterval(checkForNewNotifications, CONFIG.checkInterval);
    }

    /**
     * إيقاف النظام
     */
    function stopEmployeeNotifications() {
        if (checkIntervalId) {
            clearInterval(checkIntervalId);
            checkIntervalId = null;
        }
    }

    // تصدير الدوال للاستخدام الخارجي
    window.EmployeeNotifications = {
        init: initEmployeeNotifications,
        stop: stopEmployeeNotifications,
        check: checkForNewNotifications,
        showTest: function() {
            const testNotifications = [{
                id: 0,
                title: 'إشعار تجريبي',
                description: 'هذا إشعار تجريبي لاختبار نظام الإشعارات المنبثقة للموظفين',
                sender: 'النظام',
                created_at: new Date().toLocaleString('ar-SA'),
                is_scheduled: false
            }];
            showNotificationsSequentially(testNotifications);
        }
    };

    // بدء النظام عند تحميل الصفحة
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEmployeeNotifications);
    } else {
        initEmployeeNotifications();
    }

    console.log('✅ نظام إشعارات الموظفين جاهز - اكتب: EmployeeNotifications.showTest()');
})();
