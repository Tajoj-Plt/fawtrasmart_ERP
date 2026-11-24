{{--
    نفس أنماط فلترة «أرصدة العملاء» (customer_blances) للتوحيد البصري.

    كل لونٍ هنا صار يقرأ `--app-*` التي تحقنها `App\Support\ThemeSettings`،
    وله بديلٌ فاتحٌ هو اللون الذي كان مثبَّتاً — فما يُضبط في «مظهر النظام»
    (اللون الأساسي والحواف والاستدارة والوضع الليلي) يسري على هذه الأنماط
    بلا سطرٍ يُعدَّل هنا. وكانت قبلَه ألواناً صمّاء: ترويسةُ جدولٍ بتدرّجٍ
    بنفسجيّ لا يمتّ إلى اللون الأساسي بصلة، وأسطحٌ بيضاء داخل صفحةٍ ليلية.

    مشتركة بين ستّ شاشات — فأيّ تعديلٍ هنا يمسّها جميعاً.
--}}
<style>
    /* تنسيق أزرار الفترات الزمنية */
    .period-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .period-tabs .btn {
        flex: 1;
        min-width: 80px;
        border-radius: var(--app-button-radius, 8px);
        padding: 10px 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }

    .period-tabs .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .period-tabs .btn.btn-primary {
        box-shadow: 0 4px 15px var(--app-primary-glow, rgba(115, 103, 240, .4));
    }

    .stat-card {
        border-radius: calc(var(--app-radius, 6px) + 4px);
        margin-bottom: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .filter-card {
        border-radius: calc(var(--app-radius, 6px) + 4px);
        margin-bottom: 20px;
    }

    .chart-container {
        position: relative;
        height: 400px;
    }

    .btn-view-toggle {
        padding: 8px 20px;
        border-radius: var(--app-button-radius, 6px);
    }

    /* تحسين جدول الملخص */
    .summary-table {
        border-radius: calc(var(--app-radius, 6px) + 4px);
        overflow: hidden;
    }

    .summary-table thead th {
        background: var(--app-primary, #7367f0);
        color: var(--app-on-primary, #fff);
        font-weight: 600;
        padding: 12px 15px;
        border: none;
        text-align: center;
    }

    .summary-table tbody tr {
        transition: all 0.2s ease;
    }

    .summary-table tbody tr:hover {
        background-color: var(--app-primary-soft, rgba(115, 103, 240, .08));
        transform: scale(1.01);
    }

    .summary-table tbody td {
        padding: 12px 15px;
        vertical-align: middle;
        color: var(--app-text, #4b4b4b);
        border-bottom: 1px solid var(--app-border, #e9ecef);
    }

    .summary-table .customer-name {
        font-weight: 600;
        color: var(--app-text, #2d3748);
    }

    .summary-table .amount-cell {
        font-weight: 600;
        font-family: 'Roboto', sans-serif;
    }

    .summary-table tfoot tr {
        background: var(--app-primary-soft, #f8f9fa);
        font-weight: 700;
    }

    .summary-table tfoot td {
        padding: 15px;
        font-size: 1.05rem;
        color: var(--app-text, #4b4b4b);
        border-top: 2px solid var(--app-border, #dee2e6);
    }

    /* Badge styling */
    .badge-days-success { background-color: var(--app-success, #28c76f); color: #fff; }
    .badge-days-warning { background-color: var(--app-warning, #ff9f43); color: #fff; }
    .badge-days-danger  { background-color: var(--app-danger, #ea5455);  color: #fff; }

    /* تبويبات إضافية (مثل تجميع المنتج) */
    .group-by-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .group-by-tabs .btn {
        flex: 1;
        min-width: 80px;
        border-radius: var(--app-button-radius, 8px);
        padding: 10px 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }

    .group-by-tabs .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .group-by-tabs .btn.btn-primary {
        box-shadow: 0 4px 15px var(--app-primary-glow, rgba(115, 103, 240, .4));
    }

    @media print {
        .no-print {
            display: none !important;
        }

        .stat-card {
            break-inside: avoid;
        }
    }
</style>
