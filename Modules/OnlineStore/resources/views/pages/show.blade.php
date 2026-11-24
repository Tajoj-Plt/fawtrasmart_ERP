@extends('onlinestore::master')

@section('title', 'عرض الصفحة: ' . $storePage->title)

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.pages.edit'))
                <a href="{{ route('store_pages.edit', $storePage) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('store_pages.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'عرض الصفحة',
            'icon' => 'file-text',
            'sub' => $storePage->title,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'صفحات المتجر' => route('store_pages.index'),
                $storePage->title => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="row">
            <!-- المحتوى الرئيسي -->
            <div class="col-md-8">
                <!-- العنوان والحالة -->
                <div class="card">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                        <h5 class="mb-0">{{ $storePage->title }}</h5>
                        <div>
                            @php
                                $typeClass = match($storePage->type->value ?? $storePage->type) {
                                    'page' => 'primary',
                                    'policy' => 'info',
                                    'faq' => 'warning',
                                    default => 'secondary'
                                };
                                $typeText = match($storePage->type->value ?? $storePage->type) {
                                    'page' => 'صفحة عادية',
                                    'policy' => 'سياسة',
                                    'faq' => 'أسئلة شائعة',
                                    default => 'غير محدد'
                                };
                            @endphp
                            <span class="badge badge-light-{{ $typeClass }}">{{ $typeText }}</span>
                            <span class="stk-state {{ $storePage->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                {{ $storePage->is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <p class="text-muted mb-0">
                            <i class="feather icon-link"></i>
                            الرابط: <code>/page/{{ $storePage->slug }}</code>
                        </p>
                    </div>
                </div>

                <!-- المحتوى العربي -->
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> المحتوى (عربي)</h6>
                    </div>
                    <div class="card-body">
                        <div class="content-preview p-3 bg-light rounded">
                            {!! $storePage->content !!}
                        </div>
                    </div>
                </div>

                <!-- المحتوى الإنجليزي -->
                @if ($storePage->content_en)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-globe"></i> المحتوى (إنجليزي)</h6>
                        </div>
                        <div class="card-body">
                            <div class="content-preview p-3 bg-light rounded" dir="ltr">
                                {!! $storePage->content_en !!}
                            </div>
                        </div>
                    </div>
                @endif

                <!-- إعدادات SEO -->
                @if ($storePage->meta_title || $storePage->meta_description)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-search"></i> إعدادات SEO</h6>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                @if ($storePage->meta_title)
                                    <div class="stk-def__row">
                                        <dt>عنوان الميتا</dt>
                                        <dd>{{ $storePage->meta_title }}</dd>
                                    </div>
                                @endif

                                @if ($storePage->meta_description)
                                    <div class="stk-def__row">
                                        <dt>وصف الميتا</dt>
                                        <dd>{{ $storePage->meta_description }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                @endif
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-md-4">
                <!-- معلومات الصفحة -->
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات الصفحة</h6>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>المعرف</dt>
                                <dd class="stk-num">#{{ $storePage->id }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الترتيب</dt>
                                <dd class="stk-num">{{ $storePage->sort_order }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>عدد الكلمات</dt>
                                <dd class="stk-num">{{ $storePage->word_count ?? 0 }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>وقت القراءة</dt>
                                <dd class="stk-num">{{ $storePage->reading_time ?? 1 }} دقيقة</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإنشاء</dt>
                                <dd class="stk-num">{{ $storePage->created_at->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>آخر تعديل</dt>
                                <dd class="stk-num">{{ $storePage->updated_at->format('Y-m-d H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- الإجراءات -->
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-zap"></i> الإجراءات</h6>
                    </div>
                    <div class="card-body">
                        @if (hasPermission('online_store.pages.edit'))
                            <a href="{{ route('store_pages.edit', $storePage) }}" class="btn btn-primary btn-block mb-1">
                                <i class="feather icon-edit-2"></i> تعديل الصفحة
                            </a>
                        @endif

                        <a href="{{ route('store_pages.preview', $storePage) }}" class="btn btn-outline-info btn-block mb-1" target="_blank">
                            <i class="feather icon-external-link"></i> معاينة
                        </a>

                        @if (hasPermission('online_store.pages.toggle_status'))
                            <button type="button" class="btn btn-outline-success btn-block mb-1 toggle-status-btn" data-url="{{ route('store_pages.toggle-status', $storePage) }}">
                                <i class="feather icon-{{ $storePage->is_active ? 'eye-off' : 'eye' }}"></i>
                                {{ $storePage->is_active ? 'إلغاء النشر' : 'نشر الصفحة' }}
                            </button>
                        @endif

                        @if (hasPermission('online_store.pages.create'))
                            <button type="button" class="btn btn-outline-secondary btn-block mb-1 duplicate-btn" data-url="{{ route('store_pages.duplicate', $storePage) }}">
                                <i class="feather icon-copy"></i> نسخ الصفحة
                            </button>
                        @endif

                        @if (hasPermission('online_store.pages.delete'))
                            <hr>

                            <form action="{{ route('store_pages.destroy', $storePage) }}" method="POST" class="delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-block">
                                    <i class="feather icon-trash-2"></i> حذف الصفحة
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // تبديل الحالة
    $('.toggle-status-btn').click(function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء العملية');
            }
        });
    });

    // نسخ الصفحة
    $('.duplicate-btn').click(function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(function() {
                        window.location.href = '{{ route("store_pages.index") }}';
                    }, 1000);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء النسخ');
            }
        });
    });

    // تأكيد الحذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذه الصفحة نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
@endsection
