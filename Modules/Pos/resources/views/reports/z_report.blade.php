{{--
    تقريرا Z وX.

    ═══════════ التقريرُ رقم واحد ═══════════

    الملخّصُ الذي يُطبع عند إقفال الوردية ويُسلَّم مع العهدة — **ولم يكن
    موجوداً في الوحدة إطلاقاً**، وهو أوّلُ ما يُطلب في أيّ نقطة بيع.

    ولم يكن ممكناً قبل الآن: التحصيلُ بكل طريقة يحتاج جدولَ المدفوعات (ولم
    يكن يُكتب)، وحركاتُ الدرج تحتاج جدولها (ولم يكن موجوداً)، والرصيدُ
    المتوقَّع يحتاج أن يشملهما.

    والفرقُ بين X وZ اصطلاحيّ: X يُقرأ أثناء الوردية ولا يُقفلها، وZ يُطبع
    عند الإقفال — فالقالبُ واحدٌ والترويسةُ تميّز.

    ═══════════ ما كان في التصميم ═══════════

    جداولُ بوتستراب عاريةً (`table-sm` و`fw-bold` و`text-end`) وعناوينُ
    `<h5>` بخطٍّ أسفلها، و`window.print()` مجرَّدةً بلا ورقةٍ مصمَّمة:
    تخرج الصفحةُ بخطِّ المتصفّح الافتراضي، وبألوانٍ لا تُطبع، وبفواصلَ
    تقطع الجدولَ في منتصفه.

    ═══════════ ولماذا نمطٌ خاصٌّ هنا ═══════════

    هذه الشاشةُ وحدها تمتدّ من `pos::layouts.blank` — لا شريطَ جانبيّ ولا
    ترويسة، لأنها مستندٌ يُطبع لا صفحةٌ تُتصفَّح. فلا يصلها نظامُ `stk-`
    ولا خطُّ Cairo، والنمطُ أدناه مكتفٍ بنفسه لهذا السبب لا لغيره.

    والورقةُ مصمَّمةٌ للطباعة أصلاً: مقاسُ إيصالٍ ضيّق، وحبرٌ أسود، وحدودٌ
    تظهر على الورق، وصفوفٌ لا تنقسم بين صفحتين.
--}}
@extends('pos::layouts.blank')

@section('title'){{ $isFinal ? 'تقرير Z' : 'تقرير X' }} — {{ $session->session_number }}@stop

@section('content')

@php
    $diff = (float) ($data['cash']['difference'] ?? 0);
    $hasActual = $data['cash']['actual'] !== null;

    $money = fn ($v, $withSymbol = false) => number_format((float) $v, 2).($withSymbol ? ' '.$symbol : '');
@endphp

