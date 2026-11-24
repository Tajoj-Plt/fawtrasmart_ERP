@extends('stock::master')

@section('title', 'ورقة جرد جديدة')

@section('content')
    {{--
        لا سنةَ مالية ⇒ المستندُ يُحفَظ بلا نسبة. يُقال ذلك قبل الإنشاء لا بعده.
    --}}
    @include('partials.no-fiscal-year-notice')

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'ورقة جرد جديدة',
            'icon' => 'clipboard',
            'sub' => 'حدّد المستودع ونوع الجرد ووقته قبل إدخال الكمّيّات',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إدارة الجرد' => route('inventory_management.index'),
                'ورقة جديدة' => null,
            ],
            'actions' => '<button type="button" class="btn btn-outline-secondary" onclick="cancelForm()">'
                . '<i class="feather icon-x"></i> إلغاء</button>'
                . '<button type="submit" form="inventory-form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الورقة</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="inventory-form" action="{{ route('inventory.store') }}" method="POST">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> بيانات ورقة الجرد</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="storehouse_id" class="stk-req">المستودع</label>
                                <select id="storehouse_id" name="storehouse_id" class="form-control select2" required>
                                    <option value="">اختر المستودع…</option>
                                    @foreach ($storehouses as $storehouse)
                                        <option value="{{ $storehouse->id }}"
                                            {{ old('storehouse_id') == $storehouse->id ? 'selected' : '' }}>
                                            {{ $storehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-create-first
                                    :count="count($storehouses)"
                                    route="storehouse.create"
                                    permission="stock.warehouses.create"
                                    :plan="\App\Constants\planFeatures\StockPlanFeatures::STOREHOUSES"
                                    label="مستودعاً"
                                    hint="الجرد يقع على مستودع، ولا مستودعات مسجَّلة بعد." />
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="inventory_time" class="stk-req">تاريخ الجرد ووقته</label>
                                <input type="datetime-local" id="inventory_time" name="inventory_time" required
                                       class="form-control"
                                       value="{{ old('inventory_time', now()->format('Y-m-d\TH:i')) }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="calculation_type" class="stk-req">نوع الحساب</label>
                                <select id="calculation_type" name="calculation_type" class="form-control" required>
                                    @foreach (['undated' => 'غير مرتبط بالتاريخ', 'dated' => 'حسب تاريخ الجرد'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('calculation_type', 'undated') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="stk-help">«حسب التاريخ» يحسب الرصيد كما كان لحظة الجرد</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="type" class="stk-req">نوع الجرد</label>
                                <select id="type" name="type" class="form-control" required>
                                    @foreach (['full' => 'جرد شامل', 'partial' => 'جرد دوري', 'spot' => 'جرد مفاجئ', 'category' => 'جرد حسب الصنف'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('type', 'full') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="reference_number">الرقم المرجعي</label>
                                <input type="text" id="reference_number" name="reference_number"
                                       class="form-control stk-num" value="{{ old('reference_number') }}"
                                       placeholder="اختياري">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group mb-0">
                                <label for="note">ملاحظات</label>
                                <textarea id="note" name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- الأصناف تخصّ «الجرد حسب الصنف» وحده --}}
            <div class="card" id="categories-section" hidden>
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-tag"></i> الأصناف المشمولة</h4>
                    <div class="d-flex" style="gap:.4rem">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="select_all_categories">
                            <i class="feather icon-check-square"></i> تحديد الكل
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clear_all_categories">
                            <i class="feather icon-square"></i> إلغاء التحديد
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    @if (count($categories ?? []) > 0)
                        <div class="stk-picks">
                            @foreach ($categories as $category)
                                <label class="stk-pick">
                                    <input type="checkbox" name="categories[]" value="{{ $category->id }}"
                                           {{ is_array(old('categories')) && in_array($category->id, old('categories')) ? 'checked' : '' }}>
                                    <span class="stk-pick__box"><i class="feather icon-check"></i></span>
                                    <span class="stk-pick__label">{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-tag"></i></div>
                            <h5 class="stk-empty__title">لا أصناف</h5>
                            <p class="stk-empty__hint">أضف أصنافاً أولاً ليمكنك الجرد حسب الصنف</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
                    <button type="button" class="btn btn-outline-secondary" onclick="cancelForm()">
                        <i class="feather icon-x"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ الورقة
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function cancelForm() {
            Swal.fire({
                title: 'إلغاء الورقة؟',
                text: 'ستُفقد كل البيانات المُدخَلة.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، ألغِ',
                cancelButtonText: 'البقاء هنا',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) {
                    window.location.href = '{{ route('inventory_management.index') }}';
                }
            });
        }

        $(function () {
            if ($.fn.select2) {
                $('#inventory-form .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }

            const type = document.getElementById('type');
            const categories = document.getElementById('categories-section');

            $(type).on('change', function () {
                categories.hidden = this.value !== 'category';
            }).trigger('change');

            $('#select_all_categories').on('click', function () {
                $('#categories-section input[type="checkbox"]').prop('checked', true);
            });

            $('#clear_all_categories').on('click', function () {
                $('#categories-section input[type="checkbox"]').prop('checked', false);
            });

            let confirmed = false;

            $('#inventory-form').on('submit', function (e) {
                if (confirmed) return;
                e.preventDefault();

                Swal.fire({
                    title: 'حفظ ورقة الجرد؟',
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
