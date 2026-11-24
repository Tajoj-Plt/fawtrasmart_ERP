@extends('sitting::master')

@section('title', __('settings.doc_templates.page_title'))

@section('content')
<link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-file-text"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.doc_templates.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.doc_templates.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <div class="btn-group">
                <a href="{{ route('document-templates.create') }}" class="btn btn-primary shadow-sm">
                    <i class="feather icon-plus"></i> {{ __('settings.doc_templates.new') }}
                </a>
            </div>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- Statistics Cards -->
        <div class="stats-row mb-2">
            <div class="stat-card-modern primary">
                <div class="stat-icon">
                    <i class="feather icon-file-text"></i>
                </div>
                <div class="stat-label">{{ __('settings.doc_templates.total') }}</div>
                <div class="stat-value">{{ $templates->total() }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-list"></i>
                    <span>{{ __('settings.doc_templates.all_sizes') }}</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">{{ __('settings.doc_templates.active_count') }}</div>
                <div class="stat-value">{{ $templates->where('is_active', true)->count() }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>{{ __('settings.doc_templates.printable') }}</span>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card filter-card no-print">
            <div class="card-header">
                <h4 class="card-title"><i class="fa fa-filter"></i> {{ __('settings.doc_templates.filters') }}</h4>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('document-templates.index') }}">
                        @if (! empty($group))
                            {{-- `?group=hr`: قادمٌ من إعدادات الرواتب — تبقى القائمة على مستنداتها --}}
                            <input type="hidden" name="group" value="{{ $group }}">
                            <div class="alert alert-info d-flex justify-content-between align-items-center py-1 mb-1">
                                <span><i class="feather icon-filter"></i> {{ $group === 'hr' ? 'قوالب مستندات الموارد البشرية والرواتب' : $group }}</span>
                                <a href="{{ route('document-templates.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('settings.doc_templates.all_documents') }}</a>
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-4 col-12 mb-1">
                                <label>{{ __('settings.doc_templates.document_type') }}</label>
                                <select name="document_type" class="form-control">
                                    <option value="">{{ __('settings.doc_templates.all_documents') }}</option>
                                    @foreach(($filterTypes ?? $documentTypes) as $key => $name)
                                        <option value="{{ $key }}" {{ request('document_type') == $key ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 col-12 mb-1">
                                <label>{{ __('settings.doc_templates.paper_size') }}</label>
                                <select name="paper_size" class="form-control">
                                    <option value="">{{ __('settings.doc_templates.all_paper_sizes') }}</option>
                                    @foreach($paperSizes as $key => $name)
                                        <option value="{{ $key }}" {{ request('paper_size') == $key ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 col-12 mb-1">
                                <label>{{ __('settings.common.status') }}</label>
                                <select name="is_active" class="form-control">
                                    <option value="">{{ __('settings.doc_templates.all_statuses') }}</option>
                                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('settings.doc_templates.active') }}</option>
                                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('settings.doc_templates.inactive') }}</option>
                                </select>
                            </div>

                            <div class="col-md-2 col-12 mb-1">
                                <label>&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-search"></i>
                                    </button>
                                    <a href="{{ route('document-templates.index', ! empty($group) ? ['group' => $group] : []) }}" class="btn btn-secondary btn-block mt-0 ml-1">
                                        <i class="fa fa-refresh"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> {{ __('settings.doc_templates.list') }}</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 60px;">#</th>
                                <th>{{ __('settings.doc_templates.name') }}</th>
                                <th>{{ __('settings.doc_templates.document_type') }}</th>
                                <th>{{ __('settings.doc_templates.print_size') }}</th>
                                <th class="text-center">{{ __('settings.doc_templates.is_default') }}</th>
                                <th class="text-center">{{ __('settings.common.status') }}</th>
                                <th class="text-center">{{ __('settings.doc_templates.updated_at') }}</th>
                                <th class="text-center" style="width: 150px;">{{ __('settings.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $tmpl)
                                <tr>
                                    <td class="text-center">{{ $tmpl->id }}</td>
                                    <td><strong>{{ $tmpl->name }}</strong></td>
                                    <td>
                                        <span class="badge badge-light-primary">
                                            {{ $documentTypes[$tmpl->document_type] ?? $tmpl->document_type }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-secondary">
                                            {{ $paperSizes[$tmpl->paper_size] ?? $tmpl->paper_size }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if($tmpl->is_default)
                                            <span class="badge badge-success"><i class="feather icon-check"></i> {{ __('settings.doc_templates.default_badge') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $tmpl->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                            {{ $tmpl->is_active ? __('settings.doc_templates.active') : __('settings.doc_templates.inactive') }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <small>{{ $tmpl->updated_at->format('Y-m-d H:i') }}</small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <div class="dropdown">
                                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                    type="button" data-toggle="dropdown" aria-expanded="false">
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="{{ route('document-templates.preview', $tmpl->id) }}" target="_blank">
                                                        <i class="feather icon-eye"></i> {{ __('settings.doc_templates.preview_template') }}
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('document-templates.edit', $tmpl->id) }}">
                                                        <i class="feather icon-edit"></i> {{ __('settings.doc_templates.edit_design') }}
                                                    </a>
                                                    <form action="{{ route('document-templates.duplicate', $tmpl->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="feather icon-copy"></i> {{ __('settings.doc_templates.duplicate') }}
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('document-templates.toggle-active', $tmpl->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="feather icon-power"></i> {{ $tmpl->is_active ? __('settings.doc_templates.disable_template') : __('settings.doc_templates.enable_template') }}
                                                        </button>
                                                    </form>
                                                    @if(!$tmpl->is_default)
                                                        <form action="{{ route('document-templates.set-default', $tmpl->id) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="feather icon-check-circle"></i> {{ __('settings.doc_templates.set_default') }}
                                                            </button>
                                                        </form>
                                                        <div class="dropdown-divider"></div>
                                                        <form action="{{ route('document-templates.destroy', $tmpl->id) }}" method="POST" onsubmit="return confirm('{{ __('settings.doc_templates.confirm_delete') }}');">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="feather icon-trash-2 text-danger"></i> {{ __('settings.doc_templates.delete') }}
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
                                    <td colspan="8" class="text-center py-4">
                                        <i class="feather icon-file-text" style="font-size: 48px; color: #ccc;"></i>
                                        <p class="mt-2 text-muted">{{ __('settings.doc_templates.no_match') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            @include('sitting::components.table-foot', ['paginator' => $templates])
        </div>
    </div>
@endsection
