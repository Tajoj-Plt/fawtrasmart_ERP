@extends('onlinestore::master')

@section('title', 'تفاصيل المخزن')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('stock.warehouses.edit'))
                <a href="{{ route('store_storehouses.edit', $storehouse->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('store_storehouses.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل المخزن',
            'icon' => 'package',
            'sub' => $storehouse->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'المخازن' => route('store_storehouses.index'),
                $storehouse->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">رصيد المخزن</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($storehouse->account->balance ?? 0) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $storehouse->major == 1 ? 'star' : 'package' }}"></i></span>
                <span class="stk-metric__label">نوع المخزن</span>
                <span class="stk-metric__value">{{ $storehouse->major == 1 ? 'رئيسي' : 'فرعي' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-hash"></i></span>
                <span class="stk-metric__label">كود الحساب</span>
                <span class="stk-metric__value stk-num">{{ $storehouse->account->code ?? '-' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $storehouse->status == 'active' ? 'check-circle' : 'x-circle' }}"></i></span>
                <span class="stk-metric__label">حالة المخزن</span>
                <span class="stk-metric__value">
                    {{ $storehouse->status == 'active' ? 'نشط' : ($storehouse->status == 'inactive' ? 'غير نشط' : 'متوقف') }}
                </span>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات المخزن</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>اسم المخزن</dt>
                                <dd><strong>{{ $storehouse->name }}</strong></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>نوع المخزن</dt>
                                <dd>
                                    @if ($storehouse->major == 1)
                                        <span class="badge badge-light-warning">
                                            <i class="feather icon-star"></i> رئيسي
                                        </span>
                                    @else
                                        <span class="badge badge-light-secondary">فرعي</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الفرع</dt>
                                <dd>{{ $storehouse->branch->name ?? 'غير محدد' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>المسند إليهم</dt>
                                <dd>
                                    @forelse ($assignees ?? [] as $assignee)
                                        <span class="badge badge-light-primary">{{ $assignee['name'] }}</span>
                                    @empty
                                        <span class="text-muted">غير مسند — يراه من يرى كل مخازن المتجر فقط</span>
                                    @endforelse
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>عنوان الشحن</dt>
                                <dd>{{ $storehouse->shipping_address ?: 'غير محدد' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الحالة</dt>
                                <dd>
                                    <span class="stk-state {{ $storehouse->status == 'active' ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $storehouse->status == 'active' ? 'نشط' : ($storehouse->status == 'inactive' ? 'غير نشط' : 'متوقف') }}
                                    </span>
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>كود الحساب</dt>
                                <dd class="stk-num">{{ $storehouse->account->code ?? '-' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإضافة</dt>
                                <dd class="stk-num">{{ $storehouse->created_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>آخر تحديث</dt>
                                <dd class="stk-num">{{ $storehouse->updated_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- الحساب المحاسبي المرتبط: يقارن قيمة المخزون المحسوبة (كمية × متوسط تكلفة) برصيد حساب المخزن في شجرة الحسابات --}}
        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-link"></i> الحساب المحاسبي المرتبط</h6>
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
                            <dd class="stk-num"><strong>{{ \App\Support\CurrencySettings::format($accountingBalance) }}</strong></dd>
                        </div>
                    </dl>
                @else
                    <div class="stk-empty">
                        <span class="stk-empty__icon"><i class="feather icon-alert-triangle"></i></span>
                        <h6 class="stk-empty__title">لا يوجد حساب محاسبي مرتبط بهذا المخزن</h6>
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
                <span class="stk-metric__value stk-num">{{ \App\Support\CurrencySettings::format($accountingTotalStockValue) }}</span>
                <span class="stk-metric__label">قيمة المخزون المحسوبة (كمية × متوسط تكلفة)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-book"></i></span>
                <span class="stk-metric__value stk-num">
                    {{ $accountingBalance !== null ? \App\Support\CurrencySettings::format($accountingBalance) : '—' }}
                </span>
                <span class="stk-metric__label">رصيد الحساب في الشجرة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $accountingValuesMatch ? 'check-circle' : 'alert-triangle' }}"></i></span>
                <span class="stk-metric__value stk-num">
                    {{ $accountingDifference !== null ? \App\Support\CurrencySettings::format($accountingDifference) : '—' }}
                </span>
                <span class="stk-metric__label">
                    الفرق —
                    <span class="stk-state {{ $accountingBalance === null ? 'stk-state--off' : ($accountingValuesMatch ? 'stk-state--on' : 'stk-state--off') }}">
                        {{ $accountingBalance === null ? 'بلا حساب' : ($accountingValuesMatch ? 'متطابق' : 'غير متطابق') }}
                    </span>
                </span>
            </div>
        </div>

        <div class="card">
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
                            <th class="text-center">متوسط التكلفة ({{ \App\Support\CurrencySettings::code() }})</th>
                            <th class="text-center">قيمة المخزون ({{ \App\Support\CurrencySettings::code() }})</th>
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
                                <td class="text-center stk-num">{{ number_format($item['unit_cost'], 2) }}</td>
                                <td class="text-center stk-num"><strong>{{ number_format($item['stock_value'], 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13">
                                    <div class="stk-empty">
                                        <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                                        <h5 class="stk-empty__title">لا حركة مسجَّلة</h5>
                                        <p class="stk-empty__hint">لم يمرّ أي صنفٍ بحركةٍ في هذا المخزن بعد</p>
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
                                <td class="text-center stk-num">{{ number_format($accountingTotalStockValue, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-zap"></i> إجراءات سريعة</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    @if (hasPermission('stock.warehouses.view'))
                        <div class="col-md-3 col-6 mb-3">
                            <a href="{{ route('store_storehouses.summary-inventory-operations', $storehouse->id) }}"
                               class="btn btn-outline-primary btn-block py-3">
                                <i class="feather icon-bar-chart-2 d-block mb-1" style="font-size: 1.5rem;"></i>
                                ملخص العمليات
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="{{ route('store_storehouses.inventory-value', $storehouse->id) }}"
                               class="btn btn-outline-success btn-block py-3">
                                <i class="feather icon-dollar-sign d-block mb-1" style="font-size: 1.5rem;"></i>
                                قيمة المخزون
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="{{ route('store_storehouses.inventory-sheet', $storehouse->id) }}"
                               class="btn btn-outline-info btn-block py-3">
                                <i class="feather icon-file-text d-block mb-1" style="font-size: 1.5rem;"></i>
                                ورقة الجرد
                            </a>
                        </div>
                    @endif
                </div>

                @if ($storehouse->major == 0 && hasPermission('stock.warehouses.delete'))
                    <hr>
                    <div class="text-center">
                        <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                            <i class="feather icon-trash-2"></i> حذف المخزن
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-activity"></i> سجل النشاطات</h6>
            </div>
            <div class="card-body">
                @if (isset($logs) && count($logs) > 0)
                    @foreach ($logs as $date => $dayLogs)
                        <div class="stk-section__head">
                            <i class="feather icon-calendar"></i>
                            <h6 class="stk-section__title">{{ $date }}</h6>
                        </div>

                        <div class="stk-timeline mb-4">
                            @foreach ($dayLogs as $log)
                                <div class="stk-timeline__item">
                                    <span class="stk-timeline__dot stk-timeline__dot--log">
                                        <i class="feather icon-activity"></i>
                                    </span>
                                    <div class="stk-timeline__body">
                                        <div class="stk-timeline__text">{{ $log->description }}</div>
                                        <div class="stk-timeline__meta">
                                            <span class="stk-timeline__time">{{ $log->created_at->format('H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @else
                    <div class="stk-empty">
                        <span class="stk-empty__icon"><i class="feather icon-activity"></i></span>
                        <h6 class="stk-empty__title">لا توجد نشاطات مسجلة</h6>
                        <p class="stk-empty__hint">لم تُسجَّل أي عملية على هذا المخزن بعد.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($storehouse->major == 0 && hasPermission('stock.warehouses.delete'))
        <form id="delete-form" action="{{ route('store_storehouses.destroy', $storehouse->id) }}" method="POST" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection

@section('scripts')
<script>
    function confirmDelete() {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف المخزن "{{ $storehouse->name }}" نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form').submit();
            }
        });
    }
</script>
@endsection
