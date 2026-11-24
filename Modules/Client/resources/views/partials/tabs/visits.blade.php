{{-- تبويب «الأنشطة والزيارات» — بطاقاتٌ بتصميم «ملاحظات الزيارات»
     (visit_note_cards.css، ثلاثٌ في الصف). حالة كل زيارة من `$visitStates`
     (قاعدة لوحة العملاء). تسعُ بطاقاتٍ ثم «عرض الكل» (`data-vn-more`). --}}
@php
    $vsRows = $visits ?? collect();
    $visitStatusMap = [
        'active' => ['label' => 'مكتملة', 'badge' => 'bg-success'],
        'unactive' => ['label' => 'غير مكتملة', 'badge' => 'bg-warning'],
    ];
    // لون شارة الحالة (Bootstrap) ← نغمة البطاقة
    $vsTone = fn (string $badge) => match (true) {
        str_contains($badge, 'success') => 'done',
        str_contains($badge, 'danger') => 'cancel',
        str_contains($badge, 'warning') || str_contains($badge, 'info') || str_contains($badge, 'primary') => 'live',
        default => 'muted',
    };
    /* زيارتان لعميلٍ واحدٍ في يومٍ واحد حالةٌ عاديّة («سأعود لهذا العميل
       اليوم») — نرقّم زيارات اليوم ونُظهر وقت العودة المجدول. */
    $visitsPerDay = $vsRows->groupBy(fn ($v) => $v->visit_date
        ? \Carbon\Carbon::parse($v->visit_date)->format('Y-m-d')
        : '—')->map->count();
    $visitDaySeen = [];
    $visitStateCounts = $vsRows->map(fn ($v) => ($visitStates ?? [])[$v->id]['key'] ?? null)->filter()->countBy();
