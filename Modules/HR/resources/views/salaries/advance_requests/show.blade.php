@extends('hr::master')

@section('title')
    طلب السلفة #{{ $request->id }}
@stop

@section('content')
    @section('page-actions')
        @php
            $canWithdraw = $request->status === 'pending' && hasPermission('loans.create');
            // الطباعة بالقوالب — تظهر حين يُسجَّل مسارها
            $advPrintRoute = collect(['hr.advances.print', 'hr.advance.print', 'hr.advance-requests.print'])->first(fn ($r) => \Illuminate\Support\Facades\Route::has($r));
        @endphp
        @if ($advPrintRoute && hasPermission('loans.show'))
            <a href="{{ route($advPrintRoute, $request->id) }}" target="_blank" class="btn btn-outline-primary">
                <i class="fas fa-print"></i> طباعة
            </a>
        @endif
        @if ($canWithdraw)
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-h"></i> المزيد
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <form action="{{ route('hr.advance-requests.cancel', $request->id) }}" method="POST"
                              onsubmit="return confirm('سحب الطلب؟ سيُغلق «ملغى» ولا يصل إلى المعتمِد.')">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="dropdown-item text-danger"><i class="fas fa-times me-2"></i>سحب الطلب</button>
                        </form>
                    </li>
                </ul>
            </div>
        @endif
    @endsection

    @include('stock::components.page-head', [
        'title' => 'طلب السلفة #' . $request->id,
        'icon' => 'arrow-up-circle',
        'crumbs' => ['الرئيسية' => route('hr.department.dashboard'), 'طلبات السلف' => route('hr.advance-requests.index'), 'تفاصيل الطلب' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> تفاصيل السلفة</h6>
                <span class="stk-state {{ $request->stageClass() }}">{{ $request->stageLabel() }}</span>
            </div>
            <div class="card-body">
                @include('hr::salaries.advance_requests.partials.details', ['request' => $request])
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-activity"></i> سجلّ السلفة</h6>
            </div>
            <div class="card-body">
                @include('hr::salaries.advance_requests.partials.timeline', ['logs' => $logs])
            </div>
        </div>
    </div>
@endsection
