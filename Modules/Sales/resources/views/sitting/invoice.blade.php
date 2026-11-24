@extends('sales::master')

{{-- إعدادات الفواتير وعروض الأسعار.

     ثلاث نُسخ مرّت على هذا القالب. أولاها حملت نظامها البصري كاملاً: خطّ
     Cairo من جوجل، وFont Awesome من شبكة توزيع، ومجموعة
     `--primary/--secondary/…` تنازع متغيّرات التطبيق. وثانيتها استعارت لوحة
     وحدة الإعدادات (`sitting::components._account_ui` والبادئة `ax-`)،
     فورثت مظهراً يخالف بقيّة شاشات المبيعات وينكسر إن عُطّلت وحدة الإعدادات
     في `modules_statuses.json` — والاستعارة كانت لقالبٍ يحمل ثمانين كيلوبايت
     من الأنماط لأجل مفتاح تبديل.

     الآن: لغة تصميم «الأذون المخزنية» منقولةً إلى الوحدة
     (`sales::partials.settings-theme`، البادئة `sl-`)، ومفتاح التبديل من
     `sales::components.switch` وفيه الحقل المخفيّ الذي يجعل الإطفاء يصل
     إلى الخادم كما يصل التشغيل.

     وفوق ذلك شريط `system-context`: من الإعدادات هنا ما لا يعمل إلّا
     بإعدادٍ عامّ في شاشةٍ أخرى — «وصف مخصّص لقيود اليومية» بلا حسابات
     مربوطة، و«تفعيل الإشعار المدين» بلا حسابَي مبيعاتٍ وضريبة، و«دفع
     الفاتورة تلقائياً» بلا طريقة دفع مفعَّلة. الشريط يقول ذلك قبل أن
     يُكتشَف بعد الحفظ.

     وسقط من الشاشة «تفعيل تسوية المبيعات» بطلب المستخدم — حقلٌ حرٌّ
     يزيد الفاتورة أو ينقصها ببيانٍ حرّ، والخصمُ يغني عنه وله حدُّه. وسقط
     معه «عرض آخر سعر بيع والحدّ الأدنى للسعر» — تلميحٌ بجانب حقل السعر
     لا يمنع شيئاً ولا يغيّر محفوظاً، و«منع البيع بأقلّ من التكلفة» هو
     الحارس. وسقط «حساب الحدّ الأدنى لسعر البيع» كذلك: المقارنة بالتكلفة
     تجري قبل الضريبة دائماً، فالتكلفة مسجَّلةٌ بلا ضريبة ولا معنى لمقارنة
     سعرٍ بضريبته بها. فصار العدد تسعة عشر مفتاحاً وحقلاً واحداً ذا قيمة.

     والقائمة المنسدلة الوحيدة في الشاشة «مصدر مستودع البيع» في قسم
     «سلوك الفاتورة»: خياران ندّان لا يصلح لهما مفتاح تبديل. و`selects`
     في بقيّة الأقسام باقيةٌ فارغةً تحتمل مثلَه. --}}

