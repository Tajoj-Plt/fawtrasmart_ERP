@extends('sitting::master')

@section('title', __('settings.notify.title'))

@section('content')
    {{-- بطاقات الإحصاء والجدول وشريط التصفية معرَّفة في هذا الملف، وهو نفسه
         الذي تحمّله قوالب المستندات وقوالب الرسائل. --}}
    <link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">

    <style>
        /* مفتاح تشغيل أفقي: مفاتيح Bootstrap تنكسر في RTL فيهبط النص تحتها */
        .ns-switch { display: flex; align-items: center; gap: .5rem; cursor: pointer; margin: 0; }
        .ns-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
        .ns-switch .track {
            position: relative; flex: 0 0 auto; width: 42px; height: 22px;
            background: #d9dee5; border-radius: 999px; transition: background .18s;
        }
        .ns-switch .track::after {
            content: ''; position: absolute; top: 3px; right: 3px;
            width: 16px; height: 16px; background: #fff; border-radius: 50%;
            transition: transform .18s; box-shadow: 0 1px 3px rgba(0,0,0,.25);
        }
        .ns-switch input:checked + .track { background: #28c76f; }
        .ns-switch input:checked + .track::after { transform: translateX(-20px); }

        .ns-group { margin-bottom: 1.25rem; }
        .ns-group-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; padding: .85rem 1.15rem; border-radius: 10px 10px 0 0;
            background: #f7f8fa; border: 1px solid #e6e9ef; border-bottom: 0;
        }
        .ns-group-title { display: flex; align-items: center; gap: .6rem; font-weight: 600; margin: 0; }
        .ns-group-count { font-size: .78rem; color: #6b7280; font-weight: 400; }
        .ns-chan { display: inline-flex; align-items: center; gap: .25rem; font-size: .72rem;
            padding: .18rem .5rem; border-radius: 6px; background: #eef2ff; color: #4338ca; margin-inline-end: .25rem; }
        .ns-chan.off { background: #f3f4f6; color: #9ca3af; }
        .ns-key { font-size: .72rem; color: #6b7280; direction: ltr; display: inline-block; }
        .ns-row-off td { opacity: .55; }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-bell"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.notify.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.notify.crumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($total === 0)
            <div class="alert d-flex align-items-start"
                style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:10px">
                <i class="fas fa-info-circle mt-1 ml-2"></i>
                <div>
                    <strong>{{ __('settings.notify.no_catalog') }}</strong>
                    <span style="font-size:.9rem">
                        {!! __('settings.notify.no_catalog_hint') !!}
                    </span>
                </div>
            </div>
        @endif

        @if ($discoveredCount > 0)
            <div class="alert d-flex align-items-start"
                style="background:#eef6ff;border:1px solid #b9d9ff;color:#0b4c8c;border-radius:10px">
                <i class="fas fa-search mt-1 ml-2"></i>
                <div>
                    <strong>{{ __('settings.notify.discovered', ['count' => $discoveredCount]) }}</strong>
                    <span style="font-size:.9rem">
                        {{ __('settings.notify.discovered_hint') }}
                    </span>
                </div>
            </div>
        @endif

        <!-- Stats -->
        <div class="stats-row mb-2">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-bell"></i></div>
                <div class="stat-label">{{ __('settings.notify.types') }}</div>
                <div class="stat-value">{{ $total }}</div>
                <div class="stat-trend up"><i class="feather icon-list"></i> <span>{{ __('settings.notify.categories_count', ['count' => count($categories)]) }}</span></div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">{{ __('settings.notify.enabled_types') }}</div>
                <div class="stat-value">{{ $enabledCount }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-power"></i>
                    <span>{{ __('settings.notify.paused_count', ['count' => $total - $enabledCount]) }}</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-mail"></i></div>
                <div class="stat-label">{{ __('settings.notify.mail_channel') }}</div>
                <div class="stat-value" style="font-size:1.1rem">{{ $mailReady ? __('settings.notify.ready') : __('settings.notify.not_configured') }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-external-link"></i>
                    <a href="{{ route('SMPT.index') }}"><span>{{ __('settings.notify.smtp_settings') }}</span></a>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-message-square"></i></div>
                <div class="stat-label">{{ __('settings.notify.sms_channel') }}</div>
                <div class="stat-value" style="font-size:1.1rem">{{ $smsReady ? __('settings.notify.ready') : __('settings.notify.not_configured') }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-external-link"></i>
                    <a href="{{ route('Sms.index') }}"><span>{{ __('settings.notify.sms_settings') }}</span></a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card filter-card-modern mb-2">
            <div class="card-body">
                <form method="GET" action="{{ route('NotificationSettings.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-4 col-12 mb-1">
                            <label>{{ __('settings.common.search') }}</label>
                            <input type="text" name="search" class="form-control"
                                value="{{ request('search') }}" placeholder="{{ __('settings.notify.search_placeholder') }}">
                        </div>

                        <div class="col-md-3 col-12 mb-1">
                            <label>{{ __('settings.notify.category') }}</label>
                            <select name="category" class="form-control">
                                <option value="">{{ __('settings.notify.all_categories') }}</option>
                                @foreach ($categories as $key => $meta)
                                    <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                                        {{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.common.status') }}</label>
                            <select name="is_enabled" class="form-control">
                                <option value="">{{ __('settings.common.all') }}</option>
                                <option value="1" {{ request('is_enabled') === '1' ? 'selected' : '' }}>{{ __('settings.notify.enabled') }}</option>
                                <option value="0" {{ request('is_enabled') === '0' ? 'selected' : '' }}>{{ __('settings.notify.paused') }}</option>
                            </select>
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.notify.channel') }}</label>
                            <select name="channel" class="form-control">
                                <option value="">{{ __('settings.common.all') }}</option>
                                @foreach ($channels as $key => $meta)
                                    <option value="{{ $key }}" {{ request('channel') === $key ? 'selected' : '' }}>
                                        {{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-1 col-12 mb-1">
                            <label>&nbsp;</label>
                            <div class="d-flex">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-search"></i>
                                </button>
                                <a href="{{ route('NotificationSettings.index') }}"
                                    class="btn btn-secondary btn-block mt-0 ml-1">
                                    <i class="fa fa-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Groups -->
        @forelse ($categories as $catKey => $catMeta)
            @php $rows = $settings[$catKey] ?? collect(); @endphp
            @continue($rows->isEmpty())

            <div class="ns-group">
                <div class="ns-group-head">
                    <h4 class="ns-group-title">
                        <i class="feather icon-{{ $catMeta['icon'] }} text-{{ $catMeta['color'] }}"></i>
                        {{ $catMeta['label'] }}
                        <span class="ns-group-count">
                            {{ __('settings.notify.rows_summary', ['count' => $rows->count(), 'enabled' => $rows->where('is_enabled', true)->count()]) }}
                        </span>
                    </h4>
                    <div class="d-flex" style="gap:.4rem">
                        <form method="POST" action="{{ route('NotificationSettings.toggleCategory', $catKey) }}">
                            @csrf
                            <input type="hidden" name="enable" value="1">
                            <button class="btn btn-sm btn-outline-success" type="submit">
                                <i class="feather icon-check"></i> {{ __('settings.notify.enable_all') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('NotificationSettings.toggleCategory', $catKey) }}"
                            onsubmit="return confirm('{{ __('settings.notify.confirm_disable_category', ['category' => $catMeta['label']]) }}')">
                            @csrf
                            <input type="hidden" name="enable" value="0">
                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                <i class="feather icon-x"></i> {{ __('settings.notify.disable_all') }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card table-card-modern mb-0" style="border-radius:0 0 10px 10px">
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:70px" class="text-center">{{ __('settings.notify.toggle') }}</th>
                                        <th>{{ __('settings.notify.notification') }}</th>
                                        <th>{{ __('settings.notify.key') }}</th>
                                        <th>{{ __('settings.notify.channels') }}</th>
                                        <th class="text-center">{{ __('settings.notify.recorded') }}</th>
                                        <th class="text-center" style="width:120px">{{ __('settings.common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $setting)
                                        <tr class="{{ $setting->is_enabled ? '' : 'ns-row-off' }}">
                                            <td class="text-center">
                                                <form method="POST"
                                                    action="{{ route('NotificationSettings.toggle', $setting->id) }}"
                                                    id="tg-{{ $setting->id }}">
                                                    @csrf
                                                    <label class="ns-switch justify-content-center"
                                                        title="{{ $setting->is_enabled ? __('settings.notify.on_uncheck') : __('settings.notify.on_check') }}">
                                                        <input type="checkbox" {{ $setting->is_enabled ? 'checked' : '' }}
                                                            onchange="document.getElementById('tg-{{ $setting->id }}').submit()">
                                                        <span class="track"></span>
                                                    </label>
                                                </form>
                                            </td>

                                            <td>
                                                <strong>{{ $setting->label }}</strong>
                                                @if ($setting->is_discovered)
                                                    <span class="badge badge-light-info" style="font-size:.65rem">{{ __('settings.notify.uncategorized') }}</span>
                                                @endif
                                                @if ($setting->description)
                                                    <div class="text-muted" style="font-size:.78rem">{{ $setting->description }}</div>
                                                @endif
                                            </td>

                                            <td><code class="ns-key">{{ $setting->key }}</code></td>

                                            <td>
                                                @foreach ($channels as $chKey => $chMeta)
                                                    @php $on = in_array($chKey, $setting->channels ?: ['database'], true); @endphp
                                                    <span class="ns-chan {{ $on ? '' : 'off' }}" title="{{ $chMeta['hint'] }}">
                                                        <i class="feather icon-{{ $chMeta['icon'] }}"></i> {{ $chMeta['label'] }}
                                                    </span>
                                                @endforeach
                                            </td>

                                            <td class="text-center">
                                                @php $count = $counts[$setting->key] ?? 0; @endphp
                                                <span class="badge {{ $count > 0 ? 'badge-light-secondary' : 'badge-light-light' }}">
                                                    {{ number_format($count) }}
                                                </span>
                                            </td>

                                            <td class="text-center">
                                                <a href="{{ route('NotificationSettings.edit', $setting->id) }}"
                                                    class="btn btn-sm bg-gradient-info">
                                                    <i class="feather icon-edit-2"></i> {{ __('settings.common.edit') }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @empty
        @endforelse

        @if ($settings->isEmpty() && $total > 0)
            <div class="card">
                <div class="card-body text-center text-muted py-4">
                    {{ __('settings.notify.no_match') }}
                    <a href="{{ route('NotificationSettings.index') }}">{{ __('settings.notify.clear_filter') }}</a>
                </div>
            </div>
        @endif
    </div>
@endsection
