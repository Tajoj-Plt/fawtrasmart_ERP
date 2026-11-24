@php
    // كل حقل: مفتاح الإعداد + تسمية + وصف مختصر
    $accountFields = [
        'sales_account_id' => ['label' => 'حساب المبيعات', 'hint' => 'الحساب الذي يُقيَّد عليه دائناً إجمالي مبيعات المتجر'],
        'sales_returns_account_id' => ['label' => 'حساب مردود المبيعات', 'hint' => 'الحساب الذي تُقيَّد عليه مدياً فواتير المرتجع عند الإلغاء أو الاسترداد'],
        'allowed_discount_account_id' => ['label' => 'حساب الخصم المسموح به', 'hint' => 'حساب خصومات الطلبات والكوبونات'],
        'cost_of_sales_account_id' => ['label' => 'حساب تكلفة المبيعات', 'hint' => 'الحساب الذي تُقيَّد عليه تكلفة البضاعة المباعة'],
        'purchases_account_id' => ['label' => 'حساب المشتريات', 'hint' => 'الحساب الذي تُقيَّد عليه مديناً مشتريات المتجر من الموردين — بدل الحساب المشترك في «الحسابات المحاسبية» العامة عند توفّره'],
        'cash_client_account_id' => ['label' => 'حساب العميل النقدي', 'hint' => 'حساب ذمم عملاء المتجر النقديين'],
        'vat_payable_account_id' => ['label' => 'حساب ضريبة القيمة المضافة (مبيعات)', 'hint' => 'الضريبة المحصَّلة من عملاء المتجر'],
        'vat_paid_account_id' => ['label' => 'حساب ضريبة القيمة المضافة (مشتريات)', 'hint' => 'الضريبة المدفوعة على فواتير شراء المتجر'],
        'shipping_revenue_account_id' => ['label' => 'حساب إيرادات الشحن', 'hint' => 'الفرق بين ما يُحصَّل من العميل وتكلفة شركة الشحن'],
        'shipping_revenue_returns_account_id' => ['label' => 'حساب مردود إيرادات الشحن', 'hint' => 'يُستعمل عند إلغاء/استرداد طلبٍ لم يُشحن بعد'],
        'shipping_vat_account_id' => ['label' => 'حساب ضريبة الشحن', 'hint' => 'ضريبة القيمة المضافة المحسوبة فوق رسم الشحن (بنسبة ضريبة المتجر) — تُقيَّد دائنةً مع كل طلبٍ عليه شحن'],
        'free_shipping_expense_account_id' => ['label' => 'حساب مصروف الشحن المجاني', 'hint' => 'ما تتحمّله المنشأة لشركة الشحن حين يكون الشحن مجانياً على العميل — يُقيَّد مديناً عند «تم الشحن»'],
    ];

    // لا حقل هنا لحساب المخزون ولا للخزينة/البنك الافتراضي: حساب المخزون هو
    // حساب المستودع الافتراضي نفسه (قسم checkout → المستودع الافتراضي)،
    // والخزينة/البنك من نفس القسم أيضاً — لا تُكرَّر هنا كإعدادٍ منفصل.
    // انظر ResolvesStoreDefaults::getDefaultInventoryAccount()/getDefaultTreasuryAccount().

    // القيمة الافتراضية الحقيقية لكل حقل — من شجرة الحسابات عبر ثوابت النظام
    // SystemAccountIds — وتظهر مُختارة فعلياً في القائمة حين لا يوجد تخصيص
    // مَحفوظ بعد (لا نصّ إرشادي منفصل).
    // المصدر نفسه الذي يقرأ منه الترحيل: ما يظهر هنا مختاراً هو ما تُقيَّد عليه عمليات المتجر
    $accountFieldDefaults = \App\Services\OnlineStoreSettingsService::defaultAccountIds();

    $savedValues = array_map(fn ($key) => optional($settings->where('key', $key)->first())->value, array_keys($accountFields));

    $accountLabels = \App\Models\Account::whereIn('id', array_unique(array_filter(
        array_merge($savedValues, array_values($accountFieldDefaults))
    )))->get(['id', 'code', 'name'])->keyBy('id');
@endphp

<div class="alert alert-info">
    <i class="feather icon-info"></i>
    هذا القسم هو مرجع المتجر: كل حقل يعرض الحساب الذي تُرحَّل إليه قيود المتجر فعلاً (الطلبات وإلغاؤها
    واستردادها، وفواتير المشتريات وأذونها). اختر حساباً آخر واحفظ فيتغيّر الترحيل من العملية التالية.
    ربطُ الحسابات العام في «إعدادات النظام» لا يُغيّر هذه البنود.
    <span class="d-block mt-50 small">غير موجود هنا لأنه يُؤخذ من مكانه: حساب المخزون (حساب المستودع نفسه)،
    والخزينة (الخزينة الافتراضية في «إعدادات الدفع»)، وحساب كل شركة شحن (يُنشأ مع طريقة الشحن)، وحساب كل مورد.</span>
</div>

<div class="row">
    @foreach($accountFields as $key => $field)
        @php
            $current = $settings->where('key', $key)->first();
            $currentId = $current?->value ?: ($accountFieldDefaults[$key] ?? '');
            $currentAccount = $currentId ? ($accountLabels[$currentId] ?? null) : null;
            $currentLabel = $currentAccount ? trim($currentAccount->code.' - '.$currentAccount->name, ' -') : '';
        @endphp
        <div class="col-md-6">
            <div class="form-group">
                <label for="{{ $key }}">{{ $field['label'] }}</label>
                <select name="{{ $key }}" id="{{ $key }}" class="form-control account-picker" data-current-label="{{ $currentLabel }}">
                    @if($currentId)
                        <option value="{{ $currentId }}" selected>{{ $currentLabel ?: ('#'.$currentId) }}</option>
                    @endif
                </select>
                <small class="text-muted">{{ $field['hint'] }}</small>
            </div>
        </div>
    @endforeach
</div>

@once
    @push('scripts')
    <script>
    $(document).ready(function () {
        $('.account-picker').select2({
            dir: 'rtl',
            allowClear: true,
            width: '100%',
            placeholder: '-- ابحث بالاسم أو الكود --',
            minimumInputLength: 1,
            ajax: {
                url: '{{ route('store_settings.accounts-search') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return {
                        results: data.map(function (account) {
                            return { id: account.id, text: account.label };
                        })
                    };
                },
                cache: true
            }
        });
    });
    </script>
    @endpush
@endonce
