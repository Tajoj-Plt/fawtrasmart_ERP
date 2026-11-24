{{--
    جدول فواتير المبيعات — يُستبدل وحده عند البحث والتنقّل بـAjax.

    نظير `stock::store_permits_management.partials.table`.

    ما أُصلح هنا:
      • *استعلامان داخل الحلقة*: كل صفّ كان يسأل عن مرتجع فاتورته
        (`Invoice::where('type','returned')…`) ويجلب مدفوعاتها ثم لا
        يستعملها — واحدٌ وعشرون استعلاماً في صفحةٍ من عشرة صفوف. صار
        المرتجعُ خريطةً تُحسَب مرّةً في المتحكّم (`returnedIds`).
      • *العملة*: كانت تُقرأ من `account_setting` — إعداد حسابٍ شخصيّ لكل
        مستخدم — ثمّ يُستبدل بالرمز صورةُ ريالٍ ثابتة مهما كانت العملة.
        صارت من «العملات»، والمبالغ تُنسَّق بكسورها وفواصلها.
      • *التاريخ*: كان بصيغة `account_setting->time_formula`، وصار بصيغة
        اللغة النشطة من شاشة «اللغات».
      • *الحذف بلا تأكيد*: كان `<form>` مخفيّاً داخل كل صفّ يُرسَل من
        `onclick` بلا سؤال. صار زرّاً يفوّض إلى مؤكِّد الشاشة.
      • *«تعديل» و«حذف» معروضان دائماً*: والمتحكّم يردّهما برسالةٍ نصّية
        لأن الفاتورة لا تُعدَّل ولا تُحذف بعد إصدارها (نظام الفاتورة
        الإلكترونية). البديل المتاح — مرتجع أو إشعار دائن — هو ما يُعرض.
      • *`DataTables_Table_0`*: بنية DataTables كاملةً بلا مكتبة
        DataTables، ومُنتقي عدد السجلّات مكتوباً بيده.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $invoices
    @param array<int, true> $returnedIds  معرّفات الفواتير التي لها مرتجع
--}}

@php
    $returnedIds = (isset($returnedIds) && is_array($returnedIds)) ? $returnedIds : [];

    // [الوسم، اللون] لكل حالة سداد — بدل سلسلة `elseif` تعيد الشارة أربع مرّات
    $states = [
        1 => [__('sales_invoice.status.paid'), 'success'],
        2 => [__('sales_invoice.status.partial'), 'info'],
        3 => [__('sales_invoice.status.unpaid'), 'danger'],
        4 => [__('sales_invoice.status.received'), 'secondary'],
    ];

    $canPayAll = hasPermission('data_scope_all'); // ✅ hasPermission('data_scope_all') بدلاً من صلاحية sales.payments.create القديمة
    $canPayOwn = hasPermission('sales.payments.create');
    $user = auth()->user();
@endphp

