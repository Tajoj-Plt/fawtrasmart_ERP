{{--
    إضافة تصنيف عميل — بنفس نظام تصميم الوحدة (`.cl-scope` + page-head)
    ونفس هيكل نموذج «إضافة عميل» (بطاقة حفظ/إلغاء علوية + بطاقة حقول).

    أُصلح أيضاً: عناصر التحقّق لم تكن ملفوفةً بـ`.form-group`، فكان
    `errorPlacement` في jQuery Validate (`element.closest('.form-group')`)
    لا يجد شيئاً ولا يضع رسالة الخطأ — يفشل بصمت. صارت الحقول ملفوفة.
--}}
@extends('client::master')

@section('title')
    إضافة تصنيف عملاء جديد
@stop

@section('content')
    @include('client::partials.settings-theme')

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'إضافة تصنيف عملاء',
            'icon' => 'plus-circle',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'تصنيفات العملاء' => route('categoriesClient.index'),
                'إضافة' => null,
            ],
        ])

        <form action="{{ route('categoriesClient.store') }}" method="POST" id="categoryForm">
            @csrf

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <label class="mb-0">الحقول التي عليها علامة <span class="text-danger">*</span> إلزامية</label>
                        <div>
                            <a href="{{ route('categoriesClient.index') }}" class="btn btn-outline-danger">
                                <i class="fa fa-ban"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fa fa-save"></i> حفظ
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title">بيانات التصنيف</h4></div>
                <div class="card-content">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">اسم التصنيف <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                        placeholder="أدخل اسم التصنيف" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="active">الحالة</label>
                                    <select name="active" id="active" class="form-control select2">
                                        <option value="1" {{ old('active', 1) == 1 ? 'selected' : '' }}>نشط</option>
                                        <option value="0" {{ old('active', 1) == 0 ? 'selected' : '' }}>غير نشط</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="description">الوصف</label>
                                    <textarea class="form-control" id="description" name="description"
                                        rows="3" placeholder="أدخل وصفًا للتصنيف (اختياري)">{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('#categoryForm').validate({
                rules: {
                    name: { required: true, maxlength: 255 }
                },
                messages: {
                    name: { required: "حقل اسم التصنيف مطلوب", maxlength: "يجب ألا يتجاوز اسم التصنيف 255 حرفاً" }
                },
                errorElement: 'div',
                errorPlacement: function (error, element) {
                    error.addClass('invalid-feedback d-block');
                    element.closest('.form-group').append(error);
                },
                highlight: function (element) {
                    $(element).addClass('is-invalid');
                },
                unhighlight: function (element) {
                    $(element).removeClass('is-invalid');
                }
            });
        });
    </script>
@endsection
