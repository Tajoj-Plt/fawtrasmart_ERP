{{--
    لوحة تحكّم خط السير — بتصميم شاشتَي التخطيط والعرض وترتيبهما.
    المُتحكِّم `ItineraryController::dashboard()`؛ الرسوم في `itinerary-dashboard-scripts`.
--}}
@php
    $levels = [
        'region' => ['المنطقة', 'fa-map-marker-alt', 'كل المناطق'],
        'branch' => ['الفرع', 'fa-building', 'كل الفروع'],
        'group' => ['المجموعة', 'fa-users', 'كل المجموعات'],
        'outlet' => ['المنفذ', 'fa-store', 'جميع المنافذ'],
    ];
    $from = $filters['date_from']->toDateString();
    $to = $filters['date_to']->toDateString();
    $scopeQuery = array_filter(['node_id' => $filters['node_id'], 'employee_id' => $filters['employee_id']]);
    $periodQuery = $scopeQuery + ['date_from' => $from, 'date_to' => $to];
    $stateLabels = ['completed' => 'مكتملة', 'started' => 'جارية', 'planned' => 'مخطَّطة', 'missed' => 'فائتة', 'cancelled' => 'ملغاة'];
    $statusBadge = function ($status) {
        if (! $status || ! ($status['name'] ?? null)) {
            return '<span class="rb-muted">—</span>';
        }
        $color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($status['color'] ?? '')) ? $status['color'] : null;

        return '<span class="rb-status"'.($color ? ' style="--rb-status:'.e($color).'"' : '').'>'.e($status['name']).'</span>';
    };
@endphp

