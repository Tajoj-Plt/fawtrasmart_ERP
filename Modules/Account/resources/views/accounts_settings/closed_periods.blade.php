@extends('account::master')

@section('title', 'الفترات المقفلة')

@section('content')
    <div class="content-body">

        {{--
            كانت هذه الشاشة صفَّين من البيانات الوهمية مكتوبَين في القالب —
            «الربع الأول» و«الربع الثاني» بتواريخ 2024 ثابتة — وأزرارُ التعديل
            والحذف `href="#"` ونموذجُ الفلترة `action=""`. تعرض الآن فترات
            النظام الحقيقية من `accounting_periods`، وإدارتها في شاشتها.
        --}}
        @include('account::components.page-head', [
            'title' => 'الفترات المقفلة',
            'icon' => 'lock',
            'sub' => 'الفترة المقفلة لا تُقبل فيها حركة محاسبية جديدة ولا تعديل على قيودها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات الحسابات' => route('accounts_settings.index'),
                'الفترات المقفلة' => null,
            ],
            {{-- الرابط يفتح `AccountingPeriods.index` (وحدة الإعدادات) التي تحرس
                 نفسها بـ`settings.accounting_periods.view` — نفس صلاحية هذه
                 الشاشة، فالفحص هنا احترازي لا تجاوزاً للحراسة الفعلية. --}}
            'actions' => (Route::has('AccountingPeriods.index') && hasPermission('settings.accounting_periods.view'))
                ? '<a href="' . route('AccountingPeriods.index') . '" class="btn btn-primary">'
                    . '<i class="feather icon-settings"></i> إدارة الفترات</a>'
                : '',
        ])

        @php
            $periods = $periods ?? collect();
            $closed = $periods->filter(fn ($p) => $p->status !== 'open');
        @endphp

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__label">إجمالي الفترات</span>
                <span class="stk-metric__value stk-num">{{ $periods->count() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-lock"></i></span>
                <span class="stk-metric__label">مقفلة</span>
                <span class="stk-metric__value stk-num">{{ $closed->count() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-unlock"></i></span>
                <span class="stk-metric__label">مفتوحة</span>
                <span class="stk-metric__value stk-num">{{ $periods->count() - $closed->count() }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['from_date', 'to_date', 'status']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('accounts_settings.closed_periods') }}">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="from_date">من تاريخ</label>
                                <input type="date" name="from_date" id="from_date" class="form-control"
                                    value="{{ request('from_date') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="to_date">إلى تاريخ</label>
                                <input type="date" name="to_date" id="to_date" class="form-control"
                                    value="{{ request('to_date') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('status') === '1')>مفتوحة</option>
                                    <option value="0" @selected(request('status') === '0')>مقفلة</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('accounts_settings.closed_periods') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            @if ($periods->isNotEmpty())
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> الفترات
                        <span class="stk-count">{{ $periods->count() }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>الفترة</th>
                                <th>السنة المالية</th>
                                <th class="text-center">من</th>
                                <th class="text-center">إلى</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center">تاريخ الإقفال</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($periods as $period)
                                <tr>
                                    <td>
                                        <span class="stk-media__title">{{ $period->name }}</span>
                                        @if ($period->notes)
                                            <small class="stk-media__sub d-block">{{ $period->notes }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $period->fiscalYear->name ?? '—' }}</td>
                                    <td class="text-center stk-num">{{ $period->start_date?->toDateString() }}</td>
                                    <td class="text-center stk-num">{{ $period->end_date?->toDateString() }}</td>
                                    <td class="text-center">
                                        @switch($period->status)
                                            @case('open')
                                                <span class="stk-state stk-state--on">مفتوحة</span>
                                                @break
                                            @case('locked')
                                                <span class="stk-state stk-state--off">مقفلة نهائياً</span>
                                                @break
                                            @default
                                                <span class="stk-state stk-state--neutral">مقفلة</span>
                                        @endswitch
                                    </td>
                                    <td class="text-center stk-num">
                                        {{ $period->closed_at?->format('Y-m-d') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="stk-table-foot">
                    <span class="stk-table-foot__count">
                        الإقفال وإعادة الفتح إجراءان منفصلان — من شاشة «الفترات المحاسبية»
                    </span>
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-lock"></i></span>
                    <h6 class="stk-empty__title">لا فترات محاسبية معرَّفة</h6>
                    <p class="stk-empty__hint">
                        عرِّف سنةً مالية أولاً ثم ولّد فتراتها — بلا فتراتٍ لا يمنع
                        النظامُ الكتابةَ في شهرٍ أُقفل.
                    </p>
                    @if (Route::has('AccountingPeriods.index') && hasPermission('settings.accounting_periods.view'))
                        <a href="{{ route('AccountingPeriods.index') }}" class="btn btn-primary mt-1">
                            <i class="feather icon-settings"></i> إدارة الفترات المحاسبية
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
