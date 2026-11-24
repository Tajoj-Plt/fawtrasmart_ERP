@extends('rentalmanagement::realestate.master')

@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $editing = $owner->exists;
    $pageTitle = $editing ? 'تعديل المالك: '.$owner->name : 'إضافة مالك';
@endphp

@section('title', $pageTitle)

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $pageTitle,
            'icon' => $editing ? 'edit' : 'user-plus',
            'sub' => 'بيانات المالك واتفاقية إدارة أملاكه — وهو من يُختار في المباني والعقود',
            'crumbs' => ['الرئيسية' => url('/'), 'الملّاك' => route('realestate.owners.index'), $editing ? 'تعديل' : 'إضافة' => null],
            'actions' => '<a href="'.($editing ? route('realestate.owners.show', $owner->id) : route('realestate.owners.index')).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="ownerForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        <form id="ownerForm" method="POST" novalidate data-re-validate enctype="multipart/form-data"
              action="{{ $editing ? route('realestate.owners.update', $owner->id) : route('realestate.owners.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-user"></i> بيانات المالك</h4>
                    <span class="badge badge-light-secondary stk-num">{{ $editing ? $owner->code : $code }}</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="name" class="stk-req">اسم المالك</label>
                                <input type="text" id="name" name="name" class="form-control" data-required="1" value="{{ old('name', $owner->name) }}" maxlength="191">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="owner_type" class="stk-req">نوع المالك</label>
                                <select id="owner_type" name="owner_type" class="form-control">
                                    @foreach (V::OWNER_TYPES as $value => $meta) <option value="{{ $value }}" @selected(old('owner_type', $owner->owner_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status" class="stk-req">الحالة</label>
                                <select id="status" name="status" class="form-control">
                                    @foreach (V::OWNER_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(old('status', $owner->status) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="mobile" class="stk-req">رقم الجوال</label>
                                <input type="text" id="mobile" name="mobile" class="form-control" dir="ltr" data-required="1" inputmode="tel" value="{{ old('mobile', $owner->mobile) }}" maxlength="30" placeholder="05xxxxxxxx">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="phone">هاتف آخر</label>
                                <input type="text" id="phone" name="phone" class="form-control" dir="ltr" inputmode="tel" value="{{ old('phone', $owner->phone) }}" maxlength="30">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="email">البريد الإلكتروني</label>
                                <input type="email" id="email" name="email" class="form-control" dir="ltr" value="{{ old('email', $owner->email) }}" maxlength="191">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="id_type">نوع الهوية</label>
                                <select id="id_type" name="id_type" class="form-control">
                                    <option value="">—</option>
                                    @foreach (V::ID_TYPES as $value => $meta) <option value="{{ $value }}" @selected(old('id_type', $owner->id_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="id_number">رقم الهوية / السجل</label>
                                <input type="text" id="id_number" name="id_number" class="form-control" dir="ltr" value="{{ old('id_number', $owner->id_number) }}" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="nationality">الجنسية</label>
                                <input type="text" id="nationality" name="nationality" class="form-control" value="{{ old('nationality', $owner->nationality) }}" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6" id="maritalWrap">
                            <div class="form-group">
                                <label for="marital_status">الحالة الاجتماعية</label>
                                <select id="marital_status" name="marital_status" class="form-control">
                                    <option value="">—</option>
                                    @foreach (V::MARITAL_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(old('marital_status', $owner->marital_status) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8 col-sm-12">
                            <div class="form-group">
                                <label for="address">العنوان</label>
                                <input type="text" id="address" name="address" class="form-control" value="{{ old('address', $owner->address) }}" maxlength="255">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-percent"></i> اتفاقية إدارة الأملاك</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="agreement_type" class="stk-req">نوع الاتفاق</label>
                                <select id="agreement_type" name="agreement_type" class="form-control">
                                    @foreach (V::AGREEMENT_TYPES as $value => $meta) <option value="{{ $value }}" @selected(old('agreement_type', $owner->agreement_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="agreement_value" class="stk-req" id="agreementValueLabel">النسبة %</label>
                                <input type="number" step="0.01" min="0" id="agreement_value" name="agreement_value" class="form-control" data-required="1" value="{{ old('agreement_value', $owner->agreement_value ?: '') }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="commission_basis" class="stk-req">أساس احتساب العمولة</label>
                                <select id="commission_basis" name="commission_basis" class="form-control">
                                    @foreach (V::COMMISSION_BASES as $value => $meta) <option value="{{ $value }}" @selected(old('commission_basis', $owner->commission_basis) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0" role="note" id="agreementHint">
                        <i class="feather icon-info"></i>
                        <span id="agreementHintText"></span>
                    </div>
                </div>
            </div>

            {{-- تسجيل المالك في ضريبة القيمة المضافة: مورِّد خدمة التأجير هو المالك لا المكتب --}}
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> ضريبة القيمة المضافة</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="stk-switch" for="is_vat_registered">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">المالك مسجَّل في ضريبة القيمة المضافة</span>
                                        <span class="stk-switch__hint">المسجَّل تُحصَّل ضريبة إيجاره التجاري من المستأجر وتُقيَّد في حسابه الجاري ليورّدها هو. غير المسجَّل لا تُحصَّل له ضريبة.</span>
                                    </span>
                                    <input type="checkbox" id="is_vat_registered" name="is_vat_registered" value="1" @checked(old('is_vat_registered', $owner->is_vat_registered))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6" id="taxNumberWrap">
                            <div class="form-group">
                                <label for="tax_number" class="stk-req">الرقم الضريبي</label>
                                <input type="text" id="tax_number" name="tax_number" class="form-control" dir="ltr" inputmode="numeric" value="{{ old('tax_number', $owner->tax_number) }}" maxlength="50" placeholder="3xxxxxxxxxxxxx3">
                                <small class="text-muted">يُطبع في إيصال المستأجر التجاري بدل رقم المنشأة.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-credit-card"></i> بيانات التحويل البنكي والملاحظات</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="bank_name">اسم البنك</label>
                                <input type="text" id="bank_name" name="bank_name" class="form-control" value="{{ old('bank_name', $owner->bank_name) }}" maxlength="191">
                            </div>
                        </div>
                        <div class="col-md-8 col-sm-6">
                            <div class="form-group">
                                <label for="iban">رقم الآيبان</label>
                                <input type="text" id="iban" name="iban" class="form-control" dir="ltr" value="{{ old('iban', $owner->iban) }}" maxlength="50">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label for="notes">ملاحظات</label>
                                <textarea id="notes" name="notes" rows="3" class="form-control" maxlength="3000">{{ old('notes', $owner->notes) }}</textarea>
                            </div>
                        </div>
                        <div class="col-12 mt-1">
                            @include('rentalmanagement::realestate.components.files', ['name' => 'attachments', 'label' => 'مرفقات المالك: صورة الهوية أو السجلّ التجاري، اتفاقية الإدارة، الوكالة', 'model' => $owner])
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            function syncAgreement() {
                const percentage = $('#agreement_type').val() === 'percentage';
                const due = $('#commission_basis').val() === 'due';
                const value = $('#agreement_value');

                $('#agreementValueLabel').text(percentage ? 'النسبة %' : 'المبلغ الثابت لكل قسط');
                value.attr('max', percentage ? 100 : null);

                $('#agreementHintText').text(
                    (percentage
                        ? 'تُخصم النسبة من صافي الإيجار (بلا ضريبة) عند كل دفعة'
                        : 'يُخصم المبلغ الثابت عن كل قسط إيجار، ويُوزَّع بنسبة ما سُدِّد منه')
                    + (due
                        ? '، وعمولة القسط تُحتسب على المستحق كاملاً وتُستوفى من أول دفعات القسط.'
                        : '، وتُحتسب على المحصَّل فعلاً لا على المستحق.')
                    + ' وما يبقى بعد العمولة يُقيَّد في الحساب الجاري للمالك.'
                );
            }

            $('#agreement_type, #commission_basis').on('change', syncAgreement);
            syncAgreement();

            // الشركة لا حالة اجتماعية لها
            function syncOwnerType() {
                const company = $('#owner_type').val() === 'company';
                $('#maritalWrap').toggle(!company);
                if (company) $('#marital_status').val('');
            }
            $('#owner_type').on('change', syncOwnerType);
            syncOwnerType();

            function syncVat() {
                const registered = $('#is_vat_registered').is(':checked');
                $('#taxNumberWrap').toggle(registered);
                $('#tax_number').attr('data-required', registered ? '1' : null);
            }

            $('#is_vat_registered').on('change', syncVat);
            syncVat();
        });
    </script>
@endsection
