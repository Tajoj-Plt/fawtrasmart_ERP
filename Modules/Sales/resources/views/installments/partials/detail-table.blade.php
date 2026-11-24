{{--
    جدول الأقساط — مشترَكٌ بين تبويب الاتفاقية وشاشة المتابعة.

    الحالة من `display_status` واللون من `status_tone` — مصدرٌ واحد بدل
    ثلاث خرائط متناقضة كانت في ثلاث شاشات، اثنتان منها بلونٍ معكوسٍ على
    التسمية.

    الوسائط:
      rows           مجموعة `InstallmentDetail`
      showAgreement  إظهار عمود الاتفاقية (شاشة المتابعة) أم لا (داخلها)
      hasFilters     لتمييز «لا نتائج» عن «لا أقساط بعد»
--}}

@php
    $showAgreement = $showAgreement ?? false;

    $toneStyle = [
        'on' => 'color:var(--app-success, #28c76f);background:var(--app-success-soft, rgba(40,199,111,.12))',
        'danger' => 'color:var(--app-danger, #ea5455);background:var(--app-danger-soft, rgba(234,84,85,.12))',
        'warning' => 'color:var(--app-warning, #ff9f43);background:color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent)',
        'off' => 'color:var(--app-muted, #6e6b7b);background:color-mix(in srgb, var(--app-muted, #6e6b7b) 12%, transparent)',
        'neutral' => 'color:var(--app-info, #00cfe8);background:color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent)',
    ];
@endphp

