<!-- بطاقة الملاحظة الفردية -->
<div class="col-12 mb-3">
    <div class="info-card note-card-item">
        <div class="card-body">
            <!-- رأس الملاحظة -->
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h6 class="mb-1">
                        <i class="fa fa-user me-2"
                            style="color: var(--primary-color);"></i>
                        {{ $note->employee->name ?? 'غير معروف' }}
                    </h6>
                    @if ($note->clientEmployee)
                        <small class="text-muted d-block mb-2">
                            <i class="fa fa-users me-1"></i>
                            موظف العميل: <strong>
                                @php
                                    $employee = $note->clientEmployee->employee;
                                    $name = $employee ? ($employee->nickname ?? ($employee->first_name . ' ' . $employee->middle_name)) : 'غير معروف';
                                @endphp
                                {{ $name }}
                            </strong>
                            @if($note->clientEmployee->employee_name)
                                <span class="badge bg-info ms-2">
                                    <i class="fa fa-id-card me-1"></i>{{ $note->clientEmployee->employee_name }}
                                </span>
                            @endif
                            @if($note->clientEmployee->is_primary)
                                <span class="badge bg-success ms-2">
                                    <i class="fa fa-star me-1"></i>رئيسي
                                </span>
                            @endif
                        </small>
                    @endif
                    @if ($note->process)
                        <span class="badge"
                            style="background-color: var(--primary-color);">
                            <i class="fa fa-tag me-1"></i>{{ $note->process }}
                        </span>
                    @endif
                </div>
                <small class="text-muted">
                    <i class="fa fa-clock me-1"></i>
                    {{ $note->created_at->format('Y-m-d H:i') }}
                </small>
            </div>

            <hr>

            <!-- وصف الملاحظة -->
            <div class="note-description mb-3">
                <p class="mb-0">{{ $note->description ?? 'لا يوجد وصف' }}</p>
            </div>
            {{-- المنافسون المرصودون — أوّلَ ما يُقرأ بعد نصّ الملاحظة لا أسفل
                 المرفقات: الصورة تُزيحه خارج الشاشة فلا يُراه أحد. راجع
                 `ClientRelation::competitorObservations()`. يظهر القسم فقط حين
                 يوجد رصد، فلا يُثقل الملاحظات التي بلا منافسين. --}}
            @if ($note->relationLoaded('competitorObservations') ? $note->competitorObservations->isNotEmpty() : $note->competitorObservations()->exists())
                <div class="mt-3 pt-2 border-top">
                    <small class="text-muted d-block mb-2">
                        <i class="fa fa-crosshairs me-1 text-danger"></i>المنافسون المرصودون
                    </small>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($note->competitorObservations as $observation)
                            <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
                                {{-- صورة كل منافس ظاهرة — لا صورةٌ واحدة للرصد كلّه --}}
                                @if ($observation->photo_path)
                                    <a href="{{ asset('assets/uploads/visit_competitors/'.$observation->photo_path) }}" target="_blank" rel="noopener">
                                        <img src="{{ asset('assets/uploads/visit_competitors/'.$observation->photo_path) }}" alt="" loading="lazy" style="width:36px;height:36px;object-fit:cover;border-radius:6px;">
                                    </a>
                                @endif
                                <i class="fa fa-store me-1 text-danger"></i>{{ $observation->display_name }}
                                @if ($observation->asset_count !== null)
                                    <span class="text-muted">— عهدته: {{ $observation->asset_count }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- معلومات إضافية -->
            @if ($note->deposit_count || $note->site_type || $note->competitor_documents || $note->clientEmployee)
                <div class="additional-info bg-light rounded p-3 mb-3">
                    <div class="row">
                        <!-- بيانات الملاحظة -->
                        @if ($note->deposit_count)
                            <div class="col-md-3 mb-2">
                                <small style="color: var(--primary-color);">
                                    <i class="fa fa-boxes me-1"></i>عدد العهدة:
                                </small>
                                <strong
                                    class="d-block">{{ $note->deposit_count }}</strong>
                            </div>
                        @endif
                        @if ($note->site_type)
                            <div class="col-md-3 mb-2">
                                <small style="color: var(--primary-color);">
                                    <i class="fa fa-store me-1"></i>نوع الموقع:
                                </small>
                                <strong class="d-block">
                                    @switch($note->site_type)
                                        @case('independent_booth')
                                            بسطة مستقلة
                                        @break

                                        @case('grocery')
                                            بقالة
                                        @break

                                        @case('supplies')
                                            تموينات
                                        @break

                                        @case('markets')
                                            أسواق
                                        @break

                                        @case('station')
                                            محطة
                                        @break

                                        @default
                                            {{ $note->site_type }}
                                    @endswitch
                                </strong>
                            </div>
                        @endif
                        @if ($note->competitor_documents)
                            <div class="col-md-3 mb-2">
                                <small style="color: var(--primary-color);">
                                    <i class="fa fa-file-contract me-1"></i>استندات:
                                </small>
                                <strong
                                    class="d-block">{{ $note->competitor_documents }}</strong>
                            </div>
                        @endif

                        <!-- بيانات الموظف -->
                        @if ($note->clientEmployee)
                            <div class="col-md-3 mb-2">
                                <small style="color: var(--primary-color);">
                                    <i class="fa fa-user me-1"></i>الموظف:
                                </small>
                                <strong class="d-block">{{ $note->clientEmployee->employee_name }}</strong>
                                @if($note->clientEmployee->is_primary)
                                    <span class="badge bg-success mt-1">رئيسي</span>
                                @endif
                            </div>
                            @if ($note->clientEmployee->employee_phone)
                                <div class="col-md-3 mb-2">
                                    <small style="color: var(--primary-color);">
                                        <i class="fa fa-phone me-1"></i>الجوال:
                                    </small>
                                    <a href="tel:{{ $note->clientEmployee->employee_phone }}" class="text-info text-decoration-none d-block">
                                        <strong>{{ $note->clientEmployee->employee_phone }}</strong>
                                    </a>
                                </div>
                            @endif
                            @if ($note->clientEmployee->employee_position)
                                <div class="col-md-3 mb-2">
                                    <small style="color: var(--primary-color);">
                                        <i class="fa fa-briefcase me-1"></i>الوظيفة:
                                    </small>
                                    <strong class="d-block">{{ $note->clientEmployee->employee_position }}</strong>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

            {{-- الفاتورة المرتبطة — فاتورةُ الملاحظة نفسها، وإلّا فاتورةُ مسؤول
                 العميل **في يوم الملاحظة وحده**: `client_employee_notes.invoice_id`
                 صفٌّ مشتركٌ بين كل ملاحظات ذلك المسؤول، فبلا شرط اليوم تظهر
                 أوّلُ فاتورةٍ صدرت للعميل في كل ملاحظةٍ بعدها. نفس قاعدة
                 `App\Services\Clients\VisitNoteCards::documentsFor()`. --}}
            @php
                $relatedInvoice = $note->invoice_id ? \App\Models\Invoice::find($note->invoice_id) : null;

                if (! $relatedInvoice && $note->clientEmployee?->invoice_id) {
                    $contactInvoice = \App\Models\Invoice::find($note->clientEmployee->invoice_id);
                    $noteDay = optional($note->created_at)->toDateString();
                    $relatedInvoice = $contactInvoice
                        && in_array(optional($contactInvoice->created_at)->toDateString(), [$noteDay, (string) $note->date], true)
                            ? $contactInvoice
                            : null;
                }
            @endphp
            @if ($relatedInvoice)
                    <div class="invoice-link bg-info bg-opacity-10 rounded p-3 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-file-invoice-dollar text-info me-2 fs-5"></i>
                            <div class="flex-grow-1">
                                <small class="text-muted d-block">الفاتورة المرتبطة</small>
                                <strong class="text-dark">
                                    الفاتورة #{{ $relatedInvoice->invoice_number ?? $relatedInvoice->id }}
                                </strong>
                                <small class="text-muted d-block">
                                    {{ $relatedInvoice->created_at->format('Y-m-d') }}
                                </small>
                            </div>
                            <a href="{{ route('invoices.show', $relatedInvoice->id) }}"
                               class="btn btn-outline-info btn-sm ms-2" target="_blank">
                                <i class="fa fa-external-link"></i> عرض
                            </a>
                        </div>
                    </div>
            @endif

            <!-- المرفقات -->
            @php
                $files = json_decode($note->attachments, true);
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            @endphp

            @if (is_array($files) && count($files) > 0 || ($note->clientEmployee && $note->clientEmployee->image))
                <div class="attachments-section mb-3">
                    <h6 class="mb-3">
                        <i class="fa fa-paperclip me-2"
                            style="color: var(--primary-color);"></i>
                        المرفقات
                    </h6>
                    <div class="row g-3">
                        <!-- صورة الموظف -->
                        @if ($note->clientEmployee && $note->clientEmployee->image)
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                <div class="position-relative">
                                    @php
                                        // تحقق من المسار - قد يكون مسار كامل أو اسم ملف فقط
                                        $imagePath = str_contains($note->clientEmployee->image, 'uploads/client_notes/')
                                            ? asset($note->clientEmployee->image)
                                            : asset('uploads/client_notes/' . $note->clientEmployee->image);
                                    @endphp
                                    <a href="{{ $imagePath }}"
                                        data-fancybox="gallery-{{ $note->id }}"
                                        data-caption="صورة الموظف: {{ $note->clientEmployee->employee_name }}">
                                        <div class="attachment-image-wrapper">
                                            <img src="{{ $imagePath }}"
                                                alt="{{ $note->clientEmployee->employee_name }}"
                                                class="img-thumbnail attachment-img">
                                            <div class="attachment-overlay">
                                                <i class="fa fa-search-plus"></i>
                                            </div>
                                        </div>
                                    </a>
                                    <small class="d-block text-center mt-2 text-muted">
                                        <i class="fa fa-user"></i> الموظف
                                    </small>
                                </div>
                            </div>
                        @endif

                        <!-- صور الملاحظة -->
                        @if (is_array($files) && count($files) > 0)
                            @foreach ($files as $file)
                                @php
                                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                    $fileUrl = asset('assets/uploads/notes/' . $file);
                                    $fileName = basename($file);
                                @endphp

                                @if (in_array($ext, $imageExtensions))
                                    <!-- عرض الصور -->
                                    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                        <a href="{{ $fileUrl }}"
                                            data-fancybox="gallery-{{ $note->id }}"
                                            data-caption="{{ $fileName }}">
                                            <div class="attachment-image-wrapper">
                                                <img src="{{ $fileUrl }}"
                                                    alt="{{ $fileName }}"
                                                    class="img-thumbnail attachment-img">
                                                <div class="attachment-overlay">
                                                    <i class="fa fa-search-plus"></i>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                @else
                                    <!-- عرض الملفات الأخرى -->
                                    <div class="col-12 col-sm-6 col-md-4">
                                        <a href="{{ $fileUrl }}" target="_blank"
                                            class="btn btn-unified btn-sm w-100 text-truncate"
                                            title="{{ $fileName }}">
                                            <i
                                                class="fa fa-file-{{ $ext == 'pdf' ? 'pdf' : 'alt' }} me-2"></i>
                                            {{ Str::limit($fileName, 20) }}
                                        </a>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif

            <!-- أزرار الإجراءات -->
            <div class="note-actions pt-3 border-top">
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-primary btn-sm print-note"
                        data-note-id="{{ $note->id }}">
                        <i class="fa fa-print me-1"></i>طباعة
                    </button>
                    @if(auth()->user()->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
                        (auth()->user()->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == auth()->id()))
                    <a href="{{ route('clients.notes.edit', $note->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-edit me-1"></i>تعديل
                    </a>
                    <form action="{{ route('clients.notes.destroy', $note->id) }}"
                        method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('هل أنت متأكد من حذف هذه الملاحظة؟')">
                            <i class="fa fa-trash me-1"></i>حذف
                        </button>
                    </form>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
