<!-- تبويب التفاصيل -->
<div class="tab-pane" id="details" role="tabpanel">
    <div class="row">
        <!-- صورة العميل -->
        <div class="col-md-12 mb-4">
            <div class="info-card">
                <div class="card-header">
                    <h5><i class="fa fa-image me-2"></i>صورة العميل</h5>
                </div>
                <div class="card-body text-center">
                    @if($client->attachments)
                        <a href="{{ asset('assets/uploads/' . $client->attachments) }}"
                           target="_blank"
                           data-fancybox="client-image"
                           data-caption="صورة العميل - {{ $client->trade_name }}">
                            <img src="{{ asset('assets/uploads/' . $client->attachments) }}"
                                 alt="صورة العميل"
                                 class="img-thumbnail shadow-sm"
                                 style="max-width: 300px; max-height: 300px; cursor: pointer; transition: transform 0.3s;"
                                 onmouseover="this.style.transform='scale(1.05)'"
                                 onmouseout="this.style.transform='scale(1)'">
                        </a>
                        <p class="text-muted mt-2 mb-0">
                            <i class="fa fa-info-circle"></i> انقر على الصورة لعرضها بالحجم الكامل
                        </p>
                    @else
                        <div class="text-center py-4">
                            <i class="fa fa-user-circle fa-5x text-muted"></i>
                            <p class="text-muted mt-2 mb-0">لا توجد صورة للعميل</p>
                            {{-- `clients_edit` غير معرَّفة في القاعدة — الصلاحية هي `clients_edit_data` --}}
                            @can('clients_edit_data')
                                <a href="{{ route('clients.edit', $client->id) }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fa fa-upload"></i> إضافة صورة
                                </a>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="info-card">
                <div class="card-header">
                    <h5><i class="fa fa-user me-2"></i>معلومات العميل الأساسية</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>الاسم التجاري:</strong></td>
                            <td>{{ $client->trade_name ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td><strong>الاسم الأول:</strong></td>
                            <td>{{ $client->first_name ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td><strong>الاسم الأخير:</strong></td>
                            <td>{{ $client->last_name ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td><strong>رقم الهاتف:</strong></td>
                            <td>{{ $client->phone ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td><strong>الجوال:</strong></td>
                            <td>{{ $client->mobile ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td><strong>البريد الإلكتروني:</strong></td>
                            <td>{{ $client->email ?? 'غير محدد' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="info-card">
                <div class="card-header">
                    <h5><i class="fa fa-map-marker-alt me-2"></i>العنوان والمعلومات الإضافية</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>العنوان:</strong></td>
                            <td>{{ $client->street1 }} {{ $client->street2 }}</td>
                        </tr>
                        <tr>
                            <td><strong>المدينة:</strong></td>
                            <td>{{ $client->city ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td><strong>المنطقة:</strong></td>
                            <td>{{ $client->region ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td><strong>الرمز البريدي:</strong></td>
                            <td>{{ $client->postal_code ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td><strong>الدولة:</strong></td>
                            <td>{{ $client->country ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td><strong>الرقم الضريبي:</strong></td>
                            <td>{{ $client->tax_number ?? 'غير محدد' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
