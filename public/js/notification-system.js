/**
 * نظام الإشعارات الاحترافي مع Font Awesome
 * Build: 20260605c
 */
window.NOTIFICATION_SYSTEM_BUILD = '20260605c';

// دوال الـ overlay (fallback في حالة عدم تحميل header.blade.php)
if (typeof hideNotificationsOverlay === 'undefined') {
    window.hideNotificationsOverlay = function () {
        const overlay = document.getElementById('notifications-overlay');
        if (overlay) {
            overlay.classList.add('hidden');
            overlay.style.display = 'none';
        }
    };
}

if (typeof showNotificationsOverlay === 'undefined') {
    window.showNotificationsOverlay = function () {
        const overlay = document.getElementById('notifications-overlay');
        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.style.display = 'flex';
        }
    };
}

// دالة إرسال الرد (fallback - قد تكون معرفة في header أو في صفحة العميل)
if (typeof sendNotificationReply === 'undefined') {
    window.sendNotificationReply = function (notificationId, replyMessage) {
        // في صفحة العميل: استخدم مسار الرد الخاص بالعميل إن وُجد
        if (typeof sendClientNotificationReply === 'function' && window.clientNotificationPageId) {
            return sendClientNotificationReply(notificationId, replyMessage);
        }
        // وإلا استخدم الـ route الأساسي (في الهيدر)
        return fetch(`/sales/notifications/reply/${notificationId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken || ''
            },
            body: JSON.stringify({ reply_message: replyMessage })
        })
            .then(response => response.json())
            .then(data => {
                console.log('🔔 بيانات الرد:', data);
                if (data.success) {
                    console.log('✅ تم إرسال الرد بنجاح');
                    return true;
                } else {
                    console.error('❌ فشل إرسال الرد:', data.message);
                    return false;
                }
            })
            .catch(error => {
                console.error('❌ خطأ في إرسال الرد:', error);
                return false;
            });
    };
}

// دالة وضع علامة مقروء (fallback)
if (typeof markNotificationAsRead === 'undefined') {
    window.markNotificationAsRead = function (notificationId) {
        // تحقق إذا كانت دالة markClientNotificationAsRead موجودة (في صفحة العميل)
        if (typeof markClientNotificationAsRead === 'function') {
            return markClientNotificationAsRead(notificationId);
        }
        // وإلا استخدم الـ route الأساسي (في الهيدر)
        fetch('/notifications/client-popup/mark-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken || ''
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('✅ تم وضع علامة مقروء على الإشعار:', notificationId);
                }
            })
            .catch(error => console.error('❌ خطأ:', error));
    };
}

function injectNotificationStyles() {
    if (document.getElementById('popup-notification-styles')) return;

    const styles = document.createElement('style');
    styles.id = 'popup-notification-styles';
    styles.textContent = `
        @keyframes dropDown {
            0% { transform: translateY(-100vh) scale(0.5) rotateX(90deg); opacity: 0; }
            60% { transform: translateY(20px) scale(1.05); opacity: 1; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30% { transform: translateX(-8px); }
            20%, 40% { transform: translateX(8px); }
            50% { transform: translateX(0); }
        }

        @keyframes bodyShake {
            0% { transform: translate(0, 0); }
            10% { transform: translate(-10px, -10px) rotate(-1deg); }
            20% { transform: translate(10px, 10px) rotate(1deg); }
            30% { transform: translate(-10px, 10px) rotate(-1deg); }
            40% { transform: translate(10px, -10px) rotate(1deg); }
            50% { transform: translate(0, 0); }
        }

        @keyframes warningFall {
            0% { transform: translateY(-20vh) translateX(-20px) rotateZ(0deg); opacity: 0; }
            10% { opacity: 1; }
            25% { transform: translateY(20vh) translateX(25px) rotateZ(45deg); }
            50% { transform: translateY(50vh) translateX(-25px) rotateZ(180deg); }
            75% { transform: translateY(80vh) translateX(25px) rotateZ(315deg); }
            100% { transform: translateY(110vh) translateX(0) rotateZ(720deg); opacity: 0; }
        }

        @keyframes flashScreen {
            0%, 100% { background: rgba(214, 48, 49, 0); }
            10%, 30% { background: rgba(214, 48, 49, 0.15); }
            20% { background: rgba(214, 48, 49, 0.05); }
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(214, 48, 49, 0.5); }
            50% { transform: scale(1.05); box-shadow: 0 0 50px rgba(214, 48, 49, 0.9); }
        }

        @keyframes floatUp {
            0% { transform: translateY(0) scale(1); opacity: 1; }
            100% { transform: translateY(-200px) scale(0); opacity: 0; }
        }

        @keyframes waveExpand {
            0% { width: 0; height: 0; opacity: 0.8; }
            100% { width: 500px; height: 500px; opacity: 0; }
        }

        @keyframes disappear {
            0% { opacity: 1; transform: scale(1) rotate(0deg); }
            100% { opacity: 0; transform: scale(0.3) rotate(180deg) translateY(100px); }
        }

        .swal2-popup {
            border-radius: 25px !important;
            border: 3px solid #d63031 !important;
            background: linear-gradient(135deg, #fff5f5 0%, #ffe0e0 100%) !important;
            box-shadow: 0 20px 60px rgba(214, 48, 49, 0.3) !important;
            animation: dropDown 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
            padding: 30px !important;
            z-index: 100001 !important;
        }

        .swal2-popup.hide {
            animation: disappear 0.6s ease-in forwards !important;
        }

        .swal2-icon.swal2-warning {
            font-size: 50px !important;
            width: 60px !important;
            height: 60px !important;
            margin: 10px auto !important;
            border-width: 3px !important;
            animation: bounce 0.6s ease-in-out !important;
        }

        .swal2-icon.swal2-warning .swal2-icon-content {
            font-size: 35px !important;
        }

        .swal2-title {
            color: #d63031 !important;
            font-weight: 800 !important;
            font-size: 26px !important;
            margin: 20px 0 15px !important;
            animation: shake 0.5s ease-out !important;
        }

        .swal2-html-container {
            color: #333 !important;
            font-size: 15px !important;
            line-height: 1.8 !important;
            direction: rtl !important;
            text-align: right !important;
            padding: 20px !important;
            background: rgba(255, 255, 255, 0.6) !important;
            border-radius: 15px !important;
        }

        .notification-sender {
            font-weight: 700 !important;
            color: #d63031 !important;
            margin-bottom: 12px !important;
        }

        .notification-description {
            color: #555 !important;
            margin: 12px 0 !important;
        }

        .notification-time {
            color: #999 !important;
            font-size: 13px !important;
        }

        .notification-reply-context {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.12), rgba(115, 103, 240, 0.12)) !important;
            border-right: 4px solid #28a745 !important;
            border-radius: 12px !important;
            padding: 14px 16px !important;
            margin-bottom: 14px !important;
            font-weight: 700 !important;
            color: #2d3436 !important;
            line-height: 1.8 !important;
        }

        .notification-reply-meta {
            color: #555 !important;
            margin: 8px 0 !important;
            font-size: 14px !important;
        }

        .notification-reply-meta strong {
            color: #7367F0 !important;
        }

        .notification-original-title {
            color: #666 !important;
            font-size: 13px !important;
            margin-bottom: 10px !important;
            padding: 8px 12px !important;
            background: rgba(0, 0, 0, 0.04) !important;
            border-radius: 8px !important;
        }

        .swal2-confirm {
            background: linear-gradient(135deg, #d63031, #c71f1f) !important;
            border-radius: 20px !important;
            padding: 12px 50px !important;
            font-weight: 700 !important;
            color: white !important;
            box-shadow: 0 8px 25px rgba(214, 48, 49, 0.4) !important;
            animation: pulse 1.5s infinite !important;
        }

        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 12px 35px rgba(214, 48, 49, 0.6) !important;
        }

        .warning-piece {
            position: fixed;
            pointer-events: none;
            z-index: 99999;
            font-size: 30px;
            color: #d63031;
        }

        .sparkle {
            position: absolute;
            pointer-events: none;
            font-size: 20px;
            color: #d63031;
            z-index: 100000;
        }

        .wave-ring {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            border: 2px solid rgba(214, 48, 49, 0.6);
            background: radial-gradient(circle, rgba(214, 48, 49, 0.1) 0%, transparent 70%);
            z-index: 99998;
        }

        .celebration-icon {
            position: fixed;
            font-size: 50px;
            pointer-events: none;
            animation: floatUp 1.5s ease-out forwards;
            z-index: 99999;
        }

        .swal2-backdrop {
            background: rgba(0, 0, 0, 0.15) !important;
            z-index: 100000 !important;
        }
    `;
    document.head.appendChild(styles);
}

function createRain(type = 'warning') {
    // تأثير الوميض المفاجئ للتحذيرات فقط
    if (type === 'warning') {
        const flash = document.createElement('div');
        flash.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99990;animation:flashScreen 0.8s ease-out forwards;';
        document.body.appendChild(flash);
        setTimeout(() => flash.remove(), 1000);
    }

    let icons, colors;

    if (type === 'warning') {
        icons = [
            '<i class="fas fa-clipboard-list"></i>',   // قائمة مهام
            '<i class="fas fa-file-signature"></i>',   // توقيع مطلوب
            '<i class="fas fa-exclamation-circle"></i>', // تنبيه
            '<i class="fas fa-stopwatch"></i>',        // وقت محدد
            '<i class="fas fa-bell"></i>',              // إشعار
            '<i class="fas fa-bullhorn"></i>',          // نداء/أمر
            '<i class="fas fa-tasks"></i>'              // مهام
        ];
        colors = ['#d63031', '#e17055', '#fdcb6e', '#0984e3', '#2d3436']; // أضفت الأزرق للمهام
    } else {
        icons = [
            '<i class="fas fa-check"></i>',
            '<i class="fas fa-star"></i>',
            '<i class="fas fa-heart"></i>',
            '<i class="fas fa-thumbs-up"></i>',
            '<i class="fas fa-smile"></i>',
            '<i class="fas fa-gift"></i>',
            '<i class="fas fa-gem"></i>'
        ];
        colors = ['#00b894', '#55efc4', '#0984e3', '#6c5ce7', '#fdcb6e', '#e84393'];
    }

    // زيادة عدد العناصر المتساقطة
    for (let i = 0; i < 80; i++) {
        const piece = document.createElement('div');
        piece.className = 'warning-piece';
        piece.innerHTML = icons[Math.floor(Math.random() * icons.length)];

        // خصائص عشوائية للموقع والحجم واللون
        piece.style.left = Math.random() * 100 + 'vw';
        piece.style.top = -50 + 'px';
        piece.style.color = colors[Math.floor(Math.random() * colors.length)];
        piece.style.fontSize = (Math.random() * 30 + 20) + 'px';
        piece.style.textShadow = '0 0 5px rgba(0,0,0,0.2)';

        // سرعة وتأخير عشوائي
        const duration = Math.random() * 3 + 3; // بين 3 و 6 ثواني
        const delay = Math.random() * 2;

        piece.style.animation = `warningFall ${duration}s ease-in-out forwards`;
        piece.style.animationDelay = delay + 's';

        document.body.appendChild(piece);
        setTimeout(() => piece.remove(), (duration + delay + 1) * 1000);
    }
}

function createWarningConfetti() {
    createRain('warning');
}

function createWaves() {
    const waveCount = 4;
    for (let i = 0; i < waveCount; i++) {
        const wave = document.createElement('div');
        wave.className = 'wave-ring';
        wave.style.top = window.innerHeight / 2 - 250 + 'px';
        wave.style.left = window.innerWidth / 2 - 250 + 'px';

        const duration = 1.2 + i * 0.15;
        wave.style.animation = `waveExpand ${duration}s ease-out forwards`;
        wave.style.animationDelay = i * 0.1 + 's';

        document.body.appendChild(wave);
        setTimeout(() => wave.remove(), duration * 1000 + 500);
    }
}

function getSwalPopupElement(popup) {
    if (!popup) {
        return (typeof Swal !== 'undefined' && Swal.getPopup) ? Swal.getPopup() : null;
    }
    if (popup.classList && popup.classList.contains('swal2-popup')) {
        return popup;
    }
    return popup.querySelector('.swal2-popup') || popup;
}

function scheduleSparkles(popup, attempt = 0) {
    const element = getSwalPopupElement(popup);
    if (!element) {
        return;
    }

    if (element.offsetWidth > 0 && element.offsetHeight > 0) {
        createSparkles(element);
        return;
    }

    if (attempt < 8) {
        requestAnimationFrame(() => scheduleSparkles(popup, attempt + 1));
    }
}

function createSparkles(element) {
    if (!element || element.offsetWidth <= 0 || element.offsetHeight <= 0) {
        return;
    }

    const sparkleIcons = [
        '<i class="fas fa-star"></i>',
        '<i class="fas fa-sparkles"></i>',
        '<i class="fas fa-asterisk"></i>'
    ];

    for (let i = 0; i < 30; i++) {
        const sparkle = document.createElement('div');
        sparkle.className = 'sparkle';
        sparkle.innerHTML = sparkleIcons[Math.floor(Math.random() * sparkleIcons.length)];
        sparkle.style.left = Math.random() * element.offsetWidth + 'px';
        sparkle.style.top = Math.random() * element.offsetHeight + 'px';
        sparkle.style.animation = `floatUp ${Math.random() * 1 + 1}s ease-out forwards`;
        sparkle.style.animationDelay = Math.random() * 0.3 + 's';
        sparkle.style.color = '#ffd700';

        element.appendChild(sparkle);
    }
}

function createCelebration() {
    createRain('success');

    const celebrationIcons = [
        '<i class="fas fa-check-circle"></i>',
        '<i class="fas fa-thumbs-up"></i>',
        '<i class="fas fa-star"></i>',
        '<i class="fas fa-check-double"></i>',
        '<i class="fas fa-badge-check"></i>'
    ];
    const positions = [
        { x: 15, y: 20 },
        { x: 85, y: 20 },
        { x: 15, y: 80 },
        { x: 85, y: 80 },
        { x: 50, y: 50 }
    ];

    positions.forEach((pos, index) => {
        setTimeout(() => {
            const icon = document.createElement('div');
            icon.className = 'celebration-icon';
            icon.innerHTML = celebrationIcons[index];
            icon.style.left = pos.x + 'vw';
            icon.style.top = pos.y + 'vh';
            icon.style.color = '#27ae60';
            document.body.appendChild(icon);
            setTimeout(() => icon.remove(), 1500);
        }, index * 50);
    });
}

function shakeBody() {
    document.body.style.animation = 'bodyShake 0.5s cubic-bezier(.36,.07,.19,.97) both';
    setTimeout(() => {
        document.body.style.animation = '';
    }, 500);
}

function showClientNotificationsSequentially(notifications, index = 0) {
    if (!notifications || notifications.length === 0) {
        // جميع الإشعارات انتهت - إخفاء الحجب
        if (typeof hideNotificationsOverlay === 'function') {
            hideNotificationsOverlay();
        }
        return;
    }
    if (index >= notifications.length) {
        // جميع الإشعارات انتهت - إخفاء الحجب
        if (typeof hideNotificationsOverlay === 'function') {
            hideNotificationsOverlay();
        }
        return;
    }

    const notification = notifications[index];

    // التحقق إذا كان الإشعار محذوف بالفعل من localStorage
    let deletedNotifications = JSON.parse(localStorage.getItem('deletedNotifications') || '[]');
    if (deletedNotifications.includes(notification.id)) {
        // تخطي هذا الإشعار والانتقال للتالي
        showClientNotificationsSequentially(notifications, index + 1);
        return;
    }

    // التحقق إذا كان الإشعار تم الرد عليه بالفعل
    let seenNotifications = JSON.parse(localStorage.getItem('seenNotifications') || '[]');
    if (seenNotifications.includes(notification.id)) {
        // تخطي هذا الإشعار والانتقال للتالي
        console.log('⏭️ تخطي الإشعار الي تم الرد عليه:', notification.id);
        showClientNotificationsSequentially(notifications, index + 1);
        return;
    }

    injectNotificationStyles();
    createWaves();
    createWarningConfetti();
    // shakeBody(); // تم إلغاء اهتزاز الشاشة

    const titleText = notification.title || 'تحذير';
    const descriptionText = notification.description || '';
    const senderText = notification.sender || 'النظام';
    const createdAtText = notification.created_at || new Date().toLocaleString('ar-SA');
    const notificationType = String(notification.type || '').trim().toLowerCase();
    const titleLower = String(notification.title || '').toLowerCase();
    const isReply = notificationType === 'reply';
    const clientLabel = notification.client_label
        || (notification.client_name
            ? (notification.client_code
                ? `${notification.client_name} - كود ${notification.client_code}`
                : notification.client_name)
            : (notification.client_id ? `عميل #${notification.client_id}` : ''));
    const isClientReply = isReply && notification.is_client_reply === true && !notification.employee_only;

    let htmlContent;
    if (isClientReply) {
        htmlContent = `
            <div class="notification-reply-context" style="margin-bottom: 12px;">
                <i class="fas fa-store" style="margin-left: 8px; color: #28a745;"></i>
                <strong>العميل:</strong> ${clientLabel}
            </div>
            <div class="notification-reply-meta">
                <i class="fas fa-user-tie" style="margin-left: 6px; color: #7367F0;"></i>
                <strong>من:</strong> ${senderText}
            </div>
            <div class="notification-description">${descriptionText}</div>
            <div class="notification-time"><i class="fas fa-clock" style="margin-left: 8px;"></i>${createdAtText}</div>
        `;
    } else if (isReply) {
        htmlContent = `
            <div class="notification-sender"><i class="fas fa-user-tie" style="margin-left: 8px;"></i><strong>من:</strong> ${senderText}</div>
            <div class="notification-description">${descriptionText}</div>
            <div class="notification-time"><i class="fas fa-clock" style="margin-left: 8px;"></i>${createdAtText}</div>
        `;
    } else {
        htmlContent = `
            <div class="notification-sender"><i class="fas fa-user-shield" style="margin-left: 8px;"></i>${senderText}</div>
            <div class="notification-description">${descriptionText}</div>
            <div class="notification-time"><i class="fas fa-clock" style="margin-left: 8px;"></i>${createdAtText}</div>
        `;
    }

    // التحقق من نوع الإشعار والمستخدم
    const isAdmin = notification.notification_target === 'admin' || notification.notification_target === 'admins';

    // إذا كان رد أو للإدارة: عرض "تم الاستلام" فقط
    // وإلا: عرض "الرد"
    const systemInfoTypes = [
        'advance_request',
        'advance_request_received',
        'advance_approved',
        'advance_rejected',
        'advance_settled',
        'deduction',
        'client_status_approved',
        'client_status_rejected',
        'client_stage_approved',
        'client_stage_rejected'
    ];
    // فقط طلب سلفة معلّق (advance_request) يعرض موافقة/رفض للمعتمد
    // إشعارات advance_approved / advance_rejected / deduction للموظف = تم الاستلام فقط
    const isAdvancePendingForApprover = notificationType === 'advance_request';
    const isClientStatusRequestPendingForApprover = notificationType === 'client_status_request';
    // نفس نمط طلب اعتماد الحالة أعلاه — طلب اعتماد وضع العميل
    const isClientStageRequestPendingForApprover = notificationType === 'client_stage_request';
    const isSystemInfoNotification = systemInfoTypes.includes(notificationType)
        || titleLower.includes('سلفة')
        || titleLower.includes('خصم');
    const showOnlyMarkAsRead = isReply || isAdmin || isSystemInfoNotification || isClientStatusRequestPendingForApprover || isClientStageRequestPendingForApprover;

    let swalConfig = {
        icon: 'warning',
        title: titleText,
        html: htmlContent,
        showCancelButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: function (popup) {
            scheduleSparkles(popup);
        }
    };

    if (showOnlyMarkAsRead) {
        if (isAdvancePendingForApprover || isClientStatusRequestPendingForApprover || isClientStageRequestPendingForApprover) {
            // المعتمد: موافقة / رفض / لاحقاً
            swalConfig.title = isClientStatusRequestPendingForApprover
                ? 'طلب اعتماد حالة عميل'
                : (isClientStageRequestPendingForApprover ? 'طلب اعتماد وضع عميل' : 'طلب سلفة جديد');
            swalConfig.confirmButtonText = '<i class="fas fa-check" style="margin-left: 8px;"></i>اعتماد';
            swalConfig.confirmButtonColor = '#28a745';
            swalConfig.showDenyButton = true;
            swalConfig.denyButtonText = '<i class="fas fa-times" style="margin-left: 8px;"></i>رفض';
            swalConfig.denyButtonColor = '#dc3545';
            swalConfig.showCancelButton = true;
            swalConfig.cancelButtonText = '<i class="fas fa-clock" style="margin-left: 8px;"></i>لاحقاً';
            swalConfig.cancelButtonColor = '#6c757d';
        } else {
            // الموظف بعد الموافقة/الرفض: تم الاستلام فقط
            swalConfig.confirmButtonText = '<i class="fas fa-check" style="margin-left: 8px;"></i>تم الاستلام';
            swalConfig.confirmButtonColor = '#7367F0';
        }
    } else {
        // للموظفين العاديين: زر "الرد" فقط
        swalConfig.confirmButtonText = '<i class="fas fa-reply" style="margin-left: 8px;"></i>الرد';
        swalConfig.confirmButtonColor = '#28a745';
    }


    Swal.fire(swalConfig).then(function (result) {
        if ((isAdvancePendingForApprover || isClientStatusRequestPendingForApprover || isClientStageRequestPendingForApprover) && notification.id) {
            if (result.isConfirmed) {
                if (isClientStatusRequestPendingForApprover) {
                    handleClientStatusRequestDecision(notification, 'approve', notifications, index);
                } else if (isClientStageRequestPendingForApprover) {
                    handleClientStageRequestDecision(notification, 'approve', notifications, index);
                } else {
                    startAdvanceApproval(notification, notifications, index);
                }
                return;
            }
            if (result.isDenied) {
                if (isClientStatusRequestPendingForApprover) {
                    handleClientStatusRequestDecision(notification, 'reject', notifications, index);
                } else if (isClientStageRequestPendingForApprover) {
                    handleClientStageRequestDecision(notification, 'reject', notifications, index);
                } else {
                    showAdvanceRejectReasonDialog(notification, notifications, index);
                }
                return;
            }
            if (result.dismiss === Swal.DismissReason.cancel) {
                if (notification.id && typeof markNotificationAsRead === 'function') {
                    markNotificationAsRead(notification.id);
                }
                if (typeof hideNotificationsOverlay === 'function') {
                    hideNotificationsOverlay();
                }
                setTimeout(() => location.reload(), 300);
                return;
            }
        }

        if (result.isConfirmed && notification.id) {
            if (showOnlyMarkAsRead) {
                // وضع علامة مقروء ثم إعادة تحميل الصفحة
                markNotificationAsRead(notification.id);

                const backdrop = document.querySelector('.swal2-container');
                if (backdrop) {
                    backdrop.remove();
                }
                if (typeof hideNotificationsOverlay === 'function') {
                    hideNotificationsOverlay();
                }

                setTimeout(() => {
                    location.reload();
                }, 250);
            } else {
                // فتح نافذة الرد
                showReplyDialogForNotification(notification, notifications, index);
            }
        }
    });
}

function extractAdvanceRequestId(notification) {
    const match = (notification.description || '').match(/#(\d+)/);
    return match && match[1] ? match[1] : null;
}

function resolveHrAdvanceApprovalUrls() {
    if (window.hrAdvanceApprovalUrls && window.hrAdvanceApprovalUrls.approve) {
        return window.hrAdvanceApprovalUrls;
    }

    const approveMeta = document.querySelector('meta[name="hr-advance-approve-url"]');
    const rejectMeta = document.querySelector('meta[name="hr-advance-reject-url"]');
    const optionsMeta = document.querySelector('meta[name="hr-advance-disbursement-options-url"]');
    if (approveMeta && rejectMeta) {
        window.hrAdvanceApprovalUrls = {
            approve: approveMeta.getAttribute('content'),
            reject: rejectMeta.getAttribute('content'),
            disbursementOptions: optionsMeta ? optionsMeta.getAttribute('content') : null
        };
        return window.hrAdvanceApprovalUrls;
    }

    return null;
}

/*
    اعتمادُ السلفة من الإشعار — خطوتان في نافذةٍ واحدة.

    كان الاعتمادُ من البوب قراراً وحده، فيُطلب من المعتمِد أن يفتح شاشةَ الطلب
    ليصرف: خطوتان ونقلةُ صفحةٍ لقرارٍ واحد. وقبل ذلك كان البوبُ يصرف من خزينةٍ
    مفترَضةٍ لم يرَها أحد — وهو ما أُصلح بفصل الاعتماد عن الصرف.

    فالحلُّ وسطٌ بينهما: البوبُ يسأل الخادمَ عن خزائنِ المعتمِد وأرصدتِها، ثمّ
    يعرضها عليه، فيضغط «اعتماد وصرف» — أو «اعتماد فقط» إن أراد تأجيلَ الصرف.
    فلا خزينةَ مفترَضة، ولا صفحةَ ثانية.

    ومتى لا تُعرَض هذه الخطوة؟ إن أُطفئ مفتاحُ «الصرف من بوب الإشعار» في
    إعدادات السلف، أو لم يملك المعتمِدُ صلاحية «صرف السلف»، أو لا خزينةَ
    يُسمح له بالسحب منها — فيعتمد البوبُ وحده كما كان. والخادمُ هو من يقرّر
    ذلك (can_disburse)، لا هذا الملف.
*/
function fetchAdvanceDisbursementOptions(requestId) {
    const hrUrls = resolveHrAdvanceApprovalUrls();
    const template = hrUrls && hrUrls.disbursementOptions;

    if (!template) {
        return Promise.resolve(null);
    }

    return fetch(template.replace('__ID__', requestId), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then((response) => (response.ok ? response.json() : null))
        .catch(() => null);
}

function showAdvanceDisburseDialog(notification, notifications, index, options) {
    const treasuries = options.treasuries || [];
    const amount = Number(options.amount || 0);

    const optionsHtml = treasuries.map(function (t) {
        const selected = String(t.id) === String(options.default_treasury_id) ? ' selected' : '';
        const short = Number(t.balance) < amount ? ' !' : '';
        return '<option value="' + t.id + '" data-balance="' + t.balance + '"' + selected + '>'
            + t.name + ' (' + t.balance_label + ')' + short + '</option>';
    }).join('');

    Swal.fire({
        title: '<i class="fas fa-money-bill-wave" style="color:#28a745; margin-left:8px;"></i>اعتماد وصرف السلفة',
        html: '<div style="text-align:right; font-size:.95rem; line-height:1.9">'
            + '<div style="margin-bottom:.6rem">المبلغ: <strong>' + (options.amount_label || amount) + '</strong>'
            + (options.employee ? ' &mdash; الموظف: <strong>' + options.employee + '</strong>' : '') + '</div>'
            + '<label for="advTreasury" style="display:block; margin-bottom:.25rem">الخزينة التي تُصرف منها</label>'
            + '<select id="advTreasury" class="swal2-select" style="width:100%; margin:0">' + optionsHtml + '</select>'
            + '<div id="advTreasuryWarn" style="display:none; color:#dc3545; font-size:.8rem; margin-top:.4rem">'
            + '<i class="fas fa-exclamation-triangle"></i> رصيد الخزينة أقل من مبلغ السلفة &mdash; سيُرفض الصرف.</div>'
            + '<div style="color:#6c757d; font-size:.78rem; margin-top:.6rem">'
            + 'الرصيدُ المعروض للتنبيه؛ والقرارُ يُفحص من الدفتر عند الحفظ. و«اعتماد فقط» يعتمد الطلبَ ويؤجّل الصرف إلى شاشة الطلب.</div>'
            + '</div>',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="fas fa-check" style="margin-left:8px;"></i>اعتماد وصرف',
        confirmButtonColor: '#28a745',
        denyButtonText: '<i class="fas fa-clock" style="margin-left:8px;"></i>اعتماد فقط',
        denyButtonColor: '#7367F0',
        cancelButtonText: 'إلغاء',
        cancelButtonColor: '#6c757d',
        allowOutsideClick: false,
        didOpen: function () {
            const select = document.getElementById('advTreasury');
            const warn = document.getElementById('advTreasuryWarn');
            const sync = function () {
                const picked = select.options[select.selectedIndex];
                const balance = picked ? Number(picked.getAttribute('data-balance') || 0) : 0;
                warn.style.display = (picked && balance < amount) ? '' : 'none';
            };
            select.addEventListener('change', sync);
            sync();
        },
        preConfirm: function () {
            const select = document.getElementById('advTreasury');
            if (!select || !select.value) {
                Swal.showValidationMessage('اختر الخزينة التي تُصرف منها السلفة');
                return false;
            }
            return select.value;
        }
    }).then(function (result) {
        if (result.isConfirmed) {
            handleAdvanceRequestDecision(notification, 'approve', notifications, index, null, result.value);
            return;
        }
        if (result.isDenied) {
            handleAdvanceRequestDecision(notification, 'approve', notifications, index);
            return;
        }
        showClientNotificationsSequentially(notifications, index);
    });
}

function startAdvanceApproval(notification, notifications, index) {
    const requestId = extractAdvanceRequestId(notification);
    if (!requestId) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر تحديد رقم طلب السلفة' });
        return;
    }

    fetchAdvanceDisbursementOptions(requestId).then(function (options) {
        if (options && options.success === true && options.can_disburse && (options.treasuries || []).length) {
            showAdvanceDisburseDialog(notification, notifications, index, options);
            return;
        }

        handleAdvanceRequestDecision(notification, 'approve', notifications, index);
    });
}

function persistDismissedNotification(notificationId) {
    if (!notificationId) {
        return;
    }

    const normalizedId = String(notificationId);
    let deletedNotifications = JSON.parse(localStorage.getItem('deletedNotifications') || '[]').map(String);
    if (!deletedNotifications.includes(normalizedId)) {
        deletedNotifications.push(normalizedId);
        localStorage.setItem('deletedNotifications', JSON.stringify(deletedNotifications));
    }
}

function showAdvanceRejectReasonDialog(notification, notifications, index) {
    Swal.fire({
        title: '<i class="fas fa-times-circle" style="color:#dc3545; margin-left:8px;"></i>سبب الرفض',
        input: 'textarea',
        inputPlaceholder: 'اكتب سبب رفض طلب السلفة...',
        inputAttributes: {
            maxlength: 500
        },
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-paper-plane" style="margin-left: 8px;"></i>تأكيد الرفض',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#dc3545',
        preConfirm: (value) => {
            if (!value || !value.trim()) {
                Swal.showValidationMessage('يرجى إدخال سبب الرفض');
                return false;
            }
            return value.trim();
        }
    }).then((result) => {
        if (result.isConfirmed) {
            handleAdvanceRequestDecision(notification, 'reject', notifications, index, result.value);
        } else {
            showClientNotificationsSequentially(notifications, index);
        }
    });
}

function handleAdvanceRequestDecision(notification, decision, notifications, index, rejectionReason = null, treasuryId = null) {
    const requestId = extractAdvanceRequestId(notification);
    if (!requestId) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر تحديد رقم طلب السلفة' });
        return;
    }

    const hrUrls = resolveHrAdvanceApprovalUrls();
    const urlTemplate = decision === 'approve'
        ? (hrUrls && hrUrls.approve)
        : (hrUrls && hrUrls.reject);

    if (!urlTemplate) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'مسار اعتماد السلفة غير مهيأ. افتح صفحة اعتماد السلف من القائمة.',
            confirmButtonText: 'حسناً'
        });
        return;
    }

    const url = urlTemplate.replace('__ID__', requestId);

    let body;
    if (decision === 'approve') {
        body = { admin_notes: 'تمت الموافقة من إشعار الإدارة' };
        if (treasuryId) {
            // الاعتمادُ والصرفُ معاملةٌ واحدة في الخادم: فإن رُفض الصرفُ
            // (رصيدٌ لا يكفي، أو خزينةٌ لا يُسمح بالسحب منها) لم يُعتمد الطلبُ
            // أصلاً - فيبقى الإشعارُ ويختار المعتمِدُ خزينةً غيرَها أو «اعتماد فقط»
            body.disburse_now = 1;
            body.treasury_id = treasuryId;
        }
    } else {
        body = { rejection_reason: rejectionReason || 'تم الرفض من إشعار الإدارة' };
    }

    fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.csrfToken || ''
        },
        body: JSON.stringify(body)
    })
        .then(async (response) => {
            let data = null;
            try {
                data = await response.json();
            } catch (e) {
                data = null;
            }

            if (!response.ok) {
                const fallbackMessage = decision === 'approve'
                    ? 'تعذر الموافقة على الطلب'
                    : 'تعذر رفض الطلب';
                throw new Error((data && data.message) ? data.message : fallbackMessage);
            }

            if (!data || data.success !== true) {
                throw new Error((data && data.message) ? data.message : 'تم استلام رد غير صالح من الخادم');
            }

            return data;
        })
        .then((data) => {
            persistDismissedNotification(notification.id);
            if (typeof markNotificationAsRead === 'function') {
                markNotificationAsRead(notification.id);
            }
            const actionText = decision === 'approve' ? 'تمت الموافقة على الطلب' : 'تم رفض الطلب';
            Swal.fire({
                icon: 'success',
                title: 'تم',
                text: data.message || actionText,
                timer: 1200,
                showConfirmButton: false
            }).then(() => {
                setTimeout(() => location.reload(), 200);
            });
        })
        .catch((error) => {
            console.error('❌ خطأ في معالجة طلب السلفة:', error);
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: error.message || 'تعذر تنفيذ الإجراء، حاول مرة أخرى',
                confirmButtonText: 'حسناً'
            });
        });
}

