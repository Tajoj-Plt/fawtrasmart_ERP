@extends('hr::master')

@section('title')
    تحصيل قسط سلفة
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('ancestor.show', $id) }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="payInstallmentForm" class="btn btn-primary">
            <i class="feather icon-save"></i> تسجيل التحصيل
        </button>
    @endsection

    @include('stock::components.page-head', [
        'title' => 'تحصيل قسط — السلفة ' . ($salaryAdvance->code ?? '#' . $id),
        'icon' => 'download',
        'sub' => 'قيد: مدين الخزينة / دائن حساب سلف الموظف — ' . ($salaryAdvance->employee->full_name ?? ''),
        'crumbs' => ['إدارة السلف' => route('hr.advance-approvals.index'), 'السلفة' => route('ancestor.show', $id), 'تحصيل قسط' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        @permission('loans.settle')
            <form action="{{ route('salary-advance.store-payments', $id) }}" method="POST" id="payInstallmentForm">
                @csrf
                <div class="card">
                    <div class="card-body">
                        @if ($InstallmentPayments->isEmpty())
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-check-circle"></i></div>
                                <h6 class="stk-empty__title">لا أقساط مستحقّة</h6>
                                <p class="stk-empty__hint">سُدِّدت أقساط هذه السلفة كلُّها.</p>
                            </div>
                        @else
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="installmentId" class="stk-req">القسط</label>
                                        <select name="installmentId" id="installmentId" class="form-control hr-s2" required>
                                            <option value="">اختر القسط</option>
                                            @foreach ($InstallmentPayments as $installment)
                                                <option value="{{ $installment->id }}" @selected((string) old('installmentId') === (string) $installment->id)>
                                                    #{{ $installment->installment_number }} — {{ \App\Support\CurrencySettings::format($installment->amount) }} — {{ $installment->due_date }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="payment_date" class="stk-req">تاريخ الدفع</label>
                                        <input type="date" id="payment_date" name="payment_date" class="form-control"
                                               value="{{ old('payment_date', date('Y-m-d')) }}" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="treasury_id">الخزينة المستلِمة
                                            <i class="feather icon-help-circle stk-tip" title="الافتراضي خزينة الموظف أو «الخزينة الرئيسية» من إعدادات الحسابات."></i>
                                        </label>
                                        <select name="treasury_id" id="treasury_id" class="form-control hr-s2">
                                            @foreach ($depositTreasuries as $t)
                                                <option value="{{ $t->id }}" @selected((string) old('treasury_id', $mainTreasuryAccount->id ?? '') === (string) $t->id)>{{ $t->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($InstallmentPayments->isNotEmpty())
                        <div class="stk-form-foot">
                            <a href="{{ route('ancestor.show', $id) }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> تسجيل التحصيل
                            </button>
                        </div>
                    @endif
                </div>
            </form>
        @endpermission
    </div>
@endsection
