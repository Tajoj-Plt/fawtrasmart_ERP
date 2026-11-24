@extends('hr::master')
@section('title', 'اعتماد طلبات العمل الإضافي')

@section('content')
@include('stock::components.page-head', [
    'title' => 'اعتماد طلبات العمل الإضافي',
    'crumbs' => ['الرواتب' => null, 'اعتماد العمل الإضافي' => null],
])

<div class="content-body">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="table-responsive">
        <table class="table table-hover" style="font-size:.85rem;">
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>تاريخ العمل</th>
                    <th>المطلوب</th>
                    <th>السبب</th>
                    <th>مقدّم الطلب</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $item)
                    <tr>
                        <td>{{ $item->employee?->full_name ?? '—' }}</td>
                        <td>{{ $item->overtime_date->format('Y-m-d') }}</td>
                        <td>{{ $item->requested_minutes }} د</td>
                        <td style="max-width:320px;white-space:normal;">{{ \Illuminate\Support\Str::limit($item->reason, 80) }}</td>
                        <td>{{ $item->requestedBy?->name ?? 'النظام' }}</td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('hr.overtime-approvals.show', $item->id) }}"><i class="fa fa-gavel me-2 text-success"></i>مراجعة وبتّ</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">لا توجد طلبات تنتظر البتّ</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $requests->links() }}
</div>

@include('hr::components.table-menu')
@endsection
