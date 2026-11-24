@extends('stock::master')

@section('title', 'قائمة أسعار جديدة')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'قائمة أسعار جديدة',
            'icon' => 'tag',
            'sub' => 'اسم القائمة وحالتها — تُضاف السلع إليها بعد الحفظ',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قوائم الأسعار' => route('price_list.index'),
                'جديدة' => null,
            ],
            'actions' => '<a href="' . route('price_list.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="createForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ القائمة</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="createForm" action="{{ route('price_list.store') }}" method="POST">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> بيانات القائمة</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group mb-0">
                                <label for="name" class="stk-req">اسم القائمة</label>
                                <input type="text" id="name" name="name" required
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" placeholder="مثال: أسعار الجملة">
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control">
                                    {{-- 0 = نشط في هذا الجدول، خلافاً لما يوحي به الاسم --}}
                                    <option value="0" {{ old('status') == 0 ? 'selected' : '' }}>نشط</option>
                                    <option value="1" {{ old('status') == 1 ? 'selected' : '' }}>موقوف</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
                    <button type="button" id="resetBtn" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> تفريغ
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ القائمة
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
            let confirmed = false;

            $('#createForm').on('submit', function (e) {
                if (confirmed) return;
                e.preventDefault();

                if (! $('#name').val().trim()) {
                    Swal.fire({ icon: 'error', title: 'بيانات ناقصة', text: 'أدخل اسم قائمة الأسعار' });
                    $('#name').addClass('is-invalid').focus();
                    return;
                }

                Swal.fire({
                    title: 'حفظ القائمة؟',
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

            $('#resetBtn').on('click', function () {
                Swal.fire({
                    title: 'تفريغ النموذج؟',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، فرّغ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (result.isConfirmed) document.getElementById('createForm').reset();
                });
            });
        });
    </script>
@endsection
