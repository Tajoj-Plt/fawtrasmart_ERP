@extends('client::portal.layout')

{{--
    الصفحة الرئيسية في بوابة العميل — على قياس التصميم الذي أرسله المستخدم
    (2026-09-19): بطاقةُ ترحيبٍ، ثمّ بطاقاتُ الوصول السريع بأسهمها، ثمّ
    (ملخّص الحساب الداكن + منحنى ستّة أشهر + إشعاراتك)، ثمّ (آخر المعاملات +
    المواعيد القادمة + ادعُ إلى الحجز)، ثمّ شريطُ القيم في الذيل.

    **بلا «الدعم الفني»** — استثناه المستخدم صراحةً، فلا بطاقةَ تَعِد بشاشةٍ
    لم تُبنَ.

    ══════════ الألوان من «مظهر النظام» ══════════

    لا لونَ مكتوبٌ هنا: الكحليُّ `--cp-nav-bg` (لون شريط النظام)، والأساسيُّ
    `--cp-primary`، والنجاحُ والخطرُ من الإعدادات. فتغييرُ مظهر المنشأة يظهر
    في البوابة كما يظهر في شاشات الموظفين.

    ══════════ المنحنى بلا مكتبة ══════════

    رسمٌ داخليٌّ (SVG): البوابة لا تُحمّل مكتبةَ رسمٍ لستّة أرقام، وأرقامُه
    من قيود حساب العميل نفسِها التي يُبنى منها كشفُه.

    ══════════ وكلُّ بطاقةٍ تُخفى إن لم يكن لها باب ══════════

    `$can()` يفحص وجودَ المسار وكونَه مسموحاً في «صلاحيات البوابة» معاً.
--}}

@section('title', 'الرئيسية')

{{-- بطاقةُ الترحيب هي عنوانُ هذه الصفحة — وعنوانٌ نصّيٌّ فوقها تكرارٌ يزيح المحتوى --}}
@section('styles')
    <style>
        .cp-head { display: none; }

        /* جداولُ البطاقات الضيّقة: بحشوٍ كاملٍ يخرج العمودُ الأخير ويظهر تمريرٌ أفقيّ */
        .cp-grid-2 .cp-table, .cp-grid-3 .cp-table { font-size: .8rem; }
        .cp-grid-2 .cp-table thead th, .cp-grid-2 .cp-table tbody td,
        .cp-grid-3 .cp-table thead th, .cp-grid-3 .cp-table tbody td { padding-inline: .6rem; }
    </style>
@endsection

@php
    use App\Support\CurrencySettings;

    $money = fn ($v) => CurrencySettings::format(abs((float) $v));
    $allowed = $allowedRoutes ?? [];
    $can = fn (string $route) => \Illuminate\Support\Facades\Route::has($route) && in_array($route, $allowed, true);
@endphp

