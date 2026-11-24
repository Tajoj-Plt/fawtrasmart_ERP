@extends('sitting::master')

@section('title', __('settings.payment.title'))

@section('content')
    @include('sitting::components._settings_styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-credit-card"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.payment.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.payment.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('PaymentMethods.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> {{ __('settings.payment.new') }}
            </a>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="st-stats">
            <div class="st-stat">
                <div class="ic primary"><i class="feather icon-credit-card"></i></div>
                <div>
                    <div class="val">{{ $stats['total'] }}</div>
                    <div class="lbl">{{ __('settings.payment.total') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic success"><i class="feather icon-check-circle"></i></div>
                <div>
                    <div class="val">{{ $stats['active'] }}</div>
                    <div class="lbl">{{ __('settings.payment.active') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic info"><i class="feather icon-smartphone"></i></div>
                <div>
                    <div class="val">{{ $stats['electronic'] }}</div>
                    <div class="lbl">{{ __('settings.payment.electronic') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic warning"><i class="feather icon-globe"></i></div>
                <div>
                    <div class="val">{{ $stats['online'] }}</div>
                    <div class="lbl">{{ __('settings.payment.online') }}</div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('update.payment.status') }}" id="statusForm">
            @csrf
            @method('PUT')

            @foreach ([
                ['title' => __('settings.payment.normal_section'), 'icon' => 'dollar-sign', 'items' => $payments,
                 'empty' => __('settings.payment.normal_empty')],
                ['title' => __('settings.payment.electronic_section'), 'icon' => 'smartphone', 'items' => $payments_electronics,
                 'empty' => __('settings.payment.electronic_empty')],
            ] as $section)
                <div class="st-card">
                    <div class="head">
                        <i class="feather icon-{{ $section['icon'] }}" style="color:#2b6cb0"></i>
                        <h4>{{ $section['title'] }}</h4>
                        <span class="count">{{ $section['items']->count() }}</span>
                    </div>
                    <div class="body flush">
                        <div class="table-responsive">
                            <table class="st-table">
                                <thead>
                                    <tr>
                                        <th style="width:60px" class="text-center">{{ __('settings.payment.toggle') }}</th>
                                        <th>{{ __('settings.common.name') }}</th>
                                        <th>{{ __('settings.common.code') }}</th>
                                        <th>{{ __('settings.common.description') }}</th>
                                        <th class="text-center">{{ __('settings.payment.store') }}</th>
                                        <th class="text-center">{{ __('settings.common.status') }}</th>
                                        <th class="text-center" style="width:110px">{{ __('settings.common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($section['items'] as $method)
                                        <tr>
                                            <td class="text-center">
                                                <label class="st-switch justify-content-center"
                                                    title="{{ $method->status === 'active' ? __('settings.payment.disable_hint') : __('settings.payment.enable_hint') }}">
                                                    <input type="hidden" name="payments[{{ $method->id }}][status]" value="inactive">
                                                    <input type="checkbox" name="payments[{{ $method->id }}][status]"
                                                        value="active" {{ $method->status === 'active' ? 'checked' : '' }}>
                                                    <span class="track"></span>
                                                </label>
                                            </td>
                                            <td>
                                                <b>{{ $method->name }}</b>
                                                @if ($method->is_default)
                                                    <span class="st-badge warn">{{ __('settings.payment.is_default') }}</span>
                                                @endif
                                                @if ($method->name_en)
                                                    <div style="font-size:.76rem;color:#8a93a0">{{ $method->name_en }}</div>
                                                @endif
                                                @if ($method->hasGatewayCredentials())
                                                    <span class="st-badge {{ $method->gateway_mode === 'live' ? 'on' : 'info' }}"
                                                        title="{{ __('settings.payment.gateway_linked') }}">
                                                        <i class="feather icon-key"></i>
                                                        {{ __('settings.payment.gateway_mode_' . ($method->gateway_mode ?: 'test')) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($method->code)
                                                    <span class="st-mono">{{ $method->code }}</span>
                                                @else
                                                    <span style="color:#b7bec8">—</span>
                                                @endif
                                            </td>
                                            <td style="font-size:.85rem;color:#6b7280">
                                                {{ \Illuminate\Support\Str::limit($method->description, 55) ?: '—' }}
                                            </td>
                                            <td class="text-center">
                                                @if ($method->is_online)
                                                    <span class="st-badge on">{{ __('settings.payment.available') }}</span>
                                                @else
                                                    <span class="st-badge off">{{ __('settings.common.no') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="st-badge {{ $method->status === 'active' ? 'on' : 'off' }}">
                                                    {{ $method->status === 'active' ? __('settings.common.enabled') : __('settings.common.disabled') }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('PaymentMethods.edit', $method->id) }}"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="{{ __('settings.common.edit') }}">
                                                    <i class="feather icon-edit-2"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger pm-delete"
                                                    data-id="{{ $method->id }}" data-name="{{ $method->name }}"
                                                    @if ($method->is_default) disabled title="{{ __('settings.payment.default_locked') }}" @endif>
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                <div class="st-empty">
                                                    <i class="feather icon-{{ $section['icon'] }}"></i>
                                                    {{ $section['empty'] }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($stats['total'] > 0)
                <div class="st-card">
                    <div class="body text-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> {{ __('settings.payment.save_statuses') }}
                        </button>
                        <div style="font-size:.82rem;color:#8a93a0;margin-top:.5rem">
                            {{ __('settings.payment.statuses_note') }}
                        </div>
                    </div>
                </div>
            @endif
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var deleteUrl = @json(route('PaymentMethods.destroy', ['id' => '__ID__']));

            document.addEventListener('click', function (event) {
                var button = event.target.closest('.pm-delete');

                if (!button || button.disabled) return;

                if (!confirm(@json(__('settings.payment.confirm_delete')).replace(':name', button.dataset.name))) return;

                button.disabled = true;

                fetch(deleteUrl.replace('__ID__', button.dataset.id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                    .then(function (res) {
                        if (res.ok && res.body.success) {
                            button.closest('tr').remove();
                        } else {
                            button.disabled = false;
                            alert(res.body.message || @json(__('settings.common.delete_failed')));
                        }
                    })
                    .catch(function () {
                        button.disabled = false;
                        alert(@json(__('settings.common.connection_failed')));
                    });
            });
        })();
    </script>
@endsection
