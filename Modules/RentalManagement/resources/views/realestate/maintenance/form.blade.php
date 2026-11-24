@extends('rentalmanagement::realestate.master')

@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $editing = $item->exists;
    $pageTitle = $editing ? 'تعديل طلب الصيانة '.$item->code : 'طلب صيانة جديد';
@endphp

@section('title', $pageTitle)

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $pageTitle,
            'icon' => $editing ? 'edit' : 'plus-circle',
            'sub' => 'بلاغٌ على وحدةٍ أو على العقار نفسه (المرافق المشتركة)',
            'crumbs' => ['الرئيسية' => url('/'), 'طلبات الصيانة' => route('realestate.maintenance.index'), $editing ? 'تعديل' : 'إضافة' => null],
            'actions' => '<a href="'.($editing ? route('realestate.maintenance.show', $item->id) : route('realestate.maintenance.index')).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="maintenanceForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        <form id="maintenanceForm" method="POST" enctype="multipart/form-data" novalidate data-re-validate
              action="{{ $editing ? route('realestate.maintenance.update', $item->id) : route('realestate.maintenance.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> البلاغ</h4>
                    <span class="badge badge-light-secondary stk-num">{{ $editing ? $item->code : $code }}</span>
                </div>
                <div class="card-body">
                    @php($scope = old('scope', $item->exists ? $item->scope : (request('scope') === 'building' || (! $item->unit_id && $item->building_id) ? 'building' : 'unit')))
                    <div class="form-group">
                        <label class="stk-req d-block">نوع الصيانة</label>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="scope_unit" name="scope" value="unit" class="custom-control-input" @checked($scope === 'unit')>
                            <label class="custom-control-label" for="scope_unit"><i class="feather icon-grid"></i> صيانة خاصة بوحدة</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="scope_building" name="scope" value="building" class="custom-control-input" @checked($scope === 'building')>
                            <label class="custom-control-label" for="scope_building"><i class="feather icon-home"></i> صيانة خاصة بالمبنى</label>
                        </div>
                        <small class="text-muted d-block">صيانة المبنى للمرافق المشتركة: المصاعد والواجهات والسطح والخزّانات ونحوها.</small>
                    </div>
                    <div class="row">
                        @php($selectedUnit = old('unit_id', $item->unit_id))
                        @php($selectedBuilding = old('building_id', $item->building_id ?: optional($units->firstWhere('id', (int) $selectedUnit))->building_id))
                        <div class="col-md-6" id="buildingField">
                            <div class="form-group">
                                <label for="building_id" class="stk-req">العقار / المبنى</label>
                                <select id="building_id" name="building_id" class="form-control select2">
                                    <option value="">اختر العقار…</option>
                                    @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) $selectedBuilding === (string) $building->id)>{{ $building->name }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6" id="unitField">
                            <div class="form-group">
                                <label for="unit_id" class="stk-req">الوحدة</label>
                                <select id="unit_id" name="unit_id" class="form-control select2" data-selected="{{ $selectedUnit }}">
                                    <option value="">اختر العقار أولاً…</option>
                                </select>
                                <small class="text-muted" id="unitHint">تظهر وحدات العقار المختار، واختيار الوحدة يحدّد عقدها الساري تلقائياً.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="title" class="stk-req">عنوان البلاغ</label>
                                <input type="text" id="title" name="title" class="form-control" data-required="1" value="{{ old('title', $item->title) }}" maxlength="191" placeholder="تسريب مياه في المطبخ…">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="category" class="stk-req">التصنيف</label>
                                <select id="category" name="category" class="form-control">
                                    @foreach (V::MAINTENANCE_CATEGORIES as $value => $meta) <option value="{{ $value }}" @selected(old('category', $item->category) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="priority" class="stk-req">الأولوية</label>
                                <select id="priority" name="priority" class="form-control">
                                    @foreach (V::PRIORITIES as $value => $meta) <option value="{{ $value }}" @selected(old('priority', $item->priority) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="description">وصف المشكلة</label>
                                <textarea id="description" name="description" rows="3" class="form-control" maxlength="5000">{{ old('description', $item->description) }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reported_by_client_id">المبلِّغ (من ملفّ العملاء)</label>
                                @include('rentalmanagement::realestate.components.client-select', ['name' => 'reported_by_client_id', 'clients' => $clients, 'selected' => $item->reported_by_client_id, 'placeholder' => 'اختر المبلِّغ…'])
                                <small class="text-muted d-block">إن تُرك فارغاً مع تحديد وحدة، يُعتمد مستأجرها الحالي.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-user-check"></i> الإسناد والجدولة</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="assigned_to_user_id">المسؤول عن المتابعة</label>
                                <select id="assigned_to_user_id" name="assigned_to_user_id" class="form-control select2">
                                    <option value="">اختر مستخدماً…</option>
                                    @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) old('assigned_to_user_id', $item->assigned_to_user_id) === (string) $user->id)>{{ $user->name }}</option> @endforeach
                                </select>
                                @unless ($editing) <small class="text-muted">الإسناد عند الفتح ينقل الطلب مباشرةً إلى «{{ V::label(V::MAINTENANCE_STATUSES, 'waiting') }}».</small> @endunless
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="contractor_supplier_id">المقاول / المورّد</label>
                                <select id="contractor_supplier_id" name="contractor_supplier_id" class="form-control select2">
                                    <option value="">اختر مورّداً…</option>
                                    @foreach ($suppliers as $supplier) <option value="{{ $supplier->id }}" @selected((string) old('contractor_supplier_id', $item->contractor_supplier_id) === (string) $supplier->id)>{{ $supplier->trade_name }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="scheduled_at">موعد الزيارة</label>
                                <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="form-control" dir="ltr" value="{{ old('scheduled_at', $item->scheduled_at?->format('Y-m-d\TH:i')) }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="estimated_cost">التكلفة التقديرية <small class="text-muted">({{ \Modules\RentalManagement\Support\RealEstateMoney::symbol() }})</small></label>
                                <input type="number" step="0.01" min="0" id="estimated_cost" name="estimated_cost" class="form-control" value="{{ old('estimated_cost', $item->estimated_cost) }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="charge_to" class="stk-req">التكلفة على</label>
                                <select id="charge_to" name="charge_to" class="form-control">
                                    @foreach (V::CHARGE_TO as $value => $meta) <option value="{{ $value }}" @selected(old('charge_to', $item->charge_to) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                                <small class="text-muted">المالك: تُخصم من حسابه الجاري (وفي المبنى المملوك للمنشأة تُقيَّد مصروفاً عليها). المستأجر: يُضاف قسط «صيانة» إلى استحقاقات عقده. الشركة: مصروف صيانة عقارات.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-paperclip"></i> المرفقات</h4></div>
                <div class="card-body">
                    @include('rentalmanagement::realestate.components.files', ['name' => 'attachments', 'label' => 'مرفقات الطلب', 'model' => $item])
                    <small class="text-muted">صور قبل/بعد وفواتير.</small>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            // الوحدات تتبع العقار: تُعاد تعبئة القائمة عند كل تغيير للعقار
            const units = @json($units->map(fn ($u) => ['id' => $u->id, 'building_id' => $u->building_id, 'label' => trim($u->type_label.' '.$u->unit_no)])->values());
            const $unit = $('#unit_id'), $building = $('#building_id');

            function fillUnits(selected) {
                const buildingId = $building.val();
                const list = units.filter(u => String(u.building_id) === String(buildingId));

                $unit.empty().append(new Option(!buildingId ? 'اختر العقار أولاً…' : (list.length ? 'اختر وحدة…' : 'لا وحدات في هذا العقار'), ''));
                list.forEach(u => $unit.append(new Option(u.label, u.id)));
                $unit.prop('disabled', !list.length).val(selected && list.some(u => String(u.id) === String(selected)) ? String(selected) : '').trigger('change');
            }

            $building.on('change', () => fillUnits(null));
            fillUnits($unit.data('selected'));

            function syncScope() {
                $('#unitField').toggle(!$('#scope_building').is(':checked'));
            }

            $('input[name="scope"]').on('change', syncScope);
            syncScope();

            $('#maintenanceForm').on('submit', function (e) {
                const building = $('#scope_building').is(':checked');
                if ($('#building_id').val() && (building || $('#unit_id').val())) return;

                e.preventDefault();
                e.stopImmediatePropagation();
                Swal.fire({ icon: 'warning', title: 'حقول مطلوبة', text: !$('#building_id').val() ? 'اختر العقار أولاً.' : 'اختر الوحدة محلّ الصيانة.' });
            });
        });
    </script>
@endsection
