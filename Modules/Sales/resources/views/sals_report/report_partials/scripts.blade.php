{{--
    سكربت تقارير المبيعات المشترك — select2 وأزرار الفترة والطباعة
    والبحث الهرمي بالشجرة (cascade selects).

    كانت هذه الأسطر تُكرَّر بنسخةٍ كاملة في كل شاشة تقرير؛ مصدرٌ واحد الآن.

    الطباعة تطبع الصفحة نفسها بقواعد `@media print` المشتركة، لا نافذةً
    جديدة يُكتب فيها HTML بـ JavaScript. النوافذ القديمة كانت تفقد ترويسة
    المنشأة والشعار ورمز العملة، وتُعيد بناء الجدول يدوياً فتسقط منه
    الأعمدة كلّما أُضيف عمود إلى الشاشة.

    @param array|null $cascadeBranches  شجرة الفروع لبناء قوائم المستويات
    @param int|null   $cascadeHeaderBranchId  فرع الهيدر — يُنتقى تلقائياً في المستوى الأول
                       فلا يعيد المستخدم اختيار «أكبر أب» في كل تقرير، ويبدأ مباشرةً
                       من المستوى الذي يليه.
--}}
@php
    // نصوص الشجرة من ملف اللغة — كانت مكتوبةً في السكربت فلا تتبع لغة المستخدم
    $rptCascadeText = [
        'branch' => __('sales_reports.filters.branch'),
        'belongsTo' => __('sales_reports.filters.belongs_to'),
        'level' => __('sales_reports.filters.level', ['n' => ':n']),
        'group' => __('sales_reports.filters.branch_group'),
        'leaf' => __('sales_reports.filters.branch_leaf'),
        'choose' => __('sales_reports.common.choose'),
        'noResults' => __('sales_reports.common.no_results'),
    ];
@endphp

