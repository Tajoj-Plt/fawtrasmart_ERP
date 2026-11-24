{{--
    سند قبض — نسخة PDF كاملة البيانات.

    كانت ورقةً بحوافّ مستديرة وظلٍّ وخلفيةٍ زرقاء فاتحة (`#f7f9fc`) — تصميم
    شاشة لا تصميم طباعة: الظلّ لا يُطبع والخلفية تُهدر الحبر. وكان المبلغ
    يُطبع بلاحقة «رس» مكتوبةً في القالب، فلا يتبع «العملات» في الإعدادات.
    وسنة حقوق النشر مثبَّتة «2025».

    صارت ورقة A4 بلغة `asol/pdf` نفسها، تحمل بيانات السند وضرائبه.
--}}
@php
    $orgName = \App\Support\OrganizationInfo::name();
    $orgAddress = \App\Support\OrganizationInfo::address();
    $orgIdentity = \App\Support\OrganizationInfo::identityLine();

    $receivedFrom = $income->client->trade_name ?? ($income->account->name ?? ($income->seller ?: 'غير محدد'));

    $taxTotal = ($income->tax1_amount ?? 0) + ($income->tax2_amount ?? 0);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>سند قبض {{ $income->code }}</title>
    @include('account::finance.partials.print-styles')
</head>

<body>
    <div class="paper">
        <div class="doc-head">
            <h1>{{ $orgName }}</h1>
            <h2>سند قبض — نسخة تفصيلية</h2>
            @if ($orgAddress)<p>{{ $orgAddress }}</p>@endif
            @if ($orgIdentity)<p>{{ $orgIdentity }}</p>@endif
            <p>رقم السند: {{ $income->code }} — بتاريخ {{ $income->date }}</p>
        </div>

        <h3 class="section-title">بيانات السند</h3>
        <table class="info-grid">
            <tr>
                <td class="info-label">رقم السند:</td>
                <td class="info-value">{{ $income->code }}</td>
                <td class="info-label">التاريخ:</td>
                <td class="info-value">{{ $income->date }}</td>
            </tr>
            {{-- نوعُ السند يُطبَع نصّاً لا وسماً ملوّناً: الطابعةُ أحاديةٌ ولا تُظهر الخلفيات --}}
            @if ($income->is_taxable)
                <tr>
                    <td class="info-label">نوع السند:</td>
                    <td class="info-value" colspan="3"><strong>سند ضريبي</strong></td>
                </tr>
            @endif
            <tr>
                <td class="info-label">استُلم من:</td>
                <td class="info-value">{{ $receivedFrom }}</td>
                <td class="info-label">التصنيف:</td>
                <td class="info-value">{{ $income->incomes_category->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">الحساب:</td>
                <td class="info-value">{{ $income->account->name ?? '—' }}</td>
                <td class="info-label">الخزينة:</td>
                <td class="info-value">{{ $income->treasury->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">طريقة القبض:</td>
                <td class="info-value">{{ $income->paymentMethod->name ?? 'نقدي' }}</td>
                <td class="info-label">الفرع:</td>
                <td class="info-value">{{ $income->branch->name ?? ($income->user->branch->name ?? '—') }}</td>
            </tr>
            <tr>
                <td class="info-label">المستلِم:</td>
                <td class="info-value">{{ $income->user->name ?? '—' }}</td>
                <td class="info-label">البيان:</td>
                <td class="info-value">{{ $income->description ?: '—' }}</td>
            </tr>
        </table>

        <div class="amount-box">
            <span>المبلغ المقبوض</span>
            <span class="amount-box__value">@money($income->amount ?? 0, $income)</span>
        </div>

        @if ($income->amount_in_words)
            <div class="amount-words">فقط: {{ $income->amount_in_words }} لا غير.</div>
        @endif

        <h3 class="section-title">تفصيل المبالغ</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>البند</th>
                    <th>النسبة</th>
                    <th>المبلغ</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>المبلغ قبل الضريبة (الوعاء)</td>
                    <td>—</td>
                    <td>@money($income->amount ?? 0, $income)</td>
                </tr>
                <tr>
                    <td>الضريبة الأولى</td>
                    <td><x-tax-line :tax="$income->tax1Setting" :amount="$income->tax1_amount" :doc="$income" /></td>
                    <td>@money($income->tax1_amount ?? 0, $income)</td>
                </tr>
                <tr>
                    <td>الضريبة الثانية</td>
                    <td><x-tax-line :tax="$income->tax2Setting" :amount="$income->tax2_amount" :doc="$income" /></td>
                    <td>@money($income->tax2_amount ?? 0, $income)</td>
                </tr>
                <tr>
                    <td><strong>الإجمالي شامل الضريبة</strong></td>
                    <td>—</td>
                    <td><strong>@money($income->total_amount ?: (($income->amount ?? 0) + $taxTotal), $income)</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="signatures">
            <div>توقيع الدافع</div>
            <div>أمين الصندوق</div>
            <div>المدير المالي</div>
        </div>

        <div class="doc-foot">© {{ date('Y') }} {{ $orgName }}</div>
    </div>

    <div class="print-bar">
        <button type="button" onclick="window.print()">طباعة</button>
        <button type="button" onclick="window.close()">إغلاق النافذة</button>
    </div>
</body>

</html>
