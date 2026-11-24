@php
    $company_name = \App\Support\OrganizationInfo::name();
    $current_date = \Carbon\Carbon::now()->format('d/m/Y H:i');
@endphp

<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>كشف حساب - {{ $account->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', 'Arial', sans-serif;
            direction: rtl;
            background-color: #fff;
            padding: 20px;
            color: #333;
        }
        
        .page {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            color: #666;
        }
        
        .account-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .account-info-item {
            margin: 5px 0;
        }
        
        .account-info-label {
            font-weight: bold;
            color: #555;
        }
        
        .account-info-value {
            color: #333;
        }
        
        .filters {
            margin-bottom: 20px;
            font-size: 13px;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        thead {
            background-color: #e9ecef;
        }
        
        th {
            border: 1px solid #000;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            font-size: 13px;
        }
        
        td {
            border: 1px solid #000;
            padding: 10px;
            text-align: center;
            font-size: 12px;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tbody tr:hover {
            background-color: #f0f0f0;
        }
        
        .debit {
            color: #dc3545;
            font-weight: bold;
        }
        
        .credit {
            color: #28a745;
            font-weight: bold;
        }
        
        .balance {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        
        tfoot {
            background-color: #fff3cd;
        }
        
        tfoot td {
            font-weight: bold;
            font-size: 13px;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .page {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- الرأس -->
        <div class="header">
            <h1>{{ $company_name }}</h1>
            <p>كشف حساب مفصل</p>
        </div>
        
        <!-- معلومات الحساب -->
        <div class="account-info">
            <div class="account-info-item">
                <span class="account-info-label">الحساب:</span>
                <span class="account-info-value">{{ $account->name }}</span>
            </div>
            <div class="account-info-item">
                <span class="account-info-label">الكود:</span>
                <span class="account-info-value">{{ $account->code }}</span>
            </div>
            <div class="account-info-item">
                <span class="account-info-label">النوع:</span>
                <span class="account-info-value">{{ $account->account_type ?? 'غير محدد' }}</span>
            </div>
        </div>
        
        <!-- الفلاتر المطبقة -->
        @if($fromDate || $toDate)
        <div class="filters">
            <p>
                @if($fromDate)
                    <strong>من:</strong> {{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }}
                @endif
                @if($fromDate && $toDate) | @endif
                @if($toDate)
                    <strong>إلى:</strong> {{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}
                @endif
            </p>
        </div>
        @endif
        
        <!-- جدول العمليات -->
        <table>
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>رقم المرجع</th>
                    <th>الوصف</th>
                    <th>مدين</th>
                    <th>دائن</th>
                    <th>الرصيد بعد</th>
                </tr>
            </thead>
            <tbody>
                {{-- الرصيدُ الافتتاحي يُقال، وإلا قُرئت سطورُ المدّة فروقاً لا أرصدة --}}
                @if (($openingBalance ?? 0) != 0 || $fromDate)
                    <tr style="background-color:#eef4ff;">
                        <td>{{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d/m/Y') : '—' }}</td>
                        <td colspan="2" style="text-align: right; font-weight: bold;">الرصيد الافتتاحي — ما قبل بداية المدّة</td>
                        <td colspan="2" style="text-align:center; color:#666;">—</td>
                        <td class="balance">{{ number_format($openingBalance ?? 0, 2) }}</td>
                    </tr>
                @endif

                @forelse ($operations as $operation)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($operation['date'])->format('d/m/Y') }}</td>
                    <td>
                        @if(isset($operation['reference_number']) && $operation['reference_number'])
                            {{ $operation['reference_number'] }}
                        @else
                            -
                        @endif
                    </td>
                    <td style="text-align: right;">{{ $operation['description'] ?? 'قيد محاسبي' }}</td>
                    <td class="debit">
                        @if($operation['debit'] > 0)
                            {{ number_format($operation['debit'], 2) }}
                        @endif
                    </td>
                    <td class="credit">
                        @if($operation['credit'] > 0)
                            {{ number_format($operation['credit'], 2) }}
                        @endif
                    </td>
                    <td class="balance">{{ number_format($operation['balance'], 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #999;">
                        لا توجد عمليات لهذا الحساب في الفترة المحددة
                    </td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: center;">حركة المدّة</td>
                    <td class="debit">{{ number_format($periodDebit ?? 0, 2) }}</td>
                    <td class="credit">{{ number_format($periodCredit ?? 0, 2) }}</td>
                    <td style="text-align:center; color:#666;">—</td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: center;">
                        الرصيد النهائي@if ($toDate) حتى {{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}@endif
                    </td>
                    <td>{{ number_format($finalBalance, 2) }}</td>
                </tr>
            </tfoot>
        </table>
        
        <!-- التذييل -->
        <div class="footer">
            <p>تم إنشاء هذا الكشف آليًا في {{ $current_date }}</p>
            <p>{{ $company_name }} - جميع الحقوق محفوظة</p>
        </div>
    </div>
</body>
</html>
