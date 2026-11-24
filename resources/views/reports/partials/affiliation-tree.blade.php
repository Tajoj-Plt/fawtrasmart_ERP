{{--
    مُنتقي «تابع لـ» — الجهةُ التي يتبع لها العميل في الشجرة.

    ══════════ ما استبدله ══════════

    كان في كلّ تقريرٍ مُنتقيانِ لسؤالٍ واحد: قائمةُ «الفرع» المسطّحة وشجرةٌ
    متدرّجةٌ تحتها، يتنازعان بقاعدةٍ خفيّةٍ في المتحكّم تُبطل أحدَهما صامتاً.
    بقيت الشجرةُ وحدَها.

    ══════════ كلُّ حقلٍ يُسمّى بنوع ما يعرضه ══════════

    كانت الحقولُ تُسمّى «الفرع الرئيسي (شجرة)» ثمّ «المستوى ٢» و«المستوى ٣»
    — أرقامٌ لا تقول شيئاً. ثمّ صارت تُسمّى بنوع ما اختِير **فوقها**، وهي
    تسميةٌ تكذب: تحت «منطقة الرياض» فروعٌ، فيقول الحقلُ «تابع لمنطقة»
    والاختيارُ منه يجعل العميلَ تابعاً لفرع.

    فصار كلُّ حقلٍ يُسمّى بنوع ما **يعرضه هو**: حقلٌ خياراتُه فروعٌ اسمُه
    «تابع لفرع»، وحقلٌ خياراتُه مجموعاتٌ اسمُه «تابع لمجموعة»، فإن اختلطت
    الأنواعُ في الحقل الواحد جُمِعت («تابع لفرع أو مجموعة»). وأعلى حقلٍ
    يُسمّى كغيره تماماً — لا استثناءَ له.

    وكلُّ خيارٍ يحمل شارةَ نوعه، فيُعرف الفرعُ من المجموعة داخل القائمة نفسها.

    ══════════ والقيمةُ معرّفٌ مجرّد ══════════

    `tree_node` = معرّف العقدة في `branches`، بلا بادئةٍ ولا ترميز. البادئتان
    `b_`/`g_` القديمتان كانتا تفترضان جدولين، و`Region_groub` و`Branch` يقرآن
    الجدولَ نفسَه.

    @param array $treeNodes    خرجُ AffiliationTree::nodes()
    @param bool  $typeOnlyLabels  تسميةُ الحقول بالنوع وحدَه بلا «تابع لـ»
    @param mixed $currentNode  الجهةُ المعروضةُ ابتداءً حين لا يحمل الطلبُ واحدة —
                               لمستخدمٍ مقصورٍ على فرعه: الخادمُ يقصر تقريرَه عليه
                               على كلّ حال، فإظهارُ «الكل» في الحقل يَعِد بما لا يكون.
--}}

