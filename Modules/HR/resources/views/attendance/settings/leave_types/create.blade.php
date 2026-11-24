@extends('hr::master')

@section('title', 'إضافة نوع الإجازة')

@section('content')
<div class="hr-hints">
<div class="content-body">
    {{-- زرّا الترويسة يُحيلان إلى زرَّي أسفل النموذج (#cancelBtn / #saveBtn)
         فيمرّ الحفظ بنفس التحقّق ونافذة التأكيد --}}
    @include('stock::components.page-head', [
        'title' => 'إضافة نوع الإجازة',
        'icon' => 'log-out',
        'crumbs' => ['الرئيسية' => url('/'), 'أنواع الإجازات' => route('leave_types.index'), 'إضافة' => null],
        'actions' => '<button type="button" class="btn btn-outline-secondary" onclick="document.getElementById(\'cancelBtn\').click()"><i class="feather icon-x"></i> إلغاء</button>'
            .'<button type="button" class="btn btn-primary" onclick="document.getElementById(\'saveBtn\').click()"><i class="feather icon-check"></i> حفظ</button>',
    ])

    @include('layouts.alerts.error')

    @if ($errors->any())
        <div class="stk-form-errors alert alert-danger" role="alert">
            <div class="stk-form-errors__head">
                <i class="feather icon-alert-circle"></i> لم يُحفظ نوع الإجازة — راجع الحقول المعلَّمة:
            </div>
            <ul class="stk-form-errors__list mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @permission('leave_types.create')
        <form id="addLeaveTypeForm" method="POST" action="{{ route('leave_types.store') }}">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="name" class="form-label stk-req">الاسم</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                            <div class="invalid-feedback">يرجى إدخال اسم نوع الإجازة</div>
                        </div>

                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="colorDisplay" class="form-label stk-req">اللون</label>
                            <div class="d-flex align-items-start" style="gap: .5rem">
                                <div class="flex-grow-1">
                                    <input type="text" disabled id="colorDisplay" class="form-control" value="#9a4d40">
                                    <input type="hidden" name="color" id="colorHidden" class="form-control" value="#9a4d40">
                                    <div class="invalid-feedback">يرجى اختيار لون للإجازة</div>
                                </div>
                                <div style="width: 30%">
                                    <input type="text" id="colorPicker" class="form-control" value="#9a4d40">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="max_days_per_year" class="form-label stk-req">الحد الأقصى للأيام خلال العام</label>
                            <input type="number" class="form-control @error('max_days_per_year') is-invalid @enderror" id="max_days_per_year" name="max_days_per_year" value="{{ old('max_days_per_year') }}" min="1" required>
                            <div class="invalid-feedback">يرجى إدخال عدد الأيام (أكبر من صفر)</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-sliders"></i> القواعد</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="max_consecutive_days" class="form-label">الحد الأقصى للأيام المتوالية</label>
                            <input type="number" class="form-control @error('max_consecutive_days') is-invalid @enderror" id="max_consecutive_days" name="max_consecutive_days" value="{{ old('max_consecutive_days') }}" min="0">
                            <small class="stk-help">اتركه فارغاً إذا لم يكن هناك حد أقصى</small>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="applicable_after" class="form-label">قابلة للتطبيق بعد (بالأيام)</label>
                            <input type="number" class="form-control @error('applicable_after') is-invalid @enderror" id="applicable_after" name="applicable_after" value="{{ old('applicable_after') }}" min="0">
                            <small class="stk-help">عدد الأيام المطلوبة قبل التمكن من طلب هذا النوع من الإجازة</small>
                        </div>

                        <div class="col-md-12 col-xl-6 mb-3">
                            <label class="form-label d-block">الخيارات</label>
                            <div class="vs-checkbox-con vs-checkbox-primary mb-1">
                                <input type="checkbox" id="requires_approval" name="requires_approval" value="1" {{ old('requires_approval') ? 'checked' : '' }}>
                                <span class="vs-checkbox">
                                    <span class="vs-checkbox--check">
                                        <i class="vs-icon feather icon-check"></i>
                                    </span>
                                </span>
                                <label for="requires_approval">يحتاج إذن</label>
                            </div>
                            <div class="vs-checkbox-con vs-checkbox-primary">
                                <input type="checkbox" id="replace_weekends" name="replace_weekends" value="1" {{ old('replace_weekends') ? 'checked' : '' }}>
                                <span class="vs-checkbox">
                                    <span class="vs-checkbox--check">
                                        <i class="vs-icon feather icon-check"></i>
                                    </span>
                                </span>
                                <label for="replace_weekends">استبدال أيام عطلة نهاية الأسبوع</label>
                            </div>
                        </div>

                        <div class="col-12 mb-0">
                            <label for="description" class="form-label">الوصف</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="أدخل وصف لنوع الإجازة...">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endpermission

    <div class="card">
        <div class="stk-form-foot">
            <button type="button" id="cancelBtn" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </button>
            <button type="button" id="saveBtn" class="btn btn-primary">
                <i class="feather icon-check"></i> حفظ
            </button>
        </div>
    </div>
</div>
</div>
@include('components.hint-tips')
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('addLeaveTypeForm');

        // Initialize Color Picker
        $("#colorPicker").spectrum({
            showPalette: true,
            showInput: true,
            preferredFormat: "hex",
            palette: [
                ["#9a4d40", "#f44336", "#e91e63", "#9c27b0", "#673ab7"],
                ["#3f51b5", "#2196f3", "#03a9f4", "#00bcd4", "#009688"],
                ["#4caf50", "#8bc34a", "#cddc39", "#ffeb3b", "#ffc107"]
            ],
            i18n: {
                cancelText: "إلغاء",
                chooseText: "اختيار اللون",
                clearText: "مسح اللون",
                noColorSelectedText: "لم يتم اختيار لون"
            },
            change: function (color) {
                $("#colorDisplay").val(color.toHexString());
                $("#colorHidden").val(color.toHexString());
                $("#colorDisplay").removeClass('is-invalid');
            }
        });

        // Cancel Button Functionality
        document.getElementById('cancelBtn').addEventListener('click', function () {
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: 'سيتم إلغاء جميع التغييرات التي قمت بها!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، إلغاء',
                cancelButtonText: 'لا، استمر'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('leave_types.index') }}";
                }
            });
        });

        // Save Button Functionality
        document.getElementById('saveBtn').addEventListener('click', function () {
            if (!validateForm()) {
                return;
            }

            Swal.fire({
                title: 'تأكيد الحفظ',
                text: 'هل تريد حفظ نوع الإجازة؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، احفظ',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'جاري الحفظ...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.submit();
                }
            });
        });

        // Form validation function
        function validateForm() {
            let isValid = true;
            const name = document.getElementById('name');
            const colorHidden = document.getElementById('colorHidden');
            const maxDaysPerYear = document.getElementById('max_days_per_year');
            const maxConsecutiveDays = document.getElementById('max_consecutive_days');
            const applicableAfter = document.getElementById('applicable_after');

            // Clear previous validation states
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            // Validate name
            if (!name.value.trim()) {
                name.classList.add('is-invalid');
                isValid = false;
            }

            // Validate color
            if (!colorHidden.value) {
                document.getElementById('colorDisplay').classList.add('is-invalid');
                isValid = false;
            }

            // Validate max days per year
            if (!maxDaysPerYear.value || parseInt(maxDaysPerYear.value) <= 0) {
                maxDaysPerYear.classList.add('is-invalid');
                isValid = false;
            }

            // Validate max consecutive days (if provided)
            if (maxConsecutiveDays.value && parseInt(maxConsecutiveDays.value) < 0) {
                maxConsecutiveDays.classList.add('is-invalid');
                isValid = false;
            }

            // Validate applicable after (if provided)
            if (applicableAfter.value && parseInt(applicableAfter.value) < 0) {
                applicableAfter.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                Swal.fire({
                    title: 'خطأ في البيانات!',
                    text: 'يرجى ملء جميع الحقول المطلوبة بشكل صحيح',
                    icon: 'error',
                    confirmButtonText: 'موافق',
                });
            }

            return isValid;
        }

        // Handle form submission response
        @if(session('success'))
            Swal.fire({
                title: 'تم بنجاح!',
                text: '{{ session('success') }}',
                icon: 'success',
                confirmButtonText: 'موافق',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('leave_types.index') }}";
                }
            });
        @endif

        @if(session('error'))
            Swal.fire({
                title: 'حدث خطأ!',
                text: '{{ session('error') }}',
                icon: 'error',
                confirmButtonText: 'موافق',
            });
        @endif

        @if ($errors->any())
            let errorMessages = '';
            @foreach ($errors->all() as $error)
                errorMessages += '• {{ $error }}\n';
            @endforeach

            Swal.fire({
                title: 'أخطاء في البيانات!',
                text: errorMessages,
                icon: 'error',
                confirmButtonText: 'موافق',
            });
        @endif
    });
</script>
@endsection
