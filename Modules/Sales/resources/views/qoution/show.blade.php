{{--
    عرض عرض السعر.

    أُعيد بناء الشاشة على قياس شاشة عرض الفاتورة
    (`sales::invoices.show`) بنظام `sl-doc-*`: ترويسة ← بطاقة هوية
    المستند وإجمالياته ← شريط إجراءات واحد ← تبويبات التفاصيل.

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات»
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • القوالب         ← «قوالب المبيعات» (النوع `quote`) في المطبوعات
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    ما أُصلح:
      • *`<style>` من ٢٧٠ سطراً* بألوان مثبَّتة لا تتبع «مظهر النظام»،
        وقد صار في نظام `sl-*`.
      • *الطباعة بكشط الـDOM*: كان زرّ الطباعة يقرأ محتوى `div` بمُحدِّد
        `div[style*="transform: scale(0.8);"]` وينسخ `document.head`
        كلَّه إلى نافذة جديدة — فتنكسر الطباعة بمجرّد تغيير سطر تنسيق.
        والقوالب المصمَّمة في «قوالب المستندات» لا تُستعمل رغم أنّ
        المتحكّم يمرّرها. صارت الطباعة عبر `questions.print` بقالبها.
      • *`convertToInvoice()` معرَّفةٌ مرّتين* بنفس الجسم حرفياً، ولا
        تُستدعى من شيء: زرّ التحويل نموذجٌ يُرسَل. الأولى تُظلّلها
        الثانية، وكلتاهما ميتة.
      • *زرّ PDF بـ`href="#"`* — لا يفعل شيئاً، والمسار `questions.pdf`
        موجود.
      • *رسالة واتساب تقول «فاتورتكم»* في شاشة عرض سعر، وبلا رابط أصلاً
        (النصّ يذكر «الرابط التالي» ثم لا يضع رابطاً).
--}}

@extends('sales::master')

