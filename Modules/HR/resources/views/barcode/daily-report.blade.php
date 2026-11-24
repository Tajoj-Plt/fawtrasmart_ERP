@extends('hr::master')

@section('title', 'تقرير الحضور اليومي')

@push('styles')
<style>
    .bdr-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:16px; }
    .bdr-stat { background: var(--app-surface, #fff); border-radius:10px; padding:14px 18px; box-shadow:0 1px 3px rgba(0,0,0,.04); border-inline-start:3px solid var(--app-primary, #6366f1); }
    .bdr-stat.green { border-inline-start-color: var(--app-success, #10b981); }
    .bdr-stat.amber { border-inline-start-color: var(--app-warning, #f59e0b); }
    .bdr-stat.red   { border-inline-start-color: var(--app-danger, #ef4444); }
    .bdr-stat .label { font-size:.78rem; color: var(--app-muted, #6b7280); }
    .bdr-stat .value { font-size:1.5rem; font-weight:700; color: var(--app-text, #1f2937); margin-top:4px; }
    .bdr-stat.green .value { color: var(--app-success, #065f46); }
    .bdr-stat.amber .value { color: var(--app-warning, #92400e); }
    .bdr-stat.red   .value { color: var(--app-danger, #991b1b); }
</style>
@endpush

@section('content')
    @include('hr::attendance._partials.styles')

    <x-layout.breadcrumb
        title="تقرير الحضور اليومي"
        :items="[
            ['title' => 'الموارد البشرية', 'url' => route('attendance_sheets.index')],
            ['title' => 'الحضور بالباركود'],
            ['title' => 'التقرير اليومي']
        ]"
    />

    <div class="att-hero blue">
        <div>
            <h2><i class="feather icon-clipboard"></i> تقرير الحضور اليومي</h2>
            <div class="sub">حضور يوم {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} ونصيب الباركود منه</div>
        </div>
        <form method="GET" class="att-toolbar">
            <input type="date" name="date" class="form-control" value="{{ $date }}" style="height:38px;">
            <button type="submit" class="btn btn-primary">
                <i class="feather icon-search me-2"></i>عرض
            </button>
        </form>
    </div>

    <div class="bdr-stats">
        <div class="bdr-stat green">
            <div class="label">حاضر</div>
            <div class="value">{{ $stats['total_present'] }}</div>
        </div>
        <div class="bdr-stat amber">
            <div class="label">متأخر</div>
            <div class="value">{{ $stats['total_late'] }}</div>
        </div>
        <div class="bdr-stat red">
            <div class="label">لم يُسجَّل له يوم</div>
            <div class="value">{{ max(0, $stats['total_absent']) }}</div>
        </div>
        <div class="bdr-stat">
            <div class="label">سُجِّل بالباركود</div>
            <div class="value">{{ $stats['barcode_usage'] }}</div>
        </div>
    </div>

    <div class="att-card">
        <div class="att-card-head">
            <span><i class="feather icon-list"></i> السجلات ({{ $attendances->count() }})</span>
        </div>
        <div class="att-card-body p-0">
            @if($attendances->count() > 0)
                <div class="table-responsive">
                    <table class="att-table">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>القسم</th>
                                <th>الحضور</th>
                                <th>الانصراف</th>
                                <th>الحالة</th>
                                <th>الوسيلة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attendances as $row)
                                <tr>
                                    <td><strong>{{ optional($row->employee)->full_name ?? '—' }}</strong></td>
                                    <td>{{ optional(optional($row->employee)->department)->name ?? '—' }}</td>
                                    <td>{{ $row->check_in_time ?? '—' }}</td>
                                    <td>{{ $row->check_out_time ?? '—' }}</td>
                                    <td>
                                        @if($row->status === 'present')
                                            <span class="att-pill success">حاضر</span>
                                        @elseif($row->status === 'late')
                                            <span class="att-pill warning">متأخر</span>
                                        @elseif($row->status === 'absent')
                                            <span class="att-pill danger">غائب</span>
                                        @else
                                            <span class="att-pill secondary">{{ $row->status ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($row->scanned_via_barcode)
                                            <span class="att-pill info"><i class="feather icon-maximize"></i> باركود</span>
                                        @else
                                            <span class="att-pill secondary">يدوي</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="att-empty">
                    <i class="feather icon-inbox"></i>
                    <h5 class="mb-1">لا توجد سجلات في هذا اليوم</h5>
                    <p class="mb-0">اختر تاريخاً آخر من الأعلى.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
