@extends('sitting::master')

@section('title', __('settings.logs.error_show_title', ['id' => $log->id]))

@section('content')
<link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
<div class="sl-page-head">
    <div class="sl-page-head__titles">
        <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-alert-triangle"></i></span>
        <div style="min-width:0">
            <h2 class="sl-page-head__title">{{ __('settings.logs.error_details') }}</h2>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ErrorLogs.index') }}">{{ __('settings.logs.errors_title') }}</a></li>
                    <li class="breadcrumb-item active">#{{ $log->id }}</li>

                </ol>
            </nav>
        </div>
    </div>
    <div class="sl-page-head__actions">
        <div class="btn-group">
            <a href="{{ route('ErrorLogs.index') }}" class="btn btn-outline-secondary">
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
                        <span class="badge badge-light-danger badge-lg">
                            {{ __('settings.logs.system_error') }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>{{ __('settings.logs.record_number') }}</strong>
                        <p class="mb-0">#{{ $log->id }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.error_type_label') }}</strong>
                        <p class="mb-0 text-danger">
                            {{ $log->error_type }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.user_label') }}</strong>
                        <p class="mb-0">
                            <strong>{{ $log->user ? $log->user->name : __('settings.logs.system_or_visitor') }}</strong>
                            <br>
                            <span class="text-muted small">{{ $log->user ? $log->user->email : '' }}</span>
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.url_label') }}</strong>
                        <p class="mb-0">
                            <a href="{{ $log->url }}" target="_blank">{{ Str::limit($log->url, 50) }}</a>
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
                    <form action="{{ route('ErrorLogs.destroy', $log->id) }}" method="POST" 
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

        {{-- محتوى الخطأ --}}
        <div class="col-md-8">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mt-2 mb-0 text-white">
                        <strong>{{ __('settings.logs.error_message_label') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-3">
                        <pre class="mb-0 text-danger" style="white-space: pre-wrap; font-family: inherit; font-size: 14px;">{{ $log->error_message ?: __('settings.logs.no_message') }}</pre>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mt-2 mb-0">
                        <strong>{{ __('settings.logs.stack_trace') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-3" style="max-height: 500px; overflow-y: auto;">
                        <code class="mb-0" style="white-space: pre-wrap; font-size: 12px; color: #d63384;">{{ $log->stack_trace ?: __('settings.logs.no_trace') }}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
