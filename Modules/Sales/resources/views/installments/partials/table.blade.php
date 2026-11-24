{{--
    صفوف جدول الاتفاقيات.

    الحالة تُقرأ من `effective_status` — مصدرٌ واحد بدل ثلاث خرائط
    متناقضة كانت في ثلاث شاشات (أرقام في `show`، ونصوص `active/unactive`
    في `agreement_installments`، و`paid/unpaid` في فلتر `index`)، ولا
    واحدةٌ منها توافق العمود.

    والمبالغ بعملة الاتفاقية عبر `$agreement->money()` — لا `ر.س` مثبَّتاً
    في القالب كما كان في عشرة مواضع.
--}}

<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>{{ __('sales_installments.table.agreement') }}</th>
                <th>{{ __('sales_installments.table.client') }}</th>
                <th class="text-center" style="width:150px">{{ __('sales_installments.table.financed') }}</th>
                <th style="width:190px">{{ __('sales_installments.table.progress') }}</th>
                <th style="width:150px">{{ __('sales_installments.table.next_due') }}</th>
                <th class="text-center" style="width:120px">{{ __('sales_installments.table.status') }}</th>
                <th class="text-center" style="width:150px">{{ __('sales_settings.ui.actions') }}</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($agreements as $agreement)
                @php
                    $status = $agreement->effective_status;

                    $tone = match ($status) {
                        'completed' => 'on',
                        'defaulted' => 'danger',
                        'cancelled' => 'off',
                        default => 'neutral',
                    };

                    // أقرب قسطٍ لم يكتمل — «ما الذي يستحقّ بعد؟»
                    $next = $agreement->details
                        ->reject(fn ($d) => $d->is_paid || $d->status === 'cancelled')
                        ->sortBy('due_date')
                        ->first();
                @endphp

                <tr>
                    <td>
                        <div class="sl-media">
                            <span class="sl-media__thumb" aria-hidden="true">
                                <i class="feather icon-credit-card"></i>
                            </span>
                            <div class="sl-media__body">
                                <a href="{{ route('installments.show', $agreement->id) }}" class="sl-media__title">
                                    {{ $agreement->code ?: '#'.$agreement->id }}
                                </a>
                                <span class="sl-media__sub">
                                    {{ \App\Support\CurrencySettings::number($agreement->installment_number, 0) }}
                                    ×
                                    {{ __('sales_installments.rates.'.$agreement->rate_key) }}
                                </span>
                            </div>
                        </div>
                    </td>

                    <td>
                        @if ($agreement->client)
                            <span class="sl-media__title">{{ $agreement->client->trade_name }}</span>
                            @if ($agreement->invoice)
                                <span class="sl-media__sub">
                                    {{ $agreement->invoice->code ?: '#'.$agreement->invoice->id }}
                                </span>
                            @endif
                        @else
                            <span class="sl-media__sub">{{ __('sales_settings.ui.none') }}</span>
                        @endif
                    </td>

                    <td class="text-center sl-num">{{ $agreement->money($agreement->financed_amount) }}</td>

                    {{-- شريط التحصيل: الرقم وحده لا يقول «كم بقي» بلمحة --}}
                    <td>
                        @php $progress = $agreement->progress; @endphp

                        <div style="height:6px;border-radius:999px;overflow:hidden;
                                    background:color-mix(in srgb, var(--app-muted, #6e6b7b) 18%, transparent)">
                            <div style="height:100%;width:{{ $progress }}%;
                                        background:var(--app-success, #28c76f)"></div>
                        </div>
                        <span class="sl-media__sub sl-num">
                            {{ $agreement->money($agreement->paid_amount) }} / {{ $progress }}%
                        </span>
                    </td>

                    <td>
                        @if ($next)
                            <span class="sl-num" style="font-size:.82rem">
                                {{ optional($next->due_date)->format('Y-m-d') }}
                            </span>
                            <span class="sl-media__sub sl-num">{{ $agreement->money($next->remaining_amount) }}</span>
                        @else
                            <span class="sl-media__sub">{{ __('sales_settings.ui.none') }}</span>
                        @endif
                    </td>

                    <td class="text-center">
                        <span class="sl-state sl-state--{{ $tone === 'danger' ? 'off' : $tone }}"
                              @if ($tone === 'danger')
                                  style="color:var(--app-danger, #ea5455);
                                         background:var(--app-danger-soft, rgba(234,84,85,.12))"
                              @endif>
                            {{ __('sales_installments.status.'.$status) }}
                        </span>

                        @if ($agreement->overdue_count > 0)
                            <span class="sl-media__sub sl-num">
                                {{ \App\Support\CurrencySettings::number($agreement->overdue_count, 0) }}
                                {{ __('sales_installments.detail_status.overdue') }}
                            </span>
                        @endif
                    </td>

                    <td class="text-center">
                        <div class="sl-actions">
                            <a href="{{ route('installments.show', $agreement->id) }}"
                               class="btn btn-outline-primary"
                               title="{{ __('sales_settings.ui.view') }}"
                               aria-label="{{ __('sales_settings.ui.view') }}">
                                <i class="feather icon-eye"></i>
                            </a>

                            @if ($agreement->invoice && Route::has('paymentsClient.create'))
                                @permission('sales.payments.create')
                                    {{-- السداد يذهب إلى سند القبض على الفاتورة —
                                         مسار المال الوحيد في النظام --}}
                                    <a href="{{ route('paymentsClient.create', ['id' => $agreement->invoice->id, 'type' => 'invoice']) }}"
                                       class="btn btn-outline-success"
                                       title="{{ __('sales_installments.actions.collect') }}"
                                       aria-label="{{ __('sales_installments.actions.collect') }}">
                                        <i class="feather icon-dollar-sign"></i>
                                    </a>
                                @endpermission
                            @endif

                            @permission('sales.installments.agreement')
                                <a href="{{ route('installments.edit', $agreement->id) }}"
                                   class="btn btn-outline-info"
                                   title="{{ __('sales_settings.ui.edit') }}"
                                   aria-label="{{ __('sales_settings.ui.edit') }}">
                                    <i class="feather icon-edit-2"></i>
                                </a>

                                <form action="{{ route('installments.destroy', $agreement->id) }}"
                                      method="POST" class="sl-delete-form d-inline"
                                      data-code="{{ $agreement->code ?: '#'.$agreement->id }}">
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
                            'icon' => 'credit-card',
                            'title' => ($hasFilters ?? false)
                                ? __('sales_settings.ui.no_results')
                                : __('sales_installments.empty'),
                            'hint' => ($hasFilters ?? false)
                                ? __('sales_settings.ui.no_results_hint')
                                : __('sales_installments.empty_hint'),
                            'action' => ($hasFilters ?? false)
                                ? '<a href="'.route('installments.index').'" class="btn btn-outline-secondary">'
                                    .'<i class="feather icon-x"></i> '.e(__('sales_settings.ui.filters_clear')).'</a>'
                                : '',
                        ])
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
