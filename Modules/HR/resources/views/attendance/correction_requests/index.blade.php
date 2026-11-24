@extends('hr::master')

@section('title')
طلبات تصحيح الحضور
@stop

@section('page-actions')
    @permission('attendance_corrections.create')
        <a href="{{ route('hr.attendance-corrections.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">طلب تصحيح جديد</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'طلبات تصحيح الحضور',
        'icon' => 'edit-3',
        'sub' => 'تصحيح يوم حضور يمرّ بطلبٍ ثم اعتماد — لا تعديلٍ مباشر',
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'daily'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-correction-requests.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه طلباتُ تصحيح الحضور: يرفعها من لا يملك تعديلَ أيام الحضور مباشرةً. والطلبُ لا يغيّر يوماً حتى يعتمده غيرُه.',
        'blocks' => [
            'لماذا طلبٌ لا تعديل' => [
                'تعديلُ يوم حضورٍ يغيّر مبلغاً يُصرَف لاحقاً، فلا يُترَك لصاحب الشأن. والطلبُ يجعل للتغيير طالباً ومعتمِداً معروفَين.',
            ],
            'ما يترتّب على الاعتماد' => [
                'اليومُ المطلوبُ تصحيحُه يُكتب بالحالة المطلوبة، وتُصفَّر معها دقائقُ التأخير والانصراف المبكّر والوقت الإضافي — فلا تبقى دقائقُ حسابٍ سابقٍ عالقةً على يومٍ اعتُمد «حاضراً».',
                'والحقولُ التي لا تخصّ الحالة الجديدة تُفرَّغ: من صار «غائباً» لا تبقى له أوقاتُ دخولٍ وخروج.',
            ],
            'قبل الاعتماد' => [
                'الطلبُ المعلَّق لا أثرَ له إطلاقاً: لا في تقرير الحضور ولا في حسم الراتب.',
            ],
        ],
        'facts' => [
            'الطلبُ لا يغيّر يوماً حتى يُعتمَد',
            'الاعتمادُ يُصفّر دقائقَ التأخير والإضافي',
        ],
    ])

    @include('hr::organizational_structure.partials._stats', ['statItems' => [
        ['label' => 'كل الطلبات', 'value' => $stats['total'], 'icon' => 'edit-3', 'tone' => 'primary'],
        ['label' => 'معلَّقة', 'value' => $stats['pending'], 'icon' => 'clock', 'tone' => 'info'],
        ['label' => 'معتمَدة', 'value' => $stats['approved'], 'icon' => 'check-circle', 'tone' => 'success'],
        ['label' => 'مرفوضة', 'value' => $stats['rejected'], 'icon' => 'x-circle', 'tone' => 'muted'],
    ]])

    @php
        $statusLabels = \App\Models\AttendanceCorrectionRequest::statusLabels();
        $hasFilters = array_key_exists((string) ($status ?? ''), $statusLabels);
        $statusTones = ['pending' => 'warning', 'approved' => 'success'];
    @endphp

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form action="{{ route('hr.attendance-corrections.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="status">حالة الطلب</label>
                            <select id="status" name="status" class="form-control hr-s2">
                                <option value="">الكل</option>
                                @foreach ($statusLabels as $key => $label)
                                    <option value="{{ $key }}" @selected(($status ?? null) === $key)>{{ $label }} ({{ $stats[$key] ?? 0 }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <a href="{{ route('hr.attendance-corrections.index') }}" class="btn btn-outline-secondary">
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
        @if ($requests->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة الطلبات
                    <span class="stk-count">{{ $requests->total() }}</span>
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>الموظف</th>
                            <th>تاريخ الحضور</th>
                            <th>الحالة المطلوبة</th>
                            <th>السبب</th>
                            <th>مقدِّم الطلب</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $index => $correction)
                            <tr>
                                <td class="stk-num text-muted">{{ $requests->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-edit-3"></i></span>
                                        <span class="stk-media__body">
                                            <a href="{{ route('hr.attendance-corrections.show', $correction->id) }}" class="stk-media__title">{{ $correction->employee->full_name ?? '—' }}</a>
                                            <span class="stk-media__sub">طلب رقم {{ $correction->id }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="stk-num">{{ \Carbon\Carbon::parse($correction->attendance_date)->format('Y-m-d') }}</td>
                                <td>{{ $correction->requestedStatusLabel() }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($correction->reason, 40) }}</td>
                                <td>{{ $correction->requestedBy->name ?? '—' }}</td>
                                <td class="text-center">
                                    <span class="badge badge-{{ $statusTones[$correction->status] ?? 'danger' }}">{{ $correction->statusLabel() }}</span>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" data-bs-display="static"
                                            aria-expanded="false" aria-label="خيارات">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{ route('hr.attendance-corrections.show', $correction->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                            @if ($correction->status === 'pending' && (int) $correction->requested_by === (int) auth()->id() && hasPermission('attendance_corrections.create'))
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('hr.attendance-corrections.cancel', $correction->id) }}" method="POST"
                                                      onsubmit="return confirm('هل تريد إلغاء طلب التصحيح هذا؟');">
                                                    @csrf
                                                    @method('PUT')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fa fa-times me-2"></i>إلغاء الطلب
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($requests->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $requests->firstItem() }}–{{ $requests->lastItem() }} من {{ $requests->total() }}</p>
                    {{ $requests->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-edit-3"></i></div>
                    <h5 class="stk-empty__title">لا توجد طلبات تصحيح</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لا توجد طلبات بهذه الحالة — اعرض الكل
                        @else
                            لم يُرفع أيّ طلب تصحيح حضور بعد
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('hr.attendance-corrections.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('attendance_corrections.create'))
                        <a href="{{ route('hr.attendance-corrections.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> طلب تصحيح جديد</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
