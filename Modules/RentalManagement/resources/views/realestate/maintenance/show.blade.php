@extends('rentalmanagement::realestate.master')

@section('title', 'طلب الصيانة '.$item->code)

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        use Modules\RentalManagement\Support\RealEstateMoney;
        // التقديرات والإجماليات بعملة النظام، وكل تكلفةٍ بعملتها المسجَّلة
        $money = fn ($v, $currency = null) => RealEstateMoney::format($v, $currency);
        $bearerLabel = match (true) {
            $item->charge_to === 'tenant' => 'المستأجر'.($chargedOwner ? ' (يدفعها المالك '.$chargedOwner->name.' ثم تُستردّ له)' : ' (تدفعها المنشأة ثم تُستردّ لها)'),
            (bool) $chargedOwner => 'المالك '.$chargedOwner->name,
            $item->charge_to === 'owner' => 'المنشأة (المبنى مملوك لها أو بلا مالك)',
            default => 'المنشأة',
        };
        $closed = in_array($item->status, ['completed', 'cancelled'], true);
        $costsTotal = (float) $item->costs->sum('amount');
        $flow = ['open', 'waiting', 'in_progress', 'completed'];
        $statusUrl = route('realestate.maintenance.status', $item->id);
        $hours = $item->completed_at ? round(($item->started_at ?? $item->created_at)->diffInMinutes($item->completed_at) / 60, 1) : null;
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.maintenance.assign')
                @unless ($closed)
                    @if ($item->status !== 'waiting')
                        <button type="button" class="btn btn-outline-warning re-action" data-url="{{ $statusUrl }}" data-method="POST" data-reload="1"
                                data-payload='{"status":"waiting"}'><i class="feather icon-clock"></i> بانتظار</button>
                    @endif
                    @if ($item->status !== 'in_progress')
                        <button type="button" class="btn btn-outline-primary re-action" data-url="{{ $statusUrl }}" data-method="POST" data-reload="1"
                                data-payload='{"status":"in_progress"}'><i class="feather icon-play"></i> بدء التنفيذ</button>
                    @endif
                    <button type="button" class="btn btn-success" data-toggle="modal" data-bs-toggle="modal" data-target="#completeModal" data-bs-target="#completeModal">
                        <i class="feather icon-check-circle"></i> إغلاق كمكتمل
                    </button>
                    <button type="button" class="btn btn-outline-danger re-action" data-url="{{ $statusUrl }}" data-method="POST" data-reload="1" data-tone="danger"
                            data-payload='{"status":"cancelled"}' data-confirm="إلغاء طلب الصيانة «{{ $item->code }}»؟ لا يُعاد فتحه بعد الإلغاء.">
                        <i class="feather icon-slash"></i> إلغاء الطلب
                    </button>
                @endunless
                @if ($item->status === 'completed')
                    <button type="button" class="btn btn-outline-primary re-action" data-url="{{ $statusUrl }}" data-method="POST" data-reload="1"
                            data-payload='{"status":"in_progress"}' data-confirm="إعادة فتح طلب الصيانة «{{ $item->code }}» إلى «قيد التنفيذ»؟"><i class="feather icon-rotate-ccw"></i> إعادة فتح</button>
                @endif
            @endpermission
            @permission('realestate.maintenance.edit')
                @unless ($closed)
                    <a href="{{ route('realestate.maintenance.edit', $item->id) }}" class="btn btn-primary"><i class="feather icon-edit"></i> تعديل</a>
                @endunless
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => $item->title,
            'icon' => V::MAINTENANCE_CATEGORIES[$item->category]['icon'] ?? 'tool',
            'sub' => $item->code.' · '.$item->category_label.' · '.$item->location_label,
            'crumbs' => ['الرئيسية' => url('/'), 'طلبات الصيانة' => route('realestate.maintenance.index'), $item->code => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        <div class="stk-metrics mb-2">
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value"><span class="badge badge-light-{{ $item->status_meta['tone'] }}">{{ $item->status_meta['label'] }}</span>
                    @if ($item->isLate()) <span class="badge badge-light-danger">متأخر</span> @endif</span><span class="stk-metric__label">الحالة</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-alert-triangle"></i></span>
                <span class="stk-metric__value"><span class="badge badge-light-{{ $item->priority_meta['tone'] }}">{{ $item->priority_meta['label'] }}</span></span><span class="stk-metric__label">الأولوية</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-{{ V::MAINTENANCE_CATEGORIES[$item->category]['icon'] ?? 'tool' }}"></i></span>
                <span class="stk-metric__value">{{ $item->category_label }}</span><span class="stk-metric__label">التصنيف</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-map-pin"></i></span>
                <span class="stk-metric__value">
                    @if ($item->unit && hasPermission('realestate.units.show'))
                        <a href="{{ route('realestate.units.show', $item->unit_id) }}">{{ $item->location_label }}</a>
                    @elseif (! $item->unit && $item->building && hasPermission('realestate.buildings.show'))
                        <a href="{{ route('realestate.buildings.show', $item->building_id) }}">{{ $item->location_label }}</a>
                    @else
                        {{ $item->location_label }}
                    @endif
                </span><span class="stk-metric__label">الموقع · <span class="text-{{ $item->scope_meta['tone'] }}">{{ $item->scope_meta['label'] }}</span></span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-user-check"></i></span>
                <span class="stk-metric__value">{{ $item->assignee->name ?? 'غير مُسنَد' }}</span><span class="stk-metric__label">المسؤول</span></div>
        </div>

        {{-- مسار الحالة للعرض فقط — الانتقال من أزرار الترويسة. والملغى خارج المسار فلا تُضاء خطوة --}}
        <div class="re-steps" aria-label="مسار الطلب">
            @foreach ($flow as $step)
                <div class="re-steps__item {{ $item->status === $step ? 'is-active' : '' }}" style="cursor:default" @if ($item->status === $step) aria-current="step" @endif>
                    {{ V::label(V::MAINTENANCE_STATUSES, $step) }}
                </div>
            @endforeach
        </div>
        @if ($item->status === 'cancelled')
            <div class="alert alert-secondary"><div class="alert-body"><i class="feather icon-slash"></i> أُلغي هذا الطلب — لا إجراءات متاحة عليه.</div></div>
        @endif

        <div class="row">
            <div class="col-lg-7 col-12">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> التفاصيل</h4></div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>وصف المشكلة</dt><dd style="white-space:pre-line">{{ $item->description ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>المبلِّغ</dt><dd>{{ $item->reporter->display_name ?? '—' }}</dd></div>
                            @if ($item->contract)
                                <div class="stk-def__row"><dt>العقد</dt>
                                    <dd>
                                        @if (hasPermission('realestate.contracts.show'))
                                            <a href="{{ route('realestate.contracts.show', $item->contract->id) }}" class="stk-num">{{ $item->contract->code }}</a>
                                        @else
                                            <span class="stk-num">{{ $item->contract->code }}</span>
                                        @endif
                                        @if ($item->contract->tenant) — {{ $item->contract->tenant->display_name }} @endif
                                    </dd>
                                </div>
                            @endif
                            <div class="stk-def__row"><dt>المقاول / المورّد</dt><dd>{{ $item->contractor->trade_name ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>موعد الزيارة</dt><dd class="stk-num {{ $item->isLate() ? 'text-danger' : '' }}">{{ $item->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>بدء التنفيذ</dt><dd class="stk-num">{{ $item->started_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>الإنجاز</dt><dd class="stk-num">{{ $item->completed_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                            @if ($hours !== null)
                                <div class="stk-def__row"><dt>مدّة التنفيذ</dt><dd class="stk-num">{{ $hours }} ساعة</dd></div>
                            @endif
                            <div class="stk-def__row"><dt>التكلفة التقديرية</dt><dd class="re-num">{{ $money($item->estimated_cost) }}</dd></div>
                            <div class="stk-def__row"><dt>التكلفة الفعلية</dt>
                                <dd class="re-num {{ (float) $item->estimated_cost > 0 && (float) $item->actual_cost > (float) $item->estimated_cost ? 'text-danger' : '' }}">{{ $money($item->actual_cost) }}</dd></div>
                            <div class="stk-def__row"><dt>التكلفة على</dt><dd>{{ V::label(V::CHARGE_TO, $item->charge_to) }} <small class="text-muted d-block">تُقيَّد على: {{ $bearerLabel }}</small></dd></div>
                            <div class="stk-def__row"><dt>فُتح بواسطة</dt><dd>{{ $item->creator->name ?? '—' }} <small class="text-muted stk-num">· {{ $item->created_at?->format('Y-m-d H:i') }}</small></dd></div>
                            @if ($item->resolution_notes)
                                <div class="stk-def__row"><dt>ملاحظات الإنجاز</dt><dd style="white-space:pre-line">{{ $item->resolution_notes }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-paperclip"></i> المرفقات</h4></div>
                    <div class="card-body">
                        @include('rentalmanagement::realestate.components.file-list', ['paths' => $item->filePaths('attachments')])
                    </div>
                </div>
            </div>

            <div class="col-lg-5 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-dollar-sign"></i> التكاليف <span class="stk-count">{{ $item->costs->count() }}</span></h6>
                    </div>
                    @if ($item->costs->isEmpty())
                        <p class="text-muted p-2 mb-0">لا تكاليف مسجّلة على هذا الطلب.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>البيان</th><th>التاريخ</th><th>دُفعت من</th><th class="text-end">المبلغ</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($item->costs as $cost)
                                        <tr>
                                            <td>{{ $cost->description }}
                                                @if ($cost->schedule) <small class="d-block text-{{ $cost->schedule->status === 'paid' ? 'success' : 'warning' }}">قسط على المستأجر: {{ $cost->schedule->status_meta['label'] }}</small> @endif</td>
                                            <td class="stk-num">{{ $cost->cost_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td><small>{{ $cost->paid_from_label }}</small>
                                                @if ($cost->journalEntry)
                                                    <small class="d-block text-success"><i class="feather icon-check-circle"></i> قيد
                                                        @if (hasPermission('account.journal.view') && Route::has('journal.show'))
                                                            <a href="{{ route('journal.show', $cost->journal_entry_id) }}" target="_blank">{{ $cost->journalEntry->reference_number }}</a>
                                                        @else {{ $cost->journalEntry->reference_number }} @endif
                                                    </small>
                                                @else
                                                    <small class="d-block text-danger"><i class="feather icon-alert-triangle"></i> بلا قيد محاسبي</small>
                                                    @if (hasPermission('realestate.maintenance.costs') && $item->status !== 'cancelled')
                                                        {{-- تكلفةٌ قديمة سُجّلت قبل إلزام القيد: تُقيَّد الآن باختيار من أين دُفعت --}}
                                                        <form method="POST" action="{{ route('realestate.maintenance.costs.post', [$item->id, $cost->id]) }}" class="mt-50 d-flex" style="gap:.25rem">
                                                            @csrf
                                                            <input type="hidden" name="paid_from" value="treasury">
                                                            <select name="treasury_account_id" class="form-control form-control-sm" required aria-label="الخزينة التي دُفعت منها">
                                                                <option value="">دُفعت من…</option>
                                                                @foreach ($treasuries as $treasury) <option value="{{ $treasury->id }}">{{ $treasury->name }}</option> @endforeach
                                                            </select>
                                                            <button class="btn btn-sm btn-outline-primary text-nowrap">قيِّد الآن</button>
                                                        </form>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="text-end re-num">{{ $money($cost->amount, $cost->currency) }}</td>
                                            <td class="text-end">
                                                @permission('realestate.maintenance.costs')
                                                    @if ($item->status !== 'cancelled')
                                                        {{-- نموذجٌ عادي: المتحكّم يردّ `back()` لا JSON. الحذف يعكس القيد --}}
                                                        <form method="POST" action="{{ route('realestate.maintenance.costs.destroy', [$item->id, $cost->id]) }}" class="m-0"
                                                              onsubmit="return confirm('حذف هذه التكلفة وعكس قيدها؟')">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-sm btn-link text-danger p-0" aria-label="حذف {{ $cost->description }}"><i class="feather icon-x"></i></button>
                                                        </form>
                                                    @endif
                                                @endpermission
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr><th colspan="3">الإجمالي</th><th class="text-end re-num">{{ $money($costsTotal) }}</th><th></th></tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                    @permission('realestate.maintenance.costs')
                        @if ($closed)
                            <p class="text-muted p-2 mb-0 border-top"><i class="feather icon-lock"></i> الطلب مغلق — لا تُضاف عليه تكاليف.</p>
                        @else
                            <form method="POST" action="{{ route('realestate.maintenance.costs.store', $item->id) }}" class="p-2 border-top" novalidate data-re-validate>
                                @csrf
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="cost_description" class="stk-req">بيان التكلفة</label>
                                            <input type="text" id="cost_description" name="description" class="form-control" data-required="1" maxlength="191" value="{{ old('description') }}" placeholder="قطع غيار، أجرة فنّي…">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="cost_amount" class="stk-req">المبلغ ({{ \Modules\RentalManagement\Support\RealEstateMoney::code() }})</label>
                                            <input type="number" step="0.01" min="0.01" id="cost_amount" name="amount" class="form-control" data-required="1" value="{{ old('amount') }}">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="cost_date">التاريخ</label>
                                            <input type="date" id="cost_date" name="cost_date" class="form-control" max="{{ now()->format('Y-m-d') }}" value="{{ old('cost_date', now()->format('Y-m-d')) }}">
                                        </div>
                                    </div>
                                </div>
                                {{-- لكل تكلفةٍ قيد: المدين من يتحمّلها، والدائن الخزينة أو ذمة المقاول --}}
                                <div class="alert alert-warning py-50">
                                    <i class="feather icon-info"></i> تُقيَّد التكلفة على: <strong>{{ $bearerLabel }}</strong>.
                                    @if ($item->charge_to === 'tenant')
                                        ويُضاف قسط «صيانة» بالمبلغ إلى استحقاقات عقد المستأجر{{ $item->contract ? ' '.$item->contract->code : '' }} ليُحصَّل منه.
                                        @unless ($item->contract) <strong class="text-danger d-block">لا عقد مرتبط بالطلب — لن تُقبل التكلفة.</strong> @endunless
                                    @endif
                                </div>
                                <div class="form-group">
                                    <label for="cost_paid_from" class="stk-req">دُفعت من</label>
                                    <select id="cost_paid_from" name="paid_from" class="form-control">
                                        <option value="treasury" @selected(old('paid_from', 'treasury') === 'treasury')>خزينة / حساب بنكي (نقداً)</option>
                                        @if ($supplierAccount)
                                            <option value="supplier" @selected(old('paid_from') === 'supplier')>ذمة المقاول {{ $item->contractor->trade_name ?? '' }} (آجل)</option>
                                        @endif
                                    </select>
                                    @if ($item->contractor_supplier_id && ! $supplierAccount)
                                        <small class="text-muted">المقاول المختار لا حساب له في الدليل، فلا تُقيَّد عليه تكلفةٌ آجلة.</small>
                                    @endif
                                </div>
                                <div class="form-group" id="costTreasuryWrap">
                                    <label for="cost_treasury" class="stk-req">الخزينة / البنك الذي دُفعت منه</label>
                                    <select id="cost_treasury" name="treasury_account_id" class="form-control">
                                        <option value="">اختر…</option>
                                        @foreach ($treasuries as $treasury) <option value="{{ $treasury->id }}" @selected((string) old('treasury_account_id') === (string) $treasury->id)>{{ $treasury->name }}</option> @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="feather icon-plus"></i> إضافة تكلفة وقيدها</button>
                                <small class="text-muted d-block mt-50">التكلفة الفعلية للطلب = مجموع هذه البنود. لا تُسجَّل تكلفةٌ بلا قيد، ولا بتاريخٍ مستقبلي.</small>
                            </form>
                        @endif
                    @endpermission
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4 class="card-title"><i class="feather icon-activity"></i> سجل النشاط</h4></div>
            <div class="card-body">
                @include('rentalmanagement::realestate.components.timeline', ['logs' => $logs])
            </div>
        </div>
    </div>

    @permission('realestate.maintenance.assign')
        @unless ($closed)
            <div class="modal fade" id="completeModal" tabindex="-1" aria-labelledby="completeModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form id="completeForm" class="modal-content" novalidate>
                        <div class="modal-header">
                            <h5 class="modal-title" id="completeModalLabel"><i class="feather icon-check-circle"></i> إغلاق الطلب كمكتمل</h5>
                            <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none" id="completeErrors" role="alert"><div class="alert-body"></div></div>
                            <div class="form-group">
                                <label for="resolution_notes" class="stk-req">ما الذي أُنجز؟</label>
                                <textarea id="resolution_notes" name="resolution_notes" rows="4" class="form-control" maxlength="3000" placeholder="وصف الإصلاح وما استُبدل…"></textarea>
                            </div>
                            <div class="form-group mb-0">
                                <label for="actual_cost">التكلفة الفعلية ({{ \Modules\RentalManagement\Support\RealEstateMoney::code() }})</label>
                                <input type="number" step="0.01" min="0" max="{{ $costsTotal }}" id="actual_cost" name="actual_cost" class="form-control" value="{{ $costsTotal > 0 ? $costsTotal : '' }}" readonly>
                                <small class="text-muted">تساوي مجموع بنود التكاليف المقيَّدة. إن زادت التكلفة فأضف الفرق من «إضافة تكلفة» قبل الإغلاق.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                            <button type="submit" class="btn btn-success" id="completeSubmit"><i class="feather icon-check"></i> إغلاق الطلب</button>
                        </div>
                    </form>
                </div>
            </div>
        @endunless
    @endpermission
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            $('#cost_paid_from').on('change', function () {
                $('#costTreasuryWrap').toggle(this.value !== 'supplier');
            }).trigger('change');

            $('#completeForm').on('submit', function (e) {
                e.preventDefault();

                const $errors = $('#completeErrors').addClass('d-none');
                const $submit = $('#completeSubmit').prop('disabled', true);

                $.ajax({ url: @json($statusUrl), method: 'POST', headers: { Accept: 'application/json' },
                    data: { _token: @json(csrf_token()), status: 'completed', resolution_notes: $('#resolution_notes').val(), actual_cost: $('#actual_cost').val() } })
                    .done(() => window.location.reload())
                    .fail(function (xhr) {
                        const body = xhr.responseJSON || {};
                        const lines = body.errors ? Object.values(body.errors).flat() : [body.message || 'حدث خطأ غير متوقّع'];

                        $errors.removeClass('d-none').find('.alert-body').html(lines.map(l => $('<div>').text(l).html()).join('<br>'));
                        $submit.prop('disabled', false);
                    });
            });
        });
    </script>
@endsection
