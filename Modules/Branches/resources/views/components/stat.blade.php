{{-- بطاقة رقم واحدة — بترميز بطاقات المبيعات الإحصائية (`sl-stat`):
     بطاقةٌ مستقلّة لكل رقم، أيقونتُها في مربّعٍ ملوَّن إلى جانبه، والرقمُ
     كبيراً فوق تسميته. تُلفّ بـ <div class="sl-stats">.

     كانت شريطاً واحداً موصولاً (`sl-stats`) بأرقامٍ صغيرة في وسط
     خاناتها — وهو مظهرُ المخزون لا المبيعات، فتبدو شاشةُ الفروع من نظامٍ
     آخر وهي تعرض الشيء نفسه.

     والواجهة هنا لم تتغيّر بحرف: كل `<x-branches::stat>` في الشاشات يعمل
     كما كان، والتبديل داخليٌّ بحت. --}}
@props([
    'label' => '',
    'value' => 0,
    'icon' => 'activity',
    'tone' => 'primary',       // primary | success | info | warning | danger
    'note' => null,            // سطر صغير أسفل التسمية
    'noteIcon' => null,
    'noteTone' => null,        // up | down — يلوّن السطر الصغير
    'title' => null,           // تلميح عند المرور
])

<div class="sl-stat" @if ($title) title="{{ $title }}" @endif>
    <span class="sl-stat__icon {{ $tone !== 'primary' ? 'sl-stat__icon--'.$tone : '' }}">
        <i class="feather icon-{{ $icon }}"></i>
    </span>

    <div class="sl-stat__body">
        <p class="sl-stat__value">{{ is_numeric($value) ? number_format($value) : $value }}</p>
        <p class="sl-stat__label">{{ $label }}</p>

        @if ($note)
            <p class="sl-stat__note {{ $noteTone }}">
                <i class="feather icon-{{ $noteIcon ?? 'corner-down-left' }}"></i>
                <span>{{ $note }}</span>
            </p>
        @endif
    </div>
</div>
