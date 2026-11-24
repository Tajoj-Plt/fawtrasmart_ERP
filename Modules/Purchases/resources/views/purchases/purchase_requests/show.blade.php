@extends('purchases::master')

@section('title')
    {{ __('purchase_orders.titles.show') }} — {{ $purchaseOrder->code }}
@stop

@section('css')
    {{-- ورقة الخطّ الزمني الأصلية التي تُصمِّم .timeline و.timeline-item --}}
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
    @include('purchases::purchases.purchase_requests.partials.style')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;
        use Modules\Purchases\Support\OrderLine;

        $isReview = $purchaseOrder->status === 'Under Review';
        $isApproved = $purchaseOrder->status === 'approval';
        $isRejected = $purchaseOrder->status === 'disagree';

        // القوالب المدمجة تظهر فقط حين لا يُصمَّم قالب في الإعدادات لهذا النوع،
        // فلا تزدحم القائمة بنسختين من الشيء نفسه.
        $builtins = ['default', 'detailed', 'simple', 'compact', 'summary'];
    @endphp

    <div class="po-page">
        <div class="po-head">
            <div class="po-head__titles">
                <h2>{{ __('purchase_orders.titles.show') }} — {{ $purchaseOrder->code }}</h2>
                <nav class="po-head__crumbs" aria-label="breadcrumb">
                    <a href="{{ route('purchases.dashboard') }}">{{ __('purchase_orders.breadcrumb.home') }}</a>
                    <span class="sep">/</span>
                    <a href="{{ route('PurchaseRequests.index') }}">{{ __('purchase_orders.breadcrumb.index') }}</a>
                    <span class="sep">/</span>
                    <span>{{ $purchaseOrder->code }}</span>
                </nav>
            </div>

            <div class="po-head__actions">
                @include('purchases::purchases.purchase_requests.partials.status', ['status' => $purchaseOrder->status])
            </div>
        </div>

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً هنا وحدها: من يفتح طلباً
             بعينه جاء لبياناته لا للشرح، بخلاف شاشتي الفهرسة والإنشاء --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-orders.show',
            'title' => __('purchase_orders.guide.show.title'),
            'lead' => __('purchase_orders.guide.show.lead'),
            'open' => false,
            'blocks' => [
                __('purchase_orders.guide.show.actions.title') => [
                    __('purchase_orders.guide.show.actions.a'),
                    __('purchase_orders.guide.show.actions.b'),
                    __('purchase_orders.guide.show.actions.c'),
                ],
                __('purchase_orders.guide.statuses.title') => [
                    __('purchase_orders.guide.statuses.a'),
                    __('purchase_orders.guide.statuses.b'),
                    __('purchase_orders.guide.statuses.c'),
                ],
                __('purchase_orders.guide.form.noedit.title') => [
                    __('purchase_orders.guide.form.noedit.a'),
                    __('purchase_orders.guide.form.noedit.b'),
                ],
            ],
            'facts' => [
                __('purchase_orders.guide.facts.no_stock'),
                __('purchase_orders.guide.facts.no_ledger'),
                __('purchase_orders.guide.facts.final'),
            ],
        ])

        {{-- شريط الإجراءات --}}
        <div class="po-card">
            <div class="po-card__body">
                <div class="po-actions-grid">
                    @permission('purchases.orders.edit')
                        @if ($isReview)
                            <a href="{{ route('PurchaseRequests.edit', $purchaseOrder->id) }}" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i><span>{{ __('purchase_orders.actions.edit') }}</span>
                            </a>
                        @endif
                    @endpermission

                    {{-- المطبوعات: القوالب المصمَّمة في الإعدادات أولاً --}}
                    @permission('purchases.orders.export')
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <i class="fas fa-print"></i><span>{{ __('purchase_orders.actions.print') }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end po-print-menu">
                            @if ($documentTemplates->isNotEmpty())
                                <li>
                                    <h6 class="dropdown-header">{{ __('purchase_orders.templates.from_settings') }}</h6>
                                </li>
                                @foreach ($documentTemplates as $template)
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center justify-content-between gap-2"
                                            href="{{ $prefersPdf ? route('PurchaseRequests.pdf', [$purchaseOrder->id, $template->id]) : route('PurchaseRequests.print', [$purchaseOrder->id, $template->id]) }}"
                                            target="_blank" rel="noopener">
                                            <span>
                                                <i class="fas fa-file-alt me-2 text-primary"></i>{{ $template->name }}
                                            </span>
                                            @if ($template->is_default)
                                                <span class="po-status po-status--approved">
                                                    <i class="fas fa-star"></i>
                                                </span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                            @endif

                            {{-- القوالب المدمجة احتياطٌ فقط: تظهر حين لا يكون في
                                 الإعدادات قالب واحد لهذا النوع --}}
                            @if ($documentTemplates->isEmpty())
                                @foreach ($builtins as $builtin)
                                    <li>
                                        <a class="dropdown-item"
                                            href="{{ route('PurchaseRequests.pdf', [$purchaseOrder->id, $builtin]) }}">
                                            <i class="fas fa-file-pdf me-2 text-danger"></i>{{ __('purchase_orders.templates.'.$builtin) }}
                                        </a>
                                    </li>
                                @endforeach

                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                            @endif

                            <li>
                                {{-- المعاينة والتنزيل يستعملان القالب الافتراضي المضبوط في
                                     «قوالب المستندات»، وإلا القالب المدمج --}}
                                <a class="dropdown-item"
                                    href="{{ route('PurchaseRequests.print', [$purchaseOrder->id, $defaultTemplate?->id ?? 'default']) }}"
                                    target="_blank" rel="noopener">
                                    <i class="fas fa-eye me-2 text-info"></i>{{ __('purchase_orders.actions.print_preview') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="{{ route('PurchaseRequests.pdf', [$purchaseOrder->id, $defaultTemplate?->id ?? 'default']) }}"
                                    target="_blank" rel="noopener">
                                    <i class="fas fa-file-pdf me-2 text-danger"></i>{{ __('purchase_orders.actions.download_pdf') }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endpermission

                    @permission('purchases.orders.approve_reject')
                        @if ($isReview)
                            <button type="button" class="btn btn-outline-success" id="approveBtn">
                                <i class="fas fa-check"></i><span>{{ __('purchase_orders.actions.approve') }}</span>
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="rejectBtn">
                                <i class="fas fa-times"></i><span>{{ __('purchase_orders.actions.reject') }}</span>
                            </button>
                        @elseif ($isRejected)
                            <button type="button" class="btn btn-outline-secondary" id="undoRejectionBtn">
                                <i class="fas fa-undo-alt"></i><span>{{ __('purchase_orders.actions.undo_rejection') }}</span>
                            </button>
                        @endif
                    @endpermission

                    @if ($isApproved)
                        <a href="{{ route('Quotations.create') }}?order_id={{ $purchaseOrder->id }}"
                            class="btn btn-outline-info">
                            <i class="fas fa-file-signature"></i><span>{{ __('purchase_orders.actions.to_quotation') }}</span>
                        </a>
                    @endif

                    @permission('purchases.orders.delete')
                        <button type="button" class="btn btn-outline-danger" id="deleteBtn">
                            <i class="fas fa-trash-alt"></i><span>{{ __('purchase_orders.actions.delete') }}</span>
                        </button>
                    @endpermission
                </div>
            </div>
        </div>

        {{-- التبويبات --}}
        <div class="po-card">
            <ul class="nav po-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#details" role="tab">
                        <i class="fas fa-info-circle me-1"></i>{{ __('purchase_orders.tabs.details') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#items" role="tab">
                        <i class="fas fa-boxes me-1"></i>{{ __('purchase_orders.tabs.items') }}
                        <span class="po-status po-status--quotation ms-1">{{ LanguageSettings::formatNumber($purchaseOrder->items->count(), 0) }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#activity" role="tab">
                        <i class="fas fa-history me-1"></i>{{ __('purchase_orders.tabs.activity') }}
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                {{-- التفاصيل --}}
                <div class="tab-pane active" id="details" role="tabpanel">
                    <div class="po-card__body">
                        <div class="po-details">
                            @php
                                $details = [
                                    ['icon' => 'fa-hashtag', 'label' => __('purchase_orders.fields.code'), 'value' => $purchaseOrder->code],
                                    ['icon' => 'fa-heading', 'label' => __('purchase_orders.fields.title'), 'value' => $purchaseOrder->title],
                                    ['icon' => 'fa-calendar', 'label' => __('purchase_orders.fields.order_date'), 'value' => LanguageSettings::formatDate($purchaseOrder->order_date)],
                                    ['icon' => 'fa-calendar-alt', 'label' => __('purchase_orders.fields.due_date'), 'value' => LanguageSettings::formatDate($purchaseOrder->due_date)],
                                    ['icon' => 'fa-user', 'label' => __('purchase_orders.fields.creator'), 'value' => $purchaseOrder->creator->name ?? null],
                                    ['icon' => 'fa-map-marker-alt', 'label' => __('purchase_orders.fields.branch'), 'value' => \App\Models\Branch::pathLabelFor($purchaseOrder->branch_id)],
                                    ['icon' => 'fa-box', 'label' => __('purchase_orders.fields.items_count'), 'value' => LanguageSettings::formatNumber($purchaseOrder->items->count(), 0)],
                                    ['icon' => 'fa-coins', 'label' => __('purchase_orders.fields.currency'), 'value' => CurrencySettings::name().' ('.CurrencySettings::symbol().')'],
                                    ['icon' => 'fa-clock', 'label' => __('purchase_orders.fields.updated_at'), 'value' => LanguageSettings::formatDate($purchaseOrder->updated_at, true)],
                                ];
                            @endphp

                            @foreach ($details as $detail)
                                <div>
                                    <div class="po-detail__label">
                                        <i class="fas {{ $detail['icon'] }}"></i>{{ $detail['label'] }}
                                    </div>
                                    <div class="po-detail__value">
                                        {{ filled($detail['value']) ? $detail['value'] : __('purchase_orders.empty.unknown') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-lg-8">
                                <div class="po-detail__label">
                                    <i class="fas fa-sticky-note"></i>{{ __('purchase_orders.fields.notes') }}
                                </div>
                                <div class="po-detail__value">
                                    {{ $purchaseOrder->notes ?: __('purchase_orders.empty.no_notes') }}
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="po-detail__label">
                                    <i class="fas fa-paperclip"></i>{{ __('purchase_orders.fields.attachments') }}
                                </div>
                                @if ($purchaseOrder->attachments)
                                    <a href="{{ asset('storage/'.$purchaseOrder->attachments) }}" target="_blank"
                                        rel="noopener" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-download me-1"></i>{{ __('purchase_orders.actions.open_attachment') }}
                                    </a>
                                @else
                                    <div class="po-detail__value">{{ __('purchase_orders.empty.no_attachments') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- البنود --}}
                <div class="tab-pane" id="items" role="tabpanel">
                    @if ($purchaseOrder->items->isEmpty())
                        <div class="po-empty">
                            <i class="fas fa-box-open"></i>
                            <h5>{{ __('purchase_orders.items.empty') }}</h5>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 46px;">{{ __('purchase_orders.items.row') }}</th>
                                        <th>{{ __('purchase_orders.items.item') }}</th>
                                        <th>{{ __('purchase_orders.items.description') }}</th>
                                        <th class="num">{{ __('purchase_orders.items.quantity') }}</th>
                                        <th>{{ __('purchase_orders.items.unit') }}</th>
                                        <th class="num">{{ __('purchase_orders.items.unit_price') }}</th>
                                        <th class="num">{{ __('purchase_orders.items.discount') }}</th>
                                        <th class="num">{{ __('purchase_orders.items.tax') }}</th>
                                        <th class="num">{{ __('purchase_orders.items.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseOrder->items as $index => $item)
                                        <tr>
                                            <td class="num" data-label="{{ __('purchase_orders.items.row') }}">
                                                {{ LanguageSettings::formatNumber($index + 1, 0) }}
                                            </td>
                                            <td data-label="{{ __('purchase_orders.items.item') }}">
                                                {{ $item->item ?: ($item->product->name ?? '—') }}
                                            </td>
                                            <td data-label="{{ __('purchase_orders.items.description') }}">
                                                {{ $item->description ?: '—' }}
                                            </td>
                                            <td class="num" data-label="{{ __('purchase_orders.items.quantity') }}">
                                                {{ LanguageSettings::formatNumber($item->quantity, 0) }}
                                            </td>
                                            <td data-label="{{ __('purchase_orders.items.unit') }}">
                                                @if($item->unit_id)
                                                    {{ \App\Models\ProductUnit::find($item->unit_id)?->name ?? '—' }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="num" data-label="{{ __('purchase_orders.items.unit_price') }}">
                                                {{ CurrencySettings::number($item->unit_price) }}
                                            </td>
                                            <td class="num" data-label="{{ __('purchase_orders.items.discount') }}">
                                                {{ CurrencySettings::number($item->discount) }}
                                                <span class="po-hint">({{ $item->discount_type == 2 ? '%' : CurrencySettings::symbol() }})</span>
                                            </td>
                                            {{-- المحفوظ نسبة، والمبلغ يُشتقّ — تُعرض النسبة والمبلغ معاً --}}
                                            <td class="num" data-label="{{ __('purchase_orders.items.tax') }}">
                                                {{ CurrencySettings::number(OrderLine::tax($item)) }}
                                                <span class="po-hint">({{ OrderLine::taxRate($item) + 0 }}%)</span>
                                            </td>
                                            <td class="num" data-label="{{ __('purchase_orders.items.total') }}">
                                                {{ CurrencySettings::format(OrderLine::total($item)) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- الصندوق نفسه الذي تعرضه بقيّة مستندات الدورة —
                             نسخته المشتركة تُخفي السطر الذي لا قيمة له --}}
                        @include('purchases::partials.totals', ['totals' => $totals])
                    @endif
                </div>

                {{-- سجل النشاطات — التصميم الأصلي بأصناف assets/css/logs.css --}}
                <div class="tab-pane" id="activity" role="tabpanel">
                    <div class="row mt-4">
                        <div class="col-12">
                            @if ($logs && count($logs) > 0)
                                @php
                                    $previousDate = null;
                                @endphp

                                @foreach ($logs as $date => $dayLogs)
                                    @php
                                        $currentDate = \Illuminate\Support\Carbon::parse($date);
                                        $diffInDays = $previousDate ? $previousDate->diffInDays($currentDate) : 0;
                                    @endphp

                                    @if ($diffInDays > 7)
                                        <div class="timeline-date">
                                            {{-- صيغة التاريخ من إعدادات اللغة لا ثابتة في القالب --}}
                                            <h4>{{ LanguageSettings::formatDate($currentDate) }}</h4>
                                        </div>
                                    @endif

                                    <div class="timeline-day">{{ $currentDate->translatedFormat('l') }}</div>

                                    <ul class="timeline">
                                        @foreach ($dayLogs as $log)
                                            @if ($log)
                                                <li class="timeline-item">
                                                    <div class="timeline-content">
                                                        <div class="time">
                                                            <i class="far fa-clock"></i>
                                                            {{ $log->created_at->timezone(LanguageSettings::timezone())->format(LanguageSettings::timeFormat()) }}
                                                        </div>
                                                        <div>
                                                            <strong>{{ $log->user->name ?? __('purchase_orders.empty.unknown_user') }}</strong>
                                                            {!! \Illuminate\Support\Str::markdown($log->description ?? '') !!}
                                                            <div class="text-muted">
                                                                {{ $log->user->branch->name ?? __('purchase_orders.empty.unknown_branch') }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>

                                    @php
                                        $previousDate = $currentDate;
                                    @endphp
                                @endforeach
                            @else
                                <div class="alert alert-info text-center" role="alert">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <p class="mb-0">{{ __('purchase_orders.empty.no_activity') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- نماذج العمليات — تُرسَل من أزرار شريط الإجراءات --}}
    <form id="approveForm" action="{{ route('PurchaseRequests.approve', $purchaseOrder->id) }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" id="approveNote" name="note">
    </form>
    <form id="rejectForm" action="{{ route('PurchaseRequests.reject', $purchaseOrder->id) }}" method="POST" class="d-none">
        @csrf
    </form>
    {{-- إلغاء الاعتماد أُزيل عمداً: الاعتماد صار نهائياً، بلا زرّ ولا نموذج ولا مسار --}}
    <form id="undoRejectionForm" action="{{ route('PurchaseRequests.undoRejection', $purchaseOrder->id) }}" method="POST" class="d-none">
        @csrf
    </form>
    <form id="deleteForm" action="{{ route('PurchaseRequests.destroy', $purchaseOrder->id) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@section('scripts')
    @php
        // `@json` تقصّ المصفوفة الحرفية متعدّدة الأسطر عند أول فاصلة،
        // فتُبنى هنا وتُمرَّر متغيّراً واحداً.
        $poDialogs = [
            'approveTitle' => __('purchase_orders.confirm.approve_title'),
            'approveText' => __('purchase_orders.confirm.approve_text'),
            'approveNote' => __('purchase_orders.fields.approval_note'),
            'rejectTitle' => __('purchase_orders.confirm.reject_title'),
            'rejectText' => __('purchase_orders.confirm.reject_text'),
            'undoRejectionTitle' => __('purchase_orders.actions.undo_rejection'),
            'undoRejectionText' => __('purchase_orders.confirm.undo_rejection_text'),
            'deleteTitle' => __('purchase_orders.confirm.delete_title'),
            'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => $purchaseOrder->code]),
            'yes' => __('purchase_orders.confirm.yes'),
            'yesDelete' => __('purchase_orders.confirm.yes_delete'),
            'no' => __('purchase_orders.confirm.no'),
        ];
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // كل النصوص من ملفّ الترجمة، فالحوارات تتبع لغة الواجهة
            const i18n = @json($poDialogs);

            const hasSwal = typeof window.Swal !== 'undefined';

            function run(button, formId, title, text, icon, confirmText, withNote) {
                const trigger = document.getElementById(button);

                if (!trigger) {
                    return;
                }

                trigger.addEventListener('click', function () {
                    const submit = function (note) {
                        if (withNote) {
                            document.getElementById('approveNote').value = note || '';
                        }
                        document.getElementById(formId).submit();
                    };

                    if (!hasSwal) {
                        if (window.confirm(text)) {
                            submit('');
                        }
                        return;
                    }

                    window.Swal.fire({
                        title: title,
                        text: text,
                        icon: icon,
                        showCancelButton: true,
                        confirmButtonText: confirmText,
                        cancelButtonText: i18n.no,
                        input: withNote ? 'textarea' : undefined,
                        inputPlaceholder: withNote ? i18n.approveNote : undefined,
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            submit(result.value);
                        }
                    });
                });
            }

            run('approveBtn', 'approveForm', i18n.approveTitle, i18n.approveText, 'question', i18n.yes, true);
            run('rejectBtn', 'rejectForm', i18n.rejectTitle, i18n.rejectText, 'warning', i18n.yes, false);
            run('undoRejectionBtn', 'undoRejectionForm', i18n.undoRejectionTitle, i18n.undoRejectionText, 'question', i18n.yes, false);
            run('deleteBtn', 'deleteForm', i18n.deleteTitle, i18n.deleteText, 'error', i18n.yesDelete, false);
        });
    </script>
@endsection
