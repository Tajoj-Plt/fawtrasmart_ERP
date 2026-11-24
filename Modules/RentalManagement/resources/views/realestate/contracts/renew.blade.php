@extends('rentalmanagement::realestate.master')

@section('title', 'تجديد العقد '.$contract->code)

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        $money = fn ($v) => $contract->money($v);
        $canComplete = hasPermission('realestate.contracts.approve_renewal');
    @endphp

    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => 'تجديد العقد '.$contract->code,
            'icon' => 'refresh-cw',
            'sub' => 'التجديد لا يعدّل العقد القديم: ينشئ عقداً جديداً مرتبطاً بسابقه ويبقى التاريخ كما هو',
            'crumbs' => ['الرئيسية' => url('/'), 'العقود' => route('realestate.contracts.index'), $contract->code => route('realestate.contracts.show', $contract->id), 'تجديد' => null],
            'actions' => '<a href="'.route('realestate.contracts.show', $contract->id).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        @if ($pending)
            <div class="alert alert-warning">لهذا العقد طلب تجديدٍ قائم بحالة «{{ $pending->status_meta['label'] }}» — راجِعه من <a href="{{ route('realestate.contracts.show', $contract->id) }}">صفحة العقد</a>.</div>
        @endif

        @if ($outstanding > 0)
            <div class="alert alert-danger"><i class="feather icon-alert-circle"></i> على العقد الحالي رصيدٌ غير مسدَّد قدره <strong class="stk-num">{{ $money($outstanding) }}</strong>. التجديد ممكن، لكنّ الرصيد يبقى ديناً على العقد السابق.</div>
        @endif

        <div class="row">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> العقد الحالي</h4></div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>المستأجر</dt><dd>{{ $contract->tenant->display_name ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>الوحدة</dt><dd>{{ $contract->unit->full_label ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>الفترة</dt><dd class="stk-num">{{ $contract->start_date->format('Y-m-d') }} ← {{ $contract->end_date->format('Y-m-d') }}</dd></div>
                            <div class="stk-def__row"><dt>القيمة السنوية</dt><dd class="stk-num">{{ $money($contract->annual_value) }}</dd></div>
                            <div class="stk-def__row"><dt>دورية السداد</dt><dd>{{ V::label(V::PAYMENT_FREQUENCIES, $contract->payment_frequency) }}</dd></div>
                            <div class="stk-def__row"><dt>التأمين المحتجز</dt><dd class="stk-num">{{ $money($heldDeposit) }} <small class="text-muted d-block">يُرحَّل كاملاً إلى العقد الجديد</small></dd></div>
                            <div class="stk-def__row"><dt>المتبقّي حتى النهاية</dt><dd class="stk-num">{{ $contract->days_left >= 0 ? $contract->days_left.' يوماً' : 'انتهى منذ '.abs($contract->days_left).' يوماً' }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <form method="POST" action="{{ route('realestate.contracts.renew.store', $contract->id) }}" id="renewForm" novalidate data-re-validate class="card">
                    @csrf
                    <input type="hidden" name="action" id="renewAction" value="request">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-edit"></i> شروط التجديد</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_start_date" class="stk-req">بداية التجديد</label>
                                    <input type="date" id="new_start_date" name="new_start_date" class="form-control" data-required="1" min="{{ $defaults['new_start_date'] }}" value="{{ old('new_start_date', $defaults['new_start_date']) }}">
                                    <small class="text-muted">بعد نهاية العقد الحالي ({{ $contract->end_date->format('Y-m-d') }}) — لا يتداخل العقدان.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_end_date" class="stk-req">نهاية التجديد</label>
                                    <input type="date" id="new_end_date" name="new_end_date" class="form-control" data-required="1" value="{{ old('new_end_date', $defaults['new_end_date']) }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="increase_percentage">نسبة الزيادة %</label>
                                    <input type="number" step="0.01" id="increase_percentage" class="form-control" value="{{ $defaults['increase_percentage'] }}">
                                    <small class="text-muted">تحسب القيمة الجديدة، والعكس.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="new_rent_value" class="stk-req">قيمة الإيجار السنوية الجديدة ({{ $contract->currency_code }})</label>
                                    <input type="number" step="0.01" min="0" id="new_rent_value" name="new_rent_value" class="form-control" data-required="1" value="{{ old('new_rent_value', $defaults['new_rent_value']) }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="payment_frequency" class="stk-req">دورية السداد</label>
                                    <select id="payment_frequency" name="payment_frequency" class="form-control">
                                        @foreach (V::PAYMENT_FREQUENCIES as $value => $meta) <option value="{{ $value }}" @selected(old('payment_frequency', $contract->payment_frequency) === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="deposit_adjustment">تعديل التأمين (زيادة مطلوبة) — {{ $contract->currency_code }}</label>
                                    <input type="number" step="0.01" min="0" id="deposit_adjustment" name="deposit_adjustment" class="form-control" value="{{ old('deposit_adjustment', 0) }}">
                                    <small class="text-muted">التأمين المحتجز ({{ $money($heldDeposit) }}) يُرحَّل إلى العقد الجديد؛ تُستحقّ هذه الزيادة وحدها قسطاً عليه.</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="notes">ملاحظات التجديد</label>
                                    <textarea id="notes" name="notes" rows="2" class="form-control" maxlength="3000">{{ old('notes') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end" style="gap:.5rem">
                        <button type="submit" class="btn btn-outline-primary" data-action="request" @disabled($pending)><i class="feather icon-send"></i> إرسال للموافقة</button>
                        @if ($canComplete)
                            <button type="submit" class="btn btn-primary" data-action="complete" @disabled($pending)><i class="feather icon-check"></i> موافقة وإنشاء العقد الجديد</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            const old = {{ (float) $contract->annual_value }};

            $('#increase_percentage').on('input', function () {
                $('#new_rent_value').val((old * (1 + (parseFloat(this.value) || 0) / 100)).toFixed(2));
            });
            $('#new_rent_value').on('input', function () {
                if (old > 0) $('#increase_percentage').val((((parseFloat(this.value) || 0) - old) / old * 100).toFixed(2));
            });
            $('[data-action]').on('click', function () { $('#renewAction').val($(this).data('action')); });
        });
    </script>
@endsection
