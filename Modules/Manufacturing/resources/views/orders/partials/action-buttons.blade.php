{{--
    أزرار أمر التصنيع حسب حالته.

    كانت خمس كتل `elseif` تعيد كتابة الأزرار نفسها لحالاتٍ متداخلة، وفيها
    زرّان («إرجاع المواد») بروابط `href="#"` لا تذهب إلى شيء، وزرّ «نسخ»
    بـ`href=""` يعيد تحميل الصفحة. الآن الأزرار تُشتقّ من الحالة، وما لا
    وجهة له حُذف.
--}}

@php
    /*
        الأزرار من آلة الحالات لا من مقارنات نصّية.

        كانت الشروط تقارن `$order->status` بسلاسل مكتوبة هنا — ونسخةٌ
        منها في `received-materials-tab` وأخرى في `finish-order-modal`
        وثالثة في `returned-materials-tab`، فتتباعد. والآن مصدرٌ واحد:
        الـEnum يعرف ما يجوز من كل حالة.
    */
    $status = \App\Enums\ManufacturOrderStatus::fromLegacy($order->status)
        ?? \App\Enums\ManufacturOrderStatus::Pending;

    $canReceive = $status->canReceiveMaterials();
    $canReturn = $status->canReturnMaterials()
        && Route::has('manufacturing.orders.show-return-materials');
    $canFinish = $status->canComplete();
    $isCompleted = $status->canUndoCompletion();
    $isClosed = $status === \App\Enums\ManufacturOrderStatus::Closed;

    /*
        «تعديل» للأمر الجديد وحده، و«حذف» لما يُحذف فعلاً.

        كان زرُّ التعديل يظهر في كل حالةٍ غير «مغلق»، والحفظُ يُرفض بعد
        استلام المواد — يفتح المستخدم النموذج ويعدّل ثمّ يُردّ. وكذلك
        «حذف» كان معروضاً دائماً والخادمُ لا يحذف إلّا الجديد والملغى.
    */
    $canEdit = $status->isEditable();
    $canDelete = $status->isDeletable();
    $canCancel = $status->canCancel() && Route::has('manufacturing.orders.cancel');
@endphp

@if ($canReceive)
    <a href="{{ route('manufacturing.orders.show-receive-materials', $order->id) }}" class="btn btn-primary">
        <i class="feather icon-download"></i> استلام المواد
    </a>
@endif

@if ($canReturn)
    <a href="{{ route('manufacturing.orders.show-return-materials', $order->id) }}" class="btn btn-outline-warning">
        <i class="feather icon-rotate-ccw"></i> إرجاع المواد
    </a>
@endif

@if ($canFinish)
    <button type="button" class="btn btn-outline-success"
            data-toggle="modal" data-bs-toggle="modal"
            data-target="#finishOrderModal" data-bs-target="#finishOrderModal">
        <i class="feather icon-check"></i> إنهاء الأمر
    </button>
@endif

@if ($isCompleted)
    <button type="submit" form="undoCompletionForm" class="btn btn-outline-warning confirm-undo">
        <i class="feather icon-rotate-ccw"></i> التراجع عن الإنهاء
    </button>
    <button type="submit" form="closeOrderForm" class="btn btn-outline-secondary confirm-close">
        <i class="feather icon-lock"></i> إغلاق
    </button>
@endif

@if ($isClosed)
    <button type="submit" form="reopenOrderForm" class="btn btn-outline-primary confirm-reopen">
        <i class="feather icon-unlock"></i> إعادة الفتح
    </button>
@endif

@if ($canEdit && hasPermission('stock.manufacturing_orders.edit'))
    <a href="{{ route('manufacturing.orders.edit', $order->id) }}" class="btn btn-outline-primary">
        <i class="feather icon-edit-2"></i> تعديل
    </a>
@endif

{{-- الإلغاء بسببٍ إلزاميّ — النافذة في `modals.blade.php` --}}
@if ($canCancel && hasPermission('stock.manufacturing_orders.cancel'))
    <button type="button" class="btn btn-outline-danger"
            data-toggle="modal" data-bs-toggle="modal"
            data-target="#cancelOrderModal" data-bs-target="#cancelOrderModal">
        <i class="feather icon-x-circle"></i> إلغاء الأمر
    </button>
@endif

<div class="btn-group">
    <button type="button" class="btn btn-outline-secondary dropdown-toggle"
            data-bs-toggle="dropdown" aria-expanded="false">
        <i class="feather icon-more-horizontal"></i> المزيد
    </button>
    <div class="dropdown-menu dropdown-menu-end">
        <a class="dropdown-item" href="javascript:void(0)" onclick="addNoteOrAttachment()">
            <i class="feather icon-plus"></i> إضافة ملاحظة
        </a>
        <a class="dropdown-item" href="javascript:void(0)" onclick="viewAllNotes()">
            <i class="feather icon-list"></i> عرض الملاحظات
        </a>
        @if ($canDelete)
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger" href="javascript:void(0)"
               data-toggle="modal" data-bs-toggle="modal"
               data-target="#modal_DELETE{{ $order->id }}" data-bs-target="#modal_DELETE{{ $order->id }}">
                <i class="feather icon-trash-2"></i> حذف
            </a>
        @endif
    </div>
</div>
