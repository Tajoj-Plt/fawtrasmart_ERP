@extends('sales::master')

@section('content')
    <style>
        * {
            box-sizing: border-box;
        }

        .collection-report {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 3rem 0;
        }

        .report-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15);
        }

        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .report-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .report-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .report-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
            letter-spacing: 0.5px;
        }

        .report-header p {
            position: relative;
            z-index: 1;
            margin: 1rem 0 0 0;
            font-size: 0.95rem;
            opacity: 0.95;
            font-weight: 500;
        }

        .search-section {
            background: linear-gradient(135deg, #f8f9fb 0%, #e9ecef 100%);
            padding: 2rem;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            margin-bottom: 2.5rem;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.7rem;
            font-size: 0.95rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.9rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            background: white;
            width: 100%;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
            background: #fafbff;
        }

        .btn-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
            margin-left: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.75rem;
            cursor: pointer;
            display: inline-block;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-search:active {
            transform: translateY(-1px);
        }

        .btn-search.btn-cancel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-search.btn-cancel:hover {
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            font-weight: 600;
            text-align: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            animation: slideInDown 0.5s ease;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .table {
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        .table thead th {

            color: black;
            border: none;
            padding: 1.3rem;
            font-weight: 700;
            text-align: center;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        .table tbody tr:hover {
            background-color: #f8f9ff;
            transform: scale(1.005);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }

        .table tbody td {
            padding: 1.2rem;
            text-align: center;
            vertical-align: middle;
            font-weight: 500;
        }

        .table tbody tr:nth-child(even) {
            background-color: #fafbff;
        }

        .employee-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1rem;
        }

        .amount {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 1.05rem;
        }

        .amount.positive {
            color: #10b981;
        }

        .amount.negative {
            color: #ef4444;
        }

        .amount.total {
            color: #667eea;
        }

        .total-row {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            font-weight: 800;
            border-top: 3px solid #667eea;
        }

        .total-row td {
            padding: 1.5rem;
            color: white;
            font-size: 1.1rem;
        }

        .total-row .amount {
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
            font-size: 1.1rem;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border: none;
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
            font-weight: 600;
        }

        .alert-warning strong {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .alert-warning ul {
            margin: 1rem 0 0 1.5rem;
            padding: 0;
        }

        .alert-warning li {
            margin-bottom: 0.5rem;
            list-style: none;
        }

        .alert-warning li::before {
            content: '✓ ';
            margin-right: 0.5rem;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .collection-report {
                padding: 1.5rem 0;
            }

            .report-card {
                margin: 0 15px;
            }

            .report-header {
                padding: 1.8rem;
            }

            .report-title {
                font-size: 1.6rem;
            }

            .search-section {
                padding: 1.5rem;
            }

            .btn-search {
                width: 100%;
                margin-top: 1rem;
                margin-left: 0;
            }

            .form-group {
                margin-bottom: 1rem;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
            }

            .table thead th {
                padding: 1rem;
                font-size: 0.85rem;
            }

            .table tbody td {
                padding: 0.9rem;
                font-size: 0.9rem;
            }

            .info-alert {
                font-size: 0.9rem;
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            .report-header {
                padding: 1.5rem;
            }

            .report-title {
                font-size: 1.3rem;
            }

            .btn-search {
                padding: 0.8rem 1.5rem;
                font-size: 0.8rem;
            }
        }
    </style>

    <div class="collection-report">
        <div class="container">
            <div class="card report-card">
                <div class="card-body" style="padding: 1.5rem;">

                    <div class="report-header">
                        <h4 class="report-title">تقرير التحصيل اليومي/الفترات</h4>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                            <i class="fas fa-info-circle"></i> التحصيلات محسوبة من القيود المحاسبية (الخزينة والبنوك)
                        </p>
                    </div>

                    <div class="search-section">
                        <form method="GET">
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="fas fa-calendar me-1"></i>بحث بيوم محدد
                                    </label>
                                    <input type="date" name="date" value="{{ $selectedDate }}" class="form-control">
                                </div>
                                <div class="col-lg-3 col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="fas fa-calendar-check me-1"></i>من تاريخ
                                    </label>
                                    <input type="date" name="from_date" value="{{ $fromDate }}" class="form-control">
                                </div>
                                <div class="col-lg-3 col-md-3 form-group">
                                    <label class="form-label">
                                        <i class="fas fa-calendar-check me-1"></i>إلى تاريخ
                                    </label>
                                    <input type="date" name="to_date" value="{{ $toDate }}" class="form-control">
                                </div>
                                <div class="col-lg-3 col-md-3 form-group d-flex flex-column justify-content-end gap-2">
                                    <button type="submit" class="btn btn-search">
                                        <i class="fas fa-search me-2"></i>عرض النتائج
                                    </button>
                                    <a href="{{ route('daily_closing_entry') }}" class="btn btn-search btn-cancel">
                                        <i class="fas fa-redo-alt me-2"></i>إعادة تعيين
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    @if ($isRangeSearch ?? false)
                        <div class="alert info-alert">
                            <i class="fas fa-calendar-alt me-2"></i>
                            يتم عرض النتائج للفترة من {{ $fromDate }} إلى {{ $toDate }}
                        </div>
                    @else
                        <div class="alert info-alert">
                            <i class="fas fa-calendar-day me-2"></i>
                            يتم عرض النتائج ليوم {{ $selectedDate }}
                        </div>
                    @endif

                    {{-- التحقق من وجود بيانات --}}
                    @if(empty($cards) || count($cards) === 0)
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>لا توجد بيانات</strong> - لم يتم العثور على بيانات تحصيل للفترة المحددة. يرجى التحقق من:
                            <ul class="mt-2 mb-0">
                                <li>التاريخ المختار</li>
                                <li>وجود قيود محاسبية للموظفين في الفترة المحددة</li>
                                <li>تأكد من أن القيود معتمدة (status = 1)</li>
                            </ul>
                        </div>
                    @else
                        <div class="table-container">
                            <table class="table" id="collectionTable">
                                <thead>
                                    <tr>
                                        <th>اسم الموظف</th>
                                        <th>التحصيلات (من القيود)</th>
                                        <th>الإجمالي</th>
                                        <th>سندات الصرف</th>
                                        <th>المحصل النهائي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totalCollections = 0;
                                        $totalExpenses = 0;
                                        $finalTotal = 0;
                                    @endphp

                                    @foreach ($cards as $card)
                                        @php
                                            $final = $card['collections'] - $card['expenses'];

                                            $totalCollections += $card['collections'];
                                            $totalExpenses += $card['expenses'];
                                            $finalTotal += $final;
                                        @endphp
                                        <tr>
                                            <td class="employee-name">{{ $card['name'] }}</td>
                                            <td class="amount positive">{{ number_format($card['collections'], 2) }}</td>
                                            <td class="amount positive">{{ number_format($card['collections'], 2) }}</td>
                                            <td class="amount negative">{{ number_format($card['expenses'], 2) }}</td>
                                            <td class="amount total">{{ number_format($final, 2) }}</td>
                                        </tr>
                                    @endforeach

                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <th>الإجمالي</th>
                                        <th class="amount positive">{{ number_format($totalCollections, 2) }}</th>
                                        <th class="amount positive">{{ number_format($totalCollections, 2) }}</th>
                                        <th class="amount negative">{{ number_format($totalExpenses, 2) }}</th>
                                        <th class="amount total">{{ number_format($finalTotal, 2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            const table = $('#collectionTable');
            if (table.length > 0) {
                table.DataTable({
                    language: {
                        url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Arabic.json"
                    },
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'asc']],
                    columnDefs: [{
                        targets: [1, 2, 3],
                        className: 'text-center'
                    }],
                    footerCallback: function(row, data, start, end, display) {
                        // يمكن إضافة منطق إضافي للتذييل هنا إذا لزم الأمر
                    }
                });
            }
        });
    </script>
@endpush
