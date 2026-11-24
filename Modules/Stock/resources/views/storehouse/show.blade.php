@extends('stock::master')

@section('title', $storehouse->name)

@section('content')
    @php
        $states = [
            'active' => ['نشط', 'on'],
            'inactive' => ['غير نشط', 'off'],
            'stopped' => ['متوقف', 'off'],
        ];
        [$stateLabel, $stateTone] = $states[$storehouse->status] ?? ['غير محدد', 'off'];
        $isMain = $storehouse->major == 1;
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('stock.warehouses.edit'))
                <a href="{{ route('storehouse.edit', $storehouse->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif

            @if ($storehouse->status === 'active' && canAny(['stock.reports.summary_movement', 'stock.reports.value', 'stock.reports.inventory_sheet']))
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="feather icon-file-text"></i> التقارير
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        @if (hasPermission('stock.reports.summary_movement'))
                            <a class="dropdown-item" href="{{ route('storehouse.summary_inventory_operations', $storehouse->id) }}">
                                <i class="feather icon-activity"></i> ملخّص العمليات
                            </a>
                        @endif
                        @if (hasPermission('stock.reports.value'))
                            <a class="dropdown-item" href="{{ route('storehouse.inventory_value', $storehouse->id) }}">
                                <i class="feather icon-dollar-sign"></i> قيمة المخزون
                            </a>
                        @endif
                        @if (hasPermission('stock.reports.inventory_sheet'))
                            <a class="dropdown-item" href="{{ route('storehouse.inventory_sheet', $storehouse->id) }}">
                                <i class="feather icon-clipboard"></i> ورقة الجرد
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            {{-- كان زر الحذف رابطاً فارغاً (href="") فيعيد تحميل الصفحة --}}
            @if (hasPermission('stock.warehouses.delete') && ! $isMain)
                <a href="{{ route('storehouse.delete', $storehouse->id) }}"
                   class="btn btn-outline-danger"
                   onclick="return confirm('حذف المستودع «{{ addslashes($storehouse->name) }}» نهائياً؟')">
                    <i class="feather icon-trash-2"></i> حذف
                </a>
            @endif
        @endsection

        @include('stock::components.page-head', [
            'title' => $storehouse->name,
            'icon' => $isMain ? 'star' : 'home',
            'sub' => $storehouse->shipping_address ?: 'بلا عنوان شحن',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المستودعات' => route('storehouse.index'),
                $storehouse->name => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#home" role="tab">
                            <i class="feather icon-info"></i> المعلومات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#activate" role="tab">
                            <i class="feather icon-activity"></i> سجلّ النشاط
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content p-2">

                <div class="tab-pane active" id="home" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-file-text"></i> بيانات المستودع</h4>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>الاسم</dt>
                                    <dd>{{ $storehouse->name }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>عنوان الشحن</dt>
                                    <dd>{{ $storehouse->shipping_address ?: '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الحالة</dt>
                                    <dd><span class="stk-state stk-state--{{ $stateTone }}">{{ $stateLabel }}</span></dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>نوع المستودع</dt>
                                    <dd>
                                        <span class="badge badge-light-{{ $isMain ? 'success' : 'primary' }}">
                                            {{ $isMain ? 'رئيسي' : 'فرعي' }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الموظفون (مستودعهم الافتراضي)</dt>
                                    <dd>
                                        @if ($permissionsMeta['responsible_employees']->isNotEmpty())
                                            @foreach ($permissionsMeta['responsible_employees'] as $employee)
                                                <a href="{{ route('employee.show', $employee->id) }}" class="d-block">
                                                    {{ $employee->full_name }}
                                                </a>
                                            @endforeach
                                        @else
                                            <span class="text-muted">
                                                لا يوجد — يُعيَّن من
                                                <a href="{{ route('inventory_settings.employee_default_warehouse') }}">شاشة المستودع الافتراضي للموظف</a>
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                                {{-- المباني التي اتّخذت هذا المستودع مستودعاً لها (موديول العقارات، إن كان مفعّلاً) --}}
                                @php
                                    $linkedBuildings = class_exists(\Modules\RentalManagement\Models\Building::class) && Route::has('realestate.buildings.show') && hasPermission('realestate.buildings.view')
                                        ? \Modules\RentalManagement\Models\Building::where('store_house_id', $storehouse->id)->get(['id', 'name_ar'])
                                        : collect();
                                @endphp
                                @if ($linkedBuildings->isNotEmpty())
                                    <div class="stk-def__row">
                                        <dt>المباني المرتبطة</dt>
                                        <dd>
                                            @foreach ($linkedBuildings as $linkedBuilding)
                                                <a href="{{ route('realestate.buildings.show', $linkedBuilding->id) }}" class="d-block">
                                                    <i class="feather icon-home"></i> {{ $linkedBuilding->name_ar }}
                                                </a>
                                            @endforeach
                                        </dd>
                                    </div>
                                @endif
                                <div class="stk-def__row">
                                    <dt>صلاحية عرض المستودع</dt>
                                    <dd>{{ $permissionsMeta['permissions']['view'] }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>صلاحية تعديل المخزون</dt>
                                    <dd>{{ $permissionsMeta['permissions']['edit_stock'] }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>صلاحية إنشاء الفواتير</dt>
                                    <dd>{{ $permissionsMeta['permissions']['create_invoices'] }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {{-- الكميات والحسابات: كمية كل صنف وحركته، وقيمة المخزون المحسوبة مقارنةً برصيد حساب المستودع في شجرة الحسابات --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-link"></i> الحساب المحاسبي المرتبط</h4>
                        </div>
                        <div class="card-body">
                            @if ($accountingAccount)
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>اسم الحساب</dt>
                                        <dd>
                                            <a href="{{ route('account.showDetails', $accountingAccount->id) }}">
                                                {{ $accountingAccount->name }}
                                            </a>
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>رمز الحساب</dt>
                                        <dd class="stk-num">{{ $accountingAccount->code ?: '—' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>طبيعة الحساب</dt>
                                        <dd>{{ $accountingAccount->balance_type === 'debit' ? 'مدين' : 'دائن' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>رصيد الحساب في الشجرة</dt>
                                        <dd class="stk-num"><strong>@money($accountingBalance)</strong></dd>
                                    </div>
                                </dl>
                            @else
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-alert-triangle"></i></div>
                                    <h5 class="stk-empty__title">لا يوجد حساب محاسبي مرتبط بهذا المستودع</h5>
                                    <p class="stk-empty__hint">لن تُحتسب أي مقارنة بشجرة الحسابات حتى يُربط حساب</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="stk-metrics">
                        <div class="stk-metric">
                            <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                            <span class="stk-metric__value stk-num">{{ number_format(count($accountingItems)) }}</span>
                            <span class="stk-metric__label">عدد الأصناف المتحرِّكة</span>
                        </div>
                        <div class="stk-metric">
                            <span class="stk-metric__icon"><i class="feather icon-archive"></i></span>
                            <span class="stk-metric__value stk-num">{{ number_format($accountingTotals['current_quantity'], 2) }}</span>
                            <span class="stk-metric__label">إجمالي الكمية الحالية</span>
                        </div>
                        <div class="stk-metric">
                            <span class="stk-metric__icon"><i class="feather icon-box"></i></span>
                            <span class="stk-metric__value stk-num">@moneyPlain($accountingTotalStockValue)</span>
                            <span class="stk-metric__label">قيمة المخزون المحسوبة (كمية × متوسط تكلفة)</span>
                        </div>
                        <div class="stk-metric">
                            <span class="stk-metric__icon"><i class="feather icon-book"></i></span>
                            <span class="stk-metric__value stk-num">
                                {{ $accountingBalance !== null ? \App\Support\CurrencySettings::number($accountingBalance) : '—' }}
                            </span>
                            <span class="stk-metric__label">رصيد الحساب في الشجرة</span>
                        </div>
                        <div class="stk-metric">
                            <span class="stk-metric__icon"><i class="feather icon-{{ $accountingValuesMatch ? 'check-circle' : 'alert-triangle' }}"></i></span>
                            <span class="stk-metric__value stk-num">
                                {{ $accountingDifference !== null ? \App\Support\CurrencySettings::number($accountingDifference) : '—' }}
                            </span>
                            <span class="stk-metric__label">
                                الفرق —
                                <span class="stk-state stk-state--{{ $accountingBalance === null ? 'off' : ($accountingValuesMatch ? 'on' : 'off') }}">
                                    {{ $accountingBalance === null ? 'بلا حساب' : ($accountingValuesMatch ? 'متطابق' : 'غير متطابق') }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-list"></i> تفصيل الأصناف
                                <span class="stk-count">{{ count($accountingItems) }}</span>
                            </h6>
                        </div>

                        <div class="table-responsive stk-scroll">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>الصنف</th>
                                        <th class="text-center">الكمية الحالية</th>
                                        <th class="text-center">وارد يدوي</th>
                                        <th class="text-center">وارد افتتاحي</th>
                                        <th class="text-center">وارد تحويل</th>
                                        <th class="text-center">إجمالي الوارد</th>
                                        <th class="text-center">منصرف يدوي</th>
                                        <th class="text-center">منصرف تحويل</th>
                                        <th class="text-center">مباع (فواتير)</th>
                                        <th class="text-center">مرتجع مبيعات</th>
                                        <th class="text-center">إجمالي المسحوب</th>
                                        <th class="text-center">متوسط التكلفة</th>
                                        <th class="text-center">قيمة المخزون</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($accountingItems as $item)
                                        <tr>
                                            <td>
                                                <span class="stk-media__title">{{ $item['name'] }}</span>
                                                @if ($item['barcode'])
                                                    <div class="stk-help stk-num">{{ $item['barcode'] }}</div>
                                                @endif
                                            </td>
                                            <td class="text-center stk-num"><strong>{{ number_format($item['current_quantity'], 2) }}</strong></td>
                                            <td class="text-center stk-num">{{ number_format($item['incoming_manual'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($item['incoming_opening'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($item['incoming_transfer'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($item['incoming_total'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($item['outgoing_manual'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($item['outgoing_transfer'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($item['sold'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($item['sales_returns'], 2) }}</td>
                                            <td class="text-center stk-num"><strong>{{ number_format($item['withdrawn_total'], 2) }}</strong></td>
                                            <td class="text-center stk-num">@moneyPlain($item['unit_cost'])</td>
                                            <td class="text-center stk-num"><strong>@moneyPlain($item['stock_value'])</strong></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="13">
                                                <div class="stk-empty">
                                                    <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                                                    <h5 class="stk-empty__title">لا حركة مسجَّلة</h5>
                                                    <p class="stk-empty__hint">لم يمرّ أي صنفٍ بحركةٍ في هذا المستودع بعد</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if (count($accountingItems))
                                    <tfoot>
                                        <tr class="stk-total-row">
                                            <td>الإجمالي</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['current_quantity'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['incoming_manual'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['incoming_opening'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['incoming_transfer'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['incoming_total'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['outgoing_manual'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['outgoing_transfer'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['sold'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['sales_returns'], 2) }}</td>
                                            <td class="text-center stk-num">{{ number_format($accountingTotals['withdrawn_total'], 2) }}</td>
                                            <td class="text-center stk-num">—</td>
                                            <td class="text-center stk-num">@money($accountingTotalStockValue)</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                {{-- سجلّ النشاط مجمَّعٌ بالأيام كما يأتي من الكنترولر --}}
                <div class="tab-pane" id="activate" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            @if ($logs && count($logs) > 0)
                                <ul class="stk-timeline">
                                    @foreach ($logs as $date => $dayLogs)
                                        @php $day = \Illuminate\Support\Carbon::parse($date); @endphp

                                        <li class="stk-timeline__day">
                                            <span>{{ $day->translatedFormat('l، d F Y') }}</span>
                                        </li>

                                        @foreach ($dayLogs as $log)
                                            @continue (! $log)

                                            <li class="stk-timeline__item">
                                                <span class="stk-timeline__dot stk-timeline__dot--log">
                                                    <i class="feather icon-edit-3"></i>
                                                </span>

                                                <div class="stk-timeline__body">
                                                    <p class="stk-timeline__head">
                                                        <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                        <span class="stk-timeline__time stk-num">
                                                            <i class="feather icon-clock"></i>
                                                            {{ $log->created_at->format('H:i:s') }}
                                                        </span>
                                                    </p>

                                                    <div class="stk-timeline__text">
                                                        {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                                                    </div>

                                                    <p class="stk-timeline__meta">
                                                        <span>
                                                            <i class="feather icon-map-pin"></i>
                                                            {{ $log->user->branch->name ?? 'فرع غير معروف' }}
                                                        </span>
                                                    </p>
                                                </div>
                                            </li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            @else
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                                    <h5 class="stk-empty__title">لا نشاط مسجَّل</h5>
                                    <p class="stk-empty__hint">تظهر هنا كل عملية على هذا المستودع فور حدوثها</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
