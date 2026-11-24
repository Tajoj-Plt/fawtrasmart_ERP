@extends('sitting::master')

@section('title', __('settings.db_size.title'))

@section('content')
    @include('sitting::components._settings_styles')

    @php
        $human = fn ($bytes) => \App\Services\Database\DatabaseSizeInspector::humanBytes((int) $bytes);
        // الشريط يُقاس على أكبر جدول لا على مجموع القاعدة: النسبة إلى
        // المجموع تجعل كل الأشرطة خيطاً رفيعاً لا يُقارَن بينها
        $largest = $summary['largest']['total_bytes'] ?? 0;
    @endphp

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-hard-drive"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.db_size.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.db_size.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        {{-- ── الإجماليات ── --}}
        <div class="row">
            @foreach ([
                ['label' => __('settings.db_size.stat_total'), 'value' => $human($summary['total_bytes']), 'icon' => 'database', 'color' => '#2b6cb0'],
                ['label' => __('settings.db_size.stat_data'), 'value' => $human($summary['data_bytes']), 'icon' => 'file-text', 'color' => '#2f855a'],
                ['label' => __('settings.db_size.stat_index'), 'value' => $human($summary['index_bytes']), 'icon' => 'list', 'color' => '#b7791f'],
                ['label' => __('settings.db_size.stat_free'), 'value' => $human($summary['free_bytes']), 'icon' => 'trash-2', 'color' => '#c53030'],
                ['label' => __('settings.db_size.stat_tables'), 'value' => number_format($summary['tables']), 'icon' => 'grid', 'color' => '#553c9a'],
                ['label' => __('settings.db_size.stat_rows'), 'value' => number_format($summary['rows']), 'icon' => 'hash', 'color' => '#2c7a7b'],
            ] as $stat)
                <div class="col-lg-2 col-md-4 col-6 mb-1">
                    <div class="st-card h-100">
                        <div class="body text-center" style="padding:1rem .5rem">
                            <i class="feather icon-{{ $stat['icon'] }}" style="color:{{ $stat['color'] }};font-size:1.3rem"></i>
                            <div style="font-size:1.05rem;font-weight:700;color:#1b2430;margin-top:.35rem;direction:ltr">
                                {{ $stat['value'] }}
                            </div>
                            <div style="font-size:.76rem;color:#8a93a0">{{ $stat['label'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($summary['free_bytes'] > 0)
            <div class="alert alert-info" style="border-radius:10px">
                <i class="feather icon-info"></i>
                {{ __('settings.db_size.free_note', ['size' => $human($summary['free_bytes'])]) }}
            </div>
        @endif

        {{-- ── الفلاتر ── --}}
        <div class="st-card mb-1">
            <div class="body">
                <form method="GET" action="{{ route('DatabaseSizes.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-6 col-12 mb-1">
                            <label>{{ __('settings.db_size.search') }}</label>
                            <input type="text" name="search" class="form-control" value="{{ $search }}"
                                placeholder="{{ __('settings.db_size.search_placeholder') }}">
                        </div>
                        <div class="col-md-4 col-9 mb-1">
                            <label>{{ __('settings.db_size.sort') }}</label>
                            <select name="sort" class="form-control">
                                @foreach (['size', 'rows', 'free', 'name'] as $option)
                                    <option value="{{ $option }}" {{ $sort === $option ? 'selected' : '' }}>
                                        {{ __('settings.db_size.sort_' . $option) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-3 mb-1">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="feather icon-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── الجدول ── --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-database" style="color:#2b6cb0"></i>
                <h4>{{ __('settings.db_size.list') }}</h4>
                <span class="count">{{ number_format($matched) }}</span>
            </div>
            <div class="body flush">
                <div class="table-responsive">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th style="width:30px" class="text-center">#</th>
                                <th>{{ __('settings.db_size.table') }}</th>
                                <th class="text-center" style="width:110px">{{ __('settings.db_size.total') }}</th>
                                <th style="width:22%">{{ __('settings.db_size.share') }}</th>
                                <th class="text-center" style="width:95px">{{ __('settings.db_size.data') }}</th>
                                <th class="text-center" style="width:95px">{{ __('settings.db_size.index') }}</th>
                                <th class="text-center" style="width:95px">{{ __('settings.db_size.free') }}</th>
                                <th class="text-center" style="width:95px">{{ __('settings.db_size.rows') }}</th>
                                <th style="width:90px">{{ __('settings.db_size.engine') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tables as $i => $table)
                                @php($share = $largest > 0 ? round($table['total_bytes'] / $largest * 100) : 0)
                                <tr>
                                    <td class="text-center" style="color:#b7bec8;font-size:.8rem">{{ $i + 1 }}</td>
                                    <td><span class="st-mono">{{ $table['name'] }}</span></td>
                                    <td class="num text-center" style="font-weight:600;direction:ltr">
                                        {{ $human($table['total_bytes']) }}
                                    </td>
                                    <td>
                                        <div style="background:#eef1f5;border-radius:4px;height:8px;overflow:hidden">
                                            <div style="width:{{ max($share, $table['total_bytes'] > 0 ? 1 : 0) }}%;height:100%;
                                                background:{{ $share >= 50 ? '#c53030' : ($share >= 20 ? '#b7791f' : '#2b6cb0') }}"></div>
                                        </div>
                                    </td>
                                    <td class="num text-center" style="direction:ltr;font-size:.83rem">{{ $human($table['data_bytes']) }}</td>
                                    <td class="num text-center" style="direction:ltr;font-size:.83rem">{{ $human($table['index_bytes']) }}</td>
                                    <td class="num text-center" style="direction:ltr;font-size:.83rem;color:{{ $table['free_bytes'] > 0 ? '#c53030' : '#b7bec8' }}">
                                        {{ $human($table['free_bytes']) }}
                                    </td>
                                    <td class="num text-center" style="direction:ltr;font-size:.83rem">{{ number_format($table['rows']) }}</td>
                                    <td style="font-size:.78rem;color:#8a93a0">{{ $table['engine'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="st-empty">
                                            <i class="feather icon-database"></i>
                                            {{ __('settings.common.no_data') }}
                                            <div style="font-size:.82rem">{{ __('settings.db_size.empty_hint') }}</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="foot">
                <small class="text-muted"><i class="feather icon-info"></i> {{ __('settings.db_size.rows_estimate_note') }}</small>
            </div>
        </div>
    </div>
@endsection
