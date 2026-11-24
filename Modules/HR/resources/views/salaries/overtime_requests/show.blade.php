@extends('hr::master')
@section('title', 'طلب عمل إضافي')

@section('page-actions')
    @php
        // السحبُ لصاحب overtime.create — والطلبُ داخل نطاقه أصلاً (المتحكّمُ يحرسه)
        $otCanCancel = hasPermission('overtime.create');
    @endphp
    @if($otCanCancel && $overtimeRequest->canBeDecided())
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <form method="POST" action="{{ route('hr.overtime-requests.cancel', $overtimeRequest->id) }}" class="m-0" onsubmit="return confirm('سحب الطلب؟');">
                        @csrf @method('PUT')
                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-times me-2"></i>سحب الطلب</button>
                    </form>
                </li>
            </ul>
        </div>
    @endif
@endsection

@section('content')
@include('stock::components.page-head', [
    'title' => 'طلب عمل إضافي #'.$overtimeRequest->id,
    'crumbs' => ['طلبات العمل الإضافي' => route('hr.overtime-requests.index'), 'تفاصيل' => null],
])

<div class="content-body">
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card">
        <div class="card-body">
            @include('hr::salaries.overtime_requests._detail')

            <a href="{{ route('hr.overtime-requests.index') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
        </div>
    </div>
</div>
@endsection