@endphp
<div class="tab-pane" id="visits" role="tabpanel">
    <div class="vn-wrap">
        <div class="vn-section-head">
            <div class="vn-section-title">
                <span class="vn-section-icon"><i class="fas fa-walking"></i></span>
                <div>
                    الأنشطة والزيارات
                    <span class="vn-section-count">{{ $vsRows->count() }} زيارة</span>
                </div>
            </div>
        </div>

        @if ($visitStateCounts->isNotEmpty())
            <div class="vn-comp-strip">
                <span class="vn-comp-strip-label"><i class="fas fa-chart-pie me-1"></i>حالات الزيارات:</span>
                @foreach (\App\Services\ClientDashboardService::CLIENT_VISIT_BADGES as $stateKey => $state)
                    @if ($visitStateCounts->get($stateKey))
                        <span class="vn-chip" title="{{ \App\Services\ClientDashboardService::PLAN_COVERAGE[$stateKey]['hint'] ?? '' }}">
                            {{ $state['label'] }} · <b>{{ $visitStateCounts->get($stateKey) }}</b>
                        </span>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($vsRows->isNotEmpty())
            <div class="vn-grid" id="vn-visits-grid">
                @foreach ($vsRows as $visit)
                    @php
                        $vs = ($visitStates ?? [])[$visit->id]
                            ?? ($visitStatusMap[$visit->status ?? ''] ?? ['label' => ($visit->status ?? '—'), 'badge' => 'bg-secondary']);
                        $vsDate = $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date) : null;
                        $visitDayKey = $vsDate ? $vsDate->format('Y-m-d') : '—';
                        // القائمة تنازليّة، فالترقيم يُحسب من الآخر ليكون الأقدم «الأولى»
                        $visitDaySeen[$visitDayKey] = ($visitDaySeen[$visitDayKey] ?? 0) + 1;
                        $visitDayTotal = $visitsPerDay[$visitDayKey] ?? 1;
                        $visitDayIndex = $visitDayTotal - $visitDaySeen[$visitDayKey] + 1;
                        $vsJustification = trim((string) $visit->justification);
                    @endphp
                    <article class="vn-card" @if ($loop->iteration > 9) hidden data-vn-extra @endif>
                        <header class="vn-head">
                            <span class="vn-num vn-num-{{ ['success', 'primary', 'warning'][$loop->index % 3] }}">{{ $loop->iteration }}</span>
                            <div class="vn-who">
                                <div class="vn-title">
                                    <span class="vn-title-icon"><i class="fas fa-user"></i></span>
                                    <span title="{{ $visit->employee->name ?? 'غير محدد' }}">{{ $visit->employee->name ?? 'غير محدد' }}</span>
                                </div>
                                <div class="vn-sub">
                                    <span><i class="fas fa-hashtag"></i><bdi>{{ $visit->id }}</bdi></span>
                                    @if ($visitDayTotal > 1)
                                        <span><i class="fas fa-layer-group"></i>الزيارة {{ $visitDayIndex }} من {{ $visitDayTotal }} في اليوم</span>
                                    @endif
                                    {{-- وقت العودة الذي حدّده المندوب عند إنهاء الزيارة السابقة --}}
                                    @if ($visit->planned_time)
                                        <span><i class="fas fa-undo"></i>عودة مجدولة {{ \Carbon\Carbon::parse($visit->planned_time)->format('h:i A') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="vn-when">
                                @if ($vsDate)
                                    <span><i class="fas fa-calendar-alt"></i>{{ $vsDate->format('Y-m-d') }}</span>
                                    <span><i class="fas fa-calendar-day"></i>{{ $vsDate->locale('ar')->translatedFormat('l') }}</span>
                                @endif
                                <span class="vn-status vn-status-{{ $vsTone($vs['badge'] ?? '') }}" @if (! empty($vs['hint'])) title="{{ $vs['hint'] }}" @endif>
                                    <i class="fas fa-walking"></i>{{ $vs['label'] }}
                                </span>
                            </div>
                        </header>

                        <div class="vn-note" title="{{ $vsJustification }}">
                            <p>
                                <span class="vn-note-label">التبرير:</span>
                                @if ($vsJustification !== '')
                                    {{ $vsJustification }}
                                    @if ($visit->isJustificationApproved())
                                        <b class="text-success">(معتمد)</b>
                                    @elseif ($visit->isJustificationRejected())
                                        <b class="text-danger">(مرفوض)</b>
                                    @else
                                        <b class="text-warning">(معلق)</b>
                                    @endif
                                @else
                                    لا يوجد تبرير
                                @endif
                            </p>
                            <span class="vn-note-icon"><i class="fas fa-comment-dots"></i></span>
                        </div>

                        <div class="vn-facts">
                            <div class="vn-fact">
                                <div class="vn-fact-label"><i class="fas fa-play vn-ic-doc"></i> البداية</div>
                                @if ($visit->started_at)
                                    <div class="vn-doc-kind"><bdi dir="ltr">{{ \Carbon\Carbon::parse($visit->started_at)->format('h:i A') }}</bdi></div>
                                @else
                                    <div class="vn-empty">—</div>
                                @endif
                            </div>
                            <div class="vn-fact">
                                <div class="vn-fact-label"><i class="fas fa-flag-checkered vn-ic-comp"></i> النهاية</div>
                                @if ($visit->completed_at)
                                    <div class="vn-doc-kind"><bdi dir="ltr">{{ \Carbon\Carbon::parse($visit->completed_at)->format('h:i A') }}</bdi></div>
                                @else
                                    <div class="vn-empty">—</div>
                                @endif
                            </div>
                            <div class="vn-fact">
                                {{-- المدّة الفعلية تكتبها مساحة عمل الزيارة عند الإنهاء --}}
                                <div class="vn-fact-label"><i class="fas fa-stopwatch vn-ic-custody"></i> المدّة</div>
                                @if ($visit->duration_label)
                                    <div class="vn-doc-kind">{{ $visit->duration_label }}</div>
                                @else
                                    <div class="vn-empty">—</div>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($vsRows->count() > 9)
                <div class="vn-more-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-vn-more="vn-visits-grid">
                        <i class="fa fa-chevron-down me-1"></i>عرض كل الزيارات ({{ $vsRows->count() }})
                    </button>
                </div>
            @endif
        @else
            <div class="empty-state">
                <i class="fas fa-walking"></i>
                <h5>لا توجد زيارات</h5>
                <p>لم يتم تسجيل أي زيارات لهذا العميل بعد</p>
            </div>
        @endif
    </div>
</div>
