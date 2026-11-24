/**
 * State Manager - مدير حالة التطبيق
 * يدير حفظ واستعادة حالة التطبيق، خاصة الخريطة والعملاء
 * Version: 1.0.0
 */

class StateManager {
    constructor() {
        this.STATE_KEY = 'clientsMapState';
        this.CLIENTS_CACHE_KEY = 'clientsDataCache';
        this.MAP_FILTERS_KEY = 'mapFiltersState';
        this.LAST_MAP_STATE = 'lastMapState';
    }

    /**
     * تهيئة مدير الحالة
     */
    init() {
        console.log('🔄 تهيئة State Manager...');
        this.setupPageVisibilityHandler();
        this.setupBeforeUnloadHandler();
        this.restoreStateOnPageLoad();
    }

    /**
     * حفظ حالة الخريطة والعملاء
     */
    saveMapState() {
        try {
            const state = {
                timestamp: new Date().getTime(),
                mapOpen: localStorage.getItem('mapOpen') === 'true',
                mapCenter: this.getMapCenter(),
                mapZoom: this.getMapZoom(),
                filters: this.getCurrentFilters(),
                currentPage: this.getCurrentPage(),
                searchQuery: this.getSearchQuery(),
                hiddenClients: this.getHiddenClientsData()
            };

            sessionStorage.setItem(this.STATE_KEY, JSON.stringify(state));
            console.log('💾 تم حفظ حالة الخريطة');

            return state;
        } catch (error) {
            console.error('❌ خطأ في حفظ حالة الخريطة:', error);
            return null;
        }
    }

    /**
     * حفظ بيانات العملاء في الكاش
     */
    cacheClientsData(clientsData) {
        try {
            const cache = {
                timestamp: new Date().getTime(),
                data: clientsData,
                filters: this.getCurrentFilters()
            };

            sessionStorage.setItem(this.CLIENTS_CACHE_KEY, JSON.stringify(cache));
            console.log('💾 تم حفظ بيانات العملاء في الكاش');

            return cache;
        } catch (error) {
            console.error('❌ خطأ في حفظ بيانات الكاش:', error);
            return null;
        }
    }

    /**
     * استرجاع حالة الخريطة
     */
    restoreMapState() {
        try {
            const stateJson = sessionStorage.getItem(this.STATE_KEY);
            if (!stateJson) {
                console.log('ℹ️ لا توجد حالة محفوظة للخريطة');
                return null;
            }

            const state = JSON.parse(stateJson);
            const ageInSeconds = (new Date().getTime() - state.timestamp) / 1000;

            // التحقق من أن الحالة محفوظة حديثاً (أقل من 30 دقيقة)
            if (ageInSeconds > 30 * 60) {
                console.log('⚠️ حالة الخريطة قديمة جداً، تم حذفها');
                sessionStorage.removeItem(this.STATE_KEY);
                return null;
            }

            console.log('📂 تم استرجاع حالة الخريطة');
            return state;
        } catch (error) {
            console.error('❌ خطأ في استرجاع حالة الخريطة:', error);
            return null;
        }
    }

    /**
     * استعادة وعرض بيانات العملاء من الكاش مباشرة
     */
    restoreAndDisplayClientsData() {
        try {
            const cachedClients = this.restoreClientsData();

            console.log('🔍 فحص البيانات المحفوظة:', cachedClients);

            if (cachedClients && Array.isArray(cachedClients) && cachedClients.length > 0) {
                console.log('💾 استخدام البيانات المحفوظة:', cachedClients.length, 'عميل');

                // تحديث الخريطة مباشرة بالبيانات المخزنة
                if (window.mapManager && window.mapManager.map && window.mapManager.isMapLoaded) {
                    console.log('📍 عرض العملاء على الخريطة من الكاش...');
                    console.log('✅ تفاصيل البيانات:', cachedClients.slice(0, 2));

                    // ✅ إعادة رسم الخريطة أولاً (في حالة كانت مخفية أو غير مرسومة بشكل صحيح)
                    if (typeof google !== 'undefined' && google.maps) {
                        google.maps.event.trigger(window.mapManager.map, 'resize');
                        console.log('🔄 تم إعادة رسم الخريطة (resize)');
                    }

                    // استدعاء updateMapMarkers مباشرة مع تأخير صغير للتأكد من أن الخريطة جاهزة
                    setTimeout(() => {
                        window.mapManager.updateMapMarkers(cachedClients);

                        // التأكد من أن الخريطة محدثة
                        if (window.mapManager.allMarkers && window.mapManager.allMarkers.length > 0) {
                            console.log('✅ تم عرض', window.mapManager.allMarkers.length, 'علامة على الخريطة');
                        } else {
                            console.warn('⚠️ لم يتم عرض أي علامات على الخريطة');
                        }
                    }, 100);
                } else {
                    console.warn('⚠️ الخريطة غير جاهزة:', {
                        mapExists: !!window.mapManager,
                        hasMap: window.mapManager ? !!window.mapManager.map : false,
                        isMapLoaded: window.mapManager ? window.mapManager.isMapLoaded : false
                    });
                }

                return true;
            } else {
                console.log('⚠️ لا توجد بيانات مخزنة أو البيانات فارغة');
                return false;
            }
        } catch (error) {
            console.error('❌ خطأ في استعادة البيانات من الكاش:', error);
            return false;
        }
    }

