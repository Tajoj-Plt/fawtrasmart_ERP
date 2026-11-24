/*
    ═══════════ المرتجعات ═══════════

    المرتجعُ يُبنى من فاتورةٍ قائمة: كمّياتُه لا تتجاوز المباع،
    وضريبتُه تتبع الأصل.
*/

// دالة فتح نافذة المرتجع
function openReturnModal(invoiceId) {
    // جلب تفاصيل الفاتورة للمرتجع
    fetch(appData.urls.invoiceDetailsApi.replace('__ID__', invoiceId), {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': appData.csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.invoice) {
            showReturnModal(data.invoice);
        } else {
            Swal.fire('خطأ', 'لا يمكن تحميل تفاصيل الفاتورة', 'error');
        }
    })
    .catch(error => {
        console.error('خطأ:', error);
        Swal.fire('خطأ', 'حدث خطأ أثناء تحميل البيانات', 'error');
    });
}

// عرض نافذة المرتجع
/*
    ═══════════ نافذةُ المرتجع ═══════════

    **ما كانت**: خانةُ الكمّية تُملأ بالكمّية المباعة كاملةً ومربّعُ الاختيار
    فارغ. فيرى المستخدم «٦» في الخانة و«٠٫٠٠» في الإجمالي، ويضغط «تأكيد
    المرتجع» فلا يحدث شيء — والخانةُ المملوءةُ تقول «هذا مختار» والمربّعُ
    يقول غيرَه.

    **وما صارت**: الخانةُ تبدأ فارغةً، وكتابةُ رقمٍ فيها تختار الصفَّ
    تلقائياً. فالفعلُ الواحد يكفي، ولا يبقى تناقضٌ بين ما يُرى وما يُحسب.

    والصفُّ غيرُ المختار يخفت — فيُقرأ ما سيُرجَع بالنظرة لا بالتدقيق.
*/
function showReturnModal(invoice) {
    const invoiceSubtotal = parseFloat(invoice.subtotal) || 0;
    const invoiceTaxTotal = parseFloat(invoice.tax_total) || 0;
    const taxRate = invoiceSubtotal > 0 ? (invoiceTaxTotal / invoiceSubtotal) : 0;

    const itemsHtml = invoice.items.map((item, index) => {
        const unitPrice = parseFloat(item.unit_price);
        const priceWithTax = unitPrice * (1 + taxRate);
        const totalWithTax = priceWithTax * item.quantity;

        const unitName = item.unit_name || '';
        const productName = item.item || item.product_name || '';
        const displayName = unitName ? `${productName} (${unitName})` : productName;

        return `
        <tr data-item-id="${item.id}"
            data-product-id="${item.product_id}"
            data-product-unit-id="${item.product_unit_id || ''}"
            data-max-qty="${item.quantity}"
            data-unit-price="${item.unit_price}"
            data-tax-rate="${taxRate}">
            <td class="ret-pick">
                <input type="checkbox" class="return-item-check" id="returnItem${index}"
                       onchange="updateReturnTotals()">
            </td>
            <td>
                <div class="ret-name">${esc(displayName)}</div>
                <div class="ret-meta">بِيع ${item.quantity} · ${money(priceWithTax)} للوحدة</div>
            </td>
            <td class="ret-sold">${item.quantity}</td>
            <td>
                <input type="number" class="return-qty" value="" placeholder="0"
                       min="0" max="${item.quantity}" step="any"
                       oninput="validateAndUpdateReturnQty(this)"
                       onchange="updateReturnTotals()">
            </td>
            <td class="item-return-total ret-line-total">${money(0)}</td>
        </tr>`;
    }).join('');

    Swal.fire({
        title: '',
        html: `
            <div class="ret-modal">
                <div class="ret-head">
                    <div>
                        <div class="ret-head__title">مرتجع فاتورة</div>
                        <div class="ret-head__code">${esc(invoice.code || invoice.id)}</div>
                    </div>
                    <div class="ret-head__cap">
                        <span class="ret-head__cap-label">إجمالي الفاتورة</span>
                        <span class="ret-head__cap-value">${money(invoice.grand_total)}</span>
                    </div>
                </div>

                <table class="ret-table">
                    <thead>
                        <tr>
                            <th class="ret-pick">
                                <input type="checkbox" id="selectAllReturn" onchange="toggleAllReturnItems()">
                            </th>
                            <th>الصنف</th>
                            <th>المباع</th>
                            <th>كمية الإرجاع</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>${itemsHtml}</tbody>
                </table>

                <div class="ret-foot">
                    <span class="ret-foot__label">إجمالي المرتجع</span>
                    <span class="ret-foot__value" id="returnTotalAmount">${money(0)}</span>
                </div>

                <div class="ret-reason">
                    <label for="returnReason">سبب الإرجاع</label>
                    <textarea id="returnReason" rows="2"
                              placeholder="صنفٌ تالف، أو خطأٌ في الطلب، أو عدولُ العميل…"></textarea>
                </div>

                ${appData.canCashRefund ? `
                    <div class="ret-pay">
                        <label class="ret-pay__opt">
                            <input type="radio" name="refundMode" value="cash" checked>
                            <span class="ret-pay__box">
                                <span class="ret-pay__title">صرف نقداً من الدرج</span>
                                <span class="ret-pay__hint">يُسلَّم المبلغ للعميل الآن، ويُنقص الدرجُ به</span>
                            </span>
                        </label>

                        <label class="ret-pay__opt">
                            <input type="radio" name="refundMode" value="credit">
                            <span class="ret-pay__box">
                                <span class="ret-pay__title">رصيد للعميل</span>
                                <span class="ret-pay__hint">يبقى المبلغ في ذمّة المنشأة يُسوّى بفاتورةٍ تالية</span>
                            </span>
                        </label>
                    </div>
                ` : `
                    <p class="ret-nopay">
                        الردُّ النقدي غيرُ متاح لك — يُنشأ المرتجع رصيداً للعميل.
                    </p>
                `}
            </div>
        `,
        width: '660px',
        showCancelButton: true,
        confirmButtonText: 'تأكيد المرتجع',
        cancelButtonText: 'إلغاء',
        customClass: { popup: 'ret-popup' },
        buttonsStyling: false,

        /*
            ═══════════ مؤشّرُ الانتظار ═══════════

            المرتجعُ ليس طلباً خفيفاً: يكتب فاتورةً، ويُصدر إذناً مخزنياً،
            ويُعيد الكمّية إلى الرفّ، ويكتب ثلاثة قيود، وقد يصرف نقداً من
            الدرج. ثوانٍ يقف فيها الكاشيرُ أمام نافذةٍ لا تتحرّك.

            و`preConfirm` وحدها لا تُظهر شيئاً — `showLoaderOnConfirm` هي
            التي تُبدّل الزرَّ إلى دوّارة. وبدونها يظنُّ الكاشيرُ أنّ
            الضغطةَ لم تصل فيضغط ثانيةً: **مرتجعان لفاتورةٍ واحدة**.

            و`allowOutsideClick` تُقفَل أثناء التنفيذ للسبب نفسِه: إغلاقُ
            النافذة لا يُلغي الطلبَ الذي انطلق.
        */
        showLoaderOnConfirm: true,
        allowOutsideClick: () => ! Swal.isLoading(),
        allowEscapeKey: () => ! Swal.isLoading(),

        preConfirm: () => processReturnFromModal(invoice.id),
    });
}

