@extends('sitting::master')

@section('title', __('settings.logs.activity_title'))

@php
    $breadcrumb = [
        ['title' => __('settings.logs.activity_title'), 'url' => '#']
    ];
@endphp

@section('content')
<link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-activity"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.logs.activity_heading') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.logs.activity_heading') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <div class="btn-group">
                <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#cleanupModal">
                    <i class="feather icon-trash"></i> {{ __('settings.logs.cleanup') }}
                </button>
            </div>

        </div>
    </div>

    <div class="content-body email-logs-page">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon">
                    <i class="feather icon-activity"></i>
                </div>
                <div class="stat-label">{{ __('settings.logs.activity_total') }}</div>
                <div class="stat-value">{{ number_format($stats['total'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-list"></i>
                    <span>{{ __('settings.logs.all_records') }}</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-clock"></i>
                </div>
                <div class="stat-label">{{ __('settings.logs.activity_today') }}</div>
                <div class="stat-value">{{ number_format($stats['today'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>{{ __('settings.logs.activity_24h') }}</span>
                </div>
            </div>
        </div>

        <!-- قسم الفلاتر -->
        <div class="card filter-card no-print">
            <div class="card-header">
                <h4 class="card-title"><i class="fa fa-filter"></i> {{ __('settings.logs.filters') }}</h4>
                <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('ActivityLog.index') }}" id="filterForm">
                        <div class="row">
                            <!-- البحث -->
                            <div class="col-md-7 col-sm-12 mb-1">
                                <label>{{ __('settings.logs.search') }}</label>
                                <input type="text" name="search" class="form-control"
                                       placeholder="{{ __('settings.logs.activity_search_placeholder') }}"
                                       value="{{ request('search') }}">
                            </div>

                            <!-- من تاريخ -->
                            <div class="col-md-2 col-sm-6 mb-1">
                                <label>{{ __('settings.logs.date_from') }}</label>
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>

                            <!-- إلى تاريخ -->
                            <div class="col-md-2 col-sm-6 mb-1">
                                <label>{{ __('settings.logs.date_to') }}</label>
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>

                            <!-- أزرار البحث -->
                            <div class="col-md-1 col-sm-12 mb-1">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-search"></i>
                                    </button>
                                    <a href="{{ route('ActivityLog.index') }}" class="btn btn-secondary btn-block mt-1">
                                        <i class="fa fa-refresh"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> {{ __('settings.logs.activity_list') }}</h4>
                <div>
                    <button type="button" id="bulk-delete-btn" disabled class="btn btn-sm btn-danger mr-1"
                            onclick="$('#bulk-form').submit();">
                        {{ __('settings.logs.bulk_delete') }}
                    </button>
                </div>
            </div>
            <div class="card-content">
                <form id="bulk-form" action="{{ route('ActivityLog.bulk-delete') }}" method="POST">
                    @csrf
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px;">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="select-all">
                                            <label class="custom-control-label" for="select-all"></label>
                                        </div>
                                    </th>
                                    <th class="text-center" style="width: 60px;">#</th>
                                    <th>{{ __('settings.logs.user') }}</th>
                                    <th>{{ __('settings.logs.branch') }}</th>
                                    <th>{{ __('settings.logs.description') }}</th>
                                    <th class="text-center">{{ __('settings.logs.datetime') }}</th>
                                    <th class="text-center" style="width: 80px;">{{ __('settings.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $index => $log)
                                    <tr>
                                        <td class="text-center">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input log-checkbox"
                                                       name="ids[]" value="{{ $log->id }}"
                                                       id="log-{{ $log->id }}">
                                                <label class="custom-control-label" for="log-{{ $log->id }}"></label>
                                            </div>
                                        </td>
                                        <td class="text-center">{{ $log->id }}</td>
                                        <td>
                                            <div>
                                                <strong>{{ $log->user ? $log->user->name : __('settings.logs.system_or_visitor') }}</strong>
                                                @if($log->user && $log->user->email)
                                                    <br><small class="text-muted">{{ $log->user->email }}</small>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-secondary">{{ ($log->user && $log->user->branch) ? $log->user->branch->name : __('settings.logs.main_branch') }}</span>
                                        </td>
                                        <td>{{ Str::limit(strip_tags($log->description), 60) }}</td>
                                        <td class="text-center">
                                            <div>{{ $log->created_at->format('Y-m-d') }}</div>
                                            <small class="text-muted">{{ $log->created_at->format('H:i:s') }}</small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <div class="dropdown">
                                                    <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                        type="button" data-toggle="dropdown" aria-expanded="false">
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item" href="{{ route('ActivityLog.show', $log->id) }}">
                                                            <i class="feather icon-eye"></i> {{ __('settings.logs.view') }}
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        {{-- زرٌّ لا نموذج: نموذجٌ داخل نموذج الحذف الجماعي يُسقطه المتصفّح،
                                                             فكان «حذف» الصفّ الأوّل يرسل النموذجَ الجماعيّ ويفشل --}}
                                                        <button type="button" class="dropdown-item text-danger js-row-delete" data-url="{{ route('ActivityLog.destroy', $log->id) }}">
                                                                <i class="feather icon-trash-2"></i> {{ __('settings.logs.delete') }}
                                                            </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="feather icon-activity" style="font-size: 48px; color: #ccc;"></i>
                                            <p class="mt-2 text-muted">{{ __('settings.logs.no_records') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                {{-- حذفُ صفٍّ واحد: نموذجٌ مستقلّ يملأ عنوانَه زرُّ الصفّ --}}
                <form id="row-delete-form" method="POST" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>

                {{-- Pagination --}}
                @include('sitting::components.table-foot', ['paginator' => $logs])
            </div>
        </div>
    </div>

    {{-- نافذة التنظيف --}}
    <div class="modal fade" id="cleanupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('settings.logs.cleanup_title') }}</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form action="{{ route('ActivityLog.cleanup') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>{{ __('settings.logs.cleanup_days_label') }}</label>
                        </div>
                        <div class="alert alert-warning">
                            <i class="feather icon-alert-triangle"></i>
                            {{ __('settings.logs.cleanup_irreversible') }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('settings.common.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('settings.logs.cleanup') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // تحديد الكل
    $('#select-all').on('change', function() {
        $('.log-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkButton();
    });

    // تحديث زر الحذف الجماعي
    $('.log-checkbox').on('change', updateBulkButton);

    function updateBulkButton() {
        const checked = $('.log-checkbox:checked').length;
        $('#bulk-delete-btn').prop('disabled', checked === 0);
        $('#bulk-delete-btn').text(checked > 0 ? `{{ __('settings.logs.bulk_delete') }} (${checked})` : '{{ __('settings.logs.bulk_delete') }}');
    }

    // تأكيد الحذف
    $(document).on('click', '.js-row-delete', function(e) {
        e.preventDefault();
        const form = $('#row-delete-form').attr('action', $(this).data('url'));

        Swal.fire({
            title: @json(__('settings.logs.sure')),
            text: @json(__('settings.logs.delete_one')),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: @json(__('settings.logs.confirm_delete')),
            cancelButtonText: @json(__('settings.common.cancel')),
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form[0].submit();
            }
        });
    });

    // تأكيد الحذف الجماعي
    $('#bulk-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const count = $('.log-checkbox:checked').length;

        Swal.fire({
            title: @json(__('settings.logs.sure')),
            text: @json(__('settings.logs.delete_many')).replace(':count', count),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: @json(__('settings.logs.confirm_delete')),
            cancelButtonText: @json(__('settings.common.cancel')),
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.off('submit').submit();
            }
        });
    });
});
</script>
@endsection
