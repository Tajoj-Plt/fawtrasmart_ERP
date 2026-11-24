@extends('sitting::master')

@section('title', __('settings.backup.history_title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-database"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.backup.history_title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('Backup.index') }}">{{ __('settings.backup.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.backup.history_crumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('Backup.restores') }}" class="btn btn-outline-secondary">
                <i class="feather icon-rotate-ccw"></i> {{ __('settings.backup.restore_log') }}
            </a>
            @permission('settings.backup.delete')
                <form method="POST" action="{{ route('Backup.cleanup') }}" class="d-inline"
                    onsubmit="return confirm('{{ __('settings.backup.confirm_cleanup') }}')">
                    @csrf
                    <button class="btn btn-outline-danger">
                        <i class="feather icon-trash-2"></i> {{ __('settings.backup.cleanup_short') }}
                    </button>
                </form>
            @endpermission
            <a href="{{ route('Backup.index') }}" class="btn btn-primary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.common.back') }}
            </a>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- لوحة المعلومات -->
        <div class="stats-row mb-2">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-archive"></i></div>
                <div class="stat-label">{{ __('settings.backup.total_records') }}</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-check-circle"></i>
                    <span>{{ __('settings.backup.still_present', ['count' => number_format($stats['available'])]) }}</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-hard-drive"></i></div>
                <div class="stat-label">{{ __('settings.backup.used_space') }}</div>
                <div class="stat-value" style="font-size:1.25rem">{{ $stats['used_human'] }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-shield"></i>
                    <span>{{ $settings->retentionLabel() }}</span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-x-circle"></i></div>
                <div class="stat-label">{{ __('settings.backup.failed_count') }}</div>
                <div class="stat-value">{{ number_format($stats['failed']) }}</div>
                <div class="stat-trend {{ $stats['failed'] > 0 ? 'down' : 'up' }}">
                    <i class="feather icon-alert-triangle"></i>
                    <span>{{ $stats['last_failed']?->created_at?->format('Y-m-d H:i') ?? __('settings.backup.no_failures_short') }}</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-clock"></i></div>
                <div class="stat-label">{{ __('settings.backup.last_successful') }}</div>
                <div class="stat-value" style="font-size:1.05rem">
                    {{ $stats['last_successful']?->completed_at?->format('Y-m-d') ?? '—' }}
                </div>
                <div class="stat-trend up">
                    <i class="feather icon-database"></i>
                    <span>{{ $stats['last_successful']?->humanSize() ?? __('settings.backup.no_backups_yet') }}</span>
                </div>
            </div>
        </div>

        <!-- المرشّحات -->
        <div class="card filter-card-modern mb-2">
            <div class="card-body">
                <form method="GET" action="{{ route('Backup.history') }}">
                    <div class="row align-items-end">
                        <div class="col-md-3 col-12 mb-1">
                            <label>{{ __('settings.common.search') }}</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                                placeholder="{{ __('settings.backup.search_placeholder') }}">
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.common.status') }}</label>
                            <select name="status" class="form-control">
                                <option value="">{{ __('settings.common.all') }}</option>
                                @foreach ($statuses as $key => $meta)
                                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                                        {{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.common.type') }}</label>
                            <select name="type" class="form-control">
                                <option value="">{{ __('settings.common.all') }}</option>
                                @foreach ($types as $key => $meta)
                                    <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>
                                        {{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.backup.source') }}</label>
                            <select name="trigger" class="form-control">
                                <option value="">{{ __('settings.common.all') }}</option>
                                @foreach ($triggers as $key => $meta)
                                    <option value="{{ $key }}" {{ request('trigger') === $key ? 'selected' : '' }}>
                                        {{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-12 mb-1">
                            <label>{{ __('settings.backup.date_range') }}</label>
                            <div class="d-flex" style="gap:.4rem">
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-12 mb-1">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="available" name="available"
                                    value="1" {{ request()->boolean('available') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="available">{{ __('settings.backup.available_only') }}</label>
                            </div>
                        </div>

                        <div class="col-md-2 col-12 mb-1 mr-auto">
                            <div class="d-flex">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i></button>
                                <a href="{{ route('Backup.history') }}" class="btn btn-secondary btn-block mt-0 ml-1">
                                    <i class="fa fa-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- الجدول -->
        <div class="card table-card-modern">
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('settings.backup.file') }}</th>
                                <th>{{ __('settings.common.type') }}</th>
                                <th>{{ __('settings.backup.source') }}</th>
                                <th>{{ __('settings.backup.size') }}</th>
                                <th>{{ __('settings.backup.duration') }}</th>
                                <th>{{ __('settings.common.date') }}</th>
                                <th class="text-center">{{ __('settings.common.status') }}</th>
                                <th class="text-center" style="width:170px">{{ __('settings.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($backups as $backup)
                                <tr class="{{ $backup->isAvailable() ? '' : 'cj-row-off' }}">
                                    <td>
                                        <a href="{{ route('Backup.show', $backup->id) }}">
                                            {{ $backup->displayName() }}
                                        </a>
                                        @if ($backup->is_locked)
                                            <i class="feather icon-lock text-warning" title="{{ __('settings.backup.locked_auto_hint') }}"></i>
                                        @endif
                                        @if ($backup->file_deleted_at)
                                            <div class="cj-sub">{{ __('settings.backup.file_deleted_at', ['date' => $backup->file_deleted_at->format('Y-m-d H:i')]) }}</div>
                                        @elseif ($backup->status === \App\Models\Backup::STATUS_FAILED && $backup->error_message)
                                            <div class="cj-sub text-danger">{{ Str::limit($backup->error_message, 60) }}</div>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $backup->typeLabel() }}
                                        @if ($backup->is_encrypted)
                                            <i class="feather icon-lock text-success" title="{{ __('settings.backup.encrypted') }}"></i>
                                        @endif
                                        @if ($backup->isPartial())
                                            {{-- بدون هذا الوسم تبدو نسخة ناقصة جداول كاملةً في السجلّ --}}
                                            <div class="cj-sub">
                                                <span class="badge badge-light-warning" style="font-size:.62rem">{{ __('settings.backup.partial_short') }}</span>
                                                {{ __('settings.backup.sections_count', ['count' => count($backup->sectionLabels())]) }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $backup->triggerLabel() }}
                                        @if ($backup->creator)
                                            <div class="cj-sub">{{ $backup->creator->name }}</div>
                                        @elseif ($backup->cronJob)
                                            <div class="cj-sub">
                                                <a href="{{ route('CronJobs.show', $backup->cronJob->id) }}">
                                                    {{ $backup->cronJob->name }}
                                                </a>
                                            </div>
                                        @endif
                                    </td>

                                    <td>{{ $backup->humanSize() }}</td>
                                    <td>{{ $backup->humanDuration() }}</td>

                                    <td>
                                        {{ $backup->created_at?->format('Y-m-d H:i') }}
                                        <div class="cj-sub">{{ $backup->created_at?->diffForHumans() }}</div>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge badge-light-{{ $backup->statusColor() }}">
                                            {{ $backup->statusLabel() }}
                                        </span>
                                        @if ($backup->trigger === \App\Models\Backup::TRIGGER_PRE_RESTORE)
                                            <div class="cj-sub"><i class="feather icon-shield"></i> {{ __('settings.backup.safety_copy') }}</div>
                                        @endif
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

                                                    @permission('settings.backup.restore')
                                                        @if ($backup->isRestorable())
                                                            <a class="dropdown-item text-warning"
                                                                href="{{ route('Backup.show', $backup->id) }}#restore">
                                                                <i class="feather icon-rotate-ccw"></i> {{ __('settings.backup.restore') }}
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
                                    <td colspan="8" class="text-center py-4">
                                        <i class="feather icon-database" style="font-size:2rem;color:#c8ccd4"></i>
                                        <div class="mt-1">{{ __('settings.backup.no_match') }}</div>
                                        <div class="cj-sub">
                                            {{ __('settings.backup.no_match_hint') }}
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                    @include('sitting::components.table-foot', ['paginator' => $backups])
            </div>
        </div>
    </div>
@endsection
