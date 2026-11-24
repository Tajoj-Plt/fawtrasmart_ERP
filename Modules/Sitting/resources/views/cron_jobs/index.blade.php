@extends('sitting::master')

@section('title', __('settings.cron.title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-clock"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.cron.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.cron.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('CronJobs.executions') }}" class="btn btn-outline-secondary">
                <i class="feather icon-list"></i> {{ __('settings.cron.exec_log') }}
            </a>
            @permission('settings.cron.create')
                <a href="{{ route('CronJobs.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('settings.cron.new') }}
                </a>
            @endpermission

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- حالة المجدول: بدونها تبدو كل المهام «نشطة» بينما لا شيء يُنفَّذ --}}
        @php $sch = $stats['scheduler']; @endphp
        <div class="cj-health {{ $sch['status'] }}">
            <span class="dot"></span>
            <div>
                <strong>{{ __('settings.cron.scheduler_state', ['label' => $sch['label']]) }}</strong>
                @if ($sch['last_heartbeat'])
                    <span class="cj-sub" style="color:inherit">
                        {{ __('settings.cron.last_heartbeat', ['time' => $sch['last_heartbeat']->format('Y-m-d H:i:s'), 'minutes' => $sch['minutes_ago']]) }}
                    </span>
                @endif
                <div style="font-size:.85rem;margin-top:.25rem">{{ $sch['message'] }}</div>
                @if ($sch['status'] !== 'healthy')
                    <div class="cj-mono" style="margin-top:.5rem">* * * * * cd {{ base_path() }} &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</div>
                @endif
                {{-- المجدول الداخلي: بلا Cron على الخادم تُشغّل زياراتُ النظام الدورةَ --}}
                <div style="font-size:.85rem;margin-top:.5rem">
                    @if ($webTick['enabled'])
                        <strong>{{ __('settings.cron.web_tick_on') }}</strong>
                        {{ __('settings.cron.web_tick_on_hint') }}
                        <div class="cj-mono" style="margin-top:.35rem" dir="ltr">{{ $webTick['url'] }}</div>
                        <span class="cj-sub" style="color:inherit">{{ __('settings.cron.web_tick_url_hint') }}</span>
                    @else
                        <strong>{{ __('settings.cron.web_tick_off') }}</strong>
                        {{ __('settings.cron.web_tick_off_hint') }}
                    @endif
                </div>
            </div>
        </div>

        <!-- لوحة المعلومات -->
        <div class="stats-row mb-2">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-clock"></i></div>
                <div class="stat-label">{{ __('settings.cron.total_jobs') }}</div>
                <div class="stat-value">{{ $stats['total_jobs'] }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-play-circle"></i>
                    <span>{{ __('settings.cron.jobs_breakdown', ['active' => $stats['active_jobs'], 'paused' => $stats['paused_jobs'], 'disabled' => $stats['disabled_jobs']]) }}</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">{{ __('settings.cron.successful_runs') }}</div>
                <div class="stat-value">{{ number_format($stats['successful_executions']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-calendar"></i>
                    <span>{{ __('settings.cron.today', ['count' => $stats['executions_today']]) }}</span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-x-circle"></i></div>
                <div class="stat-label">{{ __('settings.cron.failed_runs') }}</div>
                <div class="stat-value">{{ number_format($stats['failed_executions']) }}</div>
                <div class="stat-trend {{ $stats['failures_today'] > 0 ? 'down' : 'up' }}">
                    <i class="feather icon-alert-triangle"></i>
                    <span>{{ __('settings.cron.today', ['count' => $stats['failures_today']]) }}</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-arrow-right-circle"></i></div>
                <div class="stat-label">{{ __('settings.cron.next_run') }}</div>
                <div class="stat-value" style="font-size:1.05rem">
                    {{ $stats['next_execution_at']?->format('H:i') ?? '—' }}
                </div>
                <div class="stat-trend up">
                    <i class="feather icon-clock"></i>
                    <span>{{ $stats['next_job']?->name ?? __('settings.cron.no_active_job') }}</span>
                </div>
            </div>
        </div>

        {{-- المهام المتعثّرة: فشل متكرّر يحتاج تدخّلاً لا مجرّد ظهور في الجدول --}}
        @if ($stats['stuck_jobs']->isNotEmpty())
            <div class="alert d-flex align-items-start"
                style="background:#fdecea;border:1px solid #f8c4be;color:#92211a;border-radius:10px">
                <i class="fas fa-exclamation-triangle mt-1 ml-2"></i>
                <div>
                    <strong>{{ __('settings.cron.stuck_title', ['count' => $stats['stuck_jobs']->count()]) }}</strong>
                    <span style="font-size:.9rem">
                        {{ __('settings.cron.stuck_body') }}
                        @foreach ($stats['stuck_jobs'] as $stuck)
                            <a href="{{ route('CronJobs.show', $stuck->id) }}" style="color:inherit;text-decoration:underline">
                                {{ $stuck->name }} ({{ $stuck->consecutive_failures }}×)
                            </a>@if (! $loop->last){{ __('settings.common.list_separator') }}@endif
                        @endforeach
                    </span>
                </div>
            </div>
        @endif

        <!-- المرشّحات -->
        <div class="card filter-card-modern mb-2">
            <div class="card-body">
                <form method="GET" action="{{ route('CronJobs.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-4 col-12 mb-1">
                            <label>{{ __('settings.common.search') }}</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                                placeholder="{{ __('settings.cron.search_placeholder') }}">
                        </div>

                        <div class="col-md-3 col-12 mb-1">
                            <label>{{ __('settings.common.type') }}</label>
                            <select name="type" class="form-control">
                                <option value="">{{ __('settings.cron.all_types') }}</option>
                                @foreach ($types as $key => $meta)
                                    <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>
                                        {{ $meta['label'] }}</option>
                                @endforeach
                            </select>
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
                            <label>&nbsp;</label>
                            <div class="custom-control custom-checkbox" style="padding-top:.5rem">
                                <input type="checkbox" class="custom-control-input" id="failing" name="failing" value="1"
                                    {{ request()->boolean('failing') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="failing">{{ __('settings.cron.failing_only') }}</label>
                            </div>
                        </div>

                        <div class="col-md-1 col-12 mb-1">
                            <label>&nbsp;</label>
                            <div class="d-flex">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i></button>
                                <a href="{{ route('CronJobs.index') }}" class="btn btn-secondary btn-block mt-0 ml-1">
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
                                <th style="width:60px" class="text-center">{{ __('settings.cron.toggle') }}</th>
                                <th>{{ __('settings.cron.job') }}</th>
                                <th>{{ __('settings.common.type') }}</th>
                                <th>{{ __('settings.cron.command') }}</th>
                                <th>{{ __('settings.cron.schedule') }}</th>
                                <th>{{ __('settings.cron.last_run') }}</th>
                                <th>{{ __('settings.cron.next_run') }}</th>
                                <th class="text-center">{{ __('settings.cron.success_fail') }}</th>
                                <th class="text-center" style="width:150px">{{ __('settings.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($jobs as $job)
                                <tr class="{{ $job->isActive() ? '' : 'cj-row-off' }}">
                                    <td class="text-center">
                                        @permission('settings.cron.update')
                                            <form method="POST" action="{{ route('CronJobs.toggle', $job->id) }}" id="tg-{{ $job->id }}">
                                                @csrf
                                                <label class="cj-switch justify-content-center"
                                                    title="{{ $job->isActive() ? __('settings.cron.disable_hint') : __('settings.cron.enable_hint') }}">
                                                    <input type="checkbox" {{ $job->isActive() ? 'checked' : '' }}
                                                        onchange="document.getElementById('tg-{{ $job->id }}').submit()">
                                                    <span class="track"></span>
                                                </label>
                                            </form>
                                        @else
                                            <span class="badge badge-light-{{ $job->statusColor() }}">{{ $job->statusLabel() }}</span>
                                        @endpermission
                                    </td>

                                    <td>
                                        <a href="{{ route('CronJobs.show', $job->id) }}"><strong>{{ $job->name }}</strong></a>
                                        @if ($job->is_system)
                                            <span class="badge badge-light-secondary" style="font-size:.65rem">{{ __('settings.cron.is_system') }}</span>
                                        @endif
                                        @if ($job->status === \App\Models\CronJob::STATUS_PAUSED)
                                            <span class="badge badge-light-warning" style="font-size:.65rem">{{ __('settings.cron.paused_badge') }}</span>
                                        @endif
                                        @if ($job->description)
                                            <div class="cj-sub">{{ Str::limit($job->description, 70) }}</div>
                                        @endif
                                    </td>

                                    <td>
                                        <i class="feather icon-{{ $job->typeIcon() }}"></i>
                                        {{ $job->typeLabel() }}
                                    </td>

                                    <td><span class="cj-mono">{{ Str::limit(class_basename($job->handler), 32) }}</span></td>

                                    <td>
                                        <span class="cj-mono">{{ $job->cron_expression }}</span>
                                        <div class="cj-sub">{{ Str::limit($descriptions[$job->id] ?? '', 42) }}</div>
                                    </td>

                                    <td>
                                        @if ($job->last_run_at)
                                            <span class="badge badge-light-{{ \App\Models\CronJobExecution::statuses()[$job->last_status]['color'] ?? 'secondary' }}">
                                                {{ \App\Models\CronJobExecution::statuses()[$job->last_status]['label'] ?? $job->last_status }}
                                            </span>
                                            <div class="cj-sub">{{ $job->last_run_at->format('Y-m-d H:i') }}</div>
                                        @else
                                            <span class="cj-sub">{{ __('settings.cron.never_ran') }}</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($job->isActive() && $job->next_run_at)
                                            {{ $job->next_run_at->format('Y-m-d H:i') }}
                                            <div class="cj-sub">{{ $job->next_run_at->diffForHumans() }}</div>
                                        @else
                                            <span class="cj-sub">—</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <span class="text-success">{{ number_format($job->success_count) }}</span>
                                        <span class="text-muted">/</span>
                                        <span class="{{ $job->failure_count > 0 ? 'text-danger' : 'text-muted' }}">
                                            {{ number_format($job->failure_count) }}
                                        </span>
                                        @if ($job->consecutive_failures > 0)
                                            <div class="cj-sub text-danger">{{ __('settings.cron.consecutive_failures', ['count' => $job->consecutive_failures]) }}</div>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-group">
                                            <div class="dropdown">
                                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                    type="button" data-toggle="dropdown" aria-expanded="false">
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="{{ route('CronJobs.show', $job->id) }}">
                                                        <i class="feather icon-eye"></i> {{ __('settings.cron.log_and_details') }}
                                                    </a>

                                                    @permission('settings.cron.run')
                                                        <form method="POST" action="{{ route('CronJobs.run', $job->id) }}"
                                                            onsubmit="return confirm('{{ __('settings.cron.confirm_run', ['name' => $job->name]) }}')">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="feather icon-play"></i> {{ __('settings.cron.run_now') }}
                                                            </button>
                                                        </form>
                                                    @endpermission

                                                    @permission('settings.cron.update')
                                                        <a class="dropdown-item" href="{{ route('CronJobs.edit', $job->id) }}">
                                                            <i class="feather icon-edit-2"></i> {{ __('settings.common.edit') }}
                                                        </a>

                                                        <div class="dropdown-divider"></div>

                                                        @if ($job->status === \App\Models\CronJob::STATUS_PAUSED)
                                                            <form method="POST" action="{{ route('CronJobs.resume', $job->id) }}">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="feather icon-play-circle"></i> {{ __('settings.cron.resume') }}
                                                                </button>
                                                            </form>
                                                        @elseif ($job->isActive())
                                                            <form method="POST" action="{{ route('CronJobs.pause', $job->id) }}">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="feather icon-pause"></i> {{ __('settings.cron.pause') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endpermission

                                                    @permission('settings.cron.delete')
                                                        @if ($job->isDeletable())
                                                            <form method="POST" action="{{ route('CronJobs.destroy', $job->id) }}"
                                                                onsubmit="return confirm('{{ __('settings.cron.confirm_delete', ['name' => $job->name]) }}')">
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
                                    <td colspan="9" class="text-center py-4">
                                        <i class="feather icon-clock" style="font-size:2rem;color:#c8ccd4"></i>
                                        <div class="mt-1">{{ __('settings.cron.no_match') }}</div>
                                        <div class="cj-sub">
                                            {{ __('settings.cron.no_match_hint') }}
                                            <span class="cj-mono">php artisan db:seed --class=CronJobsSeeder</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
