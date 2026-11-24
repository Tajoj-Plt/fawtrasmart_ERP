{{--
    شاشة تخطيط خط السير الأسبوعي — إنشاءً وتعديلاً.

    الآلية آليةُ الشاشة القديمة: مندوبٌ وسنةٌ وأسبوع، ثم عملاءُ الجهة
    المختارة يُوزَّعون على أيام السبت إلى الجمعة، ويُحفظ الأسبوع كلُّه.
    الترتيبُ من «ترتيب الزيارات في خط السير» في إعدادات وحدات العملاء.
    المُتحكِّم `ItineraryController::create()`/`edit()`، والحفظ
    `ItineraryWeekService::saveWeek()`. المعرّفات `rb-*` يقرؤها
    `route-builder-scripts` — لا تُغيَّر من طرفٍ واحد.
--}}
@php
    $isEdit = $mode === 'edit';
    $levels = [
        'region' => ['المنطقة', 'fa-map-marker-alt', 'كل المناطق'],
        'branch' => ['الفرع', 'fa-building', 'كل الفروع'],
        'group' => ['المجموعة', 'fa-users', 'كل المجموعات'],
        'outlet' => ['المنفذ', 'fa-store', 'جميع المنافذ'],
    ];
    $orderLabel = __('client_settings.module_settings.fields.itinerary_order_'.$orderStrategy);
@endphp