/*
    كتابةُ كمّيةٍ تختار الصفَّ، ومحوُها يُلغيه.

    كان المستخدم يكتب الكمّيةَ ويضغط «تأكيد» فلا يحدث شيء — لأنّ مربّعَ
    الاختيار لم يُعلَّم، والإجماليُّ يُحسب من المُعلَّم وحده. وهما فعلان
    لقصدٍ واحد.
*/
function validateAndUpdateReturnQty(input) {
    const row = input.closest('tr');
    const maxQty = parseFloat(row.dataset.maxQty) || 0;
    const check = row.querySelector('.return-item-check');

    if (input.value === '') {
        if (check) check.checked = false;
        updateReturnTotals();
        return;
    }

    let value = parseFloat(input.value) || 0;

    if (value > maxQty) {
        value = maxQty;
        input.value = maxQty;
    }

    if (value < 0) {
        value = 0;
        input.value = 0;
    }

    // القصدُ واحد: كمّيةٌ مكتوبة تعني «أرجِعْ هذا»
    if (check) {
        check.checked = value > 0;
    }

    updateReturnTotals();
}

// تحديد/إلغاء تحديد جميع عناصر المرتجع
function toggleAllReturnItems() {
    const selectAll = document.getElementById('selectAllReturn');
    document.querySelectorAll('.return-item-check').forEach(check => {
        check.checked = selectAll.checked;
    });
    updateReturnTotals();
}

