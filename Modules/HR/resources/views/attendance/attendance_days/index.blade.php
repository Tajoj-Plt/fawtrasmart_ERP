@extends('hr::master')

@section('title')
أيام الحضور
@stop

@section('page-actions')
    @php
        $canCalcDays = hasPermission('attendance_days.calculate');
        $canOnline = hasPermission('attendance_registration.kiosk');
    @endphp
    @permission('attendance_days.edit')
        <a href="{{ route('attendanceDays.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">أضف الحضور اليومي</span>
        </a>
    @endpermission
    @if ($canCalcDays || $canOnline)
        <div class="btn-group">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                id="attendanceDaysActions" data-bs-toggle="dropdown"
                data-bs-display="static" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i>
                <span class="d-none d-md-inline">إجراءات</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="attendanceDaysActions">
                <li><h6 class="dropdown-header">الإدارة</h6></li>
                @if ($canCalcDays)
                    <li>
                        <a class="dropdown-item" href="{{ route('attendanceDays.calculation') }}">
                            <i class="fas fa-calculator me-2 text-success"></i>حساب الحضور
                        </a>
                    </li>
                @endif
                @if ($canOnline)
                    <li>
                        <a class="dropdown-item" href="{{ route('barcode.scan') }}">
                            <i class="fas fa-camera me-2 text-info"></i>كاميرا مسح الباركود
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'أيام الحضور',
        'icon' => 'calendar',
        'sub' => 'سجلات الحضور اليومية لكل موظف: حاضر، غائب، متأخر، إجازة.',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'أيام الحضور' => null],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-attendance-days.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه أيامُ الحضور: يومٌ لكلّ موظف بحالته. وهذا السجلُّ ليس للاطّلاع وحده — منه يُحتسَب حسمُ الغياب في الراتب.',
        'blocks' => [
            'ما يترتّب على الحالة' => [
                'الغيابُ في هذا السجلّ يصير حسماً في صرفة الراتب، فتعديلُ يومٍ هنا يغيّر مبلغاً يُصرَف لاحقاً.',
                'و«متأخر» ليس «غائب»: الأوّل يخضع لقواعد التأخير في الإعدادات، والثاني يُحسَم يوماً كاملاً.',
                'ومن لا سجلَّ له في يومٍ لا يُحسَب حاضراً ولا غائباً — فالفراغُ ليس حالة.',
            ],
            'من أين تأتي هذه الأيام' => [
                'من بصمات الحضور المسجَّلة، أو استيرادٍ من جهاز البصمة، أو إدخالٍ يدويٍّ لمن يملك تعديلَ الأيام.',
                'والإجازةُ المعتمدةُ تسبق الغياب: يومٌ في إجازةٍ لا يُحسَم.',
            ],
            'التصحيح' => [
                'من لا يملك التعديلَ المباشر يرفع «طلبَ تصحيح» يعتمده غيرُه — فلا يُعدَّل سجلُّ الحضور بلا أثرٍ يُعرَف صاحبُه.',
            ],
        ],
        'facts' => [
            'الغيابُ هنا يصير حسماً في الراتب',
            'الإجازةُ المعتمدةُ تمنع احتسابَ الغياب',
        ],
    ])

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                <span class="stk-count" id="attDaysFiltersActive" style="display:none">فلاتر مفعّلة</span>
                <span id="loading" class="text-muted small" style="display:none;">
                    <i class="fa fa-spinner fa-spin"></i> جاري البحث…
                </span>
            </div>

            <form class="form" id="filterForm">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="keywords">الموظف</label>
                            <input type="text" class="form-control filter-input" placeholder="ابحث بالاسم أو الرقم…" name="keywords" id="keywords">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="from_date">من تاريخ</label>
                            <input type="date" class="form-control filter-input" name="from_date" id="from_date">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="to_date">إلى تاريخ</label>
                            <input type="date" class="form-control filter-input" name="to_date" id="to_date">
                        </div>
                    </div>
                </div>

                <div class="collapse" id="advancedSearchForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="status">حالة الحضور</label>
                                <select class="form-control filter-input" name="status" id="status">
                                    <option value="">الكل</option>
                                    <option value="present">حاضر</option>
                                    <option value="absent">غائب</option>
                                    <option value="holiday">عطلة</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="department">القسم</label>
                                <select class="form-control select2 filter-input" id="department" name="department">
                                    <option value="">الكل</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" {{ request('department') == $department->id ? 'selected' : '' }}>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                @include('reports.partials.affiliation-tree', [
                                    'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                                    'name' => 'branch',
                                    'showExact' => false,
                                    'label' => 'تابع لـ',
                                    'treeInputClass' => 'filter-input',
                                ])
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <a class="btn btn-outline-secondary me-auto collapsed" href="#advancedSearchForm" data-toggle="collapse" data-target="#advancedSearchForm">
                        <i class="feather icon-sliders"></i> بحث متقدم
                    </a>
                    <button type="button" id="clearFilters" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-list"></i> قائمة أيام الحضور
                <span class="stk-count" id="attendanceDaysTotal">{{ $attendance_days->total() }}</span>
            </h6>
            @include('stock::components.per-page', [
                'id' => 'attendanceDaysPerPage',
                'name' => 'per_page',
                'current' => request('per_page', 10),
            ])
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0" id="attendance-days-table">
                <thead>
                    <tr>
                        <th style="width:44px">#</th>
                        <th>الموظف</th>
                        <th>التاريخ</th>
                        <th>تسجيل الدخول</th>
                        <th>تسجيل الخروج</th>
                        <th>ساعات العمل</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center" style="width:70px">إجراءات</th>
                    </tr>
                </thead>
                <tbody id="attendance-table-body">
                    @include('hr::attendance.attendance_days.table_rows')
                </tbody>
            </table>
        </div>

        <div id="pagination-section">
            @include('hr::attendance.attendance_days.pagination')
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
<script>
$(document).ready(function() {

    function syncFilterBadge() {
        const active = $('#filterForm').find('input[type="text"], input[type="date"], select').filter(function() {
            return this.name && this.name !== 'per_page' && this.name !== '_token' && $(this).val();
        }).length > 0 || !!$('#branch').val();
        $('#attDaysFiltersActive').toggle(active);
    }

    // دالة لتنفيذ الفلترة
    function performFilter() {
        $('#loading').show();

        const formData = new FormData($('#filterForm')[0]);
        const perPage = $('select[name="per_page"]').val();
        formData.append('per_page', perPage);

        $.ajax({
            url: '{{ route("attendanceDays.filter") }}',
            type: 'GET',
            data: Object.fromEntries(formData),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#attendance-table-body').html(response.html);

                    if (response.pagination !== undefined) {
                        $('#pagination-section').html(response.pagination);
                        const total = $('#pagination-section .js-att-days-total').data('total');
                        if (total !== undefined) {
                            $('#attendanceDaysTotal').text(total);
                        }
                    }
                    syncFilterBadge();
                }
            },
            error: function(xhr, status, error) {
                console.error('خطأ في الفلترة:', error);
                alert('حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.');
            },
            complete: function() {
                $('#loading').hide();
            }
        });
    }

    // تنفيذ الفلترة عند الكتابة في حقول النص (مع تأخير)
    let searchTimeout;
    $('.filter-input').on('keyup change', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            performFilter();
        }, 300);
    });

    // تنفيذ الفلترة عند إرسال النموذج
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        performFilter();
    });

    // مسح جميع الفلاتر
    function clearFilters() {
        $('#filterForm')[0].reset();
        $('#branch').val(''); if (window.__rptInitAffiliationTree_branch) window.__rptInitAffiliationTree_branch();
        if ($.fn.select2) { $('#department').val('').trigger('change.select2'); }
        performFilter();
    }
    $('#clearFilters').on('click', clearFilters);
    $(document).on('click', '.js-att-days-reset', clearFilters);

    // تنفيذ الفلترة عند تغيير التواريخ والقوائم المنسدلة (ومنها عدد السجلات)
    $('input[type="date"], select').on('change', function() {
        performFilter();
    });

    // تهيئة Select2 إذا كان مستخدماً
    if ($.fn.select2) {
        $('.select2').select2({
            placeholder: "اختر من القائمة",
            allowClear: true
        });
    }
});
</script>
@endpush
