{{-- تبويبة الملاحظات لفاتورة الشراء --}}
<div id="notesContainer">
    <div class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">{{ __('common.loading') }}</span>
        </div>
        <p class="text-muted mt-3">{{ __('common.loading') }}...</p>
    </div>
</div>

<script>
(function() {
    'use strict';

    const documentId = {{ $documentId }};
    const container = document.getElementById('notesContainer');

    // جلب الملاحظات عند تحميل الصفحة
    function loadNotes() {
        fetch(`{{ route('invoicePurchases.getNotes', '') }}/${documentId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.notes && data.notes.length > 0) {
                    renderNotes(data.notes);
                } else {
                    container.innerHTML = `
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            {{ __('purchase_orders.empty.no_notes') }}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('خطأ في جلب الملاحظات:', error);
                container.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        {{ __('purchase_orders.error.load_notes') }}
                    </div>
                `;
            });
    }

    // عرض الملاحظات
    function renderNotes(notes) {
        let notesHtml = '';

        notes.forEach((note, index) => {
            const processName = note.process || 'غير محدد';
            const employeeName = note.employee_name || 'غير محدد';
            const noteDate = note.date || 'غير محدد';
            const noteTime = note.time || 'غير محدد';
            const description = note.description || 'لا يوجد وصف';
            const hasAttachment = note.has_attachment ? true : false;
            const attachmentUrl = note.attachment_url || '';

            notesHtml += `
                <div class="card mb-3 border-start border-primary border-3" id="note-${note.id}">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="badge bg-primary me-2">${index + 1}</span>
                                    <h6 class="mb-0 text-primary fw-bold">${processName}</h6>
                                </div>
                                <small class="text-muted d-block">
                                    <i class="fas fa-user me-1 text-info"></i><strong>{{ __('purchase_orders.fields.employee') }}:</strong> ${employeeName}
                                </small>
                                <small class="text-muted d-block">
                                    <i class="fas fa-calendar me-1 text-success"></i><strong>{{ __('purchase_orders.fields.date') }}:</strong> ${noteDate}
                                    <i class="fas fa-clock me-2 ms-3 text-warning"></i><strong>{{ __('purchase_orders.fields.time') }}:</strong> ${noteTime}
                                </small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p class="mb-2">
                                <strong>{{ __('purchase_orders.fields.description') }}:</strong>
                            </p>
                            <p class="text-secondary">${escapeHtml(description)}</p>
                        </div>
                        ${hasAttachment ? `
                            <div class="mt-2">
                                <a href="${attachmentUrl}" class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="fas fa-download me-1"></i>{{ __('purchase_orders.actions.download_attachment') }}
                                </a>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        container.innerHTML = notesHtml;
    }

    // دالة مساعدة لـ escape النصوص HTML
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // تحميل الملاحظات عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', loadNotes);
})();
</script>
