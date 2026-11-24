{{--
    عرض اشتراك دوري.

    أُعيد بناء الشاشة على قياس شاشة عرض الفاتورة (`sales::invoices.show`)
    بنظام `sl-doc-*`: ترويسة ← بطاقة هوية الاشتراك وإجمالياته ← شريط
    إجراءات ← تبويبات (الجدول، البنود، سجلّ النشاط).

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات» (كل مبلغ في الشاشة)
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • طرق الدفع       ← «طرق الدفع» (الاسم لا الرقم)
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`
      • الجدولة         ← «المهام المجدولة»: مهمّة «توليد الفواتير الدورية»

    ما أُصلح:
      • *«المدفوع» صفرٌ دائماً*: كان يُحسب بـ`payment_status === 'paid'`
        والعمود رقميّ (١ مدفوعة … ٤ مستلمة) لا نصّيّ، فالمقارنة كاذبة
        مهما سُدِّد.
      • *لا جدول للنسخ*: الاشتراك يُصدِر فواتير على مواعيد، ولم تكن
        الشاشة تُظهر ما صدر منها ولا متى تصدر التالية.
      • *بلا بنود ولا إجماليات ولا سجلّ نشاط*.
      • *نصوصٌ مكتوبةٌ في القالب* بلا ترجمة.
--}}

@extends('sales::master')

@section('title', __('sales_periodic.show.title', ['code' => $periodicInvoice->invoice_number]))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: `document-theme` لا تعرّفهما،
         وكلُّ قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $clientName = $periodicInvoice->client
            ? ($periodicInvoice->client->trade_name
                ?: trim(($periodicInvoice->client->first_name ?? '') . ' ' . ($periodicInvoice->client->last_name ?? '')))
            : __('sales_documents.ui.unknown');

        $code = $periodicInvoice->invoice_number;

        $repeatKey = \App\Models\PeriodicInvoice::REPEAT_TYPES[(int) $periodicInvoice->repeat_type] ?? null;
        $repeatLabel = __('sales_periodic.repeat_types.' . (int) $periodicInvoice->repeat_type);

        $issuedCount = $schedule->whereNotNull('invoice_id')->count();
        $failedCount = $schedule->where('status', \App\Models\PeriodicInvoiceInstance::STATUS_FAILED)->count();
        $plannedTotal = $schedule->where('status', '!=', \App\Models\PeriodicInvoiceInstance::STATUS_CANCELLED)->count();
        $isTaxed = ($periodicInvoice->total_tax ?? 0) > 0;

        // اسم طريقة الدفع من «طرق الدفع» لا رقمها
        $methods = (isset($paymentMethods) && $paymentMethods instanceof \Illuminate\Support\Collection)
            ? $paymentMethods
            : collect();
        $paymentMethodName = optional($methods->firstWhere('id', $periodicInvoice->payment_method))->name;

        // شارات الخيارات — تُعرَض المفعَّلة منها فقط
        $flags = [
            'auto_generate' => (bool) $periodicInvoice->auto_generate,
            'send_copy' => (bool) $periodicInvoice->send_copy,
            'show_dates' => (bool) $periodicInvoice->show_from_to_dates,
            'disable_partial' => (bool) $periodicInvoice->disable_partial_payment,
        ];
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route('periodic_invoices.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('sales_periodic.show.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_periodic.show.title', ['code' => $code]),
            'icon' => 'repeat',
            'sub' => $clientName,
            'crumbs' => [
                __('sales_documents.ui.home') => route('dashboard'),
                __('sales_periodic.plural') => route('periodic_invoices.index'),
                $code => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
@include('sales::components.guide', [
            'key' => 'sales-periodic.show',
            'title' => __('sales_periodic.guide.show.title'),
            'lead' => __('sales_periodic.guide.lead_show'),
            'open' => false,
            'blocks' => [
                __('sales_periodic.guide.effects.title') => [
                    __('sales_periodic.guide.effects.a'),
                    __('sales_periodic.guide.effects.b'),
                    __('sales_periodic.guide.effects.c'),
                ],
                __('sales_periodic.guide.money_flow.title') => [
                    __('sales_periodic.guide.money_flow.a'),
                    __('sales_periodic.guide.money_flow.b'),
                    __('sales_periodic.guide.money_flow.c'),
                ],
                __('sales_periodic.guide.noedit.title') => [
                    __('sales_periodic.guide.noedit.a'),
                    __('sales_periodic.guide.noedit.b'),
                ],
            ],
        ])

        {{-- ══════════ هوية الاشتراك وإجمالياته ══════════ --}}
        <div class="sl-doc-card sl-doc-card--summary">
            <div class="sl-doc-card__body">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="sl-facts">
                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.number') }}</span>
                                <span class="sl-fact__value"><span class="sl-code">{{ $code }}</span></span>
                            </div>

                            <div class="sl-fact sl-fact--wide">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.client') }}</span>
                                <span class="sl-fact__value">{{ $clientName }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.first_date') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ LanguageSettings::formatDate($periodicInvoice->first_invoice_date) }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.end_date') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ $periodicInvoice->end_date
                                        ? LanguageSettings::formatDate($periodicInvoice->end_date)
                                        : __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.repeat') }}</span>
                                <span class="sl-fact__value">
                                    {{ $repeatLabel }}
                                    @if (($periodicInvoice->repeat_interval ?? 1) > 1)
                                        <span class="sl-media__sub">
                                            ({{ __('sales_periodic.show.every', ['n' => $periodicInvoice->repeat_interval]) }})
                                        </span>
                                    @endif
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.issued_count') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ __('sales_periodic.table.issued_of', [
                                        'done' => $issuedCount,
                                        'total' => $plannedTotal,
                                    ]) }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.offset') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ __('sales_periodic.show.offset_days', ['n' => (int) ($periodicInvoice->invoice_days_offset ?? 0)]) }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.sales_person') }}</span>
                                <span class="sl-fact__value">
                                    {{ $periodicInvoice->employee->full_name ?? __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.created_by') }}</span>
                                <span class="sl-fact__value">
                                    {{ $periodicInvoice->creator->name ?? __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.payment_method') }}</span>
                                <span class="sl-fact__value">{{ $paymentMethodName ?: __('sales_documents.ui.dash') }}</span>
                            </div>

                            {{-- الإيداع يُعرَض مع طريقة الدفع لا في تبويبٍ
                                 آخر: هو من يقرّر حالةَ كلّ فاتورةٍ يُصدرها
                                 المولّد ومستحقَّها، فمن يقرأ الاشتراك يحتاج
                                 معرفتَه. ويغيب السطر متى كان صفراً بلا
                                 «مدفوعة بالكامل»: صفرٌ معروضٌ يُقرأ إعلانَ
                                 إيداعٍ مقداره لا شيء --}}
                            @if ($periodicInvoice->is_paid)
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('sales_periodic.create.deposit') }}</span>
                                    <span class="sl-fact__value">{{ __('sales_periodic.create.fully_paid') }}</span>
                                </div>
                            @elseif ((float) $periodicInvoice->advance_payment > 0)
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('sales_periodic.create.deposit') }}</span>
                                    <span class="sl-fact__value sl-num">
                                        {{ CurrencySettings::format($periodicInvoice->advance_payment) }}
                                    </span>
                                </div>
                            @endif

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_periodic.show.currency') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ $periodicInvoice->currency ?: CurrencySettings::code() }}
                                </span>
                            </div>
                        </div>

                        @if ($periodicInvoice->details_subscription)
                            <p class="sl-help mt-3">
                                <strong>{{ __('sales_periodic.show.details') }}:</strong>
                                {{ $periodicInvoice->details_subscription }}
                            </p>
                        @endif

                        <div class="sl-tags mt-3">
                            <span class="badge badge-light-{{ $periodicInvoice->is_active ? 'success' : 'secondary' }}">
                                <i class="feather icon-{{ $periodicInvoice->is_active ? 'play-circle' : 'pause-circle' }}"></i>
                                {{ __('sales_periodic.state.' . ($periodicInvoice->is_active ? 'active' : 'paused')) }}
                            </span>

                            @if ($isTaxed)
                                <span class="badge badge-light-primary">
                                    <i class="feather icon-percent"></i> {{ __('sales_periodic.show.taxed') }}
                                </span>
                            @endif

                            @foreach ($flags as $flag => $enabled)
                                @if ($enabled)
                                    <span class="badge badge-light-info">
                                        <i class="feather icon-check"></i> {{ __('sales_periodic.show.' . $flag) }}
                                    </span>
                                @endif
                            @endforeach
                        </div>

                        <p class="sl-help mt-2">{{ __('sales_periodic.show.currency_frozen') }}</p>
                        <p class="sl-help">{{ __('sales_periodic.show.cron_hint') }}</p>
                    </div>

                    <div class="col-lg-5">
                        <div class="sl-totals">
                            <div class="sl-totals__row">
                                <span>{{ __('sales_documents.totals.subtotal') }}</span>
                                <span>{{ CurrencySettings::format($periodicInvoice->subtotal ?? 0) }}</span>
                            </div>
                            <div class="sl-totals__row">
                                <span>{{ __('sales_documents.totals.discount') }}</span>
                                <span>{{ CurrencySettings::format($periodicInvoice->total_discount ?? 0) }}</span>
                            </div>
                            <div class="sl-totals__row">
                                <span>{{ __('sales_periodic.totals.items_tax') }}</span>
                                <span>{{ CurrencySettings::format((float) $periodicInvoice->total_tax - (float) $periodicInvoice->shipping_tax) }}</span>
                            </div>
                            <div class="sl-totals__row">
                                <span>{{ __('sales_documents.totals.shipping') }}</span>
                                <span>{{ CurrencySettings::format($periodicInvoice->shipping_cost ?? 0) }}</span>
                            </div>
                            @if ((float) $periodicInvoice->shipping_tax > 0)
                                <div class="sl-totals__row">
                                    <span>{{ __('sales_periodic.totals.shipping_tax') }}</span>
                                    <span>{{ CurrencySettings::format($periodicInvoice->shipping_tax) }}</span>
                                </div>
                            @endif
                            <div class="sl-totals__row sl-totals__row--grand">
                                <span>{{ __('sales_documents.totals.grand_total') }}</span>
                                <span>{{ CurrencySettings::format($periodicInvoice->grand_total ?? 0) }}</span>
                            </div>
                            <div class="sl-totals__row">
                                <span>{{ __('sales_periodic.show.paid') }}</span>
                                <span>{{ CurrencySettings::format($periodicInvoice->paid_amount ?? 0) }}</span>
                            </div>
                            <div class="sl-totals__row">
                                <span class="{{ ($periodicInvoice->unpaid_amount ?? 0) > 0 ? 'text-danger' : '' }}">
                                    {{ __('sales_periodic.show.unpaid') }}
                                </span>
                                <span class="{{ ($periodicInvoice->unpaid_amount ?? 0) > 0 ? 'text-danger' : '' }}">
                                    {{ CurrencySettings::format($periodicInvoice->unpaid_amount ?? 0) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ شريط الإجراءات ══════════ --}}
            <div class="sl-doc-card__foot">
                <div class="sl-toolbar">
                    @permission('sales.periodic_invoices.edit')
                        <a href="{{ route('periodic_invoices.edit', $periodicInvoice->id) }}"
                           class="btn btn-outline-secondary">
                            <i class="feather icon-edit-2"></i> {{ __('sales_periodic.show.edit') }}
                        </a>

                        {{-- إدارةُ الاشتراك بعد إنشائه.

                             كانت الشاشة تعرض حالة «موقوف/مفعَّل» ولا سبيلَ
                             إلى تغييرها إلّا بفتح شاشة التعديل كلِّها — وهي
                             تحذف البنود وتعيد كتابتها في كلّ حفظ. ولا سبيل
                             إلى الإصدار إلّا سطرُ أمرٍ على الخادم، فمن أنشأ
                             اشتراكاً دفعتُه الأولى مستحقّةٌ اليوم ينتظر
                             الليل. --}}
                        @if ($periodicInvoice->is_active)
                            <form action="{{ route('periodic_invoices.generate', $periodicInvoice->id) }}"
                                  method="POST" id="generateForm" class="d-inline">
                                @csrf
                                <button type="button" class="btn btn-outline-primary"
                                        onclick="confirmPeriodicAction('generateForm', 'generate')">
                                    <i class="feather icon-zap"></i> {{ __('sales_periodic.actions.generate') }}
                                </button>
                            </form>
                        @endif

                        <form action="{{ route('periodic_invoices.toggle', $periodicInvoice->id) }}"
                              method="POST" id="toggleForm" class="d-inline">
                            @csrf
                            <button type="button"
                                    class="btn btn-outline-{{ $periodicInvoice->is_active ? 'warning' : 'success' }}"
                                    onclick="confirmPeriodicAction('toggleForm', '{{ $periodicInvoice->is_active ? 'pause' : 'resume' }}')">
                                <i class="feather icon-{{ $periodicInvoice->is_active ? 'pause' : 'play' }}"></i>
                                {{ __('sales_periodic.actions.' . ($periodicInvoice->is_active ? 'pause' : 'resume')) }}
                            </button>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>

        {{-- ══════════ التفاصيل ══════════ --}}
        <div class="sl-doc-card">
            <div class="sl-doc-card__head sl-doc-card__head--flush">
                <ul class="nav sl-doc-tabs w-100" role="tablist">
                    @php
                        $tabs = [
                            ['periodic-schedule', 'calendar', __('sales_periodic.show.tab_schedule'), true],
                            ['periodic-items', 'package', __('sales_periodic.show.tab_items'), false],
                            ['periodic-activity', 'clock', __('sales_periodic.show.tab_activity'), false],
                        ];
                    @endphp

                    @foreach ($tabs as [$tabId, $icon, $label, $active])
                        <li class="nav-item">
                            <a class="nav-link {{ $active ? 'active' : '' }}" data-bs-toggle="tab"
                               href="#{{ $tabId }}" role="tab">
                                <i class="feather icon-{{ $icon }}"></i> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="tab-content">

                {{-- جدول النسخ: ما صدر وما لم يحن موعده --}}
                <div class="tab-pane active" id="periodic-schedule" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:56px">{{ __('sales_periodic.show.schedule_number') }}</th>
                                    <th style="width:150px">{{ __('sales_periodic.show.schedule_issue') }}</th>
                                    <th style="width:150px">{{ __('sales_periodic.show.schedule_due') }}</th>
                                    <th style="width:150px">{{ __('sales_periodic.show.schedule_state') }}</th>
                                    <th>{{ __('sales_periodic.show.schedule_invoice') }}</th>
                                    <th class="num" style="width:150px">{{ __('sales_periodic.show.schedule_total') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($schedule as $row)
                                    @php
                                        $stateKey = $row->statusKey();
                                        $stateTone = ['issued' => 'success', 'failed' => 'danger', 'cancelled' => 'secondary', 'scheduled' => 'info'][$stateKey];
                                    @endphp
                                    <tr @class(['text-muted' => $stateKey === 'cancelled'])>
                                        <td class="num"><span class="sl-row__index">{{ $row->instance_number }}</span></td>
                                        <td class="sl-num">
                                            {{ $row->issue_date ? LanguageSettings::formatDate($row->issue_date) : __('sales_documents.ui.dash') }}
                                        </td>
                                        <td class="sl-num">
                                            {{ $row->due_date ? LanguageSettings::formatDate($row->due_date) : __('sales_documents.ui.dash') }}
                                        </td>
                                        <td>
                                            <span class="badge badge-light-{{ $stateTone }}">
                                                {{ __('sales_periodic.schedule_status.' . $stateKey) }}
                                            </span>

                                            {{-- سببُ ما لم يصدر — نقصُ مخزون، حدٌّ ائتماني، فترةٌ مقفلة --}}
                                            @if ($stateKey === 'failed' && $row->last_error)
                                                <span class="sl-media__sub d-block text-danger" style="white-space:normal">
                                                    {{ $row->last_error }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($row->invoice)
                                                <a href="{{ route('invoices.show', $row->invoice->id) }}" class="sl-code">
                                                    {{ $row->invoice->code }}
                                                </a>
                                            @else
                                                <span class="sl-media__sub">{{ __('sales_documents.ui.dash') }}</span>
                                            @endif
                                        </td>
                                        <td class="num sl-line-total">
                                            {{ $row->invoice
                                                ? CurrencySettings::format($row->invoice->grand_total)
                                                : CurrencySettings::format($periodicInvoice->grand_total ?? 0) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- البنود --}}
                <div class="tab-pane" id="periodic-items" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:46px">{{ __('sales_documents.items.row') }}</th>
                                    <th>{{ __('sales_documents.items.item') }}</th>
                                    <th class="num" style="width:110px">{{ __('sales_documents.items.quantity') }}</th>
                                    <th class="num" style="width:130px">{{ __('sales_documents.items.unit_price') }}</th>
                                    <th class="num" style="width:120px">{{ __('sales_documents.items.discount') }}</th>
                                    <th class="num" style="width:120px">{{ __('sales_documents.items.tax_1') }}</th>
                                    <th class="num" style="width:140px">{{ __('sales_documents.items.total') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($periodicInvoice->items as $index => $item)
                                    @php
                                        $lineSubtotal = $item->unit_price * $item->quantity;
                                        $lineTax = ($item->tax_1 ?? 0) > 0
                                            ? ($lineSubtotal - ($item->discount ?? 0)) * ($item->tax_1 / 100)
                                            : 0;
                                        $lineTotal = $lineSubtotal - ($item->discount ?? 0) + $lineTax;
                                    @endphp

                                    <tr>
                                        <td class="num"><span class="sl-row__index">{{ $index + 1 }}</span></td>
                                        <td>
                                            <span class="sl-media__title d-block">
                                                {{ $item->product->name ?? $item->item }}
                                                @if ($item->unit_name)
                                                    <span class="sl-media__sub">({{ $item->unit_name }})</span>
                                                @endif
                                            </span>
                                            @if ($item->description)
                                                <span class="sl-media__sub">{{ $item->description }}</span>
                                            @endif
                                        </td>
                                        <td class="num sl-num">{{ CurrencySettings::quantity($item->quantity) }}</td>
                                        <td class="num sl-num">{{ CurrencySettings::format($item->unit_price) }}</td>
                                        <td class="num sl-num">{{ CurrencySettings::format($item->discount ?? 0) }}</td>
                                        <td class="num sl-num">{{ CurrencySettings::format($lineTax) }}</td>
                                        <td class="num sl-line-total">{{ CurrencySettings::format($lineTotal) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            @include('sales::components.empty-state', [
                                                'icon' => 'package',
                                                'title' => __('sales_periodic.show.no_items'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- سجلّ النشاط --}}
                <div class="tab-pane" id="periodic-activity" role="tabpanel">
                    <div class="sl-doc-card__body">
                        @forelse ($logs as $date => $dayLogs)
                            <div class="sl-timeline">
                                <p class="sl-timeline__day">
                                    <i class="feather icon-calendar"></i>
                                    {{ \Carbon\Carbon::parse($date)->translatedFormat('l') }}
                                    <span class="sl-num">{{ $date }}</span>
                                </p>

                                @foreach ($dayLogs as $log)
                                    <div class="sl-timeline__item">
                                        <span class="sl-timeline__dot"></span>
                                        <div class="sl-timeline__body">
                                            <span class="sl-timeline__time sl-num">
                                                {{ $log->created_at->format('H:i') }}
                                            </span>
                                            <div class="sl-timeline__text">
                                                <strong>{{ $log->user->name ?? __('sales_periodic.show.unknown_user') }}</strong>
                                                {!! Str::markdown($log->description ?? '') !!}
                                                <span class="sl-media__sub">
                                                    {{ $log->user->branch->name ?? __('sales_periodic.show.no_branch') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            @include('sales::components.empty-state', [
                                'icon' => 'clock',
                                'title' => __('sales_periodic.show.no_activity'),
                            ])
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        /* الإيقاف والاستئناف والإصدار كلُّها تمسّ فواتيرَ بيعٍ فعليّة،
           فيُسأل عنها قبل الإرسال. النصوص من ملفّ الترجمة لا مكتوبةً في
           السكربت. */
        const PERIODIC_TEXT = {
            pause: {
                title: @json(__('sales_periodic.actions.pause_title')),
                text: @json(__('sales_periodic.actions.pause_text')),
            },
            resume: {
                title: @json(__('sales_periodic.actions.resume_title')),
                text: @json(__('sales_periodic.actions.resume_text')),
            },
            generate: {
                title: @json(__('sales_periodic.actions.generate_title')),
                text: @json(__('sales_periodic.actions.generate_text')),
            },
        };

        function confirmPeriodicAction(formId, action) {
            const form = document.getElementById(formId);
            if (!form) return;

            const copy = PERIODIC_TEXT[action];

            if (typeof Swal === 'undefined') {
                if (confirm(copy.text)) form.submit();
                return;
            }

            Swal.fire({
                title: copy.title,
                text: copy.text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: @json(__('sales_periodic.actions.confirm_yes')),
                cancelButtonText: @json(__('sales_periodic.actions.confirm_no')),
                reverseButtons: true,
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
        }
    </script>
@endsection
