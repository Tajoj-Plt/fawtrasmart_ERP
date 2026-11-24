{{--
    جدول بنود المستند — مشترك بين شاشات عرض دورة المشتريات (أمر الشراء،
    فاتورة الشراء، الإشعار المدين، المرتجع، طلب الشراء).

    الأصناف: `po-table` تجلب هيئة جداول القسم — رأسٌ بأحرفٍ كبيرة وفواصلُ
    خفيفة وتظليلٌ عند المرور وقلبٌ إلى بطاقات على الجوّال. وكان الجدول
    `modern-table` وحدها، وهي غير معرَّفة في أيّ ورقة أنماط، فيرث تنسيق
    `.table` الخام — بينما جدول الإدخال في `partials/form` منسَّق. فاختلف
    شكلُ البنود بين شاشة الإنشاء وشاشة العرض للمستند الواحد.

    المبالغ بالعملة الافتراضية والكميات بفواصل اللغة النشطة.

    @param \Illuminate\Support\Collection $items
    @param bool $showAmounts   إخفاؤها في «طلب عرض الأسعار» فهو بلا تسعير
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;
    use Modules\Purchases\Support\OrderLine;

    $showAmounts = $showAmounts ?? true;

    /* اسم الوحدة: العمود المخزَّن أوّلاً.

       `unit_name` على `InvoiceItem` مُشتقٌّ من `product_unit_id` ويُهمل
       العمود المحفوظ، فيعود سلسلةً فارغة لبندٍ وحدتُه من وحدات الصنف —
       وتلك تُحفَظ بالاسم والمعامل وحدهما بلا صفٍّ في `product_units`.
       فكان عمود الوحدة يختفي من مستنداتٍ لها وحدات، أو يظهر بشرطة. */
    $unitOf = static function ($item): ?string {
        $stored = $item->getAttributes()['unit_name'] ?? null;

        return filled($stored) ? $stored : ($item->unit_name ?: null);
    };

    // عمود الوحدة لا يظهر إلا حين يحمل بندٌ واحد على الأقل وحدةً مسمّاة،
    // فالمستندات القديمة والأصناف بلا وحدات تبقى بجدولها كما كان
    $hasUnits = collect($items)->contains(fn ($item) => filled($unitOf($item)));

    /* عدد أعمدة الجدول — يقرؤه ذيلُ «إجمالي الخصم».
       كان الذيل يكتب `colspan` رقمين ثابتين (٥ أو ٤، ثمّ ٣) فينقص عن
       الرأس عموداً في الحالتين، ويزحف سطرُ الخصم عن عمود الإجمالي. */
    $columnCount = 4 + ($hasUnits ? 1 : 0) + ($showAmounts ? 4 : 0);
@endphp

@if ($items->isEmpty())
    <div class="po-empty">
        <i class="fas fa-box-open"></i>
        <h5>{{ __('purchase_orders.items.empty') }}</h5>
    </div>
@else
    <div class="table-responsive">
        <table class="table modern-table po-table mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width: 46px;">{{ __('purchase_orders.items.row') }}</th>
                    <th>{{ __('purchase_orders.items.item') }}</th>
                    <th>{{ __('purchase_orders.items.description') }}</th>
                    <th class="text-center">{{ __('purchase_orders.items.quantity') }}</th>
                    {{-- الوحدة عمودٌ قائمٌ بذاته — على ترتيب جدول الإنشاء
                         نفسه: الكمية ثمّ وحدتها ثمّ سعرها --}}
                    @if ($hasUnits)
                        <th class="text-center">{{ __('purchase_orders.items.unit') }}</th>
                    @endif
                    @if ($showAmounts)
                        <th class="text-center">{{ __('purchase_orders.items.unit_price') }}</th>
                        <th class="text-center">{{ __('purchase_orders.items.discount') }}</th>
                        <th class="text-center">{{ __('purchase_orders.items.tax') }}</th>
                        <th class="text-center">{{ __('purchase_orders.items.total') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $index => $item)
                    <tr>
                        <td class="text-center num" data-label="{{ __('purchase_orders.items.row') }}">{{ LanguageSettings::formatNumber($index + 1, 0) }}</td>
                        <td data-label="{{ __('purchase_orders.items.item') }}">{{ $item->item ?: ($item->product->name ?? '—') }}</td>
                        <td data-label="{{ __('purchase_orders.items.description') }}">{{ $item->description ?: '—' }}</td>
                        <td class="text-center num" data-label="{{ __('purchase_orders.items.quantity') }}">{{ LanguageSettings::formatNumber($item->quantity ?? 0, 0) }}</td>
                        @if ($hasUnits)
                            <td class="text-center" data-label="{{ __('purchase_orders.items.unit') }}">
                                @if (filled($unitOf($item)))
                                    {{ $unitOf($item) }}
                                    @if ((float) ($item->conversion_factor ?? 1) != 1)
                                        <small class="po-hint">
                                            (× {{ LanguageSettings::formatNumber($item->conversion_factor, 2) }})
                                        </small>
                                    @endif
                                @else
                                    <span class="po-unit-none">&mdash;</span>
                                @endif
                            </td>
                        @endif
                        @if ($showAmounts)
                            <td class="text-center num" data-label="{{ __('purchase_orders.items.unit_price') }}">{{ CurrencySettings::number($item->unit_price ?? 0) }}</td>
                            <td class="text-center num" data-label="{{ __('purchase_orders.items.discount') }}">{{ CurrencySettings::number($item->discount ?? 0) }}</td>
                            {{-- مبلغ الضريبة لا نسبتها، وبنوعها المضبوط:
                                 المتضمَّنة مستخرَجة من السعر لا مضافة إليه --}}
                            <td class="text-center num" data-label="{{ __('purchase_orders.items.tax') }}">
                                {{ CurrencySettings::number(OrderLine::tax($item)) }}
                                <small class="po-hint">
                                    ({{ OrderLine::taxRate($item) + 0 }}%@if (OrderLine::isTaxIncluded($item))
                                        — {{ __('purchase_orders.items.tax_included') }}
                                    @endif)
                                </small>
                            </td>
                            <td class="text-center num" data-label="{{ __('purchase_orders.items.total') }}">{{ CurrencySettings::format($item->total ?? 0) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            @if ($showAmounts)
                {{-- ذيل الجدول: مجموع خصم البنود، تحت عمود الإجمالي --}}
                @php
                    $totalDiscount = $items->sum(fn ($item) => $item->discount ?? 0);
                @endphp
                <tfoot>
                    <tr style="border-top: 2px solid var(--po-border);">
                        <td colspan="{{ $columnCount - 1 }}" style="text-align: {{ LanguageSettings::isRtl() ? 'right' : 'left' }}; font-weight: 600; padding: .75rem 1rem;">
                            {{ __('purchase_orders.totals.discount') }}:
                        </td>
                        <td class="text-center" style="font-weight: 600; padding: .75rem 1rem;">
                            {{ CurrencySettings::format($totalDiscount) }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endif
