{{--
    حقول مركز التكلفة — مودالا الإضافة والتعديل يقرآن من هنا.

    كان النموذجان منسوخَين حرفاً بحرف، فأيُّ إصلاحٍ في أحدهما يُنسى في الآخر.
--}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>الاسم</label>
            <input type="text" class="form-control" name="name" required>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label>الكود</label>
            <input type="text" class="form-control stk-num" name="code" required>
            <small class="stk-help">يُقترَح تلقائياً عند اختيار مركزٍ أب.</small>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label>مركز التكلفة الأب</label>
            <select name="parent_id" class="form-control">
                <option value="">بلا أب — مركز رئيسي</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
            </select>
            <small class="text-muted d-block mt-1 parent-hint"></small>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            {{--
                **الخانةُ والأبُ وجهان لحقيقةٍ واحدة، لا حقلان.**

                كانا مستقلَّين: يُؤشَّر «رئيسي» ويُختار أبٌ معاً، فيخرج سجلٌّ
                يناقض نفسه — الشجرةُ تبنيه ابناً والإحصاءُ يعدّه رئيسياً.
                صارا مربوطَين: تأشيرُ «رئيسي» يرفع الأب، واختيارُ أبٍ يرفع
                التأشير، ورفعُ الأب يُعيد التأشير.
            --}}
            <div class="vs-checkbox-con vs-checkbox-primary mt-2">
                <input type="checkbox" name="is_main" value="1">
                <span class="vs-checkbox">
                    <span class="vs-checkbox--check">
                        <i class="vs-icon feather icon-check"></i>
                    </span>
                </span>
                <span>مركز تكلفة رئيسي ؟</span>
            </div>
        </div>
    </div>
</div>
