/**
 * Hidden Clients Manager - مدير العملاء المخفيين
 * يدير عمليات إخفاء وإظهار العملاء من الخريطة
 */

class HiddenClientsManager {
    constructor() {
        this.hiddenClients = [];
    }

    /**
     * بداية اليوم الحالي (منتصف الليل محلياً)
     */
    getTodayStart() {
        const start = new Date();
        start.setHours(0, 0, 0, 0);
        return start;
    }

    /**
     * هل تم الإخفاء من بداية اليوم الحالي؟
     */
    isHiddenToday(hiddenAt) {
        if (!hiddenAt) {
            return false;
        }

        const parsed = new Date(String(hiddenAt).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) {
            return false;
        }

        return parsed >= this.getTodayStart();
    }

    /**
     * العملاء المخفيون الفعّالون اليوم فقط (للعرض والإخفاء من الواجهة)
     */
    getActiveHiddenClients() {
        return this.hiddenClients.filter((client) => this.isHiddenToday(client.hidden_at));
    }

    /**
     * تهيئة مدير العملاء المخفيين
     */
    init() {
        this.bindEvents();
        // تحميل القائمة فقط في صفحة الفهرس
        if (window.clientRoutes && window.clientRoutes.getHiddenClients) {
            this.loadHiddenClients();
        }
    }

    /**
     * ربط الأحداث
     */
    bindEvents() {
        // عند الضغط على زر الإخفاء في الكارد
        $(document).on('click', '.hide-from-map-btn, .hide-from-map-link', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const clientId = $(e.currentTarget).data('client-id');
            const clientName = $(e.currentTarget).data('client-name');

            this.hideClient(clientId, clientName);
        });

        // عند الضغط على زر الإظهار في الكونتينر
        $(document).on('click', '.show-client-btn', (e) => {
            const clientId = $(e.currentTarget).data('client-id');
            this.showClient(clientId);
        });

