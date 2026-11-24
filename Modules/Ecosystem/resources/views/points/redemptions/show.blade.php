@extends('ecosystem::master')

@section('title', 'تفاصيل طلب الاستبدال #' . $redemption->redemption_number)

@section('content')
    {{-- استيراد الأنماط الحديثة --}}
    @include('ecosystem::points.partials.points-modern-styles')

    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تفاصيل طلب الاستبدال</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.redemptions.index') }}">طلبات الاستبدال</a></li>
                            <li class="breadcrumb-item active">{{ $redemption->redemption_number }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <div class="pts-btn-group" style="display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap;">
                <a href="{{ route('ecosystem.points.redemptions.index') }}" class="pts-btn pts-btn-secondary">
                    <i class="feather icon-arrow-right"></i> رجوع
                </a>
                @if($redemption->isPending())
                    <form action="{{ route('ecosystem.points.redemptions.approve', $redemption) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="pts-btn pts-btn-success approve-btn">
                            <i class="feather icon-check"></i> موافقة
                        </button>
                    </form>
                @endif
                @if($redemption->isApproved() && !($redemption->reward && $redemption->reward->needsVoucherCode()))
                    <form action="{{ route('ecosystem.points.redemptions.process', $redemption) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="pts-btn pts-btn-primary">
                            <i class="feather icon-loader"></i> تجهيز
                        </button>
                    </form>
                @endif
                @php
                    // مكافأة الموظف النقدية تُسلَّم بصرف الراتب لا بزرّ «تسليم»
                    $rdmFulfillment = app(\App\Services\Rewards\EmployeeRewardFulfillment::class);
                    $rdmViaPayroll = $rdmFulfillment->isPayrollCash($redemption) && ! $rdmFulfillment->paidFromTreasuryLegacy($redemption);
                @endphp
                @if($rdmViaPayroll && in_array($redemption->status->value, ['approved', 'processing']))
                    <span class="pts-btn pts-btn-secondary" title="تظهر استحقاقاً لمرّةٍ واحدة على قسيمة راتب الموظف، وتصير «تم التسليم» عند صرف الراتب">
                        <i class="feather icon-briefcase"></i> بانتظار صرفة الراتب
                    </span>
                @endif
                @if(! $rdmViaPayroll && in_array($redemption->status->value, ['approved', 'processing']) && !($redemption->reward && $redemption->reward->needsVoucherCode()))
                    <form action="{{ route('ecosystem.points.redemptions.deliver', $redemption) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="pts-btn pts-btn-primary deliver-btn">
                            <i class="feather icon-package"></i> تسليم
                        </button>
                    </form>
                @endif
                @if($redemption->reward && $redemption->reward->needsVoucherCode() && in_array($redemption->status->value, ['approved', 'processing']) && !$redemption->voucher_used)
                    <a href="{{ route('ecosystem.points.redemptions.verify-voucher') }}" class="pts-btn pts-btn-primary">
                        <i class="feather icon-credit-card"></i> استخدام القسيمة
                    </a>
                @endif
                @if(!$redemption->isDelivered() && !$redemption->isCancelled())
                    <button type="button" class="pts-btn pts-btn-danger" id="cancelBtn">
                        <i class="feather icon-x-circle"></i> إلغاء
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- شريط الحالة الحديث --}}
        @php
            $steps = [
                ['key' => 'pending', 'label' => 'معلق', 'icon' => 'clock', 'color' => '#ff9f43'],
                ['key' => 'approved', 'label' => 'موافق عليه', 'icon' => 'check', 'color' => '#00cfe8'],
                ['key' => 'processing', 'label' => 'قيد التجهيز', 'icon' => 'loader', 'color' => '#7367f0'],
                ['key' => 'delivered', 'label' => 'تم التسليم', 'icon' => 'check-circle', 'color' => '#28c76f'],
            ];
            $statusOrder = ['pending' => 1, 'approved' => 2, 'processing' => 3, 'delivered' => 4, 'cancelled' => 0];
            $currentOrder = $statusOrder[$redemption->status->value] ?? 0;
            $isCancelled = $redemption->isCancelled();
        @endphp

        <div class="rdm-status-tracker mb-3">
            @if($isCancelled)
                <div class="rdm-cancelled-banner">
                    <div class="rdm-cancelled-icon">
                        <i class="feather icon-x-circle"></i>
                    </div>
                    <div>
                        <h5>تم إلغاء هذا الطلب</h5>
                        @if($redemption->cancellation_reason)
                            <p>السبب: {{ $redemption->cancellation_reason }}</p>
                        @endif
                    </div>
                </div>
            @else
                <div class="rdm-steps-container">
                    @foreach($steps as $index => $step)
                        @php
                            $stepOrder = $statusOrder[$step['key']];
                            $isCompleted = $currentOrder >= $stepOrder;
                            $isCurrent = $currentOrder == $stepOrder;
                        @endphp
                        <div class="rdm-step {{ $isCompleted ? 'completed' : '' }} {{ $isCurrent ? 'current' : '' }}">
                            <div class="rdm-step-dot" style="{{ $isCompleted ? 'background:' . $step['color'] . '; border-color:' . $step['color'] . ';' : '' }} {{ $isCurrent ? 'box-shadow: 0 0 0 6px ' . $step['color'] . '25;' : '' }}">
                                <i class="feather icon-{{ $step['icon'] }}" style="{{ $isCompleted ? 'color: #fff;' : '' }}"></i>
                            </div>
                            <span class="rdm-step-label" style="{{ $isCompleted ? 'color:' . $step['color'] . '; font-weight: 600;' : '' }}">{{ $step['label'] }}</span>
                        </div>
                        @if(!$loop->last)
                            <div class="rdm-step-line" style="background: {{ $currentOrder > $stepOrder ? 'linear-gradient(90deg, ' . $step['color'] . ', ' . $steps[$index + 1]['color'] . ')' : '#e9ecef' }};"></div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- تنبيه للخصومات والقسائم: التسليم يتم من صفحة التحقق --}}
        @if($redemption->reward && $redemption->reward->needsVoucherCode() && !$redemption->voucher_used && !$redemption->isCancelled() && $redemption->isApproved())
        <div class="rdm-voucher-notice mb-3">
            <div class="d-flex align-items-center" style="gap: 12px;">
                <div class="rdm-voucher-notice-icon">
                    <i class="feather icon-info"></i>
                </div>
                <div style="flex: 1;">
                    <h6 class="mb-1" style="color: #7367f0; font-weight: 600;">هذا الطلب من نوع الخصم/القسيمة</h6>
                    <p class="mb-0" style="color: #82868b; font-size: 0.9rem;">
                        التسليم والقيد المحاسبي يتم تلقائياً عند استخدام كود القسيمة وتحديد مبلغ الفاتورة من
                        <a href="{{ route('ecosystem.points.redemptions.verify-voucher') }}" style="color: #7367f0; font-weight: 600; text-decoration: underline;">صفحة التحقق من القسائم</a>.
                        @if($redemption->reward->needsInvoiceAmount())
                            <br><small class="text-warning"><i class="feather icon-alert-circle"></i> يجب تحديد مبلغ الفاتورة لحساب نسبة الخصم ({{ $redemption->reward->reward_value }}%) مع حد أقصى {{ $redemption->reward->max_discount_value ? number_format($redemption->reward->max_discount_value, 2) . ' ريال' : 'غير محدد' }}.</small>
                        @endif
                    </p>
                </div>
                @if($redemption->voucher_code)
                <div class="text-center" style="min-width: 180px;">
                    <small class="d-block text-muted mb-1">كود القسيمة</small>
                    <code class="px-3 py-2 rounded d-block" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%); color: #fff; font-size: 1.1rem; letter-spacing: 2px;">{{ $redemption->voucher_code }}</code>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- بطاقات الإحصائيات --}}
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-hash"></i></div>
                <div class="stat-label">رقم الطلب</div>
                <div class="stat-value" style="font-size: 1.25rem;">{{ $redemption->redemption_number }}</div>
                <div class="stat-trend">
                    <i class="feather icon-calendar"></i>
                    <span>{{ $redemption->created_at->format('Y/m/d') }}</span>
                </div>
            </div>

            <div class="stat-card-modern {{ $redemption->isCancelled() ? 'danger' : ($redemption->isDelivered() ? 'success' : 'warning') }}">
                <div class="stat-icon"><i class="feather icon-{{ $redemption->isCancelled() ? 'x-circle' : ($redemption->isDelivered() ? 'check-circle' : 'clock') }}"></i></div>
                <div class="stat-label">الحالة</div>
                <div class="stat-value" style="font-size: 1.25rem;">{{ $redemption->status->label() }}</div>
                <div class="stat-trend">
                    <i class="feather icon-info"></i>
                    <span>{{ $redemption->status->value }}</span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-star"></i></div>
                <div class="stat-label">النقاط المستخدمة</div>
                <div class="stat-value">{{ number_format($redemption->points_used ?? 0) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-minus-circle"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-package"></i></div>
                <div class="stat-label">الكمية</div>
                <div class="stat-value">{{ $redemption->quantity }}</div>
                <div class="stat-trend">
                    @if($redemption->reward_value)
                        @if($redemption->reward && $redemption->reward->reward_type->value === 'discount_percentage')
                            <i class="feather icon-percent"></i>
                            <span>{{ number_format($redemption->reward_value, 2) }}%</span>
                        @else
                            <i class="feather icon-dollar-sign"></i>
                            <span>{{ number_format($redemption->reward_value, 2) }}</span>
                        @endif
                    @else
                        <i class="feather icon-gift"></i>
                        <span>مكافأة</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- البطاقة الرئيسية: المكافأة + المستفيد + التسلسل الزمني --}}
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <div class="row align-items-start">
                    {{-- المكافأة --}}
                    <div class="col-md-3 text-center" style="border-left: 1px solid #f0f0f0;">
                        @if($redemption->reward)
                            @if($redemption->reward->image)
                                <img src="{{ asset('uploads/rewards/' . $redemption->reward->image) }}"
                                     alt="{{ $redemption->reward->name }}"
                                     class="rounded-circle shadow mb-2"
                                     style="width: 100px; height: 100px; object-fit: cover;">
                            @else
                                <div class="rounded-circle shadow d-flex align-items-center justify-content-center mx-auto mb-2"
                                     style="width: 100px; height: 100px; background: linear-gradient(135deg, #7367f0 0%, #ce9ffc 100%); font-size: 2.5rem; color: #fff;">
                                    <i class="feather icon-gift"></i>
                                </div>
                            @endif
                            <h4 class="mb-1 font-weight-bold">
                                <a href="{{ route('ecosystem.points.rewards.show', $redemption->reward) }}" style="color: #5e5873;">
                                    {{ $redemption->reward->name }}
                                </a>
                            </h4>
                            <span class="pts-badge pts-badge-primary">
                                {{ $redemption->reward->reward_type->icon() }} {{ $redemption->reward->reward_type->label() }}
                            </span>
                            <div class="mt-1">
                                <span class="badge badge-pill px-3 py-2 shadow-sm" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%); color: #fff; font-size: 0.9rem;">
                                    <i class="feather icon-star mr-1"></i> {{ number_format($redemption->reward->points_required) }} نقطة
                                </span>
                            </div>
                        @else
                            <div class="rounded-circle shadow d-flex align-items-center justify-content-center mx-auto mb-2"
                                 style="width: 100px; height: 100px; background: #f0f0f0; font-size: 2.5rem; color: #ccc;">
                                <i class="feather icon-alert-triangle"></i>
                            </div>
                            <h5 class="text-muted">المكافأة محذوفة</h5>
                        @endif
                    </div>

                    {{-- تفاصيل الطلب --}}
                    <div class="col-md-5">
                        <div class="rdm-details-list">
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-user mr-1"></i> المستفيد</span>
                                <strong>
                                    @if($redemption->user_type->value === 'employee' && $redemption->employee)
                                        {{ $redemption->employee->full_name }}
                                    @elseif($redemption->client)
                                        {{ $redemption->client->full_name }}
                                    @else
                                        غير معروف
                                    @endif
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-users mr-1"></i> نوع المستفيد</span>
                                <span class="pts-badge pts-badge-{{ $redemption->user_type->value === 'employee' ? 'info' : 'primary' }}">
                                    {{ $redemption->user_type->label() }}
                                </span>
                            </div>
                            @if($redemption->branch)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-map-pin mr-1"></i> الفرع</span>
                                <strong>{{ $redemption->branch->name }}</strong>
                            </div>
                            @endif
                            @if($redemption->delivery_method)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-truck mr-1"></i> طريقة التسليم</span>
                                <strong>{{ $redemption->delivery_method->label() }}</strong>
                            </div>
                            @endif
                            @if($redemption->delivery_address)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-home mr-1"></i> عنوان التسليم</span>
                                <strong>{{ $redemption->delivery_address }}</strong>
                            </div>
                            @endif
                            @if($redemption->voucher_code)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-tag mr-1"></i> كود القسيمة</span>
                                <div>
                                    <code class="px-2 py-1 rounded" style="background: #f0f0f0; font-size: 1rem; letter-spacing: 1px;">{{ $redemption->voucher_code }}</code>
                                    @if($redemption->voucher_status)
                                        <span class="pts-badge pts-badge-{{ $redemption->voucher_status_color }}" style="font-size: 0.7rem;">{{ $redemption->voucher_status_label }}</span>
                                    @elseif($redemption->voucher_used)
                                        <span class="pts-badge pts-badge-success" style="font-size: 0.7rem;">مستخدم</span>
                                    @else
                                        <span class="pts-badge pts-badge-warning" style="font-size: 0.7rem;">غير مستخدم</span>
                                    @endif
                                </div>
                            </div>
                            @if($redemption->voucher_serial)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-hash mr-1"></i> الرقم التسلسلي</span>
                                <code class="px-2 py-1 rounded" style="background: #f0f0f0;">{{ $redemption->voucher_serial }}</code>
                            </div>
                            @endif
                            @if($redemption->voucher_pin)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-lock mr-1"></i> رمز PIN</span>
                                <code class="px-2 py-1 rounded" style="background: #fff3cd; color: #856404;">{{ $redemption->voucher_pin }}</code>
                            </div>
                            @endif
                            @if($redemption->voucher_expires_at)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-calendar mr-1"></i> صلاحية القسيمة</span>
                                <span class="{{ $redemption->voucher_expires_at->isPast() ? 'text-danger' : 'text-success' }}">
                                    {{ $redemption->voucher_expires_at->format('Y/m/d') }}
                                    @if($redemption->voucher_expires_at->isPast())
                                        <small>(منتهية)</small>
                                    @else
                                        <small>({{ $redemption->voucher_expires_at->diffForHumans() }})</small>
                                    @endif
                                </span>
                            </div>
                            @endif
                            @if($redemption->applied_to_amount)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-file-text mr-1"></i> مبلغ الفاتورة</span>
                                <strong>{{ number_format($redemption->applied_to_amount, 2) }} ريال</strong>
                            </div>
                            @endif
                            @if($redemption->calculated_discount)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-percent mr-1"></i> قيمة الخصم المحسوبة</span>
                                <strong class="text-success">{{ number_format($redemption->calculated_discount, 2) }} ريال</strong>
                            </div>
                            @endif
                            @if($redemption->used_in_invoice_id)
                            <div class="d-flex justify-content-between py-2">
                                <span class="text-muted"><i class="feather icon-file mr-1"></i> رقم الفاتورة</span>
                                <strong>#{{ $redemption->used_in_invoice_id }}</strong>
                            </div>
                            @endif
                            @endif
                        </div>
                    </div>

                    {{-- التسلسل الزمني --}}
                    <div class="col-md-4">
                        <div class="rdm-timeline-mini">
                            <h6 class="text-muted mb-3"><i class="feather icon-clock mr-1"></i> التسلسل الزمني</h6>

                            <div class="rdm-tl-item">
                                <div class="rdm-tl-dot" style="background: #ff9f43;"></div>
                                <div class="rdm-tl-content">
                                    <span class="rdm-tl-title">إنشاء الطلب</span>
                                    <span class="rdm-tl-date">{{ $redemption->created_at->format('Y/m/d H:i') }}</span>
                                </div>
                            </div>

                            @if($redemption->approved_at)
                            <div class="rdm-tl-item">
                                <div class="rdm-tl-dot" style="background: #00cfe8;"></div>
                                <div class="rdm-tl-content">
                                    <span class="rdm-tl-title">الموافقة</span>
                                    <span class="rdm-tl-date">{{ $redemption->approved_at->format('Y/m/d H:i') }}</span>
                                    @if($redemption->approver)
                                        <span class="rdm-tl-by">{{ $redemption->approver->name }}</span>
                                    @endif
                                </div>
                            </div>
                            @endif

                            @if($redemption->processed_at)
                            <div class="rdm-tl-item">
                                <div class="rdm-tl-dot" style="background: #7367f0;"></div>
                                <div class="rdm-tl-content">
                                    <span class="rdm-tl-title">قيد التجهيز</span>
                                    <span class="rdm-tl-date">{{ $redemption->processed_at->format('Y/m/d H:i') }}</span>
                                    @if($redemption->processor)
                                        <span class="rdm-tl-by">{{ $redemption->processor->name }}</span>
                                    @endif
                                </div>
                            </div>
                            @endif

                            @if($redemption->delivered_at)
                            <div class="rdm-tl-item">
                                <div class="rdm-tl-dot" style="background: #28c76f;"></div>
                                <div class="rdm-tl-content">
                                    <span class="rdm-tl-title">تم التسليم</span>
                                    <span class="rdm-tl-date">{{ $redemption->delivered_at->format('Y/m/d H:i') }}</span>
                                </div>
                            </div>
                            @endif

                            @if($redemption->cancelled_at)
                            <div class="rdm-tl-item">
                                <div class="rdm-tl-dot" style="background: #ea5455;"></div>
                                <div class="rdm-tl-content">
                                    <span class="rdm-tl-title">تم الإلغاء</span>
                                    <span class="rdm-tl-date">{{ $redemption->cancelled_at->format('Y/m/d H:i') }}</span>
                                    @if($redemption->cancellation_reason)
                                        <span class="rdm-tl-reason">{{ $redemption->cancellation_reason }}</span>
                                    @endif
                                </div>
                            </div>
                            @endif

                            @if($redemption->expires_at)
                            <div class="rdm-tl-item">
                                <div class="rdm-tl-dot" style="background: {{ $redemption->expires_at->isPast() ? '#ea5455' : '#82868b' }};"></div>
                                <div class="rdm-tl-content">
                                    <span class="rdm-tl-title">انتهاء الصلاحية</span>
                                    <span class="rdm-tl-date {{ $redemption->expires_at->isPast() ? 'text-danger' : '' }}">{{ $redemption->expires_at->format('Y/m/d H:i') }}</span>
                                    @if($redemption->expires_at->isPast())
                                        <span class="pts-badge pts-badge-danger" style="font-size: 0.65rem;">منتهية</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ملاحظات --}}
        @if($redemption->notes)
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="feather icon-message-square mr-1"></i> ملاحظات</h6>
                <p class="mb-0" style="color: #5e5873;">{{ $redemption->notes }}</p>
            </div>
        </div>
        @endif

        {{-- التبويبات: معاملة النقاط + القيود المحاسبية --}}
        @php
            $hasAccountingEntries = isset($redemption->metadata['journal_entry_id']) 
                || isset($redemption->metadata['liability_journal_entry_id'])
                || isset($redemption->metadata['settlement_journal_entry_id'])
                || isset($redemption->metadata['voucher_liability_journal_entry_id'])
                || isset($redemption->metadata['voucher_settlement_journal_entry_id'])
                || isset($redemption->metadata['voucher_usage_journal_entry_id']);
        @endphp
        @if($redemption->transaction || $hasAccountingEntries)
        <div class="card table-card-modern">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs pts-nav-tabs nav-fill" role="tablist">
                    @if($redemption->transaction)
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#points-tab" role="tab">
                            <i class="feather icon-star"></i> معاملة النقاط
                        </a>
                    </li>
                    @endif
                    @if($hasAccountingEntries)
                    <li class="nav-item">
                        <a class="nav-link {{ !$redemption->transaction ? 'active' : '' }}" data-toggle="tab" href="#accounting-tab" role="tab">
                            <i class="feather icon-book-open"></i> القيود المحاسبية
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    {{-- معاملة النقاط --}}
                    @if($redemption->transaction)
                    <div class="tab-pane fade show active" id="points-tab" role="tabpanel">
                        <div class="stats-row" style="margin-bottom: 0;">
                            <div class="stat-card-modern danger" style="flex: 1;">
                                <div class="stat-icon"><i class="feather icon-minus-circle"></i></div>
                                <div class="stat-label">النقاط المخصومة</div>
                                <div class="stat-value">{{ number_format(abs($redemption->transaction->points)) }}</div>
                                <div class="stat-trend"><i class="feather icon-star"></i> <span>نقطة</span></div>
                            </div>
                            <div class="stat-card-modern info" style="flex: 1;">
                                <div class="stat-icon"><i class="feather icon-arrow-right-circle"></i></div>
                                <div class="stat-label">الرصيد قبل</div>
                                <div class="stat-value">{{ number_format($redemption->transaction->balance_before) }}</div>
                                <div class="stat-trend"><i class="feather icon-database"></i> <span>نقطة</span></div>
                            </div>
                            <div class="stat-card-modern success" style="flex: 1;">
                                <div class="stat-icon"><i class="feather icon-arrow-left-circle"></i></div>
                                <div class="stat-label">الرصيد بعد</div>
                                <div class="stat-value">{{ number_format($redemption->transaction->balance_after) }}</div>
                                <div class="stat-trend"><i class="feather icon-database"></i> <span>نقطة</span></div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- القيود المحاسبية --}}
                    @if($hasAccountingEntries)
                    <div class="tab-pane fade {{ !$redemption->transaction ? 'show active' : '' }}" id="accounting-tab" role="tabpanel">
                        
                        {{-- 1. قيد الصرف المباشر (للنقدية فقط) --}}
                        @if(isset($redemption->metadata['journal_entry_id']))
                        @php
                            $journalEntry = \App\Models\JournalEntry::with('details.account')->find($redemption->metadata['journal_entry_id']);
                        @endphp
                        @if($journalEntry)
                            <div class="accounting-entry-section mb-4">
                                <div class="d-flex align-items-center mb-3 flex-wrap" style="gap: 1rem;">
                                    <span class="pts-badge pts-badge-success">
                                        <i class="feather icon-check-circle"></i> قيد صرف مباشر
                                    </span>
                                    <span class="pts-badge pts-badge-primary">
                                        <i class="feather icon-hash"></i> {{ $journalEntry->reference_number }}
                                    </span>
                                    <span class="pts-badge pts-badge-secondary">
                                        <i class="feather icon-calendar"></i> {{ $journalEntry->date->format('Y/m/d H:i') }}
                                    </span>
                                </div>
                                <p class="text-muted small mb-2">{{ $journalEntry->description }}</p>
                                <div class="table-responsive">
                                    <table class="pts-table">
                                        <thead>
                                            <tr>
                                                <th>الحساب</th>
                                                <th>البيان</th>
                                                <th>مدين</th>
                                                <th>دائن</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($journalEntry->details as $detail)
                                            <tr>
                                                <td><strong>{{ $detail->account ? $detail->account->name : 'N/A' }}</strong></td>
                                                <td>{{ $detail->description }}</td>
                                                <td class="{{ $detail->debit > 0 ? 'text-danger font-weight-bold' : '' }}">
                                                    {{ $detail->debit > 0 ? number_format($detail->debit, 2) : '-' }}
                                                </td>
                                                <td class="{{ $detail->credit > 0 ? 'text-success font-weight-bold' : '' }}">
                                                    {{ $detail->credit > 0 ? number_format($detail->credit, 2) : '-' }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                        @endif

                        {{-- 2. قيد الالتزام (عند الموافقة) --}}
                        @php
                            $liabilityEntryId = $redemption->metadata['liability_journal_entry_id'] 
                                ?? $redemption->metadata['voucher_liability_journal_entry_id'] 
                                ?? null;
                        @endphp
                        @if($liabilityEntryId)
                        @php
                            $liabilityEntry = \App\Models\JournalEntry::with('details.account')->find($liabilityEntryId);
                        @endphp
                        @if($liabilityEntry)
                            <div class="accounting-entry-section mb-4">
                                <div class="d-flex align-items-center mb-3 flex-wrap" style="gap: 1rem;">
                                    <span class="pts-badge pts-badge-warning">
                                        <i class="feather icon-clock"></i> قيد الالتزام (عند الموافقة)
                                    </span>
                                    <span class="pts-badge pts-badge-primary">
                                        <i class="feather icon-hash"></i> {{ $liabilityEntry->reference_number }}
                                    </span>
                                    <span class="pts-badge pts-badge-secondary">
                                        <i class="feather icon-calendar"></i> {{ $liabilityEntry->date->format('Y/m/d H:i') }}
                                    </span>
                                </div>
                                <p class="text-muted small mb-2">{{ $liabilityEntry->description }}</p>
                                <div class="table-responsive">
                                    <table class="pts-table">
                                        <thead>
                                            <tr>
                                                <th>الحساب</th>
                                                <th>البيان</th>
                                                <th>مدين</th>
                                                <th>دائن</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($liabilityEntry->details as $detail)
                                            <tr>
                                                <td><strong>{{ $detail->account ? $detail->account->name : 'N/A' }}</strong></td>
                                                <td>{{ $detail->description }}</td>
                                                <td class="{{ $detail->debit > 0 ? 'text-danger font-weight-bold' : '' }}">
                                                    {{ $detail->debit > 0 ? number_format($detail->debit, 2) : '-' }}
                                                </td>
                                                <td class="{{ $detail->credit > 0 ? 'text-success font-weight-bold' : '' }}">
                                                    {{ $detail->credit > 0 ? number_format($detail->credit, 2) : '-' }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                        @endif

                        {{-- 3. قيد تسديد الالتزام (عند التسليم) --}}
                        @php
                            $settlementEntryId = $redemption->metadata['settlement_journal_entry_id'] 
                                ?? $redemption->metadata['voucher_settlement_journal_entry_id'] 
                                ?? null;
                        @endphp
                        @if($settlementEntryId)
                        @php
                            $settlementEntry = \App\Models\JournalEntry::with('details.account')->find($settlementEntryId);
                        @endphp
                        @if($settlementEntry)
                            <div class="accounting-entry-section mb-4">
                                <div class="d-flex align-items-center mb-3 flex-wrap" style="gap: 1rem;">
                                    <span class="pts-badge pts-badge-success">
                                        <i class="feather icon-check-circle"></i> قيد تسديد الالتزام (عند التسليم)
                                    </span>
                                    <span class="pts-badge pts-badge-primary">
                                        <i class="feather icon-hash"></i> {{ $settlementEntry->reference_number }}
                                    </span>
                                    <span class="pts-badge pts-badge-secondary">
                                        <i class="feather icon-calendar"></i> {{ $settlementEntry->date->format('Y/m/d H:i') }}
                                    </span>
                                </div>
                                <p class="text-muted small mb-2">{{ $settlementEntry->description }}</p>
                                <div class="table-responsive">
                                    <table class="pts-table">
                                        <thead>
                                            <tr>
                                                <th>الحساب</th>
                                                <th>البيان</th>
                                                <th>مدين</th>
                                                <th>دائن</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($settlementEntry->details as $detail)
                                            <tr>
                                                <td><strong>{{ $detail->account ? $detail->account->name : 'N/A' }}</strong></td>
                                                <td>{{ $detail->description }}</td>
                                                <td class="{{ $detail->debit > 0 ? 'text-danger font-weight-bold' : '' }}">
                                                    {{ $detail->debit > 0 ? number_format($detail->debit, 2) : '-' }}
                                                </td>
                                                <td class="{{ $detail->credit > 0 ? 'text-success font-weight-bold' : '' }}">
                                                    {{ $detail->credit > 0 ? number_format($detail->credit, 2) : '-' }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                        @endif

                        {{-- 4. قيد استخدام القسيمة/الخصم (عند تطبيق الكود على فاتورة) --}}
                        @if(isset($redemption->metadata['voucher_usage_journal_entry_id']))
                        @php
                            $voucherUsageEntry = \App\Models\JournalEntry::with('details.account')->find($redemption->metadata['voucher_usage_journal_entry_id']);
                        @endphp
                        @if($voucherUsageEntry)
                            <div class="accounting-entry-section mb-4">
                                <div class="d-flex align-items-center mb-3 flex-wrap" style="gap: 1rem;">
                                    <span class="pts-badge pts-badge-info">
                                        <i class="feather icon-credit-card"></i> قيد استخدام القسيمة
                                    </span>
                                    <span class="pts-badge pts-badge-primary">
                                        <i class="feather icon-hash"></i> {{ $voucherUsageEntry->reference_number }}
                                    </span>
                                    <span class="pts-badge pts-badge-secondary">
                                        <i class="feather icon-calendar"></i> {{ $voucherUsageEntry->date->format('Y/m/d H:i') }}
                                    </span>
                                </div>
                                <p class="text-muted small mb-2">{{ $voucherUsageEntry->description }}</p>
                                @if($redemption->applied_to_amount)
                                <div class="d-flex mb-2 flex-wrap" style="gap: 8px;">
                                    <span class="pts-badge pts-badge-secondary">
                                        <i class="feather icon-file-text"></i> مبلغ الفاتورة: {{ number_format($redemption->applied_to_amount, 2) }} ريال
                                    </span>
                                    <span class="pts-badge pts-badge-success">
                                        <i class="feather icon-percent"></i> الخصم المحسوب: {{ number_format($redemption->calculated_discount, 2) }} ريال
                                    </span>
                                    <span class="pts-badge pts-badge-primary">
                                        <i class="feather icon-shopping-cart"></i> المبلغ بعد الخصم: {{ number_format($redemption->applied_to_amount - $redemption->calculated_discount, 2) }} ريال
                                    </span>
                                </div>
                                @endif
                                <div class="table-responsive">
                                    <table class="pts-table">
                                        <thead>
                                            <tr>
                                                <th>الحساب</th>
                                                <th>البيان</th>
                                                <th>مدين</th>
                                                <th>دائن</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($voucherUsageEntry->details as $detail)
                                            <tr>
                                                <td><strong>{{ $detail->account ? $detail->account->name : 'N/A' }}</strong></td>
                                                <td>{{ $detail->description }}</td>
                                                <td class="{{ $detail->debit > 0 ? 'text-danger font-weight-bold' : '' }}">
                                                    {{ $detail->debit > 0 ? number_format($detail->debit, 2) : '-' }}
                                                </td>
                                                <td class="{{ $detail->credit > 0 ? 'text-success font-weight-bold' : '' }}">
                                                    {{ $detail->credit > 0 ? number_format($detail->credit, 2) : '-' }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                        @endif

                        {{-- 5. القيود العكسية (عند الإلغاء) --}}
                        @if(isset($redemption->metadata['reverse_journal_entry_ids']) && is_array($redemption->metadata['reverse_journal_entry_ids']))
                            @foreach($redemption->metadata['reverse_journal_entry_ids'] as $reverseId)
                            @php
                                $reverseEntry = \App\Models\JournalEntry::with('details.account')->find($reverseId);
                            @endphp
                            @if($reverseEntry)
                                <div class="accounting-entry-section mb-4">
                                    <div class="d-flex align-items-center mb-3 flex-wrap" style="gap: 1rem;">
                                        <span class="pts-badge pts-badge-danger">
                                            <i class="feather icon-rotate-ccw"></i> قيد عكسي (إلغاء)
                                        </span>
                                        <span class="pts-badge pts-badge-primary">
                                            <i class="feather icon-hash"></i> {{ $reverseEntry->reference_number }}
                                        </span>
                                        <span class="pts-badge pts-badge-secondary">
                                            <i class="feather icon-calendar"></i> {{ $reverseEntry->date->format('Y/m/d H:i') }}
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-2">{{ $reverseEntry->description }}</p>
                                    <div class="table-responsive">
                                        <table class="pts-table">
                                            <thead>
                                                <tr>
                                                    <th>الحساب</th>
                                                    <th>البيان</th>
                                                    <th>مدين</th>
                                                    <th>دائن</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($reverseEntry->details as $detail)
                                                <tr>
                                                    <td><strong>{{ $detail->account ? $detail->account->name : 'N/A' }}</strong></td>
                                                    <td>{{ $detail->description }}</td>
                                                    <td class="{{ $detail->debit > 0 ? 'text-danger font-weight-bold' : '' }}">
                                                        {{ $detail->debit > 0 ? number_format($detail->debit, 2) : '-' }}
                                                    </td>
                                                    <td class="{{ $detail->credit > 0 ? 'text-success font-weight-bold' : '' }}">
                                                        {{ $detail->credit > 0 ? number_format($detail->credit, 2) : '-' }}
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                            @endforeach
                        @endif

                        {{-- للتوافق مع القيود القديمة --}}
                        @if(isset($redemption->metadata['reverse_journal_entry_id']))
                            @php
                                $reverseEntry = \App\Models\JournalEntry::with('details.account')->find($redemption->metadata['reverse_journal_entry_id']);
                            @endphp
                            @if($reverseEntry)
                                <div class="accounting-entry-section mb-4">
                                    <div class="d-flex align-items-center mb-3 flex-wrap" style="gap: 1rem;">
                                        <span class="pts-badge pts-badge-danger">
                                            <i class="feather icon-rotate-ccw"></i> قيد عكسي (إلغاء)
                                        </span>
                                        <span class="pts-badge pts-badge-primary">
                                            <i class="feather icon-hash"></i> {{ $reverseEntry->reference_number }}
                                        </span>
                                        <span class="pts-badge pts-badge-secondary">
                                            <i class="feather icon-calendar"></i> {{ $reverseEntry->date->format('Y/m/d H:i') }}
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-2">{{ $reverseEntry->description }}</p>
                                    <div class="table-responsive">
                                        <table class="pts-table">
                                            <thead>
                                                <tr>
                                                    <th>الحساب</th>
                                                    <th>البيان</th>
                                                    <th>مدين</th>
                                                    <th>دائن</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($reverseEntry->details as $detail)
                                                <tr>
                                                    <td><strong>{{ $detail->account ? $detail->account->name : 'N/A' }}</strong></td>
                                                    <td>{{ $detail->description }}</td>
                                                    <td class="{{ $detail->debit > 0 ? 'text-danger font-weight-bold' : '' }}">
                                                        {{ $detail->debit > 0 ? number_format($detail->debit, 2) : '-' }}
                                                    </td>
                                                    <td class="{{ $detail->credit > 0 ? 'text-success font-weight-bold' : '' }}">
                                                        {{ $detail->credit > 0 ? number_format($detail->credit, 2) : '-' }}
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Modal إلغاء الطلب --}}
    <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
                <form action="{{ route('ecosystem.points.redemptions.cancel', $redemption) }}" method="POST">
                    @csrf
                    <div class="modal-header" style="background: linear-gradient(135deg, #ea5455 0%, #f08182 100%); color: #fff; border: none;">
                        <h5 class="modal-title">
                            <i class="feather icon-x-circle"></i> إلغاء طلب الاستبدال
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem;">
                        <div class="rdm-cancel-warning">
                            <i class="feather icon-alert-triangle"></i>
                            <span>سيتم إلغاء الطلب <strong>{{ $redemption->redemption_number }}</strong> واسترجاع النقاط المخصومة.</span>
                        </div>
                        <div class="form-group mt-3">
                            <label for="cancellation_reason" class="font-weight-bold">سبب الإلغاء <span class="text-danger">*</span></label>
                            <textarea name="cancellation_reason" id="cancellation_reason"
                                      class="form-control" rows="3" required
                                      placeholder="أدخل سبب الإلغاء..." style="border-radius: 10px;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border: none;">
                        <button type="button" class="pts-btn pts-btn-secondary" data-dismiss="modal">تراجع</button>
                        <button type="submit" class="pts-btn pts-btn-danger">
                            <i class="feather icon-x-circle"></i> تأكيد الإلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
    /* ===================================
       Redemption Show - Modern Design
       =================================== */
    .rdm-status-tracker {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.06);
        border: 1px solid #f0f0f0;
    }
    .rdm-steps-container {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0;
    }
    .rdm-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        min-width: 100px;
    }
    .rdm-step-dot {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #f0f0f0;
        border: 3px solid #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: #b4b7bd;
        transition: all 0.3s ease;
    }
    .rdm-step-label {
        font-size: 0.85rem;
        color: #b4b7bd;
        font-weight: 500;
    }
    .rdm-step-line {
        flex: 1;
        height: 3px;
        background: #e9ecef;
        border-radius: 3px;
        margin-top: -28px;
        min-width: 40px;
    }
    .rdm-cancelled-banner {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        background: linear-gradient(135deg, rgba(234, 84, 85, 0.08) 0%, rgba(234, 84, 85, 0.03) 100%);
        border: 1px solid rgba(234, 84, 85, 0.2);
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
    }
    .rdm-cancelled-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #ea5455 0%, #f08182 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.75rem;
        flex-shrink: 0;
    }
    .rdm-cancelled-banner h5 {
        margin: 0 0 0.25rem;
        color: #ea5455;
        font-weight: 600;
    }
    .rdm-cancelled-banner p {
        margin: 0;
        color: #82868b;
        font-size: 0.9rem;
    }

    /* Timeline Mini */
    .rdm-timeline-mini {
        position: relative;
    }
    .rdm-tl-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 1rem;
        position: relative;
        padding-right: 5px;
    }
    .rdm-tl-item:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 10px;
        top: 22px;
        bottom: -12px;
        width: 2px;
        background: #e9ecef;
    }
    .rdm-tl-dot {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 2px;
        z-index: 1;
    }
    .rdm-tl-content {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .rdm-tl-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #5e5873;
    }
    .rdm-tl-date {
        font-size: 0.8rem;
        color: #b4b7bd;
    }
    .rdm-tl-by {
        font-size: 0.75rem;
        color: #7367f0;
    }
    .rdm-tl-reason {
        font-size: 0.75rem;
        color: #ea5455;
    }

    /* Cancel Warning */
    .rdm-cancel-warning {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 159, 67, 0.1);
        border: 1px solid rgba(255, 159, 67, 0.3);
        border-radius: 10px;
        padding: 1rem 1.25rem;
        color: #ff9f43;
        font-size: 0.9rem;
    }
    .rdm-cancel-warning i {
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    /* Voucher Notice */
    .rdm-voucher-notice {
        background: linear-gradient(135deg, rgba(115, 103, 240, 0.08) 0%, rgba(115, 103, 240, 0.03) 100%);
        border: 1px solid rgba(115, 103, 240, 0.25);
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 4px 15px rgba(115, 103, 240, 0.08);
    }
    .rdm-voucher-notice-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        .rdm-steps-container {
            flex-wrap: wrap;
            gap: 10px;
        }
        .rdm-step-line {
            display: none;
        }
        .rdm-step {
            min-width: 70px;
        }
        .rdm-step-dot {
            width: 38px;
            height: 38px;
            font-size: 0.9rem;
        }
    }
    </style>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#cancelBtn').on('click', function() {
        $('#cancelModal').modal('show');
    });

    $('.approve-btn').on('click', function(e) {
        e.preventDefault();
        const form = $(this).closest('form');
        Swal.fire({
            title: 'الموافقة على الطلب؟',
            text: 'سيتم إنشاء القيد المحاسبي عند الموافقة',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28c76f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، وافق',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });

    $('.deliver-btn').on('click', function(e) {
        e.preventDefault();
        const form = $(this).closest('form');
        Swal.fire({
            title: 'تسليم المكافأة؟',
            text: 'تأكيد تسليم المكافأة للمستفيد',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، تسليم',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@endpush