@section('title', __('sales_settings.invoice.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        /**
         * ترتيب العرض — مصدره هنا وحده. أسماء المفاتيح مطابقة لأعمدة
         * `sales_invoice_settings`، فالقالب لا يخترع مفتاحاً لا يُحفَظ.
         */
        $sections = [
            'pricing' => [
                'icon' => 'tag',
                'selects' => [],
                'flags' => [
                    'no_sell_below_cost',
                    'enable_max_discount',
                    'select_price_list',
                    'show_invoice_profit',
                ],
            ],
            'behavior' => [
                'icon' => 'file-text',

                /* مصدر مستودع البيع — قائمةٌ لا مفتاح: الخياران ندّان
                   («مستودع الموظّف» و«المستودع الرئيسي»)، ومفتاحُ تبديلٍ
                   يجعل أحدهما «الوضع الطبيعي» والآخر استثناءً له. */
                'selects' => [
                    'warehouse_source' => \App\Models\SalesInvoiceSetting::WAREHOUSE_SOURCES,
                ],
                'flags' => [
                    'preview_before_save',
                    'default_paid_status',
                    'auto_pay_if_balance',
                    'auto_pay_from_wallet',
                    'manual_invoice_status',
                    'disable_delivery_options',
                    'show_service_products',
                    'show_digital_products',
                ],
            ],
            'quotes' => [
                'icon' => 'clipboard',
                'selects' => [],
                'flags' => [
                    'disable_quotes',
                    'manual_quote_status',
                    'enable_sales_orders',
                    'manual_sales_order_status',
                    'apply_offers_to_quotes',
                    'enable_coupons',
                    'copy_notes_on_conversion',
                ],
            ],
            'accounting' => [
                'icon' => 'book-open',
                'selects' => [],
                'flags' => [
                    'custom_journal_description',
                    'send_on_social',
                ],
            ],
        ];

        /* لكلّ مفتاحٍ شرحُه الآن.
           كانت أربعةٌ من اثنين وعشرين تحمل شرحاً، والبقيّة عنوانٌ مجرَّد
           — يُقرأ «حالة الفاتورة يدوياً» ولا يُعرف أثرُ تبديله. وقائمة
           السماح هذه كانت تُبقيها كذلك حتى لو أُضيف الشرح إلى ملفّ
           الترجمة. `Lang::has` تُغني عنها: ما له شرحٌ يُعرَض، وما لا
           فلا — بلا مفتاحٍ خامٍ يظهر في الشاشة. */
        /* رمز العملة — لاحقةُ حقل «أقصى مبلغ خصم»: سقفٌ بالمال يُقرأ بلا
           رمزه رقماً مجرَّداً لا يُعرف أنسبةٌ هو أم مبلغ. */
        $symbol = \App\Support\CurrencySettings::symbol();

        /* حقلٌ رقميّ يتبع مفتاحاً: يُعرَض تحته مباشرةً لا في قسمٍ منفصل،
           فمن فعّل «الحدّ الأقصى للخصم» يجد نسبتَه حيث نظر لا حيث يبحث.
           كان المفتاح بلا رقمٍ في الشاشة كلّها، والنسبة تُقرأ من «إعدادات
           الفروع» في وحدةٍ أخرى لا تُذكر هنا. */
        $numberAfter = [
            'enable_max_discount' => [
                'field' => 'max_discount_value',
                'min' => 0,
                'step' => 0.01,

                /* وحدةُ السقف تُختار: نسبةٌ **أو** مبلغ لا الاثنان. كانا
                   حقلين منفصلين يُملآن معاً ويسريان معاً، والمراد أحدُهما.
                   والقيمة تُكتب في عمود وحدتها — انظر
                   `SalesInvoiceSettingsRequest::settings()`. */
                'unit_field' => 'max_discount_type',
                'units' => [
                    'percentage' => '%',
                    'amount' => $symbol,
                ],
            ],
        ];

        /* قيمةُ القائمة المعروضة — تُقرأ عبر دالّة النموذج لا من العمود
           مباشرةً: صفٌّ سابقٌ للترحيل يحمل `null` فلا يُطابق خياراً،
           فتُفتح القائمة على أوّلها كأنّ المضبوط غيرُ المحفوظ. */
        $selectValue = [
            'warehouse_source' => $settings->warehouseSource(),
        ];

        $hintFor = static function (string $field): ?string {
            $key = 'sales_settings.invoice.fields.'.$field.'_hint';

            return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : null;
        };

        $flagCount = count(\App\Models\SalesInvoiceSetting::FLAGS);

        /* الضريبة المضبوطة — تُعرَض في الإرشادات لأنّ «منع البيع بأقلّ من
           التكلفة» يقارن قبل الضريبة، ونوعُها (متضمَّنة أم مضافة) يُضبَط
           في «إعدادات الضريبة» لا هنا. */
        $activeTax = \App\Models\TaxSitting::query()->orderBy('id')->first();
    @endphp

    <div class="content-body sl-scope">

        {{-- يُعرَّف قبل استدعاء الترويسة: `yieldContent` تقرأ ما سُجّل حتى تلك اللحظة --}}
        @section('page-actions')
            @permission('sales.settings.update')
                <button type="submit" form="salesInvoiceSettings" class="btn btn-primary">
                    <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                </button>
            @endpermission

            <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_settings.invoice.title'),
            'icon' => 'settings',
            'sub' => __('sales_settings.invoice.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.invoice.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-toggle-right"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ $settings->enabledCount() }} / {{ $flagCount }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.invoice.enabled_count') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-clock"></i></div>
                <div class="sl-stat__body">
                    {{-- `updated_by` هو دليل الحفظ عبر الشاشة: الصفّ يُنشأ عند أوّل
                         زيارة بـ`firstOrCreate`، فـ`updated_at` وحده يزعم تعديلاً لم يقع.

                         والتاريخ بتنسيق اللغة لا بـ`format('Y-m-d H:i')` مثبَّتاً:
                         من ضبط تنسيقاً هجرياً أو أمريكياً في «اللغات» يراه هنا --}}
                    <p class="sl-stat__value" style="font-size:1.05rem">
                        {{ $settings->updated_by
                            ? \App\Support\LanguageSettings::formatDate($settings->updated_at, true)
                            : __('sales_settings.invoice.never') }}
                    </p>
                    <p class="sl-stat__label">
                        {{ __('sales_settings.invoice.last_update') }}
                        @if ($settings->updatedBy)
                            — {{ $settings->updatedBy->name }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- إعدادات النظام التي تعمل بها هذه الشاشة --}}
        @include('sales::components.system-context')

        {{-- المسار PUT، وكان النموذج يرسل POST بلا `@method` فيرتدّ بـ405.

             `id` على النموذج ليصل إليه زرّ الحفظ في ترويسة الصفحة عبر
             `form=` — الزرّ خارج النموذج فلا يُرسله بغير ذلك --}}
        <form id="salesInvoiceSettings" action="{{ route('settings.update_invoices') }}" method="POST">
            @csrf
            @method('PUT')

            {{-- ══════════ الإرشادات ══════════
                 مطويّةٌ عند فتح الصفحة: الشاشة نفسُها هي المقصود، والشرح
                 يُطلَب عند الحاجة إليه. كانت مفتوحةً ابتداءً فتدفع
                 التبويبات والمفاتيح تحت طيّ الشاشة عند كل زيارة --}}
            <details class="sl-guide">
                <summary>
                    <i class="feather icon-info"></i>
                    <span>{{ __('sales_settings.invoice.guide.title') }}</span>
                </summary>

                <div class="sl-guide__body">
                    <p>{!! nl2br(e(__('sales_settings.invoice.guide.intro'))) !!}</p>

                    <h6>{{ __('sales_settings.invoice.guide.scope_title') }}</h6>
                    <p>{{ __('sales_settings.invoice.guide.scope') }}</p>

                    <h6>{{ __('sales_settings.invoice.guide.tax_title') }}</h6>
                    <p>{{ __('sales_settings.invoice.guide.tax_intro') }}</p>

                    <p class="sl-guide__fact">
                        @if ($activeTax)
                            <i class="feather icon-percent"></i>
                            {{ __('sales_settings.invoice.guide.tax_current', [
                                'name' => $activeTax->name,
                                'rate' => $activeTax->tax,
                                'type' => __('sales_settings.invoice.guide.tax_'
                                    . ($activeTax->type === 'included' ? 'included' : 'excluded')),
                            ]) }}
                        @else
                            <i class="feather icon-alert-triangle"></i>
                            {{ __('sales_settings.invoice.guide.tax_none') }}
                        @endif
                    </p>

                    <h6>{{ __('sales_settings.invoice.guide.warehouse_title') }}</h6>
                    <p>{{ __('sales_settings.invoice.guide.warehouse') }}</p>

                    <h6>{{ __('sales_settings.invoice.guide.min_price_title') }}</h6>
                    <p>{{ __('sales_settings.invoice.guide.min_price') }}</p>

                    <h6>{{ __('sales_settings.invoice.guide.save_title') }}</h6>
                    <p>{{ __('sales_settings.invoice.guide.save') }}</p>
                </div>
            </details>

            {{-- ══════════ الأقسام في تبويبات ══════════
                 كانت أربع بطاقاتٍ مكدَّسة بعشرين مفتاحاً، فتطول الصفحة حتى
                 يضيع أوّلها عن آخرها. التبويب يُبقي القسم الواحد في شاشة
                 واحدة، والحفظ يسري عليها كلِّها لأنّها نموذجٌ واحد --}}
            <div class="card">
                <div class="card-header p-0">
                    <ul class="nav sl-doc-tabs w-100" role="tablist">
                        @foreach ($sections as $key => $section)
                            <li class="nav-item">
                                <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab"
                                   href="#section-{{ $key }}" role="tab">
                                    <i class="feather icon-{{ $section['icon'] }}"></i>
                                    {{ __('sales_settings.invoice.sections.'.$key) }}
                                    <span class="badge badge-light-secondary sl-num">
                                        {{ count($section['flags']) + count($section['selects']) }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="tab-content">
            @foreach ($sections as $key => $section)
                <div class="tab-pane {{ $loop->first ? 'active' : '' }}" id="section-{{ $key }}" role="tabpanel">
                    <div class="card-body">
                        <p class="sl-help" style="margin-top:0;margin-bottom:var(--sl-4)">
                            {{ __('sales_settings.invoice.sections.'.$key.'_hint') }}
                        </p>

                        @if ($section['selects'])
                            <div class="row">
                                @foreach ($section['selects'] as $field => $options)
                                    <div class="col-md-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="{{ $field }}" class="sl-req">
                                                {{ __('sales_settings.invoice.fields.'.$field) }}
                                            </label>

                                            <select id="{{ $field }}" name="{{ $field }}"
                                                    class="form-control @error($field) is-invalid @enderror">
                                                @foreach ($options as $option)
                                                    <option value="{{ $option }}"
                                                        @selected(old($field, $selectValue[$field] ?? $settings->{$field}) === $option)>
                                                        {{ __('sales_settings.invoice.options.'.$option) }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            @error($field)
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @else
                                                @if ($hint = $hintFor($field))
                                                    <span class="sl-help">{{ $hint }}</span>
                                                @endif
                                            @enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @foreach ($section['flags'] as $flag)
                            <div class="sl-switch-row">
                                @include('sales::components.switch', [
                                    'name' => $flag,
                                    'checked' => (bool) old($flag, $settings->{$flag}),
                                    'title' => __('sales_settings.invoice.fields.'.$flag),
                                    'hint' => $hintFor($flag),
                                ])
                            </div>

                            @isset ($numberAfter[$flag])
                                @php
                                    $number = $numberAfter[$flag];
                                    $unitValue = old($number['unit_field'], $settings->maxDiscountType());
                                @endphp

                                <div class="sl-switch-row sl-number-row" data-requires="{{ $flag }}">
                                    <div class="form-group mb-0" style="max-width:320px">
                                        <label for="{{ $number['field'] }}" class="sl-req">
                                            {{ __('sales_settings.invoice.fields.'.$number['field']) }}
                                        </label>

                                        <div class="input-group">
                                            <input type="number" id="{{ $number['field'] }}"
                                                   name="{{ $number['field'] }}"
                                                   class="form-control sl-num @error($number['field']) is-invalid @enderror"
                                                   value="{{ old($number['field'], $settings->maxDiscountValue()) }}"
                                                   min="{{ $number['min'] }}"
                                                   step="{{ $number['step'] }}">

                                            {{-- الوحدة داخل مجموعة الحقل نفسِها، كما في حقل خصم
                                                 الفاتورة — لا حقلٌ ثانٍ منفصل يُظنّ سقفاً آخر --}}
                                            <select id="{{ $number['unit_field'] }}"
                                                    name="{{ $number['unit_field'] }}"
                                                    class="form-control sl-unit-select @error($number['unit_field']) is-invalid @enderror"
                                                    style="max-width:5.5rem;flex:0 0 auto">
                                                @foreach ($number['units'] as $unit => $label)
                                                    <option value="{{ $unit }}" @selected($unitValue === $unit)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        @error($number['field'])
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror

                                        @error($number['unit_field'])
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror

                                        @if (! $errors->has($number['field']) && ! $errors->has($number['unit_field']))
                                            @if ($hint = $hintFor($number['field']))
                                                <span class="sl-help">{{ $hint }}</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endisset
                        @endforeach
                    </div>
                </div>
            @endforeach
                </div>
            </div>

            <div class="sl-form-foot">
                <span class="sl-help" style="margin:0">{{ __('sales_settings.invoice.toggle_hint') }}</span>

                <div class="d-flex" style="gap:.5rem">
                    <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                        {{ __('sales_settings.ui.cancel') }}
                    </a>

                    @permission('sales.settings.update')
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                        </button>
                    @endpermission
                </div>
            </div>
        </form>
    </div>
@endsection

@push('styles')
    <style>
        /* الحقل الرقميّ التابع لمفتاحه — يبقى معروضاً وهو مطفأ ليُعرف
           أنّه موجود، ويُعطَّل فلا يُظنّ أنّ كتابته تكفي دون تشغيل المفتاح */
        .sl-number-row.is-off { opacity: .5; }

        /* وقائمةُ الوحدة معه: `readOnly` لا أثر له على `select`، و`disabled`
           يمنع إرسالَه فيردّه التحقّق «مطلوب». فالمنعُ بالمؤشّر وحده */
        .sl-number-row.is-off select { pointer-events: none; }
    </style>
@endpush

@push('scripts')
    <script>
        /* ربط الحقل الرقميّ بمفتاحه — العرضُ وحده لا يكفي: من كتب نسبةً
           والمفتاح مطفأ يظنّ الحدّ سارياً */
        document.querySelectorAll('.sl-number-row[data-requires]').forEach(function (row) {
            var toggle = document.getElementById('sw_' + row.dataset.requires);
            var input = row.querySelector('input, select');

            if (!toggle || !input) return;

            /* `readOnly` لا `disabled`: الحقل المعطَّل لا يُرسَل أصلاً،
               فتردّه قاعدة التحقّق «مطلوب» على من لم يمسّه */
            var sync = function () {
                row.classList.toggle('is-off', !toggle.checked);
                input.readOnly = !toggle.checked;
            };

            toggle.addEventListener('change', sync);
            sync();
        });
    </script>
@endpush
