{{--
    سند صرف — نسخة PDF كاملة البيانات.

    كان هذا الملف نسخةً حرفيّة من `print_thermal`: عرض 80 مم وباركود وختم
    «مسوغ» — تحت اسم `pdf`. ولا يستدعيه الكنترولر أصلاً، فما كان أحدٌ ليرى
    التناقض. صار ورقة A4 كاملة كما يوحي اسمه، تحمل بيانات السند وقيده
    وضرائبه، بنفس لغة `asol/pdf`.
--}}
@php
    $orgName = \App\Support\OrganizationInfo::name();
    $orgAddress = \App\Support\OrganizationInfo::address();
    $orgIdentity = \App\Support\OrganizationInfo::identityLine();

    $paidTo =
        $expense->Supplier->trade_name ??
        ($expense->account->name ?? ($expense->expenses_category->name ?? ($expense->seller ?: 'غير محدد')));

    $taxTotal = ($expense->tax1_amount ?? 0) + ($expense->tax2_amount ?? 0);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>سند صرف {{ $expense->code }}</title>
    @include('account::finance.partials.print-styles')
</head>

<body>
    <div class="paper">
        <div class="doc-head">
            <h1>{{ $orgName }}</h1>
            <h2>سند صرف — نسخة تفصيلية</h2>
            @if ($orgAddress)<p>{{ $orgAddress }}</p>@endif
            @if ($orgIdentity)<p>{{ $orgIdentity }}</p>@endif
            <p>رقم السند: {{ $expense->code }} — بتاريخ {{ $expense->date }}</p>
        </div>

        <h3 class="section-title">بيانات السند</h3>
        <table class="info-grid">
            <tr>
                <td class="info-label">رقم السند:</td>
                <td class="info-value">{{ $expense->code }}</td>
                <td class="info-label">التاريخ:</td>
                <td class="info-value">{{ $expense->date }}</td>
            </tr>
            {{-- نوعُ السند يُطبَع نصّاً لا وسماً ملوّناً: الطابعةُ أحاديةٌ ولا تُظهر الخلفيات --}}
            @if ($expense->is_taxable)
                <tr>
                    <td class="info-label">نوع السند:</td>
                    <td class="info-value" colspan="3"><strong>سند ضريبي</strong></td>
                </tr>
            @endif
            <tr>
                <td class="info-label">صُرف إلى:</td>
                <td class="info-value">{{ $paidTo }}</td>
                <td class="info-label">التصنيف:</td>
                <td class="info-value">{{ $expense->expenses_category->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">الحساب:</td>
                <td class="info-value">{{ $expense->account->name ?? '—' }}</td>
                <td class="info-label">الخزينة:</td>
                <td class="info-value">{{ $expense->treasury->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">طريقة الصرف:</td>
                <td class="info-value">{{ $expense->paymentMethod->name ?? ($expense->payment_method ?? 'نقدي') }}</td>
                <td class="info-label">الفرع:</td>
                <td class="info-value">
                    {{ $expense->branch->name ?? ($expense->createdBy->branch->name ?? '—') }}
                </td>
            </tr>
            <tr>
                <td class="info-label">أنشئ بواسطة:</td>
                <td class="info-value">{{ $expense->createdBy->name ?? '—' }}</td>
                <td class="info-label">البيان:</td>
                <td class="info-value">{{ $expense->description ?: '—' }}</td>
            </tr>
        </table>

        <div class="amount-box">
            <span>المبلغ المصروف</span>
            <span class="amount-box__value">@money($expense->amount ?? 0, $expense)</span>
        </div>

        @if ($expense->amount_in_words)
            <div class="amount-words">فقط: {{ $expense->amount_in_words }} لا غير.</div>
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
                    <td>@money($expense->amount ?? 0, $expense)</td>
                </tr>
                <tr>
                    <td>الضريبة الأولى</td>
                    <td><x-tax-line :tax="$expense->tax1Setting" :amount="$expense->tax1_amount" :doc="$expense" /></td>
                    <td>@money($expense->tax1_amount ?? 0, $expense)</td>
                </tr>
                <tr>
                    <td>الضريبة الثانية</td>
                    <td><x-tax-line :tax="$expense->tax2Setting" :amount="$expense->tax2_amount" :doc="$expense" /></td>
                    <td>@money($expense->tax2_amount ?? 0, $expense)</td>
                </tr>
                <tr>
                    <td><strong>الإجمالي شامل الضريبة</strong></td>
                    <td>—</td>
                    <td><strong>@money($expense->total_amount ?: (($expense->amount ?? 0) + $taxTotal), $expense)</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="signatures">
            <div>توقيع المستلم</div>
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
