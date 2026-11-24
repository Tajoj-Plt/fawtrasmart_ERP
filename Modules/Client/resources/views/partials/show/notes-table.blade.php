@if (isset($ClientRelations) && $ClientRelations->count() > 0)
    <div class="d-flex justify-content-between align-items-center mb-4">
        {{-- <h6 class="mb-0">
            <i class="fa fa-sticky-note me-2" style="color: var(--primary-color);"></i>
            إجمالي <span id="notes-count">{{ $ClientRelations->total() }}</span> ملاحظة
        </h6> --}}
        <div class="d-flex gap-2">
            <a href="{{ route('clients.notes.pdf', $client->id) }}" target="_blank"
                class="btn btn-outline-primary btn-sm">
                <i class="fa fa-file-pdf me-1"></i>طباعة PDF
            </a>
            <a href="{{ route('appointment.notes.create', $client->id) }}"
                class="btn btn-primary btn-sm">
                <i class="fa fa-plus me-1"></i>إضافة ملاحظة
            </a>
        </div>
    </div>

   <div class="row">
        @foreach ($ClientRelations as $note)
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

                        <!-- معلومات إضافية -->
                        @if ($note->deposit_count || $note->site_type || $note->competitor_documents)
                            <div class="additional-info bg-light rounded p-3 mb-3">
                                <div class="row">
                                    @if ($note->deposit_count)
                                        <div class="col-md-4 mb-2">
                                            <small style="color: var(--primary-color);">
                                                <i class="fa fa-boxes me-1"></i>عدد العهدة:
                                            </small>
                                            <strong
                                                class="d-block">{{ $note->deposit_count }}</strong>
                                        </div>
                                    @endif
                                    @if ($note->site_type)
                                        <div class="col-md-4 mb-2">
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
                                        <div class="col-md-4 mb-2">
                                            <small style="color: var(--primary-color);">
                                                <i class="fa fa-file-contract me-1"></i>استندات
                                                المنافسين:
                                            </small>
                                            <strong
                                                class="d-block">{{ $note->competitor_documents }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- المنافسون المرصودون مع هذه الملاحظة — نفس ما تعرضه
                             بطاقة الملاحظة في تبويب الملاحظات
                             (`partials/tabs/note-card.blade.php`). --}}
                        @if ($note->competitorObservations->isNotEmpty())
                            <div class="mb-3">
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

                        <!-- المرفقات -->
                        @php
                            $files = json_decode($note->attachments, true);
                            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                        @endphp

                        @if (is_array($files) && count($files) > 0)
                            <div class="attachments-section mb-3">
                                <h6 class="mb-3">
                                    <i class="fa fa-paperclip me-2"
                                        style="color: var(--primary-color);"></i>
                                    المرفقات ({{ count($files) }})
                                </h6>
                                <div class="row g-3">
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
        @endforeach
    </div>
    @if($ClientRelations instanceof \Illuminate\Pagination\LengthAwarePaginator && $ClientRelations->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="pagination-info text-muted">
                عرض {{ $ClientRelations->firstItem() ?? 0 }} إلى {{ $ClientRelations->lastItem() ?? 0 }} من {{ $ClientRelations->total() }} ملاحظة
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ $ClientRelations->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $ClientRelations->currentPage() - 1 }}"
                           data-tab="notes"
                           {{ $ClientRelations->onFirstPage() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-right"></i>
                        </a>
                    </li>

                    @php
                        $start = max($ClientRelations->currentPage() - 2, 1);
                        $end = min($start + 4, $ClientRelations->lastPage());
                        if ($end - $start < 4) {
                            $start = max($end - 4, 1);
                        }
                    @endphp

                    @for ($i = $start; $i <= $end; $i++)
                        <li class="page-item {{ $ClientRelations->currentPage() == $i ? 'active' : '' }}">
                            <a class="page-link pagination-link"
                               href="#"
                               data-page="{{ $i }}"
                               data-tab="notes">
                                {{ $i }}
                            </a>
                        </li>
                    @endfor

                    <li class="page-item {{ !$ClientRelations->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $ClientRelations->currentPage() + 1 }}"
                           data-tab="notes"
                           {{ !$ClientRelations->hasMorePages() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-left"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    @endif
@else
    <div class="empty-state">
        <i class="fas fa-sticky-note"></i>
        <h5>لا توجد ملاحظات</h5>
        <p>لم يتم إضافة أي ملاحظات لهذا العميل بعد</p>
        <a href="{{ route('appointment.notes.create', $client->id) }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>إضافة ملاحظة جديدة
        </a>
    </div>
@endif
