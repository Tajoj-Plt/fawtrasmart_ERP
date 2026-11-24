@extends('onlinestore::master')

@section('title', 'تعديل قالب: ' . $emailTemplate->template_name)

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('email_templates.preview', $emailTemplate) }}" class="btn btn-outline-info" target="_blank">
                <i class="feather icon-monitor"></i> معاينة
            </a>
            <a href="{{ route('email_templates.show', $emailTemplate) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة للتفاصيل
            </a>
            @if (hasPermission('online_store.email_templates.edit'))
                <button type="submit" form="template-form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ التعديلات
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل قالب البريد',
            'icon' => 'mail',
            'sub' => $emailTemplate->template_name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قوالب البريد' => route('email_templates.index'),
                'تعديل' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form action="{{ route('email_templates.update', $emailTemplate) }}" method="POST" id="template-form">
            @csrf
            @method('PUT')

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
                                               value="{{ old('template_name', $emailTemplate->template_name) }}" required>
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
                                               value="{{ old('template_code', $emailTemplate->template_code) }}" required>
                                        @error('template_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="subject" class="stk-req">موضوع البريد</label>
                                <input type="text" name="subject" id="subject"
                                       class="form-control @error('subject') is-invalid @enderror"
                                       value="{{ old('subject', $emailTemplate->subject) }}" required>
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="html_content">محتوى HTML</label>
                                <textarea name="html_content" id="html_content" rows="15"
                                          class="form-control @error('html_content') is-invalid @enderror">{{ old('html_content', $emailTemplate->html_content) }}</textarea>
                                @error('html_content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="text_content">محتوى نصي (بديل)</label>
                                <textarea name="text_content" id="text_content" rows="6"
                                          class="form-control @error('text_content') is-invalid @enderror">{{ old('text_content', $emailTemplate->text_content) }}</textarea>
                                @error('text_content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="variables">المتغيرات</label>
                                <input type="text" name="variables" id="variables"
                                       class="form-control @error('variables') is-invalid @enderror"
                                       value="{{ old('variables', $variables) }}">
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
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}"
                                                @selected(old('template_category', $emailTemplate->template_category?->value ?? $emailTemplate->template_category) == $category->value)>
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
                                    <option value="ar" @selected(old('language', $emailTemplate->language) == 'ar')>العربية</option>
                                    <option value="en" @selected(old('language', $emailTemplate->language) == 'en')>English</option>
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
                                               {{ old('is_active', $emailTemplate->is_active) ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                            @if (hasPermission('online_store.email_templates.edit'))
                                <hr>
                                <div class="form-group mb-0">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="feather icon-save"></i> حفظ التعديلات
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- إرسال تجريبي --}}
                    @if (hasPermission('online_store.email_templates.send_test'))
                        <div class="card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-send"></i> إرسال تجريبي</h6>
                            </div>
                            <div class="card-body">
                                {{-- الحقل والزرّ يتبعان النموذج المستقل `test-form` أسفل الصفحة
                                     (سمة form) — لا نموذجَ داخل نموذج. --}}
                                <div class="form-group">
                                    <input type="email" name="test_email" form="test-form" class="form-control"
                                           placeholder="البريد الإلكتروني للاختبار" required>
                                </div>
                                <button type="submit" form="test-form" class="btn btn-outline-info btn-block">
                                    <i class="feather icon-send"></i> إرسال نسخة تجريبية
                                </button>
                                <small class="text-muted d-block mt-1">يُرسَل آخر ما حُفظ — احفظ تعديلاتك أولاً.</small>
                            </div>
                        </div>
                    @endif

                    {{-- معلومات إضافية --}}
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-clock"></i> معلومات القالب</h6>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>تاريخ الإنشاء</dt>
                                    <dd>{{ $emailTemplate->created_at->format('Y-m-d H:i') }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>آخر تعديل</dt>
                                    <dd>{{ $emailTemplate->updated_at->format('Y-m-d H:i') }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>عدد مرات الإرسال</dt>
                                    <dd class="stk-num">{{ $emailTemplate->emailLogs()->count() }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @if (hasPermission('online_store.email_templates.send_test'))
            <form action="{{ route('email_templates.send-test', $emailTemplate) }}" method="POST" id="test-form" class="d-none">
                @csrf
            </form>
        @endif
    </div>
@endsection
