@extends('hr::master')

@section('title')
اعتماد طلبات تصحيح الحضور
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'اعتماد طلبات تصحيح الحضور',
        'icon' => 'check-square',
        'sub' => 'الطلبات المعلَّقة التي تنتظر قرارك — وسجلُّ ما بُتَّ فيه',
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'daily'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-correction-approvals.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه طلباتُ التصحيح المعروضةُ عليك للبتّ. واعتمادُك يكتب في سجلّ الحضور مباشرةً — وهو سجلٌّ يُحسَم منه الراتب.',
        'blocks' => [
            'ما يقع عند الاعتماد' => [
                'يومُ الحضور المعنيُّ يُكتب بالحالة المطلوبة فوراً، ودقائقُ التأخير والانصراف المبكّر والوقت الإضافي تُصفَّر معه.',
                'والحقولُ التي لا تخصّ الحالة الجديدة تُفرَّغ، فلا يبقى وقتُ دخولٍ على يومٍ صار «غياباً».',
                'ومن ثمّ يتغيّر ما يُحسَم من راتب الموظف عن ذلك اليوم.',
            ],
            'الرفض' => [
                'الرفضُ لا يمسّ سجلَّ الحضور بشيء: اليومُ يبقى كما هو، ويُعلَم الطالبُ بالردّ.',
            ],
        ],
        'facts' => [
            'الاعتمادُ يكتب في سجلّ الحضور فوراً',
            'ويغيّر ما يُحسَم من الراتب',
        ],
    ])

    @include('hr::organizational_structure.partials._stats', ['statItems' => [
        ['label' => 'تنتظر قرارك', 'value' => $counts['pending'], 'icon' => 'clock', 'tone' => 'info'],
        ['label' => 'معتمَدة', 'value' => $counts['approved'], 'icon' => 'check-circle', 'tone' => 'success'],
        ['label' => 'مرفوضة', 'value' => $counts['rejected'], 'icon' => 'x-circle', 'tone' => 'muted'],
    ]])

    @php
        $statusLabels = \App\Models\AttendanceCorrectionRequest::statusLabels();
        $isPending = $status === 'pending';
        // الافتراضيُّ «معلَّق» (طابور القرار)؛ وأيُّ حالةٍ غيرُه تصفيةٌ مفعّلة
        $hasFilters = ! $isPending;
    @endphp

    {{-- ما بُتَّ فيه لا يختفي: القرارُ يُراجَع كما يُتَّخذ --}}
    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form action="{{ route('hr.attendance-correction-approvals.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="status">حالة الطلب</label>
                            <select id="status" name="status" class="form-control hr-s2">
                                @foreach ($statusLabels as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <a href="{{ route('hr.attendance-correction-approvals.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-list"></i> طلبات {{ $statusLabels[$status] }}ة
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
                            <th>تاريخ الطلب</th>
                            @unless ($isPending)
                                <th>{{ $status === 'approved' ? 'اعتمده' : 'رفضه' }}</th>
                            @endunless
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $index => $correction)
                            <tr>
                                <td class="stk-num text-muted">{{ $requests->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-check-square"></i></span>
                                        <span class="stk-media__body">
                                            <a href="{{ route('hr.attendance-correction-approvals.show', $correction->id) }}" class="stk-media__title">{{ $correction->employee->full_name ?? '—' }}</a>
                                            <span class="stk-media__sub">طلب رقم {{ $correction->id }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="stk-num">{{ \Carbon\Carbon::parse($correction->attendance_date)->format('Y-m-d') }}</td>
                                <td>{{ $correction->requestedStatusLabel() }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($correction->reason, 40) }}</td>
                                <td>{{ $correction->requestedBy->name ?? '—' }}</td>
                                <td class="stk-num">{{ $correction->created_at->format('Y-m-d') }}</td>
                                @unless ($isPending)
                                    <td>
                                        {{ $correction->approvedBy->name ?? '—' }}
                                        <div class="text-muted stk-num" style="font-size:.72rem">
                                            {{ optional($correction->approved_at)->format('Y-m-d H:i') }}
                                        </div>
                                    </td>
                                @endunless
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" data-bs-display="static"
                                            aria-expanded="false" aria-label="خيارات">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{ route('hr.attendance-correction-approvals.show', $correction->id) }}">
                                                @if ($isPending)
                                                    <i class="fa fa-clipboard-check me-2 text-success"></i>مراجعة (اعتماد / رفض)
                                                @else
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                @endif
                                            </a>
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
                    <div class="stk-empty__icon"><i class="feather icon-check-square"></i></div>
                    <h5 class="stk-empty__title">لا توجد طلبات {{ $statusLabels[$status] }}ة حالياً</h5>
                    <p class="stk-empty__hint">
                        @if ($isPending)
                            لا طلب ينتظر قرارك الآن — ما بُتَّ فيه تجده باختيار «معتمَد» أو «مرفوض»
                        @else
                            لم يُبتّ في أيّ طلبٍ بهذه الحالة بعد
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('hr.attendance-correction-approvals.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
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
