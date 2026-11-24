@extends('ecosystem::master')

@section('title', 'إنشاء فريق جديد')

@section('content')
    @include('ecosystem::points.partials.points-modern-styles')

    <div class="content-header row">
        <div class="content-header-left col-md-8 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إنشاء فريق جديد</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.teams.index') }}">إدارة الفرق</a></li>
                            <li class="breadcrumb-item active">إنشاء فريق</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-4 col-12">
            <div class="pts-btn-group" style="justify-content: flex-end;">
                <a href="{{ route('ecosystem.points.challenges.teams.index') }}" class="pts-btn pts-btn-secondary">
                    <i class="feather icon-arrow-right"></i> رجوع
                </a>
                <button type="submit" form="teamForm" class="pts-btn pts-btn-primary">
                    <i class="feather icon-save"></i> حفظ الفريق
                </button>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.error')

        <form action="{{ route('ecosystem.points.challenges.teams.store') }}" method="POST" id="teamForm">
            @csrf

            <!-- بطاقة معلومات الفريق -->
            <div class="card profile-card-modern mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="employee-details">
                                <div class="detail-item py-2 border-bottom">
                                    <label for="challenge_id" class="font-weight-bold mb-1">
                                        <i class="feather icon-target mr-1 text-primary"></i> التحدي <span class="text-danger">*</span>
                                    </label>
                                    <select name="challenge_id" id="challenge_id" class="form-control @error('challenge_id') is-invalid @enderror" required>
                                        <option value="">-- اختر التحدي --</option>
                                        @foreach($challenges as $challenge)
                                            <option value="{{ $challenge->id }}" {{ old('challenge_id') == $challenge->id ? 'selected' : '' }}>
                                                {{ $challenge->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('challenge_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">يظهر فقط تحديات الفرق النشطة</small>
                                </div>

                                <div class="detail-item py-2 border-bottom">
                                    <label for="name" class="font-weight-bold mb-1">
                                        <i class="feather icon-flag mr-1 text-primary"></i> اسم الفريق <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="name" id="name"
                                           class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name') }}" required placeholder="مثال: فريق النجوم">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="detail-item py-2 border-bottom">
                                    <label for="user_type" class="font-weight-bold mb-1">
                                        <i class="feather icon-tag mr-1 text-primary"></i> نوع الفريق <span class="text-danger">*</span>
                                    </label>
                                    <select name="user_type" id="user_type" class="form-control @error('user_type') is-invalid @enderror" required>
                                        <option value="employee" {{ old('user_type', 'employee') === 'employee' ? 'selected' : '' }}>فريق موظفين</option>
                                        <option value="client" {{ old('user_type') === 'client' ? 'selected' : '' }}>فريق عملاء</option>
                                    </select>
                                    @error('user_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="employee-details">
                                <!-- قائد الفريق -->
                                <div class="detail-item py-2 border-bottom" id="leaderSection">
                                    <label for="leader_employee_id" class="font-weight-bold mb-1">
                                        <i class="feather icon-user mr-1 text-warning"></i> قائد الفريق
                                    </label>
                                    <select name="leader_employee_id" id="leader_employee_id" class="form-control @error('leader_employee_id') is-invalid @enderror">
                                        <option value="">بدون قائد</option>
                                        @foreach($employees as $emp)
                                            <option value="{{ $emp['id'] }}" {{ old('leader_employee_id') == $emp['id'] ? 'selected' : '' }}>{{ $emp['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('leader_employee_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">اختياري - متاح فقط لفرق الموظفين</small>
                                </div>

                                <!-- إرشادات -->
                                <div class="detail-item py-2">
                                    <div class="alert alert-light-info mb-0" style="border-radius: 10px; border: 1px solid #00cfe833; background: #00cfe811; padding: 0.75rem;">
                                        <ul class="list-unstyled mb-0" style="font-size: 0.85rem;">
                                            <li class="mb-1"><i class="feather icon-check text-success mr-1"></i> فريق الموظفين يقبل موظفين فقط والعكس</li>
                                            <li class="mb-1"><i class="feather icon-check text-success mr-1"></i> المستخدم لا يكون في أكثر من فريق بنفس التحدي</li>
                                            <li class="mb-1"><i class="feather icon-check text-success mr-1"></i> القائد متاح فقط لفرق الموظفين</li>
                                            <li><i class="feather icon-alert-circle text-warning mr-1"></i> لا يمكن تغيير النوع بعد الإنشاء</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- جدول أعضاء الفريق -->
            <div class="card table-card-modern">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0"><i class="feather icon-users"></i> أعضاء الفريق</h4>
                    <div>
                        <span class="badge-count mr-1" id="membersCountBadge">0 عضو</span>
                    </div>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 45%;">العضو</th>
                                    <th style="width: 20%;">النوع</th>
                                    <th class="text-center" style="width: 15%;">الحالة</th>
                                    <th class="text-center" style="width: 15%;">إجراء</th>
                                </tr>
                            </thead>
                            <tbody id="selectedMembersTable">
                                <tr id="emptyRow">
                                    <td colspan="5" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-user-plus" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لم يتم اختيار أعضاء بعد</p>
                                            <small class="text-muted">اختر التحدي ثم أضف أعضاء من القائمة أدناه</small>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Hidden inputs -->
                <div id="membersHiddenInputs"></div>
            </div>

            <!-- المستخدمون المتاحون -->
            <div class="card table-card-modern">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="feather icon-list"></i>
                        <span id="panelTitle">المستخدمون المتاحون</span>
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
                                    <td colspan="4" class="text-center py-4">
                                        <i class="feather icon-alert-circle" style="font-size: 2rem; color: #b8c2cc;"></i>
                                        <p class="mt-2 mb-0 text-muted">اختر التحدي أولاً لتحميل المستخدمين المتاحين</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </form>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let selectedMembers = [];
    let allUsers = [];
    let currentUserType = $('#user_type').val() || 'employee';
    let takenEmployees = @json($takenEmployees);
    let allLeaderOptions = $('#leader_employee_id option').clone();
    let challengeUserTypes = @json($challengeUserTypes);

    // إعداد أولي
    initPage();

    function initPage() {
        updateLeaderVisibility();
        updatePanelTitle();

        // إذا كان التحدي محدداً من البداية (old values)
        if ($('#challenge_id').val()) {
            updateUserTypeOptions();
            filterLeaderOptions();
            loadAvailableUsers();
        }
    }

    // تغيير نوع الفريق
    $('#user_type').on('change', function() {
        currentUserType = $(this).val();

        // إعادة ضبط الأعضاء
        selectedMembers = [];
        updateSelectedTable();

        // إظهار/إخفاء القائد
        updateLeaderVisibility();

        // تحميل المستخدمين
        loadAvailableUsers();
        updatePanelTitle();
    });

    // تغيير التحدي
    $('#challenge_id').on('change', function() {
        selectedMembers = [];
        updateSelectedTable();
        updateUserTypeOptions();
        loadAvailableUsers();
        filterLeaderOptions();
    });

    // تقييد نوع الفريق حسب نوع التحدي
    function updateUserTypeOptions() {
        var challengeId = $('#challenge_id').val();
        var $userType = $('#user_type');

        if (!challengeId || !challengeUserTypes[challengeId]) {
            // إظهار الكل
            $userType.find('option').show().prop('disabled', false);
            return;
        }

        var cType = challengeUserTypes[challengeId];

        if (cType === 'employee') {
            $userType.val('employee');
            $userType.find('option[value="client"]').hide().prop('disabled', true);
            $userType.find('option[value="employee"]').show().prop('disabled', false);
        } else if (cType === 'client') {
            $userType.val('client');
            $userType.find('option[value="employee"]').hide().prop('disabled', true);
            $userType.find('option[value="client"]').show().prop('disabled', false);
        } else {
            // both
            $userType.find('option').show().prop('disabled', false);
        }

        currentUserType = $userType.val();
        updateLeaderVisibility();
        updatePanelTitle();
    }

    function updateLeaderVisibility() {
        if (currentUserType === 'employee') {
            $('#leaderSection').slideDown(300);
        } else {
            $('#leaderSection').slideUp(300);
            $('#leader_employee_id').val('');
        }
    }

    function updatePanelTitle() {
        var label = currentUserType === 'employee' ? 'الموظفون المتاحون' : 'العملاء المتاحون';
        $('#panelTitle').text(label);
    }

    // فلترة القادة حسب التحدي المختار
    function filterLeaderOptions() {
        var challengeId = $('#challenge_id').val();
        var taken = challengeId && takenEmployees[challengeId] ? takenEmployees[challengeId] : [];
        var currentVal = $('#leader_employee_id').val();

        var $select = $('#leader_employee_id');
        $select.empty();

        allLeaderOptions.each(function() {
            var val = $(this).val();
            if (!val || taken.indexOf(parseInt(val)) === -1) {
                $select.append($(this).clone());
            }
        });

        // إعادة القيمة المحددة إذا لا تزال متاحة
        if ($select.find('option[value="' + currentVal + '"]').length) {
            $select.val(currentVal);
        }
    }

    // تحميل المستخدمين المتاحين
    function loadAvailableUsers() {
        var challengeId = $('#challenge_id').val();

        if (!challengeId) {
            $('#availableUsersList').html(
                '<tr><td colspan="4" class="text-center py-4">' +
                '<i class="feather icon-alert-circle" style="font-size: 2rem; color: #b8c2cc;"></i>' +
                '<p class="mt-2 mb-0 text-muted">اختر التحدي أولاً لتحميل المستخدمين المتاحين</p>' +
                '</td></tr>'
            );
            return;
        }

        $('#availableUsersList').html(
            '<tr><td colspan="4" class="text-center py-3">' +
            '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>' +
            '<p class="mt-2 mb-0 text-muted">جاري تحميل المستخدمين...</p>' +
            '</td></tr>'
        );

        $.ajax({
            url: '{{ route("ecosystem.points.challenges.teams.get-available-users") }}',
            data: { user_type: currentUserType, challenge_id: challengeId },
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

    // عرض قائمة المتاحين (جدول)
    function renderUsersList(users) {
        var searchTerm = ($('#userSearchInput').val() || '').toLowerCase();
        var filtered = users.filter(function(u) { return u.name.toLowerCase().indexOf(searchTerm) > -1; });

        if (filtered.length === 0) {
            $('#availableUsersList').html(
                '<tr><td colspan="4" class="text-center py-3 text-muted">' +
                '<i class="feather icon-inbox" style="font-size: 1.5rem;"></i>' +
                '<p class="mt-1 mb-0">لا يوجد مستخدمون متاحون</p>' +
                '</td></tr>'
            );
            return;
        }

        var html = '';
        var iconClass = currentUserType === 'employee' ? 'icon-briefcase' : 'icon-shopping-bag';
        var iconColor = currentUserType === 'employee' ? '#ff9f43' : '#00cfe8';
        var badgeClass = currentUserType === 'employee' ? 'badge-light-warning' : 'badge-light-info';
        var typeLabel = currentUserType === 'employee' ? 'موظف' : 'عميل';

        filtered.forEach(function(user, idx) {
            var isSelected = selectedMembers.some(function(m) { return m.id === user.id; });
            var initials = user.name.split(' ').map(function(w) { return w[0]; }).join('').substring(0, 2);

            html += '<tr data-id="' + user.id + '" data-name="' + user.name + '">';
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
            if (isSelected) {
                html += '<button type="button" class="btn btn-sm btn-outline-danger remove-user-btn" data-id="' + user.id + '">';
                html += '  <i class="feather icon-user-x"></i> إزالة';
                html += '</button>';
            } else {
                html += '<button type="button" class="btn btn-sm btn-outline-success add-user-btn" data-id="' + user.id + '" data-name="' + user.name + '">';
                html += '  <i class="feather icon-user-plus"></i> إضافة';
                html += '</button>';
            }
            html += '</td>';
            html += '</tr>';
        });

        $('#availableUsersList').html(html);
    }

    // إضافة عضو
    $(document).on('click', '.add-user-btn', function(e) {
        e.preventDefault();
        var userId = $(this).data('id');
        var userName = $(this).data('name');

        if (!selectedMembers.some(function(m) { return m.id === userId; })) {
            selectedMembers.push({ id: userId, name: userName });
            updateSelectedTable();
            renderUsersList(allUsers);
        }
    });

    // إزالة عضو (من جدول المتاحين)
    $(document).on('click', '.remove-user-btn', function(e) {
        e.preventDefault();
        var userId = $(this).data('id');
        selectedMembers = selectedMembers.filter(function(m) { return m.id !== userId; });
        updateSelectedTable();
        renderUsersList(allUsers);
    });

    // إزالة عضو (من جدول المختارين)
    $(document).on('click', '.remove-selected-btn', function(e) {
        e.preventDefault();
        var userId = parseInt($(this).data('id'));
        selectedMembers = selectedMembers.filter(function(m) { return m.id !== userId; });
        updateSelectedTable();
        renderUsersList(allUsers);
    });

    // تحديث جدول الأعضاء المختارين
    function updateSelectedTable() {
        var $table = $('#selectedMembersTable');
        var $hidden = $('#membersHiddenInputs');

        if (selectedMembers.length === 0) {
            $table.html(
                '<tr id="emptyRow"><td colspan="5" class="text-center py-4">' +
                '<div class="empty-state">' +
                '<i class="feather icon-user-plus" style="font-size: 3rem; color: #b8c2cc;"></i>' +
                '<p class="mt-2 text-muted">لم يتم اختيار أعضاء بعد</p>' +
                '<small class="text-muted">اختر التحدي ثم أضف أعضاء من القائمة أدناه</small>' +
                '</div></td></tr>'
            );
            $hidden.html('');
            $('#membersCountBadge').text('0 عضو');
            return;
        }

        var iconColor = currentUserType === 'employee' ? '#ff9f43' : '#00cfe8';
        var badgeClass = currentUserType === 'employee' ? 'badge-light-warning' : 'badge-light-info';
        var typeLabel = currentUserType === 'employee' ? 'موظف' : 'عميل';

        var html = '';
        var hiddenHtml = '';

        selectedMembers.forEach(function(member, idx) {
            var initials = member.name.split(' ').map(function(w) { return w[0]; }).join('').substring(0, 2);

            html += '<tr>';
            html += '<td>' + (idx + 1) + '</td>';
            html += '<td>';
            html += '  <div class="d-flex align-items-center">';
            html += '    <div class="rounded-circle d-flex align-items-center justify-content-center ml-2" ';
            html += '         style="width: 32px; height: 32px; background: ' + iconColor + '22; color: ' + iconColor + '; flex-shrink: 0; font-weight: 600; font-size: 0.8rem;">';
            html += '      ' + initials;
            html += '    </div>';
            html += '    <strong>' + member.name + '</strong>';
            html += '  </div>';
            html += '</td>';
            html += '<td><span class="badge ' + badgeClass + '">' + typeLabel + '</span></td>';
            html += '<td class="text-center"><span class="badge-modern inactive"><i class="feather icon-clock"></i> جديد</span></td>';
            html += '<td class="text-center">';
            html += '  <button type="button" class="btn btn-sm btn-outline-danger remove-selected-btn" data-id="' + member.id + '" title="إزالة">';
            html += '    <i class="feather icon-user-x"></i>';
            html += '  </button>';
            html += '</td>';
            html += '</tr>';

            hiddenHtml += '<input type="hidden" name="members[]" value="' + member.id + '">';
        });

        $table.html(html);
        $hidden.html(hiddenHtml);
        $('#membersCountBadge').text(selectedMembers.length + ' عضو');
    }

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
