@extends('hr::master')

@section('title', 'إعدادات العمولات')

@section('content')
    @section('page-actions')
        <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> الإعدادات
        </a>
        @permission('sales.target_settings.view')
            <a href="{{ route('sales-targets.settings', 'sales') }}" class="btn btn-primary">
                <i class="feather icon-external-link"></i> تعديلها في إعدادات الأهداف
            </a>
        @endpermission
    @endsection

    @include('stock::components.page-head', [
        'title' => 'إعدادات العمولات',
        'icon' => 'percent',
        'crumbs' => ['الإعدادات' => route('SalarySittings.index'), 'إعدادات العمولات' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('hr::components.guide', [
            'key' => 'hr-commission-settings',
            'title' => 'من أين تأتي العمولات، ومن يصرفها؟',
            'lead' => 'العمولاتُ لا تُحسب في الموارد البشرية: مصدرُها أهدافُ المبيعات وشرائحُها، وتُصرف بتسوية الهدف. وهذه الشاشة تعرض إعداداتِ ذلك الصرف كما هي هناك.',
            'blocks' => [
                'الاحتساب' => [
                    'الإنجاز = صافي مبيعات الموظف في الفترة (بطريقة الاحتساب المضبوطة)، والعمولة = الإنجاز × نسبة أعلى شريحةٍ بلغها — من المحقَّق لا من الهدف.',
                ],
                'الصرف' => [
                    'تسويةُ الهدف تكتب لكل موظف سندَ صرفٍ وقيداً: مدين حساب العمولة / دائن الخزينة، ثم تظهر في «العمولات المصروفة».',
                    'ولا تُصرف عمولةٌ إن لم يكفِ رصيدُ الخزينة — يُستثنى الموظفُ ويبقى مستحقّاً حتى تُغذّى الخزينة. ولا تُسوّى فترةٌ مرّتين للموظف نفسه.',
                ],
            ],
            'facts' => [
                'لا نظامَ أهدافٍ في الموارد البشرية',
                'لا صرفَ يجعل الخزينة سالبة',
            ],
        ])

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-target"></i> إعدادات أهداف المبيعات</h6>
                <span class="stk-state {{ $targetSetting->enabled ? 'stk-state--on' : 'stk-state--off' }}">
                    {{ $targetSetting->enabled ? 'مفعّلة' : 'موقوفة' }}
                </span>
            </div>
            <div class="card-body">
                <dl class="stk-def">
                    <div class="stk-def__row">
                        <dt>حساب العمولة</dt>
                        <dd>
                            @if ($commissionAccount)
                                {{ $commissionAccount->code }} — {{ $commissionAccount->name }}
                            @else
                                <span class="text-danger"><i class="feather icon-alert-triangle"></i> غير مضبوط — لا تُسوّى عمولة</span>
                            @endif
                        </dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>خزينة الصرف الافتراضية</dt>
                        <dd>
                            @if ($commissionTreasury)
                                {{ $commissionTreasury->name }}
                                <span class="text-muted">(الرصيد المعروض {{ \App\Support\CurrencySettings::format($commissionTreasury->balance) }})</span>
                            @else
                                <span class="text-warning">غير مضبوطة</span>
                            @endif
                        </dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>الصرف من خزينة الموظف</dt>
                        <dd>{{ $targetSetting->pay_from_employee_treasury ? 'نعم — وإلّا فالافتراضية' : 'لا — من الخزينة الافتراضية' }}</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>طريقة احتساب الإنجاز</dt>
                        <dd>{{ ['net_sales' => 'صافي المبيعات', 'gross_sales' => 'إجمالي الفواتير', 'paid_only' => 'الفواتير المدفوعة'][$targetSetting->calculation_method] ?? $targetSetting->calculation_method }}</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>قيد الصرف يتطلب اعتماداً</dt>
                        <dd>{{ $targetSetting->require_approval ? 'نعم — يُكتب القيد بانتظار الاعتماد' : 'لا — يُعتمد القيد فوراً' }}</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>التسوية التلقائية</dt>
                        <dd>{{ $targetSetting->auto_settlement_enabled ? 'مفعّلة ('.($targetSetting->settlement_frequency ?: '—').')' : 'يدوية من شاشة الهدف' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
@endsection
