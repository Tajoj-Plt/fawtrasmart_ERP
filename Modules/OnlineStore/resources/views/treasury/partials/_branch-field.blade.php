{{--
    حقل فرع الخزينة/الحساب البنكي — مشترك بين الإنشاء والتعديل.

    الخزينة تُختم بفرعها عند الحفظ، وقائمة خزائن المتجر تُرشَّح به (الفرع
    المختار في الشريط وما تحته). الخيارات مقصوصةٌ على نطاق المستخدم
    (`DataScope::branchOptions()`)، ومن لا يملك اختيار فرعٍ آخر يُعرض له
    فرعه للقراءة فقط — والخادم يتجاهل ما يُرسَل خارج النطاق على أي حال.

    @param \Illuminate\Support\Collection $branches
    @param int|null $selected
--}}
@php
    $canPickBranch = \App\Support\Scopes::canSelectBranch(auth()->user());
    $selectedBranch = old('branch_id', $selected ?? null);
@endphp
<div class="form-group col-md-4">
    <label for="branch_id">الفرع</label>
    @if ($canPickBranch)
        <select class="form-control select2" id="branch_id" name="branch_id">
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected($selectedBranch == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    @else
        <input type="text" class="form-control" id="branch_id" readonly tabindex="-1"
            value="{{ optional($branches->firstWhere('id', $selectedBranch))->name ?? 'فرعك الحالي' }}">
    @endif
    <small class="text-muted">تظهر الخزينة في قائمة خزائن المتجر عند اختيار هذا الفرع (أو ما فوقه) من الشريط العلوي.</small>
    @error('branch_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
</div>