<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th class="text-center" style="width:70px">{{ __('sales_installments.fields.number') }}</th>
                @if ($showAgreement)
                    <th>{{ __('sales_installments.table.agreement') }}</th>
                @endif
                <th style="width:130px">{{ __('sales_installments.fields.due_date') }}</th>
                <th class="text-center" style="width:130px">{{ __('sales_installments.fields.amount') }}</th>
                <th class="text-center" style="width:130px">{{ __('sales_installments.fields.paid_amount') }}</th>
                <th class="text-center" style="width:130px">{{ __('sales_installments.fields.remaining') }}</th>
                <th class="text-center" style="width:140px">{{ __('sales_installments.fields.late_fee_charged') }}</th>
                <th class="text-center" style="width:120px">{{ __('sales_installments.fields.status') }}</th>
                <th class="text-center" style="width:110px">{{ __('sales_settings.ui.actions') }}</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($rows as $row)
                @php
                    $agreement = $row->installment;
                    $state = $row->display_status;
                @endphp

                <tr>
                    <td class="text-center sl-num">{{ $row->installment_number ?: '—' }}</td>

                    @if ($showAgreement)
                        <td>
                            @if ($agreement)
                                <a href="{{ route('installments.show', $agreement->id) }}" class="sl-media__title">
                                    {{ $agreement->code ?: '#'.$agreement->id }}
                                </a>
                                <span class="sl-media__sub">{{ $agreement->client?->trade_name }}</span>
                            @else
                                <span class="sl-media__sub">{{ __('sales_settings.ui.none') }}</span>
                            @endif
                        </td>
                    @endif

                    <td>
                        <span class="sl-num" style="font-size:.82rem">
                            {{ optional($row->due_date)->format('Y-m-d') ?: '—' }}
                        </span>
                        @if ($row->days_late > 0)
                            <span class="sl-media__sub sl-num" style="color:var(--app-danger, #ea5455)">
                                {{ $row->days_late }} {{ __('sales_installments.fields.days_late') }}
                                @if ($row->chargeable_late_days > 0 && $row->chargeable_late_days !== $row->days_late)
                                    {{-- أيامُ السماح لا تُحتسب: العددان يختلفان فيُقالان --}}
                                    ({{ $row->chargeable_late_days }} {{ __('sales_installments.fields.chargeable_days') }})
                                @endif
                            </span>
                        @endif
                    </td>

                    <td class="text-center sl-num">{{ $row->money($row->amount) }}</td>
                    <td class="text-center sl-num">{{ $row->money($row->paid_amount) }}</td>
                    <td class="text-center sl-num">{{ $row->money($row->remaining_amount) }}</td>

                    {{-- رقمان لا واحد:

                         **المحمَّل** (`late_fee_amount`) ما رُحِّل فعلاً قيداً
                         على العميل — هو المطالَب به ويُجمَع مع مبلغ القسط في
                         شاشة السداد.

                         و**المستحقّ** (`late_fee_due`) تقديرٌ لما سيُحمَّل لو
                         شُغِّلت مهمّةُ الغرامات الآن. يُعرَض تحته صغيراً حين
                         يزيد عنه، فيُرى أثرُ السياسة قبل وقوعه.

                         وخلطُهما في رقمٍ واحد يجعل الشاشة تقول ما ليس في دفتر
                         الأستاذ. --}}
                    <td class="text-center sl-num">
                        @php
                            $charged = (float) $row->late_fee_amount;
                            $pending = round((float) $row->late_fee_due - $charged, 2);
                        @endphp

                        @if ($charged > 0)
                            <span style="color:var(--app-danger, #ea5455)">{{ $row->money($charged) }}</span>

                            @if ((float) $row->late_fee_paid > 0)
                                <span class="sl-media__sub">
                                    {{ __('sales_installments.fields.paid_amount') }}: {{ $row->money($row->late_fee_paid) }}
                                </span>
                            @endif
                        @else
                            <span class="sl-media__sub">—</span>
                        @endif

                        @if ($pending > 0.009)
                            <span class="sl-media__sub" style="color:var(--app-warning, #ff9f43)">
                                {{ __('sales_installments.fields.late_fee') }}: {{ $row->money($pending) }}
                            </span>
                        @endif
                    </td>

                    <td class="text-center">
                        <span class="sl-state" style="{{ $toneStyle[$row->status_tone] ?? $toneStyle['neutral'] }}">
                            {{ __('sales_installments.detail_status.'.$state) }}
                        </span>
                    </td>

                    <td class="text-center">
                        <div class="sl-actions">
                            {{-- التحصيل على القسط نفسه لا على الفاتورة: كان الرابط
                                 يرسل معرّف الفاتورة و`type=invoice`، فتفتح شاشةُ
                                 السداد على متبقّي الفاتورة كاملاً بدل مبلغ القسط. --}}
                            {{-- قسطٌ سُدِّد وبقيت عليه غرامةٌ محمَّلة يبقى قابلاً
                                 للتحصيل: `is_paid` تصف المبلغ لا المطالبة --}}
                            @if ($agreement?->invoice && Route::has('paymentsClient.create') && $row->status !== 'cancelled' && (! $row->is_paid || $row->late_fee_remaining > 0.009))
                                <a href="{{ route('paymentsClient.create', ['id' => $row->id, 'type' => 'installment_detail']) }}"
                                   class="btn btn-outline-success"
                                   title="{{ __('sales_installments.actions.collect') }}"
                                   aria-label="{{ __('sales_installments.actions.collect') }}">
                                    <i class="feather icon-dollar-sign"></i>
                                </a>
                            @endif

                            @if (! $row->is_paid && $row->status !== 'cancelled' && (float) $row->paid_amount <= 0)
                                {{-- الإلغاء لا الحذف: الجدول أثرٌ لا يُمحى --}}
                                <form action="{{ route('installments.detail.cancel', $row->id) }}"
                                      method="POST" class="sl-cancel-detail d-inline"
                                      data-number="{{ $row->installment_number }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger"
                                            title="{{ __('sales_installments.actions.cancel_detail') }}"
                                            aria-label="{{ __('sales_installments.actions.cancel_detail') }}">
                                        <i class="feather icon-slash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showAgreement ? 9 : 8 }}">
                        @include('sales::components.empty-state', [
                            'icon' => 'list',
                            'title' => ($hasFilters ?? false)
                                ? __('sales_settings.ui.no_results')
                                : __('sales_installments.schedule_empty'),
                            'hint' => ($hasFilters ?? false) ? __('sales_settings.ui.no_results_hint') : null,
                        ])
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
