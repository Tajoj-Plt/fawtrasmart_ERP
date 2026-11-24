{{--
    بطاقةُ جهاز الكاشير.

    المسارُ `pos.settings.devices.show` كان مسجَّلاً منذ البداية ويشير إلى
    `DevicesController@show` — **ودالّةٌ بهذا الاسم لم تكن موجودة في
    المتحكّم**. فالرابطُ يؤدّي إلى خطأ.

    ═══════════════ والتصميم ═══════════════

    ترويسةٌ مكوّنة، والبياناتُ `stk-def` بدل جدولٍ بلا حدود، والجلساتُ
    بشاراتِ حالةٍ لا بالنصّ الخام (`$session->status` كان يُطبع `closed`
    و`pending_review` كما هي في قاعدة البيانات).
--}}
@extends('pos::master')

@section('title', 'الجهاز — '.$device->device_name)

@section('content')

@php
    use App\Models\PosSession;

    $states = [
        PosSession::STATUS_ACTIVE => ['stk-state--on', 'نشطة'],
        PosSession::STATUS_CLOSED => ['stk-state--neutral', 'مغلقة'],
        PosSession::STATUS_SUSPENDED => ['stk-state--warn', 'معلَّقة'],
        PosSession::STATUS_PENDING_REVIEW => ['stk-state--warn', 'بانتظار التأكيد'],
    ];
@endphp

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.devices.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> رجوع
        </a>
        @permission('pos.devices.manage')
            <a href="{{ route('pos.settings.devices.edit', $device->id) }}" class="btn btn-primary">
                <i class="feather icon-edit-2"></i> تعديل
            </a>
        @endpermission
    @endsection

    @include('pos::components.page-head', [
        'title' => $device->device_name,
        'icon' => 'monitor',
        'sub' => $device->description ?: 'بطاقةُ الجهاز وآخرُ الجلسات عليه',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الأجهزة' => route('pos.settings.devices.index'),
            $device->device_name => null,
        ],
    ])

    {{--
        المستودعُ ليس تفصيلاً: منه يُخصم كلُّ بيعٍ يقع على هذا الجهاز.
        وجهازٌ بلا مستودعٍ لا يُباع منه أصلاً، فالتنبيهُ في أعلى الشاشة.
    --}}
    @unless ($device->store)
        <div class="alert alert-danger">
            <i class="feather icon-alert-triangle"></i>
            <strong>الجهاز بلا مستودع.</strong>
            لا يمكن البيع منه — إذ لا يُعرف من أين تُخصم البضاعة.
            @permission('pos.devices.manage')
                <a href="{{ route('pos.settings.devices.edit', $device->id) }}" class="alert-link">اربطه بمستودع</a>
            @endpermission
        </div>
    @endunless

    <div class="row">

        {{-- ═══════════ بيانات الجهاز ═══════════ --}}
        <div class="col-lg-5 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="stk-filters__head">
                        <h6 class="stk-filters__title">
                            <i class="feather icon-info"></i> بيانات الجهاز
                        </h6>

                        <span class="stk-state {{ $device->isActive() ? 'stk-state--on' : 'stk-state--off' }}">
                            {{ $device->status_text }}
                        </span>
                    </div>

                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>الفرع</dt>
                            <dd>{{ $device->branch->name ?? '—' }}</dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>المستودع</dt>
                            <dd>{{ $device->store->name ?? '—' }}</dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>الكاشير المسنَد</dt>
                            <dd>{{ $device->user->name ?? '—' }}</dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>الرقم التسلسلي</dt>
                            <dd>{{ $device->serial_number ?: '—' }}</dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>العنوان الشبكي</dt>
                            <dd dir="ltr">{{ $device->ip_address ?: '—' }}</dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>حدّ النقد في الدرج</dt>
                            <dd>
                            {{ $device->max_cash_in_drawer
                                ? number_format((float) $device->max_cash_in_drawer, 2)
                                : 'بلا حدّ' }}
                        </dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>الطابعة الافتراضية</dt>
                            <dd>{{ $device->defaultPrinter->name ?? 'الطابعة العامّة' }}</dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>أُنشئ في</dt>
                            <dd>{{ $device->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- ═══════════ آخر الجلسات ═══════════ --}}
        <div class="col-lg-7 col-12">
            <div class="card">
                <div class="card-body">
                    @if ($recentSessions->count() > 0)
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-list"></i> آخر الجلسات على هذا الجهاز
                                <span class="stk-count">{{ $recentSessions->count() }}</span>
                            </h6>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>الجلسة</th>
                                        <th>الكاشير</th>
                                        <th class="text-center">المبيعات</th>
                                        <th class="text-center">الفرق</th>
                                        <th class="text-center">الحالة</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($recentSessions as $session)
                                        @php
                                            [$cls, $label] = $states[$session->status]
                                                ?? ['stk-state--neutral', $session->status];

                                            $variance = (float) $session->difference;
                                        @endphp

                                        <tr>
                                            <td>
                                                <a href="{{ route('pos.sessions.show', $session->id) }}">
                                                    {{ $session->session_number }}
                                                </a>
                                                <span class="stk-media__sub d-block">
                                                    {{ $session->started_at?->format('Y-m-d H:i') ?? '—' }}
                                                </span>
                                            </td>

                                            <td>{{ $session->user->name ?? '—' }}</td>

                                            <td class="text-center">
                                                <span class="stk-num">
                                                    {{ number_format((float) $session->total_sales, 2) }}
                                                </span>
                                            </td>

                                            {{--
                                                الفرقُ يُلوَّن باتّجاهه لا بوجوده: الفائضُ
                                                والعجزُ مسألتان مختلفتان، ودمجُهما في
                                                «أحمر» واحد يُخفي أيَّهما وقع.
                                            --}}
                                            <td class="text-center">
                                                @if (abs($variance) <= 0.01)
                                                    <span class="text-muted">—</span>
                                                @else
                                                    <span class="stk-move {{ $variance > 0 ? 'stk-move--success' : 'stk-move--danger' }}">
                                                        {{ $variance > 0 ? 'فائض' : 'عجز' }}
                                                        {{ number_format(abs($variance), 2) }}
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="text-center">
                                                <span class="stk-state {{ $cls }}">{{ $label }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="stk-empty">
                            <span class="stk-empty__icon"><i class="feather icon-monitor"></i></span>
                            <h6 class="stk-empty__title">لا جلسات على هذا الجهاز</h6>
                            <p class="stk-empty__hint">
                                لم تُفتح عليه جلسةٌ بعد — وأوّلُ جلسةٍ تظهر هنا بعُهدتها ومبيعاتها.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
