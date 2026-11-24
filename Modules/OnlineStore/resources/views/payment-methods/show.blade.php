@extends('onlinestore::master')

@section('title', 'تفاصيل طريقة الدفع - ' . $paymentMethod->name)

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('PaymentMethods.edit', $paymentMethod->id) }}" class="btn btn-primary">
                <i class="feather icon-edit"></i> تعديل من الإعدادات
            </a>
            <a href="{{ route('payment_methods.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => $paymentMethod->name,
            'icon' => 'credit-card',
            'sub' => 'عرضٌ فقط — الإدارة تتم من شاشة الإعدادات',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'طرق الدفع' => route('payment_methods.index'),
                $paymentMethod->name => null,
            ],
        ])

        <div class="row">
            {{-- البيانات الأساسية --}}
            <div class="col-md-8">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-info"></i> البيانات الأساسية</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>الاسم (عربي)</dt>
                                        <dd>{{ $paymentMethod->name }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الاسم (إنجليزي)</dt>
                                        <dd>{{ $paymentMethod->name_en ?? '-' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الكود</dt>
                                        <dd class="stk-num">{{ $paymentMethod->code ?? '-' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>النوع</dt>
                                        <dd><span class="badge badge-light-info">{{ $paymentMethod->type_label }}</span></dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>الحالة</dt>
                                        <dd>
                                            <span class="stk-state {{ $paymentMethod->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                                {{ $paymentMethod->is_active ? 'نشط' : 'متوقف' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>متاح أونلاين</dt>
                                        <dd>
                                            @if ($paymentMethod->is_online)
                                                <span class="badge badge-light-success">نعم</span>
                                            @else
                                                <span class="badge badge-light-secondary">لا</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>افتراضي</dt>
                                        <dd>
                                            @if ($paymentMethod->is_default)
                                                <span class="badge badge-light-primary"><i class="feather icon-star"></i> نعم</span>
                                            @else
                                                <span class="badge badge-light-secondary">لا</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>يتطلب بطاقة</dt>
                                        <dd>
                                            @if ($paymentMethod->requires_card)
                                                <span class="badge badge-light-warning">نعم</span>
                                            @else
                                                <span class="badge badge-light-secondary">لا</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        @if ($paymentMethod->description)
                            <hr>
                            <h6>الوصف</h6>
                            <p class="text-muted">{{ $paymentMethod->description }}</p>
                        @endif
                    </div>
                </div>

                {{-- الرسوم والحدود --}}
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-dollar-sign"></i> الرسوم والحدود</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-metrics">
                            <div class="stk-metric">
                                <span class="stk-metric__label">نوع الرسوم</span>
                                <span class="stk-metric__value" style="font-size:1rem;">{{ $paymentMethod->fee_type == 'percentage' ? 'نسبة مئوية' : 'مبلغ ثابت' }}</span>
                            </div>
                            <div class="stk-metric">
                                <span class="stk-metric__label">مبلغ الرسوم</span>
                                <span class="stk-metric__value" style="font-size:1rem;">
                                    @if ($paymentMethod->fee_amount > 0)
                                        {{ number_format($paymentMethod->fee_amount, 2) }}{{ $paymentMethod->fee_type == 'percentage' ? '%' : '' }}
                                    @else
                                        مجاني
                                    @endif
                                </span>
                            </div>
                            <div class="stk-metric">
                                <span class="stk-metric__label">الحد الأدنى</span>
                                <span class="stk-metric__value" style="font-size:1rem;">{{ $paymentMethod->min_amount ? number_format($paymentMethod->min_amount, 2) : '-' }}</span>
                            </div>
                            <div class="stk-metric">
                                <span class="stk-metric__label">الحد الأقصى</span>
                                <span class="stk-metric__value" style="font-size:1rem;">{{ $paymentMethod->max_amount ? number_format($paymentMethod->max_amount, 2) : '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- بيانات البنك (للتحويل البنكي) --}}
                @if ($paymentMethod->type == 'bank_transfer')
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-briefcase"></i> بيانات الحساب البنكي</h6>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>اسم البنك</dt>
                                    <dd>{{ $paymentMethod->bank_name ?? '-' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>اسم صاحب الحساب</dt>
                                    <dd>{{ $paymentMethod->bank_account_name ?? '-' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>رقم الحساب</dt>
                                    <dd class="stk-num">{{ $paymentMethod->bank_account_number ?? '-' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>رقم الآيبان</dt>
                                    <dd class="stk-num">{{ $paymentMethod->bank_iban ?? '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif

                {{-- التعليمات --}}
                @if ($paymentMethod->instructions)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> تعليمات الدفع</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-0">
                                {!! nl2br(e($paymentMethod->instructions)) !!}
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- الشريط الجانبي --}}
            <div class="col-md-4">
                {{-- الشعار --}}
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">الشعار</h6>
                    </div>
                    <div class="card-body text-center">
                        @if ($paymentMethod->logo)
                            <img src="{{ asset($paymentMethod->logo) }}" alt="{{ $paymentMethod->name }}"
                                class="img-fluid rounded" style="max-height: 150px;">
                        @else
                            <div class="avatar bg-light-primary mx-auto" style="width: 100px; height: 100px; font-size: 3rem;">
                                <span class="avatar-content"><i class="fa fa-{{ $paymentMethod->type_icon }}"></i></span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- معلومات إضافية --}}
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">معلومات إضافية</h6>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الترتيب</dt>
                                <dd class="stk-num">{{ $paymentMethod->sort_order }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإنشاء</dt>
                                <dd class="stk-num">{{ $paymentMethod->created_at->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>آخر تحديث</dt>
                                <dd class="stk-num">{{ $paymentMethod->updated_at->format('Y-m-d H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- ملاحظة --}}
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">الإجراءات</h6>
                    </div>
                    <div class="card-body">
                        <a href="{{ route('PaymentMethods.edit', $paymentMethod->id) }}" class="btn btn-primary btn-block mb-1">
                            <i class="feather icon-edit"></i> تعديل / حذف / تعيين كافتراضي
                        </a>
                        <p class="text-muted small mb-0">تُدار كل تعديلات طرق الدفع من شاشة الإعدادات لتفادي تعارض البيانات.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
