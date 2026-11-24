@extends('account::master')

@section('title')
    تعديل التحويل
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('treasury.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="chequeForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ التغييرات
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'تعديل التحويل',
        'crumbs' => [
            'الرئيسيه' => route('treasury.index'),
            'تعديل' => null,
        ],
    ])


    <form action="{{ route('treasury.transferUpdate', $transfer->id) }}" method="post" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row">
            <!-- الخزينة المصدر -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-content">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group row">
                                        <div class="col-md-3">
                                            <span>من:</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group row">
                                        <div class="col-md-6">
                                            <label class=""> الخزينة </label>
                                            <select class="form-control select2" id="from_treasury_id" name="from_treasury_id" required>
                                                <option value="">اختر الخزينة</option>
                                                @foreach ($treasuries as $treasury)
                                                    <option value="{{ $treasury->id }}" data-balance="{{ $treasury->balance }}" {{ $transfer->details->where('is_debit', false)->first()->account_id == $treasury->id ? 'selected' : '' }}>
                                                        {{ $treasury->name }} ({{ $treasury->balance }} ريال)
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-create-first
                                                :count="count($treasuries ?? [])"
                                                route="treasury.create"
                                                permission="account.treasury.create"
                                                :plan="\App\Constants\planFeatures\AccountPlanFeatures::TREASURY"
                                                label="خزينة"
                                                hint="لا خزائن مسجَّلة بعد — والعملية تحتاج خزينةً تُقيَّد عليها." />
                                        </div>
                                        <div class="col-md-6">
                                            <label class=""> العملة </label>
                                            @include('account::components.currency-select', [
                                                'name' => 'from_currency',
                                                'class' => 'form-control select2',
                                                'required' => true,
                                                'selected' => old('from_currency', $transfer->currency),
                                            ])
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">المبلغ</label>
                                            <input type="number" class="form-control" id="amount" name="amount" value="{{ $transfer->details->where('is_debit', false)->first()->credit }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class=""> المتاح قبل </label>
                                            <input type="text" class="form-control" id="available_before_from" name="available_before_from" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class=""> المتاح بعد </label>
                                            <input type="text" class="form-control" id="available_after_from" name="available_after_from" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الخزينة الهدف -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-content">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group row">
                                        <div class="col-md-3">
                                            <span>الى:</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group row">
                                        <div class="col-md-6">
                                            <label class=""> الخزينة </label>
                                            <select class="form-control select2" id="to_treasury_id" name="to_treasury_id" required>
                                                <option value="">اختر الخزينة</option>
                                                @foreach ($treasuries as $treasury)
                                                    <option value="{{ $treasury->id }}" data-balance="{{ $treasury->balance }}" {{ $transfer->details->where('is_debit', true)->first()->account_id == $treasury->id ? 'selected' : '' }}>
                                                        {{ $treasury->name }} ({{ $treasury->balance }} ريال)
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class=""> العملة </label>
                                            @include('account::components.currency-select', [
                                                'name' => 'to_currency',
                                                'class' => 'form-control select2',
                                                'required' => true,
                                                'selected' => old('to_currency', $transfer->currency),
                                            ])
                                        </div>
                                        <div class="col-md-6">
                                            <label class=""> المتاح قبل </label>
                                            <input type="text" class="form-control" id="available_before_to" name="available_before_to" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class=""> المتاح بعد </label>
                                            <input type="text" class="form-control" id="available_after_to" name="available_after_to" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- تفاصيل إضافية -->
        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    <div class="form-body row">
                        <div class="form-group col-md-6">
                            <label for="transfer_date"> تاريخ التحويل </label>
                            <input type="date" id="transfer_date" class="form-control" name="transfer_date" value="{{ $transfer->date }}" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الملاحظات </label>
                            <textarea class="form-control" id="notes" name="notes" rows="3">{{ $transfer->description }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                    <div class="stk-form-foot">
                        <a href="{{ route('treasury.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> حفظ التغييرات
                        </button>
                    </div>

    </form>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // عند تغيير الخزينة المصدر
            $('#from_treasury_id').change(function() {
                const selectedTreasury = $(this).find(':selected');
                const balance = selectedTreasury.data('balance');
                $('#available_before_from').val(balance); // تحديث المتاح قبل
                calculateAvailableAfterFrom(); // حساب المتاح بعد
            });

            // عند تغيير الخزينة الهدف
            $('#to_treasury_id').change(function() {
                const selectedTreasury = $(this).find(':selected');
                const balance = selectedTreasury.data('balance');
                $('#available_before_to').val(balance); // تحديث المتاح قبل
                calculateAvailableAfterTo(); // حساب المتاح بعد
            });

            // عند تغيير المبلغ
            $('#amount').keyup(function() {
                calculateAvailableAfterFrom(); // حساب المتاح بعد للخزينة المصدر
                calculateAvailableAfterTo(); // حساب المتاح بعد للخزينة الهدف
            });

            // دالة لحساب المتاح بعد للخزينة المصدر
            function calculateAvailableAfterFrom() {
                const amount = parseFloat($('#amount').val()) || 0;
                const availableBefore = parseFloat($('#available_before_from').val()) || 0;
                const availableAfter = availableBefore - amount; // خصم المبلغ من الرصيد المتاح
                $('#available_after_from').val(availableAfter); // تحديث المتاح بعد
            }

            // دالة لحساب المتاح بعد للخزينة الهدف
            function calculateAvailableAfterTo() {
                const amount = parseFloat($('#amount').val()) || 0;
                const availableBefore = parseFloat($('#available_before_to').val()) || 0;
                const availableAfter = availableBefore + amount; // إضافة المبلغ إلى الرصيد المتاح
                $('#available_after_to').val(availableAfter); // تحديث المتاح بعد
            }
        });
    </script>
@endsection
