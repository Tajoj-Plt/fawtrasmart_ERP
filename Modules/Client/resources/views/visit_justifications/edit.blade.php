@extends('client::master')

@section('title')
    تعديل فترة تبرير/إعفاء
@stop

@section('content')
<div class="cl-scope">
    @include('layouts.alerts.error')

    @include('client::components.page-head', [
        'title' => 'تعديل فترة تبرير/إعفاء #'.$period->id,
        'icon' => 'calendar-plus',
        'sub' => 'تعديل التعديل لا يمسّ صلاحية أي زيارةٍ سابقة كانت غطّتها هذه الفترة تلقائياً — يُعاد فحص التغطية بالنطاق الجديد فور الحفظ.',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'العملاء' => route('clients.index'),
            'تبريرات الزيارات' => route('admin.visit-justifications.index'),
            'تعديل' => null,
        ],
    ])

    <div class="content-body">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.visit-justifications.update', $period->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>الموظف <span class="text-danger">*</span></label>
                            <div class="cl-select-add">
                                <select name="employee_id" class="form-control select2" required>
                                    <option value="">-- اختر الموظف --</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ old('employee_id', $period->employee_id) == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @permission('employees.create')
                                    @if (Route::has('employee.create'))
                                        <a href="{{ route('employee.create') }}" target="_blank" rel="noopener"
                                            class="btn btn-outline-primary cl-select-add__btn" title="إضافة موظف جديد">
                                            <i class="feather icon-plus"></i>
                                        </a>
                                    @endif
                                @endpermission
                            </div>
                            @error('employee_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6">
                            <label>العميل <span class="text-danger">*</span></label>
                            <div class="cl-select-add">
                                <select name="client_id" class="form-control select2" required>
                                    <option value="">-- اختر العميل --</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ old('client_id', $period->client_id) == $client->id ? 'selected' : '' }}>
                                            {{ $client->trade_name }} ({{ $client->code }})
                                        </option>
                                    @endforeach
                                </select>
                                @permission('clients_add_client')
                                    @if (Route::has('clients.create'))
                                        <a href="{{ route('clients.create') }}" target="_blank" rel="noopener"
                                            class="btn btn-outline-primary cl-select-add__btn" title="إضافة عميل جديد">
                                            <i class="feather icon-plus"></i>
                                        </a>
                                    @endif
                                @endpermission
                            </div>
                            @error('client_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6">
                            <label>من تاريخ <span class="text-danger">*</span></label>
                            <input type="date" name="date_from" class="form-control" value="{{ old('date_from', $period->date_from->format('Y-m-d')) }}" required>
                            @error('date_from') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6">
                            <label>إلى تاريخ <span class="text-danger">*</span></label>
                            <input type="date" name="date_to" class="form-control" value="{{ old('date_to', $period->date_to->format('Y-m-d')) }}" required>
                            @error('date_to') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-12">
                            <label>سبب التبرير/الإعفاء <span class="text-danger">*</span></label>
                            <input type="text" name="reason" class="form-control" maxlength="255" value="{{ old('reason', $period->reason) }}" required>
                            @error('reason') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-12">
                            <label>ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="3" maxlength="2000">{{ old('notes', $period->notes) }}</textarea>
                            @error('notes') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> حفظ التعديلات
                        </button>
                        <a href="{{ route('admin.visit-justifications.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
