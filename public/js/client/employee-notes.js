/**
 * Employee Notes Manager
 * إدارة ملاحظات الموظفين للعملاء
 *
 * البطاقة هنا نسخةٌ من بطاقة `partials/tabs/employee-notes.blade.php`
 * (تصميم «ملاحظات الزيارات»، visit_note_cards.css) — غيّرهما معاً.
 * المساران من `data-url`/`data-store-url` على `#employee-notes-container`،
 * فيحملان بادئة اللغة ومسار التثبيت بدل نصٍّ ثابت.
 */

const EmployeeNotesManager = {
    clientId: null,
    VISIBLE: 9,

    init(clientId) {
        this.clientId = clientId;
        this.setupEventListeners();
        this.loadNotes();
    },

    container() {
        return document.getElementById('employee-notes-container');
    },

    url(kind) {
        const container = this.container();
        const fromPage = container && container.getAttribute(kind === 'store' ? 'data-store-url' : 'data-url');

        return fromPage || `/clients/clients_management/${this.clientId}/employee-notes`;
    },

    setupEventListeners() {
        // معاينة الصورة عند اختيارها
        $('#noteImageInput').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#previewImg').attr('src', e.target.result);
                    $('#imagePreview').show();
                };
                reader.readAsDataURL(file);
            }
        });

        // إزالة الصورة
        $('#removeImageBtn').on('click', function() {
            $('#noteImageInput').val('');
            $('#previewImg').attr('src', '');
            $('#imagePreview').hide();
        });

        // إرسال النموذج
        $('#employeeNoteForm').on('submit', (e) => {
            e.preventDefault();
            this.saveNote();
        });

        // تحميل الملاحظات عند فتح التبويبة
        $('#employee-notes-tab').on('shown.bs.tab', () => {
            this.loadNotes();
        });
    },

    async loadNotes() {
        if (!this.container()) return;

        try {
            const response = await fetch(this.url('index'), { headers: { 'Accept': 'application/json' } });
            const data = await response.json();

            if (data.success) {
                this.renderNotes(data.notes);
                $('#employee-notes-count').text(data.notes.length);
                $('#employee-notes-total').text(data.notes.length);
                // التبويب مخفيٌّ بلا ملاحظات — أوّل ملاحظة تُضاف تُظهره
                if (data.notes.length) $('#employee-notes-tab').closest('.nav-item').show();
            }
        } catch (error) {
            console.error('خطأ في تحميل الملاحظات:', error);
            this.showError('فشل تحميل الملاحظات');
        }
    },

    async saveNote() {
        const form = $('#employeeNoteForm')[0];
        const formData = new FormData(form);

        const submitBtn = $('#employeeNoteForm button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fa fa-spinner fa-spin me-1"></i>جاري الحفظ...').prop('disabled', true);

        try {
            const response = await fetch(this.url('store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                toastr.success('تمت إضافة موظف العميل بنجاح');
                $('#addEmployeeNoteModal').modal('hide');
                form.reset();
                $('#imagePreview').hide();
                this.loadNotes();
            } else {
                toastr.error(data.message || 'فشل حفظ موظف العميل');
            }
        } catch (error) {
            console.error('خطأ في حفظ موظف العميل:', error);
            toastr.error('حدث خطأ أثناء حفظ موظف العميل');
        } finally {
            submitBtn.html(originalText).prop('disabled', false);
        }
    },

    renderNotes(notes) {
        const container = $(this.container());
        const e = (text) => this.escapeHtml(text);

        if (!notes || notes.length === 0) {
            container.html(`
                <div class="empty-state">
                    <i class="fas fa-user-edit"></i>
                    <h5>لا توجد ملاحظات</h5>
                    <p>لم تُضف ملاحظات عن موظفي هذا العميل بعد</p>
                </div>
            `);
            return;
        }

        const tones = ['success', 'primary', 'warning'];
        let html = '<div class="vn-grid" id="vn-employee-notes-grid">';

        notes.forEach((note, index) => {
            const extra = index >= this.VISIBLE ? ' hidden data-vn-extra' : '';
            const status = note.is_primary
                ? '<span class="vn-status vn-status-done"><i class="fas fa-star"></i>رئيسي</span>'
                : '<span class="vn-status vn-status-muted"><i class="fas fa-id-badge"></i>موظف العميل</span>';

            html += `
                <article class="vn-card"${extra}>
                    <header class="vn-head">
                        <span class="vn-num vn-num-${tones[index % 3]}">${index + 1}</span>
                        <div class="vn-who">
                            <div class="vn-title">
                                <span class="vn-title-icon"><i class="fas fa-user"></i></span>
                                <span title="${e(note.employee_name)}">${e(note.employee_name || 'غير محدد')}</span>
                            </div>
                            <div class="vn-sub">
                                <span><i class="fas fa-briefcase"></i>${e(note.employee_position || 'موظف')}</span>
                                ${note.employee_phone ? `<span><i class="fas fa-phone"></i><a href="tel:${e(note.employee_phone)}"><bdi dir="ltr">${e(note.employee_phone)}</bdi></a></span>` : ''}
                            </div>
                        </div>
                        <div class="vn-when">
                            <span><i class="fas fa-calendar-alt"></i>${e(note.created_date)}</span>
                            <span><i class="fas fa-clock"></i>${e(note.created_time)}</span>
                            ${status}
                        </div>
                    </header>

                    <div class="vn-note" title="${e(note.note)}">
                        <p><span class="vn-note-label">ملاحظة:</span> ${e(note.note || 'لا توجد ملاحظات')}</p>
                        <span class="vn-note-icon"><i class="fas fa-file-alt"></i></span>
                    </div>

                    <div class="vn-facts">
                        <div class="vn-fact">
                            <div class="vn-fact-label"><i class="fas fa-user-tie vn-ic-doc"></i> أضافها</div>
                            ${note.created_by ? `<div class="vn-doc-kind">${e(note.created_by)}</div>` : '<div class="vn-empty">—</div>'}
                        </div>
                        <div class="vn-fact">
                            <div class="vn-fact-label"><i class="fas fa-file-invoice vn-ic-comp"></i> الفاتورة</div>
                            ${note.invoice_url ? `
                                <div class="vn-doc-kind">فاتورة بيع</div>
                                <a class="vn-doc-no" href="${e(note.invoice_url)}" target="_blank" rel="noopener"><bdi dir="ltr">#${e(note.invoice_code)}</bdi></a>
                            ` : '<div class="vn-empty">—</div>'}
                        </div>
                        <div class="vn-fact">
                            <div class="vn-fact-label"><i class="fas fa-image vn-ic-custody"></i> الصورة</div>
                            ${note.image_url ? `
                                <a href="${e(note.image_url)}" data-fancybox="employee-notes" data-caption="${e(note.employee_name)}">
                                    <img class="vn-photo" src="${e(note.image_url)}" alt="" loading="lazy">
                                </a>
                            ` : '<span class="vn-photo-empty"><i class="fas fa-image"></i></span>'}
                        </div>
                    </div>
                </article>
            `;
        });

        html += '</div>';

        if (notes.length > this.VISIBLE) {
            html += `
                <div class="vn-more-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-vn-more="vn-employee-notes-grid">
                        <i class="fa fa-chevron-down me-1"></i>عرض كل الملاحظات (${notes.length})
                    </button>
                </div>
            `;
        }

        container.html(html);

        // تفعيل Fancybox للصور
        if (typeof Fancybox !== 'undefined') {
            Fancybox.bind("[data-fancybox='employee-notes']", {
                buttons: ["zoom", "slideShow", "fullScreen", "close"]
            });
        }
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML.replace(/"/g, '&quot;');
    },

    showError(message) {
        $(this.container()).html(`
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-triangle me-2"></i>
                ${message}
            </div>
        `);
    }
};

// تفعيل المدير عند تحميل الصفحة
$(document).ready(function() {
    if (typeof window.currentClient !== 'undefined' && window.currentClient.id) {
        EmployeeNotesManager.init(window.currentClient.id);
    }
});
