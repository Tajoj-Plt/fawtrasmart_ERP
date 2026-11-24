@extends('hr::master')

@section('title')
    عرض الماكينة
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
@endsection

@php
    $canManageMachine = hasPermission('attendance_devices.edit');
    $canDeleteMachine = hasPermission('attendance_devices.destroy');
    $canPullData = hasPermission('attendance_records.pull');
    $canPullMachine = $canManageMachine || $canPullData;
@endphp

@section('page-actions')
    @if ($canManageMachine)
        <a href="{{ route('machines.edit', $machine->id) }}" class="btn btn-primary">
            <i class="fa fa-edit"></i>
            <span>تعديل</span>
        </a>
    @endif
    @if ($canPullMachine)
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa fa-plug"></i>
                <span>الجهاز</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if ($canManageMachine)
                    <li>
                        <button type="button" class="dropdown-item" id="testConnectionBtn" data-machine-id="{{ $machine->id }}">
                            <i class="fa fa-bolt me-2 text-warning"></i>اختبر الاتصال
                        </button>
                    </li>
                @endif
                @if ($canPullData)
                <li>
                    <button type="button" class="dropdown-item" id="pullDataBtn" data-machine-id="{{ $machine->id }}">
                        <i class="fa fa-database me-2 text-info"></i>اسحب البيانات
                    </button>
                </li>
                @endif
                @if ($canManageMachine)
                <li>
                    <button type="button" class="dropdown-item" id="connectBtn" data-machine-id="{{ $machine->id }}">
                        <i class="fa fa-exchange-alt me-2 text-primary"></i>ربط
                    </button>
                </li>
                @endif
            </ul>
        </div>
    @endif
    @if ($canManageMachine || $canDeleteMachine)
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa fa-ellipsis-h"></i>
                <span>المزيد</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if ($canManageMachine)
                <li>
                    <button type="button" class="dropdown-item status-toggle"
                        data-machine-id="{{ $machine->id }}" data-current-status="{{ $machine->status }}">
                        <i class="fa {{ $machine->status ? 'fa-ban text-warning' : 'fa-check text-success' }} me-2"></i>{{ $machine->status ? 'تعطيل' : 'تفعيل' }}
                    </button>
                </li>
                @endif
                @if ($canDeleteMachine)
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button type="button" class="dropdown-item text-danger" id="deleteBtn"
                        data-machine-id="{{ $machine->id }}" data-machine-name="{{ $machine->name }}">
                        <i class="fa fa-trash me-2"></i>حذف
                    </button>
                </li>
                @endif
            </ul>
        </div>
    @endif
@endsection

@section('content')
    @include('hr::components.table-menu')
    @include('hr::attendance._partials.styles')

