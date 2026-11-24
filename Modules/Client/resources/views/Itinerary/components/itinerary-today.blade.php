{{--
    ملاحظات اليوم لخط السير — بتصميم شاشات خط السير وترتيبها.
    المُتحكِّم `ItineraryController::getTodayNotes()`؛ الخريطة والتتبّع في
    `itinerary-today-scripts`.

    **بطاقةُ الملاحظة مكتوبةٌ هنا لا مُضمَّنة**: هي نسخةُ `partials/tabs/visit-note-card`
    حرفياً (بياناتُ `VisitNoteCards` وتنسيقُ `visit_note_cards.css` نفسُهما) — غيّر
    الاثنين معاً. التضمينُ لكل بطاقة كان يُشغّل مُركِّبات العرض العامّة (`View::composer('*')`
    — منها استعلامُ زيارات اليوم) وفحصَي الصلاحية مرّةً لكل بطاقة: 150 بطاقة ⇒ نحو
    700 استعلامٍ و18 ثانية.
--}}
@php
    $dayName = \App\Services\Itinerary\ItineraryWeekService::dayName($dayKey);

    // صلاحيتا تعديل الملاحظات مرّةً للصفحة — لا لكل بطاقة
    $canEditAllNotes = auth()->user()->can('client_follow_up_edit_delete_notes_attachments_appointments_all');
    $canEditOwnNotes = auth()->user()->can('client_follow_up_edit_delete_notes_attachments_appointments_own');
    $dateString = $date->toDateString();
    $isToday = $date->isToday();
    $employeeKey = $employee?->id;
    $pageLink = fn (array $extra = []) => route('itinerary.today-notes', array_filter(
        ['employeeId' => $employeeKey, 'date' => $dateString, 'visit_status' => $visitStatus] + $extra,
        fn ($value) => $value !== null && $value !== ''
    ));
    $stateLabels = ['planned' => 'مخطَّطة', 'started' => 'جارية', 'completed' => 'مكتملة', 'missed' => 'فائتة', 'cancelled' => 'ملغاة'];
    // لونُ العميل في يومه — نفسُ دليل شاشة «خطوط السير»
    $tones = \App\Services\Itinerary\DailyItineraryService::VISIT_TONES;
    $toneCounts = collect($clients)->countBy('tone');
    $statusBadge = function ($status) {
        if (! $status || ! ($status['name'] ?? null)) {
            return '';
        }
        $color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($status['color'] ?? '')) ? $status['color'] : null;

        return '<span class="rb-status"'.($color ? ' style="--rb-status:'.e($color).'"' : '').'>'.e($status['name']).'</span>';
    };
@endphp

<link rel="stylesheet" href="{{ asset('assets/css/visit_note_cards.css') }}?v=4">

