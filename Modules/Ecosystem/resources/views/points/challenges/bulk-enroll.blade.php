@extends('ecosystem::master')

@section('title', 'إضافة مشاركين - ' . $challenge->name)

@section('content')
    @include('ecosystem::points.partials.points-modern-styles')

    @php
        $challengeUserTypeValue = $challenge->user_type instanceof \App\Enums\UserType
            ? $challenge->user_type->value
            : (string)$challenge->user_type;
        $branchName = $challenge->branch ? $challenge->branch->name : null;
    @endphp

    <div class="content-header row">
        <div class="content-header-left col-md-8 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إضافة مشاركين</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.index') }}">التحديات</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.show', $challenge) }}">{{ $challenge->name }}</a></li>
                            <li class="breadcrumb-item active">إضافة مشاركين</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-4 col-12">
            <a href="{{ route('ecosystem.points.challenges.show', $challenge) }}" class="pts-btn pts-btn-secondary">
                <i class="feather icon-arrow-right"></i> العودة للتحدي
            </a>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- بطاقة معلومات التحدي --}}
        <div class="card profile-card-modern mb-3">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="font-weight-bold mb-1">
                            <i class="feather icon-target text-primary"></i>
                            {{ $challenge->name }}
                        </h5>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            @if($challengeUserTypeValue === 'employee')
                                <span class="pts-badge pts-badge-warning"><i class="feather icon-user"></i> موظفين فقط</span>
                            @elseif($challengeUserTypeValue === 'client')
                                <span class="pts-badge pts-badge-info"><i class="feather icon-users"></i> عملاء فقط</span>
                            @else
                                <span class="pts-badge pts-badge-primary"><i class="feather icon-globe"></i> موظفين وعملاء</span>
                            @endif

                            @if($branchName)
                                <span class="pts-badge pts-badge-secondary"><i class="feather icon-map-pin"></i> {{ $branchName }}</span>
                            @else
                                <span class="pts-badge pts-badge-secondary"><i class="feather icon-globe"></i> جميع الفروع</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6 text-md-right mt-2 mt-md-0">
                        <span class="text-muted">المشاركون الحاليون:</span>
                        <span class="font-weight-bold text-primary" style="font-size: 1.5rem;">{{ $currentParticipantsCount }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- قيود التحدي --}}
        <div class="alert alert-info d-flex align-items-start mb-3" style="border-radius: 10px; border: 1px solid #00cfe833;">
            <i class="feather icon-info mr-2 mt-1" style="font-size: 1.2rem;"></i>
            <div>
                <strong>قيود الإضافة:</strong>
                <ul class="mb-0 mt-1" style="padding-right: 1.2rem;">
                    @if($challengeUserTypeValue === 'employee')
                        <li>يمكن إضافة <strong>موظفين</strong> فقط لهذا التحدي</li>
                    @elseif($challengeUserTypeValue === 'client')
                        <li>يمكن إضافة <strong>عملاء</strong> فقط لهذا التحدي</li>
                    @else
                        <li>يمكن إضافة <strong>موظفين وعملاء</strong> لهذا التحدي</li>
                    @endif
                    @if($branchName)
                        <li>المشاركون من فرع <strong>{{ $branchName }}</strong> فقط</li>
                    @endif
                    <li>لن يتم إضافة مشاركين مسجلين مسبقاً</li>
                </ul>
            </div>
        </div>

        {{-- نموذج الإضافة --}}
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-user-plus"></i> اختيار المشاركين</h4>
                <div>
                    <button type="button" class="pts-btn pts-btn-sm pts-btn-success" id="selectAllBtn">
                        <i class="feather icon-check-square"></i> تحديد الكل
                    </button>
                    <button type="button" class="pts-btn pts-btn-sm pts-btn-secondary" id="deselectAllBtn">
                        <i class="feather icon-square"></i> إلغاء التحديد
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if($challengeUserTypeValue === 'both')
                {{-- تبويبات نوع المستخدم --}}
                <ul class="nav nav-tabs mb-3" role="tablist" style="border-bottom: 2px solid #f0f0f0;">
                    <li class="nav-item">
                        <a class="nav-link active" id="emp-tab" data-toggle="tab" href="#employeesTab" role="tab">
                            <i class="feather icon-user"></i> الموظفون
                            <span class="badge badge-pill badge-warning ml-1">{{ $employees->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="cli-tab" data-toggle="tab" href="#clientsTab" role="tab">
                            <i class="feather icon-users"></i> العملاء
                            <span class="badge badge-pill badge-info ml-1">{{ $clients->count() }}</span>
                        </a>
                    </li>
                </ul>
                @endif

                <form action="{{ route('ecosystem.points.challenges.bulk-enroll', $challenge) }}" method="POST" id="bulkEnrollForm">
                    @csrf

                    {{-- حقل بحث --}}
                    <div class="form-group mb-3">
                        <div class="position-relative">
                            <input type="text" id="searchUsers" class="form-control" placeholder="🔍 ابحث عن اسم مشارك..."
                                   style="border-radius: 10px; padding: 0.75rem 1rem; border: 2px solid #eee;">
                        </div>
                    </div>

                    {{-- عدد المحدد --}}
                    <div class="alert alert-primary py-2 px-3 mb-3" style="border-radius: 10px; display: none;" id="selectedCount">
                        <i class="feather icon-check-circle"></i>
                        تم تحديد <strong><span id="countNum">0</span></strong> مشارك
                    </div>

                    @if($challengeUserTypeValue === 'both')
                    <div class="tab-content">
                        {{-- تبويب الموظفين --}}
                        <div class="tab-pane fade show active" id="employeesTab" role="tabpanel">
                            @if($employees->count() > 0)
                            <div class="table-responsive">
                                <table class="pts-table" id="employeesTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%;"><input type="checkbox" class="select-all-group" data-group="employee"></th>
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 35%;">الاسم</th>
                                            <th style="width: 25%;">الفرع</th>
                                            <th style="width: 15%;">القسم</th>
                                            <th style="width: 15%;">الوظيفة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($employees as $index => $emp)
                                        <tr class="user-row">
                                            <td>
                                                <input type="checkbox" name="users[]" value="employee_{{ $emp->id }}" class="user-checkbox employee-cb">
                                            </td>
                                            <td class="cell-id">{{ $index + 1 }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                         style="width: 36px; height: 36px; background: rgba(255, 159, 67, 0.12); color: #ff9f43; flex-shrink: 0;">
                                                        <i class="feather icon-user"></i>
                                                    </div>
                                                    <span class="font-weight-bold user-name">{{ $emp->full_name }}</span>
                                                </div>
                                            </td>
                                            <td>{{ $emp->branch ? $emp->branch->name : '-' }}</td>
                                            <td>{{ $emp->department ? $emp->department->name : '-' }}</td>
                                            <td>{{ $emp->job_title ? $emp->job_title->name : '-' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="pts-empty-state py-4">
                                <div class="empty-icon"><i class="feather icon-user-x"></i></div>
                                <h5>لا يوجد موظفون متاحون</h5>
                                <p class="text-muted">جميع الموظفين مسجلون بالفعل في هذا التحدي</p>
                            </div>
                            @endif
                        </div>

                        {{-- تبويب العملاء --}}
                        <div class="tab-pane fade" id="clientsTab" role="tabpanel">
                            @if($clients->count() > 0)
                            <div class="table-responsive">
                                <table class="pts-table" id="clientsTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%;"><input type="checkbox" class="select-all-group" data-group="client"></th>
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 40%;">الاسم</th>
                                            <th style="width: 25%;">الفرع</th>
                                            <th style="width: 25%;">الهاتف</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($clients as $index => $client)
                                        <tr class="user-row">
                                            <td>
                                                <input type="checkbox" name="users[]" value="client_{{ $client->id }}" class="user-checkbox client-cb">
                                            </td>
                                            <td class="cell-id">{{ $index + 1 }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                         style="width: 36px; height: 36px; background: rgba(0, 207, 232, 0.12); color: #00cfe8; flex-shrink: 0;">
                                                        <i class="feather icon-users"></i>
                                                    </div>
                                                    <span class="font-weight-bold user-name">{{ $client->full_name }}</span>
                                                </div>
                                            </td>
                                            <td>{{ $client->branch ? $client->branch->name : '-' }}</td>
                                            <td>{{ $client->mobile_number ?? '-' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="pts-empty-state py-4">
                                <div class="empty-icon"><i class="feather icon-user-x"></i></div>
                                <h5>لا يوجد عملاء متاحون</h5>
                                <p class="text-muted">جميع العملاء مسجلون بالفعل في هذا التحدي</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    @elseif($challengeUserTypeValue === 'employee')
                    {{-- موظفين فقط --}}
                    @if($employees->count() > 0)
                    <div class="table-responsive">
                        <table class="pts-table" id="employeesTable">
                            <thead>
                                <tr>
                                    <th style="width: 5%;"><input type="checkbox" class="select-all-group" data-group="employee"></th>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 35%;">الاسم</th>
                                    <th style="width: 25%;">الفرع</th>
                                    <th style="width: 15%;">القسم</th>
                                    <th style="width: 15%;">الوظيفة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($employees as $index => $emp)
                                <tr class="user-row">
                                    <td>
                                        <input type="checkbox" name="users[]" value="employee_{{ $emp->id }}" class="user-checkbox employee-cb">
                                    </td>
                                    <td class="cell-id">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                 style="width: 36px; height: 36px; background: rgba(255, 159, 67, 0.12); color: #ff9f43; flex-shrink: 0;">
                                                <i class="feather icon-user"></i>
                                            </div>
                                            <span class="font-weight-bold user-name">{{ $emp->full_name }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $emp->branch ? $emp->branch->name : '-' }}</td>
                                    <td>{{ $emp->department ? $emp->department->name : '-' }}</td>
                                    <td>{{ $emp->job_title ? $emp->job_title->name : '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="pts-empty-state py-4">
                        <div class="empty-icon"><i class="feather icon-user-x"></i></div>
                        <h5>لا يوجد موظفون متاحون</h5>
                        <p class="text-muted">جميع الموظفين مسجلون بالفعل أو لا يوجد موظفون في هذا الفرع</p>
                    </div>
                    @endif

                    @elseif($challengeUserTypeValue === 'client')
                    {{-- عملاء فقط --}}
                    @if($clients->count() > 0)
                    <div class="table-responsive">
                        <table class="pts-table" id="clientsTable">
                            <thead>
                                <tr>
                                    <th style="width: 5%;"><input type="checkbox" class="select-all-group" data-group="client"></th>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 40%;">الاسم</th>
                                    <th style="width: 25%;">الفرع</th>
                                    <th style="width: 25%;">الهاتف</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clients as $index => $client)
                                <tr class="user-row">
                                    <td>
                                        <input type="checkbox" name="users[]" value="client_{{ $client->id }}" class="user-checkbox client-cb">
                                    </td>
                                    <td class="cell-id">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                 style="width: 36px; height: 36px; background: rgba(0, 207, 232, 0.12); color: #00cfe8; flex-shrink: 0;">
                                                <i class="feather icon-users"></i>
                                            </div>
                                            <span class="font-weight-bold user-name">{{ $client->full_name }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $client->branch ? $client->branch->name : '-' }}</td>
                                    <td>{{ $client->mobile_number ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="pts-empty-state py-4">
                        <div class="empty-icon"><i class="feather icon-user-x"></i></div>
                        <h5>لا يوجد عملاء متاحون</h5>
                        <p class="text-muted">جميع العملاء مسجلون بالفعل أو لا يوجد عملاء في هذا الفرع</p>
                    </div>
                    @endif
                    @endif

                    {{-- زر الإرسال --}}
                    <div class="text-center mt-4 pt-3" style="border-top: 2px solid #f0f0f0;">
                        <button type="submit" class="pts-btn pts-btn-primary pts-btn-lg" id="submitBtn" disabled>
                            <i class="feather icon-user-plus"></i>
                            إضافة المشاركين المحددين (<span id="submitCount">0</span>)
                        </button>
                        <p class="text-muted mt-2 mb-0"><small>حدد مشاركاً واحداً على الأقل للمتابعة</small></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // تحديث العداد
    function updateCount() {
        var count = $('.user-checkbox:checked').length;
        $('#countNum, #submitCount').text(count);
        if (count > 0) {
            $('#selectedCount').fadeIn(200);
            $('#submitBtn').prop('disabled', false);
        } else {
            $('#selectedCount').fadeOut(200);
            $('#submitBtn').prop('disabled', true);
        }
    }

    // تحديد/إلغاء عند الضغط على checkbox
    $(document).on('change', '.user-checkbox', function() {
        updateCount();
    });

    // تحديد الكل (حسب المجموعة)
    $(document).on('change', '.select-all-group', function() {
        var group = $(this).data('group');
        var checked = $(this).is(':checked');
        $('.' + group + '-cb:visible').prop('checked', checked);
        updateCount();
    });

    // زر تحديد الكل
    $('#selectAllBtn').on('click', function() {
        $('.user-checkbox:visible').prop('checked', true);
        $('.select-all-group').prop('checked', true);
        updateCount();
    });

    // زر إلغاء التحديد
    $('#deselectAllBtn').on('click', function() {
        $('.user-checkbox').prop('checked', false);
        $('.select-all-group').prop('checked', false);
        updateCount();
    });

    // البحث
    $('#searchUsers').on('input', function() {
        var search = $(this).val().toLowerCase();
        $('.user-row').each(function() {
            var name = $(this).find('.user-name').text().toLowerCase();
            $(this).toggle(name.includes(search));
        });
    });

    // تأكيد الإرسال
    $('#bulkEnrollForm').on('submit', function(e) {
        e.preventDefault();
        var count = $('.user-checkbox:checked').length;

        if (count === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'لم يتم تحديد أي مشارك',
                text: 'يرجى تحديد مشارك واحد على الأقل',
            });
            return;
        }

        var form = this;
        Swal.fire({
            title: 'إضافة ' + count + ' مشارك؟',
            text: 'سيتم تسجيل المشاركين المحددين في التحدي',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            confirmButtonText: 'نعم، أضف الجميع',
            cancelButtonText: 'إلغاء',
            reverseButtons: true,
            showLoaderOnConfirm: true,
        }).then((result) => {
            if (result.isConfirmed) {
                // إظهار مؤشر التحميل
                Swal.fire({
                    title: 'جاري الإضافة...',
                    text: 'يرجى الانتظار',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                form.submit();
            }
        });
    });
});
</script>
@endpush