// تحديث إجماليات المرتجع
function updateReturnTotals() {
    let total = 0;

    // تحديث جميع الصفوف أولاً
    document.querySelectorAll('tr[data-item-id]').forEach(row => {
        const qtyInput = row.querySelector('.return-qty');
        const qty = parseFloat(qtyInput.value) || 0;
        const maxQty = parseFloat(row.dataset.maxQty) || qty;
        const unitPrice = parseFloat(row.dataset.unitPrice) || 0;
        const taxRate = parseFloat(row.dataset.taxRate) || 0.15;

        // التأكد من أن الكمية لا تتجاوز الحد الأقصى
        const validQty = Math.min(Math.max(qty, 0), maxQty);
        if (qty !== validQty) {
            qtyInput.value = validQty;
        }

        // حساب الإجمالي مع الضريبة
        const priceWithTax = unitPrice * (1 + taxRate);
        const itemTotal = validQty * priceWithTax;
        row.querySelector('.item-return-total').textContent = money(itemTotal);

        const checkbox = row.querySelector('.return-item-check');
        const picked = checkbox && checkbox.checked;

        /*
            الاختيارُ بالمربّع يملأ الكمّية، وإلغاؤه يمحوها.

            وبدونه يبقى رقمٌ مكتوبٌ في صفٍّ غير مختار — يُقرأ أنه سيُرجَع
            ولا يُرجَع.
        */
        if (picked && validQty <= 0) {
            qtyInput.value = maxQty;
            row.querySelector('.item-return-total').textContent = money(maxQty * priceWithTax);
            total += maxQty * priceWithTax;
        } else if (! picked && qtyInput.value !== '') {
            qtyInput.value = '';
            row.querySelector('.item-return-total').textContent = money(0);
        } else if (picked) {
            total += itemTotal;
        }

        // الصفُّ غيرُ المختار يخفت — فيُقرأ ما سيُرجَع بالنظرة
        row.classList.toggle('is-picked', !! picked);
    });

    const totalEl = document.getElementById('returnTotalAmount');

    if (totalEl) {
        totalEl.textContent = money(total);
        totalEl.classList.toggle('is-on', total > 0.005);
    }
}