<div class="zr-shell">

    <div class="zr" id="zReport">

        {{-- ═══════════ الترويسة ═══════════ --}}
        <header class="zr-head">
            <h1 class="zr-head__org">{{ \App\Support\OrganizationInfo::name() }}</h1>

            @if ($tax = \App\Support\OrganizationInfo::taxNumber())
                <p class="zr-head__tax">الرقم الضريبي: {{ $tax }}</p>
            @endif

            <p class="zr-head__kind">
                {{ $isFinal ? 'تقرير Z — إقفال الوردية' : 'تقرير X — قراءة أثناء الوردية' }}
            </p>

            @unless ($isFinal)
                {{--
                    التحذيرُ في متن الورقة لا في الشاشة وحدها.

                    تقريرُ X يُطبع ويُترك على الطاولة، فمن يقرؤه بعد ساعةٍ
                    لا يعرف أنّ أرقامَه كانت مؤقّتة — إلّا أن تقولها الورقةُ
                    نفسُها.
                --}}
                <p class="zr-provisional">الجلسة ما زالت مفتوحة — الأرقام غير نهائية</p>
            @endunless
        </header>

        {{-- ═══════════ بطاقةُ الجلسة ═══════════ --}}
        <dl class="zr-facts">
            <div><dt>رقم الجلسة</dt><dd class="zr-strong">{{ $session->session_number }}</dd></div>
            <div><dt>الكاشير</dt><dd>{{ $session->user->name ?? '—' }}</dd></div>
            <div><dt>الفرع</dt><dd>{{ $session->branch->name ?? '—' }}</dd></div>
            <div><dt>الجهاز</dt><dd>{{ $session->device->device_name ?? '—' }}</dd></div>
            <div><dt>الوردية</dt><dd>{{ $session->shift->name ?? '—' }}</dd></div>
            <div><dt>من</dt><dd>{{ $session->started_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div><dt>إلى</dt><dd>{{ $session->ended_at?->format('Y-m-d H:i') ?? 'مستمرّة' }}</dd></div>
        </dl>

        {{-- ═══════════ المبيعات ═══════════ --}}
        <section class="zr-sec">
            <h2 class="zr-sec__title">المبيعات</h2>

            <div class="zr-row"><span>عدد الفواتير</span><b>{{ number_format($data['sales']['count']) }}</b></div>
            <div class="zr-row"><span>الوعاء (قبل الضريبة)</span><b>{{ $money($data['sales']['subtotal']) }}</b></div>
            <div class="zr-row"><span>الخصومات</span><b>{{ $money($data['sales']['discount']) }}</b></div>
            <div class="zr-row"><span>الضريبة</span><b>{{ $money($data['sales']['tax']) }}</b></div>
            <div class="zr-row zr-row--sum"><span>إجمالي المبيعات</span><b>{{ $money($data['sales']['total']) }}</b></div>
        </section>

        {{-- ═══════════ المرتجعات ═══════════ --}}
        <section class="zr-sec">
            <h2 class="zr-sec__title">المرتجعات</h2>

            <div class="zr-row"><span>عدد المرتجعات</span><b>{{ number_format($data['returns']['count']) }}</b></div>
            <div class="zr-row"><span>ضريبة مردودة</span><b>{{ $money($data['returns']['tax']) }}</b></div>
            <div class="zr-row zr-row--sum zr-neg">
                <span>إجمالي المرتجعات</span><b>− {{ $money($data['returns']['total']) }}</b>
            </div>
        </section>

        <div class="zr-net">
            <span>صافي المبيعات</span>
            <b>{{ $money($data['net_sales'], true) }}</b>
        </div>

        {{-- ═══════════ التحصيل ═══════════ --}}
        <section class="zr-sec">
            <h2 class="zr-sec__title">التحصيل حسب طريقة الدفع</h2>

            <table class="zr-table">
                <thead>
                    <tr>
                        <th>الطريقة</th>
                        <th class="zr-c">العدد</th>
                        <th class="zr-e">المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['payments'] as $payment)
                        <tr>
                            <td>
                                {{ $payment->name }}
                                @if ($payment->is_online)<span class="zr-dim">(إلكتروني)</span>@endif
                            </td>
                            <td class="zr-c">{{ number_format($payment->count) }}</td>
                            <td class="zr-e">{{ $money($payment->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="zr-c zr-dim">لا مدفوعات مسجَّلة</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">الإجمالي</td>
                        <td class="zr-e">{{ $money($data['payments']->sum('total')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        {{-- ═══════════ الدرج ═══════════ --}}
        <section class="zr-sec">
            <h2 class="zr-sec__title">الدرج النقدي</h2>

            <div class="zr-row"><span>الرصيد الافتتاحي</span><b>{{ $money($data['cash']['opening']) }}</b></div>
            <div class="zr-row"><span>إيداعات</span><b class="zr-pos">+ {{ $money($data['drawer']['pay_in']) }}</b></div>
            <div class="zr-row"><span>سحوبات</span><b class="zr-neg">− {{ $money($data['drawer']['pay_out']) }}</b></div>
            <div class="zr-row"><span>ترحيل إلى الخزينة</span><b class="zr-neg">− {{ $money($data['drawer']['drop']) }}</b></div>
            <div class="zr-row zr-row--sum"><span>الرصيد المتوقَّع</span><b>{{ $money($data['cash']['expected']) }}</b></div>

            @if ($hasActual)
                <div class="zr-row"><span>الرصيد الفعلي</span><b>{{ $money($data['cash']['actual']) }}</b></div>

                {{--
                    الفرقُ هو الرقمُ الذي يُراجَع — فيُفرَد في إطارٍ ملوَّن
                    لا في صفٍّ كبقيّة الصفوف. وصفرٌ يُعلَّم مطابقاً صراحةً،
                    فسكوتُ الورقة عن الفرق لا يُقرأ «لا فرق».
                --}}
                <div class="zr-diff {{ abs($diff) > 0.01 ? ($diff > 0 ? 'zr-diff--over' : 'zr-diff--short') : 'zr-diff--ok' }}">
                    <span>
                        @if (abs($diff) <= 0.01)
                            مطابق — لا فرق
                        @else
                            {{ $diff > 0 ? 'فائض في الدرج' : 'عجز في الدرج' }}
                        @endif
                    </span>
                    <b>{{ $money($diff) }}</b>
                </div>
            @endif
        </section>

        @if ($data['movements_detail']->isNotEmpty())
            <section class="zr-sec">
                <h2 class="zr-sec__title">حركات الدرج</h2>

                <table class="zr-table">
                    <thead>
                        <tr><th>الحركة</th><th>السبب</th><th class="zr-e">المبلغ</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($data['movements_detail'] as $movement)
                            <tr>
                                <td>{{ \Modules\Pos\Models\PosCashMovement::typeLabel($movement->type) }}</td>
                                <td class="zr-dim">{{ $movement->reason ?: '—' }}</td>
                                <td class="zr-e">{{ $money($movement->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        {{-- ═══════════ الأصناف ═══════════ --}}
        <section class="zr-sec">
            <h2 class="zr-sec__title">أكثر الأصناف مبيعاً</h2>

            <table class="zr-table">
                <thead>
                    <tr><th>الصنف</th><th class="zr-c">الكمية</th><th class="zr-e">القيمة</th></tr>
                </thead>
                <tbody>
                    @forelse ($data['top_products'] as $product)
                        <tr>
                            <td>{{ $product->item }}</td>
                            <td class="zr-c">{{ number_format((float) $product->quantity, 2) }}</td>
                            <td class="zr-e">{{ $money($product->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="zr-c zr-dim">لا مبيعات</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- ═══════════ التوقيعات ═══════════ --}}
        <div class="zr-signs">
            <div class="zr-sign">
                <span class="zr-sign__role">الكاشير</span>
                <span class="zr-sign__line">{{ $session->closedBy->name ?? $session->user->name ?? '' }}</span>
            </div>

            <div class="zr-sign">
                <span class="zr-sign__role">اعتماد المشرف</span>
                <span class="zr-sign__line">
                    @if ($session->confirmed_at)
                        {{ $session->confirmedBy->name ?? '—' }}
                    @endif
                </span>
                @if ($session->confirmed_at)
                    <span class="zr-sign__at">{{ $session->confirmed_at->format('Y-m-d H:i') }}</span>
                @endif
            </div>
        </div>

        <p class="zr-foot">طُبع في {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    {{-- خارج الورقة: لا يُطبع --}}
    <div class="zr-bar">
        <button type="button" onclick="window.print()" class="zr-btn zr-btn--go">طباعة</button>
        <a href="{{ route('pos.sessions.show', $session->id) }}" class="zr-btn">رجوع إلى الجلسة</a>
    </div>
</div>

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
    /*
        ═══════════ ورقةُ التقرير ═══════════

        الشاشةُ تعرض ما سيخرج من الطابعة لا صفحةَ ويبٍ تُختصر عند الطباعة:
        نفسُ العرض، ونفسُ الخط، ونفسُ الحدود. فما يُرى هو ما يُطبع.
    */
    :root {
        --zr-ink: #1B2437;
        --zr-ink-2: #4A5468;
        --zr-dim: #8A93A6;
        --zr-line: #D9DEE7;
        --zr-line-2: #EDF0F5;
        --zr-go: #2F6F4E;
        --zr-stop: #B23A2E;
        --zr-warn: #8A5A2B;
    }

    body {
        background: #EEF1F6;
        font-family: 'Cairo', 'Segoe UI', sans-serif;
        color: var(--zr-ink);
        margin: 0;
    }

    .zr-shell { padding: 24px 12px 48px; }

    .zr {
        max-width: 520px;
        margin: 0 auto;
        background: #fff;
        padding: 22px 24px 26px;
        border: 1px solid var(--zr-line);
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(16, 24, 40, .06), 0 12px 28px rgba(16, 24, 40, .07);
        font-size: 13px;
        line-height: 1.55;
        font-variant-numeric: tabular-nums;
    }

    /* ─── الترويسة ─── */

    .zr-head { text-align: center; padding-bottom: 14px; border-bottom: 2px solid var(--zr-ink); }
    .zr-head__org { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -.01em; }
    .zr-head__tax { margin: 2px 0 0; font-size: 11.5px; color: var(--zr-ink-2); }

    .zr-head__kind {
        margin: 9px 0 0;
        font-size: 13.5px; font-weight: 700;
        color: var(--zr-ink-2);
    }

    /* مؤقّتٌ يُعلَّم على الورق نفسِه، لا على الشاشة وحدها */
    .zr-provisional {
        margin: 9px 0 0;
        padding: 5px 10px;
        border: 1px dashed var(--zr-warn);
        border-radius: 6px;
        color: var(--zr-warn);
        font-size: 11.5px; font-weight: 700;
    }

    /* ─── بطاقةُ الجلسة ─── */

    .zr-facts { margin: 14px 0 0; padding: 0; }

    .zr-facts > div {
        display: flex; justify-content: space-between; gap: 12px;
        padding: 3px 0;
    }

    .zr-facts dt { margin: 0; color: var(--zr-ink-2); font-size: 12px; }
    .zr-facts dd { margin: 0; font-weight: 600; text-align: end; }
    .zr-strong { font-weight: 800 !important; }

    /* ─── الأقسام ─── */

    .zr-sec { margin-top: 16px; }

    .zr-sec__title {
        margin: 0 0 7px;
        font-size: 12px; font-weight: 800;
        letter-spacing: .02em;
        color: var(--zr-ink-2);
        padding-bottom: 4px;
        border-bottom: 1px solid var(--zr-line);
    }

    .zr-row {
        display: flex; justify-content: space-between; gap: 12px;
        padding: 3.5px 0;
        border-bottom: 1px solid var(--zr-line-2);
    }

    .zr-row:last-child { border-bottom: none; }
    .zr-row span { color: var(--zr-ink-2); }
    .zr-row b { font-weight: 700; }

    .zr-row--sum {
        margin-top: 3px; padding-top: 7px;
        border-top: 1px solid var(--zr-line);
        border-bottom: none;
    }

    .zr-row--sum span { color: var(--zr-ink); font-weight: 700; }

    .zr-pos { color: var(--zr-go); }
    .zr-neg b, b.zr-neg { color: var(--zr-stop); }
    .zr-dim { color: var(--zr-dim); font-size: 11.5px; }

    /* صافي المبيعات — الرقمُ الذي يُقرأ أوّلاً */
    .zr-net {
        display: flex; justify-content: space-between; align-items: baseline;
        margin-top: 14px; padding: 11px 14px;
        background: #F4F6FA;
        border: 1px solid var(--zr-line);
        border-radius: 8px;
    }

    .zr-net span { font-weight: 700; font-size: 13px; }
    .zr-net b { font-size: 21px; font-weight: 800; letter-spacing: -.02em; }

    /* ─── الجداول ─── */

    .zr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }

    .zr-table th {
        text-align: start; font-weight: 700; font-size: 11.5px;
        color: var(--zr-ink-2);
        padding: 5px 6px;
        border-bottom: 1px solid var(--zr-line);
    }

    .zr-table td { padding: 5px 6px; border-bottom: 1px solid var(--zr-line-2); }
    .zr-table tbody tr:last-child td { border-bottom: none; }

    .zr-table tfoot td {
        padding: 7px 6px;
        border-top: 1px solid var(--zr-line);
        font-weight: 800;
    }

    .zr-c { text-align: center; }
    .zr-e { text-align: end; }

    /* ─── الفرق ─── */

    .zr-diff {
        display: flex; justify-content: space-between; align-items: baseline;
        margin-top: 9px; padding: 8px 12px;
        border-radius: 7px; border: 1px solid;
        font-weight: 800;
    }

    .zr-diff b { font-size: 16px; }
    .zr-diff--ok { border-color: var(--zr-go); color: var(--zr-go); background: #EFF6F2; }
    .zr-diff--over { border-color: var(--zr-warn); color: var(--zr-warn); background: #FBF5EE; }
    .zr-diff--short { border-color: var(--zr-stop); color: var(--zr-stop); background: #FBF0EE; }

    /* ─── التوقيعات ─── */

    .zr-signs {
        display: grid; grid-template-columns: 1fr 1fr; gap: 18px;
        margin-top: 26px;
    }

    .zr-sign { text-align: center; }
    .zr-sign__role { display: block; font-size: 11.5px; color: var(--zr-ink-2); font-weight: 700; }

    .zr-sign__line {
        display: block; min-height: 32px;
        margin-top: 4px; padding-top: 22px;
        border-bottom: 1px solid var(--zr-ink);
        font-weight: 600;
    }

    .zr-sign__at { display: block; margin-top: 3px; font-size: 10.5px; color: var(--zr-dim); }

    .zr-foot { margin: 18px 0 0; text-align: center; font-size: 10.5px; color: var(--zr-dim); }

    /* ─── شريطُ الأزرار (خارج الورقة) ─── */

    .zr-bar { display: flex; justify-content: center; gap: 8px; margin-top: 18px; }

    .zr-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 20px;
        border: 1px solid var(--zr-line);
        border-radius: 8px;
        background: #fff; color: var(--zr-ink-2);
        font-family: inherit; font-size: 13px; font-weight: 700;
        text-decoration: none; cursor: pointer;
        transition: background-color .12s ease, border-color .12s ease;
    }

    .zr-btn:hover { background: #F4F6FA; color: var(--zr-ink); }
    .zr-btn--go { background: var(--zr-ink); border-color: var(--zr-ink); color: #fff; }
    .zr-btn--go:hover { background: #0F1726; color: #fff; }

    /*
        ═══════════ الطباعة ═══════════

        كانت `window.print()` على صفحةِ بوتستراب: خطُّ المتصفّح الافتراضي،
        وظلالٌ وحوافٌّ لا معنى لها على الورق، وألوانٌ تُسقطها الطابعةُ
        فيصير «فائض» و«عجز» نصّاً رمادياً واحداً، وجداولُ تنقسم بين صفحتين.
    */
    @media print {
        @page { size: A4; margin: 12mm; }

        body { background: #fff; }
        .zr-shell { padding: 0; }
        .zr-bar { display: none !important; }

        .zr {
            max-width: 100%;
            border: none; border-radius: 0; box-shadow: none;
            padding: 0;
            font-size: 11pt;
        }

        /* الألوانُ الدلالية تُطبع — الفائضُ والعجزُ يُقرآن بلونهما */
        .zr-diff, .zr-net, .zr-provisional {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .zr-sec, .zr-signs, .zr-net, .zr-diff { break-inside: avoid; }
        .zr-table tr { break-inside: avoid; }
        .zr-sec__title { break-after: avoid; }
    }
</style>
@endpush

@endsection
