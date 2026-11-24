@extends('sitting::master')

@section('title', __('settings.backup.restores_title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-database"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.backup.restores_title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('Backup.index') }}">{{ __('settings.backup.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.backup.restores_crumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('Backup.history') }}" class="btn btn-outline-secondary">
                <i class="feather icon-database"></i> {{ __('settings.backup.backup_log') }}
            </a>
            <a href="{{ route('Backup.index') }}" class="btn btn-primary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.common.back') }}
            </a>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="cj-health unknown">
            <span class="dot"></span>
            <div>
                <strong>{{ __('settings.backup.restores_note_title') }}</strong>
                <div style="font-size:.85rem;margin-top:.25rem">
                    {{ __('settings.backup.restores_note_body') }}
                </div>
            </div>
        </div>

        <div class="card filter-card-modern mb-2">
            <div class="card-body">
                <form method="GET" action="{{ route('Backup.restores') }}">
                    <div class="row align-items-end">
                        <div class="col-md-3 col-12 mb-1">
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
                            <div class="d-flex">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i></button>
                                <a href="{{ route('Backup.restores') }}" class="btn btn-secondary btn-block mt-0 ml-1">
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
                                <th>{{ __('settings.common.date') }}</th>
                                <th>{{ __('settings.backup.restored_backup') }}</th>
                                <th>{{ __('settings.backup.safety_backup') }}</th>
                                <th>{{ __('settings.backup.executor') }}</th>
                                <th>{{ __('settings.backup.ip') }}</th>
                                <th>{{ __('settings.backup.duration') }}</th>
                                <th class="text-center">{{ __('settings.common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($restores as $restore)
                                <tr>
                                    <td>
                                        {{ $restore->started_at?->format('Y-m-d H:i:s') }}
                                        <div class="cj-sub">{{ $restore->started_at?->diffForHumans() }}</div>
                                    </td>

                                    <td>
                                        @if ($restore->backup)
                                            <a href="{{ route('Backup.show', $restore->backup->id) }}">
                                                {{ $restore->backup->displayName() }}
                                            </a>
                                            <div class="cj-sub">
                                                {{ __('settings.backup.data_of', ['date' => $restore->backup->created_at?->format('Y-m-d H:i')]) }}
                                            </div>
                                        @else
                                            <span class="cj-sub">{{ __('settings.backup.backup_row_deleted') }}</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($restore->safetyBackup)
                                            <a href="{{ route('Backup.show', $restore->safetyBackup->id) }}">
                                                #{{ $restore->safetyBackup->id }}
                                            </a>
                                            <div class="cj-sub">{{ $restore->safetyBackup->humanSize() }}</div>
                                        @else
                                            {{-- لا نسخة أمان يعني أن العملية انهارت قبل أخذها --}}
                                            <span class="cj-sub text-danger">{{ __('settings.backup.not_taken') }}</span>
                                        @endif
                                    </td>

                                    <td>{{ $restore->executor?->name ?? '—' }}</td>
                                    <td><span class="cj-mono">{{ $restore->ip_address ?? '—' }}</span></td>

                                    <td>
                                        @if ($restore->duration_ms !== null)
                                            {{ __('settings.backup.seconds', ['count' => number_format($restore->duration_ms / 1000, 1)]) }}
                                        @else
                                            —
                                        @endif
                                        @if ($restore->statements_executed)
                                            <div class="cj-sub">{{ __('settings.backup.statements', ['count' => number_format($restore->statements_executed)]) }}</div>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <span class="badge badge-light-{{ $restore->statusColor() }}">
                                            {{ $restore->statusLabel() }}
                                        </span>
                                        @if ($restore->error_message)
                                            <div class="cj-sub text-danger">{{ Str::limit($restore->error_message, 50) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="feather icon-rotate-ccw" style="font-size:2rem;color:#c8ccd4"></i>
                                        <div class="mt-1">{{ __('settings.backup.no_restores') }}</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                    @include('sitting::components.table-foot', ['paginator' => $restores])
            </div>
        </div>
    </div>
@endsection
