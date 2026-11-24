{{--
    أنماط الطباعة الحرارية (80 مم) — مشتركة بين سندات الصرف والقبض.

    كان كل سند يحمل نسخته: نفس القياسات ونفس الخطوط المتقطّعة، ويختلفان في
    اللون وحده — وطابعة الإيصالات الحرارية لا تطبع لوناً أصلاً، فكان الفارق
    كلّه بلا أثر على الورق.
--}}
<style>
    @page {
        size: 80mm auto;
        margin: 0;
    }

    body {
        direction: rtl;
        text-align: right;
        font-family: 'Cairo', Arial, sans-serif;
        width: 80mm;
        margin: 0 auto;
        padding: 0;
        background: #fff;
        color: #000;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .slip {
        padding: 6px 8px;
    }

    .slip-head {
        text-align: center;
        padding-bottom: 8px;
        margin-bottom: 8px;
        border-bottom: 2px dashed #000;
    }

    .slip-head h1 {
        margin: 0 0 2px;
        font-size: 16px;
    }

    .slip-head h2 {
        margin: 2px 0;
        font-size: 18px;
        font-weight: 700;
    }

    .slip-head p {
        margin: 2px 0;
        font-size: 11px;
    }

    .slip-rows {
        margin: 8px 0;
        font-size: 13px;
    }

    .slip-row {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        padding: 4px 0;
        border-bottom: 1px dotted #999;
    }

    .slip-row__label {
        font-weight: 700;
        white-space: nowrap;
    }

    .slip-row__value {
        text-align: left;
        word-break: break-word;
    }

    .slip-amount {
        border: 2px solid #000;
        padding: 6px 8px;
        margin: 10px 0;
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        font-weight: 700;
    }

    .slip-amount__value {
        font-size: 17px;
    }

    .slip-words {
        border: 1px solid #000;
        padding: 6px 8px;
        margin-bottom: 10px;
        font-size: 12px;
    }

    .slip-signatures {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        margin-top: 18px;
        padding-top: 8px;
        border-top: 2px dashed #000;
        font-size: 12px;
    }

    .slip-barcode {
        text-align: center;
        margin: 12px 0 6px;
        font-family: 'Libre Barcode 39', monospace;
        font-size: 22px;
        letter-spacing: 2px;
    }

    .slip-foot {
        text-align: center;
        font-size: 11px;
        margin-bottom: 8px;
    }

    .print-bar {
        text-align: center;
        margin: 14px 0;
    }

    .print-bar button {
        padding: 8px 16px;
        margin: 0 3px;
        border: 1px solid #000;
        background: #fff;
        border-radius: 4px;
        cursor: pointer;
        font-family: inherit;
        font-size: 12px;
    }

    @media print {
        .print-bar {
            display: none;
        }
    }
</style>
