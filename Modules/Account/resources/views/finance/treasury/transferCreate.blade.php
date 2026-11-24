@extends('account::master')

@section('title')
    التحويلات
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('treasury.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="chequeForm" class="btn btn-primary">
            <i class="feather icon-save"></i> تنفيذ التحويل
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'التحويلات',
        'crumbs' => [
            'الرئيسيه' => route('treasury.index'),
            'عرض' => null,
        ],
    ])


    <form action="{{ route('treasury.transferTreasuryStore') }}" method="post" enctype="multipart/form-data">
        @csrf

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
                                            <label class=""> الخزينة <span class="text-danger">*</span></label>
                                            <select class="form-control select2" id="from_treasury_id" name="from_treasury_id" required>
                                                <option value="">اختر الخزينة</option>
                                                @foreach ($treasuries as $treasury)
                                                    <option value="{{ $treasury->id }}" data-balance="{{ $treasury->balance }}" {{ old('from_treasury_id') == $treasury->id ? 'selected' : '' }}>
                                                        {{ $treasury->name }} ({{ number_format($treasury->balance, 2) }} ريال)
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
                                            @error('from_treasury_id')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class=""> العملة <span class="text-danger">*</span></label>
                                            @include('account::components.currency-select', [
                                                'name' => 'from_currency',
                                                'class' => 'form-control select2',
                                                'required' => true,
                                                'selected' => old('from_currency'),
                                            ])
                                            @error('from_currency')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">المبلغ <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" required>
                                            @error('amount')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class=""> المتاح قبل </label>
                                            <input type="text" class="form-control" id="available_before_from" name="available_before_from" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class=""> المتاح بعد </label>
                                            <input type="text" class="form-control" id="available_after_from" name="available_after_from" readonly>
                                            <small id="negative-warning" class="text-danger d-none">تحذير: الرصيد بعد العملية سيكون سالباً</small>
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
                                            <label class=""> الخزينة <span class="text-danger">*</span></label>
                                            <select class="form-control select2" id="to_treasury_id" name="to_treasury_id" required>
                                                <option value="">اختر الخزينة</option>
                                                @foreach ($treasuries as $treasury)
                                                    <option value="{{ $treasury->id }}" data-balance="{{ $treasury->balance }}" {{ old('to_treasury_id') == $treasury->id ? 'selected' : '' }}>
                                                        {{ $treasury->name }} ({{ number_format($treasury->balance, 2) }} ريال)
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('to_treasury_id')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class=""> العملة <span class="text-danger">*</span></label>
                                            @include('account::components.currency-select', [
                                                'name' => 'to_currency',
                                                'class' => 'form-control select2',
                                                'required' => true,
                                                'selected' => old('to_currency'),
                                            ])
                                            @error('to_currency')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
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
                            <label for="transfer_date"> تاريخ التحويل <span class="text-danger">*</span></label>
                            <input type="date" id="transfer_date" class="form-control" name="transfer_date" value="{{ old('transfer_date', date('Y-m-d')) }}" required>
                            @error('transfer_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label>الملاحظات </label>
                            <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <div class="form-group">
                            <label for="attachments">المرفقات</label>
                            <input type="file" name="attachments" id="attachments" class="d-none">
                            <div class="upload-area border rounded p-3 text-center position-relative" onclick="document.getElementById('attachments').click()">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <i class="fas fa-cloud-upload-alt text-primary"></i>
                                    <span class="text-primary">اضغط هنا</span>
                                    <span>أو</span>
                                    <span class="text-primary">اختر من جهازك</span>
                                </div>
                                <div class="position-absolute end-0 top-50 translate-middle-y me-3">
                                    <i class="fas fa-file-alt fs-3 text-secondary"></i>
                                </div>
                            </div>
                            @error('attachments')
                                <small class="text-danger d-block mt-1">{{ $message }}</small>
                            @enderror
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
                            <i class="feather icon-save"></i> تنفيذ التحويل
                        </button>
                    </div>

    </form>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // دالة لحساب المتاح بعد للخزينة المصدر
            function calculateAvailableAfterFrom() {
                const amount = parseFloat($('#amount').val()) || 0;
                const availableBefore = parseFloat($('#available_before_from').val()) || 0;
                const availableAfter = availableBefore - amount; // خصم المبلغ من الرصيد المتاح
                $('#available_after_from').val(availableAfter.toFixed(2)); // تحديث المتاح بعد
                if (availableAfter < 0) {
                    $('#available_after_from').addClass('is-invalid');
                    $('#negative-warning').removeClass('d-none');
                } else {
                    $('#available_after_from').removeClass('is-invalid');
                    $('#negative-warning').addClass('d-none');
                }
            }

            // دالة لحساب المتاح بعد للخزينة الهدف
            function calculateAvailableAfterTo() {
                const amount = parseFloat($('#amount').val()) || 0;
                const availableBefore = parseFloat($('#available_before_to').val()) || 0;
                const availableAfter = availableBefore + amount; // إضافة المبلغ إلى الرصيد المتاح
                $('#available_after_to').val(availableAfter.toFixed(2)); // تحديث المتاح بعد
            }

            // دالة لتحديث رصيد الخزينة المصدر
            function updateFromTreasury() {
                const selectedOption = $('#from_treasury_id option:selected');
                const balance = parseFloat(selectedOption.attr('data-balance')) || 0;
                console.log('From Treasury Selected:', selectedOption.text(), 'Balance:', balance);
                $('#available_before_from').val(balance.toFixed(2));
                calculateAvailableAfterFrom();
            }

            // دالة لتحديث رصيد الخزينة الهدف
            function updateToTreasury() {
                const selectedOption = $('#to_treasury_id option:selected');
                const balance = parseFloat(selectedOption.attr('data-balance')) || 0;
                console.log('To Treasury Selected:', selectedOption.text(), 'Balance:', balance);
                $('#available_before_to').val(balance.toFixed(2));
                calculateAvailableAfterTo();
            }

            // معالج تغيير الخزينة المصدر (يدعم select2)
            $('#from_treasury_id').on('change select2:select', function(e) {
                updateFromTreasury();
            });

            // معالج تغيير الخزينة الهدف (يدعم select2)
            $('#to_treasury_id').on('change select2:select', function(e) {
                updateToTreasury();
            });

            // عند تغيير المبلغ
            $('#amount').on('input keyup change', function() {
                calculateAvailableAfterFrom();
                calculateAvailableAfterTo();
            });

            // تحميل القيم عند فتح الصفحة (إذا كانت هناك قيم قديمة من old())
            setTimeout(function() {
                if ($('#from_treasury_id').val()) {
                    updateFromTreasury();
                }
                if ($('#to_treasury_id').val()) {
                    updateToTreasury();
                }
            }, 300); // تأخير بسيط للتأكد من تحميل select2
        });
    </script>
@endsection
