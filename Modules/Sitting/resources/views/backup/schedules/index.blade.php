@extends('sitting::master')

@section('title', __('settings.backup.schedules.title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-database"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.backup.schedules.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('Backup.index') }}">{{ __('settings.backup.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.backup.schedules.crumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            @permission('settings.backup.settings')
                <a href="{{ route('Backup.schedules.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('settings.backup.schedules.add') }}
                </a>
            @endpermission
            <a href="{{ route('Backup.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.common.back') }}
            </a>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- مفتاح واحد يحكم كل الجداول: بدون هذا التنبيه يضيف المسؤول
             جدولاً ويراه «نشطاً» ولا يُنفَّذ شيء، بلا سببٍ ظاهر --}}
        @if (! $settings->is_enabled)
            <div class="alert alert-warning d-flex align-items-center" style="border-radius:10px;gap:.6rem">
                <i class="feather icon-alert-triangle"></i>
                <div class="flex-grow-1">
                    <strong>{{ __('settings.backup.schedules.master_off') }}</strong>
                    <div class="cj-sub">{{ __('settings.backup.schedules.master_off_hint') }}</div>
                </div>
                @permission('settings.backup.settings')
                    <a href="{{ route('Backup.settings') }}" class="btn btn-sm btn-warning">
                        {{ __('settings.backup.schedules.master_off_action') }}
                    </a>
                @endpermission
            </div>
        @endif

        <div class="alert mb-2"
            style="background:#eef6ff;border:1px solid #b9d9ff;color:#12508f;border-radius:10px;font-size:.86rem">
            <i class="feather icon-info"></i>
            {{ __('settings.backup.schedules.intro') }}
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('settings.cron.name') }}</th>
                                <th>{{ __('settings.backup.schedules.what') }}</th>
                                <th>{{ __('settings.backup.schedules.when') }}</th>
                                <th>{{ __('settings.common.status') }}</th>
                                <th>{{ __('settings.backup.schedules.last_result') }}</th>
                                <th class="text-center">{{ __('settings.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($jobs as $job)
                                @php $isPrimary = $job->key === $primaryKey; @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $job->name }}</strong>
                                        @if ($isPrimary)
                                            <span class="badge badge-light-primary" style="font-size:.62rem">
                                                {{ __('settings.backup.schedules.primary') }}
                                            </span>
                                        @endif
                                        @if ($job->description)
                                            <div class="cj-sub">{{ Str::limit($job->description, 80) }}</div>
                                        @endif
                                    </td>

                                    <td>{{ $scopes[$job->id] }}</td>

                                    <td>
                                        {{ $descriptions[$job->id] }}
                                        <div class="cj-sub cj-mono" dir="ltr" style="text-align:left">{{ $job->cron_expression }}</div>
                                        @if ($job->isActive() && $job->next_run_at)
                                            <div class="cj-sub">
                                                {{ __('settings.backup.schedules.next', ['time' => $job->next_run_at->format('Y-m-d H:i')]) }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="badge badge-light-{{ $job->statusColor() }}">{{ $job->statusLabel() }}</span>
                                    </td>

                                    <td>
                                        @if (isset($lastBackups[$job->id]))
                                            @php $last = $lastBackups[$job->id]; @endphp
                                            <a href="{{ route('Backup.show', $last->id) }}">
                                                {{ $last->completed_at?->format('Y-m-d H:i') }}
                                            </a>
                                            <div class="cj-sub">
                                                {{ $last->humanSize() }}
                                                @if ($last->is_encrypted)
                                                    <i class="feather icon-lock text-success" title="{{ __('settings.backup.encrypted') }}"></i>
                                                @endif
                                            </div>
                                        @elseif ($job->last_error)
                                            <span class="text-danger">{{ Str::limit($job->last_error, 50) }}</span>
                                        @else
                                            <span class="cj-sub">{{ __('settings.backup.schedules.never_ran') }}</span>
                                        @endif
                                    </td>

                                    <td class="text-center" style="white-space:nowrap">
                                        <a href="{{ route('CronJobs.show', $job->id) }}"
                                            class="btn btn-sm btn-outline-secondary" title="{{ __('settings.backup.job_log_link') }}">
                                            <i class="feather icon-list"></i>
                                        </a>

                                        @permission('settings.backup.create')
                                            <form method="POST" action="{{ route('Backup.schedules.run', $job->id) }}"
                                                class="d-inline"
                                                onsubmit="return confirm('{{ __('settings.backup.schedules.confirm_run') }}')">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-primary" title="{{ __('settings.cron.run_now') }}">
                                                    <i class="feather icon-play"></i>
                                                </button>
                                            </form>
                                        @endpermission

                                        @permission('settings.backup.settings')
                                            <form method="POST" action="{{ route('Backup.schedules.toggle', $job->id) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-{{ $job->isActive() ? 'warning' : 'success' }}"
                                                    title="{{ $job->isActive() ? __('settings.common.disable') : __('settings.common.enable') }}">
                                                    <i class="feather icon-{{ $job->isActive() ? 'pause' : 'play-circle' }}"></i>
                                                </button>
                                            </form>

                                            {{-- الأساسي يُعدَّل من شاشة الإعدادات: جدولته مشتقّة منها
                                                 ويعاد بناؤها عند كل حفظ، فتعديله هنا يُمحى --}}
                                            @if ($isPrimary)
                                                <a href="{{ route('Backup.settings') }}"
                                                    class="btn btn-sm btn-outline-secondary" title="{{ __('settings.backup.schedules.edit_primary') }}">
                                                    <i class="feather icon-settings"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('Backup.schedules.edit', $job->id) }}"
                                                    class="btn btn-sm btn-outline-secondary" title="{{ __('settings.common.edit') }}">
                                                    <i class="feather icon-edit-2"></i>
                                                </a>

                                                <form method="POST" action="{{ route('Backup.schedules.destroy', $job->id) }}"
                                                    class="d-inline"
                                                    onsubmit="return confirm('{{ __('settings.backup.schedules.confirm_delete') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('settings.common.delete') }}">
                                                        <i class="feather icon-trash-2"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        @endpermission
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center p-3">
                                        <div class="cj-sub">{{ __('settings.backup.schedules.empty') }}</div>
                                        <div class="cj-sub mt-1">{!! __('settings.backup.job_missing') !!}</div>
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
