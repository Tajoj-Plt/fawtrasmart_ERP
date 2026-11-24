@extends('rentalmanagement::realestate.master')

@section('title', $client->display_name)

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        // المبلغ بعملته: `RealEstateMoney` إن وُجد، وإلّا رقمٌ بعملة النظام
        $money = fn ($v, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
            ? \Modules\RentalManagement\Support\RealEstateMoney::format($v, $currency)
            : number_format((float) $v, 2).' '.(\App\Support\CurrencySettings::code() ?? '');
        $canMoney = canAny(['realestate.payments.view', 'realestate.reports.financial']);
        $showPayments = hasPermission('realestate.payments.view') && $payments->isNotEmpty();
        $roles = $relationships->pluck('roles')->flatten()->filter()->unique()->values();
        // ملفّ العميل في ERP يُفتح بصلاحيات قسم العملاء لا بصلاحيات العقارات
        $canErp = Route::has('clients.show') && canAny(['clients_view_all_clients', 'clients_view_own_clients', 'clients_view_by_employee_groups', 'clients_view_by_daily_itinerary']);

        $kpis = [
            ['label' => 'الاهتمامات', 'value' => number_format($relationships->count()), 'icon' => 'link', 'tone' => 'info'],
            ['label' => 'العقود', 'value' => number_format($contracts->count()), 'icon' => 'file-text', 'tone' => 'primary'],
        ];
        if ($canMoney) {
            $kpis[] = ['label' => 'المستحق غير المسدَّد', 'value' => $money($outstanding), 'icon' => 'alert-circle', 'tone' => $outstanding > 0 ? 'danger' : 'success'];
        }
        if (hasPermission('realestate.payments.view')) {
            $kpis[] = ['label' => 'الدفعات', 'value' => number_format($payments->count()), 'icon' => 'credit-card', 'tone' => 'success', 'note' => $payments->count() >= 30 ? 'آخر 30 دفعة' : null];
        }

        $tabs = [['relationships', 'link', 'الاهتمامات', $relationships->count()], ['contracts', 'file-text', 'العقود', $contracts->count()]];
        if ($showPayments) {
            $tabs[] = ['payments', 'credit-card', 'المدفوعات', $payments->count()];
        }
        $tabs[] = ['viewings', 'eye', 'المعاينات', $viewings->count()];
        $tabs[] = ['tasks', 'check-square', 'الأنشطة', $tasks->count()];
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if ($canErp)
                <a href="{{ route('clients.show', $client->id) }}" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="feather icon-external-link"></i> فتح ملف العميل في ERP</a>
            @endif
            @permission('realestate.contracts.create')
                <a href="{{ route('realestate.contracts.create', ['client_id' => $client->id]) }}" class="btn btn-primary"><i class="feather icon-file-plus"></i> عقد جديد</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => $client->display_name,
            'icon' => 'user',
            'sub' => collect([$client->code, $client->phone])->filter()->implode(' · ') ?: 'ملف العميل العقاري',
            'crumbs' => ['الرئيسية' => url('/'), 'اهتمامات العملاء' => route('realestate.relationships.index'), $client->display_name => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="stk-metrics mb-2">
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-user"></i></span><span class="stk-metric__value">{{ $client->display_name }}</span><span class="stk-metric__label">العميل</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-hash"></i></span><span class="stk-metric__value stk-num">{{ $client->code ?: '—' }}</span><span class="stk-metric__label">الرمز</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-phone"></i></span><span class="stk-metric__value stk-num" dir="ltr">{{ $client->mobile ?: ($client->phone ?: '—') }}</span><span class="stk-metric__label">الجوال</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-award"></i></span>
                <span class="stk-metric__value">
                    @forelse ($roles as $role)
                        <span class="badge badge-light-{{ V::tone(V::RELATIONSHIP_ROLES, $role) }}">{{ V::label(V::RELATIONSHIP_ROLES, $role) }}</span>
                    @empty
                        —
                    @endforelse
                </span>
                <span class="stk-metric__label">الأدوار العقارية</span></div>
        </div>

        @if ($profile)
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-credit-card"></i> البيانات الشخصية والهوية</h4></div>
                <div class="card-body">
                    <dl class="stk-def mb-0">
                        @foreach ([
                            $profile->id_type_label => $profile->id_number,
                            'انتهاء الهوية' => $profile->id_expiry_date?->format('Y-m-d'),
                            'الجنسية' => $profile->nationality,
                            'تاريخ الميلاد' => $client->birth_date ? \Illuminate\Support\Carbon::parse($client->birth_date)->format('Y-m-d') : null,
                            'الحالة الاجتماعية' => $profile->marital_status ? V::label(V::MARITAL_STATUSES, $profile->marital_status) : null,
                            'المهنة' => $profile->job_title,
                            'جهة العمل' => $profile->employer,
                            'البريد الإلكتروني' => $client->email,
                            'العنوان' => collect([$client->city, $client->region, $client->street1])->filter()->implode('، '),
                            'جهة اتصال الطوارئ' => collect([$profile->emergency_name, $profile->emergency_relation, $profile->emergency_phone])->filter()->implode(' · '),
                        ] as $label => $value)
                            @if (filled($value))
                                <div class="stk-def__row"><dt>{{ $label }}</dt><dd>{{ $value }}@if ($label === 'انتهاء الهوية' && $profile->idExpired()) <span class="badge badge-light-danger">منتهية</span>@endif</dd></div>
                            @endif
                        @endforeach
                    </dl>
                </div>
            </div>
        @endif

        @include('rentalmanagement::realestate.components.kpis', ['items' => $kpis])

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    @foreach ($tabs as $i => [$id, $icon, $label, $count])
                        <li class="nav-item">
                            <a class="nav-link {{ $i === 0 ? 'active' : '' }}" data-toggle="tab" data-bs-toggle="tab" href="#tab-{{ $id }}" role="tab"><i class="feather icon-{{ $icon }}"></i> {{ $label }} <span class="stk-count">{{ $count }}</span></a>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content pt-2">
                    <div class="tab-pane active" id="tab-relationships" role="tabpanel">
                        @if ($relationships->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'link', 'title' => 'لا علاقات مسجّلة', 'hint' => 'يظهر العميل هنا لأن له عقوداً عقارية'])
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>نوع العلاقة</th><th>الوحدة / العقار</th><th>العرض</th><th>العقد</th><th class="text-center">الحالة</th><th>آخر نشاط</th><th>ملاحظات</th></tr></thead>
                                    <tbody>
                                        @foreach ($relationships as $relationship)
                                            @php $place = $relationship->unit?->building ?? $relationship->building; @endphp
                                            <tr>
                                                <td>
                                                    <div class="stk-tags">
                                                        @foreach ((array) $relationship->roles as $role)
                                                            <span class="badge badge-light-{{ V::tone(V::RELATIONSHIP_ROLES, $role) }}">{{ V::label(V::RELATIONSHIP_ROLES, $role) }}</span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($relationship->unit) {{ $relationship->unit->type_label }} {{ $relationship->unit->unit_no }} <small class="text-muted d-block">{{ $place->name ?? '—' }}</small>
                                                    @else {{ $place->name ?? '—' }} @endif
                                                </td>
                                                <td class="stk-num">
                                                    @if ($relationship->listing && hasPermission('realestate.listings.show'))
                                                        <a href="{{ route('realestate.listings.show', $relationship->listing->id) }}">{{ $relationship->listing->code }}</a>
                                                    @else
                                                        {{ $relationship->listing->code ?? '—' }}
                                                    @endif
                                                </td>
                                                <td class="stk-num">
                                                    @if ($relationship->contract && hasPermission('realestate.contracts.show'))
                                                        <a href="{{ route('realestate.contracts.show', $relationship->contract->id) }}">{{ $relationship->contract->code }}</a>
                                                    @else
                                                        {{ $relationship->contract->code ?? '—' }}
                                                    @endif
                                                </td>
                                                <td class="text-center"><span class="badge badge-light-{{ $relationship->status_meta['tone'] }}">{{ $relationship->status_meta['label'] }}</span></td>
                                                <td>{{ $relationship->last_activity_at?->diffForHumans() ?? '—' }}</td>
                                                <td>{{ \Illuminate\Support\Str::limit((string) $relationship->notes, 60) ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="tab-contracts" role="tabpanel">
                        @if ($contracts->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'file-text', 'title' => 'لا عقود', 'hint' => 'لم يُبرم مع هذا العميل عقدٌ عقاري بعد'])
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>العقد</th><th>صفة العميل</th><th>الوحدة</th><th>الفترة</th><th class="text-end">القيمة السنوية</th><th class="text-center">الحالة</th></tr></thead>
                                    <tbody>
                                        @foreach ($contracts as $contract)
                                            <tr>
                                                <td class="stk-num">
                                                    @if (hasPermission('realestate.contracts.show'))
                                                        <a href="{{ route('realestate.contracts.show', $contract->id) }}">{{ $contract->code }}</a>
                                                    @else
                                                        {{ $contract->code }}
                                                    @endif
                                                    <small class="text-muted d-block">{{ $contract->type_label }}</small>
                                                </td>
                                                <td>{{ (int) $contract->tenant_client_id === (int) $client->id ? V::label(V::RELATIONSHIP_ROLES, 'tenant') : V::label(V::RELATIONSHIP_ROLES, 'owner') }}</td>
                                                <td>{{ $contract->unit?->full_label ?? '—' }}</td>
                                                <td class="stk-num">{{ $contract->start_date?->format('Y-m-d') ?? '—' }} ← {{ $contract->end_date?->format('Y-m-d') ?? '—' }}</td>
                                                <td class="text-end re-num">{{ $money($contract->annual_value, $contract->currency) }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $contract->status_meta['tone'] }}">{{ $contract->status_meta['label'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if ($showPayments)
                        <div class="tab-pane" id="tab-payments" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>الدفعة</th><th>التاريخ</th><th>العقد</th><th>طريقة الدفع</th><th class="text-end">المبلغ</th><th class="text-center">الترحيل</th></tr></thead>
                                    <tbody>
                                        @foreach ($payments as $payment)
                                            <tr>
                                                <td class="stk-num">
                                                    @if (hasPermission('realestate.payments.show'))
                                                        <a href="{{ route('realestate.payments.show', $payment->id) }}">{{ $payment->code }}</a>
                                                    @else
                                                        {{ $payment->code }}
                                                    @endif
                                                </td>
                                                <td class="stk-num">{{ $payment->payment_date?->format('Y-m-d') ?? '—' }}</td>
                                                <td class="stk-num">{{ $payment->contract->code ?? '—' }}</td>
                                                <td>{{ $payment->method_label }}</td>
                                                <td class="text-end re-num">{{ $money($payment->amount, $payment->currency) }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $payment->status_meta['tone'] }}">{{ $payment->status_meta['label'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="tab-pane" id="tab-viewings" role="tabpanel">
                        @if ($viewings->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'eye', 'title' => 'لا معاينات', 'hint' => 'لم يُسجَّل لهذا العميل استفسارٌ أو معاينة'])
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>العرض</th><th>الوحدة</th><th class="text-center">النوع</th><th>الموعد</th><th class="text-center">الحالة</th><th>ملاحظات</th></tr></thead>
                                    <tbody>
                                        @foreach ($viewings as $viewing)
                                            <tr>
                                                <td>
                                                    @if ($viewing->listing && hasPermission('realestate.listings.show'))
                                                        <a href="{{ route('realestate.listings.show', $viewing->listing->id) }}">{{ $viewing->listing->title }}</a>
                                                    @else
                                                        {{ $viewing->listing->title ?? '—' }}
                                                    @endif
                                                    <small class="text-muted d-block stk-num">{{ $viewing->listing->code ?? '' }}</small>
                                                </td>
                                                <td>{{ $viewing->unit?->full_label ?? '—' }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $viewing->kind === 'viewing' ? 'primary' : 'info' }}">{{ $viewing->kind === 'viewing' ? V::label(V::ACTIVITY_TYPES, 'viewing') : 'استفسار' }}</span></td>
                                                <td class="stk-num">{{ $viewing->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $viewing->status_meta['tone'] }}">{{ $viewing->status_meta['label'] }}</span></td>
                                                <td>{{ \Illuminate\Support\Str::limit((string) $viewing->notes, 60) ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="tab-tasks" role="tabpanel">
                        @if ($tasks->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'check-square', 'title' => 'لا أنشطة', 'hint' => 'لا مهام ولا متابعات مرتبطة بهذا العميل'])
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>المهمة</th><th>النشاط</th><th>مرتبطة بـ</th><th class="text-center">الأولوية</th><th>الاستحقاق</th><th class="text-center">الحالة</th></tr></thead>
                                    <tbody>
                                        @foreach ($tasks as $task)
                                            <tr>
                                                <td>{{ $task->title }}</td>
                                                <td><i class="feather icon-{{ V::ACTIVITY_TYPES[$task->activity_type]['icon'] ?? 'activity' }}"></i> {{ $task->type_label }}</td>
                                                <td>{{ $task->related_label ?? '—' }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $task->priority_meta['tone'] }}">{{ $task->priority_meta['label'] }}</span></td>
                                                <td class="stk-num {{ $task->isOverdue() ? 'text-danger' : '' }}">{{ $task->due_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $task->status_meta['tone'] }}">{{ $task->status_meta['label'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @permission('realestate.tasks.view')
                                <p class="mt-1 mb-0"><a href="{{ route('realestate.tasks.index', ['client_id' => $client->id]) }}">كل مهام العميل <i class="feather icon-arrow-left"></i></a></p>
                            @endpermission
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
