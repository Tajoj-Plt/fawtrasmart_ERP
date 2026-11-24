@extends('client::master')

@section('title')
    تحديث الحالة الجماعي
@stop

{{--
    تعيينُ الحالات والأوضاع جماعياً — أُعيد تصميمه (2026-09-30، طلب المستخدم:
    «مرتّبة وتصميم احترافي»).

    التخطيط: ترويسةٌ بأرقام من يحتاج ضبطاً، ثمّ بطاقةُ «ضبط الوضع حسب الحالة»
    بنقرة، ثمّ عمودان — يميناً: ① جمعُ العملاء (تصفية أو بحث) وجدولُ
    المحدَّدين؛ يساراً ثابتاً: ② التطبيق بملخّصٍ حيّ لما سيقع.

    المعرّفاتُ التي يقرؤها السكربت لم تتغيّر، والنداءاتُ الثلاثة نفسُها
    (`searchClientsForSelect`، `getFilteredClients`، `bulkUpdateStatus`).
--}}

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    .bs { --bs-line: var(--cl-line, #e6e9f0); --bs-soft: var(--cl-line-soft, #eef1f6); --bs-tint: color-mix(in srgb, var(--app-primary, #7367f0) 6%, var(--app-surface, #fff)); --bs-pri: var(--app-primary, #7367f0); --bs-text: var(--app-text, #3b4253); --bs-muted: var(--app-muted, #6e6b7b); }

    /* ═══ الأرقام ═══ */
    .bs-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1rem; }
    .bs-kpi { display: flex; align-items: center; gap: .85rem; padding: 1rem 1.1rem; border-radius: 14px; background: var(--app-surface, #fff); border: 1px solid var(--bs-line); box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
    .bs-kpi__icon { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; flex: none; color: var(--tone); background: color-mix(in srgb, var(--tone) 13%, #fff); }
    .bs-kpi__num { font-size: 1.45rem; font-weight: 800; color: var(--bs-text); line-height: 1.15; }
    .bs-kpi__label { font-size: .8rem; font-weight: 600; color: var(--bs-muted); }

    /* ═══ ضبطٌ بنقرة ═══ */
    .bs-quick { display: flex; align-items: center; gap: 1.2rem; flex-wrap: wrap; padding: 1.1rem 1.25rem; border-radius: 14px; margin-bottom: 1rem; background: linear-gradient(135deg, color-mix(in srgb, var(--bs-pri) 9%, #fff), color-mix(in srgb, var(--bs-pri) 3%, #fff)); border: 1px solid color-mix(in srgb, var(--bs-pri) 22%, #fff); }
    .bs-quick__icon { width: 48px; height: 48px; border-radius: 14px; background: var(--bs-pri); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; flex: none; box-shadow: 0 6px 16px color-mix(in srgb, var(--bs-pri) 35%, transparent); }
    .bs-quick__body { flex: 1; min-width: 240px; }
    .bs-quick__title { font-weight: 800; font-size: 1rem; color: var(--bs-text); margin: 0 0 .15rem; }
    .bs-quick__sub { font-size: .82rem; color: var(--bs-muted); margin: 0 0 .55rem; }
    .bs-quick__chips { display: flex; gap: .5rem; flex-wrap: wrap; }
    .bs-map { display: inline-flex; align-items: center; gap: .45rem; padding: .3rem .7rem; border-radius: 999px; background: var(--app-surface, #fff); border: 1px solid var(--bs-line); font-size: .8rem; font-weight: 700; color: var(--bs-text); }
    .bs-map b { color: var(--bs-pri); }
    .bs-map i { color: var(--bs-muted); font-size: .7rem; }

    /* ═══ التخطيط ═══ */
    .bs-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 1rem; align-items: start; }
    .bs-card { background: var(--app-surface, #fff); border: 1px solid var(--bs-line); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); margin-bottom: 1rem; }
    .bs-card__head { display: flex; align-items: center; gap: .75rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--bs-soft); }
    .bs-card__body { padding: 1.1rem 1.25rem; }
    .bs-step { width: 30px; height: 30px; border-radius: 50%; background: var(--bs-pri); color: #fff; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; font-size: .9rem; flex: none; }
    .bs-card__title { margin: 0; font-size: 1rem; font-weight: 800; color: var(--bs-text); }
    .bs-card__sub { margin: 0; font-size: .78rem; color: var(--bs-muted); }
    .bs-card__tools { margin-inline-start: auto; display: flex; align-items: center; gap: .5rem; }
    .bs-aside { position: sticky; top: 90px; }

    /* تبويبا الجمع */
    .bs-tabs { display: inline-flex; padding: 4px; border-radius: 10px; background: var(--bs-soft); gap: 4px; margin-bottom: 1rem; }
    .bs-tabs button { border: 0; background: transparent; padding: .45rem 1rem; border-radius: 8px; font-weight: 700; font-size: .84rem; color: var(--bs-muted); display: inline-flex; align-items: center; gap: .4rem; }
    .bs-tabs button.is-active { background: var(--app-surface, #fff); color: var(--bs-pri); box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
    .bs-pane[hidden] { display: none !important; }

    .bs .form-label { font-size: .8rem; font-weight: 700; color: var(--bs-text); margin-bottom: .35rem; }
    .bs-hint { display: block; font-size: .74rem; color: var(--bs-muted); margin-top: .3rem; }
    .bs-foot { display: flex; justify-content: flex-end; gap: .5rem; padding-top: 1rem; margin-top: .5rem; border-top: 1px dashed var(--bs-line); }

    /* ═══ جدول المحدَّدين ═══ */
    .bs-count { display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 24px; padding: 0 .5rem; border-radius: 999px; background: var(--bs-pri); color: #fff; font-size: .78rem; font-weight: 800; }
    .bs-listsearch { position: relative; }
    .bs-listsearch input { width: 200px; height: 34px; border: 1px solid var(--bs-line); border-radius: 8px; padding: 0 .7rem; padding-inline-start: 2rem; font-size: .8rem; outline: none; }
    .bs-listsearch input:focus { border-color: var(--bs-pri); }
    .bs-listsearch i { position: absolute; inset-inline-start: .65rem; top: 50%; transform: translateY(-50%); color: var(--bs-muted); font-size: .78rem; }
    .bs-table-wrap { max-height: 480px; overflow: auto; }
    .bs-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .84rem; }
    .bs-table th { position: sticky; top: 0; z-index: 1; background: var(--bs-tint); color: var(--bs-muted); font-weight: 800; font-size: .74rem; text-align: start; padding: .6rem 1rem; border-bottom: 1px solid var(--bs-line); }
    .bs-table td { padding: .6rem 1rem; border-bottom: 1px solid var(--bs-soft); color: var(--bs-text); vertical-align: middle; }
    .bs-table tbody tr:hover td { background: var(--bs-tint); }
    .bs-table .bs-name { font-weight: 700; }
    .bs-table .bs-code { color: var(--bs-muted); font-size: .78rem; direction: ltr; unicode-bidi: isolate; }
    .bs-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); white-space: nowrap; }
    .bs-pill::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--c); }
    .bs-pill--none { --c: #a0a6b4; }
    .bs-rm { border: 0; background: transparent; width: 30px; height: 30px; border-radius: 8px; color: var(--bs-muted); }
    .bs-rm:hover { background: var(--app-danger-soft, rgba(234, 84, 85, .12)); color: var(--app-danger, #ea5455); }
    .bs-empty { text-align: center; padding: 2.5rem 1rem; color: var(--bs-muted); }
    .bs-empty i { font-size: 2rem; display: block; margin-bottom: .6rem; opacity: .5; }
    .bs-empty b { display: block; color: var(--bs-text); margin-bottom: .2rem; }

    /* ═══ لوحة التطبيق ═══ */
    .bs-summary { border-radius: 12px; background: var(--bs-tint); border: 1px solid var(--bs-soft); padding: .9rem 1rem; margin: 1rem 0; }
    .bs-summary__num { font-size: 1.9rem; font-weight: 800; color: var(--bs-pri); line-height: 1; }
    .bs-summary__label { font-size: .8rem; color: var(--bs-muted); font-weight: 600; }
    .bs-summary__lines { margin: .7rem 0 0; padding: 0; list-style: none; font-size: .82rem; }
    .bs-summary__lines li { display: flex; justify-content: space-between; gap: .5rem; padding: .3rem 0; border-top: 1px dashed var(--bs-line); color: var(--bs-muted); }
    .bs-summary__lines li b { color: var(--bs-text); }
    .bs-apply { width: 100%; height: 46px; font-weight: 800; font-size: .95rem; border-radius: 10px; }

    /* ═══ Select2 بألوان الثيم ═══ */
    .bs .select2-container { width: 100% !important; }
    .bs .select2-container .select2-selection--single { height: 40px !important; border: 1px solid var(--bs-line) !important; border-radius: 9px !important; display: flex !important; align-items: center !important; padding: 0 .75rem !important; padding-inline-end: 2rem !important; background: var(--app-surface, #fff) !important; }
    .bs .select2-container--focus .select2-selection--single, .bs .select2-container--open .select2-selection--single { border-color: var(--bs-pri) !important; box-shadow: 0 0 0 3px color-mix(in srgb, var(--bs-pri) 14%, transparent) !important; }
    .bs .select2-selection__rendered { line-height: 38px !important; padding: 0 !important; font-size: .86rem; color: var(--bs-text) !important; }
    .bs .select2-selection__arrow { height: 38px !important; left: 8px !important; right: auto !important; top: 0 !important; }
    .select2-dropdown { border: 1px solid var(--bs-line, #e6e9f0) !important; border-radius: 10px !important; box-shadow: 0 10px 28px rgba(16, 24, 40, .14) !important; direction: rtl; z-index: 9999 !important; overflow: hidden; }
    .select2-search--dropdown .select2-search__field { border: 1px solid #e6e9f0 !important; border-radius: 8px !important; padding: .4rem .65rem !important; font-size: .82rem; }
    .select2-results__option { padding: .55rem .8rem !important; font-size: .84rem; }
    .select2-results__option--highlighted[aria-selected] { background: var(--app-primary, #7367f0) !important; color: #fff !important; }
    .select2-results__group { font-size: .74rem !important; color: var(--app-muted, #6e6b7b); background: #f7f8fb; }
    .s2-row { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; }
    .s2-name { font-weight: 700; }
    .s2-code { font-size: .74rem; opacity: .75; }
    .s2-added { font-size: .72rem; font-weight: 700; opacity: .8; margin-inline-start: auto; }
    .select2-results__option--highlighted .bs-pill { background: rgba(255, 255, 255, .2); color: #fff; }

    @media (max-width: 1199px) {
        .bs-grid { grid-template-columns: 1fr; }
        .bs-aside { position: static; }
    }
    @media (max-width: 767px) {
        .bs-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .bs-listsearch input { width: 140px; }
        .bs-table .bs-col-stage { display: none; }
    }
</style>
@stop

@section('content')
@include('client::partials.settings-theme')
@include('components.hint-tips')
<div class="cl-scope bs">

    <div class="cl-page-head">
        <div class="cl-page-head__titles">
            <span class="cl-page-head__icon"><i class="fas fa-users-cog"></i></span>
            <div>
                <h2 class="cl-page-head__title">تحديث جماعي للعملاء</h2>
                <p class="cl-page-head__sub">غيّر حالة عملاء كثيرين أو وضعهم دفعة واحدة</p>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('sales.department.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">إدارة العملاء</a></li>
                    <li class="breadcrumb-item active">تحديث جماعي</li>
                </ol>
            </div>
        </div>
    </div>

    @include('client::components.guide', [
        'key' => 'clients.bulk-status.index',
        'title' => 'كيف تعمل هذه الشاشة؟',
        'lead' => 'خطوتان: اجمع العملاء المطلوبين في القائمة (بحثاً أو تصفيةً)، ثم اختر الوضع الجديد و/أو الحالة الجديدة وطبّق — الاثنان معاً في عملية واحدة إن أردت.',
        'blocks' => [
            '① اجمع العملاء' => 'إمّا بتصفيةٍ (الحالة الحالية — وفيها الحالات القديمة مثل ضريبي وبلدية —، وضع العميل، الموظف، «تابع لـ» وما تحته) ثم «جلب وإضافة للقائمة»، أو بالبحث عن كل عميل بالاسم/الكود وإضافته يدوياً.',
            '② طبّق' => 'اختر «الوضع الجديد» و/أو «الحالة الجديدة» في اللوحة الجانبية واضغط «تطبيق التحديث» — يشمل كل من في القائمة. ما تتركه على «بدون تغيير» يبقى كما هو.',
            'مثال: نقل الحالات القديمة إلى الوضع' => 'صفِّ بالحالة الحالية «ضريبي» ← جلب ← الوضع الجديد «ضريبي» والحالة الجديدة «نشط» ← تطبيق. إن لم يكن وضع «ضريبي» موجوداً فاختره من مجموعة «من الحالات القديمة» أسفل القائمة، ويُنشأ تلقائياً بالاسم واللون نفسيهما عند التطبيق.',
        ],
    ])

    @if (session('success'))
        <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i> {{ session('error') }}</div>
    @endif

    {{-- ═══════════ من يحتاج ضبطاً ═══════════ --}}
    <div class="bs-kpis">
        <div class="bs-kpi" style="--tone:#f59e0b">
            <span class="bs-kpi__icon"><i class="fas fa-flag"></i></span>
            <div><div class="bs-kpi__num">{{ number_format($counts['no_stage']) }}</div><div class="bs-kpi__label">عملاء بدون وضع</div></div>
        </div>
        <div class="bs-kpi" style="--tone:#ea5455">
            <span class="bs-kpi__icon"><i class="fas fa-user-slash"></i></span>
            <div><div class="bs-kpi__num">{{ number_format($counts['no_status']) }}</div><div class="bs-kpi__label">عملاء بدون حالة</div></div>
        </div>
        <div class="bs-kpi" style="--tone:#8e44ad">
            <span class="bs-kpi__icon"><i class="fas fa-history"></i></span>
            <div><div class="bs-kpi__num">{{ number_format($counts['legacy']) }}</div><div class="bs-kpi__label">على حالات قديمة</div></div>
        </div>
        <div class="bs-kpi" style="--tone:var(--app-primary, #7367f0)">
            <span class="bs-kpi__icon"><i class="fas fa-list-check"></i></span>
            <div><div class="bs-kpi__num" id="kpiSelected">0</div><div class="bs-kpi__label">في قائمة التحديث</div></div>
        </div>
    </div>

    {{-- ═══════════ ضبطٌ بنقرة: كلُّ عميلٍ بلا وضع يأخذ وضعاً باسم حالته ═══════════ --}}
    <div class="bs-quick">
        <span class="bs-quick__icon"><i class="fas fa-magic"></i></span>
        <div class="bs-quick__body">
            <h5 class="bs-quick__title">ضبط وضع العملاء بلا وضع حسب حالتهم</h5>
            <p class="bs-quick__sub">كل عميل بلا وضع يأخذ وضعاً باسم حالته. من له وضعٌ لا يتغيّر، والوضعُ يُنشأ بالاسم واللون إن لم يوجد.</p>
            <div class="bs-quick__chips">
                @foreach ($statuses as $st)
                    <span class="bs-map">
                        <span class="bs-pill" style="--c:{{ $st->color ?: '#6c757d' }}">{{ $st->name }}</span>
                        بلا وضع: <b>{{ number_format($noStageByStatus[$st->id] ?? 0) }}</b>
                        <i class="fas fa-arrow-left"></i> وضع «{{ $st->name }}»
                    </span>
                @endforeach
            </div>
        </div>
        <form method="POST" action="{{ route('clients.fillStageFromStatus') }}"
              onsubmit="return confirm('سيأخذ كل عميل بلا وضع وضعاً باسم حالته. لا يتغيّر من له وضع. متابعة؟')">
            @csrf
            <button type="submit" class="btn btn-primary px-3" @disabled($noStageByStatus->sum() === 0)>
                <i class="fas fa-check me-1"></i> تطبيق الآن
            </button>
        </form>
    </div>

    <div class="bs-grid">
        {{-- ═══════════ ① جمعُ العملاء ═══════════ --}}
        <div>
            <div class="bs-card">
                <div class="bs-card__head">
                    <span class="bs-step">1</span>
                    <div>
                        <h4 class="bs-card__title">اجمع العملاء المطلوبين</h4>
                        <p class="bs-card__sub">بتصفيةٍ تجلب مجموعة كاملة، أو بالبحث عن عميلٍ بعينه — ويمكن الجمع بينهما</p>
                    </div>
                </div>
                <div class="bs-card__body">
                    <div class="bs-tabs" role="tablist">
                        <button type="button" class="is-active" data-pane="filter"><i class="fas fa-filter"></i> تصفية وجلب</button>
                        <button type="button" data-pane="search"><i class="fas fa-search"></i> بحث بالاسم أو الكود</button>
                    </div>

                    <div class="bs-pane" data-pane="filter">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="filterStatusSelect">الحالة الحالية</label>
                                <select id="filterStatusSelect" class="form-control bs-s2">
                                    <option value="">كل الحالات</option>
                                    <option value="none">بدون حالة ({{ number_format($counts['no_status']) }})</option>
                                    <optgroup label="الحالات المعتمدة">
                                        @foreach($filterStatuses->whereNotNull('kpi_role') as $status)
                                            <option value="{{ $status->id }}">{{ $status->name }} ({{ number_format($status->clients_count) }})</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="حالات قديمة — تُنقل إلى الوضع">
                                        @foreach($filterStatuses->whereNull('kpi_role') as $status)
                                            <option value="{{ $status->id }}">{{ $status->name }} ({{ number_format($status->clients_count) }})</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="filterStageSelect">الوضع الحالي</label>
                                <select id="filterStageSelect" class="form-control bs-s2">
                                    <option value="">كل الأوضاع</option>
                                    <option value="none">بدون وضع ({{ number_format($counts['no_stage']) }})</option>
                                    @foreach($registrationStages as $stage)
                                        <option value="{{ $stage->id }}">{{ $stage->name }} ({{ number_format($stage->registration_stage_clients_count) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="filterEmployeeSelect">الموظف</label>
                                <select id="filterEmployeeSelect" class="form-control bs-s2">
                                    <option value="">كل الموظفين</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->nickname ?: trim($employee->first_name.' '.$employee->middle_name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- «تابع لـ» — الجهةُ وكلُّ ما تحتها؛ المُنتقي الموحّد يكتب معرّفها في #filterBranchSelect --}}
                        <div class="row g-3 mt-0">
                            @include('reports.partials.affiliation-tree', [
                                'treeNodes' => $treeNodes,
                                'name' => 'branch_filter',
                                'fieldId' => 'filterBranchSelect',
                                'showExact' => false,
                            ])
                        </div>

                        <div class="bs-foot">
                            <button type="button" class="btn btn-outline-secondary" id="resetFiltersBtn">
                                <i class="fas fa-undo me-1"></i> مسح التصفية
                            </button>
                            <button type="button" class="btn btn-primary" id="fetchFilteredBtn" onclick="fetchAndAddFilteredClients()">
                                <i class="fas fa-download me-1"></i> جلب وإضافة للقائمة
                            </button>
                        </div>
                    </div>

                    <div class="bs-pane" data-pane="search" hidden>
                        <label class="form-label" for="clientSelect">ابحث عن العميل</label>
                        <select id="clientSelect" style="width:100%">
                            <option></option>
                        </select>
                        <span class="bs-hint">اختر العميل من النتائج فيُضاف إلى القائمة، وابحث عن التالي.</span>
                    </div>
                </div>
            </div>

            {{-- ═══════════ قائمة التحديث ═══════════ --}}
            <div class="bs-card">
                <div class="bs-card__head">
                    <i class="fas fa-list-check" style="color:var(--bs-pri)"></i>
                    <h4 class="bs-card__title">قائمة التحديث</h4>
                    <span class="bs-count" id="listCounter">0</span>
                    <div class="bs-card__tools">
                        <div class="bs-listsearch">
                            <i class="fas fa-search"></i>
                            <input type="search" id="listSearch" placeholder="بحث في القائمة…" autocomplete="off">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAll()">
                            <i class="fas fa-trash-alt me-1"></i> مسح الكل
                        </button>
                    </div>
                </div>
                <div class="bs-table-wrap">
                    <table class="bs-table">
                        <thead>
                            <tr>
                                <th>العميل</th>
                                <th>الكود</th>
                                <th>الحالة</th>
                                <th class="bs-col-stage">الوضع</th>
                                <th style="width:48px"></th>
                            </tr>
                        </thead>
                        <tbody id="selectedItems"></tbody>
                    </table>
                    <div class="bs-empty" id="listEmpty">
                        <i class="fas fa-inbox"></i>
                        <b>القائمة فارغة</b>
                        اجمع العملاء بالتصفية أو البحث أعلاه
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════ ② التطبيق ═══════════ --}}
        <aside class="bs-aside">
            <div class="bs-card">
                <div class="bs-card__head">
                    <span class="bs-step">2</span>
                    <div>
                        <h4 class="bs-card__title">اختر التغيير وطبّق</h4>
                        <p class="bs-card__sub">الحقلان اختياريان — ما تتركه لا يتغيّر</p>
                    </div>
                </div>
                <div class="bs-card__body">
                    <div class="mb-3">
                        <label class="form-label" for="newStageSelect"><i class="fas fa-flag me-1"></i> الوضع الجديد</label>
                        <div class="cl-select-add">
                            <select id="newStageSelect" class="form-control bs-s2">
                                <option value="">— بدون تغيير —</option>
                                @foreach($registrationStages as $stage)
                                    <option value="{{ $stage->id }}" data-color="{{ $stage->color }}">{{ $stage->name }}</option>
                                @endforeach
                                {{-- الحالات القديمة التي لا وضع باسمها بعد: اختيار إحداها
                                     يُنشئ الوضع بالاسم واللون نفسيهما عند التطبيق. --}}
                                @php
                                    $stageNames = $registrationStages->pluck('name')->all();
                                    $legacyForStage = $filterStatuses->whereNull('kpi_role')->reject(fn ($s) => in_array($s->name, $stageNames, true));
                                @endphp
                                @if ($legacyForStage->isNotEmpty())
                                    <optgroup label="من الحالات القديمة — يُنشأ الوضع تلقائياً">
                                        @foreach($legacyForStage as $status)
                                            <option value="from-status:{{ $status->id }}" data-color="{{ $status->color }}">{{ $status->name }} (جديد)</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            @if (Route::has('clients.registrationStages') && canAny(['settings_customer_followup_status', 'clients_edit_client_settings']))
                                <a href="{{ route('clients.registrationStages') }}" target="_blank"
                                   class="btn btn-outline-primary cl-select-add__btn" title="إضافة وضع جديد (أعد تحميل الصفحة بعدها)">
                                    <i class="feather icon-plus"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="newStatusSelect"><i class="fas fa-user-check me-1"></i> الحالة الجديدة</label>
                        <select id="newStatusSelect" class="form-control bs-s2">
                            <option value="">— بدون تغيير —</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}" data-color="{{ $status->color }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="bs-summary">
                        <div class="bs-summary__num" id="sumCount">0</div>
                        <div class="bs-summary__label">عميل سيُحدَّث</div>
                        <ul class="bs-summary__lines">
                            <li><span>الوضع</span><b id="sumStage">بدون تغيير</b></li>
                            <li><span>الحالة</span><b id="sumStatus">بدون تغيير</b></li>
                        </ul>
                    </div>

                    <button class="btn btn-primary bs-apply" id="applyBtn" onclick="applyUpdate()" disabled>
                        <i class="fas fa-check me-1"></i> تطبيق التحديث
                    </button>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const SEARCH_URL      = @json(route('clients.searchClientsForSelect'));
const BULK_UPDATE_URL = @json(route('clients.bulkUpdateStatus'));
const FILTER_URL      = @json(route('clients.getFilteredClients'));
const CSRF            = @json(csrf_token());
const ACTIVE_STATUS_ID = @json($activeStatusId);

let selectedClients = {};

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const pill = (name, color) => name && name !== '—'
    ? `<span class="bs-pill" style="--c:${esc(color || '#6c757d')}">${esc(name)}</span>`
    : '<span class="bs-pill bs-pill--none">بدون</span>';

// select2 يُعلن التغيير بـjQuery؛ وضبطُ القيمة من الشيفرة يحتاج تنبيهَه
const setSelect = (id, value) => $('#' + id).val(value).trigger('change');

$(function () {
    $('.bs-s2').each(function () {
        $(this).select2({ dir: 'rtl', width: '100%', minimumResultsForSearch: 8, dropdownParent: $(this).parent() });
    });

    // تبويبا الجمع
    document.querySelectorAll('.bs-tabs button').forEach(btn => btn.addEventListener('click', () => {
        document.querySelectorAll('.bs-tabs button').forEach(b => b.classList.toggle('is-active', b === btn));
        document.querySelectorAll('.bs-pane').forEach(p => { p.hidden = p.dataset.pane !== btn.dataset.pane; });
        if (btn.dataset.pane === 'search') setTimeout(() => $('#clientSelect').select2('open'), 50);
    }));

    // اختيار وضعٍ جديد يقترح «نشط» للحالة إن كانت على «بدون تغيير»
    $('#newStageSelect').on('change', function () {
        if (this.value && !$('#newStatusSelect').val() && ACTIVE_STATUS_ID) {
            setSelect('newStatusSelect', String(ACTIVE_STATUS_ID));
        }
        renderSummary();
    });
    $('#newStatusSelect').on('change', renderSummary);

    // التصفية بحالةٍ قديمة تقترح الوضع الذي يحمل اسمها (الموجود أو «جديد»)
    $('#filterStatusSelect').on('change', function () {
        if (!this.value || this.value === 'none' || $('#newStageSelect').val()) return;
        const name = this.selectedOptions[0].text.replace(/\s*\([\d,]+\)\s*$/, '').trim();
        const match = Array.from(document.getElementById('newStageSelect').options).find(o =>
            o.value === 'from-status:' + this.value || (o.value && !o.value.startsWith('from-status:') && o.text.trim() === name));
        if (match) setSelect('newStageSelect', match.value);
    });

    document.getElementById('resetFiltersBtn').addEventListener('click', () => {
        ['filterStatusSelect', 'filterStageSelect', 'filterEmployeeSelect'].forEach(id => setSelect(id, ''));
        $('#filterBranchSelect').val('').trigger('change');
        document.querySelectorAll('.bs-pane[data-pane="filter"] .affiliation-tree select, .bs-pane[data-pane="filter"] [id^="affiliationTree"] select').forEach(s => { s.value = ''; $(s).trigger('change'); });
    });

    document.getElementById('listSearch').addEventListener('input', function () {
        const term = this.value.trim().toLowerCase();
        document.querySelectorAll('#selectedItems tr').forEach(tr => {
            tr.hidden = term !== '' && !tr.dataset.search.includes(term);
        });
    });

    $('#clientSelect').select2({
        dir: 'rtl',
        placeholder: 'اكتب كود أو اسم العميل...',
        minimumInputLength: 1,
        language: {
            inputTooShort: () => 'اكتب حرفاً على الأقل...',
            searching:     () => 'جارٍ البحث...',
            noResults:     () => 'لا توجد نتائج',
        },
        ajax: {
            url: SEARCH_URL,
            dataType: 'json',
            delay: 280,
            data: p => ({ term: p.term }),
            processResults: d => ({ results: d.results }),
            cache: true,
        },
        templateResult: renderOption,
        templateSelection: () => 'اكتب كود أو اسم العميل...',
        width: '100%',
    });

    $('#clientSelect').on('select2:select', function (e) {
        const d = e.params.data;
        addClient({ id: d.id, trade_name: d.trade_name, code: d.code, status_name: d.status_name, status_color: d.status_color, stage_name: d.stage_name, stage_color: d.stage_color });
        $(this).val(null).trigger('change');
    });

    renderList();
});

function fetchAndAddFilteredClients() {
    const statusId = $('#filterStatusSelect').val();
    const stageId = $('#filterStageSelect').val();
    const branchId = $('#filterBranchSelect').val();
    const employeeId = $('#filterEmployeeSelect').val();

    if (!statusId && !stageId && !branchId && !employeeId) {
        Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'اختر معياراً واحداً على الأقل للتصفية.', confirmButtonText: 'موافق' });
        return;
    }

    const btn = document.getElementById('fetchFilteredBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جارٍ الجلب...';

    const url = new URL(FILTER_URL, window.location.origin);
    if (statusId) url.searchParams.append('status_id', statusId);
    if (stageId) url.searchParams.append('registration_stage_id', stageId);
    if (branchId) url.searchParams.append('branch_id', branchId);
    if (employeeId) url.searchParams.append('employee_id', employeeId);

    const done = () => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download me-1"></i> جلب وإضافة للقائمة';
    };

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
        .then(r => r.json())
        .then(d => {
            done();
            if (d.success && d.clients.length > 0) {
                let added = 0;
                d.clients.forEach(c => { if (!selectedClients[c.id]) { selectedClients[c.id] = c; added++; } });
                renderList();
                Swal.fire({ icon: 'success', title: 'تم الجلب', text: `أُضيف ${added} عميل إلى القائمة (المطابق للتصفية: ${d.clients.length}).`, confirmButtonText: 'موافق' });
            } else {
                Swal.fire({ icon: 'info', title: 'لا توجد نتائج', text: 'لا عملاء مطابقين لهذه التصفية.', confirmButtonText: 'موافق' });
            }
        })
        .catch(() => {
            done();
            Swal.fire({ icon: 'error', title: 'خطأ', text: 'حدث خطأ أثناء جلب البيانات.', confirmButtonText: 'موافق' });
        });
}

function renderOption(c) {
    if (c.loading || !c.code) return c.text;
    const added = selectedClients[c.id] ? '<span class="s2-added">✓ في القائمة</span>' : '';
    return $(`<div class="s2-row">
        <span class="s2-name">${esc(c.trade_name)}</span>
        <span class="s2-code">${esc(c.code)}</span>
        ${pill(c.status_name, c.status_color)}
        ${pill(c.stage_name, c.stage_color)}
        ${added}
    </div>`);
}

function addClient(c) {
    if (selectedClients[c.id]) return;
    selectedClients[c.id] = c;
    renderList();
}

function removeClient(id) {
    delete selectedClients[id];
    renderList();
}

function clearAll() {
    selectedClients = {};
    document.getElementById('listSearch').value = '';
    renderList();
}

function renderList() {
    const body = document.getElementById('selectedItems');
    const list = Object.values(selectedClients);
    const count = list.length;

    document.getElementById('listCounter').textContent = count.toLocaleString('en');
    document.getElementById('kpiSelected').textContent = count.toLocaleString('en');
    document.getElementById('listEmpty').hidden = count > 0;

    body.innerHTML = list.map(c => `<tr data-search="${esc(((c.trade_name || '') + ' ' + (c.code || '')).toLowerCase())}">
            <td class="bs-name">${esc(c.trade_name)}</td>
            <td class="bs-code">${esc(c.code)}</td>
            <td>${pill(c.status_name, c.status_color)}</td>
            <td class="bs-col-stage">${pill(c.stage_name, c.stage_color)}</td>
            <td><button type="button" class="bs-rm" title="إزالة من القائمة" onclick="removeClient(${Number(c.id)})"><i class="fas fa-times"></i></button></td>
        </tr>`).join('');

    renderSummary();
}

function renderSummary() {
    const count = Object.keys(selectedClients).length;
    const stageSel = document.getElementById('newStageSelect');
    const statusSel = document.getElementById('newStatusSelect');
    const label = sel => sel.value ? sel.selectedOptions[0].text.trim() : 'بدون تغيير';

    document.getElementById('sumCount').textContent = count.toLocaleString('en');
    document.getElementById('sumStage').textContent = label(stageSel);
    document.getElementById('sumStatus').textContent = label(statusSel);
    document.getElementById('applyBtn').disabled = count === 0 || (!stageSel.value && !statusSel.value);
}

function applyUpdate() {
    const stageSel  = document.getElementById('newStageSelect');
    const statusSel = document.getElementById('newStatusSelect');
    const stageId   = stageSel.value;
    const statusId  = statusSel.value;
    const ids       = Object.keys(selectedClients).map(Number);
    if (!stageId && !statusId) { Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'اختر الوضع الجديد أو الحالة الجديدة (أو كليهما).', confirmButtonText: 'موافق' }); return; }
    if (!ids.length) { Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'القائمة فارغة.', confirmButtonText: 'موافق' }); return; }

    const parts = [];
    if (stageId)  parts.push('الوضع ← «' + esc(stageSel.selectedOptions[0].text.trim()) + '»');
    if (statusId) parts.push('الحالة ← «' + esc(statusSel.selectedOptions[0].text.trim()) + '»');

    Swal.fire({
        icon: 'question',
        title: 'تأكيد التحديث الجماعي',
        html: 'سيتم تحديث <b>' + ids.length + '</b> عميل:<br>' + parts.join('<br>'),
        showCancelButton: true,
        confirmButtonText: 'نعم، طبّق',
        cancelButtonText: 'إلغاء',
    }).then(res => { if (res.isConfirmed) sendUpdate(ids, stageId, statusId); });
}

function sendUpdate(ids, stageId, statusId) {
    const btn = document.getElementById('applyBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جارٍ التحديث...';

    const reset = () => { btn.innerHTML = '<i class="fas fa-check me-1"></i> تطبيق التحديث'; renderSummary(); };

    fetch(BULK_UPDATE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(Object.assign(
            { client_ids: ids, status_id: statusId || null },
            stageId.startsWith('from-status:')
                ? { registration_stage_from_status_id: stageId.slice('from-status:'.length) }
                : { registration_stage_id: stageId || null }
        )),
    })
        .then(r => r.json())
        .then(d => {
            reset();
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'تم', text: d.message, confirmButtonText: 'موافق' })
                    // الأرقامُ في الترويسة وقوائمُ التصفية تتغيّر بعد التحديث — التحميلُ يعيدها صحيحة
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'خطأ', text: d.message, confirmButtonText: 'موافق' });
            }
        })
        .catch(() => {
            reset();
            Swal.fire({ icon: 'error', title: 'خطأ', text: 'حدث خطأ غير متوقع.', confirmButtonText: 'موافق' });
        });
}
</script>
@endsection
