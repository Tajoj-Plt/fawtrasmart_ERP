{{--
    ملخّصُ الجلسة بعد إقفالها.

    ═══════════════ ما كان ═══════════════

    ستُّمئة سطرٍ بترميز Vuexy القديم: `content-header row` وترويسةٌ ومسارٌ
    مكتوبان بيدهما، و`card-title` و`badge-light-*`، وأيقوناتُ `fa`،
    و**«ر.س» مكتوبةٌ في اثني عشر موضعاً** رغم أنّ `$symbol` يُمرَّر من
    المتحكّم.

    و`$drawer` يُمرَّر من المتحكّم — إيداعاتٌ وسحوباتٌ وترحيلٌ ومنتظِرات —
    **ولا يُقرأ في سطرٍ واحد**. فتسويةُ الصندوق تُعرض بمعادلةٍ ناقصة:

        افتتاحي + نقد − مرتجعات = المتوقَّع

    وهي ليست المعادلة. حركاتُ الدرج تدخل فيها (`computeExpectedCash()`
    تحسبها)، فيقرأ المستخدمُ «متوقَّع» لا يوافق مجموعَ ما فوقه ولا يعرف
    لماذا.

    ═══════════════ وما صار ═══════════════

    نظامُ `stk-`، والمعادلةُ كاملةً بحركات الدرج في موضعها، والفرقُ
    مُفرَدٌ بلونه — وهو الرقمُ الذي تُفتح الصفحةُ من أجله.

    والطباعةُ والتصدير بنفس مكوّن تقارير الحسابات والمخزون.
--}}
@extends('pos::master')

@section('title', 'ملخص الجلسة — '.$session->session_number)

@section('content')

@php
    /*
        الأسماءُ كاملةً لا `use`: كتلةُ `@php` تُصيَّر داخل دالّةِ
        القالب، و`use` لا تجوز إلّا في أعلى الملفّ.
    */
    $S = 'App\Models\PosSession';

    $money = fn ($v) => number_format((float) $v, 2).' '.$symbol;

    $diff = (float) ($session->difference ?? 0);
    $balanced = abs($diff) <= 0.01;

    // المعادلةُ كما يحسبها `computeExpectedCash()` — لا ناقصةً منها الحركات
    $drawerNet = (float) ($drawer['net'] ?? 0);
    $expected = (float) ($session->closing_balance ?? $session->computeExpectedCash());

    $statusMap = [
        $S::STATUS_CLOSED => ['مغلقة', 'stk-state--off'],
        $S::STATUS_PENDING_REVIEW => ['بانتظار التأكيد', 'stk-state--neutral'],
        $S::STATUS_ACTIVE => ['نشطة', 'stk-state--on'],
        $S::STATUS_SUSPENDED => ['معلَّقة', 'stk-state--neutral'],
    ];

    [$statusText, $statusClass] = $statusMap[$session->status] ?? ['—', 'stk-state--neutral'];

    $sales = $session->details->where('transaction_type', 'sale');
    $returns = $session->details->where('transaction_type', 'return');

    $hours = $session->ended_at
        ? max($session->started_at->diffInHours($session->ended_at), 0)
        : 0;

    $avgInvoice = $session->total_transactions > 0
        ? (float) $session->total_sales / $session->total_transactions
        : 0.0;