/**
 * عرض نافذة الرد على الإشعار
 */
function showReplyDialogForNotification(notification, notifications, index) {
    Swal.fire({
        title: '<i class="fas fa-reply" style="color: #28a745; margin-left: 10px;"></i>الرد على الإشعار',
        html: `
            <div style="text-align: right; direction: rtl;">
                <div class="alert alert-info mb-3" style="background: rgba(115, 103, 240, 0.1); border-right: 4px solid #7367F0; padding: 15px; border-radius: 8px;">
                    <strong><i class="fas fa-bell" style="margin-left: 5px;"></i>الإشعار الأصلي:</strong> ${notification.title || 'بدون عنوان'}
                    <br><small style="color: #666;"><i class="fas fa-user" style="margin-left: 5px;"></i>من: ${notification.sender || 'النظام'}</small>
                </div>
                <textarea id="swal-reply-message" class="swal2-textarea" placeholder="اكتب ردك هنا..." style="text-align: right; direction: rtl; min-height: 120px;"></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-paper-plane" style="margin-left: 8px;"></i>إرسال الرد',
        cancelButtonText: '<i class="fas fa-times" style="margin-left: 8px;"></i>إلغاء',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        preConfirm: () => {
            const replyMessage = document.getElementById('swal-reply-message').value;
            if (!replyMessage.trim()) {
                Swal.showValidationMessage('يرجى كتابة رسالة الرد');
                return false;
            }
            return replyMessage;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // إرسال الرد
            if (typeof sendNotificationReply === 'function') {
                console.log('🔔 جاري إرسال الرد للإشعار:', notification.id);
                sendNotificationReply(notification.id, result.value).then((success) => {
                    console.log('🔔 نتيجة الرد:', success);
                    if (success) {
                        // بعد نجاح الرد، وضع علامة مقروء تلقائياً
                        console.log('✅ تم الرد بنجاح، وضع علامة مقروء...');
                        if (notification.id) {
                            markNotificationAsRead(notification.id);

                            // حفظ معرف الإشعار في localStorage لعدم عرضه مرة أخرى
                            let seenNotifications = JSON.parse(localStorage.getItem('seenNotifications') || '[]');
                            if (!seenNotifications.includes(notification.id)) {
                                seenNotifications.push(notification.id);
                            }
                            localStorage.setItem('seenNotifications', JSON.stringify(seenNotifications));

                            // تأكيد إضافي في deletedNotifications لضمان عدم الظهور عند الرجوع
                            let deletedNotifications = JSON.parse(localStorage.getItem('deletedNotifications') || '[]');
                            if (!deletedNotifications.includes(notification.id)) {
                                deletedNotifications.push(notification.id);
                            }
                            localStorage.setItem('deletedNotifications', JSON.stringify(deletedNotifications));

                            console.log('💾 تم حفظ الإشعار في localStorage (seen & deleted)');
                        }
                        // حذف البوب من DOM بشكل فوري مع تأخير صغير
                        setTimeout(() => {
                            console.log('🔔 حذف البوبأب من DOM وإخفاء الـ overlay');
                            const backdrop = document.querySelector('.swal2-container');
                            if (backdrop) {
                                backdrop.remove();
                            }
                            // إخفاء الـ overlay
                            if (typeof hideNotificationsOverlay === 'function') {
                                hideNotificationsOverlay();
                            }

                            // تحديث الصفحة دائماً بعد الرد بناءً على طلب العميل
                            console.log('� إعادة تحميل الصفحة لضمان تحديث البيانات...');
                            setTimeout(() => {
                                location.reload(true);
                            }, 500);
                        }, 300);
                    } else {
                        console.error('❌ فشل الرد، إعادة محاولة');
                        Swal.fire({
                            title: 'خطأ',
                            text: 'حدث خطأ في إرسال الرد. حاول مرة أخرى.',
                            icon: 'error',
                            confirmButtonText: 'حسناً'
                        });
                        showClientNotificationsSequentially(notifications, index);
                    }
                });
            } else {
                console.error('sendNotificationReply function not found');
                Swal.fire({
                    title: 'خطأ',
                    text: 'دالة الإرسال غير متاحة. تأكد من تحميل جميع السكريبتات.',
                    icon: 'error',
                    confirmButtonText: 'حسناً'
                });
                showClientNotificationsSequentially(notifications, index);
            }
        } else {
            // إذا ألغى الرد، نعود للإشعار الأصلي
            console.log('🔔 تم إلغاء الرد');
            showClientNotificationsSequentially(notifications, index);
        }
    });
}

function handleClientStatusRequestDecision(notification, decision, notifications, index) {
    if (!notification.client_id) {
        Swal.fire({
            title: 'خطأ',
            text: 'بيانات العميل غير موجودة',
            icon: 'error'
        }).then(() => {
            showClientNotificationsSequentially(notifications, index + 1);
        });
        return;
    }
    
    // وضع الإشعار كمقروء أولاً
    if (typeof markNotificationAsRead === 'function') {
        markNotificationAsRead(notification.id);
    }
    
    const actionText = decision === 'approve' ? 'اعتماد' : 'رفض';
    const url = decision === 'approve' 
        ? `/clients/clients_management/status-requests/client/${notification.client_id}/approve`
        : `/clients/clients_management/status-requests/client/${notification.client_id}/reject`;

    Swal.fire({
        title: 'جاري المعالجة...',
        text: `يرجى الانتظار بينما يتم ${actionText} الطلب`,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = window.csrfToken;
    form.appendChild(csrfInput);
    
    document.body.appendChild(form);
    form.submit();
}

// نفس handleClientStatusRequestDecision أعلاه بمسار طلبات الوضع
function handleClientStageRequestDecision(notification, decision, notifications, index) {
    if (!notification.client_id) {
        Swal.fire({
            title: 'خطأ',
            text: 'بيانات العميل غير موجودة',
            icon: 'error'
        }).then(() => {
            showClientNotificationsSequentially(notifications, index + 1);
        });
        return;
    }

    if (typeof markNotificationAsRead === 'function') {
        markNotificationAsRead(notification.id);
    }

    const actionText = decision === 'approve' ? 'اعتماد' : 'رفض';
    const url = decision === 'approve'
        ? `/clients/clients_management/stage-requests/client/${notification.client_id}/approve`
        : `/clients/clients_management/stage-requests/client/${notification.client_id}/reject`;

    Swal.fire({
        title: 'جاري المعالجة...',
        text: `يرجى الانتظار بينما يتم ${actionText} الطلب`,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = window.csrfToken;
    form.appendChild(csrfInput);

    document.body.appendChild(form);
    form.submit();
}

// دالة markNotificationAsRead معرفة في header.blade.php

window.showTestNotification = function () {
    const testNotifications = [
        {
            id: 1,
            title: 'تحذير مهم',
            description: 'هذا إشعار اختباري لاختبار النظام الجديد مع Font Awesome',
            sender: 'إدارة النظام',
            created_at: new Date().toLocaleString('ar-SA')
        },
        {
            id: 2,
            title: 'تنبيه عاجل',
            description: 'هذا إشعار ثاني يظهر بعد الأول مع تأثيرات احترافية',
            sender: 'المدير العام',
            created_at: new Date().toLocaleString('ar-SA')
        }
    ];

    showClientNotificationsSequentially(testNotifications);
};

console.log('✅ نظام الإشعارات جاهز [' + (window.NOTIFICATION_SYSTEM_BUILD || 'legacy') + '] - اكتب: window.showTestNotification()');
