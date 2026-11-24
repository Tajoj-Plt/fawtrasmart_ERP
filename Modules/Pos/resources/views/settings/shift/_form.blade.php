{{--
    نموذجُ الوردية — مشتركٌ بين الإضافة والتعديل.

    ═══════════════ لماذا قالبٌ واحد ═══════════════

    كانت `create` و`edit` نسختين متطابقتين تقريباً (٣٤٨ و٣٧٠ سطراً). وهما
    تتباعدان بأوّل حقلٍ يُضاف لإحداهما: يمرّ في الإنشاء ويُرفض في التعديل،
    أو يُضبط ثم يختفي عند أوّل تحرير.

    الوسائط:
      $shift  سجلُّ الوردية عند التعديل، أو null عند الإضافة
--}}
@php
    $shift = $shift ?? null;

    // القيمُ المحفوظةُ قد تكون أرقاماً أو نصوصاً بحسب ما كُتب سابقاً
    $selectedDays = array_map('strval', (array) old('days_of_week', $shift->days_of_week ?? []));
@endphp

<div class="row">

    {{-- ═══════════ التعريف ═══════════ --}}
    <div class="col-12">
        <h6 class="stk-section">التعريف</h6>
    </div>

    <div class="col-md-6 col-sm-12">
        <div class="form-group">
            <label for="name" class="stk-req">اسم الوردية</label>
            <input type="text" name="name" id="name" required minlength="3" maxlength="255"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $shift->name ?? '') }}"
                   placeholder="الوردية الصباحية">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-6 col-sm-12">
        <div class="form-group">
            <label for="category_id">التصنيف</label>
            <select name="category_id" id="category_id"
                    class="form-control select2 @error('category_id') is-invalid @enderror">
                <option value="">بلا تصنيف</option>
                @foreach ($categories as $category)
                    {{-- `data-branch` تُصفّى به القائمة بالفرع، كقائمة الموظفين --}}
                    <option value="{{ $category->id }}"
                        data-branch="{{ $category->branch_id ?? '' }}"
                        @selected(old('category_id', $shift->category_id ?? '') == $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">
                التصنيفُ اختياري.
                @permission('pos.shift_categories.view')
                    <a href="{{ route('pos.settings.shift_categories.index') }}">إدارة التصنيفات</a>
                @endpermission
            </p>
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="description">الوصف</label>
            <textarea name="description" id="description" rows="2" maxlength="1000"
                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $shift->description ?? '') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    {{-- ═══════════ المدى الزمني ═══════════ --}}
    <div class="col-12">
        <h6 class="stk-section">المدى الزمني</h6>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="form-group">
            <label for="start_time">وقت البداية</label>
            <input type="time" name="start_time" id="start_time" dir="ltr"
                   class="form-control @error('start_time') is-invalid @enderror"
                   value="{{ old('start_time', $shift?->start_time ? substr($shift->start_time, 0, 5) : '') }}">
            @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="form-group">
            <label for="end_time">وقت النهاية</label>
            <input type="time" name="end_time" id="end_time" dir="ltr"
                   class="form-control @error('end_time') is-invalid @enderror"
                   value="{{ old('end_time', $shift?->end_time ? substr($shift->end_time, 0, 5) : '') }}">
            @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">نهايةٌ أبكرُ من البداية تعني وردية تعبر منتصف الليل.</p>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="form-group">
            <label for="grace_minutes">مهلة التأخّر (دقيقة)</label>
            <input type="number" name="grace_minutes" id="grace_minutes" min="0" max="240"
                   class="form-control @error('grace_minutes') is-invalid @enderror"
                   value="{{ old('grace_minutes', $shift->grace_minutes ?? '') }}"
                   placeholder="0 = بلا مهلة">
            @error('grace_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">
                تُوسّع طرفَي الوردية معاً — فمن يفتح قبل الموعد بها أو بعده لا يُمنع.
            </p>
        </div>
    </div>

    {{--
        مفتاحُ الحالة.

        كان فوقه `<label>الحالة</label>` ثم عنوانُ المفتاح «مفعَّلة» — عنوانان
        لحقلٍ واحد، يقرأهما المستخدم سؤالاً وجواباً لا اسماً وقيمة. وكان
        `--sm` في عمودٍ من ثلاثة، فيظهر دائرةً باهتةً بلا مسارٍ يُرى.

        صار مفتاحاً كامل الحجم بعنوانٍ واحد وشرحٍ يقول **أثرَ الإطفاء** —
        وهو ما يحتاج المستخدم معرفتَه قبل أن يطفئه.
    --}}
    {{--
        المفتاحُ **خارج** `.form-group` عمداً.

        كان بداخلها فاختفى: قاعدةُ Vuexy `.form-group label` تخصيصُها (0,1,1)
        وقاعدةُ `.stk-switch` (0,1,0) — فتغلبُ الأولى و`display:flex` لا
        تُطبَّق. فيصير المسارُ والإبهامُ عنصرين كتليّين بلا أبعاد، ولا يُرى
        من المفتاح إلّا نصُّه.

        وكلُّ الاستعمالات العاملة في المشروع (إعدادات نقاط البيع، الإعدادات
        المحاسبية العامة) تضعه مباشرةً في العمود بلا لفّ.
    --}}
    <div class="col-md-6 col-sm-12">
        <label class="stk-switch" for="is_active">
            <span class="stk-switch__label">
                <span class="stk-switch__title">الوردية مفعَّلة</span>
                <span class="stk-switch__hint">
                    المعطَّلة لا تظهر في قائمة فتح الجلسة، ولا تُفتح عليها جلسةٌ جديدة —
                    والجلساتُ القائمة عليها تبقى كما هي.
                </span>
            </span>

            {{-- الحقلُ المخفي يضمن وصول «مطفأ»: خانةُ الاختيار غير
                 المحدَّدة لا تُرسَل أصلاً --}}
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $shift->is_active ?? true))>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
        </label>
    </div>

    {{--
        أيّامُ الدوام — `days_of_week` مصفوفةٌ من ٠ إلى ٦ كما يتحقّق
        `StoreShiftRequest`. وتركُها فارغةً يعني كلَّ الأيام.
    --}}
    <div class="col-12">
        <div class="form-group">
            <label class="d-block">أيام الدوام</label>

            <div class="stk-picks">
                @foreach ($weekDays as $value => $label)
                    <label class="stk-pick" for="day_{{ $value }}">
                        <input type="checkbox" id="day_{{ $value }}" name="days_of_week[]"
                               value="{{ $value }}" @checked(in_array((string) $value, $selectedDays, true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            @error('days_of_week')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">بلا اختيار = كلُّ الأيام.</p>
        </div>
    </div>

    {{-- ═══════════ الارتباط ═══════════ --}}
    <div class="col-12">
        <h6 class="stk-section">الارتباط والمرفقات</h6>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="branch_id" class="stk-req">الفرع</label>

            @if ($canSelectBranch)
                <select name="branch_id" id="branch_id"
                        class="form-control select2 @error('branch_id') is-invalid @enderror" required>
                    <option value="">اختر الفرع…</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}"
                            @selected(old('branch_id', $shift->branch_id ?? $currentBranchId) == $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            @else
                <input type="text" class="form-control" readonly
                       value="{{ $branches->firstWhere('id', $shift->branch_id ?? $currentBranchId)->name ?? '—' }}">
                <input type="hidden" name="branch_id" id="branch_id" value="{{ $shift->branch_id ?? $currentBranchId }}">
                <p class="stk-help mb-0">الفرع يُحدَّد من سجلّك الوظيفي.</p>
            @endif

            @error('branch_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            <x-create-first :count="$canSelectBranch ? $branches->count() : 1" route="branches.create"
                permission="branches.create" label="فرع"
                hint="الوردية تتبع فرعاً، ولا فروع متاحة لك." />
        </div>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="user_id">الموظف المسؤول</label>
            <select name="user_id" id="user_id"
                    class="form-control select2 @error('user_id') is-invalid @enderror">
                <option value="">بلا تحديد</option>
                @foreach ($users as $user)
                    {{-- `data-branch` هو ما تُصفّى به القائمة — انظر [_branch_employees] --}}
                    <option value="{{ $user->id }}"
                        data-branch="{{ $user->employee->branch_id ?? '' }}"
                        @selected(old('user_id', $shift->user_id ?? '') == $user->id)>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
            @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror

            <x-create-first :count="$users->count()" route="employee.create" label="موظّف"
                hint="لا موظفين لهم حسابُ دخول بعد." />
        </div>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="attachment">مرفق</label>
            <input type="file" name="attachment" id="attachment"
                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                   class="form-control @error('attachment') is-invalid @enderror">
            @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">pdf أو doc أو صورة، بحدّ 10 ميغابايت.</p>

            @if ($shift?->attachment)
                <p class="stk-help mb-0">
                    <a href="{{ Storage::url($shift->attachment) }}" target="_blank" rel="noopener">
                        <i class="feather icon-paperclip"></i> المرفقُ الحالي
                    </a>
                    — رفعُ غيرِه يستبدله.
                </p>
            @endif
        </div>
    </div>
</div>

{{-- تصفيةُ الموظفين بالفرع المختار — انظر [devices/_form] للعلّة نفسها --}}
@include('pos::settings._branch_employees', ['selectId' => 'user_id', 'branchId' => 'branch_id'])
