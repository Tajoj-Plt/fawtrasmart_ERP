{{--
    جدولُ الموظفين المنسوبين إلى سجلٍّ في الهيكل التنظيمي — داخل نطاق رؤية
    المستخدم (المتحكّم يقيّده)، مرقَّمٌ بمعامل `emp_page`.

      members     LengthAwarePaginator من Employee
      emptyHint   نصُّ الحالة الفارغة
      hideColumn  'department' | 'job_title' — عمودٌ لا معنى له في الشاشة (اختياري)
--}}
@php
    $membersHide = $hideColumn ?? null;
    $membersCanShow = hasPermission('employees.show');
@endphp
@if ($members->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>الموظف</th>
                    @if ($membersHide !== 'job_title')
                        <th>المسمى الوظيفي</th>
                    @endif
                    @if ($membersHide !== 'department')
                        <th>القسم</th>
                    @endif
                    <th>الفرع</th>
                    <th class="text-center">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($members as $i => $member)
                    @php
                        $memberStatus = $member->employmentStatus();
                        $memberTone = match ($memberStatus) {
                            \App\Enums\EmploymentStatus::ACTIVE, \App\Enums\EmploymentStatus::PROBATION => 'on',
                            \App\Enums\EmploymentStatus::SUSPENDED => 'warn',
                            default => 'off',
                        };
                    @endphp
                    <tr>
                        <td class="stk-num text-muted">{{ $members->firstItem() + $i }}</td>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                <span class="stk-media__body">
                                    @if ($membersCanShow)
                                        <a href="{{ route('employee.show', $member->id) }}" class="stk-media__title">{{ $member->full_name }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $member->full_name }}</span>
                                    @endif
                                    <span class="stk-media__sub" dir="ltr">{{ $member->employee_code ?: '#'.$member->id }}</span>
                                </span>
                            </div>
                        </td>
                        @if ($membersHide !== 'job_title')
                            <td>{{ $member->job_title->name ?? '—' }}</td>
                        @endif
                        @if ($membersHide !== 'department')
                            <td>{{ $member->department->name ?? '—' }}</td>
                        @endif
                        <td>{{ $member->branch->name ?? '—' }}</td>
                        <td class="text-center">
                            <span class="stk-state stk-state--{{ $memberTone }}">{{ $memberStatus->label() }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($members->hasPages())
        <div class="stk-table-foot">
            <p class="stk-table-foot__count">عرض {{ $members->firstItem() }}–{{ $members->lastItem() }} من {{ $members->total() }}</p>
            {{ $members->fragment('employees')->links() }}
        </div>
    @endif
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-users"></i></div>
            <h5 class="stk-empty__title">لا موظفين</h5>
            <p class="stk-empty__hint">{{ $emptyHint ?? 'لا أحد من الموظفين ضمن نطاقك منسوبٌ إلى هذا السجل.' }}</p>
        </div>
    </div>
@endif
