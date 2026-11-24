{{--
    ══════════ ما يُحفَظ به هذا المستند ══════════

    نظيرُ `sl-doc-note` في شاشة «إنشاء فاتورة مبيعات». العملةُ والترقيم
    كانا يجريان في الخلفية بلا ذِكر، فيُفاجَأ المستخدم برقمٍ غير الذي رآه
    أو بعملةٍ غير التي توقّع.

    كان هذا الشريط داخل `partials/form`، فيقع **بعد** لوحة الإرشاد —
    وترتيبُ شاشة المبيعات عكسُه: شريطُ السياق أوّلاً ثمّ اللوحة. فأُخرِج
    إلى قالبٍ مستقلّ تستدعيه كلُّ شاشة في موضعه الصحيح: بعد الترويسة
    مباشرةً وقبل تنبيه الفترة ولوحة الإرشاد.

    @param string|null $code  رقم المستند من «تسلسل أرقام المستندات»
--}}
<div class="po-doc-note">
    <span class="po-doc-note__icon"><i class="feather icon-info"></i></span>
    <div class="po-doc-note__body">
        <strong>{{ __('purchase_cycle.fields.currency') }}:</strong>
        {{ \App\Support\CurrencySettings::name() }} ({{ \App\Support\CurrencySettings::code() }})
        &nbsp;·&nbsp;
        <strong>{{ __('purchase_cycle.fields.code') }}:</strong>
        <span class="po-code-inline">{{ ($code ?? null) ?: '—' }}</span>
        <span class="po-hint">{{ __('purchase_orders.placeholders.code') }}</span>
    </div>
</div>
