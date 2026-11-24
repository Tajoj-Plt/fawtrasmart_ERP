/*
    ═══════════ الدفعُ وحفظُ الفاتورة ═══════════

    الدفعُ متعدّدُ الطرق، والفرقُ بين المدفوع والمستحقّ يحسم
    أهو دفعٌ جزئيٌّ أم تامٌّ أم فيه باقٍ.
*/

// بدء عملية الدفع
function initiatePayment() {
    const order = orders[currentOrder];

    if (!order.items.length) {
        showNotification('لا يمكن إتمام دفع طلب فارغ', 'error');
        return;
    }

    const finalTotal = parseFloat(document.getElementById('finalTotal').textContent.replace(' ' + CURRENCY, ''));
    document.getElementById('modalTotalAmount').textContent = money(finalTotal);

    // إعادة تعيين قائمة طرق الدفع
    resetPaymentMethodsList();

    // إضافة طريقة دفع افتراضية مع المبلغ الكامل
    addPaymentMethod(appData.defaultPaymentMethodId, finalTotal);

    // تحديث الملخص
    updatePaymentSummary();

    $('#paymentModal').modal('show');
}

// حساب ملخص الدفع (للتوافق مع الكود القديم)
function calculatePaymentSummary() {
    updatePaymentSummary();
}

// تحديث ملخص الدفع لطرق الدفع المتعددة
function updatePaymentSummary() {
    const totalAmount = parseFloat(document.getElementById('modalTotalAmount').textContent.replace(' ' + CURRENCY, ''));

    // حساب إجمالي المدفوعات من جميع طرق الدفع
    let totalPaid = 0;
    const paymentRows = document.querySelectorAll('#paymentMethodsList .payment-method-row');
    paymentRows.forEach(row => {
        const amountInput = row.querySelector('.payment-amount-input');
        totalPaid += parseFloat(amountInput?.value) || 0;
    });

    const remaining = totalAmount - totalPaid;
    const change = totalPaid > totalAmount ? totalPaid - totalAmount : 0;

    /*
        المتبقّي كان يُكتب رقماً خاماً حين يكون موجباً:

            remaining > 0 ? remaining : money(0)

        فيظهر «103.5» بلا عملةٍ ولا منزلتين، بينما جاره «0.00 ر.س». وهو
        الرقمُ الذي يُقرأ للزبون.
    */
    document.getElementById('totalPaidAmount').textContent = money(totalPaid);
    document.getElementById('remainingAmount').textContent = money(remaining > 0 ? remaining : 0);
    document.getElementById('changeAmount').textContent = money(change);

    /*
        اللونُ يتبع الحالةَ لا مكانَ البطاقة: المتبقّي يحمرّ حين يكون،
        والباقي للعميل يخضرّ حين يوجد. وصفرٌ ملوَّنٌ يصرخ بلا خبر.
    */
    document.querySelector('.pay-sum--remaining')
        ?.classList.toggle('is-on', remaining > 0.01);

    document.querySelector('.pay-sum--change')
        ?.classList.toggle('is-on', change > 0.01);

    // شارةُ الاكتمال
    const badge = document.getElementById('paymentValidationBadge');
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    const done = remaining <= 0.01;

    if (badge) {
        badge.textContent = done ? 'مكتمل' : 'غير مكتمل';
        badge.className = 'pay-state ' + (done ? 'pay-state--done' : 'pay-state--short');
    }

    // الدفعُ الجزئيّ مسموحٌ بتحذير، فالزرُّ يبقى مفعَّلاً
    if (confirmBtn) {
        confirmBtn.disabled = false;
    }
}

// إضافة طريقة دفع جديدة
function addPaymentMethod(methodId = null, amount = 0) {
    const template = document.getElementById('paymentMethodTemplate');
    const list = document.getElementById('paymentMethodsList');

    if (!template || !list) return;

    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('.payment-method-row');
    const select = row.querySelector('.payment-method-select');
    const amountInput = row.querySelector('.payment-amount-input');
    const removeBtn = row.querySelector('.remove-payment-method');

    // تعيين طريقة الدفع المحددة
    if (methodId && select) {
        select.value = methodId;
    } else if (appData.defaultPaymentMethodId && select) {
        select.value = appData.defaultPaymentMethodId;
    }

    // تعيين المبلغ
    if (amountInput) {
        amountInput.value = amount.toFixed(2);
        amountInput.addEventListener('input', updatePaymentSummary);
    }

    // حذف طريقة الدفع
    removeBtn.addEventListener('click', function() {
        row.remove();
        updatePaymentSummary();
    });

    list.appendChild(clone);
    updatePaymentSummary();
}

// إعادة تعيين قائمة طرق الدفع
function resetPaymentMethodsList() {
    const list = document.getElementById('paymentMethodsList');
    if (list) {
        list.innerHTML = '';
    }
}

