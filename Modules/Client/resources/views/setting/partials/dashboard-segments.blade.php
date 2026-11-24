{{--
    محرّر شرائح مخطَّطٍ في لوحة تحكّم العملاء.

    الصفوفُ تُرسَم بالجافاسكربت من JSON واحد — الأوّلي والافتراضي بنفس
    الدالّة — فلا مساران للرسم. والأسماءُ تُعاد فهرستها بعد كل تعديل
    (`name[i][field]` بترتيب الظهور)، وترتيبُ الظهور هو ترتيبُ الفحص.
    إن لم يعمل الجافاسكربت لا يُرسَل الحقل أصلاً، فيبقى المحفوظ كما هو
    («الغائب لا يُمسّ» في `saveSettingGroups()`).

    @param string $name      اسم الحقل في `ClientModuleSettingsController::FIELDS`
    @param string $kind      lifecycle | aging
    @param mixed  $rows      المحفوظ (أو old() بعد خطأ تحقّق)
    @param array  $defaults  `ClientDashboardSegments::DEFAULT_*`
--}}
@php
    $segT = fn (string $key) => __('client_settings.module_settings.fields.dashboard_segments.'.$key);
    $segRows = is_array($rows) && $rows !== [] ? array_values($rows) : $defaults;
@endphp
<div class="cl-seg-editor mb-2" data-seg-editor data-name="{{ $name }}" data-kind="{{ $kind }}"
    data-max-rows="{{ \App\Support\ClientDashboardSegments::MAX_ROWS }}"
    data-last-row="{{ $segT('last_row') }}" data-reset-confirm="{{ $segT('reset_confirm') }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-50" style="gap: .5rem;">
        <h6 class="mb-0" style="font-weight: 700;">{{ $segT($kind.'_title') }}</h6>
        <div class="d-flex" style="gap: .4rem;">
            <button type="button" class="btn btn-sm btn-outline-primary" data-seg-add><i class="feather icon-plus"></i> {{ $segT('add') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-seg-reset><i class="feather icon-rotate-ccw"></i> {{ $segT('reset') }}</button>
        </div>
    </div>
    <span class="cl-help d-block mb-50">{{ $segT($kind.'_help') }}</span>

    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead>
                <tr>
                    <th style="width: 36px;">#</th>
                    <th style="min-width: 260px;">{{ $segT('name') }}</th>
                    <th style="width: 64px;">{{ $segT('color') }}</th>
                    <th style="width: 110px;">{{ $segT($kind === 'aging' ? 'aging_from' : 'from') }}</th>
                    <th style="width: 120px;">{{ $segT($kind === 'aging' ? 'aging_to' : 'to') }}</th>
                    @if ($kind !== 'aging')
                        <th style="width: 110px;">{{ $segT('min_invoices') }}</th>
                    @endif
                    @if ($kind === 'lifecycle')
                        <th style="width: 150px;">{{ $segT('min_gap') }}</th>
                    @endif
                    <th style="width: 110px;"></th>
                </tr>
            </thead>
            <tbody data-seg-body></tbody>
        </table>
    </div>

    <template data-seg-template>
        <tr>
            <td class="text-center align-middle" data-seg-index></td>
            <td>
                <input type="text" class="form-control form-control-sm" data-f="label" maxlength="40" required>
                <small class="d-block text-muted mt-25" style="line-height: 1.5;" data-seg-sentence></small>
            </td>
            <td><input type="color" class="form-control form-control-sm p-0" data-f="color" style="height: 31px;"></td>
            <td>
                @if ($kind === 'aging')
                    <input type="text" class="form-control form-control-sm" data-seg-from readonly tabindex="-1">
                @else
                    <input type="number" min="0" max="3650" class="form-control form-control-sm" data-f="from" placeholder="0">
                @endif
            </td>
            <td><input type="number" min="0" max="3650" class="form-control form-control-sm" data-f="to" placeholder="{{ $segT('to_placeholder') }}"></td>
            @if ($kind !== 'aging')
                <td><input type="number" min="0" max="1000" class="form-control form-control-sm" data-f="min_invoices" placeholder="—"></td>
            @endif
            @if ($kind === 'lifecycle')
                <td><input type="number" min="0" max="3650" class="form-control form-control-sm" data-f="min_gap" placeholder="—"></td>
            @endif
            <td class="text-nowrap align-middle">
                @if ($kind !== 'aging')
                    <button type="button" class="btn btn-sm btn-flat-secondary px-50" data-seg-up title="{{ $segT('move_up') }}"><i class="feather icon-arrow-up"></i></button>
                    <button type="button" class="btn btn-sm btn-flat-secondary px-50" data-seg-down title="{{ $segT('move_down') }}"><i class="feather icon-arrow-down"></i></button>
                @endif
                <button type="button" class="btn btn-sm btn-flat-danger px-50" data-seg-remove title="{{ $segT('remove') }}"><i class="feather icon-trash-2"></i></button>
            </td>
        </tr>
    </template>

    <script type="application/json" data-seg-rows>@json($segRows)</script>
    <script type="application/json" data-seg-defaults>@json($defaults)</script>
    <script type="application/json" data-seg-texts>@json(trans('client_settings.module_settings.fields.dashboard_segments.sentence'))</script>
</div>
