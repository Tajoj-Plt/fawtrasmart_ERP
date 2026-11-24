{{--
    أنماط طباعة السندات على ورق A4 — مشتركة بين سندات الصرف والقبض.

    كانت كل شاشة طباعة تحمل نسختها من نحو مئة سطر CSS، تختلف عن أختها في
    الألوان والحدود والمسافات: سند الصرف أحمر وسند القبض أخضر، والعلامة
    المائية في أحدهما دون الآخر. وهي ورقة واحدة تُطبع بالحبر نفسه.

    الأنماط هنا تتبع لغة `asol/pdf`: ترويسة محاطة بإطار، وشبكة بيانات
    بقِيَمٍ على خطٍّ منقّط، وعناوين أقسام بخطٍّ سفلي — بلا ألوان تُهدر حبراً
    ولا تظهر على طابعة أحادية.
--}}
<style>
    @page {
        size: A4;
        margin: 12mm;
    }

    body {
        direction: rtl;
        text-align: right;
        font-family: 'Cairo', 'aealarabiya', Arial, sans-serif;
        margin: 0;
        padding: 0;
        background: #f4f5f7;
        color: #000;
    }

    .paper {
        width: 210mm;
        min-height: 260mm;
        margin: 16px auto;
        padding: 18mm 14mm;
        background: #fff;
        border: 1px solid #ddd;
        box-sizing: border-box;
    }

    .doc-head {
        text-align: center;
        border: 2px solid #000;
        padding: 12px;
        margin-bottom: 24px;
    }

    .doc-head h1 {
        margin: 0 0 4px;
        font-size: 20px;
    }

    .doc-head h2 {
        margin: 0 0 4px;
        font-size: 16px;
        font-weight: 700;
    }

    .doc-head p {
        margin: 2px 0;
        font-size: 12px;
        color: #333;
    }

    .info-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 22px;
    }

    .info-grid td {
        padding: 8px 6px;
        vertical-align: top;
        font-size: 13px;
    }

    .info-label {
        font-weight: 700;
        width: 20%;
        color: #333;
        white-space: nowrap;
    }

    .info-value {
        border-bottom: 1px dotted #666;
        width: 30%;
    }

    .section-title {
        font-size: 15px;
        font-weight: 700;
        margin: 0 0 10px;
        border-bottom: 2px solid #000;
        padding-bottom: 5px;
    }

    .amount-box {
        border: 2px solid #000;
        padding: 10px 12px;
        margin: 0 0 12px;
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        font-weight: 700;
    }

    .amount-box .amount-box__value {
        font-size: 20px;
    }

    .amount-words {
        border: 1px solid #999;
        padding: 8px 12px;
        margin-bottom: 22px;
        font-size: 13px;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 22px;
        font-size: 13px;
    }

    .data-table th,
    .data-table td {
        border: 1px solid #000;
        padding: 7px;
        text-align: center;
    }

    .data-table th {
        background: #f0f0f0;
    }

    .signatures {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        margin-top: 48px;
        font-size: 13px;
    }

    .signatures div {
        flex: 1;
        text-align: center;
        border-top: 1px solid #000;
        padding-top: 6px;
    }

    .doc-foot {
        margin-top: 28px;
        padding-top: 8px;
        border-top: 1px solid #ccc;
        text-align: center;
        font-size: 11px;
        color: #555;
    }

    .print-bar {
        text-align: center;
        margin: 16px;
    }

    .print-bar button {
        padding: 9px 20px;
        margin: 0 4px;
        border: 1px solid #000;
        background: #fff;
        border-radius: 4px;
        cursor: pointer;
        font-family: inherit;
        font-size: 13px;
    }

    @media print {
        body {
            background: #fff;
        }

        .paper {
            width: auto;
            min-height: 0;
            margin: 0;
            padding: 0;
            border: 0;
        }

        .print-bar {
            display: none;
        }
    }
</style>
