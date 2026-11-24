@extends('sales::master')

{{--
    شاشة الاتفاقية.

    ══════════════════ ما كان ══════════════════

    • **زرّ حذفٍ ميّت**: `href="#"` بـ`data-target="#modal_DELETE…"`
      ولا نافذةَ بهذا المعرّف في الملفّ كلِّه.

    • **`data-toggle` (BS4) للنافذة و`data-bs-toggle` (BS5) للتبويبات**
      في القالب نفسه — واحدةٌ منهما على الأكثر تعمل.

    • **حالة القسط مقلوبة**: `status == 1` تخرج خضراء بنصّ «غير مكتمل»،
      و`== 2` صفراء بنصّ «مكتمل».

    • **`due_value` يُطبع مرّتين** بعنوانين مختلفين، و`grand_total` بعنوان
      ثالث — ثلاثةُ أرقامٍ اثنان منها متطابقان.

    • **`ر.س` مثبَّتٌ في خمسة مواضع** مهما كانت عملة الفاتورة.

    • **دوريّة السداد بثلاث قيمٍ من خمس**، و`2` مترجَمةٌ «يومي» هنا
      و«أسبوعي» في شاشة الإنشاء.

    • **`$installment->invoice->client->trade_name` بلا فحصٍ للعدم** في
      ستّة مواضع، ومع فحصٍ في موضعين — فأمرٌ بلا عميل يسقط بـ500.

    • `<a href="#">` مكان رابط العميل، مع أن `route('clients.show', …)`
      مستعمَلٌ صحيحاً بعده باثنين وعشرين سطراً.
--}}

