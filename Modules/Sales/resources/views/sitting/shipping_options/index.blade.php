@extends('sales::master')

{{-- خيارات الشحن — على لغة تصميم «الأذون المخزنية» منقولةً إلى المبيعات:
     ترويسة صفحة بأيقونة وفتات مسار وإجراءات، ثم بطاقة تصفية ببحث أساسي
     وبحث متقدّم مطويّ، ثم بطاقة جدول برأس فيه العدّاد، ثم ذيلٌ فيه
     مُنتقي عدد السجلّات والترقيم.

     كانت على تخطيط شاشات وحدة الإعدادات: بطاقات `stat-card-modern` من
     `online-store.css` وجدول `modern-table` وقائمة إجراءات منسدلة —
     ثلاثة أنظمة بصرية في وحدة واحدة.

     التكلفة تُعرض بـ`CurrencySettings::format`: كانت `number_format(...,2)`
     رقماً عارياً بلا رمز ولا فاصلة عملة النظام، فيقرأ المستخدم «25.00»
     ولا يعلم بأيّ عملة. --}}

@section('title', __('sales_settings.shipping.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $hasFilters = request()->hasAny(['search', 'status', 'tax']);
    @endphp

    <div class="content-body sl-scope">

        {{-- يُعرَّف قبل استدعاء الترويسة: `yieldContent` تقرأ ما سُجّل حتى تلك اللحظة --}}
        @section('page-actions')
            @permission('sales.shipping_options.manage')
                <a href="{{ route('shippingOptions.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('sales_settings.shipping.new') }}
                </a>
            @endpermission

            <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_settings.shipping.title'),
            'icon' => 'truck',
            'sub' => __('sales_settings.shipping.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.shipping.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-shipping.index',
            'open' => false,
            'title' => __('sales_settings.guide.shipping.title'),
            'lead' => __('sales_settings.guide.shipping.lead'),
            'blocks' => [
                __('sales_settings.guide.shipping.fields.title') => [
                    __('sales_settings.guide.shipping.fields.a'),
                    __('sales_settings.guide.shipping.fields.b'),
                    __('sales_settings.guide.shipping.fields.c'),
                ],
                __('sales_settings.guide.shipping.effects.title') => [
                    __('sales_settings.guide.shipping.effects.a'),
                    __('sales_settings.guide.shipping.effects.b'),
                    __('sales_settings.guide.shipping.effects.c'),
                ],
            ],
        ])

        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- إفصاح مقصود: لا مرجع واحد لـ`ShippingOption` خارج هذه الشاشة في
             المشروع كلّه — الفاتورة تحفظ نصّاً حرّاً. إخفاء ذلك يجعل المستخدم
             يضبط خيارات يظنّها فاعلة --}}
        <div class="alert alert-info">
            <i class="feather icon-info"></i> {{ __('sales_settings.shipping.not_wired_note') }}
        </div>

        {{-- ══════════ بطاقات الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-truck"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($stats['total'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.shipping.total') }} — {{ __('sales_settings.shipping.all_options') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-check-circle"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($stats['active'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.shipping.active') }} — {{ __('sales_settings.shipping.available_on_invoice') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-dollar-sign"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.25rem">
                        {{ \App\Support\CurrencySettings::format($stats['avg_cost']) }}
                    </p>
                    <p class="sl-stat__label">{{ __('sales_settings.shipping.avg_cost') }} — {{ __('sales_settings.shipping.across_options') }}</p>
                </div>
            </div>
        </div>

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="feather icon-filter"></i> {{ __('sales_settings.ui.filters') }}
                    </h6>

                    @if ($hasFilters)
                        <span class="sl-count">{{ __('sales_settings.ui.filters_active') }}</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('shippingOptions.index') }}">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="search">{{ __('sales_settings.ui.search') }}</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       value="{{ request('search') }}"
                                       placeholder="{{ __('sales_settings.ui.search_placeholder') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">{{ __('sales_settings.ui.status') }}</label>
                                {{-- القيم 1/2 لا 1/0: هي ما يكتبه `updateStatus` وما يقبله `store` --}}
                                <select id="status" name="status" class="form-control">
                                    <option value="">{{ __('sales_settings.ui.all_statuses') }}</option>
                                    <option value="{{ \App\Models\ShippingOption::STATUS_ACTIVE }}"
                                        @selected(request('status') === (string) \App\Models\ShippingOption::STATUS_ACTIVE)>
                                        {{ __('sales_settings.ui.active') }}
                                    </option>
                                    <option value="{{ \App\Models\ShippingOption::STATUS_INACTIVE }}"
                                        @selected(request('status') === (string) \App\Models\ShippingOption::STATUS_INACTIVE)>
                                        {{ __('sales_settings.ui.inactive') }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="tax">{{ __('sales_settings.shipping.tax_type') }}</label>
                                <select id="tax" name="tax" class="form-control">
                                    <option value="">{{ __('sales_settings.ui.none') }}</option>
                                    <option value="{{ \App\Models\ShippingOption::TAX_VAT }}"
                                        @selected(request('tax') === (string) \App\Models\ShippingOption::TAX_VAT)>
                                        {{ __('sales_settings.shipping.tax_types.'.\App\Models\ShippingOption::TAX_VAT) }}
                                    </option>
                                    <option value="{{ \App\Models\ShippingOption::TAX_ZERO }}"
                                        @selected(request('tax') === (string) \App\Models\ShippingOption::TAX_ZERO)>
                                        {{ __('sales_settings.shipping.tax_types.'.\App\Models\ShippingOption::TAX_ZERO) }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        @if ($hasFilters)
                            <a href="{{ route('shippingOptions.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> {{ __('sales_settings.ui.filters_clear') }}
                            </a>
                        @endif

                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> {{ __('sales_settings.ui.filters_apply') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ الجدول ══════════ --}}
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-truck"></i> {{ __('sales_settings.shipping.title') }}
                </h6>
                <span class="sl-count">{{ \App\Support\CurrencySettings::number($shippingOptions->total(), 0) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:78px">{{ __('sales_settings.shipping.display_order') }}</th>
                            <th>{{ __('sales_settings.ui.name') }}</th>
                            <th class="text-center">{{ __('sales_settings.shipping.cost') }}</th>
                            <th class="text-center">{{ __('sales_settings.shipping.tax_type') }}</th>
                            <th>{{ __('sales_settings.shipping.default_account') }}</th>
                            <th class="text-center">{{ __('sales_settings.ui.status') }}</th>
                            <th class="text-center" style="width:130px">{{ __('sales_settings.ui.actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($shippingOptions as $shipping)
                            <tr>
                                <td class="text-center sl-num">{{ $shipping->display_order }}</td>

                                <td>
                                    <div class="sl-media">
                                        <span class="sl-media__thumb" aria-hidden="true"><i class="feather icon-truck"></i></span>
                                        <div class="sl-media__body">
                                            <span class="sl-media__title">{{ $shipping->name }}</span>
                                            @if ($shipping->description)
                                                <span class="sl-media__sub">{{ Str::limit($shipping->description, 55) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- بعملة النظام لا برقم عارٍ --}}
                                <td class="text-center sl-num">
                                    {{ \App\Support\CurrencySettings::format((float) $shipping->cost) }}
                                </td>

                                {{-- `tax` رمز معاملة لا مبلغ، وكان يُعرض «1.00» تحت عنوان «الضريبة» --}}
                                <td class="text-center">
                                    <span class="badge {{ $shipping->tax === \App\Models\ShippingOption::TAX_ZERO ? 'badge-secondary' : 'badge-primary' }}">
                                        {{ $shipping->taxLabel() }}
                                    </span>
                                </td>

                                <td>
                                    @if ($shipping->defaultAccount)
                                        <span class="sl-media__title">{{ Str::limit($shipping->defaultAccount->name, 28) }}</span>
                                        <span class="sl-media__sub sl-num">{{ $shipping->defaultAccount->code }}</span>
                                    @else
                                        <span class="sl-state sl-state--neutral">{{ __('sales_settings.shipping.no_account') }}</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span class="sl-state {{ $shipping->isActive() ? 'sl-state--on' : 'sl-state--off' }}">
                                        {{ $shipping->isActive() ? __('sales_settings.ui.active') : __('sales_settings.ui.inactive') }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <div class="sl-actions">
                                        <a href="{{ route('shippingOptions.show', $shipping->id) }}"
                                           class="btn btn-outline-secondary" title="{{ __('sales_settings.ui.view') }}"
                                           aria-label="{{ __('sales_settings.ui.view') }}">
                                            <i class="feather icon-eye"></i>
                                        </a>

                                        @permission('sales.shipping_options.manage')
                                            <a href="{{ route('shippingOptions.edit', $shipping->id) }}"
                                               class="btn btn-outline-primary" title="{{ __('sales_settings.ui.edit') }}"
                                               aria-label="{{ __('sales_settings.ui.edit') }}">
                                                <i class="feather icon-edit-2"></i>
                                            </a>

                                            {{-- التبديل مسار POST، فهو زرّ داخل نموذج لا رابط --}}
                                            <form action="{{ route('shippingOptions.updateStatus', $shipping->id) }}"
                                                  method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-outline-{{ $shipping->isActive() ? 'warning' : 'success' }}"
                                                        title="{{ __('sales_settings.shipping.toggle_hint') }}"
                                                        aria-label="{{ __('sales_settings.shipping.toggle_hint') }}">
                                                    <i class="feather icon-{{ $shipping->isActive() ? 'x-circle' : 'check-circle' }}"></i>
                                                </button>
                                            </form>

                                            {{-- المسار DELETE؛ كان الحذف رابطاً أي GET فيرتدّ بـ405 --}}
                                            <form action="{{ route('shippingOptions.destroy', $shipping->id) }}"
                                                  method="POST" class="sl-delete-form d-inline"
                                                  data-name="{{ $shipping->name }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger"
                                                        title="{{ __('sales_settings.ui.delete') }}"
                                                        aria-label="{{ __('sales_settings.ui.delete') }}">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            </form>
                                        @endpermission
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    @include('sales::components.empty-state', [
                                        'icon' => 'truck',
                                        'title' => $hasFilters
                                            ? __('sales_settings.ui.no_results')
                                            : __('sales_settings.shipping.empty'),
                                        'hint' => $hasFilters
                                            ? __('sales_settings.ui.no_results_hint')
                                            : __('sales_settings.shipping.empty_hint'),
                                        'action' => $hasFilters
                                            ? '<a href="'.route('shippingOptions.index').'" class="btn btn-outline-secondary">'
                                                .'<i class="feather icon-x"></i> '.e(__('sales_settings.ui.filters_clear')).'</a>'
                                            : (hasPermission('sales.shipping_options.manage')
                                                ? '<a href="'.route('shippingOptions.create').'" class="btn btn-primary">'
                                                    .'<i class="feather icon-plus"></i> '.e(__('sales_settings.shipping.new')).'</a>'
                                                : ''),
                                    ])
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('sales::components.table-foot', [
                'paginator' => $shippingOptions,
                'sizes' => $pageSizes,
            ])
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            // تأكيد الحذف — نفس نمط شاشات الأذون المخزنية
            $('.sl-delete-form').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);

                Swal.fire({
                    title: @json(__('sales_settings.ui.confirm_delete_title')),
                    text: @json(__('sales_settings.shipping.confirm_delete')).replace(':name', form.data('name')),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_settings.ui.confirm_yes')),
                    cancelButtonText: @json(__('sales_settings.ui.cancel')),
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.off('submit').submit();
                    }
                });
            });
        });
    </script>
@endsection
