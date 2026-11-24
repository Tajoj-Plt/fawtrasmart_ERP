/*
    ═══════════ قائمةُ الفواتير ═══════════

    القائمةُ تُحمَّل مرّةً وتُصفَّح محلياً — والبحثُ وحده يعود
    إلى الخادم.
*/

// تحميل الفواتير مع loading و pagination (مرة واحدة فقط)
function loadInvoices(page = 1, forceReload = false) {
    // إذا تم تحميل الفواتير مسبقاً ولا نريد إعادة التحميل، نعرضها مباشرة
    if (invoicesLoaded && !forceReload && allInvoices.length > 0) {
        currentInvoicesPage = page;
        displayInvoicesWithPagination(allInvoices, page);
        return;
    }

    if (isLoadingInvoices) return;
    isLoadingInvoices = true;
    currentInvoicesPage = page;

    const invoicesContainer = document.getElementById('invoicesContainer');
    if (invoicesContainer) {
        invoicesContainer.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #667eea;">
                <div style="width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px;"></div>
                <p>جاري تحميل الفواتير...</p>
            </div>
        `;
    }

    // إرسال طلب لجلب الفواتير
    fetch(`${appData.urls.search}?type=invoices&query=&limit=100`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': appData.csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        isLoadingInvoices = false;
        invoicesLoaded = true; // تم تحميل الفواتير
        if (data.success && data.invoices) {
            allInvoices = data.invoices;
            displayInvoicesWithPagination(allInvoices, page);
        } else {
            allInvoices = [];
            displayInvoicesWithPagination([], page);
        }
    })
    .catch(error => {
        isLoadingInvoices = false;
        console.error('خطأ في تحميل الفواتير:', error);
        allInvoices = [];
        displayInvoicesWithPagination([], page);
    });
}

// إضافة فاتورة جديدة للقائمة محلياً (بدون إعادة تحميل من السيرفر)
function addNewInvoiceToList(response, invoiceData, totalAmount) {
    // إنشاء كائن الفاتورة الجديدة
    const newInvoice = {
        id: response.invoice_id,
        code: response.invoice_code || 'POS' + String(response.invoice_id).padStart(5, '0'),
        client_name: invoiceData.client_name || 'عميل نقدي',
        client_id: invoiceData.client_id,
        grand_total: totalAmount,
        subtotal: invoiceData.total,
        tax_total: (totalAmount - invoiceData.total + (invoiceData.discount_value || 0)),
        discount_value: invoiceData.discount_value || 0,
        payment_status: 1, // مدفوعة
        is_paid: true,
        created_at: new Date().toISOString(),
        items_count: invoiceData.products.length,
        has_return: false,
        return_status: null,
        can_return: true
    };

    // إضافة الفاتورة في بداية القائمة (الأحدث أولاً)
    allInvoices.unshift(newInvoice);

    // تحديث العرض إذا كان المستخدم في قسم الفواتير
    if (currentView === 'invoices') {
        displayInvoicesWithPagination(allInvoices, 1); // الانتقال للصفحة الأولى لعرض الفاتورة الجديدة
    }

    posLog('✅ تم إضافة الفاتورة الجديدة للقائمة:', newInvoice.code);
}

// تحديث الفاتورة بعد المرتجع وإضافة فاتورة المرتجع للقائمة
function updateInvoiceAfterReturn(originalInvoiceId, returnItems, result) {
    // البحث عن الفاتورة الأصلية وتحديثها
    const originalInvoiceIndex = allInvoices.findIndex(inv => inv.id == originalInvoiceId);

    if (originalInvoiceIndex !== -1) {
        const originalInvoice = allInvoices[originalInvoiceIndex];

        // استخدام البيانات من الـ Response إذا كانت متوفرة
        if (result.return_status) {
            originalInvoice.has_return = true;
            originalInvoice.return_status = result.return_status;
            originalInvoice.can_return = result.can_return_more !== false;
            originalInvoice.total_returned_amount = result.total_returned_amount || 0;
            originalInvoice.remaining_amount = result.remaining_amount || 0;
        } else {
            // حساب من البيانات المحلية (fallback)
            const totalReturnedQty = returnItems.reduce((sum, item) => sum + (item.quantity || item.return_quantity || 0), 0);
            const totalOriginalQty = originalInvoice.total_original_qty || originalInvoice.items_count || 1;
            const previousReturnedQty = originalInvoice.total_returned_qty || 0;
            const newTotalReturnedQty = previousReturnedQty + totalReturnedQty;

            originalInvoice.has_return = true;
            originalInvoice.total_returned_qty = newTotalReturnedQty;

            if (newTotalReturnedQty >= totalOriginalQty) {
                originalInvoice.return_status = 'full';
                originalInvoice.can_return = false;
            } else {
                originalInvoice.return_status = 'partial';
                originalInvoice.can_return = true;
            }
        }

        posLog('✅ تم تحديث الفاتورة الأصلية:', originalInvoice.code, 
            'حالة المرتجع:', originalInvoice.return_status,
            'يمكن الإرجاع أكثر:', originalInvoice.can_return);
    }

    // إضافة فاتورة المرتجع للقائمة
    if (result.return_invoice_id) {
        const returnTotal = result.return_total || returnItems.reduce((sum, item) => sum + (item.total || 0), 0);

        const returnInvoice = {
            id: result.return_invoice_id,
            code: result.return_invoice_code || 'RET' + String(result.return_invoice_id).padStart(5, '0'),
            client_name: allInvoices[originalInvoiceIndex]?.client_name || 'عميل نقدي',
            client_id: allInvoices[originalInvoiceIndex]?.client_id,
            grand_total: returnTotal,
            subtotal: returnTotal,
            tax_total: 0,
            discount_value: 0,
            payment_status: 1,
            is_paid: true,
            type: 'returned',
            reference_number: originalInvoiceId,
            created_at: new Date().toISOString(),
            items_count: returnItems.length,
            has_return: false,
            return_status: null,
            can_return: false
        };

        // إضافة فاتورة المرتجع في بداية القائمة
        allInvoices.unshift(returnInvoice);

        posLog('✅ تم إضافة فاتورة المرتجع للقائمة:', returnInvoice.code);
    }

    /*
        ═══════════ ثم تُقرأ القائمةُ من الخادم ═══════════

        الترقيعُ المحلّي أعلاه يُظهر الأثرَ فوراً، لكنه يخمّن: حالةُ المرتجع
        تُحسب من `items_count` حين لا يُرسلها الخادم، والفاتورةُ الجديدة
        تُبنى بحقولٍ ناقصة. فيبقى الصفُّ الأصليُّ على حاله حتى يُحدَّث
        المتصفّح — وهو ما شكا منه المستخدم.

        والخادمُ يعرف الحقيقة: كم أُرجع، وكم بقي، وأيُّ فاتورةٍ صارت
        مستوفاة. فتُقرأ منه مرّةً واحدةً بعد كل مرتجع.

        والشرطُ `currentView === 'invoices'` سقط: القائمةُ تُحدَّث في
        الذاكرة على كل حال، ومن يفتحها بعد لحظةٍ يجدها صحيحة.
    */
    displayInvoicesWithPagination(allInvoices, currentInvoicesPage);

    loadInvoices(currentInvoicesPage, true);
}

// التنقل بين صفحات الفواتير
function goToInvoicePage(page) {
    if (page < 1) return;
    const totalPages = Math.ceil(allInvoices.length / invoicesPerPage);
    if (page > totalPages) return;
    currentInvoicesPage = page;
    displayInvoicesWithPagination(allInvoices, page);
}

// تغيير عدد الفواتير في الصفحة
function changeInvoicesPerPage(value) {
    invoicesPerPage = parseInt(value);
    currentInvoicesPage = 1;
    displayInvoicesWithPagination(allInvoices, 1);
}

// عرض الفواتير مع Pagination
function displayInvoicesWithPagination(invoices, page = 1) {
    const invoicesContainer = document.getElementById('invoicesContainer');
    if (!invoicesContainer) return;

    if (!invoices || invoices.length === 0) {
        invoicesContainer.innerHTML = `
            <div class="invoices-pro-container">
                <div class="invoices-table-wrapper">
                    <div class="empty-invoices">
                        <i class="fas fa-file-invoice"></i>
                        <p>لا توجد فواتير في هذه الجلسة</p>
                        <small class="text-muted">ستظهر الفواتير هنا بعد إنشائها</small>
                        <button class="btn btn-primary btn-sm mt-3" onclick="loadInvoices(1)">
                            <i class="fas fa-sync-alt"></i> تحديث
                        </button>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    // Pagination calculations
    const totalItems = invoices.length;
    const totalPages = Math.ceil(totalItems / invoicesPerPage);
    const startIndex = (page - 1) * invoicesPerPage;
    const endIndex = Math.min(startIndex + invoicesPerPage, totalItems);
    const paginatedInvoices = invoices.slice(startIndex, endIndex);

    // Pass to original display function
    displayInvoices(paginatedInvoices, totalItems, page, totalPages, startIndex, endIndex);
}

// عرض الفواتير بتصميم احترافي
function displayInvoices(invoices, totalItems = 0, currentPage = 1, totalPages = 1, startIndex = 0, endIndex = 0) {
    const container = document.getElementById('invoicesContainer');
    if (!container) {
        const invoicesSection = document.getElementById('invoicesSection');
        if (!invoicesSection) {
            const newSection = document.createElement('div');
            newSection.id = 'invoicesSection';
            newSection.className = 'content-section';
            newSection.style.display = 'none';
            newSection.innerHTML = '<div id="invoicesContainer"></div>';
            document.querySelector('.content-body').appendChild(newSection);
        }
    }

    const invoicesContainer = document.getElementById('invoicesContainer') || 
                             document.querySelector('#invoicesSection');

    // إضافة CSS للتصميم الاحترافي
    if (!document.getElementById('invoicesProCSS')) {
        const style = document.createElement('style');
        style.id = 'invoicesProCSS';

        document.head.appendChild(style);
    }

    if (!invoices || invoices.length === 0) {
        invoicesContainer.innerHTML = `
            <div class="invoices-pro-container">
                <div class="invoices-table-wrapper">
                    <div class="empty-invoices">
                        <i class="fas fa-file-invoice"></i>
                        <p>لا توجد فواتير في هذه الجلسة</p>
                        <small class="text-muted">ستظهر الفواتير هنا بعد إنشائها</small>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    // حساب الإحصائيات
    const totalAmount = invoices.reduce((sum, inv) => sum + parseFloat(inv.grand_total || 0), 0);
    const paidCount = invoices.filter(inv => inv.payment_status == 1).length;
    const unpaidCount = invoices.filter(inv => inv.payment_status == 3).length;

    const invoicesHtml = `
        <div class="invoices-pro-container">
            <div class="invoices-header">
                <h5>فواتير الجلسة</h5>
                <div class="invoices-stats">
                    <div class="stat-item">
                        <div class="stat-value">${invoices.length}</div>
                        <div class="stat-label">إجمالي الفواتير</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${totalAmount.toFixed(2)}</div>
                        <div class="stat-label">إجمالي المبيعات</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${paidCount}</div>
                        <div class="stat-label">مدفوعة</div>
                    </div>
                </div>
            </div>

            <div class="invoices-table-wrapper">
                <table class="invoices-table">
                    <thead>
                        <!--
                            رؤوسٌ بلا أيقونات.

                            كانت ستُّ أيقوناتٍ في سطرٍ واحد — واحدةٌ لكل عمود.
                            وأيقونةُ «#» للرقم و«الترس» للإجراءات لا تُضيفان
                            على الكلمة شيئاً، وستٌّ منها تصير زخرفاً يزاحم
                            العناوين.
                        -->
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>العميل</th>
                            <th>التاريخ</th>
                            <th>المبلغ</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${invoices.map(invoice => {
                            // تحديد badge حالة المرتجع
                            /*
                                حالةُ المرتجع صنفٌ على الصفّ، لا `style` مكتوباً هنا.

                                كانت الأرضيّةُ والتعطيلُ يُكتبان نصّاً في الشيفرة
                                (`background: #fff5f5`)، فيختلط ما يُعرض بكيف
                                يُعرض. والورقةُ ترسم الحدَّ على الحافّة بدل أرضيّةٍ
                                كاملة تُنافس المبلغَ على الانتباه.
                            */
                            let returnBadge = '';
                            let rowClass = '';
                            let returnBtnDisabled = '';
                            let returnBtnTitle = 'مرتجع';
                            let returnClick = 'openReturnModal(' + invoice.id + ')';

                            /*
                                فاتورةُ المرتجع لا تُرجَع.

                                القائمةُ تعرض فواتيرَ الجلسة كلَّها — ومنها
                                المرتجعات. وزرُّ المرتجع كان يظهر فعّالاً على كل
                                صفّ، فيُفتح المودل لفاتورةِ مرتجعٍ ويُنشأ مرتجعٌ
                                لمرتجع: بضاعةٌ تُضاف إلى المخزون مرّةً ثانية وقد
                                أُضيفت، وقيدٌ يعكس عكساً.

                                والخادمُ يردّها الآن أيضاً — فالحراسةُ في
                                الموضعين: هنا لئلّا يُغرى المستخدم، وهناك لئلّا
                                تُلتفّ بنقرة.
                            */
                            const isReturnInvoice = invoice.type === 'returned';

                            if (isReturnInvoice) {
                                returnBtnDisabled = 'disabled';
                                returnBtnTitle = 'فاتورةُ مرتجعٍ — لا تُرجَع';
                                rowClass = 'is-return-doc';
                                returnClick = "showError('فاتورةُ المرتجع لا تُرجَع — هي نفسُها إرجاعٌ لفاتورةٍ أخرى')";
                            } else if (invoice.return_status === 'full') {
                                returnBadge = '<span class="return-flag return-flag--full">مرتجع بالكامل</span>';
                                rowClass = 'is-returned-full';
                                returnBtnDisabled = 'disabled';
                                returnBtnTitle = 'هذه الفاتورة مرتجعة بالكامل';
                                returnClick = "showError('هذه الفاتورة مرتجعة بالكامل ولا يمكن إنشاء مرتجع آخر لها')";
                            } else if (invoice.return_status === 'partial') {
                                returnBadge = '<span class="return-flag return-flag--partial">مرتجع جزئي</span>';
                                rowClass = 'is-returned-partial';
                            }

                            return `
                            <tr data-invoice-id="${invoice.id}" class="${rowClass}">
                                <td>
                                    <!--
                                        حُذفت شارةُ «#»: مربّعٌ أزرقُ في أوّل كل صفٍّ
                                        يقول ما يقوله عنوانُ العمود فوقه، ويأخذ من
                                        عرضِ الرقم نفسِه.
                                    -->
                                    <div class="invoice-code-text">${esc(invoice.code || invoice.id)} ${returnBadge}</div>
                                    <span class="invoice-type-badge${isReturnInvoice ? ' invoice-type-badge--return' : ''}">
                                        ${isReturnInvoice ? 'مرتجع' : (invoice.type === 'pos' ? 'نقطة بيع' : 'عادية')}
                                    </span>
                                </td>
                                <td class="client-info-cell">
                                    <div class="client-name" title="${esc(invoice.client_name || 'عميل غير محدد')}">
                                        ${invoice.client_name || 'عميل غير محدد'}
                                    </div>
                                    ${invoice.client_phone ? `<div class="client-phone">${esc(invoice.client_phone)}</div>` : ''}
                                </td>
                                <td>
                                    <div class="date-main">${formatDate(invoice.invoice_date || invoice.created_at)}</div>
                                    <div class="date-time">${formatTime(invoice.created_at)}</div>
                                </td>
                                <td class="amount-cell">
                                    <div class="amount-total">${money(invoice.grand_total)}</div>
                                    ${invoice.due_value > 0 ? `<div class="amount-due">متبقي: ${money(invoice.due_value)}</div>` : ''}
                                </td>
                                <td>
                                    <span class="status-badge ${getStatusClass(invoice.payment_status)}">
                                        ${getStatusIcon(invoice.payment_status)}
                                        ${getInvoiceStatusText(invoice.payment_status)}
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <div class="action-btn-group">
                                        <button class="action-btn btn-view" onclick="viewInvoiceDetails(${invoice.id})" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn btn-print" onclick="printInvoice(${invoice.id})" title="طباعة">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="action-btn btn-return" onclick="${returnClick}" title="${returnBtnTitle}" ${returnBtnDisabled}>
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `}).join('')}
                    </tbody>
                </table>
                ${totalPages > 1 ? `
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        عرض ${startIndex + 1} إلى ${endIndex} من ${totalItems} فاتورة
                    </div>
                    <div class="pagination-controls">
                        <select class="per-page-select" onchange="changeInvoicesPerPage(this.value)">
                            <option value="10" ${invoicesPerPage === 10 ? 'selected' : ''}>10 في الصفحة</option>
                            <option value="20" ${invoicesPerPage === 20 ? 'selected' : ''}>20 في الصفحة</option>
                            <option value="50" ${invoicesPerPage === 50 ? 'selected' : ''}>50 في الصفحة</option>
                        </select>
                        <button class="page-btn" onclick="goToInvoicePage(1)" ${currentPage === 1 ? 'disabled' : ''}>
                            <i class="fas fa-angle-double-right"></i>
                        </button>
                        <button class="page-btn" onclick="goToInvoicePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                            <i class="fas fa-angle-right"></i>
                        </button>
                        <span style="padding: 0 10px; font-size: 0.875rem;">
                            صفحة ${currentPage} من ${totalPages}
                        </span>
                        <button class="page-btn" onclick="goToInvoicePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                            <i class="fas fa-angle-left"></i>
                        </button>
                        <button class="page-btn" onclick="goToInvoicePage(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `;

    invoicesContainer.innerHTML = invoicesHtml;
}

// دالة تنسيق الوقت
function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });
}

