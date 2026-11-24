{{-- بطاقات «ملاحظات الزيارات» — ثلاثٌ في الصف. تُصيَّر مع الشاشة وتُعاد وحدها
     عند الترقيم (فرع AJAX في `ClientController::show()`)، فالترقيم هنا.
     `$ClientRelations` صفحةٌ من الملاحظات **القائدة** — بطاقةٌ لكل زيارة. --}}
@php
    $vnCurrency = ($account_setting ?? \App\Models\AccountSetting::where('user_id', auth()->id())->first())?->currency;
    $vnCurrencySymbol = empty($vnCurrency) || $vnCurrency === 'SAR'
        ? '<img src="' . asset('assets/images/Saudi_Riyal.svg') . '" alt="ريال سعودي" width="14">'
        : e($vnCurrency);
    $vnOffset = max(0, (int) $ClientRelations->firstItem() - 1);
@endphp
<div class="vn-grid">
    @foreach ($ClientRelations as $note)
        @include('client::partials.tabs.visit-note-card', [
            'note' => $note,
            'card' => $visitNoteCards[$note->id],
            'number' => $vnOffset + $loop->iteration,
        ])
    @endforeach
</div>

<div id="notes-pagination-container" class="mt-3">
    {!! $ClientRelations->links('client::partials.client-notes-pagination') !!}
</div>
