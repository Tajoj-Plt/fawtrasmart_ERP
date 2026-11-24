@extends('client::master')

@section('title')
    إدارة العملاء
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/indexclient.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/map-styles.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/hidden-clients.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/card_client.css') }}?v={{ time() }}">
    <!-- إضافة Font Awesome إذا لم تكن محملة -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
@stop

@section('content')
    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::partials.settings-theme')
    @include('components.hint-tips')

    {{-- قوائم إجراءات الصفوف تخرج من قصّ البطاقة والجدول — بلا هذه
         الجزئية تُقطَع القائمة عند حافّة الجدول --}}
    @include('client::partials.table-menu')

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'إدارة العملاء',
            'icon' => 'users',
            'crumbs' => [
                'الرئيسية' => route('sales.department.dashboard'),
                'العملاء' => null,
            ],
        ])

    </div>
    {{--  --}}
    <div class="content-body">
        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً، كما في «قائمة الموردين» --}}
        @include('client::components.guide', [
            'key' => 'clients.index',
            'open' => false,
            'title' => __('client_settings.guide.index.title'),
            'lead' => __('client_settings.guide.index.lead'),
            'blocks' => [
                __('client_settings.guide.index.scope.title') => [
                    __('client_settings.guide.index.scope.a'),
                    __('client_settings.guide.index.scope.b'),
                    __('client_settings.guide.index.scope.c'),
                ],
                __('client_settings.guide.index.balance.title') => [
                    __('client_settings.guide.index.balance.a'),
                    __('client_settings.guide.index.balance.b'),
                ],
            ],
            'facts' => [
                __('client_settings.guide.index.facts.status'),
                __('client_settings.guide.index.facts.currency'),
            ],
        ])

        {{-- ══════════ الإحصاءات ══════════
             أربعُ بلاطاتٍ بنفس ترتيب «قائمة الموردين»: الإجمالي، النشط،
             المستحقّ، الموقوف. الأرقام من `$clientStats` في المتحكّم،
             و«نشط»/«موقوف» من `kpi_role` في شاشة الحالات لا من معرّفٍ مثبَّت. --}}
        <div class="cl-stats">
            <div class="cl-stat">
                <span class="cl-stat__icon"><i class="fas fa-users"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value" data-client-stat="total">{{ \App\Support\LanguageSettings::formatNumber($clientStats['total'] ?? 0, 0) }}</p>
                    <p class="cl-stat__label">{{ __('client_settings.index.stats.total') }}</p>
                </div>
            </div>

            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--success"><i class="fas fa-check-circle"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value" data-client-stat="active">{{ \App\Support\LanguageSettings::formatNumber($clientStats['active'] ?? 0, 0) }}</p>
                    <p class="cl-stat__label">{{ __('client_settings.index.stats.active') }}</p>
                </div>
            </div>

            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--warning"><i class="fas fa-hourglass-half"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value" data-client-stat="due">{{ \App\Support\LanguageSettings::formatNumber($clientStats['due'] ?? 0, 0) }}</p>
                    <p class="cl-stat__label">{{ __('client_settings.index.stats.due') }}</p>
                </div>
            </div>

            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--danger"><i class="fas fa-ban"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value" data-client-stat="stopped">{{ \App\Support\LanguageSettings::formatNumber($clientStats['stopped'] ?? 0, 0) }}</p>
                    <p class="cl-stat__label">{{ __('client_settings.index.stats.stopped') }}</p>
                </div>
            </div>
        </div>

        <!-- بطاقة الإجراءات -->
        @include('client::partials.action_card')

        <!-- حاوي الخريطة -->
        @include('client::partials.map_container')

        <!-- كونتينر العملاء المخفيين -->
        @include('client::partials.hidden_clients_container')

        {{-- العملاء المُخفَون بعد «إنهاء الزيارة» — مع زرّ إرجاعهم لخط السير --}}
        @include('client::partials.finished_today_clients')

        <!-- بطاقة البحث -->
        @include('client::partials.search_card')

        {{-- ══════════ النتائج ══════════
             نفسُ هيكل بطاقة نتائج «قائمة الموردين»: ترويسةٌ فيها العنوان
             وعدّادُ النتائج ومُنتقي عدد السجلّات، ثم الجسم، ثم قدمٌ للترقيم.
             `#clientsContainer` و`#paginationControls` و`#loadingOverlay`
             تقرؤها `PaginationManager` و`SearchManager` — لم تُمسّ. --}}
        <div class="card cl-table-card" id="clientsCard">
            <div class="cl-table-head">
                <h6 class="cl-table-head__title">
                    <i class="fas fa-list"></i>
                    {{ __('client_settings.index.results') }}
                    <span class="cl-count results-info">
                        {{ __('client_settings.index.results_count', [
                            'count' => \App\Support\LanguageSettings::formatNumber($clients->total(), 0),
                        ]) }}
                    </span>
                </h6>

                <div class="d-flex align-items-center gap-2">
                    @include('client::partials.per_page_selector')
                </div>
            </div>

            <div class="card-body position-relative">
                <!-- Loading Overlay -->
                <div id="loadingOverlay" class="loading-overlay" style="display: none;">
                    <div class="text-center">
                        <div class="dot-pulse mb-3"></div>
                        <h5 class="text-primary mb-2">جارٍ تحديث البيانات...</h5>
                        <p class="text-muted small">سيتم عرض النتائج خلال لحظات</p>
                    </div>
                </div>

                <div id="clientsContainer">
                    @include('client::partials.client_cards')
                </div>
            </div>

            <div class="cl-table-foot" id="paginationControls">
                @include('client::partials.pagination_controls')
            </div>
        </div>

        <!-- Modal إضافة حد ائتماني -->
        @include('client::partials.credit_limit_modal_all_clients', ['allClients' => $allClients])

        <!-- Modal تغيير الحالة الجماعي -->
        <div class="modal fade" id="bulkStatusModal" tabindex="-1" aria-labelledby="bulkStatusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkStatusModalLabel">تغيير حالة العملاء المحددين</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="bulkStatusForm">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="bulk_status_id" class="form-label font-weight-bold">اختر الحالة الجديدة:</label>
                                <select class="form-select" id="bulk_status_id" name="status_id" required>
                                    <option value="">اختر الحالة...</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">تحديث الحالة</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts Configuration -->
    <script>
        // تعريف الراوتات في الجافاسكريبت
        window.clientRoutes = {
            registerVisit: "{{ route('clients.openVisitWorkspace', ':id') }}",
            index: "{{ route('clients.index') }}",
            show: "{{ route('clients.show', '') }}/",
            hideFromMap: "{{ route('clients.hideFromMap', ':id') }}",
            showInMap: "{{ route('clients.showInMap', ':id') }}",
            getHiddenClients: "{{ route('clients.getHiddenClients') }}",
            getMapData: "{{ route('clients.getMapData') }}",
            update_credit_limit: "{{ route('clients.update_credit_limit') }}",
            update_all_credit_limits: "{{ route('clients.update_all_credit_limits') }}",
            bulkUpdateStatus: "{{ route('clients.bulkUpdateStatus') }}"
        };

        /* شرط «الزيارة قبل العرض» كما يراه الخادم بالضبط — يقرؤه
           hidden-clients-manager.js عند إعادة كتابة رابط العميل بعد إظهاره،
           بدل فحص «هل هو موظف؟» الذي كان يحوّل رابط المُعفى وغير الملزَم أيضاً.
           نفس تركيبة $mustVisit في partials/client_cards.blade.php. */
        window.clientMustVisitBeforeView = @json(
            hasPermission('clients_must_visit_before_view')
                && ! hasPermission('clients_exempt_visit_before_view')
        );

        // CSRF Token
        window.csrfToken = "{{ csrf_token() }}";

        // Google Maps API Key
        window.googleMapsApiKey = "{{ env('GOOGLE_MAPS_API_KEY') }}";
    </script>
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>

    <!-- Core App Scripts -->
    <script src="{{ asset('assets/js/app-core.js') }}"></script>
    {{-- <script src="{{ asset('assets/js/state-manager.js') }}"></script> --}}
    <script src="{{ asset('assets/js/search-manager.js') }}?v=2"></script>
    <script src="{{ asset('assets/js/pagination-manager.js') }}?v=3"></script>
    <script src="{{ asset('assets/js/hidden-clients-manager.js') }}"></script>
    <script src="{{ asset('assets/js/credit-limit-manager.js') }}"></script>
    <script src="{{ asset('assets/js/app-init.js') }}"></script>

    <!-- Map Manager - Inline Implementation -->
    <script>
        /**
         * Map Manager - إدارة الخريطة
         * يدير جميع العمليات المتعلقة بالخريطة
         */

        class MapManager {
            constructor() {
                this.map = null;
                this.infoWindow = null;
                this.currentUserMarker = null;
                this.allMarkers = [];
                this.markerCluster = null;
                this.isMapLoaded = false;
                this.isInitialized = false;

                // إعدادات الخريطة
                this.defaultCenter = {
                    lat: 24.7136,
                    lng: 46.6753
                }; // الرياض
                this.retryAttempts = 0;
                this.maxRetryAttempts = 3;
            }

            /**
             * تهيئة الخريطة
             */
            init() {
                this.initializeMapState();
                this.bindEvents();
            }

            /**
             * ربط الأحداث
             */
            bindEvents() {
                // زر الخريطة
                $('#toggleMapButton').on('click', (e) => {
                    e.preventDefault();
                    this.toggleMap();
                });

                // زر التكبير/التصغير
                $('#fullscreenButton').on('click', (e) => {
                    e.preventDefault();
                    this.toggleFullscreen();
                });

                // معالج تغيير حجم الشاشة
                let resizeTimer;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => {
                        if (this.map && this.isMapLoaded) {
                            this.handleResize();
                        }
                    }, 250);
                });
            }

            /**
             * تعيين الحالة الافتراضية للخريطة
             */
            initializeMapState() {
                // تعيين الحالة الافتراضية للخريطة مغلقة
                localStorage.setItem('mapOpen', 'false');

                const toggleButton = $('#toggleMapButton');
                const mapContainer = $('#mapContainer');
                const actionCard = $('#actionCard');

                // إخفاء الخريطة بشكل افتراضي
                mapContainer.addClass('map-hidden').removeClass('map-show');
                actionCard.addClass('map-closed').removeClass('map-open');
                toggleButton.removeClass('map-active')
                    .attr('data-tooltip', 'عرض الخريطة')
                    .attr('title', 'عرض الخريطة')
                    .find('i').removeClass('fa-map').addClass('fa-map-marked-alt');
            }

            /**
             * تبديل حالة الخريطة
             */
            toggleMap() {
                const isMapOpen = localStorage.getItem('mapOpen') === 'true';
                const newState = !isMapOpen;

                localStorage.setItem('mapOpen', newState);

                const toggleButton = $('#toggleMapButton');
                const mapContainer = $('#mapContainer');
                const actionCard = $('#actionCard');

                // إضافة تأثير الموجة
                const ripple = $('<div class="map-toggle-effect"></div>');
                toggleButton.append(ripple);
                ripple.addClass('active');
                setTimeout(() => ripple.remove(), 600);

                if (newState) {
                    this.showMap(toggleButton, mapContainer, actionCard);
                } else {
                    this.hideMap(toggleButton, mapContainer, actionCard);
                }
            }

            /**
             * إظهار الخريطة
             */
            showMap(toggleButton, mapContainer, actionCard) {
                actionCard.addClass('map-open').removeClass('map-closed');
                setTimeout(() => mapContainer.removeClass('map-hidden').addClass('map-show'), 200);

                toggleButton.addClass('map-active')
                    .attr('data-tooltip', 'إخفاء الخريطة')
                    .attr('title', 'إخفاء الخريطة')
                    .find('i').removeClass('fa-map-marked-alt').addClass('fa-map');

                if (!this.isMapLoaded) {
                    this.loadGoogleMaps();
                } else if (this.map) {
                    setTimeout(() => {
                        google.maps.event.trigger(this.map, 'resize');
                        this.map.setCenter(this.defaultCenter);
                        this.fitMapToMarkers();
                    }, 700);
                }
            }

            /**
             * إخفاء الخريطة
             */
            hideMap(toggleButton, mapContainer, actionCard) {
                mapContainer.removeClass('map-show').addClass('map-hidden');
                setTimeout(() => actionCard.addClass('map-closed').removeClass('map-open'), 300);

                toggleButton.removeClass('map-active')
                    .attr('data-tooltip', 'عرض الخريطة')
                    .attr('title', 'عرض الخريطة')
                    .find('i').removeClass('fa-map').addClass('fa-map-marked-alt');
            }

            /**
             * تحميل Google Maps
             */
            loadGoogleMaps() {
                if (typeof google !== 'undefined' && google.maps) {
                    setTimeout(() => this.initializeMap(), 500);
                    return;
                }

                // انتظار تحميل Google Maps
                this.waitForGoogleMaps();
            }

            /**
             * انتظار تحميل Google Maps
             */
            waitForGoogleMaps() {
                const checkGoogleMaps = setInterval(() => {
                    if (typeof google !== 'undefined' && google.maps) {
                        clearInterval(checkGoogleMaps);
                        setTimeout(() => this.initializeMap(), 500);
                    }
                }, 500);

                setTimeout(() => {
                    clearInterval(checkGoogleMaps);
                    if (!this.isMapLoaded) {
                        console.error('❌ فشل في تحميل Google Maps');
                        this.showMapError();
                    }
                }, 15000);
            }

            /**
             * تهيئة الخريطة
             */
            initializeMap() {
                const mapElement = document.getElementById('map');
                if (!mapElement) {
                    console.error('❌ عنصر الخريطة غير موجود');
                    return;
                }

                if (typeof google === 'undefined' || !google.maps) {
                    if (this.retryAttempts < this.maxRetryAttempts) {
                        this.retryAttempts++;
                        setTimeout(() => this.initializeMap(), 1000);
                    } else {
                        this.showMapError();
                    }
                    return;
                }

                try {
                    const isMobile = window.innerWidth <= 768;
                    const isTablet = window.innerWidth > 768 && window.innerWidth <= 992;

                    const mapOptions = {
                        zoom: isMobile ? 9 : isTablet ? 10 : 11,
                        center: this.defaultCenter,
                        mapTypeId: google.maps.MapTypeId.ROADMAP,
                        zoomControl: true,
                        mapTypeControl: true,
                        scaleControl: false,
                        streetViewControl: false,
                        rotateControl: false,
                        fullscreenControl: true,
                        gestureHandling: isMobile ? 'cooperative' : 'greedy',
                        styles: [],
                        // إعدادات إضافية للتوافق مع جميع الأجهزة
                        disableDefaultUI: false,
                        clickableIcons: false,
                        keyboardShortcuts: false
                    };

                    this.map = new google.maps.Map(mapElement, mapOptions);

                    google.maps.event.addListenerOnce(this.map, 'idle', () => {
                        console.log('🗺️ الخريطة جاهزة الآن');
                        this.isMapLoaded = true;
                        this.isInitialized = true;
                        google.maps.event.trigger(this.map, 'resize');

                        // تحميل بيانات الخريطة
                        if (window.searchManager) {
                            const filters = window.searchManager.getCurrentFilters();
                            console.log('📋 الفلاتر الحالية:', filters);
                            this.loadMapData(filters);
                        } else {
                            console.warn('⚠️ مدير البحث غير متاح، جاري تحميل البيانات بدون فلاتر');
                            this.loadMapData({});
                        }

                        // إضافة أزرار التحكم
                        setTimeout(() => {
                            console.log('➕ إضافة أزرار التحكم');
                            this.addCenterMapButton();
                            this.addMyLocationButton();
                        }, 1000);
                    });

                    this.infoWindow = new google.maps.InfoWindow({
                        maxWidth: isMobile ? 250 : 300,
                        disableAutoPan: false
                    });

                    // معالج الأخطاء
                    google.maps.event.addListener(this.map, 'tilesloaded', () => {
                        console.log('✅ تم تحميل الخريطة بنجاح');
                    });

                } catch (error) {
                    console.error('❌ خطأ في تهيئة الخريطة:', error);
                    this.isMapLoaded = false;
                    this.showMapError();
                }
            }

            /**
             * معالج تغيير حجم الشاشة
             */
            handleResize() {
                google.maps.event.trigger(this.map, 'resize');
                this.map.setCenter(this.defaultCenter);

                const newIsMobile = window.innerWidth <= 768;
                this.map.setOptions({
                    gestureHandling: newIsMobile ? 'cooperative' : 'greedy'
                });

                setTimeout(() => this.fitMapToMarkers(), 300);
            }

            /**
             * تحميل بيانات الخريطة
             */
            loadMapData(params = {}) {
                if (!this.isMapLoaded || !this.map) {
                    console.warn('⚠️ الخريطة غير محملة بعد');
                    return;
                }

                console.log('📍 جاري تحميل بيانات العملاء... المعاملات:', params);

                $.ajax({
                    url: window.clientRoutes.getMapData,
                    method: 'GET',
                    data: params,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: (response) => {
                        console.log('✅ تم استقبال بيانات الخريطة:', response);

                        if (response && response.clients && Array.isArray(response.clients)) {
                            console.log(`📊 عدد العملاء: ${response.clients.length}`);
                            this.updateMapMarkers(response.clients);
                        } else {
                            console.warn('⚠️ البيانات المستقبلة غير صحيحة:', response);
                            this.clearMapMarkers();
                        }
                    },
                    error: (xhr) => {
                        console.error('❌ خطأ في تحميل بيانات الخريطة:', xhr);
                        console.error('حالة الخطأ:', xhr.status, xhr.statusText);
                        console.error('الرد:', xhr.responseText);
                    }
                });
            }

            /**
             * تحديث علامات الخريطة
             */
            updateMapMarkers(clientsData) {
                console.log('🔄 بدء تحديث الماركرز - عدد العملاء:', clientsData ? clientsData.length : 0);

                this.clearMapMarkers();

                if (!clientsData || clientsData.length === 0) {
                    console.warn('⚠️ لا توجد بيانات عملاء');
                    this.hideEmptyClusters();
                    return;
                }

                this.allMarkers = [];
                const validClients = clientsData.filter(client => {
                    const hasCoords = client.lat && client.lng;
                    const validCoords = parseFloat(client.lat) !== 0 && parseFloat(client.lng) !== 0;

                    if (!hasCoords || !validCoords) {
                        console.warn('⚠️ عميل بدون إحداثيات صحيحة:', client.trade_name || client.code);
                    }

                    return hasCoords && validCoords;
                });

                console.log(`✅ عدد العملاء مع إحداثيات صحيحة: ${validClients.length}`);

                // إذا لم تكن هناك عملاء صالحين، أخف الكتل
                if (validClients.length === 0) {
                    console.warn('⚠️ لا يوجد عملاء بإحداثيات صحيحة');
                    this.hideEmptyClusters();
                    return;
                }

                validClients.forEach((client, index) => {
                    const lat = parseFloat(client.lat);
                    const lng = parseFloat(client.lng);

                    if (isNaN(lat) || isNaN(lng)) {
                        console.warn('⚠️ إحداثيات غير صحيحة للعميل:', client.trade_name);
                        return;
                    }

                    try {
                        const marker = this.createMarker(client, index, lat, lng);
                        if (marker) {
                            this.allMarkers.push({
                                marker: marker,
                                clientName: (client.trade_name || '').toLowerCase(),
                                clientCode: String(client.code || '').toLowerCase(),
                                clientData: client
                            });
                        }
                    } catch (error) {
                        console.error('❌ خطأ في إنشاء العلامة للعميل:', client, error);
                    }
                });

                console.log(`🎯 تم إنشاء ${this.allMarkers.length} ماركر على الخريطة`);

                if (this.allMarkers.length > 0) {
                    this.initializeMarkerCluster();
                    this.fitMapToMarkers();
                    console.log('✅ تم تحديث الخريطة بنجاح');
                } else {
                    console.warn('⚠️ لم يتم إنشاء أي ماركرات');
                    this.hideEmptyClusters();
                }
            }

            /**
             * إنشاء علامة العميل
             */
            createMarker(client, index, lat, lng) {
                /* الكودُ كاملاً في الكبسولة.

                   كان `substring(0, 6)` يقصّه بلا إشارةٍ إلى القصّ، فكودٌ
                   مثل `CUST-00001` يظهر `CUST-0` — وكلُّ عملاء الخريطة
                   يتشابهون في هذه الأحرف الستّة فلا يميّز الماركرُ أحداً عن
                   أحد، وهو كلُّ فائدته. والعرضُ الآن يُحسَب من طول النصّ بدل
                   أن يبقى ٦٠ بكسل ثابتة والنصُّ يُقصّ ليدخل فيها. */
                const displayCode = client.code ? String(client.code) : 'N/A';
                const statusColor = this.getSafeColor(client.statusColor);

                const fontSize = displayCode.length > 12 ? 11 : 13;
                // تقديرٌ لعرض الحرف في Arial بوزن ٩٠٠ — ‏٠٫٦٥ من حجم الخطّ
                const textWidth = Math.ceil(displayCode.length * fontSize * 0.65);
                const capsuleWidth = Math.max(60, textWidth + 20);
                const svgWidth = capsuleWidth + 10;
                const svgHeight = 45;

                // الشكل الموحد (كبسولة) لجميع الحالات (لون الحالة للماركر)
                const markerSvg = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="${svgWidth}" height="${svgHeight}" viewBox="0 0 ${svgWidth} ${svgHeight}">
                        <defs>
                            <filter id="shadow${index}" x="-50%" y="-50%" width="200%" height="200%">
                                <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.4"/>
                            </filter>
                        </defs>
                        <rect x="5" y="5" width="${capsuleWidth}" height="35" rx="17.5" ry="17.5"
                               fill="${statusColor}" stroke="white" stroke-width="2" filter="url(#shadow${index})"/>
                        <text x="${5 + capsuleWidth / 2}" y="25" font-family="Arial, sans-serif" font-size="${fontSize}" font-weight="900"
                               fill="white" text-anchor="middle" dominant-baseline="middle">
                            ${displayCode}
                        </text>
                    </svg>`;

                const marker = new google.maps.Marker({
                    position: {
                        lat: lat,
                        lng: lng
                    },
                    map: this.map,
                    title: `${client.trade_name || 'غير محدد'} (${client.code || 'غير محدد'})`,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(markerSvg),
                        scaledSize: new google.maps.Size(svgWidth, svgHeight),
                        anchor: new google.maps.Point(svgWidth / 2, svgHeight / 2)
                    },
                    optimized: true // تحسين الأداء
                });

                marker.addListener('click', () => this.showClientInfo(marker, client));

                return marker;
            }

            /**
             * تهيئة تجميع العلامات
             */
            initializeMarkerCluster() {
                if (typeof markerClusterer !== 'undefined' && this.allMarkers.length > 0) {
                    this.markerCluster = new markerClusterer.MarkerClusterer({
                        map: this.map,
                        markers: this.allMarkers.map(m => m.marker),
                        renderer: {
                            render: ({
                                count,
                                position
                            }) => {
                                // لا تعرض الكتلة إذا كان العدد 0
                                if (count === 0) return null;

                                const clusterColor = '#4285F4';
                                let circleSize = 45,
                                    fontSize = 14;

                                if (count >= 100) {
                                    circleSize = 65;
                                    fontSize = 16;
                                } else if (count >= 50) {
                                    circleSize = 55;
                                    fontSize = 15;
                                } else if (count >= 20) {
                                    circleSize = 50;
                                    fontSize = 14;
                                }

                                return new google.maps.Marker({
                                    position,
                                    icon: {
                                        url: 'data:image/svg+xml;charset=UTF-8,' +
                                            encodeURIComponent(`
                                    <svg xmlns="http://www.w3.org/2000/svg" width="${circleSize}" height="${circleSize}" viewBox="0 0 ${circleSize} ${circleSize}">
                                        <defs>
                                            <filter id="clusterShadow" x="-50%" y="-50%" width="200%" height="200%">
                                                <feDropShadow dx="0" dy="2" stdDeviation="4" flood-opacity="0.3"/>
                                            </filter>
                                        </defs>
                                        <circle cx="${circleSize/2}" cy="${circleSize/2}" r="${(circleSize-6)/2}"
                                                fill="${clusterColor}" opacity="0.95" filter="url(#clusterShadow)"/>
                                        <text x="${circleSize/2}" y="${circleSize/2 + fontSize/3}" font-family="Arial, sans-serif"
                                              font-size="${fontSize}" font-weight="bold" fill="white" text-anchor="middle"
                                              dominant-baseline="middle">${count}</text>
                                    </svg>
                                `),
                                        scaledSize: new google.maps.Size(circleSize, circleSize),
                                        anchor: new google.maps.Point(circleSize / 2, circleSize /
                                            2)
                                    }
                                });
                            }
                        }
                    });
                }
            }

            /**
             * إخفاء الكتل الفارغة
             */
            hideEmptyClusters() {
                if (this.markerCluster) {
                    this.markerCluster.clearMarkers();
                }

                // إخفاء جميع العلامات الموجودة
                if (this.allMarkers && this.allMarkers.length > 0) {
                    this.allMarkers.forEach(item => {
                        if (item.marker) {
                            item.marker.setVisible(false);
                        }
                    });
                }
            }

            /**
             * مسح علامات الخريطة
             */
            clearMapMarkers() {
                if (this.markerCluster) {
                    this.markerCluster.clearMarkers();
                    this.markerCluster = null;
                }

                this.allMarkers.forEach(item => {
                    if (item.marker) item.marker.setMap(null);
                });

                this.allMarkers = [];
            }

            /**
             * فلترة العلامات
             */
            filterMarkers(searchValue) {
                let anyVisible = false;
                let visibleMarkers = [];

                this.allMarkers.forEach(item => {
                    const isVisible = !searchValue ||
                        item.clientName.includes(searchValue) ||
                        item.clientCode.includes(searchValue);

                    item.marker.setVisible(isVisible);

                    if (isVisible) {
                        anyVisible = true;
                        visibleMarkers.push(item);

                        if (item.clientName === searchValue || item.clientCode === searchValue) {
                            this.showClientInfo(item.marker, item.clientData);
                            this.map.panTo(item.marker.getPosition());
                            this.map.setZoom(window.innerWidth <= 768 ? 14 : 15);
                        }
                    }
                });

                // تحديث MarkerClusterer بالعلامات المرئية فقط
                if (this.markerCluster) {
                    this.markerCluster.clearMarkers();
                    if (visibleMarkers.length > 0) {
                        this.markerCluster.addMarkers(visibleMarkers.map(item => item.marker));
                    }
                }

                // إذا لم تكن هناك علامات مرئية، اعرض رسالة
                if (!anyVisible && searchValue) {
                    this.showNoResultsMessage(searchValue);
                }
            }

            /**
             * عرض معلومات العميل
             */
            showClientInfo(marker, clientData) {
                /* ألوانُ الكارد من «مظهر النظام» لا مثبَّتةً هنا.

                   كان `#7367f0` و`#28c76f` و`#f8f9fa` و`#FFD700` مكتوبةً في
                   الملفّ، فبطاقةُ الخريطة وحدَها تبقى بنفسجيةً مهما بُدّل
                   اللونُ الأساسي أو فُعّل الوضع الليلي في شاشة المظهر.
                   `App\Support\ThemeSettings` تحقن `--app-*` في ترويسة كل
                   صفحة — فتُقرأ من هناك، ويبقى الثابتُ بديلاً وحسب لو لم
                   تُحقَن (رندرٌ قبل تحميل الأنماط). */
                const themeVar = (name, fallback) => {
                    try {
                        const value = getComputedStyle(document.documentElement)
                            .getPropertyValue(name)
                            .trim();
                        return value || fallback;
                    } catch (e) {
                        return fallback;
                    }
                };

                const uniformColor = themeVar('--app-primary', '#7367f0');
                const onPrimary = themeVar('--app-on-primary', '#ffffff');
                const surfaceColor = themeVar('--app-surface', '#f8f9fa');
                const successColor = themeVar('--app-success', '#28c76f');
                const warningColor = themeVar('--app-warning', '#ff9f43');
                const isMobile = window.innerWidth <= 768;

                let windowWidth;
                if (isMobile) {
                    windowWidth = Math.min(window.innerWidth - 20, 280) + 'px';
                } else {
                    windowWidth = '300px';
                }

                // دالة لتحويل الوقت لصيغة عربية (12 ساعة)
                const formatTimeArabic = (timeStr) => {
                    if (!timeStr) return 'غير محدد';
                    try {
                        const [hours, minutes] = timeStr.split(':');
                        let h = parseInt(hours);
                        const m = minutes;
                        const ampm = h >= 12 ? 'مساءً' : 'صباحاً';
                        h = h % 12;
                        h = h ? h : 12; // الساعة 0 تصبح 12
                        return `${h}:${m} ${ampm}`;
                    } catch (e) {
                        return timeStr;
                    }
                };

                // تنسيق وقت الدوام
                let shiftDisplay = 'غير محدد';
                if (clientData.shift_start && clientData.shift_end) {
                    // «دوام كامل» يُخزَّن 00:00 → 24:00 (`ClientEmployeeNote::FULL_TIME_*`)
                    const fullTime = String(clientData.shift_start).slice(0, 5) === '00:00'
                        && String(clientData.shift_end).slice(0, 5) === '24:00';
                    shiftDisplay = fullTime
                        ? 'دوام كامل (24 ساعة)'
                        : `${formatTimeArabic(clientData.shift_start)} إلى ${formatTimeArabic(clientData.shift_end)}`;
                }

                // مسارُ الفرع كاملاً — نفس ما تعرضه شاشةُ العميل تحت «مسار الفرع»
                const branchPath = clientData.branch_path || clientData.branch || 'غير محدد';

                /* زرُّ البطاقة يقول ما يقوله صفُّ الجدول للعميل نفسه: الموظّفُ
                   المُلزَم بالزيارة قبل العرض يرى «تسجيل الزيارة» لا «التفاصيل»،
                   ويذهب لمساحة عمل زيارته المفتوحة أو لإنشاء واحدة. الوجهةُ
                   تُحسب في الخادم (`getMapData`) بنفس شرط الجدول؛ و`show`
                   يبقى البديلَ لو جاءت بياناتٌ قديمة من نداءٍ سابق. */
                const needsVisit = clientData.needs_visit === true;
                const actionUrl = clientData.action_url
                    || `${window.clientRoutes.show}${clientData.id}`;
                const actionLabel = needsVisit ? 'تسجيل الزيارة' : 'التفاصيل';
                const actionIcon = needsVisit ? 'fa-walking' : 'fa-external-link-alt';
                // الزيارةُ سلسلةُ خطواتٍ ترجع لمساحة عملها، فلا تُفتح في تبويبٍ
                // جديد؛ «التفاصيل» تبقى كما كانت
                const actionClick = needsVisit
                    ? `window.location.href='${actionUrl}'`
                    : `window.open('${actionUrl}', '_blank')`;

                /* مصدرُ العميل في خط سير اليوم — من الخطّة، أو أضافه المندوب، أو
                   أضافه دورٌ آخر من «خط سيري اليومي». يأتي فقط لمن خريطتُه خطُّ سيره. */
                const sourceTones = { plan: ['#e0e7ff', '#3730a3', 'fa-route'], self: ['#fef3c7', '#b45309', 'fa-user-plus'], other: ['#ede9fe', '#6d28d9', 'fa-user-plus'] };
                const source = clientData.itinerary_source;
                const sourceLabel = source ? String(source.label || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])) : '';
                const sourceBadge = source && sourceTones[source.tone]
                    ? `<div style="margin-top:10px;display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:${sourceTones[source.tone][0]};color:${sourceTones[source.tone][1]};"><i class="fas ${sourceTones[source.tone][2]}"></i> ${sourceLabel}</div>`
                    : '';

                /* رصيدُ العميل — نفسُ رقم عمود «المستحق» واتجاهه (مدين/دائن) */
                const escapeHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
                const balanceRow = clientData.balance !== undefined
                    ? `<div style="margin-top:8px;font-size:${isMobile ? '13px' : '14px'};font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px;">
                            <i class="fas fa-wallet"></i>
                            <span>رصيد العميل:</span>
                            <span style="font-weight:800;">${escapeHtml(clientData.balance)}</span>
                            ${clientData.balance_direction ? `<span style="font-size:12px;padding:2px 8px;border-radius:12px;background:rgba(255,255,255,0.2);">${escapeHtml(clientData.balance_direction)}</span>` : ''}
                        </div>`
                    : '';

                const contentString = `
            <div class="custom-info-window" style="
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                width: ${windowWidth};
                max-width: 98vw;
                box-sizing: border-box;
                border: 2px solid ${uniformColor};
                border-radius: 12px;
                overflow: hidden;
            ">
                <!-- Header -->
                <div class="info-header" style="
                    background: ${uniformColor};
                    color: ${onPrimary};
                    padding: ${isMobile ? '16px 14px' : '20px 18px'};
                    text-align: center;
                    border-radius: 8px 8px 0 0;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
                ">
                    <h5 style="
                        margin: 0 0 10px 0;
                        font-size: ${isMobile ? '16px' : '18px'};
                        font-weight: 800;
                        line-height: 1.4;
                        word-wrap: break-word;
                        text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
                    ">
                        ${clientData.trade_name || 'غير محدد'}
                    </h5>
                    <div style="
                        font-size: ${isMobile ? '13px' : '14px'};
                        font-weight: 600;
                        background: rgba(255,255,255,0.2);
                        padding: 6px 10px;
                        border-radius: 20px;
                        display: inline-block;
                        border: 1px solid rgba(255,255,255,0.3);
                    ">
                        <i class="fas fa-barcode me-1"></i> ${clientData.code || '---'}
                    </div>
                    <div style="
                        margin-top: 10px;
                        font-size: ${isMobile ? '12px' : '13px'};
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 6px;
                    ">
                        <i class="fas fa-sitemap"></i>
                        <span style="opacity:.95;">${branchPath}</span>
                    </div>
                    ${sourceBadge}
                    <div style="
                        margin-top: 6px;
                        font-size: ${isMobile ? '13px' : '14px'};
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 8px;
                    ">
                        <i class="fas fa-clock"></i>
                        <span>دوام موظف العميل:</span>
                        <span style="color: ${warningColor}; font-weight: 800;">${shiftDisplay}</span>
                    </div>
                    ${balanceRow}
                </div>

                <!-- Action Buttons -->
                <div class="info-body" style="
                    padding: ${isMobile ? '16px 12px' : '18px 14px'};
                    background: ${surfaceColor};
                    border-radius: 0 0 12px 12px;
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 12px;
                    width: 100%;
                ">
                    <button
                        onclick="${actionClick}"
                        style="
                            width: 100%;
                            padding: ${isMobile ? '12px 8px' : '14px 10px'};
                            background: ${uniformColor};
                            color: ${onPrimary};
                            border: none;
                            border-radius: 8px;
                            cursor: pointer;
                            font-size: ${isMobile ? '13px' : '14px'};
                            font-weight: 700;
                            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                            box-shadow: 0 4px 6px rgba(115, 103, 240, 0.2);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 6px;
                            -webkit-tap-highlight-color: transparent;
                        "
                        onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 12px rgba(115, 103, 240, 0.3)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(115, 103, 240, 0.2)';"
                    >
                        <i class="fas ${actionIcon}"></i>
                        <span>${actionLabel}</span>
                    </button>

                    <button
                        onclick="window.mapManager.openDirections(${marker.getPosition().lat()}, ${marker.getPosition().lng()})"
                        style="
                            width: 100%;
                            padding: ${isMobile ? '12px 8px' : '14px 10px'};
                            background: ${successColor};
                            color: #fff;
                            border: none;
                            border-radius: 8px;
                            cursor: pointer;
                            font-size: ${isMobile ? '13px' : '14px'};
                            font-weight: 700;
                            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                            box-shadow: 0 4px 6px rgba(40, 199, 111, 0.2);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 6px;
                            -webkit-tap-highlight-color: transparent;
                        "
                        onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 12px rgba(40, 199, 111, 0.3)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(40, 199, 111, 0.2)';"
                    >
                        <i class="fas fa-directions"></i>
                        <span>الاتجاهات</span>
                    </button>
                </div>
            </div>
        `;

                this.infoWindow.setContent(contentString);
                this.infoWindow.open(this.map, marker);

                // تحسين خيارات InfoWindow للجوال
                this.infoWindow.setOptions({
                    maxWidth: isMobile ? 280 : 300,
                    pixelOffset: new google.maps.Size(0, -10),
                    disableAutoPan: false
                });
            }
            /**
             * إضافة زر توسيط الخريطة
             */
            addCenterMapButton() {
                // إنشاء زر التوسيط
                const centerButton = document.createElement('button');
                centerButton.innerHTML = '<i class="fas fa-crosshairs"></i>';
                centerButton.title = 'توسيط الخريطة';
                centerButton.className = 'map-center-button';

                // إضافة تأثيرات التفاعل
                centerButton.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                    this.style.transform = 'scale(1.05)';
                    this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.3)';
                });

                centerButton.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'white';
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
                });

                // وظيفة التوسيط
                centerButton.addEventListener('click', () => {
                    this.centerMapToMarkers();

                    // تأثير بصري للنقر
                    centerButton.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        centerButton.style.transform = 'scale(1)';
                    }, 150);
                });

                // إضافة الزر للخريطة
                const mapContainer = document.getElementById('map');
                if (mapContainer) {
                    mapContainer.appendChild(centerButton);
                }
            }

            /**
             * إضافة زر الموقع الحالي
             */
            addMyLocationButton() {
                console.log('➕ إضافة زر الموقع الحالي');

                // التحقق من عدم وجود الزر مسبقاً
                const existingButton = document.getElementById('myLocationButton');
                if (existingButton) {
                    console.log('⚠️ زر الموقع موجود مسبقاً');
                    existingButton.remove();
                }

                const locationButton = document.createElement('button');
                locationButton.id = 'myLocationButton';
                locationButton.className = 'map-my-location-button';
                locationButton.innerHTML = '<i class="fas fa-location-arrow"></i>';
                locationButton.title = 'موقعي الحالي';
                locationButton.type = 'button';

                const mapContainer = document.getElementById('map');
                if (mapContainer) {
                    mapContainer.appendChild(locationButton);
                    console.log('✅ تم إضافة زر الموقع');

                    setTimeout(() => {
                        const btn = document.getElementById('myLocationButton');
                        if (btn) {
                            // إزالة أي event listeners قديمة
                            const newBtn = btn.cloneNode(true);
                            btn.parentNode.replaceChild(newBtn, btn);

                            // إضافة event listener جديد
                            newBtn.addEventListener('click', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                console.log('🎯 تم الضغط على زر الموقع');
                                this.showCurrentLocation();
                            });

                            console.log('✅ تم ربط حدث النقر');
                        }
                    }, 100);
                } else {
                    console.error('❌ عنصر الخريطة غير موجود');
                }
            }

            /**
             * توسيط الخريطة على العلامات
             */
            centerMapToMarkers() {
                if (!this.allMarkers || this.allMarkers.length === 0) {
                    // العودة إلى المركز الافتراضي (الرياض)
                    this.map.setCenter(this.defaultCenter);
                    this.map.setZoom(window.innerWidth <= 768 ? 9 : 11);
                    return;
                }

                // الحصول على العلامات المرئية فقط
                const visibleMarkers = this.allMarkers.filter(item =>
                    item.marker && item.marker.getVisible && item.marker.getVisible()
                );

                if (visibleMarkers.length === 0) {
                    // العودة إلى المركز الافتراضي
                    this.map.setCenter(this.defaultCenter);
                    this.map.setZoom(window.innerWidth <= 768 ? 9 : 11);
                    return;
                }

                if (visibleMarkers.length === 1) {
                    // علامة واحدة - التوسيط عليها
                    const position = visibleMarkers[0].marker.getPosition();
                    this.map.setCenter(position);
                    this.map.setZoom(window.innerWidth <= 768 ? 14 : 15);
                } else {
                    // عدة علامات - احتواء جميع العلامات المرئية
                    this.fitMapToVisibleMarkers(visibleMarkers);
                }

                // إغلاق أي نافذة معلومات مفتوحة
                if (this.infoWindow) {
                    this.infoWindow.close();
                }

                // مسح البحث
                const searchInput = document.getElementById('clientSearch');
                if (searchInput) {
                    searchInput.value = '';
                }
            }

            /**
             * ضبط الخريطة لتتسع لجميع العلامات
             */
            fitMapToMarkers() {
                if (!this.allMarkers || this.allMarkers.length === 0) return;

                const bounds = new google.maps.LatLngBounds();
                let hasValidMarkers = false;

                this.allMarkers.forEach(item => {
                    if (item.marker && item.marker.getVisible && item.marker.getVisible()) {
                        const position = item.marker.getPosition();
                        if (position) {
                            bounds.extend(position);
                            hasValidMarkers = true;
                        }
                    }
                });

                if (hasValidMarkers) {
                    this.fitBounds(bounds);
                }
            }

            /**
             * ضبط الخريطة للعلامات المرئية
             */
            fitMapToVisibleMarkers(visibleMarkers) {
                const bounds = new google.maps.LatLngBounds();

                visibleMarkers.forEach(item => {
                    const position = item.marker.getPosition();
                    if (position) {
                        bounds.extend(position);
                    }
                });

                this.fitBounds(bounds);
            }

            /**
             * ضبط حدود الخريطة
             */
            fitBounds(bounds) {
                const maxZoom = window.innerWidth <= 768 ? 13 : 14;

                const listener = google.maps.event.addListenerOnce(this.map, 'bounds_changed', () => {
                    if (this.map.getZoom() > maxZoom) {
                        this.map.setZoom(maxZoom);
                    }
                });

                this.map.fitBounds(bounds);
            }

            /**
             * عرض رسالة عدم وجود نتائج
             */
            showNoResultsMessage(searchValue) {
                if (this.infoWindow) {
                    this.infoWindow.setContent(`
                <div style="padding: 20px; text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                    <i class="fas fa-search" style="font-size: 32px; color: #6c757d; margin-bottom: 10px;"></i>
                    <h6 style="color: #495057; margin-bottom: 8px;">لا توجد نتائج</h6>
                    <p style="color: #6c757d; margin: 0; font-size: 14px;">
                        لم يتم العثور على عملاء مطابقين لـ "<strong>${searchValue}</strong>"
                    </p>
                </div>
            `);
                    this.infoWindow.setPosition(this.map.getCenter());
                    this.infoWindow.open(this.map);

                    // إخفاء الرسالة بعد 3 ثواني
                    setTimeout(() => {
                        if (this.infoWindow) this.infoWindow.close();
                    }, 3000);
                }
            }

            /**
             * عرض خطأ الخريطة
             */
            showMapError() {
                const mapElement = document.getElementById('map');
                if (mapElement) {
                    mapElement.innerHTML = `
                <div class="map-error-container">
                    <div class="map-error-card">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #dc3545; margin-bottom: 16px;"></i>
                        <h5 style="color: #dc3545; margin-bottom: 12px; font-weight: 600;">خطأ في تحميل الخريطة</h5>
                        <p style="color: #6c757d; margin-bottom: 20px; line-height: 1.5;">تعذر تحميل خريطة Google Maps. يرجى التحقق من اتصال الإنترنت أو إعادة المحاولة لاحقاً.</p>
                        <button onclick="window.mapManager.retryLoadMap()" class="btn btn-primary" style="background: linear-gradient(135deg, #4285f4, #1a73e8); border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600;">
                            <i class="fas fa-sync-alt me-2"></i>إعادة المحاولة
                        </button>
                    </div>
                </div>
            `;
                }
            }

            /**
             * إعادة محاولة تحميل الخريطة
             */
            retryLoadMap() {
                const mapElement = document.getElementById('map');
                if (mapElement) {
                    mapElement.innerHTML = `
                <div class="map-loading-container">
                    <div class="dot-pulse mb-3"></div>
                    <p style="color: #4285f4;">جارٍ تحميل الخريطة...</p>
                </div>
            `;
                }

                this.isMapLoaded = false;
                this.retryAttempts = 0;

                // إعادة تحميل سكريبت Google Maps
                const existingScript = document.querySelector('script[src*="maps.googleapis.com"]');
                if (existingScript) {
                    existingScript.remove();
                }

                const script = document.createElement('script');
                script.src =
                    `https://maps.googleapis.com/maps/api/js?key=${window.googleMapsApiKey}&libraries=places&callback=window.initMap`;
                script.async = true;
                script.defer = true;
                script.onerror = () => {
                    console.error('❌ فشل في إعادة تحميل Google Maps');
                    this.showMapError();
                };
                document.head.appendChild(script);
            }

            /**
             * فتح الاتجاهات
             */
            openDirections(lat, lng) {
                if (lat === 0 || lng === 0) {
                    alert('لا يوجد إحداثيات متاحة لهذا العميل');
                    return;
                }
                window.open(`https://www.google.com/maps?q=${lat},${lng}&z=17`, '_blank');
            }

            /**
             * إخفاء/إظهار علامة العميل
             */
            hideClientFromMap(clientId) {
                const markerItem = this.allMarkers.find(item =>
                    item.clientData && item.clientData.id == clientId
                );

                if (markerItem && markerItem.marker) {
                    markerItem.marker.setVisible(false);

                    // تحديث MarkerClusterer إذا كان موجوداً
                    if (this.markerCluster) {
                        this.markerCluster.removeMarker(markerItem.marker);
                    }
                }
            }

            showClientInMap(clientId) {
                const markerItem = this.allMarkers.find(item =>
                    item.clientData && item.clientData.id == clientId
                );

                if (markerItem && markerItem.marker) {
                    markerItem.marker.setVisible(true);

                    // تحديث MarkerClusterer إذا كان موجوداً
                    if (this.markerCluster) {
                        this.markerCluster.addMarker(markerItem.marker);
                    }
                }
            }

            /**
             * الحصول على لون آمن
             */
            getSafeColor(color) {
                const validHex = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;
                if (color && validHex.test(color)) return color;

                const standardColors = ['red', 'green', 'blue', 'yellow', 'orange', 'purple', 'pink', 'brown', 'black',
                    'white', 'gray', 'grey'
                ];
                if (color && standardColors.includes(color.toLowerCase())) return color;

                if (color && color.startsWith('rgb')) return color;

                return '#4361ee';
            }

            /**
             * تنظيف الموارد
             */
            cleanup() {
                this.clearMapMarkers();
                if (this.infoWindow) {
                    this.infoWindow.close();
                }
                this.allMarkers = [];
                this.isMapLoaded = false;
                this.isInitialized = false;
            }

            /**
             * تبديل وضع ملء الشاشة للخريطة
             */
            toggleFullscreen() {
                const mapContainer = document.getElementById('mapContainer');
                const fullscreenButton = document.getElementById('fullscreenButton');
                const fullscreenIcon = fullscreenButton ? fullscreenButton.querySelector('i') : null;

                if (!mapContainer) return;

                // Check if we're entering or exiting fullscreen
                const isEnteringFullscreen = !mapContainer.classList.contains('fullscreen');

                if (isEnteringFullscreen) {
                    // Enter fullscreen mode
                    mapContainer.classList.add('fullscreen');
                    document.body.style.overflow = 'hidden';
                    document.documentElement.style.overflow = 'hidden';

                    if (fullscreenIcon) {
                        fullscreenIcon.classList.remove('fa-expand');
                        fullscreenIcon.classList.add('fa-compress');
                    }

                    // Force fullscreen on mobile browsers that support it
                    if (mapContainer.requestFullscreen) {
                        mapContainer.requestFullscreen().catch(err => {
                            console.error('Error attempting to enable fullscreen:', err);
                        });
                    } else if (mapContainer.webkitRequestFullscreen) {
                        /* Safari */
                        mapContainer.webkitRequestFullscreen();
                    } else if (mapContainer.msRequestFullscreen) {
                        /* IE11 */
                        mapContainer.msRequestFullscreen();
                    }
                } else {
                    // Exit fullscreen mode
                    mapContainer.classList.remove('fullscreen');
                    document.body.style.overflow = '';
                    document.documentElement.style.overflow = '';

                    if (fullscreenIcon) {
                        fullscreenIcon.classList.remove('fa-compress');
                        fullscreenIcon.classList.add('fa-expand');
                    }

                    // Exit fullscreen if browser API was used
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        /* Safari */
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) {
                        /* IE11 */
                        document.msExitFullscreen();
                    }
                }

                // Force a resize and recenter of the map
                this.forceMapResize();
            }

            /**
             * Force a map resize and recenter
             */
            forceMapResize() {
                if (window.google && window.google.maps && this.map) {
                    // Small delay to ensure the container has resized
                    setTimeout(() => {
                        // Trigger resize event
                        google.maps.event.trigger(this.map, 'resize');

                        // Recenter the map
                        if (this.map.center) {
                            const center = this.map.getCenter();
                            this.map.setCenter(center);
                        }

                        // Additional check for mobile viewport height
                        const mapElement = document.getElementById('map');
                        if (mapElement) {
                            const vh = window.innerHeight * 0.01;
                            mapElement.style.height = `calc(${100 * vh}px - 0px)`;
                        }
                    }, 100);
                }
            }

            // Getters للوصول للحالة
            getIsMapLoaded() {
                return this.isMapLoaded;
            }

            getAllMarkers() {
                return this.allMarkers;
            }

            /**
             * عرض الموقع الحالي للمستخدم
             */