// معالجة المرتجع
async function processReturnFromModal(invoiceId) {
    /*
        ═══════════ النافذةُ تُفتح الآن لا بعد الانتظار ═══════════

        `window.open` بعد `await` **يُحجب**: المتصفّح يسمح بفتح نافذةٍ داخل
        مَهمّةِ النقر وحدها، والانتظارُ يُنهيها. فيتمّ المرتجعُ في الخادم
        ولا تظهر ورقتُه — بلا رسالةٍ ولا خطأ، لأنّ الحجبَ صامت.

        فتُفتح فارغةً هنا (والنقرةُ ما زالت قائمة) ثم تُوجَّه إلى الورقة حين
        يصل رقمُ المرتجع. وإن فشل الطلبُ تُغلق.
    */
    const printWindow = window.open('', '_blank');

    if (printWindow) {
        printWindow.document.write(
            '<!doctype html><html dir="rtl"><head><meta charset="utf-8">'
            + '<title>جارٍ تجهيز المرتجع…</title></head>'
            + '<body style="font-family:sans-serif;display:grid;place-items:center;height:100vh;margin:0;color:#4A5468">'
            + 'جارٍ تجهيز ورقة المرتجع…</body></html>'
        );
    }

    const returnItems = [];
    document.querySelectorAll('.return-item-check:checked').forEach(check => {
        const row = check.closest('tr');
        const qty = parseFloat(row.querySelector('.return-qty').value) || 0;
        const maxQty = parseFloat(row.dataset.maxQty) || qty;
        const unitPrice = parseFloat(row.dataset.unitPrice) || 0;
        const taxRate = parseFloat(row.dataset.taxRate) || 0.15;

        // الحصول على product_unit_id (قد يكون فارغاً للمنتجات بدون وحدة)
        const productUnitId = row.dataset.productUnitId || null;

        // التأكد من أن الكمية صحيحة
        const validQty = Math.min(Math.max(qty, 1), maxQty);

        returnItems.push({
            item_id: row.dataset.itemId,
            product_id: row.dataset.productId,
            product_unit_id: productUnitId ? parseInt(productUnitId) : null, // إرسال معرف الوحدة
            quantity: validQty,
            unit_price: unitPrice,
            total: validQty * unitPrice * (1 + taxRate)
        });
    });

    if (returnItems.length === 0) {
        if (printWindow) printWindow.close();

        Swal.showValidationMessage('يرجى اختيار منتج واحد على الأقل للإرجاع');
        return false;
    }

    const reason = document.getElementById('returnReason').value;

    const modeInput = document.querySelector('input[name="refundMode"]:checked');
    const refundMode = modeInput ? modeInput.value : 'credit';

    try {
        const response = await fetch(appData.urls.processReturn, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': appData.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                invoice_id: invoiceId,
                items: returnItems,
                reason: reason,
                /*
                    القرارُ يُرسَل صريحاً ولا يُترك للخادم.

                    كان الصرفُ يقع تلقائياً متى سُمح به — فيخرج المالُ من
                    الدرج بلا أن يعرف الكاشير. والعميلُ قد يريد رصيداً
                    لفاتورةٍ تالية لا نقداً، وهو قرارُ لحظةِ المرتجع.
                */
                refund_cash: refundMode === 'cash'
            })
        });

        const result = await response.json();

        if (result.success) {
            /*
                الرسالةُ تقول ما وقع بالمال — «تم إنشاء المرتجع» وحدها لا
                تُفرّق بين مالٍ خرج من الدرج ورصيدٍ قُيّد على الذمّة.
            */
            showNotification(
                result.refunded_cash
                    ? `تم المرتجع وصُرف ${money(result.refunded_amount)} من الدرج`
                    : 'تم إنشاء المرتجع — قُيّد رصيداً للعميل',
                'success'
            );

            // البضاعةُ عادت إلى الرفّ — فيعود رقمُها في الشاشة كذلك
            applyStockUpdate(result.stock);

            // تحديث الفاتورة الأصلية محلياً وإضافة فاتورة المرتجع
            updateInvoiceAfterReturn(invoiceId, returnItems, result);

            // ورقةُ المرتجع في النافذة التي فُتحت مع النقرة
            if (result.return_invoice_id) {
                const url = appData.urls.returnPrint.replace('__ID__', result.return_invoice_id);

                if (printWindow) {
                    printWindow.location.href = url;
                } else {
                    // حُجبت رغم ذلك — يُقال ولا يُترك المستخدم يظنّ أنّ الطباعة تمّت
                    showNotification('تعذّر فتح ورقة المرتجع — اسمح بالنوافذ المنبثقة، أو اطبعها من قائمة الفواتير', 'warning');
                }
            } else if (printWindow) {
                printWindow.close();
            }

            return true;
        } else {
            throw new Error(result.message || 'حدث خطأ');
        }
    } catch (error) {
        if (printWindow) printWindow.close();

        Swal.showValidationMessage(error.message);
        return false;
    }
}

// إنشاء مرتجع (الطريقة القديمة - للتوافق)
function createReturn(invoiceId) {
    openReturnModal(invoiceId);
}

