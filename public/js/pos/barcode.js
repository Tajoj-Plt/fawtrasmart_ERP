/*
    ═══════════ قارئُ الباركود ═══════════

    القارئُ يكتب أحرفاً متتابعةً بسرعةٍ لا تبلغها يد، وهذا ما
    يميّزه عن الكتابة اليدوية.
*/

// ==========================================
// مستمع الباركود العالمي (يعمل بدون التركيز على حقل الإدخال)
// ==========================================
function setupGlobalBarcodeListener() {
    document.addEventListener('keydown', handleGlobalKeydown);
    posLog('✅ تم تفعيل مستمع الباركود العالمي');
}

function handleGlobalKeydown(e) {
    // تجاهل إذا كان التركيز على حقل إدخال (سيتم التعامل معه بواسطة handleSearch)
    const activeElement = document.activeElement;
    const isInputFocused = activeElement && (
        activeElement.tagName === 'INPUT' || 
        activeElement.tagName === 'TEXTAREA' ||
        activeElement.isContentEditable
    );

    // تجاهل إذا كان Modal مفتوح
    const isModalOpen = document.querySelector('.modal.show') !== null;

    // إذا كان التركيز على حقل إدخال أو Modal مفتوح، لا نتدخل
    if (isInputFocused || isModalOpen) {
        return;
    }

    // تجاهل مفاتيح التحكم (Shift, Ctrl, Alt, etc.)
    if (e.key.length > 1 && e.key !== 'Enter') {
        // مفاتيح خاصة مثل Shift, Ctrl, Alt, F1-F12, etc.
        // نتجاهلها إلا Enter
        if (['Shift', 'Control', 'Alt', 'Meta', 'CapsLock', 'Tab', 'Escape', 
             'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 
             'Home', 'End', 'PageUp', 'PageDown', 'Insert', 'Delete',
             'F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11', 'F12'
            ].includes(e.key)) {
            return;
        }
    }

    // مسح المؤقت السابق
    if (barcodeTimeout) {
        clearTimeout(barcodeTimeout);
    }

    // إذا تم الضغط على Enter
    if (e.key === 'Enter') {
        e.preventDefault();

        // إذا كان هناك باركود في البفر
        if (barcodeBuffer.length >= MIN_BARCODE_LENGTH) {
            posLog('🔍 تم اكتشاف باركود:', barcodeBuffer);
            processGlobalBarcode(barcodeBuffer);
        }

        // مسح البفر
        barcodeBuffer = '';
        return;
    }

    // إضافة الحرف للبفر
    if (e.key.length === 1) { // أحرف عادية فقط
        barcodeBuffer += e.key;

        // تعيين مؤقت لمسح البفر إذا توقف الإدخال
        barcodeTimeout = setTimeout(() => {
            // إذا كان البفر يحتوي على باركود صالح، ابحث عنه
            if (barcodeBuffer.length >= MIN_BARCODE_LENGTH) {
                posLog('🔍 باركود (بدون Enter):', barcodeBuffer);
                processGlobalBarcode(barcodeBuffer);
            }
            barcodeBuffer = '';
        }, BARCODE_TIMEOUT_MS * 5); // انتظر 500ms بعد آخر حرف
    }
}

// معالجة الباركود المكتشف عالمياً
async function processGlobalBarcode(barcode) {
    posLog('📦 معالجة الباركود:', barcode);

    try {
        const response = await fetch(`${appData.urls.productByBarcode}?barcode=${encodeURIComponent(barcode)}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': appData.csrfToken,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        posLog('📦 نتيجة البحث:', data);

        if (data.success && data.product) {
            const product = data.product;

            // ملاحظة مهمة: لا نقوم بتحديث appData.products
            // لأن المنتج قد يكون بوحدة مختلفة (كرتون، علبة، إلخ)
            // ويجب أن يبقى سعر المنتج الأساسي في الصفحة كما هو

            // إضافة المنتج للسلة (مع بيانات الوحدة من الباركود)
            addProductToOrderDirect(product);

            // تشغيل صوت
            playBeepSound();
        } else {
            // لم يتم العثور على المنتج
            showNotification(`لم يتم العثور على منتج بالباركود: ${barcode}`, 'warning');
        }
    } catch (error) {
        console.error('❌ خطأ في البحث بالباركود:', error);
        showNotification('حدث خطأ أثناء البحث بالباركود', 'error');
    }
}

// تشغيل صوت بسيط عند إضافة منتج بالباركود
function playBeepSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        oscillator.frequency.value = 800;
        oscillator.type = 'sine';

        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.1);
    } catch (e) {
        // تجاهل أخطاء الصوت
    }
}