<div id="route-builder" class="rb rb--today" dir="rtl">
    <header class="rb-head">
        <div class="rb-head__title">
            <span class="rb-head__icon"><i class="far fa-sticky-note"></i></span>
            <div>
                <h1>ملاحظات اليوم</h1>
                <p>
                    @if ($employee)
                        {{ $employee->name }} — يوم {{ $dayName }} {{ $date->format('Y/m/d') }}
                    @else
                        اختر مندوباً لعرض عملاء يومه وحالة زياراتهم وآخر ملاحظاتهم
                    @endif
                    @if ($headerBranch)
                        <span class="rb-badge" title="المندوبون مقصورون على هذا الفرع وما يتبع له — غيّره من أعلى الصفحة"><i class="fas fa-filter"></i> {{ $headerBranch }}</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="rb-head__actions">
            <a href="{{ route('itinerary.list') }}" class="rb-btn rb-btn--light"><i class="fas fa-route"></i> خطوط السير</a>
            <a href="{{ route('itinerary.dashboard') }}" class="rb-btn rb-btn--light"><i class="fas fa-chart-line"></i> لوحة التحكم</a>
            @if ($employee && $clients !== [])
                <button type="button" id="rb-print" class="rb-btn rb-btn--primary"><i class="fas fa-print"></i> طباعة</button>
            @endif
        </div>
    </header>

    <div class="rb-layout">
        <aside class="rb-card rb-picker">
            <form method="GET" action="{{ route('itinerary.today-notes') }}" id="rb-today-form" class="rb-filters">
                <div class="rb-picker__title"><i class="fas fa-user-clock"></i> يوم المندوب</div>

                <label class="rb-field">
                    <span class="rb-field__label">المندوب</span>
                    <span class="rb-select">
                        <i class="fas fa-user"></i>
                        <select name="employee_id" @disabled($employeeLocked)>
                            @unless ($employeeLocked)
                                <option value="">-- اختر المندوب --</option>
                            @endunless
                            @foreach ($employees as $option)
                                <option value="{{ $option->id }}" @selected($employee && (int) $employee->id === (int) $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>

                <div class="rb-field">
                    <span class="rb-field__label">التاريخ</span>
                    <div class="rb-date-nav">
                        <a class="rb-btn rb-btn--light rb-btn--sm" href="{{ $pageLink(['date' => $date->copy()->subDay()->toDateString()]) }}" title="اليوم السابق"><i class="fas fa-chevron-right"></i></a>
                        <span class="rb-select"><input type="date" name="date" value="{{ $dateString }}"></span>
                        <a class="rb-btn rb-btn--light rb-btn--sm" href="{{ $pageLink(['date' => $date->copy()->addDay()->toDateString()]) }}" title="اليوم التالي"><i class="fas fa-chevron-left"></i></a>
                    </div>
                    @unless ($isToday)
                        <a href="{{ $pageLink(['date' => today()->toDateString()]) }}" class="rb-chip" style="align-self: flex-start">العودة إلى اليوم</a>
                    @endunless
                </div>

                <label class="rb-field">
                    <span class="rb-field__label">حالة الزيارة</span>
                    <span class="rb-select">
                        <i class="fas fa-tasks"></i>
                        <select name="visit_status">
                            <option value="">كل الحالات</option>
                            @foreach ($stateLabels as $value => $label)
                                <option value="{{ $value }}" @selected($visitStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>

                <button type="submit" class="rb-btn rb-btn--primary rb-btn--block"><i class="fas fa-search"></i> عرض</button>

                @if ($employee)
                    @if ($trackingEnabled)
                        <div id="rb-live" class="rb-live"><i class="fas fa-satellite-dish"></i> <span>جارٍ جلب آخر موقع للمندوب…</span></div>
                    @else
                        <div class="rb-live"><i class="fas fa-eye-slash"></i> <span>تتبّع خط السير معطّل من إعدادات العملاء.</span></div>
                    @endif
                @endif
            </form>
        </aside>

        <main class="rb-main">
            @if (! $employee)
                <section class="rb-card rb-work">
                    <div class="rb-section-head">
                        <h2><i class="fas fa-users"></i> مندوبو يوم {{ $dayName }} <small>{{ $date->format('Y/m/d') }}</small></h2>
                        <span class="rb-help">اختر مندوباً لعرض عملائه وملاحظاتهم</span>
                    </div>
                    @if ($repsToday === [])
                        <div class="rb-empty rb-empty--block">
                            <p><i class="far fa-calendar-times fa-2x"></i></p>
                            <p><strong>لا زيارات مجدولة في هذا اليوم</strong></p>
                            <p>{{ $employees->isEmpty() ? 'لا مندوبين ضمن نطاقك.' : 'اختر مندوباً ويوماً آخر من اللوحة الجانبية.' }}</p>
                        </div>
                    @else
                        <div class="rb-reps-grid">
                            @foreach ($repsToday as $rep)
                                @php $rate = $rep['total'] ? (int) round($rep['completed'] * 100 / $rep['total']) : 0; @endphp
                                <a class="rb-rep-card" href="{{ route('itinerary.today-notes', ['employeeId' => $rep['id'], 'date' => $dateString]) }}">
                                    <strong>{{ $rep['name'] }}</strong>
                                    <small>{{ $rep['branch'] ?? '—' }}</small>
                                    <span class="rb-rate">
                                        <span class="rb-progress"><span style="width: {{ $rate }}%"></span></span>
                                        <small>{{ $rep['completed'] }} / {{ $rep['total'] }} مكتملة</small>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @else
                <div class="rb-print-only">
                    <h2>ملاحظات يوم {{ $dayName }} {{ $date->format('Y/m/d') }} — {{ $employee->name }}</h2>
                </div>

                <section class="rb-stats">
                    <div class="rb-stat rb-stat--blue">
                        <div>
                            <span class="rb-stat__label">عملاء اليوم</span>
                            <strong>{{ number_format($stats['total']) }}</strong>
                            <small class="rb-stat__hint">مكتملة {{ number_format($stats['completed']) }} · جارية {{ number_format($stats['started']) }}</small>
                        </div>
                        <span class="rb-stat__icon"><i class="fas fa-store"></i></span>
                    </div>
                    <div class="rb-stat rb-stat--navy">
                        <div>
                            <span class="rb-stat__label">المتبقية</span>
                            <strong>{{ number_format($stats['remaining']) }}</strong>
                            <small class="rb-stat__hint">فائتة {{ number_format($stats['missed']) }} · ملغاة {{ number_format($stats['cancelled']) }}</small>
                        </div>
                        <span class="rb-stat__icon"><i class="far fa-hourglass"></i></span>
                    </div>
                    <div class="rb-stat rb-stat--red">
                        <div>
                            <span class="rb-stat__label">أرصدة عملاء اليوم</span>
                            <strong>{{ number_format($stats['balance'], 2) }}</strong>
                            <small class="rb-stat__hint">مجموع ذمم عملاء هذا اليوم</small>
                        </div>
                        <span class="rb-stat__icon"><i class="fas fa-wallet"></i></span>
                    </div>
                    <div class="rb-stat rb-stat--green">
                        <div>
                            <span class="rb-stat__label">ملاحظات اليوم</span>
                            <strong>{{ number_format($stats['notes_today']) }}</strong>
                            <small class="rb-stat__hint">سجّلها المندوب في هذا التاريخ</small>
                        </div>
                        <span class="rb-stat__icon"><i class="far fa-edit"></i></span>
                    </div>
                </section>

                <section class="rb-card rb-daybar">
                    <div class="rb-days">
                        @foreach ($weekDays as $weekDay)
                            <a href="{{ $pageLink(['date' => $weekDay['date']]) }}"
                                class="rb-day rb-week {{ $weekDay['date'] === $dateString ? 'is-active' : '' }} {{ $weekDay['total'] ? 'has-clients' : '' }}"
                                title="{{ $weekDay['completed'] }} مكتملة من {{ $weekDay['total'] }}">
                                <span class="rb-week__text">{{ $weekDay['name'] }} <small>{{ \Carbon\Carbon::parse($weekDay['date'])->format('j/n') }}</small></span>
                                <span class="rb-day__count">{{ $weekDay['total'] }}</span>
                            </a>
                        @endforeach
                    </div>
                    <div class="rb-week-summary">
                        <span><i class="far fa-calendar-alt"></i> الأسبوع {{ $weekNumber }}</span>
                        @if (Route::has('itinerary.edit') && hasPermission('clients_itinerary_edit'))
                            <a href="{{ route('itinerary.edit', ['id' => $employee->id, 'date' => $dateString]) }}" class="rb-chip"><i class="far fa-edit"></i> تعديل خط اليوم</a>
                        @endif
                    </div>
                </section>

                <section class="rb-card rb-work">
                    <div class="rb-section-head">
                        <h2><i class="fas fa-store"></i> عملاء يوم {{ $dayName }} <small>{{ count($clients) }} عميل{{ $visitStatus ? ' — '.$stateLabels[$visitStatus] : '' }}</small></h2>
                        <div class="rb-legend">
                            @foreach ($stateLabels as $value => $label)
                                <span><span class="rb-dot rb-dot--{{ $value }}"></span> {{ $label }}</span>
                            @endforeach
                        </div>
                    </div>

                    @if ($clients !== [])
                        <div class="rb-tone-legend">
                            @foreach ($tones as $toneKey => $tone)
                                <div class="rb-tone-legend__item" style="--tone: {{ $tone['color'] }}">
                                    <div><strong>{{ $tone['label'] }}</strong><small>{{ $tone['hint'] }}</small></div>
                                    <span class="rb-tone-legend__count">{{ $toneCounts->get($toneKey, 0) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="rb-tabs" role="tablist">
                        <button type="button" class="rb-tab is-active" data-view="list"><i class="far fa-sticky-note"></i> العملاء والملاحظات</button>
                        <button type="button" class="rb-tab" data-view="map"><i class="fas fa-map-marker-alt"></i> الخريطة</button>
                    </div>

                    <div id="rb-today-body" class="rb-today-body" data-view="list">
                        <div class="rb-visit-list">
                            @forelse ($clients as $client)
                                <article class="rb-visit-card rb-visit-card--{{ $client['state'] }} rb-has-tone" style="--tone: {{ $tones[$client['tone']]['color'] }}">
                                    <div class="rb-visit-card__head">
                                        <span class="rb-order">{{ $loop->iteration }}</span>
                                        <div class="rb-visit-card__main">
                                            <div class="rb-visit-card__title">
                                                <a href="{{ route('clients.show', $client['id']) }}" target="_blank" rel="noopener" title="فتح صفحة العميل">{{ $client['name'] }} <i class="fas fa-external-link-alt" style="font-size: .7em"></i></a>
                                                <span class="rb-tone" style="--tone: {{ $tones[$client['tone']]['color'] }}" title="{{ $tones[$client['tone']]['hint'] }}">{{ $tones[$client['tone']]['label'] }}</span>
                                                {!! $statusBadge($client['status']) !!}
                                                @if ($client['source'])
                                                    <span class="rb-src rb-src--{{ $client['source']['tone'] === 'self' ? 'self' : 'other' }}" title="أُضيف من «خط سيري اليومي»"><i class="fas fa-user-plus"></i> {{ $client['source']['label'] }}</span>
                                                @endif
                                            </div>
                                            <div class="rb-visit-card__meta">
                                                <span><i class="fas fa-hashtag"></i> {{ $client['code'] ?: '—' }}</span>
                                                @if ($client['region'] || $client['branch'] || ($client['unit'] ?? null))
                                                    <span><i class="fas fa-map-marker-alt"></i> {{ implode(' · ', array_filter([$client['region'], $client['branch'], $client['unit'] ?? null])) }}</span>
                                                @endif
                                                @if ($client['last_visit'])
                                                    <span><i class="fas fa-history"></i> آخر زيارة: {{ $client['last_visit'] }}</span>
                                                @endif
                                                @if ($client['lat'] === null)
                                                    <span class="rb-badge rb-badge--warn">بلا موقع</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="rb-visit-card__side">
                                            <span class="rb-vstate rb-vstate--{{ $client['state'] }}">
                                                {{ $stateLabels[$client['state']] ?? $client['state'] }}
                                                @if ($client['state'] === 'completed' && $client['completed_at'])
                                                    {{ $client['completed_at'] }}
                                                @elseif ($client['started_at'])
                                                    {{ $client['started_at'] }}
                                                @endif
                                            </span>
                                            @if ($client['moved_from'])
                                                <span class="rb-badge rb-badge--muted">منقولة من {{ $client['moved_from'] }}</span>
                                            @endif
                                            @if ($client['field_visit'])
                                                <span class="rb-badge" title="سُجّلت زيارةٌ ميدانية لهذا العميل في هذا اليوم"><i class="fas fa-walking"></i> زيارة ميدانية</span>
                                            @endif
                                            @if ($client['balance'] !== null)
                                                <span class="rb-balance" title="رصيد ذمّة العميل">{{ number_format($client['balance'], 2) }} {!! $currencySymbol !!}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="rb-visit-card__notes">
                                        <div class="rb-notes-head">
                                            <strong><i class="fas fa-history"></i> آخر الملاحظات</strong>
                                            <span class="rb-help">{{ $client['notes_shown'] }} من {{ $client['notes_total'] }}</span>
                                            @if ($client['notes_total'] > $client['notes_shown'])
                                                <a class="rb-link" href="{{ route('clients.show', $client['id']) }}" target="_blank" rel="noopener">كل الملاحظات</a>
                                            @endif
                                            @if (count($client['notes']))
                                                <button type="button" class="rb-link" data-toggle-notes="{{ $client['id'] }}">إخفاء</button>
                                            @endif
                                        </div>
                                        <div id="rb-notes-{{ $client['id'] }}">
                                            @if (count($client['notes']))
                                                <div class="vn-grid">
                                                    @foreach ($client['notes'] as $note)
                                                        @php
                                                            $number = $loop->iteration;
                                                            $card = $client['cards'][$note->id];
                                                            $vnTone = ['success', 'primary', 'warning'][($number - 1) % 3];
                                                            $vnDocs = $card['documents'];
                                                            $vnCanEdit = $canEditAllNotes || ($canEditOwnNotes && $note->employee_id == auth()->id());
                                                            $vnAttachCount = count($card['images']) + count($card['files']);
                                                        @endphp
                                                        <article class="vn-card">
                                                            <header class="vn-head">
                                                                <span class="vn-num vn-num-{{ $vnTone }}">{{ $number }}</span>
                                                                <div class="vn-who">
                                                                    <div class="vn-title">
                                                                        <span class="vn-title-icon"><i class="fas fa-user"></i></span>
                                                                        <span title="{{ $card['employee'] }}">{{ $card['employee'] }}</span>
                                                                    </div>
                                                                    <div class="vn-sub">
                                                                        @if ($card['process'])
                                                                            <span><i class="fas fa-tag"></i>{{ $card['process'] }}</span>
                                                                        @endif
                                                                        @if ($card['site_type'])
                                                                            <span><i class="fas fa-store"></i>{{ $card['site_type'] }}</span>
                                                                        @endif
                                                                        @if ($card['visit']['number'] ?? null)
                                                                            <span><i class="fas fa-hashtag"></i><bdi>{{ $card['visit']['number'] }}</bdi></span>
                                                                        @endif
                                                                        @if ($card['entries'] > 1)
                                                                            <span title="ملاحظة الزيارة وملاحظات مستنداتها في بطاقةٍ واحدة"><i class="fas fa-layer-group"></i>ملاحظات مدمجة: {{ $card['entries'] }}</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <div class="vn-when">
                                                                    @if ($card['date_label'])
                                                                        <span><i class="fas fa-calendar-alt"></i>{{ $card['date_label'] }}</span>
                                                                    @endif
                                                                    @if ($card['time_label'])
                                                                        <span><i class="fas fa-clock"></i>{{ $card['time_label'] }}</span>
                                                                    @endif
                                                                    @if ($card['visit'])
                                                                        <span class="vn-status vn-status-{{ $card['visit']['tone'] }}">
                                                                            <i class="fas {{ $card['visit']['icon'] }}"></i>{{ $card['visit']['label'] }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </header>

                                                            <div class="vn-note" title="{{ $card['description'] }}">
                                                                <p><span class="vn-note-label">ملاحظة:</span> {{ $card['description'] !== '' ? $card['description'] : 'لا يوجد وصف' }}</p>
                                                                <span class="vn-note-icon"><i class="fas fa-file-alt"></i></span>
                                                            </div>

                                                            <div class="vn-facts">
                                                                <div class="vn-fact">
                                                                    <div class="vn-fact-label"><i class="fas fa-file-invoice vn-ic-doc"></i> المستندات</div>
                                                                    @forelse (array_slice($vnDocs, 0, 3) as $vnDoc)
                                                                        <div class="vn-doc">
                                                                            <div class="vn-doc-kind"><i class="fas {{ $vnDoc['icon'] }}"></i> {{ $vnDoc['kind'] }}</div>
                                                                            <a class="vn-doc-no" href="{{ $vnDoc['url'] }}" target="_blank" rel="noopener"><bdi dir="ltr">#{{ $vnDoc['number'] }}</bdi></a>
                                                                            <div class="vn-amount vn-amount-{{ $vnDoc['tone'] }}"><bdi dir="ltr">{{ number_format($vnDoc['amount'], 2) }}</bdi> {!! $currencySymbol !!}</div>
                                                                        </div>
                                                                    @empty
                                                                        <div class="vn-empty">لم يتم إنشاء مستند</div>
                                                                    @endforelse
                                                                    @if (count($vnDocs) > 3)
                                                                        <span class="vn-more">+{{ count($vnDocs) - 3 }} مستند آخر في الزيارة</span>
                                                                    @endif
                                                                </div>

                                                                <div class="vn-fact">
                                                                    <div class="vn-fact-label"><i class="fas fa-chart-bar vn-ic-comp"></i> المنافس</div>
                                                                    @forelse ($card['competitors'] as $vnCompetitor)
                                                                        <div class="vn-comp-item">
                                                                            @if ($vnCompetitor['photo_url'])
                                                                                <a href="{{ $vnCompetitor['photo_url'] }}" data-fancybox="vn-comp-{{ $note->id }}" data-caption="{{ $vnCompetitor['name'] }}">
                                                                                    <img class="vn-comp-thumb" src="{{ $vnCompetitor['photo_url'] }}" alt="" loading="lazy">
                                                                                </a>
                                                                            @else
                                                                                <span class="vn-comp-thumb vn-comp-thumb-empty"><i class="fas fa-image"></i></span>
                                                                            @endif
                                                                            <div class="vn-comp-body">
                                                                                <div class="vn-line"><span title="{{ $vnCompetitor['name'] }}">{{ $vnCompetitor['name'] }}</span></div>
                                                                                <div class="vn-count"><b>{{ $vnCompetitor['asset_count'] }}</b> {{ $vnCompetitor['asset_label'] }}</div>
                                                                            </div>
                                                                        </div>
                                                                    @empty
                                                                        {{-- «لا يوجد منافس» مع صورة إثباته إن صوّرها المندوب --}}
                                                                        <div class="vn-empty">{{ $card['no_competitor'] ? 'لا يوجد منافس' : 'لم يُرصد' }}</div>
                                                                        @foreach ($card['no_competitor_photos'] ?? [] as $vnProof)
                                                                            <a href="{{ $vnProof }}" target="_blank" rel="noopener">
                                                                                <img class="vn-comp-thumb" src="{{ $vnProof }}" alt="" loading="lazy">
                                                                            </a>
                                                                        @endforeach
                                                                    @endforelse
                                                                </div>

                                                                <div class="vn-fact">
                                                                    <div class="vn-fact-label"><i class="fas fa-cube vn-ic-custody"></i> العهدة</div>
                                                                    @if ($card['custody']['count'] !== null)
                                                                        <div class="vn-count"><b>{{ $card['custody']['count'] }}</b> {{ $card['custody']['label'] }}</div>
                                                                    @else
                                                                        <div class="vn-empty">—</div>
                                                                    @endif
                                                                    @foreach ($card['images'] as $vnImage)
                                                                        @if ($loop->first)
                                                                            <a href="{{ $vnImage }}" data-fancybox="vn-note-{{ $note->id }}">
                                                                                <img class="vn-photo" src="{{ $vnImage }}" alt="" loading="lazy">
                                                                            </a>
                                                                            @if (! $loop->last)<div class="vn-thumbs">@endif
                                                                        @else
                                                                            <a href="{{ $vnImage }}" data-fancybox="vn-note-{{ $note->id }}">
                                                                                <img class="vn-thumb" src="{{ $vnImage }}" alt="" loading="lazy">
                                                                            </a>
                                                                            @if ($loop->last)</div>@endif
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            </div>

                                                            <footer class="vn-foot">
                                                                <span class="vn-foot-meta">
                                                                    @if ($vnAttachCount)
                                                                        <i class="fas fa-paperclip"></i> مرفقات: {{ $vnAttachCount }}
                                                                    @endif
                                                                </span>
                                                                <div class="vn-actions">
                                                                    @foreach ($card['files'] as $vnFile)
                                                                        <a class="vn-act" href="{{ $vnFile['url'] }}" target="_blank" rel="noopener" title="{{ $vnFile['name'] }}"><i class="fas fa-file-download"></i></a>
                                                                    @endforeach
                                                                    <a class="vn-act" href="{{ route('clients.notes.print', ['noteId' => $note->id]) }}" target="_blank" rel="noopener" title="طباعة"><i class="fas fa-print"></i></a>
                                                                    @if ($vnCanEdit)
                                                                        <a class="vn-act" href="{{ route('clients.notes.edit', $note->id) }}" title="تعديل"><i class="fas fa-edit"></i></a>
                                                                        <form action="{{ route('clients.notes.destroy', $note->id) }}" method="POST">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button type="submit" class="vn-act vn-act-danger" title="حذف"
                                                                                onclick="return confirm('هل أنت متأكد من حذف هذه الملاحظة؟')"><i class="fas fa-trash"></i></button>
                                                                        </form>
                                                                    @endif
                                                                </div>
                                                            </footer>
                                                        </article>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="rb-empty">لم تُسجَّل ملاحظات لهذا العميل بعد.</p>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="rb-empty rb-empty--block">
                                    <p><i class="far fa-calendar-times fa-2x"></i></p>
                                    <p><strong>{{ $visitStatus ? 'لا عملاء بهذه الحالة في هذا اليوم' : 'لا عملاء مجدولين في هذا اليوم' }}</strong></p>
                                    @if ($visitStatus)
                                        <a href="{{ $pageLink(['visit_status' => '']) }}" class="rb-btn rb-btn--light rb-btn--sm">عرض كل الحالات</a>
                                    @endif
                                </div>
                            @endforelse
                        </div>

                        <div class="rb-pane rb-pane--map">
                            <div id="rb-map" class="rb-map"></div>
                            <div id="rb-map-notice" class="rb-map-notice" hidden></div>
                            <div class="rb-pane__foot">
                                <div class="rb-legend">
                                    <span><span class="rb-dot" style="background:#e11d48"></span> موقع المندوب</span>
                                    <span><span class="rb-dot" style="background:#1d4ed8"></span> مساره اليوم</span>
                                </div>
                                <span id="rb-map-updated" class="rb-help"></span>
                                @if ($trackingEnabled)
                                    <button type="button" id="rb-map-refresh" class="rb-btn rb-btn--light rb-btn--sm"><i class="fas fa-sync-alt"></i> تحديث الموقع</button>
                                @endif
                                <a id="rb-open-gmaps" class="rb-btn rb-btn--primary rb-btn--sm is-disabled" target="_blank" rel="noopener">فتح في خرائط جوجل</a>
                            </div>
                        </div>
                    </div>
                </section>
            @endif
        </main>
    </div>
</div>
