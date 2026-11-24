@extends('hr::master')

@section('title')
    السلفة #{{ $request->id }}
@stop

@section('content')
    @php
        $stage = $request->stage();
        $money = fn ($v) => \App\Support\CurrencySettings::format($v);
        $remaining = $request->remainingAmount();
    @endphp

    @section('page-actions')
        @php
            // الإجراءُ الحاسم في هذه المرحلة — نماذجه (بحقولها) في العمود الجانبي، والزرّ يقفز إليها
            $flow = match (true) {
                $stage === 'pending' && hasPermission('loans.approve') => ['#approveForm', 'fa-check', 'اعتماد الطلب'],
                $stage === 'approved' && hasPermission('loans.disburse') => ['#disburseForm', 'fa-money-bill-wave', 'صرف السلفة'],
                in_array($stage, ['disbursed', 'repaying'], true) && hasPermission('loans.settle') => ['#settleForm', 'fa-hand-holding-usd', 'تسجيل سداد'],
                default => null,
            };
            $canCancelHere = in_array($stage, ['pending', 'approved', 'disbursed', 'repaying'], true)
                && hasPermission('loans.cancel') && ! $cancelBlock;
            $canRejectHere = $stage === 'pending' && hasPermission('loans.approve');
            $advPrintRoute = collect(['hr.advances.print', 'hr.advance.print', 'hr.advance-approvals.print'])->first(fn ($r) => \Illuminate\Support\Facades\Route::has($r));
        @endphp

        @if ($flow)
            <a href="{{ $flow[0] }}" class="btn btn-primary"><i class="fas {{ $flow[1] }}"></i> {{ $flow[2] }}</a>
        @endif
        @if ($advPrintRoute && hasPermission('loans.show'))
            <a href="{{ route($advPrintRoute, $request->id) }}" target="_blank" class="btn btn-outline-primary">
                <i class="fas fa-print"></i> طباعة
            </a>
        @endif
        @if ($canRejectHere || $canCancelHere)
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-h"></i> المزيد
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if ($canRejectHere)
                        <li><a class="dropdown-item text-danger" href="#rejectForm"><i class="fas fa-times me-2"></i>رفض الطلب</a></li>
                    @endif
                    @if ($canCancelHere)
                        <li><a class="dropdown-item text-danger" href="#cancelForm"><i class="fas fa-ban me-2"></i>إلغاء السلفة</a></li>
                    @endif
                </ul>
            </div>
        @endif
    @endsection

    @include('stock::components.page-head', [
        'title' => 'السلفة #' . $request->id . ' — ' . ($request->employee->full_name ?? ''),
        'icon' => 'arrow-up-circle',
        'crumbs' => ['الرئيسية' => route('hr.department.dashboard'), 'إدارة السلف' => route('hr.advance-approvals.index'), 'السلفة #' . $request->id => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        @isset($advanceSummary)
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-arrow-up-circle"></i></span>
                    <span class="stk-metric__label">سلف الموظف المصروفة
                        <i class="feather icon-help-circle stk-tip" title="من القيود على حساب سلف الموظف — كلُّ سلفه لا هذه وحدها"></i></span>
                    <span class="stk-metric__value">{{ $money($advanceSummary['issued']) }}</span>
                </div>
                <div class="stk-metric stk-metric--success">
                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                    <span class="stk-metric__label">المسدَّد</span>
                    <span class="stk-metric__value">{{ $money($advanceSummary['settled']) }}</span>
                </div>
                <div class="stk-metric {{ $advanceSummary['remaining'] > 0 ? 'stk-metric--danger' : '' }}">
                    <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                    <span class="stk-metric__label">المتبقّي على الموظف</span>
                    <span class="stk-metric__value">{{ $money($advanceSummary['remaining']) }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-book"></i></span>
                    <span class="stk-metric__label">حساب السلف</span>
                    <span class="stk-metric__value" style="font-size:.9rem">{{ optional($request->employee->advanceAccount)->name ?: 'غير مرتبط' }}</span>
                </div>
            </div>
        @endisset

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> تفاصيل السلفة</h6>
                        <span class="stk-state {{ $request->stageClass() }}">{{ $request->stageLabel() }}</span>
                    </div>
                    <div class="card-body">
                        @include('hr::salaries.advance_requests.partials.details', ['request' => $request])
                    </div>
                </div>

                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-book-open"></i> القيود المحاسبية
                            <span class="stk-count">{{ $journals->count() }}</span>
                        </h6>
                    </div>
                    @if ($journals->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>القيد</th>
                                        <th>المرجع</th>
                                        <th>التاريخ</th>
                                        <th>البيان</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($journals as $je)
                                        <tr>
                                            <td>
                                                @permission('account.journal.view')
                                                    <a href="{{ route('journal.show', $je->id) }}" target="_blank" class="stk-num">#{{ $je->id }}</a>
                                                @else
                                                    <span class="stk-num">#{{ $je->id }}</span>
                                                @endpermission
                                            </td>
                                            <td class="stk-num">{{ $je->reference_number }}</td>
                                            <td>{{ \Carbon\Carbon::parse($je->date)->format('Y-m-d') }}</td>
                                            <td>{{ \Illuminate\Support\Str::limit($je->description, 90) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-book-open"></i></div>
                            <h6 class="stk-empty__title">لا قيود بعد</h6>
                            <p class="stk-empty__hint">الاعتمادُ لا يكتب قيداً — الصرفُ يكتبه.</p>
                        </div>
                    @endif
                </div>

                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-activity"></i> سجلّ السلفة</h6>
                    </div>
                    <div class="card-body">
                        @include('hr::salaries.advance_requests.partials.timeline', ['logs' => $logs])
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- ═══════════ بانتظار الاعتماد ═══════════ --}}
                @if ($stage === 'pending')
                    @permission('loans.approve')
                        <div class="card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-check-circle"></i> اعتماد الطلب</h6>
                            </div>
                            <form action="{{ route('hr.advance-approvals.approve', $request->id) }}" method="POST" id="approveForm">
                                @csrf
                                <div class="card-body">
                                    @permission('loans.disburse')
                                        <div class="form-group">
                                            <label class="stk-switch mb-0">
                                                <input type="checkbox" name="disburse_now" id="disburse_now" value="1" @checked(old('disburse_now', '1') == '1')>
                                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                                <span class="stk-switch__label">
                                                    <span class="stk-switch__title">اعتماد وصرف الآن</span>
                                                    <span class="stk-switch__hint">أطفئه لتعتمد الطلب وتؤجّل الصرف</span>
                                                </span>
                                            </label>
                                        </div>

                                        <div class="form-group" id="approveTreasuryGroup">
                                            <label for="approve_treasury_id" class="stk-req">الخزينة
                                                <i class="feather icon-help-circle stk-tip" title="الخزائن التي يُسمح لك بالسحب منها. الرصيدُ المعروض للتنبيه؛ والقرارُ يُفحص من الدفتر تحت قفل عند الحفظ."></i>
                                            </label>
                                            <select name="treasury_id" id="approve_treasury_id" class="form-control hr-s2 js-treasury" data-amount="{{ (float) $request->amount }}">
                                                <option value="">-- اختر الخزينة --</option>
                                                @foreach ($treasuries as $t)
                                                    <option value="{{ $t->id }}" data-balance="{{ (float) $t->balance }}"
                                                        @selected((string) old('treasury_id', $defaultTreasuryId) === (string) $t->id)>
                                                        {{ $t->name }} ({{ $money($t->balance) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="stk-help text-danger js-balance-warning" style="display:none">
                                                <i class="feather icon-alert-triangle"></i> رصيد الخزينة أقل من مبلغ السلفة — سيُرفض الصرف.
                                            </small>
                                        </div>
                                    @endpermission

                                    <div class="form-group mb-0">
                                        <label for="admin_notes">ملاحظات</label>
                                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="2" maxlength="500">{{ old('admin_notes') }}</textarea>
                                    </div>
                                </div>
                                <div class="stk-form-foot">
                                    <button type="submit" class="btn btn-success">
                                        <i class="feather icon-check"></i> اعتماد
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-x-circle"></i> رفض الطلب</h6>
                            </div>
                            <form action="{{ route('hr.advance-approvals.reject', $request->id) }}" method="POST" id="rejectForm">
                                @csrf
                                <div class="card-body">
                                    <div class="form-group mb-0">
                                        <label for="rejection_reason" class="stk-req">سبب الرفض</label>
                                        <textarea name="rejection_reason" id="rejection_reason" class="form-control @error('rejection_reason') is-invalid @enderror"
                                                  rows="3" maxlength="500" required>{{ old('rejection_reason') }}</textarea>
                                        @error('rejection_reason')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="stk-form-foot">
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="feather icon-x"></i> رفض
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endpermission
                @endif

                {{-- ═══════════ معتمدة بانتظار الصرف ═══════════ --}}
                @if ($stage === 'approved')
                    @permission('loans.disburse')
                        <div class="card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-dollar-sign"></i> صرف السلفة</h6>
                            </div>
                            <form action="{{ route('hr.advance-approvals.disburse', $request->id) }}" method="POST" id="disburseForm">
                                @csrf
                                <div class="card-body">
                                    <p class="stk-help mt-0">قيد: مدين حساب سلف الموظف / دائن الخزينة بمبلغ <strong>{{ $money($request->amount) }}</strong>.</p>
                                    <div class="form-group mb-0">
                                        <label for="disburse_treasury_id" class="stk-req">الخزينة</label>
                                        <select name="treasury_id" id="disburse_treasury_id" class="form-control hr-s2 js-treasury" data-amount="{{ (float) $request->amount }}" required>
                                            <option value="">-- اختر الخزينة --</option>
                                            @foreach ($treasuries as $t)
                                                <option value="{{ $t->id }}" data-balance="{{ (float) $t->balance }}"
                                                    @selected((string) old('treasury_id', $defaultTreasuryId) === (string) $t->id)>
                                                    {{ $t->name }} ({{ $money($t->balance) }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="stk-help text-danger js-balance-warning" style="display:none">
                                            <i class="feather icon-alert-triangle"></i> رصيد الخزينة أقل من مبلغ السلفة — سيُرفض الصرف.
                                        </small>
                                    </div>
                                </div>
                                <div class="stk-form-foot">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-dollar-sign"></i> صرف
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endpermission
                @endif

                {{-- ═══════════ في دورة السداد: التسوية ═══════════ --}}
                @if (in_array($stage, ['disbursed', 'repaying'], true))
                    @permission('loans.settle')
                        <div class="card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-download"></i> تسوية — تحصيل سداد نقدي</h6>
                            </div>
                            <form action="{{ route('hr.advance-approvals.settle', $request->id) }}" method="POST" id="settleForm">
                                @csrf
                                <div class="card-body">
                                    <p class="stk-help mt-0">
                                        التسويةُ لا تُلغي السلفة: تُسجِّل سداداً (مدين الخزينة / دائن حساب السلف) وتبقى السلفةُ في دورة السداد حتى يُقفل المتبقّي
                                        <strong>{{ $money($remaining) }}</strong>.
                                    </p>

                                    @if ($request->isScheduled())
                                        @php
                                            $nextMonth = \Carbon\Carbon::parse(($request->start_month ?: now()->format('Y-m')).'-01')->addMonths((int) $request->paid_installments)->format('Y-m');
                                        @endphp
                                        <div class="form-group">
                                            <label for="settlement_month" class="stk-req">تسوية الأقساط حتى شهر
                                                <i class="feather icon-help-circle stk-tip" title="تُحصَّل الأقساط غير المسدَّدة من أوّل قسطٍ مستحق حتى هذا الشهر. الشهرُ الأخير يُقفل المتبقّي كلَّه."></i>
                                            </label>
                                            <input type="month" name="settlement_month" id="settlement_month" class="form-control"
                                                   min="{{ $nextMonth }}" value="{{ old('settlement_month', $nextMonth) }}" required>
                                        </div>
                                    @else
                                        <div class="form-group">
                                            <label for="settlement_amount">المبلغ المحصَّل
                                                <i class="feather icon-help-circle stk-tip" title="اتركه فارغاً لتحصيل المتبقّي كلِّه وإقفال السلفة. مبلغٌ أقلّ يُسجَّل سداداً جزئياً."></i>
                                            </label>
                                            <input type="number" name="settlement_amount" id="settlement_amount" class="form-control"
                                                   step="0.01" min="0.01" max="{{ $remaining }}" value="{{ old('settlement_amount') }}"
                                                   placeholder="{{ number_format($remaining, 2, '.', '') }}">
                                        </div>
                                    @endif

                                    <div class="form-group">
                                        <label for="settle_treasury_id" class="stk-req">الخزينة المستلِمة</label>
                                        <select name="treasury_id" id="settle_treasury_id" class="form-control hr-s2" required>
                                            <option value="">-- اختر الخزينة --</option>
                                            @foreach ($depositTreasuries as $t)
                                                <option value="{{ $t->id }}" @selected((string) old('treasury_id', $defaultTreasuryId) === (string) $t->id)>{{ $t->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group mb-0">
                                        <label for="settlement_notes">ملاحظات</label>
                                        <textarea name="settlement_notes" id="settlement_notes" class="form-control" rows="2" maxlength="500">{{ old('settlement_notes') }}</textarea>
                                    </div>
                                </div>
                                <div class="stk-form-foot">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-check"></i> تسجيل السداد
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endpermission
                @endif

                {{-- ═══════════ الإلغاء — عمليةٌ مستقلّة ═══════════ --}}
                @if (in_array($stage, ['pending', 'approved', 'disbursed', 'repaying'], true))
                    @permission('loans.cancel')
                        <div class="card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-slash"></i> إلغاء السلفة</h6>
                            </div>
                            @if ($cancelBlock)
                                <div class="card-body">
                                    <p class="stk-help mt-0 text-warning"><i class="feather icon-lock"></i> {{ $cancelBlock }}</p>
                                </div>
                            @else
                                <form action="{{ route('hr.advance-approvals.cancel', $request->id) }}" method="POST" id="cancelForm">
                                    @csrf
                                    <div class="card-body">
                                        <p class="stk-help mt-0">
                                            @if ($request->isDisbursed())
                                                السلفةُ مصروفة: يُكتب قيدٌ عكسيّ (مدين {{ $request->treasury->name ?? 'الخزينة' }} / دائن حساب السلف) بمبلغ {{ $money($request->amount) }}.
                                            @else
                                                لم تُصرف بعد: تُغلق «ملغاة» بلا قيد.
                                            @endif
                                        </p>
                                        <div class="form-group mb-0">
                                            <label for="cancellation_reason" class="stk-req">سبب الإلغاء</label>
                                            <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="2" maxlength="500" required>{{ old('cancellation_reason') }}</textarea>
                                        </div>
                                    </div>
                                    <div class="stk-form-foot">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="feather icon-slash"></i> إلغاء السلفة
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endpermission
                @endif

                @if (in_array($stage, ['settled', 'rejected', 'cancelled'], true))
                    <div class="card">
                        <div class="card-body">
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-{{ $stage === 'settled' ? 'check-circle' : 'x-circle' }}"></i></div>
                                <h6 class="stk-empty__title">{{ $request->stageLabel() }}</h6>
                                <p class="stk-empty__hint">لا إجراءات متاحة على سلفةٍ مغلقة.</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            // تنبيهٌ مبكّر بالرصيد المعروض — قرارُ الصرف يُفحص في الخادم تحت قفل
            $('.js-treasury').on('change', function () {
                var $opt = $(this).find(':selected');
                var balance = parseFloat($opt.data('balance'));
                var amount = parseFloat($(this).data('amount')) || 0;
                $(this).closest('.form-group').find('.js-balance-warning').toggle($opt.val() !== '' && ! isNaN(balance) && balance < amount);
            }).trigger('change');

            var toggleTreasury = function () {
                var on = $('#disburse_now').is(':checked');
                $('#approveTreasuryGroup').toggle(on);
                $('#approve_treasury_id').prop('required', on);
            };
            $('#disburse_now').on('change', toggleTreasury);
            toggleTreasury();

            var confirmOn = function (selector, message) {
                $(selector).on('submit', function () {
                    if (this.checkValidity && ! this.checkValidity()) { return true; }
                    return confirm(typeof message === 'function' ? message() : message);
                });
            };

            confirmOn('#approveForm', function () {
                return $('#disburse_now').is(':checked')
                    ? 'اعتماد السلفة وصرفها الآن من ' + $('#approve_treasury_id option:selected').text().trim() + '؟'
                    : 'اعتماد الطلب دون صرف؟';
            });
            confirmOn('#rejectForm', 'رفض الطلب؟');
            confirmOn('#disburseForm', 'صرف السلفة من الخزينة المختارة وكتابة القيد؟');
            confirmOn('#settleForm', 'تسجيل السداد النقدي وكتابة قيد التحصيل؟');
            confirmOn('#cancelForm', 'إلغاء السلفة؟ لا يمكن التراجع.');
        });
    </script>
@endpush
