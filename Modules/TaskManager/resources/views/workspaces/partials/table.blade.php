{{-- ملف: resources/views/taskmanager/workspaces/partials/table.blade.php --}}

@if ($workspaces->count() > 0)
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
        @foreach ($workspaces as $workspace)
            <div class="entity-card">
                <div class="entity-card-top">
                    <div class="entity-avatar" style="background: {{ $workspace->is_primary ? 'linear-gradient(135deg, #ff9f43, #e08e2b)' : 'linear-gradient(135deg, #2196F3, #1976D2)' }}">
                        <i class="feather icon-layers"></i>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown"><i class="feather icon-more-vertical"></i></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="{{ route('workspaces.show', $workspace->id) }}">
                                <i class="feather icon-eye me-2 text-primary"></i>عرض
                            </a>
                            <a class="dropdown-item" href="{{ route('workspaces.edit', $workspace->id) }}">
                                <i class="feather icon-edit me-2 text-success"></i>تعديل
                            </a>
                            @if (!$workspace->is_primary && ($workspace->projects_count ?? 0) == 0)
                                <form id="delete-form-{{ $workspace->id }}"
                                      action="{{ route('workspaces.destroy', $workspace->id) }}"
                                      method="POST"
                                      style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <a class="dropdown-item text-danger delete-workspace"
                                   href="#"
                                   data-id="{{ $workspace->id }}"
                                   onclick="event.preventDefault(); document.getElementById('delete-form-{{ $workspace->id }}').submit();">
                                    <i class="feather icon-trash-2 me-2"></i> حذف
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <a href="{{ route('workspaces.show', $workspace->id) }}" class="entity-card-title">{{ $workspace->title }}</a>
                @if ($workspace->description)
                    <p class="entity-card-desc">{{ Str::limit($workspace->description, 80) }}</p>
                @endif

                <div class="entity-card-meta">
                    <span><i class="feather icon-user"></i> {{ $workspace->admin->name ?? 'غير محدد' }}</span>
                    <span><i class="feather icon-calendar"></i> {{ $workspace->created_at ? $workspace->created_at->diffForHumans() : '' }}</span>
                </div>

                <div class="entity-card-badges">
                    <span class="badge bg-primary">{{ $workspace->projects_count ?? 0 }} مشروع</span>
                    @if ($workspace->is_primary)
                        <span class="badge bg-warning">رئيسية</span>
                    @else
                        <span class="badge bg-info">فرعية</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- الترقيم --}}
    @include('taskmanager::workspaces.partials.pagination', ['workspaces' => $workspaces])
@else
    <div class="entity-empty-state text-center">
        <i class="feather icon-layers"></i>
        <p class="mb-0">لا توجد مساحات عمل تطابق معايير البحث</p>
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
