<script>
    /*
        تهيئةُ قوائم الاختيار في شاشات الحجوزات — موضعٌ واحد لكل الشاشات.

        ═══════════ لماذا هنا لا في كلّ شاشة؟ ═══════════

        كانت كلُّ شاشةٍ تحتاج كتلةَ select2 خاصّةً بها، والشاشةُ التي تُنسى
        تبقى بقوائمَ خامّة لا بحثَ فيها — فتفترق الشاشاتُ في شيءٍ يجب أن
        تتّفق فيه. وهو يُحمَّل من القالب الأمّ **بعد** `select2.full.min.js`
        في الذيل، فالإضافةُ جاهزةٌ ساعةَ التنفيذ.

        ═══════════ ولماذا لا `$('select').select2()`؟ ═══════════

        المسحُ العامّ يلتقط ثلاثةَ أشياءَ لا يجوز أن يلتقطها:

          1. **حاويةَ select2 نفسِها** — فيُهيَّأ select2 على `span` لا على
             `select`، وتظهر قائمةٌ ثانيةٌ فارغة فوق الحقل الممتلئ.
          2. **قوائمَ شجرة «تابع لـ»** — تُزيّن نفسَها في
             `reports.partials.affiliation-tree`، وتُبنى وتُهدَم مع كلّ
             تغيير مستوى؛ فتهيئةٌ ثانيةٌ من الخارج تترك حاوياتٍ يتيمة.
          3. **منتقي العميل** — له تهيئةٌ خاصّةٌ بـAJAX في نموذج الحجز.

        فالمسحُ محصورٌ في `.rsv-scope`، ويستثني الثلاثةَ صراحةً، ويتخطّى ما
        هُيّئ سلفاً.

        ═══════════ ولا jQuery جديد ═══════════

        محمَّلٌ في القالب الأمّ. إعادةُ تحميله تُفرغ ما سجّلته الإضافاتُ
        عليه فتموت select2 والمودالاتُ صامتةً.
    */
    (function () {
        var $ = window.jQuery;

        if (!$ || !$.fn) {
            return;
        }

        function decorate() {
            if (!$.fn.select2) {
                return false;
            }

            $('.rsv-scope select')
                .not('.rpt-tree-select')          // شجرة «تابع لـ» تُزيّن نفسَها
                .not('#rsv-client')               // منتقي العميل: تهيئة AJAX خاصّة
                .not('[data-no-select2]')         // مخرجٌ صريحٌ لمن أراد قائمةً خامّة
                .each(function () {
                    var $select = $(this);

                    // مُهيَّأٌ سلفاً — إعادةُ التهيئة تُنتج حاويةً ثانية
                    if ($select.data('select2')) {
                        return;
                    }

                    $select.select2({
                        dir: 'rtl',
                        width: '100%',
                        // القوائمُ القصيرة لا تحتاج حقلَ بحثٍ يزاحمها
                        minimumResultsForSearch: $select.find('option').length > 8 ? 0 : Infinity,
                        placeholder: $select.find('option[value=""]').first().text() || '— اختر —',
                        allowClear: !$select.prop('required') && !$select.prop('multiple'),
                        language: {
                            noResults: function () { return 'لا توجد نتائج'; },
                            searching: function () { return 'جارٍ البحث…'; },
                        },
                    });

                    /*
                        الصنفُ `select2` على الحاوية يجعلها هدفاً لأيّ مسحٍ
                        عامٍّ لاحق (`$('.select2').select2()`) في قوالبَ
                        أخرى — فتُهيَّأ مرّةً ثانيةً على `span`. يُنزع،
                        وأنماطُ select2 كلُّها على `select2-container`.
                    */
                    $select.next('.select2-container').removeClass('select2');
                });

            return true;
        }

        $(function () {
            /*
                select2 تُحمَّل من ذيل الصفحة، وقد يسبقها هذا الجزءُ في
                ترتيبٍ مختلف. فتُجرَّب مباشرةً، وإن غابت رُوقبت عشرَ ثوانٍ
                ثمّ يُستسلَم — والقوائمُ تبقى عاملةً خامّةً على كل حال، لا
                شاشةً فارغة.
            */
            if (decorate()) {
                return;
            }

            var tries = 0;

            (function poll() {
                if (decorate() || ++tries > 200) {
                    return;
                }

                setTimeout(poll, 50);
            })();
        });
    })();
</script>
