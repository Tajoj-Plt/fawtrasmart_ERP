{{--
    زرُّ «+» بجانب قائمة اختيار: يفتح شاشةَ إضافة ما تعرضه القائمة في تبويبٍ
    جديد، فلا يغادر المستخدمُ النموذجَ الذي يملؤه ولا يضيع ما أدخله.

    محروسٌ بالصلاحية نفسِها التي يحرس بها مسارُ الإضافة، وبوجود المسار —
    زرٌّ يقود إلى ٤٠٣ أو إلى وحدةٍ غير مثبّتة أسوأ من غيابه.

    المتغيّرات: $route، $permission، $title، و$icon اختياريّ (افتراضه plus).
--}}
@permission($permission)
    @if (Route::has($route))
        <a href="{{ route($route) }}" target="_blank" rel="noopener"
           class="btn btn-outline-primary rsv-select-add__btn" title="{{ $title }}" aria-label="{{ $title }}">
            <i class="feather icon-{{ $icon ?? 'plus' }}"></i>
        </a>
    @endif
@endpermission
