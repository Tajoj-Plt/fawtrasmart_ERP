/*
    ═══════════ السلّةُ والمجاميع ═══════════

    المجاميعُ تُحسب من الحالة لا من الشاشة، والشاشةُ تعرض ما حُسب.
*/

// إضافة منتج للسلة مباشرة (للباركود)
function addProductToOrderDirect(product) {
    const order = orders[currentOrder];

    // التحقق من وجود عميل - إذا لم يكن هناك عميل، استخدم العميل الافتراضي
    if (!order.client) {
        if (appData.defaultClient) {
            order.client = appData.defaultClient;
            selectedClientName.textContent = appData.defaultClient.trade_name;
        } else {
            Swal.fire({
                title: 'يجب اختيار عميل',
                text: 'الرجاء اختيار عميل من القائمة المنسدلة',
                icon: 'warning',
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#4CAF50'
            }).then(() => {
                openClientDropdown();
            });
            return;
        }
    }

    const availableStock = parseFloat(product.stock_quantity) || 0;
    const isService = ! tracksStock(product);

    // إنشاء مفتاح فريد للمنتج مع الوحدة
    const itemKey = product.product_unit_id 
        ? `${product.id}_${product.product_unit_id}` 
        : product.id;

    const existingItem = order.items.find(item => item.itemKey == itemKey);

    // التحقق من الكمية المتاحة (للمنتجات فقط وليس الخدمات)
    if (!isService) {
        const currentQtyInCart = existingItem ? existingItem.quantity : 0;
        const newQty = currentQtyInCart + 1;

        if (newQty > availableStock) {
            Swal.fire({
                title: 'الكمية غير متاحة',
                text: `الكمية المتاحة في المخزون: ${availableStock}`,
                icon: 'warning',
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#f0ad4e'
            });
            return;
        }
    }

    if (existingItem) {
        existingItem.quantity += 1;
        existingItem.total = existingItem.quantity * existingItem.price;
    } else {
        // بناء اسم العرض مع الوحدة إن وجدت
        let displayName = product.name;
        if (product.unit_name) {
            displayName = `${product.name} (${product.unit_name})`;
        }

        order.items.push({
            id: product.id,
            itemKey: itemKey, // مفتاح فريد للمنتج + الوحدة
            product_unit_id: product.product_unit_id || null, // معرف الوحدة
            name: displayName,
            price: parseFloat(product.sale_price || 0),
            quantity: 1,
            total: parseFloat(product.sale_price || 0),
            stock_quantity: availableStock,
            type: product.type || 'products',
            unit_name: product.unit_name || null,
            conversion_factor: product.conversion_factor || 1
        });
    }

    updateOrderDisplay();
    const unitInfo = product.unit_name ? ` (${product.unit_name})` : '';
    showNotification(`تم إضافة "${product.name}"${unitInfo} عبر الباركود`, 'success');
}

        // إضافة منتج للطلب (من الصفحة - بدون وحدة محددة)
