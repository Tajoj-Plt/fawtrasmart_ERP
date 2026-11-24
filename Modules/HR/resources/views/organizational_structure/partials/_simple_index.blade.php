{{--
    الفلاتر والجدول لقوائم المستويات وأنواع الوظائف — السجلُّ فيها اسمٌ
    وحالةٌ ووصف، والعمودُ `employees_count` يضيفه المتحكّم (موظفو النطاق).

      rows          LengthAwarePaginator
      routePrefix   'ManagingFunctionalLevels' | 'ManagingJobTypes'
      permPrefix    'job_levels' | 'job_types'
      icon, tableId, listTitle, searchLabel, deleteHeading, subject,
      emptyTitle, emptyHint, addLabel
--}}
@php
    $filterKeys = ['name', 'status'];
    $hasFilters = collect(request()->only($filterKeys))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $canShow = hasPermission($permPrefix.'.show');
    $canEdit = hasPermission($permPrefix.'.edit');
    $canDestroy = hasPermission($permPrefix.'.destroy');
@endphp

<div class="card stk-filters">
    <div class="card-body">
        <div class="stk-filters__head">
            <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
            @if ($hasFilters)
                <span class="stk-count">فلاتر مفعّلة</span>
            @endif
        </div>

        <form action="{{ route($routePrefix.'.index') }}" method="GET">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="name">{{ $searchLabel }}</label>
                        <input type="text" id="name" name="name" class="form-control" value="{{ request('name') }}" placeholder="ابحث بالاسم…">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="status">الحالة</label>
                        <select id="status" name="status" class="form-control hr-s2">
                            <option value="">الكل</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>نشط</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>غير نشط</option>
                        </select>
                    </div>
                </div>
            </div>
            @if (request()->filled('per_page'))
                <input type="hidden" name="per_page" value="{{ request('per_page') }}">
            @endif

            <div class="stk-filters__foot">
                <a href="{{ route($routePrefix.'.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-search"></i> بحث
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card stk-table-card">
    @if ($rows->count() > 0)
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-list"></i> {{ $listTitle }}
                <span class="stk-count">{{ $rows->total() }}</span>
            </h6>
            @include('stock::components.per-page', [
                'id' => $routePrefix.'PerPage',
                'current' => request('per_page', 10),
                'onchange' => "window.location.href='".url()->current().'?'.http_build_query(request()->except(['per_page', 'page']))."&per_page='+this.value",
            ])
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0" id="{{ $tableId }}">
                <thead>
                    <tr>
                        <th style="width:44px">#</th>
                        <th>الاسم</th>
                        <th class="text-center">الموظفون</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center" style="width:70px">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        @php $isActive = (int) $row->status === 0; @endphp
                        <tr>
                            <td class="stk-num text-muted">{{ $rows->firstItem() + $index }}</td>
                            <td>
                                <div class="stk-media">
                                    <span class="stk-media__thumb"><i class="feather icon-{{ $icon }}"></i></span>
                                    <span class="stk-media__body">
                                        @if ($canShow)
                                            <a href="{{ route($routePrefix.'.show', $row->id) }}" class="stk-media__title">{{ $row->name }}</a>
                                        @else
                                            <span class="stk-media__title">{{ $row->name }}</span>
                                        @endif
                                        @if ($row->description)
                                            <span class="stk-media__sub">{{ \Illuminate\Support\Str::limit($row->description, 70) }}</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="text-center stk-num">{{ (int) $row->employees_count }}</td>
                            <td class="text-center">
                                <span class="stk-state stk-state--{{ $isActive ? 'on' : 'off' }}">{{ $isActive ? 'نشط' : 'غير نشط' }}</span>
                            </td>
                            <td class="text-center">
                                @if ($canShow || $canEdit || $canDestroy)
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" data-bs-display="static"
                                            aria-expanded="false" aria-label="خيارات">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            @if ($canShow)
                                                <a class="dropdown-item" href="{{ route($routePrefix.'.show', $row->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                </a>
                                            @endif
                                            @if ($canEdit)
                                                <a class="dropdown-item" href="{{ route($routePrefix.'.edit', $row->id) }}">
                                                    <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                </a>
                                                <a class="dropdown-item" href="{{ route($routePrefix.'.updateStatus', $row->id) }}">
                                                    @if ($isActive)
                                                        <i class="fa fa-pause-circle me-2 text-warning"></i>تعطيل
                                                    @else
                                                        <i class="fa fa-play-circle me-2 text-success"></i>تفعيل
                                                    @endif
                                                </a>
                                            @endif
                                            @if ($canDestroy)
                                                @if ($canShow || $canEdit)
                                                    <div class="dropdown-divider"></div>
                                                @endif
                                                <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $row->id }}">
                                                    <i class="fa fa-trash me-2"></i>حذف
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="stk-table-foot">
                <p class="stk-table-foot__count">عرض {{ $rows->firstItem() }}–{{ $rows->lastItem() }} من {{ $rows->total() }}</p>
                {{ $rows->links() }}
            </div>
        @endif
    @else
        <div class="card-body">
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="feather icon-{{ $icon }}"></i></div>
                <h5 class="stk-empty__title">{{ $emptyTitle }}</h5>
                <p class="stk-empty__hint">
                    @if ($hasFilters)
                        لم يطابق البحث أيّ سجل — جرّب معايير أوسع
                    @else
                        {{ $emptyHint }}
                    @endif
                </p>
                @if ($hasFilters)
                    <a href="{{ route($routePrefix.'.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                @elseif (hasPermission($permPrefix.'.create'))
                    <a href="{{ route($routePrefix.'.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> {{ $addLabel }}</a>
                @endif
            </div>
        </div>
    @endif
</div>

@if ($canDestroy)
    @foreach ($rows as $row)
        @include('hr::organizational_structure.partials._delete_modal', [
            'modalId' => 'modal_DELETE'.$row->id,
            'heading' => $deleteHeading,
            'action' => route($routePrefix.'.delete', $row->id),
            'itemName' => $row->name,
            'subject' => $subject,
            'usage' => [],
        ])
    @endforeach
@endif
