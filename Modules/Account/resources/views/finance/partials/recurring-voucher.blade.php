{{--
    «سند مكرَّر» في شاشة السند — لسند القبض وسند الصرف معاً.

    كانت الشاشةُ لا تقول إنّ السند مكرَّر أصلاً، والمستخدم لا يرى أين ذهبت
    النسخُ ولا متى تأتي التالية. الأصلُ يعرض دورته ونهايته والتالية ونسخه،
    والنسخةُ تشير إلى أصلها. والتالية تُحسب من تاريخ الأصل (`RecursAsVoucher`)
    — يُصدرها `finance:generate-recurring-vouchers` يومياً.

    المتغيّرات: `$voucher` (Receipt|Expense)، `$showRoute` (اسم مسار العرض).
--}}
@php
    $recurringParent = $voucher->recurring_parent_id ? $voucher->recurringParent : null;
    $isRecurringSource = $voucher->isRecurringSource();
    $recurringCopies = $isRecurringSource
        ? $voucher->recurringCopies()->orderBy('recurring_sequence')->get(['id', 'code', 'date', 'recurring_sequence'])
        : collect();
    $nextOccurrence = $isRecurringSource ? $voucher->nextRecurringOccurrence() : null;
@endphp

@if ($recurringParent)
    <div class="alert alert-info d-flex align-items-center" style="gap: .5rem;">
        <i class="feather icon-repeat"></i>
        <span>
            نسخةٌ مولَّدة تلقائياً (رقم {{ $voucher->recurring_sequence }}) من السند المكرَّر
            <a href="{{ route($showRoute, $recurringParent->id) }}" class="font-weight-bold">{{ $recurringParent->code }}</a>
        </span>
    </div>
@elseif ($isRecurringSource)
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center" style="gap: 1.25rem;">
                <strong><i class="feather icon-repeat"></i> سند مكرَّر</strong>
                <span>الدورة: <strong>{{ $voucher->recurringFrequencyLabel() }}</strong></span>
                <span>تاريخ الانتهاء: <strong class="stk-num">{{ $voucher->end_date ?: 'بلا نهاية' }}</strong></span>
                <span>
                    النسخة التالية:
                    <strong class="stk-num">
                        {{ $nextOccurrence ? $nextOccurrence[1]->format('Y-m-d') : 'انتهت السلسلة' }}
                    </strong>
                </span>
                <span>نسخ صادرة: <strong class="stk-num">{{ $recurringCopies->count() }}</strong></span>
            </div>

            @if ($recurringCopies->isNotEmpty())
                <div class="d-flex flex-wrap mt-2" style="gap: .5rem;">
                    @foreach ($recurringCopies as $copy)
                        <a href="{{ route($showRoute, $copy->id) }}" class="badge badge-light border stk-num"
                            title="{{ $copy->date }}">#{{ $copy->recurring_sequence }} — {{ $copy->code }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif
