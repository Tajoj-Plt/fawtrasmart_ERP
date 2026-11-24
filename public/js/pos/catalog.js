/*
    ═══════════ التصنيفاتُ والمنتجاتُ والبحث ═══════════

    التصنيفُ المختار يقيّد البحثَ والعرضَ معاً — وهو ما كانت
    نسخةُ الدوالّ المكرَّرة تُسقطه.
*/

/*
    ═══════════ ما يشبه الباركود ═══════════

    الباركودُ أرقامٌ متّصلة — أو أرقامٌ وحروفٌ لاتينية في بعض الترميزات —
    بلا فراغٍ ولا حرفٍ عربي، وطولُه ستٌّ فأكثر (EAN-8 أقصرُ الشائع).

    واسمُ المنتج لا يشبهه أبداً.
*/
const BARCODE_SHAPE = /^[0-9A-Za-z-]{6,}$/;

function looksLikeBarcode(text) {
    return BARCODE_SHAPE.test(text) && /[0-9]/.test(text);
}

/*
    ═══════════ البحثُ في حقل الشاشة ═══════════

    **ما كان**: كلُّ ضغطةِ مفتاحٍ — بعد ثلثِ ثانيةٍ من السكون — تُطلق نداءَ
    شبكةٍ إلى `product-by-barcode`، ثم تنتظر ردَّه، ثم تسقط إلى البحث
    العادي. فكتابةُ «قهوة» تسأل الخادمَ عن باركودٍ اسمُه «قهوة».

    وأثرُه مضاعف: رحلةٌ مهدورةٌ لكل كلمة، ونتائجُ البحث لا تظهر حتى تعود.
    وعلى شبكةٍ بطيئةٍ يبدو البحثُ معطَّلاً.

    **وما صار**: الشكلُ يحسم قبل الشبكة.

      • ما يشبه الباركود ⇐ يُسأل عنه الخادم (ماسحٌ ألصقه، أو رقمٌ كُتب).
      • وما لا يشبهه ⇐ بحثٌ مباشر، بلا رحلة.
      • و**Enter** يُجرّب الباركودَ دائماً: ضغطُها قصدٌ صريح.

    وقارئُ الباركود له مستمعُه العام (`setupGlobalBarcodeListener`) يميّزه
    بسرعة الطباعة — فلا حاجةَ لأن يكون الحقلُ قارئاً ثانياً.
*/
function handleSearch(e) {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();

    // Enter قصدٌ صريح: يُجرَّب الباركودُ مهما كان الشكل
    if (e.type === 'keydown' && e.key === 'Enter') {
        e.preventDefault();
        searchByBarcodeAndAdd(query);
        return;
    }

    searchTimeout = setTimeout(() => {
        if (looksLikeBarcode(query)) {
            searchByBarcodeAndAdd(query);
        } else {
            performSearch(query);
        }
    }, 250);
}

