@extends('hr::master')

@section('title', 'الماكينات')

@section('page-actions')
    @permission('attendance_devices.create')
        <a href="{{ route('machines.create') }}" class="btn btn-primary">
            <i class="feather icon-plus"></i>
            <span class="d-none d-md-inline">إضافة ماكينة جديدة</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'آلات البصمة والحضور',
        'icon' => 'monitor',
        'sub' => 'إدارة ماكينات تسجيل الحضور المرتبطة بالنظام',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'آلات البصمة' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'devices'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-attendance-machines.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه أجهزةُ البصمة المسجَّلة. والجهازُ مصدرٌ للسجلّات الخام — يُعرَّف هنا كي يُعرَف من أين جاءت البصمة.',
        'blocks' => [
            'ما يترتّب على التعريف' => [
                'البصماتُ الواردةُ تُنسَب إلى جهازها، فيُعرَف مصدرُ كلّ سجلٍّ عند الاعتراض.',
                'وجهازٌ غيرُ معرَّفٍ لا تُقبَل بصماتُه أو تصل بلا نسبةٍ إلى مصدر.',
            ],
            'ما لا يفعله الجهاز' => [
                'لا يقرّر حالةَ اليوم: يرسل الوقائعَ، وتصنيفُها يقع بقواعد الحضور والورديّات.',
                'وتعطيلُ جهازٍ لا يحذف ما ورد منه سابقاً — يوقف الوارد بعده.',
            ],
        ],
        'facts' => [
            'الجهازُ مصدرُ بصماتٍ لا مصدرُ حالات',
            'تعطيلُه لا يحذف ما ورد منه',
        ],
    ])

    @php
        $hasFilters = collect(request()->only(['name', 'status', 'machine_type']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            {{-- البحث يجري بـ AJAX (performSearch) ويستبدل محتوى #tableContainer --}}
            <form id="searchForm" action="{{ route('machines.index') }}" method="GET">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">الماكينة</label>
                            <input type="text" id="name" name="name" class="form-control"
                                placeholder="ابحث باسم الماكينة أو الرقم التسلسلي…" value="{{ request('name') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">الكل</option>
                                <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>نشط</option>
                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>غير نشط</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="machine_type">النوع</label>
                            <select class="form-control" id="machine_type" name="machine_type">
                                <option value="">كل الأنواع</option>
                                <option value="zkteco" {{ request('machine_type') == 'zkteco' ? 'selected' : '' }}>ZkTeco</option>
                                <option value="hikvision" {{ request('machine_type') == 'hikvision' ? 'selected' : '' }}>Hikvision</option>
                                <option value="suprema" {{ request('machine_type') == 'suprema' ? 'selected' : '' }}>Suprema</option>
                                <option value="other" {{ request('machine_type') == 'other' ? 'selected' : '' }}>أخرى</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <button type="button" id="clearFilter" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <span class="search-text"><i class="feather icon-search"></i> بحث</span>
                        <span class="search-loading d-none"><i class="fa fa-spinner fa-spin"></i> جاري البحث…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card stk-table-card" id="tableContainer">
        @include('hr::attendance.settings.machines.table-content', compact('machines'))
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush


@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // البحث بـ AJAX
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                performSearch();
            });

            // البحث التلقائي أثناء الكتابة
            $('#name').on('input', function() {
                clearTimeout(window.searchTimeout);
                window.searchTimeout = setTimeout(function() {
                    performSearch();
                }, 500);
            });

            // البحث عند تغيير الفلاتر
            $('#status, #machine_type').on('change', function() {
                performSearch();
            });

            // مسح الفلتر
            $('#clearFilter').on('click', function() {
                $('#name').val('');
                $('#status').val('');
                $('#machine_type').val('');
                performSearch();
            });

            function performSearch() {
                const searchBtn = $('.search-text');
                const loadingBtn = $('.search-loading');

                // إظهار مؤشر التحميل
                searchBtn.addClass('d-none');
                loadingBtn.removeClass('d-none');

                const formData = {
                    name: $('#name').val(),
                    status: $('#status').val(),
                    machine_type: $('#machine_type').val(),
                    _token: $('input[name="_token"]').val()
                };

                $.ajax({
                    url: '{{ route('machines.search') }}',
                    method: 'GET',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('#tableContainer').html(response.html);

                            // إظهار عدد النتائج
                            const countText = response.count > 0 ?
                                `تم العثور على ${response.count} نتيجة` :
                                'لم يتم العثور على نتائج';

                            showAlert(countText, 'info', 2000);

                            // تحديث URL بدون إعادة تحميل الصفحة
                            updateUrl(formData);
                        } else {
                            showAlert('حدث خطأ أثناء البحث', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert('حدث خطأ في الاتصال', 'error');
                        console.error('Search error:', error);
                    },
                    complete: function() {
                        // إخفاء مؤشر التحميل
                        searchBtn.removeClass('d-none');
                        loadingBtn.addClass('d-none');
                    }
                });
            }

            // تحديث URL
            function updateUrl(formData) {
                const newUrl = new URL(window.location);
                Object.keys(formData).forEach(key => {
                    if (formData[key] && key !== '_token') {
                        newUrl.searchParams.set(key, formData[key]);
                    } else if (key !== '_token') {
                        newUrl.searchParams.delete(key);
                    }
                });
                window.history.pushState({}, '', newUrl);
            }

            // إظهار التنبيهات
            function showAlert(message, type = 'success', duration = 3000) {
                let icon = type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success');

                Swal.fire({
                    icon: icon,
                    title: message,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: duration,
                    timerProgressBar: true
                });
            }

            // جعل performSearch متاحة عالمياً
            window.performSearch = performSearch;

            // Display success/error messages from session
            @if (session('success'))
                showAlert('{{ session('success') }}', 'success');
            @endif

            @if (session('error'))
                showAlert('{{ session('error') }}', 'error');
            @endif

            @if (session('warning'))
                showAlert('{{ session('warning') }}', 'warning');
            @endif
        });

        // تفعيل/إلغاء تفعيل الماكينة
        function toggleStatus(machineId) {
            const toggle = document.querySelector(`[data-machine-id="${machineId}"]`);
            const isChecked = toggle.checked;
            const previousState = !isChecked;

            // تعطيل التبديل أثناء المعالجة
            toggle.disabled = true;

            fetch("{{ route('machines.toggle-status', ':id') }}".replace(':id', machineId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                toggle.disabled = false;

                if (data.success) {
                    Swal.fire({
                        title: 'تم بنجاح!',
                        text: data.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // تحديث الجدول فوراً
                    window.performSearch();
                } else {
                    // إرجاع الحالة السابقة
                    toggle.checked = previousState;
                    Swal.fire({
                        title: 'خطأ!',
                        text: data.message || 'حدث خطأ أثناء تغيير الحالة',
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                toggle.disabled = false;
                toggle.checked = previousState;

                Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
            });
        }

        // حذف الماكينة
        function deleteMachine(machineId, machineName) {
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: `سيتم حذف الماكينة "${machineName}" نهائياً ولا يمكن التراجع عن هذا الإجراء`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'جاري الحذف...',
                        text: 'يرجى الانتظار',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch("{{ route('machines.destroy', ':id') }}".replace(':id', machineId), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'تم الحذف!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'موافق'
                            });

                            // تحديث الجدول فوراً
                            window.performSearch();
                        } else {
                            Swal.fire({
                                title: 'خطأ!',
                                text: data.message || 'حدث خطأ أثناء الحذف',
                                icon: 'error',
                                confirmButtonText: 'موافق'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error details:', error);
                        Swal.fire({
                            title: 'خطأ!',
                            text: 'حدث خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.',
                            icon: 'error',
                            confirmButtonText: 'موافق'
                        });
                    });
                }
            });
        }
    </script>
@endsection