/**
 * ====================================
 * Excel Export Helper - مساعد تصدير Excel
 * ====================================
 * ملف عام لتصدير التقارير إلى Excel بشكل احترافي
 * قابل للاستخدام مع صفحات متعددة بأعمدة وعناوين مختلفة
 * 
 * @requires ExcelJS
 * @requires FileSaver
 * @version 1.0.0
 */

/**
 * الألوان الافتراضية للتقرير
 */
const EXCEL_COLORS = {
    primary: '1A365D',
    primaryLight: '2B6CB0',
    success: '276749',
    successLight: 'C6F6D5',
    successBg: 'F0FFF4',
    danger: '9B2C2C',
    dangerLight: 'FED7D7',
    dangerBg: 'FFF5F5',
    warning: 'B7791F',
    warningLight: 'FEFCBF',
    purple: '553C9A',
    purpleLight: 'E9D8FD',
    gray: '718096',
    grayLight: 'E2E8F0',
    grayBg: 'F7FAFC',
    white: 'FFFFFF',
    black: '1A202C',
    border: 'CBD5E0'
};

/**
 * تنسيق الأرقام بفواصل
 * @param {number} number - الرقم المراد تنسيقه
 * @param {number} decimals - عدد الخانات العشرية (افتراضي: 2)
 * @returns {string} - الرقم المنسق
 */
