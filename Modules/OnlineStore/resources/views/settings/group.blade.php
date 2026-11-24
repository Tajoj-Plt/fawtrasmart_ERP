@extends('onlinestore::master')

@section('title', $currentGroup['title'])

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_settings.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة للإعدادات
            </a>
            @if (hasPermission('online_store.settings.reset'))
                <button type="button" class="btn btn-outline-danger" id="resetGroupSettings">
                    <i class="feather icon-refresh-cw"></i> استعادة الافتراضية
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => $currentGroup['title'],
            'icon' => $currentGroup['icon'],
            'sub' => $currentGroup['description'],
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الإعدادات' => route('store_settings.index'),
                $currentGroup['title'] => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-md-3">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-list"></i> أقسام الإعدادات</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @foreach($groups as $key => $grp)
                                <a href="{{ route('store_settings.group', $key) }}"
                                   class="list-group-item list-group-item-action {{ $group === $key ? 'active' : '' }}">
                                    <i class="feather icon-{{ $grp['icon'] }} ml-2"></i>
                                    {{ $grp['title'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="col-md-9">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-{{ $currentGroup['icon'] }}"></i>
                            {{ $currentGroup['title'] }}
                        </h6>
                    </div>
                    <p class="stk-help px-3 pt-2 mb-0">{{ $currentGroup['description'] }}</p>
                    <div class="card-body">
                        <form action="{{ route('store_settings.update') }}" method="POST" enctype="multipart/form-data" id="settingsForm">
                            @csrf
                            <input type="hidden" name="group" value="{{ $group }}">

                            @switch($group)
                                @case('general')
                                    @include('onlinestore::settings.partials.general', ['settings' => $settings])
                                    @break

                                @case('appearance')
                                    @include('onlinestore::settings.partials.appearance', ['settings' => $settings])
                                    @break

                                @case('filters')
                                    @include('onlinestore::settings.partials.filters', ['settings' => $settings])
                                    @break

                                @case('checkout')
                                    @include('onlinestore::settings.partials.checkout', ['settings' => $settings])
                                    @break

                                @case('accounting')
                                    @include('onlinestore::settings.partials.accounting', ['settings' => $settings])
                                    @break

                                @case('shipping')
                                    @include('onlinestore::settings.partials.shipping', ['settings' => $settings])
                                    @break

                                @case('seo')
                                    @include('onlinestore::settings.partials.seo', ['settings' => $settings])
                                    @break

                                @case('notifications')
                                    @include('onlinestore::settings.partials.notifications', ['settings' => $settings])
                                    @break

                                @case('advanced')
                                    @include('onlinestore::settings.partials.advanced', ['settings' => $settings])
                                    @break

                                @case('social')
                                    @include('onlinestore::settings.partials.social', ['settings' => $settings])
                                    @break

                                @default
                                    @foreach($settings as $setting)
                                        <div class="form-group">
                                            <label for="{{ $setting->key }}">{{ $setting->key }}</label>
                                            <input type="text"
                                                   name="{{ $setting->key }}"
                                                   id="{{ $setting->key }}"
                                                   class="form-control"
                                                   value="{{ $setting->value }}">
                                            @if($setting->description)
                                                <small class="text-muted">{{ $setting->description }}</small>
                                            @endif
                                        </div>
                                    @endforeach
                            @endswitch

                            <hr>

                            <div class="d-flex justify-content-between">
                                @if (hasPermission('online_store.settings.manage'))
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-save"></i> حفظ الإعدادات
                                    </button>
                                @else
                                    <span></span>
                                @endif
                                <a href="{{ route('store_settings.index') }}" class="btn btn-secondary">
                                    <i class="feather icon-arrow-right"></i> العودة
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // حفظ الإعدادات بـ AJAX
    $('#settingsForm').submit(function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $(this).find('button[type="submit"]');

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جاري الحفظ...');

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    // إعادة تحميل الصفحة لعرض الصور المرفوعة حديثاً وأي قيم أخرى
                    // تم تطبيعها في الخادم — استجابة AJAX لا تعيد مسار الملف الجديد،
                    // فكارد "الصور المحفوظة" يبقى يعرض الصورة القديمة بدون هذا التحديث.
                    setTimeout(function() {
                        location.reload();
                    }, 800);
                } else {
                    toastr.error(response.message || 'حدث خطأ');
                }
            },
            error: function(xhr) {
                var message = xhr.responseJSON?.message || 'حدث خطأ أثناء الحفظ';
                toastr.error(message);
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="feather icon-save"></i> حفظ الإعدادات');
            }
        });
    });

    // استعادة إعدادات المجموعة
    $('#resetGroupSettings').click(function() {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم استعادة إعدادات هذا القسم إلى القيم الافتراضية!',
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
                    data: {
                        _token: '{{ csrf_token() }}',
                        group: '{{ $group }}'
                    },
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

    // معاينة الصورة عند الاختيار
    $('input[type="file"]').change(function() {
        var input = this;
        var formGroup = $(this).closest('.form-group');
        var previewContainer = formGroup.find('.image-preview');

        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                if (previewContainer.length === 0) {
                    // إنشاء معاينة جديدة إذا لم تكن موجودة
                    previewContainer = $('<div class="image-preview mt-2"><img src="" alt="Preview" style="max-height: 60px;"></div>');
                    formGroup.append(previewContainer);
                }
                previewContainer.find('img').attr('src', e.target.result);
                previewContainer.show();
            }
            reader.readAsDataURL(input.files[0]);
        }
    });

    // حذف الصورة
    $(document).on('click', '.delete-image', function() {
        var key = $(this).data('key');
        var btn = $(this);

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذه الصورة نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("store_settings.delete-file") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        key: key
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.closest('.image-preview').fadeOut(function() {
                                $(this).remove();
                            });
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        toastr.error('حدث خطأ أثناء الحذف');
                    }
                });
            }
        });
    });
});
</script>
@endsection
