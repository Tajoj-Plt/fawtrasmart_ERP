{{-- تبويب «سجل الدخول» — كلُّ دخولٍ لموظف إلى ملف العميل ببطاقة، بتصميم
     «ملاحظات الزيارات» (visit_note_cards.css، ثلاثٌ في الصف). البيانات من
     ClientController::show() ← ClientProfileView::entriesFor()، ويظهر لحامل
     clients_view_profile_access_log وحده (والرابط في tabs-container بنفس الشرط). --}}
@if (! empty($canViewProfileAccessLog))
    @php
        $pvRows = $profileViews ?? collect();
        $pvEntries = $profileViewEntries ?? collect();
        $pvTotal = (int) $pvRows->sum('views_count');
    @endphp
    <div class="tab-pane fade" id="profile-access-log" role="tabpanel">
        <div class="vn-wrap">
            <div class="vn-section-head">
                <div class="vn-section-title">
                    <span class="vn-section-icon"><i class="fas fa-door-open"></i></span>
                    <div>
                        سجل الدخول إلى ملف العميل
                        <span class="vn-section-count">
                            {{ $pvTotal }} دخول · {{ $pvRows->count() }} موظف
                            @if ($pvTotal > $pvEntries->count())
                                · يُعرض آخر {{ $pvEntries->count() }} دخول
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            @if ($pvEntries->isNotEmpty())
                <div class="vn-comp-strip">
                    <span class="vn-comp-strip-label"><i class="fas fa-users me-1"></i>الموظفون:</span>
                    <button type="button" class="vn-chip vn-chip-btn is-active" data-pv-user="">
                        الكل · <b>{{ $pvTotal }}</b>
                    </button>
                    @foreach ($pvRows as $row)
                        <button type="button" class="vn-chip vn-chip-btn" data-pv-user="{{ $row->user_id }}"
                            title="أول دخول: {{ $row->first_viewed_at?->format('Y-m-d h:i A') }} — آخر دخول: {{ $row->last_viewed_at?->format('Y-m-d h:i A') }}">
                            {{ $row->user->name ?? ('مستخدم محذوف #' . $row->user_id) }} · <b>{{ $row->views_count }}</b>
                            @if ($row->last_viewed_at)
                                · {{ $row->last_viewed_at->diffForHumans() }}
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="vn-grid" id="vn-pv-grid">
                    @foreach ($pvEntries as $entry)
                        @php
                            $pvName = $entry->user->name ?? ('مستخدم محذوف #' . $entry->user_id);
                            $pvOrdinal = (int) $entry->ordinal;
                            $pvIsFirst = $pvOrdinal === 1;
                            $pvIsLatest = $pvOrdinal === (int) $entry->user_total;
                            $pvGap = $entry->gapLabel();
                            $pvPrevious = $entry->previous_viewed_at;
                        @endphp
                        <article class="vn-card" data-pv-card data-user="{{ $entry->user_id }}" @if ($loop->iteration > 9) hidden @endif>
                            <header class="vn-head">
                                <span class="vn-num vn-num-{{ $pvIsFirst ? 'success' : ($pvIsLatest ? 'primary' : 'warning') }}" title="الدخول {{ \App\Models\ClientProfileView::ordinalLabel($pvOrdinal) }}">{{ $pvOrdinal }}</span>
                                <div class="vn-who">
                                    <div class="vn-title">
                                        <span class="vn-title-icon"><i class="fas fa-user"></i></span>
                                        <span title="{{ $pvName }}">{{ $pvName }}</span>
                                    </div>
                                    <div class="vn-sub">
                                        <span><i class="fas fa-code-branch"></i>{{ $entry->user->branch->name ?? 'فرع غير معروف' }}</span>
                                    </div>
                                </div>
                                <div class="vn-when">
                                    @if ($entry->viewed_at)
                                        <span><i class="fas fa-calendar-alt"></i>{{ $entry->viewed_at->format('Y-m-d') }}</span>
                                        <span><i class="fas fa-clock"></i>{{ $entry->viewed_at->translatedFormat('h:i A') }}</span>
                                        <span><i class="fas fa-calendar-day"></i>{{ $entry->viewed_at->translatedFormat('l') }}</span>
                                    @endif
                                    @if ($pvIsLatest)
                                        <span class="vn-status vn-status-done"><i class="fas fa-eye"></i>آخر دخول</span>
                                    @elseif ($pvIsFirst)
                                        <span class="vn-status vn-status-live"><i class="fas fa-flag"></i>أول دخول</span>
                                    @endif
                                </div>
                            </header>

                            <div class="vn-note">
                                <p>
                                    <span class="vn-note-label">الدخول {{ \App\Models\ClientProfileView::ordinalLabel($pvOrdinal) }}:</span>
                                    {{ $pvName }} فتح ملف العميل {{ $entry->viewed_at?->diffForHumans() }}
                                </p>
                                <span class="vn-note-icon"><i class="fas fa-sign-in-alt"></i></span>
                            </div>

                            <div class="vn-facts">
                                <div class="vn-fact">
                                    <div class="vn-fact-label"><i class="fas fa-list-ol vn-ic-doc"></i> الترتيب</div>
                                    <div class="vn-doc-kind">{{ $pvOrdinal }} من {{ $entry->user_total }}</div>
                                    <div class="vn-empty">الدخول {{ \App\Models\ClientProfileView::ordinalLabel($pvOrdinal) }}</div>
                                </div>
                                <div class="vn-fact">
                                    <div class="vn-fact-label"><i class="fas fa-hourglass-half vn-ic-comp"></i> منذ السابق</div>
                                    @if ($pvGap)
                                        <div class="vn-doc-kind">{{ $pvGap }}</div>
                                        <div class="vn-empty">{{ $pvPrevious->format('Y-m-d') }} · {{ $pvPrevious->translatedFormat('h:i A') }}</div>
                                    @elseif ($pvIsFirst)
                                        <div class="vn-empty">لا يوجد — أول دخول</div>
                                    @else
                                        <div class="vn-empty">خارج المعروض</div>
                                    @endif
                                </div>
                                <div class="vn-fact">
                                    <div class="vn-fact-label"><i class="fas fa-mobile-alt vn-ic-custody"></i> الجهاز</div>
                                    <div class="vn-doc-kind">{{ $entry->device_label }}</div>
                                    <div class="vn-empty"><bdi dir="ltr">{{ $entry->ip_address ?? '—' }}</bdi></div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="vn-more-wrap" id="vn-pv-more-wrap" @if ($pvEntries->count() <= 9) hidden @endif>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="vn-pv-more">
                        <i class="fa fa-chevron-down me-1"></i>عرض المزيد
                    </button>
                </div>
            @else
                <div class="empty-state">
                    <i class="fas fa-door-open"></i>
                    <h5>لا يوجد دخول مسجَّل</h5>
                    <p>يُسجَّل كل دخولٍ لموظف إلى ملف هذا العميل من الآن</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        // تصفية البطاقات بالموظف، وتسعُ بطاقاتٍ في كل «عرض المزيد»
        (function () {
            var grid = document.getElementById('vn-pv-grid');
            if (!grid) return;

            var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-pv-card]'));
            var chips = Array.prototype.slice.call(document.querySelectorAll('[data-pv-user]'));
            var moreWrap = document.getElementById('vn-pv-more-wrap');
            var step = 9;
            var limit = step;
            var user = '';

            function apply() {
                var matching = 0;
                cards.forEach(function (card) {
                    var match = !user || card.getAttribute('data-user') === user;
                    card.hidden = !match || matching >= limit;
                    if (match) matching++;
                });
                moreWrap.hidden = matching <= limit;
            }

            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    user = chip.getAttribute('data-pv-user');
                    limit = step;
                    chips.forEach(function (c) { c.classList.toggle('is-active', c === chip); });
                    apply();
                });
            });

            document.getElementById('vn-pv-more').addEventListener('click', function () {
                limit += step;
                apply();
            });
        })();
    </script>
@endif
