<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معاينة الطباعة</title>
    <style>
        body { margin: 0; padding: 0; background: #eee; }
        .page-container {
            width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 20px auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        @media print {
            body { background: white; margin: 0; }
            .page-container { width: 100%; margin: 0; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; padding: 10px; background: #333; color: white;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">🖨️ طباعة</button>
    </div>

    <div class="page-container">
        {!! $html !!}
    </div>
</body>
</html>