// البحث بالباركود وإضافة المنتج مباشرة للسلة
async function searchByBarcodeAndAdd(barcode) {
    if (!barcode || barcode.length < 2) {
        performSearch(barcode);
        return;
    }

    try {
        const response = await fetch(`${appData.urls.productByBarcode}?barcode=${encodeURIComponent(barcode)}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': appData.csrfToken,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        posLog('نتيجة البحث بالباركود:', data);

        if (data.success && data.product) {
            // وجدنا منتج بالباركود - إضافته مباشرة للسلة
            const product = data.product;
            posLog('المنتج:', product);

            // ملاحظة مهمة: لا نقوم بتحديث appData.products لأن ذلك سيغير سعر المنتج في صفحة العرض
            // المنتج المُرجع من الباركود قد يكون بوحدة مختلفة (كرتون، علبة، إلخ)
            // ويجب أن يبقى سعر المنتج الأساسي في appData.products كما هو

            // إضافة المنتج للسلة مباشرة (مع بيانات الوحدة من الباركود)
            addProductToOrderDirect(product);

            // مسح حقل البحث
            searchInput.value = '';

            // تشغيل صوت بسيط للإشارة للمستخدم
            playBeepSound();
        } else {
            // لم نجد بالباركود - متابعة البحث العادي
            performSearch(barcode);
        }
    } catch (error) {
        console.error('خطأ في البحث بالباركود:', error);
        // في حالة الخطأ، متابعة البحث العادي
        performSearch(barcode);
    }
}

// تحميل التصنيفات
function loadCategories() {
    displayCategories(appData.categories);
}

function displayCategories(categories) {
    const grid = document.getElementById('categoriesGrid');

    if (!categories || categories.length === 0) {
        grid.innerHTML = '<div class="empty-state"><i class="fas fa-folder-open"></i><p>لا توجد تصنيفات</p></div>';
        return;
    }

    // التصنيفات محملة بالفعل في HTML - نحتاج فقط لتحديث الفلترة
    const existingCards = grid.querySelectorAll('.category-card');
    existingCards.forEach(card => {
        const categoryId = card.dataset.categoryId;
        const categoryExists = categories.some(cat => cat.id == categoryId);
        card.style.display = categoryExists ? 'flex' : 'none';
    });
}

// اختيار تصنيف وتحميل منتجاته من الخادم
function selectCategory(categoryId) {
    posLog('تم اختيار التصنيف:', categoryId);
    selectedCategory = categoryId;

    // تحديث حالة التصنيفات بصرياً
    document.querySelectorAll('.category-card').forEach(card => {
        card.classList.remove('active');
    });
    const selectedCard = document.querySelector(`[data-category-id="${categoryId}"]`);
    if (selectedCard) {
        selectedCard.classList.add('active');
    }

    // التبديل إلى عرض المنتجات بدون تحميل (لتجنب الطلب المزدوج)
    currentView = 'products';
    document.querySelectorAll('.btn-search').forEach(btn => btn.classList.remove('active'));
    document.getElementById('productsBtn')?.classList.add('active');
    searchInput.placeholder = 'البحث في منتجات التصنيف...';
    document.querySelectorAll('.content-section').forEach(section => section.style.display = 'none');
    document.getElementById('productsSection').style.display = 'block';
    searchInput.value = '';

    // تحميل منتجات التصنيف باستخدام API (طلب واحد فقط)
    loadProductsByCategory(categoryId);

    // عرض رسالة تأكيد
    const categoryName = selectedCard ? selectedCard.querySelector('h6').textContent : 'التصنيف';
    showNotification(`تم اختيار تصنيف: ${categoryName}`, 'info');
}

// تحميل المنتجات بناءً على التصنيف باستخدام API
function loadProductsByCategory(categoryId) {
    const productsGrid = document.getElementById('productsGrid');

    // عرض حالة التحميل
    productsGrid.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p class="mt-2">جاري تحميل منتجات التصنيف...</p>
        </div>
    `;

    // إرسال طلب لجلب منتجات التصنيف باستخدام URL الصحيح
    fetch(`${appData.urls.productsByCategory}?category_id=${categoryId}`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': appData.csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        posLog('منتجات التصنيف:', data);
        if (data.success && data.products) {
            if (data.products.length > 0) {
                displayProductsWithHeader(data.products, categoryId);
            } else {
                displayEmptyProducts('لا توجد منتجات في هذا التصنيف', categoryId);
            }
        } else {
            displayProductsError('فشل في تحميل منتجات التصنيف', categoryId);
        }
    })
    .catch(error => {
        console.error('خطأ في تحميل منتجات التصنيف:', error);
        displayProductsError(error.message, categoryId);
    });
}

// عرض المنتجات مع رأس يوضح التصنيف المختار
function displayProductsWithHeader(products, categoryId) {
    const grid = document.getElementById('productsGrid');

    // الحصول على اسم التصنيف
    const categoryCard = document.querySelector(`[data-category-id="${categoryId}"]`);
    const categoryName = categoryCard ? categoryCard.querySelector('h6').textContent : 'التصنيف المختار';

    // إنشاء رأس التصفية
    const headerHtml = `

    `;

    // إنشاء شبكة المنتجات
    const productsHtml = products.map(product => {
        const stockQty = parseFloat(product.stock_quantity || 0);
        // ما لا رصيدَ له لا ينفد
        const holdsStock = tracksStock(product);
        const isOutOfStock = stockQty <= 0 && holdsStock;
        const stockClass = isOutOfStock ? 'out-of-stock' : (stockQty <= 5 ? 'low-stock' : '');
        const stockBadge = holdsStock ? `<span class="stock-badge ${stockClass}">${stockQty}</span>` : '';

        return `
        <div class="product-card fade-in ${isOutOfStock ? 'disabled-product' : ''}" 
             data-product-id="${product.id}" 
             data-stock="${stockQty}"
             data-type="${product.type || 'products'}">
            ${isOutOfStock ? '<div class="out-of-stock-overlay">نفذت الكمية</div>' : ''}
            <img src="${getProductImage(product)}" 
                 alt="${product.name}" class="product-image"
                 onerror="this.src='/assets/uploads/no_image.jpg'">
            <div class="product-name" title="${esc(product.name)}">${esc(product.name)}</div>
            <div class="product-info">
                <span class="product-price">${money(parseFloat(product.sale_price || 0))}</span>
                ${stockBadge}
                <button class="info-btn" title="تفاصيل المنتج" onclick="showProductInfo(${product.id})">
                    <i class="fas fa-info-circle"></i>
                </button>
            </div>
        </div>
    `}).join('');

    // عرض المحتوى
    grid.innerHTML = headerHtml + '<div class="products-grid">' + productsHtml + '</div>';
}

// عرض رسالة فارغة مع خيارات
function displayEmptyProducts(message = 'لا توجد منتجات', categoryId = null) {
    const productsGrid = document.getElementById('productsGrid');

    let headerHtml = '';
    if (categoryId) {
        const categoryCard = document.querySelector(`[data-category-id="${categoryId}"]`);
        const categoryName = categoryCard ? categoryCard.querySelector('h6').textContent : 'التصنيف';

        headerHtml = `

        `;
    }

    productsGrid.innerHTML = headerHtml + `
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <p>${message}</p>

        </div>
    `;
}

// عرض خطأ مع خيارات إعادة المحاولة
function displayProductsError(errorMessage, categoryId = null) {
    const productsGrid = document.getElementById('productsGrid');

    productsGrid.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-exclamation-triangle text-warning"></i>
            <p>خطأ في تحميل المنتجات</p>
            <small class="text-muted">${errorMessage}</small>
            <div class="mt-3">
                ${categoryId ? `
                    <button class="btn btn-primary me-2" onclick="loadProductsByCategory(${categoryId})">
                        <i class="fas fa-redo"></i> إعادة المحاولة
                    </button>
                ` : ''}
                <button class="btn btn-secondary" onclick="clearCategorySelection()">
                    <i class="fas fa-list"></i> عرض جميع المنتجات
                </button>
            </div>
        </div>
    `;
}

// إلغاء اختيار التصنيف وعرض جميع المنتجات
function clearCategorySelection() {
    selectedCategory = null;

    // إزالة التحديد من جميع التصنيفات
    document.querySelectorAll('.category-card').forEach(card => {
        card.classList.remove('active');
    });

    // تحديث placeholder البحث
    if (currentView === 'products') {
        searchInput.placeholder = 'البحث في المنتجات...';
    }

    // تحميل جميع المنتجات
    displayProducts(appData.products);

    showNotification('تم إلغاء اختيار التصنيف - عرض جميع المنتجات', 'info');
}

// تحديث دالة loadProducts للتحقق من التصنيف المختار
function loadProducts() {
    if (selectedCategory) {
        // إذا كان هناك تصنيف مختار، استخدمه
        loadProductsByCategory(selectedCategory);
    } else {
        // عرض جميع المنتجات
        displayProducts(appData.products);
    }
}

// تحديث دالة switchView للتعامل مع التصنيف المختار
function switchView(view) {
    posLog('تبديل العرض إلى:', view);
    currentView = view;

    // تحديث الأزرار
    document.querySelectorAll('.btn-search').forEach(btn => {
        btn.classList.remove('active');
    });

    const btnId = view + 'Btn';
    const activeBtn = document.getElementById(btnId);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }

    // تحديث placeholder البحث
    const placeholders = {
        categories: 'البحث في التصنيفات...',
        products: selectedCategory ? 'البحث في منتجات التصنيف...' : 'البحث في المنتجات...',
        clients: 'البحث في العملاء...',
        invoices: 'البحث في الفواتير...'
    };
    searchInput.placeholder = placeholders[view] || 'البحث...';

    // إخفاء جميع الأقسام
    document.querySelectorAll('.content-section').forEach(section => {
        section.style.display = 'none';
    });

    // عرض القسم المطلوب
    const targetSection = document.getElementById(view + 'Section');
    if (targetSection) {
        targetSection.style.display = 'block';
    }

    // تحميل البيانات حسب النوع
    switch(view) {
        case 'categories':
            // عند العودة للتصنيفات، لا نقوم بإلغاء الاختيار
            loadCategories();
            break;
        case 'products':
            loadProducts(); // ستتحقق من وجود تصنيف مختار
            break;
        case 'invoices':
            if (typeof loadInvoices === 'function') {
                loadInvoices();
            }
            break;
    }

    // مسح البحث السابق
    searchInput.value = '';
}

// تحديث دالة performSearch للتعامل مع البحث في منتجات التصنيف
function performSearch(query) {
    if (!query) {
        if (currentView === 'categories') {
            loadCategories();
        } else if (currentView === 'products') {
            loadProducts(); // ستتحقق من التصنيف المختار
        } else if (currentView === 'invoices') {
            if (typeof loadInvoices === 'function') {
                loadInvoices();
            }
        }
        return;
    }

    try {
        if (currentView === 'products') {
            searchInProducts(query);
        } else if (currentView === 'categories') {
            const filteredCategories = appData.categories.filter(category => 
                category.name.toLowerCase().includes(query.toLowerCase())
            );
            displayCategories(filteredCategories);
        } else if (currentView === 'invoices') {
            if (typeof searchInvoices === 'function') {
                searchInvoices(query);
            }
        }
    } catch (error) {
        console.error('خطأ في البحث:', error);
        showError('حدث خطأ أثناء البحث');
    }
}

// البحث في المنتجات مع مراعاة التصنيف المختار
function searchInProducts(query) {
    if (selectedCategory) {
        // البحث في منتجات التصنيف المحدد باستخدام API
        searchInCategoryProducts(query);
    } else {
        // البحث في جميع المنتجات (البحث المحلي)
        const filteredProducts = appData.products.filter(product => 
            product.name.toLowerCase().includes(query.toLowerCase()) ||
            (product.code && product.code.toLowerCase().includes(query.toLowerCase()))
        );
        displayProducts(filteredProducts);
    }
}

// البحث في منتجات التصنيف المحدد باستخدام API
function searchInCategoryProducts(query) {
    const productsGrid = document.getElementById('productsGrid');
    productsGrid.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p class="mt-2">جاري البحث في منتجات التصنيف...</p>
        </div>
    `;

    fetch(`${appData.urls.productsByCategory}?category_id=${selectedCategory}&search=${encodeURIComponent(query)}`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': appData.csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.products) {
            if (data.products.length > 0) {
                displayProductsWithHeader(data.products, selectedCategory);
            } else {
                displayEmptyProducts(`لم يتم العثور على منتجات تحتوي على "${query}" في هذا التصنيف`, selectedCategory);
            }
        } else {
            displayProductsError('فشل في البحث', selectedCategory);
        }
    })
    .catch(error => {
        console.error('خطأ في البحث:', error);
        displayProductsError(error.message, selectedCategory);
    });
}

