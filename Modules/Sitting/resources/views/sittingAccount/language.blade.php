@extends('sitting::master')

@section('title', __('settings.language.title'))

@php
    $breadcrumb = [
        ['title' => __('settings.language.title'), 'url' => '#']
    ];
@endphp

@section('css')
<style>
    /* تحسينات الجدول الاحترافية */
    .table-custom tbody tr {
        transition: all 0.2s ease-in-out;
    }
    .table-custom tbody tr:hover {
        background-color: #f8f8f8;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.04);
    }
    .table-custom td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #ebe9f1;
    }
</style>
@endsection

@section('content')    <div class="content-header row">
<!-- ... (الترويسة كما هي) ... -->
    <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">{{ __('settings.language.manage') }}</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="">{{ __('settings.common.home') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('settings.currency.view') }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <!-- صندوق الإرشادات -->
        <div class="alert alert-info mb-2" role="alert">
            <h5 class="alert-heading"><i class="fa fa-info-circle me-2"></i> {{ __('settings.language.guide') }}</h5>
            <ul class="mb-0">
                <li>{{ __('settings.language.guide_1') }}</li>
                <li>{{ __('settings.language.guide_2') }}</li>
                <li>{{ __('settings.language.guide_3') }}</li>
                <li>{{ __('settings.language.guide_4') }}</li>
            </ul>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex flex-wrap justify-content-between">
                        <h4 class="mb-0">{{ __('settings.language.list') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center p-2">
                <div class="d-flex gap-2">
                    <span class="hide-button-text">{{ __('settings.common.search_filter') }}</span>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <button class="btn btn-outline-primary btn-sm waves-effect waves-light" data-toggle="collapse" data-target="#advancedSearchForm">
                        <i class="fa fa-filter me-1"></i>
                        <span class="button-text">{{ __('settings.common.advanced') }}</span>
                    </button>
                    <a href="{{ route('SittingAccount.language') }}" class="btn btn-outline-primary btn-sm waves-effect waves-light">
                        <i class="fa fa-refresh me-1"></i>
                        {{ __('settings.common.reset_filter') }}
                    </a>
                </div>
            </div>

            <div class="card-body">
                <form class="form" method="GET" action="{{ route('SittingAccount.language') }}" id="searchForm">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="{{ __('settings.language.search_placeholder') }}" value="{{ request('search') }}">
                        </div>

                        <div class="col-md-4">
                            <select name="status" class="form-control select2">
                                <option value="">{{ __('settings.common.all_statuses') }}</option>
                                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('settings.common.is_active') }}</option>
                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('settings.common.is_inactive') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="collapse mt-2 {{ request('direction') ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <select name="direction" class="form-control select2">
                                    <option value="">{{ __('settings.language.direction_all') }}</option>
                                    <option value="rtl" {{ request('direction') === 'rtl' ? 'selected' : '' }}>{{ __('settings.language.rtl') }}</option>
                                    <option value="ltr" {{ request('direction') === 'ltr' ? 'selected' : '' }}>{{ __('settings.language.ltr') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-2">
                        <button type="submit" class="btn btn-primary">{{ __('settings.common.search') }}</button>
                        <a href="{{ route('SittingAccount.language') }}" class="btn btn-outline-warning">{{ __('settings.common.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                @if ($system_languages->count() > 0)
                    <div class="table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="fa fa-language me-1 text-primary"></i> {{ __('settings.language.language') }}</th>
                                    <th><i class="fa fa-code me-1 text-info"></i> {{ __('settings.currency.code') }}</th>
                                    <th><i class="fa fa-align-right me-1 text-warning"></i> {{ __('settings.language.direction') }}</th>
                                    <th><i class="fa fa-toggle-on me-1 text-success"></i> {{ __('settings.common.status') }}</th>
                                    <th><i class="fa fa-star me-1 text-danger"></i> {{ __('settings.language.is_default') }}</th>
                                    <th style="width: 15%" class="text-center"><i class="fa fa-cogs me-1 text-secondary"></i> {{ __('settings.common.options') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($system_languages as $lang)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @if($lang->flag)
                                                    <img src="{{ asset('flags/'.$lang->flag) }}" alt="flag" style="width: 35px; height: 25px; margin-left: 12px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                @else
                                                    <div class="mr-2 ml-2 shadow-sm" style="width: 35px; height: 25px; background: #f3f2f7; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; color: #7367f0;">
                                                        {{ strtoupper(substr($lang->code, 0, 2)) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <div style="font-size: 1.05rem; font-weight: 600; color: #5e5873;">{{ $lang->name }}</div>
                                                    <div style="font-size: 0.85rem; color: #b9b9c3;">{{ $lang->native_name ?? '-' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge badge-light-primary px-2 py-1">{{ strtoupper($lang->code) }}</span></td>
                                        <td>
                                            @if($lang->direction == 'rtl')
                                                <span class="badge badge-light-info"><i class="fa fa-align-right me-1"></i> RTL</span>
                                            @else
                                                <span class="badge badge-light-secondary"><i class="fa fa-align-left me-1"></i> LTR</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($lang->is_active)
                                                <span class="badge badge-light-success"><i class="fa fa-check-circle me-1"></i> {{ __('settings.common.is_active') }}</span>
                                            @else
                                                <span class="badge badge-light-secondary"><i class="fa fa-times-circle me-1"></i> {{ __('settings.common.is_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($lang->is_default)
                                                <span class="badge bg-warning text-white" data-toggle="tooltip" title="{{ __('settings.language.default_title') }}"><i class="fa fa-star me-1"></i> {{ __('settings.language.default_badge') }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <div class="dropdown">
                                                    <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                        type="button" data-toggle="dropdown" aria-expanded="false">
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right dropdown-menu-start">

                                                        <!-- Set Default -->
                                                        @if(!$lang->is_default)
                                                            <form action="{{ route('SittingAccount.language.store') }}" method="POST" style="display:none;" id="default-form-{{ $lang->id }}">
                                                                @csrf
                                                                <input type="hidden" name="language" value="{{ $lang->code }}">
                                                            </form>
                                                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('default-form-{{ $lang->id }}').submit();" data-toggle="tooltip" title="{{ __('settings.language.set_default_title') }}">
                                                                <i class="fa fa-star me-2 text-warning"></i>{{ __('settings.language.set_default') }}
                                                            </a>
                                                        @else
                                                            <span class="dropdown-item text-muted"><i class="fa fa-check me-2 text-muted"></i> {{ __('settings.language.is_default_now') }}</span>
                                                        @endif

                                                        <!-- Toggle Active -->
                                                        <form action="{{ route('SittingAccount.language.toggleActive') }}" method="POST" style="display:none;" id="toggle-active-form-{{ $lang->id }}">
                                                            @csrf
                                                            <input type="hidden" name="id" value="{{ $lang->id }}">
                                                        </form>
                                                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('toggle-active-form-{{ $lang->id }}').submit();" data-toggle="tooltip" title="{{ __('settings.common.toggle_state_title') }}">
                                                            @if($lang->is_active)
                                                                <i class="fa fa-times-circle me-2 text-secondary"></i>{{ __('settings.common.disable') }}
                                                            @else
                                                                <i class="fa fa-check-circle me-2 text-success"></i>{{ __('settings.common.enable') }}
                                                            @endif
                                                        </a>

                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="d-flex flex-column align-items-center justify-content-center p-5 text-center">
                        <div class="mb-3">
                            <i class="fa fa-language text-muted" style="font-size: 4rem; opacity: 0.5;"></i>
                        </div>
                        <h4 class="text-muted mb-2">{{ __('settings.common.no_data') }}</h4>
                    </div>
                @endif
            </div>

            @if ($system_languages->count() > 0)
                <div class="card-footer">
                    <div class="d-flex justify-content-center">
                        @include('sitting::components.table-foot', ['paginator' => $system_languages])
                    </div>
                </div>
            @endif
        </div>
    </div>

<script>
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
        
        // تحديد الكل
        $('#selectAll').off('change').on('change', function() {
            $('.invoice-checkbox').prop('checked', $(this).prop('checked'));
        });
    });
</script>
@endsection
