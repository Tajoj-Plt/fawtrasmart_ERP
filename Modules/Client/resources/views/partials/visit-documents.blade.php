{{-- مستندات هذه الزيارة بأزرار طباعتها — تُسلَّم للعميل من داخل الزيارة.
     المصدر `VisitWorkspaceController::visitDocuments()`؛ يُضمَّن في بطاقة
     الملخص وفي تبويب «المستندات». `data-ws-allow` على الروابط: الطباعة ليست
     إجراءً على الزيارة، فلا يقفلها `wsApplyLock()` ولو عُرضت قبل البدء. --}}
@forelse ($visitDocuments as $doc)
    <div class="ws-doc-row">
        <div class="ws-doc-icon"><i class="fas {{ $doc['icon'] }}"></i></div>
        <div class="flex-grow-1 min-w-0">
            <div class="fw-bold small">{{ $doc['kind'] }} <span class="text-muted">{{ $doc['number'] }}</span>@if (! empty($doc['status']))<span class="badge {{ $doc['status']['class'] }} ms-1">{{ $doc['status']['text'] }}</span>@endif</div>
            {{-- كلُّ قيمةٍ في `<bdi>` مستقلّ: الوقت «06:22 PM» والمبلغ رقمان
                 لاتينيان وسط سطرٍ عربيّ، فكان اتجاه النصّ يخلطهما
                 («PM — 62.10 06:22»). --}}
            <small class="text-muted">
                <bdi dir="ltr">{{ $doc['at']?->format(($showDate ?? false) ? 'Y-m-d h:i A' : 'h:i A') }}</bdi>
                @if ($doc['amount'] !== null) · <bdi dir="ltr">{{ number_format((float) $doc['amount'], 2) }}</bdi> @endif
            </small>
        </div>
        <div class="d-flex gap-1 justify-content-end" data-ws-allow>
            {{-- إجراءاتٌ على المستند نفسه (للفاتورة: «إنشاء مرتجع») — عبر
                 `openDocument` فيعود المندوب إلى الزيارة بعد الحفظ --}}
            @foreach ($doc['actions'] ?? [] as $action)
                <a href="{{ $action['url'] }}" class="btn btn-sm btn-outline-warning py-0 text-nowrap">
                    <i class="fas {{ $action['icon'] }} me-1"></i>{{ $action['label'] }}
                </a>
            @endforeach
            @if (count($doc['prints']) === 1)
                <a href="{{ $doc['prints'][0]['url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary py-0 text-nowrap">
                    <i class="fas fa-print me-1"></i>طباعة
                </a>
            @elseif (count($doc['prints']) > 1)
                {{-- المطبوعات المعيَّنة افتراضيّاً فقط (قالبٌ لكل مقاس ورق، أو
                     طباعة النظام حين لا افتراضيّ) — `visitDocuments()`. --}}
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 dropdown-toggle text-nowrap" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-print me-1"></i>طباعة
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end ws-print-menu">
                        @foreach ($doc['prints'] as $print)
                            <li>
                                <a class="dropdown-item small" href="{{ $print['url'] }}" target="_blank" rel="noopener">
                                    <i class="fas fa-print me-1 text-muted"></i>{{ $print['label'] }}
                                    @if (! empty($print['hint']))<span class="badge bg-light text-dark border ms-1">{{ $print['hint'] }}</span>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <small class="text-muted text-nowrap"><i class="fas fa-lock me-1"></i>لا تملك صلاحية طباعته</small>
            @endif
        </div>
    </div>
@empty
    {{-- `$emptyText`: تبويبات مستندات العميل تمرّر نصّها؛ بطاقة الزيارة تبقى على نصّها --}}
    <p class="text-muted small mb-0">
        {{ $emptyText ?? 'لم يُصدَر مستندٌ في هذه الزيارة بعد. ما تُصدره من «إجراءات سريعة» يظهر هنا بزرّ طباعته.' }}
    </p>
@endforelse