// دالة الحصول على class الحالة
function getStatusClass(status) {
    const classes = {
        1: 'status-paid',
        2: 'status-partial',
        3: 'status-unpaid',
        4: 'status-received'
    };
    return classes[status] || 'status-unpaid';
}

// دالة الحصول على أيقونة الحالة
function getStatusIcon(status) {
    const icons = {
        1: '<i class="fas fa-check-circle"></i>',
        2: '<i class="fas fa-adjust"></i>',
        3: '<i class="fas fa-times-circle"></i>',
        4: '<i class="fas fa-hand-holding-usd"></i>'
    };
    return icons[status] || '<i class="fas fa-question-circle"></i>';
}

/** أهذه فاتورةُ مرتجع؟ — سؤالٌ يُطرح في أربعة مواضع. */
function invoiceIsReturn(invoice) {
    return !! invoice && (invoice.type === 'returned' || invoice.is_return === true);
}

/*
    طباعةُ الفاتورة — والمرتجعُ إلى قالبه.

    كان النداءُ واحداً لكل فاتورة، ويعتمد على أنّ الخادمَ يُحوّل المرتجعَ
    إلى `printReturnInvoice()`. وهو يفعل — لكنّ الاعتمادَ على تحويلٍ في
    الخادم يعني أنّ أيَّ تغييرٍ هناك يكسر الطباعة هنا بصمت.

    والنوعُ معروفٌ في الشاشة أصلاً، فيُقصَد القالبُ قصداً.
*/
function printInvoice(invoiceId, isReturn = null) {
    const known = allInvoices.find(inv => inv.id == invoiceId);

    /*
        ═══════════ فاتورةٌ أُرجعت بالكامل تُطبَع مرتجعاً ═══════════

        كان الزرُّ يطبع فاتورةَ البيع دائماً. وفاتورةٌ أُرجعت بالكامل لم يعد
        لها وجودٌ فعليّ: بضاعتُها عادت إلى الرفّ، ومالُها رُدّ أو قُيّد
        رصيداً. فطباعتُها ورقةَ بيعٍ تُسلَّم للعميل تصفُ ما لم يعد قائماً.

        والمرتجعُ الجزئي يبقى على ورقة البيع: أصنافُه غيرُ المرتجَعة ما زالت
        عند العميل. ووَرَقةُ مرتجعِه تُطبَع من صفِّها أو من نافذة التفاصيل.
    */
    let targetId = invoiceId;
    let asReturn = isReturn !== null ? isReturn : invoiceIsReturn(known);

    if (isReturn === null && known && known.return_status === 'full' && known.return_invoice_id) {
        targetId = known.return_invoice_id;
        asReturn = true;
    }

    invoiceId = targetId;

    const base = asReturn ? appData.urls.returnPrint : appData.urls.printInvoice;

    const url = base.replace('__ID__', invoiceId);
    const win = window.open(url, '_blank', 'width=800,height=600');

    /*
        الحجبُ صامت: `window.open` تُرجع `null` ولا ترمي. فبلا هذا الفحص
        يضغط المستخدمُ «طباعة» فلا يحدث شيء ولا يعرف لماذا.
    */
    if (! win) {
        showNotification('تعذّر فتح نافذة الطباعة — اسمح بالنوافذ المنبثقة لهذا الموقع', 'warning');
    }
}

