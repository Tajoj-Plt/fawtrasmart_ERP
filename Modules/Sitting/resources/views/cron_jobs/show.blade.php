@extends('sitting::master')

@section('title', $job->name)

@section('content')
    @include('sitting::cron_jobs._styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-clock"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title"><i class="feather icon-{{ $job->typeIcon() }} ml-1"></i>{{ $job->name }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('CronJobs.index') }}">{{ __('settings.cron.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ $job->name }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            @permission('settings.cron.run')
                <form method="POST" action="{{ route('CronJobs.run', $job->id) }}" class="d-inline"
                    onsubmit="return confirm('{{ __('settings.cron.confirm_run_short', ['name' => $job->name]) }}')">
                    @csrf
                    <button class="btn btn-success"><i class="feather icon-play"></i> {{ __('settings.cron.run_now') }}</button>
                </form>
            @endpermission
            @permission('settings.cron.update')
                <a href="{{ route('CronJobs.edit', $job->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> {{ __('settings.common.edit') }}
                </a>
            @endpermission

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- إحصاءات المهمة -->
        <div class="stats-row mb-2">
            <div class="stat-card-modern {{ $job->statusColor() === 'success' ? 'success' : 'warning' }}">
                <div class="stat-icon"><i class="feather icon-{{ \App\Models\CronJob::STATUSES[$job->status]['icon'] ?? 'circle' }}"></i></div>
                <div class="stat-label">{{ __('settings.common.status') }}</div>
                <div class="stat-value" style="font-size:1.05rem">{{ $job->statusLabel() }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-clock"></i>
                    <span>{{ $job->isActive() && $job->next_run_at ? __('settings.cron.next_in', ['when' => $job->next_run_at->diffForHumans()]) : __('settings.cron.no_scheduled_run') }}</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">{{ __('settings.cron.success') }}</div>
                <div class="stat-value">{{ number_format($stats['success']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>{{ __('settings.cron.of_total_runs', ['count' => number_format($stats['total'])]) }}</span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-x-circle"></i></div>
                <div class="stat-label">{{ __('settings.cron.failure') }}</div>
                <div class="stat-value">{{ number_format($stats['failed']) }}</div>
                <div class="stat-trend {{ $job->consecutive_failures > 0 ? 'down' : 'up' }}">
                    <i class="feather icon-repeat"></i>
                    <span>{{ __('settings.cron.consecutive_failures', ['count' => $job->consecutive_failures]) }}</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-clock"></i></div>
                <div class="stat-label">{{ __('settings.cron.avg_duration') }}</div>
                <div class="stat-value" style="font-size:1.05rem">
                    {{ $stats['avg_duration'] ? ($stats['avg_duration'] < 1000 ? __('settings.cron.ms', ['count' => $stats['avg_duration']]) : __('settings.cron.seconds', ['count' => round($stats['avg_duration'] / 1000, 1)])) : '—' }}
                </div>
                <div class="stat-trend up">
                    <i class="feather icon-zap"></i>
                    <span>{{ __('settings.cron.timeout_max', ['count' => $job->timeout]) }}</span>
                </div>
            </div>
        </div>

        @if ($job->last_error)
            <div class="alert" style="background:#fdecea;border:1px solid #f8c4be;color:#92211a;border-radius:10px">
                <strong><i class="fas fa-exclamation-triangle"></i> {{ __('settings.cron.last_error') }}</strong>
                <div style="font-size:.85rem;margin-top:.3rem">{{ Str::limit($job->last_error, 500) }}</div>
            </div>
        @endif

        <div class="row">
            <!-- التفاصيل -->
            <div class="col-lg-5 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-info ml-1"></i> {{ __('settings.cron.details') }}</h4>
                    </div>
                    <div class="card-body">
                        <dl class="mb-0">
                            <div class="cj-def"><dt>{{ __('settings.cron.key') }}</dt><dd><span class="cj-mono">{{ $job->key }}</span></dd></div>
                            <div class="cj-def"><dt>{{ __('settings.common.type') }}</dt><dd>{{ $job->typeLabel() }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.command') }}</dt><dd><span class="cj-mono">{{ $job->handler }}</span></dd></div>
                            @if ($job->parameters)
                                <div class="cj-def">
                                    <dt>{{ __('settings.cron.options') }}</dt>
                                    <dd>
                                        @foreach ($job->parameters as $k => $v)
                                            <span class="cj-mono">{{ $k }}={{ $v }}</span>
                                        @endforeach
                                    </dd>
                                </div>
                            @endif
                            <div class="cj-def"><dt>{{ __('settings.cron.expression') }}</dt><dd><span class="cj-mono">{{ $job->cron_expression }}</span></dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.description') }}</dt><dd style="font-weight:500">{{ $description }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.prevent_overlap') }}</dt><dd>{{ $job->prevent_overlap ? __('settings.common.active') : __('settings.common.inactive') }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.notify_failure') }}</dt><dd>{{ $job->notify_on_failure ? __('settings.common.active') : __('settings.common.inactive') }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.notify_success') }}</dt><dd>{{ $job->notify_on_success ? __('settings.common.active') : __('settings.common.inactive') }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.system_job') }}</dt><dd>{{ $job->is_system ? __('settings.cron.system_yes') : __('settings.common.no') }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.last_run') }}</dt><dd>{{ $job->last_run_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.last_finished') }}</dt><dd>{{ $job->last_finished_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
                        </dl>

                        @if ($job->description)
                            <div class="mt-1 p-1" style="background:#f7f8fa;border-radius:8px;font-size:.85rem">
                                {{ $job->description }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-calendar ml-1"></i> {{ __('settings.cron.upcoming') }}</h4>
                    </div>
                    <div class="card-body">
                        @if (! $job->isActive())
                            <div class="cj-sub">{{ __('settings.cron.inactive_note', ['state' => $job->statusLabel()]) }}</div>
                        @elseif (empty($nextRuns))
                            <div class="cj-sub">{{ __('settings.cron.schedule_unreadable') }}</div>
                        @else
                            <ol class="mb-0" style="padding-inline-start:1.2rem">
                                @foreach ($nextRuns as $run)
                                    <li style="padding:.2rem 0">
                                        {{ \Carbon\Carbon::instance($run)->format('Y-m-d H:i') }}
                                        <span class="cj-sub">({{ \Carbon\Carbon::instance($run)->diffForHumans() }})</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                </div>

                @permission('settings.cron.delete')
                    @if ($job->isDeletable())
                        <div class="card cj-danger-zone">
                            <div class="card-header">
                                <h4 class="card-title text-danger"><i class="feather icon-alert-triangle ml-1"></i> {{ __('settings.cron.danger_zone') }}</h4>
                            </div>
                            <div class="card-body">
                                <p style="font-size:.85rem" class="mb-1">
                                    {{ __('settings.cron.delete_note') }}
                                </p>
                                <form method="POST" action="{{ route('CronJobs.destroy', $job->id) }}"
                                    onsubmit="return confirm('{{ __('settings.cron.confirm_delete_full', ['name' => $job->name]) }}')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm"><i class="feather icon-trash-2"></i> {{ __('settings.cron.delete_job') }}</button>
                                </form>
                            </div>
                        </div>
                    @endif
                @endpermission
            </div>

            <!-- سجلّ التنفيذ -->
            <div class="col-lg-7 col-12">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-list ml-1"></i> {{ __('settings.cron.exec_log') }}</h4>
                        <a href="{{ route('CronJobs.executions', ['cron_job_id' => $job->id]) }}"
                            class="btn btn-sm btn-outline-secondary">{{ __('settings.cron.full_log') }}</a>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('settings.common.status') }}</th>
                                        <th>{{ __('settings.cron.started') }}</th>
                                        <th>{{ __('settings.cron.duration') }}</th>
                                        <th>{{ __('settings.cron.trigger') }}</th>
                                        <th>{{ __('settings.cron.details') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($executions as $execution)
                                        <tr>
                                            <td>
                                                <span class="badge badge-light-{{ $execution->statusColor() }}">
                                                    {{ $execution->statusLabel() }}
                                                </span>
                                            </td>
                                            <td>
                                                {{ $execution->started_at?->format('Y-m-d H:i:s') ?? '—' }}
                                                <div class="cj-sub">{{ $execution->started_at?->diffForHumans() }}</div>
                                            </td>
                                            <td>{{ $execution->humanDuration() }}</td>
                                            <td>
                                                {{ $execution->triggerLabel() }}
                                                @if ($execution->executor)
                                                    <div class="cj-sub">{{ $execution->executor->name }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('CronJobs.executions.show', $execution->id) }}"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    <i class="feather icon-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <span class="cj-sub">{{ __('settings.cron.never_ran_job') }}</span>
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
        </div>
    </div>
@endsection
