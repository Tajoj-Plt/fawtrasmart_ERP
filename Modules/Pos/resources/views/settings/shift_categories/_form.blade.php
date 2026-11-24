{{--
    حقولُ تصنيف الوردية — مشتركةٌ بين الإضافة والتعديل.

    ما ليس هنا مقصودٌ غيابُه: لا وقتَ بدءٍ ولا نهاية ولا أيّامَ دوامٍ ولا
    مهلةَ تأخّرٍ ولا موظّفاً مسنَداً. التصنيفُ تسميةٌ تجمع الورديات، ولو
    حمل مدًى زمنياً لعاد العطلُ الذي جاء ليصلحه — تصنيفٌ يظهر في قائمة
    الورديات المتاحة لفتح جلسة.
--}}
@php
    $c = $category ?? null;
@endphp

<div class="row">

    <div class="col-md-6 col-sm-12">
        <div class="form-group">
            <label for="name" class="stk-req">اسم التصنيف</label>
            <input type="text" name="name" id="name" required minlength="2" maxlength="190"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $c->name ?? '') }}"
                   placeholder="ورديات نهارية">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">الاسم متفرّدٌ داخل الفرع — لا في المنشأة كلّها.</p>
        </div>
    </div>

    <div class="col-md-6 col-sm-12">
        <div class="form-group">
            <label for="branch_id" class="stk-req">الفرع</label>

            @if ($canSelectBranch)
                <select name="branch_id" id="branch_id"
                        class="form-control select2 @error('branch_id') is-invalid @enderror" required>
                    <option value="">اختر الفرع…</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}"
                            @selected(old('branch_id', $c->branch_id ?? $currentBranchId) == $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            @else
                <input type="text" class="form-control" readonly
                       value="{{ $branches->firstWhere('id', $c->branch_id ?? $currentBranchId)->name ?? '—' }}">
                <input type="hidden" name="branch_id" id="branch_id"
                       value="{{ $c->branch_id ?? $currentBranchId }}">
                <p class="stk-help mb-0">الفرع يُحدَّد من سجلّك الوظيفي.</p>
            @endif

            @error('branch_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            <x-create-first :count="$canSelectBranch ? $branches->count() : 1" route="branches.create"
                permission="branches.create" label="فرع"
                hint="التصنيف يتبع فرعاً، ولا فروع متاحة لك." />
        </div>
    </div>

    {{--
        اللونُ يُختار من لوحةٍ مضبوطة لا من مُنتقٍ حُرّ.

        لونٌ حرٌّ يُنتج تصنيفاً بأصفرَ فاقعٍ لا يُقرأ عليه نصّ، وآخرَ بلونٍ
        يطابق لونَ التنبيه فيُقرأ خطأً على أنه إنذار. والثمانيةُ هنا كلُّها
        داكنةٌ بما يكفي ليظهر عليها الأبيض.
    --}}
    <div class="col-md-8 col-sm-12">
        <div class="form-group">
            <label>لون التمييز</label>

            @php
                $palette = [
                    '#2F6F4E' => 'أخضر',    '#1D4E89' => 'أزرق',
                    '#5B4B8A' => 'بنفسجي',  '#8A5A2B' => 'بنّي',
                    '#B23A2E' => 'أحمر',    '#0F766E' => 'فيروزي',
                    '#9A3412' => 'برتقالي', '#6B7280' => 'رمادي',
                ];
                $picked = strtoupper(old('color', $c->color ?? ''));
            @endphp

            <div class="stk-swatches">
                <label class="stk-swatch">
                    <input type="radio" name="color" value="" @checked($picked === '')>
                    <span class="stk-swatch__box">
                        <span class="stk-swatch__dot stk-swatch__dot--none"></span>
                        <span class="stk-swatch__name">بلا لون</span>
                    </span>
                </label>

                @foreach ($palette as $hex => $label)
                    <label class="stk-swatch">
                        <input type="radio" name="color" value="{{ $hex }}" @checked($picked === $hex)>
                        <span class="stk-swatch__box">
                            <span class="stk-swatch__dot" style="background: {{ $hex }}"></span>
                            <span class="stk-swatch__name">{{ $label }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            @error('color')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="sort_order">ترتيب العرض</label>
            <input type="number" name="sort_order" id="sort_order" min="0" max="9999"
                   class="form-control @error('sort_order') is-invalid @enderror"
                   value="{{ old('sort_order', $c->sort_order ?? 0) }}">
            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">الأصغرُ أوّلاً، والمتساوون بالاسم.</p>
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="description">الوصف</label>
            <textarea name="description" id="description" rows="2" maxlength="1000"
                      class="form-control @error('description') is-invalid @enderror"
                      placeholder="ورديات الدوام النهاري في الفروع الرئيسية">{{ old('description', $c->description ?? '') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12">
        <label class="stk-switch" for="is_active">
            <span class="stk-switch__label">
                <span class="stk-switch__title">تصنيف مفعَّل</span>
                <span class="stk-switch__hint">
                    المعطَّلُ يبقى على وردياته ولا يُعرض خياراً عند إنشاء ورديةٍ جديدة.
                </span>
            </span>

            {{-- الحقلُ المخفي يضمن وصول «مطفأ»: خانةُ الاختيار غير
                 المحدَّدة لا تُرسَل أصلاً --}}
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $c->is_active ?? true))>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
        </label>
    </div>

</div>

{{--
    اللوحةُ اللونية مكوَّنٌ خاصٌّ بهذه الشاشة، فنمطُها هنا لا في قالب
    التصميم المشترك بين ثلاث وحدات. و`@once` تمنع تكرارَه لو ضُمّ النموذجُ
    مرّتين في صفحةٍ واحدة.
--}}
@once
@push('styles')
<style>
    .stk-swatches { display: flex; flex-wrap: wrap; gap: var(--stk-2); }

    .stk-swatch { margin: 0; cursor: pointer; }
    .stk-swatch input { position: absolute; opacity: 0; pointer-events: none; }

    .stk-swatch__box {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .34rem .6rem;
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r);
        background: var(--app-surface);
        transition: border-color .12s ease, background-color .12s ease;
    }

    .stk-swatch__dot {
        width: 15px; height: 15px; border-radius: 50%;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .12);
    }

    .stk-swatch__dot--none {
        background: repeating-linear-gradient(45deg,
            var(--app-body-bg) 0 4px, var(--stk-line) 4px 5px);
    }

    .stk-swatch__name { font-size: .82rem; font-weight: 600; color: var(--app-text); }

    .stk-swatch:hover .stk-swatch__box { background: var(--app-body-bg); }

    /* المختارُ يُعلَّم بالإطار لا باللون — فاللونُ نفسُه هو المحتوى */
    .stk-swatch input:checked + .stk-swatch__box {
        border-color: var(--app-primary);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--app-primary) 18%, transparent);
    }

    .stk-swatch input:focus-visible + .stk-swatch__box { outline: 2px solid var(--app-primary); outline-offset: 2px; }
</style>
@endpush
@endonce
