@extends('hr::master')

@section('title')
    دفاتر الحضور
@stop

@section('page-actions')
    @permission('attendance_books.create')
        <a href="{{ route('attendance_sheets.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">أضف دفتر حضور</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'دفاتر الحضور',
        'icon' => 'book',
        'sub' => 'دفاتر تجمع أيام حضور مجموعة من الموظفين في مدّة محدّدة.',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'دفاتر الحضور' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'daily'])

    <div id="alertContainer">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
    </div>

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-attendance-sheets.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه كشوفُ الحضور: مجموعةُ موظفين تُتابَع أيامُهم معاً في مدّةٍ محدَّدة. والكشفُ وعاءُ متابعةٍ لا مصدرٌ جديدٌ للحضور.',
        'blocks' => [
            'ما يجمعه الكشف' => [
                'يُبنى الكشفُ باختيار فرعٍ أو قسمٍ أو مسمّىً أو ورديّة، فيضمّ من ينطبق عليه الاختيارُ في تلك المدّة.',
                'وأيامُ الحضور المعروضةُ فيه هي أيامُ «أيام الحضور» نفسُها — لا نسخةَ منها. فتعديلُ يومٍ هناك يظهر هنا.',
            ],
            'ما لا يفعله' => [
                'لا يُنشئ حضوراً ولا يُغيّر حالةَ يومٍ بمجرّد ضمّه. وحذفُ الكشف لا يمسّ أيامَ الحضور — يُلغي التجميعَ وحده.',
            ],
        ],
        'facts' => [
            'الكشفُ تجميعٌ لا مصدرٌ للحضور',
            'حذفُه لا يمسّ أيامَ الحضور',
        ],
    ])

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                <span class="stk-count" id="sheetsFiltersActive" style="display:none">فلاتر مفعّلة</span>
            </div>

            <form class="form" id="searchForm">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="keywords">الموظف</label>
                            <input type="text" class="form-control" placeholder="ابحث بالاسم أو الرقم الوظيفي…" name="keywords" id="keywords">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="from_date">من تاريخ</label>
                            <input type="date" class="form-control" name="from_date" id="from_date">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="to_date">إلى تاريخ</label>
                            <input type="date" class="form-control" name="to_date" id="to_date">
                        </div>
                    </div>
                </div>

                <div class="collapse" id="advancedSearchForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select class="form-control" name="status" id="status">
                                    <option value="">الكل</option>
                                    <option value="0">تحت المراجعة</option>
                                    <option value="1">موافق عليه</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="department">القسم</label>
                                <select class="form-control select2" name="department" id="department">
                                    <option value="">الكل</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="job_title">المسمّى الوظيفي</label>
                                <select class="form-control select2" name="job_title" id="job_title">
                                    <option value="">الكل</option>
                                    @foreach ($job_titles as $job)
                                        <option value="{{ $job->id }}">{{ $job->name }}</option>
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
                                ])
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <button type="button" class="btn btn-outline-secondary me-auto" data-bs-toggle="collapse" data-bs-target="#advancedSearchForm">
                        <i class="feather icon-sliders"></i> بحث متقدم
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card stk-table-card position-relative">
        <div id="loadingSpinner" class="text-center py-2 d-none">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <span class="text-muted small ms-1">جاري التحميل…</span>
        </div>

        <div id="resultsContainer">
            @include('hr::attendance.attendance_sheets.table_rows')
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">حذف دفتر الحضور</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0"><strong>هل أنت متأكد من حذف هذا الدفتر؟</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">تأكيد الحذف</button>
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
        let deleteId = null;
        let currentPage = 1;

        // Search form submission
        $('#searchForm').on('submit', function(e) {
            e.preventDefault();
            currentPage = 1;
            loadAttendanceSheets();
        });

        // Real-time search as user types
        $('#keywords').on('input', debounce(function() {
            currentPage = 1;
            loadAttendanceSheets();
        }, 300));

        // Date and filter change handlers
        $('#from_date, #to_date, #status, #department, #job_title, #branch').on('change', function() {
            currentPage = 1;
            loadAttendanceSheets();
        });

        // Reset filters
        function resetFilters() {
            $('#searchForm')[0].reset();
            $('#branch').val(''); if (window.__rptInitAffiliationTree_branch) window.__rptInitAffiliationTree_branch();
            if ($.fn.select2) { $('#department, #job_title').val('').trigger('change.select2'); }
            currentPage = 1;
            loadAttendanceSheets();
        }
        $('#resetFilters').on('click', resetFilters);
        $(document).on('click', '.js-sheets-reset', resetFilters);

        // الترقيم: روابطُه تشير إلى مسار AJAX، فتُحمَّل الصفحةُ المطلوبة هنا
        $(document).on('click', '#resultsContainer .pagination a', function(e) {
            e.preventDefault();
            const page = new URL(this.href, window.location.origin).searchParams.get('page');
            if (page) {
                currentPage = parseInt(page, 10) || 1;
                loadAttendanceSheets();
            }
        });

        // Delete confirmation
        $(document).on('click', '.delete-btn', function() {
            deleteId = $(this).data('id');
            $('#deleteModal').modal('show');
        });

        $('#confirmDeleteBtn').on('click', function() {
            if (deleteId) {
                deleteAttendanceSheet(deleteId);
            }
        });

        function syncFilterBadge() {
            const active = $('#searchForm').find('input[type="text"], input[type="date"], select').filter(function() {
                return this.name && this.name !== '_token' && $(this).val();
            }).length > 0 || !!$('#branch').val();
            $('#sheetsFiltersActive').toggle(active);
        }

        // Function to load attendance sheets
        function loadAttendanceSheets() {
            showLoading();

            const formData = $('#searchForm').serialize() + (currentPage > 1 ? '&page=' + currentPage : '');

            $.ajax({
                url: "{{ route('attendance_sheets.ajax.index') }}",
                method: 'GET',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function(response) {
                    hideLoading();
                    $('#resultsContainer').html(response.html);
                    syncFilterBadge();

                    if (response.message) {
                        showAlert('success', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('خطأ في تحميل البيانات:', error);
                    showAlert('danger', 'حدث خطأ في تحميل البيانات. يرجى المحاولة مرة أخرى.');
                }
            });
        }

        // Function to delete attendance sheet
        function deleteAttendanceSheet(id) {
            showLoading();

            $.ajax({
                url: "{{ url('attendance_sheets') }}/" + id,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function(response) {
                    hideLoading();
                    $('#deleteModal').modal('hide');

                    if (response.success) {
                        showAlert('success', response.message || 'تم الحذف بنجاح');
                        loadAttendanceSheets();
                    } else {
                        showAlert('danger', response.message || 'حدث خطأ أثناء الحذف');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    $('#deleteModal').modal('hide');
                    console.error('خطأ في الحذف:', error);

                    let errorMessage = 'حدث خطأ أثناء الحذف. يرجى المحاولة مرة أخرى.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    showAlert('danger', errorMessage);
                }
            });
        }

        // Utility functions
        function showLoading() {
            $('#loadingSpinner').removeClass('d-none');
            $('#resultsContainer').addClass('opacity-50');
        }

        function hideLoading() {
            $('#loadingSpinner').addClass('d-none');
            $('#resultsContainer').removeClass('opacity-50');
        }

        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                </div>
            `;
            $('#alertContainer').html(alertHtml);

            setTimeout(function() {
                $('#alertContainer .alert').fadeOut();
            }, 5000);
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    });
</script>
@endpush
