@extends('ecosystem::master')

@section('title', 'تعديل الفريق - ' . $team->name)

@section('content')
    @include('ecosystem::points.partials.points-modern-styles')

    <div class="content-header row">
        <div class="content-header-left col-md-8 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تعديل الفريق</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.teams.index') }}">إدارة الفرق</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.teams.show', $team) }}">{{ $team->name }}</a></li>
                            <li class="breadcrumb-item active">تعديل</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-4 col-12">
            <div class="pts-btn-group" style="justify-content: flex-end;">
                <a href="{{ route('ecosystem.points.challenges.teams.show', $team) }}" class="pts-btn pts-btn-secondary">
                    <i class="feather icon-arrow-right"></i> رجوع
                </a>
                <button type="submit" form="teamForm" class="pts-btn pts-btn-primary">
                    <i class="feather icon-save"></i> حفظ التعديلات
                </button>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.error')

        <form action="{{ route('ecosystem.points.challenges.teams.update', $team) }}" method="POST" id="teamForm">
            @csrf
            @method('PUT')

            <!-- بطاقة معلومات الفريق -->
            <div class="card profile-card-modern mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="employee-details">
                                <div class="detail-item py-2 border-bottom">
                                    <label class="font-weight-bold mb-1">
                                        <i class="feather icon-target mr-1 text-primary"></i> التحدي
                                    </label>
                                    <input type="text" class="form-control" value="{{ $team->challenge->name ?? '-' }}" disabled style="background: #f8f9fa;">
                                    <small class="text-muted">لا يمكن تغيير التحدي بعد الإنشاء</small>
                                </div>

                                <div class="detail-item py-2 border-bottom">
                                    <label for="name" class="font-weight-bold mb-1">
                                        <i class="feather icon-flag mr-1 text-primary"></i> اسم الفريق <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="name" id="name"
                                           class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name', $team->name) }}" required placeholder="اسم الفريق">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="detail-item py-2 border-bottom">
                                    <label class="font-weight-bold mb-1">
                                        <i class="feather icon-tag mr-1 text-primary"></i> نوع الفريق
                                    </label>
                                    <div>
                                        @if($team->user_type === 'employee')
                                            <span class="badge badge-light-warning" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                                <i class="feather icon-briefcase mr-1"></i> فريق موظفين
                                            </span>
                                        @else
                                            <span class="badge badge-light-info" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                                <i class="feather icon-shopping-bag mr-1"></i> فريق عملاء
                                            </span>
                                        @endif
                                    </div>
                                    <small class="text-muted">لا يمكن تغيير نوع الفريق بعد الإنشاء</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="employee-details">
                                @if($team->isEmployeeTeam())
                                <!-- قائد الفريق -->
                                <div class="detail-item py-2 border-bottom">
                                    <label for="leader_employee_id" class="font-weight-bold mb-1">
                                        <i class="feather icon-user mr-1 text-warning"></i> قائد الفريق
                                    </label>
                                    <select name="leader_employee_id" id="leader_employee_id" class="form-control @error('leader_employee_id') is-invalid @enderror">
                                        <option value="">بدون قائد</option>
                                        @foreach($employees as $emp)
                                            <option value="{{ $emp['id'] }}" {{ old('leader_employee_id', $team->leader_employee_id) == $emp['id'] ? 'selected' : '' }}>{{ $emp['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('leader_employee_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">يظهر الموظفون غير المسجلين في فريق آخر بنفس التحدي</small>
                                </div>
                                @endif

                                <!-- معلومات التحدي -->
                                <div class="detail-item py-2 border-bottom">
                                    <label class="font-weight-bold mb-1">
                                        <i class="feather icon-award mr-1 text-success"></i> معلومات التحدي
                                    </label>
                                    @if($team->challenge)
                                    <div style="font-size: 0.85rem;">
                                        <div class="mb-1"><span class="text-muted">الهدف:</span> <strong>{{ number_format($team->challenge->target_value) }} {{ $team->challenge->target_unit }}</strong></div>
                                        <div><span class="text-muted">المكافأة:</span> <strong class="text-success">{{ number_format($team->challenge->reward_points) }} نقطة</strong></div>
                                    </div>
                                    @endif
                                </div>

                                <!-- إرشادات -->
                                <div class="detail-item py-2">
                                    <div class="alert alert-light-info mb-0" style="border-radius: 10px; border: 1px solid #00cfe833; background: #00cfe811; padding: 0.75rem;">
                                        <ul class="list-unstyled mb-0" style="font-size: 0.85rem;">
                                            <li class="mb-1"><i class="feather icon-alert-circle text-warning mr-1"></i> لا يمكن إزالة أعضاء حصلوا على نقاط</li>
                                            <li><i class="feather icon-alert-circle text-warning mr-1"></i> لإضافة أعضاء جدد استخدم الجدول أدناه</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- أعضاء الفريق الحاليين -->
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-users"></i> أعضاء الفريق الحاليين</h4>
                <span class="badge-count">{{ $team->members->count() }} عضو</span>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 35%;">العضو</th>
                                <th style="width: 15%;">النوع</th>
                                <th style="width: 15%;">التقدم</th>
                                <th style="width: 15%;">النقاط</th>
                                <th class="text-center" style="width: 15%;">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($team->members as $index => $member)
                                @php
                                    $memberName = '';
                                    $isEmployee = false;
                                    if ($member->employee) {
                                        $memberName = $member->employee->full_name ?? $member->employee->first_name;
                                        $isEmployee = true;
                                    } elseif ($member->client) {
                                        $memberName = $member->client->full_name ?? $member->client->first_name;
                                    }
                                    $initials = collect(explode(' ', $memberName))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join('');
                                    $iconColor = $isEmployee ? '#ff9f43' : '#00cfe8';
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                 style="width: 32px; height: 32px; background: {{ $iconColor }}22; color: {{ $iconColor }}; flex-shrink: 0; font-weight: 600; font-size: 0.8rem;">
                                                {{ $initials }}
                                            </div>
                                            <strong>{{ $memberName }}</strong>
                                        </div>
                                    </td>
                                    <td>
                                        @if($isEmployee)
                                            <span class="badge badge-light-warning">موظف</span>
                                        @else
                                            <span class="badge badge-light-info">عميل</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($member->current_progress) }}</td>
                                    <td>
                                        @if($member->points_awarded)
                                            <span class="badge badge-light-success">+{{ number_format($member->points_awarded) }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if(!$member->points_awarded)
                                            <form action="{{ route('ecosystem.points.challenges.teams.remove-member', [$team, $member]) }}" method="POST" class="d-inline remove-member-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="إزالة" data-name="{{ $memberName }}">
                                                    <i class="feather icon-user-x"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted" title="لا يمكن الإزالة - حصل على نقاط"><i class="feather icon-lock"></i></span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-users" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا يوجد أعضاء في الفريق</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- إضافة أعضاء جدد -->
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="feather icon-user-plus"></i>
                    إضافة أعضاء جدد -
                    <span class="text-primary">{{ $team->user_type === 'employee' ? 'الموظفون المتاحون' : 'العملاء المتاحون' }}</span>
                </h4>
                <div class="d-flex align-items-center">
                    <div class="position-relative mr-2">
                        <input type="text" id="userSearchInput" class="form-control form-control-sm"
                               placeholder="بحث..." style="border-radius: 8px; min-width: 200px; padding-right: 2rem;">
                        <i class="feather icon-search" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); color: #b8c2cc;"></i>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="refreshUsersBtn" style="border-radius: 8px;">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 50%;">المستخدم</th>
                                <th style="width: 20%;">النوع</th>
                                <th class="text-center" style="width: 25%;">إجراء</th>
                            </tr>
                        </thead>
                        <tbody id="availableUsersList">
                            <tr>
                                <td colspan="4" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                    <p class="mt-2 mb-0 text-muted">جاري تحميل المستخدمين...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let allUsers = [];
    let teamUserType = '{{ $team->user_type }}';
    let iconColor = teamUserType === 'employee' ? '#ff9f43' : '#00cfe8';
    let badgeClass = teamUserType === 'employee' ? 'badge-light-warning' : 'badge-light-info';
    let typeLabel = teamUserType === 'employee' ? 'موظف' : 'عميل';

    // تحميل المستخدمين المتاحين
    loadAvailableUsers();

    function loadAvailableUsers() {
        $('#availableUsersList').html(
            '<tr><td colspan="4" class="text-center py-3">' +
            '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>' +
            '<p class="mt-2 mb-0 text-muted">جاري تحميل المستخدمين...</p>' +
            '</td></tr>'
        );

        $.ajax({
            url: '{{ route("ecosystem.points.challenges.teams.get-available-users") }}',
            data: { user_type: teamUserType, challenge_id: '{{ $team->challenge_id }}' },
            success: function(users) {
                allUsers = users;
                renderUsersList(users);
            },
            error: function() {
                $('#availableUsersList').html(
                    '<tr><td colspan="4" class="text-center py-3 text-danger">' +
                    '<i class="feather icon-alert-circle"></i> خطأ في تحميل البيانات' +
                    '</td></tr>'
                );
            }
        });
    }

    function renderUsersList(users) {
        var searchTerm = ($('#userSearchInput').val() || '').toLowerCase();
        var filtered = users.filter(function(u) { return u.name.toLowerCase().indexOf(searchTerm) > -1; });

        if (filtered.length === 0) {
            $('#availableUsersList').html(
                '<tr><td colspan="4" class="text-center py-3 text-muted">' +
                '<i class="feather icon-inbox" style="font-size: 1.5rem;"></i>' +
                '<p class="mt-1 mb-0">لا يوجد مستخدمون متاحون للإضافة</p>' +
                '</td></tr>'
            );
            return;
        }

        var html = '';
        filtered.forEach(function(user, idx) {
            var initials = user.name.split(' ').map(function(w) { return w[0]; }).join('').substring(0, 2);

            html += '<tr>';
            html += '<td>' + (idx + 1) + '</td>';
            html += '<td>';
            html += '  <div class="d-flex align-items-center">';
            html += '    <div class="rounded-circle d-flex align-items-center justify-content-center ml-2" ';
            html += '         style="width: 32px; height: 32px; background: ' + iconColor + '22; color: ' + iconColor + '; flex-shrink: 0; font-weight: 600; font-size: 0.8rem;">';
            html += '      ' + initials;
            html += '    </div>';
            html += '    <strong>' + user.name + '</strong>';
            html += '  </div>';
            html += '</td>';
            html += '<td><span class="badge ' + badgeClass + '">' + typeLabel + '</span></td>';
            html += '<td class="text-center">';
            html += '  <button type="button" class="btn btn-sm btn-outline-success add-member-btn" data-id="' + user.id + '" data-name="' + user.name + '">';
            html += '    <i class="feather icon-user-plus"></i> إضافة';
            html += '  </button>';
            html += '</td>';
            html += '</tr>';
        });

        $('#availableUsersList').html(html);
    }

    // إضافة عضو
    $(document).on('click', '.add-member-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var userId = $btn.data('id');
        var userName = $btn.data('name');

        Swal.fire({
            title: 'إضافة عضو؟',
            text: 'هل تريد إضافة "' + userName + '" للفريق؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'نعم، أضف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                // إنشاء فورم وإرساله
                var form = $('<form>', {
                    method: 'POST',
                    action: '{{ route("ecosystem.points.challenges.teams.add-member", $team) }}'
                });
                form.append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
                form.append($('<input>', { type: 'hidden', name: 'user_id', value: userId }));
                $('body').append(form);
                form.submit();
            }
        });
    });

    // تأكيد إزالة عضو
    $(document).on('submit', '.remove-member-form', function(e) {
        e.preventDefault();
        var form = this;
        var name = $(form).find('button').data('name');

        Swal.fire({
            title: 'إزالة العضو؟',
            text: 'هل تريد إزالة "' + name + '" من الفريق؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'نعم، أزل',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // تحديث البحث
    $('#refreshUsersBtn').on('click', function() {
        loadAvailableUsers();
    });

    $('#userSearchInput').on('input', function() {
        renderUsersList(allUsers);
    });
});
</script>
@endpush
