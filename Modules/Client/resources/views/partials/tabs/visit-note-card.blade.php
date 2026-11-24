{{-- بطاقة «ملاحظة الزيارة» — الزيارة كلّها: ملاحظتها ومستنداتها ورصد المنافسين.
     البيانات `$card` من `App\Services\Clients\VisitNoteCards::build()`؛ نظيرتها
     في إدارة علاقات العملاء `generateNotesHTML()` في `crm_shared_scripts_details`
     وفي `Itinerary/components/itinerary-today`، والتنسيق المشترك
     `public/assets/css/visit_note_cards.css` — غيّرها معاً.
     `$note` الملاحظة القائدة: التعديل والطباعة والحذف لها وحدها. --}}
@php
    $vnTone = ['success', 'primary', 'warning'][($number - 1) % 3];
    $vnDocs = $card['documents'];
    $vnCanEdit = auth()->user()->can('client_follow_up_edit_delete_notes_attachments_appointments_all')
        || (auth()->user()->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == auth()->id());
    $vnAttachCount = count($card['images']) + count($card['files']);
@endphp
<article class="vn-card">
    <header class="vn-head">
        <span class="vn-num vn-num-{{ $vnTone }}">{{ $number }}</span>
        <div class="vn-who">
            <div class="vn-title">
                <span class="vn-title-icon"><i class="fas fa-user"></i></span>
                <span title="{{ $card['employee'] }}">{{ $card['employee'] }}</span>
            </div>
            <div class="vn-sub">
                @if ($card['process'])
                    <span><i class="fas fa-tag"></i>{{ $card['process'] }}</span>
                @endif
                @if ($card['site_type'])
                    <span><i class="fas fa-store"></i>{{ $card['site_type'] }}</span>
                @endif
                @if ($card['visit']['number'] ?? null)
                    <span><i class="fas fa-hashtag"></i><bdi>{{ $card['visit']['number'] }}</bdi></span>
                @endif
                @if ($card['entries'] > 1)
                    <span title="ملاحظة الزيارة وملاحظات مستنداتها في بطاقةٍ واحدة"><i class="fas fa-layer-group"></i>ملاحظات مدمجة: {{ $card['entries'] }}</span>
                @endif
            </div>
        </div>
        <div class="vn-when">
            @if ($card['date_label'])
                <span><i class="fas fa-calendar-alt"></i>{{ $card['date_label'] }}</span>
            @endif
            @if ($card['time_label'])
                <span><i class="fas fa-clock"></i>{{ $card['time_label'] }}</span>
            @endif
            @if ($card['visit'])
                <span class="vn-status vn-status-{{ $card['visit']['tone'] }}">
                    <i class="fas {{ $card['visit']['icon'] }}"></i>{{ $card['visit']['label'] }}
                </span>
            @endif
        </div>
    </header>

    <div class="vn-note" title="{{ $card['description'] }}">
        <p><span class="vn-note-label">ملاحظة:</span> {{ $card['description'] !== '' ? $card['description'] : 'لا يوجد وصف' }}</p>
        <span class="vn-note-icon"><i class="fas fa-file-alt"></i></span>
    </div>

    <div class="vn-facts">
        <div class="vn-fact">
            <div class="vn-fact-label"><i class="fas fa-file-invoice vn-ic-doc"></i> المستندات</div>
            @forelse (array_slice($vnDocs, 0, 3) as $vnDoc)
                <div class="vn-doc">
                    <div class="vn-doc-kind"><i class="fas {{ $vnDoc['icon'] }}"></i> {{ $vnDoc['kind'] }}</div>
                    <a class="vn-doc-no" href="{{ $vnDoc['url'] }}" target="_blank" rel="noopener"><bdi dir="ltr">#{{ $vnDoc['number'] }}</bdi></a>
                    <div class="vn-amount vn-amount-{{ $vnDoc['tone'] }}"><bdi dir="ltr">{{ number_format($vnDoc['amount'], 2) }}</bdi> {!! $vnCurrencySymbol !!}</div>
                </div>
            @empty
                <div class="vn-empty">لم يتم إنشاء مستند</div>
            @endforelse
            @if (count($vnDocs) > 3)
                <span class="vn-more">+{{ count($vnDocs) - 3 }} مستند آخر في الزيارة</span>
            @endif
        </div>

        <div class="vn-fact">
            <div class="vn-fact-label"><i class="fas fa-chart-bar vn-ic-comp"></i> المنافس</div>
            {{-- كلُّ منافسٍ بعهدته وصورته — لا «+N منافس» ولا صورٌ مخفيّة --}}
            @forelse ($card['competitors'] as $vnCompetitor)
                <div class="vn-comp-item">
                    @if ($vnCompetitor['photo_url'])
                        <a href="{{ $vnCompetitor['photo_url'] }}" data-fancybox="vn-comp-{{ $note->id }}" data-caption="{{ $vnCompetitor['name'] }}">
                            <img class="vn-comp-thumb" src="{{ $vnCompetitor['photo_url'] }}" alt="" loading="lazy">
                        </a>
                    @else
                        <span class="vn-comp-thumb vn-comp-thumb-empty"><i class="fas fa-image"></i></span>
                    @endif
                    <div class="vn-comp-body">
                        <div class="vn-line"><span title="{{ $vnCompetitor['name'] }}">{{ $vnCompetitor['name'] }}</span></div>
                        <div class="vn-count"><b>{{ $vnCompetitor['asset_count'] }}</b> {{ $vnCompetitor['asset_label'] }}</div>
                    </div>
                </div>
            @empty
                {{-- «لا يوجد منافس» مع صورة إثباته إن صوّرها المندوب --}}
                <div class="vn-empty">{{ $card['no_competitor'] ? 'لا يوجد منافس' : 'لم يُرصد' }}</div>
                @foreach ($card['no_competitor_photos'] ?? [] as $vnProof)
                    <a href="{{ $vnProof }}" data-fancybox="vn-comp-{{ $note->id }}" data-caption="لا يوجد منافس">
                        <img class="vn-comp-thumb" src="{{ $vnProof }}" alt="" loading="lazy">
                    </a>
                @endforeach
            @endforelse
        </div>

        <div class="vn-fact">
            <div class="vn-fact-label"><i class="fas fa-cube vn-ic-custody"></i> العهدة</div>
            @if ($card['custody']['count'] !== null)
                <div class="vn-count"><b>{{ $card['custody']['count'] }}</b> {{ $card['custody']['label'] }}</div>
            @else
                <div class="vn-empty">—</div>
            @endif
            {{-- صور الملاحظة كلُّها ظاهرة: الأولى كبيرة والباقي مصغّرات تحتها --}}
            @foreach ($card['images'] as $vnImage)
                @if ($loop->first)
                    <a href="{{ $vnImage }}" data-fancybox="vn-note-{{ $note->id }}">
                        <img class="vn-photo" src="{{ $vnImage }}" alt="" loading="lazy">
                    </a>
                    @if (! $loop->last)<div class="vn-thumbs">@endif
                @else
                    <a href="{{ $vnImage }}" data-fancybox="vn-note-{{ $note->id }}">
                        <img class="vn-thumb" src="{{ $vnImage }}" alt="" loading="lazy">
                    </a>
                    @if ($loop->last)</div>@endif
                @endif
            @endforeach
        </div>
    </div>

    <footer class="vn-foot">
        <span class="vn-foot-meta">
            @if ($vnAttachCount)
                <i class="fas fa-paperclip"></i> مرفقات: {{ $vnAttachCount }}
            @endif
        </span>
        <div class="vn-actions">
            @foreach ($card['files'] as $vnFile)
                <a class="vn-act" href="{{ $vnFile['url'] }}" target="_blank" rel="noopener" title="{{ $vnFile['name'] }}"><i class="fas fa-file-download"></i></a>
            @endforeach
            <a class="vn-act" href="{{ route('clients.notes.print', ['noteId' => $note->id]) }}" target="_blank" rel="noopener" title="طباعة"><i class="fas fa-print"></i></a>
            @if ($vnCanEdit)
                <a class="vn-act" href="{{ route('clients.notes.edit', $note->id) }}" title="تعديل"><i class="fas fa-edit"></i></a>
                <form action="{{ route('clients.notes.destroy', $note->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="vn-act vn-act-danger" title="حذف"
                        onclick="return confirm('هل أنت متأكد من حذف هذه الملاحظة؟')"><i class="fas fa-trash"></i></button>
                </form>
            @endif
        </div>
    </footer>
</article>
