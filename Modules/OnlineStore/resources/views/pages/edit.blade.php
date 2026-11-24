@extends('onlinestore::master')

@section('title', 'تعديل الصفحة: ' . $storePage->title)

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_pages.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة للقائمة
            </a>
            @if (hasPermission('online_store.pages.edit'))
                <button type="submit" form="pageForm" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ التعديلات
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل الصفحة',
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

        <form action="{{ route('store_pages.update', $storePage) }}" method="POST" id="pageForm">
            @csrf
            @method('PUT')

            <div class="row">
                <!-- المحتوى الرئيسي -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> محتوى الصفحة</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="title" class="stk-req">عنوان الصفحة</label>
                                <input type="text" name="title" id="title"
                                    class="form-control @error('title') is-invalid @enderror"
                                    value="{{ old('title', $storePage->title) }}"
                                    required
                                    placeholder="أدخل عنوان الصفحة">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="slug">الرابط المختصر (Slug)</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">/page/</span>
                                    </div>
                                    <input type="text" name="slug" id="slug"
                                        class="form-control @error('slug') is-invalid @enderror"
                                        value="{{ old('slug', $storePage->slug) }}"
                                        placeholder="الرابط المختصر">
                                </div>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="content" class="stk-req">المحتوى (عربي)</label>
                                <textarea name="content" id="content"
                                    class="form-control editor @error('content') is-invalid @enderror"
                                    rows="15"
                                    required>{{ old('content', $storePage->content) }}</textarea>
                                @error('content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="content_en">المحتوى (إنجليزي)</label>
                                <textarea name="content_en" id="content_en"
                                    class="form-control editor @error('content_en') is-invalid @enderror"
                                    rows="15">{{ old('content_en', $storePage->content_en) }}</textarea>
                                @error('content_en')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- إعدادات SEO -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-search"></i> إعدادات SEO</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="meta_title">عنوان الميتا</label>
                                <input type="text" name="meta_title" id="meta_title"
                                    class="form-control @error('meta_title') is-invalid @enderror"
                                    value="{{ old('meta_title', $storePage->meta_title) }}"
                                    maxlength="255"
                                    placeholder="عنوان يظهر في نتائج البحث">
                                <small class="stk-help">يُفضل أن يكون بين 50-60 حرفاً.</small>
                                @error('meta_title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="meta_description">وصف الميتا</label>
                                <textarea name="meta_description" id="meta_description"
                                    class="form-control @error('meta_description') is-invalid @enderror"
                                    rows="3"
                                    maxlength="500"
                                    placeholder="وصف مختصر يظهر في نتائج البحث">{{ old('meta_description', $storePage->meta_description) }}</textarea>
                                <small class="stk-help">يُفضل أن يكون بين 150-160 حرفاً.</small>
                                @error('meta_description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الإعدادات الجانبية -->
                <div class="col-md-4">
                    <!-- النشر -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-settings"></i> إعدادات النشر</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="type" class="stk-req">نوع الصفحة</label>
                                <select name="type" id="type" class="form-control select2 @error('type') is-invalid @enderror" required>
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected(old('type', $storePage->type->value ?? $storePage->type) == $type->value)>
                                            @switch($type->value)
                                                @case('page')
                                                    صفحة عادية
                                                    @break
                                                @case('policy')
                                                    سياسة
                                                    @break
                                                @case('faq')
                                                    أسئلة شائعة
                                                    @break
                                                @default
                                                    {{ $type->value }}
                                            @endswitch
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="sort_order">الترتيب</label>
                                <input type="number" name="sort_order" id="sort_order"
                                    class="form-control @error('sort_order') is-invalid @enderror"
                                    value="{{ old('sort_order', $storePage->sort_order) }}"
                                    min="0">
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <label class="stk-switch" for="is_active">
                                <input type="checkbox" id="is_active" name="is_active" value="1"
                                    {{ old('is_active', $storePage->is_active) ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">نشر الصفحة</span>
                                    <span class="stk-switch__hint">الصفحات النشطة تظهر للزوار.</span>
                                </span>
                            </label>

                            <hr>

                            <a href="{{ route('store_pages.preview', $storePage) }}" class="btn btn-outline-secondary btn-block" target="_blank">
                                <i class="feather icon-eye"></i> معاينة
                            </a>
                        </div>
                    </div>

                    <!-- معلومات الصفحة -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات الصفحة</h6>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>تاريخ الإنشاء</dt>
                                    <dd class="stk-num">{{ $storePage->created_at->format('Y-m-d H:i') }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>آخر تعديل</dt>
                                    <dd class="stk-num">{{ $storePage->updated_at->format('Y-m-d H:i') }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>عدد الكلمات</dt>
                                    <dd class="stk-num">{{ $storePage->word_count ?? 0 }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>وقت القراءة</dt>
                                    <dd class="stk-num">{{ $storePage->reading_time ?? 1 }} دقيقة</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- إجراءات إضافية -->
                    @if (hasPermission(['online_store.pages.create', 'online_store.pages.delete']))
                        <div class="card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-zap"></i> إجراءات سريعة</h6>
                            </div>
                            <div class="card-body">
                                @if (hasPermission('online_store.pages.create'))
                                    <button type="button" class="btn btn-outline-info btn-block mb-1 duplicate-btn" data-url="{{ route('store_pages.duplicate', $storePage) }}">
                                        <i class="feather icon-copy"></i> نسخ الصفحة
                                    </button>
                                @endif
                                @if (hasPermission('online_store.pages.delete'))
                                    {{-- الزرّ يشير إلى نموذج الحذف المستقل أسفل الصفحة (سمة form) —
                                         لا نموذجَ داخل نموذج: التداخل كان يحذف الصفحة عند «حفظ». --}}
                                    <button type="submit" form="pageDeleteForm" class="btn btn-outline-danger btn-block">
                                        <i class="feather icon-trash-2"></i> حذف الصفحة
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </form>

        @if (hasPermission('online_store.pages.delete'))
            <form id="pageDeleteForm" action="{{ route('store_pages.destroy', $storePage) }}" method="POST" class="delete-form d-none">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>
@endsection

@section('scripts')
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
$(document).ready(function() {
    // تهيئة Select2
    if ($.fn.select2) {
        $('.select2').select2({
            dir: 'rtl',
            width: '100%'
        });
    }

    // تهيئة CKEditor
    if (typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(document.querySelector('#content'), {
                language: 'ar',
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'blockQuote', 'insertTable', 'undo', 'redo']
            })
            .catch(error => {
                console.error(error);
            });

        ClassicEditor
            .create(document.querySelector('#content_en'), {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'blockQuote', 'insertTable', 'undo', 'redo']
            })
            .catch(error => {
                console.error(error);
            });
    }

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
