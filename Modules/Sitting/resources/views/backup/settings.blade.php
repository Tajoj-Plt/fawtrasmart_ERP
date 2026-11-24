@extends('sitting::master')

@section('title', __('settings.backup.settings_title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    @php
        // القيم المعروضة تأتي من `old()` أولاً — الاستمارة المرفوضة يجب أن
        // تعود بما كتبه المسؤول لا بما في قاعدة البيانات
        $frequency = old('frequency', $settings->frequency ?? 'daily');
        $strategy = old('retention_strategy', $settings->retention_strategy ?? 'count');
        $backupType = old('backup_type', $settings->backup_type ?? 'database');
        $runTime = old('run_time', $settings->run_time ? substr((string) $settings->run_time, 0, 5) : '02:00');
        $included = old('included_paths', $settings->included_paths ?? []);
        $excluded = old('excluded_paths', $settings->excluded_paths ?? []);
        $tableMode = old('table_mode', $settings->table_mode ?? 'all');
        $includedKeys = old('included_sections', $includedSections);
        $excludedKeys = old('excluded_sections', $excludedSections);

        // الكلمة نفسها لا تُعاد إلى الشاشة أبداً — وجودها من عدمه هو كل ما
        // تحتاجه الواجهة لتقول «محفوظة» وتعرض «اتركه فارغاً للإبقاء عليها»
        $hasPassword = ! empty($settings->archive_password);
        $envPassword = ! empty(config('backup.backup.password'));
        $encryptOn = (bool) old('encrypt_archives', $settings->encrypt_archives ?? false);

        // التوقيت المخصّص والمسارات وقرص الحفظ كلّها في «خيارات متقدّمة».
        // تُفتَح من نفسها حين يكون فيها ما هو مضبوط فعلاً، وإلا بقي المسؤول
        // يرى إعداداً ساري المفعول لا أثر له في الشاشة
        $advancedOpen = $frequency === 'custom'
            || ! empty($included)
            || ! empty($excluded)
            || ($errors->hasAny(['cron_expression', 'disk', 'establishment_name', 'included_paths', 'excluded_paths']));

        $diskLabel = function ($disk) {
            $key = 'settings.backup.disks.'.$disk;
            $label = __($key);

            return $label === $key ? $disk : $label;
        };
    @endphp

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-database"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.backup.settings_title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('Backup.index') }}">{{ __('settings.backup.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.common.settings') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('Backup.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.common.back') }}
            </a>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('Backup.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-8 col-12">
                    <!-- ما الذي يُحفَظ -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.backup.what') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>{{ __('settings.backup.backup_type') }}</label>
                                <div class="bk-choice">
                                    @foreach ($types as $key => $meta)
                                        <label>
                                            <input type="radio" class="backup-type" name="backup_type"
                                                value="{{ $key }}" {{ $backupType === $key ? 'checked' : '' }}>
                                            <span class="bk-choice-title">
                                                <i class="feather icon-{{ $meta['icon'] }}"></i>
                                                {{ $meta['label'] }}
                                                @if ($key === 'both')
                                                    <span class="bk-choice-tag">{{ __('settings.backup.recommended') }}</span>
                                                @endif
                                            </span>
                                            <span class="bk-choice-hint">{{ $meta['hint'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- نطاق الأقسام يخصّ البيانات وحدها، فيُخفى حين
                                 تكون النسخة مرفقات فقط --}}
                            <div id="tableScopeBlock" style="{{ $backupType === 'files' ? 'display:none' : '' }}">
                                <hr>
                                <div class="form-group">
                                    <label>{{ __('settings.backup.table_scope') }}</label>
                                    <div class="bk-choice">
                                        @foreach ($tableModes as $key => $meta)
                                            <label>
                                                <input type="radio" class="table-mode" name="table_mode"
                                                    value="{{ $key }}" {{ $tableMode === $key ? 'checked' : '' }}>
                                                <span class="bk-choice-title">
                                                    {{ $meta['label'] }}
                                                    @if ($key === 'all')
                                                        <span class="bk-choice-tag">{{ __('settings.backup.recommended') }}</span>
                                                    @endif
                                                </span>
                                                <span class="bk-choice-hint">{{ $meta['hint'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="form-group tscope-include"
                                    style="{{ $tableMode === 'include' ? '' : 'display:none' }}">
                                    <label>{{ __('settings.backup.included_sections') }}</label>
                                    @include('sitting::backup._section_picker', [
                                        'name' => 'included_sections',
                                        'selected' => $includedKeys,
                                        'id' => 'picker-include',
                                    ])
                                    <div class="alert mt-1 mb-0"
                                        style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:8px;font-size:.82rem">
                                        <i class="feather icon-alert-triangle"></i>
                                        {!! __('settings.backup.included_hint') !!}
                                    </div>
                                </div>

                                <div class="form-group tscope-exclude"
                                    style="{{ $tableMode === 'exclude' ? '' : 'display:none' }}">
                                    <label>{{ __('settings.backup.excluded_sections') }}</label>
                                    @include('sitting::backup._section_picker', [
                                        'name' => 'excluded_sections',
                                        'selected' => $excludedKeys,
                                        'id' => 'picker-exclude',
                                    ])
                                    <small class="text-muted">
                                        {{ __('settings.backup.excluded_hint') }}
                                    </small>
                                </div>

                                @if (($tableMode === 'include' && $uncoveredIncluded > 0) || ($tableMode === 'exclude' && $uncoveredExcluded > 0))
                                    {{-- إعداد أُنشئ بالاختيار التفصيلي القديم: يُقال
                                         صراحةً أن الحفظ من هنا سيستبدله بالأقسام --}}
                                    <div class="alert mt-1 mb-0"
                                        style="background:#f4f5f7;border:1px solid #e0e3e9;color:#4b5563;border-radius:8px;font-size:.82rem">
                                        <i class="feather icon-info"></i>
                                        {{ __('settings.backup.legacy_scope_notice') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- متى يُحفَظ -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.backup.when') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="hidden" name="is_enabled" value="0">
                                    <input type="checkbox" class="custom-control-input" id="is_enabled" name="is_enabled"
                                        value="1" {{ old('is_enabled', $settings->is_enabled) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_enabled">
                                        <strong>{{ __('settings.backup.enable') }}</strong>
                                        <div class="cj-sub">
                                            {{ __('settings.backup.enable_hint') }}
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 col-12 form-group">
                                    <label>{{ __('settings.backup.frequency') }}</label>
                                    <select name="frequency" id="frequency" class="form-control">
                                        @foreach ($frequencies as $key => $meta)
                                            <option value="{{ $key }}" {{ $frequency === $key ? 'selected' : '' }}>
                                                {{ $meta['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4 col-12 form-group sched-time"
                                    style="{{ in_array($frequency, ['hourly', 'daily', 'weekly', 'monthly'], true) ? '' : 'display:none' }}">
                                    <label>{{ __('settings.backup.run_time') }}</label>
                                    <input type="time" name="run_time" class="form-control" value="{{ $runTime }}">
                                    <small class="text-muted sched-hourly-note"
                                        style="{{ $frequency === 'hourly' ? '' : 'display:none' }}">
                                        {{ __('settings.backup.minute_only_hint') }}
                                    </small>
                                </div>

                                <div class="col-md-4 col-12 form-group sched-weekday"
                                    style="{{ $frequency === 'weekly' ? '' : 'display:none' }}">
                                    <label>{{ __('settings.backup.weekday') }}</label>
                                    <select name="run_day" class="form-control" {{ $frequency === 'weekly' ? '' : 'disabled' }}>
                                        @foreach ($weekDays as $value => $label)
                                            <option value="{{ $value }}"
                                                {{ (int) old('run_day', $settings->run_day ?? 0) === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4 col-12 form-group sched-monthday"
                                    style="{{ $frequency === 'monthly' ? '' : 'display:none' }}">
                                    <label>{{ __('settings.backup.monthday') }}</label>
                                    <input type="number" name="run_day" class="form-control" min="1" max="31"
                                        value="{{ old('run_day', $settings->run_day ?? 1) }}"
                                        {{ $frequency === 'monthly' ? '' : 'disabled' }}>
                                </div>
                            </div>

                            <div class="alert mb-0 mt-1"
                                style="background:#f4f5f7;border:1px solid #e0e3e9;color:#4b5563;border-radius:8px;font-size:.85rem">
                                <i class="feather icon-clock"></i>
                                @if ($job)
                                    {{ __('settings.backup.job_current_plain', ['schedule' => $scheduleDescription]) }}
                                    {{ __('settings.backup.job_save_note') }}
                                @else
                                    {{ __('settings.backup.job_missing_plain') }}
                                @endif
                                <div class="mt-1">
                                    {!! __('settings.backup.schedules.more_here', [
                                        'link' => '<a href="'.route('Backup.schedules.index').'">'.__('settings.backup.schedules.title').'</a>',
                                    ]) !!}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- تعشية الأرشيف -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.backup.encryption') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="hidden" name="encrypt_archives" value="0">
                                    <input type="checkbox" class="custom-control-input" id="encrypt_archives"
                                        name="encrypt_archives" value="1"
                                        {{ $encryptOn ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="encrypt_archives">
                                        <strong>{{ __('settings.backup.encrypt_enable') }}</strong>
                                        <div class="cj-sub">{{ __('settings.backup.encrypt_enable_hint') }}</div>
                                    </label>
                                </div>
                            </div>

                            <div id="encryptionFields" style="{{ $encryptOn ? '' : 'display:none' }}">
                                <div class="row">
                                    <div class="col-md-6 col-12 form-group">
                                        <label>
                                            {{ __('settings.backup.archive_password') }}
                                            @if ($hasPassword)
                                                <span class="badge badge-light-success">{{ __('settings.backup.password_set') }}</span>
                                            @endif
                                        </label>
                                        <input type="password" name="archive_password" class="form-control"
                                            autocomplete="new-password" minlength="8" maxlength="120"
                                            placeholder="{{ $hasPassword ? __('settings.backup.password_keep') : '' }}">
                                        <small class="text-muted">{{ __('settings.backup.password_hint') }}</small>
                                    </div>

                                    <div class="col-md-6 col-12 form-group">
                                        <label>{{ __('settings.backup.archive_password_confirm') }}</label>
                                        <input type="password" name="archive_password_confirmation" class="form-control"
                                            autocomplete="new-password" maxlength="120">
                                    </div>
                                </div>

                                {{-- تحذير لا نصيحة: فقدان الكلمة يعني نسخاً لا تُفَكّ،
                                     وهو أسوأ من عدم وجود نسخ لأنه لا يُكتشَف إلا وقت الحاجة --}}
                                <div class="alert mb-0"
                                    style="background:#fdecec;border:1px solid #f5b5b5;color:#8a1c1c;border-radius:8px;font-size:.85rem">
                                    <i class="feather icon-alert-triangle"></i>
                                    {{ __('settings.backup.password_warning') }}
                                </div>
                            </div>

                            @if ($envPassword && ! $hasPassword)
                                <div class="alert mb-0 mt-1"
                                    style="background:#f4f5f7;border:1px solid #e0e3e9;color:#4b5563;border-radius:8px;font-size:.82rem">
                                    <i class="feather icon-info"></i>
                                    {{ __('settings.backup.password_from_env') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- خيارات متقدّمة -->
                    <div class="card">
                        <div class="card-body">
                            <details class="bk-adv" {{ $advancedOpen ? 'open' : '' }} id="advancedBlock">
                                <summary>{{ __('settings.backup.advanced') }}</summary>
                                <div class="bk-adv-body">
                                    <p class="cj-sub">{{ __('settings.backup.advanced_hint') }}</p>

                                    <div class="form-group">
                                        <label>{{ __('settings.backup.disk') }}</label>
                                        <select name="disk" class="form-control">
                                            @foreach ($disks as $disk)
                                                <option value="{{ $disk }}"
                                                    {{ old('disk', $settings->disk ?? 'local') === $disk ? 'selected' : '' }}>
                                                    {{ $diskLabel($disk) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">{{ __('settings.backup.disk_hint') }}</small>
                                    </div>

                                    <div class="form-group">
                                        <label>{{ __('settings.backup.establishment_name') }}</label>
                                        <input type="text" name="establishment_name"
                                            class="form-control @error('establishment_name') is-invalid @enderror"
                                            value="{{ old('establishment_name', $settings->establishment_name) }}"
                                            maxlength="80"
                                            placeholder="{{ __('settings.backup.establishment_placeholder') }}">
                                        @error('establishment_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="text-muted">
                                            {{ __('settings.backup.establishment_hint') }}
                                            @if ($settings->establishment_folder)
                                                <br>
                                                <b>{{ __('settings.backup.establishment_folder_now', [
                                                    'folder' => $settings->archiveFolder()]) }}</b>
                                                <br>{{ __('settings.backup.establishment_folder_locked') }}
                                            @endif
                                        </small>
                                    </div>

                                    <div class="form-group sched-cron"
                                        style="{{ $frequency === 'custom' ? '' : 'display:none' }}">
                                        <label>{{ __('settings.backup.cron_expression') }}</label>
                                        <input type="text" name="cron_expression" class="form-control cj-mono"
                                            style="direction:ltr;text-align:left;width:100%"
                                            value="{{ old('cron_expression', $settings->cron_expression) }}"
                                            placeholder="0 2 * * *">
                                        <small class="text-muted">{!! __('settings.backup.cron_hint') !!}</small>
                                    </div>

                                    {{-- مسارات المرفقات تظهر فقط حين تشمل النسخة مرفقات --}}
                                    <div id="filePathsBlock"
                                        style="{{ in_array($backupType, ['files', 'both'], true) ? '' : 'display:none' }}">
                                        <div class="form-group">
                                            <label>{{ __('settings.backup.included_paths') }}</label>
                                            <textarea name="included_paths" class="form-control cj-mono" rows="3"
                                                style="direction:ltr;text-align:left;width:100%">{{ is_array($included) ? implode("\n", $included) : $included }}</textarea>
                                            <small class="text-muted">{{ __('settings.backup.included_paths_hint') }}</small>
                                        </div>

                                        <div class="form-group mb-0">
                                            <label>{{ __('settings.backup.excluded_paths') }}</label>
                                            <textarea name="excluded_paths" class="form-control cj-mono" rows="2"
                                                style="direction:ltr;text-align:left;width:100%">{{ is_array($excluded) ? implode("\n", $excluded) : $excluded }}</textarea>
                                            <small class="text-muted">{{ __('settings.backup.excluded_paths_hint') }}</small>
                                        </div>
                                    </div>

                                    @if ($job)
                                        <div class="cj-sub mt-1">
                                            <a href="{{ route('CronJobs.show', $job->id) }}">{{ __('settings.backup.job_log_link') }}</a>
                                        </div>
                                    @else
                                        <div class="alert mb-0 mt-1"
                                            style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:8px;font-size:.85rem">
                                            <i class="feather icon-alert-triangle"></i>
                                            {!! __('settings.backup.job_missing') !!}
                                        </div>
                                    @endif
                                </div>
                            </details>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <!-- مدّة الاحتفاظ -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.backup.retention') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                @foreach ($strategies as $key => $meta)
                                    <div class="custom-control custom-radio mb-1">
                                        <input type="radio" class="custom-control-input retention-strategy"
                                            id="strategy-{{ $key }}" name="retention_strategy" value="{{ $key }}"
                                            {{ $strategy === $key ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="strategy-{{ $key }}">
                                            <strong>{{ $meta['label'] }}</strong>
                                            <div class="cj-sub">{{ $meta['hint'] }}</div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="form-group retention-count"
                                style="{{ $strategy === 'count' ? '' : 'display:none' }}">
                                <label>{{ __('settings.backup.retention_count') }}</label>
                                <input type="number" name="retention_count" class="form-control" min="1" max="1000"
                                    value="{{ old('retention_count', $settings->retention_count ?? 7) }}">
                            </div>

                            <div class="form-group retention-days"
                                style="{{ $strategy === 'days' ? '' : 'display:none' }}">
                                <label>{{ __('settings.backup.retention_days') }}</label>
                                <input type="number" name="retention_days" class="form-control" min="1" max="3650"
                                    value="{{ old('retention_days', $settings->retention_days ?? 30) }}">
                            </div>

                            {{-- الحقلان يُرسَلان معاً دائماً — المخفيّ منهما مخفيّ
                                 بالعرض لا معطَّل. القاعدة تتطلّب العمودين، وتبديل
                                 السياسة لاحقاً يجد قيمة سليمة في الآخر بلا إعادة ضبط --}}

                            <div class="form-group mb-0">
                                <label>{{ __('settings.backup.storage_limit') }}</label>
                                <input type="number" name="max_storage_mb" class="form-control" min="1"
                                    value="{{ old('max_storage_mb', $settings->max_storage_mb) }}"
                                    placeholder="{{ __('settings.common.no_limit') }}">
                                <small class="text-muted">
                                    {{ __('settings.backup.storage_limit_hint') }}
                                </small>
                            </div>

                            <div class="alert mt-1 mb-0"
                                style="background:#eafaf1;border:1px solid #b7e8cd;color:#1b6b45;border-radius:8px;font-size:.82rem">
                                <i class="feather icon-shield"></i>
                                {{ __('settings.backup.never_deleted') }}
                            </div>
                        </div>
                    </div>

                    <!-- الإشعارات -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.backup.notifications') }}</h4>
                        </div>
                        <div class="card-body">
                            @php
                                $toggles = [
                                    'notify_on_failure' => [__('settings.backup.notify_failure'), __('settings.backup.notify_failure_hint')],
                                    'notify_on_success' => [__('settings.backup.notify_success'), __('settings.backup.notify_success_hint')],
                                    'notify_on_cleanup_failure' => [__('settings.backup.notify_cleanup'), __('settings.backup.notify_cleanup_hint')],
                                    'notify_on_storage_full' => [__('settings.backup.notify_storage'), __('settings.backup.notify_storage_hint')],
                                ];
                            @endphp

                            @foreach ($toggles as $field => [$label, $hint])
                                <div class="form-group">
                                    <input type="hidden" name="{{ $field }}" value="0">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="{{ $field }}"
                                            name="{{ $field }}" value="1"
                                            {{ old($field, $settings->{$field}) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="{{ $field }}">
                                            {{ $label }}
                                            <div class="cj-sub">{{ $hint }}</div>
                                        </label>
                                    </div>
                                </div>
                            @endforeach

                            <div class="alert mb-0"
                                style="background:#f4f5f7;border:1px solid #e0e3e9;color:#4b5563;border-radius:8px;font-size:.82rem">
                                <i class="feather icon-users"></i>
                                {!! __('settings.backup.notify_where', ['link' => '<a href="'.route('NotificationSettings.index').'">'.__('settings.account.notification_settings').'</a>']) !!}
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body text-center">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="feather icon-save"></i> {{ __('settings.backup.save_settings') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var frequency = document.getElementById('frequency');
            var advanced = document.getElementById('advancedBlock');

            // الحقل المخفيّ يُعطَّل لا يُخفى فقط: حقلا `run_day` يحملان الاسم
            // نفسه، وترك كليهما مفعّلاً يجعل المتصفّح يرسل قيمة الأخير أياً
            // كان الظاهر منهما
            function show(selector, visible, disableInputs) {
                document.querySelectorAll(selector).forEach(function (element) {
                    element.style.display = visible ? '' : 'none';

                    if (disableInputs) {
                        element.querySelectorAll('input, select').forEach(function (input) {
                            input.disabled = !visible;
                        });
                    }
                });
            }

            function syncSchedule() {
                var value = frequency.value;

                show('.sched-time', ['hourly', 'daily', 'weekly', 'monthly'].indexOf(value) !== -1, false);
                show('.sched-hourly-note', value === 'hourly', false);
                show('.sched-weekday', value === 'weekly', true);
                show('.sched-monthday', value === 'monthly', true);
                show('.sched-cron', value === 'custom', false);

                // حقل التوقيت المخصّص يسكن «خيارات متقدّمة»: اختياره من قائمة
                // التكرار يجب أن يفتحها، وإلا اختار المسؤول توقيتاً مخصّصاً
                // ولم يجد أين يكتبه
                if (value === 'custom' && advanced) {
                    advanced.open = true;
                }
            }

            frequency.addEventListener('change', syncSchedule);
            syncSchedule();

            document.querySelectorAll('.retention-strategy').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    show('.retention-count', this.value === 'count', false);
                    show('.retention-days', this.value === 'days', false);
                });
            });

            document.querySelectorAll('.backup-type').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    document.getElementById('filePathsBlock').style.display =
                        (this.value === 'files' || this.value === 'both') ? '' : 'none';

                    // نسخة مرفقات فقط بلا أقسام بيانات أصلاً
                    document.getElementById('tableScopeBlock').style.display =
                        this.value === 'files' ? 'none' : '';
                });
            });

            // حقول التعشية تُخفى ولا تُعطَّل: تعطيلها يمنع إرسال الكلمة
            // المكتوبة إن أُخفيت لحظةً ثم أُعيد إظهارها
            var encryptToggle = document.getElementById('encrypt_archives');

            if (encryptToggle) {
                encryptToggle.addEventListener('change', function () {
                    document.getElementById('encryptionFields').style.display = this.checked ? '' : 'none';
                });
            }

            document.querySelectorAll('.table-mode').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    show('.tscope-include', this.value === 'include', false);
                    show('.tscope-exclude', this.value === 'exclude', false);
                });
            });
        })();
    </script>
@endsection
