@extends('taskmanager::master')

@section('title')
    المشاريع
@stop

@section('css')
    <style>
        .form-control {
            margin-bottom: 10px;
        }

        #loading-indicator {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 0.375rem;
        }

        .spinner-border {
            width: 2rem;
            height: 2rem;
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .content-header-title {
                font-size: 1.5rem;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
            }

            .card {
                margin: 10px;
                padding: 10px;
            }

            .table {
                font-size: 0.8rem;
                width: 100%;
                overflow-x: auto;
            }

            .table th,
            .table td {
                white-space: nowrap;
            }

            .form-check {
                margin-bottom: 10px;
            }

            .form-control {
                width: 100%;
            }

            .dropdown-menu {
                min-width: 200px;
            }
        }

        @media (max-width: 480px) {

            .table th,
            .table td {
                font-size: 0.7rem;
            }
        }
    </style>
@endsection

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إدارة المشاريع</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسيه</a></li>
                            <li class="breadcrumb-item active">عرض</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p class="mb-0">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="content-body">
        <div class="container-fluid">
            @include('taskmanager::partials.page_hero', [
                'heroTitle' => 'إدارة',
                'heroHighlight' => 'المشاريع',
                'heroSubtitle' => 'تابع مشاريعك السكرم واليدوية في مكان واحد، وابدأ مشروعاً جديداً بضغطة واحدة',
                'heroStats' => [
                    ['icon' => 'fas fa-diagram-project', 'value' => $totalProjectsCount, 'label' => 'إجمالي المشاريع'],
                    ['icon' => 'fas fa-bolt', 'value' => $activeProjectsCount, 'label' => 'مشاريع نشطة'],
                    ['icon' => 'fas fa-layer-group', 'value' => $workspaces->count(), 'label' => 'مساحات العمل'],
                ],
                'heroActions' => [
                    ['url' => route('projects.create'), 'label' => 'مشروع جديد', 'icon' => 'fas fa-plus-circle', 'style' => 'solid'],
                ],
            ])

            <!-- شريط الأدوات العلوي -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex flex-wrap justify-content-between">
                            <a href="{{ route('projects.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-1"></i>مشروع جديد
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- نموذج البحث -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center p-2">
                    <div class="d-flex gap-2">
                        <span class="hide-button-text">بحث وتصفية</span>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">

                        <!-- إخفاء / إظهار -->
                        <button class="btn btn-outline-primary btn-sm waves-effect waves-light"
                            onclick="toggleSearchFields(this)">
                            <i class="fa fa-times me-1"></i>
                            <span class="hide-button-text">إخفاء</span>
                        </button>

                        <!-- بحث متقدم -->
                        <button class="btn btn-outline-primary btn-sm waves-effect waves-light" data-bs-toggle="collapse"
                            data-bs-target="#advancedSearchForm" onclick="toggleSearchText(this)">
                            <i class="fa fa-filter me-1"></i>
                            <span class="button-text">متقدم</span>
                        </button>

                        <!-- إعادة تعيين -->
                        <button type="button" id="resetSearch"
                            class="btn btn-outline-primary btn-sm waves-effect waves-light">
                            <i class="fa fa-refresh me-1"></i>
                            إعادة تعيين
                        </button>

                    </div>

                </div>

                <div class="card-body">
                    <form class="form" id="searchForm">
                        @csrf
                        <div class="row g-3">
                            <!-- الحقول الأساسية -->
                            <div class="col-md-4">
                                <input type="text" name="title" class="form-control" placeholder="اسم المشروع">
                            </div>

                            <div class="col-md-4">
                                <select name="workspace_id" class="form-control select2">
                                    <option value="">أي مساحة عمل</option>
                                    @foreach ($workspaces as $workspace)
                                        <option value="{{ $workspace->id }}">{{ $workspace->title }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <select name="status" class="form-control select2">
                                    <option value="">الحالة</option>
                                    <option value="planning">تخطيط</option>
                                    <option value="active">نشط</option>
                                    <option value="on_hold">معلق</option>
                                    <option value="completed">مكتمل</option>
                                    <option value="cancelled">ملغي</option>
                                    <option value="archived">مؤرشف</option>
                                </select>
                            </div>
                        </div>

                        <!-- البحث المتقدم -->
                        <div class="collapse" id="advancedSearchForm">
                            <div class="row g-3 mt-2">
                                <div class="col-md-3">
                                    <select name="priority" class="form-control select2">
                                        <option value="">الأولوية</option>
                                        <option value="low">منخفضة</option>
                                        <option value="medium">متوسطة</option>
                                        <option value="high">عالية</option>
                                        <option value="urgent">عاجلة</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <input type="date" name="from_date" class="form-control" placeholder="التاريخ من">
                                </div>

                                <div class="col-md-3">
                                    <input type="date" name="to_date" class="form-control" placeholder="التاريخ إلى">
                                </div>
                            </div>
                        </div>

                        <div class="form-actions mt-2">
                            <button type="submit" class="btn btn-primary">بحث</button>
                            <button type="button" id="resetSearch" class="btn btn-outline-warning">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- جدول النتائج -->
            <div class="card">
                <div class="card-body">
                    <div id="results-container">
                        <!-- سيتم تحميل الجدول هنا عبر AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        $(document).ready(function() {
            // تأكد من إظهار مؤشر التحميل قبل تحميل البيانات الأولية
            showLoading();
            // ثم قم بتحميل البيانات الأولية بعد لحظة قصيرة لضمان ظهور مؤشر التحميل
            setTimeout(function() {
                loadData();
            }, 100);

            // البحث عند إرسال النموذج
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                loadData();
            });

            // البحث الفوري عند تغيير قيم المدخلات
            $('#searchForm input, #searchForm select').on('change input', function() {
                clearTimeout(window.searchTimeout);
                window.searchTimeout = setTimeout(function() {
                    loadData();
                }, 500);
            });

            // إعادة تعيين الفلاتر
            $('#resetSearch').on('click', function() {
                $('#searchForm')[0].reset();
                loadData();
            });

            // التعامل مع الترقيم
            $(document).on('click', '.pagination a', function(e) {
                e.preventDefault();
                let url = $(this).attr('href');
                if (url) {
                    let page = new URL(url).searchParams.get('page');
                    loadData(page);
                }
            });

        // دالة تحميل البيانات
            window.loadData = function(page = 1, perPage = 10) {
                console.log('loadData called: page=' + page + ', perPage=' + perPage); // Debug log
                showLoading();

                // تحديث قيمة عدد العناصر في القائمة المنسدلة
                $('select[name="DataTables_Table_0_length"]').val(perPage);

                let formData = $('#searchForm').serialize();
                formData += '&page=' + page;
                formData += '&per_page=' + perPage;
                formData += '&is_ajax=1'; // إضافة معلمة واضحة للطلب AJAX

                $.ajax({
                    url: '{{ route('projects.index') }}',
                    method: 'GET',
                    data: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: false,
                    success: function(response) {
                        if (response.success) {
                            $('#results-container').html(response.table);
                            updatePaginationInfo(response);
                            initializeEvents();
                            initializeDataTableFeatures();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('خطأ في تحميل البيانات:', error);
                        console.error('حالة الخطأ:', status);
                        console.error('استجابة الخادم:', xhr.responseText);
                        $('#results-container').html(
                            '<div class="alert alert-danger text-center">' +
                            '<p class="mb-0">حدث خطأ في تحميل البيانات. يرجى المحاولة مرة أخرى.</p>' +
                            '</div>'
                        );
                    },
                    complete: function() {
                        hideLoading();
                    }
                });
            }

            function showLoading() {
                $('#results-container').css('opacity', '0.6');
                if ($('#loading-indicator').length === 0) {
                    $('#results-container').prepend(`
                <div id="loading-indicator" class="text-center p-3">
                    <div class="spinner-border text-primary" role="status">

                    </div>

                </div>
            `);
                }
            }

            function hideLoading() {
                $('#loading-indicator').remove();
                $('#results-container').css('opacity', '1');
            }

            function updatePaginationInfo(response) {
                $('.pagination-info').text(`صفحة ${response.current_page} من ${response.last_page}`);
                if (response.total > 0) {
                    $('.results-info').text(`${response.from}-${response.to} من ${response.total}`);
                } else {
                    $('.results-info').text('لا توجد نتائج');
                }
            }
 // في قسم scripts في ملف index الرئيسي
function setupPaginationEvents() {
    $('.pagination-link').off('click').on('click', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        const perPage = $('select[name="DataTables_Table_0_length"]').val();
        
        if (page && window.loadData) {
            window.loadData(page, perPage);
        }
    });
}
            function initializeEvents() {
                setupPaginationEvents();
                // أحداث الحذف
                $('.delete-project').off('click').on('click', function(e) {
                    e.preventDefault();
                    const projectId = $(this).data('id');

                    Swal.fire({
                        title: 'تأكيد الحذف',
                        text: 'هل أنت متأكد من حذف المشروع؟',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#2196F3',
                        confirmButtonText: 'نعم، احذف',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            deleteProject(projectId);
                        }
                    });
                });

                // تحديد الكل
                $('#selectAll').off('change').on('change', function() {
                    $('.project-checkbox').prop('checked', $(this).prop('checked'));
                });

                $('.project-checkbox').off('change').on('change', function() {
                    let totalCheckboxes = $('.project-checkbox').length;
                    let checkedCheckboxes = $('.project-checkbox:checked').length;
                    $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
                });
                
                // Pagination Click Events
                $(document).on('click', '.paginate_button:not(.disabled) a', function(e) {
                    e.preventDefault();
                    let url = $(this).attr('href');
                    if (url) {
                        let page = new URL(url).searchParams.get('page') || 1;
                        let perPage = $('select[name="DataTables_Table_0_length"]').val() || 10;
                        loadData(page, perPage);
                    }
                });
            }
            
            function initializeDataTableFeatures() {
                // Show entries dropdown change handler
                $('select[name="DataTables_Table_0_length"]').off('change').on('change', function() {
                    const perPage = $(this).val();
                    loadData(1, perPage);
                });
                
                // Search input handler
                $('#DataTables_Table_0_filter input').off('keyup').on('keyup', function() {
                    const searchTerm = $(this).val();
                    if (searchTerm.length > 2 || searchTerm.length === 0) {
                        clearTimeout(window.searchTimeout);
                        window.searchTimeout = setTimeout(function() {
                            $('input[name="title"]').val(searchTerm);
                            loadData(1);
                        }, 500);
                    }
                });
            }

            function deleteProject(projectId) {
                const row = $(`.delete-project[data-id="${projectId}"]`).closest('tr');
                row.css('opacity', '0.5');

                $.ajax({
                    url: '{{ route('projects.destroy', '__ID__') }}'.replace('__ID__', encodeURIComponent(projectId)),
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف!',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            loadData();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ!',
                                text: response.message
                            });
                            row.css('opacity', '1');
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: 'حدث خطأ أثناء حذف المشروع'
                        });
                        row.css('opacity', '1');
                    }
                });
            }

            initializeEvents();
        });

        // دوال التحكم في البحث المتقدم
        function toggleSearchText(button) {
            const buttonText = button.querySelector('.button-text');
            const advancedFields = document.querySelectorAll('.advanced-field');

            if (buttonText.textContent.trim() === 'متقدم') {
                buttonText.textContent = 'بحث بسيط';
                advancedFields.forEach(field => field.style.display = 'block');
            } else {
                buttonText.textContent = 'متقدم';
                advancedFields.forEach(field => field.style.display = 'none');
            }
        }

        function toggleSearchFields(button) {
            const searchForm = document.getElementById('searchForm');
            const buttonText = button.querySelector('.hide-button-text');
            const icon = button.querySelector('i');

            if (buttonText.textContent === 'اخفاء') {
                searchForm.style.display = 'none';
                buttonText.textContent = 'اظهار';
                icon.classList.remove('fa-times');
                icon.classList.add('fa-eye');
            } else {
                searchForm.style.display = 'block';
                buttonText.textContent = 'اخفاء';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-times');
            }
        }
    </script>
    
   


@endsection