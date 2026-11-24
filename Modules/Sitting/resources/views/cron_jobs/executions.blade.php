@extends('sitting::master')

@section('title', __('settings.cron.executions_title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-clock"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.cron.executions_title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('CronJobs.index') }}">{{ __('settings.cron.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.cron.exec_log') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <button class="btn btn-outline-danger" data-toggle="modal" data-target="#cleanupModal">
                <i class="feather icon-trash-2"></i> {{ __('settings.cron.cleanup_log') }}
            </button>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stats-row mb-2">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-activity"></i></div>
                <div class="stat-label">{{ __('settings.cron.total_executions') }}</div>
                <div class="stat-value">{{ number_format($summary['total']) }}</div>
                <div class="stat-trend up"><i class="feather icon-calendar"></i> <span>{{ __('settings.cron.today', ['count' => $summary['today']]) }}</span></div>
            </div>
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">{{ __('settings.cron.succeeded') }}</div>
                <div class="stat-value">{{ number_format($summary['success']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-percent"></i>
                    <span>{{ __('settings.cron.success_rate', ['percent' => $summary['total'] ? round($summary['success'] / $summary['total'] * 100) : 0]) }}</span>
                </div>
            </div>
            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-x-circle"></i></div>
                <div class="stat-label">{{ __('settings.cron.failed') }}</div>
                <div class="stat-value">{{ number_format($summary['failed']) }}</div>
                <div class="stat-trend {{ $summary['failed'] ? 'down' : 'up' }}">
                    <i class="feather icon-alert-triangle"></i> <span>{{ __('settings.cron.needs_review') }}</span>
                </div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-skip-forward"></i></div>
                <div class="stat-label">{{ __('settings.cron.skipped') }}</div>
                <div class="stat-value">{{ number_format($summary['skipped']) }}</div>
                <div class="stat-trend up"><i class="feather icon-lock"></i> <span>{{ __('settings.cron.overlap_prevented') }}</span></div>
            </div>
        </div>

        <!-- المرشّحات -->
        <div class="card filter-card-modern mb-2">
            <div class="card-body">
                <form method="GET" action="{{ route('CronJobs.executions') }}">
                    <div class="row align-items-end">
                        <div class="col-md-3 col-12 mb-1">
                            <label>{{ __('settings.common.search') }}</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                                placeholder="{{ __('settings.cron.exec_search_placeholder') }}">
                        </div>

                        <div class="col-md-3 col-12 mb-1">
                            <label>{{ __('settings.cron.job') }}</label>
                            <select name="cron_job_id" class="form-control">
                                <option value="">{{ __('settings.cron.all_jobs') }}</option>
                                @foreach ($jobs as $job)
                                    <option value="{{ $job->id }}"
                                        {{ (string) request('cron_job_id') === (string) $job->id ? 'selected' : '' }}>
                                        {{ $job->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-6 mb-1">
                            <label>{{ __('settings.common.status') }}</label>
                            <select name="status" class="form-control">
                                <option value="">{{ __('settings.common.all') }}</option>
                                @foreach ($statuses as $key => $meta)
                                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                                        {{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-6 mb-1">
                            <label>{{ __('settings.cron.trigger') }}</label>
                            <select name="trigger" class="form-control">
                                <option value="">{{ __('settings.common.all') }}</option>
                                @foreach ($triggers as $key => $meta)
                                    <option value="{{ $key }}" {{ request('trigger') === $key ? 'selected' : '' }}>
                                        {{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-6 mb-1">
                            <label>{{ __('settings.cron.date_from') }}</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>

                        <div class="col-md-2 col-6 mb-1">
                            <label>{{ __('settings.cron.date_to') }}</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>&nbsp;</label>
                            <div class="d-flex">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> {{ __('settings.cron.filter') }}</button>
                                <a href="{{ route('CronJobs.executions') }}" class="btn btn-secondary btn-block mt-0 ml-1">
                                    <i class="fa fa-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card table-card-modern">
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('settings.cron.job') }}</th>
                                <th>{{ __('settings.common.status') }}</th>
                                <th>{{ __('settings.cron.started') }}</th>
                                <th>{{ __('settings.cron.finished') }}</th>
                                <th>{{ __('settings.cron.duration') }}</th>
                                <th>{{ __('settings.cron.trigger') }}</th>
                                <th>{{ __('settings.cron.environment') }}</th>
                                <th class="text-center" style="width:110px">{{ __('settings.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($executions as $execution)
                                <tr>
                                    <td class="cj-sub">{{ $execution->id }}</td>
                                    <td>
                                        @if ($execution->job)
                                            <a href="{{ route('CronJobs.show', $execution->job->id) }}">
                                                <strong>{{ $execution->task_name }}</strong>
                                            </a>
                                        @else
                                            <strong>{{ $execution->task_name }}</strong>
                                            <span class="badge badge-light-secondary" style="font-size:.65rem">{{ __('settings.cron.deleted_badge') }}</span>
                                        @endif
                                        @if ($execution->error_message)
                                            <div class="cj-sub text-danger">{{ Str::limit($execution->error_message, 80) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light-{{ $execution->statusColor() }}">
                                            {{ $execution->statusLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $execution->started_at?->format('Y-m-d H:i:s') ?? '—' }}
                                        <div class="cj-sub">{{ $execution->started_at?->diffForHumans() }}</div>
                                    </td>
                                    <td>{{ $execution->finished_at?->format('H:i:s') ?? '—' }}</td>
                                    <td>{{ $execution->humanDuration() }}</td>
                                    <td>
                                        {{ $execution->triggerLabel() }}
                                        @if ($execution->executor)
                                            <div class="cj-sub">{{ $execution->executor->name }}</div>
                                        @endif
                                    </td>
                                    <td class="cj-sub">
                                        {{ $execution->environment }}
                                        @if ($execution->hostname)
                                            <div>{{ Str::limit($execution->hostname, 18) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="cj-actions">
                                            <a href="{{ route('CronJobs.executions.show', $execution->id) }}"
                                                class="btn btn-sm btn-outline-secondary" title="{{ __('settings.cron.details') }}">
                                                <i class="feather icon-eye"></i>
                                            </a>
                                            <form method="POST" action="{{ route('CronJobs.executions.destroy', $execution->id) }}"
                                                onsubmit="return confirm('{{ __('settings.cron.confirm_delete_execution') }}')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="{{ __('settings.common.delete') }}">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="feather icon-inbox" style="font-size:2rem;color:#c8ccd4"></i>
                                        <div class="mt-1">{{ __('settings.cron.no_executions') }}</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
                @include('sitting::components.table-foot', ['paginator' => $executions])
        </div>
    </div>

    <!-- تنظيف السجلّ -->
    <div class="modal fade" id="cleanupModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('CronJobs.executions.cleanup') }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('settings.cron.cleanup_title') }}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="alert" style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:8px">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ __('settings.cron.cleanup_warning') }}
                        </div>
                        <div class="form-group mb-0">
                            <label>{{ __('settings.cron.cleanup_days') }}</label>
                            <input type="number" name="days" class="form-control" min="1" max="3650" value="30" required>
                            <small class="text-muted">
                                {{ __('settings.cron.cleanup_hint') }}
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('settings.common.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('settings.cron.cleanup_submit') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
