@extends('account::master')

@section('title')
    بيع الأصل - {{ $asset->name }}
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('Assets.show', $asset->id) }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="sellForm" class="btn btn-primary">
            <i class="feather icon-save"></i> تأكيد البيع
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'بيع الأصل',
        'icon' => 'dollar-sign',
        'sub' => $asset->name . ' — يُقفَل حساب الأصل ومجمّع إهلاكه ويُثبَت فرق البيع ربحاً أو خسارة',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الأصول الثابتة' => route('Assets.index'),
            $asset->name => route('Assets.show', $asset->id),
            'بيع' => null,
        ],
    ])


    <div class="content-body">
                <form id="sellForm" action="{{ route('Assets.sell', $asset->id) }}" method="POST">
                    @csrf

                    <!-- معلومات الأصل -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">معلومات الأصل</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- اسم الأصل -->
                                <div class="form-group col-md-4 mb-3">
                                    <label>اسم الأصل</label>
                                    <input type="text" class="form-control" value="{{ $asset->name }}" readonly>
                                </div>

                                <!-- كود الأصل -->
                                <div class="form-group col-md-4 mb-3">
                                    <label>كود الأصل</label>
                                    <input type="text" class="form-control" value="{{ $asset->code }}" readonly>
                                </div>

                                <!-- قيمة الشراء -->
                                <div class="form-group col-md-4 mb-3">
                                    <label>قيمة الشراء</label>
                                    <input type="text" class="form-control" value="{{ number_format($asset->purchase_value, 2) }}" readonly>
                                </div>

                                <!-- القيمة الدفترية -->
                                <div class="form-group col-md-4 mb-3">
                                    <label>القيمة الدفترية الحالية</label>
                                    <input type="text" class="form-control text-danger font-weight-bold" 
                                           value="{{ number_format($asset->depreciation ? $asset->depreciation->book_value : $asset->purchase_value, 2) }}" readonly>
                                </div>

                                <!-- تاريخ الشراء -->
                                <div class="form-group col-md-4 mb-3">
                                    <label>تاريخ الشراء</label>
                                    <input type="text" class="form-control" value="{{ $asset->date_price }}" readonly>
                                </div>

                                <!-- المكان -->
                                <div class="form-group col-md-4 mb-3">
                                    <label>المكان</label>
                                    <input type="text" class="form-control" value="{{ $asset->place ?? 'غير محدد' }}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- بيانات البيع -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">بيانات البيع</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- تاريخ البيع -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="sale_date">تاريخ البيع <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="sale_date" name="sale_date"
                                           value="{{ old('sale_date', date('Y-m-d')) }}" required>
                                </div>

                                <!-- سعر البيع -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="sale_price">سعر البيع <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="sale_price"
                                           name="sale_price" value="{{ old('sale_price') }}" required>
                                </div>

                                <!-- حساب النقدية -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="cash_account">حساب النقدية <span class="text-danger">*</span></label>
                                    <select name="cash_account" id="cash_account" class="form-control select2" required>
                                        <option value="">اختر حساب النقدية</option>
                                        @foreach($accounts_all as $account)
                                            <option value="{{ $account->id }}"
                                                {{ old('cash_account') == $account->id ? 'selected' : '' }}>
                                                {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- الضريبة 1 -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="tax1">الضريبة 1</label>
                                    <select name="tax1" id="tax1" class="form-control">
                                        <option value="">بدون ضريبة</option>
                                        <option value="1" {{ old('tax1') == '1' ? 'selected' : '' }}>القيمة المضافة</option>
                                        <option value="2" {{ old('tax1') == '2' ? 'selected' : '' }}>صفرية</option>
                                        <option value="3" {{ old('tax1') == '3' ? 'selected' : '' }}>معفاة</option>
                                    </select>
                                </div>

                                <!-- الضريبة 2 -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="tax2">الضريبة 2</label>
                                    <select name="tax2" id="tax2" class="form-control">
                                        <option value="">بدون ضريبة</option>
                                        <option value="1" {{ old('tax2') == '1' ? 'selected' : '' }}>القيمة المضافة</option>
                                        <option value="2" {{ old('tax2') == '2' ? 'selected' : '' }}>صفرية</option>
                                        <option value="3" {{ old('tax2') == '3' ? 'selected' : '' }}>معفاة</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- معلومات الربح/الخسارة -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">توضيح الربح/الخسارة</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert bg-royal-luxury" role="alert">
                                <i class="fa fa-info-circle"></i>
                                <strong>ملاحظة:</strong> سيتم حساب الربح أو الخسارة تلقائياً بناءً على الفرق بين سعر البيع والقيمة الدفترية الحالية.
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <tr>
                                                    <th width="50%">القيمة الدفترية الحالية</th>
                                                    <td class="text-danger font-weight-bold">{{ number_format($asset->depreciation ? $asset->depreciation->book_value : $asset->purchase_value, 2) }}</td>
                                                </tr>
                                                <tr>
                                                    <th>سعر البيع (سيتم احتسابه بعد الإدخال)</th>
                                                    <td id="sale-price-display">0.00</td>
                                                </tr>
                                                <tr class="bg-royal-luxury">
                                                    <th>الربح/الخسارة</th>
                                                    <td id="profit-loss-display" class="font-weight-bold">0.00</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stk-form-foot">
                        <a href="{{ route('Assets.show', $asset->id) }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="feather icon-save"></i> تأكيد البيع
                        </button>
                    </div>

                </form>
    </div>
@endsection

@section('scripts')
    <!-- إضافة مكتبة Select2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // تهيئة Select2
            $('.select2').select2({
                placeholder: "اختر...",
                allowClear: true,
                width: '100%'
            });

            // القيمة الدفترية
            const bookValue = {{ $asset->depreciation ? $asset->depreciation->book_value : $asset->purchase_value }};

            // حساب الربح/الخسارة عند تغيير سعر البيع
            $('#sale_price').on('input', function() {
                const salePrice = parseFloat($(this).val()) || 0;
                const profitLoss = salePrice - bookValue;

                $('#sale-price-display').text(salePrice.toFixed(2));
                
                const profitLossDisplay = $('#profit-loss-display');
                profitLossDisplay.text(profitLoss.toFixed(2));
                
                if (profitLoss > 0) {
                    profitLossDisplay.removeClass('text-danger').addClass('text-success');
                    profitLossDisplay.html('ربح: ' + profitLoss.toFixed(2));
                } else if (profitLoss < 0) {
                    profitLossDisplay.removeClass('text-success').addClass('text-danger');
                    profitLossDisplay.html('خسارة: ' + Math.abs(profitLoss).toFixed(2));
                } else {
                    profitLossDisplay.removeClass('text-success text-danger');
                    profitLossDisplay.text('0.00');
                }
            });

            // معالجة إرسال النموذج
            $('#sellForm').submit(function(e) {
                e.preventDefault();

                const form = this;
                const submitBtn = $('#submitBtn');
                const salePrice = parseFloat($('#sale_price').val()) || 0;
                const profitLoss = salePrice - bookValue;

                let profitLossText = '';
                if (profitLoss > 0) {
                    profitLossText = '<p class="text-success"><strong>ربح:</strong> ' + profitLoss.toFixed(2) + '</p>';
                } else if (profitLoss < 0) {
                    profitLossText = '<p class="text-danger"><strong>خسارة:</strong> ' + Math.abs(profitLoss).toFixed(2) + '</p>';
                }

                Swal.fire({
                    title: 'تأكيد بيع الأصل',
                    html: `
                        <div class="text-right" dir="rtl">
                            <p><strong>الأصل:</strong> {{ $asset->name }}</p>
                            <p><strong>القيمة الدفترية:</strong> ${bookValue.toFixed(2)}</p>
                            <p><strong>سعر البيع:</strong> ${salePrice.toFixed(2)}</p>
                            ${profitLossText}
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'نعم، تأكيد البيع',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitBtn.prop('disabled', true);
                        submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري الحفظ...');
                        form.submit();
                    }
                });
            });
        });
    </script>
@endsection