// جمع طرق الدفع المحددة
function collectPaymentMethods() {
    const payments = [];
    const paymentRows = document.querySelectorAll('#paymentMethodsList .payment-method-row');

    paymentRows.forEach(row => {
        const select = row.querySelector('.payment-method-select');
        const amountInput = row.querySelector('.payment-amount-input');
        const amount = parseFloat(amountInput?.value) || 0;

        if (amount > 0 && select) {
            const selectedOption = select.options[select.selectedIndex];
            payments.push({
                method_id: select.value,
                amount: amount,
                is_online: selectedOption?.dataset?.isOnline === 'true'
            });
        }
    });

    return payments;
}

// إظهار حالة التحميل في نافذة الدفع
function showPaymentLoading() {
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    const modalBody = document.querySelector('#paymentModal .modal-body');

    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري المعالجة...';
    }

    // إضافة overlay للتحميل
    const loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'paymentLoadingOverlay';
    loadingOverlay.style.cssText = 'position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 1000; border-radius: 8px;';
    loadingOverlay.innerHTML = `
        <div style="width: 60px; height: 60px; border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        <p style="margin-top: 15px; color: #667eea; font-weight: bold;">جاري معالجة الدفع...</p>
    `;

    if (modalBody) {
        modalBody.style.position = 'relative';
        modalBody.appendChild(loadingOverlay);
    }
}

// إخفاء حالة التحميل
function hidePaymentLoading() {
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    const loadingOverlay = document.getElementById('paymentLoadingOverlay');

    if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-check me-2"></i>تأكيد الدفع';
    }

    if (loadingOverlay) {
        loadingOverlay.remove();
    }
}

// تأكيد الدفع مع التحقق من المبالغ
function confirmPayment() {
    const totalAmount = parseFloat(document.getElementById('modalTotalAmount').textContent.replace(' ' + CURRENCY, ''));
    const payments = collectPaymentMethods();

    // التحقق من وجود طرق دفع
    if (payments.length === 0) {
        Swal.fire({
            title: 'لا توجد طرق دفع',
            text: 'يرجى إضافة طريقة دفع واحدة على الأقل',
            icon: 'warning'
        });
        return;
    }

    const totalPaid = payments.reduce((sum, p) => sum + p.amount, 0);
    const remaining = totalAmount - totalPaid;

    // التحقق من المبلغ المدفوع
    if (remaining > 0.01) {
        Swal.fire({
            title: 'المبلغ غير مكتمل',
            text: `يتبقى ${money(remaining)} لإتمام الدفع`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'متابعة كدفعة جزئية',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#ffc107'
        }).then((result) => {
            if (result.isConfirmed) {
                processPartialPayment(totalAmount, totalPaid);
            }
        });
        return;
    } else if (remaining < -0.01) {
        Swal.fire({
            title: 'المبلغ المدفوع أكبر من المستحق',
            text: `الباقي للعميل: ${money(Math.abs(remaining))}`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'متابعة مع الباقي',
            cancelButtonText: 'تعديل المبلغ',
            confirmButtonColor: '#17a2b8'
        }).then((result) => {
            if (result.isConfirmed) {
                processExcessPayment(totalAmount, totalPaid, Math.abs(remaining));
            }
        });
        return;
    }

    // إذا كان المبلغ صحيح تماماً
    processExactPayment(totalAmount);
}

// معالجة الدفعة الجزئية
function processPartialPayment(totalAmount, paidAmount) {
    showNotification('تم حفظ الدفعة الجزئية - الفاتورة لم تكتمل', 'warning');
    $('#paymentModal').modal('hide');
    resetPaymentForm();
}

