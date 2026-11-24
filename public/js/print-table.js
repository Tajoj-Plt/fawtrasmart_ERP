/**
 * دالة طباعة جدول عامة ومرنة
 * يمكن استخدامها مع أي جدول وأي عدد من الأعمدة
 *
 * @param {Object} options - خيارات الطباعة
 * @param {string} options.tableSelector - محدد الجدول (مثل: '#transactionsTableBody')
 * @param {string} options.tfootSelector - محدد tfoot (اختياري)
 * @param {string} options.title - عنوان التقرير
 * @param {Array} options.infoCards - بطاقات المعلومات (اختياري)
 * @param {Object} options.filterInfo - معلومات الفلاتر (اختياري)
 * @param {Array} options.tableHeaders - رؤوس الأعمدة
 * @param {Array} options.columnWidths - عرض الأعمدة بالنسبة المئوية
 * @param {number} options.descriptionColumn - رقم عمود الوصف للمحاذاة لليمين (اختياري)
 * @param {Array<number>} options.skipColumns - أرقام أعمدة (من الصفر) تُحذف من الورقة
 */
function printTable(options) {
    const {
        tableSelector,
        tfootSelector = null,
        title = 'تقرير',
        infoCards = [],
        filterInfo = {},
        tableHeaders = [],
        columnWidths = [],
        descriptionColumn = null,
        skipColumns = [],
        windowWidth = 1200,
        windowHeight = 800
    } = options;

    // فتح نافذة جديدة
    const printWindow = window.open('', '', `height=${windowHeight},width=${windowWidth}`);

    // جمع بيانات الجدول
    /*
        أعمدةٌ لا معنى لها على الورق.

        كان الصفّ يُنسخ بـ`outerHTML` كما هو — بما فيه خلية «إجراءات»
        وقائمتها المنسدلة بأزرار التعديل والحذف. فتخرج الورقة المطبوعة
        وفيها أزرارٌ لا تُنقر، وعمودٌ يأكل عرضاً في صفحةٍ ضيّقة أصلاً.

        `skipColumns` اختياريّ: من لا يمرّره لا يتغيّر عنده شيء.
    */
    const skipSet = new Set(skipColumns);

    const stripSkipped = (row) => {
        if (skipSet.size === 0) {
            return row.outerHTML;
        }

        const clone = row.cloneNode(true);

        Array.from(clone.children)
            .filter((cell, index) => skipSet.has(index))
            .forEach((cell) => cell.remove());

        return clone.outerHTML;
    };

    const tableRows = document.querySelectorAll(tableSelector);
    let tableContent = '';
    tableRows.forEach(function(row) {
        tableContent += stripSkipped(row);
    });

    // جمع بيانات tfoot إن وجدت
    let tfootContent = '';
    if (tfootSelector) {
        const tfootElement = document.querySelector(tfootSelector);
        if (tfootElement) {
            tfootContent = tfootElement.innerHTML;
        }
    }

    // بناء بطاقات المعلومات
    let infoCardsHtml = '';
    if (infoCards.length > 0) {
        const infoItems = infoCards.map(card => `
            <div class="info-item">
                <label>${card.label}</label>
                <value class="${card.class || ''}">${card.value}</value>
            </div>
        `).join('');

        infoCardsHtml = `
            <div class="info-section">
                <div class="info-row">
                    ${infoItems}
                </div>
            </div>
        `;
    }

    // بناء معلومات الفلاتر
    let filterInfoHtml = '';
    if (Object.keys(filterInfo).length > 0) {
        const filterItems = Object.entries(filterInfo).map(([key, value]) =>
            `<strong>${key}:</strong> ${value}`
        ).join(' | ');

        filterInfoHtml = `
            <div class="filter-info">
                ${filterItems}
            </div>
        `;
    }

    // بناء رؤوس الأعمدة
    /*
        ترويسة المنشأة من الإعدادات.

        كانت كل ورقةٍ تُطبع تحمل عنوان التقرير وتاريخ الطباعة فقط — بلا
        اسم المنشأة ولا عنوانها ولا رقمها الضريبي. ورقةٌ تخرج من النظام
        ولا يُعرف من أصدرها. `window.AppOrg` يُحقن من القالب الرئيسي
        ويقرأ من «إعدادات المنشأة».
    */
    const org = window.AppOrg || {};
    const orgHeaderHtml = org.name
        ? `<div class="org">
               <h2 style="margin:0 0 2px;font-size:15pt;">${org.name}</h2>
               ${org.address ? `<p style="margin:1px 0;font-size:9pt;">${org.address}</p>` : ''}
               ${org.identity ? `<p style="margin:1px 0;font-size:9pt;">${org.identity}</p>` : ''}
           </div>`
        : '';

    const headersHtml = tableHeaders
        .map((header, index) => ({ header, width: columnWidths[index] || 'auto', index }))
        .filter(({ index }) => ! skipSet.has(index))
        .map(({ header, width }) => `<th style="width: ${width};">${header}</th>`)
        .join('');

    // تحديد محاذاة عمود الوصف
    let descriptionStyleRule = '';
    if (descriptionColumn !== null) {
        descriptionStyleRule = `
            .table tbody td:nth-child(${descriptionColumn}),
            .table tfoot td:nth-child(${descriptionColumn}) {
                text-align: right;
                padding-right: 12px;
            }
        `;
    }

    // بناء محتوى HTML للطباعة
    const printContent = `
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <title>${title}</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Arial', 'Tahoma', sans-serif;
                    padding: 25px;
                    background-color: white;
                    color: #333;
                    line-height: 1.6;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 15px;
                }
                .header h1 {
                    font-size: 28px;
                    color: #333;
                    margin-bottom: 8px;
                    font-weight: bold;
                }
                .header p {
                    color: #666;
                    font-size: 13px;
                    margin: 3px 0;
                }
                .info-section {
                    margin-bottom: 20px;
                    padding: 15px;
                    background-color: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #dee2e6;
                }
                .info-row:last-child {
                    margin-bottom: 0;
                    padding-bottom: 0;
                    border-bottom: none;
                }
                .info-item {
                    flex: 1;
                    text-align: center;
                }
                .info-item label {
                    display: block;
                    font-size: 12px;
                    color: #666;
                    margin-bottom: 4px;
                }
                .info-item value {
                    display: block;
                    font-size: 16px;
                    font-weight: bold;
                    color: #333;
                }
                .filter-info {
                    background-color: #f8f9fa;
                    padding: 12px 15px;
                    margin-bottom: 20px;
                    border-right: 3px solid #333;
                    font-size: 13px;
                }
                .filter-info strong {
                    color: #333;
                }
                .table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                    border: 1px solid #333;
                }
                .table thead th {
                    background-color: #333;
                    color: white;
                    padding: 12px 8px;
                    text-align: center;
                    font-weight: bold;
                    border: 1px solid #333;
                    font-size: 13px;
                }
                .table tbody td {
                    padding: 10px 8px;
                    border: 1px solid #ddd;
                    text-align: center;
                    font-size: 12px;
                }
                .table tbody tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                .table tfoot tr {
                    font-weight: bold;
                    font-size: 13px;
                    background-color: #e9ecef;
                }
                .table tfoot td {
                    padding: 12px 8px;
                    border: 1px solid #333;
                }
                .table-secondary td {
                    background-color: #e9ecef !important;
                    font-weight: bold;
                    border: 1px solid #333 !important;
                }
                .table-success td {
                    background-color: #e9ecef !important;
                    font-weight: bold;
                }
                .table-danger td {
                    background-color: #e9ecef !important;
                    font-weight: bold;
                }
                .table-warning td {
                    background-color: #dee2e6 !important;
                    font-weight: bold;
                    font-size: 14px;
                }
                .text-success {
                    color: #28a745 !important;
                    font-weight: bold;
                }
                .text-danger {
                    color: #dc3545 !important;
                    font-weight: bold;
                }
                .badge {
                    background-color: #6c757d;
                    color: white;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: normal;
                }
                .badge-light-primary {
                    background-color: #e3e8ff;
                    color: #5e72e4;
                }
                .fw-bold {
                    font-weight: bold;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 2px solid #333;
                    text-align: center;
                    color: #666;
                    font-size: 11px;
                }
                a {
                    text-decoration: none;
                    color: inherit;
                }
                ${descriptionStyleRule}
                @media print {
                    body {
                        margin: 0;
                        padding: 15px;
                    }
                    .header {
                        page-break-after: avoid;
                    }
                    tr {
                        page-break-inside: avoid;
                    }
                    thead {
                        display: table-header-group;
                    }
                    tfoot {
                        display: table-footer-group;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                ${orgHeaderHtml}
                <h1>${title}</h1>
                <p>تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')} - ${new Date().toLocaleTimeString('ar-SA')}</p>
            </div>

            ${infoCardsHtml}
            ${filterInfoHtml}

            <table class="table">
                <thead>
                    <tr>
                        ${headersHtml}
                    </tr>
                </thead>
                <tbody>
                    ${tableContent}
                </tbody>
                ${tfootContent ? `<tfoot>${tfootContent}</tfoot>` : ''}
            </table>

            <div class="footer">
                <p>تم إنشاء هذا التقرير بواسطة النظام المحاسبي - جميع الحقوق محفوظة ${new Date().getFullYear()}</p>
            </div>
        </body>
        </html>
    `;

    printWindow.document.write(printContent);
    printWindow.document.close();

    // انتظر تحميل الصفحة ثم اطبع تلقائيًا
    setTimeout(function() {
        printWindow.focus();
        printWindow.print();
    }, 500);
}

// تصدير الدالة للاستخدام العام
if (typeof module !== 'undefined' && module.exports) {
    module.exports = printTable;
}
