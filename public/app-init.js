/**
 * App Initialization - تهيئة التطبيق الرئيسي
 * يدير تهيئة جميع المديرين والأنظمة
 */

// المتغيرات العامة
window.mapManager = null;
window.searchManager = null;
window.hiddenClientsManager = null;
window.paginationManager = null;

// تهيئة Google Maps
window.initMap = function() {
    if (window.mapManager) {
        window.mapManager.initializeMap();
    }
};

// معالج أخطاء Google Maps
window.gm_authFailure = function() {
    console.error('❌ خطأ في مصادقة Google Maps API');
    if (window.mapManager) {
        window.mapManager.showMapError();
    }
};

// تهيئة التطبيق عند تحميل DOM
$(document).ready(function() {
    initializeApp();
});

/**
 * تهيئة التطبيق الرئيسي
 */
function initializeApp() {
    console.log('🚀 بدء تهيئة تطبيق إدارة العملاء');

    try {
        // تهيئة المديرين
        initializeManagers();

        // ربط الأحداث العامة
        bindGlobalEvents();

        // تحميل Google Maps
        loadGoogleMaps();

        // دعم زر الرجوع في المتصفح
        setupBrowserHistory();

        console.log('✅ تم تهيئة التطبيق بنجاح');

    } catch (error) {
        console.error('❌ خطأ في تهيئة التطبيق:', error);
        showGlobalError('حدث خطأ في تهيئة التطبيق. يرجى إعادة تحميل الصفحة.');
    }
}

/**
 * تهيئة المديرين
 */
function initializeManagers() {
    // تهيئة مدير البحث
    window.searchManager = new SearchManager();
    window.searchManager.init();

    // تهيئة مدير الخريطة
    window.mapManager = new MapManager();
    window.mapManager.init();

    // تهيئة مدير العملاء المخفيين
    window.hiddenClientsManager = new HiddenClientsManager();
    window.hiddenClientsManager.init();

    // تهيئة مدير الترقيم
    window.paginationManager = new PaginationManager();
    window.paginationManager.init();
}

/**
 * ربط الأحداث العامة
 */
function bindGlobalEvents() {
    // معالج الأخطاء العام
    window.addEventListener('error', handleGlobalError);

    // معالج تغيير حجم الشاشة
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleWindowResize, 250);
    });

    // تنظيف الذاكرة عند مغادرة الصفحة
    window.addEventListener('beforeunload', cleanup);

    // معالج الترقيم
    $(document).on('click', '.pagination-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && window.paginationManager && !window.searchManager.getIsLoading()) {
            window.paginationManager.goToPage(page);
        }
    });

    // تصدير Excel
    $('#exportExcelBtn').on('click', handleExcelExport);

    // دوال UI المساعدة
    window.toggleSearchFields = function(button) {
        if (window.searchManager) {
            window.searchManager.toggleSearchFields(button);
        }
    };

    window.toggleSearchText = function(button) {
        if (window.searchManager) {
            window.searchManager.toggleSearchText(button);
        }
    };

    window.openMap = function(lat, lng) {
        if (window.mapManager) {
            window.mapManager.openDirections(lat, lng);
        }
    };

    window.retryLoadMap = function() {
        if (window.mapManager) {
            window.mapManager.retryLoadMap();
        }
    };
}

/**
 * تحميل Google Maps
 */
