@extends('sales::master')

{{-- عرض خيار الشحن — بطاقات إحصاء ثم بطاقة بيانات، على لغة تصميم
     «الأذون المخزنية».

     كان زرّ الحذف هنا رابطاً إلى مسار DELETE داخل نافذة تأكيد فيرتدّ
     بـ405، وفتات المسار `href=""`.

     التاريخ يُنسَّق بـ`LanguageSettings::formatDate` لا بـ`format('Y-m-d H:i')`
     مكتوبةً في القالب: شاشة «اللغات» تضبط صيغة التاريخ والوقت والمنطقة
     الزمنية، وكانت هذه الشاشة وحدها لا تعرف بذلك. --}}

@section('title', $shippingOption->name)

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('shippingOptions.edit', $shippingOption->id) }}" class="btn btn-primary">
                <i class="feather icon-edit-2"></i> {{ __('sales_settings.ui.edit') }}
            </a>

            <a href="{{ route('shippingOptions.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => $shippingOption->name,
            'icon' => 'truck',
            'sub' => __('sales_settings.shipping.show_title'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.shipping.title') => route('shippingOptions.index'),
                $shippingOption->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-shipping.show',
            'title' => __('sales_settings.guide.shipping.title'),
            'lead' => __('sales_settings.guide.shipping.lead'),
            'open' => false,
            'blocks' => [
                __('sales_settings.guide.shipping.fields.title') => [
                    __('sales_settings.guide.shipping.fields.a'),
                    __('sales_settings.guide.shipping.fields.b'),
                    __('sales_settings.guide.shipping.fields.c'),
                ],
            ],
        ])

        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon {{ $shippingOption->isActive() ? 'sl-stat__icon--success' : 'sl-stat__icon--danger' }}">
                    <i class="feather icon-{{ $shippingOption->isActive() ? 'check-circle' : 'x-circle' }}"></i>
                </div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.2rem">
                        {{ $shippingOption->isActive() ? __('sales_settings.ui.active') : __('sales_settings.ui.inactive') }}
                    </p>
                    <p class="sl-stat__label">{{ __('sales_settings.ui.status') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-dollar-sign"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.2rem">
                        {{ \App\Support\CurrencySettings::format((float) $shippingOption->cost) }}
                    </p>
                    <p class="sl-stat__label">{{ __('sales_settings.shipping.cost') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-percent"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.2rem">{{ $shippingOption->taxLabel() }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.shipping.tax_type') }}</p>
                </div>
            </div>
        </div>

        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-truck"></i> {{ __('sales_settings.shipping.details') }}
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table mb-0">
                    <tbody>
                        <tr>
                            <td style="width:220px;color:var(--app-muted)">{{ __('sales_settings.ui.name') }}</td>
                            <td>{{ $shippingOption->name }}</td>
                        </tr>
                        <tr>
                            <td style="color:var(--app-muted)">{{ __('sales_settings.ui.description') }}</td>
                            <td>{{ $shippingOption->description ?: __('sales_settings.ui.none') }}</td>
                        </tr>
                        <tr>
                            <td style="color:var(--app-muted)">{{ __('sales_settings.shipping.display_order') }}</td>
                            <td class="sl-num">{{ $shippingOption->display_order }}</td>
                        </tr>
                        <tr>
                            <td style="color:var(--app-muted)">{{ __('sales_settings.shipping.default_account') }}</td>
                            <td>
                                @if ($shippingOption->defaultAccount)
                                    <span class="sl-num">{{ $shippingOption->defaultAccount->code }}</span>
                                    — {{ $shippingOption->defaultAccount->name }}
                                @elseif (! empty($fallbackAccount))
                                    <span class="sl-state sl-state--neutral">
                                        {{ __('sales_settings.shipping.fallback_account') }}:
                                        {{ $fallbackAccount->code }} — {{ $fallbackAccount->name }}
                                    </span>
                                @else
                                    <span class="sl-state sl-state--off">{{ __('sales_settings.shipping.no_account') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="color:var(--app-muted)">{{ __('sales_settings.ui.created_at') }}</td>
                            <td>
                                {{ $shippingOption->created_at
                                    ? \App\Support\LanguageSettings::formatDate($shippingOption->created_at, true)
                                    : __('sales_settings.ui.none') }}
                            </td>
                        </tr>
                        <tr>
                            <td style="color:var(--app-muted)">{{ __('sales_settings.ui.updated_at') }}</td>
                            <td>
                                {{ $shippingOption->updated_at
                                    ? \App\Support\LanguageSettings::formatDate($shippingOption->updated_at, true)
                                    : __('sales_settings.ui.none') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="sl-form-foot">
                {{-- التبديل مسار POST، فهو زرّ داخل نموذج لا رابط --}}
                <form action="{{ route('shippingOptions.updateStatus', $shippingOption->id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="btn btn-outline-{{ $shippingOption->isActive() ? 'warning' : 'success' }}">
                        <i class="feather icon-{{ $shippingOption->isActive() ? 'x-circle' : 'check-circle' }}"></i>
                        {{ $shippingOption->isActive() ? __('sales_settings.ui.inactive') : __('sales_settings.ui.active') }}
                    </button>
                </form>

                {{-- المسار DELETE؛ الرابط هنا كان يرتدّ بـ405 --}}
                <form action="{{ route('shippingOptions.destroy', $shippingOption->id) }}" method="POST"
                      class="sl-delete-form" data-name="{{ $shippingOption->name }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="feather icon-trash-2"></i> {{ __('sales_settings.ui.delete') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
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