@endphp

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.sessions.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> الجلسات
        </a>

        <button type="button" id="exportExcel" class="btn btn-outline-success">
            <i class="feather icon-file-text"></i> تصدير إكسل
        </button>

        <a href="{{ route('pos.reports.z', $session->id) }}" target="_blank" class="btn btn-outline-primary">
            <i class="feather icon-file"></i> تقرير Z
        </a>

        <button type="button" id="printReport" class="btn btn-primary">
            <i class="feather icon-printer"></i> طباعة
        </button>

        @if ($canConfirm)
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmModal">
                <i class="feather icon-check-circle"></i> تأكيد الإقفال
            </button>
        @endif
    @endsection

    @include('pos::components.page-head', [
        'title' => 'ملخص الجلسة '.$session->session_number,
        'icon' => 'clipboard',
        'sub' => ($session->user->name ?? '—').' · '.$session->started_at->format('Y-m-d H:i')
            .($session->ended_at ? ' ← '.$session->ended_at->format('H:i') : ''),
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الجلسات' => route('pos.sessions.index'),
            'الملخص' => null,
        ],
    ])

    {{--
        الفرقُ أوّلَ ما يُقرأ.

        كان في جدولٍ في منتصف الصفحة بين صفَّي «متوقَّع» و«فعليّ». وهو
        سببُ فتحِ هذه الصفحة أصلاً: أطابق الصندوقُ أم لا.
    --}}
    <div class="ses-verdict {{ $balanced ? 'ses-verdict--ok' : ($diff > 0 ? 'ses-verdict--over' : 'ses-verdict--short') }}">
        <div class="ses-verdict__side">
            <span class="ses-verdict__label">
                {{ $balanced ? 'الصندوق مطابق' : ($diff > 0 ? 'فائض في الصندوق' : 'عجز في الصندوق') }}
            </span>
            <span class="ses-verdict__value">{{ $money(abs($diff)) }}</span>
        </div>

        <div class="ses-verdict__meta">
            <span class="stk-state {{ $statusClass }}">{{ $statusText }}</span>

            @if ($session->confirmed_at)
                <span class="ses-verdict__note">
                    اعتمدها {{ $session->confirmedBy->name ?? '—' }} · {{ $session->confirmed_at->format('Y-m-d H:i') }}
                </span>
            @elseif ($session->needsConfirmation())
                <span class="ses-verdict__note">لم تُعتمد بعد</span>
            @endif
        </div>
    </div>

    {{-- ═══════════ الأرقام ═══════════ --}}
    <div class="stk-metrics">
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
            <span class="stk-metric__value stk-num">{{ $money($session->total_sales) }}</span>
            <span class="stk-metric__label">إجمالي المبيعات</span>
        </div>

        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
            <span class="stk-metric__value stk-num">{{ number_format($session->total_transactions) }}</span>
            <span class="stk-metric__label">عدد الفواتير</span>
        </div>

        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
            <span class="stk-metric__value stk-num">{{ $money($session->total_cash) }}</span>
            <span class="stk-metric__label">تحصيل نقدي</span>
        </div>

        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-credit-card"></i></span>
            <span class="stk-metric__value stk-num">{{ $money($session->total_card) }}</span>
            <span class="stk-metric__label">تحصيل بالبطاقة</span>
        </div>

        <div class="stk-metric {{ (float) $session->total_returns > 0 ? 'stk-metric--danger' : '' }}">
            <span class="stk-metric__icon"><i class="feather icon-corner-up-left"></i></span>
            <span class="stk-metric__value stk-num">{{ $money($session->total_returns) }}</span>
            <span class="stk-metric__label">المرتجعات</span>
        </div>

        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-bar-chart"></i></span>
            <span class="stk-metric__value stk-num">{{ $money($avgInvoice) }}</span>
            <span class="stk-metric__label">متوسط الفاتورة</span>
        </div>
    </div>

    <div class="row">
        {{-- ═══════════ تسوية الصندوق ═══════════ --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="stk-section__head">
                        <h6 class="stk-section__title"><i class="feather icon-inbox"></i> تسوية الصندوق</h6>
                    </div>

                    {{--
                        المعادلةُ كاملة.

                        كانت: افتتاحي + نقد − مرتجعات = المتوقَّع. وحركاتُ
                        الدرج تدخل فيها فعلاً (`computeExpectedCash()`
                        تحسبها) ولا تظهر — فيقرأ المستخدمُ متوقَّعاً لا
                        يوافق مجموعَ ما فوقه.
                    --}}
                    <div class="ses-eq">
                        <div class="ses-eq__row">
                            <span>الرصيد الافتتاحي</span>
                            <b>{{ $money($session->opening_balance) }}</b>
                        </div>

                        <div class="ses-eq__row">
                            <span>التحصيل النقدي</span>
                            <b class="ses-pos">+ {{ $money($session->total_cash) }}</b>
                        </div>

                        @if ((float) $session->cashRefundsTotal() > 0)
                            <div class="ses-eq__row">
                                <span>مرتجعات صُرفت نقداً</span>
                                <b class="ses-neg">− {{ $money($session->cashRefundsTotal()) }}</b>
                            </div>
                        @endif

                        @if ((float) ($drawer['pay_in'] ?? 0) > 0)
                            <div class="ses-eq__row">
                                <span>إيداعات في الدرج</span>
                                <b class="ses-pos">+ {{ $money($drawer['pay_in']) }}</b>
                            </div>
                        @endif

                        @if ((float) ($drawer['pay_out'] ?? 0) > 0)
                            <div class="ses-eq__row">
                                <span>سحوبات من الدرج</span>
                                <b class="ses-neg">− {{ $money($drawer['pay_out']) }}</b>
                            </div>
                        @endif

                        @if ((float) ($drawer['drop'] ?? 0) > 0)
                            <div class="ses-eq__row">
                                <span>ترحيل إلى الخزينة</span>
                                <b class="ses-neg">− {{ $money($drawer['drop']) }}</b>
                            </div>
                        @endif

                        <div class="ses-eq__row ses-eq__row--sum">
                            <span>النقد المتوقَّع</span>
                            <b>{{ $money($expected) }}</b>
                        </div>

                        <div class="ses-eq__row">
                            <span>النقد الفعلي (المعدود)</span>
                            <b>{{ $money($session->actual_closing_balance) }}</b>
                        </div>

                        <div class="ses-eq__row ses-eq__row--diff {{ $balanced ? 'is-ok' : ($diff > 0 ? 'is-over' : 'is-short') }}">
                            <span>{{ $balanced ? 'الفرق — مطابق' : 'الفرق' }}</span>
                            <b>{{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2) }} {{ $symbol }}</b>
                        </div>
                    </div>

                    @if ((int) ($drawer['pending'] ?? 0) > 0)
                        <p class="stk-help mb-0">
                            <i class="feather icon-clock"></i>
                            {{ $drawer['pending'] }} حركةَ درجٍ بانتظار الاعتماد — لا تدخل الرصيدَ المتوقَّع حتى تُعتمد.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══════════ بطاقةُ الجلسة والتحصيل ═══════════ --}}
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <div class="stk-section__head">
                        <h6 class="stk-section__title"><i class="feather icon-info"></i> بيانات الجلسة</h6>
                    </div>

                    <dl class="stk-def stk-def--inline">
                        <div class="stk-def__row"><dt>الكاشير</dt><dd>{{ $session->user->name ?? '—' }}</dd></div>
                        <div class="stk-def__row"><dt>الفرع</dt><dd>{{ $session->branch->name ?? '—' }}</dd></div>
                        <div class="stk-def__row"><dt>الجهاز</dt><dd>{{ $session->device->device_name ?? '—' }}</dd></div>
                        <div class="stk-def__row"><dt>الوردية</dt><dd>{{ $session->shift->name ?? '—' }}</dd></div>
                        <div class="stk-def__row">
                            <dt>الفتح</dt><dd>{{ $session->started_at->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الإقفال</dt>
                            <dd>{{ $session->ended_at?->format('Y-m-d H:i') ?? 'مستمرّة' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>المدّة</dt>
                            <dd>
                                {{ $session->ended_at
                                    ? $session->started_at->diff($session->ended_at)->format('%h ساعة %i دقيقة')
                                    : '—' }}
                            </dd>
                        </div>
                        @if ($session->closedBy)
                            <div class="stk-def__row"><dt>أقفلها</dt><dd>{{ $session->closedBy->name }}</dd></div>
                        @endif
                    </dl>

                    @php
                        $totalPay = (float) $session->total_cash + (float) $session->total_card;
                        $cashPct = $totalPay > 0 ? ($session->total_cash / $totalPay) * 100 : 0;
                    @endphp

                    @if ($totalPay > 0)
                        <div class="stk-section__head mt-2">
                            <h6 class="stk-section__title"><i class="feather icon-pie-chart"></i> توزيع التحصيل</h6>
                        </div>

                        <div class="stk-bar">
                            <span class="stk-bar__fill stk-bar--success" style="width: {{ round($cashPct, 1) }}%"></span>
                        </div>
                        <p class="stk-help mb-0">
                            نقداً {{ round($cashPct, 1) }}٪ · بطاقة {{ round(100 - $cashPct, 1) }}٪
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ سجلُّ المعاملات ═══════════ --}}
    <div class="card">
        <div class="card-body">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> سجل المعاملات
                    <span class="stk-count">{{ $session->details->count() }}</span>
                </h6>

                <span class="stk-tag">{{ $sales->count() }} بيع · {{ $returns->count() }} مرتجع</span>
            </div>

            @if ($session->details->count())
                <div class="table-responsive">
                    <table id="sessionTable" class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>الوقت</th>
                                <th>النوع</th>
                                <th>المرجع</th>
                                <th class="text-center">المبلغ</th>
                                <th class="text-center">نقداً</th>
                                <th class="text-center">بطاقة</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($session->details->sortBy('transaction_time') as $detail)
                                @php
                                    $kind = match ($detail->transaction_type) {
                                        'sale' => ['بيع', 'stk-state--on'],
                                        'return' => ['مرتجع', 'stk-state--off'],
                                        'opening_balance' => ['رصيد افتتاحي', 'stk-state--neutral'],
                                        default => [$detail->transaction_type, 'stk-state--neutral'],
                                    };
                                @endphp

                                <tr>
                                    <td class="stk-num">{{ optional($detail->transaction_time)->format('H:i') }}</td>
                                    <td><span class="stk-state {{ $kind[1] }}">{{ $kind[0] }}</span></td>
                                    <td>{{ $detail->reference_number ?: '—' }}</td>
                                    <td class="text-center stk-num">{{ number_format((float) $detail->amount, 2) }}</td>
                                    <td class="text-center stk-num">
                                        {{ (float) $detail->cash_amount != 0 ? number_format((float) $detail->cash_amount, 2) : '—' }}
                                    </td>
                                    <td class="text-center stk-num">
                                        {{ (float) $detail->card_amount > 0 ? number_format((float) $detail->card_amount, 2) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-list"></i></span>
                    <h6 class="stk-empty__title">لا معاملات</h6>
                    <p class="stk-empty__hint">أُقفلت الجلسةُ بلا بيعٍ ولا مرتجع.</p>
                </div>
            @endif
        </div>
    </div>

    @if ($session->closing_notes)
        <div class="card">
            <div class="card-body">
                <div class="stk-section__head">
                    <h6 class="stk-section__title"><i class="feather icon-message-square"></i> ملاحظات الإقفال</h6>
                </div>
                <p class="mb-0">{{ $session->closing_notes }}</p>
            </div>
        </div>
    @endif
</div>

{{-- ═══════════ نافذةُ تأكيد الإقفال ═══════════ --}}
@if ($canConfirm)
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('pos.sessions.confirm', $session->id) }}" method="POST" class="modal-content">
                @csrf

                <div class="modal-header">
                    <h6 class="modal-title">تأكيد إقفال {{ $session->session_number }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>

                <div class="modal-body">
                    @unless ($balanced)
                        <div class="alert alert-warning">
                            <strong>فرقٌ في الصندوق: {{ number_format($diff, 2) }} {{ $symbol }}</strong>
                            — تأكيدُك يعتمده ويُقفل المراجعة.
                        </div>
                    @endunless

                    <div class="form-group mb-0">
                        <label for="confirmation_notes">ملاحظات الاعتماد</label>
                        <textarea id="confirmation_notes" name="confirmation_notes" rows="3"
                                  class="form-control" placeholder="سببُ الفرق أو ما اتُّفق عليه…"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">
                        <i class="feather icon-check"></i> اعتماد الإقفال
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@include('pos::components.report-tools')

@php
    /*
        تُبنى في PHP لا داخل `@json(...)`: مُحلّلُ توجيهات Blade يعدّ
        الأقواسَ عدّاً بسيطاً، فمصفوفةٌ متعدّدةُ الأسطر داخل التوجيه تُنهيه
        عند أوّل قوسٍ مغلق — «Unclosed '[' does not match ')'».
    */
    $printInfo = [
        'الكاشير' => $session->user->name ?? '—',
        'الفرع' => $session->branch->name ?? '—',
        'الجهاز' => $session->device->device_name ?? '—',
        'الوردية' => $session->shift->name ?? '—',
        'الفتح' => $session->started_at->format('Y-m-d H:i'),
        'الإقفال' => $session->ended_at?->format('Y-m-d H:i') ?? 'مستمرّة',
    ];

    $printCards = [
        ['title' => 'إجمالي المبيعات', 'value' => number_format((float) $session->total_sales, 2)],
        ['title' => 'عدد الفواتير', 'value' => number_format((int) $session->total_transactions)],
        ['title' => 'النقد المتوقَّع', 'value' => number_format($expected, 2)],
        ['title' => 'النقد الفعلي', 'value' => number_format((float) $session->actual_closing_balance, 2)],
        ['title' => 'الفرق', 'value' => number_format($diff, 2)],
    ];

    $printTitle = 'ملخص الجلسة '.$session->session_number;
    $printSub = ($session->user->name ?? '').' · '.$session->started_at->format('Y-m-d');
    $printNumeric = [3, 4, 5];
@endphp

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof stkReportTools !== 'function') {
            return;
        }

        stkReportTools({
            table: '#sessionTable',
            fileName: @json($printTitle, JSON_UNESCAPED_UNICODE),
            title: @json($printTitle, JSON_UNESCAPED_UNICODE),
            subTitle: @json($printSub, JSON_UNESCAPED_UNICODE),
            info: @json($printInfo, JSON_UNESCAPED_UNICODE),
            cards: @json($printCards, JSON_UNESCAPED_UNICODE),
            numericColumns: @json($printNumeric),
            descriptionColumn: 2,
            exportButton: '#exportExcel',
            printButton: '#printReport',
        });
    });
