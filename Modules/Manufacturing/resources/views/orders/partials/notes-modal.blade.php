{{--
    نافذتا الملاحظات: الإضافة والعرض.

    كانت عناوين الطلبات مكتوبة بخطّ اليد `/manufacturing/orders/…` بلا بادئة
    اللغة والوحدة، فتردّ 404 دائماً؛ وطريق الحذف `notes/{id}` غير موجود
    (الصحيح `note/{id}`)، و`notes-count` لا وجود له أصلاً — أُزيل.
--}}

@php $orderId = $order->id; @endphp

<div class="modal fade" id="addNoteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header">
                <h5 class="modal-title">إضافة ملاحظة</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="addNoteForm" enctype="multipart/form-data">
                @csrf

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="note_process" class="stk-req">نوع الإجراء</label>
                                <select id="note_process" name="process" required class="form-control">
                                    <option value="">اختر نوع الإجراء…</option>
                                    @foreach ([
                                        'بداية التصنيع', 'استلام المواد', 'مرحلة الإنتاج', 'فحص الجودة',
                                        'انتهاء التصنيع', 'مشكلة في الإنتاج', 'تأخير في التسليم',
                                        'تحديث الكمية', 'ملاحظة عامة', 'أخرى',
                                    ] as $processOption)
                                        <option value="{{ $processOption }}">{{ $processOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="noteDate" class="stk-req">التاريخ</label>
                                <input type="date" id="noteDate" name="date" required class="form-control">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="noteTime" class="stk-req">الوقت</label>
                                <input type="time" id="noteTime" name="time" required class="form-control">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label for="note_description" class="stk-req">تفاصيل الملاحظة</label>
                                <textarea id="note_description" name="description" rows="4" required
                                          class="form-control" maxlength="1000"
                                          placeholder="اكتب تفاصيل الملاحظة هنا…"></textarea>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label for="note_attachment">مرفق</label>
                                <input type="file" id="note_attachment" name="attachment" class="form-control"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="stk-help">PDF أو DOC أو صورة — بحدّ أقصى 5 ميغابايت</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> حفظ الملاحظة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewNotesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header">
                <h5 class="modal-title">ملاحظات الأمر</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body" style="max-height:65vh; overflow-y:auto">
                <div id="notesLoading" class="text-center py-4" style="display:none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="stk-help mt-2 mb-0">جارٍ تحميل الملاحظات…</p>
                </div>

                <div id="notesContent"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                    إغلاق
                </button>
                <button type="button" class="btn btn-primary" id="addNoteFromList">
                    <i class="feather icon-plus"></i> إضافة ملاحظة
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const notesRoutes = {
        add: @json(route('manufacturing.orders.addNote', $orderId)),
        list: @json(route('manufacturing.orders.getNotes', $orderId)),
        remove: @json(route('manufacturing.orders.deleteNote', 'NOTE_ID')),
    };

    const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    function toast(message, tone) {
        const cls = tone === 'success' ? 'alert-success' : tone === 'error' ? 'alert-danger' : 'alert-info';
        const node = $('<div class="alert position-fixed" style="top:1rem; inset-inline-start:1rem; z-index:2000; max-width:22rem"></div>')
            .addClass(cls)
            .text(message);

        $('body').append(node);
        setTimeout(function () { node.fadeOut(200, function () { node.remove(); }); }, 4000);
    }

    function emptyState() {
        return '<div class="stk-empty">'
            + '<div class="stk-empty__icon"><i class="feather icon-message-square"></i></div>'
            + '<h5 class="stk-empty__title">لا ملاحظات</h5>'
            + '<p class="stk-empty__hint">أضف أول ملاحظة على هذا الأمر</p>'
            + '</div>';
    }

    function noteCard(note, index) {
        const attachment = note.has_attachment
            ? '<a href="' + escapeHtml(note.attachment_url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">'
                + '<i class="feather icon-paperclip"></i> المرفق</a>'
            : '';

        return '<li class="stk-timeline__item" id="note-' + note.id + '">'
            + '<span class="stk-timeline__dot stk-timeline__dot--log"><i class="feather icon-message-square"></i></span>'
            + '<div class="stk-timeline__body">'
                + '<p class="stk-timeline__head">'
                    + '<strong>' + escapeHtml(note.process || 'ملاحظة') + '</strong>'
                    + '<span class="stk-timeline__time stk-num"><i class="feather icon-clock"></i> '
                    + escapeHtml(note.date) + ' ' + escapeHtml(note.time) + '</span>'
                + '</p>'
                + '<div class="stk-timeline__text">' + escapeHtml(note.description) + '</div>'
                + '<p class="stk-timeline__meta">'
                    + '<span><i class="feather icon-user"></i> ' + escapeHtml(note.employee_name) + '</span>'
                    + '<span class="stk-num"><i class="feather icon-hash"></i> ' + (index + 1) + '</span>'
                    + attachment
                    + '<button type="button" class="btn btn-sm btn-outline-danger js-note-delete" data-id="' + note.id + '">'
                        + '<i class="feather icon-trash-2"></i></button>'
                + '</p>'
            + '</div>'
        + '</li>';
    }

    function loadNotes() {
        $('#notesLoading').show();
        $('#notesContent').html('');

        fetch(notesRoutes.list, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                $('#notesLoading').hide();

                if (! data.success) {
                    $('#notesContent').html($('<div class="alert alert-danger mb-0"></div>').text(data.message || 'تعذّر تحميل الملاحظات'));
                    return;
                }

                const notes = data.notes || [];

                $('#notesContent').html(
                    notes.length
                        ? '<ul class="stk-timeline">' + notes.map(noteCard).join('') + '</ul>'
                        : emptyState()
                );
            })
            .catch(function () {
                $('#notesLoading').hide();
                $('#notesContent').html('<div class="alert alert-danger mb-0">تعذّر الاتصال بالخادم</div>');
            });
    }

    function openAddNote() {
        const now = new Date();
        const pad = function (value) { return String(value).padStart(2, '0'); };

        $('#noteDate').val(now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()));
        $('#noteTime').val(pad(now.getHours()) + ':' + pad(now.getMinutes()));
        $('#addNoteModal').modal('show');
    }

    $(function () {
        $('#addNoteFromList').on('click', function () {
            $('#viewNotesModal').modal('hide');
            setTimeout(openAddNote, 300);
        });

        $('#notesContent').on('click', '.js-note-delete', function () {
            const id = $(this).data('id');

            if (! confirm('هل تريد حذف هذه الملاحظة؟')) return;

            fetch(notesRoutes.remove.replace('NOTE_ID', id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (! data.success) { toast(data.message || 'تعذّر الحذف', 'error'); return; }

                    $('#note-' + id).remove();
                    if (! $('#notesContent .stk-timeline__item').length) $('#notesContent').html(emptyState());
                    toast('حُذفت الملاحظة', 'success');
                })
                .catch(function () { toast('تعذّر الاتصال بالخادم', 'error'); });
        });

        $('#addNoteForm').on('submit', function (e) {
            e.preventDefault();

            const form = this;
            const button = $(form).find('button[type="submit"]');
            const label = button.html();

            button.prop('disabled', true).html('<i class="feather icon-loader"></i> جارٍ الحفظ…');

            fetch(notesRoutes.add, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (! data.success) { toast(data.message || 'تعذّر الحفظ', 'error'); return; }

                    $('#addNoteModal').modal('hide');
                    form.reset();
                    toast('أُضيفت الملاحظة', 'success');

                    if ($('#viewNotesModal').hasClass('show')) loadNotes();
                })
                .catch(function () { toast('تعذّر الاتصال بالخادم', 'error'); })
                .finally(function () { button.prop('disabled', false).html(label); });
        });

        $('#addNoteModal').on('hidden.bs.modal', function () { $('#addNoteForm')[0].reset(); });
        $('#viewNotesModal').on('show.bs.modal', loadNotes);
    });

    window.addNoteOrAttachment = openAddNote;
    window.viewAllNotes = function () { $('#viewNotesModal').modal('show'); };
})();
</script>
@endpush
