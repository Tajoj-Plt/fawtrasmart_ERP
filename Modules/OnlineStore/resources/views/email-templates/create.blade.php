@extends('onlinestore::master')

@section('title', 'إضافة قالب بريد جديد')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('email_templates.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.email_templates.create'))
                <button type="submit" form="template-form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ القالب
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة قالب بريد جديد',
            'icon' => 'mail',
            'sub' => 'قالب رسالة آلية جديد يُستخدم في مراسلات المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قوالب البريد' => route('email_templates.index'),
                'إضافة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form action="{{ route('email_templates.store') }}" method="POST" id="template-form">
            @csrf

            <div class="row">
                {{-- المعلومات الأساسية --}}
                <div class="col-md-8">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات القالب</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="template_name" class="stk-req">اسم القالب</label>
                                        <input type="text" name="template_name" id="template_name"
                                               class="form-control @error('template_name') is-invalid @enderror"
                                               value="{{ old('template_name') }}" required>
                                        @error('template_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="template_code" class="stk-req">كود القالب</label>
                                        <input type="text" name="template_code" id="template_code"
                                               class="form-control @error('template_code') is-invalid @enderror"
                                               value="{{ old('template_code') }}" placeholder="مثال: order.confirmed" required>
                                        @error('template_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">استخدم صيغة: category.action</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="subject" class="stk-req">موضوع البريد</label>
                                <input type="text" name="subject" id="subject"
                                       class="form-control @error('subject') is-invalid @enderror"
                                       value="{{ old('subject') }}"
                                       placeholder="مثال: تم استلام طلبك #@{{ '{{ order_number }}' }}" required>
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">يمكنك استخدام المتغيرات مثل: @{{ '{{ customer_name }}' }}</small>
                            </div>

                            <div class="form-group">
                                <label for="html_content">محتوى HTML</label>
                                <textarea name="html_content" id="html_content" rows="15"
                                          class="form-control @error('html_content') is-invalid @enderror">{{ old('html_content') }}</textarea>
                                @error('html_content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="text_content">محتوى نصي (بديل)</label>
                                <textarea name="text_content" id="text_content" rows="6"
                                          class="form-control @error('text_content') is-invalid @enderror"
                                          placeholder="نسخة نصية بسيطة للعملاء الذين لا يدعمون HTML">{{ old('text_content') }}</textarea>
                                @error('text_content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="variables">المتغيرات</label>
                                <input type="text" name="variables" id="variables"
                                       class="form-control @error('variables') is-invalid @enderror"
                                       value="{{ old('variables') }}"
                                       placeholder="customer_name, order_number, total, store_name">
                                @error('variables')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">المتغيرات مفصولة بفواصل</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- الإعدادات الجانبية --}}
                <div class="col-md-4">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-settings"></i> الإعدادات</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="template_category" class="stk-req">التصنيف</label>
                                <select name="template_category" id="template_category"
                                        class="form-control @error('template_category') is-invalid @enderror" required>
                                    <option value="">اختر التصنيف</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}" @selected(old('template_category') == $category->value)>
                                            {{ $category->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('template_category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="language" class="stk-req">اللغة</label>
                                <select name="language" id="language"
                                        class="form-control @error('language') is-invalid @enderror" required>
                                    <option value="ar" @selected(old('language', 'ar') == 'ar')>العربية</option>
                                    <option value="en" @selected(old('language') == 'en')>English</option>
                                </select>
                                @error('language')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="mb-0">القالب مفعل</label>
                                    <label class="ios-switch">
                                        <input type="checkbox" name="is_active" value="1" id="is_active"
                                               {{ old('is_active', true) ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                            @if (hasPermission('online_store.email_templates.create'))
                                <hr>
                                <div class="form-group mb-0">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="feather icon-save"></i> حفظ القالب
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- المتغيرات الشائعة --}}
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-code"></i> المتغيرات الشائعة</h6>
                        </div>
                        <div class="card-body">
                            <div class="variables-list">
                                <code class="d-block mb-1" onclick="copyVariable('store_name')">@{{ '{{ store_name }}' }}</code>
                                <code class="d-block mb-1" onclick="copyVariable('customer_name')">@{{ '{{ customer_name }}' }}</code>
                                <code class="d-block mb-1" onclick="copyVariable('customer_email')">@{{ '{{ customer_email }}' }}</code>
                                <code class="d-block mb-1" onclick="copyVariable('order_number')">@{{ '{{ order_number }}' }}</code>
                                <code class="d-block mb-1" onclick="copyVariable('total')">@{{ '{{ total }}' }}</code>
                                <code class="d-block mb-1" onclick="copyVariable('store_url')">@{{ '{{ store_url }}' }}</code>
                                <code class="d-block mb-1" onclick="copyVariable('support_email')">@{{ '{{ support_email }}' }}</code>
                                <code class="d-block mb-1" onclick="copyVariable('unsubscribe_url')">@{{ '{{ unsubscribe_url }}' }}</code>
                            </div>
                            <small class="text-muted mt-2 d-block">انقر للنسخ</small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
function copyVariable(varName) {
    const text = '{{ ' + varName + ' }}';
    navigator.clipboard.writeText(text).then(() => {
        toastr.success('تم نسخ المتغير');
    });
}

$(document).ready(function() {
    // توليد كود القالب من الاسم
    $('#template_name').on('blur', function() {
        if (!$('#template_code').val()) {
            const name = $(this).val().toLowerCase()
                .replace(/\s+/g, '_')
                .replace(/[^\w؀-ۿ]/g, '');
            $('#template_code').val(name);
        }
    });
});
</script>
@endsection
