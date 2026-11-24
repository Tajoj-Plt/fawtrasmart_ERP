{{--
    سند قبض — نسخة الطابعة الحرارية (80 مم).

    الأنماط انتقلت إلى `finance.partials.thermal-styles` لأنها هي نفسها في
    سند الصرف حرفاً بحرف عدا لون العنوان — ولون لا تطبعه الطابعة الحرارية.
--}}
@php
    $orgName = \App\Support\OrganizationInfo::name();
    $orgAddress = \App\Support\OrganizationInfo::address();
    $orgIdentity = \App\Support\OrganizationInfo::identityLine();

    $receivedFrom = $income->client->trade_name ?? ($income->account->name ?? ($income->seller ?: 'غير محدد'));
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سند قبض {{ $income->code }}</title>
    @include('account::finance.partials.thermal-styles')
</head>

<body>
    <div class="slip">
        <div class="slip-head">
            <h1>{{ $orgName }}</h1>
            <h2>سند قبض</h2>
            @if ($orgAddress)<p>{{ $orgAddress }}</p>@endif
            @if ($orgIdentity)<p>{{ $orgIdentity }}</p>@endif
        </div>

        <div class="slip-rows">
            <div class="slip-row">
                <span class="slip-row__label">رقم السند</span>
                <span class="slip-row__value">{{ $income->code }}</span>
            </div>
            @if ($income->is_taxable)
                <div class="slip-row">
                    <span class="slip-row__label">نوع السند</span>
                    <span class="slip-row__value"><strong>ضريبي</strong></span>
                </div>
            @endif
            <div class="slip-row">
                <span class="slip-row__label">التاريخ</span>
                <span class="slip-row__value">{{ $income->date }}</span>
            </div>
            <div class="slip-row">
                <span class="slip-row__label">استُلم من</span>
                <span class="slip-row__value">{{ $receivedFrom }}</span>
            </div>
        </div>

        {{--
            **الإيصالُ الحراري كان يطبع المبلغَ وحده.**

            فالزبونُ يأخذ ورقةً تقول 1,000 عن سندٍ قيمتُه 1,150، ولا ذكرَ
            للضريبة فيها. والإيصالُ مستندٌ يُسلَّم، فحقُّه أن يقول الوعاءَ
            والضريبةَ والإجمالي — كما يقول سندُ الشاشة والـPDF.

            ولا تظهر سطورُ الضريبة إلا حين تكون: إيصالُ سندٍ بلا ضريبة يبقى
            على سطرٍ واحد كما كان.
        --}}
        @php
            $slipTax = ($income->tax1_amount ?? 0) + ($income->tax2_amount ?? 0);
            $slipTotal = $income->total_amount ?: (($income->amount ?? 0) + $slipTax);
        @endphp

        @if ($slipTax > 0)
            <div class="slip-rows">
                <div class="slip-row">
                    <span class="slip-row__label">المبلغ قبل الضريبة</span>
                    <span class="slip-row__value">@money($income->amount ?? 0, $income)</span>
                </div>

                @if ($income->tax1Setting)
                    <div class="slip-row">
                        <span class="slip-row__label">{{ $income->tax1Setting->name ?? 'الضريبة الأولى' }}</span>
                        <span class="slip-row__value">@money($income->tax1_amount ?? 0, $income)</span>
                    </div>
                @endif

                @if ($income->tax2Setting)
                    <div class="slip-row">
                        <span class="slip-row__label">{{ $income->tax2Setting->name ?? 'الضريبة الثانية' }}</span>
                        <span class="slip-row__value">@money($income->tax2_amount ?? 0, $income)</span>
                    </div>
                @endif
            </div>
        @endif

        <div class="slip-amount">
            <span>{{ $slipTax > 0 ? 'الإجمالي شامل الضريبة' : 'المبلغ' }}</span>
            <span class="slip-amount__value">@money($slipTotal, $income)</span>
        </div>

        @if ($income->amount_in_words)
            <div class="slip-words">فقط: {{ $income->amount_in_words }} لا غير.</div>
        @endif

        <div class="slip-rows">
            <div class="slip-row">
                <span class="slip-row__label">وذلك مقابل</span>
                <span class="slip-row__value">{{ $income->description ?: '—' }}</span>
            </div>
            <div class="slip-row">
                <span class="slip-row__label">التصنيف</span>
                <span class="slip-row__value">{{ $income->incomes_category->name ?? '—' }}</span>
            </div>
            <div class="slip-row">
                <span class="slip-row__label">طريقة القبض</span>
                <span class="slip-row__value">{{ $income->paymentMethod->name ?? 'نقدي' }}</span>
            </div>
            <div class="slip-row">
                <span class="slip-row__label">الخزينة</span>
                <span class="slip-row__value">{{ $income->treasury->name ?? 'الخزينة الرئيسية' }}</span>
            </div>
        </div>

        <div class="slip-signatures">
            <div>توقيع الدافع</div>
            <div>أمين الصندوق</div>
        </div>

        <div class="slip-barcode">*{{ $income->code }}*</div>

        <div class="slip-foot">© {{ date('Y') }} {{ $orgName }}</div>
    </div>

    <div class="print-bar">
        <button type="button" onclick="window.print()">طباعة السند</button>
        <button type="button" onclick="window.close()">إغلاق النافذة</button>
    </div>

    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 200);
        });
    </script>
</body>

</html>
