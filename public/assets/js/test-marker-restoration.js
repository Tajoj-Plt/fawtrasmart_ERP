// 🧪 اختبار سريع - فحص استعادة الماركرز

/**
 * اختبار 1: فحص البيانات المحفوظة
 * اكتب في Console:
 */
function testCachedData() {
    console.log('🔍 فحص البيانات المحفوظة...');

    const data = window.stateManager.restoreClientsData();
    console.log('البيانات:', data);
    console.log('عدد العملاء:', data ? data.length : 0);

    if (data && data.length > 0) {
        console.log('✅ البيانات موجودة');
        console.log('أول عميل:', data[0]);
        return true;
    } else {
        console.log('❌ لا توجد بيانات');
        return false;
    }
}

/**
 * اختبار 2: فحص حالة الخريطة
 */
function testMapState() {
    console.log('🗺️ فحص حالة الخريطة...');

    const state = {
        mapLoaded: window.mapManager.isMapLoaded,
        hasMap: !!window.mapManager.map,
        markers: window.mapManager.allMarkers.length,
        cluster: !!window.mapManager.markerCluster
    };

    console.log('حالة الخريطة:', state);

    if (state.mapLoaded && state.hasMap) {
        console.log('✅ الخريطة محملة');
        return true;
    } else {
        console.log('❌ الخريطة غير محملة');
        return false;
    }
}

/**
 * اختبار 3: اختبار استعادة الماركرز يدويًا
 */
function testRestoreMarkers() {
    console.log('📍 اختبار استعادة الماركرز...');

    // التأكد من أن الخريطة محملة
    if (!window.mapManager.isMapLoaded) {
        console.log('❌ الخريطة غير محملة بعد');
        return;
    }

    // استدعاء استعادة البيانات
    const restored = window.stateManager.restoreAndDisplayClientsData();
    console.log('هل تم استعادة البيانات؟', restored);

    // انتظار ثانية واحدة ثم فحص الماركرز
    setTimeout(() => {
        console.log('عدد الماركرز بعد 1 ثانية:', window.mapManager.allMarkers.length);
    }, 1000);
}

/**
 * اختبار 4: محاكاة زر الرجوع
 */
function testBackButton() {
    console.log('🔙 محاكاة زر الرجوع...');

    // تشغيل حدث popstate
    window.dispatchEvent(new PopStateEvent('popstate', {
        state: {
            mapOpen: true,
            filters: {}
        }
    }));

    console.log('✅ تم محاكاة زر الرجوع');
    console.log('انتظر ثانية أو ثانيتين لرؤية الماركرز...');
}

/**
 * اختبار 5: فحص شامل
 */
function runFullTest() {
    console.log('🧪 تشغيل الاختبار الشامل...');
    console.log('='.repeat(50));

    // اختبار 1
    const hasData = testCachedData();
    console.log('');

    // اختبار 2
    const mapReady = testMapState();
    console.log('');

    // اختبار 3
    if (mapReady && hasData) {
        testRestoreMarkers();
    }

    console.log('='.repeat(50));
    console.log('✅ انتهى الاختبار الشامل');
}

/**
 * اختبار 6: حل سريع إذا فشل الاختبار
 */
function quickFix() {
    console.log('🔧 تطبيق حل سريع...');

    // مسح الكاش وإعادة تحميل
    sessionStorage.clear();
    console.log('✅ تم مسح الكاش');
    console.log('⚠️ جاري إعادة التحميل...');

    setTimeout(() => {
        location.reload();
    }, 1000);
}

// 🚀 استخدام الاختبارات:
//
// في Console:
// 1. testCachedData()        - فحص البيانات
// 2. testMapState()           - فحص الخريطة
// 3. testRestoreMarkers()     - استعادة الماركرز
// 4. testBackButton()         - محاكاة الرجوع
// 5. runFullTest()            - اختبار شامل
// 6. quickFix()               - حل سريع
