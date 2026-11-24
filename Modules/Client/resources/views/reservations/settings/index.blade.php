@extends('client::reservations.master')

{{-- إعدادات وحدة الحجوزات.

     أُعيد ترتيبها (2026-09-10، طلب المستخدم: «ترتّب أنت الإعدادات»):

       • أُزيل ما لم يعد له معنى: الدوام (من ورديات الموارد البشرية الآن)،
         والفرعُ الافتراضي (الحجزُ يتبع فرع موظّفه)، والعربونُ ورسمُ الإلغاء
         والخزينةُ والفوترةُ التلقائية وحساباتُ الحجوزات (لا مالَ في الحجز —
         المالُ على فاتورة المبيعات)، ومفتاحُ «تفعيل الوحدة» (لم يكن يقرؤه أحد).
       • بقي ما يحكم الحجز فعلاً، في ستّة تبويبات بترتيب ما يحتاجه المستخدم.

     المفاتيحُ من `client::reservations.components.switch` — وفيه الحقلُ المخفيّ
     الذي يجعل الإطفاء يصل إلى الخادم كما يصل التشغيل. --}}

@section('title', 'إعدادات الحجوزات')

@section('content')
    <div class="content-body rsv-scope">

        @include('client::reservations.components.page-head', [
            'title' => 'إعدادات الحجوزات',
            'icon' => 'sliders',
            'sub' => 'كلُّ مفتاح هنا يحرس شيئاً فعلياً في سلوك الحجز',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'الحجوزات' => route('Reservations.index'),
                'الإعدادات' => null,
            ],
        ])

        @include('client::reservations.partials.alerts')

        <div class="rsv-stats">
            <div class="rsv-stat">
                <div class="rsv-stat__icon"><i class="feather icon-clock"></i></div>
                <div class="rsv-stat__body">
                    <p class="rsv-stat__value">{{ $settings->default_duration }} د</p>
                    <p class="rsv-stat__label">مدّة الحجز الافتراضية</p>
                </div>
            </div>

            <div class="rsv-stat">
                <div class="rsv-stat__icon rsv-stat__icon--info"><i class="feather icon-grid"></i></div>
                <div class="rsv-stat__body">
                    <p class="rsv-stat__value">كل {{ $settings->slot_interval }} د</p>
                    <p class="rsv-stat__label">الأوقات المعروضة للاختيار</p>
                </div>
            </div>

            <div class="rsv-stat">
                <div class="rsv-stat__icon rsv-stat__icon--info"><i class="feather icon-hash"></i></div>
                <div class="rsv-stat__body">
                    <p class="rsv-stat__value" style="font-size:1.05rem">{{ $nextNumber }}</p>
                    <p class="rsv-stat__label">رقم الحجز التالي</p>
                </div>
            </div>

            <div class="rsv-stat">
                <div class="rsv-stat__icon rsv-stat__icon--info"><i class="feather icon-edit"></i></div>
                <div class="rsv-stat__body">
                    {{-- `updated_by` هو دليلُ الحفظ عبر الشاشة: الصفُّ يُنشأ عند أوّل
                         زيارة بـ`firstOrCreate`، فـ`updated_at` وحدَه يزعم تعديلاً لم يقع. --}}
                    <p class="rsv-stat__value" style="font-size:1.05rem">
                        {{ $settings->updated_by
                            ? \App\Support\LanguageSettings::formatDate($settings->updated_at, true)
                            : 'لم تُعدَّل بعد' }}
                    </p>
                    <p class="rsv-stat__label">
                        آخر تعديل
                        @if ($settings->updatedBy)
                            — {{ $settings->updatedBy->name }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        @include('client::reservations.components.guide', [
            'key' => 'reservations-settings',
            'open' => false,
            'title' => 'ما الذي تضبطه هذه الشاشة؟',
            'lead' => 'قواعدُ الحجز التي يطبّقها النظام على كلّ حجزٍ يُنشأ.',
            'blocks' => [
                'ما يُضبط هنا' => [
                    '«المواعيد»: مدّة الحجز، وكل كم دقيقة تُعرض الأوقات، ومتى يُسمح بالحجز.',
                    '«الإلغاء وإعادة الجدولة» و«العميل» و«الإشعارات»: قواعد سير الحجز.',
                ],
                'ما لا يُضبط هنا' => [
                    'دوام الموظفين: من «الورديات» في الموارد البشرية — والحجز يُقبل داخل وردية الموظّف فقط.',
                    'العطل الرسمية: من «العطلات» في الموارد البشرية.',
                    'المال: الحجز بلا مدفوعات — «تحويل إلى فاتورة» يفتح فاتورة المبيعات والتحصيل عليها.',
                    'الترقيم: من «الترقيم المتسلسل» — القسم «حجز».',
                ],
            ],
        ])

        <form id="rsvSettings" method="POST" action="{{ route('Reservations.settings.update') }}">
            @csrf
            @method('PUT')

            @php
                $tabs = [
                    'appointment' => ['المواعيد', 'clock'],
                    'general' => ['عام', 'sliders'],
                    'employees' => ['الموظفون', 'users'],
                    'cancellation' => ['الإلغاء وإعادة الجدولة', 'x-circle'],
                    'customer' => ['العميل', 'user'],
                    'notifications' => ['الإشعارات والتذكير', 'bell'],
                    // لا تبويبَ للفاتورة ولا للترقيم: كلاهما من إعدادات النظام (المبيعات، الترقيم المتسلسل)
                ];
            @endphp

            <div class="card">
                <div class="card-header p-0">
                    <ul class="nav rsv-doc-tabs w-100" role="tablist">
                        @foreach ($tabs as $id => [$label, $icon])
                            <li class="nav-item">
                                <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab"
                                   href="#tab-{{ $id }}" role="tab">
                                    <i class="feather icon-{{ $icon }}"></i> {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="tab-content">

                    {{-- ═══════════ المواعيد ═══════════ --}}
                    <div class="tab-pane active" id="tab-appointment" role="tabpanel">
                        <div class="card-body">
                            <h6 class="mb-50" style="font-weight: 700;"><i class="feather icon-clock"></i> حجوزات الخدمات</h6>
                            <p class="rsv-help">
                                كم يستغرق الحجز، وكل كم دقيقة تُعرض الأوقات للاختيار، ومتى يُسمح بالحجز.
                                الأوقات نفسها تُحسب من <strong>وردية الموظّف</strong> في الموارد البشرية.
                            </p>

                            <div class="row g-3">
                                @foreach ([
                                    ['default_duration', 'مدّة الحجز (دقيقة)', 'تُطبَّق على كل حجز — إلا خدمة حُدِّدت لها مدّة في «خدمات الحجز».', 1, 1440, true],
                                    ['slot_interval', 'الأوقات المعروضة كل (دقيقة)', 'مثال: 30 = تُعرض 09:00 و09:30 و10:00…', 5, 240, true],
                                    ['min_duration', 'أقلّ مدّة مسموحة (دقيقة)', null, 1, 1440, true],
                                    ['max_duration', 'أقصى مدّة مسموحة (دقيقة)', null, 1, 1440, true],
                                    ['min_advance_minutes', 'أقلّ مهلة قبل الموعد (دقيقة)', 'صفر = يُسمح بالحجز حتى آخر لحظة (لا في الماضي).', 0, null, false],
                                    ['max_advance_days', 'أقصى مدى للحجز المسبق (يوم)', 'صفر = بلا حدّ.', 0, 3650, false],
                                ] as [$field, $label, $hint, $min, $max, $required])
                                    <div class="col-md-4">
                                        <label class="form-label">
                                            {{ $label }}@if ($required)<span class="rsv-req">*</span>@endif
                                            @if ($hint)<i class="fas fa-exclamation-circle rsv-tip" tabindex="0" data-tip="{{ $hint }}" role="img" aria-label="{{ $hint }}"></i>@endif
                                        </label>
                                        <input type="number" name="{{ $field }}" class="form-control"
                                               min="{{ $min }}" @if ($max) max="{{ $max }}" @endif
                                               value="{{ $settings->{$field} }}" @required($required)>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- ═══════════ مواعيد العملاء ═══════════
                             انتقلت هنا من «إعدادات وحدات العملاء» مع شاشاتها. القيمُ تُحفظ في
                             مخزنها القديم فلم يتغيّر أيُّ قارئٍ لها —
                             ReservationSettingsController::clientAppointmentSettings(). --}}
                        <div class="card-body border-top" id="client-appointments-settings">
                            <h6 class="mb-50" style="font-weight: 700;"><i class="feather icon-calendar"></i> مواعيد العملاء</h6>
                            <p class="rsv-help">
                                مواعيد المتابعة مع العملاء («المواعيد» في القائمة، وزرّ «إضافة موعد» في ملف العميل) — غير حجوزات الخدمات أعلاه.
                                تُطبَّق عند إضافة موعد أو تعديله.
                            </p>

                            <div class="rsv-switch-row">
                                @include('client::reservations.components.switch', [
                                    'name' => 'appointments_enabled',
                                    'checked' => (bool) old('appointments_enabled', $clientAppointments['enabled']),
                                    'title' => 'تفعيل مواعيد العملاء',
                                    'hint' => 'مطفأً تختفي «المواعيد» من القائمة وتُمنع شاشاتُها والإضافةُ من ملف العميل.',
                                ])
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-3">
                                    <label class="form-label">مدّة الموعد الافتراضية (دقيقة)<span class="rsv-req">*</span><i class="fas fa-exclamation-circle rsv-tip" tabindex="0" data-tip="تُملأ في نموذج الموعد، وتُستعمل حين تُترك المدّة فارغة." role="img" aria-label="تُملأ في نموذج الموعد، وتُستعمل حين تُترك المدّة فارغة."></i></label>
                                    <input type="number" name="appointment_default_duration_minutes" class="form-control" min="5" max="480" required
                                           value="{{ old('appointment_default_duration_minutes', $clientAppointments['duration']) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">بداية ساعات العمل<span class="rsv-req">*</span></label>
                                    <input type="time" name="appointment_working_hours_start" class="form-control" required
                                           value="{{ old('appointment_working_hours_start', $clientAppointments['working_start']) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">نهاية ساعات العمل<span class="rsv-req">*</span></label>
                                    <input type="time" name="appointment_working_hours_end" class="form-control" required
                                           value="{{ old('appointment_working_hours_end', $clientAppointments['working_end']) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">التذكير قبل الموعد (ساعة)<i class="fas fa-exclamation-circle rsv-tip" tabindex="0" data-tip="فارغ = بلا تذكير." role="img" aria-label="فارغ = بلا تذكير."></i></label>
                                    <input type="number" name="appointment_reminder_hours_before" class="form-control" min="1" max="336"
                                           value="{{ old('appointment_reminder_hours_before', $clientAppointments['reminder_hours']) }}">
                                </div>
                            </div>

                            <div class="rsv-switch-row mt-1">
                                @foreach ([
                                    ['appointment_enforce_working_hours', 'enforce_working_hours', 'رفض المواعيد خارج ساعات العمل', 'مطفأً تبقى ساعاتُ العمل إرشاداً في النموذج بلا منع.'],
                                    ['appointment_allow_overlap', 'allow_overlap', 'السماح بموعدين متداخلين لنفس الموظف', 'مطفأً يُرفض موعدٌ يتقاطع — بمدّته — مع موعدٍ آخر للموظف نفسه (غير الملغى).'],
                                    ['appointment_allow_past_dates', 'allow_past_dates', 'السماح بموعد بتاريخ مضى', 'مطفأً لا يُختار تاريخٌ مضى، ويبقى تعديلُ موعدٍ قديم ممكناً ما لم يتغيّر تاريخه.'],
                                    ['appointment_auto_complete_past', 'auto_complete_past', 'إقفال المواعيد الفائتة تلقائياً', 'تنقل المواعيد التي مضى يومها وبقيت «قيد الانتظار» إلى «مكتمل».'],
                                ] as [$apptField, $apptKey, $apptTitle, $apptHint])
                                    @include('client::reservations.components.switch', [
                                        'name' => $apptField,
                                        'checked' => (bool) old($apptField, $clientAppointments[$apptKey]),
                                        'title' => $apptTitle,
                                        'hint' => $apptHint,
                                    ])
                                @endforeach
                            </div>

                            <div class="alert {{ $clientAppointments['cron_active'] ? 'alert-success' : 'alert-warning' }} mt-2 mb-0">
                                <i class="feather icon-clock"></i>
                                التذكير قبل الموعد والإقفال التلقائي يعملان عبر المهمة المجدولة «تذكير مواعيد العملاء» — حالتها الآن:
                                <strong>{{ $clientAppointments['cron_active'] ? 'مفعّلة' : 'معطّلة، فلا يُرسَل تذكير ولا يُقفَل موعد' }}</strong>.
                                @if ($clientAppointments['cron_url'])
                                    <a href="{{ $clientAppointments['cron_url'] }}">فتح المهام المجدولة ←</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ عام ═══════════ --}}
                    <div class="tab-pane" id="tab-general" role="tabpanel">
                        <div class="card-body">
                            <p class="rsv-help">بأيّ حالة يبدأ الحجز، وحدود الحجز في اليوم.</p>

                            <div class="rsv-switch-row">
                                @include('client::reservations.components.switch', [
                                    'name' => 'allow_repeated_booking',
                                    'checked' => $settings->allow_repeated_booking,
                                    'title' => 'السماح بأكثر من حجز للعميل في اليوم',
                                    'hint' => null,
                                ])

                                @include('client::reservations.components.switch', [
                                    'name' => 'allow_overbooking',
                                    'checked' => $settings->allow_overbooking,
                                    'title' => 'السماح بحجز الموظّف مرّتين في الوقت نفسه',
                                    'hint' => 'يعطّل منع التعارض. لا يُفعَّل إلا لمن يقبل أكثر من عميل في الوقت نفسه عمداً.',
                                ])
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="form-label">حالة الحجز الجديد<i class="fas fa-exclamation-circle rsv-tip" tabindex="0" data-tip="«مؤكَّد» يتخطّى خطوة التأكيد." role="img" aria-label="«مؤكَّد» يتخطّى خطوة التأكيد."></i></label>
                                    <select name="default_status" class="form-select">
                                        @foreach ($statuses as $status)
                                            @continue(! in_array($status->value, ['draft', 'pending', 'confirmed'], true))
                                            <option value="{{ $status->value }}" @selected($settings->default_status === $status->value)>
                                                {{ $status->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">أقصى عدد حجوزات في اليوم<i class="fas fa-exclamation-circle rsv-tip" tabindex="0" data-tip="صفر = بلا حدّ." role="img" aria-label="صفر = بلا حدّ."></i></label>
                                    <input type="number" min="0" name="max_daily_reservations" class="form-control"
                                           value="{{ $settings->max_daily_reservations }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">عرض التقويم الافتراضي</label>
                                    <select name="calendar_default_view" class="form-select">
                                        {{-- متغيّرا الحلقة باسمين لا يتكرّران: `$value` يتسرّب إلى
                                             المفاتيح المُضمَّنة بعده فتُرسل «listWeek» بدل 1 --}}
                                        @foreach (['dayGridMonth' => 'شهري', 'timeGridWeek' => 'أسبوعي', 'timeGridDay' => 'يومي', 'listWeek' => 'قائمة أسبوعية'] as $calendarView => $calendarLabel)
                                            <option value="{{ $calendarView }}" @selected($settings->calendar_default_view === $calendarView)>{{ $calendarLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ الموظفون: من يُحجز له ═══════════
                         يقرؤه `eligibleEmployees()` — قائمةُ نموذج الحجز وأعمدةُ التقويم
                         ومودالُ إعادة الجدولة والبوّابة — ويُفرض عند الحفظ. --}}
                    @php
                        $employeeFilter = old('employee_filter', $settings->employee_filter ?: 'all');
                        $pickedDepartments = array_map('intval', (array) old('employee_department_ids', $settings->employee_department_ids ?? []));
                        $pickedJobTitles = array_map('intval', (array) old('employee_job_title_ids', $settings->employee_job_title_ids ?? []));
                    @endphp
                    <div class="tab-pane" id="tab-employees" role="tabpanel">
                        <div class="card-body">
                            <p class="rsv-help">
                                من يظهر في قائمة الموظفين عند إضافة حجز وفي أعمدة التقويم. يبقى شرطُ أن يكون للموظّف
                                <strong>وردية</strong> في الموارد البشرية، ويُضاف إليه القسمُ أو المسمّى المختار هنا.
                            </p>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">اختيار الموظفين حسب</label>
                                    @foreach (['all' => 'كل من له وردية', 'department' => 'قسم معيّن', 'job_title' => 'مسمّى وظيفي معيّن'] as $filterValue => $filterLabel)
                                        <div class="form-check">
                                            <input class="form-check-input rsv-emp-filter" type="radio" name="employee_filter"
                                                   id="employee_filter_{{ $filterValue }}" value="{{ $filterValue }}" @checked($employeeFilter === $filterValue)>
                                            <label class="form-check-label" for="employee_filter_{{ $filterValue }}">{{ $filterLabel }}</label>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="col-md-8" data-emp-filter="department" @unless ($employeeFilter === 'department') hidden @endunless>
                                    <label class="form-label" for="employee_department_ids">الأقسام<span class="rsv-req">*</span></label>
                                    <select name="employee_department_ids[]" id="employee_department_ids" class="form-select" multiple>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" @selected(in_array((int) $department->id, $pickedDepartments, true))>{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('employee_department_ids')<span class="text-danger small d-block">{{ $message }}</span>@enderror
                                </div>

                                <div class="col-md-8" data-emp-filter="job_title" @unless ($employeeFilter === 'job_title') hidden @endunless>
                                    <label class="form-label" for="employee_job_title_ids">المسمّيات الوظيفية<span class="rsv-req">*</span></label>
                                    <select name="employee_job_title_ids[]" id="employee_job_title_ids" class="form-select" multiple>
                                        @foreach ($jobTitles as $jobTitle)
                                            <option value="{{ $jobTitle->id }}" @selected(in_array((int) $jobTitle->id, $pickedJobTitles, true))>{{ $jobTitle->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('employee_job_title_ids')<span class="text-danger small d-block">{{ $message }}</span>@enderror
                                </div>
                            </div>

                            <div class="alert alert-info mt-2 mb-0">
                                <i class="feather icon-users"></i>
                                يُحجز الآن لـ <strong>{{ $eligibleCount }}</strong> موظّف.
                                والحجوزاتُ القائمة لموظفين خارج الاختيار تبقى كما هي — القيدُ على الحجز الجديد وتغيير الموظّف.
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ الإلغاء وإعادة الجدولة ═══════════ --}}
                    <div class="tab-pane" id="tab-cancellation" role="tabpanel">
                        <div class="card-body">
                            <div class="rsv-section">
                                <div class="rsv-section__head">
                                    <h6 class="rsv-section__title"><i class="feather icon-x-circle"></i> الإلغاء</h6>
                                </div>

                                <div class="rsv-switch-row">
                                    @include('client::reservations.components.switch', [
                                        'name' => 'allow_cancellation',
                                        'checked' => $settings->allow_cancellation,
                                        'title' => 'السماح بإلغاء الحجوزات',
                                        'hint' => null,
                                    ])

                                    @include('client::reservations.components.switch', [
                                        'name' => 'allow_cancel_after_confirm',
                                        'checked' => $settings->allow_cancel_after_confirm,
                                        'title' => 'السماح بالإلغاء بعد التأكيد',
                                        'hint' => null,
                                    ])
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-md-4">
                                        <label class="form-label">آخر مهلة للإلغاء قبل الموعد (ساعة)<i class="fas fa-exclamation-circle rsv-tip" tabindex="0" data-tip="صفر = يُلغى في أي وقت." role="img" aria-label="صفر = يُلغى في أي وقت."></i></label>
                                        <input type="number" min="0" name="cancellation_deadline_hours" class="form-control"
                                               value="{{ $settings->cancellation_deadline_hours }}">
                                    </div>
                                </div>
                            </div>

                            <div class="rsv-section">
                                <div class="rsv-section__head">
                                    <h6 class="rsv-section__title"><i class="feather icon-calendar"></i> إعادة الجدولة</h6>
                                </div>

                                <div class="rsv-switch-row">
                                    @include('client::reservations.components.switch', [
                                        'name' => 'allow_rescheduling',
                                        'checked' => $settings->allow_rescheduling,
                                        'title' => 'السماح بتغيير موعد الحجز',
                                        'hint' => null,
                                    ])
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-md-4">
                                        <label class="form-label">آخر مهلة لتغيير الموعد (ساعة)</label>
                                        <input type="number" min="0" name="reschedule_deadline_hours" class="form-control"
                                               value="{{ $settings->reschedule_deadline_hours }}">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">أقصى عدد مرّات تغيير الموعد<i class="fas fa-exclamation-circle rsv-tip" tabindex="0" data-tip="صفر = بلا حدّ." role="img" aria-label="صفر = بلا حدّ."></i></label>
                                        <input type="number" min="0" max="50" name="max_reschedule_attempts" class="form-control"
                                               value="{{ $settings->max_reschedule_attempts }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ العميل ═══════════ --}}
                    <div class="tab-pane" id="tab-customer" role="tabpanel">
                        <div class="card-body">
                            <p class="rsv-help">ما يُلزَم به الحجز من بيانات العميل.</p>

                            <div class="rsv-switch-row">
                                @foreach ([
                                    ['require_client', 'العميل إلزامي في الحجز', 'الحجز بلا عميل لا يُحوَّل إلى فاتورة.'],
                                    ['require_mobile', 'إلزام وجود جوّال للعميل', 'يحتاجه الإرسال بالواتساب والتذكير.'],
                                    ['require_email', 'إلزام وجود بريد إلكتروني للعميل', null],
                                ] as [$field, $label, $hint])
                                    @include('client::reservations.components.switch', [
                                        'name' => $field,
                                        'checked' => $settings->{$field},
                                        'title' => $label,
                                        'hint' => $hint,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ الإشعارات ═══════════ --}}
                    <div class="tab-pane" id="tab-notifications" role="tabpanel">
                        <div class="card-body">
                            <p class="rsv-help">
                                هذه المفاتيح تقرّر <strong>هل يُنشأ الإشعار</strong>؛ أمّا القناة فتُضبط في
                                «الإعدادات ← إعدادات الإشعارات».
                            </p>

                            <div class="rsv-switch-row">
                                @foreach ([
                                    'notify_created' => 'عند إنشاء الحجز',
                                    'notify_confirmed' => 'عند تأكيد الحجز',
                                    'notify_cancelled' => 'عند إلغاء الحجز',
                                    'notify_rescheduled' => 'عند تغيير الموعد',
                                    'notify_completed' => 'عند اكتمال الخدمة',
                                    'notify_no_show' => 'عند تسجيل عدم الحضور',
                                    'notify_reminder' => 'تذكير قبل الموعد',
                                ] as $field => $label)
                                    @include('client::reservations.components.switch', [
                                        'name' => $field,
                                        'checked' => $settings->{$field},
                                        'title' => $label,
                                        'hint' => null,
                                    ])
                                @endforeach

                                {{-- طلب المستخدم (2026-09-20): التذكير المجدول يصل الموظّف المعيَّن أيضاً --}}
                                @include('client::reservations.components.switch', [
                                    'name' => 'notify_reminder_employee',
                                    'checked' => $settings->notify_reminder_employee ?? true,
                                    'title' => 'تذكير الموظّف المعيَّن بحجزه',
                                    'hint' => 'مع التذكير قبل الموعد: يصل إشعارٌ للموظّف صاحب الحجز في صندوقه، إضافةً إلى تذكير العميل.',
                                ])
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label">التذكير قبل الموعد (دقيقة)<i class="fas fa-exclamation-circle rsv-tip" tabindex="0" data-tip="أرقام مفصولة بفواصل — مثال: 1440,120,30 = قبل يوم وقبل ساعتين وقبل نصف ساعة. القناة (بريد/رسالة نصية) تُضبط لنوع «تذكير بموعد حجز» في «إعدادات الإشعارات»." role="img" aria-label="أرقام مفصولة بفواصل"></i></label>
                                    <input type="text" name="reminder_offsets" class="form-control"
                                           value="{{ $settings->reminder_offsets }}" placeholder="1440,120,30">
                                </div>
                            </div>

                            {{-- المهلُ تُحفظ هنا والإرسالُ من المهمّة المجدولة — فحالتُها جزءٌ من الإعداد --}}
                            <div class="alert {{ $reminderCron['active'] ? 'alert-success' : 'alert-warning' }} mt-2 mb-0">
                                <i class="feather icon-clock"></i>
                                التذكير قبل الموعد يعمل عبر المهمة المجدولة «تذكير الحجوزات القادمة» — حالتها الآن:
                                <strong>{{ $reminderCron['active'] ? 'مفعّلة' : 'معطّلة، فلا يُرسَل تذكير مهما كانت المهل' }}</strong>
                                @if ($reminderCron['last_run_at'])
                                    (آخر تشغيل: {{ \Illuminate\Support\Carbon::parse($reminderCron['last_run_at'])->format('Y-m-d H:i') }})
                                @endif.
                                @if ($reminderCron['url'])
                                    <a href="{{ $reminderCron['url'] }}">فتح المهام المجدولة ←</a>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            @if (hasPermission('reservation_settings.update'))
                <div class="rsv-form-foot">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> حفظ الإعدادات
                    </button>
                    <a href="{{ route('Reservations.settings') }}" class="btn btn-outline-secondary">تراجع</a>
                </div>
            @else
                <div class="alert alert-secondary">
                    <i class="feather icon-lock"></i> لا تملك صلاحية تعديل الإعدادات — العرض فقط.
                </div>
            @endif
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        // «اختيار الموظفين حسب» يكشف قائمتَه وحدَها
        (function () {
            const radios = document.querySelectorAll('.rsv-emp-filter');
            const sync = () => {
                const picked = (document.querySelector('.rsv-emp-filter:checked') || {}).value || 'all';
                document.querySelectorAll('[data-emp-filter]').forEach(panel => {
                    panel.hidden = panel.dataset.empFilter !== picked;
                });
            };
            radios.forEach(radio => radio.addEventListener('change', sync));
            sync();
        })();
    </script>
@endsection