    /**
     * استرجاع بيانات العملاء من الكاش
     */
    restoreClientsData() {
        try {
            const cacheJson = sessionStorage.getItem(this.CLIENTS_CACHE_KEY);
            if (!cacheJson) {
                console.log('ℹ️ لا توجد بيانات مخزنة للعملاء');
                return null;
            }

            const cache = JSON.parse(cacheJson);
            const ageInSeconds = (new Date().getTime() - cache.timestamp) / 1000;

            // التحقق من أن البيانات محفوظة حديثاً (أقل من 30 دقيقة)
            if (ageInSeconds > 30 * 60) {
                console.log('⚠️ بيانات العملاء قديمة جداً، تم حذفها');
                sessionStorage.removeItem(this.CLIENTS_CACHE_KEY);
                return null;
            }

            console.log('📂 تم استرجاع بيانات العملاء من الكاش');
            return cache.data;
        } catch (error) {
            console.error('❌ خطأ في استرجاع بيانات الكاش:', error);
            return null;
        }
    }

    /**
     * استعادة الحالة عند تحميل الصفحة
     */
    restoreStateOnPageLoad() {
        window.addEventListener('load', () => {
            setTimeout(() => {
                const savedState = this.restoreMapState();

                if (savedState && savedState.mapOpen) {
                    console.log('🔄 استعادة حالة الخريطة المفتوحة...');

                    // التحقق من وجود عناصر الخريطة
                    const toggleButton = $('#toggleMapButton');
                    const mapContainer = $('#mapContainer');
                    const actionCard = $('#actionCard');

                    // إذا لم تكن العناصر موجودة (مثل صفحة show)
                    if (toggleButton.length === 0 || mapContainer.length === 0) {
                        console.log('ℹ️ عناصر الخريطة غير موجودة في الصفحة الحالية');
                        console.log('💾 تم حفظ حالة الخريطة وستُستعاد عند الرجوع');
                        return;
                    }

                    // فتح الخريطة
                    localStorage.setItem('mapOpen', 'true');

                    if (window.mapManager) {
                        window.mapManager.showMap(toggleButton, mapContainer, actionCard);

                        // استعادة مواضع الخريطة بعد تحميلها
                        if (window.mapManager.isMapLoaded && window.mapManager.map) {
                            console.log('⏱️ الخريطة محملة بالفعل، جاري استعادة البيانات...');
                            this.applyMapState(savedState);
                            // محاولة استخدام البيانات المحفوظة مباشرة
                            const dataRestored = this.restoreAndDisplayClientsData();
                            console.log('📊 هل تم استعادة البيانات؟', dataRestored);
                            // ثم تحميل البيانات من السيرفر للتحديث
                            if (savedState.filters) {
                                setTimeout(() => window.mapManager.loadMapData(savedState.filters), 1000);
                            }
                        } else {
                            // انتظار تحميل الخريطة
                            let checkCount = 0;
                            const checkMapLoaded = setInterval(() => {
                                checkCount++;
                                console.log('⏳ انتظار تحميل الخريطة... محاولة', checkCount);

                                if (window.mapManager && window.mapManager.isMapLoaded && window.mapManager.map) {
                                    clearInterval(checkMapLoaded);
                                    console.log('✅ الخريطة محملة، جاري استعادة البيانات...');

                                    this.applyMapState(savedState);
                                    // محاولة استخدام البيانات المحفوظة مباشرة
                                    const dataRestored = this.restoreAndDisplayClientsData();
                                    console.log('📊 هل تم استعادة البيانات؟', dataRestored);

                                    // ثم تحميل البيانات من السيرفر للتحديث
                                    if (savedState.filters) {
                                        setTimeout(() => window.mapManager.loadMapData(savedState.filters), 1000);
                                    }
                                }
                            }, 300);

                            // توقف المحاولة بعد 30 ثانية
                            setTimeout(() => clearInterval(checkMapLoaded), 30000);
                        }
                    }
                }
            }, 500);
        });
    }