// وظائف مساعدة للفواتير
function formatDate(dateString) {
    if (!dateString) return 'غير محدد';
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA');
}

function getInvoiceStatusBadge(status) {
    const badges = {
        1: 'bg-success',
        2: 'bg-warning', 
        3: 'bg-secondary',
        4: 'bg-info',
        5: 'bg-danger'
    };
    return badges[status] || 'bg-secondary';
}

function getInvoiceStatusText(status) {
    const texts = {
        1: 'مدفوعة',
        2: 'جزئياً', 
        3: 'غير مدفوعة',
        4: 'مستلمة',
        5: 'ملغية'
    };
    return texts[status] || 'غير محدد';
}

// عرض تفاصيل الفاتورة
function viewInvoiceDetails(invoiceId) {
    Swal.fire({
        title: 'جاري تحميل تفاصيل الفاتورة...',
        allowOutsideClick: false,
        didOpen: () => {
            // Swal.showLoading();
        }
    });

    fetch(appData.urls.invoiceDetailsApi.replace('__ID__', invoiceId), {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': appData.csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showInvoiceDetailsModal(data.invoice);
        } else {
            Swal.fire('خطأ', 'لا يمكن تحميل تفاصيل الفاتورة', 'error');
        }
    })
    .catch(error => {
        console.error('خطأ:', error);
        Swal.fire('خطأ', 'حدث خطأ أثناء تحميل البيانات', 'error');
    });
}

