@php
    /** @var \App\Models\SystemLanguage|null $language */
    $language = $language ?? null;
    $isEdit = (bool) $language;

    // القيم الحالية: المُدخل السابق عند فشل التحقق، ثم بيانات السجل، ثم الافتراضي
    $val = function (string $field, $fallback = null) use ($language) {
        return old($field, $language->{$field} ?? $fallback);
    };
@endphp

@once
    @push('language-form-styles')
    @endpush
    <style>
        .lang-hint {
            display: block;
            margin-top: .35rem;
            font-size: .78rem;
            line-height: 1.6;
            color: #6e6b7b;
        }
        .lang-section-title {
            margin-bottom: 1rem;
            padding-bottom: .6rem;
            border-bottom: 2px solid #ebe9f1;
            font-weight: 600;
        }
        .lang-section-title small {
            display: block;
            margin-top: .25rem;
            font-size: .78rem;
            font-weight: 400;
            color: #b9b9c3;
        }
        /* المفتاح وليبله في سطر واحد، والشرح تحتهما بمسافة واضحة */
        .lang-switch {
            display: block;
            min-height: 1.7rem;
            margin-bottom: .35rem;
        }
        .lang-switch .custom-control-label {
            display: inline-block;
            font-weight: 600;
            line-height: 1.5rem;
            white-space: nowrap;
            cursor: pointer;
        }
    </style>
@endonce

{{-- المعلومات الأساسية --}}
<h5 class="lang-section-title">
    <i class="feather icon-info"></i> {{ __('languages.basic_info') }}
</h5>