<div id="route-builder" class="rb" dir="rtl">
    <header class="rb-head">
        <div class="rb-head__title">
            <span class="rb-head__icon"><i class="fas fa-route"></i></span>
            <div>
                <h1>{{ $isEdit ? 'تعديل خط السير' : 'تخطيط خط السير' }}</h1>
                <p>
                    إدارة خط سير المندوبين بشكل فعال
                    {{-- فرعُ الهيدر يقصر المندوبين والجهات والعملاء على ما تحته --}}
                    @if ($headerBranch)
                        <span class="rb-badge" title="المندوبون والجهات والعملاء مقصورون على هذا الفرع وما يتبع له — غيّره من أعلى الصفحة"><i class="fas fa-filter"></i> {{ $headerBranch }}</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="rb-head__actions">
            <a href="{{ route('itinerary.list') }}" class="rb-btn rb-btn--light">إلغاء</a>
            <button type="button" id="rb-copy" class="rb-btn rb-btn--light"><i class="far fa-copy"></i> نسخ من خط سابق</button>
            <button type="button" id="rb-save" class="rb-btn rb-btn--success"><i class="fas fa-save"></i> حفظ خط السير</button>
        </div>
    </header>

    <div id="rb-locked" class="rb-alert rb-alert--danger" hidden>
        <span><i class="fas fa-lock"></i> <span id="rb-locked-text"></span></span>
    </div>

    <div class="rb-layout">
        <aside class="rb-card rb-picker">
            <div class="rb-picker__title"><i class="fas fa-th-large"></i> اختيار العملاء</div>

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

            <label class="rb-field">
                <span class="rb-field__label">المندوب</span>
                <span class="rb-select">
                    <i class="fas fa-user"></i>
                    <select id="rb-assigned" @disabled($restrictToAssigned)>
                        @unless ($restrictToAssigned)
                            <option value="">كل عملاء الجهة المختارة</option>
                        @endunless
                        <option value="route">المُسنَدون للمندوب</option>
                    </select>
                </span>
                @if ($restrictToAssigned)
                    <small class="rb-help">مقصورٌ على عملاء المندوب — من إعدادات خطوط السير.</small>
                @endif
            </label>

            <div class="rb-search">
                <input type="search" id="rb-search" placeholder="بحث عن عميل (الاسم / الكود)" autocomplete="off">
                <i class="fas fa-search"></i>
            </div>

            <label class="rb-check">
                <input type="checkbox" id="rb-check-all">
                <span>تحديد الكل</span>
            </label>

            <div id="rb-client-list" class="rb-client-list">
                <p class="rb-empty">اختر المنطقة أو الفرع لعرض عملائه.</p>
            </div>
            <p id="rb-list-note" class="rb-note" hidden></p>

            <button type="button" id="rb-add" class="rb-btn rb-btn--primary rb-btn--block">
                <i class="fas fa-arrow-left"></i> <span id="rb-add-label">إضافة المحدد إلى خط السير</span>
            </button>
        </aside>

        <main class="rb-main">
            <section class="rb-card rb-form">
                <label class="rb-field">
                    <span class="rb-field__label">المندوب</span>
                    <span class="rb-select">
                        <i class="fas fa-user"></i>
                        <select id="rb-employee" @disabled($employeeLocked)>
                            @unless ($employeeLocked)
                                <option value="">-- اختر المندوب --</option>
                            @endunless
                            @foreach ($employees as $option)
                                <option value="{{ $option->id }}" @selected($employee && (int) $employee->id === (int) $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>

                <label class="rb-field">
                    <span class="rb-field__label">السنة</span>
                    <span class="rb-select">
                        <i class="far fa-calendar"></i>
                        <select id="rb-year">
                            @for ($year = $currentYear - 2; $year <= $currentYear + 2; $year++)
                                <option value="{{ $year }}" @selected($year === $currentYear)>{{ $year }}</option>
                            @endfor
                        </select>
                    </span>
                </label>

                <label class="rb-field">
                    <span class="rb-field__label">الأسبوع</span>
                    <span class="rb-select">
                        <i class="far fa-calendar-alt"></i>
                        <select id="rb-week">
                            <option value="{{ $currentWeek }}" selected>الأسبوع {{ $currentWeek }}</option>
                        </select>
                    </span>
                </label>

                <div class="rb-field">
                    <span class="rb-field__label">فترة الأسبوع</span>
                    <span id="rb-week-range" class="rb-pill">—</span>
                </div>
            </section>

            <section class="rb-stats">
                <div class="rb-stat rb-stat--green">
                    <div>
                        <span class="rb-stat__label">العملاء المختارون</span>
                        <strong id="rb-stat-selected">0</strong>
                        <small class="rb-stat__hint">في الأسبوع</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-user-friends"></i></span>
                </div>
                <div class="rb-stat rb-stat--blue">
                    <div>
                        <span class="rb-stat__label">إجمالي عملاء المندوب</span>
                        <strong id="rb-stat-assigned">—</strong>
                        <small class="rb-stat__hint">المُسنَدون إليه</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-users"></i></span>
                </div>
                <div class="rb-stat rb-stat--red">
                    <div>
                        <span class="rb-stat__label">غير مضافين</span>
                        <strong id="rb-stat-missing">—</strong>
                        <small class="rb-stat__hint">مُسنَدون وليسوا في الأسبوع</small>
                    </div>
                    <span class="rb-stat__icon"><i class="fas fa-user-minus"></i></span>
                </div>
                <div class="rb-stat rb-stat--navy">
                    <div>
                        <span class="rb-stat__label">عملاء اليوم</span>
                        <strong id="rb-stat-day">0</strong>
                        <small id="rb-stat-day-name" class="rb-stat__hint"></small>
                    </div>
                    <span class="rb-stat__icon"><i class="far fa-calendar-check"></i></span>
                </div>
            </section>

            <section class="rb-card rb-daybar">
                <div id="rb-days" class="rb-days"></div>
                <div class="rb-day-actions">
                    <button type="button" id="rb-auto" class="rb-btn rb-btn--light rb-btn--sm"><i class="fas fa-magic"></i> توزيع تلقائي</button>
                    <button type="button" id="rb-clear-day" class="rb-btn rb-btn--light rb-btn--sm"><i class="fas fa-eraser"></i> مسح اليوم</button>
                    <button type="button" id="rb-clear-all" class="rb-btn rb-btn--danger-light rb-btn--sm"><i class="far fa-trash-alt"></i> مسح الكل</button>
                </div>
            </section>

            <section class="rb-card rb-work">
                <div class="rb-tabs" role="tablist">
                    <button type="button" class="rb-tab is-active" data-view="split"><i class="fas fa-list-ul"></i> قائمة العملاء</button>
                    <button type="button" class="rb-tab" data-view="map"><i class="fas fa-map-marker-alt"></i> الخريطة</button>
                </div>

                <div id="rb-work" class="rb-work__body" data-view="split">
                    <div class="rb-pane rb-pane--table">
                        <div class="rb-table-wrap">
                            <table class="rb-table">
                                <thead>
                                    <tr>
                                        <th>الترتيب</th>
                                        <th>اسم العميل</th>
                                        <th>الحالة</th>
                                        <th>المنطقة</th>
                                        <th>الفرع</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="rb-stops"></tbody>
                            </table>
                            <p id="rb-stops-empty" class="rb-empty">لم يُضف عملاء لهذا اليوم بعد.</p>
                        </div>
                        <div class="rb-pane__foot">
                            <span><i class="fas fa-users"></i> عدد العملاء في المسار: <strong id="rb-foot-count">0</strong></span>
                        </div>
                        <p class="rb-hint">
                            <i class="fas fa-info-circle"></i>
                            @if ($canReorder)
                                يمكنك سحب العملاء لتغيير ترتيب الزيارة
                            @elseif ($orderStrategy === 'manual')
                                الترتيب بترتيب الإضافة — إعادة الترتيب بالسحب غير مسموحة لك من إعدادات خطوط السير
                            @else
                                الترتيب تلقائي: «{{ $orderLabel }}» — من «ترتيب الزيارات في خط السير» في إعدادات وحدات العملاء
                            @endif
                        </p>
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

    <div class="rb-modal" id="rb-copy-modal" hidden>
        <div class="rb-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="rb-copy-title">
            <h3 id="rb-copy-title">نسخ من خط سابق</h3>
            <p class="rb-help">يُنسخ خط الأسبوع المختار — عملاء كل يوم — إلى هذا الأسبوع، عدا الأيام المقفلة. لا يُحفظ شيء حتى تضغط «حفظ خط السير».</p>
            <div id="rb-copy-list" class="rb-copy-list"></div>
            <div class="rb-modal__actions">
                <button type="button" class="rb-btn rb-btn--light" data-close>إغلاق</button>
            </div>
        </div>
    </div>
</div>
