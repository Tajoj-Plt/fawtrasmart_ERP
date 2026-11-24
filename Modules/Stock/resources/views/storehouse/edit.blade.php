@extends('stock::master')

@section('title', 'تعديل ' . $storehouse->name)

@section('content')
    @php
        /* البنية نفسها التي في شاشة الإضافة: ثلاثة نطاقات متطابقة. */
        $scopes = [
            ['key' => 'view', 'prefix' => 'v', 'select' => 'view_permissions', 'label' => 'عرض المستودع'],
            ['key' => 'create', 'prefix' => 'c', 'select' => 'crate_invoices_permissions', 'label' => 'إنشاء فاتورة'],
            ['key' => 'edit', 'prefix' => 'e', 'select' => 'edit_stock_permissions', 'label' => 'تعديل المستودع'],
        ];

        $audiences = [
            0 => 'الكل',
            1 => 'موظف محدَّد',
            2 => 'دور وظيفي محدَّد',
            3 => 'فرع محدَّد',
        ];
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تعديل المستودع',
            'icon' => 'edit-2',
            'sub' => $storehouse->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المستودعات' => route('storehouse.index'),
                $storehouse->name => route('storehouse.show', $storehouse->id),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('storehouse.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="products_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="products_form" action="{{ route('storehouse.update', $storehouse->id) }}" method="POST">
            @csrf

            <div class="row">
                <div class="col-lg-8 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-info"></i> بيانات المستودع</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="name" class="stk-req">اسم المستودع</label>
                                        <input type="text" id="name" name="name" required
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name', $storehouse->name) }}">
                                        @error('name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="status">الحالة</label>
                                        <select id="status" name="status" class="form-control">
                                            @php $currentStatus = old('status', $storehouse->status); @endphp
                                            @foreach (['active' => 'نشط', 'inactive' => 'غير نشط', 'stopped' => 'موقوف'] as $value => $label)
                                                <option value="{{ $value }}" {{ $currentStatus === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="branch_id" class="stk-req">الفرع</label>
                                        @if ($canSelectBranch)
                                            <select id="branch_id" name="branch_id" required
                                                    class="form-control select2 @error('branch_id') is-invalid @enderror">
                                                <option value="">اختر الفرع…</option>
                                                @foreach ($selectableBranches as $branch)
                                                    <option value="{{ $branch->id }}"
                                                        {{ (int) old('branch_id', $storehouse->branch_id) === $branch->id ? 'selected' : '' }}>
                                                        {{ $branch->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('branch_id')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <x-create-first
                                                :count="count($selectableBranches)"
                                                route="branches.create"
                                                label="فرعاً"
                                                hint="المستودع يتبع فرعاً، ولا فروع مسجَّلة بعد." />
                                        @else
                                            <input type="hidden" name="branch_id" value="{{ $storehouse->branch_id }}">
                                            <input type="text" class="form-control" disabled
                                                   value="{{ optional($storehouse->branch)->name ?? 'بلا فرع' }}">
                                        @endif
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="shipping_address">عنوان الشحن</label>
                                        <textarea id="shipping_address" name="shipping_address" class="form-control"
                                                  rows="2">{{ old('shipping_address', $storehouse->shipping_address) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-star"></i> نوع المستودع</h4>
                        </div>
                        <div class="card-body">
                            <label class="stk-switch">
                                <input type="checkbox" name="major" value="1"
                                       {{ old('major', $storehouse->major) ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">مستودع رئيسي</span>
                                    <span class="stk-switch__hint">المستودع الافتراضي للعمليات التي لا تحدّد مستودعاً</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ الصلاحيات ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-shield"></i> الصلاحيات</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach ($scopes as $scope)
                            @php $selected = (string) old($scope['select'], $storehouse->{$scope['select']} ?? 0); @endphp

                            <div class="col-lg-4 col-12">
                                <div class="stk-scope" data-scope="{{ $scope['key'] }}">
                                    <div class="form-group">
                                        <label for="{{ $scope['select'] }}">{{ $scope['label'] }}</label>
                                        <select id="{{ $scope['select'] }}" name="{{ $scope['select'] }}"
                                                class="form-control stk-scope__audience">
                                            @foreach ($audiences as $value => $label)
                                                <option value="{{ $value }}" {{ $selected === (string) $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group mb-0 stk-scope__target" data-audience="1" hidden>
                                        <label>الموظف</label>
                                        <select class="form-control" name="{{ $scope['prefix'] }}_employee_id">
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}"
                                                    {{ old($scope['prefix'] . '_employee_id', $storehouse->employee_id) == $employee->id ? 'selected' : '' }}>
                                                    {{ $employee->full_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group mb-0 stk-scope__target" data-audience="2" hidden>
                                        <label>الدور</label>
                                        <select class="form-control" name="{{ $scope['prefix'] }}_functional_role_id">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}"
                                                    {{ old($scope['prefix'] . '_functional_role_id', $storehouse->functional_role_id) == $role->id ? 'selected' : '' }}>
                                                    {{ $role->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group mb-0 stk-scope__target" data-audience="3" hidden>
                                        <label>الفرع</label>
                                        <select class="form-control" name="{{ $scope['prefix'] }}_branch_id">
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}"
                                                    {{ old($scope['prefix'] . '_branch_id', $storehouse->branch_id) == $branch->id ? 'selected' : '' }}>
                                                    {{ $branch->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="stk-form-foot">
                    <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
                    <a href="{{ route('storehouse.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ التعديلات
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            /* القائمة التابعة تظهر حسب اختيار «لمن؟» — و`trigger` عند التحميل
               يكشف المحفوظ منها فوراً */
            $('.stk-scope__audience').on('change', function () {
                const scope = $(this).closest('.stk-scope');
                const value = this.value;

                scope.find('.stk-scope__target').each(function () {
                    this.hidden = String($(this).data('audience')) !== value;
                });
            }).trigger('change');

            let confirmed = false;

            $('#products_form').on('submit', function (e) {
                if (confirmed) return;
                e.preventDefault();

                if (! $('#name').val().trim()) {
                    Swal.fire({ icon: 'error', title: 'بيانات ناقصة', text: 'أدخل اسم المستودع' });
                    $('#name').addClass('is-invalid').focus();
                    return;
                }

                Swal.fire({
                    title: 'حفظ التعديلات؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احفظ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (! result.isConfirmed) return;
                    confirmed = true;
                    Swal.fire({
                        title: 'جارٍ الحفظ…',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading(),
                    });
                    this.submit();
                });
            });
        });
    </script>
@endsection
