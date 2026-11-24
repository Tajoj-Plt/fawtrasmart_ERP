@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.quotation_request') }} — {{ $purchaseQuotation->code }}
@stop

@section('css')
    {{-- ورقة الخطّ الزمني التي تُصمِّم .timeline و.timeline-item --}}
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
    @include('purchases::partials.style')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        use App\Support\LanguageSettings;

        $isReview = $purchaseQuotation->status === 'Under Review';
        $isApproved = $purchaseQuotation->status === 'approval';
        $isRejected = $purchaseQuotation->status === 'disagree';
        $items = $productDetails;
    @endphp

    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => __('purchase_cycle.documents.quotation_request') . ' — ' . $purchaseQuotation->code,
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.quotation_request'), 'url' => route('Quotations.index')],
                ['label' => $purchaseQuotation->code],
            ],
            'action' => view('purchases::partials.status', ['status' => $purchaseQuotation->status])->render(),
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً هنا وحدها --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-quotation-requests.show',
            'title' => __('purchase_cycle.guide.quotation_request.show.title'),
            'lead' => __('purchase_cycle.guide.quotation_request.show.lead'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.quotation_request.show.actions.title') => [
                    __('purchase_cycle.guide.quotation_request.show.actions.a'),
                    __('purchase_cycle.guide.quotation_request.show.actions.b'),
                    __('purchase_cycle.guide.quotation_request.show.actions.c'),
                ],
                __('purchase_cycle.guide.quotation_request.statuses.title') => [
                    __('purchase_cycle.guide.quotation_request.statuses.a'),
                    __('purchase_cycle.guide.quotation_request.statuses.b'),
                    __('purchase_cycle.guide.quotation_request.statuses.c'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.quotation_request.facts.no_prices'),
                __('purchase_cycle.guide.quotation_request.facts.no_ledger'),
                __('purchase_cycle.guide.quotation_request.facts.final'),
            ],
        ])

        {{-- شريط الإجراءات --}}
        <div class="po-card">
            <div class="po-card__body">
                <div class="po-actions-grid">
                    @if ($isReview)
                        @permission('purchases.quotations.edit')
                            <a href="{{ route('Quotations.edit', $purchaseQuotation->id) }}" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i><span>{{ __('purchase_cycle.actions.edit') }}</span>
                            </a>
                        @endpermission
                    @endif

                    @permission('purchases.quotations.duplicate')
                        <a href="{{ route('Quotations.duplicate', $purchaseQuotation->id) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-copy"></i><span>{{ __('purchase_cycle.actions.duplicate') }}</span>
                        </a>
                    @endpermission

                    @permission('purchases.quotations.export')
                        @include('purchases::partials.print-menu', [
                            'templates' => $documentTemplates,
                            'default' => $defaultTemplate,
                            'prefersPdf' => $prefersPdf,
                            'printRoute' => 'Quotations.print',
                            'pdfRoute' => 'Quotations.pdf',
                            'documentId' => $purchaseQuotation->id,
                        ])

                        {{-- الطباعةُ العامّة أعلاه تُخرج نسخةَ كلّ مورّد في
                             ملفٍّ واحد بفواصل صفحات — تصلح للأرشفة وللطباعة
                             دفعةً واحدة. وهذه القائمةُ لمن يريد إرسالَ نسخةِ
                             مورّدٍ بعينه إليه وحدَه: ورقةٌ واحدة معنونةٌ باسمه
                             بلا ذكرِ من سواه. --}}
                        @if ($purchaseQuotation->suppliers->count() > 1)
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                    id="supplierCopyMenu" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-tag"></i><span>طباعة نسخة مورّد</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="supplierCopyMenu">
                                    <h6 class="dropdown-header">نسخةٌ لكلّ مورّد على حدة</h6>
                                    @foreach ($purchaseQuotation->suppliers as $supplier)
                                        <a class="dropdown-item"
                                            href="{{ route('Quotations.print', ['id' => $purchaseQuotation->id, 'template' => $defaultTemplate?->id ?? 'default', 'supplier' => $supplier->id]) }}"
                                            target="_blank" rel="noopener">
                                            <i class="fas fa-print me-2 text-primary"></i>{{ $supplier->trade_name ?: trim($supplier->first_name.' '.$supplier->last_name) }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endpermission

                    @if ($isReview)
                        @permission('purchases.quotations.approve_reject')
                            <button type="button" class="btn btn-outline-success" id="approveBtn">
                                <i class="fas fa-check"></i><span>{{ __('purchase_orders.actions.approve') }}</span>
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="rejectBtn">
                                <i class="fas fa-times"></i><span>{{ __('purchase_orders.actions.reject') }}</span>
                            </button>
                        @endpermission
                    @elseif ($isApproved)
                        {{-- الاعتماد نهائي: لا زرّ إلغاء اعتماد بعد الآن —
                             فقط التحويل إلى عرض سعر.

                             والزرّ صار قائمةً بموردي الطلب: عرضُ السعر مورّدٌ
                             واحد بالضرورة، فطلبٌ أُرسل إلى خمسةٍ يقابله خمسةُ
                             عروض تُقارَن. وكان زرّاً واحداً لا يقول ذلك، فمن
                             اختار خمسةً يظنّ أنّ التحويل أضاعهم. --}}
                        @permission('purchases.price_quotations.create')
                            @php
                                $quotedSupplierIds = $purchaseQuotation->generatedQuotations
                                    ->pluck('supplier_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();
                            @endphp

                            @if ($purchaseQuotation->suppliers->count() > 1)
                                <div class="dropdown">
                                    <button type="button" class="btn btn-outline-info dropdown-toggle"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-tags"></i><span>{{ __('purchase_cycle.sections.purchase_quotation') }}</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <h6 class="dropdown-header">سجِّل عرضَ كلِّ مورّد على حدة</h6>
                                        @foreach ($purchaseQuotation->suppliers as $supplier)
                                            @php $isQuoted = in_array((int) $supplier->id, $quotedSupplierIds, true); @endphp
                                            <a class="dropdown-item d-flex align-items-center gap-2"
                                                href="{{ route('ViewPurchasePrice.create') }}?quotation_id={{ $purchaseQuotation->id }}&supplier_id={{ $supplier->id }}">
                                                <i class="fas fa-{{ $isQuoted ? 'check text-success' : 'plus' }}"></i>
                                                <span>{{ $supplier->name ?? $supplier->trade_name }}</span>
                                                @if ($isQuoted)
                                                    <small class="ms-auto text-muted">عرضُه مسجَّل</small>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <a href="{{ route('ViewPurchasePrice.create') }}?quotation_id={{ $purchaseQuotation->id }}"
                                    class="btn btn-outline-info">
                                    <i class="fas fa-tags"></i><span>{{ __('purchase_cycle.sections.purchase_quotation') }}</span>
                                </a>
                            @endif
                        @endpermission
                    @endif

                    @permission('purchases.quotations.delete')
                        <button type="button" class="btn btn-outline-danger" id="deleteBtn">
                            <i class="fas fa-trash-alt"></i><span>{{ __('purchase_cycle.actions.delete') }}</span>
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
                        <span class="po-status po-status--quotation ms-1">
                            {{ LanguageSettings::formatNumber($items->count(), 0) }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#activity" role="tab">
                        <i class="fas fa-history me-1"></i>{{ __('purchase_orders.tabs.activity') }}
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane active" id="details" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.details', [
                            'details' => [
                                ['icon' => 'fa-hashtag', 'label' => __('purchase_cycle.fields.code'), 'value' => $purchaseQuotation->code],
                                ['icon' => 'fa-calendar', 'label' => __('purchase_cycle.fields.date'), 'value' => LanguageSettings::formatDate($purchaseQuotation->order_date)],
                                ['icon' => 'fa-calendar-alt', 'label' => __('purchase_cycle.fields.due_date'), 'value' => LanguageSettings::formatDate($purchaseQuotation->due_date)],
                                ['icon' => 'fa-user', 'label' => __('purchase_cycle.fields.creator'), 'value' => $purchaseQuotation->creator->name ?? null],
                                ['icon' => 'fa-map-marker-alt', 'label' => __('purchase_cycle.fields.branch'), 'value' => \App\Models\Branch::pathLabelFor($purchaseQuotation->branch_id)],
                                ['icon' => 'fa-truck', 'label' => __('purchase_cycle.fields.suppliers'), 'value' => $purchaseQuotation->suppliers->pluck('name')->implode('، ')],
                                ['icon' => 'fa-box', 'label' => __('purchase_cycle.fields.items_count'), 'value' => LanguageSettings::formatNumber($items->count(), 0)],
                                ['icon' => 'fa-clock', 'label' => __('purchase_orders.fields.updated_at'), 'value' => LanguageSettings::formatDate($purchaseQuotation->updated_at, true)],
                            ],
                        ])

                        <hr>

                        <div class="row g-3">
                            <div class="col-lg-8">
                                <div class="po-detail__label">
                                    <i class="fas fa-sticky-note"></i>{{ __('purchase_cycle.fields.notes') }}
                                </div>
                                <div class="po-detail__value">
                                    {{ $purchaseQuotation->notes ?: __('purchase_orders.empty.no_notes') }}
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="po-detail__label">
                                    <i class="fas fa-paperclip"></i>{{ __('purchase_cycle.fields.attachments') }}
                                </div>
                                @if ($purchaseQuotation->attachments)
                                    <a href="{{ asset('storage/'.$purchaseQuotation->attachments) }}" target="_blank"
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

                <div class="tab-pane" id="items" role="tabpanel">
                    {{-- طلب عرض الأسعار بلا تسعير: هو طلب تسعيرة لا تسعيرة --}}
                    @include('purchases::partials.items-table', ['items' => $items, 'showAmounts' => false])
                </div>

                <div class="tab-pane" id="activity" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.activity', ['logs' => $logs])
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- نماذج العمليات — تُرسَل من أزرار شريط الإجراءات --}}
    <form id="approveForm" action="{{ route('Quotations.approve', $purchaseQuotation->id) }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" id="approveNote" name="note">
    </form>
    <form id="rejectForm" action="{{ route('Quotations.reject', $purchaseQuotation->id) }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="note">
    </form>
    <form id="deleteForm" action="{{ route('Quotations.destroy', $purchaseQuotation->id) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@section('scripts')
    @php
        $poDialogs = [
            'approveTitle' => __('purchase_orders.confirm.approve_title'),
            'approveText' => __('purchase_orders.confirm.approve_text'),
            'approveNote' => __('purchase_orders.fields.approval_note'),
            'rejectTitle' => __('purchase_orders.confirm.reject_title'),
            'rejectText' => __('purchase_orders.confirm.reject_text'),
            // لا cancelApproval هنا: الاعتماد نهائي في طلبات عروض الأسعار
            'deleteTitle' => __('purchase_orders.confirm.delete_title'),
            'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => $purchaseQuotation->code]),
            'yes' => __('purchase_orders.confirm.yes'),
            'yesDelete' => __('purchase_orders.confirm.yes_delete'),
            'no' => __('purchase_orders.confirm.no'),
        ];
    @endphp

    <script>
        window.purchaseShowConfig = @json($poDialogs);
    </script>
    <script src="{{ asset('assets/js/purchase-cycle-show.js') }}"></script>
@endsection
