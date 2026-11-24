{{--
    تعيين العملاء إلى الموظفين — تصميمٌ مضغوط (2026-09-28، الجولة الثانية).

    قال المستخدم عن الترتيب السابق: «وين التصميم المرتب… وين التقليص للأحجام».
    فالشاشةُ الآن ثلاثُ قطعٍ قصيرة تُرى معاً بلا تمرير طويل:
      ① شريطُ أرقامٍ واحد (٥٦ بكسل) بدل أربع بلاطاتٍ طويلة.
      ② بطاقةُ التعيين: حقولُها الأربعةُ وزرُّها في **سطرٍ واحد**، والتبويبان في ترويستها.
      ③ «الموظفون وعملاؤهم»: بطاقاتٌ صغيرة، كلٌّ تفتح عملاءَ موظفها في تقرير
         «العملاء المسندون للموظفين» حيث النقلُ والإزالة.

    النماذجُ ومساراتُها وأسماءُ حقولها ومعرّفاتُ عناصرها كما كانت.
--}}
@extends('client::master')

@section('title')
تعيين العملاء إلى الموظفين
@endsection

@section('css')
<style>
    .cl-scope .ba { --ba-h: 34px; font-size: .82rem; }

    /* ── ① شريط الأرقام ── */
    .cl-scope .ba-strip {
        display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
        background: var(--app-surface, #fff); border: 1px solid var(--cl-line); border-radius: 12px;
        margin-bottom: 12px; overflow: hidden;
    }
    .cl-scope .ba-strip__item {
        display: flex; align-items: center; gap: 10px; padding: 9px 14px; min-width: 0;
        border-inline-start: 1px solid var(--cl-line-soft); color: inherit; text-decoration: none;
    }
    .cl-scope .ba-strip__item:first-child { border-inline-start: 0; }
    .cl-scope a.ba-strip__item:hover { background: var(--cl-tint); }
    .cl-scope .ba-strip__icon {
        flex: 0 0 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
        font-size: .85rem; color: var(--c, var(--app-primary, #7367f0));
        background: color-mix(in srgb, var(--c, var(--app-primary, #7367f0)) 12%, transparent);
    }
    .cl-scope .ba-strip__value { font-size: 1.05rem; font-weight: 800; line-height: 1.15; color: var(--app-text, #4b4b4b); }
    .cl-scope .ba-strip__label { font-size: .7rem; color: var(--app-muted, #6e6b7b); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* ── البطاقة ── */
    .cl-scope .ba-card { background: var(--app-surface, #fff); border: 1px solid var(--cl-line); border-radius: 12px; margin-bottom: 12px; }
    .cl-scope .ba-card__head {
        display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
        padding: 8px 14px; border-bottom: 1px solid var(--cl-line-soft);
    }
    .cl-scope .ba-card__title { margin: 0; font-size: .88rem; font-weight: 800; color: var(--app-text, #4b4b4b); display: inline-flex; align-items: center; gap: 8px; }
    .cl-scope .ba-card__title i { color: var(--app-primary, #7367f0); }
    .cl-scope .ba-card__body { padding: 12px 14px; }
    .cl-scope .ba-count {
        font-size: .7rem; font-weight: 700; padding: 1px 8px; border-radius: 999px;
        background: var(--cl-tint); color: var(--app-muted, #6e6b7b);
    }

    /* التبويبان — في ترويسة البطاقة */
    .cl-scope .ba-tabs { display: inline-flex; gap: 2px; padding: 3px; border-radius: 9px; background: var(--cl-tint); }
    .cl-scope .ba-tab {
        border: 0; background: transparent; padding: 0 12px; height: 28px; border-radius: 7px; cursor: pointer;
        font-size: .76rem; font-weight: 700; color: var(--app-muted, #6e6b7b);
    }
    .cl-scope .ba-tab.is-active { background: var(--app-surface, #fff); color: var(--app-primary, #7367f0); box-shadow: 0 1px 3px rgba(0, 0, 0, .1); }
    .cl-scope .ba-pane[hidden] { display: none; }

    /* ── ② نموذجُ السطر الواحد ── */
    .cl-scope .ba-form { display: grid; grid-template-columns: 150px minmax(200px, 1.5fr) minmax(170px, 1fr) auto auto; gap: 10px; align-items: end; }
    .cl-scope .ba-f { min-width: 0; }
    .cl-scope .ba .ba-f > label, .cl-scope .ba .ba-row label {
        display: block; margin: 0 0 3px; font-size: .7rem; font-weight: 700; color: var(--app-muted, #6e6b7b);
    }
    .cl-scope .ba .btn { height: var(--ba-h); padding: 0 14px; font-size: .8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .cl-scope .ba .btn-sm { height: 28px; padding: 0 10px; font-size: .74rem; }

    /* select2 بارتفاع الحقول المضغوطة */
    .cl-scope .ba .select2-container .select2-selection--single { height: var(--ba-h); min-height: var(--ba-h); border-radius: 8px; }
    .cl-scope .ba .select2-container .select2-selection--single .select2-selection__rendered { line-height: calc(var(--ba-h) - 2px); font-size: .8rem; padding-inline: 10px 26px; }
    .cl-scope .ba .select2-container .select2-selection--single .select2-selection__arrow { height: calc(var(--ba-h) - 2px); }

    /* «إضافة / نقل» زرّان متلاصقان */
    .cl-scope .ba-seg { display: inline-flex; height: var(--ba-h); border: 1px solid var(--cl-line); border-radius: 8px; overflow: hidden; background: var(--app-surface, #fff); }
    .cl-scope .ba-seg input { position: absolute; opacity: 0; pointer-events: none; }
    /* `label[for]` يغلب `.cl-scope label:not(.cl-switch)` (display:block) في ثيم الوحدة */
    .cl-scope .ba .ba-seg label[for] {
        display: inline-flex; align-items: center; gap: 6px; margin: 0; padding: 0 13px; cursor: pointer;
        font-size: .78rem; font-weight: 700; color: var(--app-muted, #6e6b7b); white-space: nowrap;
    }
    .cl-scope .ba .ba-seg input:checked + label[for] { background: var(--app-primary, #7367f0); color: var(--app-on-primary, #fff); }
    .cl-scope .ba .ba-seg input:focus-visible + label[for] { outline: 2px solid var(--app-primary, #7367f0); outline-offset: -2px; }
    .cl-scope .ba-mode { display: contents; }
    .cl-scope .ba-mode__hint { display: none; }

    /* سطرُ ما تحت الحقول: «يشمل ما تحتها» وشرحُ الطريقة */
    .cl-scope .ba-note { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px 16px; margin-top: 8px; font-size: .72rem; color: var(--app-muted, #6e6b7b); }
    .cl-scope .ba .ba-note label { display: inline-flex; align-items: center; gap: 6px; margin: 0; font-size: .72rem; font-weight: 600; cursor: pointer; color: var(--app-muted, #6e6b7b); }
    .cl-scope .ba-note__hint i { color: var(--app-primary, #7367f0); }

    /* المعاينة */
    .cl-scope .ba-preview { margin-top: 10px; border: 1px solid var(--cl-line); border-radius: 10px; overflow: hidden; }
    .cl-scope .ba-preview__bar { display: flex; flex-wrap: wrap; gap: 6px 14px; padding: 7px 12px; background: var(--cl-tint); font-size: .76rem; }
    .cl-scope .ba-preview__item { display: inline-flex; gap: 4px; align-items: baseline; }
    .cl-scope .ba-preview__item strong { font-size: .84rem; color: var(--app-primary, #7367f0); }
    .cl-scope .ba-clients { max-height: 232px; overflow: auto; }
    .cl-scope .ba-clients table { margin: 0; }
    .cl-scope .ba-clients thead th { position: sticky; top: 0; z-index: 1; background: var(--app-surface, #fff); font-size: .7rem; padding: 5px 10px; }
    .cl-scope .ba-clients td { font-size: .76rem; padding: 4px 10px; vertical-align: middle; }
    .cl-scope .ba-type-badge {
        display: inline-block; padding: 0 7px; border-radius: 999px; font-size: .68rem; font-weight: 700;
        background: color-mix(in srgb, var(--app-primary, #7367f0) 12%, transparent); color: var(--app-primary, #7367f0);
    }
    .cl-scope .ba-type-badge:empty { display: none; }

    /* عدّة جهات: صفٌّ لكلّ جهة */
    .cl-scope .ba-row { display: grid; grid-template-columns: 150px minmax(200px, 1.5fr) minmax(170px, 1fr) var(--ba-h); gap: 10px; align-items: end; margin-bottom: 8px; }
    .cl-scope .ba .ba-row__remove { width: var(--ba-h); padding: 0; justify-content: center; }
    .cl-scope .ba-bulk-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding-top: 10px; border-top: 1px solid var(--cl-line-soft); }
    .cl-scope .ba-bulk-foot__end { margin-inline-start: auto; display: inline-flex; gap: 10px; align-items: center; }

    /* ── ③ بطاقاتُ الموظفين ── */
    .cl-scope .ba-search { height: 30px; width: 220px; max-width: 100%; font-size: .78rem; border-radius: 8px; }
    .cl-scope .ba-emps { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px; }
    .cl-scope .ba-emp {
        position: relative; display: flex; align-items: center; gap: 10px; padding: 9px 12px 12px; min-width: 0;
        border: 1px solid var(--cl-line); border-radius: 10px; background: var(--app-surface, #fff);
        color: inherit; text-decoration: none; overflow: hidden; transition: border-color .15s, box-shadow .15s, transform .15s;
    }
    .cl-scope .ba-emp:hover { border-color: var(--app-primary, #7367f0); box-shadow: 0 4px 14px rgba(0, 0, 0, .07); transform: translateY(-1px); }
    .cl-scope .ba-emp[hidden] { display: none; }
    .cl-scope .ba-emp__av {
        flex: 0 0 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: .85rem; color: var(--app-primary, #7367f0);
        background: color-mix(in srgb, var(--app-primary, #7367f0) 12%, transparent);
    }
    .cl-scope .ba-emp__body { flex: 1; min-width: 0; }
    .cl-scope .ba-emp__name { display: block; font-weight: 700; font-size: .82rem; color: var(--app-text, #4b4b4b); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cl-scope .ba-emp__meta { display: block; font-size: .68rem; color: var(--app-muted, #6e6b7b); }
    .cl-scope .ba-emp__n { text-align: center; line-height: 1.1; }
    .cl-scope .ba-emp__n strong { display: block; font-size: 1.05rem; font-weight: 800; color: var(--app-primary, #7367f0); }
    .cl-scope .ba-emp__n small { font-size: .66rem; color: var(--app-muted, #6e6b7b); }
    .cl-scope .ba-emp__go { font-size: .7rem; color: var(--app-muted, #6e6b7b); opacity: 0; transition: opacity .15s; }
    .cl-scope .ba-emp:hover .ba-emp__go { opacity: 1; color: var(--app-primary, #7367f0); }
    /* حصّتُه من أكثر الموظفين عملاءً — خطٌّ أسفل البطاقة */
    .cl-scope .ba-emp__bar { position: absolute; inset-inline: 0; bottom: 0; height: 3px; background: var(--cl-tint); }
    .cl-scope .ba-emp__bar span { display: block; height: 100%; background: var(--app-primary, #7367f0); opacity: .75; }
    .cl-scope .ba-empty { padding: 22px 10px; text-align: center; color: var(--app-muted, #6e6b7b); font-size: .8rem; }

    @media (max-width: 1100px) {
        .cl-scope .ba-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cl-scope .ba-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 768px) {
        .cl-scope .ba-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cl-scope .ba-strip__item:nth-child(3) { border-inline-start: 0; }
        .cl-scope .ba-strip__item:nth-child(n+3) { border-top: 1px solid var(--cl-line-soft); }
        .cl-scope .ba-form, .cl-scope .ba-row { grid-template-columns: minmax(0, 1fr); }
    }
</style>
@endsection

@section('content')
@include('client::partials.settings-theme')
@include('components.hint-tips')
<div class="cl-scope">
<div class="ba">

@php
    // الوضعُ الافتراضي يتبع الإعدادات: موظفٌ واحد للعميل ⇒ «نقل»، وإلّا «إضافة»
    $defaultMode = $allowMultiple ? 'add' : 'replace';
    $modeRule = ! $allowMultiple
        ? 'الإعدادات تسمح بموظفٍ واحد لكل عميل — «إضافة» تتخطّى من له موظف.'
        : ($maxEmployees ? 'الحدّ الأقصى من الإعدادات: '.$maxEmployees.' موظف لكل عميل — «إضافة» تتخطّى من بلغه.' : null);
    $currentUrl = route('clients.bulk-assignment.current');
    $topClients = max(1, (int) $employeeSummary->max('clients'));
    $fmt = fn ($n) => \App\Support\LanguageSettings::formatNumber($n, 0);

    // أنواع الجهات بترتيب الشجرة، كلٌّ بعدد ما في القائمة منه — نوعٌ بلا جهات لا يُعرض
    $typeCounts = $nodeOptions->countBy('display_type');
    $nodeTypes = collect(['branch', 'region', 'group', 'outlet'])
        ->filter(fn ($t) => $typeCounts->get($t))
        ->mapWithKeys(fn ($t) => [$t => \App\Models\Branch::nodeTypeMeta($t)['label'].' ('.$typeCounts->get($t).')']);
    // خطأُ تحقّقٍ في «عدّة جهات» يُعيد فتحَ تبويبها
    $openBulk = $errors->has('assignments') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'assignments.'));
@endphp

@include('client::components.page-head', [
    'title' => 'تعيين العملاء إلى الموظفين',
    'icon' => 'users',
    'sub' => 'أسنِد عملاء جهةٍ لموظف، وتابع من عليه كم عميلاً',
    'crumbs' => [
        'الرئيسية' => route('dashboard'),
        'العملاء' => route('clients.index'),
        'تعيين جماعي' => null,
    ],
    'actions' => '<a href="'.e($currentUrl).'" class="btn btn-outline-primary"><i class="fas fa-user-friends me-1"></i> التعيينات الحالية</a>',
])

@include('client::components.guide', [
    'key' => 'clients.bulk-assignment.index',
    'open' => false,
    'title' => 'ما هذه الشاشة، وأين أنقل عميلاً أو أزيل موظفاً؟',
    'lead' => 'هنا تُسند عملاء جهةٍ كاملة (فرع، منطقة، مجموعة، منفذ) لموظف. أما العمل على عملاء بأعيانهم — نقلهم أو إزالة موظف عنهم — فمن «التعيينات الحالية».',
    'blocks' => [
        'جهة لموظف' => [
            'اختر «نوع الجهة» فتُحصر قائمة «تابع لـ» فيه، ثم الجهة، ثم الموظف.',
            'اختيار منطقةٍ يأخذ عملاءها وحدها — عملاء ما تحتها لا يدخلون إلا بتفعيل «يشمل ما تحتها».',
            'قبل الضغط يظهر جدول العملاء الذين سيُسندون بالضبط ومن عليهم الآن.',
        ],
        '«إضافة» أم «نقل»؟' => [
            'إضافة: يُضاف الموظف بجانب من هم على العميل، ولا يفقد أحدٌ عملاءه.',
            'نقل: يصير الموظف المختار وحده على هؤلاء العملاء، ويُنزع عنهم كل موظفٍ آخر.',
        ],
        'عدّة جهات' => 'عدّة جهاتٍ لعدّة موظفين في عمليةٍ واحدة. تُنفَّذ الجهة الأعلى قبل ما تحتها.',
        'الموظفون وعملاؤهم' => 'اضغط بطاقة الموظف: تفتح عملاءه بجهاتهم، ومنها تحدّد عملاء وتنقلهم لموظفٍ آخر أو تزيله عنهم، وتحذف موظفاً من عميلٍ بعينه.',
    ],
    'facts' => array_values(array_filter([
        $modeRule,
        'كل عملية تُسجَّل في سجلّ النشاط باسم من نفّذها.',
    ])),
])

@include('layouts.alerts.error')
@include('layouts.alerts.success')

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
        <strong>خطأ في البيانات المدخلة:</strong>
        {{ implode(' · ', $errors->all()) }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ══════════ ① شريط الأرقام ══════════ --}}
<div class="ba-strip">
    <div class="ba-strip__item" style="--c:#10b981">
        <span class="ba-strip__icon"><i class="fas fa-user-check"></i></span>
        <div class="ba-f">
            <div class="ba-strip__value">{{ $fmt($stats['assigned']) }}</div>
            <div class="ba-strip__label">عملاء مسنَدون</div>
        </div>
    </div>
    <a class="ba-strip__item" style="--c:#f59e0b" href="{{ $currentUrl }}?assignment=unassigned" title="عرض العملاء غير المسنَدين">
        <span class="ba-strip__icon"><i class="fas fa-user-slash"></i></span>
        <div class="ba-f">
            <div class="ba-strip__value">{{ $fmt($stats['unassigned']) }}</div>
            <div class="ba-strip__label">بلا موظف — اضغط لعرضهم</div>
        </div>
    </a>
    <div class="ba-strip__item">
        <span class="ba-strip__icon"><i class="fas fa-users"></i></span>
        <div class="ba-f">
            <div class="ba-strip__value">{{ $fmt($stats['employees']) }}</div>
            <div class="ba-strip__label">موظفون عليهم عملاء</div>
        </div>
    </div>
    <div class="ba-strip__item" style="--c:#06b6d4">
        <span class="ba-strip__icon"><i class="fas fa-sliders-h"></i></span>
        <div class="ba-f">
            <div class="ba-strip__value">{{ ! $allowMultiple ? 'موظف واحد' : ($maxEmployees ? 'حتى '.$maxEmployees : 'بلا حدّ') }}</div>
            <div class="ba-strip__label">موظفون للعميل — من الإعدادات</div>
        </div>
    </div>
</div>

{{-- قوالب الخيارات — يُنسخ منها كل صفٍّ جديد، ومنها تُعاد قائمةُ «تابع لـ» كلّما تغيّر النوع --}}
<template id="baNodeOptions">
    <option value="">— اختر —</option>
    @foreach ($nodeOptions as $option)
        <option value="{{ $option['id'] }}" data-type="{{ $option['display_type'] }}">{{ $option['label'] }}</option>
    @endforeach
</template>
<template id="baTypeOptions">
    <option value="">كل الأنواع</option>
    @foreach ($nodeTypes as $type => $typeLabel)
        <option value="{{ $type }}">{{ $typeLabel }}</option>
    @endforeach
</template>
<template id="baEmployeeOptions">
    <option value="">— اختر —</option>
    @foreach ($employees as $employee)
        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
    @endforeach
</template>

{{-- ══════════ ② التعيين ══════════ --}}
<div class="ba-card">
    <div class="ba-card__head">
        <h5 class="ba-card__title"><i class="fas fa-sitemap"></i> تعيين عملاء جهةٍ إلى موظف</h5>
        <div class="ba-tabs" role="tablist">
            <button type="button" class="ba-tab {{ $openBulk ? '' : 'is-active' }}" data-pane="baPaneOne" role="tab">جهة لموظف</button>
            <button type="button" class="ba-tab {{ $openBulk ? 'is-active' : '' }}" data-pane="baPaneBulk" role="tab">عدّة جهات</button>
        </div>
    </div>

    <div class="ba-card__body">
        {{-- ── جهة لموظف: الحقولُ والزرُّ في سطرٍ واحد ── --}}
        <div class="ba-pane" id="baPaneOne" @if ($openBulk) hidden @endif>
            <form action="{{ route('clients.assign-branch-to-employee') }}" method="POST" id="nodeAssignForm">
                @csrf
                <div class="ba-form">
                    <div class="ba-f">
                        <label for="nodeAssignType">نوع الجهة</label>
                        <select class="form-control ba-select ba-type" id="nodeAssignType" data-target="#nodeAssignNode">
                            <option value="">كل الأنواع</option>
                            @foreach ($nodeTypes as $type => $typeLabel)
                                <option value="{{ $type }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ba-f">
                        <label for="nodeAssignNode">تابع لـ</label>
                        <select class="form-control ba-select" name="tree_node" id="nodeAssignNode" required>
                            <option value="">— اختر —</option>
                            @foreach ($nodeOptions as $option)
                                <option value="{{ $option['id'] }}" data-type="{{ $option['display_type'] }}" @selected((string) old('tree_node') === (string) $option['id'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ba-f">
                        <label for="nodeAssignEmployee">الموظف</label>
                        <select class="form-control ba-select" name="employee_id" id="nodeAssignEmployee" required>
                            <option value="">— اختر —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ba-f">
                        <label>الطريقة</label>
                        @include('client::bulk-assignment.partials.mode', ['name' => 'mode', 'idPrefix' => 'nodeMode'])
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> تعيين الآن</button>
                </div>

                <div class="ba-note">
                    {{-- مطفأٌ افتراضاً: اختيارُ منطقةٍ يعني عملاءَها هي، لا عملاءَ كلِّ فرعٍ ومنفذٍ تحتها --}}
                    <span>
                        <input type="hidden" name="include_descendants" value="0">
                        <label for="nodeAssignDesc">
                            <input type="checkbox" id="nodeAssignDesc" name="include_descendants" value="1" @checked(old('include_descendants') === '1')>
                            يشمل أيضاً عملاء ما تحتها (الفروع والمناطق والمنافذ التابعة)
                        </label>
                    </span>
                    <span class="ba-note__hint"><i class="fas fa-info-circle"></i> <span data-ba-hint-for="nodeMode"></span></span>
                </div>

                <div class="ba-preview" id="nodeAssignPreview" hidden>
                    <div class="ba-preview__bar">
                        <span class="ba-preview__item">تابع لـ: <strong data-k="node_name">—</strong> <span class="ba-type-badge" data-k="node_type"></span></span>
                        <span class="ba-preview__item">العملاء: <strong data-k="clients_count">0</strong></span>
                        <span class="ba-preview__item">بلا موظف: <strong data-k="unassigned_count">0</strong></span>
                        <span class="ba-preview__item" data-wrap="with_employee_count" hidden>مسندون لهذا الموظف أصلاً: <strong data-k="with_employee_count">0</strong></span>
                        <span class="ba-preview__item" data-k="truncated" hidden>تُعرض أول 500 — والتعيين يشمل الجميع</span>
                    </div>
                    <div class="ba-clients">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr><th>الكود</th><th>العميل</th><th>تابع لـ</th><th>النوع</th><th>الموظف الحالي</th></tr>
                            </thead>
                            <tbody data-k="clients"></tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>

        {{-- ── عدّة جهات دفعة واحدة ── --}}
        <div class="ba-pane" id="baPaneBulk" @if (! $openBulk) hidden @endif>
            <form action="{{ route('clients.bulk-assign') }}" method="POST" id="bulkForm">
                @csrf
                <div id="assignmentRows"></div>

                <div class="ba-bulk-foot">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="addRowBtn"><i class="fa fa-plus"></i> إضافة صف</button>
                    <span class="ba-note" style="margin:0">
                        <input type="hidden" name="include_descendants" value="0">
                        <label for="bulkDesc">
                            <input type="checkbox" id="bulkDesc" name="include_descendants" value="1">
                            يشمل أيضاً عملاء ما تحت كل جهة
                        </label>
                    </span>
                    <span class="ba-bulk-foot__end">
                        @include('client::bulk-assignment.partials.mode', ['name' => 'mode', 'idPrefix' => 'bulkMode'])
                        <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> تعيين جميع الصفوف</button>
                    </span>
                </div>
                <div class="ba-note">
                    <span>صفٌّ لكل جهةٍ وموظفها — تُنفَّذ الجهة الأعلى قبل ما تحتها.</span>
                    <span class="ba-note__hint"><i class="fas fa-info-circle"></i> <span data-ba-hint-for="bulkMode"></span></span>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════ ③ الموظفون وعملاؤهم ══════════ --}}
<div class="ba-card">
    <div class="ba-card__head">
        <h5 class="ba-card__title">
            <i class="fas fa-users"></i> الموظفون وعملاؤهم
            <span class="ba-count">{{ $fmt($employeeSummary->count()) }} موظف</span>
        </h5>
        <input type="search" class="form-control ba-search" id="baEmployeeSearch" placeholder="بحث باسم الموظف..." aria-label="بحث باسم الموظف">
    </div>
    <div class="ba-card__body">
        @if ($employeeSummary->isEmpty())
            <div class="ba-empty"><i class="fas fa-users"></i> لا توجد تعيينات بعد — اختر جهةً وموظفاً من البطاقة أعلاه.</div>
        @else
            <div class="ba-emps" id="baEmployees">
                @foreach ($employeeSummary as $row)
                    <a class="ba-emp" href="{{ $currentUrl }}?sales_manager={{ $row['id'] }}" data-name="{{ $row['name'] }}"
                        title="عملاء {{ $row['name'] }} بجهاتهم — ومنها النقل والإزالة">
                        <span class="ba-emp__av">{{ mb_substr(trim($row['name']), 0, 1) }}</span>
                        <span class="ba-emp__body">
                            <span class="ba-emp__name">{{ $row['name'] }}</span>
                            <span class="ba-emp__meta">{{ $row['nodes'] }} جهة <span class="ba-emp__go">· عرض ونقل وإزالة <i class="fas fa-arrow-left"></i></span></span>
                        </span>
                        <span class="ba-emp__n"><strong>{{ $fmt($row['clients']) }}</strong><small>عميل</small></span>
                        <span class="ba-emp__bar"><span style="width: {{ max(2, round($row['clients'] / $topClients * 100)) }}%"></span></span>
                    </a>
                @endforeach
            </div>
            <div class="ba-empty" id="baEmployeesNone" hidden>لا موظف بهذا الاسم.</div>
        @endif
    </div>
</div>

</div>
</div>
@endsection

@section('scripts')
{{-- السكربتُ في قسم `scripts` لا داخل المحتوى: `client::master` يحمّل jQuery في الرأس ثمّ
     `vendors.min.js` في الأسفل يستبدله بنسخةٍ تحمل select2 — فسكربتٌ داخل المحتوى يلتقط
     عناصرَه بالنسخة الأولى، و`$el.select2` غيرُ معرَّفةٍ فيها، فيتوقّف قبل ربط المعاينة. --}}
<script>
{{--
    select2 بصنفٍ خاصّ (`ba-select`) لا `select2`: المُهيِّئُ العامّ
    `form-select2.js` يمسح `.select2`، وselect2 يضع الصنفَ نفسَه على الحاوية
    التي يولّدها — فكان «إضافة صف» يعيد تهيئة كل حاويات الصفحة فوق بعضها.
--}}
function baSelect2($el, extra) {
    $el.select2(Object.assign({ dir: 'rtl', width: '100%' }, extra || {}));
    $el.next('.select2-container').removeClass('select2');
}

{{--
    «نوع الجهة» يحصر قائمة «تابع لـ» فيه: تُعاد خياراتُها من القالب
    (`#baNodeOptions`، كلُّ خيارٍ بـ`data-type` هو النوعُ المعروض لا
    المخزَّن) ويبقى المختارُ إن كان من النوع الجديد.
--}}
const BA_NODE_OPTIONS = Array.from(
    document.getElementById('baNodeOptions').content.querySelectorAll('option[value]:not([value=""])')
).map(o => ({ id: o.value, type: o.dataset.type, label: o.textContent }));

function baFilterNodes($nodeSel, type) {
    const keep = $nodeSel.val();
    const el = $nodeSel[0];
    el.innerHTML = '<option value="">— اختر —</option>';
    BA_NODE_OPTIONS.forEach(function (o) {
        if (type && o.type !== type) return;
        el.add(new Option(o.label, o.id, false, o.id === keep));
    });
    $nodeSel.trigger('change');
}

function baBindType($typeSel, $nodeSel) {
    $typeSel.on('change', function () { baFilterNodes($nodeSel, $typeSel.val()); });
}

// ── التبويبان ──
(function () {
    document.querySelectorAll('.ba-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.ba-tab').forEach(t => t.classList.toggle('is-active', t === tab));
            document.querySelectorAll('.ba-pane').forEach(p => { p.hidden = p.id !== tab.dataset.pane; });
        });
    });
})();

// ── شرحُ «إضافة / نقل» — سطرٌ واحد يتبدّل بالاختيار ──
(function () {
    document.querySelectorAll('[data-ba-hint-for]').forEach(function (target) {
        const radios = document.querySelectorAll('input[type="radio"][id^="' + target.dataset.baHintFor + '"]');
        const show = function () {
            const on = Array.from(radios).find(r => r.checked);
            target.textContent = on ? on.dataset.hint : '';
        };
        radios.forEach(r => r.addEventListener('change', show));
        show();
    });
})();

// ── المعاينة ──
(function () {
    const infoUrl = @json(route('clients.group-info', ['groupId' => '__ID__']));
    const $node = $('#nodeAssignNode');
    const $emp = $('#nodeAssignEmployee');
    const $desc = $('#nodeAssignDesc');
    const box = document.getElementById('nodeAssignPreview');
    let seq = 0;

    function refresh() {
        const nodeId = $node.val();
        if (!nodeId) { box.hidden = true; return; }

        const params = new URLSearchParams({ include_descendants: $desc.is(':checked') ? '1' : '0' });
        if ($emp.val()) params.set('employee_id', $emp.val());

        const mine = ++seq;
        fetch(infoUrl.replace('__ID__', nodeId) + '?' + params, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : null)
            .then(d => {
                if (!d || mine !== seq) return;
                box.querySelector('[data-k="node_name"]').textContent = d.node ? d.node.name : '—';
                box.querySelector('[data-k="node_type"]').textContent = d.node ? d.node.type_label : '';
                box.querySelector('[data-k="clients_count"]').textContent = d.clients_count;

                // العملاء أنفسهم — textContent لا innerHTML: الأسماء من المستخدمين
                const tbody = box.querySelector('[data-k="clients"]');
                tbody.textContent = '';
                if (!d.clients.length) {
                    const tr = tbody.insertRow();
                    const td = tr.insertCell();
                    td.colSpan = 5;
                    td.className = 'text-center text-muted';
                    td.textContent = 'لا يوجد عملاء يتبعون لهذه الجهة'
                        + ($desc.is(':checked') ? '' : ' مباشرةً — فعّل «يشمل أيضاً عملاء ما تحتها» إن كانوا على ما تحتها');
                }
                d.clients.forEach(function (c) {
                    const tr = tbody.insertRow();
                    [c.code, c.name, c.node].forEach(v => { tr.insertCell().textContent = v ?? ''; });
                    const typeCell = tr.insertCell();
                    const badge = document.createElement('span');
                    badge.className = 'ba-type-badge';
                    badge.textContent = c.node_type;
                    typeCell.appendChild(badge);
                    const empCell = tr.insertCell();
                    empCell.textContent = c.employees.length ? c.employees.join('، ') : '—';
                    if (!c.employees.length) empCell.className = 'text-muted';
                });
                box.querySelector('[data-k="truncated"]').hidden = !d.clients_truncated;

                box.querySelector('[data-k="unassigned_count"]').textContent = d.unassigned_count;
                const wrap = box.querySelector('[data-wrap="with_employee_count"]');
                wrap.hidden = d.with_employee_count === null;
                box.querySelector('[data-k="with_employee_count"]').textContent = d.with_employee_count ?? 0;
                box.hidden = false;
            });
    }

    $(function () {
        baSelect2($('#nodeAssignType'));
        baSelect2($node);
        baSelect2($emp);
        baBindType($('#nodeAssignType'), $node);
        $node.on('change', refresh);
        $emp.on('change', refresh);
        $desc.on('change', refresh);
        refresh();
    });
})();

// ── صفوف التعيين المتعدد ──
(function () {
    let rowIndex = 0;
    const nodeTpl = document.getElementById('baNodeOptions').innerHTML;
    const typeTpl = document.getElementById('baTypeOptions').innerHTML;
    const empTpl = document.getElementById('baEmployeeOptions').innerHTML;

    function addRow() {
        const i = rowIndex++;
        const row = document.createElement('div');
        row.className = 'ba-row';
        row.innerHTML = `
            <div class="ba-f">
                <label>نوع الجهة</label>
                <select class="form-control ba-select" data-role="type">${typeTpl}</select>
            </div>
            <div class="ba-f">
                <label>تابع لـ</label>
                <select class="form-control ba-select" data-role="node" name="assignments[${i}][tree_node]" required>${nodeTpl}</select>
            </div>
            <div class="ba-f">
                <label>الموظف</label>
                <select class="form-control ba-select" name="assignments[${i}][employee_id]" required>${empTpl}</select>
            </div>
            <button type="button" class="btn btn-outline-danger ba-row__remove" title="حذف الصف" aria-label="حذف الصف"><i class="fa fa-times"></i></button>
        `;
        document.getElementById('assignmentRows').appendChild(row);
        $(row).find('select.ba-select').each(function () { baSelect2($(this)); });
        baBindType($(row).find('[data-role="type"]'), $(row).find('[data-role="node"]'));
        row.querySelector('.ba-row__remove').addEventListener('click', function () {
            if (document.querySelectorAll('#assignmentRows .ba-row').length === 1) return;
            $(row).find('select.ba-select').select2('destroy');
            row.remove();
        });
    }

    $(function () {
        addRow();
        document.getElementById('addRowBtn').addEventListener('click', addRow);
    });
})();

// ── البحث في الموظفين ──
(function () {
    const input = document.getElementById('baEmployeeSearch');
    const none = document.getElementById('baEmployeesNone');
    if (!input || !none) return;
    input.addEventListener('input', function () {
        const term = input.value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll('#baEmployees .ba-emp').forEach(function (card) {
            card.hidden = term !== '' && !card.dataset.name.toLowerCase().includes(term);
            if (!card.hidden) shown++;
        });
        none.hidden = shown > 0;
    });
})();
</script>
@endsection