@if ($invoices->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">
        @include('sales::components.per-page', ['onchange' => 'loadData(1)'])
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr>
                    <th style="width:18%">{{ __('sales_invoice.table.number') }}</th>
                    <th style="width:24%">{{ __('sales_invoice.table.client') }}</th>
                    <th>{{ __('branches.node_filter.column') }}</th>
                    <th style="width:22%">{{ __('sales_invoice.table.date') }}</th>
                    <th style="width:18%">{{ __('sales_invoice.table.total') }}</th>
                    <th class="text-center" style="width:12%">{{ __('sales_invoice.table.status') }}</th>
                    <th class="text-center" style="width:6%">{{ __('sales_invoice.table.actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($invoices as $invoice)
                    @php
                        [$stateLabel, $stateTone] = $states[$invoice->payment_status] ?? [__('sales_invoice.status.unknown'), 'dark'];

                        // المرتجعُ (أو الإشعار الدائن) أطفأ ما بقي — لا «غير مدفوعة» بلا مستحق
                        [$stateLabel, $stateTone] = match ($invoice->returnSettlementState()) {
                            'returned' => [__('sales_invoice.status.fully_returned'), 'secondary'],
                            'settled' => [__('sales_invoice.status.settled'), 'success'],
                            default => [$stateLabel, $stateTone],
                        };

                        $hasReturn = isset($returnedIds[$invoice->id]);
                        $netDue = ($invoice->due_value ?? 0) - ($invoice->returned_payment ?? 0);

                        $clientName = $invoice->client
                            ? ($invoice->client->trade_name ?: trim($invoice->client->first_name.' '.$invoice->client->last_name))
                            : __('sales_invoice.table.unknown_client');

                        // صلاحية الدفع: الكلّ، أو الخاصّة به وحدها
                        $canAddPayment = $canPayAll
                            || ($canPayOwn && ($invoice->created_by == $user->id || $invoice->employee_id == $user->employee_id));

                        /* مهلةُ السداد — تُحسب مرّةً هنا ويقرؤها عمودُ الحالة.
                           `null` حين لا مهلة، فلا شارةَ ولا سطرَ فارغ. */
                        $terms = \App\Support\PaymentTerms::of($invoice);
                    @endphp

                    <tr>
                        <td>
                            <div class="sl-media">
                                <span class="sl-media__thumb text-primary"><i class="feather icon-file-text"></i></span>
                                <div class="sl-media__body">
                                    <a href="{{ route('invoices.show', $invoice->id) }}" class="sl-media__title">
                                        {{ $invoice->code }}
                                    </a>
                                    <span class="sl-media__sub sl-num">#{{ $invoice->id }}</span>
                                </div>
                            </div>
                        </td>

                        <td><span class="sl-media__sub">{{ $clientName }}</span></td>

                        {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                        <td>@include('partials.branch-node-path', ['branchId' => $invoice->branch_id])</td>

                        <td>
                            <span class="sl-num d-block">
                                {{ \App\Support\LanguageSettings::formatDate($invoice->created_at, true) }}
                            </span>
                            <span class="sl-media__sub">
                                <i class="feather icon-user"></i>
                                {{ __('sales_invoice.table.added_by', ['name' => $invoice->createdByUser->name ?? '—']) }}
                            </span>
                            @if ($invoice->employee)
                                <span class="sl-media__sub d-block">
                                    {{ __('sales_invoice.table.sales_person', ['name' => $invoice->employee->first_name ?? '—']) }}
                                </span>
                            @endif
                        </td>

                        <td>
                            <span class="sl-num">
                                {{ \App\Support\CurrencySettings::format($invoice->grand_total ?? $invoice->total) }}
                            </span>
                            {{-- «مرتجع» — ما أرجعه مرتجعٌ أو إشعارٌ دائن، وسطرُه يسكت عند الصفر --}}
                            @if (($invoice->returned_payment ?? 0) > 0)
                                <span class="sl-media__sub d-block">
                                    {{ __('sales_invoice.table.returned_amount', ['amount' => \App\Support\CurrencySettings::format($invoice->returned_payment)]) }}
                                </span>
                            @endif
                            {{-- المستحقُّ بعد المرتجع لا قبله: كان يظهر «المستحق: ٠٫٠٠» لفاتورةٍ أرجعها العميل كلَّها --}}
                            @if ($netDue >= 0.005)
                                <span class="sl-media__sub d-block text-danger">
                                    {{ __('sales_invoice.table.due', ['amount' => \App\Support\CurrencySettings::format($netDue)]) }}
                                </span>
                            @endif
                        </td>

                        <td class="text-center">
                            <span class="badge badge-light-{{ $stateTone }}">{{ $stateLabel }}</span>

                            {{-- شارةُ الاستحقاق — تظهر وحدَها حين يبقى مستحقٌّ
                                 وتُخفى متى سُدِّدت الفاتورة أو أُرجعت: «متأخّرة»
                                 على فاتورةٍ مدفوعة إنذارٌ كاذب يُفقد الشارةَ
                                 معناها. --}}
                            @if ($terms && $terms->isAlert)
                                <span class="sl-media__sub d-block">
                                    <span class="badge badge-light-{{ $terms->tone() }}"
                                          title="{{ $terms->summary() }}">
                                        <i class="feather {{ $terms->icon() }}"></i> {{ $terms->label() }}
                                    </span>
                                </span>
                            @elseif ($terms)
                                <span class="sl-media__sub d-block">{{ $terms->summary() }}</span>
                            @endif

                            @if ($hasReturn)
                                <span class="sl-media__sub d-block">
                                    <span class="badge badge-light-danger">{{ __('sales_invoice.status.returned') }}</span>
                                </span>
                            @endif
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        {{-- `data-bs-toggle` وحده: الصفحة تحمّل بوتستراب ٥
                                             (سطر ٦١ في `master`) وبوتستراب ٤ داخل
                                             `vendors.min.js` (سطر ٤٠١)، والوسمان معاً
                                             يجعلان الاثنين يعالجان النقرة نفسها. --}}
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="{{ __('sales_invoice.table.actions') }} {{ $invoice->code }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('invoices.show', $invoice->id) }}">
                                        <i class="feather icon-eye"></i> {{ __('sales_invoice.actions.show') }}
                                    </a>

                                    <div class="dropdown-divider"></div>

                                    {{-- المطبوعات و«إرسال إلى العميل» لا مكان لهما هنا:
                                         كلاهما في شاشة العرض حيث يُنتقى القالب
                                         ويُعايَن قبل الطباعة أو الإرسال. --}}

                                    {{-- فاتورةٌ لها مرتجع لا تقبل تحصيلاً.

                                         `$hasReturn` كانت تُحسَب في هذا الصفّ
                                         ولا يستعملها شيء: يُعرَض «تسجيل دفعة»
                                         على فاتورةٍ رُدَّت بضاعتها وعُكس قيدها،
                                         فيقبض المستخدمُ ثمن ما رُدّ. --}}
                                    @if ($canAddPayment && ! $invoice->is_paid)
                                        @permission('sales.payments.create')
                                            <div class="dropdown-divider"></div>

                                            @if ($hasReturn)
                                                <span class="dropdown-item disabled text-muted"
                                                      title="{{ __('sales_payment.blocked.has_return_short') }}">
                                                    <i class="feather icon-slash"></i>
                                                    {{ __('sales_invoice.actions.add_payment') }}
                                                </span>
                                            @else
                                                <a class="dropdown-item" href="{{ route('paymentsClient.create', ['id' => $invoice->id]) }}">
                                                    <i class="feather icon-credit-card"></i> {{ __('sales_invoice.actions.add_payment') }}
                                                </a>
                                            @endif
                                        @endpermission
                                    @endif

                                    @permission('sales.invoices.delete')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-invoice" href="#"
                                           data-id="{{ $invoice->id }}" data-code="{{ $invoice->code }}">
                                            <i class="feather icon-trash-2"></i> {{ __('sales_invoice.actions.delete') }}
                                        </a>
                                    @endpermission
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('sales::invoices.partials.pagination', ['invoices' => $invoices])
@else
    @include('sales::components.empty-state', [
        'icon' => 'file-text',
        'title' => __('sales_invoice.empty.title'),
        'hint' => __('sales_invoice.empty.hint'),
    ])
@endif
