@extends('client::portal.layout')

{{--
    نقاط العميل ومكافآته وإحالاته — قسمٌ في البوابة الواحدة.

    كان بوابةً مستقلّة على `/client-portal` بقالبٍ خاصٍّ ودخولٍ برقم الهاتف؛
    وُحِّد هنا (طلب المستخدم 2026-09-19: «أبغى بوابة واحدة») فصار بتصميم
    البوابة وحارسها. والأرقامُ من `ClientPointsOverview` — وهي حسابُ البوابة
    القديمة نفسُه منقولاً إلى خدمة، فلا يختلف رصيدٌ عن رصيد.
--}}

@section('title', 'نقاطي ومكافآتي')
@section('subtitle', 'رصيدك ومستواك وما يمكنك استبداله')

@section('content')
    @php
        $tierColor = $currentTier->color ?? null;
    @endphp

    {{-- ══════════ الرصيد والمستوى ══════════ --}}
    <div class="cp-stats">
        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--info"><i class="fa fa-coins"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ number_format($currentBalance) }}</p>
                <p class="cp-stat__label">رصيد نقاطك</p>
            </div>
        </div>

        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--success"><i class="fa fa-arrow-up"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ number_format($totalEarned) }}</p>
                <p class="cp-stat__label">إجمالي ما كسبته</p>
            </div>
        </div>

        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--warning"><i class="fa fa-arrow-down"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ number_format($totalSpent) }}</p>
                <p class="cp-stat__label">ما استبدلته</p>
            </div>
        </div>

        @if ($expiringPointsTotal > 0)
            {{-- أهمُّ رقمٍ بعد الرصيد: نقطةٌ تنتهي بلا تنبيهٍ شكوى مؤجَّلة --}}
            <div class="cp-stat">
                <span class="cp-stat__icon cp-stat__icon--danger"><i class="fa fa-hourglass-half"></i></span>
                <div class="cp-stat__body">
                    <p class="cp-stat__value">{{ number_format($expiringPointsTotal) }}</p>
                    <p class="cp-stat__label">تنتهي خلال ٣٠ يوماً</p>
                </div>
            </div>
        @endif
    </div>

    <div class="cp-cols" style="margin-top:1rem">
        {{-- ══════════ المستوى والتقدّم ══════════ --}}
        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-medal"></i>مستواك</h2>
            </div>

            <div class="cp-card__body">
                @if ($currentTier)
                    <div style="display:flex;align-items:center;gap:.75rem">
                        <span class="cp-figure__icon" style="{{ $tierColor ? 'color:'.$tierColor : '' }}">
                            <i class="fa fa-crown"></i>
                        </span>
                        <div>
                            <p class="cp-figure__label">مستواك الحالي</p>
                            <p class="cp-figure__value">{{ $currentTier->name }}</p>
                        </div>
                    </div>

                    @if ($nextTier)
                        <p class="cp-muted" style="margin:1rem 0 .35rem;font-size:.8rem">
                            يفصلك عن «{{ $nextTier->name }}» {{ number_format(max(0, (int) $pointsToNextTier)) }} نقطة
                        </p>

                        {{-- شريطُ التقدّم بلا جافاسكربت: عرضٌ محسوبٌ في الخادم --}}
                        <div style="height:10px;border-radius:6px;background:var(--cp-bg);overflow:hidden">
                            <div style="height:100%;width:{{ (int) $progressToNextTier }}%;
                                        background:var(--cp-primary);border-radius:6px"></div>
                        </div>
                        <p class="cp-muted" style="margin:.35rem 0 0;font-size:.72rem">
                            {{ (int) $progressToNextTier }}% من الطريق
                        </p>
                    @else
                        <p class="cp-muted" style="margin-top:1rem;font-size:.82rem">
                            أنت في أعلى مستوًى — شكراً لولائك.
                        </p>
                    @endif
                @else
                    <div class="cp-empty" style="padding:1rem 0">
                        <i class="fa fa-medal"></i>
                        <p>لا مستويات مُعرَّفة بعد.</p>
                    </div>
                @endif

                @if ($expiringBatches->isNotEmpty())
                    <hr style="border:0;border-top:1px solid var(--cp-line);margin:1rem 0">
                    <p class="cp-muted" style="font-size:.78rem;margin-bottom:.35rem">نقاط تنتهي قريباً</p>
                    @foreach ($expiringBatches as $batch)
                        <div style="display:flex;justify-content:space-between;font-size:.8rem;padding:.2rem 0">
                            <span>{{ number_format($batch->remaining_points) }} نقطة</span>
                            <span class="cp-muted" dir="ltr">{{ $batch->expires_at?->format('Y-m-d') }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- ══════════ حركة النقاط ══════════ --}}
        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-exchange-alt"></i>آخر حركات النقاط</h2>
                <span class="cp-muted" style="font-size:.76rem">آخر ٦ أشهر</span>
            </div>

            <div class="cp-card__body cp-card__body--flush">
                @php
                    /* عمودان لكل شهر: المكتسب والمصروف — رسمٌ داخليٌّ بلا مكتبة،
                       كما في ملخّص الحساب في الرئيسية. */
                    $months = collect($monthlyPoints ?? []);
                    $peak = max(1, $months->flatMap(fn ($m) => [$m['earned'], $m['spent']])->max() ?: 1);
                @endphp

                @if ($months->isNotEmpty())
                    <div style="display:flex;align-items:flex-end;gap:.5rem;height:110px;padding:1rem 1.5rem 0">
                        @foreach ($months as $month)
                            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:.25rem">
                                <div style="display:flex;align-items:flex-end;gap:2px;height:80px">
                                    <span title="مكتسب: {{ number_format($month['earned']) }}"
                                          style="width:10px;border-radius:3px 3px 0 0;background:var(--cp-success);
                                                 height:{{ max(2, round(($month['earned'] / $peak) * 80)) }}px"></span>
                                    <span title="مستبدَل: {{ number_format($month['spent']) }}"
                                          style="width:10px;border-radius:3px 3px 0 0;background:var(--cp-warning);
                                                 height:{{ max(2, round(($month['spent'] / $peak) * 80)) }}px"></span>
                                </div>
                                <span class="cp-muted" style="font-size:.68rem">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($recentTransactions->isEmpty())
                    <div class="cp-empty">
                        <i class="fa fa-coins"></i>
                        <p>لا حركات نقاط بعد.</p>
                    </div>
                @else
                    <div class="cp-list">
                        @foreach ($recentTransactions as $transaction)
                            @php $isEarn = (int) $transaction->points > 0; @endphp
                            <div class="cp-list__item">
                                <span class="cp-list__icon"
                                      style="{{ $isEarn ? 'background:color-mix(in srgb,var(--cp-success) 12%,transparent);color:var(--cp-success)' : 'background:color-mix(in srgb,var(--cp-warning) 14%,transparent);color:var(--cp-warning)' }}">
                                    <i class="fa fa-{{ $isEarn ? 'plus' : 'minus' }}"></i>
                                </span>
                                <div class="cp-list__body">
                                    <p class="cp-list__title">{{ $transaction->description ?: ($isEarn ? 'نقاط مكتسبة' : 'استبدال نقاط') }}</p>
                                    <p class="cp-list__sub">{{ $transaction->created_at?->format('Y-m-d') }}</p>
                                </div>
                                <span class="cp-list__side" style="color:{{ $isEarn ? 'var(--cp-success)' : 'var(--cp-warning)' }}">
                                    {{ $isEarn ? '+' : '' }}{{ number_format((int) $transaction->points) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════ المكافآت والإنجازات ══════════ --}}
    <div class="cp-cols" style="margin-top:1rem">
        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-gift"></i>المكافآت المتاحة</h2>
            </div>

            <div class="cp-card__body cp-card__body--flush">
                @if ($availableRewards->isEmpty())
                    <div class="cp-empty">
                        <i class="fa fa-gift"></i>
                        <p>لا مكافآت متاحة حالياً.</p>
                    </div>
                @else
                    <div class="cp-list">
                        @foreach ($availableRewards as $reward)
                            @php $affordable = $currentBalance >= (int) $reward->points_required; @endphp
                            <div class="cp-list__item">
                                <span class="cp-list__icon"><i class="fa fa-gift"></i></span>
                                <div class="cp-list__body">
                                    <p class="cp-list__title">{{ $reward->name }}</p>
                                    <p class="cp-list__sub">
                                        {{ number_format((int) $reward->points_required) }} نقطة
                                        @unless ($affordable)
                                            — يلزمك {{ number_format((int) $reward->points_required - $currentBalance) }} نقطة إضافية
                                        @endunless
                                    </p>
                                </div>
                                <span class="cp-badge cp-badge--{{ $affordable ? 'success' : 'muted' }}">
                                    {{ $affordable ? 'متاحة لك' : 'لم تكتمل' }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    {{-- الاستبدالُ من عند الموظّف: المكافأة تُسلَّم يداً بيد،
                         ولا زرَّ هنا يَعِد بما لا يُنفَّذ آلياً --}}
                    <div class="cp-card__body">
                        <p class="cp-muted" style="margin:0;font-size:.78rem">
                            للاستبدال تواصل معنا أو اطلبه عند زيارتك القادمة.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-trophy"></i>إنجازاتك</h2>
                <span class="cp-muted" style="font-size:.76rem">
                    {{ $userAchievements->count() }} من {{ $allAvailableAchievements->count() }}
                </span>
            </div>

            <div class="cp-card__body cp-card__body--flush">
                @if ($allAvailableAchievements->isEmpty())
                    <div class="cp-empty">
                        <i class="fa fa-trophy"></i>
                        <p>لا إنجازات مُعرَّفة بعد.</p>
                    </div>
                @else
                    @php $achievedIds = $userAchievements->pluck('achievement_id')->all(); @endphp
                    <div class="cp-list">
                        @foreach ($allAvailableAchievements as $achievement)
                            @php $done = in_array($achievement->id, $achievedIds, true); @endphp
                            <div class="cp-list__item" style="{{ $done ? '' : 'opacity:.6' }}">
                                <span class="cp-list__icon"
                                      style="{{ $done ? 'background:color-mix(in srgb,var(--cp-success) 12%,transparent);color:var(--cp-success)' : '' }}">
                                    <i class="fa fa-{{ $done ? 'check' : 'lock' }}"></i>
                                </span>
                                <div class="cp-list__body">
                                    <p class="cp-list__title">{{ $achievement->name }}</p>
                                    @if ($achievement->description)
                                        <p class="cp-list__sub">{{ $achievement->description }}</p>
                                    @endif
                                </div>
                                @if ($achievement->bonus_points)
                                    <span class="cp-list__side cp-muted">+{{ number_format((int) $achievement->bonus_points) }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════ الإحالات ══════════ --}}
    @if (($referralReady ?? false) && $referralSettings && $referralSettings->is_active)
        <div class="cp-card" style="margin-top:1rem">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-user-friends"></i>أحِل صديقاً</h2>
                <span class="cp-muted" style="font-size:.76rem">
                    {{ $referralStats['confirmed'] }} مؤكّدة من {{ $referralStats['total'] }}
                </span>
            </div>

            <div class="cp-card__body">
                @if ($portalClient?->referral_code)
                    <p class="cp-muted" style="margin-top:0;font-size:.82rem">
                        كودك للإحالة: <strong class="cp-num" dir="ltr">{{ $portalClient->referral_code }}</strong>
                        — تكسب {{ number_format((int) $referralSettings->referrer_points) }} نقطة عن كل إحالة مؤكّدة.
                    </p>
                @endif

                <form method="POST" action="{{ route('clients.portal.points.refer') }}">
                    @csrf
                    <div class="cp-grid">
                        <div class="cp-field">
                            <label for="referred_name">اسم من تُحيله</label>
                            <input type="text" id="referred_name" name="referred_name" maxlength="255" required
                                   value="{{ old('referred_name') }}">
                        </div>

                        <div class="cp-field">
                            <label for="referred_phone">رقم جواله</label>
                            <input type="text" id="referred_phone" name="referred_phone" maxlength="20" required
                                   value="{{ old('referred_phone') }}" dir="ltr">
                        </div>
                    </div>

                    <button type="submit" class="cp-btn cp-btn--primary" style="margin-top:.75rem">
                        <i class="fa fa-paper-plane"></i> أرسل الإحالة
                    </button>
                </form>
            </div>

            @if ($referrals->isNotEmpty())
                <div class="cp-card__body cp-card__body--flush">
                    <div class="cp-tablewrap">
                        <table class="cp-table">
                            <thead>
                                <tr>
                                    <th>من أحلته</th>
                                    <th>التاريخ</th>
                                    <th class="cp-num">نقاطك</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($referrals as $referral)
                                    @php
                                        $status = $referral->status instanceof BackedEnum
                                            ? $referral->status->value
                                            : (string) $referral->status;
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ $referral->referred->trade_name
                                                ?? ($referral->metadata['referred_name'] ?? '—') }}
                                        </td>
                                        <td class="cp-num">{{ $referral->created_at?->format('Y-m-d') }}</td>
                                        <td class="cp-num">{{ number_format((int) $referral->referrer_points) }}</td>
                                        <td>
                                            <span class="cp-badge cp-badge--{{ $status === 'confirmed' ? 'success' : ($status === 'pending' ? 'warning' : 'muted') }}">
                                                {{ $status === 'confirmed' ? 'مؤكّدة' : ($status === 'pending' ? 'بانتظار التأكيد' : 'منتهية') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection
