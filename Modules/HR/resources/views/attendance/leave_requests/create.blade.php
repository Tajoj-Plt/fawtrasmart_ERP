@extends('hr::master')

@section('title', 'إضافة طلب إجازة')

@section('page-actions')
    @permission('leave_requests.index')
        <a href="{{ route('attendance.leave_requests.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
    @endpermission
    @permission('leave_requests.create')
        <button type="submit" form="leave-request-form" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ الطلب
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة طلب إجازة',
        'icon' => 'send',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'طلبات الإجازة' => hasPermission('leave_requests.index') ? route('attendance.leave_requests.index') : null,
            'إضافة' => null,
        ],
    ])

    @include('layouts.alerts.error')

    @permission('leave_requests.create')
        <form id="leave-request-form" method="POST" action="{{ route('attendance.leave_requests.store') }}" enctype="multipart/form-data">
            @csrf

            @if ($errors->any())
                <div class="stk-form-errors alert alert-danger" role="alert">
                    <div class="stk-form-errors__head">
                        <i class="feather icon-alert-circle"></i> لم يُحفظ الطلب — راجع الحقول المعلَّمة:
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
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-calendar"></i> البيانات الأساسية</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- الموظف -->
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="employee_id" class="form-label stk-req">الموظف</label>
                            <select class="form-control select2 @error('employee_id') is-invalid @enderror"
                                    id="employee_id" name="employee_id" required>
                                <option value="" disabled selected>اختر الموظف</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
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
                            <label for="leave_type_id" class="form-label stk-req">الإجازة</label>
                            <select class="form-control @error('leave_type_id') is-invalid @enderror"
                                    id="leave_type_id" name="leave_type_id" required>
                                <option value="" disabled selected>اختر نوع الإجازة</option>
                                @foreach ($leaveTypes as $leaveType)
                                    <option value="{{ $leaveType->id }}" {{ old(key: 'leave_type_id') == $leaveType->id ? 'selected' : '' }}>
                                        {{ $leaveType->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($leaveTypes ?? [])"
                                route="leave_types.create"
                                permission="leave_types.create"
                                label="نوع إجازة"
                                hint="طلب الإجازة يتبع نوعاً، ولا أنواع إجازات مسجَّلة بعد." />
                            @error('leave_type_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- نوع الطلب -->
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="request_type" class="form-label stk-req">نوع الطلب</label>
                            <select class="form-control @error('request_type') is-invalid @enderror"
                                    id="request_type" name="request_type" required>
                                <option value="leave" {{ old('request_type', 'leave') == 'leave' ? 'selected' : '' }}>إجازة عادية</option>
                                <option value="emergency" {{ old('request_type') == 'emergency' ? 'selected' : '' }}>إجازة طارئة</option>
                                <option value="sick" {{ old('request_type') == 'sick' ? 'selected' : '' }}>إجازة مرضية</option>
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
                                   value="{{ old('days') }}" required placeholder="أدخل عدد أيام العمل">
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
                                   value="{{ old('start_date') }}" required>
                            @error('start_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- تاريخ الانتهاء -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="end_date" class="form-label">تاريخ الانتهاء</label>
                            <input type="date" class="form-control" id="end_date" name="end_date"
                                   value="{{ old('end_date') }}" readonly>
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
                                      placeholder="أدخل وصف أو ملاحظات إضافية حول طلب الإجازة (اختياري)">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- المرفقات -->
                        <div class="col-xl-6 col-12 mb-3">
                            <label for="attachments" class="form-label">المرفقات</label>
                            <input type="file" name="attachments[]" id="attachments" class="d-none" multiple
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">

                            <div class="upload-area border border-2 border-dashed rounded p-3 text-center position-relative cursor-pointer"
                                 onclick="document.getElementById('attachments').click()">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="feather icon-upload-cloud text-primary mb-1" style="font-size:1.5rem"></i>
                                    <div class="mb-1">
                                        <span class="text-primary font-weight-bold">اضغط هنا لاختيار الملفات</span>
                                        <span class="text-muted">أو اسحب الملفات إلى هنا</span>
                                    </div>
                                    <small class="stk-help mt-0">
                                        الملفات المسموحة: PDF, DOC, DOCX, JPG, PNG (حد أقصى 5 ميجابايت لكل ملف)
                                    </small>
                                </div>
                            </div>

                            <div id="file-list" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    @permission('leave_requests.index')
                        <a href="{{ route('attendance.leave_requests.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    @endpermission
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ الطلب
                    </button>
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
                        <p class="mt-2">يرجى اختيار موظف أولاً</p>
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

            // تأكيد الحفظ عند إرسال النموذج
            $('#leave-request-form').on('submit', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: 'تأكيد الحفظ',
                    text: 'هل أنت متأكد من حفظ طلب الإجازة؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احفظ الطلب',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // عرض تنبيه التحميل
                        Swal.fire({
                            title: 'جاري حفظ الطلب...',
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

            // عرض الملفات المرفوعة
            $('#attachments').change(function() {
                let files = $(this)[0].files;
                let fileList = $('#file-list');
                fileList.empty();

                if (files.length > 0) {
                    let filesHtml = '<div class="alert alert-success py-2 mb-0"><i class="feather icon-check mr-1"></i><strong>تم اختيار الملفات:</strong><ul class="mb-0 mt-1">';

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

            // جلب رصيد الإجازات عند اختيار موظف
            $('#employee_id').change(function() {
                let employeeId = $(this).val();
                console.log('تم اختيار موظف جديد:', employeeId);
                
                if (employeeId) {
                    console.log('إرسال طلب لجلب رصيد الإجازات...');
                    $.ajax({
                        url: "{{ route('attendance.employees.leave_balance', ':id') }}".replace(':id', employeeId),
                        method: 'GET',
                        beforeSend: function() {
                            console.log('جاري إرسال الطلب...');
                        },
                        success: function(data) {
                            console.log('استجابة رصيد الإجازات:', data);
                            
                            if (data.success) {
                                // عرض الرصيد المتبقي أو صفر إذا لم يوجد
                                $('#leave-balance').text(data.remaining_balance || 0);

                                // تحديث جدول رصيد الإجازات في المودال
                                let balanceDetails = '';
                                if (data.leave_types && data.leave_types.length > 0) {
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
                                    // إذا لم توجد أنواع إجازات، عرض رسالة إعلامية بدلاً من خطأ
                                    balanceDetails = '<tr><td colspan="4" class="text-center text-info">لا توجد أنواع إجازات محددة لهذا الموظف</td></tr>';
                                }

                                $('#leave-balance-details').html(balanceDetails);
                                $('#balance-table-container').removeClass('d-none');
                                $('.spinner-border').parent().addClass('d-none');
                            } else {
                                // إذا كانت الاستجابة غير ناجحة ولكن ليس خطأ، عرض القيم بصفر
                                console.log('الاستجابة غير ناجحة، عرض القيم بصفر');
                                $('#leave-balance').text('0');
                                
                                // عرض رسالة إعلامية بدلاً من خطأ
                                let balanceDetails = '<tr><td colspan="4" class="text-center text-info">لا توجد بيانات إجازات متاحة لهذا الموظف</td></tr>';
                                $('#leave-balance-details').html(balanceDetails);
                                $('#balance-table-container').removeClass('d-none');
                                $('.spinner-border').parent().addClass('d-none');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('خطأ AJAX:', {xhr, status, error});
                            console.error('Response Text:', xhr.responseText);
                            
                            // عرض القيم بصفر حتى في حالة الخطأ
                            $('#leave-balance').text('0');
                            
                            // تحديد نوع الرسالة حسب نوع الخطأ
                            let errorMessage = '';
                            if (xhr.status === 404) {
                                errorMessage = 'لا توجد بيانات إجازات لهذا الموظف';
                            } else if (xhr.status === 500) {
                                errorMessage = 'خطأ في الخادم، يرجى المحاولة مرة أخرى';
                            } else {
                                errorMessage = 'لا توجد بيانات إجازات متاحة حالياً';
                            }
                            
                            $('#leave-balance-details').html(`<tr><td colspan="4" class="text-center text-warning">${errorMessage}</td></tr>`);
                            $('#balance-table-container').removeClass('d-none');
                            $('.spinner-border').parent().addClass('d-none');
                        }
                    });
                } else {
                    $('#leave-balance').text('0');
                    $('#balance-table-container').addClass('d-none');
                    $('.spinner-border').parent().removeClass('d-none');
                }
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

            // تعيين الحد الأدنى لتاريخ البدء (اليوم)
            const today = new Date().toISOString().split('T')[0];
            $('#start_date').attr('min', today);
        });
    </script>

    <style>
        /* منطقة رفع المرفقات — يضيف إليها سكربت السحب والإفلات `border-primary bg-light` */
        .upload-area { cursor: pointer; border-style: dashed !important; transition: border-color .2s ease, background-color .2s ease; }
        .upload-area:hover { border-color: var(--app-primary, #7367f0) !important; background-color: var(--app-body-bg, #f8f9fa); }
    </style>
@endsection
