{{-- شريط الشرح — بشكلَين، كما في شاشات المبيعات:

       • `primary` إرشادٌ يُقرأ مرّةً: لوحةٌ قابلةٌ للطيّ (`sl-guide`)، مطويّةٌ
         افتراضياً. من يفتح الشاشة جاء لعمله لا لقراءة شرحها، ومن أراد
         الشرح وجده بنقرة.
       • `warning` و`danger` تنبيهٌ يجب أن يُرى: يبقى ظاهراً دائماً.

     يجيب على «ما هذه الشاشة؟ وماذا يحدث إذا ضغطت تنفيذ؟»: الشاشات الحسّاسة
     (الضم، النقل، الترحيل) كانت تنفّذ عمليات تمسّ آلاف العملاء بلا سطر
     واحد يشرح أثرها قبل التنفيذ. --}}
@props([
    'tone' => 'primary',   // primary | warning | danger
    'icon' => null,
    'title' => null,
    'open' => false,       // للإرشاد وحده: أيُفتح من البداية؟
])

@php
    $toneIcon = $icon ?? [
        'primary' => 'icon-help-circle',
        'warning' => 'icon-alert-triangle',
        'danger' => 'icon-alert-octagon',
    ][$tone] ?? 'icon-info';
@endphp

@if ($tone === 'primary' && $title)
    <details class="sl-guide" @if ($open) open @endif>
        <summary>
            <i class="feather {{ $toneIcon }}" aria-hidden="true"></i>
            {{ $title }}
        </summary>
        <div class="sl-guide__body">{{ $slot }}</div>
    </details>
@else
    <div class="alert alert-{{ $tone === 'primary' ? 'info' : $tone }} sl-note" role="alert">
        <i class="feather {{ $toneIcon }}"></i>
        <div style="flex: 1; min-width: 0;">
            @if ($title)
                <strong class="d-block">{{ $title }}</strong>
            @endif
            {{ $slot }}
        </div>
    </div>
@endif