@section('title', $doc['t']('doc.show_title', ['number' => $quote->quote_number ?: $quote->id]))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: `document-theme` لا تعرّفهما،
         وكلُّ قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $symbol = CurrencySettings::symbol();

        $statusTones = [
            'Draft' => ['secondary', 'edit-3'],
            'Pending' => ['warning', 'clock'],
            'Approved' => ['success', 'check-circle'],
            'Converted_to_Invoice' => ['info', 'file-text'],
            'Cancelled' => ['danger', 'x-circle'],
            'Expired' => ['secondary', 'clock'],
        ];

        [$stateTone, $stateIcon] = $statusTones[$quote->status] ?? ['dark', 'help-circle'];

        $clientName = $quote->client
            ? ($quote->client->trade_name ?: trim(($quote->client->first_name ?? '').' '.($quote->client->last_name ?? '')))
            : $doc['t']('ui.unknown');


        $printTemplates = (isset($templates) && $templates instanceof \Illuminate\Support\Collection)
            ? $templates
            : collect();

        $isConverted = $quote->status === 'Converted_to_Invoice';
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route($doc['routes']['index']) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ $doc['t']('ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => $doc['t']('doc.show_title', ['number' => $quote->quote_number ?: $quote->id]),
            'icon' => 'file-text',
            'sub' => $clientName,
            'crumbs' => [
                $doc['t']('ui.home') => route('dashboard'),
                $doc['t']('doc.title') => route($doc['routes']['index']),
                ($quote->quote_number ?: $quote->id) => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
@include('sales::components.guide', [
            'key' => 'sales-quotes.show',
            'title' => $doc['t']('guide.show.title'),
            'lead' => $doc['t']('guide.lead_show'),
            'open' => false,
            'blocks' => [
                $doc['t']('guide.conversion.title') => [
                    $doc['t']('guide.conversion.a'),
                    $doc['t']('guide.conversion.b'),
                    $doc['t']('guide.conversion.c'),
                    $doc['t']('guide.conversion.d'),
                ],
                $doc['t']('guide.money_flow.title') => [
                    $doc['t']('guide.money_flow.a'),
                    $doc['t']('guide.money_flow.b'),
                    $doc['t']('guide.money_flow.c'),
                ],
                $doc['t']('guide.noedit.title') => [
                    $doc['t']('guide.noedit.a'),
                    $doc['t']('guide.noedit.b'),
                ],
            ],
        ])

        {{-- ══════════ هوية المستند وإجمالياته ══════════ --}}
        <div class="sl-doc-card sl-doc-card--summary">
            <div class="sl-doc-card__body">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="sl-facts">
                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ $doc['t']('doc.number') }}</span>
                                <span class="sl-fact__value">
                                    <span class="sl-code">{{ $quote->quote_number ?: $quote->id }}</span>
                                </span>
                            </div>

                            <div class="sl-fact sl-fact--wide">
                                <span class="sl-fact__label">{{ $doc['t']('doc.client') }}</span>
                                <span class="sl-fact__value">{{ $clientName }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ $doc['t']('doc.date') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ LanguageSettings::formatDate($quote->quote_date ?: $quote->created_at) }}
                                </span>
                            </div>

                            {{-- أمر البيع بلا «تاريخ تسليم» — للعرض وحدَه «صالح حتى» --}}
                            @unless ($doc['is_sales_order'])
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ $doc['t']('doc.expiry_date') }}</span>
                                    <span class="sl-fact__value sl-num">
                                        {{ $quote->expiry_date
                                            ? LanguageSettings::formatDate($quote->expiry_date)
                                            : $doc['t']('ui.dash') }}
                                    </span>
                                </div>
                            @endunless

                            {{-- مسؤول المبيعات — من بَاع، لا من أنشأ الصفّ.
                                 السطران يفترقان: المندوبُ يبيع والإداريُّ
                                 يُدخل، ولذلك يُعرضان معاً. --}}
                            @if ($quote->salesPerson)
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ $doc['t']('fields.employee') }}</span>
                                    <span class="sl-fact__value">{{ $quote->salesPerson->full_name }}</span>
                                </div>
                            @endif

                            {{-- شروط الدفع — المهلةُ وتاريخُ استحقاقها.
                                 يُخفى السطرُ لمستندٍ نقديّ بلا مهلة. --}}
                            @php $quoteTerms = \App\Support\PaymentTerms::of($quote); @endphp

                            @if ($quoteTerms)
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('payment_terms.label') }}</span>
                                    <span class="sl-fact__value sl-num">{{ $quoteTerms->summary() }}</span>
                                </div>
                            @endif

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ $doc['t']('doc.created_by') }}</span>
                                <span class="sl-fact__value">{{ $quote->creator->name ?? $doc['t']('ui.dash') }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ $doc['t']('fields.currency') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ $quote->currency ?: CurrencySettings::code() }}
                                </span>
                            </div>
                        </div>

                        <div class="sl-tags mt-2">
                            <span class="badge badge-light-{{ $stateTone }}">
                                <i class="feather icon-{{ $stateIcon }}"></i>
                                {{ $doc['t']('status.'.$quote->status) }}
                            </span>

                            {{-- شارةُ الاستحقاق — إخبارٌ هنا لا إنذار: المستندُ
                                 لم يُحوَّل بعدُ فلا مستحقَّ قائماً، والشارةُ
                                 تقول متى يحلّ الأجلُ إن قُبِل. وتُخفى بعد
                                 التحويل: الفاتورةُ صارت هي مَن يحمل الأجل. --}}
                            @if ($quoteTerms && ! $isConverted)
                                <span class="badge badge-light-secondary" title="{{ $quoteTerms->summary() }}">
                                    <i class="feather icon-clock"></i>
                                    {{ __('payment_terms.state.scheduled', ['date' => $quoteTerms->formattedDueDate()]) }}
                                </span>
                            @endif

                            @if ($isConverted && $quote->invoice_id)
                                <a href="{{ route('invoices.show', $quote->invoice_id) }}"
                                   class="badge badge-light-info">
                                    <i class="feather icon-external-link"></i>
                                    {{ $doc['t']('doc.converted_to', [
                                        'code' => optional(\App\Models\Invoice::find($quote->invoice_id))->code ?? $quote->invoice_id,
                                    ]) }}
                                </a>
                            @endif
                        </div>

                        {{-- عملة المستند مثبَّتة عند الحفظ — يُقال هنا كي لا
                             يُظنّ أنّ تبديل العملة في الإعدادات يعيد تسعيره --}}
                        <p class="sl-help mt-2">{{ $doc['t']('notes.currency_frozen') }}</p>
                    </div>

                    <div class="col-lg-5">
                        {{-- الصندوق المشترك: يرسم السطر متى كان لقيمته وجود
                             ويسكت عنه متى كانت صفراً — نفس شرط شاشة الإنشاء
                             ونفس شرط المطبوعة --}}
                        @include('sales::components.totals', [
                            'totals' => [
                                'subtotal' => (float) ($quote->subtotal ?? 0),
                                // صافياً من الكوبون — `total_discount` يحملهما معاً
                                'discount' => (float) ($quote->total_discount ?? 0)
                                    - (float) ($quote->coupon_discount ?? 0),
                                'coupon' => (float) ($quote->coupon_discount ?? 0),
                                'shipping' => (float) ($quote->shipping_cost ?? 0),
                                'tax' => (float) ($quote->total_tax ?? 0),
                                'grand_total' => (float) ($quote->grand_total ?? 0),
                            ],
                            'totalsLabels' => [
                                'coupon' => __('sales_invoice.coupon.row', ['code' => $quote->coupon_code ?? '']),
                            ],
                        ])
                    </div>
                </div>
            </div>

            {{-- ══════════ شريط الإجراءات ══════════ --}}
            <div class="sl-doc-card__foot">
                <div class="sl-toolbar">
                    {{-- المطبوعات — القوالب المصمَّمة في «قوالب المبيعات» أوّلاً --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button"
                                data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="feather icon-printer"></i> {{ $doc['t']('notes.template_menu') }}
                        </button>

                        <div class="dropdown-menu">
                            @if ($doc['can']('print'))
                                @forelse ($printTemplates as $template)
                                    <a class="dropdown-item"
                                       href="{{ route($doc['routes']['print'], [$quote->id, $template->id]) }}"
                                       target="_blank" rel="noopener">
                                        <i class="feather icon-file"></i> {{ $template->name }}
                                        @if ($template->is_default)
                                            <i class="feather icon-star text-warning"></i>
                                        @endif
                                    </a>
                                @empty
                                    <h6 class="dropdown-header">{{ $doc['t']('notes.no_template') }}</h6>
                                @endforelse

                                @if ($printTemplates->isNotEmpty())
                                    <div class="dropdown-divider"></div>
                                @endif

                                <a class="dropdown-item" href="{{ route($doc['routes']['print'], $quote->id) }}"
                                   target="_blank" rel="noopener">
                                    <i class="feather icon-printer"></i> {{ $doc['t']('ui.print') }}
                                </a>

                                {{-- كان زرّ PDF بـ`href="#"` والمسار موجود --}}
                                <a class="dropdown-item" href="{{ route($doc['routes']['pdf'], $quote->id) }}">
                                    <i class="feather icon-download"></i> {{ $doc['t']('ui.pdf') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    @if ($doc['can']('edit'))
                        @unless ($isConverted)
                            <a href="{{ route($doc['routes']['edit'], $quote->id) }}" class="btn btn-outline-secondary">
                                <i class="feather icon-edit-2"></i> {{ $doc['t']('ui.edit') }}
                            </a>
                        @endunless
                    @endif

                    {{-- كان زرٌّ واحدٌ اسمُه «إرسال بالبريد» وتحته `wa.me`.
                         صارا اثنين، وكلٌّ يفعل ما يقوله اسمُه --}}
                    @if (! empty($quote->client?->phone))
                        <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                           href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $quote->client->phone) }}?text={{ urlencode(
                               $doc['t']('doc.whatsapp_body', [
                                   'client' => $clientName,
                                   'number' => $quote->quote_number ?: $quote->id,
                                   'link' => route($doc['routes']['print'], $quote->id),
                                   'business' => \App\Support\BusinessInfo::name(),
                               ]),
                           ) }}">
                            <i class="feather icon-message-circle text-success"></i> {{ __('sales_documents.ui.whatsapp') }}
                        </a>
                    @endif

                    @if ($doc['can']('send_link'))
                        @include('sales::partials.send-email', [
                            'mailId' => 'quote-' . $quote->id,
                            'mailRoute' => route($doc['routes']['email'], $quote->id),
                            'mailNumber' => $quote->quote_number ?: $quote->id,
                            'mailEmail' => $quote->client?->email,
                            'mailClientName' => $clientName,
                            'mailTemplates' => $printTemplates,
                            'mailDefaultTemplate' => $defaultTemplate,
                        ])
                    @endif

                    {{-- التحويل إلى فاتورة: نموذجٌ يُرسَل بعد تأكيد.
                         والمنتهي لا زرَّ له — الخادم يردّه، وإظهار زرٍّ
                         يُردّ عند ضغطه إخفاءٌ للسبب لا إتاحةٌ للفعل --}}
                    @unless ($isConverted)
                        @if ($doc['can']('convert'))
                            @if ($quote->isExpired())
                                <span class="sl-help sl-toolbar--end">
                                    <i class="feather icon-clock"></i>
                                    {{ $doc['t']('notes.expired_no_convert') }}
                                </span>
                            @else
                                <form action="{{ route($doc['routes']['convert'], $quote->id) }}" method="POST"
                                      id="convertForm" class="sl-toolbar sl-toolbar--end">
                                    @csrf
                                    <button type="submit" class="btn btn-primary" id="convertBtn">
                                        <i class="feather icon-file-plus"></i> {{ $doc['t']('doc.convert') }}
                                    </button>
                                </form>
                            @endif
                        @endif
                    @endunless
                </div>
            </div>
        </div>

        {{-- ══════════ التفاصيل ══════════ --}}
        <div class="sl-doc-card">
            <div class="sl-doc-card__head sl-doc-card__head--flush">
                <ul class="nav sl-doc-tabs w-100" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#quote-preview" role="tab">
                            <i class="feather icon-eye"></i> {{ $doc['t']('ui.view') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#quote-items" role="tab">
                            <i class="feather icon-package"></i> {{ $doc['t']('sections.items') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#quote-activity" role="tab">
                            <i class="feather icon-clock"></i> {{ $doc['t']('log.timeline') }}
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content">
                {{-- معاينة المستند: نفس مسار الطباعة داخل إطار — لا نسخةٌ
                     ثانية من القالب مصغَّرةً بـ`transform: scale(0.8)` --}}
                <div class="tab-pane active" id="quote-preview" role="tabpanel">
                    <iframe src="{{ route($doc['routes']['print'], [$quote->id, 'embed' => true]) }}"
                            class="sl-doc-frame" frameborder="0"
                            title="{{ $doc['t']('ui.view') }}"></iframe>
                </div>

                {{-- البنود --}}
                <div class="tab-pane" id="quote-items" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:46px">{{ $doc['t']('items.row') }}</th>
                                    <th>{{ $doc['t']('items.item') }}</th>
                                    <th class="num" style="width:110px">{{ $doc['t']('items.quantity') }}</th>
                                    <th class="num" style="width:130px">{{ $doc['t']('items.unit_price') }}</th>
                                    <th class="num" style="width:120px">{{ $doc['t']('items.discount') }}</th>
                                    <th class="num" style="width:120px">{{ $doc['t']('items.tax_1') }}</th>
                                    <th class="num" style="width:140px">{{ $doc['t']('items.total') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($quote->items as $index => $item)
                                    @php
                                        $lineSubtotal = $item->unit_price * $item->quantity;
                                        $lineTax = ($item->tax_1 ?? 0) > 0
                                            ? ($lineSubtotal - ($item->discount ?? 0)) * ($item->tax_1 / 100)
                                            : 0;
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
                                        <td class="num sl-line-total">
                                            {{ CurrencySettings::format($item->total ?? ($lineSubtotal - ($item->discount ?? 0) + $lineTax)) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            @include('sales::components.empty-state', [
                                                'icon' => 'package',
                                                'title' => $doc['t']('empty.items_title'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            @if ($quote->items->isNotEmpty())
                                <tfoot>
                                    <tr>
                                        <td colspan="6" class="text-end">{{ $doc['t']('totals.grand_total') }}</td>
                                        <td class="num sl-line-total">
                                            {{ CurrencySettings::format($quote->grand_total ?? 0) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- سجلّ النشاط --}}
                <div class="tab-pane" id="quote-activity" role="tabpanel">
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
                                                <strong>{{ $log->user->name ?? $doc['t']('ui.unknown') }}</strong>
                                                {!! Str::markdown($log->description ?? '') !!}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            @include('sales::components.empty-state', [
                                'icon' => 'clock',
                                'title' => $doc['t']('log.no_events'),
                            ])
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        /* التحويل إلى فاتورة يُسأل عنه قبل تنفيذه: يُنشئ فاتورة ويخصم
           المخزون ويُرحّل قيداً — ولا رجعة فيه. */
        document.getElementById('convertForm')?.addEventListener('submit', function (event) {
            if (this.dataset.confirmed === 'yes') return;

            event.preventDefault();
            const form = this;

            Swal.fire({
                icon: 'question',
                title: @json($doc['t']('confirm.convert_title', ['number' => $quote->quote_number ?: $quote->id])),
                text: @json($doc['t']('confirm.convert_text')),
                showCancelButton: true,
                confirmButtonText: @json($doc['t']('ui.yes')),
                cancelButtonText: @json($doc['t']('ui.cancel')),
                reverseButtons: true,
            }).then(function (result) {
                if (!result.isConfirmed) return;

                form.dataset.confirmed = 'yes';
                document.getElementById('convertBtn').disabled = true;
                form.submit();
            });
        });
    </script>
@endsection
