{{--
    نوافذ أمر التصنيع مجتمعة.

    كان هذا الملف نسخةً ثالثة من جدول «عمليات التصنيع» لا يستدعيه أحد.
    صار المكان الواحد الذي تُجمع فيه نوافذ الشاشة، ومعه نماذج تغيير الحالة
    المخفيّة التي كانت مبعثرة بين الأزرار.
--}}

@include('manufacturing::orders.partials.delete-modal', ['order' => $order])
@include('manufacturing::orders.partials.finish-order-modal', [
    'order' => $order,
    'storehouse' => $storehouse ?? [],
])
@include('manufacturing::orders.partials.notes-modal', ['order' => $order])

{{-- نماذج تغيير الحالة: الأزرار تشير إليها بـ`form=` فتبقى خارج أي نموذج آخر --}}
@if (Route::has('manufacturing.orders.undo-completion'))
    <form id="undoCompletionForm" method="POST" class="d-none"
          action="{{ route('manufacturing.orders.undo-completion', $order->id) }}">
        @csrf
    </form>
@endif

@if (Route::has('manufacturing.orders.close'))
    <form id="closeOrderForm" method="POST" class="d-none"
          action="{{ route('manufacturing.orders.close', $order->id) }}">
        @csrf
    </form>
@endif

@if (Route::has('manufacturing.orders.reopen'))
    <form id="reopenOrderForm" method="POST" class="d-none"
          action="{{ route('manufacturing.orders.reopen', $order->id) }}">
        @csrf
    </form>
@endif

{{--
    إلغاء الأمر — بسببٍ إلزاميّ يُحفظ معه.

    لم يكن للإلغاء زرٌّ في أيّ شاشة: المسارُ والمتحكّم قائمان، ولا شيء
    يستدعيهما. الإلغاء يُرجع ما صُرف من مواد بتكلفته ويفكّ الحجز ويُقفل
    رصيد الأمر، فلا يبقى عليه شيء.
--}}
@php
    $cancelStatus = \App\Enums\ManufacturOrderStatus::fromLegacy($order->status)
        ?? \App\Enums\ManufacturOrderStatus::Pending;
@endphp

@if ($cancelStatus->canCancel() && Route::has('manufacturing.orders.cancel'))
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <form method="POST" action="{{ route('manufacturing.orders.cancel', $order->id) }}">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title">إلغاء أمر التصنيع</h5>
                        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-1">سيُلغى الأمر <strong>{{ $order->name }}</strong> نهائياً.</p>

                        @if ($cancelStatus->holdsMaterials())
                            <p class="stk-empty__hint">
                                المواد المصروفة للأمر (غير المُرجَعة) تعود إلى مستودعها بالتكلفة التي خرجت بها، ويُقفل رصيد الأمر.
                            </p>
                        @else
                            <p class="stk-empty__hint">يُفكّ حجز المواد إن وُجد. لا يمكن التراجع عن الإلغاء.</p>
                        @endif

                        <div class="form-group mb-0">
                            <label for="cancellation_reason" class="stk-req">سبب الإلغاء</label>
                            <textarea id="cancellation_reason" name="cancellation_reason" required maxlength="500"
                                      class="form-control" rows="3"
                                      placeholder="اكتب سبب إلغاء الأمر…">{{ old('cancellation_reason') }}</textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                            تراجع
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="feather icon-x-circle"></i> تأكيد الإلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
