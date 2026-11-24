@extends('hr::master')

@section('title')
    تعديل جدول السلفة
@stop

@section('content')
    @php
        $rateKey = [1 => 'monthly', 2 => 'weekly', 3 => 'daily'][(int) $ancestor->payment_rate] ?? 'monthly';
    @endphp

    @section('page-actions')
        <a href="{{ route('ancestor.show', $ancestor->id) }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="ancestorEditForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الجدول
        </button>
    @endsection

    @include('stock::components.page-head', [
        'title' => 'تعديل جدول السلفة ' . ($ancestor->code ?? '#' . $ancestor->id),
        'icon' => 'edit-2',
        'sub' => 'يُعاد توليدُ الأقساط كلِّها من تاريخ البدء — المبلغ والموظف لا يُعدَّلان لأن قيد الصرف كُتب بهما',
        'crumbs' => ['إدارة السلف' => route('hr.advance-approvals.index'), 'السلفة' => route('ancestor.show', $ancestor->id), 'تعديل' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        <form action="{{ route('ancestor.update', $ancestor->id) }}" method="POST" id="ancestorEditForm">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-body">
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-lock"></i>
                            <h6 class="stk-section__title">بيانات الصرف (للعرض)</h6>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>الموظف</label>
                                    <input type="text" class="form-control" value="{{ $ancestor->employee->full_name ?? '—' }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>المبلغ
                                        <i class="feather icon-help-circle stk-tip" title="لتصحيح المبلغ: ألغِ السلفة (قيدٌ عكسي) ثم أصدر غيرها."></i>
                                    </label>
                                    <input type="text" class="form-control" id="amount" data-amount="{{ (float) $ancestor->amount }}"
                                           value="{{ \App\Support\CurrencySettings::format($ancestor->amount) }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>الخزينة</label>
                                    <input type="text" class="form-control" value="{{ $ancestor->treasury->name ?? ($treasure->name ?? '—') }}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-calendar"></i>
                            <h6 class="stk-section__title">جدول الأقساط</h6>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="installment_amount" class="stk-req">قيمة القسط</label>
                                    <input type="number" class="form-control" id="installment_amount" name="installment_amount"
                                           step="0.01" min="0.01" max="{{ (float) $ancestor->amount }}"
                                           value="{{ old('installment_amount', $ancestor->installment_amount) }}" required>
                                    <small class="stk-help">عدد الأقساط: <strong id="installment_count" class="stk-num">{{ $ancestor->total_installments }}</strong></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="payment_rate" class="stk-req">معدّل السداد</label>
                                    <select name="payment_rate" id="payment_rate" class="form-control hr-s2" required>
                                        <option value="monthly" @selected(old('payment_rate', $rateKey) === 'monthly')>شهري</option>
                                        <option value="weekly" @selected(old('payment_rate', $rateKey) === 'weekly')>أسبوعي</option>
                                        <option value="daily" @selected(old('payment_rate', $rateKey) === 'daily')>يومي</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="installment_start_date" class="stk-req">تاريخ أوّل قسط</label>
                                    <input type="date" class="form-control" id="installment_start_date" name="installment_start_date"
                                           value="{{ old('installment_start_date', optional($ancestor->installment_start_date)->format('Y-m-d')) }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="stk-switch mb-0">
                                        <input type="checkbox" name="pay_from_salary" value="1" @checked(old('pay_from_salary', $ancestor->pay_from_salary))>
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title">الاقتطاع من قسيمة الراتب</span>
                                            <span class="stk-switch__hint">الأقساط المستحقّة تُخصم مع الراتب</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="tag">وسم</label>
                                    <input type="text" class="form-control" id="tag" name="tag" value="{{ old('tag', $ancestor->tag) }}" maxlength="255">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group mb-0">
                                    <label for="note">ملاحظة</label>
                                    <textarea class="form-control" id="note" name="note" rows="3">{{ old('note', $ancestor->note) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <a href="{{ route('ancestor.show', $ancestor->id) }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> حفظ الجدول
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var amount = parseFloat($('#amount').data('amount')) || 0;
            $('#installment_amount').on('input change', function () {
                var each = parseFloat($(this).val()) || 0;
                $('#installment_count').text(each > 0 ? Math.ceil(amount / each) : '—');
            }).trigger('change');
        });
    </script>
@endpush
