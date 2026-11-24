@extends('branches::layouts.master')

@section('title', __('branches.migrate.title'))

@section('content')
    <style>
        .mrg-hero {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 24px rgba(238, 9, 121, 0.25);
        }
        .mrg-hero h2 { color: #fff; font-weight: 700; margin: 0 0 0.4rem; }
        .mrg-hero p { color: rgba(255,255,255,.92); margin: 0; font-size: 0.95rem; line-height: 1.7; }
        .mrg-stats { display: flex; gap: 0.85rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .mrg-stat {
            flex: 1; min-width: 160px;
            background: #fff; border: 1px solid #e8eaf0; border-radius: 12px;
            padding: 1rem 1.2rem; box-shadow: 0 2px 8px rgba(34,41,47,.04);
        }
        .mrg-stat-label { font-size: 0.78rem; color: #6c7591; margin-bottom: 0.25rem; }
        .mrg-stat-value { font-size: 1.6rem; font-weight: 700; color: #2c3142; line-height: 1.1; }
        .mrg-stat .feather { font-size: 1.1rem; vertical-align: -2px; margin-inline-end: 4px; }
        .mrg-stat.is-warn .mrg-stat-value { color: #ff9f43; }
        .mrg-stat.is-info .mrg-stat-value { color: #00cfe8; }
        .mrg-stat.is-success .mrg-stat-value { color: #28c76f; }
        .mrg-stat.is-danger .mrg-stat-value { color: #ea5455; }
        .mrg-action {
            border: 2px solid #ea5455;
            background: linear-gradient(135deg, #fff5f5 0%, #fff 60%);
            border-radius: 12px;
            padding: 1.5rem 1.75rem;
        }
        .mrg-action h5 { color: #ea5455; font-weight: 700; }
        .mrg-action ul { padding-inline-start: 1.25rem; color: #495057; line-height: 1.9; }
        .mrg-action ul li code { background: #fff3f3; color: #c0392b; padding: 0 0.3rem; border-radius: 4px; }
        .mrg-go-btn { padding: 0.95rem 2rem; font-size: 1.05rem; font-weight: 700; border-radius: 10px; }
        .mrg-go-btn .feather { vertical-align: -3px; }
        .mrg-toggle-row {
            display: flex; gap: 0.85rem; flex-wrap: wrap;
            background: #fffbe6; border: 1px dashed #ffc107; border-radius: 10px;
            padding: 0.85rem 1.1rem; margin-top: 1rem;
        }
        .mrg-toggle-row .custom-control-label { font-weight: 600; color: #6f5300; }
        .mrg-toggle-row small { color: #856404; font-size: 0.78rem; display: block; }
    </style>

    {{-- كانت الترويسة لوحةً خاصّة بهذه الشاشة (`mrg-hero`) وفتاتُ التنقّل
         تحتها منفصلاً — بينما بقيّة الوحدة ترويسةٌ واحدة. --}}
    <x-branches::page-head
        icon="zap"
        :title="__('branches.migrate.hero_title')"
        :subtitle="__('branches.migrate.hero_text')"
        :crumbs="[__('branches.tree.breadcrumb') => route('branches.index'), __('branches.migrate.breadcrumb') => null]" />

    <div class="content-body">
        <div class="container-fluid">
            @include('layouts.alerts.error')
            @include('layouts.alerts.success')

            @if (!$hasTable)
                <div class="alert alert-success">
                    <h5 class="mb-1"><i class="feather icon-check-circle"></i> {{ __('branches.migrate.no_table') }}</h5>
                    <p class="mb-0">{{ __('branches.migrate.no_table_hint') }}</p>
                </div>
            @elseif ($rowCount === 0)
                <div class="alert alert-warning">
                    <h5 class="mb-1"><i class="feather icon-alert-triangle"></i> {{ __('branches.migrate.empty_table') }}</h5>
                    <p class="mb-0">{{ __('branches.migrate.empty_table_hint') }}</p>
                </div>
            @else
                <div class="mrg-stats">
                    <div class="mrg-stat is-warn">
                        <div class="mrg-stat-label"><i class="feather icon-folder"></i> {{ __('branches.migrate.rows_label') }}</div>
                        <div class="mrg-stat-value">{{ number_format($rowCount) }}</div>
                    </div>
                    <div class="mrg-stat is-success">
                        <div class="mrg-stat-label"><i class="feather icon-check"></i> {{ __('branches.migrate.will_migrate') }}</div>
                        <div class="mrg-stat-value">{{ number_format($rowsWithParent) }}</div>
                    </div>
                    @if ($rowsWithoutParent > 0)
                        <div class="mrg-stat is-danger">
                            <div class="mrg-stat-label"><i class="feather icon-x-circle"></i> {{ __('branches.migrate.will_skip') }}</div>
                            <div class="mrg-stat-value">{{ number_format($rowsWithoutParent) }}</div>
                        </div>
                    @endif
                    <div class="mrg-stat is-info">
                        <div class="mrg-stat-label"><i class="feather icon-users"></i> {{ __('branches.migrate.clients_to_link') }}</div>
                        <div class="mrg-stat-value">{{ number_format($clientsLinked) }}</div>
                    </div>
                    <div class="mrg-stat is-info">
                        <div class="mrg-stat-label"><i class="feather icon-map-pin"></i> {{ __('branches.migrate.hoods_to_link') }}</div>
                        <div class="mrg-stat-value">{{ number_format($neighborhoodsLinked) }}</div>
                    </div>
                </div>

                <div class="mrg-action">
                    <h5><i class="feather icon-alert-octagon"></i> {{ __('branches.migrate.on_click') }}</h5>
                    <ul class="mb-3">
                        <li>{{ __('branches.migrate.step_1') }} {{ __('branches.migrate.step_2') }} {{ __('branches.migrate.step_3') }} {{ __('branches.migrate.step_4') }}</li>
                    </ul>

                    <form method="POST" action="{{ route('branches.migrate_region_groubs_submit') }}" id="mrg-form">
                        @csrf

                        <div class="mrg-toggle-row">
                            <label class="stk-switch flex-grow-1 mb-0">
                                <input type="checkbox" id="dry_run" name="dry_run" value="1">
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">
                                        <i class="feather icon-eye"></i> {{ __('branches.migrate.dry_run') }}
                                    </span>
                                    <span class="stk-switch__hint">{{ __('branches.migrate.dry_run_hint') }}</span>
                                </span>
                            </label>
                            <label class="stk-switch flex-grow-1 mb-0">
                                <input type="checkbox" id="keep_table" name="keep_table" value="1">
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">
                                        <i class="feather icon-database"></i> {{ __('branches.migrate.keep_table') }}
                                    </span>
                                    <span class="stk-switch__hint">{{ __('branches.migrate.keep_table_hint') }}</span>
                                </span>
                            </label>
                        </div>

                        <div class="d-flex gap-2 mt-3 flex-wrap" style="gap: 0.5rem;">
                            <button type="submit" class="btn btn-danger mrg-go-btn" id="mrg-go">
                                <i class="feather icon-zap"></i>
                                <span id="mrg-btn-label">{{ __('branches.migrate.run_now') }}</span>
                            </button>
                            @can('branches.view')
                                <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary mrg-go-btn">
                                    <i class="feather icon-arrow-right"></i> {{ __('branches.migrate.back') }}
                                </a>
                            @endcan
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            'use strict';
            var form = document.getElementById('mrg-form');
            if (!form) return;
            var dryRun = document.getElementById('dry_run');
            var btnLabel = document.getElementById('mrg-btn-label');
            var goBtn = document.getElementById('mrg-go');

            function syncLabel() {
                if (!dryRun || !btnLabel || !goBtn) return;
                if (dryRun.checked) {
                    btnLabel.textContent = '{{ __('branches.migrate.run_dry') }}';
                    goBtn.classList.remove('btn-danger');
                    goBtn.classList.add('btn-warning');
                } else {
                    btnLabel.textContent = '{{ __('branches.migrate.run_now') }}';
                    goBtn.classList.remove('btn-warning');
                    goBtn.classList.add('btn-danger');
                }
            }
            if (dryRun) dryRun.addEventListener('change', syncLabel);
            syncLabel();

            form.addEventListener('submit', function (e) {
                if (dryRun && dryRun.checked) return; // dry run = no confirm
                var ok = window.confirm(
                    @json(__('branches.migrate.confirm_text'))
                );
                if (!ok) {
                    e.preventDefault();
                    return;
                }
                if (goBtn) {
                    goBtn.disabled = true;
                    if (btnLabel) btnLabel.textContent = '' + @json(__('branches.migrate.running')) + '';
                }
            });
        })();
    </script>
@endsection
