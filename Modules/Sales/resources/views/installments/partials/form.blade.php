{{--
    نموذج اتفاقية التقسيط — مشترَكٌ بين الإنشاء والتعديل.

    ══════════════════ ما كان ══════════════════

    قالبان منفصلان فيهما:

    • **حقلان اسمُهما `amount`** في كلٍّ منهما: الأول إجمالي الفاتورة
      (`readonly`) والثاني قيمة القسط. في HTTP يفوز الأخير، فالمتحكّم
      يتحقّق من قيمة القسط ويحفظها مكان الإجمالي.

    • **`name="installment number"`** بفراغٍ داخل الاسم في قالب التعديل.

    • **`DOMContentLoaded` يدهس `#due_date` بتاريخ اليوم** في القالبين،
      فيمحو القيمة المحفوظة التي صُيّرت قبله بسطرين: فتحُ التعديل وحفظه
      بلا لمس شيء كان يعيد الاستحقاق إلى اليوم.

    • **لا قيمةَ مبدئية** لقيمة القسط ولا للعدد ولا للدوريّة ولا
      للملاحظات في التعديل — فكلّ حفظٍ يفقد ما لم يُعَد إدخاله.

    • **جدولٌ يُبنى بجافاسكربت** ويُعرَض للمستخدم ولا يُرسَل ولا يُحفَظ،
      وحسابُه نسخةٌ ثالثة تختلف عن نسختَي المتحكّم.

    ══════════════════ ما صار ══════════════════

    اسمٌ واحدٌ لكلّ معنى. و**قيمة القسط لا تُدخَل ولا تُرسَل**: تُحسب في
    `Modules\Sales\Support\InstallmentSchedule` — صنفٌ نقيّ مُختبَرٌ عددياً
    يناديه المتحكّم عند الحفظ.

    والمعاينة أدناه أرقامٌ أربعة لا جدولاً: الخادم هو من يبني الجدول
    ويوزّع الكسور على القسط الأخير. فسطحُ الازدواج محصورٌ في أربع عمليات
    جمعٍ وضرب، والجدول الحقيقي يظهر بعد الحفظ.

    الوسائط:
      agreement  موديل `Installment` (جديدٌ أو محفوظ)
      invoice    الفاتورة المقسَّطة
      code       رقم الاتفاقية — معاينةٌ في الإنشاء، والمحفوظ في التعديل
--}}

@php
    use Modules\Sales\Support\InstallmentSchedule;

    $val = function (string $field, $fallback = null) use ($agreement) {
        $stored = $agreement->getAttribute($field);

        if ($stored instanceof \DateTimeInterface) {
            $stored = $stored->format('Y-m-d');
        }

        return old($field, $stored ?? $fallback);
    };

    $locked = ($hasPayments ?? false);
@endphp

@if ($locked)
    {{-- التعديل يُعيد بناء الجدول، وإعادةُ البناء تمحو سداداً مسجَّلاً --}}
    <div class="alert alert-warning">
        <i class="feather icon-lock"></i> {{ __('sales_installments.messages.has_payments') }}
    </div>
@endif

