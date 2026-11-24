{{--
    لوحة تحكّم العملاء — بنمط تصميم لوحة المبيعات (متغيّرات CSS من الثيم،
    ونمط ApexCharts نفسه: palette/base/render).

    **كلُّ رقمٍ يفتح عملاءَه.** البطاقاتُ وأجزاءُ المخطَّطات وصفوفُ الجداول
    تحمل `data-segment`؛ النقرُ يطلب `clients.dashboard.segment` بفلاتر اللوحة
    نفسِها فيعرض القائمةَ في لوحةٍ جانبية، وكلُّ عميلٍ فيها رابطٌ إلى صفحته.
    والقائمةُ تُبنى في `App\Services\ClientDashboardService` من الخريطة التي
    عُدّ منها الرقم — فطولُها يساوي الرقمَ المعروض.
--}}
@extends('client::master')

@section('title')
لوحة تحكم العملاء
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.css">
<style>
    #client-dashboard {
        --cd-surface: var(--app-surface, #fff);
        --cd-border: var(--app-border, #e6e9f0);
        --cd-text: var(--app-text, #4b4b4b);
        --cd-muted: var(--app-muted, #7b8194);
        --cd-radius: var(--app-radius, 8px);
        --cd-primary: var(--app-primary, #7367f0);
        --cd-success: var(--app-success, #28c76f);
        --cd-danger: var(--app-danger, #ea5455);
        --cd-warning: var(--app-warning, #ff9f43);
        --cd-info: var(--app-info, #00cfe8);
        font-family: var(--app-font-family, 'Cairo', sans-serif);
    }

    #client-dashboard .cd-toolbar {
        background: var(--cd-surface);
        border: 1px solid var(--cd-border);
        border-radius: calc(var(--cd-radius) + 4px);
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
    }

    #client-dashboard .cd-title {
        font-weight: 700; color: var(--cd-text); margin: 0 0 .75rem; font-size: 1.1rem;
        display: flex; align-items: center; gap: .5rem;
    }

    #client-dashboard .cd-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .85rem; }

    #client-dashboard .cd-tab {
        border: 1px solid var(--cd-border); background: transparent; color: var(--cd-muted);
        border-radius: var(--cd-radius); padding: .42rem .95rem; font-size: .85rem; font-weight: 600; cursor: pointer;
    }

    #client-dashboard .cd-tab.active { background: var(--cd-primary); border-color: var(--cd-primary); color: #fff; }

    /* شارةُ نوع العنصر — بنفس ألوان شجرة الفروع */
    .cd-node-type {
        display: inline-block; margin-inline-start: .25rem; padding: 0 .35rem; border-radius: .25rem;
        font-size: .68rem; line-height: 1.7; background: #eef0f4; color: #4b5563; vertical-align: middle;
    }

    #client-dashboard .cd-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin: -.4rem 0 .85rem; font-size: .78rem; }
    #client-dashboard .cd-crumbs a { color: var(--cd-primary); text-decoration: none; }
    #client-dashboard .cd-crumbs a:hover { text-decoration: underline; }
    #client-dashboard .cd-crumbs strong { color: var(--cd-text); }
    #client-dashboard .cd-crumb-sep { color: var(--cd-muted); opacity: .6; }

    #client-dashboard .cd-filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
    #client-dashboard .cd-field { display: flex; flex-direction: column; gap: .25rem; }
    #client-dashboard .cd-field > label { font-size: .74rem; color: var(--cd-muted); margin: 0; font-weight: 600; }

    #client-dashboard .cd-field .form-control,
    #client-dashboard .cd-field .custom-select,
    #client-dashboard .cd-field select {
        height: var(--app-input-height, 38px); font-size: .84rem; border-radius: var(--cd-radius);
        border: 1px solid var(--cd-border); background: var(--cd-surface); color: var(--cd-text);
        padding: .25rem .6rem; min-width: 165px;
    }
    #client-dashboard .cd-field input.cd-days-input { min-width: 0; width: 120px; }

    #client-dashboard .cd-switch {
        display: inline-flex; align-items: center; gap: .4rem;
        height: var(--app-input-height, 38px); padding: 0 .75rem;
        border: 1px solid var(--cd-border); border-radius: var(--cd-radius);
        background: var(--cd-surface); font-size: .82rem; font-weight: 600;
        color: var(--cd-muted); cursor: pointer; margin: 0; white-space: nowrap;
    }
    #client-dashboard .cd-switch input { accent-color: var(--cd-primary); width: 15px; height: 15px; cursor: pointer; }
    #client-dashboard .cd-scope-count { font-style: normal; font-size: .72rem; font-weight: 700; color: var(--cd-primary); }

    #client-dashboard .cd-apply {
        height: var(--app-input-height, 38px); border-radius: var(--app-button-radius, 6px); border: none;
        background: var(--cd-primary); color: #fff; font-size: .84rem; font-weight: 600; padding: 0 1.1rem; cursor: pointer;
    }

    #client-dashboard .cd-reset {
        height: var(--app-input-height, 38px); display: inline-flex; align-items: center; gap: .3rem;
        border-radius: var(--app-button-radius, 6px); border: 1px solid var(--cd-border);
        background: var(--cd-surface); color: var(--cd-muted); font-size: .84rem; padding: 0 .9rem;
        cursor: pointer; text-decoration: none;
    }

    #client-dashboard .cd-note-text { margin-top: .25rem; font-size: .8rem; line-height: 1.6; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    #client-dashboard .cd-range-note { margin-top: .7rem; font-size: .76rem; color: var(--cd-muted); }

    /* ---------- العناصر القابلة للنقر ---------- */
    #client-dashboard [data-segment] { cursor: pointer; }
    #client-dashboard [data-segment]:focus-visible { outline: 2px solid var(--cd-primary); outline-offset: 2px; }

    /* ---------- المؤشرات ---------- */
    #client-dashboard .cd-kpi {
        background: var(--cd-surface); border: 1px solid var(--cd-border);
        border-radius: calc(var(--cd-radius) + 4px); padding: var(--app-card-padding, 1rem);
        height: 100%; transition: transform .18s ease, border-color .18s ease; position: relative;
    }
    #client-dashboard .cd-kpi[data-segment]:hover { transform: translateY(-3px); border-color: var(--cd-primary); }
    #client-dashboard .cd-kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; }
    #client-dashboard .cd-kpi-icon { width: 40px; height: 40px; border-radius: var(--cd-radius); display: flex; align-items: center; justify-content: center; }
    #client-dashboard .cd-kpi-open { font-size: .7rem; color: var(--cd-muted); opacity: 0; transition: opacity .15s; }
    #client-dashboard .cd-kpi[data-segment]:hover .cd-kpi-open { opacity: 1; color: var(--cd-primary); }
    #client-dashboard .cd-kpi-label { color: var(--cd-muted); font-size: .78rem; font-weight: 600; margin: 0 0 .25rem; }
    #client-dashboard .cd-kpi-value { font-size: 1.3rem; font-weight: 700; color: var(--cd-text); margin: 0; direction: ltr; text-align: start; }
    #client-dashboard .cd-kpi-foot { margin-top: .4rem; font-size: .74rem; color: var(--cd-muted); line-height: 1.5; }

    /* ---------- البطاقات ---------- */
    #client-dashboard .cd-card {
        background: var(--cd-surface); border: 1px solid var(--cd-border);
        border-radius: calc(var(--cd-radius) + 4px); margin-bottom: 1.25rem;
        height: calc(100% - 1.25rem); display: flex; flex-direction: column;
    }
    #client-dashboard .cd-card-head { padding: 1rem 1.15rem .4rem; display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
    #client-dashboard .cd-card-head h4 { font-size: 1rem; font-weight: 700; color: var(--cd-text); margin: 0; }
    #client-dashboard .cd-card-head small { color: var(--cd-muted); font-size: .76rem; }
    #client-dashboard .cd-card-body { padding: .4rem 1.15rem 1.1rem; flex: 1; }
    #client-dashboard .cd-card-tip { font-size: .72rem; color: var(--cd-muted); margin: -.1rem 0 .4rem; }

    #client-dashboard .cd-link-btn {
        background: none; border: none; padding: 0; color: var(--cd-primary); font-size: .78rem; font-weight: 600; cursor: pointer;
    }

    #client-dashboard .cd-info-toggle {
        background: none; border: none; padding: .2rem .35rem; line-height: 1;
        border-radius: 5px; color: var(--cd-muted); cursor: pointer; flex-shrink: 0;
    }
    #client-dashboard .cd-info-toggle:hover { color: var(--cd-primary); background: color-mix(in srgb, var(--cd-primary) 10%, transparent); }
    #client-dashboard .cd-info-panel {
        background: color-mix(in srgb, var(--cd-primary) 6%, transparent); border: 1px solid var(--cd-border);
        border-radius: var(--cd-radius); padding: .6rem .8rem; margin-bottom: .7rem;
        font-size: .78rem; line-height: 1.7; color: var(--cd-muted);
    }
    #client-dashboard .cd-info-panel code {
        background: color-mix(in srgb, var(--cd-text) 8%, transparent); padding: .05rem .3rem;
        border-radius: 4px; font-size: .74rem; direction: ltr; display: inline-block;
    }

    /* قائمةُ الشرائح تحت كل مخطَّط — الرقمُ والتعريفُ ومفتاحُ القائمة */
    #client-dashboard .cd-seg-list { list-style: none; margin: .5rem 0 0; padding: 0; }
    #client-dashboard .cd-subhead { margin: 1rem 0 0; padding-top: .8rem; border-top: 1px dashed var(--cd-border); font-size: .88rem; font-weight: 700; color: var(--cd-text); display: flex; justify-content: space-between; align-items: baseline; }
    #client-dashboard .cd-subhead small { font-weight: 600; color: var(--cd-muted); font-size: .72rem; }
    #client-dashboard .cd-seg-list li {
        display: flex; align-items: center; gap: .5rem; padding: .38rem .45rem;
        border-radius: var(--cd-radius); font-size: .8rem; color: var(--cd-text);
    }
    #client-dashboard .cd-seg-list li:hover { background: color-mix(in srgb, var(--cd-primary) 7%, transparent); }
    #client-dashboard .cd-seg-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    #client-dashboard .cd-seg-label { flex: 1; min-width: 0; }
    #client-dashboard .cd-seg-label small { display: block; color: var(--cd-muted); font-size: .7rem; line-height: 1.4; }
    #client-dashboard .cd-seg-val { font-weight: 700; direction: ltr; white-space: nowrap; font-variant-numeric: tabular-nums; }
    #client-dashboard .cd-seg-pct { color: var(--cd-muted); font-size: .72rem; min-width: 44px; text-align: end; direction: ltr; }

    #client-dashboard .cd-table { width: 100%; font-size: .84rem; margin: 0; color: var(--cd-text); }
    #client-dashboard .cd-table thead th {
        background: color-mix(in srgb, var(--cd-primary) 5%, transparent);
        color: var(--cd-muted); font-weight: 700; font-size: .76rem; border: none;
        padding: .6rem .55rem; white-space: nowrap;
    }
    #client-dashboard .cd-table tbody td { padding: .55rem .55rem; border-top: 1px solid var(--cd-border); vertical-align: middle; }
    #client-dashboard .cd-table tbody tr[data-segment]:hover td { background: color-mix(in srgb, var(--cd-primary) 6%, transparent); }
    #client-dashboard .cd-table a { color: var(--cd-primary); font-weight: 600; text-decoration: none; }
    #client-dashboard .cd-table a:hover { text-decoration: underline; }
    #client-dashboard .cd-num { direction: ltr; text-align: start; font-variant-numeric: tabular-nums; white-space: nowrap; }
    #client-dashboard .cd-long { min-width: 200px; max-width: 360px; white-space: normal; line-height: 1.6; }

    #client-dashboard .cd-empty { text-align: center; color: var(--cd-muted); padding: 2rem 1rem; font-size: .86rem; }
    #client-dashboard .cd-empty i { display: block; font-size: 1.8rem; margin-bottom: .5rem; opacity: .45; }

    #client-dashboard .cd-badge { display: inline-block; padding: .16rem .5rem; border-radius: 6px; font-size: .72rem; font-weight: 700; }

    /* مركز التنبيهات */
    #client-dashboard .cd-notif-list { display: flex; flex-direction: column; gap: .5rem; }
    #client-dashboard .cd-notif-item {
        display: flex; align-items: center; gap: .65rem; padding: .6rem .7rem;
        border-radius: var(--cd-radius); border: 1px solid var(--cd-border);
    }
    #client-dashboard a.cd-notif-item--link,
    #client-dashboard .cd-notif-item[data-segment] { text-decoration: none; transition: background-color .15s, border-color .15s; }
    #client-dashboard a.cd-notif-item--link:hover,
    #client-dashboard .cd-notif-item[data-segment]:hover { background: color-mix(in srgb, var(--cd-primary) 6%, transparent); border-color: var(--cd-primary); }
    #client-dashboard .cd-notif-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    #client-dashboard .cd-notif-label { flex: 1; font-size: .84rem; color: var(--cd-text); }
    #client-dashboard .cd-notif-count { font-weight: 700; font-size: .95rem; direction: ltr; }

    #client-dashboard .cd-map { height: 420px; border-radius: var(--cd-radius); overflow: hidden; }
    #client-dashboard .cd-map-head-actions { display: flex; align-items: center; gap: .6rem; }
    #client-dashboard .cd-map-toggle {
        border: 1px solid var(--cd-border); background: transparent; color: var(--cd-text);
        border-radius: var(--cd-radius); padding: .22rem .7rem; font-size: .78rem; font-weight: 600;
        display: inline-flex; align-items: center; gap: .3rem; line-height: 1.6;
    }
    #client-dashboard .cd-map-toggle:hover { border-color: var(--cd-primary); color: var(--cd-primary); }

    #client-dashboard .cd-mini-stat { text-align: center; padding: .5rem; border-radius: var(--cd-radius); }
    #client-dashboard .cd-mini-stat[data-segment]:hover { background: color-mix(in srgb, var(--cd-primary) 7%, transparent); }
    #client-dashboard .cd-mini-stat .val { font-size: 1.15rem; font-weight: 700; color: var(--cd-text); direction: ltr; }
    #client-dashboard .cd-mini-stat .lbl { font-size: .74rem; color: var(--cd-muted); }

    /* ---------- اللوحة الجانبية: عملاءُ الشريحة ---------- */
    #client-dashboard .cd-drawer-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); z-index: 1990; }
    #client-dashboard .cd-drawer {
        position: fixed; top: 0; bottom: 0; inset-inline-end: 0; width: min(1080px, 96vw);
        background: var(--cd-surface); z-index: 1991; display: flex; flex-direction: column;
        box-shadow: 0 0 40px rgba(15, 23, 42, .25);
    }
    #client-dashboard .cd-drawer-head {
        display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
        padding: 1rem 1.25rem .6rem; border-bottom: 1px solid var(--cd-border);
    }
    #client-dashboard .cd-drawer-head h5 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--cd-text); }
    #client-dashboard .cd-drawer-head p { margin: .25rem 0 0; font-size: .8rem; color: var(--cd-muted); line-height: 1.6; }
    #client-dashboard .cd-drawer-close {
        border: 1px solid var(--cd-border); background: transparent; color: var(--cd-muted);
        width: 34px; height: 34px; border-radius: 50%; font-size: 1.2rem; line-height: 1; cursor: pointer; flex-shrink: 0;
    }
    #client-dashboard .cd-drawer-tools {
        display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: .7rem 1.25rem;
        border-bottom: 1px solid var(--cd-border);
    }
    #client-dashboard .cd-drawer-tools input[type="search"] {
        flex: 1; min-width: 200px; height: 38px; border: 1px solid var(--cd-border);
        border-radius: var(--cd-radius); padding: .25rem .7rem; font-size: .85rem; background: var(--cd-surface); color: var(--cd-text);
    }
    #client-dashboard .cd-drawer-count { font-size: .82rem; font-weight: 700; color: var(--cd-primary); white-space: nowrap; }
    #client-dashboard .cd-drawer-body { flex: 1; overflow: auto; padding: 0 1.25rem 1rem; }
    #client-dashboard .cd-drawer-body .cd-table thead th { position: sticky; top: 0; background: var(--cd-surface); box-shadow: inset 0 -1px 0 var(--cd-border); z-index: 1; }
    #client-dashboard .cd-drawer-more { display: block; margin: .8rem auto 0; }