<script>
    (function () {
        var CASCADE_BRANCHES = @json($cascadeBranches ?? []);
        // فرع الهيدر (users.branch_id): جذر الشجرة الذي اختاره المستخدم من الأعلى
        var CASCADE_HEADER_BRANCH = @json($cascadeHeaderBranchId ?? (auth()->user()->branch_id ?? null));
        var CASCADE_TXT = @json($rptCascadeText);

        // ===================== الفترة =====================
        /*
         * الفتراتُ الجاهزة روابطُ يحسب مداها الخادم (انظر period-tabs)، فلم
         * يبقَ لـ JavaScript إلا «مخصص»: تُبرِز زرَّها وتُنزِل المؤشّر على أول
         * حقلِ تاريخ. وأيُّ تعديلٍ يدويٍّ على أحد الحقلين ينقل الفترة إليها،
         * كي لا يبقى زرُّ «شهر» مضيئاً بينما المعروض مدىً كتبه المستخدم بيده.
         */
        function markCustomPeriod() {
            $('#periodInput').val('custom');
            // `is-active` لمظهر `ag-tab`، وصنفا الأزرار لشاشةٍ ما زالت على القديم
            $('.period-btn').removeClass('is-active');
            $('.period-btn-custom').addClass('is-active');
            $('.period-btn.btn').removeClass('btn-primary').addClass('btn-outline-primary');
            $('.period-btn-custom.btn').removeClass('btn-outline-primary').addClass('btn-primary');
        }

        // ===================== البحث الهرمي بالشجرة =====================
        function parseValue(val) {
            if (!val) return null;
            if (val.indexOf('b_') === 0) return { id: parseInt(val.substring(2)) };
            return null;
        }
        /* المستوى الأعلى = ما أبوه ليس في القائمة: القائمةُ تصل مقصوصةً على
           فرع الشريط العلويّ، فلا يبقى فيها جذرٌ أبوه `null`. */
        function getRootBranches() {
            return CASCADE_BRANCHES.filter(function (b) { return !b.parent_id || !branchExists(b.parent_id); });
        }
        function getChildren(id) {
            return CASCADE_BRANCHES.filter(function (b) { return b.parent_id == id; });
        }
        function hasChildren(id) { return getChildren(id).length > 0; }
        function branchExists(id) {
            return CASCADE_BRANCHES.some(function (b) { return b.id == id; });
        }
        function getAncestorPath(id) {
            var path = [], current = { id: parseInt(id) }, safety = 20;
            while (current && safety-- > 0) {
                path.unshift(current);
                var b = CASCADE_BRANCHES.find(function (x) { return x.id == current.id; });
                current = (b && b.parent_id && branchExists(b.parent_id)) ? { id: b.parent_id } : null;
            }
            return path;
        }
        /* عنوانُ المستوى: نوعُ العقدة المختارة فوقه، لا رقمُه — بنفس مفردات
         * شجرة الفروع وشاشة العميل («تابع لمنطقة»، «تابع لفرع»...)، مطابقةً
         * لِـ `affiliation-tree.blade.php` في تقارير العملاء. */
        function levelLabel(parentId) {
            if (!parentId) return CASCADE_TXT.belongsTo;
            var parent = CASCADE_BRANCHES.find(function (b) { return b.id == parentId; });
            return parent ? (CASCADE_TXT.belongsTo + ' ' + parent.type_label) : CASCADE_TXT.belongsTo;
        }
        function addLevel(level, parentId, selectedValue) {
            var options = (level === 0) ? getRootBranches() : getChildren(parentId);
            if (options.length === 0) return false;
            var labelText = levelLabel(parentId);
            var $col = $('<div class="col-lg-3 col-md-4 col-sm-6 col-12 rpt-field cascade-col"></div>');
            $col.append($('<label></label>').text(labelText));
            var $select = $('<select class="form-control rpt-select2 cascade-select" data-level="' + level + '"></select>');
            $select.append($('<option value=""></option>').text(CASCADE_TXT.choose));
            options.forEach(function (b) {
                var label = b.name;
                if (level > 0 && b.type_label) { label += ' — ' + b.type_label; }
                var sel = (selectedValue === ('b_' + b.id)) ? ' selected' : '';
                $select.append('<option value="b_' + b.id + '"' + sel + '>' + label + '</option>');
            });
            $col.append($select);
            $('#cascadeContainer').append($col);
            if ($.fn.select2) {
                $select.select2({
                    dir: 'rtl',
                    language: { noResults: function () { return CASCADE_TXT.noResults; } },
                    allowClear: true,
                    width: '100%'
                });
            }
            $select.on('change', function () {
                var val = $(this).val();
                $('#cascadeContainer .cascade-col').each(function () {
                    var $wrap = $(this);
                    var lvl = parseInt($wrap.find('select').attr('data-level'), 10);
                    if (lvl > level) {
                        var $s = $wrap.find('select');
                        if ($.fn.select2 && $s.length && $s.data('select2')) {
                            $s.select2('destroy');
                        }
                        $wrap.remove();
                    }
                });
                if (val) {
                    $('#cascade_node').val(val);
                    var $rgClear = $('#region_group_id');
                    if ($rgClear.length) {
                        $rgClear.val('').trigger('change');
                    }
                    var parsed = parseValue(val);
                    if (parsed && hasChildren(parsed.id)) {
                        addLevel(level + 1, parsed.id, null);
                    }
                } else {
                    var $prev = $('#cascadeContainer select[data-level="' + (level - 1) + '"]');
                    $('#cascade_node').val($prev.length ? ($prev.val() || '') : '');
                }
                // لا إرسال تلقائي — التحديث عند الضغط على «بحث»
            });
            return true;
        }
        function initCascade() {
            if (!$('#cascadeContainer').length) return;
            $('#cascadeContainer .cascade-col select').each(function () {
                var $s = $(this);
                if ($.fn.select2 && $s.data('select2')) {
                    $s.select2('destroy');
                }
            });
            $('#cascadeContainer').empty();
            var initialValue = $('#cascade_node').val();
            var path = [];
            if (initialValue) {
                var parsed = parseValue(initialValue);
                if (parsed) path = getAncestorPath(parsed.id);
            } else if (CASCADE_HEADER_BRANCH && branchExists(CASCADE_HEADER_BRANCH)) {
                // لم يُرسل الطلب أي عقدة: نبدأ من فرع الهيدر منتقى في المستوى الأول
                // (التقرير مقيَّد به أصلاً في الخادم) فلا يبقى إلا اختيار ما تحته.
                path = getAncestorPath(CASCADE_HEADER_BRANCH);
            }
            var firstSel = path[0] ? ('b_' + path[0].id) : null;
            addLevel(0, null, firstSel);
            for (var i = 0; i < path.length; i++) {
                var next = path[i + 1] || null;
                var selVal = next ? ('b_' + next.id) : null;
                addLevel(i + 1, path[i].id, selVal);
            }
        }

        $(function () {
            // select2 — تُدمَّر أولاً تفادياً للتهيئة المزدوجة مع القالب
            if ($.fn.select2) {
                $('.rpt-select2').each(function () {
                    var $el = $(this);
                    if ($el.data('select2')) { $el.select2('destroy'); }
                });
                $('.rpt-select2').select2({
                    dir: 'rtl',
                    language: { noResults: function () { return CASCADE_TXT.noResults; } },
                    allowClear: true,
                    width: '100%'
                });
            }

            /*
             * الرابط وحده يكفي (الخادم يحسب المدى من `period`)، لكنّ النقر
             * يرسل الفورم كي لا يضيع ما كتبه المستخدم في الفلاتر ولم يطبّقه
             * بعد. يُفرَّغ التاريخان لأن الخادم يقدّمهما على `period`.
             * وبلا JavaScript يبقى الرابط عاملاً بنفس النتيجة.
             */
            $('.period-btn[data-period-value]').on('click', function (e) {
                var $form = $('#filterForm');
                if (! $form.length) { return; }
                e.preventDefault();
                $('#periodInput').val($(this).data('period-value'));
                $('#date_from').val('');
                $('#date_to').val('');
                $form.submit();
            });

            $('.period-btn-custom').on('click', function () {
                markCustomPeriod();
                $('#date_from').focus();
            });

            $('#date_from, #date_to').on('change', markCustomPeriod);

            $('#printBtn').on('click', function () { window.print(); });

            initCascade();

            var $rg = $('#region_group_id');
            if ($rg.length) {
                $rg.on('change', function () {
                    if ($(this).val()) {
                        $('#cascade_node').val('');
                        initCascade();
                    }
                });
            }
        });
    })();
</script>
