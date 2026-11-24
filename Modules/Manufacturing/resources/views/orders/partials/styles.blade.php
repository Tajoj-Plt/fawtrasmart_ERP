{{--
    كانت هنا 115 سطراً من CSS خاص بهذه الشاشة وحدها (`materials-card`،
    `status-badge`، `empty-state`، `action-btn`…) بألوانٍ مكتوبة صراحةً لا
    تتبع الثيم. كلّها صارت من نظام تصميم المخزون:

        materials-card / materials-header → card + stk-table-card + stk-table-head
        status-badge + status-*           → partials/status (badge badge-light-*)
        empty-state                       → stk-empty
        action-btn                        → btn + btn-outline-*

    أُبقي الملف لأن `show` كان يستدعيه، وحُذف محتواه بدل تركه يزاحم الثيم.
--}}
