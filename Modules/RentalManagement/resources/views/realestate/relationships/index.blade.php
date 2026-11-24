@extends('rentalmanagement::realestate.master')

@section('title', 'اهتمامات العملاء')

@section('content')
    @php
        use Modules\RentalManagement\Models\CustomerRelationship as Rel;
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        $hasFilters = request()->hasAny(['assigned_user_id', 'building_id']);
        $canWrite = canAny(['realestate.relationships.create', 'realestate.relationships.edit']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.relationships.create')
                <button type="button" class="btn btn-primary" id="addRelationship"><i class="feather icon-plus"></i> إضافة اهتمام</button>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'اهتمامات العملاء',
            'icon' => 'users',
            'sub' => 'من يهتم بماذا: عميل مهتمّ بوحدة أو مبنى أو عرض، ومن يتابعه',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'اهتمامات العملاء' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('rentalmanagement::realestate.components.kpis', ['items' => [
            ['label' => 'عملاء مهتمّون', 'value' => number_format($stats['clients']), 'icon' => 'users', 'tone' => 'info'],
            ['label' => 'بحاجة إلى متابعة', 'value' => number_format($stats['follow_up']), 'icon' => 'phone-call', 'tone' => $stats['follow_up'] ? 'warning' : 'success'],
            ['label' => 'طلبات عملاء مفتوحة', 'value' => number_format($stats['requests']), 'icon' => 'search', 'tone' => 'primary'],
            ['label' => 'مستأجرون حاليون', 'value' => number_format($stats['tenants']), 'icon' => 'key', 'tone' => 'success'],
        ]])

        <p class="d-none"><i class="feather icon-info"></i>
            <strong>من يهتم بماذا:</strong> عميل مهتمّ بوحدة أو مبنى أو عرض، ومن يتابعه. يُختار العميل من ملفّ العملاء المركزي (لا يُنشأ هنا)، ويُضاف تلقائياً عند تسجيل معاينةٍ أو طلبٍ له.
            المستأجرون الحاليون (من العقود) في تبويبهم كي لا يغمروا القائمة.
        </p>

        <div class="re-rel-hint">
            <i class="feather icon-info text-info mt-25"></i>
            <div>
                <strong>ما هذه الشاشة؟</strong> سجلّ <strong>اهتمامات العملاء</strong>: من يهتمّ بأي وحدة أو عقار أو عرض، ومن الموظف الذي يتابعه.
                العميل يُختار من ملفّ العملاء المركزي ولا يُنشأ هنا. المستأجرون الحاليون (من العقود) في تبويبهم كي لا يغمروا القائمة.
                أمّا <a href="{{ Route::has('realestate.listings.requests') ? route('realestate.listings.requests') : '#' }}">طلبات العملاء</a> فهي ما يبحث عنه العميل (شقة، أرض…) بميزانيته.
            </div>
        </div>

        <div class="re-rel-tabs nav" id="interestTabs">
            @foreach (['interests' => ['الاهتمامات', $stats['clients'] ?? null], 'tenants' => ['مستأجرون حاليون', $stats['tenants'] ?? null], 'all' => ['الكل', null]] as $tabKey => [$tabLabel, $tabCount])
                <a href="#" class="nav-link {{ $view === $tabKey ? 'active' : '' }}" data-view="{{ $tabKey }}">{{ $tabLabel }}@if ($tabCount !== null)<span class="badge badge-light-secondary">{{ number_format($tabCount) }}</span>@endif</a>
            @endforeach
        </div>

        <div class="re-rel-chips" id="statusChips" role="group" aria-label="حالة الاهتمام">
            <button type="button" class="btn btn-sm {{ request('relationship_status') ? 'btn-outline-secondary' : 'btn-primary' }}" data-status="">الكل</button>
            @foreach (V::RELATIONSHIP_STATUSES as $statusKey => $statusMeta)
                <button type="button" class="btn btn-sm {{ request('relationship_status') === $statusKey ? 'btn-primary' : 'btn-outline-secondary' }}" data-status="{{ $statusKey }}">{{ $statusMeta['label'] }}</button>
            @endforeach
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters) <span class="stk-count">فلاتر مفعّلة</span> @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-bs-toggle="collapse"
                                data-target="#advancedSearchForm" data-bs-target="#advancedSearchForm" aria-expanded="false">
                            <i class="feather icon-sliders"></i> بحث متقدّم
                        </button>
                    </div>
                </div>

                <form id="searchForm">
                    <input type="hidden" name="view" id="interestView" value="{{ $view }}">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="اسم العميل أو رمزه أو هاتفه…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="role">نوع الاهتمام</label>
                                <select id="role" name="role" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::RELATIONSHIP_ROLES as $value => $meta) <option value="{{ $value }}" @selected(request('role') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="relationship_status">الحالة</label>
                                <select id="relationship_status" name="relationship_status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::RELATIONSHIP_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(request('relationship_status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="assigned_user_id">المسؤول</label>
                                    <select id="assigned_user_id" name="assigned_user_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) request('assigned_user_id') === (string) $user->id)>{{ $user->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="building_id">العقار</label>
                                    <select id="building_id" name="building_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) request('building_id') === (string) $building->id)>{{ $building->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إعادة تعيين</button>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-users"></i> العملاء واهتماماتهم <span class="stk-count">{{ $relationships->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.relationships.partials.table', ['relationships' => $relationships])
            </div>
        </div>
    </div>

    {{-- نافذة واحدة للإضافة والتعديل: العنوان والمسار والطريقة تتبدّل من السكربت --}}
    @if ($canWrite)
        <div class="modal fade" id="relationshipModal" tabindex="-1" aria-labelledby="relationshipModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" id="relationshipForm" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="relationshipModalTitle"><i class="feather icon-users"></i> <span>إضافة اهتمام عميل</span></h5>
                        <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="relationshipErrors" role="alert"></div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rel_client_id" class="stk-req">العميل (من ملفّ العملاء)</label>
                                    @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'id' => 'rel_client_id', 'selected' => null, 'required' => true])
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rel_roles" class="stk-req">نوع الاهتمام</label>
                                    <select id="rel_roles" name="roles[]" class="form-control select2" multiple data-required="1">
                                        @foreach (Rel::SELECTABLE_ROLES as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                    <small class="text-muted">قد يحمل العميل أكثر من دور (مستأجر ومستثمر مثلاً). الملّاك يُسجَّلون من شاشة «الملّاك».</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rel_building_id">محلّ الاهتمام — العقار</label>
                                    <select id="rel_building_id" name="building_id" class="form-control select2">
                                        <option value="">اختر العقار…</option>
                                        @foreach ($buildings as $building) <option value="{{ $building->id }}">{{ $building->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rel_unit_id">محلّ الاهتمام — الوحدة</label>
                                    <select id="rel_unit_id" name="unit_id" class="form-control select2">
                                        <option value="">اختر الوحدة…</option>
                                        @foreach ($units as $unit) <option value="{{ $unit->id }}" data-building="{{ $unit->building_id }}">{{ $unit->full_label }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rel_status" class="stk-req">الحالة</label>
                                    <select id="rel_status" name="relationship_status" class="form-control" data-required="1">
                                        @foreach (V::RELATIONSHIP_STATUSES as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rel_assigned_user_id">المسؤول عن المتابعة</label>
                                    <select id="rel_assigned_user_id" name="assigned_user_id" class="form-control select2">
                                        <option value="">اختر مستخدماً…</option>
                                        @foreach ($users as $user) <option value="{{ $user->id }}">{{ $user->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label for="rel_notes">ملاحظات</label>
                                    <textarea id="rel_notes" name="notes" rows="3" class="form-control" maxlength="3000"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-primary" id="relationshipSubmit"><i class="feather icon-check"></i> حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.unit-filter')
    <script>
        $(function () {
            reList({ url: @json(route('realestate.relationships.index')) });
            reUnitFilter('#rel_building_id', '#rel_unit_id');

            // التبويبات: الاهتمامات (الافتراضي) / مستأجرون حاليون / الكل
            $('#interestTabs').on('click', '[data-view]', function (e) {
                e.preventDefault();
                $('#interestView').val($(this).data('view'));
                $(this).addClass('active').siblings().removeClass('active');
                loadData(1);
            });

            // شرائح الحالة السريعة تحرّك قائمة «الحالة» نفسها فيبقى البحث المتقدّم والشرائح متّسقَين
            $('#statusChips').on('click', '[data-status]', function () {
                const value = String($(this).data('status') || '');
                $('#relationship_status').val(value).trigger('change');
                $('#statusChips .btn').removeClass('btn-primary').addClass('btn-outline-secondary');
                $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
                loadData(1);
            });
            $('#relationship_status').on('change', function () {
                const value = String($(this).val() || '');
                $('#statusChips .btn').each(function () {
                    const on = String($(this).data('status') || '') === value;
                    $(this).toggleClass('btn-primary', on).toggleClass('btn-outline-secondary', !on);
                });
            });
            // «إعادة تعيين» تُفرغ الحقول المخفية أيضاً — يُعاد التبويب الحالي
            $('#resetSearch').on('click', () => { $('#interestView').val($('#interestTabs .active').data('view') || 'interests'); $('#relationship_status').val('').trigger('change'); });

            const $modal = $('#relationshipModal');
            if (!$modal.length) return;

            const $form = $('#relationshipForm');
            const $errors = $('#relationshipErrors');
            const storeUrl = @json(route('realestate.relationships.store'));
            const token = @json(csrf_token());
            let target = { url: storeUrl, method: 'POST' };

            function toggleModal(show) {
                reModal($modal, show);
            }

            function fill(data) {
                $errors.addClass('d-none').empty();
                $form.find('.is-invalid').removeClass('is-invalid');

                if (window.reClientSelectSet) reClientSelectSet('#rel_client_id', data.client || '', data.clientName || '');

                // دورٌ قديم («مالك») ليس في القائمة: يُضاف خياراً مؤقّتاً كي لا يسقط بالتعديل
                $('#rel_roles option[data-legacy]').remove();
                (data.roles || []).forEach(function (role) {
                    if (!$('#rel_roles option[value="' + role + '"]').length) {
                        $('#rel_roles').append($('<option data-legacy="1"></option>').val(role).text(role === 'owner' ? 'مالك (قديم)' : role));
                    }
                });
                $('#rel_roles').val(data.roles || []).trigger('change');
                $('#rel_building_id').val(data.building || '').trigger('change');
                $('#rel_unit_id').val(data.unit || '').trigger('change');
                $('#rel_status').val(data.status || 'active');
                $('#rel_assigned_user_id').val(data.assignee || '').trigger('change');
                $('#rel_notes').val(data.notes || '');
            }

            $('#addRelationship').on('click', function () {
                target = { url: storeUrl, method: 'POST' };
                $('#relationshipModalTitle span').text('إضافة اهتمام عميل');
                fill({});
                toggleModal(true);
            });

            // الجدول يُستبدل مع كل بحث — فالحدث مفوَّض
            $(document).on('click', '.re-rel-edit', function (e) {
                e.preventDefault();

                const $btn = $(this);
                let roles = [];
                try { roles = JSON.parse($btn.attr('data-roles') || '[]'); } catch (error) { roles = []; }

                target = { url: $btn.attr('data-url'), method: 'PUT' };
                $('#relationshipModalTitle span').text('تعديل اهتمام العميل');
                fill({ client: $btn.attr('data-client'), clientName: $btn.attr('data-client-name'), roles: roles, building: $btn.attr('data-building'), unit: $btn.attr('data-unit'),
                    status: $btn.attr('data-status'), assignee: $btn.attr('data-assignee'), notes: $btn.attr('data-notes') });
                toggleModal(true);
            });

            // اختيار الوحدة يحدّد عقارها
            $('#rel_unit_id').on('change', function () {
                const building = $(this).find('option:selected').data('building');
                if (building && String($('#rel_building_id').val()) !== String(building)) $('#rel_building_id').val(building).trigger('change');
            });

            $form.on('submit', function (e) {
                e.preventDefault();

                const missing = [];
                $form.find('[data-required="1"]').each(function () {
                    const value = $(this).val();
                    const empty = !value || (Array.isArray(value) && !value.length);
                    $(this).toggleClass('is-invalid', empty);
                    if (empty) missing.push($('label[for="' + this.id + '"]').first().text().trim());
                });

                if (missing.length) {
                    return $errors.removeClass('d-none').text('حقول مطلوبة: ' + missing.join('، '));
                }

                const $submit = $('#relationshipSubmit').prop('disabled', true);
                const data = $form.serializeArray().concat([{ name: '_token', value: token }]);
                if (target.method !== 'POST') data.push({ name: '_method', value: target.method });

                $.ajax({ url: target.url, method: 'POST', data: $.param(data), headers: { Accept: 'application/json' } })
                    .done(function (response) {
                        toggleModal(false);
                        Swal.fire({ icon: 'success', title: response.message || 'تم', timer: 1300, showConfirmButton: false });
                        loadData(1, false);
                    })
                    .fail(function (xhr) {
                        const body = xhr.responseJSON || {};
                        const list = body.errors ? Object.values(body.errors).flat() : [body.message || 'حدث خطأ غير متوقّع'];

                        $errors.removeClass('d-none').empty().append($('<ul class="mb-0 ps-1"></ul>').append(list.map(m => $('<li></li>').text(m))));
                        Object.keys(body.errors || {}).forEach(name => $form.find('[name="' + name.split('.')[0] + '"], [name="' + name.split('.')[0] + '[]"]').addClass('is-invalid'));
                    })
                    .always(() => $submit.prop('disabled', false));
            });
        });
    </script>
@endsection
