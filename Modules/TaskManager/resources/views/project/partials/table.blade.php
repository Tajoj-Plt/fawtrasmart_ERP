{{-- ملف: resources/views/taskmanager/project/partials/table.blade.php --}}

@php
    $statusColors = [
        'planning' => 'primary',
        'active' => 'warning',
        'on_hold' => 'danger',
        'completed' => 'success',
        'cancelled' => 'dark',
        'archived' => 'secondary',
    ];
    $statusNames = [
        'planning' => 'تخطيط',
        'active' => 'نشط',
        'on_hold' => 'معلق',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
        'archived' => 'مؤرشف',
    ];
    $priorityColors = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'urgent' => 'dark'
    ];
    $priorityNames = [
        'low' => 'منخفضة',
        'medium' => 'متوسطة',
        'high' => 'عالية',
        'urgent' => 'عاجلة'
    ];
@endphp

@if ($projects->count() > 0)
    <div class="d-flex justify-content-end align-items-center mb-2">
        <label class="mb-0 d-flex align-items-center gap-2 small text-muted">
            عرض
            <select class="form-select form-select-sm w-auto"
                onchange="if(window.loadData) { window.loadData(1, parseInt(this.value)); }">
                <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                <option value="25" {{ request('per_page', 10) == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ request('per_page', 10) == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ request('per_page', 10) == 100 ? 'selected' : '' }}>100</option>
            </select>
            سجل
        </label>
    </div>

    <div class="entity-card-grid">
        @foreach ($projects as $project)
            <div class="entity-card">
                <div class="entity-card-top">
                    <div class="entity-avatar" style="background: {{ $project->priority == 'urgent' ? 'linear-gradient(135deg, #F44336, #C62828)' : ($project->priority == 'high' ? 'linear-gradient(135deg, #FF9800, #E08E2B)' : 'linear-gradient(135deg, #2196F3, #1976D2)') }}">
                        <i class="feather icon-briefcase"></i>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown"><i class="feather icon-more-vertical"></i></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="{{ route('projects.show', $project->id) }}">
                                <i class="feather icon-eye me-2 text-primary"></i>عرض
                            </a>
                            <a class="dropdown-item" href="{{ route('projects.edit', $project->id) }}">
                                <i class="feather icon-edit me-2 text-success"></i>تعديل
                            </a>
                            @if ($project->created_by === Auth::id())
                                <form id="delete-form-{{ $project->id }}"
                                      action="{{ route('projects.destroy', $project->id) }}"
                                      method="POST"
                                      style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <a class="dropdown-item text-danger delete-project"
                                   href="#"
                                   data-id="{{ $project->id }}"
                                   onclick="event.preventDefault(); document.getElementById('delete-form-{{ $project->id }}').submit();">
                                    <i class="feather icon-trash-2 me-2"></i> حذف
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <a href="{{ route('projects.show', $project->id) }}" class="entity-card-title">{{ $project->title }}</a>
                @if ($project->description)
                    <p class="entity-card-desc">{{ Str::limit($project->description, 80) }}</p>
                @endif

                <div class="entity-card-meta">
                    <span><i class="feather icon-layers"></i> {{ $project->workspace->title ?? 'غير محدد' }}</span>
                    <span><i class="feather icon-calendar"></i> {{ $project->created_at ? $project->created_at->diffForHumans() : '' }}</span>
                </div>

                <div class="entity-card-badges mb-2">
                    <span class="badge bg-{{ $statusColors[$project->status] ?? 'secondary' }}">{{ $statusNames[$project->status] ?? 'غير محدد' }}</span>
                    <span class="badge bg-{{ $priorityColors[$project->priority] ?? 'secondary' }}">{{ $priorityNames[$project->priority] ?? 'غير محدد' }}</span>
                    @if ($project->management_type === 'manual')
                        <span class="badge bg-secondary">مهام بسيطة</span>
                    @else
                        <span class="badge bg-light-primary" style="color:#7367f0; background:rgba(115,103,240,.12);">سكرم</span>
                    @endif
                </div>

                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar"
                        style="width: {{ $project->progress_percentage }}%"
                        aria-valuenow="{{ $project->progress_percentage }}"
                        aria-valuemin="0"
                        aria-valuemax="100">
                    </div>
                </div>
                <small class="text-muted">{{ $project->progress_percentage }}% مكتمل</small>
            </div>
        @endforeach
    </div>

    {{-- الترقيم --}}
    @include('taskmanager::project.partials.pagination', ['projects' => $projects])
@else
    <div class="entity-empty-state text-center">
        <i class="feather icon-briefcase"></i>
        <p class="mb-0">لا توجد مشاريع تطابق معايير البحث</p>
    </div>
@endif

<style>
    .entity-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
    }
    .entity-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #f0f0f0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        padding: 18px;
        transition: all 0.25s ease;
    }
    .entity-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        transform: translateY(-3px);
    }
    .entity-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }
    .entity-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.1rem;
    }
    .entity-card-title {
        display: block;
        font-size: 1.05rem;
        font-weight: 700;
        color: #172b4d;
        margin-bottom: 4px;
    }
    .entity-card-title:hover { color: #7367f0; }
    .entity-card-desc {
        font-size: 0.82rem;
        color: #5e6c84;
        margin-bottom: 10px;
    }
    .entity-card-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 0.78rem;
        color: #6c757d;
        margin-bottom: 10px;
    }
    .entity-card-badges {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .entity-empty-state {
        padding: 60px 20px;
        background: #fff;
        border-radius: 14px;
        border: 1px dashed #dfe1e6;
    }
    .entity-empty-state i { font-size: 3rem; color: #dfe1e6; }
</style>
