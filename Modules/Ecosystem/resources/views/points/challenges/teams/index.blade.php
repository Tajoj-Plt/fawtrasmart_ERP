@extends('ecosystem::master')

@section('title', 'إدارة الفرق')

@section('content')
    @include('ecosystem::points.partials.points-modern-styles')

    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إدارة الفرق</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.index') }}">التحديات</a></li>
                            <li class="breadcrumb-item active">إدارة الفرق</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.challenges.teams.create') }}" class="btn btn-primary waves-effect waves-light">
                <i class="feather icon-plus"></i> إنشاء فريق جديد
            </a>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- قسم الفلاتر -->
        <div class="card filter-card no-print">
            <div class="card-header">
                <h4 class="card-title"><i class="fa fa-filter"></i> خيارات البحث</h4>
                <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('ecosystem.points.challenges.teams.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>بحث</label>
                                    <input type="text" name="search" class="form-control" placeholder="اسم الفريق..." value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>التحدي</label>
                                    <select name="challenge_id" class="form-control">
                                        <option value="">جميع التحديات</option>
                                        @foreach($challenges as $challenge)
                                            <option value="{{ $challenge->id }}" {{ request('challenge_id') == $challenge->id ? 'selected' : '' }}>
                                                {{ $challenge->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>نوع الفريق</label>
                                    <select name="user_type" class="form-control">
                                        <option value="">جميع الأنواع</option>
                                        <option value="employee" {{ request('user_type') == 'employee' ? 'selected' : '' }}>فرق موظفين</option>
                                        <option value="client" {{ request('user_type') == 'client' ? 'selected' : '' }}>فرق عملاء</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>حالة الإكمال</label>
                                    <select name="is_completed" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="1" {{ request('is_completed') === '1' ? 'selected' : '' }}>مكتمل</option>
                                        <option value="0" {{ request('is_completed') === '0' ? 'selected' : '' }}>غير مكتمل</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa fa-search"></i> بحث
                                        </button>
                                        <a href="{{ route('ecosystem.points.challenges.teams.index') }}" class="btn btn-outline-secondary">
                                            <i class="feather icon-refresh-cw"></i> إعادة تعيين
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- بطاقات الإحصائيات -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-flag"></i></div>
                <div class="stat-label">إجمالي الفرق</div>
                <div class="stat-value">{{ number_format($stats['total_teams']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-layers"></i>
                    <span>فريق</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-users"></i></div>
                <div class="stat-label">إجمالي الأعضاء</div>
                <div class="stat-value">{{ number_format($stats['total_members']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-user-check"></i>
                    <span>عضو</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-target"></i></div>
                <div class="stat-label">التحديات الجماعية</div>
                <div class="stat-value">{{ number_format($stats['team_challenges']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-award"></i>
                    <span>تحدي</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">فرق مكتملة</div>
                <div class="stat-value">{{ number_format($stats['completed_teams']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-star"></i>
                    <span>فريق</span>
                </div>
            </div>
        </div>

        <!-- جدول الفرق -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-flag"></i> قائمة الفرق</h4>
                <div>
                    <span class="badge-count mr-1">{{ $teams->total() }} فريق</span>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 20%;">اسم الفريق</th>
                                <th style="width: 15%;">التحدي</th>
                                <th style="width: 10%;">النوع</th>
                                <th style="width: 15%;">القائد</th>
                                <th style="width: 10%;">الأعضاء</th>
                                <th style="width: 10%;">التقدم</th>
                                <th style="width: 5%;">الحالة</th>
                                <th class="text-center" style="width: 10%;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teams as $team)
                                <tr>
                                    <td>{{ $loop->iteration + ($teams->currentPage() - 1) * $teams->perPage() }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                 style="width: 36px; height: 36px; background: linear-gradient(135deg, #7367f0, #ce9ffc); color: #fff; font-size: 0.85rem; flex-shrink: 0;">
                                                <i class="feather icon-flag"></i>
                                            </div>
                                            <div>
                                                <strong>{{ $team->name }}</strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($team->challenge)
                                            <a href="{{ route('ecosystem.points.challenges.show', $team->challenge) }}" class="text-primary">
                                                {{ $team->challenge->name }}
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($team->user_type === 'employee')
                                            <span class="badge badge-light-warning">
                                                <i class="feather icon-briefcase"></i> موظفين
                                            </span>
                                        @else
                                            <span class="badge badge-light-info">
                                                <i class="feather icon-shopping-bag"></i> عملاء
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($team->leader)
                                            <span class="badge badge-light-primary">
                                                <i class="feather icon-user"></i>
                                                {{ $team->leader->full_name ?? $team->leader->first_name }}
                                            </span>
                                        @else
                                            <span class="text-muted">غير محدد</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info badge-pill">
                                            {{ $team->members_count }} عضو
                                        </span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold text-primary">{{ number_format($team->total_progress) }}</span>
                                    </td>
                                    <td>
                                        @if($team->is_completed)
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
                                        <div class="actions-dropdown dropdown">
                                            <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.challenges.teams.show', $team) }}">
                                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.challenges.teams.edit', $team) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('ecosystem.points.challenges.teams.destroy', $team) }}" method="POST" class="delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item delete-item text-danger">
                                                        <i class="feather icon-trash-2"></i> حذف
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا توجد فرق لعرضها</p>
                                            <a href="{{ route('ecosystem.points.challenges.teams.create') }}" class="btn btn-primary mt-2">
                                                <i class="feather icon-plus"></i> إنشاء فريق جديد
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $teams, 'label' => 'فريق'])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // تأكيد الحذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;
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
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@endsection
