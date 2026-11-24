@extends('client::portal.layout')

{{--
    عروض الأسعار الصادرة للعميل.

    **عرضٌ لا موافقة**: مفتاح الصلاحية اسمه «مشاهدة والموافقة على عروض
    الأسعار»، والموافقة من البوابة لم تُبنَ بعد. فلا يُعرض هنا زرُّ موافقةٍ
    لا يفعل شيئاً — زرٌّ يَعِد ولا يفي أسوأ من غيابه، والعميل الذي يضغطه
    يظنّ أنه وافق فيبني عليه.
--}}

@section('title', 'عروض الأسعار')
@section('subtitle', 'العروض الصادرة لك وصلاحيتها')

@section('actions')
    <button type="button" class="cp-btn cp-btn--ghost cp-btn--sm" onclick="window.print()">
        <i class="fa fa-print"></i> طباعة
    </button>
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;

        $money = fn ($v) => CurrencySettings::format(abs((float) $v));
        $today = now()->startOfDay();
    @endphp

    <div class="cp-card">
        <div class="cp-card__head">
            <h2 class="cp-card__title"><i class="fa fa-tags"></i>العروض</h2>
            <span class="cp-badge cp-badge--muted">{{ number_format($quotes->total()) }} عرض</span>
        </div>

        <div class="cp-card__body cp-card__body--flush">
            @if ($quotes->isEmpty())
                <div class="cp-empty">
                    <i class="fa fa-tags"></i>
                    <p>لا توجد عروض أسعار صادرة لك.</p>
                </div>
            @else
                <div class="cp-tablewrap">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>رقم العرض</th>
                                <th>التاريخ</th>
                                <th>صالح حتى</th>
                                <th class="cp-num">البنود</th>
                                <th class="cp-num">الإجمالي</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quotes as $quote)
                                @php
                                    $expiry = $quote->expiry_date ?? $quote->due_date ?? null;
                                    $expired = $expiry && \Illuminate\Support\Carbon::parse($expiry)->startOfDay()->lt($today);
                                @endphp

                                <tr>
                                    <td class="cp-num cp-strong">{{ $quote->code ?? $quote->quote_number ?? $quote->id }}</td>
                                    <td class="cp-num">{{ optional($quote->created_at)->format('Y-m-d') ?? '—' }}</td>
                                    <td class="cp-num">{{ $expiry ? \Illuminate\Support\Carbon::parse($expiry)->format('Y-m-d') : '—' }}</td>
                                    <td class="cp-num">{{ number_format($quote->items->count()) }}</td>
                                    <td class="cp-num">{{ $money($quote->grand_total ?? $quote->total ?? 0) }}</td>
                                    <td>
                                        @if ($expired)
                                            <span class="cp-badge cp-badge--muted">منتهي الصلاحية</span>
                                        @else
                                            <span class="cp-badge cp-badge--success">ساري</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($quotes->hasPages())
        <div class="cp-noprint" style="display:flex;justify-content:center;gap:.5rem">
            @if ($quotes->onFirstPage())
                <span class="cp-btn cp-btn--ghost cp-btn--sm" style="opacity:.5">السابق</span>
            @else
                <a href="{{ $quotes->previousPageUrl() }}" class="cp-btn cp-btn--ghost cp-btn--sm">السابق</a>
            @endif

            <span class="cp-btn cp-btn--ghost cp-btn--sm" style="pointer-events:none">
                {{ $quotes->currentPage() }} / {{ $quotes->lastPage() }}
            </span>

            @if ($quotes->hasMorePages())
                <a href="{{ $quotes->nextPageUrl() }}" class="cp-btn cp-btn--ghost cp-btn--sm">التالي</a>
            @else
                <span class="cp-btn cp-btn--ghost cp-btn--sm" style="opacity:.5">التالي</span>
            @endif
        </div>
    @endif
@endsection
