@extends('sitting::master')

@section('title', __('settings.telegram.title'))

@section('content')
    <style>
        /* مفتاح تشغيل أفقي: مفاتيح Bootstrap تنكسر في RTL فيهبط النص تحتها — نفس نمط شاشة إعدادات الإشعارات */
        .tg-switch { display: flex; align-items: center; gap: .5rem; cursor: pointer; margin: 0; }
        .tg-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
        .tg-switch .track {
            position: relative; flex: 0 0 auto; width: 42px; height: 22px;
            background: #d9dee5; border-radius: 999px; transition: background .18s;
        }
        .tg-switch .track::after {
            content: ''; position: absolute; top: 3px; right: 3px;
            width: 16px; height: 16px; background: #fff; border-radius: 50%;
            transition: transform .18s; box-shadow: 0 1px 3px rgba(0,0,0,.25);
        }
        .tg-switch input:checked + .track { background: #28c76f; }
        .tg-switch input:checked + .track::after { transform: translateX(-20px); }
        .tg-content-check { display: flex; align-items: center; gap: .5rem; padding: .4rem 0; }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-send"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.telegram.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.telegram.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- Stats -->
        <div class="stats-row mb-2">
            <div class="stat-card-modern {{ $settings->bot_token && $settings->chat_id ? 'success' : 'warning' }}">
                <div class="stat-icon"><i class="feather icon-link"></i></div>
                <div class="stat-label">{{ __('settings.telegram.connection') }}</div>
                <div class="stat-value" style="font-size:1.1rem">
                    {{ $settings->bot_token && $settings->chat_id ? __('settings.notify.ready') : __('settings.notify.not_configured') }}
                </div>
            </div>

            <div class="stat-card-modern {{ $settings->daily_report_enabled ? 'primary' : 'secondary' }}">
                <div class="stat-icon"><i class="feather icon-send"></i></div>
                <div class="stat-label">{{ __('settings.telegram.daily_report_enabled') }}</div>
                <div class="stat-value" style="font-size:1.1rem">
                    {{ $settings->daily_report_enabled ? __('settings.notify.enabled') : __('settings.notify.paused') }}
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <form action="{{ route('Telegram.store') }}" method="POST">
                    @csrf

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title d-flex align-items-center gap-2">
                                <i class="feather icon-link"></i>
                                {{ __('settings.telegram.connection') }}
                            </h4>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">{{ __('settings.telegram.subtitle') }}</p>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>{{ __('settings.telegram.bot_token') }}</label>
                                    {{-- الرمزُ لا يُطبع في الصفحة: به وحدَه يُتحكَّم في البوت. الحقلُ
                                         الفارغ يُبقي المحفوظ، والكتابةُ فيه تستبدله. --}}
                                    <input type="password" name="bot_token" value="" class="form-control" dir="ltr" autocomplete="new-password"
                                           placeholder="{{ filled($settings->bot_token) ? __('settings.telegram.bot_token_saved') : '123456789:AAExample-Token' }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>{{ __('settings.telegram.chat_id') }}</label>
                                    <input type="text" name="chat_id" value="{{ old('chat_id', $settings->chat_id) }}" class="form-control" dir="ltr" placeholder="@channel">
                                    <small class="form-text text-muted">{{ __('settings.telegram.chat_id_hint') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h4 class="card-title d-flex align-items-center gap-2">
                                <i class="feather icon-send"></i>
                                {{ __('settings.telegram.daily_report_enabled') }}
                            </h4>
                            <label class="tg-switch" title="{{ $settings->daily_report_enabled ? __('settings.notify.on_uncheck') : __('settings.notify.on_check') }}">
                                <input type="checkbox" name="daily_report_enabled" value="1" {{ old('daily_report_enabled', $settings->daily_report_enabled) ? 'checked' : '' }}>
                                <span class="track"></span>
                            </label>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">{{ __('settings.telegram.daily_report_hint') }}</p>

                            <h5 class="font-weight-bold mb-2">{{ __('settings.telegram.report_content') }}</h5>
                            <div class="row">
                                @foreach ([
                                    'report_show_sales' => __('settings.telegram.report_show_sales'),
                                    'report_show_collections' => __('settings.telegram.report_show_collections'),
                                    'report_show_returns' => __('settings.telegram.report_show_returns'),
                                    'report_show_expenses' => __('settings.telegram.report_show_expenses'),
                                    'report_show_stock' => __('settings.telegram.report_show_stock'),
                                ] as $key => $label)
                                    <div class="col-md-4 col-6">
                                        <div class="tg-content-check">
                                            <input class="form-check-input" type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1" {{ old($key, $settings->$key) ? 'checked' : '' }}>
                                            <label class="form-check-label mb-0" for="{{ $key }}">{{ $label }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- إشعارات المبيعات — تُرسَل بعد حفظ المستند، ومطفأةٌ حتى تُفعَّل هنا --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title d-flex align-items-center gap-2">
                                <i class="feather icon-shopping-cart"></i>
                                {{ __('settings.telegram.sales_section') }}
                            </h4>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">{{ __('settings.telegram.sales_hint') }}</p>

                            <div class="mb-3">
                                <label>{{ __('settings.telegram.sales_chat_id') }}</label>
                                <input type="text" name="sales_chat_id" value="{{ old('sales_chat_id', $settings->sales_chat_id) }}" class="form-control" dir="ltr" placeholder="@sales_channel">
                                <small class="form-text text-muted">{{ __('settings.telegram.sales_chat_id_hint') }}</small>
                            </div>

                            @foreach (\App\Models\TelegramSetting::SALES_FLAGS as $key)
                                <div class="d-flex align-items-start justify-content-between gap-3 py-2 border-top">
                                    <div>
                                        <div class="font-weight-bold">{{ __('settings.telegram.'.$key) }}</div>
                                        <small class="text-muted">{{ __('settings.telegram.'.$key.'_hint') }}</small>
                                    </div>
                                    <label class="tg-switch">
                                        <input type="checkbox" name="{{ $key }}" value="1" {{ old($key, $settings->$key) ? 'checked' : '' }}>
                                        <span class="track"></span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> {{ __('settings.telegram.save') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="font-weight-bold mb-3 d-flex align-items-center gap-2">
                            <i class="feather icon-help-circle"></i>
                            {{ __('settings.telegram.help_title') }}
                        </h5>
                        <p class="text-muted small mb-3">{{ __('settings.telegram.help_body') }}</p>
                        <a href="https://core.telegram.org/bots#botfather" target="_blank" class="btn btn-outline-primary btn-sm w-100">
                            {{ __('settings.telegram.help_link') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
