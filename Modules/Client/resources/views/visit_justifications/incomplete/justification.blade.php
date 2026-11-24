@extends('client::master')

@section('title')
    تبرير الزيارات غير المكتملة
@stop

{{-- client::master لا يملك @yield('css') — @push('styles') هو ما يقرؤه فعلاً (master.blade.php:12) --}}
@push('styles')
    <style>
        /* تحسين العرض بشكل عام */
        .empty-state {
            text-align: center;
            padding: 40px 0;
        }
        .empty-state i {
            font-size: 3.5rem;
            color: #b8c2cc;
            margin-bottom: 15px;
        }
        .info-icon {
            font-size: 1.5rem;
            margin-right: 15px;
        }

        /* تحسين مظهر حقول التبرير في جميع أحجام الشاشة */
        .justification-area {
            min-height: 100px;
            width: 100%;
            resize: vertical;
        }

        .visit-card {
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
        }

        .visit-card .card-header {
            background-color: rgba(0,0,0,.03);
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 0.75rem 1.25rem;
        }

        .visit-info {
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }

        /* تنسيق رسائل الخطأ */
        .error-alert {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 0.25rem;
            color: #721c24;
            padding: 1rem;
            margin-bottom: 1rem;
            display: none;
        }

        .error-alert.show {
            display: block;
        }

        .error-alert ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .error-alert li {
            margin-bottom: 0.5rem;
        }

        .invalid-field-highlight {
            border-color: #dc3545 !important;
            background-color: #fff5f5;
        }

        /* تعديلات للعرض المتجاوب */
        @media (max-width: 767.98px) {
            .justification-area {
                min-height: 120px;
            }
        }

        @media (min-width: 768px) {
            .justification-area {
                min-height: 100px;
            }
        }
    </style>
    <!-- إضافة مكتبة SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- ترويسة الصفحة -->
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تبرير الزيارات غير المكتملة</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{route('dashboard_sales.index')}}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">تبرير الزيارات</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        <!-- تنبيه هام -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-3">
                <div class="alert alert-warning d-flex align-items-start mb-0">
                    <div class="info-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h5 class="alert-heading mb-1">
                            <i class="fas fa-bell me-1"></i>تنبيه مهم!
                        </h5>
                        <p class="mb-0">
                            لديك <strong>{{ $incompleteVisits->count() }}</strong> زيارة غير مكتملة في خط السير.
                            يرجى تقديم تبرير لكل زيارة حتى تتمكن من استخدام النظام بشكل طبيعي.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول الزيارات غير المكتملة -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">
                        <i class="fas fa-clipboard-list me-1"></i>
                        الزيارات غير المكتملة
                        @if(!$incompleteVisits->isEmpty())
                            <span class="badge bg-danger ms-1">{{ $incompleteVisits->count() }}</span>
                        @endif
                    </h4>
                </div>

                @if($incompleteVisits->isEmpty())
                    <!-- حالة فارغة -->
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h4 class="mb-1">ممتاز!</h4>
                        <p class="mb-0">لا توجد زيارات غير مكتملة في الوقت الحالي.</p>
                        <div class="mt-3">

                        </div>
                    </div>
                @else
                    <form action="{{ route('incomplete.visits.submit') }}" method="POST" id="justification-form">
                        @csrf

                        <!-- حاوية رسائل الأخطاء -->
                        <div class="error-alert" id="error-container">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-circle me-2" style="font-size: 1.3rem; margin-top: 0.2rem;"></i>
                                <div>
                                    <h5 class="mb-2">
                                        <strong>تم العثور على أخطاء:</strong>
                                    </h5>
                                    <ul id="error-list">
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- عرض البطاقات (يظهر على جميع الأجهزة) -->
                        <div class="visits-container">
                            @foreach($incompleteVisits as $index => $visit)
                                <div class="visit-card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-building me-1"></i>
                                            <strong>{{ $visit->client->trade_name ?? 'غير محدد' }}</strong>
                                        </span>
                                        <span class="badge bg-light-primary">{{ $visit->day_of_week }}</span>
                                    </div>
                                    <div class="visit-info">
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-hashtag me-1"></i>
                                                كود العميل: {{ $visit->client->code ?? '---' }}
                                            </small>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                التاريخ: {{ \Carbon\Carbon::parse($visit->created_at)->locale('ar')->translatedFormat('l j F Y') }}
                                            </small>
                                        </div>
                                        <div>
                                            <small class="fw-bold">{{ $visit->year }}</small> -
                                            <small class="text-muted">الأسبوع {{ $visit->week_number }}</small>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <label class="form-label fw-bold">التبرير <span class="text-danger">*</span></label>
                                        <textarea
                                            name="justifications[{{ $visit->id }}]"
                                            class="form-control justification-field justification-area @error('justifications.' . $visit->id) is-invalid @enderror"
                                            placeholder="يرجى تقديم تبرير واضح ومفصل لعدم إتمام الزيارة..."
                                            required>{{ old('justifications.' . $visit->id) }}</textarea>
                                        @error('justifications.' . $visit->id)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- نصائح كتابة التبرير -->
                        <div class="alert alert-info d-flex align-items-start mt-4">
                            <div class="info-icon">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <div>
                                <h5 class="alert-heading mb-1">
                                    <i class="fas fa-info-circle me-1"></i>نصائح مهمة
                                </h5>
                                <ul class="mb-0">
                                    <li>سيتم مراجعة تبريراتك من قبل الإدارة في أقرب وقت ممكن</li>
                                    <li>بعد الموافقة على التبريرات، ستتمكن من استخدام النظام بشكل طبيعي</li>
                                    <li>في حالة رفض أي تبرير، سيُطلب منك تعديله وإعادة إرساله</li>
                                    <li>يرجى التأكد من وضوح ودقة التبريرات المقدمة</li>
                                </ul>
                            </div>
                        </div>

                        <!-- أزرار الإجراءات -->
                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">


                            <button type="submit" class="btn btn-primary" id="submit-button">
                                <i class="fas fa-paper-plane me-1"></i> تقديم التبريرات
                            </button>
                        </div>
                    </form>
                @endif
                  <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right me-1"></i> العودة للرئيسية
                                </a>

            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- إضافة مكتبة SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // التحقق من وجود النموذج وزر الإرسال
            const form = document.getElementById('justification-form');
            const submitButton = document.getElementById('submit-button');

            if (form) {
                console.log('تم العثور على النموذج'); // للتأكد من أن النموذج موجود

                // إضافة مستمع حدث للنموذج
                form.addEventListener('submit', function(e) {
                    // منع السلوك الافتراضي للنموذج
                    e.preventDefault();
                    console.log('تم تنفيذ حدث تقديم النموذج');

                    // إخفاء رسائل الخطأ السابقة
                    const errorContainer = document.getElementById('error-container');
                    const errorList = document.getElementById('error-list');

                    // التحقق من وجود العناصر
                    if (errorList) {
                        errorList.innerHTML = '';
                    }
                    if (errorContainer) {
                        errorContainer.classList.remove('show');
                    }

                    // التحقق من جميع حقول التبرير
                    let isEmpty = false;
                    let emptyFields = [];
                    let errorCount = 0;

                    document.querySelectorAll('.justification-field').forEach(function(field, index) {
                        // إزالة تنسيق الخطأ السابق
                        field.classList.remove('invalid-field-highlight', 'is-invalid');

                        if (field.value.trim() === '') {
                            isEmpty = true;
                            errorCount++;
                            field.classList.add('invalid-field-highlight');

                            // الحصول على اسم العميل والمعلومات
                            const card = field.closest('.visit-card');
                            const clientName = card ? card.querySelector('strong').textContent : `الزيارة ${index + 1}`;
                            const visitInfo = card ? card.querySelector('.visit-info').textContent.trim() : '';

                            // إضافة الخطأ للقائمة
                            if (errorList) {
                                const errorItem = document.createElement('li');
                                errorItem.innerHTML = `<strong>${clientName}</strong> - يرجى ملء حقل التبرير`;
                                errorList.appendChild(errorItem);
                            }

                            emptyFields.push(field);
                        }
                    });

                    if (isEmpty) {
                        // عرض حاوية الأخطاء
                        if (errorContainer) {
                            errorContainer.classList.add('show');
                            // التمرير إلى أعلى الصفحة لعرض الأخطاء
                            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }

                        // عرض تنبيه SweetAlert مع عدد الأخطاء
                        Swal.fire({
                            title: 'توجد أخطاء!',
                            text: `يرجى ملء ${errorCount} حقل(قول) من حقول التبريرات المتبقية`,
                            icon: 'error',
                            confirmButtonText: 'حسناً'
                        });
                        return;
                    }

                    // تأكيد الإرسال
                    Swal.fire({
                        title: 'تأكيد',
                        text: 'هل أنت متأكد من تقديم جميع التبريرات؟',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'نعم، تقديم التبريرات',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            console.log('تم تأكيد الإرسال');
                            // إنشاء عنصر مخفي لتجنب مشكلة SweetAlert
                            const submitForm = document.createElement('form');
                            submitForm.method = form.method;
                            submitForm.action = form.action;

                            // نسخ جميع حقول النموذج
                            const formData = new FormData(form);
                            for (const [name, value] of formData.entries()) {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = name;
                                input.value = value;
                                submitForm.appendChild(input);
                            }

                            // إضافة عنصر الحماية من CSRF
                            const csrfToken = document.querySelector('meta[name="csrf-token"]');
                            if (csrfToken) {
                                const csrfInput = document.createElement('input');
                                csrfInput.type = 'hidden';
                                csrfInput.name = '_token';
                                csrfInput.value = csrfToken.content;
                                submitForm.appendChild(csrfInput);
                            }

                            document.body.appendChild(submitForm);
                            submitForm.submit();
                        }
                    });
                });

                // إضافة مستمع حدث للزر للتأكد من عمله
                if (submitButton) {
                    submitButton.addEventListener('click', function(e) {
                        console.log('تم النقر على زر الإرسال');
                    });
                } else {
                    console.error('زر الإرسال غير موجود!');
                }

                // إزالة تنسيق الخطأ عند الكتابة
                document.querySelectorAll('.justification-field').forEach(function(field) {
                    field.addEventListener('input', function() {
                        if (this.value.trim() !== '') {
                            this.classList.remove('invalid-field-highlight', 'is-invalid');

                            // إعادة التحقق من جميع الحقول وإخفاء الأخطاء إذا كانت جميعها ممتلئة
                            let allFilled = true;
                            document.querySelectorAll('.justification-field').forEach(function(f) {
                                if (f.value.trim() === '') {
                                    allFilled = false;
                                }
                            });

                            if (allFilled) {
                                const errorContainer = document.getElementById('error-container');
                                if (errorContainer) {
                                    errorContainer.classList.remove('show');
                                }
                            }
                        }
                    });
                });

                // تعديل ارتفاع مناطق النص
                function adjustTextareaHeight() {
                    document.querySelectorAll('.justification-area').forEach(function(textarea) {
                        textarea.style.height = 'auto';
                        let newHeight = Math.max(textarea.scrollHeight, 100);
                        textarea.style.height = newHeight + 'px';
                    });
                }

                // تنفيذ التعديل عند تحميل الصفحة وعند تغيير النص
                window.addEventListener('load', adjustTextareaHeight);

                document.querySelectorAll('.justification-area').forEach(function(textarea) {
                    textarea.addEventListener('input', adjustTextareaHeight);
                    textarea.addEventListener('focus', adjustTextareaHeight);
                });

                // تأكيد إضافي بعد التحميل
                setTimeout(adjustTextareaHeight, 500);
            } else {
                console.error('لم يتم العثور على النموذج!');
            }

            // التحقق من تحميل مكتبة SweetAlert2
            if (typeof Swal === 'undefined') {
                console.error('مكتبة SweetAlert2 غير محملة!');
            }

            // عرض رسائل الجلسة عند وجودها
            @if(session('success'))
                Swal.fire({
                    title: 'تم بنجاح!',
                    text: "{{ session('success') }}",
                    icon: 'success',
                    confirmButtonText: 'حسناً'
                });
            @endif

            @if(session('error'))
                Swal.fire({
                    title: 'خطأ!',
                    text: "{{ session('error') }}",
                    icon: 'error',
                    confirmButtonText: 'حسناً'
                });
            @endif
        });
    </script>
@endsection
