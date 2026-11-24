@extends('sitting::master')

@section('title', $job->exists ? __('settings.cron.edit_title') : __('settings.cron.new_title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    @php
        $isEdit = $job->exists;
        $currentType = old('schedule_type', $job->schedule_type ?? 'daily');
        $currentHandler = old('handler', $job->handler);
        $currentParams = old('parameters', $job->parameters ?? []);
    @endphp

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-clock"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ $isEdit ? __('settings.cron.editing', ['name' => $job->name]) : __('settings.cron.new_title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('CronJobs.index') }}">{{ __('settings.cron.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ $isEdit ? __('settings.common.edit') : __('settings.cron.create') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger" style="border-radius:10px">
                <strong>{{ __('settings.cron.save_failed') }}</strong>
                <ul class="mb-0 mt-1" style="padding-inline-start:1.2rem">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
            action="{{ $isEdit ? route('CronJobs.update', $job->id) : route('CronJobs.store') }}"
            id="cronForm">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="row">
                <!-- ============ التعريف ============ -->
                <div class="col-lg-7 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-info ml-1"></i> {{ __('settings.cron.definition') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>{{ __('settings.cron.name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $job->name) }}" required maxlength="190">
                            </div>

                            <div class="form-group">
                                <label>{{ __('settings.common.description') }}</label>
                                <textarea name="description" class="form-control" rows="2"
                                    maxlength="1000">{{ old('description', $job->description) }}</textarea>
                                <small class="text-muted">{{ __('settings.cron.description_hint') }}</small>
                            </div>

                            <div class="form-group">
                                <label>{{ __('settings.cron.handler') }} <span class="text-danger">*</span></label>
                                <select name="handler" id="handler" class="form-control" required
                                    {{ $job->is_system ? 'disabled' : '' }}>
                                    <option value="">{{ __('settings.cron.choose') }}</option>
                                    @foreach ($handlers as $groupKey => $group)
                                        <optgroup label="{{ $types[$groupKey]['label'] ?? $groupKey }}">
                                            @foreach ($group as $handler => $meta)
                                                @php $available = ($meta['available'] ?? true) === true; @endphp
                                                {{-- المخطَّطة تُعرَض ولا تُختار: أمرها غير مكتوب، واختيارها
                                                     لمهمة جديدة يصنع صفّاً يفشل عند أول تشغيل --}}
                                                <option value="{{ $handler }}"
                                                    data-options="{{ implode(',', $meta['options'] ?? []) }}"
                                                    data-schedule="{{ $meta['suggested_schedule'] ?? '' }}"
                                                    data-description="{{ $meta['description'] ?? '' }}"
                                                    {{ $currentHandler === $handler ? 'selected' : '' }}
                                                    {{ $available || $currentHandler === $handler ? '' : 'disabled' }}>
                                                    {{ $meta['label'] }}{{ $available ? '' : ' — '.__('settings.cron.not_implemented') }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>

                                @if ($job->is_system)
                                    {{-- المهام النظامية مربوطة بأمرها: تغييره يجعل الصفّ يدّعي
                                         وظيفة لا يؤدّيها. الحقل معطّل ومُرسَل مخفيّاً --}}
                                    <input type="hidden" name="handler" value="{{ $job->handler }}">
                                    <small class="text-warning d-block mt-1">
                                        <i class="feather icon-lock"></i>
                                        {{ __('settings.cron.system_locked') }}
                                    </small>
                                @endif

                                <small class="text-muted d-block mt-1" id="handlerHint">
                                    {!! __('settings.cron.registry_note') !!}
                                </small>
                            </div>

                            {{-- المعاملات تظهر فقط للأوامر التي تقبلها --}}
                            <div class="form-group" id="paramsBlock" style="display:none">
                                <label>{{ __('settings.cron.command_options') }}</label>
                                <div id="paramsFields"></div>
                                <small class="text-muted">{{ __('settings.cron.options_hint') }}</small>
                            </div>
                        </div>
                    </div>

                    <!-- ============ الجدولة ============ -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-clock ml-1"></i> {{ __('settings.cron.schedule_section') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>{{ __('settings.cron.schedule_pattern') }} <span class="text-danger">*</span></label>
                                <select name="schedule_type" id="scheduleType" class="form-control" required>
                                    @foreach ($scheduleTypes as $key => $meta)
                                        <option value="{{ $key }}"
                                            data-needs-time="{{ $meta['needs_time'] ? 1 : 0 }}"
                                            data-needs-day="{{ $meta['needs_day'] ? 1 : 0 }}"
                                            {{ $currentType === $key ? 'selected' : '' }}>
                                            {{ $meta['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group" id="timeBlock">
                                    <label>{{ __('settings.cron.run_time') }}</label>
                                    <input type="time" name="schedule_time" id="scheduleTime" class="form-control"
                                        value="{{ old('schedule_time', $job->schedule_time ? \Carbon\Carbon::parse($job->schedule_time)->format('H:i') : '02:00') }}">
                                    <small class="text-muted">{{ __('settings.cron.timezone_note', ['tz' => config('app.timezone')]) }}</small>
                                </div>

                                <div class="col-md-6 form-group" id="dayBlock">
                                    <label id="dayLabel">{{ __('settings.cron.day') }}</label>

                                    <select name="schedule_day" id="weekDay" class="form-control">
                                        @foreach ($weekDays as $num => $label)
                                            <option value="{{ $num }}"
                                                {{ (int) old('schedule_day', $job->schedule_day ?? 0) === $num ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <input type="number" name="schedule_day" id="monthDay" class="form-control"
                                        min="1" max="31" value="{{ old('schedule_day', $job->schedule_day ?? 1) }}"
                                        style="display:none">
                                    <small class="text-muted" id="monthDayHint" style="display:none">
                                        {{ __('settings.cron.monthday_hint') }}
                                    </small>
                                </div>
                            </div>

                            <div class="form-group" id="cronBlock" style="display:none">
                                <label>{{ __('settings.cron.expression') }} <span class="text-danger">*</span></label>
                                <input type="text" name="cron_expression" id="cronExpression" class="form-control cj-mono lg"
                                    value="{{ old('cron_expression', $job->cron_expression ?? '0 2 * * *') }}"
                                    placeholder="0 2 * * *" dir="ltr">

                                <div class="mt-1 p-1" style="background:#f7f8fa;border:1px solid #e6e9ef;border-radius:8px">
                                    <div style="font-size:.82rem">
                                        <strong>{{ __('settings.cron.order') }}</strong>
                                        <span class="cj-mono">{{ __('settings.cron.order_fields') }}</span>
                                    </div>
                                    <div class="cj-sub mt-1">
                                        <span class="cj-mono">*</span> {{ __('settings.cron.legend_all') }} ·
                                        <span class="cj-mono">*/5</span> {{ __('settings.cron.legend_step') }} ·
                                        <span class="cj-mono">1,15</span> {{ __('settings.cron.legend_list') }} ·
                                        <span class="cj-mono">1-5</span> {{ __('settings.cron.legend_range') }} ·
                                        <span class="cj-mono">L</span> {{ __('settings.cron.legend_last') }}
                                    </div>
                                    <div class="cj-sub mt-1">
                                        {{ __('settings.cron.examples') }}
                                        <span class="cj-mono">0 2 * * *</span> {{ __('settings.cron.example_daily') }} ·
                                        <span class="cj-mono">0 */6 * * *</span> {{ __('settings.cron.example_hours') }} ·
                                        <span class="cj-mono">55 23 L * *</span> {{ __('settings.cron.example_last') }}
                                    </div>
                                </div>
                            </div>

                            {{-- المعاينة الحيّة: الشرح ومواعيد التشغيل القادمة تُحسَب في المتصفح
                                 لأن حفظ تعبير خاطئ ثم اكتشافه بعد يوم أسوأ من التحقّق الآن --}}
                            <div class="mt-1 p-1" style="background:#eef6ff;border:1px solid #b9d9ff;border-radius:8px">
                                <div><i class="feather icon-eye"></i> <strong>{{ __('settings.cron.preview') }}</strong></div>
                                <div id="previewExpr" class="cj-mono lg mt-1">—</div>
                                <div id="previewDesc" class="mt-1" style="font-size:.88rem">—</div>
                                <div id="previewRuns" class="cj-sub mt-1"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ التشغيل والحالة ============ -->
                <div class="col-lg-5 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-sliders ml-1"></i> {{ __('settings.cron.runtime_section') }}</h4>
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
                                <small class="text-muted">
                                    {{ __('settings.cron.status_hint') }}
                                </small>
                            </div>

                            <div class="form-group">
                                <label>{{ __('settings.cron.timeout') }}</label>
                                <input type="number" name="timeout" class="form-control" min="10" max="7200"
                                    value="{{ old('timeout', $job->timeout ?? 300) }}">
                                <small class="text-muted">{{ __('settings.cron.timeout_hint') }}</small>
                            </div>

                            <hr>

                            <div class="custom-control custom-checkbox mb-1">
                                <input type="hidden" name="prevent_overlap" value="0">
                                <input type="checkbox" class="custom-control-input" id="prevent_overlap"
                                    name="prevent_overlap" value="1"
                                    {{ old('prevent_overlap', $job->prevent_overlap ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="prevent_overlap">{{ __('settings.cron.prevent_overlap') }}</label>
                                <small class="d-block text-muted">
                                    {{ __('settings.cron.prevent_overlap_hint') }}
                                </small>
                            </div>

                            <div class="custom-control custom-checkbox mb-1">
                                <input type="hidden" name="notify_on_failure" value="0">
                                <input type="checkbox" class="custom-control-input" id="notify_on_failure"
                                    name="notify_on_failure" value="1"
                                    {{ old('notify_on_failure', $job->notify_on_failure ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="notify_on_failure">{{ __('settings.cron.notify_failure') }}</label>
                            </div>

                            <div class="custom-control custom-checkbox mb-1">
                                <input type="hidden" name="notify_on_success" value="0">
                                <input type="checkbox" class="custom-control-input" id="notify_on_success"
                                    name="notify_on_success" value="1"
                                    {{ old('notify_on_success', $job->notify_on_success ?? false) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="notify_on_success">{{ __('settings.cron.notify_success') }}</label>
                                <small class="d-block text-muted">
                                    {{ __('settings.cron.notify_success_hint') }}
                                </small>
                            </div>

                            <small class="text-muted d-block mt-1">
                                <i class="feather icon-bell"></i>
                                {!! __('settings.cron.notify_where', ['link' => '<a href="'.route('NotificationSettings.index').'">'.__('settings.account.notification_settings').'</a>']) !!}
                            </small>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body d-flex justify-content-between">
                            <a href="{{ route('CronJobs.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> {{ __('settings.common.cancel') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> {{ $isEdit ? __('settings.cron.save_edits') : __('settings.cron.create_job') }}
                            </button>
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
            var monthHint = document.getElementById('monthDayHint');
            var timeInput = document.getElementById('scheduleTime');
            var cronInput = document.getElementById('cronExpression');
            var handler   = document.getElementById('handler');

            var WEEK = @json(array_values(\App\Models\CronJob::weekDays()));

            /* قوالب مطابقة لـ CronJob::SCHEDULE_TYPES — المعاينة يجب أن تعرض
               ما سيبنيه الخادم بالضبط لا تقريباً له */
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

            function parts() {
                var t = (timeInput.value || '00:00').split(':');
                return { H: parseInt(t[0] || 0, 10), m: parseInt(t[1] || 0, 10) };
            }

            function buildExpression() {
                var type = typeSel.value;
                if (type === 'cron') { return (cronInput.value || '').trim(); }

                var tpl = TEMPLATES[type];
                if (!tpl) { return ''; }

                var p = parts();
                var day = type === 'weekly' ? weekDay.value : monthDay.value;

                return tpl.replace('{m}', p.m).replace('{H}', p.H).replace('{d}', day);
            }

            /* شرح مبسّط للأنماط الشائعة. الشرح الكامل يبقى على الخادم؛ هذا
               للتأكيد البصري قبل الحفظ فقط */
            function describe(expr) {
                var f = expr.split(/\s+/);
                if (f.length !== 5) { return @json(__('settings.cron.preview_incomplete')); }

                var pad = function (n) { return (n < 10 ? '0' : '') + n; };
                var time = pad(parseInt(f[1], 10)) + ':' + pad(parseInt(f[0], 10));

                if (expr === '* * * * *') { return @json(__('settings.cron.preview_every_minute')); }
                if (/^\*\/(\d+) \* \* \* \*$/.test(expr)) { return @json(__('settings.cron.preview_every_n')).replace(':count', expr.match(/^\*\/(\d+)/)[1]); }
                if (f[1] === '*' && f[2] === '*' && f[3] === '*' && f[4] === '*') { return @json(__('settings.cron.preview_hourly')).replace(':minute', parseInt(f[0], 10)); }
                if (f[2] === '*' && f[3] === '*' && f[4] === '*') { return @json(__('settings.cron.preview_daily')).replace(':time', time); }
                if (f[2] === '*' && f[3] === '*' && /^\d+$/.test(f[4])) { return @json(__('settings.cron.preview_weekly')).replace(':day', WEEK[parseInt(f[4], 10)]).replace(':time', time); }
                if (f[2].toUpperCase() === 'L' && f[3] === '*' && f[4] === '*') { return @json(__('settings.cron.preview_last_day')).replace(':time', time); }
                if (/^\d+$/.test(f[2]) && f[3] === '*' && f[4] === '*') { return @json(__('settings.cron.preview_monthly')).replace(':day', parseInt(f[2], 10)).replace(':time', time); }

                return @json(__('settings.cron.preview_custom'));
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

                /* حقلا اليوم يحملان الاسم نفسه؛ تعطيل المخفيّ يمنع إرسال
                   قيمتين لـ schedule_day فتفوز الأخيرة عشوائياً */
                weekDay.style.display = isWeekly ? '' : 'none';
                weekDay.disabled = !(!isCron && needsDay && isWeekly);
                monthDay.style.display = (!isWeekly && needsDay) ? '' : 'none';
                monthDay.disabled = !(!isCron && needsDay && !isWeekly);
                monthHint.style.display = (!isCron && needsDay && !isWeekly) ? '' : 'none';
                dayLabel.textContent = isWeekly ? @json(__('settings.cron.weekday')) : @json(__('settings.cron.monthday'));

                cronInput.disabled = !isCron;

                var expr = buildExpression();
                document.getElementById('previewExpr').textContent = expr || '—';
                document.getElementById('previewDesc').textContent = expr ? describe(expr) : '—';
            }

            /* اقتراح جدولة مناسبة عند اختيار أمر — يوفّر خطوة ويمنع
               جدولة نسخة احتياطية كل دقيقة سهواً */
            function onHandlerChange() {
                var opt = handler.options[handler.selectedIndex];
                if (!opt || !opt.value) { return; }

                var hint = document.getElementById('handlerHint');
                var desc = opt.getAttribute('data-description');
                if (desc) { hint.textContent = desc; }

                var suggested = opt.getAttribute('data-schedule');
                if (suggested && !typeSel.dataset.touched) {
                    typeSel.value = suggested;
                    refresh();
                }

                buildParams(opt.getAttribute('data-options'));
            }

            function buildParams(optionsCsv) {
                var block = document.getElementById('paramsBlock');
                var fields = document.getElementById('paramsFields');
                var names = (optionsCsv || '').split(',').filter(Boolean);

                fields.innerHTML = '';
                block.style.display = names.length ? '' : 'none';

                var existing = @json($currentParams ?: (object) []);

                names.forEach(function (name) {
                    var wrap = document.createElement('div');
                    wrap.className = 'form-group mb-1';
                    var value = existing['--' + name] || existing[name] || '';
                    wrap.innerHTML =
                        '<label style="font-size:.85rem">--' + name + '</label>' +
                        '<input type="text" class="form-control" name="parameters[' + name + ']" value="' +
                        String(value).replace(/"/g, '&quot;') + '">';
                    fields.appendChild(wrap);
                });
            }

            typeSel.addEventListener('change', function () { typeSel.dataset.touched = '1'; refresh(); });
            timeInput.addEventListener('change', refresh);
            weekDay.addEventListener('change', refresh);
            monthDay.addEventListener('change', refresh);
            cronInput.addEventListener('input', refresh);
            if (handler) { handler.addEventListener('change', onHandlerChange); }

            /* عند التعديل لا تُدهَس جدولة قائمة باقتراح الأمر */
            if ({{ $isEdit ? 'true' : 'false' }}) { typeSel.dataset.touched = '1'; }

            if (handler && handler.value) { buildParams(handler.options[handler.selectedIndex].getAttribute('data-options')); }
            refresh();
        })();
    </script>
@endsection
