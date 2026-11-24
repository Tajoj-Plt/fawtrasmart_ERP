{{--
    نافذة حذف أمر التصنيع.

    كان هذا الملف — رغم اسمه — يحوي **نسخةً ثانية من نافذة إنهاء الأمر**
    بالمعرّف نفسه `#finishOrderModal`، فيتكرّر المعرّف في الصفحة (تفتح
    بوتستراب الأولى دائماً)، ولا توجد نافذة حذفٍ أصلاً — فزرّ «حذف» الذي
    يشير إلى `#modal_DELETE{id}` لا يفتح شيئاً.
--}}

<div class="modal fade" id="modal_DELETE{{ $order->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header">
                <h5 class="modal-title">حذف أمر التصنيع</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <p class="mb-1">
                    سيُحذف الأمر <strong>{{ $order->name }}</strong>
                    <span class="stk-num">#{{ $order->code }}</span> نهائياً.
                </p>
                <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                    إلغاء
                </button>
                {{--
                    نموذج DELETE لا رابط GET.

                    كان `<a href>` على مسار GET، فأيّ `<img src="…/delete/5">`
                    في بريدٍ أو صفحة يحذف الأمر عند فتحها من متصفّح مسجَّل
                    الدخول — بلا رمز CSRF يحمي. وأيّ مسبِّق تحميل أو زاحف
                    يزور الرابط ينفّذ الحذف.

                    وبقيّة الوحدة تفعلها صحيحاً سلفاً — `indirectCosts/show`
                    و`bom/index` و`workstations/index`.
                --}}
                <form action="{{ route('manufacturing.orders.delete', $order->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="feather icon-trash-2"></i> حذف نهائي
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
