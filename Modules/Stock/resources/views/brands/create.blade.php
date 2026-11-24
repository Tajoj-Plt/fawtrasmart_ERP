@extends('stock::master')

@section('title', 'إضافة براند')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إضافة براند',
            'icon' => 'plus-circle',
            'sub' => 'علامة تجارية جديدة وبيانات التواصل معها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'البراندات' => route('stock.brands.index'),
                'إضافة' => null,
            ],
            'actions' => '<a href="' . route('stock.brands.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="brand_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ البراند</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="brand_form" action="{{ route('stock.brands.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row">
                {{-- ══════════ العمود الرئيسي ══════════ --}}
                <div class="col-lg-8 col-12">

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="name" class="stk-req">اسم البراند</label>
                                        <input type="text" id="name" name="name" required
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name') }}" placeholder="مثال: نستله">
                                        @error('name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

<div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="description">الوصف</label>
                                        <textarea id="description" name="description" rows="3"
                                                  class="form-control @error('description') is-invalid @enderror"
                                                  placeholder="نبذة مختصرة عن العلامة التجارية">{{ old('description') }}</textarea>
                                        @error('description')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-search"></i> بيانات الأرشفة (SEO)</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="website">الموقع الإلكتروني</label>
                                        <input type="url" id="website" name="website"
                                               class="form-control @error('website') is-invalid @enderror"
                                               value="{{ old('website') }}" placeholder="https://example.com" dir="ltr">
                                        @error('website')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="meta_title">عنوان الصفحة</label>
                                        <input type="text" id="meta_title" name="meta_title"
                                               class="form-control @error('meta_title') is-invalid @enderror"
                                               value="{{ old('meta_title') }}" placeholder="يظهر في نتائج البحث">
                                        @error('meta_title')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="meta_description">وصف الصفحة</label>
                                        <textarea id="meta_description" name="meta_description" rows="2"
                                                  class="form-control @error('meta_description') is-invalid @enderror"
                                                  placeholder="سطران يظهران تحت العنوان في نتائج البحث">{{ old('meta_description') }}</textarea>
                                        @error('meta_description')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ══════════ العمود الجانبي ══════════ --}}
                <div class="col-lg-4 col-12">

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-image"></i> شعار البراند</h4>
                        </div>
                        <div class="card-body">
                            <label for="logo" class="stk-upload">
                                <img src="" alt="" class="stk-upload__preview d-none" id="preview_img">
                                <span class="stk-upload__placeholder" id="upload_placeholder">
                                    <i class="feather icon-upload-cloud"></i>
                                    <span class="stk-upload__title">اسحب الشعار أو اضغط للاختيار</span>
                                    <span class="stk-upload__hint">PNG أو JPG — بحدّ أقصى 2 ميغابايت</span>
                                </span>
                            </label>
                            <input type="file" id="logo" name="logo" accept="image/*" class="d-none">

                            <button type="button" class="btn btn-outline-danger btn-sm w-100 mt-2 d-none" id="remove_img">
                                <i class="feather icon-trash-2"></i> إزالة الشعار
                            </button>

                            @error('logo')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-settings"></i> الإعدادات</h4>
                        </div>
                        <div class="card-body">
                            <label class="stk-switch mb-3">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">البراند نشط</span>
                                    <span class="stk-switch__hint">غير النشط لا يظهر عند إضافة منتج</span>
                                </span>
                            </label>

</div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('stock.brands.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ البراند
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            const $file = $('#logo'), $prev = $('#preview_img'),
                  $ph = $('#upload_placeholder'), $rm = $('#remove_img');

            $file.on('change', function () {
                const f = this.files && this.files[0];
                if (!f) return;
                const r = new FileReader();
                r.onload = e => {
                    $prev.attr('src', e.target.result).removeClass('d-none');
                    $ph.addClass('d-none');
                    $rm.removeClass('d-none');
                };
                r.readAsDataURL(f);
            });

            $rm.on('click', function () {
                $file.val('');
                $prev.attr('src', '').addClass('d-none');
                $ph.removeClass('d-none');
                $(this).addClass('d-none');
            });
        });
    </script>
@endsection