</style>
@endpush

@section('content')
<div class="content-body">
    {{-- أسطر الشرح تحت الشرائح والبطاقات ← علامة «!» بجوار عناوينها --}}
    @include('components.hint-tips')

    <section id="client-dashboard">

        {{-- ==================== ابدأ من هنا: دليل قسم العملاء ==================== --}}
        @include('client::dashboard.client._start-guide')

        {{-- ==================== الفلترة ==================== --}}
        <div class="cd-toolbar">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <h4 class="cd-title"><i class="feather icon-bar-chart-2"></i> لوحة تحكّم العملاء</h4>
                <a href="{{ route('clients.index') }}" class="cd-apply" style="margin: 0;" title="الذهاب إلى إدارة العملاء">
                    <i class="feather icon-users"></i> إدارة العملاء
                </a>
            </div>

            @if ($branchLineage->isNotEmpty())
                <nav class="cd-crumbs">
                    @if ($isMainBranch)
                        <a href="{{ request()->fullUrlWithQuery(['branch_id' => null]) }}">— الكل —</a>
                    @endif
                    @foreach ($branchLineage as $node)
                        @if (! $loop->first || $isMainBranch)
                            <span class="cd-crumb-sep">/</span>
                        @endif
                        @if ($loop->last)
                            <strong>{{ $node->name }} <small class="text-muted">{{ $node->displayTypeLabel() }}</small></strong>
                        @else
                            <a href="{{ request()->fullUrlWithQuery(['branch_id' => $node->id]) }}">{{ $node->name }}</a>
                        @endif
                    @endforeach
                </nav>
            @endif

            <div class="cd-tabs">
                @foreach (['1' => 'يوم', '7' => 'أسبوع', '30' => 'شهر', '90' => '3 أشهر', '180' => '6 أشهر', '365' => 'سنة', 'all' => 'الشهر الجاري', 'custom' => 'مخصص'] as $key => $label)
                    <button type="button" class="cd-tab {{ request('period', 'all') == $key ? 'active' : '' }}" data-period="{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>

            <form method="get" action="{{ url()->current() }}" class="cd-filters" id="cdFilterForm">
                <input type="hidden" name="period" id="cdPeriod" value="{{ request('period', 'all') }}">

                <div class="cd-field" id="cdCustomDates" style="{{ request('period') == 'custom' ? '' : 'display:none;' }}">
                    <label>من</label>
                    <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}">
                </div>
                <div class="cd-field" style="{{ request('period') == 'custom' ? '' : 'display:none;' }}" id="cdCustomDatesTo">
                    <label>إلى</label>
                    <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}">
                </div>

                @if ($hasChildBranches)
                    <div class="cd-field">
                        <label for="cdBranch">{{ $selectedBranchId ? 'انزل إلى ما يتبعه' : 'تابع لـ' }}</label>
                        <select name="branch_id" id="cdBranch" class="custom-select">
                            <option value="">
                                {{ $selectedBranchId ? '— رجوع لهذا كاملاً —' : ($isMainBranch ? '— كل العملاء —' : '— كل عملائي —') }}
                            </option>
                            @foreach ($branchOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }} — {{ $option->displayTypeLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($selectedBranchId)
                    <div class="cd-field">
                        <label>النطاق</label>
                        <label class="cd-switch" title="تضمين كل ما يتبع له مهما عمق">
                            <input type="hidden" name="include_children" value="0">
                            <input type="checkbox" name="include_children" value="1" id="cdIncludeChildren" {{ $includeChildren ? 'checked' : '' }}>
                            <span>يشمل ما يتبعه</span>
                            @if ($includeChildren && $descendantCount > 0)
                                <em class="cd-scope-count">+{{ $descendantCount }}</em>
                            @endif
                        </label>
                    </div>
                @endif

                <div class="cd-field">
                    <label for="cdCategory">التصنيف</label>
                    <select name="category" id="cdCategory" class="custom-select">
                        <option value="">— الكل —</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) request('category') === (string) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cd-field">
                    <label for="cdStatus">الحالة</label>
                    <select name="status" id="cdStatus" class="custom-select">
                        <option value="">— الكل —</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st->id }}" @selected((string) request('status') === (string) $st->id)>{{ $st->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cd-field">
                    <label for="cdEmployee">الموظف</label>
                    <select name="employee" id="cdEmployee" class="custom-select select2">
                        <option value="">— الكل —</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}" @selected((string) request('employee') === (string) $emp->id)>{{ $emp->full_name ?? $emp->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cd-field">
                    <label for="cdVisitGap" title="الافتراضي من إعدادات وحدات العملاء ← المتابعة">بحاجة زيارة بعد (يوم)</label>
                    <input type="number" min="1" max="3650" class="form-control cd-days-input" name="visit_gap" id="cdVisitGap"
                        value="{{ request('visit_gap') }}" placeholder="{{ $defaultVisitGap }}">
                </div>

                <div class="cd-field">
                    <label for="cdOverdueDays" title="الافتراضي من إعدادات وحدات العملاء ← المتابعة">متأخّر بعد (يوم)</label>
                    <input type="number" min="0" max="3650" class="form-control cd-days-input" name="overdue_days" id="cdOverdueDays"
                        value="{{ request('overdue_days') }}" placeholder="{{ $defaultOverdueDays }}">
                </div>

                <button type="submit" class="cd-apply"><i class="feather icon-filter"></i> تصفية</button>
                <a href="{{ url()->current() }}" class="cd-reset"><i class="feather icon-refresh-cw"></i> تحديث</a>
            </form>

            <div class="cd-range-note">
                <i class="feather icon-info"></i>
                الفترة تحدّد مدى المبيعات والتحصيل والزيارات والعملاء الجدد: <b>{{ $rangeLabel }}</b> — ولا تُنقص عدد العملاء.
                اضغط على أي رقم أو جزء من مخطَّط لعرض عملائه.
            </div>
        </div>

        {{-- ==================== المؤشرات: صفّان من 6 ==================== --}}
        @php
            $netSalesForRate = $ledgerMovement['sales'] - $ledgerMovement['returns'];
            $collectionRate = $netSalesForRate > 0 ? round(($ledgerMovement['collections'] / $netSalesForRate) * 100, 1) : 0;
            $totalDebtForKpi = $debtAging['total_debt'] ?? 0;
            $pct = fn ($part, $whole) => $whole > 0 ? round(($part / $whole) * 100, 1) : 0;

            $kpiRow1 = [
                ['label' => 'إجمالي العملاء', 'value' => number_format($totalClients), 'icon' => 'users', 'color' => 'var(--cd-primary)', 'segment' => 'kpi.total',
                    'foot' => 'ضمن الفلاتر المختارة'],
                ['label' => 'العملاء النشطون', 'value' => number_format($activeClients), 'icon' => 'user-check', 'color' => 'var(--cd-success)', 'segment' => 'kpi.active',
                    'foot' => $pct($activeClients, $totalClients).'% من الإجمالي — حالتهم «نشط»'],
                ['label' => 'العملاء الموقوفون', 'value' => number_format($stoppedClients), 'icon' => 'user-x', 'color' => 'var(--cd-danger)', 'segment' => 'kpi.stopped',
                    'foot' => $pct($stoppedClients, $totalClients).'% من الإجمالي'.($clientsWithoutStatus > 0 ? ' · بدون حالة: '.number_format($clientsWithoutStatus) : '')],
                ['label' => $hasPeriod ? 'عملاء جدد (الفترة)' : 'عملاء جدد (هذا الشهر)', 'value' => number_format($newClients), 'icon' => 'user-plus', 'color' => 'var(--cd-info)', 'segment' => 'kpi.new',
                    'foot' => $growthRate === null ? 'أُضيفوا خلال الفترة' : (($growthRate >= 0 ? '▲ ' : '▼ ').abs($growthRate).'% عن الشهر الماضي')],
                ['label' => 'بحاجة زيارة', 'value' => number_format($clientsNeedingVisit), 'icon' => 'map-pin', 'color' => 'var(--cd-warning)', 'segment' => 'kpi.needs_visit',
                    'foot' => 'آخر زيارة قبل '.$visitGap.' يوماً فأكثر أو لم يُزَر (دون الموقوفين)'],
                ['label' => 'متأخرون', 'value' => number_format($overdueClients), 'icon' => 'alert-triangle', 'color' => 'var(--cd-danger)', 'segment' => 'kpi.overdue',
                    'foot' => 'عليهم دين تجاوز استحقاقه '.$overdueDays.' يوماً'],
            ];

            $kpiRow2 = [
                ['label' => 'إجمالي المبيعات', 'value' => \App\Support\CurrencySettings::format($ledgerMovement['sales']), 'icon' => 'trending-up', 'color' => 'var(--cd-primary)', 'segment' => 'movement.sales',
                    'foot' => 'فواتير مرحَّلة في الفترة'],
                ['label' => 'المرتجعات والإشعارات', 'value' => \App\Support\CurrencySettings::format($ledgerMovement['returns']), 'icon' => 'corner-up-left', 'color' => 'var(--cd-danger)', 'segment' => 'movement.returns',
                    'foot' => $ledgerMovement['sales'] > 0 ? $pct($ledgerMovement['returns'], $ledgerMovement['sales']).'% من المبيعات' : 'مرتجع مبيعات وإشعار دائن'],
                ['label' => 'إجمالي التحصيل', 'value' => \App\Support\CurrencySettings::format($ledgerMovement['collections']), 'icon' => 'credit-card', 'color' => 'var(--cd-info)', 'segment' => 'movement.collections',
                    'foot' => 'تغطية '.$collectionRate.'% من صافي المبيعات'],
                ['label' => 'إجمالي المديونية', 'value' => \App\Support\CurrencySettings::format($totalDebtForKpi), 'icon' => 'dollar-sign', 'color' => 'var(--cd-warning)', 'segment' => 'kpi.debtors',
                    'foot' => number_format($debtAging['debtors'] ?? 0).' عميل مدين — الرصيد اليوم'],
                ['label' => 'المديونية المتأخرة', 'value' => \App\Support\CurrencySettings::format($overdueDebt), 'icon' => 'alert-circle', 'color' => 'var(--cd-danger)', 'segment' => 'kpi.overdue',
                    'foot' => $pct($overdueDebt, $totalDebtForKpi).'% من المديونية — تجاوزت '.$overdueDays.' يوماً'],
                // منفصلةٌ عن المديونية: مبالغُ لصالح العملاء لا تُطرح منها
                ['label' => 'أرصدة دائنة للعملاء', 'value' => \App\Support\CurrencySettings::format($creditTotal ?? 0), 'icon' => 'arrow-up-right', 'color' => 'var(--cd-success)', 'segment' => 'kpi.creditors',
                    'foot' => number_format($creditClients ?? 0).' عميل دفع زيادةً أو مقدَّماً — لهم علينا، لا تُطرح من المديونية'],
            ];
        @endphp

        @foreach ([$kpiRow1, $kpiRow2] as $kpiRow)
            <div class="row">
                @foreach ($kpiRow as $kpi)
                    <div class="{{ count($kpiRow) === 6 ? 'col-xl-2' : 'col-xl' }} col-lg-4 col-md-6 col-12 mb-1">
                        <div class="cd-kpi" data-segment="{{ $kpi['segment'] }}" role="button" tabindex="0">
                            <div class="cd-kpi-top">
                                <div class="cd-kpi-icon" style="background: color-mix(in srgb, {{ $kpi['color'] }} 14%, transparent);">
                                    <i class="feather icon-{{ $kpi['icon'] }}" style="color: {{ $kpi['color'] }};"></i>
                                </div>
                                <span class="cd-kpi-open"><i class="feather icon-list"></i> العملاء</span>
                            </div>
                            <p class="cd-kpi-label">{{ $kpi['label'] }}</p>
                            <p class="cd-kpi-value">{{ $kpi['value'] }}</p>
                            <div class="cd-kpi-foot">{{ $kpi['foot'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        {{-- ==================== دورة الحياة / أهداف العملاء / تقادم الديون ==================== --}}
        @php
            $canEditSegments = hasPermission('clients_module_settings_view');
            $segmentCards = [
                [
                    'id' => 'cdLifecycleChart', 'prefix' => 'lifecycle', 'question' => 'متى آخر فاتورة للعميل؟ — لمتابعة الانقطاع عن الشراء. على العملاء النشطين وحدَهم.', 'title' => 'دورة حياة العميل', 'icon' => 'refresh-cw',
                    'rows' => $lifecycleSegments, 'money' => false, 'total' => $activeClients,
                    'info' => 'آخر نشاطٍ للعميل = أحدث فاتورة مبيعاتٍ غير ملغاة (دون المرتجعات) — الزيارات لا تُحسب. ومن لا فاتورة له يقع في «بلا نشاط». الشرائح وحدودها وأسماؤها وألوانها من الإعدادات، وتُفحص بالترتيب: يدخل العميل أول شريحة تنطبق عليه، فالمجموع = إجمالي العملاء النشطين — الموقوف وأي حالةٍ أخرى لا يدخل هذه البطاقة.',
                ],
                $customerTargetsEnabled ? [
                    'id' => 'cdTargetsChart', 'prefix' => 'target', 'title' => 'أهداف العملاء', 'icon' => 'award',
                    'question' => 'في أيّ هدفٍ يقع كل عميل؟ — الأهداف وشروطها وألوانها من إعدادات «أهداف العملاء». على العملاء النشطين وحدَهم.',
                    'rows' => $customerTargets['rows'], 'money' => false, 'total' => $activeClients,
                    'settings_url' => canAny(['clients.reports.targets', 'clients_edit_client_settings']) ? route('client.customer_targets.index') : null,
                    'settings_label' => 'إعدادات أهداف العملاء ←',
                    'info' => 'يُصنَّف العميل تلقائياً كل يوم بمهمة «التقييم الديناميكي للعملاء»: يدخل أعلى هدفٍ نشط (بالأولوية) تنطبق عليه شروطه — معدل السداد الشهري، والتحصيلات، ومتوسط أيام التحصيل. اللوحة تعرض آخر تقييم ولا تعيد الحساب. «غير مقيَّم» = لم يطابق أيَّ هدف أو لم يُقيَّم بعد. المجموع = إجمالي العملاء النشطين وحدَهم.',
                ] : null,
                [
                    'id' => 'cdAgingChart', 'prefix' => 'aging', 'question' => 'كم عمر الديون غير المسدَّدة؟', 'title' => 'تقادم المديونية', 'icon' => 'clock',
                    'rows' => $debtAging['buckets'], 'money' => true, 'total' => $totalDebtForKpi,
                    'info' => 'من دفتر الأستاذ (نفس تقرير أعمار ديون العملاء): التحصيلات والمرتجعات تُطفئ أقدم المستحقّات أوّلاً (FIFO)، ثم يُوزَّع ما بقي على الشرائح بعمره الحقيقي من تاريخ استحقاقه (تاريخ الحركة + مدّة ائتمان العميل). مجموع الشرائح = إجمالي المديونية، والعميل قد يظهر في أكثر من شريحة. حدود الشرائح وأسماؤها وألوانها من الإعدادات.',
                ],
            ];
        @endphp

        <div class="row">
            @foreach (array_filter($segmentCards) as $card)
                <div class="col-lg-4 col-12">
                    <div class="cd-card">
                        <div class="cd-card-head">
                            <h4><i class="feather icon-{{ $card['icon'] }} text-primary me-1"></i> {{ $card['title'] }}</h4>
                            <button type="button" class="cd-info-toggle" data-cd-info-toggle title="كيف يُحسب؟" aria-label="كيف يُحسب؟"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="cd-card-body">
                            <p class="cd-card-tip">{{ $card['question'] }}@if (! empty($card['settings_url'])) <a href="{{ $card['settings_url'] }}">{{ $card['settings_label'] }}</a>@elseif ($canEditSegments && empty($card['settings_label'])) <a href="{{ route('clients.module_settings', ['tab' => 'follow_up']) }}">تحديد الأيام والشرائح ←</a>@endif</p>
                            <div class="cd-info-panel" hidden>{{ $card['info'] }}@if ($canEditSegments) <a href="{{ route('clients.module_settings', ['tab' => 'follow_up']) }}">تعديل الشرائح ←</a>@endif</div>
                            <div id="{{ $card['id'] }}"></div>
                            <ul class="cd-seg-list">
                                @foreach ($card['rows'] as $row)
                                    @php $value = $card['money'] ? $row['total'] : $row['count']; @endphp
                                    <li data-segment="{{ $card['prefix'] }}.{{ $row['key'] }}" role="button" tabindex="0">
                                        <span class="cd-seg-dot" style="background: {{ $row['color'] }};"></span>
                                        <span class="cd-seg-label">
                                            {{ $row['label'] }}
                                            <small>{{ $row['hint'] }}@if ($card['money']) · {{ number_format($row['clients']) }} عميل @endif</small>
                                        </span>
                                        <span class="cd-seg-val">{{ $card['money'] ? \App\Support\CurrencySettings::format($value) : number_format($value) }}</span>
                                        <span class="cd-seg-pct">{{ $pct($value, $card['total']) }}%</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($card['prefix'] === 'target')
                                <p class="cd-card-tip mt-1">
                                    آخر تقييم: {{ $customerTargets['evaluated_at'] ? substr($customerTargets['evaluated_at'], 0, 10) : 'لم يُقيَّم بعد' }}
                                    @if ($customerTargets['downgraded'] > 0)
                                        · <a href="#" data-segment="target.downgraded">تراجع هدف {{ number_format($customerTargets['downgraded']) }} عميل</a>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ==================== مركز التنبيهات / نتائج الزيارات / هذا الشهر ==================== --}}
        <div class="row">
            <div class="col-lg-4 col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-bell text-warning me-1"></i> مركز التنبيهات</h4></div>
                    <div class="cd-card-body">
                        @if (empty($dashboardNotifications))
                            <div class="cd-empty"><i class="feather icon-check-circle"></i> لا توجد بنود معلَّقة تحتاج مراجعة.</div>
                        @else
                            <div class="cd-notif-list">
                                @foreach ($dashboardNotifications as $notif)
                                    @php $notifHref = $notif['route'] ? route($notif['route'], $notif['params'] ?? []) : null; @endphp
                                    @if ($notifHref)
                                        <a href="{{ $notifHref }}" class="cd-notif-item cd-notif-item--link">
                                            @include('client::dashboard.client._notif-item-body', ['notif' => $notif])
                                        </a>
                                    @elseif (! empty($notif['segment']))
                                        <div class="cd-notif-item" data-segment="{{ $notif['segment'] }}" role="button" tabindex="0">
                                            @include('client::dashboard.client._notif-item-body', ['notif' => $notif])
                                        </div>
                                    @else
                                        <div class="cd-notif-item">
                                            @include('client::dashboard.client._notif-item-body', ['notif' => $notif])
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5 col-12">
                <div class="cd-card">
                    <div class="cd-card-head">
                        <h4><i class="feather icon-map-pin text-primary me-1"></i> نتائج الزيارات</h4>
                        <small>{{ $rangeLabel }}</small>
                    </div>
                    <div class="cd-card-body">
                        @php $visitsTotal = array_sum(array_column($visitStates['rows'], 'count')); @endphp
                        <p class="cd-card-tip">زيارات خطّ السير في المدى، دون الملغاة. اضغط أي حالة لعرض عملائها.</p>
                        <div id="cdVisitsOutcomeChart"></div>
                        <ul class="cd-seg-list">
                            @foreach ($visitStates['rows'] as $row)
                                <li data-segment="visit_state.{{ $row['key'] }}" role="button" tabindex="0">
                                    <span class="cd-seg-dot" style="background: {{ $row['color'] }};"></span>
                                    <span class="cd-seg-label">
                                        {{ $row['label'] }}
                                        <small>{{ $row['hint'] }}@if ($row['key'] === 'missed' && $visitStates['justified'] > 0) — منها {{ number_format($visitStates['justified']) }} بتبريرٍ معتمد@endif</small>
                                    </span>
                                    <span class="cd-seg-val">{{ number_format($row['count']) }}</span>
                                    <span class="cd-seg-pct">{{ $pct($row['count'], $visitsTotal) }}%</span>
                                </li>
                            @endforeach
                        </ul>

                        {{-- نتيجة الزيارات المكتملة أعلاه — ناجحة + سلبية = مكتملة دائماً (`visitOutcomeCounts()`) --}}
                        @php
                            $outcomeTotal = $visitOutcomes['success'] + $visitOutcomes['negative'];
                            $outcomeMeta = \App\Services\ClientDashboardService::VISIT_OUTCOMES;
                        @endphp
                        <p class="cd-card-tip mt-1 mb-50">نتيجة الزيارات المكتملة ({{ number_format($outcomeTotal) }}) — فاتورة أو سند قبض للعميل في يوم الزيارة ⇐ ناجحة، وإلّا سلبية.</p>
                        <ul class="cd-seg-list">
                            <li data-segment="visit_result.success" role="button" tabindex="0">
                                <span class="cd-seg-dot" style="background: {{ $outcomeMeta['success']['color'] }};"></span>
                                <span class="cd-seg-label">ناجحة <small>مكتملة وفيها فاتورة أو سند قبض</small></span>
                                <span class="cd-seg-val">{{ number_format($visitOutcomes['success']) }}</span>
                                <span class="cd-seg-pct">{{ $pct($visitOutcomes['success'], $outcomeTotal) }}%</span>
                            </li>
                            <li data-segment="visit_result.negative" role="button" tabindex="0">
                                <span class="cd-seg-dot" style="background: {{ $outcomeMeta['failed']['color'] }};"></span>
                                <span class="cd-seg-label">سلبية <small>مكتملة بلا فاتورة ولا سند قبض @if ($visitOutcomes['return_later'] > 0) — منها {{ number_format($visitOutcomes['return_later']) }} «سأعود لاحقاً» @endif</small></span>
                                <span class="cd-seg-val">{{ number_format($visitOutcomes['negative']) }}</span>
                                <span class="cd-seg-pct">{{ $pct($visitOutcomes['negative'], $outcomeTotal) }}%</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-calendar text-primary me-1"></i> هذا الشهر</h4></div>
                    <div class="cd-card-body">
                        <div class="row">
                            <div class="col-6 cd-mini-stat" data-segment="month.visits" role="button" tabindex="0"><div class="val">{{ number_format($monthVisitResults['total_visits']) }}</div><div class="lbl">زيارات يدوية</div></div>
                            <div class="col-6 cd-mini-stat" data-segment="month.success" role="button" tabindex="0"><div class="val">{{ number_format($monthVisitResults['success_visits']) }}</div><div class="lbl">زيارات ناجحة</div></div>
                            <div class="col-6 cd-mini-stat" data-segment="month.invoices" role="button" tabindex="0"><div class="val">{{ number_format($monthVisitResults['invoices_count']) }}</div><div class="lbl">فواتير</div></div>
                            <div class="col-6 cd-mini-stat" data-segment="month.notes" role="button" tabindex="0"><div class="val">{{ number_format($monthVisitResults['notes_count']) }}</div><div class="lbl">ملاحظات متابعة</div></div>
                        </div>
                        <p class="cd-card-tip mt-1">«ملاحظات متابعة» = ما كتبه الموظفون فقط، دون قيود الفواتير والسندات وسجلّ الإشعارات. «زيارات ناجحة» = يدوية أُنهيت وفيها فاتورة أو تحصيل.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== الخريطة ==================== --}}
        <div class="row">
            <div class="col-12">
                <div class="cd-card">
                    <div class="cd-card-head">
                        <h4><i class="feather icon-map text-primary me-1"></i> خريطة العملاء</h4>
                        <div class="cd-map-head-actions">
                            <small id="cdMapCount"></small>
                            <button type="button" id="cdMapToggle" class="cd-map-toggle" aria-expanded="false" aria-controls="cdMapBody">
                                <i class="feather icon-chevron-down"></i><span>فتح الخريطة</span>
                            </button>
                        </div>
                    </div>
                    <div class="cd-card-body d-none" id="cdMapBody">
                        <div id="cdMap" class="cd-map"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== أعلى المتأخرين / توزيع الحالة ==================== --}}
        <div class="row">
            <div class="col-lg-7 col-12">
                <div class="cd-card">
                    <div class="cd-card-head">
                        <h4><i class="feather icon-alert-circle text-danger me-1"></i> العملاء الأقدم تأخّراً</h4>
                        @if ($overdueClients > 0)
                            <button type="button" class="cd-link-btn" data-segment="kpi.overdue">عرض الكل ({{ number_format($overdueClients) }})</button>
                        @endif
                    </div>
                    <div class="cd-card-body">
                        <p class="cd-card-tip">المبلغ المتأخّر = ما تجاوز استحقاقُه {{ $overdueDays }} يوماً فقط، لا الرصيد كلُّه.</p>
                        @if ($topOverdueDebtors->isEmpty())
                            <div class="cd-empty"><i class="feather icon-check-circle"></i> لا يوجد عملاء متأخرون أكثر من {{ $overdueDays }} يوماً.</div>
                        @else
                            <div class="table-responsive">
                                <table class="cd-table">
                                    <thead><tr><th>العميل</th><th>المبلغ المتأخّر</th><th>أقدم تأخّر</th><th>الرصيد</th></tr></thead>
                                    <tbody>
                                        @foreach ($topOverdueDebtors as $row)
                                            <tr>
                                                <td><a href="{{ route('clients.show', $row['client_id']) }}" target="_blank" rel="noopener">{{ $row['client_name'] }}</a></td>
                                                <td class="cd-num">{{ \App\Support\CurrencySettings::format($row['overdue']) }}</td>
                                                <td><span class="cd-badge" style="background: color-mix(in srgb, var(--cd-danger) 14%, transparent); color: var(--cd-danger);">{{ $row['oldest_days'] }} يوم</span></td>
                                                <td class="cd-num">{{ \App\Support\CurrencySettings::format($row['balance']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5 col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-pie-chart text-primary me-1"></i> توزيع الحالة</h4></div>
                    <div class="cd-card-body">
                        <p class="cd-card-tip">اضغط على أي جزء لعرض عملائه.</p>
                        <div id="cdStatusChart"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== وضع العملاء / نوع النشاط ==================== --}}
        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-flag text-primary me-1"></i> توزيع وضع العملاء</h4></div>
                    <div class="cd-card-body">
                        @if ($clientsByRegistrationStage->isEmpty())
                            <div class="cd-empty"><i class="feather icon-flag"></i> لا يوجد عملاء ضمن هذا النطاق.</div>
                        @else
                            <p class="cd-card-tip">اضغط على أي جزء لعرض عملائه. على العملاء النشطين وحدَهم.</p>
                            <div id="cdRegistrationStageChart"></div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-tag text-primary me-1"></i> نوع النشاط</h4></div>
                    <div class="cd-card-body">
                        <p class="cd-card-tip">اضغط على أي جزء لعرض عملائه. على العملاء النشطين وحدَهم.</p>
                        <div id="cdCategoryChart"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== التوزيع الجغرافي / الفروع / الموظفون ==================== --}}
        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-git-branch text-primary me-1"></i> العملاء حسب ما يتبعون له <small class="text-muted">(شامل ما تحته)</small></h4></div>
                    <div class="cd-card-body">
                        @if ($clientsByBranch->isEmpty())
                            <div class="cd-empty"><i class="feather icon-git-branch"></i> لا توجد بيانات.</div>
                        @else
                            <p class="cd-card-tip">«أخرى» = بلا حالة أو حالة غير النشط والموقوف. اضغط أي رقم لعرض عملائه.</p>
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="cd-table">
                                    <thead><tr><th>تابع لـ</th><th>نشط</th><th>موقوف</th><th>أخرى</th><th>الإجمالي</th></tr></thead>
                                    <tbody>
                                        @foreach ($clientsByBranch as $b)
                                            <tr>
                                                <td data-segment="{{ $b['segment'] }}" role="button" tabindex="0">{{ $b['branch'] }}</td>
                                                @include('client::dashboard.client._status-kind-cells', ['segment' => $b['segment'], 'active' => $b['active'], 'stopped' => $b['stopped'], 'other' => $b['other'], 'total' => $b['total']])
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr style="border-top: 2px solid var(--cd-border);">
                                            <td data-segment="scope.all" role="button" tabindex="0" style="font-weight: 700; padding: .55rem;">إجمالي النطاق</td>
                                            @include('client::dashboard.client._status-kind-cells', ['segment' => 'scope.all', 'active' => $activeClients, 'stopped' => $stoppedClients, 'other' => max(0, $totalClients - $activeClients - $stoppedClients), 'total' => $totalClients])
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-user text-primary me-1"></i> الموظفون وعملاؤهم</h4></div>
                    <div class="cd-card-body">
                        @if (empty($employeeActivity['rows']))
                            <div class="cd-empty"><i class="feather icon-users"></i> لا موظف مسؤول ولا خطّ سير ولا زيارات لعملاء هذا النطاق.</div>
                        @else
                            <p class="cd-card-tip">«مُسنَدون» = الموظف المسؤول في ملف العميل. «في خطّ السير» والزيارات خلال {{ $rangeLabel }}. اضغط أي رقم لعرض تفاصيله.</p>
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="cd-table">
                                    <thead><tr><th>الموظف</th><th>مُسنَدون</th><th>في خطّ السير</th><th>زيارات يدوية</th></tr></thead>
                                    <tbody>
                                        @foreach ($employeeActivity['rows'] as $emp)
                                            <tr>
                                                <td>{{ $emp['name'] }}</td>
                                                <td class="cd-num" data-segment="emp_assigned.{{ $emp['key'] }}" role="button" tabindex="0">{{ number_format($emp['assigned']) }}</td>
                                                <td class="cd-num" data-segment="emp_route.{{ $emp['key'] }}" role="button" tabindex="0">{{ number_format($emp['route']) }}</td>
                                                <td class="cd-num" data-segment="emp_manual.{{ $emp['key'] }}" role="button" tabindex="0">{{ number_format($emp['manual']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if ($employeeActivity['unassigned'] > 0)
                            <p class="cd-card-tip mt-1">
                                <a href="#" data-segment="unassigned.all">{{ number_format($employeeActivity['unassigned']) }} عميلاً بلا موظف مسؤول في ملفه ←</a>
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== المنافسون / الأصول ==================== --}}
        <div class="row">
            <div class="col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-users text-primary me-1"></i> المنافسون المُلاحَظون</h4></div>
                    <div class="cd-card-body">
                        @if ($competitorsSummary['top']->isEmpty())
                            <div class="cd-empty"><i class="feather icon-eye-off"></i> لا توجد ملاحظات منافسين مسجَّلة بعد.</div>
                        @else
                            <div class="table-responsive">
                                <table class="cd-table">
                                    <thead><tr><th>المنافس</th><th>مرات الذكر</th><th>متوسط السعر</th></tr></thead>
                                    <tbody>
                                        @foreach ($competitorsSummary['top'] as $c)
                                            <tr>
                                                <td>{{ $c['name'] }}</td>
                                                <td class="cd-num">{{ number_format($c['mentions']) }}</td>
                                                <td class="cd-num">{{ $c['avg_price'] > 0 ? \App\Support\CurrencySettings::format($c['avg_price']) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($competitorsSummary['new_this_period_count'] > 0)
                                <div class="cd-kpi-foot mt-1">
                                    <span class="cd-badge" style="background: color-mix(in srgb, var(--cd-info) 14%, transparent); color: var(--cd-info);">
                                        {{ $competitorsSummary['new_this_period_count'] }} منافس جديد هذا الشهر
                                    </span>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

        </div>

        {{-- ==================== المقارنة السنوية / آخر الأنشطة ==================== --}}
        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-trending-up text-primary me-1"></i> العملاء المضافون شهرياً</h4><small>هذا العام مقابل العام الماضي</small></div>
                    <div class="cd-card-body"><div id="cdYearlyChart"></div></div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="cd-card">
                    <div class="cd-card-head"><h4><i class="feather icon-activity text-primary me-1"></i> آخر ملاحظات المتابعة</h4></div>
                    <div class="cd-card-body" style="max-height: 340px; overflow-y: auto;">
                        @if ($recentNotes->isEmpty())
                            <div class="cd-empty"><i class="feather icon-inbox"></i> لا توجد أنشطة حديثة.</div>
                        @else
                            @foreach ($recentNotes as $note)
                                <div class="py-2" style="border-bottom: 1px solid var(--cd-border);">
                                    <a href="{{ route('clients.show', $note['client_id']) }}" target="_blank" rel="noopener" style="font-size: .84rem; color: var(--cd-primary); font-weight: 600;">{{ $note['client_name'] }}</a>
                                    <div style="font-size: .76rem; color: var(--cd-muted);">{{ $note['employee_name'] }} — {{ $note['date'] }}@if ($note['process']) — {{ $note['process'] }}@endif</div>
                                    @if ($note['description'] !== '')
                                        <div class="cd-note-text" title="{{ $note['description'] }}">{{ $note['description'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== اللوحة الجانبية: عملاء الشريحة ==================== --}}
        <div class="cd-drawer-backdrop" id="cdDrawerBackdrop" data-cd-drawer-close hidden></div>
        <aside class="cd-drawer" id="cdDrawer" role="dialog" aria-modal="true" aria-labelledby="cdDrawerTitle" hidden>
            <header class="cd-drawer-head">
                <div>
                    <h5 id="cdDrawerTitle"></h5>
                    <p id="cdDrawerHint"></p>
                </div>
                <button type="button" class="cd-drawer-close" data-cd-drawer-close aria-label="إغلاق">&times;</button>
            </header>
            <div class="cd-drawer-tools">
                <input type="search" id="cdDrawerSearch" placeholder="بحث بالاسم أو الكود أو الجوال…" autocomplete="off">
                <span class="cd-drawer-count" id="cdDrawerCount"></span>
                <button type="button" class="cd-reset" id="cdDrawerCsv"><i class="feather icon-download"></i> تصدير CSV</button>
            </div>
            <div class="cd-drawer-body" id="cdDrawerBody"></div>
        </aside>

    </section>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('client-dashboard');

    // ==================== «كيف يُحسب؟» ====================
    root.querySelectorAll('[data-cd-info-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panel = btn.closest('.cd-card').querySelector('.cd-info-panel');
            if (panel) { panel.hidden = !panel.hidden; }
        });
    });

    // ==================== الفلترة: أزرار الفترة ====================
    root.querySelectorAll('.cd-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var period = btn.getAttribute('data-period');
            document.getElementById('cdPeriod').value = period;
            var isCustom = period === 'custom';
            document.getElementById('cdCustomDates').style.display = isCustom ? '' : 'none';
            document.getElementById('cdCustomDatesTo').style.display = isCustom ? '' : 'none';
            if (!isCustom) { document.getElementById('cdFilterForm').submit(); }
        });
    });

    // ==================== قائمة عملاء الشريحة ====================
    var segmentUrl = @json(route('clients.dashboard.segment'));
    var dashboardFilters = @json(request()->except(['segment']));
    var drawer = document.getElementById('cdDrawer');
    var backdrop = document.getElementById('cdDrawerBackdrop');
    var drawerBody = document.getElementById('cdDrawerBody');
    var drawerSearch = document.getElementById('cdDrawerSearch');
    var drawerCount = document.getElementById('cdDrawerCount');
    var PAGE = 200;
    var state = { data: null, filtered: [], shown: PAGE, request: 0, trigger: null };

    var esc = function (value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var money = function (v) { return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    var cell = function (col, row) {
        var v = row[col.key];
        switch (col.type) {
            case 'client': return '<a href="' + esc(row.url) + '" target="_blank" rel="noopener">' + esc(v) + '</a>';
            // لون الشارة من `<المفتاح>_color` — لكل عمودٍ لونُه (الحالة، الوضع)
            case 'status': {
                var tone = row[col.key + '_color'] || row.status_color || '#999999';
                return '<span class="cd-badge" style="background: color-mix(in srgb, ' + esc(tone) + ' 16%, transparent); color: ' + esc(tone) + ';">' + esc(v) + '</span>';
            }
            case 'money': return '<span class="cd-num">' + money(v) + '</span>';
            case 'number': return '<span class="cd-num">' + Number(v || 0).toLocaleString('en-US') + '</span>';
            case 'days': return v === null || v === undefined ? '—' : '<span class="cd-num">' + v + '</span> يوم';
            case 'date': return v ? '<span class="cd-num">' + esc(v) + '</span>' : '—';
            case 'longtext': return v ? '<div class="cd-long">' + esc(v) + '</div>' : '—';
            case 'reply': return v
                ? '<div class="cd-long">' + esc(v) + '</div><small class="text-muted">' + esc(row.reply_meta) + '</small>'
                : '<span style="color: var(--cd-warning); font-weight: 600;">لم يُردّ بعد</span>';
            case 'link': return v ? '<a href="' + esc(v) + '" target="_blank" rel="noopener">التفاصيل</a>' : '';
            case 'visit': return v === null || v === undefined
                ? '<span style="color: var(--cd-danger); font-weight: 600;">لم يُزَر</span>'
                : '<span class="cd-num">' + esc(row.last_visit) + '</span> <small class="text-muted">(' + v + ' يوم)</small>';
            default: return v ? esc(v) : '—';
        }
    };

    var renderRows = function () {
        var data = state.data;
        if (!data) { return; }
        drawerCount.textContent = state.filtered.length === data.rows.length
            ? data.rows.length.toLocaleString('en-US') + ' عميل'
            : state.filtered.length.toLocaleString('en-US') + ' من ' + data.rows.length.toLocaleString('en-US') + ' عميل';

        if (!state.filtered.length) {
            drawerBody.innerHTML = '<div class="cd-empty"><i class="feather icon-users"></i>' + (data.rows.length ? 'لا نتائج للبحث.' : 'لا يوجد عملاء في هذه الشريحة.') + '</div>';
            return;
        }

        var head = '<tr><th>#</th>' + data.columns.map(function (c) { return '<th>' + esc(c.label) + '</th>'; }).join('') + '</tr>';
        var body = state.filtered.slice(0, state.shown).map(function (row, i) {
            return '<tr><td class="cd-num">' + (i + 1) + '</td>' + data.columns.map(function (c) { return '<td>' + cell(c, row) + '</td>'; }).join('') + '</tr>';
        }).join('');
        var more = state.filtered.length > state.shown
            ? '<button type="button" class="cd-reset cd-drawer-more" id="cdDrawerMore">عرض المزيد (' + (state.filtered.length - state.shown).toLocaleString('en-US') + ' متبقٍّ)</button>'
            : '';

        drawerBody.innerHTML = '<div class="table-responsive"><table class="cd-table"><thead>' + head + '</thead><tbody>' + body + '</tbody></table></div>' + more;
    };

    var applySearch = function () {
        var q = drawerSearch.value.trim().toLowerCase();
        state.shown = PAGE;
        state.filtered = !q ? state.data.rows.slice() : state.data.rows.filter(function (r) {
            return [r.name, r.code, r.phone, r.title, r.description, r.reply].some(function (x) { return String(x || '').toLowerCase().indexOf(q) !== -1; });
        });
        renderRows();
    };

    var openDrawer = function () {
        drawer.hidden = false;
        backdrop.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    var closeDrawer = function () {
        drawer.hidden = true;
        backdrop.hidden = true;
        document.body.style.overflow = '';
        state.request++;
        if (state.trigger && document.contains(state.trigger)) { state.trigger.focus(); }
    };

    var openSegment = function (segment, trigger) {
        state.trigger = trigger || null;
        state.data = null;
        var requestId = ++state.request;

        document.getElementById('cdDrawerTitle').textContent = 'جارٍ التحميل…';
        document.getElementById('cdDrawerHint').textContent = '';
        drawerCount.textContent = '';
        drawerSearch.value = '';
        drawerBody.innerHTML = '<div class="cd-empty"><i class="feather icon-loader"></i>جارٍ جلب العملاء…</div>';
        openDrawer();

        var params = new URLSearchParams();
        Object.keys(dashboardFilters).forEach(function (k) {
            var v = dashboardFilters[k];
            if (Array.isArray(v)) { v.forEach(function (x) { params.append(k + '[]', x); }); }
            else if (v !== null && v !== '') { params.append(k, v); }
        });
        params.set('segment', segment);
        if (trigger && trigger.getAttribute('data-segment-params')) {
            try {
                var extra = JSON.parse(trigger.getAttribute('data-segment-params'));
                Object.keys(extra).forEach(function (k) { params.set(k, extra[k]); });
            } catch (e) { /* معاملاتٌ تالفة لا تمنع القائمة */ }
        }

        fetch(segmentUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) { throw new Error(r.status === 403 ? 'لا تملك صلاحية عرض هذه القائمة.' : 'تعذّر جلب القائمة (' + r.status + ').'); }
                return r.json();
            })
            .then(function (data) {
                if (requestId !== state.request) { return; }
                state.data = data;
                document.getElementById('cdDrawerTitle').textContent = data.title;
                document.getElementById('cdDrawerHint').textContent = data.hint || '';
                state.filtered = data.rows.slice();
                state.shown = PAGE;
                renderRows();
                drawerSearch.focus();
            })
            .catch(function (err) {
                if (requestId !== state.request) { return; }
                document.getElementById('cdDrawerTitle').textContent = 'تعذّر التحميل';
                drawerBody.innerHTML = '<div class="cd-empty"><i class="feather icon-alert-triangle"></i>' + esc(err.message) + '</div>';
            });
    };

    root.addEventListener('click', function (e) {
        if (e.target.closest('[data-cd-drawer-close]')) { closeDrawer(); return; }
        if (e.target.closest('#cdDrawerMore')) { state.shown += PAGE; renderRows(); return; }

        var link = e.target.closest('a[href]');
        var el = e.target.closest('[data-segment]');
        if (!el || (link && el.contains(link) && link !== el)) { return; }
        e.preventDefault();
        openSegment(el.getAttribute('data-segment'), el);
    });

    root.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-segment]')) {
            e.preventDefault();
            openSegment(e.target.getAttribute('data-segment'), e.target);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !drawer.hidden) { closeDrawer(); }
    });

    drawerSearch.addEventListener('input', function () { if (state.data) { applySearch(); } });

    document.getElementById('cdDrawerCsv').addEventListener('click', function () {
        if (!state.data || !state.filtered.length) { return; }
        var cols = state.data.columns;
        var quote = function (v) { return '"' + String(v === null || v === undefined ? '' : v).replace(/"/g, '""') + '"'; };
        var lines = [cols.map(function (c) { return quote(c.label); }).join(',')];
        state.filtered.forEach(function (row) {
            lines.push(cols.map(function (c) {
                if (c.type === 'visit') { return quote(row.last_visit || 'لم يُزَر'); }
                return quote(row[c.key]);
            }).join(','));
        });
        var blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (state.data.title || 'clients').replace(/[\\/:*?"<>|]/g, '-') + '.csv';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 0);
    });

    // ==================== المخطَّطات ====================
    if (typeof ApexCharts === 'undefined') { return; }

    var probe = document.createElement('span');
    probe.style.display = 'none';
    root.appendChild(probe);
    var SENTINEL = 'rgb(1, 2, 3)';
    var color = function (name, fallback) {
        probe.style.color = SENTINEL;
        probe.style.color = 'var(' + name + ')';
        var resolved = getComputedStyle(probe).color;
        return (!resolved || resolved === SENTINEL) ? fallback : resolved;
    };

    var palette = {
        primary: color('--cd-primary', '#7367f0'),
        success: color('--cd-success', '#28c76f'),
        danger: color('--cd-danger', '#ea5455'),
        warning: color('--cd-warning', '#ff9f43'),
        info: color('--cd-info', '#00cfe8'),
        muted: color('--cd-muted', '#7b8194'),
        font: getComputedStyle(root).fontFamily || 'Cairo, sans-serif'
    };

    var base = {
        chart: { fontFamily: palette.font, toolbar: { show: false } },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', fontSize: '12px', labels: { colors: palette.muted } }
    };

    var render = function (selector, options) {
        var el = document.querySelector(selector);
        if (!el) { return; }
        new ApexCharts(el, options).render();
    };

    /* دونات قابلة للنقر: كل جزءٍ (ومفتاحه في الوسيلة) يفتح `prefix.key` */
    var donut = function (selector, rows, opts) {
        var series = rows.map(function (r) { return Number(r[opts.valueKey || 'count']) || 0; });
        if (!series.some(function (v) { return v > 0; })) {
            var el = document.querySelector(selector);
            if (el) { el.innerHTML = '<div class="cd-empty"><i class="feather icon-pie-chart"></i>لا توجد بيانات ضمن هذا النطاق.</div>'; }
            return;
        }
        var format = opts.money ? money : function (v) { return Number(v).toLocaleString('en-US'); };
        var open = function (index) {
            var row = rows[index];
            if (row && row.key !== undefined) { openSegment(opts.prefix + '.' + row.key); }
        };

        render(selector, Object.assign({}, base, {
            chart: Object.assign({}, base.chart, {
                type: 'donut', height: 250,
                events: {
                    dataPointSelection: function (event, ctx, config) { open(config.dataPointIndex); },
                    legendClick: function (ctx, index) { open(index); }
                }
            }),
            series: series,
            // صفوفٌ تحمل «active» (نشاط/أهداف العملاء) تُلحق عدد النشطين
            // باسم الشريحة، فيظهر دائماً في الوسيلة الإيضاحية لا عند التحويم فقط
            labels: rows.map(function (r) { return r[opts.labelKey || 'label']; }),
            colors: rows.map(function (r, i) { return r.color || opts.colors[i % opts.colors.length]; }),
            legend: Object.assign({}, base.legend, { show: opts.legend !== false, onItemClick: { toggleDataSeries: false } }),
            tooltip: { y: { formatter: function (v) { return format(v); } } },
            states: { active: { filter: { type: 'none' } } },
            plotOptions: {
                pie: { expandOnClick: false, donut: { labels: { show: true, total: {
                    show: true, label: opts.totalLabel, fontSize: '13px', color: palette.muted,
                    formatter: function (w) { return format(w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0)); }
                } } } }
            }
        }));
    };

    var cycle = [palette.primary, palette.info, palette.success, palette.warning, palette.danger, palette.muted, '#20c997', '#6f42c1', '#fd7e14', '#0dcaf0'];

    donut('#cdLifecycleChart', @json(array_values($lifecycleSegments)), { prefix: 'lifecycle', totalLabel: 'عميل', legend: false });
    donut('#cdTargetsChart', @json(array_values($customerTargets['rows'])), { prefix: 'target', totalLabel: 'عميل', legend: false });
    donut('#cdAgingChart', @json(array_values($debtAging['buckets'])), { prefix: 'aging', totalLabel: 'إجمالي', valueKey: 'total', money: true, legend: false });
    donut('#cdVisitsOutcomeChart', @json(array_values($visitStates['rows'])), { prefix: 'visit_state', totalLabel: 'زيارة', legend: false });
    donut('#cdStatusChart', @json($clientsByStatus), { prefix: 'status', labelKey: 'status', totalLabel: 'عميل', colors: cycle });
    donut('#cdRegistrationStageChart', @json($clientsByRegistrationStage), { prefix: 'stage', labelKey: 'stage', totalLabel: 'عميل', colors: cycle });
    donut('#cdCategoryChart', @json($clientsByCategory), { prefix: 'category', labelKey: 'category', totalLabel: 'عميل', colors: cycle });

    var yearly = @json($yearlyComparison);
    if (yearly.monthNames && yearly.monthNames.length) {
        render('#cdYearlyChart', Object.assign({}, base, {
            chart: Object.assign({}, base.chart, { type: 'area', height: 320 }),
            series: [
                { name: String(yearly.currentYear), data: yearly.currentYearData },
                { name: String(yearly.previousYear), data: yearly.previousYearData }
            ],
            colors: [palette.primary, palette.muted],
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .05, stops: [0, 90, 100] } },
            xaxis: { categories: yearly.monthNames, labels: { style: { colors: palette.muted, fontSize: '11px' } } },
            yaxis: { labels: { style: { colors: palette.muted, fontSize: '11px' } } }
        }));
    }

    // ==================== الخريطة — مغلقة افتراضياً، وتُحمَّل عند أول فتح ====================
    var cdMapToggle = document.getElementById('cdMapToggle');
    var cdMapBody = document.getElementById('cdMapBody');
    var cdMapLoaded = false;

    function cdLoadMap() {
        if (typeof google === 'undefined' || !google.maps) {
            document.getElementById('cdMap').outerHTML = '<div class="cd-empty"><i class="feather icon-map-pin"></i>خرائط جوجل غير متاحة حالياً.</div>';
            return;
        }

        var map = new google.maps.Map(document.getElementById('cdMap'), { center: { lat: 24.7136, lng: 46.6753 }, zoom: 6 });

        fetch('{{ route('clients.getMapData') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var clients = data.clients || [];
                document.getElementById('cdMapCount').textContent = clients.length + ' عميل على الخريطة';
                if (!clients.length) { return; }

                var bounds = new google.maps.LatLngBounds();
                clients.forEach(function (c) {
                    var pos = { lat: c.lat, lng: c.lng };
                    var marker = new google.maps.Marker({ position: pos, map: map, title: c.trade_name });
                    var info = new google.maps.InfoWindow({
                        content: '<div style="font-family:inherit;text-align:right;"><strong>' + esc(c.trade_name) + '</strong><br>' + esc(c.phone || '') + '<br><small>' + esc(c.branch || '') + '</small></div>'
                    });
                    marker.addListener('click', function () { info.open(map, marker); });
                    bounds.extend(pos);
                });
                map.fitBounds(bounds);
            })
            .catch(function () {
                document.getElementById('cdMapCount').textContent = 'تعذّر تحميل بيانات الخريطة.';
            });
    }

    if (cdMapToggle && cdMapBody) {
        cdMapToggle.addEventListener('click', function () {
            var willOpen = cdMapBody.classList.contains('d-none');
            cdMapBody.classList.toggle('d-none', !willOpen);
            cdMapToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            cdMapToggle.querySelector('span').textContent = willOpen ? 'إغلاق الخريطة' : 'فتح الخريطة';
            cdMapToggle.querySelector('i').className = willOpen ? 'feather icon-chevron-up' : 'feather icon-chevron-down';
            if (willOpen && !cdMapLoaded) { cdMapLoaded = true; cdLoadMap(); }
        });
    }
});
</script>
@endsection
