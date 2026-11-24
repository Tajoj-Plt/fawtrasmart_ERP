@extends('onlinestore::master')

@section('title', 'تفاصيل العميل - ' . $storeClient->first_name)

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.clients.edit'))
                <a href="{{ route('store_clients.edit', $storeClient) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('store_clients.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل العميل',
            'icon' => 'user',
            'sub' => trim($storeClient->first_name . ' ' . $storeClient->last_name) . ' — #' . ($storeClient->code ?? '---'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'عملاء المتجر' => route('store_clients.index'),
                $storeClient->first_name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
                <span class="stk-metric__label">إجمالي الطلبات</span>
                <span class="stk-metric__value">{{ $stats['total_orders'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">طلبات مكتملة</span>
                <span class="stk-metric__value">{{ $stats['completed_orders'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">طلبات معلقة</span>
                <span class="stk-metric__value">{{ $stats['pending_orders'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">قيمة الطلبات</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($stats['total_orders_value']) }}</span>
            </div>
        </div>

        <div class="row">
            <!-- العمود الأيسر - معلومات العميل -->
            <div class="col-lg-4 col-md-5">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-user"></i> بيانات العميل</h6>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>البريد الإلكتروني</dt>
                                <dd>{{ $storeClient->email ?? '-' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>رقم الجوال</dt>
                                <dd class="stk-num">{{ $storeClient->mobile ?? '-' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>رقم الهاتف</dt>
                                <dd class="stk-num">{{ $storeClient->phone ?? '-' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ التسجيل</dt>
                                <dd class="stk-num">{{ $storeClient->created_at ? $storeClient->created_at->format('Y-m-d') : '-' }}</dd>
                            </div>
                            @if ($storeClient->notes)
                                <div class="stk-def__row">
                                    <dt>ملاحظات</dt>
                                    <dd>{{ $storeClient->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <div class="stk-metrics">
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-heart"></i></span>
                        <span class="stk-metric__label">قائمة الأمنيات</span>
                        <span class="stk-metric__value">{{ $stats['wishlist_count'] }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-star"></i></span>
                        <span class="stk-metric__label">التقييمات</span>
                        <span class="stk-metric__value">{{ $stats['reviews_count'] }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-award"></i></span>
                        <span class="stk-metric__label">متوسط التقييم</span>
                        <span class="stk-metric__value">{{ number_format($stats['average_rating'], 1) }}</span>
                    </div>
                </div>
            </div>

            <!-- العمود الأيمن - الطلبات والفواتير والعناوين -->
            <div class="col-lg-8 col-md-7">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-shopping-bag"></i> آخر الطلبات</h6>
                        <span class="badge badge-light-primary">{{ $stats['total_orders'] }} طلب</span>
                    </div>

                    @if ($storeClient->onlineOrders && $storeClient->onlineOrders->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <th class="text-center">التاريخ</th>
                                        <th class="text-center">الحالة</th>
                                        <th class="text-center">المبلغ</th>
                                        <th class="text-center" style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($storeClient->onlineOrders as $order)
                                        @php
                                            $statusMap = [
                                                'pending' => ['class' => 'warning', 'text' => 'معلق'],
                                                'processing' => ['class' => 'info', 'text' => 'قيد المعالجة'],
                                                'shipped' => ['class' => 'info', 'text' => 'تم الشحن'],
                                                'delivered' => ['class' => 'success', 'text' => 'تم التوصيل'],
                                                'completed' => ['class' => 'success', 'text' => 'مكتمل'],
                                                'cancelled' => ['class' => 'danger', 'text' => 'ملغي'],
                                                'refunded' => ['class' => 'secondary', 'text' => 'مسترد'],
                                            ];
                                            $status = $statusMap[$order->status] ?? ['class' => 'secondary', 'text' => $order->status];
                                        @endphp
                                        <tr>
                                            <td><strong class="stk-num">#{{ $order->order_number ?? $order->id }}</strong></td>
                                            <td class="text-center stk-num">{{ $order->created_at ? $order->created_at->format('Y-m-d') : '-' }}</td>
                                            <td class="text-center"><span class="badge badge-light-{{ $status['class'] }}">{{ $status['text'] }}</span></td>
                                            <td class="text-center">{{ $order->formatMoney($order->total_amount ?? 0) }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('online_orders.show', $order) }}" class="btn btn-sm btn-icon">
                                                    <i class="feather icon-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="stk-empty">
                            <span class="stk-empty__icon"><i class="feather icon-shopping-bag"></i></span>
                            <h6 class="stk-empty__title">لا توجد طلبات</h6>
                            <p class="stk-empty__hint">لم يقم هذا العميل بأي طلب بعد.</p>
                        </div>
                    @endif
                </div>

                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> الفواتير</h6>
                        <span class="badge badge-light-primary">{{ $stats['total_invoices'] }} فاتورة</span>
                    </div>

                    @if ($storeClient->invoices && $storeClient->invoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>رقم الفاتورة</th>
                                        <th class="text-center">التاريخ</th>
                                        <th class="text-center">الحالة</th>
                                        <th class="text-center">الإجمالي</th>
                                        <th class="text-center">المتبقي</th>
                                        <th class="text-center" style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($storeClient->invoices as $invoice)
                                        <tr>
                                            <td><strong class="stk-num">#{{ $invoice->code ?? $invoice->id }}</strong></td>
                                            <td class="text-center stk-num">{{ $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : '-' }}</td>
                                            <td class="text-center">
                                                @if ($invoice->is_paid)
                                                    <span class="badge badge-light-success">مدفوعة</span>
                                                @elseif ($invoice->advance_payment > 0)
                                                    <span class="badge badge-light-warning">جزئية</span>
                                                @else
                                                    <span class="badge badge-light-danger">غير مدفوعة</span>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ number_format($invoice->grand_total ?? 0, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</td>
                                            <td class="text-center {{ ($invoice->remaining_amount ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($invoice->remaining_amount ?? 0, 2) }} {{ \App\Support\CurrencySettings::symbol() }}
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-icon" target="_blank">
                                                    <i class="feather icon-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="stk-empty">
                            <span class="stk-empty__icon"><i class="feather icon-file-text"></i></span>
                            <h6 class="stk-empty__title">لا توجد فواتير</h6>
                            <p class="stk-empty__hint">لا توجد فواتير مسجلة لهذا العميل.</p>
                        </div>
                    @endif
                </div>

                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-map-pin"></i> عناوين التوصيل</h6>
                        @if (hasPermission('online_store.clients.edit'))
                            <button type="button" class="btn btn-sm btn-primary" data-os-modal="#addAddressModal">
                                <i class="feather icon-plus"></i> إضافة
                            </button>
                        @endif
                    </div>

                    <div class="card-body">
                        @if ($storeClient->addresses && $storeClient->addresses->count() > 0)
                            <div class="row">
                                @foreach ($storeClient->addresses as $address)
                                    <div class="col-md-6 mb-3">
                                        <div class="stk-media" style="align-items:flex-start;">
                                            <span class="stk-media__thumb"><i class="feather icon-map-pin"></i></span>
                                            <span class="stk-media__body">
                                                <span class="stk-media__title">
                                                    {{ $address->title ?? 'عنوان التوصيل' }}
                                                    @if ($address->is_default)
                                                        <span class="badge badge-light-primary">افتراضي</span>
                                                    @endif
                                                </span>
                                                <span class="stk-media__sub">
                                                    @if ($address->receiver_name)
                                                        <strong>{{ $address->receiver_name }}</strong><br>
                                                    @endif
                                                    @if ($address->receiver_phone)
                                                        <span dir="ltr">{{ $address->receiver_phone }}</span><br>
                                                    @endif
                                                    {{ $address->street }}
                                                    @if ($address->building_number), مبنى {{ $address->building_number }}@endif
                                                    @if ($address->district)<br>حي {{ $address->district }}@endif
                                                    <br>{{ $address->city }}{{ $address->region ? ', ' . $address->region : '' }}
                                                </span>

                                                @if (hasPermission('online_store.clients.edit'))
                                                    <span class="d-flex gap-1 mt-2">
                                                        @if (! $address->is_default)
                                                            <form action="{{ route('store_clients.addresses.set-default', [$storeClient, $address]) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-icon" title="تعيين كافتراضي">
                                                                    <i class="feather icon-check"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                        <button type="button" class="btn btn-sm btn-icon edit-address-btn" data-address='@json($address)' title="تعديل">
                                                            <i class="feather icon-edit-2"></i>
                                                        </button>
                                                        <form action="{{ route('store_clients.addresses.destroy', [$storeClient, $address]) }}" method="POST" class="d-inline delete-address-form">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-icon text-danger" title="حذف">
                                                                <i class="feather icon-trash-2"></i>
                                                            </button>
                                                        </form>
                                                        @if ($address->latitude && $address->longitude)
                                                            <a href="https://www.google.com/maps?q={{ $address->latitude }},{{ $address->longitude }}" target="_blank" class="btn btn-sm btn-icon" title="الخريطة">
                                                                <i class="feather icon-map"></i>
                                                            </a>
                                                        @endif
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-map-pin"></i></span>
                                <h6 class="stk-empty__title">لا توجد عناوين توصيل</h6>
                                @if (hasPermission('online_store.clients.edit'))
                                    <button type="button" class="btn btn-primary btn-sm" data-os-modal="#addAddressModal">
                                        <i class="feather icon-plus"></i> إضافة عنوان
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (hasPermission('online_store.clients.edit'))
        <!-- Modal إضافة عنوان -->
        <div class="modal fade" id="addAddressModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="{{ route('store_clients.addresses.store', $storeClient) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">إضافة عنوان جديد</h5>
                            <button type="button" class="btn-close" data-os-dismiss aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label stk-req">تسمية العنوان</label>
                                    <input type="text" name="title" class="form-control" required placeholder="مثال: المنزل، العمل">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">اسم المستلم</label>
                                    <input type="text" name="receiver_name" class="form-control" value="{{ $storeClient->first_name }} {{ $storeClient->last_name }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">هاتف المستلم</label>
                                    <input type="text" name="receiver_phone" class="form-control" dir="ltr" value="{{ $storeClient->mobile ?? $storeClient->phone }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">الدولة</label>
                                    <input type="text" name="country" class="form-control" value="المملكة العربية السعودية">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label stk-req">المدينة</label>
                                    <input type="text" name="city" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">المنطقة</label>
                                    <input type="text" name="region" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">الحي</label>
                                    <input type="text" name="district" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label stk-req">الشارع</label>
                                    <input type="text" name="street" class="form-control" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">رقم المبنى</label>
                                    <input type="text" name="building_number" class="form-control">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">الطابق</label>
                                    <input type="text" name="floor" class="form-control">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">الشقة</label>
                                    <input type="text" name="apartment" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">الرمز البريدي</label>
                                    <input type="text" name="postal_code" class="form-control" dir="ltr">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">خط العرض</label>
                                    <input type="text" name="latitude" class="form-control" dir="ltr">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">خط الطول</label>
                                    <input type="text" name="longitude" class="form-control" dir="ltr">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">ملاحظات</label>
                                    <textarea name="notes" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="is_default" id="is_default_new" value="1">
                                        <label class="form-check-label" for="is_default_new">تعيين كعنوان افتراضي</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-os-dismiss>إلغاء</button>
                            <button type="submit" class="btn btn-primary">حفظ العنوان</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal تعديل عنوان -->
        <div class="modal fade" id="editAddressModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="editAddressForm" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">تعديل العنوان</h5>
                            <button type="button" class="btn-close" data-os-dismiss aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label stk-req">تسمية العنوان</label>
                                    <input type="text" name="title" id="edit_title" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">اسم المستلم</label>
                                    <input type="text" name="receiver_name" id="edit_receiver_name" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">هاتف المستلم</label>
                                    <input type="text" name="receiver_phone" id="edit_receiver_phone" class="form-control" dir="ltr">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">الدولة</label>
                                    <input type="text" name="country" id="edit_country" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label stk-req">المدينة</label>
                                    <input type="text" name="city" id="edit_city" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">المنطقة</label>
                                    <input type="text" name="region" id="edit_region" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">الحي</label>
                                    <input type="text" name="district" id="edit_district" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label stk-req">الشارع</label>
                                    <input type="text" name="street" id="edit_street" class="form-control" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">رقم المبنى</label>
                                    <input type="text" name="building_number" id="edit_building_number" class="form-control">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">الطابق</label>
                                    <input type="text" name="floor" id="edit_floor" class="form-control">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">الشقة</label>
                                    <input type="text" name="apartment" id="edit_apartment" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">الرمز البريدي</label>
                                    <input type="text" name="postal_code" id="edit_postal_code" class="form-control" dir="ltr">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">خط العرض</label>
                                    <input type="text" name="latitude" id="edit_latitude" class="form-control" dir="ltr">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">خط الطول</label>
                                    <input type="text" name="longitude" id="edit_longitude" class="form-control" dir="ltr">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">ملاحظات</label>
                                    <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-os-dismiss>إلغاء</button>
                            <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
@include('onlinestore::layouts._modal-helper')
<script>
$(document).ready(function() {
    // حذف العنوان
    $('.delete-address-form').submit(function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'حذف العنوان',
            text: 'هل أنت متأكد من حذف هذا العنوان؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });

    // تعديل العنوان
    $('.edit-address-btn').click(function() {
        var address = $(this).data('address');
        $('#editAddressForm').attr('action', '{{ route('store_clients.addresses.update', ['storeClient' => $storeClient->id, 'address' => '__ID__']) }}'.replace('__ID__', address.id));
        $('#edit_title').val(address.title);
        $('#edit_receiver_name').val(address.receiver_name);
        $('#edit_receiver_phone').val(address.receiver_phone);
        $('#edit_country').val(address.country);
        $('#edit_city').val(address.city);
        $('#edit_region').val(address.region);
        $('#edit_district').val(address.district);
        $('#edit_street').val(address.street);
        $('#edit_building_number').val(address.building_number);
        $('#edit_floor').val(address.floor);
        $('#edit_apartment').val(address.apartment);
        $('#edit_postal_code').val(address.postal_code);
        $('#edit_latitude').val(address.latitude);
        $('#edit_longitude').val(address.longitude);
        $('#edit_notes').val(address.notes);
        osModal.show('#editAddressModal');
    });
});
</script>
@endsection
