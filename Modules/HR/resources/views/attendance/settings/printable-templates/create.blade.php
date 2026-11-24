@extends('hr::master')

@section('title', 'قالب طباعة جديد')

@section('content')
    @include('hr::attendance._partials.styles')


    <div class="stk-page-head">
        <div class="stk-page-head__titles">
            <span class="stk-page-head__icon" aria-hidden="true"><i class="feather icon-printer"></i></span>
            <div class="min-width-0">
                <h2 class="stk-page-head__title">قالب طباعة جديد</h2>
                <div class="stk-page-head__sub">اسم القالب ونوعه ومقاس ورقه — التصميم بعد الحفظ</div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('attendance.settings.printable-templates.store') }}">
        @csrf
        <div class="att-card">
            <div class="att-card-head"><span><i class="feather icon-file-text"></i> بيانات القالب</span></div>
            <div class="att-card-body">
                <div class="att-field">
                    <label>اسم القالب <span class="req">*</span></label>
                    <input type="text" name="name" class="form-control" maxlength="255" required
                        placeholder="مثال: عقد عمل — الفرع الرئيسي" value="{{ old('name') }}">
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="att-field">
                            <label>نوع المستند <span class="req">*</span></label>
                            <select name="document_type" class="form-select" required>
                                @foreach($documentTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('document_type') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="hint">أنواع الموارد البشرية فقط</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="att-field">
                            <label>مقاس الورق <span class="req">*</span></label>
                            <select name="paper_size" class="form-select" required>
                                @foreach($paperSizes as $value => $label)
                                    <option value="{{ $value }}" {{ old('paper_size', 'A4') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="att-field">
                            <label>التوجيه</label>
                            <select name="orientation" class="form-select">
                                <option value="portrait" {{ old('orientation', 'portrait') === 'portrait' ? 'selected' : '' }}>رأسي</option>
                                <option value="landscape" {{ old('orientation') === 'landscape' ? 'selected' : '' }}>عرضي</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    <i class="feather icon-info me-2"></i>
                    يُنشأ القالب بمحتوى مبدئي. لتصميمه بعد الحفظ افتح المحرِّر من زر التعديل في قائمة القوالب —
                    وهو محرِّرٌ مشترك يقع في شاشة الإعدادات العامة.
                </div>
            </div>
        </div>

        <div class="att-toolbar">
            <button type="submit" class="btn btn-primary">
                <i class="feather icon-save me-2"></i>حفظ القالب
            </button>
            <a href="{{ route('attendance.settings.printable-templates.index') }}" class="btn btn-outline-secondary">
                إلغاء
            </a>
        </div>
    </form>
@endsection
