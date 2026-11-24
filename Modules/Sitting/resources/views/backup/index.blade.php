@extends('sitting::master')

@section('title', __('settings.backup.title'))

@section('content')
    {{-- الأنماط مشتركة مع شاشات المهام المجدولة — النسخ المجدول مهمة منها --}}
    @include('sitting::cron_jobs._styles')

    <style>
        /* شريط إجراءات واحد بدل أزرار متناثرة: فجوة ثابتة، وارتفاع موحّد،
           وفاصل يفرز الفعل الرئيسي عن الروابط الثانوية */
        .bk-actions { display:flex; flex-wrap:wrap; align-items:center; gap:.4rem; justify-content:flex-end; }
        .bk-actions .btn { display:inline-flex; align-items:center; gap:.35rem;
            font-size:.82rem; padding:.45rem .8rem; white-space:nowrap; margin:0; }
        .bk-actions .btn i { font-size:.9rem; }
        .bk-actions-sep { width:1px; align-self:stretch; min-height:1.9rem; background:#e0e4ec; margin:0 .15rem; }

        @media (max-width: 767.98px) {
            .bk-actions { justify-content:flex-start; }
            .bk-actions-sep { display:none; }
        }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-database"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.backup.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.backup.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <div class="bk-actions">
                @permission('settings.backup.create')
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#runBackupModal">
                        <i class="feather icon-play"></i> {{ __('settings.backup.now') }}
                    </button>
                @endpermission

                @permission('settings.backup.settings')
                    <a href="{{ route('Backup.settings') }}" class="btn btn-outline-primary">
                        <i class="feather icon-settings"></i> {{ __('settings.common.settings') }}
                    </a>
                @endpermission

                <span class="bk-actions-sep" aria-hidden="true"></span>

                <a href="{{ route('Backup.schedules.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-clock"></i> {{ __('settings.backup.schedules.title') }}
                </a>
                <a href="{{ route('Backup.history') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-list"></i> {{ __('settings.backup.backup_log') }}
                </a>
                @permission('settings.backup.export')
                    <a href="{{ route('DataExport.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-file-text"></i> {{ __('settings.backup.export_data') }}
                    </a>
                @endpermission
            </div>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- حالة النسخ الدوري: بدونها تبدو الشاشة سليمة بينما لا نسخة تُؤخذ --}}
        @php
            $health = ! $settings->is_enabled || $settings->frequency === 'manual' ? 'unknown'
                : (($stats['age_hours'] === null || $stats['age_hours'] > 48) ? 'failed'
                    : ($stats['age_hours'] > 24 ? 'warning' : 'healthy'));
        @endphp
        <div class="cj-health {{ $health }}">
            <span class="dot"></span>
            <div class="flex-grow-1">
                @if (! $settings->is_enabled)
                    <strong>{{ __('settings.backup.disabled_title') }}</strong>
                    <div style="font-size:.85rem;margin-top:.25rem">
                        {!! __('settings.backup.disabled_body', ['link' => '<a href="'.route('Backup.settings').'" style="color:inherit;text-decoration:underline">'.__('settings.common.settings').'</a>']) !!}
                    </div>
                @elseif ($settings->frequency === 'manual')
                    <strong>{{ __('settings.backup.manual_only_title') }}</strong>
                    <div style="font-size:.85rem;margin-top:.25rem">
                        {{ __('settings.backup.manual_only_body') }}
                    </div>
                @elseif ($stats['last_successful'] === null)
                    <strong>{{ __('settings.backup.none_yet') }}</strong>
                    <div style="font-size:.85rem;margin-top:.25rem">
                        {{ $scheduleDescription ?? $settings->frequencyLabel() }}
                        @if ($job && ! $job->isActive())
                            <span class="text-danger">{{ __('settings.backup.job_state', ['state' => $job->statusLabel()]) }}</span>
                        @endif
                    </div>
                @else
                    <strong>
                        {{ __('settings.backup.last_success_since', ['age' => $stats['age_hours'] < 1 ? __('settings.backup.less_than_hour') : __('settings.backup.hours', ['count' => $stats['age_hours']])]) }}
                    </strong>
                    <span class="cj-sub" style="color:inherit">
                        — {{ $stats['last_successful']->displayName() }} ({{ $stats['last_successful']->humanSize() }})
                    </span>
                    <div style="font-size:.85rem;margin-top:.25rem">
                        {{ $scheduleDescription ?? $settings->frequencyLabel() }}
                        @if ($job?->next_run_at && $job->isActive())
                            {{ __('settings.backup.next_run', ['time' => $job->next_run_at->format('Y-m-d H:i')]) }}
                            ({{ $job->next_run_at->diffForHumans() }})
                        @endif
                    </div>
                @endif
            </div>
            @if ($job)
                <a href="{{ route('CronJobs.show', $job->id) }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">
                    <i class="feather icon-clock"></i> {{ __('settings.backup.scheduled_job') }}
                </a>
            @endif
        </div>

        <!-- لوحة المعلومات -->
        <div class="stats-row mb-2">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-database"></i></div>
                <div class="stat-label">{{ __('settings.backup.available') }}</div>
                <div class="stat-value">{{ number_format($stats['available']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-archive"></i>
                    <span>{{ __('settings.backup.total_log', ['count' => number_format($stats['total'])]) }}</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-hard-drive"></i></div>
                <div class="stat-label">{{ __('settings.backup.used_space') }}</div>
                <div class="stat-value" style="font-size:1.25rem">{{ $stats['used_human'] }}</div>
                <div class="stat-trend {{ ($stats['usage_percent'] ?? 0) >= 90 ? 'down' : 'up' }}">
                    <i class="feather icon-pie-chart"></i>
                    <span>
                        @if ($stats['usage_percent'] !== null)
                            {{ __('settings.backup.percent_of', ['percent' => $stats['usage_percent'], 'limit' => $settings->max_storage_mb]) }}
                        @else
                            {{ __('settings.backup.no_limit_set') }}
                        @endif
                    </span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-x-circle"></i></div>
                <div class="stat-label">{{ __('settings.backup.failed_count') }}</div>
                <div class="stat-value">{{ number_format($stats['failed']) }}</div>
                <div class="stat-trend {{ $stats['failed'] > 0 ? 'down' : 'up' }}">
                    <i class="feather icon-alert-triangle"></i>
                    <span>
                        {{ $stats['last_failed']?->created_at?->format('Y-m-d H:i') ?? __('settings.backup.no_failures') }}
                    </span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-rotate-ccw"></i></div>
                <div class="stat-label">{{ __('settings.backup.restore_ops') }}</div>
                <div class="stat-value">{{ number_format($stats['restores']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-clock"></i>
                    <span>{{ $lastRestore?->started_at?->format('Y-m-d H:i') ?? __('settings.backup.never_restored') }}</span>
                </div>
            </div>
        </div>

        @if ($stats['usage_percent'] !== null && $stats['usage_percent'] >= 90)
            <div class="alert d-flex align-items-start"
                style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:10px">
                <i class="fas fa-exclamation-triangle mt-1 ml-2"></i>
                <div>
                    <strong>{{ __('settings.backup.storage_warning', ['percent' => $stats['usage_percent']]) }}</strong>
                    <span style="font-size:.9rem">
                        {!! __('settings.backup.storage_warning_body', ['link' => '<a href="'.route('Backup.settings').'" style="color:inherit;text-decoration:underline">'.__('settings.common.settings').'</a>']) !!}
                    </span>
                </div>
            </div>
        @endif

        <div class="row">
            <!-- ملخّص الإعدادات -->
            <div class="col-md-4 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ __('settings.backup.current_setup') }}</h4>
                    </div>
                    <div class="card-body">
                        <dl class="mb-0">
                            <div class="cj-def">
                                <dt>{{ __('settings.common.status') }}</dt>
                                <dd>
                                    <span class="badge badge-light-{{ $settings->is_enabled ? 'success' : 'secondary' }}">
                                        {{ $settings->is_enabled ? __('settings.common.active') : __('settings.common.inactive') }}
                                    </span>
                                </dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.backup_type') }}</dt>
                                <dd>{{ $settings->typeLabel() }}</dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.table_scope') }}</dt>
                                <dd>
                                    {{ $settings->tableSelectionLabel() }}
                                    @if ($settings->table_mode === 'include')
                                        <div class="cj-sub text-warning">{{ __('settings.backup.partial') }}</div>
                                    @endif
                                </dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.frequency') }}</dt>
                                <dd>{{ $settings->frequencyLabel() }}</dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.run_time') }}</dt>
                                <dd>{{ $settings->run_time ? \Illuminate\Support\Str::of($settings->run_time)->substr(0, 5) : '—' }}</dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.retention') }}</dt>
                                <dd>{{ $settings->retentionLabel() }}</dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.disk') }}</dt>
                                <dd>
                                    @php $diskKey = 'settings.backup.disks.'.$settings->disk; @endphp
                                    {{ __($diskKey) === $diskKey ? $settings->disk : __($diskKey) }}
                                </dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.encryption') }}</dt>
                                <dd>
                                    @if ($settings->encryptionActive())
                                        <span class="badge badge-light-success">
                                            <i class="feather icon-lock"></i> {{ __('settings.backup.encrypted') }}
                                        </span>
                                    @else
                                        <span class="badge badge-light-secondary">{{ __('settings.backup.not_encrypted') }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="card-footer d-flex flex-wrap" style="gap:.5rem">
                        @permission('settings.backup.create')
                            <form method="POST" action="{{ route('Backup.reconcile') }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary"
                                    title="{{ __('settings.backup.sync_disk_hint') }}">
                                    <i class="feather icon-refresh-cw"></i> {{ __('settings.backup.sync_disk') }}
                                </button>
                            </form>
                        @endpermission
                        @permission('settings.backup.delete')
                            <form method="POST" action="{{ route('Backup.cleanup') }}"
                                onsubmit="return confirm('{{ __('settings.backup.confirm_cleanup') }}')">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="feather icon-trash-2"></i> {{ __('settings.backup.cleanup') }}
                                </button>
                            </form>
                        @endpermission
                    </div>
                </div>

                @if ($lastRestore)
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.backup.last_restore') }}</h4>
                        </div>
                        <div class="card-body">
                            <span class="badge badge-light-{{ $lastRestore->statusColor() }}">
                                {{ $lastRestore->statusLabel() }}
                            </span>
                            <div class="cj-sub mt-1">
                                {{ $lastRestore->started_at?->format('Y-m-d H:i') }}
                                {{ __('settings.backup.by', ['name' => $lastRestore->executor?->name ?? __('settings.backup.unknown')]) }}
                            </div>
                            @if ($lastRestore->backup)
                                <div class="cj-sub">{{ __('settings.backup.from_file', ['file' => $lastRestore->backup->displayName()]) }}</div>
                            @endif
                            <a href="{{ route('Backup.restores') }}" class="btn btn-sm btn-outline-secondary mt-1">
                                {{ __('settings.backup.restore_log') }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            <!-- آخر النسخ -->
            <div class="col-md-8 col-12">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ __('settings.backup.latest') }}</h4>
                        <a href="{{ route('Backup.history') }}" class="btn btn-sm btn-outline-secondary">{{ __('settings.backup.view_all') }}</a>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('settings.backup.file') }}</th>
                                        <th>{{ __('settings.common.type') }}</th>
                                        <th>{{ __('settings.backup.source') }}</th>
                                        <th>{{ __('settings.backup.size') }}</th>
                                        <th>{{ __('settings.common.date') }}</th>
                                        <th class="text-center">{{ __('settings.common.status') }}</th>
                                        <th class="text-center" style="width:120px">{{ __('settings.common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($latest as $backup)
                                        <tr class="{{ $backup->isAvailable() ? '' : 'cj-row-off' }}">
                                            <td>
                                                <a href="{{ route('Backup.show', $backup->id) }}">
                                                    {{ $backup->displayName() }}
                                                </a>
                                                @if ($backup->is_locked)
                                                    <i class="feather icon-lock text-warning" title="{{ __('settings.backup.locked_hint') }}"></i>
                                                @endif
                                                @if ($backup->file_deleted_at)
                                                    <div class="cj-sub">{{ __('settings.backup.file_deleted_at', ['date' => $backup->file_deleted_at->format('Y-m-d')]) }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $backup->typeLabel() }}</td>
                                            <td>
                                                <span class="cj-sub">{{ $backup->triggerLabel() }}</span>
                                                @if ($backup->creator)
                                                    <div class="cj-sub">{{ $backup->creator->name }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $backup->humanSize() }}</td>
                                            <td>
                                                {{ $backup->created_at?->format('Y-m-d H:i') }}
                                                <div class="cj-sub">{{ $backup->humanDuration() }}</div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-light-{{ $backup->statusColor() }}">
                                                    {{ $backup->statusLabel() }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <div class="dropdown">
                                                        <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                            type="button" data-toggle="dropdown" aria-expanded="false">
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right">
                                                            <a class="dropdown-item" href="{{ route('Backup.show', $backup->id) }}">
                                                                <i class="feather icon-eye"></i> {{ __('settings.backup.view_details') }}
                                                            </a>

                                                            @permission('settings.backup.download')
                                                                @if ($backup->isAvailable())
                                                                    <a class="dropdown-item" href="{{ route('Backup.download', $backup->id) }}">
                                                                        <i class="feather icon-download"></i> {{ __('settings.backup.download') }}
                                                                    </a>
                                                                @endif
                                                            @endpermission

                                                            @permission('settings.backup.delete')
                                                                @if ($backup->isAvailable())
                                                                    <div class="dropdown-divider"></div>
                                                                    <form method="POST" action="{{ route('Backup.lock', $backup->id) }}">
                                                                        @csrf
                                                                        <button type="submit" class="dropdown-item">
                                                                            <i class="feather icon-{{ $backup->is_locked ? 'unlock' : 'lock' }}"></i>
                                                                            {{ $backup->is_locked ? __('settings.backup.unlock') : __('settings.backup.lock') }}
                                                                        </button>
                                                                    </form>
                                                                @endif

                                                                @if (! $backup->isRunning() && ! $backup->is_locked)
                                                                    <form method="POST" action="{{ route('Backup.destroy', $backup->id) }}"
                                                                        onsubmit="return confirm('{{ __('settings.backup.confirm_delete', ['file' => $backup->displayName()]) }}')">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="dropdown-item text-danger">
                                                                            <i class="feather icon-trash-2"></i> {{ __('settings.common.delete') }}
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            @endpermission
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="feather icon-database" style="font-size:2rem;color:#c8ccd4"></i>
                                                <div class="mt-1">{{ __('settings.backup.empty') }}</div>
                                                <div class="cj-sub">{{ __('settings.backup.empty_hint') }}</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @permission('settings.backup.create')
        {{-- نافذة الحفظ: سؤال واحد — «ما الذي تريد حفظه؟» — وثلاثة أجوبة.
             الأوّلان يُنتجان نسخة قابلة للاسترجاع فيمضيان إلى محرّك النسخ،
             والثالث (حقول بعينها) يُنتج ملفّ بيانات فيمضي إلى محرّك التصدير.
             الاستمارتان منفصلتان لأن وجهتيهما مختلفتان، والمستخدم لا يرى
             إلا اختياراً واحداً من ثلاثة --}}
        <div class="modal fade" id="runBackupModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('settings.backup.now_title') }}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body pb-0">
                        <label>{{ __('settings.backup.what_to_save') }}</label>
                        <div class="bk-choice mb-1" id="saveChoice">
                            <label>
                                <input type="radio" name="__save_mode" value="everything" checked>
                                <span class="bk-choice-title">
                                    <i class="feather icon-hard-drive"></i>
                                    {{ __('settings.backup.mode_everything') }}
                                    <span class="bk-choice-tag">{{ __('settings.backup.recommended') }}</span>
                                </span>
                                <span class="bk-choice-hint">{{ __('settings.backup.mode_everything_hint') }}</span>
                            </label>
                            <label>
                                <input type="radio" name="__save_mode" value="sections">
                                <span class="bk-choice-title">
                                    <i class="feather icon-grid"></i>
                                    {{ __('settings.backup.mode_sections') }}
                                </span>
                                <span class="bk-choice-hint">{{ __('settings.backup.mode_sections_hint') }}</span>
                            </label>
                            @if ($exportGroups !== [])
                                <label>
                                    <input type="radio" name="__save_mode" value="fields">
                                    <span class="bk-choice-title">
                                        <i class="feather icon-columns"></i>
                                        {{ __('settings.backup.mode_fields') }}
                                    </span>
                                    <span class="bk-choice-hint">{{ __('settings.backup.mode_fields_hint') }}</span>
                                </label>
                            @endif
                        </div>
                    </div>

                    <!-- (١) و(٢): نسخة قابلة للاسترجاع -->
                    <form method="POST" action="{{ route('Backup.run') }}" id="runBackupForm">
                        @csrf
                        {{-- نطاق «كل شيء» يُرسَل صريحاً: من اختار الحفظ الكامل
                             يقصده الآن مهما كان النطاق المحفوظ في الإعدادات.
                             يُعطَّل حين يختار المستخدم أقساماً فيرسل المنتقي
                             نطاقه بدلاً منه — والمعطَّل لا يُرسَل أصلاً --}}
                        <input type="hidden" name="table_mode" value="all" id="runFullScope">
                        <div class="modal-body pt-0">
                            <div class="form-group">
                                <label>{{ __('settings.backup.backup_type') }}</label>
                                <select name="type" class="form-control" id="runBackupType">
                                    <option value="">{{ __('settings.backup.from_settings', ['label' => $settings->typeLabel()]) }}</option>
                                    @foreach (\App\Models\BackupSetting::types() as $key => $meta)
                                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- نطاق هذه النسخة وحدها — لا يمسّ الإعدادات المحفوظة --}}
                            <div id="runSectionScope" style="display:none">
                                <div class="form-group">
                                    <label>{{ __('settings.backup.section_scope_mode') }}</label>
                                    <select name="table_mode" class="form-control" id="runTableMode">
                                        <option value="include">{{ __('settings.backup.include_sections') }}</option>
                                        <option value="exclude">{{ __('settings.backup.exclude_sections') }}</option>
                                    </select>
                                    <small class="text-muted">{{ __('settings.backup.one_off_hint') }}</small>
                                </div>

                                @include('sitting::backup._section_picker', [
                                    'name' => 'sections',
                                    'selected' => [],
                                    'id' => 'picker-manual',
                                ])

                                <div class="alert mt-1 mb-0" id="runPartialWarning"
                                    style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:8px;font-size:.82rem">
                                    <i class="feather icon-alert-triangle"></i>
                                    {{ __('settings.backup.partial_restore_warning') }}
                                </div>
                            </div>

                            <div class="alert mb-0"
                                style="background:#f4f5f7;border:1px solid #e0e3e9;color:#4b5563;border-radius:8px;font-size:.85rem">
                                <i class="feather icon-info"></i>
                                {{ __('settings.backup.inline_warning') }}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('settings.common.cancel') }}</button>
                            <button type="submit" class="btn btn-primary" id="runBackupSubmit">
                                <i class="feather icon-play"></i> {{ __('settings.backup.start') }}
                            </button>
                        </div>
                    </form>

                    <!-- (٣): حقول بعينها — ملفّ بيانات لا نسخة استرجاع -->
                    @if ($exportGroups !== [])
                        <form method="POST" action="{{ route('DataExport.run') }}" id="runFieldsForm" style="display:none">
                            @csrf
                            <input type="hidden" name="selections[0][key]" id="fieldEntityKey">

                            <div class="modal-body pt-0">
                                <div class="form-group">
                                    <label>{{ __('settings.backup.field_entity') }}</label>
                                    <select class="form-control" id="fieldEntity">
                                        <option value="">{{ __('settings.backup.field_entity_placeholder') }}</option>
                                        @foreach ($exportGroups as $group => $entities)
                                            <optgroup label="{{ $group }}">
                                                @foreach ($entities as $entity)
                                                    <option value="{{ $entity['key'] }}">{{ $entity['label'] }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group" id="fieldListBlock" style="display:none">
                                    <label>{{ __('settings.backup.field_list') }}</label>
                                    <div class="bk-picker" id="picker-fields">
                                        <div class="bk-picker-bar">
                                            <input type="text" class="form-control form-control-sm bk-picker-search"
                                                placeholder="{{ __('settings.backup.search_field') }}" autocomplete="off">
                                            <button type="button" class="btn btn-sm btn-outline-secondary bk-picker-all">{{ __('settings.backup.select_all') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary bk-picker-none">{{ __('settings.backup.select_none') }}</button>
                                            <span class="bk-picker-count cj-sub"></span>
                                        </div>
                                        <div class="bk-picker-list" id="fieldList"></div>
                                    </div>
                                    <small class="text-muted">{{ __('settings.backup.field_list_hint') }}</small>
                                </div>

                                <div class="form-group">
                                    <label>{{ __('settings.backup.field_format') }}</label>
                                    <select name="format" class="form-control">
                                        @foreach ($exportFormats as $key => $meta)
                                            <option value="{{ $key }}" {{ $key === 'xlsx' ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="alert mb-0"
                                    style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:8px;font-size:.82rem">
                                    <i class="feather icon-alert-triangle"></i>
                                    {{ __('settings.backup.fields_not_restorable') }}
                                    <a href="{{ route('DataExport.index') }}">{{ __('settings.backup.fields_advanced_link') }}</a>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('settings.common.cancel') }}</button>
                                <button type="submit" class="btn btn-primary" id="runFieldsSubmit" disabled>
                                    <i class="feather icon-download"></i> {{ __('settings.backup.field_save') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endpermission
@endsection

@section('scripts')
    <script>
        // النسخ عملية طويلة وزرّ يبقى قابلاً للضغط يعني طلبين متوازيين —
        // يرفضه القفل في الخادم، لكن المسؤول يرى «فشل» بلا سبب مفهوم
        document.getElementById('runBackupForm')?.addEventListener('submit', function () {
            var button = document.getElementById('runBackupSubmit');
            button.disabled = true;
            button.innerHTML = '<i class="feather icon-loader"></i> ' + @json(__('settings.backup.running'));
        });

        // اختيار «ما الذي تريد حفظه؟»: يبدّل بين استمارتَي النسخة والتصدير
        (function () {
            var choice = document.getElementById('saveChoice');

            if (!choice) return;

            var backupForm = document.getElementById('runBackupForm');
            var fieldsForm = document.getElementById('runFieldsForm');
            var scope = document.getElementById('runSectionScope');
            var mode = document.getElementById('runTableMode');
            var warning = document.getElementById('runPartialWarning');
            var type = document.getElementById('runBackupType');

            function selected() {
                var picked = choice.querySelector('input[name="__save_mode"]:checked');

                return picked ? picked.value : 'everything';
            }

            function sync() {
                var value = selected();
                var isFields = value === 'fields';

                backupForm.style.display = isFields ? 'none' : '';

                if (fieldsForm) {
                    fieldsForm.style.display = isFields ? '' : 'none';
                }

                if (isFields) return;

                // نسخة مرفقات فقط لا بيانات فيها — اختيار الأقسام بلا معنى
                var hidden = value !== 'sections' || type.value === 'files';

                scope.style.display = hidden ? 'none' : '';
                warning.style.display = ! hidden && mode.value === 'include' ? '' : 'none';

                // الحقول المخفيّة تُعطَّل: مربّعات أقسام مؤشَّرة ثم أُخفيت
                // تُرسَل مع الطلب فتُنتج نسخة جزئية طلب المستخدم أن تكون كاملة
                scope.querySelectorAll('input, select').forEach(function (input) {
                    input.disabled = hidden;
                });

                // نطاقٌ واحد يُرسَل لا اثنان: المخفيّ منهما معطَّل دائماً
                document.getElementById('runFullScope').disabled = ! hidden;
            }

            choice.addEventListener('change', sync);
            mode.addEventListener('change', sync);
            type.addEventListener('change', sync);
            sync();
        })();

        // حقول الكيان المختار — تُحمَّل عند الطلب لا مع الصفحة
        (function () {
            var entity = document.getElementById('fieldEntity');

            if (!entity) return;

            var keyInput = document.getElementById('fieldEntityKey');
            var block = document.getElementById('fieldListBlock');
            var list = document.getElementById('fieldList');
            var submit = document.getElementById('runFieldsSubmit');
            var url = @json(route('DataExport.columns', ['key' => '__key__']));

            entity.addEventListener('change', function () {
                var key = entity.value;

                keyInput.value = key;
                submit.disabled = true;
                list.innerHTML = '';
                block.style.display = key ? '' : 'none';

                if (!key) return;

                list.innerHTML = '<div class="p-2 cj-sub">' + @json(__('settings.common.loading')) + '</div>';

                fetch(url.replace('__key__', key), { headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : Promise.reject(response); })
                    .then(function (data) {
                        list.innerHTML = '';

                        data.columns.forEach(function (column) {
                            var item = document.createElement('label');
                            item.className = 'bk-picker-item';
                            item.setAttribute('data-name', column.label);

                            var box = document.createElement('input');
                            box.type = 'checkbox';
                            box.name = 'selections[0][columns][]';
                            box.value = column.name;
                            box.checked = true; // الافتراضي كل الحقول، والتضييق اختيار

                            var text = document.createElement('span');
                            text.textContent = column.label;

                            item.appendChild(box);
                            item.appendChild(text);
                            list.appendChild(item);
                        });

                        submit.disabled = false;
                    })
                    .catch(function () {
                        list.innerHTML = '<div class="p-2 cj-sub">' + @json(__('settings.backup.field_load_failed')) + '</div>';
                    });
            });

            document.getElementById('runFieldsForm').addEventListener('submit', function (event) {
                // حقلٌ واحد على الأقلّ: استمارة بلا حقول تُنتج ملفّاً فارغاً
                if (!list.querySelector('input:checked')) {
                    event.preventDefault();
                    alert(@json(__('settings.backup.pick_one_field')));
                }
            });
        })();
    </script>
@endsection
