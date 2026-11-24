{{--
    ذيلُ نموذج الفلترة في تقارير الموظفين: «عرض التقرير» و«إعادة تعيين».
    التصديرُ والطباعةُ في رأس الجدول (`_table_tools`) لا هنا.

    @param string $reset  رابطُ التقرير بلا فلاتر
--}}
<div class="ag-filter-foot">
    <div class="ag-quick__actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
        <a href="{{ $reset ?? url()->current() }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
    </div>
</div>
