@extends('hr::master')
@section('title', 'طلبات العمل الإضافي')

@push('styles')
<style>
    .ot-pill { font-size:.7rem; padding:2px 9px; border-radius:999px; }
    .ot-pill--pending  { background: color-mix(in srgb, var(--app-warning, #d97706) 15%, transparent); color: var(--app-warning, #92400e); }
    .ot-pill--approved { background: color-mix(in srgb, var(--app-success, #16a34a) 15%, transparent); color: var(--app-success, #15803d); }
    .ot-pill--rejected { background: color-mix(in srgb, var(--app-danger, #dc2626) 15%, transparent); color: var(--app-danger, #b91c1c); }
    .ot-pill--cancelled{ background: var(--app-body-bg, #f3f4f6); color: var(--app-muted, #6b7280); }
    .ot-table td, .ot-table th { vertical-align:middle; font-size:.85rem; }
</style>
@endpush

@php
    // كلُّ زرٍّ بصلاحيته — وعلى من يحدّده النطاق (المتحكّمُ قصر القائمة عليه)
    $otCanShow = hasPermission('overtime.show');
    $otCanCreate = hasPermission('overtime.create');
@endphp

@section('page-actions')
    @if ($otCanCreate)
        <a href="{{ route('hr.overtime-requests.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> طلب جديد
        </a>
    @endif
@endsection

@section('content')
@include('stock::components.page-head', [
    'title' => 'طلبات العمل الإضافي',
    'crumbs' => ['الرواتب' => null, 'طلبات العمل الإضافي' => null],
])

<div class="content-body">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    @unless($requiresApproval)
        {{-- بلا المفتاح لا أثر لهذه الطلبات على القسيمة — قولُها أصدق من تركها تُقدَّم بلا جدوى --}}
        <div class="alert alert-warning" style="font-size:.85rem;">
            مفتاحُ «الاعتماد المسبق» معطَّل حالياً، فكلُّ الدقائق المسجَّلة تُصرف بلا طلب.
            هذه الطلباتُ تُحفظ ولا تُقيّد شيئاً حتى يُفعَّل المفتاح من
            @if (hasPermission('overtime.settings'))<a href="{{ route('SalarySittings.overtime') }}">إعدادات الأجر الإضافي</a>@else إعدادات الأجر الإضافي@endif .
        </div>
    @endunless

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <form method="GET" class="d-flex gap-2 m-0" style="min-width: 200px">
            <select name="status" class="form-control form-control-sm hr-s2" onchange="this.form.submit()">
                <option value="">كل الحالات</option>
                @foreach(['pending' => 'قيد الاعتماد', 'approved' => 'معتمَد', 'rejected' => 'مرفوض', 'cancelled' => 'ملغى'] as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover ot-table">
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>تاريخ العمل</th>
                    <th>المطلوب</th>
                    <th>المعتمَد</th>
                    <th>الحالة</th>
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
                        <td>{{ $item->approved_minutes !== null ? $item->approved_minutes.' د' : '—' }}</td>
                        <td><span class="ot-pill ot-pill--{{ $item->status }}">{{ $item->statusLabel() }}</span></td>
                        <td>{{ $item->requestedBy?->name ?? 'النظام' }}</td>
                        @php
                            $rowShow = $otCanShow;
                            $rowCancel = $otCanCreate && $item->canBeDecided();
                        @endphp
                        <td class="text-center">
                            @if ($rowShow || $rowCancel)
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if ($rowShow)
                                        <a class="dropdown-item" href="{{ route('hr.overtime-requests.show', $item->id) }}"><i class="fa fa-eye me-2 text-primary"></i>عرض</a>
                                    @endif
                                    @if($rowCancel)
                                        @if ($rowShow)<div class="dropdown-divider"></div>@endif
                                        <form method="POST" action="{{ route('hr.overtime-requests.cancel', $item->id) }}" class="m-0" onsubmit="return confirm('سحب الطلب؟');">
                                            @csrf @method('PUT')
                                            <button type="submit" class="dropdown-item text-danger"><i class="fa fa-times me-2"></i>سحب الطلب</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">لا توجد طلبات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $requests->links() }}
</div>

@include('hr::components.table-menu')
@endsection