function addProductToOrder(productId, stockQty = null, productType = 'products') {
    const product = appData.products.find(p => p.id == productId);
    if (!product) {
        showError('المنتج غير موجود');
        return;
    }

    // استخدام الكمية من المنتج إذا لم تُمرر
    const availableStock = stockQty !== null ? stockQty : (product.stock_quantity || 0);
    /*
        النوعُ قد يصل من الباركود بوحدةٍ مختلفة، فيُفحص المرسَلُ والمنتجُ
        معاً — وأيُّهما بلا رصيدٍ يكفي.
    */
    const isService = ! tracksStock({ type: productType, tracks_stock: product.tracks_stock })
        || ! tracksStock(product);

    const order = orders[currentOrder];

    // التحقق من وجود عميل - إذا لم يكن هناك عميل، استخدم العميل الافتراضي
    if (!order.client) {
        if (appData.defaultClient) {
            order.client = appData.defaultClient;
            selectedClientName.textContent = appData.defaultClient.trade_name;
        } else {
            Swal.fire({
                title: 'يجب اختيار عميل',
                text: 'الرجاء اختيار عميل من القائمة المنسدلة',
                icon: 'warning',
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#4CAF50'
            }).then(() => {
                // فتح قائمة العملاء
                openClientDropdown();
            });
            return;
        }
    }

    // عند إضافة منتج من الصفحة (وليس باركود)، نستخدم سعره الأساسي بدون وحدة
    // المفتاح الفريد: productId_nounit للتفريق عن نفس المنتج بوحدات أخرى
    const itemKey = `${productId}_nounit`;

    // البحث بالمفتاح الفريد لضمان عدم الخلط مع المنتج نفسه بوحدة مختلفة
    const existingItem = order.items.find(item => item.itemKey == itemKey);

    // التحقق من الكمية المتاحة (للمنتجات فقط وليس الخدمات)
    if (!isService) {
        const currentQtyInCart = existingItem ? existingItem.quantity : 0;
        const newQty = currentQtyInCart + 1;

        if (newQty > availableStock) {
            Swal.fire({
                title: 'الكمية غير متاحة',
                text: `الكمية المتاحة في المخزون: ${availableStock}`,
                icon: 'warning',
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#f0ad4e'
            });
            return;
        }
    }

    // استخدام السعر الأساسي للمنتج (بدون وحدة)
    const basePrice = parseFloat(product.sale_price || 0);

    if (existingItem) {
        existingItem.quantity += 1;
        existingItem.total = existingItem.quantity * existingItem.price;
    } else {
        order.items.push({
            id: productId,
            itemKey: itemKey, // مفتاح فريد للمنتج بدون وحدة
            product_unit_id: null, // لا توجد وحدة - سعر أساسي
            name: product.name, // اسم المنتج بدون اسم وحدة
            price: basePrice,
            quantity: 1,
            total: basePrice,
            stock_quantity: availableStock,
            type: productType,
            unit_name: null, // لا توجد وحدة
            conversion_factor: 1 // معامل التحويل = 1 (الوحدة الأساسية)
        });
    }

    updateOrderDisplay();
    showNotification(`تم إضافة "${product.name}" للطلب`, 'success');
}

/*
    ═══════════ رسمُ السلّة ═══════════

    **ما كان**: كلُّ تغييرٍ — زيادةُ كمّيةٍ بواحد — يُعيد بناء `innerHTML`
    للسلّة كلِّها. وأثرُه ثلاثة:

      • عناصرُ لم تتغيّر تُهدَم وتُبنى، وتفقد حالتها في المتصفّح.
      • صنفُ `slide-in` يُعاد على الجميع، فتتحرّك السلّةُ كلُّها لضغطةٍ
        على صنفٍ واحد.
      • وسلّةٌ فيها ثلاثون صنفاً تُعيد تحليل ثلاثين قالباً لتغيير رقم.

    **وما صار**: البناءُ الكامل لأوّل رسمٍ وللإضافة والحذف، وتغييرُ الكمّية
    يكتب رقمَه ومجموعَه في مكانهما.

    ═══════════ والاسمُ يُهذَّب ═══════════

    `title="${item.name}"` بلا تهذيب: اسمٌ فيه علامةُ اقتباسٍ يكسر السمةَ
    فيتعطّل الزرّ، واسمٌ فيه وسمٌ يُنفَّذ. والاسمُ من قاعدة البيانات لا من
    يد الكاشير.
*/
function orderItemHtml(item) {
    const key = esc(item.itemKey || item.id);
    const name = esc(item.name);

    return `
        <div class="order-item slide-in" data-item-key="${key}">
            <div class="item-details">
                <div class="item-name" title="${name}">${name}</div>
                <div class="item-price">${money(item.price)} للوحدة</div>
            </div>
            <div class="item-controls">
                <div class="quantity-controls">
                    <button class="qty-btn" onclick="updateQuantity('${key}', -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="qty-display">${item.quantity}</span>
                    <button class="qty-btn" onclick="updateQuantity('${key}', 1)">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="item-total">${money(item.total)}</div>
                <button class="delete-btn" onclick="removeFromOrder('${key}')" title="حذف العنصر">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
}

function updateOrderDisplay() {
    const order = orders[currentOrder];

    if (!order.items.length) {
        orderContent.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <p>لا توجد عناصر في الطلب</p>
                <small>اختر المنتجات لإضافتها للطلب</small>
            </div>
        `;
    } else {
        orderContent.innerHTML = order.items.map(orderItemHtml).join('');
    }

    updateTotals();
}

