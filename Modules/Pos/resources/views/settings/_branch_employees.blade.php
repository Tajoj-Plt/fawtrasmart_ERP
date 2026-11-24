{{--
    تصفيةُ قائمة الموظفين بالفرع المختار — مشتركةٌ بين نموذجَي الجهاز والوردية.

    ═══════════════ العلّة ═══════════════

    القائمةُ تعرض **كلَّ موظفٍ في نطاق المستخدم** أيّاً كان فرعُه، والفرعُ
    حقلٌ مستقلٌّ فوقها. فيُختار فرعُ «الرياض» ويُسنَد إليه موظّفُ «جدة» —
    ولا شيء يمنع: الحفظُ يقبل، والجهازُ أو الوردية يُنسبان إلى فرعٍ ومسؤولٍ
    من فرعٍ آخر. وأثرُه لا يظهر إلّا لاحقاً: تقاريرُ الفرع تعدّ جهازاً
    مسؤولُه ليس منه.

    ═══════════════ ولماذا لم تعمل النسخةُ الأولى ═══════════════

    كُتبت بـ`addEventListener` مباشرةً وتعمل عند تحليل الصفحة — وفي ذلك
    خطآن قاتلان معاً:

      • **jQuery تُحمَّل بعدها**: `master` يضع `vendors.min.js` في السطر ٢٩٤
        و`@yield('content')` في ٢٨٥. فـ`window.jQuery` غيرُ معرَّفةٍ لحظةَ
        تشغيل السكربت، وفرعُ تحديث select2 لا يُنفَّذ إطلاقاً.

      • **select2 يُطلق أحداثَ jQuery لا أحداثاً أصليّة**: فمستمعٌ مربوطٌ
        بـ`addEventListener('change')` **لا يُنبَّه** حين يختار المستخدم
        فرعاً من قائمة select2. فالتصفيةُ تقع مرّةً عند التحميل ثم لا تتحرّك
        أبداً — وهو ما رآه المستخدم: «أختار الفرع ويظهر كلُّ الموظفين».

    فالحلُّ: الانتظارُ حتى تكتمل الصفحة (`load` بعد كل السكربتات)، والربطُ
    عبر jQuery نفسِها كي تصل أحداثُ select2.

    ═══════════════ وهي دلالةٌ لا حراسة ═══════════════

    `assertEmployeeBelongsToBranch()` في المتحكّم هو المنع؛ هذه تمنع الخطأ
    قبل وقوعه لا بعده.

    الوسائط:
      $selectId   مُعرّفُ قائمة الموظفين
      $branchId   مُعرّفُ حقل الفرع
--}}
@php
    $selectId = $selectId ?? 'user_id';
    $branchId = $branchId ?? 'branch_id';

    // النصُّ يختلف بالقائمة: «لا موظفين» لا تصلح للمستودعات
    $emptyText = $emptyText ?? 'لا موظفين في هذا الفرع';
@endphp

<script>
    (function () {
        var SELECT_ID = @json($selectId);
        var BRANCH_ID = @json($branchId);
        var EMPTY_TEXT = @json($emptyText);

        function start() {
            var select = document.getElementById(SELECT_ID);
            var branch = document.getElementById(BRANCH_ID);

            if (! select || ! branch) {
                return;
            }

            /*
                الخيارات تُحفظ مرّةً واحدة قبل أيّ تصفية.

                إزالةُ العناصر من DOM ثم محاولةُ إعادتها لا تعمل: ما أُزيل
                فُقد. فالنسخةُ الكاملة تبقى هنا، وتُعاد بناءُ القائمة منها.
            */
            var all = Array.prototype.map.call(select.options, function (o) {
                return { value: o.value, text: o.text, branch: o.getAttribute('data-branch') || '' };
            });

            var placeholder = all.filter(function (o) { return o.value === ''; })[0];
            var $ = window.jQuery;
            var isSelect2 = $ && $(select).data('select2');

            function apply() {
                var wanted = branch.value || '';
                var previous = select.value;

                select.innerHTML = '';

                if (placeholder) {
                    select.add(new Option(placeholder.text, ''));
                }

                var kept = 0;

                all.forEach(function (o) {
                    if (o.value === '') {
                        return;
                    }

                    // بلا فرعٍ مختار تُعرض القائمة كاملةً،
                    // وموظّفٌ بلا فرع لا يُخفى: سجلُّه ناقصٌ لا مخالف
                    if (wanted && o.branch && o.branch !== wanted) {
                        return;
                    }

                    select.add(new Option(o.text, o.value, false, o.value === previous));
                    kept++;
                });

                /*
                    رسالةٌ حين لا يبقى أحد: قائمةٌ فارغةٌ بلا سبب تُقرأ عطباً.
                    و`disabled` كي لا تُختار.
                */
                if (kept === 0) {
                    var none = new Option(EMPTY_TEXT, '');
                    none.disabled = true;
                    select.add(none);
                }

                // select2 يبني واجهتَه مرّةً، فلا يرى تغييرَ الخيارات وحدَه
                if ($ && $(select).data('select2')) {
                    $(select).trigger('change.select2');
                }
            }

            /*
                الربطُ عبر jQuery حين توجد: select2 يُطلق أحداثَ jQuery،
                ومستمعٌ أصليٌّ لا يصله منها شيء. وبلا jQuery يُكتفى بالأصليّ
                — فالحقلُ عندئذٍ قائمةٌ عادية تُطلق `change` حقيقياً.
            */
            if ($) {
                $(branch).on('change', apply);
            } else {
                branch.addEventListener('change', apply);
            }

            // شاشةُ التعديل تفتح بفرعٍ محدَّدٍ سلفاً، فتُصفّى القائمةُ فوراً
            apply();
        }

        /*
            `load` لا `DOMContentLoaded`: الثاني يقع قبل تنفيذ سكربتات الذيل،
            فلا تكون jQuery ولا select2 جاهزتين — وهو سببُ فشل النسخة الأولى.
        */
        if (document.readyState === 'complete') {
            start();
        } else {
            window.addEventListener('load', start);
        }
    })();
</script>
