@extends('client::reservations.master')
@section('title', 'إضافة ملاحظة أو مرفق')

@section('content')

    <div class="rsv-scope">
        @include('client::reservations.components.page-head', [
            'title' => 'إضافة ملاحظة أو مرفق',
            'icon' => 'file-plus',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'إضافة ملاحظة' => null,
            ],
        ])

        <form onsubmit="return validateAttachments()" id="clientForm" action="{{ route('clients.addnotes') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="current_latitude" id="current_latitude">
            <input type="hidden" name="current_longitude" id="current_longitude">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <label>الحقول التي عليها علامة <span style="color: red">*</span> الزامية</label>
                        </div>
                        <div>
                            {{-- «إلغاء» يعود من حيث جاء المستخدم.

                                 كان يذهب دائماً إلى فهرس المواعيد: من فتح
                                 الشاشةَ من صفحة العميل أو من خطّ السير أو من
                                 مساحة الزيارة يجد نفسه في شاشةٍ لم يكن فيها،
                                 فيفقد سياقَه كلَّه لأنّه تراجَع.

                                 والحارس ضروري: بعد فشل التحقّق يعود الطلبُ إلى
                                 هذه الصفحة نفسها، فتصير هي «السابقة» — ولولاه
                                 لأصبح الزرّ يعيد تحميل نفس الشاشة. --}}
                            @php
                                $noteCancelUrl = url()->previous();
                                if (! $noteCancelUrl || rtrim($noteCancelUrl, '/') === rtrim(url()->current(), '/')) {
                                    $noteCancelUrl = route('clients.show', $id);
                                }
                            @endphp
                            <a href="{{ $noteCancelUrl }}" class="btn btn-outline-danger">
                                <i class="fa fa-ban"></i>الغاء
                            </a>
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fa fa-save"></i>حفظ
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Section -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <!-- Hidden Client ID -->
                    <input type="hidden" name="client_id" value="{{ $id }}">

                    <div class="row g-3 mb-3">
                        <!-- موظف العميل -->
                        <div class="form-group col-md-4">
                            <label for="client_employee_id" class="form-label">موظف العميل <span class="text-danger">*</span></label>
                            <select class="form-control" id="client_employee_id" name="client_employee_id" required>
                                <option value="">اختر موظف العميل</option>
                                @php
                                    $clientEmployeeNotes = \App\Models\ClientEmployeeNote::where('client_id', $id)
                                        ->distinct('employee_id')
                                        ->get(['id', 'employee_id', 'employee_name', 'employee_position', 'employee_phone']);
                                @endphp
                                @forelse($clientEmployeeNotes as $note)
                                    <option value="{{ $note->id }}">
                                        {{ $note->employee_name }}
                                        @if($note->employee_position)
                                            - {{ $note->employee_position }}
                                        @endif
                                    </option>
                                @empty
                                    <option value="" disabled>لا توجد موظفين مسندين لهذا العميل</option>
                                @endforelse
                            </select>
                        </div>

                        <!-- عدد العهدة -->
                        <div class="form-group col-md-4">
                            <label for="deposit_count" class="form-label">عدد العهدة الموجودة <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="deposit_count" name="deposit_count" min="0" required>
                        </div>

                        <!-- عدد استندات المنافسين -->
                        <div class="form-group col-md-4">
                            <label for="competitor_documents" class="form-label">عدد استندات المنافسين <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="competitor_documents" name="competitor_documents" min="0" required>
                        </div>

                        <!-- بداية الدوام -->
                        <div class="form-group col-md-6 mb-2">
                            <label for="shift_start" class="form-label d-block">بداية دوام المسؤول <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="shift_start" name="shift_start" required onchange="document.getElementById('quick_shift').value = '';">
                        </div>

                        <!-- نهاية الدوام -->
                        <div class="form-group col-md-6 mb-2">
                            <label for="shift_end" class="form-label d-block">نهاية دوام المسؤول <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="shift_end" name="shift_end" required onchange="document.getElementById('quick_shift').value = '';">
                        </div>
                        
                        <!-- خيارات سريعة للدوام -->
                        <div class="form-group col-md-12 mb-3">
                            <label for="quick_shift" class="form-label text-muted small fw-bold"><i class="fas fa-bolt text-warning me-1"></i> تحديد سريع لأوقات الدوام:</label>
                            <select id="quick_shift" class="form-control" onchange="handleQuickShiftSelect(this.value)">
                                <option value="">-- اختر فترة الدوام السريعة --</option>
                                <option value="00:00|23:59">24 ساعة (تفرغ كامل)</option>
                                <optgroup label="فترات 12 ساعة (صباحاً إلى مساءً)">
                                    @for($i = 1; $i <= 12; $i++)
                                        @php
                                            $startH = $i === 12 ? 0 : $i;
                                            $endH = $i === 12 ? 12 : $i + 12;
                                            $startFmt = str_pad($startH, 2, '0', STR_PAD_LEFT) . ':00';
                                            $endFmt = str_pad($endH, 2, '0', STR_PAD_LEFT) . ':00';
                                            $amLabel = $i === 12 ? '12 صباحاً (منتصف الليل)' : $i . ' صباحاً';
                                            $pmLabel = $i === 12 ? '12 مساءً (الظهر)' : $i . ' مساءً';
                                        @endphp
                                        <option value="{{ $startFmt }}|{{ $endFmt }}">من {{ $amLabel }} إلى {{ $pmLabel }}</option>
                                    @endfor
                                </optgroup>
                                <optgroup label="فترات 12 ساعة (مساءً إلى صباحاً)">
                                    @for($i = 1; $i <= 12; $i++)
                                        @php
                                            $startH = $i === 12 ? 12 : $i + 12;
                                            $endH = $i === 12 ? 0 : $i;
                                            $startFmt = str_pad($startH, 2, '0', STR_PAD_LEFT) . ':00';
                                            $endFmt = str_pad($endH, 2, '0', STR_PAD_LEFT) . ':00';
                                            $pmLabel = $i === 12 ? '12 مساءً (الظهر)' : $i . ' مساءً';
                                            $amLabel = $i === 12 ? '12 صباحاً (منتصف الليل)' : $i . ' صباحاً';
                                        @endphp
                                        <option value="{{ $startFmt }}|{{ $endFmt }}">من {{ $pmLabel }} إلى {{ $amLabel }}</option>
                                    @endfor
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="notes" class="form-label">ملاحظة</label>
                        <textarea class="form-control" name="description" rows="4" required></textarea>
                    </div>

                    <!-- Enhanced Attachments Section -->
                    <div class="col-md-12 col-12 mb-3">
                        <div class="form-group">
                            <label for="attachments" class="form-label">المرفقات (التقاط من الكاميرا) <span class="text-danger">*</span></label>
                            <input type="file" name="attachments[]" multiple id="attachments" class="form-control d-none"
                                accept="image/*"
                                capture="environment"
                                onchange="handleFileSelection(event)" required>

                            <!-- Upload Area -->
                            <div class="upload-area border rounded p-4 text-center position-relative bg-light"
                                onclick="document.getElementById('attachments').click()" style="cursor: pointer;">
                                <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                    <i class="fas fa-camera fa-2x text-primary"></i>
                                    <p class="mb-0 text-primary fw-bold">اضغط هنا لفتح الكاميرا</p>
                                    <small class="text-muted">يتم ضغط الصور تلقائياً لتوفير مساحة التخزين</small>
                                    <small class="text-info">التقط صورة مباشرة من الكاميرا</small>
                                </div>
                                <div class="position-absolute end-0 top-50 translate-middle-y me-3">
                                    <i class="fas fa-camera-retro fs-3 text-secondary"></i>
                                </div>
                            </div>

                            <!-- Compression Settings -->
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="imageQuality" class="form-label small">جودة ضغط الصور</label>
                                    <select id="imageQuality" class="form-select form-select-sm">
                                        <option value="0.8">عالية جداً (80%)</option>
                                        <option value="0.6" selected>عالية (60%)</option>
                                        <option value="0.4">متوسطة (40%)</option>
                                        <option value="0.2">منخفضة (20%)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="maxImageSize" class="form-label small">الحد الأقصى لحجم الصورة</label>
                                    <select id="maxImageSize" class="form-select form-select-sm">
                                        <option value="1920">1920px (عالية الدقة)</option>
                                        <option value="1280" selected>1280px (دقة متوسطة)</option>
                                        <option value="800">800px (دقة منخفضة)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div id="compressionProgress" class="mt-3 d-none">
                                <label class="form-label small">تقدم ضغط الملفات:</label>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                         role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted mt-1 d-block">يتم ضغط الملفات...</small>
                            </div>

                            <!-- File Preview -->
                            <div id="selected-files" class="mt-3"></div>

                            <!-- Compression Summary -->
                            <div id="compressionSummary" class="mt-3 d-none">
                                <div class="alert alert-success border-0">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <small class="text-muted d-block">الحجم الأصلي</small>
                                            <span class="fw-bold" id="originalSize">0 MB</span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">بعد الضغط</small>
                                            <span class="fw-bold text-success" id="compressedSize">0 MB</span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">نسبة التوفير</small>
                                            <span class="fw-bold text-primary" id="savingsPercentage">0%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="share_with_work" id="shareWithWork">
                        <label class="form-check-label" for="shareWithWork">مشاركة مع العمل</label>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @parent
    <style>
        .upload-area {
            border: 2px dashed #007bff;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #0056b3;
            background-color: #f1f3f4 !important;
        }
        .file-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 10px;
            padding: 12px;
            background: #f8f9fa;
        }
        .image-preview {
            max-width: 60px;
            max-height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
    </style>

    <script>
        // كم صورةً يجب إرفاقها — إعدادات وحدات العملاء ← الزيارات
        var NOTE_MIN_PHOTOS = @json(\App\Helpers\ClientSettingsHelper::visitWorkspaceSectionVisible('note_photo') ? \App\Helpers\ClientSettingsHelper::noteMinPhotos() : 0);

        let rawFiles = [];
        let processedFiles = [];
        let totalOriginalSize = 0;
        let totalCompressedSize = 0;
        let locationObtained = false; // متغير لتتبع حالة الموقع

        $(document).ready(function() {
            // طلب الموقع فوراً عند تحميل الصفحة
            getLocation();

            // جلب موظفي العميل عند تحميل الصفحة
            loadClientEmployees();

            // التحقق من تغيير موظف العميل عند الحاجة
            checkClientEmployeeChange();
        });

        // دالة لجلب موظفي العميل
        function loadClientEmployees() {
            const clientId = document.querySelector('input[name="client_id"]').value;
            const select = document.getElementById('client_employee_id');

            console.log('جاري جلب موظفي العميل للعميل رقم:', clientId);

            if (!clientId) {
                console.warn('لم يتم تحديد العميل');
                return;
            }

            fetch(`{{ route('clients.client_employees', '') }}/${clientId}`, {
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                console.log('حالة الاستجابة:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('بيانات الموظفين المستقبلة:', data);

                if (data.employees && data.employees.length > 0) {
                    // مسح الخيارات الحالية
                    const options = select.querySelectorAll('option');
                    options.forEach((option, index) => {
                        if (index > 0) option.remove(); // احذف كل شيء ما عدا الخيار الأول
                    });

                    // أضف الموظفين الجدد
                    data.employees.forEach(emp => {
                        const option = document.createElement('option');
                        option.value = emp.id;
                        option.textContent = emp.name;
                        select.appendChild(option);
                        console.log('تم إضافة موظف:', emp.name);
                    });
                } else {
                    console.warn('لا توجد موظفين مسندين لهذا العميل');
                    // أضف خيار تنبيه
                    const option = select.querySelector('option:nth-child(2)');
                    if (option) {
                        option.textContent = 'لا توجد موظفين مسندين لهذا العميل';
                    }
                }
            })
            .catch(error => {
                console.error('خطأ في جلب الموظفين:', error);
                console.error('تفاصيل الخطأ:', error.message);
            });
        }

        // دالة للتحقق من تغيير موظف العميل
        function checkClientEmployeeChange() {
            const clientId = document.querySelector('input[name="client_id"]').value;
            const currentSelect = document.getElementById('client_employee_id');

            if (!clientId) return;

            // طلب آخر ملاحظة للعميل
            fetch(`{{ route('clients.last_note', '') }}/${clientId}`, {
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.lastNote && data.lastNote.client_employee_id) {
                    const lastEmployeeId = data.lastNote.client_employee_id;
                    const currentSelection = currentSelect.value;

                    // إذا كانت هناك ملاحظة سابقة وموظف العميل مختلف
                    if (lastEmployeeId != currentSelection) {
                        showEmployeeChangeAlert(data.lastNote.employee_name, data.currentEmployee);
                    }
                }
            })
            .catch(error => console.log('خطأ في التحقق من الموظف:', error));
        }

        // دالة لإظهار تنبيه تغيير الموظف
        function showEmployeeChangeAlert(oldEmployeeName, newEmployeeName) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-warning alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="fa fa-exclamation-triangle me-2"></i>
                <strong>تنبيه!</strong> تم تغيير موظف العميل من <strong>${oldEmployeeName}</strong> إلى <strong>${newEmployeeName}</strong>.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            const form = document.getElementById('clientForm');
            form.insertBefore(alertDiv, form.firstChild);
        }

        // دالة للحصول على الموقع
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        document.getElementById('current_latitude').value = position.coords.latitude;
                        document.getElementById('current_longitude').value = position.coords.longitude;
                        locationObtained = true;
                        console.log('تم الحصول على الموقع بنجاح');
                        console.log('Latitude:', position.coords.latitude);
                        console.log('Longitude:', position.coords.longitude);
                    },
                    function(error) {
                        locationObtained = false;
                        let errorMsg = '';
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMsg = "تم رفض الإذن للوصول للموقع. يرجى السماح بالوصول للموقع من إعدادات المتصفح.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMsg = "معلومات الموقع غير متوفرة.";
                                break;
                            case error.TIMEOUT:
                                errorMsg = "انتهت مهلة طلب الموقع.";
                                break;
                            default:
                                errorMsg = "حدث خطأ غير معروف في الحصول على الموقع.";
                        }
                        alert(errorMsg);
                        console.error('Location error:', error);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                alert("المتصفح لا يدعم خدمة تحديد الموقع!");
            }
        }

        // Handle file selection with compression
        async function handleFileSelection(event, isRecompression = false) {
            const files = Array.from(event.target.files);
            if (files.length === 0) return;

            showProgress(true);
            
            if (isRecompression) {
                processedFiles = [];
                totalOriginalSize = 0;
                totalCompressedSize = 0;
            } else {
                rawFiles.push(...files);
            }

            const filesToProcess = isRecompression ? rawFiles : files;

            for (let i = 0; i < filesToProcess.length; i++) {
                const file = filesToProcess[i];
                updateProgress((i / filesToProcess.length) * 100, `معالجة ${file.name}...`);

                const processedFile = await processFile(file);
                if (processedFile) {
                    processedFiles.push(processedFile);
                }
            }

            updateProgress(100, 'تم الانتهاء من معالجة جميع الملفات');
            setTimeout(() => {
                showProgress(false);
                previewSelectedFiles();
                updateCompressionSummary();
            }, 500);
        }

        // Process individual file
        async function processFile(file) {
            totalOriginalSize += file.size;

            if (file.type.startsWith('image/')) {
                return await compressImage(file);
            } else {
                // رفض أي ملف غير صورة
                alert(`يُسمح فقط بالصور من الكاميرا`);
                return null;
            }
        }

        // Compress image function
        function compressImage(file) {
            return new Promise((resolve) => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const img = new Image();

                img.onload = function() {
                    const quality = parseFloat(document.getElementById('imageQuality').value);
                    const maxSize = parseInt(document.getElementById('maxImageSize').value);

                    // Calculate new dimensions
                    let { width, height } = img;
                    const ratio = Math.min(maxSize / width, maxSize / height);

                    if (ratio < 1) {
                        width *= ratio;
                        height *= ratio;
                    }

                    canvas.width = width;
                    canvas.height = height;

                    // Draw and compress
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob((blob) => {
                        totalCompressedSize += blob.size;

                        // Create preview
                        const previewUrl = URL.createObjectURL(blob);

                        // Create new file from blob
                        const compressedFile = new File([blob], file.name, {
                            type: file.type,
                            lastModified: Date.now()
                        });

                        resolve({
                            file: compressedFile,
                            originalSize: file.size,
                            compressedSize: blob.size,
                            type: 'image',
                            preview: previewUrl
                        });
                    }, file.type, quality);
                };

                img.src = URL.createObjectURL(file);
            });
        }

        // Updated preview function
        function previewSelectedFiles() {
            const preview = document.getElementById('selected-files');
            preview.innerHTML = '';

            if (processedFiles.length === 0) return;

            processedFiles.forEach((item, index) => {
                const fileDiv = document.createElement('div');
                fileDiv.className = 'file-item';

                const savings = item.originalSize > 0 ?
                    ((item.originalSize - item.compressedSize) / item.originalSize * 100).toFixed(1) : 0;
                const savingsClass = savings > 0 ? 'text-success' : 'text-muted';

                fileDiv.innerHTML = `
                    <div class="row align-items-center">
                        <div class="col-2">
                            ${item.preview ?
                                `<img src="${item.preview}" class="image-preview" alt="معاينة">` :
                                `<i class="fas fa-image fa-2x text-secondary"></i>`
                            }
                        </div>
                        <div class="col-7">
                            <h6 class="mb-1 text-truncate">${item.file.name}</h6>
                            <small class="text-muted">صورة</small>
                            <div class="mt-1">
                                <span class="badge bg-primary">${formatFileSize(item.compressedSize)}</span>
                                ${savings > 0 ? `<span class="badge bg-success ms-1">-${savings}%</span>` : ''}
                            </div>
                        </div>
                        <div class="col-2">
                            <small class="text-muted d-block">أصلي: ${formatFileSize(item.originalSize)}</small>
                            ${savings > 0 ? `<small class="${savingsClass}">توفير: ${formatFileSize(item.originalSize - item.compressedSize)}</small>` : ''}
                        </div>
                        <div class="col-1">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;

                preview.appendChild(fileDiv);
            });

            // Update the original file input with compressed files
            updateFileInput();
        }

        // Update file input with compressed files
        function updateFileInput() {
            const dt = new DataTransfer();
            processedFiles.forEach(item => {
                dt.items.add(item.file);
            });
            document.getElementById('attachments').files = dt.files;
        }

        // Update compression summary
        function updateCompressionSummary() {
            if (processedFiles.length === 0) {
                document.getElementById('compressionSummary').classList.add('d-none');
                return;
            }

            const summary = document.getElementById('compressionSummary');
            const savings = totalOriginalSize > 0 ?
                ((totalOriginalSize - totalCompressedSize) / totalOriginalSize * 100).toFixed(1) : 0;

            document.getElementById('originalSize').textContent = formatFileSize(totalOriginalSize);
            document.getElementById('compressedSize').textContent = formatFileSize(totalCompressedSize);
            document.getElementById('savingsPercentage').textContent = savings + '%';

            summary.classList.remove('d-none');
        }

        // Utility functions
        function showProgress(show) {
            const progress = document.getElementById('compressionProgress');
            if (show) {
                progress.classList.remove('d-none');
            } else {
                progress.classList.add('d-none');
            }
        }

        function updateProgress(percentage, message) {
            const progressBar = document.querySelector('.progress-bar');
            const progressText = document.querySelector('#compressionProgress small');

            progressBar.style.width = percentage + '%';
            progressText.textContent = message;
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function removeFile(index) {
            const item = processedFiles[index];
            totalOriginalSize -= item.originalSize;
            totalCompressedSize -= item.compressedSize;

            processedFiles.splice(index, 1);
            rawFiles.splice(index, 1);
            previewSelectedFiles();
            updateCompressionSummary();
        }

        // Enhanced validation function
        function validateAttachments() {
            // التحقق من الموقع أولاً
            if (!locationObtained) {
                alert('لم يتم الحصول على موقعك! يرجى السماح بالوصول للموقع وإعادة تحميل الصفحة.');
                return false;
            }

            const latitude = document.getElementById('current_latitude').value;
            const longitude = document.getElementById('current_longitude').value;

            if (!latitude || !longitude) {
                alert('لم يتم تسجيل موقعك! يرجى السماح بالوصول للموقع.');
                getLocation(); // محاولة الحصول على الموقع مرة أخرى
                return false;
            }

            const shiftStart = document.getElementById('shift_start').value;
            const shiftEnd = document.getElementById('shift_end').value;

            if (!shiftStart || !shiftEnd) {
                alert('يرجى تحديد وقت بداية ونهاية دوام المسؤول');
                return false;
            }

            /* العدد من إعدادات وحدات العملاء لا `=== 0` مثبَّتاً: الخادم
               (`ClientNoteController::addnotes()`) يطبّق `min:` نفسه، فبقاء
               الشرط على واحدة يعني نموذجاً يُرسَل ثمّ يُرفض. */
            if (NOTE_MIN_PHOTOS > 0 && processedFiles.length < NOTE_MIN_PHOTOS) {
                alert('يرجى التقاط ' + NOTE_MIN_PHOTOS + ' صورة على الأقل قبل إرسال النموذج.');
                return false;
            }

            // Check file sizes after compression
            const oversizedFiles = processedFiles.filter(item => {
                if (item.type === 'image' && item.compressedSize > 5 * 1024 * 1024) return true; // 5MB for images
                return false;
            });

            if (oversizedFiles.length > 0) {
                alert('بعض الصور كبيرة جداً حتى بعد الضغط. يرجى التقاط صور أصغر.');
                return false;
            }

            return true;
        }

        // دالة مساعدة لتحديث تسمية الوقت (صباحاً/مساءً)
        function updateTimeLabel(input) {
            const time = input.value;
            if (!time) {
                input.nextElementSibling.textContent = '';
                return;
            }
            const hours = parseInt(time.split(':')[0]);
            const label = hours >= 12 ? 'مساءً' : 'صباحاً';
            
            let labelElement = input.parentElement.querySelector('.time-label-helper');
            if (!labelElement) {
                labelElement = document.createElement('span');
                labelElement.className = 'time-label-helper badge bg-light text-dark mt-1';
                input.parentElement.appendChild(labelElement);
            }
            labelElement.textContent = label;
        }

        // ضبط الأوقات بسرعة من القائمة
        function handleQuickShiftSelect(value) {
            if (!value) return;
            const [startTime, endTime] = value.split('|');
            const startInput = document.getElementById('shift_start');
            const endInput = document.getElementById('shift_end');
            
            startInput.value = startTime;
            endInput.value = endTime;
            
            updateTimeLabel(startInput);
            updateTimeLabel(endInput);
        }

        // إضافة مستمعي الأحداث لمدخلات الوقت
        document.getElementById('shift_start').addEventListener('change', function() { updateTimeLabel(this); });
        document.getElementById('shift_end').addEventListener('change', function() { updateTimeLabel(this); });

        // Auto-compression when settings change
        document.getElementById('imageQuality').addEventListener('change', function() {
            if (rawFiles.length > 0) {
                const event = new Event('change');
                Object.defineProperty(event, 'target', {
                    value: { files: rawFiles },
                    enumerable: true
                });
                handleFileSelection(event, true);
            }
        });

        document.getElementById('maxImageSize').addEventListener('change', function() {
            if (rawFiles.length > 0) {
                const event = new Event('change');
                Object.defineProperty(event, 'target', {
                    value: { files: rawFiles },
                    enumerable: true
                });
                handleFileSelection(event, true);
            }
        });
    </script>

@endsection
