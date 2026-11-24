@extends('sitting::master')

@section('title', __('settings.logs.event_show_title', ['id' => $log->id]))

@section('content')
<link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
<div class="sl-page-head">
    <div class="sl-page-head__titles">
        <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-zap"></i></span>
        <div style="min-width:0">
            <h2 class="sl-page-head__title">{{ __('settings.logs.event_details') }}</h2>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('EventLogs.index') }}">{{ __('settings.logs.events_title') }}</a></li>
                    <li class="breadcrumb-item active">#{{ $log->id }}</li>

                </ol>
            </nav>
        </div>
    </div>
    <div class="sl-page-head__actions">
        <div class="btn-group">
            <a href="{{ route('EventLogs.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.logs.back') }}
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
                    <h4 class="card-title">{{ __('settings.logs.record_info') }}</h4>
                    <div class="heading-elements">
                        <span class="badge badge-light-primary badge-lg">
                            {{ $log->action }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>{{ __('settings.logs.record_number') }}</strong>
                        <p class="mb-0">#{{ $log->id }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.user_label') }}</strong>
                        <p class="mb-0">
                            <strong>{{ $log->user ? $log->user->name : __('settings.logs.system') }}</strong>
                            <br>
                            <span class="text-muted small">{{ $log->user ? $log->user->email : '' }}</span>
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.ip_label') }}</strong>
                        <p class="mb-0">
                            {{ $log->ip_address }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.created_at_label') }}</strong>
                        <p class="mb-0">{{ $log->created_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>

            {{-- الإجراءات --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('settings.logs.actions') }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('EventLogs.destroy', $log->id) }}" method="POST" 
                          onsubmit="return confirm('{{ __('settings.logs.confirm_delete_one') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-block">
                            <i class="feather icon-trash-2"></i> {{ __('settings.logs.delete_record') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- محتوى الحدث --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mt-2 mb-0">
                        <strong>{{ __('settings.logs.description_label') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-3">
                        <pre class="mb-0" style="white-space: pre-wrap; font-family: inherit; font-size: 14px;">{{ $log->description ?: __('settings.logs.no_details') }}</pre>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mt-2 mb-0">
                        <strong>{{ __('settings.logs.user_agent') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-3">
                        <code class="mb-0" style="white-space: pre-wrap; color: #d63384;">{{ $log->user_agent ?: __('settings.logs.unknown') }}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
