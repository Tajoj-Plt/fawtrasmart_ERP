@extends('account::master')

@section('title', 'تصنيفات سندات القبض')

@section('content')
    <div class="content-body">

        @section('page-actions')
            {{--
                الهدف كان `#info-modal-account` — معرّفٌ لا وجود له في الصفحة،
                والنافذة معرّفها `#modal_Create`. فالزرّ يُنقَر ولا يحدث شيء.
            --}}
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_Create">
                <i class="feather icon-plus"></i> تصنيف جديد
            </button>
        @endsection

        @include('account::components.page-head', [
            'title' => 'تصنيفات سندات القبض',
            'icon' => 'file-plus',
            'sub' => 'تصنيف المقبوضات لتقاريرها ولتوجيه حساباتها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الإعدادات المالية' => route('finance_settings.index'),
                'تصنيفات المقبوضات' => null,
            ],
        ])

        <div class="card stk-table-card">
            @include('account::finance.finance_settings.partials.receipt_table', [
                'receipt_categories' => $receipt_categories ?? [],
            ])
        </div>
    </div>

    {{--
        ترويسة النافذة كانت `bg-royal-deep` بنصٍّ أبيض — لونٌ لا يظهر في
        بقيّة نوافذ النظام ولا يتبع سمته. ومفتاح التعطيل كان
        `custom-control custom-switch` من بوتستراب 4، والصفحة تُحمّل
        بوتستراب 5 الذي لا يعرّف هذين الصنفين — فيظهر مربّع اختيارٍ عارٍ
        بتسميةٍ فارغة بجانبه.
    --}}
    <div class="modal fade" id="modal_Create" tabindex="-1" aria-labelledby="modal_CreateLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal_CreateLabel">تصنيف مقبوضات جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>

                <form class="form" action="{{ route('finance_settings.receipt_category_store') }}" method="POST">
                    @csrf

                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name" class="stk-req">الاسم</label>
                            <input type="text" id="name" class="form-control" name="name"
                                placeholder="اسم التصنيف" value="{{ old('name') }}" required autofocus>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <textarea id="description" name="description" class="form-control" rows="3" placeholder="وصف التصنيف">{{ old('description') }}</textarea>
                            @error('description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <label class="stk-switch" for="statusSwitch">
                            <input type="checkbox" id="statusSwitch" name="status">
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label">
                                <span class="stk-switch__title">تعطيل التصنيف</span>
                                <span class="stk-switch__hint">لا يظهر في قوائم اختيار التصنيف عند إنشاء السندات.</span>
                            </span>
                        </label>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> حفظ التصنيف
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.getElementById('modal_Create').addEventListener('shown.bs.modal', function() {
            var field = this.querySelector('[autofocus]');
            if (field) field.focus();
        });
    </script>
@endsection
