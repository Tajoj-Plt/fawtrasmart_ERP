{{--
    حقل «دوام المسؤول» — مصدرٌ واحد يُدرَج أينما سُجِّل الدوام: مودال إضافة
    موظف العميل في شاشة العميل، ونظيره في مساحة عمل الزيارة، ومودال تغيير
    الدوام. كان الحقل مكرَّراً في ثلاثة مواضع فتفترق قوائمها.

    **قائمة واحدة «من – إلى»**، لا قائمتين: قيمتها `HH:MM|HH:MM` ويفكّها
    الخادم (`ClientEmployeeNote::parseShiftRange()`). وأوّلُ خيارٍ فيها
    «دوام كامل» (قيمته `full`) لمسؤولٍ يُوجد على مدار الساعة — يُخزَّن
    `00:00 → 24:00` في العمودين نفسيهما.

    الخيارات تُبنى في المتصفّح لا في HTML: التركيبات ٥٥٢ (٢٤ ساعة × ٢٣)،
    وطبعُها في الصفحة يضيف ~٣٠ كيلوبايت لكل قائمة — وفي مساحة عمل الزيارة
    قائمتان. المبعوث هنا خريطةُ الساعات الأربع والعشرين فقط.

    و`select2` هو ما يجعل ٥٥٢ خياراً صالحةً للاستعمال أصلاً: بحثٌ بالكتابة
    بدل تمريرٍ طويل.

    الصنف `cl-shift-select` **لا `select2`** عمداً: `public/js/client/show_client.js`
    يمسح `$('.select2')` عالمياً ويجبر `dropdownParent` على `#assignEmployeeModal`
    — فقائمةٌ داخل مودالٍ آخر تُفتح خارج مودالها ويتعذّر البحث فيها. نهيّئها
    هنا بأنفسنا بالمودال الذي تعيش فيه فعلاً.

    @param string|null $selected  «HH:MM|HH:MM» لاختياره مسبقاً (اختياري)
    @param string|null $label     عنوان الحقل (اختياري)
--}}
@php
    $shiftHourMap = \App\Models\ClientEmployeeNote::shiftHourOptions();
    $shiftFullTimeValue = \App\Models\ClientEmployeeNote::FULL_TIME_VALUE;
    $shiftFullTimeLabel = \App\Models\ClientEmployeeNote::FULL_TIME_LABEL;
@endphp

<label class="form-label">{{ $label ?? 'دوام المسؤول (من – إلى)' }} <span class="text-danger">*</span></label>
<select name="shift" class="form-control cl-shift-select" data-shift-select required
    @isset($selected) data-selected="{{ $selected }}" @endisset>
    <option value="">-- اختر فترة الدوام --</option>
    {{-- ثابتٌ في HTML لا في المتصفّح: خيارٌ واحد، ويسبق تركيبات الساعات --}}
    <option value="{{ $shiftFullTimeValue }}">{{ $shiftFullTimeLabel }}</option>
</select>
<small class="text-muted d-block">
    ساعات تواجد <strong>هذا الشخص</strong>، لا ساعات فتح المحل — تُجدوَل بها الزيارة القادمة في وقتٍ تجده فيه.
    اكتب في القائمة للبحث (مثال: «9 صباحاً»). ولمن يداوم ليلاً اختر «من 8:00 مساءً إلى 4:00 صباحاً»،
    ولمن يُوجد على مدار الساعة اختر «دوام كامل».
</small>

@once
    <script>
        (function () {
            var CL_SHIFT_HOURS = @json($shiftHourMap);

            function clBuildShiftOptions(select) {
                if (select.dataset.shiftBuilt === '1') return;

                var keys = Object.keys(CL_SHIFT_HOURS);
                var frag = document.createDocumentFragment();

                keys.forEach(function (from) {
                    keys.forEach(function (to) {
                        // «من ٩ إلى ٩» مدىً بلا طول — نفس ما ترفضه قاعدة
                        // `different` في الخادم، فلا يُعرض أصلاً
                        if (from === to) return;
                        var option = document.createElement('option');
                        option.value = from + '|' + to;
                        option.textContent = 'من ' + CL_SHIFT_HOURS[from] + ' إلى ' + CL_SHIFT_HOURS[to];
                        frag.appendChild(option);
                    });
                });

                select.appendChild(frag);
                select.dataset.shiftBuilt = '1';

                /* دوامٌ قديمٌ بدقائق غير صفرية (`08:30`) لا يطابق أيّ خيار،
                   فيبقى الحقل فارغاً — وهو الصحيح: يُختار صراحةً بدل أن
                   يُعرَض وقتٌ ليس هو المحفوظ. */
                if (select.dataset.selected) {
                    select.value = select.dataset.selected;
                }
            }

            function clInitShiftSelects(root) {
                (root || document).querySelectorAll('[data-shift-select]').forEach(function (select) {
                    clBuildShiftOptions(select);

                    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
                    if (select.classList.contains('select2-hidden-accessible')) return;

                    var modal = select.closest('.modal');
                    window.jQuery(select).select2({
                        width: '100%',
                        dir: 'rtl',
                        placeholder: 'ابحث عن الفترة…',
                        // بلا هذا يُفتح المنسدل خلف المودال ويتعذّر البحث فيه
                        dropdownParent: modal ? window.jQuery(modal) : undefined,
                    });
                });
            }

            window.clInitShiftSelects = clInitShiftSelects;

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { clInitShiftSelects(); });
            } else {
                clInitShiftSelects();
            }
        })();
    </script>
@endonce
