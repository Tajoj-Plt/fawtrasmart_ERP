{{--
    ═══════════ إحصائيات الجلسة ═══════════

    **ما كانت**: مودلاً في شاشة العرض، فيه ثمانيةُ أسطر — أربعةُ مبالغ وأربعُ
    نسب. لا عنوانَ لها يُرسَل، ولا تُطبع، وتُبنى مع كل فتحةٍ للشاشة ويراها
    واحدٌ من كلّ كثير.

    **وما صارت**: صفحةٌ لها رابطُها وطباعتُها، وتتّسع لما لم يكن للمودل أن
    يسعه — توزيعُ الساعات، وتفصيلُ أنواع الحركة وطرقِ الدفع، وأثرُ الدرج.

    ═══════════ وترتيبُ القراءة ═══════════

    الأرقامُ الأربعة أوّلاً: كم بيع، وكم حركة، وكم متوسّطُها، وكم يُنتظر في
    الدرج. ثم **التحصيل** — نقدٌ وبطاقةٌ ومرتجع، كلٌّ بنسبته من المبيعات،
    لأنّ النسبةَ هي ما يُقارَن بين الوردياتِ لا المبلغ.

    ثم **الساعات**: أين ذروةُ الوردية وأين ركودُها — وهو السؤال الذي يُبنى
    عليه جدولُ الكاشيرين، ولا يُجيبه رقمٌ مجمَّع.
--}}
@extends('pos::master')

@section('title', 'إحصائيات الجلسة - '.$session->session_number)

@section('content')

@php
    use App\Models\PosSession;

    $states = [
        PosSession::STATUS_ACTIVE => ['stk-state--on', 'نشطة'],
        PosSession::STATUS_CLOSED => ['stk-state--neutral', 'مغلقة'],
        PosSession::STATUS_SUSPENDED => ['stk-state--warn', 'معلَّقة'],
        PosSession::STATUS_PENDING_REVIEW => ['stk-state--warn', 'بانتظار التأكيد'],
    ];

    [$stateClass, $stateLabel] = $states[$session->status] ?? ['stk-state--neutral', $session->status];

    // أكبرُ ساعةٍ هي مرجعُ الأشرطة — لا نسبةٌ من الإجمالي، فتُقارن الساعاتُ ببعضها
    $peak = $byHour->max('count') ?: 1;

    $typeLabels = [
        'sale' => 'بيع',
        'return' => 'إرجاع',
        'opening_balance' => 'رصيد افتتاحي',
        'cash_adjustment' => 'تعديل نقدي',
    ];
