@extends('hr::master')

@section('title', 'تعديل طلب إجازة')

@section('page-actions')
    @permission('leave_requests.index')
        <a href="{{ route('attendance.leave_requests.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
    @endpermission
    @permission('leave_requests.edit')
        @if ($leaveRequest->isPending())
            <button type="submit" form="leave-request-form" class="btn btn-primary">
                <i class="feather icon-check"></i> حفظ التعديلات
            </button>
        @endif
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل طلب إجازة #'.$leaveRequest->id,
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'طلبات الإجازة' => hasPermission('leave_requests.index') ? route('attendance.leave_requests.index') : null,
            'طلب #'.$leaveRequest->id => hasPermission('leave_requests.show') ? route('attendance.leave_requests.show', $leaveRequest->id) : null,
            'تعديل' => null,
        ],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @permission('leave_requests.edit')
        <form id="leave-request-form" method="POST" action="{{ route('attendance.leave_requests.update', $leaveRequest->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="stk-form-errors alert alert-danger" role="alert">
                    <div class="stk-form-errors__head">
                        <i class="feather icon-alert-circle"></i> لم تُحفظ التعديلات — راجع الحقول المعلَّمة:
                    </div>
                    <ul class="stk-form-errors__list mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ══════════ البيانات الأساسية ══════════ --}}
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem">
                    <h4 class="card-title"><i class="feather icon-calendar"></i> البيانات الأساسية</h4>
                    <div class="d-flex align-items-center flex-wrap stk-help mt-0" style="gap:.75rem">
                        <span>رقم الطلب: <strong class="stk-num">#{{ $leaveRequest->id }}</strong></span>
                        <span class="badge badge-{{ $leaveRequest->status === 'pending' ? 'warning' : ($leaveRequest->status === 'approved' ? 'success' : 'danger') }}">
                            {{ $leaveRequest->status_text }}
                        </span>
                        <span>أُنشئ: <span class="stk-num">{{ $leaveRequest->created_at->format('Y-m-d H:i') }}</span></span>
                        @if (! $leaveRequest->isPending())
                            <span><i class="feather icon-lock"></i> لا يمكن تعديل الطلبات المعتمدة أو المرفوضة</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- الموظف -->
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="employee_id" class="form-label stk-req">الموظف</label>
                            <select class="form-control select2 @error('employee_id') is-invalid @enderror"
                                    id="employee_id" name="employee_id" required {{ !$leaveRequest->isPending() ? 'disabled' : '' }}>
                                <option value="" disabled>اختر الموظف</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}"
                                            {{ old('employee_id', $leaveRequest->employee_id) == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($employees ?? [])"
                                route="employee.create"
                                permission="employees.create"
                                :plan="\App\Constants\planFeatures\HrPlanFeatures::EMPLOYEES"
                                label="موظفاً"
                                hint="العملية تقع على موظف، ولا موظف مسجَّل بعد." />
                            @error('employee_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- نوع الإجازة -->
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="leave_type_id" class="form-label stk-req">نوع الإجازة</label>
                            <select class="form-control @error('leave_type_id') is-invalid @enderror"
                                    id="leave_type_id" name="leave_type_id" required {{ !$leaveRequest->isPending() ? 'disabled' : '' }}>
                                <option value="" disabled>اختر نوع الإجازة</option>
                                @foreach ($leaveTypes as $leaveType)
                                    <option value="{{ $leaveType->id }}"
                                            {{ old('leave_type_id', $leaveRequest->leave_type_id) == $leaveType->id ? 'selected' : '' }}>
                                        {{ $leaveType->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('leave_type_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- نوع الطلب -->
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="request_type" class="form-label stk-req">نوع الطلب</label>
                            <select class="form-control @error('request_type') is-invalid @enderror"
                                    id="request_type" name="request_type" required {{ !$leaveRequest->isPending() ? 'disabled' : '' }}>
                                <option value="leave" {{ old('request_type', $leaveRequest->request_type) == 'leave' ? 'selected' : '' }}>إجازة عادية</option>
                                <option value="emergency" {{ old('request_type', $leaveRequest->request_type) == 'emergency' ? 'selected' : '' }}>إجازة طارئة</option>
                                <option value="sick" {{ old('request_type', $leaveRequest->request_type) == 'sick' ? 'selected' : '' }}>إجازة مرضية</option>
                            </select>
                            @error('request_type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- عدد الأيام -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="days" class="form-label stk-req">عدد أيام العمل</label>
                            <input type="number" class="form-control @error('days') is-invalid @enderror"
                                   id="days" name="days" min="1" max="365"
                                   value="{{ old('days', $leaveRequest->days) }}" required
                                   placeholder="أدخل عدد أيام العمل" {{ !$leaveRequest->isPending() ? 'readonly' : '' }}>
                            @error('days')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="stk-help">تُستثنى العطل الأسبوعية والرسمية تلقائياً من الفترة، ويُحسب تاريخ الانتهاء تلقائيًا</small>
                        </div>

                        <!-- تاريخ البدء -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="start_date" class="form-label stk-req">تاريخ البدء</label>
                            <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                                   id="start_date" name="start_date"
                                   value="{{ old('start_date', \Carbon\Carbon::parse($leaveRequest->start_date)->format('Y-m-d')) }}"
                                   required {{ !$leaveRequest->isPending() ? 'readonly' : '' }}>
                            @error('start_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- تاريخ الانتهاء -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="end_date" class="form-label">تاريخ الانتهاء</label>
                            <input type="date" class="form-control" id="end_date" name="end_date"
                                   value="{{ old('end_date', \Carbon\Carbon::parse($leaveRequest->end_date)->format('Y-m-d')) }}"
                                   readonly>
                            <small class="stk-help">يتم حسابه تلقائيًا</small>
                        </div>

                        <!-- رصيد الإجازات -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label class="form-label">رصيد الإجازات</label>
                            <div class="alert alert-info py-2 mb-0">
                                <i class="feather icon-info mr-1"></i>
                                <span class="font-weight-bold"><span id="leave-balance">0</span> يوم</span>
                            </div>
                            <a href="#" class="stk-help text-primary" data-toggle="modal" data-target="#leaveBalanceModal">
                                <i class="feather icon-pie-chart"></i> تفقد رصيد الإجازات
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ بيانات إضافية ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-file-text"></i> بيانات إضافية</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- الوصف -->
                        <div class="col-xl-6 col-12 mb-3">
                            <label for="description" class="form-label">الوصف والملاحظات</label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description" name="description" rows="4"
                                      placeholder="أدخل وصف أو ملاحظات إضافية حول طلب الإجازة (اختياري)"
                                      {{ !$leaveRequest->isPending() ? 'readonly' : '' }}>{{ old('description', $leaveRequest->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- المرفقات -->
                        <div class="col-xl-6 col-12 mb-3">
                            <label for="attachments" class="form-label">المرفقات</label>

                            <!-- المرفقات الحالية -->
                            @if($leaveRequest->attachments)
                                <div class="mb-2">
                                    <small class="stk-help mt-0 mb-50">المرفقات الحالية:</small>
                                    <div class="d-flex flex-wrap lr-attachments">
                                        @foreach(json_decode($leaveRequest->attachments) as $attachment)
                                            <div class="border rounded px-1 py-50 d-flex align-items-center">
                                                <a href="{{ asset('storage/' . $attachment) }}" target="_blank" class="text-decoration-none">
                                                    <i class="feather icon-file-text mr-50 text-primary"></i>
                                                    <span>{{ basename($attachment) }}</span>
                                                </a>
                                                @if($leaveRequest->isPending())
                                                    <button type="button" class="btn btn-sm btn-outline-danger ml-50 remove-attachment"
                                                            data-attachment="{{ $attachment }}">
                                                        <i class="feather icon-x"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($leaveRequest->isPending())
                                <input type="file" name="attachments[]" id="attachments" class="d-none" multiple
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">

                                <div class="upload-area border border-2 border-dashed rounded p-3 text-center position-relative cursor-pointer"
                                     onclick="document.getElementById('attachments').click()">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="feather icon-upload-cloud text-primary mb-1" style="font-size:1.5rem"></i>
                                        <div class="mb-1">
                                            <span class="text-primary font-weight-bold">اضغط هنا لإضافة مرفقات جديدة</span>
                                            <span class="text-muted">أو اسحب الملفات إلى هنا</span>
                                        </div>
                                        <small class="stk-help mt-0">
                                            الملفات المسموحة: PDF, DOC, DOCX, JPG, PNG (حد أقصى 5 ميجابايت لكل ملف)
                                        </small>
                                    </div>
                                </div>

                                <div id="file-list" class="mt-2"></div>
                            @else
                                <div class="alert alert-warning py-2 mb-0">
                                    <i class="feather icon-lock mr-1"></i>
                                    لا يمكن تعديل المرفقات بعد اعتماد الطلب
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- معلومات الموافقة/الرفض -->
                    @if(!$leaveRequest->isPending())
                        <div class="alert alert-{{ $leaveRequest->status === 'approved' ? 'success' : 'danger' }} mb-0">
                            <h6 class="alert-heading">
                                <i class="feather icon-{{ $leaveRequest->status === 'approved' ? 'check-circle' : 'x-circle' }} mr-1"></i>
                                {{ $leaveRequest->status === 'approved' ? 'تمت الموافقة على الطلب' : 'تم رفض الطلب' }}
                            </h6>
                            <p class="mb-1">
                                <strong>بواسطة:</strong> {{ $leaveRequest->approver->name ?? 'غير محدد' }} |
                                <strong>في:</strong> {{ $leaveRequest->approved_at ? $leaveRequest->approved_at->format('Y-m-d H:i') : 'غير محدد' }}
                            </p>
                            @if($leaveRequest->rejection_reason)
                                <p class="mb-0">
                                    <strong>سبب الرفض:</strong> {{ $leaveRequest->rejection_reason }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    @permission('leave_requests.index')
                        <a href="{{ route('attendance.leave_requests.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    @endpermission
                    @if($leaveRequest->isPending())
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-check"></i> حفظ التعديلات
                        </button>
                    @endif
                </div>
            </div>
        </form>
    @endpermission

    <!-- مودال رصيد الإجازات -->
    <div class="modal fade" id="leaveBalanceModal" tabindex="-1" role="dialog" aria-labelledby="leaveBalanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveBalanceModalLabel">
                        <i class="feather icon-pie-chart mr-1"></i> رصيد الإجازات
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">جاري التحميل...</span>
                        </div>
                        <p class="mt-2">جاري تحميل بيانات الموظف...</p>
                    </div>

                    <div class="table-responsive d-none" id="balance-table-container">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>نوع الإجازة</th>
                                    <th>المستحق</th>
                                    <th>المستخدم</th>
                                    <th>المتبقي</th>
                                </tr>
                            </thead>
                            <tbody id="leave-balance-details">
                                <!-- سيتم ملؤها بالجافاسكريبت -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // عرض تنبيه SweetAlert عند وجود رسائل من السيرفر
            @if(session('sweet_alert'))
                const alert = @json(session('sweet_alert'));
                Swal.fire({
                    icon: alert.type,
                    title: alert.title,
                    text: alert.message,
                    confirmButtonText: 'موافق',
                });
            @endif

            // تفعيل Select2
            $('.select2').select2({
                placeholder: 'اختر من القائمة',
                allowClear: true
            });

            // تأكيد الحفظ عند إرسال النموذج (فقط إذا كان الطلب في حالة انتظار)
            @if($leaveRequest->isPending())
                $('#leave-request-form').on('submit', function(e) {
                    e.preventDefault();

                    Swal.fire({
                        title: 'تأكيد التعديل',
                        text: 'هل أنت متأكد من حفظ التعديلات على طلب الإجازة؟',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'نعم، احفظ التعديلات',
                        cancelButtonText: 'إلغاء',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // عرض تنبيه التحميل
                            Swal.fire({
                                title: 'جاري حفظ التعديلات...',
                                text: 'يرجى الانتظار',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                showConfirmButton: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            // إرسال النموذج
                            this.submit();
                        }
                    });
                });

                // عرض الملفات المرفوعة الجديدة
                $('#attachments').change(function() {
                    let files = $(this)[0].files;
                    let fileList = $('#file-list');
                    fileList.empty();

                    if (files.length > 0) {
                        let filesHtml = '<div class="alert alert-success py-2 mb-0"><i class="feather icon-check mr-1"></i><strong>ملفات جديدة للإضافة:</strong><ul class="mb-0 mt-1">';

                        for (let i = 0; i < files.length; i++) {
                            filesHtml += `<li><i class="feather icon-file mr-2"></i>${files[i].name} (${(files[i].size / 1024 / 1024).toFixed(2)} MB)</li>`;
                        }

                        filesHtml += '</ul></div>';
                        fileList.html(filesHtml);
                    } else {
                        fileList.empty();
                    }
                });

                // حساب التواريخ عند تغيير تاريخ البدء أو عدد الأيام
                // والموظفُ أيضاً: عطلتُه الأسبوعية من ورديته تغيّر تاريخ النهاية
            $('#start_date, #days, #employee_id').on('change', function() {
                    calculateDates();
                });

                // حذف مرفق موجود
                $('.remove-attachment').click(function() {
                    let attachment = $(this).data('attachment');
                    let attachmentElement = $(this).closest('.border');

                    Swal.fire({
                        title: 'حذف المرفق',
                        text: 'هل أنت متأكد من حذف هذا المرفق؟',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'نعم، احذف',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // هنا يمكنك إضافة منطق حذف المرفق من الخادم
                            attachmentElement.fadeOut(300, function() {
                                $(this).remove();
                            });

                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف',
                                text: 'تم حذف المرفق بنجاح',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    });
                });

                // تحسين تصميم منطقة رفع الملفات
                $('.upload-area').on('dragover', function(e) {
                    e.preventDefault();
                    $(this).addClass('border-primary bg-light');
                });

                $('.upload-area').on('dragleave', function(e) {
                    e.preventDefault();
                    $(this).removeClass('border-primary bg-light');
                });

                $('.upload-area').on('drop', function(e) {
                    e.preventDefault();
                    $(this).removeClass('border-primary bg-light');

                    let files = e.originalEvent.dataTransfer.files;
                    document.getElementById('attachments').files = files;
                    $('#attachments').trigger('change');
                });
            @endif

            // جلب رصيد الإجازات عند تحميل الصفحة أو تغيير الموظف
            function loadEmployeeBalance() {
                let employeeId = $('#employee_id').val();
                if (employeeId) {
                    $.ajax({
                        // نفسُ مسار صفحة الإضافة ومفاتيحِها — كان هنا عنوانٌ مكتوبٌ باليد بلا لغة ومفاتيحُ لا يُرجعها الخادم
                        url: "{{ route('attendance.employees.leave_balance', ':id') }}".replace(':id', employeeId),
                        method: 'GET',
                        success: function(data) {
                            $('#leave-balance').text((data && data.success) ? (data.remaining_balance || 0) : 0);

                            // تحديث جدول رصيد الإجازات في المودال
                            let balanceDetails = '';
                            if (data && data.success && data.leave_types && data.leave_types.length > 0) {
                                data.leave_types.forEach(function(item) {
                                    balanceDetails += `
                                        <tr>
                                            <td>${item.name}</td>
                                            <td><span class="badge badge-info">${item.total_balance || 0}</span></td>
                                            <td><span class="badge badge-warning">${item.used_balance || 0}</span></td>
                                            <td><span class="badge badge-${(item.remaining_balance && item.remaining_balance > 0) ? 'success' : 'danger'}">${item.remaining_balance || 0}</span></td>
                                        </tr>
                                    `;
                                });
                            } else {
                                balanceDetails = '<tr><td colspan="4" class="text-center text-muted">لا توجد بيانات إجازات لهذا الموظف</td></tr>';
                            }

                            $('#leave-balance-details').html(balanceDetails);
                            $('#balance-table-container').removeClass('d-none');
                            $('.spinner-border').parent().addClass('d-none');
                        },
                        error: function() {
                            $('#leave-balance').text('0');
                            $('#leave-balance-details').html('<tr><td colspan="4" class="text-center text-muted">حدث خطأ في تحميل البيانات</td></tr>');
                            $('#balance-table-container').removeClass('d-none');
                            $('.spinner-border').parent().addClass('d-none');
                        }
                    });
                }
            }

            // تحميل رصيد الإجازات عند تحميل الصفحة
            loadEmployeeBalance();

            // جلب رصيد الإجازات عند تغيير الموظف
            $('#employee_id').change(function() {
                $('#balance-table-container').addClass('d-none');
                $('.spinner-border').parent().removeClass('d-none');
                loadEmployeeBalance();
            });

            // دالة حساب تاريخ الانتهاء بناءً على تاريخ البدء وعدد الأيام
            function calculateDates() {
                /*
                    تاريخُ النهاية من الخادم — بأيام العمل، متخطّياً العطل
                    الأسبوعية والرسمية كما يُحفظ. كان يُحسب هنا بأيامٍ تقويمية
                    فيختلف عمّا يُحفظ، ويرمي خطأً إن كُتب عددُ الأيام قبل التاريخ.
                */
                const start = $('#start_date').val();
                const days = parseInt($('#days').val(), 10);
                const employeeId = $('#employee_id').val();

                if (!start || isNaN(days) || days < 1 || !employeeId) {
                    $('#end_date').val('');
                    return;
                }

                $.get("{{ route('attendance.employees.leave_end_date', ':id') }}".replace(':id', employeeId), {
                    start_date: start,
                    days: days
                }).done(function (res) {
                    if (res && res.end_date) {
                        $('#end_date').val(res.end_date);
                    }
                });
            }

            // تشغيل حساب التواريخ عند تحميل الصفحة إذا كانت هناك قيم موجودة
            if ($('#start_date').val() && $('#days').val()) {
                calculateDates();
            }
        });
    </script>

    <style>
        /* منطقة رفع المرفقات — يضيف إليها سكربت السحب والإفلات `border-primary bg-light` */
        .upload-area { cursor: pointer; border-style: dashed !important; transition: border-color .2s ease, background-color .2s ease; }
        .upload-area:hover { border-color: var(--app-primary, #7367f0) !important; background-color: var(--app-body-bg, #f8f9fa); }
        .lr-attachments { gap: .5rem; }
    </style>
@endsection
