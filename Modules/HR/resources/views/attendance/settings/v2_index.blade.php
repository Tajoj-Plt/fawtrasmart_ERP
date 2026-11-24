@extends('hr::master')

{{-- إعدادات الدوام والحضور — على تصميم «إعدادات الفواتير» (`sales::partials.settings-theme`، البادئة `sl-`):
     ترويسةٌ بزرّ الحفظ، بطاقاتُ إحصاء، شريطُ «ما المضبوط الآن»، إرشاداتٌ مطويّة، ثم بطاقةٌ واحدة بتبويبات.

     التبويبات الخمسة الأولى نموذجٌ واحد (`#attGeneral`) يُحفَظ كلُّه بزرٍّ واحد. و«الصور» و«الموظفون»
     لكلٍّ منهما نموذجُه (إضافة نوع صورة، إعداد موظف) فلا تدخل في النموذج العام — وحقولُ الصور
     العامة فيها تُربط به بالسمة `form="attGeneral"`.

     والتبديلُ بين التبويبات بسكربتٍ صغير هنا لا بـ`data-toggle`: الشاشة تحمل Bootstrap 4 و5 معاً،
     ولوحةٌ خارج حاوية أختها لا يُخفيها محرّكُ 4. --}}

@section('title', 'الدوام والحضور')

@section('content')
    @include('sales::partials.settings-theme')

    @php
        $symbol = \App\Support\CurrencySettings::symbol();
        $nightlyOn = $nightlyJob?->isActive() ?? false;
        $activeKiosks = $kiosks->where('is_active', true);
        $silentKiosks = $activeKiosks->filter(fn ($k) => ! $k->last_seen_at || $k->last_seen_at->lt(now()->subHours(3)));
        $dayLabels = ['sat' => 'السبت', 'sun' => 'الأحد', 'mon' => 'الاثنين', 'tue' => 'الثلاثاء', 'wed' => 'الأربعاء', 'thu' => 'الخميس', 'fri' => 'الجمعة'];
        $approval = old('deduction_approval', $setting->deduction_approval ?? 'auto');

        /* حالةُ السويتش بعد فشل التحقّق: الخانةُ المطفأة تصل `0` من الحقل
           المخفيّ في `sales::components.switch`، فـ`old()` وحدها تكفي — لكنها
           تعيدها مفعّلةً لو لم يُرسَل النموذجُ أصلاً. فالتمييزُ بـ`hasOldInput()`. */
        $resubmitted = session()->hasOldInput();
        $on = fn (string $field, bool $current) => $resubmitted ? (bool) old($field) : $current;

        $tabs = [
            'tab-shift' => ['icon' => 'clock', 'label' => 'الدوام', 'general' => true],
            'tab-checkin' => ['icon' => 'log-in', 'label' => 'التسجيل والتأخير', 'general' => true],
            'tab-judge' => ['icon' => 'pie-chart', 'label' => 'الحكم على اليوم', 'general' => true],
            'tab-deductions' => ['icon' => 'dollar-sign', 'label' => 'الخصميات', 'general' => true, 'badge' => $pendingDeductions ?: null],
            'tab-photos' => ['icon' => 'camera', 'label' => 'الصور', 'general' => false],
            'tab-employees' => ['icon' => 'users', 'label' => 'الموظفون', 'general' => false, 'badge' => $perEmployeeConfigs->total() ?: null],
            'tab-places' => ['icon' => 'map-pin', 'label' => 'الأماكن والأجهزة', 'general' => false],
        ];
    @endphp

