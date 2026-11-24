{{--
    ═══════════ الحسابات الافتراضية لنقاط البيع ═══════════

    **ما كان**: القسمُ يحرّر مفاتيحَ «الحسابات المحاسبية» المشتركة مباشرةً.
    وهي تخدم المنشأةَ كلَّها — مبيعاتُ الجملة والتجزئة ونقاط البيع في حسابٍ
    واحد. فمن أراد قراءةَ أداء نقاط البيع من ميزان المراجعة لزمه تقريرٌ لكل
    سؤال، ومن غيّر الربط هنا غيّره على الوحدات جميعاً.

    **وما صار**: أعمدةٌ في `pos_general_settings` — لكل فرعٍ صفُّه، فلمنشأةٍ
    بفرعين أن تفرد لكلٍّ إيرادَه وبنكَه. والترتيبُ عند القيد:

        إعدادُ الفرع  ←  إعدادُ نقاط البيع العام  ←  المشترك  ←  الثابت

    فمن لم يضبط شيئاً يبقى نظامُه عاملاً بما كان.

    ═══════════ وما لا حقلَ له عمداً ═══════════

    **الخزينةُ والمخزون** ليسا إعداداً بل **عهدةُ الموظّف**:

      • الخزينة من `treasury_employees` — لكل كاشيرٍ خزينتُه، وقبضُه يدخلها
        هي. وثابتٌ واحدٌ يجمع نقدَ الجميع في عهدةٍ لا صاحبَ لها، فلا يُعرف
        على مَن الفرق عند الإقفال.

      • والمخزون من **مستودع الجهاز** — والبيعُ يُخصم من مستودعٍ بعينه،
        فحسابُه يتبعه.

    وحقلٌ لهما يوهم بخيارٍ ليس خياراً، ويُغري بضبطٍ يُبطل التتبّع.

    ═══════════ ولا بطاقةَ حولَه ═══════════

    القسمُ لوحةُ تبويبٍ داخل بطاقة الإعدادات، و«بطاقةٌ داخل بطاقة» تُضاعف
    الحدَّ والظلَّ والحشو فيبدو القسمُ دخيلاً على الشاشة.
--}}
<div class="stk-filters__head">
    <p class="stk-help mb-0">
        خاصّةٌ بنقاط البيع — تعديلُها <strong>لا يمسّ</strong> بقيّةَ الوحدات،
        وما تُرك فارغاً يرث «الحسابات المحاسبية» المشتركة. والمعروضُ أوراقُ
        الشجرة وحدها: الأبُ مجموعٌ لا يُرحَّل إليه.
    </p>

    @if (Route::has('AccountingSettings.index'))
        <a href="{{ route('AccountingSettings.index') }}"
           class="btn btn-sm btn-outline-secondary flex-shrink-0">
            <i class="feather icon-external-link"></i> الحسابات المشتركة
        </a>
    @endif
</div>

@php
    $groups = [
        'قيدُ الفاتورة' => ['sales_account_id', 'sales_returns_account_id', 'cost_of_sales_account_id', 'discount_account_id', 'vat_account_id'],
        'تحصيلُ البطاقات' => ['default_bank_account_id'],
        'فرقُ الدرج عند الإقفال' => ['cash_over_account_id', 'cash_short_account_id'],
    ];

    $groupIcons = [
        'قيدُ الفاتورة' => 'file-text',
        'تحصيلُ البطاقات' => 'credit-card',
        'فرقُ الدرج عند الإقفال' => 'shuffle',
    ];

    $byKey = collect($accountKeys)->keyBy('key');
@endphp

@foreach ($groups as $groupLabel => $keys)
    <div class="stk-section__head">
        <i class="feather icon-{{ $groupIcons[$groupLabel] ?? 'circle' }}"></i>
        <h6 class="stk-section__title">{{ $groupLabel }}</h6>
    </div>

    <div class="row">
        @foreach ($keys as $key)
            @php $entry = $byKey[$key] ?? null; @endphp
            @continue (! $entry)

            @php
                $current = old($key, $posSettings->{$key} ?? null);

                // الموروثُ من المشترك — يُعرض حين لا تجاوزَ هنا
                $shared = \App\Support\AccountingSettings::account($entry['shared']);
            @endphp

            <div class="col-md-4 col-sm-6">
                <div class="form-group">
                    <label for="{{ $key }}" class="{{ $entry['required'] ? 'stk-req' : '' }}">
                        {{ $entry['label'] }}
                        <i class="feather icon-help-circle stk-tip" title="{{ $entry['hint'] }}"></i>
                    </label>

                    <select id="{{ $key }}" name="{{ $key }}"
                            class="form-control select2 @error($key) is-invalid @enderror">
                        <option value="">
                            @if ($shared)
                                يرث المشترك — {{ $shared->code }} {{ $shared->name }}
                            @else
                                بلا تحديد
                            @endif
                        </option>

                        @foreach ($accountOptions as $account)
                            <option value="{{ $account->id }}" @selected($current == $account->id)>
                                {{ $account->code }} — {{ $account->name }}
                            </option>
                        @endforeach
                    </select>

                    {{--
                        الشرحُ انتقل إلى أيقونةٍ فوق: يُقرأ عند الحاجة ولا
                        يشغل سطراً تحت كل حقل. ويبقى تحتَه ما **يمنع** لا ما
                        يشرح — فهذه هي الحالةُ الوحيدة التي تكسر القيد.
                    --}}
                    @if (! $current && ! $shared)
                        <p class="stk-help mb-0 text-danger">غير مربوط، لا هنا ولا في المشترك.</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endforeach

{{--
    الخزينةُ والمخزون: يُقال أين يُحلّان بدل أن يُتركا بلا ذكرٍ فيُظنّا نقصاً.
--}}
<div class="alert alert-info mb-0">
    <i class="feather icon-info"></i>
    <strong>الخزينةُ والمخزون لا يُضبطان هنا:</strong>
    خزينةُ القبض تُحلّ من <strong>خزينة الكاشير</strong> المربوطة به في شاشة الموظفين،
    وحسابُ المخزون من <strong>مستودع الجهاز</strong> — فكلاهما عهدةٌ تتبع صاحبَها لا
    إعداداً عامّاً.
</div>
