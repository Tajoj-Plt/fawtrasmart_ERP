@extends('account::master')

@section('title')
    كشف حساب - {{ $account->name }}
@stop

@section('content')
<div class="content-body">
    <div class="card">
        <div class="card-body">
            <!-- رأس الكشف -->
            <div class="statement-header mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                    <div>
                        <h4 class="mb-2">{{ $account->name }}</h4>
                        <p class="text-muted mb-1">كود الحساب: <strong>{{ $account->code }}</strong></p>
                        <p class="text-muted mb-1">نوع الحساب: <strong>{{ $account->account_type ?? 'N/A' }}</strong></p>
                    </div>
                    <div class="text-left">
                        <button class="btn btn-primary" id="printStatementBtn">
                            <i class="fa fa-print"></i> طباعة
                        </button>
                        <button class="btn btn-primary" id="exportPdfBtn">
                            <i class="fa fa-download"></i> تصدير PDF
                        </button>
                    </div>
                </div>

                <!-- فلاتر التاريخ -->
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            {{--
                                الحقلان يعرضان المدى المطبَّق فعلاً لا ما كُتب وحده:
                                الكشفُ يفتح على الفترة المحاسبية الجارية، وحقلٌ فارغ
                                فوق كشفٍ مفلتر يُقرأ كأنّ الكشف يعرض العمر كلَّه.
                            --}}
                            <div class="col-md-4">
                                <label>من التاريخ</label>
                                <input type="date" id="fromDate" class="form-control"
                                    value="{{ request('from_date') }}">
                            </div>
                            <div class="col-md-4">
                                <label>إلى التاريخ</label>
                                <input type="date" id="toDate" class="form-control"
                                    value="{{ request('to_date') }}">
                            </div>
                            <div class="col-md-4">
                                <label>&nbsp;</label>
                                <button class="btn btn-primary form-control" id="applyFilterBtn">
                                    <i class="fa fa-filter"></i> تطبيق الفلتر
                                </button>
                            </div>
                        </div>
                    </div>

                    @if (! empty($period['is_default']) && ! empty($period['label']))
                        <div class="mt-2 text-muted">
                            <i class="feather icon-info"></i>
                            الكشف مقروءٌ على الفترة المحاسبية الجارية: <strong>{{ $period['label'] }}</strong> —
                            غيِّر التاريخين لمدًى آخر.
                        </div>
                    @endif
                </div>
            </div>

            <!-- جدول الكشف -->
            <div class="table-responsive" id="printSection">
                <table class="table table-striped table-hover" style="margin-bottom: 0;">
                    <thead style="background-color: #e9ecef;">
                        <tr>
                            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">التاريخ</th>
                            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">رقم المرجع</th>
                            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">الوصف</th>
                            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">مدين</th>
                            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">دائن</th>
                            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">الرصيد بعد</th>

                        </tr>
                    </thead>
                    <tbody>
                        {{--
                            **الرصيد الافتتاحي سطرٌ في الكشف لا رقمٌ مطويّ.**

                            الرصيدُ الجاري كان يبدأ من صفرٍ مهما كان الفلتر، فسطورُ
                            كشفِ مدّةٍ تقرأ فروقاً لا أرصدة. وصار يبدأ ممّا قبل
                            المدّة، والسطرُ الأوّل يقول من أين بدأ.
                        --}}
                        @if ($openingBalance != 0 || request('from_date'))
                            <tr style="background-color:#eef4ff;">
                                <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 12px;">
                                    {{ request('from_date') ? \Carbon\Carbon::parse(request('from_date'))->format('d/m/Y') : '—' }}
                                </td>
                                <td colspan="2" style="border: 1px solid #000; padding: 8px; text-align: right; font-size: 12px; font-weight: bold;">
                                    الرصيد الافتتاحي — ما قبل بداية المدّة
                                </td>
                                <td colspan="2" style="border: 1px solid #000; padding: 8px; text-align: center; color:#666; font-size: 12px;">—</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; font-size: 12px;">
                                    @money($openingBalance)
                                </td>
                            </tr>
                        @endif

                        @forelse ($operationsPaginator as $operation)
                        <tr>
                            <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 12px;">
                                {{ \Carbon\Carbon::parse($operation['date'])->format('d/m/Y') }}
                            </td>
                            <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 12px;">
                                @if($operation['reference_number'])
                                    <span style="background-color: #f0f0f0; padding: 3px 8px; border-radius: 3px;">
                                        {{ $operation['reference_number'] }}
                                    </span>
                                @else
                                    <span style="color: #ccc;">-</span>
                                @endif
                            </td>
                            <td style="border: 1px solid #000; padding: 8px; text-align: right; font-size: 12px;">
                                {{ $operation['description'] ?? 'قيد محاسبي' }}
                            </td>
                            <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 12px; color: #dc3545; font-weight: bold;">
                                @if($operation['debit'] > 0)
                                    {{ number_format($operation['debit'], 2) }}
                                @endif
                            </td>
                            <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 12px; color: #28a745; font-weight: bold;">
                                @if($operation['credit'] > 0)
                                    {{ number_format($operation['credit'], 2) }}
                                @endif
                            </td>
                            <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; font-size: 12px;">
                                @money($operation['balance'])
                            </td>

                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="border: 1px solid #000; padding: 15px; text-align: center; color: #999;">
                                لا توجد عمليات لهذا الحساب
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background-color:#f1f3f5;">
                            <td colspan="3" style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; font-size: 13px;">
                                حركة المدّة
                            </td>
                            <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; font-size: 13px; color:#dc3545;">
                                @money($periodDebit)
                            </td>
                            <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; font-size: 13px; color:#28a745;">
                                @money($periodCredit)
                            </td>
                            <td style="border: 1px solid #000; padding: 10px; text-align: center; color:#666; font-size: 12px;">—</td>
                        </tr>
                        <tr style="background-color: #fff3cd;">
                            <td colspan="5" style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; font-size: 14px;">
                                {{--
                                    الرصيدُ النهائي رصيدُ **آخر المدّة المطلوبة**، فيُقال
                                    مداه: قارئٌ يرى رقماً بلا تاريخٍ يحسبه رصيدَ اليوم.
                                --}}
                                الرصيد النهائي
                                @if (request('to_date'))
                                    <span style="font-weight:normal; font-size:12px;">
                                        حتى {{ \Carbon\Carbon::parse(request('to_date'))->format('d/m/Y') }}
                                    </span>
                                @endif
                            </td>
                            <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; font-size: 14px;">
                                @money($finalBalance)
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- الترقيم -->
            @if(!request('all'))
            <div class="mt-3">
                {{ $operationsPaginator->links() }}
            </div>
            @endif

            <!-- تذييل الكشف -->
            <div style="margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px;">
                <p style="margin: 5px 0;">تم إنشاء هذا الكشف آليًا في {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</p>
                <p style="margin: 5px 0;">{{ $companyName ?? 'اسم الشركة' }} - جميع الحقوق محفوظة</p>
            </div>
        </div>
    </div>
</div>

<!-- تفاصيل القيد المحاسبي Modal -->
<div class="modal fade" id="entryDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل القيد المحاسبي</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="entryDetailsContent">
                <!-- سيتم تحميل المحتوى عبر AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
$(document).ready(function() {
    const accountId = {{ $account->id }};

    // تطبيق الفلتر
    $('#applyFilterBtn').on('click', function() {
        const fromDate = $('#fromDate').val();
        const toDate = $('#toDate').val();
        let url = window.location.pathname;

        if (fromDate || toDate) {
            url += '?';
            if (fromDate) url += 'from_date=' + fromDate;
            if (fromDate && toDate) url += '&';
            if (toDate) url += 'to_date=' + toDate;
        }

        window.location.href = url;
    });

    // عرض تفاصيل القيد
    $(document).on('click', '.view-entry-btn', function() {
        const entryId = $(this).data('entry-id');

        $.ajax({
            url: `/Accounts/accounts_chart/journal-entry/${entryId}/details`,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>التاريخ:</strong> ${moment(response.data.date).format('DD/MM/YYYY')}</p>
                                <p><strong>الرقم المرجعي:</strong> ${response.data.reference_number || 'N/A'}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>الحالة:</strong> ${response.data.status ? 'معتمد' : 'قيد المراجعة'}</p>
                                <p><strong>الوصف:</strong> ${response.data.description}</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>الحساب</th>
                                        <th>الوصف</th>
                                        <th>مدين</th>
                                        <th>دائن</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                    response.data.details.forEach(detail => {
                        html += `
                            <tr>
                                <td>${detail.account_name} (${detail.account_code})</td>
                                <td>${detail.description || 'N/A'}</td>
                                <td>${detail.debit > 0 ? detail.debit.toFixed(2) : ''}</td>
                                <td>${detail.credit > 0 ? detail.credit.toFixed(2) : ''}</td>
                            </tr>
                        `;
                    });

                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;

                    $('#entryDetailsContent').html(html);
                    $('#entryDetailsModal').modal('show');
                }
            },
            error: function() {
                alert('فشل في جلب التفاصيل');
            }
        });
    });

    // طباعة الكشف كامل بدون باجنيشن
    $('#printStatementBtn').on('click', function() {
        const fromDate = $('#fromDate').val();
        const toDate = $('#toDate').val();
        
        let printUrl = window.location.pathname + '?all=1';
        if (fromDate) printUrl += '&from_date=' + fromDate;
        if (toDate) printUrl += '&to_date=' + toDate;

        const printWindow = window.open(printUrl, '_blank');
        
        printWindow.onload = function() {
            // إضافة ستايل للطباعة لإخفاء العناصر غير الضرورية
            const style = printWindow.document.createElement('style');
            style.innerHTML = `
                @media print {
                    .btn, .statement-header .card, .mt-3, .modal, .main-menu, .header-navbar, .footer, .breadcrumb-wrapper, .content-header {
                        display: none !important;
                    }
                    .content-body, .card, .card-body {
                        margin: 0 !important;
                        padding: 0 !important;
                        box-shadow: none !important;
                    }
                    body {
                        padding: 20px;
                        background: white !important;
                    }
                    table {
                        width: 100% !important;
                        border-collapse: collapse !important;
                    }
                    th, td {
                        border: 1px solid #000 !important;
                    }
                }
            `;
            printWindow.document.head.appendChild(style);
            
            setTimeout(() => {
                printWindow.print();
                // printWindow.close(); // اختيارياً يمكن تركه مفتوحاً للمراجعة
            }, 1000);
        };
    });

    // تصدير PDF
    $('#exportPdfBtn').on('click', function() {
        const fromDate = $('#fromDate').val();
        const toDate = $('#toDate').val();

        let url = `/Accounts/accounts_chart/account-statement/${accountId}/export-pdf`;
        if (fromDate) url += '?from_date=' + fromDate;
        if (toDate) url += (fromDate ? '&' : '?') + 'to_date=' + toDate;

        window.location.href = url;
    });
});
</script>
@endsection
