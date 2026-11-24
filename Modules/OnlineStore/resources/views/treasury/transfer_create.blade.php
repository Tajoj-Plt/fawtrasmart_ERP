@extends('onlinestore::master')

@section('title', 'تحويل بين الخزائن')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_treasury.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('account.treasury_transfers.create'))
                <button type="submit" form="transfer_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تحويل بين الخزائن',
            'icon' => 'repeat',
            'sub' => 'تحويل مبلغ بين خزينتين أو حسابين بنكيين — يُنشأ له قيدٌ محاسبي',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الخزائن والحسابات البنكية' => route('store_treasury.index'),
                'تحويل' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="transfer_form" action="{{ route('store_treasury.transfer_store') }}" method="post" enctype="multipart/form-data">
            @csrf

            <div class="row">
                <!-- الخزينة المصدر -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-arrow-up-left"></i> من</h6>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="from_treasury_id" class="stk-req">الخزينة</label>
                                    <select class="form-control select2" id="from_treasury_id" name="from_treasury_id" required>
                                        <option value="">اختر الخزينة</option>
                                        {{-- ما يجوز للمستخدم السحب منه وحده — نفس فحص الحفظ --}}
                                        @foreach ($fromTreasuries as $treasury)
                                            <option value="{{ $treasury->id }}" data-balance="{{ $treasury->balance }}" data-currency="{{ $treasury->currency_code }}" @selected(old('from_treasury_id') == $treasury->id)>
                                                {{ $treasury->name }} ({{ \App\Support\CurrencySettings::number($treasury->balance) }} {{ $treasury->currency_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-create-first
                                        :count="count($treasuries ?? [])"
                                        route="store_treasury.create"
                                        permission="account.treasury.create"
                                        :plan="\App\Constants\planFeatures\AccountPlanFeatures::TREASURY"
                                        label="خزينة"
                                        hint="لا خزائن مسجَّلة بعد — والتحويل يحتاج خزينتين على الأقل." />
                                    @error('from_treasury_id')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    {{-- العملة عملة الخزينة نفسها — تُعرض ولا تُختار (كانت قائمةً
                                         بخيارٍ واحد مكتوب «SAR» أياً كانت عملة النظام) --}}
                                    <label for="from_currency">العملة</label>
                                    <input type="text" class="form-control" id="from_currency" readonly tabindex="-1" value="{{ $defaultCurrency }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="amount" class="stk-req">المبلغ</label>
                                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" required>
                                    @error('amount')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="available_before_from">المتاح قبل</label>
                                    <input type="text" class="form-control" id="available_before_from" readonly tabindex="-1">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="available_after_from">المتاح بعد</label>
                                    <input type="text" class="form-control" id="available_after_from" readonly tabindex="-1">
                                    <small id="negative-warning" class="text-danger d-none">تحذير: الرصيد بعد العملية سيكون سالباً</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الخزينة الهدف -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-arrow-down-left"></i> إلى</h6>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="to_treasury_id" class="stk-req">الخزينة</label>
                                    <select class="form-control select2" id="to_treasury_id" name="to_treasury_id" required>
                                        <option value="">اختر الخزينة</option>
                                        {{-- ما يجوز للمستخدم الإيداع فيه وحده — نفس فحص الحفظ --}}
                                        @foreach ($toTreasuries as $treasury)
                                            <option value="{{ $treasury->id }}" data-balance="{{ $treasury->balance }}" data-currency="{{ $treasury->currency_code }}" @selected(old('to_treasury_id') == $treasury->id)>
                                                {{ $treasury->name }} ({{ \App\Support\CurrencySettings::number($treasury->balance) }} {{ $treasury->currency_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('to_treasury_id')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="to_currency">العملة</label>
                                    <input type="text" class="form-control" id="to_currency" readonly tabindex="-1" value="{{ $defaultCurrency }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="available_before_to">المتاح قبل</label>
                                    <input type="text" class="form-control" id="available_before_to" readonly tabindex="-1">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="available_after_to">المتاح بعد</label>
                                    <input type="text" class="form-control" id="available_after_to" readonly tabindex="-1">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تفاصيل إضافية -->
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> تفاصيل إضافية</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="transfer_date" class="stk-req">تاريخ التحويل</label>
                            <input type="date" id="transfer_date" class="form-control" name="transfer_date" value="{{ old('transfer_date', date('Y-m-d')) }}" required>
                            @error('transfer_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label for="notes">الملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-12">
                            <label for="attachments">المرفقات</label>
                            <input type="file" name="attachments" id="attachments" class="d-none">
                            <div class="upload-area border rounded p-3 text-center position-relative" onclick="document.getElementById('attachments').click()">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <i class="fas fa-cloud-upload-alt text-primary"></i>
                                    <span class="text-primary">اضغط هنا</span>
                                    <span>أو</span>
                                    <span class="text-primary">اختر من جهازك</span>
                                </div>
                            </div>
                            @error('attachments')
                                <small class="text-danger d-block mt-1">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        if ($.fn.select2) {
            $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
        }

        function calculateAvailableAfterFrom() {
            const amount = parseFloat($('#amount').val()) || 0;
            const availableBefore = parseFloat($('#available_before_from').val()) || 0;
            const availableAfter = availableBefore - amount;
            $('#available_after_from').val(availableAfter.toFixed(2));
            if (availableAfter < 0) {
                $('#available_after_from').addClass('is-invalid');
                $('#negative-warning').removeClass('d-none');
            } else {
                $('#available_after_from').removeClass('is-invalid');
                $('#negative-warning').addClass('d-none');
            }
        }

        function calculateAvailableAfterTo() {
            const amount = parseFloat($('#amount').val()) || 0;
            const availableBefore = parseFloat($('#available_before_to').val()) || 0;
            const availableAfter = availableBefore + amount;
            $('#available_after_to').val(availableAfter.toFixed(2));
        }

        function updateFromTreasury() {
            const selectedOption = $('#from_treasury_id option:selected');
            const balance = parseFloat(selectedOption.attr('data-balance')) || 0;
            $('#from_currency').val(selectedOption.attr('data-currency') || @json($defaultCurrency));
            $('#available_before_from').val(balance.toFixed(2));
            calculateAvailableAfterFrom();
        }

        function updateToTreasury() {
            const selectedOption = $('#to_treasury_id option:selected');
            const balance = parseFloat(selectedOption.attr('data-balance')) || 0;
            $('#to_currency').val(selectedOption.attr('data-currency') || @json($defaultCurrency));
            $('#available_before_to').val(balance.toFixed(2));
            calculateAvailableAfterTo();
        }

        $('#from_treasury_id').on('change select2:select', function(e) {
            updateFromTreasury();
        });

        $('#to_treasury_id').on('change select2:select', function(e) {
            updateToTreasury();
        });

        $('#amount').on('input keyup change', function() {
            calculateAvailableAfterFrom();
            calculateAvailableAfterTo();
        });

        setTimeout(function() {
            if ($('#from_treasury_id').val()) {
                updateFromTreasury();
            }
            if ($('#to_treasury_id').val()) {
                updateToTreasury();
            }
        }, 300);
    });
</script>
@endsection
