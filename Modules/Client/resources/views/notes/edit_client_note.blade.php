@extends('client::master')
@section('title', 'تعديل ملاحظة')

@section('content')
    @include('client::partials.settings-theme')

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'تعديل ملاحظة',
            'icon' => 'edit-3',
            'sub' => 'العميل: ' . $client->trade_name,
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                $client->trade_name => route('clients.show', $client->id),
                'تعديل ملاحظة' => null,
            ],
        ])

        <form onsubmit="return validateForm()" id="editNoteForm" action="{{ route('clients.notes.update', $note->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

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
                            <h5 class="mb-1">
                                <i class="fa fa-edit me-2"></i>
                                تعديل ملاحظة للعميل: <strong>{{ $client->trade_name }}</strong>
                            </h5>
                            <label>الحقول التي عليها علامة <span style="color: red">*</span> الزامية</label>
                        </div>
                        <div>
                            <a href="{{ route('clients.show', $client->id) }}" class="btn btn-outline-danger">
                                <i class="fa fa-ban"></i>الغاء
                            </a>
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fa fa-save"></i>حفظ التعديلات
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Section -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <!-- معلومات الملاحظة الأصلية -->
                    <div class="alert alert-info mb-3">
                        <div class="row">
                            <div class="col-md-4">
                                <strong><i class="fa fa-user me-1"></i> أنشأها:</strong>
                                {{ $note->employee->name ?? 'غير معروف' }}
                            </div>
                            <div class="col-md-4">
                                <strong><i class="fa fa-calendar me-1"></i> تاريخ الإنشاء:</strong>
                                {{ $note->created_at->format('Y-m-d H:i') }}
                            </div>
                            <div class="col-md-4">
                                <strong><i class="fa fa-clock me-1"></i> آخر تحديث:</strong>
                                {{ $note->updated_at->format('Y-m-d H:i') }}
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Client ID -->
                    <input type="hidden" name="client_id" value="{{ $client->id }}">

                    <!-- Hidden Client ID -->
                    <input type="hidden" name="client_id" value="{{ $client->id }}">

                    <div class="row g-3 mb-3">
                        <!-- موظف العميل -->
                        <div class="form-group col-md-4">
                            <label for="client_employee_id" class="form-label">موظف العميل <span class="text-danger">*</span></label>
                            <select class="form-control" id="client_employee_id" name="client_employee_id" required>
                                <option value="">اختر موظف العميل</option>
                                @php
                                    $clientEmployeeNotes = \App\Models\ClientEmployeeNote::where('client_id', $client->id)
                                        ->distinct('employee_id')
                                        ->get(['id', 'employee_id', 'employee_name', 'employee_position', 'employee_phone']);
                                @endphp
                                @forelse($clientEmployeeNotes as $empNote)
                                    <option value="{{ $empNote->id }}" {{ old('client_employee_id', $note->client_employee_id) == $empNote->id ? 'selected' : '' }}>
                                        {{ $empNote->employee_name }}
                                        @if($empNote->employee_position)
                                            - {{ $empNote->employee_position }}
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
                            <input type="number" class="form-control" id="deposit_count" name="deposit_count"
                                   min="0" value="{{ old('deposit_count', $note->deposit_count) }}" required>
                        </div>

                        <!-- نوع الموقع -->
                        <div class="form-group col-md-4">
                            <label for="site_type" class="form-label">نوع الموقع <span class="text-danger">*</span></label>
                            <select class="form-control" id="site_type" name="site_type" required>
                                <option value="">اختر نوع الموقع</option>
                                <option value="independent_booth" {{ old('site_type', $note->site_type) == 'independent_booth' ? 'selected' : '' }}>بسطة مستقلة</option>
                                <option value="grocery" {{ old('site_type', $note->site_type) == 'grocery' ? 'selected' : '' }}>بقالة</option>
                                <option value="supplies" {{ old('site_type', $note->site_type) == 'supplies' ? 'selected' : '' }}>تموينات</option>
                                <option value="markets" {{ old('site_type', $note->site_type) == 'markets' ? 'selected' : '' }}>أسواق</option>
                                <option value="station" {{ old('site_type', $note->site_type) == 'station' ? 'selected' : '' }}>محطة</option>
                            </select>
                        </div>

                        <!-- عدد استندات المنافسين -->
                        <div class="form-group col-md-4">
                            <label for="competitor_documents" class="form-label">عدد استندات المنافسين <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="competitor_documents" name="competitor_documents"
                                   min="0" value="{{ old('competitor_documents', $note->competitor_documents) }}" required>
                        </div>

                        <!-- بداية الدوام -->
                        <div class="form-group col-md-4">
                            <label for="shift_start" class="form-label d-block">بداية دوام المسؤول <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="time" class="form-control" id="shift_start" name="shift_start"
                                       value="{{ old('shift_start', $note->shift_start) }}" required>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="setQuickShift('morning')">
                                    صباحي
                                </button>
                            </div>
                        </div>

                        <!-- نهاية الدوام -->
                        <div class="form-group col-md-4">
                            <label for="shift_end" class="form-label d-block">نهاية دوام المسؤول <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="time" class="form-control" id="shift_end" name="shift_end"
                                       value="{{ old('shift_end', $note->shift_end) }}" required>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickShift('evening')">
                                    مسائي
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="description" class="form-label">ملاحظة <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="description" id="description" rows="4" required>{{ old('description', $note->description) }}</textarea>
                    </div>

                    <!-- المرفقات الحالية -->
                    @php
                        $existingAttachments = json_decode($note->attachments, true) ?? [];
                    @endphp

                    @if(count($existingAttachments) > 0)
                    <div class="mb-3">
                        <label class="form-label">المرفقات الحالية</label>
                        <div class="row" id="existing-attachments">
                            @foreach($existingAttachments as $index => $attachment)
                            <div class="col-md-3 mb-3" id="attachment-{{ $index }}">
                                <div class="card">
                                    <img src="{{ asset('assets/uploads/notes/' . $attachment) }}"
                                         class="card-img-top" alt="مرفق" style="height: 150px; object-fit: cover;">
                                    <div class="card-body p-2 text-center">
                                        <button type="button" class="btn btn-danger btn-sm"
                                                onclick="deleteAttachment({{ $index }}, '{{ $attachment }}')">
                                            <i class="fa fa-trash"></i> حذف
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Enhanced Attachments Section -->
                    <div class="col-md-12 col-12 mb-3">
                        <div class="form-group">
                            <label for="attachments" class="form-label">إضافة مرفقات جديدة (التقاط من الكاميرا)</label>
                            <input type="file" name="attachments[]" multiple id="attachments" class="form-control d-none"
                                accept="image/*"
                                capture="environment"
                                onchange="handleFileSelection(event)">

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
                        <input class="form-check-input" type="checkbox" name="share_with_work" id="shareWithWork"
                               {{ $note->share_with_work ? 'checked' : '' }}>
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
        let processedFiles = [];
        let totalOriginalSize = 0;
        let totalCompressedSize = 0;
        let existingAttachmentsCount = {{ count($existingAttachments) }};

        // حذف مرفق موجود
        function deleteAttachment(index, filename) {
            if (!confirm('هل أنت متأكد من حذف هذا المرفق؟')) {
                return;
            }

            fetch('{{ route("clients.notes.deleteAttachment", $note->id) }}', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ attachment_index: index })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('attachment-' + index).remove();
                    existingAttachmentsCount--;
                    if (typeof toastr !== 'undefined') {
                        toastr.success(data.message);
                    } else {
                        alert(data.message);
                    }
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(data.message);
                    } else {
                        alert(data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء حذف المرفق');
            });
        }

        // Handle file selection with compression
        async function handleFileSelection(event) {
            const files = Array.from(event.target.files);
            if (files.length === 0) return;

            showProgress(true);
            processedFiles = [];
            totalOriginalSize = 0;
            totalCompressedSize = 0;

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                updateProgress((i / files.length) * 100, `معالجة ${file.name}...`);

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
            previewSelectedFiles();
            updateCompressionSummary();
        }

        // Enhanced validation function
        function validateForm() {
            const clientEmployeeId = document.getElementById('client_employee_id').value;
            const depositCount = document.getElementById('deposit_count').value;
            const siteType = document.getElementById('site_type').value;
            const competitorDocs = document.getElementById('competitor_documents').value;
            const shiftStart = document.getElementById('shift_start').value;
            const shiftEnd = document.getElementById('shift_end').value;
            const description = document.getElementById('description').value;

            if (!clientEmployeeId) {
                alert('يرجى اختيار موظف العميل');
                return false;
            }

            if (!depositCount || depositCount < 0) {
                alert('يرجى إدخال عدد العهدة');
                return false;
            }

            if (!siteType) {
                alert('يرجى اختيار نوع الموقع');
                return false;
            }

            if (!competitorDocs || competitorDocs < 0) {
                alert('يرجى إدخال عدد استندات المنافسين');
                return false;
            }

            if (!shiftStart || !shiftEnd) {
                alert('يرجى تحديد وقت بداية ونهاية دوام المسؤول');
                return false;
            }

            if (!description.trim()) {
                alert('يرجى إدخال الملاحظة');
                return false;
            }

            // التحقق من وجود مرفقات (حالية أو جديدة)
            if (existingAttachmentsCount === 0 && processedFiles.length === 0) {
                alert('يجب وجود صورة واحدة على الأقل للملاحظة');
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

        // Auto-compression when settings change
        document.getElementById('imageQuality').addEventListener('change', function() {
            if (processedFiles.length > 0) {
                // Get original file input
                const fileInput = document.getElementById('attachments');
                const originalFiles = Array.from(fileInput.files);

                // Re-trigger file selection to recompress with new settings
                const event = new Event('change');
                Object.defineProperty(event, 'target', {
                    value: { files: originalFiles },
                    enumerable: true
                });
                handleFileSelection(event);
            }
        });

        document.getElementById('maxImageSize').addEventListener('change', function() {
            if (processedFiles.length > 0) {
                // Get original file input
                const fileInput = document.getElementById('attachments');
                const originalFiles = Array.from(fileInput.files);

                // Re-trigger file selection to recompress with new settings
                const event = new Event('change');
                Object.defineProperty(event, 'target', {
                    value: { files: originalFiles },
                    enumerable: true
                });
                handleFileSelection(event);
            }
        });
        // دالة مساعدة لتحديث تسمية الوقت (صباحاً/مساءً)
        function updateTimeLabel(input) {
            const time = input.value;
            if (!time) return;
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

        // ضبط الأوقات بسرعة
        function setQuickShift(type) {
            const startInput = document.getElementById('shift_start');
            const endInput = document.getElementById('shift_end');
            
            if (type === 'morning') {
                startInput.value = '00:00';
                endInput.value = '12:00';
            } else if (type === 'evening') {
                startInput.value = '12:00';
                endInput.value = '23:59';
            }
            
            updateTimeLabel(startInput);
            updateTimeLabel(endInput);
        }

        // إضافة مستمعي الأحداث لمدخلات الوقت عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            const startInput = document.getElementById('shift_start');
            const endInput = document.getElementById('shift_end');
            
            startInput.addEventListener('change', function() { updateTimeLabel(this); });
            endInput.addEventListener('change', function() { updateTimeLabel(this); });
            
            // تحديث التسميات الأولية
            updateTimeLabel(startInput);
            updateTimeLabel(endInput);
        });
    </script>

@endsection
