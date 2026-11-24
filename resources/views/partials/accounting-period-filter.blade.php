{{--
    اختيار الفترة المحاسبية.

    **ما كان**

    كل شاشة تفتح على مدًى مكتوبٍ في شيفرتها — «أول الشهر إلى اليوم» أو «أول
    السنة» — والمحاسب يكتب حدَّي فترته بيده في كل شاشة. فالرقم الذي يُقرأ في
    «قائمة الدخل» قد لا يوافق الذي يُقرأ في «ميزان المراجعة» لأنّ التاريخين
    اختلفا حرفاً.

    **ما صار**

    الفترة **علاقة**: `accounting_period_id` مختومٌ في المستند لحظة إنشائه،
    والفلتر يرسل معرّف الفترة فيُقارَن بالعمود مباشرةً — لا مدًى يُحسب ولا
    تاريخان يُقارنان. فإعادةُ ترسيم الفترات لا تُعيد تصنيف الماضي، والشاشتان
    لا تفترقان في الرقم نفسِه.

    الوسائط:
      period      ناتج `AccountingPeriodFilter::resolve()` — يُمرَّر دائماً
      colClass    أصناف العمود، لتوافق شبكة الشاشة المضيفة
      costName    اسم حقل مركز التكلفة — يُعرض الفلتر إن مُرِّر فقط
      withDates   شاشات التقارير وحدها: تسمح بمدًى تاريخيّ إلى جانب الفترة
      fromName    اسم حقل «من» حين `withDates`
      toName      اسم حقل «إلى» حين `withDates`
--}}

@php
    // القائمة من الصنف مباشرةً: القالب يُستعمل في وحدتين، ومُركِّبُ عرضٍ
    // مربوطٌ بمساحة أسماء إحداهما يترك نصف الشاشات بلا قائمة
    $reportYears = \App\Support\AccountingPeriodOptions::years();
    $reportCenters = \App\Support\AccountingPeriodOptions::costCenters();

    $colClass = $colClass ?? 'form-group col-md-3';
    $costName = $costName ?? null;
    $withDates = $withDates ?? false;
    $fromName = $fromName ?? 'from_date';
    $toName = $toName ?? 'to_date';

    // بعض الشاشات تضع `form-group` في صنف العمود وبعضها يلفّ الحقل بها
    // داخله؛ يُضاف اللفّ حين لا يكون في الصنف كي لا تُضاعَف الهوامش
    $needsGroup = $needsGroup ?? ! str_contains($colClass, 'form-group');

    $applied = $period ?? [];
    $selected = (string) request('period_id', $applied['period_id'] ?? '');
@endphp

<div class="{{ $colClass }}">
    @if ($needsGroup)<div class="form-group">@endif
    <label for="period_id">الفترة المحاسبية</label>

    <select name="period_id" id="period_id" class="form-control" data-report-period
        data-from-name="{{ $fromName }}" data-to-name="{{ $toName }}">
        {{--
            السنةُ خيارٌ أوّلاً وفتراتُها تحتها.

            النطاقُ الافتراضي هو السنة المالية الجارية لا فترةٌ واحدة:
            المحاسبُ يعمل على سنته — يقارن الشهرَ بالشهر ويراجع ما مضى منها —
            والفترةُ وحدةُ إقفالٍ لا وحدةُ عرض.
        --}}
        @foreach ($reportYears as $reportYear)
            <option value="year:{{ $reportYear->id }}"
                data-from="{{ \Illuminate\Support\Carbon::parse($reportYear->start_date)->toDateString() }}"
                data-to="{{ \Illuminate\Support\Carbon::parse($reportYear->end_date)->toDateString() }}"
                @selected($selected === 'year:' . $reportYear->id)>
                {{ $reportYear->name }}{{ $reportYear->status !== 'open' ? ' (مقفلة)' : '' }}
            </option>

            @foreach ($reportYear->periods as $reportPeriod)
                <option value="{{ $reportPeriod->id }}"
                    data-from="{{ $reportPeriod->start_date?->toDateString() }}"
                    data-to="{{ $reportPeriod->end_date?->toDateString() }}"
                    @selected($selected === (string) $reportPeriod->id)>
                    &nbsp;&nbsp;— {{ $reportPeriod->name }}
                </option>
            @endforeach
        @endforeach

        {{--
            مخرجان صريحان: العرضُ محصورٌ في السنة، ومن أراد التاريخَ كلَّه —
            بحثاً عن سندٍ قديم — بقي بغيرهما يُقلّب السنواتِ واحدةً واحدة، أو
            ظنّ أنّ السجلّ ضاع.
        --}}
        <option value="all" data-from="" data-to="" @selected($selected === 'all')>كل الفترات</option>

        {{-- غيرُ المصنَّف: مستنداتٌ تاريخُها خارج التقويم، تُرى لتُصحَّح --}}
        <option value="none" data-from="" data-to="" @selected($selected === 'none')>غير مصنَّفة (بلا فترة)</option>
    </select>

    @if (! empty($applied['no_calendar']))
        {{--
            لا سنةَ مالية في النظام: تُعرض العملياتُ كلُّها، لأنّ الحصرَ
            بمعيارٍ لا وجود له يُخفي بياناتٍ قائمةً بلا سببٍ يقوله للمستخدم.
        --}}
        <small class="stk-help text-danger d-block">
            <i class="feather icon-alert-triangle"></i>
            لا سنة مالية معرَّفة — تُعرض العمليات كلُّها.
            @if (Route::has('AccountingPeriods.index'))
                <a href="{{ route('AccountingPeriods.index') }}">أضف سنة مالية</a>
            @endif
        </small>
    @elseif (! empty($applied['unconfigured']))
        {{--
            الخللُ يُعلَن ليُصلَح، لا يُداوى بعرضٍ منفلت. و«لا فترةَ تحوي
            اليوم» إمّا أنّ الفترات لم تُولَّد، وإمّا أنّ في التقويم فجوة.
        --}}
        <small class="stk-help text-danger d-block">
            <i class="feather icon-alert-triangle"></i>
            لا فترة محاسبية تغطّي اليوم داخل <strong>{{ $applied['label'] ?? '—' }}</strong> — أكمل فترات السنة.
            @if (Route::has('AccountingPeriods.index'))
                <a href="{{ route('AccountingPeriods.index') }}">اضبط الفترات</a>
            @endif
        </small>
    @elseif (! empty($applied['is_default']) && ! empty($applied['label']))
        <small class="stk-help">النطاق الافتراضي: {{ $applied['label'] }}</small>
    @endif

    @if ($needsGroup)</div>@endif
