@extends('ecosystem::master')

@section('title', 'التحديات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">التحديات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">التحديات</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.challenges.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> إنشاء تحدي
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
                        <li><a data-action="collapse"><i class="feather icon-chevron-down"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('ecosystem.points.challenges.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>البحث</label>
                                    <input type="text" name="search" class="form-control" placeholder="ابحث باسم التحدي..." value="{{ request('search') }}">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <select name="user_type" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="client" {{ request('user_type') === 'client' ? 'selected' : '' }}>عملاء</option>
                                        <option value="employee" {{ request('user_type') === 'employee' ? 'selected' : '' }}>موظفين</option>
                                        <option value="both" {{ request('user_type') === 'both' ? 'selected' : '' }}>الجميع</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع التحدي</label>
                                    <select name="challenge_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($challengeTypes as $type)
                                            <option value="{{ $type->value }}" {{ request('challenge_type') === $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>جاري</option>
                                        <option value="upcoming" {{ request('status') === 'upcoming' ? 'selected' : '' }}>قادم</option>
                                        <option value="ended" {{ request('status') === 'ended' ? 'selected' : '' }}>منتهي</option>
                                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>غير نشط</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>الفرع</label>
                                    <select name="branch_id" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-search"></i> بحث
                                </button>
                                <a href="{{ route('ecosystem.points.challenges.index') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-x"></i> إعادة تعيين
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon">
                    <i class="feather icon-target"></i>
                </div>
                <div class="stat-label">إجمالي التحديات</div>
                <div class="stat-value">{{ number_format($stats['total'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>تحدي</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-play-circle"></i>
                </div>
                <div class="stat-label">تحديات جارية</div>
                <div class="stat-value">{{ number_format($stats['active'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>نشط</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-users"></i>
                </div>
                <div class="stat-label">إجمالي المشاركين</div>
                <div class="stat-value">{{ number_format($stats['total_participants'] ?? 0) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-user-check"></i>
                    <span>مشارك</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-star"></i>
                </div>
                <div class="stat-label">النقاط الموزعة</div>
                <div class="stat-value">{{ number_format($stats['total_points_awarded'] ?? 0) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-gift"></i>
                    <span>نقطة</span>
                </div>
            </div>
        </div>

        <!-- Challenges Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-target"></i> قائمة التحديات</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash-2"></i> حذف المحدد
                    </button>
                    <span class="badge-count mr-1">{{ $challenges->total() }} تحدي</span>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th width="3%">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="selectAll">
                                        <label class="custom-control-label" for="selectAll"></label>
                                    </div>
                                </th>
                                <th width="20%">التحدي</th>
                                <th width="8%">النوع</th>
                                <th width="10%">المنافسة</th>
                                <th width="10%">الهدف</th>
                                <th width="8%">المكافأة</th>
                                <th width="10%">المشاركون</th>
                                <th width="12%">المدة</th>
                                <th width="8%">الحالة</th>
                                <th width="7%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($challenges as $challenge)
                                @php
                                    $statusColor = 'secondary';
                                    $statusText = 'غير نشط';
                                    if ($challenge->is_active) {
                                        if ($challenge->isOngoing()) {
                                            $statusColor = 'success';
                                            $statusText = 'جاري';
                                        } elseif ($challenge->isUpcoming()) {
                                            $statusColor = 'info';
                                            $statusText = 'قادم';
                                        } elseif ($challenge->hasEnded()) {
                                            $statusColor = 'warning';
                                            $statusText = 'منتهي';
                                        }
                                    } else {
                                        $statusColor = 'danger';
                                        $statusText = 'غير نشط';
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox"
                                                   id="check-{{ $challenge->id }}" value="{{ $challenge->id }}">
                                            <label class="custom-control-label" for="check-{{ $challenge->id }}"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ route('ecosystem.points.challenges.show', $challenge->id) }}" class="font-weight-bold">
                                            {{ $challenge->name }}
                                        </a>
                                        @if($challenge->name_en)
                                            <br><small class="text-muted">{{ $challenge->name_en }}</small>
                                        @endif
                                        @if($challenge->is_featured)
                                            <span class="badge badge-light-warning badge-sm ml-1"><i class="feather icon-star"></i> مميز</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light-primary">
                                            {{ $challenge->challenge_type instanceof \App\Enums\ChallengeType ? $challenge->challenge_type->label() : $challenge->challenge_type }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-secondary">
                                            {{ $challenge->competition_type instanceof \App\Enums\CompetitionType ? $challenge->competition_type->label() : $challenge->competition_type }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{ number_format($challenge->target_value) }}</span>
                                        @if($challenge->target_unit)
                                            <small class="text-muted">{{ $challenge->target_unit }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="font-weight-bold text-success">
                                            <i class="feather icon-star"></i> {{ number_format($challenge->reward_points) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{ number_format($challenge->participants_count) }}</span>
                                        <small class="text-muted">/</small>
                                        <span class="text-success">{{ number_format($challenge->completed_count) }}</span>
                                        <small class="text-muted">مكتمل</small>
                                    </td>
                                    <td>
                                        <small>
                                            {{ $challenge->starts_at ? $challenge->starts_at->format('Y/m/d') : '-' }}<br>
                                            {{ $challenge->ends_at ? $challenge->ends_at->format('Y/m/d') : '-' }}
                                        </small>
                                        @if($challenge->isOngoing())
                                            <br><small class="text-success">
                                                <i class="feather icon-clock"></i> {{ $challenge->getDaysRemaining() }} يوم
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $statusColor }}">{{ $statusText }}</span>
                                    </td>
                                    <td>
                                        <div class="dropdown actions-dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.challenges.show', $challenge->id) }}">
                                                    <i class="feather icon-eye mr-1"></i> عرض
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.challenges.edit', $challenge->id) }}">
                                                    <i class="feather icon-edit mr-1"></i> تعديل
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.challenges.leaderboard', $challenge->id) }}">
                                                    <i class="feather icon-bar-chart-2 mr-1"></i> المتصدرون
                                                </a>
                                                @if($challenge->challenge_type instanceof \App\Enums\ChallengeType && $challenge->challenge_type === \App\Enums\ChallengeType::TEAM)
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.challenges.teams.index', ['challenge_id' => $challenge->id]) }}">
                                                    <i class="feather icon-users mr-1"></i> الفرق
                                                </a>
                                                @endif
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-{{ $challenge->is_active ? 'warning' : 'success' }} toggle-status"
                                                   href="javascript:void(0);"
                                                   data-id="{{ $challenge->id }}">
                                                    <i class="feather icon-{{ $challenge->is_active ? 'x-circle' : 'check-circle' }} mr-1"></i>
                                                    {{ $challenge->is_active ? 'إلغاء تفعيل' : 'تفعيل' }}
                                                </a>
                                                @if($challenge->hasEnded() && !$challenge->results_announced_at)
                                                <a class="dropdown-item text-primary finalize-challenge"
                                                   href="javascript:void(0);"
                                                   data-id="{{ $challenge->id }}">
                                                    <i class="feather icon-award mr-1"></i> إنهاء وتوزيع الجوائز
                                                </a>
                                                @endif
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('ecosystem.points.challenges.destroy', $challenge->id) }}"
                                                      method="POST" class="delete-form d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather icon-trash-2 mr-1"></i> حذف
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <i class="feather icon-target font-large-2 text-muted mb-1"></i>
                                        <p class="text-muted mb-0">لا توجد تحديات</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $challenges, 'label' => 'تحدي'])
            </div>
        </div>
    </div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Select all checkboxes
    $('#selectAll').on('change', function() {
        $('.row-checkbox').prop('checked', $(this).prop('checked'));
        toggleBulkDeleteBtn();
    });

    // Individual checkbox
    $('.row-checkbox').on('change', function() {
        toggleBulkDeleteBtn();
        if (!$(this).prop('checked')) {
            $('#selectAll').prop('checked', false);
        }
    });

    function toggleBulkDeleteBtn() {
        const checkedCount = $('.row-checkbox:checked').length;
        $('#bulkDeleteBtn').toggle(checkedCount > 0);
    }

    // Bulk delete
    $('#bulkDeleteBtn').on('click', function() {
        const ids = $('.row-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف ' + ids.length + ' تحدي نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("ecosystem.points.challenges.bulk-delete") }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', ids: ids },
                    success: function(response) {
                        location.reload();
                    },
                    error: function() {
                        Swal.fire('خطأ!', 'حدث خطأ أثناء الحذف', 'error');
                    }
                });
            }
        });
    });

    // Toggle status
    $('.toggle-status').click(function() {
        var challengeId = $(this).data('id');
        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: 'هل تريد تغيير حالة هذا التحدي؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url("ecosystem/points/challenges") }}/' + challengeId + '/toggle-status',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('تم!', response.message, 'success').then(() => location.reload());
                        }
                    },
                    error: function() {
                        Swal.fire('خطأ!', 'حدث خطأ أثناء تغيير الحالة', 'error');
                    }
                });
            }
        });
    });

    // Finalize challenge
    $('.finalize-challenge').click(function() {
        var challengeId = $(this).data('id');
        Swal.fire({
            title: 'إنهاء التحدي وتوزيع الجوائز',
            text: 'سيتم حساب الترتيب النهائي وتوزيع النقاط على الفائزين. هذا الإجراء لا يمكن التراجع عنه.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، وزّع الجوائز',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("ecosystem/points/challenges") }}/' + challengeId + '/finalize';
                var token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = '{{ csrf_token() }}';
                form.appendChild(token);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Delete confirmation
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا التحدي نهائياً!',
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
