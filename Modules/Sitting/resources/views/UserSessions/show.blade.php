@extends('sitting::master')

@section('title', __('settings.sessions.show_title', ['id' => Str::limit($session->id, 8, '')]))

@section('content')
<link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
<div class="sl-page-head">
    <div class="sl-page-head__titles">
        <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-monitor"></i></span>
        <div style="min-width:0">
            <h2 class="sl-page-head__title">{{ __('settings.sessions.details') }}</h2>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('UserSessions.index') }}">{{ __('settings.sessions.heading') }}</a></li>
                    <li class="breadcrumb-item active">#{{ Str::limit($session->id, 8, '') }}</li>

                </ol>
            </nav>
        </div>
    </div>
    <div class="sl-page-head__actions">
        <div class="btn-group">
            <a href="{{ route('UserSessions.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.sessions.back') }}
            </a>
        </div>

    </div>
</div>

<div class="content-body email-logs-page">
    <div class="row">
        {{-- معلومات السجل --}}
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('settings.sessions.info') }}</h4>
                    <div class="heading-elements">
                        @php
                            $isOnline = \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffInMinutes() < 15;
                        @endphp
                        @if($isOnline)
                            <span class="badge badge-success badge-lg">{{ __('settings.sessions.active_badge') }}</span>
                        @else
                            <span class="badge badge-secondary badge-lg">{{ __('settings.sessions.inactive_badge') }}</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>{{ __('settings.sessions.session_id') }}</strong>
                        <p class="mb-0" style="word-break: break-all;"><code>{{ $session->id }}</code></p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.sessions.user_label') }}</strong>
                        <p class="mb-0">
                            @php
                                $user = $session->user_id ? \App\Models\User::find($session->user_id) : null;
                            @endphp
                            <strong>{{ $user ? $user->name : __('settings.sessions.anonymous_guest') }}</strong>
                            <br>
                            <span class="text-muted small">{{ $user ? $user->email : '' }}</span>
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.sessions.ip_label') }}</strong>
                        <p class="mb-0">
                            {{ $session->ip_address }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.sessions.last_activity_label') }}</strong>
                        <p class="mb-0">
                            {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->format('Y-m-d H:i:s') }}
                            <br>
                            <small class="text-muted">({{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }})</small>
                        </p>
                    </div>
                </div>
            </div>

            {{-- الإجراءات --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('settings.sessions.actions') }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('UserSessions.destroy', $session->id) }}" method="POST" 
                          onsubmit="return confirm('{{ __('settings.sessions.confirm_terminate_one') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-block">
                            <i class="feather icon-log-out"></i> {{ __('settings.sessions.terminate_now') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- محتوى الجلسة --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mt-2 mb-0">
                        <strong>{{ __('settings.sessions.user_agent') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-4" style="background-color: #f8f9fa;">
                        <code class="mb-0" style="white-space: pre-wrap; color: #d63384; font-size: 14px;">{{ $session->user_agent ?: __('settings.sessions.unknown') }}</code>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mt-2 mb-0">
                        <strong>{{ __('settings.sessions.payload') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-3" style="max-height: 400px; overflow-y: auto;">
                        <pre class="mb-0 text-muted" style="white-space: pre-wrap; font-size: 11px; word-break: break-all;">{{ $session->payload ?: __('settings.sessions.no_payload') }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
