{{--
    «تابع لـ» — حقلٌ مشترك بين فلاتر شاشات الوحدة.

    مرشِّحُ العملة كان هنا وأُزيل بطلب المستخدم: الفلاتر بلا عملة، والقوائمُ
    تعرض المستندات بعملاتها كلِّها.

    ── «تابع لـ» لا «الفرع» ────────────────────────────────────────────

    كان هنا `<select>` مسطّح باسم «الفرع» يعرض الفرعَ النشط وما تحته.
    صار مُنتقي «تابع لـ» المتدرّج نفسُه في المبيعات والمشتريات
    (`reports.partials.affiliation-tree`): كلُّ مستوىً قائمةٌ تُسمّى بنوع
    ما تعرضه، والقيمةُ معرّفُ العقدة في الحقل المخفي `branch_id`.

    والعُقدُ من [\Modules\Manufacturing\Support\BranchTree::nodes()]:
    من يقف على الفرع الرئيسيّ يرى الشجرةَ كلَّها (الرئيسيّ = المنشأة
    كلُّها)، ومن يقف على غيره يرى عقدتَه وما تحتها. والمتحكّم يفحص الوارد
    مرّةً أخرى عند التطبيق — الشاشةُ ليست حارساً.

    ── المعاملات ───────────────────────────────────────────────────────

    $currencies        مجموعة العملات المفعَّلة (اختياري)
    $selectedCurrency  رمز العملة المختارة (اختياري)
    $ajax              true للشاشات التي تُرسل النموذج بـAjax — يُسقَط
                       عندها `onchange` لأن سكربت الشاشة يتولّى الإرسال
    $withBranch        false لإخفاء «تابع لـ» (يُضمَّن في موضعٍ آخر)
--}}

@php
    $scopeTree = \Modules\Manufacturing\Support\BranchTree::nodes();
    $scopeCurrencies = $currencies ?? collect();
    $scopeSelectedCurrency = $selectedCurrency ?? null;
    $scopeAutoSubmit = ($ajax ?? false) ? '' : 'this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()';
@endphp

@if (($withBranch ?? true) && count($scopeTree) > 0)
    @include('reports.partials.affiliation-tree', [
        'treeNodes' => $scopeTree,
        'name' => 'branch_id',
        'showExact' => false,
        'label' => 'تابع لـ',
        'value' => request('branch_id'),
    ])

@endif

@once
    <script>
        /* «مسح» يعيد المُنتقي إلى «الكل» — `form.reset()` لا يمسّ الحقل المخفي */
        window.mfgResetAffiliation = function () {
            var field = document.getElementById('branch_id');
            if (field) field.value = '';
            if (window.__rptInitAffiliationTree_branch_id) window.__rptInitAffiliationTree_branch_id();
        };

        /* «بحث متقدّم» — `data-mfg-toggle` لا `data-toggle`: القالب يحمّل
           Bootstrap 4 و5 معاً، فيفتح أحدُهما اللوحة ويغلقها الآخر */
        document.addEventListener('click', function (e) {
            var button = e.target.closest ? e.target.closest('[data-mfg-toggle]') : null;
            if (! button) return;

            var panel = document.querySelector(button.getAttribute('data-mfg-toggle'));
            if (! panel) return;

            panel.classList.toggle('show');
            button.setAttribute('aria-expanded', panel.classList.contains('show') ? 'true' : 'false');
        });
    </script>
@endonce
