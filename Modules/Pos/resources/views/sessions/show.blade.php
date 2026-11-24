{{--
    ═══════════ شاشةُ الجلسة ═══════════

    **ما كانت**: ترويسةَ Vuexy القديمة، وأيقوناتِ `fa`، وبطاقاتِ `stat-card`
    محلّيةَ التنسيق، وبطاقاتٍ بـ`card-header` وأدواتِ طيٍّ لا تُستعمل.

    **وما صارت**: نظامَ `stk-` الذي تعمل به بقيّةُ الوحدة.

    ═══════════ وأربعةُ أشياءَ كانت مكسورة ═══════════

    ١. **المودلات لا تُفتح**. الصفحةُ تُحمّل Bootstrap 5 وحدها، والمودلُ
       مكتوبٌ بـ`data-toggle`/`data-dismiss` — وهي صيغةُ الرابع. فزرُّ
       «إغلاق الجلسة» لا يفعل شيئاً، والمستخدمُ يظنّ النظامَ معطَّلاً.
       صارت `data-bs-*` كما في بقيّة الشاشات المُهاجَرة.

    ٢. **العملةُ مثبّتة**. `ر.س` مكتوبةٌ حرفياً في سبعةَ عشرَ موضعاً، و
       `$symbol` يُمرَّر من المتحكّم ولا يُستعمل. فمنشأةٌ تبيع بغير الريال
       ترى أرقامَها موسومةً بعملةٍ ليست عملتها.

    ٣. **الرصيدُ المتوقَّع يُحسب في القالب** — `افتتاحي + نقدي − مرتجعات`،
       بلا حركاتِ الدرج. والنموذجُ يملك `computeExpectedCash()` الذي
       يحسبها. فدرجٌ أُودع فيه أو سُحب منه كان يُظهر فرقاً وهمياً عند
       الإقفال.

    ٤. **`$drawer` يُمرَّر ولا يُعرض** — وهو ما يفسّر ذلك الفرق بعينه.

    وشريطُ «مدة الوردية التقريبية: ٨ ساعات» كان ثابتاً مكتوباً، والوردية
    لها وقتُ بدءٍ ونهايةٍ حقيقيّان.

    ═══════════ والإحصائياتُ خرجت ═══════════

    كانت مودلاً بثمانيةِ أسطر يُبنى مع كل فتحةٍ للشاشة. صارت
    [صفحةً](sessions/stats.blade.php) لها رابطُها وطباعتُها.
--}}
@extends('pos::master')

@section('title', 'الجلسة '.$session->session_number)

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

    /*
        ═══════════ من يرى زرَّ الإقفال؟ ═══════════

        مسارُ العرض يطلب `view_own|view_all` فقط، ومسارُ الإقفال يطلب
        `close_own|close_all`. فكان الزرُّ يُعرض لكل من يفتح الشاشة، ويُردّ
        بـ403 عند الضغط — والمشرفُ الذي يراجع جلسةَ غيره يظنّ النظام مكسوراً.

        و`..._own` لا تكفي لجلسة غيره: تُفحَص ملكيّةُ الجلسة كذلك، وهو نفسُ
        ما تفحصه `ScopesToPosAccess::authorizeSessionClose()` في المتحكّم.
    */
    $isOwnSession = (int) $session->user_id === (int) auth()->id();

    $canCloseSession = $session->canBeClosed() && (
        hasPermission('points_sale_close_sessions_all')
        || ($isOwnSession && hasPermission('points_sale_close_sessions_own'))
    );

    // الحسابُ من النموذج: يشمل حركاتِ الدرج، والقالبُ لا يعيد اشتقاقه
    $expectedCash = $session->computeExpectedCash();

    $details = $session->details?->sortByDesc('transaction_time') ?? collect();

    $typeStyles = [
        'sale' => ['stk-move--success', 'icon-shopping-cart', 'بيع'],
        'return' => ['stk-move--danger', 'icon-corner-up-left', 'إرجاع'],
        'opening_balance' => ['', 'icon-play', 'افتتاحي'],
        'cash_adjustment' => ['', 'icon-edit-2', 'تعديل نقدي'],
    ];
