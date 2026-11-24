@extends('taskmanager::master')

@section('title')
    السبرنتات
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">السبرنتات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">السبرنتات</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('taskmanager::partials.page_hero', [
            'heroTitle' => 'إدارة',
            'heroHighlight' => 'السبرنتات',
            'heroSubtitle' => 'خطّط سباقات فرقك وتابع تقدمها بمنهجية سكرم التفاعلية',
            'heroStats' => [
                ['icon' => 'fas fa-repeat', 'value' => $totalSprintsCount, 'label' => 'إجمالي السبرنتات'],
                ['icon' => 'fas fa-bolt', 'value' => $activeSprintsCount, 'label' => 'سبرنتات نشطة'],
            ],
            'heroActions' => [
                ['url' => route('sprints.create'), 'label' => 'سبرنت جديد', 'icon' => 'fas fa-plus-circle', 'style' => 'solid'],
            ],
        ])

        <div class="card shadow-sm sprint-filter-bar">
            <div class="card-body">
                <form method="GET" action="{{ route('sprints.index') }}" class="row align-items-end">
                    <div class="col-md-4">
                        <label>المشروع</label>
                        <select name="project_id" class="form-control select2">
                            <option value="">جميع المشاريع</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>{{ $project->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>الحالة</label>
                        <select name="status" class="form-control select2">
                            <option value="">جميع الحالات</option>
                            <option value="planned" {{ request('status') == 'planned' ? 'selected' : '' }}>مخطط له</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>نشط</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>مكتمل</option>
                            <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>مغلق</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> تصفية</button>
                        <a href="{{ route('sprints.index') }}" class="btn btn-outline-secondary">إعادة تعيين</a>
                    </div>
                </form>
            </div>
        </div>

        @if ($sprints->isEmpty())
            <div class="empty-state-card text-center">
                <i class="feather icon-repeat"></i>
                <h5>لا توجد سبرنتات بعد</h5>
                <p class="text-muted">أنشئ أول سبرنت لمشروعك لبدء التخطيط بمنهجية سكرم</p>
                <a href="{{ route('sprints.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> سبرنت جديد</a>
            </div>
        @else
            <div class="sprint-grid">
                @foreach ($sprints as $sprint)
                    @php
                        $total = $sprint->tasks->count();
                        $completed = $sprint->tasks->where('status', 'completed')->count();
                        $rate = $total > 0 ? round(($completed / $total) * 100) : 0;
                        $statusMeta = [
                            'planned' => ['label' => 'مخطط له', 'class' => 'secondary'],
                            'active' => ['label' => 'نشط', 'class' => 'primary'],
                            'completed' => ['label' => 'مكتمل', 'class' => 'success'],
                            'closed' => ['label' => 'مغلق', 'class' => 'dark'],
                        ][$sprint->status] ?? ['label' => $sprint->status, 'class' => 'secondary'];
                    @endphp
                    <div class="sprint-card">
                        <div class="sprint-card-header">
                            <span class="badge badge-{{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown"><i class="feather icon-more-vertical"></i></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('sprints.show', $sprint->id) }}"><i class="feather icon-eye me-1"></i> عرض</a>
                                    <a class="dropdown-item" href="{{ route('sprints.edit', $sprint->id) }}"><i class="feather icon-edit me-1"></i> تعديل</a>
                                    <a class="dropdown-item text-danger sprint-delete-btn" href="#" data-id="{{ $sprint->id }}"><i class="feather icon-trash-2 me-1"></i> حذف</a>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('sprints.show', $sprint->id) }}" class="sprint-card-title">{{ $sprint->name }}</a>
                        <div class="sprint-card-project">
                            <i class="feather icon-folder"></i> {{ $sprint->project->title ?? 'بدون مشروع' }}
                        </div>
                        @if ($sprint->goal)
                            <p class="sprint-card-goal">{{ Str::limit($sprint->goal, 90) }}</p>
                        @endif
                        <div class="sprint-card-dates">
                            <i class="feather icon-calendar"></i>
                            {{ optional($sprint->start_date)->format('d M') }} — {{ optional($sprint->end_date)->format('d M Y') }}
                        </div>
                        <div class="sprint-card-progress">
                            <div class="progress" style="height:6px;">
                                <div class="progress-bar bg-success" style="width: {{ $rate }}%"></div>
                            </div>
                            <span class="small text-muted">{{ $completed }}/{{ $total }} مهام مكتملة ({{ $rate }}%)</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $sprints->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection

@section('styles')
<style>
    .sprint-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }
    .sprint-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #f0f0f0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        padding: 18px;
        transition: all 0.25s ease;
    }
    .sprint-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        transform: translateY(-3px);
    }
    .sprint-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .sprint-card-title {
        display: block;
        font-size: 1.05rem;
        font-weight: 700;
        color: #172b4d;
        margin-bottom: 6px;
    }
    .sprint-card-title:hover { color: #7367f0; }
    .sprint-card-project {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 8px;
    }
    .sprint-card-goal {
        font-size: 0.85rem;
        color: #5e6c84;
        margin-bottom: 10px;
    }
    .sprint-card-dates {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 10px;
    }
    .empty-state-card {
        padding: 60px 20px;
        background: #fff;
        border-radius: 14px;
        border: 1px dashed #dfe1e6;
        margin-top: 16px;
    }
    .empty-state-card i { font-size: 3rem; color: #dfe1e6; }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function () {
    $('.select2').select2();

    $('.sprint-delete-btn').on('click', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف السبرنت وإلغاء ربطه بجميع المهام',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذفه',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#ea5455'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route('sprints.destroy', '__ID__') }}'.replace('__ID__', encodeURIComponent(id)),
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function () {
                        Swal.fire('تم الحذف!', '', 'success').then(() => location.reload());
                    },
                    error: function (xhr) {
                        Swal.fire('خطأ!', xhr.responseJSON?.message || 'حدث خطأ أثناء الحذف', 'error');
                    }
                });
            }
        });
    });
});
</script>
@endsection
