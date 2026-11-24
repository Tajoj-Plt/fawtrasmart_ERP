@extends('ecosystem::master')

@section('title', 'عرض الفريق - ' . $team->name)

@section('content')
    @include('ecosystem::points.partials.points-modern-styles')

    <div class="content-header row">
        <div class="content-header-left col-md-8 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">عرض الفريق</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.teams.index') }}">إدارة الفرق</a></li>
                            <li class="breadcrumb-item active">{{ $team->name }}</li>
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
                <a href="{{ route('ecosystem.points.challenges.teams.edit', $team) }}" class="pts-btn pts-btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
                <button type="button" class="pts-btn pts-btn-danger" id="deleteBtn" style="padding: 0.75rem 1rem;">
                    <i class="feather icon-trash-2"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- بطاقة بيانات الفريق الرئيسية -->
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center border-left">
                        <div class="avatar-wrapper mb-2">
                            <div class="rounded-circle shadow d-flex align-items-center justify-content-center mx-auto"
                                 style="width: 100px; height: 100px; background: linear-gradient(135deg, #7367f0 0%, #ce9ffc 100%); font-size: 2.5rem; color: #fff;">
                                <i class="feather icon-flag"></i>
                            </div>
                        </div>
                        <h4 class="mb-1 font-weight-bold">{{ $team->name }}</h4>
                        @if($team->is_completed)
                            <span class="pts-badge pts-badge-success" style="font-size: 0.9rem; padding: 0.5rem 1.2rem;">
                                <span class="pts-status-dot active"></span> مكتمل
                            </span>
                        @else
                            <span class="pts-badge pts-badge-info" style="font-size: 0.9rem; padding: 0.5rem 1.2rem;">
                                <span class="pts-status-dot active"></span> جاري
                            </span>
                        @endif
                    </div>

                    <div class="col-md-5">
                        <div class="employee-details">
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-target mr-1"></i> التحدي</span>
                                <strong>
                                    @if($team->challenge)
                                        <a href="{{ route('ecosystem.points.challenges.show', $team->challenge) }}" class="text-primary">
                                            {{ $team->challenge->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-tag mr-1"></i> نوع الفريق</span>
                                <strong>
                                    @if($team->user_type === 'employee')
                                        <span class="badge badge-light-warning">
                                            <i class="feather icon-briefcase"></i> فريق موظفين
                                        </span>
                                    @else
                                        <span class="badge badge-light-info">
                                            <i class="feather icon-shopping-bag"></i> فريق عملاء
                                        </span>
                                    @endif
                                </strong>
                            </div>
                            @if($team->isEmployeeTeam())
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-user mr-1"></i> القائد</span>
                                <strong>
                                    @if($team->leader)
                                        <span class="badge badge-light-primary">
                                            {{ $team->leader->full_name ?? $team->leader->first_name }}
                                        </span>
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </strong>
                            </div>
                            @endif
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-users mr-1"></i> عدد الأعضاء</span>
                                <strong>{{ $teamStats['total_members'] }}</strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-bar-chart-2 mr-1"></i> التقدم الكلي</span>
                                <strong class="text-primary">{{ number_format($teamStats['total_progress']) }}</strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2">
                                <span class="text-muted"><i class="feather icon-calendar mr-1"></i> تاريخ الإنشاء</span>
                                <strong>{{ $team->created_at->format('Y/m/d') }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        @if($team->rank)
                            <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #FFD70022, #FFD70011); border: 2px solid #FFD70066;">
                                <div style="font-size: 3rem;">
                                    @if($team->rank == 1) 🥇
                                    @elseif($team->rank == 2) 🥈
                                    @elseif($team->rank == 3) 🥉
                                    @else 🏅
                                    @endif
                                </div>
                                <h5 class="font-weight-bold mt-1">الترتيب #{{ $team->rank }}</h5>
                            </div>
                        @else
                            <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);">
                                <i class="feather icon-activity text-primary" style="font-size: 2rem;"></i>
                                <h6 class="mt-2 text-muted">لم يتم تحديد الترتيب بعد</h6>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- بطاقات الإحصائيات -->
        <div class="stats-row">
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-users"></i></div>
                <div class="stat-label">الأعضاء</div>
                <div class="stat-value">{{ number_format($teamStats['total_members']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-user-check"></i>
                    <span>عضو</span>
                </div>
            </div>

            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">أكملوا التحدي</div>
                <div class="stat-value">{{ number_format($teamStats['completed_members']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-percent"></i>
                    <span>{{ $teamStats['total_members'] > 0 ? round(($teamStats['completed_members'] / $teamStats['total_members']) * 100) : 0 }}%</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-activity"></i></div>
                <div class="stat-label">متوسط التقدم</div>
                <div class="stat-value">{{ number_format($teamStats['avg_progress'], 1) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-bar-chart-2"></i>
                    <span>وحدة</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-star"></i></div>
                <div class="stat-label">نقاط موزعة</div>
                <div class="stat-value">{{ number_format($teamStats['points_distributed']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-gift"></i>
                    <span>نقطة</span>
                </div>
            </div>
        </div>

        <!-- أعضاء الفريق -->
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-users"></i> أعضاء الفريق</h4>
                <div>
                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addMemberModal">
                        <i class="feather icon-user-plus"></i> إضافة عضو
                    </button>
                    <span class="badge-count mr-1">{{ $teamStats['total_members'] }} عضو</span>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 25%;">العضو</th>
                                <th style="width: 15%;">النوع</th>
                                <th style="width: 20%;">التقدم</th>
                                <th style="width: 10%;">النقاط</th>
                                <th style="width: 10%;">الحالة</th>
                                <th class="text-center" style="width: 15%;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($team->members as $index => $member)
                                @php
                                    $progressPct = $member->target_value_snapshot > 0
                                        ? min(100, round(($member->current_progress / $member->target_value_snapshot) * 100, 1))
                                        : 0;
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($member->employee)
                                                <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                     style="width: 32px; height: 32px; background: #ff9f4322; color: #ff9f43; flex-shrink: 0;">
                                                    <i class="feather icon-user" style="font-size: 0.8rem;"></i>
                                                </div>
                                                <strong>{{ $member->employee->full_name ?? $member->employee->first_name }}</strong>
                                            @elseif($member->client)
                                                <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                     style="width: 32px; height: 32px; background: #00cfe822; color: #00cfe8; flex-shrink: 0;">
                                                    <i class="feather icon-users" style="font-size: 0.8rem;"></i>
                                                </div>
                                                <strong>{{ $member->client->full_name ?? $member->client->first_name }}</strong>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($member->employee)
                                            <span class="badge badge-light-warning">موظف</span>
                                        @elseif($member->client)
                                            <span class="badge badge-light-info">عميل</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 ml-2" style="height: 8px; border-radius: 4px;">
                                                <div class="progress-bar" style="width: {{ $progressPct }}%; background: linear-gradient(90deg, #7367f0, #ce9ffc);"></div>
                                            </div>
                                            <span class="font-weight-bold" style="min-width: 80px; font-size: 0.85rem;">
                                                {{ number_format($member->current_progress) }}
                                                <small class="text-muted">({{ $progressPct }}%)</small>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        @if($member->points_awarded)
                                            <span class="badge badge-light-success">+{{ number_format($member->points_awarded) }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($member->is_completed)
                                            <span class="badge-modern active">
                                                <i class="feather icon-check-circle"></i> مكتمل
                                            </span>
                                        @else
                                            <span class="badge-modern inactive">
                                                <i class="feather icon-clock"></i> جاري
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <form action="{{ route('ecosystem.points.challenges.teams.remove-member', [$team, $member]) }}" 
                                              method="POST" class="remove-member-form d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="إزالة العضو"
                                                    {{ $member->points_awarded ? 'disabled' : '' }}>
                                                <i class="feather icon-user-x"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا يوجد أعضاء في هذا الفريق</p>
                                            <button type="button" class="btn btn-primary mt-2" data-toggle="modal" data-target="#addMemberModal">
                                                <i class="feather icon-user-plus"></i> إضافة عضو
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal إضافة عضو --}}
    <div class="modal fade" id="addMemberModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 15px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%); color: #fff; border: none;">
                    <h5 class="modal-title"><i class="feather icon-user-plus"></i> إضافة عضو جديد</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <form action="{{ route('ecosystem.points.challenges.teams.add-member', $team) }}" method="POST">
                    @csrf
                    <div class="modal-body" style="padding: 1.5rem;">
                        <div class="alert alert-info" style="border-radius: 10px;">
                            <i class="feather icon-info mr-1"></i>
                            سيتم تسجيل العضو تلقائياً في التحدي: <strong>{{ $team->challenge->name ?? '-' }}</strong>
                            <br>
                            <small>نوع الفريق:
                                @if($team->user_type === 'employee')
                                    <span class="badge badge-light-warning">فريق موظفين</span>
                                @else
                                    <span class="badge badge-light-info">فريق عملاء</span>
                                @endif
                            </small>
                        </div>

                        <div class="form-group">
                            <label>المستخدم <span class="text-danger">*</span></label>
                            <select name="user_id" id="modal_user_id" class="form-control" required style="border-radius: 8px; padding: 0.75rem;">
                                <option value="">جاري التحميل...</option>
                            </select>
                            <small class="text-muted">
                                @if($team->user_type === 'employee')
                                    يظهر فقط الموظفون غير المسجلين في فريق آخر
                                @else
                                    يظهر فقط العملاء غير المسجلين في فريق آخر
                                @endif
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #eee;">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-user-plus"></i> إضافة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // حذف الفريق
    $('#deleteBtn').on('click', function() {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا الفريق والأعضاء المرتبطين به نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("ecosystem.points.challenges.teams.destroy", $team) }}';
                var token = document.createElement('input');
                token.type = 'hidden'; token.name = '_token'; token.value = '{{ csrf_token() }}';
                var method = document.createElement('input');
                method.type = 'hidden'; method.name = '_method'; method.value = 'DELETE';
                form.appendChild(token);
                form.appendChild(method);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // تحميل المستخدمين في modal عند فتحه
    $('#addMemberModal').on('show.bs.modal', function() {
        var $userSelect = $('#modal_user_id');
        $userSelect.empty().append('<option value="">جاري التحميل...</option>');

        $.ajax({
            url: '{{ route("ecosystem.points.challenges.teams.get-available-users") }}',
            data: { user_type: '{{ $team->user_type }}', challenge_id: '{{ $team->challenge_id }}' },
            success: function(users) {
                $userSelect.empty().append('<option value="">-- اختر المستخدم --</option>');
                users.forEach(function(user) {
                    $userSelect.append('<option value="' + user.id + '">' + user.name + '</option>');
                });
                if (users.length === 0) {
                    $userSelect.empty().append('<option value="">لا يوجد مستخدمون متاحون</option>');
                }
            },
            error: function() {
                $userSelect.empty().append('<option value="">خطأ في تحميل البيانات</option>');
            }
        });
    });

    // إزالة عضو
    $('.remove-member-form').submit(function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'إزالة العضو؟',
            text: 'هل تريد إزالة هذا العضو من الفريق؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'نعم، أزل',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@endpush
