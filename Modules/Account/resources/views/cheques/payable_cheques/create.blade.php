@extends('account::master')
@section('title')
إصدار شيك
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('payable_cheques.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="chequeForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الشيك
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'إصدار شيك',
        'crumbs' => [
            'الشيكات الصادرة' => route('payable_cheques.index'),
            'إصدار شيك جديد' => null,
        ],
    ])


    <div class="content-body">
        <div class="container-fluid">
            <form class="form-horizontal" action="{{ route('payable_cheques.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="card">
                    <div class="card-content">
                        <div class="card-body">
                            <h4 class="card-title"><i class="fa fa-money-check text-primary"></i> معلومات الشيك</h4>
                        </div>

                        <div class="card-body">
                            <div class="form-body row">

                                <!-- دفتر الشيكات (الاختيار الأول) -->
                                <div class="form-group col-md-6">
                                    <label for="cheque_book_id">دفتر الشيكات <span class="stk-req"></span></label>
                                    <select id="cheque_book_id" class="form-control select2" name="cheque_book_id" required>
                                        <option value="" disabled selected>اختر دفتر الشيكات</option>
                                        @foreach ($check_books as $check_book)
                                            <option value="{{ $check_book->id }}" 
                                                data-bank-name="{{ $check_book->bank->name ?? 'غير محدد' }}"
                                                data-currency="{{ $check_book->currency }}"
                                                {{ old('cheque_book_id') == $check_book->id ? 'selected' : '' }}>
                                                {{ $check_book->cheque_book_number }} - {{ $check_book->bank->name ?? '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-create-first
                                        :count="count($check_books ?? [])"
                                        route="check_books.create"
                                        permission="account.cheque_books.create"
                                        :plan="\App\Constants\planFeatures\AccountPlanFeatures::CHECK_BOOKS"
                                        label="دفتر شيكات"
                                        hint="الشيك الصادر يُسحب من دفتر، ولا دفاتر مسجَّلة بعد." />
                                    <small class="text-muted" id="bank_info"></small>
                                </div>

                                <!-- رقم الشيك -->
                                <div class="form-group col-md-6">
                                    <label for="cheque_number">رقم الشيك <span class="stk-req"></span></label>
                                    <input type="number" id="cheque_number" class="form-control" name="cheque_number" value="{{ old('cheque_number') }}" required>
                                </div>

                                <!-- المبلغ -->
                                <div class="form-group col-md-6">
                                    <label for="amount">المبلغ <span class="stk-req"></span></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" id="amount" class="form-control" name="amount" value="{{ old('amount') }}" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text" id="currency_display">---</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- تاريخ الإصدار -->
                                <div class="form-group col-md-6">
                                    <label for="issue_date">تاريخ الإصدار <span class="stk-req"></span></label>
                                    <input type="date" id="issue_date" class="form-control" name="issue_date" value="{{ old('issue_date', date('Y-m-d')) }}" required>
                                </div>

                                <!-- تاريخ الاستحقاق -->
                                <div class="form-group col-md-6">
                                    <label for="due_date">تاريخ الاستحقاق</label>
                                    <input type="date" id="due_date" class="form-control" name="due_date" value="{{ old('due_date') }}">
                                </div>

                                <!-- الجهة المستلمة (الحساب) -->
                                <div class="form-group col-md-6">
                                    <label for="recipient_account_id">الحساب المستلم (الجهة المستفيدة) <span class="stk-req"></span></label>
                                    <select class="form-control select2" id="recipient_account_id" name="recipient_account_id" required>
                                        <option value="" disabled selected>اختر الحساب المستلم</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}" {{ old('recipient_account_id') == $account->id ? 'selected' : '' }}>
                                                {{ $account->code }} - {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">الحساب الذي سيُخصم من حساب الشيكات تحت التحصيل لصالحه</small>
                                </div>

                                <!-- الاسم على الشيك -->
                                <div class="form-group col-md-6">
                                    <label for="payee_name">الاسم على الشيك <span class="stk-req"></span></label>
                                    <input type="text" id="payee_name" class="form-control" name="payee_name" value="{{ old('payee_name') }}" required>
                                </div>

                                <!-- المرفق -->
                                <div class="form-group col-md-6">
                                    <label for="attachment">المرفق</label>
                                    <input type="file" id="attachment" class="form-control" name="attachment">
                                    <small class="text-muted">الأنواع المدعومة: صور، PDF، Word، Excel (حد أقصى 5 ميجا)</small>
                                </div>

                                <!-- الوصف -->
                                <div class="form-group col-md-12">
                                    <label for="description">الوصف</label>
                                    <textarea id="description" class="form-control" rows="3" placeholder="أدخل وصف أو ملاحظات للشيك" name="description">{{ old('description') }}</textarea>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                    <div class="stk-form-foot">
                        <a href="{{ route('payable_cheques.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> حفظ الشيك
                        </button>
                    </div>

            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chequeBookSelect = document.getElementById('cheque_book_id');
            const bankInfo = document.getElementById('bank_info');
            const currencyDisplay = document.getElementById('currency_display');

            // تحديث معلومات البنك والعملة عند اختيار دفتر الشيكات
            function updateBankInfo() {
                const selectedOption = chequeBookSelect.options[chequeBookSelect.selectedIndex];
                
                if (selectedOption && selectedOption.value) {
                    const bankName = selectedOption.getAttribute('data-bank-name');
                    const currency = selectedOption.getAttribute('data-currency');
                    
                    bankInfo.innerHTML = '<i class="fa fa-university"></i> البنك: <strong>' + (bankName || 'غير محدد') + '</strong>';
                    currencyDisplay.textContent = currency || '---';
                } else {
                    bankInfo.innerHTML = '';
                    currencyDisplay.textContent = '---';
                }
            }

            // استماع لتغيير دفتر الشيكات
            chequeBookSelect.addEventListener('change', updateBankInfo);

            // تحديث عند التحميل (في حالة وجود قيمة محددة مسبقاً)
            if (chequeBookSelect.value) {
                updateBankInfo();
            }
        });
    </script>
@endsection