/*
    ═══════════ نافذةُ تفاصيل الفاتورة ═══════════

    **ما كانت**: أصنافُ بوتستراب و`invoice-details-pro` بألوانٍ لا صلةَ لها
    بلوحة الشاشة، وأيقوناتُ FontAwesome، وثلاثةُ أزرارٍ متساوية الوزن —
    و«مرتجع» بينها **يُعرض دائماً**: على فاتورة مرتجع، وعلى فاتورةٍ أُرجعت
    بالكامل. يضغطه المستخدم فيُردّ برسالة.

    **وما صارت**: نظامُ `.inv-*` بنفس الرموز التي عليها الشاشة، والزرُّ
    الذي لا يجوز يخرج **معطَّلاً ومعه سببُه** لا مخفياً — فالإخفاءُ يترك
    المستخدمَ يبحث عمّا لا يجده.

    وحالةُ المرتجع تظهر في الترويسة: «أُرجع 3 من 8» — فيُعرف ما بقي قبل
    فتح نافذة المرتجع.
*/
function showInvoiceDetailsModal(invoice) {
    const isReturn = invoiceIsReturn(invoice);
    const canReturn = ! isReturn && invoice.can_return !== false;

    const itemsHtml = invoice.items.map(item => `
        <tr>
            <td class="inv-name">${esc(item.item || item.product_name || '')}</td>
            <td class="inv-c">${item.quantity}</td>
            <td class="inv-c">${money(item.unit_price)}</td>
            <td class="inv-e inv-strong">${money(item.total)}</td>
        </tr>
    `).join('');

    /* سببُ تعطيل زرّ المرتجع — يُقال ولا يُترك للتخمين */
    const returnBlock = isReturn
        ? 'هذه فاتورةُ مرتجع — لا تُرجَع مرّةً أخرى'
        : (invoice.can_return === false
            ? `أُرجعت بالكامل (${invoice.total_returned_qty || 0} من ${invoice.total_original_qty || 0})`
            : null);

    const statusText = getInvoiceStatusText(invoice.payment_status);
    const paid = Number(invoice.payment_status) === 1;

    Swal.fire({
        title: '',
        html: `
            <div class="inv-modal">
                <div class="inv-head${isReturn ? ' inv-head--return' : ''}">
                    <div>
                        <div class="inv-head__kind">${isReturn ? 'فاتورة مرتجع' : 'فاتورة نقطة بيع'}</div>
                        <div class="inv-head__code">${esc(invoice.code || invoice.id)}</div>
                    </div>
                    <div class="inv-head__cap">
                        <span class="inv-head__cap-label">الإجمالي</span>
                        <span class="inv-head__cap-value">${money(invoice.grand_total)}</span>
                    </div>
                </div>

                <div class="inv-facts">
                    <div class="inv-fact">
                        <span class="inv-fact__label">العميل</span>
                        <span class="inv-fact__value">${esc(invoice.client_name || 'عميل نقدي')}</span>
                    </div>
                    <div class="inv-fact">
                        <span class="inv-fact__label">التاريخ</span>
                        <span class="inv-fact__value">${formatDate(invoice.invoice_date || invoice.created_at)}</span>
                    </div>
                    <div class="inv-fact">
                        <span class="inv-fact__label">حالة السداد</span>
                        <span class="inv-fact__value">
                            <span class="inv-pill${paid ? ' inv-pill--ok' : ''}">${statusText}</span>
                        </span>
                    </div>
                    <div class="inv-fact">
                        <span class="inv-fact__label">حالة الإرجاع</span>
                        <span class="inv-fact__value">
                            ${isReturn
                                ? '<span class="inv-pill inv-pill--return">مرتجع</span>'
                                : (invoice.has_return
                                    ? `<span class="inv-pill inv-pill--${invoice.return_status === 'full' ? 'stop' : 'warn'}">`
                                        + `${invoice.return_status === 'full' ? 'أُرجعت بالكامل' : 'مرتجع جزئي'}`
                                        + `</span> <span class="inv-dim">${invoice.total_returned_qty || 0} من ${invoice.total_original_qty || 0}</span>`
                                    : '<span class="inv-dim">لم يُرجَع منها شيء</span>')}
                        </span>
                    </div>
                </div>

                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>الصنف</th>
                            <th class="inv-c">الكمية</th>
                            <th class="inv-c">السعر</th>
                            <th class="inv-e">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>${itemsHtml}</tbody>
                </table>

                <div class="inv-sums">
                    <div class="inv-sum">
                        <span>المجموع الفرعي</span>
                        <b>${money(invoice.subtotal || invoice.total || invoice.grand_total)}</b>
                    </div>
                    ${Number(invoice.discount_value || invoice.discount_amount) > 0 ? `
                        <div class="inv-sum inv-sum--minus">
                            <span>الخصم</span>
                            <b>− ${money(invoice.discount_value || invoice.discount_amount)}</b>
                        </div>` : ''}
                    ${Number(invoice.tax_total || invoice.tax) > 0 ? `
                        <div class="inv-sum">
                            <span>الضريبة${invoice.tax_rate ? ` (${invoice.tax_rate}٪)` : ''}</span>
                            <b>${money(invoice.tax_total || invoice.tax)}</b>
                        </div>` : ''}
                    <div class="inv-sum inv-sum--grand">
                        <span>الإجمالي النهائي</span>
                        <b>${money(invoice.grand_total)}</b>
                    </div>
                </div>

                <div class="inv-acts">
                    <button type="button" class="inv-btn inv-btn--go"
                            onclick="printInvoice(${invoice.id}, ${isReturn}); Swal.close();">
                        طباعة${isReturn ? ' المرتجع' : ' الفاتورة'}
                    </button>

                    ${(! isReturn && invoice.return_invoice_id) ? `
                        <button type="button" class="inv-btn"
                                onclick="printInvoice(${invoice.return_invoice_id}, true); Swal.close();"
                                title="ورقة المرتجع ${esc(invoice.return_invoice_code || '')}">
                            طباعة المرتجع
                        </button>
                    ` : ''}

                    ${canReturn
                        ? `<button type="button" class="inv-btn inv-btn--warn"
                                   onclick="Swal.close(); openReturnModal(${invoice.id});">إنشاء مرتجع</button>`
                        : `<button type="button" class="inv-btn" disabled title="${esc(returnBlock || '')}">
                               إنشاء مرتجع
                           </button>`}

                    <button type="button" class="inv-btn" onclick="Swal.close();">إغلاق</button>
                </div>

                ${returnBlock ? `<p class="inv-note">${esc(returnBlock)}</p>` : ''}
            </div>
        `,
        width: '640px',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: { popup: 'inv-popup' },
    });
}

