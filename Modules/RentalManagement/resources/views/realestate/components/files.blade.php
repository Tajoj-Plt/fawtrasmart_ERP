{{--
    حقل رفع ملفات مع عرض الموجود وإمكان إزالته. الوسائط: $name (images|attachments)،
    $label، $model (اختياري)، $images (bool) لعرض مصغّرات.
--}}
@php
    use App\Support\DocumentAttachments as Files;
    $existing = isset($model) && $model->exists ? $model->filePaths($name) : [];
@endphp
<div class="form-group">
    <label for="{{ $name }}">{{ $label }}</label>
    <input type="file" name="{{ $name }}[]" id="{{ $name }}" class="form-control" multiple
           accept="{{ ! empty($images) ? 'image/*' : '.'.implode(',.', Files::EXTENSIONS) }}">
    <small class="text-muted">حتى {{ Files::MAX_FILES }} ملفات — {{ Files::MAX_KILOBYTES / 1024 }}MB للملف.</small>

    @if ($existing)
        @if (! empty($images))
            <div class="re-gallery mt-1">
                @foreach ($existing as $path)
                    <span class="re-gallery__item" style="background-image:url('{{ Files::url($path) }}')">
                        <label title="إزالة"><input type="checkbox" name="remove_{{ $name }}[]" value="{{ $path }}"> حذف</label>
                    </span>
                @endforeach
            </div>
        @else
            <ul class="re-files mt-1">
                @foreach ($existing as $path)
                    <li>
                        <i class="feather icon-paperclip"></i>
                        <a href="{{ Files::url($path) }}" target="_blank" rel="noopener">{{ basename($path) }}</a>
                        <label class="mb-0 ms-auto text-danger"><input type="checkbox" name="remove_{{ $name }}[]" value="{{ $path }}"> حذف</label>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
