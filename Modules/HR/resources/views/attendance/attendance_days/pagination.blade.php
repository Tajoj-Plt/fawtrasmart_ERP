{{--
    ذيلُ جدول أيام الحضور — قالبٌ واحدٌ يخدم التحميلَ الأول وردَّ
    الفلترة بـAJAX معاً (يُوضع في `#pagination-section`).

    `data-total` يقرؤه سكربت الصفحة ليحدّث العدّاد في رأس الجدول بعد كل بحث.
--}}
<span class="d-none js-att-days-total" data-total="{{ $attendance_days->total() }}"></span>
@if ($attendance_days->total() > 0)
    <div class="stk-table-foot">
        <p class="stk-table-foot__count">عرض {{ $attendance_days->firstItem() }}–{{ $attendance_days->lastItem() }} من {{ $attendance_days->total() }}</p>
        @if ($attendance_days->hasPages())
            {{ $attendance_days->onEachSide(1)->links() }}
        @endif
    </div>
@endif
