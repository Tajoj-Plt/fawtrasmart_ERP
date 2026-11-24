/*
    ═══════════ الحالةُ ومراجعُ العناصر ═══════════

    يُنفَّذ عند التحميل لا عند النداء — فملفُّه يسبق الجميع،
    ومراجعُ العناصر تُقرأ بعد رسم الترميز.
*/


/*
    ═══════════ رمزُ العملة ═══════════

    كانت «ر.س» مكتوبةً حرفياً في ستٍّ وخمسين موضعاً — في السلّة والمجاميع
    ونافذة الدفع والمرتجع وقائمة الفواتير. فمنشأةٌ تبيع بغيرِ الريال ترى
    أرقامَها موسومةً بعملةٍ ليست عملتها.

    والرمزُ يأتي من الخادم عبر `appData` — من `SystemCurrency` لا من نصّ.
*/
const CURRENCY = (typeof appData !== 'undefined' && appData.currencySymbol) || 'ر.س';

/** مبلغٌ منسَّقٌ برمز عملته. */
function money(value) {
    return (Number(value) || 0).toFixed(2) + ' ' + CURRENCY;
}

/*
    ═══════════ هل لهذا الصنف رصيد؟ ═══════════

    كان الجوابُ يُستنتج بمقارنة: `product.type === 'service'`.

    وقيمةُ العمود في القاعدة **`services`** بالجمع — والتعدادُ أربعةٌ:
    `products` · `services` · `compiled` · `digital`.

    فالمقارنةُ لا تصدق أبداً. وأثرُها أنّ **الخدمة لا تُباع من نقطة البيع
    إطلاقاً**: تُعرض برصيد صفرٍ ووسمِ «نفدت الكمّية»، ويمنع فحصُ التوفّر
    إضافتَها إلى السلّة. والرقميُّ مثلُها ولم يكن مذكوراً أصلاً.

    والخادمُ يُرسل `tracks_stock` صريحةً الآن من `ProductType::tracksStock()`
    — فلا تُخمَّن. والسقوطُ إلى الأسماء احتياطٌ لصفحةٍ مخبّأةٍ في المتصفّح
    تحمل بياناتٍ قبل النشر.
*/
const STOCKLESS_TYPES = ['services', 'service', 'digital'];

function tracksStock(product) {
    if (! product) {
        return false;
    }

    if (typeof product.tracks_stock === 'boolean') {
        return product.tracks_stock;
    }

    return ! STOCKLESS_TYPES.includes(String(product.type || '').toLowerCase());
}

/*
    ═══════════ التشخيص ═══════════

    خمسةٌ وعشرون `console.log` تعمل دائماً — منها ما يُطبع مع كل تغييرٍ في
    السلّة، وما يطبع كائنَ التطبيق كاملاً عند كل فتحة.

    والضررُ ليس في الطباعة وحدها: وسائطُها تُحسب قبل النداء، فـ`toFixed`
    يجري مرّتين لسطرٍ لا يقرؤه أحد. وطرفيّةٌ ممتلئةٌ بالضجيج تُخفي الخطأ
    الحقيقي حين يقع.

    ولا تُحذف: تُطفأ. ومن أراد إشعالَها في جهازٍ بعينه:

        localStorage.setItem('pos_debug', '1')

    و`console.error` تبقى على حالها — الخطأُ يُرى دائماً.
*/
const POS_DEBUG = (function () {
    try {
        return localStorage.getItem('pos_debug') === '1';
    } catch (e) {
        return false;   // وضعُ التصفّح الخاص يمنع التخزين
    }
})();

function posLog() {
    if (POS_DEBUG) {
        console.log.apply(console, arguments);
    }
}

