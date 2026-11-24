@if (isset($shifts) && !empty($shifts) && count($shifts) > 0)
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="shifts-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>الاسم</th>
                    <th>النوع</th>
                    <th>الدوام</th>
                    <th>مهلة التأخير</th>
                    <th>أيام العمل</th>
                    <th>أيام العطل</th>
                    <th class="text-center" style="width:70px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($shifts as $index => $shift)
                    <tr>
                        <td class="stk-num text-muted">{{ $shifts->firstItem() + $index }}</td>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-clock"></i></span>
                                <span class="stk-media__body">
                                    @if (hasPermission('shifts.show'))
                                        <a href="{{ route('shift_management.show', $shift->id) }}" class="stk-media__title">{{ $shift->name }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $shift->name }}</span>
                                    @endif
                                    <span class="stk-media__sub">تم الإنشاء: {{ $shift->created_at ? $shift->created_at->format('Y-m-d') : 'غير محدد' }}</span>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-{{ $shift->type == 1 ? 'success' : 'info' }}">
                                {{ $shift->type == 1 ? 'أساسي' : 'متقدم' }}
                            </span>
                        </td>

                        {{--
                            أوقاتُ الدوام ومهلةُ التأخير — وهما ما تُعرَّف هذه
                            الشاشةُ لأجله، ولم يكن لهما عمودٌ في الجدول: يُعرفان
                            بفتح كل وردية على حدة.

                            والورديةُ الليلية تُعلَّم: نهايةٌ تسبق البداية تعني
                            أنها تمتدّ إلى اليوم التالي، وعليها يُبنى الحساب
                            (`getExpectedCheckOut` يُرحّلها يوماً). وإظهارُها
                            هنا يُطمئن من أدخل «22:00 → 06:00» أنّ النظام فهمها
                            وردِيّةً ليلية لا خطأً في الإدخال.
                        --}}
                        @php
                            $days = $shift->days->where('working_day', 1);
                            $sample = $days->first();
                            $crossesMidnight = $days->contains(fn ($d) =>
                                $d->start_time && $d->end_time && $d->end_time <= $d->start_time);
                            $graces = $days->pluck('grace_period')->filter(fn ($g) => $g !== null)->unique();
                        @endphp

                        <td>
                            @if ($sample && $sample->start_time)
                                <span dir="ltr">{{ substr($sample->start_time, 0, 5) }} – {{ substr($sample->end_time ?? '', 0, 5) ?: '—' }}</span>
                                @if ($days->count() > 1 && $days->pluck('start_time')->unique()->count() > 1)
                                    <div class="stk-help">يختلف بين الأيام</div>
                                @endif
                                @if ($crossesMidnight)
                                    <span class="badge badge-info" title="النهاية تسبق البداية — تُحتسب في اليوم التالي">
                                        <i class="feather icon-moon"></i> تمتد لليوم التالي
                                    </span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        <td>
                            @if ($graces->count() === 1)
                                {{ (int) $graces->first() }} دقيقة
                            @elseif ($graces->count() > 1)
                                <span title="{{ $graces->map(fn ($g) => (int) $g.' د')->implode(' · ') }}">يختلف بين الأيام</span>
                            @else
                                <span class="text-muted">مهلة المنشأة</span>
                            @endif
                        </td>

                        <td>
                            @php
                                $workingDays = $shift->days->where('working_day', 1)->pluck('day')->toArray();
                                $workingDaysNames = [
                                    'sunday' => 'الأحد',
                                    'monday' => 'الإثنين',
                                    'tuesday' => 'الثلاثاء',
                                    'wednesday' => 'الأربعاء',
                                    'thursday' => 'الخميس',
                                    'friday' => 'الجمعة',
                                    'saturday' => 'السبت',
                                ];
                                $workingDaysTranslated = array_map(function($day) use ($workingDaysNames) {
                                    return $workingDaysNames[$day] ?? $day;
                                }, $workingDays);
                            @endphp
                            <div class="d-flex flex-wrap">
                                @foreach ($workingDaysTranslated as $day)
                                    <span class="badge badge-success mr-1 mb-1">{{ $day }}</span>
                                @endforeach
                            </div>
                            <small class="stk-help">{{ count($workingDays) }} من 7 أيام</small>
                        </td>
                        <td>
                            @php
                                $allDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                                $daysOff = array_diff($allDays, $workingDays);
                                $daysNames = [
                                    'sunday' => 'الأحد',
                                    'monday' => 'الإثنين',
                                    'tuesday' => 'الثلاثاء',
                                    'wednesday' => 'الأربعاء',
                                    'thursday' => 'الخميس',
                                    'friday' => 'الجمعة',
                                    'saturday' => 'السبت',
                                ];
                                $daysOffTranslated = array_map(function($day) use ($daysNames) {
                                    return $daysNames[$day] ?? $day;
                                }, $daysOff);
                            @endphp
                            @if(count($daysOff) > 0)
                                <div class="d-flex flex-wrap">
                                    @foreach ($daysOffTranslated as $day)
                                        <span class="badge badge-danger mr-1 mb-1">{{ $day }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted">لا توجد أيام عطل</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if (canAny(['shifts.show', 'shifts.edit', 'shifts.destroy']))
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="dropdownMenuButton{{ $shift->id }}"
                                            data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $shift->id }}">
                                        @permission('shifts.show')
                                            <a class="dropdown-item" href="{{ route('shift_management.show', $shift->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                        @endpermission
                                        @permission('shifts.edit')
                                            <a class="dropdown-item" href="{{ route('shift_management.edit', $shift->id) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                        @endpermission
                                        @permission('shifts.destroy')
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger delete-shift-btn"
                                               href="#"
                                               data-shift-id="{{ $shift->id }}"
                                               data-shift-name="{{ $shift->name }}">
                                                <i class="fa fa-trash me-2"></i>حذف
                                            </a>
                                        @endpermission
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($shifts->hasPages())
        <div class="stk-table-foot">
            <p class="stk-table-foot__count">عرض {{ $shifts->firstItem() }}–{{ $shifts->lastItem() }} من {{ $shifts->total() }}</p>
            {{ $shifts->onEachSide(1)->links() }}
        </div>
    @endif
@else
    @php
        $shHasFilters = collect(request()->only(['keywords', 'type', 'days']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-clock"></i></div>
            <h5 class="stk-empty__title">لا توجد ورديات</h5>
            <p class="stk-empty__hint">
                @if ($shHasFilters)
                    لم يطابق البحث أي وردية — جرّب معايير أوسع
                @else
                    أضف أول وردية لتحديد أوقات الدوام وأيام العمل التي يُقاس إليها الحضور
                @endif
            </p>
            @if ($shHasFilters)
                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('clear-filters').click()"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</button>
            @else
                @permission('shifts.create')
                    <a href="{{ route('shift_management.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> أضف وردية</a>
                @endpermission
            @endif
        </div>
    </div>
@endif
