@extends('hr::master')

@section('title', 'أرصدة الإجازات')

@section('page-actions')
    @permission('leave_balances.create')
        <a href="{{ route('employee_leave_balances.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">إضافة رصيد</span>
        </a>
    @endpermission
    <div class="btn-group">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button"
            id="leaveBalancesActions" data-bs-toggle="dropdown"
            data-bs-display="static" aria-expanded="false">
            <i class="fas fa-ellipsis-h"></i>
            <span class="d-none d-md-inline">إجراءات</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="leaveBalancesActions">
            @permission('leave_balances.edit')
                <li><h6 class="dropdown-header">الإدارة</h6></li>
                <li>
                    <button type="button" id="recalculateBtn" class="dropdown-item">
                        <i class="fas fa-calculator me-2 text-warning"></i>إعادة حساب الأرصدة
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
            @endpermission
            <li><h6 class="dropdown-header">التصدير</h6></li>
            <li>
                <button type="button" id="exportBtn" class="dropdown-item">
                    <i class="fas fa-file-excel me-2 text-success"></i>تصدير Excel
                </button>
            </li>
        </ul>
    </div>
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'أرصدة الإجازات',
        'icon' => 'pie-chart',
        'sub' => 'رصيد سنوي لكل موظف من كل نوع إجازة',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'أرصدة الإجازات' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'leaves'])

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-leave-balances.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه أرصدةُ إجازات الموظفين: ما لكلٍّ منهم من كلّ نوع، وما استُهلك منه. وهذا الرصيدُ هو ما يُفحَص عند اعتماد أيّ طلب إجازة.',
        'blocks' => [
            'كيف يتحرّك الرصيد' => [
                'اعتمادُ إجازةٍ يخصم أيامَها من رصيد نوعها؛ وإلغاءُ إجازةٍ اعتُمدت يُعيدها إليه.',
                'والطلبُ المعلَّق لا يمسّ الرصيد.',
                'ومصدرُ الرصيد الأصليّ سياساتُ الإجازات — وهذه الشاشةُ لتصحيحه ومتابعته.',
            ],
            'ما يترتّب على التعديل اليدويّ' => [
                'رفعُ رصيدٍ هنا يُجيز إجازةً كانت تُرفَض، وخفضُه يمنع إجازةً كانت تُقبَل — أثرُه مباشرٌ على ما يُعتمَد غداً.',
                'وهو لا يُصحّح إجازاتٍ اعتُمدت سلفاً: تلك خُصمت وقت اعتمادها.',
            ],
        ],
        'facts' => [
            'الرصيدُ يُفحَص عند اعتماد كلّ إجازة',
            'التعديلُ اليدويّ يُجيز أو يمنع ما يأتي بعده',
        ],
    ])

    @php
        $hasFilters = collect(request()->only(['employee_id', 'leave_type_id', 'year']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
        $canShow = hasPermission('leave_balances.show');
        $canEdit = hasPermission('leave_balances.edit');
        $canDestroy = hasPermission('leave_balances.destroy');
    @endphp

    @permission('leave_balances.index')
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if ($hasFilters)
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('employee_leave_balances.index') }}" id="filtersForm">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="employee_id">الموظف</label>
                                <select class="form-control select2" id="employee_id" name="employee_id">
                                    <option value="">جميع الموظفين</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->full_name }} - {{ $employee->employee_code ?? $employee->id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="leave_type_id">نوع الإجازة</label>
                                <select class="form-control select2" id="leave_type_id" name="leave_type_id">
                                    <option value="">جميع الأنواع</option>
                                    @foreach ($leaveTypes as $leaveType)
                                        <option value="{{ $leaveType->id }}" {{ request('leave_type_id') == $leaveType->id ? 'selected' : '' }}>
                                            {{ $leaveType->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="year">السنة</label>
                                <select class="form-control" id="year" name="year">
                                    <option value="">جميع السنوات</option>
                                    @for ($i = date('Y') + 1; $i >= date('Y') - 5; $i--)
                                        <option value="{{ $i }}" {{ request('year', date('Y')) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('employee_leave_balances.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endpermission

    <div class="card stk-table-card">
        @if ($balances->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة الأرصدة
                    <span class="stk-count">{{ $balances->total() }}</span>
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="balancesTable">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>الموظف</th>
                            <th>نوع الإجازة</th>
                            <th class="text-center">السنة</th>
                            <th class="text-center">الرصيد المبدئي</th>
                            <th class="text-center">المرحل</th>
                            <th class="text-center">الإضافي</th>
                            <th class="text-center">الإجمالي</th>
                            <th class="text-center">المستخدم</th>
                            <th class="text-center">المتبقي</th>
                            <th style="min-width:120px">نسبة الاستخدام</th>
                            <th class="text-center">الحالة</th>
                            <th>آخر تحديث</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($balances as $index => $balance)
                            @php
                                $totalAvailable = $balance->getTotalAvailableBalance();
                                $remaining = $balance->getActualRemainingBalance();
                                $percentage = $totalAvailable > 0
                                    ? round(($balance->used_balance / $totalAvailable) * 100, 1)
                                    : 0;
                                $typeColor = $balance->leaveType->color ?? null;
                            @endphp
                            <tr>
                                <td class="stk-num text-muted">{{ $balances->firstItem() + $index }}</td>
                                <td>
                                    {{-- لا قيدَ مرجعياً على employee_id/leave_type_id (الهجرة تركت جزء
                                         المفاتيح الأجنبية فارغاً)، فحذفُ موظفٍ أو نوع إجازةٍ لا يمنعه شيء
                                         من ترك صفّ رصيدٍ يشير إلى صفٍّ لم يعد موجوداً. --}}
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                        <span class="stk-media__body">
                                            @if ($canShow)
                                                <a href="{{ route('employee_leave_balances.show', $balance->id) }}" class="stk-media__title">{{ $balance->employee->full_name ?? 'موظف محذوف' }}</a>
                                            @else
                                                <span class="stk-media__title">{{ $balance->employee->full_name ?? 'موظف محذوف' }}</span>
                                            @endif
                                            <span class="stk-media__sub">{{ $balance->employee->employee_code ?? $balance->employee->id ?? '#'.$balance->employee_id }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: {{ $typeColor ?: 'var(--app-muted, #6c757d)' }}; color: #fff;">
                                        {{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }}
                                    </span>
                                </td>
                                <td class="text-center stk-num">{{ $balance->year }}</td>
                                <td class="text-center stk-num">{{ $balance->initial_balance }}</td>
                                <td class="text-center stk-num">{{ $balance->carried_forward }}</td>
                                <td class="text-center stk-num">{{ $balance->additional_balance }}</td>
                                <td class="text-center stk-num"><strong>{{ $totalAvailable }}</strong></td>
                                <td class="text-center stk-num">{{ $balance->used_balance }}</td>
                                <td class="text-center stk-num {{ $remaining <= 5 ? 'text-danger' : 'text-success' }}">
                                    <strong>{{ $remaining }}</strong>
                                </td>
                                <td>
                                    <div class="progress" style="height: 18px;">
                                        <div class="progress-bar {{ $percentage >= 90 ? 'bg-danger' : ($percentage >= 75 ? 'bg-warning' : 'bg-success') }}"
                                            role="progressbar"
                                            style="width: {{ $percentage }}%"
                                            aria-valuenow="{{ $percentage }}"
                                            aria-valuemin="0"
                                            aria-valuemax="100">
                                            {{ $percentage }}%
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if ($percentage >= 90)
                                        <span class="badge badge-danger">مكتمل تقريباً</span>
                                    @elseif ($percentage >= 75)
                                        <span class="badge badge-warning">مرتفع</span>
                                    @elseif ($percentage >= 50)
                                        <span class="badge badge-info">متوسط</span>
                                    @else
                                        <span class="badge badge-success">منخفض</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="stk-num">{{ $balance->updated_at->format('Y-m-d') }}</span>
                                    <span class="d-block text-muted small stk-num">{{ $balance->updated_at->format('H:i') }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($canShow || $canEdit || $canDestroy)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($canShow)
                                                    <a class="dropdown-item" href="{{ route('employee_leave_balances.show', $balance->id) }}">
                                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                    </a>
                                                @endif
                                                @if ($canEdit)
                                                    <a class="dropdown-item" href="{{ route('employee_leave_balances.edit', $balance->id) }}">
                                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                    </a>
                                                @endif
                                                @if ($canDestroy)
                                                    @if ($canShow || $canEdit)
                                                        <div class="dropdown-divider"></div>
                                                    @endif
                                                    @if ($balance->used_balance == 0)
                                                        <button type="button" class="dropdown-item text-danger delete-btn"
                                                            data-id="{{ $balance->id }}"
                                                            data-employee="{{ $balance->employee->full_name ?? 'موظف محذوف' }}"
                                                            data-leave-type="{{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }}">
                                                            <i class="fa fa-trash me-2"></i>حذف
                                                        </button>
                                                    @else
                                                        <span class="dropdown-item text-muted" title="لا يمكن الحذف - الرصيد مُستخدم">
                                                            <i class="fa fa-lock me-2"></i>لا يُحذف: الرصيد مستخدم
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($balances->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $balances->firstItem() }}–{{ $balances->lastItem() }} من {{ $balances->total() }}</p>
                    {{ $balances->appends(request()->query())->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-pie-chart"></i></div>
                    <h5 class="stk-empty__title">لا توجد أرصدة إجازات</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أي رصيد — جرّب معايير أوسع
                        @else
                            أضف رصيداً لموظف، أو أسند إليه سياسة إجازات ثم أعد حساب الأرصدة.
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('employee_leave_balances.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('leave_balances.create'))
                        <a href="{{ route('employee_leave_balances.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة رصيد</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Select2
        $('.select2').select2({
            placeholder: 'اختر...',
            allowClear: true
        });

        // Auto-submit filters on change
        $('.form-control').on('change', function() {
            if (this.id !== 'employee_id' && this.id !== 'leave_type_id') {
                document.getElementById('filtersForm').submit();
            }
        });

        // Delete functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-btn')) {
                const btn = e.target.closest('.delete-btn');
                const id = btn.getAttribute('data-id');
                const employee = btn.getAttribute('data-employee');
                const leaveType = btn.getAttribute('data-leave-type');

                Swal.fire({
                    title: 'تأكيد الحذف',
                    text: `هل تريد حذف رصيد إجازة "${leaveType}" للموظف "${employee}"؟`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        {{-- نفس علّة /recalculate أدناه: "admin/" ليست بادئة هذا المسار --}}
                        fetch(`{{ route('employee_leave_balances.index') }}/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'تم الحذف!',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonText: 'موافق'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'خطأ!',
                                    text: data.message,
                                    icon: 'error',
                                    confirmButtonText: 'موافق'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                title: 'خطأ!',
                                text: 'حدث خطأ أثناء الحذف',
                                icon: 'error',
                                confirmButtonText: 'موافق'
                            });
                        });
                    }
                });
            }
        });

        // Recalculate balances
        document.getElementById('recalculateBtn')?.addEventListener('click', function() {
            Swal.fire({
                title: 'إعادة حساب الأرصدة',
                text: 'سيتم إعادة حساب جميع الأرصدة بناءً على الطلبات المعتمدة. هل تريد المتابعة؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، احسب',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'جاري إعادة الحساب...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    {{--
                        المسار الحقيقي مُسبَّقٌ بالمنطقة واللغة والوحدة
                        (`{locale}/hr/employee-leave-balances/recalculate`)، لا جذراً
                        صرفاً. `fetch('/recalculate')` لا يطابق شيئاً، فيقع على
                        Route::fallback العام (المُسجَّل GET فقط) فيرُدّ 405 مضلِّلاً
                        بدل 404 — وهو الخطأ المُبلَّغ عنه. `route()` يُصلحه لأنه
                        يُصدر الرابط الكامل والصحيح دائماً، كما في show.blade.php.
                    --}}
                    fetch('{{ route('employee_leave_balances.recalculate') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            year: document.getElementById('year').value || new Date().getFullYear(),
                            employee_id: document.getElementById('employee_id').value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'تم بنجاح!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'موافق'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'خطأ!',
                                text: data.message,
                                icon: 'error',
                                confirmButtonText: 'موافق'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'خطأ!',
                            text: 'حدث خطأ أثناء إعادة الحساب',
                            icon: 'error',
                            confirmButtonText: 'موافق'
                        });
                    });
                }
            });
        });

        // Export functionality (placeholder)
        document.getElementById('exportBtn').addEventListener('click', function() {
            Swal.fire({
                title: 'قريباً',
                text: 'ميزة التصدير ستكون متاحة قريباً',
                icon: 'info',
                confirmButtonText: 'موافق'
            });
        });

        // Handle session messages
        @if(session('success'))
            Swal.fire({
                title: 'تم بنجاح!',
                text: '{{ session('success') }}',
                icon: 'success',
                confirmButtonText: 'موافق',
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
    });
</script>
@endsection