<div id="route-builder" class="rb rb--dashboard" dir="rtl">
    <header class="rb-head">
        <div class="rb-head__title">
            <span class="rb-head__icon"><i class="fas fa-chart-line"></i></span>
            <div>
                <h1>لوحة تحكم خط السير</h1>
                <p>
                    الفترة: {{ $filters['date_from']->format('Y/m/d') }} — {{ $filters['date_to']->format('Y/m/d') }}
                    @if ($headerBranch)
                        <span class="rb-badge" title="المندوبون وزياراتهم مقصورون على هذا الفرع وما يتبع له — غيّره من أعلى الصفحة"><i class="fas fa-filter"></i> {{ $headerBranch }}</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="rb-head__actions">
            <a href="{{ route('itinerary.list', $periodQuery) }}" class="rb-btn rb-btn--light"><i class="fas fa-route"></i> خطوط السير</a>
            @permission('clients_itinerary_today_notes_view')
                <a href="{{ route('itinerary.today-notes') }}" class="rb-btn rb-btn--light"><i class="far fa-sticky-note"></i> ملاحظات اليوم</a>
            @endpermission
            @if ($canAdd)
                <a href="{{ route('itinerary.create') }}" class="rb-btn rb-btn--success"><i class="fas fa-plus"></i> إضافة خط سير</a>
            @endif
        </div>
    </header>

    <div class="rb-layout">
        <aside class="rb-card rb-picker">
            <form method="GET" action="{{ route('itinerary.dashboard') }}" id="rb-filters" class="rb-filters">
                <div class="rb-picker__title"><i class="fas fa-sliders-h"></i> تصفية اللوحة</div>

                <div class="rb-field">
                    <span class="rb-field__label">الفترة</span>
                    <div class="rb-ranges">
                        @foreach ($ranges as $range)
                            <a href="{{ route('itinerary.dashboard', $scopeQuery + ['date_from' => $range['from'], 'date_to' => $range['to']]) }}"
                                class="rb-chip {{ $range['from'] === $from && $range['to'] === $to ? 'is-active' : '' }}">{{ $range['label'] }}</a>
                        @endforeach
                    </div>
                </div>

                <div class="rb-filters__dates">
                    <label class="rb-field">
                        <span class="rb-field__label">من تاريخ</span>
                        <span class="rb-select"><input type="date" name="date_from" value="{{ $from }}"></span>
                    </label>
                    <label class="rb-field">
                        <span class="rb-field__label">إلى تاريخ</span>
                        <span class="rb-select"><input type="date" name="date_to" value="{{ $to }}"></span>
                    </label>
                </div>

                @foreach ($levels as $level => [$label, $icon, $allLabel])
                    <label class="rb-field">
                        <span class="rb-field__label">{{ $label }}</span>
                        <span class="rb-select">
                            <i class="fas {{ $icon }}"></i>
                            <select class="rb-level" data-level="{{ $level }}">
                                <option value="">{{ $allLabel }}</option>
                            </select>
                        </span>
                    </label>
                @endforeach
                <input type="hidden" name="node_id" id="rb-node" value="{{ $filters['node_id'] }}">

                <label class="rb-field">
                    <span class="rb-field__label">المندوب</span>
                    <span class="rb-select">
                        <i class="fas fa-user"></i>
                        <select name="employee_id">
                            @if ($employees->count() !== 1)
                                <option value="">كل المندوبين</option>
                            @endif
                            @foreach ($employees as $option)
                                <option value="{{ $option->id }}" @selected((int) $filters['employee_id'] === (int) $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>

                <p class="rb-help">«زيارات اليوم» تعدّ اليوم نفسه بصرف النظر عن الفترة. الجهة تطابق «تابع لـ» للعميل.</p>

                <button type="submit" class="rb-btn rb-btn--primary rb-btn--block"><i class="fas fa-filter"></i> تطبيق</button>
                @if ($scopeQuery !== [] || request()->hasAny(['date_from', 'date_to']))
                    <a href="{{ route('itinerary.dashboard') }}" class="rb-btn rb-btn--light rb-btn--block"><i class="fas fa-times"></i> مسح التصفية</a>
                @endif
            </form>
        </aside>

        <main class="rb-main">
            <section class="rb-stats">
                <div class="rb-stat rb-stat--green">
                    <div>
                        <span class="rb-stat__label">نسبة الإنجاز</span>
                        <strong>{{ $kpi['rate'] }}%</strong>
                        <small class="rb-stat__hint">{{ number_format($kpi['completed']) }} مكتملة من {{ number_format($kpi['total'] - $kpi['cancelled']) }} (بلا الملغاة)</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-bullseye"></i></span>
                </div>
                <div class="rb-stat rb-stat--blue">
                    <div>
                        <span class="rb-stat__label">زيارات اليوم</span>
                        <strong>{{ number_format($kpi['today_total']) }}</strong>
                        <small class="rb-stat__hint">جارية {{ number_format($kpi['today_started']) }} · مكتملة {{ number_format($kpi['today_completed']) }}</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-calendar-day"></i></span>
                </div>
                <div class="rb-stat rb-stat--red">
                    <div>
                        <span class="rb-stat__label">المتأخرة</span>
                        <strong>{{ number_format($kpi['overdue']) }}</strong>
                        <small class="rb-stat__hint">فائتة بالفترة: {{ number_format($kpi['missed']) }}</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-exclamation-triangle"></i></span>
                </div>
                <div class="rb-stat rb-stat--navy">
                    <div>
                        <span class="rb-stat__label">مندوبون بخط سير</span>
                        <strong>{{ number_format($kpi['reps']) }}</strong>
                        <small class="rb-stat__hint">{{ number_format($kpi['clients']) }} عميل · {{ number_format($kpi['total']) }} زيارة بالفترة</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-user-tie"></i></span>
                </div>
            </section>

            <div class="rb-grid-2">
                <section class="rb-card">
                    <div class="rb-section-head">
                        <h2><i class="fas fa-chart-pie"></i> توزيع حالة الزيارات</h2>
                    </div>
                    <div id="rb-status-chart" class="rb-chart"></div>
                </section>
                <section class="rb-card">
                    <div class="rb-section-head">
                        <h2><i class="fas fa-chart-bar"></i> الزيارات يوماً بيوم</h2>
                        <div class="rb-legend">
                            <span><span class="rb-dot"></span> المخطَّطة</span>
                            <span><span class="rb-dot rb-dot--completed"></span> المكتملة</span>
                        </div>
                    </div>
                    <div id="rb-trend-chart" class="rb-chart"></div>
                </section>
            </div>

            <section class="rb-card rb-work">
                <div class="rb-section-head">
                    <h2><i class="fas fa-user-tie"></i> أداء المندوبين <small>{{ count($reps) }} مندوب</small></h2>
                    <span class="rb-help">الإنجاز = المكتملة ÷ (الإجمالي بلا الملغاة)</span>
                </div>
                @if ($reps === [])
                    <div class="rb-empty rb-empty--block"><p><strong>لا زيارات في هذه الفترة ضمن التصفية.</strong></p></div>
                @else
                    <div class="rb-table-wrap">
                        <table class="rb-table">
                            <thead>
                                <tr>
                                    <th>المندوب</th>
                                    <th>الفرع</th>
                                    <th class="rb-num">الإجمالي</th>
                                    <th class="rb-num">مكتملة</th>
                                    <th class="rb-num">جارية</th>
                                    <th class="rb-num">قادمة</th>
                                    <th class="rb-num">متأخرة</th>
                                    <th class="rb-num">فائتة</th>
                                    <th class="rb-num">ملغاة</th>
                                    <th>الإنجاز</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reps as $rep)
                                    <tr>
                                        <td class="rb-client-cell"><strong>{{ $rep['name'] }}</strong></td>
                                        <td>{{ $rep['branch'] ?? '—' }}</td>
                                        <td class="rb-num"><strong>{{ number_format($rep['total']) }}</strong></td>
                                        <td class="rb-num">{{ number_format($rep['completed']) }}</td>
                                        <td class="rb-num">{{ number_format($rep['started']) }}</td>
                                        <td class="rb-num">{{ number_format($rep['upcoming']) }}</td>
                                        <td class="rb-num">{{ number_format($rep['overdue']) }}</td>
                                        <td class="rb-num">{{ number_format($rep['missed']) }}</td>
                                        <td class="rb-num">{{ number_format($rep['cancelled']) }}</td>
                                        <td>
                                            <span class="rb-rate">
                                                <span class="rb-progress"><span style="width: {{ min(100, $rep['rate']) }}%"></span></span>
                                                <small>{{ $rep['rate'] }}%</small>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('itinerary.list', ['employee_id' => $rep['id'], 'date_from' => $from, 'date_to' => $to] + array_filter(['node_id' => $filters['node_id']])) }}"
                                                class="rb-icon rb-icon--edit" title="عرض خطوط سيره في الفترة"><i class="fas fa-external-link-alt"></i></a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <div class="rb-grid-2 rb-grid-2--even">
                <section class="rb-card">
                    <div class="rb-section-head">
                        <h2><i class="far fa-clock"></i> زيارات اليوم الآن <small>{{ today()->format('Y/m/d') }}</small></h2>
                    </div>
                    @if ($todayGroups === [])
                        <div class="rb-empty rb-empty--block"><p>لا زيارات مجدولة اليوم ضمن التصفية.</p></div>
                    @else
                        <div class="rb-timeline">
                            @foreach ($todayGroups as $group)
                                <div class="rb-timeline__rep">
                                    <div class="rb-timeline__head">
                                        @if (Route::has('itinerary.today-notes') && hasPermission('clients_itinerary_today_notes_view'))
                                            <a href="{{ route('itinerary.today-notes', ['employeeId' => $group['id']]) }}" title="ملاحظات يومه">{{ $group['name'] }}</a>
                                        @else
                                            <span>{{ $group['name'] }}</span>
                                        @endif
                                        <span class="rb-badge">{{ $group['completed'] }} / {{ $group['total'] }}</span>
                                    </div>
                                    <ul class="rb-timeline__list">
                                        @foreach ($group['visits'] as $visit)
                                            <li class="rb-dot-item" title="{{ $stateLabels[$visit['state']] ?? $visit['state'] }}">
                                                <span class="rb-dot rb-dot--{{ $visit['state'] }}"></span>
                                                {{ $visit['name'] }}
                                                @if ($visit['time'])
                                                    <small>{{ $visit['time'] }}</small>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="rb-card">
                    <div class="rb-section-head">
                        <h2><i class="fas fa-star"></i> العملاء الأكثر زيارة <small>زيارات مكتملة بالفترة</small></h2>
                    </div>
                    @if ($topClients === [])
                        <div class="rb-empty rb-empty--block"><p>لا زيارات مكتملة في هذه الفترة.</p></div>
                    @else
                        <div class="rb-table-wrap">
                            <table class="rb-table">
                                <thead><tr><th>#</th><th>العميل</th><th>الحالة</th><th>الفرع</th><th class="rb-num">الزيارات</th></tr></thead>
                                <tbody>
                                    @foreach ($topClients as $client)
                                        <tr>
                                            <td><span class="rb-order">{{ $loop->iteration }}</span></td>
                                            <td class="rb-client-cell"><strong><a href="{{ route('clients.show', $client['id']) }}" target="_blank">{{ $client['name'] }}</a></strong><small>{{ $client['code'] }}</small></td>
                                            <td>{!! $statusBadge($client['status']) !!}</td>
                                            <td>{{ $client['branch'] ?? '—' }}@if (! empty($client['unit']))<small class="rb-muted" style="display:block">{{ $client['unit'] }}</small>@endif</td>
                                            <td class="rb-num"><strong>{{ $client['visits'] }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <section class="rb-card rb-work">
                <div class="rb-section-head">
                    <h2><i class="fas fa-user-clock"></i> عملاء مُسنَدون بلا زيارة في الفترة <small>{{ number_format($unscheduledTotal) }} عميل</small></h2>
                    <span class="rb-help">مُسنَدون لمندوبي اللوحة ولم يُدرجوا في أيّ يومٍ من الفترة{{ $unscheduledTotal > count($unscheduled) ? ' — يُعرض أوّل '.count($unscheduled) : '' }}</span>
                </div>
                @if ($unscheduled === [])
                    <div class="rb-empty rb-empty--block"><p><i class="fas fa-check-circle"></i> كل العملاء المُسنَدين مُدرجون في خطوط السير.</p></div>
                @else
                    <div class="rb-table-wrap">
                        <table class="rb-table">
                            <thead><tr><th>العميل</th><th>الحالة</th><th>المندوب</th><th>المنطقة</th><th>الفرع</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($unscheduled as $client)
                                    <tr>
                                        <td class="rb-client-cell"><strong><a href="{{ route('clients.show', $client['id']) }}" target="_blank">{{ $client['name'] }}</a></strong><small>{{ $client['code'] }}</small></td>
                                        <td>{!! $statusBadge($client['status']) !!}</td>
                                        <td>{{ $client['rep'] ?? '—' }}</td>
                                        <td>{{ $client['region'] ?? '—' }}</td>
                                        <td>{{ $client['branch'] ?? '—' }}@if (! empty($client['unit']))<small class="rb-muted" style="display:block">{{ $client['unit'] }}</small>@endif</td>
                                        <td>
                                            @if ($canAdd && $client['rep_id'])
                                                <a href="{{ route('itinerary.create', ['employee_id' => $client['rep_id']]) }}" class="rb-icon rb-icon--edit" title="تخطيط خط سير المندوب"><i class="fas fa-calendar-plus"></i></a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </main>
    </div>
</div>
