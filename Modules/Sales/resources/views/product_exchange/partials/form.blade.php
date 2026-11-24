{{--
    حقولُ نموذج التبديل — تشترك فيها شاشتا الإنشاء والتعديل.

    كانت الشاشتان قالبين لا يتشابهان: الإنشاءُ على بطاقات القالب القديمة
    بألوانٍ مكتوبةٍ بالسداسي (أخضر `#eafff1` وأحمر `#fff3f3`) وصناديقَ
    متقطّعة الحدود، والتعديلُ على نظام `sl-`. ومن انتقل من إحداهما إلى
    الأخرى وجد الحقولَ نفسَها بترتيبٍ آخر وأسماءٍ أخرى. صارتا قالباً واحداً،
    فما يُصلَح في إحداهما يُصلَح في الاثنتين.

    الأسماءُ والمعرِّفات كما يقرؤها `ProductExchangeController`:
    `client_id`، `employee_id`، `exchange_date`، `storehouse_id`،
    `return_storehouse_id`، `notes`، `image`، `items[i][…]`.

    الوسائط (من `formContext()`):
      exchange     العملية المعدَّلة، أو `null` في الإنشاء
      clients      العملاء المتاحون
      employees    الموظفون — لمن لا قيدَ عليه وحده
      storehouses  المستودعات المتاحة
      storehouse   المستودع الافتراضي للصرف
      products     الأصناف
      reasons      أسباب التبديل [id => label]
      isEmployee   هل المستخدم مقصورٌ على مستودعاته
--}}
@php
    $isEdit = (bool) ($exchange ?? null);
    $money = fn ($v) => \App\Support\CurrencySettings::format((float) $v);

    $selectedClient = old('client_id', $exchange->client_id ?? request()->query('client_id'));
    $selectedEmployee = old('employee_id', $exchange->employee_id ?? auth()->user()->employee_id);
    $selectedOut = old('storehouse_id', $exchange->storehouse_id ?? optional($storehouse ?? null)->id);

    /* مستودعُ الاسترجاع الافتراضي أوّلُ مستودعٍ **غير** مستودع الصرف: كانت
       القائمتان تفتحان على الخيار الأوّل نفسِه، فأوّلُ ما يراه المستخدم
       نموذجٌ مرفوضٌ قبل أن يلمسه. */
    $selectedBack = old('return_storehouse_id', $exchange->return_storehouse_id ?? null)
        ?: optional($storehouses->first(fn ($house) => (string) $house->id !== (string) $selectedOut))->id;

    $items = $isEdit ? $exchange->items : collect([null]);
@endphp

