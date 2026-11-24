@extends('layouts.master')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white rounded-top-3">
                    <h4 class="mb-0">
                        <i class="fas fa-cloud-upload-alt me-2"></i>
                        استيراد بيانات العملاء
                    </h4>
                </div>

                <div class="card-body p-4">
                    <!-- قسم التعليمات -->
                    <div class="alert alert-info rounded-3 mb-4" role="alert">
                        <h5 class="alert-heading">
                            <i class="fas fa-info-circle me-2"></i>
                            تعليمات الاستيراد
                        </h5>
                        <ul class="mb-0">
                            <li>ابدأ بتحميل <strong>نموذج Excel</strong> الفارغ</li>
                            <li>أملأ البيانات حسب الأعمدة المحددة</li>
                            <li>تأكد من صحة البيانات قبل الاستيراد</li>
                            <li>يدعم ملفات Excel (.xlsx, .xls) و CSV</li>
                            <li>الحد الأقصى: 5000 صف في الملف الواحد</li>
                        </ul>
                    </div>

                    <!-- قسم تحميل النموذج -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-dashed h-100">
                                <div class="card-body text-center py-5">
                                    <div class="mb-3">
                                        <i class="fas fa-download fa-3x text-info"></i>
                                    </div>
                                    <h5>تحميل نموذج Excel</h5>
                                    <p class="text-muted small mb-3">احصل على نموذج فارغ لملء البيانات</p>
                                    <a href="{{ route('clients.import.template') }}" class="btn btn-info btn-sm">
                                        <i class="fas fa-download me-1"></i>
                                        تحميل النموذج
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border-dashed h-100">
                                <div class="card-body text-center py-5">
                                    <div class="mb-3">
                                        <i class="fas fa-file-import fa-3x text-success"></i>
                                    </div>
                                    <h5>استيراد ملف</h5>
                                    <p class="text-muted small mb-3">رفع ملف Excel يحتوي على بيانات العملاء</p>
                                    <button type="button" class="btn btn-success btn-sm" id="uploadExcelBtn">
                                        <i class="fas fa-upload me-1"></i>
                                        اختر ملف
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- حقول البيانات المتوقعة -->
                    <div class="alert alert-secondary rounded-3 mb-4">
                        <h6 class="mb-3">
                            <i class="fas fa-columns me-2"></i>
                            الأعمدة المدعومة:
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="small mb-0">
                                    <li><strong>كود العميل:</strong> رقم فريد للعميل</li>
                                    <li><strong>الاسم التجاري:</strong> اسم الشركة/المتجر</li>
                                    <li><strong>اسم العميل:</strong> الاسم الأول</li>
                                    <li><strong>اسم العائلة:</strong> اسم العائلة</li>
                                    <li><strong>رقم الهاتف:</strong> رقم الهاتف</li>
                                    <li><strong>رقم الجوال:</strong> رقم الجوال</li>
                                    <li><strong>البريد الإلكتروني:</strong> البريد الإلكتروني</li>
                                    <li><strong>التصنيف:</strong> تصنيف العميل</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="small mb-0">
                                    <li><strong>الفرع:</strong> الفرع المسؤول</li>
                                    <li><strong>المنطقة:</strong> المنطقة الجغرافية</li>
                                    <li><strong>الحي:</strong> الحي السكني</li>
                                    <li><strong>العنوان:</strong> العنوان الكامل</li>
                                    <li><strong>الرصيد الافتتاحي:</strong> الرصيد الأولي</li>
                                    <li><strong>حد الائتمان:</strong> حد الائتمان المسموح</li>
                                    <li><strong>مدة الائتمان:</strong> عدد الأيام</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- شريط التقدم -->
                    <div id="progressContainer" class="mb-4" style="display: none;">
                        <div class="progress" style="height: 25px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                role="progressbar" style="width: 0%">
                                0%
                            </div>
                        </div>
                    </div>

                    <!-- نتائج الاستيراد -->
                    <div id="resultsContainer" class="mb-4" style="display: none;">
                        <div id="resultsMessage" class="alert alert-success rounded-3"></div>
                    </div>

                    <!-- أزرار العمل -->
                    <div class="d-flex gap-2 mt-5">
                        <a href="{{ route('clients.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            رجوع للعملاء
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Input File المخفي -->
<input type="file" id="excelInput" accept=".xlsx,.xls,.csv" style="display: none;">

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const uploadBtn = document.getElementById('uploadExcelBtn');
        const excelInput = document.getElementById('excelInput');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const resultsContainer = document.getElementById('resultsContainer');
        const resultsMessage = document.getElementById('resultsMessage');

        // فتح نافذة اختيار الملف
        uploadBtn.addEventListener('click', function() {
            excelInput.click();
        });

        // معالجة اختيار الملف
        excelInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // التحقق من حجم الملف (حد أقصى 10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('❌ حجم الملف كبير جداً. الحد الأقصى 10MB');
                return;
            }

            uploadBtn.disabled = true;
            progressContainer.style.display = 'block';
            resultsContainer.style.display = 'none';

            try {
                const formData = new FormData();
                formData.append('file', file);

                // محاكاة شريط التقدم
                let progress = 0;
                const progressInterval = setInterval(() => {
                    if (progress < 90) {
                        progress += Math.random() * 30;
                        if (progress > 90) progress = 90;
                        updateProgressBar(progress);
                    }
                }, 200);

                const response = await fetch("{{ route('clients.import.excel') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: formData
                });

                clearInterval(progressInterval);
                updateProgressBar(100);

                const result = await response.json();

                setTimeout(() => {
                    progressContainer.style.display = 'none';
                    resultsContainer.style.display = 'block';

                    if (result.success) {
                        resultsMessage.className = 'alert alert-success rounded-3';
                        resultsMessage.innerHTML = `
                            <h4 class="alert-heading">
                                <i class="fas fa-check-circle me-2"></i>
                                تم بنجاح!
                            </h4>
                            <p>${result.message}</p>
                            <hr>
                            <p class="mb-0">سيتم إعادة تحميل الصفحة خلال ثانية...</p>
                        `;

                        setTimeout(() => {
                            location.href = "{{ route('clients.index') }}";
                        }, 2000);
                    } else {
                        resultsMessage.className = 'alert alert-danger rounded-3';
                        resultsMessage.innerHTML = `
                            <h4 class="alert-heading">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                خطأ في الاستيراد
                            </h4>
                            <p>${result.message}</p>
                        `;
                    }
                }, 500);

            } catch (err) {
                clearInterval(progressInterval);
                progressContainer.style.display = 'none';
                resultsContainer.style.display = 'block';

                resultsMessage.className = 'alert alert-danger rounded-3';
                resultsMessage.innerHTML = `
                    <h4 class="alert-heading">
                        <i class="fas fa-times-circle me-2"></i>
                        خطأ في الاتصال
                    </h4>
                    <p>حدث خطأ أثناء الاستيراد: ${err.message}</p>
                `;

                console.error('❌ خطأ:', err);
            } finally {
                uploadBtn.disabled = false;
                excelInput.value = '';
            }
        });

        function updateProgressBar(value) {
            progressBar.style.width = value + '%';
            progressBar.textContent = Math.round(value) + '%';
        }
    });
</script>

<style>
    .border-dashed {
        border: 2px dashed #dee2e6 !important;
        transition: all 0.3s ease;
    }

    .border-dashed:hover {
        border-color: #0d6efd !important;
        background-color: #f8f9fa;
    }

    .card-header {
        border-radius: 1rem 1rem 0 0 !important;
    }

    .progress {
        border-radius: 0.5rem;
        background-color: #e9ecef;
    }

    .progress-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
    }
</style>
@endsection