/**
 * عرض الموقع الحالي للمستخدم
 */
showCurrentLocation() {
    console.log('📍 بدء تحديد الموقع...');

    if (!navigator.geolocation) {
        console.error('❌ المتصفح لا يدعم تحديد الموقع');
        this.showLocationError('متصفحك لا يدعم خدمة تحديد الموقع');
        return;
    }

    const locationButton = document.getElementById('myLocationButton');
    let originalHtml = '';

    if (locationButton) {
        originalHtml = locationButton.innerHTML;
        locationButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        locationButton.disabled = true;
        console.log('⏳ عرض مؤشر التحميل');
    }

    const resetButton = () => {
        if (locationButton) {
            locationButton.innerHTML = originalHtml || '<i class="fas fa-location-arrow"></i>';
            locationButton.disabled = false;
        }
    };

    navigator.geolocation.getCurrentPosition(
        (position) => {
            console.log('✅ تم الحصول على الموقع:', position.coords);

            const userLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };

            // إزالة الماركر القديم
            if (this.currentUserMarker) {
                this.currentUserMarker.setMap(null);
                if (this.currentUserMarker.accuracyCircle) {
                    this.currentUserMarker.accuracyCircle.setMap(null);
                }
            }

            // إنشاء علامة الموقع الحالي
            this.currentUserMarker = new google.maps.Marker({
                position: userLocation,
                map: this.map,
                title: 'موقعي الحالي',
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 12,
                    fillColor: '#4285F4',
                    fillOpacity: 1,
                    strokeColor: 'white',
                    strokeWeight: 3
                },
                animation: google.maps.Animation.DROP,
                zIndex: 1000
            });

            // إضافة دائرة الدقة
            const accuracyCircle = new google.maps.Circle({
                map: this.map,
                center: userLocation,
                radius: position.coords.accuracy,
                fillColor: '#4285F4',
                fillOpacity: 0.1,
                strokeColor: '#4285F4',
                strokeOpacity: 0.3,
                strokeWeight: 1
            });

            this.currentUserMarker.accuracyCircle = accuracyCircle;

            // التوسيط على الموقع
            this.map.setCenter(userLocation);
            this.map.setZoom(15);

            // البحث عن أقرب العملاء


            resetButton();
        },
        (error) => {
            console.error('❌ خطأ في تحديد الموقع:', error);

            let errorMessage = 'فشل في تحديد موقعك';

            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage = 'تم رفض إذن الوصول للموقع. يرجى السماح بالوصول من إعدادات المتصفح';
                    console.error('❌ تم رفض الإذن');
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage = 'معلومات الموقع غير متوفرة';
                    console.error('❌ الموقع غير متوفر');
                    break;
                case error.TIMEOUT:
                    errorMessage = 'انتهت مهلة طلب تحديد الموقع';
                    console.error('❌ انتهت المهلة');
                    break;
            }

            this.showLocationError(errorMessage);
            resetButton();
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}
            /**
             * عرض معلومات الموقع الحالي للمستخدم
             */
            showUserLocationInfo(location, accuracy) {
                const contentString = `
            <div class="user-location-info">
                <h4><i class="fas fa-map-marker-alt"></i> موقعك الحالي</h4>
                <p><strong>خط العرض:</strong> ${location.lat.toFixed(6)}</p>
                <p><strong>خط الطول:</strong> ${location.lng.toFixed(6)}</p>
                <p class="accuracy"><strong>دقة الموقع:</strong> ${Math.round(accuracy)} متر</p>
            </div>
        `;

                // إنشاء نافذة معلومات للموقع الحالي
                if (this.userLocationInfoWindow) {
                    this.userLocationInfoWindow.close();
                }

                this.userLocationInfoWindow = new google.maps.InfoWindow({
                    content: contentString,
                    maxWidth: 300
                });

                // فتح نافذة المعلومات على علامة الموقع الحالي
                if (this.currentUserMarker) {
                    this.userLocationInfoWindow.open(this.map, this.currentUserMarker);
                }
            }
            /**
             * البحث عن أقرب العملاء
             */

            /**
             * حساب المسافة بين نقطتين (بالكيلومتر)
             */
            calculateDistance(lat1, lon1, lat2, lon2) {
                const R = 6371; // نصف قطر الأرض بالكيلومتر
                const dLat = this.toRad(lat2 - lat1);
                const dLon = this.toRad(lon2 - lon1);
                const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
                    Math.sin(dLon / 2) * Math.sin(dLon / 2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                return R * c;
            }

            toRad(deg) {
                return deg * (Math.PI / 180);
            }

            /**
             * التركيز على عميل معين
             */
            focusOnClient(clientId) {
                const markerItem = this.allMarkers.find(item =>
                    item.clientData && item.clientData.id == clientId
                );

                if (markerItem && markerItem.marker) {
                    this.map.setCenter(markerItem.marker.getPosition());
                    this.map.setZoom(15);
                    this.showClientInfo(markerItem.marker, markerItem.clientData);
                }
            }

            /**
             * عرض رسالة خطأ الموقع
             */
            showLocationError(message) {
                // يمكنك استخدام مكتبة التنبيهات المفضلة لديك
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في تحديد الموقع',
                        text: message,
                        confirmButtonText: 'حسناً'
                    });
                } else {
                    alert(message);
                }
            }

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
        }

        // تصدير الكلاس
        window.MapManager = MapManager;

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
            // محاولة استعادة حالة الخريطة أولاً
            const shouldRestoreMap = sessionStorage.getItem('mapState') !== null;

            console.log('🚀 بدء تحميل الصفحة...');
            console.log('📍 الراوتات المتاحة:', window.clientRoutes);

            initializeApp();

            // استعادة حالة الخريطة بعد التهيئة
            if (shouldRestoreMap && window.mapManager) {
                setTimeout(() => {
                    console.log('♻️ استعادة حالة الخريطة المحفوظة');
                    window.mapManager.restoreMapState();
                }, 1000);
            }

            // تحميل بيانات الخريطة الأولية بعد ثانيتين
            setTimeout(() => {
                if (window.mapManager && window.mapManager.getIsMapLoaded() && window.searchManager) {
                    console.log('⏱️ تحميل بيانات الخريطة الأولية...');
                    const filters = window.searchManager.getCurrentFilters();
                    console.log('📋 الفلاتر:', filters);
                    window.mapManager.loadMapData(filters);
                } else {
                    console.warn('⚠️ الخريطة أو مدير البحث غير جاهز');
                    console.log('🔍 حالة الخريطة:', {
                        mapManager: !!window.mapManager,
                        isMapLoaded: window.mapManager ? window.mapManager.getIsMapLoaded() : false,
                        searchManager: !!window.searchManager
                    });
                }
            }, 2000);
        });

        // حفظ حالة الخريطة قبل مغادرة الصفحة
        window.addEventListener('beforeunload', function() {
            if (window.mapManager && window.mapManager.getIsMapLoaded()) {
                window.mapManager.saveMapState();
            }
        });

        // حفظ حالة الخريطة عند الضغط على أي رابط داخلي
        $(document).on('click',
            'a[href]:not([target="_blank"]):not([href^="#"]):not([href^="tel:"]):not([href^="mailto:"])',
            function(e) {
                const href = $(this).attr('href');

                // تحقق من أن الرابط داخلي (نفس الدومين)
                if (href && !href.startsWith('http') && window.mapManager && window.mapManager.getIsMapLoaded()) {
                    window.mapManager.saveMapState();
                }
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

            // معالجُ الترقيم يعيش في `PaginationManager.bindEvents()` وحده —
            // راجع التعليق في `partials/pagination_controls.blade.php`

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
            script.src =
                `https://maps.googleapis.com/maps/api/js?key=${window.googleMapsApiKey}&libraries=places&callback=window.initMap`;
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
                    if (window.mapManager && !window.mapManager.getIsMapLoaded() && typeof window.initMap ===
                        'function') {
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
                hiddenClientsCount: window.hiddenClientsManager ? window.hiddenClientsManager.getHiddenClients()
                    .length : 0
            };

            console.log('📊 حالة التطبيق:', status);
            return status;
        };

        // تصدير الدوال للاستخدام العام
        window.initializeApp = initializeApp;
        window.cleanup = cleanup;
    </script>

    <!-- Bulk Status Update Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllClients');
            const bulkUpdateBtn = document.getElementById('bulkUpdateStatusBtn');
            const countSpan = document.getElementById('selectedClientsCount');
            const bulkForm = document.getElementById('bulkStatusForm');
            
            function updateBulkButtonState() {
                const selectedCheckboxes = document.querySelectorAll('.client-select-checkbox:checked');
                const totalCheckboxes = document.querySelectorAll('.client-select-checkbox');
                
                if (selectedCheckboxes.length > 0) {
                    bulkUpdateBtn.classList.remove('d-none');
                    countSpan.textContent = selectedCheckboxes.length;
                } else {
                    bulkUpdateBtn.classList.add('d-none');
                }
                
                if (totalCheckboxes.length > 0 && selectedCheckboxes.length === totalCheckboxes.length) {
                    selectAllCheckbox.checked = true;
                } else {
                    selectAllCheckbox.checked = false;
                }
            }
            
            // Click Select All
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.client-select-checkbox');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateBulkButtonState();
                });
            }
            
            // Handle individual checkbox change (delegated for AJAX compatibility)
            $(document).on('change', '.client-select-checkbox', function() {
                updateBulkButtonState();
            });
            
            // Uncheck all on AJAX loading / search completion / pagination change
            $(document).ajaxComplete(function() {
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                if (bulkUpdateBtn) bulkUpdateBtn.classList.add('d-none');
            });
            
            // Submit Bulk Form
            if (bulkForm) {
                bulkForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const selectedCheckboxes = document.querySelectorAll('.client-select-checkbox:checked');
                    const clientIds = Array.from(selectedCheckboxes).map(cb => cb.value);
                    const statusId = document.getElementById('bulk_status_id').value;
                    
                    if (clientIds.length === 0 || !statusId) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى اختيار عميل واحد على الأقل وتحديد الحالة الجديدة.',
                            confirmButtonText: 'موافق'
                        });
                        return;
                    }
                    
                    const submitBtn = bulkForm.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> جارٍ التحديث...';
                    
                    fetch(window.clientRoutes.bulkUpdateStatus, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            client_ids: clientIds,
                            status_id: statusId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'تحديث الحالة';
                        
                        if (data.success) {
                            closeBsModal('bulkStatusModal');
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'نجح!',
                                text: data.message,
                                confirmButtonText: 'موافق'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ!',
                                text: data.message,
                                confirmButtonText: 'موافق'
                            });
                        }
                    })
                    .catch(error => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'تحديث الحالة';
                        console.error('Error updating bulk status:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: 'حدث خطأ غير متوقع أثناء التحديث.',
                            confirmButtonText: 'موافق'
                        });
                    });
                });
            }
        });
    </script>
@endsection
