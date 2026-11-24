{{--
    جدول الإشعارات الدائنة — يُستبدل وحده عند البحث والتنقّل بـAjax.

    نظير `sales::invoices.partials.table`.

    ما أُصلح:
      • *العملة*: كانت تُقرأ من `account_setting` — إعداد حسابٍ شخصيّ لكل
        مستخدم — ثم يُستبدل بالرمز صورةُ ريالٍ ثابتة مهما كانت العملة.
      • *التاريخ*: بصيغة اللغة النشطة لا بصيغة مكتوبة.
      • *«حذف»*: الإشعار الدائن مستندٌ محاسبيّ صدر وله قيد؛ لا يُحذف بل
        يُلغى بقيدٍ عاكس (`CreditNotes.cancel`) ويبقى في السجلّ. وزرّ
        الحذف كان يشير إلى مسارٍ لا يقبل GET.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $credits
--}}

@php
    // [الوسم، اللون] لكل حالة — بدل سلسلة `elseif`
    $states = [
        0 => [__('sales_credit_note.status.cancelled'), 'danger'],
        1 => [__('sales_credit_note.status.draft'), 'secondary'],
        2 => [__('sales_credit_note.status.pending'), 'warning'],
        3 => [__('sales_credit_note.status.approved'), 'success'],
        5 => [__('sales_credit_note.status.cancelled'), 'danger'],
    ];
@endphp

@if ($credits->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">
        @include('sales::components.per-page', ['onchange' => 'loadData(1)'])
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr>
                    <th style="width:16%">{{ __('sales_credit_note.filters.number') }}</th>
                    <th style="width:22%">{{ __('sales_invoice.table.client') }}</th>
                    <th>{{ __('branches.node_filter.column') }}</th>
                    <th style="width:16%">{{ __('sales_credit_note.source_invoice') }}</th>
                    <th style="width:20%">{{ __('sales_invoice.table.date') }}</th>
                    <th style="width:14%">{{ __('sales_invoice.table.total') }}</th>
                    <th class="text-center" style="width:10%">{{ __('sales_invoice.table.status') }}</th>
                    <th class="text-center" style="width:6%">{{ __('sales_invoice.table.actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($credits as $credit)
                    @php
                        [$stateLabel, $stateTone] = $states[$credit->status] ?? [__('sales_invoice.status.unknown'), 'dark'];

                        $clientName = $credit->client
                            ? ($credit->client->trade_name ?: trim($credit->client->first_name.' '.$credit->client->last_name))
                            : __('sales_invoice.table.unknown_client');

                        $isCancelled = in_array((int) $credit->status, [0, 5], true);
                    @endphp

                    <tr>
                        <td>
                            <div class="sl-media">
                                <span class="sl-media__thumb text-danger"><i class="feather icon-plus-circle"></i></span>
                                <div class="sl-media__body">
                                    <a href="{{ route('CreditNotes.show', $credit->id) }}" class="sl-media__title">
                                        {{ $credit->credit_number }}
                                    </a>
                                    <span class="sl-media__sub sl-num">#{{ $credit->id }}</span>
                                </div>
                            </div>
                        </td>

                        <td><span class="sl-media__sub">{{ $clientName }}</span></td>

                        {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                        <td>@include('partials.branch-node-path', ['branchId' => $credit->branch_id])</td>

                        <td>
                            @if ($credit->invoice)
                                @permission('sales.invoices.view')
                                    <a href="{{ route('invoices.show', $credit->invoice->id) }}" class="sl-code">
                                        {{ $credit->invoice->code }}
                                    </a>
                                @else
                                    <span class="sl-code">{{ $credit->invoice->code }}</span>
                                @endpermission
                            @else
                                <span class="sl-media__sub">—</span>
                            @endif
                        </td>

                        <td>
                            <span class="sl-num d-block">
                                {{ \App\Support\LanguageSettings::formatDate($credit->credit_date ?: $credit->created_at) }}
                            </span>
                            <span class="sl-media__sub">
                                <i class="feather icon-user"></i>
                                {{ __('sales_invoice.table.added_by', ['name' => $credit->createdBy->name ?? '—']) }}
                            </span>
                        </td>

                        <td>
                            <span class="sl-num">
                                {{ \App\Support\CurrencySettings::format($credit->grand_total) }}
                            </span>
                            @if (($credit->total_tax ?? 0) > 0)
                                <span class="sl-media__sub d-block">
                                    {{ __('sales_invoice.tax') }}:
                                    {{ \App\Support\CurrencySettings::format($credit->total_tax) }}
                                </span>
                            @endif
                        </td>

                        <td class="text-center">
                            <span class="badge badge-light-{{ $stateTone }}">{{ $stateLabel }}</span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        {{-- `data-bs-toggle` وحده: الصفحة تحمّل بوتستراب ٥
                                             (سطر ٦١ في `master`) وبوتستراب ٤ داخل
                                             `vendors.min.js` (سطر ٤٠١)، والوسمان معاً
                                             يجعلان الاثنين يعالجان النقرة نفسها. --}}
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="{{ __('sales_invoice.table.actions') }} {{ $credit->credit_number }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('CreditNotes.show', $credit->id) }}">
                                        <i class="feather icon-eye"></i> {{ __('sales_invoice.actions.show') }}
                                    </a>

                                    {{-- المطبوعات و«إرسال إلى العميل» لا مكان لهما هنا:
                                         كلاهما في شاشة العرض. --}}

                                    {{-- sales.credit_notes.cancel — الصلاحية نفسها التي يتحقّق منها
                                         CreditNotificationController::cancel() لا صلاحيات التعديل/الحذف القديمة --}}
                                    @permission('sales.credit_notes.cancel')
                                        @unless ($isCancelled)
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger cancel-credit" href="#"
                                               data-id="{{ $credit->id }}" data-code="{{ $credit->credit_number }}">
                                                <i class="feather icon-x-circle"></i> {{ __('sales_credit_note.actions.cancel') }}
                                            </a>
                                        @endunless
                                    @endpermission
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('sales::creted_note.partials.pagination', ['credits' => $credits])
@else
    @include('sales::components.empty-state', [
        'icon' => 'plus-circle',
        'title' => __('sales_credit_note.empty.title'),
        'hint' => __('sales_credit_note.empty.hint'),
    ])
@endif
