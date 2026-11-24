@extends('rentalmanagement::realestate.master')

@php
    use Modules\RentalManagement\Models\TenantProfile as TP;
    use Modules\RentalManagement\Support\RealEstateVocabulary as TV;
    $editing = ! empty($tenant);
    $pageTitle = $editing ? 'بيانات المستأجر '.$tenant['trade_name'] : 'تسجيل مستأجر';
@endphp

@section('title', $pageTitle)

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $pageTitle,
            'icon' => $editing ? 'edit' : 'user-plus',
            'sub' => 'الهوية والجوال والبيانات الشخصية — وهي ما يظهر في حقل «المستأجر / العميل» عند إنشاء العقد',
            'crumbs' => ['الرئيسية' => url('/'), 'المستأجرون' => route('realestate.tenants.index'), $editing ? 'تعديل' : 'إضافة' => null],
            'actions' => '<a href="'.route('realestate.tenants.index').'" class="btn btn-outline-secondary" id="tenantCancel"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="tenantForm" class="btn btn-primary" id="tenantSubmit"><i class="feather icon-check"></i> حفظ</button>',
        ])

        <div class="alert alert-danger d-none" id="tenantErrors" role="alert"></div>

        <form id="tenantForm" novalidate enctype="multipart/form-data">
            <div class="card">
                <div class="card-body pb-50">
                    <div class="form-group mb-50">
                        <label class="d-block">نوع المستأجر</label>
                        @foreach (TP::TYPES as $value => $meta)
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="tenant_type_{{ $value }}" name="tenant_type" value="{{ $value }}" class="custom-control-input" @checked(($tenant['tenant_type'] ?? 'individual') === $value)>
                                <label class="custom-control-label" for="tenant_type_{{ $value }}">{{ $meta['label'] }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @unless ($editing)
            <div class="card">
                <div class="card-body pb-50">
                <div class="form-group" id="tenantModeWrap">
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="tenant_mode_new" name="mode" value="new" class="custom-control-input" checked>
                        <label class="custom-control-label" for="tenant_mode_new">مستأجر جديد</label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="tenant_mode_existing" name="mode" value="existing" class="custom-control-input">
                        <label class="custom-control-label" for="tenant_mode_existing">عميل موجود في ملفّ العملاء</label>
                    </div>
                </div>

                <div class="form-group" id="tenantExistingWrap" style="display:none">
                    <label for="tenant_new_client_id" class="stk-req">العميل</label>
                    @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'id' => 'tenant_new_client_id', 'selected' => null])
                </div>
                </div>
            </div>
            @endunless

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-user"></i> <span id="tenantMainTitle">البيانات الشخصية</span></h4></div>
                <div class="card-body">
                <div class="row">
                    <div class="col-md-4" id="tenantNameWrap">
                        <div class="form-group">
                            <label for="tenant_trade_name" class="stk-req" id="tenantNameLabel">اسم المستأجر الكامل</label>
                            <input type="text" id="tenant_trade_name" name="trade_name" class="form-control" maxlength="191">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="tenant_mobile" class="stk-req">رقم الجوال</label>
                            <input type="text" id="tenant_mobile" name="mobile" class="form-control" dir="ltr" maxlength="30" inputmode="tel" placeholder="05xxxxxxxx">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="tenant_phone">هاتف آخر</label>
                            <input type="text" id="tenant_phone" name="phone" class="form-control" dir="ltr" maxlength="30" inputmode="tel">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="tenant_email">البريد الإلكتروني</label>
                            <input type="email" id="tenant_email" name="email" class="form-control" dir="ltr" maxlength="191">
                        </div>
                    </div>
                    <div class="col-md-4 col-6 tenant-personal">
                        <div class="form-group">
                            <label for="tenant_birth_date">تاريخ الميلاد</label>
                            <input type="date" id="tenant_birth_date" name="birth_date" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4 col-6 tenant-personal">
                        <div class="form-group">
                            <label for="tenant_gender">الجنس</label>
                            <select id="tenant_gender" name="gender" class="form-control">
                                <option value="">—</option>
                                @foreach (TV::GENDERS as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-6 tenant-personal">
                        <div class="form-group">
                            <label for="tenant_nationality">الجنسية</label>
                            <input type="text" id="tenant_nationality" name="nationality" class="form-control" maxlength="100">
                        </div>
                    </div>
                    <div class="col-md-4 col-6 tenant-personal">
                        <div class="form-group">
                            <label for="tenant_marital_status">الحالة الاجتماعية</label>
                            <select id="tenant_marital_status" name="marital_status" class="form-control">
                                <option value="">—</option>
                                @foreach (TV::MARITAL_STATUSES as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-6 tenant-personal">
                        <div class="form-group">
                            <label for="tenant_family_members">عدد أفراد الأسرة</label>
                            <input type="number" min="0" max="100" id="tenant_family_members" name="family_members" class="form-control">
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <div class="card tenant-company" style="display:none">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-briefcase"></i> بيانات الشركة وممثّلها</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-6">
                            <div class="form-group">
                                <label for="tenant_commercial_registration" class="stk-req">السجلّ التجاري</label>
                                <input type="text" id="tenant_commercial_registration" name="commercial_registration" class="form-control" dir="ltr" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-group">
                                <label for="tenant_tax_number">الرقم الضريبي</label>
                                <input type="text" id="tenant_tax_number" name="tax_number" class="form-control" dir="ltr" maxlength="50">
                            </div>
                        </div>
                        @foreach ([['representative_name', 'اسم الممثّل / المفوَّض بالتوقيع', 150], ['representative_title', 'صفته', 100], ['representative_id_number', 'رقم هويته', 50], ['representative_phone', 'جواله', 30]] as [$field, $label, $max])
                            <div class="col-md-4 col-6">
                                <div class="form-group">
                                    <label for="tenant_{{ $field }}">{{ $label }}</label>
                                    <input type="text" id="tenant_{{ $field }}" name="{{ $field }}" class="form-control" maxlength="{{ $max }}" @if (in_array($field, ['representative_id_number', 'representative_phone'], true)) dir="ltr" @endif>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card tenant-personal">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-credit-card"></i> الهوية</h4></div>
                <div class="card-body">
                <div class="row">
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="tenant_id_type">نوع الهوية</label>
                            <select id="tenant_id_type" name="id_type" class="form-control">
                                @foreach (TV::ID_TYPES as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="tenant_id_number" class="stk-req">رقم الهوية</label>
                            <input type="text" id="tenant_id_number" name="id_number" class="form-control" dir="ltr" maxlength="50" inputmode="numeric">
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="tenant_id_issue_place">مكان الإصدار</label>
                            <input type="text" id="tenant_id_issue_place" name="id_issue_place" class="form-control" maxlength="100">
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="tenant_id_issue_date">تاريخ الإصدار</label>
                            <input type="date" id="tenant_id_issue_date" name="id_issue_date" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="tenant_id_expiry_date">تاريخ الانتهاء</label>
                            <input type="date" id="tenant_id_expiry_date" name="id_expiry_date" class="form-control">
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-map-pin"></i> <span class="tenant-personal">العمل و</span>العنوان</h4></div>
                <div class="card-body">
                <div class="row">
                    @foreach ([['job_title', 'المهنة', 150], ['employer', 'جهة العمل', 150], ['employer_phone', 'هاتف جهة العمل', 30], ['city', 'المدينة', 100], ['region', 'الحي / المنطقة', 100], ['street1', 'العنوان', 255]] as [$field, $label, $max])
                        <div class="col-md-4 col-6 @if (in_array($field, ['job_title', 'employer', 'employer_phone'], true)) tenant-personal @endif">
                            <div class="form-group">
                                <label for="tenant_{{ $field }}">{{ $label }}</label>
                                <input type="text" id="tenant_{{ $field }}" name="{{ $field }}" class="form-control" maxlength="{{ $max }}" @if ($field === 'employer_phone') dir="ltr" @endif>
                            </div>
                        </div>
                    @endforeach
                    <div class="col-md-4 col-6 tenant-personal">
                        <div class="form-group">
                            <label for="tenant_monthly_income">الدخل الشهري</label>
                            <input type="number" min="0" step="0.01" id="tenant_monthly_income" name="monthly_income" class="form-control" dir="ltr">
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-phone-call"></i> جهة اتصال للطوارئ</h4></div>
                <div class="card-body">
                <div class="row">
                    @foreach ([['emergency_name', 'الاسم', 150], ['emergency_relation', 'صلة القرابة', 50], ['emergency_phone', 'رقم الجوال', 30]] as [$field, $label, $max])
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tenant_{{ $field }}">{{ $label }}</label>
                                <input type="text" id="tenant_{{ $field }}" name="{{ $field }}" class="form-control" maxlength="{{ $max }}" @if ($field === 'emergency_phone') dir="ltr" @endif>
                            </div>
                        </div>
                    @endforeach
                </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-paperclip"></i> المرفقات</h4></div>
                <div class="card-body">
                    @include('rentalmanagement::realestate.components.files', ['name' => 'attachments', 'label' => 'صورة الهوية أو السجلّ التجاري وما يلزم من مستندات', 'model' => $profile ?? null])
                </div>
            </div>

            @unless ($editing)
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-flag"></i> المتابعة</h4></div>
                <div class="card-body">
                    <div class="row">
                        @isset($units)
                            @php $tenantUnitBuildings = $units->map(fn ($u) => $u->building)->filter()->unique('id')->sortBy('name_ar'); @endphp
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tenant_building_filter">العقار</label>
                                    <select id="tenant_building_filter" class="form-control select2">
                                        <option value="">كل العقارات</option>
                                        @foreach ($tenantUnitBuildings as $tenantUnitBuilding) <option value="{{ $tenantUnitBuilding->id }}">{{ $tenantUnitBuilding->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tenant_unit_id">الوحدة محلّ الاهتمام</label>
                                    <select id="tenant_unit_id" name="unit_id" class="form-control select2">
                                        <option value="">اختر الوحدة…</option>
                                        @foreach ($units as $unit) <option value="{{ $unit->id }}" data-building="{{ $unit->building_id }}">{{ $unit->full_label }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        @endisset
                        @isset($users)
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tenant_assigned_user_id">المسؤول عن المتابعة</label>
                                    <select id="tenant_assigned_user_id" name="assigned_user_id" class="form-control select2">
                                        <option value="">اختر مستخدماً…</option>
                                        @foreach ($users as $user) <option value="{{ $user->id }}">{{ $user->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        @endisset
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label for="tenant_notes">ملاحظات</label>
                                <textarea id="tenant_notes" name="notes" rows="2" class="form-control" maxlength="3000"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endunless

            <div class="d-flex justify-content-end mb-2" style="gap:.5rem">
                <a href="{{ route('realestate.tenants.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                <button type="submit" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.unit-filter')
    <script>
        $(function () {
            reUnitFilter('#tenant_building_filter', '#tenant_unit_id');
            const $form = $('#tenantForm');
            const $errors = $('#tenantErrors');
            const tenant = @json($tenant ?? null);
            const storeUrl = @json(route('realestate.tenants.store'));
            const showUrl = @json(route('realestate.tenants.show', '__ID__'));
            const indexUrl = @json(route('realestate.tenants.index'));
            // فُتحت الصفحة من نموذج العقد: بعد الحفظ يُبلَّغ النموذج وتُغلق هذه الصفحة
            const fromContract = @json(request('return_to') === 'contract');

            if ($.fn.select2) $form.find('.select2').select2({ dir: 'rtl', width: '100%' });

            function fail(messages) {
                $errors.removeClass('d-none').empty().append($('<ul class="mb-0 ps-1"></ul>').append([].concat(messages).map(m => $('<li></li>').text(m))));
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function fill(client) {
                $form.find('[name]').each(function () {
                    if (['mode', 'client_id', 'tenant_type'].includes(this.name) || this.type === 'file' || this.type === 'checkbox' || client[this.name] === undefined) return;
                    $(this).val(client[this.name] ?? '').trigger('change.select2');
                });

                if (client.tenant_type) {
                    $('input[name="tenant_type"][value="' + client.tenant_type + '"]', $form).prop('checked', true);
                    syncType();
                }
            }

            const phonePattern = /^\+?[0-9][0-9 ]{5,24}$/;

            function isCompany() { return $('input[name="tenant_type"]:checked', $form).val() === 'company'; }

            // الشركة: تُخفى الحقول الشخصية والهوية، ويظهر السجلّ التجاري والممثّل
            function syncType() {
                const company = isCompany();
                $('.tenant-personal').toggle(!company);
                $('.tenant-company').toggle(company);
                $('#tenantNameLabel').text(company ? 'اسم الشركة / المؤسسة' : 'اسم المستأجر الكامل');
                $('#tenantMainTitle').text(company ? 'بيانات التواصل' : 'البيانات الشخصية');
            }

            function syncMode() {
                const existing = !tenant && $('#tenant_mode_existing').is(':checked');
                $('#tenantExistingWrap').toggle(existing);
                $('#tenantNameWrap').toggle(!existing);
            }

            // عميلٌ موجود: تُجلب بياناته المحفوظة لتُستكمل لا لتُكتب من الصفر
            $('#tenant_new_client_id').on('change', function () {
                if (!this.value || tenant) return;
                $.ajax({ url: showUrl.replace('__ID__', this.value), headers: { Accept: 'application/json' } }).done(fill);
            });

            $('input[name="mode"]', $form).on('change', syncMode);
            $('input[name="tenant_type"]', $form).on('change', syncType);
            syncMode();
            syncType();
            if (tenant) fill(tenant);

            $form.on('submit', function (e) {
                e.preventDefault();
                $errors.addClass('d-none');

                const existing = !tenant && $('#tenant_mode_existing').is(':checked');

                if (existing && !$('#tenant_new_client_id').val()) return fail('اختر العميل أولاً.');
                const company = isCompany();

                if (!existing && !$('#tenant_trade_name').val().trim()) return fail(company ? 'اكتب اسم الشركة.' : 'اكتب اسم المستأجر.');
                if (!$('#tenant_mobile').val().trim()) return fail('اكتب رقم جوال المستأجر.');
                if (!phonePattern.test($('#tenant_mobile').val().trim())) return fail('رقم الجوال يقبل الأرقام والمسافات وعلامة + في أوّله فقط.');
                if (company && !$('#tenant_commercial_registration').val().trim()) return fail('اكتب السجلّ التجاري للشركة.');
                if (!company && !$('#tenant_id_number').val().trim()) return fail('اكتب رقم هوية المستأجر.');

                const issue = $('#tenant_id_issue_date').val(), expiry = $('#tenant_id_expiry_date').val();
                if (!company && issue && expiry && expiry <= issue) return fail('تاريخ انتهاء الهوية يجب أن يكون بعد تاريخ إصدارها.');

                const $buttons = $('button[type="submit"]').prop('disabled', true);
                // FormData لا serialize: النموذج يحمل مرفقات
                const data = new FormData($form[0]);
                data.append('_token', @json(csrf_token()));
                if (tenant) data.append('_method', 'PUT');

                $.ajax({ url: tenant ? showUrl.replace('__ID__', tenant.id) : storeUrl, method: 'POST', data: data, processData: false, contentType: false, headers: { Accept: 'application/json' } })
                    .done(function (response) {
                        // نموذج العقد في تبويبه يلتقط هذا الحدث ويختار المستأجر في الحقل
                        try { localStorage.setItem('re_tenant_saved', JSON.stringify(Object.assign({ at: Date.now() }, response.client || {}))); } catch (ignore) {}

                        Swal.fire({ icon: 'success', title: response.message || 'تم', timer: 1200, showConfirmButton: false }).then(function () {
                            if (fromContract) window.close();
                            window.location.href = response.redirect || indexUrl;
                        });
                    })
                    .fail(function (xhr) {
                        const body = xhr.responseJSON || {};
                        fail(body.errors ? Object.values(body.errors).flat() : [body.message || 'حدث خطأ غير متوقّع']);
                    })
                    .always(() => $buttons.prop('disabled', false));
            });
        });
    </script>
@endsection
