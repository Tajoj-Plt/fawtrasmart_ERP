@extends('hr::master')

@section('title')
    إصدار سلفة لموظف
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('hr.advance-approvals.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="directAdvanceForm" class="btn btn-primary">
            <i class="feather icon-save"></i> إصدار وصرف
        </button>
    @endsection

    @include('stock::components.page-head', [
        'title' => 'إصدار سلفة لموظف',
        'icon' => 'arrow-up-circle',
        'sub' => 'اعتمادٌ وصرفٌ في خطوةٍ واحدة: يُكتب قيد الصرف فوراً بعد التحقّق من رصيد الخزينة',
        'crumbs' => ['الرئيسية' => route('hr.department.dashboard'), 'إدارة السلف' => route('hr.advance-approvals.index'), 'إصدار سلفة' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        @if ($employeesWithoutAccount->count() > 0)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                <strong>{{ $employeesWithoutAccount->count() }}</strong> موظفاً بلا حساب سلف في شجرة الحسابات — لا يظهرون في القائمة:
                {{ $employeesWithoutAccount->take(8)->map(fn ($e) => trim($e->first_name.' '.$e->middle_name))->implode('، ') }}{{ $employeesWithoutAccount->count() > 8 ? '…' : '' }}
            </div>
        @endif

        @if (canAll(['loans.create', 'loans.approve', 'loans.disburse']))
            <form action="{{ route('hr.advance-approvals.store') }}" method="POST" id="directAdvanceForm">
                @csrf

                <div class="card">
                    <div class="card-body">
                        <div class="stk-section">
                            <div class="stk-section__head">
                                <i class="feather icon-user"></i>
                                <h6 class="stk-section__title">الموظف والمبلغ</h6>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_id" class="stk-req">الموظف</label>
                                        <select name="employee_id" id="employee_id" class="form-control select2" required>
                                            <option value="">-- اختر الموظف --</option>
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                                                    {{ $employee->full_name }}{{ $employee->employee_code ? ' — '.$employee->employee_code : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount" class="stk-req">المبلغ</label>
                                        <div class="input-group">
                                            <input type="number" name="amount" id="amount" class="form-control"
                                                   value="{{ old('amount') }}" step="0.01" min="1" placeholder="0.00" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text">{{ \App\Support\CurrencySettings::symbol() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="requested_date" class="stk-req">تاريخ السلفة
                                            <i class="feather icon-help-circle stk-tip" title="تاريخ قيد الصرف — لا يُقبل في فترة محاسبية مقفلة."></i>
                                        </label>
                                        <input type="date" name="requested_date" id="requested_date" class="form-control"
                                               value="{{ old('requested_date', date('Y-m-d')) }}" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="treasury_id" class="stk-req">الخزينة
                                            <i class="feather icon-help-circle stk-tip" title="الخزائن التي يُسمح لك بالسحب منها. الرصيد المعروض للتنبيه؛ والقرار يُفحص من الدفتر تحت قفل عند الحفظ — لا صرفَ يجعل الخزينة سالبة."></i>
                                        </label>
                                        <select name="treasury_id" id="treasury_id" class="form-control select2" required>
                                            <option value="">-- اختر الخزينة --</option>
                                            @foreach ($treasuries as $treasury)
                                                <option value="{{ $treasury->id }}" data-balance="{{ (float) $treasury->balance }}"
                                                    @selected((string) old('treasury_id', $defaultTreasuryId) === (string) $treasury->id)>
                                                    {{ $treasury->name }} ({{ \App\Support\CurrencySettings::format($treasury->balance) }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="stk-help" id="treasury-balance-warning"></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="stk-section">
                            <div class="stk-section__head">
                                <i class="feather icon-calendar"></i>
                                <h6 class="stk-section__title">طريقة الاسترداد</h6>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="stk-req">نوع السلفة</label>
                                        <div class="d-flex flex-wrap" style="gap:1rem">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="type_normal" name="advance_type" class="custom-control-input" value="normal" @checked(old('advance_type', 'normal') === 'normal')>
                                                <label class="custom-control-label" for="type_normal">عادية — تُسترَدّ دفعةً واحدة</label>
                                            </div>
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="type_scheduled" name="advance_type" class="custom-control-input" value="scheduled" @checked(old('advance_type') === 'scheduled')>
                                                <label class="custom-control-label" for="type_scheduled">مجدولة — أقساط شهرية من الراتب</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3" id="installments_section" style="display:none">
                                    <div class="form-group">
                                        <label for="installments_count" class="stk-req">عدد الأقساط</label>
                                        <input type="number" name="installments_count" id="installments_count" class="form-control"
                                               value="{{ old('installments_count', 2) }}" min="2" max="24">
                                        <small class="stk-help">من 2 إلى 24 شهراً</small>
                                    </div>
                                </div>

                                <div class="col-md-3" id="start_month_section" style="display:none">
                                    <div class="form-group">
                                        <label for="start_month" class="stk-req">شهر بداية الاقتطاع</label>
                                        <input type="month" name="start_month" id="start_month" class="form-control"
                                               value="{{ old('start_month', now()->addMonth()->format('Y-m')) }}">
                                    </div>
                                </div>

                                <div class="col-12" id="installments_table_section" style="display:none">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="text-center">#</th>
                                                    <th class="text-center">الشهر</th>
                                                    <th class="text-center">القسط</th>
                                                </tr>
                                            </thead>
                                            <tbody id="installments_table_body"></tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="2" class="text-center"><strong>الإجمالي</strong></td>
                                                    <td class="text-center"><strong id="total_installments">0.00</strong></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="stk-section">
                            <div class="stk-section__head">
                                <i class="feather icon-edit-3"></i>
                                <h6 class="stk-section__title">البيان</h6>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reason">سبب السلفة</label>
                                        <textarea name="reason" id="reason" class="form-control" rows="3" maxlength="500"
                                                  placeholder="سلفة صادرة من الإدارة">{{ old('reason') }}</textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="admin_notes">ملاحظات إدارية</label>
                                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3" maxlength="500">{{ old('admin_notes') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mb-0" id="normal_info">
                            <i class="feather icon-info"></i>
                            يُكتب قيد الصرف فوراً (مدين حساب سلف الموظف / دائن الخزينة)، وتُقتطع السلفة كاملةً من أوّل راتبٍ يُصرف بعدها أو تُسدَّد نقداً.
                        </div>
                        <div class="alert alert-info mb-0" id="scheduled_info" style="display:none">
                            <i class="feather icon-info"></i>
                            يُكتب قيد الصرف فوراً، ويُقتطع <strong id="summary_installment_amount">0.00</strong> شهرياً لمدة <strong id="summary_installments_count">0</strong> شهراً ابتداءً من شهر البداية.
                        </div>
                    </div>

                    <div class="stk-form-foot">
                        <a href="{{ route('hr.advance-approvals.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> إصدار وصرف
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#employee_id').select2({ dir: 'rtl', width: '100%', placeholder: '-- اختر الموظف --' });
                $('#treasury_id').select2({ dir: 'rtl', width: '100%', placeholder: '-- اختر الخزينة --' });
            }

            var fmt = function (n) { return (Math.round(n * 100) / 100).toFixed(2); };

            // تنبيهٌ مبكّر بالرصيد المعروض — القرار يُفحص في الخادم تحت قفل
            function checkTreasuryBalance() {
                var amount = parseFloat($('#amount').val()) || 0;
                var $opt = $('#treasury_id option:selected');
                var balance = parseFloat($opt.data('balance'));
                var $w = $('#treasury-balance-warning');

                if (! $opt.val() || isNaN(balance) || amount <= 0) {
                    $w.text('').removeClass('text-danger text-success');
                } else if (balance < amount) {
                    $w.text('رصيد الخزينة (' + fmt(balance) + ') أقل من المبلغ — سيُرفض الصرف.').addClass('text-danger').removeClass('text-success');
                } else {
                    $w.text('الرصيد المعروض يكفي.').addClass('text-success').removeClass('text-danger');
                }
            }

            function generateInstallmentsTable() {
                var amount = parseFloat($('#amount').val()) || 0;
                var count = parseInt($('#installments_count').val(), 10) || 2;
                var startMonth = $('#start_month').val();
                var $body = $('#installments_table_body');

                if (amount <= 0 || count < 2 || ! startMonth) {
                    $body.html('<tr><td colspan="3" class="text-center text-muted">أدخل المبلغ وعدد الأقساط وشهر البداية</td></tr>');
                    return;
                }

                var each = Math.round((amount / count) * 100) / 100;
                var parts = startMonth.split('-');
                var html = '';

                for (var i = 0; i < count; i++) {
                    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1 + i, 1);
                    var ym = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                    html += '<tr><td class="text-center">' + (i + 1) + '</td><td class="text-center">' + ym + '</td><td class="text-center">' + fmt(each) + '</td></tr>';
                }

                $body.html(html);
                $('#total_installments').text(fmt(amount));
                $('#summary_installment_amount').text(fmt(each));
                $('#summary_installments_count').text(count);
            }

            function toggleAdvanceType() {
                var scheduled = $('#type_scheduled').is(':checked');
                $('#installments_section, #start_month_section, #installments_table_section, #scheduled_info').toggle(scheduled);
                $('#normal_info').toggle(! scheduled);
                $('#installments_count, #start_month').prop('required', scheduled);
                if (scheduled) { generateInstallmentsTable(); }
            }

            $('input[name="advance_type"]').on('change', toggleAdvanceType);
            $('#amount, #installments_count, #start_month').on('change keyup', function () {
                if ($('#type_scheduled').is(':checked')) { generateInstallmentsTable(); }
            });
            $('#amount, #treasury_id').on('change keyup', checkTreasuryBalance);

            $('#directAdvanceForm').on('submit', function () {
                if (this.checkValidity && ! this.checkValidity()) { return true; }
                return confirm('إصدار السلفة وصرفها الآن من الخزينة المختارة؟');
            });

            toggleAdvanceType();
            checkTreasuryBalance();
        });
    </script>
@endpush