@section('title', __('sales_installments.show_title', ['code' => $agreement->code]))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $tab = request('tab', 'summary');
        $status = $agreement->effective_status;
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @if ($agreement->invoice && Route::has('paymentsClient.create'))
                <a href="{{ route('paymentsClient.create', ['id' => $agreement->invoice->id, 'type' => 'invoice']) }}"
                   class="btn btn-primary" title="{{ __('sales_installments.actions.collect_hint') }}">
                    <i class="feather icon-dollar-sign"></i> {{ __('sales_installments.actions.collect') }}
                </a>
            @endif

            <form method="POST" action="{{ route('installments.sync', $agreement->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary"
                        title="{{ __('sales_installments.actions.sync_hint') }}">
                    <i class="feather icon-refresh-cw"></i> {{ __('sales_installments.actions.sync') }}
                </button>
            </form>

            <div class="dropdown">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="feather icon-printer"></i> {{ __('sales_installments.actions.print') }}
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="{{ route('installments.print', $agreement->id) }}"
                       target="_blank" rel="noopener">
                        <i class="feather icon-printer"></i> {{ __('sales_installments.actions.print') }}
                    </a>
                    <a class="dropdown-item" href="{{ route('installments.pdf', $agreement->id) }}"
                       target="_blank" rel="noopener">
                        <i class="feather icon-file-text"></i> {{ __('sales_installments.actions.pdf') }}
                    </a>

                    @if ($templates->isNotEmpty())
                        <div class="dropdown-divider"></div>
                        @foreach ($templates as $template)
                            <a class="dropdown-item"
                               href="{{ route('installments.print', ['id' => $agreement->id, 'template' => $template->id]) }}"
                               target="_blank" rel="noopener">
                                <i class="feather icon-layout"></i> {{ $template->name }}
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>

            <a href="{{ route('installments.edit', $agreement->id) }}" class="btn btn-outline-info">
                <i class="feather icon-edit-2"></i> {{ __('sales_settings.ui.edit') }}
            </a>

            <a href="{{ route('installments.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_installments.show_title', ['code' => $agreement->code]),
            'icon' => 'credit-card',
            'sub' => $agreement->client?->trade_name ?: __('sales_installments.show_subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_installments.title') => route('installments.index'),
                $agreement->code => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        @include('sales::components.guide', [
            'key' => 'installments.show',
            'open' => false,
            'title' => __('sales_installments.guide.show.title'),
            'lead' => __('sales_installments.guide.show.lead'),
            'blocks' => [
                __('sales_installments.guide.money_flow.title') => [
                    __('sales_installments.guide.money_flow.b'),
                    __('sales_installments.guide.money_flow.c'),
                    __('sales_installments.guide.money_flow.d'),
                    __('sales_installments.guide.money_flow.e'),
                ],
                __('sales_installments.guide.show.sync.title') => [
                    __('sales_installments.guide.show.sync.a'),
                ],
                __('sales_installments.guide.show.late.title') => [
                    __('sales_installments.guide.show.late.a'),
                    __('sales_installments.guide.show.late.b'),
                    __('sales_installments.guide.show.late.c'),
                ],
                __('sales_installments.guide.statuses.title') => [
                    __('sales_installments.guide.statuses.a'),
                    __('sales_installments.guide.statuses.c'),
                ],
            ],
        ])

        {{-- ══════════ شريط الحالة والتحصيل ══════════ --}}
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:1rem">
                    <div class="d-flex align-items-center" style="gap:.75rem;flex-wrap:wrap">
                        <span class="sl-state sl-state--{{ $status === 'completed' ? 'on' : ($status === 'cancelled' ? 'off' : 'neutral') }}"
                              @if ($status === 'defaulted')
                                  style="color:var(--app-danger, #ea5455);
                                         background:var(--app-danger-soft, rgba(234,84,85,.12))"
                              @endif>
                            {{ __('sales_installments.status.'.$status) }}
                        </span>

                        <span class="sl-media__sub sl-num">
                            {{ $agreement->money($agreement->paid_amount) }}
                            /
                            {{ $agreement->money($agreement->financed_amount) }}
                            — {{ $agreement->progress }}%
                        </span>

                        @if ($agreement->late_fees_outstanding > 0)
                            {{-- الغرامةُ ذمّةٌ خارج جدول الأقساط، فلا تدخل شريطَ
                                 التقدّم ولا نسبتَه — تُقال إلى جانبه --}}
                            <span class="sl-state" style="color:var(--app-warning, #ff9f43);
                                         background:color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent)">
                                {{ __('sales_installments.fields.late_fee_outstanding') }}:
                                {{ $agreement->money($agreement->late_fees_outstanding) }}
                            </span>
                        @endif

                        @if ($agreement->overdue_count > 0)
                            <span class="sl-state" style="color:var(--app-danger, #ea5455);
                                         background:var(--app-danger-soft, rgba(234,84,85,.12))">
                                {{ \App\Support\CurrencySettings::number($agreement->overdue_count, 0) }}
                                {{ __('sales_installments.detail_status.overdue') }}
                            </span>
                        @endif
                    </div>

                    <div class="d-flex" style="gap:.5rem;flex-wrap:wrap">
                        @if ($agreement->invoice && Route::has('invoices.show'))
                            <a href="{{ route('invoices.show', $agreement->invoice->id) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="feather icon-file-text"></i> {{ __('sales_installments.actions.open_invoice') }}
                            </a>
                        @endif

                        @if ($agreement->client && Route::has('clients.show'))
                            <a href="{{ route('clients.show', $agreement->client->id) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="feather icon-user"></i> {{ __('sales_installments.actions.open_client') }}
                            </a>
                        @endif
                    </div>
                </div>

                <div style="height:8px;border-radius:999px;overflow:hidden;margin-top:.9rem;
                            background:color-mix(in srgb, var(--app-muted, #6e6b7b) 18%, transparent)">
                    <div style="height:100%;width:{{ $agreement->progress }}%;
                                background:var(--app-success, #28c76f)"></div>
                </div>
            </div>
        </div>

        {{-- ══════════ التبويبات: روابط `?tab=` لا `data-toggle` ══════════ --}}
        <nav class="sl-tabs" aria-label="{{ __('sales_installments.tabs.summary') }}">
            @foreach (['summary' => 'file-text', 'schedule' => 'list', 'activity' => 'activity'] as $key => $icon)
                <a class="sl-tab {{ $tab === $key ? 'is-active' : '' }}"
                   href="{{ route('installments.show', ['id' => $agreement->id, 'tab' => $key]) }}">
                    <i class="feather icon-{{ $icon }}"></i>
                    {{ __('sales_installments.tabs.'.$key) }}

                    @if ($key === 'schedule')
                        <span class="sl-count sl-num">{{ $agreement->details->count() }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        {{-- ══════════ الملخّص ══════════ --}}
        @if ($tab === 'summary')
            <div class="row">
                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title"><i class="feather icon-dollar-sign"></i>
                                {{ __('sales_installments.sections.money') }}</h6>
                        </div>
                        <div class="card-body">
                            @include('sales::installments.partials.facts', ['rows' => [
                                __('sales_installments.fields.total_amount') => $agreement->money($agreement->total_amount),
                                __('sales_installments.fields.down_payment') => $agreement->money($agreement->down_payment),
                                __('sales_installments.fields.principal_amount') => $agreement->money($agreement->principal_amount),
                                __('sales_installments.fields.profit_amount') => (float) $agreement->profit_rate > 0
                                    ? $agreement->money($agreement->profit_amount).' ('.$agreement->profit_rate.'%)'
                                    : null,
                                __('sales_installments.fields.admin_fees') => (float) $agreement->admin_fees > 0
                                    ? $agreement->money($agreement->admin_fees) : null,
                                __('sales_installments.fields.tax_amount') => (float) $agreement->tax_rate > 0
                                    ? $agreement->money($agreement->tax_amount)
                                        .' ('.rtrim(rtrim(number_format((float) $agreement->tax_rate, 2), '0'), '.').'%'
                                        .($agreement->tax?->name ? ' — '.$agreement->tax->name : '').')'
                                    : null,
                                __('sales_installments.fields.financed_amount') => $agreement->money($agreement->financed_amount),
                                __('sales_installments.fields.remaining') => $agreement->money($agreement->remaining_amount),
                            ]])

                            {{-- الدفعة المقدَّمة تخصم من الممول لكنّها لا تُنشئ سند قبض،
                                 و`applyPayments()` تعدّها مستحقّةً قبل الأقساط. فما لم
                                 تُحصَّل، ذهب أوّلُ سندٍ إليها وبقي القسط الأوّل «غير
                                 مسدَّد» بلا سببٍ ظاهر. تُقال الحالة هنا صراحةً. --}}
                            {{-- المقدَّمة تُقبَض وتُقيَّد الآن لحظةَ حفظ الاتفاقية
                                 (`InstallmentDownPayment`)، فالحالةُ المعتادة أن
                                 تكون محصَّلة. وتبقى رسالةُ «لم تُحصَّل» لاتفاقيات
                                 أُنشئت قبل ذلك، أو لمن أُلغي سندُها. --}}
                            @php
                                $downVoucher = (float) $agreement->down_payment > 0
                                    ? \App\Services\Installments\InstallmentDownPayment::existingFor($agreement)
                                    : null;
                            @endphp

                            @if ($downVoucher)
                                <div class="alert alert-success mb-0 mt-2">
                                    <i class="feather icon-check-circle"></i>
                                    {{ __('sales_installments.down_payment.posted', [
                                        'amount' => $agreement->money($downVoucher->amount),
                                        'reference' => $downVoucher->reference_number ?: ('#'.$downVoucher->id),
                                    ]) }}
                                </div>
                            @endif

                            @if ((float) $agreement->down_payment > 0 && (float) $agreement->down_payment_remaining > 0)
                                <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0 mt-2">
                                    <span>
                                        <i class="feather icon-alert-triangle"></i>
                                        {{ __('sales_installments.down_payment.uncollected', [
                                            'amount' => $agreement->money($agreement->down_payment_remaining),
                                        ]) }}
                                    </span>

                                    @if ($agreement->invoice && Route::has('paymentsClient.create'))
                                        <a href="{{ route('paymentsClient.create', ['id' => $agreement->id, 'type' => 'installment_down']) }}"
                                           class="btn btn-sm btn-warning">
                                            <i class="feather icon-dollar-sign"></i>
                                            {{ __('sales_installments.down_payment.collect') }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title"><i class="feather icon-calendar"></i>
                                {{ __('sales_installments.sections.schedule') }}</h6>
                        </div>
                        <div class="card-body">
                            @include('sales::installments.partials.facts', ['rows' => [
                                __('sales_installments.fields.installment_number') => \App\Support\CurrencySettings::number($agreement->installment_number, 0),
                                __('sales_installments.fields.installment_amount') => $agreement->money($agreement->installment_amount),
                                __('sales_installments.fields.payment_rate') => __('sales_installments.rates.'.$agreement->rate_key),
                                __('sales_installments.fields.first_due_date') => optional($agreement->first_due_date)->format('Y-m-d'),
                                __('sales_installments.fields.last_due_date') => optional($agreement->last_due_date)->format('Y-m-d'),
                                __('sales_installments.fields.payment_method') => $agreement->paymentMethod?->name,
                                __('sales_installments.fields.currency') => $agreement->currency_code,
                                __('sales_installments.fields.late_fee_charged') => $agreement->late_fees_charged > 0
                                    ? $agreement->money($agreement->late_fees_charged) : null,
                                __('sales_installments.fields.late_fee_outstanding') => $agreement->late_fees_outstanding > 0
                                    ? $agreement->money($agreement->late_fees_outstanding) : null,
                                __('sales_installments.fields.late_fee') => $agreement->late_fees_due > $agreement->late_fees_charged
                                    ? $agreement->money(round($agreement->late_fees_due - $agreement->late_fees_charged, 2))
                                    : null,
                                __('sales_installments.fields.created_by') => $agreement->creator?->name,
                                __('sales_installments.fields.created_at') => optional($agreement->created_at)->format('Y-m-d H:i'),
                            ]])

                            @if ($agreement->note)
                                <div class="sl-section" style="margin-top:1rem">
                                    <div class="sl-section__head">
                                        <i class="feather icon-align-left"></i>
                                        <h6 class="sl-section__title">{{ __('sales_installments.fields.note') }}</h6>
                                    </div>
                                    <p style="margin:0;white-space:pre-line">{{ $agreement->note }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══════════ جدول الأقساط ══════════ --}}
        @if ($tab === 'schedule')
            <div class="card sl-table-card">
                <div class="sl-table-head">
                    <h6 class="sl-table-head__title">
                        <i class="feather icon-list"></i> {{ __('sales_installments.tabs.schedule') }}
                    </h6>
                    <span class="sl-count sl-num">{{ $agreement->details->count() }}</span>
                </div>

                @include('sales::installments.partials.detail-table', [
                    'rows' => $agreement->details,
                    'showAgreement' => false,
                ])
            </div>
        @endif

        {{-- ══════════ سجلّ النشاطات ══════════ --}}
        @if ($tab === 'activity')
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title"><i class="feather icon-activity"></i>
                        {{ __('sales_installments.tabs.activity') }}</h6>
                </div>
                <div class="card-body">
                    @forelse ($timeline as $date => $entries)
                        <div class="sl-section">
                            <div class="sl-section__head">
                                <i class="feather icon-calendar"></i>
                                <h6 class="sl-section__title sl-num">{{ $date }}</h6>
                            </div>

                            @foreach ($entries as $entry)
                                <div class="d-flex" style="gap:.75rem;padding:.5rem 0">
                                    <span class="sl-media__thumb" aria-hidden="true" style="width:32px;height:32px">
                                        <i class="feather icon-clock"></i>
                                    </span>
                                    <div style="min-width:0">
                                        <span class="sl-media__title">
                                            {{ $entry->user->name ?? __('sales_settings.ui.none') }}
                                        </span>
                                        <span class="sl-media__sub" style="white-space:normal">
                                            {!! \Illuminate\Support\Str::markdown(strip_tags((string) $entry->description)) !!}
                                        </span>
                                        <span class="sl-media__sub sl-num">
                                            {{ optional($entry->created_at)->format('H:i') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        @include('sales::components.empty-state', [
                            'icon' => 'activity',
                            'title' => __('sales_installments.log.empty'),
                        ])
                    @endforelse
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            $('.sl-cancel-detail').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);

                Swal.fire({
                    title: @json(__('sales_settings.ui.confirm_delete_title')),
                    text: @json(__('sales_installments.messages.confirm_cancel_detail'))
                        .replace(':number', form.data('number')),
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