@php
    $atNodes = $treeNodes ?? [];

    /*
        اسمُ الحقل — وهو ما يجعل المُنتقي صالحاً للنموذج كما هو صالحٌ
        للفلتر. الافتراضُ `tree_node` هو ما تقرؤه التقاريرُ كلُّها، وما
        تناديه أزرارُ إعادة التعيين فيها بـ`$('#tree_node')`.

        ونماذجُ الحجوزات تمرّر `branch_id` فيكتب المُنتقي في الحقل الذي
        يحفظه المتحكّم مباشرةً، بلا حقلٍ وسيطٍ ولا نصٍّ يربط بينهما.
    */
    $atName = $name ?? 'tree_node';

    /* المعرّفات مشتقّةٌ من الاسم: مُنتقيان في صفحةٍ واحدة لا يتصادمان،
       والاسمُ الافتراضي يُنتج المعرّفاتِ القديمةَ نفسَها. */
    /* `fieldId`: معرّفٌ صريحٌ للحقل المخفي — لشاشةٍ يقرأ سكربتُها `#branch`
       والحقلُ يُرسَل `branch_id`، أو لنموذجٍ يُضمَّن مرّاتٍ في الصفحة نفسها. */
    $atUid = preg_replace('/[^A-Za-z0-9_]/', '_', $fieldId ?? $atName);
    $atFieldId = isset($fieldId) ? $atUid : ($atName === 'tree_node' ? 'tree_node' : $atUid);
    $atTreeId = $atName === 'tree_node' ? 'affiliationTree' : 'affiliationTree_'.$atUid;
    $atTrailId = $atName === 'tree_node' ? 'affiliationTrail' : 'affiliationTrail_'.$atUid;

    /* خانةُ «هذه الجهة وحدها» فلترٌ لا معنى له في نموذج إدخال: الحجزُ
       يُنسب إلى عقدةٍ بعينها دائماً. */
    $atShowExact = $showExact ?? ($atName === 'tree_node');

    $atLabel = $label ?? 'تابع لـ';

    /*
        `typeOnlyLabels`: يُسمّى كلُّ حقلٍ بنوع ما يعرضه وحدَه — «منطقة»،
        «مجموعة» — بلا بادئة «تابع لـ».

        سببُه أن المُنتقي يخدم سؤالين: «تابع لمن؟» في نماذج العملاء
        والتقارير، و«أيّ جهةٍ أعرض؟» في لوحة الهيكل. والبادئةُ صحيحةٌ
        في الأوّل، وفي الثاني تقول شيئاً لا يُقصد: اللوحةُ لا تُنسب
        أحداً إلى أحد، بل تختار ما يُعرض.

        والافتراضُ `false` — فما يستدعيه اليومَ لا يتغيّر بحرف.
    */
    $atTypeOnly = (bool) ($typeOnlyLabels ?? false);

    /*
        القيمةُ الابتدائية: ما في الطلب، ثمّ ما مرّرته الشاشة.

        وحقلُ النموذج يعكس الترتيب: القيمةُ المحفوظة (`value`) تسبق الطلبَ،
        وإلّا أعاد فتحُ نموذج تعديلٍ بعد بحثٍ في شاشةٍ أخرى ضبطَ الفرعَ من
        بقايا ذلك البحث.
    */
    if (isset($value) && $value !== null && $value !== '') {
        $atCurrent = (string) $value;
    } else {
        $atCurrent = (string) request($atName, request('cascade_node', request('branch', '')));

        if ($atCurrent === '') {
            $atCurrent = (string) ($currentNode ?? '');
        }
    }

    // قيمةٌ قديمة بصيغة b_12 / g_7 — يُقرأ منها الرقم
    $atCurrent = preg_replace('/^[bg]_/', '', $atCurrent);
    $atExact = request()->boolean('exact_node');
@endphp

<div class="col-12">
    <div class="rpt-field">
        <label class="d-flex align-items-center" style="gap:8px">
            <span><i class="fa fa-sitemap"></i> {{ $atLabel }}</span>
            @if ($atShowExact)
                <span class="form-check form-check-inline mb-0" style="font-weight:400">
                    <input type="checkbox" class="form-check-input" id="exact_node" name="exact_node" value="1" {{ $atExact ? 'checked' : '' }}>
                    <label class="form-check-label small text-muted" for="exact_node">هذه الجهة وحدها دون ما تحتها</label>
                </span>
            @endif
        </label>

        {{-- `treeInputClass`: صنفٌ للحقل المخفي — فلاترُ الفهارس تربط بـ`.search-input`
             مستمعَ إعادة التحميل، والمُنتقي يُطلق `change` عليه عند كلّ اختيار --}}
        <input type="hidden" name="{{ $atName }}" id="{{ $atFieldId }}" value="{{ $atCurrent }}" class="{{ $treeInputClass ?? '' }}">
        <div class="row" id="{{ $atTreeId }}"></div>

        <small class="text-muted d-block mt-1" id="{{ $atTrailId }}"></small>
    </div>
</div>