<div class="content-body sl-scope hr-hints">

    @section('page-actions')
        @permission('attendance_settings.manage')
            <button type="submit" form="attGeneral" class="btn btn-primary" id="attSaveTop">
                <i class="feather icon-save"></i> حفظ الإعدادات
            </button>
        @endpermission
        @if (Route::has('barcode.scan') && hasPermission('attendance_registration.kiosk'))
            <a href="{{ route('barcode.scan') }}" target="_blank" class="btn btn-outline-secondary">
                <i class="feather icon-tablet"></i> شاشة جهاز الفرع
            </a>
        @endif
    @endsection

    @include('sales::components.page-head', [
        'title' => 'الدوام والحضور',
        'icon' => 'sliders',
        'sub' => 'الدوام، ومواعيد التسجيل، والحكم على اليوم، والخصميات، والصور، وإعداد كل موظف، وأجهزة الفروع.',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'الدوام والحضور' => null],
    ])

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- ══════════ الإحصاء ══════════ --}}
    <div class="sl-stats">
        <div class="sl-stat">
            <div class="sl-stat__icon"><i class="feather icon-users"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value sl-num">{{ $perEmployeeConfigs->total() }} / {{ $employees->count() }}</p>
                <p class="sl-stat__label">موظفون لهم إعداد خاص</p>
            </div>
        </div>
        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--{{ $pendingDeductions ? 'warning' : 'success' }}"><i class="feather icon-check-square"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value sl-num">{{ $pendingDeductions }}</p>
                <p class="sl-stat__label">
                    @if (Route::has('hr.deductions.index'))
                        <a href="{{ route('hr.deductions.index', ['status' => 'pending']) }}">خصميات بانتظار الاعتماد</a>
                    @else
                        خصميات بانتظار الاعتماد
                    @endif
                </p>
            </div>
        </div>
        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-map-pin"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value sl-num">{{ $geofences->count() }}</p>
                <p class="sl-stat__label">أماكن تسجيل مفعّلة</p>
            </div>
        </div>
        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--{{ $silentKiosks->isNotEmpty() ? 'warning' : 'success' }}"><i class="feather icon-tablet"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value sl-num">{{ $activeKiosks->count() }}</p>
                <p class="sl-stat__label">أجهزة فروع مفعّلة{{ $silentKiosks->isNotEmpty() ? ' — '.$silentKiosks->count().' غير مستخدم' : '' }}</p>
            </div>
        </div>
    </div>

    {{-- ══════════ ما المضبوط الآن ══════════ --}}
    <section class="sl-ctx" aria-label="ما المضبوط الآن">
        <div class="sl-ctx__head">
            <h6 class="sl-ctx__title"><i class="feather icon-sliders" aria-hidden="true"></i> ما المضبوط الآن</h6>
            <p class="sl-ctx__hint">ملخص ما يعمل به الحضور حالياً — اضغط أي بند لفتح تبويبه.</p>
        </div>
        <div class="sl-ctx__grid">
            @foreach ([
                ['tab' => 'tab-shift', 'icon' => 'clock', 'label' => 'الدوام اليومي', 'tone' => 'on',
                    'value' => rtrim(rtrim(number_format((float) ($setting->work_hours_per_day ?? 8), 2, '.', ''), '0'), '.').' ساعة · استراحة '.($setting->break_allowed_minutes ?? 60).' د'],
                ['tab' => 'tab-checkin', 'icon' => 'log-in', 'label' => 'سماح التأخير / الغياب بعد', 'tone' => 'on',
                    'value' => ($setting->late_grace_minutes ?? 15).' د / '.($setting->absent_after_minutes ?? 120).' د'],
                ['tab' => 'tab-shift', 'icon' => 'map-pin', 'label' => 'زيارة العميل قبل الحضور',
                    'tone' => ($setting->visit_requires_check_in ?? true) ? 'on' : 'off',
                    'value' => ($setting->visit_requires_check_in ?? true) ? 'ممنوعة — يسجّل حضوره أولاً' : 'مسموحة بلا حضور'],
                ['tab' => 'tab-checkin', 'icon' => 'lock', 'label' => 'إقفال تسجيل الحضور',
                    'tone' => ($setting->checkin_close_after_minutes ?? null) !== null ? 'on' : 'off',
                    'value' => ($setting->checkin_close_after_minutes ?? null) !== null
                        ? 'بعد '.$setting->checkin_close_after_minutes.' د من البداية'
                        : 'لا يُقفل — المتأخر يسجّل'],
                ['tab' => 'tab-judge', 'icon' => 'pie-chart', 'label' => 'غياب تحت / نصف يوم تحت', 'tone' => 'on',
                    'value' => ($setting->absent_below_minutes ?? 120).' د / '.($setting->half_day_below_percent ?? 50).'%'],
                ['tab' => 'tab-deductions', 'icon' => 'dollar-sign', 'label' => 'الخصم من الحضور', 'tone' => $setting->auto_deduction_enabled ? 'on' : 'off',
                    'value' => $setting->auto_deduction_enabled ? (($setting->deduction_approval ?? 'auto') === 'manual' ? 'يعمل — اعتماد يدوي' : 'يعمل — اعتماد تلقائي') : 'متوقف'],
                ['tab' => 'tab-deductions', 'icon' => 'moon', 'label' => 'المعالجة الليلية', 'tone' => $nightlyOn ? 'on' : 'off',
                    'value' => $nightlyJob ? ($nightlyOn ? 'تعمل' : 'متوقفة') : 'غير موجودة'],
                ['tab' => 'tab-photos', 'icon' => 'camera', 'label' => 'صور الحضور', 'tone' => $setting->photos_required ? 'on' : 'off',
                    'value' => $setting->photos_required ? 'مطلوبة' : 'غير مطلوبة'],
            ] as $item)
                <a href="#{{ $item['tab'] }}" class="sl-ctx__item sl-ctx__item--{{ $item['tone'] }}" data-att-tab="{{ $item['tab'] }}">
                    <span class="sl-ctx__icon" aria-hidden="true"><i class="feather icon-{{ $item['icon'] }}"></i></span>
                    <span class="sl-ctx__body">
                        <span class="sl-ctx__label">{{ $item['label'] }}</span>
                        <span class="sl-ctx__value" title="{{ $item['value'] }}">{{ $item['value'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ══════════ الإرشادات (مطويّة) ══════════ --}}
    <details class="sl-guide">
        <summary><i class="feather icon-info"></i> <span>كيف يعمل الحضور؟</span></summary>
        <div class="sl-guide__body">
            <h6>التسجيل</h6>
            <p>موظف البطاقة يمسح بطاقته على جهاز الفرع. والميداني يسجّل من «سجّل حضوري» على جواله بموقعه. ويُفتح التسجيل من «يُفتح التسجيل قبل الوردية»، ولا يُقفل بعد ذلك إلا إن ضُبط «إقفال تسجيل الحضور بعد» — وهو فارغ افتراضاً. فالمتأخر يسجّل حضوره ويُحكم على يومه بـ«الغياب بعد»: سجلٌّ بحقيقته بدل يومٍ فارغ وطلبِ تصحيح.</p>
            <h6>الحكم على اليوم بعد الانصراف</h6>
            <p>الدوام المطلوب صافياً = طول الوردية ناقص الاستراحة المسموحة (وردية 08:00–16:00 باستراحة ساعة = 7 ساعات). صافي عمل أقل من «يُحتسب غياباً إذا عمل أقل من» ← غياب، وأقل من نسبة «نصف يوم» ← نصف يوم، وغير ذلك حاضر وما نقص يُحسب انصرافاً مبكراً.</p>
            <p class="sl-guide__fact"><i class="feather icon-check"></i> بالإعداد الحالي: 7 ساعات من 7 ← حاضر · 6 ساعات ← حاضر وانصراف مبكر ساعة · 3 ساعات ← نصف يوم · 40 دقيقة ← غياب.</p>
            <h6>الخصميات</h6>
            <p>تُحسب فوراً مع كل تسجيل، وتراجع «المعالجة الليلية» يومَي أمس واليوم. بالاعتماد اليدوي تنزل في «الخصميات» بانتظار الاعتماد ولا تُخصم من الراتب حتى تُعتمد.</p>
            <h6>الحقل الفارغ في إعداد الموظف</h6>
            <p>يعني «استخدم الإعداد العام» في هذه الشاشة.</p>
        </div>
    </details>

    {{-- ══════════ التبويبات ══════════ --}}
    <div class="card">
        <div class="card-header p-0">
            <ul class="nav nav-tabs att-tabs w-100" role="tablist">
                @foreach ($tabs as $tabId => $tab)
                    <li class="nav-item">
                        <a class="nav-link {{ $loop->first ? 'active' : '' }}" href="#{{ $tabId }}" data-att-tab="{{ $tabId }}" role="tab">
                            <i class="feather icon-{{ $tab['icon'] }}"></i> {{ $tab['label'] }}
                            @if (! empty($tab['badge']))
                                <span class="badge badge-light-{{ $tabId === 'tab-deductions' ? 'warning' : 'secondary' }} sl-num">{{ $tab['badge'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <form id="attGeneral" method="POST" action="{{ route('attendance.v2.settings.general') }}">
            @csrf

            {{-- ── الدوام ── --}}
            <div class="att-pane active" id="tab-shift" role="tabpanel">
                <div class="card-body">
                    <p class="att-lead">الدوام الافتراضي لمن لا وردية مُسنَدة له ولا إعداد خاص.</p>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>بداية الدوام الافتراضية</label>
                            <input type="time" name="default_shift_start" class="form-control"
                                value="{{ old('default_shift_start', $setting->default_shift_start ? substr($setting->default_shift_start, 0, 5) : '') }}">
                            <span class="sl-help">تُطبَّق على من لا وردية مُسنَدة له، ونهاية الدوام = البداية + ساعات العمل. فارغة = 08:00. والوردية المُسنَدة تتجاوزها دائماً.</span>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="sl-req">عدد ساعات العمل اليومي</label>
                            <input type="number" step="0.25" name="work_hours_per_day" class="form-control sl-num" min="1" max="24" value="{{ old('work_hours_per_day', $setting->work_hours_per_day ?? 8) }}" required>
                            <span class="sl-help">يُستخدم لحساب وقت الانصراف، وهو ساعات الدوام المرن لمن لم تُحدَّد له ساعات.</span>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="sl-req">مدة الاستراحة المسموحة في اليوم (دقيقة)</label>
                            <input type="number" name="break_allowed_minutes" class="form-control sl-num" min="0" max="480" value="{{ old('break_allowed_minutes', $setting->break_allowed_minutes ?? 60) }}" required>
                            <span class="sl-help">لا تُحسب من الدوام، والزائد عنها يُحسب نقصاً ويُخصم كالانصراف المبكر.</span>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="sl-req">طريقة الانصراف</label>
                            <select name="checkout_method" class="form-control" required>
                                <option value="hours" @selected(($setting->checkout_method ?? 'hours') == 'hours')>عند انتهاء ساعات العمل</option>
                                <option value="route" @selected(($setting->checkout_method ?? '') == 'route')>عند انتهاء خط السير/المهام</option>
                                <option value="both" @selected(($setting->checkout_method ?? '') == 'both')>كلاهما</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>نافذة الانصراف بعد الوردية (دقيقة)</label>
                            <input type="number" name="checkout_window_minutes" class="form-control sl-num" min="0" max="1440"
                                value="{{ old('checkout_window_minutes', $setting->checkout_window_minutes ?? null) }}">
                            <span class="sl-help">انصرافٌ بعد هذه المدة يُقبل ويُسجَّل على نهاية الوردية فلا يُحسب إضافياً — حمايةً من نسيان الانصراف. فارغ = بلا قيد.</span>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label>العطلة الأسبوعية الافتراضية</label>
                        @php $offDays = old('default_weekly_off_days', $setting->default_weekly_off_days ?? ['fri']); @endphp
                        <div class="att-days">
                            @foreach ($dayLabels as $code => $label)
                                <label class="att-day">
                                    <input type="checkbox" name="default_weekly_off_days[]" value="{{ $code }}" @checked(in_array($code, (array) $offDays, true))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <span class="sl-help" data-hint-keep>أيام لا يُحتسب فيها حضور ولا غياب. إلغاء الاختيار كله = عمل سبعة أيام.</span>
                    </div>

                    {{-- ══════════ زيارات العملاء للموظف الميداني ══════════
                         ثلاثةُ قيودٍ كانت مثبَّتةً في `FieldWorkGuard` بلا مفتاح. --}}
                    <div class="sl-section mt-2">
                        <div class="sl-section__head">
                            <i class="feather icon-map-pin"></i>
                            <h6 class="sl-section__title">زيارات العملاء للموظف الميداني</h6>
                        </div>
                        <p class="att-lead">متى يُسمح للمندوب أن يبدأ زيارة عميل؟ تسري على الميداني وحده («من الجوال» أو «الاثنين») — وموظفُ الفرع لا يزور عملاء.</p>

                        <div class="sl-switch-row">
                            @include('sales::components.switch', [
                                'name' => 'visit_requires_check_in',
                                'checked' => $on('visit_requires_check_in', (bool) ($setting->visit_requires_check_in ?? true)),
                                'title' => 'لا يبدأ زيارة قبل تسجيل حضوره',
                                'hint' => 'مشغَّلاً: من لم يسجّل حضورَه يُردّ بـ«سجّل حضورك أولاً». أطفئه إن كان مندوبُك يبدأ طريقه إلى أول عميل ثم يسجّل حضوره من عنده — ويبقى وقتُ الزيارة محفوظاً في الإقفال اليومي على أي حال.',
                            ])
                        </div>
                        <div class="sl-switch-row">
                            @include('sales::components.switch', [
                                'name' => 'visit_blocked_after_check_out',
                                'checked' => $on('visit_blocked_after_check_out', (bool) ($setting->visit_blocked_after_check_out ?? true)),
                                'title' => 'لا يبدأ زيارة بعد تسجيل انصرافه',
                                'hint' => 'مشغَّلاً: من سجّل انصرافَه انتهى يومُه فلا زيارةَ بعده. وإطفاؤه يسمح بزيارةٍ عابرةٍ بعد الدوام — ويظهر وقتُها في الإقفال خارج الدوام المسجَّل.',
                            ])
                        </div>
                        <div class="sl-switch-row">
                            @include('sales::components.switch', [
                                'name' => 'visit_blocked_during_break',
                                'checked' => $on('visit_blocked_during_break', (bool) ($setting->visit_blocked_during_break ?? true)),
                                'title' => 'لا يبدأ زيارة وهو في استراحة',
                                'hint' => 'مشغَّلاً: يُنهي استراحتَه من «سجّل حضوري» ثم يبدأ الزيارة — فلا تُحتسب الاستراحةُ ووقتُ العميل معاً.',
                            ])
                        </div>
                        <span class="sl-help" data-hint-keep>
                            والقيدُ المعاكس لا يُطفأ: زيارةٌ مفتوحةٌ عند عميلٍ تمنع الاستراحة والانصراف — لا معنى لانصرافٍ وزيارةٌ ما زالت تعدّ وقتَها.
                        </span>
                    </div>
                </div>
            </div>

            {{-- ── التسجيل والتأخير ── --}}
            <div class="att-pane" id="tab-checkin" role="tabpanel">
                <div class="card-body">
                    <p class="att-lead">متى يُقبل تسجيل الحضور، ومتى يُحسب الموظف متأخراً أو غائباً.</p>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="sl-req">يُفتح تسجيل الحضور قبل الوردية (دقيقة)</label>
                            <input type="number" name="checkin_early_minutes" class="form-control sl-num" min="0" max="720" value="{{ old('checkin_early_minutes', $setting->checkin_early_minutes ?? 60) }}" required>
                            <span class="sl-help">مثال: 60 ووردية 08:00 ← يُقبل الحضور من 07:00 حتى نهاية مهلة الغياب. خارج ذلك يُرفض.</span>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="sl-req">مدة السماح للتأخير (دقيقة)</label>
                            <input type="number" name="late_grace_minutes" class="form-control sl-num" min="0" max="480" value="{{ old('late_grace_minutes', $setting->late_grace_minutes ?? 15) }}" required>
                            <span class="sl-help">قبلها = حاضر بلا خصم، وبعدها = متأخر ويُنشأ الخصم. صفر = خصم كل دقيقة تأخير.</span>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="sl-req">اعتبار غياب بعد (دقيقة)</label>
                            <input type="number" name="absent_after_minutes" class="form-control sl-num" min="0" max="1440" value="{{ old('absent_after_minutes', $setting->absent_after_minutes ?? 120) }}" required>
                            <span class="sl-help">من تأخر أكثر من هذا يُحسب يومُه غياباً — ويبقى قادراً على تسجيل حضوره فيُحفظ السجل بحقيقته. المنعُ من التسجيل إعدادٌ آخر أدناه.</span>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>إقفال تسجيل الحضور بعد (دقيقة)</label>
                            <input type="number" name="checkin_close_after_minutes" class="form-control sl-num" min="0" max="1440"
                                value="{{ old('checkin_close_after_minutes', $setting->checkin_close_after_minutes ?? null) }}" placeholder="لا يُقفل">
                            <span class="sl-help">بعد هذه المدة من بداية الوردية لا يُقبل تسجيل الحضور إطلاقاً، ولا يبقى للموظف إلا طلب تصحيح. <strong>فارغ = لا يُقفل</strong> (الافتراض): المتأخر يسجّل ويُحكم على يومه بالمهلة المجاورة. ونافذة صفّ الوردية — إن ضُبطت — تتجاوز هذا الحقل.</span>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>انصراف مبكر مسموح (دقيقة)</label>
                            <input type="number" name="early_leave_threshold_minutes" class="form-control sl-num" min="0" max="480"
                                value="{{ old('early_leave_threshold_minutes', $setting->early_leave_threshold_minutes ?? 0) }}">
                            <span class="sl-help">الانصراف قبل الموعد بهذا القدر أو أقل لا يُحسب انصرافاً مبكراً.</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── الحكم على اليوم ── --}}
            <div class="att-pane" id="tab-judge" role="tabpanel">
                <div class="card-body">
                    <p class="att-lead">بعد انصراف الموظف: الدوام المطلوب صافياً = طول الوردية ناقص الاستراحة المسموحة. ثم يُقارن صافي عمله بهذين الحدّين.</p>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="sl-req">يُحتسب غياباً إذا عمل أقل من (دقيقة)</label>
                            <input type="number" name="absent_below_minutes" class="form-control sl-num" min="0" max="1440" value="{{ old('absent_below_minutes', $setting->absent_below_minutes ?? 120) }}" required>
                            <span class="sl-help">صافي العمل بعد خصم الاستراحة. صفر = لا يُحسب غائباً من حضر.</span>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="sl-req">يُحتسب نصف يوم إذا عمل أقل من (%) من دوامه</label>
                            <input type="number" name="half_day_below_percent" class="form-control sl-num" min="0" max="100" value="{{ old('half_day_below_percent', $setting->half_day_below_percent ?? 50) }}" required>
                            <span class="sl-help">مثال 50: دوام 7 ساعات ← أقل من 3.5 ساعة نصف يوم. صفر = لا نصف يوم.</span>
                        </div>
                    </div>
                    <div class="att-example" data-hint-keep>
                        <div class="att-example__row"><span>7 ساعات من 7</span><b class="text-success">حاضر كامل</b></div>
                        <div class="att-example__row"><span>6 ساعات</span><b>حاضر + انصراف مبكر ساعة</b></div>
                        <div class="att-example__row"><span>3 ساعات</span><b class="text-warning">نصف يوم</b></div>
                        <div class="att-example__row"><span>40 دقيقة</span><b class="text-danger">غياب</b></div>
                    </div>
                </div>
            </div>

            {{-- ── الخصميات ── --}}
            <div class="att-pane" id="tab-deductions" role="tabpanel">
                <div class="card-body">
                    <div class="sl-switch-row">
                        @include('sales::components.switch', [
                            'name' => 'auto_deduction_enabled', 'id' => 'sw_auto_deduction_enabled',
                            'checked' => (bool) old('auto_deduction_enabled', $setting->auto_deduction_enabled),
                            'title' => 'احتساب الخصم من الحضور',
                            'hint' => 'يُنشئ خصماً عند التأخير بعد السماح، والغياب، ونصف اليوم، ونقص الدوام (الانصراف المبكر أو الاستراحة الزائدة) — وينزل في «الخصميات».',
                        ])
                    </div>

                    @if ($nightlyJob)
                        <input type="hidden" name="nightly_job_present" value="1">
                        <div class="sl-switch-row">
                            <label class="sl-switch" for="sw_nightly_job_enabled">
                                <input type="checkbox" id="sw_nightly_job_enabled" name="nightly_job_enabled" value="1" @checked($nightlyOn)>
                                <span class="sl-switch__track" aria-hidden="true"><span class="sl-switch__thumb"></span></span>
                                <span class="sl-switch__label">
                                    <span class="sl-switch__title">تشغيل المعالجة الليلية للحضور</span>
                                    <span class="sl-switch__hint">كل ليلة تراجع يومَي أمس واليوم: تسجّل غياب من لم يحضر، وتُقفل أيام الأمس (الدوام المرن والوردية الليلية).</span>
                                </span>
                            </label>
                            <div class="att-job" data-hint-keep>
                                <span class="sl-state sl-state--{{ $nightlyOn ? 'on' : 'off' }}">{{ $nightlyOn ? 'تعمل' : 'متوقفة' }}</span>
                                الموعد: {{ $nightlyJob->cron_expression === '55 23 * * *' ? 'كل يوم 23:55' : $nightlyJob->cron_expression }}
                                · آخر تشغيل: {{ $nightlyJob->last_run_at ? $nightlyJob->last_run_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : 'لم يعمل بعد' }}
                                @if ($nightlyJob->last_status)
                                    ({{ $nightlyJob->last_status === 'success' ? 'نجح' : ($nightlyJob->last_status === 'failed' ? 'فشل' : $nightlyJob->last_status) }})
                                @endif
                                @if (Route::has('CronJobs.show'))
                                    · <a href="{{ route('CronJobs.show', $nightlyJob->id) }}">إدارة المهمة</a>
                                @endif
                                @if ($nightlyJob->last_status === 'failed' && $nightlyJob->last_error)
                                    <div class="text-danger mt-25">آخر خطأ: {{ \Illuminate\Support\Str::limit($nightlyJob->last_error, 160) }}</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="feather icon-alert-triangle"></i>
                            مهمة «معالجة الحضور اليومي» غير موجودة في المهام المجدولة — أضفها من
                            @if (Route::has('CronJobs.index'))<a href="{{ route('CronJobs.index') }}">المهام المجدولة</a>@else المهام المجدولة @endif
                            وإلا فلا يُسجَّل غياب من لم يحضر.
                        </div>
                    @endif

                    <div class="row mt-2">
                        <div class="col-md-6 form-group">
                            <label>اعتماد الخصم</label>
                            <select name="deduction_approval" class="form-control">
                                <option value="auto" @selected($approval === 'auto')>تلقائي — يُعتمد ويُقيَّد فوراً</option>
                                <option value="manual" @selected($approval === 'manual')>يدوي — ينتظر اعتماد المدير</option>
                            </select>
                            <span class="sl-help">اليدوي: يظهر في «الخصميات» بانتظار الاعتماد، ولا يُقيَّد ولا يُخصم من الراتب حتى يُعتمد. والمرفوض لا يُعاد إنشاؤه.</span>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>طريقة الاحتساب</label>
                            <select name="deduction_calculation_type" class="form-control">
                                <option value="fixed" @selected(($setting->deduction_calculation_type ?? 'fixed') == 'fixed')>مبلغ ثابت</option>
                                <option value="daily_salary" @selected(($setting->deduction_calculation_type ?? '') == 'daily_salary')>يومية من الراتب</option>
                                <option value="per_minute" @selected(($setting->deduction_calculation_type ?? '') == 'per_minute')>لكل دقيقة (تأخير/انصراف)</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>قيمة خصم التأخير ({{ $symbol }})</label>
                            <input type="number" step="0.01" name="late_deduction_amount" class="form-control sl-num" min="0" value="{{ old('late_deduction_amount', $setting->late_deduction_amount ?? 0) }}">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>قيمة خصم الغياب ({{ $symbol }})</label>
                            <input type="number" step="0.01" name="absence_deduction_amount" class="form-control sl-num" min="0" value="{{ old('absence_deduction_amount', $setting->absence_deduction_amount ?? 0) }}">
                            <span class="sl-help">نصف اليوم = نصف هذه القيمة.</span>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>قيمة خصم الانصراف المبكر ({{ $symbol }})</label>
                            <input type="number" step="0.01" name="early_leave_deduction_amount" class="form-control sl-num" min="0" value="{{ old('early_leave_deduction_amount', $setting->early_leave_deduction_amount ?? $setting->late_deduction_amount ?? 0) }}">
                            <span class="sl-help">يُطبَّق عند انصراف مبكر دون بلوغ نصف يوم.</span>
                        </div>
                    </div>

                    @if (Route::has('hr.deductions.index'))
                        <div class="att-callout" data-hint-keep>
                            <i class="feather icon-check-square"></i>
                            <span>اعتماد الخصميات أو رفضها من شاشة «الخصميات» في الرواتب.</span>
                            <a href="{{ route('hr.deductions.index', ['status' => 'pending']) }}" class="btn btn-sm btn-outline-primary">
                                الخصميات بانتظار الاعتماد
                                <span class="badge badge-{{ $pendingDeductions ? 'warning' : 'secondary' }}">{{ $pendingDeductions }}</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            @permission('attendance_settings.manage')
                <div class="sl-form-foot" id="attGeneralFoot">
                    <span class="sl-help" style="margin:0" data-hint-keep>الحفظ يشمل تبويبات: الدوام، والتسجيل والتأخير، والحكم على اليوم، والخصميات، وصور الحضور.</span>
                    <button type="submit" class="btn btn-primary"><i class="feather icon-save"></i> حفظ الإعدادات</button>
                </div>
            @endpermission
        </form>

        {{-- ── الصور ── --}}
        <div class="att-pane" id="tab-photos" role="tabpanel">
            <div class="card-body">
                <div class="sl-switch-row">
                    {{-- حقولٌ عامة خارج النموذج — مربوطةٌ به بالسمة form --}}
                    <label class="sl-switch" for="sw_photos_required">
                        <input type="hidden" name="photos_required" value="0" form="attGeneral">
                        <input type="checkbox" id="sw_photos_required" name="photos_required" value="1" form="attGeneral" @checked(old('photos_required', $setting->photos_required))>
                        <span class="sl-switch__track" aria-hidden="true"><span class="sl-switch__thumb"></span></span>
                        <span class="sl-switch__label">
                            <span class="sl-switch__title">طلب صور عند الحضور والانصراف</span>
                            <span class="sl-switch__hint">يلزم المُسجِّل بتقديم صور حسب الأنواع المفعّلة أدناه.</span>
                        </span>
                    </label>
                </div>
                <div class="row mt-1">
                    <div class="col-md-6 form-group">
                        <label>أقل عدد صور عند الحضور</label>
                        <input type="number" name="photos_count_checkin" form="attGeneral" class="form-control sl-num" min="0" max="10" value="{{ old('photos_count_checkin', $setting->photos_count_checkin ?? 0) }}">
                        <span class="sl-help">صفر = يكفي ما تشترطه الأنواع الإلزامية.</span>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>أقل عدد صور عند الانصراف</label>
                        <input type="number" name="photos_count_checkout" form="attGeneral" class="form-control sl-num" min="0" max="10" value="{{ old('photos_count_checkout', $setting->photos_count_checkout ?? 0) }}">
                    </div>
                </div>
                @permission('attendance_settings.manage')
                    <div class="sl-form-foot mt-0 mb-2">
                        <span class="sl-help" style="margin:0" data-hint-keep>زر الحفظ هنا يحفظ إعدادات الصور مع بقية الإعدادات العامة.</span>
                        <button type="submit" form="attGeneral" class="btn btn-primary"><i class="feather icon-save"></i> حفظ الإعدادات</button>
                    </div>
                @endpermission

                <div class="row">
                    @permission('attendance_settings.manage')
                    <div class="col-lg-4 col-12">
                        <div class="sl-section att-box">
                            <h6 class="att-box__title"><i class="feather icon-plus-circle"></i> إضافة نوع صورة</h6>
                            <form method="POST" action="{{ route('attendance.v2.settings.photo_types.store') }}">
                                @csrf
                                <div class="form-group">
                                    <label class="sl-req">الاسم</label>
                                    <input type="text" name="name" class="form-control" placeholder="مثال: صورة شخصية" value="{{ old('name') }}" required>
                                </div>
                                <div class="form-group">
                                    <label class="sl-req">رمز بالإنجليزي</label>
                                    <input type="text" name="code" class="form-control" placeholder="selfie" value="{{ old('code') }}" required style="direction:ltr">
                                    <span class="sl-help">حروف إنجليزية بلا مسافات، ولا يتكرر.</span>
                                </div>
                                <div class="form-group">
                                    <label class="sl-req">متى تُطلب</label>
                                    <select name="phase" class="form-control" required>
                                        <option value="both" @selected(old('phase') === 'both')>عند الحضور والانصراف</option>
                                        <option value="checkin" @selected(old('phase') === 'checkin')>عند الحضور فقط</option>
                                        <option value="checkout" @selected(old('phase') === 'checkout')>عند الانصراف فقط</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>وصف مختصر</label>
                                    <input type="text" name="description" class="form-control" maxlength="500" placeholder="ما الذي يجب أن تُظهره الصورة؟" value="{{ old('description') }}">
                                </div>
                                <div class="row">
                                    <div class="col-6 form-group">
                                        <label>ترتيب العرض</label>
                                        <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', 0) }}">
                                    </div>
                                    <div class="col-6 form-group">
                                        <label>أيقونة (اختياري)</label>
                                        <input type="text" name="icon" class="form-control" placeholder="fa-camera" value="{{ old('icon') }}" style="direction:ltr">
                                    </div>
                                </div>
                                <div class="sl-switch-row">
                                    @include('sales::components.switch', [
                                        'name' => 'is_required', 'id' => 'sw_photo_is_required',
                                        'checked' => (bool) old('is_required'),
                                        'title' => 'صورة إلزامية',
                                        'hint' => 'لا يكتمل التسجيل بدونها.',
                                    ])
                                </div>
                                <button type="submit" class="btn btn-primary w-100 mt-1"><i class="feather icon-plus"></i> إضافة</button>
                            </form>
                        </div>
                    </div>
                    @endpermission

                    <div class="col-lg-8 col-12">
                        <div class="sl-table-card att-box p-0">
                            <div class="sl-table-head">
                                <h6 class="sl-table-head__title"><i class="feather icon-camera"></i> أنواع الصور <span class="sl-count">{{ $photoTypes->count() }}</span></h6>
                            </div>
                            @if ($photoTypes->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>النوع</th>
                                                <th>متى تُطلب</th>
                                                <th class="text-center">إلزامية</th>
                                                <th class="text-center">الحالة</th>
                                                <th class="text-center" style="width:70px">إجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($photoTypes as $type)
                                                <tr>
                                                    <td>
                                                        <div class="sl-media">
                                                            <span class="sl-media__thumb"><i class="fa {{ $type->icon ?: 'fa-image' }}"></i></span>
                                                            <span class="sl-media__body">
                                                                <span class="sl-media__title">{{ $type->name }}</span>
                                                                <span class="sl-media__sub">{{ $type->description ?: $type->code }}</span>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>{{ $type->phase === 'both' ? 'الحضور والانصراف' : ($type->phase === 'checkin' ? 'الحضور' : 'الانصراف') }}</td>
                                                    <td class="text-center">{{ $type->is_required ? 'نعم' : 'لا' }}</td>
                                                    <td class="text-center"><span class="sl-state sl-state--{{ $type->is_active ? 'on' : 'off' }}">{{ $type->is_active ? 'مفعّل' : 'موقوف' }}</span></td>
                                                    <td class="text-center">
                                                        @permission('attendance_settings.manage')
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                                                    <i class="fa fa-ellipsis-v"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <form method="POST" action="{{ route('attendance.v2.settings.photo_types.toggle', $type->id) }}" class="m-0">
                                                                        @csrf
                                                                        <button type="submit" class="dropdown-item">
                                                                            <i class="fa fa-{{ $type->is_active ? 'pause-circle' : 'play-circle' }} me-2 text-warning"></i>{{ $type->is_active ? 'إيقاف' : 'تفعيل' }}
                                                                        </button>
                                                                    </form>
                                                                    <div class="dropdown-divider"></div>
                                                                    <form method="POST" action="{{ route('attendance.v2.settings.photo_types.delete', $type->id) }}" class="m-0" onsubmit="return confirm('حذف نوع الصورة؟');">
                                                                        @csrf @method('DELETE')
                                                                        <button type="submit" class="dropdown-item text-danger"><i class="fa fa-trash me-2"></i>حذف</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @endpermission
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sl-empty">
                                    <div class="sl-empty__icon"><i class="feather icon-camera"></i></div>
                                    <h5 class="sl-empty__title">لا توجد أنواع صور</h5>
                                    <p class="sl-empty__hint">أضف نوعاً إن أردت أن يرفق الموظف صورة عند تسجيل الحضور أو الانصراف.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── الموظفون ── --}}
        <div class="att-pane" id="tab-employees" role="tabpanel">
            <div class="card-body">
                @permission('attendance_settings.manage')
                    <form method="POST" action="{{ route('attendance.v2.settings.employee_config.store') }}">
                        @csrf
                        <p class="att-lead">إعداد خاص لموظف أو أكثر يتجاوز الإعداد العام. الحقل الفارغ = «استخدم الإعداد العام».</p>

                        <div id="empConfigNotice" class="alert alert-info" style="display:none;">
                            <i class="feather icon-edit-2 me-2"></i>
                            لهذا الموظف إعدادٌ محفوظ — حُمِّل في النموذج، والحفظ سيستبدله.
                        </div>
                        <div id="empBulkNotice" class="alert alert-warning" style="display:none;"></div>

                        <h6 class="att-sub"><i class="feather icon-users"></i> من يشمله الإعداد</h6>
                        <div class="row">
                            <div class="col-md-5 form-group">
                                <label>أضف موظفي أقسام</label>
                                <select id="pickDepartment" class="form-control" multiple style="width:100%;" data-placeholder="اختر قسماً أو أكثر">
                                    @foreach ($departments as $dep)
                                        <option value="{{ $dep->id }}" @disabled(! $dep->emp_count)>{{ $dep->name }} ({{ $dep->emp_count }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5 form-group">
                                <label>أضف موظفي فروع</label>
                                <select id="pickBranch" class="form-control" multiple style="width:100%;" data-placeholder="اختر فرعاً أو أكثر">
                                    @foreach ($branches as $br)
                                        <option value="{{ $br->id }}" @disabled(! $br->emp_count)>{{ $br->name }} ({{ $br->emp_count }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 form-group d-flex align-items-end" style="gap:6px">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="pickAll"><i class="feather icon-check-square"></i> الكل</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="pickClear"><i class="feather icon-x"></i> مسح</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="sl-req">الموظفون <small class="text-muted" id="pickCount" data-hint-keep></small></label>
                            <select name="employee_ids[]" id="employeeConfigSelect" class="form-control select2" multiple required style="width:100%;">
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp->id }}" data-department="{{ $emp->department_id }}" data-branches=",{{ implode(',', $emp->branch_ids) }},">{{ $emp->employee_code ?? '#'.$emp->id }} — {{ $emp->full_name }}</option>
                                @endforeach
                            </select>
                            <span class="sl-help">موظف واحد يُحمَّل إعداده للتعديل، وعدة موظفين (أو أقسام وفروع كاملة) يُطبَّق عليهم الإعداد نفسه.</span>
                        </div>

                        <h6 class="att-sub"><i class="feather icon-smartphone"></i> طريقة التسجيل ونوع الدوام</h6>
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>طريقة التسجيل</label>
                                <select name="attendance_channel" id="cfgChannel" class="form-control">
                                    <option value="branch">بالبطاقة على جهاز الفرع</option>
                                    <option value="field">ميداني — من الجوال</option>
                                    <option value="both">الاثنين</option>
                                </select>
                                <span class="sl-help">الميداني يسجّل من «سجّل حضوري»، ولا يبدأ زيارة عميل إلا داخل دوامه.</span>
                            </div>
                            <div class="col-md-3 form-group" id="cfgPlaceWrap">
                                <label>من أين يُقبل تسجيله من الجوال</label>
                                <select name="attendance_geofence_id" class="form-control">
                                    <option value="">من أي مكان — يُحفظ موقعه</option>
                                    @foreach ($geofences as $gf)
                                        <option value="{{ $gf->id }}">داخل: {{ $gf->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 form-group" id="cfgKioskWrap">
                                <label>الأجهزة المسموحة</label>
                                <select name="kiosk_ids[]" id="cfgKiosks" class="form-control" multiple style="width:100%;" data-placeholder="أي جهاز">
                                    @foreach ($kiosks->where('is_active', true) as $k)
                                        <option value="{{ $k->id }}">{{ $k->name }}{{ $k->branch ? ' — '.$k->branch->name : '' }}</option>
                                    @endforeach
                                </select>
                                <span class="sl-help">موظف البطاقة لا يُقبل مسحه إلا على هذه الأجهزة. فارغ = أي جهاز.</span>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>نوع الدوام</label>
                                <select name="work_mode" id="cfgWorkMode" class="form-control">
                                    <option value="shift">حسب الوردية (وقت بداية ونهاية)</option>
                                    <option value="flexible">دوام مرن — عدد ساعات يبدؤها متى شاء</option>
                                </select>
                                <span class="sl-help">المرن: لا تأخير، ويُقاس صافي عمله بـ«ساعات العمل اليومي» — وإن تُركت فارغة فمن الإعداد العام ({{ rtrim(rtrim((string) ($setting->work_hours_per_day ?? 8), '0'), '.') }} ساعة).</span>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>الاستراحة المسموحة (دقيقة)</label>
                                <input type="number" name="break_allowed_minutes" class="form-control" min="0" max="480" placeholder="العام ({{ $setting->break_allowed_minutes ?? 60 }})">
                            </div>
                        </div>

                        <h6 class="att-sub"><i class="feather icon-clock"></i> المواعيد</h6>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>وقت الحضور المتوقع</label>
                                <input type="time" name="expected_check_in" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>وقت الانصراف المتوقع</label>
                                <input type="time" name="expected_check_out" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>ساعات العمل اليومي</label>
                                <input type="number" step="0.25" name="work_hours_per_day" class="form-control" min="1" max="24" placeholder="العام">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>مدة السماح (دقيقة)</label>
                                <input type="number" name="late_grace_minutes" class="form-control" min="0" placeholder="العام">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>غياب بعد (دقيقة)</label>
                                <input type="number" name="absent_after_minutes" class="form-control" min="0" placeholder="العام">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>طريقة الانصراف</label>
                                <select name="checkout_method" class="form-control">
                                    <option value="">— الإعداد العام —</option>
                                    <option value="hours">ساعات</option>
                                    <option value="route">خط سير</option>
                                    <option value="both">كلاهما</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>أيام العطلة الأسبوعية لهؤلاء الموظفين</label>
                            <div class="att-days">
                                @foreach ($dayLabels as $code => $label)
                                    <label class="att-day">
                                        <input type="checkbox" name="weekly_off_days[]" value="{{ $code }}">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <span class="sl-help" data-hint-keep>إلغاء تحديد الكل = لا عطلة أسبوعية لهم.</span>
                        </div>

                        <div class="form-group">
                            <label>ملاحظات</label>
                            <input type="text" name="notes" id="cfgNotes" class="form-control" maxlength="500" placeholder="سبب الاستثناء مثلاً" value="{{ old('notes') }}">
                        </div>

                        <div class="sl-form-foot">
                            <span class="sl-help" style="margin:0" data-hint-keep>لإرجاع موظف إلى الإعداد العام احذف سطره من الجدول أدناه.</span>
                            <div class="d-flex" style="gap:.5rem">
                                <button type="button" class="btn btn-outline-secondary" onclick="resetEmployeeConfigForm()"><i class="feather icon-rotate-ccw"></i> تفريغ</button>
                                <button type="submit" class="btn btn-primary"><i class="feather icon-save"></i> حفظ إعداد الموظف</button>
                            </div>
                        </div>
                    </form>
                @endpermission

                <div class="sl-table-card att-box p-0 mt-2">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title"><i class="feather icon-list"></i> الإعدادات الخاصة الحالية <span class="sl-count">{{ $perEmployeeConfigs->total() }}</span></h6>
                    </div>
                    @if ($perEmployeeConfigs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>التسجيل</th>
                                        <th>الدوام</th>
                                        <th>الحضور ← الانصراف</th>
                                        <th>السماح</th>
                                        <th>العطلة الأسبوعية</th>
                                        <th class="text-center" style="width:70px">إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($perEmployeeConfigs as $cfg)
                                        @php $cfgChannel = optional($cfg->employee)->attendance_channel ?: 'branch'; @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-bold">{{ optional($cfg->employee)->full_name ?? '—' }}</div>
                                                <small class="text-muted">#{{ $cfg->employee_id }}</small>
                                            </td>
                                            <td>
                                                <span class="sl-state sl-state--neutral">{{ ['branch' => 'بالبطاقة', 'field' => 'ميداني', 'both' => 'الاثنين'][$cfgChannel] ?? $cfgChannel }}</span>
                                                @if ($cfgChannel !== 'field')
                                                    <small class="text-muted d-block">{{ isset($employeeKiosks[$cfg->employee_id]) ? implode('، ', $employeeKiosks[$cfg->employee_id]) : 'أي جهاز' }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $cfg->work_mode === 'flexible' ? 'مرن '.rtrim(rtrim((string) ($cfg->work_hours_per_day ?: ($setting->work_hours_per_day ?? 8)), '0'), '.').' ساعة' : 'حسب الوردية' }}
                                                @if ($cfg->break_allowed_minutes !== null)
                                                    <small class="text-muted d-block">استراحة {{ $cfg->break_allowed_minutes }} د</small>
                                                @endif
                                            </td>
                                            <td class="sl-num">
                                                @if ($cfg->expected_check_in || $cfg->expected_check_out)
                                                    {{ substr((string) $cfg->expected_check_in, 0, 5) ?: '—' }} ← {{ substr((string) $cfg->expected_check_out, 0, 5) ?: '—' }}
                                                @else
                                                    <span class="text-muted">العام</span>
                                                @endif
                                            </td>
                                            <td>{{ $cfg->late_grace_minutes !== null ? $cfg->late_grace_minutes.' د' : 'العام' }}</td>
                                            <td>
                                                @if (is_array($cfg->weekly_off_days) && count($cfg->weekly_off_days) > 0)
                                                    {{ collect($cfg->weekly_off_days)->map(fn ($d) => $dayLabels[$d] ?? $d)->implode('، ') }}
                                                @else
                                                    <span class="text-muted">العام</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @permission('attendance_settings.manage')
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                                            <i class="fa fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <button type="button" class="dropdown-item" onclick="loadEmployeeConfig('{{ $cfg->employee_id }}')">
                                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                            </button>
                                                            <div class="dropdown-divider"></div>
                                                            <form method="POST" action="{{ route('attendance.v2.settings.employee_config.delete', $cfg->id) }}" class="m-0" onsubmit="return confirm('حذف الإعداد الخاص؟ سيعود الموظف إلى الإعدادات العامة.');">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger"><i class="fa fa-trash me-2"></i>حذف</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                @endpermission
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{-- fragment# يعيد فتح تبويب الموظفين بعد إعادة التحميل --}}
                        <div class="p-2">{{ $perEmployeeConfigs->fragment('tab-employees')->links() }}</div>
                    @else
                        <div class="sl-empty">
                            <div class="sl-empty__icon"><i class="feather icon-users"></i></div>
                            <h5 class="sl-empty__title">لا توجد إعدادات خاصة</h5>
                            <p class="sl-empty__hint">كل الموظفين يعملون بالإعدادات العامة.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── الأماكن والأجهزة ── --}}
        <div class="att-pane" id="tab-places" role="tabpanel">
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6 col-12">
                        <div class="sl-section att-box">
                            <h6 class="att-box__title"><i class="feather icon-tablet"></i> ربط جهاز الفرع</h6>
                            <ol class="att-steps" data-hint-keep>
                                <li>أضف الجهاز من
                                    @if (Route::has('attendance.settings.kiosks.create') && hasPermission('attendance_places.create'))<a href="{{ route('attendance.settings.kiosks.create') }}">«إضافة جهاز»</a>@else «أجهزة التسجيل» @endif
                                    واختر فرعه ومكانه، فيظهر لك <b>رمز الربط</b> في صفحة الجهاز.</li>
                                <li>على جهاز الفرع نفسه (تابلت أو كمبيوتر) سجّل الدخول بحساب له صلاحية التسجيل، وافتح
                                    @if (Route::has('barcode.scan') && hasPermission('attendance_registration.kiosk'))<a href="{{ route('barcode.scan') }}" target="_blank">«شاشة جهاز الفرع»</a>@else شاشة التسجيل @endif:
                                    <code class="att-url">{{ Route::has('barcode.scan') ? route('barcode.scan') : '' }}</code></li>
                                <li>اكتب رمز الربط في أعلى الشاشة واضغط «اقتران» — مرة واحدة.</li>
                                <li>لتقييد موظفين بأجهزة معيّنة: تبويب «الموظفون» ← «الأجهزة المسموحة». من لا جهاز له يُقبل على أي جهاز.</li>
                                <li>يمسح الموظفون بطاقاتهم عليه للحضور والانصراف. وإن فُقد الجهاز اضغط «رمز جديد» في صفحته فيتوقف الرمز القديم.</li>
                            </ol>
                            <p class="sl-help mb-0" data-hint-keep>الربط يبقى ما دام المتصفح مفتوحاً على الجهاز؛ إن أُغلق المتصفح يُدخَل الرمز مرة أخرى.</p>
                        </div>
                    </div>
                    <div class="col-lg-6 col-12">
                        <div class="sl-table-card att-box p-0">
                            <div class="sl-table-head">
                                <h6 class="sl-table-head__title"><i class="feather icon-tablet"></i> أجهزة الفروع <span class="sl-count">{{ $kiosks->count() }}</span></h6>
                                @if (Route::has('attendance.settings.kiosks.index') && hasPermission('attendance_places.index'))
                                    <a href="{{ route('attendance.settings.kiosks.index') }}" class="btn btn-sm btn-outline-primary">إدارة الأجهزة</a>
                                @endif
                            </div>
                            @if ($kiosks->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead><tr><th>الجهاز</th><th>الفرع</th><th class="text-center">موظفون مربوطون</th><th>آخر استخدام</th><th class="text-center">الحالة</th></tr></thead>
                                        <tbody>
                                            @foreach ($kiosks as $k)
                                                <tr>
                                                    <td>@if (hasPermission('attendance_places.show'))<a href="{{ route('attendance.settings.kiosks.show', $k->id) }}">{{ $k->name }}</a>@else {{ $k->name }} @endif</td>
                                                    <td>{{ $k->branch->name ?? '—' }}</td>
                                                    <td class="text-center sl-num">{{ $k->employees_count ?: 'الكل' }}</td>
                                                    <td>{{ $k->last_seen_at ? $k->last_seen_at->diffForHumans() : 'لم يُربط بعد' }}</td>
                                                    <td class="text-center"><span class="sl-state sl-state--{{ $k->is_active ? 'on' : 'off' }}">{{ $k->is_active ? 'مفعّل' : 'موقوف' }}</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sl-empty">
                                    <div class="sl-empty__icon"><i class="feather icon-tablet"></i></div>
                                    <h5 class="sl-empty__title">لا توجد أجهزة</h5>
                                    <p class="sl-empty__hint">أضف جهازاً لكل فرع يسجّل موظفوه بالبطاقة.</p>
                                </div>
                            @endif
                        </div>

                        <div class="sl-section att-box mt-2">
                            <h6 class="att-box__title"><i class="feather icon-map-pin"></i> أماكن التسجيل ({{ $geofences->count() }})</h6>
                            <p class="mb-1">مكان الفرع على الخريطة ونطاقه وشبكة المكتب — تُطبَّق على التسجيل من الجوال وعلى أجهزة الفروع.</p>
                            @if (Route::has('attendance.settings.geofences.index') && hasPermission('attendance_places.index'))
                                <a href="{{ route('attendance.settings.geofences.index') }}" class="btn btn-sm btn-outline-primary">إدارة الأماكن</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.hint-tips')
@endsection

@push('styles')
<style>
    .att-tabs { border-bottom: 1px solid var(--app-border, #ebe9f1); padding: 0 .75rem; flex-wrap: wrap; }
    .att-tabs .nav-link { border: 0; border-bottom: 2px solid transparent; padding: .9rem 1rem; color: var(--app-muted, #6e6b7b); font-weight: 600; background: none; }
    .att-tabs .nav-link:hover { color: var(--app-primary, #7367f0); }
    .att-tabs .nav-link.active { color: var(--app-primary, #7367f0); border-bottom-color: var(--app-primary, #7367f0); background: none; }
    .att-tabs .nav-link .badge { margin-inline-start: .3rem; }
    .att-pane { display: none; }
    .att-pane.active { display: block; }
    .att-lead { color: var(--app-muted, #6e6b7b); font-size: .88rem; margin: 0 0 1rem; }
    .att-sub { font-size: .85rem; font-weight: 700; margin: 1.1rem 0 .7rem; color: var(--app-text, #4b4b4b); display: flex; align-items: center; gap: .4rem; }
    .att-sub:first-of-type { margin-top: .2rem; }
    .att-days { display: flex; flex-wrap: wrap; gap: .5rem; }
    .att-day { margin: 0; cursor: pointer; }
    .att-day input { display: none; }
    .att-day span { display: inline-block; padding: .35rem .9rem; border: 1px solid var(--app-border, #d8d6de); border-radius: 999px; font-size: .82rem; font-weight: 600; color: var(--app-muted, #6e6b7b); transition: .15s; }
    .att-day input:checked + span { background: var(--app-primary, #7367f0); border-color: var(--app-primary, #7367f0); color: #fff; }
    .att-example { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .5rem; }
    .att-example__row { border: 1px solid var(--app-border, #ebe9f1); border-radius: .5rem; padding: .6rem .8rem; display: flex; flex-direction: column; gap: .15rem; font-size: .82rem; }
    .att-example__row span { color: var(--app-muted, #6e6b7b); }
    .att-job { font-size: .8rem; color: var(--app-muted, #6e6b7b); margin-top: .4rem; padding-inline-start: 3.2rem; }
    .att-callout { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; border: 1px dashed var(--app-border, #d8d6de); border-radius: .5rem; padding: .7rem .9rem; font-size: .85rem; }
    .att-box { border: 1px solid var(--app-border, #ebe9f1); border-radius: .5rem; padding: 1rem; background: var(--app-surface, #fff); }
    .att-box__title { font-size: .9rem; font-weight: 700; margin: 0 0 .8rem; display: flex; align-items: center; gap: .4rem; }
    .att-box .sl-table-head { padding: .8rem 1rem; display: flex; justify-content: space-between; align-items: center; }
    .att-steps { padding-inline-start: 1.2rem; margin-bottom: .6rem; line-height: 2; font-size: .86rem; }
    .att-url { direction: ltr; display: inline-block; font-size: .78rem; word-break: break-all; }
    .sl-scope .select2-container--default .select2-selection--multiple { min-height: 38px; border-color: var(--app-border, #d8d6de); }
</style>
@endpush

@push('scripts')
<script>
// نائبٌ نصّيٌّ لا يُرمَّز في الرابط، بخلاف ":id" التي تخرج %3Aid
const EMPLOYEE_CONFIG_URL = "{{ route('attendance.v2.settings.employee_config.show', '__ID__') }}";

$(function () {
    $('.select2').select2({ dir: 'rtl', allowClear: true, placeholder: 'اختر...' });

    // اختيار موظفٍ يحمّل إعداده المحفوظ إن وُجد، فالنموذج «إضافة/تعديل» فعلاً
    // لا إضافةً تمسح ما قبلها.
    $('#employeeConfigSelect').on('change', onEmployeesChanged);

    /*
        أقسامٌ وفروعٌ متعدّدة: اختيارُ قسمٍ/فرعٍ يُضيف موظفيه إلى الاختيار،
        وإزالتُه تُزيلهم — إلا من بقي داخلاً في قسمٍ أو فرعٍ آخر مختار.
        الفرعُ يشمل الفرعَ الرئيسي والفروعَ الإضافية للموظف.
    */
    const groupSelector = {
        department: (id) => 'option[data-department="' + id + '"]',
        branch: (id) => 'option[data-branches*=",' + id + ',"]',
    };
    const stillCovered = function (opt) {
        const deps = ($('#pickDepartment').val() || []).map(String);
        const brs = ($('#pickBranch').val() || []).map(String);
        if (deps.includes(String(opt.dataset.department || ''))) return true;
        return brs.some((b) => (opt.dataset.branches || '').includes(',' + b + ','));
    };
    const applyGroup = function (attr, id, add) {
        const $sel = $('#employeeConfigSelect');
        const current = new Set(($sel.val() || []).map(String));
        $sel.find(groupSelector[attr](id)).each(function () {
            if (add) { current.add(this.value); } else if (! stillCovered(this)) { current.delete(this.value); }
        });
        $sel.val(Array.from(current)).trigger('change');
    };
    $('#pickDepartment, #pickBranch').each(function () {
        const attr = this.id === 'pickDepartment' ? 'department' : 'branch';
        $(this).select2({ dir: 'rtl', width: '100%', placeholder: this.dataset.placeholder, closeOnSelect: false })
            .on('select2:select', (e) => applyGroup(attr, e.params.data.id, true))
            .on('select2:unselect', (e) => applyGroup(attr, e.params.data.id, false));
    });
    $('#cfgKiosks').select2({ dir: 'rtl', width: '100%', placeholder: 'أي جهاز', closeOnSelect: false });

    $('#pickAll').on('click', function () {
        $('#employeeConfigSelect').val($('#employeeConfigSelect option').map(function () { return this.value; }).get()).trigger('change');
    });
    $('#pickClear').on('click', function () {
        $('#pickDepartment, #pickBranch').val([]).trigger('change.select2');
        $('#employeeConfigSelect').val([]).trigger('change');
    });

});

/*
    التبويبات — مبدِّلٌ صغير بدل data-toggle (الشاشة تحمل Bootstrap 4 و5 معاً،
    واللوحات ليست في حاويةٍ واحدة: العامةُ داخل نموذج الحفظ والبقيةُ خارجه).
    الرابطُ (#tab-employees …) يفتح تبويبه — ومنه ترقيمُ صفحات الإعدادات الخاصة.
*/
function openAttTab(id) {
    const pane = document.getElementById(id);
    if (! pane || ! pane.classList.contains('att-pane')) return false;
    document.querySelectorAll('.att-pane').forEach(p => p.classList.toggle('active', p === pane));
    document.querySelectorAll('.att-tabs .nav-link').forEach(a => a.classList.toggle('active', a.dataset.attTab === id));
    // زرُّ حفظ الإعدادات العامة أسفل النموذج يظهر مع تبويباتها وحدها
    const foot = document.getElementById('attGeneralFoot');
    if (foot) foot.style.display = pane.closest('#attGeneral') ? '' : 'none';
    if (history.replaceState) history.replaceState(null, '', '#' + id);
    return true;
}
document.addEventListener('click', function (e) {
    const link = e.target.closest('[data-att-tab]');
    if (! link) return;
    e.preventDefault();
    openAttTab(link.dataset.attTab);
    if (link.classList.contains('sl-ctx__item')) {
        document.querySelector('.att-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
(function () {
    const fromHash = window.location.hash.slice(1);
    // بعد خطأ تحقّقٍ في النموذج العام يُفتح تبويبُ أوّل حقلٍ فيه خطأ
    const invalid = @json(array_keys($errors->getMessages()));
    let target = fromHash;
    if (invalid.length) {
        const field = document.querySelector('#attGeneral [name="' + invalid[0] + '"], [form="attGeneral"][name="' + invalid[0] + '"]');
        const pane = field && field.closest('.att-pane');
        if (pane) target = pane.id;
    }
    if (! (target && openAttTab(target))) openAttTab('tab-shift');
})();

// واحدٌ ⇐ يُحمَّل إعدادُه للتعديل؛ عدّةٌ ⇐ نموذجٌ فارغ يُطبَّق عليهم جميعاً
function onEmployeesChanged() {
    const ids = $('#employeeConfigSelect').val() || [];
    const bulk = document.getElementById('empBulkNotice');
    document.getElementById('pickCount').textContent = ids.length ? '(' + ids.length + ' محدد)' : '';

    if (ids.length === 1) {
        bulk.style.display = 'none';
        loadEmployeeConfig(ids[0]);
        return;
    }

    // لا يُمسح ما كتبه المستخدم — يُفرَّغ النموذج فقط إن كان يحمل إعدادَ موظفٍ حُمِّل للتعديل
    if (formHoldsLoadedConfig) {
        resetEmployeeConfigForm(true);
    }
    if (ids.length > 1) {
        bulk.innerHTML = '<i class="feather icon-users me-2"></i> سيُطبَّق هذا الإعداد على <strong>' + ids.length
            + ' موظف</strong> — ويستبدل أي إعداد خاص سابق لهم. الحقل الفارغ يعني «استخدم الإعداد العام».';
        bulk.style.display = '';
    } else {
        bulk.style.display = 'none';
    }
}

// هل في النموذج الآن إعدادُ موظفٍ محفوظٌ حُمِّل للتعديل (لا ما كتبه المستخدم بيده)؟
let formHoldsLoadedConfig = false;

function employeeConfigForm() {
    return document.querySelector('#tab-employees form');
}

function resetEmployeeConfigForm(keepEmployee) {
    const form = employeeConfigForm();
    if (!form) return;
    const employees = $('#employeeConfigSelect').val() || [];
    form.reset();
    form.querySelectorAll('input[name="weekly_off_days[]"]').forEach(c => { c.checked = false; });
    $('#cfgKiosks').val([]).trigger('change.select2');
    $('#employeeConfigSelect').val(keepEmployee ? employees : []).trigger('change.select2');
    if (! keepEmployee) {
        document.getElementById('empBulkNotice').style.display = 'none';
        document.getElementById('pickCount').textContent = '';
    }
    document.getElementById('empConfigNotice').style.display = 'none';
    formHoldsLoadedConfig = false;
    togglePlace();
}

function loadEmployeeConfig(employeeId) {
    const form = employeeConfigForm();
    if (!form) return;

    $('#employeeConfigSelect').val([String(employeeId)]).trigger('change.select2');
    document.getElementById('empBulkNotice').style.display = 'none';
    document.getElementById('pickCount').textContent = '(1 محدد)';
    document.querySelector('#tab-employees').scrollIntoView({ behavior: 'smooth', block: 'start' });

    $.getJSON(EMPLOYEE_CONFIG_URL.replace('__ID__', employeeId))
        .done(function (res) {
            const notice = document.getElementById('empConfigNotice');

            // طريقةُ التسجيل ومكانُه من ملفّ الموظف
            const applyRegistration = function () {
                const reg = res.employee || {};
                form.querySelector('[name=attendance_channel]').value = reg.attendance_channel || 'branch';
                form.querySelector('[name=attendance_geofence_id]').value = reg.attendance_geofence_id || '';
                $('#cfgKiosks').val(reg.kiosk_ids || []).trigger('change.select2');
                togglePlace();
            };

            if (!res.exists) {
                // لا إعدادَ محفوظ: يبقى ما كتبه المستخدم كما هو؛ ويُفرَّغ فقط ما حُمِّل من موظفٍ سابق
                notice.style.display = 'none';
                if (! formHoldsLoadedConfig) {
                    return;
                }
                formHoldsLoadedConfig = false;
                form.querySelectorAll('input[name="weekly_off_days[]"]').forEach(c => { c.checked = false; });
                applyRegistration();
                form.querySelector('[name=work_mode]').value = 'shift';
                ['expected_check_in', 'expected_check_out', 'late_grace_minutes',
                 'absent_after_minutes', 'work_hours_per_day', 'checkout_method', 'notes', 'break_allowed_minutes']
                    .forEach(n => { const f = form.querySelector('[name=' + n + ']'); if (f) f.value = ''; });
                notice.style.display = 'none';
                return;
            }

            form.querySelectorAll('input[name="weekly_off_days[]"]').forEach(c => { c.checked = false; });
            applyRegistration();
            const c = res.config;
            const set = (name, value) => {
                const f = form.querySelector('[name=' + name + ']');
                if (f) f.value = (value === null || value === undefined) ? '' : value;
            };
            set('expected_check_in', c.expected_check_in);
            set('expected_check_out', c.expected_check_out);
            set('late_grace_minutes', c.late_grace_minutes);
            set('absent_after_minutes', c.absent_after_minutes);
            set('work_hours_per_day', c.work_hours_per_day);
            set('checkout_method', c.checkout_method || '');
            set('notes', c.notes);
            set('work_mode', c.work_mode || 'shift');
            set('break_allowed_minutes', c.break_allowed_minutes);
            formHoldsLoadedConfig = true;
            (c.weekly_off_days || []).forEach(function (day) {
                const box = form.querySelector('input[name="weekly_off_days[]"][value="' + day + '"]');
                if (box) box.checked = true;
            });
            notice.style.display = '';
        })
        .fail(function () {
            if (window.toastr) { toastr.error('تعذّر تحميل إعدادات هذا الموظف'); }
        });
}

// مكانُ التسجيل من الجوال لا معنى له لموظف البطاقة
function togglePlace() {
    const channel = document.getElementById('cfgChannel');
    const wrap = document.getElementById('cfgPlaceWrap');
    if (channel && wrap) { wrap.style.display = channel.value === 'branch' ? 'none' : ''; }
    // والأجهزةُ لا معنى لها للميداني
    const kioskWrap = document.getElementById('cfgKioskWrap');
    if (channel && kioskWrap) { kioskWrap.style.display = channel.value === 'field' ? 'none' : ''; }
}
document.addEventListener('DOMContentLoaded', function () {
    const channel = document.getElementById('cfgChannel');
    if (channel) { channel.addEventListener('change', togglePlace); togglePlace(); }
});

</script>
@endpush