/*
    صفٌّ واحدٌ يُحدَّث في مكانه.

    يُرجع `false` إن لم يجد صفَّه — فينادي المستدعي الرسمَ الكامل. وهذا
    يقع أوّلَ مرّةٍ بعد إضافةِ صنفٍ لم يُرسم بعد.
*/
function updateOrderRow(item) {
    const key = String(item.itemKey || item.id);
    const row = orderContent.querySelector(`.order-item[data-item-key="${CSS.escape(key)}"]`);

    if (!row) {
        return false;
    }

    const qty = row.querySelector('.qty-display');
    const total = row.querySelector('.item-total');

    if (!qty || !total) {
        return false;
    }

    qty.textContent = item.quantity;
    total.textContent = money(item.total);

    return true;
}

function updateQuantity(itemKey, change) {
    const order = orders[currentOrder];
    const item = order.items.find(i => (i.itemKey || i.id) == itemKey);

    if (!item) return;

    const newQty = item.quantity + change;

    // التحقق من الكمية المتاحة عند الزيادة (للمنتجات فقط وليس الخدمات)
    if (change > 0 && tracksStock(item)) {
        const availableStock = item.stock_quantity || 0;
        if (newQty > availableStock) {
            Swal.fire({
                title: 'الكمية غير متاحة',
                text: `الكمية المتاحة في المخزون: ${availableStock}`,
                icon: 'warning',
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#f0ad4e'
            });
            return;
        }
    }

    item.quantity = Math.max(1, newQty);
    item.total = item.quantity * item.price;

    /*
        الرقمُ والمجموعُ في مكانهما — لا هدمٌ للسلّة كلِّها.

        والرسمُ الكامل يبقى احتياطاً: صنفٌ أُضيف للتوّ ولم يُرسم صفُّه بعد.
        والمجاميعُ تُحدَّث في الحالين.
    */
    if (!updateOrderRow(item)) {
        updateOrderDisplay();
        return;
    }

    updateTotals();
}

// حذف من الطلب
function removeFromOrder(itemKey) {
    const order = orders[currentOrder];
    order.items = order.items.filter(item => (item.itemKey || item.id) != itemKey);

    updateOrderDisplay();
    showNotification('تم حذف العنصر من الطلب', 'warning');
}

// الحصول على نسبة الضريبة المختارة
/*
    ═══════════ الضريبةُ المختارة ═══════════

    `data-rate` يصل الآن **مئويةً** (١٥) لا كسراً (٠٫١٥) — وكان الكسرُ
    يُقسَم على مئةٍ ثانيةً فتصير الضريبةُ جزءاً من عشرة آلاف.

    و`data-type` يقول «مضافة» أم «شاملة»، ولم يكن يُرسَل أصلاً.

    والقراءةُ تحتمل الصيغتين احتياطاً: صفحةٌ مخبّأةٌ في المتصفّح قد تحمل
    الوسمَ القديم بعد النشر.
*/
function selectedTax() {
    const el = document.getElementById('taxType');

    if (!el || !el.options.length || el.selectedIndex < 0) {
        return { rate: 0, type: 'excluded' };
    }

    const opt = el.options[el.selectedIndex];
    let rate = parseFloat(opt?.dataset?.rate || 0) || 0;

    // ما دون الواحد كسرٌ قطعاً — لا ضريبةَ عمليّةً نسبتُها أقلُّ من ١٪
    if (rate > 0 && rate < 1) {
        rate = rate * 100;
    }

    return { rate: rate, type: opt?.dataset?.type === 'included' ? 'included' : 'excluded' };
}

function getSelectedTaxRate() {
    return selectedTax().rate;
}

// تحديث عرض نسبة الضريبة
function updateTaxRateDisplay() {
    const tax = selectedTax();
    const taxRateDisplay = document.getElementById('taxRateDisplay');
    if (taxRateDisplay) {
        taxRateDisplay.textContent = tax.rate + '%' + (tax.type === 'included' ? ' شاملة' : '');
    }
    const taxRate = tax.rate;
    posLog('تم تغيير الضريبة إلى:', taxRate + '%');
    // إعادة حساب الإجماليات
    updateTotals();
}