function formatNumber(number, decimals = 2) {
    return parseFloat(number || 0).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

/**
 * تصدير تقرير إلى Excel بشكل احترافي
 * 
 * @param {Object} config - إعدادات التصدير
 * @param {string} config.fileName - اسم الملف (بدون امتداد)
 * @param {string} config.mainTitle - العنوان الرئيسي للتقرير
 * @param {string} config.subTitle - العنوان الفرعي (اختياري)
 * @param {Object} config.reportInfo - معلومات التقرير (مثل: الفترة، الفرع، إلخ)
 * @param {Array} config.statisticsCards - بطاقات الإحصائيات
 * @param {Array} config.tableHeaders - رؤوس أعمدة الجدول
 * @param {Array} config.tableData - بيانات الجدول
 * @param {Array} config.columnWidths - عرض الأعمدة (اختياري)
 * @param {Object} config.summaryRows - صفوف الملخص النهائي (اختياري)
 * @param {Object} config.summarySheet - بيانات ورقة الملخص (اختياري)
 * @param {string} config.buttonSelector - محدد زر التصدير (لعرض حالة التحميل)
 * @param {Object} config.colors - الألوان المخصصة (اختياري)
 * 
 * @returns {Promise<void>}
 */
async function exportToExcelProfessional(config) {
    // التحقق من المكتبات المطلوبة
    if (typeof ExcelJS === 'undefined') {
        console.error('ExcelJS library is not loaded');
        showMessage('خطأ: مكتبة ExcelJS غير محملة', 'error');
        return;
    }
    if (typeof saveAs === 'undefined') {
        console.error('FileSaver library is not loaded');
        showMessage('خطأ: مكتبة FileSaver غير محملة', 'error');
        return;
    }

    // حالة التحميل
    let loadingBtn = null;
    let originalText = '';
    if (config.buttonSelector) {
        loadingBtn = $(config.buttonSelector);
        originalText = loadingBtn.html();
        loadingBtn.html('<i class="feather icon-loader spin"></i> جاري التصدير...').prop('disabled', true);
    }

    try {
        // إنشاء workbook جديد
        const workbook = new ExcelJS.Workbook();
        workbook.creator = config.creator || 'نظام المحاسبة';
        workbook.created = new Date();

        // الألوان
        const colors = config.colors || EXCEL_COLORS;

        // =====================================
        // الورقة الرئيسية
        // =====================================
        const ws = workbook.addWorksheet(config.mainTitle || 'التقرير', {
            properties: { tabColor: { argb: colors.primary } },
            views: [{ rightToLeft: true, showGridLines: false }]
        });

        // عرض الأعمدة
        const numColumns = config.tableHeaders.length;
        if (config.columnWidths && config.columnWidths.length === numColumns) {
            ws.columns = config.columnWidths.map(width => ({ width }));
        } else {
            // عرض افتراضي
            ws.columns = Array(numColumns).fill({ width: 15 });
        }

        let currentRow = 1;

        // ===== الهيدر =====
        ws.addRow([]); currentRow++;
        
        /*
            اسم المنشأة فوق عنوان التقرير.

            كانت الورقة المُصدَّرة تبدأ بعنوان التقرير مباشرةً، فلا يُعرف
            من ملفٍ مُرسَلٍ بالبريد لأيّ منشأةٍ هو. `window.AppOrg` يُحقن
            من القالب الرئيسي ويقرأ من «إعدادات المنشأة».
        */
        const org = window.AppOrg || {};

        if (org.name) {
            ws.mergeCells(`A${currentRow}:${getColumnLetter(numColumns)}${currentRow}`);
            const orgCell = ws.getCell(`A${currentRow}`);
            orgCell.value = org.name;
            orgCell.font = { name: 'Arial', size: 14, bold: true, color: { argb: colors.gray } };
            orgCell.alignment = { horizontal: 'center', vertical: 'middle' };
            ws.getRow(currentRow).height = 24;
            currentRow++;

            const orgLine = [org.address, org.identity].filter(Boolean).join('  —  ');

            if (orgLine) {
                ws.mergeCells(`A${currentRow}:${getColumnLetter(numColumns)}${currentRow}`);
                const orgInfoCell = ws.getCell(`A${currentRow}`);
                orgInfoCell.value = orgLine;
                orgInfoCell.font = { name: 'Arial', size: 10, color: { argb: colors.gray } };
                orgInfoCell.alignment = { horizontal: 'center', vertical: 'middle' };
                ws.getRow(currentRow).height = 18;
                currentRow++;
            }
        }

        // العنوان الرئيسي
        ws.mergeCells(`A${currentRow}:${getColumnLetter(numColumns)}${currentRow}`);
        const titleCell = ws.getCell(`A${currentRow}`);
        titleCell.value = config.mainTitle;
        titleCell.font = { name: 'Arial', size: 22, bold: true, color: { argb: colors.primary } };
        titleCell.alignment = { horizontal: 'center', vertical: 'middle' };
        ws.getRow(currentRow).height = 35;
        currentRow++;

        // العنوان الفرعي
        if (config.subTitle) {
            ws.mergeCells(`A${currentRow}:${getColumnLetter(numColumns)}${currentRow}`);
            const subtitleCell = ws.getCell(`A${currentRow}`);
            subtitleCell.value = config.subTitle;
            subtitleCell.font = { name: 'Arial', size: 12, color: { argb: colors.gray } };
            subtitleCell.alignment = { horizontal: 'center', vertical: 'middle' };
            ws.getRow(currentRow).height = 22;
            currentRow++;
        }

        // خط فاصل
        ws.addRow([]); currentRow++;
        ws.mergeCells(`A${currentRow}:${getColumnLetter(numColumns)}${currentRow}`);
        const lineCell = ws.getCell(`A${currentRow}`);
        lineCell.border = { bottom: { style: 'medium', color: { argb: colors.primary } } };
        currentRow++;
        ws.addRow([]); currentRow++;

        // ===== معلومات التقرير =====
        if (config.reportInfo) {
            const infoKeys = Object.keys(config.reportInfo);
            const halfLength = Math.ceil(infoKeys.length / 2);
            
            for (let i = 0; i < halfLength; i++) {
                const key1 = infoKeys[i];
                const value1 = config.reportInfo[key1];
                
                const midCol = Math.ceil(numColumns / 2);
                const key2 = infoKeys[i + halfLength];
                const value2 = key2 ? config.reportInfo[key2] : '';
                
                const infoRow = ws.addRow([]);
                
                // العمود الأول
                ws.getCell(currentRow, 2).value = key1 + ':';
                ws.getCell(currentRow, 2).font = { bold: true, color: { argb: colors.gray } };
                ws.getCell(currentRow, 3).value = value1;
                ws.getCell(currentRow, 3).font = { color: { argb: colors.black } };
                
                // العمود الثاني (إذا وجد)
                if (key2) {
                    ws.getCell(currentRow, midCol + 1).value = key2 + ':';
                    ws.getCell(currentRow, midCol + 1).font = { bold: true, color: { argb: colors.gray } };
                    ws.getCell(currentRow, midCol + 2).value = value2;
                    ws.getCell(currentRow, midCol + 2).font = { color: { argb: colors.black } };
                }
                
                currentRow++;
            }
            
            ws.addRow([]); currentRow++;
            ws.addRow([]); currentRow++;
        }

        // ===== بطاقات الإحصائيات =====
        if (config.statisticsCards && config.statisticsCards.length > 0) {
            currentRow = addStatisticsCards(ws, config.statisticsCards, numColumns, currentRow, colors);
            ws.addRow([]); currentRow++;
            ws.addRow([]); currentRow++;
        }

        // ===== عنوان الجدول =====
        if (config.tableTitle) {
            ws.mergeCells(`A${currentRow}:${getColumnLetter(numColumns)}${currentRow}`);
            const tableTitle = ws.getCell(`A${currentRow}`);
            tableTitle.value = config.tableTitle;
            tableTitle.font = { name: 'Arial', size: 14, bold: true, color: { argb: colors.primary } };
            tableTitle.border = { bottom: { style: 'medium', color: { argb: colors.primary } } };
            ws.getRow(currentRow).height = 28;
            currentRow++;

            ws.addRow([]); currentRow++;
        }

        // ===== رأس الجدول =====
        const headerRow = ws.addRow(config.tableHeaders);
        headerRow.height = 28;
        headerRow.eachCell((cell) => {
            cell.font = { name: 'Arial', size: 11, bold: true, color: { argb: colors.white } };
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: colors.primary } };
            cell.alignment = { horizontal: 'center', vertical: 'middle' };
            cell.border = {
                top: { style: 'thin', color: { argb: colors.border } },
                bottom: { style: 'thin', color: { argb: colors.border } },
                left: { style: 'thin', color: { argb: colors.border } },
                right: { style: 'thin', color: { argb: colors.border } }
            };
        });
        currentRow++;

        // ===== بيانات الجدول =====
        let isAlternate = false;
        config.tableData.forEach((rowData) => {
            const dataRow = ws.addRow(rowData.values);
            dataRow.height = 22;
            
            dataRow.eachCell((cell, colNumber) => {
                if (isAlternate) {
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: colors.grayBg } };
                }
                
                cell.border = {
                    top: { style: 'thin', color: { argb: colors.border } },
                    bottom: { style: 'thin', color: { argb: colors.border } },
                    left: { style: 'thin', color: { argb: colors.border } },
                    right: { style: 'thin', color: { argb: colors.border } }
                };
                
                cell.alignment = { 
                    horizontal: rowData.alignments && rowData.alignments[colNumber - 1] ? rowData.alignments[colNumber - 1] : 'center',
                    vertical: 'middle'
                };

                // تنسيق خاص للخلايا
                if (rowData.cellStyles && rowData.cellStyles[colNumber - 1]) {
                    const style = rowData.cellStyles[colNumber - 1];
                    if (style.color) cell.font = { ...cell.font, color: { argb: colors[style.color] || style.color }, bold: style.bold || false };
                    if (style.background) cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: colors[style.background] || style.background } };
                }
            });

            // دمج الخلايا إذا لزم الأمر
            if (rowData.mergeFrom && rowData.mergeTo) {
                ws.mergeCells(`${getColumnLetter(rowData.mergeFrom)}${currentRow}:${getColumnLetter(rowData.mergeTo)}${currentRow}`);
            }

            isAlternate = !isAlternate;
            currentRow++;
        });

        ws.addRow([]); currentRow++;

        // ===== صفوف الملخص =====
        if (config.summaryRows && config.summaryRows.length > 0) {
            config.summaryRows.forEach((summaryRow) => {
                const row = ws.addRow(summaryRow.values);
                if (summaryRow.mergeFrom && summaryRow.mergeTo) {
                    ws.mergeCells(`${getColumnLetter(summaryRow.mergeFrom)}${currentRow}:${getColumnLetter(summaryRow.mergeTo)}${currentRow}`);
                }
                
                row.height = summaryRow.height || 26;
                row.eachCell((cell) => {
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: colors[summaryRow.background] || summaryRow.background } };
                    cell.font = { bold: true, color: { argb: colors[summaryRow.color] || summaryRow.color }, size: summaryRow.fontSize || 11 };
                    cell.border = {
                        top: { style: 'thin', color: { argb: colors[summaryRow.borderColor] || summaryRow.borderColor } },
                        bottom: { style: 'thin', color: { argb: colors[summaryRow.borderColor] || summaryRow.borderColor } },
                        left: { style: 'thin', color: { argb: colors[summaryRow.borderColor] || summaryRow.borderColor } },
                        right: { style: 'thin', color: { argb: colors[summaryRow.borderColor] || summaryRow.borderColor } }
                    };
                    cell.alignment = { horizontal: 'center', vertical: 'middle' };
                });
                
                // تنسيق خاص لخلايا محددة
                if (summaryRow.cellStyles) {
                    summaryRow.cellStyles.forEach((style, index) => {
                        if (style) {
                            const cell = row.getCell(index + 1);
                            if (style.color) cell.font = { ...cell.font, color: { argb: colors[style.color] || style.color } };
                            if (style.fontSize) cell.font = { ...cell.font, size: style.fontSize };
                            if (style.alignment) cell.alignment = { ...cell.alignment, horizontal: style.alignment };
                        }
                    });
                }
                
                currentRow++;
            });
        }

        // ===== التذييل =====
        ws.addRow([]); currentRow++;
        ws.addRow([]); currentRow++;
        
        ws.mergeCells(`A${currentRow}:${getColumnLetter(numColumns)}${currentRow}`);
        const footerCell = ws.getCell(`A${currentRow}`);
        footerCell.value = config.footerText || `تم إنشاء هذا التقرير آلياً بتاريخ ${new Date().toLocaleString('ar-SA')}`;
        footerCell.font = { italic: true, size: 10, color: { argb: colors.gray } };
        footerCell.alignment = { horizontal: 'center', vertical: 'middle' };

        // =====================================
        // ورقة الملخص (اختياري)
        // =====================================
        if (config.summarySheet) {
            addSummarySheet(workbook, config.summarySheet, colors);
        }

        // =====================================
        // حفظ الملف
        // =====================================
        const buffer = await workbook.xlsx.writeBuffer();
        const today = new Date();
        const fileName = `${config.fileName}_${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.xlsx`;
        
        saveAs(new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), fileName);

        showMessage(config.successMessage || 'تم تصدير التقرير بنجاح!', 'success');

    } catch (error) {
        console.error('Error exporting Excel:', error);
        showMessage(config.errorMessage || 'حدث خطأ أثناء تصدير التقرير', 'error');
    } finally {
        if (loadingBtn) {
            loadingBtn.html(originalText).prop('disabled', false);
        }
    }
}

