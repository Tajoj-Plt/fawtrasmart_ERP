@extends('account::master')

@section('title', 'تفاصيل سند الصرف')

@section('content')
    @php
        // $canEdit يُمرَّر من ExpensesController::show() — الوصول إلى هذه
        // الصفحة يعني أن authorizeCreatorScope() أجاز العرض بالفعل

        /*
        سندُ الصرف لا دورةَ اعتمادٍ له: لا عمودَ `status`
        في جدوله أصلاً (خلافاً لسند القبض). فكان
        `$expense->status` فارغاً دائماً والشاشةُ تقول
        «غير معروفة» عن كل سند.

        والصرفُ يقع بالحفظ، فالسندُ معتمدٌ بوجوده.
        */
        [$stateClass, $stateLabel] = ['stk-state--on', 'معتمد'];

        $attachments = $expense->attachments ? json_decode($expense->attachments, true) : null;

        if (!$attachments && $expense->attachments) {
            $attachments = [$expense->attachments];
        }

        /*
            الجهةُ المقابلة تُعرض بحسب ما هي، لا ثلاثةَ صفوفٍ أحدُها مملوء.

            كان الصفُّ «المورد» يظهر دائماً — ويقول «غير محدد» في كل سندٍ
            ليس لمورد، وهي أكثرُ السندات. والسندُ إمّا لمورد، أو لجهةٍ في
            شجرة الحسابات (عميلٍ أو غيره)، أو قيدٌ عامّ لا جهةَ له.
        */
        $partyLabel = null;
        $partyValue = null;
        $partyLink = null;

        if ($expense->supplier_id && $expense->Supplier) {
            $partyLabel = 'المورد';
            $partyValue = $expense->Supplier->trade_name ?? $expense->Supplier->name ?? '—';
        } elseif ($expense->account) {
            /*
                حسابُ العملاء أبٌ في الشجرة، فما تحته عميل. والأبُ يُقرأ من
                «الحسابات المحاسبية» لا من رقمٍ مكتوب.
            */
            $clientsParent = \App\Support\AccountingSettings::id('clients_account');
            $isClient = $clientsParent && $expense->account->parent_id == $clientsParent;

            $partyLabel = $isClient ? 'العميل' : 'الحساب المرتبط';
            $partyValue = trim(($expense->account->code ? $expense->account->code . ' — ' : '') . $expense->account->name);
            $partyLink = $expense->account->id;
        } elseif (filled($expense->seller)) {
            $partyLabel = 'صُرف إلى';
            $partyValue = $expense->seller;
        }
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('expenses.print', [$expense->id, 'normal']) }}" class="btn btn-outline-secondary">
                <i class="feather icon-printer"></i> طباعة
            </a>
            <a href="{{ route('expenses.print', [$expense->id, 'thermal']) }}" class="btn btn-outline-secondary">
                <i class="feather icon-align-justify"></i> طباعة حرارية
            </a>
            @if ($canEdit)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                    data-bs-target="#deleteExpenseModal">
                    <i class="feather icon-trash-2"></i> حذف
                </button>
                <a href="{{ route('expenses.edit', $expense->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
        @endsection

        @include('account::components.page-head', [
            'title' => 'سند صرف ' . $expense->code,
            'icon' => 'file-minus',
            'sub' => ($expense->expenses_category->name ?? 'غير مصنّف') . ' — ' . $stateLabel . ($expense->is_taxable ? ' — سند ضريبي' : ''),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'سندات الصرف' => route('expenses.index'),
                $expense->code => null,
            ],
        ])


        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">المبلغ قبل الضريبة</span>
                <span class="stk-metric__value">@money($expense->amount ?? 0, $expense)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">الإجمالي بعد الضريبة</span>
                <span class="stk-metric__value">
                    {{-- `??` تقبل صفراً مخزَّناً؛ و`?:` تسقط إلى المبلغ حين لم يُحسب الإجمالي بعد --}}
                    @money($expense->total_amount ?: ($expense->amount ?? 0), $expense)
                </span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">التاريخ</span>
                <span class="stk-metric__value stk-num">{{ $expense->date ?? '—' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-briefcase"></i></span>
                <span class="stk-metric__label">الخزينة</span>
                <span class="stk-metric__value">{{ $expense->treasury->name ?? 'غير محددة' }}</span>
            </div>
        </div>

        @include('account::finance.partials.recurring-voucher', ['voucher' => $expense, 'showRoute' => 'expenses.show'])

        <div class="card">
            <div class="card-body">
                {{--
                    الألسنة كانت تحمل لساناً رابعاً `#about` بمحتوى «time table»
                    نصّاً حرفياً بلا زرٍّ يفتحه، ولساناً `#profile` فارغاً بخلفية
                    رمادية. بقي ما له محتوى فعلاً.
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
                                        <dd class="stk-num">{{ $expense->code }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الحالة</dt>
                                        <dd><span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span></dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الوصف</dt>
                                        <dd>{{ $expense->description ?: '—' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>التصنيف</dt>
                                        <dd>{{ $expense->expenses_category->name ?? 'غير مصنّف' }}</dd>
                                    </div>
                                    @if ($partyLabel)
                                        <div class="stk-def__row">
                                            <dt>{{ $partyLabel }}</dt>
                                            <dd>
                                                @if ($partyLink)
                                                    @can('account.chart.view')
                                                        <a href="{{ route('accounts_chart.view', $partyLink) }}">{{ $partyValue }}</a>
                                                    @else
                                                        {{ $partyValue }}
                                                    @endcan
                                                @else
                                                    {{ $partyValue }}
                                                @endif
                                            </dd>
                                        </div>
                                    @endif
                                    <div class="stk-def__row">
                                        <dt>أنشئ بواسطة</dt>
                                        <dd>{{ $expense->createdBy->name ?? 'غير معروف' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>طريقة الدفع</dt>
                                        <dd>{{ $expense->paymentMethod->name ?? 'غير محددة' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الفرع</dt>
                                        <dd>
                                            {{ $expense->branch->name ?? ($expense->createdBy->branch->name ?? 'غير محدد') }}
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الضريبة الأولى</dt>
                                        <dd>
                                            <x-tax-line :tax="$expense->tax1Setting" :amount="$expense->tax1_amount" :doc="$expense" />
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الضريبة الثانية</dt>
                                        <dd>
                                            <x-tax-line :tax="$expense->tax2Setting" :amount="$expense->tax2_amount" :doc="$expense" />
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>المرفقات</dt>
                                        <dd>
                                            {{-- عارضٌ واحد لكل الأنواع: صورةٌ أو PDF أو مستند — بأيقونته
                                                 وحجمه وأزرارِ عرضٍ وتنزيل، وPDF بمعاينةٍ داخل الصفحة. --}}
                                            <x-attachment-list :items="$attachments ?? []" />
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
                            الخطّ الزمني كان تسعين سطراً من CSS في رأس الصفحة: خطٌّ
                            أخضر متدرّج بموضع مطلق، ونقاطٌ محسوبة بالبكسل، ولا يتبع
                            سمة النظام ولا اتّجاه الصفحة. صار stk-timeline المشترك.
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
            كان هنا نافذتا حذفٍ بالمعرّف نفسه: الأولى تقول «عفوا لا يمكنك الحذف»
            والثانية تسأل التأكيد. بوتستراب يفتح الأولى دائماً، فزرّ الحذف كان
            معطَّلاً فعلياً. بقيت واحدة تسأل وتحذف.
        --}}
        <div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-labelledby="deleteExpenseModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteExpenseModalLabel">حذف سند الصرف</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-0">
                            سيُحذف سند الصرف <strong>{{ $expense->code }}</strong> بمبلغ
                            <strong>@money($expense->amount ?? 0, $expense)</strong> ويُعكَس قيده المحاسبي. لا رجوع عن هذا.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <form method="POST" action="{{ route('expenses.delete', $expense->id) }}" class="d-inline"
                            onsubmit="this.querySelector('button').disabled = true;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="feather icon-trash-2"></i> تأكيد الحذف
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
