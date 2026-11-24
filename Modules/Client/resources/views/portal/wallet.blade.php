@extends('client::portal.layout')

{{--
    محفظة العميل.

    ══════════════════ من أين الرصيد؟ ══════════════════

    من `ClientWalletService::balance()` — الرقمُ نفسُه الذي تعرضه شاشةُ
    الموظّف، مقروءاً من `accounts.balance`. ولا يُجمَع هنا من الحركات:
    مصدرُ رصيدٍ ثانٍ يعني رقمين لرصيدٍ واحد.

    و«الرصيد بعده» في كل سطر عمودٌ مخزَّن (`balance_after`) كتبته الخدمةُ
    ساعةَ الحركة، لا تسلسلاً يُحسَب في القالب — فالصفحةُ الثانية لا تعرف ما
    قبلها.

    ══════════════════ الاتّجاه ══════════════════

    المحفظةُ مالُ العميل عندنا (حسابٌ في الخصوم): الإيداعُ والردُّ يزيدانه،
    والسحبُ وسدادُ الفاتورة ينقصانه — و`isCredit()` على النموذج هي من تقرّر،
    لا قائمةُ أنواعٍ مكتوبةٌ هنا.
--}}

@section('title', 'محفظتي')
@section('subtitle', 'رصيدك المحفوظ عندنا وحركته')

@section('actions')
    <button type="button" class="cp-btn cp-btn--ghost cp-btn--sm" onclick="window.print()">
        <i class="fa fa-print"></i> طباعة
    </button>
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;

        $money = fn ($v) => CurrencySettings::format(abs((float) $v));
        $balance = (float) $balance;
    @endphp

    <div class="cp-stats">
        <div class="cp-stat">
            <span class="cp-stat__icon {{ $balance > 0 ? 'cp-stat__icon--success' : 'cp-stat__icon--info' }}">
                <i class="fa fa-wallet"></i>
            </span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ $money($balance) }}</p>
                <p class="cp-stat__label">{{ $balance > 0 ? 'رصيد محفظتك الحالي' : 'محفظتك فارغة' }}</p>
            </div>
        </div>

        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--success"><i class="fa fa-arrow-down"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ $money($totalIn) }}</p>
                <p class="cp-stat__label">إجمالي ما أُضيف للمحفظة</p>
            </div>
        </div>

        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--warning"><i class="fa fa-arrow-up"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ $money($totalOut) }}</p>
                <p class="cp-stat__label">إجمالي ما صُرف منها</p>
            </div>
        </div>
    </div>

    <div class="cp-card">
        <div class="cp-card__head">
            <h2 class="cp-card__title"><i class="fa fa-wallet"></i>حركة المحفظة</h2>
            <span class="cp-badge cp-badge--muted">{{ number_format($transactions->total()) }} حركة</span>
        </div>

        <div class="cp-card__body cp-card__body--flush">
            @if ($transactions->isEmpty())
                {{-- الجدولُ فارغٌ عند أوّل عميل: الرسالةُ تقول ما المحفظة وكيف
                     تمتلئ، فلا يُقرأ الفراغُ عطلاً --}}
                <div class="cp-empty">
                    <i class="fa fa-wallet"></i>
                    <p>لا حركات على محفظتك بعد.</p>
                    <p class="cp-muted" style="font-size:.8rem">
                        المحفظة رصيدٌ تحفظه عندنا فتُسدَّد منه فواتيرك — تُشحَن بإيداعٍ لدى موظّفينا.
                    </p>
                </div>
            @else
                <div class="cp-tablewrap">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الحركة</th>
                                <th>البيان</th>
                                <th class="cp-num">وارد</th>
                                <th class="cp-num">صادر</th>
                                <th class="cp-num">الرصيد بعدها</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $transaction)
                                @php $in = $transaction->isCredit(); @endphp
                                <tr>
                                    <td class="cp-num">{{ $transaction->created_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        <span class="cp-badge cp-badge--{{ $in ? 'success' : 'warning' }}">
                                            {{ $transaction->typeLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($transaction->invoice)
                                            فاتورة {{ $transaction->invoice->code ?: '#'.$transaction->invoice_id }}
                                        @endif
                                        {{-- الملاحظةُ تكمّل الفاتورة ولا تحلّ محلّها: حركةٌ
                                             على فاتورةٍ بملاحظةٍ تحتاج الاثنتين --}}
                                        @if ($transaction->note)
                                            <small style="color:var(--cp-muted)">{{ $transaction->note }}</small>
                                        @elseif (! $transaction->invoice)
                                            —
                                        @endif
                                    </td>

                                    {{-- خانةٌ صفرها فارغة: صفرٌ مطبوعٌ في عمود «صادر»
                                         يجعل العين تقرأ حركةً لم تقع --}}
                                    <td class="cp-num">{{ $in ? $money($transaction->amount) : '' }}</td>
                                    <td class="cp-num">{{ $in ? '' : $money($transaction->amount) }}</td>
                                    <td class="cp-num cp-strong">{{ $money($transaction->balance_after) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($transactions->hasPages())
                    <div class="cp-card__body cp-noprint">{{ $transactions->links() }}</div>
                @endif
            @endif
        </div>
    </div>

    <p style="font-size:.78rem;color:var(--cp-muted);text-align:center;margin-top:-.5rem">
        المحفظة غير كشف الحساب: هذه مالُك المحفوظ عندنا، وذاك ما لك وما عليك من فواتير.
    </p>
@endsection
