@extends('sales::master')

{{-- مصادر الطلب — الطريقة التي أتى بها العميل.

     الجدول هنا قابل للتحرير في مكانه: صفوف تُضاف وتُسحَب وتُحفَظ دفعةً
     واحدة. هذا يخالف جداول العرض في بقيّة شاشات الإعدادات بحكم وظيفته، لا
     بحكم مزاج — فالمصدر سطرٌ باسمٍ وحالة، وفتحُ شاشة إضافة لكل سطر يجعل
     إدخال خمسة مصادر خمسَ رحلات.

     كانت الشاشة تجمع ثلاثة أنظمة بصرية: `online-store.css` من ملفّ عامّ،
     و`sitting::components._settings_styles` من وحدة أخرى، وورقة `os-*`
     ثالثة مكتوبة هنا بألوان مثبَّتة (`#b7bec8`، `#f0f4f9`) لا تتبع لون
     المظهر ولا تنقلب في الوضع الليلي. وكانت تنادي مفاتيح `settings.*` من
     وحدة الإعدادات، فتُظهر مفاتيح خاماً إن عُطّلت.

     الآن: `sl-*` وحدها، ومفاتيحها من `sales_settings`. --}}

@section('title', __('sales_settings.order_sources.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <style>
        /* السحب وحده يحتاج أنماطاً لا يغطّيها نظام التصميم: مقبضٌ ومكانٌ
           شاغر أثناء الإفلات. الألوان من `--app-*` لا مثبَّتة. */
        .sl-scope .os-handle { cursor: grab; color: var(--app-muted, #6e6b7b); }
        .sl-scope .os-handle:active { cursor: grabbing; }
        .sl-scope .os-row.ui-sortable-helper {
            display: table; background: var(--app-surface, #fff);
            box-shadow: var(--sl-e4);
        }
        .sl-scope .os-placeholder td {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            height: 52px;
        }
    </style>

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.order_sources.manage')
                <button type="button" class="btn btn-primary" id="osAdd">
                    <i class="feather icon-plus"></i> {{ __('sales_settings.order_sources.new') }}
                </button>

                <button type="submit" form="osForm" class="btn btn-success">
                    <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                </button>
            @endpermission

            <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_settings.order_sources.title'),
            'icon' => 'git-merge',
            'sub' => __('sales_settings.order_sources.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.order_sources.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-order-sources.index',
            'open' => false,
            'title' => __('sales_settings.guide.order_sources.title'),
            'lead' => __('sales_settings.guide.order_sources.lead'),
            'blocks' => [
                __('sales_settings.guide.order_sources.fields.title') => [
                    __('sales_settings.guide.order_sources.fields.a'),
                    __('sales_settings.guide.order_sources.fields.b'),
                    __('sales_settings.guide.order_sources.fields.c'),
                ],
                __('sales_settings.guide.order_sources.effects.title') => [
                    __('sales_settings.guide.order_sources.effects.a'),
                    __('sales_settings.guide.order_sources.effects.b'),
                ],
            ],
        ])

        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- ══════════ الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-git-merge"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num" id="osCountTotal">
                        {{ \App\Support\CurrencySettings::number($stats['total'], 0) }}
                    </p>
                    <p class="sl-stat__label">{{ __('sales_settings.order_sources.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-check-circle"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">
                        {{ \App\Support\CurrencySettings::number($stats['active'], 0) }}
                    </p>
                    <p class="sl-stat__label">
                        {{ __('sales_settings.order_sources.active') }} —
                        {{ __('sales_settings.order_sources.available_on_invoice') }}
                    </p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--{{ $is_mandatory === '1' ? 'warning' : 'info' }}">
                    <i class="feather icon-alert-circle"></i>
                </div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.1rem">
                        {{ $is_mandatory === '1' ? __('sales_settings.ui.active') : __('sales_settings.ui.inactive') }}
                    </p>
                    <p class="sl-stat__label">{{ __('sales_settings.order_sources.mandatory') }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('order_sources.storeOrUpdate') }}" id="osForm">
            @csrf

            {{-- ══════════ الصفوف ══════════ --}}
            <div class="card sl-table-card">
                <div class="sl-table-head">
                    <h6 class="sl-table-head__title">
                        <i class="feather icon-list"></i> {{ __('sales_settings.order_sources.list') }}
                    </h6>
                    <span class="sl-ctx__hint">{{ __('sales_settings.order_sources.drag_hint') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:48px"><span class="sr-only">{{ __('sales_settings.order_sources.sort') }}</span></th>
                                <th class="sl-req">{{ __('sales_settings.order_sources.row_name') }}</th>
                                <th class="text-center" style="width:120px">{{ __('sales_settings.ui.status') }}</th>
                                <th class="text-center" style="width:80px">{{ __('sales_settings.ui.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody id="osBody">
                            @foreach ($sources as $src)
                                <tr class="os-row" data-id="{{ $src->id }}">
                                    <td class="text-center os-handle" aria-hidden="true">
                                        <i class="feather icon-menu"></i>
                                    </td>

                                    <td>
                                        <input type="hidden" name="ids[]" value="{{ $src->id }}">
                                        <input type="text" name="name[]" value="{{ $src->name }}"
                                               class="form-control" required maxlength="255"
                                               aria-label="{{ __('sales_settings.order_sources.row_name') }}">
                                    </td>

                                    <td class="text-center">
                                        {{-- ترتيب العناصر مقصود: hidden ثم checkbox ثم المسار —
                                             قاعدة `input:checked + .sl-switch__track` تتطلّب أن
                                             يسبق المدخلُ المسارَ مباشرةً --}}
                                        <label class="sl-switch sl-switch--bare">
                                            <input type="hidden" class="os-active-off" value="0">
                                            <input type="checkbox" class="os-active" value="1"
                                                   @checked($src->active)
                                                   aria-label="{{ __('sales_settings.ui.status') }} — {{ $src->name }}">
                                            <span class="sl-switch__track" aria-hidden="true">
                                                <span class="sl-switch__thumb"></span>
                                            </span>
                                        </label>
                                    </td>

                                    <td class="text-center">
                                        <div class="sl-actions">
                                            @permission('sales.order_sources.manage')
                                                <button type="button" class="btn btn-outline-danger os-delete"
                                                        data-id="{{ $src->id }}" data-name="{{ $src->name }}"
                                                        title="{{ __('sales_settings.ui.delete') }}"
                                                        aria-label="{{ __('sales_settings.ui.delete') }}">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            @endpermission
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div id="osEmpty" style="{{ $sources->isEmpty() ? '' : 'display:none' }}">
                    @include('sales::components.empty-state', [
                        'icon' => 'git-merge',
                        'title' => __('sales_settings.order_sources.empty'),
                        'hint' => __('sales_settings.order_sources.empty_hint'),
                    ])
                </div>
            </div>

            {{-- ══════════ الافتراضات ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-sliders"></i> {{ __('sales_settings.order_sources.defaults') }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="defaultSource">{{ __('sales_settings.order_sources.default_source') }}</label>

                                <select id="defaultSource" name="default_order_source_id" class="form-control">
                                    <option value="">{{ __('sales_settings.ui.none') }}</option>
                                    @foreach ($sources as $src)
                                        <option value="{{ $src->id }}" @selected((string) $default_id === (string) $src->id)>
                                            {{ $src->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <span class="sl-help">{{ __('sales_settings.order_sources.default_source_hint') }}</span>
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="sl-switch-row" style="border:0;padding-top:1.9rem">
                                @include('sales::components.switch', [
                                    'name' => 'order_source_mandatory',
                                    'checked' => $is_mandatory === '1',
                                    'title' => __('sales_settings.order_sources.mandatory'),
                                    'hint' => __('sales_settings.order_sources.mandatory_hint'),
                                ])
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sl-form-foot">
                    <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                        {{ __('sales_settings.ui.cancel') }}
                    </a>

                    @permission('sales.order_sources.manage')
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                        </button>
                    @endpermission
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
        (function () {
            var body = document.getElementById('osBody');
            var form = document.getElementById('osForm');
            var empty = document.getElementById('osEmpty');
            var total = document.getElementById('osCountTotal');
            var deleteUrl = @json(route('order_sources.destroy', ['id' => '__ID__']));

            /**
             * `active` مصفوفة مفهرسة بالموضع، و`name[]` كذلك — والمتحكّم يقرن
             * بينهما بالفهرس. فبعد كلّ إضافة أو حذف أو سحب تُعاد التسمية،
             * وإلا اقترن اسم صفّ بحالة صفّ آخر.
             *
             * وحقل `hidden` يسبق مربّع الاختيار بنفس الاسم: مربّع غير مؤشَّر
             * لا يُرسَل، فبلا الحقل يصير التعطيل «لم يُذكَر» فيبقى مفعَّلاً.
             */
            function renumber() {
                var rows = body.querySelectorAll('tr');

                rows.forEach(function (row, index) {
                    row.querySelector('.os-active-off').name = 'active[' + index + ']';
                    row.querySelector('.os-active').name = 'active[' + index + ']';
                });

                empty.style.display = rows.length ? 'none' : '';
                if (total) total.textContent = rows.length;
            }

            document.getElementById('osAdd').addEventListener('click', function () {
                var row = document.createElement('tr');
                row.className = 'os-row';
                row.innerHTML =
                    '<td class="text-center os-handle" aria-hidden="true"><i class="feather icon-menu"></i></td>' +
                    '<td><input type="hidden" name="ids[]" value="">' +
                    '<input type="text" name="name[]" class="form-control" required maxlength="255"></td>' +
                    '<td class="text-center"><label class="sl-switch sl-switch--bare">' +
                    '<input type="hidden" class="os-active-off" value="0">' +
                    '<input type="checkbox" class="os-active" value="1" checked>' +
                    '<span class="sl-switch__track" aria-hidden="true"><span class="sl-switch__thumb"></span></span>' +
                    '</label></td>' +
                    '<td class="text-center"><div class="sl-actions">' +
                    '<button type="button" class="btn btn-outline-danger os-delete">' +
                    '<i class="feather icon-trash-2"></i></button></div></td>';

                body.appendChild(row);
                renumber();
                row.querySelector('input[type=text]').focus();
            });

            // الصفّ غير المحفوظ يُزال من الشاشة وحدها؛ والمحفوظ يُحذف من القاعدة
            form.addEventListener('click', function (event) {
                var button = event.target.closest('.os-delete');
                if (!button) return;

                var row = button.closest('tr');
                var id = button.dataset.id;

                if (!id) {
                    row.remove();
                    renumber();
                    return;
                }

                Swal.fire({
                    title: @json(__('sales_settings.ui.confirm_delete_title')),
                    text: @json(__('sales_settings.order_sources.confirm_delete')).replace(':name', button.dataset.name),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_settings.ui.confirm_yes')),
                    cancelButtonText: @json(__('sales_settings.ui.cancel')),
                    reverseButtons: true
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    button.disabled = true;

                    fetch(deleteUrl.replace('__ID__', id), {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                        .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                        .then(function () {
                            row.remove();
                            renumber();

                            // الصفّ المحذوف قد يكون هو المصدر الافتراضي: خيارُه
                            // يبقى في القائمة فيُرسَل معرّفاً لا وجود له، فيسقط
                            // الحفظُ كلُّه على قاعدة `exists`
                            var option = document.querySelector('#defaultSource option[value="' + id + '"]');
                            if (option) option.remove();
                        })
                        .catch(function () {
                            button.disabled = false;
                            Swal.fire({ icon: 'error', text: @json(__('sales_settings.ui.delete_failed')) });
                        });
                });
            });

            if (window.jQuery && jQuery.fn.sortable) {
                jQuery(body).sortable({
                    handle: '.os-handle',
                    helper: function (event, row) {
                        // بلا تثبيت العرض تنكمش الخلايا أثناء السحب
                        row.children().each(function () { jQuery(this).width(jQuery(this).width()); });
                        return row;
                    },
                    placeholder: 'os-placeholder',
                    update: renumber
                }).disableSelection();
            }

            renumber();
        })();
    </script>
@endsection