function loadGoogleMaps() {
    // التحقق من وجود مفتاح API
    if (!window.googleMapsApiKey) {
        console.error('❌ مفتاح Google Maps API غير متوفر');
        return;
    }

    console.log('Google Maps API Key:', window.googleMapsApiKey);

    // التحقق من عدم وجود سكريبت محمل مسبقاً
    if (document.querySelector('script[src*="maps.googleapis.com"]')) {
        console.log('📍 Google Maps محمل مسبقاً');
        return;
    }

    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${window.googleMapsApiKey}&libraries=places&callback=window.initMap`;
    script.async = true;
    script.defer = true;

    script.onerror = function() {
        console.error('❌ فشل في تحميل Google Maps');
        if (window.mapManager) {
            window.mapManager.showMapError();
        }
    };

    script.onload = function() {
        console.log('✅ تم تحميل Google Maps بنجاح');
    };

    document.head.appendChild(script);
}

/**
 * دعم زر الرجوع في المتصفح
 */
function setupBrowserHistory() {
    window.addEventListener('popstate', function() {
        if (window.searchManager) {
            window.searchManager.updateCurrentFilters();
            window.searchManager.performSearch();
        }
    });
}

/**
 * معالج تغيير حجم الشاشة
 */
function handleWindowResize() {
    // تحديث حالة الكارد حسب حالة الخريطة
    const isMapOpen = localStorage.getItem('mapOpen') === 'true';
    const actionCard = $('#actionCard');

    if (isMapOpen) {
        actionCard.addClass('map-open').removeClass('map-closed');
    } else {
        actionCard.addClass('map-closed').removeClass('map-open');
    }

    // إعلام مدير الخريطة بتغيير الحجم
    if (window.mapManager && window.mapManager.getIsMapLoaded()) {
        window.mapManager.handleResize();
    }
}

/**
 * معالج الأخطاء العام
 */
function handleGlobalError(event) {
    console.error('خطأ عام:', event.error);

    // معالجة خاصة لأخطاء Google Maps
    if (event.error && event.error.message &&
        (event.error.message.includes('google') || event.error.message.includes('map'))) {
        setTimeout(() => {
            if (window.mapManager && !window.mapManager.getIsMapLoaded() && typeof window.initMap === 'function') {
                window.initMap();
            }
        }, 2000);
    }
}

/**
 * معالج تصدير Excel
 */
function handleExcelExport() {
    const button = $(this);
    const originalHtml = button.html();

    // إظهار مؤشر التحميل
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> جاري التصدير...');

    // محاكاة عملية التصدير (يمكن استبدالها بالتنفيذ الفعلي)
    setTimeout(() => {
        button.prop('disabled', false).html(originalHtml);

        // إظهار رسالة نجاح
        if (window.hiddenClientsManager) {
            window.hiddenClientsManager.showNotification('تم تصدير البيانات بنجاح', 'success');
        } else {
            alert('تم تصدير البيانات بنجاح');
        }
    }, 2000);
}

/**
 * إظهار خطأ عام
 */
function showGlobalError(message) {
    const errorDiv = $(`
        <div class="alert alert-danger alert-dismissible fade show position-fixed"
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas fa-exclamation-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);

    $('body').append(errorDiv);

    // إزالة تلقائية بعد 10 ثواني
    setTimeout(() => {
        errorDiv.alert('close');
    }, 10000);
}

/**
 * تنظيف الموارد
 */
function cleanup() {
    console.log('🧹 تنظيف موارد التطبيق');

    if (window.searchManager) {
        window.searchManager.cleanup();
    }

    if (window.mapManager) {
        window.mapManager.cleanup();
    }

    if (window.hiddenClientsManager) {
        window.hiddenClientsManager.cleanup();
    }

    if (window.paginationManager) {
        window.paginationManager.cleanup();
    }

    // مسح المؤقتات
    if (window.searchTimeout) {
        clearTimeout(window.searchTimeout);
    }
}

/**
 * إعادة تهيئة التطبيق (للاستخدام عند الحاجة)
 */
window.reinitializeApp = function() {
    cleanup();
    setTimeout(() => {
        initializeApp();
    }, 100);
};

/**
 * فحص حالة التطبيق
 */
window.checkAppStatus = function() {
    const status = {
        searchManager: !!window.searchManager,
        mapManager: !!window.mapManager,
        hiddenClientsManager: !!window.hiddenClientsManager,
        paginationManager: !!window.paginationManager,
        mapLoaded: window.mapManager ? window.mapManager.getIsMapLoaded() : false,
        hiddenClientsCount: window.hiddenClientsManager ? window.hiddenClientsManager.getHiddenClients().length : 0
    };

    console.log('📊 حالة التطبيق:', status);
    return status;
};

// تصدير الدوال للاستخدام العام
window.initializeApp = initializeApp;
window.cleanup = cleanup;
/**
 * حفظ حالة الخريطة
 */
saveMapState() {
    if (!this.map || !this.isMapLoaded) return;

    const mapState = {
        center: {
            lat: this.map.getCenter().lat(),
            lng: this.map.getCenter().lng()
        },
        zoom: this.map.getZoom(),
        clients: this.allMarkers.map(item => ({
            clientData: item.clientData,
            visible: item.marker.getVisible()
        })),
        isMapOpen: localStorage.getItem('mapOpen') === 'true',
        timestamp: Date.now()
    };

    sessionStorage.setItem('mapState', JSON.stringify(mapState));
    console.log('💾 تم حفظ حالة الخريطة:', mapState);
}

/**
 * استعادة حالة الخريطة
 */
