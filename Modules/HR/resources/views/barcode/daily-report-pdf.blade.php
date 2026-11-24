{{-- قالبُ PDF لتقرير الحضور اليومي — يُصيَّر عبر dompdf لا في المتصفّح --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير الحضور {{ $date }}</title>
    <style>
        /* dompdf لا يقرأ خطوط النظام — DejaVu مضمّنٌ فيه ويدعم العربية */
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; direction: rtl; color: var(--app-text, #2f3349); }
        .header { text-align: center; border-bottom: 2px solid var(--app-text, #2f3349); padding-bottom: 8px; margin-bottom: 14px; }
        h1 { font-size: 15px; margin: 0; font-weight: bold; }
        .meta { color: var(--app-muted, #6e6b7b); font-size: 10px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d8d6de; padding: 5px 6px; text-align: center; }
        th { background: #f3f2f7; font-weight: bold; }
        tr:nth-child(even) td { background: #fafafc; }
        .empty { text-align: center; color: var(--app-muted, #6e6b7b); padding: 24px; }
    </style>
</head>
<body>
    <div class="header">
        @include('stock::components.print-org-head')
        <h1>تقرير الحضور اليومي</h1>
        <div class="meta">
            التاريخ: {{ $date }} — عدد السجلات: {{ count($rows) }}
        </div>
    </div>

    @if(count($rows) > 0)
        <table>
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>القسم</th>
                    <th>التاريخ</th>
                    <th>الحضور</th>
                    <th>الانصراف</th>
                    <th>ساعات العمل</th>
                    <th>الحالة</th>
                    <th>الوسيلة</th>
                    <th>ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">لا توجد سجلات حضور في هذا التاريخ</div>
    @endif
</body>
</html>