// إجعل الدوال متاحة عالمياً
window.selectCategory = selectCategory;
window.loadProductsByCategory = loadProductsByCategory;
window.clearCategorySelection = clearCategorySelection;
window.displayProductsWithHeader = displayProductsWithHeader;
window.searchInProducts = searchInProducts;
window.searchInCategoryProducts = searchInCategoryProducts;

// عرض المنتجات
/*
    ═══════════ تحديثُ الأرصدة بعد الحركة ═══════════

    `appData.products` كانت تُحمَّل مرّةً عند فتح الشاشة و**لا تُمسّ بعدها**:
    لا سطرَ واحدٌ في الشيفرة يكتب في `stock_quantity`.

    فالكاشيرُ يبيع أربعاً ويرى الرقمَ لا يتغيّر، ويُرجع اثنتين فلا يتغيّر —
    فيظنّ المرتجعَ لم يعمل وهو عامل. وأسوأُ منه أنّ فحصَ التوفّر قبل
    الإضافة يقرأ الرقمَ القديم، فصنفٌ نفد يبقى قابلاً للإضافة حتى يُرفض
    عند الحفظ.

    والخادمُ يُرجع الأرصدةَ الجديدةَ مع ردّ البيع والمرتجع — فلا رحلةَ
    إضافية ولا رقمٌ من عمليةٍ أخرى.

    @param {Object} stock  معرّفُ الصنف ⇐ رصيدُه الجديد
*/
function applyStockUpdate(stock) {
    if (! stock || typeof stock !== 'object') {
        return;
    }

    let touched = 0;

    Object.keys(stock).forEach(id => {
        const product = appData.products.find(p => String(p.id) === String(id));

        if (product) {
            product.stock_quantity = stock[id];
            touched++;
        }
    });

    if (! touched) {
        return;
    }

    /*
        تُعاد الشبكةُ إن كانت معروضة. وشاشةُ الفواتير أو التصنيفات لا
        تعرض أرصدةً، فلا معنى لإعادة رسمها.
    */
    if (currentView === 'products') {
        if (selectedCategory) {
            loadProductsByCategory(selectedCategory);
        } else {
            displayProducts(appData.products);
        }
    }
}

