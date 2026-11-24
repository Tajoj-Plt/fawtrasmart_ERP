{{--
    شريط «إعدادات النظام السارية هنا» — نظير `sales::components.system-context`
    بالبادئة `cl-`.

    شاشات وحدة العملاء تعمل بعشرة إعدادات لا تُضبط فيها: المظهر يحقن
    `--app-*`، واللغة تضبط الاتجاه، والعملة تُنسّق رصيد العميل، وطرق الدفع
    هي ما يظهر في سنده، والحسابات المحاسبية تستقبل قيدَ دينه، والفترة
    المحاسبية قد تردّ الحفظ، ومعلومات المنشأة رأسُ كشف حسابه، وقوالبُ
    المستندات ما يُطبع به، والمهامُّ المجدولة ما يُشغّل تذكيرَ مواعيده،
    والإشعاراتُ ما يصل الموظف عند التعيين.

    كل بندٍ رابطٌ إلى الشاشة التي تضبطه، فالتصحيح ضغطةٌ واحدة لا رحلةُ بحث.

    الوسائط (كلها اختيارية):
      only    مفاتيح البنود المطلوبة وحدها، مثال: ['currency', 'period']
      hint    استبدال السطر الشارح تحت العنوان

    مثال:
      @include('client::components.system-context')
      @include('client::components.system-context', ['only' => ['accounts', 'period']])
--}}
@php
    $ctxItems = \Modules\Client\Support\ClientSettingsContext::items();

    // `@include` يمرّر متغيّرات القالب الأب كلَّها — ففحصُ النوع يمنع
    // متغيّراً محليّاً في الأب من أن يصير وسيطاً هنا
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
    <section class="cl-ctx" aria-label="{{ __('client_settings.context.title') }}">
        <div class="cl-ctx__head">
            <h6 class="cl-ctx__title">
                <i class="feather icon-sliders" aria-hidden="true"></i>
                {{ __('client_settings.context.title') }}
            </h6>
            <p class="cl-ctx__hint">{{ $hint ?: __('client_settings.context.hint') }}</p>
        </div>

        <div class="cl-ctx__grid">
            @foreach ($ctxItems as $item)
                @php
                    // مسارٌ غير موجود يعني وحدة الإعدادات معطَّلة: يُعرَض البند
                    // بقيمته `div` بلا رابط بدل أن تنكسر الشاشة
                    $tag = $item['url'] ? 'a' : 'div';
                @endphp

                <{{ $tag }} class="cl-ctx__item cl-ctx__item--{{ $item['tone'] }}"
                    @if ($item['url'])
                        href="{{ $item['url'] }}"
                        title="{{ __('client_settings.context.open') }} — {{ $item['label'] }}"
                    @endif
                >
                    <span class="cl-ctx__icon" aria-hidden="true">
                        <i class="feather icon-{{ $item['icon'] }}"></i>
                    </span>

                    <span class="cl-ctx__body">
                        <span class="cl-ctx__label">{{ $item['label'] }}</span>
                        <span class="cl-ctx__value" title="{{ $item['value'] }}">{{ $item['value'] }}</span>
                    </span>
                </{{ $tag }}>
            @endforeach
        </div>
    </section>
@endif
