@extends('onlinestore::master')

@section('title', 'إضافة صفحة جديدة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_pages.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة للقائمة
            </a>
            @if (hasPermission('online_store.pages.create'))
                <button type="submit" form="pageForm" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ الصفحة
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة صفحة جديدة',
            'icon' => 'file-text',
            'sub' => 'صفحة ثابتة جديدة تُعرض في المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'صفحات المتجر' => route('store_pages.index'),
                'إضافة صفحة جديدة' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <form action="{{ route('store_pages.store') }}" method="POST" id="pageForm">
            @csrf

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
                                    value="{{ old('title') }}"
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
                                        value="{{ old('slug') }}"
                                        placeholder="سيتم إنشاؤه تلقائياً من العنوان">
                                </div>
                                <small class="stk-help">اتركه فارغاً لإنشائه تلقائياً من العنوان.</small>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="content" class="stk-req">المحتوى (عربي)</label>
                                <textarea name="content" id="content"
                                    class="form-control editor @error('content') is-invalid @enderror"
                                    rows="15"
                                    required>{{ old('content') }}</textarea>
                                @error('content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="content_en">المحتوى (إنجليزي)</label>
                                <textarea name="content_en" id="content_en"
                                    class="form-control editor @error('content_en') is-invalid @enderror"
                                    rows="15">{{ old('content_en') }}</textarea>
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
                                    value="{{ old('meta_title') }}"
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
                                    placeholder="وصف مختصر يظهر في نتائج البحث">{{ old('meta_description') }}</textarea>
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
                                        <option value="{{ $type->value }}" @selected(old('type') == $type->value)>
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
                                    value="{{ old('sort_order', 0) }}"
                                    min="0">
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <label class="stk-switch" for="is_active">
                                <input type="checkbox" id="is_active" name="is_active" value="1"
                                    {{ old('is_active', true) ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">نشر الصفحة</span>
                                    <span class="stk-switch__hint">الصفحات النشطة تظهر للزوار.</span>
                                </span>
                            </label>

                            <hr>

                            <button type="button" class="btn btn-outline-secondary btn-block" id="previewBtn">
                                <i class="feather icon-eye"></i> معاينة
                            </button>
                        </div>
                    </div>

                    <!-- معلومات مساعدة -->
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6><i class="feather icon-info"></i> نصائح</h6>
                            <ul class="mb-0 pr-3">
                                <li>استخدم عنواناً واضحاً ومختصراً</li>
                                <li>أضف محتوى بالإنجليزية لدعم الزوار الأجانب</li>
                                <li>اختر النوع المناسب للصفحة</li>
                                <li>أضف وصف ميتا لتحسين ظهور الصفحة في محركات البحث</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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

    // إنشاء slug تلقائياً
    $('#title').on('blur', function() {
        var title = $(this).val();
        var slug = $('#slug').val();

        if (!slug && title) {
            // تحويل العنوان إلى slug
            var generatedSlug = title
                .toLowerCase()
                .replace(/[^ء-يa-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim();
            $('#slug').val(generatedSlug);
        }
    });

    // معاينة
    $('#previewBtn').click(function() {
        var content = $('#content').val();
        var title = $('#title').val();

        if (!title || !content) {
            toastr.warning('يرجى ملء العنوان والمحتوى أولاً');
            return;
        }

        // فتح نافذة معاينة
        var previewWindow = window.open('', '_blank');
        previewWindow.document.write(`
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <meta charset="UTF-8">
                <title>معاينة: ${title}</title>
                <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { font-family: 'Cairo', sans-serif; padding: 20px; }
                    .preview-content { max-width: 800px; margin: 0 auto; }
                </style>
            </head>
            <body>
                <div class="preview-content">
                    <h1>${title}</h1>
                    <hr>
                    <div>${content}</div>
                </div>
            </body>
            </html>
        `);
    });
});
</script>
@endsection
