@extends('sitting::master')

@section('title', __('settings.backup.show_title'))

@section('content')
    @include('sitting::cron_jobs._styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-database"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.backup.backup_no', ['id' => $backup->id]) }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('Backup.index') }}">{{ __('settings.backup.title') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('Backup.history') }}">{{ __('settings.backup.history_crumb') }}</a></li>
                        <li class="breadcrumb-item active">{{ Str::limit($backup->file_name ?? '—', 30) }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            @permission('settings.backup.download')
                @if ($backup->isAvailable() && $fileExists)
                    <a href="{{ route('Backup.download', $backup->id) }}" class="btn btn-success">
                        <i class="feather icon-download"></i> {{ __('settings.backup.download') }}
                    </a>
                @endif
            @endpermission
            <a href="{{ route('Backup.history') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.common.back') }}
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

        {{-- الصفّ يقول «مكتملة» والملف غير موجود: أخطر تناقض في هذه الشاشة،
             لأنه يعني أن ما يبدو طريق عودة ليس موجوداً أصلاً --}}
        @if ($backup->isAvailable() && ! $fileExists)
            <div class="cj-health failed">
                <span class="dot"></span>
                <div>
                    <strong>{{ __('settings.backup.file_missing_title') }}</strong>
                    <div style="font-size:.85rem;margin-top:.25rem">
                        {!! __('settings.backup.file_missing_body', ['link' => '<a href="'.route('Backup.index').'" style="color:inherit;text-decoration:underline">'.__('settings.backup.backup_panel').'</a>']) !!}
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <!-- التفاصيل -->
            <div class="col-md-5 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ __('settings.backup.details') }}</h4>
                        <span class="badge badge-light-{{ $backup->statusColor() }}">{{ $backup->statusLabel() }}</span>
                    </div>
                    <div class="card-body">
                        <dl class="mb-0">
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.file_name') }}</dt>
                                <dd>{{ $backup->displayName() }}</dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.uuid') }}</dt>
                                <dd><span class="cj-mono" style="font-size:.75rem">{{ $backup->uuid }}</span></dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.common.type') }}</dt>
                                <dd>{{ $backup->typeLabel() }}</dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.table_scope') }}</dt>
                                <dd>
                                    {{ $backup->tableScopeLabel() }}
                                    @if ($backup->isPartial())
                                        <span class="badge badge-light-warning" style="font-size:.65rem">{{ __('settings.backup.partial_short') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.source') }}</dt>
                                <dd>{{ $backup->triggerLabel() }}</dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.size') }}</dt>
                                <dd>{{ $backup->humanSize() }}</dd>
                            </div>
                            {{-- حالة التعشية تخصّ هذه النسخة لا الإعداد الحالي:
                                 تغيير الإعداد لاحقاً لا يغيّر ملفاً كُتب --}}
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.encryption') }}</dt>
                                <dd>
                                    @if ($backup->is_encrypted)
                                        <span class="badge badge-light-success">
                                            <i class="feather icon-lock"></i> {{ __('settings.backup.encrypted') }}
                                        </span>
                                    @else
                                        <span class="badge badge-light-secondary">
                                            <i class="feather icon-unlock"></i> {{ __('settings.backup.not_encrypted') }}
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.duration') }}</dt>
                                <dd>{{ $backup->humanDuration() }}</dd>
                            </div>
                            {{-- مكان الحفظ باسمه المفهوم، والمسار الحقيقي ليس
                                 هنا: من يحتاجه يجده في سجلّ الخادم، ومن يقرأ
                                 هذه الشاشة يحتاج أن يعرف «أين» لا «كيف» --}}
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.disk') }}</dt>
                                <dd>
                                    @php $diskKey = 'settings.backup.disks.'.$backup->disk; @endphp
                                    {{ __($diskKey) === $diskKey ? $backup->disk : __($diskKey) }}
                                </dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.started') }}</dt>
                                <dd>{{ $backup->started_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.ended') }}</dt>
                                <dd>{{ $backup->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.created_by') }}</dt>
                                <dd>
                                    @if ($backup->creator)
                                        {{ $backup->creator->name }}
                                    @elseif ($backup->cronJob)
                                        <a href="{{ route('CronJobs.show', $backup->cronJob->id) }}">
                                            {{ $backup->cronJob->name }}
                                        </a>
                                    @else
                                        {{ __('settings.backup.system') }}
                                    @endif
                                </dd>
                            </div>
                            <div class="cj-def">
                                <dt>{{ __('settings.backup.lock_state') }}</dt>
                                <dd>
                                    <span class="badge badge-light-{{ $backup->is_locked ? 'warning' : 'secondary' }}">
                                        {{ $backup->is_locked ? __('settings.backup.locked') : __('settings.backup.unlocked') }}
                                    </span>
                                </dd>
                            </div>
                            @if ($backup->file_deleted_at)
                                <div class="cj-def">
                                    <dt>{{ __('settings.backup.file_deleted') }}</dt>
                                    <dd class="text-danger">{{ $backup->file_deleted_at->format('Y-m-d H:i') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    @permission('settings.backup.delete')
                        @if ($backup->isAvailable())
                            <div class="card-footer">
                                <form method="POST" action="{{ route('Backup.lock', $backup->id) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-{{ $backup->is_locked ? 'warning' : 'secondary' }}">
                                        <i class="feather icon-{{ $backup->is_locked ? 'unlock' : 'lock' }}"></i>
                                        {{ $backup->is_locked ? __('settings.backup.unlock') : __('settings.backup.lock_auto') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endpermission
                </div>
            </div>

            <div class="col-md-7 col-12">
                @php $sectionLabels = $backup->isPartial() ? $backup->sectionLabels() : []; @endphp
                @if ($sectionLabels !== [])
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">
                                {{ __('settings.backup.backed_up_sections', ['count' => count($sectionLabels)]) }}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div style="max-height:220px;overflow:auto">
                                @foreach ($sectionLabels as $sectionLabel)
                                    <span class="badge badge-light-primary" style="margin:.12rem;font-size:.78rem">{{ $sectionLabel }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @if ($backup->error_message)
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0 text-danger">{{ __('settings.backup.error_message') }}</h4>
                        </div>
                        <div class="card-body">
                            <pre class="cj-out err">{{ $backup->error_message }}</pre>
                        </div>
                    </div>
                @endif

                @if ($backup->output)
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.backup.output') }}</h4>
                        </div>
                        <div class="card-body">
                            <pre class="cj-out">{{ $backup->output }}</pre>
                        </div>
                    </div>
                @endif

                @if ($backup->restores->isNotEmpty())
                    <div class="card table-card-modern">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.backup.restores_from') }}</h4>
                        </div>
                        <div class="card-content">
                            <div class="table-responsive">
                                <table class="table modern-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('settings.common.date') }}</th>
                                            <th>{{ __('settings.backup.executor') }}</th>
                                            <th>{{ __('settings.backup.ip') }}</th>
                                            <th class="text-center">{{ __('settings.common.status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($backup->restores as $restore)
                                            <tr>
                                                <td>{{ $restore->started_at?->format('Y-m-d H:i') }}</td>
                                                <td>{{ $restore->executor?->name ?? '—' }}</td>
                                                <td><span class="cj-mono">{{ $restore->ip_address ?? '—' }}</span></td>
                                                <td class="text-center">
                                                    <span class="badge badge-light-{{ $restore->statusColor() }}">
                                                        {{ $restore->statusLabel() }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                @permission('settings.backup.restore')
                    @if ($backup->isRestorable() && $fileExists)
                        <div class="card cj-danger-zone" id="restore">
                            <div class="card-header">
                                <h4 class="card-title mb-0 text-danger">
                                    <i class="feather icon-alert-triangle"></i> {{ __('settings.backup.restore_db') }}
                                </h4>
                            </div>
                            <div class="card-body">
                                @if ($backup->isPartial())
                                    {{-- النسخة الجزئية أخطر من الكاملة عند الاسترجاع لا أقلّ:
                                         تكتب فوق جداولها وتترك الباقي على حاله، فتنتج قاعدة
                                         نصفها من تاريخ ونصفها من تاريخ آخر --}}
                                    <div class="alert mb-1"
                                        style="background:#fdecea;border:1px solid #f8c4be;color:#92211a;border-radius:8px">
                                        <strong>{{ __('settings.backup.partial_title', ['count' => count($backup->sectionLabels())]) }}</strong>
                                        {{ __('settings.backup.partial_body') }}
                                    </div>
                                @endif

                                <p class="mb-1">
                                    {{ __('settings.backup.overwrite_note', [
                                'scope' => $backup->isPartial() ? __('settings.backup.scope_sections') : __('settings.backup.scope_full'),
                                'date' => $backup->created_at?->format('Y-m-d H:i'),
                            ]) }}
                                </p>
                                <ul style="font-size:.88rem;color:#6b7280">
                                    <li>{!! __('settings.backup.restore_rule_safety') !!}</li>
                                    <li>{{ __('settings.backup.restore_rule_local') }}</li>
                                    <li>{{ __('settings.backup.restore_rule_audit') }}</li>
                                </ul>
                                <button type="button" class="btn btn-danger" data-toggle="modal"
                                    data-target="#restoreModal">
                                    <i class="feather icon-rotate-ccw"></i> {{ __('settings.backup.restore_from_this') }}
                                </button>
                            </div>
                        </div>
                    @endif
                @endpermission

                @permission('settings.backup.delete')
                    @if (! $backup->isRunning() && ! $backup->is_locked && ! $backup->file_deleted_at)
                        <div class="card cj-danger-zone">
                            <div class="card-header">
                                <h4 class="card-title mb-0 text-danger">{{ __('settings.backup.delete_backup') }}</h4>
                            </div>
                            <div class="card-body">
                                <p class="cj-sub mb-1">
                                    {{ __('settings.backup.delete_backup_hint') }}
                                </p>
                                <form method="POST" action="{{ route('Backup.destroy', $backup->id) }}"
                                    onsubmit="return confirm('{{ __('settings.backup.confirm_delete_file', ['file' => $backup->displayName()]) }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger">
                                        <i class="feather icon-trash-2"></i> {{ __('settings.backup.delete_file') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                @endpermission
            </div>
        </div>
    </div>

    @permission('settings.backup.restore')
        @if ($backup->isRestorable() && $fileExists)
            <div class="modal fade" id="restoreModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <form method="POST" action="{{ route('Backup.restore', $backup->id) }}" id="restoreForm">
                        @csrf
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title text-white">{{ __('settings.backup.confirm_restore_title') }}</h5>
                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="alert mb-1"
                                    style="background:#fdecea;border:1px solid #f8c4be;color:#92211a;border-radius:8px">
                                    <strong>{{ __('settings.backup.irreversible') }}</strong>
                                    @if ($backup->isPartial())
                                        {{ __('settings.backup.will_replace_partial', ['count' => count($backup->sectionLabels()), 'date' => $backup->created_at?->format('Y-m-d H:i')]) }}
                                    @else
                                        {{ __('settings.backup.will_replace_full', ['date' => $backup->created_at?->format('Y-m-d H:i')]) }}
                                    @endif
                                </div>

                                <div class="form-group mb-0">
                                    <label>{!! __('settings.backup.type_word', ['word' => __('settings.backup.confirm_word')]) !!}</label>
                                    <input type="text" name="confirm" class="form-control" autocomplete="off"
                                        placeholder="{{ __('settings.backup.confirm_word') }}" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('settings.common.cancel') }}</button>
                                <button type="submit" class="btn btn-danger" id="restoreSubmit">
                                    <i class="feather icon-rotate-ccw"></i> {{ __('settings.backup.run_restore') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endpermission
@endsection

@section('scripts')
    <script>
        // الاسترجاع قد يستغرق دقائق؛ ضغطة ثانية تعني عمليتين متوازيتين على
        // قاعدة البيانات نفسها
        document.getElementById('restoreForm')?.addEventListener('submit', function () {
            var button = document.getElementById('restoreSubmit');
            button.disabled = true;
            button.innerHTML = '<i class="feather icon-loader"></i> ' + @json(__('settings.backup.restoring'));
        });
    </script>
@endsection
