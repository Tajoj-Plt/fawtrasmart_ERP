@extends('sitting::master')

@section('title', __('settings.logs.activity_show_title', ['id' => $log->id]))

@section('content')
<link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
<div class="sl-page-head">
    <div class="sl-page-head__titles">
        <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-activity"></i></span>
        <div style="min-width:0">
            <h2 class="sl-page-head__title">{{ __('settings.logs.activity_details') }}</h2>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ActivityLog.index') }}">{{ __('settings.logs.activity_heading') }}</a></li>
                    <li class="breadcrumb-item active">#{{ $log->id }}</li>

                </ol>
            </nav>
        </div>
    </div>
    <div class="sl-page-head__actions">
        <div class="btn-group">
            <a href="{{ route('ActivityLog.index') }}" class="btn btn-outline-secondary">
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
                            <i class="{{ $log->icon ?: 'feather icon-activity' }}"></i>
                            {{ $log->type ?: __('settings.logs.general') }}
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
                            <strong>{{ $log->user ? $log->user->name : __('settings.logs.system_or_visitor') }}</strong>
                            <br>
                            <span class="text-muted small">{{ $log->user ? $log->user->email : '' }}</span>
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.branch_label') }}</strong>
                        <p class="mb-0">
                            <span class="badge badge-light-secondary">{{ ($log->user && $log->user->branch) ? $log->user->branch->name : __('settings.logs.main_branch') }}</span>
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.reference_label') }}</strong>
                        <p class="mb-0">
                            @if($log->type_id)
                                <code>#{{ $log->type_id }}</code>
                            @else
                                <span class="text-muted">{{ __('settings.logs.no_reference') }}</span>
                            @endif
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>{{ __('settings.logs.datetime_label') }}</strong>
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
                    <form action="{{ route('ActivityLog.destroy', $log->id) }}" method="POST"
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

        {{-- محتوى النشاط --}}
        <div class="col-md-8">
            <div class="card border-primary">
                <div class="card-header bg-light">
                    <h5 class="mt-2 mb-0">
                        <strong>{{ __('settings.logs.activity_description') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-4">
                        <p class="mb-0" style="white-space: pre-wrap; font-size: 16px; line-height: 1.6;">{!! strip_tags(preg_replace('/(\*\*.*?\*\*)/', '<strong>$1</strong>', $log->description), '<strong><br>') !!}</p>
                    </div>
                </div>
            </div>

            @if($log->old_value)
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mt-2 mb-0">
                        <strong>{{ __('settings.logs.old_data') }}</strong>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-3" style="max-height: 400px; overflow-y: auto;">
                        <pre class="mb-0 text-muted" style="white-space: pre-wrap; font-size: 12px; font-family: monospace;">{{ is_string($log->old_value) ? $log->old_value : json_encode($log->old_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