<script>
(function () {
    var NODES = @json($atNodes);
    var INITIAL = @json($atCurrent);
    var TYPE_ONLY = @json($atTypeOnly);
    var FIELD = '#' + @json($atFieldId);
    var TREE = '#' + @json($atTreeId);
    var TRAIL = '#' + @json($atTrailId);

    var tries = 0;

    /* jQuery وحدَها شرطُ البناء؛ وselect2 تحسينٌ فوقه.

       كان الشرطُ يشمل select2، فصفحةٌ تُحمّل jQuery مرّةً ثانيةً في ذيلها
       (وهي عادةٌ في عشرات الشاشات) تُسقط الإضافةَ عن النسخة الجديدة —
       فينتظر هذا الجزءُ ستَّ ثوانٍ ثمّ يستسلم، ولا يظهر للمستخدم حقلٌ
       واحد. صار يبني قوائمَ عاديةً تعمل بالكامل، ويُزيّنها بselect2 إن
       وُجدت. */
    function ready() {
        return window.jQuery && window.jQuery.fn;
    }

    function hasSelect2() {
        return !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2);
    }

    function boot() {
        var $ = window.jQuery;
        var $container = $(TREE);
        if (!$container.length || $container.data('rpt-bound')) return;
        $container.data('rpt-bound', 1);

        var byId = {};
        NODES.forEach(function (n) { byId[n.id] = n; });

        /* select2 زينةٌ تُضاف متى توفّرت: تُطبَّق على الحقل ساعةَ إنشائه إن
           كانت جاهزة، و`enhance()` تلحق ما بُني قبل وصولها. */
        function decorate($select) {
            if (!hasSelect2() || $select.data('select2')) return;

            $select.select2({
                dir: 'rtl',
                width: '100%',
                allowClear: false,
                language: { noResults: function () { return 'لا توجد نتائج'; } }
            });

            /* الصنفُ `select2` على الحاوية يجعلها هدفاً لأيّ مسحٍ عامّ
               من نوع `$('.select2').select2()` — فيُهيَّأ select2 على
               `span` لا على `select`، وتظهر حاويةٌ ثانيةٌ فارغة تُخفي
               الأصلية. الحاويةُ لا تحتاجه (أنماطُ select2 كلُّها على
               `select2-container`)، فيُنزع. */
            $select.next('.select2-container').removeClass('select2');
        }

        function enhance() {
            $container.find('select').each(function () { decorate($(this)); });
        }

        /* المستوى الأعلى = كلُّ عقدةٍ أبوها ليس في القائمة، لا ما أبوه `null`
           وحدَه. القائمةُ تصل مقصوصةً أحياناً — على فرع الشريط العلويّ، أو
           على نطاق الرؤية — فيبدأ جذرُها من منتصف الشجرة، وكان المستوى
           الأوّل يخرج فارغاً فلا يظهر حقلٌ واحد. */
        function childrenOf(parentId) {
            if (parentId === null) {
                return NODES.filter(function (n) { return !n.parent_id || !byId[n.parent_id]; });
            }

            return NODES.filter(function (n) { return n.parent_id === parentId; });
        }

        /* قائمةٌ مقصوصةٌ على جهةٍ واحدة: تلك الجهةُ هي النطاقُ أصلاً، فتُعرض
           مختارةً وما تحتها في الحقل التالي — بدل «الكل» الذي لا يعني هنا
           إلّا هي. */
        function trimmedRootId() {
            var tops = childrenOf(null);

            return (tops.length === 1 && tops[0].parent_id) ? tops[0].id : null;
        }

        function lineage(id) {
            var path = [], cur = byId[id], guard = 40;
            while (cur && guard-- > 0) { path.unshift(cur.id); cur = byId[cur.parent_id]; }
            return path;
        }

        /* عنوانُ الحقل: نوعُ ما يعرضه هو، لا نوعُ ما فوقه ولا رقمُ مستواه.
           فإن اختلطت الأنواعُ جُمِعت، وإن زادت على اثنين سقط التفصيل. */
        function levelLabel(nodes) {
            var types = [];

            nodes.forEach(function (node) {
                if (types.indexOf(node.type_label) === -1) types.push(node.type_label);
            });

            var prefix = TYPE_ONLY ? '' : 'تابع ل';

            if (types.length === 1) return prefix + types[0];
            if (types.length === 2) return prefix + types[0] + ' أو ' + types[1];

            return TYPE_ONLY ? 'العنصر' : 'تابع لـ';
        }

        function destroyFrom(level) {
            $container.find('.rpt-tree-col').each(function () {
                var $col = $(this);
                if (parseInt($col.attr('data-level'), 10) >= level) {
                    var $s = $col.find('select');
                    if (hasSelect2() && $s.data('select2')) { $s.select2('destroy'); }
                    $col.remove();
                }
            });
        }

        function updateTrail() {
            var id = parseInt($(FIELD).val(), 10);
            if (!id || !byId[id]) { $(TRAIL).text(''); return; }

            var parts = lineage(id).map(function (nid) {
                return byId[nid].name + ' (' + byId[nid].type_label + ')';
            });

            $(TRAIL).text((TYPE_ONLY ? 'المسار: ' : 'تابع لـ: ') + parts.join('  ←  '));
        }

        function addLevel(level, parentId, selectedId) {
            var nodes = childrenOf(parentId);
            if (!nodes.length) return false;

            var $col = $('<div class="col-lg-3 col-md-4 col-6 rpt-field rpt-tree-col"></div>')
                .attr('data-level', level);

            $col.append($('<label class="small text-muted mb-1"></label>').text(levelLabel(nodes)));

            var $select = $('<select class="form-control rpt-tree-select"></select>')
                .attr('data-level', level);

            $select.append($('<option value=""></option>').text(
                parentId ? ('كل ما يتبع لـ ' + byId[parentId].name) : 'الكل'
            ));

            nodes.forEach(function (node) {
                $('<option></option>')
                    .attr('value', node.id)
                    .attr('data-type', node.node_type)
                    .prop('selected', String(selectedId) === String(node.id))
                    .text(node.name + ' — ' + node.type_label)
                    .appendTo($select);
            });

            $col.append($select);
            $container.append($col);

            decorate($select);

            $select.on('change', function () {
                var val = $(this).val();
                destroyFrom(level + 1);

                if (val) {
                    $(FIELD).val(val);
                    addLevel(level + 1, parseInt(val, 10), null);
                } else {
                    // رجوعٌ مستوىً واحداً: الجهةُ صارت الأبَ لا الابن
                    $(FIELD).val(parentId || '');
                }

                updateTrail();

                /* حدثٌ أصليٌّ لا `$.trigger`: `trigger` يبلغ مستمعي jQuery وحدهم،
                   وقوائمُ المشتريات (`purchase-cycle-list.js`) تستمع بـ
                   `addEventListener` فلا يصلها شيء ولا تُعاد تعبئتها. والحدثُ
                   الأصليُّ الفقّاعيّ يبلغ الطرفين — مستمعي jQuery المباشرين
                   والمفوَّضين (`$(document).on('change', '#tree_node')`) معاً،
                   مرّةً واحدة. */
                $(FIELD)[0].dispatchEvent(new Event('change', { bubbles: true }));
            });

            return true;
        }

        function init() {
            destroyFrom(0);

            var initial = INITIAL && byId[parseInt(INITIAL, 10)] ? INITIAL : '';
            var root = trimmedRootId();

            if (!initial && root) {
                initial = String(root);
                $(FIELD).val(initial);
            }

            var path = initial ? lineage(parseInt(initial, 10)) : [];

            addLevel(0, null, path[0] || null);
            for (var i = 0; i < path.length; i++) {
                addLevel(i + 1, path[i], path[i + 1] || null);
            }

            updateTrail();
        }

        /* الدالّتان العامّتان تخصّان المُنتقي الافتراضي: أزرارُ «إعادة
           التعيين» في تقارير العملاء تناديهما بالاسم. ولمُنتقٍ مسمّىً
           تُسجَّلان باسمٍ مشتقٍّ فلا يدهس أحدُهما الآخر. */
        var SUFFIX = @json($atFieldId === 'tree_node' ? '' : '_'.$atUid);
        window['__rptInitAffiliationTree' + SUFFIX] = init;
        window['__rptEnhanceAffiliationTree' + SUFFIX] = enhance;
        init();
    }

    /* select2 تُحمَّل من ذيل الصفحة فلا تكون جاهزةً ساعةَ تنفيذ هذا الجزء.
       فتُمهَل ثانيتين، ثمّ يُبنى الحقلُ عادياً على كلّ حال — يعمل بالكامل
       بلا زينة — وتبقى المراقبةُ عشرَ ثوانٍ فإن وصلت أُلبِست الحقولُ إيّاها.

       والانتظارُ اللانهائي هو ما كان يُخلي الشاشةَ: صفحةٌ تُحمّل jQuery
       مرّةً ثانيةً في ذيلها تُسقط select2 عن النسخة الجديدة، فلا تصل أبداً
       ولا يظهر للمستخدم حقلٌ واحد. */
    (function wait() {
        if (ready() && (hasSelect2() || tries > 40)) {
            boot();

            if (!hasSelect2()) {
                var late = 0;

                (function poll() {
                    if (hasSelect2()) {
                        if (window.__rptEnhanceAffiliationTree) window.__rptEnhanceAffiliationTree();

                        return;
                    }

                    if (++late > 200) return;
                    setTimeout(poll, 50);
                })();
            }

            return;
        }

        if (++tries > 120) return;
        setTimeout(wait, 50);
    })();
})();
</script>