restoreMapState() {
    const savedState = sessionStorage.getItem('mapState');

    if (!savedState) {
        console.log('❌ لا توجد حالة محفوظة');
        return false;
    }

    try {
        const mapState = JSON.parse(savedState);

        // التحقق من أن الحالة المحفوظة ليست قديمة جداً (أقل من 30 دقيقة)
        const thirtyMinutes = 30 * 60 * 1000;
        if (Date.now() - mapState.timestamp > thirtyMinutes) {
            console.log('⏰ الحالة المحفوظة قديمة، سيتم تجاهلها');
            sessionStorage.removeItem('mapState');
            return false;
        }

        // استعادة حالة الخريطة (مفتوحة/مغلقة)
        if (mapState.isMapOpen) {
            localStorage.setItem('mapOpen', 'true');
            const toggleButton = $('#toggleMapButton');
            const mapContainer = $('#mapContainer');
            const actionCard = $('#actionCard');

            // فتح الخريطة
            actionCard.addClass('map-open').removeClass('map-closed');
            setTimeout(() => mapContainer.removeClass('map-hidden').addClass('map-show'), 200);

            toggleButton.addClass('map-active')
                .attr('data-tooltip', 'إخفاء الخريطة')
                .attr('title', 'إخفاء الخريطة')
                .find('i').removeClass('fa-map-marked-alt').addClass('fa-map');

            // تحميل الخريطة إذا لم تكن محملة
            if (!this.isMapLoaded) {
                this.loadGoogleMaps();

                // الانتظار حتى تحميل الخريطة ثم استعادة البيانات
                const checkMapLoaded = setInterval(() => {
                    if (this.isMapLoaded && this.map) {
                        clearInterval(checkMapLoaded);
                        this.restoreMapData(mapState);
                    }
                }, 500);

                // timeout بعد 10 ثواني
                setTimeout(() => clearInterval(checkMapLoaded), 10000);
            } else {
                // الخريطة محملة، استعادة البيانات مباشرة
                this.restoreMapData(mapState);
            }
        }

        console.log('✅ تم استعادة حالة الخريطة');
        return true;

    } catch (error) {
        console.error('❌ خطأ في استعادة حالة الخريطة:', error);
        sessionStorage.removeItem('mapState');
        return false;
    }
}

/**
 * استعادة بيانات الخريطة
 */
restoreMapData(mapState) {
    if (!this.map || !this.isMapLoaded) return;

    // مسح العلامات الحالية
    this.clearMapMarkers();

    // استعادة الموقع والزوم
    if (mapState.center) {
        this.map.setCenter(mapState.center);
        this.map.setZoom(mapState.zoom || 11);
    }

    // استعادة العملاء
    if (mapState.clients && mapState.clients.length > 0) {
        const clientsData = mapState.clients.map(item => item.clientData);
        this.updateMapMarkers(clientsData);

        // استعادة حالة الرؤية لكل عميل
        setTimeout(() => {
            mapState.clients.forEach(savedClient => {
                const markerItem = this.allMarkers.find(item =>
                    item.clientData && item.clientData.id === savedClient.clientData.id
                );

                if (markerItem && markerItem.marker) {
                    markerItem.marker.setVisible(savedClient.visible);
                }
            });

            // تحديث MarkerClusterer
            if (this.markerCluster) {
                const visibleMarkers = this.allMarkers
                    .filter(item => item.marker.getVisible())
                    .map(item => item.marker);

                this.markerCluster.clearMarkers();
                if (visibleMarkers.length > 0) {
                    this.markerCluster.addMarkers(visibleMarkers);
                }
            }
        }, 500);
    }

    console.log('✅ تم استعادة بيانات الخريطة');
}

/**
 * مسح حالة الخريطة المحفوظة
 */
clearMapState() {
    sessionStorage.removeItem('mapState');
    console.log('🗑️ تم مسح حالة الخريطة المحفوظة');
}
$(document).ready(function() {
    // محاولة استعادة حالة الخريطة أولاً
    const shouldRestoreMap = sessionStorage.getItem('mapState') !== null;

    initializeApp();

    // استعادة حالة الخريطة بعد التهيئة
    if (shouldRestoreMap && window.mapManager) {
        setTimeout(() => {
            window.mapManager.restoreMapState();
        }, 1000);
    }
});

// حفظ حالة الخريطة قبل مغادرة الصفحة
window.addEventListener('beforeunload', function() {
    if (window.mapManager && window.mapManager.getIsMapLoaded()) {
        window.mapManager.saveMapState();
    }
});

// حفظ حالة الخريطة عند الضغط على أي رابط داخلي
$(document).on('click', 'a[href]:not([target="_blank"]):not([href^="#"]):not([href^="tel:"]):not([href^="mailto:"])', function(e) {
    const href = $(this).attr('href');

    // تحقق من أن الرابط داخلي (نفس الدومين)
    if (href && !href.startsWith('http') && window.mapManager && window.mapManager.getIsMapLoaded()) {
        window.mapManager.saveMapState();
    }
});