@endphp

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.sessions.show', $session->id) }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> الجلسة
        </a>
        <button type="button" class="btn btn-outline-primary" id="printStatsBtn">
            <i class="feather icon-printer"></i> طباعة
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => 'إحصائيات الجلسة '.$session->session_number,
        'icon' => 'bar-chart-2',
        'sub' => ($session->user->name ?? '—').' · '.($session->device->device_name ?? 'بلا جهاز')
            .' · '.$session->started_at->format('Y-m-d H:i'),
        'crumbs' => [
            'الرئيسية' => route('dashboard.pos'),
            'الجلسات' => route('pos.sessions.index'),
            $session->session_number => route('pos.sessions.show', $session->id),
            'الإحصائيات' => null,
        ],
    ])

    <div class="stk-metrics">
        <div class="stk-metric stk-metric--success">
            <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
            <span class="stk-metric__label">إجمالي المبيعات ({{ $symbol }})</span>
            <span class="stk-metric__value">{{ number_format($stats['sales'], 2) }}</span>
        </div>

        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
            <span class="stk-metric__label">عدد الحركات</span>
            <span class="stk-metric__value">{{ number_format($stats['transactions']) }}</span>
        </div>

        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-divide"></i></span>
            <span class="stk-metric__label">متوسّط الحركة ({{ $symbol }})</span>
            <span class="stk-metric__value">{{ number_format($stats['average'], 2) }}</span>
        </div>

        <div class="stk-metric stk-metric--warning">
            <span class="stk-metric__icon"><i class="feather icon-briefcase"></i></span>
            <span class="stk-metric__label">النقد المتوقَّع في الدرج ({{ $symbol }})</span>
            <span class="stk-metric__value">{{ number_format($stats['expected_cash'], 2) }}</span>
        </div>
    </div>

    <div class="row">
        {{-- ═══════════ التحصيل ═══════════ --}}
        <div class="col-lg-6 col-12">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stk-section__head">
                        <i class="feather icon-credit-card"></i>
                        <h6 class="stk-section__title">التحصيل</h6>
                    </div>

                    {{--
                        النسبةُ لا المبلغ وحده: ورديةٌ حصّلت ٩٠٪ نقداً غير
                        أخرى حصّلت ٩٠٪ بطاقات، وإن تساوى مجموعُهما — الأولى
                        عهدةٌ في درج، والثانية مالٌ في طريقه إلى بنك.
                    --}}
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach ([
                                ['النقدي', $stats['cash'], $stats['cash_ratio'], 'stk-bar--success'],
                                ['البطاقات', $stats['card'], $stats['card_ratio'], ''],
                                ['المرتجعات', $stats['returns'], $stats['return_ratio'], 'stk-bar--danger'],
                            ] as [$label, $amount, $pct, $tone])
                                <tr>
                                    <td style="width: 26%">{{ $label }}</td>
                                    <td class="stk-num" style="width: 30%">
                                        {{ number_format($amount, 2) }} {{ $symbol }}
                                    </td>
                                    <td>
                                        <div class="stk-bar {{ $tone }}">
                                            <span class="stk-bar__fill" style="width: {{ min($pct, 100) }}%"></span>
                                            <span class="stk-bar__text">{{ $pct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <p class="stk-help mb-0">النِّسَبُ من إجمالي المبيعات.</p>
                </div>
            </div>
        </div>

        {{-- ═══════════ الدرج ═══════════ --}}
        <div class="col-lg-6 col-12">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stk-section__head">
                        <i class="feather icon-briefcase"></i>
                        <h6 class="stk-section__title">حركاتُ الدرج</h6>
                    </div>

                    {{--
                        هذه الأرقامُ كانت تُحسب وتُمرَّر إلى شاشة العرض ولا
                        تُعرض. وهي ما يفسّر الفرق عند الإقفال: درجٌ أُودع فيه
                        أو سُحب منه لا يطابق «افتتاحي + نقدي − مرتجعات».
                    --}}
                    <dl class="stk-def mb-0">
                        <div class="stk-def__row">
                            <dt>الرصيد الافتتاحي</dt>
                            <dd class="stk-num">{{ number_format($session->opening_balance, 2) }} {{ $symbol }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>إيداعات</dt>
                            <dd class="stk-num text-success">+ {{ number_format($drawer['pay_in'], 2) }} {{ $symbol }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>سحوبات</dt>
                            <dd class="stk-num text-danger">− {{ number_format($drawer['pay_out'], 2) }} {{ $symbol }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تحويل للخزينة</dt>
                            <dd class="stk-num text-danger">− {{ number_format($drawer['drop'], 2) }} {{ $symbol }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>صافي أثر الحركات</dt>
                            <dd class="stk-num">{{ number_format($drawer['net'], 2) }} {{ $symbol }}</dd>
                        </div>
                    </dl>

                    @if ($drawer['pending'] > 0)
                        {{--
                            المعلَّقُ لا يدخل الحساب — فيبدو الرصيدُ المتوقَّع
                            خاطئاً لمن لا يعرف أنّ حركةً تنتظر اعتماداً.
                        --}}
                        <div class="alert alert-warning mb-0 mt-1">
                            <i class="feather icon-clock"></i>
                            <strong>{{ $drawer['pending'] }}</strong> حركةُ درجٍ تنتظر الاعتماد —
                            لا تدخل في النقد المتوقَّع حتى تُعتمد.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ الساعات ═══════════ --}}
    <div class="card">
        <div class="card-body">
            <div class="stk-section__head">
                <i class="feather icon-clock"></i>
                <h6 class="stk-section__title">توزيع الحركة على الساعات</h6>
            </div>

            @if ($byHour->isEmpty())
                <div class="stk-empty">
                    <i class="feather icon-clock"></i>
                    <p class="mb-0">لا حركاتٍ في هذه الجلسة بعد.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="hoursTable">
                        <thead class="stk-table-head">
                            <tr>
                                <th style="width: 12%">الساعة</th>
                                <th style="width: 12%">الحركات</th>
                                <th style="width: 22%">المبلغ ({{ $symbol }})</th>
                                <th>مقارنةً بالذروة</th>
                            </tr>
                        </thead>
                        <tbody id="hoursTableBody">
                            @foreach ($byHour as $hour => $row)
                                <tr>
                                    <td class="stk-num">{{ $hour }}:00</td>
                                    <td class="stk-num">{{ number_format($row['count']) }}</td>
                                    <td class="stk-num">{{ number_format($row['amount'], 2) }}</td>
                                    <td>
                                        <div class="stk-bar">
                                            <span class="stk-bar__fill"
                                                  style="width: {{ round($row['count'] / $peak * 100) }}%"></span>
                                            <span class="stk-bar__text">{{ round($row['count'] / $peak * 100) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="stk-help mb-0">
                    الشريطُ نسبةٌ إلى أكثرِ الساعات ازدحاماً ({{ number_format($peak) }} حركة)، لا إلى الإجمالي —
                    فتُقارن الساعاتُ ببعضها.
                </p>
            @endif
        </div>
    </div>

    <div class="row">
        {{-- ═══════════ أنواع الحركة ═══════════ --}}
        <div class="col-lg-6 col-12">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stk-section__head">
                        <i class="feather icon-layers"></i>
                        <h6 class="stk-section__title">أنواع الحركة</h6>
                    </div>

                    @if ($byType->isEmpty())
                        <div class="stk-empty">
                            <i class="feather icon-layers"></i>
                            <p class="mb-0">لا حركات.</p>
                        </div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="stk-table-head">
                                <tr>
                                    <th>النوع</th>
                                    <th style="width: 22%">العدد</th>
                                    <th style="width: 32%">المبلغ ({{ $symbol }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byType as $type => $row)
                                    <tr>
                                        <td>{{ $typeLabels[$type] ?? $type }}</td>
                                        <td class="stk-num">{{ number_format($row['count']) }}</td>
                                        <td class="stk-num">{{ number_format($row['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══════════ طرق الدفع ═══════════ --}}
        <div class="col-lg-6 col-12">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stk-section__head">
                        <i class="feather icon-credit-card"></i>
                        <h6 class="stk-section__title">طرق الدفع</h6>
                    </div>

                    @if ($byMethod->isEmpty())
                        {{--
                            حركاتٌ بلا طريقةِ دفعٍ مسجَّلة ليست خطأً: الرصيدُ
                            الافتتاحي وتعديلاتُ الدرج لا طريقةَ لها.
                        --}}
                        <div class="stk-empty">
                            <i class="feather icon-credit-card"></i>
                            <p class="mb-0">لم تُسجَّل طريقةُ دفعٍ على أيّ حركة.</p>
                        </div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="stk-table-head">
                                <tr>
                                    <th>الطريقة</th>
                                    <th style="width: 22%">العدد</th>
                                    <th style="width: 32%">المبلغ ({{ $symbol }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byMethod as $method => $row)
                                    <tr>
                                        <td>{{ $method }}</td>
                                        <td class="stk-num">{{ number_format($row['count']) }}</td>
                                        <td class="stk-num">{{ number_format($row['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ سياقُ الجلسة ═══════════ --}}
    <div class="card">
        <div class="card-body">
            <div class="stk-section__head">
                <i class="feather icon-info"></i>
                <h6 class="stk-section__title">سياق الجلسة</h6>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <dl class="stk-def mb-0">
                        <div class="stk-def__row">
                            <dt>الحالة</dt>
                            <dd><span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span></dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الكاشير</dt>
                            <dd>{{ $session->user->name ?? '—' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الوردية</dt>
                            <dd>{{ $session->shift->name ?? 'غير محددة' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الجهاز</dt>
                            <dd>{{ $session->device->device_name ?? 'غير محدد' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="col-md-6">
                    <dl class="stk-def mb-0">
                        <div class="stk-def__row">
                            <dt>الفرع</dt>
                            <dd>{{ $session->branch->name ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>البداية</dt>
                            <dd>{{ $session->started_at->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>النهاية</dt>
                            <dd>{{ $session->ended_at?->format('Y-m-d H:i') ?? 'ما تزال مفتوحة' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>أوّل وآخر حركة</dt>
                            <dd>
                                @if ($stats['first_at'] && $stats['last_at'])
                                    {{ \Carbon\Carbon::parse($stats['first_at'])->format('H:i') }}
                                    —
                                    {{ \Carbon\Carbon::parse($stats['last_at'])->format('H:i') }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
{{--
    الطباعةُ المخصَّصة — نفسُ آليّة تقارير الحسابات العامة.

    `window.print()` يطبع الشاشةَ بشريطها الجانبي وترويستها وأزرارها، ولا
    يحمل اسمَ المنشأة ولا رقمَها الضريبي: ورقةٌ تخرج من النظام ولا يُعرف من
    أصدرها. و`printTable` يبني ورقةً مستقلّة بترويسة المنشأة من الإعدادات.
--}}
<script src="{{ asset('js/print-table.js') }}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('printStatsBtn');

        if (! btn) {
            return;
        }

        btn.addEventListener('click', function () {
            if (typeof printTable !== 'function') {
                window.print();
                return;
            }

            printTable({
                tableSelector: '#hoursTableBody tr',
                title: 'إحصائيات الجلسة {{ $session->session_number }}',
                tableHeaders: ['الساعة', 'الحركات', 'المبلغ ({{ $symbol }})', 'مقارنةً بالذروة'],
                columnWidths: ['15%', '15%', '30%', '40%'],

                /*
                    بطاقاتُ المعلومات تحمل خلاصةَ الجلسة، فالورقةُ تُقرأ
                    وحدها: جدولُ ساعاتٍ بلا مجاميعَ لا يُفيد مراجِعاً.
                */
                infoCards: [
                    { label: 'الكاشير', value: @json($session->user->name ?? '—') },
                    { label: 'الجهاز', value: @json($session->device->device_name ?? 'غير محدد') },
                    { label: 'الوردية', value: @json($session->shift->name ?? 'غير محددة') },
                    { label: 'إجمالي المبيعات', value: @json(number_format($stats['sales'], 2).' '.$symbol) },
                    { label: 'عدد الحركات', value: @json(number_format($stats['transactions'])) },
                    { label: 'متوسّط الحركة', value: @json(number_format($stats['average'], 2).' '.$symbol) },
                    { label: 'النقدي', value: @json(number_format($stats['cash'], 2).' '.$symbol.' ('.$stats['cash_ratio'].'%)') },
                    { label: 'البطاقات', value: @json(number_format($stats['card'], 2).' '.$symbol.' ('.$stats['card_ratio'].'%)') },
                    { label: 'المرتجعات', value: @json(number_format($stats['returns'], 2).' '.$symbol.' ('.$stats['return_ratio'].'%)') },
                    { label: 'النقد المتوقَّع', value: @json(number_format($stats['expected_cash'], 2).' '.$symbol) },
                ],

                filterInfo: {
                    'الجلسة': @json($session->session_number),
                    'الفرع': @json($session->branch->name ?? 'غير محدد'),
                    'الفترة': @json($session->started_at->format('Y-m-d H:i')
                        .' — '.($session->ended_at?->format('Y-m-d H:i') ?? 'مفتوحة')),
                },
            });
        });
    });
</script>
@endsection
