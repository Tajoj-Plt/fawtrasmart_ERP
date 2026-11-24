@extends('hr::master')

@section('title', 'حسابات الرواتب')

@push('styles')
    <style>
        /*
            «حسابات الرواتب» — نظرةٌ للقراءة، بطاقةٌ لكلِّ قسم.

            كانت الأقسامُ متتاليةً في بطاقةٍ واحدة، تحت كلٍّ منها زرُّ «تعديل
            الربط» يلتصق به عنوانُ القسم التالي، وصفوفُها بعرض الصفحة كلِّه
            فتبتعد القيمةُ عن اسمها. صار لكلِّ قسمٍ بطاقتُه (عمودان على
            الشاشات الواسعة)، وفي رأسها عددُ غير المربوط ورابطُ التعديل.
        */
        .pa-summary { display: flex; flex-wrap: wrap; align-items: center; gap: var(--stk-3) var(--stk-5); padding: var(--stk-4) var(--stk-5); }
        .pa-summary__item { display: flex; align-items: center; gap: var(--stk-3); min-width: 0; }
        .pa-summary__icon {
            flex: none; width: 40px; height: 40px; border-radius: var(--stk-r);
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
        }
        .pa-summary__icon--on { background: var(--app-success-soft); color: var(--app-success); }
        .pa-summary__icon--off { background: color-mix(in srgb, var(--app-danger) 12%, transparent); color: var(--app-danger); }
        .pa-summary__value { font-size: 1.35rem; font-weight: 700; line-height: 1.1; color: var(--app-text); font-variant-numeric: tabular-nums; margin: 0; }
        .pa-summary__label { font-size: .78rem; color: var(--app-muted); margin: 0; }
        .pa-summary__note { flex: 1 1 16rem; font-size: .8rem; color: var(--app-muted); margin: 0; min-width: 0; }

        /* بطاقتا الصفّ الواحد بارتفاعٍ واحد */
        .pa-grid > [class*="col-"] { display: flex; }
        .pa-card { margin-bottom: var(--stk-4); flex: 1 1 auto; min-width: 0; }
        .pa-card > .card-header {
            display: flex; align-items: center; flex-wrap: wrap; gap: var(--stk-2);
            padding: var(--stk-3) var(--stk-4); border-bottom: 1px solid var(--stk-line-soft);
        }
        .pa-card__icon { color: var(--app-primary); font-size: .95rem; flex: none; }
        .pa-card__title { font-weight: 700; font-size: .92rem; color: var(--app-text); margin: 0; }
        .pa-card__edit { margin-inline-start: auto; font-size: .8rem; font-weight: 600; white-space: nowrap; }
        .pa-card > .card-body { padding: var(--stk-2) var(--stk-4) var(--stk-3); }

        .pa-list { margin: 0; padding: 0; list-style: none; }
        .pa-row {
            display: grid; grid-template-columns: minmax(0, 15rem) minmax(0, 1fr);
            align-items: center; gap: var(--stk-1) var(--stk-4);
            padding: .6rem 0; border-bottom: 1px solid var(--stk-line-soft);
        }
        .pa-list > li:last-child > .pa-row,
        .pa-sub > li:last-child > .pa-row { border-bottom: 0; }
        .pa-row__label {
            display: flex; align-items: center; gap: .3rem; min-width: 0;
            font-size: .83rem; font-weight: 600; color: var(--app-muted);
        }
        .pa-row__label .stk-tip { flex: none; margin: 0; }
        .pa-row__value { display: flex; align-items: center; flex-wrap: wrap; gap: .35rem; min-width: 0; font-size: .86rem; color: var(--app-text); }
        .pa-row__value .stk-state { font-variant-numeric: tabular-nums; }
        .pa-row__name { min-width: 0; overflow-wrap: anywhere; }
        .pa-row__fix { font-size: .78rem; font-weight: 600; white-space: nowrap; }

        /* أنواعُ الخصميات تحت «خصميات الموظفين» — كما في شجرة الحسابات */
        .pa-sub {
            list-style: none; margin: 0 0 .35rem; padding: 0;
            margin-inline-start: .55rem; padding-inline-start: var(--stk-4);
            border-inline-start: 2px solid var(--stk-line-soft);
        }
        .pa-sub .pa-row { padding: .45rem 0; grid-template-columns: minmax(0, calc(15rem - 1.2rem)) minmax(0, 1fr); }
        .pa-sub .pa-row__label { font-weight: 500; }
        .pa-sub .pa-row__label::before { content: '└'; color: var(--stk-line); margin-inline-end: .15rem; }

        @media (max-width: 575px) {
            .pa-row, .pa-sub .pa-row { grid-template-columns: 1fr; }
            .pa-summary { padding: var(--stk-3) var(--stk-4); }
        }
    </style>
