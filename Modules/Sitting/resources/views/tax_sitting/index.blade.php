@extends('sitting::master')

@section('title', __('settings.tax.title'))

@section('content')
    @include('sitting::components._settings_styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-percent"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.tax.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.tax.breadcrumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <button type="button" class="btn btn-outline-primary" id="addTaxRow">
                <i class="feather icon-plus"></i> {{ __('settings.tax.new') }}
            </button>
            <button type="submit" form="taxForm" class="btn btn-primary">
                <i class="feather icon-save"></i> {{ __('settings.common.save') }}
            </button>

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

        @php
            $inUse = $usage->filter(fn ($count) => $count > 0)->count();
        @endphp

        <div class="st-stats">
            <div class="st-stat">
                <div class="ic primary"><i class="feather icon-percent"></i></div>
                <div>
                    <div class="val">{{ $tax->count() }}</div>
                    <div class="lbl">{{ __('settings.tax.defined') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic success"><i class="feather icon-link"></i></div>
                <div>
                    <div class="val">{{ $inUse }}</div>
                    <div class="lbl">{{ __('settings.tax.in_use') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic info"><i class="feather icon-trending-up"></i></div>
                <div>
                    <div class="val">{{ $tax->count() ? rtrim(rtrim(number_format($tax->max('tax'), 2), '0'), '.') : 0 }}%</div>
                    <div class="lbl">{{ __('settings.tax.highest') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic warning"><i class="feather icon-layers"></i></div>
                <div>
                    <div class="val">{{ $tax->where('type', 'included')->count() }}</div>
                    <div class="lbl">{{ __('settings.tax.inclusive_count') }}</div>
                </div>
            </div>
        </div>

        <div class="st-note info">
            <i class="feather icon-info mt-1"></i>
            <div>
                <strong>{{ __('settings.tax.note_types') }}</strong>
                <p>
                    {{ __('settings.tax.note_delete') }}
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('TaxSitting.updateAll') }}" id="taxForm">
            @csrf

            @php $comprehensiveId = optional($tax->firstWhere('is_comprehensive', true))->id; @endphp

            {{-- الضريبة الشاملة على المستندات — تفسيرها وخيارُ تعطيلها.
                 اختيارُ ضريبةٍ في العمود يجعلها الافتراضية أعلى كلّ مستندٍ
                 (مبيعاتٍ ومشترياتٍ) وتُخفى ضريبةُ الأسطر؛ والبائعُ يقدر يتركها
                 «بدون ضريبة» على المستند. «تعطيل» يُعيد ضريبةَ كلّ سطر. --}}
            <div class="st-note info">
                <i class="feather icon-percent mt-1"></i>
                <div>
                    <strong>{{ __('settings.tax.comprehensive') }}</strong>
                    <p>{{ __('settings.tax.comprehensive_note') }}</p>
                    <label style="display:inline-flex;align-items:center;gap:.4rem;margin:0;cursor:pointer">
                        <input type="radio" name="comprehensive_tax_id" value="" @checked(! $comprehensiveId)>
                        <span>{{ __('settings.tax.comprehensive_disable') }}</span>
                    </label>
                </div>
            </div>

            <div class="st-card">
                <div class="head">
                    <i class="feather icon-percent" style="color:#2b6cb0"></i>
                    <h4>{{ __('settings.tax.table') }}</h4>
                    <span class="count">{{ $tax->count() }}</span>
                </div>
                <div class="body flush">
                    <div class="table-responsive">
                        <table class="st-table" id="taxTable">
                            <thead>
                                <tr>
                                    <th style="width:32%">{{ __('settings.tax.tax_name') }}</th>
                                    <th style="width:14%">{{ __('settings.tax.rate') }}</th>
                                    <th style="width:18%">{{ __('settings.common.type') }}</th>
                                    <th style="width:18%" class="text-center">{{ __('settings.tax.comprehensive') }}</th>
                                    <th style="width:12%">{{ __('settings.tax.usage') }}</th>
                                    <th style="width:6%" class="text-center">{{ __('settings.common.delete') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($tax as $index => $row)
                                    <tr data-id="{{ $row->id }}">
                                        <td>
                                            <input type="hidden" name="id[]" value="{{ $row->id }}">
                                            <input type="text" name="name[]" class="form-control"
                                                value="{{ old('rows.'.$index.'.name', $row->name) }}"
                                                placeholder="{{ __('settings.tax.name_placeholder') }}" required>
                                        </td>
                                        <td>
                                            <input type="number" name="tax[]" class="form-control"
                                                value="{{ old('rows.'.$index.'.tax', $row->tax) }}"
                                                step="0.01" min="0" max="100" required>
                                        </td>
                                        <td>
                                            <select name="type[]" class="form-control">
                                                <option value="excluded" {{ $row->type === 'excluded' ? 'selected' : '' }}>{{ __('settings.tax.excluded') }}</option>
                                                <option value="included" {{ $row->type === 'included' ? 'selected' : '' }}>{{ __('settings.tax.included') }}</option>
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            {{-- الضريبة الشاملة للمستندات: زرّ اختيارٍ واحدٌ عبر الجدول
                                                 (واحدةٌ فقط)، ويُطفَأ الكلُّ باختيار «تعطيل» أعلاه --}}
                                            <input type="radio" name="comprehensive_tax_id" value="{{ $row->id }}"
                                                @checked($row->is_comprehensive)
                                                title="{{ __('settings.tax.comprehensive_hint') }}">
                                        </td>
                                        <td class="num">
                                            @if (($usage[$row->id] ?? 0) > 0)
                                                <span class="st-badge info">{{ __('settings.tax.records', ['count' => number_format($usage[$row->id])]) }}</span>
                                            @else
                                                <span class="st-badge off">{{ __('settings.tax.unused') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger tax-delete"
                                                data-id="{{ $row->id }}" data-name="{{ $row->name }}"
                                                @if (($usage[$row->id] ?? 0) > 0) disabled title="{{ __('settings.tax.used_locked') }}" @endif>
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="taxEmpty">
                                        <td colspan="6">
                                            <div class="st-empty">
                                                <i class="feather icon-percent"></i>
                                                {{ __('settings.tax.empty') }}
                                                <div style="font-size:.82rem">{{ __('settings.tax.empty_hint') }}</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="foot text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> {{ __('settings.tax.save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- قالب صفّ جديد --}}
    <template id="taxRowTemplate">
        <tr>
            <td>
                <input type="hidden" name="id[]" value="">
                <input type="text" name="name[]" class="form-control" placeholder="{{ __('settings.tax.tax_name') }}" required>
            </td>
            <td>
                <input type="number" name="tax[]" class="form-control" step="0.01" min="0" max="100"
                    placeholder="15" required>
            </td>
            <td>
                <select name="type[]" class="form-control">
                    <option value="excluded">{{ __('settings.tax.excluded') }}</option>
                    <option value="included">{{ __('settings.tax.included') }}</option>
                </select>
            </td>
            <td class="text-center"><small class="text-muted">{{ __('settings.tax.comprehensive_save_first') }}</small></td>
            <td><span class="st-badge off">{{ __('settings.tax.is_new') }}</span></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger tax-remove-row">
                    <i class="feather icon-x"></i>
                </button>
            </td>
        </tr>
    </template>
@endsection

@section('scripts')
    <script>
        (function () {
            var body = document.querySelector('#taxTable tbody');
            // المسار من المولّد لا مكتوباً يدوياً — بادئته `TaxSitting`
            var deleteUrl = @json(route('tax-sittings.destroy', ['id' => '__ID__']));
            var template = document.getElementById('taxRowTemplate');

            document.getElementById('addTaxRow').addEventListener('click', function () {
                var empty = document.getElementById('taxEmpty');
                if (empty) empty.remove();

                body.appendChild(template.content.firstElementChild.cloneNode(true));
                body.lastElementChild.querySelector('input[name="name[]"]').focus();
            });

            body.addEventListener('click', function (event) {
                // صفّ لم يُحفظ بعد: يُزال من الشاشة بلا طلب للخادم
                var remove = event.target.closest('.tax-remove-row');
                if (remove) { remove.closest('tr').remove(); return; }

                var del = event.target.closest('.tax-delete');
                if (!del || del.disabled) return;

                var name = del.dataset.name || @json(__('settings.tax.this_tax'));

                if (!confirm(@json(__('settings.tax.confirm_delete')).replace(':name', name))) return;

                del.disabled = true;

                fetch(deleteUrl.replace('__ID__', del.dataset.id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                    .then(function (res) {
                        if (res.ok && res.body.success) {
                            del.closest('tr').remove();
                        } else {
                            del.disabled = false;
                            alert(res.body.message || @json(__('settings.common.delete_failed')));
                        }
                    })
                    .catch(function () {
                        del.disabled = false;
                        alert(@json(__('settings.common.connection_failed')));
                    });
            });
        })();
    </script>
@endsection
