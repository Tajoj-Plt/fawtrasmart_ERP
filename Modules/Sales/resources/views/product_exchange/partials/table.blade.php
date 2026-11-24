{{-- ملف: resources/views/sales/product_exchange/partials/table.blade.php --}}

@if ($exchanges->count() > 0)
    <div class="table-responsive">
        <div id="DataTables_Table_0_wrapper" class="dataTables_wrapper dt-bootstrap4">
            <div class="row align-items-center">
                <div class="col-12">
                    <div class="dataTables_length" id="DataTables_Table_0_length" style="text-align: left;">
                        <label style="margin-bottom: 0;">
                            عرض 
                            <select name="DataTables_Table_0_length" aria-controls="DataTables_Table_0" class="form-select form-select-sm d-inline-block w-auto" 
                                onchange="if(window.loadData) { window.loadData(1, parseInt(this.value)); }">
                                <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page', 10) == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page', 10) == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page', 10) == 100 ? 'selected' : '' }}>100</option>
                            </select> 
                            سجل
                        </label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <table class="table dataTable" id="DataTables_Table_0" role="grid" aria-describedby="DataTables_Table_0_info">
                        <thead>
                            <tr role="row">
                                <th class="sorting_asc" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-sort="ascending">رقم العملية</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">العميل</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">{{ __('branches.node_filter.column') }}</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">التاريخ</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">عدد المنتجات</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">القيمة الإجمالية</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" style="width: 10%">خيارات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($exchanges as $exchange)
                                @php
                                    $currency = $account_setting->currency ?? 'SAR';
                                    $currencySymbol = $currency == 'SAR' || empty($currency)
                                        ? '<img src="' . asset('assets/images/Saudi_Riyal.svg') . '" alt="ريال سعودي" width="15" style="vertical-align: middle;">'
                                        : $currency;
                                @endphp

                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-2" style="background-color: #17a2b8">
                                                <span class="avatar-content"><i class="fa fa-exchange-alt"></i></span>
                                            </div>
                                            <div>
                                                {{ $exchange->code }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="client-info">
                                            <strong>
                                                {{ $exchange->client ? ($exchange->client->trade_name ?: $exchange->client->first_name . ' ' . $exchange->client->last_name) : 'عميل غير معروف' }}
                                            </strong>
                                        </div>
                                    </td>

                                    {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                                    <td>@include('partials.branch-node-path', ['branchId' => $exchange->branch_id])</td>
                                    <td>
                                        {{ $exchange->exchange_date ? $exchange->exchange_date->format($account_setting->time_formula ?? 'Y-m-d') : '' }}
                                        <br>
                                        <small class="text-muted">أنشئت بواسطة: {{ $exchange->createdByUser->name ?? 'غير محدد' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            {{ $exchange->items->count() }} منتج
                                        </span>
                                    </td>
                                    <td>
                                        {{ number_format($exchange->total_value, 2) }} {!! $currencySymbol !!}
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <div class="dropdown">
                                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="{{ route('product-exchanges.show', $exchange->id) }}">
                                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                    </a>
                                                    @permission('sales.exchanges.print')
                                                        <a class="dropdown-item" href="{{ route('product-exchanges.print', $exchange->id) }}">
                                                            <i class="fa fa-print me-2 text-dark"></i>طباعة
                                                        </a>
                                                    @endpermission
                                                    @permission('sales.exchanges.delete')
                                                        <form action="{{ route('product-exchanges.destroy', $exchange->id) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="fa fa-trash me-2"></i>حذف
                                                            </button>
                                                        </form>
                                                    @endpermission
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- الترقيم -->
            <div class="row mt-3">
                <div class="col-sm-12 col-md-5">
                    <div class="dataTables_info" id="DataTables_Table_0_info" role="status" aria-live="polite">
                        عرض {{ $exchanges->firstItem() ?? 0 }} إلى {{ $exchanges->lastItem() ?? 0 }} من أصل {{ $exchanges->total() }} سجل
                    </div>
                </div>
                <div class="col-sm-12 col-md-7">
                    <div class="dataTables_paginate paging_simple_numbers" id="DataTables_Table_0_paginate">
                        {{ $exchanges->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info text-center">
        <i class="fa fa-info-circle me-2"></i>
        لا توجد عمليات تبديل حالياً
        <br>
        @permission('sales.exchanges.create')
            <a href="{{ route('product-exchanges.create') }}" class="btn btn-primary btn-sm mt-2">
                <i class="fa fa-plus"></i> إنشاء عملية تبديل جديدة
            </a>
        @endpermission
    </div>
@endif
