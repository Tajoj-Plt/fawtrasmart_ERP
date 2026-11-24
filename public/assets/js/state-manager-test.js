/**
 * State Manager Testing - اختبار نظام مدير الحالة
 * استخدم هذا الملف للتحقق من عمل نظام الحفظ والاستعادة
 */

// اختبر في Console باستخدام هذه الأوامر:

// 1. التحقق من وجود StateManager
console.log('🔍 فحص StateManager:');
console.log('StateManager موجود؟', !!window.stateManager);
console.log('stateManager:', window.stateManager);

// 2. فحص الحالة الحالية
console.log('\n📊 الحالة الحالية:');
const currentState = window.stateManager.getState();
console.log(currentState);

// 3. حفظ الحالة يدوياً
console.log('\n💾 حفظ الحالة:');
const savedState = window.stateManager.saveMapState();
console.log('تم الحفظ:', savedState);

// 4. استرجاع الحالة
console.log('\n📂 استرجاع الحالة:');
const restoredState = window.stateManager.restoreMapState();
console.log('الحالة المسترجعة:', restoredState);

// 5. فحص SessionStorage
console.log('\n🗂️ محتوى SessionStorage:');
console.log('clientsMapState:', sessionStorage.getItem('clientsMapState'));
console.log('clientsDataCache:', sessionStorage.getItem('clientsDataCache'));

// 6. اختبار سيناريو الملاحة
console.log('\n🧪 اختبار سيناريو الملاحة:');
console.log('خطوات الاختبار:');
console.log('1. انقر على "عرض الخريطة" لفتح الخريطة');
console.log('2. انقر على "بحث" لتحميل العملاء');
console.log('3. انقر على رابط آخر للانتقال لصفحة مختلفة');
console.log('4. اضغط زر الرجوع في المتصفح');
console.log('5. تحقق من أن الخريطة تفتح تلقائياً والعملاء يظهرون');

// 7. دالة مساعدة للمراقبة
window.monitorStateManager = function() {
    console.log('🔄 مراقبة مدير الحالة الحي...');
    
    setInterval(() => {
        const state = window.stateManager.getState();
        console.clear();
        console.log('⏱️ تحديث حي - الوقت:', new Date().toLocaleTimeString('ar-SA'));
        console.log('---');
        console.log('🗺️ الخريطة مفتوحة:', state.current.mapOpen);
        console.log('📍 موقع الخريطة:', state.current.mapCenter);
        console.log('🔍 مستوى التكبير:', state.current.mapZoom);
        console.log('💾 حالة محفوظة:', !!state.saved);
        console.log('📊 بيانات مخزنة:', !!state.cached);
        console.log('---');
        console.log('لإيقاف المراقبة، اضغط Ctrl+Shift+J وأغلق الـ Console');
    }, 2000);
};

// 8. دالة لمسح البيانات
window.clearAppState = function() {
    console.log('🗑️ مسح جميع البيانات المحفوظة...');
    window.stateManager.clearAllState();
    console.log('✅ تم المسح');
};

// 9. دالة للتحقق من التخزين
window.checkStorage = function() {
    console.log('📊 معلومات التخزين:');
    
    let totalSize = 0;
    for (let key in sessionStorage) {
        if (sessionStorage.hasOwnProperty(key)) {
            const size = new Blob([sessionStorage[key]]).size;
            totalSize += size;
            console.log(`${key}: ${(size / 1024).toFixed(2)} KB`);
        }
    }
    
    console.log(`إجمالي الحجم: ${(totalSize / 1024).toFixed(2)} KB`);
};

// رسالة ترحيب
console.log('%c🎉 State Manager جاهز للاختبار!', 'color: green; font-size: 16px; font-weight: bold;');
console.log('%cاستخدم الأوامر التالية في Console:', 'color: blue; font-weight: bold;');
console.log('%c• window.stateManager.getState() - للحصول على الحالة الحالية', 'color: #666;');
console.log('%c• window.monitorStateManager() - لمراقبة الحالة بشكل حي', 'color: #666;');
console.log('%c• window.checkStorage() - لفحص حجم التخزين', 'color: #666;');
console.log('%c• window.clearAppState() - لمسح جميع البيانات', 'color: #666;');
