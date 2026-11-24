@extends('sitting::master')

@section('title', __('settings.cron.execution_title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-clock"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.cron.execution_no', ['id' => $execution->id, 'name' => $execution->task_name]) }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('CronJobs.index') }}">{{ __('settings.cron.title') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('CronJobs.executions') }}">{{ __('settings.cron.exec_log') }}</a></li>
                        <li class="breadcrumb-item active">#{{ $execution->id }}</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')

        <div class="row">
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <span class="badge badge-light-{{ $execution->statusColor() }}">{{ $execution->statusLabel() }}</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <dl class="mb-0">
                            <div class="cj-def">
                                <dt>{{ __('settings.cron.job') }}</dt>
                                <dd>
                                    @if ($execution->job)
                                        <a href="{{ route('CronJobs.show', $execution->job->id) }}">{{ $execution->task_name }}</a>
                                    @else
                                        {{ $execution->task_name }} <span class="cj-sub">{{ __('settings.cron.deleted_note') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="cj-def"><dt>{{ __('settings.cron.trigger') }}</dt><dd>{{ $execution->triggerLabel() }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.executed_by') }}</dt><dd>{{ $execution->executor?->name ?? __('settings.cron.scheduler') }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.started') }}</dt><dd>{{ $execution->started_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.finished') }}</dt><dd>{{ $execution->finished_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.duration') }}</dt><dd>{{ $execution->humanDuration() }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.exit_code') }}</dt><dd><span class="cj-mono">{{ $execution->exit_code ?? '—' }}</span></dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.environment') }}</dt><dd>{{ $execution->environment ?? '—' }}</dd></div>
                            <div class="cj-def"><dt>{{ __('settings.cron.hostname') }}</dt><dd><span class="cj-mono">{{ $execution->hostname ?? '—' }}</span></dd></div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 col-12">
                @if ($execution->error_message)
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title text-danger"><i class="feather icon-alert-octagon ml-1"></i> {{ __('settings.cron.error_message') }}</h4>
                        </div>
                        <div class="card-body">
                            <pre class="cj-out err">{{ $execution->error_message }}</pre>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-terminal ml-1"></i> {{ __('settings.cron.output') }}</h4>
                    </div>
                    <div class="card-body">
                        @if ($execution->output)
                            <pre class="cj-out">{{ $execution->output }}</pre>
                        @else
                            <div class="cj-sub">{{ __('settings.cron.no_output') }}</div>
                        @endif
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('CronJobs.executions') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-arrow-right"></i> {{ __('settings.cron.back_to_log') }}
                    </a>
                    @if ($execution->job)
                        @permission('settings.cron.run')
                            <form method="POST" action="{{ route('CronJobs.run', $execution->job->id) }}"
                                onsubmit="return confirm('{{ __('settings.cron.confirm_rerun', ['name' => $execution->task_name]) }}')">
                                @csrf
                                <button class="btn btn-success"><i class="feather icon-refresh-cw"></i> {{ __('settings.cron.rerun') }}</button>
                            </form>
                        @endpermission
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