// دالة بدء الاسترداد
async function initiateReturn() {
    try {
        // جلب الفواتير المتاحة للاسترداد من الجلسة الحالية
        const response = await fetch(appData.urls.availableInvoicesForReturn, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': appData.csrfToken,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success && data.invoices && data.invoices.length > 0) {
            availableInvoices = data.invoices;
            showInvoiceSelectionModal();
        } else {
            showNotification('لا توجد فواتير متاحة للاسترداد في الجلسة الحالية', 'warning');
        }
    } catch (error) {
        console.error('خطأ في جلب الفواتير:', error);
        showError('حدث خطأ أثناء جلب الفواتير المتاحة للاسترداد');
    }
}

// عرض modal اختيار الفاتورة
function showInvoiceSelectionModal() {
    posLog('بيانات الفواتير للاسترداد:', availableInvoices);

    const invoicesHtml = availableInvoices.map(invoice => {
        // تحديد حالة الاسترداد وتصميم العنصر
        let statusBadge = '';
        let borderStyle = 'border: 1px solid #ddd;';
        let cursorStyle = 'cursor: pointer;';
        let canClick = true;

        if (invoice.return_status === 'full') {
            statusBadge = '<span class="badge bg-danger" style="font-size: 0.8em;">مرتجع بالكامل</span>';
            borderStyle = 'border: 2px solid #dc3545;';
            cursorStyle = 'cursor: not-allowed; opacity: 0.6;';
            canClick = false;
        } else if (invoice.return_status === 'partial') {
            statusBadge = '<span class="badge bg-warning text-dark" style="font-size: 0.8em;">مرتجع جزئي</span>';
            borderStyle = 'border: 2px solid #ffc107;';
        }

        return `
        <div class="invoice-selection-item" data-invoice-id="${invoice.id}" data-invoice-code="${esc(invoice.code)}" data-client-name="${esc(invoice.client_name || '')}" data-can-return="${canClick}" style="${borderStyle} padding: 10px; margin-bottom: 10px; ${cursorStyle} border-radius: 8px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6>فاتورة رقم: ${invoice.code} ${statusBadge}</h6>
                    <small class="text-muted">العميل: ${invoice.client_name || 'عميل نقدي'}</small><br>
                    <small class="text-muted">التاريخ: ${formatDate(invoice.invoice_date)}</small>
                </div>
                <div class="text-end">
                    <span class="h5 text-success">${money(parseFloat(invoice.grand_total))}</span>
                </div>
            </div>
        </div>
    `}).join('');

    Swal.fire({
        title: 'اختر الفاتورة للاسترداد',
        html: `
            <div style="margin-bottom: 15px;">
                <input type="text" id="invoiceSearchInput" class="form-control" placeholder="ابحث برقم الفاتورة أو اسم العميل..." style="padding: 10px; border-radius: 8px; border: 2px solid #ddd;">
            </div>
            <div id="invoicesContainer" style="max-height: 400px; overflow-y: auto;">
                ${invoicesHtml}
            </div>
        `,
        showCancelButton: true,
        showConfirmButton: false,
        cancelButtonText: 'إلغاء',
        width: '600px',
        didOpen: () => {
            const searchInput = document.getElementById('invoiceSearchInput');
            const invoicesContainer = document.getElementById('invoicesContainer');

            // إضافة مستمعات للنقر على الفواتير
function addInvoiceClickListeners() {
    document.querySelectorAll('.invoice-selection-item').forEach(item => {
        item.addEventListener('click', function() {
            const canReturn = this.dataset.canReturn === 'true';
            if (!canReturn) {
                showError('هذه الفاتورة مرتجعة بالكامل ولا يمكن إنشاء مرتجع آخر لها');
                return;
            }
            const invoiceId = this.dataset.invoiceId;
            loadInvoiceForReturn(invoiceId);
            Swal.close();
        });
    });
}

            // إضافة المستمعات للفواتير الحالية
            addInvoiceClickListeners();

            // وظيفة البحث
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();

                if (!searchTerm) {
                    // عرض جميع الفواتير
                    invoicesContainer.innerHTML = invoicesHtml;
                    addInvoiceClickListeners();
                    return;
                }

                // فلترة الفواتير
                const filteredInvoices = availableInvoices.filter(invoice => {
                    const invoiceCode = (invoice.code || '').toLowerCase();
                    const clientName = (invoice.client_name || '').toLowerCase();

                    return invoiceCode.includes(searchTerm) || clientName.includes(searchTerm);
                });

                // عرض النتائج المفلترة
                if (filteredInvoices.length > 0) {
                    const filteredHtml = filteredInvoices.map(invoice => {
                        let statusBadge = '';
                        let borderStyle = 'border: 1px solid #ddd;';
                        let cursorStyle = 'cursor: pointer;';
                        let canClick = true;

                        if (invoice.return_status === 'full') {
                            statusBadge = '<span class="badge bg-danger" style="font-size: 0.8em;">مرتجع بالكامل</span>';
                            borderStyle = 'border: 2px solid #dc3545;';
                            cursorStyle = 'cursor: not-allowed; opacity: 0.6;';
                            canClick = false;
                        } else if (invoice.return_status === 'partial') {
                            statusBadge = '<span class="badge bg-warning text-dark" style="font-size: 0.8em;">مرتجع جزئي</span>';
                            borderStyle = 'border: 2px solid #ffc107;';
                        }

                        return `
                        <div class="invoice-selection-item" data-invoice-id="${invoice.id}" data-can-return="${canClick}" style="${borderStyle} padding: 10px; margin-bottom: 10px; ${cursorStyle} border-radius: 8px;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6>فاتورة رقم: ${invoice.code} ${statusBadge}</h6>
                                    <small class="text-muted">العميل: ${invoice.client_name || 'عميل نقدي'}</small><br>
                                    <small class="text-muted">التاريخ: ${formatDate(invoice.invoice_date)}</small>
                                </div>
                                <div class="text-end">
                                    <span class="h5 text-success">${money(parseFloat(invoice.grand_total))}</span>
                                </div>
                            </div>
                        </div>
                    `}).join('');

                    invoicesContainer.innerHTML = filteredHtml;
                } else {
                    invoicesContainer.innerHTML = `
                        <div style="text-align: center; padding: 30px; color: #666;">
                            <i class="fas fa-search" style="font-size: 2em; margin-bottom: 10px;"></i>
                            <p>لم يتم العثور على فواتير تطابق البحث</p>
                            <small>جرب البحث برقم فاتورة مختلف أو اسم العميل</small>
                        </div>
                    `;
                }

                // إعادة إضافة المستمعات للنتائج الجديدة
                addInvoiceClickListeners();
            });

            // التركيز على حقل البحث
            searchInput.focus();
        }
    });
}

