@extends('sales::master')

{{-- تفاصيل عملية التبديل.

     **ما أُضيف**: قسمُ «أثر العملية». التبديل يولّد إذنين مخزنيّين وقيدين
     محاسبيّين، ولم تكن الشاشة تذكر شيئاً من ذلك — فيرى المستخدم عمليةً
     يظنّها سجلَّ بياناتٍ، ولا يعرف أنّ حذفها يمسّ المخزون ودفتر اليومية،
     ولا أين يذهب ليراجع القيد. صار كلُّ إذنٍ وقيدٍ معروضاً برقمه ورابطه.

     والمبالغ تمرّ بـ`CurrencySettings`، والتواريخ بـ`LanguageSettings`،
     فتتبع «العملات» و«اللغات» في إعدادات النظام. --}}

@section('title', __('sales_exchanges.view') . ' — ' . $exchange->code)

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $money = fn ($v) => \App\Support\CurrencySettings::format((float) $v);
        $num = fn ($v, $d = 0) => \App\Support\CurrencySettings::number($v, $d);
        $date = fn ($v) => $v ? \App\Support\LanguageSettings::formatDate($v) : '—';

        $items = $exchange->items;

        // القيدان يُطلبان برقم المرجع لا بمفتاحٍ محفوظ: العلاقة غير مخزَّنة
        // في `product_exchanges`، والمرجع هو `code` مضافاً إليه الاتجاه
        $entries = \App\Models\JournalEntry::whereIn('reference_number', [
            $exchange->code . '-OUT',
            $exchange->code . '-IN',
        ])->get()->keyBy('reference_number');
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.exchanges.edit')
                <a href="{{ route('product-exchanges.edit', $exchange->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> {{ __('sales_settings.ui.edit') }}
                </a>
            @endpermission

            @permission('sales.exchanges.print')
                <a href="{{ route('product-exchanges.print', $exchange->id) }}" class="btn btn-outline-info" target="_blank">
                    <i class="feather icon-printer"></i> {{ __('sales_settings.ui.print') }}
                </a>
            @endpermission

            <a href="{{ route('product-exchanges.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => $exchange->code,
            'icon' => 'repeat',
            'sub' => __('sales_exchanges.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_exchanges.title') => route('product-exchanges.index'),
                $exchange->code => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-exchanges.show',
            'title' => __('sales_exchanges.guide.show.title'),
            'lead' => __('sales_exchanges.guide.lead_show'),
            'open' => false,
            'blocks' => [
                __('sales_exchanges.guide.what_is.title') => [
                    __('sales_exchanges.guide.what_is.a'),
                    __('sales_exchanges.guide.what_is.b'),
                    __('sales_exchanges.guide.what_is.c'),
                ],
                __('sales_exchanges.guide.effects.title') => [
                    __('sales_exchanges.guide.effects.a'),
                    __('sales_exchanges.guide.effects.b'),
                ],
            ],
        ])

        {{-- ══════════ الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-package"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $num($items->count()) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.items.count') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-layers"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $num($items->sum('quantity')) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.items.quantity_total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--warning"><i class="feather icon-dollar-sign"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.25rem">{{ $money($exchange->total_value) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.fields.total_value') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-calendar"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.25rem">{{ $date($exchange->exchange_date) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.fields.exchange_date') }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- ══════════ البيانات ══════════ --}}
            <div class="col-lg-4 col-md-12">
                <div class="card sl-section">
                    <div class="sl-section__head">
                        <h6 class="sl-section__title">
                            <i class="feather icon-file-text"></i> {{ __('sales_exchanges.view') }}
                        </h6>
                    </div>

                    <div class="card-body">
                        <dl class="sl-dl">
                            <dt>{{ __('sales_exchanges.fields.code') }}</dt>
                            <dd class="sl-num">{{ $exchange->code }}</dd>

                            <dt>{{ __('sales_exchanges.fields.client') }}</dt>
                            <dd>{{ $exchange->client->trade_name ?? $exchange->client->name ?? '—' }}</dd>

                            <dt>{{ __('sales_exchanges.fields.storehouse') }}</dt>
                            <dd>{{ $exchange->storehouse->name ?? '—' }}</dd>

                            <dt>{{ __('sales_exchanges.fields.return_storehouse') }}</dt>
                            <dd>{{ $exchange->returnStorehouse->name ?? '—' }}</dd>

                            <dt>{{ __('sales_exchanges.fields.employee') }}</dt>
                            <dd>
                                {{ $exchange->employee
                                    ? trim(($exchange->employee->first_name ?? '') . ' ' . ($exchange->employee->last_name ?? ''))
                                    : '—' }}
                            </dd>

                            <dt>{{ __('sales_exchanges.fields.created_by') }}</dt>
                            <dd>{{ $exchange->createdByUser->name ?? '—' }}</dd>

                            <dt>{{ __('sales_exchanges.fields.created_at') }}</dt>
                            <dd>{{ $date($exchange->created_at) }}</dd>

                            @if ($exchange->notes)
                                <dt>{{ __('sales_exchanges.fields.notes') }}</dt>
                                <dd>{{ $exchange->notes }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- ══════════ صورة الإثبات ══════════ --}}
                <div class="card sl-section">
                    <div class="sl-section__head">
                        <h6 class="sl-section__title">
                            <i class="feather icon-camera"></i> {{ __('sales_exchanges.fields.image') }}
                        </h6>
                    </div>

                    <div class="card-body">
                        @if ($exchange->image)
                            <a href="{{ asset('storage/' . $exchange->image) }}" target="_blank">
                                <img src="{{ asset('storage/' . $exchange->image) }}"
                                     alt="{{ __('sales_exchanges.fields.image') }}" class="sl-proof">
                            </a>
                        @else
                            @include('sales::components.empty-state', [
                                'icon' => 'camera-off',
                                'title' => __('sales_exchanges.fields.no_image'),
                            ])
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-8 col-md-12">
                {{-- ══════════ الأصناف ══════════ --}}
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-package"></i> {{ __('sales_exchanges.items.title') }}
                        </h6>
                        <span class="sl-count">{{ $items->count() }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_exchanges.items.product') }}</th>
                                    <th>{{ __('sales_exchanges.items.reason') }}</th>
                                    <th class="text-center">{{ __('sales_exchanges.items.quantity') }}</th>
                                    <th class="text-center">{{ __('sales_exchanges.items.unit_price') }}</th>
                                    <th class="text-center">{{ __('sales_exchanges.items.total') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>
                                            <div class="sl-media">
                                                <span class="sl-media__thumb" aria-hidden="true"><i class="feather icon-box"></i></span>
                                                <div class="sl-media__body">
                                                    <span class="sl-media__title">{{ $item->product->name ?? '—' }}</span>
                                                    @if ($item->product?->code)
                                                        <span class="sl-media__sub sl-num">{{ $item->product->code }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="sl-media__title">{{ $item->exchangeReason->name ?? '—' }}</span>
                                            @if ($item->reason_notes)
                                                <span class="sl-media__sub">{{ $item->reason_notes }}</span>
                                            @endif
                                        </td>

                                        <td class="text-center sl-num">{{ $num($item->quantity) }}</td>
                                        <td class="text-center sl-num">{{ $money($item->unit_price) }}</td>
                                        <td class="text-center sl-num">{{ $money($item->total) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            @include('sales::components.empty-state', [
                                                'icon' => 'package',
                                                'title' => __('sales_exchanges.items.empty'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            @if ($items->count())
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">{{ __('sales_exchanges.fields.total_value') }}</th>
                                        <th class="text-center sl-num">{{ $money($exchange->total_value) }}</th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- ══════════ أثر العملية ══════════ --}}
                <div class="card sl-section">
                    <div class="sl-section__head">
                        <h6 class="sl-section__title">
                            <i class="feather icon-git-branch"></i> {{ __('sales_exchanges.effects.title') }}
                        </h6>
                    </div>

                    <div class="card-body">
                        <p class="sl-help mb-3">
                            <i class="feather icon-info"></i> {{ __('sales_exchanges.effects.hint') }}
                        </p>

                        <div class="sl-effects">
                            @php
                                $effects = [
                                    ['icon' => 'upload', 'label' => __('sales_exchanges.effects.permit_out'), 'value' => $exchange->outgoingPermit->number ?? null],
                                    ['icon' => 'download', 'label' => __('sales_exchanges.effects.permit_in'), 'value' => $exchange->incomingPermit->number ?? null],
                                    ['icon' => 'book-open', 'label' => __('sales_exchanges.effects.journal_out'), 'value' => $entries[$exchange->code . '-OUT']->reference_number ?? null],
                                    ['icon' => 'book-open', 'label' => __('sales_exchanges.effects.journal_in'), 'value' => $entries[$exchange->code . '-IN']->reference_number ?? null],
                                ];
                            @endphp

                            @foreach ($effects as $effect)
                                <div class="sl-effect sl-effect--{{ $effect['value'] ? 'on' : 'off' }}">
                                    <span class="sl-effect__icon" aria-hidden="true">
                                        <i class="feather icon-{{ $effect['icon'] }}"></i>
                                    </span>
                                    <span class="sl-effect__body">
                                        <span class="sl-effect__label">{{ $effect['label'] }}</span>
                                        <span class="sl-effect__value sl-num">
                                            {{ $effect['value'] ?: __('sales_exchanges.effects.none') }}
                                        </span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .sl-dl { margin: 0; }
        .sl-dl dt {
            font-size: .75rem;
            font-weight: 600;
            color: var(--app-muted, #6b7280);
            margin-block-start: .75rem;
        }
        .sl-dl dt:first-child { margin-block-start: 0; }
        .sl-dl dd { margin: .125rem 0 0; color: var(--app-text, #1f2937); }

        .sl-proof {
            display: block;
            width: 100%;
            max-height: 320px;
            object-fit: contain;
            border-radius: var(--sl-r, 8px);
            border: 1px solid var(--app-border, rgba(0, 0, 0, .09));
        }

        .sl-effects {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: .625rem;
        }
        .sl-effect {
            display: flex;
            align-items: center;
            gap: .625rem;
            padding: .625rem .75rem;
            border: 1px solid var(--app-border, rgba(0, 0, 0, .09));
            border-radius: var(--sl-r, 8px);
        }
        .sl-effect__icon {
            display: grid;
            place-items: center;
            width: 2rem;
            height: 2rem;
            flex: none;
            border-radius: var(--sl-r, 8px);
        }
        .sl-effect--on .sl-effect__icon {
            background: var(--app-success-soft, rgba(53, 180, 126, .12));
            color: var(--app-success, #35b47e);
        }
        .sl-effect--off .sl-effect__icon {
            background: var(--app-danger-soft, rgba(224, 79, 95, .12));
            color: var(--app-danger, #e04f5f);
        }
        .sl-effect__body { display: flex; flex-direction: column; min-width: 0; }
        .sl-effect__label { font-size: .75rem; color: var(--app-muted, #6b7280); }
        .sl-effect__value { font-weight: 600; color: var(--app-text, #1f2937); }
    </style>
@endsection