<div class="card">
    <div class="card-body">

        {{-- ══════════ الفاتورة والعميل ══════════ --}}
        <div class="sl-section">
            <div class="sl-section__head">
                <i class="feather icon-file-text"></i>
                <h6 class="sl-section__title">{{ __('sales_installments.sections.parties') }}</h6>
            </div>

            <div class="row">
                <div class="col-md-4 col-12">
                    <div class="form-group">
                        <label for="code">{{ __('sales_installments.fields.code') }}</label>

                        {{-- رقم الاتفاقية **للقراءة فقط**، كرقم الفاتورة بجانبه.

                             `readonly` لا `disabled`: المعطَّل لا يُرسَل أصلاً،
                             فيصل الحقل فارغاً ويُولَّد رقمٌ جديد عند الحفظ غيرُ
                             الذي رآه المستخدم في الشاشة — وفي التعديل يضيع رقمُ
                             الاتفاقية المحفوظ.

                             والرقمُ يبقى مُرسَلاً فيمرّ بـ`resolveDocumentNumber()`:
                             إن استُهلك بين فتح الشاشة والحفظ أُخذ التالي الشاغر
                             بدل السقوط على قيد التفرّد. --}}
                        <input type="text" id="code" name="code" readonly
                               class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code', $code) }}">
                        <span class="sl-help">{{ __('sales_installments.hints.code') }}</span>
                        @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="form-group">
                        <label>{{ __('sales_installments.fields.invoice') }}</label>
                        {{-- الفاتورة لا تُغيَّر بعد الإنشاء: تغييرُها يعني
                             اتفاقيةً أخرى لا تعديلاً لهذه --}}
                        <input type="text" class="form-control" readonly
                               value="{{ $invoice?->code ?: '#'.($invoice?->id ?? '') }}">
                        <input type="hidden" name="invoice_id" value="{{ $val('invoice_id', $invoice?->id) }}">
                        <span class="sl-help">{{ __('sales_installments.hints.invoice') }}</span>
                        @error('invoice_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="form-group">
                        <label for="client_id" class="sl-req">{{ __('sales_installments.fields.client') }}</label>
                        <select id="client_id" name="client_id"
                                class="form-control @error('client_id') is-invalid @enderror">
                            <option value="">{{ __('sales_settings.ui.none') }}</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected($val('client_id') == $client->id)>
                                    {{ $client->trade_name }}@if ($client->code) — {{ $client->code }}@endif
                                </option>
                            @endforeach
                        </select>
                        <span class="sl-help">{{ __('sales_installments.hints.client') }}</span>
                        @error('client_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ المبالغ والتمويل ══════════ --}}
        <div class="sl-section">
            <div class="sl-section__head">
                <i class="feather icon-dollar-sign"></i>
                <h6 class="sl-section__title">{{ __('sales_installments.sections.money') }}</h6>
            </div>

            <div class="row">
                <div class="col-md-3 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="total_amount" class="sl-req">{{ __('sales_installments.fields.total_amount') }}</label>
                        <input type="number" step="0.01" min="0.01" id="total_amount" name="total_amount"
                               class="form-control ins-calc @error('total_amount') is-invalid @enderror"
                               value="{{ $val('total_amount') }}">
                        <span class="sl-help">{{ __('sales_installments.hints.total_amount') }}</span>
                        @error('total_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="down_payment">{{ __('sales_installments.fields.down_payment') }}</label>
                        <input type="number" step="0.01" min="0" id="down_payment" name="down_payment"
                               class="form-control ins-calc @error('down_payment') is-invalid @enderror"
                               value="{{ $val('down_payment', 0) }}">
                        <span class="sl-help">{{ __('sales_installments.hints.down_payment') }}</span>
                        @error('down_payment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="profit_rate">{{ __('sales_installments.fields.profit_rate') }} %</label>
                        <input type="number" step="0.01" min="0" max="100" id="profit_rate" name="profit_rate"
                               class="form-control ins-calc @error('profit_rate') is-invalid @enderror"
                               value="{{ $val('profit_rate', 0) }}">
                        <span class="sl-help">{{ __('sales_installments.hints.profit_rate') }}</span>
                        @error('profit_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="admin_fees">{{ __('sales_installments.fields.admin_fees') }}</label>
                        <input type="number" step="0.01" min="0" id="admin_fees" name="admin_fees"
                               class="form-control ins-calc @error('admin_fees') is-invalid @enderror"
                               value="{{ $val('admin_fees', 0) }}">
                        <span class="sl-help">{{ __('sales_installments.hints.admin_fees') }}</span>
                        @error('admin_fees')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="tax_id">{{ __('sales_installments.fields.tax') }}</label>

                        {{-- **تُختار ولا تُكتب** — البند ٢.

                             كان حقلاً رقمياً حرّاً (`tax_rate`)، فاتفاقيتان
                             في المنشأة نفسِها تحملان ضريبتين مختلفتين بلا
                             سببٍ إلا اختلافَ من أدخلهما. والنسبةُ الآن
                             تُشتقّ في الخادم من الصفّ المختار
                             (`InstallmentRequest::taxRate()`) وتُجمَّد مع
                             الاتفاقية، فتعديلُها في الإعدادات لاحقاً لا
                             يمسّ ما وُقّع. --}}
                        <select id="tax_id" name="tax_id"
                                class="form-control ins-calc @error('tax_id') is-invalid @enderror"
                                data-rates='@json($taxRates ?? [])'>
                            <option value="">{{ __('sales_installments.fields.no_tax') }}</option>
                            @foreach ($taxes as $tax)
                                <option value="{{ $tax->id }}" @selected($val('tax_id') == $tax->id)>
                                    {{ $tax->name ?: __('sales_installments.fields.tax') }} — {{ rtrim(rtrim(number_format($taxRates[$tax->id] ?? 0, 2), '0'), '.') }}%
                                </option>
                            @endforeach
                        </select>


                        <span class="sl-help">{{ __('sales_installments.hints.tax') }}</span>
                        @error('tax_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                @if ($taxes->isEmpty())
                    <div class="col-12">
                        <div class="alert alert-warning" style="margin-top:.25rem">
                            <i class="feather icon-info"></i> {{ __('sales_installments.hints.no_taxes_configured') }}
                        </div>
                    </div>
                @elseif (! empty($orphanTaxRate))
                    {{-- اتفاقيةٌ تحمل نسبةً محفوظة لا تقابلها ضريبةٌ في
                         الإعدادات — تُقال بدل أن تُمحى بصمتٍ عند أوّل حفظ --}}
                    <div class="col-12">
                        <div class="alert alert-warning" style="margin-top:.25rem">
                            <i class="feather icon-alert-triangle"></i>
                            {{ __('sales_installments.hints.orphan_tax_rate', [
                                'rate' => rtrim(rtrim(number_format((float) $orphanTaxRate, 2), '0'), '.'),
                            ]) }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ══════════ الجدولة ══════════ --}}
        <div class="sl-section">
            <div class="sl-section__head">
                <i class="feather icon-calendar"></i>
                <h6 class="sl-section__title">{{ __('sales_installments.sections.schedule') }}</h6>
            </div>

            <div class="row">
                <div class="col-md-4 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="installment_number" class="sl-req">{{ __('sales_installments.fields.installment_number') }}</label>
                        {{-- الاسم بلا فراغ: كان `name="installment number"` --}}
                        <input type="number" min="1" max="{{ InstallmentSchedule::MAX_INSTALLMENTS }}"
                               id="installment_number" name="installment_number"
                               class="form-control ins-calc @error('installment_number') is-invalid @enderror"
                               value="{{ $val('installment_number', 1) }}">
                        <span class="sl-help">{{ __('sales_installments.hints.installment_number') }}</span>
                        @error('installment_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="payment_rate" class="sl-req">{{ __('sales_installments.fields.payment_rate') }}</label>
                        <select id="payment_rate" name="payment_rate"
                                class="form-control @error('payment_rate') is-invalid @enderror">
                            {{-- من `InstallmentSchedule::RATES` لا من خمسة
                                 خياراتٍ مكتوبة في القالب --}}
                            @foreach ($rates as $key => $rate)
                                <option value="{{ $key }}" @selected($val('payment_rate', 1) == $key)>
                                    {{ __('sales_installments.rates.'.$rate['key']) }}
                                </option>
                            @endforeach
                        </select>
                        <span class="sl-help">{{ __('sales_installments.hints.payment_rate') }}</span>
                        @error('payment_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="form-group">
                        <label for="first_due_date" class="sl-req">{{ __('sales_installments.fields.first_due_date') }}</label>
                        {{-- لا سكربتَ يدهس هذه القيمة عند التحميل --}}
                        <input type="date" id="first_due_date" name="first_due_date"
                               class="form-control @error('first_due_date') is-invalid @enderror"
                               data-aligned='@json($alignment)'
                               value="{{ $val('first_due_date') }}">
                        <span class="sl-help">{{ __('sales_installments.hints.first_due_date') }}</span>
                        @error('first_due_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ غرامة التأخير ══════════ --}}
        <div class="sl-section">
            <div class="sl-section__head">
                <i class="feather icon-alert-triangle"></i>
                <h6 class="sl-section__title">{{ __('sales_installments.sections.penalty') }}</h6>
            </div>

            <div class="row">
                <div class="col-md-4 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="late_fee_type">{{ __('sales_installments.fields.late_fee_type') }}</label>
                        <select id="late_fee_type" name="late_fee_type"
                                class="form-control @error('late_fee_type') is-invalid @enderror">
                            <option value="">{{ __('sales_installments.late_fee_types.none') }}</option>
                            @foreach (['percent', 'fixed'] as $type)
                                <option value="{{ $type }}" @selected($val('late_fee_type') === $type)>
                                    {{ __('sales_installments.late_fee_types.'.$type) }}
                                </option>
                            @endforeach
                        </select>
                        <span class="sl-help">{{ __('sales_installments.hints.late_fee_type') }}</span>
                        @error('late_fee_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="late_fee_value">{{ __('sales_installments.fields.late_fee_value') }}</label>
                        <input type="number" step="0.01" min="0" id="late_fee_value" name="late_fee_value"
                               class="form-control @error('late_fee_value') is-invalid @enderror"
                               value="{{ $val('late_fee_value', 0) }}">
                        <span class="sl-help">{{ __('sales_installments.hints.late_fee_value') }}</span>
                        @error('late_fee_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="form-group">
                        <label for="late_fee_grace_days">{{ __('sales_installments.fields.late_fee_grace_days') }}</label>
                        <input type="number" min="0" max="365" id="late_fee_grace_days" name="late_fee_grace_days"
                               class="form-control @error('late_fee_grace_days') is-invalid @enderror"
                               value="{{ $val('late_fee_grace_days', 0) }}">
                        <span class="sl-help">{{ __('sales_installments.hints.late_fee_grace_days') }}</span>
                        @error('late_fee_grace_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="late_fee_basis">{{ __('sales_installments.fields.late_fee_basis') }}</label>
                        <select id="late_fee_basis" name="late_fee_basis"
                                class="form-control @error('late_fee_basis') is-invalid @enderror">
                            @foreach ($feeBases as $basis)
                                <option value="{{ $basis }}" @selected($val('late_fee_basis', 'once') === $basis)>
                                    {{ __('sales_installments.late_fee_bases.'.$basis) }}
                                </option>
                            @endforeach
                        </select>
                        <span class="sl-help">{{ __('sales_installments.hints.late_fee_basis') }}</span>
                        @error('late_fee_basis')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="late_fee_max">{{ __('sales_installments.fields.late_fee_max') }}</label>
                        <input type="number" step="0.01" min="0" id="late_fee_max" name="late_fee_max"
                               class="form-control @error('late_fee_max') is-invalid @enderror"
                               value="{{ $val('late_fee_max') }}">
                        <span class="sl-help">{{ __('sales_installments.hints.late_fee_max') }}</span>
                        @error('late_fee_max')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- ما يترتّب على ضبط السياسة — يُقال قبل الحفظ لا بعده --}}
            <div class="sl-help" style="margin-top:.25rem">
                <i class="feather icon-alert-triangle"></i>
                {{ __('sales_installments.hints.late_fee_posting') }}
            </div>
        </div>

        {{-- ══════════ العملة والسداد ══════════ --}}
        <div class="sl-section">
            <div class="sl-section__head">
                <i class="feather icon-credit-card"></i>
                <h6 class="sl-section__title">{{ __('sales_installments.sections.settings') }}</h6>
            </div>

            <div class="row">
                <div class="col-md-4 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="currency">{{ __('sales_installments.fields.currency') }}</label>
                        {{-- العملة لا تُختار: في الإنشاء من فاتورة التقسيط
                             (أو «العملات» إن خلت)، وفي التعديل المجمَّدةُ مع
                             الاتفاقية — قائمةٌ هنا كانت تسمح باتفاقيةٍ بعملةٍ
                             غير عملة فاتورتها. تُرسَل مخفيّةً لأنّ `readonly`
                             لا يعمل على select و`disabled` لا يُرسِل. --}}
                        @php
                            $currencyCode = $val('currency') ?: \App\Support\CurrencySettings::code();
                            $currencyRow = collect($currencies)->firstWhere('code', $currencyCode);
                        @endphp
                        <input type="text" id="currency" readonly
                               class="form-control @error('currency') is-invalid @enderror"
                               value="{{ $currencyCode }}{{ $currencyRow && $currencyRow->name ? ' — '.$currencyRow->name : '' }}">
                        <input type="hidden" name="currency" value="{{ $currencyCode }}">
                        <span class="sl-help">{{ __('sales_installments.hints.currency') }}</span>
                        @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4 col-sm-6 col-12">
                    <div class="form-group">
                        <label for="payment_method">{{ __('sales_installments.fields.payment_method') }}</label>
                        <select id="payment_method" name="payment_method"
                                class="form-control @error('payment_method') is-invalid @enderror">
                            <option value="">{{ __('sales_settings.ui.none') }}</option>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->id }}" @selected($val('payment_method') == $method->id)>
                                    {{ $method->name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="sl-help">{{ __('sales_installments.hints.payment_method') }}</span>
                        @error('payment_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                @unless ($agreement->exists)
                    <div class="col-md-4 col-sm-6 col-12">
                        <div class="form-group">
                            <label for="treasury_id">{{ __('sales_installments.fields.treasury') }}</label>
                            <select id="treasury_id" name="treasury_id"
                                    class="form-control @error('treasury_id') is-invalid @enderror">
                                <option value="">{{ __('sales_settings.ui.none') }}</option>
                                @foreach ($treasuries as $treasury)
                                    <option value="{{ $treasury->id }}" @selected(old('treasury_id') == $treasury->id)>
                                        {{ $treasury->name }}@if ($treasury->code) — {{ $treasury->code }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <span class="sl-help">{{ __('sales_installments.hints.treasury') }}</span>
                            @error('treasury_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endunless

                @if ($agreement->exists)
                    <div class="col-md-4 col-12">
                        <div class="form-group">
                            <label for="status">{{ __('sales_installments.fields.status') }}</label>
                            <select id="status" name="status"
                                    class="form-control @error('status') is-invalid @enderror">
                                @foreach (\App\Models\Installment::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($val('status') === $status)>
                                        {{ __('sales_installments.status.'.$status) }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="sl-help">{{ __('sales_installments.hints.status') }}</span>
                            @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endif
            </div>

            <div class="form-group">
                <label for="note">{{ __('sales_installments.fields.note') }}</label>
                <textarea id="note" name="note" rows="3"
                          class="form-control @error('note') is-invalid @enderror">{{ $val('note') }}</textarea>
                <span class="sl-help">{{ __('sales_installments.hints.note') }}</span>
                @error('note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- ══════════ المعاينة ══════════ --}}
        <div class="sl-section">
            <div class="sl-section__head">
                <i class="feather icon-eye"></i>
                <h6 class="sl-section__title">{{ __('sales_installments.sections.preview') }}</h6>
            </div>

            {{-- ستّ بطاقاتٍ متساوية الوزن كانت تُخفي السؤال الذي يسأله
                 المستخدم فعلاً: **ما المبلغ الذي تُخصَم منه المقدَّمة؟**

                 فالمقدَّمة لا تُصغّر وعاء المرابحة — هي سدادٌ على تمويلٍ
                 قائم (البند ١-ب في `InstallmentSchedule`) — ورقمان
                 متقاربان في الشاشة («التمويل» و«ما يُقسَّم») بلا ما يربطهما
                 يُقرآن على أنّهما خطأ أو تكرار.

                 فصارت المعاينة سطرَين يُقرآن كمعادلة، والرقمان المميَّزان
                 فيهما هما **التمويل الكامل** و**ما يُقسَّم** — ما قبل الخصم
                 وما بعده. --}}
            <div class="ins-preview">

                {{-- السطر الأول: بناءُ التمويل --}}
                <div class="ins-chain">
                    @foreach ([
                        ['slot' => 'total', 'field' => 'total_amount', 'op' => null],
                        ['slot' => 'profit', 'field' => 'profit_amount', 'op' => '+'],
                        ['slot' => 'fees', 'field' => 'admin_fees', 'op' => '+'],
                        ['slot' => 'tax', 'field' => 'tax_amount', 'op' => '+'],
                        ['slot' => 'financed', 'field' => 'financed_total', 'op' => '=', 'lead' => true],
                    ] as $cell)
                        @if ($cell['op'])
                            <span class="ins-chain__op" aria-hidden="true">{{ $cell['op'] }}</span>
                        @endif

                        <div class="ins-cell {{ ($cell['lead'] ?? false) ? 'is-lead' : '' }}">
                            <p class="ins-cell__value sl-num" data-preview="{{ $cell['slot'] }}">—</p>
                            <p class="ins-cell__label">{{ __('sales_installments.fields.'.$cell['field']) }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- السطر الثاني: الخصمُ والقسمة --}}
                <div class="ins-chain">
                    @foreach ([
                        ['slot' => 'financed2', 'field' => 'financed_total', 'op' => null],
                        ['slot' => 'down', 'field' => 'down_payment', 'op' => '−'],
                        ['slot' => 'scheduled', 'field' => 'scheduled_amount', 'op' => '=', 'lead' => true],
                        ['slot' => 'count', 'field' => 'installment_number', 'op' => '÷'],
                        ['slot' => 'each', 'field' => 'installment_amount', 'op' => '=', 'lead' => true],
                    ] as $cell)
                        @if ($cell['op'])
                            <span class="ins-chain__op" aria-hidden="true">{{ $cell['op'] }}</span>
                        @endif

                        <div class="ins-cell {{ ($cell['lead'] ?? false) ? 'is-lead' : '' }}">
                            <p class="ins-cell__value sl-num" data-preview="{{ $cell['slot'] }}">—</p>
                            <p class="ins-cell__label">{{ __('sales_installments.fields.'.$cell['field']) }}</p>
                        </div>
                    @endforeach
                </div>

                <p class="sl-help" style="margin:.6rem 0 0">
                    {{ __('sales_installments.hints.preview_chain') }}
                </p>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <style>
        /* سلسلةُ المعاينة — تُقرأ معادلةً، وتنكسر إلى عمودٍ على الجوّال
           بدل أن تُخرج الصفحةَ عن حدّها أفقياً */
        .ins-preview { padding: .25rem 0; }

        .ins-chain {
            display: flex; flex-wrap: wrap; align-items: stretch;
            gap: .4rem .55rem; margin-bottom: .65rem;
        }
        .ins-chain:last-of-type { margin-bottom: 0; }

        .ins-chain__op {
            display: flex; align-items: center;
            font-size: 1.05rem; font-weight: 700;
            color: var(--app-muted, #6e6b7b);
            padding: 0 .1rem;
        }

        .ins-cell {
            flex: 1 1 128px; min-width: 118px;
            padding: .55rem .7rem;
            border: 1px solid var(--app-border, #e4e6ef);
            border-radius: .5rem;
            background: var(--app-card, #fff);
        }

        /* الرقمان اللذان يبحث عنهما المستخدم: ما قبل الخصم وما بعده */
        .ins-cell.is-lead {
            border-color: var(--app-primary, #7367f0);
            background: color-mix(in srgb, var(--app-primary, #7367f0) 7%, transparent);
        }

        .ins-cell__value {
            margin: 0; line-height: 1.15; font-weight: 700;
            font-size: 1.02rem; font-variant-numeric: tabular-nums;
            color: var(--app-text, #4b4b4b);
            overflow-wrap: anywhere;
        }
        .ins-cell.is-lead .ins-cell__value { font-size: 1.2rem; color: var(--app-primary, #7367f0); }

        .ins-cell__label {
            margin: .15rem 0 0; font-size: .74rem; line-height: 1.4;
            color: var(--app-muted, #6e6b7b);
        }

        @media (max-width: 575px) {
            .ins-chain { flex-direction: column; }
            .ins-chain__op { justify-content: center; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        /*
         * معاينةٌ تقديرية — لا جدول.
         *
         * الأرقامُ الخمسة أدناه تعكس معادلة
         * `Modules\Sales\Support\InstallmentSchedule`، و**الخادمُ هو
         * المرجع**: هو من يبني الجدول ويوزّع الكسور على القسط الأخير،
         * وما يُحفَظ ما يحسبه هو لا هذا السكربت.
         *
         * القالب القديم كان يبني الجدول كلَّه هنا ثمّ لا يُرسله — نسخةٌ
         * ثالثة من الحساب تختلف عن نسختَي المتحكّم. حصرُ الازدواج في
         * أربع عمليات هو ما يمكن ضمانُ توافقه.
         */
        (function () {
            var num = function (id) {
                var el = document.getElementById(id);
                var v = el ? parseFloat(el.value) : 0;
                return isNaN(v) ? 0 : v;
            };

            /* نسبة الضريبة من الصفّ المختار في «إعدادات الضريبة».
               الخريطة مبنيّةٌ في الخادم من الجدول نفسِه الذي يقرؤه
               `InstallmentRequest::taxRate()`، فلا نسختان للنسبة. */
            var taxSelect = document.getElementById('tax_id');
            var taxMap = {};

            try {
                taxMap = JSON.parse((taxSelect && taxSelect.dataset.rates) || '{}');
            } catch (e) {
                taxMap = {};
            }

            function taxRate() {
                var id = taxSelect ? taxSelect.value : '';
                return id && taxMap[id] ? parseFloat(taxMap[id]) : 0;
            }

            var out = function (slot, value, decimals) {
                var digits = typeof decimals === 'number' ? decimals : 2;

                document.querySelectorAll('[data-preview="' + slot + '"]').forEach(function (el) {
                    el.textContent = isFinite(value)
                        ? value.toLocaleString(undefined, {
                            minimumFractionDigits: digits,
                            maximumFractionDigits: digits,
                        })
                        : '—';
                });
            };

            function recalc() {
                var total = num('total_amount');
                var down = num('down_payment');
                var count = num('installment_number');

                /* الوعاء هو الإجمالي كلُّه — المقدَّمة من ضمن التمويل لا
                   خصمٌ قبله. نظيرُ `InstallmentSchedule::profitAmount()` */
                var profit = total * (num('profit_rate') / 100);
                var fees = num('admin_fees');
                var tax = (profit + fees) * (taxRate() / 100);
                var financed = total + profit + fees + tax;
                var scheduled = Math.max(0, financed - down);

                out('total', total);
                out('profit', profit);
                out('fees', fees);
                out('tax', tax);

                /* التمويل الكامل يظهر مرّتين: آخرَ السطر الأول وأوّلَ
                   الثاني — به يتّصل البناءُ بالخصم فيُقرأ السطران معادلةً
                   واحدة لا رقمَين منفصلين */
                out('financed', financed);
                out('financed2', financed);

                out('down', down);
                out('scheduled', scheduled);
                out('count', count, 0);

                /* القسط بلا الكسر — الخادم يعطي الفرق للأخير */
                out('each', count >= 1 ? Math.floor((scheduled / count) * 100) / 100 : NaN);
            }

            document.querySelectorAll('.ins-calc').forEach(function (el) {
                el.addEventListener('input', recalc);
                el.addEventListener('change', recalc);
            });

            /* تبديلُ الدوريّة يُحرّك تاريخَ أوّل قسطٍ إلى **بداية المدّة
             * التالية** — الخريطةُ من الخادم (`InstallmentSchedule::alignmentMap`)
             * فلا يُعاد حسابُ «أوّل السبت القادم» بجافاسكربت.
             *
             * ولا يُدهَس تاريخٌ لمسه المستخدم: من اختار يومَه بيده لا
             * يُعاد إلى المُقترَح كلّما بدّل الدوريّة. وكان القالبُ القديم
             * يدهس `#due_date` بتاريخ اليوم عند كلّ تحميل، فيمحو القيمةَ
             * المحفوظة في التعديل. */
            (function () {
                var rate = document.getElementById('payment_rate');
                var date = document.getElementById('first_due_date');

                if (!rate || !date) return;

                var map = {};

                try {
                    map = JSON.parse(date.dataset.aligned || '{}');
                } catch (e) {
                    return;
                }

                var touched = false;
                date.addEventListener('input', function () { touched = true; });

                rate.addEventListener('change', function () {
                    if (touched) return;

                    var proposed = map[rate.value];

                    if (proposed) date.value = proposed;
                });
            })();

            recalc();
        })();
    </script>
@endpush