/**
 * إضافة بطاقات الإحصائيات
 */
function addStatisticsCards(ws, cards, numColumns, startRow, colors) {
    let currentRow = startRow;
    const cardsPerRow = Math.min(cards.length, 2); // عدد البطاقات في الصف الواحد
    const colsPerCard = Math.floor(numColumns / cardsPerRow);
    
    for (let i = 0; i < cards.length; i += cardsPerRow) {
        const rowCards = cards.slice(i, i + cardsPerRow);
        
        // عناوين البطاقات
        rowCards.forEach((card, index) => {
            const startCol = (index * colsPerCard) + 2;
            const endCol = startCol + colsPerCard - 2;
            
            ws.mergeCells(`${getColumnLetter(startCol)}${currentRow}:${getColumnLetter(endCol)}${currentRow}`);
            const titleCell = ws.getCell(`${getColumnLetter(startCol)}${currentRow}`);
            titleCell.value = card.title;
            titleCell.font = { bold: true, size: 11, color: { argb: colors[card.titleColor] || card.titleColor } };
            titleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: colors[card.backgroundColor] || card.backgroundColor } };
            titleCell.alignment = { horizontal: 'center', vertical: 'middle' };
            titleCell.border = {
                top: { style: 'thin', color: { argb: colors[card.borderColor] || card.borderColor } },
                left: { style: 'thin', color: { argb: colors[card.borderColor] || card.borderColor } },
                right: { style: 'thin', color: { argb: colors[card.borderColor] || card.borderColor } }
            };
        });
        ws.getRow(currentRow).height = 25;
        currentRow++;

        // قيم البطاقات
        rowCards.forEach((card, index) => {
            const startCol = (index * colsPerCard) + 2;
            const endCol = startCol + colsPerCard - 2;
            
            ws.mergeCells(`${getColumnLetter(startCol)}${currentRow}:${getColumnLetter(endCol)}${currentRow}`);
            const valueCell = ws.getCell(`${getColumnLetter(startCol)}${currentRow}`);
            valueCell.value = card.value + (card.unit || '');
            valueCell.font = { bold: true, size: 16, color: { argb: colors[card.valueColor] || card.valueColor } };
            valueCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: colors[card.backgroundColor] || card.backgroundColor } };
            valueCell.alignment = { horizontal: 'center', vertical: 'middle' };
            valueCell.border = {
                bottom: { style: 'thin', color: { argb: colors[card.borderColor] || card.borderColor } },
                left: { style: 'thin', color: { argb: colors[card.borderColor] || card.borderColor } },
                right: { style: 'thin', color: { argb: colors[card.borderColor] || card.borderColor } }
            };
        });
        ws.getRow(currentRow).height = 35;
        currentRow++;

        if (i + cardsPerRow < cards.length) {
            ws.addRow([]); currentRow++;
        }
    }
    
    return currentRow;
}

