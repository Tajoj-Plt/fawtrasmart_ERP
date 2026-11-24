// Constants
const STATUS_MESSAGES = {
    success: 'تم تحديث الحالة بنجاح',
    error: 'حدث خطأ أثناء تحديث الحالة'
};

const LOADING_TEMPLATE = `
    <div class="tab-loading" id="status-loading">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">جاري التحديث...</span>
        </div>
        <p class="mt-2">جاري تحديث الحالة...</p>
    </div>
`;

// Utility Functions
const showLoading = (elementId) => {
    document.getElementById(elementId).style.display = 'block';
};

const hideLoading = (elementId) => {
    document.getElementById(elementId).style.display = 'none';
};

const showError = (containerId, message) => {
    const container = document.getElementById(containerId);
    container.innerHTML = `
        <div class="alert alert-danger text-center" role="alert">
            <p class="mb-0">${message}</p>
        </div>
    `;
};

// Core Functions
const updateProductStatus = async (dropdown) => {
    const productId = dropdown.dataset.productId;
    const status = dropdown.value;
    const token = document.querySelector('meta[name="csrf-token"]').content;

    dropdown.style.display = 'none';
    dropdown.insertAdjacentHTML('afterend', LOADING_TEMPLATE);

    try {
        const response = await fetch(`/stock/products/${productId}/update-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({ status })
        });

        const data = await response.json();
        
        if (data.success) {
            alert(STATUS_MESSAGES.success);
            dropdown.value = status;
        } else {
            alert(`${STATUS_MESSAGES.error}: ${data.message}`);
        }
    } catch (error) {
        console.error('Error:', error);
        alert(STATUS_MESSAGES.error);
    } finally {
        document.getElementById('status-loading')?.remove();
        dropdown.style.display = '';
    }
};

const loadStockMovements = async (page = 1) => {
    showLoading('stock-loading');
    const stockMovementsContent = document.getElementById('stock-movements-content');
    stockMovementsContent.innerHTML = '';

    const sourceId = document.getElementById('source_filter')?.value;
    const dateFrom = document.getElementById('date_from_filter')?.value;
    const dateTo = document.getElementById('date_to_filter')?.value;

    try {
        const response = await fetch(`/stock/products/${productId}/stock-movements?page=${page}&source_id=${sourceId}&date_from=${dateFrom}&date_to=${dateTo}`);
        const data = await response.json();
        hideLoading('stock-loading');
        stockMovementsContent.innerHTML = data.html;
    } catch (error) {
        hideLoading('stock-loading');
        showError('stock-movements-content', 'حدث خطأ أثناء تحميل البيانات. يرجى المحاولة مرة أخرى.');
        console.error('Error loading stock movements:', error);
    }
};

const loadTimeline = async (page = 1) => {
    showLoading('timeline-loading');
    const timelineContent = document.getElementById('timeline-content');
    timelineContent.innerHTML = '';

    try {
        const response = await fetch(`/stock/products/${productId}/timeline?page=${page}`);
        const data = await response.json();
        hideLoading('timeline-loading');
        timelineContent.innerHTML = data.html;
    } catch (error) {
        hideLoading('timeline-loading');
        showError('timeline-content', 'حدث خطأ أثناء تحميل البيانات. يرجى المحاولة مرة أخرى.');
        console.error('Error loading timeline:', error);
    }
};

const loadActivityLogs = async (page = 1) => {
    const activityLogsContent = document.getElementById('activity-logs-content');
    activityLogsContent.innerHTML = LOADING_TEMPLATE;

    try {
        const response = await fetch(`/stock/products/${productId}/activity-logs?page=${page}`);
        const data = await response.json();
        activityLogsContent.innerHTML = data.html;
    } catch (error) {
        showError('activity-logs-content', 'حدث خطأ أثناء تحميل البيانات. يرجى المحاولة مرة أخرى.');
        console.error('Error loading activity logs:', error);
    }
};

const adjustTableDisplay = () => {
    const tables = document.querySelectorAll('.table-responsive');
    tables.forEach(table => {
        if (window.innerWidth < 768) {
            table.classList.add('table-responsive-sm');
        } else {
            table.classList.remove('table-responsive-sm');
        }
    });
};

// Form Utility Functions
const updateDisabledState = () => {
    const trackStockCheckbox = document.getElementById('ProductTrackStock');
    if (trackStockCheckbox) {
        disableForm(!trackStockCheckbox.checked);
    }
};

const disableForm = (flag) => {
    document.querySelectorAll('.ProductTrackingInput').forEach(element => {
        element.readOnly = flag;
        element.disabled = flag;
    });
};

const updateFeaturedProduct = () => {
    const availableOnline = document.getElementById('available_online');
    const featuredProduct = document.getElementById('featured_product');
    if (availableOnline && featuredProduct) {
        featuredProduct.disabled = !availableOnline.checked;
    }
};

// Barcode Functions
const printBarcode = (productId) => {
    // استخدام القيم من window.barcodeData (يجب تعريفها في blade file)
    const { name, serialNumber, barcode } = window.barcodeData;
    
    const printWindow = window.open('', '_blank');
    const barcodeValue = barcode || serialNumber;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <title>طباعة الباركود - ${serialNumber}</title>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    text-align: center;
                    direction: rtl;
                }
                .barcode-container {
                    margin: 20px auto;
                    padding: 20px;
                    border: 1px solid #ddd;
                    display: inline-block;
                    background: white;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    border-radius: 8px;
                }
                .product-name {
                    font-size: 18px;
                    font-weight: bold;
                    margin: 10px 0;
                    color: #333;
                }
                .product-code {
                    font-size: 14px;
                    color: #666;
                    margin: 5px 0;
                }
            </style>
        </head>
        <body>
            <div class="barcode-container">
                <div class="product-name">${name}</div>
                <div class="product-code">#${serialNumber}</div>
                <svg id="barcode"></svg>
                ${barcode ? `<div class="product-code">${barcode}</div>` : ''}
            </div>
            <div class="no-print" style="margin-top: 20px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">طباعة</button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">إغلاق</button>
            </div>
            <script>
                window.onload = function() {
                    JsBarcode("#barcode", "${barcodeValue}", {
                        format: "CODE128",
                        width: 2,
                        height: 80,
                        displayValue: true,
                        fontSize: 16,
                        margin: 10
                    });
                };
            </script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
};

// Event Listeners Setup
const setupEventListeners = () => {
    // Status Dropdown
    document.querySelectorAll('.status-dropdown').forEach(dropdown => {
        dropdown.addEventListener('change', () => updateProductStatus(dropdown));
    });

    // Tab Events
    const setupTabEvent = (tabId, loadFunction) => {
        const tab = document.getElementById(tabId);
        if (tab) {
            tab.addEventListener('shown.bs.tab', () => {
                const contentId = tab.getAttribute('aria-controls');
                const content = document.getElementById(contentId);
                if (content && content.innerHTML.trim() === '') {
                    loadFunction(1);
                }
            });
        }
    };

    setupTabEvent('profile-tab', loadStockMovements);
    setupTabEvent('about-tab', loadTimeline);
    setupTabEvent('activate-tab', loadActivityLogs);

    // Responsive Tables
    window.addEventListener('resize', adjustTableDisplay);
    adjustTableDisplay();

    // Form Controls
    const trackStockCheckbox = document.getElementById('ProductTrackStock');
    if (trackStockCheckbox) {
        trackStockCheckbox.addEventListener('change', updateDisabledState);
    }

    const availableOnlineCheckbox = document.getElementById('available_online');
    if (availableOnlineCheckbox) {
        availableOnlineCheckbox.addEventListener('change', updateFeaturedProduct);
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', setupEventListeners);