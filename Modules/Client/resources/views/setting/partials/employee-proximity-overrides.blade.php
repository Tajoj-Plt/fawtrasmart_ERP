{{--
    نطاق قرب مخصّص لموظّفين بعينهم — عمليّاتٌ مستقلّة (إضافة/إلغاء لكل
    موظّف) لا تنتمي لنموذج «حفظ الإعدادات» الكبير الذي يلفّ هذه البطاقة،
    فحقولها وأزرارها تشير بسمة `form="..."` إلى نماذج فعلية مُعرَّفة خارج
    ذلك النموذج الكبير (أسفل الصفحة) بدل التداخل غير الصالح `<form>` داخل
    `<form>`.

    @see \Modules\Client\Http\Controllers\ClientModuleSettingsController::storeEmployeeProximityOverride()
    @see \Modules\Client\Http\Controllers\ClientModuleSettingsController::destroyEmployeeProximityOverride()
--}}
<div class="card">
    <div class="card-header">
        <h4 class="card-title">
            <i class="feather icon-users"></i>
            {{ __('client_settings.module_settings.sections.employee_proximity_overrides') }}
        </h4>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">{{ __('client_settings.module_settings.fields.employee_proximity_overrides_hint') }}</p>

        @if ($employeesWithProximityOverride->isEmpty())
            <p class="text-muted small mb-3">{{ __('client_settings.module_settings.fields.employee_proximity_overrides_empty') }}</p>
        @else
            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('client_settings.module_settings.fields.employee_proximity_overrides_employee') }}</th>
                            <th>{{ __('client_settings.module_settings.fields.employee_proximity_overrides_radius') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employeesWithProximityOverride as $emp)
                            <tr>
                                <td>{{ $emp->name }}</td>
                                <td>{{ round($emp->visit_proximity_radius_km * 1000) }}</td>
                                <td class="text-end">
                                    <button type="submit" form="removeProximityOverride{{ $emp->id }}"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('{{ __('client_settings.module_settings.fields.employee_proximity_overrides_remove') }}?')">
                                        <i class="feather icon-x"></i>
                                        {{ __('client_settings.module_settings.fields.employee_proximity_overrides_remove') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($employeesWithoutProximityOverride->isNotEmpty())
            <div class="row g-2 align-items-end">
                <div class="col-md-5 col-12">
                    <label for="proximityOverrideUserId" class="form-label small mb-1">
                        {{ __('client_settings.module_settings.fields.employee_proximity_overrides_employee') }}
                    </label>
                    <select id="proximityOverrideUserId" name="user_id" class="form-control" form="addProximityOverrideForm">
                        @foreach ($employeesWithoutProximityOverride as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-8">
                    <label for="proximityOverrideRadius" class="form-label small mb-1">
                        {{ __('client_settings.module_settings.fields.employee_proximity_overrides_radius') }}
                    </label>
                    <input type="number" step="1" min="10" max="50000" class="form-control"
                        id="proximityOverrideRadius" name="radius_meters" form="addProximityOverrideForm" value="300">
                </div>
                <div class="col-md-3 col-4">
                    <button type="submit" form="addProximityOverrideForm" class="btn btn-primary w-100">
                        <i class="feather icon-plus"></i>
                        {{ __('client_settings.module_settings.fields.employee_proximity_overrides_add') }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
