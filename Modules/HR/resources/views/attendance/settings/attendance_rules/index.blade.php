@extends('hr::master')

@section('title', 'قواعد الحضور')

@section('page-actions')
    @permission('attendance_rules.create')
        <a href="{{ route('attendance-rules.create') }}" class="btn btn-primary">
            <i class="feather icon-plus"></i>
            <span class="d-none d-md-inline">إضافة قاعدة جديدة</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'قواعد الحضور',
        'icon' => 'check-square',
        'sub' => 'صيغ احتساب التأخير والخصم لكل وردية',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'قواعد الحضور' => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-attendance-rules.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه قواعدُ الحضور: متى يُعدّ الموظفُ متأخّراً، وكم يُتسامَح، وكيف يُحتسَب الوقتُ الإضافي. وهي المنطقُ الذي تُصنَّف به أيامُ الحضور.',
        'blocks' => [
            'ما تحكمه القاعدة' => [
                'مهلةُ التسامح تقرّر متى يصير التأخيرُ تأخيراً يُحاسَب عليه، وما دونها لا يُحتسَب.',
                'وحدُّ الغياب يقرّر متى يُقلَب اليومُ من «متأخّر» إلى «غائب» — والفرقُ بينهما حسمُ يومٍ كامل.',
                'والقواعدُ تُسنَد إلى ورديّاتٍ بعينها، فما يسري على ورديّةٍ لا يلزم أن يسري على غيرها.',
            ],
            'ما يترتّب على التغيير' => [
                'الأيامُ التي تُحتسَب بعد التغيير تتبعه؛ والمحسوبةُ سلفاً تبقى على القاعدة القديمة حتى يُعاد احتسابُها.',
                'ومن ثمّ فتغييرُ قاعدةٍ في منتصف شهرٍ يجعل نصفَه محسوباً بغير ما حُسب به نصفُه الآخر.',
            ],
        ],
        'facts' => [
            'القاعدةُ تفرّق بين «متأخّر» و«غائب»',
            'التغييرُ يسري على ما يُحتسَب بعده',
        ],
    ])

    @php
        $hasFilters = collect(request()->only(['keywords', 'status', 'shift']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
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
            <form id="searchForm" action="{{ route('attendance-rules.index') }}" method="GET">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="keywords">القاعدة</label>
                            <input type="text" id="keywords" name="keywords" class="form-control"
                                placeholder="ابحث بالاسم أو الوصف…" value="{{ request('keywords') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">الكل</option>
                                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>نشط</option>
                                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>غير نشط</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="shift">الوردية</label>
                            <select class="form-control" id="shift" name="shift">
                                <option value="">جميع الورديات</option>
                                @foreach ($shifts as $shift)
                                    <option value="{{ $shift->id }}" {{ (string) request('shift') === (string) $shift->id ? 'selected' : '' }}>{{ $shift->name }}</option>
                                @endforeach
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
        @include('hr::attendance.settings.attendance_rules.table-content', compact('attendanceRules'))
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        /*
            كانت performSearch تقرأ حقولاً لا وجود لها (#searchInput…) وترسل POST
            إلى مسارٍ GET، وكانت محصورةً داخل ready فلا يراها toggleStatus/deleteRule.
            الآن: الحقول الفعلية، GET، ومعرَّفةٌ على window.
        */
        function performSearch() {
            const searchBtn = $('.search-text');
            const loadingBtn = $('.search-loading');
            searchBtn.addClass('d-none');
            loadingBtn.removeClass('d-none');

            const formData = {
                keywords: $('#keywords').val(),
                status: $('#status').val(),
                shift: $('#shift').val(),
                per_page: $('#perPageSelect').val() || '{{ (int) request('per_page', 10) }}'
            };

            $.ajax({
                url: '{{ route('attendance-rules.search') }}',
                method: 'GET',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $('#tableContainer').html(response.html);
                        updateUrl(formData);
                    } else {
                        Swal.fire('خطأ!', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('خطأ!', 'حدث خطأ أثناء البحث', 'error');
                },
                complete: function() {
                    searchBtn.removeClass('d-none');
                    loadingBtn.addClass('d-none');
                }
            });
        }
        window.performSearch = performSearch;

        // تحديث URL
        function updateUrl(formData) {
            const newUrl = new URL(window.location);
            newUrl.searchParams.delete('page');
            Object.keys(formData).forEach(key => {
                if (formData[key]) {
                    newUrl.searchParams.set(key, formData[key]);
                } else {
                    newUrl.searchParams.delete(key);
                }
            });
            window.history.pushState({}, '', newUrl);
        }

        $(document).ready(function() {
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                performSearch();
            });

            // البحث التلقائي أثناء الكتابة
            $('#keywords').on('input', function() {
                clearTimeout(window.searchTimeout);
                window.searchTimeout = setTimeout(performSearch, 500);
            });

            $('#status, #shift').on('change', performSearch);

            $('#clearFilter').on('click', function() {
                $('#keywords').val('');
                $('#status').val('');
                $('#shift').val('');
                performSearch();
            });
        });

        // تفعيل/إلغاء تفعيل القاعدة
        function toggleStatus(ruleId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const statusText = newStatus === 'active' ? 'تفعيل' : 'تعطيل';

            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: `سيتم ${statusText} قاعدة الحضور`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `نعم، ${statusText}`,
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('attendance-rules.toggle-status', ':id') }}".replace(':id', ruleId),
                        method: 'PATCH',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم بنجاح!',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                performSearch();
                            } else {
                                Swal.fire('خطأ!', response.message || 'حدث خطأ غير معروف', 'error');
                            }
                        },
                        error: function(xhr) {
                            let errorMessage = 'حدث خطأ أثناء تغيير الحالة';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            Swal.fire('خطأ!', errorMessage, 'error');
                        }
                    });
                }
            });
        }

        function deleteRule(ruleId, ruleName) {
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: `سيتم حذف قاعدة الحضور "${ruleName}" نهائياً`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('attendance-rules.destroy', ':id') }}".replace(':id', ruleId),
                        method: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم الحذف!',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                performSearch();
                            } else {
                                Swal.fire('خطأ!', response.message || 'فشل في حذف القاعدة', 'error');
                            }
                        },
                        error: function(xhr) {
                            let errorMessage = 'حدث خطأ أثناء الحذف';
                            if (xhr.responseJSON) {
                                if (xhr.responseJSON.message) {
                                    errorMessage = xhr.responseJSON.message;
                                } else if (xhr.responseJSON.error) {
                                    errorMessage = xhr.responseJSON.error;
                                }
                            } else if (xhr.status === 404) {
                                errorMessage = 'القاعدة غير موجودة';
                            } else if (xhr.status === 403) {
                                errorMessage = 'ليس لديك صلاحية لحذف هذه القاعدة';
                            }
                            Swal.fire('خطأ!', errorMessage, 'error');
                        }
                    });
                }
            });
        }
    </script>
@endsection
