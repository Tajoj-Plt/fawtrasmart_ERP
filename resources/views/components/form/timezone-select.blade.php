@props([
    'label' => 'المنطقة الزمنية',
    'name' => 'timezone',
    'icon' => 'clock',
    'required' => false,
    'value' => 'Asia/Riyadh'
])

<div class="form-group">
    <label for="{{ $name }}">{{ $label }}</label>
    <div class="position-relative has-icon-left">
        <select class="form-control select2" id="{{ $name }}" name="{{ $name }}">
            @foreach(\App\Helpers\TimezoneHelper::getAllTimezones() as $key => $timezone)
                <option value="{{ $key }}" {{ old($name, $value) == $key ? 'selected' : '' }}>
                    {{ $timezone }}
                </option>
            @endforeach
        </select>
        <div class="form-control-position">
            <i class="feather icon-{{ $icon }}"></i>
        </div>
    </div>
</div>

