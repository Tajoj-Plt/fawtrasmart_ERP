@extends('rentalmanagement::realestate.master')

@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    use Modules\RentalManagement\Support\RealEstateMoney;
    // الإجماليات بعملة النظام، وكل حركةٍ في الكشف بعملتها المسجَّلة
    $money = fn ($v, $currency = null) => RealEstateMoney::format($v, $currency);
    $canCancelTransfer = hasPermission('realestate.owners.transfer');
    $canTransfer = hasPermission('realestate.owners.transfer') && $balance > 0;
    $actions = '';
    if (hasPermission('realestate.owners.edit')) {
        $actions .= '<a href="'.route('realestate.owners.edit', $owner->id).'" class="btn btn-outline-secondary"><i class="feather icon-edit-2"></i> تعديل</a>';
    }
    if ($canTransfer) {
        $actions .= '<button type="button" class="btn btn-primary" data-toggle="modal" data-bs-toggle="modal" data-target="#transferModal" data-bs-target="#transferModal"><i class="feather icon-send"></i> تحويل مستحقات</button>';
    }
@endphp

@section('title', 'المالك '.$owner->name)

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $owner->name,
            'icon' => 'user',
            'sub' => $owner->code.' — '.$owner->agreement_label,
            'crumbs' => ['الرئيسية' => url('/'), 'الملّاك' => route('realestate.owners.index'), $owner->name => null],
            'actions' => $actions,
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('rentalmanagement::realestate.components.kpis', ['items' => [
            ['label' => 'رصيد المالك المتاح للتحويل', 'value' => $money($balance), 'icon' => 'briefcase', 'tone' => $balance > 0 ? 'success' : 'secondary', 'note' => 'بعد التحصيل والخصومات'],
            ['label' => 'مستحق على المستأجرين لم يُحصَّل', 'value' => $money($accrued), 'icon' => 'clock', 'tone' => $accrued > 0 ? 'warning' : 'success', 'note' => 'إيجارٌ ليس متاحاً للتحويل بعد'],
            ['label' => 'إيجار محصَّل له', 'value' => $money($totals['rent']), 'icon' => 'trending-up', 'tone' => 'info'],
            ['label' => 'ضريبة محصَّلة لحسابه', 'value' => $money($totals['tax']), 'icon' => 'file-text', 'tone' => 'info', 'note' => 'ضريبة الإيجار التجاري — يورّدها المالك'],
            ['label' => 'عمولة الإدارة المخصومة', 'value' => $money($totals['commission']), 'icon' => 'percent', 'tone' => 'primary',
                'note' => $totals['commission_tax'] > 0 ? 'وضريبتها '.$money($totals['commission_tax']) : null],
            ['label' => 'صيانة محمَّلة عليه', 'value' => $money($totals['maintenance']), 'icon' => 'tool', 'tone' => 'warning'],
            ['label' => 'محوَّل إليه', 'value' => $money($totals['transfer']), 'icon' => 'send', 'tone' => 'secondary'],
        ]])

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-user"></i> بيانات المالك</h4>
                        <span class="badge badge-light-{{ $owner->status_meta['tone'] }}">{{ $owner->status_meta['label'] }}</span></div>
                    <div class="card-body">
                        <dl class="stk-def mb-0">
                            <div class="stk-def__row"><dt>نوع المالك</dt><dd>{{ V::label(V::OWNER_TYPES, $owner->owner_type) }}</dd></div>
                            <div class="stk-def__row"><dt>الجوال</dt><dd class="stk-num" dir="ltr">{{ $owner->mobile ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>هاتف آخر</dt><dd class="stk-num" dir="ltr">{{ $owner->phone ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>البريد</dt><dd dir="ltr">{{ $owner->email ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>الهوية</dt><dd>{{ $owner->id_number ? V::label(V::ID_TYPES, $owner->id_type, 'رقم').': '.$owner->id_number : '—' }}</dd></div>
                            <div class="stk-def__row"><dt>الجنسية</dt><dd>{{ $owner->nationality ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>الحالة الاجتماعية</dt><dd>{{ $owner->marital_status ? V::label(V::MARITAL_STATUSES, $owner->marital_status) : '—' }}</dd></div>
                            <div class="stk-def__row"><dt>العنوان</dt><dd>{{ $owner->address ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>البنك / الآيبان</dt><dd>{{ $owner->bank_name ?: '—' }} <span class="stk-num" dir="ltr">{{ $owner->iban }}</span></dd></div>
                            <div class="stk-def__row"><dt>ضريبة القيمة المضافة</dt><dd>
                                @if ($owner->is_vat_registered)
                                    <span class="badge badge-light-success">مسجَّل</span> <span class="stk-num" dir="ltr">{{ $owner->tax_number ?: '—' }}</span>
                                @else
                                    <span class="badge badge-light-secondary">غير مسجَّل</span> <small class="text-muted">عقوده التجارية بلا ضريبة</small>
                                @endif
                            </dd></div>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-percent"></i> اتفاقية إدارة الأملاك</h4></div>
                    <div class="card-body">
                        <dl class="stk-def mb-1">
                            <div class="stk-def__row"><dt>نوع الاتفاق</dt><dd>{{ V::label(V::AGREEMENT_TYPES, $owner->agreement_type) }}</dd></div>
                            <div class="stk-def__row"><dt>{{ $owner->isPercentage() ? 'النسبة' : 'المبلغ الثابت لكل قسط' }}</dt><dd class="stk-num">{{ $owner->isPercentage() ? rtrim(rtrim(number_format($owner->agreement_value, 2), '0'), '.').'%' : $money($owner->agreement_value) }}</dd></div>
                            <div class="stk-def__row"><dt>أساس الاحتساب</dt><dd>{{ V::label(V::COMMISSION_BASES, $owner->commission_basis) }}</dd></div>
                            <div class="stk-def__row"><dt>الحساب الجاري</dt><dd>@include('rentalmanagement::realestate.components.account-link', ['account' => $owner->account])</dd></div>
                        </dl>
                        <p class="text-muted mb-0" style="font-size:.85rem">
                            ما يُحصَّل من المستأجر عن مبانيه المُدارة يدخل حسابه الجاري لا إيراد المكتب — الإيجار، وضريبته بسطرٍ مستقل — وتُخصم منه عمولة الإدارة (وضريبتها إن فُعّلت) والصيانة المحمَّلة عليه، وما بقي يُحوَّل إليه. كل حركةٍ في كشفه يقابلها قيد يومية على حسابه الجاري.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @if ($owner->filePaths('attachments'))
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-paperclip"></i> مرفقات المالك</h4></div>
                <div class="card-body">@include('rentalmanagement::realestate.components.file-list', ['paths' => $owner->filePaths('attachments')])</div>
            </div>
        @endif

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-home"></i> عقارات المالك <span class="stk-count">{{ $buildings->count() }}</span></h6>
            </div>
            @if ($buildings->isEmpty())
                @include('rentalmanagement::realestate.components.empty', ['icon' => 'home', 'title' => 'لا عقارات', 'hint' => 'اختر هذا المالك في نموذج المبنى ليظهر هنا'])
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>المبنى</th><th>المدينة</th><th class="text-center">الوحدات</th></tr></thead>
                        <tbody>
                            @foreach ($buildings as $b)
                                <tr>
                                    <td>@if (hasPermission('realestate.buildings.show'))<a href="{{ route('realestate.buildings.show', $b->id) }}">{{ $b->name }}</a>@else{{ $b->name }}@endif <small class="text-muted stk-num">{{ $b->code }}</small></td>
                                    <td>{{ $b->city ?: '—' }}</td>
                                    <td class="text-center"><span class="stk-count">{{ $b->units_count }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> كشف حساب المالك</h6>
                <form method="GET" class="form-inline" style="gap:.5rem">
                    <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $filters['from_date'] }}" aria-label="من تاريخ">
                    <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $filters['to_date'] }}" aria-label="إلى تاريخ">
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="feather icon-search"></i></button>
                </form>
            </div>
            @if ($entries->isEmpty() && ! $opening)
                @include('rentalmanagement::realestate.components.empty', ['icon' => 'file-text', 'title' => 'لا حركات بعد', 'hint' => 'تظهر هنا الإيجارات المحصَّلة والعمولة والصيانة والتحويلات'])
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>التاريخ</th><th>العملية</th><th>البيان</th><th class="text-end">له</th><th class="text-end">عليه</th><th class="text-end">الرصيد</th><th></th></tr></thead>
                        <tbody>
                            @if ($filters['from_date'])
                                <tr class="table-light"><td colspan="5">رصيد سابق لـ {{ $filters['from_date'] }}</td><td class="text-end stk-num">{{ $money($opening) }}</td><td></td></tr>
                            @endif
                            @foreach ($entries as $entry)
                                <tr>
                                    <td class="stk-num">{{ $entry->entry_date->format('Y-m-d') }}</td>
                                    <td><span class="badge badge-light-{{ $entry->type_meta['tone'] }}">{{ $entry->type_meta['label'] }}</span></td>
                                    <td>{{ $entry->description }}@if ($entry->building) <small class="text-muted d-block">{{ $entry->building->name }}{{ $entry->unit ? ' — '.$entry->unit->unit_no : '' }}</small>@endif
                                        @if ($entry->reversal) <span class="badge badge-light-danger">ملغاة</span> @endif</td>
                                    <td class="text-end stk-num text-success">{{ $entry->direction === 'credit' ? $money($entry->amount, $entry->currency) : '' }}</td>
                                    <td class="text-end stk-num text-danger">{{ $entry->direction === 'debit' ? $money($entry->amount, $entry->currency) : '' }}</td>
                                    <td class="text-end stk-num">{{ $money($entry->running_balance, $entry->currency) }}</td>
                                    <td class="text-end">
                                        @if ($canCancelTransfer && $entry->type === 'transfer' && ! $entry->reversal)
                                            <button type="button" class="btn btn-sm btn-outline-danger re-action" data-reload="1" data-tone="danger"
                                                    data-url="{{ route('realestate.owners.transfers.cancel', [$owner->id, $entry->id]) }}"
                                                    data-confirm="إلغاء التحويل {{ $entry->reference_no }} وعكس قيده" data-prompt="سبب الإلغاء"
                                                    aria-label="إلغاء التحويل {{ $entry->reference_no }}"><i class="feather icon-rotate-ccw"></i> إلغاء</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($contracts->isNotEmpty())
            <div class="card stk-table-card">
                <div class="stk-table-head"><h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> عقود على عقاراته</h6></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>العقد</th><th>المستأجر</th><th>الوحدة</th><th class="text-center">الحالة</th></tr></thead>
                        <tbody>
                            @foreach ($contracts as $c)
                                <tr>
                                    <td>@if (hasPermission('realestate.contracts.show'))<a class="stk-num" href="{{ route('realestate.contracts.show', $c->id) }}">{{ $c->code }}</a>@else<span class="stk-num">{{ $c->code }}</span>@endif</td>
                                    <td>{{ $c->tenant->display_name ?? '—' }}</td>
                                    <td>{{ $c->unit->full_label ?? '—' }}</td>
                                    <td class="text-center"><span class="badge badge-light-{{ $c->status_meta['tone'] }}">{{ $c->status_meta['label'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($canTransfer)
            <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferTitle" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content re-ajax-form" data-url="{{ route('realestate.owners.transfer', $owner->id) }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="transferTitle">تحويل مستحقات {{ $owner->name }}</h5>
                            <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none re-ajax-errors"></div>
                            <p class="text-muted" style="font-size:.85rem">المتاح للتحويل <strong class="stk-num">{{ $money($balance) }}</strong> — يُقيَّد التحويل: مدين الحساب الجاري للمالك، دائن الخزينة/البنك.</p>
                            <div class="form-group">
                                <label for="transfer_amount" class="stk-req">المبلغ ({{ \Modules\RentalManagement\Support\RealEstateMoney::code() }})</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $balance }}" id="transfer_amount" name="amount" class="form-control" value="{{ $balance }}">
                            </div>
                            <div class="form-group">
                                <label for="transfer_date" class="stk-req">تاريخ التحويل</label>
                                <input type="date" id="transfer_date" name="date" class="form-control" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="form-group">
                                <label for="transfer_treasury" class="stk-req">الخزينة / الحساب البنكي المحوَّل منه</label>
                                <select id="transfer_treasury" name="treasury_account_id" class="form-control">
                                    <option value="">اختر…</option>
                                    @foreach ($treasuries as $treasury) <option value="{{ $treasury->id }}">{{ $treasury->name }}</option> @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="transfer_reference">رقم التحويل / المرجع</label>
                                <input type="text" id="transfer_reference" name="reference_no" class="form-control" maxlength="100">
                            </div>
                            <div class="form-group mb-0">
                                <label for="transfer_notes">ملاحظات</label>
                                <textarea id="transfer_notes" name="notes" rows="2" class="form-control" maxlength="1000"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                            <button type="submit" class="btn btn-primary"><i class="feather icon-send"></i> تحويل</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.ajax-form-scripts')
@endsection