@section('hero')
    <div class="cp-hero">
        <span class="cp-hero__avatar"><i class="fa fa-user"></i></span>

        <div class="cp-hero__body">
            <h2 class="cp-hero__name">مرحباً بك في بوابة العميل</h2>
            <p style="margin:.4rem 0 0;font-size:.85rem;opacity:.85">
                يمكنك من خلال هذه البوابة متابعة جميع تعاملاتك معنا بسهولة
            </p>
            <div class="cp-hero__meta">
                <span class="cp-chip"><i class="fa fa-hashtag"></i> العميل رقم: {{ $portalClient->code ?? $portalClient->id }}</span>
                @if ($portalClient?->trade_name)
                    <span class="cp-chip"><i class="fa fa-building"></i> {{ $portalClient->trade_name }}</span>
                @endif
                @if ($portalClient?->tax_number)
                    <span class="cp-chip"><i class="fa fa-file-alt"></i> الرقم الضريبي: {{ $portalClient->tax_number }}</span>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('content')
    {{-- ══════════ الوصول السريع ══════════ --}}
    <div class="cp-tiles">
        @if ($can('clients.portal.statement'))
            <a href="{{ route('clients.portal.statement') }}" class="cp-tile">
                <span class="cp-tile__icon cp-tile__icon--info"><i class="fa fa-file-alt"></i></span>
                <div>
                    <p class="cp-tile__title">كشف حسابك</p>
                    <p class="cp-tile__desc">عرض جميع المعاملات</p>
                </div>
                <i class="fa fa-chevron-left cp-tile__go"></i>
            </a>
        @endif

        @if ($can('clients.portal.returns'))
            <a href="{{ route('clients.portal.returns') }}" class="cp-tile">
                <span class="cp-tile__icon cp-tile__icon--danger"><i class="fa fa-undo"></i></span>
                <div>
                    <p class="cp-tile__title">مرتجعاتك</p>
                    <p class="cp-tile__desc">متابعة المرتجعات</p>
                </div>
                <i class="fa fa-chevron-left cp-tile__go"></i>
            </a>
        @endif

        @if ($can('clients.invoice_client'))
            <a href="{{ route('clients.invoice_client') }}" class="cp-tile">
                <span class="cp-tile__icon"><i class="fa fa-file-invoice"></i></span>
                <div>
                    <p class="cp-tile__title">فواتيرك</p>
                    <p class="cp-tile__desc">عرض وسداد الفواتير</p>
                </div>
                <i class="fa fa-chevron-left cp-tile__go"></i>
            </a>
        @endif

        @if ($can('clients.portal.reservations.index'))
            <a href="{{ route('clients.portal.reservations.index') }}" class="cp-tile">
                <span class="cp-tile__icon cp-tile__icon--success"><i class="fa fa-calendar-check"></i></span>
                <div>
                    <p class="cp-tile__title">حجوزاتك ومواعيدك</p>
                    <p class="cp-tile__desc">احجز مواعيدك بسهولة</p>
                </div>
                <i class="fa fa-chevron-left cp-tile__go"></i>
            </a>
        @endif

        @if ($can('clients.portal.points'))
            <a href="{{ route('clients.portal.points') }}" class="cp-tile">
                <span class="cp-tile__icon cp-tile__icon--warning"><i class="fa fa-award"></i></span>
                <div>
                    <p class="cp-tile__title">نقاطك ومكافآتك</p>
                    <p class="cp-tile__desc">رصيدك ومستواك</p>
                </div>
                <i class="fa fa-chevron-left cp-tile__go"></i>
            </a>
        @endif

        @if ($can('clients.portal.visit_requests.index'))
            <a href="{{ route('clients.portal.visit_requests.index') }}" class="cp-tile">
                <span class="cp-tile__icon cp-tile__icon--info"><i class="fa fa-map-marker-alt"></i></span>
                <div>
                    <p class="cp-tile__title">طلب زيارة ميدانية</p>
                    <p class="cp-tile__desc">اطلب زيارة مندوبنا</p>
                </div>
                <i class="fa fa-chevron-left cp-tile__go"></i>
            </a>
        @endif
    </div>

    {{-- ══════════ ملخّص الحساب · المنحنى · الإشعارات ══════════ --}}
    <div class="cp-grid-3" style="margin-top:1rem">

        <div class="cp-balance">
            <div class="cp-balance__head"><i class="fa fa-wallet"></i> ملخّص الحساب</div>

            <div>
                <p class="cp-balance__label">{{ ($ledgerBalance ?? 0) >= 0 ? 'الرصيد المستحقّ عليك' : 'الرصيد لك' }}</p>
                <p class="cp-balance__value">{{ $money($ledgerBalance ?? 0) }}</p>
                <span class="cp-balance__tag">
                    <i class="fa fa-circle" style="font-size:.4rem"></i>
                    {{ ($ledgerBalance ?? 0) > 0 ? 'قابل للسداد من «فواتيري»' : 'لا مستحقّات علیك' }}
                </span>
            </div>

            <div class="cp-balance__split">
                <div class="cp-balance__box">
                    <div>
                        <p class="v">{{ $money($returnsTotal ?? 0) }}</p>
                        <p class="l">إجمالي المرتجعات</p>
                    </div>
                    <span class="cp-balance__arrow cp-balance__arrow--down"><i class="fa fa-arrow-down"></i></span>
                </div>

                <div class="cp-balance__box">
                    <div>
                        <p class="v">{{ $money($ledgerPaid ?? 0) }}</p>
                        <p class="l">إجمالي المدفوعات</p>
                    </div>
                    <span class="cp-balance__arrow cp-balance__arrow--up"><i class="fa fa-arrow-up"></i></span>
                </div>
            </div>
        </div>

        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-chart-line"></i>حركة الحساب خلال آخر ٦ أشهر</h2>
            </div>

            <div class="cp-card__body">
                @php
                    /* النقاطُ تُحسب في الخادم: SVG ثابتٌ يظهر ولو كان الجافاسكربت مطفأً */
                    $series = collect($monthlySeries ?? []);
                    $max = (float) max(1, $series->pluck('value')->map(fn ($v) => abs($v))->max() ?: 1);
                    $w = 320; $h = 120;
                    $step = $series->count() > 1 ? $w / ($series->count() - 1) : $w;

                    /* في الواجهة العربية يسير الزمنُ من اليمين إلى اليسار،
                       و SVG لا يعرف اتجاهَ الصفحة: بلا عكسِ المحور يُرسم
                       أبريل يساراً بينما اسمُه تحت اليمين — منحنًى يكذب على
                       قارئه. فالنقاطُ تُعكس هنا لا في CSS (المرآة تقلب
                       الأيقونات والنصوص معها). */
                    $rtl = ($languageIsRtl ?? true);
                    $last = max(0, $series->count() - 1);

                    $coords = $series->values()->map(function ($row, $i) use ($step, $h, $max, $rtl, $last) {
                        $y = $h - (abs((float) $row['value']) / $max) * ($h - 18) - 9;
                        $x = ($rtl ? ($last - $i) : $i) * $step;

                        return [round($x, 1), round($y, 1)];
                    });

                    $points = $coords->map(fn ($p) => $p[0].','.$p[1])->implode(' ');
                    $area = $coords->isNotEmpty()
                        ? $points.' '.$coords->last()[0].','.$h.' '.$coords->first()[0].','.$h
                        : '';

                    // الأسماءُ بترتيب الصفحة: الحاوية RTL فأوّلُ عنصرٍ يمينُها
                    $labels = $series;
                @endphp

                @if ($series->isEmpty())
                    <div class="cp-empty"><i class="fa fa-chart-line"></i><p>لا حركة على حسابك بعد.</p></div>
                @else
                    <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none"
                         style="width:100%;height:150px;overflow:hidden;display:block" role="img"
                         aria-label="حركة الحساب في آخر ستة أشهر">
                        <polygon points="{{ $area }}"
                                 fill="color-mix(in srgb, var(--cp-primary) 12%, transparent)"></polygon>
                        <polyline points="{{ $points }}" fill="none" stroke="var(--cp-primary)"
                                  stroke-width="2" vector-effect="non-scaling-stroke"
                                  stroke-linecap="round" stroke-linejoin="round"></polyline>
                        @foreach ($coords as $point)
                            <circle cx="{{ $point[0] }}" cy="{{ $point[1] }}" r="3"
                                    fill="var(--cp-surface)" stroke="var(--cp-primary)" stroke-width="2"
                                    vector-effect="non-scaling-stroke"></circle>
                        @endforeach
                    </svg>

                    <div style="display:flex;justify-content:space-between;margin-top:.4rem">
                        @foreach ($labels as $row)
                            <span class="cp-muted" style="font-size:.7rem">{{ $row['label'] }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-bell"></i>إشعاراتك</h2>
            </div>

            <div class="cp-card__body cp-card__body--flush">
                @if (($portalNotices ?? collect())->isEmpty())
                    <div class="cp-empty"><i class="fa fa-bell-slash"></i><p>لا إشعارات جديدة.</p></div>
                @else
                    <div class="cp-list">
                        @foreach ($portalNotices as $notice)
                            @php
                                /* أيقونةُ الإشعار من نوعه — لا لونَ عشوائيّ:
                                   الفاتورةُ ملفّ، والسدادُ صحّ، والمرتجعُ سهمٌ راجع. */
                                $tone = match (true) {
                                    str_contains((string) $notice->type, 'payment') => ['check-circle', 'var(--cp-success)'],
                                    str_contains((string) $notice->type, 'return') => ['undo', 'var(--cp-danger)'],
                                    str_contains((string) $notice->type, 'reservation'),
                                    str_contains((string) $notice->type, 'appointment') => ['calendar-check', 'var(--cp-primary)'],
                                    default => ['file-invoice', 'var(--cp-info)'],
                                };
                            @endphp
                            <div class="cp-list__item">
                                <span class="cp-list__icon"
                                      style="background:color-mix(in srgb, {{ $tone[1] }} 13%, transparent);color:{{ $tone[1] }}">
                                    <i class="fa fa-{{ $tone[0] }}"></i>
                                </span>
                                <div class="cp-list__body">
                                    <p class="cp-list__title">{{ $notice->title ?: 'إشعار' }}</p>
                                    <p class="cp-list__sub">{{ \Illuminate\Support\Str::limit($notice->description, 70) }}</p>
                                    <p class="cp-list__sub">{{ $notice->created_at?->format('Y-m-d') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════ آخر المعاملات · المواعيد القادمة · احجز الآن ══════════ --}}
    <div class="cp-grid-3" style="margin-top:1rem">

        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-list"></i>آخر المعاملات</h2>
                @if ($can('clients.portal.statement'))
                    <a href="{{ route('clients.portal.statement') }}" class="cp-btn cp-btn--ghost cp-btn--sm">عرض الكل</a>
                @endif
            </div>

            <div class="cp-card__body cp-card__body--flush">
                @if (($recentMovements ?? collect())->isEmpty())
                    <div class="cp-empty"><i class="fa fa-exchange-alt"></i><p>لا حركات على حسابك بعد.</p></div>
                @else
                    <div class="cp-tablewrap">
                        <table class="cp-table">
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    <th>التاريخ</th>
                                    <th class="cp-num">المبلغ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentMovements as $movement)
                                    @php
                                        $debit = (float) $movement->debit;
                                        $entry = $movement->journalEntry;
                                    @endphp
                                    <tr>
                                        <td>{{ \Illuminate\Support\Str::limit($movement->description ?: ($entry->description ?? 'حركة'), 34) }}</td>
                                        <td class="cp-num">{{ optional($entry?->date ?? $movement->created_at)->format('Y-m-d') }}</td>
                                        <td class="cp-num" style="color:{{ $debit > 0 ? 'var(--cp-text)' : 'var(--cp-success)' }}">
                                            {{ $debit > 0 ? '' : '−' }}{{ $money($debit > 0 ? $debit : $movement->credit) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-calendar-alt"></i>المواعيد القادمة</h2>
                @if ($can('clients.portal.reservations.index'))
                    <a href="{{ route('clients.portal.reservations.index') }}" class="cp-btn cp-btn--ghost cp-btn--sm">عرض الكل</a>
                @endif
            </div>

            <div class="cp-card__body cp-card__body--flush">
                @if (($upcomingReservations ?? collect())->isEmpty())
                    <div class="cp-empty">
                        <i class="fa fa-calendar-plus"></i>
                        <p>لا مواعيد قادمة.</p>
                    </div>
                @else
                    <div class="cp-list">
                        @foreach ($upcomingReservations as $reservation)
                            <div class="cp-list__item">
                                <span class="cp-list__icon"><i class="fa fa-calendar-day"></i></span>
                                <div class="cp-list__body">
                                    <p class="cp-list__title">{{ $reservation->product->name ?? 'حجز' }}</p>
                                    <p class="cp-list__sub" dir="ltr" style="text-align:start">
                                        {{ \Illuminate\Support\Carbon::parse($reservation->reservation_date)->format('Y-m-d') }}
                                        · {{ substr((string) $reservation->start_time, 0, 5) }}
                                    </p>
                                </div>
                                <span class="cp-badge cp-badge--{{ $reservation->status->value === 'pending' ? 'warning' : 'success' }}">
                                    {{ $reservation->status->label() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem">
            @if ($can('clients.portal.reservations.create'))
                <div class="cp-promo">
                    <span class="cp-promo__icon"><i class="fa fa-calendar-plus"></i></span>
                    <div class="cp-promo__body">
                        <p class="cp-promo__title">احجز موعدك الآن</p>
                        <p class="cp-promo__desc">اختر الخدمة واليوم والوقت المناسب، وتابع حجوزاتك من مكان واحد.</p>
                    </div>
                    <a href="{{ route('clients.portal.reservations.create') }}" class="cp-btn cp-btn--primary cp-btn--sm">
                        <i class="fa fa-plus"></i> حجز موعد جديد
                    </a>
                </div>
            @endif

            @if (($recentInvoices ?? collect())->isNotEmpty())
                <div class="cp-card" style="flex:1 1 auto">
                    <div class="cp-card__head">
                        <h2 class="cp-card__title"><i class="fa fa-file-invoice"></i>الفواتير الأخيرة</h2>
                        @if ($can('clients.invoice_client'))
                            <a href="{{ route('clients.invoice_client') }}" class="cp-btn cp-btn--ghost cp-btn--sm">عرض الكل</a>
                        @endif
                    </div>

                    <div class="cp-card__body cp-card__body--flush">
                        <div class="cp-list">
                            @foreach ($recentInvoices->take(4) as $invoice)
                                @php $invoiceDue = (float) ($invoice->due_value ?? 0); @endphp
                                <div class="cp-list__item">
                                    <div class="cp-list__body">
                                        <p class="cp-list__title cp-num">{{ $invoice->code ?? $invoice->id }}</p>
                                        <p class="cp-list__sub cp-num">
                                            {{ optional($invoice->invoice_date ?? $invoice->created_at)->format('Y-m-d') }}
                                            · {{ $money($invoice->grand_total ?? 0) }}
                                        </p>
                                    </div>
                                    <span class="cp-badge cp-badge--{{ $invoiceDue <= 0 ? 'success' : ($invoiceDue < (float) $invoice->grand_total ? 'warning' : 'danger') }}">
                                        {{ $invoiceDue <= 0 ? 'مدفوعة' : ($invoiceDue < (float) $invoice->grand_total ? 'جزئية' : 'غير مدفوعة') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════ شريط القيم ══════════ --}}
    <div class="cp-foot cp-noprint">
        <div class="cp-foot__brand"><i class="fa fa-chart-line"></i> معاً لمستقبل أفضل</div>

        <div class="cp-foot__items">
            <div class="cp-foot__item">
                <span class="cp-foot__icon"><i class="fa fa-bolt"></i></span>
                <div>
                    <p class="cp-foot__t">وصول أسرع للخدمات</p>
                    <p class="cp-foot__s">حجزٌ وسدادٌ من مكانك</p>
                </div>
            </div>

            <div class="cp-foot__item">
                <span class="cp-foot__icon"><i class="fa fa-clock"></i></span>
                <div>
                    <p class="cp-foot__t">متابعة لحظية</p>
                    <p class="cp-foot__s">فواتيرك وحسابك أوّلاً بأوّل</p>
                </div>
            </div>

            <div class="cp-foot__item">
                <span class="cp-foot__icon"><i class="fa fa-shield-alt"></i></span>
                <div>
                    <p class="cp-foot__t">بياناتك آمنة</p>
                    <p class="cp-foot__s">لا يراها إلا أنت وفريقنا</p>
                </div>
            </div>
        </div>
    </div>
@endsection
