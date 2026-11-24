{{--
    تبويب المعلومات — بطاقة الأمر ثم أقسام التكلفة الأربعة ثم الإجمالي.
    كانت بطاقة المعلومات مكرَّرة هنا وفي `order-info`؛ صارت هناك وحدها.
--}}

<div class="tab-pane active" id="info" role="tabpanel">

    @include('manufacturing::orders.partials.order-info', ['order' => $order])

    @include('manufacturing::orders.partials.raw-materials', ['order' => $order])
    @include('manufacturing::orders.partials.expenses', ['order' => $order])
    @include('manufacturing::orders.partials.manufacturing-operations', ['order' => $order])
    @include('manufacturing::orders.partials.waste-materials', ['order' => $order])

    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="feather icon-bar-chart-2"></i> التكلفة المبدئية/الفعلية</h4>
            <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
        </div>
        <div class="card-body">
            <dl class="stk-def mb-0">
                <div class="stk-def__row">
                    <dt><strong>الإجمالي</strong></dt>
                    <dd class="stk-num stk-balance total-cost">@money($order->last_total_cost)</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
