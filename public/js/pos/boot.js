/*
    ═══════════ الإقلاعُ وربطُ الأحداث ═══════════

    يُنادى بعد اكتمال الترميز، ويربط كلَّ ما سبق — فملفُّه يأتي
    أخيراً.
*/

function initializeApp() {
    // تحديد العميل الافتراضي من الإعدادات
    if (appData.defaultClient) {
        orders[currentOrder].client = appData.defaultClient;
        selectedClientName.textContent = appData.defaultClient.trade_name;
    }

    // تحميل البيانات الافتراضية بناءً على إعداد تفعيل الأقسام
    if (appData.settings.enableDepartments) {
        currentView = 'categories';
        loadCategories();
    } else {
        currentView = 'products';
        displayProducts(appData.products);
    }
    updateOrderDisplay();

    // تحميل الفواتير في الخلفية
    setTimeout(() => loadInvoices(1), 300);
}

function setupEventListeners() {
    // البحث
    searchInput.addEventListener('input', handleSearch);

    // البحث بالباركود عند الضغط على Enter
    searchInput.addEventListener('keydown', handleSearch);

    // أزرار البحث - التحقق من وجود زر التصنيفات قبل إضافة المستمع
    const categoriesBtn = document.getElementById('categoriesBtn');
    if (categoriesBtn) {
        categoriesBtn.addEventListener('click', () => switchView('categories'));
    }
    document.getElementById('productsBtn').addEventListener('click', () => switchView('products'));
    document.getElementById('invoicesBtn').addEventListener('click', () => switchView('invoices'));

    // التصنيفات
    document.addEventListener('click', (e) => {
        if (e.target.closest('.category-card')) {
            const categoryCard = e.target.closest('.category-card');
            const categoryId = categoryCard.dataset.categoryId;
            selectCategory(categoryId);
        }
    });

    // المنتجات
    document.addEventListener('click', (e) => {
        if (e.target.closest('.product-card') && !e.target.closest('.info-btn')) {
            const productCard = e.target.closest('.product-card');

            // التحقق من أن المنتج ليس نفذت كميته
            if (productCard.classList.contains('disabled-product')) {
                showNotification('هذا المنتج نفذت كميته', 'warning');
                return;
            }

            const productId = productCard.dataset.productId;
            const stockQty = parseFloat(productCard.dataset.stock || 0);
            const productType = productCard.dataset.type || 'products';

            addProductToOrder(productId, stockQty, productType);
        }
    });

    // تبويبات الطلبات
    document.addEventListener('click', (e) => {
        if (e.target.closest('.order-tab') && e.target.closest('.order-tab').dataset.order) {
            const orderNumber = parseInt(e.target.closest('.order-tab').dataset.order);
            switchOrder(orderNumber);
        }
    });

    // الخصم
    document.getElementById('discountType').addEventListener('change', updateTotals);
    document.getElementById('discountValue').addEventListener('input', updateTotals);

    // الضريبة - تحديث عند تغيير نوع الضريبة
    const taxTypeSelect = document.getElementById('taxType');
    if (taxTypeSelect) {
        taxTypeSelect.addEventListener('change', updateTaxRateDisplay);
    }

    // أزرار العمليات
    document.getElementById('holdOrderBtn').addEventListener('click', holdOrder);
    document.getElementById('payOrderBtn').addEventListener('click', initiatePayment);

    // نافذة الدفع
    document.getElementById('confirmPaymentBtn').addEventListener('click', confirmPayment);

    // زر إضافة طريقة دفع
    const addPaymentMethodBtn = document.getElementById('addPaymentMethodBtn');
    if (addPaymentMethodBtn) {
        addPaymentMethodBtn.addEventListener('click', () => addPaymentMethod());
    }

    // زر إعادة تعيين طرق الدفع
    const resetPaymentsBtn = document.getElementById('resetPaymentsBtn');
    if (resetPaymentsBtn) {
        resetPaymentsBtn.addEventListener('click', () => {
            const finalTotal = parseFloat(document.getElementById('modalTotalAmount').textContent.replace(' ' + CURRENCY, ''));
            resetPaymentMethodsList();
            addPaymentMethod(appData.defaultPaymentMethodId, finalTotal);
        });
    }

    // أزرار الدفع السريع
    document.addEventListener('click', (e) => {
        if (e.target.closest('.quick-pay-btn')) {
            const btn = e.target.closest('.quick-pay-btn');
            const methodId = btn.dataset.methodId;
            const finalTotal = parseFloat(document.getElementById('modalTotalAmount').textContent.replace(' ' + CURRENCY, ''));

            resetPaymentMethodsList();
            addPaymentMethod(methodId, finalTotal);
        }
    });

    // اختصارات لوحة المفاتيح
    setupKeyboardShortcuts();
}