function displayProducts(products) {
    const grid = document.getElementById('productsGrid');

    if (!products || products.length === 0) {
        grid.innerHTML = '<div class="empty-state"><i class="fas fa-box-open"></i><p>لا توجد منتجات</p></div>';
        return;
    }

    const productsHtml = products.map(product => {
        const stockQty = parseFloat(product.stock_quantity || 0);
        // ما لا رصيدَ له لا ينفد
        const holdsStock = tracksStock(product);
        const isOutOfStock = stockQty <= 0 && holdsStock;
        const stockClass = isOutOfStock ? 'out-of-stock' : (stockQty <= 5 ? 'low-stock' : '');
        const stockBadge = holdsStock ? `<span class="stock-badge ${stockClass}">${stockQty}</span>` : '';

        // تطبيق إعداد عرض صور المنتجات
        const showImages = appData.settings.showProductImages;
        const productImage = showImages ? `<img src="${getProductImage(product)}" alt="${product.name}" class="product-image" onerror="this.src=''">` : '';

        return `
        <div class="product-card fade-in ${isOutOfStock ? 'disabled-product' : ''} ${!showImages ? 'no-image' : ''}" 
             data-product-id="${product.id}"
             data-stock="${stockQty}"
             data-type="${product.type || 'products'}">
            ${isOutOfStock ? '<div class="out-of-stock-overlay">نفذت الكمية</div>' : ''}
            ${productImage}
            <div class="product-name" title="${esc(product.name)}">${esc(product.name)}</div>
            <div class="product-info">
                <span class="product-price">${money(parseFloat(product.sale_price || 0))}</span>
                ${stockBadge}
                <button class="info-btn" title="تفاصيل المنتج" onclick="showProductInfo(${product.id})">
                    <i class="fas fa-info-circle"></i>
                </button>
            </div>
        </div>
    `}).join('');

    grid.innerHTML = productsHtml;
}