/*
    ═══════════ مجاميعُ السلّة ═══════════

    تُنادى مع كل تغيير: إضافةٌ، وحذفٌ، وكلُّ ضغطةٍ على زرّ الكمّية، وكلُّ
    حرفٍ يُكتب في حقل الخصم.

    فأُصلح فيها ثلاثة:

      • **مراجعُ العناصر تُحلّ مرّةً واحدة** لا في كل نداء — كانت ستَّ
        نداءاتِ `getElementById` لكلّ تغييرٍ في رقم.
      • **السطران المطبوعان حُذفا**: كانا يحسبان `toFixed` أربعَ مرّاتٍ
        لطباعةِ ما هو معروضٌ على الشاشة أصلاً.
      • **العملةُ من الإعدادات** عبر `money()`.

    والحسابُ نفسُه لم يُمسّ: السعرُ صافٍ، والضريبةُ فوقه، والخصمُ من
    الإجمالي شاملَ الضريبة.
*/
const totalsEls = {};

function totalsEl(id) {
    if (!(id in totalsEls)) {
        totalsEls[id] = document.getElementById(id);
    }

    return totalsEls[id];
}

function updateTotals() {
    const order = orders[currentOrder];

    const totalItems = order.items.reduce((sum, item) => sum + item.quantity, 0);

    const tax = selectedTax();
    const taxRatePercent = tax.rate;
    const fraction = taxRatePercent / 100;

    /*
        ═══════════ مضافةٌ أم شاملة ═══════════

        كان الحسابُ يفترض «مضافة» دائماً: الضريبةُ فوق السعر.

        وفي «الشاملة» السعرُ المعروض يحملها أصلاً، فتُستخرَج منه ولا تُضاف:

            ضريبة = السعر × النسبة ÷ (١ + النسبة)

        فسعرٌ ١١٥ بنسبة ١٥٪ شاملة ⇒ وعاؤه ١٠٠ وضريبتُه ١٥ والإجمالي ١١٥.
        ولو حُسبت مضافةً لصار ١٣٢٫٢٥ — يدفع العميلُ الضريبةَ مرّتين.

        وهو نفسُ ما يفعله `TaxCalculator` في الخادم، فيتطابق ما يُعرض مع
        ما يُقيَّد.
    */
    let subtotalExcludingTax = 0;
    let totalTax = 0;

    /*
        التقريبُ على مستوى السطر — كما يفعل الخادم.

        `CalculatesPosTax::taxTotals` تقرّب ضريبةَ كل سطرٍ ثم تجمع، لأنّ
        الإيصالَ يعرض السطورَ فيجمعها العميلُ بعينه.

        ولو قرّبت الشاشةُ على الإجمالي وحده لاختلف المعروضُ عن المحفوظ
        بقروش: ستّةُ أصنافٍ بـ١٠٥ وضريبةٍ شاملةٍ ١٥٪ تعطي ٨٢٫١٧ إجمالاً
        و٨٢٫٢٠ سطراً سطراً. والفرقُ يظهر على الإيصال فيُسأل عنه الكاشير.
    */
    const round2 = v => Math.round((v + Number.EPSILON) * 100) / 100;

    order.items.forEach(item => {
        const gross = item.price * item.quantity;

        if (tax.type === 'included' && fraction > 0) {
            const t = round2(gross * fraction / (1 + fraction));
            subtotalExcludingTax += round2(gross - t);
            totalTax += t;
        } else {
            subtotalExcludingTax += round2(gross);
            totalTax += round2(gross * fraction);
        }
    });

    subtotalExcludingTax = round2(subtotalExcludingTax);
    totalTax = round2(totalTax);

    const subtotalIncludingTax = subtotalExcludingTax + totalTax;

    // الخصمُ من الإجمالي شاملَ الضريبة
    const discountType = totalsEl('discountType')?.value || 'amount';
    const discountValue = parseFloat(totalsEl('discountValue')?.value) || 0;

    let discountAmount = 0;
    if (discountValue > 0) {
        discountAmount = discountType === 'percentage'
            ? subtotalIncludingTax * (discountValue / 100)
            : discountValue;
    }

    const finalTotal = Math.max(0, subtotalIncludingTax - discountAmount);

    // العرض
    const itemsEl = totalsEl('totalItems');
    if (itemsEl) itemsEl.textContent = totalItems;

    const subtotalEl = totalsEl('subtotalAmount');
    if (subtotalEl) subtotalEl.textContent = money(subtotalExcludingTax);

    const taxAmountEl = totalsEl('taxAmount');
    if (taxAmountEl) taxAmountEl.textContent = money(totalTax);

    const finalEl = totalsEl('finalTotal');
    if (finalEl) finalEl.textContent = money(finalTotal);

    // حفظُ الخصم والضريبة في الطلب
    order.discount = { type: discountType, value: discountValue };
    order.taxRate = taxRatePercent;
    order.taxType = tax.type;
    order.taxId = totalsEl('taxType')?.value || null;
}

