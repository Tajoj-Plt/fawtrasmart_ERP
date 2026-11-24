/**
 * تحديث جدول البيانات
 */
function updateTableBody(data) {
    let tableHtml = '';

    if (!data || data.length === 0) {
        tableHtml = '<tr><td colspan="10" class="text-center text-muted py-4">لا توجد بيانات</td></tr>';
    } else {
        data.forEach((item, index) => {
            const balanceClass = item.balance < 0 ? 'text-danger' : 'text-success';
            const statusBadgeClass = item.status_color ? `style="background-color: ${item.status_color}; color: white; padding: 5px 10px; border-radius: 3px;"` : '';

            tableHtml += `
                <tr>
                    <td>${item.code || '-'}</td>
                    <td>${item.name || '-'}</td>
                    <td>${item.branch || '-'}</td>
                    <td>${item.employee_name || '-'}</td>
                    <td><span ${statusBadgeClass}>${item.status || '-'}</span></td>
                    <td>${item.days_late || 0}</td>
                    <td class="text-end">${formatNumber(item.total_sales || 0)}</td>
                    <td class="text-end">${formatNumber(item.total_payments || 0)}</td>
                    <td class="text-end ${balanceClass}"><strong>${formatNumber(item.balance || 0)}</strong></td>
                </tr>
            `;
        });
    }

    $('#reportTableBody').html(tableHtml);
}

/**
 * تصفية البيانات حسب الفترة
 */
function filterDataByPeriod(data, period) {
    if (!period || period === 'custom' || period === '') {
        return data;
    }

    const periodDays = parseInt(period);
    return data.filter(item => {
        return item.days_late >= periodDays;
    });
}

/**
 * حساب الإجماليات من البيانات المفلترة
 */
function calculateFilteredTotals(data) {
    let totals = {
        'total_clients': 0,
        'total_sales': 0,
        'total_payments': 0,
        'total_balance': 0,
    };

    data.forEach(item => {
        totals.total_balance += item.balance || 0;
        if (item.balance > 0) {
            totals.total_sales += item.balance;
        } else {
            totals.total_payments += Math.abs(item.balance || 0);
        }
    });

    totals.total_clients = data.length;
    return totals;
}

/**
 * إعادة تعيين الفلاتر
 */
function resetFilters() {
    $('#reportForm')[0].reset();
    $('#customer, #branch, #region_group, #sales_manager, #added_by, #status').val(null).trigger('change');
    $('#paginationContainer').html('');
    $('#reportTableBody').html('<tr><td colspan="10" class="text-center text-muted py-4">انقر على البحث لتحميل البيانات</td></tr>');
    $('#totalClients, #totalSales, #totalPayments, #totalBalance').text('0');
}

/**
 * تصدير البيانات إلى Excel
 */
function exportToExcel() {
    const table = document.getElementById('balancesTable');
    const workbook = XLSX.utils.table_to_book(table, { sheet: "أرصدة العملاء" });
    XLSX.writeFile(workbook, 'تقرير_أرصدة_العملاء.xlsx');
}
