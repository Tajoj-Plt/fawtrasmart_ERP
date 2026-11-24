@extends('client::master')

@section('title')
    تعديل التبرير
@stop

{{-- client::master لا يملك @yield('css') — @push('styles') هو ما يقرؤه فعلاً (master.blade.php:12) --}}
@push('styles')
    <style>
        /* تحسين العرض بشكل عام */
        .info-icon {
            font-size: 1.5rem;
            margin-right: 15px;
        }

        /* تحسين مظهر حقول التبرير في جميع أحجام الشاشة */
        .justification-area {
            min-height: 150px;
            width: 100%;
            padding: 12px;
        }

        /* تعديلات للعرض المتجاوب */
        @media (max-width: 767.98px) {
            .justification-area {
                min-height: 200px;
            }
        }

        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 10px;
        }

        #charCount {
            margin-top: 8px;
            display: block;
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
                    <h2 class="content-header-title float-left mb-0">تعديل التبرير</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{route('dashboard_sales.index')}}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('employee.visit-justifications.index') }}">تبريراتي</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('employee.visit-justifications.show', $justification->id) }}">تفاصيل التبرير</a></li>
                            <li class="breadcrumb-item active">تعديل التبرير</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        <!-- بطاقة العنوان -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-edit me-1"></i>
                        تعديل تبرير الزيارة
                    </h4>
                </div>
            </div>
        </div>

        <!-- نموذج تعديل التبرير -->
        <div class="card">
            <div class="card-body">
                <form action="{{ route('employee.visit-justifications.update', $justification->id) }}" method="POST" id="justification-form">
                    @csrf
                    @method('PUT')

                    <!-- معلومات العميل والزيارة (على شكل بطاقات) -->
                    <div class="row">
                        <!-- معلومات العميل -->
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="fas fa-building text-primary me-1"></i>
                                        معلومات العميل
                                    </h5>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="fw-bold" style="width: 40%;">
                                                <i class="fas fa-user me-1"></i>اسم العميل:
                                            </td>
                                            <td>{{ $justification->client->trade_name ?? 'غير محدد' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">
                                                <i class="fas fa-hashtag me-1"></i>كود العميل:
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">{{ $justification->client->code ?? '---' }}</span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- معلومات الزيارة -->
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="fas fa-calendar-check text-info me-1"></i>
                                        معلومات الزيارة
                                    </h5>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="fw-bold" style="width: 40%;">
                                                <i class="fas fa-calendar-day me-1"></i>اليوم:
                                            </td>
                                            <td>
                                                <span class="badge bg-light-primary">{{ $justification->day_of_week }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">
                                                <i class="fas fa-calendar-week me-1"></i>التاريخ:
                                            </td>
                                            <td>{{ $justification->year }} - الأسبوع {{ $justification->week_number }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- حقل التبرير -->
                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="justification" class="form-label fw-bold mb-2">
                                    <i class="fas fa-comment-alt text-warning me-1"></i>
                                    التبرير <span class="text-danger">*</span>
                                </label>
                                <textarea
                                    name="justification"
                                    id="justification"
                                    class="form-control justification-area @error('justification') is-invalid @enderror"
                                    placeholder="يرجى تقديم تبرير واضح ومفصل لعدم إتمام الزيارة..."
                                    required>{{ old('justification', $justification->justification) }}</textarea>
                                @error('justification')
                                    <div class="invalid-feedback">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        {{ $message }}
                                    </div>
                                @enderror

                                <!-- عداد الأحرف -->
                                <small class="form-text text-muted" id="charCount"></small>

                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    يرجى كتابة تبرير واضح ومفصل يوضح سبب عدم إتمام الزيارة.
                                    أمثلة: "العميل غير موجود في موقعه"، "العميل رفض الزيارة"، "المحل مغلق لظروف طارئة"، إلخ.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- نصائح كتابة التبرير -->
                    <div class="alert alert-info d-flex align-items-start mt-4">
                        <div class="info-icon">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div>
                            <h5 class="alert-heading mb-1">
                                <i class="fas fa-info-circle me-1"></i>نصائح لكتابة تبرير جيد
                            </h5>
                            <ul class="mb-0">
                                <li>اكتب تبريراً واضحاً ومحدداً</li>
                                <li>اذكر السبب الحقيقي لعدم إتمام الزيارة</li>
                                <li>تجنب العبارات المبهمة أو العامة</li>
                                <li>كن صادقاً ودقيقاً في وصف الموقف</li>
                            </ul>
                        </div>
                    </div>

                    <!-- أزرار الإجراءات -->
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="{{ route('employee.visit-justifications.show', $justification->id) }}" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> إلغاء
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> تحديث التبرير
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <!-- إضافة مكتبة SweetAlert2 قبل كود JavaScript الخاص بنا -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // التحقق من النموذج
            const form = document.getElementById('justification-form');
            const justificationInput = document.getElementById('justification');
            const charCountElement = document.getElementById('charCount');

            // تحديث عداد الأحرف
            function updateCharCount() {
                const length = justificationInput.value.length;
                charCountElement.innerHTML = `<i class="fas fa-keyboard me-1"></i>عدد الأحرف: ${length}`;
            }

            // تحديث عداد الأحرف عند تحميل الصفحة
            updateCharCount();

            // تحديث عداد الأحرف عند الكتابة
            justificationInput.addEventListener('input', function() {
                updateCharCount();

                // إزالة صنف is-invalid عند الكتابة
                if (this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                }

                // تعديل ارتفاع مساحة النص
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // التحقق عند تقديم النموذج
            if (form) {
                form.addEventListener('submit', function(e) {
                    const justificationText = justificationInput.value.trim();

                    if (justificationText === '') {
                        e.preventDefault();
                        justificationInput.classList.add('is-invalid');

                        Swal.fire({
                            title: 'خطأ!',
                            text: 'يرجى كتابة التبرير قبل الحفظ',
                            icon: 'error',
                            confirmButtonText: 'حسناً'
                        });

                        return false;
                    }

                    if (justificationText.length < 10) {
                        e.preventDefault();
                        justificationInput.classList.add('is-invalid');

                        Swal.fire({
                            title: 'خطأ!',
                            text: 'التبرير قصير جداً. يرجى كتابة تبرير أكثر تفصيلاً (10 أحرف على الأقل)',
                            icon: 'error',
                            confirmButtonText: 'حسناً'
                        });

                        return false;
                    }

                    // تأكيد الحفظ
                    e.preventDefault();
                    Swal.fire({
                        title: 'تأكيد',
                        text: 'هل أنت متأكد من تحديث التبرير؟',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'نعم، حفظ التعديلات',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
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