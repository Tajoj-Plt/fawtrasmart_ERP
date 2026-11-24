@extends('hr::master')

@section('title', 'إعدادات نظام الحضور')

@section('content')
@php
    use App\Constants\planFeatures\HrPlanFeatures;
    use App\Support\PlanLock;
@endphp

    {{--
        روابطُ هذه الشاشة كانت مكتوبةً بعناوينَ ثابتة تبدأ بـ `/ar/` — أي
        بلغةٍ مثبَّتة. والنظام يدعم سبعَ لغات، فكان المستخدمُ بغير العربية
        يُقذف إلى العربية عند كل بلاطة. وواحدٌ منها (`/ar/hr/attendance/sheets/index`)
        لم يكن له مسارٌ أصلاً — عنوانٌ يردّ ٤٠٤.

        و`route()` تبني العنوانَ بلغة الطلب، وتكشف الخطأَ وقتَ العرض لا
        وقتَ نقر المستخدم.
    --}}
<style>
    .ah-hero { background:linear-gradient(135deg, var(--app-primary, #6366f1) 0%, color-mix(in srgb, var(--app-primary, #6366f1) 70%, #a78bfa) 100%); color:#fff; border-radius:12px; padding:24px 28px; margin-bottom:20px; box-shadow:0 4px 12px rgba(99,102,241,.25); }
    .ah-hero h2 { margin:0; font-size:1.5rem; color:#fff; }
    .ah-hero p { color:rgba(255,255,255,.85); margin:6px 0 0; font-size:.9rem; }
    .ah-hero h2 i { margin-left:8px; }

    .ah-section { background: var(--app-surface, #fff); border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.04); margin-bottom:20px; overflow:hidden; }
    .ah-section-head { padding:14px 22px; background: var(--app-body-bg, #f9fafb); border-bottom: 1px solid var(--app-border, #f0f0f0); font-weight:700; color: var(--app-text, #2c3e50); display:flex; align-items:center; gap:8px; }
    .ah-section-head .step { width:28px; height:28px; background:var(--app-primary, #6366f1); color:#fff; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:.85rem; font-weight:700; }
    .ah-section-body { padding:20px 22px; }

    .ah-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:14px; }
    .ah-item { background: var(--app-surface, #fff); border: 1px solid var(--app-border, #e5e7eb); border-radius:10px; padding:18px; text-align:center; transition:.2s; text-decoration:none !important; color:inherit; display:block; }
    .ah-item:hover { transform:translateY(-3px); box-shadow:0 6px 16px rgba(0,0,0,.08); border-color:var(--app-primary, #6366f1); }
    .ah-item .icon { width:56px; height:56px; border-radius:14px; background: var(--app-primary-soft, #eef2ff); color:var(--app-primary, #6366f1); display:inline-flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:10px; }
    .ah-item .title { font-weight:700; color: var(--app-text, #1f2937); font-size:.95rem; margin-bottom:4px; }
    .ah-item .desc { font-size:.75rem; color: var(--app-muted, #6b7280); }

    /* ألوان مختلفة لكل قسم */
    .ah-item.green .icon  { background: var(--app-success-soft, #d1fae5); color: var(--app-success, #065f46); }
    .ah-item.green:hover  { border-color: var(--app-success, #10b981); }
    .ah-item.amber .icon  { background: var(--app-warning-soft, #fef3c7); color: var(--app-warning, #92400e); }
    .ah-item.amber:hover  { border-color: var(--app-warning, #f59e0b); }
    .ah-item.red .icon    { background: var(--app-danger-soft, #fee2e2); color: var(--app-danger, #991b1b); }
    .ah-item.red:hover    { border-color: var(--app-danger, #ef4444); }
    .ah-item.blue .icon   { background: var(--app-info-soft, #dbeafe); color: var(--app-info, #1e3a8a); }
    .ah-item.blue:hover   { border-color: var(--app-info, #3b82f6); }
    .ah-item.purple .icon { background: var(--app-primary-soft, #ede9fe); color:#5b21b6; }
    .ah-item.purple:hover { border-color: var(--app-primary, #8b5cf6); }
    .ah-item.cyan .icon   { background:#cffafe; color:#0e7490; }
    .ah-item.cyan:hover   { border-color:#06b6d4; }

    .ah-step-badge { background: var(--app-surface, #fff); color:var(--app-primary, #6366f1); padding:3px 10px; border-radius:10px; font-size:.7rem; font-weight:700; border: 1px solid var(--app-primary-soft, #c7d2fe); margin-right:8px; }
</style>

<div class="content-body">

    {{--
        هذه اللوحةُ للإعدادات وحدها. ما كان فيها من غيرها انتقل إلى مجموعته:
          · أماكن التسجيل والأجهزة وآلات البصمة ← «الأجهزة وأماكن التسجيل»
          · أرصدة الإجازات ← تبويبٌ في «الإجازات»
        و«محدّدات الحضور» و«قواعد الحضور» أُخفيتا: الأولى تكرّر الموقعَ
        وعنوانَ الشبكة الموجودَين في أماكن التسجيل، والثانية تكرّر إعدادات
        الدوام بصيغةٍ نصّية (`مفتاح=قيمة`) لا يفهمها غيرُ مبرمج. وشاشتاهما
        ومنطقُهما باقيان لما حُفظ فيهما سلفاً.
    --}}
    <div class="stk-page-head">
        <div class="stk-page-head__titles">
            <span class="stk-page-head__icon" aria-hidden="true"><i class="feather icon-settings"></i></span>
            <div class="min-width-0">
                <h2 class="stk-page-head__title">إعدادات الحضور والانصراف</h2>
                <div class="stk-page-head__sub">تُضبط مرة واحدة. ترتيب التجهيز كاملاً — من الإعدادات حتى تسجيل أول موظف — في «لوحة الحضور».</div>
            </div>
        </div>
    </div>

    {{-- ============ الأساسيات ============ --}}
    <div class="ah-section">
        <div class="ah-section-head">
            <span class="step">1</span>
            الأساسيات
        </div>
        <div class="ah-section-body">
            <div class="ah-grid">
                @permission('attendance_settings.manage')
                    <a href="{{ PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES) ? PlanLock::url() : route('attendance.v2.settings.index') }}" class="ah-item purple {{ PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES) ? 'stk-plan-locked' : '' }}" @if (PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES)) title="{{ PlanLock::reason(HrPlanFeatures::ATTENDANCE_RULES) }}" @endif>
                        <div class="icon"><i class="feather icon-clock"></i></div>
                        <div class="title">الدوام والحضور</div>
                        <div class="desc">بداية الدوام، السماح بالتأخير، العطلة الأسبوعية، الخصم</div>
                    </a>
                @endpermission
                @permission('holidays.index')
                    <a href="{{ PlanLock::locked(HrPlanFeatures::HOLIDAYS) ? PlanLock::url() : route('holiday_lists.index') }}" class="ah-item blue {{ PlanLock::locked(HrPlanFeatures::HOLIDAYS) ? 'stk-plan-locked' : '' }}" @if (PlanLock::locked(HrPlanFeatures::HOLIDAYS)) title="{{ PlanLock::reason(HrPlanFeatures::HOLIDAYS) }}" @endif>
                        <div class="icon"><i class="feather icon-calendar"></i></div>
                        <div class="title">العطل الرسمية</div>
                        <div class="desc">الأعياد واليوم الوطني ويوم التأسيس</div>
                    </a>
                @endpermission
                {{-- القواعدُ والمحدّداتُ شاشتان قائمتان بصلاحيتهما (`attendance_rules.*`) — ولم يكن إليهما رابطٌ في أيّ قائمة --}}
                @permission('attendance_rules.index')
                    <a href="{{ PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES) ? PlanLock::url() : route('attendance-rules.index') }}" class="ah-item {{ PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES) ? 'stk-plan-locked' : '' }}" @if (PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES)) title="{{ PlanLock::reason(HrPlanFeatures::ATTENDANCE_RULES) }}" @endif>
                        <div class="icon"><i class="feather icon-sliders"></i></div>
                        <div class="title">قواعد الحضور</div>
                        <div class="desc">قواعد احتساب التأخير والغياب والخصم</div>
                    </a>
                    <a href="{{ PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES) ? PlanLock::url() : route('attendance_determinants.index') }}" class="ah-item {{ PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES) ? 'stk-plan-locked' : '' }}" @if (PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES)) title="{{ PlanLock::reason(HrPlanFeatures::ATTENDANCE_RULES) }}" @endif>
                        <div class="icon"><i class="feather icon-filter"></i></div>
                        <div class="title">محددات الحضور</div>
                        <div class="desc">شروط إضافية على تسجيل موظفين بعينهم</div>
                    </a>
                @endpermission
                @permission('attendance_settings.manage')
                    <a href="{{ PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES) ? PlanLock::url() : route('settings_basic.index') }}" class="ah-item {{ PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES) ? 'stk-plan-locked' : '' }}" @if (PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES)) title="{{ PlanLock::reason(HrPlanFeatures::ATTENDANCE_RULES) }}" @endif>
                        <div class="icon"><i class="feather icon-settings"></i></div>
                        <div class="title">الموافقات وبداية السنة</div>
                        <div class="desc">من يعتمد الإجازات، ومتى تتجدد الأرصدة</div>
                    </a>
                @endpermission
            </div>
        </div>
    </div>

    @if(canAny(['leave_types.index', 'leave_policies.index']))
    {{-- ============ الإجازات ============ --}}
    <div class="ah-section">
        <div class="ah-section-head">
            <span class="step">2</span>
            الإجازات
        </div>
        <div class="ah-section-body">
            <div class="ah-grid">
                @permission('leave_types.index')
                    <a href="{{ PlanLock::locked(HrPlanFeatures::LEAVE_TYPES) ? PlanLock::url() : route('leave_types.index') }}" class="ah-item green {{ PlanLock::locked(HrPlanFeatures::LEAVE_TYPES) ? 'stk-plan-locked' : '' }}" @if (PlanLock::locked(HrPlanFeatures::LEAVE_TYPES)) title="{{ PlanLock::reason(HrPlanFeatures::LEAVE_TYPES) }}" @endif>
                        <div class="icon"><i class="feather icon-log-out"></i></div>
                        <div class="title">أنواع الإجازات</div>
                        <div class="desc">سنوية، مرضية، طارئة — ورصيد كلٍّ منها</div>
                    </a>
                @endpermission
                @permission('leave_policies.index')
                    <a href="{{ PlanLock::locked(HrPlanFeatures::LEAVE_TYPES) ? PlanLock::url() : route('leave_policy.index') }}" class="ah-item green {{ PlanLock::locked(HrPlanFeatures::LEAVE_TYPES) ? 'stk-plan-locked' : '' }}" @if (PlanLock::locked(HrPlanFeatures::LEAVE_TYPES)) title="{{ PlanLock::reason(HrPlanFeatures::LEAVE_TYPES) }}" @endif>
                        <div class="icon"><i class="feather icon-bookmark"></i></div>
                        <div class="title">سياسات الإجازات</div>
                        <div class="desc">أي أنواع الإجازات يستحقها كل قسم أو موظف</div>
                    </a>
                @endpermission
            </div>
        </div>
    </div>
    @endif

    {{--
        ═══ الإغلاق التلقائي للأيام غير المكتملة ═══

        مهمّةُ `attendance:process` هي التي تُغلق يومَ من لم يُسجِّل، وتحسب
        التأخيرَ والغياب، وتُنشئ الخصميات. وحين تتوقّف — تُعطَّل من شاشة
        المهام، أو يسكت `schedule:run` على الخادم — لا يظهر ذلك في أيّ شاشةٍ
        من شاشات الحضور: الأيامُ تبقى مفتوحةً بلا حساب، والرواتبُ تُصرف على
        بياناتٍ لم تُعالَج، ولا شيءَ يقول إن شيئاً توقّف.

        فحالتُها تُقرأ هنا، حيث يقف من يدير الحضور — لا في شاشة المهام وحدها.
    --}}
    <div class="ah-section">
        <div class="ah-section-head">
            <span class="step" style="background: var(--app-info, #3b82f6);"><i class="feather icon-refresh-cw"></i></span>
            الإغلاق التلقائي للأيام غير المكتملة
        </div>
        <div class="ah-section-body">
            @if ($autoCloseJob)
                @php
                    $running = $autoCloseJob->isActive();
                    $lastFailed = $autoCloseJob->last_status === \App\Models\CronJobExecution::STATUS_FAILED;
                    $staleHours = $autoCloseJob->last_run_at
                        ? $autoCloseJob->last_run_at->diffInHours(now())
                        : null;
                @endphp

                <div class="d-flex flex-wrap align-items-center" style="gap:14px">
                    <span class="badge badge-{{ $running ? 'success' : 'danger' }}" style="font-size:.8rem">
                        {{ $running ? 'مُفعَّلة' : 'متوقّفة' }}
                    </span>

                    <span>
                        آخر تشغيل:
                        <strong>
                            {{ $autoCloseJob->last_run_at?->format('Y-m-d H:i') ?? 'لم تُشغَّل بعد' }}
                        </strong>
                        @if ($staleHours !== null && $staleHours > 36)
                            {{-- يومٌ ونصف بلا تشغيل: ليست جدولةً يومية تعمل --}}
                            <span class="badge badge-warning">مضى {{ (int) $staleHours }} ساعة</span>
                        @endif
                    </span>

                    @if ($lastFailed)
                        <span class="badge badge-danger">آخر تشغيل فشل</span>
                    @endif

                    <a href="{{ route('CronJobs.show', $autoCloseJob->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="feather icon-external-link"></i> إدارة الجدولة
                    </a>
                </div>

                @unless ($running)
                    <div class="alert alert-danger mt-3 mb-0">
                        الإغلاقُ التلقائي متوقّف: الأيامُ غير المكتملة لا تُحسَب، ولا تُنشأ
                        خصمياتُ الغياب والتأخير، وما يُرحَّل إلى الرواتب ناقص.
                    </div>
                @endunless
            @else
                <div class="alert alert-warning mb-0">
                    لا توجد مهمّةٌ مجدولةٌ لمعالجة الحضور اليومي
                    (<code>attendance:process</code>) — فالأيامُ غير المكتملة لا تُغلق آلياً.
                    <a href="{{ route('CronJobs.index') }}">أضِفها من شاشة المهام المجدولة</a>.
                </div>
            @endif
        </div>
    </div>

    {{-- «المرحلة 4: متقدم» كانت بلاطةً واحدة — «قوالب الطباعة» — وأُزيلت:
         القوالبُ تُدار من «قوالب المستندات» في الإعدادات العامة وحدها. --}}

    {{-- وقسمُ «للتشغيل اليومي» أُزيل: كان يكرّر روابطَ قائمة «الحضور» الجانبية
         كلَّها، فتبقى هذه اللوحةُ للإعدادات وحدها. --}}
</div>
@endsection