/**
 * إضافة ورقة الملخص
 */
function addSummarySheet(workbook, summaryData, colors) {
    const summarySheet = workbook.addWorksheet(summaryData.sheetName || 'الملخص', {
        properties: { tabColor: { argb: colors.success } },
        views: [{ rightToLeft: true, showGridLines: false }]
    });

    summarySheet.columns = summaryData.columnWidths || [
        { width: 5 },
        { width: 30 },
        { width: 20 },
        { width: 15 },
        { width: 5 }
    ];

    summarySheet.addRow([]);
    
    // العنوان
    summarySheet.mergeCells('B2:D2');
    const sumTitle = summarySheet.getCell('B2');
    sumTitle.value = summaryData.title || 'الملخص الإحصائي';
    sumTitle.font = { name: 'Arial', size: 18, bold: true, color: { argb: colors.primary } };
    sumTitle.alignment = { horizontal: 'center', vertical: 'middle' };
    summarySheet.getRow(2).height = 30;

    summarySheet.addRow([]);
    summarySheet.addRow([]);

    // الجداول
    if (summaryData.tables) {
        summaryData.tables.forEach((table, tableIndex) => {
            if (tableIndex > 0) {
                summarySheet.addRow([]);
                summarySheet.addRow([]);
            }

            // رأس الجدول
            const header = summarySheet.addRow(['', table.title || 'البيان', 'القيمة', 'الوحدة', '']);
            header.eachCell((cell, col) => {
                if (col >= 2 && col <= 4) {
                    cell.font = { bold: true, color: { argb: colors.white } };
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: colors[table.headerColor] || colors.primary } };
                    cell.alignment = { horizontal: 'center', vertical: 'middle' };
                    cell.border = {
                        top: { style: 'thin', color: { argb: colors.border } },
                        bottom: { style: 'thin', color: { argb: colors.border } },
                        left: { style: 'thin', color: { argb: colors.border } },
                        right: { style: 'thin', color: { argb: colors.border } }
                    };
                }
            });
            header.height = 25;

            // بيانات الجدول
            table.data.forEach((item, index) => {
                const row = summarySheet.addRow(['', item.label, item.value, item.unit || '', '']);
                row.height = 24;
                row.eachCell((cell, col) => {
                    if (col >= 2 && col <= 4) {
                        cell.border = {
                            top: { style: 'thin', color: { argb: colors.border } },
                            bottom: { style: 'thin', color: { argb: colors.border } },
                            left: { style: 'thin', color: { argb: colors.border } },
                            right: { style: 'thin', color: { argb: colors.border } }
                        };
                        if (index % 2 === 0) {
                            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: colors.grayBg } };
                        }
                        cell.alignment = { horizontal: 'center', vertical: 'middle' };
                    }
                });
                if (item.color) {
                    row.getCell(3).font = { bold: true, color: { argb: colors[item.color] || item.color } };
                }
            });
        });
    }
}

/**
 * الحصول على حرف العمود من رقمه
 */
function getColumnLetter(columnNumber) {
    let letter = '';
    while (columnNumber > 0) {
        const remainder = (columnNumber - 1) % 26;
        letter = String.fromCharCode(65 + remainder) + letter;
        columnNumber = Math.floor((columnNumber - 1) / 26);
    }
    return letter;
}

/**
 * عرض رسالة للمستخدم
 */
function showMessage(message, type = 'info') {
    if (typeof toastr !== 'undefined') {
        toastr[type](message);
    } else if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: type === 'error' ? 'error' : 'success',
            title: type === 'error' ? 'خطأ' : 'نجاح',
            text: message,
            timer: 3000
        });
    } else {
        alert(message);
    }
}

// تصدير الدوال للاستخدام العام
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { exportToExcelProfessional, formatNumber, EXCEL_COLORS };
}
