{{-- عرض ملفاتٍ للقراءة. الوسائط: $paths، $images (bool). --}}
@php use App\Support\DocumentAttachments as Files; @endphp
@if (empty($paths))
    <p class="text-muted mb-0">لا مرفقات.</p>
@elseif (! empty($images))
    <div class="re-gallery">
        @foreach ($paths as $path)
            <a href="{{ Files::url($path) }}" target="_blank" rel="noopener" style="background-image:url('{{ Files::url($path) }}')" aria-label="صورة"></a>
        @endforeach
    </div>
@else
    <ul class="re-files">
        @foreach ($paths as $path)
            <li><i class="feather icon-paperclip"></i><a href="{{ Files::url($path) }}" target="_blank" rel="noopener">{{ basename($path) }}</a></li>
        @endforeach
    </ul>
@endif
