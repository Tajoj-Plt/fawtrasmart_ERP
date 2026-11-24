@extends('sitting::master')

@section('title', __('languages.title'))

@section('content')
    @include('sitting::components._settings_styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-globe"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('languages.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('languages.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('languages.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('languages.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('SittingAccount.language') }}" class="btn btn-outline-primary">
                <i class="feather icon-refresh-cw"></i> {{ __('languages.reset') }}
            </a>
            @if (canLanguages('create'))
                <a href="{{ route('languages.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('languages.create') }}
                </a>
            @endif

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
                <div class="ic primary"><i class="feather icon-globe"></i></div>
                <div>
                    <div class="val">{{ $stats['total'] ?? 0 }}</div>
                    <div class="lbl">{{ __('languages.stats.total') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic success"><i class="feather icon-check-circle"></i></div>
                <div>
                    <div class="val">{{ $stats['active'] ?? 0 }}</div>
                    <div class="lbl">{{ __('languages.stats.active') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic danger"><i class="feather icon-slash"></i></div>
                <div>
                    <div class="val">{{ $stats['inactive'] ?? 0 }}</div>
                    <div class="lbl">{{ __('languages.stats.inactive') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic info"><i class="feather icon-align-right"></i></div>
                <div>
                    <div class="val">{{ $stats['rtl'] ?? 0 }}</div>
                    <div class="lbl">{{ __('languages.stats.rtl') }}</div>
                </div>
            </div>
        </div>

        @php
            // __() تُعيد المفتاح نصاً حين تغيب الترجمة، فلا يُدار عليه foreach مباشرة
            $guidelines = __('languages.guidelines');
            $guidelines = is_array($guidelines) ? $guidelines : [];
        @endphp
        @if (! empty($guidelines))
            <div class="st-note info">
                <i class="feather icon-info mt-1"></i>
                <div>
                    <strong>{{ __('languages.guidelines_title') }}</strong>
                    @foreach ($guidelines as $guideline)
                        <p>{{ $guideline }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── البحث والتصفية ── --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-filter" style="color:#2b6cb0"></i>
                <h4>{{ __('languages.search') }}</h4>
            </div>
            <div class="body">
                <form method="GET" action="{{ route('SittingAccount.language') }}" id="languageSearchForm">
                    <div class="row">
                        <div class="col-md-5 col-12 mb-1">
                            <input type="text" name="search" class="form-control"
                                placeholder="{{ __('languages.search_placeholder') }}"
                                value="{{ request('search') }}">
                        </div>
                        <div class="col-md-3 col-12 mb-1">
                            <select name="status" class="form-control">
                                <option value="">{{ __('languages.all_statuses') }}</option>
                                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('languages.active') }}</option>
                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('languages.inactive') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-12 mb-1">
                            <select name="direction" class="form-control">
                                <option value="">{{ __('languages.all_directions') }}</option>
                                <option value="rtl" {{ request('direction') === 'rtl' ? 'selected' : '' }}>RTL</option>
                                <option value="ltr" {{ request('direction') === 'ltr' ? 'selected' : '' }}>LTR</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-12 mb-1">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="feather icon-search"></i> {{ __('languages.filter') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── الجدول ── --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-globe" style="color:#2b6cb0"></i>
                <h4>{{ __('languages.list') }}</h4>
                <span class="count">{{ $system_languages->total() }}</span>
            </div>
            <div class="body flush">
                <div class="table-responsive">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th style="width:26%">{{ __('languages.name') }}</th>
                                <th style="width:9%">{{ __('languages.code') }}</th>
                                <th style="width:11%">{{ __('languages.locale') }}</th>
                                <th style="width:9%" class="text-center">{{ __('languages.direction') }}</th>
                                <th style="width:11%" class="text-center">{{ __('languages.translation') }}</th>
                                <th style="width:9%" class="text-center">{{ __('languages.status') }}</th>
                                <th style="width:9%" class="text-center">{{ __('languages.default') }}</th>
                                <th style="width:150px" class="text-center">{{ __('languages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($system_languages as $lang)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center" style="gap:.6rem">
                                            @if ($lang->flagIsImage())
                                                <img src="{{ asset('flags/' . $lang->flag) }}" alt="{{ $lang->code }}"
                                                    style="flex:0 0 auto;width:34px;height:26px;border-radius:6px;object-fit:cover">
                                            @elseif ($lang->flagIsText())
                                                <span style="flex:0 0 auto;font-size:1.4rem;line-height:1">{{ $lang->flag }}</span>
                                            @else
                                                <span style="flex:0 0 auto;width:34px;height:26px;background:#eef1f5;border-radius:6px;
                                                    display:inline-grid;place-items:center;font-size:.72rem;font-weight:700;color:#2b6cb0">
                                                    {{ strtoupper(substr($lang->code, 0, 2)) }}
                                                </span>
                                            @endif
                                            <span style="min-width:0">
                                                <b style="display:block;font-weight:600;font-size:.93rem;color:#1b2430">{{ $lang->name }}</b>
                                                @if ($lang->native_name)
                                                    <small style="color:#8a93a0;font-size:.76rem">{{ $lang->native_name }}</small>
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td><span class="st-mono">{{ strtoupper($lang->code) }}</span></td>
                                    <td><span class="st-mono">{{ $lang->locale ?: $lang->code }}</span></td>
                                    <td class="text-center">
                                        <span class="st-badge {{ $lang->isRtl() ? 'info' : 'off' }}">
                                            {{ $lang->isRtl() ? 'RTL' : 'LTR' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if ($lang->translation_file)
                                            <span class="st-badge on" title="{{ $lang->translation_file }}">
                                                {{ __('languages.uploaded') }}
                                            </span>
                                        @else
                                            <span style="color:#b7bec8">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="st-badge {{ $lang->is_active ? 'on' : 'off' }}">
                                            {{ $lang->is_active ? __('languages.active') : __('languages.inactive') }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if ($lang->is_default)
                                            <span class="st-badge warn" title="{{ __('languages.is_the_default') }}">
                                                {{ __('languages.default') }}
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
                                                    <a class="dropdown-item" href="{{ route('languages.show', $lang->id) }}">
                                                        <i class="feather icon-eye"></i> {{ __('languages.show') }}
                                                    </a>

                                                    @if (canLanguages('edit'))
                                                        <a class="dropdown-item" href="{{ route('languages.edit', $lang->id) }}">
                                                            <i class="feather icon-edit-2"></i> {{ __('languages.edit') }}
                                                        </a>
                                                    @endif

                                                    @if (canLanguages('set_default'))
                                                        @if ($lang->is_default)
                                                            <span class="dropdown-item text-muted">
                                                                <i class="feather icon-check"></i> {{ __('languages.is_the_default') }}
                                                            </span>
                                                        @else
                                                            <form action="{{ route('languages.set-default', $lang->id) }}" method="POST"
                                                                onsubmit="return confirm({{ Illuminate\Support\Js::from(__('languages.confirm_set_default')) }});">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item" @disabled(! $lang->is_active)
                                                                    title="{{ $lang->is_active ? __('languages.set_as_default') : __('languages.default_requires_active') }}">
                                                                    <i class="feather icon-star"></i> {{ __('languages.set_as_default') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endif

                                                    @if (canLanguages('activate') && ! $lang->is_default)
                                                        <form action="{{ route('languages.toggle-status', $lang->id) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="feather icon-power"></i>
                                                                {{ $lang->is_active ? __('languages.deactivate') : __('languages.activate') }}
                                                            </button>
                                                        </form>
                                                    @endif

                                                    @if (canLanguages('delete') && ! $lang->is_default)
                                                        <div class="dropdown-divider"></div>
                                                        <form action="{{ route('languages.destroy', $lang->id) }}" method="POST"
                                                            onsubmit="return confirm({{ Illuminate\Support\Js::from(__('languages.confirm_delete')) }});">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="feather icon-trash-2"></i> {{ __('languages.delete') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="st-empty">
                                            <i class="feather icon-globe"></i>
                                            {{ __('languages.empty') }}
                                            @if (canLanguages('create'))
                                                <div style="margin-top:.7rem">
                                                    <a href="{{ route('languages.create') }}" class="btn btn-primary btn-sm">
                                                        <i class="feather icon-plus"></i> {{ __('languages.create_first') }}
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($system_languages->hasPages())
                <div class="foot p-0">
                    @include('sitting::components.table-foot', ['paginator' => $system_languages])
                </div>
            @endif
        </div>
    </div>
@endsection
