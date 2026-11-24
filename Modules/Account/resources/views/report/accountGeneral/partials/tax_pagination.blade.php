{{--
    شريط ترقيمٍ يقوده السكربت — الصفحات تُجلب بـAjax فلا `Paginator` هنا.

    كان أربعةً وثلاثين سطراً تبني الأزرار الخمسة بيدها بأيقونات
    `fa-angle-double-right` — واتّجاهها مقلوبٌ في صفحةٍ من اليمين إلى اليسار:
    «الأول» يشير يميناً و«الأخير» يساراً، وهو عكس ما تعنيه في RTL.

    المعرّفات كما هي: السكربت يمسك بها بالاسم، وتغييرها يقطع الترقيم.
--}}

<div id="{{ $type }}Pagination" class="stk-table-foot" style="display: none;">
    <span class="stk-table-foot__count" id="{{ $type }}PageInfo">
        عرض 0 إلى 0 من 0 نتيجة
    </span>

    <nav aria-label="صفحات النتائج">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item disabled" id="{{ $type }}FirstPageItem">
                <span class="page-link" id="{{ $type }}FirstPage" role="button" title="الأولى">
                    <i class="feather icon-chevrons-right"></i>
                </span>
            </li>

            <li class="page-item disabled" id="{{ $type }}PrevPageItem">
                <span class="page-link" id="{{ $type }}PrevPage" role="button" title="السابقة">
                    <i class="feather icon-chevron-right"></i>
                </span>
            </li>

            <li class="page-item active" id="{{ $type }}CurrentPageItem">
                <span class="page-link stk-num" id="{{ $type }}CurrentPage">1</span>
            </li>

            <li class="page-item disabled" id="{{ $type }}NextPageItem">
                <span class="page-link" id="{{ $type }}NextPage" role="button" title="التالية">
                    <i class="feather icon-chevron-left"></i>
                </span>
            </li>

            <li class="page-item disabled" id="{{ $type }}LastPageItem">
                <span class="page-link" id="{{ $type }}LastPage" role="button" title="الأخيرة">
                    <i class="feather icon-chevrons-left"></i>
                </span>
            </li>
        </ul>
    </nav>
</div>