// الحصول على صورة المنتج
function getProductImage(product) {
    let images = product.images;

    if (!images) {
        return '/assets/uploads/no_image.jpg';
    }

    // إذا كانت الصور JSON string أو array
    if (typeof images === 'string') {
        // محاولة فك الـ JSON إذا كانت array مخزنة كـ string
        try {
            const parsed = JSON.parse(images);
            if (Array.isArray(parsed) && parsed.length > 0) {
                images = parsed[0];
            }
        } catch (e) {
            // ليست JSON، استخدمها كما هي
        }
    } else if (Array.isArray(images) && images.length > 0) {
        images = images[0];
    }

    // إذا كان الرابط كاملاً
    if (images.startsWith('http://') || images.startsWith('https://')) {
        return images;
    }

    // إذا كان يبدأ بـ /
    if (images.startsWith('/')) {
        return images;
    }

    // إزالة الـ backslashes وتحويلها لـ forward slashes
    images = images.replace(/\\/g, '/');

    // إذا كان المسار يبدأ بـ assets
    if (images.startsWith('assets/')) {
        return '/' + images;
    }

    // افتراض أن الصورة في مجلد المنتجات
    return '/assets/uploads/products/' + images;
}

// عرض تفاصيل المنتج
function showProductInfo(productId) {
    const product = appData.products.find(p => p.id == productId);
    if (!product) return;

    Swal.fire({
        title: product.name,
        html: `
            <div class="text-start">
                <img src="${getProductImage(product)}" class="img-fluid mb-3" style="max-height: 200px; border-radius: 10px;">
                <p><strong>الكود:</strong> ${product.code || 'غير محدد'}</p>
                <p><strong>السعر:</strong> ${money(parseFloat(product.sale_price || 0))}</p>
            </div>
        `,
        confirmButtonText: 'إغلاق',
        confirmButtonColor: '#4CAF50'
    });
}

// تبديل وضع البحث مع إضافة الفواتير
function switchSearchMode(mode) {
    currentView = mode;

    const modeTexts = {
        products: 'المنتجات',
        clients: 'العملاء',
        invoices: 'الفواتير'
    };

    const placeholders = {
        products: 'ابحث عن المنتجات...',
        clients: 'ابحث عن العملاء...',
        invoices: 'ابحث عن الفواتير...'
    };

    document.getElementById('searchModeText').textContent = modeTexts[mode];
    searchInput.placeholder = placeholders[mode];

    // تبديل العرض
    switchView(mode);

    // تحميل البيانات حسب النوع
    if (mode === 'invoices') {
        loadInvoices();
    }

    // التركيز على حقل البحث
    searchInput.focus();
}

/*
    تُنادى من سماتِ `onclick` في الترميز — فلا بدّ من `window`.
*/
window.showProductInfo = showProductInfo;

window.applyStockUpdate = applyStockUpdate;
