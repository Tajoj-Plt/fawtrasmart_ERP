{{--
    «إضافة» أم «نقل» — يقرّر هل يفقد الموظفُ الحالي عملاءه.
    زرّان متلاصقان (segmented) وسطرُ شرحٍ واحد يتبدّل بالاختيار.

    @param string $name      اسم الحقل
    @param string $idPrefix  بادئة المعرّفات — الصفحة فيها نموذجان
    يقرأ من الأب: $allowMultiple, $maxEmployees, $defaultMode
--}}
@php
    $modeAddHint = ! $allowMultiple
        ? 'يُسنَد لمن ليس له موظف فقط — الإعدادات تسمح بموظفٍ واحد للعميل.'
        : ($maxEmployees ? 'لا يفقد أحدٌ عملاءه. يُتخطّى من بلغ '.$maxEmployees.' موظف.' : 'لا يفقد أحدٌ عملاءه — يصير للعميل موظفٌ إضافي.');
    $modeReplaceHint = 'يصير وحده على هؤلاء العملاء، ويُنزع عنهم كل موظفٍ آخر.';
@endphp
<div class="ba-mode" data-ba-mode>
    <div class="ba-seg" role="radiogroup" aria-label="طريقة التعيين">
        <input type="radio" name="{{ $name }}" id="{{ $idPrefix }}Add" value="add" data-hint="{{ $modeAddHint }}" @checked($defaultMode === 'add')>
        <label for="{{ $idPrefix }}Add"><i class="fas fa-user-plus"></i> إضافة</label>
        <input type="radio" name="{{ $name }}" id="{{ $idPrefix }}Replace" value="replace" data-hint="{{ $modeReplaceHint }}" @checked($defaultMode === 'replace')>
        <label for="{{ $idPrefix }}Replace"><i class="fas fa-exchange-alt"></i> نقل</label>
    </div>
    <small class="ba-mode__hint" data-ba-mode-hint>{{ $defaultMode === 'add' ? $modeAddHint : $modeReplaceHint }}</small>
</div>
