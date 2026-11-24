@extends('hr::master')
@section('title', 'بتّ في طلب عمل إضافي')

@section('content')
@include('stock::components.page-head', [
    'title' => 'طلب عمل إضافي #'.$overtimeRequest->id,
    'crumbs' => ['اعتماد العمل الإضافي' => route('hr.overtime-approvals.index'), 'بتّ' => null],
])

<div class="content-body">
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            @include('hr::salaries.overtime_requests._detail')
        </div>
    </div>

    @if($overtimeRequest->canBeDecided())
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="stk-table-head__title">اعتماد</h6>
                        <p class="stk-help">
                            الدقائقُ المعتمَدة سقفٌ لا مِنحة — يُصرف الأقلُّ منها ومن المسجَّل فعلاً
                            ({{ $recordedMinutes }} دقيقة).
                        </p>
                        <form method="POST" action="{{ route('hr.overtime-approvals.approve', $overtimeRequest->id) }}">
                            @csrf
                            <div class="form-group">
                                <label>الدقائق المعتمَدة</label>
                                <input type="number" name="approved_minutes" class="form-control" min="0" max="1440" required
                                       value="{{ old('approved_minutes', $overtimeRequest->requested_minutes) }}">
                            </div>
                            <div class="form-group">
                                <label>ملاحظات</label>
                                <textarea name="admin_notes" class="form-control" rows="2" maxlength="1000">{{ old('admin_notes') }}</textarea>
                            </div>
                            <button class="btn btn-success">اعتماد</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="stk-table-head__title">رفض</h6>
                        <form method="POST" action="{{ route('hr.overtime-approvals.reject', $overtimeRequest->id) }}">
                            @csrf
                            <div class="form-group">
                                <label>سبب الرفض</label>
                                <textarea name="rejection_reason" class="form-control" rows="4" maxlength="255" required>{{ old('rejection_reason') }}</textarea>
                            </div>
                            <button class="btn btn-danger">رفض</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info">سبق البتّ في هذا الطلب.</div>
    @endif

    <a href="{{ route('hr.overtime-approvals.index') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
</div>
@endsection
