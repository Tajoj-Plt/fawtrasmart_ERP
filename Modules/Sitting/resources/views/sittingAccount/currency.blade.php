@extends('sitting::master')

@section('title', __('settings.currency.title'))

@section('content')
    @include('sitting::components._settings_styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-settings"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.currency.manage') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.currency.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('SittingAccount.currency') }}" class="btn btn-outline-primary">
                <i class="feather icon-refresh-cw"></i> {{ __('settings.common.reset_filter') }}
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
                <div class="ic primary"><i class="feather icon-dollar-sign"></i></div>
                <div>
                    <div class="val">{{ $stats['total'] }}</div>
                    <div class="lbl">{{ __('settings.currency.stat_total') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic success"><i class="feather icon-check-circle"></i></div>
                <div>
                    <div class="val">{{ $stats['active'] }}</div>
                    <div class="lbl">{{ __('settings.currency.stat_active') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic danger"><i class="feather icon-slash"></i></div>
                <div>
                    <div class="val">{{ max($stats['total'] - $stats['active'], 0) }}</div>
                    <div class="lbl">{{ __('settings.currency.stat_inactive') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic warning"><i class="feather icon-edit-3"></i></div>
                <div>
                    <div class="val">{{ $stats['manual'] }}</div>
                    <div class="lbl">{{ __('settings.currency.stat_manual') }}</div>
                </div>
            </div>
        </div>

        <div class="st-note info">
            <i class="feather icon-info mt-1"></i>
            <div>
                <strong>{{ __('settings.currency.guide') }}</strong>
                <p>{{ __('settings.currency.guide_1') }} {{ __('settings.currency.guide_2') }}</p>
                <p>{{ __('settings.currency.guide_3') }} {{ __('settings.currency.guide_4') }}</p>
            </div>
        </div>

        {{-- ── البحث والتصفية ── --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-filter" style="color:#2b6cb0"></i>
                <h4>{{ __('settings.common.search_filter') }}</h4>
            </div>
            <div class="body">
                <form method="GET" action="{{ route('SittingAccount.currency') }}" id="currencyFilterForm">
                    <div class="row">
                        <div class="col-md-5 col-12 mb-1">
                            <input type="text" name="search" class="form-control"
                                placeholder="{{ __('settings.currency.search_placeholder') }}"
                                value="{{ request('search') }}">
                        </div>
                        <div class="col-md-3 col-12 mb-1">
                            <select name="status" class="form-control">
                                <option value="">{{ __('settings.common.all_statuses') }}</option>
                                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('settings.common.is_active') }}</option>
                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('settings.common.is_inactive') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-12 mb-1">
                            <select name="source" class="form-control">
                                <option value="">{{ __('settings.currency.source_all') }}</option>
                                <option value="manual" {{ request('source') === 'manual' ? 'selected' : '' }}>{{ __('settings.currency.source_manual') }}</option>
                                <option value="api" {{ request('source') === 'api' ? 'selected' : '' }}>{{ __('settings.currency.source_api') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-12 mb-1">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="feather icon-search"></i> {{ __('settings.common.search') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if ($defaultLocked)
            <div class="alert alert-warning" role="alert" style="border-radius:10px">
                <i class="feather icon-lock"></i>
                <strong>{{ __('settings.currency.locked_banner_title') }}</strong>
                <div style="font-size:.86rem;margin-top:.25rem">
                    {{ __('settings.currency.locked_banner_body', ['usage' => $defaultUsage]) }}
                </div>
            </div>
        @endif

        {{-- ── الجدول ── --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-dollar-sign" style="color:#2b6cb0"></i>
                <h4>{{ __('settings.currency.list') }}</h4>
                <span class="count">{{ $system_currencies->total() }}</span>
            </div>
            <div class="body flush">
                <div class="table-responsive">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th style="width:24%">{{ __('settings.currency.currency') }}</th>
                                <th style="width:9%">{{ __('settings.currency.code') }}</th>
                                <th style="width:14%">{{ __('settings.currency.country') }}</th>
                                <th style="width:11%" class="text-center">{{ __('settings.currency.rate') }}</th>
                                <th style="width:10%" class="text-center">{{ __('settings.currency.source') }}</th>
                                <th style="width:11%">{{ __('settings.currency.updated_at') }}</th>
                                <th style="width:9%" class="text-center">{{ __('settings.common.status') }}</th>
                                <th style="width:9%" class="text-center">{{ __('settings.currency.is_default') }}</th>
                                <th style="width:110px" class="text-center">{{ __('settings.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($system_currencies as $curr)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center" style="gap:.6rem">
                                            <span style="flex:0 0 auto;width:34px;height:26px;background:#eef1f5;border-radius:6px;
                                                display:inline-grid;place-items:center;font-size:.82rem;font-weight:700;color:#2b6cb0">
                                                {{ $curr->symbol ?: strtoupper(substr($curr->code, 0, 2)) }}
                                            </span>
                                            <span style="min-width:0">
                                                <b style="display:block;font-weight:600;font-size:.93rem;color:#1b2430">{{ $curr->name }}</b>
                                                @if ($curr->english_name && $curr->english_name !== $curr->name)
                                                    <small style="color:#8a93a0;font-size:.76rem">{{ $curr->english_name }}</small>
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td><span class="st-mono">{{ strtoupper($curr->code) }}</span></td>
                                    <td style="font-size:.85rem;color:#6b7280">{{ $curr->country ?: '—' }}</td>
                                    <td class="num text-center">{{ rtrim(rtrim(number_format($curr->exchange_rate, 4), '0'), '.') ?: '0' }}</td>
                                    <td class="text-center">
                                        <span class="st-badge {{ $curr->rate_source === 'api' ? 'info' : 'off' }}">
                                            {{ $curr->rate_source === 'api' ? __('settings.currency.source_api') : __('settings.currency.source_manual') }}
                                        </span>
                                    </td>
                                    <td class="num" style="font-size:.82rem;color:#8a93a0">
                                        {{ $curr->updated_at ? $curr->updated_at->format('Y-m-d') : '—' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="st-badge {{ $curr->is_active ? 'on' : 'off' }}">
                                            {{ $curr->is_active ? __('settings.common.is_active') : __('settings.common.is_inactive') }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if ($curr->is_default)
                                            <span class="st-badge warn" title="{{ __('settings.currency.default_title') }}">
                                                {{ __('settings.currency.default_badge') }}
                                            </span>
                                        @else
                                            <span style="color:#b7bec8">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <div class="dropdown">
                                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                    type="button" data-toggle="dropdown" aria-expanded="false">
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    @if (! $curr->is_default && $defaultLocked)
                                                        {{-- الافتراضية مقفلة بحركات: الزر يُعرض معطّلاً لا مخفياً،
                                                             فالغياب يبدو عطلاً بينما التعطيل يشرح سببه --}}
                                                        <span class="dropdown-item text-muted"
                                                            style="cursor:not-allowed"
                                                            title="{{ __('settings.currency.set_default_locked_title', ['usage' => $defaultUsage]) }}">
                                                            <i class="feather icon-lock"></i> {{ __('settings.currency.set_default') }}
                                                        </span>
                                                    @elseif (! $curr->is_default)
                                                        <form action="{{ route('SittingAccount.currency.store') }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="currency" value="{{ $curr->code }}">
                                                            <button type="submit" class="dropdown-item"
                                                                title="{{ __('settings.currency.set_default_title') }}">
                                                                <i class="feather icon-star"></i> {{ __('settings.currency.set_default') }}
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="dropdown-item text-muted">
                                                            <i class="feather icon-check"></i> {{ __('settings.currency.is_default_now') }}
                                                        </span>
                                                    @endif

                                                    <div class="dropdown-divider"></div>

                                                    <form action="{{ route('SittingAccount.currency.toggleActive') }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{ $curr->id }}">
                                                        <button type="submit" class="dropdown-item"
                                                            title="{{ __('settings.common.toggle_state_title') }}">
                                                            <i class="feather icon-power"></i>
                                                            {{ $curr->is_active ? __('settings.common.disable') : __('settings.common.enable') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="st-empty">
                                            <i class="feather icon-dollar-sign"></i>
                                            {{ __('settings.common.no_data') }}
                                            <div style="font-size:.82rem">{{ __('settings.currency.empty_hint') }}</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($system_currencies->hasPages())
                <div class="foot">
                    <div class="d-flex justify-content-center">
                        @include('sitting::components.table-foot', ['paginator' => $system_currencies])
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection
