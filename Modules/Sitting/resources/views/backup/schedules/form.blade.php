@extends('sitting::master')

@section('title', $job->exists ? __('settings.backup.schedules.edit_title') : __('settings.backup.schedules.new_title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    @php
        $isEdit = $job->exists;
        $scheduleType = old('schedule_type', $job->schedule_type ?? 'weekly');
        $backupType = old('backup_type', $currentType);
        $scopeMode = old('scope_mode', $currentScope);
        $pickedSections = old('sections', $currentSections);
        $runTime = old('schedule_time', $job->schedule_time ? \Carbon\Carbon::parse($job->schedule_time)->format('H:i') : '02:00');
    @endphp

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-database"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ $isEdit ? __('settings.backup.schedules.editing', ['name' => $job->name]) : __('settings.backup.schedules.new_title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('Backup.index') }}">{{ __('settings.backup.title') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('Backup.schedules.index') }}">{{ __('settings.backup.schedules.crumb') }}</a></li>
                        <li class="breadcrumb-item active">{{ $isEdit ? __('settings.common.edit') : __('settings.backup.schedules.add') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger" style="border-radius:10px">
                <ul class="mb-0" style="padding-inline-start:1.2rem">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
            action="{{ $isEdit ? route('Backup.schedules.update', $job->id) : route('Backup.schedules.store') }}">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="row">
                <div class="col-lg-7 col-12">
                    <!-- ============ التعريف ============ -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="feather icon-info ml-1"></i> {{ __('settings.cron.definition') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>{{ __('settings.cron.name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required maxlength="190"
                                    value="{{ old('name', $job->name) }}"
                                    placeholder="{{ __('settings.backup.schedules.name_placeholder') }}">
                            </div>

                            <div class="form-group mb-0">
                                <label>{{ __('settings.common.description') }}</label>
                                <textarea name="description" class="form-control" rows="2"
                                    maxlength="1000">{{ old('description', $job->description) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- ============ ما الذي يُحفَظ ============ -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="feather icon-database ml-1"></i> {{ __('settings.backup.schedules.what') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>{{ __('settings.backup.backup_type') }}</label>
                                <div class="bk-choice">
                                    <label>
                                        <input type="radio" name="backup_type" value="settings"
                                            {{ $backupType === 'settings' ? 'checked' : '' }}>
                                        <span class="bk-choice-title">
                                            <i class="feather icon-sliders"></i>
                                            {{ __('settings.backup.schedules.type_from_settings') }}
                                        </span>
                                        <span class="bk-choice-hint">
                                            {{ __('settings.backup.from_settings', ['label' => $settings->typeLabel()]) }}
                                        </span>
                                    </label>
                                    @foreach ($types as $key => $meta)
                                        <label>
                                            <input type="radio" name="backup_type" value="{{ $key }}"
                                                {{ $backupType === $key ? 'checked' : '' }}>
                                            <span class="bk-choice-title">
                                                <i class="feather icon-{{ $meta['icon'] }}"></i> {{ $meta['label'] }}
                                            </span>
                                            <span class="bk-choice-hint">{{ $meta['hint'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <hr>

                            <div class="form-group">
                                <label>{{ __('settings.backup.table_scope') }}</label>
                                <div class="bk-choice">
                                    @php
                                        $scopeChoices = [
                                            'settings' => [
                                                __('settings.backup.schedules.scope_from_settings'),
                                                __('settings.backup.schedules.scope_from_settings_hint', ['label' => $settings->tableSelectionLabel()]),
                                            ],
                                            'all' => [
                                                __('settings.backup.schedules.scope_all'),
                                                __('settings.backup.schedules.scope_all_hint'),
                                            ],
                                            'exclude' => [
                                                __('settings.backup.exclude_sections'),
                                                __('settings.backup.excluded_hint'),
                                            ],
                                            'include' => [
                                                __('settings.backup.include_sections'),
                                                __('settings.backup.schedules.scope_include_hint'),
                                            ],
                                        ];
                                    @endphp
                                    @foreach ($scopeChoices as $key => [$label, $hint])
                                        <label>
                                            <input type="radio" class="scope-mode" name="scope_mode" value="{{ $key }}"
                                                {{ $scopeMode === $key ? 'checked' : '' }}>
                                            <span class="bk-choice-title">
                                                {{ $label }}
                                                @if ($key === 'all')
                                                    <span class="bk-choice-tag">{{ __('settings.backup.recommended') }}</span>
                                                @endif
                                            </span>
                                            <span class="bk-choice-hint">{{ $hint }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- منتقٍ واحد للنمطين: الاسم المرسَل واحد (`sections`)
                                 والفرق في `scope_mode` وحده، فلا يمكن أن يُرسَل
                                 اختياران متضاربان --}}
                            <div class="form-group mb-0" id="sectionsBlock"
                                style="{{ in_array($scopeMode, ['include', 'exclude'], true) ? '' : 'display:none' }}">
                                <label>{{ __('settings.backup.schedules.pick_sections') }}</label>
                                @include('sitting::backup._section_picker', [
                                    'name' => 'sections',
                                    'selected' => $pickedSections,
                                    'id' => 'picker-schedule',
                                ])
                            </div>
                        </div>
                    </div>

                    <!-- ============ متى ============ -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="feather icon-clock ml-1"></i> {{ __('settings.backup.schedules.when') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>{{ __('settings.cron.schedule_pattern') }} <span class="text-danger">*</span></label>
                                <select name="schedule_type" id="scheduleType" class="form-control" required>
                                    @foreach ($scheduleTypes as $key => $meta)
                                        <option value="{{ $key }}"
                                            data-needs-time="{{ $meta['needs_time'] ? 1 : 0 }}"
                                            data-needs-day="{{ $meta['needs_day'] ? 1 : 0 }}"
                                            {{ $scheduleType === $key ? 'selected' : '' }}>
                                            {{ $meta['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group" id="timeBlock">
                                    <label>{{ __('settings.cron.run_time') }}</label>
                                    <input type="time" name="schedule_time" id="scheduleTime" class="form-control"
                                        value="{{ $runTime }}">
                                    <small class="text-muted">{{ __('settings.cron.timezone_note', ['tz' => config('app.timezone')]) }}</small>
                                </div>

                                <div class="col-md-6 form-group" id="dayBlock">
                                    <label id="dayLabel">{{ __('settings.cron.day') }}</label>

                                    <select name="schedule_day" id="weekDay" class="form-control">
                                        @foreach ($weekDays as $num => $label)
                                            <option value="{{ $num }}"
                                                {{ (int) old('schedule_day', $job->schedule_day ?? 5) === $num ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>

                                    {{-- الحقلان يحملان الاسم نفسه؛ المخفيّ منهما
                                         يُعطَّل في JS وإلا فازت قيمة الأخير عشوائياً --}}
                                    <input type="number" name="schedule_day" id="monthDay" class="form-control"
                                        min="1" max="31" style="display:none"
                                        value="{{ old('schedule_day', $job->schedule_day ?? 1) }}">
                                </div>
                            </div>

                            <div class="form-group mb-0" id="cronBlock" style="display:none">
                                <label>{{ __('settings.cron.expression') }} <span class="text-danger">*</span></label>
                                <input type="text" name="cron_expression" id="cronExpression"
                                    class="form-control cj-mono lg" dir="ltr" placeholder="0 2 * * *"
                                    value="{{ old('cron_expression', $job->cron_expression ?? '0 2 * * *') }}">
                                <small class="text-muted">{!! __('settings.backup.cron_hint') !!}</small>
                            </div>

                            <div class="mt-1 p-1" style="background:#eef6ff;border:1px solid #b9d9ff;border-radius:8px">
                                <div><i class="feather icon-eye"></i> <strong>{{ __('settings.cron.preview') }}</strong></div>
                                <div id="previewExpr" class="cj-mono lg mt-1" dir="ltr" style="text-align:left">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ التشغيل ============ -->
                <div class="col-lg-5 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="feather icon-sliders ml-1"></i> {{ __('settings.cron.runtime_section') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>{{ __('settings.common.status') }}</label>
                                <select name="status" class="form-control">
                                    @foreach ($statuses as $key => $meta)
                                        <option value="{{ $key }}"
                                            {{ old('status', $job->status ?? 'active') === $key ? 'selected' : '' }}>
                                            {{ $meta['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label>{{ __('settings.cron.timeout') }}</label>
                                <input type="number" name="timeout" class="form-control" min="60" max="7200"
                                    value="{{ old('timeout', $job->timeout ?? 1800) }}">
                                <small class="text-muted">{{ __('settings.backup.schedules.timeout_hint') }}</small>
                            </div>

                            <hr>

                            <div class="custom-control custom-checkbox mb-1">
                                <input type="hidden" name="skip_cleanup" value="0">
                                <input type="checkbox" class="custom-control-input" id="skip_cleanup"
                                    name="skip_cleanup" value="1"
                                    {{ old('skip_cleanup', $skipCleanup) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="skip_cleanup">
                                    {{ __('settings.backup.schedules.skip_cleanup') }}
                                </label>
                                <small class="d-block text-muted">{{ __('settings.backup.schedules.skip_cleanup_hint') }}</small>
                            </div>

                            <div class="custom-control custom-checkbox mb-1">
                                <input type="hidden" name="notify_on_failure" value="0">
                                <input type="checkbox" class="custom-control-input" id="notify_on_failure"
                                    name="notify_on_failure" value="1"
                                    {{ old('notify_on_failure', $job->notify_on_failure ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="notify_on_failure">{{ __('settings.cron.notify_failure') }}</label>
                            </div>

                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="notify_on_success" value="0">
                                <input type="checkbox" class="custom-control-input" id="notify_on_success"
                                    name="notify_on_success" value="1"
                                    {{ old('notify_on_success', $job->notify_on_success ?? false) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="notify_on_success">{{ __('settings.cron.notify_success') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="alert mb-1"
                                style="background:#f4f5f7;border:1px solid #e0e3e9;color:#4b5563;border-radius:8px;font-size:.82rem">
                                <i class="feather icon-info"></i>
                                {{ __('settings.backup.schedules.retention_note') }}
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('Backup.schedules.index') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-x"></i> {{ __('settings.common.cancel') }}
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-save"></i>
                                    {{ $isEdit ? __('settings.cron.save_edits') : __('settings.backup.schedules.create_job') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        (function () {
            var typeSel   = document.getElementById('scheduleType');
            var timeBlock = document.getElementById('timeBlock');
            var dayBlock  = document.getElementById('dayBlock');
            var cronBlock = document.getElementById('cronBlock');
            var weekDay   = document.getElementById('weekDay');
            var monthDay  = document.getElementById('monthDay');
            var dayLabel  = document.getElementById('dayLabel');
            var timeInput = document.getElementById('scheduleTime');
            var cronInput = document.getElementById('cronExpression');

            /* قوالب مطابقة لـ CronJob::SCHEDULE_TYPES — المعاينة تعرض ما
               سيبنيه الخادم بالضبط لا تقريباً له */
            var TEMPLATES = {
                every_minute:     '* * * * *',
                every_5_minutes:  '*/5 * * * *',
                every_10_minutes: '*/10 * * * *',
                every_15_minutes: '*/15 * * * *',
                every_30_minutes: '*/30 * * * *',
                hourly:           '{m} * * * *',
                daily:            '{m} {H} * * *',
                weekly:           '{m} {H} * * {d}',
                monthly:          '{m} {H} {d} * *'
            };

            function buildExpression() {
                if (typeSel.value === 'cron') { return (cronInput.value || '').trim(); }

                var tpl = TEMPLATES[typeSel.value];
                if (!tpl) { return ''; }

                var t = (timeInput.value || '00:00').split(':');
                var day = typeSel.value === 'weekly' ? weekDay.value : monthDay.value;

                return tpl.replace('{m}', parseInt(t[1] || 0, 10))
                          .replace('{H}', parseInt(t[0] || 0, 10))
                          .replace('{d}', day);
            }

            function refresh() {
                var opt = typeSel.options[typeSel.selectedIndex];
                var isCron = typeSel.value === 'cron';
                var needsTime = opt.getAttribute('data-needs-time') === '1';
                var needsDay = opt.getAttribute('data-needs-day') === '1';
                var isWeekly = typeSel.value === 'weekly';

                cronBlock.style.display = isCron ? '' : 'none';
                timeBlock.style.display = (!isCron && needsTime) ? '' : 'none';
                dayBlock.style.display  = (!isCron && needsDay) ? '' : 'none';

                weekDay.style.display = isWeekly ? '' : 'none';
                weekDay.disabled = !(!isCron && needsDay && isWeekly);
                monthDay.style.display = (!isWeekly && needsDay) ? '' : 'none';
                monthDay.disabled = !(!isCron && needsDay && !isWeekly);
                dayLabel.textContent = isWeekly ? @json(__('settings.cron.weekday')) : @json(__('settings.cron.monthday'));

                cronInput.disabled = !isCron;

                document.getElementById('previewExpr').textContent = buildExpression() || '—';
            }

            typeSel.addEventListener('change', refresh);
            timeInput.addEventListener('change', refresh);
            weekDay.addEventListener('change', refresh);
            monthDay.addEventListener('change', refresh);
            cronInput.addEventListener('input', refresh);
            refresh();

            /* المنتقي يُخفى ولا يُعطَّل: تعطيله يُسقِط اختياراً مؤشَّراً إن
               تنقّل المسؤول بين الأنماط قبل الحفظ */
            document.querySelectorAll('.scope-mode').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    document.getElementById('sectionsBlock').style.display =
                        (this.value === 'include' || this.value === 'exclude') ? '' : 'none';
                });
            });
        })();
    </script>
@endsection
