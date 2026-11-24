{{--
    نموذج دفتر الشيكات — مشترك بين الإنشاء والتعديل.

    كانت الشاشتان نسختين متطابقتين تقريباً (152 و151 سطراً) بترميزهما
    وسكربتهما: تصحيحٌ في إحداهما يُنسى في الأخرى. وثلاثة حقول تحمل المعرّف
    `basicSelect` نفسه في صفحةٍ واحدة — معرّفٌ مكرَّر يكسر ربط `label`
    بحقله ويُربك select2.

    الوسائط: `action`، و`book` (اختياري للتعديل)، و`bank_accounts`،
    و`cheque_book_number`.
--}}

@php
    $book = $book ?? null;
    $isEdit = (bool) $book;
@endphp

{{-- مسار التحديث مسجَّل `POST` لا `PUT`، فلا انتحال طريقة هنا --}}
<form action="{{ $action }}" method="POST" id="chequeBookForm">
    @csrf

    <div class="card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-book"></i> معلومات دفتر الشيكات
            </h6>
            <span class="stk-help">الحقول المعلَّمة <span class="stk-req"></span> إلزامية</span>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-6 col-sm-12">
                    <div class="form-group">
                        <label for="bank_id">البنك <span class="stk-req"></span></label>
                        <select class="form-control select2" id="bank_id" name="bank_id" required>
                            <option value="">اختر البنك</option>
                            @foreach ($bank_accounts as $bank_account)
                                <option value="{{ $bank_account->id }}"
                                    @selected(old('bank_id', $book->bank_id ?? null) == $bank_account->id)>
                                    {{ $bank_account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-6 col-sm-12">
                    <div class="form-group">
                        <label for="cheque_book_number">رقم الدفتر <span class="stk-req"></span></label>
                        <input type="text" id="cheque_book_number" name="cheque_book_number" class="form-control"
                            value="{{ old('cheque_book_number', $book->cheque_book_number ?? $cheque_book_number) }}"
                            readonly>
                        <small class="stk-help">يُولَّد تلقائياً</small>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="start_serial_number">أول رقم مسلسل <span class="stk-req"></span></label>
                        <input type="number" id="start_serial_number" name="start_serial_number" class="form-control"
                            value="{{ old('start_serial_number', $book->start_serial_number ?? '') }}" min="1"
                            required>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="end_serial_number">آخر رقم مسلسل <span class="stk-req"></span></label>
                        <input type="number" id="end_serial_number" name="end_serial_number" class="form-control"
                            value="{{ old('end_serial_number', $book->end_serial_number ?? '') }}" min="1" required>
                    </div>
                </div>

                <div class="col-md-6 col-sm-12">
                    <div class="form-group">
                        <label>عدد الأوراق</label>
                        <div class="stk-preview">
                            <span class="stk-preview__label">أوراق الدفتر</span>
                            <strong id="cheque_count" class="stk-num">0</strong>
                        </div>
                        <small id="serial_error" class="text-danger"></small>
                    </div>
                </div>

                <div class="col-md-6 col-sm-12">
                    <div class="form-group">
                        @include('account::components.currency-select', [
                            'name' => 'currency',
                            'id' => 'currency',
                            'label' => 'عملة الدفتر',
                            'class' => 'form-control select2',
                            'required' => true,
                            'selected' => old('currency', $book->currency ?? null),
                        ])
                    </div>
                </div>

                <div class="col-md-6 col-sm-12">
                    <div class="form-group">
                        <label for="status">الحالة <span class="stk-req"></span></label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="1" @selected(old('status', $book->status ?? 1) == 1)>نشط</option>
                            <option value="0" @selected(old('status', $book->status ?? 1) == 0)>غير نشط</option>
                        </select>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-group">
                        <label for="notes">الملاحظات</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"
                            placeholder="ما يُميّز هذا الدفتر عند مراجعته لاحقاً">{{ old('notes', $book->notes ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="stk-form-foot">
            <a href="{{ route('check_books.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="feather icon-save"></i> {{ $isEdit ? 'حفظ التغييرات' : 'حفظ الدفتر' }}
            </button>
        </div>
    </div>
</form>

@section('scripts')
    <script>
        (function () {
            var start = document.getElementById('start_serial_number');
            var end = document.getElementById('end_serial_number');
            var count = document.getElementById('cheque_count');
            var error = document.getElementById('serial_error');

            /** أقصى ما يسعه دفترٌ واحد */
            var MAX_LEAVES = 150;

            function recount() {
                var from = parseInt(start.value, 10) || 0;
                var to = parseInt(end.value, 10) || 0;

                if (! from || ! to) {
                    count.textContent = '0';
                    error.textContent = '';
                    return;
                }

                if (to < from) {
                    count.textContent = '0';
                    error.textContent = 'الرقم الأخير يجب أن يساوي الأول أو يزيد عليه.';
                    return;
                }

                var leaves = to - from + 1;
                count.textContent = leaves.toLocaleString('en');

                error.textContent = leaves > MAX_LEAVES
                    ? 'لا يتجاوز الدفتر الواحد ' + MAX_LEAVES + ' ورقة.'
                    : '';
            }

            start.addEventListener('input', recount);
            end.addEventListener('input', recount);
            recount();
        })();
    </script>
@endsection
