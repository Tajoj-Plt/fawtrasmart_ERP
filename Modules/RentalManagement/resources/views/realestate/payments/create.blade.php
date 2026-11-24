@extends('rentalmanagement::realestate.master')

@section('title', 'تسجيل دفعة')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        use Modules\RentalManagement\Support\RealEstateSettings;
        use Modules\RentalManagement\Support\RealEstateMoney;
        $invalid = fn (string $field) => $errors->has($field) ? 'is-invalid' : '';
        $posting = RealEstateSettings::enabled('post_payments');
        $advance = RealEstateSettings::enabled('allow_advance_payment');

        // المبنى ثم الوحدة يضيّقان قائمة العقود في الواجهة، واختيار المبنى
        // يقترح خزينته تلقائياً — دون منع تغييرها يدوياً
        $contractsJson = $contracts->map(fn ($item) => [
            'id' => $item->id,
            'code' => $item->code,
            'tenant' => $item->tenant->display_name ?? '',
            'unit_label' => $item->unit->full_label ?? '',
            'unit_id' => $item->unit_id,
            'building_id' => $item->unit->building_id ?? null,
        ])->values();
        $buildingTreasuryJson = $buildings->mapWithKeys(fn ($b) => [$b->id => $b->default_treasury_account_id])->filter();
        $defaultMethod = old('payment_method_id', optional($methods->firstWhere('is_default', true) ?? $methods->firstWhere('kind', 'cash') ?? $methods->first())->id);
    @endphp

    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => 'تسجيل دفعة',
            'icon' => 'credit-card',
            'sub' => 'الدفعة تُسجَّل على استحقاقٍ محدّد، وتقبل السداد الجزئي والتوزيع على أكثر من استحقاق',
            'crumbs' => ['الرئيسية' => url('/'), 'الدفعات' => route('realestate.payments.index'), 'دفعة جديدة' => null],
            'actions' => '<a href="'.route('realestate.payments.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="paymentForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ'.($posting ? ' وترحيل' : '').'</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        @if ($treasuries->isEmpty())
            <div class="alert alert-warning">لا خزينة أو حساب بنكي متاح لك للإيداع — راجع صلاحيات الخزائن في قسم المالية.</div>
        @endif

        <form id="paymentForm" method="POST" action="{{ route('realestate.payments.store') }}" enctype="multipart/form-data" novalidate data-re-validate>
            @csrf
            <div class="row">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-info"></i> بيانات الدفعة</h4>
                            <span class="badge badge-light-secondary stk-num">{{ $code }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="pf_building_id">المبنى / العقار</label>
                                        <select id="pf_building_id" class="form-control select2">
                                            <option value="">كل المباني…</option>
                                            @foreach ($buildings as $building) <option value="{{ $building->id }}">{{ $building->name }}</option> @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="pf_unit_id">الوحدة</label>
                                        <select id="pf_unit_id" class="form-control select2" disabled>
                                            <option value="">اختر العقار أولاً…</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="contract_id" class="stk-req">العقد</label>
                                <select id="contract_id" name="contract_id" class="form-control select2 {{ $invalid('contract_id') }}" data-required="1" data-selected="{{ old('contract_id', $contract?->id) }}">
                                    <option value="">اختر العقد…</option>
                                </select>
                                <small class="text-muted">اختر المبنى ثم الوحدة لتضييق القائمة، أو اختر العقد مباشرةً — تظهر العقود المعتمدة التي عليها استحقاقات مفتوحة فقط.</small>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="payment_date" class="stk-req">تاريخ الدفع الفعلي</label>
                                        <input type="date" id="payment_date" name="payment_date" class="form-control {{ $invalid('payment_date') }}" data-required="1" max="{{ now()->format('Y-m-d') }}" value="{{ old('payment_date', now()->format('Y-m-d')) }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="amount" class="stk-req">المبلغ <small class="text-muted" id="amountCurrency">({{ RealEstateMoney::symbol($contract?->currency) }})</small></label>
                                        <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control {{ $invalid('amount') }}" data-required="1" value="{{ old('amount') }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="payment_method_id" class="stk-req">طريقة الدفع</label>
                                        {{-- من طرق الدفع المعرَّفة في النظام؛ `data-kind` نوعها المشتقّ (لشرط رقم المرجع) --}}
                                        <select id="payment_method_id" name="payment_method_id" class="form-control {{ $invalid('payment_method_id') }}" data-required="1">
                                            @foreach ($methods as $method) <option value="{{ $method->id }}" data-kind="{{ $method->kind }}" @selected((string) $defaultMethod === (string) $method->id)>{{ $method->name }}</option> @endforeach
                                        </select>
                                        @if ($methods->isEmpty()) <small class="text-danger">لا طرق دفع مفعّلة — أضفها من الإعدادات ← طرق الدفع.</small> @endif
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="reference_no" id="referenceLabel">رقم التحويل / الإيصال</label>
                                        <input type="text" id="reference_no" name="reference_no" class="form-control {{ $invalid('reference_no') }}" maxlength="100" dir="ltr" value="{{ old('reference_no') }}">
                                    </div>
                                </div>
                            </div>
                            {{-- الشيك: يُسجَّل في «الشيكات المستلمة» بالحسابات ويُحصَّل من هناك — لا خزينة له عند الاستلام --}}
                            <div id="chequeBox" class="d-none">
                                <div class="row">
                                    <div class="col-sm-6"><div class="form-group"><label for="cheque_due_date">تاريخ استحقاق الشيك</label><input type="date" id="cheque_due_date" name="cheque_due_date" class="form-control" value="{{ old('cheque_due_date') }}"></div></div>
                                    <div class="col-sm-6"><div class="form-group"><label for="cheque_bank">البنك المسحوب عليه</label><input type="text" id="cheque_bank" name="cheque_bank" class="form-control" maxlength="191" value="{{ old('cheque_bank') }}"></div></div>
                                </div>
                                <div class="alert alert-info py-50"><i class="feather icon-info"></i> يُسجَّل الشيك في «الحسابات ← الشيكات المستلمة». تُرحَّل الدفعة ويُقيَّد نصيب المالك عند <strong>تحصيل الشيك</strong> من هناك، وإن ارتُجع أُلغيت الدفعة تلقائياً وعادت الأقساط مفتوحة.</div>
                            </div>
                            <div class="form-group" id="treasuryWrap">
                                <label for="treasury_account_id" class="stk-req">الخزينة / الحساب البنكي المستلم</label>
                                <select id="treasury_account_id" name="treasury_account_id" class="form-control select2 {{ $invalid('treasury_account_id') }}" data-required="1">
                                    <option value="">اختر…</option>
                                    @foreach ($treasuries as $treasury) <option value="{{ $treasury->id }}" @selected((string) old('treasury_account_id') === (string) $treasury->id)>{{ $treasury->name }}</option> @endforeach
                                </select>
                            </div>
                            @include('rentalmanagement::realestate.components.files', ['name' => 'attachments', 'label' => 'إيصال / سند'])
                            <div class="form-group mb-0">
                                <label for="notes">ملاحظات</label>
                                <textarea id="notes" name="notes" rows="2" class="form-control" maxlength="3000">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-calendar"></i> التوزيع على الاستحقاقات</h4>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="autoAllocate"><i class="feather icon-zap"></i> توزيع آلي (الأقدم أولاً)</button>
                        </div>
                        <div class="card-body">
                            <div id="contractSummary" class="stk-metrics mb-1 d-none">
                                <div class="stk-metric"><span class="stk-metric__value" id="csTenant">—</span><span class="stk-metric__label">المستأجر</span></div>
                                <div class="stk-metric"><span class="stk-metric__value" id="csUnit">—</span><span class="stk-metric__label">الوحدة</span></div>
                                <div class="stk-metric"><span class="stk-metric__value stk-num" id="csOutstanding">—</span><span class="stk-metric__label">الرصيد المفتوح</span></div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>#</th><th>النوع</th><th>الاستحقاق</th><th class="text-end">المتبقّي</th><th class="text-center">الحالة</th><th style="width:150px">يُسدَّد الآن</th></tr></thead>
                                    <tbody id="allocRows"><tr><td colspan="6" class="text-center text-muted">اختر العقد لعرض استحقاقاته المفتوحة</td></tr></tbody>
                                </table>
                            </div>

                            <dl class="stk-def mt-1 mb-0">
                                <div class="stk-def__row"><dt>مبلغ الدفعة</dt><dd class="stk-num" id="tAmount">0.00</dd></div>
                                <div class="stk-def__row"><dt>الموزَّع</dt><dd class="stk-num" id="tAllocated">0.00</dd></div>
                                <div class="stk-def__row"><dt>غير موزَّع</dt><dd class="stk-num" id="tLeft">0.00</dd></div>
                            </dl>
                            <div class="alert d-none mt-1 mb-0" id="allocNote"></div>
                            <small class="text-muted d-block mt-1">
                                إن تُرك التوزيع فارغاً وُزّعت الدفعة آلياً على الأقدم استحقاقاً. الدفع الجزئي لا يحوّل الاستحقاق إلى «مدفوع».
                                @if ($posting) عند الحفظ يُنشأ قيد يومية: الخزينة مدينة، والدائن حساب المالك الجاري (للمبنى المُدار) أو إيرادات الإيجار (للمبنى المملوك)، مع الضريبة والتأمين. @else الترحيل المحاسبي معطَّل في الإعدادات — تُسجَّل الدفعة «غير مرحّلة» بلا قيد ولا حركة في دفتر المالك. @endif
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            const url = @json(route('realestate.payments.contract_schedules', ['contractId' => ':id']));
            const preselected = @json($preselected);
            const allowAdvance = @json($advance);
            const oldAlloc = @json(old('allocations', []));
            const fmt = v => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const round = v => Math.round(v * 100) / 100;

            const contracts = @json($contractsJson);
            const buildingTreasury = @json($buildingTreasuryJson);
            const oldTreasury = @json(old('treasury_account_id'));

            const $pfBuilding = $('#pf_building_id'), $pfUnit = $('#pf_unit_id'), $contract = $('#contract_id'), $treasury = $('#treasury_account_id');
            let treasuryAuto = !oldTreasury, settingTreasury = false;

            function fillUnits(selectedUnit) {
                const b = $pfBuilding.val();
                const list = [], seen = new Set();

                contracts.filter(c => !b || String(c.building_id) === String(b)).forEach(function (c) {
                    if (!seen.has(c.unit_id)) { seen.add(c.unit_id); list.push({ id: c.unit_id, label: c.unit_label }); }
                });

                $pfUnit.empty().append(new Option(!b ? 'اختر العقار أولاً…' : (list.length ? 'كل وحدات العقار' : 'لا وحدات لها عقودٌ مفتوحة'), ''));
                list.forEach(u => $pfUnit.append(new Option(u.label, u.id)));
                $pfUnit.prop('disabled', !b)
                    .val(selectedUnit && list.some(u => String(u.id) === String(selectedUnit)) ? String(selectedUnit) : '')
                    .trigger('change');
            }

            function fillContracts(selectedContract) {
                const b = $pfBuilding.val(), u = $pfUnit.val();
                const list = contracts.filter(c => (!b || String(c.building_id) === String(b)) && (!u || String(c.unit_id) === String(u)));

                $contract.empty().append(new Option(list.length ? 'اختر العقد…' : 'لا عقود مطابقة', ''));
                list.forEach(c => $contract.append(new Option(c.code + ' — ' + c.tenant + ' — ' + c.unit_label, c.id)));

                const keep = selectedContract || $contract.data('selected');
                const value = keep && list.some(c => String(c.id) === String(keep)) ? String(keep)
                    : (list.length === 1 ? String(list[0].id) : '');
                $contract.val(value).trigger('change');
            }

            function applyTreasury() {
                if (!treasuryAuto) return;
                const acc = buildingTreasury[$pfBuilding.val()];
                if (acc === undefined || !$treasury.find('option[value="' + acc + '"]').length) return;
                settingTreasury = true;
                $treasury.val(String(acc)).trigger('change');
                settingTreasury = false;
            }

            $treasury.on('change', function () { if (!settingTreasury) treasuryAuto = false; });
            $pfBuilding.on('change', function () { fillUnits(null); applyTreasury(); });
            $pfUnit.on('change', function () { fillContracts(null); });

            // تحديدٌ أوّلي: من رابط «تسجيل دفعة» على عقدٍ بعينه، أو من قيمةٍ سابقة بعد خطأ تحقّق
            const initialContractId = $contract.data('selected');
            const initial = contracts.find(c => String(c.id) === String(initialContractId));

            if (initial) {
                $pfBuilding.val(String(initial.building_id)).trigger('change'); // يملأ الوحدات ويقترح الخزينة
                fillUnits(initial.unit_id); // إعادةٌ لتحديد الوحدة بعينها في القائمة
            } else {
                fillUnits(null); // يهيّئ قائمة الوحدة وقائمة العقود الكاملة
            }

            function totals() {
                const amount = parseFloat($('#amount').val()) || 0;
                let allocated = 0;
                $('.alloc-input').each(function () { allocated += parseFloat(this.value) || 0; });
                allocated = round(allocated);
                const left = round(amount - allocated);

                $('#tAmount').text(fmt(amount));
                $('#tAllocated').text(fmt(allocated));
                $('#tLeft').text(fmt(left)).toggleClass('text-danger', left < 0);

                const $note = $('#allocNote').removeClass('alert-danger alert-warning alert-info').addClass('d-none');

                if (left < 0) $note.removeClass('d-none').addClass('alert-danger').text('الموزَّع يتجاوز مبلغ الدفعة.');
                else if (left > 0 && allocated > 0) $note.removeClass('d-none').addClass(allowAdvance ? 'alert-info' : 'alert-warning')
                    .text(allowAdvance ? 'الباقي يُقيَّد دفعةً مقدّمة على حساب العميل.' : 'يتبقّى مبلغٌ غير موزَّع — الدفعة المقدّمة معطّلة في الإعدادات وسيُرفض الحفظ.');
            }

            function autoAllocate() {
                let left = parseFloat($('#amount').val()) || 0;

                $('.alloc-input').each(function () {
                    const take = round(Math.min(left, parseFloat($(this).data('balance'))));
                    $(this).val(take > 0 ? take.toFixed(2) : '');
                    left = round(left - take);
                });
                totals();
            }

            function load() {
                const id = $('#contract_id').val();
                const $rows = $('#allocRows');

                if (!id) {
                    $('#contractSummary').addClass('d-none');
                    return $rows.html('<tr><td colspan="6" class="text-center text-muted">اختر العقد لعرض استحقاقاته المفتوحة</td></tr>');
                }

                $.getJSON(url.replace(':id', id)).done(function (data) {
                    $('#contractSummary').removeClass('d-none');
                    $('#csTenant').text(data.contract.tenant);
                    $('#csUnit').text(data.contract.unit);
                    $('#csOutstanding').text(fmt(data.contract.outstanding) + ' ' + data.contract.currency_symbol);
                    $('#amountCurrency').text('(' + data.contract.currency_symbol + ')');
                    // لا دفعة قبل بداية العقد
                    $('#payment_date').attr('min', data.contract.start_date || null);

                    if (!data.schedules.length) return $rows.html('<tr><td colspan="6" class="text-center text-muted">لا استحقاقات مفتوحة على هذا العقد</td></tr>');

                    $rows.html(data.schedules.map(function (s) {
                        const value = oldAlloc[s.id] || (String(s.id) === String(preselected) ? s.balance.toFixed(2) : '');
                        return '<tr><td class="stk-num">' + (s.no || '—') + '</td><td>' + s.kind + '</td><td class="stk-num">' + s.due_date + '</td>'
                            + '<td class="text-end re-num">' + fmt(s.balance) + '</td><td class="text-center"><span class="badge badge-light-' + s.status.tone + '">' + s.status.label + '</span></td>'
                            + '<td><input type="number" step="0.01" min="0" max="' + s.balance + '" data-balance="' + s.balance + '" name="allocations[' + s.id + ']" class="form-control form-control-sm alloc-input" value="' + value + '"></td></tr>';
                    }).join(''));

                    // جاء من زرّ «سداد» على قسطٍ بعينه: المبلغ = رصيده
                    if (preselected && !$('#amount').val()) {
                        const hit = data.schedules.find(s => String(s.id) === String(preselected));
                        if (hit) $('#amount').val(hit.balance.toFixed(2));
                    }

                    totals();
                }).fail(() => $rows.html('<tr><td colspan="6" class="text-center text-danger">تعذّر تحميل الاستحقاقات</td></tr>'));
            }

            $('#contract_id').on('change', load);
            $('#autoAllocate').on('click', autoAllocate);
            $('#amount').on('input', totals);
            $(document).on('input', '.alloc-input', function () {
                const max = parseFloat($(this).data('balance'));
                if ((parseFloat(this.value) || 0) > max) this.value = max.toFixed(2);
                totals();
            });

            $('#payment_method_id').on('change', function () {
                const kind = $(this).find('option:selected').data('kind');
                const needs = ['transfer', 'cheque'].includes(kind);
                $('#referenceLabel').toggleClass('stk-req', needs).text(kind === 'cheque' ? 'رقم الشيك' : 'رقم التحويل / الإيصال');
                $('#reference_no').attr('data-required', needs ? '1' : null);
                const cheque = kind === 'cheque';
                $('#chequeBox').toggleClass('d-none', !cheque);
                $('#treasuryWrap').toggleClass('d-none', cheque);
                $('#treasury_account_id').attr('data-required', cheque ? null : '1');
            }).trigger('change');

            load();
        });
    </script>
@endsection
