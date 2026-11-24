@extends('rentalmanagement::realestate.master')

@section('title', 'المهام والمتابعات')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        // المبلغ بعملته: `RealEstateMoney` إن وُجد، وإلّا رقمٌ بعملة النظام
        $money = fn ($v, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
            ? \Modules\RentalManagement\Support\RealEstateMoney::format($v, $currency)
            : number_format((float) $v, 2).' '.(\App\Support\CurrencySettings::code() ?? '');
        $hasFilters = request()->hasAny(['activity_type', 'assigned_user_id', 'related_type', 'mine']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            <div class="btn-group re-view-toggle" role="group" aria-label="طريقة العرض">
                <button type="button" class="btn btn-outline-secondary {{ $mode === 'kanban' ? 'active' : '' }}" data-mode="kanban" title="لوحة"><i class="feather icon-columns"></i></button>
                <button type="button" class="btn btn-outline-secondary {{ $mode === 'list' ? 'active' : '' }}" data-mode="list" title="قائمة"><i class="feather icon-list"></i></button>
            </div>
            @permission('realestate.tasks.create')
                <button type="button" class="btn btn-primary" id="newTask"><i class="feather icon-plus"></i> مهمة جديدة</button>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'المهام والمتابعات',
            'icon' => 'check-square',
            'sub' => 'اتصالات ومعاينات وتحصيل ومتابعة عقود — على لوحةٍ واحدة',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'المهام والمتابعات' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="row">
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-calendar"></i> جدول اليوم</h4>
                        <span class="stk-count">{{ $today->count() }}</span>
                    </div>
                    <div class="card-body">
                        @if ($today->isEmpty())
                            <p class="text-muted mb-0">لا مهام مجدولة لهذا اليوم.</p>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach ($today as $task)
                                    <li class="list-group-item d-flex align-items-center px-0 py-50" style="gap:.6rem">
                                        <span class="badge badge-light-{{ $task->isOverdue() ? 'danger' : 'primary' }} stk-num">{{ $task->due_at?->format('H:i') }}</span>
                                        <span class="min-width-0">
                                            {{ $task->title }}
                                            @if ($task->client) <small class="text-muted d-block">{{ $task->client->display_name }}</small> @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            {{-- القائمة نفسها التي تعرضها لوحة التحكم — من `AttentionService` فلا يختلف العدد --}}
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-alert-triangle"></i> تحتاج تدخّلاً</h4>
                        <small class="text-muted">تُشتقّ آلياً من العقود والدفعات والعروض والصيانة</small>
                    </div>
                    <div class="card-body">
                        @if (empty($attention))
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'check-circle', 'title' => 'لا شيء يحتاج تدخّلاً', 'hint' => 'لا دفعات متأخرة ولا عقود مهمَلة ولا صيانة متجاوزة لموعدها'])
                        @else
                            <div class="re-attention">
                                @foreach ($attention as $item)
                                    <div class="re-attention__item">
                                        <div class="re-attention__head">
                                            <span><i class="feather icon-{{ $item['icon'] }} text-{{ $item['tone'] }}"></i> {{ $item['label'] }}</span>
                                            <a href="{{ $item['url'] }}" class="badge badge-light-{{ $item['tone'] }}">{{ $item['count'] }}</a>
                                        </div>
                                        <ul class="re-attention__rows">
                                            @foreach ($item['rows'] as $row)
                                                <li>
                                                    @switch($item['key'])
                                                        @case('overdue') {{ $row->contract->tenant->display_name ?? '—' }} — {{ $money($row->balance, $row->contract->currency ?? null) }} منذ {{ $row->due_date->format('Y-m-d') }} @break
                                                        @case('expiring') {{ $row->code }} — {{ $row->tenant->display_name ?? '—' }} — بعد {{ max(0, $row->days_left) }} يوماً @break
                                                        @case('pending') {{ $row->code }} — {{ $row->tenant->display_name ?? '—' }} @break
                                                        @case('maintenance') {{ $row->code }} — {{ $row->title }} @break
                                                        @case('listings') {{ $row->code }} — {{ $row->title }} @break
                                                        @case('vacant') {{ $row->full_label }} — منذ {{ $row->vacant_since?->format('Y-m-d') }} @break
                                                    @endswitch
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
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
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="عنوان المهمة…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::TASK_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(request('status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="priority">الأولوية</label>
                                <select id="priority" name="priority" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::PRIORITIES as $value => $meta) <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="activity_type">نوع النشاط</label>
                                    <select id="activity_type" name="activity_type" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::ACTIVITY_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('activity_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="assigned_user_id">المسؤول</label>
                                    <select id="assigned_user_id" name="assigned_user_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) request('assigned_user_id') === (string) $user->id)>{{ $user->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="related_type">مرتبطة بـ</label>
                                    <select id="related_type" name="related_type" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::TASK_RELATED as $value => $meta) <option value="{{ $value }}" @selected(request('related_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="custom-control custom-checkbox form-check mb-1">
                                    <input type="checkbox" class="custom-control-input form-check-input" id="mine" name="mine" value="1" @checked(request()->boolean('mine'))>
                                    <label class="custom-control-label form-check-label" for="mine">مهامي فقط</label>
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
                <h6 class="stk-table-head__title"><i class="feather icon-check-square"></i> المهام <span class="stk-count">{{ $mode === 'list' ? $tasks->total() : $tasks->count() }}</span></h6>
                <div class="d-flex align-items-center" style="gap:.6rem">
                    @permission('realestate.tasks.edit')
                        <small class="text-muted" id="kanbanHint" @if ($mode !== 'kanban') style="display:none" @endif>اسحب البطاقة بين الأعمدة لتغيير حالتها</small>
                    @endpermission
                    <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
                </div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.tasks.partials.'.$mode, ['tasks' => $tasks])
            </div>
        </div>
    </div>

    @if (canAny(['realestate.tasks.create', 'realestate.tasks.edit']))
        <div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form id="taskForm" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="taskModalLabel">مهمة جديدة</h5>
                        <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="taskErrors" role="alert"><div class="alert-body"></div></div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="task_title" class="stk-req">عنوان المهمة</label>
                                    <input type="text" id="task_title" name="title" class="form-control" maxlength="191">
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-group">
                                    <label for="task_activity_type" class="stk-req">نوع النشاط</label>
                                    <select id="task_activity_type" name="activity_type" class="form-control">
                                        @foreach (V::ACTIVITY_TYPES as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-group">
                                    <label for="task_priority" class="stk-req">الأولوية</label>
                                    <select id="task_priority" name="priority" class="form-control">
                                        @foreach (V::PRIORITIES as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="task_related_type">مرتبطة بـ</label>
                                    <select id="task_related_type" name="related_type" class="form-control">
                                        <option value="">— بلا ارتباط —</option>
                                        @foreach (V::TASK_RELATED as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="task_related_id">السجلّ المرتبط</label>
                                    <select id="task_related_id" name="related_id" class="form-control" data-url="{{ route('realestate.tasks.related') }}">
                                        <option value="">اختر نوع الارتباط أولاً…</option>
                                    </select>
                                    <small class="text-muted">ابحث بالرمز أو الاسم — مطلوب عند تحديد نوع الارتباط.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="task_client_id">العميل</label>
                                    @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'id' => 'task_client_id', 'selected' => null, 'searchRoute' => 'realestate.tasks.clients'])
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="task_assigned_user_id">المسؤول</label>
                                    <select id="task_assigned_user_id" name="assigned_user_id" class="form-control select2">
                                        <option value="">اختر مستخدماً…</option>
                                        @foreach ($users as $user) <option value="{{ $user->id }}">{{ $user->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="task_due_at">الموعد</label>
                                    <input type="datetime-local" id="task_due_at" name="due_at" class="form-control" dir="ltr">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="task_next_follow_up_at">المتابعة التالية</label>
                                    <input type="datetime-local" id="task_next_follow_up_at" name="next_follow_up_at" class="form-control" dir="ltr">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label for="task_notes">ملاحظات</label>
                                    <textarea id="task_notes" name="notes" rows="3" class="form-control" maxlength="3000"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-primary" id="taskSubmit"><i class="feather icon-check"></i> حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            const token = @json(csrf_token());
            const storeUrl = @json(route('realestate.tasks.store'));
            const fields = ['title', 'activity_type', 'priority', 'related_type', 'related_id', 'client_id', 'assigned_user_id', 'due_at', 'next_follow_up_at', 'notes'];
            const defaults = { activity_type: @json(array_key_first(V::ACTIVITY_TYPES)), priority: 'medium' };
            let mode = @json($mode);
            let updateUrl = null;

            // `reset()` يعيد المربّع إلى حالته عند التحميل — وقد يكون مؤشَّراً من الرابط؛ فيُفرَّغ قبل معالج `reList`
            $('#resetSearch').on('click', () => $('#mine').removeAttr('checked').prop('checked', false));

            reList({ url: @json(route('realestate.tasks.index')), extra: () => ({ mode: mode }) });

            $('.re-view-toggle [data-mode]').on('click', function () {
                mode = $(this).data('mode');
                $(this).addClass('active').siblings().removeClass('active');
                $('#kanbanHint').toggle(mode === 'kanban');
                loadData(1);
            });

            // ── نافذة الإضافة/التعديل ──
            const modalEl = document.getElementById('taskModal');
            const toggleModal = function (show) {
                if (!modalEl) return;
                reModal(modalEl, show);
            };

            // ── «السجلّ المرتبط»: قائمة بحثٍ حسب النوع المختار، لا رقمٌ يُكتب باليد ──
            const $related = $('#task_related_id');

            function setRelated(id, text) {
                $related.find('option').filter((i, o) => o.value !== '').remove();
                if (id) $related.append(new Option(text || ('#' + id), id, true, true));
                $related.val(id || '').trigger('change');
            }

            if ($.fn.select2 && $related.length) {
                $related.select2({
                    dir: 'rtl', width: '100%', allowClear: true, placeholder: 'ابحث عن السجلّ…', dropdownParent: $('#taskModal'),
                    language: { searching: () => 'جارٍ البحث…', noResults: () => 'لا نتائج — اختر نوع الارتباط أولاً', errorLoading: () => 'تعذّر تحميل النتائج' },
                    ajax: {
                        url: $related.data('url'), dataType: 'json', delay: 250,
                        data: params => ({ q: params.term || '', page: params.page || 1, type: $('#task_related_type').val() }),
                        processResults: data => ({ results: data.results || [], pagination: data.pagination || { more: false } }),
                    },
                });
            }

            // تبديل النوع يُفرغ السجلّ: معرّفُ عقدٍ ليس معرّفَ وحدة
            $('#task_related_type').on('change', function () {
                if ($(this).data('silent')) return;
                setRelated('', '');
            });

            function openTask(values, url) {
                updateUrl = url || null;
                $('#taskModalLabel').text(url ? 'تعديل المهمة' : 'مهمة جديدة');
                $('#taskErrors').addClass('d-none');
                $('#taskForm .is-invalid').removeClass('is-invalid');

                $('#task_related_type').data('silent', true);
                fields.filter(name => !['related_id', 'client_id'].includes(name))
                    .forEach(name => $('#task_' + name).val(values[name] || defaults[name] || '').trigger('change'));
                $('#task_related_type').data('silent', false);

                setRelated(values.related_id || '', values.related_label || '');
                if (window.reClientSelectSet) reClientSelectSet('#task_client_id', values.client_id || '', values.client_name || '');
                toggleModal(true);
            }

            $('#newTask').on('click', () => openTask({}, null));

            // مفوَّض: البطاقات والصفوف تُستبدل مع كل بحث. و`attr` لا `data` كي لا تُحوَّل القيم أرقاماً
            $(document).on('click', '.re-task-edit', function (e) {
                e.stopPropagation();
                const values = {};
                fields.concat(['related_label', 'client_name']).forEach(name => values[name] = $(this).attr('data-' + name));
                openTask(values, $(this).attr('data-update-url'));
            });

            $('#taskForm').on('submit', function (e) {
                e.preventDefault();

                const $errors = $('#taskErrors').addClass('d-none');
                const $submit = $('#taskSubmit').prop('disabled', true);
                const data = { _token: token };

                fields.forEach(name => data[name] = $('#task_' + name).val() || '');
                if (updateUrl) data._method = 'PUT';
                $('#taskForm .is-invalid').removeClass('is-invalid');

                $.ajax({ url: updateUrl || storeUrl, method: 'POST', data: data, headers: { Accept: 'application/json' } })
                    .done(function (response) {
                        toggleModal(false);
                        // اللوحة تُحدَّث فوراً، ثم تُعاد الصفحة ليتحدّث «جدول اليوم» و«تحتاج تدخّلاً» معها
                        loadData(1, false);
                        Swal.fire({ icon: 'success', title: response.message || 'تم', timer: 1100, showConfirmButton: false })
                            .then(() => window.location.reload());
                    })
                    .fail(function (xhr) {
                        const body = xhr.responseJSON || {};
                        const lines = body.errors ? Object.values(body.errors).flat() : [body.message || 'حدث خطأ غير متوقّع'];

                        Object.keys(body.errors || {}).forEach(name => $('#task_' + name).addClass('is-invalid'));
                        $errors.removeClass('d-none').find('.alert-body').html(lines.map(l => $('<div>').text(l).html()).join('<br>'));
                    })
                    .always(() => $submit.prop('disabled', false));
            });

            // ── السحب والإفلات — مفوَّض إلى `document` للسبب نفسه ──
            let dragged = null;

            $(document).on('dragstart', '.re-task[draggable="true"]', function (e) {
                dragged = this;
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/plain', $(this).data('id'));
                $(this).css('opacity', .5);
            });

            $(document).on('dragend', '.re-task', function () {
                $(this).css('opacity', '');
                $('.re-kanban__col').removeClass('is-over');
                dragged = null;
            });

            $(document).on('dragover', '.re-kanban__col', function (e) {
                if (!dragged) return;
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
                $(this).addClass('is-over');
            });

            $(document).on('dragleave', '.re-kanban__col', function (e) {
                if (!this.contains(e.originalEvent.relatedTarget)) $(this).removeClass('is-over');
            });

            $(document).on('drop', '.re-kanban__col', function (e) {
                e.preventDefault();
                $(this).removeClass('is-over');

                if (!dragged) return;

                const $card = $(dragged);
                const status = $(this).data('status');

                if ($card.attr('data-column') === status) return;

                // تُنقل فوراً ثم تُؤكَّد من الخادم؛ وعند الفشل تُعاد اللوحة كما هي في القاعدة
                $(this).append($card);

                $.ajax({ url: $card.attr('data-status-url'), method: 'POST', data: { _token: token, status: status }, headers: { Accept: 'application/json' } })
                    .done(() => loadData(1, false))
                    .fail(function (xhr) {
                        const body = xhr.responseJSON || {};
                        Swal.fire({ icon: 'error', title: 'تعذّر نقل المهمة', text: body.message || 'حدث خطأ غير متوقّع' }).then(() => loadData(1, false));
                    });
            });
        });
    </script>
@endsection
