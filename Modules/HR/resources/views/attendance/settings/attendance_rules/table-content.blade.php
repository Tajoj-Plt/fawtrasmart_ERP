{{--
    محتوى بطاقة جدول قواعد الحضور — يُعرض داخل #tableContainer في الصفحة،
    ويُعاد نفسه من attendance-rules.search عند البحث بـ AJAX.
--}}
@php
    $ruleFilterKeys = ['keywords', 'status', 'shift'];
    // روابط الصفحات تشير إلى شاشة القائمة لا إلى نقطة البحث (التي تُعيد JSON)
    $attendanceRules->withPath(route('attendance-rules.index'))
        ->appends(collect(request()->only(array_merge($ruleFilterKeys, ['per_page'])))->filter(fn ($v) => $v !== null && $v !== '')->all());
    $ruleFilters = collect(request()->only($ruleFilterKeys))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $canShowRule = hasPermission('attendance_rules.show');
    $canEditRule = hasPermission('attendance_rules.edit');
    $canDestroyRule = hasPermission('attendance_rules.destroy');
    // ما تضبطُه القاعدةُ فعلاً — من الخدمة نفسِها التي يقرأ بها المحرّك
    $ruleOverrides = app(\App\Services\Attendance\AttendanceRuleOverrides::class);
@endphp

@if ($attendanceRules->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> قائمة القواعد
            <span class="stk-count">{{ $attendanceRules->total() }}</span>
        </h6>
        @include('stock::components.per-page', [
            'id' => 'perPageSelect',
            'current' => request('per_page', 10),
            'onchange' => 'performSearch()',
        ])
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="attendance-rules-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>القاعدة</th>
                    <th>ما تضبطه</th>
                    <th>تسري على</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:70px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($attendanceRules as $index => $rule)
                    <tr id="rule-{{ $rule->id }}">
                        <td class="stk-num text-muted">{{ $attendanceRules->firstItem() + $index }}</td>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb" style="background: color-mix(in srgb, {{ $rule->color }} 18%, transparent); color: {{ $rule->color }};"><i class="feather icon-check-square"></i></span>
                                <span class="stk-media__body">
                                    @if ($canShowRule)
                                        <a href="{{ route('attendance-rules.show', $rule->id) }}" class="stk-media__title">{{ $rule->name }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $rule->name }}</span>
                                    @endif
                                    <span class="stk-media__sub">{{ \Illuminate\Support\Str::limit($rule->description, 60) }}</span>
                                </span>
                            </div>
                        </td>
                        <td style="min-width:260px">
                            @php $ruleSets = $ruleOverrides->describe($rule->formula, $rule->condition); @endphp
                            @forelse ($ruleSets as $line)
                                <span class="badge badge-light-primary mb-25">{{ $line }}</span>
                            @empty
                                {{-- قاعدةٌ لا تضبط شيئاً لا أثر لها مهما كانت «نشطة» --}}
                                <span class="badge badge-light-warning" title="لا قيمةَ محفوظةً يقرؤها المحرّك">لا تضبط شيئاً</span>
                            @endforelse
                        </td>
                        <td>
                            @if ($rule->shift_id)
                                {{ $rule->shift->name ?? '—' }}
                            @else
                                <span class="text-muted">كل الورديات</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="stk-state stk-state--{{ $rule->status == 'active' ? 'on' : 'off' }}">{{ $rule->status == 'active' ? 'نشط' : 'غير نشط' }}</span>
                        </td>
                        <td class="text-center">
                            @if ($canShowRule || $canEditRule || $canDestroyRule)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="dropdown" data-bs-display="static"
                                        aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if ($canShowRule)
                                            <a class="dropdown-item" href="{{ route('attendance-rules.show', $rule->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                        @endif
                                        @if ($canEditRule)
                                            <a class="dropdown-item" href="{{ route('attendance-rules.edit', $rule->id) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                            <a class="dropdown-item" href="#"
                                               onclick="event.preventDefault(); toggleStatus({{ $rule->id }}, '{{ $rule->status }}');">
                                                @if ($rule->status == 'active')
                                                    <i class="fa fa-pause-circle me-2 text-warning"></i>تعطيل
                                                @else
                                                    <i class="fa fa-play-circle me-2 text-success"></i>تفعيل
                                                @endif
                                            </a>
                                        @endif
                                        @if ($canDestroyRule)
                                            @if ($canShowRule || $canEditRule)
                                                <div class="dropdown-divider"></div>
                                            @endif
                                            <a class="dropdown-item text-danger" href="#"
                                               data-name="{{ $rule->name }}"
                                               onclick="event.preventDefault(); deleteRule({{ $rule->id }}, this.dataset.name);">
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

    @if ($attendanceRules->hasPages())
        <div class="stk-table-foot">
            <p class="stk-table-foot__count">عرض {{ $attendanceRules->firstItem() }}–{{ $attendanceRules->lastItem() }} من {{ $attendanceRules->total() }}</p>
            {{ $attendanceRules->links() }}
        </div>
    @endif
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-check-square"></i></div>
            <h5 class="stk-empty__title">لا توجد قواعد حضور</h5>
            <p class="stk-empty__hint">
                @if ($ruleFilters)
                    لم يطابق البحث أي قاعدة — جرّب معايير أوسع
                @else
                    أضف قاعدة حضور تحدّد مهلة التأخير وحدّ الغياب لكل وردية
                @endif
            </p>
            @if ($ruleFilters)
                <a href="{{ route('attendance-rules.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
            @elseif (hasPermission('attendance_rules.create'))
                <a href="{{ route('attendance-rules.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة قاعدة</a>
            @endif
        </div>
    </div>
@endif
