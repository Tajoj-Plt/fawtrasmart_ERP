{{--
    بطاقةُ الوردية — مداها الزمني وأيّامُها وفروعُها وآخرُ جلساتها.

    ═══════════════ والتصميم ═══════════════

    ترويسةٌ مكوّنة، والبياناتُ `stk-def` بدل جدولٍ بلا حدود، وحالةُ الجلسة
    شارةٌ لا نصّاً خاماً — كان `{{ $session->status }}` يطبع `pending_review`
    كما هي في قاعدة البيانات.
--}}
@extends('pos::master')

@section('title', 'الوردية — '.$shift->name)

@section('content')

@php
    use App\Models\PosSession;

    $states = [
        PosSession::STATUS_ACTIVE => ['stk-state--on', 'نشطة'],
        PosSession::STATUS_CLOSED => ['stk-state--neutral', 'مغلقة'],
        PosSession::STATUS_SUSPENDED => ['stk-state--warn', 'معلَّقة'],
        PosSession::STATUS_PENDING_REVIEW => ['stk-state--warn', 'بانتظار التأكيد'],
    ];

    $days = (array) ($shift->days_of_week ?? []);
@endphp

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.shift.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> رجوع
        </a>
        @permission('pos.shifts.manage')
            <a href="{{ route('pos.settings.shift.edit', $shift->id) }}" class="btn btn-primary">
                <i class="feather icon-edit-2"></i> تعديل
            </a>
        @endpermission
    @endsection

    @include('pos::components.page-head', [
        'title' => $shift->name,
        'icon' => 'clock',
        'sub' => $shift->description ?: 'مدى الوردية وأيّامُها وآخرُ جلساتها',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الورديات' => route('pos.settings.shift.index'),
            $shift->name => null,
        ],
    ])

    {{--
        وردية بلا مدًى زمنيّ تسميةٌ في شجرة لا وردية: لا يُقاس بها تأخّرٌ
        ولا يُنسب إليها دوام. والتنبيهُ في أعلى الشاشة لأنه يُبطل غرضَها.
    --}}
    @unless ($shift->start_time && $shift->end_time)
        <div class="alert alert-warning">
            <i class="feather icon-alert-triangle"></i>
            <strong>الوردية بلا مدًى زمني.</strong>
            لا يُقاس بها تأخّرٌ ولا تُحسب ساعاتُ دوام.
            @permission('pos.shifts.manage')
                <a href="{{ route('pos.settings.shift.edit', $shift->id) }}" class="alert-link">اضبط وقتَي البداية والنهاية</a>
            @endpermission
        </div>
    @endunless

    {{--
        ═══════════ حصيلةُ الوردية ═══════════

        كانت الشاشةُ تعرض عشرَ جلساتٍ وحدها — قائمةً بلا حصيلة. فمن يفتح
        البطاقة لا يعرف كم جلسةً عليها إجمالاً ولا كم باعت ولا كم مرّةً
        اختلّ صندوقُها.

        والفائضُ والعجزُ **مفصولان**: دمجُهما في «صافي فرق» يُخفي أيَّهما
        وقع، وهو ما يُراد قياسه — ورديةٌ فائضُها ألفٌ وعجزُها ألفٌ ليست
        كورديةٍ لا فرقَ فيها.
    --}}
    @if (($stats->sessions_count ?? 0) > 0)
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-monitor"></i></span>
                <span class="stk-metric__label">جلسات الوردية</span>
                <span class="stk-metric__value">{{ number_format($stats->sessions_count) }}</span>
            </div>

            @if ($stats->open_count > 0)
                <div class="stk-metric stk-metric--warning">
                    <span class="stk-metric__icon"><i class="feather icon-play-circle"></i></span>
                    <span class="stk-metric__label">مفتوحة الآن</span>
                    <span class="stk-metric__value">{{ number_format($stats->open_count) }}</span>
                </div>
            @endif

            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي المبيعات</span>
                <span class="stk-metric__value">{{ number_format((float) $stats->sales, 2) }}</span>
            </div>

            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">المعاملات</span>
                <span class="stk-metric__value">{{ number_format($stats->transactions) }}</span>
            </div>

            @if ($stats->variance_count > 0)
                <div class="stk-metric stk-metric--danger">
                    <span class="stk-metric__icon"><i class="feather icon-alert-triangle"></i></span>
                    <span class="stk-metric__label">جلسات بفرق صندوق</span>
                    <span class="stk-metric__value">{{ number_format($stats->variance_count) }}</span>
                </div>
            @endif
        </div>

        @if ((float) $stats->over_total > 0.01 || (float) $stats->short_total > 0.01)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                فروقُ صندوق هذه الوردية —
                <strong>فائض {{ number_format((float) $stats->over_total, 2) }}</strong>
                و<strong>عجز {{ number_format((float) $stats->short_total, 2) }}</strong>
                في {{ $stats->variance_count }} جلسة.
            </div>
        @endif
    @endif

    @if (($pendingMovements ?? 0) > 0)
        <div class="alert alert-warning">
            <i class="feather icon-clock"></i>
            <strong>{{ $pendingMovements }}</strong> حركةَ درجٍ على جلسات هذه الوردية بانتظار الاعتماد —
            ولا تدخل الرصيدَ المتوقَّع حتى تُعتمد.
            @if (Route::has('pos.cash_drawer.index'))
                <a href="{{ route('pos.cash_drawer.index', ['status' => 'pending']) }}" class="alert-link">مراجعتها</a>
            @endif
        </div>
    @endif

    <div class="row">

        {{-- ═══════════ بيانات الوردية ═══════════ --}}
        <div class="col-lg-5 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="stk-filters__head">
                        <h6 class="stk-filters__title"><i class="feather icon-info"></i> بيانات الوردية</h6>

                        <span class="stk-state {{ $shift->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                            {{ $shift->is_active ? 'مفعَّلة' : 'معطَّلة' }}
                        </span>
                    </div>

                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>المدى الزمني</dt>
                            <dd>
                                @if ($shift->start_time && $shift->end_time)
                                    <span dir="ltr">
                                        {{ substr($shift->start_time, 0, 5) }} — {{ substr($shift->end_time, 0, 5) }}
                                    </span>
                                    @if ($shift->end_time < $shift->start_time)
                                        <span class="stk-media__sub d-block">تعبر منتصف الليل</span>
                                    @endif
                                @else
                                    غير مضبوط
                                @endif
                            </dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>أيام الدوام</dt>
                            <dd>
                                @if ($days)
                                    <span class="stk-tags">
                                        @foreach ($days as $day)
                                            <span class="stk-tag">{{ $weekDays[$day] ?? $day }}</span>
                                        @endforeach
                                    </span>
                                @else
                                    كل الأيام
                                @endif
                            </dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>مهلة التأخّر</dt>
                            <dd>{{ $shift->grace_minutes ? (int) $shift->grace_minutes.' دقيقة' : '—' }}</dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>الفرع</dt>
                            <dd>{{ $shift->branch->name ?? '—' }}</dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>التصنيف</dt>
                            <dd>
                                @if ($shift->category)
                                    {{-- نقطةُ اللون بنمطٍ في السطر: صنفٌ جديدٌ في قالب
                                         التصميم المشترك بين ثلاث وحدات من أجل نقطةٍ
                                         واحدة ثمنُه أكبر من نفعه --}}
                                    <span style="display:inline-block;width:9px;height:9px;border-radius:50%;
                                                 margin-inline-end:.35rem;vertical-align:middle;
                                                 background: {{ $shift->category->color ?: '#6B7280' }}"></span>
                                    {{ $shift->category->name }}
                                @else
                                    <span class="text-muted">بلا تصنيف</span>
                                @endif
                            </dd>
                        </div>

                        <div class="stk-def__row">
                            <dt>الموظف المسنَد</dt>
                            <dd>{{ $shift->user->name ?? '—' }}</dd>
                        </div>

                        @if ($shift->attachment)
                            <div class="stk-def__row">
                                <dt>المرفق</dt>
                                <dd>
                                    <a href="{{ Storage::url($shift->attachment) }}" target="_blank" rel="noopener">
                                        <i class="feather icon-paperclip"></i> تحميل
                                    </a>
                                </dd>
                            </div>
                        @endif

                        <div class="stk-def__row">
                            <dt>أُنشئت في</dt>
                            <dd>{{ $shift->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            @if ($shift->children->isNotEmpty())
                <div class="card">
                    <div class="card-body">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-git-branch"></i> الورديات الفرعية
                                <span class="stk-count">{{ $shift->children->count() }}</span>
                            </h6>
                        </div>

                        <div class="stk-tags">
                            @foreach ($shift->children as $child)
                                <a class="stk-tag" href="{{ route('pos.settings.shift.show', $child->id) }}">
                                    {{ $child->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ═══════════ آخر الجلسات ═══════════ --}}
        <div class="col-lg-7 col-12">
            <div class="card">
                <div class="card-body">
                    @if ($recentSessions->count() > 0)
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-list"></i> آخر الجلسات في هذه الوردية
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
                            <span class="stk-empty__icon"><i class="feather icon-clock"></i></span>
                            <h6 class="stk-empty__title">لا جلسات في هذه الوردية</h6>
                            <p class="stk-empty__hint">
                                لم تُفتح جلسةٌ تُنسب إليها بعد.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{--
        ═══════════ من عمل عليها وبأيّ جهاز ═══════════

        الجلسةُ هي الجسرُ: الوردية لا ترتبط بكاشيرٍ ولا بجهازٍ مباشرةً، بل
        تُفتح عليها جلساتٌ لكلٍّ منها كاشيرُه وجهازُه. فهذان مشتقّان منها
        لا مخزَّنان.
    --}}
    @if ($operators->isNotEmpty() || $devices->isNotEmpty())
        <div class="row">
            @if ($operators->isNotEmpty())
                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-users"></i> من عمل على هذه الوردية
                                    <span class="stk-count">{{ $operators->count() }}</span>
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>الكاشير</th>
                                            <th class="text-center">جلسات</th>
                                            <th class="text-center">المبيعات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($operators as $row)
                                            <tr>
                                                <td>{{ $row->user->name ?? '—' }}</td>
                                                <td class="text-center">
                                                    <span class="stk-num">{{ number_format($row->sessions_count) }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="stk-num">{{ number_format((float) $row->sales, 2) }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($devices->isNotEmpty())
                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-monitor"></i> الأجهزة المستعملة
                                    <span class="stk-count">{{ $devices->count() }}</span>
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>الجهاز</th>
                                            <th class="text-center">جلسات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($devices as $row)
                                            <tr>
                                                <td>
                                                    @if ($row->device && Route::has('pos.settings.devices.show'))
                                                        <a href="{{ route('pos.settings.devices.show', $row->device_id) }}">
                                                            {{ $row->device->device_name }}
                                                        </a>
                                                    @else
                                                        {{ $row->device->device_name ?? '—' }}
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span class="stk-num">{{ number_format($row->sessions_count) }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>

@endsection