// البحث في الفواتير
function searchInvoices(query) {
    // showLoading();

    fetch(`${appData.urls.search}?type=invoices&query=${encodeURIComponent(query)}`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': appData.csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.invoices) {
            displayInvoicesWithPagination(data.invoices, 1);
        } else {
            displayInvoicesWithPagination([], 1);
        }
    })
    .catch(error => {
        console.error('خطأ في البحث:', error);
        displayInvoices([]);
    });
}

// إضافة زر الفواتير للواجهة
// document.addEventListener('DOMContentLoaded', function() {
//     // إضافة زر الفواتير بعد التحميل
//     const clientsBtn = document.getElementById('clientsBtn');
//     if (clientsBtn && !document.getElementById('invoicesBtn')) {
//         const invoicesBtn = document.createElement('button');
//         invoicesBtn.className = 'btn btn-search';
//         invoicesBtn.id = 'invoicesBtn';
//         invoicesBtn.title = 'الفواتير';
//         invoicesBtn.innerHTML = `
//             <i class="fas fa-file-invoice"></i>
//             <span class="d-none d-md-inline">الفواتير</span>
//         `;
//         invoicesBtn.addEventListener('click', () => switchView('invoices'));

//         clientsBtn.parentNode.insertBefore(invoicesBtn, clientsBtn.nextSibling);
//     }
// });

// إجعل الدوال متاحة عالمياً
window.viewInvoiceDetails = viewInvoiceDetails;
window.printInvoice = printInvoice;
window.invoiceIsReturn = invoiceIsReturn;
