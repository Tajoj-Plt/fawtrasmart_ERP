{{--
    سند قبض — نسخة A4.

    كان القالب يحمل نسختَي تصميم في ملف واحد: قواعد `@media screen` لمقاس
    A4 وأخرى لمقاس 80 مم، وسكربتٌ يقيس عرض النافذة ليُظهر إحداهما ويخفي
    الأخرى — أي أن الطباعة الحرارية كانت تعتمد على عرض نافذة المتصفّح لا
    على الطابعة. والنسخة الحرارية لها قالبها المستقلّ أصلاً
    (`print_thermal`)، فبقي هنا ورقُ A4 وحده.

    اسم المنشأة وعنوانها وبيانات هويّتها تُقرأ من «إعدادات المنشأة» عبر
    `OrganizationInfo` — نفس المصدر الذي يقرأ منه مُصيّر قوالب المستندات،
    فلا تفترق ورقةٌ عن ورقة.
--}}
@php
    $orgName = \App\Support\OrganizationInfo::name();
    $orgAddress = \App\Support\OrganizationInfo::address();
    $orgIdentity = \App\Support\OrganizationInfo::identityLine();

    $receivedFrom =
        $income->client->trade_name ?? ($income->account->name ?? ($income->seller ?: 'غير محدد'));
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سند قبض {{ $income->code }}</title>
    @include('account::finance.partials.print-styles')
</head>

<body>
    <div class="paper">
        <div class="doc-head">
            <h1>{{ $orgName }}</h1>
            <h2>سند قبض</h2>
            @if ($orgAddress)<p>{{ $orgAddress }}</p>@endif
            @if ($orgIdentity)<p>{{ $orgIdentity }}</p>@endif
            <p>رقم السند: {{ $income->code }} — بتاريخ {{ $income->date }}</p>
        </div>

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
                <td class="info-label">طريقة القبض:</td>
                <td class="info-value">{{ $income->paymentMethod->name ?? 'نقدي' }}</td>
                <td class="info-label">الخزينة:</td>
                <td class="info-value">{{ $income->treasury->name ?? 'الخزينة الرئيسية' }}</td>
            </tr>
            <tr>
                <td class="info-label">الفرع:</td>
                <td class="info-value">
                    {{ $income->branch->name ?? ($income->user->branch->name ?? '—') }}
                </td>
                <td class="info-label">المستلِم:</td>
                <td class="info-value">{{ $income->user->name ?? '—' }}</td>
            </tr>
        </table>

        <div class="amount-box">
            <span>المبلغ المقبوض</span>
            <span class="amount-box__value">@money($income->amount ?? 0, $income)</span>
        </div>

        @if ($income->amount_in_words)
            <div class="amount-words">فقط: {{ $income->amount_in_words }} لا غير.</div>
        @endif

        <h3 class="section-title">وذلك مقابل</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:60%">البيان</th>
                    <th>المبلغ</th>
                    <th>الضريبة</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align:right">{{ $income->description ?: '—' }}</td>
                    <td>@money($income->amount ?? 0, $income)</td>
                    <td>@money(($income->tax1_amount ?? 0) + ($income->tax2_amount ?? 0), $income)</td>
                    {{-- الإجمالي كما حُسب عند الحفظ، لا جمعٌ يُعاد في الشاشة --}}
                    <td>
                        @money($income->total_amount ?: (($income->amount ?? 0) + ($income->tax1_amount ?? 0) + ($income->tax2_amount ?? 0)), $income)
                    </td>
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
        <button type="button" onclick="window.print()">طباعة السند</button>
        <button type="button" onclick="window.close()">إغلاق النافذة</button>
    </div>
</body>

</html>