@endpush

@section('content')
    @section('page-actions')
        <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> الإعدادات
        </a>
        @if (\App\Support\PayrollAccounts::canEdit())
            <a href="{{ \App\Support\PayrollAccounts::editUrl() }}" class="btn btn-primary">
                <i class="feather icon-external-link"></i> تعديل الربط في إعدادات الحسابات
            </a>
        @endif
    @endsection

    @include('stock::components.page-head', [
        'title' => 'حسابات الرواتب',
        'icon' => 'briefcase',
        'sub' => 'للقراءة — كلُّ حسابٍ هنا يُربط من «' . \App\Support\PayrollAccounts::SCREEN . '»',
        'crumbs' => ['الإعدادات' => route('SalarySittings.index'), 'حسابات الرواتب' => null],
    ])

    @php
        $canEdit = \App\Support\PayrollAccounts::canEdit();
    @endphp

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')

        @include('hr::components.guide', [
            'key' => 'hr-payroll-settings.accounts',
            'title' => 'على أيّ حساباتٍ تُقيَّد الرواتب والخصميات ونهاية الخدمة والتأمينات؟',
            'lead' => 'مصدرُ هذه الحسابات كلِّها واحد: «إعدادات الحسابات المحاسبية» (مجموعة «الرواتب والموظفين»). هذه الشاشة تعرضها مجمّعةً للقراءة، والتعديلُ هناك — ويسري على القيود فور حفظه.',
            'blocks' => [
                'عند صرف الراتب' => [
                    'مدين «مصروف رواتب الموظفين» / دائن ذمّة الموظف، ثمّ من الذمّة إلى الخزينة بالصافي — وحصّتا التأمينات على حساباتهما.',
                ],
                'عند إنشاء موظف' => [
                    'يُفتح له حسابُ مستحقات تحت أبِ المستحقات، وحسابُ سلفٍ تحت أبِ السلف. تغييرُ الأب لا ينقل الحسابات القائمة.',
                ],
                'عند اعتماد خصمية أو مكافأة أو نهاية خدمة' => [
                    'الخصمية: مدين ذمّة الموظف / دائن حساب نوعها — وحساباتُ الأنواع فروعٌ تحت «خصميات الموظفين»، وما لم يُربط لنوعه حسابٌ يُقيَّد على العام.',
                    'نهاية الخدمة: مدين المصروف / دائن المخصص عند الاعتماد، ثمّ من المخصص إلى ذمّة الموظف عند التسوية.',
                ],
            ],
            'facts' => [
                'لا حسابَ احتياطيّاً',
                'غيرُ المربوط يوقف العمليةَ برسالة',
            ],
        ])

        {{-- ═══════════ الخلاصة ═══════════ --}}
        <div class="card mb-2">
            <div class="pa-summary">
                <div class="pa-summary__item">
                    <span class="pa-summary__icon pa-summary__icon--on"><i class="feather icon-link"></i></span>
                    <div>
                        <p class="pa-summary__value">{{ $summary['linked'] }}</p>
                        <p class="pa-summary__label">مربوط من {{ $summary['total'] }}</p>
                    </div>
                </div>
                <div class="pa-summary__item">
                    <span class="pa-summary__icon {{ $summary['unlinked'] ? 'pa-summary__icon--off' : 'pa-summary__icon--on' }}">
                        <i class="feather icon-{{ $summary['unlinked'] ? 'alert-triangle' : 'check' }}"></i>
                    </span>
                    <div>
                        <p class="pa-summary__value">{{ $summary['unlinked'] }}</p>
                        <p class="pa-summary__label">غير مربوط</p>
                    </div>
                </div>
                <p class="pa-summary__note">
                    @if ($summary['unlinked'])
                        غيرُ المربوط يوقف العمليةَ التي تحتاجه برسالةٍ تسمّيه — إلا حساباتُ أنواع الخصميات: ما لم يُربط منها يُقيَّد على «خصميات الموظفين» العام.
                    @else
                        كلُّ حسابات الرواتب مربوطة.
                    @endif
                </p>
            </div>
        </div>

        {{-- ═══════════ الأقسام ═══════════ --}}
        <div class="row pa-grid">
            @foreach ($sections as $sectionId => $section)
                <div class="col-xl-6">
                    <div class="card pa-card" id="payroll-accounts-{{ $sectionId }}">
                        <div class="card-header">
                            <i class="feather icon-{{ $section['icon'] }} pa-card__icon"></i>
                            <h6 class="pa-card__title">{{ $section['title'] }}</h6>
                            @if ($section['unlinked'])
                                <span class="stk-state stk-state--warn">{{ $section['unlinked'] }} غير مربوط</span>
                            @else
                                <span class="stk-state stk-state--on">{{ $section['linked'] }} مربوط</span>
                            @endif
                            @if ($canEdit)
                                <a href="{{ \App\Support\PayrollAccounts::editUrl(array_key_first($section['keys'])) }}" class="pa-card__edit">
                                    <i class="feather icon-edit-2"></i> تعديل
                                </a>
                            @endif
                        </div>
                        <div class="card-body">
                            @php
                                $parents = collect($section['rows'])->reject(fn ($r) => $r['child']);
                                $children = collect($section['rows'])->filter(fn ($r) => $r['child']);
                            @endphp
                            <ul class="pa-list">
                                @foreach ($parents as $row)
                                    <li>
                                        @include('hr::salaries.sitting.partials.account-row', ['row' => $row, 'hint' => $section['keys'][$row['key']] ?? null, 'canEdit' => $canEdit])

                                        @if ($row['key'] === \App\Support\PayrollAccounts::DEDUCTIONS && $children->isNotEmpty())
                                            <ul class="pa-sub" aria-label="حسابات أنواع الخصميات">
                                                @foreach ($children as $child)
                                                    <li>
                                                        @include('hr::salaries.sitting.partials.account-row', ['row' => $child, 'hint' => $section['keys'][$child['key']] ?? null, 'canEdit' => $canEdit])
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- ═══════════ العمولات (من أهداف المبيعات) ═══════════ --}}
            <div class="col-xl-6">
                <div class="card pa-card" id="payroll-accounts-commission">
                    <div class="card-header">
                        <i class="feather icon-percent pa-card__icon"></i>
                        <h6 class="pa-card__title">العمولات</h6>
                        @if ($commissionAccount)
                            <span class="stk-state stk-state--on">1 مربوط</span>
                        @else
                            <span class="stk-state stk-state--warn">1 غير مربوط</span>
                        @endif
                        @permission('sales.target_settings.view')
                            <a href="{{ route('sales-targets.settings', 'sales') }}" class="pa-card__edit">
                                <i class="feather icon-edit-2"></i> تعديل في إعدادات الأهداف
                            </a>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <ul class="pa-list">
                            <li>
                                <div class="pa-row">
                                    <div class="pa-row__label">
                                        <span>حساب العمولة</span>
                                        <i class="feather icon-help-circle stk-tip" title="حسابُ العمولة يُضبط في «إعدادات أهداف المبيعات» — تسويةُ الهدف تقيّده مديناً مقابل الخزينة."></i>
                                    </div>
                                    <div class="pa-row__value">
                                        @if ($commissionAccount)
                                            <span class="stk-state stk-state--on">{{ $commissionAccount->code ?? '—' }}</span>
                                            <span class="pa-row__name">{{ $commissionAccount->name }}</span>
                                        @else
                                            <span class="badge badge-danger"><i class="feather icon-alert-triangle"></i> غير مربوط</span>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
