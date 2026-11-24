{{--
    «خط سيري اليومي» — يومُ مندوبٍ واحد: الإحصاءات، وقائمة الزيارات، والخريطة،
    وبطاقة العميل التالي، و«إضافة عميل للمسار».

    كلُّ المحتوى يُملأ من `DailyItineraryController::day()` بالـJS — المعرّفات
    `dr-*` يقرؤها `daily-route-scripts`، فلا تُغيَّر من طرفٍ واحد.
--}}
@php
    $nodeTypes = ['branch' => 'فرع', 'region' => 'منطقة', 'group' => 'مجموعة', 'outlet' => 'منفذ'];
@endphp

<div id="daily-route" class="dr" dir="rtl">
    <section class="dr-top">
        <div class="dr-card dr-title">
            <div class="dr-title__row">
                <div class="dr-title__text">
                    <h1 id="dr-title">خط سيري اليومي</h1>
                    <p id="dr-date-label">—</p>
                </div>
                <span class="dr-title__icon"><i class="far fa-calendar-alt"></i></span>
            </div>
            <div class="dr-title__controls">
                @if ($employees->count() > 1)
                    <label class="dr-control" title="المندوب">
                        <i class="fas fa-user"></i>
                        <select id="dr-employee">
                            @foreach ($employees as $option)
                                <option value="{{ $option['id'] }}" @selected((int) $option['id'] === $employeeId)>
                                    {{ (int) $option['id'] === (int) auth()->id() ? 'خطّي أنا' : $option['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="dr-control" title="اليوم">
                    <input type="date" id="dr-date" value="{{ $date }}">
                </label>
            </div>
        </div>

        <div class="dr-card dr-stat">
            <div>
                <span class="dr-stat__label">تمت الزيارات</span>
                <strong id="dr-stat-done">0 من 0</strong>
            </div>
            <span class="dr-stat__icon dr-stat__icon--green"><i class="fas fa-check"></i></span>
        </div>

        <div class="dr-card dr-stat">
            <div>
                <span class="dr-stat__label">إجمالي المسافة</span>
                <strong id="dr-stat-distance">0 كم</strong>
            </div>
            <span class="dr-stat__icon dr-stat__icon--blue"><i class="fas fa-map-marker-alt"></i></span>
        </div>

        <div class="dr-card dr-stat">
            <div>
                <span class="dr-stat__label">الوقت المتوقع</span>
                <strong id="dr-stat-time">0 د</strong>
            </div>
            <span class="dr-stat__icon dr-stat__icon--orange"><i class="far fa-clock"></i></span>
        </div>

        <div class="dr-card dr-stat">
            <div>
                <span class="dr-stat__label">نسبة الإنجاز</span>
            </div>
            <span class="dr-ring" id="dr-ring" style="--p: 0"><span id="dr-stat-percent">0%</span></span>
        </div>
    </section>

    <div id="dr-alert" class="dr-alert" hidden></div>

    <section class="dr-main">
        <aside class="dr-card dr-list">
            <div class="dr-panel-head">
                <h2>قائمة الزيارات</h2>
                <span id="dr-list-count" class="dr-count">0</span>
            </div>

            <ol id="dr-stops" class="dr-stops"></ol>
            <div id="dr-stops-empty" class="dr-empty" hidden>
                <i class="fas fa-route"></i>
                <p>لا زيارات في خط سير هذا اليوم.</p>
            </div>

            <div class="dr-list__foot">
                <button type="button" id="dr-add-open" class="dr-btn dr-btn--primary dr-btn--block" hidden>
                    <i class="fas fa-plus"></i> إضافة عميل للمسار
                </button>
            </div>
        </aside>

        <main class="dr-card dr-center">
            <div class="dr-tabs" role="tablist">
                <button type="button" class="dr-tab is-active" data-view="map">الخريطة</button>
                <button type="button" class="dr-tab" data-view="table">القائمة</button>
            </div>

            <div id="dr-view-map" class="dr-view dr-view--map">
                <div id="dr-map" class="dr-map"></div>
                <div id="dr-map-notice" class="dr-map-notice" hidden></div>
                <button type="button" id="dr-fullscreen" class="dr-map-btn dr-map-btn--corner"><i class="fas fa-expand"></i> ملء الشاشة</button>
                <button type="button" id="dr-locate" class="dr-map-btn dr-map-btn--locate" title="موقعي"><i class="fas fa-crosshairs"></i></button>
                <span id="dr-unlocated" class="dr-map-chip" hidden></span>
            </div>

            <div id="dr-view-table" class="dr-view dr-view--table" hidden>
                <div class="dr-table-wrap">
                    <table class="dr-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>العميل</th>
                                <th>الوقت</th>
                                <th>المكان</th>
                                <th>الحالة</th>
                                <th>أُضيف بواسطة</th>
                            </tr>
                        </thead>
                        <tbody id="dr-table-body"></tbody>
                    </table>
                </div>
            </div>

            <div class="dr-legend">
                <span><i class="dr-dot dr-dot--done"></i> تمت الزيارة</span>
                <span><i class="dr-dot dr-dot--next"></i> الزيارة التالية</span>
                <span><i class="dr-dot dr-dot--planned"></i> قادمة</span>
                <span><i class="dr-dot dr-dot--late"></i> متأخرة</span>
                <span><i class="dr-dot dr-dot--self"></i> إضافة المندوب</span>
                <span><i class="dr-dot dr-dot--other"></i> إضافة من دور آخر</span>
            </div>
        </main>

        <aside class="dr-card dr-next">
            <div class="dr-panel-head">
                <h2 id="dr-next-title">العميل التالي</h2>
                <span id="dr-next-seq" class="dr-seq" hidden></span>
            </div>

            <div id="dr-next-body" class="dr-next__body" hidden>
                <div class="dr-next__card">
                    <div class="dr-next__info">
                        <h3 id="dr-next-name"></h3>
                        <span id="dr-next-code" class="dr-code"></span>
                        <span id="dr-next-status" class="dr-client-status"></span>
                        <ul class="dr-facts">
                            <li id="dr-next-place"><i class="fas fa-map-marker-alt"></i> <span></span></li>
                            <li id="dr-next-time"><i class="far fa-clock"></i> <bdi dir="ltr"></bdi></li>
                            <li id="dr-next-leg"><i class="fas fa-route"></i> <span></span></li>
                        </ul>
                    </div>
                    <div id="dr-next-photo" class="dr-next__photo"></div>
                </div>

                <div id="dr-next-by" class="dr-by" hidden></div>

                <div class="dr-quick">
                    <a id="dr-act-details" class="dr-quick__btn" target="_blank" rel="noopener"><i class="fas fa-store"></i><span>تفاصيل العميل</span></a>
                    <a id="dr-act-directions" class="dr-quick__btn" target="_blank" rel="noopener"><i class="fas fa-location-arrow"></i><span>الاتجاهات</span></a>
                    <a id="dr-act-call" class="dr-quick__btn"><i class="fas fa-phone-alt"></i><span>اتصال</span></a>
                </div>

                <a id="dr-act-start" class="dr-btn dr-btn--success dr-btn--block dr-btn--lg" hidden><i class="fas fa-play"></i> <span>بدء الزيارة</span></a>

                <section class="dr-section">
                    <h4><i class="far fa-sticky-note"></i> ملاحظات</h4>
                    <p id="dr-next-note" class="dr-note"></p>
                </section>

                <section class="dr-section">
                    <div class="dr-section__head">
                        <h4><i class="far fa-images"></i> الصور السابقة</h4>
                        <span class="dr-gallery__nav">
                            <button type="button" id="dr-gallery-prev" aria-label="السابق"><i class="fas fa-chevron-right"></i></button>
                            <button type="button" id="dr-gallery-next" aria-label="التالي"><i class="fas fa-chevron-left"></i></button>
                        </span>
                    </div>
                    <div id="dr-gallery" class="dr-gallery"></div>
                </section>
            </div>

            <div id="dr-next-empty" class="dr-empty">
                <i class="fas fa-flag-checkered"></i>
                <p>لا زيارة متبقية في هذا اليوم.</p>
            </div>
        </aside>
    </section>


    <div class="dr-modal" id="dr-add-modal" hidden>
        <div class="dr-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="dr-add-title">
            <div class="dr-modal__head">
                <h3 id="dr-add-title"><i class="fas fa-user-plus"></i> إضافة عميل للمسار</h3>
                <button type="button" class="dr-modal__close" data-close aria-label="إغلاق">&times;</button>
            </div>

            <p id="dr-add-target" class="dr-help"></p>

            <label class="dr-field">
                <span class="dr-field__label">العميل تابع لـ</span>
                <span class="dr-control dr-control--wide">
                    <i class="fas fa-sitemap"></i>
                    <select id="dr-add-node">
                        <option value="">كل ما يتبعك</option>
                        @foreach ($nodes as $node)
                            <option value="{{ $node['id'] }}">{{ $node['label'] }}{{ isset($nodeTypes[$node['type']]) ? ' ('.$nodeTypes[$node['type']].')' : '' }}</option>
                        @endforeach
                    </select>
                </span>
            </label>

            <div class="dr-search">
                <input type="search" id="dr-add-q" placeholder="بحث عن عميل (الاسم / الكود)" autocomplete="off">
                <i class="fas fa-search"></i>
            </div>

            <div id="dr-add-list" class="dr-add-list"></div>
            <p id="dr-add-note" class="dr-help" hidden></p>

            <div class="dr-modal__actions">
                <button type="button" class="dr-btn dr-btn--light" data-close>إلغاء</button>
                <button type="button" id="dr-add-submit" class="dr-btn dr-btn--primary" disabled>
                    <i class="fas fa-plus"></i> إضافة <span id="dr-add-count">0</span>
                </button>
            </div>
        </div>
    </div>
</div>
