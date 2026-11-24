@extends('client::portal.layout')

{{--
    كشف حساب العميل.

    ══════════════════ من أين الأرقام؟ ══════════════════

    من قيود حسابه المحاسبي (`journal_entry_details` على حساب أستاذ العميل)
    لا من جدول الفواتير. والفرق جوهري: الفواتير تُظهر ما صدر له فقط، أمّا
    الحساب فيُظهر **كلَّ ما تحرّك**: الفاتورة، والسداد، والإشعار الدائن،
    والتسوية، والرصيد الافتتاحي. وكشفٌ لا يُظهر سدادَ العميل كشفٌ يُخاصم.

    ══════════════════ الاتّجاه ══════════════════

    حساب العميل مدينٌ بطبعه: ما عليه يزيد بالمدين، وسدادُه ينقص بالدائن.
    فالرصيد المتسلسل = مجموع (مدين − دائن) حتى ذلك السطر، ويُعرض موجباً مع
    كلمة «عليك» أو «لك» — لا بإشارة سالبٍ يقرؤها الزبون خطأً.
--}}

@section('title', 'كشف الحساب')
@section('subtitle', 'حركة حسابك عندنا ورصيدك الحالي')

@section('actions')
    <button type="button" class="cp-btn cp-btn--ghost cp-btn--sm" onclick="window.print()">
        <i class="fa fa-print"></i> طباعة
    </button>
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;

        $money = fn ($v) => CurrencySettings::format(abs((float) $v));
        $balance = (float) $closingBalance;
    @endphp

    <div class="cp-stats">
        <div class="cp-stat">
            <span class="cp-stat__icon {{ $balance > 0 ? 'cp-stat__icon--danger' : ($balance < 0 ? 'cp-stat__icon--info' : 'cp-stat__icon--success') }}">
                <i class="fa fa-balance-scale"></i>
            </span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ $money($balance) }}</p>
                <p class="cp-stat__label">
                    @if ($balance > 0)
                        الرصيد المستحقّ عليك
                    @elseif ($balance < 0)
                        رصيدٌ لك عندنا
                    @else
                        حسابك متوازن — لا لك ولا عليك
                    @endif
                </p>
            </div>
        </div>

        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--warning"><i class="fa fa-arrow-up"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ $money($totalDebit) }}</p>
                <p class="cp-stat__label">إجمالي ما تُرتّب عليك</p>
            </div>
        </div>

        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--success"><i class="fa fa-arrow-down"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ $money($totalCredit) }}</p>
                <p class="cp-stat__label">إجمالي ما سُدِّد ونُزِّل</p>
            </div>
        </div>
    </div>

    <div class="cp-card">
        <div class="cp-card__head">
            <h2 class="cp-card__title"><i class="fa fa-book-open"></i>حركة الحساب</h2>
            <span class="cp-badge cp-badge--muted">{{ number_format(count($rows)) }} حركة</span>
        </div>

        <div class="cp-card__body cp-card__body--flush">
            @if (empty($rows))
                <div class="cp-empty">
                    <i class="fa fa-book-open"></i>
                    <p>لا توجد حركات على حسابك بعد.</p>
                </div>
            @else
                <div class="cp-tablewrap">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>البيان</th>
                                <th>المرجع</th>
                                <th class="cp-num">عليك</th>
                                <th class="cp-num">لك</th>
                                <th class="cp-num">الرصيد</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="cp-num">{{ $row['date'] }}</td>
                                    <td>{{ $row['description'] }}</td>
                                    <td class="cp-num">{{ $row['reference'] ?: '—' }}</td>

                                    {{-- خانةٌ صفرها فارغة: صفرٌ مطبوعٌ في عمود
                                         «لك» يجعل العين تقرأ حركةً لم تقع --}}
                                    <td class="cp-num">{{ $row['debit'] > 0 ? $money($row['debit']) : '' }}</td>
                                    <td class="cp-num">{{ $row['credit'] > 0 ? $money($row['credit']) : '' }}</td>

                                    <td class="cp-num cp-strong">
                                        {{ $money($row['balance']) }}
                                        <small style="color:var(--cp-muted);font-weight:400">
                                            {{ $row['balance'] > 0 ? 'عليك' : ($row['balance'] < 0 ? 'لك' : '') }}
                                        </small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr style="background:color-mix(in srgb, var(--cp-text) 4%, transparent)">
                                <td colspan="3" class="cp-strong">الإجمالي</td>
                                <td class="cp-num cp-strong">{{ $money($totalDebit) }}</td>
                                <td class="cp-num cp-strong">{{ $money($totalCredit) }}</td>
                                <td class="cp-num cp-strong">
                                    {{ $money($balance) }}
                                    <small style="color:var(--cp-muted);font-weight:400">
                                        {{ $balance > 0 ? 'عليك' : ($balance < 0 ? 'لك' : '') }}
                                    </small>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <p style="font-size:.78rem;color:var(--cp-muted);text-align:center;margin-top:-.5rem">
        كشفٌ مستخرَجٌ آلياً في {{ now()->format('Y-m-d H:i') }} — للاستفسار راجع من يتابع حسابك.
    </p>
@endsection
