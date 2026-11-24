{{--
    شاشة عرض خطوط السير. المعرّفات `rb-*` يقرؤها `itinerary-index-scripts`.
    الأصناف المشتركة من `route-builder-styles`، والخاصّة من `itinerary-index-styles`.
--}}
@php
    $levels = [
        'region' => ['المنطقة', 'fa-map-marker-alt', 'كل المناطق'],
        'branch' => ['الفرع', 'fa-building', 'كل الفروع'],
        'group' => ['المجموعة', 'fa-users', 'كل المجموعات'],
        'outlet' => ['المنفذ', 'fa-store', 'جميع المنافذ'],
    ];
    $filterQuery = array_filter([
        'node_id' => $filters['node_id'],
        'employee_id' => $filters['employee_id'],
        'date_from' => $filters['date_from']?->toDateString(),
        'date_to' => $filters['date_to']?->toDateString(),
    ]);
    $today = today()->toDateString();
    $dayTotals = [];
    foreach (array_keys($days) as $dayKey) {
        $dayTotals[$dayKey] = array_sum(array_map(fn ($rep) => $rep['days'][$dayKey]['visits'], $reps));
    }
@endphp

<div id="route-builder" class="rb rb--index" dir="rtl">
    <header class="rb-head">
        <div class="rb-head__title">
            <span class="rb-head__icon"><i class="fas fa-route"></i></span>
            <div>
                <h1>خطوط السير</h1>
                <p>
                    متابعة خطوط سير المندوبين أسبوعاً بأسبوع
                    @if ($headerBranch)
                        <span class="rb-badge" title="المندوبون وزياراتهم مقصورون على هذا الفرع وما يتبع له — غيّره من أعلى الصفحة"><i class="fas fa-filter"></i> {{ $headerBranch }}</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="rb-head__actions">
            <a href="{{ route('itinerary.dashboard') }}" class="rb-btn rb-btn--light"><i class="fas fa-chart-line"></i> لوحة التحكم</a>
            @permission('clients_itinerary_today_notes_view')
                <a href="{{ route('itinerary.today-notes') }}" class="rb-btn rb-btn--light"><i class="far fa-sticky-note"></i> ملاحظات اليوم</a>
            @endpermission
            @if (Route::has('itinerary.daily'))
                @permission('clients_daily_itinerary_view|clients_daily_itinerary_add_for_others')
                    <a href="{{ route('itinerary.daily') }}" class="rb-btn rb-btn--light"><i class="far fa-calendar-check"></i> خط سيري اليومي</a>
                @endpermission
            @endif
            @if ($canAdd)
                <a href="{{ route('itinerary.create') }}" class="rb-btn rb-btn--success"><i class="fas fa-plus"></i> إضافة خط سير</a>
            @endif
        </div>
    </header>

    <div class="rb-layout">
        <aside class="rb-card rb-picker">
            <form method="GET" action="{{ route('itinerary.list') }}" id="rb-filters" class="rb-filters">
                <div class="rb-picker__title"><i class="fas fa-sliders-h"></i> تصفية خطوط السير</div>

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

                <div class="rb-filters__dates">
                    <label class="rb-field">
                        <span class="rb-field__label">من تاريخ</span>
                        <span class="rb-select"><input type="date" name="date_from" value="{{ $filters['date_from']?->toDateString() }}"></span>
                    </label>
                    <label class="rb-field">
                        <span class="rb-field__label">إلى تاريخ</span>
                        <span class="rb-select"><input type="date" name="date_to" value="{{ $filters['date_to']?->toDateString() }}"></span>
                    </label>
                </div>
                <p class="rb-help">التواريخ تقصر الأسابيع والجدول على زياراتٍ في هذه الفترة، والبطاقات تعدّ الآن بصرف النظر عنها. الجهة تطابق «تابع لـ» للعميل.</p>

                <button type="submit" class="rb-btn rb-btn--primary rb-btn--block"><i class="fas fa-filter"></i> تطبيق</button>
                @if ($filterQuery !== [])
                    <a href="{{ route('itinerary.list') }}" class="rb-btn rb-btn--light rb-btn--block"><i class="fas fa-times"></i> مسح التصفية</a>
                @endif
            </form>
        </aside>

        <main class="rb-main">
            <section class="rb-stats">
                <div class="rb-stat rb-stat--blue">
                    <div>
                        <span class="rb-stat__label">زيارات اليوم</span>
                        <strong>{{ number_format($stats['today']) }}</strong>
                        <small class="rb-stat__hint">قيد التنفيذ الآن: {{ number_format($stats['in_progress']) }}</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-calendar-day"></i></span>
                </div>
                <div class="rb-stat rb-stat--navy">
                    <div>
                        <span class="rb-stat__label">القادمة</span>
                        <strong>{{ number_format($stats['upcoming']) }}</strong>
                        <small class="rb-stat__hint">مخطَّطة بعد اليوم</small>
                    </div>
                    <span class="rb-stat__icon"><i class="far fa-calendar-plus"></i></span>
                </div>
                <div class="rb-stat rb-stat--red">
                    <div>
                        <span class="rb-stat__label">المتأخرة</span>
                        <strong>{{ number_format($stats['overdue']) }}</strong>
                        <small class="rb-stat__hint">مخطَّطة فات يومها ولم تبدأ</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-exclamation-triangle"></i></span>
                </div>
                <div class="rb-stat rb-stat--green">
                    <div>
                        <span class="rb-stat__label">المكتملة</span>
                        <strong>{{ number_format($stats['completed']) }}</strong>
                        <small class="rb-stat__hint">ملغاة: {{ number_format($stats['cancelled']) }}</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-check-circle"></i></span>
                </div>
            </section>

            <section class="rb-card rb-daybar">
                <div class="rb-days rb-weeks">
                    @forelse ($weeks as $week)
                        <a href="{{ route('itinerary.list', $filterQuery + ['week' => $week['key']]) }}"
                            class="rb-day rb-week {{ $selectedWeek && $selectedWeek['key'] === $week['key'] ? 'is-active' : '' }} {{ $week['visits'] ? 'has-clients' : '' }}"
                            title="{{ $week['reps'] }} مندوب · {{ $week['completed'] }} مكتملة من {{ $week['visits'] }}">
                            <span class="rb-week__text">
                                الأسبوع {{ $week['week'] }}
                                <small>{{ $week['label'] }}</small>
                            </span>
                            <span class="rb-day__count">{{ number_format($week['visits']) }}</span>
                        </a>
                    @empty
                        <span class="rb-help">لا أسابيع ضمن هذه التصفية.</span>
                    @endforelse
                </div>
                @if ($selectedWeek)
                    <div class="rb-week-summary">
                        <span><i class="fas fa-users"></i> {{ number_format($selectedWeek['reps']) }} مندوب</span>
                        <span><i class="fas fa-store"></i> {{ number_format($selectedWeek['visits']) }} زيارة</span>
                        <span class="rb-progress" title="{{ $selectedWeek['completed'] }} مكتملة"><span style="width: {{ $selectedWeek['rate'] }}%"></span></span>
                        <strong>{{ $selectedWeek['rate'] }}%</strong>
                    </div>
                @endif
            </section>

            <section class="rb-card rb-work">
                <div class="rb-section-head">
                    <h2>
                        <i class="fas fa-user-tie"></i>
                        مندوبو الأسبوع {{ $selectedWeek['week'] ?? '' }}
                        @if ($selectedWeek)
                            <small>{{ $selectedWeek['label'] }} {{ $selectedWeek['year'] }}</small>
                        @endif
                    </h2>
                    <span class="rb-help">اضغط عدد زيارات يومٍ لعرض عملائه</span>
                </div>

                @if ($reps === [])
                    <div class="rb-empty rb-empty--block">
                        <p><i class="fas fa-route fa-2x"></i></p>
                        <p><strong>لا خطوط سير في هذا الأسبوع</strong></p>
                        <p>{{ $filterQuery !== [] ? 'لا زيارات تطابق التصفية الحالية.' : 'لم يُخطَّط لأي مندوب في هذا الأسبوع بعد.' }}</p>
                        @if ($canAdd && $selectedWeek)
                            <a href="{{ route('itinerary.create', ['year' => $selectedWeek['year'], 'week' => $selectedWeek['week']]) }}" class="rb-btn rb-btn--primary"><i class="fas fa-plus"></i> تخطيط هذا الأسبوع</a>
                        @endif
                    </div>
                @else
                    <div class="rb-table-wrap rb-table-wrap--grid">
                        <table class="rb-table rb-grid">
                            <thead>
                                <tr>
                                    <th>المندوب</th>
                                    <th>الفرع</th>
                                    @foreach ($days as $dayKey => $dayMeta)
                                        @php $date = $selectedWeek['dates'][$dayKey]; @endphp
                                        <th class="rb-grid__day {{ $date === $today ? 'is-today' : '' }}">
                                            {{ $dayMeta['name'] }}
                                            <small>{{ \Carbon\Carbon::parse($date)->format('j/n') }}</small>
                                        </th>
                                    @endforeach
                                    <th>الإجمالي</th>
                                    <th>الإنجاز</th>
                                    @if ($canEdit)
                                        <th>إجراءات</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reps as $rep)
                                    <tr>
                                        <td class="rb-client-cell"><strong>{{ $rep['name'] }}</strong></td>
                                        <td>{{ $rep['branch'] ?? '—' }}</td>
                                        @foreach ($days as $dayKey => $dayMeta)
                                            @php
                                                $cell = $rep['days'][$dayKey];
                                                $isToday = $selectedWeek['dates'][$dayKey] === $today;
                                            @endphp
                                            <td class="rb-grid__cell {{ $isToday ? 'is-today' : '' }}">
                                                <button type="button"
                                                    class="rb-cell {{ $cell['visits'] ? '' : 'is-empty' }} {{ $cell['visits'] && $cell['completed'] === $cell['visits'] ? 'is-done' : '' }}"
                                                    data-employee="{{ $rep['id'] }}" data-day="{{ $dayKey }}"
                                                    title="{{ $dayMeta['name'] }}: {{ $cell['visits'] }} عميل، {{ $cell['completed'] }} مكتملة"
                                                    @disabled(! $cell['visits'])>
                                                    <strong>{{ $cell['visits'] }}</strong>
                                                    @if ($cell['visits'])
                                                        <small><i class="fas fa-check"></i> {{ $cell['completed'] }}</small>
                                                    @endif
                                                </button>
                                            </td>
                                        @endforeach
                                        <td><strong>{{ number_format($rep['visits']) }}</strong></td>
                                        <td>
                                            <span class="rb-rate">
                                                <span class="rb-progress"><span style="width: {{ $rep['rate'] }}%"></span></span>
                                                <small>{{ $rep['rate'] }}%</small>
                                            </span>
                                        </td>
                                        @if ($canEdit)
                                            <td>
                                                <a href="{{ route('itinerary.edit', ['id' => $rep['id'], 'year' => $selectedWeek['year'], 'week' => $selectedWeek['week']]) }}"
                                                    class="rb-icon rb-icon--edit" title="تعديل خط سير الأسبوع"><i class="far fa-edit"></i></a>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">الإجمالي ({{ count($reps) }} مندوب)</td>
                                    @foreach ($days as $dayKey => $dayMeta)
                                        <td class="{{ $selectedWeek['dates'][$dayKey] === $today ? 'is-today' : '' }}">{{ number_format($dayTotals[$dayKey]) }}</td>
                                    @endforeach
                                    <td>{{ number_format(array_sum($dayTotals)) }}</td>
                                    <td colspan="{{ $canEdit ? 2 : 1 }}"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>

            <section id="rb-day-panel" class="rb-card rb-work" hidden>
                <div class="rb-section-head">
                    <h2>
                        <i class="far fa-calendar-alt"></i>
                        <span id="rb-day-title">زيارات اليوم</span>
                        <small id="rb-day-date"></small>
                        <span id="rb-day-locked" class="rb-badge rb-badge--warn" hidden><i class="fas fa-lock"></i> مقفل بعد بدء الزيارات</span>
                    </h2>
                    <div class="rb-day-actions">
                        @if ($canEdit)
                            <button type="button" id="rb-move" class="rb-btn rb-btn--light rb-btn--sm"><i class="fas fa-share"></i> نقل غير المكتمل</button>
                            <button type="button" id="rb-delete-selected" class="rb-btn rb-btn--light rb-btn--sm" disabled><i class="far fa-trash-alt"></i> <span>حذف المحدد</span></button>
                            <button type="button" id="rb-delete-day" class="rb-btn rb-btn--danger-light rb-btn--sm"><i class="fas fa-trash"></i> حذف اليوم</button>
                            <a id="rb-day-edit" class="rb-btn rb-btn--primary rb-btn--sm" href="#"><i class="far fa-edit"></i> تعديل الخط</a>
                        @endif
                        <button type="button" id="rb-day-close" class="rb-icon" title="إغلاق"><i class="fas fa-times"></i></button>
                    </div>
                </div>

                {{-- دليلُ ألوان العملاء بعددهم في اليوم — يملؤه renderToneLegend() --}}
                <div id="rb-day-legend" class="rb-tone-legend"></div>

                <div class="rb-tabs" role="tablist">
                    <button type="button" class="rb-tab is-active" data-view="split"><i class="fas fa-list-ul"></i> قائمة الزيارات</button>
                    <button type="button" class="rb-tab" data-view="map"><i class="fas fa-map-marker-alt"></i> الخريطة</button>
                </div>

                <div id="rb-work" class="rb-work__body" data-view="split">
                    <div class="rb-pane rb-pane--table">
                        <div class="rb-table-wrap">
                            <table class="rb-table">
                                <thead>
                                    <tr>
                                        @if ($canEdit)
                                            <th><input type="checkbox" id="rb-check-all" class="rb-check-input" aria-label="تحديد الكل"></th>
                                        @endif
                                        <th>الترتيب</th>
                                        <th>اسم العميل</th>
                                        <th>حالة العميل</th>
                                        <th>الزيارة</th>
                                        <th>المنطقة</th>
                                        <th>الفرع</th>
                                        @if ($canEdit)
                                            <th></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody id="rb-visits"></tbody>
                            </table>
                        </div>
                        <div class="rb-pane__foot">
                            <span><i class="fas fa-users"></i> عملاء اليوم: <strong id="rb-day-count">0</strong></span>
                            <span><i class="fas fa-check"></i> المكتملة: <strong id="rb-day-done">0</strong></span>
                        </div>
                    </div>

                    <div class="rb-pane rb-pane--map">
                        <div id="rb-map" class="rb-map"></div>
                        <div id="rb-map-notice" class="rb-map-notice" hidden></div>
                        <div class="rb-pane__foot">
                            <div class="rb-metric">
                                <i class="fas fa-route"></i>
                                <div><span>المسافة التقديرية</span><strong id="rb-distance">—</strong></div>
                            </div>
                            <a id="rb-open-gmaps" class="rb-btn rb-btn--primary is-disabled" target="_blank" rel="noopener">فتح في خرائط جوجل</a>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    @if ($canEdit)
        <div class="rb-modal" id="rb-move-modal" hidden>
            <div class="rb-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="rb-move-title">
                <h3 id="rb-move-title">نقل الزيارات غير المكتملة</h3>
                <p class="rb-help">تُنقل الزيارات المحدَّدة إلى اليوم المختار وتعود «مخطَّطة» فيه، ويبقى أثرُ النقل عليها. العميلُ الموجود أصلاً في اليوم الجديد تُدمج زيارتُه.</p>
                <div id="rb-move-list" class="rb-client-list rb-move-list"></div>
                <div class="rb-move-targets">
                    <label class="rb-field">
                        <span class="rb-field__label">السنة</span>
                        <span class="rb-select">
                            <select id="rb-move-year">
                                @for ($year = $currentWeek['year']; $year <= $currentWeek['year'] + 1; $year++)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                @endfor
                            </select>
                        </span>
                    </label>
                    <label class="rb-field">
                        <span class="rb-field__label">الأسبوع</span>
                        <span class="rb-select"><select id="rb-move-week"></select></span>
                    </label>
                    <label class="rb-field">
                        <span class="rb-field__label">اليوم</span>
                        <span class="rb-select">
                            <select id="rb-move-day">
                                @foreach ($days as $dayKey => $dayMeta)
                                    <option value="{{ $dayKey }}">{{ $dayMeta['name'] }}</option>
                                @endforeach
                            </select>
                        </span>
                    </label>
                </div>
                <div class="rb-modal__actions">
                    <button type="button" class="rb-btn rb-btn--primary" id="rb-move-submit"><i class="fas fa-check"></i> تأكيد النقل</button>
                    <button type="button" class="rb-btn rb-btn--light" data-close>إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</div>
