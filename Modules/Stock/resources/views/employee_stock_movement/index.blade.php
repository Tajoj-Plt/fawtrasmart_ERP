@extends('stock::master')

@section('title', 'تحركات المخزون للموظف')

@section('content')
<div class="container-fluid">
    @include('stock::components.page-head', [
        'title' => 'حركة المخزون',
        'icon' => 'user-check',
        'sub' => 'المستودع: '.($currentStorehouse->name ?? 'غير محدّد'),
        'crumbs' => [
            'الرئيسية' => url('/'),
            'حركة المخزون' => null,
        ],
    ])

    <!-- Warehouse Selector -->
    <div class="card card-custom gutter-b mb-4">
        <div class="card-body">
            <form action="{{ route('stock.employee_movements') }}" method="GET" class="form-inline">
                <label for="storehouse_id" class="mr-2 font-weight-bold">اختر المستودع:</label>
                <select name="storehouse_id" id="storehouse_id" class="form-control mr-2" onchange="this.form.submit()">
                    @foreach($allWarehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ (isset($currentStorehouse) && $currentStorehouse->id == $warehouse->id) ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-primary">عرض</button></noscript>
            </form>
        </div>
    </div>

    <div class="card card-custom gutter-b">
        <div class="card-header card-header-tabs-line">
            <div class="card-toolbar">
                <ul class="nav nav-tabs nav-bold nav-tabs-line">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#incoming_tab">
                            <span class="nav-icon"><i class="flaticon2-download-1"></i></span>
                            <span class="nav-text">وارد (Incoming)</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#outgoing_tab">
                            <span class="nav-icon"><i class="flaticon2-upload-1"></i></span>
                            <span class="nav-text">صادر (Outgoing)</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <!-- Incoming Tab -->
                <div class="tab-pane fade show active" id="incoming_tab" role="tabpanel">
                    @if($incoming->isEmpty())
                        <div class="alert alert-info text-center">لا توجد تحركات واردة.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead style="background-color: #f0f3f5;">
                                    <tr>
                                        <th>#</th>
                                        <th>التاريخ</th>
                                        <th>نوع العملية</th>
                                        <th>رقم المستند</th>
                                        <th>إجمالي الكمية</th>
                                        <th>الحالة</th>
                                        <th>عرض</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($incoming as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->doc_date }}</td>
                                        <td><span class="badge badge-light-info">{{ $item->type_label }}</span></td>
                                        <td>{{ $item->doc_number }}</td>
                                        <td>
                                            <span class="font-weight-bold text-success">
                                                {{ number_format($item->total_qty, 2) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-success">
                                                {{ $item->status }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($item->source_type == 'permit')
                                                <a href="{{ route('store_permits_management.show', $item->id) }}" class="btn btn-sm btn-icon btn-light-primary" target="_blank"><i class="fa fa-eye"></i></a>
                                            @elseif($item->source_type == 'invoice')
                                                @if(Route::has('invoices.show'))
                                                <a href="{{ route('invoices.show', $item->id) }}" class="btn btn-sm btn-icon btn-light-primary" target="_blank"><i class="fa fa-eye"></i></a>
                                                @else
                                                <span class="text-muted">-</span>
                                                @endif
                                            @elseif($item->source_type == 'purchase')
                                                @if(Route::has('purchase_invoices.show'))
                                                <a href="{{ route('purchase_invoices.show', $item->id) }}" class="btn btn-sm btn-icon btn-light-primary" target="_blank"><i class="fa fa-eye"></i></a>
                                                @else
                                                <span class="text-muted">-</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Outgoing Tab -->
                <div class="tab-pane fade" id="outgoing_tab" role="tabpanel">
                     @if($outgoing->isEmpty())
                        <div class="alert alert-info text-center">لا توجد تحركات صادرة.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead style="background-color: #f0f3f5;">
                                    <tr>
                                        <th>#</th>
                                        <th>التاريخ</th>
                                        <th>نوع العملية</th>
                                        <th>رقم المستند</th>
                                        <th>إجمالي الكمية</th>
                                        <th>الحالة</th>
                                        <th>عرض</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($outgoing as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->doc_date }}</td>
                                        <td><span class="badge badge-light-info">{{ $item->type_label }}</span></td>
                                        <td>{{ $item->doc_number }}</td>
                                        <td>
                                             <span class="font-weight-bold text-danger">
                                                {{ number_format($item->total_qty, 2) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-success">
                                                {{ $item->status }}
                                            </span>
                                        </td>
                                        <td>
                                           @if($item->source_type == 'permit')
                                                <a href="{{ route('store_permits_management.show', $item->id) }}" class="btn btn-sm btn-icon btn-light-primary" target="_blank"><i class="fa fa-eye"></i></a>
                                            @elseif($item->source_type == 'invoice')
                                                @if(Route::has('invoices.show'))
                                                <a href="{{ route('invoices.show', $item->id) }}" class="btn btn-sm btn-icon btn-light-primary" target="_blank"><i class="fa fa-eye"></i></a>
                                                @else
                                                <span class="text-muted">-</span>
                                                @endif
                                            @elseif($item->source_type == 'purchase')
                                                @if(Route::has('purchase_invoices.show'))
                                                <a href="{{ route('purchase_invoices.show', $item->id) }}" class="btn btn-sm btn-icon btn-light-primary" target="_blank"><i class="fa fa-eye"></i></a>
                                                @else
                                                <span class="text-muted">-</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