        // تحديث أيقونة السهم عند فتح/إغلاق الكونتينر
        $('#hiddenClientsCollapse').on('shown.bs.collapse', () => {
            $('#toggleIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        });

        $('#hiddenClientsCollapse').on('hidden.bs.collapse', () => {
            $('#toggleIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });

        // من الخريطة: حفظ الحالة وفتح العميل في تبويب جديد
        $(document).on('mousedown', 'a[href*="from=map"], a[href*="/show/client/"], a[href*="register-visit"]', (e) => {
            if (!this.isMapActive()) return;

            const link = $(e.currentTarget);
            if (link.hasClass('hide-from-map-link') || link.hasClass('delete-client')) return;

            const href = link.attr('href');
            if (!href || href === '#' || href.startsWith('tel:') || href.startsWith('mailto:')) return;

            this.saveMapStateBeforeNavigate();

            if (!link.attr('target')) {
                link.attr('target', '_blank');
                link.attr('rel', 'noopener noreferrer');
            }
        });

        // تحديث النظام عند تحديث الصفحة بـ AJAX
        $(document).ajaxComplete((event, xhr, settings) => {
            // التحقق من أن الطلب يخص صفحة العملاء
            if (settings.url && settings.url.includes('clients') &&
                xhr.responseJSON && xhr.responseJSON.html &&
                !settings.url.includes('hide-from-map') &&
                !settings.url.includes('show-in-map')) {

                // إعادة إخفاء العملاء المخفية بعد تحديث AJAX
                setTimeout(() => {
                    this.getActiveHiddenClients().forEach(client => {
                        $(`.client-card[data-client-id="${client.id}"]`).addClass('hidden').hide();
                        this.hideClientFromMap(client.id);
                    });
                }, 200);
            }
        });
    }

    /**
     * هل الخريطة مفتوحة حالياً؟
     */
    isMapActive() {
        if (localStorage.getItem('mapOpen') === 'true') return true;
        const mapContainer = document.getElementById('mapContainer');
        if (mapContainer && mapContainer.classList.contains('map-show')) return true;
        return !!(window.mapManager && window.mapManager.getIsMapLoaded && window.mapManager.getIsMapLoaded());
    }

    /**
     * حفظ حالة الخريطة قبل الانتقال لصفحة العميل
     */
    saveMapStateBeforeNavigate() {
        if (window.mapManager && typeof window.mapManager.saveMapState === 'function') {
            window.mapManager.saveMapState();
        }
        localStorage.setItem('mapOpen', 'true');
    }

    /**
     * فتح رابط العميل في تبويب جديد (الخريطة تبقى في التبويب الحالي)
     */
    openClientUrl(url) {
        if (!url) return;

        this.saveMapStateBeforeNavigate();

        const absoluteUrl = url.startsWith('http')
            ? url
            : new URL(url, window.location.origin).href;

        const opened = window.open(absoluteUrl, '_blank', 'noopener,noreferrer');
        if (!opened) {
            this.showNotification('يرجى السماح بالنوافذ المنبثقة لفتح صفحة العميل', 'warning');
        }
    }

    /**
     * فتح صفحة عميل من الخريطة في تبويب جديد
     */
    buildClientShowUrl(clientId) {
        if (!clientId || !window.clientRoutes?.show) return null;

        const tpl = window.clientRoutes.show;
        const url = tpl.includes('CLIENT_ID')
            ? tpl.replace('CLIENT_ID', clientId)
            : `${tpl.replace(/\/$/, '')}/${clientId}`;

        return url.includes('?') ? `${url}&from=map` : `${url}?from=map`;
    }

    openClientFromMap(clientId) {
        const url = this.buildClientShowUrl(clientId);
        if (!url) return;
        this.openClientUrl(url);
    }

    /**
     * تجهيز العودة لصفحة العملاء: خريطة مفتوحة + ملء شاشة + التركيز على موقع العميل
     */
    prepareReturnToClientsMap(clientId, coords = {}) {
        localStorage.setItem('mapOpen', 'true');
        sessionStorage.setItem('returnToClientsMap', '1');

        let lat = coords.lat ?? window.currentClient?.lat ?? null;
        let lng = coords.lng ?? window.currentClient?.lng ?? null;

        // احتياطي: إحداثيات العميل من حالة الخريطة المحفوظة سابقاً
        if ((lat === null || lat === '' || lng === null || lng === '') && clientId) {
            try {
                const saved = sessionStorage.getItem('mapState');
                if (saved) {
                    const mapState = JSON.parse(saved);
                    const entry = (mapState.clients || []).find(
                        (c) => c.clientData && String(c.clientData.id) === String(clientId)
                    );
                    if (entry?.clientData) {
                        lat = lat || entry.clientData.lat;
                        lng = lng || entry.clientData.lng;
                    }
                }
            } catch (e) { /* ignore */ }
        }

        const latNum = lat !== null && lat !== '' ? parseFloat(lat) : null;
        const lngNum = lng !== null && lng !== '' ? parseFloat(lng) : null;
        const hasCoords = latNum !== null && lngNum !== null && !isNaN(latNum) && !isNaN(lngNum)
            && latNum !== 0 && lngNum !== 0;

        const focusPayload = {
            clientId: clientId,
            lat: hasCoords ? latNum : null,
            lng: hasCoords ? lngNum : null,
            name: coords.name || window.currentClient?.name || '',
            isFullscreen: true,
            zoom: 17,
            timestamp: Date.now()
        };
        sessionStorage.setItem('pendingMapFocus', JSON.stringify(focusPayload));

        try {
            let mapState = {};
            const saved = sessionStorage.getItem('mapState');
            if (saved) {
                mapState = JSON.parse(saved);
            }
            mapState.isMapOpen = true;
            mapState.isFullscreen = true;
            mapState.timestamp = Date.now();
            if (hasCoords) {
                mapState.center = { lat: latNum, lng: lngNum };
                mapState.zoom = 17;
                mapState.focusClient = focusPayload;
            }
            sessionStorage.setItem('mapState', JSON.stringify(mapState));
        } catch (e) {
            sessionStorage.setItem('mapState', JSON.stringify({
                isMapOpen: true,
                isFullscreen: true,
                center: hasCoords ? { lat: latNum, lng: lngNum } : null,
                zoom: 17,
                focusClient: focusPayload,
                timestamp: Date.now()
            }));
        }
    }

    /**
     * رابط العودة لصفحة الفهرس مع تفعيل استعادة الخريطة
     */
    getIndexReturnUrl() {
        const base = window.clientRoutes.index;
        const separator = base.includes('?') ? '&' : '?';
        return base + separator + 'restore_map=1';
    }

    /**
     * هل يجب العودة لصفحة الفهرس بعد الإخفاء؟
     */
    shouldRedirectToIndex(clientId) {
        const btn = $(`.hide-from-map-btn[data-client-id="${clientId}"], .hide-from-map-link[data-client-id="${clientId}"]`);

        if (btn.length) {
            const flag = btn.attr('data-redirect-to-index') ?? btn.data('redirectToIndex');
            if (flag === '1' || flag === 1 || flag === true) {
                return true;
            }
        }

        // صفحة عرض العميل: لا يوجد كارد عميل + مسار صفحة العرض
        const onShowPage = !$(`.client-card[data-client-id="${clientId}"]`).length
            && window.location.pathname.includes('/show/client/');

        return onShowPage;
    }

    /**
     * إخفاء عميل
     */
    hideClient(clientId, clientName) {
        // تحضير URL
        const url = window.clientRoutes.hideFromMap.replace(':id', clientId);

        // إرسال طلب للخادم
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: window.csrfToken
            },
            beforeSend: () => {
                // إظهار مؤشر التحميل على الزر
                $(`.hide-from-map-btn[data-client-id="${clientId}"]`)
                    .prop('disabled', true)
                    .addClass('loading')
                    .html('<i class="fas fa-spinner fa-spin me-1"></i> جاري الإخفاء...');
            },
            success: (response) => {
                if (response.success) {
                    const redirectToIndex = this.shouldRedirectToIndex(clientId);

                    // من صفحة عرض العميل: العودة فوراً لصفحة الفهرس مع استعادة الخريطة
                    if (redirectToIndex && window.clientRoutes && window.clientRoutes.index) {
                        this.prepareReturnToClientsMap(clientId, {
                            lat: response.client_lat,
                            lng: response.client_lng,
                            name: clientName
                        });
                        window.location.replace(this.getIndexReturnUrl());
                        return;
                    }

                    // إضافة العميل للقائمة المحلية (صفحة الفهرس)
                    const client = {
                        id: clientId,
                        name: clientName,
                        code: this.getClientCode(clientId),
                        hidden_at: this.formatDateTime(new Date()),
                        expires_at: this.formatDateTime(this.getEndOfToday())
                    };

                    this.hiddenClients.push(client);

                    const clientCard = $(`.client-card[data-client-id="${clientId}"]`);
                    if (clientCard.length) {
                        clientCard.addClass('hidden').fadeOut(300);
                    }

                    this.hideClientFromMap(clientId);

                    if ($('#hiddenClientsContainer').length) {
                        this.updateDisplay();
                    }

                    this.showNotification(`تم تسجيل الانصراف وإخفاء العميل: ${clientName} حتى نهاية اليوم`, 'success');

                    if ($('#hiddenClientsCollapse').length && !$('#hiddenClientsCollapse').hasClass('show')) {
                        $('#hiddenClientsCollapse').collapse('show');
                    }
                } else {
                    this.showNotification(response.message || 'حدث خطأ في إخفاء العميل', 'error');
                }
            },
            error: (xhr) => {
                let errorMessage = 'حدث خطأ في الاتصال بالخادم';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                this.showNotification(errorMessage, 'error');
            },
            complete: () => {
                // إعادة تفعيل الزر
                $(`.hide-from-map-btn[data-client-id="${clientId}"]`)
                    .prop('disabled', false)
                    .removeClass('loading')
                    .html('<i class="fas fa-eye-slash me-1"></i> إخفاء حتى نهاية اليوم');
            }
        });
    }

    /**
     * إظهار عميل
     */
    showClient(clientId) {
        // تحضير URL
        const url = window.clientRoutes.showInMap.replace(':id', clientId);

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: window.csrfToken
            },
            beforeSend: () => {
                // إظهار مؤشر التحميل على الزر
                $(`.show-client-btn[data-client-id="${clientId}"]`)
                    .prop('disabled', true)
                    .addClass('loading')
                    .html('<i class="fas fa-spinner fa-spin me-1"></i> جاري الإظهار...');
            },
            success: (response) => {
                if (response.success) {
                    // إظهار الإشعار
                    this.showNotification(`تم إظهار العميل: ${response.client_name}`, 'success');

                    // ✅ إعادة تحميل الصفحة بعد ثانية ليظهر العميل في القائمة والخريطة
                    setTimeout(() => {
                        
                        location.reload();
                    }, 800);
                } else {
                    this.showNotification(response.message || 'حدث خطأ في إظهار العميل', 'error');
                }
            },
            error: (xhr) => {
                let errorMessage = 'حدث خطأ في الاتصال بالخادم';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                this.showNotification(errorMessage, 'error');
            },
            complete: () => {
                // إعادة تفعيل الزر
                $(`.show-client-btn[data-client-id="${clientId}"]`)
                    .prop('disabled', false)
                    .removeClass('loading')
                    .html('<i class="fas fa-eye me-1"></i> إظهار');
            }
        });
    }

    /**
     * ✅ تحديث زر الـ Dropdown بعد الإظهار
     * تحويل زر "عرض" إلى "تسجيل زيارة" (نفس التسمية الموحَّدة في
     * client_cards.blade.php — كان هنا "تسجيل زيارة وعرض" القديمة).
     *
     * الشرط يأتي من الخادم وحده (`window.clientMustVisitBeforeView`) وهو
     * نفس تركيبة `$mustVisit` في client_cards.blade.php: صلاحية الإلزام
     * `clients_must_visit_before_view` ما لم يُعفَ منها بـ
     * `clients_exempt_visit_before_view`.
     *
     * لا فحصَ دورٍ هنا إطلاقاً: كان `userRole === 'employee'` يحوّل رابط كلّ
     * موظّفٍ إلى «تسجيل زيارة» ولو لم يكن مُلزَماً أو كان مُعفى، ويترك رابط
     * غير الموظّف الملزَم على «عرض» فيصطدم بإعادة توجيه المتحكّم. الدور لا
     * يقرّر شيئاً — الصلاحية وحدها. وإن غاب المتغيّر (صفحةٌ لا تمرّره) لم
     * يُعَد كتابةُ الرابط: `clients.show` صحيحٌ لغير الملزَم، والملزَمُ
     * يعيده الخادم إلى الزيارة على أي حال.
     */
    updateClientDropdownButton(clientId) {
        const dropdown = $(`.client-card[data-client-id="${clientId}"]`).find('.dropdown-menu');
        const firstMenuItem = dropdown.find('li:first-child a.dropdown-item');

        if (firstMenuItem.length && window.clientMustVisitBeforeView === true) {
            const registerVisitRoute = window.clientRoutes && window.clientRoutes.registerVisit;

            if (!registerVisitRoute) {
                return;
            }

            firstMenuItem
                .attr('href', registerVisitRoute.replace(':id', clientId))
                .html('<i class="fas fa-walking me-1"></i> تسجيل زيارة');
        }
    }


    /**
     * إخفاء العميل من الخريطة
     */
    hideClientFromMap(clientId) {
        if (window.mapManager && window.mapManager.getIsMapLoaded()) {
            window.mapManager.hideClientFromMap(clientId);
        }
    }

    /**
     * إظهار العميل في الخريطة
     */
    showClientInMap(clientId) {
        if (window.mapManager && window.mapManager.getIsMapLoaded()) {
            window.mapManager.showClientInMap(clientId);
        }
    }

    /**
     * تحميل العملاء المخفيين من الخادم
     */
    loadHiddenClients() {
        $.ajax({
            url: window.clientRoutes.getHiddenClients,
            type: 'GET',
            success: (response) => {
                if (response.success) {
                    this.hiddenClients = response.hidden_clients || [];
                    this.updateDisplay();

                    this.getActiveHiddenClients().forEach(client => {
                        $(`.client-card[data-client-id="${client.id}"]`).addClass('hidden').hide();
                        this.hideClientFromMap(client.id);
                    });
                }
            },
            error: () => {
                console.log('فشل في تحميل العملاء المخفيين');
            }
        });
    }

    /**
     * تحديث عرض الكونتينر
     */
    updateDisplay() {
        const container = $('#hiddenClientsContainer');
        const list = $('#hiddenClientsList');
        const badge = $('#hiddenCountBadge');
        const activeHidden = this.getActiveHiddenClients();

        if (activeHidden.length === 0) {
            container.removeClass('show').addClass('hide').hide();
            badge.hide();
            list.html(`
                <div class="text-center text-muted py-3" id="emptyMessage">
                    <i class="fas fa-eye fa-2x mb-2"></i>
                    <div>لا يوجد عملاء مخفيين حالياً</div>
                </div>
            `);
        } else {
            container.removeClass('hide').addClass('show').show();
            badge.text(activeHidden.length).show();

            const html = activeHidden.map(client => `
                <div class="hidden-client-item">
                    <div class="client-info flex-grow-1">
                        <h6 class="fw-bold text-primary mb-1">${client.name}</h6>
                        <div class="client-meta mb-1">
                            <i class="fas fa-barcode me-1"></i>
                            كود: ${client.code || '---'}
                        </div>
                        <div class="client-meta">
                            <i class="fas fa-clock me-1"></i>
                            تم الإخفاء: ${client.hidden_at}
                        </div>
                    </div>
                    <div>
                        <button class="show-client-btn" data-client-id="${client.id}">
                            <i class="fas fa-eye me-1"></i>
                            إظهار
                        </button>
                    </div>
                </div>
            `).join('');

            list.html(html);
        }
    }

    /**
     * الحصول على كود العميل من الكارد
     */
    getClientCode(clientId) {
        const card = $(`.client-card[data-client-id="${clientId}"]`);
        const codeElement = card.find('.client-code-badge');
        return codeElement.length > 0 ? codeElement.text().trim() : '---';
    }

    /**
     * إظهار الإشعارات
     */
    showNotification(message, type = 'info') {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        };

        const iconClass = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };

        const notification = $(`
            <div class="alert ${alertClass[type]} alert-dismissible fade show notification" role="alert"
                 style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <i class="fas ${iconClass[type]} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);

        $('body').append(notification);

        // إزالة الإشعار تلقائياً بعد 5 ثواني
        setTimeout(() => {
            notification.alert('close');
        }, 5000);
    }

    /**
     * الحصول على العملاء المخفيين
     */
    getHiddenClients() {
        return this.getActiveHiddenClients();
    }

    /**
     * التحقق من كون العميل مخفي اليوم
     */
    isClientHidden(clientId) {
        return this.getActiveHiddenClients().some(client => client.id == clientId);
    }

    getEndOfToday() {
        const end = new Date();
        end.setHours(23, 59, 59, 999);
        return end;
    }

    formatDateTime(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    /**
     * تنظيف الموارد
     */
    cleanup() {
        this.hiddenClients = [];
    }
}

// تصدير الكلاس
window.HiddenClientsManager = HiddenClientsManager;

// دالة عامة لاستدعائها من نافذة معلومات الخريطة
window.openClientFromMap = function (clientId) {
    if (window.hiddenClientsManager) {
        window.hiddenClientsManager.openClientFromMap(clientId);
        return;
    }
    const tpl = window.clientRoutes?.show;
    const url = tpl
        ? (tpl.includes('CLIENT_ID')
            ? tpl.replace('CLIENT_ID', clientId)
            : `${tpl.replace(/\/$/, '')}/${clientId}`) + (tpl.includes('?') ? '&from=map' : '?from=map')
        : null;
    if (url) {
        if (window.mapManager?.saveMapState) window.mapManager.saveMapState();
        window.open(url, '_blank', 'noopener,noreferrer');
    }
};