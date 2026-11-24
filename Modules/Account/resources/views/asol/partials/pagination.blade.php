{{--
    تذييل جدول الأصول: عدّ النتائج وأزرار التنقّل.

    كان خمسةً وثمانين سطراً تبني شريط الترقيم بيدها — «الأول» و«السابق»
    و«التالي» و«الأخير» بأربع كتل `@if` متكرّرة، وأيقونات `fa-angle-double`
    بلا مراعاة لاتّجاه الصفحة. و`$assets->links()` تفعل ذلك كلّه بسطر،
    وتتبع القالب المضبوط في `AppServiceProvider`.
--}}

@if ($assets instanceof \Illuminate\Contracts\Pagination\Paginator || $assets instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <div class="stk-table-foot">
        <span class="stk-table-foot__count">
            @if ($assets->total() > 0)
                عرض <strong class="stk-num">{{ $assets->firstItem() }}</strong>
                إلى <strong class="stk-num">{{ $assets->lastItem() }}</strong>
                من <strong class="stk-num">{{ $assets->total() }}</strong> أصلاً
            @else
                لا نتائج
            @endif
        </span>

        @if ($assets->hasPages())
            {{ $assets->withQueryString()->links() }}
        @endif
    </div>
@endif
