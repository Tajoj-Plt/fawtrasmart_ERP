{{--
    محتوى جسم الجدول الشجريّ — موضعٌ واحد تقرؤه الصفحةُ عند فتحها ويقرؤه
    طريقُ `tree-rows` بعد كلّ إضافةٍ أو تعديلٍ أو حذف. فما يُبنى في الخادم
    لا يفترق عمّا يُبنى بعد الحفظ.
--}}
@forelse ($roots as $center)
    @include('account::cost_centers.partials.tree-row', [
        'center' => $center,
        'level' => 0,
        'can' => $can,
    ])
@empty
    <tr>
        <td colspan="5">
            <div class="stk-empty">
                <i class="feather icon-pie-chart"></i>
                <p>لا مراكز تكلفة بعد — ابدأ بمركزٍ رئيسيّ ثمّ فرّعه.</p>
            </div>
        </td>
    </tr>
@endforelse
