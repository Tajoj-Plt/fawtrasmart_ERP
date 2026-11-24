{{--
    شريط «إعدادات النظام السارية هنا».

    شاشات إعدادات المبيعات تعمل بستّة إعدادات لا تُضبط فيها: العملة تُنسّق
    كل مبلغ، واللغة تضبط الاتجاه وتنسيق التاريخ، والمظهر يحقن `--app-*`
    التي بُني عليها نظام `sl-*` كلّه، والحسابات المحاسبية تستقبل قيود
    الفاتورة، والفترة المحاسبية قد تردّ الحفظ، وطرق الدفع هي ما يظهر في
    سند القبض. كان ذلك كلّه يجري في الخلفية، فيرى المستخدم مبلغاً بلا رمز
    أو حفظاً مرفوضاً ولا يعرف أين يُصلحه.

    كل بندٍ رابطٌ إلى الشاشة التي تضبطه، فالتصحيح ضغطةٌ واحدة لا رحلةُ بحث.

    الوسائط (كلها اختيارية):
      only    مفاتيح البنود المطلوبة وحدها، مثال: ['currency', 'period']
      hint    استبدال السطر الشارح تحت العنوان

    مثال:
      @include('sales::components.system-context')
      @include('sales::components.system-context', ['only' => ['currency', 'accounts', 'period']])
--}}
@php
    $ctxItems = \Modules\Sales\Support\SalesSettingsContext::items();

    $only = (isset($only) && is_array($only)) ? $only : null;
    $hint = (isset($hint) && is_string($hint) && $hint !== '') ? $hint : null;

    if ($only) {
        $ctxItems = array_values(array_filter(
            $ctxItems,
            fn (array $item) => in_array($item['key'], $only, true)
        ));
    }
@endphp

@if (! empty($ctxItems))
    <section class="sl-ctx" aria-label="{{ __('sales_settings.context.title') }}">
        <div class="sl-ctx__head">
            <h6 class="sl-ctx__title">
                <i class="feather icon-sliders" aria-hidden="true"></i>
                {{ __('sales_settings.context.title') }}
            </h6>
            <p class="sl-ctx__hint">{{ $hint ?: __('sales_settings.context.hint') }}</p>
        </div>

        <div class="sl-ctx__grid">
            @foreach ($ctxItems as $item)
                @php
                    // مسارٌ غير موجود يعني وحدة الإعدادات معطَّلة: يُعرَض البند
                    // بقيمته `div` بلا رابط بدل أن تنكسر الشاشة
                    $tag = $item['url'] ? 'a' : 'div';
                @endphp

                <{{ $tag }} class="sl-ctx__item sl-ctx__item--{{ $item['tone'] }}"
                    @if ($item['url'])
                        href="{{ $item['url'] }}"
                        title="{{ __('sales_settings.context.open') }} — {{ $item['label'] }}"
                    @endif
                >
                    <span class="sl-ctx__icon" aria-hidden="true">
                        <i class="feather icon-{{ $item['icon'] }}"></i>
                    </span>

                    <span class="sl-ctx__body">
                        <span class="sl-ctx__label">{{ $item['label'] }}</span>
                        <span class="sl-ctx__value" title="{{ $item['value'] }}">{{ $item['value'] }}</span>
                    </span>
                </{{ $tag }}>
            @endforeach
        </div>
    </section>
@endif
