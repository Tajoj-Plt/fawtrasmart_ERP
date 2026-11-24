{{--
    قائمة «المطبوعات» — تُبنى من قوالب المستندات المضبوطة في الإعدادات.

    القائمة محدودة الارتفاع وتُمرَّر داخل نفسها، فعشرون قالباً لا تغطّي
    الصفحة، وعنوانها يثبت أثناء التمرير.

    @param \Illuminate\Support\Collection $templates
    @param \App\Models\DocumentTemplate|null $default
    @param bool $prefersPdf          خيار «طريقة الطباعة» في إعدادات الحساب
    @param string $printRoute        اسم مسار المعاينة
    @param string $pdfRoute          اسم مسار التنزيل
    @param int|string $documentId
--}}
<div class="dropdown">
    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown"
        aria-expanded="false">
        <i class="fas fa-print"></i><span>{{ __('purchase_cycle.actions.print') }}</span>
    </button>

    <ul class="dropdown-menu dropdown-menu-end po-print-menu">
        @if ($templates->isNotEmpty())
            <li>
                <h6 class="dropdown-header">{{ __('purchase_cycle.templates.from_settings') }}</h6>
            </li>

            @foreach ($templates as $template)
                <li>
                    {{-- المساران يفتحان صفحة طباعة (تُحفظ PDF من المتصفّح)، فكلاهما في لسانٍ جديد --}}
                    <a class="dropdown-item d-flex align-items-center justify-content-between gap-2"
                        href="{{ $prefersPdf ? route($pdfRoute, [$documentId, $template->id]) : route($printRoute, [$documentId, $template->id]) }}"
                        target="_blank" rel="noopener">
                        <span><i class="fas fa-file-alt me-2 text-primary"></i>{{ $template->name }}</span>
                        @if ($template->is_default)
                            <span class="po-status po-status--approved"><i class="fas fa-star"></i></span>
                        @endif
                    </a>
                </li>
            @endforeach

            <li>
                <hr class="dropdown-divider">
            </li>
        @endif

        <li>
            {{-- المعاينة والتنزيل يتبعان القالب الافتراضي المضبوط في الإعدادات --}}
            <a class="dropdown-item" href="{{ route($printRoute, [$documentId, $default?->id ?? 'default']) }}"
                target="_blank" rel="noopener">
                <i class="fas fa-eye me-2 text-info"></i>{{ __('purchase_cycle.actions.print_preview') }}
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route($pdfRoute, [$documentId, $default?->id ?? 'default']) }}"
                target="_blank" rel="noopener">
                <i class="fas fa-file-pdf me-2 text-danger"></i>{{ __('purchase_cycle.actions.download_pdf') }}
            </a>
        </li>
    </ul>
</div>
