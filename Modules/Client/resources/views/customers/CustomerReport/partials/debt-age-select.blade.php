{{--
    مُنتقي «عمر الدين» — يُبقي ما تجاوز عمرُه عدداً من الأيام ({@see \App\Support\DebtAge}).

    مشتركٌ بين أرصدة العملاء والموردين وأعمار ديونهم (أستاذاً وفواتير)،
    فتُكتب الخياراتُ مرّةً واحدة ويُقرأ المعاملُ نفسُه (`debt_age`) في المتحكّمات الستّة.

    المعاملاتُ مسبوقةٌ بـ`debtAge` كي لا يلتقط الـinclude متغيّراً من الشاشة
    (`$label`، `$class`…) — راجع رأس partials.report-pager.

    @param ?string $debtAgeLabel  عنوانُ الحقل (الافتراض «عمر الدين»)
    @param ?string $debtAgeHint   ما يعنيه الفلترُ في هذه الشاشة، تحت الحقل
    @param ?string $debtAgeClass  أصنافُ الحقل (الافتراض `form-control rpt-select2`)
--}}
@php
    $daCurrent = (string) request(\App\Support\DebtAge::PARAM, '');
    $daLabel = is_string($debtAgeLabel ?? null) ? $debtAgeLabel : 'عمر الدين';
    $daHint = is_string($debtAgeHint ?? null) ? $debtAgeHint : null;
    $daClass = is_string($debtAgeClass ?? null) ? $debtAgeClass : 'form-control rpt-select2';
    $daOptions = \App\Support\DebtAge::OPTIONS;
    if ($daCurrent !== '' && ! in_array((int) $daCurrent, $daOptions, true) && (int) $daCurrent > 0) {
        // قيمةٌ من رابطٍ محفوظ خارج القائمة — تُعرض كي لا يبدو الفلترُ فارغاً وهو مطبَّق
        $daOptions[] = (int) $daCurrent;
        sort($daOptions);
    }
@endphp

<label for="debt_age">{{ $daLabel }}</label>
<select name="{{ \App\Support\DebtAge::PARAM }}" id="debt_age" class="{{ $daClass }}" data-placeholder="كل الأعمار">
    <option value="">كل الأعمار</option>
    @foreach ($daOptions as $daDays)
        <option value="{{ $daDays }}" {{ $daCurrent === (string) $daDays ? 'selected' : '' }}>{{ \App\Support\DebtAge::label($daDays) }}</option>
    @endforeach
</select>
@if ($daHint)
    <small class="text-muted d-block mt-1">{{ $daHint }}</small>
@endif