<div class="row">
    <div class="col-lg-8 col-md-12">

        {{-- ══════════ بيانات العملية ══════════ --}}
        <div class="card sl-section">
            <div class="sl-section__head">
                <h6 class="sl-section__title">
                    <i class="feather icon-file-text"></i> {{ __('sales_exchanges.form.details') }}
                </h6>
                @if ($isEdit)
                    <span class="sl-count sl-num">{{ $exchange->code }}</span>
                @endif
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="client_id">
                                {{ __('sales_exchanges.fields.client') }} <span class="sl-req"></span>
                            </label>
                            <select id="client_id" name="client_id" class="form-control px-select2" required
                                    data-placeholder="{{ __('sales_exchanges.form.pick_client') }}">
                                <option value=""></option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) $selectedClient === (string) $client->id)>
                                        {{ $client->trade_name ?: trim($client->first_name . ' ' . $client->last_name) }}@if ($client->code) ({{ $client->code }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="exchange_date">
                                {{ __('sales_exchanges.fields.exchange_date') }} <span class="sl-req"></span>
                            </label>
                            <input type="date" id="exchange_date" name="exchange_date" required class="form-control"
                                   value="{{ old('exchange_date', $isEdit ? optional($exchange->exchange_date)->format('Y-m-d') : date('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group mb-0">
                            <label for="employee_id">
                                {{ __('sales_exchanges.form.responsible') }} <span class="sl-req"></span>
                            </label>

                            @if (! empty($isEmployee) && auth()->user()->employee_id && ! isset($employees))
                                {{-- المندوبُ يسجّل على نفسه — لا قائمةَ يختار منها غيرَه --}}
                                <input type="hidden" name="employee_id" value="{{ $selectedEmployee }}">
                                <input type="text" id="employee_id" class="form-control" readonly
                                       value="{{ $isEdit ? ($exchange->employee->full_name ?? auth()->user()->name) : auth()->user()->name }}">
                            @else
                                <select id="employee_id" name="employee_id" class="form-control px-select2" required
                                        data-placeholder="{{ __('sales_exchanges.form.pick_employee') }}">
                                    <option value=""></option>
                                    @foreach ($employees ?? [] as $employee)
                                        <option value="{{ $employee->id }}" @selected((string) $selectedEmployee === (string) $employee->id)>
                                            {{ $employee->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif

                            <small class="sl-help">{{ __('sales_exchanges.form.responsible_hint') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ مسار المخزون ══════════
             من أين يخرج السليم، وإلى أين يدخل التالف — جنباً إلى جنب وبينهما
             سهم، فالاتجاهُ يُقرأ من الشكل قبل أن يُقرأ من النصّ. --}}
        <div class="card sl-section">
            <div class="sl-section__head">
                <h6 class="sl-section__title">
                    <i class="feather icon-repeat"></i> {{ __('sales_exchanges.form.route') }}
                </h6>
            </div>

            <div class="card-body">
                @if ($storehouses->count() < 2)
                    <div class="alert alert-warning">
                        <i class="feather icon-alert-triangle"></i> {{ __('sales_exchanges.form.need_two_storehouses') }}
                    </div>
                @endif

                <div class="px-route">
                    <div class="form-group mb-0">
                        <label for="storehouse_id">
                            <span class="px-step">1</span>
                            {{ __('sales_exchanges.fields.storehouse') }} <span class="sl-req"></span>
                        </label>
                        <select id="storehouse_id" name="storehouse_id" class="form-control px-select2" required>
                            @foreach ($storehouses as $house)
                                <option value="{{ $house->id }}" @selected((string) $selectedOut === (string) $house->id)>
                                    {{ $house->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="sl-help">{{ __('sales_exchanges.fields.storehouse_hint') }}</small>
                    </div>

                    <div class="px-route__arrow" aria-hidden="true">
                        <i class="feather icon-repeat"></i>
                    </div>

                    <div class="form-group mb-0">
                        <label for="return_storehouse_id">
                            <span class="px-step">2</span>
                            {{ __('sales_exchanges.fields.return_storehouse') }} <span class="sl-req"></span>
                        </label>
                        <select id="return_storehouse_id" name="return_storehouse_id" class="form-control px-select2" required>
                            @foreach ($storehouses as $house)
                                <option value="{{ $house->id }}" @selected((string) $selectedBack === (string) $house->id)>
                                    {{ $house->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="sl-help">{{ __('sales_exchanges.fields.return_storehouse_hint') }}</small>
                    </div>
                </div>

                <p class="px-route__error d-none" id="storehouseError">
                    <i class="feather icon-alert-circle"></i> {{ __('sales_exchanges.messages.same_storehouse') }}
                </p>
            </div>
        </div>

        {{-- ══════════ الأصناف ══════════ --}}
        <div class="card sl-section">
            <div class="sl-section__head">
                <h6 class="sl-section__title">
                    <i class="feather icon-package"></i> {{ __('sales_exchanges.items.title') }}
                </h6>

                <button type="button" class="btn btn-sm btn-outline-primary" id="addItem">
                    <i class="feather icon-plus"></i> {{ __('sales_exchanges.items.add') }}
                </button>
            </div>

            <div class="card-body">
                <p class="sl-help mb-2">
                    <i class="feather icon-info"></i> {{ __('sales_exchanges.items.hint') }}
                </p>

                <div id="itemsBody">
                    @foreach ($items as $i => $item)
                        @include('sales::product_exchange.partials.item-row', [
                            'index' => $i,
                            'item' => $item,
                            'products' => $products,
                            'reasons' => $reasons,
                        ])
                    @endforeach
                </div>

                <div id="itemsEmpty" class="d-none">
                    @include('sales::components.empty-state', [
                        'icon' => 'package',
                        'title' => __('sales_exchanges.items.empty'),
                        'hint' => __('sales_exchanges.items.hint'),
                    ])
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ الجانب ══════════ --}}
    <div class="col-lg-4 col-md-12">
        {{-- القيمةُ تُحتسب عند الحفظ — فلا بطاقةَ لها في الإنشاء تعرض فراغاً --}}
        @if ($isEdit)
            <div class="card sl-section">
                <div class="sl-section__head">
                    <h6 class="sl-section__title">
                        <i class="feather icon-dollar-sign"></i> {{ __('sales_exchanges.fields.total_value') }}
                    </h6>
                </div>

                <div class="card-body">
                    <p class="sl-stat__value sl-num mb-1">{{ $money($exchange->total_value) }}</p>
                    <small class="sl-help">{{ __('sales_exchanges.fields.total_value_hint') }}</small>
                </div>
            </div>
        @endif

        <div class="card sl-section">
            <div class="sl-section__head">
                <h6 class="sl-section__title">
                    <i class="feather icon-camera"></i> {{ __('sales_exchanges.fields.image') }}
                    @unless ($isEdit)
                        <span class="sl-req"></span>
                    @endunless
                </h6>
            </div>

            <div class="card-body">
                @if ($isEdit && $exchange->image)
                    <img src="{{ asset('storage/' . $exchange->image) }}"
                         alt="{{ __('sales_exchanges.fields.current_image') }}"
                         class="sl-proof" id="imagePreview">
                @else
                    <img src="" alt="" class="sl-proof d-none" id="imagePreview">
                @endif

                <div class="form-group mt-2 mb-0">
                    <input type="file" name="image" id="image" class="form-control" accept="image/*"
                           @unless ($isEdit) required capture="environment" @endunless>
                    <small class="sl-help">
                        {{ $isEdit ? __('sales_exchanges.fields.image_keep') : __('sales_exchanges.fields.image_hint') }}
                    </small>
                </div>
            </div>
        </div>

        <div class="card sl-section">
            <div class="sl-section__head">
                <h6 class="sl-section__title">
                    <i class="feather icon-edit-3"></i> {{ __('sales_exchanges.fields.notes') }}
                </h6>
            </div>

            <div class="card-body">
                <textarea id="notes" name="notes" rows="3" class="form-control">{{ old('notes', $exchange->notes ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>

@once
    <style>
        /* مسارُ المخزون: مستودعان وبينهما سهم */
        .px-route {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            gap: .75rem 1rem;
            align-items: start;
        }
        .px-route__arrow {
            display: flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; margin-top: 1.55rem;
            border-radius: 50%;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }
        .px-route__error {
            margin: .6rem 0 0; font-size: .82rem; font-weight: 600;
            color: var(--app-danger, #ea5455);
        }
        .px-step {
            display: inline-flex; align-items: center; justify-content: center;
            width: 20px; height: 20px; margin-inline-end: .25rem;
            border-radius: 50%;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: .72rem; font-weight: 700;
        }

        /* زرُّ الترويسة في طرفها الآخر لا ملاصقاً للعنوان */
        .sl-section__head > .btn,
        .sl-section__head > .sl-count { margin-inline-start: auto; }

        /* صفُّ الصنف */
        .sl-item {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 2fr) auto;
            gap: .625rem;
            align-items: end;
            padding: .75rem;
            margin-block-end: .625rem;
            border: 1px solid var(--app-border, rgba(0, 0, 0, .09));
            border-radius: var(--sl-r, 8px);
            background: var(--app-surface, #fff);
        }
        .sl-item__notes { grid-column: 1 / -1; }
        .sl-item .select2-container { width: 100% !important; }

        .sl-proof {
            display: block;
            width: 100%;
            max-height: 240px;
            object-fit: contain;
            border-radius: var(--sl-r, 8px);
            border: 1px solid var(--app-border, rgba(0, 0, 0, .09));
        }

        @media (max-width: 720px) {
            .px-route { grid-template-columns: 1fr; }
            .px-route__arrow { margin: 0 auto; transform: rotate(90deg); }
            .sl-item { grid-template-columns: 1fr; }
        }
    </style>
@endonce
