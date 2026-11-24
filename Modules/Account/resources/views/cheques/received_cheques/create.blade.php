@extends('account::master')

@section('title')

@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('received_cheques.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="chequeForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الشيك
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'الشيكات المستلمه',
        'crumbs' => [
            'الرئيسيه' => null,
            'استلم شيك' => null,
        ],
    ])


    <div class="content-body">
        <div class="container-fluid">
            <form class="form-horizontal" action="{{ route('received_cheques.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="card">
                    <div class="card-content">
                        <div class="card-body">
                            <h4 class="card-title">معلومات الشيك المستلم</h4>
                        </div>

                        <div class="card-body">
                            <div class="form-body row">

                                <div class="form-group col-md-6">
                                    <label for="">المبلغ <span class="stk-req"></span></label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="amount" value="{{ old('amount') }}">
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="">رقم الشيك <span class="stk-req"></span></label>
                                    <input type="number" class="form-control" name="cheque_number" value="{{ old('cheque_number') }}">
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="">تاريخ الإصدار <span class="stk-req"></span></label>
                                    <input type="date" id="issue_date" class="form-control" name="issue_date" value="{{ old('issue_date') }}">
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="">تاريخ الاستحقاق </label>
                                    <input type="date" id="due_date" class="form-control" name="due_date" value="{{ old('due_date') }}">
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="">حساب العميل (المستلم منه) <span class="stk-req"></span></label>
                                    <select class="form-control select2" name="recipient_account_id">
                                        <option value="" disabled selected>اختر حساب العميل</option>
                                        @if(isset($client_accounts))
                                            @foreach($client_accounts as $account)
                                                <option value="{{ $account->id }}" {{ old('recipient_account_id') == $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <x-create-first
                                        :count="count($client_accounts ?? [])"
                                        route="accounts_chart.view_root"
                                        permission="account.chart.view"
                                        :plan="\App\Constants\planFeatures\AccountPlanFeatures::CHART_OF_ACCOUNTS"
                                        label="حساب عميل"
                                        hint="حسابات العملاء تُنشأ من دليل الحسابات، ولا حساب عميل بعد." />
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="">الاسم على الشيك <span class="stk-req"></span></label>
                                    <input type="text" class="form-control" name="payee_name" value="{{ old('payee_name') }}">
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="">بنك الساحب </label>
                                    <input type="text" class="form-control" name="drawer_bank" value="{{ old('drawer_bank') }}" placeholder="البنك المكتوب على الشيك">
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="">فرع الساحب </label>
                                    <input type="text" class="form-control" name="drawer_branch" value="{{ old('drawer_branch') }}">
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="">بنك الإيداع المتوقَّع </label>
                                    <select class="form-control select2" name="bank_account_id">
                                        <option value="">لم يُحدَّد بعد (يُختار لاحقاً عند الإيداع للتحصيل)</option>
                                        @if(isset($bank_accounts))
                                            @foreach($bank_accounts as $account)
                                                <option value="{{ $account->id }}" {{ old('bank_account_id') == $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <x-create-first
                                        :count="count($bank_accounts ?? [])"
                                        route="accounts_chart.view_root"
                                        permission="account.chart.view"
                                        :plan="\App\Constants\planFeatures\AccountPlanFeatures::CHART_OF_ACCOUNTS"
                                        label="حساباً بنكياً"
                                        hint="الحسابات البنكية تُنشأ من دليل الحسابات، ولا حساب بنكي بعد." />
                                    <small class="form-text text-muted">لا يُنشئ أي قيدٍ محاسبي الآن — القيد الفعلي يُكتب عند تنفيذ «إيداع للتحصيل»</small>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="">المرفق </label>
                                    <input type="file" class="form-control" name="attachment">
                                </div>

                                <div class="form-group col-md-12">
                                    <label for="">الوصف </label>
                                    <textarea class="form-control" rows="2" placeholder="أدخل الوصف" name="description">{{ old('description') }}</textarea>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                    <div class="stk-form-foot">
                        <a href="{{ route('received_cheques.index') }}" class="btn btn-outline-secondary">
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



