@extends('client::portal.layout')

{{--
    مرتجعات العميل.

    ══════════════════ من أين الأرقام؟ ══════════════════

    من `invoices` بنوع `returned` — لا جدولَ مرتجعاتٍ مستقلّاً في هذا
    النظام. و«الفاتورة الأصلية» علاقةُ `originalInvoice()` على
    `reference_id`، وهي ما يكتبه مسارُ المرتجع في المبيعات.

    ══════════════════ لماذا «استُرِدّ نقداً» لا حالةُ الدفع؟ ══════════════════

    كلُّ مرتجعٍ يُخزَّن بـ`payment_status = 4` ثابتاً، فالعمودُ لا يفرّق
    مرتجعاً عن مرتجع. والذي يعني صاحبَ الحساب: أخرج المبلغُ من الخزينة إليه
    أم نزل من مستحقّه؟ — ويأتي من المتحكّم (`$refunds`) لا من قراءةٍ هنا.

    عرضٌ فقط: المرتجعُ يُنشئه موظّف.
--}}

@section('title', 'المرتجعات')
@section('subtitle', 'ما أرجعتَه من فواتيرك وكيف سُوِّي')

@section('actions')
    <button type="button" class="cp-btn cp-btn--ghost cp-btn--sm" onclick="window.print()">
        <i class="fa fa-print"></i> طباعة
    </button>
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;

        $money = fn ($v) => CurrencySettings::format(abs((float) $v));
    @endphp

    <div class="cp-stats">
        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--info"><i class="fa fa-undo"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ number_format($totalCount) }}</p>
                <p class="cp-stat__label">عدد المرتجعات</p>
            </div>
        </div>

        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--warning"><i class="fa fa-coins"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ $money($totalValue) }}</p>
                <p class="cp-stat__label">إجمالي قيمة المرتجعات</p>
            </div>
        </div>
    </div>

    <div class="cp-card">
        <div class="cp-card__head">
            <h2 class="cp-card__title"><i class="fa fa-undo"></i>مرتجعاتي</h2>
            <span class="cp-badge cp-badge--muted">{{ number_format($totalCount) }} مرتجع</span>
        </div>

        <div class="cp-card__body cp-card__body--flush">
            @if ($returns->isEmpty())
                <div class="cp-empty">
                    <i class="fa fa-undo"></i>
                    <p>لا مرتجعات على حسابك.</p>
                </div>
            @else
                <div class="cp-tablewrap">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>رقم المرتجع</th>
                                <th>التاريخ</th>
                                <th>عن الفاتورة</th>
                                <th class="cp-num">القيمة</th>
                                <th>التسوية</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($returns as $return)
                                @php
                                    $refunded = (float) ($refunds[$return->id] ?? 0);
                                    // الأصليةُ برقمها إن وُجدت العلاقة، وإلّا فرقمُ الصفّ
                                    // الخام (`reference_number`) — فاتورةٌ حُذفت تترك رقماً
                                    // بلا سجلّ، وشرطةٌ مكانه تُخفي مرجعاً ما زال يُفيد
                                    $source = $return->originalInvoice?->code ?: $return->reference_number;
                                @endphp
                                <tr>
                                    <td class="cp-num cp-strong">{{ $return->code }}</td>
                                    <td class="cp-num">
                                        {{ \Illuminate\Support\Carbon::parse($return->invoice_date ?: $return->created_at)->format('Y-m-d') }}
                                    </td>
                                    <td class="cp-num">{{ $source ?: '—' }}</td>
                                    <td class="cp-num">{{ $money($return->grand_total) }}</td>
                                    <td>
                                        @if ($refunded > 0)
                                            <span class="cp-badge cp-badge--success">استُرِدّ نقداً</span>
                                            @if (abs($refunded - (float) $return->grand_total) >= 0.005)
                                                <small style="color:var(--cp-muted)">{{ $money($refunded) }}</small>
                                            @endif
                                        @else
                                            <span class="cp-badge cp-badge--muted">خُصم من حسابك</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($returns->hasPages())
                    <div class="cp-card__body cp-noprint">{{ $returns->links() }}</div>
                @endif
            @endif
        </div>
    </div>

    <p style="font-size:.78rem;color:var(--cp-muted);text-align:center;margin-top:-.5rem">
        «خُصم من حسابك» يعني أنّ قيمة المرتجع نزلت من مستحقّك عندنا — تراها في كشف الحساب.
    </p>
@endsection
