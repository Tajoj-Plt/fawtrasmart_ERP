{{--
    لوحة إرشادات نموذج الاتفاقية — مشترَكةٌ بين الإنشاء والتعديل.

    `key` مختلفٌ بين الشاشتين: من طوى الشرح في الإنشاء بعد أن أتقنه قد
    يظلّ يحتاجه في التعديل — وقاعدةُ إعادة بناء الجدول تخصّ التعديل وحده.
--}}

@include('sales::components.guide', [
    'open' => false,
    'key' => $key ?? 'installments.form',
    'title' => __('sales_installments.guide.form.title'),
    'lead' => __('sales_installments.guide.what_is'),
    'blocks' => [
        __('sales_installments.guide.formula.title') => [
            __('sales_installments.guide.formula.a'),
            __('sales_installments.guide.formula.b'),
            __('sales_installments.guide.formula.c'),
            __('sales_installments.guide.formula.d'),
            __('sales_installments.guide.formula.e'),
            __('sales_installments.guide.formula.f'),
        ],
        __('sales_installments.guide.form.save.title') => [
            __('sales_installments.guide.form.save.a'),
            __('sales_installments.guide.form.save.b'),
            __('sales_installments.guide.form.save.c'),
            __('sales_installments.guide.form.save.d'),
        ],
        __('sales_installments.guide.form.rebuild.title') => [
            __('sales_installments.guide.form.rebuild.a'),
            __('sales_installments.guide.form.rebuild.b'),
        ],
        __('sales_installments.guide.form.blocked.title') => [
            __('sales_installments.guide.form.blocked.a'),
            __('sales_installments.guide.form.blocked.b'),
        ],
        __('sales_installments.guide.money_flow.title') => [
            __('sales_installments.guide.money_flow.a'),
            __('sales_installments.guide.money_flow.b'),
            __('sales_installments.guide.money_flow.e'),
        ],
    ],
])
