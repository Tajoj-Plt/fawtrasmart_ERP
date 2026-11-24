/*
    ═══════════ اختيارُ العميل ═══════════

    العميلُ النقدي هو الافتراضي — أكثرُ ما يُباع بيعٌ نقديٌّ لمن
    لا ملفَّ له.
*/

// ==========================================
// إعداد القائمة المنسدلة لاختيار العميل
// ==========================================
function setupClientSelector() {
    // فتح/إغلاق القائمة المنسدلة
    clientSelector.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleClientDropdown();
    });

    // البحث عن العملاء
    clientSearchInput.addEventListener('input', function(e) {
        clearTimeout(clientSearchTimeout);
        const query = e.target.value.trim();

        clientSearchTimeout = setTimeout(() => {
            if (query.length >= 1) {
                searchClients(query);
            } else {
                // إظهار العميل الافتراضي إذا كان موجوداً
                showDefaultClient();
            }
        }, 300);
    });

    // اختيار عميل من القائمة
    clientResults.addEventListener('click', function(e) {
        const resultItem = e.target.closest('.client-result-item');
        if (resultItem) {
            selectClientFromDropdown(resultItem);
        }
    });

    // إغلاق القائمة عند النقر خارجها
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.client-selector-wrapper')) {
            closeClientDropdown();
        }
    });

    // منع إغلاق القائمة عند النقر داخل حقل البحث
    clientSearchInput.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

// فتح/إغلاق القائمة المنسدلة
function toggleClientDropdown() {
    const isOpen = clientDropdown.classList.contains('show');
    if (isOpen) {
        closeClientDropdown();
    } else {
        openClientDropdown();
    }
}

function openClientDropdown() {
    clientDropdown.classList.add('show');
    clientSelector.classList.add('open');
    clientSearchInput.focus();
}

function closeClientDropdown() {
    clientDropdown.classList.remove('show');
    clientSelector.classList.remove('open');
    clientSearchInput.value = '';
}

// عرض العميل الافتراضي
function showDefaultClient() {
    const defaultClient = appData.defaultClient;
    const currentClient = orders[currentOrder].client;

    let html = '';

    if (currentClient) {
        html += createClientResultItem(currentClient, true);
    } else if (defaultClient) {
        html += createClientResultItem(defaultClient, false);
    }

    clientResults.innerHTML = html + `
        <div class="client-loading" style="display: none;">
            <div class="spinner"></div>
            <span>جاري البحث...</span>
        </div>
        <div class="client-no-results" style="display: none;">
            <i class="fas fa-user-slash"></i>
            <span>لا توجد نتائج</span>
        </div>
    `;
}

// إنشاء عنصر نتيجة العميل
function createClientResultItem(client, isActive = false) {
    return `
        <div class="client-result-item ${isActive ? 'active' : ''}" 
             data-client-id="${client.id}" 
             data-client-name="${esc(client.trade_name)}"
             data-client-phone="${client.phone || ''}"
             data-client-code="${client.code || ''}">
            <div class="client-info">
                <span class="client-name">${client.trade_name}</span>
                <span class="client-code">${client.code || ''}</span>
            </div>
            <span class="client-phone">${client.phone || ''}</span>
        </div>
    `;
}

// البحث عن العملاء عبر AJAX
function searchClients(query) {
    const loadingEl = clientResults.querySelector('.client-loading');
    const noResultsEl = clientResults.querySelector('.client-no-results');

    // إظهار حالة التحميل
    clientResults.querySelectorAll('.client-result-item').forEach(el => el.remove());
    if (loadingEl) loadingEl.style.display = 'flex';
    if (noResultsEl) noResultsEl.style.display = 'none';

    fetch(`${appData.urls.searchClients}?query=${encodeURIComponent(query)}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': appData.csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (loadingEl) loadingEl.style.display = 'none';

        if (data.success && data.clients && data.clients.length > 0) {
            let html = '';
            const currentClientId = orders[currentOrder].client?.id;

            data.clients.forEach(client => {
                const isActive = client.id === currentClientId;
                html += createClientResultItem(client, isActive);
            });

            // إضافة العناصر قبل عناصر التحميل والنتائج الفارغة
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            while (tempDiv.firstChild) {
                clientResults.insertBefore(tempDiv.firstChild, loadingEl);
            }
        } else {
            if (noResultsEl) noResultsEl.style.display = 'flex';
        }
    })
    .catch(error => {
        console.error('خطأ في البحث عن العملاء:', error);
        if (loadingEl) loadingEl.style.display = 'none';
        if (noResultsEl) noResultsEl.style.display = 'flex';
    });
}

// اختيار عميل من القائمة المنسدلة
function selectClientFromDropdown(resultItem) {
    const client = {
        id: parseInt(resultItem.dataset.clientId),
        trade_name: resultItem.dataset.clientName,
        phone: resultItem.dataset.clientPhone,
        code: resultItem.dataset.clientCode
    };

    // تحديث الطلب الحالي
    orders[currentOrder].client = client;

    // تحديث العرض
    selectedClientName.textContent = client.trade_name;

    // تحديث العنصر النشط في القائمة
    clientResults.querySelectorAll('.client-result-item').forEach(el => {
        el.classList.remove('active');
    });
    resultItem.classList.add('active');

    // إغلاق القائمة
    closeClientDropdown();

    // عرض إشعار
    showNotification(`تم اختيار العميل: ${client.trade_name}`, 'success');
}

// اختيار عميل (للتوافق مع الكود القديم)
function selectClient(clientId) {
    // البحث عن العميل
    fetch(`${appData.urls.searchClients}?query=${clientId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': appData.csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.clients && data.clients.length > 0) {
            const client = data.clients.find(c => c.id == clientId);
            if (client) {
                orders[currentOrder].client = client;
                selectedClientName.textContent = client.trade_name;
                showNotification(`تم اختيار العميل: ${client.trade_name}`, 'success');
            }
        }
    })
    .catch(error => {
        console.error('خطأ في اختيار العميل:', error);
    });
}