<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $machine->name,
        'icon' => 'monitor',
        'crumbs' => ['الرئيسية' => url('/'), 'الماكينات' => route('machines.index'), $machine->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-monitor"></i></span>
                    <h4 class="mb-25">{{ $machine->name }}</h4>
                    <p class="stk-help mt-0 mb-1">ماكينة رقم #{{ $machine->id }} · {{ ucfirst($machine->machine_type) }}</p>
                    <span class="stk-state stk-state--{{ $machine->status == 1 ? 'on' : 'off' }}">{{ $machine->status == 1 ? 'مفعّلة' : 'غير مفعّلة' }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">رقم المنفذ</span>
                        <span class="stk-metric__value">{{ $machine->port_number }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">سجلات النشاط</span>
                        <span class="stk-metric__value">{{ $logs ? collect($logs)->flatten()->count() : 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>الاسم</dt>
                            <dd>{{ $machine->name }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الرقم التسلسلي</dt>
                            <dd>{{ $machine->serial_number ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>نوع الماكينة</dt>
                            <dd>{{ ucfirst($machine->machine_type) }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>اسم المضيف</dt>
                            <dd class="stk-num" dir="ltr">{{ $machine->host_name }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>رقم المنفذ</dt>
                            <dd class="stk-num">{{ $machine->port_number }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>مفتاح الاتصال</dt>
                            <dd>{{ $machine->connection_key ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الحالة (مفعل/غير مفعل)</dt>
                            <dd>{{ $machine->status == 1 ? 'مفعّلة' : 'غير مفعّلة' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تاريخ الإضافة / آخر تحديث</dt>
                            <dd class="stk-num">{{ optional($machine->created_at)->format('Y-m-d H:i') ?? '—' }} / {{ optional($machine->updated_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- ══════════ سجل النشاطات ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card" id="activity-log">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-activity"></i> سجل النشاطات</h4></div>
                <div class="card-body">
                    @if ($logs && count($logs) > 0)
                        @php
                            $previousDate = null;
                        @endphp

                        @foreach ($logs as $date => $dayLogs)
                            @php
                                $currentDate = \Carbon\Carbon::parse($date);
                                $diffInDays = $previousDate ? $previousDate->diffInDays($currentDate) : 0;
                            @endphp

                            @if ($diffInDays > 7)
                                <div class="timeline-date">
                                    <h4>{{ $currentDate->format('Y-m-d') }}</h4>
                                </div>
                            @endif

                            <div class="timeline-day">{{ $currentDate->translatedFormat('l') }}</div>

                            <ul class="timeline">
                                @foreach ($dayLogs as $log)
                                    @if ($log)
                                        <li class="timeline-item">
                                            <div class="timeline-content">
                                                <div class="time">
                                                    <i class="feather icon-clock"></i>
                                                    {{ $log->created_at->format('H:i:s') }}
                                                </div>
                                                <div>
                                                    <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                    {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                                                    <div class="text-muted">
                                                        {{ $log->user->branch->name ?? 'فرع غير معروف' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>

                            @php
                                $previousDate = $currentDate;
                            @endphp
                        @endforeach
                    @else
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                            <h5 class="stk-empty__title">لا توجد سجلات نشاط حتى الآن!</h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // معالجة تغيير حالة الماكينة
            document.querySelector('.status-toggle')?.addEventListener('click', function() {
                const machineId = this.dataset.machineId;
                const currentStatus = this.dataset.currentStatus === '1';
                const actionText = currentStatus ? 'تعطيل' : 'تفعيل';

                Swal.fire({
                    title: `${actionText} الماكينة`,
                    text: `هل تريد ${actionText} هذه الماكينة؟`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: `نعم، ${actionText}`,
                    cancelButtonText: 'إلغاء',
                    confirmButtonColor: currentStatus ? '#dc3545' : '#28a745'
                }).then((result) => {
                    if (result.isConfirmed) {
                        toggleMachineStatus(machineId);
                    }
                });
            });

            // معالجة حذف الماكينة
            document.getElementById('deleteBtn')?.addEventListener('click', function() {
                const machineId = this.dataset.machineId;
                const machineName = this.dataset.machineName;

                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: `سيتم حذف الماكينة "${machineName}" نهائياً ولا يمكن التراجع عن هذا الإجراء`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteMachine(machineId);
                    }
                });
            });

            // اختبار الاتصال
            document.getElementById('testConnectionBtn')?.addEventListener('click', function() {
                const machineId = this.dataset.machineId;
                testConnection(machineId);
            });

            // سحب البيانات
            document.getElementById('pullDataBtn')?.addEventListener('click', function() {
                const machineId = this.dataset.machineId;
                pullData(machineId);
            });

            // ربط الماكينة
            document.getElementById('connectBtn')?.addEventListener('click', function() {
                const machineId = this.dataset.machineId;
                connectMachine(machineId);
            });

            // رسائل النجاح/الخطأ من Session
            @if (session('success'))
                Swal.fire({
                    title: 'تم بنجاح!',
                    text: '{{ session('success') }}',
                    icon: 'success',
                    confirmButtonText: 'موافق'
                });
            @endif

            @if (session('error'))
                Swal.fire({
                    title: 'خطأ!',
                    text: '{{ session('error') }}',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
            @endif
        });

        // دالة تغيير حالة الماكينة
        function toggleMachineStatus(machineId) {
            Swal.fire({
                title: 'جاري التحديث...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch("{{ route('machines.toggle-status', ':id') }}".replace(':id', machineId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
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
                console.error('Error:', error);
                Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ في الاتصال بالخادم',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
            });
        }

        // دالة حذف الماكينة
        function deleteMachine(machineId) {
            Swal.fire({
                title: 'جاري الحذف...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch("{{ route('machines.destroy', ':id') }}".replace(':id', machineId), {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'تم الحذف!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'موافق'
                    }).then(() => {
                        window.location.href = "{{ route('machines.index') }}";
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
                console.error('Error:', error);
                Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ في الاتصال بالخادم',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
            });
        }

        // دالة اختبار الاتصال
        function testConnection(machineId) {
            Swal.fire({
                title: 'جاري اختبار الاتصال...',
                text: 'يرجى الانتظار',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });

            // استخدام GET request لاختبار الاتصال
            fetch("{{ route('machines.test-connection', ':id') }}".replace(':id', machineId), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'نجح الاتصال!',
                        text: data.message || 'تم اختبار الاتصال بنجاح',
                        icon: 'success',
                        confirmButtonText: 'موافق'
                    });
                } else {
                    Swal.fire({
                        title: 'فشل الاتصال!',
                        text: data.message || 'فشل في الاتصال بالماكينة',
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ أثناء اختبار الاتصال',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
            });
        }

        // دالة سحب البيانات
        function pullData(machineId) {
            Swal.fire({
                title: 'جاري سحب البيانات...',
                text: 'قد تستغرق هذه العملية بعض الوقت',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });

            // استخدام POST request لسحب البيانات
            fetch("{{ route('machines.pull-data', ':id') }}".replace(':id', machineId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'تم سحب البيانات!',
                        text: data.message || 'تم سحب البيانات بنجاح',
                        icon: 'success',
                        confirmButtonText: 'موافق'
                    });
                } else {
                    Swal.fire({
                        title: 'فشل سحب البيانات!',
                        text: data.message || 'فشل في سحب البيانات',
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ أثناء سحب البيانات',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
            });
        }

        // دالة ربط الماكينة
        function connectMachine(machineId) {
            Swal.fire({
                title: 'جاري ربط الماكينة...',
                text: 'يرجى الانتظار',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });

            // استخدام POST request لربط الماكينة
            fetch("{{ route('machines.connect', ':id') }}".replace(':id', machineId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'تم الربط!',
                        text: data.message || 'تم ربط الماكينة بنجاح',
                        icon: 'success',
                        confirmButtonText: 'موافق'
                    });
                } else {
                    Swal.fire({
                        title: 'فشل الربط!',
                        text: data.message || 'فشل في ربط الماكينة',
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ أثناء ربط الماكينة',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
            });
        }
    </script>
@endsection