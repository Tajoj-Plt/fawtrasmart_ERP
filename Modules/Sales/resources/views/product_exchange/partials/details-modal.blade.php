{{-- Modal Content for Exchange Details --}}
<div class="exchange-details-modal-content">
    {{-- Header Info --}}
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="p-3 bg-light rounded">
                <small class="text-muted d-block"><i class="fa fa-hashtag me-1"></i> رقم التبديل</small>
                <strong class="text-primary">{{ $exchange->code }}</strong>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-3 bg-light rounded">
                <small class="text-muted d-block"><i class="fa fa-calendar me-1"></i> تاريخ التبديل</small>
                <strong>{{ $exchange->exchange_date ? $exchange->exchange_date->format('Y-m-d') : '--' }}</strong>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="p-3 bg-light rounded h-100">
                <small class="text-muted d-block"><i class="fa fa-user me-1"></i> العميل</small>
                <strong>{{ $exchange->client ? ($exchange->client->trade_name ?: $exchange->client->first_name . ' ' . $exchange->client->last_name) : 'غير محدد' }}</strong>
                @if($exchange->client && $exchange->client->code)
                    <br><small class="text-muted">كود: {{ $exchange->client->code }}</small>
                @endif
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-light rounded h-100">
                <small class="text-muted d-block"><i class="fa fa-warehouse me-1"></i> المستودع</small>
                <strong>{{ $exchange->storehouse ? $exchange->storehouse->name : 'غير محدد' }}</strong>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-light rounded h-100">
                <small class="text-muted d-block"><i class="fa fa-user-tie me-1"></i> بواسطة</small>
                <strong>{{ $exchange->createdByUser ? $exchange->createdByUser->name : ($exchange->employee ? $exchange->employee->name : 'غير محدد') }}</strong>
            </div>
        </div>
    </div>

    {{-- Products Table --}}
    <h6 class="border-bottom pb-2 mb-3">
        <i class="fa fa-box text-primary me-2"></i>
        المنتجات المُبدلة ({{ $exchange->items->count() }})
    </h6>

    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width: 40%;">المنتج</th>
                    <th class="text-center" style="width: 15%;">الكمية</th>
                    <th class="text-center" style="width: 15%;">السعر</th>
                    <th class="text-center" style="width: 15%;">الإجمالي</th>
                    <th class="text-center" style="width: 15%;">السبب</th>
                </tr>
            </thead>
            <tbody>
                @forelse($exchange->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product ? $item->product->name : 'منتج غير معروف' }}</strong>
                            @if($item->product && $item->product->code)
                                <br><small class="text-muted">{{ $item->product->code }}</small>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-center">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-center text-success fw-bold">{{ number_format($item->total, 2) }}</td>
                        <td class="text-center">
                            <span class="badge bg-secondary">{{ $item->reason_text }}</span>
                            @if($item->reason_notes)
                                <br><small class="text-muted">{{ $item->reason_notes }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">لا توجد منتجات</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="table-primary">
                <tr>
                    <td colspan="3" class="text-end fw-bold">الإجمالي الكلي:</td>
                    <td colspan="2" class="text-center fw-bold">
                        {{ number_format($exchange->total_value, 2) }}
                        @if(isset($account_setting) && $account_setting->currency)
                            {{ $account_setting->currency }}
                        @else
                            <img src="{{ asset('assets/images/Saudi_Riyal.svg') }}" alt="ريال سعودي" width="15" style="vertical-align: middle;">
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Notes --}}
    @if($exchange->notes)
        <div class="alert alert-info mt-3">
            <i class="fa fa-sticky-note me-2"></i>
            <strong>ملاحظات:</strong> {{ $exchange->notes }}
        </div>
    @endif

    {{-- Image --}}
    @if($exchange->image)
        <div class="mt-3 text-center">
            <h6 class="mb-2"><i class="fa fa-image me-2"></i> صورة المنتج</h6>
            <a href="{{ asset($exchange->image) }}" target="_blank" data-fancybox="exchange-modal-images">
                <img src="{{ asset($exchange->image) }}" 
                     alt="صورة المنتج" 
                     class="img-thumbnail"
                     style="max-width: 250px; max-height: 250px;">
            </a>
        </div>
    @endif

    {{-- Actions --}}
    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
        @permission('sales.exchanges.view')
            <a href="{{ route('product-exchanges.show', $exchange->id) }}" class="btn btn-primary btn-sm" target="_blank">
                <i class="fa fa-external-link-alt me-1"></i> فتح صفحة كاملة
            </a>
        @endpermission
        @permission('sales.exchanges.print')
            <a href="{{ route('product-exchanges.print', $exchange->id) }}" class="btn btn-outline-dark btn-sm" target="_blank">
                <i class="fa fa-print me-1"></i> طباعة
            </a>
        @endpermission
    </div>
</div>