// معالجة الدفعة الزائدة
function processExcessPayment(totalAmount, paidAmount, changeAmount) {
    showPaymentLoading();

    completePaymentProcess(totalAmount).then(() => {
        hidePaymentLoading();
        // تطبيق إعداد عرض نافذة الطباعة بعد تأكيد الفاتورة
        if (appData.settings.showPrintWindowAfterConfirm) {
            Swal.fire({
                title: 'تم الدفع بنجاح',
                text: `الباقي للعميل: ${money(changeAmount)}`,
                icon: 'success',
                confirmButtonText: 'طباعة الفاتورة',
                confirmButtonColor: '#28a745'
            }).then(() => {
                openPrintPage();
            });
        } else {
            Swal.fire({
                title: 'تم الدفع بنجاح',
                text: `الباقي للعميل: ${money(changeAmount)}`,
                icon: 'success',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        }
    }).catch(() => {
        hidePaymentLoading();
    });
}

// معالجة الدفعة الصحيحة
function processExactPayment(totalAmount) {
    showPaymentLoading();

    completePaymentProcess(totalAmount).then(() => {
        hidePaymentLoading();
        Swal.fire({
            title: 'تم الدفع بنجاح!',
            text: 'تم إتمام العملية بنجاح',
            icon: 'success',
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false
        }).then(() => {
            // تطبيق إعداد عرض نافذة الطباعة بعد تأكيد الفاتورة
            if (appData.settings.showPrintWindowAfterConfirm) {
                openPrintPage();
            }
        });
    }).catch(() => {
        hidePaymentLoading();
    });
}

        // إتمام عملية الدفع
async function completePaymentProcess(totalAmount) {
                // جمع بيانات الدفع من قائمة طرق الدفع المتعددة
                const payments = collectPaymentMethods();

                const order = orders[currentOrder];

                // الحصول على معلومات الضريبة المختارة
                const taxSelect = document.getElementById('taxType');
                const selectedTaxId = taxSelect?.value || null;
                const selectedTaxRate = getSelectedTaxRate();

                const invoiceData = {
                    client_id: order.client?.id || null,
                    client_name: order.client?.trade_name || 'عميل نقدي',
                    products: order.items.map(item => ({
                        id: item.id,
                        product_unit_id: item.product_unit_id || null, // معرف الوحدة المستخدمة (null = سعر أساسي بدون وحدة)
                        name: item.name,
                        unit_price: item.price,
                        quantity: item.quantity,
                        total: item.total,
                        conversion_factor: item.conversion_factor || 1, // معامل التحويل (1 = وحدة أساسية)
                        unit_name: item.unit_name || null // اسم الوحدة للعرض
                    })),
                    discount_type: order.discount.type,
                    discount_value: order.discount.value,
                    tax_id: selectedTaxId,
                    tax_rate: selectedTaxRate,
                    total: order.items.reduce((sum, item) => sum + item.total, 0),
                    net_total: totalAmount,
                    payments: payments
                };

    try {
        const response = await saveInvoiceToServer(invoiceData);
        if (response.success) {
            // حفظ معرف الفاتورة للطباعة
            window.lastInvoiceId = response.invoice_id;

            // إضافة الفاتورة الجديدة لقائمة الفواتير محلياً (بدون إعادة تحميل من السيرفر)
            addNewInvoiceToList(response, invoiceData, totalAmount);

            $('#paymentModal').modal('hide');
            resetPaymentForm();
            resetCurrentOrder();
            return response;
        } else {
            throw new Error(response.message || 'فشل في حفظ الفاتورة');
        }
    } catch (error) {
        console.error('خطأ في الدفع:', error);

        // لو الرد جاي من السيرفر برسالة
        let message = 'حدث خطأ أثناء معالجة الدفع';
        if (error.response && error.response.data && error.response.data.message) {
            message = error.response.data.message;
        } else if (error.message) {
            message = error.message;
        }

        Swal.fire({
            title: 'خطأ في المعاملة',
            text: message,
            icon: 'error'
        });

        throw error;
    }


}

// حفظ الفاتورة في الخادم
async function saveInvoiceToServer(invoiceData) {
    const response = await fetch(appData.urls.store, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': appData.csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify(invoiceData)
    });

    const result = await response.json();

    if (!response.ok) {
        // استخراج رسالة الخطأ من الاستجابة
        let errorMessage = result.message || 'حدث خطأ غير معروف';

        // إذا كانت هناك أخطاء validation
        if (result.errors) {
            const errorMessages = [];
            for (const field in result.errors) {
                errorMessages.push(...result.errors[field]);
            }
            errorMessage = errorMessages.join('\n');
        }

        throw new Error(errorMessage);
    }

    // الأرصدةُ الجديدة إلى الشاشة — وإلّا بقيت تعرض ما قبل البيع
    applyStockUpdate(result.stock);

    return result;
}

// فتح صفحة الطباعة
function openPrintPage() {
    if (window.lastInvoiceId) {

        const printUrl = appData.urls.printInvoice.replace('__ID__', window.lastInvoiceId);
        window.open(printUrl, '_blank', 'width=800,height=600');
    } else {
        showError('لا يمكن العثور على الفاتورة للطباعة');
    }
}

// إعادة تعيين نموذج الدفع
function resetPaymentForm() {
    // إعادة تعيين قائمة طرق الدفع
    resetPaymentMethodsList();

    // إعادة تعيين الملخص
    document.getElementById('totalPaidAmount').textContent = money(0);
    document.getElementById('remainingAmount').textContent = money(0);
    document.getElementById('changeAmount').textContent = money(0);
    document.getElementById('modalTotalAmount').textContent = money(0);

    // إعادة تعيين شارة التحقق
    const badge = document.getElementById('paymentValidationBadge');
    if (badge) {
        badge.textContent = 'غير مكتمل';
        badge.className = 'badge bg-danger';
    }
}
