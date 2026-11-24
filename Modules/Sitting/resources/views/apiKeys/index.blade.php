@extends('sitting::master')

@section('title', __('settings.api.title'))

@section('content')
    @include('sitting::components._settings_styles')

    <style>
        .ak-secret {
            direction: ltr; text-align: left; word-break: break-all;
            background: #1e1e2d; color: #9ae6b4; border-radius: 8px;
            padding: .7rem .9rem; font-family: Consolas, Monaco, monospace;
            font-size: .82rem; line-height: 1.7;
        }
        .ak-field { display: flex; gap: .5rem; align-items: center; margin-bottom: .6rem; }
        .ak-field label { flex: 0 0 90px; margin: 0; font-size: .82rem; color: #6b7280; font-weight: 600; }
        .ak-ability { display: flex; align-items: flex-start; gap: .5rem; padding: .35rem 0; margin: 0; cursor: pointer; }
        .ak-ability input { margin-top: .28rem; flex: 0 0 auto; }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-key"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.api.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.api.breadcrumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            @permission('api_keys.create')
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newKeyModal">
                    <i class="feather icon-plus"></i> {{ __('settings.api.new') }}
                </button>
            @endpermission

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="st-stats">
            <div class="st-stat">
                <div class="ic primary"><i class="feather icon-key"></i></div>
                <div>
                    <div class="val">{{ $stats['total'] }}</div>
                    <div class="lbl">{{ __('settings.api.total') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic success"><i class="feather icon-check-circle"></i></div>
                <div>
                    <div class="val">{{ $stats['active'] }}</div>
                    <div class="lbl">{{ __('settings.api.valid') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic warning"><i class="feather icon-clock"></i></div>
                <div>
                    <div class="val">{{ $stats['expired'] }}</div>
                    <div class="lbl">{{ __('settings.api.expired') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic info"><i class="feather icon-activity"></i></div>
                <div>
                    <div class="val">{{ number_format($stats['calls']) }}</div>
                    <div class="lbl">{{ __('settings.api.calls') }}</div>
                </div>
            </div>
        </div>

        <div class="st-note warn">
            <i class="feather icon-shield mt-1"></i>
            <div>
                <strong>{{ __('settings.api.warning_title') }}</strong>
                <p>{!! __('settings.api.warning_body') !!}</p>
            </div>
        </div>

        <div class="st-card">
            <div class="head">
                <i class="feather icon-key" style="color:#2b6cb0"></i>
                <h4>{{ __('settings.api.keys') }}</h4>
                <span class="count">{{ $apiKeys->count() }}</span>
            </div>
            <div class="body flush">
                <div class="table-responsive">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>{{ __('settings.common.name') }}</th>
                                <th>{{ __('settings.api.key') }}</th>
                                <th>{{ __('settings.api.abilities') }}</th>
                                <th>{{ __('settings.api.usage') }}</th>
                                <th>{{ __('settings.api.expiry') }}</th>
                                <th class="text-center">{{ __('settings.common.status') }}</th>
                                <th class="text-center" style="width:90px">{{ __('settings.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($apiKeys as $key)
                                @php
                                    $expired = $key->expires_at && $key->expires_at->isPast();
                                @endphp
                                <tr data-id="{{ $key->id }}" style="{{ $key->isValid() ? '' : 'opacity:.6' }}">
                                    <td>
                                        <b>{{ $key->name }}</b>
                                        <div style="font-size:.76rem;color:#8a93a0">
                                            {{ __('settings.api.created_on', ['date' => $key->created_at?->format('Y-m-d')]) }}
                                        </div>
                                    </td>
                                    <td><span class="st-mono">{{ $key->key }}</span></td>
                                    <td>
                                        @forelse ($key->abilityLabels() as $label)
                                            <span class="st-badge info" style="margin:.1rem">{{ $label }}</span>
                                        @empty
                                            {{-- مفتاح بلا صلاحيات لا يفتح شيئاً — كان هذا حال
                                                 كل مفتاح يُنشأ من الشاشة قبل الإصلاح --}}
                                            <span class="st-badge dang">{{ __('settings.api.no_abilities') }}</span>
                                        @endforelse
                                    </td>
                                    <td class="num">
                                        <b>{{ number_format($key->usage_count ?? 0) }}</b>
                                        <div style="font-size:.76rem;color:#8a93a0">
                                            {{ $key->last_used_at ? __('settings.api.last_used', ['when' => $key->last_used_at->diffForHumans()]) : __('settings.api.never_used') }}
                                        </div>
                                    </td>
                                    <td>
                                        @if ($key->expires_at)
                                            <span class="st-badge {{ $expired ? 'dang' : 'warn' }}">
                                                {{ $key->expires_at->format('Y-m-d') }}
                                            </span>
                                        @else
                                            <span class="st-badge off">{{ __('settings.api.no_expiry') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="st-badge {{ $key->isValid() ? 'on' : ($expired ? 'dang' : 'off') }}">
                                            {{ $key->isValid() ? __('settings.api.state_active') : ($expired ? __('settings.api.state_expired') : __('settings.api.state_disabled')) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="st-actions">
                                            @permission('api_keys.revoke')
                                                <button type="button" class="btn btn-sm btn-outline-{{ $key->is_active ? 'warning' : 'success' }} ak-toggle"
                                                    data-id="{{ $key->id }}"
                                                    title="{{ $key->is_active ? __('settings.api.disable') : __('settings.api.enable') }}">
                                                    <i class="feather icon-{{ $key->is_active ? 'pause' : 'play' }}"></i>
                                                </button>
                                            @endpermission
                                            @permission('api_keys.delete')
                                                <button type="button" class="btn btn-sm btn-outline-danger ak-delete"
                                                    data-id="{{ $key->id }}" data-name="{{ $key->name }}" title="{{ __('settings.common.delete') }}">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            @endpermission
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="st-empty">
                                            <i class="feather icon-key"></i>
                                            {{ __('settings.api.empty') }}
                                            <div style="font-size:.82rem">{{ __('settings.api.empty_hint') }}</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="st-card">
            <div class="head">
                <i class="feather icon-code" style="color:#2b6cb0"></i>
                <h4>{{ __('settings.api.how_to') }}</h4>
            </div>
            <div class="body">
                <p style="font-size:.9rem;color:#6b7280;margin-bottom:.6rem">{{ __('settings.api.how_to_hint') }}</p>
                <pre class="ak-secret" style="margin:0">X-Api-Key: fawt_xxxxxxxxxxxxxxxx
X-Api-Secret: yyyyyyyyyyyyyyyyyyyy</pre>
            </div>
        </div>
    </div>

    @permission('api_keys.create')
        <div class="modal fade" id="newKeyModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('settings.api.new_title') }}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <form id="newKeyForm">
                        <div class="modal-body">
                            <div id="keyFormError" class="alert alert-danger" style="display:none"></div>

                            <div class="form-group">
                                <label>{{ __('settings.api.name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                    placeholder="{{ __('settings.api.name_placeholder') }}">
                                <small class="text-muted">{{ __('settings.api.name_hint') }}</small>
                            </div>

                            <div class="form-group">
                                <label>{{ __('settings.api.abilities') }} <span class="text-danger">*</span></label>
                                @foreach ($abilities as $value => $label)
                                    <label class="ak-ability">
                                        <input type="checkbox" name="abilities[]" value="{{ $value }}">
                                        <span>
                                            {{ $label }}
                                            <div style="font-size:.74rem;color:#9aa2ae">
                                                <span class="st-mono">{{ $value }}</span>
                                            </div>
                                        </span>
                                    </label>
                                @endforeach
                                <small class="text-muted">{{ __('settings.api.abilities_hint') }}</small>
                            </div>

                            <div class="form-group mb-0">
                                <label>{{ __('settings.api.expiry_date') }}</label>
                                <input type="date" name="expires_at" class="form-control"
                                    min="{{ now()->addDay()->toDateString() }}">
                                <small class="text-muted">{{ __('settings.api.expiry_hint') }}</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('settings.common.cancel') }}</button>
                            <button type="submit" class="btn btn-primary" id="createKeyBtn">
                                <i class="feather icon-key"></i> {{ __('settings.api.create') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- عرض الرمز السرّي مرّة واحدة -->
        <div class="modal fade" id="secretModal" tabindex="-1" role="dialog" data-backdrop="static" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title text-white">{{ __('settings.api.created_title') }}</h5>
                    </div>
                    <div class="modal-body">
                        <div class="st-note dang">
                            <i class="feather icon-alert-triangle mt-1"></i>
                            <div>
                                <strong>{{ __('settings.api.copy_now') }}</strong>
                                <p>{{ __('settings.api.copy_now_hint') }}</p>
                            </div>
                        </div>

                        <div class="ak-field">
                            <label>{{ __('settings.api.key') }}</label>
                            <input type="text" class="form-control st-mono" id="outKey" readonly
                                style="direction:ltr;text-align:left">
                            <button type="button" class="btn btn-sm btn-outline-secondary ak-copy" data-target="outKey">
                                <i class="feather icon-copy"></i>
                            </button>
                        </div>

                        <div class="ak-field">
                            <label>{{ __('settings.api.secret') }}</label>
                            <input type="text" class="form-control st-mono" id="outSecret" readonly
                                style="direction:ltr;text-align:left">
                            <button type="button" class="btn btn-sm btn-outline-secondary ak-copy" data-target="outSecret">
                                <i class="feather icon-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="secretDone">{{ __('settings.api.copied_close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endpermission
@endsection

@section('scripts')
    <script>
        (function () {
            var token = document.querySelector('meta[name=csrf-token]').content;

            function send(url, method, body) {
                return fetch(url, {
                    method: method,
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: body ? JSON.stringify(body) : undefined
                }).then(function (r) {
                    return r.json().then(function (b) { return { ok: r.ok, status: r.status, body: b }; });
                });
            }

            // ── إنشاء ──
            var form = document.getElementById('newKeyForm');

            if (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    var error = document.getElementById('keyFormError');
                    var button = document.getElementById('createKeyBtn');
                    var abilities = Array.prototype.slice
                        .call(form.querySelectorAll('input[name="abilities[]"]:checked'))
                        .map(function (i) { return i.value; });

                    error.style.display = 'none';
                    button.disabled = true;

                    send('{{ route('apikeys.store') }}', 'POST', {
                        name: form.name.value,
                        abilities: abilities,
                        expires_at: form.expires_at.value || null
                    }).then(function (res) {
                        button.disabled = false;

                        if (!res.ok || !res.body.success) {
                            // رسائل التحقّق تصل في `errors` — تُعرض كما هي بالعربية
                            var messages = res.body.errors
                                ? Object.keys(res.body.errors).map(function (k) { return res.body.errors[k][0]; })
                                : [res.body.message || @json(__('settings.api.errors.create_failed'))];
                            error.innerHTML = messages.join('<br>');
                            error.style.display = '';
                            return;
                        }

                        $('#newKeyModal').modal('hide');
                        document.getElementById('outKey').value = res.body.data.key;
                        document.getElementById('outSecret').value = res.body.data.secret;
                        $('#secretModal').modal('show');
                        form.reset();
                    }).catch(function () {
                        button.disabled = false;
                        error.textContent = @json(__('settings.common.connection_failed'));
                        error.style.display = '';
                    });
                });

                document.getElementById('secretDone').addEventListener('click', function () {
                    $('#secretModal').modal('hide');
                    window.location.reload();
                });
            }

            // ── نسخ ──
            document.addEventListener('click', function (event) {
                var copy = event.target.closest('.ak-copy');

                if (copy) {
                    var field = document.getElementById(copy.dataset.target);
                    field.select();
                    document.execCommand('copy');
                    copy.innerHTML = '<i class="feather icon-check"></i>';
                    setTimeout(function () { copy.innerHTML = '<i class="feather icon-copy"></i>'; }, 1500);
                    return;
                }

                // ── تفعيل/تعطيل ──
                var toggle = event.target.closest('.ak-toggle');

                if (toggle) {
                    toggle.disabled = true;
                    send('{{ url('apikeys') }}/' + toggle.dataset.id + '/toggle', 'POST')
                        .then(function () { window.location.reload(); })
                        .catch(function () { toggle.disabled = false; });
                    return;
                }

                // ── حذف ──
                var del = event.target.closest('.ak-delete');

                if (del) {
                    if (!confirm(@json(__('settings.api.confirm_delete')).replace(':name', del.dataset.name))) return;

                    del.disabled = true;
                    send('{{ url('apikeys') }}/' + del.dataset.id, 'DELETE')
                        .then(function (res) {
                            if (res.ok && res.body.success) {
                                del.closest('tr').remove();
                            } else {
                                del.disabled = false;
                                alert(res.body.message || @json(__('settings.common.delete_failed')));
                            }
                        })
                        .catch(function () { del.disabled = false; });
                }
            });
        })();
    </script>
@endsection
