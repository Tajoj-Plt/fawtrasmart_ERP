@extends('client::portal.layout')

{{--
    طلبُ زيارةٍ ميدانية من البوابة، ومتابعةُ ما سبق طلبُه.

    ══════════════════ طلبٌ لا موعد ══════════════════

    ما يُرسَل هنا رغبةٌ يراجعها موظّف — لا يحجز وقتَ مندوبٍ ولا يظهر في خطّ
    سيره حتى يعتمده. والنصُّ في الشاشة يقول ذلك صراحةً: عميلٌ ظنّ أنه حجز
    زيارةً ثم لم يأتِه أحدٌ في يومه أسوأ من عميلٍ يعرف أنه طَلَب.

    ══════════════════ النموذجُ يختفي وقت الانتظار ══════════════════

    ما دام طلبٌ معلَّقاً، لا نموذجَ — يُعرَض مكانَه ما طُلب وزرُّ سحبِه.
    المتحكّم يردّ الطلبَ الثاني على كلّ حال؛ وإخفاءُ الزرّ يمنع الضغطةَ التي
    تُردّ بدل أن يعتذر عنها.

    وثلاثةُ حقولٍ فقط: السبب، واليومُ المفضَّل (اختياري)، والفترة. حقلٌ رابع
    يزيد التردّد ولا يزيد ما يعرفه من سيرتّب الخطّ.
--}}

@section('title', 'طلب زيارة ميدانية')
@section('subtitle', 'اطلب أن يزورك مندوبنا، وتابع طلباتك السابقة')

@section('content')
    @php
        $windows = \App\Models\ClientVisitRequest::TIME_WINDOWS;
    @endphp

    @if ($pending)
        {{-- بطاقةُ «طلبك قيد المراجعة» مكانَ النموذج --}}
        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-hourglass-half"></i>طلبك قيد المراجعة</h2>
                <span class="cp-badge cp-badge--warning">بانتظار المراجعة</span>
            </div>

            <div class="cp-card__body">
                <p style="margin-top:0">
                    أرسلتَ طلبَ زيارةٍ في
                    <strong>{{ $pending->created_at?->format('Y-m-d') }}</strong>
                    بسبب: <strong>{{ $pending->reason }}</strong>
                    @if ($pending->preferred_date)
                        — تفضّل يوم <strong>{{ $pending->preferred_date->format('Y-m-d') }}</strong>
                        ({{ $pending->timeWindowLabel() }}).
                    @else
                        — بلا يومٍ محدَّد ({{ $pending->timeWindowLabel() }}).
                    @endif
                </p>

                <p class="cp-muted" style="font-size:.85rem">
                    لا يُرسَل طلبٌ جديد حتى يُراجَع هذا. إن تغيّر ما تريده فاسحبه وأرسل غيره.
                </p>

                <form method="POST" action="{{ route('clients.portal.visit_requests.cancel', $pending) }}"
                      onsubmit="return confirm('سحب طلب الزيارة؟');">
                    @csrf
                    <button type="submit" class="cp-btn cp-btn--ghost cp-btn--sm">
                        <i class="fa fa-times"></i> اسحب الطلب
                    </button>
                </form>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('clients.portal.visit_requests.store') }}">
            @csrf

            <div class="cp-card">
                <div class="cp-card__head">
                    <h2 class="cp-card__title"><i class="fa fa-map-marker-alt"></i>اطلب زيارة</h2>
                </div>

                <div class="cp-card__body">
                    <div class="cp-field">
                        <label for="reason">سبب الزيارة <span style="color:var(--cp-danger)">*</span></label>
                        <input type="text" name="reason" id="reason" required maxlength="150"
                               value="{{ old('reason') }}" placeholder="استلام طلبية، متابعة حساب، مشكلة في صنف…">
                        <span class="cp-field__hint">سطرٌ واحدٌ يكفي — يقرؤه من يرتّب زيارتك.</span>
                    </div>

                    <div class="cp-grid">
                        <div class="cp-field">
                            <label for="preferred_date">اليوم المفضَّل (اختياري)</label>
                            <input type="date" name="preferred_date" id="preferred_date"
                                   min="{{ today()->toDateString() }}" value="{{ old('preferred_date') }}">
                            <span class="cp-field__hint">اقتراحٌ لا موعد — قد نزورك في يومٍ قريبٍ منه.</span>
                        </div>

                        <div class="cp-field">
                            <label for="time_window">الفترة</label>
                            <select name="time_window" id="time_window">
                                @foreach ($windows as $value => $label)
                                    <option value="{{ $value }}" @selected(old('time_window', 'any') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="cp-field">
                        <label for="note">تفاصيل إضافية (اختياري)</label>
                        <textarea name="note" id="note" rows="3" maxlength="500"
                                  placeholder="أيّ شيءٍ يساعدنا على تجهيز الزيارة">{{ old('note') }}</textarea>
                    </div>

                    <p class="cp-muted" style="font-size:.85rem">
                        طلبك <strong>لا يُثبِّت موعداً</strong>: يراجعه موظّفونا ويرتّبون زيارتك، ويصلك ردٌّ عليه.
                    </p>

                    <button type="submit" class="cp-btn cp-btn--primary">
                        <i class="fa fa-paper-plane"></i> أرسل الطلب
                    </button>
                </div>
            </div>
        </form>
    @endif

    <div class="cp-card">
        <div class="cp-card__head">
            <h2 class="cp-card__title"><i class="fa fa-history"></i>طلباتي السابقة</h2>
            <span class="cp-badge cp-badge--muted">{{ number_format($requests->total()) }} طلب</span>
        </div>

        <div class="cp-card__body cp-card__body--flush">
            @if ($requests->isEmpty())
                <div class="cp-empty">
                    <i class="fa fa-map-marker-alt"></i>
                    <p>لم ترسل طلبَ زيارةٍ من قبل.</p>
                </div>
            @else
                <div class="cp-tablewrap">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>السبب</th>
                                <th>اليوم المفضَّل</th>
                                <th>الفترة</th>
                                <th>الحالة</th>
                                <th class="cp-noprint"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $item)
                                <tr>
                                    <td class="cp-num">{{ $item->created_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        {{ $item->reason }}
                                        @if ($item->note)
                                            <small style="color:var(--cp-muted)">{{ $item->note }}</small>
                                        @endif
                                    </td>
                                    <td class="cp-num">{{ $item->preferred_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $item->timeWindowLabel() }}</td>
                                    <td>
                                        <span class="cp-badge cp-badge--{{ $item->portalTone() }}">
                                            {{ $item->statusLabel() }}
                                        </span>
                                        {{-- سببُ الرفض يُقرأ هنا لا في مكالمةٍ هاتفية --}}
                                        @if ($item->review_note)
                                            <small style="color:var(--cp-muted)">{{ $item->review_note }}</small>
                                        @endif
                                    </td>
                                    <td class="cp-noprint">
                                        @if ($item->isPending())
                                            <form method="POST"
                                                  action="{{ route('clients.portal.visit_requests.cancel', $item) }}"
                                                  onsubmit="return confirm('سحب طلب الزيارة؟');">
                                                @csrf
                                                <button type="submit" class="cp-btn cp-btn--ghost cp-btn--sm">
                                                    <i class="fa fa-times"></i> سحب
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($requests->hasPages())
                    <div class="cp-card__body cp-noprint">{{ $requests->links() }}</div>
                @endif
            @endif
        </div>
    </div>
@endsection