<div class="row">
    <div class="col-md-6 col-12 mb-1">
        <label>{{ __('languages.name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ $val('name') }}" required maxlength="100"
               placeholder="{{ __('languages.name_placeholder') }}">
        <small class="lang-hint">{{ __('languages.hints.name') }}</small>
        @error('name')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-6 col-12 mb-1">
        <label>{{ __('languages.native_name') }}</label>
        <input type="text" name="native_name" class="form-control @error('native_name') is-invalid @enderror"
               value="{{ $val('native_name') }}" maxlength="100"
               placeholder="{{ __('languages.native_name_placeholder') }}">
        <small class="lang-hint">{{ __('languages.hints.native_name') }}</small>
        @error('native_name')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.code') }} <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ $val('code') }}" required maxlength="10"
               placeholder="{{ __('languages.code_placeholder') }}">
        <small class="lang-hint">{{ __('languages.hints.code') }}</small>
        @error('code')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.locale') }}</label>
        <input type="text" name="locale" class="form-control @error('locale') is-invalid @enderror"
               value="{{ $val('locale') }}" maxlength="20">
        <small class="lang-hint">{{ __('languages.hints.locale') }}</small>
        @error('locale')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.direction') }} <span class="text-danger">*</span></label>
        <select name="direction" class="form-control @error('direction') is-invalid @enderror" required>
            <option value="rtl" {{ $val('direction', 'ltr') === 'rtl' ? 'selected' : '' }}>{{ __('languages.rtl') }}</option>
            <option value="ltr" {{ $val('direction', 'ltr') === 'ltr' ? 'selected' : '' }}>{{ __('languages.ltr') }}</option>
        </select>
        <small class="lang-hint">{{ __('languages.hints.direction') }}</small>
        @error('direction')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-6 col-12 mb-1">
        <label>{{ __('languages.flag') }}</label>
        <input type="text" name="flag" class="form-control @error('flag') is-invalid @enderror"
               value="{{ $val('flag') }}" maxlength="50">
        <small class="lang-hint">{{ __('languages.hints.flag') }}</small>
        @error('flag')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-6 col-12 mb-1">
        <label>{{ __('languages.sort_order') }}</label>
        <input type="number" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror"
               value="{{ $val('sort_order', 0) }}" min="0">
        <small class="lang-hint">{{ __('languages.hints.sort_order') }}</small>
        @error('sort_order')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-12 mb-1">
        <label>{{ __('languages.description') }}</label>
        <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ $val('description') }}</textarea>
        <small class="lang-hint">{{ __('languages.hints.description') }}</small>
        @error('description')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>
</div>

{{-- الإعدادات الإقليمية --}}
<h5 class="lang-section-title mt-2">
    <i class="feather icon-globe"></i> {{ __('languages.regional_settings') }}
    <small>{{ __('languages.regional_note') }}</small>
</h5>

<div class="row">
    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.date_format') }}</label>
        <input type="text" name="date_format" class="form-control" value="{{ $val('date_format', 'Y-m-d') }}" maxlength="50">
        <small class="lang-hint">{{ __('languages.hints.date_format') }}</small>
        @error('date_format')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.time_format') }}</label>
        <input type="text" name="time_format" class="form-control" value="{{ $val('time_format', 'H:i:s') }}" maxlength="50">
        <small class="lang-hint">{{ __('languages.hints.time_format') }}</small>
        @error('time_format')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.number_format') }}</label>
        <input type="text" name="number_format" class="form-control" value="{{ $val('number_format', '1,234.56') }}" maxlength="50">
        <small class="lang-hint">{{ __('languages.hints.number_format') }}</small>
        @error('number_format')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.timezone') }}</label>
        <select name="timezone" class="form-control">
            @foreach (timezone_identifiers_list() as $tz)
                <option value="{{ $tz }}" {{ $val('timezone', 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
            @endforeach
        </select>
        <small class="lang-hint">{{ __('languages.hints.timezone') }}</small>
        @error('timezone')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.calendar_type') }}</label>
        <select name="calendar_type" class="form-control">
            @foreach (['gregorian' => 'Gregorian', 'hijri' => 'Hijri', 'jalali' => 'Jalali'] as $key => $label)
                <option value="{{ $key }}" {{ $val('calendar_type', 'gregorian') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <small class="lang-hint">{{ __('languages.hints.calendar_type') }}</small>
        @error('calendar_type')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-2 col-6 mb-1">
        <label>{{ __('languages.thousands_separator') }}</label>
        <input type="text" name="thousands_separator" class="form-control" value="{{ $val('thousands_separator', ',') }}" maxlength="5">
        <small class="lang-hint">{{ __('languages.hints.thousands_separator') }}</small>
        @error('thousands_separator')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-2 col-6 mb-1">
        <label>{{ __('languages.decimal_separator') }}</label>
        <input type="text" name="decimal_separator" class="form-control" value="{{ $val('decimal_separator', '.') }}" maxlength="5">
        <small class="lang-hint">{{ __('languages.hints.decimal_separator') }}</small>
        @error('decimal_separator')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>
</div>

{{-- المظهر --}}
<h5 class="lang-section-title mt-2">
    <i class="feather icon-type"></i> {{ __('languages.appearance') }}
    <small>{{ __('languages.appearance_note') }}</small>
</h5>

<div class="row">
    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.font_family') }}</label>
        <input type="text" name="font_family" class="form-control" value="{{ $val('font_family') }}" maxlength="100" placeholder="Cairo, Tajawal, ...">
        <small class="lang-hint">{{ __('languages.hints.font_family') }}</small>
        @error('font_family')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.font_size') }}</label>
        <input type="text" name="font_size" class="form-control" value="{{ $val('font_size') }}" maxlength="20" placeholder="14px">
        <small class="lang-hint">{{ __('languages.hints.font_size') }}</small>
        @error('font_size')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>

    <div class="col-md-4 col-12 mb-1">
        <label>{{ __('languages.color') }}</label>
        <input type="text" name="color" class="form-control" value="{{ $val('color') }}" maxlength="20" placeholder="#7367F0">
        <small class="lang-hint">{{ __('languages.hints.color') }}</small>
        @error('color')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>
</div>

{{-- ملف الترجمة --}}
<h5 class="lang-section-title mt-2">
    <i class="feather icon-upload"></i> {{ __('languages.translation_file') }}
</h5>

@if ($isEdit && $language->translationFiles())
    <div class="alert alert-success py-1 px-2" role="alert">
        <i class="fa fa-file-alt mr-1"></i>
        {{ __('languages.current_files') }}:
        @foreach ($language->translationFiles() as $path)
            <code class="d-inline-block">{{ $path }}</code>@if (! $loop->last)<span class="mx-1">،</span>@endif
        @endforeach
    </div>
@endif

<div class="row">
    <div class="col-12 mb-1">
        <label>{{ $isEdit ? __('languages.replace_translation') : __('languages.upload_translation') }}</label>
        <input type="file" name="translation_files[]" multiple accept=".json,.php,application/json"
               class="form-control @error('translation_files') is-invalid @enderror @error('translation_files.*') is-invalid @enderror">
        <small class="lang-hint">{{ __('languages.hints.translation_files') }}</small>
        @error('translation_files')<small class="text-danger d-block">{{ $message }}</small>@enderror
        @foreach ($errors->get('translation_files.*') as $messages)
            @foreach ($messages as $message)
                <small class="text-danger d-block">{{ $message }}</small>
            @endforeach
        @endforeach
        @error('translation_file')<small class="text-danger d-block">{{ $message }}</small>@enderror

        <div class="alert alert-info py-1 px-2 mt-1 mb-0" role="alert">
            <i class="fa fa-shield-alt mr-1"></i>
            <small>{{ __('languages.hints.translation_security') }}</small>
        </div>

        <details class="mt-1">
            <summary style="cursor: pointer; font-size: .82rem; color: #7367f0;">
                {{ __('languages.translation_example_title') }}
            </summary>
            <div class="row mt-1">
                <div class="col-md-6 col-12">
                    <small class="text-muted d-block mb-1">{{ __('languages.translation_example_php') }}</small>
<pre class="mb-0 p-1" style="background:#f8f8f8;border-radius:4px;font-size:.75rem;direction:ltr;text-align:left;overflow:auto;">&lt;?php

return [
    'welcome' =&gt; 'Welcome',
    'menu' =&gt; [
        'home' =&gt; 'Home',
    ],
];</pre>
                </div>
                <div class="col-md-6 col-12">
                    <small class="text-muted d-block mb-1">{{ __('languages.translation_example_json') }}</small>
<pre class="mb-0 p-1" style="background:#f8f8f8;border-radius:4px;font-size:.75rem;direction:ltr;text-align:left;overflow:auto;">{{ __('settings.layout.json_example') }}</pre>
                </div>
            </div>
        </details>
    </div>
</div>

{{-- الحالة --}}
<h5 class="lang-section-title mt-2">
    <i class="feather icon-toggle-right"></i> {{ __('languages.status_options') }}
</h5>

<div class="row">
    <div class="col-md-6 col-12 mb-1">
        {{-- بلا custom-control-inline: كان يحصر عرض المفتاح فيلتفّ نصّ الليبل فوق الشرح تحته --}}
        <div class="custom-control custom-switch lang-switch">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1"
                   {{ $val('is_active', true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="is_active">{{ __('languages.is_active') }}</label>
        </div>
        <small class="lang-hint">{{ __('languages.hints.is_active') }}</small>
    </div>

    <div class="col-md-6 col-12 mb-1">
        <div class="custom-control custom-switch lang-switch">
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" class="custom-control-input" id="is_default" name="is_default" value="1"
                   {{ $val('is_default', false) ? 'checked' : '' }}
                   {{ canLanguages('set_default') ? '' : 'disabled' }}>
            <label class="custom-control-label" for="is_default">{{ __('languages.is_default') }}</label>
        </div>
        <small class="lang-hint">{{ __('languages.hints.is_default') }}</small>
    </div>
</div>

{{-- مفاتيح الترجمة تُحرَّر من شاشة التفاصيل وحدها، لا من هذا النموذج --}}

<script>
    // اللغة الافتراضية لا تكون معطّلة: تفعيلها كافتراضية يفرض التفعيل ويقفله
    (function () {
        var isDefault = document.getElementById('is_default');
        var isActive = document.getElementById('is_active');
        if (!isDefault || !isActive) return;

        function sync() {
            if (isDefault.checked) {
                isActive.checked = true;
                isActive.setAttribute('readonly', 'readonly');
                isActive.style.pointerEvents = 'none';
            } else {
                isActive.removeAttribute('readonly');
                isActive.style.pointerEvents = '';
            }
        }

        isDefault.addEventListener('change', sync);
        sync();
    })();
</script>
