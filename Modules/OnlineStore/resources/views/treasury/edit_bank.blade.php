@extends('onlinestore::master')

@section('title', 'تعديل حساب بنكي')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_treasury.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('account.treasury.edit'))
                <button type="submit" form="bank_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ التعديلات
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل حساب بنكي',
            'icon' => 'credit-card',
            'sub' => $treasury->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الخزائن والحسابات البنكية' => route('store_treasury.index'),
                'تعديل حساب بنكي' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="bank_form" class="form form-vertical" action="{{ route('store_treasury.update_bank', $account->id) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات الحساب البنكي</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="type">النوع</label>
                            <input type="text" disabled id="type" class="form-control" value="حساب بنكي">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="name" class="stk-req">الاسم</label>
                            <input type="text" id="name" class="form-control" name="name" value="{{ old('name', $treasury->name) }}" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-6">
                            <label for="bank_name" class="stk-req">البنك</label>
                            <select id="bank_name" class="form-control" name="bank_name" required>
                                <option value="">-- اختر البنك --</option>
                                @foreach ($banks as $code => $bankName)
                                    <option value="{{ $code }}" @selected(old('bank_name', $treasury->bank_name) === $code)>{{ $bankName }}</option>
                                @endforeach
                            </select>
                            @error('bank_name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-6">
                            <label for="account_number" class="stk-req">رقم الآيبان (IBAN)</label>
                            <input type="text" id="account_number" class="form-control" name="account_number"
                                   value="{{ \App\Constants\Banks::formatIban(old('account_number', $treasury->account_number)) }}"
                                   placeholder="SA00 0000 0000 0000 0000 0000"
                                   dir="ltr" style="text-align:left; letter-spacing:.5px"
                                   autocomplete="off" required>
                            <small class="text-muted">يبدأ برمز الدولة ثم خانتَي تحقّق — ويجب أن يطابق البنك المختار</small>
                            @error('account_number')<small class="text-danger d-block">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-6">
                            @include('account::components.currency-select', [
                                'name' => 'currency',
                                'label' => 'العملة',
                                'selected' => old('currency', $treasury->currency),
                            ])
                            @error('currency')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-6">
                            <label for="status">الحالة</label>
                            <select class="form-control" id="status" name="status">
                                <option value="1" @selected(old('status', $treasury->status) == 1)>نشط</option>
                                <option value="0" @selected(old('status', $treasury->status) == 0)>غير نشط</option>
                            </select>
                        </div>

                        @include('onlinestore::treasury.partials._branch-field', ['branches' => $branches, 'selected' => $account->branch_id])

                        <div class="form-group col-12">
                            <label for="description">الوصف</label>
                            <textarea name="description" class="form-control" id="description" rows="2">{{ old('description', $treasury->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
