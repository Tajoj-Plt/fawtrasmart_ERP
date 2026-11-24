<!-- تبويب ملاحظات الزيارات — الملاحظة ورصد المنافسين معاً -->
{{-- كانا تبويبين («الملاحظات» و«المنافسون») يُقرأ كلٌّ منهما بمعزلٍ عن الآخر،
     والرصدُ جزءٌ من ملاحظة الزيارة — فصار في بطاقتها. البطاقة `visit-note-card`
     وبياناتها `App\Services\Clients\VisitNoteCards`؛ نظيرها في إدارة علاقات
     العملاء `generateNotesHTML()` في `crm_shared_scripts_details`. --}}
<div class="tab-pane" id="notes" role="tabpanel">
    @php
        $vnObservations = $competitorObservations ?? collect();
        // الوضع الحالي: آخر رصدٍ لكل منافس (القائمة مرتّبة الأحدث أولاً)
        $vnCompetitorsNow = $vnObservations->whereNotNull('competitor_id')->unique('competitor_id')->values();
        $vnTotal = isset($ClientRelations) ? $ClientRelations->total() : 0;
    @endphp
    <div class="vn-wrap">
        <div class="vn-section-head">
            <div class="vn-section-title">
                <span class="vn-section-icon"><i class="fas fa-clipboard-list"></i></span>
                <div>
                    ملاحظات الزيارات
                    <span class="vn-section-count" id="notes-total-count">{{ $vnTotal }} ملاحظة · {{ $vnObservations->count() }} رصد منافسين</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                @if ($vnTotal > 0)
                    <a href="{{ route('clients.notes.pdf', $client->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-file-pdf me-1"></i>طباعة PDF
                    </a>
                @endif
                <a href="{{ route('appointment.notes.create', $client->id) }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus me-1"></i>إضافة ملاحظة
                </a>
            </div>
        </div>

        @if ($vnCompetitorsNow->isNotEmpty())
            <div class="vn-comp-strip">
                <span class="vn-comp-strip-label"><i class="fas fa-crosshairs me-1"></i>المنافسون عند العميل الآن:</span>
                @foreach ($vnCompetitorsNow as $vnObs)
                    <span class="vn-chip" title="آخر رصد: {{ $vnObs->created_at?->format('Y-m-d') }}">
                        {{ $vnObs->display_name }} · عهدة <b>{{ (int) $vnObs->asset_count }}</b>
                    </span>
                @endforeach
            </div>
        @endif

        @if ($vnTotal > 0)
            <div id="notes-list-container" class="position-relative">
                <div id="notes-loading-overlay" class="position-absolute top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center"
                     style="background: rgba(255,255,255,0.7); z-index: 10;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">جاري التحميل...</span>
                    </div>
                </div>

                <div id="notes-content-wrapper">
                    @include('client::partials.tabs.notes-list')
                </div>
            </div>
        @else
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h5>لا توجد ملاحظات</h5>
                <p>الملاحظة ورصد المنافسين يُسجَّلان من زيارة العميل</p>
            </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const notesContainer = document.getElementById('notes-list-container');
        if (!notesContainer) return;

        notesContainer.addEventListener('click', function(e) {
            const link = e.target.closest('.custom-notes-pagination a');
            if (link) {
                e.preventDefault();
                loadNotes(link.getAttribute('href'));
            }
        });

        function loadNotes(url) {
            const overlay = document.getElementById('notes-loading-overlay');
            const wrapper = document.getElementById('notes-content-wrapper');

            overlay.classList.remove('d-none');
            overlay.classList.add('d-flex');

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                // `html` يحمل البطاقات والترقيم معاً (notes-list)
                wrapper.innerHTML = data.html;
                notesContainer.scrollIntoView({ behavior: 'smooth' });
            })
            .catch(error => {
                console.error('Error loading notes:', error);
                toastr.error('حدث خطأ أثناء تحميل الملاحظات');
            })
            .finally(() => {
                overlay.classList.add('d-none');
                overlay.classList.remove('d-flex');
            });
        }
    });
</script>
