@extends('onlinestore::master')

@section('title', 'سجلات البريد الإلكتروني')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.email_logs.view'))
                <a href="{{ route('email_logs.statistics') }}" class="btn btn-outline-info">
                    <i class="feather icon-bar-chart-2"></i> الإحصائيات
                </a>
            @endif
            @if (hasPermission('online_store.email_logs.cleanup'))
                <button type="button" class="btn btn-outline-danger" data-os-modal="#cleanupModal">
                    <i class="feather icon-trash"></i> تنظيف
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'سجلات البريد الإلكتروني',
            'icon' => 'inbox',
            'sub' => 'سجل جميع رسائل البريد المرسلة من المتجر الإلكتروني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'سجلات البريد' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-mail"></i></span>
                <span class="stk-metric__label">إجمالي الرسائل</span>
                <span class="stk-metric__value">{{ number_format($stats['total']) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">تم الإرسال</span>
                <span class="stk-metric__value">{{ number_format($stats['sent']) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">فشل الإرسال</span>
                <span class="stk-metric__value">{{ number_format($stats['failed']) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">اليوم</span>
                <span class="stk-metric__value">{{ number_format($stats['today']) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('email_logs.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="البريد، الموضوع، رقم السجل…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}" @selected(request('status') == $status->value)>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="template_id">القالب</label>
                                <select name="template_id" id="template_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->template_id }}" @selected(request('template_id') == $template->template_id)>
                                            {{ $template->template_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="date_from">من تاريخ</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="date_to">إلى تاريخ</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('email_logs.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة السجلات</h6>
                <div>
                    @if (hasPermission('online_store.email_logs.delete'))
                        <button type="button" id="bulk-delete-btn" disabled class="btn btn-sm btn-danger mr-1"
                                onclick="$('#bulk-form').submit();">
                            حذف المحدد
                        </button>
                    @endif
                    @if (hasPermission('online_store.email_logs.export'))
                        <button type="button" id="printEmailLogsBtn" class="btn btn-sm btn-success">
                            <i class="fa fa-print"></i> طباعة
                        </button>
                        <button type="button" id="exportEmailLogsExcel" class="btn btn-sm btn-info">
                            <i class="fa fa-file-excel-o"></i> Excel
                        </button>
                    @endif
                    <span class="badge badge-light-primary">{{ $logs->total() }} سجل</span>
                </div>
            </div>

            {{-- نموذج الحذف الجماعي مستقلٌّ وفارغ؛ مربّعات الصفوف تتبعه بسمة form="bulk-form".
                 لا يلفّ الجدول: في كل صفٍّ نموذجان (إعادة إرسال/حذف) ولا نموذجَ داخل نموذج. --}}
            <form id="bulk-form" action="{{ route('email_logs.bulk-delete') }}" method="POST" class="d-none">
                @csrf
            </form>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="emailLogsTable">
                        <thead>
                            <tr>
                                @if (hasPermission('online_store.email_logs.delete'))
                                    <th class="text-center" style="width: 40px;">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="select-all">
                                            <label class="custom-control-label" for="select-all"></label>
                                        </div>
                                    </th>
                                @endif
                                <th class="text-center" style="width: 60px;">#</th>
                                <th>المستلم</th>
                                <th>القالب</th>
                                <th>الموضوع</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center">التاريخ</th>
                                @if (hasPermission(['online_store.email_logs.view', 'online_store.email_logs.resend', 'online_store.email_logs.delete']))
                                    <th class="text-center" style="width: 80px;">الإجراءات</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    @if (hasPermission('online_store.email_logs.delete'))
                                        <td class="text-center">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" form="bulk-form" class="custom-control-input log-checkbox"
                                                       name="log_ids[]" value="{{ $log->log_id }}"
                                                       id="log-{{ $log->log_id }}">
                                                <label class="custom-control-label" for="log-{{ $log->log_id }}"></label>
                                            </div>
                                        </td>
                                    @endif
                                    <td class="text-center stk-num">{{ $log->log_id }}</td>
                                    <td>
                                        <strong>{{ $log->recipient_email }}</strong>
                                        @if ($log->recipient_name)
                                            <br><small class="text-muted">{{ $log->recipient_name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($log->emailTemplate)
                                            @if (hasPermission('online_store.email_templates.view'))
                                                <a href="{{ route('email_templates.show', $log->emailTemplate) }}">
                                                    {{ $log->emailTemplate->template_name }}
                                                </a>
                                            @else
                                                {{ $log->emailTemplate->template_name }}
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ Str::limit($log->subject, 40) }}</td>
                                    <td class="text-center">
                                        <span class="stk-state {{ $log->status->value == 'sent' ? 'stk-state--on' : ($log->status->value == 'failed' ? 'stk-state--off' : 'stk-state--warn') }}">
                                            {{ $log->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="stk-num">{{ $log->created_at->format('Y-m-d') }}</div>
                                        <small class="text-muted">{{ $log->created_at->format('H:i:s') }}</small>
                                    </td>
                                    @if (hasPermission(['online_store.email_logs.view', 'online_store.email_logs.resend', 'online_store.email_logs.delete']))
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="feather icon-more-vertical"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    @if (hasPermission('online_store.email_logs.view'))
                                                        <a class="dropdown-item" href="{{ route('email_logs.show', $log) }}">
                                                            <i class="feather icon-eye"></i> عرض
                                                        </a>
                                                    @endif
                                                    @if ($log->status->value != 'sent' && hasPermission('online_store.email_logs.resend'))
                                                        <form action="{{ route('email_logs.resend', $log) }}" method="POST" class="d-inline w-100">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="feather icon-refresh-cw"></i> إعادة إرسال
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if (hasPermission('online_store.email_logs.delete'))
                                                        <div class="dropdown-divider"></div>
                                                        <form action="{{ route('email_logs.destroy', $log) }}" method="POST" class="delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="feather icon-trash-2"></i> حذف
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="stk-empty">
                                            <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                            <h6 class="stk-empty__title">لا توجد سجلات</h6>
                                            <p class="stk-empty__hint">لم يتم إرسال أي بريد بعد.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            {{-- جدول مخفي بنصوص صافية، لأغراض الطباعة والتصدير فقط (بلا إجراءات) --}}
            <table id="emailLogsExportTable" class="d-none">
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->log_id }}</td>
                            <td>{{ $log->recipient_email }}{{ $log->recipient_name ? ' - '.$log->recipient_name : '' }}</td>
                            <td>{{ $log->template->template_name ?? '-' }}</td>
                            <td>{{ $log->subject }}</td>
                            <td>{{ $log->status->label() }}</td>
                            <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $logs, 'label' => 'سجل'])
        </div>
    </div>

    @include('onlinestore::layouts._modal-helper')

    {{-- نافذة التنظيف --}}
    @if (hasPermission('online_store.email_logs.cleanup'))
        <div class="modal fade" id="cleanupModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">تنظيف السجلات القديمة</h5>
                        <button type="button" class="close" data-os-dismiss>
                            <span>&times;</span>
                        </button>
                    </div>
                    <form action="{{ route('email_logs.cleanup') }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="form-group">
                                <label>حذف السجلات الأقدم من:</label>
                                <select name="days" class="form-control" required>
                                    <option value="30">30 يوم</option>
                                    <option value="60">60 يوم</option>
                                    <option value="90" selected>90 يوم</option>
                                    <option value="180">180 يوم</option>
                                    <option value="365">سنة</option>
                                </select>
                            </div>
                            <div class="alert alert-warning">
                                <i class="feather icon-alert-triangle"></i>
                                لا يمكن التراجع عن هذه العملية!
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-os-dismiss>إلغاء</button>
                            <button type="submit" class="btn btn-danger">تنظيف</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
$(document).ready(function() {
    // طباعة سجلات البريد الإلكتروني
    $('#printEmailLogsBtn').click(function() {
        printEmailLogsReport();
    });

    // تصدير سجلات البريد الإلكتروني إلى Excel
    $('#exportEmailLogsExcel').click(function() {
        exportEmailLogsReport();
    });

    // تهيئة Select2
    if ($.fn.select2) {
        $('.select2').select2({
            dir: 'rtl',
            language: {
                noResults: function() {
                    return 'لا توجد نتائج';
                }
            },
            allowClear: true,
            width: '100%'
        });
    }

    // تحديد الكل
    $('#select-all').on('change', function() {
        $('.log-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkButton();
    });

    // تحديث زر الحذف الجماعي
    $('.log-checkbox').on('change', updateBulkButton);

    function updateBulkButton() {
        const checked = $('.log-checkbox:checked').length;
        $('#bulk-delete-btn').prop('disabled', checked === 0);
        $('#bulk-delete-btn').text(checked > 0 ? `حذف المحدد (${checked})` : 'حذف المحدد');
    }

    // تأكيد الحذف
    $('.delete-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا السجل!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.off('submit').submit();
            }
        });
    });

    // تأكيد الحذف الجماعي
    $('#bulk-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const count = $('.log-checkbox:checked').length;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: `سيتم حذف ${count} سجل!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.off('submit').submit();
            }
        });
    });
});

@php
    $emailLogsFilterTemplateName = null;
    if (request('template_id')) {
        $emailLogsFilterTemplateName = $templates->firstWhere('template_id', request('template_id'))?->template_name;
    }
@endphp

// =====================================
// طباعة سجلات البريد الإلكتروني
// =====================================
function printEmailLogsReport() {
    printTable({
        tableSelector: '#emailLogsExportTable tbody tr',
        title: 'تقرير سجلات البريد الإلكتروني',
        infoCards: [
            { label: 'إجمالي الرسائل', value: '{{ number_format($stats['total']) }}' },
            { label: 'تم الإرسال', value: '{{ number_format($stats['sent']) }}', class: 'text-success' },
            { label: 'فشل الإرسال', value: '{{ number_format($stats['failed']) }}', class: 'text-danger' },
            { label: 'اليوم', value: '{{ number_format($stats['today']) }}' }
        ],
        filterInfo: {
            @if (request('search'))
                'بحث': '{{ request('search') }}',
            @endif
            @if (request('status'))
                'الحالة': '{{ request('status') }}',
            @endif
            @if ($emailLogsFilterTemplateName)
                'القالب': '{{ $emailLogsFilterTemplateName }}',
            @endif
            @if (request('date_from'))
                'من تاريخ': '{{ request('date_from') }}',
            @endif
            @if (request('date_to'))
                'إلى تاريخ': '{{ request('date_to') }}',
            @endif
        },
        tableHeaders: ['#', 'المستلم', 'القالب', 'الموضوع', 'الحالة', 'التاريخ'],
        columnWidths: ['6%', '22%', '16%', '28%', '12%', '16%'],
        descriptionColumn: 4,
        windowWidth: 1300,
        windowHeight: 850
    });
}

// =====================================
// تصدير سجلات البريد الإلكتروني إلى Excel
// =====================================
function exportEmailLogsReport() {
    var tableData = [];

    @foreach ($logs as $log)
        tableData.push({
            values: [
                {{ $log->log_id }},
                @json($log->recipient_email.($log->recipient_name ? ' - '.$log->recipient_name : '')),
                @json($log->template->template_name ?? '-'),
                @json($log->subject),
                @json($log->status->label()),
                @json($log->created_at->format('Y-m-d H:i:s'))
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        });
    @endforeach

    var config = {
        fileName: 'تقرير_سجلات_البريد_الإلكتروني',
        mainTitle: 'تقرير سجلات البريد الإلكتروني',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            @if (request('search'))
                'بحث': '{{ request('search') }}',
            @endif
            @if (request('status'))
                'الحالة': '{{ request('status') }}',
            @endif
            @if ($emailLogsFilterTemplateName)
                'القالب': '{{ $emailLogsFilterTemplateName }}',
            @endif
            @if (request('date_from'))
                'من تاريخ': '{{ request('date_from') }}',
            @endif
            @if (request('date_to'))
                'إلى تاريخ': '{{ request('date_to') }}',
            @endif
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        },

        statisticsCards: [
            {
                title: 'إجمالي الرسائل',
                value: '{{ number_format($stats['total']) }}',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'تم الإرسال',
                value: '{{ number_format($stats['sent']) }}',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'فشل الإرسال',
                value: '{{ number_format($stats['failed']) }}',
                titleColor: 'danger',
                valueColor: 'danger',
                backgroundColor: 'dangerLight',
                borderColor: 'danger'
            },
            {
                title: 'اليوم',
                value: '{{ number_format($stats['today']) }}',
                titleColor: 'warning',
                valueColor: 'warning',
                backgroundColor: 'warningLight',
                borderColor: 'warning'
            }
        ],

        tableTitle: 'قائمة سجلات البريد',
        tableHeaders: ['#', 'المستلم', 'القالب', 'الموضوع', 'الحالة', 'التاريخ'],
        columnWidths: [8, 28, 20, 35, 14, 18],

        tableData: tableData,

        buttonSelector: '#exportEmailLogsExcel',
        successMessage: 'تم تصدير تقرير سجلات البريد بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
