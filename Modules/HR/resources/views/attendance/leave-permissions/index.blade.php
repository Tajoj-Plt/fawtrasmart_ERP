@extends('hr::master')

@section('title')
    أذونات إجازة
@stop

@section('page-actions')
    @if (hasPermission('leave_permissions.create'))
        <a href="{{ route('leave_permissions.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">إضافة إذن إجازة</span>
        </a>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'أذونات الإجازة اليومية',
        'icon' => 'clock',
        'sub' => 'تصاريح التأخير عن الدخول أو الانصراف المبكر للموظفين',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'أذونات الإجازة' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'leaves'])

    <div id="alertContainer">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
    </div>

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-leave-permissions.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه أذونُ الاستئذان: خروجٌ ساعاتٍ في يوم دوام، لا إجازةُ يومٍ كامل. فهي غيرُ «طلبات الإجازة» ولا تُخصَم من رصيدها.',
        'blocks' => [
            'الفرقُ عن الإجازة' => [
                'الإجازةُ تُحتسَب أياماً وتُخصَم من رصيد نوعها. والإذنُ ساعاتٌ داخل يومٍ يبقى يومَ حضور.',
                'فالموظفُ المأذونُ حاضرٌ في سجلّ اليوم، والساعاتُ المأذونةُ لا تُعدّ انصرافاً مبكّراً يُحاسَب عليه.',
            ],
            'ما يترتّب على الاعتماد' => [
                'الإذنُ المعتمَدُ يمنع احتسابَ تلك الساعات تأخيراً أو انصرافاً مبكّراً في حساب اليوم.',
                'والمعلَّقُ لا أثرَ له: الساعاتُ تُحسَب على الموظف حتى يُعتمَد الإذن.',
            ],
        ],
        'facts' => [
            'الإذنُ ساعاتٌ لا يومٌ، ولا يُخصَم من رصيد الإجازات',
            'المعتمَدُ وحده يمنع احتسابَ الساعات تأخيراً',
        ],
    ])

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                <span class="stk-count" id="lpFiltersBadge" style="display:none">فلاتر مفعّلة</span>
            </div>

            <form class="form" id="searchForm">
                @csrf
                <input type="hidden" name="per_page" id="lp_per_page" value="{{ request('per_page', 10) }}">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="keywords">الموظف</label>
                            <input type="text" class="form-control" placeholder="ابحث بالاسم أو الكود…" name="keywords" id="keywords">
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

                <!-- Advanced Search -->
                <div class="collapse" id="advancedSearchForm">
                    <div class="row">
                        {{--
                            القيمُ أرقامُ العمود لا أسماءٌ إنجليزية.

                            كانت `annual_leave`/`emergency_leave` تُرسَل إلى
                            `where('type', ...)` وعمودُ `type` رقمٌ (1/2) —
                            فلا يُطابِق شيئاً، وتعود الشاشةُ فارغةً عند كل
                            اختيار.
                        --}}
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="status">نوع الإجازة</label>
                                <select class="form-control" name="status" id="status">
                                    <option value="">كل الأنواع</option>
                                    @foreach (\App\Models\LeavePermissions::TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{--
                            نوعُ الإذن — ما يُفتَح لأجله هذا السجلّ: الإذنُ
                            يرفع عقوبةَ التأخير أو عقوبةَ الانصراف المبكر،
                            لا كليهما.

                            وموضعَه كانت «حالة الموافقة» — قائمةٌ على عمودٍ
                            لا وجودَ له (`approval_status`) فكان كلُّ اختيارٍ
                            منها يردّ الشاشةَ بخطإ 500.
                        --}}
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="leave_type">نوع الإذن</label>
                                <select class="form-control" name="leave_type" id="leave_type">
                                    <option value="">كل الأنواع</option>
                                    @foreach (\App\Models\LeavePermissions::LEAVE_TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="department">القسم</label>
                                <select class="form-control select2" name="department" id="department">
                                    <option value="">-- اختر القسم --</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-8">
                            @include('reports.partials.affiliation-tree', [
                                'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                                'name' => 'branch',
                                'showExact' => false,
                                'label' => 'تابع لـ',
                            ])
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="job_title">المسمى الوظيفي</label>
                                <select class="form-control select2" name="job_title" id="job_title">
                                    <option value="">-- اختر الوظيفة --</option>
                                    @foreach ($job_titles as $job)
                                        <option value="{{ $job->id }}">{{ $job->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-toggle="collapse"
                        data-bs-target="#advancedSearchForm" data-target="#advancedSearchForm">
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

    <div class="card stk-table-card">
        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="card-body text-center d-none">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <!-- Results — تُحمَّل عبر AJAX -->
        <div id="resultsContainer"></div>
    </div>

    {{-- نافذةُ الحذف تُبنى مع كلّ صفٍّ في `partials/table_rows` --}}
</div>
@endsection

@push('scripts')
    @include('hr::components.table-menu')
    {{--
        كان هنا تحميلٌ ثانٍ لـ jQuery وبوتستراب ٥ — والقالبُ الأمّ يحمّلهما
        أصلاً. وتحميلُ jQuery مرّةً ثانيةً هو ما كان يرمي قائمةَ الإجراءات
        إلى أعلى الشاشة: سكربتُ وضْعِ القوائم في `hr::master` يقرأ `$`
        وقت التنفيذ، أي النسخةَ الأخيرة، بينما بوتستراب ٤ خزّن نسخةَ القائمة
        في مخزن jQuery الأوّل. فبنسخةٍ واحدةٍ من jQuery تعود القائمة تحت زرّها.
    --}}
    <script>
        $(document).ready(function() {
            var filterFields = ['#keywords', '#from_date', '#to_date', '#status', '#leave_type', '#department', '#branch', '#job_title'];

            // Load initial data
            loadLeavePermissions();

            // Search form submission
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                loadLeavePermissions();
            });

            // Real-time search as user types
            $('#keywords').on('input', debounce(function() {
                loadLeavePermissions();
            }, 300));

            // Date and filter change handlers
            $('#from_date, #to_date, #status, #leave_type, #department, #branch, #job_title').on('change', function() {
                loadLeavePermissions();
            });

            // Reset filters
            function resetFilters() {
                var perPage = $('#lp_per_page').val();
                $('#searchForm')[0].reset();
                $('#lp_per_page').val(perPage);
                $('#department, #job_title').val('').trigger('change.select2');
                $('#branch').val(''); if (window.__rptInitAffiliationTree_branch) window.__rptInitAffiliationTree_branch();
                loadLeavePermissions();
            }
            $('#resetFilters').on('click', resetFilters);
            $(document).on('click', '#resultsContainer .lp-reset-filters', resetFilters);

            // روابطُ الصفحات في الجدول تشير إلى مسار AJAX نفسه — تُحمَّل هنا لا بفتحها
            $(document).on('click', '#resultsContainer .pagination a', function(e) {
                var href = $(this).attr('href');
                if (!href || href === '#') return;
                e.preventDefault();
                loadLeavePermissions(href);
            });

            /*
                الحذفُ يتمّ من نافذةِ الصفّ نفسها في `partials/table_rows`:
                زرٌّ يفتحها ونموذجٌ يرسل `DELETE` ثم يعود المتحكّم بتحويلٍ
                ورسالةِ نجاح.
            */

            function updateFiltersBadge() {
                var active = filterFields.some(function(sel) {
                    var v = $(sel).val();
                    return v !== undefined && v !== null && v !== '';
                });
                $('#lpFiltersBadge').toggle(active);
            }

            // Function to load leave permissions
            function loadLeavePermissions(pageUrl) {
                showLoading();
                updateFiltersBadge();

                $.ajax({
                    url: pageUrl || "{{ route('leave_permissions.ajax.index') }}",
                    method: 'GET',
                    data: pageUrl ? undefined : $('#searchForm').serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        hideLoading();
                        $('#resultsContainer').html(response.html).addClass('fade-in');

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
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                $('#alertContainer').html(alertHtml);

                // Auto-hide after 5 seconds
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
