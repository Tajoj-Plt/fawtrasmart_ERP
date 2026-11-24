{{-- منتقي أقسام البيانات — مشترك بين شاشة الإعدادات ونافذة «نسخة الآن».

     ما يُرسَل مفتاحٌ معتَّم لا اسم قسم ولا اسم جدول: مصدر الصفحة يجب ألّا
     يكشف بنية النظام أكثر ممّا يكشفه ما يُعرَض فيها.

     المتغيّرات:
       $sections  الأقسام من DataSectionCatalog::forPicker()
       $name      اسم الحقل المرسَل (included_sections | excluded_sections | sections)
       $selected  المفاتيح المؤشَّرة ابتداءً
       $id        بادئة فريدة — الشاشة قد تعرض أكثر من منتقٍ
--}}
@php
    $selected = collect($selected ?? []);
    $id = $id ?? 'sp';
@endphp

<div class="bk-picker" id="{{ $id }}">
    <div class="bk-picker-bar">
        <input type="text" class="form-control form-control-sm bk-picker-search"
            placeholder="{{ __('settings.backup.search_section') }}" autocomplete="off">
        <button type="button" class="btn btn-sm btn-outline-secondary bk-picker-all">{{ __('settings.backup.select_all') }}</button>
        <button type="button" class="btn btn-sm btn-outline-secondary bk-picker-none">{{ __('settings.backup.select_none') }}</button>
        <span class="bk-picker-count cj-sub"></span>
    </div>

    <div class="bk-picker-list bk-sec-grid">
        @forelse ($sections as $section)
            {{-- البحث في `data-name` يطابق العنوان والوصف معاً: من يبحث عن
                 «الرواتب» يجب أن يجد «الموظفون والحضور والرواتب» --}}
            <label class="bk-picker-item bk-sec" data-name="{{ $section['label'] }} {{ $section['hint'] }}">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $section['key'] }}"
                    {{ $selected->contains($section['key']) ? 'checked' : '' }}>
                <span class="bk-sec-body">
                    <span class="bk-sec-title">
                        <i class="feather icon-{{ $section['icon'] }}"></i>
                        {{ $section['label'] }}
                    </span>
                    <span class="cj-sub bk-sec-hint">{{ $section['hint'] }}</span>
                    <span class="cj-sub bk-sec-meta">
                        {{ __('settings.backup.section_meta', [
                            'records' => number_format($section['rows']),
                            'size' => \App\Models\Backup::formatBytes($section['bytes']),
                        ]) }}
                    </span>
                </span>
            </label>
        @empty
            <div class="p-2 cj-sub">{{ __('settings.backup.sections_unreadable') }}</div>
        @endforelse
    </div>
</div>