</div>

@if ($withDates)
    {{--
        المدى التاريخيّ في التقارير وحدها.

        التقريرُ قد يُطلب على مدًى يقطع الفترات — كأسبوعٍ بين شهرين، أو مقارنةٍ
        بين سنتين — والقائمةُ التشغيلية لا تُطلب كذلك: هي دفترُ فترة. فبقاءُ
        الحقلين في كلّ شاشة يُغري بأن يُقرأ الدفترُ على مدًى لا يوافق فترةً،
        فتختلف مجاميعُه عن الإقفال.
    --}}
    <div class="{{ $colClass }}">
        @if ($needsGroup)<div class="form-group">@endif
        <label for="{{ $fromName }}">من تاريخ</label>
        <input type="date" name="{{ $fromName }}" id="{{ $fromName }}" class="form-control"
            value="{{ is_string(request($fromName)) ? request($fromName) : '' }}">
        @if ($needsGroup)</div>@endif
    </div>

    <div class="{{ $colClass }}">
        @if ($needsGroup)<div class="form-group">@endif
        <label for="{{ $toName }}">إلى تاريخ</label>
        <input type="date" name="{{ $toName }}" id="{{ $toName }}" class="form-control"
            value="{{ is_string(request($toName)) ? request($toName) : '' }}">
        <small class="stk-help">مدًى حرّ — يتقدّم على الفترة المختارة.</small>
        @if ($needsGroup)</div>@endif
    </div>
@endif

@if ($costName && $reportCenters->isNotEmpty())
    <div class="{{ $colClass }}">
        @if ($needsGroup)<div class="form-group">@endif
        <label for="{{ $costName }}">مركز التكلفة</label>
        <select name="{{ $costName }}" id="{{ $costName }}" class="form-control">
            <option value="">كل المراكز</option>
            @foreach ($reportCenters as $reportCenter)
                <option value="{{ $reportCenter->id }}" @selected(request($costName) == $reportCenter->id)>
                    {{ $reportCenter->code ? $reportCenter->code . ' — ' : '' }}{{ $reportCenter->name }}
                </option>
            @endforeach
        </select>
        @if ($needsGroup)</div>@endif
    </div>
@endif

@once
    @push('scripts')
        <script>
            /*
                اختيارُ فترةٍ يملأ حقلَي التاريخ حيث وُجدا (شاشات التقارير).

                البحث بالاسم لا بالمعرّف: تقارير الوحدة لا تُسمّي حقولها بأسماء
                موحّدة، وكثيرٌ منها بلا `id` أصلاً.
            */
            document.querySelectorAll('[data-report-period]').forEach(function (picker) {
                var form = picker.closest('form');
                if (! form) return;

                var from = form.querySelector('[name="' + picker.dataset.fromName + '"]');
                var to = form.querySelector('[name="' + picker.dataset.toName + '"]');

                picker.addEventListener('change', function () {
                    var option = picker.options[picker.selectedIndex];
                    if (! option) return;

                    // «كل الفترات» و«بلا فترة» لا مدى لهما: تُمسح الحدود
                    if (! option.dataset.from) {
                        if (from) from.value = '';
                        if (to) to.value = '';

                        return;
                    }

                    if (from) from.value = option.dataset.from;
                    if (to) to.value = option.dataset.to;
                });
            });
        </script>
    @endpush
@endonce