// تحميل الفاتورة للاسترداد
async function loadInvoiceForReturn(invoiceId) {
    try {
        const response = await fetch(appData.urls.invoiceDetailsForReturn.replace('__ID__', invoiceId), {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': appData.csrfToken,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success && data.invoice) {
            displayReturnModal(data.invoice);
        } else {
            showError('فشل في تحميل تفاصيل الفاتورة');
        }
    } catch (error) {
        console.error('خطأ في تحميل الفاتورة:', error);
        showError('حدث خطأ أثناء تحميل تفاصيل الفاتورة');
    }
}

function displayReturnModal(invoice) {
    // حفظ نسبة الضريبة من الفاتورة
    currentInvoiceTaxRate = (invoice.tax_rate || 15) / 100; // تحويل من نسبة مئوية إلى عشري

    returnItems = invoice.items.map(item => ({
        ...item,
        return_quantity: 0,
        max_quantity: item.quantity - (item.returned_quantity || 0),
        tax_rate: item.tax_rate || (invoice.tax_rate || 15) // نسبة الضريبة لكل عنصر
    }));

    const itemsHtml = returnItems.map((item, index) => `
        <div class="return-item" data-index="${index}" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 8px;">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <h6 class="mb-1">${item.item}</h6>
                    <small class="text-muted">السعر (شامل الضريبة ${item.tax_rate}%): ${money(parseFloat(item.unit_price_with_tax || (item.unit_price * (1 + item.tax_rate/100))))}</small><br>
                    <small class="text-muted">الكمية الأصلية: ${item.quantity}</small><br>
                    <small class="text-muted">المتاح للاسترداد: ${item.max_quantity}</small>
                </div>
                <div class="col-md-4">
                    <div class="quantity-controls d-flex align-items-center">
                        <button class="btn btn-sm btn-outline-secondary" onclick="updateReturnQuantity(${index}, -1)">-</button>
                        <input type="number" class="form-control mx-2 text-center" style="width: 80px;" 
                               value="0" min="0" max="${item.max_quantity}" 
                               onchange="updateReturnQuantity(${index}, this.value, true)">
                        <button class="btn btn-sm btn-outline-secondary" onclick="updateReturnQuantity(${index}, 1)">+</button>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <button class="btn btn-sm btn-warning" onclick="setFullReturn(${index})">استرداد كامل</button>
                    <div class="mt-2">
                        <span class="h6 text-danger" id="returnTotal-${index}">${money(0)}</span>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    document.getElementById('returnItemsList').innerHTML = itemsHtml;
    updateReturnSummary();
    $('#returnModal').modal('show');
}

// تحديث كمية الاسترداد
function updateReturnQuantity(index, change, direct = false) {
    const item = returnItems[index];
    const input = document.querySelector(`[data-index="${index}"] input`);

    if (direct) {
        item.return_quantity = Math.max(0, Math.min(parseInt(change) || 0, item.max_quantity));
    } else {
        item.return_quantity = Math.max(0, Math.min(item.return_quantity + parseInt(change), item.max_quantity));
    }

    input.value = item.return_quantity;

    // حساب المجموع للعنصر - استخدام نسبة الضريبة من العنصر
    const priceExcludingTax = parseFloat(item.unit_price);
    const taxRateDecimal = (item.tax_rate || 15) / 100;
    const priceIncludingTax = priceExcludingTax * (1 + taxRateDecimal);
    const itemTotal = item.return_quantity * priceIncludingTax;
    document.getElementById(`returnTotal-${index}`).textContent = money(itemTotal);

    updateReturnSummary();
}

// تعيين استرداد كامل للعنصر
function setFullReturn(index) {
    updateReturnQuantity(index, returnItems[index].max_quantity, true);
}

// تحديث ملخص الاسترداد
function updateReturnSummary() {
    let totalItems = 0;
    let subtotalExcludingTax = 0;
    let totalTax = 0;

    returnItems.forEach(item => {
        if (item.return_quantity > 0) {
            totalItems += item.return_quantity;
            // استخدام نسبة الضريبة من العنصر
            const priceExcludingTax = parseFloat(item.unit_price);
            const taxRateDecimal = (item.tax_rate || 15) / 100;
            const taxPerUnit = priceExcludingTax * taxRateDecimal;

            subtotalExcludingTax += priceExcludingTax * item.return_quantity;
            totalTax += taxPerUnit * item.return_quantity;
        }
    });

    const finalTotal = subtotalExcludingTax + totalTax;

    document.getElementById('returnTotalItems').textContent = totalItems;
    document.getElementById('returnSubtotalAmount').textContent = money(subtotalExcludingTax);
    document.getElementById('returnTaxAmount').textContent = money(totalTax);
    document.getElementById('returnFinalTotal').textContent = money(finalTotal);
}

/*
    تُنادى من سماتِ `onclick` في الترميز — فلا بدّ من `window`.
*/
window.createReturn = createReturn;
window.openReturnModal = openReturnModal;
window.processReturnFromModal = processReturnFromModal;
window.showReturnModal = showReturnModal;
window.toggleAllReturnItems = toggleAllReturnItems;
window.updateReturnTotals = updateReturnTotals;
window.validateAndUpdateReturnQty = validateAndUpdateReturnQty;
