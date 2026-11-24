@extends('hr::master')

@section('title', 'الموظفون المؤرشفون')

@section('content')
    @include('stock::components.page-head', [
        'title' => 'الموظفون المؤرشفون',
        'icon' => 'archive',
        'sub' => 'من أُخرجوا من القائمة — بياناتهم وعلاقاتهم كما هي، وتُستردّ بنقرة',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الموظفون' => route('employee.index'),
            'المؤرشفون' => null,
        ],
    ])

    <div class="content-body">

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-archive"></i> الأرشيف
                </h6>
                <span class="stk-count">{{ $employees->total() }}</span>
            </div>

            <div class="card-body">
                <form method="GET" action="{{ route('employee.archived') }}" class="mb-2">
                    <div class="row">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control"
                                placeholder="اسم أو رقم موظف أو بريد" value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-search"></i> بحث
                            </button>
                        </div>
                    </div>
                </form>

                @if ($employees->isEmpty())
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-archive"></i></div>
                        <h5 class="stk-empty__title">لا موظفين مؤرشفين</h5>
                        <p class="stk-empty__hint">
                            الأرشفة تُخرج الموظف من القوائم ولا تمحو شيئاً من بياناته
                        </p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>رقم الموظف</th>
                                    <th>الاسم</th>
                                    <th>القسم</th>
                                    <th>الفرع</th>
                                    <th>الحالة عند الأرشفة</th>
                                    <th>تاريخ الأرشفة</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employees as $employee)
                                    @php $st = $employee->employmentStatus(); @endphp
                                    <tr>
                                        <td>
                                            @if ($employee->employee_code)
                                                <code>{{ $employee->employee_code }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $employee->full_name }}</td>
                                        <td>{{ optional($employee->department)->name ?? 'غير محدد' }}</td>
                                        <td>{{ optional($employee->branch)->name ?? 'غير محدد' }}</td>
                                        <td><span class="badge badge-{{ $st->badge() }}">{{ $st->label() }}</span></td>
                                        <td>{{ optional($employee->deleted_at)->format('Y-m-d H:i') }}</td>
                                        <td>
                                            {{-- الاستردادُ `POST`: يُعيد صفّاً إلى الحياة، فلا يقع بزيارة رابط --}}
                                            @permission('employees.archive')
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                                        <i class="fa fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-end">
                                                        <form action="{{ route('employee.restore', $employee->id) }}" method="POST" class="m-0">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item"><i class="fa fa-undo me-2 text-success"></i>استرداد</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endpermission
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $employees->links() }}
                @endif
            </div>
        </div>
    </div>

    @include('hr::components.table-menu')
@stop
