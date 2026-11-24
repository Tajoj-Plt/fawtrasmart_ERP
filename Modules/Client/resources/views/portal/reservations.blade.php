@extends('client::portal.layout')

{{--
    حجوزات العميل في بوابته.

    عرضٌ فقط ومدخلُ حجزٍ جديد: التأكيدُ والإلغاءُ وإعادةُ الجدولة من عند
    الموظّف — الوقتُ مواردُ المنشأة، وتحريرُه أو نقلُه قرارُها.
--}}

@section('title', 'حجوزاتي')
@section('subtitle', 'مواعيدك المحجوزة وحالتها')

@section('actions')
    <a href="{{ route('clients.portal.reservations.create') }}" class="cp-btn cp-btn--primary cp-btn--sm">
        <i class="fa fa-plus"></i> احجز موعداً
    </a>
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;

        $money = fn ($v) => CurrencySettings::format(abs((float) $v));

        /* ألوانُ الحالة بمفردات البوابة (`cp-badge--*`) لا بمفردات لوحة
           الموظّف: القوالبُ هنا نظامُ تصميمٍ مستقلّ، وصنفٌ غريبٌ عنه يصمت. */
        $tone = [
            'draft' => 'muted',
            'pending' => 'warning',
            'confirmed' => 'success',
            'checked_in' => 'success',
            'in_progress' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            'rejected' => 'danger',
            'no_show' => 'danger',
            'rescheduled' => 'warning',
        ];
    @endphp

    <div class="cp-card">
        <div class="cp-card__head">
            <h2 class="cp-card__title"><i class="fa fa-bookmark"></i>حجوزاتي</h2>
        </div>

        <div class="cp-card__body cp-card__body--flush">
            @if ($reservations->isEmpty())
                <div class="cp-empty">
                    <i class="fa fa-calendar-plus"></i>
                    <p>لا حجوزات على حسابك بعد.</p>
                    <a href="{{ route('clients.portal.reservations.create') }}" class="cp-btn cp-btn--primary cp-btn--sm">
                        احجز موعدك الأول
                    </a>
                </div>
            @else
                <div class="cp-tablewrap">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>الرقم</th>
                                <th>الخدمة</th>
                                <th>التاريخ</th>
                                <th>الوقت</th>
                                <th class="cp-num">القيمة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reservations as $reservation)
                                <tr>
                                    <td class="cp-num cp-strong">{{ $reservation->code }}</td>
                                    <td>{{ $reservation->product->name ?? '—' }}</td>
                                    <td class="cp-num">{{ \Illuminate\Support\Carbon::parse($reservation->reservation_date)->format('Y-m-d') }}</td>
                                    {{-- وقتان لاتينيّان متجاوران في سطرٍ عربيّ ينقلبان ترتيباً — `dir="ltr"` --}}
                                    <td class="cp-num" dir="ltr">
                                        {{ substr((string) $reservation->start_time, 0, 5) }}
                                        –
                                        {{ substr((string) $reservation->end_time, 0, 5) }}
                                    </td>
                                    <td class="cp-num">{{ $money($reservation->total) }}</td>
                                    <td>
                                        <span class="cp-badge cp-badge--{{ $tone[$reservation->status->value ?? (string) $reservation->status] ?? 'muted' }}">
                                            {{ $reservation->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($reservations->hasPages())
                    <div class="cp-card__body">{{ $reservations->links() }}</div>
                @endif
            @endif
        </div>
    </div>

    <p class="cp-muted" style="font-size:.85rem">
        لتعديل موعدٍ أو إلغائه تواصل معنا — سنعيد جدولته لك.
    </p>
@endsection
