{{--
    مُنتقي العميل في تقارير العملاء — Select2 على AJAX.

    ══════════ لماذا لا `<option>` لكلّ عميل ══════════

    كانت كلُّ شاشةِ تقريرٍ تطبع ٣٨٥٢ عنصرَ `<option>` في صفحتها، تُحمَّل
    كلُّها في كلّ فتحةٍ سواءٌ أطُلب تقريرٌ أم لا. صارت تُطلب عشرين في
    الطلب من `ClientReport.searchClients`، والبقيةُ بالتمرير.

    ══════════ ولماذا يتبع بقيّةَ الفلاتر ══════════

    القائمةُ تُرسل معها قيمَ الفرع والحالة والهدف والتصنيف الحيّةَ من
    النموذج نفسِه. فمن اختار فرعاً ثمّ فتح القائمة لا يرى إلّا عملاءَ ذلك
    الفرع — وإلّا لأمكن اختيارُ عميلٍ يستحيل أن يُرجع التقريرُ له صفّاً،
    فتخرج الشاشةُ فارغةً بلا سببٍ ظاهر.

    والعنوانُ فوقه اختياري — تمرّره الشاشةُ التي تصفُّ حقولَها في شبكةٍ
    واحدة كي تستوي رؤوسُ الحقول، وتتركه الشاشةُ التي تكتفي بالنصّ داخل الحقل.

    ══════════ ولماذا صار مشتركاً ══════════

    تقاريرُ المبيعات كانت تطبع القائمةَ كاملةً في صفحتها: ٣٨٥٣ عنصرَ
    `<option>` تزن مليون حرفٍ في كلّ فتحة. صارت تستعمل هذا المُنتقيَ نفسَه،
    ولكلّ وحدةٍ مصدرُ بحثها المحروسُ بصلاحيتها هي — `url`.

    @param string        $name           اسمُ الحقل (`customer` أو `client_id`)
    @param ?string       $url            مسارُ البحث — الافتراضُ مسارُ تقارير العملاء
    @param ?\App\Models\Client $selectedClient  المختارُ حالياً — كي يظهر اسمُه بعد إعادة التحميل
    @param ?string       $placeholder
    @param ?string       $label          نصُّ العنوان فوق الحقل — بلا قيمةٍ لا يُطبع عنوان
--}}

@php
    $csName = $name ?? 'customer';
    $csId = $id ?? $csName;
    $csSelected = $selectedClient ?? null;
    $csPlaceholder = $placeholder ?? 'كل العملاء — ابحث بالاسم أو الكود أو الجوال';
    $csLabel = $label ?? null;
    $csUrl = $url ?? route('ClientReport.searchClients');
@endphp

@if ($csLabel)
    <label for="{{ $csId }}">{{ $csLabel }}</label>
@endif

<select name="{{ $csName }}" id="{{ $csId }}"
        class="form-control rpt-client-select"
        data-url="{{ $csUrl }}"
        data-placeholder="{{ $csPlaceholder }}">
    <option value=""></option>
    @if ($csSelected)
        <option value="{{ $csSelected->id }}" selected>
            {{ $csSelected->code }} - {{ $csSelected->display_name }}
        </option>
    @endif
</select>

<script>
(function () {
    /* الشاشةُ تُحمّل jQuery وselect2 في ذيلها، وهذا الجزءُ يُطبع في متنها.
       فيُنتظر توفّرهما بدل الاعتماد على ترتيبٍ لا نملكه هنا. */
    var tries = 0;

    function ready() {
        return window.jQuery && window.jQuery.fn && window.jQuery.fn.select2;
    }

    function boot() {
        var $ = window.jQuery;

        $('.rpt-client-select').each(function () {
            var $el = $(this);
            if ($el.data('rpt-bound')) return;
            $el.data('rpt-bound', 1);

            var $form = $el.closest('form');

            $el.select2({
                dir: 'rtl',
                width: '100%',
                allowClear: true,
                placeholder: $el.data('placeholder'),
                minimumInputLength: 0,
                language: { noResults: function () { return 'لا توجد نتائج'; },
                            searching: function () { return 'جارٍ البحث…'; },
                            inputTooShort: function () { return 'اكتب للبحث'; } },
                ajax: {
                    url: $el.data('url'),
                    dataType: 'json',
                    delay: 250,
                    cache: true,
                    data: function (params) {
                        // الفلاترُ الحيّةُ تُرسل مع كل طلب فتضيق القائمةُ بها
                        var payload = { q: params.term || '', page: params.page || 1 };

                        ['tree_node', 'exact_node', 'status', 'registration_stage',
                         'customer_target_id', 'category_client', 'category',
                         'neighborhood', 'sales_manager', 'added_by'].forEach(function (key) {
                            var $field = $form.find('[name="' + key + '"]');
                            if (! $field.length) return;

                            /* `val()` على خانة اختيارٍ يعيد قيمتَها («1») سواءٌ
                               أُشّرت أم لا. فكان «هذه الجهة وحدها» يُرسَل دائماً،
                               والمطابقةُ التامّةُ تُخرج القائمةَ فارغةً لكلّ جهةٍ
                               عملاؤها معلّقون على ما تحتها — يختار المستخدمُ فرعاً
                               فلا يجد عميلاً واحداً. */
                            if ($field.is(':checkbox')) {
                                if ($field.is(':checked')) payload[key] = 1;

                                return;
                            }

                            if ($field.val()) payload[key] = $field.val();
                        });

                        return payload;
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results || [],
                            pagination: { more: !!(data.pagination && data.pagination.more) }
                        };
                    }
                }
            });

            /* الصنفُ `select2` على الحاوية يجعلها هدفاً لأيّ مسحٍ عامّ من
               نوع `$('.select2').select2()`، فيُهيَّأ select2 على `span` لا
               على `select`: حاويةٌ ثانيةٌ فارغةٌ تُخفي الأصلية، والبحثُ
               يقول «لا نتائج» وخلفه القائمةُ كاملة. */
            $el.next('.select2-container').removeClass('select2');

            /* تغييرُ الفرع (أو أيّ فلترٍ يضيّق القائمة) يُبطل اختياراً صار
               خارجَ النطاق — وإبقاؤه يُخرج تقريراً فارغاً بلا تفسير. */
            $form.on('change', '[name="tree_node"], [name="registration_stage"], [name="customer_target_id"], [name="status"]', function () {
                if ($el.val()) $el.val(null).trigger('change');
            });
        });
    }

    (function wait() {
        if (ready()) { boot(); return; }
        if (++tries > 120) return;   // ستُّ ثوانٍ ثمّ يُترك الحقلُ منسدلاً عادياً
        setTimeout(wait, 50);
    })();
})();
</script>
