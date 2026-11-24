{{--
    «أُنهيت زياراتهم اليوم» — العملاء الذين أُخفوا عن المندوب بعد «إنهاء الزيارة».

    بلا هذا الشريط يختفي العميل من الجدول والخريطة ولا يعرف المندوب أين ذهب
    ولا كيف يعيده. الإخفاء ليومه وحده (خطّ سير الغد يُظهره)، و«إرجاع لخط
    السير» يفتح له صفّاً مفتوحاً اليوم فيعود ظاهراً —
    `VisitWorkspaceController::returnToItinerary()`.

    `<details>` لا `collapse` من Bootstrap: الشاشة تحمّل إصدارين من Bootstrap
    معاً، وسمات `data-toggle`/`data-bs-toggle` لا تعمل إلا مع أحدهما.

    الإعدادان: «إخفاء العميل…» يقرّر ظهور الشريط أصلاً (المتحكّم يمرّر قائمةً
    فارغة متى أُطفئ)، و«السماح بإرجاعه…» يقرّر الزرّ.
--}}
@if (isset($finishedTodayClients) && $finishedTodayClients->isNotEmpty())
    <details class="card cl-finished-today mb-3">
        <summary class="d-flex align-items-center gap-2 px-3 py-2" style="cursor:pointer; list-style:none;">
            <i class="fas fa-circle-check text-success"></i>
            <strong>أُنهيت زياراتهم اليوم</strong>
            <span class="badge bg-success">{{ $finishedTodayClients->count() }}</span>
            <small class="text-muted ms-auto">
                مخفيّون من قائمتك وخريطتك بقيّة اليوم{{ $canReturnFinishedClients ? ' — اضغط لإرجاع أحدهم' : '' }}
            </small>
        </summary>

        <div class="card-body pt-0 pb-2">
            @foreach ($finishedTodayClients as $finishedClient)
                <div class="d-flex align-items-center justify-content-between gap-2 border-top py-2">
                    <span>
                        {{ $finishedClient->trade_name }}
                        <small class="text-muted">#{{ $finishedClient->code }}</small>
                    </span>
                    @if ($canReturnFinishedClients)
                        <form method="POST" action="{{ route('clients.returnToItinerary', $finishedClient->id) }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2 text-nowrap">
                                <i class="fas fa-rotate-left me-1"></i> إرجاع لخط السير
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach

            <small class="cl-help d-block mt-1">
                الإرجاع يُظهر العميل من جديد اليوم ويبدأ له زيارةً جديدة عند فتحه. ومن لم تُرجعه يظهر في خطّ سير الغد كالمعتاد.
            </small>
        </div>
    </details>
@endif