    /**
     * تطبيق حالة الخريطة
     */
    applyMapState(state) {
        try {
            console.log('🔄 تطبيق حالة الخريطة...');

            // استعادة مركز الخريطة والتكبير
            if (state.mapCenter) {
                console.log('📍 استعادة موقع الخريطة:', state.mapCenter);
                window.mapManager.map.setCenter(state.mapCenter);
            }

            if (state.mapZoom) {
                console.log('🔍 استعادة مستوى التكبير:', state.mapZoom);
                window.mapManager.map.setZoom(state.mapZoom);
            }

            // تحميل بيانات الخريطة بناءً على الفلاتر المحفوظة
            if (state.filters && window.mapManager.isMapLoaded) {
                console.log('📍 تحميل بيانات الخريطة بناءً على الفلاتر المحفوظة...');
                window.mapManager.loadMapData(state.filters);
            }

            console.log('✅ تم تطبيق حالة الخريطة بنجاح');
        } catch (error) {
            console.error('❌ خطأ في تطبيق حالة الخريطة:', error);
        }
    }

    /**
     * تطبيق الفلاتر المحفوظة
     */
    applyFilters(filters) {
        try {
            if (window.searchManager) {
                // تحديث الفلاتر
                if (filters.searchQuery) {
                    $('#searchInput').val(filters.searchQuery);
                }

                // تحديث حالة البحث
                window.searchManager.updateCurrentFilters();
                window.searchManager.performSearch();

                console.log('✅ تم تطبيق الفلاتر المحفوظة');
            }
        } catch (error) {
            console.error('❌ خطأ في تطبيق الفلاتر:', error);
        }
    }

    /**
     * الحصول على مركز الخريطة الحالي
     */
    getMapCenter() {
        if (window.mapManager && window.mapManager.map) {
            const center = window.mapManager.map.getCenter();
            return {
                lat: center.lat(),
                lng: center.lng()
            };
        }
        return null;
    }

    /**
     * الحصول على مستوى التكبير الحالي
     */
    getMapZoom() {
        if (window.mapManager && window.mapManager.map) {
            return window.mapManager.map.getZoom();
        }
        return null;
    }

    /**
     * الحصول على الفلاتر الحالية
     */
    getCurrentFilters() {
        if (window.searchManager) {
            return window.searchManager.getCurrentFilters();
        }
        return {};
    }

    /**
     * الحصول على الصفحة الحالية
     */
    getCurrentPage() {
        const urlParams = new URLSearchParams(window.location.search);
        return parseInt(urlParams.get('page') || 1);
    }

    /**
     * الحصول على نص البحث الحالي
     */
    getSearchQuery() {
        return $('#searchInput').val() || '';
    }

    /**
     * الحصول على بيانات العملاء المخفيين
     */
    getHiddenClientsData() {
        if (window.hiddenClientsManager) {
            return window.hiddenClientsManager.getHiddenClients();
        }
        return [];
    }

    /**
     * إعداد معالج الرؤية
     */
    setupPageVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // حفظ الحالة عند إخفاء الصفحة
                console.log('📤 حفظ الحالة قبل إخفاء الصفحة...');
                this.saveMapState();
            } else {
                // استعادة الحالة عند إظهار الصفحة
                console.log('📥 استعادة الحالة بعد إظهار الصفحة...');
                const savedState = this.restoreMapState();
                if (savedState) {
                    // تحديث البيانات
                    if (window.searchManager) {
                        window.searchManager.performSearch();
                    }
                }
            }
        });
    }

    /**
     * إعداد معالج قبل إلغاء التحميل
     */
    setupBeforeUnloadHandler() {
        window.addEventListener('beforeunload', () => {
            console.log('📤 حفظ الحالة قبل مغادرة الصفحة...');
            this.saveMapState();
        });
    }

    /**
     * مسح جميع الحالات المحفوظة
     */
    clearAllState() {
        try {
            sessionStorage.removeItem(this.STATE_KEY);
            sessionStorage.removeItem(this.CLIENTS_CACHE_KEY);
            sessionStorage.removeItem(this.MAP_FILTERS_KEY);
            sessionStorage.removeItem(this.LAST_MAP_STATE);
            console.log('🗑️ تم مسح جميع الحالات المحفوظة');
        } catch (error) {
            console.error('❌ خطأ في مسح الحالات:', error);
        }
    }

    /**
     * الحصول على حالة التطبيق
     */
    getState() {
        return {
            saved: this.restoreMapState(),
            cached: this.restoreClientsData(),
            current: {
                mapOpen: localStorage.getItem('mapOpen') === 'true',
                mapCenter: this.getMapCenter(),
                mapZoom: this.getMapZoom()
            }
        };
    }
}

// تصدير مثيل عام
window.stateManager = new StateManager();
