@extends('hr::master')

@section('title')
    ورديات متخصصه
@stop

@section('page-actions')
    @permission('shifts.create')
        <a href="{{ route('custom_shifts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">أضف ورديات مخصصة</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'الورديات المخصصة',
        'icon' => 'user-check',
        'sub' => 'تعيين ورديات مختلفة لموظفين معينين لفترة محددة',
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'shifts'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-custom-shifts.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه الورديّاتُ المخصَّصة: ورديّةٌ تُسنَد إلى فئةٍ بعينها — فرعٍ أو قسمٍ أو مسمّىً أو موظفٍ — خلافاً للورديّة العامّة.',
        'blocks' => [
            'ما تحكمه الورديّة' => [
                'أوقاتُ بدء الدوام وانتهائه هي المقياسُ الذي يُقاس إليه حضورُ الموظف: منها يُشتقّ التأخيرُ والانصرافُ المبكّر والوقتُ الإضافي.',
                'فتغييرُ ورديّةٍ يغيّر تصنيفَ الأيام التي تُحسَب بعدها — ومن ثمّ ما يُحسَم من الرواتب.',
            ],
            'الأخصُّ يتقدّم' => [
                'ورديّةٌ مسنَدةٌ إلى موظفٍ بعينه تتقدّم على المسنَدة إلى مسمّاه أو قسمه أو فرعه، والعامّةُ آخرُها.',
                'وإسنادُ ورديّةٍ إلى فئةٍ يسري على كلّ من يدخلها لاحقاً، لا على من فيها اليوم وحدَهم.',
            ],
        ],
        'facts' => [
            'الورديّةُ مقياسُ التأخير والوقت الإضافي',
            'الأخصُّ إسناداً يتقدّم على الأعمّ',
        ],
    ])

    @php
        $hasFilters = collect(request()->only(['keywords', 'from_date', 'to_date']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    {{-- التصفيةُ بـAJAX: `#filterForm` وحقولُه و`#resetFilter` تقرؤها شيفرةُ الصفحة --}}
    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form id="filterForm" method="GET" action="#">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="keywords">البحث بواسطة الموظف</label>
                            <input type="text" class="form-control" placeholder="ادخل الإسم او الكود" name="keywords" id="keywords" value="{{ request('keywords') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="from_date">من تاريخ</label>
                            <input type="date" class="form-control" name="from_date" id="from_date" value="{{ request('from_date') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="to_date">إلى تاريخ</label>
                            <input type="date" class="form-control" name="to_date" id="to_date" value="{{ request('to_date') }}">
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <button type="button" id="resetFilter" class="btn btn-outline-secondary">
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
        <div id="loadingSpinner" class="text-center py-2 d-none">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden"></span>
            </div>
        </div>

        {{-- `#resultsContainer` يُستبدل محتواه بما يعيده `custom_shifts.filter` --}}
        <div id="resultsContainer">
            @include('hr::attendance.custom_shifts.partials.table_rows', ['custom_shifts' => $custom_shifts])
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush

@section('scripts')
<script>
    $(document).ready(function() {
        // فلترة البيانات عند تغيير أي حقل
        $('#keywords, #from_date, #to_date').on('input change', debounce(function() {
            filterData();
        }, 300));

        // إعادة تعيين الفلترة
        $('#resetFilter').on('click', function() {
            $('#keywords').val('');
            $('#from_date').val('');
            $('#to_date').val('');
            filterData();
        });

        // منع إعادة تحميل الصفحة عند الضغط على Enter في النموذج
        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            filterData();
        });

        // وظيفة الفلترة
        function filterData(customData = null) {
            showLoading();
            
            const formData = customData || $('#filterForm').serialize();

            $.ajax({
                url: "{{ route('custom_shifts.filter') }}",
                method: 'GET',
                data: formData,
                success: function(response) {
                    hideLoading();
                    if (response.html) {
                        $('#resultsContainer').html(response.html);
                    }
                },
                error: function(xhr) {
                    hideLoading();
                    console.log('خطأ في الفلترة:', xhr.responseText);
                    showAlert('حدث خطأ أثناء الفلترة', 'danger');
                }
            });
        }

        // تحديث عدد السجلات المعروضة
        function updatePerPage(perPage) {
            const formData = $('#filterForm').serialize() + '&per_page=' + perPage;
            filterData(formData);
        }

        // دوال مساعدة
        function showLoading() {
            $('#loadingSpinner').removeClass('d-none');
            $('#resultsContainer').addClass('opacity-50');
        }

        function hideLoading() {
            $('#loadingSpinner').addClass('d-none');
            $('#resultsContainer').removeClass('opacity-50');
        }

        function showAlert(message, type = 'info') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#resultsContainer').prepend(alertHtml);

            setTimeout(() => {
                $('.alert').fadeOut();
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

        // جعل الدوال متاحة globally
        window.updatePerPage = updatePerPage;
        window.filterData = filterData;
    });
</script>
@endsection