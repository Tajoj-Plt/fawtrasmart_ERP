@extends('onlinestore::master')

@section('title', 'إعدادات المتجر الإلكتروني')

@section('content')
    <div class="content-body">

        @php
            $canManageSettings = hasPermission('online_store.settings.manage');
        @endphp

        @section('page-actions')
            @if (canAny(['online_store.settings.import_export', 'online_store.settings.reset']))
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" data-toggle="dropdown">
                        <i class="feather icon-more-vertical"></i> خيارات
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        @if (hasPermission('online_store.settings.import_export'))
                            <a class="dropdown-item" href="{{ route('store_settings.export') }}">
                                <i class="feather icon-download"></i> تصدير الإعدادات
                            </a>
                            <button type="button" class="dropdown-item" data-os-modal="#importModal">
                                <i class="feather icon-upload"></i> استيراد الإعدادات
                            </button>
                        @endif
                        @if (hasPermission('online_store.settings.reset'))
                            @if (hasPermission('online_store.settings.import_export'))
                                <div class="dropdown-divider"></div>
                            @endif
                            <button type="button" class="dropdown-item text-danger" id="resetAllSettings">
                                <i class="feather icon-refresh-cw"></i> استعادة الافتراضية
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إعدادات المتجر',
            'icon' => 'settings',
            'sub' => 'إعدادات المتجر الإلكتروني مقسّمة حسب الأقسام',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الإعدادات' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- Settings Groups Cards -->
        <div class="row match-height">
            @foreach($groups as $key => $group)
                @php
                    $count = isset($settingsByGroup[$key]) ? $settingsByGroup[$key]->count() : 0;
                @endphp
                <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                    <div class="card mb-2 h-100 position-relative">
                        <div class="card-body">
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-{{ $group['icon'] }}"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title">{{ $group['title'] }}</span>
                                    <span class="stk-media__sub">{{ Str::limit($group['description'], 40) }}</span>
                                </span>
                                <i class="feather icon-chevron-left text-muted"></i>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <span class="stk-count">
                                    <i class="feather icon-sliders"></i> {{ $count }} إعداد
                                </span>
                            </div>
                            <a href="{{ route('store_settings.group', $key) }}" class="stretched-link"></a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Quick Settings -->
        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-zap"></i> إعدادات سريعة</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- وضع الصيانة -->
                    <div class="col-md-4 col-sm-6 mb-3">
                        @php
                            $maintenanceMode = $settings->where('key', 'maintenance_mode')->first();
                            $isMaintenanceOn = $maintenanceMode && $maintenanceMode->value === 'true';
                        @endphp
                        <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                            @if ($canManageSettings)
                                <label class="stk-switch" for="maintenance_mode">
                                    <input type="checkbox" class="quick-setting" id="maintenance_mode"
                                           data-key="maintenance_mode" {{ $isMaintenanceOn ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">وضع الصيانة</span>
                                        <span class="stk-switch__hint">إيقاف المتجر مؤقتاً</span>
                                    </span>
                                </label>
                            @else
                                <div>
                                    <h6 class="mb-0">وضع الصيانة</h6>
                                    <small class="text-muted">إيقاف المتجر مؤقتاً</small>
                                </div>
                                <span class="stk-state {{ $isMaintenanceOn ? 'stk-state--on' : 'stk-state--off' }}">
                                    {{ $isMaintenanceOn ? 'مفعل' : 'متوقف' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- السماح للزوار بالشراء -->
                    <div class="col-md-4 col-sm-6 mb-3">
                        @php
                            $guestCheckout = $settings->where('key', 'enable_guest_checkout')->first();
                            $isGuestCheckoutOn = $guestCheckout && $guestCheckout->value === 'true';
                        @endphp
                        <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                            @if ($canManageSettings)
                                <label class="stk-switch" for="enable_guest_checkout">
                                    <input type="checkbox" class="quick-setting" id="enable_guest_checkout"
                                           data-key="enable_guest_checkout" {{ $isGuestCheckoutOn ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">شراء الزوار</span>
                                        <span class="stk-switch__hint">السماح للزوار بالشراء</span>
                                    </span>
                                </label>
                            @else
                                <div>
                                    <h6 class="mb-0">شراء الزوار</h6>
                                    <small class="text-muted">السماح للزوار بالشراء</small>
                                </div>
                                <span class="stk-state {{ $isGuestCheckoutOn ? 'stk-state--on' : 'stk-state--off' }}">
                                    {{ $isGuestCheckoutOn ? 'مفعل' : 'متوقف' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- تفعيل التقييمات -->
                    <div class="col-md-4 col-sm-6 mb-3">
                        @php
                            $enableReviews = $settings->where('key', 'enable_reviews')->first();
                            $isReviewsOn = $enableReviews && $enableReviews->value === 'true';
                        @endphp
                        <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                            @if ($canManageSettings)
                                <label class="stk-switch" for="enable_reviews">
                                    <input type="checkbox" class="quick-setting" id="enable_reviews"
                                           data-key="enable_reviews" {{ $isReviewsOn ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">التقييمات</span>
                                        <span class="stk-switch__hint">تفعيل تقييمات المنتجات</span>
                                    </span>
                                </label>
                            @else
                                <div>
                                    <h6 class="mb-0">التقييمات</h6>
                                    <small class="text-muted">تفعيل تقييمات المنتجات</small>
                                </div>
                                <span class="stk-state {{ $isReviewsOn ? 'stk-state--on' : 'stk-state--off' }}">
                                    {{ $isReviewsOn ? 'مفعل' : 'متوقف' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- الدفع عند الاستلام -->
                    <div class="col-md-4 col-sm-6 mb-3">
                        @php
                            $enableCod = $settings->where('key', 'enable_cod')->first();
                            $isCodOn = $enableCod && $enableCod->value === 'true';
                        @endphp
                        <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                            @if ($canManageSettings)
                                <label class="stk-switch" for="enable_cod">
                                    <input type="checkbox" class="quick-setting" id="enable_cod"
                                           data-key="enable_cod" {{ $isCodOn ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">الدفع عند الاستلام</span>
                                        <span class="stk-switch__hint">تفعيل COD</span>
                                    </span>
                                </label>
                            @else
                                <div>
                                    <h6 class="mb-0">الدفع عند الاستلام</h6>
                                    <small class="text-muted">تفعيل COD</small>
                                </div>
                                <span class="stk-state {{ $isCodOn ? 'stk-state--on' : 'stk-state--off' }}">
                                    {{ $isCodOn ? 'مفعل' : 'متوقف' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- إشعارات البريد -->
                    <div class="col-md-4 col-sm-6 mb-3">
                        @php
                            $enableEmail = $settings->where('key', 'enable_email_notifications')->first();
                            $isEmailOn = $enableEmail && $enableEmail->value === 'true';
                        @endphp
                        <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                            @if ($canManageSettings)
                                <label class="stk-switch" for="enable_email_notifications">
                                    <input type="checkbox" class="quick-setting" id="enable_email_notifications"
                                           data-key="enable_email_notifications" {{ $isEmailOn ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">إشعارات البريد</span>
                                        <span class="stk-switch__hint">تفعيل إشعارات البريد</span>
                                    </span>
                                </label>
                            @else
                                <div>
                                    <h6 class="mb-0">إشعارات البريد</h6>
                                    <small class="text-muted">تفعيل إشعارات البريد</small>
                                </div>
                                <span class="stk-state {{ $isEmailOn ? 'stk-state--on' : 'stk-state--off' }}">
                                    {{ $isEmailOn ? 'مفعل' : 'متوقف' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- الموافقة التلقائية على التقييمات -->
                    <div class="col-md-4 col-sm-6 mb-3">
                        @php
                            $autoApprove = $settings->where('key', 'auto_approve_reviews')->first();
                            $isAutoApproveOn = $autoApprove && $autoApprove->value === 'true';
                        @endphp
                        <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                            @if ($canManageSettings)
                                <label class="stk-switch" for="auto_approve_reviews">
                                    <input type="checkbox" class="quick-setting" id="auto_approve_reviews"
                                           data-key="auto_approve_reviews" {{ $isAutoApproveOn ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">موافقة تلقائية</span>
                                        <span class="stk-switch__hint">موافقة تلقائية على التقييمات</span>
                                    </span>
                                </label>
                            @else
                                <div>
                                    <h6 class="mb-0">موافقة تلقائية</h6>
                                    <small class="text-muted">موافقة تلقائية على التقييمات</small>
                                </div>
                                <span class="stk-state {{ $isAutoApproveOn ? 'stk-state--on' : 'stk-state--off' }}">
                                    {{ $isAutoApproveOn ? 'مفعل' : 'متوقف' }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters & Display Summary -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-filter"></i> إعدادات الفلاتر والعرض</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @php
                                $maxCategories = $settings->where('key', 'filter_max_categories')->first();
                                $maxBrands = $settings->where('key', 'filter_max_brands')->first();
                                $maxFeatured = $settings->where('key', 'max_featured_products')->first();
                                $maxNewArrivals = $settings->where('key', 'max_new_arrivals')->first();
                                $maxTopRated = $settings->where('key', 'max_top_rated_products')->first();
                                $maxBestSelling = $settings->where('key', 'max_best_selling_products')->first();
                                $maxRecentlyViewed = $settings->where('key', 'max_recently_viewed')->first();
                            @endphp
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6 text-center mb-3">
                                <div class="p-2 border rounded">
                                    <h3 class="text-primary mb-0">{{ $maxCategories->value ?? 10 }}</h3>
                                    <small class="text-muted">أصناف</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6 text-center mb-3">
                                <div class="p-2 border rounded">
                                    <h3 class="text-warning mb-0">{{ $maxBrands->value ?? 10 }}</h3>
                                    <small class="text-muted">براندات</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6 text-center mb-3">
                                <div class="p-2 border rounded">
                                    <h3 class="text-success mb-0">{{ $maxFeatured->value ?? 8 }}</h3>
                                    <small class="text-muted">مميزة</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6 text-center mb-3">
                                <div class="p-2 border rounded">
                                    <h3 class="text-info mb-0">{{ $maxNewArrivals->value ?? 8 }}</h3>
                                    <small class="text-muted">جديدة</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6 text-center mb-3">
                                <div class="p-2 border rounded">
                                    <h3 class="text-danger mb-0">{{ $maxTopRated->value ?? 5 }}</h3>
                                    <small class="text-muted">أعلى تقييم</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6 text-center mb-3">
                                <div class="p-2 border rounded">
                                    <h3 class="text-secondary mb-0">{{ $maxBestSelling->value ?? 8 }}</h3>
                                    <small class="text-muted">أكثر مبيعاً</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6 text-center mb-3">
                                <div class="p-2 border rounded">
                                    <h3 class="text-info mb-0">{{ $maxRecentlyViewed->value ?? 10 }}</h3>
                                    <small class="text-muted">مشاهدة مؤخراً</small>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('store_settings.group', 'filters') }}" class="btn btn-outline-primary btn-sm">
                            <i class="feather icon-edit"></i> تعديل إعدادات الفلاتر
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Store Info Summary -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات المتجر</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            @php
                                $storeName = $settings->where('key', 'store_name')->first();
                                $storeEmail = $settings->where('key', 'store_email')->first();
                                $storePhone = $settings->where('key', 'store_phone')->first();
                            @endphp
                            <tr>
                                <td><strong>اسم المتجر:</strong></td>
                                <td>{{ $storeName->value ?? 'غير محدد' }}</td>
                            </tr>
                            <tr>
                                <td><strong>البريد الإلكتروني:</strong></td>
                                <td>{{ $storeEmail->value ?? 'غير محدد' }}</td>
                            </tr>
                            <tr>
                                <td><strong>رقم الهاتف:</strong></td>
                                <td>{{ $storePhone->value ?? 'غير محدد' }}</td>
                            </tr>
                            <tr>
                                <td><strong>العملة:</strong></td>
                                <td>{{ \App\Support\CurrencySettings::name() }} ({{ \App\Support\CurrencySettings::code() }})</td>
                            </tr>
                        </table>
                        <a href="{{ route('store_settings.group', 'general') }}" class="btn btn-outline-primary btn-sm">
                            <i class="feather icon-edit"></i> تعديل
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-dollar-sign"></i> إعدادات مالية</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            @php
                                $taxRate = $settings->where('key', 'tax_rate')->first();
                                $minOrder = $settings->where('key', 'min_order_amount')->first();
                                $freeShipping = $settings->where('key', 'free_shipping_threshold')->first();
                                $defaultShipping = $settings->where('key', 'default_shipping_cost')->first();
                            @endphp
                            <tr>
                                <td><strong>نسبة الضريبة:</strong></td>
                                <td>{{ $taxRate->value ?? '15' }}%</td>
                            </tr>
                            <tr>
                                <td><strong>الحد الأدنى للطلب:</strong></td>
                                <td>{{ $minOrder->value ?? '50' }} {{ \App\Support\CurrencySettings::symbol() }}</td>
                            </tr>
                            <tr>
                                <td><strong>شحن مجاني فوق:</strong></td>
                                <td>{{ $freeShipping->value ?? '200' }} {{ \App\Support\CurrencySettings::symbol() }}</td>
                            </tr>
                            <tr>
                                <td><strong>تكلفة الشحن الافتراضية:</strong></td>
                                <td>{{ $defaultShipping->value ?? '25' }} {{ \App\Support\CurrencySettings::symbol() }}</td>
                            </tr>
                        </table>
                        <a href="{{ route('store_settings.group', 'checkout') }}" class="btn btn-outline-primary btn-sm">
                            <i class="feather icon-edit"></i> تعديل
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('onlinestore::layouts._modal-helper')

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">استيراد الإعدادات</h5>
                    <button type="button" class="close" data-os-dismiss>
                        <span>&times;</span>
                    </button>
                </div>
                <form action="{{ route('store_settings.import') }}" method="POST" enctype="multipart/form-data" id="importForm">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>اختر ملف JSON</label>
                            <input type="file" name="file" class="form-control" accept=".json" required>
                            <small class="text-muted">يجب أن يكون الملف بصيغة JSON</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-os-dismiss>إلغاء</button>
                        <button type="submit" class="btn btn-primary">استيراد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // تحديث الإعدادات السريعة
    $('.quick-setting').change(function() {
        var key = $(this).data('key');
        var value = $(this).is(':checked');

        $.ajax({
            url: '{{ route("store_settings.update-single") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                key: key,
                value: value
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء التحديث');
            }
        });
    });

    // استعادة الإعدادات الافتراضية
    $('#resetAllSettings').click(function() {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم استعادة جميع الإعدادات إلى القيم الافتراضية!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، استعادة',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("store_settings.reset") }}',
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            location.reload();
                        }
                    },
                    error: function() {
                        toastr.error('حدث خطأ');
                    }
                });
            }
        });
    });

    // استيراد الإعدادات
    $('#importForm').submit(function(e) {
        e.preventDefault();
        var formData = new FormData(this);

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    osModal.hide('#importModal');
                    location.reload();
                }
            },
            error: function(xhr) {
                var message = xhr.responseJSON?.message || 'حدث خطأ';
                toastr.error(message);
            }
        });
    });
});
</script>
@endsection
