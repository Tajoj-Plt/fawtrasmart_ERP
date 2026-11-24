@extends('stock::master')

@section('title', 'تتبع المنتجات')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تتبع المنتجات',
            'icon' => 'crosshair',
            'sub' => 'المنتجات التي يُتتبَّع مخزونها بالرقم التسلسلي أو الشحنة أو تاريخ الانتهاء',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => route('products.index'),
                'التتبع' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @php
            /* نوع التتبع: [التسمية، الأيقونة، اللون] — أوضح من سلسلة elseif
               تعيد كتابة الشارة خمس مرّات */
            $trackingKinds = [
                0 => ['الرقم التسلسلي', 'hash', 'primary'],
                1 => ['رقم الشحنة', 'package', 'info'],
                2 => ['تاريخ الانتهاء', 'calendar', 'warning'],
                3 => ['الشحنة والتاريخ', 'layers', 'secondary'],
                4 => ['الكمية فقط', 'box', 'info'],
            ];
        @endphp

        <div class="card stk-table-card">
            @if (isset($Products) && count($Products) > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> المنتجات المتتبَّعة
                        <span class="stk-count">{{ count($Products) }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:30%">المنتج</th>
                                <th style="width:18%">نوع التتبع</th>
                                <th class="text-center" style="width:14%">حدّ التنبيه</th>
                                <th class="text-center" style="width:22%">تاريخ الانتهاء</th>
                                <th class="text-center" style="width:10%">إخطار قبل</th>
                                <th class="text-center" style="width:6%">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($Products as $Product)
                                @php
                                    [$kindLabel, $kindIcon, $kindTone] =
                                        $trackingKinds[$Product->track_inventory] ?? ['غير محدد', 'help-circle', 'secondary'];

                                    $expiry = $Product->expiry_date
                                        ? \Illuminate\Support\Carbon::parse($Product->expiry_date)
                                        : null;

                                    // الفارق موقَّعاً: سالبٌ يعني أن التاريخ مضى
                                    $daysLeft = $expiry
                                        ? (int) now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false)
                                        : null;

                                    $notifyBefore = $Product->notify_before_days ?? 7;
                                @endphp

                                <tr>
                                    <td>
                                        <div class="stk-media">
                                            <span class="stk-media__thumb">
                                                {{ Str::upper(Str::substr($Product->name, 0, 1)) }}
                                            </span>
                                            <div class="stk-media__body">
                                                <a href="{{ route('products.show', $Product->id) }}" class="stk-media__title">
                                                    {{ $Product->name ?? 'غير محدد' }}
                                                </a>
                                                <span class="stk-media__sub stk-num">
                                                    #{{ $Product->serial_number ?? '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge badge-light-{{ $kindTone }}">
                                            <i class="feather icon-{{ $kindIcon }}"></i> {{ $kindLabel }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        @if ($Product->low_stock_alert)
                                            <span class="stk-num">{{ number_format($Product->low_stock_alert) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        @if ($expiry)
                                            <span class="stk-num d-block">{{ $expiry->format('Y-m-d') }}</span>
                                            @if ($daysLeft < 0)
                                                <span class="badge badge-light-danger">منتهي الصلاحية</span>
                                            @elseif ($daysLeft <= $notifyBefore)
                                                <span class="badge badge-light-warning">
                                                    يبقى {{ $daysLeft }} يوم
                                                </span>
                                            @else
                                                <span class="badge badge-light-success">صالح</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <span class="stk-num">{{ $notifyBefore }}</span>
                                        <span class="stk-media__sub d-block">يوم</span>
                                    </td>

                                    <td class="text-center">
                                        <div class="stk-actions">
                                            <a href="{{ route('products.show', $Product->id) }}"
                                               class="btn btn-outline-secondary" title="عرض" aria-label="عرض">
                                                <i class="feather icon-eye"></i>
                                            </a>
                                            <a href="{{ route('products.edit', $Product->id) }}"
                                               class="btn btn-outline-primary" title="تعديل" aria-label="تعديل">
                                                <i class="feather icon-edit-2"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-crosshair"></i></div>
                        <h5 class="stk-empty__title">لا توجد منتجات متتبَّعة</h5>
                        <p class="stk-empty__hint">فعِّل التتبع من صفحة تعديل المنتج ليظهر هنا</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
