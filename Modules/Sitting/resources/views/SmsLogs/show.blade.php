@extends('sitting::master')

@section('title', __('settings.logs.sms_show_title', ['id' => $log->id]))

@section('content')
<link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
<div class="sl-page-head">
    <div class="sl-page-head__titles">
        <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-message-square"></i></span>
        <div style="min-width:0">
            <h2 class="sl-page-head__title">{{ __('settings.logs.sms_details') }}</h2>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('SmsLogs.index') }}">{{ __('settings.logs.sms_title') }}</a></li>
                    <li class="breadcrumb-item active">#{{ $log->id }}</li>

                </ol>
            </nav>
        </div>
    </div>
    <div class="sl-page-head__actions">
        <div class="btn-group">
            <a href="{{ route('SmsLogs.index') }}" class="btn btn-outline-secondary">
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
                        @if($log->status == 'sent' || $log->status == 'delivered' || strtolower($log->status) == 'success')
                            <span class="badge badge-light-success badge-lg">{{ __('settings.logs.success_badge') }}</span>
                        @else
                            <span class="badge badge-light-danger badge-lg">{{ $log->status }}</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>{{ __('settings.logs.record_number') }}</strong>
                        <p class="mb-0">#{{ $log->id }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.recipient_label') }}</strong>
                        <p class="mb-0" dir="ltr" style="text-align: right;">
                            <strong>{{ $log->recipient }}</strong>
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.sender_label') }}</strong>
                        <p class="mb-0">
                            <strong>{{ $log->user ? $log->user->name : __('settings.logs.the_system') }}</strong>
                            <br>
                            <span class="text-muted small">{{ $log->user ? $log->user->email : '' }}</span>
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.sent_at_label') }}</strong>
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
                    <form action="{{ route('SmsLogs.destroy', $log->id) }}" method="POST" 
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

        {{-- محتوى الرسالة --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mt-2 mb-0">
                        <strong>{{ __('settings.logs.sms_body') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-4" style="background-color: #f8f9fa;">
                        <div class="sms-bubble" style="background-color: #e0f7fa; padding: 15px; border-radius: 15px; display: inline-block; max-width: 80%; border-bottom-right-radius: 0;">
                            <p class="mb-0" style="white-space: pre-wrap; font-size: 16px;">{{ $log->message ?: __('settings.logs.no_content') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mt-2 mb-0">
                        <strong>{{ __('settings.logs.gateway_response') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-3">
                        <code class="mb-0" style="white-space: pre-wrap; color: #6c757d;">{{ $log->gateway_response ?: __('settings.logs.no_response') }}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
