@extends('sales::master')

{{-- سجلّ ما وُقِّع وأُرسل إلى هيئة الزكاة والضريبة والجمارك.

     كلّ صفٍّ إرسالٌ لا مستند: المستند المرفوض ثم المصحَّح يظهر مرّتين، لأنّ
     كلّ إرسالٍ أخذ عدّاده في سلسلة الهيئة. --}}

@section('title', __('zatca.documents.title'))

@php
    $statuses = ['pending', 'reported', 'cleared', 'rejected', 'failed'];
    $tones = ['success' => 'on', 'warning' => 'warn', 'info' => 'info', 'danger' => 'dang'];
    $cards = [
        'cleared' => ['success', 'check-circle'],
        'reported' => ['success', 'send'],
        'pending' => ['warning', 'clock'],
        'rejected' => ['danger', 'x-circle'],
    ];
@endphp

@section('content')
    <link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
    @include('sitting::components._settings_styles')

    <style>
        .zd-messages { margin: .3rem 0 0; padding: 0 1rem 0 0; font-size: .78rem; line-height: 1.7; }
        .zd-messages.err { color: #a3342a; }
        .zd-messages.warn { color: #96700f; }
        .zd-meta { font-size: .74rem; color: #8a93a0; direction: ltr; text-align: right; }
    </style>

    <div class="content-header row">
        <div class="content-header-left col-md-8 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">{{ __('zatca.documents.title') }}</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('settings.electronic_invoice') }}">{{ __('sales_settings.electronic.title') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('zatca.documents.title') }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right col-md-4 col-12 mb-2 text-right">
            <a href="{{ route('settings.electronic_invoice') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.common.back') }}
            </a>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stats-row">
            @foreach ($cards as $status => [$tone, $icon])
                <div class="stat-card-modern {{ $tone }}">
                    <div class="stat-icon"><i class="feather icon-{{ $icon }}"></i></div>
                    <div class="stat-label">{{ __('zatca.status.'.$status) }}</div>
                    <div class="stat-value">{{ number_format((int) ($counts[$status] ?? 0)) }}</div>
                </div>
            @endforeach
        </div>

        <div class="st-card">
            <div class="body">
                <form method="GET" action="{{ route('electronic_invoice.documents') }}" class="row align-items-end">
                    <div class="col-md-4 col-12 form-group mb-md-0">
                        <label>{{ __('zatca.documents.search') }}</label>
                        <input type="text" name="q" class="form-control" value="{{ request('q') }}">
                    </div>
                    <div class="col-md-3 col-6 form-group mb-md-0">
                        <label>{{ __('zatca.documents.status') }}</label>
                        <select name="status" class="form-control">
                            <option value="">{{ __('zatca.documents.all_statuses') }}</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>
                                    {{ __('zatca.status.'.$status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-6 form-group mb-md-0">
                        <label>{{ __('zatca.documents.environment') }}</label>
                        <select name="environment" class="form-control">
                            <option value="">—</option>
                            @foreach (\App\Models\ElectronicInvoiceSetting::ENVIRONMENTS as $env)
                                <option value="{{ $env }}" @selected(request('environment') === $env)>
                                    {{ __('sales_settings.electronic.env.'.$env) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-12">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="feather icon-filter"></i> {{ __('zatca.documents.filter') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="st-card">
            <div class="head">
                <i class="feather icon-list" style="color:#2b6cb0"></i>
                <h4>{{ __('zatca.documents.title') }}</h4>
                <span class="count">{{ number_format($documents->total()) }}</span>
            </div>
            <div class="body flush">
                @if ($documents->isEmpty())
                    <div class="text-center text-muted p-3">
                        <i class="feather icon-inbox" style="font-size:2rem"></i>
                        <p class="mt-1 mb-0">{{ __('zatca.documents.empty') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th>{{ __('zatca.documents.icv') }}</th>
                                    <th>{{ __('zatca.documents.number') }}</th>
                                    <th>{{ __('zatca.documents.type') }}</th>
                                    <th>{{ __('zatca.documents.status') }}</th>
                                    <th>{{ __('zatca.documents.sent_at') }}</th>
                                    <th>{{ __('zatca.documents.attempts') }}</th>
                                    <th>{{ __('zatca.documents.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($documents as $document)
                                    @php
                                        $showRoute = $document->document_type === 'invoice' && Route::has('invoices.show')
                                            ? route('invoices.show', $document->document_id)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td class="num">{{ $document->icv }}</td>
                                        <td style="white-space:nowrap">
                                            @if ($showRoute)
                                                <a href="{{ $showRoute }}">{{ $document->document_number }}</a>
                                            @else
                                                {{ $document->document_number }}
                                            @endif
                                            <div class="zd-meta">{{ $document->uuid }}</div>
                                        </td>
                                        <td style="white-space:nowrap">
                                            {{ __('zatca.profile.'.$document->profile) }}
                                            <div class="text-muted" style="font-size:.78rem">
                                                {{ __('zatca.kind.'.$document->kind) }}
                                                · {{ __('sales_settings.electronic.env.'.$document->environment) }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="st-badge {{ $tones[$document->tone()] ?? 'off' }}">
                                                {{ __('zatca.status.'.$document->status) }}
                                            </span>
                                            @if ($document->isAccepted() && ! empty($document->warnings))
                                                <span class="st-badge warn">{{ __('zatca.status.with_warnings') }}</span>
                                            @endif

                                            @if (! empty($document->errors))
                                                <ul class="zd-messages err">
                                                    @foreach ($document->errors as $message)
                                                        <li>{{ $message }}</li>
                                                    @endforeach
                                                </ul>
                                            @elseif (filled($document->last_error))
                                                <ul class="zd-messages err"><li>{{ $document->last_error }}</li></ul>
                                            @endif

                                            @if (! empty($document->warnings))
                                                <ul class="zd-messages warn">
                                                    @foreach ($document->warnings as $message)
                                                        <li>{{ $message }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                        <td class="num" style="white-space:nowrap">
                                            {{ optional($document->submitted_at ?? $document->last_attempt_at)->format('Y-m-d H:i') ?? '—' }}
                                        </td>
                                        <td class="num">{{ $document->attempts }}</td>
                                        <td style="white-space:nowrap">
                                            @if ($document->isAccepted())
                                                @permission('sales.invoices.export_pdf')
                                                    <a href="{{ route('electronic_invoice.documents.pdf', $document->id) }}" target="_blank"
                                                        class="btn btn-sm btn-outline-primary" title="{{ __('zatca.pdf.download') }}">
                                                        <i class="feather icon-file-text"></i>
                                                    </a>
                                                @endpermission
                                            @endif

                                            <a href="{{ route('electronic_invoice.documents.xml', $document->id) }}"
                                                class="btn btn-sm btn-outline-secondary" title="{{ __('zatca.documents.download') }}">
                                                <i class="feather icon-download"></i>
                                            </a>

                                            @if ($document->canRetry() && $document->environment === $settings->environment)
                                                @permission('sales.settings.update')
                                                    <form action="{{ route('electronic_invoice.documents.retry', $document->id) }}"
                                                        method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-primary"
                                                            title="{{ __('zatca.documents.retry') }}">
                                                            <i class="feather icon-refresh-cw"></i>
                                                        </button>
                                                    </form>
                                                @endpermission
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            @if ($documents->hasPages())
                <div class="foot">
                    {{ $documents->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
