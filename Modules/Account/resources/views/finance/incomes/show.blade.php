@extends('account::master')

@section('title', 'تفاصيل سند القبض')

@section('content')
    @php
        // $canEdit يُمرَّر من IncomesController::show() — الوصول إلى هذه
        // الصفحة يعني أن authorizeCreatorScope() أجاز العرض بالفعل

        $states = [
            1 => ['stk-state--on', 'نشط'],
            2 => ['stk-state--neutral', 'متوقف'],
            3 => ['stk-state--off', 'غير نشط'],
        ];

        [$stateClass, $stateLabel] = $states[$income->status] ?? ['stk-state--neutral', 'غير معروفة'];

        $attachments = $income->attachments ? json_decode($income->attachments, true) : null;

        if (!$attachments && $income->attachments) {
            $attachments = [$income->attachments];
        }

        $taxTotal = ($income->tax1_amount ?? 0) + ($income->tax2_amount ?? 0);
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('incomes.print', [$income->id, 'normal']) }}" class="btn btn-outline-secondary">
                <i class="feather icon-printer"></i> طباعة
            </a>
            <a href="{{ route('incomes.print', [$income->id, 'thermal']) }}" class="btn btn-outline-secondary">
                <i class="feather icon-align-justify"></i> طباعة حرارية
            </a>
            @if ($canEdit)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                    data-bs-target="#cancelIncomeModal">
                    <i class="feather icon-x-circle"></i> إلغاء السند
                </button>
                <a href="{{ route('incomes.edit', $income->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
        @endsection

        @include('account::components.page-head', [
            'title' => 'سند قبض ' . $income->code,
            'icon' => 'file-plus',
            'sub' => ($income->incomes_category->name ?? 'غير مصنّف') . ' — ' . $stateLabel . ($income->is_taxable ? ' — سند ضريبي' : ''),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'سندات القبض' => route('incomes.index'),
                $income->code => null,
            ],
        ])

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">المبلغ قبل الضريبة</span>
                <span class="stk-metric__value">@money($income->amount ?? 0, $income)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">الضريبة</span>
                <span class="stk-metric__value">@money($taxTotal, $income)</span>
            </div>
            {{--
                الإجمالي شاملاً الضريبة كان غائباً عن ترويسة السند: يُعرض
                المبلغُ قبلها والضريبةُ منفردةً، ويُترك القارئُ يجمع. وهو
                الرقمُ الذي دخل الخزينة فعلاً، فحقُّه أن يُقال.
            --}}
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">الإجمالي شامل الضريبة</span>
                <span class="stk-metric__value">@money($income->total_amount ?: (($income->amount ?? 0) + $taxTotal), $income)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">التاريخ</span>
                <span class="stk-metric__value stk-num">{{ $income->date ?? '—' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-briefcase"></i></span>
                <span class="stk-metric__label">الخزينة</span>
                <span class="stk-metric__value">{{ $income->treasury->name ?? 'غير محددة' }}</span>
            </div>
        </div>

        @include('account::finance.partials.recurring-voucher', ['voucher' => $income, 'showRoute' => 'incomes.show'])

        <div class="card">
            <div class="card-body">
                {{--
                    كانت هنا أربعة ألسنة: «مطبوعات» يحشر مستند
                    `print_normal` كاملاً — بوسم <html> و<head> و<style> خاصّته —
                    داخل الصفحة، فتتسرّب أنماطه إلى بقيّتها؛ ولسان `#about`
                    بمحتوى «time table» نصّاً حرفياً وبلا زرٍّ يفتحه أصلاً.

                    الطباعة صارت زرّاً في ترويسة الصفحة يفتح المستند في نافذته،
                    وبقي من الألسنة ما له محتوى فعلاً.
                --}}
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details"
                            type="button" role="tab" aria-controls="details" aria-selected="true">
                            <i class="feather icon-file-text"></i> التفاصيل
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="journal-tab" data-bs-toggle="tab" data-bs-target="#journal"
                            type="button" role="tab" aria-controls="journal" aria-selected="false">
                            <i class="feather icon-book"></i> القيد المحاسبي
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity"
                            type="button" role="tab" aria-controls="activity" aria-selected="false">
                            <i class="feather icon-activity"></i> سجل النشاط
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-3">

                    <div class="tab-pane active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>الكود</dt>
                                        <dd class="stk-num">{{ $income->code }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الحالة</dt>
                                        <dd><span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span></dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الوصف</dt>
                                        <dd>{{ $income->description ?: '—' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>التصنيف</dt>
                                        <dd>{{ $income->incomes_category->name ?? 'غير مصنّف' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الحساب</dt>
                                        <dd>{{ $income->account->name ?? 'غير محدد' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>العميل</dt>
                                        <dd>{{ $income->client->trade_name ?? 'غير محدد' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>طريقة القبض</dt>
                                        <dd>{{ $income->paymentMethod->name ?? 'غير محددة' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الفرع</dt>
                                        <dd>{{ $income->branch->name ?? ($income->user->branch->name ?? 'غير محدد') }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>أنشئ بواسطة</dt>
                                        <dd>{{ $income->user->name ?? 'غير معروف' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الضريبة الأولى</dt>
                                        <dd>
                                            <x-tax-line :tax="$income->tax1Setting" :amount="$income->tax1_amount" :doc="$income" />
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الضريبة الثانية</dt>
                                        <dd>
                                            <x-tax-line :tax="$income->tax2Setting" :amount="$income->tax2_amount" :doc="$income" />
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>المرفقات</dt>
                                        <dd>
                                            @forelse ($attachments ?? [] as $attachment)
                                                @php
                                                    $isImage = in_array(
                                                        strtolower(pathinfo($attachment, PATHINFO_EXTENSION)),
                                                        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                                                    );
                                                @endphp

                                                @if ($isImage)
                                                    <a href="{{ asset($attachment) }}" target="_blank">
                                                        <img src="{{ asset($attachment) }}" alt="مرفق"
                                                            class="stk-media__thumb mb-1" style="width:64px;height:64px">
                                                    </a>
                                                @else
                                                    <a href="{{ asset($attachment) }}" target="_blank" class="d-block">
                                                        <i class="feather icon-file"></i> {{ basename($attachment) }}
                                                    </a>
                                                @endif
                                            @empty
                                                —
                                            @endforelse
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="journal" role="tabpanel" aria-labelledby="journal-tab">
                        @if ($journalEntry)
                            <dl class="stk-def stk-def--inline mb-3">
                                <div class="stk-def__row">
                                    <dt>رقم القيد</dt>
                                    <dd><b class="stk-num">{{ $journalEntry->id }}</b></dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>تاريخ القيد</dt>
                                    <dd><b class="stk-num">{{ $journalEntry->date }}</b></dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>البيان</dt>
                                    <dd><b>{{ $journalEntry->description }}</b></dd>
                                </div>
                            </dl>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>الحساب</th>
                                            <th class="text-center">مدين</th>
                                            <th class="text-center">دائن</th>
                                            <th>البيان</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($journalEntry->details as $detail)
                                            @php
                                                $detailAccount = \App\Models\Account::find($detail->account_id);
                                            @endphp

                                            <tr>
                                                <td>{{ $detailAccount->name ?? 'غير محدد' }}</td>
                                                <td class="text-center">
                                                    {{ $detail->debit > 0 ? \App\Support\CurrencySettings::format($detail->debit) : '—' }}
                                                </td>
                                                <td class="text-center">
                                                    {{ $detail->credit > 0 ? \App\Support\CurrencySettings::format($detail->credit) : '—' }}
                                                </td>
                                                <td>{{ $detail->description ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>

                                    <tfoot>
                                        <tr class="stk-total-row">
                                            <th>الإجمالي</th>
                                            <th class="text-center">@money($journalEntry->details->sum('debit'))</th>
                                            <th class="text-center">@money($journalEntry->details->sum('credit'))</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-book"></i></span>
                                <h6 class="stk-empty__title">لا قيد محاسبي مرتبط</h6>
                                <p class="stk-empty__hint">لم يُنشأ قيدٌ لهذا السند — راجع إعدادات توجيه الحسابات.</p>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                        {{--
                            الخطّ الزمني كان يستعمل أصناف `timeline` و`timeline-item`
                            المعرَّفة في شاشة المصروفات وحدها — أي أنّ هذه الصفحة
                            كانت تعرض قائمةً عاريةً بلا خطٍّ ولا نقاط. صار
                            stk-timeline المشترك.
                        --}}
                        @if ($logs && count($logs) > 0)
                            @foreach ($logs as $date => $dayLogs)
                                @php
                                    $currentDate = \Carbon\Carbon::parse($date);
                                @endphp

                                <div class="stk-section__head">
                                    <i class="feather icon-calendar"></i>
                                    <h6 class="stk-section__title">
                                        {{ $currentDate->translatedFormat('l') }} — {{ $currentDate->format('Y-m-d') }}
                                    </h6>
                                </div>

                                <div class="stk-timeline mb-4">
                                    @foreach ($dayLogs as $log)
                                        @if ($log)
                                            <div class="stk-timeline__item">
                                                <span class="stk-timeline__dot stk-timeline__dot--log">
                                                    <i class="feather icon-activity"></i>
                                                </span>

                                                <div class="stk-timeline__body">
                                                    <div class="stk-timeline__text">
                                                        <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                        {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                                                    </div>

                                                    <div class="stk-timeline__meta">
                                                        <span class="stk-timeline__time">
                                                            {{ $log->created_at->format('H:i') }}
                                                        </span>
                                                        <span>{{ $log->user->branch->name ?? 'فرع غير معروف' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-activity"></i></span>
                                <h6 class="stk-empty__title">لا سجلّات نشاط</h6>
                                <p class="stk-empty__hint">لم تُسجَّل عمليات على هذا السند بعد.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{--
            نافذة الحذف كانت تقول «عفوا لا يمكن الحذف يمكنك اصدار سند صرف»
            ثم تنتهي بلا زرٍّ ولا إجراء — رسالةٌ في هيئة نافذة تأكيد. وسند
            القبض يُلغى فعلاً (`incomes.cancel`) لا يُحذف، فصارت النافذة
            تُلغيه وتشرح ما سيحدث.
        --}}
        <div class="modal fade" id="cancelIncomeModal" tabindex="-1" aria-labelledby="cancelIncomeModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelIncomeModalLabel">إلغاء سند القبض</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-0">
                            سيُلغى سند القبض <strong>{{ $income->code }}</strong> بمبلغ
                            <strong>@money($income->amount ?? 0, $income)</strong>، ويُعكَس قيده المحاسبي فيعود المبلغ من
                            الخزينة. لا رجوع عن هذا.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">تراجع</button>
                        <form action="{{ route('incomes.cancel', $income->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-danger">
                                <i class="feather icon-x-circle"></i> تأكيد الإلغاء
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
