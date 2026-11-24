@extends('onlinestore::master')

@section('title', 'قوالب البريد الإلكتروني')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.email_templates.export'))
                <button type="button" id="printEmailTemplatesBtn" class="btn btn-outline-success">
                    <i class="fa fa-print"></i> طباعة
                </button>
                <button type="button" id="exportEmailTemplatesExcel" class="btn btn-outline-info">
                    <i class="fa fa-file-excel-o"></i> Excel
                </button>
            @endif
            @if (hasPermission('online_store.email_templates.create'))
                <a href="{{ route('email_templates.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة قالب جديد
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'قوالب البريد الإلكتروني',
            'icon' => 'mail',
            'sub' => 'قوالب الرسائل الآلية المرسلة من المتجر الإلكتروني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'قوالب البريد' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-mail"></i></span>
                <span class="stk-metric__label">إجمالي القوالب</span>
                <span class="stk-metric__value">{{ $templates->total() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">قوالب مفعلة</span>
                <span class="stk-metric__value">{{ $templates->where('is_active', true)->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-globe"></i></span>
                <span class="stk-metric__label">قوالب عربية</span>
                <span class="stk-metric__value">{{ $templates->where('language', 'ar')->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-send"></i></span>
                <span class="stk-metric__label">رسائل مرسلة</span>
                <span class="stk-metric__value">{{ $templates->sum(fn($t) => $t->emailLogs()->count()) ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('email_templates.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="اسم القالب، الكود، الموضوع…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="category">التصنيف</label>
                                <select name="category" id="category" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}" @selected(request('category') == $category->value)>
                                            {{ $category->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('status') == '1')>مفعل</option>
                                    <option value="0" @selected(request('status') == '0')>معطل</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="language">اللغة</label>
                                <select name="language" id="language" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="ar" @selected(request('language') == 'ar')>العربية</option>
                                    <option value="en" @selected(request('language') == 'en')>English</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('email_templates.index') }}" class="btn btn-outline-secondary">
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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة القوالب</h6>
                <span class="badge badge-light-primary">{{ $templates->total() }} قالب</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="emailTemplatesTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>اسم القالب</th>
                            <th>الكود</th>
                            <th>التصنيف</th>
                            <th>الموضوع</th>
                            <th class="text-center">اللغة</th>
                            <th class="text-center">الحالة</th>
                            @if (hasPermission(['online_store.email_templates.view', 'online_store.email_templates.edit', 'online_store.email_templates.create', 'online_store.email_templates.delete']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $template)
                            <tr>
                                <td class="text-center stk-num">{{ $template->template_id }}</td>
                                <td>
                                    @if (hasPermission('online_store.email_templates.view'))
                                        <a href="{{ route('email_templates.show', $template) }}">
                                            <strong>{{ $template->template_name }}</strong>
                                        </a>
                                    @else
                                        <strong>{{ $template->template_name }}</strong>
                                    @endif
                                </td>
                                <td><code>{{ $template->template_code }}</code></td>
                                <td>
                                    <span class="badge badge-light-primary">
                                        {{ $template->template_category?->label() ?? $template->template_category }}
                                    </span>
                                </td>
                                <td>{{ Str::limit($template->subject, 40) }}</td>
                                <td class="text-center">
                                    @if ($template->language == 'ar')
                                        <span class="badge badge-light-info">العربية</span>
                                    @else
                                        <span class="badge badge-light-secondary">English</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.email_templates.edit'))
                                        <span class="stk-state {{ $template->is_active ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $template->template_id }}" data-url="{{ route('email_templates.toggle-status', $template) }}">
                                            {{ $template->is_active ? 'مفعل' : 'معطل' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $template->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $template->is_active ? 'مفعل' : 'معطل' }}
                                        </span>
                                    @endif
                                </td>
                                @if (hasPermission(['online_store.email_templates.view', 'online_store.email_templates.edit', 'online_store.email_templates.create', 'online_store.email_templates.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('online_store.email_templates.view'))
                                                    <a class="dropdown-item" href="{{ route('email_templates.show', $template) }}">
                                                        <i class="feather icon-eye"></i> عرض
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('email_templates.preview', $template) }}" target="_blank">
                                                        <i class="feather icon-monitor"></i> معاينة
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.email_templates.edit'))
                                                    <a class="dropdown-item" href="{{ route('email_templates.edit', $template) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.email_templates.create'))
                                                    <form action="{{ route('email_templates.duplicate', $template) }}" method="POST" class="d-inline w-100">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="feather icon-copy"></i> نسخ
                                                        </button>
                                                    </form>
                                                @endif
                                                @if (hasPermission('online_store.email_templates.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('email_templates.destroy', $template) }}" method="POST" class="delete-form">
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
                                        <span class="stk-empty__icon"><i class="feather icon-mail"></i></span>
                                        <h6 class="stk-empty__title">لا توجد قوالب</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول قالب بريد إلكتروني.</p>
                                        @if (hasPermission('online_store.email_templates.create'))
                                            <a href="{{ route('email_templates.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة أول قالب
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- جدول مخفي بنصوص صافية، لأغراض الطباعة والتصدير فقط (بلا إجراءات) --}}
            <table id="emailTemplatesExportTable" class="d-none">
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td>{{ $template->template_id }}</td>
                            <td>{{ $template->template_name }}</td>
                            <td>{{ $template->template_code }}</td>
                            <td>{{ $template->template_category?->label() ?? $template->template_category }}</td>
                            <td>{{ $template->subject }}</td>
                            <td>{{ $template->language == 'ar' ? 'العربية' : 'English' }}</td>
                            <td>{{ $template->is_active ? 'مفعل' : 'معطل' }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $templates, 'label' => 'قالب'])
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
$(document).ready(function() {
    // طباعة قوالب البريد الإلكتروني
    $('#printEmailTemplatesBtn').click(function() {
        printEmailTemplatesReport();
    });

    // تصدير قوالب البريد الإلكتروني إلى Excel
    $('#exportEmailTemplatesExcel').click(function() {
        exportEmailTemplatesReport();
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

    // تبديل الحالة
    $('.toggle-status').click(function() {
        var btn = $(this);
        $.ajax({
            url: btn.data('url'),
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    btn.toggleClass('stk-state--on stk-state--off');
                    btn.text(response.is_active ? 'مفعل' : 'معطل');
                    toastr.success(response.message);
                } else {
                    toastr.error('حدث خطأ أثناء تحديث الحالة');
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء تحديث الحالة');
            }
        });
    });

    // تأكيد الحذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا القالب نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});

@php
    $emailTemplatesFilterCategoryLabel = null;
    if (request('category')) {
        $matchedCategory = collect($categories)->first(fn ($c) => $c->value == request('category'));
        $emailTemplatesFilterCategoryLabel = $matchedCategory?->label();
    }
@endphp

// =====================================
// طباعة قوالب البريد الإلكتروني
// =====================================
function printEmailTemplatesReport() {
    printTable({
        tableSelector: '#emailTemplatesExportTable tbody tr',
        title: 'تقرير قوالب البريد الإلكتروني',
        infoCards: [
            { label: 'إجمالي القوالب', value: {{ $templates->total() ?? 0 }} },
            { label: 'قوالب مفعلة', value: {{ $templates->where('is_active', true)->count() ?? 0 }}, class: 'text-success' },
            { label: 'قوالب عربية', value: {{ $templates->where('language', 'ar')->count() ?? 0 }} },
            { label: 'رسائل مرسلة', value: {{ $templates->sum(fn($t) => $t->emailLogs()->count()) ?? 0 }} }
        ],
        filterInfo: {
            @if (request('search'))
                'بحث': '{{ request('search') }}',
            @endif
            @if ($emailTemplatesFilterCategoryLabel)
                'التصنيف': '{{ $emailTemplatesFilterCategoryLabel }}',
            @endif
            @if (request('status') !== null && request('status') !== '')
                'الحالة': '{{ request('status') == '1' ? 'مفعل' : 'معطل' }}',
            @endif
            @if (request('language'))
                'اللغة': '{{ request('language') == 'ar' ? 'العربية' : 'English' }}',
            @endif
        },
        tableHeaders: ['#', 'اسم القالب', 'الكود', 'التصنيف', 'الموضوع', 'اللغة', 'الحالة'],
        columnWidths: ['6%', '20%', '15%', '15%', '25%', '9%', '10%'],
        windowWidth: 1300,
        windowHeight: 850
    });
}

// =====================================
// تصدير قوالب البريد الإلكتروني إلى Excel
// =====================================
function exportEmailTemplatesReport() {
    var tableData = [];

    @foreach ($templates as $template)
        tableData.push({
            values: [
                {{ $template->template_id }},
                @json($template->template_name),
                @json($template->template_code),
                @json($template->template_category?->label() ?? $template->template_category),
                @json($template->subject),
                @json($template->language == 'ar' ? 'العربية' : 'English'),
                @json($template->is_active ? 'مفعل' : 'معطل')
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        });
    @endforeach

    var config = {
        fileName: 'تقرير_قوالب_البريد_الإلكتروني',
        mainTitle: 'تقرير قوالب البريد الإلكتروني',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            @if (request('search'))
                'بحث': '{{ request('search') }}',
            @endif
            @if ($emailTemplatesFilterCategoryLabel)
                'التصنيف': '{{ $emailTemplatesFilterCategoryLabel }}',
            @endif
            @if (request('language'))
                'اللغة': '{{ request('language') == 'ar' ? 'العربية' : 'English' }}',
            @endif
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        },

        statisticsCards: [
            {
                title: 'إجمالي القوالب',
                value: '{{ $templates->total() ?? 0 }}',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'قوالب مفعلة',
                value: '{{ $templates->where('is_active', true)->count() ?? 0 }}',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'قوالب عربية',
                value: '{{ $templates->where('language', 'ar')->count() ?? 0 }}',
                titleColor: 'warning',
                valueColor: 'warning',
                backgroundColor: 'warningLight',
                borderColor: 'warning'
            },
            {
                title: 'رسائل مرسلة',
                value: '{{ $templates->sum(fn($t) => $t->emailLogs()->count()) ?? 0 }}',
                titleColor: 'purple',
                valueColor: 'purple',
                backgroundColor: 'purpleLight',
                borderColor: 'purple'
            }
        ],

        tableTitle: 'قائمة القوالب',
        tableHeaders: ['#', 'اسم القالب', 'الكود', 'التصنيف', 'الموضوع', 'اللغة', 'الحالة'],
        columnWidths: [6, 26, 20, 18, 30, 12, 12],

        tableData: tableData,

        buttonSelector: '#exportEmailTemplatesExcel',
        successMessage: 'تم تصدير تقرير القوالب بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
