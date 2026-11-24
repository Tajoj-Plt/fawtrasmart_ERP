@extends('sitting::master')

@section('title', __('settings.sms_templates.title'))

@section('css')
    <style>
        .tpl { --b:#e3e7ef; --muted:#79809a; --soft:#f7f9fc; --accent: var(--app-primary,#7367F0); --ink:#2f3349; }

        /* عنوان التصنيف: القوالب تجاوزت الستّين، وقائمة مسطّحة بهذا الطول
           لا يُعثر فيها على قالب */
        .tpl-group { margin-bottom:1.1rem; }
        .tpl-group-head { display:flex; align-items:center; gap:.5rem; padding:.5rem .2rem; margin-bottom:.35rem;
            border-bottom:2px solid var(--accent); }
        .tpl-group-head h5 { margin:0; font-size:.92rem; font-weight:700; color:var(--ink);
            display:flex; align-items:center; gap:.45rem; }
        .tpl-group-head .count { margin-inline-start:auto; font-size:.72rem; color:var(--muted); }

        .tpl-name { font-weight:700; color:var(--ink); font-size:.86rem; }
        .tpl-desc { display:block; font-size:.73rem; color:var(--muted); line-height:1.6; margin-top:.15rem; }
        .tpl-body { font-size:.75rem; color:#5a6076; line-height:1.7; }
        .tpl-code { font-family:ui-monospace,monospace; font-size:.72rem; color:#4a4f61; direction:ltr; display:inline-block; }
        .tpl-sys { font-size:.66rem; background:#f1eefc; color:#5a4b9c; border:1px solid #ddd6f5;
            border-radius:20px; padding:.05rem .45rem; white-space:nowrap; }
    </style>
@endsection

@section('content')
    {{-- بطاقات الإحصاء والجدول وشريط التصفية معرَّفة في هذا الملف، وهو ما
         تحمّله صفحة قوالب المستندات أيضاً. بدونه تظهر الصفحة بلا تنسيق. --}}
    <link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">

    <div class="tpl">
    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-message-square"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.sms_templates.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.sms_templates.crumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <div class="btn-group">
                <a href="{{ route('SmsTemplates.create') }}" class="btn btn-primary shadow-sm">
                    <i class="feather icon-plus"></i> {{ __('settings.sms_templates.new') }}
                </a>
            </div>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- القوالب بلا بوابة مضبوطة نصوص لا تصل أحداً — يُقال ذلك صراحةً --}}
        @unless ($configured)
            <div class="alert d-flex align-items-start"
                style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:10px">
                <i class="fas fa-info-circle mt-1 ml-2"></i>
                <div>
                    <strong>{{ __('settings.sms_templates.gateway_off') }}</strong>
                    <span style="font-size:.9rem">
                        {!! __('settings.sms_templates.gateway_off_hint', ['link' => '<a href="'.route('Sms.index').'">'.__('settings.sms_templates.sms_settings').'</a>']) !!}
                    </span>
                </div>
            </div>
        @endunless

        <!-- Stats -->
        <div class="stats-row mb-2">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-file-text"></i></div>
                <div class="stat-label">{{ __('settings.sms_templates.total') }}</div>
                <div class="stat-value">{{ $templates->count() }}</div>
                <div class="stat-trend up"><i class="feather icon-grid"></i> <span>{{ __('settings.sms_templates.in_category', ['count' => $grouped->count()]) }}</span></div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">{{ __('settings.sms_templates.active_count') }}</div>
                <div class="stat-value">{{ $templates->where('is_active', true)->count() }}</div>
                <div class="stat-trend up"><i class="feather icon-trending-up"></i> <span>{{ __('settings.sms_templates.ready_to_send') }}</span></div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-send"></i></div>
                <div class="stat-label">{{ __('settings.sms_templates.sent_messages') }}</div>
                <div class="stat-value">{{ number_format($sentCount) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-list"></i>
                    <a href="{{ route('SmsLogs.index') }}"><span>{{ __('settings.sms_templates.sms_log') }}</span></a>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-settings"></i></div>
                <div class="stat-label">{{ __('settings.sms_templates.gateway_state') }}</div>
                <div class="stat-value" style="font-size:1.1rem">{{ $configured ? __('settings.sms_templates.enabled') : __('settings.sms_templates.not_configured') }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-external-link"></i>
                    <a href="{{ route('Sms.index') }}"><span>{{ __('settings.sms_templates.settings') }}</span></a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card filter-card-modern mb-2">
            <div class="card-body">
                <form method="GET" action="{{ route('SmsTemplates.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-3 col-12 mb-1">
                            <label>{{ __('settings.common.search') }}</label>
                            <input type="text" name="search" class="form-control"
                                value="{{ request('search') }}" placeholder="{{ __('settings.sms_templates.search_placeholder') }}">
                        </div>

                        <div class="col-md-3 col-12 mb-1">
                            <label>{{ __('settings.sms_templates.category') }}</label>
                            <select name="category" class="form-control">
                                <option value="">{{ __('settings.sms_templates.all_categories') }}</option>
                                @foreach ($categories as $key => $label)
                                    <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.sms_templates.event') }}</label>
                            <select name="event" class="form-control">
                                <option value="">{{ __('settings.sms_templates.all_events_option') }}</option>
                                @foreach ($eventGroups as $group)
                                    <optgroup label="{{ $group['label'] }}">
                                        @foreach ($group['events'] as $key => $label)
                                            <option value="{{ $key }}" {{ request('event') === $key ? 'selected' : '' }}>
                                                {{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>{{ __('settings.common.status') }}</label>
                            <select name="is_active" class="form-control">
                                <option value="">{{ __('settings.sms_templates.all_statuses') }}</option>
                                <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('settings.sms_templates.active') }}</option>
                                <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('settings.sms_templates.inactive') }}</option>
                            </select>
                        </div>

                        <div class="col-md-2 col-12 mb-1">
                            <label>&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-search"></i>
                                </button>
                                <a href="{{ route('SmsTemplates.index') }}" class="btn btn-secondary btn-block mt-0 ml-1">
                                    <i class="fa fa-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Groups -->
        @forelse ($grouped as $categoryKey => $items)
            <div class="tpl-group">
                <div class="tpl-group-head">
                    <h5>
                        <i class="feather icon-{{ \App\Models\SmsTemplate::categoryIcon($categoryKey) }}" style="color:var(--accent)"></i>
                        {{ $categories[$categoryKey] ?? \App\Models\SmsTemplate::categoryLabel($categoryKey) }}
                    </h5>
                    <span class="count">{{ __('settings.sms_templates.in_category', ['count' => $items->count()]) }}</span>
                </div>

                <div class="card table-card-modern mb-0">
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('settings.sms_templates.name') }}</th>
                                        <th>{{ __('settings.sms_templates.code') }}</th>
                                        <th>{{ __('settings.sms_templates.sent_on') }}</th>
                                        <th>{{ __('settings.sms_templates.body') }}</th>
                                        <th class="text-center">{{ __('settings.sms_templates.length') }}</th>
                                        <th class="text-center">{{ __('settings.common.status') }}</th>
                                        <th class="text-center" style="width:70px">{{ __('settings.common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $template)
                                        <tr>
                                            <td style="min-width:210px">
                                                <span class="tpl-name">{{ $template->name }}</span>
                                                @if ($template->is_system)
                                                    <span class="tpl-sys" title="{{ __('settings.sms_templates.system_hint') }}">
                                                        {{ __('settings.sms_templates.system_badge') }}</span>
                                                @endif
                                                <span class="tpl-desc">{{ $template->description ?: __('settings.sms_templates.no_description') }}</span>
                                            </td>
                                            <td><code class="tpl-code">{{ $template->code }}</code></td>
                                            <td>
                                                <span class="badge badge-light-primary">
                                                    {{ $events[$template->event] ?? __('settings.sms_templates.no_event') }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="tpl-body d-inline-block text-truncate" style="max-width:300px"
                                                    title="{{ $template->body }}">{{ $template->body }}</span>
                                            </td>
                                            <td class="text-center">
                                                {{-- العربية Unicode: حدّ الرسالة 70 حرفاً لا 160، والتجاوز يضاعف الكلفة --}}
                                                @php $segments = $template->segments(); @endphp
                                                <span class="badge {{ $segments > 3 ? 'badge-light-warning' : 'badge-light-secondary' }}"
                                                    title="{{ __('settings.sms_templates.chars', ['count' => mb_strlen($template->body)]) }}">
                                                    {{ __('settings.sms_templates.segments', ['count' => $segments]) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge {{ $template->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                                    {{ $template->is_active ? __('settings.sms_templates.active') : __('settings.sms_templates.inactive') }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="dropdown">
                                                    <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                        type="button" data-toggle="dropdown" aria-expanded="false"></button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item" href="{{ route('SmsTemplates.edit', $template->id) }}">
                                                            <i class="feather icon-edit-2"></i> {{ __('settings.sms_templates.edit_template') }}
                                                        </a>

                                                        <form method="POST" action="{{ route('SmsTemplates.toggle', $template->id) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="feather icon-power"></i>
                                                                {{ $template->is_active ? __('settings.sms_templates.disable') : __('settings.sms_templates.enable') }}
                                                            </button>
                                                        </form>

                                                        <form method="POST" action="{{ route('SmsTemplates.duplicate', $template->id) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="feather icon-copy"></i> {{ __('settings.sms_templates.copy') }}
                                                            </button>
                                                        </form>

                                                        <div class="dropdown-divider"></div>

                                                        {{-- القالب النظامي يستدعيه الكود برمزه؛ حذفه يُعطّل ميزة --}}
                                                        @if ($template->is_system)
                                                            <span class="dropdown-item text-muted" style="font-size:.76rem;white-space:normal">
                                                                <i class="feather icon-lock"></i> {{ __('settings.sms_templates.system_locked') }}
                                                            </span>
                                                        @else
                                                            <form method="POST" action="{{ route('SmsTemplates.destroy', $template->id) }}"
                                                                onsubmit="return confirm('{{ __('settings.sms_templates.confirm_delete', ['name' => $template->name]) }}')">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="feather icon-trash-2"></i> {{ __('settings.common.delete') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
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
            <div class="card">
                <div class="card-body text-center py-4 text-muted">
                    {{ __('settings.sms_templates.no_match') }}
                    <a href="{{ route('SmsTemplates.create') }}">{{ __('settings.sms_templates.add_new') }}</a>
                    {{ __('settings.sms_templates.restore_defaults') }}
                    <code>php artisan db:seed --class=SmsTemplateSeeder</code>
                </div>
            </div>
        @endforelse
    </div>
    </div>
@endsection