</script>

@once
@push('styles')
<style>
    /*
        الحكمُ على الصندوق — أوّلُ ما يُقرأ.

        كان الفرقُ صفّاً في جدولٍ في منتصف الصفحة. وهو سببُ فتحِ هذه
        الصفحة أصلاً: أطابق الصندوقُ أم لا.
    */
    .ses-verdict {
        display: flex; justify-content: space-between; align-items: center;
        gap: 16px; flex-wrap: wrap;
        margin-bottom: var(--stk-4);
        padding: 14px 20px;
        border: 1px solid; border-radius: var(--stk-r-lg);
    }

    .ses-verdict__label { display: block; font-size: .82rem; font-weight: 700; opacity: .85; }

    .ses-verdict__value {
        display: block; font-size: 1.7rem; font-weight: 800;
        letter-spacing: -.02em; font-variant-numeric: tabular-nums;
    }

    .ses-verdict__meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ses-verdict__note { font-size: .78rem; opacity: .8; }

    .ses-verdict--ok { border-color: #2F6F4E; background: #EFF6F2; color: #2F6F4E; }
    .ses-verdict--over { border-color: #8A5A2B; background: #FBF5EE; color: #8A5A2B; }
    .ses-verdict--short { border-color: #B23A2E; background: #FBF0EE; color: #B23A2E; }

    /* ─── معادلةُ التسوية ─── */

    .ses-eq { font-variant-numeric: tabular-nums; }

    .ses-eq__row {
        display: flex; justify-content: space-between; gap: 12px;
        padding: 7px 0;
        border-bottom: 1px solid var(--stk-line-soft);
        font-size: .88rem;
    }

    .ses-eq__row:last-child { border-bottom: none; }
    .ses-eq__row span { color: var(--app-text); opacity: .75; }
    .ses-eq__row b { font-weight: 700; }

    .ses-eq__row--sum {
        margin-top: 4px; padding-top: 10px;
        border-top: 1px solid var(--stk-line);
    }

    .ses-eq__row--sum span { opacity: 1; font-weight: 700; }
    .ses-eq__row--sum b { font-size: 1.05rem; }

    .ses-pos { color: #2F6F4E; }
    .ses-neg { color: #B23A2E; }

    .ses-eq__row--diff {
        margin-top: 6px; padding: 9px 12px;
        border: 1px solid; border-radius: var(--stk-r);
        font-weight: 800;
    }

    .ses-eq__row--diff span { opacity: 1; font-weight: 700; }
    .ses-eq__row--diff.is-ok { border-color: #2F6F4E; color: #2F6F4E; background: #EFF6F2; }
    .ses-eq__row--diff.is-over { border-color: #8A5A2B; color: #8A5A2B; background: #FBF5EE; }
    .ses-eq__row--diff.is-short { border-color: #B23A2E; color: #B23A2E; background: #FBF0EE; }
</style>
@endpush
@endonce

@endsection
