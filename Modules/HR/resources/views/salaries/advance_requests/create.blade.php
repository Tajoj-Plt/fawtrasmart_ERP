@extends('hr::master')

@section('title')
    طلب سلفة جديدة
@stop

@section('content')
    @include('stock::components.page-head', [
        'title' => 'طلب سلفة جديدة',
        'icon' => 'arrow-up-circle',
        'crumbs' => ['الرئيسية' => route('hr.department.dashboard'), 'طلبات السلف' => route('hr.advance-requests.index'), 'طلب جديد' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        @isset($advanceSummary)
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-arrow-up-circle"></i></span>
                    <span class="stk-metric__label">إجمالي السلف المصروفة لك
                        <i class="feather icon-help-circle stk-tip" title="من القيود المحاسبية على حساب سلفك في الدليل المحاسبي"></i></span>
                    <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($advanceSummary['issued']) }}</span>
                </div>
                <div class="stk-metric stk-metric--success">
                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                    <span class="stk-metric__label">المسدَّد</span>
                    <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($advanceSummary['settled']) }}</span>
                </div>
                <div class="stk-metric {{ $advanceSummary['remaining'] > 0 ? 'stk-metric--danger' : '' }}">
                    <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                    <span class="stk-metric__label">المتبقّي عليك</span>
                    <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($advanceSummary['remaining']) }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-book"></i></span>
                    <span class="stk-metric__label">حساب السلف</span>
                    <span class="stk-metric__value" style="font-size:.9rem">{{ optional($employee->advanceAccount)->name ?: '—' }}</span>
                </div>
            </div>
        @endisset

        @permission('loans.create')
            <form action="{{ route('hr.advance-requests.store') }}" method="POST" id="advanceRequestForm">
                @csrf

                <div class="card">
                    <div class="card-body">
                        <div class="stk-section">
                            <div class="stk-section__head">
                                <i class="feather icon-file-text"></i>
                                <h6 class="stk-section__title">بيانات الطلب</h6>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="approver_user_id" class="stk-req">الموظف المعتمد
                                            <i class="feather icon-help-circle stk-tip"
                                               title="من يملك صلاحية اعتماد السلف{{ $approverDepartments->isNotEmpty() ? ' من أقسام: '.$approverDepartments->pluck('name')->implode('، ') : '' }} — كما ضُبط في «إعدادات السلف». يصله إشعارُ الطلب وحده."></i>
                                        </label>
                                        <select class="form-control select2 @error('approver_user_id') is-invalid @enderror"
                                                id="approver_user_id" name="approver_user_id" required>
                                            <option value="">-- اختر الموظف المعتمد --</option>
                                            @foreach ($approverUsers as $approver)
                                                <option value="{{ $approver->id }}" @selected((string) old('approver_user_id') === (string) $approver->id)>
                                                    {{ $approver->employee->full_name ?? $approver->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('approver_user_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        @if ($approverUsers->isEmpty())
                                            <small class="stk-help text-danger">
                                                <i class="feather icon-alert-triangle"></i>
                                                لا يوجد معتمِدٌ متاح — راجع إدارة الموارد البشرية لضبط «إعدادات السلف».
                                            </small>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount" class="stk-req">المبلغ المطلوب</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control @error('amount') is-invalid @enderror"
                                                   id="amount" name="amount" value="{{ old('amount') }}"
                                                   placeholder="0.00" step="0.01" min="1" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text">{{ \App\Support\CurrencySettings::symbol() }}</span>
                                            </div>
                                        </div>
                                        @error('amount')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="requested_date" class="stk-req">تاريخ الطلب</label>
                                        <input type="date" class="form-control @error('requested_date') is-invalid @enderror"
                                               id="requested_date" name="requested_date"
                                               value="{{ old('requested_date', date('Y-m-d')) }}" min="{{ date('Y-m-d') }}" required>
                                        @error('requested_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="reason" class="stk-req">سبب طلب السلفة</label>
                                        <textarea class="form-control @error('reason') is-invalid @enderror"
                                                  id="reason" name="reason" rows="4" maxlength="500"
                                                  placeholder="اذكر سبب طلب السلفة بوضوح" required>{{ old('reason') }}</textarea>
                                        @error('reason')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="stk-help">(<span id="charCount">0</span>/500)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mb-0">
                            <i class="feather icon-info"></i>
                            يصل الطلبُ إلى المعتمِد الذي اخترته. والاعتمادُ لا يُخرج المال بنفسه: السلفةُ تُصرف بعده من الخزينة، ثمّ تُقتطع من راتبك أو تُسدَّد نقداً.
                        </div>
                    </div>

                    <div class="stk-form-foot">
                        <a href="{{ route('hr.advance-requests.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                        <button type="submit" class="btn btn-primary" @disabled($approverUsers->isEmpty())>
                            <i class="feather icon-send"></i> إرسال الطلب
                        </button>
                    </div>
                </div>
            </form>
        @endpermission
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#approver_user_id').select2({ dir: 'rtl', width: '100%', placeholder: '-- اختر الموظف المعتمد --' });
            }

            var $reason = $('#reason');
            var count = function () { $('#charCount').text(($reason.val() || '').length); };
            $reason.on('input', count);
            count();

            $('#advanceRequestForm').on('submit', function () {
                if (this.checkValidity && ! this.checkValidity()) {
                    return true; // يتولّاه `hr::components.form-ux`
                }
                return confirm('إرسال طلب السلفة إلى المعتمِد؟');
            });
        });
    </script>
@endpush