@endphp

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.sessions.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> الجلسات
        </a>

        <a href="{{ route('pos.sessions.stats', $session->id) }}" class="btn btn-outline-primary">
            <i class="feather icon-bar-chart-2"></i> الإحصائيات
        </a>

        <button type="button" class="btn btn-outline-primary" id="printSessionBtn">
            <i class="feather icon-printer"></i> طباعة
        </button>

        @if ($session->acceptsSales())
            <a href="{{ route('sales_start.index') }}" class="btn btn-success">
                <i class="feather icon-shopping-cart"></i> شاشة البيع
            </a>
        @endif

        @if ($canCloseSession)
            <button type="button" class="btn btn-danger"
                    data-bs-toggle="modal" data-bs-target="#closeSessionModal">
                <i class="feather icon-stop-circle"></i> إغلاق الجلسة
            </button>
        @endif
    @endsection

    @include('pos::components.page-head', [
        'title' => 'الجلسة '.$session->session_number,
        'icon' => 'monitor',
        'sub' => ($session->user->name ?? '—').' · '.($session->device->device_name ?? 'بلا جهاز')
            .' · '.$session->started_at->format('Y-m-d H:i'),
        'crumbs' => [
            'الرئيسية' => route('dashboard.pos'),
            'الجلسات' => route('pos.sessions.index'),
            $session->session_number => null,
        ],
    ])

    <div class="stk-metrics">
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
            <span class="stk-metric__label">عدد الحركات</span>
            <span class="stk-metric__value">{{ number_format($session->total_transactions) }}</span>
        </div>

        <div class="stk-metric stk-metric--success">
            <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
            <span class="stk-metric__label">إجمالي المبيعات ({{ $symbol }})</span>
            <span class="stk-metric__value">{{ number_format($session->total_sales, 2) }}</span>
        </div>

        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
            <span class="stk-metric__label">النقدي ({{ $symbol }})</span>
            <span class="stk-metric__value">{{ number_format($session->total_cash, 2) }}</span>
        </div>

        <div class="stk-metric stk-metric--warning">
            <span class="stk-metric__icon"><i class="feather icon-credit-card"></i></span>
            <span class="stk-metric__label">الشبكة ({{ $symbol }})</span>
            <span class="stk-metric__value">{{ number_format($session->total_card, 2) }}</span>
        </div>
    </div>

    <div class="row">
        {{-- ═══════════ تفاصيلُ الجلسة ═══════════ --}}
        <div class="col-lg-7 col-12">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stk-section__head">
                        <i class="feather icon-info"></i>
                        <h6 class="stk-section__title">تفاصيل الجلسة</h6>
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
                                    <dt>المدة</dt>
                                    <dd>{{ $session->duration }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>النهاية</dt>
                                    <dd>{{ $session->ended_at?->format('Y-m-d H:i') ?? 'ما تزال مفتوحة' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    @if ($session->isClosed() || $session->status === PosSession::STATUS_PENDING_REVIEW)
                        <div class="stk-section__head mt-2">
                            <i class="feather icon-check-square"></i>
                            <h6 class="stk-section__title">الإقفال</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <dl class="stk-def mb-0">
                                    <div class="stk-def__row">
                                        <dt>أقفلها</dt>
                                        <dd>{{ $session->closedBy->name ?? '—' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الرصيد الفعلي</dt>
                                        <dd class="stk-num">
                                            {{ number_format((float) $session->actual_closing_balance, 2) }} {{ $symbol }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="col-md-6">
                                <dl class="stk-def mb-0">
                                    <div class="stk-def__row">
                                        <dt>الفرق</dt>
                                        <dd class="stk-num {{ (float) $session->difference < 0 ? 'text-danger' : ((float) $session->difference > 0 ? 'text-success' : '') }}">
                                            {{ number_format((float) $session->difference, 2) }} {{ $symbol }}
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>التأكيد</dt>
                                        <dd>
                                            @if ($session->confirmed_at)
                                                {{ $session->confirmedBy->name ?? '—' }}
                                                <small class="text-muted">{{ $session->confirmed_at->format('Y-m-d H:i') }}</small>
                                            @else
                                                <span class="stk-state stk-state--warn">لم تُؤكَّد</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        @if (filled($session->closing_notes))
                            <p class="stk-help mb-0 mt-1">
                                <strong>ملاحظات الإقفال:</strong> {{ $session->closing_notes }}
                            </p>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══════════ الدرج ═══════════ --}}
        <div class="col-lg-5 col-12">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stk-section__head">
                        <i class="feather icon-briefcase"></i>
                        <h6 class="stk-section__title">الدرج النقدي</h6>
                    </div>

                    {{--
                        الرصيدُ المتوقَّع من `computeExpectedCash()` — يشمل
                        حركاتِ الدرج المعتمَدة. وكان القالبُ يحسبه بنفسه
                        `افتتاحي + نقدي − مرتجعات` فيُظهر فرقاً وهمياً على
                        كل درجٍ أُودع فيه أو سُحب منه.
                    --}}
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>الرصيد الافتتاحي</dt>
                            <dd class="stk-num">{{ number_format($session->opening_balance, 2) }} {{ $symbol }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>النقدي المحصَّل</dt>
                            <dd class="stk-num text-success">+ {{ number_format($session->total_cash, 2) }} {{ $symbol }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>المرتجعات النقدية</dt>
                            <dd class="stk-num text-danger">− {{ number_format($session->total_returns, 2) }} {{ $symbol }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>صافي حركات الدرج</dt>
                            <dd class="stk-num">{{ number_format($drawer['net'], 2) }} {{ $symbol }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt><strong>النقد المتوقَّع</strong></dt>
                            <dd class="stk-num"><strong>{{ number_format($expectedCash, 2) }} {{ $symbol }}</strong></dd>
                        </div>
                    </dl>

                    @if ($drawer['pending'] > 0)
                        <div class="alert alert-warning mb-0">
                            <i class="feather icon-clock"></i>
                            <strong>{{ $drawer['pending'] }}</strong> حركةُ درجٍ تنتظر الاعتماد —
                            لا تدخل في النقد المتوقَّع حتى تُعتمد.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ الحركات ═══════════ --}}
    <div class="card">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title">
                    <i class="feather icon-list"></i>
                    الحركات
                    <span class="text-muted">({{ number_format($details->count()) }})</span>
                </h6>

                <div class="">
                    <a href="{{ route('pos.sessions.stats', $session->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="feather icon-bar-chart-2"></i> تحليلُ الحركات
                    </a>
                </div>
            </div>

            @if ($details->isEmpty())
                <div class="stk-empty">
                    <i class="feather icon-inbox"></i>
                    <p>لا حركاتٍ في هذه الجلسة بعد.</p>
                    <p class="stk-help">تظهر هنا كلُّ عمليةِ بيعٍ أو إرجاعٍ تُسجَّل من شاشة البيع.</p>

                    @if ($session->acceptsSales())
                        <a href="{{ route('sales_start.index') }}" class="btn btn-primary">
                            <i class="feather icon-shopping-cart"></i> الانتقال لشاشة البيع
                        </a>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="transactionsTable">
                        <thead class="stk-table-head">
                            <tr>
                                <th style="width: 10%">الوقت</th>
                                <th style="width: 12%">النوع</th>
                                <th style="width: 16%">المرجع</th>
                                <th style="width: 14%">المبلغ ({{ $symbol }})</th>
                                <th style="width: 13%">النقدي</th>
                                <th style="width: 13%">البطاقة</th>
                                <th>الوصف</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody">
                            @foreach ($details as $detail)
                                @php
                                    [$tone, $icon, $label] = $typeStyles[$detail->transaction_type]
                                        ?? ['', 'icon-circle', $detail->transaction_type];
                                @endphp
                                <tr>
                                    <td class="stk-num">{{ $detail->transaction_time?->format('H:i:s') ?? '—' }}</td>
                                    <td>
                                        <span class="stk-state {{ $tone ?: 'stk-state--neutral' }}">
                                            <i class="feather {{ $icon }}"></i> {{ $label }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($detail->reference_number)
                                            <code>{{ $detail->reference_number }}</code>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="stk-num {{ $detail->transaction_type === 'return' ? 'text-danger' : '' }}">
                                        {{ number_format($detail->amount, 2) }}
                                    </td>
                                    <td class="stk-num">{{ number_format($detail->cash_amount, 2) }}</td>
                                    <td class="stk-num">{{ number_format($detail->card_amount, 2) }}</td>
                                    <td>{{ $detail->description ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{--
    مودل إغلاق الجلسة.

    يُحرَس المودلُ كلُّه لا الزرُّ وحده: إخفاءُ الزرّ يترك النموذجَ في الصفحة،
    ويُفتح بسطرٍ واحد في طرفية المتصفّح. والحراسةُ التي تُلتفّ بنقرةٍ ليست
    حراسة — وإن كان المسارُ يردّ على كل حال، فلا داعي لإرسال النموذج أصلاً.
--}}
@if ($canCloseSession)
<div class="modal fade" id="closeSessionModal" tabindex="-1"
     aria-labelledby="closeSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="closeSessionForm" method="POST" action="{{ route('pos.sessions.close', $session->id) }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="closeSessionModalLabel">
                        <i class="feather icon-stop-circle text-danger"></i>
                        إغلاق الجلسة {{ $session->session_number }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stk-section__head">
                                <i class="feather icon-file-text"></i>
                                <h6 class="stk-section__title">المتوقَّع</h6>
                            </div>

                            <dl class="stk-def mb-0">
                                <div class="stk-def__row">
                                    <dt>الرصيد الافتتاحي</dt>
                                    <dd class="stk-num">{{ number_format($session->opening_balance, 2) }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>النقدي المحصَّل</dt>
                                    <dd class="stk-num text-success">+ {{ number_format($session->total_cash, 2) }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>المرتجعات</dt>
                                    <dd class="stk-num text-danger">− {{ number_format($session->total_returns, 2) }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>صافي حركات الدرج</dt>
                                    <dd class="stk-num">{{ number_format($drawer['net'], 2) }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt><strong>النقد المتوقَّع</strong></dt>
                                    <dd class="stk-num"><strong>{{ number_format($expectedCash, 2) }} {{ $symbol }}</strong></dd>
                                </div>
                            </dl>
                        </div>

                        <div class="col-md-6">
                            <div class="stk-section__head">
                                <i class="feather icon-briefcase"></i>
                                <h6 class="stk-section__title">العدُّ الفعلي</h6>
                            </div>

                            <div class="form-group">
                                <label for="actual_closing_balance" class="stk-req">
                                    النقدُ الموجود في الدرج
                                    <i class="feather icon-help-circle stk-tip"
                                       title="عُدَّ النقدَ فعلياً قبل الإدخال — الفرقُ بينه وبين المتوقَّع هو ما يُقيَّد فائضاً أو عجزاً."></i>
                                </label>

                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" required
                                           class="form-control text-center"
                                           id="actual_closing_balance" name="actual_closing_balance"
                                           value="{{ number_format($expectedCash, 2, '.', '') }}">
                                    <span class="input-group-text">{{ $symbol }}</span>
                                </div>
                            </div>

                            {{--
                                الفرقُ يُعرض فور الكتابة: مبلغٌ يُقيَّد على
                                حسابِ فائضٍ أو عجز، ورؤيتُه قبل التأكيد تمنع
                                خطأً يحتاج قيدَ تسويةٍ لإصلاحه.
                            --}}
                            <div id="difference-display" class="alert mb-0" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>الفرق</span>
                                    <strong id="difference-amount" class="stk-num"></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-2 mb-0">
                        <label for="closing_notes">ملاحظات الإقفال</label>
                        <textarea class="form-control" id="closing_notes" name="closing_notes" rows="2"
                                  placeholder="سببُ الفرق إن وُجد، أو أيُّ ملاحظةٍ على الوردية"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger" id="confirmCloseBtn">
                        <i class="feather icon-check"></i> تأكيد الإقفال
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@section('scripts')
{{--
    الطباعةُ المخصَّصة — نفسُ آليّة تقارير الحسابات العامة.

    `window.print()` كان يطبع الشاشةَ بشريطها الجانبي وأزرارها، ولا يحمل
    اسمَ المنشأة ولا رقمَها الضريبي: ورقةٌ تخرج من النظام ولا يُعرف من
    أصدرها. و`printTable` يبني ورقةً مستقلّة بترويسةِ المنشأة من الإعدادات.
--}}
<script src="{{ asset('js/print-table.js') }}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        /* ═══════════ الطباعة ═══════════ */
        var printBtn = document.getElementById('printSessionBtn');

        if (printBtn) {
            printBtn.addEventListener('click', function () {
                if (typeof printTable !== 'function' || ! document.getElementById('transactionsTableBody')) {
                    window.print();
                    return;
                }

                printTable({
                    tableSelector: '#transactionsTableBody tr',
                    title: 'حركات الجلسة {{ $session->session_number }}',
                    tableHeaders: [
                        'الوقت', 'النوع', 'المرجع',
                        'المبلغ ({{ $symbol }})', 'النقدي', 'البطاقة', 'الوصف',
                    ],
                    columnWidths: ['10%', '12%', '16%', '14%', '13%', '13%', '22%'],
                    descriptionColumn: 7,

                    infoCards: [
                        { label: 'الكاشير', value: @json($session->user->name ?? '—') },
                        { label: 'الجهاز', value: @json($session->device->device_name ?? 'غير محدد') },
                        { label: 'الوردية', value: @json($session->shift->name ?? 'غير محددة') },
                        { label: 'عدد الحركات', value: @json(number_format($session->total_transactions)) },
                        { label: 'إجمالي المبيعات', value: @json(number_format($session->total_sales, 2).' '.$symbol) },
                        { label: 'النقدي', value: @json(number_format($session->total_cash, 2).' '.$symbol) },
                        { label: 'الشبكة', value: @json(number_format($session->total_card, 2).' '.$symbol) },
                        { label: 'النقد المتوقَّع', value: @json(number_format($expectedCash, 2).' '.$symbol) },
                    ],

                    filterInfo: {
                        'الجلسة': @json($session->session_number),
                        'الفرع': @json($session->branch->name ?? 'غير محدد'),
                        'الحالة': @json($stateLabel),
                        'الفترة': @json($session->started_at->format('Y-m-d H:i')
                            .' — '.($session->ended_at?->format('Y-m-d H:i') ?? 'مفتوحة')),
                    },
                });
            });
        }

        /* ═══════════ الفرقُ عند الإقفال ═══════════ */
        var actual = document.getElementById('actual_closing_balance');

        if (! actual) {
            return;
        }

        var expected = {{ $expectedCash }};
        var box = document.getElementById('difference-display');
        var amount = document.getElementById('difference-amount');

        var render = function () {
            var diff = (parseFloat(actual.value) || 0) - expected;

            if (Math.abs(diff) < 0.01) {
                box.style.display = 'none';
                return 0;
            }

            box.style.display = '';
            box.className = 'alert mb-0 ' + (diff > 0 ? 'alert-success' : 'alert-warning');
            amount.textContent = (diff > 0 ? '+' : '') + diff.toFixed(2) + ' {{ $symbol }}'
                + (diff > 0 ? ' (فائض)' : ' (عجز)');

            return diff;
        };

        actual.addEventListener('input', render);
        render();

        /*
            التأكيدُ على الفرق الكبير: الإقفالُ يكتب قيداً على حساب الفائض
            أو العجز ولا يُتراجَع عنه بتعديل.

            والحدُّ مكتوبٌ هنا لأنّه ليس إعداداً بعد — لا عمودَ له في
            `pos_general_settings`. وموضعُه الصحيح شاشةُ الإعدادات، وقتَ
            إضافته: منشأةٌ تبيع بالآلاف غير أخرى تبيع بالعشرات.
        */
        var threshold = 50;

        document.getElementById('closeSessionForm').addEventListener('submit', function (e) {
            var diff = render();

            if (Math.abs(diff) <= threshold) {
                return;
            }

            var word = diff > 0 ? 'فائض' : 'عجز';
            var msg = 'الفرقُ ' + word + ' بمقدار ' + Math.abs(diff).toFixed(2) + ' {{ $symbol }}.\n\n'
                + 'سيُقيَّد على حساب ال' + word + ' ولا يُتراجَع عنه. هل الرصيدُ المُدخَل صحيح؟';

            if (! confirm(msg)) {
                e.preventDefault();
            }
        });
    });
</script>
@endsection
