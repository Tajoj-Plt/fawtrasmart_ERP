{{-- ملصقُ «رمز المكتب» المطبوع — يُعلَّق في المكتب، والموظف يمسحه بجواله.
     يُقبل فقط وموقعُ الجوال داخل مكان الجهاز، و«رمز مطبوع جديد» يُبطله. --}}
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>رمز المكتب — {{ $kiosk->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Tahoma, Arial, sans-serif; background: #f3f4f6; color: #111827; }
        .sheet { width: 190mm; min-height: 270mm; margin: 10mm auto; background: #fff; border-radius: 8px;
            padding: 18mm 14mm; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8mm; }
        h1 { margin: 0; font-size: 30px; }
        .place { font-size: 18px; color: #4b5563; }
        .qr svg { width: 120mm; height: 120mm; }
        ol { text-align: right; font-size: 17px; line-height: 2; margin: 0; padding-inline-start: 1.4em; }
        .note { font-size: 13px; color: #6b7280; }
        .actions { text-align: center; margin: 12px; }
        .actions button { font-size: 15px; padding: 8px 22px; border-radius: 6px; border: 0; background: #4f46e5; color: #fff; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { margin: 0; border-radius: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">طباعة</button></div>
    <div class="sheet">
        <h1>سجّل حضورك بجوالك</h1>
        <div class="place">{{ $kiosk->name }}{{ $kiosk->geofence ? ' — '.$kiosk->geofence->name : '' }}</div>
        <div class="qr">{!! $svg !!}</div>
        <ol>
            <li>افتح «سجّل حضوري» في النظام من جوالك، وفعّل الموقع.</li>
            <li>اضغط «امسح رمز المكتب» ووجّه الكاميرا إلى هذا الرمز.</li>
            <li>يُسجَّل حضورك، أو انصرافك إن كنت حاضراً.</li>
        </ol>
        <div class="note">يُقبل المسح فقط وجوالك داخل المكتب. تصوير هذا الرمز واستعماله من خارج المكتب يُرفض ويُسجَّل.</div>
    </div>
</body>
</html>