// إضافة طلب جديد
function addNewOrder() {
    const newOrderNumber = Object.keys(orders).length + 1;

    /*
        الطلبُ الجديد يبدأ بالعميل الافتراضي — لا فارغاً.

        كان `client: null`، فيفتح الكاشيرُ طلباً ثانياً لزبونٍ آخر فيجد
        «اختر العميل». وأكثرُ ما يُباع بيعٌ نقديٌّ لمن لا ملفَّ له: فإمّا
        أن يختاره في كل طلب، أو ينساه فتُحفظ الفاتورةُ بلا عميل.

        والطلبُ الأوّلُ يرثه من `initializeApp`، فلا معنى لأن يختلف عنه
        الثاني.
    */
    orders[newOrderNumber] = {
        items: [],
        client: appData.defaultClient || null,
        discount: { type: 'amount', value: 0 }
    };

    // إضافة تبويب جديد
    const newTab = document.createElement('button');
    newTab.className = 'order-tab';
    newTab.textContent = `#${newOrderNumber}`;
    newTab.dataset.order = newOrderNumber;
    newTab.onclick = () => switchOrder(newOrderNumber);

    const addBtn = document.querySelector('.order-tab:last-child');
    addBtn.parentNode.insertBefore(newTab, addBtn);

    switchOrder(newOrderNumber);
}

// تبديل الطلب
function switchOrder(orderNumber) {
    currentOrder = orderNumber;

    // تحديث التبويبات
    document.querySelectorAll('.order-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    const activeTab = document.querySelector(`[data-order="${orderNumber}"]`);
    if (activeTab) {
        activeTab.classList.add('active');
    }

    /*
        اسمُ العميل عند التبديل.

        وحين لا عميلَ للطلب — طلبٌ قديمٌ أُنشئ قبل هذا الإصلاح — يُعرض
        الافتراضيُّ **ويُثبَّت** في الطلب، فلا يبقى معروضاً وغيرَ محفوظ.
    */
    const order = orders[orderNumber];

    if (! order.client && appData.defaultClient) {
        order.client = appData.defaultClient;
    }

    selectedClientName.textContent = order.client
        ? order.client.trade_name
        : 'اختر العميل';

    // تحديث الخصم
    document.getElementById('discountType').value = order.discount.type;
    document.getElementById('discountValue').value = order.discount.value;

    updateOrderDisplay();
}

// تعليق الطلب
function holdOrder() {
    const order = orders[currentOrder];

    if (!order.items.length) {
        showNotification('لا يمكن تعليق طلب فارغ', 'error');
        return;
    }

    Swal.fire({
        title: 'تعليق الطلب',
        text: 'هل تريد تعليق هذا الطلب؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، علق الطلب',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#ffc107'
    }).then((result) => {
        if (result.isConfirmed) {
            const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
            heldOrders.push({
                ...order,
                orderNumber: currentOrder,
                heldAt: new Date().toISOString()
            });
            localStorage.setItem('heldOrders', JSON.stringify(heldOrders));

            showNotification('تم تعليق الطلب بنجاح', 'success');
            addNewOrder();
        }
    });
}

// إعادة تعيين الطلب الحالي
/*
    تفريغُ الطلب بعد إتمام الفاتورة.

    كان يُعيد العميلَ إلى `null`، فيصير الطلبُ التالي بلا عميل — وهو أشيعُ
    ما يحدث: كلُّ فاتورةٍ تُغلق تترك التي بعدها فارغة. فيعود الافتراضيّ.
*/
function resetCurrentOrder() {
    const fallback = appData.defaultClient || null;

    orders[currentOrder] = {
        items: [],
        client: fallback,
        discount: { type: 'amount', value: 0 }
    };

    selectedClientName.textContent = fallback ? fallback.trade_name : 'اختر العميل';
    document.getElementById('discountType').value = 'amount';
    document.getElementById('discountValue').value = 0;

    updateOrderDisplay();
}

/*
    تُنادى من سماتِ `onclick` في الترميز — فلا بدّ من `window`.
*/
window.addNewOrder = addNewOrder;
window.removeFromOrder = removeFromOrder;
window.updateQuantity = updateQuantity;
