@extends('account::master')

@section('title', 'لوحة الحسابات')

@section('content')
    <div class="content-body">

        {{--
            كانت الشاشة بطاقةً واحدة: «أهلاً <الاسم>، مرحباً بعودتك» وتاريخُ
            اليوم ورابطٌ إلى الموقع — لا رقم ولا مؤشّر. لوحةُ تحكّمٍ لا
            تُحكِم شيئاً، ولا تُغني المحاسب عن فتح كل شاشةٍ ليعرف حاله.

            ووسم `<script>` لمكتبة الرسوم كان خارج أي `@section` — يُطبع قبل
            `@extends` فيُهمَل، والمكتبة لا تُحمَّل أصلاً.
        --}}
        @include('account::components.page-head', [
            'title' => 'لوحة الحسابات',
            'icon' => 'bar-chart-2',
            'sub' => 'حال الدورة المحاسبية في ' . $periodLabel,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الحسابات' => null,
                'اللوحة' => null,
            ],
        ])

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-briefcase"></i></span>
                <span class="stk-metric__label">رصيد الخزائن والبنوك</span>
                <span class="stk-metric__value">@money($cashBalance)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-plus"></i></span>
                <span class="stk-metric__label">المقبوضات في الفترة</span>
                <span class="stk-metric__value">@money($receiptsTotal)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-minus"></i></span>
                <span class="stk-metric__label">المصروفات في الفترة</span>
                <span class="stk-metric__value">@money($expensesTotal)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-edit-3"></i></span>
                <span class="stk-metric__label">قيود الفترة</span>
                <span class="stk-metric__value stk-num">{{ number_format($entriesCount) }}</span>
            </div>
        </div>

        @if ($unpostedCount > 0)
            <div class="alert alert-warning" role="alert">
                <i class="feather icon-alert-triangle"></i>
                <strong>{{ number_format($unpostedCount) }} قيداً غير مُرحَّل.</strong>
                القيد غير المرحَّل لا يظهر في ميزان المراجعة ولا يُحرّك رصيد حسابه.
                @if (Route::has('journal.index'))
                    <a href="{{ route('journal.index', ['is_posted' => 0]) }}" class="alert-link">راجعها</a>.
                @endif
            </div>
        @endif

        <div class="row">

            {{-- ═══════════ الخزائن ═══════════ --}}
            <div class="col-lg-5 col-md-6">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-briefcase"></i> الخزائن والبنوك
                            <span class="stk-count">{{ $treasuries->count() }}</span>
                        </h6>
                    </div>

                    @if ($treasuries->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>الحساب</th>
                                        <th class="text-center">الكود</th>
                                        <th class="text-center">الرصيد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($treasuries as $treasury)
                                        <tr>
                                            <td>{{ $treasury->name }}</td>
                                            <td class="text-center stk-num">{{ $treasury->code ?? '—' }}</td>
                                            <td class="text-center"><strong>@money($treasury->balance)</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="stk-table-foot">
                            <span class="stk-table-foot__count">
                                الإجمالي: <strong>@money($treasuries->sum('balance'))</strong>
                            </span>
                            @if (Route::has('treasury.index'))
                                <a href="{{ route('treasury.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="feather icon-arrow-left"></i> كل الخزائن
                                </a>
                            @endif
                        </div>
                    @else
                        <div class="stk-empty">
                            <span class="stk-empty__icon"><i class="feather icon-briefcase"></i></span>
                            <h6 class="stk-empty__title">لا خزائن معرَّفة</h6>
                            <p class="stk-empty__hint">
                                بلا خزينةٍ لا يمكن تحرير سند صرفٍ ولا قبض.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ═══════════ آخر القيود ═══════════ --}}
            <div class="col-lg-7 col-md-6">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-edit-3"></i> آخر القيود
                            <span class="stk-count">{{ $latestEntries->count() }}</span>
                        </h6>
                    </div>

                    @if ($latestEntries->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>الرقم</th>
                                        <th>البيان</th>
                                        <th class="text-center">التاريخ</th>
                                        <th class="text-center">المبلغ</th>
                                        <th class="text-center">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($latestEntries as $entry)
                                        <tr>
                                            <td class="stk-num">
                                                @if (Route::has('journal.show'))
                                                    <a href="{{ route('journal.show', $entry->id) }}">
                                                        {{ $entry->reference_number ?? '#' . $entry->id }}
                                                    </a>
                                                @else
                                                    {{ $entry->reference_number ?? '#' . $entry->id }}
                                                @endif
                                            </td>
                                            <td>{{ Str::limit($entry->description, 46) }}</td>
                                            <td class="text-center stk-num">
                                                {{ $entry->date?->format('Y-m-d') ?? '—' }}
                                            </td>
                                            <td class="text-center">
                                                @money($entry->details->sum('debit'), $entry)
                                            </td>
                                            <td class="text-center">
                                                @if ($entry->is_posted)
                                                    <span class="stk-state stk-state--on">مرحَّل</span>
                                                @else
                                                    <span class="stk-state stk-state--neutral">غير مرحَّل</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if (Route::has('journal.index'))
                            <div class="stk-table-foot">
                                <span class="stk-table-foot__count">أحدث ثمانية قيود</span>
                                <a href="{{ route('journal.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="feather icon-arrow-left"></i> كل القيود
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="stk-empty">
                            <span class="stk-empty__icon"><i class="feather icon-edit-3"></i></span>
                            <h6 class="stk-empty__title">لا قيود بعد</h6>
                            <p class="stk-empty__hint">تظهر هنا القيود فور تحريرها يدوياً أو توليدها من المستندات.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
