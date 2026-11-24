{{--
    مُنتقي عدد السجلّات في الصفحة.

    كان يُكتب في كل شاشة بيدها: «عرض:» ثم `form-select` عائم ثم كلمة «سجل»،
    بثلاثة عناصر متباعدة لا يجمعها شيء. هنا عنصر واحد مضمومٌ في إطار،
    والكلمتان جزء منه لا جيران له.

    الوسائط (كلها اختيارية):
      id       مُعرّف الحقل — الشاشات القديمة تستعمل perPage أو perPageSelect
      name     اسم الحقل حين يكون داخل نموذج يُرسَل
      sizes    الخيارات المتاحة
      current  القيمة المختارة
--}}

@php
    $id = $id ?? 'perPage';
    $sizes = $sizes ?? [10, 25, 50, 100];
    $current = $current ?? request('per_page', $sizes[0]);
@endphp

<div class="stk-perpage">
    <label for="{{ $id }}">عرض</label>

    <select id="{{ $id }}" @isset($name) name="{{ $name }}" @endisset
            @isset($onchange) onchange="{{ $onchange }}" @endisset
            class="stk-perpage__select" aria-label="عدد السجلات في الصفحة">
        @foreach ($sizes as $size)
            <option value="{{ $size }}" {{ (string) $current === (string) $size ? 'selected' : '' }}>
                {{ $size }}
            </option>
        @endforeach
    </select>

    <span class="stk-perpage__unit">سجلاً</span>
</div>
