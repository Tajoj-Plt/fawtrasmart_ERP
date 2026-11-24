{{--
    مُنتقي عدد السجلّات في الصفحة — نظير `stock::components.per-page`.

    كان يُكتب في كل شاشة بيدها: «عرض:» ثم `form-select` عائم ثم كلمة
    «سجل»، بثلاثة عناصر متباعدة لا يجمعها شيء. هنا عنصر واحد مضمومٌ في
    إطار، والكلمتان جزء منه.

    القيم المسموحة مقصورة على `$sizes`: البند 6.3 في تقرير التحسين يرصد
    `per_page` غير مُتحقَّق منه كثغرة إنهاك — قائمة مغلقة في الواجهة لا
    تُغني عن التحقّق في المتحكّم، لكنّها تمنع الحالة العابرة.

    الوسائط (كلها اختيارية):
      id        مُعرّف الحقل
      name      اسم الحقل حين يكون داخل نموذج يُرسَل
      sizes     الخيارات المتاحة
      current   القيمة المختارة
      onchange  سطر JS عند التغيير
--}}
@php
    $id = (isset($id) && is_string($id) && $id !== '') ? $id : 'perPage';
    $sizes = (isset($sizes) && is_array($sizes) && $sizes !== []) ? $sizes : [10, 25, 50, 100];
    $current = (isset($current) && is_scalar($current)) ? $current : request('per_page', $sizes[0]);

    // اسم الحقل وسطر التغيير قد يصلان من نطاق الأب: لا يُستعملان إلّا نصّاً
    $name = (isset($name) && is_string($name) && $name !== '') ? $name : null;
    $onchange = (isset($onchange) && is_string($onchange) && $onchange !== '') ? $onchange : null;
@endphp

<div class="sl-perpage">
    <label for="{{ $id }}">{{ __('sales_settings.ui.per_page_show') }}</label>

    <select id="{{ $id }}" @if ($name) name="{{ $name }}" @endif
            @if ($onchange) onchange="{{ $onchange }}" @endif
            class="sl-perpage__select" aria-label="{{ __('sales_settings.ui.per_page_aria') }}">
        @foreach ($sizes as $size)
            <option value="{{ $size }}" {{ (string) $current === (string) $size ? 'selected' : '' }}>
                {{ $size }}
            </option>
        @endforeach
    </select>

    <span class="sl-perpage__unit">{{ __('sales_settings.ui.per_page_unit') }}</span>
</div>
