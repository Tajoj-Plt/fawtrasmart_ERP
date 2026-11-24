@extends('sitting::master')

@section('title', __('settings.sessions.title'))

@php
    $breadcrumb = [
        ['title' => __('settings.sessions.title'), 'url' => '#']
    ];
@endphp

@section('content')
    <link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-monitor"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.sessions.heading') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.sessions.heading') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <div class="btn-group">
                <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#cleanupModal">
                    <i class="feather icon-trash"></i> {{ __('settings.sessions.cleanup') }}
                </button>
            </div>

        </div>
    </div>

    <div class="content-body email-logs-page">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- الصفحة تقرأ جدول `sessions`، ولا يكتب فيه شيء إلا إذا كان مخزّن
             الجلسات هو قاعدة البيانات. بلا هذا التنبيه تبدو الشاشة معطوبة
             بينما هي تعمل على جدول لن يمتلئ أبداً. --}}
        @unless ($driverSupported ?? true)
            <div class="alert d-flex align-items-start"
                style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:10px">
                <i class="fas fa-info-circle mt-1 ml-2"></i>
                <div>
                    <strong>{{ __('settings.sessions.driver_warning') }}
                        <code>{{ $sessionDriver ?? 'file' }}</code>.</strong>
                    <div style="font-size:.88rem;margin-top:4px;line-height:1.9">
                        {!! __('settings.sessions.driver_body') !!}
                        <code>php artisan config:clear</code>.
                        <br>
                        <span style="color:#a1743a">
                            {{ __('settings.sessions.driver_note') }}
                        </span>
                    </div>
                </div>
            </div>
        @endunless

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon">
                    <i class="feather icon-monitor"></i>
                </div>
                <div class="stat-label">{{ __('settings.sessions.total') }}</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-list"></i>
                    <span>{{ __('settings.sessions.all_records') }}</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-wifi"></i>
                </div>
                <div class="stat-label">{{ __('settings.sessions.online_now') }}</div>
                <div class="stat-value">{{ number_format($stats['active_now']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>{{ __('settings.sessions.active_now') }}</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-users"></i>
                </div>
                <div class="stat-label">{{ __('settings.sessions.members_online') }}</div>
                <div class="stat-value">{{ number_format($stats['users']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-user-check"></i>
                    <span>{{ __('settings.sessions.registered_user') }}</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-user-x"></i>
                </div>
                <div class="stat-label">{{ __('settings.sessions.guests') }}</div>
                <div class="stat-value">{{ number_format($stats['guests']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-eye"></i>
                    <span>{{ __('settings.sessions.anonymous') }}</span>
                </div>
            </div>
        </div>

        <!-- قسم الفلاتر -->
        <div class="card filter-card no-print">
            <div class="card-header">
                <h4 class="card-title"><i class="fa fa-filter"></i> {{ __('settings.sessions.filters') }}</h4>
                <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('UserSessions.index') }}" id="filterForm">
                        <div class="row">
                            <!-- البحث -->
                            <div class="col-md-5 col-sm-12 mb-1">
                                <label>{{ __('settings.sessions.search') }}</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="{{ __('settings.sessions.search_placeholder') }}" 
                                       value="{{ request('search') }}">
                            </div>

                            <!-- من تاريخ -->
                            <div class="col-md-2 col-sm-6 mb-1">
                                <label>{{ __('settings.sessions.date_from') }}</label>
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>

                            <!-- إلى تاريخ -->
                            <div class="col-md-2 col-sm-6 mb-1">
                                <label>{{ __('settings.sessions.date_to') }}</label>
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>

                            <!-- أزرار البحث -->
                            <div class="col-md-3 col-sm-12 mb-1">
                                <label class="d-block">&nbsp;</label>
                                <div class="d-flex" style="gap: 10px;">
                                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                                        <i class="fa fa-search"></i> {{ __('settings.sessions.filter') }}
                                    </button>
                                    <a href="{{ route('UserSessions.index') }}" class="btn btn-secondary" style="flex: 1;">
                                        <i class="fa fa-times"></i> {{ __('settings.common.cancel') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> {{ __('settings.sessions.list') }}</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 60px;">#</th>
                                <th>{{ __('settings.sessions.user') }}</th>
                                <th>{{ __('settings.common.email') }}</th>
                                <th>{{ __('settings.sessions.ip') }}</th>
                                <th>{{ __('settings.sessions.device') }}</th>
                                <th class="text-center">{{ __('settings.sessions.last_activity') }}</th>
                                <th class="text-center" style="width: 80px;">{{ __('settings.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sessions as $index => $session)
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td><strong>{{ $session->user_name ?? __('settings.sessions.guest') }}</strong></td>
                                    <td>{{ $session->user_email ?? '-' }}</td>
                                    <td>{{ $session->ip_address }}</td>
                                    <td>{{ Str::limit($session->user_agent, 40) }}</td>
                                    <td class="text-center">
                                        @php
                                            $diff = \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans();
                                            $isOnline = \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffInMinutes() < 15;
                                        @endphp
                                        @if($isOnline)
                                            <span class="badge badge-success">{{ __('settings.sessions.online_now') }}</span>
                                        @else
                                            <span class="text-muted">{{ $diff }}</span>
                                        @endif
                                    </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <div class="dropdown">
                                                    <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                        type="button" data-toggle="dropdown" aria-expanded="false">
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item" href="{{ route('UserSessions.show', $session->id) }}">
                                                            <i class="feather icon-eye"></i> {{ __('settings.sessions.view') }}
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <form action="{{ route('UserSessions.destroy', $session->id) }}" method="POST" class="delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="feather icon-log-out"></i> {{ __('settings.sessions.terminate') }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="feather icon-monitor" style="font-size: 48px; color: #ccc;"></i>
                                        <p class="mt-2 text-muted">{{ __('settings.sessions.no_sessions') }}</p>
                                    </td>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Pagination --}}
                @include('sitting::components.table-foot', ['paginator' => $sessions])
            </div>
        </div>
    </div>

    <!-- Cleanup Modal -->
    <div class="modal fade text-left" id="cleanupModal" tabindex="-1" role="dialog" aria-labelledby="cleanupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger white">
                    <h5 class="modal-title" id="cleanupModalLabel">{{ __('settings.sessions.cleanup_title') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('UserSessions.cleanup') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        {{ __('settings.sessions.cleanup_confirm') }}
                        <br><br>
                        <strong>{{ __('settings.sessions.cleanup_note_label') }}</strong> {{ __('settings.sessions.cleanup_note') }}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('settings.common.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('settings.sessions.cleanup_submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('.delete-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        
        Swal.fire({
            title: @json(__('settings.sessions.sure')),
            text: @json(__('settings.sessions.terminate_warning')),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: @json(__('settings.sessions.confirm_terminate')),
            cancelButtonText: @json(__('settings.common.cancel')),
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.off('submit').submit();
            }
        });
    });
});
</script>
@endsection
