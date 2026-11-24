@extends('hr::master')
@section('title', 'طلب عمل إضافي')

@section('content')
@include('stock::components.page-head', [
    'title' => 'طلب عمل إضافي',
    'crumbs' => ['طلبات العمل الإضافي' => route('hr.overtime-requests.index'), 'طلب جديد' => null],
])

<div class="content-body">
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <p class="stk-help">
                الاعتمادُ <strong>سقفٌ لا مِنحة</strong>: إن عمل الموظفُ أقلَّ ممّا اعتُمد صُرف ما عمله،
                وإن عمل أكثر صُرف السقف. والدقائقُ الفعلية تُسجَّل كاملةً على أي حال.
            </p>

            <form method="POST" action="{{ route('hr.overtime-requests.store') }}">
                @csrf
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>الموظف</label>
                        <select name="employee_id" class="form-control hr-s2" required>
                            <option value="">— اختر —</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>
                                    {{ $employee->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group col-md-3">
                        <label>تاريخ العمل الإضافي</label>
                        <input type="date" name="overtime_date" class="form-control" required
                               value="{{ old('overtime_date') }}">
                    </div>

                    <div class="form-group col-md-3">
                        <label>عدد الدقائق المطلوبة</label>
                        <input type="number" name="requested_minutes" class="form-control" min="15" max="1440" required
                               value="{{ old('requested_minutes') }}">
                        <small class="stk-help">ساعتان = 120 دقيقة</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>سبب العمل الإضافي</label>
                    <textarea name="reason" class="form-control" rows="3" maxlength="1000" required>{{ old('reason') }}</textarea>
                </div>

                <button class="btn btn-primary">تقديم الطلب</button>
                <a href="{{ route('hr.overtime-requests.index') }}" class="btn btn-outline-secondary">إلغاء</a>
            </form>
        </div>
    </div>
</div>
@endsection
