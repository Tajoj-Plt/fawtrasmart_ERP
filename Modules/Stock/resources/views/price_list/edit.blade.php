@extends('stock::master')

@section('title', 'تعديل ' . $price_list->name)

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تعديل قائمة الأسعار',
            'icon' => 'edit-2',
            'sub' => $price_list->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قوائم الأسعار' => route('price_list.index'),
                $price_list->name => route('price_list.show', $price_list->id),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('price_list.show', $price_list->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="editForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="editForm" action="{{ route('price_list.update', $price_list->id) }}" method="POST">
            @csrf
            @method('PUT')

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
                                       value="{{ old('name', $price_list->name) }}">
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label for="status">الحالة</label>
                                @php $current = old('status', $price_list->status); @endphp
                                <select id="status" name="status" class="form-control">
                                    <option value="0" {{ $current == 0 ? 'selected' : '' }}>نشط</option>
                                    <option value="1" {{ $current == 1 ? 'selected' : '' }}>موقوف</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
                    <button type="button" id="resetBtn" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </button>
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
            /* «إعادة تعيين» تُرجع القيم المحفوظة لا تُفرّغ الحقول */
            const original = @json(['name' => $price_list->name, 'status' => (string) $price_list->status]);

            let confirmed = false;

            $('#editForm').on('submit', function (e) {
                if (confirmed) return;
                e.preventDefault();

                if (! $('#name').val().trim()) {
                    Swal.fire({ icon: 'error', title: 'بيانات ناقصة', text: 'أدخل اسم قائمة الأسعار' });
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

            $('#resetBtn').on('click', function () {
                $('#name').val(original.name).removeClass('is-invalid');
                $('#status').val(original.status);
            });
        });
    </script>
@endsection
