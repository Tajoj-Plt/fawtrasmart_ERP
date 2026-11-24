{{--
    ملخّصُ أخطاء النموذج — في رأس الشاشة لا عند الحقل وحدَه.

    الرسالةُ تحت الحقل وحدَها تُفوَّت: النموذجُ أطولُ من الشاشة، والحقلُ
    الخاطئ قد يكون تحت الطيّ، فيضغط المستخدمُ «حفظ» فتعود الصفحةُ كما هي
    بلا شيءٍ ظاهر — فيظنّ أن الزرّ لا يعمل.

    ويُضاف إليه ما يصل في `session('error')` من المتحكّم (فشلُ حفظٍ لا
    فشلُ تحقّق)، فمصدرا الخطأ يظهران في موضعٍ واحد.

    @props:
      title   عنوانُ الصندوق
      bag     اسمُ حقيبة الأخطاء (لغير الافتراضية)
--}}
@props([
    'title' => 'تعذّر الحفظ',
    'bag' => 'default',
])

@php
    $messages = $errors->getBag($bag)->all();
    $sessionError = session('error');
@endphp

@if ($messages || $sessionError)
    <div class="stk-form-errors" role="alert" aria-live="assertive">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-octagon"></i>
            <strong>{{ $title }}</strong>
            @if (count($messages) > 1)
                <span class="stk-count">{{ count($messages) }}</span>
            @endif
        </div>

        <ul class="stk-form-errors__list">
            @if ($sessionError)
                <li>{{ $sessionError }}</li>
            @endif
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>

    @once
        @push('scripts')
            <script>
                // نُمرّر إلى أوّل حقلٍ خاطئ: الملخّصُ يقول ما الخطأ، والتمرير
                // يقول أين هو — والنموذجُ الطويل يُخفي الثاني.
                document.addEventListener('DOMContentLoaded', function () {
                    var first = document.querySelector('.is-invalid, .stk-form-errors');
                    if (first) {
                        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    var field = document.querySelector('.is-invalid');
                    if (field && typeof field.focus === 'function') {
                        setTimeout(function () { field.focus({ preventScroll: true }); }, 400);
                    }
                });
            </script>
        @endpush
    @endonce
@endif
