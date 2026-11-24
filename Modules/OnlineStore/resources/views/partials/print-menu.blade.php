{{--
    قائمة «المطبوعات» — تُبنى من قوالب المستندات المضبوطة في الإعدادات.
    نسخة مطابقة لـ Modules\Purchases\resources\views\partials\print-menu.blade.php.

    @param \Illuminate\Support\Collection $templates
    @param \App\Models\DocumentTemplate|null $default
    @param bool $prefersPdf
    @param string $printRoute
    @param string $pdfRoute
    @param int|string $documentId
--}}
<div class="dropdown d-inline-block">
    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="feather icon-printer"></i> طباعة
    </button>

    <ul class="dropdown-menu dropdown-menu-end">
        @if ($templates->isNotEmpty())
            <li><h6 class="dropdown-header">قوالب من الإعدادات</h6></li>

            @foreach ($templates as $template)
                <li>
                    <a class="dropdown-item d-flex align-items-center justify-content-between gap-2"
                        href="{{ $prefersPdf ? route($pdfRoute, [$documentId, $template->id]) : route($printRoute, [$documentId, $template->id]) }}"
                        @unless ($prefersPdf) target="_blank" rel="noopener" @endunless>
                        <span><i class="feather icon-file-text me-2"></i>{{ $template->name }}</span>
                        @if ($template->is_default)
                            <span class="badge badge-light-success"><i class="feather icon-star"></i></span>
                        @endif
                    </a>
                </li>
            @endforeach

            <li><hr class="dropdown-divider"></li>
        @endif

        <li>
            <a class="dropdown-item" href="{{ route($printRoute, [$documentId, $default?->id ?? 'default']) }}" target="_blank" rel="noopener">
                <i class="feather icon-eye me-2"></i> معاينة الطباعة
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route($pdfRoute, [$documentId, $default?->id ?? 'default']) }}">
                <i class="feather icon-download me-2"></i> تنزيل PDF
            </a>
        </li>
    </ul>
</div>
