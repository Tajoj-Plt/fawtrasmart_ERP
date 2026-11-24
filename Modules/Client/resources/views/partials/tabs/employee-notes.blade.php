{{-- تبويب «ملاحظات الموظفين» — بطاقاتٌ بتصميم «ملاحظات الزيارات» و«الأنشطة والزيارات»
     (visit_note_cards.css، ثلاثٌ في الصف). تُصيَّر هنا أوّلاً، ثم يُعيد
     `public/js/client/employee-notes.js` رسمَها بالقالب نفسه بعد كل إضافة —
     غيّر البطاقتين معاً. تسعُ بطاقاتٍ ثم «عرض الكل» (`data-vn-more` في `activity-log`).

     كان في آخر هذا الملف نسخةٌ ثانية من ذلك السكربت تُعرّف `const EmployeeNotesManager`
     مرّةً ثانية في الصفحة نفسها — فيسقط أحدُ التعريفين بخطأ «already declared».
     وكان السكربت يطلب `/clients/clients_management/{id}/employee-notes` نصّاً ثابتاً
     بلا بادئة اللغة ولا مسار التثبيت؛ صار يقرأ المسارين من `data-url` أدناه. --}}
@php
    $enRows = $client->employeeNotes->loadMissing('employee')->sortByDesc('created_at')->values();
    // رقمُ الفاتورة المرتبطة — استعلامٌ واحد لا واحدٌ لكل بطاقة
    $enInvoiceCodes = \App\Models\Invoice::whereIn('id', $enRows->pluck('invoice_id')->filter())->pluck('code', 'id');
    // نفسُ صلاحيات مسار `clients.storeEmployeeNote`
    $enCanAdd = canAny([
        'client_follow_up_add_notes_attachments_appointments_all',
        'client_follow_up_add_notes_attachments_appointments_assigned',
        'client_follow_up_add_notes_attachments_appointments_own',
        'clients_add_employee_note',
    ]);
@endphp
<div class="tab-pane" id="employee-notes" role="tabpanel">
    <div class="vn-wrap">
        <div class="vn-section-head d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="vn-section-title">
                <span class="vn-section-icon"><i class="fas fa-user-edit"></i></span>
                <div>
                    موظفو العميل
                    <span class="vn-section-count"><span id="employee-notes-total">{{ $enRows->count() }}</span> موظف</span>
                </div>
            </div>
            @if ($enCanAdd)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEmployeeNoteModal">
                    <i class="fa fa-user-plus me-1"></i>إضافة موظف العميل
                </button>
            @endif
        </div>

        <div id="employee-notes-container"
            data-url="{{ route('clients.getEmployeeNotes', $client->id) }}"
            data-store-url="{{ route('clients.storeEmployeeNote', $client->id) }}">
            @if ($enRows->isNotEmpty())
                <div class="vn-grid" id="vn-employee-notes-grid">
                    @foreach ($enRows as $note)
                        @php
                            $enAt = $note->created_at;
                            $enImage = $note->image ? asset($note->image) : null;
                            $enInvoiceCode = $note->invoice_id ? ($enInvoiceCodes[$note->invoice_id] ?? $note->invoice_id) : null;
                        @endphp
                        <article class="vn-card" @if ($loop->iteration > 9) hidden data-vn-extra @endif>
                            <header class="vn-head">
                                <span class="vn-num vn-num-{{ ['success', 'primary', 'warning'][$loop->index % 3] }}">{{ $loop->iteration }}</span>
                                <div class="vn-who">
                                    <div class="vn-title">
                                        <span class="vn-title-icon"><i class="fas fa-user"></i></span>
                                        <span title="{{ $note->employee_name }}">{{ $note->employee_name ?: 'غير محدد' }}</span>
                                    </div>
                                    <div class="vn-sub">
                                        <span><i class="fas fa-briefcase"></i>{{ $note->employee_position ?: 'موظف' }}</span>
                                        @if ($note->employee_phone)
                                            <span><i class="fas fa-phone"></i><a href="tel:{{ $note->employee_phone }}"><bdi dir="ltr">{{ $note->employee_phone }}</bdi></a></span>
                                        @endif
                                    </div>
                                </div>
                                <div class="vn-when">
                                    @if ($enAt)
                                        <span><i class="fas fa-calendar-alt"></i>{{ $enAt->format('Y-m-d') }}</span>
                                        <span><i class="fas fa-clock"></i>{{ $enAt->format('h:i') }} {{ $enAt->format('A') === 'AM' ? 'ص' : 'م' }}</span>
                                    @endif
                                    @if ($note->is_primary)
                                        <span class="vn-status vn-status-done"><i class="fas fa-star"></i>رئيسي</span>
                                    @else
                                        <span class="vn-status vn-status-muted"><i class="fas fa-id-badge"></i>موظف العميل</span>
                                    @endif
                                </div>
                            </header>

                            <div class="vn-note" title="{{ $note->note }}">
                                <p><span class="vn-note-label">ملاحظة:</span> {{ $note->note ?: 'لا توجد ملاحظات' }}</p>
                                <span class="vn-note-icon"><i class="fas fa-file-alt"></i></span>
                            </div>

                            <div class="vn-facts">
                                <div class="vn-fact">
                                    <div class="vn-fact-label"><i class="fas fa-user-tie vn-ic-doc"></i> أضافها</div>
                                    @if ($note->employee)
                                        <div class="vn-doc-kind">{{ $note->employee->name }}</div>
                                    @else
                                        <div class="vn-empty">—</div>
                                    @endif
                                </div>
                                <div class="vn-fact">
                                    <div class="vn-fact-label"><i class="fas fa-file-invoice vn-ic-comp"></i> الفاتورة</div>
                                    @if ($enInvoiceCode)
                                        <div class="vn-doc-kind">فاتورة بيع</div>
                                        <a class="vn-doc-no" href="{{ route('invoices.show', $note->invoice_id) }}" target="_blank" rel="noopener"><bdi dir="ltr">#{{ $enInvoiceCode }}</bdi></a>
                                    @else
                                        <div class="vn-empty">—</div>
                                    @endif
                                </div>
                                <div class="vn-fact">
                                    <div class="vn-fact-label"><i class="fas fa-image vn-ic-custody"></i> الصورة</div>
                                    @if ($enImage)
                                        <a href="{{ $enImage }}" data-fancybox="employee-notes" data-caption="{{ $note->employee_name }}">
                                            <img class="vn-photo" src="{{ $enImage }}" alt="" loading="lazy">
                                        </a>
                                    @else
                                        <span class="vn-photo-empty"><i class="fas fa-image"></i></span>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($enRows->count() > 9)
                    <div class="vn-more-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-vn-more="vn-employee-notes-grid">
                            <i class="fa fa-chevron-down me-1"></i>عرض كل الملاحظات ({{ $enRows->count() }})
                        </button>
                    </div>
                @endif
            @else
                <div class="empty-state">
                    <i class="fas fa-user-edit"></i>
                    <h5>لا توجد ملاحظات</h5>
                    <p>لم تُضف ملاحظات عن موظفي هذا العميل بعد</p>
                </div>
            @endif
        </div>
    </div>
</div>
