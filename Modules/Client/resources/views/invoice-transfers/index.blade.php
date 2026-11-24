{{--
    تسليم واستلام الفواتير بين الموظفين.

    الشاشة **لم يكن لها قالبٌ أصلاً**: المتحكّم والمسارات والنموذج موجودة
    منذ البداية، و`index()` يعيد `view('client::invoice-transfers.index')`
    وهو ملفٌّ لا وجود له — فكل دخولٍ إلى «تحويلات الفواتير» ينتهي بـ
    «View [invoice-transfers.index] not found»، ومعه بندُ «تحويلات فواتير
    معلَّقة» في مركز تنبيهات لوحة العملاء الذي يقود إلى هنا.

    مبنيّةٌ على نظام تصميم وحدة العملاء نفسه (`.cl-scope` +
    `partials/settings-theme` + `components/page-head` + `components/guide`)
    كما في «الديون المشكوك فيها»، لا نظاماً مخترَعاً لها وحدها.

    كلُّ زرٍّ محروسٌ بنفس صلاحية مساره في `Modules/Client/routes/web.php`،
    فلا يظهر إجراءٌ يردّه الخادم بـ 403.
--}}
@extends('client::master')

@section('title')
    تسليم واستلام الفواتير
@stop

@section('content')
    @include('client::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $reasonRequired = \App\Helpers\ClientSettingsHelper::invoiceTransferReasonRequired();
        $requiresApproval = \App\Helpers\ClientSettingsHelper::invoiceTransferRequiresApproval();
        $maxOpen = \App\Helpers\ClientSettingsHelper::invoiceTransferMaxOpenPerEmployee();
        $allowPaid = \App\Helpers\ClientSettingsHelper::invoiceTransferAllowPaid();

        // يقرؤها `_table` لكلّ صفّ — كلُّ زرٍّ بصلاحية مساره
        $itUser = auth()->user();
        $itCanRespond = hasPermission('clients_invoice_transfer_respond');
        $itCanCancel = hasPermission('clients_invoice_transfer_cancel');
        $itActsOnBehalf = $itUser->hasRoleName('admin') || $itUser->hasRoleName('manager');

        $sentPending = $sentTransfers->where('status', 'pending')->count();
        $mine = $sentTransfers->concat($receivedTransfers)->unique('id');
        $acceptedCount = $mine->where('status', 'accepted')->count();
        $rejectedCount = $mine->where('status', 'rejected')->count();

        $activeFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->count();
        $filterTab = in_array(request('tab'), ['received', 'sent', 'all'], true) ? request('tab') : null;
    @endphp

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'تسليم واستلام الفواتير',
            'icon' => 'repeat',
            'sub' => 'نقل عهدة فاتورة عميلٍ من موظفٍ إلى آخر — تسليماً أو طلبَ استلام — ومتابعةُ كلّ طلب',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'تحويلات الفواتير' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @include('client::components.guide', [
            'key' => 'clients.invoice-transfers.index',
            'open' => false,
            'title' => 'ما هذه الشاشة؟',
            'lead' => 'تسليمُ الفاتورة نقلُ عهدتها: حين يُقبل التحويل تصير الفاتورة باسم الموظف الجديد في جدول الفواتير، ويُسجَّل ذلك في سجلّ العميل.',
            'blocks' => [
                'نوعا التحويل' => [
                    'تسليم — تُرسل فاتورةً في عهدتك إلى موظفٍ آخر، ويقبلها هو.',
                    'طلب استلام — تطلب فاتورةً في عهدة غيرك (من مساحة الزيارة غالباً)، ويقبل صاحبُ عهدتها.',
                    'بعد القبول تصير الفاتورة باسم من استلمها، فيقدر هو أن يسلّمها لغيره.',
                ],
                'القوائم' => [
                    'بانتظار ردّي — تسليمٌ أُرسل إليك، أو طلبُ استلامٍ لفاتورةٍ في عهدتك.',
                    'بدأتُها — ما أرسلتَه أو طلبتَه، ويمكنك إلغاؤه ما دام معلّقاً.',
                    'كلّ التحويلات — لمن نطاقُ رؤيته يشمل موظفين آخرين: كلُّ تحويلٍ أحدُ طرفيه ضمن نطاقك.',
                ],
            ],
            'facts' => array_values(array_filter([
                $requiresApproval
                    ? 'الاعتماد مطلوب: التسليم ينتظر قبول المستلِم'
                    : 'الاعتماد غير مطلوب: التسليم يُنفَّذ فور إرساله',
                'طلب الاستلام ينتظر صاحب العهدة دائماً',
                $reasonRequired ? 'سببُ التحويل والرفض مطلوبٌ كتابةً' : null,
                $allowPaid ? null : 'الفاتورة المسدَّدة كلياً لا تُحوَّل',
                $maxOpen ? 'أقصى طلباتٍ معلّقة للموظف الواحد: '.$maxOpen : null,
            ])),
        ])

        {{-- ══════════ ماذا تريد أن تفعل؟ ══════════
             الإجراءان أوّلَ الشاشة لا في آخر شريط التبويبات: هما سببُ فتحها. --}}
        <div class="cl-it-actions">
            @permission('clients_invoice_transfer_send')
                <button type="button" class="cl-it-action" id="clItNewBtn" data-bs-toggle="modal" data-bs-target="#clItNewModal">
                    <span class="cl-it-action__icon"><i class="feather icon-send"></i></span>
                    <span>
                        <strong>تسليم لموظف — بالرصيد أو بالفواتير</strong>
                        <small>خط سير موظفٍ كامل بضغطة، أو عميل عميل — لموظفٍ من فرع العميل</small>
                    </span>
                </button>
            @endpermission
            {{-- «استلام»: ما سلّمه الزملاءُ لي فقط — لا تصفّحَ لفواتير غيري ولا طلبَ
                 لها (بطلب المستخدم 2026-09-26). الزرّ ينزل إلى «بانتظار استلامك». --}}
            @permission('clients_invoice_transfer_respond')
                @php $myIncoming = $awaitingBatches->flatten(1)->where('direction', '!=', 'request')->count(); @endphp
                <a href="#clItAwaiting" class="cl-it-action text-reset text-decoration-none" id="clItReceiveBtn">
                    <span class="cl-it-action__icon is-alt"><i class="feather icon-download"></i></span>
                    <span>
                        <strong>استلام @if ($myIncoming)<span class="badge bg-warning text-dark ms-1">{{ $myIncoming }}</span>@endif</strong>
                        <small>{{ $myIncoming ? 'ما سلّمه لك زملاؤك وينتظر استلامك' : 'لا شيء سُلِّم لك الآن' }}</small>
                    </span>
                </a>
            @endpermission
        </div>

        {{-- ══════════ بانتظار استلامك ══════════
             دفعاتٌ لا صفوف: «سلّمك فلانٌ ٥ فواتير للعميل س» واستلامُها كلِّها
             بضغطة (`invoice-transfers.batch.respond`). المستلِم يصل العميلَ من
             خطّ سيره فيستلم من الزيارة أيضاً — هذا القسم نفسُه هناك. --}}
        <div class="card cl-table-card cl-it-awaiting" id="clItAwaiting">
            <div class="cl-table-head d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h4 class="cl-table-head__title">
                    <i class="feather icon-inbox"></i>
                    بانتظار استلامك
                    <span class="cl-count">{{ $awaitingBatches->flatten(1)->count() }}</span>
                </h4>
                @permission('clients_invoice_transfer_respond')
                    @if ($awaitingBatches->count() > 1)
                        <button type="button" class="btn btn-success btn-sm" id="clItReceiveAll">
                            <i class="feather icon-check-circle"></i> استلام الكل ({{ $awaitingBatches->flatten(1)->where('direction', '!=', 'request')->count() }})
                        </button>
                    @endif
                @endpermission
            </div>
            <div class="card-body">
                @forelse ($awaitingBatches as $batchKey => $batchRows)
                    @php
                        $bFirst = $batchRows->first();
                        $bIsRequest = $bFirst->isRequest();
                        $bIsBalance = $bFirst->isBalance();
                        $bDue = $batchRows->sum(fn ($t) => (float) ($t->isBalance() ? $t->invoice_amount : ($t->invoice->due_value ?? 0)));
                        $bWhat = $bIsBalance
                            ? ($batchRows->count() === 1 ? 'رصيد عميل' : 'أرصدة '.$batchRows->count().' عملاء')
                            : ($batchRows->count() === 1 ? 'فاتورة' : $batchRows->count().' فواتير');
                        $bClients = $batchRows->pluck('client')->filter()->unique('id');
                        $bAt = $bFirst->sent_at ?? $bFirst->created_at;
                        $bInvoiceDue = $bIsBalance
                            ? \App\Models\Invoice::whereIn('client_id', $batchRows->pluck('client_id'))->where('due_value', '>', 0)->whereNull('voided_at')
                                ->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'returned'))
                                ->groupBy('client_id')->selectRaw('client_id, SUM(due_value) as due')->pluck('due', 'client_id')
                            : collect();
                    @endphp
                    <article class="cl-it-batch">
                        <header class="cl-it-batch__head">
                            <div>
                                <p class="cl-it-batch__title">
                                    @if ($bIsRequest)
                                        <i class="feather icon-download"></i>
                                        {{ $bFirst->toEmployee->name ?? '—' }} يطلب استلام
                                        {{ $batchRows->count() === 1 ? 'فاتورة' : $batchRows->count().' فواتير' }} من عهدتك
                                    @else
                                        <i class="feather icon-send"></i>
                                        {{ $bFirst->fromEmployee->name ?? '—' }} سلّمك {{ $bWhat }}
                                    @endif
                                </p>
                                <p class="cl-it-batch__meta">
                                    @foreach ($bClients as $bClient)
                                        <a href="{{ route('clients.show', $bClient->id) }}">{{ $bClient->trade_name }}</a>@if ($bClient->code) <small>({{ $bClient->code }})</small>@endif @if (! $loop->last) ·@endif
                                    @endforeach
                                    · {{ $bAt?->locale('ar')->diffForHumans() }}
                                </p>
                                @if ($bFirst->sender_notes)
                                    <p class="cl-it-batch__note"><b>السبب:</b> {{ $bFirst->sender_notes }}</p>
                                @endif
                            </div>
                            <div class="cl-it-batch__total">
                                <small>{{ $bIsBalance ? 'إجمالي الرصيد' : 'إجمالي المستحق' }}</small>
                                <strong>{{ number_format($bDue, 2) }}</strong>
                            </div>
                        </header>

                        <table class="table table-sm mb-2">
                            <thead><tr><th>{{ $bIsBalance ? 'البند' : 'الفاتورة' }}</th><th>العميل</th><th>التاريخ</th><th>{{ $bIsBalance ? 'الرصيد يوم التسليم' : 'الإجمالي' }}</th><th>{{ $bIsBalance ? 'مستحق فواتيره الآن' : 'المستحق' }}</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($batchRows as $row)
                                    <tr>
                                        <td>
                                            @if ($row->isBalance())
                                                <strong>رصيد الحساب</strong>
                                            @else
                                                <a href="{{ route('invoices.show', $row->invoice_id) }}" target="_blank"><bdi>{{ $row->invoice_code }}</bdi></a>
                                            @endif
                                        </td>
                                        <td>{{ $row->client->trade_name ?? '—' }}</td>
                                        <td>{{ $row->isBalance() ? ($row->sent_at?->format('Y-m-d') ?? '—') : ($row->invoice?->invoice_date ? \Illuminate\Support\Carbon::parse($row->invoice->invoice_date)->format('Y-m-d') : '—') }}</td>
                                        <td>{{ number_format((float) $row->invoice_amount, 2) }}</td>
                                        <td><strong>{{ $row->isBalance() ? number_format((float) ($bInvoiceDue[$row->client_id] ?? 0), 2) : number_format((float) ($row->invoice->due_value ?? 0), 2) }}</strong></td>
                                        {{-- رفضُ عميلٍ واحد من الدفعة — ثمّ «استلام الكل» يأخذ الباقي --}}
                                        <td class="text-end">
                                            @if ($batchRows->count() > 1)
                                                @permission('clients_invoice_transfer_respond')
                                                    <button type="button" class="btn btn-sm btn-outline-danger cl-it-respond"
                                                            data-bs-toggle="modal" data-bs-target="#clItRespondModal"
                                                            data-id="{{ $row->id }}" data-mode="reject" data-request="{{ $row->isRequest() ? 1 : 0 }}"
                                                            data-subject="{{ $row->isBalance() ? 'رصيد العميل '.($row->client->trade_name ?? '') : 'الفاتورة '.$row->invoice_code }}"
                                                            data-to="{{ $row->toEmployee->name ?? '' }}">
                                                        <i class="feather icon-x"></i> رفض
                                                    </button>
                                                @endpermission
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @permission('clients_invoice_transfer_respond')
                            <div class="cl-it-batch__foot">
                                <button type="button" class="btn btn-success cl-it-respond"
                                        data-bs-toggle="modal" data-bs-target="#clItRespondModal"
                                        @if ($bFirst->batch) data-batch="{{ $bFirst->batch }}" @else data-id="{{ $bFirst->id }}" @endif
                                        data-mode="accept" data-request="{{ $bIsRequest ? 1 : 0 }}"
                                        data-subject="{{ $bIsBalance ? ($batchRows->count() === 1 ? 'رصيد العميل '.($bFirst->client->trade_name ?? '') : $bWhat) : ($batchRows->count() === 1 ? 'الفاتورة '.$bFirst->invoice_code : $batchRows->count().' فواتير') }}"
                                        data-to="{{ $bFirst->toEmployee->name ?? '' }}">
                                    <i class="feather icon-check"></i>
                                    {{ $bIsRequest ? 'موافقة وتسليمها' : ($batchRows->count() === 1 ? ($bIsBalance ? 'استلام الرصيد' : 'استلام الفاتورة') : 'استلام الكل ('.$batchRows->count().')') }}
                                </button>
                                <button type="button" class="btn btn-outline-danger cl-it-respond"
                                        data-bs-toggle="modal" data-bs-target="#clItRespondModal"
                                        @if ($bFirst->batch) data-batch="{{ $bFirst->batch }}" @else data-id="{{ $bFirst->id }}" @endif
                                        data-mode="reject" data-request="{{ $bIsRequest ? 1 : 0 }}"
                                        data-subject="{{ $bIsBalance ? ($batchRows->count() === 1 ? 'رصيد العميل '.($bFirst->client->trade_name ?? '') : $bWhat) : ($batchRows->count() === 1 ? 'الفاتورة '.$bFirst->invoice_code : $batchRows->count().' فواتير') }}"
                                        data-to="{{ $bFirst->toEmployee->name ?? '' }}">
                                    <i class="feather icon-x"></i> {{ $batchRows->count() > 1 ? 'رفض الكل' : 'رفض' }}
                                </button>
                            </div>
                        @endpermission
                    </article>
                @empty
                    <div class="cl-empty">
                        <span class="cl-empty__icon"><i class="feather icon-check-circle"></i></span>
                        <p class="cl-empty__title">لا شيء بانتظارك</p>
                        <p class="cl-empty__hint">حين يسلّمك زميلٌ فواتير تظهر هنا وفي زيارة العميل، فتستلمها بضغطة.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ══════════ السجلّ ══════════ --}}
        <h5 class="cl-it-history-title"><i class="feather icon-clock"></i> سجلّ التحويلات</h5>

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="cl-stats">
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--warning"><i class="feather icon-inbox"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $pendingReceivedCount }}</p>
                    <p class="cl-stat__label">بانتظار ردّك</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--info"><i class="feather icon-send"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $sentPending }}</p>
                    <p class="cl-stat__label">بدأتَها وما زالت معلّقة</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--success"><i class="feather icon-check-circle"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $acceptedCount }}</p>
                    <p class="cl-stat__label">مقبولة</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--danger"><i class="feather icon-x-circle"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $rejectedCount }}</p>
                    <p class="cl-stat__label">مرفوضة</p>
                </div>
            </div>
        </div>

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card cl-filters">
            <div class="card-body">
                <div class="cl-filters__head">
                    <div class="cl-filters__title">
                        <i class="feather icon-sliders"></i>
                        <span>بحث وتصفية التحويلات</span>
                    </div>
                </div>

                <form method="GET" action="{{ route('invoice-transfers.index') }}" id="clItFilters">
                    <input type="hidden" name="tab" value="{{ $filterTab }}">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">رقم الفاتورة أو العميل</label>
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="بحث…">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">العميل</label>
                            <select name="client_id" class="form-select">
                                <option value="">— كل العملاء —</option>
                                @foreach ($clientOptions as $clientOption)
                                    <option value="{{ $clientOption->id }}" @selected($filters['client_id'] === $clientOption->id)>
                                        {{ $clientOption->trade_name }}@if ($clientOption->code) ({{ $clientOption->code }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">النوع</label>
                            <select name="direction" class="form-select">
                                <option value="">— الكل —</option>
                                <option value="send" @selected($filters['direction'] === 'send')>تسليم</option>
                                <option value="request" @selected($filters['direction'] === 'request')>طلب استلام</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-select">
                                <option value="">— كل الحالات —</option>
                                <option value="pending" @selected($filters['status'] === 'pending')>معلّق</option>
                                <option value="accepted" @selected($filters['status'] === 'accepted')>مقبول</option>
                                <option value="rejected" @selected($filters['status'] === 'rejected')>مرفوض</option>
                            </select>
                        </div>
                        @if ($canSeeAll)
                            <div class="col-md-2">
                                <label class="form-label">الموظف</label>
                                <select name="employee_id" class="form-select">
                                    <option value="">— الكل —</option>
                                    @foreach ($employeeOptions as $employeeOption)
                                        <option value="{{ $employeeOption->id }}" @selected($filters['employee_id'] === $employeeOption->id)>{{ $employeeOption->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <div class="cl-filters__foot">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> تطبيق البحث
                        </button>
                        <a href="{{ route('invoice-transfers.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> إعادة تعيين
                        </a>
                        @if ($activeFilters > 0)
                            <small class="text-muted ms-auto">
                                <i class="fa fa-info-circle me-1"></i> لديك {{ $activeFilters }} فلتر مفعّل حالياً
                                @if ($filters['employee_id'])
                                    — فلتر الموظف يسري على «كلّ التحويلات» وحدها
                                @endif
                            </small>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ التبويبات ══════════ --}}
        <div class="cl-tabs" role="tablist">
            <button type="button" class="cl-tab is-active" data-itab="received" role="tab" aria-selected="true">
                <i class="feather icon-download"></i> وردت إليّ
                <span class="cl-count">{{ $receivedTransfers->count() }}</span>
            </button>
            <button type="button" class="cl-tab" data-itab="sent" role="tab" aria-selected="false">
                <i class="feather icon-upload"></i> أرسلتُها أو طلبتُها
                <span class="cl-count">{{ $sentTransfers->count() }}</span>
            </button>
            @if ($canSeeAll)
                <button type="button" class="cl-tab" data-itab="all" role="tab" aria-selected="false">
                    <i class="feather icon-users"></i> كلّ التحويلات
                    <span class="cl-count">{{ $allTransfers->count() }}</span>
                </button>
            @endif
        </div>

        {{-- ══════════ بانتظار ردّي ══════════ --}}
        <div class="card cl-table-card" data-ipane="received">
            <div class="cl-table-head">
                <h4 class="cl-table-head__title">
                    <i class="feather icon-download"></i>
                    تسليمٌ إليّ وطلباتُ استلامٍ من عهدتي
                    <span class="cl-count">{{ $receivedTransfers->count() }}</span>
                </h4>
            </div>
            <div class="card-body">
                @include('client::invoice-transfers._table', [
                    'rows' => $receivedTransfers,
                    'emptyIcon' => 'icon-inbox',
                    'emptyTitle' => 'لا شيء بانتظار ردّك',
                    'emptyHint' => 'لم يُرسَل إليك تسليم، ولم يطلب أحدٌ فاتورةً من عهدتك',
                ])
            </div>
        </div>

        {{-- ══════════ بدأتُها ══════════ --}}
        <div class="card cl-table-card d-none" data-ipane="sent">
            <div class="cl-table-head">
                <h4 class="cl-table-head__title">
                    <i class="feather icon-upload"></i>
                    ما سلّمتُه وما طلبتُ استلامه
                    <span class="cl-count">{{ $sentTransfers->count() }}</span>
                </h4>
            </div>
            <div class="card-body">
                @include('client::invoice-transfers._table', [
                    'rows' => $sentTransfers,
                    'emptyIcon' => 'icon-send',
                    'emptyTitle' => 'لم تبدأ أيّ تحويل',
                    'emptyHint' => 'سلِّم فاتورةً من زرّ «تسليم فاتورة»، أو اطلب استلام فاتورة عميلٍ من مساحة زيارته',
                ])
            </div>
        </div>

        {{-- ══════════ كلّ التحويلات ══════════ --}}
        @if ($canSeeAll)
            <div class="card cl-table-card d-none" data-ipane="all">
                <div class="cl-table-head">
                    <h4 class="cl-table-head__title">
                        <i class="feather icon-users"></i>
                        كلّ التحويلات ضمن نطاقك
                        <span class="cl-count">{{ $allTransfers->count() }}</span>
                    </h4>
                </div>
                <div class="card-body">
                    @include('client::invoice-transfers._table', [
                        'rows' => $allTransfers,
                        'emptyIcon' => 'icon-repeat',
                        'emptyTitle' => 'لا توجد تحويلات',
                        'emptyHint' => $activeFilters ? 'لا تحويل يطابق الفلاتر الحالية' : 'لم يجرِ أيُّ تحويلٍ بين موظفي نطاقك بعد',
                    ])
                    @if ($allTransfers->count() >= 500)
                        <small class="cl-help d-block mt-2">تُعرض أحدث 500 تحويل — ضيّق البحث بالعميل أو الموظف لرؤية الأقدم.</small>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ══════════ نافذتا التسليم وطلب الاستلام ══════════
         خطواتٌ مرقّمة: الفاتورة من قائمةٍ يُبحث فيها (رقمها وعميلها وتاريخها
         ومستحقّها ظاهرةٌ كلُّها، لا سطرٌ واحد في قائمةٍ منسدلة)، ثم الموظف،
         ثم السبب — وملخّصٌ بجملةٍ واحدة يقول ما سيحدث قبل الإرسال.
         المستلِمون من يملك دورُه صلاحية الاستلام فقط؛ من لا يملكها لا يُعرض
         خياراً معطّلاً بل يُذكر تحت القائمة لمن يريد أن يعرف لماذا غاب. --}}
    @php
        $eligibleRecipients = $employees->where('can_receive', true)->values();
        $blockedRecipients = $employees->where('can_receive', false)->values();
    @endphp

    {{-- ══════════ نافذة التسليم ══════════
         شريطٌ واحد فوق: من أين العملاء (يومٌ من خطّ سيري أو كلُّ عملائي)،
         والمستلِم (زملاءُ فرعي)، وماذا يُسلَّم (الرصيد أو الفواتير) — ثم جدولٌ
         واحد بطول الشاشة، لا نافذةٌ داخل نافذة. من لا يُسلَّم لا يزاحم
         الجدول: يُطوى في سطرٍ واحد تحته بسببه. --}}
    @permission('clients_invoice_transfer_send')
        <div class="modal fade" id="clItNewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog cl-it-wide" style="max-width: min(1280px, 96vw) !important; width: 96vw !important;">
                <div class="modal-content cl-scope">
                    <form id="clItNewForm" class="d-flex flex-column h-100">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="feather icon-send"></i> تسليم لموظف</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>

                        <div class="modal-body">
                            <div class="cl-it-bar">
                                <div>
                                    <label class="form-label">العملاء</label>
                                    <select class="form-select" data-source>
                                        @foreach ($itineraryDays as $day)
                                            @php
                                                $dayDate = \Illuminate\Support\Carbon::parse($day['date']);
                                                $dayRel = $dayDate->isToday() ? ' (اليوم)' : ($dayDate->isTomorrow() ? ' (غداً)' : ($dayDate->isPast() ? ' (مضى)' : ''));
                                            @endphp
                                            <option value="{{ $day['date'] }}" @selected($defaultSource === $day['date'])>
                                                خط سيري — {{ $dayDate->locale('ar')->translatedFormat('l') }} {{ $day['date'] }}{{ $dayRel }} · {{ $day['clients'] }} عميل
                                            </option>
                                        @endforeach
                                        <option value="all" @selected($defaultSource === 'all')>كل عملائي</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">الموظف المستلِم — من فرعك</label>
                                    <select class="form-select" name="to_employee_id" data-receiver>
                                        @if ($branchMates->isEmpty())
                                            <option value="">لا يوجد في فرعك موظفٌ يملك صلاحية الاستلام</option>
                                        @else
                                            <option value="">— اختر الموظف —</option>
                                            @foreach ($branchMates as $mate)
                                                <option value="{{ $mate->id }}">{{ $mate->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">ماذا تسلّم</label>
                                    <div class="cl-it-seg" role="radiogroup">
                                        <label><input type="radio" name="__what" value="balance" checked><span>رصيد العميل</span></label>
                                        <label><input type="radio" name="__what" value="invoices"><span>فواتيره</span></label>
                                    </div>
                                </div>
                            </div>
                            {{-- من غاب من زملاء الفرع ولماذا — لا يختفي أحدٌ بصمت --}}
                            @foreach ($excludedMates as $why => $people)
                                <div class="cl-it-absent">
                                    <i class="feather icon-alert-circle"></i>
                                    <span>لا يظهر في قائمة المستلِمين: <b>{{ $people->pluck('name')->implode('، ') }}</b> — {{ $people->count() > 1 ? str_replace(['حالته', 'دورُه'], ['حالتهم', 'أدوارهم'], $why) : $why }}.
                                        {{ str_contains($why, 'شؤون الموظفين') ? 'غيّر الحالة إلى «نشط» من ملف الموظف ليستلم.' : 'امنح دورَه صلاحية «قبول أو رفض تحويل وارد» من شاشة الأدوار.' }}</span>
                                </div>
                            @endforeach
                            <p class="cl-it-step__hint mb-3" data-what-hint></p>

                            <div data-list></div>

                            <details class="cl-it-skipped" data-skipped hidden>
                                <summary></summary>
                                <ul></ul>
                            </details>

                            <div class="mt-3">
                                <label class="form-label">سبب التسليم @if ($reasonRequired)<span class="cl-req"></span>@else<small class="text-muted">(اختياري)</small>@endif</label>
                                <textarea name="sender_notes" rows="2" class="form-control" maxlength="1000"
                                          @if ($reasonRequired) required @endif
                                          placeholder="مثلاً: عملاء خط سيري اليوم — سيتابع تحصيلهم زميلي"></textarea>
                            </div>
                        </div>

                        <div class="modal-footer cl-it-foot">
                            <div class="cl-it-foot__summary" data-summary>اختر الموظف المستلِم.</div>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary" disabled>
                                <i class="feather icon-send"></i> تسليم
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endpermission






    <style>
        .cl-it-step { margin-bottom: 1.25rem; }
        .cl-it-step__title { display: flex; align-items: center; gap: .5rem; font-weight: 700; margin-bottom: .35rem; }
        .cl-it-step__title > span:first-child:not(.cl-req) { width: 24px; height: 24px; border-radius: 50%; display: inline-grid; place-items: center; background: var(--app-primary, #7367f0); color: var(--app-on-primary, #fff); font-size: .8rem; }
        .cl-it-step__hint { color: var(--app-muted, #6e6b7b); font-size: .85rem; margin-bottom: .5rem; }
        .cl-it-picker { max-height: 260px; overflow-y: auto; border: 1px solid var(--app-border, #ebe9f1); border-radius: 8px; }
        .cl-it-picker__state { padding: 1.25rem; text-align: center; color: var(--app-muted, #6e6b7b); }
        /* `.cl-scope label` يجعل كلَّ label كتلةً — فتتكدّس خانات الفاتورة فوق بعضها */
        .cl-scope label.cl-it-pick { display: grid; grid-template-columns: auto 1fr auto; gap: .75rem; align-items: center; padding: .6rem .85rem; border-bottom: 1px solid var(--app-border, #ebe9f1); cursor: pointer; margin: 0; }
        .cl-it-pick:last-child { border-bottom: 0; }
        .cl-it-pick:hover { background: color-mix(in srgb, var(--app-primary, #7367f0) 5%, transparent); }
        .cl-it-pick.is-selected { background: color-mix(in srgb, var(--app-primary, #7367f0) 12%, transparent); }
        .cl-it-pick__main strong { display: block; }
        .cl-it-pick__main small { color: var(--app-muted, #6e6b7b); }
        .cl-it-pick__due { text-align: end; white-space: nowrap; }
        .cl-it-pick__due b { display: block; color: var(--app-danger, #ea5455); }
        .cl-it-pick__due small { color: var(--app-muted, #6e6b7b); }
        .cl-it-form .form-select, #clItNewForm .form-select { display: block; width: 100%; }
        .cl-scope label.cl-it-all { display: inline-flex; align-items: center; gap: .35rem; margin-inline-start: auto; font-weight: 500; font-size: .85rem; }
        .cl-it-diff { color: var(--app-warning, #ff9f43); font-size: .72rem; }
        .cl-it-awaiting { scroll-margin-top: 90px; transition: box-shadow .3s ease; }
        .cl-it-awaiting.is-flash { box-shadow: 0 0 0 3px var(--app-warning, #ff9f43); }
        .cl-it-bar { display: grid; grid-template-columns: 1.3fr 1.3fr auto; gap: 1rem; align-items: end; margin-bottom: .5rem; }
        @media (max-width: 767px) { .cl-it-bar { grid-template-columns: 1fr; } }
        .cl-it-bar .form-select { display: block; width: 100%; }
        .cl-it-seg { display: inline-flex; border: 1px solid var(--app-border, #ebe9f1); border-radius: 8px; overflow: hidden; }
        .cl-scope .cl-it-seg label { display: block; margin: 0; cursor: pointer; }
        .cl-it-seg input { position: absolute; opacity: 0; pointer-events: none; }
        .cl-it-seg span { display: block; padding: .55rem 1.1rem; font-weight: 600; white-space: nowrap; }
        .cl-it-seg input:checked + span { background: var(--app-primary, #7367f0); color: var(--app-on-primary, #fff); }
        .cl-it-list th { font-size: .8rem; color: var(--app-muted, #6e6b7b); }
        .cl-it-list td { vertical-align: middle; }
        .cl-it-skipped { margin-top: .75rem; padding: .6rem .9rem; border-radius: 8px; background: color-mix(in srgb, var(--app-muted, #6e6b7b) 8%, transparent); }
        .cl-it-skipped summary { cursor: pointer; font-weight: 600; }
        .cl-it-skipped ul { margin: .5rem 0 0; padding-inline-start: 1.2rem; font-size: .85rem; }
        /* بوتستراب الرابع في القالب لا يعرف `modal-fullscreen` — عرضٌ ثابتٌ واسع
           وارتفاعٌ ثابت، فلا تتقلّص النافذة مع قِصَر الجدول ولا يتمرّر إلّا جسمُها */
        #clItNewModal .cl-it-wide { max-width: min(1280px, 96vw) !important; width: 96vw !important; margin: 2vh auto; }
        #clItNewModal .cl-it-list td:nth-child(3) { min-width: 220px; }
        #clItNewModal .cl-it-list td:nth-child(4), #clItNewModal .cl-it-list td:nth-child(5) { white-space: nowrap; }
        #clItNewModal .cl-it-wide .modal-content { height: 96vh; }
        #clItNewModal .cl-it-wide .modal-body { overflow-y: auto; flex: 1 1 auto; }
        .cl-it-foot { gap: .5rem; }
        .cl-it-absent { display: flex; gap: .5rem; align-items: flex-start; padding: .55rem .8rem; margin: .5rem 0; border-radius: 8px; font-size: .85rem; background: color-mix(in srgb, var(--app-warning, #ff9f43) 12%, transparent); }
        .cl-it-absent i { color: var(--app-warning, #ff9f43); margin-top: .15rem; }
        .cl-it-foot__summary { margin-inline-end: auto; font-size: .95rem; }
        .cl-it-history-title { margin: 1.5rem 0 .75rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
        .cl-it-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .cl-it-action { display: flex; align-items: center; gap: .85rem; text-align: start; padding: 1rem 1.1rem; border: 1px solid var(--app-border, #ebe9f1); border-radius: 12px; background: var(--app-surface, #fff); cursor: pointer; transition: box-shadow .15s ease, border-color .15s ease; }
        .cl-it-action:hover { border-color: var(--app-primary, #7367f0); box-shadow: 0 4px 16px rgba(0,0,0,.06); }
        .cl-it-action strong { display: block; font-size: 1rem; }
        .cl-it-action small { color: var(--app-muted, #6e6b7b); }
        .cl-it-action__icon { flex: none; width: 44px; height: 44px; border-radius: 10px; display: grid; place-items: center; font-size: 1.2rem; background: color-mix(in srgb, var(--app-primary, #7367f0) 14%, transparent); color: var(--app-primary, #7367f0); }
        .cl-it-action__icon.is-alt { background: color-mix(in srgb, var(--app-info, #00cfe8) 14%, transparent); color: var(--app-info, #00cfe8); }
        .cl-it-batch { border: 1px solid var(--app-border, #ebe9f1); border-inline-start: 4px solid var(--app-warning, #ff9f43); border-radius: 10px; padding: .9rem 1rem; margin-bottom: .9rem; }
        .cl-it-batch:last-child { margin-bottom: 0; }
        .cl-it-batch__head { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: .6rem; }
        .cl-it-batch__title { font-weight: 700; font-size: 1rem; margin: 0 0 .2rem; }
        .cl-it-batch__meta, .cl-it-batch__note { margin: 0; color: var(--app-muted, #6e6b7b); font-size: .85rem; }
        .cl-it-batch__total { text-align: end; white-space: nowrap; }
        .cl-it-batch__total small { display: block; color: var(--app-muted, #6e6b7b); }
        .cl-it-batch__total strong { font-size: 1.25rem; color: var(--app-danger, #ea5455); }
        .cl-it-batch__foot { display: flex; gap: .5rem; flex-wrap: wrap; }
        .cl-it-blocked { margin-top: .5rem; font-size: .8rem; color: var(--app-muted, #6e6b7b); }
        .cl-it-blocked summary { cursor: pointer; }
        .cl-it-blocked p { margin: .35rem 0 0; }
        .cl-it-summary { padding: .75rem 1rem; border-radius: 8px; background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent); font-weight: 600; }
    </style>

    {{-- ══════════ نافذة الردّ ══════════ --}}
    @permission('clients_invoice_transfer_respond')
        <div class="modal fade" id="clItRespondModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content cl-scope">
                    <form id="clItRespondForm">
                        <input type="hidden" name="transfer_id">
                        <input type="hidden" name="batch">
                        <input type="hidden" name="mode">

                        <div class="modal-header">
                            <h5 class="modal-title" id="clItRespondTitle">الردّ على طلب التسليم</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>

                        <div class="modal-body">
                            <p id="clItRespondLead" class="mb-3"></p>

                            <label class="form-label">
                                ملاحظات <span class="cl-req d-none" id="clItRespondReq"></span>
                            </label>
                            <textarea name="receiver_notes" rows="3" class="form-control" maxlength="1000"></textarea>
                            <small class="cl-help" id="clItRespondHint"></small>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary" id="clItRespondSubmit">تأكيد</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endpermission
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var meta = document.querySelector('meta[name="csrf-token"]');
    var CSRF = meta ? meta.getAttribute('content') : '';
    var REASON_REQUIRED = @json($reasonRequired);

    var ROUTES = {
        store: @json(route('invoice-transfers.store')),
        balance: @json(route('invoice-transfers.balance')),
        sendable: @json(route('invoice-transfers.sendable-clients')),
        receiveAll: @json(route('invoice-transfers.receive-all')),
        batch: @json(route('invoice-transfers.batch.respond', ['batch' => '__BATCH__'])),
        invoices: @json(route('invoice-transfers.available-invoices')),
        accept: @json(route('invoice-transfers.accept', ['id' => '__ID__'])),
        reject: @json(route('invoice-transfers.reject', ['id' => '__ID__'])),
        cancel: @json(route('invoice-transfers.cancel', ['id' => '__ID__'])),
    };

    function notify(type, message) {
        if (window.toastr) { toastr[type](message); return; }
        alert(message);
    }

    /* الشاشة تُعاد بالكامل بعد كل عملية: الحالات والإحصاءات كلها من الخادم،
       فتحديثُ صفٍّ واحد في المتصفّح يترك بقيّةَ الأرقام كاذبة */
    function reload() { window.location.reload(); }

    function send(url, method, payload) {
        return fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF
            },
            body: payload ? JSON.stringify(payload) : null
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                return { ok: response.ok, status: response.status, body: body };
            });
        });
    }

    /* رسالةُ الخادم أوّلاً — رسائل التحقّق من لارافيل تأتي في `errors` */
    function errorOf(result) {
        if (result.body && result.body.message) { return result.body.message; }
        if (result.body && result.body.errors) {
            var first = Object.values(result.body.errors)[0];
            if (first && first.length) { return first[0]; }
        }
        return 'تعذّر إتمام العملية';
    }

    /* ═══════════ التبويبات ═══════════
       التبويب في عنوان الصفحة (#sent): بعد التسليم تُعاد الصفحة على «مرسلة
       مني» — كانت تعود إلى «واردة إليّ» فيبدو التسليمُ كأنه لم يُحفظ */
    function activateTab(target) {
        document.querySelectorAll('.cl-tab[data-itab]').forEach(function (other) {
            var active = other.dataset.itab === target;
            other.classList.toggle('is-active', active);
            other.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        document.querySelectorAll('[data-ipane]').forEach(function (pane) {
            pane.classList.toggle('d-none', pane.dataset.ipane !== target);
        });
    }

    /* التبويب يُحمل مع الفلاتر أيضاً (`tab` في النموذج): تطبيقُ البحث وأنت
       على «كلّ التحويلات» يعيدك إليها لا إلى «بانتظار ردّي» */
    var filterTab = document.querySelector('#clItFilters input[name="tab"]');

    function rememberTab(target) {
        history.replaceState(null, '', location.pathname + location.search + '#' + target);
        if (filterTab) { filterTab.value = target; }
    }

    document.querySelectorAll('.cl-tab[data-itab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab.dataset.itab);
            rememberTab(tab.dataset.itab);
        });
    });

    var initialTab = location.hash.replace('#', '') || @json($filterTab);
    if (initialTab && document.querySelector('.cl-tab[data-itab="' + initialTab + '"]')) {
        activateTab(initialTab);
        if (filterTab) { filterTab.value = initialTab; }
    }

    /* ═══════════ التسليم وطلب الاستلام ═══════════

       فتحُ النوافذ وإغلاقها تصريحيّ بـ`data-bs-toggle`/`data-bs-dismiss` لا
       `new bootstrap.Modal(...)`: القالب `client::master` يحمّل بوتستراب 5
       أوّلاً ثمّ `vendors.min.js` وفيه بوتستراب 4 — فآخر ما يكتب
       `window.bootstrap` هو الرابع، بينما مستمعاتُ الخامس المفوَّضة على
       المستند تبقى حيّةً وتفتح النافذة صحيحةً.

       نافذتان بسلوكٍ واحد (`data-mode`): قائمةُ فواتير يُبحث فيها من الخادم
       (`available-invoices`، و`mode=request` لما في عهدة غيري)، واختيارُ
       فاتورةٍ يملأ `invoice_id` ويكتب ملخّصاً بجملةٍ واحدة. */
    var REQUIRES_APPROVAL = @json($requiresApproval);

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }


    /* ═══════════ التسليم ═══════════
       العملاء (يومٌ من خطّ سيري أو كلُّ عملائي) + المستلِم (زميلٌ من فرعي) +
       ماذا (الرصيد أو الفواتير) ← جدولٌ بمن يُسلَّم، محدَّدين كلُّهم.
       الجدولُ من `sendable-clients`، ويُعاد طلبُه حين يتغيّر المصدر أو
       المستلِم (فرعُ المستلِم يُخرج من ليس في فرعه). الخادمُ يعيد التحقّق. */
    (function () {
        var form = document.getElementById('clItNewForm');
        if (!form) { return; }

        var source = form.querySelector('[data-source]');
        var receiver = form.querySelector('[data-receiver]');
        var list = form.querySelector('[data-list]');
        var skippedBox = form.querySelector('[data-skipped]');
        var summary = form.querySelector('[data-summary]');
        var whatHint = form.querySelector('[data-what-hint]');
        var submit = form.querySelector('button[type="submit"]');
        var clients = [];
        var unchecked = {};
        var loadToken = 0;

        var what = function () { return form.querySelector('input[name="__what"]:checked').value; };
        function money(value) {
            return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function clientsCount(n) {
            return n === 1 ? 'عميل واحد' : n === 2 ? 'عميلان' : n + (n <= 10 ? ' عملاء' : ' عميلاً');
        }

        /** هل يُسلَّم هذا العميل؟ وبكم، أو لماذا لا */
        function state(client) {
            if (client.outside) { return { ok: false, why: 'خارج فرع المستلِم (' + client.outside + ')' }; }
            if (what() === 'balance') {
                if (client.balance <= 0) { return { ok: false, why: 'رصيده صفر أو دائن' }; }
                if (!client.my_balance) { return { ok: false, why: 'رصيده ليس في عهدتك' }; }
                return { ok: true, amount: client.balance };
            }
            if (!client.my_invoice_ids.length) { return { ok: false, why: 'لا فواتير مستحقّة له في عهدتك' }; }
            return { ok: true, amount: client.my_invoice_due };
        }

        function render() {
            whatHint.textContent = what() === 'balance'
                ? 'رصيد العميل = رصيد حسابه بعد الإشعارات الدائنة والسندات والرصيد الافتتاحي. حين يُستلَم تنتقل معه فواتيرك المستحقّة لذلك العميل.'
                : 'تُسلَّم كلُّ فواتيرك المستحقّة لكلّ عميلٍ محدَّد، بمستحقّها كما في الفاتورة.';

            var ok = [];
            var skipped = [];
            clients.forEach(function (client) {
                var st = state(client);
                (st.ok ? ok : skipped).push({ client: client, st: st });
            });

            if (!clients.length) {
                list.innerHTML = '<div class="cl-empty"><span class="cl-empty__icon"><i class="feather icon-inbox"></i></span>'
                    + '<p class="cl-empty__title">' + (source.value === 'all' ? 'لا يوجد عميلٌ لك عنده رصيدٌ أو فاتورةٌ مستحقّة' : 'لا عملاء في خط سيرك هذا اليوم') + '</p>'
                    + '<p class="cl-empty__hint">اختر يوماً آخر أو «كل عملائي».</p></div>';
            } else if (!ok.length) {
                list.innerHTML = '<div class="cl-empty"><span class="cl-empty__icon"><i class="feather icon-slash"></i></span>'
                    + '<p class="cl-empty__title">لا عميل يمكن تسليمه هنا</p><p class="cl-empty__hint">الأسباب في السطر تحت.</p></div>';
            } else {
                list.innerHTML = '<table class="table table-hover mb-0 cl-it-list"><thead><tr>'
                    + '<th style="width:44px"><input type="checkbox" data-all title="تحديد الكل"></th>'
                    + '<th style="width:44px">#</th><th>العميل</th>'
                    + '<th>' + (what() === 'balance' ? 'الرصيد المُسلَّم' : 'فواتيره المُسلَّمة') + '</th>'
                    + '<th>' + (what() === 'balance' ? 'مستحق فواتيره' : 'رصيد حسابه') + '</th>'
                    + '</tr></thead><tbody>'
                    + ok.map(function (row, i) {
                        var c = row.client;
                        var other = what() === 'balance' ? c.invoice_due : c.balance;
                        var differs = Math.abs(c.balance - c.invoice_due) > 0.01;
                        return '<tr><td><input type="checkbox" data-row value="' + c.id + '"' + (unchecked[c.id] ? '' : ' checked') + '></td>'
                            + '<td>' + (source.value === 'all' ? i + 1 : c.order) + '</td>'
                            + '<td><strong>' + escapeHtml(c.name) + '</strong>' + (c.code ? ' <small class="text-muted">' + escapeHtml(c.code) + '</small>' : '') + '</td>'
                            + '<td><strong>' + money(row.st.amount) + '</strong>'
                            + (what() === 'invoices' ? ' <small class="text-muted">(' + c.my_invoice_ids.length + ' فاتورة)</small>' : '') + '</td>'
                            + '<td class="text-muted">' + money(other) + (differs ? ' <small class="cl-it-diff">فرق ' + money(Math.abs(c.invoice_due - c.balance)) + '</small>' : '') + '</td></tr>';
                    }).join('')
                    + '</tbody></table>';
            }

            skippedBox.hidden = !skipped.length;
            if (skipped.length) {
                skippedBox.querySelector('summary').textContent = 'لن يُسلَّم ' + clientsCount(skipped.length) + ' — اضغط لرؤية السبب';
                skippedBox.querySelector('ul').innerHTML = skipped.map(function (row) {
                    return '<li><strong>' + escapeHtml(row.client.name) + '</strong> — ' + escapeHtml(row.st.why) + '</li>';
                }).join('');
            }

            refresh();
        }

        function picked() {
            var ids = Array.prototype.map.call(list.querySelectorAll('input[data-row]:checked'), function (box) { return Number(box.value); });
            return clients.filter(function (c) { return ids.indexOf(c.id) !== -1 && state(c).ok; });
        }

        function refresh() {
            var rows = picked();
            var total = rows.reduce(function (sum, c) { return sum + Number(state(c).amount); }, 0);
            var all = list.querySelector('[data-all]');
            if (all) {
                var boxes = list.querySelectorAll('input[data-row]');
                all.checked = boxes.length > 0 && Array.prototype.every.call(boxes, function (box) { return box.checked; });
            }

            submit.disabled = !(rows.length && receiver.value);

            if (!receiver.value) { summary.textContent = 'اختر الموظف المستلِم.'; return; }
            if (!rows.length) { summary.textContent = 'لم تحدّد أيّ عميل.'; return; }

            var who = receiver.options[receiver.selectedIndex].text.trim();
            summary.innerHTML = 'ستُسلِّم <b>' + (what() === 'balance' ? 'أرصدة ' : 'فواتير ') + escapeHtml(clientsCount(rows.length)) + '</b>'
                + ' بإجمالي <b>' + money(total) + '</b> إلى <b>' + escapeHtml(who) + '</b>'
                + (REQUIRES_APPROVAL ? ' — تبقى باسمك حتى يستلمها' : ' — تصير باسمه فوراً');
        }

        function load() {
            var token = ++loadToken;
            list.innerHTML = '<div class="cl-it-picker__state">جارٍ التحميل…</div>';
            skippedBox.hidden = true;
            var url = ROUTES.sendable + '?source=' + encodeURIComponent(source.value)
                + (receiver.value ? '&receiver_id=' + encodeURIComponent(receiver.value) : '');

            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (token !== loadToken) { return; }
                    clients = (data && data.clients) || [];
                    render();
                })
                .catch(function () { list.innerHTML = '<div class="cl-it-picker__state">تعذّر التحميل — أعد المحاولة.</div>'; });
        }

        source.addEventListener('change', function () { unchecked = {}; load(); });
        receiver.addEventListener('change', load);
        form.querySelectorAll('input[name="__what"]').forEach(function (radio) { radio.addEventListener('change', render); });
        list.addEventListener('change', function (event) {
            if (event.target.hasAttribute('data-all')) {
                list.querySelectorAll('input[data-row]').forEach(function (box) {
                    box.checked = event.target.checked;
                    unchecked[box.value] = !box.checked;
                });
            } else if (event.target.hasAttribute('data-row')) {
                unchecked[event.target.value] = !event.target.checked;
            }
            refresh();
        });

        document.getElementById('clItNewBtn').addEventListener('click', function () {
            form.reset();
            unchecked = {};
            load();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var rows = picked();
            if (!rows.length || !receiver.value) { return; }

            var balance = what() === 'balance';
            var payload = { to_employee_id: receiver.value, sender_notes: form.sender_notes.value };
            if (balance) {
                payload.client_ids = rows.map(function (c) { return c.id; });
            } else {
                payload.invoice_ids = [].concat.apply([], rows.map(function (c) { return c.my_invoice_ids; }));
            }

            submit.disabled = true;
            send(balance ? ROUTES.balance : ROUTES.store, 'POST', payload).then(function (result) {
                submit.disabled = false;
                if (result.ok && result.body.success) {
                    notify('success', result.body.message || 'تم التسليم');
                    history.replaceState(null, '', location.pathname + location.search + '#sent');
                    reload();
                    return;
                }
                notify('error', errorOf(result));
            }).catch(function () {
                submit.disabled = false;
                notify('error', 'تعذّر الاتصال بالخادم');
            });
        });
    })();

    // ═══════════ «استلام»: النزول إلى ما ينتظرني، و«استلام الكل» ═══════════
    var receiveCard = document.getElementById('clItReceiveBtn');
    if (receiveCard) {
        receiveCard.addEventListener('click', function (event) {
            event.preventDefault();
            var box = document.getElementById('clItAwaiting');
            box.scrollIntoView({ behavior: 'smooth', block: 'start' });
            box.classList.add('is-flash');
            setTimeout(function () { box.classList.remove('is-flash'); }, 1600);
        });
    }

    var receiveAll = document.getElementById('clItReceiveAll');
    if (receiveAll) {
        receiveAll.addEventListener('click', function () {
            var run = function () {
                receiveAll.disabled = true;
                send(ROUTES.receiveAll, 'POST', {}).then(function (result) {
                    receiveAll.disabled = false;
                    if (result.ok && result.body.success) { notify('success', result.body.message); reload(); return; }
                    notify('error', errorOf(result));
                }).catch(function () { receiveAll.disabled = false; notify('error', 'تعذّر الاتصال بالخادم'); });
            };
            var text = 'ستُستلَم كلُّ الفواتير والأرصدة التي سلّمها لك زملاؤك وتصير باسمك، وتُسجَّل ملاحظةٌ على كلّ عميل.';
            if (window.Swal) {
                Swal.fire({ title: 'استلام الكل؟', text: text, icon: 'question', showCancelButton: true, confirmButtonText: 'نعم، استلم الكل', cancelButtonText: 'تراجع' })
                    .then(function (choice) { if (choice.isConfirmed) { run(); } });
                return;
            }
            if (confirm(text)) { run(); }
        });
    }

    // ═══════════ القبول والرفض ═══════════
    document.querySelectorAll('.cl-it-respond').forEach(function (button) {
        /* يسبق هذا المستمعُ فتحَ النافذة: مستمع بوتستراب مفوَّضٌ على
           المستند فيصل إليه الحدث بعد مستمع الزرّ نفسه — فالحقول مملوءةٌ
           قبل أن تُعرض النافذة */
        button.addEventListener('click', function () {
            var form = document.getElementById('clItRespondForm');
            if (!form) { return; }
            var reject = button.dataset.mode === 'reject';
            var isRequest = button.dataset.request === '1';
            var code = button.dataset.code || '';

            form.transfer_id.value = button.dataset.id || '';
            form.batch.value = button.dataset.batch || '';
            form.mode.value = button.dataset.mode;
            form.receiver_notes.value = '';

            /* «سببٌ مكتوب مطلوب» إعدادٌ يسري على الرفض وحده — القبول
               يمرّ بملاحظةٍ اختيارية، تماماً كما يتحقّق الخادم */
            form.receiver_notes.required = reject && REASON_REQUIRED;

            /* في طلب الاستلام يردّ صاحبُ العهدة: قبولُه تسليمٌ منه إلى الطالب */
            document.getElementById('clItRespondTitle').textContent = isRequest
                ? (reject ? 'رفض طلب الاستلام' : 'الموافقة على طلب الاستلام')
                : (reject ? 'رفض الاستلام' : 'تأكيد الاستلام');
            var subject = button.dataset.subject || ('الفاتورة ' + code);
            document.getElementById('clItRespondLead').textContent = isRequest
                ? (reject
                    ? 'سترفض طلب استلام ' + subject + ' — تبقى في عهدة صاحبها.'
                    : 'ستنتقل عهدة ' + subject + ' إلى ' + (button.dataset.to || 'الطالب') + '.')
                : (reject
                    ? 'سترفض استلام ' + subject + ' — تبقى العهدة مع المرسِل، وتُكتب ملاحظةٌ على العميل.'
                    : 'ستنتقل عهدة ' + subject + ' إلى ' + (button.dataset.to || 'المستلِم') + '.');
            document.getElementById('clItRespondReq').classList.toggle('d-none', !(reject && REASON_REQUIRED));
            document.getElementById('clItRespondHint').textContent = reject && REASON_REQUIRED
                ? 'سببُ الرفض مطلوبٌ في إعدادات وحدات العملاء.'
                : 'ملاحظةٌ اختيارية تُسجَّل في سجلّ العميل.';

            var submit = document.getElementById('clItRespondSubmit');
            submit.className = 'btn ' + (reject ? 'btn-danger' : 'btn-success');
            submit.textContent = reject ? 'رفض' : 'قبول';
        });
    });

    var respondForm = document.getElementById('clItRespondForm');

    if (respondForm) {
        respondForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var reject = respondForm.mode.value === 'reject';
            // دفعةٌ كاملة («استلام الكل») أو تحويلٌ واحد
            var batch = respondForm.batch.value;
            var url = batch
                ? ROUTES.batch.replace('__BATCH__', batch)
                : (reject ? ROUTES.reject : ROUTES.accept).replace('__ID__', respondForm.transfer_id.value);
            var button = document.getElementById('clItRespondSubmit');

            button.disabled = true;

            send(url, 'POST', { receiver_notes: respondForm.receiver_notes.value, mode: respondForm.mode.value }).then(function (result) {
                button.disabled = false;

                if (result.ok && result.body.success) {
                    notify('success', result.body.message || 'تم الحفظ');
                    reload();
                    return;
                }

                notify('error', errorOf(result));
            }).catch(function () {
                button.disabled = false;
                notify('error', 'تعذّر الاتصال بالخادم');
            });
        });
    }

    // ═══════════ إلغاء طلب مرسَل ═══════════
    document.querySelectorAll('.cl-it-cancel').forEach(function (button) {
        button.addEventListener('click', function () {
            var code = button.dataset.code || '';

            var run = function () {
                send(ROUTES.cancel.replace('__ID__', button.dataset.id), 'DELETE', null).then(function (result) {
                    if (result.ok && result.body.success) {
                        notify('success', result.body.message || 'تم الإلغاء');
                        reload();
                        return;
                    }

                    notify('error', errorOf(result));
                }).catch(function () {
                    notify('error', 'تعذّر الاتصال بالخادم');
                });
            };

            if (window.Swal) {
                Swal.fire({
                    title: 'إلغاء طلب التسليم؟',
                    text: 'سيُحذف طلب تسليم الفاتورة ' + code + ' ويُخطَر المستلِم بالإلغاء.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، ألغِ الطلب',
                    cancelButtonText: 'تراجع'
                }).then(function (choice) { if (choice.isConfirmed) { run(); } });
                return;
            }

            if (confirm('إلغاء طلب تسليم الفاتورة ' + code + '؟')) { run(); }
        });
    });
})();
</script>
@endsection