/*
    ═══════════ الهروبُ من الترميز ═══════════

    أسماءُ المنتجات والعملاء تُحقن في `innerHTML` وفي سمات `title`
    و`onclick` بلا تهذيب. واسمٌ فيه علامةُ اقتباسٍ يكسر السمةَ فيتعطّل
    الزرّ، واسمٌ فيه وسمٌ يُنفَّذ.

    والاسمُ يأتي من قاعدة البيانات — يكتبه موظّفُ المخزون، لا الكاشير.
*/
function esc(text) {
    return String(text == null ? '' : text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// حالة التطبيق
let currentView = 'categories';
let currentOrder = 1;
let orders = {
    1: {
        items: [],
        client: null,
        discount: { type: 'amount', value: 0 }
    }
};
let searchTimeout;
let selectedCategory = null;
let clientSearchTimeout;

// متغيرات الفواتير مع Pagination
let allInvoices = [];
let currentInvoicesPage = 1;
let invoicesLoaded = false; // هل تم تحميل الفواتير مسبقاً؟
let invoicesPerPage = 10;
let isLoadingInvoices = false;

// متغيرات مستمع الباركود العالمي
let barcodeBuffer = '';
let barcodeTimeout = null;
const BARCODE_TIMEOUT_MS = 100; // الوقت الأقصى بين الأحرف (قارئ الباركود سريع جداً)
const MIN_BARCODE_LENGTH = 3; // الحد الأدنى لطول الباركود

// عناصر DOM
const searchInput = document.getElementById('searchInput');
const categoriesSection = document.getElementById('categoriesSection');
const productsSection = document.getElementById('productsSection');
const orderContent = document.getElementById('orderContent');
const selectedClientName = document.getElementById('selectedClientName');
const clientSelector = document.getElementById('clientSelector');
const clientDropdown = document.getElementById('clientDropdown');
const clientSearchInput = document.getElementById('clientSearchInput');
const clientResults = document.getElementById('clientResults');

// تهيئة التطبيق
document.addEventListener('DOMContentLoaded', function() {
    posLog('بيانات التطبيق:', appData);
    initializeApp();
    setupEventListeners();
    setupClientSelector();
    setupGlobalBarcodeListener(); // مستمع الباركود العالمي
    updateDateTime();
    setInterval(updateDateTime, 60000);

    // Register Service Worker for PWA
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(appData.urls.serviceWorker)
            .then(reg => posLog('Service Worker Registered!', reg))
            .catch(err => posLog('Service Worker Registration Failed!', err));
    }

    // PWA Install Logic
    let deferredPrompt;
    const installBtnContainer = document.getElementById('pwaInstallBtnContainer');
    const installBtn = document.getElementById('pwaInstallBtn');

    // Force display initially for potential "manual" instructions or feedback
    if(installBtnContainer) installBtnContainer.style.display = 'block';

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        posLog('beforeinstallprompt fired');
    });

    if (installBtn) {
        installBtn.addEventListener('click', async (e) => {
            e.preventDefault();

            if (deferredPrompt) {
                // Hide the app provided install promotion
                // installBtnContainer.style.display = 'none'; // Keep it or hide it? Let's keep it until installed
                 deferredPrompt.prompt();
                // Wait for the user to respond to the prompt
                const { outcome } = await deferredPrompt.userChoice;
                posLog(`User response to the install prompt: ${outcome}`);
                // We've used the prompt, and can't use it again, throw it away
                deferredPrompt = null;
                if(outcome === 'accepted') {
                    installBtnContainer.style.display = 'none';
                }
            } else {
                // Fallback message if the prompt isn't valid (e.g. not HTTPS, already installed, or not supported)
                if (window.matchMedia('(display-mode: standalone)').matches) {
                     Swal.fire({
                        icon: 'info',
                        title: 'تم التثبيت',
                        text: 'التطبيق مثبت بالفعل ويعمل في نافذة مستقلة.',
                        confirmButtonText: 'حسناً'
                    });
                } else {
                     Swal.fire({
                        icon: 'info',
                        title: 'تثبيت التطبيق',
                        text: 'للتثبيت، انقر على أيقونة التثبيت في شريط عنوان المتصفح (Chrome/Edge)، أو تأكد من فتح الموقع عبر HTTPS.',
                        confirmButtonText: 'حسناً'
                    });
                }
            }
        });
    }

    window.addEventListener('appinstalled', () => {
        // Hide the app-provided install promotion
        if(installBtnContainer) installBtnContainer.style.display = 'none';
        // Clear the deferredPrompt so it can be garbage collected
        deferredPrompt = null;
        posLog('PWA was installed');
    });

    // Check if already in standalone mode
    if (window.matchMedia('(display-mode: standalone)').matches) {
        if(installBtnContainer) installBtnContainer.style.display = 'none';
    }
});
