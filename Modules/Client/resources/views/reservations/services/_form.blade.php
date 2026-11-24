{{--
    ملفُّ حجز منتجٍ خدمي — ما لا يعرفه جدولُ المنتجات وحده.

    المنتجُ ثابتٌ هنا (حقلٌ مخفيّ): الاسمُ والسعرُ والضريبةُ تُقرأ منه وتُعرض
    للاطّلاع، ولا تُكتب في الملفّ — فلا يفترق سعرُ الحجز عن سعر الفاتورة.
--}}
@php
    $old = fn ($key, $default = null) => old($key, $service->{$key} ?? $default);
    $chosen = $service->exists ? $service->employees->pluck('id')->all() : [];
@endphp

<input type="hidden" name="product_id" value="{{ $product->id }}">

<div class="rsv-card">
    <div class="rsv-card__head">
        <h6 class="rsv-card__title"><i class="feather icon-package"></i> {{ __('reservations.services.product') }}</h6>
    </div>
    <div class="rsv-card__body">
        <div class="row g-3">
            <div class="col-md-6">
                <span class="rsv-muted d-block">{{ __('reservations.services.product') }}</span>
                <strong>{{ $product->name }}</strong>
            </div>
            <div class="col-md-3">
                <span class="rsv-muted d-block">{{ __('reservations.columns.price') }}</span>
                <strong class="rsv-money">{{ number_format((float) $product->sale_price, 2) }}</strong>
            </div>
            <div class="col-md-3">
                <span class="rsv-muted d-block">{{ __('reservations.columns.tax') }}</span>
                <strong>{{ $product->tax1 ? (\App\Models\TaxSitting::find($product->tax1)?->name ?? '—') : __('reservations.services.no_tax') }}</strong>
            </div>
            <div class="col-12">
                <span class="rsv-hint">{{ __('reservations.services.product_info') }}</span>
            </div>
        </div>
    </div>
</div>

<div class="rsv-card">
    <div class="rsv-card__head">
        <h6 class="rsv-card__title"><i class="feather icon-clock"></i> {{ __('reservations.services.form_head') }}</h6>
    </div>
    <div class="rsv-card__body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">{{ __('reservations.attributes.duration') }} <span class="text-danger">*</span></label>
                <input type="number" name="duration" class="form-control" min="1" max="1440" required value="{{ $old('duration', 30) }}">
                <span class="rsv-hint">{{ __('reservations.services.duration_hint') }}</span>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('reservations.attributes.buffer_before') }}</label>
                <input type="number" name="buffer_before" class="form-control" min="0" max="1440" value="{{ $old('buffer_before', 0) }}">
                <span class="rsv-hint">{{ __('reservations.services.buffer_before_hint') }}</span>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('reservations.attributes.buffer_after') }}</label>
                <input type="number" name="buffer_after" class="form-control" min="0" max="1440" value="{{ $old('buffer_after', 0) }}">
                <span class="rsv-hint">{{ __('reservations.services.buffer_after_hint') }}</span>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('reservations.attributes.max_daily') }}</label>
                <input type="number" name="max_daily" class="form-control" min="1" value="{{ $old('max_daily') }}">
                <span class="rsv-hint">{{ __('reservations.services.max_daily_hint') }}</span>
            </div>

            <div class="col-12">
                <label class="form-label">{{ __('reservations.attributes.employees') }}</label>
                <div class="rsv-select-add rsv-select-add--top">
                    <select name="employees[]" class="form-select" multiple size="6">
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(in_array($employee->id, old('employees', $chosen)))>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                    @include('client::reservations.partials.select-add', ['route' => 'employee.create', 'permission' => 'employees.create', 'title' => __('reservations.actions.quick_add_employee')])
                    @include('client::reservations.partials.select-add', ['route' => 'shift_management.create', 'permission' => 'shifts.create', 'title' => __('reservations.actions.quick_add_shift'), 'icon' => 'clock'])
                </div>
                <span class="rsv-hint">{{ __('reservations.services.employees_hint') }}</span>
            </div>

            <div class="col-md-3">
                <label class="form-label">{{ __('reservations.services.sort') }}</label>
                <input type="number" name="sort_order" class="form-control" min="0" value="{{ $old('sort_order', 0) }}">
            </div>
            <div class="col-md-5 d-flex align-items-center pt-3">
                <div class="form-check form-switch">
                    {{-- الحقل المخفي: الخانة غير المؤشَّرة لا تُرسَل، فيستحيل إطفاؤها بدونه --}}
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="svcActive" @checked($old('is_active', true))>
                    <label class="form-check-label" for="svcActive">{{ __('reservations.services.active_label') }}</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">{{ __('reservations.services.description') }}</label>
                <textarea name="description" rows="2" class="form-control">{{ $old('description') }}</textarea>
            </div>
        </div>
    </div>
</div>
