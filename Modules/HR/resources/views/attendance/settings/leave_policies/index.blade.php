@extends('hr::master')

@section('title', 'سياسات الإجازات')

@section('page-actions')
    @permission('leave_policies.create')
        <a href="{{ route('leave_policy.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">إضافة سياسة إجازة</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'سياسات الإجازات',
        'icon' => 'bookmark',
        'sub' => 'تخصيص أنواع الإجازات وأرصدتها لمجموعات الموظفين',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'سياسات الإجازات' => null],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-leave-policies.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه سياساتُ الإجازات: كم يستحقّ الموظفُ من كلّ نوع، وكيف يتجدّد رصيدُه. ومنها تُشتقّ الأرصدةُ التي تُخصَم منها الطلبات.',
        'blocks' => [
            'ما تقرّره السياسة' => [
                'مقدارَ الاستحقاق السنويّ لكلّ نوع إجازة، وطريقةَ تجدّده، وما يُرحَّل منه إن رُحِّل.',
                'وتقبل التخصيصَ بفرعٍ أو قسمٍ أو مسمّىً أو مستوىً — فسياسةٌ تُكتب لفئةٍ لا تسري على غيرها.',
            ],
            'ما يترتّب على التغيير' => [
                'تغييرُ السياسة يغيّر ما يُمنَح بعده من رصيد؛ والأرصدةُ الممنوحةُ فعلاً لا تُنقَص بأثرٍ رجعيّ من هنا.',
                'ولتعديل رصيدٍ قائمٍ موضعُه شاشةُ «أرصدة إجازات الموظفين» لا هذه.',
            ],
        ],
        'facts' => [
            'السياسةُ مصدرُ الرصيد لا الرصيدُ نفسُه',
            'تخصَّص بفرعٍ أو قسمٍ أو مسمّىً أو مستوى',
        ],
    ])

    @php
        $hasFilters = request()->filled('keywords') || (request()->has('status') && request('status') !== '' && request('status') !== null);
    @endphp

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                <span class="stk-count" id="lpFiltersBadge" @if (! $hasFilters) style="display:none" @endif>فلاتر مفعّلة</span>
            </div>

            <form class="form" id="searchForm" method="GET" action="{{ route('leave_policy.index') }}">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="keywords">السياسة</label>
                            <input type="text" class="form-control" placeholder="ابحث باسم السياسة…" name="keywords" id="keywords" value="{{ request('keywords') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">الكل</option>
                                <option value="0" @selected(request('status') === '0')>نشط</option>
                                <option value="1" @selected(request('status') === '1')>غير نشط</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <a href="{{ route('leave_policy.index') }}" id="clearFilter" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card stk-table-card" id="tableContainer">
        @include('hr::attendance.settings.leave_policies.table-content')
    </div>

    <!-- Modal لعرض الموظفين -->
    <div class="modal fade" id="employeesModal" tabindex="-1" role="dialog" aria-labelledby="employeesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title" id="employeesModalLabel">قائمة الموظفين</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>الموظف</th>
                                    <th>القسم</th>
                                    <th>المسمى الوظيفي</th>
                                    <th>نوع التخصيص</th>
                                </tr>
                            </thead>
                            <tbody id="employeesTableBody">
                                <!-- سيتم ملؤها بـ AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
<script>
$(document).ready(function() {
    function lpHasFilters() {
        return $('#keywords').val() !== '' || $('#status').val() !== '';
    }

    // البحث بـ AJAX — المسار `leave_policy.search` مسجَّلٌ GET
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: '{{ route("leave_policy.search") }}',
            type: 'GET',
            data: {
                keywords: $('#keywords').val(),
                status: $('#status').val()
            },
            beforeSend: function() {
                $('#tableContainer').html('<div class="card-body text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
            },
            success: function(response) {
                if (response.success) {
                    $('#tableContainer').html(response.html);
                    $('#lpFiltersBadge').toggle(lpHasFilters());
                } else {
                    toastr.error('حدث خطأ أثناء البحث');
                }
            },
            error: function() {
                toastr.error('حدث خطأ في الاتصال');
                location.reload();
            }
        });
    });

    // إعادة تعيين
    $('#clearFilter').on('click', function(e) {
        e.preventDefault();
        $('#keywords').val('');
        $('#status').val('');
        $('#searchForm').trigger('submit');
    });

    // عرض قائمة الموظفين في نافذة
    var employeesListUrl = @json(route('leave_policy.employeesList', '__ID__'));

    $(document).on('click', '.show-employees-modal', function(e) {
        e.preventDefault();
        const policyId = $(this).data('policy-id');
        const policyName = $(this).data('policy-name');

        $('#employeesModalLabel').text('موظفو سياسة: ' + policyName);
        $('#employeesTableBody').html('<tr><td colspan="4" class="text-center"><i class="fa fa-spinner fa-spin"></i> جاري التحميل...</td></tr>');
        $('#employeesModal').modal('show');

        $.ajax({
            url: employeesListUrl.replace('__ID__', policyId),
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.employees && response.employees.length > 0) {
                    let tableContent = '';
                    response.employees.forEach(function(employee) {
                        tableContent += `
                            <tr>
                                <td>${employee.full_name}</td>
                                <td>${employee.department ? employee.department.name : 'غير محدد'}</td>
                                <td>${employee.job_title ? employee.job_title.name : 'غير محدد'}</td>
                                <td>
                                    <span class="badge ${employee.assignment_type === 'مباشر' ? 'badge-primary' : 'badge-info'}">
                                        ${employee.assignment_type}
                                    </span>
                                </td>
                            </tr>
                        `;
                    });
                    $('#employeesTableBody').html(tableContent);
                } else {
                    $('#employeesTableBody').html('<tr><td colspan="4" class="text-center text-muted">لا يوجد موظفون مخصصون لهذه السياسة</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error details:', status, error, xhr.responseText);
                $('#employeesTableBody').html('<tr><td colspan="4" class="text-center text-danger">حدث خطأ أثناء تحميل البيانات</td></tr>');
            }
        });
    });
});
</script>
@endpush
