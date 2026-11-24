{{--
    حقل «تابع لـ» في نماذج الإدخال — الجهةُ التي يُنسب إليها المستند.

    المُنتقي المتدرّج نفسُه في الفلاتر، يكتب في `branch_id` مباشرةً.
    الافتراضُ عند الإنشاء فرعُ الشريط العلويّ؛ وعند التعديل ما حُفظ.
    والخادم يفحص المختار ([ScopesToBranch::documentBranchId]) — معرّفٌ خارج
    النطاق يُهمَل ويُختم بفرع الشريط.

    $value   الجهةُ المحفوظة (تعديل) — اختياري
    $locked  true حين لا يُنقل المستند بعد حفظه: يُعرض الاسمُ للقراءة وحدَها
--}}

@php
    $afNodes = \Modules\Manufacturing\Support\BranchTree::nodes();
    $afValue = old('branch_id', $value ?? current_branch_id());
@endphp

@if ($locked ?? false)
    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label>تابع لـ</label>
            <input type="text" class="form-control" disabled
                   value="{{ \Modules\Manufacturing\Support\BranchTree::name($value ?? null) }}">
            <span class="stk-help">الجهة تُختم مع المستند عند إنشائه ولا تُنقل بعده</span>
        </div>
    </div>
@elseif (count($afNodes) > 0)
    @include('reports.partials.affiliation-tree', [
        'treeNodes' => $afNodes,
        'name' => 'branch_id',
        'showExact' => false,
        'label' => 'تابع لـ',
        'value' => $afValue,
    ])
@endif
